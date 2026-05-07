<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaHelper;
use App\Helpers\SupertrendCalculator;
use App\Helpers\DonchianCalculator;
use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use App\Models\IndicatorConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchFutures1MinHistoricalCommand extends Command
{
    protected $signature = 'futures:fetch-1min-historical 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--symbol= : Specific symbol to fetch}';

    protected $description = 'Fetch historical 1-minute futures data with Supertrend and Donchian calculation';

    private $zerodha;

    public function handle()
    {
        try {
            $this->zerodha = new ZerodhaHelper();

            $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');

            $this->info("📊 Fetching Historical 1-Minute Futures Data");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            $this->info("   Using configuration-based indicators\n");

            // Get symbols with 1-minute interval
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
                $this->warn('No 1-minute futures found!');
                return 0;
            }

            $this->info("Processing " . $futures->count() . " futures contract(s)...\n");

            foreach ($futures as $future) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("Processing: {$future->trading_symbol}");
                $this->fetchHistoricalData($future, $fromDate, $toDate);
            }

            $this->info("\n✅ Historical 1-minute data fetch completed successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('1-Min Historical Data Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function fetchHistoricalData(FuturesMonitored $future, $fromDate, $toDate)
    {
        try {
            // Get configuration for this symbol
            $config = IndicatorConfig::getForSymbol($future->trading_symbol);
            
            $this->info("  Using config:");
            $this->info("    - ST ATR Period: {$config->supertrend_atr_period}");
            $this->info("    - ST Multiplier: {$config->supertrend_multiplier}");
            $this->info("    - DC Period: {$config->donchian_period}");
            $this->info("    - DC Risk:Reward: {$config->donchian_risk_reward}");

            $this->info("  - Fetching minute data...");

            $data = $this->zerodha->getHistoricalDataByToken(
                $future->instrument_token,
                'minute',
                $fromDate . ' 09:15:00',
                $toDate . ' 15:30:00'
            );

            if (empty($data)) {
                $this->warn("    No data received");
                return;
            }

            $this->info("    Received " . count($data) . " candles");

            $insertedCount = $this->storeHistoricalData($future, $data, 'minute');
            $this->info("    ✓ Inserted/Updated {$insertedCount} records");

            if ($insertedCount > 0) {
                $this->calculateIndicatorsForInterval($future, 'minute', $config);
                $this->info("    ✓ Indicators calculated");
            }

            $future->update(['last_fetched_at' => now()]);

        } catch (Exception $e) {
            $this->error("  ✗ Error fetching {$future->trading_symbol}: " . $e->getMessage());
            Log::error("Error fetching {$future->trading_symbol}: " . $e->getMessage());
        }
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
            $this->info("    Skipped {$skippedCount} candles (weekends/holidays/outside market hours)");
        }

        return $insertedCount;
    }

    private function calculateIndicatorsForInterval(FuturesMonitored $future, string $interval, IndicatorConfig $config)
    {
        try {
            $records = FuturesData::where('trading_symbol', $future->trading_symbol)
                ->where('exchange', $future->exchange)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($records->isEmpty() || $records->count() < $config->supertrend_atr_period + 2) {
                $this->warn("    Insufficient data for indicator calculation (need " . ($config->supertrend_atr_period + 2) . ", have " . $records->count() . ")");
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

            // Calculate Supertrend with config
            $supertrendCalculator = new SupertrendCalculator(
                $ohlcData, 
                $config->supertrend_atr_period, 
                $config->supertrend_multiplier
            );
            $supertrendResults = $supertrendCalculator->calculateSupertrend();

            // Calculate Donchian with config
            $donchianSignals = DonchianCalculator::calculateSignalsForDataset(
                $ohlcData,
                $config->donchian_period,
                $config->donchian_risk_reward
            );

            // Update database with both indicators
            DB::beginTransaction();
            try {
                $updateCount = 0;
                
                foreach ($supertrendResults as $index => $result) {
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
                
                $this->info("    ✓ Updated {$updateCount} records with indicators");

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Error calculating indicators: " . $e->getMessage());
            $this->error("    ✗ Indicator calculation failed: " . $e->getMessage());
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