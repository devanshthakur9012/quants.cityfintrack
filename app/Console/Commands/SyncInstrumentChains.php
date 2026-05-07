<?php

namespace App\Console\Commands;

use App\Models\InstrumentChain;
use App\Traits\AngelApiHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncInstrumentChains extends Command
{
    use AngelApiHelper;

    protected $signature = 'instruments:sync-chains {--symbol=* : Specific symbols to process}';
    protected $description = 'Sync instrument chain data (futures and options) for symbols';

    private $processedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;

    public function handle()
    {
        set_time_limit(0);
        $today = date("Y-m-d");
        $dayName = date("l");

        //----------------------------------------
        // 1️⃣ Skip Saturday & Sunday
        //----------------------------------------
        // if ($dayName == "Saturday" || $dayName == "Sunday") {
        //     $this->info("Skipped: Weekend ($dayName)");
        //     return 0;
        // }

        //----------------------------------------
        // 2️⃣ Skip if market holiday from DB
        //----------------------------------------
        $isHoliday = \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $today)
            ->exists();

        // if ($isHoliday) {
        //     $this->info("Skipped: Market Holiday ($today)");
        //     return 0;
        // }

        $this->info("🚀 Starting Instrument Chain Sync...\n");

        try {
            $symbols = $this->getSymbolsToProcess();
            
            if ($symbols->isEmpty()) {
                $this->error('❌ No symbols found to process.');
                return 1;
            }

            $this->info("📊 Found {$symbols->count()} symbol(s) to process.\n");

            DB::beginTransaction();

            foreach ($symbols as $symbolData) {
                $this->processSymbol($symbolData);
            }

            DB::commit();
            
            $this->displaySummary($symbols->count());

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Instrument chain sync critical error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Critical Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function getSymbolsToProcess()
    {
        $symbolOption = $this->option('symbol');

        $query = DB::table('symbol_lists')
            ->orderBy('id', 'DESC')
            ->orderBy('is_early');

        if (!empty($symbolOption)) {
            $query->whereIn('symbol', $symbolOption);
        } else {
            $defaultSymbols = [
                'AXISBANK', 'BAJFINANCE', 'BHARTIARTL', 'DRREDDY', 
                'CIPLA', 'SHRIRAMFIN', 'CHOALFIN', 'PAYTM', 
                'NIFTY', 'BANKNIFTY',  'EICHERMOT'
            ];
            $query->whereIn('symbol', $defaultSymbols);
        }

        return $query->get(['underlying', 'symbol', 'step_value', 'symbol_token']);
    }

    private function processSymbol($symbolData)
    {
        try {
            $this->line("⏳ Processing: {$symbolData->symbol}...");

            // Get future contract
            $future = $this->getFutureContract($symbolData->symbol);
            if (!$future) {
                $this->warn("⚠️  Skipped: {$symbolData->symbol} - No future contract");
                $this->skippedCount++;
                return;
            }

            // Get current price
            $futurePrice = $this->getCurrentPrice($symbolData->symbol_token);
            if (!$futurePrice) {
                $this->warn("⚠️  Skipped: {$symbolData->symbol} - Price fetch failed");
                $this->skippedCount++;
                return;
            }

            // Calculate strikes
            $strikes = $this->calculateStrikes($symbolData->step_value, $futurePrice);

            // Deactivate old records
            InstrumentChain::deactivateOldRecords($symbolData->symbol);

            $recordsCreated = 0;

            // Insert future contract
            $this->insertFutureContract($symbolData->symbol, $future, $futurePrice, $symbolData->step_value);
            $recordsCreated++;

            // Insert options for each strike
            foreach ($strikes as $position => $strikePrice) {
                $options = $this->getOptionsForStrike($symbolData->symbol, $strikePrice);
                
                $atmPosition = 3; // Middle position is ATM
                $isAtm = ($position === $atmPosition);
                $relativePosition = $position - $atmPosition;

                // Insert CE
                if (!empty($options['CE'])) {
                    $this->insertOption($symbolData->symbol, $options['CE'], 'CE', $strikePrice, $relativePosition, $isAtm, $symbolData->step_value);
                    $recordsCreated++;
                }

                // Insert PE
                if (!empty($options['PE'])) {
                    $this->insertOption($symbolData->symbol, $options['PE'], 'PE', $strikePrice, $relativePosition, $isAtm, $symbolData->step_value);
                    $recordsCreated++;
                }
            }

            if ($recordsCreated > 0) {
                $this->info("✅ Success: {$symbolData->symbol} ({$recordsCreated} records)");
                $this->processedCount++;
            } else {
                $this->warn("⚠️  Skipped: {$symbolData->symbol} - No data generated");
                $this->skippedCount++;
            }

        } catch (\Exception $e) {
            Log::error("Symbol processing failed: {$symbolData->symbol}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Failed: {$symbolData->symbol} - {$e->getMessage()}");
            $this->failedCount++;
        }
    }

    private function insertFutureContract($underlying, $future, $price, $stepValue)
    {
        InstrumentChain::create([
            'underlying' => $underlying,
            'symbol' => $future->symbol_name,
            'token' => $future->token,
            'type' => 'FUT',
            'exchange' => $future->exch_seg ?? 'NFO',
            'expiry_date' => $this->parseExpiry($future->expiry ?? null),
            'lot_size' => $future->lotsize ?? null,
            'tick_size' => $future->tick_size ?? null,
            'current_price' => $price,
            'step_value' => $stepValue,
            'is_active' => true,
            'generated_at' => now()
        ]);
    }

    private function insertOption($underlying, $option, $type, $strikePrice, $position, $isAtm, $stepValue)
    {
        InstrumentChain::create([
            'underlying' => $underlying,
            'symbol' => $option->symbol_name,
            'token' => $option->token,
            'type' => $type,
            'exchange' => $option->exch_seg ?? 'NFO',
            'strike_price' => $strikePrice,
            'strike_position' => $position,
            'is_atm' => $isAtm,
            'expiry_date' => $this->parseExpiry($option->expiry ?? null),
            'lot_size' => $option->lotsize ?? null,
            'tick_size' => $option->tick_size ?? null,
            'step_value' => $stepValue,
            'is_active' => true,
            'generated_at' => now()
        ]);
    }

    private function parseExpiry($expiry)
    {
        if (!$expiry) return null;
        
        try {
            return \Carbon\Carbon::createFromFormat('dmY', $expiry)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function displaySummary($totalSymbols)
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("         SYNC SUMMARY          ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Symbols', $totalSymbols],
                ['✅ Successfully Processed', $this->processedCount],
                ['⚠️  Skipped', $this->skippedCount],
                ['❌ Failed', $this->failedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}