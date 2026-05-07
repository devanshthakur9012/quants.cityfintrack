<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\PECE9to12AutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PECE9to12AutoTradeCommand extends Command
{
    protected $signature = 'pece:9to12-auto-trade {--test-date= : Test date (Y-m-d)}';

    protected $description = 'Auto trade based on 9:15 AM → 12:15 PM CE/PE OI Change signals (runs 12:15-12:30 PM)';

    public function handle()
    {
        $testDate = $this->option('test-date');

        try {
            if ($testDate) {
                $this->info("TEST MODE - Date: {$testDate}");
                Log::info("9to12 CE/PE OI Change Auto Trade - TEST MODE: {$testDate}");
            } else {
                $currentTime = Carbon::now('Asia/Kolkata');
                $this->info("Processing 9to12 CE/PE OI Change Auto Trading");
                $this->info("Current Time: {$currentTime->format('H:i:s')}");
                Log::info("9to12 CE/PE OI Change Auto Trade - LIVE MODE: {$currentTime->format('Y-m-d H:i:s')}");
            }

            $helper = new PECE9to12AutoTradingHelper();

            // Step 1: Detect signals (reads CE/PE OI change % from option_strike_9to12)
            $this->info("Detecting 9to12 CE/PE OI Change signals...");
            $this->info("Reading data from option_strike_9to12 table...");
            $helper->processSignals($testDate);

            // Step 2: Place orders
            $this->info("Placing orders...");
            $helper->placeOrders($testDate);

            $this->info("9to12 CE/PE OI Change auto trading completed!");

            $this->line('');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('9to12 CE/PE OI CHANGE TRADING LOGIC:');
            $this->line('');
            $this->line('  1. Reads ce_oi_change_pct and pe_oi_change_pct from option_strike_9to12');
            $this->line('  2. Computes rank from |CE% - PE%|:');
            $this->line('     • Rank 1 (diff > 40) → Strongest signal');
            $this->line('     • Rank 2 (diff > 25) → Strong signal');
            $this->line('     • Rank 3 (diff > 10) → Moderate signal');
            $this->line('     • Rank 4 (diff > 5)  → Weak signal');
            $this->line('     • Normal (diff <= 5)  → SKIP');
            $this->line('');
            $this->line('  3. Determines direction:');
            $this->line('     • CE% > PE% by rank threshold → BEARISH → BUY PE');
            $this->line('     • PE% > CE% by rank threshold → BULLISH → BUY CE');
            $this->line('');
            $this->line('  4. Applies config signal_mode:');
            $this->line('     • ALIGN    → follow direction');
            $this->line('     • OPPOSITE → reverse direction');
            $this->line('');
            $this->line('  5. Uses 12:15 PM FUT close (current_close) as spot for ATM option');
            $this->line('  6. Only processes configs with config_type = "9to12"');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('9to12 CE/PE OI Change Auto Trade Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}