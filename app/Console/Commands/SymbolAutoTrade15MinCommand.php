<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\SymbolAutoTradingHelper15min;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SymbolAutoTrade15MinCommand extends Command
{
    protected $signature = 'symbol:auto-trade-15min {--test-date= : Test date (Y-m-d H:i:s)}';

    protected $description = 'Auto trade CE/PE options for symbols based on 15-min Supertrend + VWAP signals';

    public function handle()
    {
        $testDate = $this->option('test-date');

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE - Date: {$testDate}");
                Log::info("Symbol Auto Trade 15-Min - TEST MODE: {$testDate}");
            } else {
                $this->info("🚀 Processing Symbol Auto Trading (15-Min)");
                Log::info("Symbol Auto Trade 15-Min - LIVE MODE");
            }

            $helper = new SymbolAutoTradingHelper15min();

            // Step 1: Process signals
            $this->info("📊 Detecting synchronized signals...");
            $helper->processSignals($testDate);

            // Step 2: Place orders
            $this->info("📤 Placing orders...");
            $helper->placeOrders($testDate);

            $this->info("✅ Symbol auto trading completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Symbol Auto Trade 15-Min Error: ' . $e->getMessage());
            return 1;
        }
    }
}