<?php

namespace App\Helpers;

use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use App\Models\IndicatorConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class ZerodhaAutoTradingHelper15minOld
{
    private $kite;

    // Freeze quantity limits for index symbols
    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,      // ✅ FIXED: Was 25, now 20
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Main process - Detect signals and create orders (5-minute interval only)
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting Zerodha Auto Trading Signal Detection (5-Minute) ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = ZerodhaAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            // Only get futures monitored for 5-minute interval
            $futures = FuturesMonitored::where('is_active', true)
                ->where('intervals', 'LIKE', '15minute')
                ->get();

            if ($futures->isEmpty()) {
                Log::info('No active 5-minute futures found');
                return;
            }

            foreach ($configs as $config) {
                foreach ($futures as $future) {
                    $this->checkAndCreateOrder($config, $future, $testDate);
                }
                
                $config->update(['last_checked_at' => now()]);
            }

            Log::info('=== Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('Signal Processing Error: ' . $e->getMessage());
        }
    }

    /**
     * ✅ GET PREVIOUS TRADING DAY'S LAST CANDLE
     */
    private function getPreviousDayLastCandle($tradingSymbol, $testDate = null)
    {
        try {
            $targetDate = $testDate ? Carbon::parse($testDate) : Carbon::today();

            $lastCandle = FuturesData::where('trading_symbol', $tradingSymbol)
                ->where('interval', '15minute')
                ->where('timestamp', '<', $targetDate->format('Y-m-d 00:00:00'))
                ->whereNotNull('supertrend')
                ->whereNotNull('vwap')
                ->orderBy('timestamp', 'DESC')
                ->first();

            if ($lastCandle) {
                Log::info("📅 [PREV] Found previous day candle: {$lastCandle->timestamp}");
                Log::info("  ST Signal: {$lastCandle->supertrend_signal}, Direction: {$lastCandle->supertrend_direction}");
                Log::info("  VWAP Signal: {$lastCandle->vwap_signal}");
            } else {
                Log::info("⚠️ [PREV] No previous day candle found");
            }

            return $lastCandle;

        } catch (\Exception $e) {
            Log::error("❌ [PREV] Error: " . $e->getMessage());
            return null;
        }
    }

    private function checkAndCreateOrder(ZerodhaAutoConfig $config, FuturesMonitored $future, $testDate = null)
    {
        try {
            Log::info("📊 [CHECK] Checking signals for {$future->trading_symbol} with strategy: {$config->signal_strategy}");

            $todayCandles = $this->getTodayCandles($future->trading_symbol, '15minute', $testDate);

            if ($todayCandles->count() < 1) { // ← CHANGED from < 2 to < 1
                Log::info("⚠️ [CHECK] Not enough data for {$future->trading_symbol}");
                return;
            }

            Log::info("📊 [CHECK] Found {$todayCandles->count()} candles for today");

            // ✅ GET PREVIOUS DAY'S LAST CANDLE
            $previousDayLastCandle = $this->getPreviousDayLastCandle($future->trading_symbol, $testDate);

            $todayOrders = ZerodhaAutoOrder::where('future_symbol', $future->trading_symbol)
                ->where('status', true)
                ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info("📋 [CHECK] Found {$todayOrders->count()} existing orders today");

            // ✅ PASS PREVIOUS DAY CANDLE TO SYNC DETECTION
            $allSyncPoints = $this->findAllSynchronizationPoints(
                $todayCandles, 
                $future->trading_symbol, 
                $config->signal_strategy,
                $previousDayLastCandle  // ← CRITICAL: Pass previous day data
            );

            if (empty($allSyncPoints)) {
                Log::info("⚠️ [CHECK] No signal synchronization found for {$future->trading_symbol}");
                return;
            }

            Log::info("✅ [CHECK] Found " . count($allSyncPoints) . " synchronization points");

            $validSyncPoint = $this->getNextValidSyncPoint($allSyncPoints, $todayOrders, $future->trading_symbol);
            if (!$validSyncPoint) {
                Log::info("⚠️ [CHECK] No new valid sync point found");
                return;
            }

            Log::info("✅ [CHECK] Valid sync point found at index {$validSyncPoint['index']}: {$validSyncPoint['signal']['type']}");

            $this->createOrderEntry($config, $future, $validSyncPoint['candle'], $validSyncPoint['signal']);

        } catch (\Exception $e) {
            Log::error("❌ [CHECK] Error checking signals for {$future->trading_symbol}: " . $e->getMessage());
        }
    }

    /**
     * Get today's candles for 5-minute interval
     */
    private function getTodayCandles($tradingSymbol, $interval, $testDate = null)
    {
        $query = FuturesData::where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->whereNotNull('atr')
            ->whereNotNull('supertrend')
            ->whereNotNull('supertrend_direction')
            ->whereNotNull('vwap')
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

    private function findAllSynchronizationPoints($candles, $tradingSymbol, $strategy, $previousDayLastCandle = null)
    {
        $syncPoints = [];
        $totalCandles = count($candles);

        Log::info("🔍 [SYNC] Scanning {$totalCandles} candles with strategy: {$strategy}");

        $previousSyncType = null;

        // ════════════════════════════════════════════════════════════
        // ✅ STEP 1: CHECK FIRST CANDLE vs PREVIOUS DAY'S LAST CANDLE
        // ════════════════════════════════════════════════════════════
        if ($totalCandles > 0 && $previousDayLastCandle) {
            $firstCandle = $candles[0];
            
            // Get signals for previous day's last candle
            $prevDayST = $this->getPersistentSupertrendSignal($previousDayLastCandle, $tradingSymbol, collect());
            $prevDayVWAP = $this->getPersistentVWAPSignal($previousDayLastCandle, $tradingSymbol, collect());
            
            // Get signals for first candle of today
            $firstST = $this->getPersistentSupertrendSignal($firstCandle, $tradingSymbol, collect([$firstCandle]));
            $firstVWAP = $this->getPersistentVWAPSignal($firstCandle, $tradingSymbol, collect([$firstCandle]));

            Log::info("🔍 [SYNC] OVERNIGHT CHECK:");
            Log::info("  Previous Day (last candle): ST={$prevDayST}, VWAP={$prevDayVWAP}");
            Log::info("  First Candle (today 09:15): ST={$firstST}, VWAP={$firstVWAP}");

            $signalType = null;
            $isSynchronized = false;

            switch ($strategy) {
                case 'SUPERTREND':
                    // Supertrend signal changed?
                    if ($firstST == 'BUY' && $prevDayST != 'BUY') {
                        $signalType = 'BUY';
                        $isSynchronized = true;
                    } elseif ($firstST == 'SELL' && $prevDayST != 'SELL') {
                        $signalType = 'SELL';
                        $isSynchronized = true;
                    }
                    break;

                case 'VWAP':
                    // VWAP signal changed?
                    if ($firstVWAP == 'BUY' && $prevDayVWAP != 'BUY') {
                        $signalType = 'BUY';
                        $isSynchronized = true;
                    } elseif ($firstVWAP == 'SELL' && $prevDayVWAP != 'SELL') {
                        $signalType = 'SELL';
                        $isSynchronized = true;
                    }
                    break;

                case 'BOTH':
                default:
                    // Both must align AND both must have changed
                    if ($firstST == 'BUY' && $firstVWAP == 'BUY') {
                        if (!($prevDayST == 'BUY' && $prevDayVWAP == 'BUY')) {
                            $signalType = 'BUY';
                            $isSynchronized = true;
                        }
                    } elseif ($firstST == 'SELL' && $firstVWAP == 'SELL') {
                        if (!($prevDayST == 'SELL' && $prevDayVWAP == 'SELL')) {
                            $signalType = 'SELL';
                            $isSynchronized = true;
                        }
                    }
                    break;
            }

            if ($isSynchronized && $signalType) {
                Log::info("✅ [SYNC] {$signalType} sync at FIRST candle (overnight change) ({$firstCandle->timestamp})");
                
                $syncPoints[] = [
                    'index' => 0,
                    'candle' => $firstCandle,
                    'signal' => [
                        'type' => $signalType,
                        'supertrend' => $firstST,
                        'vwap' => $firstVWAP,
                        'price' => $firstCandle->close,
                        'strategy' => $strategy
                    ]
                ];
                $previousSyncType = $signalType;
            }
        }

        // ════════════════════════════════════════════════════════════
        // ✅ STEP 2: CHECK INTRADAY CANDLE-TO-CANDLE CHANGES
        // ════════════════════════════════════════════════════════════
        for ($i = 1; $i < $totalCandles; $i++) {
            $currentCandle = $candles[$i];
            $previousCandle = $candles[$i - 1];

            $prevST = $this->getPersistentSupertrendSignal($previousCandle, $tradingSymbol, $candles->slice(0, $i));
            $currST = $this->getPersistentSupertrendSignal($currentCandle, $tradingSymbol, $candles->slice(0, $i + 1));
            
            $prevVWAP = $this->getPersistentVWAPSignal($previousCandle, $tradingSymbol, $candles->slice(0, $i));
            $currVWAP = $this->getPersistentVWAPSignal($currentCandle, $tradingSymbol, $candles->slice(0, $i + 1));

            $signalType = null;
            $isSynchronized = false;

            switch ($strategy) {
                case 'SUPERTREND':
                    if ($currST == 'BUY' && $prevST != 'BUY') {
                        $signalType = 'BUY';
                        $isSynchronized = true;
                    } elseif ($currST == 'SELL' && $prevST != 'SELL') {
                        $signalType = 'SELL';
                        $isSynchronized = true;
                    }
                    break;

                case 'VWAP':
                    if ($currVWAP == 'BUY' && $prevVWAP != 'BUY') {
                        $signalType = 'BUY';
                        $isSynchronized = true;
                    } elseif ($currVWAP == 'SELL' && $prevVWAP != 'SELL') {
                        $signalType = 'SELL';
                        $isSynchronized = true;
                    }
                    break;

                case 'BOTH':
                default:
                    if ($currST == 'BUY' && $currVWAP == 'BUY') {
                        if (!($prevST == 'BUY' && $prevVWAP == 'BUY') || $previousSyncType != 'BUY') {
                            $signalType = 'BUY';
                            $isSynchronized = true;
                        }
                    } elseif ($currST == 'SELL' && $currVWAP == 'SELL') {
                        if (!($prevST == 'SELL' && $prevVWAP == 'SELL') || $previousSyncType != 'SELL') {
                            $signalType = 'SELL';
                            $isSynchronized = true;
                        }
                    }
                    break;
            }

            if ($isSynchronized && $signalType && $signalType != $previousSyncType) {
                Log::info("✅ [SYNC] {$signalType} sync at index {$i} ({$currentCandle->timestamp})");
                
                $syncPoints[] = [
                    'index' => $i,
                    'candle' => $currentCandle,
                    'signal' => [
                        'type' => $signalType,
                        'supertrend' => $currST,
                        'vwap' => $currVWAP,
                        'price' => $currentCandle->close,
                        'strategy' => $strategy
                    ]
                ];
                $previousSyncType = $signalType;
            }
        }

        Log::info("✅ [SYNC] Total synchronization points found: " . count($syncPoints));
        return $syncPoints;
    }

    /**
     * Get next valid sync point
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
        
        Log::info("📋 [VALID] Last order was: {$lastSignalType} at {$lastOrder->signal_detected_at}");

        foreach ($allSyncPoints as $syncPoint) {
            $syncTime = $syncPoint['candle']->timestamp;
            $syncType = $syncPoint['signal']['type'];

            if ($syncTime <= $lastOrder->signal_detected_at) {
                continue;
            }

            if ($syncType == $lastSignalType) {
                continue;
            }

            Log::info("✅ [VALID] Found valid new sequence at {$syncTime}: {$syncType}");
            return $syncPoint;
        }

        return null;
    }

    /**
     * Get persistent Supertrend signal
     */
    private function getPersistentSupertrendSignal($candle, $tradingSymbol, $candlesUpToCurrent = null)
    {
        if ($candle->supertrend_signal == 'BUY' || $candle->supertrend_signal == 'SELL') {
            return $candle->supertrend_signal;
        }

        if ($candlesUpToCurrent && $candlesUpToCurrent->count() > 0) {
            $reversed = $candlesUpToCurrent->reverse();
            foreach ($reversed as $pastCandle) {
                if (in_array($pastCandle->supertrend_signal, ['BUY', 'SELL'])) {
                    return $pastCandle->supertrend_signal;
                }
            }
        }

        $lastSignal = FuturesData::where('trading_symbol', $tradingSymbol)
            ->where('interval', '15minute')
            ->where('timestamp', '<', $candle->timestamp)
            ->whereIn('supertrend_signal', ['BUY', 'SELL'])
            ->orderBy('timestamp', 'DESC')
            ->first();

        return $lastSignal ? $lastSignal->supertrend_signal : 'HOLD';
    }

    /**
     * Get persistent VWAP signal (handles GAP_UP/GAP_DOWN)
     */
    private function getPersistentVWAPSignal($candle, $tradingSymbol, $candlesUpToCurrent = null)
    {
        $currentSignal = $candle->vwap_signal;

        // Convert GAP_UP to BUY and GAP_DOWN to SELL
        if ($currentSignal === 'GAP_UP' || $currentSignal === 'BUY') {
            return 'BUY';
        } elseif ($currentSignal === 'GAP_DOWN' || $currentSignal === 'SELL') {
            return 'SELL';
        }

        // Look back in provided candles
        if ($candlesUpToCurrent && $candlesUpToCurrent->count() > 0) {
            $reversed = $candlesUpToCurrent->reverse();
            foreach ($reversed as $pastCandle) {
                $pastSignal = $pastCandle->vwap_signal;
                if ($pastSignal === 'GAP_UP' || $pastSignal === 'BUY') {
                    return 'BUY';
                } elseif ($pastSignal === 'GAP_DOWN' || $pastSignal === 'SELL') {
                    return 'SELL';
                }
            }
        }

        // Fallback to database lookup
        $lastSignal = FuturesData::where('trading_symbol', $tradingSymbol)
            ->where('interval', '15minute')
            ->where('timestamp', '<', $candle->timestamp)
            ->whereIn('vwap_signal', ['BUY', 'SELL', 'GAP_UP', 'GAP_DOWN'])
            ->orderBy('timestamp', 'DESC')
            ->first();

        if ($lastSignal) {
            $signal = $lastSignal->vwap_signal;
            if ($signal === 'GAP_UP' || $signal === 'BUY') {
                return 'BUY';
            } elseif ($signal === 'GAP_DOWN' || $signal === 'SELL') {
                return 'SELL';
            }
        }

        return 'HOLD';
    }

    /**
     * Create order entry
     */
    private function createOrderEntry(ZerodhaAutoConfig $config, FuturesMonitored $future, $candle, $signal)
    {
        try {
            Log::info("📝 [CREATE] Creating order for {$future->trading_symbol}");
            
            $optionDetails = $this->getATMOption($future->trading_symbol, $signal['type'], $signal['price']);

            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find ATM option");
                return;
            }

            // Get appropriate quantity based on symbol type
            $quantity = $config->getQuantityForSymbol($future->trading_symbol);
            Log::info("📝 [CREATE] Using quantity: {$quantity} for {$future->trading_symbol}");

            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($quantity);

            $orderData = [
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $config->broker_api_id,
                'future_symbol' => $future->trading_symbol,
                'future_token' => $future->instrument_token,
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

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
        }
    }

    /**
     * Get ATM option
     */
    private function getATMOption($futureSymbol, $signalType, $futurePrice)
    {
        try {
            $baseSymbol = $this->extractBaseSymbol($futureSymbol);
            $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
            $strikeInterval = $this->getStrikeInterval($baseSymbol);
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            $option = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now())
                ->orderBy('expiry', 'ASC')
                ->first();

            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike])
                    ->orderBy('strike_diff', 'ASC')
                    ->orderBy('expiry', 'ASC')
                    ->first();
            }

            if (!$option) {
                return null;
            }

            $ltp = $this->getOptionLTP($option->instrument_token);

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

    private function extractBaseSymbol($futureSymbol)
    {
        return preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $futureSymbol);
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

    private function getOptionLTP($instrumentToken)
    {
        try {
            if (!$this->kite) {
                $broker = ZerodhaAutoConfig::with('broker')
                    ->where('status', 1)
                    ->first()
                    ->broker;
                    
                $this->kite = new KiteConnect($broker->api_key);
                $this->kite->setAccessToken($broker->access_token);
            }

            $instrument = ZerodhaInstrument::where('instrument_token', $instrumentToken)->first();
            $quoteKey = "NFO:" . $instrument->trading_symbol;
            $quotes = $this->kite->getQuote([$quoteKey]);
            
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
     * Place orders with freeze quantity handling
     */
    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== Starting Order Placement ===');

            $pendingOrders = ZerodhaAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->with(['config', 'broker'])
                ->get();

            foreach ($pendingOrders as $order) {
                $this->placeOrder($order);
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
            $this->kite = new KiteConnect($broker->api_key);
            $this->kite->setAccessToken($broker->access_token);

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();
            
            if (!$instrument) {
                Log::error("❌ [ORDER] Instrument not found");
                return;
            }

            $this->placePyramidOrders($order, $instrument);

            $order->update([
                'is_order_placed' => true,
                'order_placed_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Error: " . $e->getMessage());
        }
    }

    private function placePyramidOrders(ZerodhaAutoOrder $order, $instrument)
    {
        $pyramids = [$order->pyramid_1, $order->pyramid_2, $order->pyramid_3];
        $delays = [0];
        
        if ($order->pyramid_2) $delays[] = $order->config->pyramid_freq * 60;
        if ($order->pyramid_3) $delays[] = $order->config->pyramid_freq * 60 * 2;

        // Get freeze limit for this symbol (in LOTS)
        $baseSymbol = $this->extractBaseSymbol($order->future_symbol);
        $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;
        
        if ($freezeLimitLots) {
            Log::info("🔍 [FREEZE] Freeze limit for {$baseSymbol}: {$freezeLimitLots} lots (lot_size: {$instrument->lot_size})");
        }

        foreach ($pyramids as $pyramidIndex => $pyramidQty) {
            if (!$pyramidQty) continue;

            if (($delays[$pyramidIndex] ?? 0) > 0) {
                sleep($delays[$pyramidIndex]);
            }

            // Calculate price for LIMIT orders
            $price = null;
            if ($order->order_type == 'LIMIT') {
                $price = $this->calculateLimitPrice(
                    $order->entry_price,
                    $order->config->disc_ltp,
                    $order->signal_type,
                    $instrument->tick_size
                );
            }

            // ✅ Handle freeze quantity - pyramidQty is already in LOTS
            if ($freezeLimitLots && $pyramidQty > $freezeLimitLots) {
                Log::info("🔄 [FREEZE] Pyramid {$pyramidQty} lots exceeds freeze limit {$freezeLimitLots} lots, splitting orders");
                
                $numOrders = ceil($pyramidQty / $freezeLimitLots);
                $remainingLots = $pyramidQty;
                
                for ($i = 0; $i < $numOrders; $i++) {
                    $lotsToPlace = min($freezeLimitLots, $remainingLots);
                    
                    Log::info(sprintf(
                        "📦 [FREEZE] Placing split order %d/%d: %d lots (%d quantity)",
                        (int) ($i + 1),
                        (int) $numOrders,
                        (int) $lotsToPlace,
                        (int) ($lotsToPlace * $instrument->lot_size)
                    ));
                    
                    $this->placeKiteOrder($order, $lotsToPlace, $price, $instrument);
                    
                    $remainingLots -= $lotsToPlace;
                    
                    if ($i < $numOrders - 1) {
                        sleep(2);
                    }
                }
            } else {
                // Place single order if within freeze limit
                $this->placeKiteOrder($order, $pyramidQty, $price, $instrument);
            }
        }
    }

    private function calculateLimitPrice($entryPrice, $discLtp, $signalType, $tickSize)
    {
        $discount = ($entryPrice * $discLtp) / 100;
        $price = $entryPrice - $discount;
        $roundedPrice = round($price / $tickSize) * $tickSize;
        return number_format($roundedPrice, 2, '.', '');
    }

    private function placeKiteOrder(ZerodhaAutoOrder $order, $quantity, $price, $instrument)
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

            $result = $this->kite->placeOrder("regular", $orderParams);
            Log::info("✅ [ORDER] Placed! ID: {$result->order_id}");
            
            $this->saveToOrderBook($order, $result->order_id, $quantity, $price);

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $quantity, $price, $e->getMessage());
        }
    }

    private function saveToOrderBook(ZerodhaAutoOrder $order, $orderId, $quantity, $price)
    {
        try {
            sleep(2);
            $orderHistory = $this->kite->getOrderHistory($orderId);
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
                'zerodha_auto_order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(ZerodhaAutoOrder $order, $quantity, $price, $error)
    {
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
            'status_message' => $error,
            'order_datetime' => now(),
            'zerodha_auto_order_id' => $order->id
        ]);
    }
}