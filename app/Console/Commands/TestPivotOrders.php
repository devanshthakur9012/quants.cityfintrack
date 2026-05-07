<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ThirtyMinOhlcData;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * TestPivotOrders
 *
 * 100% same logic as PlacePivotOrders EXCEPT:
 *   - Orders saved to new_pivot_orders table (kite_status = 'TEST')
 *   - NO real Zerodha API call ever made
 *   - Works on any date — even weekends / market closed days
 *   - Broker token is NOT validated (so no token needed for testing)
 *
 * Usage examples:
 *   php artisan pivot:test-orders
 *   php artisan pivot:test-orders --date=2026-03-03
 *   php artisan pivot:test-orders --date=2026-03-03 --slot=09:15
 *   php artisan pivot:test-orders --config=1
 *   php artisan pivot:test-orders --date=2026-03-03 --slot=09:15 --config=1
 *
 * After running, check results:
 *   SELECT * FROM new_pivot_orders WHERE kite_status = 'TEST' ORDER BY id DESC LIMIT 50;
 */
class TestPivotOrders extends Command
{
    protected $signature = 'pivot:test-orders
                            {--date=   : Trade date to test (Y-m-d). Default = today.}
                            {--slot=   : Candle slot time (H:i) e.g. 09:15. Default = last completed slot.}
                            {--config= : Test only a specific config ID.}';

    protected $description = '[TEST] Simulate pivot order placement — saves to DB, NO Zerodha API call.';

    private const INTERVALS = [
        '09:15','09:45','10:15','10:45','11:15','11:45',
        '12:15','12:45','13:15','13:45','14:15','14:45','15:15',
    ];

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        $slot = $this->option('slot') ?? $this->resolveSlot($date);

        if (!$slot) {
            // If no slot resolved (e.g. running before 09:46), default to latest available
            $slot = $this->getLatestAvailableSlot($date);
        }

        if (!$slot) {
            $this->warn("No slot resolved and no data found for date={$date}. Pass --slot=09:15 manually.");
            return 0;
        }

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════╗');
        $this->info('║         PIVOT ORDER TEST — NO ZERODHA CALLS          ║');
        $this->info('╚══════════════════════════════════════════════════════╝');
        $this->info("  Date : {$date}");
        $this->info("  Slot : {$slot}");
        $this->info('');

        // ── Load configs ──────────────────────────────────────────────────────
        $configQuery = NewPivotOrderConfig::query();

        if ($this->option('config')) {
            $configQuery->where('id', $this->option('config'));
        }
        // Note: we include INACTIVE configs too for testing purposes
        $configs = $configQuery->with('broker')->get();

        if ($configs->isEmpty()) {
            $this->warn('No configs found. Create one first at /pivot-signal/config');
            return 0;
        }

        $this->info("Found {$configs->count()} config(s) (includes inactive — testing all).");

        // ── Discover symbols with data ────────────────────────────────────────
        $symbols = ThirtyMinOhlcData::whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('base_symbol')
            ->distinct()
            ->pluck('base_symbol')
            ->toArray();

        if (empty($symbols)) {
            $this->warn("No 30-min data for date={$date} slot={$slot}.");
            $this->warn("Run: php artisan options:live-collect-30min --force-date={$date}");
            return 0;
        }

        $this->info('Symbols found: ' . implode(', ', $symbols));
        $this->info('');

        // ── Process ───────────────────────────────────────────────────────────
        $placed   = 0;
        $skipped  = 0;

