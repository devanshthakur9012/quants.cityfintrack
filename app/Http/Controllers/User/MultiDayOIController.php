<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * MultiDayOIController — 4-Day OI Accumulation / Distribution Pattern
 *
 * CORE IDEA:
 *   The original OIIVAutoController compares TODAY vs PREVIOUS DAY only.
 *   That gives a one-day snapshot — easy to fake or noise-driven.
 *
 *   This controller looks back across the last 4 trading days and tracks
 *   HOW the OI has been building up or unwinding DAY BY DAY.
 *
 *   If someone is slowly accumulating or writing over 3-4 days to avoid
 *   detection, this catches it. A single-day spike could be noise.
 *   A 4-day consistent trend is a real positioning signal.
 *
 * WHAT WE COMPUTE PER SYMBOL PER "TODAY":
 *
 *   Day Snapshots (EOD OI at 15:00):
 *     D0 = today 14:45  (latest)
 *     D1 = prev 1 trading day 15:00
 *     D2 = prev 2 trading days 15:00
 *     D3 = prev 3 trading days 15:00
 *     D4 = prev 4 trading days 15:00  (baseline)
 *
 *   Per-day OI change %:
 *     chg_D1 = (D0 - D1) / D1 * 100   ← yesterday → today
 *     chg_D2 = (D1 - D2) / D2 * 100   ← 2 days ago → yesterday
 *     chg_D3 = (D2 - D3) / D3 * 100
 *     chg_D4 = (D3 - D4) / D4 * 100
 *
 *   4-Day Net OI Change:
 *     net_chg = (D0 - D4) / D4 * 100   ← full 4-day drift
 *
 * SCORING ENGINE (per instrument type — CE and PE separately):
 *
 *   1. TREND SCORE (0–4):
 *      +1 for each day where OI moved in same direction as today
 *      Max = 4 (all 4 prev days consistent)
 *
 *   2. ACCELERATION CHECK:
 *      Is the rate of change increasing? chg_D1 > chg_D2 > chg_D3?
 *      → "ACCELERATING" or "DECELERATING" or "STABLE"
 *
 *   3. PATTERN CLASSIFICATION:
 *      STEADY_BUILDUP      — all 4 days positive, trend score >= 3
 *      STEADY_UNWINDING    — all 4 days negative, trend score >= 3
 *      LATE_SURGE          — D1/D2 flat, D3/D4 sudden spike
 *      EARLY_DISTRIBUTION  — D1/D2 spike, D3/D4 flat/negative
 *      REVERSAL_SIGNAL     — last 2 days opposite to first 2 days
 *      CHOPPY              — mixed / no clear pattern
 *
 *   4. MANIPULATION SCORE (0–10):
 *      High score = likely slow accumulation / distribution play
 *      Factors: consistency + acceleration + net magnitude + days positive
 *
 *   5. FINAL MULTI-DAY SIGNAL:
 *      Combines CE pattern + PE pattern → STRONG_BULLISH / BULLISH /
 *      BEARISH / STRONG_BEARISH / NEUTRAL / WAIT
 *
 * SIGNAL INTERPRETATION:
 *   CE STEADY_BUILDUP   → Sustained call writing → BEARISH
 *   CE STEADY_UNWINDING → Call writers exiting   → BULLISH
 *   PE STEADY_BUILDUP   → Sustained put writing  → BULLISH
 *   PE STEADY_UNWINDING → Put writers exiting    → BEARISH
 *   Reversal signals    → Trend change imminent
 *
 * DB COLUMN NOTE:
 *   trade_date    = DATETIME — always use whereDate()
 *   interval_time = DATETIME — use whereRaw("TIME(interval_time) = 'HH:MM:SS'")
 */
class MultiDayOIController extends Controller
{
    // =========================================================
    //  CONFIG
    // =========================================================

    /** Number of previous trading days to look back */
    private const LOOKBACK_DAYS = 4;

    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = '4-Day OI Accumulation Tracker';
        return view($this->activeTemplate . 'user.multiday-oi.index', compact('pageTitle'));
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
    //  EXPIRY HELPERS  (mirrors OIIVAutoController exactly)
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

