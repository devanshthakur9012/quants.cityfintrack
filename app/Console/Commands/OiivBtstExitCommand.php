<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\OiivBtstConfig;
use App\Models\OiivOrderBook;
use App\Models\OiivPosition;
use App\Models\ZerodhaInstrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * OiivBtstExitCommand
 *
 * Manages BTST (Buy Today Sell Tomorrow) exits for OIIV positions.
 *
 * Uses EXISTING tables only:
 *   - oiiv_positions    → reads open positions
 *   - oiiv_order_book   → writes SL/Profit/Sweep SELL orders (same table as BUY)
 *   - oiiv_btst_configs → reads user configuration
 *
 * SIGNAL TYPES written to oiiv_order_book:
 *   BTST_SL       → SL-L order placed at 9:15 AM (sits idle on exchange)
 *   BTST_PROFIT   → Limit SELL at profit target, placed at 9:15 AM
 *   BTST_SWEEP    → Limit SELL at LTP, placed at 10:00 AM sweep
 *   BTST_COSTCOST → Limit SELL at AVG (cost-to-cost), for old positions
 *
 * SCHEDULER (add to Kernel.php):
 *   $schedule->command('oiiv:btst-exit --phase=9am')
 *            ->weekdays()->at('09:15')->timezone('Asia/Kolkata')
 *            ->withoutOverlapping(5)->runInBackground();
 *
 *   $schedule->command('oiiv:btst-exit --phase=10am')
 *            ->weekdays()->at('10:00')->timezone('Asia/Kolkata')
 *            ->withoutOverlapping(5)->runInBackground();
 */
class OiivBtstExitCommand extends Command
{
    protected $signature = 'oiiv:btst-exit
                            {--phase=9am    : 9am | 10am}
                            {--broker_id=   : Run only for this broker}
                            {--dry-run      : Log actions, do NOT place orders}
                            {--force        : Skip market-hours check}';

    protected $description = 'BTST exit: place SL+profit orders at 9:15, sweep at 10:00';

    private const RATE_MS    = 350;
    private const CHUNK_SLEEP = 1;

    private array $kite     = [];
    private array $lastCall = [];

    // =========================================================
    //  ENTRY
    // =========================================================

