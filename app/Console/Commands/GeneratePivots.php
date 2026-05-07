<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockDailyOhlcData;
use App\Models\StockDailyOhlcSymbol;
use App\Models\StockPivot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * GeneratePivots
 *
 * Detects swing pivot highs and lows from your existing OHLC data
 * and stores them in the stock_pivots table.
 *
 * ALGORITHM — Classic N-bar swing pivot:
 *   PIVOT HIGH at bar[i] if high[i] > high of ALL bars in [i-N..i-1] AND [i+1..i+N]
 *   PIVOT LOW  at bar[i] if low[i]  < low  of ALL bars in [i-N..i-1] AND [i+1..i+N]
 *
 * N = --strength option (default 2).
 *   strength=2 → standard pivots (common for daily charts)
 *   strength=3 → stronger, rarer pivots (more reliable but fewer)
 *
 * USAGE:
 *   php artisan stocks:generate-pivots
 *   php artisan stocks:generate-pivots --symbol=BSE
 *   php artisan stocks:generate-pivots --symbol=BSE --strength=3
 *   php artisan stocks:generate-pivots --days=60          # only last 60 calendar days
 *   php artisan stocks:generate-pivots --symbol=BSE --fresh  # delete & rebuild
 */
class GeneratePivots extends Command
{
    protected $signature = 'stocks:generate-pivots
                            {--symbol=    : Limit to one symbol (e.g. BSE)}
                            {--strength=2 : Bars on each side needed to confirm a pivot (2 or 3)}
                            {--days=      : Only process last N calendar days of OHLC data}
                            {--fresh      : Delete all existing pivots for the symbol(s) before regenerating}';

    protected $description = 'Detect swing pivot highs/lows from OHLC data → stock_pivots table';

    public function handle(): int
    {
        $symbolOpt = $this->option('symbol') ? strtoupper(trim($this->option('symbol'))) : null;
        $strength  = max(2, min(5, (int) $this->option('strength')));
        $days      = $this->option('days') ? (int) $this->option('days') : null;
        $fresh     = (bool) $this->option('fresh');

        $this->printHeader('📌 Pivot Detection Engine', [
            'Strength' => "{$strength} bars each side",
            'Window'   => $days ? "last {$days} days" : 'full history',
            'Mode'     => $fresh ? 'FRESH (delete + rebuild)' : 'incremental',
        ]);

        // ── Load symbol list ──────────────────────────────────────────────────
        $symbolQuery = StockDailyOhlcSymbol::active();
        if ($symbolOpt) {
            $symbolQuery->where('symbol', $symbolOpt);
        }
        $symbols = $symbolQuery->orderBy('symbol')->pluck('symbol');

        if ($symbols->isEmpty()) {
            $this->error('❌ No active symbols found in stock_daily_ohlc_symbols.');
            return 1;
        }

        $this->line("  Symbols: " . $symbols->count());
        $this->newLine();

        $totalNew     = 0;
        $totalUpdated = 0;

        foreach ($symbols as $symbol) {
            [$new, $updated] = $this->processSymbol($symbol, $strength, $days, $fresh);
            $totalNew     += $new;
            $totalUpdated += $updated;
        }

        $this->newLine();
        $this->line("═══════════════════════════════════════════════");
        $this->info("  ✅ Done — New: {$totalNew} | Updated: {$totalUpdated}");
        $this->line("═══════════════════════════════════════════════");

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function processSymbol(string $symbol, int $strength, ?int $days, bool $fresh): array
    {
        // Load OHLC ordered ascending
        $query = StockDailyOhlcData::where('symbol', $symbol)
            ->where('is_missing', 0)
            ->where('high', '>', 0)
            ->where('low', '>', 0)
            ->orderBy('trade_date');

        if ($days !== null) {
            $query->where('trade_date', '>=', now()->subDays($days)->toDateString());
        }

        $rows  = $query->select(['trade_date', 'high', 'low', 'close'])->get()->values();
        $total = $rows->count();
        $need  = $strength * 2 + 1;

        if ($total < $need) {
            $this->warn("  ⚠  {$symbol} — only {$total} rows, need {$need}. Skipping.");
            return [0, 0];
        }

        // Delete existing pivots if --fresh requested
        if ($fresh) {
            $deleted = StockPivot::where('symbol', $symbol)->delete();
            $this->line("  🗑  {$symbol} — deleted {$deleted} existing pivots");
        }

        $pivots = $this->detectPivots($rows, $strength);

        $newCount     = 0;
        $updatedCount = 0;

        foreach ($pivots as $p) {
            $record = StockPivot::updateOrCreate(
                [
                    'symbol'     => $symbol,
                    'trade_date' => $p['date'],
                    'pivot_type' => $p['type'],
                ],
                [
                    'price'    => $p['price'],
                    'strength' => $strength,
                ]
            );

            if ($record->wasRecentlyCreated) {
                $newCount++;
            } elseif ($record->wasChanged()) {
                $updatedCount++;
            }
        }

        $this->line(sprintf(
            "  ✅ %-20s  %4d bars → %3d pivots (%d new, %d updated)",
            $symbol,
            $total,
            count($pivots),
            $newCount,
            $updatedCount
        ));

        return [$newCount, $updatedCount];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Core pivot detection — O(n × 2N) where N = strength.
     *
     * For bar at index $i to be a PIVOT HIGH:
     *   high[$i] must be STRICTLY GREATER than the high of every bar
     *   from [$i-N] to [$i-1] and from [$i+1] to [$i+N].
     *
     * Equal highs (flat tops) do NOT qualify — strict inequality only.
     * This matches TradingView's default "Pivot HL" indicator behaviour.
     */
    private function detectPivots(Collection $rows, int $strength): array
    {
        $pivots = [];
        $count  = $rows->count();

        // We cannot evaluate the first $strength or last $strength bars
        // because we need $strength confirming bars on EACH side.
        for ($i = $strength; $i < $count - $strength; $i++) {
            $curr     = $rows[$i];
            $currHigh = (float) $curr->high;
            $currLow  = (float) $curr->low;

            // ── Pivot HIGH check ──────────────────────────────────────────────
            $isPivotHigh = true;
            for ($j = 1; $j <= $strength; $j++) {
                if ((float)$rows[$i - $j]->high >= $currHigh ||
                    (float)$rows[$i + $j]->high >= $currHigh) {
                    $isPivotHigh = false;
                    break;
                }
            }

            // ── Pivot LOW check ───────────────────────────────────────────────
            $isPivotLow = true;
            for ($j = 1; $j <= $strength; $j++) {
                if ((float)$rows[$i - $j]->low <= $currLow ||
                    (float)$rows[$i + $j]->low <= $currLow) {
                    $isPivotLow = false;
                    break;
                }
            }

            $date = is_string($curr->trade_date)
                ? $curr->trade_date
                : $curr->trade_date->format('Y-m-d');

            if ($isPivotHigh) {
                $pivots[] = ['type' => 'HIGH', 'date' => $date, 'price' => $currHigh];
            }

            if ($isPivotLow) {
                $pivots[] = ['type' => 'LOW', 'date' => $date, 'price' => $currLow];
            }
        }

        return $pivots;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function printHeader(string $title, array $meta = []): void
    {
        $this->line('═══════════════════════════════════════════════');
        $this->info("  {$title}");
        foreach ($meta as $key => $val) {
            $this->line("  {$key}: {$val}");
        }
        $this->line('═══════════════════════════════════════════════');
        $this->newLine();
    }
}