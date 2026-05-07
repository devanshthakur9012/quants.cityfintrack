<?php
// FILE: app/Console/Commands/Cp/CpOrchestrate.php

namespace App\Console\Commands\Cp;

use Illuminate\Console\Command;
use App\Models\AnalysisConfig;
use Carbon\Carbon;

/**
 * CpOrchestrate
 *
 * Master command — reads active analysis config for the given timeframe
 * and runs all 3 collectors in sequence: Stock → FUT → Option.
 *
 * This is what your cron calls. Each timeframe has its own cron entry.
 *
 * Usage:
 *   php artisan cp:orchestrate --timeframe=15min
 *   php artisan cp:orchestrate --timeframe=30min
 *   php artisan cp:orchestrate --timeframe=1hr
 *
 * Cron examples (in Kernel.php):
 *   15min: every 15 minutes, 9:15 AM to 3:30 PM, Mon-Fri
 *   30min: every 30 minutes
 *   1hr:   every 60 minutes
 */
class CpOrchestrate extends Command
{
    protected $signature = 'cp:orchestrate
                            {--timeframe=15min : 15min | 30min | 1hr}
                            {--skip-stock      : Skip stock collection}
                            {--skip-fut        : Skip FUT collection}
                            {--skip-option     : Skip option collection}
                            {--from=           : Pass to all sub-commands (historical)}
                            {--to=             : Pass to all sub-commands}
                            {--symbol=         : Limit all collectors to this symbol}';

    protected $description = 'Master orchestrator: runs Stock + FUT + Option OHLC collectors for the given timeframe';

    public function handle(): int
    {
        $timeframe = $this->option('timeframe');
        $now       = Carbon::now();

        $this->info("╔══════════════════════════════════════════════╗");
        $this->info("║  🚀 CP Orchestrator [{$timeframe}]                ");
        $this->info("║  " . $now->format('Y-m-d H:i:s') . "              ");
        $this->info("╚══════════════════════════════════════════════╝");
        $this->newLine();

        if (!in_array($timeframe, ['15min', '30min', '1hr'])) {
            $this->error("❌ Invalid timeframe. Use: 15min | 30min | 1hr");
            return 1;
        }

        // ── Verify an active config exists ────────────────────────────────────
        $config = AnalysisConfig::where('time_frame', $timeframe)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            $this->warn("⚠️  No active config for timeframe [{$timeframe}]. Nothing to collect.");
            $this->line("   Create one at Admin → Analysis Config.");
            return 0;
        }

        $this->info("   Config ID : {$config->id}");
        $this->info("   Broker    : " . ($config->broker->account_user_name ?? 'N/A'));
        $this->info("   Symbols   : " . $config->symbols->pluck('symbol')->implode(', '));
        $this->newLine();

        // ── Build shared options for sub-commands ────────────────────────────
        $subOptions = array_filter([
            '--timeframe' => $timeframe,
            '--from'      => $this->option('from'),
            '--to'        => $this->option('to'),
            '--symbol'    => $this->option('symbol'),
        ], fn($v) => $v !== null);

        $results = [];

        // ── 1. Stock OHLC ─────────────────────────────────────────────────────
        if (!$this->option('skip-stock')) {
            $this->info("▶ Step 1/3: Stock OHLC");
            $exit = $this->call('cp:collect-stock', $subOptions);
            $results['stock'] = $exit;
            $this->newLine();
        } else {
            $this->warn("⏭  Skipping Stock (--skip-stock)");
        }

        // ── 2. FUT OHLC ───────────────────────────────────────────────────────
        if (!$this->option('skip-fut')) {
            $this->info("▶ Step 2/3: FUT OHLC");
            $exit = $this->call('cp:collect-fut', $subOptions);
            $results['fut'] = $exit;
            $this->newLine();
        } else {
            $this->warn("⏭  Skipping FUT (--skip-fut)");
        }

        // ── 3. Option OHLC ────────────────────────────────────────────────────
        if (!$this->option('skip-option')) {
            $this->info("▶ Step 3/3: Option OHLC");
            $exit = $this->call('cp:collect-option', $subOptions);
            $results['option'] = $exit;
            $this->newLine();
        } else {
            $this->warn("⏭  Skipping Option (--skip-option)");
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->info("════════════════════════════════════════════");
        $this->info("  Orchestration complete — " . Carbon::now()->format('H:i:s'));
        foreach ($results as $type => $code) {
            $icon = $code === 0 ? '✅' : '❌';
            $this->info("  {$icon} {$type}: exit code {$code}");
        }
        $this->info("════════════════════════════════════════════");

        // Return non-zero if any sub-command failed
        return in_array(1, array_values($results)) ? 1 : 0;
    }
}