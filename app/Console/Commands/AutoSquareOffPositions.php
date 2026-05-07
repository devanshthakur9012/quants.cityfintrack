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

class AutoSquareOffPositions extends Command
{
    protected $signature = 'positions:auto-square-off 
                            {--broker_id= : Specific broker ID}
                            {--all : Process all active brokers}
                            {--dry-run : Simulate without placing orders}';

    protected $description = 'Auto square-off positions with profit targets from database';

    private $chunkSize = 20; // Always split into 20 qty chunks
    private $rateLimitSeconds = 1; // Always wait 1 second between orders
    private $brokerRateLimitSeconds = 2; // Always wait 2 seconds between brokers

    public function handle()
    {
        $this->info('🚀 Starting auto square-off at ' . now()->format('H:i:s'));
        
        if (!$this->option('dry-run') && now()->format('H:i') > '15:30') {
            $this->warn('⚠️  This command should run before 3:30 PM. Current time: ' . now()->format('H:i:s'));
            if (!$this->confirm('Do you want to continue anyway?')) {
                return Command::FAILURE;
            }
        }

        try {
            $brokers = $this->getBrokers();
            
            if ($brokers->isEmpty()) {
                $this->warn('⚠️  No active brokers found!');
                return Command::FAILURE;
            }

            $stats = [
                'total_positions' => 0,
                'old_positions' => 0,
                'today_positions' => 0,
                'amo_orders' => 0,
                'sell_orders' => 0,
                'failed_orders' => 0,
            ];

            foreach ($brokers as $broker) {
                $this->info("\n📊 Processing broker: {$broker->client_name} ({$broker->account_user_name})");
                
                $brokerStats = $this->processBrokerPositions($broker);
                
                $stats['total_positions'] += $brokerStats['total'];
                $stats['old_positions'] += $brokerStats['old'];
                $stats['today_positions'] += $brokerStats['today'];
                $stats['amo_orders'] += $brokerStats['amo_placed'];
                $stats['sell_orders'] += $brokerStats['sell_placed'];
                $stats['failed_orders'] += $brokerStats['failed'];
                
                sleep($this->brokerRateLimitSeconds);
            }

            $this->displaySummary($stats);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('AutoSquareOffPositions error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getBrokers()
    {
        if ($this->option('broker_id')) {
            return BrokerApi::where('id', $this->option('broker_id'))
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->get();
        }

        return BrokerApi::where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();
    }

    private function processBrokerPositions($broker)
    {
        $stats = [
            'total' => 0,
            'old' => 0,
            'today' => 0,
            'amo_placed' => 0,
            'sell_placed' => 0,
            'failed' => 0,
        ];

        try {
            // Get ONLY profit % from database
            $config = PortfolioConfig::getForUser($broker->user_id);
            
            $this->info("  ⚙️  Config: Old={$config->old_position_profit_percent}%, Fresh={$config->fresh_position_profit_percent}%, Chunk={$this->chunkSize} (fixed)");

            $positions = PortfolioPosition::where('user_id', $broker->user_id)
                ->where('broker_api_id', $broker->id)
                ->where('position_status', 'open')
                ->whereNotNull('purchase_date')
                ->get();
            
            if ($positions->isEmpty()) {
                $this->warn("  ⚠️  No open positions found");
                return $stats;
            }

            $stats['total'] = $positions->count();
            $classified = $this->classifyPositions($positions);
            
            $stats['old'] = count($classified['old']);
            $stats['today'] = count($classified['today']);

            $this->info("  📈 Total: {$stats['total']} | Old (before today): {$stats['old']} | Today: {$stats['today']}");

            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Process OLD positions - AMO orders
            if (!empty($classified['old'])) {
                $this->info("\n  🌙 Processing OLD positions - AMO orders with {$config->old_position_profit_percent}% profit...");
                foreach ($classified['old'] as $position) {
                    $result = $this->placeAMOOrder($kite, $broker, $position, $config->old_position_profit_percent);
                    if ($result['success']) {
                        $stats['amo_placed']++;
                    } else {
                        $stats['failed']++;
                    }
                    sleep($this->rateLimitSeconds);
                }
            }

            // Process TODAY's positions - SELL orders
            if (!empty($classified['today'])) {
                $this->info("\n  ☀️  Processing TODAY's positions - SELL orders with {$config->fresh_position_profit_percent}% profit...");
                foreach ($classified['today'] as $position) {
                    $result = $this->placeSellOrder($kite, $broker, $position, $config->fresh_position_profit_percent);
                    if ($result['success']) {
                        $stats['sell_placed'] += $result['orders_placed'];
                    } else {
                        $stats['failed']++;
                    }
                    sleep($this->rateLimitSeconds);
                }
            }

        } catch (\Exception $e) {
            $this->error("  ❌ Error processing broker: " . $e->getMessage());
            Log::error("Error processing broker {$broker->id}: " . $e->getMessage());
        }

        return $stats;
    }

    private function classifyPositions($positions)
    {
        $classified = ['today' => [], 'old' => []];
        $today = Carbon::today();

        foreach ($positions as $position) {
            $purchaseDate = Carbon::parse($position->purchase_date);
            
            if ($purchaseDate->isSameDay($today)) {
                $classified['today'][] = $position;
            } else {
                $classified['old'][] = $position;
            }
        }

        return $classified;
    }

    private function placeAMOOrder($kite, $broker, $position, $profitPercent)
    {
        try {
            if ($this->option('dry-run')) {
                $this->info("    [DRY-RUN] Would place AMO for {$position->tradingsymbol}");
                return ['success' => true, 'dry_run' => true];
            }

            $avgPrice = $position->average_price;
            $targetPrice = round($avgPrice * (1 + ($profitPercent / 100)), 2);

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

            $result = $kite->placeOrder("amo", $orderParams);

            if (isset($result->order_id)) {
                $this->info("    ✅ AMO placed: {$position->tradingsymbol} @ ₹{$targetPrice} (Order: {$result->order_id})");
                
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
            $this->error("    ❌ AMO failed for {$position->tradingsymbol}: " . $e->getMessage());
            Log::error("AMO order failed: " . $e->getMessage());
            
            $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'AMO');
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function placeSellOrder($kite, $broker, $position, $profitPercent)
    {
        try {
            $avgPrice = $position->average_price;
            $targetPrice = round($avgPrice * (1 + ($profitPercent / 100)), 2);

            $transactionType = $position->quantity > 0 ? 'SELL' : 'BUY';
            $totalQuantity = abs($position->quantity);

            if ($this->option('dry-run')) {
                $this->info("    [DRY-RUN] Would place SELL for {$position->tradingsymbol} @ ₹{$targetPrice}");
                return ['success' => true, 'dry_run' => true, 'orders_placed' => 1];
            }

            $numOrders = ceil($totalQuantity / $this->chunkSize);
            $remainingQty = $totalQuantity;
            $placedOrders = 0;

            $this->info("    📦 Splitting {$totalQuantity} qty into {$numOrders} orders of {$this->chunkSize} qty");

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
                        $this->saveOrderToDatabase($broker, $result->order_id, $orderParams, 'SELL', $profitPercent);
                    }
                    
                } catch (\Exception $e) {
                    $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'SELL');
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
            $this->error("    ❌ SELL failed for {$position->tradingsymbol}: " . $e->getMessage());
            Log::error("SELL order failed: " . $e->getMessage());
            
            $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'SELL');
            
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
                'status_message' => "{$orderCategory} auto square-off ({$profitPercent}% profit)",
                'order_datetime' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving order to database: " . $e->getMessage());
        }
    }

    private function saveFailedOrderToDatabase($broker, $position, $error, $orderCategory)
    {
        try {
            OrderBook::create([
                'user_id' => $broker->user_id,
                'broker_username' => $broker->account_user_name,
                'order_id' => '-',
                'status' => 'FAILED',
                'trading_symbol' => $position->tradingsymbol,
                'order_type' => 'LIMIT',
                'transaction_type' => $position->quantity > 0 ? 'SELL' : 'BUY',
                'product' => $position->product,
                'price' => '-',
                'quantity' => abs($position->quantity),
                'status_message' => "{$orderCategory} failed: " . substr($error, 0, 450),
                'order_datetime' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }

    private function displaySummary($stats)
    {
        $this->info("\n" . str_repeat('=', 60));
        $this->info("📊 EXECUTION SUMMARY");
        $this->info(str_repeat('=', 60));
        $this->info("Total Positions Processed: " . $stats['total_positions']);
        $this->info("  • Old Positions (before today): " . $stats['old_positions']);
        $this->info("  • Today's Positions: " . $stats['today_positions']);
        $this->info("");
        $this->info("Orders Placed:");
        $this->info("  • AMO Orders: " . $stats['amo_orders']);
        $this->info("  • SELL Orders: " . $stats['sell_orders']);
        $this->info("  • Failed Orders: " . $stats['failed_orders']);
        $this->info(str_repeat('=', 60));
    }
}