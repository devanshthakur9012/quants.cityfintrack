<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockDailyOhlcSymbol;
use App\Services\Stock\FeatureService;

/**
 * GenerateFeatures
 *
 * Computes feature vectors (trend, volatility, RSI, SMA, volume, distances)
 * for every symbol × trading day and stores them in stock_features.
 *
 * These features are the input to the SimilarityService.
 *
 * USAGE:
 *   php artisan stocks:generate-features                   # all symbols, full history
 *   php artisan stocks:generate-features --symbol=BSE      # one symbol, full history
 *   php artisan stocks:generate-features --days=5          # last 5 days only (for daily cron)
 *   php artisan stocks:generate-features --symbol=BSE --days=30
 */
class GenerateFeatures extends Command
{
    protected $signature = 'stocks:generate-features
                            {--symbol= : Limit to one symbol}
                            {--days=   : Only compute features for the last N calendar days (uses extra buffer for rolling window accuracy)}';

    protected $description = 'Compute RSI, trend, volatility, SMA, distance features → stock_features table';

    public function handle(FeatureService $featureService): int
    {
        $symbolOpt = $this->option('symbol') ? strtoupper(trim($this->option('symbol'))) : null;
        $days      = $this->option('days') ? (int) $this->option('days') : null;

        $this->line('═══════════════════════════════════════════════');
        $this->info('  🧠 Feature Engineering Engine');
        $this->line('  Window : ' . ($days ? "last {$days} days" : 'full history'));
        $this->line('  RSI    : Wilder 14-period (matches TradingView)');
        $this->line('  SMA    : 20 + 50 period');
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

        $totalRows = 0;

        foreach ($symbols as $symbol) {
            $count = $featureService->generate($symbol, $days);
            $this->line(sprintf("  ✅ %-20s  %d row(s) upserted", $symbol, $count));
            $totalRows += $count;
        }

        $this->newLine();
        $this->line('═══════════════════════════════════════════════');
        $this->info("  Total feature rows upserted: {$totalRows}");
        $this->line('═══════════════════════════════════════════════');

        return 0;
    }
}