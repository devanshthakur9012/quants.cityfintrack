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

class FetchOptionOIIntradayCommand extends Command
{
    protected $signature = 'options:fetch-oi-intraday 
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force fetch even on holidays}
                            {--debug : Show detailed debug information}';

    protected $description = 'Fetch intraday OI + IV data (Prev day 12:15 to Current day 12:15) with signals for 15minute interval';

    private $riskFreeRate = 0.06;
    private $apiCallDelay = 350000;
    private $maxRetries = 3;

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        if (!$this->option('force')) {
            $isSpecialTradingSunday = ($today === '2026-02-01');
            
            if (!$isSpecialTradingSunday && ($dayName == "Saturday" || $dayName == "Sunday")) {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("Option OI+IV intraday fetch skipped: Weekend");
                return 0;
            }

            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Option OI+IV intraday fetch skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->info("🚀 Starting Intraday OI + IV Fetch (12:15 timeframe)");
            $this->info("   Date: {$today}");
            $this->info("   Time: " . Carbon::now()->format('H:i:s'));
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

            $totalProcessed = 0;
            $totalFailed = 0;

            foreach ($brokers as $broker) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

                $result = $this->processBrokerIntraday($broker, $today);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Intraday OI + IV Fetch Completed!");
            $this->info("   Total Processed: {$totalProcessed} symbols");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Intraday OI+IV Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBrokerIntraday(BrokerApi $broker, string $date)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            $this->info("   📅 Processing Intraday OI + IV for: {$date}");
            $this->newLine();

            $result = $this->processIntradayOIAndIV($broker, $zerodhaHelper, $date);
            
            $success = $result['success'];
            $failed = $result['failed'];

            $this->info("   Summary: ✓ {$success} symbols | ✗ {$failed} failed");
            $this->newLine();

        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage() . "\n");
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
            $this->warn("   ⚠️  No valid symbols found with 15minute interval");
            return ['success' => 0, 'failed' => 0];
        }

        $this->info("   📊 Processing " . $validSymbols->count() . " symbols (15minute interval)");
        $this->newLine();

        foreach ($validSymbols as $futSymbol) {
            try {
                $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
                
                $this->info("   └─ {$baseSymbol}");
                
                $futInstrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)
                    ->where('exchange', 'NFO')
                    ->first();
                
                if (!$futInstrument) {
                    if ($this->option('debug')) {
                        $this->warn("      ❌ FUT instrument not found");
                    }
                    $failed++;
                    continue;
                }
                
                // Get spot price at current day 12:15
                $spotPrice = $this->getIntradayPrice($zerodhaHelper, $futInstrument->instrument_token, $date);
                
                if (!$spotPrice) {
                    if ($this->option('debug')) {
                        $this->warn("      ❌ No price data");
                    }
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

                // Fetch FUT, CE, PE intraday data
                $futAnalysis = $this->fetchFutureIntradayOIAndIV($broker, $futSymbol, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                $ceAnalysis = $this->fetchAndMergeCEIntradayOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                $peAnalysis = $this->fetchAndMergePEIntradayOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);

                // Calculate BTST signal
                if ($futAnalysis && $ceAnalysis && $peAnalysis) {
                    $this->calculateAndStoreBTSTSignal($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    
                    $this->info("      ✓ Intraday OI + IV + BTST signals stored");
                    $success++;
                } else {
                    $this->warn("      ⚠️ No data stored");
                    $failed++;
                }

            } catch (Exception $e) {
                $this->error("      ✗ Failed: " . $e->getMessage());
                Log::error("Failed to process intraday symbol: {$baseSymbol}", [
                    'date' => $date,
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Calculate and store BTST signal for intraday
     */
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

        if ($this->option('debug')) {
            $this->info("      📊 BTST: {$btst['btst_signal']} (Confidence: {$btst['confidence']}%)");
            $this->info("      💡 Reason: {$btst['reason']}");
        }
    }

    /**
     * ✅ CORRECTED: Fetch Future intraday OI from PREVIOUS DAY 12:15 to CURRENT DAY 12:15
     */
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

            // ✅ Fetch PREVIOUS trading day's 12:15 OI from API
            $previousTradingDay = $this->getPreviousTradingDay($date);
            $prevDayOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $previousTradingDay, '12:15:00');
            
            // ✅ Fetch CURRENT day's 12:15 OI from API
            $currentOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $date, '12:15:00');
            
            if ($currentOI === null) {
                if ($this->option('debug')) {
                    $this->warn("      ⚠️ Could not fetch current day OI at 12:15");
                }
                return null;
            }

            // If prev day OI not available, use 0
            $prevDayOI = $prevDayOI ?? 0;

            if ($this->option('debug')) {
                $this->info("      📊 FUT OI: Prev Day ({$previousTradingDay}) = {$prevDayOI}, Current Day ({$date}) = {$currentOI}");
            }
            
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
            Log::error("FUT intraday OI fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ✅ CORRECTED: Fetch CE merged OI from PREVIOUS DAY 12:15 to CURRENT DAY 12:15
     */
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

                // ✅ Fetch CURRENT day 12:15 OI and Price
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

                // ✅ Fetch PREVIOUS day 12:15 OI
                $prevDayOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $previousTradingDay, '12:15:00');
                $prevDayTotalOI += ($prevDayOI ?? 0);

                // Calculate IV
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
            }

            if ($currentDayTotalOI == 0) {
                return null;
            }

            if ($this->option('debug')) {
                $this->info("      📊 CE OI: Prev Day ({$previousTradingDay}) = {$prevDayTotalOI}, Current Day ({$date}) = {$currentDayTotalOI}");
            }

            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            
            // Get previous day IV from database (since IV is calculated, not fetched)
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
            Log::error("CE intraday merge failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ✅ CORRECTED: Fetch PE merged OI from PREVIOUS DAY 12:15 to CURRENT DAY 12:15
     */
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

                // ✅ Fetch CURRENT day 12:15 OI and Price
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

                // ✅ Fetch PREVIOUS day 12:15 OI
                $prevDayOI = $this->fetchOIAtSpecificTime($zerodhaHelper, $instrument->instrument_token, $previousTradingDay, '12:15:00');
                $prevDayTotalOI += ($prevDayOI ?? 0);

                // Calculate IV
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
            }

            if ($currentDayTotalOI == 0) {
                return null;
            }

            if ($this->option('debug')) {
                $this->info("      📊 PE OI: Prev Day ({$previousTradingDay}) = {$prevDayTotalOI}, Current Day ({$date}) = {$currentDayTotalOI}");
            }

            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            
            // Get previous day IV from database
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
            Log::error("PE intraday merge failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ✅ NEW: Fetch OI at a specific time on a specific date using DAY candle
     */
    private function fetchOIAtSpecificTime($zerodhaHelper, int $instrumentToken, string $date, string $time): ?int
    {
        try {
            // Use DAY candle to get specific time data
            $startDateTime = $date . ' ' . $time;
            $endDateTime = Carbon::parse($startDateTime)->addMinutes(1)->format('Y-m-d H:i:s');
            
            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',  // ✅ Use 'day' interval to get the time-specific snapshot
                $startDateTime,
                $endDateTime
            );

            if (empty($data)) {
                return null;
            }

            $candle = $data[0];
            return ($candle->oi ?? null) !== 'null' ? (int)($candle->oi ?? 0) : 0;

        } catch (Exception $e) {
            Log::error("OI fetch at specific time failed for token {$instrumentToken} at {$date} {$time}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ NEW: Fetch OI and Price at a specific time on a specific date using DAY candle
     */
    private function fetchOIAndPriceAtSpecificTime($zerodhaHelper, int $instrumentToken, string $date, string $time): ?array
    {
        try {
            $startDateTime = $date . ' ' . $time;
            $endDateTime = Carbon::parse($startDateTime)->addMinutes(1)->format('Y-m-d H:i:s');
            
            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',  // ✅ Use 'day' interval
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
            Log::error("OI+Price fetch at specific time failed for token {$instrumentToken} at {$date} {$time}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get intraday price at 12:15
     */
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
            Log::error("Intraday price fetch failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ NEW: Get previous trading day (skips weekends and holidays)
     */
    private function getPreviousTradingDay(string $currentDate): string
    {
        $prevDate = Carbon::parse($currentDate)->subDay();
        $maxLookback = 10;
        $attempts = 0;
        
        while ($attempts < $maxLookback) {
            // Skip special Sunday
            if ($prevDate->format('Y-m-d') === '2026-02-01') {
                return $prevDate->format('Y-m-d');
            }

            // Skip weekends
            if ($prevDate->isWeekend()) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }

            // Skip holidays
            if ($this->isHoliday($prevDate->format('Y-m-d'))) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }

            // Found valid trading day
            return $prevDate->format('Y-m-d');
        }

        // Fallback: return previous day even if might be holiday
        return Carbon::parse($currentDate)->subDay()->format('Y-m-d');
    }

    /**
     * Get previous trading day's 12:15 IV (from database)
     */
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