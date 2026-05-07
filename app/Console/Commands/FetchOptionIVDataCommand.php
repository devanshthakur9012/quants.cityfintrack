<?php
// app/Console/Commands/FetchOptionIVDataCommand.php

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

class FetchOptionIVDataCommand extends Command
{
    protected $signature = 'options:fetch-iv 
                            {--from= : From date (Y-m-d)}
                            {--to= : To date (Y-m-d)}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific symbol (NIFTY, BANKNIFTY)}
                            {--force : Force fetch even on holidays}
                            {--risk-free-rate= : Risk-free rate (default: 0.06 for 6%)}';

    protected $description = 'Fetch Option Chain IV data (ATM-1, ATM, ATM+1) with IV Calculation';

    private $symbols = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'];
    private $riskFreeRate = 0.06; // 6% default

    public function handle()
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        
        // Set risk-free rate if provided
        if ($this->option('risk-free-rate')) {
            $this->riskFreeRate = floatval($this->option('risk-free-rate'));
        }
        
        // If no dates provided, use current date (daily mode)
        if (!$fromDate && !$toDate) {
            $fromDate = $toDate = Carbon::today()->format('Y-m-d');
            $this->info("📅 Daily Mode: " . $fromDate);
        } else {
            $fromDate = $fromDate ?: Carbon::today()->format('Y-m-d');
            $toDate = $toDate ?: Carbon::today()->format('Y-m-d');
            $this->info("📅 Historical Mode: {$fromDate} to {$toDate}");
        }
        
        // Holiday check (unless forced)
        if (!$this->option('force')) {
            if ($this->isWeekendOrHoliday($fromDate)) {
                $this->info("⏭️  Skipped: Weekend or Holiday");
                return 0;
            }
        }
        
        try {
            $this->info("🚀 Starting Option IV Data Collection with IV Calculation");
            $this->info("   Time: " . Carbon::now()->format('Y-m-d H:i:s'));
            $this->info("   Risk-Free Rate: " . ($this->riskFreeRate * 100) . "%");
            $this->info("   Config: ATM Range = ±" . config('iv_analysis.atm.range') . "\n");
            
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
            $totalIVCalculated = 0;
            
            foreach ($brokers as $broker) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");
                
                $result = $this->processBroker($broker, $fromDate, $toDate);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
                $totalIVCalculated += $result['iv_calculated'];
            }
            
            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ IV Data Collection Completed!");
            $this->info("   Total Processed: {$totalProcessed}");
            $this->info("   IV Calculated: {$totalIVCalculated}");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
            
            return 0;
            
        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Option IV Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Process broker for IV data collection
     */
    private function processBroker(BrokerApi $broker, $fromDate, $toDate)
    {
        $success = 0;
        $failed = 0;
        $ivCalculated = 0;
        
        try {
            $zerodhaHelper = new BrokerZerodhaHelperNew($broker);
            
            // Get symbols to process
            $symbolsToProcess = $this->symbols;
            
            if ($this->option('symbol')) {
                $symbolsToProcess = [strtoupper($this->option('symbol'))];
            }
            
            $this->info("   📊 Processing " . count($symbolsToProcess) . " symbol(s)");
            
            foreach ($symbolsToProcess as $symbol) {
                try {
                    $this->info("\n   └─ {$symbol}");
                    
                    // Get expiry for this symbol
                    $expiry = $this->getNearestExpiry($symbol);
                    
                    if (!$expiry) {
                        $this->error("      ✗ No expiry found");
                        $failed++;
                        continue;
                    }
                    
                    $this->info("      Expiry: {$expiry}");
                    
                    // Collect IV data with calculation
                    $result = $this->fetchIVDataWithCalculation($broker, $symbol, $expiry, $zerodhaHelper);
                    
                    $success++;
                    $ivCalculated += $result['iv_count'];
                    $this->info("      ✓ Completed ({$result['iv_count']} IVs calculated)");
                    
                } catch (Exception $e) {
                    $failed++;
                    $this->error("      ✗ Failed: " . $e->getMessage());
                    Log::error("IV fetch failed: {$symbol}", [
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
     * Fetch IV data with Black-Scholes IV calculation
     */
    private function fetchIVDataWithCalculation(
        BrokerApi $broker, 
        string $symbol, 
        string $expiry, 
        BrokerZerodhaHelperNew $zerodhaHelper
    ) {
        // STEP 1: Get the underlying future instrument to get live price
        $futureInstrument = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'FUT')
            ->whereDate('expiry', '>=', now())
            ->orderBy('expiry', 'asc')
            ->first();
        
        if (!$futureInstrument) {
            throw new Exception("No future instrument found for {$symbol}");
        }
        
        $this->info("      Future: {$futureInstrument->trading_symbol}");
        
        // STEP 2: Fetch live quote to get current price
        $futureSymbolWithExchange = $futureInstrument->exchange . ':' . $futureInstrument->trading_symbol;
        $this->info("      Fetching quote for: {$futureSymbolWithExchange}");
        
        try {
            $futureQuote = $zerodhaHelper->getQuote($futureSymbolWithExchange);
            
            if (!$futureQuote) {
                throw new Exception("No quote returned from API");
            }
            
            // Extract future price
            $futurePrice = $this->extractPrice($futureQuote);
            
            if (!$futurePrice) {
                throw new Exception("Could not extract price from quote");
            }
            
            $this->info("      Future Price: ₹" . number_format($futurePrice, 2));
            
        } catch (Exception $e) {
            throw new Exception("Future quote fetch failed: " . $e->getMessage());
        }
        
        // STEP 3: Calculate ATM strikes (ATM-1, ATM, ATM+1)
        $atmStrikes = $this->getATMStrikes($symbol, $futurePrice);
        $this->info("      ATM Strikes: " . implode(', ', $atmStrikes));
        
        // STEP 4: Get option instruments for these strikes
        $options = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->whereIn('strike', $atmStrikes)
            ->get();
        
        if ($options->isEmpty()) {
            throw new Exception("No options found for strikes");
        }
        
        $this->info("      Found " . $options->count() . " option instruments");
        
        // STEP 5: Calculate days to expiry
        $daysToExpiry = Carbon::parse($expiry)->diffInDays(Carbon::today());
        if ($daysToExpiry == 0) $daysToExpiry = 1; // Minimum 1 day to avoid division by zero
        
        $this->info("      Days to Expiry: {$daysToExpiry}");
        
        // STEP 6: Fetch live quotes for all options
        $tradingSymbolsWithExchange = $options->map(function($option) {
            return $option->exchange . ':' . $option->trading_symbol;
        })->toArray();
        
        $this->info("      Fetching quotes for " . count($tradingSymbolsWithExchange) . " options...");
        
        try {
            $quotes = $zerodhaHelper->getQuotes($tradingSymbolsWithExchange);
            
            if (empty($quotes)) {
                throw new Exception("No quotes returned for options");
            }
            
            $savedCount = 0;
            $ivCalculatedCount = 0;
            $ivFailedCount = 0;
            
            foreach ($options as $option) {
                $symbolWithExchange = $option->exchange . ':' . $option->trading_symbol;
                $quote = $quotes[$symbolWithExchange] ?? null;
                
                if (!$quote) {
                    $this->warn("      ⚠️ No quote for {$option->trading_symbol}");
                    continue;
                }
                
                // Determine ATM position
                $atmPosition = $this->determineATMPosition($option->strike, $atmStrikes);
                
                // Extract LTP
                $ltp = $this->extractPrice($quote);
                
                if (!$ltp || $ltp <= 0) {
                    $this->warn("      ⚠️ Invalid LTP for {$option->trading_symbol}");
                    continue;
                }
                
                // ✅ CALCULATE IMPLIED VOLATILITY using Black-Scholes
                $calculatedIV = null;
                
                try {
                    $calculatedIV = IVCalculator::calculate(
                        $ltp,                           // Market price
                        $futurePrice,                   // Spot price (using future as proxy)
                        $option->strike,                // Strike price
                        $daysToExpiry,                  // Days to expiry
                        $option->instrument_type,       // CE or PE
                        $this->riskFreeRate             // Risk-free rate
                    );
                    
                    if ($calculatedIV !== null) {
                        $ivCalculatedCount++;
                        $ivPercentage = round($calculatedIV * 100, 2);
                        $this->comment("      🧮 {$option->trading_symbol}: IV = {$ivPercentage}%");
                    } else {
                        $ivFailedCount++;
                        $this->warn("      ⚠️ IV calculation failed for {$option->trading_symbol}");
                    }
                    
                } catch (Exception $e) {
                    $ivFailedCount++;
                    $this->warn("      ⚠️ IV calc error for {$option->trading_symbol}: {$e->getMessage()}");
                    Log::warning("IV calculation error", [
                        'symbol' => $option->trading_symbol,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Extract Greeks from Zerodha (if available)
                $zerodhaIV = null;
                if (isset($quote->greeks) && isset($quote->greeks->iv)) {
                    $zerodhaIV = $quote->greeks->iv;
                }
                
                // Save to database
                OptionIVData::updateOrCreate([
                    'broker_api_id' => $broker->id,
                    'trading_symbol' => $option->trading_symbol,
                    'timestamp' => now(),
                ], [
                    'symbol' => $option->name,
                    'expiry' => $expiry,
                    'strike' => $option->strike,
                    'option_type' => $option->instrument_type,
                    'ltp' => $ltp,
                    'iv' => $calculatedIV,  // Our calculated IV
                    'zerodha_iv' => $zerodhaIV,  // Zerodha's IV (if available)
                    'oi' => $quote->oi ?? 0,
                    'volume' => $quote->volume ?? 0,
                    'bid' => isset($quote->depth->buy[0]) ? $quote->depth->buy[0]->price : null,
                    'ask' => isset($quote->depth->sell[0]) ? $quote->depth->sell[0]->price : null,
                    'delta' => isset($quote->greeks->delta) ? $quote->greeks->delta : null,
                    'gamma' => isset($quote->greeks->gamma) ? $quote->greeks->gamma : null,
                    'theta' => isset($quote->greeks->theta) ? $quote->greeks->theta : null,
                    'vega' => isset($quote->greeks->vega) ? $quote->greeks->vega : null,
                    'atm_position' => $atmPosition,
                    'future_price' => $futurePrice,
                    'days_to_expiry' => $daysToExpiry,
                    'risk_free_rate' => $this->riskFreeRate,
                ]);
                
                $savedCount++;
            }
            
            $this->info("      💾 Saved {$savedCount} option records");
            $this->info("      ✓ IV Calculated: {$ivCalculatedCount}");
            
            if ($ivFailedCount > 0) {
                $this->warn("      ⚠️ IV Calculation Failed: {$ivFailedCount}");
            }
            
            return [
                'saved' => $savedCount,
                'iv_count' => $ivCalculatedCount
            ];
            
        } catch (Exception $e) {
            throw new Exception("Option quotes fetch failed: " . $e->getMessage());
        }
    }
    
    /**
     * Extract price from quote with multiple fallbacks
     */
    private function extractPrice($quote): ?float
    {
        if (isset($quote->last_price)) {
            return $quote->last_price;
        } elseif (isset($quote->lastPrice)) {
            return $quote->lastPrice;
        } elseif (isset($quote->ltp)) {
            return $quote->ltp;
        } elseif (isset($quote->ohlc) && isset($quote->ohlc->close)) {
            return $quote->ohlc->close;
        }
        
        return null;
    }
    
    /**
     * Get nearest expiry for symbol
     */
    private function getNearestExpiry($symbol)
    {
        $expiry = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', '>=', now())
            ->orderBy('expiry', 'asc')
            ->value('expiry');
        
        return $expiry;
    }
    
    /**
     * Calculate ATM-1, ATM, ATM+1 strikes
     */
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
    
    /**
     * Determine if strike is ATM-1, ATM, or ATM+1
     */
    private function determineATMPosition($strike, $atmStrikes)
    {
        if ($strike == $atmStrikes[0]) return 'ATM-1';
        if ($strike == $atmStrikes[1]) return 'ATM';
        if ($strike == $atmStrikes[2]) return 'ATM+1';
        return 'OTHER';
    }
    
    /**
     * Check if weekend or holiday
     */
    private function isWeekendOrHoliday($date)
    {
        $carbon = Carbon::parse($date);
        
        if ($carbon->isWeekend()) {
            return true;
        }
        
        return \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}