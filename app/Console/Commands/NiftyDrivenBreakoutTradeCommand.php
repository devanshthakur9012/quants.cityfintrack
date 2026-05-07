<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\NiftyDrivenBreakoutHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NiftyDrivenBreakoutTradeCommand extends Command
{
    protected $signature = 'nifty-breakout:auto-trade
                            {--test-date= : Override date for back-testing (Y-m-d)}
                            {--signals-only : Run signal detection only, skip order placement}
                            {--orders-only  : Run order placement only, skip signal detection}';

    protected $description = 'Auto trade NIFTY-driven multi-symbol breakout signals';

    public function handle(): int
    {
        $testDate    = $this->option('test-date');
        $signalsOnly = $this->option('signals-only');
        $ordersOnly  = $this->option('orders-only');

        try {
            $now = Carbon::now('Asia/Kolkata');
            $this->line('');
            $this->info('╔══════════════════════════════════════════════════════╗');
            $this->info('║     NIFTY-DRIVEN BREAKOUT  AUTO TRADER               ║');
            $this->info('╚══════════════════════════════════════════════════════╝');
            $this->line('');

            if ($testDate) {
                $this->warn("🧪 TEST MODE — Date: {$testDate}");
                Log::info("NiftyDrivenBreakout: TEST MODE date={$testDate}");
            } else {
                $this->info("🚀 LIVE MODE — {$now->format('Y-m-d H:i:s')} IST");
                Log::info("NiftyDrivenBreakout: LIVE MODE {$now->format('Y-m-d H:i:s')}");
            }

            $helper = new NiftyDrivenBreakoutHelper();

            if (!$ordersOnly) {
                $this->info('📊 Step 1 — Scanning NIFTY FUT candles for breakout signals...');
                $helper->processSignals($testDate);
                $this->info('   ✅ Signal detection complete');
            }

            if (!$signalsOnly) {
                $this->info('📤 Step 2 — Placing pending orders...');
                $helper->placeOrders($testDate);
                $this->info('   ✅ Order placement complete');
            }

            $this->line('');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📌 STRATEGY SUMMARY');
            $this->line('');
            $this->line('  Signal source : NIFTY FUT 15-min candles');
            $this->line('  Open ref      : Close of 09:15 candle');
            $this->line('  CE signal     : Close >= Open + threshold');
            $this->line('  PE signal     : Close <= Open - threshold');
            $this->line('  Threshold     : Set per config (dynamic)');
            $this->line('');
            $this->line('  Strike        : Highest-OI CE/PE for each symbol');
            $this->line('  Quantity      : Lots mode OR Investment mode (per config)');
            $this->line('  Stop-loss     : SL/SL-M placed immediately after buy');
            $this->line('  Signal mode   : align (CE→buyCE) OR opposite (CE→buyPE)');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line('');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('NiftyDrivenBreakout Command Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 1;
        }
    }
}