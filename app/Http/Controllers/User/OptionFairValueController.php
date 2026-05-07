<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Helpers\OptionFairPriceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Option Fair Value Analyser — Black-Scholes Theoretical Pricing
 *
 * Modes:
 *   1. All Symbols  (no symbol filter) → one row per symbol, LATEST candle only
 *   2. Single Symbol selected          → one row PER CANDLE TIME (09:15 … 15:15)
 *
 * Signal Logic (per-row):
 *   BUY_CALL : PE expensive + CE cheap + PE IV expanding + spot >= strike
 *   BUY_PUT  : CE expensive + PE cheap + CE IV expanding + spot <= strike
 *   NO_TRADE : no signal
 *
 * CE IV and PE IV are calculated INDEPENDENTLY via Newton-Raphson (calcIV).
 * Combined ATM IV is kept only for Black-Scholes fair-price calculation.
 *
 * IV 11-MA:
 *   ce_iv_11ma and pe_iv_11ma are separate rolling windows (period = 11).
 *   In single-symbol mode, the last 11 candles of the PREVIOUS trading day
 *   are prepended as seed rows — MA is fully warmed at 09:15, zero blind period.
 *
 * Valuation Tolerance: ±5%
 * No DB writes — all calculations are runtime only.
 *
 * ── Bugs fixed vs previous version ──────────────────────────────────────────
 * FIX 1: calcIV() alias now exists in OptionFairPriceCalculator (was calling
 *         a non-existent method — would throw a fatal BadMethodCallException).
 * FIX 2: calcSignal() return array used compact() creating camelCase keys
 *         (ceDiffPct, peDiffPct) alongside the correct snake_case keys.
 *         Now returns a single clean array with only snake_case keys.
 * FIX 3: sortRows() 'signal' case was dead code — a stray break after the
 *         'iv_below' case meant the usort for signal order never ran.
 *         Now has its own properly-structured case 'signal'.
 * FIX 4: Single-symbol JSON response was missing 'prev_date' key that the
 *         Blade JS reads to show the prev-day seeding notice.
 */
class OptionFairValueController extends Controller
{
    private const STEP_DEFAULTS = [
        'NIFTY'      => 50,
        'BANKNIFTY'  => 100,
        'FINNIFTY'   => 50,
        'MIDCPNIFTY' => 25,
        'SENSEX'     => 100,
        'BANKEX'     => 100,
    ];

