<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\BrokerApi;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * 9:30 AM → 12:15 PM Intraday PE/CE Analysis + Config Management
 *
 * Series-scoped: every query is filtered by a single expiry_date (series).
 * Default series = the current active series (nearest expiry >= today).
 * User can override via the series dropdown.
 *
 * WEEKLY EXPIRY FIX (NIFTY etc.):
 *   getNearestExpiryForDate() — data-driven, no hard-coded weekly/monthly logic.
 *   Works for NIFTY weekly, BANKNIFTY monthly, stocks, etc. automatically.
 *   The selected series_expiry from the UI is used only as a UI filter for
 *   getSeries() / display. Internally, per-symbol per-date expiry resolution
 *   uses the same pattern as OIIVAutoController (EOD).
 *
 * Pivot Points: Standard Floor Pivot using previous actual trading day FUT OHLC
 *   P  = (H + L + C) / 3
 *   R1 = 2P − L,  R2 = P + (H−L),  R3 = H + 2(P−L)
 *   S1 = 2P − H,  S2 = P − (H−L),  S3 = L − 2(H−P)
 */
class OIIVAuto9to12Controller extends Controller
{
    // =========================================================
    //  ANALYSIS PAGE
    // =========================================================

    public function peCeAnalysis()
    {
        $pageTitle = '9:30 AM → 12:15 PM Intraday PE/CE Analysis';
        return view($this->activeTemplate . 'user.oiiv-auto.pece-analysis-9to12', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS  (from option_ohlc_data)
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::distinct()
            ->whereNotNull('base_symbol')
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  AVAILABLE SERIES (distinct expiry_date values)
    //
    //  Returns all unique series present in the DB, plus which
    //  one is the "current" default (nearest expiry >= today).
    // =========================================================

    public function getSeries()
    {
        $today = Carbon::today()->toDateString();

        $allExpiries = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->distinct()
            ->orderBy('expiry_date', 'ASC')
            ->pluck('expiry_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($allExpiries)) {
            return response()->json(['success' => true, 'current_series' => null]);
        }

        // Group by month, keep the LAST expiry per month
        $byMonth = [];
        foreach ($allExpiries as $d) {
            $month = substr($d, 0, 7);
            if (!isset($byMonth[$month]) || $d > $byMonth[$month]) {
                $byMonth[$month] = $d;
            }
        }

        // Current = latest month whose last expiry >= today
        $currentSeries = null;
        foreach (array_values($byMonth) as $lastExpiry) {
            if ($lastExpiry >= $today) { $currentSeries = $lastExpiry; break; }
        }
        if (!$currentSeries) {
            $currentSeries = end($byMonth);
        }

        return response()->json(['success' => true, 'current_series' => $currentSeries]);
    }

    // =========================================================
    //  EXPIRY HELPERS — data-driven, handles NIFTY weekly + monthly
    //
    //  Ported from OIIVAutoController (EOD) — same pattern.
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
     * Optionally constrained to $seriesExpiry when provided — this lets
     * the user pin a specific monthly series from the UI while still
     * correctly resolving per-symbol expiry within that series.
     */
    private function getNearestExpiryForDate(string $symbol, string $date, ?string $seriesExpiry = null): ?string
    {
        // If a series expiry is selected from UI, scope to that month's expiries only.
        // This prevents e.g. selecting "Feb 2026" from accidentally picking up
        // a March weekly that happens to exist on the same date.
        $seriesMonth = $seriesExpiry ? substr($seriesExpiry, 0, 7) : null; // "2026-02"

        // Forward-looking: nearest expiry on or after today
        $query = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date');

        if ($seriesMonth) {
            $query->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth]);
        }

        $expiry = $query->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        // Fallback: most recent expiry from today's data (handles expiry day)
        $fallback = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date');

        if ($seriesMonth) {
            $fallback->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth]);
        }

        return $fallback->value(DB::raw('DATE(expiry_date)'));
    }

    /**
     * For the 09:30 candle (open baseline), find the expiry that best matches
     * the current candle's expiry for OI comparison.
     *
     * For non-rollover days: open candle has the same expiry → use it directly.
     * For rollover days: open candle may have the previous week's expiry.
     *   Try the same expiry first; if no 09:30 data exists for it,
     *   fall back to the nearest expiry that DID have data at 09:30.
     */
    private function getOpenCandleExpiry(string $symbol, string $date, string $currentExpiry): ?string
    {
        // Best case: same expiry had data at 09:30 today
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $currentExpiry)
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->where('is_missing', 0)
            ->exists();

        if ($exists) return $currentExpiry;

        // Rollover case: find whichever expiry had data at 09:30 today
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  ANALYZE PE/CE SIGNALS (AJAX)
    // =========================================================

    public function analyzePECESignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');
            $seriesExpiry    = $request->get('series_expiry'); // UI series scope (optional for expiry helpers)

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data'    => [],
                ]);
            }

            if (!$seriesExpiry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Series (expiry) is required — please select a series',
                    'data'    => [],
                ]);
            }

            // ── Get all trade dates in range that have FUT data ───────────
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d');

            if (!empty($selectedSymbols)) {
                $tradeDates->whereIn('base_symbol', $selectedSymbols);
            }

            $tradeDates = $tradeDates->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for the selected date range',
                    'data'    => [],
                ]);
            }

            $results = [];

            foreach ($tradeDates as $date) {
                $rows = $this->buildSignalRowsForDate($date, $selectedSymbols, $filterAction, $seriesExpiry);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }

            if (empty($results)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for the selected series / date range',
                    'data'    => [],
                ]);
            }

            usort($results, function ($a, $b) {
                $dateComp = strcmp($b['date'], $a['date']);
                return $dateComp !== 0 ? $dateComp : strcmp($a['symbol'], $b['symbol']);
            });

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
                'series_expiry' => $seriesExpiry,
            ]);

        } catch (\Exception $e) {
            Log::error('9to12 PE/CE Analysis Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD SIGNAL ROWS FOR A SINGLE DATE
    //
    //  KEY FIX for NIFTY/weekly expiry (same pattern as EOD controller):
    //   1. getNearestExpiryForDate() resolves the active expiry from actual
    //      data — no hard-coded weekly/monthly logic.
    //   2. getOpenCandleExpiry() finds the matching expiry for the 09:30
    //      open candle — handles rollover where the new expiry may not have
    //      existed at market open.
    //   3. OI comparison scopes 09:30 candles to openExpiry and 12:00 candles
    //      to currentExpiry (may differ on rollover morning).
    //   4. The `continue` guard fires only when BOTH 12:00 CE and PE OI are
    //      genuinely zero — not due to expiry mismatch.
    // =========================================================

    private function buildSignalRowsForDate(
        string $date,
        array $symbolFilter,
        ?string $actionFilter,
        string $seriesExpiry
    ): array {
        // ── Fetch all FUT candles at 09:30 and 12:00 for this date ────────
        $futQuery = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) IN ('09:30:00', '12:00:00')")
            ->where('is_missing', 0);

        if (!empty($symbolFilter)) {
            $futQuery->whereIn('base_symbol', $symbolFilter);
        }

        // FUT expiry scoping: use series expiry if set, also allow null (legacy data)
        $seriesMonth = substr($seriesExpiry, 0, 7); // "2026-02"
        $futQuery->where(function ($q) use ($seriesExpiry, $seriesMonth) {
            $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
              ->orWhereDate('expiry_date', $seriesExpiry)
              ->orWhereNull('expiry_date');
        });

        $futCandles = $futQuery->get()->groupBy(function ($c) {
            return $c->base_symbol . '|' . substr($c->candle_time ?? '', 0, 5);
        });

        // Collect unique symbols from FUT data
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->where(function ($q) use ($seriesExpiry, $seriesMonth) {
                $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                  ->orWhereDate('expiry_date', $seriesExpiry)
                  ->orWhereNull('expiry_date');
            });

        if (!empty($symbolFilter)) {
            $symbols->whereIn('base_symbol', $symbolFilter);
        }

        $symbols = $symbols->distinct()->pluck('base_symbol')->toArray();

        if (empty($symbols)) return [];

        $rows = [];

        foreach ($symbols as $symbol) {
            // ── FUT candles at 09:30 and 12:00 ──────────────────────────
            $futOpen    = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->where('is_missing', 0)
                ->where(function ($q) use ($seriesExpiry, $seriesMonth) {
                    $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                      ->orWhereDate('expiry_date', $seriesExpiry)
                      ->orWhereNull('expiry_date');
                })
                ->first();

            $futCurrent = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '12:00:00'")
                ->where('is_missing', 0)
                ->where(function ($q) use ($seriesExpiry, $seriesMonth) {
                    $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                      ->orWhereDate('expiry_date', $seriesExpiry)
                      ->orWhereNull('expiry_date');
                })
                ->first();

            if (!$futOpen || !$futCurrent) continue;

            // ── Resolve expiries per-symbol, data-driven ─────────────────
            // currentExpiry = expiry active at 12:00 today
            $currentExpiry = $this->getNearestExpiryForDate($symbol, $date, $seriesExpiry);

            // openExpiry = expiry that had data at 09:30 today
            // (may differ on rollover morning for weekly instruments)
            $openExpiry = $currentExpiry
                ? $this->getOpenCandleExpiry($symbol, $date, $currentExpiry)
                : null;

            // ── CE/PE at 09:30 (open baseline) scoped to openExpiry ──────
            $ceOpen09Query = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->where('is_missing', 0);
            if ($openExpiry) $ceOpen09Query->whereDate('expiry_date', $openExpiry);
            $ceOpen09 = $ceOpen09Query->get();

            $peOpen09Query = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->where('is_missing', 0);
            if ($openExpiry) $peOpen09Query->whereDate('expiry_date', $openExpiry);
            $peOpen09 = $peOpen09Query->get();

            // ── CE/PE at 12:00 (current snapshot) scoped to currentExpiry
            $ceCur12Query = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '12:00:00'")
                ->where('is_missing', 0);
            if ($currentExpiry) $ceCur12Query->whereDate('expiry_date', $currentExpiry);
            $ceCur12 = $ceCur12Query->get();

            $peCur12Query = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '12:00:00'")
                ->where('is_missing', 0);
            if ($currentExpiry) $peCur12Query->whereDate('expiry_date', $currentExpiry);
            $peCur12 = $peCur12Query->get();

            // ── Sum OI across all strikes (no strike-matching needed) ─────
            $openOiCe = (int) $ceOpen09->sum('oi');
            $curOiCe  = (int) $ceCur12->sum('oi');
            $openOiPe = (int) $peOpen09->sum('oi');
            $curOiPe  = (int) $peCur12->sum('oi');

            // On expiry day OI can legitimately collapse to 0 at 12:00,
            // but open_oi at 09:30 was non-zero — still a valid signal row.
            // Only skip if BOTH open AND current OI are zero (truly no data).
            if ($curOiCe == 0 && $curOiPe == 0 && $openOiCe == 0 && $openOiPe == 0) {
                continue;
            }

            // ── FUT OI ───────────────────────────────────────────────────
            $openOiFut   = (int) ($futOpen->oi    ?? 0);
            $curOiFut    = (int) ($futCurrent->oi ?? 0);
            $oiChangeFut = $curOiFut - $openOiFut;
            $oiPctFut    = $openOiFut > 0 ? round(($oiChangeFut / $openOiFut) * 100, 4) : 0;

            // ── FUT price ────────────────────────────────────────────────
            $openCloseFut    = (float) ($futOpen->close    ?? 0);
            $currentCloseFut = (float) ($futCurrent->close ?? 0);
            $spotPrice       = $currentCloseFut;

            $futClChange    = $currentCloseFut - $openCloseFut;
            $futClChangePct = $openCloseFut > 0
                ? round(($futClChange / $openCloseFut) * 100, 4)
                : 0;
            $futPriceSignal = $currentCloseFut > $openCloseFut
                ? 'BULLISH'
                : ($currentCloseFut < $openCloseFut ? 'BEARISH' : 'NEUTRAL');

            $futOiSignal = $this->analyzeFutOI($curOiFut, $openOiFut);
            $futBias     = $futOiSignal['market_bias'] ?? 'Normal';

            // ── Option close prices (ATM aggregation for price tracking) ─
            $ceAgg = $this->aggregateOptionCandles(
                $ceOpen09->all(),
                $ceCur12->all()
            );
            $peAgg = $this->aggregateOptionCandles(
                $peOpen09->all(),
                $peCur12->all()
            );

            // ── OI % change ──────────────────────────────────────────────
            $ceOiPct = $openOiCe > 0
                ? round((($curOiCe - $openOiCe) / $openOiCe) * 100, 4)
                : 0;
            $peOiPct = $openOiPe > 0
                ? round((($curOiPe - $openOiPe) / $openOiPe) * 100, 4)
                : 0;

            $ceClChangePct = ($ceAgg['open_close'] ?? 0) > 0
                ? round((($ceAgg['cur_close'] - $ceAgg['open_close']) / $ceAgg['open_close']) * 100, 4)
                : null;
            $peClChangePct = ($peAgg['open_close'] ?? 0) > 0
                ? round((($peAgg['cur_close'] - $peAgg['open_close']) / $peAgg['open_close']) * 100, 4)
                : null;

            // ── Signal logic ─────────────────────────────────────────────
            $oiSignal    = $this->getOISignal($ceOiPct, $peOiPct);
            $sentiment   = $oiSignal['signal'];
            $tradeAction = match ($sentiment) {
                'BULLISH' => 'BUY CE',
                'BEARISH' => 'BUY PE',
                default   => 'WAIT',
            };

            if (!empty($actionFilter) && $tradeAction !== $actionFilter) continue;

            $peCeRatio = $curOiCe > 0
                ? round($curOiPe / $curOiCe, 2)
                : 0;

            $diff = abs($ceOiPct - $peOiPct);
            if      ($diff > 40) $strengthRank = 'Rank 1';
            elseif  ($diff > 25) $strengthRank = 'Rank 2';
            elseif  ($diff > 10) $strengthRank = 'Rank 3';
            elseif  ($diff > 5)  $strengthRank = 'Rank 4';
            else                 $strengthRank = 'Normal';

            $isBoth       = str_contains($oiSignal['condition'], 'Both');
            $strongerSide = $isBoth
                ? (abs($ceOiPct) > abs($peOiPct) ? 'CE' : (abs($peOiPct) > abs($ceOiPct) ? 'PE' : 'EQUAL'))
                : 'CLEAR';

            // ── 50 MA signal ─────────────────────────────────────────────
            $fut50MaResult  = $this->getFut50MaSignal($symbol, $date, $seriesExpiry);
            $fut50Ma        = $fut50MaResult['signal'];
            $fut50MaFresh   = $fut50MaResult['freshness'];

            // ── Pivot analysis ───────────────────────────────────────────
            $pivotLevels   = null;
            $pivotPosition = 'N/A';
            $pivotData     = [];

            $prevOhlc = $this->getPreviousDayFutOHLC($symbol, $date, $seriesExpiry);
            if ($prevOhlc) {
                $pivotLevels   = $this->calculatePivots($prevOhlc);
                $pivotPosition = $this->getPivotPosition($spotPrice, $pivotLevels);
                $pivotData     = $pivotLevels;
            }

            $rows[] = [
                'date'              => $date,
                'symbol'            => $symbol,
                'series_expiry'     => $seriesExpiry,
                'current_expiry'    => $currentExpiry,  // actual resolved expiry for this symbol/date
                'open_expiry'       => $openExpiry,     // expiry used for 09:30 baseline
                'fut_symbol'        => $futCurrent->trading_symbol ?? $symbol . 'FUT',

                'ce_oi'            => $curOiCe,
                'ce_oi_prev'       => $openOiCe,
                'ce_oi_change_pct' => $ceOiPct,

                'pe_oi'            => $curOiPe,
                'pe_oi_prev'       => $openOiPe,
                'pe_oi_change_pct' => $peOiPct,

                'fut_oi'            => $curOiFut,
                'fut_oi_prev'       => $openOiFut,
                'fut_oi_change_pct' => round($oiPctFut, 2),

                'ce_oi_change_pct_fut' => round($ceOiPct, 2),
                'pe_oi_change_pct_fut' => round($peOiPct, 2),

                'strength_rank' => $strengthRank,
                'strength_diff' => round($diff, 2),
                'stronger_side' => $strongerSide,

                'pe_ce_ratio'       => $peCeRatio,
                'oi_interpretation' => $oiSignal['reason'],
                'oi_condition'      => $oiSignal['condition'],

                'options_sentiment' => $sentiment,
                'futures_oi_view'   => $futBias,
                'final_sentiment'   => $sentiment,
                'trade_action'      => $tradeAction,

                'fut_price_prev'       => round($openCloseFut, 2),
                'fut_price_today'      => round($currentCloseFut, 2),
                'fut_price_change'     => round($futClChange, 2),
                'fut_price_change_pct' => round($futClChangePct, 2),
                'fut_price_signal'     => $futPriceSignal,

                'ce_open_close'       => round($ceAgg['open_close']  ?? 0, 2),
                'ce_current_close'    => round($ceAgg['cur_close']   ?? 0, 2),
                'ce_close_change_pct' => round($ceClChangePct        ?? 0, 2),

                'pe_open_close'       => round($peAgg['open_close']  ?? 0, 2),
                'pe_current_close'    => round($peAgg['cur_close']   ?? 0, 2),
                'pe_close_change_pct' => round($peClChangePct        ?? 0, 2),

                'spot_price'         => round($spotPrice, 2),
                'fut_50ma_signal'    => $fut50Ma,
                'fut_50ma_freshness' => $fut50MaFresh,

                'pivot_position' => $pivotPosition,
                'pivot'          => isset($pivotData['P'])  ? round($pivotData['P'],  2) : null,
                'r1'             => isset($pivotData['R1']) ? round($pivotData['R1'], 2) : null,
                'r2'             => isset($pivotData['R2']) ? round($pivotData['R2'], 2) : null,
                'r3'             => isset($pivotData['R3']) ? round($pivotData['R3'], 2) : null,
                's1'             => isset($pivotData['S1']) ? round($pivotData['S1'], 2) : null,
                's2'             => isset($pivotData['S2']) ? round($pivotData['S2'], 2) : null,
                's3'             => isset($pivotData['S3']) ? round($pivotData['S3'], 2) : null,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  PIVOT POINT HELPERS
    // =========================================================

    /**
     * Fetch previous actual trading day FUT OHLC scoped to series.
     */
    private function getPreviousDayFutOHLC(string $symbol, string $tradeDate, string $seriesExpiry): ?array
    {
        $seriesMonth = substr($seriesExpiry, 0, 7);

        $prevDate = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', '<', $tradeDate)
            ->where('is_missing', 0)
            ->where(function ($q) use ($seriesExpiry, $seriesMonth) {
                $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                  ->orWhereDate('expiry_date', $seriesExpiry)
                  ->orWhereNull('expiry_date');
            })
            ->orderByDesc('trade_date')
            ->value(DB::raw('DATE(trade_date)'));

        if (!$prevDate) return null;

        $rows = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->where('is_missing', 0)
            ->where(function ($q) use ($seriesExpiry, $seriesMonth) {
                $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                  ->orWhereDate('expiry_date', $seriesExpiry)
                  ->orWhereNull('expiry_date');
            })
            ->get(['high', 'low', 'close', 'interval_time']);

        if ($rows->isEmpty()) return null;

        return [
            'high'  => (float) $rows->max('high'),
            'low'   => (float) $rows->min('low'),
            'close' => (float) $rows->sortByDesc('interval_time')->first()->close,
        ];
    }

    private function calculatePivots(array $ohlc): array
    {
        $H = $ohlc['high'];
        $L = $ohlc['low'];
        $C = $ohlc['close'];

        $P  = ($H + $L + $C) / 3;
        $R1 = (2 * $P) - $L;
        $S1 = (2 * $P) - $H;
        $R2 = $P + ($H - $L);
        $S2 = $P - ($H - $L);
        $R3 = $H + 2 * ($P - $L);
        $S3 = $L - 2 * ($H - $P);

        return compact('P', 'R1', 'R2', 'R3', 'S1', 'S2', 'S3');
    }

    private function getPivotPosition(float $price, array $pivots): string
    {
        $levels = [
            'R3' => $pivots['R3'],
            'R2' => $pivots['R2'],
            'R1' => $pivots['R1'],
            'P'  => $pivots['P'],
            'S1' => $pivots['S1'],
            'S2' => $pivots['S2'],
            'S3' => $pivots['S3'],
        ];

        foreach ($levels as $label => $level) {
            if ($level > 0 && abs($price - $level) / $level * 100 < 0.3) {
                return "Near {$label}";
            }
        }

        if ($price >= $pivots['R3']) return 'Above R3';
        if ($price >= $pivots['R2']) return 'R2–R3';
        if ($price >= $pivots['R1']) return 'R1–R2';
        if ($price >= $pivots['P'])  return 'P–R1';
        if ($price >= $pivots['S1']) return 'S1–P';
        if ($price >= $pivots['S2']) return 'S2–S1';
        if ($price >= $pivots['S3']) return 'S3–S2';
        return 'Below S3';
    }

    // =========================================================
    //  AGGREGATE option candles across ATM-1, ATM, ATM+1
    //  (for close price tracking — OI is now summed directly above)
    // =========================================================

    private function aggregateOptionCandles(array $openCandles, array $curCandles): array
    {
        $curByPosition = [];
        foreach ($curCandles as $c) {
            $pos = $c->strike_position ?? 'N/A';
            if ($pos === 'N/A') continue;
            $curByPosition[$pos] = $c;
        }

        $openClTotal = 0.0;
        $curClTotal  = 0.0;
        $closeCount  = 0;

        foreach ($openCandles as $oc) {
            $pos = $oc->strike_position ?? 'N/A';
            if ($pos === 'N/A') continue;

            $cc = $curByPosition[$pos] ?? null;
            if (!$cc) continue;

            $ocClose = (float) ($oc->close ?? 0);
            $ccClose = (float) ($cc->close ?? 0);
            if ($ocClose > 0 && $ccClose > 0) {
                $openClTotal += $ocClose;
                $curClTotal  += $ccClose;
                $closeCount++;
            }
        }

        // open_oi / cur_oi are now computed by summing all strikes directly
        // in buildSignalRowsForDate, so we return 0 here — kept for compatibility.
        return [
            'open_oi'    => 0,
            'cur_oi'     => 0,
            'open_close' => $closeCount > 0 ? $openClTotal / $closeCount : 0,
            'cur_close'  => $closeCount > 0 ? $curClTotal  / $closeCount : 0,
        ];
    }

    // =========================================================
    //  CALCULATE PROFIT  — series-scoped, expiry-aware
    // =========================================================

    public function calculateProfit(Request $request)
    {
        $signals = $request->input('signals', []);

        if (empty($signals)) {
            return response()->json(['success' => false, 'message' => 'No signals provided', 'data' => []]);
        }

        $results = [];

        foreach ($signals as $signal) {
            $idx          = (int)   ($signal['index']         ?? 0);
            $symbol       =          $signal['symbol']         ?? '';
            $tradeDate    =          $signal['date']           ?? '';
            $tradeAction  =          $signal['trade_action']   ?? '';
            $spotPrice    = (float) ($signal['spot_price']    ?? 0);
            $seriesExpiry =          $signal['series_expiry'] ?? null;

            $placeholder = [
                'index'         => $idx,
                'option_symbol' => null,
                'strike'        => null,
                'option_type'   => null,
                'buy_price'     => 0,
                'lot_size'      => 0,
                'investment'    => 0,
                'high_price'    => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                'low_price'     => 0, 'low_time'  => null, 'low_pl'  => 0, 'low_roi'  => 0,
                'eod_price'     => 0,                      'eod_pl'  => 0, 'eod_roi'  => 0,
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

                // ── Resolve current expiry data-driven ───────────────────
                $currentExpiry = $this->getNearestExpiryForDate($symbol, $tradeDate, $seriesExpiry);

                // ── ATM row at 12:00 scoped to resolved expiry ───────────
                $atmQuery = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '12:00:00'");

                if ($currentExpiry) {
                    $atmQuery->whereDate('expiry_date', $currentExpiry);
                } elseif ($seriesExpiry) {
                    $atmQuery->whereDate('expiry_date', $seriesExpiry);
                }

                $atmRow = $atmQuery->orderBy('expiry_date')->first();

                // Fallback: nearest strike to spot at 12:15
                if (!$atmRow) {
                    $fallback = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '12:15:00'")
                        ->whereNotNull('strike')
                        ->whereNotNull('expiry_date');

                    if ($currentExpiry) {
                        $fallback->whereDate('expiry_date', $currentExpiry);
                    } elseif ($seriesExpiry) {
                        $fallback->whereDate('expiry_date', $seriesExpiry);
                    }

                    $atmRow = $fallback->orderByRaw('ABS(strike - ?)', [$spotPrice])
                        ->orderBy('expiry_date')
                        ->first();
                }

                if (!$atmRow) {
                    $placeholder['error'] = 'NO_ATM_ROW';
                    $results[] = $placeholder;
                    continue;
                }

                $strike     = $atmRow->strike;
                $expiryDate = $atmRow->expiry_date instanceof \Carbon\Carbon
                    ? $atmRow->expiry_date->toDateString()
                    : (is_string($atmRow->expiry_date)
                        ? substr($atmRow->expiry_date, 0, 10)
                        : (string) $atmRow->expiry_date);

                // All subsequent queries bound to strike + expiryDate → series-safe
                $buyCandle = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->where(function ($q) {
                        $q->whereRaw("TIME(interval_time) = '12:00:00'")
                          ->orWhereRaw("TIME(interval_time) BETWEEN '12:00:00' AND '12:29:59'");
                    })
                    ->orderByRaw("ABS(TIME_TO_SEC(TIME(interval_time)) - TIME_TO_SEC('12:15:00'))")
                    ->first();

                $buyPrice = 0.0;
                if ($buyCandle) {
                    $candleOpen  = (float) ($buyCandle->open  ?? 0);
                    $candleClose = (float) ($buyCandle->close ?? 0);
                    $buyPrice    = $candleOpen > 0 ? $candleOpen : $candleClose;
                }
                if ($buyPrice <= 0 && $atmRow) {
                    $atmOpen  = (float) ($atmRow->open  ?? 0);
                    $atmClose = (float) ($atmRow->close ?? 0);
                    $buyPrice = $atmOpen > 0 ? $atmOpen : $atmClose;
                    $buyCandle = $atmRow;
                }

                if ($buyPrice <= 0) {
                    $placeholder['error']         = 'NO_BUY_PRICE';
                    $placeholder['option_symbol'] = $atmRow->trading_symbol ?? null;
                    $placeholder['strike']        = $strike;
                    $placeholder['option_type']   = $optionType;
                    $results[] = $placeholder;
                    continue;
                }

                $highRow = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) BETWEEN '12:15:00' AND '15:15:00'")
                    ->orderByDesc('high')
                    ->first();

                $highPrice = $highRow ? max((float) $highRow->high, $buyPrice) : $buyPrice;
                $highTime  = $highRow ? Carbon::parse($highRow->interval_time)->format('H:i') : null;

                $lowRow = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) BETWEEN '12:30:00' AND '15:15:00'")
                    ->orderBy('low')
                    ->first();

                $lowPrice = $lowRow ? (float) $lowRow->low : $buyPrice;
                $lowTime  = $lowRow ? Carbon::parse($lowRow->interval_time)->format('H:i') : null;

                $eodCandle = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = '15:15:00'")
                    ->first();

                if (!$eodCandle) {
                    $eodCandle = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->where('strike', $strike)
                        ->whereDate('expiry_date', $expiryDate)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) > '12:00:00'")
                        ->orderByDesc('interval_time')
                        ->first();
                }

                $eodPrice   = $eodCandle ? (float) $eodCandle->close : $buyPrice;
                $lotSize    = $this->getLotSize($symbol);
                $investment = round($buyPrice * $lotSize, 2);

                $highPL  = round(($highPrice - $buyPrice) * $lotSize, 2);
                $highRoi = $investment > 0 ? round(($highPL  / $investment) * 100, 2) : 0;
                $lowPL   = round(($lowPrice  - $buyPrice) * $lotSize, 2);
                $lowRoi  = $investment > 0 ? round(($lowPL   / $investment) * 100, 2) : 0;
                $eodPL   = round(($eodPrice  - $buyPrice) * $lotSize, 2);
                $eodRoi  = $investment > 0 ? round(($eodPL   / $investment) * 100, 2) : 0;

                $results[] = [
                    'index'         => $idx,
                    'option_symbol' => ($buyCandle ?? $atmRow)->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
                    'strike'        => $strike,
                    'option_type'   => $optionType,
                    'lot_size'      => $lotSize,
                    'investment'    => $investment,
                    'buy_price'     => round($buyPrice, 2),
                    'high_price'    => round($highPrice, 2),
                    'high_time'     => $highTime,
                    'high_pl'       => $highPL,
                    'high_roi'      => $highRoi,
                    'low_price'     => round($lowPrice, 2),
                    'low_time'      => $lowTime,
                    'low_pl'        => $lowPL,
                    'low_roi'       => $lowRoi,
                    'eod_price'     => round($eodPrice, 2),
                    'eod_pl'        => $eodPL,
                    'eod_roi'       => $eodRoi,
                    'profit_loss'   => $highPL,
                    'roi_percent'   => $highRoi,
                    'error'         => null,
                ];

            } catch (\Exception $e) {
                Log::error("9to12 Profit row error (idx={$idx}): " . $e->getMessage());
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
        $instrument = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->value('lot_size');

        if ($instrument) return (int) $instrument;

        $lots = [
            'NIFTY' => 25, 'BANKNIFTY' => 15, 'FINNIFTY' => 25,
            'MIDCPNIFTY' => 50, 'SENSEX' => 10, 'BANKEX' => 15,
        ];
        return $lots[$symbol] ?? 1;
    }

    // =========================================================
    //  50 MA  — series-scoped, expiry-aware
    // =========================================================

    private function historyStartDate(string $tradeDate, int $maPeriod = 50): string
    {
        return Carbon::parse($tradeDate)->subDays((int) ceil($maPeriod * 2.5) + 15)->toDateString();
    }

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

    private function getFut50MaSignal(string $baseSymbol, string $tradeDate, string $seriesExpiry): array
    {
        $maPeriod     = 50;
        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);
        $seriesMonth  = substr($seriesExpiry, 0, 7);

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $historyStart)
            ->whereDate('trade_date', '<=', $tradeDate)
            ->where(function ($q) use ($seriesExpiry, $seriesMonth) {
                $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                  ->orWhereDate('expiry_date', $seriesExpiry)
                  ->orWhereNull('expiry_date');
            })
            ->orderBy('trade_date')
            ->orderBy('interval_time')
            ->get([
                DB::raw("DATE(trade_date) as candle_date"),
                DB::raw("TIME(interval_time) as candle_time"),
                'close',
            ]);

        if ($allCandles->isEmpty()) return ['signal' => 'N/A', 'freshness' => 'N/A'];

        $closeValues = $allCandles->pluck('close')->map(fn($v) => (float) $v)->toArray();
        $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

        $targetIdx = null;
        foreach ($allCandles as $idx => $candle) {
            $candleDate = is_string($candle->candle_date)
                ? $candle->candle_date
                : Carbon::parse($candle->candle_date)->toDateString();
            if ($candleDate !== $tradeDate) continue;
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($time >= '12:00' && $time <= '12:14') { $targetIdx = $idx; break; }
        }

        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $candleDate = is_string($candle->candle_date)
                    ? $candle->candle_date
                    : Carbon::parse($candle->candle_date)->toDateString();
                if ($candleDate === $tradeDate) $targetIdx = $idx;
            }
        }

        if ($targetIdx === null || !isset($closeMa[$targetIdx])) return ['signal' => 'N/A', 'freshness' => 'N/A'];

        $close = $closeValues[$targetIdx];
        $ma    = $closeMa[$targetIdx];

        if ($ma === null) return ['signal' => 'N/A', 'freshness' => 'N/A'];

        $signal = $close > $ma ? 'BULLISH' : ($close < $ma ? 'BEARISH' : 'NEUTRAL');

        $freshness = 'N/A';
        if ($signal !== 'NEUTRAL') {
            $streakLen = 1;
            for ($i = $targetIdx - 1; $i >= 0 && ($targetIdx - $i) <= 12; $i--) {
                $prevMa    = $closeMa[$i] ?? null;
                $prevClose = $closeValues[$i] ?? null;
                if ($prevMa === null || $prevClose === null) break;

                $prevSignal = $prevClose > $prevMa ? 'BULLISH' : ($prevClose < $prevMa ? 'BEARISH' : 'NEUTRAL');
                if ($prevSignal === $signal) {
                    $streakLen++;
                } else {
                    break;
                }
            }
            $freshness = $streakLen <= 6 ? 'FRESH' : 'OLD';
        }

        return ['signal' => $signal, 'freshness' => $freshness];
    }

    // =========================================================
    //  OI SIGNAL LOGIC
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
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup but CE stronger (CE: +{$cePct}% > PE: +{$pePct}%)", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup but PE stronger (PE: +{$pePct}% > CE: +{$cePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding but CE stronger (CE: {$cePct}% < PE: {$pePct}%)", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding but PE stronger (PE: {$pePct}% < CE: {$cePct}%)", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    private function analyzeFutOI(int $curOI, int $openOI): array
    {
        $change    = $curOI - $openOI;
        $changePct = $openOI > 0 ? ($change / $openOI) * 100 : 0;

        if ($change > 0) return ['direction' => 'BUILDUP',   'strength' => abs($changePct) > 10 ? 'STRONG' : 'MODERATE', 'market_bias' => 'Bullish'];
        if ($change < 0) return ['direction' => 'UNWINDING', 'strength' => abs($changePct) > 10 ? 'STRONG' : 'MODERATE', 'market_bias' => 'Bearish'];
        return ['direction' => 'NEUTRAL', 'strength' => 'WEAK', 'market_bias' => 'Normal'];
    }

    // =========================================================
    //  CONFIG / ORDERS  (unchanged)
    // =========================================================

    public function config()
    {
        $pageTitle = '9:30→12:15 Auto Trading Configuration';
        $brokers   = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();
        $configs   = OIIVAutoConfig::where('user_id', Auth::id())
            ->where('config_type', '9to12')
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.oiiv-auto.config-9to12', compact('pageTitle', 'brokers', 'configs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'order_type'    => 'required|in:LIMIT,MARKET',
            'product'       => 'required|in:NRML,MIS',
            'disc_ltp'      => 'required|numeric|min:0|max:100',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
            'signal_mode'   => 'required|in:align,opposite',
            'option_series' => 'required|in:current,next',
            'status'        => 'required|in:1,0',
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
                'option_series'      => $request->option_series,
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
                'config_type'        => '9to12',
            ]);

            $notify[] = ['success', '9to12 auto trading configuration created successfully!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('9to12 Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration'];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'order_type'    => 'required|in:LIMIT,MARKET',
            'product'       => 'required|in:NRML,MIS',
            'disc_ltp'      => 'required|numeric|min:0|max:100',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
            'signal_mode'   => 'required|in:align,opposite',
            'option_series' => 'required|in:current,next',
            'status'        => 'required|in:1,0',
            'rank1_ce_quantity' => 'nullable|integer|min:0',
            'rank1_pe_quantity' => 'nullable|integer|min:0',
            'rank2_ce_quantity' => 'nullable|integer|min:0',
            'rank2_pe_quantity' => 'nullable|integer|min:0',
            'rank3_ce_quantity' => 'nullable|integer|min:0',
            'rank3_pe_quantity' => 'nullable|integer|min:0',
            'rank4_ce_quantity' => 'nullable|integer|min:0',
            'rank4_pe_quantity' => 'nullable|integer|min:0',
        ]);

        $config = OIIVAutoConfig::where('id', $id)->where('user_id', Auth::id())->where('config_type', '9to12')->firstOrFail();
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
            'option_series'     => $request->option_series,
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

        $notify[] = ['success', '9to12 configuration updated successfully!'];
        return back()->withNotify($notify);
    }

    public function toggleStatus($id)
    {
        try {
            $config = OIIVAutoConfig::where('id', $id)->where('user_id', Auth::id())->where('config_type', '9to12')->firstOrFail();
            $config->status = !$config->status;
            $config->save();
            $notify[] = ['success', "9to12 configuration " . ($config->status ? 'activated' : 'deactivated') . " successfully!"];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating configuration.'];
            return back()->withNotify($notify);
        }
    }

    public function destroy($id)
    {
        try {
            $config = OIIVAutoConfig::where('id', $id)->where('user_id', Auth::id())->where('config_type', '9to12')->firstOrFail();
            $pending = $config->orders()->where('is_order_placed', false)->where('status', true)->count();
            if ($pending > 0) {
                $notify[] = ['error', "Cannot delete. {$pending} orders pending."];
                return back()->withNotify($notify);
            }
            $config->delete();
            $notify[] = ['success', '9to12 configuration deleted successfully!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error deleting configuration.'];
            return back()->withNotify($notify);
        }
    }

    public function viewOrders($configId)
    {
        $pageTitle = '9to12 Auto Trading Orders';
        $config    = OIIVAutoConfig::where('user_id', Auth::id())->where('id', $configId)->where('config_type', '9to12')->firstOrFail();
        $orders    = OIIVAutoOrder::where('config_id', $configId)->where('user_id', Auth::id())
            ->with(['broker:id,client_name'])
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.oiiv-auto.orders', compact('pageTitle', 'config', 'orders'));
    }

    public function runSignalsManually(Request $request)
    {
        try {
            $testDate = $request->get('test_date');

            $helper = new \App\Helpers\PECE9to12AutoTradingHelper();
            $helper->processSignals($testDate ?: null);
            $helper->placeOrders($testDate ?: null);

            $notify[] = ['success', '9to12 signals processed and orders placed successfully!'];
        } catch (\Exception $e) {
            \Log::error('Manual 9to12 trigger: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
        }

        return back()->withNotify($notify);
    }
}