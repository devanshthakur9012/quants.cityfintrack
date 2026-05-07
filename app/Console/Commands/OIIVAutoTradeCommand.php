<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OIIVAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OIIVAutoTradeCommand extends Command
{
    protected $signature = 'oiiv:auto-trade {--test-date= : Test date (Y-m-d)}';
    
    protected $description = 'Auto trade based on OI + Price signal alignment (runs 3:00-3:30 PM)';

    public function handle()
    {
        $testDate = $this->option('test-date');

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE - Date: {$testDate}");
                Log::info("OI+IV Auto Trade - TEST MODE: {$testDate}");
            } else {
                $currentTime = Carbon::now('Asia/Kolkata');
                $this->info("🚀 Processing OI+IV Auto Trading");
                $this->info("⏰ Current Time: {$currentTime->format('H:i:s')}");
                Log::info("OI+IV Auto Trade - LIVE MODE: {$currentTime->format('Y-m-d H:i:s')}");
            }

            $helper = new OIIVAutoTradingHelper();

            // Step 1: Detect signals (checks today's option_strikes table)
            $this->info("📊 Detecting OI+IV aligned signals...");
            $helper->processSignals($testDate);

            // Step 2: Place orders
            $this->info("📤 Placing orders...");
            $helper->placeOrders($testDate);

            $this->info("✅ OI+IV auto trading completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('OI+IV Auto Trade Error: ' . $e->getMessage());
            return 1;
        }
    }
}