    // ─────────────────────────────────────────────────────────────────────────
    //  PAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Option Fair Value Analyser';
        return view($this->activeTemplate . 'user.option-fair-value.index', compact('pageTitle'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  AJAX: ANALYZE
    // ─────────────────────────────────────────────────────────────────────────

    public function analyze(Request $request)
    {
        try {
            $strikeFilter = $request->get('strike_filter', 'ATM');
            $sortBy       = $request->get('sort_by', 'symbol');
            $symbolFilter = $request->get('symbol');   // '' or null = all symbols
            $dateFilter   = $request->get('date');     // YYYY-MM-DD or null

            // ── 1. Resolve trade date ──────────────────────────────────────
            if ($dateFilter) {
                $exists = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
                    ->whereDate('trade_date', $dateFilter)
                    ->exists();
                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No data found for ' . $dateFilter . '. Market may have been closed.',
                    ]);
                }
                $tradeDate = $dateFilter;
            } else {
                $tradeDate = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
                    ->max(DB::raw('DATE(trade_date)'));
                if (!$tradeDate) {
                    return response()->json(['success' => false, 'message' => 'No data found in database']);
                }
            }

            $isToday = ($tradeDate === now()->toDateString());

            // ── 2. Single-symbol mode: ALL candle times ───────────────────
            if ($symbolFilter) {

                $cancleTimes = OptionOhlcData::where('base_symbol', $symbolFilter)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->selectRaw('DISTINCT TIME(interval_time) AS candle_time')
                    ->orderByRaw('TIME(interval_time) ASC')
                    ->pluck('candle_time')
                    ->map(fn($t) => substr((string)$t, 0, 5))
                    ->unique()->values()->toArray();

                if (empty($cancleTimes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No candle data for ' . $symbolFilter . ' on ' . $tradeDate,
                    ]);
                }

                // ── Load previous-day candles to seed the IV-11MA ─────────
                // FIX 4: $prevDate is returned in the JSON so Blade can show
                //         the "seeded from prev-day" notice in the UI.
                $prevDate = OptionOhlcData::where('base_symbol', $symbolFilter)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->whereDate('trade_date', '<', $tradeDate)
                    ->max(DB::raw('DATE(trade_date)'));

                $allRows = [];

                if ($prevDate) {
                    $prevTimes = OptionOhlcData::where('base_symbol', $symbolFilter)
                        ->whereIn('instrument_type', ['CE', 'PE'])
                        ->whereDate('trade_date', $prevDate)
                        ->where('is_missing', 0)
                        ->selectRaw('DISTINCT TIME(interval_time) AS candle_time')
                        ->orderByRaw('TIME(interval_time) ASC')
                        ->pluck('candle_time')
                        ->map(fn($t) => substr((string)$t, 0, 5))
                        ->unique()->values()->toArray();

                    // Take last 11 prev-day candles — enough to seed both MAs
                    $prevTimes = array_slice($prevTimes, -11);

                    foreach ($prevTimes as $ct) {
                        foreach ($this->buildSymbolRows($symbolFilter, $prevDate, $ct . ':00', $strikeFilter) as $row) {
                            $row['_prev_day'] = true;   // excluded from final response
                            $allRows[] = $row;
                        }
                    }
                }

                // Today's candles
                foreach ($cancleTimes as $candleTime) {
                    foreach ($this->buildSymbolRows($symbolFilter, $tradeDate, $candleTime . ':00', $strikeFilter) as $row) {
                        $row['_prev_day'] = false;
                        $allRows[] = $row;
                    }
                }

                // Post-process: MA computed over full window, signals then recalculated
                $allRows = $this->addIvMovingAverage($allRows, 11);
                $allRows = $this->recalcSignalsWithMa($allRows);

                // Strip seed (prev-day) rows — only today's rows go to the UI
                $rows = array_values(array_filter($allRows, fn($r) => !($r['_prev_day'] ?? false)));
                foreach ($rows as &$r) { unset($r['_prev_day']); }
                unset($r);

                $latestTime = end($cancleTimes);

                return response()->json([
                    'success'       => true,
                    'trade_date'    => $tradeDate,
                    'latest_time'   => $latestTime,
                    'is_today'      => $isToday,
                    'mode'          => 'single',
                    'strike_filter' => $strikeFilter,
                    'prev_date'     => $prevDate,   // FIX 4: was missing — Blade JS reads this
                    'total_symbols' => 1,
                    'total_rows'    => count($rows),
                    'summary'       => $this->buildSummary($rows),
                    'rows'          => $rows,
                ]);
            }

            // ── 3. All-symbols mode: latest candle only ───────────────────
            $latestTime = OptionOhlcData::whereDate('trade_date', $tradeDate)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->max(DB::raw('TIME(interval_time)'));

            $symbols = OptionOhlcData::whereDate('trade_date', $tradeDate)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->orderBy('base_symbol')
                ->pluck('base_symbol')
                ->values()
                ->toArray();

