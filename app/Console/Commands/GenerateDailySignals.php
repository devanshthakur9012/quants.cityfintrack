<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\User\EodSignalController;
use App\Models\SignalPrediction;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * GenerateDailySignals
 *
 * Runs at 15:15 every market day via cron.
 *
 * WHAT IT DOES (two steps every day):
 *
 * STEP 1 — Generate signal for TOMORROW:
 *   Reads today's market data (OI, price, PCR)
 *   Runs the v4 signal engine
 *   Saves to signal_predictions (signal_date = today, trade_date = tomorrow)
 *   Result: "System says BUY CE on NIFTY tomorrow"
 *
 * STEP 2 — Fill outcome for YESTERDAY's signal:
 *   Finds signals with trade_date = today and outcome = PENDING
 *   These are signals that said "trade on today's date"
 *   Checks today's actual FUT price movement
 *   Marks WIN (T1 hit +0.8%) / LOSS (SL hit -0.5%) / FLAT (neither)
 *   Result: "Yesterday's BUY CE on NIFTY was a WIN — price moved +1.2%"
 *
 * CRON SETUP (add to Kernel.php schedule method):
 *   $schedule->command('signals:generate-daily')->dailyAt('15:15')->weekdays();
 *
 * MANUAL USAGE:
 *   php artisan signals:generate-daily                       → today
 *   php artisan signals:generate-daily --date=2026-03-19    → specific date
 *   php artisan signals:generate-daily --symbol=NIFTY       → one symbol only
 *   php artisan signals:generate-daily --force              → overwrite existing
 *
 * BACKFILLING OLD DATA:
 *   php artisan signals:generate-daily --start=2025-01-01 --end=2025-12-31
 *   This generates + fills outcomes for all dates in range.
 *   Use this once to populate historical data for the backtest page.
 */
class GenerateDailySignals extends Command
{
    protected $signature = 'signals:generate-daily
                            {--date=       : Single date Y-m-d (default: today)}
                            {--start=      : Start date for range backfill Y-m-d}
                            {--end=        : End date for range backfill Y-m-d}
                            {--symbol=     : Specific symbol only (default: all)}
                            {--force       : Overwrite already-saved signals}';

    protected $description = 'Generate EOD signals for today + fill yesterday outcomes. Use --start/--end to backfill history.';

    private EodSignalController $engine;

    public function __construct(EodSignalController $engine)
    {
        parent::__construct();
        $this->engine = $engine;
    }

    // =========================================================================
    public function handle(): int
    {
        $symbolFilter = $this->option('symbol')
            ? strtoupper($this->option('symbol'))
            : null;
        $force = (bool) $this->option('force');

        // Determine date range to process
        if ($this->option('start')) {
            // Range mode — backfill history
            $startDate = Carbon::parse($this->option('start'))->toDateString();
            $endDate   = $this->option('end')
                ? Carbon::parse($this->option('end'))->toDateString()
                : Carbon::yesterday()->toDateString();
            $this->info("=== BACKFILL MODE: {$startDate} → {$endDate} ===");
        } else {
            // Single date mode — default today
            $singleDate = $this->option('date')
                ? Carbon::parse($this->option('date'))->toDateString()
                : Carbon::today()->toDateString();
            $startDate = $singleDate;
            $endDate   = $singleDate;
            $this->info("=== DAILY MODE: {$singleDate} ===");
        }

        // Get all actual trading dates from DB in the range
        // (uses real DB dates — not calendar math — so holidays handled correctly)
        $tradingDates = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereBetween(DB::raw('DATE(trade_date)'), [$startDate, $endDate])
            ->select(DB::raw('DATE(trade_date) as d'))
            ->distinct()->orderBy('d')
            ->pluck('d')->map(fn($d) => (string) $d)->toArray();

        if (empty($tradingDates)) {
            $this->warn("No trading data found for this range.");
            return 0;
        }

        $this->info("Trading dates found: " . count($tradingDates));
        $this->newLine();

        // Get available symbols
        $symbols = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereBetween(DB::raw('DATE(trade_date)'), [$startDate, $endDate])
            ->whereNotNull('base_symbol')
            ->distinct()->orderBy('base_symbol')
            ->pluck('base_symbol')->toArray();

        if ($symbolFilter) {
            $symbols = array_filter($symbols, fn($s) => $s === $symbolFilter);
            $symbols = array_values($symbols);
        }

        if (empty($symbols)) {
            $this->error("No symbols found.");
            return 1;
        }

        $this->info("Symbols: " . implode(', ', $symbols));
        $this->newLine();

        $totalGenerated  = 0;
        $totalFilled     = 0;
        $totalSkipped    = 0;
        $totalErrors     = 0;

        foreach ($tradingDates as $idx => $dateStr) {
            $this->line("📅 Processing {$dateStr}");

            // Prev/next from actual DB dates (correct holiday handling)
            $prevDate = $idx > 0 ? $tradingDates[$idx - 1] : null;
            $nextDate = isset($tradingDates[$idx + 1]) ? $tradingDates[$idx + 1] : null;

            // ─── STEP 2: Fill outcome for signals that traded ON this date ─────
            // These are signals generated on prevDate that said "trade on dateStr"
            $this->fillOutcomesForDate($dateStr, $symbols, $totalFilled, $totalErrors);

            // ─── STEP 1: Generate signal for this date (predict nextDate) ──────
            foreach ($symbols as $sym) {
                // Skip if already saved and not forcing
                if (!$force) {
                    $exists = SignalPrediction::where('symbol', $sym)
                        ->where('signal_date', $dateStr)
                        ->where('version', 'v4')
                        ->exists();
                    if ($exists) {
                        $totalSkipped++;
                        continue;
                    }
                }

                try {
                    $result = $this->engine->analyseSymbol(
                        $sym, $dateStr, $prevDate, $nextDate, true // marketClosed = true for signals
                    );

                    if (!$result) {
                        $this->warn("  ⚠ {$sym}: no data");
                        continue;
                    }

                    $this->engine->savePrediction($result);

                    $sig    = $result['signal'];
                    $action = $sig['action'];
                    $conf   = $sig['confidence'];

                    $icon = match ($action) {
                        'BUY_CE' => '🟢',
                        'BUY_PE' => '🔴',
                        default  => '⚪',
                    };

                    $this->line("  {$icon} {$sym}: {$action} (conf: {$conf}%) → trade on " . ($nextDate ?? '?'));
                    $totalGenerated++;

                } catch (\Exception $e) {
                    Log::error("GenerateDailySignals {$sym} {$dateStr}: " . $e->getMessage());
                    $this->error("  ✗ {$sym}: " . $e->getMessage());
                    $totalErrors++;
                }
            }

            $this->newLine();
        }

        // Final summary
        $this->info("═══════════════════════════════════════");
        $this->info("  DONE");
        $this->info("  Signals generated : {$totalGenerated}");
        $this->info("  Outcomes filled   : {$totalFilled}");
        $this->info("  Skipped (exists)  : {$totalSkipped}");
        $this->info("  Errors            : {$totalErrors}");
        $this->info("═══════════════════════════════════════");

        return 0;
    }

