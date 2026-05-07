<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaAutoTradingHelper15min;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ZerodhaAutoTrade15MinCommand extends Command
{
   
    protected $signature = 'zerodha:auto-trade-15min {--test-date= : Test date (Y-m-d)}';

    protected $description = 'Auto trade CE/PE options based on synchronized Supertrend + Donchian signals';

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");
        $testDate = $this->option('test-date');

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE - Date: {$testDate}");
                Log::info("Zerodha Auto Trade - TEST MODE: {$testDate}");
            } else {
                $this->info("🚀 Processing Auto Trading");
                Log::info("Zerodha Auto Trade - LIVE MODE");
            }

            $helper = new ZerodhaAutoTradingHelper15min();

            // Step 1: Process signals
            $this->info("📊 Detecting synchronized signals...");
            $helper->processSignals($testDate);

            // Step 2: Place orders
            $this->info("📤 Placing orders...");
            $helper->placeOrders($testDate);

            $this->info("✅ Auto trading completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Zerodha Auto Trade Error: ' . $e->getMessage());
            return 1;
        }
    }
}