    public function handle(): int
    {
        $phase = $this->option('phase');
        $isDry = (bool) $this->option('dry-run');
        $now   = Carbon::now('Asia/Kolkata');

        $this->info(sprintf(
            '[oiiv:btst-exit] Phase=%s | %s | DryRun=%s',
            $phase, $now->format('Y-m-d H:i:s'), $isDry ? 'YES' : 'NO'
        ));

        if (!$isDry && !$this->option('force')) {
            $t = $now->hour * 60 + $now->minute;
            if ($t < 9 * 60 + 10 || $t > 15 * 60 + 35) {
                $this->warn('Outside market hours. Use --force to override.');
                return self::FAILURE;
            }
        }

        $brokers = $this->getBrokers();
        if ($brokers->isEmpty()) {
            $this->warn('No active brokers found.');
            return self::SUCCESS;
        }

        foreach ($brokers as $broker) {
            $this->info("\n── Broker: {$broker->client_name} ({$broker->account_user_name})");
            try {
                if ($phase === '9am') {
                    $this->phase9am($broker, $isDry);
                } elseif ($phase === '10am') {
                    $this->phase10am($broker, $isDry);
                } else {
                    $this->error("Unknown phase '{$phase}'. Use 9am or 10am.");
                    return self::FAILURE;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ {$e->getMessage()}");
                Log::error("[OiivBtstExit] Broker {$broker->id}: " . $e->getMessage());
            }
        }

        $this->info('[oiiv:btst-exit] Completed.');
        return self::SUCCESS;
    }

    // =========================================================
    //  PHASE 9 AM — SL + Profit orders
    // =========================================================

    private function phase9am(BrokerApi $broker, bool $isDry): void
    {
        $config = $this->getConfig($broker->id);
        if (!$config) { $this->warn('  No active BTST config — skip.'); return; }

        $positions = $this->openPositions($broker->id);
        if ($positions->isEmpty()) { $this->warn('  No open OIIV positions.'); return; }

        $this->info("  {$positions->count()} open position(s).");

        // ── ONE batch LTP call ─────────────────────────────────────────────
        $ltpMap = $this->batchLtps($broker, $positions);

        $today = now()->toDateString();

        foreach ($positions as $pos) {
            $sym      = $pos->trading_symbol;
            $avgEntry = (float) $pos->entry_price;
            $ltp      = $ltpMap[$sym] ?? null;

            if ($ltp === null || $ltp <= 0 || $avgEntry <= 0) {
                $this->warn("  ⚠ {$sym}: no LTP or zero entry — skip.");
                continue;
            }

            // Skip if already placed 9AM orders today for this position
            if ($this->already9amPlaced($broker->id, $sym, $today)) {
                $this->line("  ⏭ {$sym}: 9AM orders already done — skip.");
                continue;
            }

            $isFresh   = OiivBtstConfig::isFresh($pos->signal_date ?? Carbon::yesterday()->toDateString());
            $profitPct = (($ltp - $avgEntry) / $avgEntry) * 100;
            $qty       = (int) ($pos->quantity_units ?? ($pos->quantity * ($pos->lot_size ?? 1)));

            $this->info(sprintf(
                "  %s | Entry ₹%.2f | LTP ₹%.2f | P/L %.2f%% | %s",
                $sym, $avgEntry, $ltp, $profitPct, $isFresh ? 'FRESH' : 'OLD'
            ));

            $inst = $this->instrument($sym, $pos->exchange);

            // ── a) SL order ────────────────────────────────────────────────
            $slPct     = $isFresh ? (float)$config->sl_percent : (float)$config->old_position_sl_percent;
            $slTrigger = $this->tick($config->slTrigger($avgEntry), $inst);
            $slLimit   = $this->tick($slTrigger * 0.97, $inst);   // 3% below trigger for fill room
            $slLimit   = max(0.05, $slLimit);

            if ($slTrigger < $ltp) {
                $this->placeSell(
                    broker: $broker,
                    pos: $pos,
                    kite: $this->kite($broker),
                    qty: $qty,
                    orderType: 'SL',
                    price: $slLimit,
                    triggerPrice: $slTrigger,
                    signalType: 'BTST_SL',
                    ltp: $ltp,
                    avgEntry: $avgEntry,
                    isDry: $isDry
                );
            } else {
                $this->warn("  ⚠ {$sym}: SL trigger ₹{$slTrigger} >= LTP ₹{$ltp} — SL skipped.");
            }

            // ── b) Profit-booking LIMIT order ──────────────────────────────
            if ($isFresh) {
                $configTarget = $config->profitTarget($avgEntry);
                // If LTP already above target → book at LTP (capture actual gain)
                $sellPrice = ($ltp >= $configTarget) ? $ltp : $configTarget;
                $sellPrice = $this->tick($sellPrice, $inst);

                $this->placeSell(
                    broker: $broker,
                    pos: $pos,
                    kite: $this->kite($broker),
                    qty: $qty,
                    orderType: 'LIMIT',
                    price: $sellPrice,
                    triggerPrice: null,
                    signalType: 'BTST_PROFIT',
                    ltp: $ltp,
                    avgEntry: $avgEntry,
                    isDry: $isDry
                );
            } else {
                // Old position: cost-to-cost if loss, LTP if in profit
                $sellPrice = $profitPct > 0
                    ? $this->tick($ltp, $inst)
                    : $this->tick($avgEntry, $inst);

                $signalType = $profitPct > 0 ? 'BTST_PROFIT' : 'BTST_COSTCOST';

                $this->placeSell(
                    broker: $broker,
                    pos: $pos,
                    kite: $this->kite($broker),
                    qty: $qty,
                    orderType: 'LIMIT',
                    price: $sellPrice,
                    triggerPrice: null,
                    signalType: $signalType,
                    ltp: $ltp,
                    avgEntry: $avgEntry,
                    isDry: $isDry
                );
            }

            $this->throttle($broker->id);
        }
    }

    // =========================================================
    //  PHASE 10 AM — sweep remaining open positions
    // =========================================================

    private function phase10am(BrokerApi $broker, bool $isDry): void
    {
        $config = $this->getConfig($broker->id);
        if (!$config || !$config->enable_10am_sweep) {
            $this->warn('  10AM sweep disabled — skip.');
            return;
        }

        $positions = $this->openPositions($broker->id);
        if ($positions->isEmpty()) {
            $this->info('  No open positions at 10AM — all closed!');
            return;
        }

        $this->info("  {$positions->count()} position(s) still open at 10AM.");
        $ltpMap  = $this->batchLtps($broker, $positions);
        $kite    = $this->kite($broker);
        $today   = now()->toDateString();
        $minProf = (float) $config->min_profit_percent;

        foreach ($positions as $pos) {
            $sym      = $pos->trading_symbol;
            $ltp      = $ltpMap[$sym] ?? null;
            $avgEntry = (float) $pos->entry_price;

            if ($ltp === null || $ltp <= 0 || $avgEntry <= 0) continue;

            $profitPct = (($ltp - $avgEntry) / $avgEntry) * 100;
            $qty       = (int) ($pos->quantity_units ?? ($pos->quantity * ($pos->lot_size ?? 1)));
            $inst      = $this->instrument($sym, $pos->exchange);
            $isFresh   = OiivBtstConfig::isFresh($pos->signal_date ?? Carbon::yesterday()->toDateString());

            $this->info(sprintf(
                "  %s | Entry ₹%.2f | LTP ₹%.2f | P/L %.2f%%",
                $sym, $avgEntry, $ltp, $profitPct
            ));

            // Find existing open profit/sweep order for this position today
            $existingOrder = OiivOrderBook::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $sym)
                ->where('transaction_type', 'SELL')
                ->whereIn('signal_type', ['BTST_PROFIT', 'BTST_SWEEP', 'BTST_COSTCOST'])
                ->whereDate('created_at', $today)
                ->whereIn('status', [OiivOrderBook::STATUS_OPEN, OiivOrderBook::STATUS_TRIGGER_PENDING])
                ->whereNotNull('zerodha_order_id')
                ->latest()
                ->first();

            if ($profitPct >= $minProf) {
                // In profit → modify existing order to LTP, or place new
                $newPrice = $this->tick($ltp, $inst);

                if ($existingOrder) {
                    $this->modifyOrder($broker, $kite, $existingOrder, $newPrice, $isDry);
                } else {
                    $this->placeSell(
                        broker: $broker,
                        pos: $pos,
                        kite: $kite,
                        qty: $qty,
                        orderType: 'LIMIT',
                        price: $newPrice,
                        triggerPrice: null,
                        signalType: 'BTST_SWEEP',
                        ltp: $ltp,
                        avgEntry: $avgEntry,
                        isDry: $isDry
                    );
                }
            } else {
                // Not meeting min profit threshold
                if ($isFresh) {
                    $this->line("  {$sym}: profit {$profitPct}% < min {$minProf}% — SL stays on exchange.");
                } else {
                    // Old position → cost-to-cost regardless
                    $costPrice = $this->tick($avgEntry, $inst);
                    if ($existingOrder) {
                        $this->modifyOrder($broker, $kite, $existingOrder, $costPrice, $isDry);
                    } else {
                        $this->placeSell(
                            broker: $broker,
                            pos: $pos,
                            kite: $kite,
                            qty: $qty,
                            orderType: 'LIMIT',
                            price: $costPrice,
                            triggerPrice: null,
                            signalType: 'BTST_COSTCOST',
                            ltp: $ltp,
                            avgEntry: $avgEntry,
                            isDry: $isDry
                        );
                    }
                }
            }

            $this->throttle($broker->id);
        }
    }

