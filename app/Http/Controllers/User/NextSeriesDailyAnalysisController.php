<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NextSeriesDailyOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * NextSeriesDailyAnalysisController
 *
 * Analyses OI change for the NEXT expiry series using daily bars.
 *
 * Data source : next_series_daily_ohlc_data
 * Comparison  : Previous trading day CE/PE OI  →  Selected date CE/PE OI
 *
 * Buy Strike logic:
 *   BULLISH → pick the CE strike with the HIGHEST volume today
 *   BEARISH → pick the PE strike with the HIGHEST volume today
 *   NEUTRAL → null
 *   Returns full trading_symbol e.g. SHREECEM26APR21750PE
 */
class NextSeriesDailyAnalysisController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Next Series Daily OI Analysis';
        return view($this->activeTemplate . 'user.next-series-daily.index', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS  (for filter dropdown)
    // =========================================================

    public function getSymbols()
    {
        $symbols = NextSeriesDailyOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  MAIN ANALYSIS ENDPOINT
    // =========================================================

    /**
     * GET /next-series-daily/analyze
     *
     * Query params:
     *   from_date   Y-m-d  (required)
     *   to_date     Y-m-d  (required)
     *   symbols[]   array  (optional — empty = all)
     *   sentiment   BULLISH | BEARISH | NEUTRAL | '' (optional filter)
     */
    public function analyze(Request $request)
    {
        try {
            $fromDate  = $request->get('from_date');
            $toDate    = $request->get('to_date');
            $symbols   = $request->get('symbols', []);
            $sentiment = $request->get('sentiment');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates.',
                    'data'    => [],
                ]);
            }

            // All distinct trading dates with FUT data in range
            $tradeDates = NextSeriesDailyOhlcData::whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->where('instrument_type', 'FUT')
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success'       => true,
                    'data'          => [],
                    'total_records' => 0,
                    'message'       => 'No data found for the selected date range.',
                ]);
            }

            $results = [];
            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                foreach ($this->buildRowsForDate($date, $prevDate, $symbols, $sentiment) as $row) {
                    $results[] = $row;
                }
            }

            // Newest date first, then symbol A→Z
            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found.',
            ]);

        } catch (\Exception $e) {
            Log::error('NextSeriesDailyAnalysis::analyze — ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR A SINGLE DATE
    // =========================================================

    private function buildRowsForDate(
        string  $date,
        string  $prevDate,
        array   $symbolFilter,
        ?string $sentimentFilter
    ): array {
        // ── Active symbols on $date ───────────────────────────────────────
        $symQuery = NextSeriesDailyOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT');

        if (!empty($symbolFilter)) {
            $symQuery->whereIn('base_symbol', $symbolFilter);
        }

        $symbols = $symQuery->pluck('base_symbol')->unique()->sort()->values()->toArray();
        if (empty($symbols)) return [];

        // ── Bulk-fetch today's CE/PE rows ─────────────────────────────────
        // Include volume + trading_symbol for buy-strike resolution
        $todayOpts = NextSeriesDailyOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereIn('base_symbol', $symbols)
            ->where('is_missing', 0)
            ->get(['base_symbol', 'instrument_type', 'oi', 'volume',
                   'strike', 'trading_symbol', 'expiry_date']);

        // ── Bulk-fetch prev-day's CE/PE rows ──────────────────────────────
        $prevOpts = NextSeriesDailyOhlcData::whereDate('trade_date', $prevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereIn('base_symbol', $symbols)
            ->where('is_missing', 0)
            ->get(['base_symbol', 'instrument_type', 'oi']);

        $todayBySymbol = $todayOpts->groupBy('base_symbol');
        $prevBySymbol  = $prevOpts->groupBy('base_symbol');

        $rows = [];

        foreach ($symbols as $symbol) {
            $todayRows = $todayBySymbol[$symbol] ?? collect();
            $prevRows  = $prevBySymbol[$symbol]  ?? collect();

            // ── OI totals ─────────────────────────────────────────────────
            $ceCurOI  = (int) $todayRows->where('instrument_type', 'CE')->sum('oi');
            $peCurOI  = (int) $todayRows->where('instrument_type', 'PE')->sum('oi');
            $ceOpenOI = (int) $prevRows->where('instrument_type', 'CE')->sum('oi');
            $peOpenOI = (int) $prevRows->where('instrument_type', 'PE')->sum('oi');

            if ($ceCurOI === 0 && $peCurOI === 0) continue;

            $ceOiPct = $ceOpenOI > 0
                ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 2) : 0;
            $peOiPct = $peOpenOI > 0
                ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 2) : 0;

            // ── Signal & sentiment ────────────────────────────────────────
            $oiSignal  = $this->getOISignal($ceOiPct, $peOiPct);
            $sentiment = $oiSignal['signal'];   // BULLISH | BEARISH | NEUTRAL

            if (!empty($sentimentFilter) && $sentiment !== $sentimentFilter) continue;

            // ── Strength rank  |CE% − PE%| ────────────────────────────────
            $absCe = abs($ceOiPct);
            $absPe = abs($peOiPct);
            $diff  = abs($ceOiPct - $peOiPct);

            $strengthRank = match(true) {
                $diff > 40 => 'Rank 1',
                $diff > 25 => 'Rank 2',
                $diff > 10 => 'Rank 3',
                $diff > 5  => 'Rank 4',
                default    => 'Normal',
            };

            $isBoth       = str_contains($oiSignal['condition'], 'Both');
            $strongerSide = $isBoth
                ? ($absCe > $absPe ? 'CE' : ($absPe > $absCe ? 'PE' : 'EQUAL'))
                : 'CLEAR';

            // ── Buy Strike: highest-volume CE (BULLISH) or PE (BEARISH) ──
            $buyStrike = $this->resolveBuyStrike($todayRows, $sentiment);

            $rows[] = [
                'date'   => $date,
                'symbol' => $symbol,

                'ce_oi'            => $ceCurOI,
                'ce_oi_prev'       => $ceOpenOI,
                'ce_oi_change_pct' => $ceOiPct,

                'pe_oi'            => $peCurOI,
                'pe_oi_prev'       => $peOpenOI,
                'pe_oi_change_pct' => $peOiPct,

                'oi_condition'    => $oiSignal['condition'],
                'final_sentiment' => $sentiment,

                'strength_rank' => $strengthRank,
                'strength_diff' => round($diff, 2),
                'stronger_side' => $strongerSide,

                // ── Buy strike info ───────────────────────────────────────
                // Full trading symbol e.g. SHREECEM26APR21750PE
                'buy_trading_symbol' => $buyStrike['trading_symbol'],
                'buy_option_type'    => $buyStrike['option_type'],   // CE | PE | null
                'buy_volume'         => $buyStrike['volume'],
                'buy_oi'             => $buyStrike['oi'],
            ];
        }

        return $rows;
    }

    // =========================================================
    //  RESOLVE BUY STRIKE — highest volume strike
    // =========================================================

    /**
     * Pick the strike with the highest volume for the direction indicated
     * by $sentiment:
     *   BULLISH  →  scan CE rows → return max-volume CE trading_symbol
     *   BEARISH  →  scan PE rows → return max-volume PE trading_symbol
     *   NEUTRAL  →  null
     *
     * Volume = day's total traded contracts. The most-traded strike has
     * the highest liquidity and movement, making it best for order placement.
     *
     * Fallback: if all volume values are 0, fall back to highest OI strike.
     */
    private function resolveBuyStrike($todayRows, string $sentiment): array
    {
        $empty = [
            'trading_symbol' => null,
            'option_type'    => null,
            'volume'         => 0,
            'oi'             => 0,
        ];

        if ($sentiment === 'NEUTRAL') return $empty;

        $optionType = $sentiment === 'BULLISH' ? 'CE' : 'PE';

        $candidates = $todayRows->where('instrument_type', $optionType);

        // Primary: highest volume
        $best = $candidates->where('volume', '>', 0)->sortByDesc('volume')->first();

        // Fallback: highest OI when no volume data
        if (!$best) {
            $best = $candidates->sortByDesc('oi')->first();
        }

        if (!$best) return $empty;

        return [
            'trading_symbol' => $best->trading_symbol,
            'option_type'    => $optionType,
            'volume'         => (int) ($best->volume ?? 0),
            'oi'             => (int) ($best->oi     ?? 0),
        ];
    }

    // =========================================================
    //  OI SIGNAL
    // =========================================================

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

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    private function dateStr($value): string
    {
        if ($value instanceof Carbon) return $value->toDateString();
        if (is_string($value))        return substr($value, 0, 10);
        return Carbon::parse($value)->toDateString();
    }
}