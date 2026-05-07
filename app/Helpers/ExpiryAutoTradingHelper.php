<?php

namespace App\Helpers;

use App\Models\ExpiryAutoConfig;
use App\Models\ExpiryAutoOrder;
use App\Models\ExpiryData;
use App\Models\ExpiryMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class ExpiryAutoTradingHelper
{
    private $kite;

    // Freeze quantity limits for index symbols
    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'SENSEX' => 10,
    ];

    /**
     * Main process - Detect signals and create orders (1-minute interval, ONLY on expiry day)
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting Expiry Auto Trading Signal Detection (1-Minute) ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = ExpiryAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active expiry configurations found');
                return;
            }

            // ✅ CRITICAL: Get ONLY symbols expiring TODAY
            $expiringToday = ExpiryMonitored::getExpiringToday();

            if ($expiringToday->isEmpty()) {
                Log::info('⚠️ No symbols expiring today - skipping expiry auto trade');
                return;
            }

            Log::info("🎯 EXPIRY DAY! Found {$expiringToday->count()} symbols expiring today");

            foreach ($configs as $config) {
                foreach ($expiringToday as $symbol) {
                    $this->checkAndCreateOrder($config, $symbol, $testDate);
                }
                
                $config->update(['last_checked_at' => now()]);
            }

            Log::info('=== Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('Expiry Signal Processing Error: ' . $e->getMessage());
        }
    }

    /**
     * ✅ GET PREVIOUS DAY'S LAST CANDLE
     */
    private function getPreviousDayLastCandle($symbol, $testDate = null)
    {
        try {
            $targetDate = $testDate ? Carbon::parse($testDate) : Carbon::today();

            $lastCandle = ExpiryData::where('symbol', $symbol)
                ->where('timestamp', '<', $targetDate->format('Y-m-d 00:00:00'))
                ->whereNotNull('supertrend')
                ->orderBy('timestamp', 'DESC')
                ->first();

            if ($lastCandle) {
                Log::info("📅 [PREV] Found previous day candle: {$lastCandle->timestamp}");
                Log::info("  ST Signal: {$lastCandle->supertrend_signal}, Direction: {$lastCandle->supertrend_direction}");
            } else {
                Log::info("⚠️ [PREV] No previous day candle found");
            }

            return $lastCandle;

        } catch (\Exception $e) {
            Log::error("❌ [PREV] Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check signals and create order if needed
     */
    private function checkAndCreateOrder(ExpiryAutoConfig $config, ExpiryMonitored $monitored, $testDate = null)
    {
        try {
            $todayCandles = $this->getTodayCandles($monitored->symbol, $testDate);

            if ($todayCandles->count() < 1) {
                Log::info("⚠️ [CHECK] Not enough data for {$monitored->symbol}");
                return;
            }

            // ✅ GET PREVIOUS DAY'S LAST CANDLE
            $previousDayLastCandle = $this->getPreviousDayLastCandle($monitored->symbol, $testDate);

            $todayOrders = ExpiryAutoOrder::where('symbol', $monitored->symbol)
                ->where('status', true)
                ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
                ->orderBy('created_at', 'asc')
                ->get();

            // ✅ FIND SYNCHRONIZATION POINTS
            $allSyncPoints = $this->findAllSynchronizationPoints(
                $todayCandles, 
                $monitored->symbol,
                $previousDayLastCandle
            );

            if (empty($allSyncPoints)) {
                Log::info("⚠️ [CHECK] No signal synchronization found for {$monitored->symbol}");
                return;
            }

            // ✅ Filter sync points to only include recent ones (within last 10 minutes)
            $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints);

            if (empty($recentSyncPoints)) {
                Log::info("⚠️ [CHECK] No recent sync points found (all older than 10 minutes)");
                return;
            }

            $validSyncPoint = $this->getNextValidSyncPoint($recentSyncPoints, $todayOrders, $monitored->symbol);

            if (!$validSyncPoint) {
                Log::info("⚠️ [CHECK] No new valid sync point found");
                return;
            }

            $this->createOrderEntry($config, $monitored, $validSyncPoint['candle'], $validSyncPoint['signal']);

        } catch (\Exception $e) {
            Log::error("❌ [CHECK] Error checking signals for {$monitored->symbol}: " . $e->getMessage());
        }
    }

    /**
     * ✅ Filter sync points to only include recent ones (within last 10 minutes)
     */
    private function filterRecentSyncPoints($allSyncPoints)
    {
        if (empty($allSyncPoints)) {
            return [];
        }

        $cutoffTime = Carbon::now()->subMinutes(10);
        
        $recentSyncPoints = array_filter($allSyncPoints, function($syncPoint) use ($cutoffTime) {
            $candleTime = Carbon::parse($syncPoint['candle']->timestamp);
            return $candleTime->gte($cutoffTime);
        });

        $recentSyncPoints = array_values($recentSyncPoints);
        
        if (count($recentSyncPoints) < count($allSyncPoints)) {
            $filtered = count($allSyncPoints) - count($recentSyncPoints);
            Log::info("🕐 [FILTER] Filtered out {$filtered} old sync points (older than 10 minutes)");
        }

        return $recentSyncPoints;
    }

    /**
     * Get today's candles for 1-minute interval
     */
    private function getTodayCandles($symbol, $testDate = null)
    {
        $query = ExpiryData::where('symbol', $symbol)
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
     * Find synchronization points (Supertrend signals)
     */
    private function findAllSynchronizationPoints($candles, $symbol, $previousDayLastCandle = null)
    {
        $syncPoints = [];
        $totalCandles = count($candles);

        $previousSyncType = null;

        // ✅ STEP 1: CHECK FIRST CANDLE vs PREVIOUS DAY'S LAST CANDLE
        if ($totalCandles > 0 && $previousDayLastCandle) {
            $firstCandle = $candles[0];
            
            $prevDayST = $this->getPersistentSupertrendSignal($previousDayLastCandle, $symbol, collect());
            $firstST = $this->getPersistentSupertrendSignal($firstCandle, $symbol, collect([$firstCandle]));

            $signalType = null;
            $isSynchronized = false;

            // Supertrend signal changed?
            if ($firstST == 'BUY' && $prevDayST != 'BUY') {
                $signalType = 'BUY';
                $isSynchronized = true;
            } elseif ($firstST == 'SELL' && $prevDayST != 'SELL') {
                $signalType = 'SELL';
                $isSynchronized = true;
            }

            if ($isSynchronized && $signalType) {
                Log::info("✅ [SYNC] {$signalType} sync at FIRST candle (overnight change) ({$firstCandle->timestamp})");
                
                $syncPoints[] = [
                    'index' => 0,
                    'candle' => $firstCandle,
                    'signal' => [
                        'type' => $signalType,
                        'supertrend' => $firstST,
                        'price' => $firstCandle->close,
                    ]
                ];
                $previousSyncType = $signalType;
            }
        }

        // ✅ STEP 2: CHECK INTRADAY CANDLE-TO-CANDLE CHANGES
        for ($i = 1; $i < $totalCandles; $i++) {
            $currentCandle = $candles[$i];
            $previousCandle = $candles[$i - 1];

            $prevST = $this->getPersistentSupertrendSignal($previousCandle, $symbol, $candles->slice(0, $i));
            $currST = $this->getPersistentSupertrendSignal($currentCandle, $symbol, $candles->slice(0, $i + 1));

            $signalType = null;
            $isSynchronized = false;

            if ($currST == 'BUY' && $prevST != 'BUY') {
                $signalType = 'BUY';
                $isSynchronized = true;
            } elseif ($currST == 'SELL' && $prevST != 'SELL') {
                $signalType = 'SELL';
                $isSynchronized = true;
            }

            if ($isSynchronized && $signalType && $signalType != $previousSyncType) {
                Log::info("✅ [SYNC] {$signalType} sync at index {$i} ({$currentCandle->timestamp})");
                
                $syncPoints[] = [
                    'index' => $i,
                    'candle' => $currentCandle,
                    'signal' => [
                        'type' => $signalType,
                        'supertrend' => $currST,
                        'price' => $currentCandle->close,
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
    private function getNextValidSyncPoint($allSyncPoints, $existingOrders, $symbol)
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
    private function getPersistentSupertrendSignal($candle, $symbol, $candlesUpToCurrent = null)
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

        $lastSignal = ExpiryData::where('symbol', $symbol)
            ->where('timestamp', '<', $candle->timestamp)
            ->whereIn('supertrend_signal', ['BUY', 'SELL'])
            ->orderBy('timestamp', 'DESC')
            ->first();

        return $lastSignal ? $lastSignal->supertrend_signal : 'HOLD';
    }

    /**
     * Create order entry
     */
    private function createOrderEntry(ExpiryAutoConfig $config, ExpiryMonitored $monitored, $candle, $signal)
    {
        try {
            Log::info("📝 [CREATE] Creating order for {$monitored->symbol}");
            
            $optionDetails = $this->getATMOption($monitored->symbol, $signal['type'], $signal['price']);

            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find ATM option");
                return;
            }

            // ✅ Get appropriate quantity based on symbol type
            $quantity = $config->getQuantityForSymbol($monitored->symbol);
            Log::info("📝 [CREATE] Using quantity: {$quantity} for {$monitored->symbol}");

            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($quantity);

            $orderData = [
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $config->broker_api_id,
                'symbol' => $monitored->symbol,
                'instrument_token' => $monitored->instrument_token,
                'signal_type' => $signal['type'],
                'supertrend_signal' => $signal['supertrend'],
                'signal_detected_at' => $candle->timestamp,
                'option_symbol' => $optionDetails['symbol'],
                'option_token' => $optionDetails['token'],
                'option_type' => $optionDetails['type'],
                'strike_price' => $optionDetails['strike'],
                'index_price' => $signal['price'],
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

            $order = ExpiryAutoOrder::create($orderData);

            Log::info("✅ [CREATE] Order created! ID: {$order->id}");

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
        }
    }

    /**
     * Get ATM option
     */
    private function getATMOption($symbol, $signalType, $indexPrice)
    {
        try {
            $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
            $strikeInterval = $this->getStrikeInterval($symbol);
            $calculatedStrike = round($indexPrice / $strikeInterval) * $strikeInterval;

            $option = ZerodhaInstrument::where('name', $symbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now())
                ->orderBy('expiry', 'ASC')
                ->first();

            if (!$option) {
                $option = ZerodhaInstrument::where('name', $symbol)
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

    private function getStrikeInterval($symbol)
    {
        $intervals = [
            'NIFTY' => 50,
            'BANKNIFTY' => 100,
            'SENSEX' => 100,
        ];
        return $intervals[$symbol] ?? 50;
    }

    private function getOptionLTP($instrumentToken)
    {
        try {
            if (!$this->kite) {
                $broker = ExpiryAutoConfig::with('broker')
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
            Log::info('=== Starting Expiry Order Placement ===');

            $pendingOrders = ExpiryAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->with(['config', 'broker'])
                ->get();

            foreach ($pendingOrders as $order) {
                $this->placeOrder($order);
            }

            Log::info('=== Expiry Order Placement Completed ===');

        } catch (\Exception $e) {
            Log::error('Expiry Order Placement Error: ' . $e->getMessage());
        }
    }

    private function placeOrder(ExpiryAutoOrder $order)
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

    private function placePyramidOrders(ExpiryAutoOrder $order, $instrument)
    {
        $pyramids = [$order->pyramid_1, $order->pyramid_2, $order->pyramid_3];
        $delays = [0];
        
        if ($order->pyramid_2) $delays[] = $order->config->pyramid_freq * 60;
        if ($order->pyramid_3) $delays[] = $order->config->pyramid_freq * 60 * 2;

        // Get freeze limit for this symbol (in LOTS)
        $freezeLimitLots = self::FREEZE_LIMITS[$order->symbol] ?? null;
        
        if ($freezeLimitLots) {
            Log::info("🔍 [FREEZE] Freeze limit for {$order->symbol}: {$freezeLimitLots} lots (lot_size: {$instrument->lot_size})");
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

    private function placeKiteOrder(ExpiryAutoOrder $order, $quantity, $price, $instrument)
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

    private function saveToOrderBook(ExpiryAutoOrder $order, $orderId, $quantity, $price)
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
                'expiry_auto_order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(ExpiryAutoOrder $order, $quantity, $price, $error)
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
            'expiry_auto_order_id' => $order->id
        ]);
    }
}