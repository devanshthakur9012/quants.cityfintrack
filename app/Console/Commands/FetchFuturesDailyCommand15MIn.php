<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaHelper;
use App\Helpers\SupertrendCalculator;
use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchFuturesDailyCommand15MIn extends Command
{
    protected $signature = 'futures:fetch-daily-old
                            {--symbol= : Specific symbol to fetch} 
                            {--force : Force fetch even on holidays}
                            {--atr-period=10 : ATR period for Supertrend}
                            {--multiplier=3 : Multiplier for Supertrend}';

    protected $description = 'Fetch daily futures data from Zerodha with Supertrend calculation';

    private $zerodha;
    private $atrPeriod;
    private $multiplier;

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        // Skip Saturday & Sunday
        if (!$this->option('force')) {
            if ($dayName == "Saturday" || $dayName == "Sunday") {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("Futures data fetch skipped: Weekend ($dayName)");
                return 0;
            }

            // Skip if market holiday
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Futures data fetch skipped: Market Holiday ($today)");
                return 0;
            }
        }

        try {
            $this->zerodha = new ZerodhaHelper();
            $this->atrPeriod = $this->option('atr-period');
            $this->multiplier = $this->option('multiplier');

            if ($this->option('symbol')) {
                $futures = FuturesMonitored::where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%')
                    ->where('is_active', true)
                    ->get();
            } else {
                $futures = FuturesMonitored::getActiveFutures();
            }

            if ($futures->isEmpty()) {
                $this->warn('No monitored futures found!');
                return 0;
            }

            $this->info("📊 Fetching futures data for " . $futures->count() . " contract(s)...\n");

            foreach ($futures as $future) {
                $this->info("Processing: {$future->trading_symbol}");
                $this->fetchFuturesData($future);
            }

            $this->info("\n✅ Futures data fetch completed successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('Futures Daily Data Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function fetchFuturesData(FuturesMonitored $future)
    {
        try {
            $intervals = $future->intervals_array;

            foreach ($intervals as $interval) {
                $this->info("  - Fetching {$interval} data...");

                $fromDate = $this->getFromDate($future, $interval);
                $toDate = date('Y-m-d H:i:s');

                $data = $this->zerodha->getHistoricalDataByToken(
                    $future->instrument_token,
                    $interval,
                    $fromDate,
                    $toDate
                );

                if (empty($data)) {
                    $this->warn("    No data received for {$interval}");
                    continue;
                }

                $insertedCount = $this->storeHistoricalData($future, $data, $interval);
                $this->info("    ✓ Inserted/Updated {$insertedCount} records");

                if ($interval === '15minute' && $insertedCount > 0) {
                    $this->calculateSupertrendForInterval($future, $interval);
                    $this->info("    ✓ Supertrend calculated for {$interval}");
                }
            }

            $future->update(['last_fetched_at' => now()]);

        } catch (Exception $e) {
            $this->error("  ✗ Error fetching {$future->trading_symbol}: " . $e->getMessage());
            Log::error("Error fetching {$future->trading_symbol}: " . $e->getMessage());
        }
    }

    private function getFromDate(FuturesMonitored $future, $interval)
    {
        if (!$future->last_fetched_at) {
            return date('Y-m-d H:i:s', strtotime('-7 days'));
        }

        $lastRecord = FuturesData::where('trading_symbol', $future->trading_symbol)
            ->where('interval', $interval)
            ->orderBy('timestamp', 'desc')
            ->first();

        if ($lastRecord) {
            return $lastRecord->timestamp->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', strtotime('-7 days'));
    }

    private function storeHistoricalData(FuturesMonitored $future, array $data, string $interval)
    {
        $insertedCount = 0;

        foreach ($data as $candle) {
            try {
                // The Zerodha API returns date as DateTime object or string
                // We need to use it exactly as provided
                $candleDate = $candle->date;
                
                // Convert to Carbon instance
                if ($candleDate instanceof \DateTime) {
                    $timestamp = Carbon::instance($candleDate);
                } else {
                    $timestamp = Carbon::parse($candleDate);
                }
                
                if ($timestamp->isWeekend()) {
                    continue;
                }

                if ($this->isMarketHoliday($timestamp->format('Y-m-d'))) {
                    continue;
                }

                $time = $timestamp->format('H:i:s');
                if ($time < '09:15:00' || $time > '15:30:00') {
                    continue;
                }

                // Use the exact timestamp from Zerodha
                FuturesData::updateOrCreate(
                    [
                        'trading_symbol' => $future->trading_symbol,
                        'exchange' => $future->exchange,
                        'interval' => $interval,
                        'timestamp' => $timestamp->format('Y-m-d H:i:s')
                    ],
                    [
                        'instrument_token' => $future->instrument_token,
                        'open' => $candle->open,
                        'high' => $candle->high,
                        'low' => $candle->low,
                        'close' => $candle->close,
                        'volume' => $candle->volume,
                        'oi' => $candle->oi ?? 0
                    ]
                );

                $insertedCount++;

            } catch (Exception $e) {
                Log::error("Error storing futures candle data: " . $e->getMessage() . " - Candle: " . json_encode($candle));
            }
        }

        return $insertedCount;
    }

    private function calculateSupertrendForInterval(FuturesMonitored $future, string $interval)
    {
        try {
            $records = FuturesData::where('trading_symbol', $future->trading_symbol)
                ->where('exchange', $future->exchange)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($records->isEmpty() || $records->count() < $this->atrPeriod + 2) {
                $this->warn("    Insufficient data for Supertrend calculation");
                return;
            }

            $ohlcData = $records->map(function ($item) {
                return [
                    'date' => $item->timestamp,
                    'open' => (float)$item->open,
                    'high' => (float)$item->high,
                    'low' => (float)$item->low,
                    'close' => (float)$item->close,
                    'volume' => (int)$item->volume,
                    'id' => $item->id
                ];
            })->toArray();

            $supertrendCalculator = new SupertrendCalculator($ohlcData, $this->atrPeriod, $this->multiplier);
            $results = $supertrendCalculator->calculateSupertrend();

            // Use raw SQL to avoid any constraint issues
            DB::beginTransaction();
            try {
                $updateCount = 0;
                foreach ($results as $result) {
                    DB::statement("
                        UPDATE futures_data 
                        SET 
                            atr = ?,
                            supertrend = ?,
                            supertrend_direction = ?,
                            supertrend_signal = ?,
                            upper_band = ?,
                            lower_band = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ", [
                        $result['atr'],
                        $result['supertrend'],
                        $result['direction'],
                        $result['signal'],
                        $result['basicUpperBand'],
                        $result['basicLowerBand'],
                        $result['id']
                    ]);
                    $updateCount++;
                }
                DB::commit();
                
                $this->info("    ✓ Updated {$updateCount} records with Supertrend data");

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Error calculating Supertrend: " . $e->getMessage());
            $this->error("    ✗ Supertrend calculation failed: " . $e->getMessage());
        }
    }

    private function isMarketHoliday($date)
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}