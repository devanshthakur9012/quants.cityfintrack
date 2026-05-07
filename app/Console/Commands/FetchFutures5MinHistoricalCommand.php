<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaHelper;
use App\Helpers\SupertrendCalculator;
use App\Helpers\DonchianCalculator;
use App\Helpers\RSICalculator;
use App\Helpers\MACDCalculator;
use App\Helpers\VWAPCalculator;
use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use App\Models\IndicatorConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchFutures5MinHistoricalCommand extends Command
{
    protected $signature = 'futures:fetch-5min-historical 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--symbol= : Specific symbol to fetch}';

    protected $description = 'Fetch historical 5-minute futures data with all indicators (Supertrend, Donchian, RSI, MACD, VWAP)';

    private $zerodha;

    public function handle()
    {
        try {
            $this->zerodha = new ZerodhaHelper();

            $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');

            $this->info("📊 Fetching Historical 5-Minute Futures Data");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            $this->info("   Using configuration-based indicators\n");

            // Get symbols with 5-minute interval
            if ($this->option('symbol')) {
                $futures = FuturesMonitored::where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%')
                    ->where('is_active', true)
                    ->where('intervals','5minute')
                    ->get();
            } else {
                $futures = FuturesMonitored::where('is_active', true)
                    ->where('intervals','5minute')
                    ->get();
            }

            if ($futures->isEmpty()) {
                $this->warn('No 5-minute futures found!');
                return 0;
            }

            $this->info("Processing " . $futures->count() . " futures contract(s)...\n");

            foreach ($futures as $future) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("Processing: {$future->trading_symbol}");
                $this->fetchHistoricalData($future, $fromDate, $toDate);
            }

            $this->info("\n✅ Historical 5-minute data fetch completed successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('5-Min Historical Data Fetch Error: ' . $e->getMessage());
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
            $this->info("    - DC High Period: {$config->donchian_high_period}");
            $this->info("    - DC Low Period: {$config->donchian_low_period}");
            $this->info("    - DC Risk:Reward: {$config->donchian_risk_reward}");
            $this->info("    - RSI Period: {$config->rsi_period}");
            $this->info("    - MACD Fast/Slow/Signal: {$config->macd_fast_period}/{$config->macd_slow_period}/{$config->macd_signal_period}");
            $this->info("    - VWAP Multiplier: {$config->vwap_band_multiplier}");

            $this->info("  - Fetching 5minute data...");

            $data = $this->zerodha->getHistoricalDataByToken(
                $future->instrument_token,
                '5minute',
                $fromDate . ' 09:15:00',
                $toDate . ' 15:30:00'
            );

            if (empty($data)) {
                $this->warn("    No data received");
                return;
            }

            $this->info("    Received " . count($data) . " candles");

            $insertedCount = $this->storeHistoricalData($future, $data, '5minute');
            $this->info("    ✓ Inserted/Updated {$insertedCount} records");

            if ($insertedCount > 0) {
                $this->calculateIndicators($future, '5minute', $config);
                $this->info("    ✓ All indicators calculated (including VWAP)");
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

    private function calculateIndicators(FuturesMonitored $future, string $interval, IndicatorConfig $config)
    {
        try {
            $records = FuturesData::where('trading_symbol', $future->trading_symbol)
                ->where('exchange', $future->exchange)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC')
                ->get();

            $minRequired = max(
                $config->supertrend_atr_period + 2,
                max($config->donchian_high_period, $config->donchian_low_period) + 2,
                $config->rsi_period + 1,
                $config->macd_slow_period + $config->macd_signal_period,
                $config->vwap_band_period
            );

            if ($records->isEmpty() || $records->count() < $minRequired) {
                $this->warn("    Insufficient data for indicators (need {$minRequired}, got {$records->count()})");
                return;
            }

            $ohlcData = $records->map(function ($item) {
                return [
                    'id' => $item->id,
                    'date' => $item->timestamp,
                    'open' => (float)$item->open,
                    'high' => (float)$item->high,
                    'low' => (float)$item->low,
                    'close' => (float)$item->close,
                    'volume' => (int)$item->volume,
                ];
            })->toArray();

            // Calculate all indicators
            $supertrendCalculator = new SupertrendCalculator(
                $ohlcData,
                $config->supertrend_atr_period,
                $config->supertrend_multiplier
            );
            $supertrendResults = $supertrendCalculator->calculateSupertrend();

            $donchianSignals = DonchianCalculator::calculateSignalsForDataset(
                $ohlcData,
                $config->donchian_high_period,
                $config->donchian_low_period,
                $config->donchian_risk_reward
            );

            $rsiResults = RSICalculator::calculateWithSignals(
                $ohlcData,
                $config->rsi_period,
                $config->rsi_overbought,
                $config->rsi_oversold
            );

            $macdResults = MACDCalculator::calculateWithSignals(
                $ohlcData,
                $config->macd_fast_period,
                $config->macd_slow_period,
                $config->macd_signal_period
            );

            // Calculate VWAP with bands
            $vwapResults = VWAPCalculator::calculateWithBands(
                $ohlcData,
                $config->vwap_band_multiplier,
                $config->vwap_band_period,
                $config->vwap_reset_daily,
                $config->vwap_distance_percent
            );

            // Update database with all indicators
            DB::beginTransaction();
            try {
                $updateCount = 0;
                
                foreach ($supertrendResults as $index => $result) {
                    $donchianData = $donchianSignals[$index] ?? null;
                    $rsiData = $rsiResults[$index] ?? null;
                    $macdData = $macdResults[$index] ?? null;
                    $vwapData = $vwapResults[$index] ?? null;

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
                            rsi = ?,
                            rsi_signal = ?,
                            macd_line = ?,
                            macd_signal_line = ?,
                            macd_histogram = ?,
                            macd_signal = ?,
                            vwap = ?,
                            vwap_signal = ?,
                            vwap_upper_band = ?,
                            vwap_lower_band = ?,
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
                        $donchianData['middle'] ?? null,
                        $donchianData['entry'] ?? null,
                        $donchianData['sl'] ?? null,
                        $donchianData['target'] ?? null,
                        $rsiData['rsi'] ?? null,
                        $rsiData['signal'] ?? 'NEUTRAL',
                        $macdData['macd_line'] ?? null,
                        $macdData['signal_line'] ?? null,
                        $macdData['histogram'] ?? null,
                        $macdData['signal'] ?? 'HOLD',
                        $vwapData['vwap'] ?? null,
                        $vwapData['signal'] ?? 'HOLD',
                        $vwapData['upper_band'] ?? null,
                        $vwapData['lower_band'] ?? null,
                        $result['id']
                    ]);
                    
                    if ($updated > 0) {
                        $updateCount++;
                    }
                }
                
                DB::commit();
                
                $this->info("    ✓ Updated {$updateCount} records with all indicators");

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Indicator calculation error: " . $e->getMessage());
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