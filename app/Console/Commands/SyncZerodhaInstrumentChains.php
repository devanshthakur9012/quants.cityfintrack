<?php

namespace App\Console\Commands;

use App\Models\InstrumentChain;
use App\Traits\ZerodhaApiHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncZerodhaInstrumentChains extends Command
{
    use ZerodhaApiHelper;

    protected $signature = 'zerodha:sync-chains 
                            {--symbol=* : Specific symbols to process}
                            {--strikes=7 : Number of strikes to fetch}
                            {--force : Force sync even on holidays}';
    
    protected $description = 'Sync instrument chain data from Zerodha';

    private $processedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;

    public function handle()
    {
        set_time_limit(0);
        
        if (!$this->shouldRun()) {
            return 0;
        }

        $this->info("🚀 Starting Zerodha Instrument Chain Sync...\n");

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
            Log::error('Zerodha chain sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function shouldRun(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $today = date("Y-m-d");
        $dayName = date("l");

        if ($dayName == "Saturday" || $dayName == "Sunday") {
            $this->info("Skipped: Weekend ($dayName)");
            return false;
        }

        $isHoliday = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $today)
            ->exists();

        if ($isHoliday) {
            $this->info("Skipped: Market Holiday ($today)");
            return false;
        }

        return true;
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
                'NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY',
                'AXISBANK', 'BAJFINANCE', 'BHARTIARTL', 'DRREDDY', 
                'CIPLA', 'SHRIRAMFIN', 'HDFCBANK', 'RELIANCE'
            ];
            $query->whereIn('symbol', $defaultSymbols);
        }

        return $query->get(['underlying', 'symbol', 'step_value', 'symbol_token']);
    }

    private function processSymbol($symbolData)
    {
        try {
            $this->line("⏳ Processing: {$symbolData->symbol}...");

            $future = $this->getZerodhaFutureContract($symbolData->symbol);
            if (!$future) {
                $this->warn("⚠️  Skipped: {$symbolData->symbol} - No future contract");
                $this->skippedCount++;
                return;
            }

            $priceData = $this->getValidatedPrice($future->instrument_token, $symbolData->symbol);
            if (!$priceData || !$priceData['valid']) {
                $this->warn("⚠️  Skipped: {$symbolData->symbol} - Invalid price");
                $this->skippedCount++;
                return;
            }

            $futurePrice = $priceData['price'];
            $strikeCount = (int) $this->option('strikes');
            $strikes = $this->calculateStrikesZerodha($symbolData->step_value, $futurePrice, $strikeCount);

            InstrumentChain::deactivateOldRecords($symbolData->symbol);

            $recordsCreated = 0;

            if ($this->insertFutureContract($symbolData->symbol, $future, $futurePrice, $symbolData->step_value)) {
                $recordsCreated++;
            }

            foreach ($strikes as $position => $strikePrice) {
                $options = $this->getZerodhaOptionsForStrike($symbolData->symbol, $strikePrice);
                
                $midPoint = floor($strikeCount / 2);
                $isAtm = ($position === $midPoint);
                $relativePosition = $position - $midPoint;

                if (!empty($options['CE'])) {
                    $this->insertOption(
                        $symbolData->symbol, 
                        $options['CE'], 
                        'CE', 
                        $strikePrice, 
                        $relativePosition, 
                        $isAtm, 
                        $symbolData->step_value
                    );
                    $recordsCreated++;
                }

                if (!empty($options['PE'])) {
                    $this->insertOption(
                        $symbolData->symbol, 
                        $options['PE'], 
                        'PE', 
                        $strikePrice, 
                        $relativePosition, 
                        $isAtm, 
                        $symbolData->step_value
                    );
                    $recordsCreated++;
                }
            }

            if ($recordsCreated > 0) {
                $this->info("✅ Success: {$symbolData->symbol} ({$recordsCreated} records)");
                $this->processedCount++;
            } else {
                $this->warn("⚠️  Skipped: {$symbolData->symbol} - No valid data");
                $this->skippedCount++;
            }

        } catch (\Exception $e) {
            Log::error("Symbol processing failed: {$symbolData->symbol}", [
                'error' => $e->getMessage()
            ]);
            $this->error("❌ Failed: {$symbolData->symbol}");
            $this->failedCount++;
        }
    }

    private function getValidatedPrice(string $instrumentToken, string $symbol): ?array
    {
        try {
            $ltpData = $this->getZerodhaLTP([$instrumentToken], 'NFO');
            $key = "NFO:{$instrumentToken}";
            
            if (empty($ltpData) || !isset($ltpData[$key])) {
                return ['valid' => false, 'price' => null];
            }

            $price = (float) ($ltpData[$key]['last_price'] ?? 0);

            if ($price <= 0 || $price > 1000000) {
                return ['valid' => false, 'price' => $price];
            }

            return ['valid' => true, 'price' => $price];

        } catch (\Exception $e) {
            Log::error("Price validation failed for {$symbol}", ['error' => $e->getMessage()]);
            return ['valid' => false, 'price' => null];
        }
    }

    private function insertFutureContract($underlying, $future, $price, $stepValue): bool
    {
        try {
            InstrumentChain::create([
                'underlying' => $underlying,
                'symbol' => $future->trading_symbol,
                'token' => $future->instrument_token,
                'type' => 'FUT',
                'exchange' => $future->exchange ?? 'NFO',
                'expiry_date' => $future->expiry ? \Carbon\Carbon::parse($future->expiry)->format('Y-m-d') : null,
                'lot_size' => $future->lot_size ?? null,
                'tick_size' => $future->tick_size ?? null,
                'current_price' => $price,
                'step_value' => $stepValue,
                'is_active' => true,
                'generated_at' => now(),
                'data_source' => 'zerodha'
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to insert future", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function insertOption($underlying, $option, $type, $strikePrice, $position, $isAtm, $stepValue): bool
    {
        try {
            InstrumentChain::create([
                'underlying' => $underlying,
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'type' => $type,
                'exchange' => $option->exchange ?? 'NFO',
                'strike_price' => $strikePrice,
                'strike_position' => $position,
                'is_atm' => $isAtm,
                'expiry_date' => $option->expiry ? \Carbon\Carbon::parse($option->expiry)->format('Y-m-d') : null,
                'lot_size' => $option->lot_size ?? null,
                'tick_size' => $option->tick_size ?? null,
                'step_value' => $stepValue,
                'is_active' => true,
                'generated_at' => now(),
                'data_source' => 'zerodha'
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to insert option", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function displaySummary($totalSymbols)
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("      SYNC SUMMARY        ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Symbols', $totalSymbols],
                ['✅ Processed', $this->processedCount],
                ['⚠️  Skipped', $this->skippedCount],
                ['❌ Failed', $this->failedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}