        foreach ($configs as $config) {
            $brokerName = $config->broker->client_name ?? "Config #{$config->id}";
            $this->line("─── Config #{$config->id} | {$brokerName} | {$config->order_type}/{$config->product} ───");

            foreach ($symbols as $symbol) {
                $expiry = $this->getNearestExpiry($symbol, $date);
                if (!$expiry) {
                    $this->warn("  [{$symbol}] No expiry found. Skipping.");
                    continue;
                }

                $ceCandle = $this->getAtmCandle($symbol, 'CE', $expiry, $date, $slot);
                $peCandle = $this->getAtmCandle($symbol, 'PE', $expiry, $date, $slot);

                if (!$ceCandle && !$peCandle) {
                    $this->warn("  [{$symbol}] No ATM candle for slot {$slot}.");
                    continue;
                }

                $this->line("  [{$symbol}] Expiry: {$expiry}");

                // S1 CE
                if ($ceCandle && !empty($config->s1_ce_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($ceCandle);
                    $this->line("    CE  PP={$PP}  S1={$S1}  R1={$R1}");
                    foreach ($config->s1_ce_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price  = $config->applyDiscount($S1, $layer);
                        $result = $this->saveTestOrder($config, $ceCandle, 'CE', 'S1', $S1, $price, $layer, $idx+1, $date, $slot);
                        $result ? $placed++ : $skipped++;
                    }
                }

                // S1 PE
                if ($peCandle && !empty($config->s1_pe_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($peCandle);
                    $this->line("    PE  PP={$PP}  S1={$S1}  R1={$R1}");
                    foreach ($config->s1_pe_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price  = $config->applyDiscount($S1, $layer);
                        $result = $this->saveTestOrder($config, $peCandle, 'PE', 'S1', $S1, $price, $layer, $idx+1, $date, $slot);
                        $result ? $placed++ : $skipped++;
                    }
                }

                // R1 CE
                if ($ceCandle && !empty($config->r1_ce_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($ceCandle);
                    foreach ($config->r1_ce_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price  = $config->applyDiscount($R1, $layer);
                        $result = $this->saveTestOrder($config, $ceCandle, 'CE', 'R1', $R1, $price, $layer, $idx+1, $date, $slot);
                        $result ? $placed++ : $skipped++;
                    }
                }

                // R1 PE
                if ($peCandle && !empty($config->r1_pe_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($peCandle);
                    foreach ($config->r1_pe_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price  = $config->applyDiscount($R1, $layer);
                        $result = $this->saveTestOrder($config, $peCandle, 'PE', 'R1', $R1, $price, $layer, $idx+1, $date, $slot);
                        $result ? $placed++ : $skipped++;
                    }
                }
            }
        }

        $this->info('');
        $this->info("╔══════════════════════════════════════════╗");
        $this->info("║  TEST COMPLETE                           ║");
        $this->info("║  Saved to DB : {$placed}");
        $this->info("║  Skipped     : {$skipped} (already exist)");
        $this->info("╚══════════════════════════════════════════╝");
        $this->info("  Check DB: SELECT * FROM new_pivot_orders WHERE kite_status='TEST' ORDER BY id DESC;");
        $this->info('');

        return 0;
    }

    // ── Save test order to DB (no Kite call) ──────────────────────────────────

    private function saveTestOrder(
        NewPivotOrderConfig $config,
        object              $candle,
        string              $optionType,
        string              $level,
        float               $rawLevel,
        float               $orderPrice,
        array               $layer,
        int                 $layerNum,
        string              $date,
        string              $slot
    ): bool {

        $symbol     = $candle->base_symbol;
        $candleTime = "{$date} {$slot}:00";
        $qty        = (int)($layer['quantity'] ?? 0);

        // Idempotency: skip if test order already exists for same config+symbol+type+level+layer+slot
        $exists = NewPivotOrder::where('config_id',    $config->id)
            ->where('symbol',        $symbol)
            ->where('option_type',   $optionType)
            ->where('trigger_level', $level)
            ->where('layer_index',   $layerNum)
            ->where('candle_time',   $candleTime)
            ->where('kite_status',   'TEST')
            ->exists();

        if ($exists) {
            $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} → test order already exists. Skip.");
            return false;
        }

        NewPivotOrder::create([
            'user_id'          => $config->user_id,
            'config_id'        => $config->id,
            'broker_api_id'    => $config->broker_api_id,
            'symbol'           => $symbol,
            'option_symbol'    => $candle->trading_symbol,
            'option_token'     => $candle->instrument_token ?? null,
            'option_type'      => $optionType,
            'strike_price'     => $candle->strike,
            'trigger_level'    => $level,
            'layer_index'      => $layerNum,
            'transaction_type' => 'BUY',
            'raw_level_price'  => $rawLevel,
            'order_price'      => $orderPrice,
            'candle_time'      => $candleTime,
            'order_type'       => $config->order_type,
            'product'          => $config->product,
            'quantity'         => $qty,
            'kite_order_id'    => null,
            'kite_status'      => 'TEST',        // ← clearly marked as test
            'is_order_placed'  => false,          // ← not actually placed
            'order_placed_at'  => now(),
            'status'           => true,
        ]);

        $dir   = ($layer['discount_direction'] ?? 'negative') === 'positive' ? '+' : '-';
        $pct   = $layer['discount_pct'] ?? 0;
        $this->info("      ✓ TEST SAVED: [{$symbol}] {$optionType} {$level} L{$layerNum} | raw={$rawLevel} price={$orderPrice} ({$dir}{$pct}%) qty={$qty} | {$candle->trading_symbol}");

        return true;
    }

    // ── Slot resolution ───────────────────────────────────────────────────────

    /**
     * For a live/today run: calculate last completed slot from current time.
     * For a past date run: return the last interval of that date (15:15).
     */
    private function resolveSlot(string $date): ?string
    {
        $today = Carbon::today()->toDateString();

        if ($date !== $today) {
            // Historical date — use last slot of the day
            return '15:15';
        }

        // Today — compute last completed slot same way as PlacePivotOrders
        $now    = Carbon::now();
        $hour   = $now->hour;
        $minute = $now->minute;

        if ($minute >= 45) {
            $slotHour = $hour; $slotMin = 15;
        } elseif ($minute >= 15) {
            $slotHour = $hour - 1; $slotMin = 45;
            if ($slotHour < 9) return null;
        } else {
            $slotHour = $hour - 1; $slotMin = 15;
            if ($slotHour < 9) return null;
        }

        $candidate = sprintf('%02d:%02d', $slotHour, $slotMin);
        if ($candidate < '09:15') $candidate = '09:15';
        if ($candidate > '15:15') $candidate = '15:15';

        return in_array($candidate, self::INTERVALS) ? $candidate : null;
    }

    /**
     * Fallback: find the latest slot that actually has data in the DB for given date.
     */
    private function getLatestAvailableSlot(string $date): ?string
    {
        $row = ThirtyMinOhlcData::whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->orderByDesc('interval_time')
            ->value('interval_time');

        if (!$row) return null;

        // interval_time is stored as "2026-03-03 09:15:00" or just "09:15:00"
        return substr($row, strpos($row, ' ') + 1, 5) ?: substr($row, 0, 5);
    }

    // ── Pivot calc ────────────────────────────────────────────────────────────

    private function pivots(object $candle): array
    {
        $H  = (float)$candle->high;
        $L  = (float)$candle->low;
        $C  = (float)$candle->close;
        $PP = round(($H + $L + $C) / 3, 2);
        $S1 = round((2 * $PP) - $H, 2);
        $R1 = round((2 * $PP) - $L, 2);
        return [$PP, $S1, $R1];
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        $expiry = ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        return $expiry ?? ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getAtmCandle(string $symbol, string $type, string $expiry, string $date, string $slot): ?object
    {
        return ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->first();
    }
}