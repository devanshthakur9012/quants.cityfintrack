<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OnePercentAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OnePercentAutoTradeCommand extends Command
{
    protected $signature = 'onepercent:auto-trade {--test-date= : Test date (Y-m-d H:i:s)}';

    protected $description = 'Auto trade based on 1% move + OI analysis';

    public function handle()
    {
        $testDate = $this->option('test-date');

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE - Date: {$testDate}");
                Log::info("One-Percent Auto Trade - TEST MODE: {$testDate}");
            } else {
                $this->info("🚀 Processing One-Percent Auto Trading");
                Log::info("One-Percent Auto Trade - LIVE MODE");
            }

            $helper = new OnePercentAutoTradingHelper();

            // Step 1: Process signals
            $this->info("📊 Detecting 1% move signals with OI analysis...");
            $helper->processSignals($testDate);

            // Step 2: Place orders
            $this->info("📤 Placing orders...");
            $helper->placeOrders($testDate);

            $this->info("✅ One-percent auto trading completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('One-Percent Auto Trade Error: ' . $e->getMessage());
            return 1;
        }
    }
}