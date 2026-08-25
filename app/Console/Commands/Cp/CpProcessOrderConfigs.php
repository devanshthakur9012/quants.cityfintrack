<?php
// FILE: app/Console/Commands/Cp/CpProcessOrderConfigs.php
//
// Runs every minute. For each ACTIVE order config, asks its analysis's
// signal resolver "got anything for today?" If yes, places orders
// (subject to the existing per-symbol dedupe in CpOrderPlacementService).
// If no, nothing happens — cheap and safe to call every minute.
//
// No trigger_time, no run_mode, no interval tracking. Each analysis's
// OWN service decides when it's ready to produce a signal (e.g. OI Flow
// Sentiment only returns rows once the 14:45 candle exists). This file
// never needs to change when analysis #2, #3... are added.
namespace App\Console\Commands\Cp;

use App\Models\CpOrderConfig;
use App\Services\Cp\CpOrderPlacementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CpProcessOrderConfigs extends Command
{
    protected $signature = 'cp:process-order-configs {--config= : Only process a specific cp_order_configs.id}';
    protected $description = 'Per-minute: checks every active config for signals and places orders when ready';

    public function __construct(private CpOrderPlacementService $placementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = now()->toDateString();

        $query = CpOrderConfig::with(['analysis', 'broker'])->where('status', true);
        if ($this->option('config')) {
            $query->where('id', $this->option('config'));
        }
        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->info('No matching active order configs.');
            return 0;
        }

        foreach ($configs as $config) {
            if (!$config->analysis) {
                $this->warn("Config #{$config->id}: no linked analysis, skipping.");
                continue;
            }

            try {
                $result = $this->placementService->runForConfig($config, $today);
                $config->update(['last_run_at' => now()]);

                if ($result['placed'] > 0 || $result['errors'] > 0) {
                    $line = "Config #{$config->id} ({$config->analysis->name}): placed={$result['placed']} skipped={$result['skipped']} errors={$result['errors']}";
                    $this->info($line);
                    Log::info("CpProcessOrderConfigs: {$line}");
                }
            } catch (\Exception $e) {
                $this->error("Config #{$config->id}: {$e->getMessage()}");
                Log::error("CpProcessOrderConfigs: config #{$config->id} — {$e->getMessage()}");
            }
        }

        return 0;
    }
}