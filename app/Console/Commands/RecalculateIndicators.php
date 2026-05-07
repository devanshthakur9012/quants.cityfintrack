<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\SupertrendCalculator;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\BrokerApi;
use App\Models\IndicatorConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class RecalculateIndicators extends Command
{
    protected $signature = 'indicators:recalculate 
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific symbol to recalculate} 
                            {--interval= : Specific interval (5minute, 15minute, etc)}';

    protected $description = 'Recalculate ONLY Supertrend indicator for existing data based on current config';

    public function handle()
    {
        try {
            $this->info("🔄 Starting Supertrend Recalculation");
            $this->info("   Time: " . Carbon::now()->format('Y-m-d H:i:s'));
            $this->info("   Mode: Recalculate ONLY Supertrend from existing data\n");

            // Get active brokers
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
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

                $result = $this->processBroker($broker);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Supertrend Recalculation Completed!");
            $this->info("   Total Processed: {$totalProcessed}");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Supertrend Recalculation Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBroker(BrokerApi $broker)
    {
        $success = 0;
        $failed = 0;

        try {
            // Get symbols assigned to this broker
            $symbolsQuery = SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('is_active', true);

            if ($this->option('symbol')) {
                $symbolsQuery->where('symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
            }

            if ($this->option('interval')) {
                $symbolsQuery->where('interval', $this->option('interval'));
            }

            $symbols = $symbolsQuery->get();

            if ($symbols->isEmpty()) {
                $this->warn("   ⚠️  No symbols found for this broker\n");
                return ['success' => 0, 'failed' => 0];
            }

            $this->info("   📊 Processing " . $symbols->count() . " symbol(s)");
            $this->newLine();

            foreach ($symbols as $symbol) {
                try {
                    $this->info("   └─ {$symbol->trading_symbol} ({$symbol->interval})");
                    $this->recalculateForSymbol($broker, $symbol);
                    $success++;
                    $this->info("      ✓ Completed\n");

                } catch (Exception $e) {
                    $failed++;
                    $this->error("      ✗ Failed: " . $e->getMessage() . "\n");
                    Log::error("Recalculation failed: {$symbol->trading_symbol}", [
                        'broker_id' => $broker->id,
                        'symbol' => $symbol->symbol,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("   Summary: ✓ {$success} | ✗ {$failed}\n");

        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage() . "\n");
            Log::error("Broker recalculation error: {$broker->client_name}", [
                'broker_id' => $broker->id,
                'error' => $e->getMessage()
            ]);
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function recalculateForSymbol(BrokerApi $broker, SymbolMonitored $symbol)
    {
        // Get existing data count
        $dataCount = SymbolData::where('broker_api_id', $broker->id)
            ->where('symbol', $symbol->symbol)
            ->where('interval', $symbol->interval)
            ->count();

        $this->info("      📦 Found {$dataCount} existing records");

        if ($dataCount === 0) {
            $this->warn("      ⚠️ No data to recalculate");
            return;
        }

        // Get config for this symbol
        $config = IndicatorConfig::getForSymbol($symbol->trading_symbol);
        
        // Recalculate ONLY Supertrend
        $this->calculateSupertrendOnly($broker->id, $symbol, $symbol->interval, $config);
        $this->info("      🔢 Supertrend recalculated");
    }

    /**
     * Calculate ONLY Supertrend indicator (matching FetchSymbols5MinCommand)
     */
    private function calculateSupertrendOnly($brokerId, SymbolMonitored $symbol, string $interval, IndicatorConfig $config)
    {
        try {
            // Fetch all existing data for this symbol
            $records = SymbolData::where('broker_api_id', $brokerId)
                ->where('symbol', $symbol->symbol)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC')
                ->get();

            // Only check for Supertrend requirements (same as FetchSymbols5MinCommand)
            $minRequired = $config->supertrend_atr_period + 2;

            if ($records->count() < $minRequired) {
                $this->warn("      ⚠️ Not enough data. Need {$minRequired}, have {$records->count()}");
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

            // Calculate ONLY Supertrend
            $supertrendCalculator = new SupertrendCalculator(
                $ohlcData,
                $config->supertrend_atr_period,
                $config->supertrend_multiplier
            );
            $supertrendResults = $supertrendCalculator->calculateSupertrend();

            // Update database - ONLY Supertrend fields (same as FetchSymbols5MinCommand)
            DB::beginTransaction();
            try {
                foreach ($supertrendResults as $result) {
                    DB::update("
                        UPDATE symbol_data 
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
                }
                DB::commit();

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Supertrend calculation error: " . $e->getMessage());
            throw $e;
        }
    }
}