<?php

namespace App\Console\Commands;

use App\Models\FuturesInstrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncFuturesInstruments extends Command
{
    protected $signature = 'futures:sync-instruments {--symbol=* : Specific symbols}';
    protected $description = 'Sync futures instruments from angel_api_instruments table';

    private $processedCount = 0;
    private $skippedCount = 0;

    public function handle(): int
    {
        $this->info("🚀 Starting Futures Instruments Sync...\n");

        $today = date('Y-m-d');
        $dayName = date('l');

        // Skip weekends
        if (in_array($dayName, ['Saturday', 'Sunday'])) {
            $this->info("Skipped: Weekend ($dayName)");
            return 0;
        }

        // Skip holidays
        $isHoliday = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $today)
            ->exists();

        if ($isHoliday) {
            $this->info("Skipped: Market Holiday ($today)");
            return 0;
        }

        try {
            $symbols = $this->getSymbolsToProcess();
            
            if (empty($symbols)) {
                $this->error('❌ No symbols to process');
                return 1;
            }

            $this->info("📊 Processing " . count($symbols) . " symbol(s)...\n");

            foreach ($symbols as $symbol) {
                $this->processSymbol($symbol);
            }

            $this->displaySummary();

        } catch (\Exception $e) {
            Log::error('Futures instrument sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Critical Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function getSymbolsToProcess(): array
    {
        $symbolOption = $this->option('symbol');

        if (!empty($symbolOption)) {
            return $symbolOption;
        }

        return [
            'NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY',
            'AXISBANK', 'BAJFINANCE', 'BHARTIARTL', 'DRREDDY',
            'CIPLA', 'SHRIRAMFIN', 'CHOLAMANDALAM', 'PAYTM', 'EICHERMOT'
        ];
    }

    private function processSymbol(string $symbol)
    {
        $this->line("⏳ Processing: {$symbol}...");

        try {
            $instrumentType = in_array($symbol, ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY']) 
                ? 'FUTIDX' 
                : 'FUTSTK';

            $future = DB::table('angel_api_instruments')
                ->where('name', $symbol)
                ->where('instrumenttype', $instrumentType)
                ->where('exch_seg', 'NFO')
                ->orderByRaw("STR_TO_DATE(expiry_raw, '%d%b%Y') ASC")
                ->first();

            if (!$future) {
                $this->warn("⚠️  Skipped: {$symbol} - No futures contract found");
                $this->skippedCount++;
                return;
            }

            // Parse expiry date
            $expiryDate = null;
            if (!empty($future->expiry_raw)) {
                try {
                    $expiryDate = Carbon::createFromFormat('dMY', strtoupper($future->expiry_raw))->format('Y-m-d');
                } catch (\Exception $e) {
                    $expiryDate = null;
                }
            }

            // Deactivate old records
            FuturesInstrument::deactivateAll($symbol);

            // Insert new record
            FuturesInstrument::create([
                'underlying' => $symbol,
                'symbol' => $future->symbol_name,
                'token' => $future->token,
                'exchange' => $future->exch_seg ?? 'NFO',
                'expiry_date' => $expiryDate,
                'lot_size' => $future->lotsize ?? null,
                'tick_size' => $future->tick_size ?? null,
                'instrument_type' => $instrumentType,
                'is_active' => true,
                'last_synced_at' => now()
            ]);

            $this->info("✅ Success: {$symbol}");
            $this->processedCount++;

        } catch (\Exception $e) {
            Log::error("Failed to process symbol: {$symbol}", [
                'error' => $e->getMessage()
            ]);
            $this->error("❌ Failed: {$symbol}");
            $this->skippedCount++;
        }
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("   FUTURES INSTRUMENTS SYNC SUMMARY  ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Processed', $this->processedCount],
                ['⚠️  Skipped', $this->skippedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}