            if (empty($symbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols found for ' . $tradeDate]);
            }

            $rows = [];
            foreach ($symbols as $symbol) {
                foreach ($this->buildSymbolRows($symbol, $tradeDate, $latestTime, $strikeFilter) as $row) {
                    $rows[] = $row;
                }
            }

            // Scanner mode: IV-expansion gate bypassed — mispricing-only signals
            $rows = $this->recalcSignalsWithMa($rows, $scannerMode = true);
            $rows = $this->sortRows($rows, $sortBy);

            return response()->json([
                'success'       => true,
                'trade_date'    => $tradeDate,
                'latest_time'   => substr($latestTime ?? '', 0, 5),
                'is_today'      => $isToday,
                'mode'          => 'all',
                'strike_filter' => $strikeFilter,
                'prev_date'     => null,
                'total_symbols' => count($symbols),
                'total_rows'    => count($rows),
                'summary'       => $this->buildSummary($rows),
                'rows'          => $rows,
            ]);

        } catch (\Exception $e) {
            Log::error('OptionFairValue analyze: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BUILD ROWS FOR ONE SYMBOL AT ONE CANDLE TIME
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSymbolRows(
        string  $symbol,
        string  $date,
        ?string $candleTime,
        string  $strikeFilter
    ): array {
        // ── A. Spot price from FUT ────────────────────────────────────────
        $futQuery = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0);

        if ($candleTime) {
            $futCandle = (clone $futQuery)
                ->whereRaw("TIME(interval_time) = ?", [$candleTime])
                ->first();
            if (!$futCandle) {
                $futCandle = (clone $futQuery)
                    ->whereRaw("TIME(interval_time) <= ?", [$candleTime])
                    ->orderByDesc('interval_time')
                    ->first();
            }
        } else {
            $futCandle = $futQuery->orderByDesc('interval_time')->first();
        }

        $spot = $futCandle ? (float) $futCandle->close : 0;

        if ($spot <= 0) {
            $spot = $this->estimateSpotFromOptions($symbol, $date, $candleTime);
        }
        if ($spot <= 0) return [];

        // ── B. Step size ──────────────────────────────────────────────────
        $step = $this->getStepSize($symbol, $date);

        // ── C. ATM strike ─────────────────────────────────────────────────
        $atm = round($spot / $step) * $step;

        // ── D. Target strike ──────────────────────────────────────────────
        $targetStrike = match ($strikeFilter) {
            'ATM+1' => $atm + $step,
            'ATM-1' => $atm - $step,
            default => $atm,
        };

        // ── E. Days to expiry ─────────────────────────────────────────────
        $expiry       = $this->getNearestExpiry($symbol, $date);
        $daysToExpiry = $expiry
            ? (int) max(1, Carbon::parse($date)->diffInDays(Carbon::parse($expiry)))
            : 30;

        // ── F. CE IV + PE IV (calculated independently per leg) ───────────
        // ATM candles only — IV MUST come from ATM strike to avoid the circular
        // loop: OTM LTP → IV → BS(IV) ≈ OTM LTP → diff ≈ 0 (useless signal).
        $atmCeCandle = $this->getOptionCandle($symbol, $date, $candleTime, 'CE', $atm, $expiry);
        $atmPeCandle = $this->getOptionCandle($symbol, $date, $candleTime, 'PE', $atm, $expiry);
        $atmCeLtp    = $atmCeCandle ? (float) ($atmCeCandle->close ?? 0) : 0;
        $atmPeLtp    = $atmPeCandle ? (float) ($atmPeCandle->close ?? 0) : 0;

        // FIX 1: was calling ::calcIV() which did not exist.
        // ::calcIV() is now a proper alias for ::calculateIV() in the helper.
        $ceIvRaw = $atmCeLtp > 0
            ? OptionFairPriceCalculator::calcIV($spot, $atm, $daysToExpiry, $atmCeLtp, 'CE')
            : null;

        $peIvRaw = $atmPeLtp > 0
            ? OptionFairPriceCalculator::calcIV($spot, $atm, $daysToExpiry, $atmPeLtp, 'PE')
            : null;

        // Combined ATM IV for BS fair-price valuation (needs both legs averaged)
        $atmIv = OptionFairPriceCalculator::calcAtmIV(
            $spot, $atm, $daysToExpiry,
            $atmCeLtp > 0 ? $atmCeLtp : null,
            $atmPeLtp > 0 ? $atmPeLtp : null
        );

        // ── G. Target strike CE + PE candles ─────────────────────────────
        $ceCandle = $this->getOptionCandle($symbol, $date, $candleTime, 'CE', $targetStrike, $expiry);
        $peCandle = $this->getOptionCandle($symbol, $date, $candleTime, 'PE', $targetStrike, $expiry);

        $ceLtp = $ceCandle ? (float) ($ceCandle->close ?? 0) : 0;
        $peLtp = $peCandle ? (float) ($peCandle->close ?? 0) : 0;

        if ($ceLtp <= 0 && $peLtp <= 0) return [];

        // Snapshot time string for display
        $snapshotTime = $ceCandle
            ? substr($ceCandle->interval_time ?? '', 11, 5)
            : ($peCandle ? substr($peCandle->interval_time ?? '', 11, 5) : substr($candleTime ?? '', 0, 5));

        // ── H. CE valuation ───────────────────────────────────────────────
        $ceValuation = $ceLtp > 0
            ? OptionFairPriceCalculator::comprehensiveValuation(
                $spot, $targetStrike, $daysToExpiry, 'CE',
                $ceLtp, $symbol, $ceCandle->trading_symbol ?? null, $atmIv
            )
            : $this->emptyValuation();

        // ── I. PE valuation ───────────────────────────────────────────────
        $peValuation = $peLtp > 0
            ? OptionFairPriceCalculator::comprehensiveValuation(
                $spot, $targetStrike, $daysToExpiry, 'PE',
                $peLtp, $symbol, $peCandle->trading_symbol ?? null, $atmIv
            )
            : $this->emptyValuation();

        $ceFair = $ceValuation['fair_price_bs'];
        $peFair = $peValuation['fair_price_bs'];

        return [[
            'symbol'            => $symbol,
            'time'              => $snapshotTime,
            'date'              => $date,
            'spot'              => round($spot, 2),
            'strike'            => $targetStrike,
            'atm_strike'        => $atm,
            'strike_level'      => $strikeFilter,
            'days_to_expiry'    => $daysToExpiry,
            'expiry_date'       => $expiry,

            // CE
            'ce_ltp'            => $ceLtp > 0 ? round($ceLtp, 2) : null,
            'ce_fair'           => $ceFair,
            'ce_status'         => $ceValuation['valuation_status'] ?? 'N/A',
            'ce_diff'           => $ceValuation['price_difference'] ?? null,
            'ce_diff_pct'       => $ceValuation['price_difference_percent'] ?? null,
            'ce_iv'             => $ceValuation['iv_used'] ?? null,
            'ce_iv_source'      => $ceValuation['iv_source'] ?? null,
            'ce_intrinsic'      => round(max(0, $spot - $targetStrike), 2),
            'ce_recommendation' => $ceValuation['recommendation'] ?? null,
            'ce_symbol'         => $ceCandle->trading_symbol ?? null,

            // PE
            'pe_ltp'            => $peLtp > 0 ? round($peLtp, 2) : null,
            'pe_fair'           => $peFair,
            'pe_status'         => $peValuation['valuation_status'] ?? 'N/A',
            'pe_diff'           => $peValuation['price_difference'] ?? null,
            'pe_diff_pct'       => $peValuation['price_difference_percent'] ?? null,
            'pe_iv'             => $peValuation['iv_used'] ?? null,
            'pe_iv_source'      => $peValuation['iv_source'] ?? null,
            'pe_intrinsic'      => round(max(0, $targetStrike - $spot), 2),
            'pe_recommendation' => $peValuation['recommendation'] ?? null,
            'pe_symbol'         => $peCandle->trading_symbol ?? null,

            // Shared ATM IV (for BS fair-price, not for signal MA)
            'atm_iv'            => $atmIv !== null ? round($atmIv * 100, 2) : null,
            'atm_iv_raw'        => $atmIv,
            'atm_iv_source'     => $ceValuation['iv_source'] ?? $peValuation['iv_source'] ?? null,

            // Independent CE IV (used for CE IV-11MA and signal gate)
            'ce_iv_pct'         => $ceIvRaw !== null ? round($ceIvRaw * 100, 2) : null,
            'ce_iv_raw'         => $ceIvRaw,

            // Independent PE IV (used for PE IV-11MA and signal gate)
            'pe_iv_pct'         => $peIvRaw !== null ? round($peIvRaw * 100, 2) : null,
            'pe_iv_raw'         => $peIvRaw,

            // MA placeholders — filled by addIvMovingAverage()
            'iv_11ma'           => null,
            'iv_11ma_raw'       => null,
            'ce_iv_11ma'        => null,
            'ce_iv_11ma_raw'    => null,
            'pe_iv_11ma'        => null,
            'pe_iv_11ma_raw'    => null,

            // Shared move
            'expected_move'     => $ceValuation['expected_move'] ?? $peValuation['expected_move'] ?? null,

            // Signal — filled in by recalcSignalsWithMa()
            'signal'            => 'NO_TRADE',
            'signal_reason'     => '',
            'signal_imbalance'  => null,
            'signal_ce_diff_pct' => null,
            'signal_pe_diff_pct' => null,
            'iv_regime'         => 'WARMING',
        ]];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SIGNAL LOGIC
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute a trade signal for a single row.
     *
     * BUY_CALL : PE overpriced + CE cheap + PE IV expanding + spot >= strike
     * BUY_PUT  : CE overpriced + PE cheap + CE IV expanding + spot <= strike
     * ATM GAMMA: |spot-strike| < 0.2% + large imbalance + relevant IV expanding
     *
     * Scanner mode (all-symbols): IV-expansion gate bypassed entirely.
     * Single-symbol mode: requires both CE and PE MAs to be available.
     *
     * FIX 2: Previous version used compact() creating camelCase keys (ceDiffPct)
     *        alongside the correctly-named snake_case keys, then merged them.
     *        Now returns one clean array with only snake_case keys.
     */
    private function calcSignal(
        float  $spot,
        float  $strike,
        float  $ceLtp,
        ?float $ceFair,
        float  $peLtp,
        ?float $peFair,
        ?float $ceIv,        // CE IV as decimal (e.g. 0.2441)
        ?float $ceIvMa,      // CE IV 11-MA as decimal; null = warming up
        ?float $peIv,        // PE IV as decimal
        ?float $peIvMa,      // PE IV 11-MA as decimal; null = warming up
        bool   $scannerMode = false
    ): array {
        // Guard — fair prices must be available
        if ($ceFair === null || $ceFair <= 0 || $peFair === null || $peFair <= 0) {
            return [
                'signal'      => 'NO_TRADE',
                'reason'      => 'Fair price unavailable',
                'ce_diff_pct' => 0.0,
                'pe_diff_pct' => 0.0,
                'imbalance'   => 0.0,
            ];
        }

        $ceDiff    = $ceLtp - $ceFair;
        $peDiff    = $peLtp - $peFair;
        $ceDiffPct = round(($ceDiff / $ceFair) * 100, 2);
        $peDiffPct = round(($peDiff / $peFair) * 100, 2);
        $imbalance = round($peDiffPct - $ceDiffPct, 2);
        $nearATM   = abs($spot - $strike) < ($spot * 0.002);

        // ── IV expansion gate ─────────────────────────────────────────────
        if (!$scannerMode) {
            // Both MAs must be warmed before any signal fires
            if ($ceIvMa === null || $peIvMa === null) {
                return [
                    'signal'      => 'NO_TRADE',
                    'reason'      => 'Warming up IV-11MA',
                    'ce_diff_pct' => $ceDiffPct,
                    'pe_diff_pct' => $peDiffPct,
                    'imbalance'   => $imbalance,
                ];
            }
            $ceIvExpanding = $ceIv !== null && $ceIv > $ceIvMa;
            $peIvExpanding = $peIv !== null && $peIv > $peIvMa;
        } else {
            // Scanner mode — bypass IV gate, mispricing-only signals
            $ceIvExpanding = true;
            $peIvExpanding = true;
        }

        $signal = 'NO_TRADE';
        $reason = '';

        // ── BUY CALL: PE expensive, CE cheap, PE IV expanding, bullish ────
        if (
            $peDiffPct  > 1.5  &&
            $ceDiffPct  < 0.5  &&
            $imbalance  > 1    &&
            $spot      >= $strike &&
            $peIvExpanding
        ) {
            $signal = 'BUY_CALL';
            $reason = $scannerMode
                ? 'Put expensive + bullish pressure'
                : 'Put expensive + bullish pressure + PE IV expanding';

        // ── BUY PUT: CE expensive, PE cheap, CE IV expanding, bearish ─────
        } elseif (
            $ceDiffPct  > 1.5  &&
            $peDiffPct  < 0.5  &&
            $imbalance  < -1   &&
            $spot      <= $strike &&
            $ceIvExpanding
        ) {
            $signal = 'BUY_PUT';
            $reason = $scannerMode
                ? 'Call expensive + bearish pressure'
                : 'Call expensive + bearish pressure + CE IV expanding';

        // ── ATM Gamma breakout: near-ATM + large imbalance ────────────────
        } elseif (
            $nearATM &&
            abs($imbalance) > 2 &&
            ($imbalance > 0 ? $peIvExpanding : $ceIvExpanding)
        ) {
            if ($imbalance > 0) {
                $signal = 'BUY_CALL';
                $reason = 'ATM gamma breakout — bullish';
            } else {
                $signal = 'BUY_PUT';
                $reason = 'ATM gamma breakout — bearish';
            }

        } else {
            $reason = $this->diagnoseNoTrade(
                $ceDiffPct, $peDiffPct, $imbalance,
                $spot, $strike, $nearATM,
                $ceIvExpanding, $peIvExpanding, $scannerMode
            );
        }

        // FIX 2: single clean array, all snake_case, no camelCase duplicates
        return [
            'signal'      => $signal,
            'reason'      => $reason,
            'ce_diff_pct' => $ceDiffPct,
            'pe_diff_pct' => $peDiffPct,
            'imbalance'   => $imbalance,
        ];
    }

    /**
     * Single-sentence explanation of the first failing gate.
     */
    private function diagnoseNoTrade(
        float $ceDiffPct,
        float $peDiffPct,
        float $imbalance,
        float $spot,
        float $strike,
        bool  $nearATM,
        bool  $ceIvExpanding,
        bool  $peIvExpanding,
        bool  $scannerMode
    ): string {
        if (abs($ceDiffPct) <= 1.5 && abs($peDiffPct) <= 1.5) {
            $max = max(abs($ceDiffPct), abs($peDiffPct));
            return 'Mispricing too small (' . $max . '% < 1.5% threshold)';
        }
        if (abs($imbalance) <= 1) {
            return 'Imbalance too small (' . $imbalance . ', need > ±1)';
        }
        if ($peDiffPct > 1.5 && $spot < $strike) {
            return 'PE expensive but spot below strike — directional conflict';
        }
        if ($ceDiffPct > 1.5 && $spot > $strike) {
            return 'CE expensive but spot above strike — directional conflict';
        }
        if (!$scannerMode) {
            if ($peDiffPct > 1.5 && !$peIvExpanding) return 'PE IV not expanding above PE IV-11MA';
            if ($ceDiffPct > 1.5 && !$ceIvExpanding) return 'CE IV not expanding above CE IV-11MA';
        }
        return 'Conditions not met';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  IV 11-PERIOD MOVING AVERAGE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Rolling 11-period SMA on independent CE and PE IV series.
     *
     * - ceBuffer / peBuffer accumulate raw decimal IV values across ALL rows
     *   (including prev-day seed rows) in chronological order.
     * - Rows where ce_iv_raw / pe_iv_raw is null/zero are skipped silently
     *   (buffer not pushed, but row still gets null MA — does not break window).
     * - iv_11ma / iv_11ma_raw kept for backward-compatible display column;
     *   CE MA is used as the canonical single-leg MA value there.
     */
    private function addIvMovingAverage(array $rows, int $period = 11): array
    {
        $ceBuffer = [];
        $peBuffer = [];

        foreach ($rows as $i => $row) {

            // — CE leg ───────────────────────────────────────────────────
            if (!empty($row['ce_iv_raw'])) {
                $ceBuffer[] = $row['ce_iv_raw'];
            }
            if (count($ceBuffer) >= $period) {
                $ceSlice                     = array_slice($ceBuffer, -$period);
                $ceMaRaw                     = array_sum($ceSlice) / $period;
                $rows[$i]['ce_iv_11ma']     = round($ceMaRaw * 100, 2);
                $rows[$i]['ce_iv_11ma_raw'] = round($ceMaRaw, 6);
            } else {
                $rows[$i]['ce_iv_11ma']     = null;
                $rows[$i]['ce_iv_11ma_raw'] = null;
            }

            // — PE leg ───────────────────────────────────────────────────
            if (!empty($row['pe_iv_raw'])) {
                $peBuffer[] = $row['pe_iv_raw'];
            }
            if (count($peBuffer) >= $period) {
                $peSlice                     = array_slice($peBuffer, -$period);
                $peMaRaw                     = array_sum($peSlice) / $period;
                $rows[$i]['pe_iv_11ma']     = round($peMaRaw * 100, 2);
                $rows[$i]['pe_iv_11ma_raw'] = round($peMaRaw, 6);
            } else {
                $rows[$i]['pe_iv_11ma']     = null;
                $rows[$i]['pe_iv_11ma_raw'] = null;
            }

            // Backward-compat combined display column (CE MA preferred)
            $rows[$i]['iv_11ma']     = $rows[$i]['ce_iv_11ma']     ?? $rows[$i]['pe_iv_11ma']     ?? null;
            $rows[$i]['iv_11ma_raw'] = $rows[$i]['ce_iv_11ma_raw'] ?? $rows[$i]['pe_iv_11ma_raw'] ?? null;
        }

        return $rows;
    }

    /**
     * Re-run calcSignal() for every row once MA values are populated.
     *
     * Scanner mode (all-symbols): IV gate bypassed.
     * Single-symbol mode       : full CE/PE IV > MA gate enforced.
     */
    private function recalcSignalsWithMa(array $rows, bool $scannerMode = false): array
    {
        foreach ($rows as $i => $row) {

            $sig = $this->calcSignal(
                spot       : (float) ($row['spot']   ?? 0),
                strike     : (float) ($row['strike'] ?? 0),
                ceLtp      : (float) ($row['ce_ltp'] ?? 0),
                ceFair     : $row['ce_fair'] ?? null,
                peLtp      : (float) ($row['pe_ltp'] ?? 0),
                peFair     : $row['pe_fair'] ?? null,
                ceIv       : $row['ce_iv_raw']      ?? null,
                ceIvMa     : $scannerMode ? null : ($row['ce_iv_11ma_raw'] ?? null),
                peIv       : $row['pe_iv_raw']      ?? null,
                peIvMa     : $scannerMode ? null : ($row['pe_iv_11ma_raw'] ?? null),
                scannerMode: $scannerMode
            );

            $rows[$i]['signal']             = $sig['signal'];
            $rows[$i]['signal_reason']      = $sig['reason'];
            $rows[$i]['signal_imbalance']   = $sig['imbalance'];
            $rows[$i]['signal_ce_diff_pct'] = $sig['ce_diff_pct'];
            $rows[$i]['signal_pe_diff_pct'] = $sig['pe_diff_pct'];

            // IV regime — CE IV vs CE MA is the canonical indicator
            $ceIv   = $row['ce_iv_pct']  ?? null;
            $ceIvMa = $row['ce_iv_11ma'] ?? null;
            $rows[$i]['iv_regime'] = ($ceIv === null || $ceIvMa === null)
                ? 'WARMING'
                : ($ceIv > $ceIvMa ? 'ABOVE' : ($ceIv < $ceIvMa ? 'BELOW' : 'AT'));
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getOptionCandle(
        string  $symbol,
        string  $date,
        ?string $candleTime,
        string  $type,
        float   $strike,
        ?string $expiry
    ) {
        $query = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('strike', $strike)
            ->where('is_missing', 0);

        if ($expiry) {
            $query->whereDate('expiry_date', $expiry);
        }

        if ($candleTime) {
            $exact = (clone $query)
                ->whereRaw("TIME(interval_time) = ?", [$candleTime])
                ->first();
            if ($exact) return $exact;

            return $query
                ->whereRaw("TIME(interval_time) <= ?", [$candleTime])
                ->orderByDesc('interval_time')
                ->first();
        }

        return $query->orderByDesc('interval_time')->first();
    }

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        $row = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->selectRaw('DATE(expiry_date) AS expiry_day')
            ->orderByRaw('expiry_date ASC')
            ->first();

        return $row ? $row->expiry_day : null;
    }

    private function getStepSize(string $symbol, string $date): float
    {
        $strikes = OptionOhlcData::where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('strike')
            ->distinct()
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($v) => (float) $v)
            ->values()
            ->toArray();

        if (count($strikes) >= 2) {
            $gaps = [];
            for ($i = 1; $i < min(count($strikes), 6); $i++) {
                $gap = $strikes[$i] - $strikes[$i - 1];
                if ($gap > 0) $gaps[] = $gap;
            }
            if (!empty($gaps)) {
                $counts = array_count_values(array_map('intval', $gaps));
                arsort($counts);
                return (float) array_key_first($counts);
            }
        }

        return (float) (self::STEP_DEFAULTS[strtoupper($symbol)] ?? 50);
    }

    private function estimateSpotFromOptions(string $symbol, string $date, ?string $candleTime): float
    {
        $query = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0);

        if ($candleTime) {
            $query->whereRaw("TIME(interval_time) <= ?", [$candleTime]);
        }

        $candles  = $query->orderByDesc('interval_time')->get(['instrument_type', 'strike', 'close']);
        $byStrike = $candles->groupBy('strike');
        $bestSpot = 0.0;
        $bestDiff = PHP_FLOAT_MAX;

        foreach ($byStrike as $strike => $group) {
            $ce = $group->where('instrument_type', 'CE')->first();
            $pe = $group->where('instrument_type', 'PE')->first();
            if ($ce && $pe) {
                $diff = abs((float)$ce->close - (float)$pe->close);
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestSpot = (float) $strike;
                }
            }
        }

        return $bestSpot;
    }

    private function emptyValuation(): array
    {
        return [
            'fair_price_bs'            => null,
            'valuation_status'         => 'N/A',
            'price_difference'         => null,
            'price_difference_percent' => null,
            'recommendation'           => null,
            'iv_used'                  => null,
            'iv_source'                => null,
            'expected_move'            => null,
        ];
    }

    private function buildSummary(array $rows): array
    {
        $collect = collect($rows);
        return [
            'ceOver'  => $collect->where('ce_status', 'OVERPRICED')->count(),
            'ceUnder' => $collect->where('ce_status', 'UNDERPRICED')->count(),
            'peOver'  => $collect->where('pe_status', 'OVERPRICED')->count(),
            'peUnder' => $collect->where('pe_status', 'UNDERPRICED')->count(),
            'ivAbove' => $collect->filter(fn($r) =>
                            isset($r['ce_iv_pct'], $r['ce_iv_11ma']) &&
                            $r['ce_iv_11ma'] !== null &&
                            $r['ce_iv_pct'] > $r['ce_iv_11ma']
                         )->count(),
            'ivBelow' => $collect->filter(fn($r) =>
                            isset($r['ce_iv_pct'], $r['ce_iv_11ma']) &&
                            $r['ce_iv_11ma'] !== null &&
                            $r['ce_iv_pct'] < $r['ce_iv_11ma']
                         )->count(),
            'buyCall' => $collect->where('signal', 'BUY_CALL')->count(),
            'buyPut'  => $collect->where('signal', 'BUY_PUT')->count(),
            'noTrade' => $collect->where('signal', 'NO_TRADE')->count(),
        ];
    }

    private function sortRows(array $rows, string $sortBy): array
    {
        switch ($sortBy) {
            case 'ce_overpriced':
                usort($rows, fn($a, $b) => ($b['ce_diff_pct'] ?? -999) <=> ($a['ce_diff_pct'] ?? -999));
                break;

            case 'ce_underpriced':
                usort($rows, fn($a, $b) => ($a['ce_diff_pct'] ?? 999) <=> ($b['ce_diff_pct'] ?? 999));
                break;

            case 'pe_overpriced':
                usort($rows, fn($a, $b) => ($b['pe_diff_pct'] ?? -999) <=> ($a['pe_diff_pct'] ?? -999));
                break;

            case 'pe_underpriced':
                usort($rows, fn($a, $b) => ($a['pe_diff_pct'] ?? 999) <=> ($b['pe_diff_pct'] ?? 999));
                break;

            case 'mispricing':
                usort($rows, function ($a, $b) {
                    $aMax = max(abs($a['ce_diff_pct'] ?? 0), abs($a['pe_diff_pct'] ?? 0));
                    $bMax = max(abs($b['ce_diff_pct'] ?? 0), abs($b['pe_diff_pct'] ?? 0));
                    return $bMax <=> $aMax;
                });
                break;

            case 'iv_above':
                $order = ['ABOVE' => 0, 'AT' => 1, 'BELOW' => 2, 'WARMING' => 3];
                usort($rows, fn($a, $b) =>
                    ($order[$a['iv_regime'] ?? 'WARMING'] ?? 9) <=>
                    ($order[$b['iv_regime'] ?? 'WARMING'] ?? 9)
                );
                break;

            case 'iv_below':
                $order = ['BELOW' => 0, 'AT' => 1, 'ABOVE' => 2, 'WARMING' => 3];
                usort($rows, fn($a, $b) =>
                    ($order[$a['iv_regime'] ?? 'WARMING'] ?? 9) <=>
                    ($order[$b['iv_regime'] ?? 'WARMING'] ?? 9)
                );
                break;

            // FIX 3: 'signal' case was dead code — the usort block lived after
            //         a stray break at the end of 'iv_below' and never executed.
            //         Now correctly placed as its own case with its own break.
            case 'signal':
                $order = ['BUY_CALL' => 0, 'BUY_PUT' => 1, 'NO_TRADE' => 2];
                usort($rows, fn($a, $b) =>
                    ($order[$a['signal'] ?? 'NO_TRADE'] ?? 9) <=>
                    ($order[$b['signal'] ?? 'NO_TRADE'] ?? 9)
                );
                break;

            default:
                usort($rows, fn($a, $b) => strcmp($a['symbol'] ?? '', $b['symbol'] ?? ''));
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SYMBOLS LIST
    // ─────────────────────────────────────────────────────────────────────────

    public function getSymbols()
    {
        $latest = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->max(DB::raw('DATE(trade_date)'));

        $symbols = OptionOhlcData::whereDate('trade_date', $latest)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('base_symbol')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols, 'latest_date' => $latest]);
    }
}