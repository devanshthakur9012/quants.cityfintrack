<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Helpers\SupertrendWithMA;
use App\Helpers\DonchianCalculator;
use App\Helpers\RSICalculator;
use App\Helpers\MACDCalculator;
use App\Helpers\VWAPCalculator;
use App\Helpers\OIAnalyzerSuper;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\BrokerApi;
use App\Models\IndicatorConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchSymbolsHistoricalCommandBackup extends Command
{
    protected $signature = 'symbols:fetch-historical-backup 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific symbol}
                            {--interval=15minute : Interval (5minute, 15minute)}';

    protected $description = 'Fetch historical data with Supertrend+50MA and persistent signals';

    public function handle()
    {
        try {
            $fromDate = $this->option('from') ?: Carbon::now()->subDays(30)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');
            $interval = $this->option('interval');

            $this->info("📊 Fetching Historical Symbol Data");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            $this->info("   Interval: {$interval}");
            $this->info("   Strategy: Supertrend + 50 MA\n");

            // Get brokers
            $brokersQuery = BrokerApi::zerodha()->validToken();

            if ($this->option('broker')) {
                $brokersQuery->where('id', $this->option('broker'));
            }

            $brokers = $brokersQuery->get();

            if ($brokers->isEmpty()) {
                $this->error('❌ No active brokers found!');
                return 1;
            }

            $this->info("📋 Found " . $brokers->count() . " broker(s)\n");

            $totalProcessed = 0;
            $totalFailed = 0;

            foreach ($brokers as $broker) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

                $result = $this->processBrokerHistorical($broker, $fromDate, $toDate, $interval);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Historical Fetch Completed!");
            $this->info("   Processed: {$totalProcessed} | Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Historical Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBrokerHistorical(BrokerApi $broker, $fromDate, $toDate, $interval)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);

            $symbolsQuery = SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('is_active', true)
                ->where('interval', $interval);

            if ($this->option('symbol')) {
                $symbolsQuery->where('symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
            }

            $symbols = $symbolsQuery->get();

            if ($symbols->isEmpty()) {
                $this->warn("   ⚠️  No symbols found\n");
                return ['success' => 0, 'failed' => 0];
            }

            $this->info("   📊 Processing " . $symbols->count() . " symbol(s)");

            $progressBar = $this->output->createProgressBar($symbols->count());
            $progressBar->start();

            foreach ($symbols as $symbol) {
                try {
                    $this->fetchHistoricalData($broker, $symbol, $zerodhaHelper, $fromDate, $toDate, $interval);
                    $success++;
                } catch (Exception $e) {
                    $failed++;
                    Log::error("Historical fetch failed: {$symbol->trading_symbol}", [
                        'error' => $e->getMessage()
                    ]);
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);
            $this->info("   Summary: ✓ {$success} | ✗ {$failed}\n");

        } catch (Exception $e) {
            $this->error("   Broker error: " . $e->getMessage() . "\n");
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function fetchHistoricalData(BrokerApi $broker, SymbolMonitored $symbol, BrokerZerodhaHelper $zerodhaHelper, $fromDate, $toDate, $interval)
    {
        $data = $zerodhaHelper->getHistoricalDataByToken(
            $symbol->instrument_token,
            $interval,
            $fromDate . ' 09:15:00',
            $toDate . ' 15:30:00'
        );

        if (empty($data)) {
            return;
        }

        $insertedCount = $this->storeHistoricalData($broker->id, $symbol, $data, $interval);

        if ($insertedCount > 0) {
            $config = IndicatorConfig::getForSymbol($symbol->trading_symbol);
            $this->calculateIndicators($broker->id, $symbol, $interval, $config);
            
            // Calculate persistent signals for historical data
            $this->calculatePersistentSignals($broker->id, $symbol->symbol, $interval, $fromDate, $toDate);
        }

        $symbol->update(['last_synced_at' => now()]);
    }

    private function storeHistoricalData($brokerId, SymbolMonitored $symbol, array $data, string $interval)
    {
        $insertedCount = 0;
        $specialTradingDays = ['2026-02-01'];

        foreach ($data as $candle) {
            try {
                $candleDate = $candle->date;

                if ($candleDate instanceof \DateTime) {
                    $timestamp = Carbon::instance($candleDate);
                } else {
                    $timestamp = Carbon::parse($candleDate);
                }

                $isSpecialDay = in_array($timestamp->format('Y-m-d'), $specialTradingDays);

                // Skip weekends (unless special)
                if (!$isSpecialDay && $timestamp->isWeekend()) {
                    continue;
                }

                // Skip holidays (unless special)
                if (!$isSpecialDay && $this->isMarketHoliday($timestamp->format('Y-m-d'))) {
                    continue;
                }

                $time = $timestamp->format('H:i:s');
                if ($time < '09:15:00' || $time > '15:30:00') {
                    continue;
                }

                // Get previous OI
                $previousOI = OIAnalyzerSuper::getPreviousOI(
                    $brokerId,
                    $symbol->symbol,
                    $interval,
                    $timestamp->format('Y-m-d H:i:s')
                );

                $currentOI = (int)($candle->oi ?? 0);
                
                $oiData = [
                    'previous_oi' => $previousOI,
                    'oi_change' => 0,
                    'oi_change_percent' => 0,
                    'oi_signal' => 'NEUTRAL'
                ];

                if ($previousOI !== null && $currentOI > 0) {
                    $oiAnalysis = OIAnalyzerSuper::analyzeFuturesOI(
                        $currentOI,
                        $previousOI,
                        $symbol->symbol
                    );
                    $oiData = $oiAnalysis;
                }

                SymbolData::updateOrCreate(
                    [
                        'broker_api_id' => $brokerId,
                        'trading_symbol' => $symbol->trading_symbol,
                        'interval' => $interval,
                        'timestamp' => $timestamp->format('Y-m-d H:i:s')
                    ],
                    [
                        'symbol' => $symbol->symbol,
                        'exchange' => $symbol->exchange,
                        'instrument_token' => $symbol->instrument_token,
                        'open' => $candle->open,
                        'high' => $candle->high,
                        'low' => $candle->low,
                        'close' => $candle->close,
                        'volume' => $candle->volume,
                        'oi' => $currentOI,
                        'previous_oi' => $oiData['previous_oi'],
                        'oi_change' => $oiData['oi_change'],
                        'oi_change_percent' => $oiData['oi_change_percent'],
                        'oi_signal' => $oiData['oi_signal']
                    ]
                );

                $insertedCount++;

            } catch (Exception $e) {
                Log::error("Error storing candle: " . $e->getMessage());
            }
        }

        return $insertedCount;
    }

    private function calculateIndicators($brokerId, SymbolMonitored $symbol, string $interval, IndicatorConfig $config)
    {
        try {
            $records = SymbolData::where('broker_api_id', $brokerId)
                ->where('symbol', $symbol->symbol)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC')
                ->get();

            // Need minimum data for 50 MA
            $minRequired = max(50, $config->supertrend_atr_period + 2);

            if ($records->count() < $minRequired) return;

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

            // Calculate Supertrend+MA
            $supertrendCalculator = new SupertrendWithMA(
                $ohlcData,
                $config->supertrend_atr_period,
                $config->supertrend_multiplier,
                50
            );
            $supertrendResults = $supertrendCalculator->calculate();

            // Calculate other indicators
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

            $vwapResults = VWAPCalculator::calculateWithBands(
                $ohlcData,
                $config->vwap_band_multiplier,
                $config->vwap_band_period,
                $config->vwap_reset_daily,
                $config->vwap_distance_percent
            );

            // Update database
            DB::beginTransaction();
            try {
                foreach ($supertrendResults as $index => $result) {
                    $donchianData = $donchianSignals[$index] ?? null;
                    $rsiData = $rsiResults[$index] ?? null;
                    $macdData = $macdResults[$index] ?? null;
                    $vwapData = $vwapResults[$index] ?? null;

                    DB::update("
                        UPDATE symbol_data 
                        SET 
                            atr = ?,
                            supertrend = ?,
                            supertrend_direction = ?,
                            supertrend_signal = ?,
                            ma50 = ?,
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
                        $result['ma50'],
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
                }
                DB::commit();

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Indicator calculation error: " . $e->getMessage());
        }
    }

    /**
     * Calculate persistent signals for historical date range
     * This ensures all dates are properly synced
     */
    private function calculatePersistentSignals($brokerId, $symbol, $interval, $fromDate, $toDate)
    {
        try {
            // Get ALL records in the date range
            $records = SymbolData::where('broker_api_id', $brokerId)
                ->where('symbol', $symbol)
                ->where('interval', $interval)
                ->whereDate('timestamp', '>=', $fromDate)
                ->whereDate('timestamp', '<=', $toDate)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($records->isEmpty()) {
                return;
            }

            // Get signal from day BEFORE the historical range
            $firstTimestamp = $records->first()->timestamp;
            $previousDaySignal = $this->getPreviousTradingDaySignal(
                $brokerId,
                $symbol,
                $interval,
                $firstTimestamp
            );

            $currentPersistentSignal = $previousDaySignal;
            $updateCount = 0;

            foreach ($records as $record) {
                $originalSignal = $record->supertrend_signal;
                $direction = $record->supertrend_direction;

                // Apply persistent logic
                if (in_array($originalSignal, ['BUY', 'SELL'])) {
                    $currentPersistentSignal = $originalSignal;
                } elseif ($originalSignal === 'HOLD' && $direction === 'UP' && $currentPersistentSignal === 'BUY') {
                    $currentPersistentSignal = 'BUY';
                } elseif ($originalSignal === 'HOLD' && $direction === 'DOWN' && $currentPersistentSignal === 'SELL') {
                    $currentPersistentSignal = 'SELL';
                } elseif ($currentPersistentSignal === null) {
                    $currentPersistentSignal = $originalSignal ?: 'HOLD';
                }

                $record->update(['supertrend_signal' => $currentPersistentSignal]);
                $updateCount++;
            }

            Log::info("✅ Historical persistent signals: {$updateCount} records updated for {$symbol}");

        } catch (Exception $e) {
            Log::error("Error calculating historical persistent signals: " . $e->getMessage());
        }
    }

    private function getPreviousTradingDaySignal($brokerId, $symbol, $interval, $currentTimestamp)
    {
        $currentDate = Carbon::parse($currentTimestamp)->startOfDay();
        $specialTradingDays = ['2026-02-01'];
        
        for ($i = 1; $i <= 10; $i++) {
            $checkDate = $currentDate->copy()->subDays($i);
            $checkDateStr = $checkDate->format('Y-m-d');
            
            $isSpecialDay = in_array($checkDateStr, $specialTradingDays);
            
            if (!$isSpecialDay && $checkDate->isWeekend()) {
                continue;
            }
            
            if (!$isSpecialDay && $this->isMarketHoliday($checkDateStr)) {
                continue;
            }
            
            $lastRecord = SymbolData::where('broker_api_id', $brokerId)
                ->where('symbol', $symbol)
                ->where('interval', $interval)
                ->whereDate('timestamp', $checkDateStr)
                ->orderBy('timestamp', 'DESC')
                ->first();
            
            if ($lastRecord) {
                Log::info("✅ Historical: Found previous day {$checkDateStr}");
                return $lastRecord->supertrend_signal;
            }
        }
        
        return null;
    }

    private function isMarketHoliday($date)
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}