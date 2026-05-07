<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Helpers\OIAnalyzerSuper;
use App\Models\OptionsOhlcData;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class FetchOptionsOhlcHistoricalCommand extends Command
{
    protected $signature = 'options:fetch-ohlc-historical 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force fetch even on holidays}
                            {--debug : Show detailed debug information}';

    protected $description = 'Fetch historical 15-minute OHLC + OI data for FUT and OPTIONS (CE/PE strikes)';

    private $apiCallDelay = 350000; // 350ms between API calls

    public function handle()
    {
        try {
            $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');

            $this->info("🚀 Starting Historical 15-Minute OHLC + OI Data Fetch");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            $this->info("   Interval: 15minute");
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

            $this->info("📋 Found " . $brokers->count() . " broker(s)\n");

            $totalProcessed = 0;
            $totalFailed = 0;

            foreach ($brokers as $broker) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

                $result = $this->processBrokerHistorical($broker, $fromDate, $toDate);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Historical OHLC + OI Fetch Completed!");
            $this->info("   Processed: {$totalProcessed} | Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Historical Options OHLC Fetch Error: ' . $e->getMessage());
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
                    $result = $this->processDayOHLC($broker, $zerodhaHelper, $date);
                    
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

    private function processDayOHLC(BrokerApi $broker, BrokerZerodhaHelper $zerodhaHelper, string $date)
    {
        $success = 0;
        $failed = 0;

        // Get all FUT symbols
        $futureSymbolsQuery = DB::table('symbols_monitored')
            ->where('broker_api_id', $broker->id)
            ->where('is_active', true)
            ->where('interval', '5minute')
            ->where('trading_symbol', 'LIKE', '%FUT');

        if ($this->option('symbol')) {
            $futureSymbolsQuery->where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
        }

        $futureSymbols = $futureSymbolsQuery->get();

        // Filter to only symbols that have options
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
                
                // Get FUT instrument
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
                
                // Fetch spot price for the day
                $spotPrice = $this->getHistoricalSpotPrice($zerodhaHelper, $futInstrument->instrument_token, $date);
                
                if (!$spotPrice) {
                    if ($this->option('debug')) {
                        $this->warn("   ║    ❌ {$baseSymbol}: No spot price");
                    }
                    $failed++;
                    continue;
                }

                // Determine strike interval
                $strikeInterval = $this->getStrikeInterval($baseSymbol);
                $atmStrike = round($spotPrice / $strikeInterval) * $strikeInterval;

                // ===== FETCH HISTORICAL OHLC DATA =====
                
                // 1. Fetch FUT OHLC
                $futSuccess = $this->fetchAndStoreFutureOHLCHistorical(
                    $broker,
                    $futSymbol,
                    $zerodhaHelper,
                    $date,
                    $baseSymbol,
                    $spotPrice
                );

                // 2. Fetch CE strikes OHLC
                $ceSuccess = $this->fetchAndStoreCEStrikesOHLCHistorical(
                    $broker,
                    $baseSymbol,
                    $zerodhaHelper,
                    $date,
                    $atmStrike,
                    $strikeInterval,
                    $spotPrice
                );

                // 3. Fetch PE strikes OHLC
                $peSuccess = $this->fetchAndStorePEStrikesOHLCHistorical(
                    $broker,
                    $baseSymbol,
                    $zerodhaHelper,
                    $date,
                    $atmStrike,
                    $strikeInterval,
                    $spotPrice
                );

                if ($futSuccess || $ceSuccess || $peSuccess) {
                    $this->info("   ║    ✓ {$baseSymbol}: Historical OHLC + OI stored");
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
     * Fetch and store FUT historical OHLC data
     */
    private function fetchAndStoreFutureOHLCHistorical(
        BrokerApi $broker,
        $futSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        string $baseSymbol,
        float $spotPrice
    ): bool {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)
                ->where('exchange', 'NFO')
                ->first();

            if (!$instrument) {
                return false;
            }

            // Fetch full day's 15-minute data
            $fromTime = $date . ' 09:15:00';
            $toTime = $date . ' 15:30:00';

            usleep($this->apiCallDelay);
            
            $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                $instrument->instrument_token,
                '15minute',
                $fromTime,
                $toTime
            );

            if (empty($historicalData)) {
                return false;
            }

            $insertedCount = 0;

            foreach ($historicalData as $candle) {
                try {
                    $candleDate = $candle->date;

                    if ($candleDate instanceof \DateTime) {
                        $timestamp = Carbon::instance($candleDate);
                    } else {
                        $timestamp = Carbon::parse($candleDate);
                    }

                    // Market hours check
                    $time = $timestamp->format('H:i:s');
                    if ($time < '09:15:00' || $time > '15:30:00') {
                        continue;
                    }

                    // Get previous OI
                    $previousOI = OptionsOhlcData::getPreviousOI(
                        $broker->id,
                        $futSymbol->trading_symbol,
                        '15minute',
                        $timestamp->format('Y-m-d H:i:s')
                    );

                    $currentOI = (int)($candle->oi ?? 0);
                    
                    $oiData = [
                        'previous_oi' => $previousOI,
                        'oi_change' => 0,
                        'oi_change_percent' => 0,
                        'oi_signal' => 'NEUTRAL'
                    ];

                    if ($previousOI !== null && $currentOI > 0) {
                        $oiAnalysis = OIAnalyzerSuper::analyzeFuturesOI(
                            $currentOI,
                            $previousOI,
                            $baseSymbol
                        );
                        $oiData = $oiAnalysis;
                    }

                    OptionsOhlcData::updateOrCreate(
                        [
                            'broker_api_id' => $broker->id,
                            'trading_symbol' => $futSymbol->trading_symbol,
                            'interval' => '15minute',
                            'timestamp' => $timestamp->format('Y-m-d H:i:s')
                        ],
                        [
                            'underlying_symbol' => $baseSymbol,
                            'option_type' => 'FUT',
                            'strike_position' => 'FUT',
                            'strike_price' => $spotPrice,
                            'expiry' => preg_replace('/.*(\d{2}[A-Z]{3})FUT$/i', '$1', $futSymbol->trading_symbol),
                            'expiry_date' => $this->getNextExpiry($baseSymbol, $date),
                            'instrument_token' => $instrument->instrument_token,
                            'exchange' => 'NFO',
                            'lot_size' => $instrument->lot_size ?? 1,
                            'trading_date' => $date,
                            'open' => $candle->open,
                            'high' => $candle->high,
                            'low' => $candle->low,
                            'close' => $candle->close,
                            'volume' => $candle->volume,
                            'oi' => $currentOI,
                            'previous_oi' => $oiData['previous_oi'],
                            'oi_change' => $oiData['oi_change'],
                            'oi_change_percent' => $oiData['oi_change_percent'],
                            'oi_signal' => $oiData['oi_signal'],
                            'spot_price' => $spotPrice,
                            'is_active' => true,
                            'last_synced_at' => now()
                        ]
                    );

                    $insertedCount++;

                } catch (Exception $e) {
                    Log::error("Error storing FUT historical candle: " . $e->getMessage());
                }
            }

            return $insertedCount > 0;

        } catch (Exception $e) {
            Log::error("FUT historical OHLC fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetch and store CE strikes historical OHLC data
     */
    private function fetchAndStoreCEStrikesOHLCHistorical(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): bool {
        try {
            // Define strikes to fetch
            $strikes = [
                ['strike' => $atmStrike - (2 * $strikeInterval), 'position' => 'CE_ITM2'],
                ['strike' => $atmStrike - $strikeInterval, 'position' => 'CE_ITM1'],
                ['strike' => $atmStrike, 'position' => 'CE_ATM'],
                ['strike' => $atmStrike + $strikeInterval, 'position' => 'CE_OTM1'],
                ['strike' => $atmStrike + (2 * $strikeInterval), 'position' => 'CE_OTM2'],
            ];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return false;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));

            $totalInserted = 0;

            foreach ($strikes as $strikeInfo) {
                $strike = $strikeInfo['strike'];
                $position = $strikeInfo['position'];
                
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'CE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                // Fetch full day's data
                $fromTime = $date . ' 09:15:00';
                $toTime = $date . ' 15:30:00';

                usleep($this->apiCallDelay);
                
                $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                    $instrument->instrument_token,
                    '15minute',
                    $fromTime,
                    $toTime
                );

                if (empty($historicalData)) {
                    continue;
                }

                foreach ($historicalData as $candle) {
                    try {
                        $candleDate = $candle->date;

                        if ($candleDate instanceof \DateTime) {
                            $timestamp = Carbon::instance($candleDate);
                        } else {
                            $timestamp = Carbon::parse($candleDate);
                        }

                        // Market hours check
                        $time = $timestamp->format('H:i:s');
                        if ($time < '09:15:00' || $time > '15:30:00') {
                            continue;
                        }

                        // Get previous OI
                        $previousOI = OptionsOhlcData::getPreviousOI(
                            $broker->id,
                            $tradingSymbol,
                            '15minute',
                            $timestamp->format('Y-m-d H:i:s')
                        );

                        $currentOI = (int)($candle->oi ?? 0);
                        
                        $oiData = [
                            'previous_oi' => $previousOI,
                            'oi_change' => 0,
                            'oi_change_percent' => 0,
                            'oi_signal' => 'NEUTRAL'
                        ];

                        if ($previousOI !== null && $currentOI > 0) {
                            $oiChange = $currentOI - $previousOI;
                            $oiChangePct = $previousOI > 0 ? (($oiChange / $previousOI) * 100) : 0;
                            
                            $oiData = [
                                'previous_oi' => $previousOI,
                                'oi_change' => $oiChange,
                                'oi_change_percent' => $oiChangePct,
                                'oi_signal' => $oiChangePct > 5 ? 'BUILDUP' : ($oiChangePct < -5 ? 'UNWINDING' : 'NEUTRAL')
                            ];
                        }

                        OptionsOhlcData::updateOrCreate(
                            [
                                'broker_api_id' => $broker->id,
                                'trading_symbol' => $tradingSymbol,
                                'interval' => '15minute',
                                'timestamp' => $timestamp->format('Y-m-d H:i:s')
                            ],
                            [
                                'underlying_symbol' => $baseSymbol,
                                'option_type' => 'CE',
                                'strike_position' => $position,
                                'strike_price' => $strike,
                                'expiry' => $expiryCode,
                                'expiry_date' => $expiryDate,
                                'instrument_token' => $instrument->instrument_token,
                                'exchange' => 'NFO',
                                'lot_size' => $instrument->lot_size ?? 1,
                                'trading_date' => $date,
                                'open' => $candle->open,
                                'high' => $candle->high,
                                'low' => $candle->low,
                                'close' => $candle->close,
                                'volume' => $candle->volume,
                                'oi' => $currentOI,
                                'previous_oi' => $oiData['previous_oi'],
                                'oi_change' => $oiData['oi_change'],
                                'oi_change_percent' => $oiData['oi_change_percent'],
                                'oi_signal' => $oiData['oi_signal'],
                                'spot_price' => $spotPrice,
                                'is_active' => true,
                                'last_synced_at' => now()
                            ]
                        );

                        $totalInserted++;

                    } catch (Exception $e) {
                        Log::error("Error storing CE historical candle: " . $e->getMessage());
                    }
                }
            }

            return $totalInserted > 0;

        } catch (Exception $e) {
            Log::error("CE strikes historical OHLC fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetch and store PE strikes historical OHLC data
     */
    private function fetchAndStorePEStrikesOHLCHistorical(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): bool {
        try {
            // Define strikes to fetch
            $strikes = [
                ['strike' => $atmStrike + (2 * $strikeInterval), 'position' => 'PE_ITM2'],
                ['strike' => $atmStrike + $strikeInterval, 'position' => 'PE_ITM1'],
                ['strike' => $atmStrike, 'position' => 'PE_ATM'],
                ['strike' => $atmStrike - $strikeInterval, 'position' => 'PE_OTM1'],
                ['strike' => $atmStrike - (2 * $strikeInterval), 'position' => 'PE_OTM2'],
            ];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return false;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));

            $totalInserted = 0;

            foreach ($strikes as $strikeInfo) {
                $strike = $strikeInfo['strike'];
                $position = $strikeInfo['position'];
                
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'PE';
                
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    continue;
                }

                // Fetch full day's data
                $fromTime = $date . ' 09:15:00';
                $toTime = $date . ' 15:30:00';

                usleep($this->apiCallDelay);
                
                $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                    $instrument->instrument_token,
                    '15minute',
                    $fromTime,
                    $toTime
                );

                if (empty($historicalData)) {
                    continue;
                }

                foreach ($historicalData as $candle) {
                    try {
                        $candleDate = $candle->date;

                        if ($candleDate instanceof \DateTime) {
                            $timestamp = Carbon::instance($candleDate);
                        } else {
                            $timestamp = Carbon::parse($candleDate);
                        }

                        // Market hours check
                        $time = $timestamp->format('H:i:s');
                        if ($time < '09:15:00' || $time > '15:30:00') {
                            continue;
                        }

                        // Get previous OI
                        $previousOI = OptionsOhlcData::getPreviousOI(
                            $broker->id,
                            $tradingSymbol,
                            '15minute',
                            $timestamp->format('Y-m-d H:i:s')
                        );

                        $currentOI = (int)($candle->oi ?? 0);
                        
                        $oiData = [
                            'previous_oi' => $previousOI,
                            'oi_change' => 0,
                            'oi_change_percent' => 0,
                            'oi_signal' => 'NEUTRAL'
                        ];

                        if ($previousOI !== null && $currentOI > 0) {
                            $oiChange = $currentOI - $previousOI;
                            $oiChangePct = $previousOI > 0 ? (($oiChange / $previousOI) * 100) : 0;
                            
                            $oiData = [
                                'previous_oi' => $previousOI,
                                'oi_change' => $oiChange,
                                'oi_change_percent' => $oiChangePct,
                                'oi_signal' => $oiChangePct > 5 ? 'BUILDUP' : ($oiChangePct < -5 ? 'UNWINDING' : 'NEUTRAL')
                            ];
                        }

                        OptionsOhlcData::updateOrCreate(
                            [
                                'broker_api_id' => $broker->id,
                                'trading_symbol' => $tradingSymbol,
                                'interval' => '15minute',
                                'timestamp' => $timestamp->format('Y-m-d H:i:s')
                            ],
                            [
                                'underlying_symbol' => $baseSymbol,
                                'option_type' => 'PE',
                                'strike_position' => $position,
                                'strike_price' => $strike,
                                'expiry' => $expiryCode,
                                'expiry_date' => $expiryDate,
                                'instrument_token' => $instrument->instrument_token,
                                'exchange' => 'NFO',
                                'lot_size' => $instrument->lot_size ?? 1,
                                'trading_date' => $date,
                                'open' => $candle->open,
                                'high' => $candle->high,
                                'low' => $candle->low,
                                'close' => $candle->close,
                                'volume' => $candle->volume,
                                'oi' => $currentOI,
                                'previous_oi' => $oiData['previous_oi'],
                                'oi_change' => $oiData['oi_change'],
                                'oi_change_percent' => $oiData['oi_change_percent'],
                                'oi_signal' => $oiData['oi_signal'],
                                'spot_price' => $spotPrice,
                                'is_active' => true,
                                'last_synced_at' => now()
                            ]
                        );

                        $totalInserted++;

                    } catch (Exception $e) {
                        Log::error("Error storing PE historical candle: " . $e->getMessage());
                    }
                }
            }

            return $totalInserted > 0;

        } catch (Exception $e) {
            Log::error("PE strikes historical OHLC fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getHistoricalSpotPrice($zerodhaHelper, $instrumentToken, $date): ?float
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
            Log::error("Historical spot price fetch failed: " . $e->getMessage());
            return null;
        }
    }

    private function getStrikeInterval(string $baseSymbol): int
    {
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
            'HINDALCO'     => 10,
            'VEDL'         => 10,
            'DRREDDY'      => 50,
            'LICHSGFIN'    => 5,
            'TATACONSUM'   => 10,
            'ABCCAPITAL'   => 10,
            'SBIN'         => 10,
            'VBL'          => 20,
            'BAJFINANCE'   => 50,
            'TCS'          => 50,
            'COFORGE'      => 50,
            'EICHERMOT'    => 50,
            'HEROMOTOCO'   => 20,
            'AMBUJACEM'    => 5,
            'FORTIS'       => 5,
            'UPL'          => 10,
            'M&M'          => 20,
            'NATIONALUM'   => 5,
            'BPCL'         => 10,
            'ETERNAL'      => 10,
        ];

        return $strikeIntervals[$baseSymbol] ?? 100;
    }

    private function getNextExpiry(string $baseSymbol, string $date)
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

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}