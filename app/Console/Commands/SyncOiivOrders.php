<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\OiivOrderBook;
use App\Models\OiivPosition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * SyncOiivOrders
 *
 * Polls Zerodha for every live OIIV order and updates our DB.
 * Automatically creates / closes positions when orders complete.
 *
 * Run this every 30–60 seconds during market hours via the scheduler:
 *
 *   $schedule->command('oiiv:sync-orders')->everyMinute()
 *            ->weekdays()->between('09:15', '15:35')
 *            ->timezone('Asia/Kolkata')
 *            ->withoutOverlapping(2);
 *
 * Or call manually:  php artisan oiiv:sync-orders [--broker_id=X]
 */
class SyncOiivOrders extends Command
{
    protected $signature   = 'oiiv:sync-orders
                              {--broker_id= : Sync only this broker ID}
                              {--full       : Re-sync ALL orders today (not just live ones)}';

    protected $description = 'Sync OIIV order status from Zerodha and update positions';

    private array $kiteInstances = [];

    public function handle(): int
    {
        $this->info('[oiiv:sync-orders] Starting at ' . now()->format('H:i:s'));

        try {
            $brokers = $this->getBrokers();

            if ($brokers->isEmpty()) {
                $this->warn('No active brokers found.');
                return self::SUCCESS;
            }

            foreach ($brokers as $broker) {
                $this->line("  Broker: {$broker->client_name} (ID {$broker->id})");

                if (!$broker->hasValidToken()) {
                    $this->warn("    ⚠ Invalid token — skipping");
                    continue;
                }

                try {
                    $this->syncBroker($broker);
                } catch (\Exception $e) {
                    $this->error("    ✗ {$e->getMessage()}");
                    Log::error("[SyncOiivOrders] Broker {$broker->id}: " . $e->getMessage());
                }
            }

            $this->info('[oiiv:sync-orders] Done');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('[oiiv:sync-orders] Fatal: ' . $e->getMessage());
            Log::error('[SyncOiivOrders] Fatal: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    // =========================================================
    //  BROKER SYNC
    // =========================================================

    private function syncBroker(BrokerApi $broker): void
    {
        $kite = $this->kite($broker);

        // ── 1. Fetch today's orders from Zerodha in one call ─────────────
        $zerodhaOrders = $this->fetchZerodhaOrders($broker, $kite);

        if (empty($zerodhaOrders)) {
            $this->line("    No orders returned from Zerodha today.");
        }

        // ── 2. Build a lookup: zerodha_order_id → zerodha order object ────
        $zerodhaMap = [];
        foreach ($zerodhaOrders as $zo) {
            $id = $zo->order_id ?? null;
            if ($id) $zerodhaMap[$id] = $zo;
        }

        // ── 3. Load our orders that need syncing ──────────────────────────
        $query = OiivOrderBook::where('broker_api_id', $broker->id)
                              ->whereNotNull('zerodha_order_id');

        if ($this->option('full')) {
            $query->whereDate('created_at', today());
        } else {
            $query->needsSync();
        }

        $ourOrders = $query->get();
        $this->line("    Orders to sync: {$ourOrders->count()}");

        $completed = $cancelled = $updated = 0;

        foreach ($ourOrders as $order) {
            $zo = $zerodhaMap[$order->zerodha_order_id] ?? null;

            if (!$zo) {
                // Order not in today's list — might be from a prev day or already purged
                $order->update(['last_synced_at' => now()]);
                continue;
            }

            $prevStatus = $order->status;
            $order->applyZerodhaStatus($zo);

            if ($order->status !== $prevStatus) {
                $this->line("    [{$order->trading_symbol}] {$prevStatus} → {$order->status}");
            }

            if ($order->status === OiivOrderBook::STATUS_COMPLETE && $prevStatus !== OiivOrderBook::STATUS_COMPLETE) {
                $this->handleOrderCompleted($order);
                $completed++;
            } elseif ($order->status === OiivOrderBook::STATUS_CANCELLED && $prevStatus !== OiivOrderBook::STATUS_CANCELLED) {
                $cancelled++;
            } else {
                $updated++;
            }
        }

        $this->line("    ✓ Completed: {$completed} | Cancelled: {$cancelled} | Updated: {$updated}");

        // ── 4. Update live LTPs for open positions ────────────────────────
        $this->syncOpenPositionLTPs($broker, $kite);
    }

    // =========================================================
    //  ORDER COMPLETED → CREATE / UPDATE POSITION
    // =========================================================

    private function handleOrderCompleted(OiivOrderBook $order): void
    {
        $avgPrice = (float) $order->average_price;
        if ($avgPrice <= 0) {
            // Sometimes average_price arrives a few seconds late; use placed_price as fallback
            $avgPrice = (float) $order->placed_price;
        }

        if ($order->transaction_type === 'BUY') {
            $this->createOrUpdatePosition($order, $avgPrice);
        } elseif ($order->transaction_type === 'SELL') {
            $this->closePosition($order, $avgPrice);
        }
    }

    private function createOrUpdatePosition(OiivOrderBook $order, float $avgPrice): void
    {
        // Avoid duplicate if another chunk already created the position
        $existing = OiivPosition::where('broker_api_id', $order->broker_api_id)
            ->where('trading_symbol', $order->trading_symbol)
            ->where('signal_date', $order->signal_date)
            ->where('status', OiivPosition::STATUS_OPEN)
            ->first();

        if ($existing) {
            // Partial fill arrived for a later chunk — re-average entry price
            $totalUnits  = $existing->quantity_units + $order->filled_quantity;
            $newAvgEntry = $totalUnits > 0
                ? (($existing->entry_price * $existing->quantity_units) + ($avgPrice * $order->filled_quantity)) / $totalUnits
                : $avgPrice;

            $existing->update([
                'entry_price'    => round($newAvgEntry, 2),
                'quantity_units' => $totalUnits,
                'last_synced_at' => now(),
            ]);

            Log::info("[SyncOiivOrders] Position {$existing->id} ({$order->trading_symbol}) qty merged to {$totalUnits} units @ avg {$newAvgEntry}");
            return;
        }

        $unitsFilled = $order->filled_quantity > 0 ? $order->filled_quantity : $order->quantity_units;

        OiivPosition::create([
            'user_id'              => $order->user_id,
            'broker_api_id'        => $order->broker_api_id,
            'oiiv_auto_order_id'   => $order->oiiv_auto_order_id,
            'entry_order_book_id'  => $order->id,
            'trading_symbol'       => $order->trading_symbol,
            'base_symbol'          => $order->base_symbol,
            'exchange'             => $order->exchange,
            'option_type'          => $order->option_type,
            'strike_price'         => $order->strike_price,
            'expiry_date'          => $order->expiry_date,
            'instrument_token'     => $order->instrument_token,
            'signal_date'          => $order->signal_date,
            'signal_type'          => $order->signal_type,
            'sentiment'            => $order->sentiment,
            'oi_condition'         => $order->oi_condition,
            'spot_price_at_signal' => $order->spot_price_at_signal,
            'ce_oi_change_pct'     => $order->ce_oi_change_pct,
            'pe_oi_change_pct'     => $order->pe_oi_change_pct,
            'position_type'        => 'LONG',
            'product'              => $order->product,
            'quantity'             => $order->quantity,
            'quantity_units'       => $unitsFilled,
            'lot_size'             => $order->lot_size,
            'entry_price'          => $avgPrice,
            'entry_ltp_at_signal'  => $order->trigger_price,
            'entry_at'             => $order->filled_at ?? now(),
            'status'               => OiivPosition::STATUS_OPEN,
            'is_btst'              => true,
            'last_synced_at'       => now(),
        ]);

        Log::info("[SyncOiivOrders] Position CREATED for {$order->trading_symbol} @ ₹{$avgPrice} | order_id {$order->id}");
    }

    private function closePosition(OiivOrderBook $order, float $avgPrice): void
    {
        $position = OiivPosition::where('broker_api_id', $order->broker_api_id)
            ->where('trading_symbol', $order->trading_symbol)
            ->where('status', OiivPosition::STATUS_OPEN)
            ->orderByDesc('entry_at')
            ->first();

        if (!$position) {
            Log::warning("[SyncOiivOrders] SELL complete for {$order->trading_symbol} but no matching open position found");
            return;
        }

        $position->close($avgPrice, 'SYSTEM_SELL', $order->id);
        Log::info("[SyncOiivOrders] Position CLOSED {$position->id} ({$order->trading_symbol}) @ ₹{$avgPrice} | P&L ₹{$position->realized_pnl}");
    }

    // =========================================================
    //  LTP SYNC FOR OPEN POSITIONS
    // =========================================================

    private function syncOpenPositionLTPs(BrokerApi $broker, \KiteConnect\KiteConnect $kite): void
    {
        $openPositions = OiivPosition::where('broker_api_id', $broker->id)
            ->where('status', OiivPosition::STATUS_OPEN)
            ->whereNotNull('instrument_token')
            ->get();

        if ($openPositions->isEmpty()) return;

        // Build quote keys grouped by exchange
        $quoteKeys = $openPositions->map(fn($p) =>
            "{$p->exchange}:{$p->trading_symbol}"
        )->unique()->values()->toArray();

        // Batch in chunks of 500 (Zerodha limit)
        foreach (array_chunk($quoteKeys, 500) as $chunk) {
            try {
                $quotes = $kite->getQuote($chunk);
                $quotes = json_decode(json_encode($quotes), true);

                foreach ($openPositions as $pos) {
                    $key = "{$pos->exchange}:{$pos->trading_symbol}";
                    $ltp = (float) ($quotes[$key]['last_price'] ?? 0);
                    if ($ltp > 0) {
                        $pos->updateLtp($ltp);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[SyncOiivOrders] LTP batch failed: " . $e->getMessage());
            }
        }

        $this->line("    LTPs updated for {$openPositions->count()} open position(s)");
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    private function fetchZerodhaOrders(BrokerApi $broker, \KiteConnect\KiteConnect $kite): array
    {
        try {
            $orders = $kite->getOrders();
            return is_array($orders) ? $orders : (array) $orders;
        } catch (\Exception $e) {
            Log::error("[SyncOiivOrders] getOrders failed for broker {$broker->id}: " . $e->getMessage());
            return [];
        }
    }

    private function getBrokers()
    {
        $brokerId = $this->option('broker_id');

        $q = BrokerApi::where('client_type', 'Zerodha')
                      ->where('is_token_valid', true);

        if ($brokerId) $q->where('id', $brokerId);

        return $q->get();
    }

    private function kite(BrokerApi $broker): \KiteConnect\KiteConnect
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $k = new KiteConnect($broker->api_key);
            $k->setAccessToken($broker->access_token);
            $this->kiteInstances[$broker->id] = $k;
        }
        return $this->kiteInstances[$broker->id];
    }
}