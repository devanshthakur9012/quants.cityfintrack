<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Models\OptionStrikeIntraday;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use App\Services\OiAnalysisServiceNew;
use App\Services\IVAnalysisServiceNew;
use App\Helpers\IVCalculator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchOptionOIIntradayHistoricalCommand extends Command
{
    protected $signature = 'options:fetch-oi-intraday-historical 
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--skip-existing : Skip dates that already have data}
                            {--debug : Show detailed debug information}';

    protected $description = 'Backfill historical intraday OI + IV data (Prev day 12:15 to Current day 12:15) for 15minute interval';

    private $riskFreeRate = 0.06;
    private $apiCallDelay = 350000;
    private $maxRetries = 3;

    public function handle()
    {
        // Validate date inputs
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        if (!$fromDate || !$toDate) {
            $this->error('❌ Both --from and --to dates are required!');
            $this->info('Usage: php artisan options:fetch-oi-intraday-historical --from=2026-01-01 --to=2026-01-31');
            return 1;
        }

        try {
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
        } catch (Exception $e) {
            $this->error('❌ Invalid date format! Use Y-m-d format (e.g., 2026-01-15)');
            return 1;
        }

        if ($endDate->lt($startDate)) {
            $this->error('❌ End date must be after start date!');
            return 1;
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;

        try {
            $this->info("🚀 Starting Historical Intraday OI + IV Backfill");
            $this->info("   From: {$startDate->format('Y-m-d')}");
            $this->info("   To: {$endDate->format('Y-m-d')}");
            $this->info("   Total Days: {$totalDays}");
            $this->info("   Timeframe: Prev Day 12:15 to Current Day 12:15");
            $this->info("   Signals: OI + IV + BTST");
            $this->newLine();

            $brokersQuery = BrokerApi::zerodha()->validToken();

            if ($this->option('broker')) {
                $brokersQuery->where('id', $this->option('broker'));
            }

            $brokers = $brokersQuery->get();

            if ($brokers->isEmpty()) {
                $this->error('❌ No active Zerodha brokers with valid tokens found!');
                return 1;
            }

            $this->info("📋 Found " . $brokers->count() . " broker(s) with valid tokens\n");

            // Generate list of trading days
            $tradingDays = $this->getTradingDaysBetween($startDate, $endDate);

            if (empty($tradingDays)) {
                $this->warn('⚠️ No trading days found in the specified date range!');
                return 0;
            }

            $this->info("📅 Found " . count($tradingDays) . " trading days to process");
            $this->newLine();

            $totalProcessed = 0;
            $totalFailed = 0;
            $totalSkipped = 0;

            $progressBar = $this->output->createProgressBar(count($tradingDays) * $brokers->count());
            $progressBar->start();

            foreach ($brokers as $broker) {
                $this->newLine(2);
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");
                $this->newLine();

                foreach ($tradingDays as $date) {
                    // Check if data already exists and skip if requested
                    if ($this->option('skip-existing') && $this->hasExistingData($broker->id, $date)) {
                        $totalSkipped++;
                        $progressBar->advance();
                        continue;
                    }

                    $result = $this->processDateIntraday($broker, $date);
                    $totalProcessed += $result['success'];
                    $totalFailed += $result['failed'];
                    
                    $progressBar->advance();
                    
                    // Small delay between dates to avoid rate limiting
                    usleep($this->apiCallDelay);
                }
            }

            $progressBar->finish();

            $this->newLine(2);
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Historical Intraday OI + IV Backfill Completed!");
            $this->info("   Total Processed: {$totalProcessed} symbols");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("   Total Skipped: {$totalSkipped}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Historical Intraday OI+IV Backfill Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get list of trading days between two dates
     */
    private function getTradingDaysBetween(Carbon $startDate, Carbon $endDate): array
    {
        $tradingDays = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->format('Y-m-d');

            // Special trading Sunday
            if ($dateString === '2026-02-01') {
                $tradingDays[] = $dateString;
                $currentDate->addDay();
                continue;
            }

            // Skip weekends
            if ($currentDate->isWeekend()) {
                $currentDate->addDay();
                continue;
            }

            // Skip holidays
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $dateString)
                ->exists();

            if (!$isHoliday) {
                $tradingDays[] = $dateString;
            }

            $currentDate->addDay();
        }

        return $tradingDays;
    }

    /**
     * Check if data already exists for a specific date
     */
    private function hasExistingData(int $brokerId, string $date): bool
    {
        return OptionStrikeIntraday::where('broker_api_id', $brokerId)
            ->where('trading_date', $date)
            ->exists();
    }

    /**
     * Process single date for a broker
     */
    private function processDateIntraday(BrokerApi $broker, string $date)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);

            if ($this->option('debug')) {
                $this->newLine();
                $this->info("   📅 Processing Date: {$date}");
            }

            $result = $this->processIntradayOIAndIV($broker, $zerodhaHelper, $date);
            
            $success = $result['success'];
            $failed = $result['failed'];

            if ($this->option('debug')) {
                $this->info("      ✓ {$success} symbols | ✗ {$failed} failed");
            }

        } catch (Exception $e) {
            if ($this->option('debug')) {
                $this->error("   Failed: " . $e->getMessage());
            }
            Log::error("Historical backfill failed for date {$date}: " . $e->getMessage());
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function processIntradayOIAndIV(BrokerApi $broker, BrokerZerodhaHelper $zerodhaHelper, string $date)
    {
        $success = 0;
        $failed = 0;

        // Get symbols with 15minute interval
        $futureSymbolsQuery = DB::table('symbols_monitored')
            ->where('broker_api_id', $broker->id)
            ->where('is_active', true)
            ->where('interval', '15minute')
            ->where('trading_symbol', 'LIKE', '%FUT');

        if ($this->option('symbol')) {
            $futureSymbolsQuery->where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
        }

        $futureSymbols = $futureSymbolsQuery->get();

        $validSymbols = collect();
        foreach ($futureSymbols as $futSymbol) {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
            
            $hasOptions = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->exists();
            
            if ($hasOptions) {
                $validSymbols->push($futSymbol);
            }
        }

        if ($validSymbols->isEmpty()) {
            return ['success' => 0, 'failed' => 0];
        }

        foreach ($validSymbols as $futSymbol) {
            try {
                $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
                
                $futInstrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)
                    ->where('exchange', 'NFO')
                    ->first();
                
                if (!$futInstrument) {
                    $failed++;
                    continue;
                }
                
                // Get spot price at 12:15 for this date
                $spotPrice = $this->getIntradayPrice($zerodhaHelper, $futInstrument->instrument_token, $date);
                
                if (!$spotPrice) {
                    $failed++;
                    continue;
                }

                $strikeIntervals = [
                    'NIFTY'        => 100,
                    'BANKNIFTY'    => 100,
                    'FINNIFTY'     => 50,
                    'MIDCPNIFTY'   => 25,
                ];
                
                $strikeInterval = $strikeIntervals[$baseSymbol] ?? 100;
                $atmStrike = round($spotPrice / $strikeInterval) * $strikeInterval;

                // Fetch FUT, CE, PE historical data for this date
                $futAnalysis = $this->fetchFutureIntradayOIAndIV($broker, $futSymbol, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                $ceAnalysis = $this->fetchAndMergeCEIntradayOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                $peAnalysis = $this->fetchAndMergePEIntradayOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);

                if ($futAnalysis && $ceAnalysis && $peAnalysis) {
                    $this->calculateAndStoreBTSTSignal($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    $success++;
                } else {
                    $failed++;
                }

                // Delay between symbols
                usleep($this->apiCallDelay);

            } catch (Exception $e) {
                Log::error("Failed to process historical symbol: {$baseSymbol} for date {$date}", [
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function calculateAndStoreBTSTSignal(
        BrokerApi $broker,
        string $baseSymbol,
        string $date,
        array $futAnalysis,
        array $ceAnalysis,
        array $peAnalysis
    ): void {
        $btst = IVAnalysisServiceNew::getBTSTSignal(
            $futAnalysis['oi'],
            $ceAnalysis['oi'],
            $ceAnalysis['iv'],
            $peAnalysis['oi'],
            $peAnalysis['iv']
        );

        OptionStrikeIntraday::where('broker_api_id', $broker->id)
            ->where('underlying_symbol', $baseSymbol)
            ->where('strike_position', 'FUT')
            ->where('trading_date', $date)
            ->update([
                'btst_signal' => $btst['btst_signal'],
                'btst_confidence' => $btst['confidence'],
                'btst_reason' => $btst['reason']
            ]);
    }

    private function fetchFutureIntradayOIAndIV(
        BrokerApi $broker,
        $futSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        string $baseSymbol,
        float $spotPrice,
        int $atmStrike
    ): ?array {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)
                ->where('exchange', 'NFO')
                ->first();

            if (!$instrument) {
                return null;
            }

            $previousTradingDay = $this->getPreviousTradingDay($date);
            $prevDayOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $previousTradingDay, '12:15:00');
            $currentOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $date, '12:15:00');
            
            if ($currentOI === null) {
                return null;
            }

            $prevDayOI = $prevDayOI ?? 0;
            
            $oiAnalysis = OiAnalysisServiceNew::analyzeFuturesOI($currentOI, $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseFutSignal($oiAnalysis['direction']);

            OptionStrikeIntraday::updateOrCreate(
                [
                    'broker_api_id' => $broker->id,
                    'underlying_symbol' => $baseSymbol,
                    'trading_symbol' => $futSymbol->trading_symbol,
                    'strike_position' => 'FUT',
                    'trading_date' => $date
                ],
                [
                    'option_type' => 'FUT',
                    'strike_price' => $spotPrice,
                    'expiry' => preg_replace('/.*(\d{2}[A-Z]{3})FUT$/i', '$1', $futSymbol->trading_symbol),
                    'expiry_date' => $this->getNextExpiry($baseSymbol, $date),
                    'instrument_token' => $instrument->instrument_token,
                    'exchange' => 'NFO',
                    'lot_size' => $instrument->lot_size ?? 1,
                    'is_active' => true,
                    'intraday_oi' => $oiAnalysis['daily_oi'],
                    'intraday_oi_prev' => $oiAnalysis['daily_oi_prev'],
                    'intraday_oi_change' => $oiAnalysis['daily_oi_change'],
                    'intraday_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                    'direction' => $reversedDirection,
                    'strength' => $oiAnalysis['strength'],
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return ['oi' => $oiAnalysis];

        } catch (Exception $e) {
            Log::error("FUT historical OI fetch failed: {$baseSymbol} on {$date}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function fetchAndMergeCEIntradayOIAndIV(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): ?array {
        try {
            $strikes = [
                $atmStrike - (2 * $strikeInterval),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                $atmStrike + (2 * $strikeInterval)
            ];

            $currentDayTotalOI = 0;
            $prevDayTotalOI = 0;
            $totalIV = 0;
            $ivCount = 0;
            $ceInstruments = [];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return null;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
            if ($daysToExpiry == 0) $daysToExpiry = 1;

            $previousTradingDay = $this->getPreviousTradingDay($date);

            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'CE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                $currentData = $this->fetchOIAndPriceAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $date, '12:15:00');
                
                if (!$currentData || $currentData['oi'] === null) {
                    continue;
                }

                $currentOI = $currentData['oi'];
                $close = $currentData['close'];

                if ($close <= 0) {
                    continue;
                }

                $currentDayTotalOI += $currentOI;
                $ceInstruments[] = $tradingSymbol;

                $prevDayOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $previousTradingDay, '12:15:00');
                $prevDayTotalOI += ($prevDayOI ?? 0);

                if ($close > 0) {
                    $iv = IVCalculator::calculate(
                        $close,
                        $spotPrice,
                        $strike,
                        $daysToExpiry,
                        'CE',
                        $this->riskFreeRate
                    );

                    if ($iv !== null) {
                        $totalIV += $iv;
                        $ivCount++;
                    }
                }

                usleep($this->apiCallDelay / 2); // Smaller delay within strikes
            }

            if ($currentDayTotalOI == 0) {
                return null;
            }

            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $prevDayIV = $this->getPreviousIntraday1215IV($broker, $baseSymbol, 'CE_MERGED', $date);
            
            $oiAnalysis = OiAnalysisServiceNew::analyzeCallOptionsOI($currentDayTotalOI, $prevDayTotalOI, $baseSymbol);
            $reversedOiDirection = $this->reverseCESignal($oiAnalysis['direction']);

            $ivAnalysis = IVAnalysisServiceNew::analyzeCallOptionsIV($avgIV, $prevDayIV, $baseSymbol);

            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
            }

            OptionStrikeIntraday::updateOrCreate(
                [
                    'broker_api_id' => $broker->id,
                    'underlying_symbol' => $baseSymbol,
                    'strike_position' => 'CE_MERGED',
                    'trading_date' => $date
                ],
                [
                    'trading_symbol' => implode(',', $ceInstruments),
                    'option_type' => 'CE',
                    'strike_price' => $atmStrike,
                    'expiry' => $expiryCode,
                    'expiry_date' => $expiryDate,
                    'instrument_token' => null,
                    'exchange' => 'NFO',
                    'lot_size' => 1,
                    'is_active' => true,
                    'intraday_oi' => $oiAnalysis['daily_oi'],
                    'intraday_oi_prev' => $oiAnalysis['daily_oi_prev'],
                    'intraday_oi_change' => $oiAnalysis['daily_oi_change'],
                    'intraday_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                    'direction' => $reversedOiDirection,
                    'strength' => $oiAnalysis['strength'],
                    'intraday_iv' => $avgIV,
                    'intraday_iv_prev' => $prevDayIV,
                    'intraday_iv_change' => $ivChange,
                    'intraday_iv_change_pct' => $ivChangePct,
                    'iv_direction' => $ivAnalysis['iv_direction'],
                    'iv_strength' => $ivAnalysis['iv_strength'],
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return [
                'oi' => $oiAnalysis,
                'iv' => $ivAnalysis
            ];

        } catch (Exception $e) {
            Log::error("CE historical merge failed: {$baseSymbol} on {$date}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function fetchAndMergePEIntradayOIAndIV(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): ?array {
        try {
            $strikes = [
                $atmStrike - (2 * $strikeInterval),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                $atmStrike + (2 * $strikeInterval)
            ];

            $currentDayTotalOI = 0;
            $prevDayTotalOI = 0;
            $totalIV = 0;
            $ivCount = 0;
            $peInstruments = [];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return null;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
            if ($daysToExpiry == 0) $daysToExpiry = 1;

            $previousTradingDay = $this->getPreviousTradingDay($date);

            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'PE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                $currentData = $this->fetchOIAndPriceAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $date, '12:15:00');
                
                if (!$currentData || $currentData['oi'] === null) {
                    continue;
                }

                $currentOI = $currentData['oi'];
                $close = $currentData['close'];

                if ($close <= 0) {
                    continue;
                }

                $currentDayTotalOI += $currentOI;
                $peInstruments[] = $tradingSymbol;

                $prevDayOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $previousTradingDay, '12:15:00');
                $prevDayTotalOI += ($prevDayOI ?? 0);

                if ($close > 0) {
                    $iv = IVCalculator::calculate(
                        $close,
                        $spotPrice,
                        $strike,
                        $daysToExpiry,
                        'PE',
                        $this->riskFreeRate
                    );

                    if ($iv !== null) {
                        $totalIV += $iv;
                        $ivCount++;
                    }
                }

                usleep($this->apiCallDelay / 2);
            }

            if ($currentDayTotalOI == 0) {
                return null;
            }

            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $prevDayIV = $this->getPreviousIntraday1215IV($broker, $baseSymbol, 'PE_MERGED', $date);
            
            $oiAnalysis = OiAnalysisServiceNew::analyzePutOptionsOI($currentDayTotalOI, $prevDayTotalOI, $baseSymbol);
            $reversedOiDirection = $this->reversePESignal($oiAnalysis['direction']);

            $ivAnalysis = IVAnalysisServiceNew::analyzePutOptionsIV($avgIV, $prevDayIV, $baseSymbol);

            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
            }

            OptionStrikeIntraday::updateOrCreate(
                [
                    'broker_api_id' => $broker->id,
                    'underlying_symbol' => $baseSymbol,
                    'strike_position' => 'PE_MERGED',
                    'trading_date' => $date
                ],
                [
                    'trading_symbol' => implode(',', $peInstruments),
                    'option_type' => 'PE',
                    'strike_price' => $atmStrike,
                    'expiry' => $expiryCode,
                    'expiry_date' => $expiryDate,
                    'instrument_token' => null,
                    'exchange' => 'NFO',
                    'lot_size' => 1,
                    'is_active' => true,
                    'intraday_oi' => $oiAnalysis['daily_oi'],
                    'intraday_oi_prev' => $oiAnalysis['daily_oi_prev'],
                    'intraday_oi_change' => $oiAnalysis['daily_oi_change'],
                    'intraday_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                    'direction' => $reversedOiDirection,
                    'strength' => $oiAnalysis['strength'],
                    'intraday_iv' => $avgIV,
                    'intraday_iv_prev' => $prevDayIV,
                    'intraday_iv_change' => $ivChange,
                    'intraday_iv_change_pct' => $ivChangePct,
                    'iv_direction' => $ivAnalysis['iv_direction'],
                    'iv_strength' => $ivAnalysis['iv_strength'],
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return [
                'oi' => $oiAnalysis,
                'iv' => $ivAnalysis
            ];

        } catch (Exception $e) {
            Log::error("PE historical merge failed: {$baseSymbol} on {$date}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function fetchOIAtSpecificTime($zerodhaHelper, int $instrumentToken, string $date, string $time): ?int
    {
        try {
            $startDateTime = $date . ' ' . $time;
            $endDateTime = Carbon::parse($startDateTime)->addMinutes(1)->format('Y-m-d H:i:s');
            
            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',
                $startDateTime,
                $endDateTime
            );

            if (empty($data)) {
                return null;
            }

            $candle = $data[0];
            return ($candle->oi ?? null) !== 'null' ? (int)($candle->oi ?? 0) : 0;

        } catch (Exception $e) {
            Log::error("Historical OI fetch failed for token {$instrumentToken} at {$date} {$time}: " . $e->getMessage());
            return null;
        }
    }

    private function fetchOIAndPriceAtSpecificTime($zerodhaHelper, int $instrumentToken, string $date, string $time): ?array
    {
        try {
            $startDateTime = $date . ' ' . $time;
            $endDateTime = Carbon::parse($startDateTime)->addMinutes(1)->format('Y-m-d H:i:s');
            
            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',
                $startDateTime,
                $endDateTime
            );

            if (empty($data)) {
                return null;
            }

            $candle = $data[0];
            
            return [
                'oi' => ($candle->oi ?? null) !== 'null' ? (int)($candle->oi ?? 0) : 0,
                'close' => ($candle->close ?? null) !== 'null' ? (float)($candle->close ?? 0) : 0
            ];

        } catch (Exception $e) {
            Log::error("Historical OI+Price fetch failed for token {$instrumentToken} at {$date} {$time}: " . $e->getMessage());
            return null;
        }
    }

    private function getIntradayPrice($zerodhaHelper, int $instrumentToken, string $date): ?float
    {
        try {
            $startDateTime = $date . ' 12:15:00';
            $endDateTime = Carbon::parse($startDateTime)->addMinutes(1)->format('Y-m-d H:i:s');
            
            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',
                $startDateTime,
                $endDateTime
            );

            if (empty($data)) {
                return null;
            }

            $candle = $data[0];
            return ($candle->close ?? null) !== 'null' ? (float)($candle->close ?? 0) : null;

        } catch (Exception $e) {
            Log::error("Historical price fetch failed: " . $e->getMessage());
            return null;
        }
    }

    private function getPreviousTradingDay(string $currentDate): string
    {
        $prevDate = Carbon::parse($currentDate)->subDay();
        $maxLookback = 10;
        $attempts = 0;
        
        while ($attempts < $maxLookback) {
            if ($prevDate->format('Y-m-d') === '2026-02-01') {
                return $prevDate->format('Y-m-d');
            }

            if ($prevDate->isWeekend()) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }

            if ($this->isHoliday($prevDate->format('Y-m-d'))) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }

            return $prevDate->format('Y-m-d');
        }

        return Carbon::parse($currentDate)->subDay()->format('Y-m-d');
    }

    private function getPreviousIntraday1215IV(BrokerApi $broker, string $baseSymbol, string $position, string $currentDate): float
    {
        $prevDate = Carbon::parse($currentDate)->subDay();
        $maxLookback = 10;
        $attempts = 0;
        
        while ($attempts < $maxLookback) {
            if (
                ($prevDate->isWeekend() && $prevDate->format('Y-m-d') !== '2026-02-01')
                || $this->isHoliday($prevDate->format('Y-m-d'))
            ) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }

            $prevRecord = OptionStrikeIntraday::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $baseSymbol)
                ->where('strike_position', $position)
                ->where('trading_date', $prevDate->format('Y-m-d'))
                ->first();

            if ($prevRecord && $prevRecord->intraday_iv > 0) {
                return $prevRecord->intraday_iv;
            }

            $prevDate->subDay();
            $attempts++;
        }

        return 0.0;
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    private function reverseFutSignal($signal)
    {
        if (!$signal || $signal === 'N/A' || $signal === 'NEUTRAL') {
            return 'NEUTRAL';
        }
        if ($signal === 'BUILDUP') {
            return 'BULLISH';
        } elseif ($signal === 'UNWINDING') {
            return 'BEARISH';
        }
        return 'NEUTRAL';
    }

    private function reverseCESignal($signal)
    {
        if (!$signal || $signal === 'N/A' || $signal === 'NEUTRAL') {
            return 'NEUTRAL';
        }
        if ($signal === 'BULLISH') {
            return 'BEARISH';
        } elseif ($signal === 'BEARISH') {
            return 'BULLISH';
        }
        return 'NEUTRAL';
    }

    private function reversePESignal($signal)
    {
        if (!$signal || $signal === 'N/A' || $signal === 'NEUTRAL') {
            return 'NEUTRAL';
        }
        if ($signal === 'BULLISH') {
            return 'BEARISH';
        } elseif ($signal === 'BEARISH') {
            return 'BULLISH';
        }
        return 'NEUTRAL';
    }

    private function getNextExpiry(string $baseSymbol, string $date): ?Carbon
    {
        $expiry = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry', 'ASC')
            ->first();

        return $expiry ? Carbon::parse($expiry->expiry) : null;
    }
}