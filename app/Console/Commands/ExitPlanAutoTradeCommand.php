<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ExitPlanTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExitPlanAutoTradeCommand extends Command
{
    protected $signature = 'exit-plan:auto-trade
                            {--test-date= : Test date (Y-m-d) for dry-run}';

    protected $description = 'Exit Plan: detect OI reversal at 09:30 AM and place SELL orders for BTST positions';

    public function handle(): int
    {
        $testDate = $this->option('test-date');

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE — Date: {$testDate}");
                Log::info("EXIT PLAN Auto Trade — TEST MODE: {$testDate}");
            } else {
                $now = Carbon::now('Asia/Kolkata');
                $this->info("🚀 EXIT PLAN Auto Trade Running — {$now->format('Y-m-d H:i:s')}");
                Log::info("EXIT PLAN Auto Trade — LIVE: {$now->format('Y-m-d H:i:s')}");
            }

            $helper = new ExitPlanTradingHelper();

            $this->info('📊 Step 1 — Detecting exit signals (09:30 OI vs signal_date 15:15 OI)...');
            $helper->processSignals($testDate);

            $this->info('📤 Step 2 — Placing SELL orders for EXIT decisions...');
            $helper->placeOrders($testDate);

            $this->info('✅ Exit Plan completed!');

            $this->line('');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line('  signal_date     = previous trading day');
            $this->line('  exit_check_date = today 09:30 AM');
            $this->line('  🟢 HOLD    → same OI direction  → no order');
            $this->line('  🔴 EXIT    → OI reversed        → SELL placed');
            $this->line('  🟡 MONITOR → OI neutral         → no order');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('EXIT PLAN Auto Trade Error: ' . $e->getMessage());
            return 1;
        }
    }
}