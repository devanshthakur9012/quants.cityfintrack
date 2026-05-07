<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\BrokerLiveLtpConfig;
use App\Models\FreezingQuantity;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PlaceLiveLtpOrders extends Command
{
    protected $signature = 'positions:place-live-ltp-orders 
                            {--broker_id= : Specific broker ID}
                            {--symbol= : Test specific symbol (e.g., NIFTY26FEB22500CE)}
                            {--loss-tolerance=0 : Allow order if LTP < Avg but gap is within this % (e.g. 5 means allow up to 5% below avg)}
                            {--dry-run : Simulate without placing orders}';

    protected $description = 'Place SELL orders based on Avg Price + profit %, only if LTP condition is met';

    private $rateLimitSeconds = 1;
    private $brokerRateLimitSeconds = 2;

    public function handle()
    {
        $this->info('📡 Starting Live LTP SELL orders at ' . now()->format('H:i:s'));

        // Loss tolerance
        $lossTolerance = (float) $this->option('loss-tolerance');
        if ($lossTolerance > 0) {
            $this->info("⚠️  Loss Tolerance Active: Orders allowed even if LTP is up to {$lossTolerance}% below average price");
        }

        // Test mode validation
        if ($this->option('symbol')) {
            $this->info('🧪 TEST MODE: Testing symbol ' . $this->option('symbol'));
            if (!$this->option('broker_id')) {
                $this->error('❌ --broker_id is required when using --symbol option');
                return Command::FAILURE;
            }
        }

        // Market hours check
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
                'total_brokers'     => $brokers->count(),
                'total_positions'   => 0,
                'ce_orders'         => 0,
                'pe_orders'         => 0,
                'failed_orders'     => 0,
                'skipped_positions' => 0,
            ];

            foreach ($brokers as $broker) {
                $this->info("\n📊 Processing broker: {$broker->client_name} ({$broker->account_user_name})");

                $brokerStats = $this->processBrokerPositions($broker, $lossTolerance);

                $stats['total_positions']   += $brokerStats['total'];
                $stats['ce_orders']         += $brokerStats['ce_placed'];
                $stats['pe_orders']         += $brokerStats['pe_placed'];
                $stats['failed_orders']     += $brokerStats['failed'];
                $stats['skipped_positions'] += $brokerStats['skipped'];

                sleep($this->brokerRateLimitSeconds);
            }

            $this->displaySummary($stats, $lossTolerance);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('PlaceLiveLtpOrders error: ' . $e->getMessage());
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

    private function processBrokerPositions($broker, $lossTolerance)
    {
        $stats = [
            'total'     => 0,
            'ce_placed' => 0,
            'pe_placed' => 0,
            'failed'    => 0,
            'skipped'   => 0,
        ];

        try {
            // Get broker's Live LTP configs
            $configs = BrokerLiveLtpConfig::getForBroker($broker->id);

            if ($configs->isEmpty()) {
                $this->warn("  ⚠️  No active Live LTP configurations found for this broker");
                return $stats;
            }

            $this->info("  ⚙️  Found " . $configs->count() . " active config(s)");
            foreach ($configs as $config) {
                $this->info("     • {$config->symbol_type}: Profit={$config->profit_percent}% (applied on AVG price)");
            }

            // Build positions query - STRICTLY scoped to this broker's user
            $positionsQuery = PortfolioPosition::where('user_id', $broker->user_id)
                ->where('broker_api_id', $broker->id)  // ← CRITICAL: must match broker
                ->where('position_status', 'open')
                ->where('quantity', '>', 0)
                ->whereNotNull('purchase_date');

            // Filter for specific symbol in test mode
            if ($this->option('symbol')) {
                $testSymbol = strtoupper($this->option('symbol'));
                $positionsQuery->where('tradingsymbol', $testSymbol);
                $this->info("  🧪 Filtering for test symbol: {$testSymbol}");
            }

            $positions = $positionsQuery->get();

            if ($positions->isEmpty()) {
                $msg = $this->option('symbol')
                    ? "No open BUY position found for symbol [" . strtoupper($this->option('symbol')) . "] under broker [{$broker->account_user_name}]"
                    : "No open BUY positions found for this broker";
                $this->warn("  ⚠️  {$msg}");
                return $stats;
            }

            $stats['total'] = $positions->count();
            $matchedPositions = $this->matchPositionsByConfig($positions, $configs);

            // Test mode: detailed position info
            if ($this->option('symbol')) {
                $this->info("\n  🧪 TEST MODE - Position Details:");
                foreach ($positions as $pos) {
                    $profitPercent = $this->getProfitPercent($pos, $configs);
                    $targetPrice   = $profitPercent
                        ? round($pos->average_price * (1 + ($profitPercent / 100)), 2)
                        : null;

                    $this->info("     Trading Symbol : {$pos->tradingsymbol}");
                    $this->info("     Exchange       : {$pos->exchange}");
                    $this->info("     Product        : {$pos->product}");
                    $this->info("     Quantity       : {$pos->quantity}");
                    $this->info("     Average Price  : ₹{$pos->average_price}");
                    $this->info("     Purchase Date  : {$pos->purchase_date}");
                    $this->info("     Broker API ID  : {$pos->broker_api_id} (must = {$broker->id})");

                    // Strict broker ownership check
                    if ((int)$pos->broker_api_id !== (int)$broker->id) {
                        $this->error("     ❌ BROKER MISMATCH! Position belongs to broker_api_id={$pos->broker_api_id}, not {$broker->id}");
                        continue;
                    }
                    $this->info("     ✅ Broker Match : broker_api_id={$pos->broker_api_id} ✓");

                    $symbolType = BrokerLiveLtpConfig::extractSymbolType($pos->tradingsymbol);
                    $this->info("     Detected Type  : " . ($symbolType ?: 'Not an option'));

                    if ($configs->has($symbolType)) {
                        $c = $configs[$symbolType];
                        $this->info("     ✅ Matched Config: {$symbolType} (Profit: {$c->profit_percent}% on avg)");
                    } elseif ($configs->has('BOTH')) {
                        $c = $configs['BOTH'];
                        $this->info("     ✅ Matched Config: BOTH (Profit: {$c->profit_percent}% on avg)");
                    } else {
                        $this->warn("     ❌ No matching config found!");
                    }

                    if ($targetPrice) {
                        $this->info("     🎯 Target Price  : ₹{$pos->average_price} × (1 + {$profitPercent}%) = ₹{$targetPrice}");
                    }

                    if ($lossTolerance > 0) {
                        $this->info("     ⚠️  Loss Tolerance: up to {$lossTolerance}% below avg LTP accepted");
                    }
                }
                $this->info("");
            }

            $this->info("  📈 Total: {$stats['total']} | Matched with config: " . count($matchedPositions));

            // Init Kite
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // ─── STEP 1: FETCH LIVE POSITIONS FROM ZERODHA ───────────
            // Build TWO maps:
            //   $liveNetQty  : symbol => net quantity from Zerodha "net" array
            //   $liveFetchOk : whether the API call succeeded at all
            //
            // Zerodha "net" positions is the source of truth:
            //   - Symbol present with quantity > 0  → still open
            //   - Symbol present with quantity = 0  → fully closed today
            //   - Symbol NOT present at all         → either closed earlier
            //                                         or never existed for this broker
            //
            $liveNetQty  = [];   // symbol => net qty (from Zerodha)
            $liveFetchOk = false;

            try {
                $livePositions = $kite->getPositions();

                // Use ONLY "net" — this is the real open quantity
                // "day" can show both BUY and SELL legs and is misleading
                $netPositions = (array)($livePositions->net ?? []);

                foreach ($netPositions as $lp) {
                    $sym = $lp->tradingsymbol ?? null;
                    if (!$sym) continue;
                    // Store the net quantity as-is (could be 0 if closed today)
                    $liveNetQty[$sym] = (int)($lp->quantity ?? 0);
                }

                $openCount = count(array_filter($liveNetQty, fn($q) => $q > 0));
                $this->info("  📋 Zerodha live positions fetched: " . count($liveNetQty) . " total | {$openCount} open (qty>0)");
                $liveFetchOk = true;

            } catch (\Exception $e) {
                $this->error("  ❌ FAILED to fetch live positions from Zerodha: " . $e->getMessage());
                $this->error("  🚫 ABORTING — cannot verify position status without live data!");
                // Do NOT proceed if we can't verify — too risky
                return $stats;
            }
            // ─────────────────────────────────────────────────────────

            // ─── STEP 2: FILTER OUT ALREADY-CLOSED POSITIONS ─────────
            $activePositions = [];
            $alreadyClosed   = 0;

            foreach ($matchedPositions as $item) {
                $sym   = $item['position']->tradingsymbol;
                $dbQty = (int)$item['position']->quantity;

                // Check if symbol exists in Zerodha live data
                if (array_key_exists($sym, $liveNetQty)) {
                    $liveQty = $liveNetQty[$sym];

                    if ($liveQty <= 0) {
                        // Symbol in Zerodha but qty=0 → closed today
                        $this->warn("    ⏭️  CLOSED {$sym}: Zerodha net qty={$liveQty} (closed). Updating DB...");
                        $item['position']->update(['position_status' => 'closed']);
                        $alreadyClosed++;
                        continue;
                    }

                    if ($liveQty != $dbQty) {
                        // Partial close — trust Zerodha qty
                        $this->warn("    ⚠️  PARTIAL {$sym}: DB qty={$dbQty} → Zerodha qty={$liveQty}. Using live qty.");
                        $item['position']->quantity = $liveQty;
                    }

                } else {
                    // Symbol NOT in Zerodha at all → not open for this broker
                    $this->warn("    ⏭️  NOT FOUND {$sym}: Not in Zerodha positions (already closed or wrong broker). Updating DB...");
                    $item['position']->update(['position_status' => 'closed']);
                    $alreadyClosed++;
                    continue;
                }

                $activePositions[] = $item;
            }

            $this->info("  ✅ Active: " . count($activePositions) . " | Closed/Not-found (skipped): {$alreadyClosed}");

            if (empty($activePositions)) {
                $this->warn("  ⚠️  No active positions to process after live check.");
                return $stats;
            }
            // ─────────────────────────────────────────────────────────

            // ─── STEP 3: FETCH LTP ONLY FOR ACTIVE POSITIONS ─────────
            $tradingSymbols = array_map(function ($item) {
                return $item['position']->exchange . ':' . $item['position']->tradingsymbol;
            }, $activePositions);

            $ltpData = [];
            if (!empty($tradingSymbols)) {
                try {
                    $ltpResponse = $kite->getLTP($tradingSymbols);
                    $ltpData     = (array)$ltpResponse;
                    $this->info("  📡 Fetched live LTP for " . count($ltpData) . " symbols");
                } catch (\Exception $e) {
                    $this->error("  ❌ Error fetching LTP: " . $e->getMessage());
                    return $stats;
                }
            }
            // ─────────────────────────────────────────────────────────

            // ─── STEP 4: PLACE ORDERS FOR ACTIVE POSITIONS ───────────
            if (!empty($activePositions)) {
                $this->info("\n  💰 Processing positions...");
                foreach ($activePositions as $item) {
                    $result = $this->placeLtpSellOrder($kite, $broker, $item['position'], $item['config'], $ltpData, $lossTolerance);

                    if ($result['success']) {
                        if (str_ends_with($item['position']->tradingsymbol, 'CE')) {
                            $stats['ce_placed'] += $result['orders_placed'];
                        } else {
                            $stats['pe_placed'] += $result['orders_placed'];
                        }
                    } elseif (!empty($result['skipped'])) {
                        $stats['skipped']++;
                    } else {
                        $stats['failed']++;
                    }

                    sleep($this->rateLimitSeconds);
                }
            }
            // ─────────────────────────────────────────────────────────

        } catch (\Exception $e) {
            $this->error("  ❌ Error processing broker: " . $e->getMessage());
            Log::error("Error processing broker {$broker->id}: " . $e->getMessage());
        }

        return $stats;
    }

    private function matchPositionsByConfig($positions, $configs)
    {
        $matched = [];

        foreach ($positions as $position) {
            $symbolType = BrokerLiveLtpConfig::extractSymbolType($position->tradingsymbol);
            if (!$symbolType) continue;

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

            $matched[] = ['position' => $position, 'config' => $matchingConfig];
        }

        return $matched;
    }

    private function getProfitPercent($position, $configs)
    {
        $symbolType = BrokerLiveLtpConfig::extractSymbolType($position->tradingsymbol);
        if (!$symbolType) return null;

        if ($configs->has($symbolType)) return $configs[$symbolType]->profit_percent;
        if ($configs->has('BOTH'))      return $configs['BOTH']->profit_percent;

        return null;
    }

    private function placeLtpSellOrder($kite, $broker, $position, $config, $ltpData, $lossTolerance)
    {
        try {
            // ─── BROKER OWNERSHIP CHECK ───────────────────────────────
            // Ensure this position actually belongs to this broker
            if ((int)$position->broker_api_id !== (int)$broker->id) {
                $this->error("    ❌ BROKER MISMATCH: {$position->tradingsymbol} belongs to broker_api_id={$position->broker_api_id}, not {$broker->id}. SKIPPING.");
                Log::error("Broker mismatch for {$position->tradingsymbol}: position broker={$position->broker_api_id}, requested broker={$broker->id}");
                return ['success' => false, 'skipped' => true, 'orders_placed' => 0];
            }
            // ─────────────────────────────────────────────────────────

            $symbolKey = $position->exchange . ':' . $position->tradingsymbol;

            // Get live LTP
            if (!isset($ltpData[$symbolKey]) || !isset($ltpData[$symbolKey]->last_price)) {
                throw new \Exception("Live LTP not available for {$position->tradingsymbol}");
            }

            $liveLtp       = $ltpData[$symbolKey]->last_price;
            $avgPrice      = $position->average_price;
            $profitPercent = $config->profit_percent;

            // ─── TARGET PRICE = AVG PRICE + PROFIT % ─────────────────
            // (NOT LTP + profit — we use avg as the base)
            $targetPrice = $avgPrice * (1 + ($profitPercent / 100));
            $targetPrice = $this->roundToTickSize($position->tradingsymbol, $targetPrice, $position->exchange);
            // ─────────────────────────────────────────────────────────

            // Gap % of LTP vs Avg (used only for the LTP condition check)
            // Positive = LTP above avg, Negative = LTP below avg
            $gapPercent = round((($liveLtp - $avgPrice) / $avgPrice) * 100, 2);

            // ─── LTP CONDITION CHECK ──────────────────────────────────
            // Case 1: LTP >= Avg               → place (profitable LTP)
            // Case 2: LTP < Avg but within tol → place with warning
            // Case 3: LTP < Avg beyond tol     → skip
            // ─────────────────────────────────────────────────────────
            if ($liveLtp >= $avgPrice) {
                $decision = 'place';
                $reason   = "+{$gapPercent}% above avg ✅";
            } elseif ($lossTolerance > 0 && abs($gapPercent) <= $lossTolerance) {
                $decision = 'place_with_loss';
                $reason   = "{$gapPercent}% below avg ⚠️ within {$lossTolerance}% tolerance";
            } else {
                $decision = 'skip';
                $reason   = $lossTolerance > 0
                    ? "{$gapPercent}% below avg ❌ exceeds {$lossTolerance}% tolerance"
                    : "{$gapPercent}% below avg ❌ (use --loss-tolerance=X to allow)";
            }

            if ($decision === 'skip') {
                $this->warn("    ⏭️  SKIPPED {$position->tradingsymbol}: LTP=₹{$liveLtp} | Avg=₹{$avgPrice} | {$reason}");
                return ['success' => false, 'skipped' => true, 'orders_placed' => 0];
            }

            // Dry run
            if ($this->option('dry-run')) {
                $tag = $decision === 'place_with_loss' ? '⚠️ ' : '✅ ';
                $this->info("    [DRY-RUN] {$tag}{$position->tradingsymbol}:");
                $this->info("              LTP=₹{$liveLtp} | Avg=₹{$avgPrice} | LTP check: {$reason}");
                $this->info("              Target = Avg ₹{$avgPrice} + {$profitPercent}% = ₹{$targetPrice}");
                return ['success' => true, 'dry_run' => true, 'orders_placed' => 1];
            }

            // Lot / chunk calculation
            $totalQuantity = abs($position->quantity);
            $lotSize       = FreezingQuantity::getLotSize($position->tradingsymbol);
            $totalLots     = FreezingQuantity::calculateLots($position->tradingsymbol, $totalQuantity);
            $freezingLots  = FreezingQuantity::getFreezingQtyInLots($position->tradingsymbol);
            $chunks        = FreezingQuantity::getChunkSizes($position->tradingsymbol, $totalQuantity);
            $numChunks     = count($chunks);

            $this->info("    📊 Lot Info: lot_size={$lotSize} | total_lots={$totalLots} | freeze_limit={$freezingLots} lots (" . ($freezingLots * $lotSize) . " qty) | chunks={$numChunks}");

            $lossTag = $decision === 'place_with_loss' ? ' ⚠️ LOSS TOL' : '';
            if ($numChunks > 1) {
                $this->info("    📦 {$position->tradingsymbol}: LTP=₹{$liveLtp} ({$gapPercent}%){$lossTag} | Target=Avg ₹{$avgPrice} +{$profitPercent}%=₹{$targetPrice} | {$totalQuantity} qty → {$numChunks} orders");
            } else {
                $this->info("    📦 {$position->tradingsymbol}: LTP=₹{$liveLtp} ({$gapPercent}%){$lossTag} | Target=Avg ₹{$avgPrice} +{$profitPercent}%=₹{$targetPrice} | {$totalQuantity} qty");
            }

            $placedOrders = 0;
            foreach ($chunks as $index => $qtyChunk) {
                $lotsInChunk = $qtyChunk / $lotSize;

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
                        $this->info("    ✅ SELL #{$placedOrders}: {$qtyChunk} qty ({$lotsInChunk} lots) @ ₹{$targetPrice} → Order: {$result->order_id}");
                        $this->saveOrderToDatabase($broker, $result->order_id, $orderParams, 'LTP-SELL', $profitPercent, $config->symbol_type, $liveLtp, $gapPercent, $avgPrice);
                    }
                } catch (\Exception $e) {
                    $this->error("    ❌ Chunk #" . ($index + 1) . " failed: " . $e->getMessage());
                    $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'LTP-SELL', $qtyChunk);
                }

                if ($index < count($chunks) - 1) {
                    sleep($this->rateLimitSeconds);
                }
            }

            if ($placedOrders > 0) {
                $position->update([
                    'target_profit_percent' => $profitPercent,
                    'target_sell_price'     => $targetPrice,
                    'square_off_status'     => 'ltp_sell_placed',
                ]);
                return ['success' => true, 'orders_placed' => $placedOrders];
            }

            throw new \Exception('No orders were placed successfully');

        } catch (\Exception $e) {
            $this->error("    ❌ LTP SELL failed for {$position->tradingsymbol}: " . $e->getMessage());
            Log::error("LTP SELL order failed: " . $e->getMessage());
            $this->saveFailedOrderToDatabase($broker, $position, $e->getMessage(), 'LTP-SELL');
            return ['success' => false, 'error' => $e->getMessage(), 'orders_placed' => 0];
        }
    }

    private function roundToTickSize($tradingSymbol, $price, $exchange)
    {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                ->where('exchange', $exchange)
                ->first();

            if ($instrument && $instrument->tick_size > 0) {
                return round($price / $instrument->tick_size) * $instrument->tick_size;
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

    private function saveOrderToDatabase($broker, $orderId, $orderParams, $orderCategory, $profitPercent, $symbolType, $ltp, $gapPercent, $avgPrice)
    {
        try {
            $gapInfo = $gapPercent >= 0 ? "+{$gapPercent}%" : "{$gapPercent}%";
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
                'status_message'   => "{$orderCategory} {$symbolType} | Avg=₹{$avgPrice} +{$profitPercent}% | LTP=₹{$ltp} ({$gapInfo})",
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

    private function displaySummary($stats, $lossTolerance)
    {
        $this->info("\n" . str_repeat('=', 60));
        $this->info("📊 LIVE LTP SELL ORDERS SUMMARY");
        $this->info(str_repeat('=', 60));
        if ($lossTolerance > 0) {
            $this->info("Loss Tolerance Applied  : {$lossTolerance}%");
        }
        $this->info("Total Brokers Processed : " . $stats['total_brokers']);
        $this->info("Total Positions         : " . $stats['total_positions']);
        $this->info("");
        $this->info("SELL Orders Placed:");
        $this->info("  • CE                  : " . $stats['ce_orders']);
        $this->info("  • PE                  : " . $stats['pe_orders']);
        $this->info("  • Skipped (out of tol): " . $stats['skipped_positions']);
        $this->info("  • Failed Orders       : " . $stats['failed_orders']);
        $this->info(str_repeat('=', 60));
    }
}