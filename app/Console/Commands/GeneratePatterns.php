<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockDailyOhlcSymbol;
use App\Services\Stock\PatternService;

/**
 * GeneratePatterns
 *
 * Runs all six pattern detectors (Double Top/Bottom, Breakout/Breakdown,
 * Support Bounce, Resistance Reject) for all or one active symbol.
 *
 * Requires stock_pivots to be populated first (run stocks:generate-pivots).
 *
 * USAGE:
 *   php artisan stocks:generate-patterns
 *   php artisan stocks:generate-patterns --symbol=BSE
 *   php artisan stocks:generate-patterns --type=DOUBLE_BOTTOM   # one pattern only
 */
class GeneratePatterns extends Command
{
    protected $signature = 'stocks:generate-patterns
                            {--symbol= : Limit to one symbol}
                            {--type=   : Run only one pattern type (DOUBLE_TOP, DOUBLE_BOTTOM, BREAKOUT, BREAKDOWN, SUPPORT_BOUNCE, RESISTANCE_REJECT)}';

    protected $description = 'Detect chart patterns from pivot data → stock_patterns table';

    public function handle(PatternService $patternService): int
    {
        $symbolOpt = $this->option('symbol') ? strtoupper(trim($this->option('symbol'))) : null;
        $typeOpt   = $this->option('type')   ? strtoupper(trim($this->option('type')))   : null;

        $this->line('═══════════════════════════════════════════════');
        $this->info('  📊 Pattern Detection Engine');
        if ($typeOpt) {
            $this->line("  Pattern filter: {$typeOpt}");
        }
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

        $totalPatterns = 0;

        foreach ($symbols as $symbol) {
            $count = $this->runDetectors($patternService, $symbol, $typeOpt);
            $this->line(sprintf("  ✅ %-20s  %d pattern(s) saved/updated", $symbol, $count));
            $totalPatterns += $count;
        }

        $this->newLine();
        $this->line('═══════════════════════════════════════════════');
        $this->info("  Total patterns saved/updated: {$totalPatterns}");
        $this->line('═══════════════════════════════════════════════');

        return 0;
    }

    private function runDetectors(PatternService $svc, string $symbol, ?string $type): int
    {
        return match ($type) {
            'DOUBLE_TOP'        => $svc->detectDoubleTop($symbol),
            'DOUBLE_BOTTOM'     => $svc->detectDoubleBottom($symbol),
            'BREAKOUT'          => $svc->detectBreakout($symbol),
            'BREAKDOWN'         => $svc->detectBreakdown($symbol),
            'SUPPORT_BOUNCE'    => $svc->detectSupportBounce($symbol),
            'RESISTANCE_REJECT' => $svc->detectResistanceReject($symbol),
            default             => $svc->detectAll($symbol),
        };
    }
}