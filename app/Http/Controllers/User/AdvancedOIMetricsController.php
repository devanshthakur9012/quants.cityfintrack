<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Advanced OI Metrics — STANDALONE, READ-ONLY analysis page.
 *
 * T = today 14:45 | T-1 = previous trading day 15:00 (same convention as
 * OIFlowSentimentController). Reuses cp_option_ohlc_15min exactly as-is.
 * Does NOT touch collectors, does NOT touch OIFlowSentimentController,
 * does NOT change BUY CE / BUY PE / WAIT logic. Zero schema changes.
 *
 * ── THIS REVISION ────────────────────────────────────────────────────
 * - Added ONE "OI Signal" column using the SAME sentiment logic as
 *   OIFlowSentimentController::calcOISignal() (duplicated here, not
 *   imported, so OIFlowSentimentController stays untouched — but the
 *   math is byte-for-byte identical: CE↑+PE↓ → BULLISH, CE↓+PE↑ → BEARISH,
 *   etc.), computed from total CE/PE OI at the same 14:45 / 15:00 anchor.
 * - Each of the 4 calculable metrics (Decay V, Efficiency, NTM Bias,
 *   Deep OTM) now exposes ONE signal word (BULLISH / BEARISH / NEUTRAL /
 *   INSUFFICIENT_DATA) derived from its own ce_status/pe_status (or
 *   bullish_status/bearish_status for NTM), instead of the UI showing
 *   raw CE/PE numbers + two badges.
 * - bullish_score / bearish_score / overall "signal" (the 8-point
 *   scoring system) REMOVED ENTIRELY, per request.
 * - Added history mode: analyze() now accepts from_date + to_date
 *   (alongside symbols[]) to return one row per trading day for a
 *   given symbol, using the exact same per-day calculation.
 *
 * ── FIX (this revision) ──
 * PHP method names are case-insensitive, so a private helper named
 * calcOiSignal() collided with the ported calcOISignal() and PHP threw
 * "Cannot redeclare". The wrapper is now named buildOiSignalFromTotals()
 * — no behavior change, purely a rename to avoid the collision.
 *
 * ── STRIKE KEY FIX (carried over, unchanged) ──
 * All strike-based lookups route through strikeKey() — a DB strike like
 * "2640.0000" and a ladder-derived float 2640.0 must resolve to the same
 * map key, or Decay/NTM/Deep-OTM silently return INSUFFICIENT_DATA.
 *
 * ── STRUCTURAL DATA LIMITATIONS (confirmed from CpCollectOption.php) ──
 * 1) STRIKE ROLL-OVER VELOCITY — permanently INSUFFICIENT_DATA. Only one
 *    expiry's option chain is collected per symbol per trade_date.
 * 2) INTRADAY OI MOMENTUM DELTA — permanently INSUFFICIENT_DATA. No
 *    5-minute interval data exists anywhere in the schema.
 * Both are still computed internally (for completeness / future use) but
 * are no longer rendered as UI columns since they can never resolve.
 */
class AdvancedOIMetricsController extends Controller
{
    private const TF             = '15min';
    private const ANALYSIS_TIME  = '14:45:00'; // today snapshot
    private const PREV_DAY_TIME  = '15:00:00'; // previous trading day anchor
    private const OPT_TABLE      = 'cp_option_ohlc_15min';

    /** Strike-ladder offsets used for both NTM Bias and Decay Velocity baskets. */
    private const BASKET_OFFSETS = [-1, 0, 1];

    private const DEEP_OTM_OFFSET = 4;

    /** Set to true to include the debug block (ATM/ladder diagnostics) per row. */
    private const DEBUG_MODE = false;

    public function index()
    {
        $pageTitle = 'Advanced OI Metrics';
        return view(activeTemplate() . 'user.advanced-oi-metrics.index', compact('pageTitle'));
    }

    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            $today  = Carbon::today()->toDateString();

