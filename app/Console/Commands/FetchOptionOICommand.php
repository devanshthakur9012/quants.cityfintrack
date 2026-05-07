<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Models\OptionStrike;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use App\Services\OiAnalysisServiceNew;
use App\Services\IVAnalysisServiceNew;
use App\Helpers\IVCalculator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchOptionOICommand extends Command
{
    protected $signature = 'options:fetch-oi 
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force fetch even on holidays}
                            {--debug : Show detailed debug information}';

    protected $description = 'Fetch daily EOD OI + IV + Close Price data with signals + BTST + PE/CE Analysis';

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
                Log::info("Option OI+IV+Close+PE/CE fetch skipped: Weekend");
                return 0;
            }

            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Option OI+IV+Close+PE/CE fetch skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->info("🚀 Starting Daily EOD OI + IV + Close Price Fetch with PE/CE Analysis");
            $this->info("   Date: {$today}");
            $this->info("   Time: " . Carbon::now()->format('H:i:s'));
            $this->info("   Signals: OI + IV + BTST + PE/CE Ratio + Trade Action");
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

                $result = $this->processBrokerDaily($broker, $today);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Daily EOD OI + IV + Close + PE/CE Analysis Completed!");
            $this->info("   Total Processed: {$totalProcessed} symbols");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Daily EOD OI+IV+Close+PE/CE Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBrokerDaily(BrokerApi $broker, string $date)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            $this->info("   📅 Processing EOD OI + IV + Close Price + PE/CE for: {$date}");
            $this->newLine();

            $result = $this->processDailyOIAndIV($broker, $zerodhaHelper, $date);
            
            $success = $result['success'];
            $failed = $result['failed'];

            $this->info("   Summary: ✓ {$success} symbols | ✗ {$failed} failed");
            $this->newLine();

        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage() . "\n");
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function processDailyOIAndIV(BrokerApi $broker, BrokerZerodhaHelper $zerodhaHelper, string $date)
    {
        $success = 0;
        $failed = 0;

        $futureSymbolsQuery = DB::table('symbols_monitored')
            ->where('broker_api_id', $broker->id)
            ->where('is_active', true)
            ->where('interval', '5minute')
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
            $this->warn("   ⚠️  No valid symbols found");
            return ['success' => 0, 'failed' => 0];
        }

        $this->info("   📊 Processing " . $validSymbols->count() . " symbols");
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
                
                $spotPrice = $this->getSpotPrice($zerodhaHelper, $futInstrument->instrument_token, $date);
                
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
                    'AXISBANK'     => 10,
                    'ICICIBANK'    => 10,
                    'INDUSINDBK'   => 10, 
                    'BHARTIARTL'   => 20,
                    'SHRIRAMFIN'   => 10,
                    'LTF'          => 5,
                    'PAYTM'        => 20,
                    'POLICYBZR'    => 20,
                    'BAJAJFINSV'   => 20,
                    'INFY'         => 20,
                    'TATAELXSI'    => 50,
                    'TATATECH'     => 10,
                    'HAVELLS'      => 20,
                    'TITAN'        => 20,
                    'ASIANPAINT'   => 20,
                    'TATACONSUMER' => 10,
                    'VOLTAS'       => 20,
                    'AUROPHARMA'   => 10,
                    'LAURUSLABS'   => 10,
                    'SRF'          => 20,
                    'JSWSTEEL'     => 10,
                    'LT'           => 20,
                    'BHEL'         => 5,
                    'ADANIPORTS'   => 20,
                    'HAL'          => 50,
                    'BDL'          => 20,
                    'MCX'          => 20,
                    'BSE'          => 50,
                    'CDSL'         => 20,
                    'LICHSG'       => 5,
                    'DELHIVERY'    => 10,
                    'BHARATFORG'   => 20,
                    'PGEL'         => 10,
                    'TMPV'         => 5,
                    // NEW
                    'HINDALCO'   => 10,
                    'VEDL'       => 10,
                    'DRREDDY'    => 50,
                    'LICHSGFIN'  => 5,
                    'TATACONSUM' => 10,
                    'ABCCAPITAL' => 10,
                    'SBIN'       => 10,
                    'VBL'        => 20,
                    'BAJFINANCE' => 50,
                    'TCS'        => 50,
                    'COFORGE'    => 50,
                    'EICHERMOT'  => 50,
                    'HEROMOTOCO' => 20,
                    'AMBUJACEM'  => 5,
                    'FORTIS'     => 5,
                    'UPL'        => 10,
                    'M&M'        => 20,
                    'NATIONALUM' => 5,
                    'BPCL'       => 10,
                    'ETERNAL'    => 10,
                ];
                
                $strikeInterval = $strikeIntervals[$baseSymbol] ?? 100;
                $atmStrike = round($spotPrice / $strikeInterval) * $strikeInterval;

                // ✅ Fetch FUT, CE, PE data
                $futAnalysis = $this->fetchFutureOIAndIV($broker, $futSymbol, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                $ceAnalysis = $this->fetchAndMergeCEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                $peAnalysis = $this->fetchAndMergePEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);

                // ✅ Calculate BTST + PE/CE SIGNALS
                if ($futAnalysis && $ceAnalysis && $peAnalysis) {
                    $this->calculateAndStoreBTSTSignal($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    
                    // ✅ Calculate PE/CE Ratio Analysis
                    $this->calculateAndStorePECEAnalysis($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    
                    $this->info("      ✓ OI + IV + Close + BTST + PE/CE signals stored");
                    $success++;
                } else {
                    $this->warn("      ⚠️ No data stored");
                    $failed++;
                }

            } catch (Exception $e) {
                $this->error("      ✗ Failed: " . $e->getMessage());
                Log::error("Failed to process symbol: {$baseSymbol}", [
                    'date' => $date,
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * ✅ ALIGNED: Get OI Signal based on CE% and PE% changes (MATCHES FRONTEND & HISTORICAL)
     * 
     * Logic:
     * - CE↑ + PE↓ → BEARISH (Call buildup + Put unwinding)
     * - CE↓ + PE↑ → BULLISH (Call unwinding + Put buildup)
     * - Both↑ → Compare: If CE% > PE% → BEARISH, else BULLISH
     * - Both↓ → Compare: If CE% < PE% (more negative) → BULLISH, else BEARISH
     */
    private function getOISignal(float $ceChangePct, float $peChangePct): array
    {
        // ✅ REMOVED THRESHOLD - Match frontend behavior
        // Determine direction
        $ceUp = $ceChangePct > 0;
        $ceDown = $ceChangePct < 0;
        $peUp = $peChangePct > 0;
        $peDown = $peChangePct < 0;

        // ===== CLEAR SCENARIOS =====
        
        // Bearish: CE up + PE down (Call buildup + Put unwinding)
        if ($ceUp && $peDown) {
            return [
                'signal' => 'BEARISH',
                'reason' => 'Call buildup + Put unwinding',
                'condition' => 'CE ↑ + PE ↓'
            ];
        }

        // Bullish: CE down + PE up (Call unwinding + Put buildup)
        if ($ceDown && $peUp) {
            return [
                'signal' => 'BULLISH',
                'reason' => 'Call unwinding + Put buildup',
                'condition' => 'CE ↓ + PE ↑'
            ];
        }

        // ===== BOTH SCENARIOS - COMPARE STRENGTH =====
        
        // Both up → Compare which is stronger
        if ($ceUp && $peUp) {
            if ($ceChangePct > $peChangePct) {
                return [
                    'signal' => 'BEARISH',
                    'reason' => "Both buildup but CE stronger (CE: +{$ceChangePct}% > PE: +{$peChangePct}%)",
                    'condition' => 'Both ↑ (CE > PE)'
                ];
            } else {
                return [
                    'signal' => 'BULLISH',
                    'reason' => "Both buildup but PE stronger (PE: +{$peChangePct}% > CE: +{$ceChangePct}%)",
                    'condition' => 'Both ↑ (PE > CE)'
                ];
            }
        }

        // Both down → Compare which is more negative (stronger unwinding)
        if ($ceDown && $peDown) {
            if ($ceChangePct < $peChangePct) {
                // CE is more negative, means more CE unwinding → Bullish
                return [
                    'signal' => 'BULLISH',
                    'reason' => "Both unwinding but CE stronger (CE: {$ceChangePct}% < PE: {$peChangePct}%)",
                    'condition' => 'Both ↓ (CE < PE)'
                ];
            } else {
                // PE is more negative, means more PE unwinding → Bearish
                return [
                    'signal' => 'BEARISH',
                    'reason' => "Both unwinding but PE stronger (PE: {$peChangePct}% < CE: {$ceChangePct}%)",
                    'condition' => 'Both ↓ (PE < CE)'
                ];
            }
        }

        // Default: flat movement (both near zero)
        return [
            'signal' => 'NEUTRAL',
            'reason' => 'No clear OI direction (flat movement)',
            'condition' => 'Flat'
        ];
    }

    /**
     * ✅ UPDATED: Calculate and store PE/CE Ratio Analysis
     * Now includes oi_condition + ce_oi_change_pct + pe_oi_change_pct
     */
    private function calculateAndStorePECEAnalysis(
        BrokerApi $broker,
        string $baseSymbol,
        string $date,
        array $futAnalysis,
        array $ceAnalysis,
        array $peAnalysis
    ): void {
        // ✅ Get OI changes
        $ceChangePct = $ceAnalysis['oi']['daily_oi_change_pct'] ?? 0;
        $peChangePct = $peAnalysis['oi']['daily_oi_change_pct'] ?? 0;

        // ✅ Get clean OI-based signal
        $oiSignal = $this->getOISignal($ceChangePct, $peChangePct);

        $optionsSentiment = $oiSignal['signal'];
        $oiInterpretation = $oiSignal['reason'];
        $oiCondition = $oiSignal['condition'];

        // ✅ Calculate PE/CE Ratio (PE OI / CE OI)
        $ceOI = $ceAnalysis['oi']['daily_oi'] ?? 0;
        $peOI = $peAnalysis['oi']['daily_oi'] ?? 0;
        $peCeRatio = $ceOI > 0 ? round($peOI / $ceOI, 2) : 0;

        // ✅ Determine trade action based on sentiment
        if ($optionsSentiment === 'BULLISH') {
            $tradeAction = 'BUY CE';
        } elseif ($optionsSentiment === 'BEARISH') {
            $tradeAction = 'BUY PE';
        } else {
            $tradeAction = 'WAIT';
        }

        $finalSentiment = $optionsSentiment;
        $futuresOIView = $futAnalysis['oi']['market_bias'] ?? 'Normal';

        // ✅ Update FUT record with all fields
        OptionStrike::where('broker_api_id', $broker->id)
            ->where('underlying_symbol', $baseSymbol)
            ->where('strike_position', 'FUT')
            ->where('trading_date', $date)
            ->update([
                // NEW fields (matching historical command)
                'oi_interpretation' => $oiInterpretation,
                'oi_condition' => $oiCondition,
                'ce_oi_change_pct' => $ceChangePct,
                'pe_oi_change_pct' => $peChangePct,
                
                // Existing fields
                'pe_ce_ratio' => $peCeRatio,
                'options_sentiment' => $optionsSentiment,
                'futures_oi_view' => $futuresOIView,
                'final_sentiment' => $finalSentiment,
                'trade_action' => $tradeAction
            ]);

        if ($this->option('debug')) {
            $this->info("      📊 CE OI Change: " . number_format($ceChangePct, 2) . "%");
            $this->info("      📊 PE OI Change: " . number_format($peChangePct, 2) . "%");
            $this->info("      📊 Condition: {$oiCondition}");
            $this->info("      📊 PE/CE Ratio: {$peCeRatio}");
            $this->info("      💡 OI Interpretation: {$oiInterpretation}");
            $this->info("      📈 Options Sentiment: {$optionsSentiment}");
            $this->info("      🔮 Futures View: {$futuresOIView}");
            $this->info("      🎯 Final Sentiment: {$finalSentiment}");
            $this->info("      🚀 Trade Action: {$tradeAction}");
        }
    }

    /**
     * ✅ Calculate and store BTST signal
     */
    private function calculateAndStoreBTSTSignal(
        BrokerApi $broker,
        string $baseSymbol,
        string $date,
        array $futAnalysis,
        array $ceAnalysis,
        array $peAnalysis
    ): void {
        // Get final BTST recommendation
        $btst = IVAnalysisServiceNew::getBTSTSignal(
            $futAnalysis['oi'],
            $ceAnalysis['oi'],
            $ceAnalysis['iv'],
            $peAnalysis['oi'],
            $peAnalysis['iv']
        );

        // Update FUT record
        OptionStrike::where('broker_api_id', $broker->id)
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
     * ✅ Fetch Future OI, IV, and Close Price with % change
     */
    private function fetchFutureOIAndIV(
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

            $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
            
            if (!$eodData || !isset($eodData['oi']) || !isset($eodData['close'])) {
                return null;
            }

            // ✅ Get previous day data
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'FUT', $date);
            $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, 'FUT', $date);
            
            // ✅ Calculate close price changes
            $closeChange = null;
            $closeChangePct = null;
            if ($prevDayClose > 0) {
                $closeChange = $eodData['close'] - $prevDayClose;
                $closeChangePct = ($closeChange / $prevDayClose) * 100;
            }

            $oiAnalysis = OiAnalysisServiceNew::analyzeFuturesOI($eodData['oi'], $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseFutSignal($oiAnalysis['direction']);

            OptionStrike::updateOrCreate(
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
                    // OI fields
                    'daily_oi' => $oiAnalysis['daily_oi'],
                    'daily_oi_prev' => $oiAnalysis['daily_oi_prev'],
                    'daily_oi_change' => $oiAnalysis['daily_oi_change'],
                    'daily_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                    'direction' => $reversedDirection,
                    'strength' => $oiAnalysis['strength'],
                    'market_bias' => $oiAnalysis['market_bias'] ?? null,
                    // ✅ Close price fields
                    'daily_close' => $eodData['close'],
                    'daily_close_prev' => $prevDayClose,
                    'daily_close_change' => $closeChange,
                    'daily_close_change_pct' => $closeChangePct,
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return ['oi' => array_merge($oiAnalysis, ['daily_close_change_pct' => $closeChangePct])];

        } catch (Exception $e) {
            Log::error("FUT OI fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ✅ Fetch and Merge CE OI, IV, and Average Close Price with % change
     */
    private function fetchAndMergeCEOIAndIV(
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
                // $atmStrike - (2 * $strikeInterval),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                // $atmStrike + (2 * $strikeInterval)
            ];

            $totalOI = 0;
            $totalIV = 0;
            $totalClose = 0;
            $ivCount = 0;
            $closeCount = 0;
            $ceInstruments = [];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return null;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
            if ($daysToExpiry == 0) $daysToExpiry = 1;

            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'CE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                
                if (!$eodData || !isset($eodData['oi']) || !isset($eodData['close'])) {
                    continue;
                }

                $oi = $eodData['oi'] ?? 0;
                $close = $eodData['close'] ?? 0;

                if ($close <= 0) {
                    continue;
                }

                $totalOI += $oi;
                $totalClose += $close;
                $closeCount++;
                $ceInstruments[] = $tradingSymbol;

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

            if ($totalOI == 0) {
                return null;
            }

            // ✅ Calculate averages
            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $avgClose = $closeCount > 0 ? ($totalClose / $closeCount) : null;
            
            // ✅ Get previous day data
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'CE_MERGED', $date);
            $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, 'CE_MERGED', $date);
            $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, 'CE_MERGED', $date);
            
            // ✅ OI Analysis
            $oiAnalysis = OiAnalysisServiceNew::analyzeCallOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $reversedOiDirection = $this->reverseCESignal($oiAnalysis['direction']);

            // ✅ IV Analysis
            $ivAnalysis = IVAnalysisServiceNew::analyzeCallOptionsIV($avgIV, $prevDayIV, $baseSymbol);

            // ✅ Calculate IV changes
            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
            }

            // ✅ Calculate Close price changes
            $closeChange = null;
            $closeChangePct = null;
            if ($avgClose !== null && $prevDayClose > 0) {
                $closeChange = $avgClose - $prevDayClose;
                $closeChangePct = ($closeChange / $prevDayClose) * 100;
            }

            OptionStrike::updateOrCreate(
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
                    // OI fields
                    'daily_oi' => $oiAnalysis['daily_oi'],
                    'daily_oi_prev' => $oiAnalysis['daily_oi_prev'],
                    'daily_oi_change' => $oiAnalysis['daily_oi_change'],
                    'daily_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                    'direction' => $reversedOiDirection,
                    'strength' => $oiAnalysis['strength'],
                    // IV fields
                    'daily_iv' => $avgIV,
                    'daily_iv_prev' => $prevDayIV,
                    'daily_iv_change' => $ivChange,
                    'daily_iv_change_pct' => $ivChangePct,
                    'iv_direction' => $ivAnalysis['iv_direction'],
                    'iv_strength' => $ivAnalysis['iv_strength'],
                    // ✅ Close price fields (average of 5 strikes)
                    'daily_close' => $avgClose,
                    'daily_close_prev' => $prevDayClose,
                    'daily_close_change' => $closeChange,
                    'daily_close_change_pct' => $closeChangePct,
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return [
                'oi' => $oiAnalysis,
                'iv' => $ivAnalysis
            ];

        } catch (Exception $e) {
            Log::error("CE merge failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ✅ Fetch and Merge PE OI, IV, and Average Close Price with % change
     */
    private function fetchAndMergePEOIAndIV(
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
                // $atmStrike - (2 * $strikeInterval),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                // $atmStrike + (2 * $strikeInterval)
            ];

            $totalOI = 0;
            $totalIV = 0;
            $totalClose = 0;
            $ivCount = 0;
            $closeCount = 0;
            $peInstruments = [];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return null;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
            if ($daysToExpiry == 0) $daysToExpiry = 1;

            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'PE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                
                if (!$eodData || !isset($eodData['oi']) || !isset($eodData['close'])) {
                    continue;
                }

                $oi = $eodData['oi'] ?? 0;
                $close = $eodData['close'] ?? 0;

                if ($close <= 0) {
                    continue;
                }

                $totalOI += $oi;
                $totalClose += $close;
                $closeCount++;
                $peInstruments[] = $tradingSymbol;

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

            if ($totalOI == 0) {
                return null;
            }

            // ✅ Calculate averages
            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $avgClose = $closeCount > 0 ? ($totalClose / $closeCount) : null;
            
            // ✅ Get previous day data
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'PE_MERGED', $date);
            $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, 'PE_MERGED', $date);
            $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, 'PE_MERGED', $date);
            
            // ✅ OI Analysis
            $oiAnalysis = OiAnalysisServiceNew::analyzePutOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $reversedOiDirection = $this->reversePESignal($oiAnalysis['direction']);

            // ✅ IV Analysis
            $ivAnalysis = IVAnalysisServiceNew::analyzePutOptionsIV($avgIV, $prevDayIV, $baseSymbol);

            // ✅ Calculate IV changes
            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
            }

            // ✅ Calculate Close price changes
            $closeChange = null;
            $closeChangePct = null;
            if ($avgClose !== null && $prevDayClose > 0) {
                $closeChange = $avgClose - $prevDayClose;
                $closeChangePct = ($closeChange / $prevDayClose) * 100;
            }

            OptionStrike::updateOrCreate(
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
                    // OI fields
                    'daily_oi' => $oiAnalysis['daily_oi'],
                    'daily_oi_prev' => $oiAnalysis['daily_oi_prev'],
                    'daily_oi_change' => $oiAnalysis['daily_oi_change'],
                    'daily_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                    'direction' => $reversedOiDirection,
                    'strength' => $oiAnalysis['strength'],
                    // IV fields
                    'daily_iv' => $avgIV,
                    'daily_iv_prev' => $prevDayIV,
                    'daily_iv_change' => $ivChange,
                    'daily_iv_change_pct' => $ivChangePct,
                    'iv_direction' => $ivAnalysis['iv_direction'],
                    'iv_strength' => $ivAnalysis['iv_strength'],
                    // ✅ Close price fields (average of 5 strikes)
                    'daily_close' => $avgClose,
                    'daily_close_prev' => $prevDayClose,
                    'daily_close_change' => $closeChange,
                    'daily_close_change_pct' => $closeChangePct,
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return [
                'oi' => $oiAnalysis,
                'iv' => $ivAnalysis
            ];

        } catch (Exception $e) {
            Log::error("PE merge failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function fetchEODDataWithRetry($zerodhaHelper, int $instrumentToken, string $date): ?array
    {
        $attempt = 0;
        
        while ($attempt < $this->maxRetries) {
            try {
                if ($attempt > 0) {
                    $waitTime = $this->apiCallDelay * pow(2, $attempt);
                    usleep($waitTime);
                } else {
                    usleep($this->apiCallDelay);
                }

                $data = $this->fetchEODData($zerodhaHelper, $instrumentToken, $date);
                
                if ($data) {
                    return $data;
                }
                
                $attempt++;
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Too many requests') !== false) {
                    $attempt++;
                    continue;
                }
                
                return null;
            }
        }
        
        return null;
    }

    private function fetchEODData($zerodhaHelper, int $instrumentToken, string $date): ?array
    {
        try {
            $startDateTime = Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:00:00';
            $endDateTime = $date . ' 15:00:00';

            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',
                $startDateTime,
                $endDateTime
            );

            if (empty($data)) {
                return null;
            }

            $lastCandle = end($data);
            
            return [
                'oi' => ($lastCandle->oi ?? null) !== 'null' ? (float)($lastCandle->oi ?? 0) : 0,
                'close' => ($lastCandle->close ?? null) !== 'null' ? (float)($lastCandle->close ?? 0) : 0
            ];

        } catch (Exception $e) {
            Log::error("EOD data fetch failed for token {$instrumentToken}: " . $e->getMessage());
            throw $e;
        }
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

    private function getPreviousDayOI(BrokerApi $broker, string $baseSymbol, string $position, string $currentDate): int
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

            $prevRecord = OptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $baseSymbol)
                ->where('strike_position', $position)
                ->where('trading_date', $prevDate->format('Y-m-d'))
                ->first();

            if ($prevRecord && $prevRecord->daily_oi > 0) {
                return $prevRecord->daily_oi;
            }

            $prevDate->subDay();
            $attempts++;
        }

        return 0;
    }

    private function getPreviousDayIV(BrokerApi $broker, string $baseSymbol, string $position, string $currentDate): float
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

            $prevRecord = OptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $baseSymbol)
                ->where('strike_position', $position)
                ->where('trading_date', $prevDate->format('Y-m-d'))
                ->first();

            if ($prevRecord && $prevRecord->daily_iv > 0) {
                return $prevRecord->daily_iv;
            }

            $prevDate->subDay();
            $attempts++;
        }

        return 0.0;
    }

    private function getPreviousDayClose(BrokerApi $broker, string $baseSymbol, string $position, string $currentDate): float
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

            $prevRecord = OptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $baseSymbol)
                ->where('strike_position', $position)
                ->where('trading_date', $prevDate->format('Y-m-d'))
                ->first();

            if ($prevRecord && $prevRecord->daily_close > 0) {
                return $prevRecord->daily_close;
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

    private function getSpotPrice($zerodhaHelper, $instrumentToken, string $date): ?float
    {
        try {
            $startDateTime = Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:00:00';
            $endDateTime = $date . ' 15:00:00';
            
            $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',
                $startDateTime,
                $endDateTime
            );

            if (!empty($historicalData)) {
                $lastCandle = end($historicalData);
                return $lastCandle->close ?? null;
            }
            
            return null;
            
        } catch (Exception $e) {
            Log::error("Spot price fetch failed: " . $e->getMessage());
            return null;
        }
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