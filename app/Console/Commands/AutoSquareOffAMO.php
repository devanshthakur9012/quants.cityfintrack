<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\BrokerAmoConfig;
use App\Models\FreezingQuantity;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoSquareOffAMO extends Command
{
    protected $signature = 'positions:auto-square-off-amo 
                            {--broker_id= : Specific broker ID}
                            {--date= : Config date (default: today)}
                            {--dry-run : Simulate without placing orders}';

    protected $description = 'Place AMO orders for positions based on broker-level configurations';

    private $rateLimitSeconds = 1;
    private $brokerRateLimitSeconds = 2;

    public function handle()
    {
        $configDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        
        $this->info('🌙 Starting AMO auto square-off for ' . $configDate->format('d M Y') . ' at ' . now()->format('H:i:s'));
        
        if (!$this->option('dry-run') && now()->format('H:i') < '15:30') {
            $this->warn('⚠️  AMO orders should be placed after 3:30 PM. Current time: ' . now()->format('H:i:s'));
            
            // Only ask for confirmation if running from CLI (not web interface)
            if (defined('STDIN') && stream_isatty(STDIN)) {
                if (!$this->confirm('Do you want to continue anyway?')) {
                    return Command::FAILURE;
                }
            } else {
                // Running from web interface - skip confirmation and continue
                $this->warn('   Continuing execution from web interface...');
            }
        }

        try {
            $brokers = $this->getBrokersWithConfig($configDate);
            
            if ($brokers->isEmpty()) {
                $this->warn('⚠️  No brokers found with active AMO configurations for ' . $configDate->format('d M Y'));
                return Command::FAILURE;
            }

            $stats = [
                'total_brokers' => $brokers->count(),
                'total_positions' => 0,
                'old_positions' => 0,
                'today_positions' => 0,
                'ce_orders' => 0,
                'pe_orders' => 0,
                'failed_orders' => 0,
            ];

            foreach ($brokers as $broker) {
                $this->info("\n📊 Processing broker: {$broker->client_name} ({$broker->account_user_name})");
                
                $brokerStats = $this->processBrokerPositions($broker, $configDate);
                
                $stats['total_positions'] += $brokerStats['total'];
                $stats['old_positions'] += $brokerStats['old'];
                $stats['today_positions'] += $brokerStats['today'];
                $stats['ce_orders'] += $brokerStats['ce_placed'];
                $stats['pe_orders'] += $brokerStats['pe_placed'];
                $stats['failed_orders'] += $brokerStats['failed'];
                
                sleep($this->brokerRateLimitSeconds);
            }

            $this->displaySummary($stats, $configDate);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('AutoSquareOffAMO error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getBrokersWithConfig($configDate)
    {
        $query = BrokerApi::whereHas('amoConfigs', function ($q) use ($configDate) {
            $q->where('config_date', $configDate)
              ->where('is_active', true);
        })
        ->where('client_type', 'Zerodha')
        ->where('is_token_valid', true);

        if ($this->option('broker_id')) {
            $query->where('id', $this->option('broker_id'));
        }

        return $query->get();
    }

    private function processBrokerPositions($broker, $configDate)
    {
        $stats = [
            'total' => 0,
            'old' => 0,
            'today' => 0,
            'ce_placed' => 0,
            'pe_placed' => 0,
            'failed' => 0,
        ];

        try {
            // Get broker's AMO configs for the date
            $configs = BrokerAmoConfig::getForBrokerAndDate($broker->id, $configDate);
            
            if ($configs->isEmpty()) {
                $this->warn("  ⚠️  No active AMO configurations found for this broker");
                return $stats;
            }

            $this->info("  ⚙️  Found " . $configs->count() . " active config(s)");
            foreach ($configs as $config) {
                $skipInfo = [];
                if ($config->skip_old_positions) $skipInfo[] = 'Skip Old';
                if ($config->skip_fresh_positions) $skipInfo[] = 'Skip Fresh';
                $skipText = !empty($skipInfo) ? ' [' . implode(', ', $skipInfo) . ']' : '';
                
                $this->info("     • {$config->symbol_type}: Old={$config->old_position_profit_percent}%, Fresh={$config->fresh_position_profit_percent}%{$skipText}");
            }

            // Get open positions
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
            
            // Filter and classify positions based on configs
            $classifiedPositions = $this->classifyPositionsByConfig($positions, $configs);
            
            $stats['old'] = count($classifiedPositions['old']);
            $stats['today'] = count($classifiedPositions['today']);

            $this->info("  📈 Total: {$stats['total']} | Old (before T-1): {$stats['old']} | Fresh (today+T-1): {$stats['today']}");

            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Process OLD positions - AMO orders
            if (!empty($classifiedPositions['old'])) {
                $this->info("\n  🌙 Processing OLD positions - AMO orders...");
                foreach ($classifiedPositions['old'] as $item) {
                    $result = $this->placeAMOOrder($kite, $broker, $item['position'], $item['config']);
                    if ($result['success']) {
                        if (str_ends_with($item['position']->tradingsymbol, 'CE')) {
                            $stats['ce_placed']++;
                        } else {
                            $stats['pe_placed']++;
                        }
                    } else {
                        $stats['failed']++;
                    }
                    sleep($this->rateLimitSeconds);
                }
            }

            // Process FRESH positions - AMO orders
            if (!empty($classifiedPositions['today'])) {
                $this->info("\n  ☀️  Processing FRESH positions - AMO orders...");
                foreach ($classifiedPositions['today'] as $item) {
                    $result = $this->placeAMOOrder($kite, $broker, $item['position'], $item['config']);
                    if ($result['success']) {
                        if (str_ends_with($item['position']->tradingsymbol, 'CE')) {
                            $stats['ce_placed']++;
                        } else {
                            $stats['pe_placed']++;
                        }
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

    private function classifyPositionsByConfig($positions, $configs)
    {
        $classified = ['today' => [], 'old' => []];
        $today = Carbon::today();
        $previousTradingDay = BrokerAmoConfig::getPreviousTradingDay($today);

        $this->info("  📅 Today: " . $today->format('d M Y') . " | Previous Trading Day: " . $previousTradingDay->format('d M Y'));

        foreach ($positions as $position) {
            // Extract symbol type (CE/PE)
            $symbolType = BrokerAmoConfig::extractSymbolType($position->tradingsymbol);
            
            if (!$symbolType) {
                // Not an option, skip
                continue;
            }

            // Find matching config
            $matchingConfig = null;
            
            // First try exact match
            if ($configs->has($symbolType)) {
                $matchingConfig = $configs[$symbolType];
            }
            // Then try BOTH
            elseif ($configs->has('BOTH')) {
                $matchingConfig = $configs['BOTH'];
            }

            if (!$matchingConfig) {
                $this->warn("  ⚠️  No config found for {$position->tradingsymbol} (type: {$symbolType})");
                continue;
            }

            $purchaseDate = Carbon::parse($position->purchase_date);
            
            $item = [
                'position' => $position,
                'config' => $matchingConfig
            ];
            
            // Check if position is fresh (today or T-1)
            $isFresh = BrokerAmoConfig::isFreshPosition($purchaseDate, $today);
            
            if ($isFresh) {
                // Skip if config says skip fresh
                if ($matchingConfig->skip_fresh_positions) {
                    $this->info("  ⏭️  Skipping FRESH position: {$position->tradingsymbol} (skip_fresh enabled)");
                    continue;
                }
                $classified['today'][] = $item;
            } else {
                // Skip if config says skip old
                if ($matchingConfig->skip_old_positions) {
                    $this->info("  ⏭️  Skipping OLD position: {$position->tradingsymbol} (skip_old enabled)");
                    continue;
                }
                $classified['old'][] = $item;
            }
        }

        return $classified;
    }

    private function placeAMOOrder($kite, $broker, $position, $config)
    {
        try {
            if ($this->option('dry-run')) {
                $isFresh = BrokerAmoConfig::isFreshPosition($position->purchase_date);
                $profitPercent = $isFresh 
                    ? $config->fresh_position_profit_percent 
                    : $config->old_position_profit_percent;
                $this->info("    [DRY-RUN] Would place AMO for {$position->tradingsymbol} with {$profitPercent}% profit");
                return ['success' => true, 'dry_run' => true];
            }

            $avgPrice = $position->average_price;
            $isFresh = BrokerAmoConfig::isFreshPosition($position->purchase_date);
            $profitPercent = $isFresh 
                ? $config->fresh_position_profit_percent 
                : $config->old_position_profit_percent;
            
            $targetPrice = $avgPrice * (1 + ($profitPercent / 100));
            
            // Round to correct tick size
            $targetPrice = $this->roundToTickSize($position->tradingsymbol, $targetPrice, $position->exchange);

            $transactionType = $position->quantity > 0 ? 'SELL' : 'BUY';
            $totalQuantity = abs($position->quantity);

            // Get lot size and calculate lots
            $lotSize = FreezingQuantity::getLotSize($position->tradingsymbol);
            $totalLots = FreezingQuantity::calculateLots($position->tradingsymbol, $totalQuantity);
            $freezingLots = FreezingQuantity::getFreezingQtyInLots($position->tradingsymbol);
            
            // Get chunk sizes (in proper quantities - multiples of lot size)
            $chunks = FreezingQuantity::getChunkSizes($position->tradingsymbol, $totalQuantity);
            $numChunks = count($chunks);

            if ($numChunks > 1) {
                $this->info("    📦 {$position->tradingsymbol}: {$totalQuantity} qty ({$totalLots} lots × {$lotSize}) → Splitting into {$numChunks} order(s) (freeze: {$freezingLots} lots) @ ₹{$targetPrice} ({$profitPercent}%)");
            } else {
                $this->info("    📦 {$position->tradingsymbol}: {$totalQuantity} qty ({$totalLots} lots × {$lotSize}) @ ₹{$targetPrice} ({$profitPercent}%)");
            }

            $placedOrders = 0;
            foreach ($chunks as $index => $qtyChunk) {
                $lotsInChunk = $qtyChunk / $lotSize;
                
                $orderParams = [
                    'exchange' => $position->exchange,
                    'tradingsymbol' => $position->tradingsymbol,
                    'transaction_type' => $transactionType,
                    'quantity' => $qtyChunk,
                    'product' => $position->product,
                    'order_type' => 'LIMIT',
                    'price' => $targetPrice,
                    'validity' => 'DAY',
                    'variety' => 'amo',
                ];

                try {
                    $result = $kite->placeOrder("amo", $orderParams);

                    if (isset($result->order_id)) {
                        $placedOrders++;
                        $this->info("    ✅ AMO #{$placedOrders} placed: {$qtyChunk} qty ({$lotsInChunk} lots) → Order: {$result->order_id}");
                        
                        $this->saveOrderToDatabase($broker, $result->order_id, $orderParams, 'AMO', $profitPercent, $config->symbol_type);
                    }
                } catch (\Exception $e) {
                    $this->error("    ❌ Chunk #{($index + 1)} failed: " . $e->getMessage());
                    $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'AMO', $qtyChunk);
                }

                if ($index < count($chunks) - 1) {
                    sleep($this->rateLimitSeconds);
                }
            }

            if ($placedOrders > 0) {
                $position->update([
                    'target_profit_percent' => $profitPercent,
                    'target_sell_price' => $targetPrice,
                    'square_off_status' => 'placed',
                ]);
                
                return ['success' => true, 'orders_placed' => $placedOrders];
            }

            throw new \Exception('No orders were placed successfully');
            
        } catch (\Exception $e) {
            $this->error("    ❌ AMO failed for {$position->tradingsymbol}: " . $e->getMessage());
            Log::error("AMO order failed: " . $e->getMessage());
            
            $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'AMO');
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function roundToTickSize($tradingSymbol, $price, $exchange)
    {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                ->where('exchange', $exchange)
                ->first();

            if ($instrument && $instrument->tick_size > 0) {
                $tickSize = $instrument->tick_size;
                return round($price / $tickSize) * $tickSize;
            }

            // Default tick sizes if instrument not found
            if ($exchange === 'NFO' || $exchange === 'BFO') {
                return round($price / 0.05) * 0.05; // 0.05 for F&O
            }
            
            return round($price, 2); // 0.01 for equity

        } catch (\Exception $e) {
            Log::warning("Error getting tick size for {$tradingSymbol}: " . $e->getMessage());
            return round($price, 2);
        }
    }

    private function saveOrderToDatabase($broker, $orderId, $orderParams, $orderCategory, $profitPercent, $symbolType)
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
                'status_message' => "{$orderCategory} {$symbolType} auto square-off ({$profitPercent}% profit)",
                'order_datetime' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving order to database: " . $e->getMessage());
        }
    }

    private function saveFailedOrderToDatabase($broker, $position, $error, $orderCategory, $quantity = null)
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
                'quantity' => $quantity ?? abs($position->quantity),
                'status_message' => "{$orderCategory} failed: " . substr($error, 0, 450),
                'order_datetime' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }

    private function displaySummary($stats, $configDate)
    {
        $this->info("\n" . str_repeat('=', 60));
        $this->info("📊 AMO EXECUTION SUMMARY - " . $configDate->format('d M Y'));
        $this->info(str_repeat('=', 60));
        $this->info("Total Brokers Processed: " . $stats['total_brokers']);
        $this->info("Total Positions Processed: " . $stats['total_positions']);
        $this->info("  • Old Positions (before T-1): " . $stats['old_positions']);
        $this->info("  • Fresh Positions (today+T-1): " . $stats['today_positions']);
        $this->info("");
        $this->info("AMO Orders Placed:");
        $this->info("  • CE (Call Options): " . $stats['ce_orders']);
        $this->info("  • PE (Put Options): " . $stats['pe_orders']);
        $this->info("  • Failed Orders: " . $stats['failed_orders']);
        $this->info(str_repeat('=', 60));
    }
}