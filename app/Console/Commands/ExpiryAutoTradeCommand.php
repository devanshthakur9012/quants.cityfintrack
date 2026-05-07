<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ExpiryAutoTradingHelper;
use App\Models\ExpiryMonitored;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExpiryAutoTradeCommand extends Command
{
    protected $signature = 'expiry:auto-trade {--test-date= : Test date (Y-m-d)}';

    protected $description = 'Auto trade CE/PE options based on Supertrend signals (ONLY on expiry day, 1-minute interval)';

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");
        $testDate = $this->option('test-date');

        // Skip weekends unless testing
        if (!$testDate) {
            if ($dayName == "Saturday" || $dayName == "Sunday") {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("Expiry auto trade skipped: Weekend");
                return 0;
            }

            // Skip market holidays
            $isHoliday = \DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Expiry auto trade skipped: Market Holiday");
                return 0;
            }

            // ✅ CRITICAL CHECK: Are there any symbols expiring today?
            $expiringToday = ExpiryMonitored::getExpiringToday();
            
            if ($expiringToday->isEmpty()) {
                $this->info("⚠️ No symbols expiring today - skipping expiry auto trade");
                Log::info("Expiry auto trade skipped: No symbols expiring today");
                return 0;
            }

            // Check market hours (9:15 AM to 3:30 PM IST)
            $currentTime = Carbon::now('Asia/Kolkata');
            $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
            $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);

            if (!$currentTime->between($marketOpen, $marketClose)) {
                $this->info("Skipped: Outside market hours");
                return 0;
            }

            $this->info("🎯 EXPIRY DAY! Found {$expiringToday->count()} symbols expiring today:");
            foreach ($expiringToday as $symbol) {
                $closestExpiry = $symbol->getClosestExpiry();
                $this->info("  - {$symbol->symbol} (Expiry: {$closestExpiry->format('d M Y')})");
            }
        }

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE - Date: {$testDate}");
                Log::info("Expiry Auto Trade - TEST MODE: {$testDate}");
            } else {
                $this->info("🚀 Processing Expiry Auto Trading (1-Minute)");
                Log::info("Expiry Auto Trade - LIVE MODE");
            }

            $helper = new ExpiryAutoTradingHelper();

            // Step 1: Process signals
            $this->info("📊 Detecting Supertrend signals...");
            $helper->processSignals($testDate);

            // Step 2: Place orders
            $this->info("📤 Placing orders...");
            $helper->placeOrders($testDate);

            $this->info("✅ Expiry auto trading completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Expiry Auto Trade Error: ' . $e->getMessage());
            return 1;
        }
    }
}