    // =========================================================
    //  PLACE SELL — writes to oiiv_order_book (same as BUY)
    // =========================================================

    private function placeSell(
        BrokerApi $broker,
        OiivPosition $pos,
        KiteConnect $kite,
        int $qty,
        string $orderType,
        float $price,
        ?float $triggerPrice,
        string $signalType,
        float $ltp,
        float $avgEntry,
        bool $isDry
    ): void {
        $sym    = $pos->trading_symbol;
        $prefix = $isDry ? '[DRY]' : '';

        $typeLabel = match($signalType) {
            'BTST_SL'       => '🛑 SL-L',
            'BTST_PROFIT'   => '💰 Profit',
            'BTST_SWEEP'    => '🧹 Sweep',
            'BTST_COSTCOST' => '🔄 Cost2Cost',
            default         => $signalType,
        };

        $this->info(sprintf(
            "  %s %s | %s | ₹%.2f%s | Qty %d",
            $prefix, $typeLabel, $sym, $price,
            $triggerPrice ? " (trigger ₹{$triggerPrice})" : '',
            $qty
        ));

        if ($isDry) {
            $this->saveOrderBook($broker, $pos, $orderType, $price, $triggerPrice, $qty, $signalType, null, 'DRY_RUN', $ltp, $avgEntry);
            return;
        }

        $chunks = $this->freezeChunks($sym, $qty);

        foreach ($chunks as $i => $chunk) {
            $params = [
                'exchange'         => $pos->exchange,
                'tradingsymbol'    => $sym,
                'transaction_type' => 'SELL',
                'quantity'         => $chunk,
                'product'          => $pos->product,
                'order_type'       => $orderType,
                'price'            => $price,
                'validity'         => 'DAY',
            ];
            if ($triggerPrice) {
                $params['trigger_price'] = $triggerPrice;
            }

            try {
                $this->throttle($broker->id);
                $result  = $kite->placeOrder('regular', $params);
                $orderId = $result->order_id ?? null;
                $this->info("    ✅ {$orderId}");
                $this->saveOrderBook($broker, $pos, $orderType, $price, $triggerPrice, $chunk, $signalType, $orderId, OiivOrderBook::STATUS_OPEN, $ltp, $avgEntry);
            } catch (\Exception $e) {
                $this->error("    ✗ Chunk " . ($i + 1) . ": " . $e->getMessage());
                Log::error("[OiivBtst] placeSell {$sym}: " . $e->getMessage());
                $this->saveOrderBook($broker, $pos, $orderType, $price, $triggerPrice, $chunk, $signalType, null, OiivOrderBook::STATUS_REJECTED, $ltp, $avgEntry, $e->getMessage());
            }

            if ($i < count($chunks) - 1) sleep(self::CHUNK_SLEEP);
        }
    }

