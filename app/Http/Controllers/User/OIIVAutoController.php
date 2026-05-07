<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\BrokerApi;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OIIVAutoController  —  EOD (3 PM) PE/CE Analysis
 *
 * CRITICAL — Column types from actual DB data:
 *   trade_date    = DATETIME  e.g. "2026-02-02 09:15:00"  (NOT a plain DATE)
 *   interval_time = DATETIME  e.g. "2026-02-02 09:15:00"  (NOT a plain TIME)
 *
 * This means:
 *   - NEVER use whereBetween('trade_date', ['2025-11-01','2026-02-23'])
 *     because MySQL evaluates "2026-02-23 15:00:00" <= "2026-02-23" as FALSE
 *     → rows on the last day are silently dropped → 50MA returns N/A
 *   - ALWAYS use whereDate('trade_date','>=', ...) / whereDate(...,'<=', ...)
 *   - TIME(interval_time) = 'HH:MM:SS' still works fine
 *
 * PROFIT WINDOW (BTST):
 *   Buy  = signal day 15:00 close price
 *   High = MAX(high)  from signal day 15:00 candle  +  next trading day up to 09:30
 *   Low  = MIN(low)   from signal day 15:00 candle  +  next trading day up to 09:30
 *   Exit = next trading day 09:30 candle OPEN  (actual sell price)
 *
 * WEEKLY EXPIRY FIX (NIFTY etc.):
 *   OI comparison uses expiry-aware prev-day lookup.
 *   getNearestExpiryForDate() mirrors PivotSignalController — driven by actual
 *   data, not hard-coded weekly/monthly logic.
 *   sumOIVsPrev() now compares same-expiry candles to avoid weekly rollover
 *   causing zero OI matches (which silently dropped NIFTY rows).
 *
 * EXPIRY DAY FIX:
 *   On expiry day the current contract has near-zero OI/liquidity.
 *   The LiveOptionOhlcCollector already skips expiry-day contracts and
 *   collects next-series data instead. This controller now mirrors that
 *   behaviour via isExpiryDay() + getNextSeriesExpiry(), so the UI shows
 *   next-series CE/PE OI rather than the flat/zero expiring contract.
 */
class OIIVAutoController extends Controller
{
    
    // =========================================================
    //  INDEX MEMBERSHIP HELPER
    // =========================================================

    private const INDEX_CONSTITUENTS = [
        'NIFTY50' => [
            'ADANIENT','ADANIPORTS','APOLLOHOSP','ASIANPAINT','AXISBANK',
            'BAJAJ-AUTO','BAJAJFINSV','BAJFINANCE','BEL','BHARTIARTL',
            'CIPLA','COALINDIA','DRREDDY','EICHERMOT','ETERNAL',
            'GRASIM','HCLTECH','HDFCBANK','HDFCLIFE','HINDALCO',
            'HINDUNILVR','ICICIBANK','INDIGO','INFY','ITC',
            'JIOFIN','JSWSTEEL','KOTAKBANK','LT','M&M',
            'MARUTI','MAXHEALTH','NESTLEIND','NTPC','ONGC',
            'POWERGRID','RELIANCE','SBILIFE','SBIN','SHRIRAMFIN',
            'SUNPHARMA','TATACONSUM','TATASTEEL','TCS','TECHM',
            'TITAN','TMPV','TRENT','ULTRACEMCO','WIPRO',
        ],
        'BANKNIFTY' => [
            'HDFCBANK','ICICIBANK','AXISBANK','SBIN','KOTAKBANK',
            'FEDERALBNK','INDUSINDBK','AUBANK','BANKBARODA','CANBK',
        ],
        'SENSEX' => [
            'RELIANCE','HDFCBANK','BHARTIARTL','SBIN','TCS',
            'ICICIBANK','INFY','BAJFINANCE','LT','HINDUNILVR',
            'SUNPHARMA','MARUTI','HCLTECH','M&M','AXISBANK',
            'ITC','TITAN','KOTAKBANK','NTPC','ADANIPORTS',
            'ULTRACEMCO','BEL','POWERGRID','BAJAJFINSV','TATASTEEL',
            'ETERNAL','ASIANPAINT','INDIGO','TECHM','TRENT',
        ],
    ];

