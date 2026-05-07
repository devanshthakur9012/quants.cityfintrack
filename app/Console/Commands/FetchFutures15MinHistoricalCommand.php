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

class FetchFutures15MinHistoricalCommand extends Command
{
    protected $signature = 'futures:fetch-15min-historical 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--symbol= : Specific symbol to fetch}';

    protected $description = 'Fetch historical 15-minute futures data with all indicators (Supertrend, Donchian, RSI, MACD, VWAP)';

    private $zerodha;

    public function handle()
    {
        try {
            $this->zerodha = new ZerodhaHelper();

            $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');

            $this->info("📊 Fetching Historical 15-Minute Futures Data");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            $this->info("   Calculating: Supertrend, Donchian, RSI, MACD, VWAP\n");

            // Get symbols with 15-minute interval
            if ($this->option('symbol')) {
                $futures = FuturesMonitored::where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%')
                    ->where('is_active', true)
                    ->where('intervals', 'LIKE', '%15minute%')
                    ->get();
            } else {
                $futures = FuturesMonitored::where('is_active', true)
                    ->where('intervals', 'LIKE', '%15minute%')
                    ->get();
            }

            if ($futures->isEmpty()) {
                $this->warn('No 15-minute futures found!');
                $this->info('Make sure futures_monitored table has intervals containing "15minute"');
                return 0;
            }

            $this->info("Processing " . $futures->count() . " futures contract(s)...\n");

            foreach ($futures as $future) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("Processing: {$future->trading_symbol}");
                $this->fetchHistoricalData($future, $fromDate, $toDate);
            }

            $this->info("\n✅ Historical 15-minute data fetch completed successfully!");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('15-Min Historical Data Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function fetchHistoricalData(FuturesMonitored $future, $fromDate, $toDate)
    {
        try {
            // Get configuration for this symbol
            $config = IndicatorConfig::getForSymbol($future->trading_symbol);
            
            $this->info("  Configuration:");
            $this->info("    ┌─ Supertrend: ATR Period={$config->supertrend_atr_period}, Multiplier={$config->supertrend_multiplier}");
            $this->info("    ├─ Donchian: High Period={$config->donchian_high_period}, Low Period={$config->donchian_low_period}, Risk:Reward={$config->donchian_risk_reward}");
            $this->info("    ├─ RSI: Period={$config->rsi_period}, Overbought={$config->rsi_overbought}, Oversold={$config->rsi_oversold}");
            $this->info("    ├─ MACD: Fast={$config->macd_fast_period}, Slow={$config->macd_slow_period}, Signal={$config->macd_signal_period}");
            $this->info("    └─ VWAP: Reset Daily=" . ($config->vwap_reset_daily ? 'Yes' : 'No') . ", Band Multiplier={$config->vwap_band_multiplier}, Band Period={$config->vwap_band_period}");
            
            $this->info("\n  📥 Fetching 15-minute data from Zerodha...");

            $data = $this->zerodha->getHistoricalDataByToken(
                $future->instrument_token,
                '15minute',
                $fromDate . ' 09:15:00',
                $toDate . ' 15:30:00'
            );

            if (empty($data)) {
                $this->warn("    ⚠️  No data received from Zerodha");
                return;
            }

            $this->info("    ✓ Received " . count($data) . " candles");

            $this->info("  💾 Storing OHLCV data...");
            $insertedCount = $this->storeHistoricalData($future, $data, '15minute');
            $this->info("    ✓ Inserted/Updated {$insertedCount} records");

            if ($insertedCount > 0) {
                $this->info("  🔢 Calculating technical indicators (including VWAP)...");
                $this->calculateIndicatorsForInterval($future, '15minute', $config);
                $this->info("    ✓ All indicators calculated successfully");
            }

            $future->update(['last_fetched_at' => now()]);
            $this->info("  ✅ Completed: {$future->trading_symbol}\n");

        } catch (Exception $e) {
            $this->error("  ✗ Error fetching {$future->trading_symbol}: " . $e->getMessage());
            Log::error("Error fetching {$future->trading_symbol}: " . $e->getMessage());
        }
    }

    private function storeHistoricalData(FuturesMonitored $future, array $data, string $interval)
    {
        $insertedCount = 0;
        $skippedCount = 0;
        $reasons = [
            'weekend' => 0,
            'holiday' => 0,
            'outside_hours' => 0
        ];

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
                    $reasons['weekend']++;
                    continue;
                }

                // Skip market holidays
                if ($this->isMarketHoliday($timestamp->format('Y-m-d'))) {
                    $skippedCount++;
                    $reasons['holiday']++;
                    continue;
                }

                // Check market hours (9:15 AM to 3:30 PM)
                $time = $timestamp->format('H:i:s');
                if ($time < '09:15:00' || $time > '15:30:00') {
                    $skippedCount++;
                    $reasons['outside_hours']++;
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
            $this->info("    ⏭️  Skipped {$skippedCount} candles:");
            if ($reasons['weekend'] > 0) $this->info("       - Weekend: {$reasons['weekend']}");
            if ($reasons['holiday'] > 0) $this->info("       - Holidays: {$reasons['holiday']}");
            if ($reasons['outside_hours'] > 0) $this->info("       - Outside market hours: {$reasons['outside_hours']}");
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

            // Determine minimum required candles for all indicators including VWAP
            $minRequired = max(
                $config->supertrend_atr_period + 2,  // Supertrend needs ATR period + 2
                max($config->donchian_high_period, $config->donchian_low_period) + 2,  // Donchian needs max period + 2
                $config->rsi_period + 1,  // RSI needs period + 1
                $config->macd_slow_period + $config->macd_signal_period,  // MACD needs slow + signal periods
                $config->vwap_band_period  // VWAP bands need period
            );

            if ($records->isEmpty() || $records->count() < $minRequired) {
                $this->warn("    ⚠️  Insufficient data for indicators");
                $this->warn("       Required: {$minRequired} candles, Available: {$records->count()}");
                return;
            }

            $this->info("    ✓ Data sufficient: {$records->count()} candles (minimum: {$minRequired})");

            // Prepare OHLC data array
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

            // Calculate Supertrend
            $this->info("    🔄 Calculating Supertrend...");
            $supertrendCalculator = new SupertrendCalculator(
                $ohlcData, 
                $config->supertrend_atr_period, 
                $config->supertrend_multiplier
            );
            $supertrendResults = $supertrendCalculator->calculateSupertrend();
            $this->info("       ✓ Supertrend calculated");

            // Calculate Donchian
            $this->info("    🔄 Calculating Donchian Channel...");
            $donchianSignals = DonchianCalculator::calculateSignalsForDataset(
                $ohlcData,
                $config->donchian_high_period,
                $config->donchian_low_period,
                $config->donchian_risk_reward
            );
            $this->info("       ✓ Donchian calculated");

            // Calculate RSI
            $this->info("    🔄 Calculating RSI...");
            $rsiResults = RSICalculator::calculateWithSignals(
                $ohlcData,
                $config->rsi_period,
                $config->rsi_overbought,
                $config->rsi_oversold
            );
            $this->info("       ✓ RSI calculated");

            // Calculate MACD
            $this->info("    🔄 Calculating MACD...");
            $macdResults = MACDCalculator::calculateWithSignals(
                $ohlcData,
                $config->macd_fast_period,
                $config->macd_slow_period,
                $config->macd_signal_period
            );
            $this->info("       ✓ MACD calculated");

            // Calculate VWAP with bands
            $this->info("    🔄 Calculating VWAP...");
            $vwapResults = VWAPCalculator::calculateWithBands(
                $ohlcData,
                $config->vwap_band_multiplier,
                $config->vwap_band_period,
                $config->vwap_reset_daily,
                $config->vwap_distance_percent
            );
            $this->info("       ✓ VWAP calculated");

            // Update database with all indicators
            $this->info("    💾 Updating database with indicator values...");
            DB::beginTransaction();
            try {
                $updateCount = 0;
                $progressBar = $this->output->createProgressBar(count($supertrendResults));
                $progressBar->setFormat('       [%bar%] %percent:3s%% (%current%/%max%)');
                $progressBar->start();
                
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
                        // Supertrend
                        $result['atr'],
                        $result['supertrend'],
                        $result['direction'],
                        $result['signal'],
                        $result['basicUpperBand'],
                        $result['basicLowerBand'],
                        // Donchian
                        $donchianData['signal'] ?? 'NO_TRADE',
                        $donchianData['upper'] ?? null,
                        $donchianData['lower'] ?? null,
                        $donchianData['middle'] ?? null,
                        $donchianData['entry'] ?? null,
                        $donchianData['sl'] ?? null,
                        $donchianData['target'] ?? null,
                        // RSI
                        $rsiData['rsi'] ?? null,
                        $rsiData['signal'] ?? 'NEUTRAL',
                        // MACD
                        $macdData['macd_line'] ?? null,
                        $macdData['signal_line'] ?? null,
                        $macdData['histogram'] ?? null,
                        $macdData['signal'] ?? 'HOLD',
                        // VWAP
                        $vwapData['vwap'] ?? null,
                        $vwapData['signal'] ?? 'HOLD',
                        $vwapData['upper_band'] ?? null,
                        $vwapData['lower_band'] ?? null,
                        // ID
                        $result['id']
                    ]);
                    
                    if ($updated > 0) {
                        $updateCount++;
                    }
                    
                    $progressBar->advance();
                }
                
                $progressBar->finish();
                $this->newLine();
                
                DB::commit();
                
                $this->info("       ✓ Updated {$updateCount} records with all indicators (including VWAP)");

            } catch (Exception $e) {
                DB::rollBack();
                $this->error("       ✗ Database update failed");
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