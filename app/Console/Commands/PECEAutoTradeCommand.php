<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\PECEAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PECEAutoTradeCommand extends Command
{
    protected $signature = 'pece:auto-trade {--test-date= : Test date (Y-m-d)}';
    
    protected $description = 'Auto trade based on CE/PE OI Change signals (runs 3:00-3:30 PM)';

    public function handle()
    {
        $testDate = $this->option('test-date');

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE - Date: {$testDate}");
                Log::info("CE/PE OI Change Auto Trade - TEST MODE: {$testDate}");
            } else {
                $currentTime = Carbon::now('Asia/Kolkata');
                $this->info("🚀 Processing CE/PE OI Change Auto Trading");
                $this->info("⏰ Current Time: {$currentTime->format('H:i:s')}");
                Log::info("CE/PE OI Change Auto Trade - LIVE MODE: {$currentTime->format('Y-m-d H:i:s')}");
            }

            $helper = new PECEAutoTradingHelper();

            // Step 1: Detect signals (reads oi_condition and trade_action from option_strikes)
            $this->info("📊 Detecting CE/PE OI Change signals...");
            $this->info("📌 Reading trade_action from database...");
            $helper->processSignals($testDate);

            // Step 2: Place orders
            $this->info("📤 Placing orders...");
            $helper->placeOrders($testDate);

            $this->info("✅ CE/PE OI Change auto trading completed!");
            
            $this->line('');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📊 CE/PE OI CHANGE TRADING LOGIC:');
            $this->line('');
            $this->line('  1. Reads oi_condition from option_strikes table');
            $this->line('  2. Reads ce_oi_change_pct and pe_oi_change_pct');
            $this->line('  3. Reads trade_action field:');
            $this->line('     • BUY CE → Places CE order (Call unwinding + Put buildup)');
            $this->line('     • BUY PE → Places PE order (Call buildup + Put unwinding)');
            $this->line('     • WAIT → Skips (no clear signal)');
            $this->line('');
            $this->line('  4. Uses locked prices (9:30 AM open, 3:00 PM close)');
            $this->line('  5. Validates final_sentiment before order creation');
            $this->line('  6. PE/CE Ratio is kept for reference only');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('CE/PE OI Change Auto Trade Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}