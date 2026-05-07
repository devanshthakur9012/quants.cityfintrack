<?php
// app/Console/Commands/BackfillOptionIVDataCommand.php

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

class BackfillOptionIVDataCommand extends Command
{
    protected $signature = 'options:backfill-iv 
                            {--from= : From date (Y-m-d) [REQUIRED]}
                            {--to= : To date (Y-m-d) [REQUIRED]}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific symbol (NIFTY, BANKNIFTY, etc.)}
                            {--time=15:00 : Time of day to capture (HH:MM, default: 15:00 for market close)}
                            {--interval=5minute : Candle interval (5minute, 15minute, day)}
                            {--risk-free-rate=0.06 : Risk-free rate (default: 0.06 for 6%)}
                            {--skip-holidays : Skip weekends and holidays automatically}';

    protected $description = 'Backfill historical IV data using historical OHLC candles from Zerodha';

    private $symbols = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'];
    private $riskFreeRate = 0.06;

    public function handle()
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $time = $this->option('time');
        $interval = $this->option('interval');
        
        // Validation
        if (!$fromDate || !$toDate) {
            $this->error('❌ Both --from and --to dates are required!');
            $this->info('');
            $this->info('📖 Usage Examples:');
            $this->info('   php artisan options:backfill-iv --from=2026-01-01 --to=2026-01-31');
            $this->info('   php artisan options:backfill-iv --from=2026-01-15 --to=2026-01-20 --symbol=NIFTY');
            $this->info('   php artisan options:backfill-iv --from=2026-01-01 --to=2026-01-31 --time=09:30');
            $this->info('   php artisan options:backfill-iv --from=2026-01-01 --to=2026-01-31 --skip-holidays');
            $this->info('');
            return 1;
        }
        
        // Set risk-free rate
        if ($this->option('risk-free-rate')) {
            $this->riskFreeRate = floatval($this->option('risk-free-rate'));
        }
        
        // Display configuration
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🕒 HISTORICAL IV DATA BACKFILL");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📅 Date Range: {$fromDate} → {$toDate}");
        $this->info("⏰ Time: {$time}");
        $this->info("📊 Interval: {$interval}");
        $this->info("💹 Risk-Free Rate: " . ($this->riskFreeRate * 100) . "%");
        $this->info("🔍 Data Source: Historical OHLC Candles");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
        
        // Get active brokers
        $brokersQuery = BrokerApi::zerodha()->validToken();
        
        if ($this->option('broker')) {
            $brokersQuery->where('id', $this->option('broker'));
        }
        
        $brokers = $brokersQuery->get();
        
        if ($brokers->isEmpty()) {
            $this->error('❌ No active Zerodha brokers found with valid tokens!');
            $this->info('💡 Tip: Make sure you have at least one active broker with a valid access token.');
            return 1;
        }
        
        $broker = $brokers->first();
        $this->info("🔑 Using Broker: {$broker->client_name} (ID: {$broker->id})\n");
        
        try {
            $zerodhaHelper = new BrokerZerodhaHelperNew($broker);
            
            // Get symbols to process
            $symbolsToProcess = $this->option('symbol') 
                ? [strtoupper($this->option('symbol'))]
                : $this->symbols;
            
            $this->info("📊 Symbols to Process: " . implode(', ', $symbolsToProcess) . "\n");
            
            // Calculate date range
            $currentDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $totalDays = $currentDate->diffInDays($endDate) + 1;
            
            $this->info("📅 Total Days in Range: {$totalDays}\n");
            
            // Statistics
            $stats = [
                'processed_days' => 0,
                'skipped_days' => 0,
                'total_options' => 0,
                'total_iv_calculated' => 0,
                'failed_calculations' => 0,
            ];
            
            // Progress bar
            $bar = $this->output->createProgressBar($totalDays);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
            $bar->setMessage('Starting...');
            
            // Loop through each date
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                
                // Skip weekends if requested
                if ($this->option('skip-holidays') && $currentDate->isWeekend()) {
                    $bar->setMessage("⏭️  Skipped {$dateStr} (Weekend)");
                    $bar->advance();
                    $stats['skipped_days']++;
                    $currentDate->addDay();
                    continue;
                }
                
                // Check if holiday
                if ($this->option('skip-holidays') && $this->isHoliday($dateStr)) {
                    $bar->setMessage("⏭️  Skipped {$dateStr} (Holiday)");
                    $bar->advance();
                    $stats['skipped_days']++;
                    $currentDate->addDay();
                    continue;
                }
                
                $bar->setMessage("Processing {$dateStr}...");
                
                foreach ($symbolsToProcess as $symbol) {
                    try {
                        // Get expiry for this date
                        $expiry = $this->getNearestExpiry($symbol, $dateStr);
                        
                        if (!$expiry) {
                            Log::warning("No expiry found for {$symbol} on {$dateStr}");
                            continue;
                        }
                        
                        // Process this symbol for this date
                        $result = $this->backfillSymbolForDate(
                            $broker, 
                            $symbol, 
                            $expiry, 
                            $dateStr, 
                            $time,
                            $interval,
                            $zerodhaHelper
                        );
                        
                        $stats['total_options'] += $result['saved'];
                        $stats['total_iv_calculated'] += $result['iv_calculated'];
                        $stats['failed_calculations'] += $result['iv_failed'];
                        
                    } catch (Exception $e) {
                        Log::error("Backfill error: {$symbol} on {$dateStr}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
                
                $stats['processed_days']++;
                $bar->setMessage("✓ {$dateStr} completed");
                $bar->advance();
                $currentDate->addDay();
                
                // Rate limiting: sleep 1 second between days to avoid API throttling
                if ($currentDate <= $endDate) {
                    sleep(1);
                }
            }
            
            $bar->finish();
            $this->info("\n");
            
            // Final summary
            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ BACKFILL COMPLETED!");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📊 Statistics:");
            $this->info("   Days Processed: {$stats['processed_days']}");
            $this->info("   Days Skipped: {$stats['skipped_days']}");
            $this->info("   Total Options Saved: {$stats['total_options']}");
            $this->info("   IVs Calculated: {$stats['total_iv_calculated']}");
            
            if ($stats['failed_calculations'] > 0) {
                $this->warn("   IV Calculation Failures: {$stats['failed_calculations']}");
            }
            
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
            
            return 0;
            
        } catch (Exception $e) {
            $this->error("\n❌ Critical Error: " . $e->getMessage());
            Log::error('Backfill Critical Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Backfill IV data for a specific symbol on a specific date
     */
    private function backfillSymbolForDate(
        BrokerApi $broker,
        string $symbol,
        string $expiry,
        string $date,
        string $time,
        string $interval,
        BrokerZerodhaHelperNew $zerodhaHelper
    ) {
        $stats = [
            'saved' => 0,
            'iv_calculated' => 0,
            'iv_failed' => 0,
        ];
        
        // STEP 1: Get future instrument
        $futureInstrument = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'FUT')
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry', 'asc')
            ->first();
        
        if (!$futureInstrument) {
            throw new Exception("No future instrument found for {$symbol}");
        }
        
        // STEP 2: Get historical candle for future (to get spot price)
        $fromDateTime = $date . ' ' . $time;
        $toDateTime = Carbon::parse($fromDateTime)->addMinutes(5)->format('Y-m-d H:i:s');
        
        $futureCandles = $zerodhaHelper->getHistoricalDataByToken(
            $futureInstrument->instrument_token,
            $interval,
            $fromDateTime,
            $toDateTime
        );
        
        if (empty($futureCandles)) {
            throw new Exception("No historical data for future {$futureInstrument->trading_symbol}");
        }
        
        // Get future price from candle
        $futurePrice = $futureCandles[0]['close'];
        
        if (!$futurePrice || $futurePrice <= 0) {
            throw new Exception("Invalid future price: {$futurePrice}");
        }
        
        // STEP 3: Calculate ATM strikes
        $atmStrikes = $this->getATMStrikes($symbol, $futurePrice);
        
        // STEP 4: Get option instruments
        $options = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->whereIn('strike', $atmStrikes)
            ->get();
        
        if ($options->isEmpty()) {
            throw new Exception("No options found for {$symbol} with strikes " . implode(', ', $atmStrikes));
        }
        
        // STEP 5: Calculate days to expiry
        $daysToExpiry = Carbon::parse($expiry)->diffInDays(Carbon::parse($date));
        if ($daysToExpiry == 0) $daysToExpiry = 1; // Minimum 1 day
        
        // STEP 6: Process each option
        foreach ($options as $option) {
            try {
                // Get historical candle for option
                $optionCandles = $zerodhaHelper->getHistoricalDataByToken(
                    $option->instrument_token,
                    $interval,
                    $fromDateTime,
                    $toDateTime
                );
                
                if (empty($optionCandles)) {
                    Log::warning("No historical data for {$option->trading_symbol} on {$date}");
                    continue;
                }
                
                $candle = $optionCandles[0];
                $ltp = $candle['close'];
                
                if (!$ltp || $ltp <= 0) {
                    Log::warning("Invalid LTP for {$option->trading_symbol}: {$ltp}");
                    continue;
                }
                
                // Calculate IV using Black-Scholes
                $calculatedIV = null;
                
                try {
                    $calculatedIV = IVCalculator::calculate(
                        $ltp,
                        $futurePrice,
                        $option->strike,
                        $daysToExpiry,
                        $option->instrument_type,
                        $this->riskFreeRate
                    );
                    
                    if ($calculatedIV !== null) {
                        $stats['iv_calculated']++;
                    } else {
                        $stats['iv_failed']++;
                    }
                    
                } catch (Exception $e) {
                    $stats['iv_failed']++;
                    Log::warning("IV calculation failed for {$option->trading_symbol}", [
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Determine ATM position
                $atmPosition = $this->determineATMPosition($option->strike, $atmStrikes);
                
                // Save to database
                OptionIVData::updateOrCreate([
                    'broker_api_id' => $broker->id,
                    'trading_symbol' => $option->trading_symbol,
                    'timestamp' => Carbon::parse($fromDateTime),
                ], [
                    'symbol' => $option->name,
                    'expiry' => $expiry,
                    'strike' => $option->strike,
                    'option_type' => $option->instrument_type,
                    'ltp' => $ltp,
                    'iv' => $calculatedIV,
                    'oi' => $candle['oi'] ?? 0,
                    'volume' => $candle['volume'] ?? 0,
                    'atm_position' => $atmPosition,
                    'future_price' => $futurePrice,
                    'days_to_expiry' => $daysToExpiry,
                    'risk_free_rate' => $this->riskFreeRate,
                ]);
                
                $stats['saved']++;
                
            } catch (Exception $e) {
                Log::error("Error processing option {$option->trading_symbol}", [
                    'date' => $date,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $stats;
    }
    
    /**
     * Get nearest expiry for a given date
     */
    private function getNearestExpiry($symbol, $date)
    {
        $expiry = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry', 'asc')
            ->value('expiry');
        
        return $expiry;
    }
    
    /**
     * Calculate ATM strikes (ATM-1, ATM, ATM+1)
     */
    private function getATMStrikes($symbol, $futurePrice)
    {
        $intervals = [
            'NIFTY' => 50,
            'BANKNIFTY' => 100,
            'FINNIFTY' => 50,
            'MIDCPNIFTY' => 25,
        ];
        
        $interval = $intervals[$symbol] ?? 50;
        $atmStrike = round($futurePrice / $interval) * $interval;
        
        return [
            $atmStrike - $interval,  // ATM-1
            $atmStrike,               // ATM
            $atmStrike + $interval,   // ATM+1
        ];
    }
    
    /**
     * Determine ATM position
     */
    private function determineATMPosition($strike, $atmStrikes)
    {
        if ($strike == $atmStrikes[0]) return 'ATM-1';
        if ($strike == $atmStrikes[1]) return 'ATM';
        if ($strike == $atmStrikes[2]) return 'ATM+1';
        return 'OTHER';
    }
    
    /**
     * Check if date is a market holiday
     */
    private function isHoliday($date)
    {
        return \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}