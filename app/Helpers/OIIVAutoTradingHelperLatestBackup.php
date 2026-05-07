<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\OptionStrike;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class OIIVAutoTradingHelperLatestBackup
{
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Main process - Detect Price + OI signals and create orders
     * 
     * NEW LOGIC (Price Movement + OI Change):
     * - Price Up + OI -ve = Short Covering (Bullish) → BUY CE
     * - Price Up + OI +ve = Long Buildup (Bullish) → BUY CE
     * - Price Down + OI -ve = Long Unwinding (Bearish) → BUY PE
     * - Price Down + OI +ve = Short Buildup (Bearish) → BUY PE
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting Price + OI Auto Trading Signal Detection ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = OIIVAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active configs");

            // Get today's date
            $currentDate = $testDate ? Carbon::parse($testDate)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            
            Log::info("📅 Processing date: {$currentDate}");

            // Get all FUT records (base data)
            $futRecords = OptionStrike::where('strike_position', 'FUT')
                ->where('trading_date', $currentDate)
                ->get();

            if ($futRecords->isEmpty()) {
                Log::info('No FUT data found for today');
                return;
            }

            Log::info("✅ Found {$futRecords->count()} symbols to analyze");

            // Group by broker
            $recordsByBroker = $futRecords->groupBy('broker_api_id');

            foreach ($recordsByBroker as $brokerId => $brokerRecords) {
                $broker = BrokerApi::find($brokerId);
                
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} has invalid token, skipping");
                    continue;
                }

                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log::info("Processing {$brokerRecords->count()} symbols for broker: {$broker->client_name}");

                // Initialize Kite instance for this broker
                if (!isset($this->kiteInstances[$broker->id])) {
                    $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                    $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
                }

                foreach ($brokerRecords as $futRecord) {
                    $config = $this->findConfigForBroker($configs, $broker);
                    
                    if ($config) {
                        $this->analyzeAndCreateOrder($config, $futRecord, $broker, $currentDate);
                    } else {
                        Log::debug("No config found for broker: {$broker->id}");
                    }
                }
            }

            Log::info('=== Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('Price+OI Signal Processing Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Find matching config for broker
     */
    private function findConfigForBroker($configs, BrokerApi $broker)
    {
        return $configs->first(function($config) use ($broker) {
            return $config->broker_api_id === $broker->id;
        });
    }

    /**
     * Analyze Price + OI signals and create order
     * 
     * LOGIC:
     * 1. Get current price (3 PM) vs opening price
     * 2. Get OI change %
     * 3. Determine scenario and place order
     */
    private function analyzeAndCreateOrder(OIIVAutoConfig $config, $futRecord, BrokerApi $broker, string $date)
    {
        try {
            $symbol = $futRecord->underlying_symbol;
            
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [ANALYZE] {$symbol}");
            
            // Get CE and PE data (for order placement later)
            $ceData = OptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $symbol)
                ->where('strike_position', 'CE_MERGED')
                ->where('trading_date', $date)
                ->first();

            $peData = OptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $symbol)
                ->where('strike_position', 'PE_MERGED')
                ->where('trading_date', $date)
                ->first();

            if (!$ceData || !$peData) {
                Log::debug("   ⚠️ Missing CE/PE data for {$symbol}");
                return;
            }

            // ✅ Get current price from Zerodha API
            $kite = $this->kiteInstances[$broker->id];
            $currentPrice = $this->getCurrentPrice($kite, $futRecord->trading_symbol);
            
            if (!$currentPrice) {
                Log::error("   ❌ Could not fetch current price for {$futRecord->trading_symbol}");
                return;
            }

            // Get opening price (stored in spot_price or open_price field)
            $openingPrice = $futRecord->open_price ?? $futRecord->spot_price;
            
            if (!$openingPrice) {
                Log::error("   ❌ Opening price not available for {$symbol}");
                return;
            }

            // Calculate price change
            $priceChange = $currentPrice - $openingPrice;
            $priceChangePercent = (($priceChange / $openingPrice) * 100);
            
            // Get OI change %
            $oiChange = $futRecord->daily_oi_change_pct ?? 0;
            
            // Determine directions
            $priceDirection = $priceChange > 0 ? 'UP' : ($priceChange < 0 ? 'DOWN' : 'FLAT');
            $oiDirection = $oiChange > 0 ? 'POSITIVE' : ($oiChange < 0 ? 'NEGATIVE' : 'FLAT');

            Log::info("   Opening Price: ₹{$openingPrice}");
            Log::info("   Current Price: ₹{$currentPrice}");
            Log::info("   Price Change: ₹{$priceChange} ({$priceChangePercent}%) → {$priceDirection}");
            Log::info("   OI Change: {$oiChange}% → {$oiDirection}");

            // Skip if no clear direction
            if ($priceDirection === 'FLAT') {
                Log::debug("   ⚠️ Skipping {$symbol} - No price movement");
                return;
            }

            // Determine signal based on new logic
            $signal = $this->determineSignal($priceDirection, $oiDirection, $priceChange, $oiChange);

            if (!$signal) {
                Log::debug("   ⚠️ No signal generated for {$symbol}");
                return;
            }

            // Create order
            $this->createOrder($config, $futRecord, $ceData, $peData, $broker, $signal, $currentPrice, $openingPrice);

        } catch (\Exception $e) {
            Log::error("❌ [ANALYZE] Error for {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Get current price from Zerodha API
     */
    private function getCurrentPrice($kite, $tradingSymbol)
    {
        try {
            $quoteKey = "NFO:" . $tradingSymbol;
            $quotes = $kite->getQuote([$quoteKey]);
            
            if (isset($quotes->$quoteKey->last_price)) {
                return $quotes->$quoteKey->last_price;
            }
            
            $quotesArray = json_decode(json_encode($quotes), true);
            if (isset($quotesArray[$quoteKey]['last_price'])) {
                return $quotesArray[$quoteKey]['last_price'];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error fetching price for {$tradingSymbol}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Determine signal based on Price + OI logic
     */
    private function determineSignal($priceDirection, $oiDirection, $priceChange, $oiChange)
    {
        // SCENARIO 1: Price UP + OI NEGATIVE = Short Covering (Bullish) → BUY CE
        if ($priceDirection === 'UP' && $oiDirection === 'NEGATIVE') {
            Log::info("   ✅ SIGNAL: Short Covering (Bullish) → BUY CE");
            return [
                'type' => 'CE',
                'action' => 'BUY_CE',
                'reason' => 'Short Covering - Bullish',
                'scenario' => 'Price Up + OI Negative',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        // SCENARIO 2: Price UP + OI POSITIVE = Long Buildup (Bullish) → BUY CE
        if ($priceDirection === 'UP' && $oiDirection === 'POSITIVE') {
            Log::info("   ✅ SIGNAL: Long Buildup (Bullish) → BUY CE");
            return [
                'type' => 'CE',
                'action' => 'BUY_CE',
                'reason' => 'Long Buildup - Bullish',
                'scenario' => 'Price Up + OI Positive',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        // SCENARIO 3: Price DOWN + OI NEGATIVE = Long Unwinding (Bearish) → BUY PE
        if ($priceDirection === 'DOWN' && $oiDirection === 'NEGATIVE') {
            Log::info("   ✅ SIGNAL: Long Unwinding (Bearish) → BUY PE");
            return [
                'type' => 'PE',
                'action' => 'BUY_PE',
                'reason' => 'Long Unwinding - Bearish',
                'scenario' => 'Price Down + OI Negative',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        // SCENARIO 4: Price DOWN + OI POSITIVE = Short Buildup (Bearish) → BUY PE
        if ($priceDirection === 'DOWN' && $oiDirection === 'POSITIVE') {
            Log::info("   ✅ SIGNAL: Short Buildup (Bearish) → BUY PE");
            return [
                'type' => 'PE',
                'action' => 'BUY_PE',
                'reason' => 'Short Buildup - Bearish',
                'scenario' => 'Price Down + OI Positive',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        return null;
    }

    /**
     * Create order entry
     */
    private function createOrder(
        OIIVAutoConfig $config,
        $futRecord,
        $ceData,
        $peData,
        BrokerApi $broker,
        array $signal,
        $currentPrice,
        $openingPrice
    ) {
        try {
            $optionType = $signal['type'];

            // ✅ Check if order already exists
            $existing = OIIVAutoOrder::where('broker_api_id', $broker->id)
                ->where('symbol', $futRecord->underlying_symbol)
                ->whereDate('signal_detected_at', $futRecord->trading_date)
                ->where('option_type', $optionType)
                ->where('status', true)
                ->first();

            if ($existing) {
                Log::info("   ⚠️ {$optionType} order already exists - ID: {$existing->id}");
                return;
            }

            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("✅ [CREATE] Creating {$optionType} order for: {$futRecord->underlying_symbol}");
            
            // Get ATM option details
            $optionDetails = $this->getATMOption($broker, $futRecord->trading_symbol, $optionType, $currentPrice, $config);
            
            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find ATM option");
                return;
            }

            // ✅ Check if this EXACT option already in order_book
            $orderBookExists = OrderBook::where('user_id', $config->user_id)
                ->where('trading_symbol', $optionDetails['symbol'])
                ->whereDate('order_datetime', $futRecord->trading_date)
                ->where('transaction_type', 'BUY')
                ->where('status', '!=', 'FAILED')
                ->exists();

            if ($orderBookExists) {
                Log::info("   ⚠️ {$optionType} order already in OrderBook: {$optionDetails['symbol']}");
                return;
            }

            $quantity = $config->getQuantityForSymbol($futRecord->trading_symbol);

            $orderData = [
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $broker->id,
                'symbol' => $futRecord->underlying_symbol,
                'trading_symbol' => $futRecord->trading_symbol,
                'instrument_token' => $futRecord->instrument_token,
                
                // Store signal data
                'btst_signal' => $signal['action'],
                'btst_confidence' => 100,
                'btst_reason' => $signal['reason'] . ' | ' . $signal['scenario'],
                'signal_detected_at' => now(),
                // 'signal_detected_at' => now()->subDay(),
                
                // Store Price + OI data
                'fut_oi_signal' => $signal['scenario'], // e.g., "Price Up + OI Negative"
                'fut_oi_strength' => $signal['reason'], // e.g., "Short Covering - Bullish"
                
                // Keep other data for reference
                'ce_oi_signal' => $ceData->direction ?? 'N/A',
                'pe_oi_signal' => $peData->direction ?? 'N/A',
                'ce_iv_signal' => 'N/A',
                'ce_iv_strength' => 'N/A',
                'pe_iv_signal' => 'N/A',
                'pe_iv_strength' => 'N/A',
                
                'spot_price' => $currentPrice,
                'option_symbol' => $optionDetails['symbol'],
                'option_token' => $optionDetails['token'],
                'option_type' => $optionType,
                'strike_price' => $optionDetails['strike'],
                'entry_price' => $optionDetails['ltp'],
                'current_price' => $optionDetails['ltp'],
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $quantity,
                'is_order_placed' => false,
                'status' => true
            ];

            $order = OIIVAutoOrder::create($orderData);
            
            Log::info("✅ [CREATE] Order created successfully!");
            Log::info("   Order ID: {$order->id}");
            Log::info("   Signal: {$signal['action']}");
            Log::info("   Scenario: {$signal['scenario']}");
            Log::info("   Reason: {$signal['reason']}");
            Log::info("   Price: ₹{$openingPrice} → ₹{$currentPrice} ({$signal['price_change']})");
            Log::info("   OI Change: {$signal['oi_change']}%");
            Log::info("   Option: {$optionDetails['symbol']}");
            Log::info("   Strike: {$optionDetails['strike']}");
            Log::info("   LTP: ₹{$optionDetails['ltp']}");
            Log::info("   Quantity: {$quantity} lots");

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
            Log::error("   Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get ATM option
     */
    private function getATMOption(BrokerApi $broker, $tradingSymbol, $optionType, $futurePrice, OIIVAutoConfig $config)
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
                ->whereDate('expiry', '>=', now())
                ->orderBy('expiry', 'ASC');

            $option = $query->first();

            if (!$option) {
                $query = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike])
                    ->orderBy('strike_diff', 'ASC')
                    ->orderBy('expiry', 'ASC');

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
            Log::info('=== Starting Price+OI Order Placement ===');

            $pendingOrders = OIIVAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->whereHas('config', function($query) {
                    $query->where('status', true);
                })
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

    private function placeOrder(OIIVAutoOrder $order)
    {
        try {
            Log::info("📤 [ORDER] Placing: {$order->option_symbol}");

            $broker = $order->broker;
            
            if (!$broker->hasValidToken()) {
                Log::error("❌ [ORDER] Invalid broker token");
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Invalid token");
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
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Instrument not found");
                return;
            }

            $this->placeKiteOrder($order, $order->quantity, $instrument, $kite);

            $order->update([
                'is_order_placed' => true,
                'order_placed_at' => now()
            ]);

            Log::info("✅ [ORDER] Order processed: ID {$order->id}");

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $order->quantity ?? 0, null, $e->getMessage());
        }
    }

    private function placeKiteOrder(OIIVAutoOrder $order, $quantity, $instrument, $kite)
    {
        try {
            $price = null;
            if ($order->order_type == 'LIMIT') {
                $discount = ($order->entry_price * $order->config->disc_ltp) / 100;
                $price = $order->entry_price - $discount;
                $roundedPrice = round($price / $instrument->tick_size) * $instrument->tick_size;
                $price = number_format($roundedPrice, 2, '.', '');
            }

            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->trading_symbol);
            $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

            if ($freezeLimitLots && $quantity > $freezeLimitLots) {
                Log::info("🔄 [FREEZE] Splitting {$quantity} lots");
                
                $numOrders = ceil($quantity / $freezeLimitLots);
                $remainingLots = $quantity;
                
                for ($i = 0; $i < $numOrders; $i++) {
                    $lotsToPlace = min($freezeLimitLots, $remainingLots);
                    $this->executeSingleOrder($order, $lotsToPlace, $price, $instrument, $kite);
                    $remainingLots -= $lotsToPlace;
                    
                    if ($i < $numOrders - 1) sleep(2);
                }
            } else {
                $this->executeSingleOrder($order, $quantity, $price, $instrument, $kite);
            }

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Kite Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $quantity, $price, $e->getMessage());
        }
    }

    private function executeSingleOrder($order, $quantity, $price, $instrument, $kite)
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
            Log::error("❌ [ORDER] Execution Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function saveToOrderBook(OIIVAutoOrder $order, $orderId, $quantity, $price)
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
                'oiiv_auto_order_id' => $order->id
            ]);

            Log::info("✅ [ORDER_BOOK] Saved order {$orderId}");

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(OIIVAutoOrder $order, $quantity, $price, $error)
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
                'oiiv_auto_order_id' => $order->id
            ]);

            Log::info("❌ [ORDER_BOOK] Saved failed order");

        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }
}