            if (!$config) {
                return response()->json(['success' => false, 'last_date' => $today, 'is_today' => true]);
            }

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->max('trade_date');

            if (!$lastDate) {
                $lastDate = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $config->id)
                    ->where('is_missing', false)
                    ->max('trade_date');
            }

            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

            return response()->json(['success' => true, 'last_date' => $lastDate, 'is_today' => $lastDate === $today]);

        } catch (\Exception $e) {
            Log::error('AdvancedOIMetricsController lastDate: ' . $e->getMessage());
            return response()->json(['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true]);
        }
    }

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json([
                'success'   => true,
                'symbols'   => [],
                'no_config' => true,
                'message'   => 'No active analysis config found.',
            ]);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    /**
     * Two modes, both returning the same row shape:
     *  - SINGLE-DATE: pass `date` (+ optional symbols[]) → all/filtered
     *    symbols, one day.
     *  - HISTORY: pass `from_date` + `to_date` (+ symbols[], normally one
     *    symbol) → every trading day in range for that symbol.
     */
    public function analyze(Request $request): JsonResponse
    {
        $date     = $request->get('date');
        $fromDate = $request->get('from_date');
        $toDate   = $request->get('to_date');
        $isRange  = !empty($fromDate) && !empty($toDate);

        if (!$isRange && !$date) {
            return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
        }

        $symbolReq = array_filter((array) $request->get('symbols', []));

        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active Analysis Config found. Go to Admin → Analysis Config.',
                    'data'      => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            if (empty($symbols)) {
                return response()->json(['success' => false, 'message' => 'Symbol not in active config.', 'available_symbols' => $configSymbols, 'data' => []]);
            }

            if ($isRange) {
                return $this->analyzeHistory($config, $configSymbols, $symbols, $fromDate, $toDate);
            }

            return $this->analyzeSingleDate($config, $configSymbols, $symbols, $date);

        } catch (\Exception $e) {
            Log::error('AdvancedOIMetricsController analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    private function analyzeSingleDate(object $config, array $configSymbols, array $symbols, string $date): JsonResponse
    {
        $rows = [];
        foreach ($symbols as $symbol) {
            $result = $this->runAnalysisForSymbol($config, $symbol, $date);
            $rows[] = array_merge(['symbol' => $symbol, 'date' => $date], $result);
        }

        usort($rows, fn ($a, $b) => strcmp($a['symbol'], $b['symbol']));

        return response()->json([
            'success'           => true,
            'mode'              => 'single',
            'data'              => $rows,
            'date'              => $date,
            'is_today'          => $date === Carbon::today()->toDateString(),
            'available_symbols' => $configSymbols,
            'total_records'     => count($rows),
            'message'           => count($rows) . ' symbol(s) analyzed for ' . $date,
        ]);
    }

    private function analyzeHistory(object $config, array $configSymbols, array $symbols, string $fromDate, string $toDate): JsonResponse
    {
        $tradeDates = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $config->id)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$fromDate, $toDate])
            ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
            ->select(DB::raw('DATE(trade_date) as d'))
            ->distinct()->orderBy('d')->pluck('d')->toArray();

        $rows = [];
        foreach ($tradeDates as $d) {
            foreach ($symbols as $symbol) {
                $result = $this->runAnalysisForSymbol($config, $symbol, $d);
                if (!empty($result['no_data'])) continue; // skip days with no snapshot — same convention as OI Flow Sentiment
                $rows[] = array_merge(['symbol' => $symbol, 'date' => $d], $result);
            }
        }

        usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return response()->json([
            'success'           => true,
            'mode'              => 'history',
            'data'              => $rows,
            'from_date'         => $fromDate,
            'to_date'           => $toDate,
            'available_symbols' => $configSymbols,
            'total_records'     => count($rows),
            'message'           => count($rows) . ' record(s) found between ' . $fromDate . ' and ' . $toDate,
        ]);
    }

    // ═════════════════════════ SHARED PER-SYMBOL/DAY COMPUTATION ═════════════════════════

    private function runAnalysisForSymbol(object $config, string $symbol, string $date): array
    {
        try {
            $prevDate = $this->getPreviousTradingDate($date);

            $liveRows = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $date)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['instrument_type', 'strike', 'atm_strike', 'expiry_date', 'oi', 'volume'])
                ->get();

            $anchorRows = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $prevDate)
                ->whereRaw('TIME(interval_time) = ?', [self::PREV_DAY_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['instrument_type', 'strike', 'oi'])
                ->get();

            if ($liveRows->isEmpty()) {
                return [
                    'success'     => true,
                    'no_data'     => true,
                    'message'     => "No data found for {$symbol} on {$date} " . substr(self::ANALYSIS_TIME, 0, 5) . '.',
                    'advanced_oi' => $this->emptyAdvancedOi(),
                ];
            }

            $liveOi = ['CE' => [], 'PE' => []];
            $liveVol = ['CE' => [], 'PE' => []];
            $atmStrike = null;
            $expiryUsed = null;

            foreach ($liveRows as $r) {
                $key = $this->strikeKey($r->strike);
                $liveOi[$r->instrument_type][$key] = (int) $r->oi;
                $liveVol[$r->instrument_type][$key] = (int) $r->volume;
                if ($atmStrike === null) $atmStrike = (float) $r->atm_strike;
                if ($expiryUsed === null) $expiryUsed = $r->expiry_date;
            }

            $anchorOi = ['CE' => [], 'PE' => []];
            foreach ($anchorRows as $r) {
                $key = $this->strikeKey($r->strike);
                $anchorOi[$r->instrument_type][$key] = (int) $r->oi;
            }

            $ladder = collect(array_keys($liveOi['CE']))
                ->map(fn ($s) => (float) $s)
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            $atmIndex = $this->findAtmIndex($ladder, $atmStrike);

            $decay      = $this->calcDecayVelocity($ladder, $atmIndex, $liveOi, $anchorOi);
            $efficiency = $this->calcEfficiency($liveOi, $anchorOi, $liveVol);
            $ntmBias    = $this->calcNtmBias($ladder, $atmIndex, $liveOi);
            $deepOtm    = $this->calcDeepOtmIndex($ladder, $atmIndex, $liveOi, $anchorOi);
            $rollover   = $this->calcRolloverVelocity();
            $momentum   = $this->calcIntradayMomentum();

            $oiSignal = $this->buildOiSignalFromTotals($liveOi, $anchorOi);

            $advanced = [
                'meta' => [
                    'symbol'        => $symbol,
                    'date'          => $date,
                    'time'          => substr(self::ANALYSIS_TIME, 0, 5),
                    'anchor_date'   => $prevDate,
                    'anchor_time'   => substr(self::PREV_DAY_TIME, 0, 5),
                    'expiry_used'   => $expiryUsed,
                    'atm_strike'    => $atmStrike,
                    'strike_ladder' => $ladder,
                ],
                'decay_velocity'       => $decay,
                'oi_volume_efficiency' => $efficiency,
                'ntm_bias'             => $ntmBias,
                'rollover_velocity'    => $rollover,   // always INSUFFICIENT_DATA — see class docblock
                'deep_otm_inflection'  => $deepOtm,
                'intraday_momentum'    => $momentum,   // always INSUFFICIENT_DATA — see class docblock

                // ── One signal word per metric (this revision) ──
                'decay_signal'      => $this->metricSignal($decay['ce_status'], $decay['pe_status']),
                'efficiency_signal' => $this->metricSignal($efficiency['ce_status'], $efficiency['pe_status']),
                'ntm_signal'        => $this->metricSignal($ntmBias['bullish_status'], $ntmBias['bearish_status']),
                'deep_otm_signal'   => $this->metricSignal($deepOtm['ce_status'], $deepOtm['pe_status']),

                // ── OI Signal — same logic as OIFlowSentimentController ──
                'oi_signal' => $oiSignal,
            ];

            if (self::DEBUG_MODE) {
                $atmVal = $this->strikeAtOffset($ladder, $atmIndex, 0);
                $advanced['debug'] = [
                    'atm'                    => $atmVal,
                    'atm_index'              => $atmIndex,
                    'atm_minus_1'            => $this->strikeAtOffset($ladder, $atmIndex, -1),
                    'atm_plus_1'             => $this->strikeAtOffset($ladder, $atmIndex, 1),
                    'atm_plus_4'             => $this->strikeAtOffset($ladder, $atmIndex, self::DEEP_OTM_OFFSET),
                    'live_ce_strike_count'   => count($liveOi['CE']),
                    'live_pe_strike_count'   => count($liveOi['PE']),
                    'anchor_ce_strike_count' => count($anchorOi['CE']),
                    'anchor_pe_strike_count' => count($anchorOi['PE']),
                ];
            }

            return ['success' => true, 'no_data' => false, 'advanced_oi' => $advanced];

        } catch (\Exception $e) {
            Log::error('AdvancedOIMetricsController runAnalysisForSymbol (' . $symbol . '): ' . $e->getMessage());
            return [
                'success'     => false,
                'message'     => $e->getMessage(),
                'advanced_oi' => $this->emptyAdvancedOi(),
            ];
        }
    }

    // ═════════════════════════ METRIC 1 — DECAY VELOCITY ═════════════════════════

    private function calcDecayVelocity(array $ladder, ?int $atmIndex, array $liveOi, array $anchorOi): array
    {
        $ce = $this->basketVelocity($ladder, $atmIndex, $liveOi['CE'], $anchorOi['CE']);
        $pe = $this->basketVelocity($ladder, $atmIndex, $liveOi['PE'], $anchorOi['PE']);

        return [
            'ce'        => $ce,
            'pe'        => $pe,
            'ce_status' => $ce === null ? 'INSUFFICIENT_DATA' : ($ce <= 0.70 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
            'pe_status' => $pe === null ? 'INSUFFICIENT_DATA' : ($pe <= 0.70 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
        ];
    }

    private function basketVelocity(array $ladder, ?int $atmIndex, array $liveMap, array $anchorMap): ?float
    {
        if ($atmIndex === null) return null;

        $liveSum = 0; $anchorSum = 0;

        foreach (self::BASKET_OFFSETS as $offset) {
            $strike = $this->strikeAtOffset($ladder, $atmIndex, $offset);
            if ($strike === null) return null;

            $key = $this->strikeKey($strike);
            if (!array_key_exists($key, $liveMap) || !array_key_exists($key, $anchorMap)) return null;

            $liveSum   += $liveMap[$key];
            $anchorSum += $anchorMap[$key];
        }

        if ($anchorSum <= 0) return null;

        return round($liveSum / $anchorSum, 4);
    }

    // ═════════════════════════ METRIC 2 — OI-TO-VOLUME EFFICIENCY ═════════════════════════

    private function calcEfficiency(array $liveOi, array $anchorOi, array $liveVol): array
    {
        $ce = $this->efficiencyForSide($liveOi['CE'], $anchorOi['CE'], $liveVol['CE']);
        $pe = $this->efficiencyForSide($liveOi['PE'], $anchorOi['PE'], $liveVol['PE']);

        return [
            'ce'        => $ce,
            'pe'        => $pe,
            'ce_status' => $ce === null ? 'INSUFFICIENT_DATA' : ($ce >= 0.40 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
            'pe_status' => $pe === null ? 'INSUFFICIENT_DATA' : ($pe >= 0.40 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
        ];
    }

    private function efficiencyForSide(array $liveMap, array $anchorMap, array $volMap): ?float
    {
        $liveTotal   = array_sum($liveMap);
        $anchorTotal = array_sum($anchorMap);
        $volTotal    = array_sum($volMap);

        if ($volTotal <= 0) return null;
        if (empty($anchorMap)) return null;

        return round(abs($liveTotal - $anchorTotal) / $volTotal, 4);
    }

    // ═════════════════════════ METRIC 3 — NTM BIAS RATIO ═════════════════════════

    private function calcNtmBias(array $ladder, ?int $atmIndex, array $liveOi): array
    {
        if ($atmIndex === null) {
            return [
                'ratio' => null, 'pe_sum' => null, 'ce_sum' => null,
                'bullish_status' => 'INSUFFICIENT_DATA', 'bearish_status' => 'INSUFFICIENT_DATA',
            ];
        }

        $peSum = 0; $ceSum = 0; $complete = true;

        foreach (self::BASKET_OFFSETS as $offset) {
            $strike = $this->strikeAtOffset($ladder, $atmIndex, $offset);
            if ($strike === null) { $complete = false; break; }
            $key = $this->strikeKey($strike);
            if (!array_key_exists($key, $liveOi['PE']) || !array_key_exists($key, $liveOi['CE'])) { $complete = false; break; }
            $peSum += $liveOi['PE'][$key];
            $ceSum += $liveOi['CE'][$key];
        }

        if (!$complete || $ceSum <= 0) {
            return [
                'ratio' => null, 'pe_sum' => $complete ? $peSum : null, 'ce_sum' => $complete ? $ceSum : null,
                'bullish_status' => 'INSUFFICIENT_DATA', 'bearish_status' => 'INSUFFICIENT_DATA',
            ];
        }

        $ratio = round($peSum / $ceSum, 4);

        return [
            'ratio'          => $ratio,
            'pe_sum'         => $peSum,
            'ce_sum'         => $ceSum,
            'bullish_status' => $ratio >= 1.25 ? 'TRIGGERED' : 'NOT_TRIGGERED',
            'bearish_status' => $ratio <= 0.75 ? 'TRIGGERED' : 'NOT_TRIGGERED',
        ];
    }

    // ═════════════════════════ METRIC 4 — STRIKE ROLL-OVER VELOCITY ═════════════════════════
    // Permanently INSUFFICIENT_DATA — see class docblock. Not rendered in UI anymore.

    private function calcRolloverVelocity(): array
    {
        return [
            'ce' => null, 'pe' => null,
            'ce_status' => 'INSUFFICIENT_DATA', 'pe_status' => 'INSUFFICIENT_DATA',
            'reason' => 'Only a single expiry is collected per symbol per trade_date. Next-month expiry OI is not stored alongside current-month data.',
        ];
    }

    // ═════════════════════════ METRIC 5 — DEEP OTM INFLECTION INDEX ═════════════════════════

    private function calcDeepOtmIndex(array $ladder, ?int $atmIndex, array $liveOi, array $anchorOi): array
    {
        $ce = $this->deepOtmForSide($ladder, $atmIndex, $liveOi['CE'], $anchorOi['CE']);
        $pe = $this->deepOtmForSide($ladder, $atmIndex, $liveOi['PE'], $anchorOi['PE']);

        return [
            'ce'        => $ce,
            'pe'        => $pe,
            'ce_status' => $ce === null ? 'INSUFFICIENT_DATA' : ($ce >= 3.0 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
            'pe_status' => $pe === null ? 'INSUFFICIENT_DATA' : ($pe >= 3.0 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
        ];
    }

    private function deepOtmForSide(array $ladder, ?int $atmIndex, array $liveMap, array $anchorMap): ?float
    {
        if ($atmIndex === null) return null;

        $atmStrike = $this->strikeAtOffset($ladder, $atmIndex, 0);
        $otmStrike = $this->strikeAtOffset($ladder, $atmIndex, self::DEEP_OTM_OFFSET);
        if ($atmStrike === null || $otmStrike === null) return null;

        $atmKey = $this->strikeKey($atmStrike);
        $otmKey = $this->strikeKey($otmStrike);

        if (!array_key_exists($atmKey, $liveMap) || !array_key_exists($atmKey, $anchorMap)) return null;
        if (!array_key_exists($otmKey, $liveMap) || !array_key_exists($otmKey, $anchorMap)) return null;

        $atmChange = $liveMap[$atmKey] - $anchorMap[$atmKey];
        $otmChange = $liveMap[$otmKey] - $anchorMap[$otmKey];

        if ($atmChange === 0) return null;

        return round($otmChange / $atmChange, 4);
    }

    // ═════════════════════════ METRIC 6 — INTRADAY OI MOMENTUM DELTA ═════════════════════════
    // Permanently INSUFFICIENT_DATA — see class docblock. Not rendered in UI anymore.

    private function calcIntradayMomentum(): array
    {
        return [
            'ce' => null, 'pe' => null,
            'ce_status' => 'INSUFFICIENT_DATA', 'pe_status' => 'INSUFFICIENT_DATA',
            'reason' => 'No 5-minute OI observations exist in this schema.',
        ];
    }

    // ═════════════════════════ OI SIGNAL — same logic as OIFlowSentimentController ═════════════════════════
    // Duplicated intentionally (not shared/imported) so OIFlowSentimentController
    // is never touched. Formula and thresholds are identical.

    /**
     * Wraps totals from the live/anchor maps into the same CE%/PE% inputs
     * that OIFlowSentimentController uses, then hands off to the ported
     * calcOISignal() below. Renamed (was calcOiSignal) to avoid colliding
     * with calcOISignal() — PHP method names are case-insensitive.
     */
    private function buildOiSignalFromTotals(array $liveOi, array $anchorOi): array
    {
        $ceToday = array_sum($liveOi['CE']);
        $peToday = array_sum($liveOi['PE']);
        $cePrev  = array_sum($anchorOi['CE']);
        $pePrev  = array_sum($anchorOi['PE']);

        $cePct = $cePrev > 0 ? round((($ceToday - $cePrev) / $cePrev) * 100, 2) : 0;
        $pePct = $pePrev > 0 ? round((($peToday - $pePrev) / $pePrev) * 100, 2) : 0;

        $result = $this->calcOISignal($cePct, $pePct);

        return [
            'sentiment'    => $result['sentiment'],
            'condition'    => $result['condition'],
            'reason'       => $result['reason'],
            'ce_oi_pct'    => $cePct,
            'pe_oi_pct'    => $pePct,
            'trade_action' => match ($result['sentiment']) {
                'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
            },
        ];
    }

    /**
     * Ported verbatim from OIFlowSentimentController::calcOISignal().
     * Do not diverge — this must stay identical to the live sentiment page.
     */
    private function calcOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp && $peDown)
            return ['sentiment' => 'BULLISH', 'condition' => 'CE ↑ + PE ↓', 'reason' => 'Call buildup + Put unwinding → Support forming'];
        if ($ceDown && $peUp)
            return ['sentiment' => 'BEARISH', 'condition' => 'CE ↓ + PE ↑', 'reason' => 'Call unwinding + Put buildup → Resistance forming'];
        if ($ceUp && $peUp) {
            if ($cePct > $pePct)
                return ['sentiment' => 'BULLISH', 'condition' => 'Both ↑ (CE > PE)', 'reason' => "Call buildup stronger (+{$cePct}% vs +{$pePct}%) → Bullish"];
            return ['sentiment' => 'BEARISH', 'condition' => 'Both ↑ (PE > CE)', 'reason' => "Put buildup stronger (+{$pePct}% vs +{$cePct}%) → Bearish"];
        }
        if ($ceDown && $peDown) {
            if ($cePct < $pePct)
                return ['sentiment' => 'BEARISH', 'condition' => 'Both ↓ (CE < PE)', 'reason' => "Call unwinding larger ({$cePct}% vs {$pePct}%) → Long covering"];
            return ['sentiment' => 'BULLISH', 'condition' => 'Both ↓ (PE < CE)', 'reason' => "Put unwinding larger ({$pePct}% vs {$cePct}%) → Short covering"];
        }
        return ['sentiment' => 'NEUTRAL', 'condition' => 'Flat', 'reason' => 'No clear OI direction'];
    }

    // ═════════════════════════ PER-METRIC SIGNAL ═════════════════════════

    /**
     * Collapses a metric's own bull/bear TRIGGERED status pair into one
     * signal word. $bullStatus/$bearStatus are the metric's own ce_status/
     * pe_status (or bullish_status/bearish_status for NTM Bias) — TRIGGERED
     * on the bull side and not on the bear side => BULLISH, and vice versa.
     * Both TRIGGERED (conflicting) or neither TRIGGERED => NEUTRAL.
     */
    private function metricSignal(?string $bullStatus, ?string $bearStatus): string
    {
        if ($bullStatus === 'INSUFFICIENT_DATA' && $bearStatus === 'INSUFFICIENT_DATA') {
            return 'INSUFFICIENT_DATA';
        }

        $bull = $bullStatus === 'TRIGGERED';
        $bear = $bearStatus === 'TRIGGERED';

        if ($bull && !$bear) return 'BULLISH';
        if ($bear && !$bull) return 'BEARISH';
        return 'NEUTRAL';
    }

    private function emptyAdvancedOi(): array
    {
        $ins = ['ce' => null, 'pe' => null, 'ce_status' => 'INSUFFICIENT_DATA', 'pe_status' => 'INSUFFICIENT_DATA'];
        return [
            'decay_velocity'       => $ins,
            'oi_volume_efficiency' => $ins,
            'ntm_bias'             => ['ratio' => null, 'pe_sum' => null, 'ce_sum' => null, 'bullish_status' => 'INSUFFICIENT_DATA', 'bearish_status' => 'INSUFFICIENT_DATA'],
            'rollover_velocity'    => $this->calcRolloverVelocity(),
            'deep_otm_inflection'  => $ins,
            'intraday_momentum'    => $this->calcIntradayMomentum(),
            'decay_signal'         => 'INSUFFICIENT_DATA',
            'efficiency_signal'    => 'INSUFFICIENT_DATA',
            'ntm_signal'           => 'INSUFFICIENT_DATA',
            'deep_otm_signal'      => 'INSUFFICIENT_DATA',
            'oi_signal'            => ['sentiment' => 'NEUTRAL', 'condition' => 'No Data', 'reason' => 'No data available', 'ce_oi_pct' => 0, 'pe_oi_pct' => 0, 'trade_action' => 'WAIT'],
        ];
    }

    // ═════════════════════════ STRIKE KEY / LADDER HELPERS ═════════════════════════

    private function strikeKey($strike): string
    {
        return number_format((float) $strike, 4, '.', '');
    }

    private function findAtmIndex(array $ladder, ?float $atmStrike): ?int
    {
        if ($atmStrike === null || empty($ladder)) return null;

        foreach ($ladder as $i => $s) {
            if (abs($s - $atmStrike) < 0.01) return $i;
        }

        $closestIdx = null; $closestDiff = null;
        foreach ($ladder as $i => $s) {
            $diff = abs($s - $atmStrike);
            if ($closestDiff === null || $diff < $closestDiff) { $closestDiff = $diff; $closestIdx = $i; }
        }
        return $closestIdx;
    }

    private function strikeAtOffset(array $ladder, ?int $atmIndex, int $offset): ?float
    {
        if ($atmIndex === null) return null;
        $idx = $atmIndex + $offset;
        return $ladder[$idx] ?? null;
    }

    // ═════════════════════════ SHARED HELPERS ═════════════════════════

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', self::TF)
            ->where('is_active', 1)
            ->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')
            ->toArray();
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->toDateString())) return $prev->toDateString();
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}