    // =========================================================
    //  MODIFY — uses existing modifyOrder on Zerodha
    // =========================================================

    private function modifyOrder(
        BrokerApi $broker,
        KiteConnect $kite,
        OiivOrderBook $order,
        float $newPrice,
        bool $isDry
    ): void {
        $this->info(sprintf(
            "  ✏ Modify %s | ₹%.2f → ₹%.2f",
            $order->trading_symbol, $order->placed_price ?? 0, $newPrice
        ));

        if ($isDry) {
            $order->recordModification($newPrice, 'BTST_SWEEP');
            return;
        }

        try {
            $this->throttle($broker->id);
            // Correct KiteConnect signature: modifyOrder($variety, $orderId, $params)
            $kite->modifyOrder('regular', $order->zerodha_order_id, [
                'price'      => $newPrice,
                'order_type' => 'LIMIT',
                'quantity'   => $order->quantity_units,
                'validity'   => 'DAY',
            ]);

            $order->recordModification($newPrice, 'BTST_SWEEP');
            $this->info("    ✅ Modified to ₹{$newPrice}");

        } catch (\Exception $e) {
            $this->error("    ✗ Modify failed: " . $e->getMessage());
            Log::error("[OiivBtst] modifyOrder {$order->trading_symbol}: " . $e->getMessage());
        }
    }

    // =========================================================
    //  WRITE TO oiiv_order_book (existing table)
    // =========================================================

