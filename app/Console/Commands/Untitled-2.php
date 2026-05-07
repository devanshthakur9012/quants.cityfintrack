<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;use App\Helpers\BrokerZerodhaHelper;use App\Models\OptionStrike;use App\Models\BrokerApi;use App\Models\ZerodhaInstrument;use App\Services\OiAnalysisServiceNew;use App\Helpers\IVCalculator;use Illuminate\Support\Facades\Log;use Illuminate\Support\Facades\DB;use Carbon\Carbon;use Exception;

class FetchOptionOIHistoricalCommand extends Command
{
    protected $signature = 'options:fetch-oi-historical  {--from= : From date (Y-m-d)}  {--to= : To date (Y-m-d)} {--broker= : Specific broker ID} {--symbol= : Specific underlying symbol} {--force : Force fetch even on holidays} {--debug : Show detailed debug information}';
    protected $description = 'Fetch historical OI + IV data (FUT + merged CE/PE) per day at 3:15 PM with reversed signals';
    private $riskFreeRate = 0.06;
    private $apiCallDelay = 350000;
    private $maxRetries = 3;
    public function handle()
    {
        try {
            $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');
            $this->newLine();
            $brokersQuery = BrokerApi::zerodha()->validToken();
            if ($this->option('broker')) {$brokersQuery->where('id', $this->option('broker'));}
            $brokers = $brokersQuery->get();
            if ($brokers->isEmpty()) {return 1;}
            $totalProcessed = 0;
            $totalFailed = 0;
            foreach ($brokers as $broker) {
                $result = $this->processBrokerHistorical($broker, $fromDate, $toDate);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }
            return 0;
        } catch (Exception $e) {return 1;}
    }
    private function processBrokerHistorical(BrokerApi $broker, $fromDate, $toDate)
    {
        $success = 0;
        $failed = 0;
        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            $dateRange = $this->getTradingDays($fromDate, $toDate);
            $this->newLine();
            foreach ($dateRange as $date) {
                try {
                    $result = $this->processDailyOIAndIV($broker, $zerodhaHelper, $date);
                    $success += $result['success'];
                    $failed += $result['failed'];
                } catch (Exception $e) {$failed++;}
            }
        } catch (Exception $e) {
        }
        return ['success' => $success, 'failed' => $failed];
    }
    private function processDailyOIAndIV(BrokerApi $broker, BrokerZerodhaHelper $zerodhaHelper, string $date)
    {
        $success = 0;
        $failed = 0;
        $futureSymbolsQuery = DB::table('symbols_monitored')->where('broker_api_id', $broker->id)->where('is_active', true)->where('trading_symbol', 'LIKE', '%FUT');
        if ($this->option('symbol')) {
            $futureSymbolsQuery->where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
        }
        $futureSymbols = $futureSymbolsQuery->get();
        $validSymbols = collect();
        foreach ($futureSymbols as $futSymbol) {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
            $hasOptions = ZerodhaInstrument::where('name', $baseSymbol)->where('exchange', 'NFO')->whereIn('instrument_type', ['CE', 'PE'])->exists();
            if ($hasOptions) {$validSymbols->push($futSymbol);}
        }
        if ($validSymbols->isEmpty()) {return ['success' => 0, 'failed' => 0];}
        foreach ($validSymbols as $futSymbol) {
            try {
                $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
                $futInstrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)->where('exchange', 'NFO')->first();
                if (!$futInstrument) {
                    $failed++;
                    continue;
                }
                $spotPrice = $this->getSpotPrice($zerodhaHelper, $futInstrument->instrument_token, $date);
                if (!$spotPrice) {
                    $failed++;
                    continue;
                }
                $strikeIntervals = [
                    'NIFTY'        => 100,'BANKNIFTY'    => 100,'FINNIFTY'     => 50,'MIDCPNIFTY'   => 25,'AXISBANK'     => 10,'ICICIBANK'    => 10,'INDUSINDBK'   => 10, 'BHARTIARTL'   => 20,'SHRIRAMFIN'   => 10,'LTF'          => 5,'PAYTM'        => 20,'POLICYBZR'    => 20,'BAJAJFINSV'   => 20,'INFY'         => 20,'TATAELXSI'    => 50,'TATATECH'     => 10,'HAVELLS'      => 20,'TITAN'        => 20,'ASIANPAINT'   => 20,'TATACONSUMER' => 10,'VOLTAS'       => 20,'AUROPHARMA'   => 10,'LAURUSLABS'   => 10,'SRF'          => 20,'JSWSTEEL'     => 10,'LT'           => 20,'BHEL'         => 5,'ADANIPORTS'   => 20,'HAL'          => 50,'BDL'          => 20,'MCX'          => 20,'BSE'          => 50,'CDSL'         => 20,'LICHSG'       => 5,'DELHIVERY'    => 10,'BHARATFORG'   => 20,'PGEL'         => 10,'TMPV'         => 5,
                ];
                $strikeInterval = $strikeIntervals[$baseSymbol] ?? 100;
                $atmStrike = round($spotPrice / $strikeInterval) * $strikeInterval;
                $futOI = $this->fetchFutureOIAndIV($broker, $futSymbol, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                $ceOI = $this->fetchAndMergeCEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                $peOI = $this->fetchAndMergePEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                if ($futOI || $ceOI || $peOI) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                $failed++;
            }
        }
        return ['success' => $success, 'failed' => $failed];
    }
    private function fetchFutureOIAndIV(BrokerApi $broker,$futSymbol,BrokerZerodhaHelper $zerodhaHelper,string $date,string $baseSymbol,float $spotPrice,int $atmStrike
    ): bool {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)->where('exchange', 'NFO')->first();
            if (!$instrument) {
                return false;
            }
            $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
            if (!$eodData || !isset($eodData['oi'])) {
                return false;
            }
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'FUT', $date);
            $analysis = OiAnalysisServiceNew::analyzeFuturesOI($eodData['oi'], $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseFutSignal($analysis['direction']);
            $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, 'FUT', $date);
            OptionStrike::updateOrCreate(
                [ 'broker_api_id' => $broker->id, 'underlying_symbol' => $baseSymbol, 'trading_symbol' => $futSymbol->trading_symbol, 'strike_position' => 'FUT', 'trading_date' => $date
                ],
                [ 'option_type' => 'FUT', 'strike_price' => $spotPrice, 'expiry' => preg_replace('/.*(\d{2}[A-Z]{3})FUT$/i', '$1', $futSymbol->trading_symbol), 'expiry_date' => $this->getNextExpiry($baseSymbol, $date), 'instrument_token' => $instrument->instrument_token, 'exchange' => 'NFO', 'lot_size' => $instrument->lot_size ?? 1, 'is_active' => true, 'daily_oi' => $analysis['daily_oi'], 'daily_oi_prev' => $analysis['daily_oi_prev'], 'daily_oi_change' => $analysis['daily_oi_change'], 'daily_oi_change_pct' => $analysis['daily_oi_change_pct'], 'direction' => $reversedDirection, 'strength' => $analysis['strength'], 'market_bias' => $analysis['market_bias'] ?? null, 'daily_iv' => null, 'daily_iv_prev' => null, 'daily_iv_change' => null, 'daily_iv_change_pct' => null, 'spot_price' => $spotPrice, 'last_synced_at' => now()
                ]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    private function fetchAndMergeCEOIAndIV(BrokerApi $broker,string $baseSymbol,BrokerZerodhaHelper $zerodhaHelper,string $date,int $atmStrike,int $strikeInterval,float $spotPrice
    ): bool {
        try {
            $strikes = [ $atmStrike - (2 * $strikeInterval), $atmStrike - $strikeInterval, $atmStrike, $atmStrike + $strikeInterval, $atmStrike + (2 * $strikeInterval)
            ];
            $totalOI = 0;
            $totalIV = 0;
            $ivCount = 0;
            $ceInstruments = [];
            $strikeResults = [];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return false;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
            if ($daysToExpiry == 0) $daysToExpiry = 1;
            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'CE';
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)->where('exchange', 'NFO')->first();
                if (!$instrument) {
                    $strikeResults[$strike] = 'NOT_FOUND_IN_DB';
                    continue;
                }
                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                if (!$eodData) {
                    $strikeResults[$strike] = 'NO_EOD_DATA_AFTER_RETRY';
                    continue;
                }
                if (!isset($eodData['oi']) || !isset($eodData['close'])) {
                    $strikeResults[$strike] = 'MISSING_FIELDS';
                    continue;
                }
                $oi = $eodData['oi'] ?? 0;
                $close = $eodData['close'] ?? 0;
                if ($close <= 0) {
                    $strikeResults[$strike] = 'INVALID_PRICE';
                    continue;
                }
                $totalOI += $oi; 
                $ceInstruments[] = $tradingSymbol;
                if ($eodData['close'] > 0) {
                    $iv = IVCalculator::calculate(
                        $close,   $spotPrice, $strike, $daysToExpiry, 'CE', $this->riskFreeRate
                    );
                    if ($iv !== null) { $totalIV += $iv; $ivCount++;
                    }
                }
                $strikeResults[$strike] = 'INCLUDED';
            }
            if ($totalOI == 0) {
                return false;
            }
            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'CE_MERGED', $date);
            $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, 'CE_MERGED', $date);
            $analysis = OiAnalysisServiceNew::analyzeCallOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseCESignal($analysis['direction']);
            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
            }
            OptionStrike::updateOrCreate(
                [ 'broker_api_id' => $broker->id, 'underlying_symbol' => $baseSymbol, 'strike_position' => 'CE_MERGED', 'trading_date' => $date
                ],
                [ 'trading_symbol' => implode(',', $ceInstruments), 'option_type' => 'CE', 'strike_price' => $atmStrike, 'expiry' => $expiryCode, 'expiry_date' => $expiryDate, 'instrument_token' => null, 'exchange' => 'NFO', 'lot_size' => 1, 'is_active' => true, 'daily_oi' => $analysis['daily_oi'], 'daily_oi_prev' => $analysis['daily_oi_prev'], 'daily_oi_change' => $analysis['daily_oi_change'], 'daily_oi_change_pct' => $analysis['daily_oi_change_pct'], 'direction' => $reversedDirection, 'strength' => $analysis['strength'], 'daily_iv' => $avgIV, 'daily_iv_prev' => $prevDayIV, 'daily_iv_change' => $ivChange, 'daily_iv_change_pct' => $ivChangePct, 'spot_price' => $spotPrice, 'last_synced_at' => now()
                ]
            );
            return true;
        } catch (Exception $e) {
            Log::error("CE merge failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }
    private function fetchAndMergePEOIAndIV(BrokerApi $broker,string $baseSymbol,BrokerZerodhaHelper $zerodhaHelper,string $date,int $atmStrike,int $strikeInterval,float $spotPrice
    ): bool {
        try {
            $strikes = [ $atmStrike - (2 * $strikeInterval), $atmStrike - $strikeInterval, $atmStrike, $atmStrike + $strikeInterval, $atmStrike + (2 * $strikeInterval)
            ];
            $totalOI = 0;
            $totalIV = 0;
            $ivCount = 0;
            $peInstruments = [];
            $strikeResults = [];
            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return false;
            }
            $expiryCode = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date));
            if ($daysToExpiry == 0) $daysToExpiry = 1;
            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int)$strike) . 'PE';
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)->where('exchange', 'NFO')->first();
                if (!$instrument) {
                    $strikeResults[$strike] = 'NOT_FOUND_IN_DB';
                    continue;
                }
                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                if (!$eodData) {
                    $strikeResults[$strike] = 'NO_EOD_DATA_AFTER_RETRY';
                    continue;
                }
                if (!isset($eodData['oi']) || !isset($eodData['close'])) {
                    $strikeResults[$strike] = 'MISSING_FIELDS';
                    continue;
                }
                $oi = $eodData['oi'] ?? 0;
                $close = $eodData['close'] ?? 0;
                if ($close <= 0) {
                    $strikeResults[$strike] = 'INVALID_PRICE';
                    continue;
                }
                $totalOI += $oi; 
                $peInstruments[] = $tradingSymbol;
                if ($close > 0) {
                    $iv = IVCalculator::calculate( $close,   $spotPrice, $strike, $daysToExpiry, 'PE', $this->riskFreeRate
                    );

                    if ($iv !== null) {
                        $totalIV += $iv;
                        $ivCount++;
                    }
                }
                $strikeResults[$strike] = 'INCLUDED';
            }
            if ($totalOI == 0) {
                return false;
            }
            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'PE_MERGED', $date);
            $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, 'PE_MERGED', $date);
            $analysis = OiAnalysisServiceNew::analyzePutOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reversePESignal($analysis['direction']);
            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
            }
            OptionStrike::updateOrCreate(
                [ 'broker_api_id' => $broker->id, 'underlying_symbol' => $baseSymbol, 'strike_position' => 'PE_MERGED', 'trading_date' => $date
                ],
                [ 'trading_symbol' => implode(',', $peInstruments), 'option_type' => 'PE', 'strike_price' => $atmStrike, 'expiry' => $expiryCode, 'expiry_date' => $expiryDate, 'instrument_token' => null, 'exchange' => 'NFO', 'lot_size' => 1, 'is_active' => true, 'daily_oi' => $analysis['daily_oi'], 'daily_oi_prev' => $analysis['daily_oi_prev'], 'daily_oi_change' => $analysis['daily_oi_change'], 'daily_oi_change_pct' => $analysis['daily_oi_change_pct'], 'direction' => $reversedDirection, 'strength' => $analysis['strength'], 'daily_iv' => $avgIV, 'daily_iv_prev' => $prevDayIV, 'daily_iv_change' => $ivChange, 'daily_iv_change_pct' => $ivChangePct, 'spot_price' => $spotPrice, 'last_synced_at' => now()
                ]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    private function fetchEODDataWithRetry($zerodhaHelper, int $instrumentToken, string $date): ?array
    {
        $attempt = 0;
        while ($attempt < $this->maxRetries) {
            try {
                if ($attempt > 0) {
                    $waitTime = $this->apiCallDelay * pow(2, $attempt); // Exponential backoff
                    usleep($waitTime);
                } else {
                    usleep($this->apiCallDelay);
                }
                $data = $this->fetchEODData($zerodhaHelper, $instrumentToken, $date);
                if ($data) {
                    if ($attempt > 0) {
                    }
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
    private function reverseFutSignal($signal)
    {
        if (!$signal || $signal === 'N/A' || $signal === 'NEUTRAL') {
            return 'NEUTRAL';
        }
        if ($signal === 'BUILDUP') {
           return $signal = 'BULLISH';
        } elseif ($signal === 'UNWINDING') {
           return $signal = 'BEARISH';
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
    private function fetchEODData($zerodhaHelper, int $instrumentToken, string $date): ?array
    {
        try {
            // For daily data at 3:15 PM, we need data from previous day 3:15 PM to current day 3:15 PM
            $startDateTime = Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:15:00';
            $endDateTime = $date . ' 15:15:00';
            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',  // ✅ DAILY timeframe as requested
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
            throw $e;
        }
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
            $prevRecord = OptionStrike::where('broker_api_id', $broker->id)->where('underlying_symbol', $baseSymbol)->where('strike_position', $position)->where('trading_date', $prevDate->format('Y-m-d'))->first();
            if ($prevRecord && $prevRecord->daily_iv > 0) {
                return $prevRecord->daily_iv;
            }
            $prevDate->subDay();
            $attempts++;
        }
        return 0.0;
    }
    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
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
                $price = $lastCandle->close ?? null;
                return $price;
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    private function fetchHistoricalPrice($zerodhaHelper, $futSymbol, string $date): ?float
    {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)->where('exchange', 'NFO')->first();
            if (!$instrument) {
                return null;
            }
            // Use the same daily interval approach
            $startDateTime = Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:15:00';
            $endDateTime = $date . ' 15:15:00';
            $historicalData = $zerodhaHelper->getHistoricalDataByToken(
                $instrument->instrument_token,
                'day',  // Changed from 'day' to match new approach
                $startDateTime,
                $endDateTime
            );
            if (!empty($historicalData)) {
                $lastCandle = end($historicalData);
                return $lastCandle->close ?? null;
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    private function getNextExpiry(string $baseSymbol, string $date): ?Carbon
    {
        $expiry = ZerodhaInstrument::where('name', $baseSymbol)->where('exchange', 'NFO')->where('instrument_type', 'CE')->whereDate('expiry', '>=', $date)->orderBy('expiry', 'ASC')->first();
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
}  <?php

namespace App\Services;

class OiAnalysisServiceNew
{
    public static function analyzeFuturesOI(
        int $todayOI,
        int $yesterdayOI,
        string $symbol
    ): array {
        $delta = $todayOI - $yesterdayOI;  
        $deltaPct = $yesterdayOI > 0 ? ($delta / $yesterdayOI) * 100 : 0;

        $direction = match(true) {
            $delta > 0 => 'BUILDUP',
            $delta < 0 => 'UNWINDING',
            default => 'NEUTRAL'
        };

        $strength = self::calculateStrength(
            abs($delta),
            self::getFuturesThresholds($symbol)
        );

        return [
            'daily_oi' => $todayOI,
            'daily_oi_prev' => $yesterdayOI,
            'daily_oi_change' => $delta,
            'daily_oi_change_pct' => round($deltaPct, 2),
            'direction' => $direction,
            'strength' => $strength
        ];
    }
    public static function analyzeCallOptionsOI(
        int $todayOISum,
        int $yesterdayOISum,
        string $symbol
    ): array {
        $delta = $todayOISum - $yesterdayOISum;
        $deltaPct = $yesterdayOISum > 0 ? ($delta / $yesterdayOISum) * 100 : 0;
        $direction = match(true) {
            $delta > 0 => 'BEARISH', 
            $delta < 0 => 'BULLISH', 
            default => 'NEUTRAL'
        };

        $strength = self::calculateStrength(
            abs($delta),
            self::getOptionsThresholds($symbol)
        );

        return [
            'daily_oi' => $todayOISum,
            'daily_oi_prev' => $yesterdayOISum,
            'daily_oi_change' => $delta,
            'daily_oi_change_pct' => round($deltaPct, 2),
            'direction' => $direction,
            'strength' => $strength
        ];
    }
    public static function analyzePutOptionsOI(
        int $todayOISum,
        int $yesterdayOISum,
        string $symbol
    ): array {
        $delta = $todayOISum - $yesterdayOISum;
        $deltaPct = $yesterdayOISum > 0 ? ($delta / $yesterdayOISum) * 100 : 0;
        $direction = match(true) {
            $delta > 0 => 'BULLISH', // Put writing
            $delta < 0 => 'BEARISH', // Put unwinding
            default => 'NEUTRAL'
        };

        $strength = self::calculateStrength(
            abs($delta),
            self::getOptionsThresholds($symbol)
        );

        return [
            'daily_oi' => $todayOISum,
            'daily_oi_prev' => $yesterdayOISum,
            'daily_oi_change' => $delta,
            'daily_oi_change_pct' => round($deltaPct, 2),
            'direction' => $direction,
            'strength' => $strength
        ];
    }
    public static function calculateMarketBias(
        array $futAnalysis,
        array $ceAnalysis,
        array $peAnalysis
    ): string {
        $futDir = $futAnalysis['direction'];
        $futStr = $futAnalysis['strength'];
        $ceDir = $ceAnalysis['direction'];
        $peDir = $peAnalysis['direction'];

        if (
            $futDir === 'BUILDUP' &&
            $ceDir === 'BULLISH' &&
            $peDir === 'BULLISH' &&
            $futStr !== 'WEAK'
        ) {
            return 'STRONG_BULLISH_' . $futStr;
        }
        if (
            $futDir === 'BUILDUP' &&
            $ceDir === 'BEARISH' &&
            $peDir === 'BEARISH' &&
            $futStr !== 'WEAK'
        ) {
            return 'STRONG_BEARISH_' . $futStr;
        }
        $bullishCount = 0;
        if ($futDir === 'BUILDUP') $bullishCount++;
        if ($ceDir === 'BULLISH') $bullishCount++;
        if ($peDir === 'BULLISH') $bullishCount++;

        if ($bullishCount >= 2) {
            return 'MODERATE_BULLISH';
        }
        $bearishCount = 0;
        if ($futDir === 'UNWINDING') $bearishCount++;
        if ($ceDir === 'BEARISH') $bearishCount++;
        if ($peDir === 'BEARISH') $bearishCount++;

        if ($bearishCount >= 2) {
            return 'MODERATE_BEARISH';
        }

        return 'MIXED_OR_RANGE';
    }
    private static function calculateStrength(int $absDelta, array $thresholds): string
    {
        if ($absDelta < $thresholds[0]) {
            return 'WEAK';
        } elseif ($absDelta < $thresholds[1]) {
            return 'MODERATE';
        } elseif ($absDelta < $thresholds[2]) {
            return 'STRONG';
        } else {
            return 'VERY_STRONG';
        }
    }
    private static function getFuturesThresholds(string $symbol): array
    {
        return match($symbol) {
            'NIFTY' => [5000, 20000, 50000],
            'BANKNIFTY' => [3000, 12000, 30000],
            'FINNIFTY' => [2000, 8000, 20000],
            'MIDCPNIFTY' => [2000, 8000, 20000],
            default => [5000, 20000, 50000]
        };
    }
    private static function getOptionsThresholds(string $symbol): array
    {
        return match($symbol) {
            'NIFTY' => [10000, 40000, 100000],
            'BANKNIFTY' => [8000, 30000, 80000],
            'FINNIFTY' => [5000, 20000, 50000],
            'MIDCPNIFTY' => [5000, 20000, 50000],
            default => [10000, 40000, 100000]
        };
    }
} okie this is my code and from here im make that data.. so what i want is so basicaly we have 2 things in this table : oi data and iv data... so, if you see we have already make signal bases on oi data okie. (you can also check and tell me how was this logic as well) and our main thing is we need to make a signal bases on iv data as well.. okie -- so that we have 2 differnt signal to take good trade hope you got it.. okie : and what was my whole idea is so this is the data i collected from 25 Jan to 3 FEB okie so  my idea is to place order bases on this.. so currenlty we are fetching this oi at 3:00 every day by cron okie..   $schedule->command('options:fetch-oi')
        ->dailyAt('15:00')
        ->timezone('Asia/Kolkata')
        ->when(function () {
            $currentTime = Carbon::now('Asia/Kolkata');
            
            // Check if it's a trading day (not weekend/holiday)
            if ($currentTime->isWeekend()) {
                return false;
            }
            
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $currentTime->format('Y-m-d'))
                ->exists();
            
            return !$isHoliday;
        })
        ->withoutOverlapping(30)
        ->sendOutputTo(storage_path('logs/option_oi_iv_fetch_daily.log'))
        ->appendOutputTo(storage_path('logs/option_oi_iv_fetch_all.log'));
    } 

class FetchOptionOICommand extends Command
{
    protected $signature = 'options:fetch-oi 
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force fetch even on holidays}
                            {--debug : Show detailed debug information}';

    protected $description = 'Fetch daily EOD OI + IV data (FUT + merged CE/PE) at 3:15 PM with reversed signals';

    private $riskFreeRate = 0.06; // 6% default
    private $apiCallDelay = 350000; // 350ms delay between API calls
    private $maxRetries = 3; // Maximum retry attempts

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        // Holiday check (unless forced)
        if (!$this->option('force')) {
            // ✅ Special handling for trading Sundays (like Feb 1, 2026)
            $isSpecialTradingSunday = ($today === '2026-02-01');
            
            if (!$isSpecialTradingSunday && ($dayName == "Saturday" || $dayName == "Sunday")) {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("Option OI+IV fetch skipped: Weekend");
                return 0;
            }

            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Option OI+IV fetch skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->info("🚀 Starting Daily EOD OI + IV Fetch at 3:15 PM");
            $this->info("   Date: {$today}");
            $this->info("   Time: " . Carbon::now()->format('H:i:s'));
            $this->info("   Risk-Free Rate: " . ($this->riskFreeRate * 100) . "%");
            $this->info("   Signals: REVERSED (BULLISH ↔ BEARISH)");
            $this->newLine();

            // Get active brokers
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
            $this->info("✅ Daily EOD OI + IV Fetch Completed!");
            $this->info("   Total Processed: {$totalProcessed} symbols");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Daily EOD OI+IV Fetch Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processBrokerDaily(BrokerApi $broker, string $date)
    {
        $success = 0;
        $failed = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            $this->info("   📅 Processing EOD OI + IV for: {$date}");
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
                
                // ✅ Get FUT instrument for spot price
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
                    'LAURUSLABS'   => 5,
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
                    'PGEL'         => 5,
                    'TMPV'         => 5,
                ];
                
                $strikeInterval = $strikeIntervals[$baseSymbol] ?? 100;
                $atmStrike = round($spotPrice / $strikeInterval) * $strikeInterval;

                // Fetch FUT, CE, PE data (OI + IV)
                $futOI = $this->fetchFutureOIAndIV($broker, $futSymbol, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                $ceOI = $this->fetchAndMergeCEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                $peOI = $this->fetchAndMergePEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);

                if ($futOI || $ceOI || $peOI) {
                    $this->info("      ✓ Stored FUT/CE/PE EOD OI + IV");
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

    private function fetchFutureOIAndIV(
        BrokerApi $broker,
        $futSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        string $baseSymbol,
        float $spotPrice,
        int $atmStrike
    ): bool {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)
                ->where('exchange', 'NFO')
                ->first();

            if (!$instrument) {
                return false;
            }

            $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
            
            if (!$eodData || !isset($eodData['oi'])) {
                return false;
            }

            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'FUT', $date);
            $analysis = OiAnalysisServiceNew::analyzeFuturesOI($eodData['oi'], $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseFutSignal($analysis['direction']);

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
                    'daily_oi' => $analysis['daily_oi'],
                    'daily_oi_prev' => $analysis['daily_oi_prev'],
                    'daily_oi_change' => $analysis['daily_oi_change'],
                    'daily_oi_change_pct' => $analysis['daily_oi_change_pct'],
                    'direction' => $reversedDirection,
                    'strength' => $analysis['strength'],
                    'market_bias' => $analysis['market_bias'] ?? null,
                    'daily_iv' => null,
                    'daily_iv_prev' => null,
                    'daily_iv_change' => null,
                    'daily_iv_change_pct' => null,
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return true;

        } catch (Exception $e) {
            Log::error("FUT OI+IV fetch failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function fetchAndMergeCEOIAndIV(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): bool {
        try {
            $strikes = [
                $atmStrike - (2 * $strikeInterval),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                $atmStrike + (2 * $strikeInterval)
            ];

            $totalOI = 0;
            $totalIV = 0;
            $ivCount = 0;
            $ceInstruments = [];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return false;
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
                
                if (!$eodData) {
                    continue;
                }

                // ✅ Handle null OI gracefully
                if (!isset($eodData['oi']) || !isset($eodData['close'])) {
                    continue;
                }

                $oi = $eodData['oi'] ?? 0;
                $close = $eodData['close'] ?? 0;

                if ($close <= 0) {
                    continue;
                }

                $totalOI += $oi;
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
                return false;
            }

            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'CE_MERGED', $date);
            $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, 'CE_MERGED', $date);
            $analysis = OiAnalysisServiceNew::analyzeCallOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseCESignal($analysis['direction']);

            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
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
                    'daily_oi' => $analysis['daily_oi'],
                    'daily_oi_prev' => $analysis['daily_oi_prev'],
                    'daily_oi_change' => $analysis['daily_oi_change'],
                    'daily_oi_change_pct' => $analysis['daily_oi_change_pct'],
                    'direction' => $reversedDirection,
                    'strength' => $analysis['strength'],
                    'daily_iv' => $avgIV,
                    'daily_iv_prev' => $prevDayIV,
                    'daily_iv_change' => $ivChange,
                    'daily_iv_change_pct' => $ivChangePct,
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return true;

        } catch (Exception $e) {
            Log::error("CE merge failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function fetchAndMergePEOIAndIV(
        BrokerApi $broker,
        string $baseSymbol,
        BrokerZerodhaHelper $zerodhaHelper,
        string $date,
        int $atmStrike,
        int $strikeInterval,
        float $spotPrice
    ): bool {
        try {
            $strikes = [
                $atmStrike - (2 * $strikeInterval),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                $atmStrike + (2 * $strikeInterval)
            ];

            $totalOI = 0;
            $totalIV = 0;
            $ivCount = 0;
            $peInstruments = [];

            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) {
                return false;
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
                
                if (!$eodData) {
                    continue;
                }

                // ✅ Handle null OI gracefully
                if (!isset($eodData['oi']) || !isset($eodData['close'])) {
                    continue;
                }

                $oi = $eodData['oi'] ?? 0;
                $close = $eodData['close'] ?? 0;

                if ($close <= 0) {
                    continue;
                }

                $totalOI += $oi;
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
                return false;
            }

            $avgIV = $ivCount > 0 ? ($totalIV / $ivCount) : null;
            $prevDayOI = $this->getPreviousDayOI($broker, $baseSymbol, 'PE_MERGED', $date);
            $prevDayIV = $this->getPreviousDayIV($broker, $baseSymbol, 'PE_MERGED', $date);
            $analysis = OiAnalysisServiceNew::analyzePutOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reversePESignal($analysis['direction']);

            $ivChange = null;
            $ivChangePct = null;
            if ($avgIV !== null && $prevDayIV > 0) {
                $ivChange = $avgIV - $prevDayIV;
                $ivChangePct = ($ivChange / $prevDayIV) * 100;
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
                    'daily_oi' => $analysis['daily_oi'],
                    'daily_oi_prev' => $analysis['daily_oi_prev'],
                    'daily_oi_change' => $analysis['daily_oi_change'],
                    'daily_oi_change_pct' => $analysis['daily_oi_change_pct'],
                    'direction' => $reversedDirection,
                    'strength' => $analysis['strength'],
                    'daily_iv' => $avgIV,
                    'daily_iv_prev' => $prevDayIV,
                    'daily_iv_change' => $ivChange,
                    'daily_iv_change_pct' => $ivChangePct,
                    'spot_price' => $spotPrice,
                    'last_synced_at' => now()
                ]
            );

            return true;

        } catch (Exception $e) {
            Log::error("PE merge failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return false;
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
            // ✅ Use daily interval like historical command
            $startDateTime = Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:15:00';
            $endDateTime = $date . ' 15:15:00';

            $data = $zerodhaHelper->getHistoricalDataByToken(
                $instrumentToken,
                'day',  // ✅ Daily interval
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
} so what i want in real time so today is 3 Feb and we have today data already so next day when market open at 4FEB this 3 Pm cron will run and fetch the oi data and iv data (confrim that this safelt fetch the data as well) and then we got both signal from iv and oi on that bases we have to place orders.. i hope you got it.. this is my complete statergy