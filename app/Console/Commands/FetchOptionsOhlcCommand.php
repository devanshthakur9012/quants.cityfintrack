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

class FetchOptionsOhlcCommand extends Command
{
    protected $signature = 'options:fetch-ohlc 
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force fetch even on holidays}
                            {--debug : Show detailed debug information}';

    protected $description = 'Fetch live 15-minute OHLC + OI data for FUT and OPTIONS (CE/PE strikes)';

    private $apiCallDelay = 350000; // 350ms between API calls

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        if (!$this->option('force')) {
            $isSpecialTradingSunday = ($today === '2026-02-01');
            
            if (!$isSpecialTradingSunday && ($dayName == "Saturday" || $dayName == "Sunday")) {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("Options OHLC fetch skipped: Weekend");
                return 0;
            }

            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Options OHLC fetch skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->info("🚀 Starting Live 15-Minute OHLC + OI Data Fetch");
            $this->info("   Date: {$today}");
            $this->info("   Time: " . Carbon::now()->format('H:i:s'));
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

                $result = $this->processBrokerLive($broker, $today);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Live OHLC + OI Fetch Completed!");
            $this->info("   Processed: {$totalProcessed} | Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Live Options OHLC Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBrokerLive(BrokerApi $broker, string $date)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            
            // Get all FUT symbols from symbols_monitored
            $futureSymbolsQuery = DB::table('symbols_monitored')
                ->where('broker_api_id', $broker->id)
                ->where('is_active', true)
                ->where('interval', '5minute') // We use 5min monitored symbols as base
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
                $this->warn("   ⚠️  No valid symbols found\n");
                return ['success' => 0, 'failed' => 0];
            }

            $this->info("   📊 Processing " . $validSymbols->count() . " underlying symbols");
            $this->newLine();

            foreach ($validSymbols as $futSymbol) {
                try {
                    $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
                    
                    $this->info("   └─ {$baseSymbol}");
                    
                    // Get FUT instrument
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
                    
                    // Fetch current spot price
                    $spotPrice = $this->getCurrentSpotPrice($zerodhaHelper, $futInstrument->instrument_token);
                    
                    if (!$spotPrice) {
                        if ($this->option('debug')) {
                            $this->warn("      ❌ No spot price");
                        }
                        $failed++;
                        continue;
                    }

                    // Determine strike interval
                    $strikeInterval = $this->getStrikeInterval($baseSymbol);
                    $atmStrike = round($spotPrice / $strikeInterval) * $strikeInterval;

                    // ===== FETCH OHLC DATA =====
                    
                    // 1. Fetch FUT OHLC
                    $futSuccess = $this->fetchAndStoreFutureOHLC(
                        $broker,
                        $futSymbol,
                        $zerodhaHelper,
                        $date,
                        $baseSymbol,
                        $spotPrice
                    );

                    // 2. Fetch CE strikes OHLC
                    $ceSuccess = $this->fetchAndStoreCEStrikesOHLC(
                        $broker,
                        $baseSymbol,
                        $zerodhaHelper,
                        $date,
                        $atmStrike,
                        $strikeInterval,
                        $spotPrice
                    );

                    // 3. Fetch PE strikes OHLC
                    $peSuccess = $this->fetchAndStorePEStrikesOHLC(
                        $broker,
                        $baseSymbol,
                        $zerodhaHelper,
                        $date,
                        $atmStrike,
                        $strikeInterval,
                        $spotPrice
                    );

                    if ($futSuccess || $ceSuccess || $peSuccess) {
                        $this->info("      ✓ OHLC + OI data stored");
                        $success++;
                    } else {
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

        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage() . "\n");
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Fetch and store FUT OHLC data
     */
    private function fetchAndStoreFutureOHLC(
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

            // Fetch last 2 hours of 15-minute data (to get latest candle)
            $toTime = Carbon::now('Asia/Kolkata');
            $fromTime = $toTime->copy()->subHours(2);

            usleep($this->apiCallDelay);
            
            $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                $instrument->instrument_token,
                '15minute',
                $fromTime->format('Y-m-d H:i:s'),
                $toTime->format('Y-m-d H:i:s')
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

                    // Only store candles for today
                    if ($timestamp->format('Y-m-d') !== $date) {
                        continue;
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
                    Log::error("Error storing FUT candle: " . $e->getMessage());
                }
            }

            return $insertedCount > 0;

        } catch (Exception $e) {
            Log::error("FUT OHLC fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetch and store CE strikes OHLC data
     */
    private function fetchAndStoreCEStrikesOHLC(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): bool {
        try {
            // Define strikes to fetch: ATM, ITM1, ITM2, OTM1, OTM2
            $strikes = [
                [
                    'strike' => $atmStrike - (2 * $strikeInterval),
                    'position' => 'CE_ITM2'
                ],
                [
                    'strike' => $atmStrike - $strikeInterval,
                    'position' => 'CE_ITM1'
                ],
                [
                    'strike' => $atmStrike,
                    'position' => 'CE_ATM'
                ],
                [
                    'strike' => $atmStrike + $strikeInterval,
                    'position' => 'CE_OTM1'
                ],
                [
                    'strike' => $atmStrike + (2 * $strikeInterval),
                    'position' => 'CE_OTM2'
                ],
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

                // Fetch OHLC data
                $toTime = Carbon::now('Asia/Kolkata');
                $fromTime = $toTime->copy()->subHours(2);

                usleep($this->apiCallDelay);
                
                $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                    $instrument->instrument_token,
                    '15minute',
                    $fromTime->format('Y-m-d H:i:s'),
                    $toTime->format('Y-m-d H:i:s')
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

                        // Only store candles for today
                        if ($timestamp->format('Y-m-d') !== $date) {
                            continue;
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
                        Log::error("Error storing CE candle: " . $e->getMessage());
                    }
                }
            }

            return $totalInserted > 0;

        } catch (Exception $e) {
            Log::error("CE strikes OHLC fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetch and store PE strikes OHLC data
     */
    private function fetchAndStorePEStrikesOHLC(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): bool {
        try {
            // Define strikes to fetch: ATM, ITM1, ITM2, OTM1, OTM2
            $strikes = [
                [
                    'strike' => $atmStrike + (2 * $strikeInterval),
                    'position' => 'PE_ITM2'
                ],
                [
                    'strike' => $atmStrike + $strikeInterval,
                    'position' => 'PE_ITM1'
                ],
                [
                    'strike' => $atmStrike,
                    'position' => 'PE_ATM'
                ],
                [
                    'strike' => $atmStrike - $strikeInterval,
                    'position' => 'PE_OTM1'
                ],
                [
                    'strike' => $atmStrike - (2 * $strikeInterval),
                    'position' => 'PE_OTM2'
                ],
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

                // Fetch OHLC data
                $toTime = Carbon::now('Asia/Kolkata');
                $fromTime = $toTime->copy()->subHours(2);

                usleep($this->apiCallDelay);
                
                $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                    $instrument->instrument_token,
                    '15minute',
                    $fromTime->format('Y-m-d H:i:s'),
                    $toTime->format('Y-m-d H:i:s')
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

                        // Only store candles for today
                        if ($timestamp->format('Y-m-d') !== $date) {
                            continue;
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
                        Log::error("Error storing PE candle: " . $e->getMessage());
                    }
                }
            }

            return $totalInserted > 0;

        } catch (Exception $e) {
            Log::error("PE strikes OHLC fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getCurrentSpotPrice($zerodhaHelper, $instrumentToken): ?float
    {
        try {
            $toTime = Carbon::now('Asia/Kolkata');
            $fromTime = $toTime->copy()->subMinutes(30);
            
            $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                '15minute',
                $fromTime->format('Y-m-d H:i:s'),
                $toTime->format('Y-m-d H:i:s')
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
}