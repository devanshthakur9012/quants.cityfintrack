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
use App\Models\IndicatorConfig;

class FetchFutures1MinCommand extends Command
{
    protected $signature = 'futures:fetch-1min 
                            {--symbol= : Specific symbol to fetch} 
                            {--force : Force fetch even on holidays}
                            {--atr-period=10 : ATR period for Supertrend}
                            {--multiplier=3 : Multiplier for Supertrend}
                            {--backfill : Fetch full day data from 9:15 AM}';

    protected $description = 'Fetch 1-minute futures data with Supertrend calculation';

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
                Log::info("1-min futures fetch skipped: Weekend");
                return 0;
            }

            // Skip if market holiday
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("1-min futures fetch skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->zerodha = new ZerodhaHelper();
            $this->atrPeriod = $this->option('atr-period');
            $this->multiplier = $this->option('multiplier');

            // Fetch only symbols with 'minute' interval
            if ($this->option('symbol')) {
                $futures = FuturesMonitored::where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%')
                    ->where('is_active', true)
                    ->where(function($query) {
                        $query->where('intervals', 'minute');
                    })
                    ->get();
            } else {
                $futures = FuturesMonitored::where('is_active', true)
                    ->where(function($query) {
                        $query->where('intervals', 'minute');
                    })
                    ->get();
            }

            if ($futures->isEmpty()) {
                $this->info('No 1-min futures to fetch');
                return 0;
            }

            $this->info("📊 Fetching 1-min data for " . $futures->count() . " contract(s)...\n");

            foreach ($futures as $future) {
                $this->info("Processing: {$future->trading_symbol}");
                $this->fetchFuturesData($future, 'minute');
            }

            $this->info("\n✅ 1-minute futures fetch completed!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('1-Min Futures Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function fetchFuturesData(FuturesMonitored $future, string $interval)
    {
        try {
            $fromDate = $this->getFromDate($future, $interval);
            $toDate = date('Y-m-d H:i:s');

            $this->info("  From: {$fromDate} To: {$toDate}");

            $data = $this->zerodha->getHistoricalDataByToken(
                $future->instrument_token,
                $interval,
                $fromDate,
                $toDate
            );

            if (empty($data)) {
                $this->warn("  No data received");
                return;
            }

            $this->info("  Received " . count($data) . " candles");

            $config = IndicatorConfig::getForSymbol($future->trading_symbol);

            $insertedCount = $this->storeHistoricalData($future, $data, $interval);
            $this->info("  ✓ Inserted/Updated {$insertedCount} records");

            if ($insertedCount > 0) {
                // $this->calculateSupertrendForInterval($future, $interval);
                $this->calculateSupertrendForInterval($future, $interval, $config);
                $this->info("  ✓ Supertrend calculated");
            }

            $future->update(['last_fetched_at' => now()]);

        } catch (Exception $e) {
            $this->error("  ✗ Error: " . $e->getMessage());
            Log::error("Error fetching {$future->trading_symbol}: " . $e->getMessage());
        }
    }

    private function getFromDate(FuturesMonitored $future, $interval)
    {
        $marketOpenToday = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        $now = Carbon::now('Asia/Kolkata');

        // If --backfill option is set, always fetch from market open
        if ($this->option('backfill')) {
            $this->info("  Backfill mode: From market open");
            return $marketOpenToday->format('Y-m-d H:i:s');
        }

        // Check if we have data for today
        $firstRecordToday = FuturesData::where('trading_symbol', $future->trading_symbol)
            ->where('interval', $interval)
            ->whereDate('timestamp', $now->toDateString())
            ->orderBy('timestamp', 'asc')
            ->first();

        // If no data for today, fetch from market open
        if (!$firstRecordToday) {
            $this->info("  No data today, fetching from market open");
            return $marketOpenToday->format('Y-m-d H:i:s');
        }

        // If first record is NOT from 9:15, fetch from 9:15
        if ($firstRecordToday->timestamp->format('H:i') !== '09:15') {
            $this->info("  Missing early data, fetching from market open");
            return $marketOpenToday->format('Y-m-d H:i:s');
        }

        // Otherwise, fetch from last record (incremental update)
        $lastRecord = FuturesData::where('trading_symbol', $future->trading_symbol)
            ->where('interval', $interval)
            ->orderBy('timestamp', 'desc')
            ->first();

        if ($lastRecord) {
            $this->info("  Incremental update from last record");
            return $lastRecord->timestamp->format('Y-m-d H:i:s');
        }

        return $marketOpenToday->format('Y-m-d H:i:s');
    }

    private function storeHistoricalData(FuturesMonitored $future, array $data, string $interval)
    {
        $insertedCount = 0;
        $skippedCount = 0;

        foreach ($data as $candle) {
            try {
                $candleDate = $candle->date;
                
                if ($candleDate instanceof \DateTime) {
                    $timestamp = Carbon::instance($candleDate);
                } else {
                    $timestamp = Carbon::parse($candleDate);
                }
                
                // Skip weekends
                if ($timestamp->isWeekend()) {
                    $skippedCount++;
                    continue;
                }

                // Skip market holidays
                if ($this->isMarketHoliday($timestamp->format('Y-m-d'))) {
                    $skippedCount++;
                    continue;
                }

                // Check market hours (9:15 AM to 3:30 PM)
                $time = $timestamp->format('H:i:s');
                if ($time < '09:15:00' || $time > '15:30:00') {
                    $skippedCount++;
                    continue;
                }

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
                Log::error("Error storing candle: " . $e->getMessage());
            }
        }

        if ($skippedCount > 0) {
            $this->info("  Skipped {$skippedCount} candles");
        }

        return $insertedCount;
    }

    private function calculateSupertrendForInterval(
        FuturesMonitored $future, 
        string $interval,
        IndicatorConfig $config
    ) {
        try {
            $records = FuturesData::where('trading_symbol', $future->trading_symbol)
                ->where('exchange', $future->exchange)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($records->isEmpty() || $records->count() < $this->atrPeriod + 2) {
                $this->warn("  Insufficient data for Supertrend");
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

            // $supertrendCalculator = new SupertrendCalculator($ohlcData, $this->atrPeriod, $this->multiplier);
            // $results = $supertrendCalculator->calculateSupertrend();

            $supertrendCalculator = new SupertrendCalculator(
                $ohlcData, 
                $config->supertrend_atr_period,  // Use config
                $config->supertrend_multiplier    // Use config
            );
            $results = $supertrendCalculator->calculateSupertrend();

            $donchianSignals = \App\Helpers\DonchianCalculator::calculateSignalsForDataset(
                $ohlcData,
                $config->donchian_period,          // Use config
                $config->donchian_risk_reward      // Use config
            );

            DB::beginTransaction();
            // try {
            //     $updateCount = 0;
            //     foreach ($results as $result) {
            //         $updated = DB::update("
            //             UPDATE futures_data 
            //             SET 
            //                 atr = ?,
            //                 supertrend = ?,
            //                 supertrend_direction = ?,
            //                 supertrend_signal = ?,
            //                 upper_band = ?,
            //                 lower_band = ?,
            //                 updated_at = NOW()
            //             WHERE id = ?
            //         ", [
            //             $result['atr'],
            //             $result['supertrend'],
            //             $result['direction'],
            //             $result['signal'],
            //             $result['basicUpperBand'],
            //             $result['basicLowerBand'],
            //             $result['id']
            //         ]);
                    
            //         if ($updated > 0) {
            //             $updateCount++;
            //         }
            //     }
            //     DB::commit();

            // } catch (Exception $e) {
            //     DB::rollBack();
            //     throw $e;
            // }

            try {
                $updateCount = 0;
                foreach ($results as $index => $result) {
                    $donchianData = $donchianSignals[$index] ?? null;
                    
                    $updated = DB::update("
                        UPDATE futures_data 
                        SET 
                            atr = ?,
                            supertrend = ?,
                            supertrend_direction = ?,
                            supertrend_signal = ?,
                            upper_band = ?,
                            lower_band = ?,
                            donchian_signal = ?,
                            donchian_upper = ?,
                            donchian_lower = ?,
                            donchian_middle = ?,
                            donchian_entry = ?,
                            donchian_sl = ?,
                            donchian_target = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ", [
                        $result['atr'],
                        $result['supertrend'],
                        $result['direction'],
                        $result['signal'],
                        $result['basicUpperBand'],
                        $result['basicLowerBand'],
                        $donchianData['signal'] ?? 'NO_TRADE',
                        $donchianData['upper'] ?? null,
                        $donchianData['lower'] ?? null,
                        ($donchianData['upper'] && $donchianData['lower']) 
                            ? ($donchianData['upper'] + $donchianData['lower']) / 2 
                            : null,
                        $donchianData['entry'] ?? null,
                        $donchianData['sl'] ?? null,
                        $donchianData['target'] ?? null,
                        $result['id']
                    ]);
                    
                    if ($updated > 0) {
                        $updateCount++;
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Supertrend calculation error: " . $e->getMessage());
            $this->error("  ✗ Supertrend failed");
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