    private function isInIndex(string $symbol, string $index): bool
    {
        return in_array(strtoupper($symbol), self::INDEX_CONSTITUENTS[$index] ?? [], true);
    }

    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'OI + IV Signal Analysis';
        return view($this->activeTemplate . 'user.oiiv-auto.index', compact('pageTitle'));
    }

    public function peCeAnalysis()
    {
        $pageTitle = 'EOD PE/CE Analysis (3 PM)';
        return view($this->activeTemplate . 'user.oiiv-auto.pece-analysis', compact('pageTitle'));
    }

    public function config()
    {
        $pageTitle = 'OI + IV Auto Trading Configuration';

        $brokers = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = OIIVAutoConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.oiiv-auto.config', [
            'pageTitle' => $pageTitle,
            'brokers'   => $brokers,
            'configs'   => $configs,
        ]);
    }

    public function viewOrders($configId)
    {
        $pageTitle = 'OI+IV Auto Trading Orders';

        $config = OIIVAutoConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = OIIVAutoOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with(['broker:id,client_name'])
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.oiiv-auto.orders', [
            'pageTitle' => $pageTitle,
            'config'    => $config,
            'orders'    => $orders,
        ]);
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  EXPIRY HELPERS  (mirrors LiveOptionOhlcCollector logic)
    // =========================================================

    /**
     * Return the nearest expiry date >= $date that has actual CE/PE data
     * in option_ohlc_data for $symbol on $date.
     *
     * Priority:
     *  1. Nearest expiry_date >= today that appears on today's rows.
     *  2. Fallback: most recent expiry_date from today's rows (handles
     *     expiry day where today == expiry → shows today's rows).
     *
     * NOTE: On expiry day this will return TODAY's expiry. Callers that
     * need next-series behaviour must call resolveActiveExpiry() instead,
     * which mirrors the collector's expiry-day shift.
     */
    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        // Forward-looking: nearest expiry on or after today
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        // Fallback: most recent expiry from today's data (handles expiry day)
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    /**
     * Resolve the ACTIVE expiry to use for a given trade date, mirroring
     * LiveOptionOhlcCollector::resolveExpiriesFor15Min() exactly.
     *
     * On expiry day the collector already stored next-series data (it shifts
     * forward on expiry day). So on expiry day we must also look at the NEXT
     * expiry here, otherwise we query the expiring (near-zero) contract and
     * get flat/zero OI — making the signal useless.
     *
     * Algorithm:
     *   1. Get the nearest expiry from actual data (getNearestExpiryForDate).
     *   2. If that expiry == $date (i.e. today IS expiry day) → shift to next.
     *   3. Otherwise return as-is.
     */
    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = $this->getNearestExpiryForDate($symbol, $date);

        if (!$expiry) return null;

        // On expiry day the resolved expiry equals the trade date.
        // Shift to next series — the collector stored next-series data.
        if ($expiry === $date) {
            $next = $this->getNextSeriesExpiry($symbol, $date, $expiry);
            if ($next) {
                Log::info("OIIVAutoController::resolveActiveExpiry — expiry day shift for {$symbol} on {$date}: {$expiry} → {$next}");
                return $next;
            }
            // If no next found, fall back to current (edge case: last ever expiry)
        }

        return $expiry;
    }

    /**
     * Fetch the next available expiry for a symbol AFTER $currentExpiry.
     *
     * Queries actual option_ohlc_data rows stored on $date so we only return
     * an expiry the collector actually fetched (not a hypothetical future one).
     * Falls back to any expiry > currentExpiry in the data if same-date rows
     * aren't available yet.
     */
    private function getNextSeriesExpiry(string $symbol, string $date, string $currentExpiry): ?string
    {
        // First: look for next expiry in today's stored data (collector already wrote it)
        $next = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($next) return $next;

        // Fallback: look ahead in the DB for any future expiry with data
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  PRICE SIGNAL  (prev day 15:00 close vs today 14:45 close)
    // =========================================================

    private function getPriceSignal(string $symbol, string $date, string $prevDate): array
    {
        $todayCandle = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        $prevCandle = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->first();

        if (!$todayCandle || !$prevCandle) {
            return ['signal' => 'N/A', 'today_close' => 0, 'prev_close' => 0, 'change_pct' => 0];
        }

        $todayClose = (float) $todayCandle->close;
        $prevClose  = (float) $prevCandle->close;

        if ($prevClose <= 0 || $todayClose <= 0) {
            return ['signal' => 'N/A', 'today_close' => $todayClose, 'prev_close' => $prevClose, 'change_pct' => 0];
        }

        $changePct = (($todayClose - $prevClose) / $prevClose) * 100;
        $signal    = $todayClose > $prevClose ? 'BULLISH' : ($todayClose < $prevClose ? 'BEARISH' : 'NEUTRAL');

        return [
            'signal'      => $signal,
            'today_close' => round($todayClose, 2),
            'prev_close'  => round($prevClose,  2),
            'change_pct'  => round($changePct,  2),
        ];
    }

    // =========================================================
    //  GANN OCTAVE  (20-day swing range → 8 levels → bias + nearest)
    // =========================================================

    private function getGannOctave(string $symbol, string $date): array
    {
        $noData = [
            'bias' => 'N/A', 'near_level' => null, 'near_price' => null,
            'distance' => null, 'distance_pct' => null, 'zone' => null,
            'levels' => [], 'swing_high' => null, 'swing_low' => null,
        ];

        $startDate = Carbon::parse($date)->subDays(20)->toDateString();

        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $startDate)
            ->whereDate('trade_date', '<=', $date)
            ->get(['high', 'low', 'close']);

        if ($candles->isEmpty()) return $noData;

        $swingHigh = (float) $candles->max('high');
        $swingLow  = (float) $candles->min('low');

        if ($swingHigh <= $swingLow) return $noData;

        $range  = $swingHigh - $swingLow;
        $octave = $range / 8;

        // Build 0/8 → 8/8 levels
        $levels = [];
        for ($i = 0; $i <= 8; $i++) {
            $levels[$i] = round($swingLow + ($octave * $i), 2);
        }

        // Today's price at 14:45
        $currentRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        $currentPrice = $currentRow ? (float) $currentRow->close : null;

        if (!$currentPrice || $currentPrice <= 0) {
            return array_merge($noData, ['levels' => $levels, 'swing_high' => $swingHigh, 'swing_low' => $swingLow]);
        }

        // Determine zone (which 1/8 band the price sits in)
        $zoneIndex = 0;
        if ($currentPrice <= $levels[0]) {
            $zoneIndex = 0;
        } elseif ($currentPrice >= $levels[8]) {
            $zoneIndex = 7;
        } else {
            for ($i = 0; $i < 8; $i++) {
                if ($currentPrice >= $levels[$i] && $currentPrice <= $levels[$i + 1]) {
                    $zoneIndex = $i;
                    break;
                }
            }
        }

        // Gann Bias rules
        $bias = match(true) {
            $zoneIndex >= 6 => 'STRONG BULLISH',
            $zoneIndex >= 4 => 'BULLISH',
            $zoneIndex <= 1 => 'STRONG BEARISH',
            default         => 'BEARISH',
        };

        // Nearest 1/8 level
        $nearestIdx = 0;
        $minDist    = PHP_FLOAT_MAX;
        foreach ($levels as $idx => $lvlPrice) {
            $dist = abs($currentPrice - $lvlPrice);
            if ($dist < $minDist) {
                $minDist    = $dist;
                $nearestIdx = $idx;
            }
        }

        $nearPrice   = $levels[$nearestIdx];
        $distancePct = round(($minDist / $currentPrice) * 100, 2);

        return [
            'bias'         => $bias,
            'zone'         => $zoneIndex . '/8',
            'near_level'   => $nearestIdx . '/8',
            'near_price'   => round($nearPrice, 2),
            'distance'     => round($minDist, 2),
            'distance_pct' => $distancePct,
            'levels'       => $levels,
            'swing_high'   => $swingHigh,
            'swing_low'    => $swingLow,
        ];
    }

    /**
     * For the previous trading day, find the expiry that best matches
     * the current day's expiry for OI comparison purposes.
     *
     * For non-rollover days: prev day had the same expiry contract → use it directly.
     * For rollover days (e.g. current expiry is a new weekly):
     *   try the same expiry first; if no prev-day data exists for it,
     *   fall back to the nearest expiry that DID have data on prev day.
     *
     * This prevents the weekly rollover from causing zero-OI matches
     * that silently drop NIFTY rows.
     */
    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        // Best case: same expiry had data on prev day
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)
            ->where('is_missing', 0)
            ->exists();

        if ($exists) return $currentExpiry;

        // Rollover case: current expiry is brand new (weekly just started).
        // Use the expiry that was active on prev day (the one that expired on/before today).
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  OI+IV ANALYSIS  (index page)
    // =========================================================

    public function analyzeSignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both From and To dates', 'data' => []]);
            }

            // whereDate() handles DATETIME columns correctly
            $openQuery = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->whereRaw("TIME(interval_time) = '09:30:00'");

            if (!empty($selectedSymbols)) $openQuery->whereIn('base_symbol', $selectedSymbols);
            $openCandles = $openQuery->get()->keyBy(fn($c) =>
                $c->base_symbol . '|' . $this->dateStr($c->trade_date)
            );

            $closeQuery = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->whereRaw("TIME(interval_time) = '15:00:00'");

            if (!empty($selectedSymbols)) $closeQuery->whereIn('base_symbol', $selectedSymbols);
            $closeCandles = $closeQuery->get()->keyBy(fn($c) =>
                $c->base_symbol . '|' . $this->dateStr($c->trade_date)
            );

            $tradeDates  = $closeCandles->map(fn($c) => $this->dateStr($c->trade_date))
                ->unique()->sort()->values()->toArray();

            $prevDateMap = [];
            foreach ($tradeDates as $d) {
                $prevDateMap[$d] = $this->getPreviousTradingDate($d);
            }

            $prevDates   = array_unique(array_values($prevDateMap));
            $prevCandles = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereIn(DB::raw('DATE(trade_date)'), $prevDates)
                ->whereRaw("TIME(interval_time) = '15:15:00'")
                ->get()
                ->keyBy(fn($c) => $c->base_symbol . '|' . $this->dateStr($c->trade_date));

            $results = [];

            foreach ($closeCandles as $key => $closeCandle) {
                [$symbol, $date] = explode('|', $key);

                $openCandle = $openCandles[$key] ?? null;
                $prevDate   = $prevDateMap[$date] ?? null;
                $prevCandle = $prevDate ? ($prevCandles[$symbol . '|' . $prevDate] ?? null) : null;

                $openPrice  = $openCandle ? (float) $openCandle->open  : (float) $closeCandle->open;
                $closePrice = (float) $closeCandle->close;

                $priceChange    = $closePrice - $openPrice;
                $priceChangePct = $openPrice > 0 ? (($priceChange / $openPrice) * 100) : 0;
                $priceDirection = $priceChange > 0 ? 'UP' : ($priceChange < 0 ? 'DOWN' : 'FLAT');

                $todayOI     = (float) ($closeCandle->oi ?? 0);
                $prevOI      = $prevCandle ? (float) ($prevCandle->oi ?? 0) : 0;
                $oiChange    = $prevOI > 0 ? (($todayOI - $prevOI) / $prevOI) * 100 : 0;
                $oiDirection = $oiChange > 0 ? 'POSITIVE' : ($oiChange < 0 ? 'NEGATIVE' : 'FLAT');

                $signal = $this->determineSignalForDisplay($priceDirection, $oiDirection);

                $results[] = [
                    'date'                 => $date,
                    'symbol'              => $symbol,
                    'fut_symbol'          => $closeCandle->trading_symbol ?? $symbol,
                    'spot_price'          => round($closePrice, 2),
                    'open_price'          => round($openPrice, 2),
                    'current_price'       => round($closePrice, 2),
                    'price_change'        => round($priceChange, 2),
                    'price_change_percent' => round($priceChangePct, 2),
                    'price_direction'     => $priceDirection,
                    'oi_change_pct'       => round($oiChange, 2),
                    'oi_direction'        => $oiDirection,
                    'fut_oi'              => (int) $todayOI,
                    'fut_oi_prev'         => (int) $prevOI,
                    'fut_oi_change_pct'   => round($oiChange, 2),
                    'signal'              => $signal['signal']   ?? 'NO_SIGNAL',
                    'signal_type'         => $signal['type']     ?? 'NONE',
                    'signal_reason'       => $signal['reason']   ?? 'No clear signal',
                    'signal_scenario'     => $signal['scenario'] ?? 'N/A',
                    'order_picked'        => ($signal['signal'] ?? 'NO_SIGNAL') !== 'NO_SIGNAL' ? 'YES' : 'NO',
                ];
            }

            usort($results, fn($a, $b) => $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']);

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('OI+IV Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    private function determineSignalForDisplay(string $priceDirection, string $oiDirection): array
    {
        if ($priceDirection === 'UP'   && $oiDirection === 'NEGATIVE') return ['signal' => 'BUY_CE', 'type' => 'CE', 'reason' => 'Short Covering - Bullish',  'scenario' => 'Price Up + OI Negative'];
        if ($priceDirection === 'UP'   && $oiDirection === 'POSITIVE') return ['signal' => 'BUY_CE', 'type' => 'CE', 'reason' => 'Long Buildup - Bullish',    'scenario' => 'Price Up + OI Positive'];
        if ($priceDirection === 'DOWN' && $oiDirection === 'NEGATIVE') return ['signal' => 'BUY_PE', 'type' => 'PE', 'reason' => 'Long Unwinding - Bearish',  'scenario' => 'Price Down + OI Negative'];
        if ($priceDirection === 'DOWN' && $oiDirection === 'POSITIVE') return ['signal' => 'BUY_PE', 'type' => 'PE', 'reason' => 'Short Buildup - Bearish',   'scenario' => 'Price Down + OI Positive'];
        return ['signal' => 'NO_SIGNAL', 'type' => 'NONE', 'reason' => 'No clear signal', 'scenario' => 'N/A'];
    }

    public function clearPriceCache(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'No cache — data sourced live.', 'affected' => 0]);
    }

    // =========================================================
    //  PE/CE ANALYSIS  (main analysis endpoint)
    // =========================================================

    public function analyzePECESignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');
    
            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }
    
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')
                ->pluck('d')->toArray();
    
            $results = [];
            foreach ($tradeDates as $date) {
                $prevDate  = $this->getPreviousTradingDate($date);
                $prev2Date = $this->getPreviousTradingDate($prevDate);     // NEW: T-2
    
                $rows = $this->buildSignalRowsForDate($date, $prevDate, $prev2Date, $selectedSymbols, $filterAction);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }
    
            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']
            );
    
            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);
    
        } catch (\Exception $e) {
            Log::error('OI Flow Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Build signal rows for a single date.
     *
     * KEY FIXES:
     *   1. resolveActiveExpiry() — on expiry day, shifts to next series just
     *      like LiveOptionOhlcCollector::resolveExpiriesFor15Min(). This ensures
     *      we query the same data the collector actually stored, not the flat
     *      near-zero expiring contract.
     *   2. getPrevDayExpiry() — handles rollover where today's expiry didn't
     *      exist on prev day (weekly new series).
     *   3. OI comparison is scoped to today's active expiry for today's candles
     *      and prev day's matching expiry for prev day's candles.
     *   4. The `continue` guard only fires when BOTH current-day CE and PE OI
     *      are genuinely zero — not due to expiry mismatch.
     *   5. is_expiry_day flag exposed in row for UI badge display.
     */
    private function buildSignalRowsForDate(
        string  $date,
        string  $prevDate,
        string  $prev2Date,       // NEW: T-2 date
        array   $symbolFilter,
        ?string $actionFilter
    ): array {
        // Today's FUT candles at 14:45 — drives symbol list
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");
        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');
        if ($futCandles->isEmpty()) return [];
    
        $rows = [];
    
        foreach ($futCandles->keys() as $symbol) {
            $futCandle    = $futCandles[$symbol];
            $currentClose = (float) $futCandle->close;
            if ($currentClose <= 0) continue;
    
            // ── Expiry resolution (mirrors collector) ──────────────────────
            $rawExpiry     = $this->getNearestExpiryForDate($symbol, $date);
            $isExpiryDay   = ($rawExpiry !== null && $rawExpiry === $date);
            $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;
    
            // ── T OI (today 14:45) ─────────────────────────────────────────
            $ceCurOI = (int) OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                ->sum('oi');
    
            $peCurOI = (int) OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                ->sum('oi');
    
            if ($ceCurOI == 0 && $peCurOI == 0) continue;
    
            // ── T-1 OI (prev day 15:00) ────────────────────────────────────
            $ceOpenOI = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
                ->sum('oi');
    
            $peOpenOI = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
                ->sum('oi');
    
            // ── T-2 OI (day before prev, 15:00) ───────────────────────────
            // Use same expiry as T-1 (or fallback to resolving for prev2Date)
            $prev2Expiry = $prevExpiry ?? $this->getPrevDayExpiry($symbol, $prev2Date, $prevExpiry ?? $currentExpiry);
    
            $cePrev2OI = (int) OptionOhlcData::whereDate('trade_date', $prev2Date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prev2Expiry, fn($q) => $q->whereDate('expiry_date', $prev2Expiry))
                ->sum('oi');
    
            $pePrev2OI = (int) OptionOhlcData::whereDate('trade_date', $prev2Date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prev2Expiry, fn($q) => $q->whereDate('expiry_date', $prev2Expiry))
                ->sum('oi');
    
            // ── % changes ──────────────────────────────────────────────────
            // T vs T-1
            $ceOiPct = $ceOpenOI > 0 ? round((($ceCurOI  - $ceOpenOI) / $ceOpenOI)  * 100, 4) : 0;
            $peOiPct = $peOpenOI > 0 ? round((($peCurOI  - $peOpenOI) / $peOpenOI)  * 100, 4) : 0;
    
            // T-1 vs T-2
            $ceFlowT1T2 = $cePrev2OI > 0 ? round((($ceOpenOI - $cePrev2OI) / $cePrev2OI) * 100, 2) : 0;
            $peFlowT1T2 = $pePrev2OI > 0 ? round((($peOpenOI - $pePrev2OI) / $pePrev2OI) * 100, 2) : 0;
    
            // T vs T-1 (same as ceOiPct/peOiPct, explicit for flow engine)
            $ceFlowT0T1 = $ceOiPct;
            $peFlowT0T1 = $peOiPct;
    
            // ── Gap (CE% vs PE% difference) ────────────────────────────────
            $diff = round(abs($ceOiPct - $peOiPct), 2);
    
            // ── Base signal (existing logic, unchanged) ────────────────────
            $oiSignal  = $this->getOISignal($ceOiPct, $peOiPct);
            $baseSignal = $oiSignal['signal']; // BULLISH / BEARISH / NEUTRAL
    
            // ── SPIKE DETECTION ────────────────────────────────────────────
            $ceSpike   = abs($ceOiPct) > 35;
            $peSpike   = abs($peOiPct) > 35;
            $spikeType = match(true) {
                $ceSpike && $peSpike => 'DUAL_SPIKE',
                $ceSpike             => 'CE_SPIKE',
                $peSpike             => 'PE_SPIKE',
                default              => 'NONE',
            };
    
            // ── OI FLOW ENGINE (3-day intelligence) ───────────────────────
            $flowSignal = $this->getOIFlowSignal(
                $ceFlowT1T2, $ceFlowT0T1,
                $peFlowT1T2, $peFlowT0T1
            );
    
            // ── SCORE ENGINE ───────────────────────────────────────────────
            $score = $this->calcOIScore(
                $baseSignal, $flowSignal, $diff, $spikeType, $isExpiryDay
            );
    
            // ── CONFLICT CHECK (base vs flow) ──────────────────────────────
            // If today's signal contradicts the 3-day flow → dangerous → WAIT
            $hasConflict = (
                ($baseSignal === 'BULLISH' && $flowSignal === 'STRONG_BEAR') ||
                ($baseSignal === 'BEARISH' && $flowSignal === 'STRONG_BULL') ||
                ($baseSignal === 'BULLISH' && $flowSignal === 'TRAP')        ||
                ($baseSignal === 'BEARISH' && $flowSignal === 'TRAP')
            );
    
            // ── TRADE ACTION (score-based) ─────────────────────────────────
            // if ($hasConflict || $diff < 10) {
            //     // Conflict = dangerous. Gap < 10 = no edge. Both → WAIT.
            //     $tradeAction = 'WAIT';
            // } elseif ($score >= 4) {
            //     $tradeAction = 'BUY CE';
            // } elseif ($score <= -4) {
            //     $tradeAction = 'BUY PE';
            // } else {
            //     $tradeAction = 'WAIT';
            // }

            // ── TRADE ACTION (sentiment-based) ────────────────────────────
            if ($baseSignal === 'BULLISH') {
                $tradeAction = 'BUY CE';
            } elseif ($baseSignal === 'BEARISH') {
                $tradeAction = 'BUY PE';
            } else {
                $tradeAction = 'WAIT';
            }
    
            // ── CONFIDENCE ─────────────────────────────────────────────────
            $absScore = abs($score);
            $confidence = match(true) {
                $absScore >= 7  => 'HIGH',
                $absScore >= 5  => 'MEDIUM',
                $absScore >= 4  => 'LOW',
                default         => 'NONE',
            };
    
            if ($hasConflict) $confidence = 'CONFLICT';
    
            // Filter by action
            if (!empty($actionFilter) && $tradeAction !== $actionFilter) continue;
    
            // ── EXISTING DATA (unchanged) ──────────────────────────────────
            $peCeRatio = $ceCurOI > 0 ? round($peCurOI / $ceCurOI, 2) : 0;
            $absCe = abs($ceOiPct); $absPe = abs($peOiPct);
            $isBoth = str_contains($oiSignal['condition'], 'Both');
            $strongerSide = $isBoth
                ? ($absCe > $absPe ? 'CE' : ($absPe > $absCe ? 'PE' : 'EQUAL'))
                : 'CLEAR';
    
            if      ($diff > 40) $strengthRank = 'Rank 1';
            elseif  ($diff > 25) $strengthRank = 'Rank 2';
            elseif  ($diff > 10) $strengthRank = 'Rank 3';
            elseif  ($diff > 5)  $strengthRank = 'Rank 4';
            else                 $strengthRank = 'Normal';
    
            $futPrices = $this->getFutPricesFromOhlc($symbol, $date, $prevDate);
    
            $prevFutCandle = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) = '15:00:00'")->first();
    
            $futOI     = (int) ($futCandle->oi ?? 0);
            $futPrevOI = $prevFutCandle ? (int) ($prevFutCandle->oi ?? 0) : 0;
            $futOiPct  = $futPrevOI > 0
                ? round((($futOI - $futPrevOI) / $futPrevOI) * 100, 2)
                : 0;
    
            $fut50Ma    = $this->getFut50MaSignal($symbol, $date);
            $mmTrap     = $this->getMmTrapForSymbolDate($symbol, $date, $prevDate, $currentExpiry, $prevExpiry);
            $priceSignal = $this->getPriceSignal($symbol, $date, $prevDate);
            $gannOctave  = $this->getGannOctave($symbol, $date);
    
            $rows[] = [
                // ── Core ──────────────────────────────────────────────────
                'date'         => $date,
                'symbol'       => $symbol,
                'fut_symbol'   => $futCandle->trading_symbol ?? $symbol,
    
                // ── T OI ──────────────────────────────────────────────────
                'ce_oi'            => $ceCurOI,
                'ce_oi_prev'       => $ceOpenOI,
                'ce_oi_change_pct' => $ceOiPct,
    
                'pe_oi'            => $peCurOI,
                'pe_oi_prev'       => $peOpenOI,
                'pe_oi_change_pct' => $peOiPct,
    
                // ── T-2 OI (NEW) ───────────────────────────────────────────
                'ce_oi_prev2'  => $cePrev2OI,
                'pe_oi_prev2'  => $pePrev2OI,
    
                // ── Flow data (NEW) ────────────────────────────────────────
                'ce_flow_t1_t2'  => $ceFlowT1T2,   // T-1 vs T-2 CE change%
                'ce_flow_t0_t1'  => $ceFlowT0T1,   // T vs T-1 CE change%
                'pe_flow_t1_t2'  => $peFlowT1T2,   // T-1 vs T-2 PE change%
                'pe_flow_t0_t1'  => $peFlowT0T1,   // T vs T-1 PE change%
                'flow_signal'    => $flowSignal,    // STRONG_BULL/BEAR/CONTINUATION/REVERSAL/TRAP/MIXED
                'spike_type'     => $spikeType,     // DUAL_SPIKE/CE_SPIKE/PE_SPIKE/NONE
                'oi_diff'        => $diff,          // abs gap CE% vs PE%
                'oi_score'       => round($score, 2), // final score
                'confidence'     => $confidence,    // HIGH/MEDIUM/LOW/NONE/CONFLICT
                'has_conflict'   => $hasConflict,
    
                // ── FUT OI ────────────────────────────────────────────────
                'fut_oi'             => $futOI,
                'fut_oi_prev'        => $futPrevOI,
                'fut_oi_change_pct'  => $futOiPct,
    
                // ── Existing fields (unchanged) ────────────────────────────
                'ce_oi_change_pct_fut' => round($ceOiPct, 2),
                'pe_oi_change_pct_fut' => round($peOiPct, 2),
    
                'strength_rank' => $strengthRank,
                'strength_diff' => round($diff, 2),
                'stronger_side' => $strongerSide,
    
                'pe_ce_ratio'       => $peCeRatio,
                'oi_interpretation' => $this->getOiInterpretation($peCeRatio),
                'oi_condition'      => $oiSignal['condition'],
    
                'options_sentiment' => $baseSignal,
                'final_sentiment'   => $baseSignal,
                'trade_action'      => $tradeAction,    // NOW SCORE-BASED
                'futures_oi_view'   => 'N/A',
    
                'fut_price_prev'       => $futPrices['fut_price_prev'],
                'fut_price_today'      => $futPrices['fut_price_today'],
                'fut_price_change'     => $futPrices['fut_price_change'],
                'fut_price_change_pct' => $futPrices['fut_price_change_pct'],
                'fut_price_signal'     => $futPrices['fut_price_signal'],
    
                'spot_price'      => round($currentClose, 2),
                'fut_50ma_signal' => $fut50Ma,
                'mm_trap'         => $mmTrap,
    
                'current_expiry' => $currentExpiry,
                'prev_expiry'    => $prevExpiry,
                'raw_expiry'     => $rawExpiry,
                'is_expiry_day'  => $isExpiryDay,
    
                'in_nifty50'   => $this->isInIndex($symbol, 'NIFTY50'),
                'in_banknifty' => $this->isInIndex($symbol, 'BANKNIFTY'),
                'in_sensex'    => $this->isInIndex($symbol, 'SENSEX'),
    
                'price_signal'      => $priceSignal['signal'],
                'price_change_pct'  => $priceSignal['change_pct'],
                'price_today_close' => $priceSignal['today_close'],
                'price_prev_close'  => $priceSignal['prev_close'],
    
                'gann_bias'         => $gannOctave['bias'],
                'gann_zone'         => $gannOctave['zone'],
                'gann_near_level'   => $gannOctave['near_level'],
                'gann_near_price'   => $gannOctave['near_price'],
                'gann_distance'     => $gannOctave['distance'],
                'gann_distance_pct' => $gannOctave['distance_pct'],
                'gann_swing_high'   => $gannOctave['swing_high'],
                'gann_swing_low'    => $gannOctave['swing_low'],
            ];
        }
    
        return $rows;
    }

    private function getOIFlowSignal(
        float $ceT1T2, float $ceT0T1,
        float $peT1T2, float $peT0T1
    ): string {
        // CE unwinding = negative change; PE building = positive change
    
        $ceUnwindBoth   = ($ceT1T2 < -3  && $ceT0T1 < -3);   // CE falling 2 days
        $peBuildBoth    = ($peT1T2 > 3   && $peT0T1 > 3);    // PE rising 2 days
    
        $ceBuildBoth    = ($ceT1T2 > 3   && $ceT0T1 > 3);    // CE rising 2 days
        $peUnwindBoth   = ($peT1T2 < -3  && $peT0T1 < -3);   // PE falling 2 days
    
        // ── STRONG BULL: CE unwinding + PE building across both days ──────
        if ($ceUnwindBoth && $peBuildBoth) return 'STRONG_BULL';
    
        // ── STRONG BEAR: CE building + PE unwinding across both days ──────
        if ($ceBuildBoth && $peUnwindBoth) return 'STRONG_BEAR';
    
        // ── TRAP: large T-1 move completely reversed at T ─────────────────
        // Example: CE was +40% yesterday, -20% today = someone exited hard
        $ceBigReverse = (abs($ceT1T2) > 20 && ($ceT1T2 * $ceT0T1 < 0) && abs($ceT0T1) > 10);
        $peBigReverse = (abs($peT1T2) > 20 && ($peT1T2 * $peT0T1 < 0) && abs($peT0T1) > 10);
        if ($ceBigReverse || $peBigReverse) return 'TRAP';
    
        // ── REVERSAL: today moves opposite to yesterday ────────────────────
        // Both CE and PE change sign today vs yesterday
        $ceReverse = ($ceT1T2 > 5  && $ceT0T1 < -5)  || ($ceT1T2 < -5  && $ceT0T1 > 5);
        $peReverse = ($peT1T2 > 5  && $peT0T1 < -5)  || ($peT1T2 < -5  && $peT0T1 > 5);
        if ($ceReverse && $peReverse) return 'REVERSAL';
    
        // ── CONTINUATION: today continues same direction as yesterday ──────
        $ceContinues = ($ceT1T2 > 3  && $ceT0T1 > 3)  || ($ceT1T2 < -3  && $ceT0T1 < -3);
        $peContinues = ($peT1T2 > 3  && $peT0T1 > 3)  || ($peT1T2 < -3  && $peT0T1 < -3);
    
        if ($ceContinues && $peContinues) return 'CONTINUATION';
        if ($ceContinues || $peContinues) return 'CONTINUATION';
    
        return 'MIXED';
    }

    private function calcOIScore(
        string $baseSignal,
        string $flowSignal,
        float  $diff,
        string $spikeType,
        bool   $isExpiryDay
    ): float {
        $score = 0.0;
    
        // ── Base signal contribution ───────────────────────────────────────
        match ($baseSignal) {
            'BULLISH' => $score += 2.0,
            'BEARISH' => $score -= 2.0,
            default   => null,
        };
    
        // ── Flow signal contribution (weighted heavily) ────────────────────
        match ($flowSignal) {
            'STRONG_BULL'   => $score += 3.0,
            'STRONG_BEAR'   => $score -= 3.0,
            'CONTINUATION'  => $score += ($baseSignal === 'BULLISH' ? 1.5 : -1.5),
            'REVERSAL'      => $score *= 0.5,   // dampen — uncertain
            'TRAP'          => $score  = 0,     // trap = no trade regardless
            'MIXED'         => null,
        };
    
        // ── Gap contribution (bigger gap = stronger signal) ────────────────
        // No edge if gap < 10 (caller already returns WAIT, but penalize score too)
        if ($diff < 10) {
            $score *= 0.3;
        } elseif ($diff > 40) {
            $score *= 1.3;   // explosive
        } elseif ($diff > 25) {
            $score *= 1.15;  // strong
        }
    
        // ── Spike contribution ─────────────────────────────────────────────
        match ($spikeType) {
            'CE_SPIKE'  => $score -= 0.5,   // usually CE write = mildly bearish lean
            'PE_SPIKE'  => $score += 0.5,   // usually PE write = mildly bullish lean
            'DUAL_SPIKE'=> $score *= 1.1,   // amplify existing direction
            'NONE'      => null,
        };
    
        // ── Expiry day boost (opportunity not veto) ────────────────────────
        if ($isExpiryDay && abs($score) >= 3) {
            $score *= 1.2;
        }
    
        return round($score, 2);
    }

    // =========================================================
    //  50 MA — FULLY FIXED
    // =========================================================

    private function calculateRollingMA(array $values, int $period): array
    {
        $ma  = [];
        $n   = count($values);
        $sum = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];
            if ($i >= $period) $sum -= $values[$i - $period];
            $ma[] = ($i >= $period - 1) ? ($sum / $period) : null;
        }

        return $ma;
    }

    private function getFut50MaSignal(string $baseSymbol, string $tradeDate): string
    {
        $maPeriod     = 50;
        $historyStart = Carbon::parse($tradeDate)->subDays(120)->toDateString();

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $historyStart)
            ->whereDate('trade_date', '<=', $tradeDate)
            ->orderBy('trade_date')
            ->orderBy('interval_time')
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
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($candleDate === $tradeDate && $time >= '14:45' && $time <= '15:15') {
                $targetIdx = $idx;
                break;
            }
        }

        // Fallback: last candle on trade date
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

        if ($ma === null)  return 'N/A';
        if ($close > $ma)  return 'BULLISH';
        if ($close < $ma)  return 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  HELPER: safely extract 'Y-m-d' from DATETIME or DATE string
    // =========================================================

    private function dateStr($value): string
    {
        if ($value instanceof Carbon) return $value->toDateString();
        if (is_string($value))        return substr($value, 0, 10);
        return Carbon::parse($value)->toDateString();
    }

    // =========================================================
    //  OI HELPERS
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup CE stronger (CE:+{$cePct}% > PE:+{$pePct}%)", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup PE stronger (PE:+{$pePct}% > CE:+{$cePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding CE stronger (CE:{$cePct}% < PE:{$pePct}%)", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding PE stronger (PE:{$pePct}% < CE:{$cePct}%)", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    private function getOiInterpretation(float $peCeRatio): string
    {
        if ($peCeRatio > 1.2) return 'Put Writing';
        if ($peCeRatio < 0.8) return 'Call Writing';
        return 'Balanced';
    }

    // =========================================================
    //  FUT PRICE COMPARISON
    // =========================================================

    private function getFutPricesFromOhlc(string $baseSymbol, string $date, string $prevDate): array
    {
        try {
            $todayCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->first();

            $futPriceToday = $todayCandle ? (float) $todayCandle->close : 0;

            $prevCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->first();

            $futPricePrev = $prevCandle ? (float) $prevCandle->close : 0;

            $priceChange    = 0;
            $priceChangePct = 0;

            if ($futPricePrev > 0 && $futPriceToday > 0) {
                $priceChange    = $futPriceToday - $futPricePrev;
                $priceChangePct = round(($priceChange / $futPricePrev) * 100, 2);
            }

            $signal = 'N/A';
            if ($futPricePrev > 0 && $futPriceToday > 0) {
                $signal = $futPriceToday > $futPricePrev ? 'BULLISH'
                        : ($futPriceToday < $futPricePrev ? 'BEARISH' : 'NEUTRAL');
            }

            return [
                'fut_price_today'      => round($futPriceToday, 2),
                'fut_price_prev'       => round($futPricePrev, 2),
                'fut_price_change'     => round($priceChange, 2),
                'fut_price_change_pct' => $priceChangePct,
                'fut_price_signal'     => $signal,
            ];

        } catch (\Exception $e) {
            Log::error("getFutPricesFromOhlc ({$baseSymbol}): " . $e->getMessage());
            return ['fut_price_today' => 0, 'fut_price_prev' => 0, 'fut_price_change' => 0, 'fut_price_change_pct' => 0, 'fut_price_signal' => 'N/A'];
        }
    }

    /**
     * MM Trap detection — now expiry-aware.
     *
     * $currentExpiry : active expiry for today (already shifted on expiry day)
     * $prevExpiry    : expiry active on prev day (may differ on rollover)
     *
     * ATM premium comparison uses same-expiry rows on each side so
     * we're not comparing a new weekly's premium to the expired one.
     */
    private function getMmTrapForSymbolDate(
        string $symbol,
        string $date,
        string $prevDate,
        ?string $currentExpiry = null,
        ?string $prevExpiry = null
    ): array {
        $noTrap = [
            'call_trap' => false,
            'put_trap'  => false,
            'type'      => null,
            'detail'    => null,
            'call_wall' => null,
            'put_wall'  => null,
            'fut_price' => null,
        ];

        // ── Futures price at 14:45 today ──────────────────────────────────
        $futRow  = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        $currFut = $futRow ? (float)$futRow->close : null;
        $noTrap['fut_price'] = $currFut;

        // ── All option rows for today (CE/PE), scoped to active expiry ────
        // currentExpiry is already shifted to next-series on expiry day
        $allOptionQuery = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0);
        if ($currentExpiry) $allOptionQuery->whereDate('expiry_date', $currentExpiry);
        $allOptionRows = $allOptionQuery->get(['instrument_type', 'strike', 'close', 'oi', 'strike_position']);

        if ($allOptionRows->isEmpty()) return $noTrap;

        // ── Global OI walls (highest OI strike for CE and PE) ────────────
        $ceOiByStrike = [];
        $peOiByStrike = [];
        foreach ($allOptionRows as $r) {
            $strike = (float)$r->strike;
            if ($strike <= 0) continue;
            if ($r->instrument_type === 'CE') {
                $ceOiByStrike[$strike] = ($ceOiByStrike[$strike] ?? 0) + (int)$r->oi;
            } else {
                $peOiByStrike[$strike] = ($peOiByStrike[$strike] ?? 0) + (int)$r->oi;
            }
        }

        $callWall = $callWallOi = $putWall = $putWallOi = null;
        foreach ($ceOiByStrike as $s => $o) {
            if ($callWallOi === null || $o > $callWallOi) { $callWall = $s; $callWallOi = $o; }
        }
        foreach ($peOiByStrike as $s => $o) {
            if ($putWallOi === null || $o > $putWallOi) { $putWall = $s; $putWallOi = $o; }
        }

        $noTrap['call_wall'] = $callWall;
        $noTrap['put_wall']  = $putWall;

        if (!$currFut) return $noTrap;

        // ── ATM rows today (active expiry) ────────────────────────────────
        $currAtmCe = $allOptionRows->where('instrument_type', 'CE')->where('strike_position', 'ATM')->first();
        $currAtmPe = $allOptionRows->where('instrument_type', 'PE')->where('strike_position', 'ATM')->first();

        // ── Prev day ATM rows (prev expiry) ───────────────────────────────
        $prevOptionQuery = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->where('is_missing', 0);
        if ($prevExpiry) $prevOptionQuery->whereDate('expiry_date', $prevExpiry);
        $prevOptionRows = $prevOptionQuery->get(['instrument_type', 'strike', 'close', 'strike_position']);

        $prevAtmCe = $prevOptionRows->where('instrument_type', 'CE')->where('strike_position', 'ATM')->first();
        $prevAtmPe = $prevOptionRows->where('instrument_type', 'PE')->where('strike_position', 'ATM')->first();

        $trap = $noTrap;

        // ── Call Trap: futures above call wall + CE premium rising ────────
        if ($callWall && $currFut > $callWall) {
            $premRising = $currAtmCe && $prevAtmCe
                && (float)$prevAtmCe->close > 0
                && (((float)$currAtmCe->close - (float)$prevAtmCe->close) / (float)$prevAtmCe->close) > 0.10;

            if ($premRising) {
                $trap['call_trap'] = true;
                $trap['type']      = 'CALL TRAP';
                $trap['detail']    = 'FUT ₹' . number_format($currFut, 0) . ' > Call Wall ₹' . number_format($callWall, 0);
            }
        }

        // ── Put Trap: futures below put wall + PE premium rising ──────────
        if ($putWall && $currFut < $putWall) {
            $premRising = $currAtmPe && $prevAtmPe
                && (float)$prevAtmPe->close > 0
                && (((float)$currAtmPe->close - (float)$prevAtmPe->close) / (float)$prevAtmPe->close) > 0.10;

            if ($premRising) {
                $trap['put_trap'] = true;
                if (!$trap['type']) {
                    $trap['type']   = 'PUT TRAP';
                    $trap['detail'] = 'FUT ₹' . number_format($currFut, 0) . ' < Put Wall ₹' . number_format($putWall, 0);
                }
            }
        }

        return $trap;
    }

    // =========================================================
    //  CALCULATE PROFIT  — BTST WINDOW
    // =========================================================

    public function calculateProfit(Request $request)
    {
        $signals = $request->input('signals', []);

        if (empty($signals)) {
            return response()->json(['success' => false, 'message' => 'No signals provided', 'data' => []]);
        }

        $results = [];

        foreach ($signals as $signal) {
            $idx         = (int)   ($signal['index']       ?? 0);
            $symbol      =          $signal['symbol']       ?? '';
            $tradeDate   =          $signal['date']         ?? '';
            $tradeAction =          $signal['trade_action'] ?? '';
            $spotPrice   = (float) ($signal['spot_price']  ?? 0);

            $placeholder = [
                'index'         => $idx,
                'option_symbol' => null,
                'strike'        => null,
                'option_type'   => null,
                'buy_price'     => 0,
                'lot_size'      => 0,
                'investment'    => 0,
                'exit_price'    => 0, 'exit_pl' => 0, 'exit_roi' => 0,
                'high_price'    => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                'low_price'     => 0, 'low_time'  => null, 'low_pl'  => 0, 'low_roi'  => 0,
                'profit_loss'   => 0,
                'roi_percent'   => 0,
                'error'         => null,
            ];

            if (!$symbol || !$tradeDate || !in_array($tradeAction, ['BUY CE', 'BUY PE'])) {
                $placeholder['error'] = 'WAIT';
                $results[] = $placeholder;
                continue;
            }

            try {
                $optionType = $tradeAction === 'BUY CE' ? 'CE' : 'PE';
                $nextDate   = $this->getNextTradingDate($tradeDate);

                // ── Resolve active expiry (handles expiry-day shift) ─────
                $currentExpiry = $this->resolveActiveExpiry($symbol, $tradeDate);

                // ── Find ATM option on signal day at 14:45 ──────────────
                $atmQuery = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '14:45:00'");

                // Scope to active expiry if resolved
                if ($currentExpiry) $atmQuery->whereDate('expiry_date', $currentExpiry);

                $atmRow = $atmQuery->orderBy('expiry_date')->first();

                // Fallback: nearest strike to spot price
                if (!$atmRow) {
                    $atmFallback = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '14:45:00'")
                        ->whereNotNull('strike')
                        ->whereNotNull('expiry_date');

                    if ($currentExpiry) $atmFallback->whereDate('expiry_date', $currentExpiry);

                    $atmRow = $atmFallback->orderByRaw('ABS(strike - ?)', [$spotPrice])
                        ->orderBy('expiry_date')
                        ->first();
                }

                if (!$atmRow) {
                    $placeholder['error'] = 'NO_ATM_ROW';
                    $results[] = $placeholder;
                    continue;
                }

                $strike     = $atmRow->strike;
                $expiryDate = $this->dateStr($atmRow->expiry_date);

                $buyPrice = (float) ($atmRow->close ?? 0);
                if ($buyPrice <= 0) $buyPrice = (float) ($atmRow->open ?? 0);

                if ($buyPrice <= 0) {
                    $placeholder['error']         = 'NO_BUY_PRICE';
                    $placeholder['option_symbol'] = $atmRow->trading_symbol ?? null;
                    $placeholder['strike']        = $strike;
                    $placeholder['option_type']   = $optionType;
                    $results[] = $placeholder;
                    continue;
                }

                // ── EXIT PRICE: next trading day 09:30 open ─────────────
                $exitRow = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $nextDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = '09:30:00'")
                    ->first();

                $exitPrice = 0;
                if ($exitRow) {
                    $exitPrice = (float) ($exitRow->open ?? 0);
                    if ($exitPrice <= 0) $exitPrice = (float) ($exitRow->close ?? 0);
                }

                // ── Window candles: signal day 15:15 → next day 09:30 ───
                $windowCandles = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->where('is_missing', 0)
                    ->where(function ($q) use ($tradeDate, $nextDate) {
                        $q->where(function ($q2) use ($tradeDate) {
                            $q2->whereDate('trade_date', $tradeDate)
                               ->whereRaw("TIME(interval_time) >= '15:15:00'");
                        })->orWhere(function ($q2) use ($nextDate) {
                            $q2->whereDate('trade_date', $nextDate)
                               ->whereRaw("TIME(interval_time) <= '09:30:00'");
                        });
                    })
                    ->get(['high', 'low', 'interval_time']);

                $nextDayHigh = $exitRow ? (float) ($exitRow->high ?? 0) : 0;
                $nextDayLow  = $exitRow ? (float) ($exitRow->low  ?? 0) : 0;

                if ($windowCandles->isNotEmpty()) {
                    $highRow   = $windowCandles->sortByDesc('high')->first();
                    $lowRow    = $windowCandles->sortBy('low')->first();
                    $highPrice = (float) $highRow->high;
                    $highTime  = Carbon::parse($highRow->interval_time)->format('H:i');
                    $lowPrice  = (float) $lowRow->low;
                    $lowTime   = Carbon::parse($lowRow->interval_time)->format('H:i');
                } else {
                    $highPrice = $nextDayHigh > 0 ? $nextDayHigh : $buyPrice;
                    $highTime  = null;
                    $lowPrice  = $nextDayLow  > 0 ? $nextDayLow  : $buyPrice;
                    $lowTime   = null;
                }

                // ── P/L calculations ─────────────────────────────────────
                $lotSize    = $this->getLotSize($symbol);
                $investment = round($buyPrice * $lotSize, 2);

                $exitPL  = $exitPrice > 0 ? round(($exitPrice - $buyPrice) * $lotSize, 2) : 0;
                $exitRoi = ($investment > 0 && $exitPrice > 0)
                    ? round(($exitPL / $investment) * 100, 2) : 0;

                $highPL  = round(($highPrice - $buyPrice) * $lotSize, 2);
                $highRoi = $investment > 0 ? round(($highPL / $investment) * 100, 2) : 0;
                $lowPL   = round(($lowPrice - $buyPrice) * $lotSize, 2);
                $lowRoi  = $investment > 0 ? round(($lowPL / $investment) * 100, 2) : 0;

                $results[] = [
                    'index'         => $idx,
                    'option_symbol' => $atmRow->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
                    'strike'        => $strike,
                    'option_type'   => $optionType,
                    'lot_size'      => $lotSize,
                    'investment'    => $investment,
                    'buy_price'     => round($buyPrice, 2),
                    'exit_price'    => round($exitPrice, 2),
                    'exit_pl'       => $exitPL,
                    'exit_roi'      => $exitRoi,
                    'high_price'    => round($highPrice, 2),
                    'high_time'     => $highTime,
                    'high_pl'       => $highPL,
                    'high_roi'      => $highRoi,
                    'low_price'     => round($lowPrice, 2),
                    'low_time'      => $lowTime,
                    'low_pl'        => $lowPL,
                    'low_roi'       => $lowRoi,
                    'profit_loss'   => $exitPL,
                    'roi_percent'   => $exitRoi,
                    'error'         => null,
                ];

            } catch (\Exception $e) {
                Log::error("EOD Profit row error (idx={$idx}): " . $e->getMessage());
                $placeholder['error'] = 'EXCEPTION: ' . $e->getMessage();
                $results[] = $placeholder;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $results,
            'message' => count($results) . ' profit records calculated',
        ]);
    }

    // =========================================================
    //  LOT SIZE HELPER
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $lots = [
            'NIFTY'      => 25,
            'BANKNIFTY'  => 15,
            'FINNIFTY'   => 25,
            'MIDCPNIFTY' => 50,
            'SENSEX'     => 10,
            'BANKEX'     => 15,
        ];

        $instrument = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->value('lot_size');

        if ($instrument) return (int) $instrument;

        return $lots[$symbol] ?? 1;
    }

    // =========================================================
    //  CONFIG CRUD
    // =========================================================

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
            'signal_mode'       => 'required|in:align,opposite',
            'status'            => 'required|in:1,0',
            'rank1_ce_quantity' => 'nullable|integer|min:0',
            'rank1_pe_quantity' => 'nullable|integer|min:0',
            'rank2_ce_quantity' => 'nullable|integer|min:0',
            'rank2_pe_quantity' => 'nullable|integer|min:0',
            'rank3_ce_quantity' => 'nullable|integer|min:0',
            'rank3_pe_quantity' => 'nullable|integer|min:0',
            'rank4_ce_quantity' => 'nullable|integer|min:0',
            'rank4_pe_quantity' => 'nullable|integer|min:0',
            // allowed_symbols: array of strings or null/empty
            'allowed_symbols'   => 'nullable|array',
            'allowed_symbols.*' => 'string|max:32',
        ]);
 
        try {
            // Normalize allowed_symbols:
            // - empty array submitted  → null (means "all symbols")
            // - non-empty array        → store as-is (uppercase)
            $allowedSymbols = null;
            if ($request->has('allowed_symbols') && is_array($request->allowed_symbols) && count($request->allowed_symbols) > 0) {
                $allowedSymbols = array_values(array_map('strtoupper', array_filter($request->allowed_symbols)));
            }
 
            OIIVAutoConfig::create([
                'user_id'            => Auth::id(),
                'broker_api_id'      => $request->broker_api_id,
                'order_type'         => $request->order_type,
                'product'            => $request->product,
                'disc_ltp'           => $request->disc_ltp,
                'index_quantity'     => $request->index_ce_quantity,
                'stock_quantity'     => $request->stock_ce_quantity,
                'index_ce_quantity'  => $request->index_ce_quantity,
                'index_pe_quantity'  => $request->index_pe_quantity,
                'stock_ce_quantity'  => $request->stock_ce_quantity,
                'stock_pe_quantity'  => $request->stock_pe_quantity,
                'signal_mode'        => $request->signal_mode,
                'status'             => $request->status,
                'strong_ce_quantity' => 0,
                'strong_pe_quantity' => 0,
                'rank1_ce_quantity'  => $request->rank1_ce_quantity ?? 0,
                'rank1_pe_quantity'  => $request->rank1_pe_quantity ?? 0,
                'rank2_ce_quantity'  => $request->rank2_ce_quantity ?? 0,
                'rank2_pe_quantity'  => $request->rank2_pe_quantity ?? 0,
                'rank3_ce_quantity'  => $request->rank3_ce_quantity ?? 0,
                'rank3_pe_quantity'  => $request->rank3_pe_quantity ?? 0,
                'rank4_ce_quantity'  => $request->rank4_ce_quantity ?? 0,
                'rank4_pe_quantity'  => $request->rank4_pe_quantity ?? 0,
                'allowed_symbols'    => $allowedSymbols,   // null = all, array = filtered
                'config_type'        => 'eod',
            ]);
 
            $notify[] = ['success', 'Auto trading configuration created successfully!'];
            return back()->withNotify($notify);
 
        } catch (\Exception $e) {
            Log::error('Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
            'signal_mode'       => 'required|in:align,opposite',
            'status'            => 'required|in:1,0',
            'rank1_ce_quantity' => 'nullable|integer|min:0',
            'rank1_pe_quantity' => 'nullable|integer|min:0',
            'rank2_ce_quantity' => 'nullable|integer|min:0',
            'rank2_pe_quantity' => 'nullable|integer|min:0',
            'rank3_ce_quantity' => 'nullable|integer|min:0',
            'rank3_pe_quantity' => 'nullable|integer|min:0',
            'rank4_ce_quantity' => 'nullable|integer|min:0',
            'rank4_pe_quantity' => 'nullable|integer|min:0',
            'allowed_symbols'   => 'nullable|array',
            'allowed_symbols.*' => 'string|max:32',
        ]);
 
        $config = OIIVAutoConfig::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
 
        // Normalize allowed_symbols
        $allowedSymbols = null;
        if ($request->has('allowed_symbols') && is_array($request->allowed_symbols) && count($request->allowed_symbols) > 0) {
            $allowedSymbols = array_values(array_map('strtoupper', array_filter($request->allowed_symbols)));
        }
 
        $config->update([
            'broker_api_id'     => $request->broker_api_id,
            'order_type'        => $request->order_type,
            'product'           => $request->product,
            'disc_ltp'          => $request->disc_ltp,
            'index_ce_quantity' => $request->index_ce_quantity,
            'index_pe_quantity' => $request->index_pe_quantity,
            'stock_ce_quantity' => $request->stock_ce_quantity,
            'stock_pe_quantity' => $request->stock_pe_quantity,
            'signal_mode'       => $request->signal_mode,
            'status'            => $request->status,
            'rank1_ce_quantity' => $request->rank1_ce_quantity ?? 0,
            'rank1_pe_quantity' => $request->rank1_pe_quantity ?? 0,
            'rank2_ce_quantity' => $request->rank2_ce_quantity ?? 0,
            'rank2_pe_quantity' => $request->rank2_pe_quantity ?? 0,
            'rank3_ce_quantity' => $request->rank3_ce_quantity ?? 0,
            'rank3_pe_quantity' => $request->rank3_pe_quantity ?? 0,
            'rank4_ce_quantity' => $request->rank4_ce_quantity ?? 0,
            'rank4_pe_quantity' => $request->rank4_pe_quantity ?? 0,
            'allowed_symbols'   => $allowedSymbols,
        ]);
 
        $notify[] = ['success', 'Configuration updated successfully!'];
        return back()->withNotify($notify);
    }

    public function toggleStatus($id)
    {
        try {
            $config = OIIVAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
            $config->status = !$config->status;
            $config->save();
            $notify[] = ['success', 'Configuration ' . ($config->status ? 'activated' : 'deactivated') . ' successfully!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating configuration.'];
            return back()->withNotify($notify);
        }
    }

    public function destroy($id)
    {
        try {
            $config = OIIVAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
            $pendingOrders = $config->orders()->where('is_order_placed', false)->where('status', true)->count();
            if ($pendingOrders > 0) {
                $notify[] = ['error', "Cannot delete. {$pendingOrders} orders pending."];
                return back()->withNotify($notify);
            }
            $config->delete();
            $notify[] = ['success', 'Configuration deleted successfully!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error deleting configuration.'];
            return back()->withNotify($notify);
        }
    }

    // =========================================================
    //  DATE HELPERS
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

    private function getNextTradingDate(string $date): string
    {
        $next     = Carbon::parse($date)->addDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$next->isWeekend() && !$this->isHoliday($next->format('Y-m-d'))) {
                return $next->format('Y-m-d');
            }
            $next->addDay();
            $attempts++;
        }
        return Carbon::parse($date)->addDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    public function runSignalsManually(Request $request)
    {
        try {
            $testDate = $request->get('test_date');

            $helper = new \App\Helpers\PECEAutoTradingHelper();
            $helper->processSignals($testDate ?: null);
            $helper->placeOrders($testDate ?: null);

            $notify[] = ['success', 'EOD signals processed and orders placed successfully!'];
        } catch (\Exception $e) {
            \Log::error('Manual EOD trigger: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
        }

        return back()->withNotify($notify);
    }
}