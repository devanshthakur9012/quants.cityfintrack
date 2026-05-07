<?php
// app/Console/Commands/FetchHistoricalOptionIVCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BrokerApi;
use App\Models\OptionIVData;
use App\Models\ZerodhaInstrument;
use App\Helpers\BrokerZerodhaHelperNew;
use App\Helpers\IVCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class FetchHistoricalOptionIVCommand extends Command
{
    protected $signature = 'options:fetch-iv-historical 
                            {--from= : From date (Y-m-d) REQUIRED}
                            {--to= : To date (Y-m-d) REQUIRED}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific symbol (NIFTY, BANKNIFTY)}
                            {--risk-free-rate=0.06 : Risk-free rate}';

    protected $description = 'Fetch Historical Option IV data using historical OHLC + IV calculation';

    private $symbols = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'];
    private $riskFreeRate = 0.06;

    public function handle()
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        
        if (!$fromDate || !$toDate) {
            $this->error('❌ Both --from and --to dates are required for historical fetch!');
            $this->info('Example: php artisan options:fetch-iv-historical --from=2026-01-01 --to=2026-01-31');
            return 1;
        }
        
        $this->riskFreeRate = floatval($this->option('risk-free-rate'));
        
        try {
            $this->info("🚀 Starting HISTORICAL Option IV Data Collection");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            $this->info("   Risk-Free Rate: " . ($this->riskFreeRate * 100) . "%\n");
            
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
            
            // ✅ FIX 1: Use only first broker (IV data is market-wide, not broker-specific)
            $broker = $brokers->first();
            
            $this->info("📋 Using broker: {$broker->client_name} (ID: {$broker->id})");
            $this->warn("   Note: IV data is market-wide, using single broker to avoid duplicate work\n");
            
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");
            
            $result = $this->processBrokerHistorical($broker, $fromDate, $toDate);
            
            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Historical IV Data Collection Completed!");
            $this->info("   Total Processed: {$result['success']}");
            $this->info("   IV Calculated: {$result['iv_calculated']}");
            $this->info("   Total Failed: {$result['failed']}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
            
            return 0;
            
        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Historical IV Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }
    
    private function processBrokerHistorical(BrokerApi $broker, $fromDate, $toDate)
    {
        $success = 0;
        $failed = 0;
        $ivCalculated = 0;
        
        try {
            $zerodhaHelper = new BrokerZerodhaHelperNew($broker);
            
            $symbolsToProcess = $this->symbols;
            
            if ($this->option('symbol')) {
                $symbolsToProcess = [strtoupper($this->option('symbol'))];
            }
            
            $this->info("   📊 Processing " . count($symbolsToProcess) . " symbol(s)");
            
            foreach ($symbolsToProcess as $symbol) {
                try {
                    $this->info("\n   └─ {$symbol}");
                    
                    // Get expiry that was active during the date range
                    $expiry = $this->getExpiryForDateRange($symbol, $fromDate, $toDate);
                    
                    if (!$expiry) {
                        $this->error("      ✗ No expiry found for date range");
                        $failed++;
                        continue;
                    }
                    
                    $this->info("      Expiry: {$expiry}");
                    
                    // Fetch historical IV data
                    $result = $this->fetchHistoricalIV($broker, $symbol, $expiry, $fromDate, $toDate, $zerodhaHelper);
                    
                    $success++;
                    $ivCalculated += $result['iv_count'];
                    $this->info("      ✓ Completed ({$result['iv_count']} IVs calculated)");
                    
                } catch (Exception $e) {
                    $failed++;
                    $this->error("      ✗ Failed: " . $e->getMessage());
                    Log::error("Historical IV fetch failed: {$symbol}", [
                        'broker_id' => $broker->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->info("\n   Summary: ✓ {$success} | ✗ {$failed} | 🧮 {$ivCalculated} IVs");
            
        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage());
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'iv_calculated' => $ivCalculated
        ];
    }
    
    /**
     * ✅ FIX 2: Calculate ATM strikes per timestamp (not average)
     */
    private function fetchHistoricalIV(
        BrokerApi $broker,
        string $symbol,
        string $expiry,
        string $fromDate,
        string $toDate,
        BrokerZerodhaHelperNew $zerodhaHelper
    ) {
        // STEP 1: Get future instrument
        $futureInstrument = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'FUT')
            ->whereDate('expiry', '>=', $fromDate)
            ->whereDate('expiry', '<=', Carbon::parse($toDate)->addMonths(2))
            ->orderBy('expiry', 'asc')
            ->first();
        
        if (!$futureInstrument) {
            throw new Exception("No future instrument found");
        }
        
        $this->info("      Future: {$futureInstrument->trading_symbol}");
        
        // STEP 2: Fetch historical data for future (to get underlying price)
        $futureHistorical = $zerodhaHelper->getHistoricalDataByToken(
            $futureInstrument->instrument_token,
            '5minute',
            $fromDate . ' 09:15:00',
            $toDate . ' 15:30:00'
        );
        
        if (empty($futureHistorical)) {
            throw new Exception("No historical data for future");
        }
        
        $this->info("      Future Historical: " . count($futureHistorical) . " candles");
        
        // STEP 3: Build a map of future prices by timestamp
        $futurePriceMap = [];
        foreach ($futureHistorical as $candle) {
            $candleDate = $candle->date instanceof \DateTime 
                ? Carbon::instance($candle->date) 
                : Carbon::parse($candle->date);
            $futurePriceMap[$candleDate->format('Y-m-d H:i:s')] = $candle->close;
        }
        
        // STEP 4: Get ALL possible option strikes (we'll filter to ATM dynamically)
        $options = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->get();
        
        if ($options->isEmpty()) {
            throw new Exception("No options found");
        }
        
        $this->info("      Options: " . $options->count() . " instruments (all strikes)");
        $this->info("      Filtering to ATM dynamically per timestamp...");
        
        // STEP 5: Fetch historical data for each option and calculate IV
        $ivCount = 0;
        $processedOptions = 0;
        
        foreach ($options as $option) {
            try {
                $optionHistorical = $zerodhaHelper->getHistoricalDataByToken(
                    $option->instrument_token,
                    '5minute',
                    $fromDate . ' 09:15:00',
                    $toDate . ' 15:30:00'
                );
                
                if (empty($optionHistorical)) {
                    continue;
                }
                
                // Process each candle
                foreach ($optionHistorical as $candle) {
                    $candleDate = $candle->date instanceof \DateTime 
                        ? Carbon::instance($candle->date) 
                        : Carbon::parse($candle->date);
                    
                    $timestampKey = $candleDate->format('Y-m-d H:i:s');
                    
                    // Get future price for this specific timestamp
                    if (!isset($futurePriceMap[$timestampKey])) {
                        continue;
                    }
                    
                    $futurePrice = $futurePriceMap[$timestampKey];
                    $optionPrice = $candle->close;
                    
                    if (!$optionPrice || $optionPrice <= 0) continue;
                    
                    // ✅ FIX: Calculate ATM strikes for THIS specific timestamp
                    $atmStrikesNow = $this->getATMStrikes($symbol, $futurePrice);
                    
                    // ✅ Check if THIS option's strike is ATM at THIS time
                    $isATM = in_array($option->strike, $atmStrikesNow);
                    
                    if (!$isATM) {
                        // Skip non-ATM options for this timestamp
                        continue;
                    }
                    
                    // Determine exact ATM position
                    $atmPosition = $this->determineATMPosition($option->strike, $atmStrikesNow);
                    
                    // Calculate days to expiry
                    $daysToExpiry = Carbon::parse($expiry)->diffInDays($candleDate);
                    if ($daysToExpiry == 0) $daysToExpiry = 1;
                    
                    // Calculate IV
                    $calculatedIV = IVCalculator::calculate(
                        $optionPrice,
                        $futurePrice,
                        $option->strike,
                        $daysToExpiry,
                        $option->instrument_type,
                        $this->riskFreeRate
                    );
                    
                    if ($calculatedIV === null) continue;
                    
                    // Save to database
                    OptionIVData::updateOrCreate([
                        'broker_api_id' => $broker->id,
                        'trading_symbol' => $option->trading_symbol,
                        'timestamp' => $candleDate,
                    ], [
                        'symbol' => $option->name,
                        'expiry' => $expiry,
                        'strike' => $option->strike,
                        'option_type' => $option->instrument_type,
                        'ltp' => $optionPrice,
                        'iv' => $calculatedIV,
                        'oi' => $candle->oi ?? 0,
                        'volume' => $candle->volume ?? 0,
                        'atm_position' => $atmPosition,
                        'future_price' => $futurePrice,
                        'days_to_expiry' => $daysToExpiry,
                        'risk_free_rate' => $this->riskFreeRate,
                    ]);
                    
                    $ivCount++;
                }
                
                $processedOptions++;
                
                // Show progress every 50 options
                if ($processedOptions % 50 == 0) {
                    $this->comment("      Progress: {$processedOptions}/{$options->count()} options processed, {$ivCount} IVs calculated...");
                }
                
            } catch (Exception $e) {
                Log::warning("Option historical fetch failed: {$option->trading_symbol}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['iv_count' => $ivCount];
    }
    
    private function getExpiryForDateRange($symbol, $fromDate, $toDate)
    {
        return ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', '>=', $fromDate)
            ->whereDate('expiry', '<=', Carbon::parse($toDate)->addDays(30))
            ->orderBy('expiry', 'asc')
            ->value('expiry');
    }
    
    private function getATMStrikes($symbol, $futurePrice)
    {
        $intervals = config('iv_analysis.atm.strike_intervals');
        $interval = $intervals[$symbol] ?? 50;
        
        $atmStrike = round($futurePrice / $interval) * $interval;
        
        return [
            $atmStrike - $interval,  // ATM-1
            $atmStrike,               // ATM
            $atmStrike + $interval,   // ATM+1
        ];
    }
    
    private function determineATMPosition($strike, $atmStrikes)
    {
        if ($strike == $atmStrikes[0]) return 'ATM-1';
        if ($strike == $atmStrikes[1]) return 'ATM';
        if ($strike == $atmStrikes[2]) return 'ATM+1';
        return 'OTHER';
    }
}