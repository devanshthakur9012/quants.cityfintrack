<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\OiivOrderBook;
use App\Models\OiivPosition;
use App\Models\OptionOhlcData;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * PECEAutoTradingHelper
 *
 * EXPIRY DAY FIX (all three bugs corrected):
 *
 * Bug 1 — resolveDataSeries() returned March on expiry day
 *   Fix: Mirror resolveInstrumentSeries() — on expiry day use '>' comparator
 *        so dataSeries always points to the NEXT (active) series.
 *
 * Bug 2 — getNearestExpiryForDate() returned March (expiring) on expiry day
 *   Fix: resolveActiveExpiry() wraps getNearestExpiryForDate() — if the
 *        resolved expiry == today, shift to next series.
 *        Also normalise all DB::raw date returns with substr(,0,10).
 *
 * Bug 3 — prevExpiry on expiry day compared April OI vs March's prev-day OI
 *   Fix: On expiry day $prevExpiry = null → ceOiPct/peOiPct = 0 → signal
 *        falls back to price direction only (correct — April series is brand new).
 *
 * ORDER BOOK INTEGRATION:
 *   All orders are now written to oiiv_order_book (OiivOrderBook model).
 *   The SyncOiivOrders command polls Zerodha and updates status + creates
 *   OiivPosition rows when orders complete.
 *   saveToOrderBook() / saveFailedOrder() are replaced by
 *   placeOrderForAutoOrder() which writes directly to oiiv_order_book.
 */
class PECEAutoTradingHelper
{
    // EOD timeframe
    const OPEN_TIME_HOUR    = 15;
    const OPEN_TIME_MINUTE  = 0;
    const CLOSE_TIME_HOUR   = 14;
    const CLOSE_TIME_MINUTE = 45;

