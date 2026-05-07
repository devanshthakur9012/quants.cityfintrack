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
use KiteConnect\KiteConnect;

/**
 * OIIVAutoController
 *
 * ALL data now reads from option_ohlc_data (OptionOhlcData model).
 * OptionStrike / option_strikes table is no longer used anywhere here.
 *
 * EOD signal logic:
 *   open OI  = previous trading day 15:15 candle
 *   close OI = today 15:00 candle
 *
 * FUT price columns:
 *   "today"  = current date 15:00 FUT close
 *   "prev"   = previous trading date 15:15 FUT close
 */
class OIIVAutoController extends Controller
{
    const CLOSE_TIME_HOUR   = 15;
    const CLOSE_TIME_MINUTE = 0;
    const OPEN_TIME_HOUR    = 9;
    const OPEN_TIME_MINUTE  = 30;

    private $kite = null;

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
        $pageTitle = 'PE/CE Ratio Analysis';
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
    //  SYMBOLS DROPDOWN
    // =========================================================

    /**
     * Returns distinct base_symbol list from option_ohlc_data (FUT rows).
     */
    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json([
            'success' => true,
            'symbols' => $symbols,
        ]);
    }

    // =========================================================
    //  OI+IV ANALYSIS  (index page endpoint)
    // =========================================================

    /**
     * Analyze OI+IV signals — raw FUT price movement viewer.
     *
     * Open  = today 09:30 candle open  (from option_ohlc_data)
     * Close = today 15:00 candle close (from option_ohlc_data)
     */
    public function analyzeSignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both From and To dates', 'data' => []]);
            }

            Log::info('=== OI+IV ANALYSIS START ===', compact('fromDate', 'toDate'));

            // Fetch today 09:30 open candles
            $openQuery = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->whereRaw("TIME(interval_time) = '09:30:00'");

            if (!empty($selectedSymbols)) {
                $openQuery->whereIn('base_symbol', $selectedSymbols);
            }

            $openCandles = $openQuery->get()->keyBy(fn($c) => $c->base_symbol . '|' . $c->trade_date);

            // Fetch today 15:00 close candles
            $closeQuery = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->whereRaw("TIME(interval_time) = '15:00:00'");

            if (!empty($selectedSymbols)) {
                $closeQuery->whereIn('base_symbol', $selectedSymbols);
            }

            $closeCandles = $closeQuery->get()->keyBy(fn($c) => $c->base_symbol . '|' . $c->trade_date);

            // Also need FUT OI change — compare today 15:00 OI vs prev 15:15 OI
            // Collect all unique dates in range to look up prev dates
            $tradeDates = $closeCandles->pluck('trade_date')->unique()->sort()->values()->toArray();
            $prevDateMap = [];
            foreach ($tradeDates as $d) {
                $prevDateMap[$d] = $this->getPreviousTradingDate($d);
            }

            // Fetch prev 15:15 FUT candles for OI comparison
            $prevDates   = array_unique(array_values($prevDateMap));
            $prevCandles = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereIn('trade_date', $prevDates)
                ->whereRaw("TIME(interval_time) = '15:15:00'")
                ->get()
                ->keyBy(fn($c) => $c->base_symbol . '|' . $c->trade_date);

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

                // FUT OI change % (today 15:00 vs prev 15:15)
                $todayOI  = (float) ($closeCandle->oi ?? 0);
                $prevOI   = $prevCandle ? (float) ($prevCandle->oi ?? 0) : 0;
                $oiChange = $prevOI > 0 ? (($todayOI - $prevOI) / $prevOI) * 100 : 0;
                $oiDirection = $oiChange > 0 ? 'POSITIVE' : ($oiChange < 0 ? 'NEGATIVE' : 'FLAT');

                $signal = $this->determineSignalForDisplay($priceDirection, $oiDirection);

                $results[] = [
                    'date'                => $date,
                    'symbol'              => $symbol,
                    'fut_symbol'          => $closeCandle->trading_symbol ?? $symbol,
                    'spot_price'          => round($closePrice, 2),
                    'open_price'          => round($openPrice, 2),
                    'current_price'       => round($closePrice, 2),
                    'price_change'        => round($priceChange, 2),
                    'price_change_percent' => round($priceChangePct, 2),
                    'price_direction'     => $priceDirection,
                    // Lock status no longer relevant — always "from ohlc"
                    'is_price_locked'     => true,
                    'is_open_locked'      => true,
                    'lock_time'           => '15:00:00',
                    'open_lock_time'      => '09:30:00',
                    // OI
                    'oi_change_pct'  => round($oiChange, 2),
                    'oi_direction'   => $oiDirection,
                    'fut_oi'         => (int) $todayOI,
                    'fut_oi_prev'    => (int) $prevOI,
                    'fut_oi_change_pct' => round($oiChange, 2),
                    'fut_oi_signal'  => 'N/A',
                    'fut_oi_strength' => 'N/A',
                    // CE / PE (not available from FUT-only query; set N/A)
                    'ce_oi_signal'   => 'N/A', 'ce_oi_strength' => 'N/A',
                    'ce_oi_change_pct' => 0,   'ce_oi' => 0, 'ce_oi_prev' => 0,
                    'ce_iv_signal'   => 'N/A', 'ce_iv_strength' => 'N/A',
                    'ce_iv_change_pct' => 0,   'ce_iv' => 0, 'ce_iv_prev' => 0,
                    'pe_oi_signal'   => 'N/A', 'pe_oi_strength' => 'N/A',
                    'pe_oi_change_pct' => 0,   'pe_oi' => 0, 'pe_oi_prev' => 0,
                    'pe_iv_signal'   => 'N/A', 'pe_iv_strength' => 'N/A',
                    'pe_iv_change_pct' => 0,   'pe_iv' => 0, 'pe_iv_prev' => 0,
                    // Signal
                    'signal'         => $signal['signal']   ?? 'NO_SIGNAL',
                    'signal_type'    => $signal['type']     ?? 'NONE',
                    'signal_reason'  => $signal['reason']   ?? 'No clear signal',
                    'signal_scenario' => $signal['scenario'] ?? 'N/A',
                    'order_picked'   => ($signal['signal'] ?? 'NO_SIGNAL') !== 'NO_SIGNAL' ? 'YES' : 'NO',
                ];
            }

            // Sort date desc, symbol asc
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

    /**
     * Determine signal from price direction + OI direction (used by index page).
     */
    private function determineSignalForDisplay(string $priceDirection, string $oiDirection): array
    {
        if ($priceDirection === 'UP'   && $oiDirection === 'NEGATIVE') return ['signal' => 'BUY_CE', 'type' => 'CE', 'reason' => 'Short Covering - Bullish',  'scenario' => 'Price Up + OI Negative'];
        if ($priceDirection === 'UP'   && $oiDirection === 'POSITIVE') return ['signal' => 'BUY_CE', 'type' => 'CE', 'reason' => 'Long Buildup - Bullish',    'scenario' => 'Price Up + OI Positive'];
        if ($priceDirection === 'DOWN' && $oiDirection === 'NEGATIVE') return ['signal' => 'BUY_PE', 'type' => 'PE', 'reason' => 'Long Unwinding - Bearish',  'scenario' => 'Price Down + OI Negative'];
        if ($priceDirection === 'DOWN' && $oiDirection === 'POSITIVE') return ['signal' => 'BUY_PE', 'type' => 'PE', 'reason' => 'Short Buildup - Bearish',   'scenario' => 'Price Down + OI Positive'];
        return ['signal' => 'NO_SIGNAL', 'type' => 'NONE', 'reason' => 'No clear signal', 'scenario' => 'N/A'];
    }

    /**
     * Clear price cache — no longer stores locked prices in OptionStrike,
     * so this now just returns a confirmation (cache lives in option_ohlc_data
     * which is written by the CollectOptionOhlcData command and never needs clearing).
     */
    public function clearPriceCache(Request $request)
    {
        return response()->json([
            'success'  => true,
            'message'  => 'Price data is sourced live from option_ohlc_data — no cache to clear. Run "View Data" to refresh.',
            'affected' => 0,
        ]);
    }

    // =========================================================
    //  PE/CE ANALYSIS  (main analysis endpoint)
    // =========================================================

    /**
     * Aggregate CE/PE OI change % from option_ohlc_data and return signal rows.
     *
     * Open OI  = previous trading day 15:15 candle  (same as EOD helper)
     * Close OI = today 15:00 candle
     */
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

            Log::info('=== PE/CE ANALYSIS START ===', compact('fromDate', 'toDate'));

            // Collect all unique trade dates in the range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $results = [];

            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                $rows     = $this->buildSignalRowsForDate($date, $prevDate, $selectedSymbols, $filterAction);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }

            // Sort by date desc, symbol asc
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
            Log::error('PE/CE Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Build signal rows for a single date.
     * Reads today 15:00 + prev 15:15 candles, aggregates OI, computes signals.
     */
    private function buildSignalRowsForDate(string $date, string $prevDate, array $symbolFilter, ?string $actionFilter): array
    {
        // ── Fetch today 15:00 candles ────────────────────────────────────
        $todayQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:00:00'");

        if (!empty($symbolFilter)) {
            $todayQuery->whereIn('base_symbol', $symbolFilter);
        }

        $todayCandles = $todayQuery->get();

        if ($todayCandles->isEmpty()) return [];

        // ── Fetch prev 15:15 candles for CE/PE ──────────────────────────
        $prevSymbols = $todayCandles->pluck('base_symbol')->unique()->values()->toArray();

        $prevCandles = OptionOhlcData::whereDate('trade_date', $prevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $prevSymbols)
            ->get();

        // ── Group candles ────────────────────────────────────────────────
        // todayGrouped[symbol][type][] = candle
        $todayGrouped = [];
        foreach ($todayCandles as $c) {
            $todayGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        // prevByStrike[symbol][type][strike] = candle
        $prevByStrike = [];
        foreach ($prevCandles as $c) {
            $prevByStrike[$c->base_symbol][$c->instrument_type][(string) $c->strike] = $c;
        }

        $rows = [];

        foreach ($todayGrouped as $symbol => $typeMap) {

            // FUT candle
            $futCandle = ($typeMap['FUT'] ?? [])[0] ?? null;
            if (!$futCandle || (float) $futCandle->close <= 0) continue;

            $currentClose = (float) $futCandle->close;

            // Aggregate CE OI
            [$ceOpenOI, $ceCurOI] = $this->sumOIVsPrev(
                $typeMap['CE'] ?? [],
                $prevByStrike[$symbol]['CE'] ?? []
            );

            // Aggregate PE OI
            [$peOpenOI, $peCurOI] = $this->sumOIVsPrev(
                $typeMap['PE'] ?? [],
                $prevByStrike[$symbol]['PE'] ?? []
            );

            if ($ceCurOI == 0 && $peCurOI == 0) continue;

            // OI change %
            $ceOiPct = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 2) : 0;
            $peOiPct = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 2) : 0;

            // Signals
            $oiSignal  = $this->getOISignal($ceOiPct, $peOiPct);
            $peCeRatio = $ceCurOI > 0 ? round($peCurOI / $ceCurOI, 2) : 0;

            $tradeAction = match($oiSignal['signal']) {
                'BULLISH' => 'BUY CE',
                'BEARISH' => 'BUY PE',
                default   => 'WAIT',
            };

            // Apply action filter
            if (!empty($actionFilter) && $tradeAction !== $actionFilter) continue;

            // FUT price comparison (today 15:00 vs prev 15:15)
            $futPrices = $this->getFutPricesFromOhlc(
                $symbol,
                $futCandle->trading_symbol,
                $date,
                $prevDate
            );

            // Stronger side
            $absCe       = abs($ceOiPct);
            $absPe       = abs($peOiPct);
            $strongerSide = $absCe > $absPe ? 'CE' : ($absPe > $absCe ? 'PE' : 'EQUAL');

            // OI interpretation (legacy field, derived)
            $oiInterpretation = $this->getOiInterpretation($peCeRatio);

            // Rank
            $diff      = abs($ceOiPct - $peOiPct);
            $strengthRank = $diff > 40 ? 1 : ($diff > 25 ? 2 : ($diff > 10 ? 3 : ($diff > 5 ? 4 : null)));

            $rows[] = [
                // Basic
                'date'    => $date,
                'symbol'  => $symbol,
                'fut_symbol' => $futCandle->trading_symbol ?? $symbol,

                // CE OI
                'ce_oi'            => $ceCurOI,
                'ce_oi_prev'       => $ceOpenOI,
                'ce_oi_change_pct' => $ceOiPct,

                // PE OI
                'pe_oi'            => $peCurOI,
                'pe_oi_prev'       => $peOpenOI,
                'pe_oi_change_pct' => $peOiPct,

                // FUT OI (today candle)
                'fut_oi'            => (int) ($futCandle->oi ?? 0),
                'fut_oi_prev'       => 0,    // prev day FUT OI not aggregated here
                'fut_oi_change_pct' => 0,

                // Aliases used by blade (ce_oi_change_pct_fut / pe_oi_change_pct_fut)
                'ce_oi_change_pct_fut' => $ceOiPct,
                'pe_oi_change_pct_fut' => $peOiPct,

                // Signals
                'oi_condition'     => $oiSignal['condition'],
                'oi_interpretation' => $oiInterpretation,
                'final_sentiment'  => $oiSignal['signal'],
                'options_sentiment' => $oiSignal['signal'],
                'futures_oi_view'  => 'N/A',
                'trade_action'     => $tradeAction,
                'stronger_side'    => $strongerSide,
                'strength_rank'    => $strengthRank,

                // Ratio
                'pe_ce_ratio' => $peCeRatio,

                // Colors
                'sentiment_color' => $oiSignal['signal'] === 'BULLISH' ? 'success' : ($oiSignal['signal'] === 'BEARISH' ? 'danger' : 'secondary'),
                'action_color'    => $tradeAction === 'BUY CE' ? 'success' : ($tradeAction === 'BUY PE' ? 'danger' : 'warning'),

                // BTST (not available from ohlc, blanked)
                'btst_signal'     => 'N/A',
                'btst_confidence' => 0,
                'btst_reason'     => 'N/A',

                // Prices
                'spot_price' => round($currentClose, 2),

                // FUT price columns
                'fut_price_today'      => $futPrices['fut_price_today'],
                'fut_price_prev'       => $futPrices['fut_price_prev'],
                'fut_price_change'     => $futPrices['fut_price_change'],
                'fut_price_change_pct' => $futPrices['fut_price_change_pct'],
                'fut_price_signal'     => $futPrices['fut_price_signal'],

                // Profit (filled later by calculateBulkProfit)
                'option_symbol'          => 'N/A',
                'investment'             => 0,
                'entry_price'            => 0,
                'exit_price'             => 0,
                'highest_price'          => 0,
                'profit_loss'            => 0,
                'highest_profit'         => 0,
                'return_percent'         => 0,
                'highest_return_percent' => 0,
                'has_profit_data'        => false,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  OI HELPERS
    // =========================================================

    /**
     * Sum today's OI vs previous day's OI across matched strikes.
     * Returns [prevOiTotal, todayOiTotal].
     */
    private function sumOIVsPrev(array $todayCandles, array $prevByStrike): array
    {
        $prevOI  = 0;
        $todayOI = 0;

        foreach ($todayCandles as $tc) {
            $key     = (string) $tc->strike;
            $todayOI += (int) ($tc->oi ?? 0);
            if (isset($prevByStrike[$key])) {
                $prevOI += (int) ($prevByStrike[$key]->oi ?? 0);
            }
        }

        return [$prevOI, $todayOI];
    }

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];

        if ($ceUp && $peUp) {
            return $cePct > $pePct
                ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)']
                : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)'];
        }

        if ($ceDown && $peDown) {
            return $cePct < $pePct
                ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)']
                : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)'];
        }

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    private function getOiInterpretation(float $peCeRatio): string
    {
        if ($peCeRatio > 1.2) return 'Put Writing';
        if ($peCeRatio < 0.8) return 'Call Writing';
        return 'Balanced';
    }

    // =========================================================
    //  FUT PRICE COMPARISON  (from option_ohlc_data)
    // =========================================================

    /**
     * Today 15:00 FUT close vs previous day 15:15 FUT close.
     */
    private function getFutPricesFromOhlc(
        string $baseSymbol,
        string $futTradingSymbol,
        string $date,
        string $prevDate
    ): array {
        try {
            // Today 15:00
            $todayCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->first();

            $futPriceToday = $todayCandle ? (float) $todayCandle->close : 0;

            // Prev 15:15
            $prevCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) = '15:15:00'")
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
            Log::error("getFutPricesFromOhlc error ({$baseSymbol}): " . $e->getMessage());
            return ['fut_price_today' => 0, 'fut_price_prev' => 0, 'fut_price_change' => 0, 'fut_price_change_pct' => 0, 'fut_price_signal' => 'N/A'];
        }
    }

    // =========================================================
    //  PROFIT CALCULATION
    // =========================================================

    public function calculateBulkProfit(Request $request)
    {
        try {
            Log::info('=== BULK PROFIT CALCULATION START ===');

            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');

            // Re-build signal rows for the date range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $signals = [];
            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                $rows     = $this->buildSignalRowsForDate($date, $prevDate, $selectedSymbols, $filterAction);
                foreach ($rows as $row) {
                    if ($row['trade_action'] !== 'WAIT') {
                        $signals[] = $row;
                    }
                }
            }

            Log::info("Processing " . count($signals) . " signals for profit calculation");

            $this->initializeKite();

            $results              = [];
            $totalProfit          = 0;
            $totalHighestProfit   = 0;
            $totalInvestment      = 0;
            $totalTrades          = 0;
            $winningTrades        = 0;
            $losingTrades         = 0;
            $highestWinningTrades = 0;

            foreach ($signals as $signal) {
                $profitData = $this->calculateSignalProfit($signal);
                if ($profitData) {
                    $profitData['symbol'] = $signal['symbol'];
                    $profitData['date']   = $signal['date'];
                    $results[]            = $profitData;
                    $totalProfit          += $profitData['profit_loss'];
                    $totalHighestProfit   += $profitData['highest_profit'];
                    $totalInvestment      += $profitData['investment'];
                    $totalTrades++;
                    if ($profitData['profit_loss'] > 0)          $winningTrades++;
                    elseif ($profitData['profit_loss'] < 0)      $losingTrades++;
                    if ($profitData['highest_profit'] > 0)       $highestWinningTrades++;
                }
            }

            $winRate         = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;
            $highestWinRate  = $totalTrades > 0 ? round(($highestWinningTrades / $totalTrades) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data'    => $results,
                'summary' => [
                    'total_trades'         => $totalTrades,
                    'winning_trades'       => $winningTrades,
                    'losing_trades'        => $losingTrades,
                    'win_rate'             => $winRate,
                    'highest_win_rate'     => $highestWinRate,
                    'total_investment'     => round($totalInvestment, 2),
                    'total_profit_loss'    => round($totalProfit, 2),
                    'total_highest_profit' => round($totalHighestProfit, 2),
                    'avg_profit_loss'      => $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0,
                    'avg_highest_profit'   => $totalTrades > 0 ? round($totalHighestProfit / $totalTrades, 2) : 0,
                    'roi_percent'          => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0,
                    'highest_roi_percent'  => $totalInvestment > 0 ? round(($totalHighestProfit / $totalInvestment) * 100, 2) : 0,
                ],
                'message' => 'Profit calculation completed',
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk Profit Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function calculateProfit(Request $request)
    {
        try {
            $signals = $request->input('signals', []);
            if (empty($signals)) {
                return response()->json(['success' => false, 'message' => 'No signals provided', 'data' => []]);
            }

            $this->initializeKite();

            $results              = [];
            $totalProfit          = 0;
            $totalHighestProfit   = 0;
            $totalInvestment      = 0;
            $totalTrades          = 0;
            $winningTrades        = 0;
            $losingTrades         = 0;
            $highestWinningTrades = 0;

            foreach ($signals as $signal) {
                if (!isset($signal['trade_action']) || $signal['trade_action'] === 'N/A') continue;
                $result = $this->calculateSignalProfit($signal);
                if ($result) {
                    $results[]           = $result;
                    $totalProfit         += $result['profit_loss'];
                    $totalHighestProfit  += $result['highest_profit'];
                    $totalInvestment     += $result['investment'];
                    $totalTrades++;
                    if ($result['profit_loss'] > 0)          $winningTrades++;
                    elseif ($result['profit_loss'] < 0)      $losingTrades++;
                    if ($result['highest_profit'] > 0)       $highestWinningTrades++;
                }
            }

            $winRate        = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;
            $highestWinRate = $totalTrades > 0 ? round(($highestWinningTrades / $totalTrades) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data'    => $results,
                'summary' => [
                    'total_trades'         => $totalTrades,
                    'winning_trades'       => $winningTrades,
                    'losing_trades'        => $losingTrades,
                    'win_rate'             => $winRate,
                    'highest_win_rate'     => $highestWinRate,
                    'total_investment'     => round($totalInvestment, 2),
                    'total_profit_loss'    => round($totalProfit, 2),
                    'total_highest_profit' => round($totalHighestProfit, 2),
                    'avg_profit_loss'      => $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0,
                    'avg_highest_profit'   => $totalTrades > 0 ? round($totalHighestProfit / $totalTrades, 2) : 0,
                    'roi_percent'          => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0,
                    'highest_roi_percent'  => $totalInvestment > 0 ? round(($totalHighestProfit / $totalInvestment) * 100, 2) : 0,
                ],
                'message' => 'Profit calculation completed',
            ]);

        } catch (\Exception $e) {
            Log::error('Profit Calculation Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  PROFIT CALCULATION HELPERS
    // =========================================================

    private function calculateSignalProfit(array $signal): ?array
    {
        try {
            $optionSymbol = $this->getOptionSymbolFromSignal($signal);
            if (!$optionSymbol) return null;

            $signalDate = Carbon::parse($signal['date']);
            $instrument = \App\Models\ZerodhaInstrument::where('trading_symbol', $optionSymbol)
                ->where('exchange', 'NFO')->first();
            if (!$instrument) return null;

            $entryDateTime = Carbon::parse($signalDate->format('Y-m-d') . ' 15:00:00', 'Asia/Kolkata');
            $exitDateTime  = $this->getNextTradingDay($signalDate)->setTime(15, 0, 0);

            $buyPrice  = $this->getOptionPriceAtTime($instrument, $entryDateTime);
            $sellPrice = $this->getOptionPriceAtTime($instrument, $exitDateTime);

            if (!$buyPrice || $buyPrice <= 0 || !$sellPrice || $sellPrice <= 0) return null;

            $highestPriceData = $this->getHighestPriceBetween($instrument, $entryDateTime, $exitDateTime);
            if ($highestPriceData['price'] <= 0) {
                $highestPriceData['price'] = max($buyPrice, $sellPrice);
            }

            $quantity     = $instrument->lot_size ?? 1;
            $profitLoss   = ($sellPrice - $buyPrice) * $quantity;
            $highestProfit = ($highestPriceData['price'] - $buyPrice) * $quantity;
            $investment   = $buyPrice * $quantity;

            return [
                'option_symbol'          => $optionSymbol,
                'signal_date'            => $signalDate->format('Y-m-d'),
                'entry_time'             => $entryDateTime->format('Y-m-d H:i:s'),
                'exit_time'              => $exitDateTime->format('Y-m-d H:i:s'),
                'buy_price'              => round($buyPrice, 2),
                'sell_price'             => round($sellPrice, 2),
                'highest_price'          => round($highestPriceData['price'], 2),
                'highest_price_time'     => $highestPriceData['time'],
                'quantity'               => $quantity,
                'investment'             => round($investment, 2),
                'profit_loss'            => round($profitLoss, 2),
                'highest_profit'         => round($highestProfit, 2),
                'profit_loss_per_lot'    => round($sellPrice - $buyPrice, 2),
                'highest_profit_per_lot' => round($highestPriceData['price'] - $buyPrice, 2),
                'return_percent'         => $buyPrice > 0 ? round((($sellPrice - $buyPrice) / $buyPrice) * 100, 2) : 0,
                'highest_return_percent' => $buyPrice > 0 ? round((($highestPriceData['price'] - $buyPrice) / $buyPrice) * 100, 2) : 0,
            ];

        } catch (\Exception $e) {
            Log::error('calculateSignalProfit error: ' . $e->getMessage());
            return null;
        }
    }

    private function getOptionSymbolFromSignal(array $signal): ?string
    {
        try {
            $baseSymbol   = $signal['symbol'];
            $futurePrice  = $signal['spot_price'] ?? 0;
            $optionType   = null;

            if ($signal['trade_action'] === 'BUY CE')      $optionType = 'CE';
            elseif ($signal['trade_action'] === 'BUY PE')  $optionType = 'PE';
            else return null;

            $strikeIntervals = [
                'NIFTY' => 100, 'BANKNIFTY' => 100, 'FINNIFTY' => 50, 'MIDCPNIFTY' => 25,
                'AXISBANK' => 10, 'ICICIBANK' => 10, 'INDUSINDBK' => 10, 'BHARTIARTL' => 20,
                'SHRIRAMFIN' => 10, 'LTF' => 5, 'PAYTM' => 20, 'POLICYBZR' => 20,
                'BAJAJFINSV' => 20, 'INFY' => 20, 'TATAELXSI' => 50, 'TATATECH' => 10,
                'HAVELLS' => 20, 'TITAN' => 20, 'ASIANPAINT' => 20, 'TATACONSUMER' => 10,
                'VOLTAS' => 20, 'AUROPHARMA' => 10, 'LAURUSLABS' => 10, 'SRF' => 20,
                'JSWSTEEL' => 10, 'LT' => 20, 'BHEL' => 5, 'ADANIPORTS' => 20,
                'HAL' => 50, 'BDL' => 20, 'MCX' => 20, 'BSE' => 50, 'CDSL' => 20,
                'LICHSG' => 5, 'DELHIVERY' => 10, 'BHARATFORG' => 20, 'PGEL' => 10,
                'TMPV' => 5, 'HINDALCO' => 10, 'VEDL' => 10, 'DRREDDY' => 50,
                'TATACONSUM' => 10, 'HEROMOTOCO' => 20, 'SBIN' => 10, 'VBL' => 20,
                'BAJFINANCE' => 50, 'TCS' => 50, 'COFORGE' => 50, 'AMBUJACEM' => 5,
                'FORTIS' => 5, 'UPL' => 10, 'M&M' => 20, 'NATIONALUM' => 5,
                'BPCL' => 10, 'ETERNAL' => 10,
            ];

            $interval         = $strikeIntervals[$baseSymbol] ?? 20;
            $calculatedStrike = round($futurePrice / $interval) * $interval;

            $option = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', $signal['date'])
                ->orderBy('expiry', 'ASC')
                ->first();

            if (!$option) {
                $option = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', $signal['date'])
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike])
                    ->orderBy('strike_diff', 'ASC')
                    ->orderBy('expiry', 'ASC')
                    ->first();
            }

            return $option?->trading_symbol;

        } catch (\Exception $e) {
            Log::error('getOptionSymbolFromSignal: ' . $e->getMessage());
            return null;
        }
    }

    private function getHighestPriceBetween($instrument, $startDateTime, $endDateTime): array
    {
        try {
            $highestCandle = \App\Models\SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('high', 'DESC')
                ->first();

            if ($highestCandle && $highestCandle->high > 0) {
                return ['price' => $highestCandle->high, 'time' => $highestCandle->timestamp->format('Y-m-d H:i')];
            }

            return $this->getHighestPriceFromKite($instrument, $startDateTime, $endDateTime);

        } catch (\Exception $e) {
            Log::error('getHighestPriceBetween: ' . $e->getMessage());
            return ['price' => 0, 'time' => null];
        }
    }

    private function getHighestPriceFromKite($instrument, $startDateTime, $endDateTime): array
    {
        try {
            if (!$this->kite) return ['price' => 0, 'time' => null];
            usleep(350000);

            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token, '5minute',
                $startDateTime->format('Y-m-d H:i:s'), $endDateTime->format('Y-m-d H:i:s')
            );

            $candles = is_array($response) ? $response : (array) $response;
            if (empty($candles)) return ['price' => 0, 'time' => null];

            $highestPrice = 0;
            $highestTime  = null;

            foreach ($candles as $candle) {
                $candle = is_object($candle) ? (array) $candle : $candle;
                $high   = $candle['high'] ?? 0;
                if ($high > $highestPrice) {
                    $highestPrice = $high;
                    $highestTime  = isset($candle['date'])
                        ? (is_string($candle['date']) ? date('Y-m-d H:i', strtotime($candle['date'])) : $candle['date']->format('Y-m-d H:i'))
                        : null;
                }
            }

            return ['price' => $highestPrice, 'time' => $highestTime];

        } catch (\Exception $e) {
            Log::error('getHighestPriceFromKite: ' . $e->getMessage());
            return ['price' => 0, 'time' => null];
        }
    }

    private function getOptionPriceAtTime($instrument, $datetime): ?float
    {
        try {
            $cached = \App\Models\OptionPriceCache::where('trading_symbol', $instrument->trading_symbol)
                ->where('price_datetime', $datetime)->first();

            if ($cached) return $cached->price;

            $price = $this->fetchPriceFromKite($instrument, $datetime);

            if ($price && $price > 0) {
                \App\Models\OptionPriceCache::updateOrCreate(
                    ['trading_symbol' => $instrument->trading_symbol, 'price_datetime' => $datetime],
                    ['instrument_token' => $instrument->instrument_token, 'price' => $price, 'cached_at' => now()]
                );
                return $price;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('getOptionPriceAtTime: ' . $e->getMessage());
            return null;
        }
    }

    private function fetchPriceFromKite($instrument, $datetime): ?float
    {
        try {
            if (!$this->kite) return null;
            usleep(350000);

            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token, '5minute',
                $datetime->copy()->subMinutes(30)->format('Y-m-d H:i:s'),
                $datetime->copy()->addMinutes(30)->format('Y-m-d H:i:s')
            );

            $candles         = is_array($response) ? $response : (array) $response;
            $targetTimestamp = $datetime->timestamp;
            $closestCandle   = null;
            $minDiff         = PHP_INT_MAX;

            foreach ($candles as $candle) {
                $candle     = is_object($candle) ? (array) $candle : $candle;
                $candleTime = isset($candle['date'])
                    ? (is_string($candle['date']) ? strtotime($candle['date']) : $candle['date']->getTimestamp())
                    : null;

                if ($candleTime === null) continue;
                $diff = abs($candleTime - $targetTimestamp);
                if ($diff < $minDiff) { $minDiff = $diff; $closestCandle = $candle; }
            }

            if ($closestCandle) {
                $price = $closestCandle['close'] ?? null;
                if ($price && $price > 0) return (float) $price;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('fetchPriceFromKite: ' . $e->getMessage());
            return null;
        }
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
        ]);

        try {
            OIIVAutoConfig::create([
                'user_id'           => Auth::id(),
                'broker_api_id'     => $request->broker_api_id,
                'order_type'        => $request->order_type,
                'product'           => $request->product,
                'disc_ltp'          => $request->disc_ltp,
                'index_quantity'    => $request->index_ce_quantity,
                'stock_quantity'    => $request->stock_ce_quantity,
                'index_ce_quantity' => $request->index_ce_quantity,
                'index_pe_quantity' => $request->index_pe_quantity,
                'stock_ce_quantity' => $request->stock_ce_quantity,
                'stock_pe_quantity' => $request->stock_pe_quantity,
                'signal_mode'       => $request->signal_mode,
                'status'            => $request->status,
                'strong_ce_quantity' => 0,
                'strong_pe_quantity' => 0,
                'rank1_ce_quantity' => $request->rank1_ce_quantity ?? 0,
                'rank1_pe_quantity' => $request->rank1_pe_quantity ?? 0,
                'rank2_ce_quantity' => $request->rank2_ce_quantity ?? 0,
                'rank2_pe_quantity' => $request->rank2_pe_quantity ?? 0,
                'rank3_ce_quantity' => $request->rank3_ce_quantity ?? 0,
                'rank3_pe_quantity' => $request->rank3_pe_quantity ?? 0,
                'rank4_ce_quantity' => $request->rank4_ce_quantity ?? 0,
                'rank4_pe_quantity' => $request->rank4_pe_quantity ?? 0,
            ]);

            $notify[] = ['success', 'Auto trading configuration created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration'];
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
        ]);

        $config = OIIVAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

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
            $status  = $config->status ? 'activated' : 'deactivated';
            $notify[] = ['success', "Configuration {$status} successfully!"];
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

            $pendingOrders = $config->orders()
                ->where('is_order_placed', false)->where('status', true)->count();

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

    private function getNextTradingDay($date): Carbon
    {
        $next     = Carbon::parse($date)->addDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$next->isWeekend() && !$this->isHoliday($next->format('Y-m-d'))) return $next;
            $next->addDay();
            $attempts++;
        }
        return Carbon::parse($date)->addDay();
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    // =========================================================
    //  KITE INIT
    // =========================================================

    private function initializeKite(): void
    {
        if ($this->kite) return;
        try {
            $brokerApi = BrokerApi::where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->where('token_expires_at', '>', now())
                ->first();

            if (!$brokerApi) throw new \Exception('No valid Zerodha broker found');

            $this->kite = new KiteConnect($brokerApi->api_key);
            $this->kite->setAccessToken($brokerApi->access_token);
        } catch (\Exception $e) {
            Log::error('initializeKite: ' . $e->getMessage());
            $this->kite = null;
        }
    }
}