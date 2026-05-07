<?php

namespace App\Console\Commands;

use App\Models\FuturesInstrument;
use App\Models\Futures15MinCandle;
use App\Traits\IndependentAngelApiTrait;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncFuturesCandles extends Command
{
    use IndependentAngelApiTrait;

    protected $signature = 'futures:sync-candles {--date= : Date (Y-m-d)} {--days= : Last X days} {--symbol=* : Symbols}';
    protected $description = 'Sync 15-min candle data for futures';

    private $processedCount = 0;
    private $failedCount = 0;
    private $totalCandles = 0;

    public function handle(): int
    {
        $this->info("🚀 Starting Futures Candles Sync...\n");

        try {
            $dates = $this->getDatesToProcess();
            
            if (empty($dates)) {
                $this->error('❌ No valid dates to process');
                return 1;
            }

            $this->info("📅 Processing " . count($dates) . " date(s): " . implode(', ', $dates) . "\n");

            foreach ($dates as $date) {
                $this->processDate($date);
            }

            $this->displaySummary();

        } catch (\Exception $e) {
            Log::error('Futures candles sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Critical Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function getDatesToProcess(): array
    {
        $dates = [];

        if ($this->option('date')) {
            $dates[] = Carbon::createFromFormat('Y-m-d', $this->option('date'))->format('Y-m-d');
        } elseif ($this->option('days')) {
            $days = (int) $this->option('days');
            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');
                $dayName = Carbon::parse($date)->format('l');
                
                // Skip weekends
                if (!in_array($dayName, ['Saturday', 'Sunday'])) {
                    $dates[] = $date;
                }
            }
        } else {
            $dates[] = Carbon::now()->format('Y-m-d');
        }

        return $dates;
    }

    private function processDate(string $date)
    {
        $this->info("\n📆 Processing Date: {$date}");

        $instruments = $this->getInstruments();

        if ($instruments->isEmpty()) {
            $this->warn("⚠️  No active futures instruments found");
            return;
        }

        $this->line("   📊 Found {$instruments->count()} futures contract(s)");

        foreach ($instruments as $instrument) {
            $this->processInstrument($instrument, $date);
            sleep(1); // API rate limit
        }
    }

    private function getInstruments()
    {
        $symbolOption = $this->option('symbol');

        $query = FuturesInstrument::active();

        if (!empty($symbolOption)) {
            $query->whereIn('underlying', $symbolOption);
        }

        return $query->get();
    }

    private function processInstrument($instrument, string $date)
    {
        $this->line("   ⏳ {$instrument->underlying}...");

        try {
            $candlesData = $this->fetchFutures15MinData($instrument->token, $date, $instrument->exchange);
            
            if (empty($candlesData)) {
                $this->line("      ⚠️  No data available");
                return;
            }

            $candleCount = 0;

            foreach ($candlesData as $candleData) {
                Futures15MinCandle::updateOrCreate(
                    [
                        'token' => $instrument->token,
                        'candle_time' => $candleData['candle_time'],
                    ],
                    [
                        'underlying' => $instrument->underlying,
                        'symbol' => $instrument->symbol,
                        'data_date' => $date,
                        'open' => $candleData['open'],
                        'high' => $candleData['high'],
                        'low' => $candleData['low'],
                        'close' => $candleData['close'],
                        'volume' => $candleData['volume'],
                        'oi' => $candleData['oi'],
                    ]
                );
                $candleCount++;
            }

            $this->line("      ✅ {$candleCount} candles");
            $this->processedCount++;
            $this->totalCandles += $candleCount;

        } catch (\Exception $e) {
            Log::error("Failed to process instrument: {$instrument->underlying}", [
                'error' => $e->getMessage()
            ]);
            $this->line("      ❌ Failed");
            $this->failedCount++;
        }
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("   FUTURES CANDLES SYNC SUMMARY     ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Processed', $this->processedCount],
                ['📊 Total Candles', $this->totalCandles],
                ['❌ Failed', $this->failedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}