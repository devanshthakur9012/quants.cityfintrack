<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\McxPECEAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class McxPECEAutoTradeCommand extends Command
{
    protected $signature = 'mcx:pece-auto-trade
                            {--test-date= : Test with a specific date (Y-m-d) instead of today}';

    protected $description = 'MCX EOD CE/PE OI auto trading — detects 23:00 signals and places MCX options orders';

    public function handle(): int
    {
        $testDate = $this->option('test-date');

        try {
            $currentTime = Carbon::now('Asia/Kolkata');

            if ($testDate) {
                $this->info("🧪 TEST MODE | Date: {$testDate}");
                Log::info("MCX CE/PE OI Auto Trade — TEST MODE: {$testDate}");
            } else {
                $this->info("🛢️  MCX EOD CE/PE OI Auto Trading");
                $this->info("⏰ IST: {$currentTime->format('H:i:s')} | Date: {$currentTime->format('Y-m-d')}");
                Log::info("MCX CE/PE OI Auto Trade — LIVE | {$currentTime->format('Y-m-d H:i:s')}");

                // Safety guard: only run after 23:00 IST
                if ($currentTime->hour < 23) {
                    $this->warn("⚠️  Before 23:00 IST — MCX EOD signal not ready yet. Use --test-date for testing.");
                    return 0;
                }
            }

            $helper = new McxPECEAutoTradingHelper();

            // Step 1: detect signals from mcx_ohlc_data
            $this->info("📊 Step 1: Detecting MCX CE/PE OI signals from mcx_ohlc_data...");
            $helper->processSignals($testDate);
            $this->info("✅ Signal detection done");

            // Step 2: place pending orders
            $this->info("📤 Step 2: Placing pending MCX options orders...");
            $helper->placeOrders($testDate);
            $this->info("✅ Order placement done");

            $this->line('');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('🛢️  MCX EOD OI TRADING LOGIC SUMMARY:');
            $this->line('');
            $this->line('  Data source  : mcx_ohlc_data (NOT option_ohlc_data)');
            $this->line('  Configs      : mcx_oiiv_auto_configs');
            $this->line('  Orders       : mcx_oiiv_auto_orders');
            $this->line('  Exchange     : MCX (not NFO)');
            $this->line('');
            $this->line('  Signal logic:');
            $this->line('    • Prev-day 23:00 OI  vs  Today 23:00 OI');
            $this->line('    • CE% and PE% change computed per strike');
            $this->line('    • BULLISH → BUY CE (ATM+1 strike)');
            $this->line('    • BEARISH → BUY PE (ATM-1 strike)');
            $this->line('    • NEUTRAL/WAIT → skip, no order');
            $this->line('');
            $this->line('  Rank thresholds (|CE% - PE%|):');
            $this->line('    Rank 1 → diff > 40  (strongest signal)');
            $this->line('    Rank 2 → diff > 25');
            $this->line('    Rank 3 → diff > 10');
            $this->line('    Rank 4 → diff >  5');
            $this->line('    Normal → diff ≤  5  (skip)');
            $this->line('');
            $this->line('  MCX-specific rules:');
            $this->line('    • Trading days : Mon–Sat (not Mon–Fri)');
            $this->line('    • Market hours : 09:00–23:30 IST');
            $this->line('    • EOD signal   : 23:00 candle close');
            $this->line('    • FUT expiry ≠ option expiry (resolved separately)');
            $this->line('    • Segment      : MCX (not NFO)');
            $this->line('    • Strike interval from mcx_symbols.strike_interval');
            $this->line('    • LTP quote    : MCX:<trading_symbol>');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            return 0;

        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('MCX CE/PE OI Auto Trade Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 1;
        }
    }
}