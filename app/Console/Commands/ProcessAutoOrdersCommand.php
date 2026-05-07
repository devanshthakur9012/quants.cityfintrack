<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaAutoOrderHelper;
use Illuminate\Support\Facades\Log;

class ProcessAutoOrdersCommand extends Command
{
    protected $signature = 'zerodha:process-auto-orders {--test-date= : Test date for processing (Y-m-d)}';

    protected $description = 'Process automatic orders based on Supertrend and Donchian signals for Zerodha';

    public function handle()
    {
        try {
            $testDate = $this->option('test-date');
            
            if ($testDate) {
                $this->info("🧪 Running in TEST MODE for date: {$testDate}");
                Log::info("Auto Order Processing - TEST MODE: {$testDate}");
            } else {
                $this->info("🚀 Processing Auto Orders for Today");
                Log::info("Auto Order Processing - LIVE MODE");
            }

            $helper = new ZerodhaAutoOrderHelper();
            
            // Step 1: Detect signals and create portfolio entries
            $this->info("📊 Step 1: Detecting signals...");
            $helper->processAutoOrders($testDate);
            
            // Step 2: Place orders for detected signals
            $this->info("📤 Step 2: Placing orders...");
            $helper->placeOrders($testDate);
            
            $this->info("✅ Auto Order Processing Completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Auto Order Command Error: ' . $e->getMessage());
            return 1;
        }
    }
}