<?php

namespace App\Console\Commands;
 
use Illuminate\Console\Command;
use App\Helpers\FutContrarianTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
 
class FutContrarian30MinCommand extends Command
{
    protected $signature = 'fut-contrarian:30min {--test-date= : Override date Y-m-d}';
 
    protected $description = 'FUT Contrarian — OI-30min window (prev 15:15 vs 09:45). Buy @ 10:00 open.';
 
    public function handle(): int
    {
        $testDate = $this->option('test-date');
        $now      = Carbon::now('Asia/Kolkata');
 
        if ($testDate) {
            $this->info("🧪 TEST MODE — Date: {$testDate}");
        } else {
            $this->info("🚀 FUT Contrarian 30-min OI Signal | {$now->format('Y-m-d H:i:s')}");
        }
 
        $this->info("📊 Signal: FUT direction + OI comparison (prev 15:15 vs today 09:45)");
        $this->info("🎯 FUT UP → BUY PE  |  FUT DOWN → BUY CE  (contrarian)");
        $this->info("⏰ Buy price = today 10:00 candle open");
        $this->line('');
 
        try {
            $helper = new FutContrarianTradingHelper();
            $helper->run($testDate, '30min');
 
            $this->info("✅ FUT Contrarian 30-min completed!");
            $this->printLogic();
            return 0;
 
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('FutContrarian30Min: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return 1;
        }
    }
 
    private function printLogic(): void
    {
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 FUT CONTRARIAN 30-MIN LOGIC:');
        $this->line('');
        $this->line('  1. FUT direction: prev 15:00 close vs today 09:30 open');
        $this->line('     • FUT UP   → action = BUY PE');
        $this->line('     • FUT DOWN → action = BUY CE');
        $this->line('');
        $this->line('  2. OI alignment check (prev 15:15 vs today 09:45):');
        $this->line('     • BUY CE needs BULLISH OI (CE↓ + PE↑)');
        $this->line('     • BUY PE needs BEARISH OI (CE↑ + PE↓)');
        $this->line('');
        $this->line('  3. Buy at 10:00 candle open (ATM/±1 highest OI strike)');
        $this->line('  4. Sells NOT managed here — manual or next system');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}