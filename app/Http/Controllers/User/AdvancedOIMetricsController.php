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
 * Anchor = previous trading day 15:00 (close), same convention as
 * OIFlowSentimentController. Reuses cp_option_ohlc_15min exactly as-is.
 * Does NOT touch collectors, does NOT touch OIFlowSentimentController,
 * does NOT change BUY CE / BUY PE / WAIT logic. Zero schema changes.
 *
 * ── THIS REVISION — TRUE OI DECAY VELOCITY (formula corrected) ─────
 * Previous revision computed liveSum/anchorSum, which is actually the
 * OI RETENTION RATIO, not a velocity — it had no time component at
 * all. This revision implements the actual OI Decay Velocity formula:
 *
 *     OI Decay Velocity = (OI_previous − OI_current) / (OI_previous × Δt) × 100
 *
 * Δt = trading hours elapsed between the anchor (previous day 15:00
 * close) and the snapshot, measured as hours-since-market-open on the
 * snapshot's own day (09:15 → 10:15 = 1hr, → 11:15 = 2hr, → 12:15 =
 * 3hr). OI only accrues during trading hours and the anchor is fixed
 * at the prior close, so this is the meaningful elapsed-time base for
 * the rate, rather than raw calendar time (which would include the
 * overnight gap).
 *
 * Sign/scale: a POSITIVE result means OI is shrinking (decaying) at
 * that %-per-hour rate; a NEGATIVE result means OI is still building.
 * This is now HIGHER-is-stronger (unlike the old ratio, which was
 * LOWER-is-stronger) — DECAY_VELOCITY_THRESHOLD below is the trigger
 * point for "STRONG" decay.
 *
 * ⚠ DECAY_VELOCITY_THRESHOLD is a PLACEHOLDER (5.0 %/hr). The old
 * 0.51/0.70 thresholds were calibrated for a 0–1 retention ratio and
 * do not carry over to this %/hour scale. Backtest against real data
 * and adjust this single constant.
 *
 * ── OI Signal (unchanged) ──
 * Ported verbatim from OIFlowSentimentController::calcOISignal() — kept
 * completely separate from the decay logic above, per request.
 *
 * ── STRIKE KEY FIX (carried over, unchanged) ──
 * All strike-based lookups route through strikeKey() so a DB strike like
 * "2640.0000" and a ladder-derived float 2640.0 resolve to the same key.
 */
class AdvancedOIMetricsController extends Controller
{
    private const TF        = '15min';
    private const OPT_TABLE = 'cp_option_ohlc_15min';

    /** The three intraday snapshots taken today, each vs. the same prev-day-close anchor. */
    private const ANALYSIS_TIMES = [
        '10:15' => '10:15:00',
        '11:15' => '11:15:00',
        '12:15' => '12:15:00',
    ];

    /** Used only for "is today's data available yet" checks (lastDate / history range). */
    private const LAST_SLOT_TIME = '12:15:00';

    private const PREV_DAY_TIME = '15:00:00'; // previous trading day close anchor

    /** Market open — used to compute Δt (hours elapsed) for each snapshot. */
    private const MARKET_OPEN_TIME = '09:15:00';

    /** Strike-ladder offsets used for the Decay Velocity basket. */
    private const BASKET_OFFSETS = [-1, 0, 1];

    /**
     * Strong-signal threshold, in %-per-hour. HIGHER decay velocity =
     * stronger directional pull. PLACEHOLDER — needs empirical tuning
     * against real OI data; the old 0.51/0.70 values do not apply to
     * this scale.
     */
    private const DECAY_VELOCITY_THRESHOLD = 5.0;

    /** Set to true to include the debug block (ATM/ladder diagnostics) per slot. */
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
                ->whereRaw('TIME(interval_time) = ?', [self::LAST_SLOT_TIME])
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
        $times = array_values(self::ANALYSIS_TIMES);

