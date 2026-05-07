<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Original30MinOhlcData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExpiryOiController — Smart Single-Trade Expiry Engine
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * PHILOSOPHY
 *   This engine does NOT fire on every candle.
 *   It watches the market patiently, builds a pressure score each candle,
 *   and fires ONCE when strong alignment appears.
 *
 *   One trade per day. High accuracy. No noise.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * SIGNALS & WEIGHTS (max = 17)
 *
 *   Premium Expansion   → +3   (option price surging while futures flat)
 *   OI Build-Up         → +2   (fresh positions entering, not covering)
 *   Volume Spike        → +2   (smart money always creates volume)
 *   Futures Direction   → +2   (confirms trade side: bullish/bearish)
 *   Gamma Pressure      → +2   (ATM + ATM+1 both expanding)
 *   Momentum Accel      → +2   (2 consecutive rising candles)
 *   MM Trap             → +4   (market maker trapped at OI wall — highest weight)
 *
 * TRADE THRESHOLD: score >= 9
 * ENTRY WINDOW:    10:30 – 14:30 only
 * ONE TRADE/DAY:   once triggered, ignore all further candles
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * MARKET MAKER TRAP LOGIC
 *
 *   1. Find OI walls: strike with highest CE OI = call wall
 *                     strike with highest PE OI = put wall
 *
 *   2. Call Trap:  futures price > call wall strike AND CE premium rising
 *                  → call sellers trapped → BUY CE
 *
 *   3. Put Trap:   futures price < put wall strike AND PE premium rising
 *                  → put sellers trapped → BUY PE
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * OUTPUT
 *   timeline[]    → all candles with their scores (for the pressure chart)
 *   best_trade    → the ONE fired trade (or null if no trade today)
 *   summary       → day stats
 * ═══════════════════════════════════════════════════════════════════════════
 */
class ExpiryOiController extends Controller
{
    // ── Signal thresholds ────────────────────────────────────────────────────
    private const PREMIUM_EXP_MIN  = 0.40;   // option premium > +40%
    private const FUTURES_FLAT_MAX = 0.003;  // futures flat < 0.3% for S1
    private const OI_CHANGE_MIN    = 0.05;   // OI increase > 5%
    private const OI_PREMIUM_MIN   = 0.25;   // premium also > 25% for S2
    private const VOLUME_SPIKE_MIN = 1.5;    // volume ratio > 1.5x
    private const FUTURES_MOMENTUM = 0.0025; // 0.25% futures move
    private const GAMMA_THRESHOLD  = 0.35;   // both ATM+ATM+1 > 35%

    // ── Pressure score weights ────────────────────────────────────────────────
    private const W_PREMIUM   = 3;
    private const W_OI        = 2;
    private const W_VOLUME    = 2;
    private const W_FUTURES   = 2;
    private const W_GAMMA     = 2;
    private const W_MOMENTUM  = 2;
    private const W_MM_TRAP   = 4;
    private const SCORE_MAX   = 17;  // 3+2+2+2+2+2+4

