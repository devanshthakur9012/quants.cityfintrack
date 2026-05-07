<?php

namespace App\Console\Commands;

use App\Models\InstrumentChain;
use App\Models\InstrumentHistoricalDataNew;
use App\Traits\ZerodhaApiHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StoreZerodhaHistoricalData extends Command
{
    use ZerodhaApiHelper;

    protected $signature = 'zerodha:store-historical 
                            {--date= : Specific date (Y-m-d)}
                            {--days=1 : Number of days}
                            {--symbol=* : Specific symbols}
                            {--backfill : Fill missing data}';
    
    protected $description = 'Store historical OHLCV+OI data in new table';

    private $storedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;

    public function handle()
    {
        set_time_limit(0);
        
        $this->info("🚀 Starting Historical Data Storage...\n");

        try {
            $dates = $this->getDatesToProcess();
            $instruments = $this->getInstrumentsToProcess();

            if ($instruments->isEmpty()) {
                $this->error('❌ No active instruments found.');
                return 1;
            }

            $this->info("📊 Processing {$instruments->count()} instruments for " . count($dates) . " date(s)\n");

            foreach ($dates as $date) {
                $this->line("📅 Processing: {$date}");
                $this->processDate($date, $instruments);
            }

            $this->displaySummary();

        } catch (\Exception $e) {
            Log::error('Historical storage error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function getDatesToProcess(): array
    {
        $dates = [];
        
        if ($this->option('date')) {
            $dates[] = Carbon::parse($this->option('date'))->format('Y-m-d');
        } else {
            $days = (int) $this->option('days');
            $startDate = Carbon::now()->subDays($days);
            
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                
                if ($date->isWeekend()) {
                    continue;
                }
                
                $isHoliday = DB::table('market_holidays')
                    ->where('market_name', 'NSE')
                    ->where('holiday_date', $date->format('Y-m-d'))
                    ->exists();
                
                if (!$isHoliday) {
                    $dates[] = $date->format('Y-m-d');
                }
            }
        }

        return $dates;
    }

    private function getInstrumentsToProcess()
    {
        $query = InstrumentChain::where('is_active', true)
            ->where('data_source', 'zerodha')
            ->whereNotNull('token');

        if ($this->option('symbol')) {
            $query->whereIn('underlying', $this->option('symbol'));
        }

        return $query->get(['id', 'underlying', 'symbol', 'token', 'type', 'strike_price', 'expiry_date']);
    }

    private function processDate(string $date, $instruments)
    {
        $batches = $instruments->chunk(100);
        
        foreach ($batches as $batchIndex => $batch) {
            $this->line("  ⏳ Batch " . ($batchIndex + 1) . "...");
            
            $tokens = $batch->pluck('token')->toArray();
            $ohlcData = $this->getZerodhaOHLC($tokens, 'NFO');
            
            foreach ($batch as $instrument) {
                $this->processInstrument($instrument, $date, $ohlcData);
            }
            
            sleep(1); // Rate limiting
        }
    }

    private function processInstrument($instrument, string $date, array $ohlcData)
    {
        try {
            $exists = InstrumentHistoricalDataNew::where('instrument_chain_id', $instrument->id)
                ->where('date', $date)
                ->exists();

            if ($exists && !$this->option('backfill')) {
                $this->skippedCount++;
                return;
            }

            $key = "NFO:{$instrument->token}";
            $ohlc = $ohlcData[$key] ?? null;

            if (!$ohlc || !$this->validateOHLC($ohlc)) {
                $this->failedCount++;
                return;
            }

            // Get historical with OI
            $historicalData = $this->getZerodhaHistoricalData(
                $instrument->token,
                $date . ' 09:15:00',
                $date . ' 15:30:00',
                'day',
                'NFO'
            );

            $oi = null;
            if (!empty($historicalData)) {
                $latestCandle = end($historicalData);
                $oi = $latestCandle['oi'] ?? null;
            }

            $this->storeData($instrument, $date, $ohlc, $oi, $exists);
            $this->storedCount++;

        } catch (\Exception $e) {
            Log::error("Process instrument failed: {$instrument->symbol}", [
                'error' => $e->getMessage()
            ]);
            $this->failedCount++;
        }
    }

    private function validateOHLC(array $ohlc): bool
    {
        if (!isset($ohlc['ohlc'])) return false;
        
        $values = $ohlc['ohlc'];
        
        if (empty($values['open']) || $values['open'] <= 0) return false;
        if (empty($values['high']) || $values['high'] <= 0) return false;
        if (empty($values['low']) || $values['low'] <= 0) return false;
        if (empty($values['close']) || $values['close'] <= 0) return false;
        
        if ($values['high'] < $values['low']) return false;
        if ($values['high'] < $values['open'] || $values['high'] < $values['close']) return false;
        if ($values['low'] > $values['open'] || $values['low'] > $values['close']) return false;
        
        return true;
    }

    private function storeData($instrument, string $date, array $ohlc, ?int $oi, bool $update)
    {
        $ohlcValues = $ohlc['ohlc'];
        $volume = $ohlc['volume'] ?? 0;
        $ltp = $ohlc['last_price'] ?? $ohlcValues['close'];

        // Calculate price changes
        $priceChange = null;
        $priceChangePercent = null;
        $oiChange = null;
        $oiChangePercent = null;

        $prevData = InstrumentHistoricalDataNew::where('instrument_chain_id', $instrument->id)
            ->where('date', '<', $date)
            ->orderBy('date', 'desc')
            ->first();

        if ($prevData) {
            $priceChange = $ohlcValues['close'] - $prevData->close;
            $priceChangePercent = ($priceChange / $prevData->close) * 100;
            
            if ($oi && $prevData->oi) {
                $oiChange = $oi - $prevData->oi;
                $oiChangePercent = ($oiChange / $prevData->oi) * 100;
            }
        }

        $data = [
            'instrument_chain_id' => $instrument->id,
            'date' => $date,
            'underlying' => $instrument->underlying,
            'symbol' => $instrument->symbol,
            'type' => $instrument->type,
            'strike_price' => $instrument->strike_price,
            'open' => (float) $ohlcValues['open'],
            'high' => (float) $ohlcValues['high'],
            'low' => (float) $ohlcValues['low'],
            'close' => (float) $ohlcValues['close'],
            'ltp' => (float) $ltp,
            'volume' => (int) $volume,
            'oi' => $oi,
            'price_change' => $priceChange,
            'price_change_percent' => $priceChangePercent,
            'oi_change' => $oiChange,
            'oi_change_percent' => $oiChangePercent,
            'data_quality_score' => $this->calculateQualityScore($ohlcValues, $volume, $oi),
            'updated_at' => now()
        ];

        if ($update) {
            InstrumentHistoricalDataNew::where('instrument_chain_id', $instrument->id)
                ->where('date', $date)
                ->update($data);
        } else {
            $data['created_at'] = now();
            InstrumentHistoricalDataNew::insert($data);
        }
    }

    private function calculateQualityScore(array $ohlc, int $volume, ?int $oi): float
    {
        $score = 100.0;

        if (empty($ohlc['open']) || $ohlc['open'] <= 0) $score -= 25;
        if (empty($ohlc['high']) || $ohlc['high'] <= 0) $score -= 25;
        if (empty($ohlc['low']) || $ohlc['low'] <= 0) $score -= 25;
        if (empty($ohlc['close']) || $ohlc['close'] <= 0) $score -= 25;
        if ($volume <= 0) $score -= 10;
        if ($oi === null) $score -= 10;

        if ($ohlc['open'] == $ohlc['high'] && $ohlc['high'] == $ohlc['low'] && $ohlc['low'] == $ohlc['close']) {
            $score -= 20;
        }

        return max(0, $score);
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("   HISTORICAL STORAGE SUMMARY  ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Stored', $this->storedCount],
                ['⚠️  Skipped', $this->skippedCount],
                ['❌ Failed', $this->failedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}