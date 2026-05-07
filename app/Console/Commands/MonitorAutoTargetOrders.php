<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AutoTargetOrderService;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\Log;

class MonitorAutoTargetOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'target:monitor 
                            {--user-id= : Specific user ID to monitor}
                            {--broker-id= : Specific broker ID to monitor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor portfolio positions and place auto target orders at 20% profit';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Auto Target Order Monitoring...');
        $this->newLine();

        $service = new AutoTargetOrderService();

        try {
            // Get active brokers
            $brokersQuery = BrokerApi::where('client_type', 'Zerodha')
                ->where('is_token_valid', true);

            if ($this->option('user-id')) {
                $brokersQuery->where('user_id', $this->option('user-id'));
            }

            if ($this->option('broker-id')) {
                $brokersQuery->where('id', $this->option('broker-id'));
            }

            $brokers = $brokersQuery->get();

            if ($brokers->isEmpty()) {
                $this->warn('⚠️  No active brokers found');
                return 0;
            }

            $this->info("📊 Found {$brokers->count()} active broker(s)");
            $this->newLine();

            $totalResults = [
                'checked' => 0,
                'placed' => 0,
                'triggered' => 0,
                'completed' => 0,
                'failed' => 0,
                'errors' => []
            ];

            // Process each broker
            foreach ($brokers as $broker) {
                $this->line("Processing broker: {$broker->account_user_name} (ID: {$broker->id})");
                
                try {
                    // Step 1: Sync positions and create new targets
                    $this->info('  → Syncing positions...');
                    $syncResult = $service->syncPositionsAndCreateTargets($broker->user_id, $broker->id);
                    
                    if ($syncResult['success']) {
                        $this->info("  ✓ Created: {$syncResult['created']}, Skipped: {$syncResult['skipped']}");
                        
                        if (!empty($syncResult['errors'])) {
                            foreach ($syncResult['errors'] as $error) {
                                $this->warn("  ⚠ {$error['symbol']}: {$error['error']}");
                            }
                        }
                    } else {
                        $this->error("  ✗ Sync failed: {$syncResult['message']}");
                    }

                } catch (\Exception $e) {
                    $this->error("  ✗ Error processing broker: " . $e->getMessage());
                    Log::error("Broker processing error: " . $e->getMessage());
                }

                $this->newLine();
            }

            // Step 2: Monitor and place target orders
            $this->info('🔍 Monitoring existing target orders...');
            $results = $service->monitorAndPlaceTargets();

            foreach ($results as $key => $value) {
                if ($key !== 'errors') {
                    $totalResults[$key] += $value;
                } else {
                    $totalResults['errors'] = array_merge($totalResults['errors'], $value);
                }
            }

            // Display summary
            $this->newLine();
            $this->info('📈 Monitoring Summary:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Checked', $totalResults['checked']],
                    ['Placed', $totalResults['placed']],
                    ['Triggered', $totalResults['triggered']],
                    ['Completed', $totalResults['completed']],
                    ['Failed', $totalResults['failed']],
                ]
            );

            if (!empty($totalResults['errors'])) {
                $this->newLine();
                $this->error('⚠️  Errors encountered:');
                foreach ($totalResults['errors'] as $error) {
                    $this->line("  • {$error['symbol']}: {$error['error']}");
                }
            }

            $this->newLine();
            $this->info('✅ Auto Target Order Monitoring Completed!');

            // Log the execution
            Log::info('Auto target monitoring completed', [
                'results' => $totalResults,
                'brokers_processed' => $brokers->count()
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Fatal Error: ' . $e->getMessage());
            Log::error('Auto target monitoring failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}