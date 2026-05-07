<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Helpers\SupertrendCalculator;
use App\Helpers\DonchianCalculator;
use App\Helpers\RSICalculator;
use App\Helpers\MACDCalculator;
use App\Helpers\VWAPCalculator;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\BrokerApi;
use App\Models\IndicatorConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchSymbols1MinCommand extends Command
{
    protected $signature = 'symbols:fetch-1min 
                            {--broker= : Specific broker ID to fetch}
                            {--symbol= : Specific symbol to fetch} 
                            {--force : Force fetch even on holidays}
                            {--backfill : Fetch full day data from 9:15 AM}';

    protected $description = 'Fetch 1-minute symbol data using broker-specific API credentials';

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        // Holiday check (unless forced)
        if (!$this->option('force')) {
            if ($dayName == "Saturday" || $dayName == "Sunday") {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("1-min symbols fetch skipped: Weekend");
                return 0;
            }

            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("1-min symbols fetch skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->info("🚀 Starting 1-Minute Symbol Data Fetch");
            $this->info("   Time: " . Carbon::now()->format('Y-m-d H:i:s'));
            $this->info("   Interval: minute\n");

            // Get active brokers with valid tokens
            $brokersQuery = BrokerApi::zerodha()->validToken();

            if ($this->option('broker')) {
                $brokersQuery->where('id', $this->option('broker'));
            }

            $brokers = $brokersQuery->get();

            if ($brokers->isEmpty()) {
                $this->error('❌ No active Zerodha brokers with valid tokens found!');
                $this->info('Please ensure brokers are logged in and have valid access tokens.');
                return 1;
            }

            $this->info("📋 Found " . $brokers->count() . " broker(s) with valid tokens\n");

            $totalProcessed = 0;
            $totalFailed = 0;

            foreach ($brokers as $broker) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");
                $this->info("   Username: {$broker->account_user_name}");
                $this->info("   Token Valid: " . ($broker->hasValidToken() ? '✅ Yes' : '❌ No'));

                if (!$broker->hasValidToken()) {
                    $this->warn("   ⚠️ Skipping - Token expired or invalid\n");
                    continue;
                }

                $result = $this->processBroker($broker);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Fetch Completed!");
            $this->info("   Total Processed: {$totalProcessed}");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('1-Min Symbols Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Process all symbols for a specific broker
     */
    private function processBroker(BrokerApi $broker)
    {
        $success = 0;
        $failed = 0;

        try {
            // Initialize broker-specific API helper
            $zerodhaHelper = new BrokerZerodhaHelper($broker);

            // Get symbols assigned to this broker for 1-minute interval
            $symbolsQuery = SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('is_active', true)
                ->where('interval', 'minute');

            if ($this->option('symbol')) {
                $symbolsQuery->where('symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
            }

            $symbols = $symbolsQuery->get();

            if ($symbols->isEmpty()) {
                $this->warn("   ⚠️  No 1-minute symbols assigned to this broker\n");
                return ['success' => 0, 'failed' => 0];
            }

            $this->info("   📊 Processing " . $symbols->count() . " symbol(s)");
            $this->newLine();

            foreach ($symbols as $symbol) {
                try {
                    $this->info("   └─ {$symbol->trading_symbol} ({$symbol->symbol})");
                    $this->fetchSymbolData($broker, $symbol, $zerodhaHelper);
                    $success++;
                    $this->info("      ✓ Completed\n");

                } catch (Exception $e) {
                    $failed++;
                    $this->error("      ✗ Failed: " . $e->getMessage() . "\n");
                    Log::error("Symbol fetch failed: {$symbol->trading_symbol}", [
                        'broker_id' => $broker->id,
                        'symbol' => $symbol->symbol,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("   Summary: ✓ {$success} | ✗ {$failed}\n");

        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage() . "\n");
            Log::error("Broker processing error: {$broker->client_name}", [
                'broker_id' => $broker->id,
                'error' => $e->getMessage()
            ]);
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Fetch data for a specific symbol
     */
    private function fetchSymbolData(BrokerApi $broker, SymbolMonitored $symbol, BrokerZerodhaHelper $zerodhaHelper)
    {
        $fromDate = $this->getFromDate($broker->id, $symbol);
        $toDate = date('Y-m-d H:i:s');

        $this->info("      From: {$fromDate}");
        $this->info("      To: {$toDate}");

        // Fetch historical data using broker's API
        $data = $zerodhaHelper->getHistoricalDataByToken(
            $symbol->instrument_token,
            'minute',
            $fromDate,
            $toDate
        );

        if (empty($data)) {
            $this->warn("      ⚠️ No data received");
            return;
        }

        $this->info("      📥 Received " . count($data) . " candles");

        // Store OHLCV data
        $insertedCount = $this->storeHistoricalData($broker->id, $symbol, $data, 'minute');
        $this->info("      💾 Stored {$insertedCount} records");

        // Calculate indicators
        if ($insertedCount > 0) {
            $config = IndicatorConfig::getForSymbol($symbol->trading_symbol);
            $this->calculateIndicators($broker->id, $symbol, 'minute', $config);
            $this->info("      🔢 Indicators calculated");
        }

        // Update last sync time
        $symbol->update(['last_synced_at' => now()]);
    }

    /**
     * Get from date for incremental fetch
     */
    private function getFromDate($brokerId, SymbolMonitored $symbol)
    {
        $marketOpenToday = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        $now = Carbon::now('Asia/Kolkata');

        if ($this->option('backfill')) {
            return $marketOpenToday->format('Y-m-d H:i:s');
        }

        $lastRecord = SymbolData::where('broker_api_id', $brokerId)
            ->where('symbol', $symbol->symbol)
            ->where('interval', 'minute')
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

    /**
     * Store historical OHLCV data
     */
    private function storeHistoricalData($brokerId, SymbolMonitored $symbol, array $data, string $interval)
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

                // Skip holidays
                if ($this->isMarketHoliday($timestamp->format('Y-m-d'))) {
                    $skippedCount++;
                    continue;
                }

                // Check market hours
                $time = $timestamp->format('H:i:s');
                if ($time < '09:15:00' || $time > '15:30:00') {
                    $skippedCount++;
                    continue;
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
                        'oi' => $candle->oi ?? 0
                    ]
                );

                $insertedCount++;

            } catch (Exception $e) {
                Log::error("Error storing candle: " . $e->getMessage());
            }
        }

        return $insertedCount;
    }

    /**
     * Calculate all technical indicators
     */
    private function calculateIndicators($brokerId, SymbolMonitored $symbol, string $interval, IndicatorConfig $config)
    {
        try {
            $records = SymbolData::where('broker_api_id', $brokerId)
                ->where('symbol', $symbol->symbol)
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
     * Check if date is market holiday
     */
    private function isMarketHoliday($date)
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}