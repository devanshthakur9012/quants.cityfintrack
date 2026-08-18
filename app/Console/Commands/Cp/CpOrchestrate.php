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
        $timeframe = '15min'; // ALWAYS 15min — never changes
        $now       = Carbon::now();
    
        $this->info("╔══════════════════════════════════════╗");
        $this->info("║  CP Orchestrator [15min]             ║");
        $this->info("║  " . $now->format('Y-m-d H:i:s') . " ║");
        $this->info("╚══════════════════════════════════════╝");
        $this->newLine();
    
        $config = AnalysisConfig::where('time_frame', '15min')
            ->where('is_active', true)->first();
    
        if (!$config) {
            $this->warn("⚠️  No active 15min config. Create one at Admin → Analysis Config.");
            return 0;
        }
    
        $this->info("   Broker  : " . ($config->broker->account_user_name ?? 'N/A'));
        $this->info("   Symbols : " . $config->symbols->pluck('symbol')->implode(', '));
        $this->newLine();
    
        $sub = array_filter([
            '--timeframe' => $timeframe,
            '--from'      => $this->option('from'),
            '--to'        => $this->option('to'),
            '--symbol'    => $this->option('symbol'),
        ], fn($v) => $v !== null);
    
        $results = [];
    
        // 1. Stock EQ
        if (!$this->option('skip-stock')) {
            $this->info("▶ 1/3 Stock OHLC");
            $results['stock'] = $this->call('cp:collect-stock', $sub);
            $this->newLine();
        }
        
    
        // 2. FUT
        if (!$this->option('skip-fut')) {
            $this->info("▶ 2/3 FUT OHLC");
            $results['fut'] = $this->call('cp:collect-fut', $sub);
            $this->newLine();
        }
    
        // 3. Option
        if (!$this->option('skip-option')) {
            $this->info("▶ 3/3 Option OHLC");
            $results['option'] = $this->call('cp:collect-option', $sub);
            $this->newLine();
        }
    
        $this->info("══════════ Done: " . Carbon::now()->format('H:i:s') . " ══════════");
        foreach ($results as $t => $code) {
            $this->info("  " . ($code === 0 ? '✅' : '❌') . " {$t}");
        }
    
        return in_array(1, array_values($results)) ? 1 : 0;
    }

}