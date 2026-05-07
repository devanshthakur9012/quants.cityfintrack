<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaHelper;
use App\Helpers\SupertrendCalculator;
use App\Models\ExpiryData;
use App\Models\ExpiryMonitored;
use App\Models\ExpiryConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchExpiry1MinHistoricalCommand extends Command
{
    protected $signature = 'expiry:fetch-1min-historical 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--symbol= : Specific symbol to fetch}';

    protected $description = 'Fetch historical 1-minute expiry data with Supertrend indicator';

    private $zerodha;

    public function handle()
    {
        try {
            $this->zerodha = new ZerodhaHelper();

            $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');

            $this->info("📊 Fetching Historical 1-Minute Expiry Data");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}\n");

            // Get monitored symbols
            if ($this->option('symbol')) {
                $symbols = ExpiryMonitored::where('symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%')
                    ->where('is_active', true)
                    ->get();
            } else {
                $symbols = ExpiryMonitored::where('is_active', true)->get();
            }

            if ($symbols->isEmpty()) {
                $this->warn('No expiry symbols found!');
                return 0;
            }

            $this->info("Processing " . $symbols->count() . " symbol(s)...\n");

            foreach ($symbols as $symbol) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("Processing: {$symbol->symbol}");
                $this->fetchHistoricalData($symbol, $fromDate, $toDate);
            }

            $this->info("\n✅ Historical 1-minute expiry data fetch completed successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('Expiry 1-Min Historical Data Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function fetchHistoricalData(ExpiryMonitored $symbol, $fromDate, $toDate)
    {
        try {
            // Get configuration for this symbol
            $config = ExpiryConfig::getForSymbol($symbol->symbol);
            
            $this->info("  Using config:");
            $this->info("    - ST ATR Period: {$config->supertrend_atr_period}");
            $this->info("    - ST Multiplier: {$config->supertrend_multiplier}");

            $this->info("  - Fetching 1-minute data...");

            $data = $this->zerodha->getHistoricalDataByToken(
                $symbol->instrument_token,
                'minute',
                $fromDate . ' 09:15:00',
                $toDate . ' 15:30:00'
            );

            if (empty($data)) {
                $this->warn("    No data received");
                return;
            }

            $this->info("    Received " . count($data) . " candles");

            $insertedCount = $this->storeHistoricalData($symbol, $data);
            $this->info("    ✓ Inserted/Updated {$insertedCount} records");

            if ($insertedCount > 0) {
                $this->calculateSupertrend($symbol, $config);
                $this->info("    ✓ Supertrend calculated");
            }

            $symbol->update(['last_fetched_at' => now()]);

        } catch (Exception $e) {
            $this->error("  ✗ Error fetching {$symbol->symbol}: " . $e->getMessage());
            Log::error("Error fetching {$symbol->symbol}: " . $e->getMessage());
        }
    }

    private function storeHistoricalData(ExpiryMonitored $symbol, array $data)
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

                // Check market hours
                $time = $timestamp->format('H:i:s');
                if ($time < '09:15:00' || $time > '15:30:00') {
                    $skippedCount++;
                    continue;
                }

                ExpiryData::updateOrCreate(
                    [
                        'symbol' => $symbol->symbol,
                        'timestamp' => $timestamp->format('Y-m-d H:i:s')
                    ],
                    [
                        'exchange' => $symbol->exchange,
                        'instrument_token' => $symbol->instrument_token,
                        'open' => $candle->open,
                        'high' => $candle->high,
                        'low' => $candle->low,
                        'close' => $candle->close,
                        'volume' => $candle->volume
                    ]
                );

                $insertedCount++;

            } catch (Exception $e) {
                Log::error("Error storing candle: " . $e->getMessage());
            }
        }

        if ($skippedCount > 0) {
            $this->info("    Skipped {$skippedCount} candles");
        }

        return $insertedCount;
    }

    private function calculateSupertrend(ExpiryMonitored $symbol, $config)
    {
        try {
            $records = ExpiryData::where('symbol', $symbol->symbol)
                ->orderBy('timestamp', 'ASC')
                ->get();

            $minRequired = $config->supertrend_atr_period + 2;

            if ($records->count() < $minRequired) {
                $this->warn("    Insufficient data for Supertrend");
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

            $calculator = new SupertrendCalculator(
                $ohlcData,
                $config->supertrend_atr_period,
                $config->supertrend_multiplier
            );
            $results = $calculator->calculateSupertrend();

            DB::beginTransaction();
            try {
                $updateCount = 0;
                foreach ($results as $result) {
                    $updated = DB::update("
                        UPDATE expiry_data 
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
            $this->error("    ✗ Supertrend calculation failed: " . $e->getMessage());
        }
    }
}