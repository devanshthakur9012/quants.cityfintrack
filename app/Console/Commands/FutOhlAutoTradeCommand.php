<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\FutOhlAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FutOhlAutoTradeCommand
 *
 * Runs at 9:20 AM IST (after 9:15 candle is collected).
 * Detects Open=High / Open=Low on 9:15 FUT candle and places options orders.
 *
 * Schedule (Kernel.php):
 *   $schedule->command('fut-ohl:auto-trade')
 *       ->dailyAt('09:20')
 *       ->timezone('Asia/Kolkata')
 *       ->weekdays()
 *       ->when(fn() => DB::table('option_ohlc_data')
 *           ->whereDate('trade_date', now('Asia/Kolkata')->toDateString())
 *           ->where('instrument_type', 'FUT')
 *           ->whereRaw("TIME(interval_time) = '09:15:00'")
 *           ->exists())
 *       ->withoutOverlapping(5);
 */
class FutOhlAutoTradeCommand extends Command
{
    protected $signature = 'fut-ohl:auto-trade
                            {--test-date= : Override trade date (Y-m-d) for backtesting}
                            {--detect-only : Only detect and create order records, do not place}
                            {--place-only  : Only place existing pending order records}';

    protected $description = 'FUT Open=High/Low auto trading — detects 9:15 signals and places NFO options orders';

    public function handle(): int
    {
        $testDate    = $this->option('test-date');
        $detectOnly  = $this->option('detect-only');
        $placeOnly   = $this->option('place-only');
        $currentTime = Carbon::now('Asia/Kolkata');

        try {
            if ($testDate) {
                $this->info("🧪 TEST MODE | Date: {$testDate}");
                Log::info("FUT OHL Auto Trade — TEST MODE: {$testDate}");
            } else {
                $this->info("📈 FUT Open=High / Open=Low Auto Trading");
                $this->info("⏰ IST: {$currentTime->format('H:i:s')} | {$currentTime->format('Y-m-d')}");
                Log::info("FUT OHL Auto Trade — LIVE | {$currentTime->format('Y-m-d H:i:s')}");

                // Safety: only run between 9:15 and 9:45
                if ($currentTime->hour < 9 || ($currentTime->hour === 9 && $currentTime->minute < 15)) {
                    $this->warn("⚠️  Before 9:15 AM — 9:15 candle not ready. Use --test-date for backtesting.");
                    return 0;
                }
            }

            $helper  = new FutOhlAutoTradingHelper();
            $summary = ['detected' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0, 'placed' => 0, 'failed' => 0];

            if (!$placeOnly) {
                $this->info("📊 Step 1: Detecting 9:15 Open=High/Low signals...");
                $s = $helper->processSignals($testDate);
                $summary = array_merge($summary, $s);
                $this->line("  → Detected: {$s['detected']} | Created: {$s['created']} | Skipped: {$s['skipped']} | Errors: {$s['errors']}");
            }

            if (!$detectOnly) {
                $this->info("📤 Step 2: Placing pending orders...");
                $s = $helper->placeOrders($testDate);
                $summary['placed'] = $s['placed'];
                $summary['failed'] = $s['failed'];
                $this->line("  → Placed: {$s['placed']} | Failed: {$s['failed']}");
            }

            $this->info("✅ FUT OHL auto trading complete!");

            $this->line('');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📈 FUT OPEN=HIGH / OPEN=LOW LOGIC:');
            $this->line('');
            $this->line('  Signal source  : option_ohlc_data (9:15 FUT candle)');
            $this->line('  Config table   : fut_ohl_auto_configs');
            $this->line('  Orders table   : fut_ohl_auto_orders');
            $this->line('  Exchange       : NFO');
            $this->line('');
            $this->line('  Rules:');
            $this->line('    |Open − High| ≤ tolerance → OPEN=HIGH → BUY PE (default)');
            $this->line('    |Open − Low|  ≤ tolerance → OPEN=LOW  → BUY CE (default)');
            $this->line('    Opposite mode reverses CE/PE direction');
            $this->line('');
            $this->line('  Option selection:');
            $this->line('    CE → ATM+1 strike (OTM call)');
            $this->line('    PE → ATM-1 strike (OTM put)');
            $this->line('    Nearest NFO expiry (or next if configured)');
            $this->line('');
            $this->line('  Flags:');
            $this->line('    --detect-only  → create records, do NOT place orders');
            $this->line('    --place-only   → skip detection, place existing pending');
            $this->line('    --test-date=Y-m-d → backtest any date');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            return 0;

        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('FUT OHL Auto Trade Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 1;
        }
    }
}