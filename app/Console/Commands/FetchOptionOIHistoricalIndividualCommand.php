<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Models\OptionStrikeIndividual;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use App\Services\OiAnalysisServiceNew;
use App\Services\IVAnalysisServiceNew;
use App\Helpers\IVCalculator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchOptionOIHistoricalIndividualCommand extends Command
{
    protected $signature = 'options:fetch-oi-historical-individual 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force fetch even on holidays}
                            {--debug : Show detailed debug information}';

    protected $description = 'Fetch historical OI + IV + Close Price data for INDIVIDUAL strikes with BTST signals';

    private $riskFreeRate = 0.06;
    private $apiCallDelay = 350000;
    private $maxRetries = 3;

    public function handle()
    {
        try {
            $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');

            $this->info("🚀 Starting Historical INDIVIDUAL Strike Data Fetch");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            $this->info("   Mode: INDIVIDUAL STRIKES (Not Aggregated)");
            $this->info("   EOD Time: 3:15 PM");
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

                $result = $this->processBrokerHistorical($broker, $fromDate, $toDate);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Historical Individual Strike Fetch Completed!");
            $this->info("   Total Processed: {$totalProcessed} symbols");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Historical Individual Strike Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBrokerHistorical(BrokerApi $broker, $fromDate, $toDate)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            $dateRange = $this->getTradingDays($fromDate, $toDate);
            
            $this->info("   📅 Processing " . count($dateRange) . " trading day(s)");
            $this->newLine();

            foreach ($dateRange as $date) {
                $this->info("   ╔═══ Date: {$date} ═══");
                
                try {
                    $result = $this->processDailyIndividualStrikes($broker, $zerodhaHelper, $date);
                    
                    $success += $result['success'];
                    $failed += $result['failed'];

                    $this->info("   ║  Day Summary: ✓ {$result['success']} symbols | ✗ {$result['failed']} failed");
                    $this->info("   ╚═══════════════════════════════");
                    $this->newLine();

                } catch (Exception $e) {
                    $this->error("   ║  ✗ Failed to process {$date}: " . $e->getMessage());
                    $this->info("   ╚═══════════════════════════════");
                    $this->newLine();
                    $failed++;
                }
            }

        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage() . "\n");
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function processDailyIndividualStrikes(BrokerApi $broker, BrokerZerodhaHelper $zerodhaHelper, string $date)
    {
        $success = 0;
        $failed = 0;

        $futureSymbolsQuery = DB::table('symbols_monitored')
            ->where('broker_api_id', $broker->id)
            ->where('is_active', true)
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
            $this->warn("   ║  ⚠️  No valid symbols found");
            return ['success' => 0, 'failed' => 0];
        }

        $this->info("   ║  Processing " . $validSymbols->count() . " symbols");

        foreach ($validSymbols as $futSymbol) {
            try {
                $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
                
                $futInstrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)
                    ->where('exchange', 'NFO')
                    ->first();
                
                if (!$futInstrument) {
                    if ($this->option('debug')) {
                        $this->warn("   ║    ❌ {$baseSymbol}: FUT instrument not found");
                    }
                    $failed++;
                    continue;
                }
                
                $spotPrice = $this->getSpotPrice($zerodhaHelper, $futInstrument->instrument_token, $date);
                
                if (!$spotPrice) {
                    if ($this->option('debug')) {
                        $this->warn("   ║    ❌ {$baseSymbol}: No price data");
                    }
                    $failed++;
                    continue;
                }

                $strikeIntervals = $this->getStrikeIntervals();
                $strikeInterval = $strikeIntervals[$baseSymbol] ?? 100;
                $atmStrike = round($spotPrice / $strikeInterval) * $strikeInterval;

                // ✅ Fetch FUT data
                $futAnalysis = $this->fetchFutureOIAndIV($broker, $futSymbol, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                
                // ✅ Fetch INDIVIDUAL CE strikes
                $ceAnalyses = $this->fetchIndividualCEStrikes($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                
                // ✅ Fetch INDIVIDUAL PE strikes
                $peAnalyses = $this->fetchIndividualPEStrikes($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);

                // ✅ Calculate BTST signal using aggregated data
                if ($futAnalysis && !empty($ceAnalyses) && !empty($peAnalyses)) {
                    $this->calculateAndStoreBTSTSignal($broker, $baseSymbol, $date, $futAnalysis, $ceAnalyses, $peAnalyses);
                    
                    $strikeCount = 1 + count($ceAnalyses) + count($peAnalyses); // FUT + CE strikes + PE strikes
                    $this->info("   ║    ✓ {$baseSymbol}: {$strikeCount} individual strikes stored");
                    $success++;
                } else {
                    $failed++;
                }

            } catch (Exception $e) {
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
     * ✅ Fetch FUT data (same as before)
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

            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'FUT', $date);
            $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, 'FUT', $date);
            
            $closeChange = null;
            $closeChangePct = null;
            if ($prevDayClose > 0) {
                $closeChange = $eodData['close'] - $prevDayClose;
                $closeChangePct = ($closeChange / $prevDayClose) * 100;
            }

            $oiAnalysis = OiAnalysisServiceNew::analyzeFuturesOI($eodData['oi'], $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseFutSignal($oiAnalysis['direction']);

            OptionStrikeIndividual::updateOrCreate(
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
                    'daily_oi' => $oiAnalysis['daily_oi'],
                    'daily_oi_prev' => $oiAnalysis['daily_oi_prev'],
                    'daily_oi_change' => $oiAnalysis['daily_oi_change'],
                    'daily_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                    'direction' => $reversedDirection,
                    'strength' => $oiAnalysis['strength'],
                    'market_bias' => $oiAnalysis['market_bias'] ?? null,
                    'daily_close' => $eodData['close'],
                    'daily_close_prev' => $prevDayClose,
                    'daily_close_change' => $closeChange,
                    'daily_close_change_pct' => $closeChangePct,
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return ['oi' => $oiAnalysis];

        } catch (Exception $e) {
            Log::error("FUT OI fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ✅ NEW - Fetch INDIVIDUAL CE strikes (not aggregated)
     */
    private function fetchIndividualCEStrikes(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): array {
        $ceAnalyses = [];
        
        $strikes = [
            'ATM-2' => $atmStrike - (2 * $strikeInterval),
            'ATM-1' => $atmStrike - $strikeInterval,
            'ATM' => $atmStrike,
            'ATM+1' => $atmStrike + $strikeInterval,
            'ATM+2' => $atmStrike + (2 * $strikeInterval)
        ];

        $expiryDate = $this->getNextExpiry($baseSymbol, $date);
        if (!$expiryDate) {
            return [];
        }
        
        $expiryCode = strtoupper($expiryDate->format('yM'));
        $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
        if ($daysToExpiry == 0) $daysToExpiry = 1;

        foreach ($strikes as $position => $strike) {
            try {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'CE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                
                if (!$eodData || !isset($eodData['oi']) || !isset($eodData['close']) || $eodData['close'] <= 0) {
                    continue;
                }

                // Calculate IV
                $iv = IVCalculator::calculate(
                    $eodData['close'],
                    $spotPrice,
                    $strike,
                    $daysToExpiry,
                    'CE',
                    $this->riskFreeRate
                );

                // Get previous day data
                $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, $position, $date, 'CE');
                $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, $position, $date, 'CE');
                $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, $position, $date, 'CE');

                // OI Analysis
                $oiAnalysis = OiAnalysisServiceNew::analyzeCallOptionsOI($eodData['oi'], $prevDayOI, $baseSymbol);
                $reversedOiDirection = $this->reverseCESignal($oiAnalysis['direction']);

                // IV Analysis
                $ivAnalysis = IVAnalysisServiceNew::analyzeCallOptionsIV($iv, $prevDayIV, $baseSymbol);

                // IV changes
                $ivChange = null;
                $ivChangePct = null;
                if ($iv !== null && $prevDayIV > 0) {
                    $ivChange = $iv - $prevDayIV;
                    $ivChangePct = ($ivChange / $prevDayIV) * 100;
                }

                // Close price changes
                $closeChange = null;
                $closeChangePct = null;
                if ($prevDayClose > 0) {
                    $closeChange = $eodData['close'] - $prevDayClose;
                    $closeChangePct = ($closeChange / $prevDayClose) * 100;
                }

                // Store individual strike
                OptionStrikeIndividual::updateOrCreate(
                    [
                        'broker_api_id' => $broker->id,
                        'underlying_symbol' => $baseSymbol,
                        'trading_symbol' => $tradingSymbol,
                        'strike_position' => $position,
                        'trading_date' => $date
                    ],
                    [
                        'option_type' => 'CE',
                        'strike_price' => $strike,
                        'expiry' => $expiryCode,
                        'expiry_date' => $expiryDate,
                        'instrument_token' => $instrument->instrument_token,
                        'exchange' => 'NFO',
                        'lot_size' => $instrument->lot_size ?? 1,
                        'is_active' => true,
                        // OI fields
                        'daily_oi' => $oiAnalysis['daily_oi'],
                        'daily_oi_prev' => $oiAnalysis['daily_oi_prev'],
                        'daily_oi_change' => $oiAnalysis['daily_oi_change'],
                        'daily_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                        'direction' => $reversedOiDirection,
                        'strength' => $oiAnalysis['strength'],
                        // IV fields
                        'daily_iv' => $iv,
                        'daily_iv_prev' => $prevDayIV,
                        'daily_iv_change' => $ivChange,
                        'daily_iv_change_pct' => $ivChangePct,
                        'iv_direction' => $ivAnalysis['iv_direction'],
                        'iv_strength' => $ivAnalysis['iv_strength'],
                        // Close price fields
                        'daily_close' => $eodData['close'],
                        'daily_close_prev' => $prevDayClose,
                        'daily_close_change' => $closeChange,
                        'daily_close_change_pct' => $closeChangePct,
                        'spot_price' => $spotPrice,
                        'last_synced_at' => now()
                    ]
                );

                $ceAnalyses[] = [
                    'position' => $position,
                    'oi' => $oiAnalysis,
                    'iv' => $ivAnalysis
                ];

            } catch (Exception $e) {
                Log::error("CE strike failed: {$baseSymbol} {$position}", ['error' => $e->getMessage()]);
                continue;
            }
        }

        return $ceAnalyses;
    }

    /**
     * ✅ NEW - Fetch INDIVIDUAL PE strikes (not aggregated)
     */
    private function fetchIndividualPEStrikes(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): array {
        $peAnalyses = [];
        
        $strikes = [
            'ATM-2' => $atmStrike - (2 * $strikeInterval),
            'ATM-1' => $atmStrike - $strikeInterval,
            'ATM' => $atmStrike,
            'ATM+1' => $atmStrike + $strikeInterval,
            'ATM+2' => $atmStrike + (2 * $strikeInterval)
        ];

        $expiryDate = $this->getNextExpiry($baseSymbol, $date);
        if (!$expiryDate) {
            return [];
        }
        
        $expiryCode = strtoupper($expiryDate->format('yM'));
        $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
        if ($daysToExpiry == 0) $daysToExpiry = 1;

        foreach ($strikes as $position => $strike) {
            try {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'PE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                
                if (!$eodData || !isset($eodData['oi']) || !isset($eodData['close']) || $eodData['close'] <= 0) {
                    continue;
                }

                // Calculate IV
                $iv = IVCalculator::calculate(
                    $eodData['close'],
                    $spotPrice,
                    $strike,
                    $daysToExpiry,
                    'PE',
                    $this->riskFreeRate
                );

                // Get previous day data
                $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, $position, $date, 'PE');
                $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, $position, $date, 'PE');
                $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, $position, $date, 'PE');

                // OI Analysis
                $oiAnalysis = OiAnalysisServiceNew::analyzePutOptionsOI($eodData['oi'], $prevDayOI, $baseSymbol);
                $reversedOiDirection = $this->reversePESignal($oiAnalysis['direction']);

                // IV Analysis
                $ivAnalysis = IVAnalysisServiceNew::analyzePutOptionsIV($iv, $prevDayIV, $baseSymbol);

                // IV changes
                $ivChange = null;
                $ivChangePct = null;
                if ($iv !== null && $prevDayIV > 0) {
                    $ivChange = $iv - $prevDayIV;
                    $ivChangePct = ($ivChange / $prevDayIV) * 100;
                }

                // Close price changes
                $closeChange = null;
                $closeChangePct = null;
                if ($prevDayClose > 0) {
                    $closeChange = $eodData['close'] - $prevDayClose;
                    $closeChangePct = ($closeChange / $prevDayClose) * 100;
                }

                // Store individual strike
                OptionStrikeIndividual::updateOrCreate(
                    [
                        'broker_api_id' => $broker->id,
                        'underlying_symbol' => $baseSymbol,
                        'trading_symbol' => $tradingSymbol,
                        'strike_position' => $position,
                        'trading_date' => $date
                    ],
                    [
                        'option_type' => 'PE',
                        'strike_price' => $strike,
                        'expiry' => $expiryCode,
                        'expiry_date' => $expiryDate,
                        'instrument_token' => $instrument->instrument_token,
                        'exchange' => 'NFO',
                        'lot_size' => $instrument->lot_size ?? 1,
                        'is_active' => true,
                        // OI fields
                        'daily_oi' => $oiAnalysis['daily_oi'],
                        'daily_oi_prev' => $oiAnalysis['daily_oi_prev'],
                        'daily_oi_change' => $oiAnalysis['daily_oi_change'],
                        'daily_oi_change_pct' => $oiAnalysis['daily_oi_change_pct'],
                        'direction' => $reversedOiDirection,
                        'strength' => $oiAnalysis['strength'],
                        // IV fields
                        'daily_iv' => $iv,
                        'daily_iv_prev' => $prevDayIV,
                        'daily_iv_change' => $ivChange,
                        'daily_iv_change_pct' => $ivChangePct,
                        'iv_direction' => $ivAnalysis['iv_direction'],
                        'iv_strength' => $ivAnalysis['iv_strength'],
                        // Close price fields
                        'daily_close' => $eodData['close'],
                        'daily_close_prev' => $prevDayClose,
                        'daily_close_change' => $closeChange,
                        'daily_close_change_pct' => $closeChangePct,
                        'spot_price' => $spotPrice,
                        'last_synced_at' => now()
                    ]
                );

                $peAnalyses[] = [
                    'position' => $position,
                    'oi' => $oiAnalysis,
                    'iv' => $ivAnalysis
                ];

            } catch (Exception $e) {
                Log::error("PE strike failed: {$baseSymbol} {$position}", ['error' => $e->getMessage()]);
                continue;
            }
        }

        return $peAnalyses;
    }

    /**
     * ✅ Calculate BTST signal using aggregated CE/PE data
     */
    private function calculateAndStoreBTSTSignal(
        BrokerApi $broker,
        string $baseSymbol,
        string $date,
        array $futAnalysis,
        array $ceAnalyses,
        array $peAnalyses
    ): void {
        // Aggregate CE data
        $totalCeOI = 0;
        $totalCeIV = 0;
        $ceIVCount = 0;

        foreach ($ceAnalyses as $ce) {
            $totalCeOI += $ce['oi']['daily_oi'];
            if ($ce['iv']['daily_iv'] !== null) {
                $totalCeIV += $ce['iv']['daily_iv'];
                $ceIVCount++;
            }
        }

        $avgCeIV = $ceIVCount > 0 ? ($totalCeIV / $ceIVCount) : null;

        // Aggregate PE data
        $totalPeOI = 0;
        $totalPeIV = 0;
        $peIVCount = 0;

        foreach ($peAnalyses as $pe) {
            $totalPeOI += $pe['oi']['daily_oi'];
            if ($pe['iv']['daily_iv'] !== null) {
                $totalPeIV += $pe['iv']['daily_iv'];
                $peIVCount++;
            }
        }

        $avgPeIV = $peIVCount > 0 ? ($totalPeIV / $peIVCount) : null;

        // Create aggregated analysis for BTST
        $ceAggregated = [
            'oi' => ['daily_oi' => $totalCeOI],
            'iv' => ['daily_iv' => $avgCeIV]
        ];

        $peAggregated = [
            'oi' => ['daily_oi' => $totalPeOI],
            'iv' => ['daily_iv' => $avgPeIV]
        ];

        // Get BTST signal
        $btst = IVAnalysisServiceNew::getBTSTSignal(
            $futAnalysis['oi'],
            $ceAggregated['oi'],
            $ceAggregated['iv'],
            $peAggregated['oi'],
            $peAggregated['iv']
        );

        // Update FUT record with BTST signal
        OptionStrikeIndividual::where('broker_api_id', $broker->id)
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

    // ==================== HELPER METHODS ====================

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
            $startDateTime = Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:15:00';
            $endDateTime = $date . ' 15:15:00';

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
            Log::error("Daily 3:15 PM data fetch failed for token {$instrumentToken}: " . $e->getMessage());
            throw $e;
        }
    }

    private function reverseFutSignal($signal)
    {
        if (!$signal || $signal === 'N/A' || $signal === 'NEUTRAL') return 'NEUTRAL';
        if ($signal === 'BUILDUP') return 'BULLISH';
        if ($signal === 'UNWINDING') return 'BEARISH';
        return 'NEUTRAL';
    }

    private function reverseCESignal($signal)
    {
        if (!$signal || $signal === 'N/A' || $signal === 'NEUTRAL') return 'NEUTRAL';
        if ($signal === 'BULLISH') return 'BEARISH';
        if ($signal === 'BEARISH') return 'BULLISH';
        return 'NEUTRAL';
    }

    private function reversePESignal($signal)
    {
        if (!$signal || $signal === 'N/A' || $signal === 'NEUTRAL') return 'NEUTRAL';
        if ($signal === 'BULLISH') return 'BEARISH';
        if ($signal === 'BEARISH') return 'BULLISH';
        return 'NEUTRAL';
    }

    private function getPreviousDayOI(BrokerApi $broker, string $baseSymbol, string $position, string $currentDate, string $optionType = 'FUT'): int
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

            $query = OptionStrikeIndividual::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $baseSymbol)
                ->where('strike_position', $position)
                ->where('trading_date', $prevDate->format('Y-m-d'));

            if ($optionType !== 'FUT') {
                $query->where('option_type', $optionType);
            }

            $prevRecord = $query->first();

            if ($prevRecord && $prevRecord->daily_oi > 0) {
                return $prevRecord->daily_oi;
            }

            $prevDate->subDay();
            $attempts++;
        }

        return 0;
    }

    private function getPreviousDayIV(BrokerApi $broker, string $baseSymbol, string $position, string $currentDate, string $optionType = 'CE'): float
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

            $prevRecord = OptionStrikeIndividual::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $baseSymbol)
                ->where('strike_position', $position)
                ->where('option_type', $optionType)
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

    private function getPreviousDayClose(BrokerApi $broker, string $baseSymbol, string $position, string $currentDate, string $optionType = 'FUT'): float
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

            $query = OptionStrikeIndividual::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $baseSymbol)
                ->where('strike_position', $position)
                ->where('trading_date', $prevDate->format('Y-m-d'));

            if ($optionType !== 'FUT') {
                $query->where('option_type', $optionType);
            }

            $prevRecord = $query->first();

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
            $startDateTime = Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:15:00';
            $endDateTime = $date . ' 15:15:00';
            
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

    private function getTradingDays($fromDate, $toDate): array
    {
        $dates = [];
        $current = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);

        while ($current->lte($end)) {
            $date = $current->format('Y-m-d');
            $isSpecialTradingSunday = ($date === '2026-02-01');
            if (($isSpecialTradingSunday || !$current->isWeekend()) && !$this->isHoliday($date)) {
                $dates[] = $date;
            }
            $current->addDay();
        }

        return $dates;
    }

    private function getStrikeIntervals(): array
    {
        return [
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
        ];
    }
}