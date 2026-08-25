<?php
// FILE: app/Console/Commands/Cp/CpProcessOrderConfigs.php
//
// Runs every minute (see scheduler entry). For each ACTIVE order config:
//   1. Look up its analysis's trigger_time (e.g. 14:45 for OI Flow Sentiment).
//   2. If the current clock (HH:MM) matches AND this config hasn't already
//      run today → call CpOrderPlacementService::runForConfig().
//   3. Stamp last_run_date so it can't fire twice in the same trading day.
//
// Adding analysis #2, #3, ... needs ZERO changes here — this file only
// ever asks "does trigger_time == now?". The per-analysis signal logic
// lives in CpAnalysisSignalResolver, and the trigger_time itself is just
// a DB value on cp_analyses (set once from Admin → Analysis Config).

namespace App\Console\Commands\Cp;

use App\Models\CpOrderConfig;
use App\Services\Cp\CpOrderPlacementService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CpProcessOrderConfigs extends Command
{
    protected $signature = 'cp:process-order-configs
                            {--force : Ignore trigger_time/last_run_date checks and run every active config right now (manual testing)}
                            {--config= : Only process a specific cp_order_configs.id (implies --force)}';

    protected $description = 'Per-minute clock: fires order placement for any active config whose analysis trigger_time has arrived';

    public function __construct(private CpOrderPlacementService $placementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = now()->toDateString();
        $nowHm = now()->format('H:i');
        $force = $this->option('force') || $this->option('config');

        $query = CpOrderConfig::with(['analysis', 'broker'])->where('status', true);
        if ($this->option('config')) {
            $query->where('id', $this->option('config'));
        }
        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->info('No matching active order configs.');
            return 0;
        }

        $fired = 0;

        foreach ($configs as $config) {
            $analysis = $config->analysis;

            if (!$analysis) {
                $this->warn("Config #{$config->id}: no linked analysis, skipping.");
                continue;
            }

            if (!$force) {
                if (empty($analysis->trigger_time)) {
                    continue; // analysis not wired into auto-order clock yet
                }

                $triggerHm = Carbon::parse($analysis->trigger_time)->format('H:i');
                if ($triggerHm !== $nowHm) {
                    continue; // not this config's minute
                }

                if ($config->last_run_date && $config->last_run_date->toDateString() === $today) {
                    continue; // already processed today
                }
            }

            try {
                $result = $this->placementService->runForConfig($config, $today);

                $config->update(['last_run_date' => $today]);
                $fired++;

                $line = "Config #{$config->id} ({$analysis->name}): placed={$result['placed']} skipped={$result['skipped']} errors={$result['errors']}";
                $this->info($line);
                Log::info("CpProcessOrderConfigs: {$line}");

            } catch (\Exception $e) {
                $this->error("Config #{$config->id}: {$e->getMessage()}");
                Log::error("CpProcessOrderConfigs: config #{$config->id} — {$e->getMessage()}");
            }
        }

        if ($fired === 0 && !$force) {
            $this->line("No config due at {$nowHm}.");
        }

        return 0;
    }
}