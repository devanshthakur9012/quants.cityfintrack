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

class ZerodhaAutoTradingHelperLiveWork
{
    private $kite;

    /**
     * Main process - Detect signals and create orders
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting Zerodha Auto Trading Signal Detection ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = ZerodhaAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            $futures = FuturesMonitored::where('is_active', true)->get();

            if ($futures->isEmpty()) {
                Log::info('No active futures found');
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
     * Check signals and create order if needed
     */
    private function checkAndCreateOrder(ZerodhaAutoConfig $config, FuturesMonitored $future, $testDate = null)
    {
        try {
            Log::info("📊 [CHECK] Checking signals for {$future->trading_symbol} with strategy: {$config->signal_strategy}");

            $todayCandles = $this->getTodayCandles($future->trading_symbol, $testDate);

            if ($todayCandles->count() < 2) {
                Log::info("⚠️ [CHECK] Not enough data for {$future->trading_symbol}");
                return;
            }

            Log::info("📊 [CHECK] Found {$todayCandles->count()} candles for today");

            $todayOrders = ZerodhaAutoOrder::where('future_symbol', $future->trading_symbol)
                ->where('status', true)
                ->whereDate('created_at', Carbon::today())
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info("📋 [CHECK] Found {$todayOrders->count()} existing orders today");

            // Find ALL synchronization points based on strategy
            $allSyncPoints = $this->findAllSynchronizationPoints($todayCandles, $future->trading_symbol, $config->signal_strategy);

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
     * Get today's candles
     */
    private function getTodayCandles($tradingSymbol, $testDate = null)
    {
        $query = FuturesData::where('trading_symbol', $tradingSymbol)
            ->where('interval', 'minute')
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
     * Find synchronization points based on strategy
     */
    private function findAllSynchronizationPoints($candles, $tradingSymbol, $strategy)
    {
        $syncPoints = [];
        $totalCandles = count($candles);

        Log::info("🔍 [SYNC] Scanning {$totalCandles} candles with strategy: {$strategy}");

        $previousSyncType = null;

        for ($i = 1; $i < $totalCandles; $i++) {
            $currentCandle = $candles[$i];
            $previousCandle = $candles[$i - 1];

            $prevST = $this->getPersistentSupertrendSignal($previousCandle, $tradingSymbol, $candles->slice(0, $i));
            $currST = $this->getPersistentSupertrendSignal($currentCandle, $tradingSymbol, $candles->slice(0, $i + 1));
            
            $prevDon = $this->getPersistentDonchianSignal($previousCandle, $tradingSymbol, $candles->slice(0, $i));
            $currDon = $this->getPersistentDonchianSignal($currentCandle, $tradingSymbol, $candles->slice(0, $i + 1));

            $signalType = null;
            $isSynchronized = false;

            // Check based on strategy
            switch ($strategy) {
                case 'SUPERTREND':
                    // Only Supertrend signal
                    if ($currST == 'BUY' && $prevST != 'BUY') {
                        $signalType = 'BUY';
                        $isSynchronized = true;
                    } elseif ($currST == 'SELL' && $prevST != 'SELL') {
                        $signalType = 'SELL';
                        $isSynchronized = true;
                    }
                    break;

                case 'DONCHIAN':
                    // Only Donchian signal
                    if ($currDon == 'BUY' && !($prevDon == 'BUY')) {
                        $signalType = 'BUY';
                        $isSynchronized = true;
                    } elseif ($currDon == 'SELL' && !($prevDon == 'SELL')) {
                        $signalType = 'SELL';
                        $isSynchronized = true;
                    }
                    break;

                case 'BOTH':
                default:
                    // Both signals must align
                    if ($currST == 'BUY' && $currDon == 'BUY') {
                        if (!($prevST == 'BUY' && $prevDon == 'BUY') || $previousSyncType != 'BUY') {
                            $signalType = 'BUY';
                            $isSynchronized = true;
                        }
                    } elseif ($currST == 'SELL' && $currDon == 'SELL') {
                        if (!($prevST == 'SELL' && $prevDon == 'SELL') || $previousSyncType != 'SELL') {
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
                        'donchian' => $currDon,
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
            ->where('interval', 'minute')
            ->where('timestamp', '<', $candle->timestamp)
            ->whereIn('supertrend_signal', ['BUY', 'SELL'])
            ->orderBy('timestamp', 'DESC')
            ->first();

        return $lastSignal ? $lastSignal->supertrend_signal : 'HOLD';
    }

    /**
     * Get persistent Donchian signal
     */
    private function getPersistentDonchianSignal($candle, $tradingSymbol, $candlesUpToCurrent = null)
    {
        // First check current candle's stored signal
        if (in_array($candle->donchian_signal, ['BUY', 'SELL'])) {
            return $candle->donchian_signal;
        }

        // Look back in provided candles
        if ($candlesUpToCurrent && $candlesUpToCurrent->count() > 0) {
            $reversed = $candlesUpToCurrent->reverse();
            foreach ($reversed as $pastCandle) {
                if (in_array($pastCandle->donchian_signal, ['BUY', 'SELL'])) {
                    return $pastCandle->donchian_signal;
                }
            }
        }

        // Fallback to database lookup
        $lastSignal = FuturesData::where('trading_symbol', $tradingSymbol)
            ->where('interval', 'minute')
            ->where('timestamp', '<', $candle->timestamp)
            ->whereIn('donchian_signal', ['BUY', 'SELL'])
            ->orderBy('timestamp', 'DESC')
            ->first();

        return $lastSignal ? $lastSignal->donchian_signal : 'NO_TRADE';
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
                'donchian_signal' => $signal['donchian'],
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
     * Place orders
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

        foreach ($pyramids as $index => $qty) {
            if (!$qty) continue;

            if (($delays[$index] ?? 0) > 0) {
                sleep($delays[$index]);
            }

            $price = null;
            if ($order->order_type == 'LIMIT') {
                $price = $this->calculateLimitPrice(
                    $order->entry_price,
                    $order->config->disc_ltp,
                    $order->signal_type,
                    $instrument->tick_size
                );
            }

            $this->placeKiteOrder($order, $qty, $price, $instrument);
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