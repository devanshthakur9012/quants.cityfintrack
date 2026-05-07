<?php

namespace App\Helpers;

use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use App\Models\BrokerApi;
use App\Models\OrderBook;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

class ZerodhaAutoOrderHelper
{
    private $kite;

    /**
     * Process all active configurations and detect signals
     */
    public function processAutoOrders($testDate = null)
    {
        try {
            Log::info('=== Starting Auto Order Processing ===');
            
            // Get all active configurations with Zerodha broker
            $configs = HistoricalOrder::where('status', 1)
                ->whereHas('broker', function($q) {
                    $q->where('client_type', 'Zerodha');
                })
                ->with(['broker', 'historicalPortfolio'])
                ->get();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found for Zerodha');
                return;
            }

            foreach ($configs as $config) {
                $this->processConfig($config, $testDate);
            }

            Log::info('=== Auto Order Processing Completed ===');

        } catch (\Exception $e) {
            Log::error('Auto Order Processing Error: ' . $e->getMessage());
        }
    }

    /**
     * Process a single configuration
     */
    private function processConfig(HistoricalOrder $config, $testDate = null)
    {
        try {
            Log::info("Processing Config ID: {$config->id} for user: {$config->user_id}");

            // Get monitored futures matching buildup type
            $futures = $this->getMatchingFutures($config->buildup_type);

            if ($futures->isEmpty()) {
                Log::info("No matching futures for buildup type: {$config->buildup_type}");
                return;
            }

            foreach ($futures as $future) {
                $this->checkSignalsAndCreatePortfolio($config, $future, $testDate);
            }

            $config->update(['last_checked_at' => now()]);

        } catch (\Exception $e) {
            Log::error("Error processing config {$config->id}: " . $e->getMessage());
        }
    }

    /**
     * Get futures matching the buildup type
     */
    private function getMatchingFutures($buildupType)
    {
        // This is a simplified version - you may want to add more sophisticated matching
        return FuturesMonitored::where('is_active', true)->get();
    }

    /**
     * Check signals and create/update portfolio entry
     */
    private function checkSignalsAndCreatePortfolio(HistoricalOrder $config, FuturesMonitored $future, $testDate = null)
    {
        try {
            // Get the latest 2 candles to detect signal change
            $candles = $this->getLatestCandles($future->trading_symbol, $testDate);

            if ($candles->count() < 2) {
                return;
            }

            $currentCandle = $candles->first();
            $previousCandle = $candles->get(1);

            // Detect signal transition
            $signalDetected = $this->detectSignalTransition(
                $previousCandle,
                $currentCandle,
                $config->buildup_type
            );

            if (!$signalDetected) {
                return;
            }

            Log::info("Signal detected for {$future->trading_symbol}: {$signalDetected['type']}");

            // Check if portfolio entry already exists
            $portfolio = HistoricalPortfolio::where('config_id', $config->id)
                ->where('symbol_name', $future->trading_symbol)
                ->where('is_order_placed', false)
                ->first();

            if (!$portfolio) {
                // Create new portfolio entry
                $this->createPortfolioEntry($config, $future, $currentCandle, $signalDetected);
            }

        } catch (\Exception $e) {
            Log::error("Error checking signals for {$future->trading_symbol}: " . $e->getMessage());
        }
    }

    /**
     * Get latest candles for a symbol
     */
    private function getLatestCandles($tradingSymbol, $testDate = null)
    {
        $query = FuturesData::where('trading_symbol', $tradingSymbol)
            ->where('interval', '15minute')
            ->whereNotNull('atr')
            ->whereNotNull('supertrend')
            ->whereNotNull('supertrend_direction')
            ->orderBy('timestamp', 'DESC');

        if ($testDate) {
            $query->whereDate('timestamp', $testDate);
        } else {
            $query->whereDate('timestamp', Carbon::today());
        }

        return $query->limit(2)->get();
    }

    /**
     * Detect if there's a signal transition (first BUY or first SELL)
     */
    private function detectSignalTransition($previousCandle, $currentCandle, $buildupType)
    {
        // Supertrend signals
        $prevST = $previousCandle->supertrend_signal;
        $currST = $currentCandle->supertrend_signal;

        // Donchian signals
        $prevDon = $this->getPersistentDonchianSignal($previousCandle);
        $currDon = $this->getPersistentDonchianSignal($currentCandle);

        // Check for BUY signal transition (both must agree)
        if ($prevST != 'BUY' && $currST == 'BUY' && 
            $prevDon != 'BUY' && $currDon == 'BUY') {
            
            if ($this->matchesBuildupType($buildupType, 'BUY')) {
                return [
                    'type' => 'BUY',
                    'supertrend' => $currST,
                    'donchian' => $currDon,
                    'price' => $currentCandle->close
                ];
            }
        }

        // Check for SELL signal transition (both must agree)
        if ($prevST != 'SELL' && $currST == 'SELL' && 
            $prevDon != 'SELL' && $currDon == 'SELL') {
            
            if ($this->matchesBuildupType($buildupType, 'SELL')) {
                return [
                    'type' => 'SELL',
                    'supertrend' => $currST,
                    'donchian' => $currDon,
                    'price' => $currentCandle->close
                ];
            }
        }

        return false;
    }

    /**
     * Get persistent Donchian signal (simulates frontend logic)
     */
    private function getPersistentDonchianSignal($candle)
    {
        // This would need to look back through history to get persistent signal
        // For now, we'll use a simplified version
        // You may want to enhance this based on your exact requirements
        
        if ($candle->donchian_signal == 'BUY') {
            return 'BUY';
        } elseif ($candle->donchian_signal == 'SELL') {
            return 'SELL';
        }
        
        return 'NO_TRADE';
    }

    /**
     * Check if buildup type matches signal type
     */
    private function matchesBuildupType($buildupType, $signalType)
    {
        $bullish = ['Strong Bullish', 'Mild Bullish'];
        $bearish = ['Strong Bearish', 'Mild Bearish'];

        if ($signalType == 'BUY' && in_array($buildupType, $bullish)) {
            return true;
        }

        if ($signalType == 'SELL' && in_array($buildupType, $bearish)) {
            return true;
        }

        return false;
    }

    /**
     * Create portfolio entry for detected signal
     */
    private function createPortfolioEntry(HistoricalOrder $config, FuturesMonitored $future, $candle, $signalDetected)
    {
        try {
            // Calculate pyramids
            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($config->quantity);

            $txnType = $signalDetected['type']; // BUY or SELL

            $portfolio = HistoricalPortfolio::create([
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $config->broker_api_id,
                'symbol_name' => $future->trading_symbol,
                'trading_symbol' => $future->trading_symbol,
                'instrument_token' => $future->instrument_token,
                'lot_size' => 1, // Futures lot size is 1
                'supertrend_signal' => $signalDetected['supertrend'],
                'donchian_signal' => $signalDetected['donchian'],
                'combined_signal' => $signalDetected['type'],
                'txn_type' => $txnType,
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $config->quantity,
                'disc_ltp' => $config->disc_ltp,
                'pyramid_1' => $pyramid1,
                'pyramid_2' => $pyramid2,
                'pyramid_3' => $pyramid3,
                'pyramid_percent' => $config->pyramid_percent,
                'pyramid_freq' => $config->pyramid_freq,
                'entry_price' => $signalDetected['price'],
                'current_price' => $signalDetected['price'],
                'is_order_placed' => false,
                'status' => true,
                'signal_detected_at' => now(),
                'last_checked_at' => now()
            ]);

            Log::info("Portfolio entry created: ID {$portfolio->id} for {$future->trading_symbol}");

        } catch (\Exception $e) {
            Log::error("Error creating portfolio entry: " . $e->getMessage());
        }
    }

    /**
     * Place orders for pending portfolios
     */
    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== Starting Order Placement ===');

            // Get pending portfolios
            $portfolios = HistoricalPortfolio::where('is_order_placed', false)
                ->where('status', true)
                ->with(['config', 'broker'])
                ->get();

            foreach ($portfolios as $portfolio) {
                $this->placeOrderForPortfolio($portfolio);
            }

            Log::info('=== Order Placement Completed ===');

        } catch (\Exception $e) {
            Log::error('Order Placement Error: ' . $e->getMessage());
        }
    }

    /**
     * Place order for a single portfolio
     */
    private function placeOrderForPortfolio(HistoricalPortfolio $portfolio)
    {
        try {
            Log::info("Placing order for portfolio ID: {$portfolio->id}");

            $broker = $portfolio->broker;
            
            // Initialize Kite Connect
            $this->kite = new KiteConnect($broker->api_key);
            $this->kite->setAccessToken($broker->access_token);

            // Get instrument details
            $instrument = $this->getInstrumentDetails($portfolio->trading_symbol);
            if (!$instrument) {
                Log::error("Instrument not found for {$portfolio->trading_symbol}");
                return;
            }

            $tickSize = $instrument->tick_size;

            // Place pyramid orders
            $this->placePyramidOrders($portfolio, $instrument, $tickSize);

            // Mark as order placed
            $portfolio->update([
                'is_order_placed' => true,
                'order_placed_at' => now()
            ]);

            // Update config last_sync_at
            $portfolio->config->update(['last_sync_at' => now()]);

        } catch (\Exception $e) {
            Log::error("Error placing order for portfolio {$portfolio->id}: " . $e->getMessage());
        }
    }

    /**
     * Get instrument details from Zerodha
     */
    private function getInstrumentDetails($tradingSymbol)
    {
        return ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
            ->where('exchange', 'NFO')
            ->first();
    }

    /**
     * Place pyramid orders
     */
    private function placePyramidOrders(HistoricalPortfolio $portfolio, $instrument, $tickSize)
    {
        $delays = [0]; // First order immediate

        if ($portfolio->pyramid_2) {
            $delays[] = $portfolio->pyramid_freq * 60; // Convert minutes to seconds
        }

        if ($portfolio->pyramid_3) {
            $delays[] = $portfolio->pyramid_freq * 60 * 2;
        }

        $pyramids = [$portfolio->pyramid_1, $portfolio->pyramid_2, $portfolio->pyramid_3];
        $fibLevels = [38.20, 50, 61.80];

        foreach ($pyramids as $index => $qty) {
            if (!$qty) continue;

            $delay = $delays[$index] ?? 0;
            
            if ($delay > 0) {
                sleep($delay);
            }

            $price = null;
            if ($portfolio->order_type == 'LIMIT') {
                $price = $this->calculateLimitPrice(
                    $portfolio->entry_price,
                    $portfolio->disc_ltp,
                    $fibLevels[$index],
                    $portfolio->txn_type,
                    $tickSize
                );
            }

            $this->placeKiteOrder($portfolio, $qty, $price);
        }
    }

    /**
     * Calculate limit price based on fibonacci levels
     */
    private function calculateLimitPrice($entryPrice, $discLtp, $fibLevel, $txnType, $tickSize)
    {
        // Apply discount percentage
        $discount = ($entryPrice * $discLtp) / 100;
        
        if ($txnType == 'BUY') {
            $price = $entryPrice - $discount;
        } else {
            $price = $entryPrice + $discount;
        }

        // Round to tick size
        $roundedPrice = round($price / $tickSize) * $tickSize;
        
        return number_format($roundedPrice, 2, '.', '');
    }

    /**
     * Place order via Kite Connect
     */
    private function placeKiteOrder(HistoricalPortfolio $portfolio, $quantity, $price = null)
    {
        try {
            $orderParams = [
                'exchange' => 'NFO',
                'tradingsymbol' => $portfolio->trading_symbol,
                'transaction_type' => $portfolio->txn_type,
                'order_type' => $portfolio->order_type,
                'quantity' => $quantity,
                'product' => $portfolio->product,
                'validity' => 'DAY'
            ];

            if ($price) {
                $orderParams['price'] = $price;
            }

            $order = $this->kite->placeOrder("regular", $orderParams);

            Log::info("Order placed successfully: Order ID {$order->order_id}");

            // Save to order book
            $this->saveToOrderBook($portfolio, $order->order_id, $quantity, $price);

        } catch (\Exception $e) {
            Log::error("Kite order placement error: " . $e->getMessage());
            
            // Save failed order to book
            $this->saveFailedOrder($portfolio, $quantity, $price, $e->getMessage());
        }
    }

    /**
     * Save successful order to order book
     */
    private function saveToOrderBook(HistoricalPortfolio $portfolio, $orderId, $quantity, $price)
    {
        try {
            sleep(2); // Wait for order to process
            
            $orderHistory = $this->kite->getOrderHistory($orderId);
            $lastOrder = end($orderHistory);

            OrderBook::create([
                'user_id' => $portfolio->user_id,
                'broker_username' => $portfolio->broker->account_user_name,
                'order_id' => $orderId,
                'status' => $lastOrder->status ?? 'PENDING',
                'trading_symbol' => $portfolio->trading_symbol,
                'order_type' => $portfolio->order_type,
                'transaction_type' => $portfolio->txn_type,
                'product' => $portfolio->product,
                'price' => $price ?? '-',
                'quantity' => $quantity,
                'status_message' => $lastOrder->status_message ?? 'Order placed',
                'order_datetime' => now()
            ]);

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    /**
     * Save failed order to order book
     */
    private function saveFailedOrder(HistoricalPortfolio $portfolio, $quantity, $price, $error)
    {
        OrderBook::create([
            'user_id' => $portfolio->user_id,
            'broker_username' => $portfolio->broker->account_user_name,
            'order_id' => '-',
            'status' => 'FAILED',
            'trading_symbol' => $portfolio->trading_symbol,
            'order_type' => $portfolio->order_type,
            'transaction_type' => $portfolio->txn_type,
            'product' => $portfolio->product,
            'price' => $price ?? '-',
            'quantity' => $quantity,
            'status_message' => $error,
            'order_datetime' => now()
        ]);
    }
}