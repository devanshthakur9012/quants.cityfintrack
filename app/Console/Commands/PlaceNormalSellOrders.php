<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\BrokerSellOrderConfig;
use App\Models\FreezingQuantity;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PlaceNormalSellOrders extends Command
{
    protected $signature = 'positions:place-sell-orders 
                            {--broker_id= : Specific broker ID}
                            {--symbol= : Filter by specific trading symbol (e.g. HAVELLS26FEB1420PE)}
                            {--dry-run : Simulate without placing orders}';

    protected $description = 'Place normal sell orders during market hours based on broker-level configurations';

    private $rateLimitSeconds       = 1;
    private $brokerRateLimitSeconds = 2;

    public function handle()
    {
        $this->info('☀️  Starting normal SELL orders at ' . now()->format('H:i:s'));

        // Check market hours (9:15 AM - 3:30 PM)
        $currentTime = now()->format('H:i');
        if (!$this->option('dry-run') && ($currentTime < '09:15' || $currentTime > '15:30')) {
            $this->warn('⚠️  This command should run during market hours (9:15 AM - 3:30 PM)');
            $this->warn('   Current time: ' . now()->format('H:i:s'));

            if (defined('STDIN') && stream_isatty(STDIN)) {
                if (!$this->confirm('Do you want to continue anyway?')) {
                    return Command::FAILURE;
                }
            } else {
                $this->warn('   Continuing execution from web interface...');
            }
        }

        try {
            $brokers = $this->getBrokers();

            if ($brokers->isEmpty()) {
                $this->warn('⚠️  No active brokers found!');
                return Command::FAILURE;
            }

            $stats = [
                'total_brokers'    => $brokers->count(),
                'total_positions'  => 0,
                'old_positions'    => 0,
                'today_positions'  => 0,
                'ce_orders'        => 0,
                'pe_orders'        => 0,
                'failed_orders'    => 0,
            ];

            foreach ($brokers as $broker) {
                $this->info("\n📊 Processing broker: {$broker->client_name} ({$broker->account_user_name})");

                $brokerStats = $this->processBrokerPositions($broker);

                $stats['total_positions'] += $brokerStats['total'];
                $stats['old_positions']   += $brokerStats['old'];
                $stats['today_positions'] += $brokerStats['today'];
                $stats['ce_orders']       += $brokerStats['ce_placed'];
                $stats['pe_orders']       += $brokerStats['pe_placed'];
                $stats['failed_orders']   += $brokerStats['failed'];

                sleep($this->brokerRateLimitSeconds);
            }

            $this->displaySummary($stats);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('PlaceNormalSellOrders error: ' . $e->getMessage());
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
            'total'      => 0,
            'old'        => 0,
            'today'      => 0,
            'ce_placed'  => 0,
            'pe_placed'  => 0,
            'failed'     => 0,
        ];

        try {
            $configs = BrokerSellOrderConfig::getForBroker($broker->id);

            if ($configs->isEmpty()) {
                $this->warn("  ⚠️  No active SELL configurations found for this broker");
                return $stats;
            }

            $this->info("  ⚙️  Found " . $configs->count() . " active config(s)");
            foreach ($configs as $config) {
                $skipInfo = [];
                if ($config->skip_old_positions)  $skipInfo[] = 'Skip Old';
                if ($config->skip_fresh_positions) $skipInfo[] = 'Skip Fresh';
                $skipText = !empty($skipInfo) ? ' [' . implode(', ', $skipInfo) . ']' : '';

                $this->info(
                    "     • {$config->symbol_type}: Old={$config->old_position_profit_percent}%," .
                    " Fresh={$config->fresh_position_profit_percent}%" .
                    " | Qty={$config->quantity_percent}%" .
                    " | Filter={$config->position_filter}" .
                    $skipText
                );
            }

            $positions = PortfolioPosition::where('user_id', $broker->user_id)
                ->where('broker_api_id', $broker->id)
                ->where('position_status', 'open')
                ->where('quantity', '>', 0)
                ->whereNotNull('purchase_date')
                ->when($this->option('symbol'), function ($query) {
                    $query->where('tradingsymbol', $this->option('symbol'));
                })
                ->get();

            if ($positions->isEmpty()) {
                $this->warn("  ⚠️  No open BUY positions found");
                return $stats;
            }

            $stats['total'] = $positions->count();

            // Build Kite connection (needed for LTP checks even before order placement)
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            $classifiedPositions = $this->classifyPositionsByConfig($positions, $configs, $kite);

            $stats['old']   = count($classifiedPositions['old']);
            $stats['today'] = count($classifiedPositions['today']);

            $this->info("  📈 Total: {$stats['total']} | Old (before T-1): {$stats['old']} | Fresh (today+T-1): {$stats['today']}");

            // Process OLD positions
            if (!empty($classifiedPositions['old'])) {
                $this->info("\n  🌙 Processing OLD positions - SELL orders...");
                foreach ($classifiedPositions['old'] as $item) {
                    $result = $this->placeSellOrder($kite, $broker, $item['position'], $item['config'], $item['ltp']);
                    if ($result['success']) {
                        if (str_ends_with($item['position']->tradingsymbol, 'CE')) {
                            $stats['ce_placed'] += $result['orders_placed'];
                        } else {
                            $stats['pe_placed'] += $result['orders_placed'];
                        }
                    } else {
                        $stats['failed']++;
                    }
                    sleep($this->rateLimitSeconds);
                }
            }

            // Process FRESH positions
            if (!empty($classifiedPositions['today'])) {
                $this->info("\n  ☀️  Processing FRESH positions - SELL orders...");
                foreach ($classifiedPositions['today'] as $item) {
                    $result = $this->placeSellOrder($kite, $broker, $item['position'], $item['config'], $item['ltp']);
                    if ($result['success']) {
                        if (str_ends_with($item['position']->tradingsymbol, 'CE')) {
                            $stats['ce_placed'] += $result['orders_placed'];
                        } else {
                            $stats['pe_placed'] += $result['orders_placed'];
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

    private function classifyPositionsByConfig($positions, $configs, $kite)
    {
        $classified = ['today' => [], 'old' => []];
        $today              = Carbon::today();
        $previousTradingDay = BrokerSellOrderConfig::getPreviousTradingDay($today);

        $this->info("  📅 Today: " . $today->format('d M Y') . " | Previous Trading Day: " . $previousTradingDay->format('d M Y'));

        foreach ($positions as $position) {
            $symbolType = BrokerSellOrderConfig::extractSymbolType($position->tradingsymbol);

            if (!$symbolType) {
                continue; // Not an option
            }

            // Find matching config (exact > BOTH)
            $matchingConfig = null;
            if ($configs->has($symbolType)) {
                $matchingConfig = $configs[$symbolType];
            } elseif ($configs->has('BOTH')) {
                $matchingConfig = $configs['BOTH'];
            }

            if (!$matchingConfig) {
                $this->warn("  ⚠️  No config found for {$position->tradingsymbol} (type: {$symbolType})");
                continue;
            }

            // ── Position filter: PROFIT / LOSS / BOTH ──────────────────────
            // We need the LTP here regardless of price_type so we can decide
            // whether the position is in profit or loss (LTP vs AVG price).
            $positionFilter = $matchingConfig->position_filter ?? 'BOTH';
            $ltp            = null; // we'll pass this along to avoid a second API call

            if ($positionFilter !== 'BOTH') {
                $ltp = $this->getLiveLTP($kite, $position->exchange, $position->tradingsymbol);

                if ($ltp !== null) {
                    $avgPrice   = $position->average_price;
                    $isProfit   = $ltp > $avgPrice;   // LTP > AVG  → profit
                    $isLoss     = $ltp < $avgPrice;   // LTP < AVG  → loss

                    if ($positionFilter === 'PROFIT' && !$isProfit) {
                        $sign = $isLoss ? 'LOSS' : 'FLAT';
                        $this->info("  ⏭️  Skipping {$position->tradingsymbol} — position filter=PROFIT but current={$sign} (AVG ₹{$avgPrice}, LTP ₹{$ltp})");
                        continue;
                    }

                    if ($positionFilter === 'LOSS' && !$isLoss) {
                        $sign = $isProfit ? 'PROFIT' : 'FLAT';
                        $this->info("  ⏭️  Skipping {$position->tradingsymbol} — position filter=LOSS but current={$sign} (AVG ₹{$avgPrice}, LTP ₹{$ltp})");
                        continue;
                    }
                } else {
                    $this->warn("  ⚠️  Could not fetch LTP for {$position->tradingsymbol} — skipping position filter check, including position");
                }
            }
            // ───────────────────────────────────────────────────────────────

            $purchaseDate = Carbon::parse($position->purchase_date);
            $isFresh      = BrokerSellOrderConfig::isFreshPosition($purchaseDate, $today);

            $item = [
                'position' => $position,
                'config'   => $matchingConfig,
                'ltp'      => $ltp, // pass already-fetched LTP so placeSellOrder can reuse it
            ];

            if ($isFresh) {
                if ($matchingConfig->skip_fresh_positions) {
                    $this->info("  ⏭️  Skipping FRESH position: {$position->tradingsymbol} (skip_fresh enabled)");
                    continue;
                }
                $classified['today'][] = $item;
            } else {
                if ($matchingConfig->skip_old_positions) {
                    $this->info("  ⏭️  Skipping OLD position: {$position->tradingsymbol} (skip_old enabled)");
                    continue;
                }
                $classified['old'][] = $item;
            }
        }

        return $classified;
    }

    /**
     * Place a SELL order for the given position + config.
     *
     * @param  mixed       $ltp  Pre-fetched LTP from classify step (or null)
     */
    private function placeSellOrder($kite, $broker, $position, $config, $cachedLtp = null)
    {
        try {
            $isFresh       = BrokerSellOrderConfig::isFreshPosition($position->purchase_date);
            $profitPercent = $isFresh
                ? $config->fresh_position_profit_percent
                : $config->old_position_profit_percent;

            // ── Determine base price ──────────────────────────────────────
            $priceType = $config->price_type ?? 'AVG';
            $basePrice = null;

            if ($priceType === 'LTP') {
                // Reuse LTP already fetched during classification if available
                $basePrice = $cachedLtp ?? $this->getLiveLTP($kite, $position->exchange, $position->tradingsymbol);

                if (!$basePrice) {
                    $this->warn("    ⚠️  Could not fetch LTP for {$position->tradingsymbol}, falling back to AVG price");
                    $basePrice = $position->average_price;
                    $priceType = 'AVG (fallback)';
                }
            } else {
                $basePrice = $position->average_price;
            }
            // ─────────────────────────────────────────────────────────────

            // ── Quantity percent ──────────────────────────────────────────
            $quantityPercent = $config->quantity_percent ?? 100;
            $totalAvailable  = abs($position->quantity);

            // Calculate sell quantity, rounded down to nearest lot size
            $lotSize    = FreezingQuantity::getLotSize($position->tradingsymbol);
            $rawSellQty = (int) floor(($totalAvailable * $quantityPercent) / 100);

            // Ensure at least 1 lot
            if ($lotSize > 0 && $rawSellQty < $lotSize) {
                $rawSellQty = $lotSize;
            }

            // Round down to nearest lot size multiple
            if ($lotSize > 0) {
                $rawSellQty = (int) (floor($rawSellQty / $lotSize) * $lotSize);
            }

            // Safety: cannot sell more than available
            $sellQuantity = min($rawSellQty, $totalAvailable);

            if ($sellQuantity <= 0) {
                $this->warn("    ⚠️  Calculated sell quantity is 0 for {$position->tradingsymbol}, skipping");
                return ['success' => false, 'error' => 'Zero sell quantity', 'orders_placed' => 0];
            }
            // ─────────────────────────────────────────────────────────────

            if ($this->option('dry-run')) {
                $this->info(
                    "    [DRY-RUN] Would place SELL for {$position->tradingsymbol}" .
                    " | Base: {$priceType} @ ₹{$basePrice}" .
                    " | Target: {$profitPercent}%" .
                    " | Qty: {$sellQuantity}/{$totalAvailable} ({$quantityPercent}%)"
                );
                return ['success' => true, 'dry_run' => true, 'orders_placed' => 1];
            }

            $targetPrice = $basePrice * (1 + ($profitPercent / 100));
            $targetPrice = $this->roundToTickSize($position->tradingsymbol, $targetPrice, $position->exchange);

            $orderLabel = $profitPercent >= 0 ? "PROFIT ({$profitPercent}%)" : "STOP-LOSS ({$profitPercent}%)";

            $this->info(
                "    📦 {$position->tradingsymbol}: base={$priceType} ₹{$basePrice} → target ₹{$targetPrice} [{$orderLabel}]" .
                " | Selling {$sellQuantity}/{$totalAvailable} qty ({$quantityPercent}%)"
            );

            $totalLots    = FreezingQuantity::calculateLots($position->tradingsymbol, $sellQuantity);
            $freezingLots = FreezingQuantity::getFreezingQtyInLots($position->tradingsymbol);
            $chunks       = FreezingQuantity::getChunkSizes($position->tradingsymbol, $sellQuantity);
            $numChunks    = count($chunks);

            if ($numChunks > 1) {
                $this->info("    📦 Splitting into {$numChunks} order(s) (freeze: {$freezingLots} lots)");
            }

            $placedOrders = 0;

            foreach ($chunks as $index => $qtyChunk) {
                $lotsInChunk = $lotSize > 0 ? ($qtyChunk / $lotSize) : $qtyChunk;

                $orderParams = [
                    'exchange'         => $position->exchange,
                    'tradingsymbol'    => $position->tradingsymbol,
                    'transaction_type' => 'SELL',
                    'quantity'         => $qtyChunk,
                    'product'          => $position->product,
                    'order_type'       => 'LIMIT',
                    'price'            => $targetPrice,
                    'validity'         => 'DAY',
                ];

                try {
                    $result = $kite->placeOrder("regular", $orderParams);

                    if (isset($result->order_id)) {
                        $placedOrders++;
                        $this->info("    ✅ SELL #{$placedOrders}: {$qtyChunk} qty ({$lotsInChunk} lots) → Order: {$result->order_id}");
                        $this->saveOrderToDatabase($broker, $result->order_id, $orderParams, 'SELL', $profitPercent, $config->symbol_type);
                    }
                } catch (\Exception $e) {
                    $this->error("    ❌ Chunk #" . ($index + 1) . " failed: " . $e->getMessage());
                    $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'SELL', $qtyChunk);
                }

                if ($index < count($chunks) - 1) {
                    sleep($this->rateLimitSeconds);
                }
            }

            if ($placedOrders > 0) {
                $position->update([
                    'target_profit_percent' => $profitPercent,
                    'target_sell_price'     => $targetPrice,
                    'square_off_status'     => 'sell_placed',
                ]);

                return ['success' => true, 'orders_placed' => $placedOrders];
            }

            throw new \Exception('No orders were placed successfully');

        } catch (\Exception $e) {
            $this->error("    ❌ SELL failed for {$position->tradingsymbol}: " . $e->getMessage());
            Log::error("SELL order failed: " . $e->getMessage());
            $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'SELL');
            return ['success' => false, 'error' => $e->getMessage(), 'orders_placed' => 0];
        }
    }

    /**
     * Fetch live LTP from Zerodha for a single instrument
     */
    private function getLiveLTP($kite, $exchange, $tradingSymbol)
    {
        try {
            $instrumentKey = "{$exchange}:{$tradingSymbol}";
            $quotes        = $kite->getQuote([$instrumentKey]);

            if (isset($quotes[$instrumentKey]->last_price) && $quotes[$instrumentKey]->last_price > 0) {
                $ltp = $quotes[$instrumentKey]->last_price;
                $this->info("    📡 LTP fetched for {$tradingSymbol}: ₹{$ltp}");
                return $ltp;
            }

            return null;

        } catch (\Exception $e) {
            $this->warn("    ⚠️  LTP fetch failed for {$tradingSymbol}: " . $e->getMessage());
            Log::warning("LTP fetch failed for {$tradingSymbol}: " . $e->getMessage());
            return null;
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

            if ($exchange === 'NFO' || $exchange === 'BFO') {
                return round($price / 0.05) * 0.05;
            }

            return round($price, 2);

        } catch (\Exception $e) {
            Log::warning("Error getting tick size for {$tradingSymbol}: " . $e->getMessage());
            return round($price, 2);
        }
    }

    private function saveOrderToDatabase($broker, $orderId, $orderParams, $orderCategory, $profitPercent, $symbolType)
    {
        try {
            OrderBook::create([
                'user_id'          => $broker->user_id,
                'broker_username'  => $broker->account_user_name,
                'order_id'         => $orderId,
                'status'           => 'PENDING',
                'trading_symbol'   => $orderParams['tradingsymbol'],
                'order_type'       => $orderParams['order_type'],
                'transaction_type' => $orderParams['transaction_type'],
                'product'          => $orderParams['product'],
                'price'            => $orderParams['price'] ?? '-',
                'quantity'         => $orderParams['quantity'],
                'status_message'   => $profitPercent >= 0
                    ? "{$orderCategory} {$symbolType} ({$profitPercent}% profit)"
                    : "{$orderCategory} {$symbolType} ({$profitPercent}% stop-loss)",
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving order to database: " . $e->getMessage());
        }
    }

    private function saveFailedOrderToDatabase($broker, $position, $error, $orderCategory, $quantity = null)
    {
        try {
            OrderBook::create([
                'user_id'          => $broker->user_id,
                'broker_username'  => $broker->account_user_name,
                'order_id'         => '-',
                'status'           => 'FAILED',
                'trading_symbol'   => $position->tradingsymbol,
                'order_type'       => 'LIMIT',
                'transaction_type' => 'SELL',
                'product'          => $position->product,
                'price'            => '-',
                'quantity'         => $quantity ?? abs($position->quantity),
                'status_message'   => "{$orderCategory} failed: " . substr($error, 0, 450),
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }

    private function displaySummary($stats)
    {
        $this->info("\n" . str_repeat('=', 60));
        $this->info("📊 NORMAL SELL ORDERS SUMMARY");
        $this->info(str_repeat('=', 60));
        $this->info("Total Brokers Processed: " . $stats['total_brokers']);
        $this->info("Total Positions: " . $stats['total_positions']);
        $this->info("  • Old Positions (before T-1): " . $stats['old_positions']);
        $this->info("  • Fresh Positions (today+T-1): " . $stats['today_positions']);
        $this->info("");
        $this->info("SELL Orders Placed:");
        $this->info("  • CE: " . $stats['ce_orders']);
        $this->info("  • PE: " . $stats['pe_orders']);
        $this->info("  • Failed Orders: " . $stats['failed_orders']);
        $this->info(str_repeat('=', 60));
    }
}