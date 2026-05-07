<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\PortfolioConfig;
use App\Models\OrderBook;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TestSquareOffSingle extends Command
{
    protected $signature = 'positions:test-square-off 
                            {broker_id : Broker ID to test}
                            {symbol : Trading symbol to test}
                            {--dry-run : Simulate without placing order}';

    protected $description = 'Test square-off for a single symbol of a specific broker';

    // Hardcoded defaults
    private $chunkSize = 20;
    private $rateLimitSeconds = 1;

    public function handle()
    {
        $brokerId = $this->argument('broker_id');
        $symbol = $this->argument('symbol');
        
        $this->info("🧪 Testing Square-Off");
        $this->info("Broker ID: {$brokerId}");
        $this->info("Symbol: {$symbol}");
        $this->info(str_repeat('=', 60));

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
                ->first();

            if (!$position) {
                $this->error("❌ Position not found for symbol: {$symbol}");
                $this->warn("Available symbols for this broker:");
                
                $available = PortfolioPosition::where('broker_api_id', $brokerId)
                    ->where('position_status', 'open')
                    ->pluck('tradingsymbol');
                
                foreach ($available as $sym) {
                    $this->line("  • {$sym}");
                }
                
                return Command::FAILURE;
            }

            $this->info("\n📊 Position Details:");
            $this->info("  Symbol: {$position->tradingsymbol}");
            $this->info("  Exchange: {$position->exchange}");
            $this->info("  Product: {$position->product}");
            $this->info("  Quantity: {$position->quantity}");
            $this->info("  Avg Price: ₹{$position->average_price}");
            $this->info("  Last Price: ₹{$position->last_price}");
            $this->info("  P&L: ₹{$position->pnl}");
            $this->info("  Purchase Date: {$position->purchase_date}");

            // Get config
            $config = PortfolioConfig::getForUser($broker->user_id);
            
            // Classify position
            $purchaseDate = Carbon::parse($position->purchase_date);
            $isOld = !$purchaseDate->isToday();
            
            $profitPercent = $isOld ? $config->old_position_profit_percent : $config->fresh_position_profit_percent;
            $positionType = $isOld ? 'OLD (before today)' : 'TODAY';
            
            $this->info("\n⚙️  Configuration:");
            $this->info("  Position Type: {$positionType}");
            $this->info("  Profit Target: {$profitPercent}%");
            $this->info("  Order Chunk Size: {$this->chunkSize}");

            // Calculate target
            $targetPrice = round($position->average_price * (1 + ($profitPercent / 100)), 2);
            $potentialProfit = ($targetPrice - $position->average_price) * abs($position->quantity);
            
            $this->info("\n💰 Target Calculation:");
            $this->info("  Target Price: ₹{$targetPrice}");
            $this->info("  Potential Profit: ₹" . number_format($potentialProfit, 2));

            // Confirm
            if (!$this->option('dry-run')) {
                if (!$this->confirm("\nDo you want to place the order?")) {
                    $this->warn('❌ Cancelled by user');
                    return Command::SUCCESS;
                }
            }

            // Initialize Kite
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Place order
            if ($isOld) {
                $result = $this->placeAMOOrder($kite, $broker, $position, $profitPercent, $targetPrice);
            } else {
                $result = $this->placeSellOrder($kite, $broker, $position, $profitPercent, $targetPrice);
            }

            if ($result['success']) {
                $this->info("\n✅ SUCCESS!");
                if ($this->option('dry-run')) {
                    $this->warn("(DRY-RUN MODE - No actual order placed)");
                } else {
                    $this->info("Orders placed successfully!");
                }
            } else {
                $this->error("\n❌ FAILED!");
                $this->error("Error: " . ($result['error'] ?? 'Unknown error'));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("Test square-off error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function placeAMOOrder($kite, $broker, $position, $profitPercent, $targetPrice)
    {
        try {
            if ($this->option('dry-run')) {
                $this->warn("\n[DRY-RUN] Would place AMO order:");
                $this->line("  Type: AMO (After Market Order)");
                $this->line("  Symbol: {$position->tradingsymbol}");
                $this->line("  Price: ₹{$targetPrice}");
                $this->line("  Quantity: " . abs($position->quantity));
                return ['success' => true, 'dry_run' => true];
            }

            $transactionType = $position->quantity > 0 ? 'SELL' : 'BUY';
            $quantity = abs($position->quantity);

            $orderParams = [
                'exchange' => $position->exchange,
                'tradingsymbol' => $position->tradingsymbol,
                'transaction_type' => $transactionType,
                'quantity' => $quantity,
                'product' => $position->product,
                'order_type' => 'LIMIT',
                'price' => $targetPrice,
                'validity' => 'DAY',
                'variety' => 'amo',
            ];

            $this->info("\n📤 Placing AMO Order...");
            $result = $kite->placeOrder("amo", $orderParams);

            if (isset($result->order_id)) {
                $this->info("✅ AMO Order ID: {$result->order_id}");
                
                $this->saveOrderToDatabase($broker, $result->order_id, $orderParams, 'AMO', $profitPercent);
                
                $position->update([
                    'target_profit_percent' => $profitPercent,
                    'target_sell_price' => $targetPrice,
                    'square_off_order_id' => $result->order_id,
                    'square_off_status' => 'placed',
                ]);
                
                return ['success' => true, 'order_id' => $result->order_id];
            }

            throw new \Exception('No order ID received');

        } catch (\Exception $e) {
            $this->error("AMO Order Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function placeSellOrder($kite, $broker, $position, $profitPercent, $targetPrice)
    {
        try {
            $transactionType = $position->quantity > 0 ? 'SELL' : 'BUY';
            $totalQuantity = abs($position->quantity);

            if ($this->option('dry-run')) {
                $this->warn("\n[DRY-RUN] Would place SELL orders:");
                $this->line("  Type: SELL (Regular)");
                $this->line("  Symbol: {$position->tradingsymbol}");
                $this->line("  Price: ₹{$targetPrice}");
                $this->line("  Total Quantity: {$totalQuantity}");
                $this->line("  Split into: " . ceil($totalQuantity / $this->chunkSize) . " orders of {$this->chunkSize} qty");
                return ['success' => true, 'dry_run' => true, 'orders_placed' => 1];
            }

            $numOrders = ceil($totalQuantity / $this->chunkSize);
            $remainingQty = $totalQuantity;
            $placedOrders = 0;

            $this->info("\n📤 Placing SELL Orders (Split into {$numOrders} orders)...");

            for ($i = 0; $i < $numOrders; $i++) {
                $qtyToPlace = min($this->chunkSize, $remainingQty);

                $orderParams = [
                    'exchange' => $position->exchange,
                    'tradingsymbol' => $position->tradingsymbol,
                    'transaction_type' => $transactionType,
                    'quantity' => $qtyToPlace,
                    'product' => $position->product,
                    'order_type' => 'LIMIT',
                    'price' => $targetPrice,
                    'validity' => 'DAY',
                ];

                try {
                    $result = $kite->placeOrder("regular", $orderParams);

                    if (isset($result->order_id)) {
                        $placedOrders++;
                        $this->info("  ✅ [{$placedOrders}/{$numOrders}] Order ID: {$result->order_id} ({$qtyToPlace} qty)");
                        
                        $this->saveOrderToDatabase($broker, $result->order_id, $orderParams, 'SELL', $profitPercent);
                    }

                } catch (\Exception $e) {
                    // $this->error("  ❌ [{$i+1}/{$numOrders}] Failed: " . $e->getMessage());
                }

                $remainingQty -= $qtyToPlace;
                
                if ($i < $numOrders - 1) {
                    sleep($this->rateLimitSeconds);
                }
            }

            if ($placedOrders > 0) {
                $position->update([
                    'target_profit_percent' => $profitPercent,
                    'target_sell_price' => $targetPrice,
                    'square_off_status' => 'placed',
                ]);
            }

            return ['success' => $placedOrders > 0, 'orders_placed' => $placedOrders];

        } catch (\Exception $e) {
            $this->error("SELL Orders Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'orders_placed' => 0];
        }
    }

    private function saveOrderToDatabase($broker, $orderId, $orderParams, $orderCategory, $profitPercent)
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
                'status_message' => "{$orderCategory} test square-off ({$profitPercent}% profit)",
                'order_datetime' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving order: " . $e->getMessage());
        }
    }
}