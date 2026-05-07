<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\PortfolioSellOrderConfig;
use App\Models\OrderBook;
use App\Models\ZerodhaInstrument;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TestNormalSellOrder extends Command
{
    protected $signature = 'positions:test-sell-order 
                            {broker_id : Broker ID to test}
                            {symbol : Trading symbol to test}
                            {--dry-run : Simulate without placing order}';

    protected $description = 'Test normal sell order for a single position with symbol-specific config';

    private $rateLimitSeconds = 1;

    // ✅ FREEZE LIMITS IN LOTS (same as BUY logic)
    const FREEZE_LIMITS = [
        'NIFTY' => 36,
        'BANKNIFTY' => 18,
        'FINNIFTY' => 36,
        'MIDCPNIFTY' => 28,
        // Add more as needed
    ];

    public function handle()
    {
        $brokerId = $this->argument('broker_id');
        $symbol = $this->argument('symbol');
        
        $this->info("🧪 Testing Normal Sell Order");
        $this->info("Broker ID: {$brokerId}");
        $this->info("Symbol: {$symbol}");
        $this->info(str_repeat('=', 60));

        // Check market hours
        $currentTime = now()->format('H:i');
        if (!$this->option('dry-run') && ($currentTime < '09:15' || $currentTime > '15:30')) {
            $this->warn('⚠️  Outside market hours (9:15 AM - 3:30 PM)');
            $this->warn('   Current time: ' . now()->format('H:i:s'));
            if (!$this->confirm('Continue anyway?')) {
                return Command::FAILURE;
            }
        }

        try {
            // Get broker
            $broker = BrokerApi::where('id', $brokerId)
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->first();

            if (!$broker) {
                $this->error('❌ Broker not found or token invalid!');
                return Command::FAILURE;
            }

            $this->info("✅ Broker: {$broker->client_name} ({$broker->account_user_name})");

            // Get position
            $position = PortfolioPosition::where('broker_api_id', $brokerId)
                ->where('tradingsymbol', $symbol)
                ->where('position_status', 'open')
                ->where('quantity', '>', 0)
                ->first();

            if (!$position) {
                $this->error("❌ Position not found or not a BUY position: {$symbol}");
                return Command::FAILURE;
            }

            // Extract symbol type
            $symbolType = PortfolioSellOrderConfig::extractSymbolType($position->tradingsymbol);
            
            $this->info("\n📊 Position Details:");
            $this->info("  Symbol: {$position->tradingsymbol}");
            $this->info("  Symbol Type: {$symbolType}");
            $this->info("  Exchange: {$position->exchange}");
            $this->info("  Product: {$position->product}");
            $this->info("  Quantity: {$position->quantity}");
            $this->info("  Avg Price: ₹{$position->average_price}");
            $this->info("  Last Price: ₹{$position->last_price}");
            $this->info("  P&L: ₹{$position->pnl}");

            // Get config
            $config = PortfolioSellOrderConfig::getForSymbolType($broker->user_id, $symbolType);
            
            if (!$config) {
                $this->error("\n❌ No active configuration found for symbol type: {$symbolType}");
                return Command::FAILURE;
            }

            // Calculate target
            $purchaseDate = Carbon::parse($position->purchase_date);
            $isOld = !$purchaseDate->isToday();
            
            $profitPercent = $isOld 
                ? $config->old_position_profit_percent 
                : $config->fresh_position_profit_percent;
            
            $targetPrice = round($position->average_price * (1 + ($profitPercent / 100)), 2);
            
            $this->info("\n💰 Target:");
            $this->info("  Profit %: {$profitPercent}%");
            $this->info("  Target Price: ₹{$targetPrice}");

            // ✅ Get lot size from database
            $instrument = $this->getInstrumentDetails($position->tradingsymbol);
            
            if (!$instrument) {
                $this->error("❌ Could not find instrument details");
                return Command::FAILURE;
            }

            $totalQuantity = abs($position->quantity);
            $lotSize = $instrument->lot_size;
            $totalLots = $totalQuantity / $lotSize;

            $this->info("\n📦 Order Details:");
            $this->info("  Total Quantity: {$totalQuantity}");
            $this->info("  Lot Size: {$lotSize}");
            $this->info("  Total Lots: {$totalLots}");

            // ✅ Check freeze limit
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $position->tradingsymbol);
            $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

            if ($freezeLimitLots && $totalLots > $freezeLimitLots) {
                $numOrders = ceil($totalLots / $freezeLimitLots);
                $this->warn("  ⚠️  Freeze Limit: {$freezeLimitLots} lots");
                $this->warn("  ⚠️  Will split into {$numOrders} orders");
                
                // Show split
                $remainingLots = $totalLots;
                for ($i = 1; $i <= min($numOrders, 3); $i++) {
                    $lotsInOrder = min($freezeLimitLots, $remainingLots);
                    $qtyInOrder = $lotsInOrder * $lotSize;
                    $this->line("     Order {$i}: {$lotsInOrder} lots ({$qtyInOrder} qty)");
                    $remainingLots -= $lotsInOrder;
                }
                if ($numOrders > 3) {
                    $this->line("     ... and " . ($numOrders - 3) . " more orders");
                }
            } else {
                $this->info("  ✅ Single order (within freeze limit)");
            }

            // Confirm
            if (!$this->option('dry-run')) {
                $this->newLine();
                if (!$this->confirm("✅ Place SELL order(s)?")) {
                    $this->warn('❌ Cancelled by user');
                    return Command::SUCCESS;
                }
            }

            // Initialize Kite
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Place sell order
            $result = $this->placeSellOrder($kite, $broker, $position, $profitPercent, $targetPrice, $symbolType, $instrument);

            if ($result['success']) {
                $this->info("\n✅ SUCCESS!");
                $this->info("Orders placed: {$result['orders_placed']}");
            } else {
                $this->error("\n❌ FAILED!");
                $this->error("Error: " . ($result['error'] ?? 'Unknown error'));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("Test sell order error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * ✅ Get instrument details from database
     */
    private function getInstrumentDetails($tradingSymbol)
    {
        try {
            // Try exact match first
            $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                ->where('exchange', 'NFO')
                ->first();
            
            if ($instrument) {
                Log::info("Found instrument by exact match: {$tradingSymbol}");
                return $instrument;
            }

            // Extract base symbol and try to find any option
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $tradingSymbol);
            
            $instrument = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('expiry', '>=', now())
                ->orderBy('expiry', 'ASC')
                ->first();
            
            if ($instrument) {
                Log::info("Found instrument by base symbol: {$baseSymbol}, lot_size: {$instrument->lot_size}");
                return $instrument;
            }
            
            Log::error("Instrument not found for: {$tradingSymbol}");
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error getting instrument: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Place sell order with freeze limit handling (same as BUY logic)
     */
    private function placeSellOrder($kite, $broker, $position, $profitPercent, $targetPrice, $symbolType, $instrument)
    {
        try {
            if ($this->option('dry-run')) {
                $this->warn("\n[DRY-RUN] Would place SELL orders");
                return ['success' => true, 'dry_run' => true, 'orders_placed' => 1];
            }

            $totalQuantity = abs($position->quantity);
            $lotSize = $instrument->lot_size;
            $totalLots = $totalQuantity / $lotSize;

            // Extract base symbol for freeze check
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $position->tradingsymbol);
            $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

            $placedOrders = 0;
            $failedOrders = 0;
            $errors = [];

            $this->info("\n📤 Placing SELL Orders...");
            Log::info("Total lots: {$totalLots}, Freeze limit: " . ($freezeLimitLots ?? 'none'));

            // ✅ Check if we need to split
            if ($freezeLimitLots && $totalLots > $freezeLimitLots) {
                // Split into multiple orders
                $this->info("  🔄 Splitting due to freeze limit");
                
                $numOrders = ceil($totalLots / $freezeLimitLots);
                $remainingLots = $totalLots;

                for ($i = 0; $i < $numOrders; $i++) {
                    $lotsToPlace = min($freezeLimitLots, $remainingLots);
                    $qtyToPlace = $lotsToPlace * $lotSize;
                    
                    $result = $this->executeSingleOrder(
                        $kite, 
                        $broker, 
                        $position, 
                        $qtyToPlace, 
                        $targetPrice, 
                        $symbolType, 
                        $profitPercent,
                        ($i + 1),
                        $numOrders
                    );
                    
                    if ($result) {
                        $placedOrders++;
                    } else {
                        $failedOrders++;
                    }
                    
                    $remainingLots -= $lotsToPlace;
                    
                    if ($i < $numOrders - 1) {
                        $this->line("  ⏱️  Waiting 2s before next order...");
                        sleep(2);
                    }
                }
            } else {
                // Single order - within freeze limit
                $this->info("  ✅ Single order (within freeze limit)");
                
                $result = $this->executeSingleOrder(
                    $kite, 
                    $broker, 
                    $position, 
                    $totalQuantity, 
                    $targetPrice, 
                    $symbolType, 
                    $profitPercent,
                    1,
                    1
                );
                
                if ($result) {
                    $placedOrders++;
                } else {
                    $failedOrders++;
                }
            }

            Log::info("Order placement complete. Placed: {$placedOrders}, Failed: {$failedOrders}");

            if ($placedOrders > 0) {
                $position->update([
                    'target_profit_percent' => $profitPercent,
                    'target_sell_price' => $targetPrice,
                    'square_off_status' => 'sell_placed',
                ]);
            }

            return [
                'success' => $placedOrders > 0, 
                'orders_placed' => $placedOrders,
                'failed_orders' => $failedOrders
            ];

        } catch (\Exception $e) {
            Log::error("SELL Orders Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'orders_placed' => 0];
        }
    }

    /**
     * ✅ Execute a single sell order
     */
    private function executeSingleOrder($kite, $broker, $position, $quantity, $targetPrice, $symbolType, $profitPercent, $orderNum, $totalOrders)
    {
        try {
            $orderParams = [
                'exchange' => $position->exchange,
                'tradingsymbol' => $position->tradingsymbol,
                'transaction_type' => 'SELL',
                'quantity' => $quantity,
                'product' => $position->product,
                'order_type' => 'LIMIT',
                'price' => $targetPrice,
                'validity' => 'DAY',
            ];

            Log::info("Order {$orderNum}/{$totalOrders} params: " . json_encode($orderParams));

            $result = $kite->placeOrder("regular", $orderParams);
            
            Log::info("Order {$orderNum} API response: " . json_encode($result));

            if (isset($result->order_id)) {
                $this->info("  ✅ [{$orderNum}/{$totalOrders}] Order: {$result->order_id} ({$quantity} qty @ ₹{$targetPrice})");
                
                $this->saveOrderToDatabase($broker, $result->order_id, $orderParams, $symbolType, $profitPercent);
                
                return true;
            } else {
                $this->error("  ❌ [{$orderNum}/{$totalOrders}] No order_id in response");
                Log::error("Order {$orderNum} failed - no order_id");
                return false;
            }

        } catch (\Exception $e) {
            $error = $e->getMessage();
            $this->error("  ❌ [{$orderNum}/{$totalOrders}] Failed: {$error}");
            Log::error("Order {$orderNum} exception: {$error}");
            
            // Save failed order
            try {
                OrderBook::create([
                    'user_id' => $broker->user_id,
                    'broker_username' => $broker->account_user_name,
                    'order_id' => '-',
                    'status' => 'FAILED',
                    'trading_symbol' => $position->tradingsymbol,
                    'order_type' => 'LIMIT',
                    'transaction_type' => 'SELL',
                    'product' => $position->product,
                    'price' => $targetPrice,
                    'quantity' => $quantity,
                    'status_message' => "{$symbolType} SELL FAILED: " . substr($error, 0, 400),
                    'order_datetime' => now(),
                ]);
            } catch (\Exception $dbEx) {
                Log::error("Failed to save failed order to DB: " . $dbEx->getMessage());
            }
            
            return false;
        }
    }

    private function saveOrderToDatabase($broker, $orderId, $orderParams, $symbolType, $profitPercent)
    {
        try {
            OrderBook::create([
                'user_id' => $broker->user_id,
                'broker_username' => $broker->account_user_name,
                'order_id' => $orderId,
                'status' => 'PENDING',
                'trading_symbol' => $orderParams['tradingsymbol'],
                'order_type' => $orderParams['order_type'],
                'transaction_type' => $orderParams['transaction_type'],
                'product' => $orderParams['product'],
                'price' => $orderParams['price'] ?? '-',
                'quantity' => $orderParams['quantity'],
                'status_message' => "{$symbolType} SELL ({$profitPercent}% target)",
                'order_datetime' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving order: " . $e->getMessage());
        }
    }
}