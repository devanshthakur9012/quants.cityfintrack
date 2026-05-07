<?php
// app/Console/Commands/FetchOldIVDataCommand.php

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

class FetchOldIVDataCommand extends Command
{
    protected $signature = 'options:fetch-old 
                            {--from= : From date (Y-m-d) [REQUIRED]}
                            {--to= : To date (Y-m-d) [REQUIRED]}
                            {--symbol= : Specific symbol (NIFTY, BANKNIFTY)}
                            {--time=15:00 : Time of day (HH:MM)}
                            {--skip-holidays : Skip weekends and holidays}';

    protected $description = 'Fetch OLD/PAST IV data for historical dates';

    private $symbols = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'];
    private $riskFreeRate = 0.06;

    public function handle()
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');
        $time = $this->option('time');
        
        // Validate
        if (!$fromDate || !$toDate) {
            $this->error('❌ Both --from and --to dates are required!');
            $this->info('Example: php artisan options:fetch-old --from=2026-01-27 --to=2026-01-31');
            return 1;
        }
        
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 FETCH OLD/HISTORICAL IV DATA");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📅 From: {$fromDate}");
        $this->info("📅 To: {$toDate}");
        $this->info("⏰ Time: {$time}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
        
        // Get broker
        $broker = BrokerApi::zerodha()->validToken()->first();
        
        if (!$broker) {
            $this->error('❌ No active broker found!');
            return 1;
        }
        
        $this->info("🔑 Broker: {$broker->client_name}\n");
        
        try {
            $zerodhaHelper = new BrokerZerodhaHelperNew($broker);
            
            // Get symbols
            $symbolsToProcess = $this->option('symbol') 
                ? [strtoupper($this->option('symbol'))]
                : $this->symbols;
            
            // Date loop
            $currentDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            $totalDays = $currentDate->diffInDays($endDate) + 1;
            
            $this->info("📅 Total Days: {$totalDays}\n");
            
            $stats = [
                'processed' => 0,
                'skipped' => 0,
                'total_saved' => 0,
                'total_iv' => 0,
            ];
            
            // Progress bar
            $bar = $this->output->createProgressBar($totalDays);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
            
            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $bar->setMessage("Processing {$dateStr}");
                
                // Skip weekends/holidays
                if ($this->option('skip-holidays')) {
                    if ($currentDate->isWeekend() || $this->isHoliday($dateStr)) {
                        $stats['skipped']++;
                        $bar->advance();
                        $currentDate->addDay();
                        continue;
                    }
                }
                
                // Process this date
                foreach ($symbolsToProcess as $symbol) {
                    try {
                        $result = $this->fetchForDate($broker, $symbol, $dateStr, $time, $zerodhaHelper);
                        $stats['total_saved'] += $result['saved'];
                        $stats['total_iv'] += $result['iv_count'];
                    } catch (Exception $e) {
                        Log::error("Error on {$symbol} - {$dateStr}: " . $e->getMessage());
                    }
                }
                
                $stats['processed']++;
                $bar->advance();
                $currentDate->addDay();
                sleep(1); // Rate limiting
            }
            
            $bar->finish();
            $this->info("\n");
            
            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ COMPLETED!");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("   Days Processed: {$stats['processed']}");
            $this->info("   Days Skipped: {$stats['skipped']}");
            $this->info("   Total Saved: {$stats['total_saved']}");
            $this->info("   IV Calculated: {$stats['total_iv']}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
            
            return 0;
            
        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Fetch Old IV Error: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Fetch IV data for a specific date
     */
    private function fetchForDate(
        BrokerApi $broker,
        string $symbol,
        string $date,
        string $time,
        BrokerZerodhaHelperNew $zerodhaHelper
    ) {
        // Get future instrument
        $futureInstrument = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'FUT')
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry', 'asc')
            ->first();
        
        if (!$futureInstrument) {
            throw new Exception("No future for {$symbol}");
        }
        
        // Fetch historical data for future
        $fromDateTime = $date . ' ' . $time;
        $toDateTime = Carbon::parse($fromDateTime)->addMinutes(5)->format('Y-m-d H:i:s');
        
        $futureCandles = $zerodhaHelper->getHistoricalDataByToken(
            $futureInstrument->instrument_token,
            '5minute',
            $fromDateTime,
            $toDateTime
        );
        
        if (empty($futureCandles)) {
            throw new Exception("No historical data for future");
        }
        
        $futurePrice = $futureCandles[0]['close'];
        
        // Get expiry
        $expiry = $this->getNearestExpiry($symbol, $date);
        
        if (!$expiry) {
            throw new Exception("No expiry found");
        }
        
        // Calculate ATM strikes
        $atmStrikes = $this->getATMStrikes($symbol, $futurePrice);
        
        // Get options
        $options = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->whereIn('strike', $atmStrikes)
            ->get();
        
        if ($options->isEmpty()) {
            throw new Exception("No options found");
        }
        
        // Calculate days to expiry
        $daysToExpiry = Carbon::parse($expiry)->diffInDays(Carbon::parse($date));
        if ($daysToExpiry == 0) $daysToExpiry = 1;
        
        $savedCount = 0;
        $ivCalculatedCount = 0;
        
        // Process each option
        foreach ($options as $option) {
            try {
                // Fetch historical data for option
                $optionCandles = $zerodhaHelper->getHistoricalDataByToken(
                    $option->instrument_token,
                    '5minute',
                    $fromDateTime,
                    $toDateTime
                );
                
                if (empty($optionCandles)) {
                    continue;
                }
                
                $candle = $optionCandles[0];
                $ltp = $candle['close'];
                
                if (!$ltp || $ltp <= 0) {
                    continue;
                }
                
                // Calculate IV
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
                        $ivCalculatedCount++;
                    }
                    
                } catch (Exception $e) {
                    Log::warning("IV calc failed: {$option->trading_symbol}");
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
                
                $savedCount++;
                
            } catch (Exception $e) {
                Log::error("Option error: {$option->trading_symbol}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [
            'saved' => $savedCount,
            'iv_count' => $ivCalculatedCount
        ];
    }
    
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
            $atmStrike - $interval,
            $atmStrike,
            $atmStrike + $interval,
        ];
    }
    
    private function determineATMPosition($strike, $atmStrikes)
    {
        if ($strike == $atmStrikes[0]) return 'ATM-1';
        if ($strike == $atmStrikes[1]) return 'ATM';
        if ($strike == $atmStrikes[2]) return 'ATM+1';
        return 'OTHER';
    }
    
    private function isHoliday($date)
    {
        return \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}