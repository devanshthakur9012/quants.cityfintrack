<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\BrokerStopLossConfig;
use App\Models\FreezingQuantity;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PlaceStopLossOrders extends Command
{
    protected $signature = 'positions:place-stop-loss-orders
                            {--broker_id=  : Run only for a specific broker}
                            {--symbol_type= : Filter by CE / PE / BOTH}
                            {--config_id=  : Run only a specific config row}
                            {--symbol=     : Filter by exact trading symbol}
                            {--dry-run     : Simulate without placing orders}';

    protected $description = 'Fetch live positions from Zerodha and place stop-loss SELL orders based on BrokerStopLossConfig';

    private int $rateLimitSeconds       = 1;
    private int $brokerRateLimitSeconds = 2;

    // ─────────────────────────────────────────────────────────────
    // ENTRY POINT
    // ─────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->info('🛑  Starting Stop-Loss orders at ' . now()->format('H:i:s'));

        $currentTime = now()->format('H:i');
        if (!$this->option('dry-run') && ($currentTime < '09:15' || $currentTime > '15:30')) {
            $this->warn('⚠️  Outside market hours (9:15 AM – 3:30 PM). Current: ' . now()->format('H:i:s'));

            // Non-interactive (called from web) — continue anyway
            if (defined('STDIN') && stream_isatty(STDIN)) {
                if (!$this->confirm('Continue anyway?')) {
                    return Command::FAILURE;
                }
            } else {
                $this->warn('   Continuing from web interface...');
            }
        }

        try {
            $brokers = $this->getBrokers();

            if ($brokers->isEmpty()) {
                $this->warn('⚠️  No active brokers found.');
                return Command::FAILURE;
            }

            $totals = [
                'brokers'         => $brokers->count(),
                'positions_seen'  => 0,
                'sl_triggered'    => 0,
                'orders_placed'   => 0,
                'orders_failed'   => 0,
                'positions_skipped' => 0,
            ];

            foreach ($brokers as $broker) {
                $this->info("\n📊 Broker: {$broker->client_name} ({$broker->account_user_name})");

                $result = $this->processBroker($broker);

                $totals['positions_seen']    += $result['positions_seen'];
                $totals['sl_triggered']      += $result['sl_triggered'];
                $totals['orders_placed']     += $result['orders_placed'];
                $totals['orders_failed']     += $result['orders_failed'];
                $totals['positions_skipped'] += $result['positions_skipped'];

                sleep($this->brokerRateLimitSeconds);
            }

            $this->displaySummary($totals);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('PlaceStopLossOrders failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // BROKER LOOP
    // ─────────────────────────────────────────────────────────────

    private function processBroker(BrokerApi $broker): array
    {
        $stats = [
            'positions_seen'    => 0,
            'sl_triggered'      => 0,
            'orders_placed'     => 0,
            'orders_failed'     => 0,
            'positions_skipped' => 0,
        ];

        try {
            // ── 1. Load configs for this broker ──────────────────────────
            $configs = $this->loadConfigs($broker->id);

            if ($configs->isEmpty()) {
                $this->warn('  ⚠️  No active SL configs for this broker — skipping.');
                return $stats;
            }

            $this->info('  ⚙️  ' . $configs->count() . ' active config(s):');
            foreach ($configs as $config) {
                $skip = collect([
                    $config->skip_old_positions   ? 'Skip Old'   : null,
                    $config->skip_fresh_positions ? 'Skip Fresh' : null,
                ])->filter()->implode(', ');

                $this->info(
                    "     • [{$config->id}] {$config->symbol_type}" .
                    " | SL={$config->stop_loss_percent}%" .
                    " | Base={$config->price_type}" .
                    " | Qty={$config->quantity_percent}%" .
                    " | Filter={$config->position_filter}" .
                    ($skip ? " | {$skip}" : '')
                );
            }

            // ── 2. Connect to Zerodha ─────────────────────────────────────
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // ── 3. Fetch LIVE positions from Zerodha ──────────────────────
            //    We do NOT read from our DB — always fresh from API.
            $livePositions = $this->fetchLivePositions($kite);

            if (empty($livePositions)) {
                $this->warn('  ℹ️  No open positions returned from Zerodha.');
                return $stats;
            }

            $this->info('  📡 Zerodha returned ' . count($livePositions) . ' open net position(s).');

            $stats['positions_seen'] = count($livePositions);

            // ── 4. Build LTP map from positions own last_price field ─────
            //    Zerodha's getPositions() response already includes last_price
            //    and pnl for each position — this is the most accurate source.
            //    We fall back to getQuote batch only for instruments where
            //    last_price is 0 or missing (e.g. illiquid / not yet traded).
            $ltpMap = $this->buildLtpMapFromPositions($livePositions);

            // Find instruments that still need LTP from getQuote (last_price was 0)
            $missing = array_filter($livePositions, fn($p) => !isset($ltpMap[$p['tradingsymbol']]));
            if (!empty($missing)) {
                $this->warn('  ⚠️  ' . count($missing) . ' instrument(s) had no last_price — fetching via getQuote...');
                $fallback = $this->fetchAllLTPs($kite, array_values($missing));
                $ltpMap   = array_merge($ltpMap, $fallback);
            }

            $this->info('  📡 LTP resolved for ' . count($ltpMap) . ' instrument(s).');

            $today      = Carbon::today();
            $prevDay    = BrokerStopLossConfig::getPreviousTradingDay($today);

            // ── 5. Match each position to a config and decide SL ─────────
            foreach ($livePositions as $pos) {
                $symbol = $pos['tradingsymbol'];

                // Symbol filter (--symbol option)
                if ($this->option('symbol') && $this->option('symbol') !== $symbol) {
                    continue;
                }

                // Only BUY (net long) positions
                if ((int)$pos['quantity'] <= 0) {
                    $stats['positions_skipped']++;
                    continue;
                }

                $symbolType = BrokerStopLossConfig::extractSymbolType($symbol);
                if (!$symbolType) {
                    // Not an option — skip
                    $stats['positions_skipped']++;
                    continue;
                }

                // --symbol_type filter
                if ($this->option('symbol_type')) {
                    $filter = strtoupper($this->option('symbol_type'));
                    if ($filter !== 'BOTH' && $filter !== $symbolType) {
                        $stats['positions_skipped']++;
                        continue;
                    }
                }

                // Find matching config
                $config = $configs[$symbolType] ?? $configs['BOTH'] ?? null;
                if (!$config) {
                    $this->line("  ⏭️  No config for {$symbol} (type={$symbolType}) — skipping.");
                    $stats['positions_skipped']++;
                    continue;
                }

                // --config_id filter (run single config)
                if ($this->option('config_id') && (int)$this->option('config_id') !== $config->id) {
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Fresh vs Old ────────────────────────────────────────
                $purchaseDate = Carbon::parse($pos['purchase_date'] ?? now());
                $isFresh      = BrokerStopLossConfig::isFreshPosition($purchaseDate, $today);

                if ($isFresh && $config->skip_fresh_positions) {
                    $this->line("  ⏭️  Skipping FRESH {$symbol} (skip_fresh enabled).");
                    $stats['positions_skipped']++;
                    continue;
                }
                if (!$isFresh && $config->skip_old_positions) {
                    $this->line("  ⏭️  Skipping OLD {$symbol} (skip_old enabled).");
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Look up LTP from the pre-fetched batch map ──────────
                $ltp = $ltpMap[$symbol] ?? null;

                if ($ltp === null) {
                    $this->warn("  ⚠️  No LTP in batch for {$symbol} — skipping.");
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Position filter: PROFIT / LOSS / BOTH ───────────────
                // Use pnl from Zerodha position data as the source of truth —
                // more reliable than comparing LTP vs AVG (avoids floating-point
                // edge cases and stale price issues).
                $avgPrice  = (float)($pos['average_price'] ?? 0);
                $posPnl    = (float)($pos['pnl']           ?? 0);
                $isProfit  = $posPnl > 0;
                $isLoss    = $posPnl < 0;

                if ($config->position_filter === 'PROFIT' && !$isProfit) {
                    $state = $isLoss ? 'LOSS' : 'FLAT';
                    $this->line("  ⏭️  {$symbol}: filter=PROFIT but position is {$state} (AVG ₹{$avgPrice} / LTP ₹{$ltp} / P&L ₹{$posPnl}) — skip.");
                    $stats['positions_skipped']++;
                    continue;
                }

                if ($config->position_filter === 'LOSS' && !$isLoss) {
                    $state = $isProfit ? 'PROFIT' : 'FLAT';
                    $this->line("  ⏭️  {$symbol}: filter=LOSS but position is {$state} (AVG ₹{$avgPrice} / LTP ₹{$ltp} / P&L ₹{$posPnl}) — skip.");
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Base price is ALWAYS avg entry price ─────────────────
                // This is a disaster fail-safe SL — it must anchor to your
                // ENTRY price, not LTP. If we used LTP, a position that ran
                // from ₹100 → ₹200 would place SL at ₹190, which is a
                // profit-lock / trailing behavior — NOT what we want here.
                //
                // Rule: SL = avgPrice × (1 + stop_loss_percent/100)
                // e.g.  AVG=₹100, SL=-5% → trigger placed at ₹95 ALWAYS.
                // LTP can be ₹200 or ₹50 — trigger stays at ₹95.
                // price_type config is intentionally ignored here.
                if ($avgPrice <= 0) {
                    $this->warn("  ⚠️  AVG price is 0 for {$symbol} — skipping.");
                    $stats['positions_skipped']++;
                    continue;
                }

                $slPrice = $avgPrice * (1 + ($config->stop_loss_percent / 100));
                $slPrice = max(0.05, $slPrice); // floor at min tick

                // ── Guard 1: Illiquid option (LTP too low, no real market) ──
                if ($ltp < 0.5) {
                    $this->line("  ⏭️  {$symbol}: LTP ₹{$ltp} too low — illiquid option, skipping SL.");
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Guard 2: Product type — SL-M only valid for MIS/NRML ──
                $posProduct = $pos['product'] ?? '';
                if (!in_array($posProduct, ['MIS', 'NRML'])) {
                    $this->warn("  ⚠️  {$symbol}: Product {$posProduct} not supported for SL-M — skipping.");
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Guard 3: Trigger >= LTP → Zerodha rejects SL-M ────────
                if ($slPrice >= $ltp) {
                    $this->warn(
                        "  ⚠️  {$symbol}: SL trigger ₹" . round($slPrice, 2) .
                        " >= LTP ₹{$ltp} — Zerodha would reject. Use a more negative SL%."
                    );
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Guard 4: Trigger too close to LTP (< 1% gap) ──────────
                // Prevents near-instant trigger due to normal tick movement.
                // Dynamic min-gap: 1% of LTP, floored at ₹0.25.
                $minGap = max(0.25, round($ltp * 0.01, 2));
                if (($ltp - $slPrice) < $minGap) {
                    $this->warn(
                        "  ⚠️  {$symbol}: Trigger ₹" . round($slPrice, 2) .
                        " too close to LTP ₹{$ltp} (gap ₹" . round($ltp - $slPrice, 2) .
                        " < min ₹{$minGap}) — may trigger instantly. Skipping."
                    );
                    $stats['positions_skipped']++;
                    continue;
                }

                // ── Guard 5: Skip if a pending SL already exists ──────────
                // Scoped to broker_username so multi-broker users are not blocked:
                // DB0542+BIOCON and DB0542+BIOCON are independent SL orders.
                $slAlreadyExists = \App\Models\OrderBook::where('user_id', $broker->user_id)
                    ->where('broker_username', $broker->account_user_name)
                    ->where('trading_symbol', $symbol)
                    ->where('transaction_type', 'SELL')
                    ->whereIn('order_type', ['SL', 'SL-M'])
                    ->whereIn('status', ['PENDING', 'OPEN'])
                    ->whereDate('order_datetime', today())
                    ->exists();

                if ($slAlreadyExists) {
                    $this->line("  ⏭️  {$symbol} [{$broker->account_user_name}]: SL order already exists today — skipping duplicate.");
                    $stats['positions_skipped']++;
                    continue;
                }

                $this->line(
                    "  🛡️  {$symbol}: Entry(AVG)=₹{$avgPrice} | LTP=₹{$ltp}" .
                    " | SL trigger=₹" . round($slPrice, 2) .
                    " ({$config->stop_loss_percent}% of entry)" .
                    " | Gap=₹" . round($ltp - $slPrice, 2) .
                    " | Placing SL order..."
                );

                // ── Place SL (Stop-Loss Limit) order — sits idle on exchange ──
                //    Activates ONLY when LTP touches trigger_price downward.
                $stats['sl_triggered']++;

                $result = $this->placeSlOrder(
                    $kite, $broker, $pos, $config, $ltp, $slPrice
                );

                if ($result['success']) {
                    $stats['orders_placed'] += $result['orders_placed'];
                } else {
                    $stats['orders_failed']++;
                }

                sleep($this->rateLimitSeconds);
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Broker processing error: ' . $e->getMessage());
            Log::error("SL broker {$broker->id} error: " . $e->getMessage());
        }

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────
    // FETCH LIVE POSITIONS FROM ZERODHA
    // ─────────────────────────────────────────────────────────────

    /**
     * Always fetch from Zerodha API — never from our DB.
     * Returns the "net" positions array (only net open positions).
     */
    private function fetchLivePositions(KiteConnect $kite): array
    {
        try {
            $response = $kite->getPositions();

            // Zerodha returns { day: [...], net: [...] }
            // We want NET positions (actual open holdings)
            // Zerodha SDK returns stdClass — normalize to array first
            $response  = json_decode(json_encode($response), true);
            $positions = $response['net'] ?? [];

            // Filter: only actually open (net quantity > 0)
            return array_filter($positions, fn($p) => ((int)($p['quantity'] ?? 0)) > 0);

        } catch (\Exception $e) {
            $this->error('  ❌ Failed to fetch positions from Zerodha: ' . $e->getMessage());
            Log::error('SL fetchLivePositions error: ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PLACE SELL ORDER
    // ─────────────────────────────────────────────────────────────

    private function placeSlOrder(
        KiteConnect $kite,
        BrokerApi $broker,
        array $pos,
        BrokerStopLossConfig $config,
        float $ltp,
        float $slPrice
    ): array {
        try {
            $symbol       = $pos['tradingsymbol'];
            $exchange     = $pos['exchange'];
            $product      = $pos['product'];
            $totalQty     = (int)$pos['quantity'];
            $avgPrice     = (float)($pos['average_price'] ?? 0);

            // ── Quantity calculation ──────────────────────────────────────
            $lotSize    = FreezingQuantity::getLotSize($symbol);
            $rawSellQty = (int)floor(($totalQty * $config->quantity_percent) / 100);

            // At least 1 lot
            if ($lotSize > 0 && $rawSellQty < $lotSize) {
                $rawSellQty = $lotSize;
            }
            // Round down to lot-size multiple
            if ($lotSize > 0) {
                $rawSellQty = (int)(floor($rawSellQty / $lotSize) * $lotSize);
            }
            // Cannot sell more than available
            $sellQty = min($rawSellQty, $totalQty);

            if ($sellQty <= 0) {
                $this->warn("    ⚠️  Sell qty=0 for {$symbol}, skipping.");
                return ['success' => false, 'orders_placed' => 0];
            }

            // ── Round SL price to valid tick size ────────────────────────
            $triggerPrice = $this->roundToTickSize($symbol, $slPrice, $exchange);
            $triggerPrice = max(0.05, $triggerPrice);

            // Limit price = 2% below trigger, rounded to tick size.
            // Rule: limit_price MUST be <= trigger_price for SELL SL orders.
            // ₹0.05 gap is too small for options (fast moves = no fill).
            // 2% gives enough room to fill while capping max extra loss.
            // e.g. trigger=₹95 → limit=₹93.10 (fills between ₹93.10–₹95)
            $limitPrice = $this->roundToTickSize($symbol, $triggerPrice * 0.98, $exchange);
            $limitPrice = max(0.05, $limitPrice);

            $pnlOnSl = ($triggerPrice - $avgPrice) * $sellQty;

            if ($this->option('dry-run')) {
                $this->info(
                    "    [DRY-RUN] SL order for {$symbol}" .
                    " | Trigger ₹{$triggerPrice} | Limit ₹{$limitPrice}" .
                    " | Qty {$sellQty}/{$totalQty} ({$config->quantity_percent}%)" .
                    " | Est P&L if triggered: ₹" . round($pnlOnSl, 2)
                );
                return ['success' => true, 'orders_placed' => 1];
            }

            // ── Split into chunks (freeze limit) ─────────────────────────
            $chunks    = FreezingQuantity::getChunkSizes($symbol, $sellQty);
            $numChunks = count($chunks);

            if ($numChunks > 1) {
                $this->info("    📦 Splitting into {$numChunks} chunk(s) (freeze limit)");
            }

            $placedOrders = 0;

            foreach ($chunks as $idx => $chunkQty) {
                // ── SL (Stop-Loss Limit) order ────────────────────────────
                // SL-M is blocked for stock options on Zerodha.
                // SL (limit) is the correct type:
                //   trigger_price = level at which order activates
                //   price         = limit price at which sell executes
                // Exchange holds it idle; activates ONLY when LTP hits trigger.
                $orderParams = [
                    'exchange'         => $exchange,
                    'tradingsymbol'    => $symbol,
                    'transaction_type' => 'SELL',
                    'quantity'         => $chunkQty,
                    'product'          => $product,
                    'order_type'       => 'SL',
                    'trigger_price'    => $triggerPrice,
                    'price'            => $limitPrice,
                    'validity'         => 'DAY',
                ];

                try {
                    $result = $kite->placeOrder('regular', $orderParams);

                    if (isset($result->order_id)) {
                        $placedOrders++;
                        $this->info(
                            "    ✅ SL order #{$placedOrders}: {$chunkQty} qty" .
                            " | Trigger ₹{$triggerPrice} | Limit ₹{$limitPrice}" .
                            " | Sits idle until trigger hit | Order: {$result->order_id}"
                        );
                        $this->saveOrder($broker, $result->order_id, $orderParams, $config->stop_loss_percent, $config->symbol_type);
                    }

                } catch (\Exception $e) {
                    $this->error("    ❌ Chunk #" . ($idx + 1) . " failed: " . $e->getMessage());
                    $this->saveFailedOrder($broker, $pos, $e->getMessage(), $chunkQty);
                }

                if ($idx < count($chunks) - 1) {
                    sleep($this->rateLimitSeconds);
                }
            }

            if ($placedOrders > 0) {
                return ['success' => true, 'orders_placed' => $placedOrders];
            }

            throw new \Exception('No chunks placed successfully.');

        } catch (\Exception $e) {
            $this->error("    ❌ SL SELL failed for {$pos['tradingsymbol']}: " . $e->getMessage());
            Log::error("SL sell failed for {$pos['tradingsymbol']}: " . $e->getMessage());
            $this->saveFailedOrder($broker, $pos, $e->getMessage());
            return ['success' => false, 'orders_placed' => 0];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function loadConfigs(int $brokerId)
    {
        $query = BrokerStopLossConfig::where('broker_api_id', $brokerId)
            ->where('is_active', true);

        if ($this->option('config_id')) {
            $query->where('id', $this->option('config_id'));
        }

        return $query->get()->keyBy('symbol_type');
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

    /**
     * Build LTP map directly from the last_price field in Zerodha positions response.
     * getPositions() already returns last_price for each instrument — this is the
     * most accurate and zero-API-call approach.
     *
     * Also uses pnl + average_price to cross-verify: if last_price == average_price
     * AND pnl != 0, we trust pnl-derived LTP instead (handles cases where Zerodha
     * returns stale last_price equal to avg for illiquid instruments).
     *
     * Returns: ['SYMBOL' => ltp_float] — only for instruments with valid LTP > 0
     */
    private function buildLtpMapFromPositions(array $positions): array
    {
        $ltpMap = [];

        foreach ($positions as $pos) {
            $symbol   = $pos['tradingsymbol'] ?? null;
            $lastPrice = (float)($pos['last_price'] ?? 0);
            $avgPrice  = (float)($pos['average_price'] ?? 0);
            $qty       = (int)($pos['quantity'] ?? 0);
            $pnl       = (float)($pos['pnl'] ?? 0);

            if (!($symbol)) continue;

            // Primary: use last_price if it looks genuine
            // A suspicious case: last_price == average_price but pnl != 0
            // (Zerodha sometimes returns avg as last_price for illiquid options)
            $ltpSeemsStale = ($lastPrice == $avgPrice) && ($pnl != 0) && ($qty > 0);

            if ($lastPrice > 0 && !$ltpSeemsStale) {
                $ltpMap[$symbol] = $lastPrice;
                continue;
            }

            // Fallback: derive LTP from pnl if possible
            // pnl = (last_price - avg_price) * qty  →  last_price = avg + pnl/qty
            if ($qty > 0 && $avgPrice > 0) {
                $derived = $avgPrice + ($pnl / $qty);
                if ($derived > 0) {
                    $ltpMap[$symbol] = round($derived, 2);
                    continue;
                }
            }

            // Could not determine LTP — will fall back to getQuote for this symbol
        }

        return $ltpMap;
    }

    /**
     * Fetch LTPs for ALL open positions in ONE batch getQuote call.
     * Zerodha allows up to 500 instruments per call — well within our limit.
     * Returns: ['SYMBOL' => ltp_float]
     */
    private function fetchAllLTPs(KiteConnect $kite, array $positions): array
    {
        try {
            // Build quote keys: ["NFO:NIFTY24FEB22000CE", "NSE:RELIANCE", ...]
            $keys = [];
            foreach ($positions as $pos) {
                if (!empty($pos['exchange']) && !empty($pos['tradingsymbol'])) {
                    $keys[] = $pos['exchange'] . ':' . $pos['tradingsymbol'];
                }
            }

            if (empty($keys)) return [];

            // Zerodha allows max 500 per call; chunk just in case
            $ltpMap = [];
            foreach (array_chunk($keys, 500) as $chunk) {
                $quotes = $kite->getQuote($chunk);
                $quotes = json_decode(json_encode($quotes), true); // stdClass → array

                foreach ($quotes as $key => $data) {
                    $symbol = explode(':', $key)[1] ?? $key;
                    $ltp    = $data['last_price'] ?? null;
                    if ($ltp !== null && (float)$ltp > 0) {
                        $ltpMap[$symbol] = (float)$ltp;
                    }
                }
            }

            return $ltpMap;

        } catch (\Exception $e) {
            $this->error('  ❌ Batch LTP fetch failed: ' . $e->getMessage());
            Log::error('SL fetchAllLTPs error: ' . $e->getMessage());
            return [];
        }
    }

    private function roundToTickSize(string $symbol, float $price, string $exchange): float
    {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $symbol)
                ->where('exchange', $exchange)
                ->first();

            if ($instrument && $instrument->tick_size > 0) {
                return round($price / $instrument->tick_size) * $instrument->tick_size;
            }

            if (in_array($exchange, ['NFO', 'BFO'])) {
                return round($price / 0.05) * 0.05;
            }

            return round($price, 2);

        } catch (\Exception $e) {
            return round($price, 2);
        }
    }

    private function saveOrder(BrokerApi $broker, string $orderId, array $params, float $slPct, string $symbolType): void
    {
        try {
            $label = $slPct < 0
                ? "SL {$symbolType} ({$slPct}% stop-loss)"
                : "SL {$symbolType} ({$slPct}% trailing lock)";

            OrderBook::create([
                'user_id'          => $broker->user_id,
                'broker_username'  => $broker->account_user_name,
                'order_id'         => $orderId,
                'status'           => 'PENDING',
                'trading_symbol'   => $params['tradingsymbol'],
                'order_type'       => $params['order_type'],
                'transaction_type' => $params['transaction_type'],
                'product'          => $params['product'],
                'price'            => $params['trigger_price'] ?? $params['price'] ?? '-',
                'quantity'         => $params['quantity'],
                'status_message'   => $label,
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('SL saveOrder error: ' . $e->getMessage());
        }
    }

    private function saveFailedOrder(BrokerApi $broker, array $pos, string $error, ?int $qty = null): void
    {
        try {
            OrderBook::create([
                'user_id'          => $broker->user_id,
                'broker_username'  => $broker->account_user_name,
                'order_id'         => '-',
                'status'           => 'FAILED',
                'trading_symbol'   => $pos['tradingsymbol'],
                'order_type'       => 'LIMIT',
                'transaction_type' => 'SELL',
                'product'          => $pos['product'],
                'price'            => '-',
                'quantity'         => $qty ?? abs((int)$pos['quantity']),
                'status_message'   => 'SL failed: ' . substr($error, 0, 450),
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('SL saveFailedOrder error: ' . $e->getMessage());
        }
    }

    private function displaySummary(array $totals): void
    {
        $this->info("\n" . str_repeat('=', 60));
        $this->info('🛑  STOP LOSS ORDERS — SUMMARY');
        $this->info(str_repeat('=', 60));
        $this->info('Brokers processed : ' . $totals['brokers']);
        $this->info('Positions seen    : ' . $totals['positions_seen']);
        $this->info('SL orders attempted : ' . $totals['sl_triggered']);
        $this->info('Orders placed     : ' . $totals['orders_placed']);
        $this->info('Orders failed     : ' . $totals['orders_failed']);
        $this->info('Positions skipped : ' . $totals['positions_skipped']);
        $this->info(str_repeat('=', 60));
    }
}