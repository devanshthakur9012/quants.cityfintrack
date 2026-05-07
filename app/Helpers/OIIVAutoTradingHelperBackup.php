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

class OIIVAutoTradingHelperBackup
{
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Main process - Detect OI+IV confluence and create orders
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting OI+IV Auto Trading Signal Detection ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = OIIVAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active OI+IV configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active OI+IV configs");

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
            Log::error('OI+IV Signal Processing Error: ' . $e->getMessage());
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
     * Analyze signals and create order if confluence exists
     * 
     * NEW LOGIC - 4 RULES:
     * Rule 1: FUT-Bullish + CE-Bullish + PE-Bearish = BUY CE
     * Rule 2: FUT-Bearish + CE-Bearish + PE-Bullish = BUY PE  
     * Rule 3: FUT-Bearish + CE-Bearish + PE-Bearish = BUY PE
     * Rule 4: FUT-Bullish + CE-Bullish + PE-Bullish = BUY CE + BUY PE
     */
    private function analyzeAndCreateOrder(OIIVAutoConfig $config, $futRecord, BrokerApi $broker, string $date)
    {
        try {
            $symbol = $futRecord->underlying_symbol;
            
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [ANALYZE] {$symbol}");
            
            // Get CE and PE data
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
                Log::debug("   ⚠️  Missing CE/PE data for {$symbol}");
                return;
            }

            $futDir = $futRecord->direction;
            $ceDir = $ceData->direction;
            $peDir = $peData->direction;

            Log::info("   FUT OI: {$futDir} ({$futRecord->strength})");
            Log::info("   CE OI: {$ceDir} ({$ceData->strength})");
            Log::info("   PE OI: {$peDir} ({$peData->strength})");

            // ✅ RULE 1: FUT-Bullish + CE-Bullish + PE-Bearish = BUY CE
            if ($futDir === 'BULLISH' && $ceDir === 'BULLISH' && $peDir === 'BEARISH') {
                Log::info("   ✅ RULE 1 MATCHED: FUT-BULLISH + CE-BULLISH + PE-BEARISH → BUY CE");
                $this->createOrder(
                    $config, $futRecord, $ceData, $peData, $broker, 'CE',
                    ['signal_type' => 'BUY_CE', 'reason' => 'Rule 1: FUT-Bullish + CE-Bullish + PE-Bearish']
                );
                return;
            }

            // ✅ RULE 2: FUT-Bearish + CE-Bearish + PE-Bullish = BUY PE
            if ($futDir === 'BEARISH' && $ceDir === 'BEARISH' && $peDir === 'BULLISH') {
                Log::info("   ✅ RULE 2 MATCHED: FUT-BEARISH + CE-BEARISH + PE-BULLISH → BUY PE");
                $this->createOrder(
                    $config, $futRecord, $ceData, $peData, $broker, 'PE',
                    ['signal_type' => 'BUY_PE', 'reason' => 'Rule 2: FUT-Bearish + CE-Bearish + PE-Bullish']
                );
                return;
            }

            // ✅ RULE 3: FUT-Bearish + CE-Bearish + PE-Bearish = BUY PE
            if ($futDir === 'BEARISH' && $ceDir === 'BEARISH' && $peDir === 'BEARISH') {
                Log::info("   ✅ RULE 3 MATCHED: FUT-BEARISH + CE-BEARISH + PE-BEARISH → BUY PE");
                $this->createOrder(
                    $config, $futRecord, $ceData, $peData, $broker, 'PE',
                    ['signal_type' => 'BUY_PE', 'reason' => 'Rule 3: FUT-Bearish + CE-Bearish + PE-Bearish']
                );
                return;
            }

            // ✅ RULE 4: FUT-Bullish + CE-Bullish + PE-Bullish = BUY CE + BUY PE (BOTH)
            if ($futDir === 'BULLISH' && $ceDir === 'BULLISH' && $peDir === 'BULLISH') {
                Log::info("   ✅ RULE 4 MATCHED: FUT-BULLISH + CE-BULLISH + PE-BULLISH → BUY CE + BUY PE");
                
                // Create CE order
                $this->createOrder(
                    $config, $futRecord, $ceData, $peData, $broker, 'CE',
                    ['signal_type' => 'BUY_CE', 'reason' => 'Rule 4: FUT-Bullish + CE-Bullish + PE-Bullish (Triple Bullish)']
                );
                
                // Create PE order
                $this->createOrder(
                    $config, $futRecord, $ceData, $peData, $broker, 'PE',
                    ['signal_type' => 'BUY_PE', 'reason' => 'Rule 4: FUT-Bullish + CE-Bullish + PE-Bullish (Triple Bullish)']
                );
                return;
            }

            // ❌ NO RULE MATCHED
            Log::debug("   ❌ No matching rule for {$symbol}: FUT={$futDir}, CE={$ceDir}, PE={$peDir}");

        } catch (\Exception $e) {
            Log::error("❌ [ANALYZE] Error: " . $e->getMessage());
        }
    }

    /**
     * Create order entry
     */
    // private function createOrder(
    //     OIIVAutoConfig $config,
    //     $futRecord,
    //     $ceData,
    //     $peData,
    //     BrokerApi $broker,
    //     string $optionType,
    //     array $signal
    // ) {
    //     try {
    //         // Check if already processed
    //         $existing = OIIVAutoOrder::where('broker_api_id', $broker->id)
    //             ->where('symbol', $futRecord->underlying_symbol)
    //             ->whereDate('signal_detected_at', $futRecord->trading_date)
    //             ->where('option_type', $optionType)
    //             ->first();

    //         if ($existing) {
    //             Log::info("   ⚠️  {$optionType} order already exists");
    //             return;
    //         }

    //         Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    //         Log::info("✅ [CREATE] Creating {$optionType} order for: {$futRecord->underlying_symbol}");
            
    //         // Get option details
    //         $optionDetails = $this->getATMOption($broker, $futRecord->trading_symbol, $optionType, $futRecord->spot_price, $config);
            
    //         if (!$optionDetails) {
    //             Log::error("❌ [CREATE] Could not find ATM option");
    //             return;
    //         }

    //         $quantity = $config->getQuantityForSymbol($futRecord->trading_symbol);

    //         $orderData = [
    //             'user_id' => $config->user_id,
    //             'config_id' => $config->id,
    //             'broker_api_id' => $broker->id,
    //             'symbol' => $futRecord->underlying_symbol,
    //             'trading_symbol' => $futRecord->trading_symbol,
    //             'instrument_token' => $futRecord->instrument_token,
    //             'btst_signal' => $signal['signal_type'],
    //             'btst_confidence' => 100,
    //             'btst_reason' => $signal['reason'],
    //             'signal_detected_at' => now(),
    //             'fut_oi_signal' => $futRecord->direction,
    //             'fut_oi_strength' => $futRecord->strength,
    //             'ce_oi_signal' => $ceData->direction,
    //             'pe_oi_signal' => $peData->direction,
    //             'ce_iv_signal' => $ceData->iv_direction ?? 'N/A',
    //             'ce_iv_strength' => $ceData->iv_strength ?? 'N/A',
    //             'pe_iv_signal' => $peData->iv_direction ?? 'N/A',
    //             'pe_iv_strength' => $peData->iv_strength ?? 'N/A',
    //             'spot_price' => $futRecord->spot_price,
    //             'option_symbol' => $optionDetails['symbol'],
    //             'option_token' => $optionDetails['token'],
    //             'option_type' => $optionType,
    //             'strike_price' => $optionDetails['strike'],
    //             'entry_price' => $optionDetails['ltp'],
    //             'current_price' => $optionDetails['ltp'],
    //             'order_type' => $config->order_type,
    //             'product' => $config->product,
    //             'quantity' => $quantity,
    //             'is_order_placed' => false,
    //             'status' => true
    //         ];

    //         $order = OIIVAutoOrder::create($orderData);
            
    //         Log::info("✅ [CREATE] Order created successfully!");
    //         Log::info("   Order ID: {$order->id}");
    //         Log::info("   Signal: {$signal['signal_type']}");
    //         Log::info("   Reason: {$signal['reason']}");
    //         Log::info("   Option: {$optionDetails['symbol']}");
    //         Log::info("   Strike: {$optionDetails['strike']}");
    //         Log::info("   LTP: ₹{$optionDetails['ltp']}");
    //         Log::info("   Quantity: {$quantity} lots");

    //     } catch (\Exception $e) {
    //         Log::error("❌ [CREATE] Error: " . $e->getMessage());
    //         Log::error("   Trace: " . $e->getTraceAsString());
    //     }
    // }

    private function createOrder(
        OIIVAutoConfig $config,
        $futRecord,
        $ceData,
        $peData,
        BrokerApi $broker,
        string $optionType,
        array $signal
    ) {
        try {
            // ✅ Step 1: Check if order already exists in oiiv_auto_orders
            $existing = OIIVAutoOrder::where('broker_api_id', $broker->id)
                ->where('symbol', $futRecord->underlying_symbol)
                ->whereDate('signal_detected_at', $futRecord->trading_date)
                ->where('option_type', $optionType)
                ->where('status', true)
                ->first();

            if ($existing) {
                Log::info("   ⚠️  {$optionType} order already exists - ID: {$existing->id}");
                return;
            }

            // Get option details FIRST (we need the exact option symbol)
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("✅ [CREATE] Creating {$optionType} order for: {$futRecord->underlying_symbol}");
            
            $optionDetails = $this->getATMOption($broker, $futRecord->trading_symbol, $optionType, $futRecord->spot_price, $config);
            
            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find ATM option");
                return;
            }

            // ✅ Step 2: Check if this EXACT option is already in order_book
            $orderBookExists = OrderBook::where('user_id', $config->user_id)
                ->where('trading_symbol', $optionDetails['symbol'])  // ✅ EXACT match!
                ->whereDate('order_datetime', $futRecord->trading_date)
                ->where('transaction_type', 'BUY')
                ->where('status', '!=', 'FAILED')
                ->exists();

            if ($orderBookExists) {
                Log::info("   ⚠️  {$optionType} order already placed in OrderBook for {$optionDetails['symbol']}");
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
                'btst_signal' => $signal['signal_type'],
                'btst_confidence' => 100,
                'btst_reason' => $signal['reason'],
                'signal_detected_at' => now(),
                'fut_oi_signal' => $futRecord->direction,
                'fut_oi_strength' => $futRecord->strength,
                'ce_oi_signal' => $ceData->direction,
                'pe_oi_signal' => $peData->direction,
                'ce_iv_signal' => $ceData->iv_direction ?? 'N/A',
                'ce_iv_strength' => $ceData->iv_strength ?? 'N/A',
                'pe_iv_signal' => $peData->iv_direction ?? 'N/A',
                'pe_iv_strength' => $peData->iv_strength ?? 'N/A',
                'spot_price' => $futRecord->spot_price,
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
            Log::info("   Signal: {$signal['signal_type']}");
            Log::info("   Reason: {$signal['reason']}");
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
     * Place pending orders (unchanged)
     */
    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== Starting OI+IV Order Placement ===');

            $pendingOrders = OIIVAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->whereHas('config', function($query) {
                    $query->where('status', true);
                })
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('No pending OI+IV orders to place');
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