    private function saveOrderBook(
        BrokerApi $broker,
        OiivPosition $pos,
        string $orderType,
        float $price,
        ?float $triggerPrice,
        int $qty,
        string $signalType,
        ?string $zerodhaOrderId,
        string $status,
        float $ltp,
        float $avgEntry,
        ?string $errorMsg = null
    ): void {
        try {
            OiivOrderBook::create([
                'user_id'              => $broker->user_id,
                'broker_api_id'        => $broker->id,
                'oiiv_auto_order_id'   => $pos->oiiv_auto_order_id,
                'zerodha_order_id'     => $zerodhaOrderId,
                'trading_symbol'       => $pos->trading_symbol,
                'base_symbol'          => $pos->base_symbol,
                'exchange'             => $pos->exchange,
                'option_type'          => $pos->option_type,
                'strike_price'         => $pos->strike_price,
                'expiry_date'          => $pos->expiry_date,
                'instrument_token'     => $pos->instrument_token,
                'signal_date'          => $pos->signal_date,
                'signal_type'          => $signalType,
                'sentiment'            => $pos->sentiment,
                'transaction_type'     => 'SELL',
                'order_type'           => $orderType,
                'product'              => $pos->product,
                'validity'             => 'DAY',
                'quantity'             => (int) ceil($qty / ($pos->lot_size ?? 1)),
                'quantity_units'       => $qty,
                'lot_size'             => $pos->lot_size ?? 1,
                'trigger_price'        => $ltp,              // LTP at signal time (shown as Trigger in UI)
                'placed_price'         => $price,
                'original_placed_price'=> $price,
                'spot_price_at_signal' => $avgEntry,
                'status'               => $status,
                'status_message'       => $errorMsg ? substr($errorMsg, 0, 500) : null,
                'internal_status'      => $zerodhaOrderId ? OiivOrderBook::INT_PLACED : OiivOrderBook::INT_FAILED,
                'filled_quantity'      => 0,
                'lot_chunk_number'     => 1,
                'lot_chunk_total'      => 1,
                'placed_at'            => now(),
                'last_synced_at'       => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("[OiivBtst] saveOrderBook: " . $e->getMessage());
        }
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    private function already9amPlaced(int $brokerId, string $sym, string $today): bool
    {
        return OiivOrderBook::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $sym)
            ->where('transaction_type', 'SELL')
            ->whereIn('signal_type', ['BTST_SL', 'BTST_PROFIT', 'BTST_COSTCOST'])
            ->whereDate('created_at', $today)
            ->where('status', '!=', OiivOrderBook::STATUS_REJECTED)
            ->exists();
    }

    private function getConfig(int $brokerId): ?OiivBtstConfig
    {
        return OiivBtstConfig::where('broker_api_id', $brokerId)
            ->where('is_active', true)
            ->first();
    }

    private function openPositions(int $brokerId)
    {
        return OiivPosition::where('broker_api_id', $brokerId)
            ->where('status', OiivPosition::STATUS_OPEN)
            ->where('quantity', '>', 0)
            ->get();
    }

    private function getBrokers()
    {
        $q = BrokerApi::where('client_type', 'Zerodha')->where('is_token_valid', true);
        if ($bid = $this->option('broker_id')) $q->where('id', $bid);
        return $q->get();
    }

    private function batchLtps(BrokerApi $broker, $positions): array
    {
        try {
            $kite = $this->kite($broker);
            $keys = [];
            $map  = [];

            foreach ($positions as $p) {
                $key = "{$p->exchange}:{$p->trading_symbol}";
                $keys[]     = $key;
                $map[$key]  = $p->trading_symbol;
            }

            $ltpMap = [];
            foreach (array_chunk($keys, 500) as $chunk) {
                $this->throttle($broker->id);
                $quotes = json_decode(json_encode($kite->getQuote($chunk)), true);
                foreach ($chunk as $key) {
                    $ltp = (float) ($quotes[$key]['last_price'] ?? 0);
                    if ($ltp > 0) $ltpMap[$map[$key]] = $ltp;
                }
            }

            $this->info("  LTPs fetched: " . count($ltpMap));
            return $ltpMap;

        } catch (\Exception $e) {
            $this->error("  ✗ Batch LTP failed: " . $e->getMessage());
            Log::error("[OiivBtst] batchLtps: " . $e->getMessage());
            return [];
        }
    }

    private function instrument(string $sym, string $exch): ?ZerodhaInstrument
    {
        return ZerodhaInstrument::where('trading_symbol', $sym)->where('exchange', $exch)->first();
    }

    private function tick(float $price, ?ZerodhaInstrument $inst): float
    {
        $tick = ($inst && (float)$inst->tick_size > 0) ? (float)$inst->tick_size : 0.05;
        return max(0.05, round($price / $tick) * $tick);
    }

    private function freezeChunks(string $sym, int $qty): array
    {
        static $limits = [
            'NIFTY' => 18, 'BANKNIFTY' => 20, 'FINNIFTY' => 24, 'MIDCPNIFTY' => 24,
        ];
        $base  = preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $sym);
        $base  = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $base);
        $limit = $limits[$base] ?? null;

        if (!$limit || $qty <= $limit) return [$qty];

        $chunks = [];
        $rem    = $qty;
        while ($rem > 0) { $c = min($limit, $rem); $chunks[] = $c; $rem -= $c; }
        return $chunks;
    }

    private function kite(BrokerApi $broker): KiteConnect
    {
        if (!isset($this->kite[$broker->id])) {
            $k = new KiteConnect($broker->api_key);
            $k->setAccessToken($broker->access_token);
            $this->kite[$broker->id] = $k;
        }
        return $this->kite[$broker->id];
    }

    private function throttle(int $brokerId): void
    {
        if (isset($this->lastCall[$brokerId])) {
            $ms = (int)((microtime(true) - $this->lastCall[$brokerId]) * 1000);
            if ($ms < self::RATE_MS) usleep((self::RATE_MS - $ms) * 1000);
        }
        $this->lastCall[$brokerId] = microtime(true);
    }
}