    // =========================================================================
    // Fill outcomes for all PENDING signals that had trade_date = $date
    // =========================================================================
    private function fillOutcomesForDate(
        string $date,
        array  $symbols,
        int    &$totalFilled,
        int    &$totalErrors
    ): void {
        $pending = SignalPrediction::where('version', 'v4')
            ->where('trade_date', $date)
            ->where('outcome', 'PENDING')
            ->whereIn('action', ['BUY_CE', 'BUY_PE'])
            ->whereIn('symbol', $symbols)
            ->get();

        foreach ($pending as $pred) {
            try {
                $candles = OptionOhlcData::where('base_symbol', $pred->symbol)
                    ->where('instrument_type', 'FUT')
                    ->whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'open', 'high', 'low', 'close']);

                if ($candles->isEmpty()) {
                    $this->warn("  ⚠ Outcome fill: no FUT data for {$pred->symbol} on {$date}");
                    continue;
                }

                $first = $candles->first();
                $last  = $candles->last();

                // Entry at 9:30 open
                $entryCandle = $candles->first(
                    fn($c) => Carbon::parse($c->interval_time)->format('H:i') === '09:30'
                );
                $entryPrice = $entryCandle
                    ? ((float) $entryCandle->open ?: (float) $first->open)
                    : (float) $first->open;

                if ($entryPrice <= 0) continue;

                $afterEntry = $candles->filter(
                    fn($c) => Carbon::parse($c->interval_time)->format('H:i') >= '09:30'
                );
                $high = $afterEntry->max(fn($c) => (float) $c->high);
                $low  = $afterEntry->min(fn($c) => (float) $c->low);

                $hitT1 = false;
                $hitSl = false;

                if ($pred->action === 'BUY_CE') {
                    $hitT1 = $high >= $entryPrice * 1.008; // +0.8%
                    $hitSl = $low  <= $entryPrice * 0.995; // -0.5%
                } else { // BUY_PE
                    $hitT1 = $low  <= $entryPrice * 0.992; // -0.8%
                    $hitSl = $high >= $entryPrice * 1.005; // +0.5%
                }

                $outcome  = $hitT1 ? 'WIN' : ($hitSl ? 'LOSS' : 'FLAT');
                $dayOpen  = (float) $first->open;
                $dayClose = (float) $last->close;
                $changePct = $dayOpen > 0
                    ? round((($dayClose - $dayOpen) / $dayOpen) * 100, 2) : 0;

                $pred->update([
                    'outcome'             => $outcome,
                    'next_day_open'       => round($dayOpen, 2),
                    'next_day_close'      => round($dayClose, 2),
                    'next_day_high'       => round($candles->max(fn($c) => (float)$c->high), 2),
                    'next_day_low'        => round($candles->min(fn($c) => (float)$c->low), 2),
                    'next_day_change_pct' => $changePct,
                    'hit_t1'              => $hitT1,
                    'hit_t2'              => false,
                    'hit_sl'              => $hitSl,
                ]);

                $icon = $outcome === 'WIN' ? '✅' : ($outcome === 'LOSS' ? '❌' : '➖');
                $this->line("  {$icon} Filled {$pred->symbol} {$pred->action}: {$outcome} (entry {$entryPrice}, change {$changePct}%)");
                $totalFilled++;

            } catch (\Exception $e) {
                Log::error("fillOutcomesForDate {$pred->symbol} {$date}: " . $e->getMessage());
                $this->error("  ✗ Fill error {$pred->symbol}: " . $e->getMessage());
                $totalErrors++;
            }
        }
    }
}