        if ($expiry) return $expiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = $this->getNearestExpiryForDate($symbol, $date);
        if (!$expiry) return null;

        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)
                ->whereDate('expiry_date', '>', $expiry)
                ->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
            if ($next) return $next;
        }

        return $expiry;
    }

    private function getBestExpiry(string $symbol, string $date, string $activeExpiry): ?string
    {
        // Same expiry on this date? use it
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $activeExpiry)
            ->where('is_missing', 0)
            ->exists();

        if ($exists) return $activeExpiry;

        // Rollover — use nearest expiry that had data on this date
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereNotNull('expiry_date')
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  MAIN ANALYSIS
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterSignal    = $request->get('filter_signal');
            $filterPattern   = $request->get('filter_pattern');
            $minManipScore   = (int) $request->get('min_manip_score', 0);

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $results = [];

            foreach ($tradeDates as $date) {
                $rows = $this->buildRowsForDate($date, $selectedSymbols, $filterSignal, $filterPattern, $minManipScore);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }

            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?:
                $b['manip_score'] <=> $a['manip_score'] ?:
                $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('MultiDayOI Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR ONE DATE
    // =========================================================

    private function buildRowsForDate(
        string $date,
        array $symbolFilter,
        ?string $filterSignal,
        ?string $filterPattern,
        int $minManipScore
    ): array {
        // Get active symbols from today's FUT candle
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');

        if ($futCandles->isEmpty()) return [];

        // Build the 5 date anchors: D0=today, D1..D4=prev trading days
        $dateAnchors = [$date]; // D0
        $d = $date;
        for ($i = 1; $i <= self::LOOKBACK_DAYS; $i++) {
            $d = $this->getPreviousTradingDate($d);
            $dateAnchors[] = $d; // D1, D2, D3, D4
        }

        $rows = [];

        foreach ($futCandles as $symbol => $futCandle) {
            if ((float) $futCandle->close <= 0) continue;

            try {
                $currentClose  = (float) $futCandle->close;
                $activeExpiry  = $this->resolveActiveExpiry($symbol, $date);

                // ── Collect OI snapshots for D0..D4 ─────────────────
                $ceSnapshots = [];
                $peSnapshots = [];

                foreach ($dateAnchors as $dayIdx => $snapDate) {
                    $snapExpiry = $activeExpiry
                        ? $this->getBestExpiry($symbol, $snapDate, $activeExpiry)
                        : null;

                    $snapTime = ($dayIdx === 0) ? '14:45:00' : '15:00:00';

                    $ceSnapshots[$dayIdx] = $this->sumOI($symbol, 'CE', $snapDate, $snapTime, $snapExpiry);
                    $peSnapshots[$dayIdx] = $this->sumOI($symbol, 'PE', $snapDate, $snapTime, $snapExpiry);
                }

                // Skip if today has no option data
                if ($ceSnapshots[0] == 0 && $peSnapshots[0] == 0) continue;

                // ── Compute per-day OI change % ──────────────────────
                // chg[1] = D0 vs D1, chg[2] = D1 vs D2, etc.
                $ceChanges = $this->computeDailyChanges($ceSnapshots);
                $peChanges = $this->computeDailyChanges($peSnapshots);

                // ── 4-day net OI change (D0 vs D4 baseline) ──────────
                $ceNet = $ceSnapshots[4] > 0
                    ? round((($ceSnapshots[0] - $ceSnapshots[4]) / $ceSnapshots[4]) * 100, 2)
                    : 0;
                $peNet = $peSnapshots[4] > 0
                    ? round((($peSnapshots[0] - $peSnapshots[4]) / $peSnapshots[4]) * 100, 2)
                    : 0;

                // ── Pattern analysis per side ─────────────────────────
                $ceAnalysis = $this->analyzePattern($ceChanges, $ceSnapshots, 'CE');
                $peAnalysis = $this->analyzePattern($peChanges, $peSnapshots, 'PE');

                // ── Combined multi-day signal ─────────────────────────
                $combined = $this->getCombinedSignal($ceAnalysis, $peAnalysis);

                // ── Classic single-day signal (D0 vs D1 only) ────────
                $classicCePct   = $ceChanges[1] ?? 0;
                $classicPePct   = $peChanges[1] ?? 0;
                $classicSignal  = $this->getClassicOISignal($classicCePct, $classicPePct);

                // ── Strength / rank ───────────────────────────────────
                $diff         = abs($classicCePct - $classicPePct);
                $strengthRank = match(true) {
                    $diff > 40 => 'Rank 1',
                    $diff > 25 => 'Rank 2',
                    $diff > 10 => 'Rank 3',
                    $diff > 5  => 'Rank 4',
                    default    => 'Normal',
                };

                // ── Manip score (from CE + PE combined) ──────────────
                $manipScore = min(10, intval(($ceAnalysis['manip_score'] + $peAnalysis['manip_score']) / 2));

                // Apply filters
                if (!empty($filterSignal) && $combined['signal'] !== $filterSignal) continue;
                if (!empty($filterPattern)) {
                    $patternsToCheck = [$ceAnalysis['pattern'], $peAnalysis['pattern']];
                    if (!in_array($filterPattern, $patternsToCheck)) continue;
                }
                if ($manipScore < $minManipScore) continue;

                // ── 50MA and other signals ────────────────────────────
                $fut50Ma     = $this->getFut50MaSignal($symbol, $date);
                $priceSignal = $this->getPriceSignal($symbol, $date, $dateAnchors[1]);

                // ── FUT OI change (D0 vs D1) ──────────────────────────
                $prevFutCandle = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', 'FUT')
                    ->whereDate('trade_date', $dateAnchors[1])
                    ->whereRaw("TIME(interval_time) = '15:00:00'")
                    ->first();
                $futOI     = (int) ($futCandle->oi ?? 0);
                $futPrevOI = $prevFutCandle ? (int) ($prevFutCandle->oi ?? 0) : 0;
                $futOiPct  = $futPrevOI > 0 ? round((($futOI - $futPrevOI) / $futPrevOI) * 100, 2) : 0;

                $rows[] = [
                    'date'         => $date,
                    'symbol'       => $symbol,
                    'fut_symbol'   => $futCandle->trading_symbol ?? $symbol,
                    'spot_price'   => round($currentClose, 2),
                    'active_expiry' => $activeExpiry,

                    // ── Daily OI snapshots (raw) ──────────────────────
                    'ce_d0' => $ceSnapshots[0], 'ce_d1' => $ceSnapshots[1],
                    'ce_d2' => $ceSnapshots[2], 'ce_d3' => $ceSnapshots[3],
                    'ce_d4' => $ceSnapshots[4],

                    'pe_d0' => $peSnapshots[0], 'pe_d1' => $peSnapshots[1],
                    'pe_d2' => $peSnapshots[2], 'pe_d3' => $peSnapshots[3],
                    'pe_d4' => $peSnapshots[4],

                    // ── Daily change % ────────────────────────────────
                    'ce_chg_d1' => $ceChanges[1] ?? 0,
                    'ce_chg_d2' => $ceChanges[2] ?? 0,
                    'ce_chg_d3' => $ceChanges[3] ?? 0,
                    'ce_chg_d4' => $ceChanges[4] ?? 0,

                    'pe_chg_d1' => $peChanges[1] ?? 0,
                    'pe_chg_d2' => $peChanges[2] ?? 0,
                    'pe_chg_d3' => $peChanges[3] ?? 0,
                    'pe_chg_d4' => $peChanges[4] ?? 0,

                    // ── 4-day net ─────────────────────────────────────
                    'ce_net_4d' => $ceNet,
                    'pe_net_4d' => $peNet,

                    // ── CE pattern analysis ───────────────────────────
                    'ce_pattern'      => $ceAnalysis['pattern'],
                    'ce_acceleration' => $ceAnalysis['acceleration'],
                    'ce_trend_score'  => $ceAnalysis['trend_score'],
                    'ce_days_up'      => $ceAnalysis['days_up'],
                    'ce_days_down'    => $ceAnalysis['days_down'],
                    'ce_signal'       => $ceAnalysis['signal'],
                    'ce_manip_score'  => $ceAnalysis['manip_score'],

                    // ── PE pattern analysis ───────────────────────────
                    'pe_pattern'      => $peAnalysis['pattern'],
                    'pe_acceleration' => $peAnalysis['acceleration'],
                    'pe_trend_score'  => $peAnalysis['trend_score'],
                    'pe_days_up'      => $peAnalysis['days_up'],
                    'pe_days_down'    => $peAnalysis['days_down'],
                    'pe_signal'       => $peAnalysis['signal'],
                    'pe_manip_score'  => $peAnalysis['manip_score'],

                    // ── Combined ──────────────────────────────────────
                    'combined_signal'     => $combined['signal'],
                    'combined_action'     => $combined['action'],
                    'combined_confidence' => $combined['confidence'],
                    'combined_reason'     => $combined['reason'],

                    // ── Classic (1-day) for comparison ────────────────
                    'classic_signal'  => $classicSignal,
                    'classic_ce_pct'  => round($classicCePct, 2),
                    'classic_pe_pct'  => round($classicPePct, 2),

                    // ── Misc ─────────────────────────────────────────
                    'strength_rank'   => $strengthRank,
                    'manip_score'     => $manipScore,
                    'fut_50ma_signal' => $fut50Ma,
                    'price_signal'    => $priceSignal['signal'],
                    'price_chg_pct'   => $priceSignal['change_pct'],
                    'fut_oi'          => $futOI,
                    'fut_oi_prev'     => $futPrevOI,
                    'fut_oi_chg_pct'  => $futOiPct,

                    // Date anchors (for display)
                    'date_d1' => $dateAnchors[1],
                    'date_d2' => $dateAnchors[2],
                    'date_d3' => $dateAnchors[3],
                    'date_d4' => $dateAnchors[4],
                ];

            } catch (\Exception $e) {
                Log::error("MultiDayOI row error ({$symbol} {$date}): " . $e->getMessage());
            }
        }

        return $rows;
    }

    // =========================================================
    //  OI SUM HELPER
    // =========================================================

    private function sumOI(string $symbol, string $type, string $date, string $time, ?string $expiry): int
    {
        $q = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = ?", [$time]);

        if ($expiry) $q->whereDate('expiry_date', $expiry);

        return (int) $q->sum('oi');
    }

    // =========================================================
    //  COMPUTE DAILY CHANGE %
    //  Input:  [D0, D1, D2, D3, D4] indexed 0..4
    //  Output: [1 => D0vD1%, 2 => D1vD2%, 3 => D2vD3%, 4 => D3vD4%]
    // =========================================================

    private function computeDailyChanges(array $snapshots): array
    {
        $changes = [];
        for ($i = 1; $i <= self::LOOKBACK_DAYS; $i++) {
            $today = $snapshots[$i - 1] ?? 0;
            $prev  = $snapshots[$i]     ?? 0;
            $changes[$i] = $prev > 0 ? round((($today - $prev) / $prev) * 100, 4) : 0;
        }
        return $changes;
    }

    // =========================================================
    //  PATTERN ANALYSIS ENGINE
    // =========================================================

    private function analyzePattern(array $changes, array $snapshots, string $side): array
    {
        // $changes[1] = most recent, $changes[4] = oldest
        $vals = [$changes[1], $changes[2], $changes[3], $changes[4]];

        $daysUp   = count(array_filter($vals, fn($v) => $v > 0));
        $daysDown = count(array_filter($vals, fn($v) => $v < 0));

        // ── Trend score: how many of 4 days moved in same direction as today
        $todayDir  = $vals[0] >= 0 ? 'up' : 'down';
        $trendScore = 0;
        foreach ($vals as $v) {
            if ($todayDir === 'up'   && $v > 0) $trendScore++;
            if ($todayDir === 'down' && $v < 0) $trendScore++;
        }

        // ── Acceleration: is momentum speeding up or slowing?
        $abs = array_map('abs', $vals);
        $acceleration = 'STABLE';
        if ($abs[0] > $abs[1] && $abs[1] > $abs[2]) $acceleration = 'ACCELERATING';
        elseif ($abs[0] < $abs[1] && $abs[1] < $abs[2]) $acceleration = 'DECELERATING';
        elseif ($abs[0] > $abs[2]) $acceleration = 'LATE_SURGE';

        // ── Pattern classification ────────────────────────────────────────
        $pattern = 'CHOPPY';

        // All 4 days moving in same direction
        if ($daysUp >= 3 && $vals[0] > 0) {
            $pattern = $trendScore === 4 ? 'STEADY_BUILDUP' : 'MOSTLY_BUILDUP';
        } elseif ($daysDown >= 3 && $vals[0] < 0) {
            $pattern = $trendScore === 4 ? 'STEADY_UNWINDING' : 'MOSTLY_UNWINDING';
        }
        // Reversal: last 2 days opposite to first 2 days
        elseif ($vals[0] > 0 && $vals[1] > 0 && $vals[2] < 0 && $vals[3] < 0) {
            $pattern = 'REVERSAL_BULLISH'; // was unwinding, now building
        } elseif ($vals[0] < 0 && $vals[1] < 0 && $vals[2] > 0 && $vals[3] > 0) {
            $pattern = 'REVERSAL_BEARISH'; // was building, now unwinding
        }
        // Late surge: flat first, big move recent
        elseif ($abs[0] > 20 && $abs[1] > 15 && $abs[2] < 5 && $abs[3] < 5) {
            $pattern = 'LATE_SURGE';
        }
        // Early distribution: spike then fade
        elseif ($abs[0] < 5 && $abs[1] < 5 && $abs[2] > 15 && $vals[2] > 0) {
            $pattern = 'EARLY_DISTRIBUTION';
        }
        // Gradual: small consistent moves (stealth accumulation)
        elseif ($daysUp === 4 && max($abs) < 15) {
            $pattern = 'STEALTH_BUILDUP';
        } elseif ($daysDown === 4 && max($abs) < 15) {
            $pattern = 'STEALTH_UNWINDING';
        }

        // ── Manipulation score (0–10) ─────────────────────────────────────
        // Higher = more likely sustained deliberate positioning
        $manipScore = 0;

        // Consistency across 4 days (max 4 pts)
        $manipScore += $trendScore;

        // Acceleration adds urgency (max 2 pts)
        if ($acceleration === 'ACCELERATING') $manipScore += 2;
        elseif ($acceleration === 'LATE_SURGE') $manipScore += 1;

        // Net magnitude of 4-day move (max 2 pts)
        $netMag = abs($snapshots[0] - $snapshots[4]);
        $base   = $snapshots[4] > 0 ? $snapshots[4] : 1;
        $netPct = ($netMag / $base) * 100;
        if ($netPct > 30) $manipScore += 2;
        elseif ($netPct > 15) $manipScore += 1;

        // Stealth bonus (small consistent moves = deliberate)
        if (in_array($pattern, ['STEALTH_BUILDUP', 'STEALTH_UNWINDING'])) $manipScore += 2;

        $manipScore = min(10, $manipScore);

        // ── Per-side signal ───────────────────────────────────────────────
        // For CE: buildup = writers = BEARISH for market
        //         unwinding = writers exit = BULLISH for market
        // For PE: buildup = writers = BULLISH for market
        //         unwinding = writers exit = BEARISH for market

        $signal = 'NEUTRAL';
        if ($side === 'CE') {
            $signal = match($pattern) {
                'STEADY_BUILDUP', 'MOSTLY_BUILDUP', 'STEALTH_BUILDUP', 'LATE_SURGE'
                    => $trendScore >= 3 ? 'STRONG_BEARISH' : 'BEARISH',
                'STEADY_UNWINDING', 'MOSTLY_UNWINDING', 'STEALTH_UNWINDING'
                    => $trendScore >= 3 ? 'STRONG_BULLISH' : 'BULLISH',
                'REVERSAL_BULLISH' => 'BULLISH',
                'REVERSAL_BEARISH' => 'BEARISH',
                'EARLY_DISTRIBUTION' => 'BEARISH_WEAK',
                default => 'NEUTRAL',
            };
        } else { // PE
            $signal = match($pattern) {
                'STEADY_BUILDUP', 'MOSTLY_BUILDUP', 'STEALTH_BUILDUP', 'LATE_SURGE'
                    => $trendScore >= 3 ? 'STRONG_BULLISH' : 'BULLISH',
                'STEADY_UNWINDING', 'MOSTLY_UNWINDING', 'STEALTH_UNWINDING'
                    => $trendScore >= 3 ? 'STRONG_BEARISH' : 'BEARISH',
                'REVERSAL_BULLISH' => 'BEARISH',
                'REVERSAL_BEARISH' => 'BULLISH',
                'EARLY_DISTRIBUTION' => 'BULLISH_WEAK',
                default => 'NEUTRAL',
            };
        }

        return compact(
            'pattern', 'acceleration', 'trendScore', 'daysUp', 'daysDown',
            'manipScore', 'signal'
        );
    }

    // =========================================================
    //  COMBINED SIGNAL (CE analysis + PE analysis)
    // =========================================================

    private function getCombinedSignal(array $ceAnalysis, array $peAnalysis): array
    {
        $ceSig = $ceAnalysis['signal'];
        $peSig = $peAnalysis['signal'];

        $bullish = ['STRONG_BULLISH', 'BULLISH', 'BULLISH_WEAK'];
        $bearish = ['STRONG_BEARISH', 'BEARISH', 'BEARISH_WEAK'];

        $ceStrong = in_array($ceSig, ['STRONG_BULLISH', 'STRONG_BEARISH']);
        $peStrong = in_array($peSig, ['STRONG_BULLISH', 'STRONG_BEARISH']);
        $ceBull   = in_array($ceSig, $bullish);
        $ceBear   = in_array($ceSig, $bearish);
        $peBull   = in_array($peSig, $bullish);
        $peBear   = in_array($peSig, $bearish);

        // ── STRONG BULLISH: CE strong buildup (writing) + PE strong buildup
        if ($ceSig === 'STRONG_BULLISH' && $peSig === 'STRONG_BULLISH') {
            return ['signal' => 'STRONG_BULLISH', 'action' => 'BUY CE',
                'confidence' => 'VERY_HIGH',
                'reason' => 'CE unwinding 4d (writers exiting) + PE buildup 4d (put writing = bull hedge)'];
        }

        if ($ceSig === 'STRONG_BEARISH' && $peSig === 'STRONG_BEARISH') {
            return ['signal' => 'STRONG_BEARISH', 'action' => 'BUY PE',
                'confidence' => 'VERY_HIGH',
                'reason' => 'CE buildup 4d (sustained call writing) + PE unwinding 4d (put writers exiting)'];
        }

        // ── HIGH CONFIDENCE: Both sides agree (non-strong)
        if ($ceBull && $peBull) {
            $conf = ($ceStrong || $peStrong) ? 'HIGH' : 'MEDIUM';
            return ['signal' => 'BULLISH', 'action' => 'BUY CE',
                'confidence' => $conf,
                'reason' => "CE: {$ceSig} + PE: {$peSig} (4-day dual confirmation)"];
        }

        if ($ceBear && $peBear) {
            $conf = ($ceStrong || $peStrong) ? 'HIGH' : 'MEDIUM';
            return ['signal' => 'BEARISH', 'action' => 'BUY PE',
                'confidence' => $conf,
                'reason' => "CE: {$ceSig} + PE: {$peSig} (4-day dual confirmation)"];
        }

        // ── MEDIUM: One strong side
        if ($ceSig === 'STRONG_BULLISH') {
            return ['signal' => 'BULLISH', 'action' => 'BUY CE', 'confidence' => 'MEDIUM',
                'reason' => 'CE steady unwinding 4d — call writers exiting (bullish)'];
        }
        if ($ceSig === 'STRONG_BEARISH') {
            return ['signal' => 'BEARISH', 'action' => 'BUY PE', 'confidence' => 'MEDIUM',
                'reason' => 'CE steady buildup 4d — sustained call writing (bearish)'];
        }
        if ($peSig === 'STRONG_BULLISH') {
            return ['signal' => 'BULLISH', 'action' => 'BUY CE', 'confidence' => 'MEDIUM',
                'reason' => 'PE steady buildup 4d — sustained put writing (bullish)'];
        }
        if ($peSig === 'STRONG_BEARISH') {
            return ['signal' => 'BEARISH', 'action' => 'BUY PE', 'confidence' => 'MEDIUM',
                'reason' => 'PE steady unwinding 4d — put writers exiting (bearish)'];
        }

        // ── Reversal signals
        if ($ceAnalysis['pattern'] === 'REVERSAL_BULLISH' || $peAnalysis['pattern'] === 'REVERSAL_BULLISH') {
            return ['signal' => 'BULLISH', 'action' => 'BUY CE', 'confidence' => 'LOW',
                'reason' => 'Reversal pattern detected — direction flip in last 2 days'];
        }
        if ($ceAnalysis['pattern'] === 'REVERSAL_BEARISH' || $peAnalysis['pattern'] === 'REVERSAL_BEARISH') {
            return ['signal' => 'BEARISH', 'action' => 'BUY PE', 'confidence' => 'LOW',
                'reason' => 'Reversal pattern detected — direction flip in last 2 days'];
        }

        // ── Lone weak signals
        if ($ceBull || $peBull) {
            return ['signal' => 'BULLISH', 'action' => 'BUY CE', 'confidence' => 'LOW',
                'reason' => "Weak bullish — CE: {$ceSig} / PE: {$peSig}"];
        }
        if ($ceBear || $peBear) {
            return ['signal' => 'BEARISH', 'action' => 'BUY PE', 'confidence' => 'LOW',
                'reason' => "Weak bearish — CE: {$ceSig} / PE: {$peSig}"];
        }

        return ['signal' => 'NEUTRAL', 'action' => 'WAIT', 'confidence' => 'NONE',
            'reason' => 'No sustained 4-day directional pattern'];
    }

    // =========================================================
    //  CLASSIC 1-DAY OI SIGNAL (for comparison column)
    // =========================================================

    private function getClassicOISignal(float $cePct, float $pePct): string
    {
        if ($cePct > 0 && $pePct < 0) return 'BEARISH';
        if ($cePct < 0 && $pePct > 0) return 'BULLISH';
        if ($cePct > 0 && $pePct > 0) return $cePct > $pePct ? 'BEARISH' : 'BULLISH';
        if ($cePct < 0 && $pePct < 0) return $cePct < $pePct ? 'BULLISH' : 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  50 MA  (mirrors OIIVAutoController)
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

    private function getFut50MaSignal(string $baseSymbol, string $tradeDate): string
    {
        $historyStart = Carbon::parse($tradeDate)->subDays(120)->toDateString();

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $historyStart)
            ->whereDate('trade_date', '<=', $tradeDate)
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get([DB::raw("DATE(trade_date) as candle_date"), DB::raw("TIME(interval_time) as candle_time"), 'close']);

        if ($allCandles->isEmpty()) return 'N/A';

        $closeValues = $allCandles->pluck('close')->map(fn($v) => (float) $v)->toArray();
        $closeMa     = $this->calculateRollingMA($closeValues, 50);

        $targetIdx = null;
        foreach ($allCandles as $idx => $candle) {
            $cd   = is_string($candle->candle_date) ? $candle->candle_date : Carbon::parse($candle->candle_date)->toDateString();
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($cd === $tradeDate && $time >= '14:45' && $time <= '15:15') { $targetIdx = $idx; break; }
        }
        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $cd = is_string($candle->candle_date) ? $candle->candle_date : Carbon::parse($candle->candle_date)->toDateString();
                if ($cd === $tradeDate) $targetIdx = $idx;
            }
        }

        if ($targetIdx === null || !isset($closeMa[$targetIdx]) || $closeMa[$targetIdx] === null) return 'N/A';

        $close = $closeValues[$targetIdx];
        $ma    = $closeMa[$targetIdx];
        if ($close > $ma) return 'BULLISH';
        if ($close < $ma) return 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  PRICE SIGNAL  (mirrors OIIVAutoController)
    // =========================================================

    private function getPriceSignal(string $symbol, string $date, string $prevDate): array
    {
        $todayCandle = OptionOhlcData::where('base_symbol', $symbol)->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)->whereRaw("TIME(interval_time) = '14:45:00'")->first();
        $prevCandle  = OptionOhlcData::where('base_symbol', $symbol)->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)->whereRaw("TIME(interval_time) = '15:00:00'")->first();

        if (!$todayCandle || !$prevCandle) return ['signal' => 'N/A', 'change_pct' => 0];

        $todayClose = (float) $todayCandle->close;
        $prevClose  = (float) $prevCandle->close;
        if ($prevClose <= 0 || $todayClose <= 0) return ['signal' => 'N/A', 'change_pct' => 0];

        $changePct = (($todayClose - $prevClose) / $prevClose) * 100;
        $signal    = $todayClose > $prevClose ? 'BULLISH' : ($todayClose < $prevClose ? 'BEARISH' : 'NEUTRAL');
        return ['signal' => $signal, 'change_pct' => round($changePct, 2)];
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay(); $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) return $prev->format('Y-m-d');
            $prev->subDay(); $attempts++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}