        $tradeDates = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $config->id)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$fromDate, $toDate])
            ->whereRaw('TIME(interval_time) IN (?, ?, ?)', $times)
            ->select(DB::raw('DATE(trade_date) as d'))
            ->distinct()->orderBy('d')->pluck('d')->toArray();

        $rows = [];
        foreach ($tradeDates as $d) {
            foreach ($symbols as $symbol) {
                $result = $this->runAnalysisForSymbol($config, $symbol, $d);
                if (!empty($result['no_data'])) continue; // skip days with no snapshot at all — same convention as OI Flow Sentiment
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

    /**
     * Runs all 3 intraday snapshots (10:15 / 11:15 / 12:15) for one symbol
     * on one date, each vs. the SAME previous-trading-day 15:00 anchor.
     */
    private function runAnalysisForSymbol(object $config, string $symbol, string $date): array
    {
        try {
            $prevDate = $this->getPreviousTradingDate($date);

            // Anchor is fetched once — identical for all 3 slots.
            $anchorRows = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $prevDate)
                ->whereRaw('TIME(interval_time) = ?', [self::PREV_DAY_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['instrument_type', 'strike', 'oi'])
                ->get();

            $anchorOi = ['CE' => [], 'PE' => []];
            foreach ($anchorRows as $r) {
                $key = $this->strikeKey($r->strike);
                $anchorOi[$r->instrument_type][$key] = (int) $r->oi;
            }

            $slots          = [];
            $metaAtm        = null;
            $metaExpiry     = null;
            $anySlotHasData = false;

            foreach (self::ANALYSIS_TIMES as $label => $time) {
                $liveRows = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $date)
                    ->whereRaw('TIME(interval_time) = ?', [$time])
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->where('is_missing', false)
                    ->select(['instrument_type', 'strike', 'atm_strike', 'expiry_date', 'oi'])
                    ->get();

                if ($liveRows->isEmpty()) {
                    $slots[$label] = $this->emptySlot($time);
                    continue;
                }

                $anySlotHasData = true;

                $liveOi    = ['CE' => [], 'PE' => []];
                $atmStrike = null;
                $expiryUsed = null;

                foreach ($liveRows as $r) {
                    $key = $this->strikeKey($r->strike);
                    $liveOi[$r->instrument_type][$key] = (int) $r->oi;
                    if ($atmStrike === null) $atmStrike = (float) $r->atm_strike;
                    if ($expiryUsed === null) $expiryUsed = $r->expiry_date;
                }

                if ($metaAtm === null) $metaAtm = $atmStrike;
                if ($metaExpiry === null) $metaExpiry = $expiryUsed;

                $ladder = collect(array_keys($liveOi['CE']))
                    ->map(fn ($s) => (float) $s)
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                $atmIndex = $this->findAtmIndex($ladder, $atmStrike);
                $deltaT   = $this->hoursSinceOpen($time);

                $decay    = $this->calcDecayVelocity($ladder, $atmIndex, $liveOi, $anchorOi, $deltaT);
                $decaySig = $this->deriveDecaySignal($decay['ce'], $decay['pe']);
                $oiSignal = $this->buildOiSignalFromTotals($liveOi, $anchorOi);

                $slot = [
                    'time'           => substr($time, 0, 5),
                    'no_data'        => false,
                    'atm_strike'     => $atmStrike,
                    'delta_t_hours'  => $deltaT,
                    'decay_velocity' => $decay,
                    'decay_signal'   => $decaySig['signal'],
                    'decay_strength' => $decaySig['strength'],
                    'oi_signal'      => $oiSignal,
                ];

                if (self::DEBUG_MODE) {
                    $slot['debug'] = [
                        'atm_index'              => $atmIndex,
                        'atm_minus_1'            => $this->strikeAtOffset($ladder, $atmIndex, -1),
                        'atm_plus_1'             => $this->strikeAtOffset($ladder, $atmIndex, 1),
                        'live_ce_strike_count'   => count($liveOi['CE']),
                        'live_pe_strike_count'   => count($liveOi['PE']),
                        'anchor_ce_strike_count' => count($anchorOi['CE']),
                        'anchor_pe_strike_count' => count($anchorOi['PE']),
                    ];
                }

                $slots[$label] = $slot;
            }

            if (!$anySlotHasData) {
                return [
                    'success'     => true,
                    'no_data'     => true,
                    'message'     => "No data found for {$symbol} on {$date}.",
                    'advanced_oi' => $this->emptyAdvancedOi(),
                ];
            }

            $advanced = [
                'meta' => [
                    'symbol'      => $symbol,
                    'date'        => $date,
                    'anchor_date' => $prevDate,
                    'anchor_time' => substr(self::PREV_DAY_TIME, 0, 5),
                    'expiry_used' => $metaExpiry,
                    'atm_strike'  => $metaAtm,
                ],
                'slots' => $slots,
            ];

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

    // ═════════════════════════ OI DECAY VELOCITY (formula corrected, per image) ═════════════════════════
    //
    //   OI Decay Velocity = (OI_previous − OI_current) / (OI_previous × Δt) × 100
    //
    // Computed per basket (CE / PE), summed across the ATM±1 strike ladder.
    // OI_previous = anchor sum (prev day 15:00 close), OI_current = live
    // sum, Δt = trading hours since today's market open (see hoursSinceOpen()).
    // POSITIVE = OI shrinking (decaying) at that %/hr rate. NEGATIVE = OI
    // still building. HIGHER positive value = stronger/faster decay.

    private function calcDecayVelocity(array $ladder, ?int $atmIndex, array $liveOi, array $anchorOi, float $deltaT): array
    {
        $ce = $this->basketVelocity($ladder, $atmIndex, $liveOi['CE'], $anchorOi['CE'], $deltaT);
        $pe = $this->basketVelocity($ladder, $atmIndex, $liveOi['PE'], $anchorOi['PE'], $deltaT);

        return [
            'ce'        => $ce,
            'pe'        => $pe,
            'ce_status' => $ce === null ? 'INSUFFICIENT_DATA' : ($ce >= self::DECAY_VELOCITY_THRESHOLD ? 'TRIGGERED' : 'NOT_TRIGGERED'),
            'pe_status' => $pe === null ? 'INSUFFICIENT_DATA' : ($pe >= self::DECAY_VELOCITY_THRESHOLD ? 'TRIGGERED' : 'NOT_TRIGGERED'),
        ];
    }

    private function basketVelocity(array $ladder, ?int $atmIndex, array $liveMap, array $anchorMap, float $deltaT): ?float
    {
        if ($atmIndex === null) return null;
        if ($deltaT <= 0) return null;

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

        // OI Decay Velocity = (OI_previous − OI_current) / (OI_previous × Δt) × 100
        return round((($anchorSum - $liveSum) / ($anchorSum * $deltaT)) * 100, 4);
    }

    /**
     * DECAY VELOCITY signal: HIGHER value = stronger directional pull that
     * side (faster OI melt / wall-floor dissolution per hour). Threshold
     * (>= DECAY_VELOCITY_THRESHOLD) marks STRONG.
     */
    private function deriveDecaySignal(?float $ce, ?float $pe): array
    {
        if ($ce === null && $pe === null) return ['signal' => 'INSUFFICIENT_DATA', 'strength' => null];

        if ($ce === null) { // only PE known — one-sided decision only if it crosses its own strong threshold
            return $pe >= self::DECAY_VELOCITY_THRESHOLD
                ? ['signal' => 'BEARISH', 'strength' => 'STRONG']
                : ['signal' => 'INSUFFICIENT_DATA', 'strength' => null];
        }
        if ($pe === null) { // only CE known
            return $ce >= self::DECAY_VELOCITY_THRESHOLD
                ? ['signal' => 'BULLISH', 'strength' => 'STRONG']
                : ['signal' => 'INSUFFICIENT_DATA', 'strength' => null];
        }

        if ($ce == $pe) return ['signal' => 'NEUTRAL', 'strength' => null];

        $bullish      = $ce > $pe; // higher CE decay velocity => CE melting faster => bullish
        $winningValue = $bullish ? $ce : $pe;
        $strength     = $winningValue >= self::DECAY_VELOCITY_THRESHOLD ? 'STRONG' : 'DIRECTIONAL';

        return ['signal' => $bullish ? 'BULLISH' : 'BEARISH', 'strength' => $strength];
    }

    // ═════════════════════════ OI SIGNAL — untouched, same logic as OIFlowSentimentController ═════════════════════════
    // Duplicated intentionally (not shared/imported) so OIFlowSentimentController is never touched.

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

    private function emptySlot(string $time): array
    {
        $ins = ['ce' => null, 'pe' => null, 'ce_status' => 'INSUFFICIENT_DATA', 'pe_status' => 'INSUFFICIENT_DATA'];

        return [
            'time'           => substr($time, 0, 5),
            'no_data'        => true,
            'atm_strike'     => null,
            'delta_t_hours'  => $this->hoursSinceOpen($time),
            'decay_velocity' => $ins,
            'decay_signal'   => 'INSUFFICIENT_DATA',
            'decay_strength' => null,
            'oi_signal'      => [
                'sentiment' => 'NEUTRAL', 'condition' => 'No Data', 'reason' => 'No data available at this time',
                'ce_oi_pct' => 0, 'pe_oi_pct' => 0, 'trade_action' => 'WAIT',
            ],
        ];
    }

    private function emptyAdvancedOi(): array
    {
        $slots = [];
        foreach (self::ANALYSIS_TIMES as $label => $time) {
            $slots[$label] = $this->emptySlot($time);
        }

        return [
            'meta' => [
                'symbol' => null, 'date' => null, 'anchor_date' => null,
                'anchor_time' => substr(self::PREV_DAY_TIME, 0, 5), 'expiry_used' => null, 'atm_strike' => null,
            ],
            'slots' => $slots,
        ];
    }

    // ═════════════════════════ Δt HELPER ═════════════════════════

    /** Hours elapsed from market open (09:15) to the given HH:MM:SS time. */
    private function hoursSinceOpen(string $time): float
    {
        $open = Carbon::createFromFormat('H:i:s', self::MARKET_OPEN_TIME);
        $t    = Carbon::createFromFormat('H:i:s', $time);
        $minutes = $open->diffInMinutes($t, false);
        return $minutes > 0 ? round($minutes / 60, 4) : 0.0;
    }

    // ═════════════════════════ STRIKE KEY / LADDER HELPERS (unchanged) ═════════════════════════

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

    // ═════════════════════════ SHARED HELPERS (unchanged) ═════════════════════════

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