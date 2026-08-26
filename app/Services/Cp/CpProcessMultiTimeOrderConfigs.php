<?php
// app/Console/Commands/Cp/CpProcessMultiTimeOrderConfigs.php
namespace App\Console\Commands\Cp;

use App\Models\CpMultiTimeOrderConfig;
use App\Services\Cp\CpMultiTimeOrderPlacementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated per-minute cron for OI Flow Multi-Snapshot ONLY. Completely
 * separate from cp:process-order-configs — different table, different
 * service, different orders table. Safe to run every minute: each
 * snapshot ('10:15'/'11:15'/'12:15') only fires once its own candle
 * exists (checked inside OIFlowMultiTimeService::getSignalsForDate),
 * and CpMultiTimeOrderPlacementService dedupes by signal_time so the
 * same snapshot is never re-placed on subsequent minutes.
 */
class CpProcessMultiTimeOrderConfigs extends Command
{
    protected $signature = 'cp:process-multi-time-order-configs
                            {--config= : Only process a specific cp_multi_time_order_configs.id}
                            {--date=   : Test against a specific date (Y-m-d) instead of today}';
    protected $description = 'Per-minute: checks every active Multi-Snapshot config for 10:15/11:15/12:15 signals and places orders';

    public function __construct(private CpMultiTimeOrderPlacementService $placementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();

        $query = CpMultiTimeOrderConfig::with('broker')->where('status', true);
        if ($this->option('config')) {
            $query->where('id', $this->option('config'));
        }
        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->info('No matching active Multi-Snapshot order configs.');
            return 0;
        }

        foreach ($configs as $config) {
            try {
                $result = $this->placementService->runForConfig($config, $date);
                $config->update(['last_run_at' => now()]);

                if ($result['placed'] > 0 || $result['errors'] > 0) {
                    $line = "MultiTime Config #{$config->id} [{$date}]: placed={$result['placed']} skipped={$result['skipped']} errors={$result['errors']}";
                    $this->info($line);
                    Log::info("CpProcessMultiTimeOrderConfigs: {$line}");
                }
            } catch (\Exception $e) {
                $this->error("Config #{$config->id}: {$e->getMessage()}");
                Log::error("CpProcessMultiTimeOrderConfigs: config #{$config->id} — {$e->getMessage()}");
            }
        }

        return 0;
    }
}