    // ── Trade rules ───────────────────────────────────────────────────────────
    private const SCORE_THRESHOLD = 8;    // fire only when >= 9/17
    private const ENTRY_START     = '10:30';
    private const ENTRY_END       = '14:30';
    private const TARGET_MULT     = 3.0;  // 3x entry price
    private const SL_MULT         = 0.50; // 50% of entry price

    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Smart Entry Engine';
        return view($this->activeTemplate . 'user.expiry-oi.index', compact('pageTitle'));
    }

    public function getSymbols()
    {
        $symbols = \App\Models\Original30MinOhlcSymbol::active()
            ->orderBy('symbol')
            ->pluck('symbol');

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    /**
     * GET /expiry-oi/data?symbol=NIFTY&date=Y-m-d
     */
    public function getData(Request $request)
    {
        try {
            $symbol = strtoupper(trim($request->get('symbol', 'NIFTY')));
            $date   = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            $futRows    = $this->loadFutRows($symbol, $date);
            $optionRows = $this->loadOptionRows($symbol, $date);

            if ($futRows->isEmpty() && $optionRows->isEmpty()) {
                return response()->json([
                    'success'        => true,
                    'data'           => null,
                    'is_today'       => $date === Carbon::today()->toDateString(),
                    'is_expiry_date' => false,
                    'message'        => "No data found for {$symbol} on {$date}.",
                ]);
            }

            $isExpiry = $this->isExpiryDate($symbol, $date);
            $expiry   = $this->getNearestExpiry($symbol, $date);

            // Build slot maps
            $futBySlot   = $this->buildFutMap($futRows);
            $ceBySlot    = $this->buildOptionMap($optionRows, 'CE');
            $peBySlot    = $this->buildOptionMap($optionRows, 'PE');
            $ceOiBySlot  = $this->totalOiBySlot($optionRows, 'CE');
            $peOiBySlot  = $this->totalOiBySlot($optionRows, 'PE');

            // Find OI walls (market maker positions) across the full day
            $oiWalls = $this->findOIWalls($optionRows);

            // Run the pressure engine — returns timeline + best_trade
            ['timeline' => $timeline, 'best_trade' => $bestTrade] = $this->runPressureEngine(
                $futBySlot, $ceBySlot, $peBySlot,
                $ceOiBySlot, $peOiBySlot, $oiWalls
            );

            $summary = $this->buildSummary($timeline, $bestTrade, $isExpiry, $oiWalls);

            return response()->json([
                'success'        => true,
                'symbol'         => $symbol,
                'expiry'         => $expiry,
                'date'           => $date,
                'is_today'       => $date === Carbon::today()->toDateString(),
                'is_expiry_date' => $isExpiry,
                'timeline'       => $timeline,
                'best_trade'     => $bestTrade,
                'oi_walls'       => $oiWalls,
                'summary'        => $summary,
                'slots_loaded'   => count($timeline),
                'message'        => count($timeline) . ' candles analyzed | ' . $date,
            ]);

        } catch (\Throwable $e) {
            Log::error('ExpiryOiController@getData: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DATA LOADERS
    // ═════════════════════════════════════════════════════════════════════════

    private function loadFutRows(string $symbol, string $date)
    {
        return Original30MinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close', 'volume', 'oi']);
    }

    private function loadOptionRows(string $symbol, string $date)
    {
        return Original30MinOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get([
                'interval_time', 'instrument_type', 'strike_position',
                'strike', 'trading_symbol', 'open', 'high', 'low',
                'close', 'volume', 'oi',
            ]);
    }

    private function buildFutMap($rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $slot       = Carbon::parse($r->interval_time)->format('H:i');
            $map[$slot] = [
                'close'  => (float) $r->close,
                'open'   => (float) $r->open,
                'high'   => (float) $r->high,
                'low'    => (float) $r->low,
                'volume' => (int)   $r->volume,
            ];
        }
        ksort($map);
        return $map;
    }

    private function buildOptionMap($rows, string $type): array
    {
        $map = [];
        foreach ($rows as $r) {
            if ($r->instrument_type !== $type) continue;
            $slot = Carbon::parse($r->interval_time)->format('H:i');
            $pos  = $r->strike_position ?? 'UNKNOWN';
            if (!isset($map[$slot][$pos])) {
                $map[$slot][$pos] = [
                    'close'  => 0.0,
                    'oi'     => 0,
                    'strike' => $r->strike,
                    'symbol' => $r->trading_symbol,
                    'volume' => 0,
                ];
            }
            $map[$slot][$pos]['close']  += (float) $r->close;
            $map[$slot][$pos]['oi']     += (int)   $r->oi;
            $map[$slot][$pos]['volume'] += (int)   $r->volume;
        }
        ksort($map);
        return $map;
    }

    private function totalOiBySlot($rows, string $type): array
    {
        $map = [];
        foreach ($rows as $r) {
            if ($r->instrument_type !== $type) continue;
            $slot       = Carbon::parse($r->interval_time)->format('H:i');
            $map[$slot] = ($map[$slot] ?? 0) + (int) $r->oi;
        }
        ksort($map);
        return $map;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MARKET MAKER OI WALL DETECTION
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Find the strike with maximum CE OI (call wall) and max PE OI (put wall).
     *
     * Market makers sell options at these strikes.
     * When price breaks through these walls → they get trapped → violent move.
     *
     * We aggregate OI across all time slots per strike to get the
     * dominant OI concentration for the day.
     */
    private function findOIWalls($rows): array
    {
        $ceOiByStrike = [];
        $peOiByStrike = [];

        foreach ($rows as $r) {
            $strike = (float) $r->strike;
            if ($strike <= 0) continue;

            if ($r->instrument_type === 'CE') {
                $ceOiByStrike[$strike] = ($ceOiByStrike[$strike] ?? 0) + (int) $r->oi;
            } elseif ($r->instrument_type === 'PE') {
                $peOiByStrike[$strike] = ($peOiByStrike[$strike] ?? 0) + (int) $r->oi;
            }
        }

        // Find call wall (highest CE OI) and put wall (highest PE OI)
        $callWall = $callWallOi = null;
        $putWall  = $putWallOi  = null;

        foreach ($ceOiByStrike as $strike => $oi) {
            if ($callWallOi === null || $oi > $callWallOi) {
                $callWall   = $strike;
                $callWallOi = $oi;
            }
        }
        foreach ($peOiByStrike as $strike => $oi) {
            if ($putWallOi === null || $oi > $putWallOi) {
                $putWall   = $strike;
                $putWallOi = $oi;
            }
        }

        // Build top-5 OI walls for display
        arsort($ceOiByStrike);
        arsort($peOiByStrike);

        return [
            'call_wall'         => $callWall,
            'call_wall_oi'      => $callWallOi,
            'put_wall'          => $putWall,
            'put_wall_oi'       => $putWallOi,
            'top_ce_strikes'    => array_slice($ceOiByStrike, 0, 5, true),
            'top_pe_strikes'    => array_slice($peOiByStrike, 0, 5, true),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PRESSURE ENGINE — Main loop
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Loop every 30-min slot, calculate pressure score per candle.
     * Fire trade ONCE when score >= threshold inside entry window.
     * Returns full timeline (for pressure chart) + the best_trade object.
     */
    private function runPressureEngine(
        array $futBySlot,
        array $ceBySlot,
        array $peBySlot,
        array $ceOiBySlot,
        array $peOiBySlot,
        array $oiWalls
    ): array {
        $allSlots = collect(array_keys($futBySlot))
            ->merge(array_keys($ceBySlot))
            ->merge(array_keys($peBySlot))
            ->unique()->sort()->values()->toArray();

        $timeline   = [];
        $tradeTaken = false;
        $bestTrade  = null;

        foreach ($allSlots as $idx => $slot) {
            $prevSlot     = $idx > 0 ? $allSlots[$idx - 1] : null;
            $prevPrevSlot = $idx > 1 ? $allSlots[$idx - 2] : null;

            $currFut = $futBySlot[$slot]['close']                           ?? null;
            $prevFut = $prevSlot ? ($futBySlot[$prevSlot]['close']  ?? null) : null;

            $currCe     = $ceBySlot[$slot]                                  ?? [];
            $prevCe     = $prevSlot     ? ($ceBySlot[$prevSlot]     ?? [])  : [];
            $prevPrevCe = $prevPrevSlot ? ($ceBySlot[$prevPrevSlot] ?? [])  : [];

            $currPe     = $peBySlot[$slot]                                  ?? [];
            $prevPe     = $prevSlot     ? ($peBySlot[$prevSlot]     ?? [])  : [];
            $prevPrevPe = $prevPrevSlot ? ($peBySlot[$prevPrevSlot] ?? [])  : [];

            $currCeOi = $ceOiBySlot[$slot]                                  ?? 0;
            $prevCeOi = $prevSlot ? ($ceOiBySlot[$prevSlot] ?? 0)           : 0;
            $currPeOi = $peOiBySlot[$slot]                                  ?? 0;
            $prevPeOi = $prevSlot ? ($peOiBySlot[$prevSlot] ?? 0)           : 0;

            $inWindow   = ($slot >= self::ENTRY_START && $slot <= self::ENTRY_END);
            $isWatchZone = ($slot >= '10:00' && $slot < self::ENTRY_START);

            // ── Compute all individual signals ────────────────────────────────
            $signals = $this->computeAllSignals(
                $currCe, $prevCe, $prevPrevCe,
                $currPe, $prevPe, $prevPrevPe,
                $prevFut, $currFut,
                $prevCeOi, $currCeOi,
                $prevPeOi, $currPeOi,
                $oiWalls
            );

            // ── Calculate pressure score ──────────────────────────────────────
            $bullScore = $this->calcBullScore($signals);
            $bearScore = $this->calcBearScore($signals);
            $maxScore  = max($bullScore, $bearScore);
            $side      = $bullScore >= $bearScore ? 'CE' : 'PE';

            // ── PCR ───────────────────────────────────────────────────────────
            $pcr = $currCeOi > 0 ? round($currPeOi / $currCeOi, 3) : null;

            // ── Build timeline row ────────────────────────────────────────────
            $row = [
                'slot'          => $slot,
                'in_window'     => $inWindow,
                'is_watch_zone' => $isWatchZone,
                'is_first_hour' => $slot < '10:00',
                'is_post_trade' => $slot > self::ENTRY_END,

                'fut_price'     => $currFut,
                'fut_chg_pct'   => ($prevFut && $prevFut > 0 && $currFut)
                    ? round((($currFut - $prevFut) / $prevFut) * 100, 3) : null,

                'pcr'           => $pcr,
                'ce_oi'         => $currCeOi,
                'pe_oi'         => $currPeOi,

                'signals'       => $signals,
                'bull_score'    => $bullScore,
                'bear_score'    => $bearScore,
                'max_score'     => $maxScore,
                'dominant_side' => $side,

                'trade_fired'   => false,
                'is_best_entry' => false,
            ];

            // ── FIRE TRADE? ───────────────────────────────────────────────────
            // Only fire if:  inside entry window + score >= threshold + not already taken
            if ($inWindow && !$tradeTaken && $maxScore >= self::SCORE_THRESHOLD) {
                $tradeTaken = true;

                $isBull   = $bullScore >= $bearScore;
                $tradeType = $isBull ? 'BUY_CALL' : 'BUY_PUT';
                $tradeScore = $isBull ? $bullScore : $bearScore;

                $strike   = $this->pickBestStrike($isBull, $currCe, $currPe, $signals, $oiWalls);
                $entryPx  = $strike ? round($strike['ltp'], 2) : null;
                $target   = $entryPx ? round($entryPx * self::TARGET_MULT, 2)  : null;
                $sl       = $entryPx ? round($entryPx * self::SL_MULT,    2)  : null;

                $reasons = $this->buildReasons($signals, $isBull);

                $bestTrade = [
                    'slot'         => $slot,
                    'signal'       => $tradeType,
                    'side'         => $isBull ? 'CE' : 'PE',
                    'score'        => $tradeScore,
                    'score_max'    => self::SCORE_MAX,
                    'confidence'   => min(100, (int) round(($tradeScore / self::SCORE_MAX) * 100)),
                    'strike'       => $strike,
                    'entry_price'  => $entryPx,
                    'target'       => $target,
                    'stoploss'     => $sl,
                    'reasons'      => $reasons,
                    'mm_trap'      => $signals['mmTrap'],
                    'futures_dir'  => $signals['futuresDir'],
                    'pcr'          => $pcr,
                    'bull_score'   => $bullScore,
                    'bear_score'   => $bearScore,
                ];

                $row['trade_fired']   = true;
                $row['is_best_entry'] = true;
            }

            $timeline[] = $row;
        }

        return ['timeline' => $timeline, 'best_trade' => $bestTrade];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIGNAL COMPUTATION
    // ═════════════════════════════════════════════════════════════════════════

    private function computeAllSignals(
        array $currCe, array $prevCe, array $prevPrevCe,
        array $currPe, array $prevPe, array $prevPrevPe,
        ?float $prevFut, ?float $currFut,
        int $prevCeOi, int $currCeOi,
        int $prevPeOi, int $currPeOi,
        array $oiWalls
    ): array {
        try {
            // ── Signal 1: Premium Expansion ───────────────────────────────────────
            $cePremEx = $this->isPremiumExpansion($prevCe, $currCe, $prevFut, $currFut);
            $pePremEx = $this->isPremiumExpansion($prevPe, $currPe, $prevFut, $currFut);

            // ── Signal 2: OI Build-Up with Volume ─────────────────────────────────
            $ceOiBuild = $this->isOiBuild($prevCe, $currCe);
            $peOiBuild = $this->isOiBuild($prevPe, $currPe);

            // ── Signal 3: Volume Spike ─────────────────────────────────────────────
            $ceVolSpike = $this->isVolSpike($prevCe, $currCe);
            $peVolSpike = $this->isVolSpike($prevPe, $currPe);

            // ── Signal 4: Futures Direction ───────────────────────────────────────
            $futuresDir = $this->getFuturesDirection($prevFut, $currFut);

            // ── Signal 5: Gamma Pressure ──────────────────────────────────────────
            $gamma = $this->detectGamma($prevCe, $currCe, $prevPe, $currPe);

            // ── Signal 6: Momentum Acceleration ──────────────────────────────────
            $ceAccel = $this->isMomentumAccel($prevPrevCe, $prevCe, $currCe);
            $peAccel = $this->isMomentumAccel($prevPrevPe, $prevPe, $currPe);

            // ── Signal 7: Market Maker Trap ───────────────────────────────────────
            $mmTrap = $this->detectMMTrap($currFut, $oiWalls, $currCe, $currPe, $prevCe, $prevPe);

            // ── Premium change pct (for display) ─────────────────────────────────
            $cePremChg = $this->getPremChg($prevCe, $currCe);
            $pePremChg = $this->getPremChg($prevPe, $currPe);
            $ceOiChg   = $this->getOiChg($prevCe, $currCe);
            $peOiChg   = $this->getOiChg($prevPe, $currPe);
            $ceVolRatio = $this->getVolRatio($prevCe, $currCe);
            $peVolRatio = $this->getVolRatio($prevPe, $currPe);

            return compact(
                'cePremEx',  'pePremEx',
                'ceOiBuild', 'peOiBuild',
                'ceVolSpike','peVolSpike',
                'futuresDir',
                'gamma',
                'ceAccel',   'peAccel',
                'mmTrap',
                'cePremChg', 'pePremChg',
                'ceOiChg',   'peOiChg',
                'ceVolRatio','peVolRatio'
            );
        } catch (\Throwable $e) {
            return $this->emptySignals();
        }
    }

    private function emptySignals(): array
    {
        return [
            'cePremEx'   => ['triggered' => false, 'val' => null],
            'pePremEx'   => ['triggered' => false, 'val' => null],
            'ceOiBuild'  => ['triggered' => false, 'val' => null],
            'peOiBuild'  => ['triggered' => false, 'val' => null],
            'ceVolSpike' => ['triggered' => false, 'val' => null],
            'peVolSpike' => ['triggered' => false, 'val' => null],
            'futuresDir' => ['direction' => 'UNKNOWN', 'bullish' => false, 'bearish' => false, 'val' => null],
            'gamma'      => ['ce' => false, 'pe' => false, 'active' => false],
            'ceAccel'    => ['triggered' => false],
            'peAccel'    => ['triggered' => false],
            'mmTrap'     => ['call_trap' => false, 'put_trap' => false, 'type' => null, 'detail' => null],
            'cePremChg'  => null,
            'pePremChg'  => null,
            'ceOiChg'    => null,
            'peOiChg'    => null,
            'ceVolRatio' => null,
            'peVolRatio' => null,
        ];
    }

    // ── Individual detectors ────────────────────────────────────────────────

    private function isPremiumExpansion(array $prev, array $curr, ?float $prevFut, ?float $currFut): array
    {
        $atm = $curr['ATM'] ?? null;
        $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['close'] <= 0) {
            return ['triggered' => false, 'val' => null];
        }
        $premChg = ($atm['close'] - $patm['close']) / $patm['close'];
        $futChg  = ($prevFut && $currFut && $prevFut > 0)
            ? abs(($currFut - $prevFut) / $prevFut) : 0;

        $triggered = $premChg > self::PREMIUM_EXP_MIN && $futChg < self::FUTURES_FLAT_MAX;
        return ['triggered' => $triggered, 'val' => round($premChg * 100, 1)];
    }

    private function isOiBuild(array $prev, array $curr): array
    {
        $atm  = $curr['ATM'] ?? null;
        $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['oi'] <= 0 || $patm['close'] <= 0) {
            return ['triggered' => false, 'val' => null];
        }
        $oiChg   = ($atm['oi']    - $patm['oi'])    / $patm['oi'];
        $premChg = ($atm['close'] - $patm['close']) / $patm['close'];
        $volRatio = $patm['volume'] > 0 ? ($atm['volume'] / $patm['volume']) : 0;

        $triggered = $oiChg > self::OI_CHANGE_MIN
            && $premChg > self::OI_PREMIUM_MIN
            && $volRatio > self::VOLUME_SPIKE_MIN;
        return ['triggered' => $triggered, 'val' => round($oiChg * 100, 1)];
    }

    private function isVolSpike(array $prev, array $curr): array
    {
        $atm  = $curr['ATM'] ?? null;
        $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['volume'] <= 0) {
            return ['triggered' => false, 'val' => null];
        }
        $ratio = $atm['volume'] / $patm['volume'];
        return ['triggered' => $ratio > self::VOLUME_SPIKE_MIN, 'val' => round($ratio, 2)];
    }

    private function getFuturesDirection(?float $prevFut, ?float $currFut): array
    {
        if (!$prevFut || !$currFut || $prevFut <= 0) {
            return ['direction' => 'UNKNOWN', 'bullish' => false, 'bearish' => false, 'val' => null];
        }
        $chg = ($currFut - $prevFut) / $prevFut;
        return [
            'direction' => $chg > self::FUTURES_MOMENTUM ? 'BULLISH'
                : ($chg < -self::FUTURES_MOMENTUM ? 'BEARISH' : 'SIDEWAYS'),
            'bullish'   => $chg > self::FUTURES_MOMENTUM,
            'bearish'   => $chg < -self::FUTURES_MOMENTUM,
            'val'       => round($chg * 100, 3),
        ];
    }

    private function detectGamma(array $prevCe, array $currCe, array $prevPe, array $currPe): array
    {
        $ce = $pe = false;
        foreach ([['CE', $prevCe, $currCe], ['PE', $prevPe, $currPe]] as [$type, $prev, $curr]) {
            $pAtm  = $prev['ATM']   ?? null;
            $pAtm1 = $prev['ATM+1'] ?? null;
            $cAtm  = $curr['ATM']   ?? null;
            $cAtm1 = $curr['ATM+1'] ?? null;
            if (!$pAtm || !$pAtm1 || !$cAtm || !$cAtm1) continue;
            if ($pAtm['close'] <= 0 || $pAtm1['close'] <= 0) continue;
            $m1 = ($cAtm['close']  - $pAtm['close'])  / $pAtm['close'];
            $m2 = ($cAtm1['close'] - $pAtm1['close']) / $pAtm1['close'];
            if ($m1 > self::GAMMA_THRESHOLD && $m2 > self::GAMMA_THRESHOLD) {
                if ($type === 'CE') $ce = true;
                if ($type === 'PE') $pe = true;
            }
        }
        return ['ce' => $ce, 'pe' => $pe, 'active' => $ce || $pe];
    }

    private function isMomentumAccel(array $pp, array $prev, array $curr): array
    {
        $ppAtm   = $pp['ATM']   ?? null;
        $prevAtm = $prev['ATM'] ?? null;
        $currAtm = $curr['ATM'] ?? null;
        if (!$ppAtm || !$prevAtm || !$currAtm) return ['triggered' => false];
        if ($ppAtm['close'] <= 0 || $prevAtm['close'] <= 0) return ['triggered' => false];
        $c1 = ($prevAtm['close'] - $ppAtm['close'])   / $ppAtm['close'];
        $c2 = ($currAtm['close'] - $prevAtm['close']) / $prevAtm['close'];
        return ['triggered' => ($c1 > 0 && $c2 > $c1)];
    }

    /**
     * Market Maker Trap Detection
     *
     * Call Trap:  price has crossed ABOVE the call wall → sellers trapped → BUY CE
     * Put Trap:   price has crossed BELOW the put wall  → sellers trapped → BUY PE
     * Also checks that option premium is rising to confirm the squeeze.
     */
    private function detectMMTrap(?float $currFut, array $oiWalls, array $currCe, array $prevCe, array $currPe, array $prevPe): array
    {
        $result = ['call_trap' => false, 'put_trap' => false, 'type' => null, 'detail' => null];

        if (!$currFut) return $result;

        $callWall = $oiWalls['call_wall'] ?? null;
        $putWall  = $oiWalls['put_wall']  ?? null;

        // ── Call trap: price broke above call wall ────────────────────────────
        if ($callWall && $currFut > $callWall) {
            $atmCe  = $currCe['ATM']  ?? null;
            $pAtmCe = $prevCe['ATM']  ?? null;
            $premRising = $atmCe && $pAtmCe && $pAtmCe['close'] > 0
                && ($atmCe['close'] - $pAtmCe['close']) / $pAtmCe['close'] > 0.1;

            $result['call_trap'] = $premRising;
            if ($premRising) {
                $result['type']   = 'CALL_TRAP';
                $result['detail'] = "Price {$currFut} crossed above Call Wall {$callWall} — CE sellers TRAPPED";
            }
        }

        // ── Put trap: price broke below put wall ──────────────────────────────
        if ($putWall && $currFut < $putWall) {
            $atmPe  = $currPe['ATM']  ?? null;
            $pAtmPe = $prevPe['ATM']  ?? null;
            $premRising = $atmPe && $pAtmPe && $pAtmPe['close'] > 0
                && ($atmPe['close'] - $pAtmPe['close']) / $pAtmPe['close'] > 0.1;

            $result['put_trap'] = $premRising;
            if ($premRising && !$result['type']) {
                $result['type']   = 'PUT_TRAP';
                $result['detail'] = "Price {$currFut} crossed below Put Wall {$putWall} — PE sellers TRAPPED";
            }
        }

        return $result;
    }

    // ── Display helpers ─────────────────────────────────────────────────────

    private function getPremChg(array $prev, array $curr): ?float
    {
        $a = $curr['ATM'] ?? null; $p = $prev['ATM'] ?? null;
        if (!$a || !$p || $p['close'] <= 0) return null;
        return round((($a['close'] - $p['close']) / $p['close']) * 100, 1);
    }

    private function getOiChg(array $prev, array $curr): ?float
    {
        $a = $curr['ATM'] ?? null; $p = $prev['ATM'] ?? null;
        if (!$a || !$p || $p['oi'] <= 0) return null;
        return round((($a['oi'] - $p['oi']) / $p['oi']) * 100, 1);
    }

    private function getVolRatio(array $prev, array $curr): ?float
    {
        $a = $curr['ATM'] ?? null; $p = $prev['ATM'] ?? null;
        if (!$a || !$p || $p['volume'] <= 0) return null;
        return round($a['volume'] / $p['volume'], 2);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SCORE CALCULATION
    // ═════════════════════════════════════════════════════════════════════════

    private function calcBullScore(array $s): int
    {
        $score = 0;
        if ($s['cePremEx']['triggered']  ?? false) $score += self::W_PREMIUM;
        if ($s['ceOiBuild']['triggered'] ?? false) $score += self::W_OI;
        if ($s['ceVolSpike']['triggered']?? false) $score += self::W_VOLUME;
        if ($s['futuresDir']['bullish']  ?? false) $score += self::W_FUTURES;
        if ($s['gamma']['ce']            ?? false) $score += self::W_GAMMA;
        if ($s['ceAccel']['triggered']   ?? false) $score += self::W_MOMENTUM;
        if ($s['mmTrap']['call_trap']    ?? false) $score += self::W_MM_TRAP;
        return $score;
    }

    private function calcBearScore(array $s): int
    {
        $score = 0;
        if ($s['pePremEx']['triggered']  ?? false) $score += self::W_PREMIUM;
        if ($s['peOiBuild']['triggered'] ?? false) $score += self::W_OI;
        if ($s['peVolSpike']['triggered']?? false) $score += self::W_VOLUME;
        if ($s['futuresDir']['bearish']  ?? false) $score += self::W_FUTURES;
        if ($s['gamma']['pe']            ?? false) $score += self::W_GAMMA;
        if ($s['peAccel']['triggered']   ?? false) $score += self::W_MOMENTUM;
        if ($s['mmTrap']['put_trap']     ?? false) $score += self::W_MM_TRAP;
        return $score;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // STRIKE PICKER
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Pick the best strike to buy.
     *
     * If MM trap active → pick ATM+1 (CE) or ATM-1 (PE) — more explosive
     * If gamma active   → pick ATM+1 (CE) or ATM-1 (PE)
     * Otherwise         → pick ATM (safer, moves with index)
     */
    private function pickBestStrike(
        bool $isBull, array $currCe, array $currPe,
        array $signals, array $oiWalls
    ): ?array {
        $options  = $isBull ? $currCe : $currPe;
        $mmActive = $isBull
            ? ($signals['mmTrap']['call_trap'] ?? false)
            : ($signals['mmTrap']['put_trap']  ?? false);
        $gmActive = $isBull
            ? ($signals['gamma']['ce'] ?? false)
            : ($signals['gamma']['pe'] ?? false);

        // For bullish: ATM+1 is aggressive, ATM is safe
        // For bearish: ATM-1 is aggressive, ATM is safe
        $aggPos = $isBull ? 'ATM+1' : 'ATM-1';

        $useAgg = $mmActive || $gmActive;
        $pos    = $useAgg ? $aggPos : 'ATM';

        $d = $options[$pos] ?? $options['ATM'] ?? null;
        if (!$d) return null;

        return [
            'position' => $pos,
            'strike'   => $d['strike'],
            'symbol'   => $d['symbol'],
            'ltp'      => round($d['close'], 2),
            'oi'       => $d['oi'],
            'reason'   => $useAgg ? "Aggressive pick ({$pos}) — MM trap / gamma active" : "Primary pick (ATM) — standard entry",
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // REASONS BUILDER
    // ═════════════════════════════════════════════════════════════════════════

    private function buildReasons(array $s, bool $isBull): array
    {
        $reasons = [];

        if ($isBull) {
            if ($s['cePremEx']['triggered']  ?? false) $reasons[] = ['key' => 'Premium Expansion',     'val' => '+' . $s['cePremEx']['val'] . '%',  'score' => self::W_PREMIUM,  'active' => true];
            if ($s['ceOiBuild']['triggered'] ?? false) $reasons[] = ['key' => 'OI Build-Up',           'val' => '+' . $s['ceOiBuild']['val'] . '%', 'score' => self::W_OI,       'active' => true];
            if ($s['ceVolSpike']['triggered']?? false) $reasons[] = ['key' => 'Volume Spike',          'val' => $s['ceVolSpike']['val'] . 'x',      'score' => self::W_VOLUME,   'active' => true];
            if ($s['futuresDir']['bullish']  ?? false) $reasons[] = ['key' => 'Futures Bullish',       'val' => '+' . $s['futuresDir']['val'] . '%','score' => self::W_FUTURES,  'active' => true];
            if ($s['gamma']['ce']            ?? false) $reasons[] = ['key' => 'Gamma Squeeze (CE)',    'val' => 'ATM+ATM+1 expanding',              'score' => self::W_GAMMA,    'active' => true];
            if ($s['ceAccel']['triggered']   ?? false) $reasons[] = ['key' => 'Momentum Acceleration', 'val' => '2 rising candles',                 'score' => self::W_MOMENTUM, 'active' => true];
            if ($s['mmTrap']['call_trap']    ?? false) $reasons[] = ['key' => '🪤 MM Call Trap',       'val' => $s['mmTrap']['detail'],             'score' => self::W_MM_TRAP,  'active' => true];
        } else {
            if ($s['pePremEx']['triggered']  ?? false) $reasons[] = ['key' => 'Premium Expansion',     'val' => '+' . $s['pePremEx']['val'] . '%',  'score' => self::W_PREMIUM,  'active' => true];
            if ($s['peOiBuild']['triggered'] ?? false) $reasons[] = ['key' => 'OI Build-Up',           'val' => '+' . $s['peOiBuild']['val'] . '%', 'score' => self::W_OI,       'active' => true];
            if ($s['peVolSpike']['triggered']?? false) $reasons[] = ['key' => 'Volume Spike',          'val' => $s['peVolSpike']['val'] . 'x',      'score' => self::W_VOLUME,   'active' => true];
            if ($s['futuresDir']['bearish']  ?? false) $reasons[] = ['key' => 'Futures Bearish',       'val' => $s['futuresDir']['val'] . '%',      'score' => self::W_FUTURES,  'active' => true];
            if ($s['gamma']['pe']            ?? false) $reasons[] = ['key' => 'Gamma Squeeze (PE)',    'val' => 'ATM+ATM+1 expanding',              'score' => self::W_GAMMA,    'active' => true];
            if ($s['peAccel']['triggered']   ?? false) $reasons[] = ['key' => 'Momentum Acceleration', 'val' => '2 rising candles',                 'score' => self::W_MOMENTUM, 'active' => true];
            if ($s['mmTrap']['put_trap']     ?? false) $reasons[] = ['key' => '🪤 MM Put Trap',        'val' => $s['mmTrap']['detail'],             'score' => self::W_MM_TRAP,  'active' => true];
        }

        return $reasons;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DAY SUMMARY
    // ═════════════════════════════════════════════════════════════════════════

    private function buildSummary(array $timeline, ?array $bestTrade, bool $isExpiry, array $oiWalls): array
    {
        $maxScore = 0;
        $peakSlot = null;
        $mmTrapSlots = 0;
        $gammaSlots  = 0;

        foreach ($timeline as $row) {
            if ($row['max_score'] > $maxScore) {
                $maxScore = $row['max_score'];
                $peakSlot = $row['slot'];
            }
            $sig = $row['signals'] ?? [];
            if (($sig['mmTrap']['call_trap'] ?? false)) $mmTrapSlots++;
            if (($sig['mmTrap']['put_trap']  ?? false)) $mmTrapSlots++;
            if (($sig['gamma']['active']     ?? false)) $gammaSlots++;
        }

        return [
            'is_expiry'       => $isExpiry,
            'trade_fired'     => $bestTrade !== null,
            'trade_signal'    => $bestTrade['signal']     ?? null,
            'trade_slot'      => $bestTrade['slot']       ?? null,
            'trade_conf'      => $bestTrade['confidence'] ?? 0,
            'peak_score'      => $maxScore,
            'peak_slot'       => $peakSlot,
            'mm_trap_slots'   => $mmTrapSlots,
            'gamma_slots'     => $gammaSlots,
            'call_wall'       => $oiWalls['call_wall'] ?? null,
            'put_wall'        => $oiWalls['put_wall']  ?? null,
            'slots_analyzed'  => count($timeline),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // UTILITIES
    // ═════════════════════════════════════════════════════════════════════════

    private function isExpiryDate(string $symbol, string $date): bool
    {
        $fut = Original30MinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $date)
            ->exists();
        if ($fut) return true;
        $exchange = in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
        return \App\Models\ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $date)
            ->exists();
    }

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        $fut = Original30MinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
        if ($fut) return $fut;
        $exchange = in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
        return \App\Models\ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry')
            ->value(DB::raw('DATE(expiry)'));
    }
}