<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AutoTargetOrder;
use Illuminate\Support\Facades\Log;

class CleanupAutoTargetOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'target:cleanup {--days=30 : Number of days old to cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old completed/cancelled auto target orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $this->info("🧹 Cleaning up auto target orders older than {$days} days...");

        try {
            $deleted = AutoTargetOrder::whereIn('order_status', ['COMPLETED', 'CANCELLED', 'EXPIRED', 'FAILED'])
                ->where('updated_at', '<', now()->subDays($days))
                ->delete();

            $this->info("✅ Cleaned up {$deleted} old auto target orders");
            
            Log::info("Auto target cleanup completed: {$deleted} records deleted");

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error cleaning up: ' . $e->getMessage());
            Log::error('Auto target cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }
}