    const MA_PERIOD = 50;

    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'SENSEX', 'BANKEX'];

    private $kiteInstances = [];

    // ── Rate-limiting state ───────────────────────────────────────────────
    private array $lastRequestTime      = [];
    private int   $minMsBetweenRequests = 350;
    private array $ltpCache             = [];

    const FREEZE_LIMITS = [
        'NIFTY'       => 18,  'BANKNIFTY'  => 20,  'FINNIFTY'   => 24,  'MIDCPNIFTY' => 24,
        'ADANIPORTS'  => 30,  'AMBUJACEM'  => 40,  'ASIANPAINT' => 40,  'AUROPHARMA' => 40,
        'AXISBANK'    => 30,  'BAJAJFINSV' => 50,  'BAJFINANCE' => 30,  'BHARATFORG' => 30,
        'BHARTIARTL'  => 30,  'BHEL'       => 30,  'BPCL'       => 30,  'BSE'        => 20,
        'CDSL'        => 30,  'COFORGE'    => 30,  'BDL'        => 40,  'DELHIVERY'  => 30,
        'DRREDDY'     => 30,  'ETERNAL'    => 30,  'FORTIS'     => 40,  'HAL'        => 40,
        'HAVELLS'     => 30,  'HEROMOTOCO' => 30,  'HINDALCO'   => 40,  'ICICIBANK'  => 30,
        'INDUSINDBK'  => 40,  'INFY'       => 40,  'JSWSTEEL'   => 30,  'LAURUSLABS' => 30,
        'LICHSGFIN'   => 40,  'LT'         => 40,  'LTF'        => 40,  'M&M'        => 30,
        'NATIONALUM'  => 20,  'PAYTM'      => 30,  'PGEL'       => 40,  'POLICYBZR'  => 40,
        'SBIN'        => 30,  'SHRIRAMFIN' => 30,  'SRF'        => 40,  'TATACONSUM' => 40,
        'TATAELXSI'   => 40,  'TATATECH'   => 50,  'TITAN'      => 30,  'TMPV'       => 50,
        'TCS'         => 40,  'UPL'        => 30,  'VBL'        => 40,  'VEDL'       => 30,
        'VOLTAS'      => 40,  'MCX'        => 20,  'CHOLAFIN'   => 20,  'TECHM'      => 30,
        'SBICARD'     => 40,  'SBILIFE'    => 30,
    ];

    const STRIKE_INTERVALS = [
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
        'TATACONSUM'   => 10,
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
        'LICHSGFIN'    => 5,
        'DELHIVERY'    => 10,
        'BHARATFORG'   => 20,
        'PGEL'         => 10,
        'TMPV'         => 5,
        'HINDALCO'     => 10,
        'VEDL'         => 10,
        'DRREDDY'      => 50,
        'HEROMOTOCO'   => 20,
        'AMBUJACEM'    => 5,
        'FORTIS'       => 5,
        'UPL'          => 10,
        'M&M'          => 20,
        'NATIONALUM'   => 5,
        'BPCL'         => 10,
        'ETERNAL'      => 10,
        'SBIN'         => 10,
        'VBL'          => 20,
        'BAJFINANCE'   => 50,
        'TCS'          => 50,
        'COFORGE'      => 50,
        'EICHERMOT'    => 50,
        'ABCCAPITAL'   => 10,
    ];

    // =========================================================
    //  RATE LIMITING HELPERS
    // =========================================================

    private function throttleRequest(int $brokerId): void
    {
        if (isset($this->lastRequestTime[$brokerId])) {
            $elapsedMs = (int) ((microtime(true) - $this->lastRequestTime[$brokerId]) * 1000);
            if ($elapsedMs < $this->minMsBetweenRequests) {
                usleep(($this->minMsBetweenRequests - $elapsedMs) * 1000);
            }
        }
        $this->lastRequestTime[$brokerId] = microtime(true);
    }

    private function isRateLimitError(\Exception $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'rate')
            || str_contains($msg, '429')
            || str_contains($msg, 'too many')
            || str_contains($msg, 'throttle');
    }

    private function getExchangeForSymbol(string $baseSymbol): string
    {
        return in_array(strtoupper($baseSymbol), ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
    }

    private function prefetchLTPs(BrokerApi $broker, array $optionSymbols): void
    {
        if (empty($optionSymbols)) return;

        $this->ensureKiteInstance($broker);
        $kite       = $this->kiteInstances[$broker->id];
        $chunks     = array_chunk(array_unique($optionSymbols), 500);
        $maxRetries = 3;

        foreach ($chunks as $chunk) {
            $attempt = 0;
            while ($attempt < $maxRetries) {
                try {
                    $this->throttleRequest($broker->id);
                    $keys = array_map(function ($s) {
                        $base = preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $s);
                        $base = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $base);
                        $exch = $this->getExchangeForSymbol(strtoupper($base));
                        return "{$exch}:{$s}";
                    }, $chunk);

                    $quotes = $kite->getQuote($keys);
                    $arr    = json_decode(json_encode($quotes), true);

                    foreach ($arr as $key => $q) {
                        $sym = preg_replace('/^[A-Z]+:/', '', $key);
                        $this->ltpCache[$broker->id][$sym] = (float) ($q['last_price'] ?? 0);
                    }

                    Log::info(sprintf(
                        "EOD prefetchLTPs broker=%d | fetched %d symbols",
                        $broker->id, count($chunk)
                    ));
                    break;

                } catch (\Exception $e) {
                    $attempt++;
                    if ($this->isRateLimitError($e) && $attempt < $maxRetries) {
                        $wait = (int) pow(2, $attempt);
                        Log::warning("EOD prefetchLTPs rate-limited — retry {$attempt}/{$maxRetries} in {$wait}s");
                        sleep($wait);
                    } else {
                        Log::error("EOD prefetchLTPs chunk failed (attempt {$attempt}): " . $e->getMessage());
                        break;
                    }
                }
            }
        }
    }

    // =========================================================
    //  PUBLIC ENTRY POINTS
    // =========================================================

    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== EOD: Starting CE/PE OI Change Auto Trading Signal Detection ===');

            $processingDate = $testDate
                ? Carbon::parse($testDate . ' 15:30:00', 'Asia/Kolkata')
                : Carbon::now('Asia/Kolkata');

            $mode        = $testDate ? 'TEST' : 'LIVE';
            $currentDate = $processingDate->format('Y-m-d');

            Log::info("{$mode} MODE - Processing Time: " . $processingDate->format('Y-m-d H:i:s'));

            $configs = OIIVAutoConfig::where('status', true)
                ->where('config_type', 'eod')
                ->get();

            if ($configs->isEmpty()) {
                Log::info('No active EOD configurations found');
                return;
            }

            Log::info("Found {$configs->count()} active EOD config(s) | Date: {$currentDate}");

            $dataSeries       = $this->resolveDataSeries($currentDate);
            $instrumentSeries = $this->resolveInstrumentSeries($currentDate);

            if (!$dataSeries) {
                Log::warning("EOD: Could not resolve data series for {$currentDate} — aborting");
                return;
            }
            if (!$instrumentSeries) {
                Log::warning("EOD: Could not resolve instrument series for {$currentDate} — aborting");
                return;
            }

            $prevDate = $this->getPreviousTradingDate($currentDate);

            Log::info(sprintf(
                "DataSeries: %s | InstrumentSeries: %s | PrevDate: %s",
                $dataSeries, $instrumentSeries, $prevDate
            ));

            $aggregatedSignals = $this->aggregateSignalsFromOhlc($currentDate, $prevDate, $dataSeries);

            if (empty($aggregatedSignals)) {
                Log::warning("No EOD signal data found for date={$currentDate}");
                return;
            }

            $aligned = array_filter($aggregatedSignals, fn($s) => $s['is_aligned']);
            Log::info(sprintf(
                "Aggregated %d symbol signals | %d sentiment-eligible | %d skipped (NEUTRAL/WAIT)",
                count($aggregatedSignals), count($aligned),
                count($aggregatedSignals) - count($aligned)
            ));

            foreach ($configs as $config) {
                Log::info("Config ID: {$config->id} | User: {$config->user_id} | Mode: {$config->signal_mode}");

                $broker = $config->broker;
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Config {$config->id}: Invalid broker token - skipping");
                    continue;
                }

                $this->ensureKiteInstance($broker);
                $this->prefetchATMOptionLTPs($config, $broker, $aggregatedSignals, $instrumentSeries);

                $this->processConfigSignals(
                    $config, $aggregatedSignals, $broker,
                    $currentDate, $processingDate, $instrumentSeries
                );
            }

            Log::info('=== EOD: Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('EOD processSignals Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    private function prefetchATMOptionLTPs(
        OIIVAutoConfig $config,
        BrokerApi $broker,
        array $aggregatedSignals,
        string $instrumentSeries
    ): void {
        $symbolsToFetch = [];

        foreach ($aggregatedSignals as $symbol => $signalData) {
            if (!$signalData['is_aligned']) continue;

            $direction     = $signalData['final_sentiment'];
            $rawOptionType = $direction === 'BULLISH' ? 'CE' : 'PE';
            $finalOT       = $config->shouldReverseSignal()
                ? ($rawOptionType === 'CE' ? 'PE' : 'CE')
                : $rawOptionType;

            $currentPrice = $signalData['current_close'] ?? 0;
            if ($currentPrice <= 0) continue;

            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $signalData['trading_symbol']);
            $interval   = self::STRIKE_INTERVALS[$baseSymbol] ?? 20;
            $atmStrike  = round($currentPrice / $interval) * $interval;
            $atmStrike  = $finalOT === 'CE' ? $atmStrike + $interval : $atmStrike - $interval;

            $symbolExpiry = $this->resolveSymbolInstrumentExpiry($baseSymbol, $instrumentSeries);
            if (!$symbolExpiry) continue;

            $targetExpiry = $symbolExpiry;
            if ($config->useNextSeries()) {
                $isWeekly     = ($baseSymbol === 'NIFTY');
                $exchange     = $this->getExchangeForSymbol($baseSymbol);
                $nextExpiries = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', $exchange)
                    ->where('instrument_type', $finalOT)
                    ->whereDate('expiry', '>', $symbolExpiry)
                    ->orderBy('expiry', 'ASC')
                    ->distinct()
                    ->pluck('expiry')
                    ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
                    ->unique()->values();

                if (!$isWeekly) {
                    $byMonth = [];
                    foreach ($nextExpiries as $exp) { $byMonth[substr($exp, 0, 7)] = $exp; }
                    $nextExpiries = collect(array_values($byMonth));
                }
                $targetExpiry = $nextExpiries->first() ?? $symbolExpiry;
            }

            $exchange = $this->getExchangeForSymbol($baseSymbol);
            $option   = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', $exchange)
                ->where('instrument_type', $finalOT)
                ->where('strike', $atmStrike)
                ->whereDate('expiry', $targetExpiry)
                ->first();

            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', $exchange)
                    ->where('instrument_type', $finalOT)
                    ->whereDate('expiry', $targetExpiry)
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                    ->orderBy('strike_diff')
                    ->first();
            }

            if ($option) {
                $symbolsToFetch[] = $option->trading_symbol;
            }
        }

        if (!empty($symbolsToFetch)) {
            Log::info(sprintf(
                "EOD Prefetching LTPs for %d option symbols (broker %d)",
                count($symbolsToFetch), $broker->id
            ));
            $this->prefetchLTPs($broker, $symbolsToFetch);
        }
    }

    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== EOD: Starting PE/CE Order Placement (legacy pending check) ===');

            // Only pick up orders that were created but NOT yet placed
            // (e.g. from a previous run that crashed before placeOrderForAutoOrder ran)
            $pendingOrders = OIIVAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->whereHas('config', fn($q) => $q->where('status', true)->where('config_type', 'eod'))
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('No pending EOD orders to place');
                return;
            }

            Log::info("Found {$pendingOrders->count()} pending EOD orders");

            foreach ($pendingOrders->groupBy('broker_api_id') as $brokerId => $orders) {
                $broker = BrokerApi::find($brokerId);
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} invalid token - skipping");
                    continue;
                }
                $this->ensureKiteInstance($broker);
                foreach ($orders as $order) {
                    $this->placeOrder($order);
                }
            }

            Log::info('=== EOD: Order Placement Completed ===');

        } catch (\Exception $e) {
            Log::error('EOD placeOrders Error: ' . $e->getMessage());
        }
    }

    // =========================================================
    //  SERIES RESOLUTION
    // =========================================================

    /**
     * On expiry day, skip today's expiry → pick next series.
     * Mirrors resolveInstrumentSeries() exactly.
     */
    private function resolveDataSeries(string $currentDate): ?string
    {
        $isTodayExpiry = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', $currentDate)
            ->whereDate('trade_date', $currentDate)
            ->where('is_missing', 0)
            ->exists();

        $comparator = $isTodayExpiry ? '>' : '>=';

        $series = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', $comparator, $currentDate)
            ->orderBy('expiry_date', 'ASC')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($series) {
            $result = substr((string) $series, 0, 10);
            Log::info("resolveDataSeries({$currentDate}) isTodayExpiry={$isTodayExpiry} comparator={$comparator} → {$result}");
            return $result;
        }

        $series = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $currentDate)
            ->orderBy('expiry_date', 'ASC')
            ->value(DB::raw('DATE(expiry_date)'));

        $result = $series ? substr((string) $series, 0, 10) : null;
        Log::info("resolveDataSeries({$currentDate}) fallback → " . ($result ?? 'null'));
        return $result;
    }

    private function resolveInstrumentSeries(string $currentDate): ?string
    {
        $isTodayExpiry = ZerodhaInstrument::where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $currentDate)
            ->exists();

        $comparator = $isTodayExpiry ? '>' : '>=';

        $expiry = ZerodhaInstrument::where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $comparator, $currentDate)
            ->orderBy('expiry', 'ASC')
            ->value('expiry');

        if (!$expiry) {
            $expiry = ZerodhaInstrument::where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('expiry', '>', $currentDate)
                ->orderBy('expiry', 'ASC')
                ->value('expiry');
        }

        $expiry = $expiry
            ? (is_string($expiry) ? substr($expiry, 0, 10) : Carbon::parse($expiry)->toDateString())
            : null;

        Log::info("resolveInstrumentSeries({$currentDate}) isTodayExpiry={$isTodayExpiry} → {$expiry}");
        return $expiry;
    }

    // =========================================================
    //  EXPIRY HELPERS
    // =========================================================

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return substr((string) $expiry, 0, 10);

        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        return $expiry ? substr((string) $expiry, 0, 10) : null;
    }

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = $this->getNearestExpiryForDate($symbol, $date);

        if (!$expiry) return null;

        $expiryDate = substr((string) $expiry, 0, 10);
        $tradeDate  = substr((string) $date,   0, 10);

        if ($expiryDate === $tradeDate) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('expiry_date')
                ->where('is_missing', 0)
                ->whereDate('trade_date', $date)
                ->whereDate('expiry_date', '>', $expiryDate)
                ->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));

            if ($next) {
                $next = substr((string) $next, 0, 10);
                Log::info("resolveActiveExpiry({$symbol}, {$date}): expiry day shift {$expiryDate} → {$next}");
                return $next;
            }

            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', $expiryDate)
                ->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));

            if ($next) {
                $next = substr((string) $next, 0, 10);
                Log::info("resolveActiveExpiry({$symbol}, {$date}): fallback shift {$expiryDate} → {$next}");
                return $next;
            }
        }

        return $expiryDate;
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)
            ->where('is_missing', 0)
            ->exists();

        if ($exists) return $currentExpiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  CORE: AGGREGATE SIGNALS FROM option_ohlc_data
    // =========================================================

    private function aggregateSignalsFromOhlc(string $currentDate, string $prevDate, string $dataSeries): array
    {
        $closeTime = '14:45:00';
        $openTime  = '15:00:00';

        $futCandles = OptionOhlcData::whereDate('trade_date', $currentDate)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '{$closeTime}'")
            ->get()
            ->keyBy('base_symbol');

        if ($futCandles->isEmpty()) {
            Log::warning("EOD: No FUT candles at {$closeTime} for {$currentDate}");
            return [];
        }

        $signals = [];

        foreach ($futCandles as $symbol => $futCandle) {
            if ((float) $futCandle->close <= 0) continue;

            $currentClose = (float) $futCandle->close;

            $rawExpiry   = $this->getNearestExpiryForDate($symbol, $currentDate);
            $isExpiryDay = ($rawExpiry !== null
                && substr((string)$rawExpiry, 0, 10) === substr((string)$currentDate, 0, 10));

            $currentExpiry = $this->resolveActiveExpiry($symbol, $currentDate);

            // On expiry day the new series has no prev-day baseline
            $prevExpiry = $isExpiryDay
                ? null
                : ($currentExpiry ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry) : null);

            $todayCeQ = OptionOhlcData::whereDate('trade_date', $currentDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '{$closeTime}'");
            if ($currentExpiry) $todayCeQ->whereDate('expiry_date', $currentExpiry);
            $ceCurOI = (int) $todayCeQ->sum('oi');

            $todayPeQ = OptionOhlcData::whereDate('trade_date', $currentDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '{$closeTime}'");
            if ($currentExpiry) $todayPeQ->whereDate('expiry_date', $currentExpiry);
            $peCurOI = (int) $todayPeQ->sum('oi');

            $prevCeQ = OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '{$openTime}'");
            if ($prevExpiry) $prevCeQ->whereDate('expiry_date', $prevExpiry);
            $ceOpenOI = (int) $prevCeQ->sum('oi');

            $prevPeQ = OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '{$openTime}'");
            if ($prevExpiry) $prevPeQ->whereDate('expiry_date', $prevExpiry);
            $peOpenOI = (int) $prevPeQ->sum('oi');

            if ($ceCurOI == 0 && $peCurOI == 0) continue;

            $ceOiPct  = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $peOiPct  = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;
            $oiSignal = $this->getOISignal($ceOiPct, $peOiPct);
            $peCeRatio = $ceCurOI > 0 ? round($peCurOI / $ceCurOI, 2) : 0;

            $sentiment = $oiSignal['signal'];
            $isAligned = ($sentiment === 'BULLISH' || $sentiment === 'BEARISH');

            Log::info(sprintf(
                "EOD %s | ExpiryDay:%s | CurExpiry:%s | PrevExpiry:%s | Sentiment:%s | CE%%:%.2f (cur:%d prev:%d) | PE%%:%.2f (cur:%d prev:%d) | Aligned:%s",
                $symbol,
                $isExpiryDay ? 'YES' : 'NO',
                $currentExpiry ?? 'null',
                $prevExpiry    ?? 'null(new-series)',
                $sentiment,
                $ceOiPct, $ceCurOI, $ceOpenOI,
                $peOiPct, $peCurOI, $peOpenOI,
                $isAligned ? 'YES' : 'NO'
            ));

            $signals[$symbol] = [
                'underlying_symbol' => $symbol,
                'trading_symbol'    => $futCandle->trading_symbol ?? $symbol,
                'instrument_token'  => $futCandle->instrument_token ?? null,
                'open_close'        => 0,
                'current_close'     => $currentClose,
                'spot_price'        => $currentClose,
                'ce_oi_change_pct'  => $ceOiPct,
                'pe_oi_change_pct'  => $peOiPct,
                'pe_ce_ratio'       => $peCeRatio,
                'oi_condition'      => $oiSignal['condition'],
                'final_sentiment'   => $sentiment,
                'trade_action'      => match($sentiment) { 'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT' },
                'fut_50ma_signal'   => 'N/A',
                'is_aligned'        => $isAligned,
                'is_expiry_day'     => $isExpiryDay,
                'data_series'       => $dataSeries,
                'trading_date'      => $currentDate,
                'current_expiry'    => $currentExpiry,
                'prev_expiry'       => $prevExpiry,
            ];
        }

        if (empty($signals)) {
            Log::warning("EOD: aggregateSignalsFromOhlc produced 0 signals for {$currentDate}");
        }

        return $signals;
    }

    // =========================================================
    //  SIGNAL PROCESSING
    // =========================================================

    // private function processConfigSignals(
    //     OIIVAutoConfig $config,
    //     array $aggregatedSignals,
    //     BrokerApi $broker,
    //     string $currentDate,
    //     Carbon $processingDateTime,
    //     string $instrumentSeries
    // ): void {
    //     $created = $skipped = $skippedSentiment = $errors = 0;

    //     foreach ($aggregatedSignals as $symbol => $signalData) {
    //         try {
    //             if (!$signalData['is_aligned']) {
    //                 Log::info("EOD {$symbol}: SKIPPED (sentiment NEUTRAL/WAIT)");
    //                 $skippedSentiment++;
    //                 continue;
    //             }

    //             $ce   = (float) ($signalData['ce_oi_change_pct'] ?? 0);
    //             $pe   = (float) ($signalData['pe_oi_change_pct'] ?? 0);
    //             $rank = OIIVAutoConfig::computeStrengthRank($ce, $pe) ?? 5;

    //             $direction = $signalData['final_sentiment'];

    //             $rawOptionType   = $direction === 'BULLISH' ? 'CE' : 'PE';
    //             $finalOptionType = $config->shouldReverseSignal()
    //                 ? ($rawOptionType === 'CE' ? 'PE' : 'CE') : $rawOptionType;

    //             $isIndex  = in_array(strtoupper($symbol), self::INDEX_SYMBOLS);
    //             $quantity = $finalOptionType === 'CE'
    //                 ? ($isIndex ? (int)($config->index_ce_quantity ?? 0) : (int)($config->stock_ce_quantity ?? 0))
    //                 : ($isIndex ? (int)($config->index_pe_quantity ?? 0) : (int)($config->stock_pe_quantity ?? 0));

    //             if ($quantity <= 0) {
    //                 Log::info("EOD {$symbol}: qty=0 - skip");
    //                 $skipped++;
    //                 continue;
    //             }

    //             // Duplicate guard — one signal per symbol per day per config
    //             $exists = OIIVAutoOrder::where('config_id', $config->id)
    //                 ->where('symbol', $symbol)
    //                 ->whereDate('signal_detected_at', $currentDate)
    //                 ->where('status', true)
    //                 ->exists();

    //             if ($exists) {
    //                 Log::debug("EOD {$symbol}: duplicate - skip");
    //                 $skipped++;
    //                 continue;
    //             }

    //             $result = $this->analyzeAndCreateOrder(
    //                 $config, $signalData, $broker, $currentDate, $processingDateTime,
    //                 $rank, $direction, $rawOptionType, $finalOptionType, $quantity, $instrumentSeries
    //             );

    //             $result ? $created++ : $errors++;

    //         } catch (\Exception $e) {
    //             Log::error("EOD Error {$symbol}: " . $e->getMessage());
    //             $errors++;
    //         }
    //     }

    //     Log::info("EOD Config {$config->id} — Created:{$created} | SkippedNeutral:{$skippedSentiment} | SkippedOther:{$skipped} | Errors:{$errors}");
    // }

    private function processConfigSignals(
        OIIVAutoConfig $config,
        array $aggregatedSignals,
        BrokerApi $broker,
        string $currentDate,
        Carbon $processingDateTime,
        string $instrumentSeries
    ): void {
        $created          = 0;
        $skipped          = 0;
        $skippedSentiment = 0;
        $skippedSymbol    = 0;   // ← NEW counter
        $errors           = 0;
 
        // Log symbol filter info once per config
        if ($config->hasSymbolFilter()) {
            $count = $config->allowedSymbolCount();
            Log::info("EOD Config {$config->id}: Symbol filter ACTIVE — {$count} symbol(s) whitelisted: " . implode(', ', $config->allowed_symbols ?? []));
        } else {
            Log::info("EOD Config {$config->id}: Symbol filter NONE — trading all available symbols");
        }
 
        foreach ($aggregatedSignals as $symbol => $signalData) {
            try {
 
                // ── NEW: Symbol whitelist check ────────────────────────────
                if (!$config->isSymbolAllowed($symbol)) {
                    Log::debug("EOD {$symbol}: SKIPPED (not in allowed_symbols for config {$config->id})");
                    $skippedSymbol++;
                    continue;
                }
 
                // ── Existing: sentiment check ──────────────────────────────
                if (!$signalData['is_aligned']) {
                    Log::info("EOD {$symbol}: SKIPPED (sentiment NEUTRAL/WAIT)");
                    $skippedSentiment++;
                    continue;
                }
 
                $ce   = (float) ($signalData['ce_oi_change_pct'] ?? 0);
                $pe   = (float) ($signalData['pe_oi_change_pct'] ?? 0);
                $rank = OIIVAutoConfig::computeStrengthRank($ce, $pe) ?? 5;
 
                $direction = $signalData['final_sentiment'];
 
                $rawOptionType   = $direction === 'BULLISH' ? 'CE' : 'PE';
                $finalOptionType = $config->shouldReverseSignal()
                    ? ($rawOptionType === 'CE' ? 'PE' : 'CE') : $rawOptionType;
 
                $isIndex  = in_array(strtoupper($symbol), self::INDEX_SYMBOLS);
                $quantity = $finalOptionType === 'CE'
                    ? ($isIndex ? (int)($config->index_ce_quantity ?? 0) : (int)($config->stock_ce_quantity ?? 0))
                    : ($isIndex ? (int)($config->index_pe_quantity ?? 0) : (int)($config->stock_pe_quantity ?? 0));
 
                if ($quantity <= 0) {
                    Log::info("EOD {$symbol}: qty=0 - skip");
                    $skipped++;
                    continue;
                }
 
                // Duplicate guard
                $exists = OIIVAutoOrder::where('config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->whereDate('signal_detected_at', $currentDate)
                    ->where('status', true)
                    ->exists();
 
                if ($exists) {
                    Log::debug("EOD {$symbol}: duplicate - skip");
                    $skipped++;
                    continue;
                }
 
                $result = $this->analyzeAndCreateOrder(
                    $config, $signalData, $broker, $currentDate, $processingDateTime,
                    $rank, $direction, $rawOptionType, $finalOptionType, $quantity, $instrumentSeries
                );
 
                $result ? $created++ : $errors++;
 
            } catch (\Exception $e) {
                Log::error("EOD Error {$symbol}: " . $e->getMessage());
                $errors++;
            }
        }
 
        Log::info(
            "EOD Config {$config->id} — " .
            "Created:{$created} | " .
            "SkippedSymbolFilter:{$skippedSymbol} | " .   // ← NEW in log
            "SkippedNeutral:{$skippedSentiment} | " .
            "SkippedOther:{$skipped} | " .
            "Errors:{$errors}"
        );
    }

    // =========================================================
    //  ANALYZE + CREATE ORDER  (signal row + immediate order placement)
    // =========================================================

    private function analyzeAndCreateOrder(
        OIIVAutoConfig $config, array $signalData, BrokerApi $broker,
        string $date, Carbon $processingDateTime,
        int $rank, string $direction, string $rawOptionType,
        string $finalOptionType, int $quantity, string $instrumentSeries
    ): bool {
        try {
            $symbol   = $signalData['underlying_symbol'];
            $lockTime = Carbon::parse(
                $date . ' ' . self::CLOSE_TIME_HOUR . ':' . sprintf('%02d', self::CLOSE_TIME_MINUTE) . ':00',
                'Asia/Kolkata'
            );

            Log::info("EOD ANALYZE Config {$config->id} | {$symbol} | Rank:{$rank} | {$direction} => {$finalOptionType} | InstrSeries:{$instrumentSeries} | ExpiryDay:" . ($signalData['is_expiry_day'] ? 'YES' : 'NO'));

            if ($processingDateTime->lessThan($lockTime)) {
                Log::info("  Before 14:45 — skip");
                return false;
            }

            $currentPrice = $signalData['current_close'] ?? null;
            if (!$currentPrice || $currentPrice <= 0) {
                Log::error("  No 14:45 price for {$symbol}");
                return false;
            }

            $optionDetails = $this->getATMOption(
                $broker, $signalData['trading_symbol'], $finalOptionType,
                $currentPrice, $config, $instrumentSeries
            );

            if (!$optionDetails) {
                Log::error("  No ATM option for {$symbol} type={$finalOptionType} on {$instrumentSeries}");
                return false;
            }

            if ($optionDetails['ltp'] <= 0) {
                Log::error("  LTP unavailable (0) for {$optionDetails['symbol']} — skipping order");
                return false;
            }

            $ce        = (float)($signalData['ce_oi_change_pct'] ?? 0);
            $pe        = (float)($signalData['pe_oi_change_pct'] ?? 0);
            $modeLabel = $config->shouldReverseSignal() ? 'OPPOSITE' : 'ALIGN';

            $reason = sprintf(
                "EOD | Rank:%d | Dir:%s | Mode:%s | BUY %s | CE%%:%.2f | PE%%:%.2f | Diff:%.2f | %s | Sentiment:%s | ExpiryDay:%s | DataSeries:%s | InstrSeries:%s | Qty:%d | CurExpiry:%s | PrevExpiry:%s",
                $rank, $direction, $modeLabel, $finalOptionType,
                $ce, $pe, abs($ce - $pe),
                $signalData['oi_condition']    ?? 'N/A',
                $signalData['final_sentiment'] ?? 'N/A',
                ($signalData['is_expiry_day'] ?? false) ? 'YES' : 'NO',
                $signalData['data_series']     ?? 'N/A',
                $instrumentSeries, $quantity,
                $signalData['current_expiry']  ?? 'N/A',
                $signalData['prev_expiry']     ?? 'null(new-series)'
            );

            // ── 1. Create the parent signal row in oiiv_auto_orders ────────
            $autoOrder = OIIVAutoOrder::create([
                'user_id'            => $config->user_id,
                'config_id'          => $config->id,
                'broker_api_id'      => $broker->id,
                'symbol'             => $symbol,
                'trading_symbol'     => $signalData['trading_symbol'],
                'instrument_token'   => $signalData['instrument_token'] ?? null,
                'btst_signal'        => "EOD_RANK{$rank}_{$direction}_{$modeLabel}_{$finalOptionType}_SENTIMENT_ONLY",
                'btst_confidence'    => 100,
                'btst_reason'        => $reason,
                'signal_detected_at' => Carbon::parse($date . ' 14:45:00', 'Asia/Kolkata'),
                'fut_oi_signal'      => "EOD Rank{$rank} | " . ($signalData['oi_condition'] ?? 'N/A'),
                'fut_oi_strength'    => $signalData['final_sentiment'] ?? 'N/A',
                'ce_oi_signal'       => 'N/A', 'pe_oi_signal'   => 'N/A',
                'ce_iv_signal'       => 'N/A', 'ce_iv_strength' => 'N/A',
                'pe_iv_signal'       => 'N/A', 'pe_iv_strength' => 'N/A',
                'spot_price'         => $currentPrice,
                'option_symbol'      => $optionDetails['symbol'],
                'option_token'       => $optionDetails['token'],
                'option_type'        => $finalOptionType,
                'strike_price'       => $optionDetails['strike'],
                'entry_price'        => $optionDetails['ltp'],
                'current_price'      => $optionDetails['ltp'],
                'order_type'         => $config->order_type,
                'product'            => $config->product,
                'quantity'           => $quantity,
                'is_order_placed'    => false,
                'status'             => true,
            ]);

            Log::info("EOD Order created! AutoOrderID:{$autoOrder->id} | {$optionDetails['symbol']} | Type:{$finalOptionType} | Strike:{$optionDetails['strike']} | Rank{$rank} | Qty:{$quantity}");

            // ── 2. Immediately place on Zerodha + write to oiiv_order_book ─
            $this->placeOrderForAutoOrder($autoOrder, $broker, $signalData, $optionDetails, $instrumentSeries);

            return true;

        } catch (\Exception $e) {
            Log::error("EOD ANALYZE {$signalData['underlying_symbol']}: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    //  LEGACY placeOrder()  (handles any leftover pending orders)
    // =========================================================

    private function placeOrder(OIIVAutoOrder $order): void
    {
        try {
            $broker = $order->broker;
            if (!$broker->hasValidToken()) {
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Invalid token");
                return;
            }
            $this->ensureKiteInstance($broker);
            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();
            if (!$instrument) {
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Instrument not found");
                return;
            }
            $this->placeKiteOrder($order, $order->quantity, $instrument, $this->kiteInstances[$broker->id]);
            $order->update(['is_order_placed' => true, 'order_placed_at' => now()]);
            Log::info("EOD ORDER Processed: ID {$order->id}");
        } catch (\Exception $e) {
            Log::error("EOD ORDER {$order->option_symbol}: " . $e->getMessage());
            $this->saveFailedOrder($order, $order->quantity ?? 0, null, $e->getMessage());
        }
    }

    // =========================================================
    //  placeOrderForAutoOrder()
    //  Immediately places order(s) on Zerodha + writes each chunk
    //  to oiiv_order_book so SyncOiivOrders can manage status.
    // =========================================================

    private function placeOrderForAutoOrder(
        OIIVAutoOrder $autoOrder,
        BrokerApi $broker,
        array $signalData,
        array $optionDetails,
        string $instrumentSeries
    ): void {
        $this->ensureKiteInstance($broker);
        $kite = $this->kiteInstances[$broker->id];

        $baseSymbol = $autoOrder->symbol;
        $instrument = ZerodhaInstrument::where('instrument_token', $autoOrder->option_token)->first();

        if (!$instrument) {
            Log::error("[placeOrderForAutoOrder] Instrument not found token={$autoOrder->option_token}");
            $this->saveFailedOrder($autoOrder, 0, 0, "Instrument not found in zerodha_instruments");
            return;
        }

        $lotSize      = (int) $instrument->lot_size;
        $totalLots    = $autoOrder->quantity;
        $freezeLimit  = self::FREEZE_LIMITS[$baseSymbol] ?? null;
        $chunkLotsMax = $freezeLimit ?? $totalLots;
        $chunkTotal   = $freezeLimit ? (int) ceil($totalLots / $chunkLotsMax) : 1;

        // Compute placed price for LIMIT orders
        $ltp         = $optionDetails['ltp'];
        $placedPrice = 0;

        if ($autoOrder->order_type === 'LIMIT') {
            $discPct     = (float) ($autoOrder->config->disc_ltp ?? 0);
            $discount    = ($ltp * $discPct) / 100;
            $raw         = $ltp - $discount;
            $tick        = (float) ($instrument->tick_size ?? 0.05);
            $placedPrice = $tick > 0 ? round($raw / $tick) * $tick : round($raw, 2);
            $placedPrice = round($placedPrice, 2);
        }

        $exchange      = $this->getExchangeForSymbol($baseSymbol);
        $remainingLots = $totalLots;
        $chunkNumber   = 0;

        while ($remainingLots > 0) {
            $chunkLots  = min($chunkLotsMax, $remainingLots);
            $chunkUnits = $chunkLots * $lotSize;
            $chunkNumber++;

            $orderParams = [
                'exchange'         => $exchange,
                'tradingsymbol'    => $autoOrder->option_symbol,
                'transaction_type' => 'BUY',
                'quantity'         => $chunkUnits,
                'product'          => $autoOrder->product,
                'order_type'       => $autoOrder->order_type === 'MARKET' ? 'MARKET' : 'LIMIT',
                'validity'         => 'DAY',
            ];
            if ($autoOrder->order_type !== 'MARKET') {
                $orderParams['price'] = $placedPrice;
            }

            $zerodhaOrderId = null;
            $statusMsg      = null;
            $placed         = false;
            $maxRetries     = 3;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $this->throttleRequest($broker->id);
                    $result         = $kite->placeOrder('regular', $orderParams);
                    $zerodhaOrderId = $result->order_id ?? null;
                    $placed         = true;
                    Log::info("[OIIV] Order placed chunk {$chunkNumber}/{$chunkTotal} | {$autoOrder->option_symbol} | Zerodha: {$zerodhaOrderId}");
                    break;
                } catch (\Exception $e) {
                    $statusMsg = $e->getMessage();
                    if ($this->isRateLimitError($e) && $attempt < $maxRetries) {
                        $wait = (int) pow(2, $attempt);
                        Log::warning("[OIIV] Rate limit chunk {$chunkNumber}, retry {$attempt} in {$wait}s");
                        sleep($wait);
                    } else {
                        Log::error("[OIIV] placeOrder chunk {$chunkNumber} failed (attempt {$attempt}): {$statusMsg}");
                        break;
                    }
                }
            }

            // ── Write to oiiv_order_book ──────────────────────────────────
            OiivOrderBook::create([
                'user_id'              => $autoOrder->user_id,
                'broker_api_id'        => $broker->id,
                'oiiv_auto_order_id'   => $autoOrder->id,
                'zerodha_order_id'     => $zerodhaOrderId,
                'trading_symbol'       => $autoOrder->option_symbol,
                'base_symbol'          => $baseSymbol,
                'exchange'             => $exchange,
                'option_type'          => $autoOrder->option_type,
                'strike_price'         => $autoOrder->strike_price,
                'expiry_date'          => optional($instrument->expiry)->format('Y-m-d') ?? $instrument->expiry,
                'instrument_token'     => (int) $autoOrder->option_token,
                'signal_date'          => optional($autoOrder->signal_detected_at)->toDateString(),
                'signal_type'          => $autoOrder->btst_signal,
                'oi_condition'         => $signalData['oi_condition'] ?? null,
                'sentiment'            => $signalData['final_sentiment'] ?? null,
                'spot_price_at_signal' => $autoOrder->spot_price,
                'ce_oi_change_pct'     => $signalData['ce_oi_change_pct'] ?? 0,
                'pe_oi_change_pct'     => $signalData['pe_oi_change_pct'] ?? 0,
                'transaction_type'     => 'BUY',
                'order_type'           => $autoOrder->order_type,
                'product'              => $autoOrder->product,
                'validity'             => 'DAY',
                'quantity'             => $chunkLots,
                'quantity_units'       => $chunkUnits,
                'lot_size'             => $lotSize,
                'trigger_price'        => $ltp,
                'placed_price'         => $placedPrice ?: $ltp,
                'filled_quantity'      => 0,
                'status'               => $placed
                    ? OiivOrderBook::STATUS_OPEN
                    : OiivOrderBook::STATUS_REJECTED,
                'status_message'       => $placed ? null : $statusMsg,
                'internal_status'      => $placed
                    ? OiivOrderBook::INT_PLACED
                    : OiivOrderBook::INT_FAILED,
                'lot_chunk_number'     => $chunkNumber,
                'lot_chunk_total'      => $chunkTotal,
                'signal_detected_at'   => $autoOrder->signal_detected_at,
                'placed_at'            => $placed ? now() : null,
                'last_synced_at'       => now(),
            ]);

            $remainingLots -= $chunkLots;
            if ($remainingLots > 0) sleep(1);
        }

        // Mark the parent auto_order row as placed
        $autoOrder->update(['is_order_placed' => true, 'order_placed_at' => now()]);
    }

    // =========================================================
    //  LEGACY placeKiteOrder()
    //  Used only by the legacy placeOrder() for pending rows.
    // =========================================================

    private function placeKiteOrder(OIIVAutoOrder $order, $quantity, $instrument, $kite): void
    {
        $price = null;
        if ($order->order_type == 'LIMIT') {
            $discount = ($order->entry_price * $order->config->disc_ltp) / 100;
            $raw      = $order->entry_price - $discount;
            $tick     = (float) ($instrument->tick_size ?? 0.05);
            $price    = $tick > 0 ? round($raw / $tick) * $tick : round($raw, 2);
            $price    = number_format($price, 2, '.', '');
        }

        $baseSymbol      = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->trading_symbol);
        $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

        if ($freezeLimitLots && $quantity > $freezeLimitLots) {
            $remaining   = $quantity;
            $chunkNumber = 0;
            $chunkTotal  = (int) ceil($quantity / $freezeLimitLots);
            while ($remaining > 0) {
                $lots = min($freezeLimitLots, $remaining);
                $chunkNumber++;
                $this->executeSingleOrder($order, $lots, $price, $instrument, $kite, $chunkNumber, $chunkTotal);
                $remaining -= $lots;
                if ($remaining > 0) sleep(2);
            }
        } else {
            $this->executeSingleOrder($order, $quantity, $price, $instrument, $kite, 1, 1);
        }
    }

    private function executeSingleOrder(
        OIIVAutoOrder $order, $quantity, $price, $instrument, $kite,
        int $chunkNumber = 1, int $chunkTotal = 1
    ): void {
        $exchange = $this->getExchangeForSymbol($order->symbol);

        $params = [
            'exchange'         => $exchange,
            'tradingsymbol'    => $order->option_symbol,
            'transaction_type' => 'BUY',
            'quantity'         => $quantity * $instrument->lot_size,
            'product'          => $order->product,
            'validity'         => 'DAY',
        ];
        $params['order_type'] = $order->order_type == 'MARKET' ? 'MARKET' : 'LIMIT';
        if ($order->order_type != 'MARKET') $params['price'] = $price;

        $zerodhaOrderId = null;
        $statusMsg      = null;
        $placed         = false;
        $maxRetries     = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->throttleRequest($order->broker_api_id);
                $result         = $kite->placeOrder('regular', $params);
                $zerodhaOrderId = $result->order_id ?? null;
                $placed         = true;
                Log::info("EOD ORDER Placed chunk {$chunkNumber}/{$chunkTotal} | Zerodha: {$zerodhaOrderId}");
                break;
            } catch (\Exception $e) {
                $statusMsg = $e->getMessage();
                if ($this->isRateLimitError($e) && $attempt < $maxRetries) {
                    $wait = (int) pow(2, $attempt);
                    Log::warning("EOD ORDER rate-limited, retry {$attempt} in {$wait}s");
                    sleep($wait);
                } else {
                    Log::error("EOD ORDER chunk {$chunkNumber} failed (attempt {$attempt}): {$statusMsg}");
                    break;
                }
            }
        }

        OiivOrderBook::create([
            'user_id'              => $order->user_id,
            'broker_api_id'        => $order->broker_api_id,
            'oiiv_auto_order_id'   => $order->id,
            'zerodha_order_id'     => $zerodhaOrderId,
            'trading_symbol'       => $order->option_symbol,
            'base_symbol'          => $order->symbol,
            'exchange'             => $exchange,
            'option_type'          => $order->option_type,
            'strike_price'         => $order->strike_price,
            'expiry_date'          => optional($instrument->expiry)->format('Y-m-d') ?? $instrument->expiry,
            'instrument_token'     => (int) $order->option_token,
            'signal_date'          => optional($order->signal_detected_at)->toDateString(),
            'signal_type'          => $order->btst_signal,
            'sentiment'            => $order->fut_oi_strength,
            'spot_price_at_signal' => $order->spot_price,
            'transaction_type'     => 'BUY',
            'order_type'           => $order->order_type,
            'product'              => $order->product,
            'validity'             => 'DAY',
            'quantity'             => $quantity,
            'quantity_units'       => $quantity * $instrument->lot_size,
            'lot_size'             => $instrument->lot_size,
            'trigger_price'        => $order->entry_price,
            'placed_price'         => $price ?? $order->entry_price,
            'filled_quantity'      => 0,
            'status'               => $placed ? OiivOrderBook::STATUS_OPEN : OiivOrderBook::STATUS_REJECTED,
            'status_message'       => $placed ? null : $statusMsg,
            'internal_status'      => $placed ? OiivOrderBook::INT_PLACED : OiivOrderBook::INT_FAILED,
            'lot_chunk_number'     => $chunkNumber,
            'lot_chunk_total'      => $chunkTotal,
            'signal_detected_at'   => $order->signal_detected_at,
            'placed_at'            => $placed ? now() : null,
            'last_synced_at'       => now(),
        ]);
    }

    // =========================================================
    //  saveFailedOrder()  — writes a REJECTED row to oiiv_order_book
    // =========================================================

    private function saveFailedOrder(OIIVAutoOrder $order, $quantity, $price, string $error): void
    {
        try {
            OiivOrderBook::create([
                'user_id'            => $order->user_id,
                'broker_api_id'      => $order->broker_api_id,
                'oiiv_auto_order_id' => $order->id,
                'zerodha_order_id'   => null,
                'trading_symbol'     => $order->option_symbol,
                'base_symbol'        => $order->symbol,
                'exchange'           => $this->getExchangeForSymbol($order->symbol),
                'option_type'        => $order->option_type,
                'strike_price'       => $order->strike_price,
                'signal_date'        => optional($order->signal_detected_at)->toDateString(),
                'signal_type'        => $order->btst_signal,
                'sentiment'          => $order->fut_oi_strength,
                'spot_price_at_signal' => $order->spot_price,
                'transaction_type'   => 'BUY',
                'order_type'         => $order->order_type,
                'product'            => $order->product,
                'validity'           => 'DAY',
                'quantity'           => $quantity ?? $order->quantity,
                'quantity_units'     => ($quantity ?? $order->quantity),
                'lot_size'           => 1,
                'placed_price'       => $price ?? $order->entry_price,
                'filled_quantity'    => 0,
                'status'             => OiivOrderBook::STATUS_REJECTED,
                'status_message'     => substr($error, 0, 500),
                'internal_status'    => OiivOrderBook::INT_FAILED,
                'lot_chunk_number'   => 1,
                'lot_chunk_total'    => 1,
                'signal_detected_at' => $order->signal_detected_at,
                'placed_at'          => null,
                'last_synced_at'     => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("[saveFailedOrder] Could not write to oiiv_order_book: " . $e->getMessage());
        }
    }

    // =========================================================
    //  ATM OPTION LOOKUP
    // =========================================================

    private function getATMOption(
        BrokerApi $broker,
        string $tradingSymbol,
        string $optionType,
        float $futurePrice,
        OIIVAutoConfig $config,
        string $instrumentSeries
    ): ?array {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
            $interval   = self::STRIKE_INTERVALS[$baseSymbol] ?? 20;
            $atmStrike  = round($futurePrice / $interval) * $interval;

            $atmStrike = $optionType === 'CE'
                ? $atmStrike + $interval
                : $atmStrike - $interval;

            $symbolExpiry = $this->resolveSymbolInstrumentExpiry($baseSymbol, $instrumentSeries);

            if (!$symbolExpiry) {
                Log::warning("EOD ATM {$baseSymbol}: no expiry found for type={$optionType} from {$instrumentSeries}");
                return null;
            }

            $exchange = $this->getExchangeForSymbol($baseSymbol);

            if ($config->useNextSeries()) {
                $isWeekly     = ($baseSymbol === 'NIFTY');
                $nextExpiries = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', $exchange)
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>', $symbolExpiry)
                    ->orderBy('expiry', 'ASC')
                    ->distinct()
                    ->pluck('expiry')
                    ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
                    ->unique()
                    ->values();

                if (!$isWeekly) {
                    $byMonth = [];
                    foreach ($nextExpiries as $exp) {
                        $key = substr($exp, 0, 7);
                        $byMonth[$key] = $exp;
                    }
                    $nextExpiries = collect(array_values($byMonth));
                }

                $targetExpiry = $nextExpiries->first() ?? $symbolExpiry;
            } else {
                $targetExpiry = $symbolExpiry;
            }

            Log::debug(sprintf(
                "EOD ATM %s [%s]: interval=%d | atmStrike=%d | symbolExpiry=%s | selected=%s | useNext=%s | exchange=%s",
                $baseSymbol, $optionType, $interval, $atmStrike,
                $symbolExpiry, $targetExpiry,
                $config->useNextSeries() ? 'Y' : 'N',
                $exchange
            ));

            $option = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', $exchange)
                ->where('instrument_type', $optionType)
                ->where('strike', $atmStrike)
                ->whereDate('expiry', $targetExpiry)
                ->first();

            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', $exchange)
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', $targetExpiry)
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                    ->orderBy('strike_diff')
                    ->first();
            }

            if (!$option) {
                Log::warning("EOD ATM {$baseSymbol}: no option found type={$optionType} strike≈{$atmStrike} expiry={$targetExpiry}");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            Log::info("EOD ATM {$baseSymbol}: FOUND {$option->trading_symbol} | type={$optionType} | strike={$option->strike} | expiry={$targetExpiry} | LTP={$ltp}");

            return [
                'symbol' => $option->trading_symbol,
                'token'  => $option->instrument_token,
                'strike' => $option->strike,
                'ltp'    => $ltp,
                'expiry' => $option->expiry,
            ];

        } catch (\Exception $e) {
            Log::error("EOD ATM {$tradingSymbol}: " . $e->getMessage());
            return null;
        }
    }

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, $tradingSymbol): float
    {
        if (isset($this->ltpCache[$broker->id][$tradingSymbol])) {
            $cached = $this->ltpCache[$broker->id][$tradingSymbol];
            Log::debug("EOD LTP cache hit: {$tradingSymbol} = {$cached}");
            return $cached;
        }

        $maxRetries = 3;
        $attempt    = 0;

        while ($attempt < $maxRetries) {
            try {
                $this->ensureKiteInstance($broker);
                $this->throttleRequest($broker->id);

                $kite     = $this->kiteInstances[$broker->id];
                $base     = preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $tradingSymbol);
                $base     = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $base);
                $exch     = $this->getExchangeForSymbol(strtoupper($base));
                $quoteKey = "{$exch}:{$tradingSymbol}";
                $quotes   = $kite->getQuote([$quoteKey]);

                if (isset($quotes->$quoteKey->last_price)) {
                    $ltp = (float) $quotes->$quoteKey->last_price;
                    $this->ltpCache[$broker->id][$tradingSymbol] = $ltp;
                    return $ltp;
                }

                $arr = json_decode(json_encode($quotes), true);
                if (isset($arr[$quoteKey]['last_price'])) {
                    $ltp = (float) $arr[$quoteKey]['last_price'];
                    $this->ltpCache[$broker->id][$tradingSymbol] = $ltp;
                    return $ltp;
                }

                return 0.0;

            } catch (\Exception $e) {
                $attempt++;
                if ($this->isRateLimitError($e) && $attempt < $maxRetries) {
                    $wait = (int) pow(2, $attempt);
                    Log::warning("EOD LTP rate-limited {$tradingSymbol} — retry {$attempt}/{$maxRetries} in {$wait}s");
                    sleep($wait);
                } else {
                    Log::error("EOD LTP {$tradingSymbol} (attempt {$attempt}): " . $e->getMessage());
                    if ($attempt >= $maxRetries) return 0.0;
                }
            }
        }

        return 0.0;
    }

    // =========================================================
    //  OI SIGNAL LOGIC
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  50 MA  (kept for future use — NOT called for current orders)
    // =========================================================

    private function calculateRollingMA(array $values, int $period): array
    {
        $ma = []; $n = count($values); $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];
            if ($i >= $period) $sum -= $values[$i - $period];
            $ma[] = ($i >= $period - 1) ? ($sum / $period) : null;
        }
        return $ma;
    }

    private function historyStartDate(string $tradeDate, int $maPeriod = 50): string
    {
        return Carbon::parse($tradeDate)->subDays((int) ceil($maPeriod * 2.5) + 15)->toDateString();
    }

    private function getFut50MaSignal(string $baseSymbol, string $tradeDate, string $dataSeries): string
    {
        $maPeriod     = self::MA_PERIOD;
        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereBetween('trade_date', [$historyStart, $tradeDate])
            ->where(function ($q) use ($dataSeries) {
                $q->whereDate('expiry_date', $dataSeries)->orWhereNull('expiry_date');
            })
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get([
                DB::raw("DATE(trade_date) as candle_date"),
                DB::raw("TIME(interval_time) as candle_time"),
                'close',
            ]);

        if ($allCandles->isEmpty()) return 'N/A';

        $closeValues = $allCandles->pluck('close')->map(fn($v) => (float) $v)->toArray();
        $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

        $targetIdx = null;
        foreach ($allCandles as $idx => $candle) {
            $candleDate = is_string($candle->candle_date)
                ? $candle->candle_date
                : Carbon::parse($candle->candle_date)->toDateString();
            if ($candleDate !== $tradeDate) continue;
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($time === '15:00') { $targetIdx = $idx; break; }
            if ($time >= '15:00' && $time <= '15:15') $targetIdx = $idx;
        }

        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $candleDate = is_string($candle->candle_date)
                    ? $candle->candle_date
                    : Carbon::parse($candle->candle_date)->toDateString();
                if ($candleDate === $tradeDate) $targetIdx = $idx;
            }
        }

        if ($targetIdx === null || !isset($closeMa[$targetIdx])) return 'N/A';

        $close = $closeValues[$targetIdx];
        $ma    = $closeMa[$targetIdx];
        if ($ma === null) return 'N/A';

        return $close > $ma ? 'BULLISH' : ($close < $ma ? 'BEARISH' : 'NEUTRAL');
    }

    // =========================================================
    //  TRADING DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;

        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) {
                return $prev->format('Y-m-d');
            }
            $prev->subDay();
            $attempts++;
        }

        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    // =========================================================
    //  INSTRUMENT EXPIRY RESOLUTION  (for order placement)
    // =========================================================

    private function resolveSymbolInstrumentExpiry(string $baseSymbol, string $currentDate): ?string
    {
        $isWeekly = ($baseSymbol === 'NIFTY');
        $exchange = $this->getExchangeForSymbol($baseSymbol);

        $expiries = ZerodhaInstrument::where('exchange', $exchange)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('name', $baseSymbol)
            ->whereDate('expiry', '>=', $currentDate)
            ->orderBy('expiry', 'ASC')
            ->distinct()
            ->pluck('expiry')
            ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) return null;

        if (!$isWeekly) {
            $byMonth = [];
            foreach ($expiries as $exp) {
                $key = substr($exp, 0, 7);
                $byMonth[$key] = $exp;
            }
            $expiries = array_values($byMonth);
        }

        return $expiries[0] ?? null;
    }

    // =========================================================
    //  KITE INSTANCE HELPER
    // =========================================================

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
            $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
        }
    }
}