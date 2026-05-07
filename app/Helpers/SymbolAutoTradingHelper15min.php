<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OptionStrikeSelection;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class SymbolAutoTradingHelper15min
{
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Main process - Detect signals and create orders
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting Symbol Auto Trading Signal Detection (15-Minute) ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = ZerodhaAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active configs");

            // Get all active monitored symbols for 15-minute interval
            $symbols = SymbolMonitored::where('is_active', true)
                ->where('interval', '15minute')
                ->get();

            if ($symbols->isEmpty()) {
                Log::info('No active 15-minute symbols found');
                return;
            }

            Log::info("✅ Found {$symbols->count()} active 15-minute symbols");

            // Group symbols by broker
            $symbolsByBroker = $symbols->groupBy('broker_api_id');

            foreach ($symbolsByBroker as $brokerId => $brokerSymbols) {
                $broker = BrokerApi::find($brokerId);
                
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} has invalid token, skipping");
                    continue;
                }

                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log::info("Processing {$brokerSymbols->count()} symbols for broker: {$broker->client_name}");

                foreach ($brokerSymbols as $symbol) {
                    Log::info("🔍 Checking symbol: {$symbol->trading_symbol} (Symbol: {$symbol->symbol})");
                    
                    $config = $this->findConfigForSymbol($configs, $symbol);
                    
                    if ($config) {
                        Log::info("   ✅ Config found: ID {$config->id}, Strategy: {$config->signal_strategy}");
                        $this->checkAndCreateOrder($config, $symbol, $broker, $testDate);
                    } else {
                        Log::warning("   ❌ NO CONFIG FOUND for user_id: {$symbol->user_id}");
                    }
                }
            }

            Log::info('=== Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('Signal Processing Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Find matching config for symbol
     */
    private function findConfigForSymbol($configs, SymbolMonitored $symbol)
    {
        // Match by user_id AND broker_api_id
        $config = $configs->first(function($config) use ($symbol) {
            return $config->user_id === $symbol->user_id 
                && $config->broker_api_id === $symbol->broker_api_id;
        });

        if ($config) {
            return $config;
        }

        // Fallback to broker_api_id only
        $config = $configs->first(function($config) use ($symbol) {
            return $config->broker_api_id === $symbol->broker_api_id;
        });

        if ($config) {
            Log::info("   ⚠️ Using broker-level config (user_id mismatch)");
            return $config;
        }

        return null;
    }

    /**
     * Get previous trading day's last candle
     */
    private function getPreviousDayLastCandle($brokerId, $tradingSymbol, $testDate = null)
    {
        try {
            $targetDate = $testDate ? Carbon::parse($testDate) : Carbon::today();

            $lastCandle = SymbolData::where('broker_api_id', $brokerId)
                ->where('trading_symbol', $tradingSymbol)
                ->where('interval', '15minute')
                ->where('timestamp', '<', $targetDate->format('Y-m-d 00:00:00'))
                ->whereNotNull('supertrend')
                ->orderBy('timestamp', 'DESC')
                ->first();

            if ($lastCandle) {
                Log::info("📅 [PREV] Previous day candle: {$lastCandle->timestamp}");
                Log::info("  ST Signal: {$lastCandle->supertrend_signal}");
            } else {
                Log::info("📅 [PREV] No previous day data found");
            }

            return $lastCandle;

        } catch (\Exception $e) {
            Log::error("❌ [PREV] Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check and create order
     */
    private function checkAndCreateOrder(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    {
        try {
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [CHECK] Starting check for: {$symbol->trading_symbol}");
            Log::info("   Strategy: {$config->signal_strategy}");
            Log::info("   Quality Filter: " . ($config->enable_quality_filter ? 'ENABLED ✅' : 'DISABLED ⚠️'));
            
            $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '15minute', $testDate);
            Log::info("📊 [CHECK] Found {$todayCandles->count()} candles today");

            if ($todayCandles->count() < 1) {
                Log::warning("❌ [CHECK] Not enough data");
                return;
            }

            // ✅ GET PREVIOUS DAY'S LAST CANDLE
            $previousDayLastCandle = $this->getPreviousDayLastCandle($broker->id, $symbol->trading_symbol, $testDate);

            $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('status', true)
                ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info("📋 [CHECK] Existing orders: {$todayOrders->count()}");

            // ✅ SIMPLIFIED SYNC FINDER (MATCHES BACKTESTING)
            $allSyncPoints = $this->findAllSynchronizationPoints(
                $todayCandles, 
                $symbol->trading_symbol, 
                $config->signal_strategy,
                $config,
                $previousDayLastCandle
            );

            Log::info("📊 [CHECK] Total sync points: " . count($allSyncPoints));

            if (empty($allSyncPoints)) {
                Log::warning("❌ [CHECK] No sync points found");
                return;
            }

            $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints, $testDate);
            Log::info("📊 [CHECK] Recent sync points: " . count($recentSyncPoints));

            if (empty($recentSyncPoints)) {
                Log::warning("❌ [CHECK] No recent sync points");
                return;
            }

            $validSyncPoint = $this->getNextValidSyncPoint($recentSyncPoints, $todayOrders, $symbol->trading_symbol);

            if (!$validSyncPoint) {
                Log::warning("❌ [CHECK] No new valid sync point");
                return;
            }

            Log::info("✅ [CHECK] Valid sync: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
            
            $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);

        } catch (\Exception $e) {
            Log::error("❌ [CHECK] Exception: " . $e->getMessage());
        }
    }

    /**
     * Get today's candles
     */
    private function getTodayCandles($brokerId, $tradingSymbol, $interval, $testDate = null)
    {
        $query = SymbolData::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->whereNotNull('atr')
            ->whereNotNull('supertrend')
            ->whereNotNull('supertrend_direction')
            ->orderBy('timestamp', 'ASC');

        if ($testDate) {
            if (strpos($testDate, ':') !== false) {
                $date = Carbon::parse($testDate)->startOfDay();
                $query->whereDate('timestamp', $date)
                      ->where('timestamp', '<=', $testDate);
            } else {
                $query->whereDate('timestamp', $testDate);
            }
        } else {
            $today = Carbon::today()->setTime(9, 15, 0);
            $now = Carbon::now();
            $query->whereBetween('timestamp', [$today, $now]);
        }

        return $query->get();
    }

    /**
     * ✅ FIXED: Simple signal change detection (matches backtesting exactly)
     */
    private function findAllSynchronizationPoints($candles, $tradingSymbol, $strategy, ZerodhaAutoConfig $config, $previousDayLastCandle = null)
    {
        $syncPoints = [];
        $totalCandles = count($candles);
        
        Log::info("🔍 [SYNC] Scanning {$totalCandles} candles with strategy: {$strategy}");
        Log::info("🔍 [SYNC] Quality Filter: " . ($config->enable_quality_filter ? 'ENABLED ✅' : 'DISABLED ⚠️'));

        // ✅ Initialize from previous day's last signal
        $lastSignal = null;
        if ($previousDayLastCandle) {
            $lastSignal = $previousDayLastCandle->supertrend_signal;
            Log::info("📅 [SYNC] Starting with previous day signal: {$lastSignal}");
        }

        foreach ($candles as $index => $candle) {
            $currentSignal = $candle->supertrend_signal; // ✅ Use DB signal directly

            // ✅ SUPERTREND Strategy: Only trigger on signal CHANGES
            if ($strategy === 'SUPERTREND' || $strategy === 'BOTH' || $strategy === 'SUPERTREND_VWAP') {
                
                // Detect signal changes: BUY → SELL or SELL → BUY (or initial signal)
                if ($lastSignal !== $currentSignal && in_array($currentSignal, ['BUY', 'SELL'])) {
                    
                    Log::info("✅ [SYNC] Signal change detected: " . ($lastSignal ?? 'null') . " → {$currentSignal} at {$candle->timestamp}");
                    
                    // ✅ APPLY QUALITY FILTER ONLY IF ENABLED
                    $hasQuality = true;
                    if ($config->enable_quality_filter) {
                        $hasQuality = $this->hasQualityMomentum($candles, $index, $currentSignal);
                        
                        if (!$hasQuality) {
                            Log::info("⚠️ [SYNC] {$currentSignal} signal at {$candle->timestamp} REJECTED (quality filter)");
                            $lastSignal = $currentSignal; // Update lastSignal even if rejected
                            continue;
                        }
                        Log::info("✅ [SYNC] {$currentSignal} at index {$index} ({$candle->timestamp}) - QUALITY APPROVED");
                    } else {
                        Log::info("✅ [SYNC] {$currentSignal} at index {$index} ({$candle->timestamp}) - QUALITY FILTER BYPASSED");
                    }
                    
                    $syncPoints[] = [
                        'index' => $index,
                        'candle' => $candle,
                        'signal' => [
                            'type' => $currentSignal,
                            'supertrend' => $currentSignal,
                            'vwap' => $candle->vwap_signal ?? 'N/A',
                            'rsi' => $candle->rsi_signal ?? 'N/A',
                            'price' => $candle->close,
                            'strategy' => $strategy,
                            'quality_approved' => $hasQuality
                        ]
                    ];
                }
                
                // ✅ Update lastSignal for next iteration
                $lastSignal = $currentSignal;
            }
            
            // ✅ Keep other strategies if needed (VWAP, RSI, etc.)
            // Add them here if you have other strategies...
        }

        Log::info("📊 [SYNC] Total sync points found: " . count($syncPoints));
        return $syncPoints;
    }

    /**
     * Check if signal has quality momentum (volume + price consistency)
     * Adapted for 15-minute timeframe
     */
    private function hasQualityMomentum($candles, $currentIndex, $signalType)
    {
        // Need at least 6 previous candles to check
        if ($currentIndex < 6) {
            Log::info("   [QUALITY] Not enough history to validate");
            return false;
        }

        // Get last 6 candles including current (for 15-min, this is 1.5 hours of data)
        $recentCandles = [];
        for ($i = max(0, $currentIndex - 5); $i <= $currentIndex; $i++) {
            $recentCandles[] = $candles[$i];
        }

        // Calculate volume SMA(15) - need 15 candles for this
        $volumeSMA = null;
        if ($currentIndex >= 14) {
            $volumeSum = 0;
            for ($i = $currentIndex - 14; $i <= $currentIndex; $i++) {
                $volumeSum += $candles[$i]->volume;
            }
            $volumeSMA = $volumeSum / 15;
        }

        Log::info("   [QUALITY] Checking momentum for {$signalType} signal");
        
        // CHECK 1: Volume confirmation (at least 4 out of 6 candles)
        $volumePassCount = 0;
        if ($volumeSMA) {
            foreach ($recentCandles as $candle) {
                if ($candle->volume > $volumeSMA) {
                    $volumePassCount++;
                }
            }
            Log::info("   [QUALITY] Volume check: {$volumePassCount}/6 candles above SMA");
            
            if ($volumePassCount < 4) {
                Log::warning("   [QUALITY] ❌ REJECTED: Insufficient volume ({$volumePassCount}/6)");
                return false;
            }
        }

        // CHECK 2: Price momentum consistency (at least 4 out of 5 recent closes)
        $priceConsistencyCount = 0;
        
        for ($i = 1; $i < count($recentCandles); $i++) {
            $prevClose = $recentCandles[$i - 1]->close;
            $currClose = $recentCandles[$i]->close;
            
            if ($signalType === 'BUY') {
                // For BUY: Check if close is trending up
                if ($currClose > $prevClose) {
                    $priceConsistencyCount++;
                }
            } elseif ($signalType === 'SELL') {
                // For SELL: Check if close is trending down
                if ($currClose < $prevClose) {
                    $priceConsistencyCount++;
                }
            }
        }
        
        Log::info("   [QUALITY] Price momentum: {$priceConsistencyCount}/5 consistent closes");
        
        if ($priceConsistencyCount < 4) {
            Log::warning("   [QUALITY] ❌ REJECTED: Weak price momentum ({$priceConsistencyCount}/5)");
            return false;
        }

        // BOTH CHECKS PASSED
        Log::info("   [QUALITY] ✅ APPROVED: Strong momentum confirmed");
        Log::info("   [QUALITY]   - Volume: {$volumePassCount}/6 above SMA");
        Log::info("   [QUALITY]   - Price: {$priceConsistencyCount}/5 consistent");
        
        return true;
    }

    /**
     * Filter to recent sync points
     */
    private function filterRecentSyncPoints($allSyncPoints, $testDate = null)
    {
        if (empty($allSyncPoints)) {
            return [];
        }

        $now = Carbon::now();
        $marketOpen = Carbon::today()->setTime(9, 15, 0);
        
        // First hour of trading - keep ALL signals
        if ($now->diffInMinutes($marketOpen) <= 60 && $now->gte($marketOpen)) {
            Log::info("🕐 [FILTER] First hour - keeping ALL " . count($allSyncPoints) . " sync points");
            return $allSyncPoints;
        }
        
        // After first hour - only recent signals (45 minutes)
        $cutoffTime = $now->copy()->subMinutes(45);
        
        $recentSyncPoints = array_filter($allSyncPoints, function($syncPoint) use ($cutoffTime) {
            $candleTime = Carbon::parse($syncPoint['candle']->timestamp);
            return $candleTime->gte($cutoffTime);
        });

        $recentSyncPoints = array_values($recentSyncPoints);
        
        if (count($recentSyncPoints) < count($allSyncPoints)) {
            $filtered = count($allSyncPoints) - count($recentSyncPoints);
            Log::info("🕐 [FILTER] Filtered out {$filtered} old sync points");
        }

        return $recentSyncPoints;
    }

    /**
     * Get next valid sync point (alternating signals only)
     */
    private function getNextValidSyncPoint($allSyncPoints, $existingOrders, $tradingSymbol)
    {
        if (empty($allSyncPoints)) {
            return null;
        }

        if ($existingOrders->isEmpty()) {
            Log::info("✅ [VALID] No existing orders, using first sync point");
            return $allSyncPoints[0];
        }

        $lastOrder = $existingOrders->last();
        $lastSignalType = $lastOrder->signal_type;

        foreach ($allSyncPoints as $syncPoint) {
            $syncTime = $syncPoint['candle']->timestamp;
            $syncType = $syncPoint['signal']['type'];

            if ($syncTime <= $lastOrder->signal_detected_at) {
                continue;
            }

            if ($syncType == $lastSignalType) {
                Log::info("⏭️ [VALID] Skipping same signal: {$syncType}");
                continue;
            }

            Log::info("✅ [VALID] Found alternating signal: {$syncType} (prev: {$lastSignalType})");
            return $syncPoint;
        }

        return null;
    }

    /**
     * ✅ Create order entry using pre-selected strikes (matching backtesting)
     */
    private function createOrderEntry(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $candle, $signal)
    {
        try {
            // ✅ Get trade date from signal candle
            $tradeDate = Carbon::parse($candle->timestamp)->format('Y-m-d');
            
            // ✅ Get option series from config (default to 'current' if not set)
            $optionSeries = $config->option_series ?? 'current';
            
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [CREATE] Looking for pre-selected strike");
            Log::info("   Trade Date: {$tradeDate}");
            Log::info("   Future Symbol: {$symbol->trading_symbol}");
            Log::info("   Signal Type: {$signal['type']}");
            Log::info("   Option Series: {$optionSeries}");
            
            // ✅ Get option from pre-selected strikes table (same as backtesting)
            $optionDetails = $this->getPreSelectedOption(
                $symbol->trading_symbol,
                $signal['type'],
                $signal['price'],
                $tradeDate,
                $optionSeries,
                $broker
            );

            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find pre-selected option strike");
                return;
            }

            Log::info("✅ [CREATE] Pre-selected option found:");
            Log::info("   Symbol: {$optionDetails['symbol']}");
            Log::info("   Strike: {$optionDetails['strike']}");
            Log::info("   Fair Price: ₹{$optionDetails['fair_price']}");
            Log::info("   LTP: ₹{$optionDetails['ltp']}");
            Log::info("   OI: {$optionDetails['oi']}");
            Log::info("   Valuation: {$optionDetails['valuation']}");

            $quantity = $config->getQuantityForSymbol($symbol->trading_symbol);
            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($quantity);

            $orderData = [
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $broker->id,
                'symbol' => $symbol->symbol,
                'trading_symbol' => $symbol->trading_symbol,
                'instrument_token' => $symbol->instrument_token,
                'signal_type' => $signal['type'],
                'signal_strategy' => $signal['strategy'],
                'supertrend_signal' => $signal['supertrend'],
                'vwap_signal' => $signal['vwap'],
                'signal_detected_at' => $candle->timestamp,
                'option_symbol' => $optionDetails['symbol'],
                'option_token' => $optionDetails['token'],
                'option_type' => $optionDetails['type'],
                'strike_price' => $optionDetails['strike'],
                'atm_price' => $signal['price'],
                'entry_price' => $optionDetails['ltp'],
                'current_price' => $optionDetails['ltp'],
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $quantity,
                'pyramid_1' => $pyramid1,
                'pyramid_2' => $pyramid2,
                'pyramid_3' => $pyramid3,
                'is_order_placed' => false,
                'status' => true
            ];

            $order = ZerodhaAutoOrder::create($orderData);
            Log::info("✅ [CREATE] Order created! ID: {$order->id}");
            Log::info("   Future: {$symbol->trading_symbol}");
            Log::info("   Option: {$optionDetails['symbol']} (Strike: {$optionDetails['strike']})");
            Log::info("   Signal: {$signal['type']} @ ₹{$signal['price']}");
            Log::info("   OI: {$optionDetails['oi']} | Valuation: {$optionDetails['valuation']}");

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
            Log::error("   Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get pre-selected option (matching backtesting logic exactly)
     */
    private function getPreSelectedOption($futureSymbol, $signalType, $futurePrice, $tradeDate, $optionSeries, BrokerApi $broker)
    {
        try {
            $selection = OptionStrikeSelection::where('trade_date', $tradeDate)
                ->where('future_symbol', $futureSymbol)
                ->where('option_series', $optionSeries)
                ->first();

            if ($selection) {
                Log::info("✅ [OPTION] Found pre-selection for {$tradeDate}");
                
                if ($signalType === 'BUY') {
                    $optionSymbol = $selection->selected_ce_symbol;
                    $strikePrice = $selection->selected_ce_strike;
                    $fairPrice = $selection->selected_ce_fair_price;
                    $oi = $selection->selected_ce_oi;
                    $valuation = $selection->selected_ce_valuation;
                } else {
                    $optionSymbol = $selection->selected_pe_symbol;
                    $strikePrice = $selection->selected_pe_strike;
                    $fairPrice = $selection->selected_pe_fair_price;
                    $oi = $selection->selected_pe_oi;
                    $valuation = $selection->selected_pe_valuation;
                }

                $instrument = ZerodhaInstrument::where('trading_symbol', $optionSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    Log::error("❌ [OPTION] Instrument not found for symbol: {$optionSymbol}");
                    return $this->getPreSelectedOptionFallback($futureSymbol, $signalType, $futurePrice, $optionSeries, $broker);
                }

                $ltp = $this->getOptionLTP($broker, $instrument->instrument_token, $optionSymbol);

                return [
                    'symbol' => $optionSymbol,
                    'token' => $instrument->instrument_token,
                    'type' => $signalType === 'BUY' ? 'CE' : 'PE',
                    'strike' => $strikePrice,
                    'ltp' => $ltp,
                    'fair_price' => $fairPrice,
                    'oi' => $oi,
                    'valuation' => $valuation,
                    'expiry' => $instrument->expiry
                ];
            }

            Log::warning("⚠️ [OPTION] No pre-selection found for {$futureSymbol} on {$tradeDate}, using fallback");
            return $this->getPreSelectedOptionFallback($futureSymbol, $signalType, $futurePrice, $optionSeries, $broker);

        } catch (\Exception $e) {
            Log::error("❌ [OPTION] Error getting pre-selected option: " . $e->getMessage());
            return $this->getPreSelectedOptionFallback($futureSymbol, $signalType, $futurePrice, $optionSeries, $broker);
        }
    }

    /**
     * Fallback method when pre-selection not available
     */
    private function getPreSelectedOptionFallback($futureSymbol, $signalType, $futurePrice, $optionSeries, BrokerApi $broker)
    {
        try {
            $baseSymbol = $this->extractBaseSymbol($futureSymbol);
            $optionType = $signalType === 'BUY' ? 'CE' : 'PE';
            
            $strikeInterval = $this->getStrikeInterval($baseSymbol);
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            Log::info("🔄 [FALLBACK] Calculating ATM strike: ₹{$calculatedStrike}");

            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($optionSeries === 'next') {
                $query->orderBy('expiry', 'ASC')->skip(1)->take(1);
            } else {
                $query->orderBy('expiry', 'ASC');
            }

            $option = $query->first();

            if (!$option) {
                $query = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike]);

                if ($optionSeries === 'next') {
                    $query->orderBy('expiry', 'ASC')->orderBy('strike_diff', 'ASC')->skip(1)->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if (!$option) {
                Log::error("❌ [FALLBACK] No option found");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            return [
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'type' => $optionType,
                'strike' => $option->strike,
                'ltp' => $ltp,
                'fair_price' => null,
                'oi' => 0,
                'valuation' => 'N/A',
                'expiry' => $option->expiry
            ];

        } catch (\Exception $e) {
            Log::error("❌ [FALLBACK] Error: " . $e->getMessage());
            return null;
        }
    }

    private function extractBaseSymbol($tradingSymbol)
    {
        return preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
    }

    private function getStrikeInterval($symbol)
    {
        $intervals = [
            'NIFTY' => 50,
            'BANKNIFTY' => 100,
            'FINNIFTY' => 50,
            'MIDCPNIFTY' => 25,
        ];
        return $intervals[$symbol] ?? 20;
    }

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, $tradingSymbol)
    {
        try {
            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];
            $quoteKey = "NFO:" . $tradingSymbol;
            $quotes = $kite->getQuote([$quoteKey]);
            
            if (isset($quotes->$quoteKey->last_price)) {
                return $quotes->$quoteKey->last_price;
            }
            
            $quotesArray = json_decode(json_encode($quotes), true);
            if (isset($quotesArray[$quoteKey]['last_price'])) {
                return $quotesArray[$quoteKey]['last_price'];
            }
            
            return 25.00;
            
        } catch (\Exception $e) {
            Log::error("❌ [LTP] Error: " . $e->getMessage());
            return 25.00;
        }
    }

    /**
     * Place orders
     */
    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== Starting Symbol Order Placement (15-Min) ===');

            $pendingOrders = ZerodhaAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('No pending orders to place');
                return;
            }

            Log::info("Found {$pendingOrders->count()} pending orders");

            $ordersByBroker = $pendingOrders->groupBy('broker_api_id');

            foreach ($ordersByBroker as $brokerId => $orders) {
                $broker = BrokerApi::find($brokerId);
                
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} has invalid token, skipping");
                    continue;
                }

                Log::info("Processing {$orders->count()} orders for broker: {$broker->client_name}");

                if (!isset($this->kiteInstances[$broker->id])) {
                    $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                    $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
                }

                foreach ($orders as $order) {
                    $this->placeOrder($order);
                }
            }

            Log::info('=== Order Placement Completed ===');

        } catch (\Exception $e) {
            Log::error('Order Placement Error: ' . $e->getMessage());
        }
    }

    private function placeOrder(ZerodhaAutoOrder $order)
    {
        try {
            Log::info("📤 [ORDER] Placing: {$order->option_symbol}");

            $broker = $order->broker;
            
            if (!$broker->hasValidToken()) {
                Log::error("❌ [ORDER] Invalid broker token");
                $this->saveFailedOrder($order, $order->pyramid_1 ?? 0, null, "Invalid token");
                return;
            }

            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();
            
            if (!$instrument) {
                Log::error("❌ [ORDER] Instrument not found");
                $this->saveFailedOrder($order, $order->pyramid_1 ?? 0, null, "Instrument not found");
                return;
            }

            $this->placePyramidOrders($order, $instrument, $kite);

            $order->update([
                'is_order_placed' => true,
                'order_placed_at' => now()
            ]);

            Log::info("✅ [ORDER] Order processed: ID {$order->id}");

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $order->pyramid_1 ?? 0, null, $e->getMessage());
        }
    }

    private function placePyramidOrders($order, $instrument, $kite)
    {
        $pyramids = [$order->pyramid_1, $order->pyramid_2, $order->pyramid_3];
        $delays = [0];
        
        if ($order->pyramid_2) $delays[] = $order->config->pyramid_freq * 60;
        if ($order->pyramid_3) $delays[] = $order->config->pyramid_freq * 60 * 2;

        $baseSymbol = $this->extractBaseSymbol($order->trading_symbol);
        $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

        foreach ($pyramids as $pyramidIndex => $pyramidQty) {
            if (!$pyramidQty) continue;

            if (($delays[$pyramidIndex] ?? 0) > 0) {
                sleep($delays[$pyramidIndex]);
            }

            $price = null;
            if ($order->order_type == 'LIMIT') {
                $pyramidLevel = $pyramidIndex + 1;
                $price = $this->calculateLimitPrice(
                    $order->entry_price,
                    $order->config->disc_ltp,
                    $order->signal_type,
                    $instrument->tick_size,
                    $pyramidLevel
                );
            }

            if ($freezeLimitLots && $pyramidQty > $freezeLimitLots) {
                Log::info("🔄 [FREEZE] Splitting {$pyramidQty} lots");
                
                $numOrders = ceil($pyramidQty / $freezeLimitLots);
                $remainingLots = $pyramidQty;
                
                for ($i = 0; $i < $numOrders; $i++) {
                    $lotsToPlace = min($freezeLimitLots, $remainingLots);
                    $this->placeKiteOrder($order, $lotsToPlace, $price, $instrument, $kite);
                    $remainingLots -= $lotsToPlace;
                    
                    if ($i < $numOrders - 1) sleep(2);
                }
            } else {
                $this->placeKiteOrder($order, $pyramidQty, $price, $instrument, $kite);
            }
        }
    }

    private function calculateLimitPrice($entryPrice, $discLtp, $signalType, $tickSize, $pyramidLevel = 1)
    {
        $effectiveDiscount = $discLtp;
        
        if ($pyramidLevel == 2) {
            $effectiveDiscount = $discLtp + ($discLtp * 0.5);
        } elseif ($pyramidLevel == 3) {
            $pyramid2Discount = $discLtp + ($discLtp * 0.5);
            $effectiveDiscount = $pyramid2Discount + ($pyramid2Discount * 0.5);
        }
        
        $discount = ($entryPrice * $effectiveDiscount) / 100;
        $price = $entryPrice - $discount;
        $roundedPrice = round($price / $tickSize) * $tickSize;
        
        Log::info("💰 [PRICE] Pyramid {$pyramidLevel}: {$effectiveDiscount}% → ₹{$roundedPrice}");
        
        return number_format($roundedPrice, 2, '.', '');
    }

    private function placeKiteOrder(ZerodhaAutoOrder $order, $quantity, $price, $instrument, $kite)
    {
        try {
            $orderParams = [
                'exchange' => 'NFO',
                'tradingsymbol' => $order->option_symbol,
                'transaction_type' => 'BUY',
                'quantity' => $quantity * $instrument->lot_size,
                'product' => $order->product,
                'validity' => 'DAY'
            ];

            if ($order->order_type == 'MARKET') {
                $orderParams['order_type'] = 'MARKET';
            } else {
                $orderParams['order_type'] = 'LIMIT';
                $orderParams['price'] = $price;
            }

            $result = $kite->placeOrder("regular", $orderParams);
            Log::info("✅ [ORDER] Placed! Order ID: {$result->order_id}");
            
            $this->saveToOrderBook($order, $result->order_id, $quantity, $price);

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Kite Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $quantity, $price, $e->getMessage());
        }
    }

    private function saveToOrderBook(ZerodhaAutoOrder $order, $orderId, $quantity, $price)
    {
        try {
            sleep(2);
            
            $kite = $this->kiteInstances[$order->broker_api_id] ?? null;
            
            if (!$kite) {
                Log::error("Kite instance not found");
                return;
            }

            $orderHistory = $kite->getOrderHistory($orderId);
            $lastOrder = end($orderHistory);

            OrderBook::create([
                'user_id' => $order->user_id,
                'broker_username' => $order->broker->account_user_name,
                'order_id' => $orderId,
                'status' => $lastOrder->status ?? 'PENDING',
                'trading_symbol' => $order->option_symbol,
                'order_type' => $order->order_type,
                'transaction_type' => 'BUY',
                'product' => $order->product,
                'price' => $price ?? '-',
                'quantity' => $quantity,
                'status_message' => $lastOrder->status_message ?? 'Order placed',
                'order_datetime' => now(),
                'symbol_auto_order_id' => $order->id
            ]);

            Log::info("✅ [ORDER_BOOK] Saved order {$orderId}");

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(ZerodhaAutoOrder $order, $quantity, $price, $error)
    {
        try {
            OrderBook::create([
                'user_id' => $order->user_id,
                'broker_username' => $order->broker->account_user_name ?? 'N/A',
                'order_id' => '-',
                'status' => 'FAILED',
                'trading_symbol' => $order->option_symbol,
                'order_type' => $order->order_type,
                'transaction_type' => 'BUY',
                'product' => $order->product,
                'price' => $price ?? '-',
                'quantity' => $quantity,
                'status_message' => substr($error, 0, 500),
                'order_datetime' => now(),
                'symbol_auto_order_id' => $order->id
            ]);

            Log::info("❌ [ORDER_BOOK] Saved failed order");

        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }
}