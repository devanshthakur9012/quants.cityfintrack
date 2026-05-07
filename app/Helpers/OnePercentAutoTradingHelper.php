<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\OnePercentAutoConfig;
use App\Models\OnePercentAutoOrder;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use App\Models\OptionStrike;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class OnePercentAutoTradingHelper
{
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Main process - Detect 1% move signals and create orders
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting One-Percent Auto Trading Signal Detection ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = OnePercentAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active one-percent configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active one-percent configs");

            // Get all active monitored symbols for 5-minute interval
            $symbols = SymbolMonitored::where('is_active', true)
                ->where('interval', '5minute')
                ->get();

            if ($symbols->isEmpty()) {
                Log::info('No active symbols found');
                return;
            }

            Log::info("✅ Found {$symbols->count()} active symbols");

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
                    Log::info("🔍 Checking symbol: {$symbol->trading_symbol}");
                    
                    $config = $this->findConfigForSymbol($configs, $symbol);
                    
                    if ($config) {
                        Log::info("   ✅ Config found: ID {$config->id}, Threshold: ±{$config->move_threshold}%");
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

    // ✅ NEW: Configurable time window for order placement (in minutes)
    const ORDER_PLACEMENT_WINDOW_MINUTES = 10;

    /**
     * ✅ UPDATED: Check for 1% move signals with two-stage filtering (like 5-min system)
     * STAGE 1: Find ALL signals (maintains signal chain)
     * STAGE 2: Create orders ONLY for RECENT signals
     */
    private function checkAndCreateOrder(OnePercentAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    {
        try {
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [CHECK] Starting check for: {$symbol->trading_symbol}");
            Log::info("   Move Threshold: ±{$config->move_threshold}%");
            
            $currentDate = $testDate ? Carbon::parse($testDate)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            
            // Get today's candles
            $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '5minute', $testDate);
            Log::info("📊 [CHECK] Found {$todayCandles->count()} candles today");

            if ($todayCandles->count() < 1) {
                Log::warning("❌ [CHECK] Not enough data");
                return;
            }

            // Get day opening price
            $dayOpenCandle = $todayCandles->first();
            $dayOpenPrice = $dayOpenCandle->open;

            if (!$dayOpenPrice) {
                Log::warning("❌ [CHECK] No opening price");
                return;
            }

            Log::info("📊 [CHECK] Day open price: ₹{$dayOpenPrice}");

            // ✅ Get existing orders (NO LIMIT - can have multiple per day)
            $todayOrders = OnePercentAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('status', true)
                ->whereDate('signal_detected_at', $currentDate)
                ->orderBy('signal_detected_at', 'asc')
                ->get();

            Log::info("📋 [CHECK] Existing orders today: {$todayOrders->count()}");

            // Get OI data from previous trading day
            $oiData = $this->getOIDataForSymbol($symbol->trading_symbol, $currentDate);

            // ✅ STAGE 1: Find ALL valid signals (entire day)
            $allSignals = $this->findAllSignals($todayCandles, $dayOpenPrice, $config->move_threshold, $oiData);
            
            Log::info("📊 [STAGE 1] Total signals found: " . count($allSignals));

            if (empty($allSignals)) {
                Log::warning("❌ [CHECK] No valid signals found");
                return;
            }

            // ✅ STAGE 2: Get valid signals (not already processed)
            $validSignals = $this->getValidSignals($allSignals, $todayOrders);

            if (empty($validSignals)) {
                Log::warning("❌ [STAGE 2] No new valid signals");
                return;
            }

            Log::info("📊 [STAGE 2] Valid new signals: " . count($validSignals));

            // ✅ STAGE 3: Filter RECENT signals for order placement
            $currentTime = $testDate ? Carbon::parse($testDate) : Carbon::now();
            $recentSignalsForOrders = $this->filterRecentForOrderPlacement($validSignals, $currentTime);

            if (empty($recentSignalsForOrders)) {
                Log::warning("⚠️ [STAGE 3] No recent signals for order placement (all signals are old)");
                Log::info("   Valid signals exist but are older than " . self::ORDER_PLACEMENT_WINDOW_MINUTES . " minutes");
                return;
            }

            Log::info("✅ [STAGE 3] Recent signals for orders: " . count($recentSignalsForOrders));

            // ✅ STAGE 4: CREATE ORDERS FOR RECENT SIGNALS
            foreach ($recentSignalsForOrders as $signal) {
                Log::info("✅ [ORDER] Creating: {$signal['type']} @ {$signal['candle']->timestamp}");
                $this->createOrderEntry(
                    $config,
                    $symbol,
                    $broker,
                    $signal['candle'],
                    $signal['type'],
                    $dayOpenPrice,
                    $signal['price'],
                    $signal['change_pct'],
                    $oiData
                );
            }

        } catch (\Exception $e) {
            Log::error("❌ [CHECK] Exception: " . $e->getMessage());
            Log::error("   Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * ✅ NEW: Find ALL valid signals (process entire day)
     */
    private function findAllSignals($candles, $dayOpenPrice, $moveThreshold, $oiData)
    {
        $signals = [];
        $lastSignalType = null;

        foreach ($candles as $candle) {
            $currentPrice = $candle->close;
            $changePct = (($currentPrice - $dayOpenPrice) / $dayOpenPrice) * 100;

            // Check for +X% move (BUY CE)
            if ($changePct >= $moveThreshold) {
                if ($oiData['ce_signal'] === 'BULLISH') {
                    if ($lastSignalType !== 'BUY_CE') {
                        $signals[] = [
                            'candle' => $candle,
                            'type' => 'BUY_CE',
                            'price' => $currentPrice,
                            'change_pct' => $changePct
                        ];
                        $lastSignalType = 'BUY_CE';
                        Log::info("✅ [SIGNAL] BUY_CE @ {$candle->timestamp} (change: +{$changePct}%)");
                    }
                }
            }

            // Check for -X% move (BUY PE)
            if ($changePct <= -$moveThreshold) {
                if ($oiData['pe_signal'] === 'BULLISH') {
                    if ($lastSignalType !== 'BUY_PE') {
                        $signals[] = [
                            'candle' => $candle,
                            'type' => 'BUY_PE',
                            'price' => $currentPrice,
                            'change_pct' => $changePct
                        ];
                        $lastSignalType = 'BUY_PE';
                        Log::info("✅ [SIGNAL] BUY_PE @ {$candle->timestamp} (change: {$changePct}%)");
                    }
                }
            }
        }

        return $signals;
    }

    /**
     * ✅ NEW: Get valid signals (not already processed)
     */
    private function getValidSignals($allSignals, $existingOrders)
    {
        if (empty($allSignals)) {
            return [];
        }

        // Get last processed time
        $lastProcessedTime = null;
        if ($existingOrders->isNotEmpty()) {
            $lastOrder = $existingOrders->last();
            $lastProcessedTime = $lastOrder->signal_detected_at;
            Log::info("🔄 [VALID] Last processed: {$lastProcessedTime}");
        }

        // Filter signals AFTER last processed time
        $newSignals = array_filter($allSignals, function($signal) use ($lastProcessedTime) {
            if (!$lastProcessedTime) {
                return true;
            }
            return $signal['candle']->timestamp > $lastProcessedTime;
        });

        return array_values($newSignals);
    }

    /**
     * ✅ NEW: Filter recent signals for order placement (last N minutes only)
     */
    private function filterRecentForOrderPlacement($validSignals, $currentTime)
    {
        $cutoffTime = $currentTime->copy()->subMinutes(self::ORDER_PLACEMENT_WINDOW_MINUTES);
        
        Log::info("🕐 [FILTER] Current time: {$currentTime->format('Y-m-d H:i:s')}");
        Log::info("🕐 [FILTER] Cutoff time: {$cutoffTime->format('Y-m-d H:i:s')}");
        Log::info("🕐 [FILTER] Window: Last " . self::ORDER_PLACEMENT_WINDOW_MINUTES . " minutes");

        $recentSignals = array_filter($validSignals, function($signal) use ($cutoffTime) {
            $signalTime = Carbon::parse($signal['candle']->timestamp);
            return $signalTime->gte($cutoffTime);
        });

        $recentSignals = array_values($recentSignals);

        if (count($recentSignals) < count($validSignals)) {
            $filtered = count($validSignals) - count($recentSignals);
            Log::info("🕐 [FILTER] Filtered out {$filtered} old signals (kept only recent)");
        }

        return $recentSignals;
    }

    /**
     * Get OI signals from PREVIOUS trading day
     */
    private function getOIDataForSymbol($futureSymbol, $date)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futureSymbol);
            $oiDate = $this->getPreviousTradingDay($date);

            $futOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'FUT')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            $ceOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'CE_MERGED')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            $peOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'PE_MERGED')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            // Log::info("📊 [OI] Signals from {$oiDate}: FUT={$futOI->direction ?? 'N/A'}, CE={$ceOI->direction ?? 'N/A'}, PE={$peOI->direction ?? 'N/A'}");

            return [
                'fut_signal' => $futOI ? $futOI->direction : 'NEUTRAL',
                'fut_strength' => $futOI ? $futOI->strength : 'N/A',
                'ce_signal' => $ceOI ? $ceOI->direction : 'NEUTRAL',
                'pe_signal' => $peOI ? $peOI->direction : 'NEUTRAL',
                'market_bias' => $futOI ? $futOI->market_bias : 'N/A',
            ];

        } catch (\Exception $e) {
            Log::error("❌ [OI] Error: " . $e->getMessage());
            return [
                'fut_signal' => 'ERROR',
                'fut_strength' => 'ERROR',
                'ce_signal' => 'ERROR',
                'pe_signal' => 'ERROR',
                'market_bias' => 'ERROR',
            ];
        }
    }

    /**
     * Get previous trading day (skip weekends/holidays)
     */
    private function getPreviousTradingDay($date)
    {
        $prevDate = Carbon::parse($date)->subDay();
        $maxAttempts = 10;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            if ($prevDate->isWeekend()) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $prevDate->format('Y-m-d'))
                ->exists();
            
            if ($isHoliday) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            return $prevDate->format('Y-m-d');
        }
        
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    /**
     * Get today's candles
     */
    private function getTodayCandles($brokerId, $tradingSymbol, $interval, $testDate = null)
    {
        $query = SymbolData::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
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
     * Create order entry
     */
    private function createOrderEntry(
        OnePercentAutoConfig $config,
        SymbolMonitored $symbol,
        BrokerApi $broker,
        $candle,
        $signalType,
        $dayOpenPrice,
        $signalPrice,
        $changePct,
        $oiData
    ) {
        try {
            // Determine option type
            $optionType = $signalType === 'BUY_CE' ? 'CE' : 'PE';
            
            $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $optionType, $signalPrice, $config);
            
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
                'signal_type' => $signalType,
                'move_threshold' => $config->move_threshold,
                'signal_detected_at' => $candle->timestamp,
                'day_open_price' => $dayOpenPrice,
                'signal_price' => $signalPrice,
                'change_pct' => $changePct,
                'fut_signal' => $oiData['fut_signal'],
                'fut_strength' => $oiData['fut_strength'],
                'ce_signal' => $oiData['ce_signal'],
                'pe_signal' => $oiData['pe_signal'],
                'market_bias' => $oiData['market_bias'],
                'option_symbol' => $optionDetails['symbol'],
                'option_token' => $optionDetails['token'],
                'option_type' => $optionType,
                'strike_price' => $optionDetails['strike'],
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

            $order = OnePercentAutoOrder::create($orderData);
            Log::info("✅ [CREATE] Order created! ID: {$order->id}");
            Log::info("   Signal: {$signalType}");
            Log::info("   Future: {$symbol->trading_symbol}");
            Log::info("   Option: {$optionDetails['symbol']}");
            Log::info("   Move: {$changePct}%");
            Log::info("   CE Signal: {$oiData['ce_signal']}");
            Log::info("   PE Signal: {$oiData['pe_signal']}");

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
            Log::error("   Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get ATM option
     */
    private function getATMOption(BrokerApi $broker, $tradingSymbol, $optionType, $futurePrice, OnePercentAutoConfig $config)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
            
            $strikeIntervals = [
                'NIFTY' => 50,
                'BANKNIFTY' => 100,
                'FINNIFTY' => 50,
                'MIDCPNIFTY' => 25,
            ];
            $strikeInterval = $strikeIntervals[$baseSymbol] ?? 20;
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($config->option_series === 'next') {
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

                if ($config->option_series === 'next') {
                    $query->orderBy('expiry', 'ASC')->orderBy('strike_diff', 'ASC')->skip(1)->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if (!$option) {
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            return [
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'strike' => $option->strike,
                'ltp' => $ltp,
                'expiry' => $option->expiry
            ];

        } catch (\Exception $e) {
            Log::error("❌ [OPTION] Error: " . $e->getMessage());
            return null;
        }
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
     * Place pending orders
     */
    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== Starting One-Percent Order Placement ===');

            $pendingOrders = OnePercentAutoOrder::where('is_order_placed', false)
            ->where('status', true)
            ->whereHas('config', function($query) {
                $query->where('status', true);
            })
            ->with(['config', 'broker'])
            ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('No pending one-percent orders to place');
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

    private function placeOrder(OnePercentAutoOrder $order)
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

        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->trading_symbol);
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

    private function calculateLimitPrice($entryPrice, $discLtp, $tickSize, $pyramidLevel = 1)
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
        
        return number_format($roundedPrice, 2, '.', '');
    }

    private function placeKiteOrder(OnePercentAutoOrder $order, $quantity, $price, $instrument, $kite)
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

    private function saveToOrderBook(OnePercentAutoOrder $order, $orderId, $quantity, $price)
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
                'one_percent_auto_order_id' => $order->id
            ]);

            Log::info("✅ [ORDER_BOOK] Saved order {$orderId}");

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(OnePercentAutoOrder $order, $quantity, $price, $error)
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
                'one_percent_auto_order_id' => $order->id
            ]);

            Log::info("❌ [ORDER_BOOK] Saved failed order");

        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }
}