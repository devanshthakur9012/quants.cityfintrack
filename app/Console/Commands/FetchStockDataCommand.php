<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ZerodhaHelper;
use App\Models\StockData;
use App\Models\MonitoredStock;
use Illuminate\Support\Facades\Log;
use Exception;

class FetchStockDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:fetch-data {--symbol= : Specific symbol to fetch} {--force : Force fetch even on holidays}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch historical stock data from Zerodha for monitored stocks';

    private $zerodha;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        //----------------------------------------
        // Skip Saturday & Sunday
        //----------------------------------------
        if (!$this->option('force')) {
            if ($dayName == "Saturday" || $dayName == "Sunday") {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("Stock data fetch skipped: Weekend ($dayName)");
                return 0;
            }

            //----------------------------------------
            // Skip if market holiday from DB
            //----------------------------------------
            $isHoliday = \DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Stock data fetch skipped: Market Holiday ($today)");
                return 0;
            }
        }

        try {
            $this->zerodha = new ZerodhaHelper();
            
            // Get stocks to monitor
            if ($this->option('symbol')) {
                $stocks = MonitoredStock::where('trading_symbol', $this->option('symbol'))
                    ->where('is_active', true)
                    ->get();
            } else {
                $stocks = MonitoredStock::getActiveStocks();
            }

            if ($stocks->isEmpty()) {
                $this->warn('No monitored stocks found!');
                return 0;
            }

            $this->info("Fetching data for " . $stocks->count() . " stock(s)...");

            foreach ($stocks as $stock) {
                $this->info("\n Processing: {$stock->trading_symbol}");
                $this->fetchStockData($stock);
            }

            $this->info("\n✅ Stock data fetch completed successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('Stock Data Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Fetch data for a specific stock
     */
    private function fetchStockData(MonitoredStock $stock)
    {
        try {
            $intervals = $stock->intervals_array;

            foreach ($intervals as $interval) {
                $this->info("  - Fetching {$interval} data...");
                
                // Determine from date based on last fetch or default
                $fromDate = $this->getFromDate($stock, $interval);
                $toDate = date('Y-m-d H:i:s');

                // Fetch historical data
                $data = $this->zerodha->getHistoricalData(
                    $stock->trading_symbol,
                    $interval,
                    $fromDate,
                    $toDate,
                    $stock->exchange
                );

                if (empty($data)) {
                    $this->warn("    No data received for {$interval}");
                    continue;
                }

                // Store data in database
                $insertedCount = $this->storeStockData($stock, $data, $interval);
                
                $this->info("    ✓ Inserted/Updated {$insertedCount} records for {$interval}");
            }

            // Update last fetched timestamp
            $stock->update(['last_fetched_at' => now()]);

        } catch (Exception $e) {
            $this->error("  ✗ Error fetching {$stock->trading_symbol}: " . $e->getMessage());
            Log::error("Error fetching {$stock->trading_symbol}: " . $e->getMessage());
        }
    }

    /**
     * Determine from date for fetching
     */
    private function getFromDate(MonitoredStock $stock, $interval)
    {
        // If never fetched, get last 7 days
        if (!$stock->last_fetched_at) {
            return date('Y-m-d H:i:s', strtotime('-7 days'));
        }

        // Get last record timestamp for this interval
        $lastRecord = StockData::where('trading_symbol', $stock->trading_symbol)
            ->where('interval', $interval)
            ->orderBy('timestamp', 'desc')
            ->first();

        if ($lastRecord) {
            // Fetch from last record time
            return $lastRecord->timestamp->format('Y-m-d H:i:s');
        }

        // Default to last 7 days
        return date('Y-m-d H:i:s', strtotime('-7 days'));
    }

    /**
     * Store stock data in database
     */
    private function storeStockData(MonitoredStock $stock, array $data, string $interval)
    {
        $insertedCount = 0;

        foreach ($data as $candle) {
            try {
                StockData::updateOrCreate(
                    [
                        'trading_symbol' => $stock->trading_symbol,
                        'exchange' => $stock->exchange,
                        'interval' => $interval,
                        'timestamp' => $candle->date
                    ],
                    [
                        'instrument_token' => $stock->instrument_token,
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
                Log::error("Error storing candle data: " . $e->getMessage());
            }
        }

        return $insertedCount;
    }
}