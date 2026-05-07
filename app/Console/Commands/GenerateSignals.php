<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockDailyOhlcSymbol;
use App\Models\StockSignal;
use App\Services\Stock\SignalService;
use Carbon\Carbon;

/**
 * GenerateSignals
 *
 * Runs the full confluence scoring engine for all (or one) active symbols
 * and stores BUY / SELL / HOLD signals in stock_signals.
 *
 * Score = Pattern(±30) + Similarity(±30) + Pivot(±20) + Volume(±10) + Trend(±10)
 * Confidence = clamp(score + 50, 0, 100)
 * BUY ≥ 65 | HOLD 36-64 | SELL ≤ 35
 *
 * USAGE:
 *   php artisan stocks:generate-signals
 *   php artisan stocks:generate-signals --symbol=BSE
 *   php artisan stocks:generate-signals --date=2024-06-15
 *   php artisan stocks:generate-signals --min-confidence=65    # only print strong signals
 */
class GenerateSignals extends Command
{
    protected $signature = 'stocks:generate-signals
                            {--symbol=          : Limit to one symbol}
                            {--date=            : Signal date in Y-m-d (default: today)}
                            {--min-confidence=0 : Only print signals at or above this confidence in output}';

    protected $description = 'Run confluence scoring → BUY/SELL/HOLD signals in stock_signals table';

    public function handle(SignalService $signalService): int
    {
        $symbolOpt  = $this->option('symbol') ? strtoupper(trim($this->option('symbol'))) : null;
        $date       = $this->option('date') ?: Carbon::today('Asia/Kolkata')->toDateString();
        $minConf    = (int) $this->option('min-confidence');

        $this->line('═══════════════════════════════════════════════');
        $this->info("  🔥 Signal Generator  —  {$date}");
        $this->line('═══════════════════════════════════════════════');
        $this->newLine();

        $symbolQuery = StockDailyOhlcSymbol::active();
        if ($symbolOpt) {
            $symbolQuery->where('symbol', $symbolOpt);
        }
        $symbols = $symbolQuery->orderBy('symbol')->pluck('symbol');

        if ($symbols->isEmpty()) {
            $this->error('❌ No active symbols found.');
            return 1;
        }

        $counts = ['BUY' => 0, 'SELL' => 0, 'HOLD' => 0];

        foreach ($symbols as $symbol) {
            try {
                $signal = $signalService->generate($symbol, $date);
                $counts[$signal->signal_type]++;

                if ($signal->confidence >= $minConf) {
                    $this->line(sprintf(
                        "  %s  %-18s  %-5s  %3d%%  %s",
                        $signal->getEmoji(),
                        $symbol,
                        $signal->signal_type,
                        $signal->confidence,
                        $signal->reason
                    ));
                }

            } catch (\Throwable $e) {
                $this->error("  ✗ {$symbol}: {$e->getMessage()}");
            }
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('═══════════════════════════════════════════════');
        $this->info("  Date: {$date}");
        $this->line("  🟢 BUY : {$counts['BUY']}");
        $this->line("  🔴 SELL: {$counts['SELL']}");
        $this->line("  🟡 HOLD: {$counts['HOLD']}");
        $this->line('═══════════════════════════════════════════════');

        // ── Print strong signals separately ───────────────────────────────────
        $this->newLine();
        $this->info("  STRONG BUY signals (confidence ≥ 65):");

        $strongBuys = StockSignal::forDate($date)
            ->buy()
            ->strong(65)
            ->orderByDesc('confidence')
            ->get();

        if ($strongBuys->isEmpty()) {
            $this->line('  (none)');
        }
        foreach ($strongBuys as $s) {
            $this->line("  🟢  {$s->symbol}  {$s->confidence}%  |  {$s->reason}");
        }

        $this->newLine();
        $this->info("  STRONG SELL signals (confidence ≤ 35):");

        $strongSells = StockSignal::forDate($date)
            ->sell()
            ->strong(0) // sell side: low confidence = strong sell
            ->where('confidence', '<=', 35)
            ->orderBy('confidence') // ascending — lowest confidence = strongest sell
            ->get();

        if ($strongSells->isEmpty()) {
            $this->line('  (none)');
        }
        foreach ($strongSells as $s) {
            $this->line("  🔴  {$s->symbol}  {$s->confidence}%  |  {$s->reason}");
        }

        $this->newLine();

        return 0;
    }
}