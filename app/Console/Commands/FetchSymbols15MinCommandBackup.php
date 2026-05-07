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

class FetchSymbols15MinCommandBackup extends Command
{
    protected $signature = 'symbols:fetch-15min-backup 
                            {--broker= : Specific broker ID to fetch}
                            {--symbol= : Specific symbol to fetch} 
                            {--force : Force fetch even on holidays}
                            {--backfill : Fetch full day data from 9:15 AM}';

    protected $description = 'Fetch 15-minute data with Supertrend+50MA and persistent signals';

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        // Special trading days
        $specialTradingDays = ['2026-02-01'];
        $isSpecialDay = in_array($today, $specialTradingDays);

        if (!$this->option('force') && !$isSpecialDay) {
            if ($dayName == "Saturday" || $dayName == "Sunday") {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("15-min fetch skipped: Weekend");
                return 0;
            }

            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("15-min fetch skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->info("🚀 Starting 15-Min Symbol Data Fetch");
            $this->info("   Time: " . Carbon::now()->format('Y-m-d H:i:s'));
            $this->info("   Strategy: Supertrend + 50 MA");
            if ($isSpecialDay) $this->info("   ⭐ Special Trading Day!");
            $this->newLine();

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

                if (!$broker->hasValidToken()) {
                    $this->warn("   ⚠️ Skipping - Invalid token\n");
                    continue;
                }

                $result = $this->processBroker($broker);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Fetch Completed!");
            $this->info("   Processed: {$totalProcessed} | Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('15-Min Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBroker(BrokerApi $broker)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);

            $symbolsQuery = SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('is_active', true)
                ->where('interval', '15minute');

            if ($this->option('symbol')) {
                $symbolsQuery->where('symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
            }

            $symbols = $symbolsQuery->get();

            if ($symbols->isEmpty()) {
                $this->warn("   ⚠️  No symbols assigned\n");
                return ['success' => 0, 'failed' => 0];
            }

            $this->info("   📊 Processing " . $symbols->count() . " symbol(s)\n");

            foreach ($symbols as $symbol) {
                try {
                    $this->info("   └─ {$symbol->trading_symbol}");
                    $this->fetchSymbolData($broker, $symbol, $zerodhaHelper);
                    $success++;
                    $this->info("      ✓ Done\n");

                } catch (Exception $e) {
                    $failed++;
                    $this->error("      ✗ Failed: " . $e->getMessage() . "\n");
                    Log::error("Symbol fetch failed: {$symbol->trading_symbol}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("   Summary: ✓ {$success} | ✗ {$failed}\n");

        } catch (Exception $e) {
            $this->error("   Broker error: " . $e->getMessage() . "\n");
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function fetchSymbolData(BrokerApi $broker, SymbolMonitored $symbol, BrokerZerodhaHelper $zerodhaHelper)
    {
        $fromDate = $this->getFromDate($broker->id, $symbol);
        $toDate = date('Y-m-d H:i:s');

        $this->info("      From: {$fromDate}");
        $this->info("      To: {$toDate}");

        $data = $zerodhaHelper->getHistoricalDataByToken(
            $symbol->instrument_token,
            '15minute',
            $fromDate,
            $toDate
        );

        if (empty($data)) {
            $this->warn("      ⚠️ No data received");
            return;
        }

        $this->info("      📥 Received " . count($data) . " candles");

        $insertedCount = $this->storeHistoricalData($broker->id, $symbol, $data, '15minute');
        $this->info("      💾 Stored {$insertedCount} records");

        if ($insertedCount > 0) {
            $config = IndicatorConfig::getForSymbol($symbol->trading_symbol);
            $this->calculateIndicators($broker->id, $symbol, '15minute', $config);
            $this->info("      🔢 Indicators calculated");
            
            $this->calculatePersistentSignals($broker->id, $symbol->symbol, '15minute');
            $this->info("      📊 Persistent signals applied");
        }

        $symbol->update(['last_synced_at' => now()]);
    }

    private function getFromDate($brokerId, SymbolMonitored $symbol)
    {
        $marketOpenToday = Carbon::today('Asia/Kolkata')->setTime(9, 15);

        if ($this->option('backfill')) {
            return $marketOpenToday->format('Y-m-d H:i:s');
        }

        $lastRecord = SymbolData::where('broker_api_id', $brokerId)
            ->where('symbol', $symbol->symbol)
            ->where('interval', '15minute')
            ->orderBy('timestamp', 'desc')
            ->first();

        if ($lastRecord) {
            if ($lastRecord->timestamp->isToday()) {
                return $lastRecord->timestamp->format('Y-m-d H:i:s');
            } else {
                return $marketOpenToday->format('Y-m-d H:i:s');
            }
        }

        return date('Y-m-d H:i:s', strtotime('-7 days'));
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

                // Check market hours
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

            if ($records->count() < $minRequired) {
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

            // Calculate Supertrend+MA
            $supertrendCalculator = new SupertrendWithMA(
                $ohlcData,
                $config->supertrend_atr_period,
                $config->supertrend_multiplier,
                50 // MA period
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

    private function calculatePersistentSignals($brokerId, $symbol, $interval)
    {
        try {
            $records = SymbolData::where('broker_api_id', $brokerId)
                ->where('symbol', $symbol)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($records->isEmpty()) {
                return;
            }

            // Get previous day signal
            $firstTimestamp = $records->first()->timestamp;
            $previousDaySignal = $this->getPreviousTradingDaySignal(
                $brokerId,
                $symbol,
                $interval,
                $firstTimestamp
            );

            Log::info("📊 Previous day signal for {$symbol}: " . ($previousDaySignal ?? 'null'));

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

            Log::info("✅ Updated {$updateCount} records with persistent signals");

        } catch (Exception $e) {
            Log::error("Error calculating persistent signals: " . $e->getMessage());
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
                Log::info("✅ Found previous trading day: {$checkDateStr}");
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