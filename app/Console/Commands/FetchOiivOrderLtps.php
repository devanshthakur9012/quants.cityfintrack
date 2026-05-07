<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\OiivOrderBook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;

/**
 * FetchOiivOrderLtps
 *
 * Fetches live LTPs for all OPEN OIIV orders from Zerodha in a single
 * batch getQuote() call per broker — extremely fast.
 *
 * Run every 15 seconds during market hours via scheduler:
 *
 *   $schedule->command('oiiv:fetch-ltps')
 *            ->everyFifteenSeconds()
 *            ->weekdays()
 *            ->between('09:15', '15:35')
 *            ->timezone('Asia/Kolkata')
 *            ->withoutOverlapping(1)
 *            ->runInBackground();
 *
 * Or poll via AJAX from the UI (the controller has a /fetch-ltps endpoint).
 */
class FetchOiivOrderLtps extends Command
{
    protected $signature   = 'oiiv:fetch-ltps {--broker_id= : Fetch only for this broker}';
    protected $description = 'Batch-fetch live LTPs for all open OIIV orders and store in DB';

    public function handle(): int
    {
        $brokers = $this->getBrokers();

        if ($brokers->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($brokers as $broker) {
            try {
                $this->fetchForBroker($broker);
            } catch (\Exception $e) {
                Log::error("[FetchOiivOrderLtps] Broker {$broker->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Per-broker logic
    // ─────────────────────────────────────────────────────────────────

    private function fetchForBroker(BrokerApi $broker): void
    {
        if (!$broker->hasValidToken()) {
            return;
        }

        // ── 1. Load all OPEN orders for this broker ───────────────────
        $orders = OiivOrderBook::where('broker_api_id', $broker->id)
            ->whereIn('status', [
                OiivOrderBook::STATUS_OPEN,
                OiivOrderBook::STATUS_TRIGGER_PENDING,
            ])
            ->whereNotNull('trading_symbol')
            ->get(['id', 'trading_symbol', 'exchange']);

        if ($orders->isEmpty()) {
            return;
        }

        // ── 2. Build unique quote keys — ONE call to Zerodha ─────────
        $symbolToIds = [];   // exchange:symbol → [order_id, ...]
        foreach ($orders as $o) {
            $key = "{$o->exchange}:{$o->trading_symbol}";
            $symbolToIds[$key][] = $o->id;
        }

        $quoteKeys = array_keys($symbolToIds);

        // ── 3. Batch getQuote() — Zerodha allows up to 500 per call ──
        $kite = new KiteConnect($broker->api_key);
        $kite->setAccessToken($broker->access_token);

        $ltpMap = [];  // order_id → ltp

        foreach (array_chunk($quoteKeys, 500) as $chunk) {
            try {
                $quotes = $kite->getQuote($chunk);
                $quotes = json_decode(json_encode($quotes), true);

                foreach ($chunk as $key) {
                    $ltp = (float) ($quotes[$key]['last_price'] ?? 0);
                    if ($ltp > 0) {
                        foreach ($symbolToIds[$key] as $orderId) {
                            $ltpMap[$orderId] = $ltp;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[FetchOiivOrderLtps] quote chunk failed: " . $e->getMessage());
            }
        }

        if (empty($ltpMap)) {
            return;
        }

        // ── 4. Bulk UPDATE — single query per LTP value group ─────────
        //    Group order IDs by their LTP value so we do as few queries
        //    as possible (usually all options have different LTPs so we
        //    use a CASE WHEN for a single UPDATE statement).
        $now     = now()->toDateTimeString();
        $ids     = array_keys($ltpMap);
        $caseSQL = 'CASE id ';
        $bindings = [];

        foreach ($ltpMap as $orderId => $ltp) {
            $caseSQL   .= "WHEN ? THEN ? ";
            $bindings[] = $orderId;
            $bindings[] = $ltp;
        }

        $caseSQL .= 'END';
        $bindings[] = $now;            // for ltp_updated_at
        $bindings   = array_merge($bindings, $ids);   // for WHERE IN

        $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

        DB::update(
            "UPDATE oiiv_order_book
             SET current_ltp = {$caseSQL},
                 ltp_updated_at = ?
             WHERE id IN ({$inPlaceholders})",
            $bindings
        );

        $this->line(sprintf(
            '[oiiv:fetch-ltps] Broker %d — updated %d order LTPs',
            $broker->id, count($ltpMap)
        ));
    }

    // ─────────────────────────────────────────────────────────────────

    private function getBrokers()
    {
        $q = BrokerApi::where('client_type', 'Zerodha')
                      ->where('is_token_valid', true);

        if ($bid = $this->option('broker_id')) {
            $q->where('id', $bid);
        }

        return $q->get();
    }
}