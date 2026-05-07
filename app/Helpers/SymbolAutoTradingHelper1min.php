<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class SymbolAutoTradingHelper1min
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
            Log::info('=== Starting Symbol Auto Trading Signal Detection (1-Minute) ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = ZerodhaAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active configs");

            // Get all active monitored symbols for 1-minute interval
            $symbols = SymbolMonitored::where('is_active', true)
                ->where('interval', 'minute')
                ->get();

            if ($symbols->isEmpty()) {
                Log::info('No active 1-minute symbols found');
                return;
            }

            Log::info("✅ Found {$symbols->count()} active 1-minute symbols");

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
                        Log::info("   ✅ Config found: ID {$config->id}, Strategy: {$config->signal_strategy}, Filter: {$config->option_filter}");
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
     * Check and create order
     * ✅ FIXED: Now matches backtesting logic exactly
     */
    private function checkAndCreateOrder(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    {
        try {
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [CHECK] Starting check for: {$symbol->trading_symbol}");
            Log::info("   Strategy: {$config->signal_strategy}");
            Log::info("   Option Filter: {$config->option_filter}");
            Log::info("   Quality Filter: " . ($config->enable_quality_filter ? 'ENABLED ✅' : 'DISABLED ⚠️'));
            
            $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, 'minute', $testDate);
            Log::info("📊 [CHECK] Found {$todayCandles->count()} candles today");

            if ($todayCandles->count() < 1) {
                Log::warning("❌ [CHECK] Not enough data");
                return;
            }

            $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('status', true)
                ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info("📋 [CHECK] Existing orders today: {$todayOrders->count()}");

            $allSyncPoints = $this->findAllSynchronizationPointsLive(
                $todayCandles, 
                $symbol->trading_symbol, 
                $config->signal_strategy,
                $config,
                null
            );

            Log::info("📊 [CHECK] Total sync points found: " . count($allSyncPoints));

            if (empty($allSyncPoints)) {
                Log::warning("❌ [CHECK] No sync points found");
                return;
            }

            $validSyncPoint = $this->getNextValidSyncPoint($allSyncPoints, $todayOrders, $symbol->trading_symbol);

            if (!$validSyncPoint) {
                Log::warning("❌ [CHECK] No new valid sync point (all already ordered)");
                return;
            }

            Log::info("✅ [CHECK] Valid sync: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
            
            $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);

        } catch (\Exception $e) {
            Log::error("❌ [CHECK] Exception: " . $e->getMessage());
            Log::error("   Trace: " . $e->getTraceAsString());
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
     * ✅ NEW METHOD: Find synchronization points for LIVE trading
     * Matches backtesting logic EXACTLY but without previous day initialization
     */
    private function findAllSynchronizationPointsLive($candles, $tradingSymbol, $strategy, ZerodhaAutoConfig $config, $previousDayLastCandle = null)
    {
        $syncPoints = [];
        $totalCandles = count($candles);
        
        Log::info("🔍 [SYNC-LIVE] Scanning {$totalCandles} candles with strategy: {$strategy}");
        Log::info("🔍 [SYNC-LIVE] Quality Filter: " . ($config->enable_quality_filter ? 'ENABLED ✅' : 'DISABLED ⚠️'));
        Log::info("🔍 [SYNC-LIVE] Option Filter: {$config->option_filter}");

        $currentSupertrendSignal = null;
        $currentVwapSignal = null;
        $currentRsiSignal = null;

        Log::info("📅 [SYNC-LIVE] Starting fresh (no previous day carry-over)");

        foreach ($candles as $index => $candle) {
            $recordSupertrendSignal = $candle->supertrend_signal;
            $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
            $recordRsiSignal = $candle->rsi_signal ?? 'NEUTRAL';
            $recordDirection = $candle->supertrend_direction;

            // ========== SUPERTREND LOGIC ==========
            $supertrendFresh = false;
            
            if ($currentSupertrendSignal === null && in_array($recordSupertrendSignal, ['BUY', 'SELL'])) {
                $supertrendFresh = true;
                $currentSupertrendSignal = $recordSupertrendSignal;
                Log::info("🎯 [ST-LIVE] Initial signal: {$recordSupertrendSignal} at {$candle->timestamp}");
            }
            elseif ($recordSupertrendSignal === 'BUY' && $currentSupertrendSignal !== 'BUY') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'BUY';
                Log::info("🎯 [ST-LIVE] Fresh BUY at {$candle->timestamp}");
            }
            elseif ($recordSupertrendSignal === 'SELL' && $currentSupertrendSignal !== 'SELL') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'SELL';
                Log::info("🎯 [ST-LIVE] Fresh SELL at {$candle->timestamp}");
            }
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'UP') {
                if ($currentSupertrendSignal !== 'BUY') {
                    $currentSupertrendSignal = 'BUY';
                }
            }
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'DOWN') {
                if ($currentSupertrendSignal !== 'SELL') {
                    $currentSupertrendSignal = 'SELL';
                }
            }

            // ========== VWAP LOGIC ==========
            $vwapFresh = false;
            
            if ($currentVwapSignal === null) {
                if ($recordVwapSignal === 'GAP_UP' || $recordVwapSignal === 'BUY') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'BUY';
                    Log::info("🎯 [VWAP-LIVE] Initial BUY at {$candle->timestamp}");
                } elseif ($recordVwapSignal === 'GAP_DOWN' || $recordVwapSignal === 'SELL') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'SELL';
                    Log::info("🎯 [VWAP-LIVE] Initial SELL at {$candle->timestamp}");
                } else {
                    $currentVwapSignal = 'HOLD';
                }
            }
            elseif ($recordVwapSignal === 'GAP_UP' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
                Log::info("🎯 [VWAP-LIVE] GAP_UP → BUY at {$candle->timestamp}");
            }
            elseif ($recordVwapSignal === 'GAP_DOWN' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
                Log::info("🎯 [VWAP-LIVE] GAP_DOWN → SELL at {$candle->timestamp}");
            }
            elseif ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
                Log::info("🎯 [VWAP-LIVE] Fresh BUY at {$candle->timestamp}");
            }
            elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
                Log::info("🎯 [VWAP-LIVE] Fresh SELL at {$candle->timestamp}");
            }

            // ========== RSI LOGIC ==========
            $rsiFresh = false;
            
            if ($currentRsiSignal === null || $currentRsiSignal === 'NEUTRAL') {
                if (in_array($recordRsiSignal, ['BUY', 'SELL'])) {
                    $rsiFresh = true;
                    $currentRsiSignal = $recordRsiSignal;
                    Log::info("🎯 [RSI-LIVE] Initial {$recordRsiSignal} at {$candle->timestamp}");
                }
            }
            elseif ($recordRsiSignal === 'BUY' && $currentRsiSignal !== 'BUY') {
                $rsiFresh = true;
                $currentRsiSignal = 'BUY';
            }
            elseif ($recordRsiSignal === 'SELL' && $currentRsiSignal !== 'SELL') {
                $rsiFresh = true;
                $currentRsiSignal = 'SELL';
            }

            // ========== CHECK STRATEGY ==========
            $shouldTrigger = false;
            $signalType = null;

            switch ($strategy) {
                case 'SUPERTREND':
                    if ($supertrendFresh && in_array($currentSupertrendSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    break;

                case 'VWAP':
                    if ($vwapFresh && in_array($currentVwapSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                    }
                    break;

                case 'RSI':
                    if ($rsiFresh && in_array($currentRsiSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentRsiSignal;
                    }
                    break;

                case 'BOTH':
                case 'SUPERTREND_VWAP':
                default:
                    if ($supertrendFresh && $vwapFresh && $currentSupertrendSignal === $currentVwapSignal) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                        Log::info("✅ [BOTH-LIVE] ST + VWAP sync {$signalType} at {$candle->timestamp}");
                    }
                    elseif ($supertrendFresh && $currentSupertrendSignal === $currentVwapSignal && 
                            in_array($currentVwapSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                        Log::info("✅ [BOTH-LIVE] ST fresh, matches VWAP {$signalType} at {$candle->timestamp}");
                    }
                    elseif ($vwapFresh && $currentVwapSignal === $currentSupertrendSignal && 
                            in_array($currentSupertrendSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                        Log::info("✅ [BOTH-LIVE] VWAP fresh, matches ST {$signalType} at {$candle->timestamp}");
                    }
                    break;
            }

            // ========== CREATE SYNC POINT ==========
            if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
                // ✅ APPLY QUALITY FILTER ONLY IF ENABLED
                $hasQuality = true;
                if ($config->enable_quality_filter) {
                    $hasQuality = $this->hasQualityMomentum($candles, $index, $signalType);
                    
                    if (!$hasQuality) {
                        Log::info("⚠️ [SYNC-LIVE] {$signalType} signal at {$candle->timestamp} REJECTED (quality filter)");
                        continue;
                    }
                    Log::info("✅ [SYNC-LIVE] {$signalType} at index {$index} ({$candle->timestamp}) - QUALITY APPROVED");
                } else {
                    Log::info("✅ [SYNC-LIVE] {$signalType} at index {$index} ({$candle->timestamp}) - QUALITY FILTER BYPASSED");
                }
                
                $syncPoints[] = [
                    'index' => $index,
                    'candle' => $candle,
                    'signal' => [
                        'type' => $signalType,
                        'supertrend' => $currentSupertrendSignal,
                        'vwap' => $currentVwapSignal,
                        'rsi' => $currentRsiSignal,
                        'price' => $candle->close,
                        'strategy' => $strategy,
                        'quality_approved' => $hasQuality
                    ]
                ];
            }
        }

        Log::info("📊 [SYNC-LIVE] Total sync points: " . count($syncPoints));
        return $syncPoints;
    }

    /**
     * Check if signal has quality momentum
     */
    private function hasQualityMomentum($candles, $currentIndex, $signalType)
    {
        if ($currentIndex < 10) {
            Log::info("   [QUALITY] Not enough history to validate");
            return false;
        }

        $recentCandles = [];
        for ($i = max(0, $currentIndex - 9); $i <= $currentIndex; $i++) {
            $recentCandles[] = $candles[$i];
        }

        $volumeSMA = null;
        if ($currentIndex >= 19) {
            $volumeSum = 0;
            for ($i = $currentIndex - 19; $i <= $currentIndex; $i++) {
                $volumeSum += $candles[$i]->volume;
            }
            $volumeSMA = $volumeSum / 20;
        }

        Log::info("   [QUALITY] Checking momentum for {$signalType} signal");
        
        $volumePassCount = 0;
        if ($volumeSMA) {
            foreach ($recentCandles as $candle) {
                if ($candle->volume > $volumeSMA) {
                    $volumePassCount++;
                }
            }
            Log::info("   [QUALITY] Volume check: {$volumePassCount}/10 candles above SMA");
            
            if ($volumePassCount < 6) {
                Log::warning("   [QUALITY] ❌ REJECTED: Insufficient volume ({$volumePassCount}/10)");
                return false;
            }
        }

        $priceConsistencyCount = 0;
        
        for ($i = 1; $i < count($recentCandles); $i++) {
            $prevClose = $recentCandles[$i - 1]->close;
            $currClose = $recentCandles[$i]->close;
            
            if ($signalType === 'BUY') {
                if ($currClose > $prevClose) {
                    $priceConsistencyCount++;
                }
            } elseif ($signalType === 'SELL') {
                if ($currClose < $prevClose) {
                    $priceConsistencyCount++;
                }
            }
        }
        
        Log::info("   [QUALITY] Price momentum: {$priceConsistencyCount}/9 consistent closes");
        
        if ($priceConsistencyCount < 6) {
            Log::warning("   [QUALITY] ❌ REJECTED: Weak price momentum ({$priceConsistencyCount}/9)");
            return false;
        }

        Log::info("   [QUALITY] ✅ APPROVED: Strong momentum confirmed");
        Log::info("   [QUALITY]   - Volume: {$volumePassCount}/10 above SMA");
        Log::info("   [QUALITY]   - Price: {$priceConsistencyCount}/9 consistent");
        
        return true;
    }

    /**
     * ✅ CRITICAL FIX: Get next valid sync point - checks for EXACT duplicate orders
     */
    private function getNextValidSyncPoint($allSyncPoints, $existingOrders, $tradingSymbol)
    {
        if (empty($allSyncPoints)) {
            Log::info("❌ [VALID] No sync points to check");
            return null;
        }

        if ($existingOrders->isEmpty()) {
            Log::info("✅ [VALID] No existing orders, using first sync point");
            Log::info("   First sync: {$allSyncPoints[0]['signal']['type']} at {$allSyncPoints[0]['candle']->timestamp}");
            return $allSyncPoints[0];
        }

        $existingOrderTimes = $existingOrders->pluck('signal_detected_at')
            ->map(function($time) {
                return Carbon::parse($time)->format('Y-m-d H:i:s');
            })
            ->toArray();

        $lastOrder = $existingOrders->last();
        $lastSignalTime = Carbon::parse($lastOrder->signal_detected_at);
        $lastSignalType = $lastOrder->signal_type;

        Log::info("📋 [VALID] Last order: {$lastSignalType} at {$lastSignalTime->format('Y-m-d H:i:s')}");
        Log::info("📋 [VALID] Total existing orders: " . count($existingOrders));
        Log::info("📋 [VALID] Checking " . count($allSyncPoints) . " sync points for new signal");

        foreach ($allSyncPoints as $index => $syncPoint) {
            $syncTime = Carbon::parse($syncPoint['candle']->timestamp);
            $syncTimeStr = $syncTime->format('Y-m-d H:i:s');
            $syncType = $syncPoint['signal']['type'];

            if (in_array($syncTimeStr, $existingOrderTimes)) {
                Log::info("   ⏭️ [{$index}] {$syncType} at {$syncTime->format('H:i:s')} - ORDER ALREADY EXISTS");
                continue;
            }

            if ($syncTime->lte($lastSignalTime)) {
                Log::info("   ⏭️ [{$index}] {$syncType} at {$syncTime->format('H:i:s')} - Before/Same as last order");
                continue;
            }

            if ($syncType == $lastSignalType) {
                Log::info("   ⏭️ [{$index}] {$syncType} at {$syncTime->format('H:i:s')} - Same as last signal");
                continue;
            }

            Log::info("✅ [VALID] Found NEW signal: {$syncType} at {$syncTime->format('H:i:s')} (prev: {$lastSignalType})");
            return $syncPoint;
        }

        Log::info("❌ [VALID] No new alternating signal found after checking all sync points");
        return null;
    }

    /**
     * Create order entry
     * ✅ ADDED: Double-check for duplicates before creating
     * ✅ ADDED: Apply option_filter logic
     */
    private function createOrderEntry(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $candle, $signal)
    {
        try {
            // ✅ CRITICAL: Apply Option Filter FIRST (before any processing)
            $optionType = $signal['type'] == 'BUY' ? 'CE' : 'PE';
            
            if ($config->option_filter != 'BOTH') {
                if ($config->option_filter != $optionType) {
                    Log::info("⏭️ [FILTER] Skipping {$optionType} order (config filter: {$config->option_filter})");
                    Log::info("   Signal: {$signal['type']} @ {$candle->timestamp}");
                    Log::info("   Symbol: {$symbol->trading_symbol}");
                    return;
                }
                Log::info("✅ [FILTER] {$optionType} order approved (config filter: {$config->option_filter})");
            }

            // ✅ CRITICAL: Final duplicate check before creating order
            $existingOrder = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('signal_detected_at', $candle->timestamp)
                ->first();

            if ($existingOrder) {
                Log::warning("⚠️ [CREATE] Order already exists for {$symbol->trading_symbol} at {$candle->timestamp}");
                Log::warning("   Existing order ID: {$existingOrder->id}");
                return;
            }

            $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $signal['type'], $signal['price'], $config);
            
            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find ATM option");
                return;
            }

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
            Log::info("   Option: {$optionDetails['symbol']} ({$optionType})");
            Log::info("   Signal: {$signal['type']} @ ₹{$signal['price']}");
            Log::info("   Time: {$candle->timestamp}");
            Log::info("   Filter: {$config->option_filter}");

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
            Log::error("   Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get ATM option (respects option_series config)
     */
    private function getATMOption(BrokerApi $broker, $tradingSymbol, $signalType, $futurePrice, ZerodhaAutoConfig $config)
    {
        try {
            $baseSymbol = $this->extractBaseSymbol($tradingSymbol);
            $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
            $strikeInterval = $this->getStrikeInterval($baseSymbol);
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($config->option_series === 'next') {
                $query->orderBy('expiry', 'ASC')
                    ->skip(1)
                    ->take(1);
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

                if ($config->option_series === 'next') {
                    $query->orderBy('expiry', 'ASC')
                        ->orderBy('strike_diff', 'ASC')
                        ->skip(1)
                        ->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')
                        ->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if (!$option) {
                Log::error("❌ [OPTION] No option found for {$baseSymbol} {$optionType} @ {$calculatedStrike}");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            Log::info("✅ [OPTION] Selected: {$option->trading_symbol}");
            Log::info("   Series: {$config->option_series}");
            Log::info("   Expiry: {$option->expiry}");

            return [
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'type' => $optionType,
                'strike' => $option->strike,
                'ltp' => $ltp,
                'expiry' => $option->expiry
            ];

        } catch (\Exception $e) {
            Log::error("❌ [OPTION] Error: " . $e->getMessage());
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
            Log::info('=== Starting Symbol Order Placement (1-Min) ===');

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