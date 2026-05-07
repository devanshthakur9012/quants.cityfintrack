<?php

namespace App\Console\Commands;

use App\Models\InstrumentChain;
use App\Models\Instrument15MinData;
use App\Traits\AngelApi15MinHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Sync15MinData extends Command
{
    use AngelApi15MinHelper;

    protected $signature = 'instruments:sync-15min {--date= : Specific date (Y-m-d)} {--symbol=* : Specific symbols}';
    protected $description = 'Sync 15-minute interval OHLCV and OI data for instrument chains';

    private const API_DELAY = 1;
    private $processedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;
    private $totalCandles = 0;

    public function handle(): int
    {
        $processDate = $this->getProcessDate();
        $this->info("🚀 Starting 15-min data sync for: {$processDate}\n");

        $dayName = Carbon::parse($processDate)->format('l');

        // Skip weekends
        if (in_array($dayName, ['Saturday', 'Sunday'])) {
            $this->info("Skipped: Weekend ($dayName)");
            return 0;
        }

        // Skip market holidays
        $isHoliday = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $processDate)
            ->exists();

        if ($isHoliday) {
            $this->info("Skipped: Market Holiday ($processDate)");
            return 0;
        }

        try {
            $underlyings = $this->getUnderlyingsToProcess();
            
            if ($underlyings->isEmpty()) {
                $this->warn('❌ No underlyings found to process.');
                return self::SUCCESS;
            }

            $this->info("📊 Found {$underlyings->count()} underlying(s) to process.\n");

            DB::beginTransaction();

            foreach ($underlyings as $underlying) {
                $this->processUnderlying($underlying, $processDate);
                sleep(self::API_DELAY);
            }

            DB::commit();
            
            $this->displaySummary($underlyings->count());

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('15-min data sync critical error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Critical Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function getProcessDate(): string
    {
        if ($this->option('date')) {
            return Carbon::createFromFormat('Y-m-d', $this->option('date'))->format('Y-m-d');
        }
        
        return Carbon::now()->format('Y-m-d');
    }

    private function getUnderlyingsToProcess()
    {
        $symbolOption = $this->option('symbol');

        $query = InstrumentChain::select('underlying')
            ->where('is_active', true)
            ->distinct();

        if (!empty($symbolOption)) {
            $query->whereIn('underlying', $symbolOption);
        } else {
            $defaultSymbols = [
                'AXISBANK', 'BAJFINANCE', 'BHARTIARTL', 'DRREDDY',
                'CIPLA', 'SHRIRAMFIN', 'CHOALFIN', 'PAYTM',
                'NIFTY', 'BANKNIFTY', 'EICHERMOT'
            ];
            $query->whereIn('underlying', $defaultSymbols);
        }

        return $query->get();
    }

    private function processUnderlying($underlyingObj, string $date)
    {
        $underlying = $underlyingObj->underlying;
        $this->line("⏳ Processing: {$underlying}...");

        try {
            $instruments = InstrumentChain::active()
                ->byUnderlying($underlying)
                ->get();

            if ($instruments->isEmpty()) {
                $this->warn("⚠️  Skipped: {$underlying} - No active instruments");
                $this->skippedCount++;
                return;
            }

            $candlesCreated = 0;

            // Process each instrument
            foreach ($instruments as $instrument) {
                $allCandlesData = $this->fetch15MinHistoricalData(
                    $instrument->token, 
                    $date, 
                    $instrument->exchange
                );
                
                if (empty($allCandlesData)) {
                    $this->line("   ⚠️  No data: {$instrument->type}");
                    continue;
                }

                $this->line("   🌐 Fetching {$instrument->type} - " . count($allCandlesData) . " candles");

                // Insert all 15-min candles for this instrument
                foreach ($allCandlesData as $candleData) {
                    $this->insert15MinRecord($instrument, $date, $candleData);
                    $candlesCreated++;
                }
            }

            if ($candlesCreated > 0) {
                $this->info("✅ Success: {$underlying} ({$candlesCreated} candles)");
                $this->processedCount++;
                $this->totalCandles += $candlesCreated;
            } else {
                $this->warn("⚠️  Skipped: {$underlying} - No data available");
                $this->skippedCount++;
            }

        } catch (\Exception $e) {
            Log::error("Failed to process underlying: {$underlying}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Failed: {$underlying} - {$e->getMessage()}");
            $this->failedCount++;
        }
    }

    private function insert15MinRecord($instrument, string $date, array $candleData)
    {
        Instrument15MinData::updateOrCreate(
            [
                'token' => $instrument->token,
                'candle_time' => $candleData['candle_time'],
            ],
            [
                'underlying' => $instrument->underlying,
                'symbol' => $instrument->symbol,
                'type' => $instrument->type,
                'data_date' => $date,
                'open' => $candleData['open'] ?? null,
                'high' => $candleData['high'] ?? null,
                'low' => $candleData['low'] ?? null,
                'close' => $candleData['close'] ?? null,
                'volume' => $candleData['volume'] ?? null,
                'oi' => $candleData['oi'] ?? null,
                'strike_price' => $instrument->strike_price,
                'strike_position' => $instrument->strike_position,
            ]
        );
    }

    private function displaySummary($totalUnderlyings)
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("    15-MIN DATA SYNC SUMMARY        ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Underlyings', $totalUnderlyings],
                ['✅ Successfully Processed', $this->processedCount],
                ['📊 Total Candles Created', $this->totalCandles],
                ['⚠️  Skipped', $this->skippedCount],
                ['❌ Failed', $this->failedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}