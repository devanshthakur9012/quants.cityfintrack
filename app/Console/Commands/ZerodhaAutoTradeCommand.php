<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ZerodhaAutoTradeCommand extends Command
{
    protected $signature = 'zerodha:auto-trade {--test-date= : Test date (Y-m-d)}';

    protected $description = 'Auto trade CE/PE options based on synchronized Supertrend + Donchian signals';

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");
        $testDate = $this->option('test-date');

        // Skip weekends unless testing
        // if (!$testDate) {
        //     if ($dayName == "Saturday" || $dayName == "Sunday") {
        //         $this->info("Skipped: Weekend ($dayName)");
        //         Log::info("Auto trade skipped: Weekend");
        //         return 0;
        //     }

        //     // Skip market holidays
        //     $isHoliday = \DB::table('market_holidays')
        //         ->where('market_name', 'NSE')
        //         ->where('holiday_date', $today)
        //         ->exists();

        //     if ($isHoliday) {
        //         $this->info("Skipped: Market Holiday ($today)");
        //         Log::info("Auto trade skipped: Market Holiday");
        //         return 0;
        //     }

        //     // Check market hours (9:15 AM to 3:30 PM IST)
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);

        //     if (!$currentTime->between($marketOpen, $marketClose)) {
        //         $this->info("Skipped: Outside market hours");
        //         return 0;
        //     }
        // }

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE - Date: {$testDate}");
                Log::info("Zerodha Auto Trade - TEST MODE: {$testDate}");
            } else {
                $this->info("🚀 Processing Auto Trading");
                Log::info("Zerodha Auto Trade - LIVE MODE");
            }

            $helper = new ZerodhaAutoTradingHelper();

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