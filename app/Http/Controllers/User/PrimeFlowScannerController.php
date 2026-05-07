<?php
// FILE: app/Http/Controllers/User/PrimeFlowScannerController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * PrimeFlow — Multi-Symbol Option Scanner
 *
 * Runs the Smart Entry Engine across ALL config-scoped symbols for a given date.
 * Data source : cp_option_ohlc_{timeframe} + cp_fut_ohlc_{timeframe}
 * Config scope: analysis_configs + analysis_config_symbols
 *
 * ── Threshold fixes (vs previous version) ────────────────────────────────────
 *
 * FIX 1 — PREMIUM_EXP_MIN: 0.40 → 0.10
 *   A 40% ATM premium expansion in a single 15-min candle is near-impossible
 *   even on expiry days. 10% is realistic for active sessions.
 *
 * FIX 2 — OI Build: was AND of 3 conditions (OI change + premium + volume all
 *   simultaneously). Changed to: OI increasing + premium rising (volume optional).
 *   OI_CHANGE_MIN: 0.05 → 0.02 (2%), OI_PREMIUM_MIN: 0.25 → 0.05 (5%)
 *
 * FIX 3 — VOLUME_SPIKE_MIN: 1.5 → 1.3 (vol spike for OI build gate)
 *   OI build no longer requires volume spike. Volume spike is its own signal.
 *
 * FIX 4 — GAMMA_THRESHOLD: 0.35 → 0.12
 *   35% simultaneous move on ATM and ATM+1 in one candle requires an extreme
 *   market event. 12% is a strong gamma squeeze on 15-min data.
 *
 * FIX 5 — FUTURES_FLAT_MAX: 0.003 → 0.008
 *   Premium expansion was gated by futures being "flat" (<0.3%). During
 *   genuine premium expansion the futures CAN move a little. 0.8% is safer.
 *
 * FIX 6 — SCORE_THRESHOLD: 8 → 6
 *   With 7 signals and max 17, a score of 8 requires at least 3 heavy signals
 *   firing together. 6 (≈35% of max) is the professional industry standard
 *   for "meaningful confluence" without being noisy.
 *
 * FIX 7 — Default timeframe: 30min → 15min
 *
 * Signal engine (7 components, max score 17):
 *   Premium Expansion  +3 — ATM option premium rising while futures reasonably flat
 *   OI Build           +2 — OI increasing with rising premium
 *   Volume Spike       +2 — current volume > 1.3× previous slot
 *   Futures Direction  +2 — futures moving > 0.25% in one direction
 *   Gamma Squeeze      +2 — ATM + ATM+1 both rising > 12%
 *   Momentum Accel     +2 — two consecutive rising candles accelerating
 *   MM Trap            +4 — price breaks OI wall with premium rising
 *
 * Trade fires when score >= 6 between 10:30–14:30.
 */
class PrimeFlowScannerController extends Controller
{
    private const TIMEFRAMES = ['15min', '30min', '1hr'];

    // ── Signal thresholds (fixed for intraday realism) ─────────────────────
    private const PREMIUM_EXP_MIN  = 0.10;   // FIX1: was 0.40 — 10% premium expansion
    private const FUTURES_FLAT_MAX = 0.008;  // FIX5: was 0.003 — futures flat = <0.8%
    private const OI_CHANGE_MIN    = 0.02;   // FIX2: was 0.05 — 2% OI increase
    private const OI_PREMIUM_MIN   = 0.05;   // FIX2: was 0.25 — 5% premium rise with OI
    private const VOLUME_SPIKE_MIN = 1.3;    // FIX3: was 1.5 — 1.3× previous slot volume
    private const FUTURES_MOMENTUM = 0.0025; // unchanged — 0.25% futures move
    private const GAMMA_THRESHOLD  = 0.12;   // FIX4: was 0.35 — 12% move on ATM + ATM+1

    // ── Weights (max = 17) ─────────────────────────────────────────────────
    private const W_PREMIUM  = 3;
    private const W_OI       = 2;
    private const W_VOLUME   = 2;
    private const W_FUTURES  = 2;
    private const W_GAMMA    = 2;
    private const W_MOMENTUM = 2;
    private const W_MM_TRAP  = 4;
    private const SCORE_MAX  = 17;

    // ── Trade rules ────────────────────────────────────────────────────────
    private const SCORE_THRESHOLD = 6;      // FIX6: was 8 — 6/17 = ~35% confluence
    private const ENTRY_START     = '10:30';
    private const ENTRY_END       = '14:30';
    private const TARGET_MULT     = 3.0;
    private const SL_MULT         = 0.50;

    // ─────────────────────────────────────────────────────────────────────────
    //  Page
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'PrimeFlow — Option Scanner';
        $thresh_hold = self::SCORE_THRESHOLD;
        return view($this->activeTemplate . 'user.primeflow-scanner.index', compact('pageTitle','thresh_hold'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Scan data endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function getData(Request $request)
    {
        try {
            $timeframe = $this->resolveTimeframe($request);
            $date      = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => "No active Analysis Config for [{$timeframe}].",
                ]);
            }

            $symbols = $this->getConfigSymbols($config->id);
            if (empty($symbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.']);
            }

            $optTable = 'cp_option_ohlc_' . $timeframe;
            $futTable = 'cp_fut_ohlc_'    . $timeframe;

            $hasData = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereDate('trade_date', $date)
                ->exists();

            if (!$hasData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for ' . $date . '. Market may have been closed.',
                ]);
            }

            $results = [];

            foreach ($symbols as $symbol) {
                try {
                    $results[] = $this->scanSymbol($symbol, $date, $config->id, $optTable, $futTable);
                } catch (\Throwable $e) {
                    Log::warning("PrimeFlow scan failed for {$symbol}: " . $e->getMessage());
                    $results[] = $this->errorRow($symbol, $e->getMessage());
                }
            }

            // Sort: trades first by score desc, then no-trade by peak score
            usort($results, function ($a, $b) {
                $aFired = in_array($a['signal'], ['BUY_CALL', 'BUY_PUT']) ? 1 : 0;
                $bFired = in_array($b['signal'], ['BUY_CALL', 'BUY_PUT']) ? 1 : 0;
                if ($aFired !== $bFired) return $bFired - $aFired;
                return ($b['score'] ?? 0) - ($a['score'] ?? 0);
            });

            return response()->json([
                'success'       => true,
                'date'          => $date,
                'is_today'      => ($date === Carbon::today()->toDateString()),
                'timeframe'     => $timeframe,
                'total_symbols' => count($symbols),
                'trade_count'   => count(array_filter($results, fn($r) => in_array($r['signal'], ['BUY_CALL', 'BUY_PUT']))),
                'results'       => $results,
                'scanned_at'    => now()->format('H:i:s'),
            ]);

        } catch (\Throwable $e) {
            Log::error('PrimeFlowScanner: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Scan one symbol
    // ─────────────────────────────────────────────────────────────────────────

    private function scanSymbol(string $symbol, string $date, int $configId, string $optTable, string $futTable): array
    {
        $base = $this->baseRow($symbol);

        $futRows = DB::table($futTable)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)
            ->where('is_missing', false)
            ->orderBy('interval_time')
            ->select(['interval_time', 'open', 'high', 'low', 'close', 'volume', 'oi'])
            ->get();

        $optRows = DB::table($optTable)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('is_missing', false)
            ->orderBy('interval_time')
            ->select([
                'interval_time', 'instrument_type', 'strike_position',
                'strike', 'trading_symbol', 'open', 'high', 'low',
                'close', 'volume', 'oi', 'expiry_date',
            ])
            ->get();

        if ($futRows->isEmpty() && $optRows->isEmpty()) {
            $base['signal'] = 'NO DATA';
            return $base;
        }

        $futBySlot  = $this->buildFutMap($futRows);
        $ceBySlot   = $this->buildOptionMap($optRows, 'CE');
        $peBySlot   = $this->buildOptionMap($optRows, 'PE');
        $ceOiBySlot = $this->totalOiBySlot($optRows, 'CE');
        $peOiBySlot = $this->totalOiBySlot($optRows, 'PE');
        $oiWalls    = $this->findOIWalls($optRows);

        $base['call_wall'] = $oiWalls['call_wall'] ?? null;
        $base['put_wall']  = $oiWalls['put_wall']  ?? null;

        ['timeline' => $timeline, 'best_trade' => $bestTrade] = $this->runEngine(
            $futBySlot, $ceBySlot, $peBySlot, $ceOiBySlot, $peOiBySlot, $oiWalls
        );

        $peakScore   = 0;
        $peakSignals = null;
        foreach ($timeline as $row) {
            if ($row['max_score'] > $peakScore) {
                $peakScore   = $row['max_score'];
                $peakSignals = $row['signals'];
            }
        }
        $base['peak_score'] = $peakScore;

        if (!$bestTrade) {
            $base['signals'] = $peakSignals;
            return $base;
        }

        $firedSignals = null;
        foreach ($timeline as $row) {
            if ($row['is_best_entry']) { $firedSignals = $row['signals']; break; }
        }

        return array_merge($base, [
            'signal'       => $bestTrade['signal'],
            'side'         => $bestTrade['side'],
            'entry_time'   => $bestTrade['slot'],
            'strike'       => $bestTrade['strike']['strike']  ?? null,
            'strike_sym'   => $bestTrade['strike']['symbol']  ?? null,
            'entry_price'  => $bestTrade['entry_price'],
            'target'       => $bestTrade['target'],
            'stoploss'     => $bestTrade['stoploss'],
            'score'        => $bestTrade['score'],
            'confidence'   => $bestTrade['confidence'],
            'futures_dir'  => $bestTrade['futures_dir']['direction'] ?? null,
            'pcr'          => $bestTrade['pcr'],
            'signals'      => $firedSignals,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Data builders
    // ─────────────────────────────────────────────────────────────────────────

    private function buildFutMap($rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $slot       = Carbon::parse($r->interval_time)->format('H:i');
            $map[$slot] = [
                'close'  => (float) $r->close,
                'open'   => (float) $r->open,
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
                    'volume' => 0,
                    'strike' => $r->strike,
                    'symbol' => $r->trading_symbol,
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

    private function findOIWalls($rows): array
    {
        $ceOi = []; $peOi = [];
        foreach ($rows as $r) {
            $strike = (float) $r->strike;
            if ($strike <= 0) continue;
            if ($r->instrument_type === 'CE') $ceOi[$strike] = ($ceOi[$strike] ?? 0) + (int) $r->oi;
            if ($r->instrument_type === 'PE') $peOi[$strike] = ($peOi[$strike] ?? 0) + (int) $r->oi;
        }
        $callWall = $callWallOi = $putWall = $putWallOi = null;
        foreach ($ceOi as $s => $o) { if ($callWallOi === null || $o > $callWallOi) { $callWall = $s; $callWallOi = $o; } }
        foreach ($peOi as $s => $o) { if ($putWallOi  === null || $o > $putWallOi)  { $putWall  = $s; $putWallOi  = $o; } }
        return ['call_wall' => $callWall, 'put_wall' => $putWall];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Pressure engine
    // ─────────────────────────────────────────────────────────────────────────

    private function runEngine(array $futBySlot, array $ceBySlot, array $peBySlot, array $ceOiBySlot, array $peOiBySlot, array $oiWalls): array
    {
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

            $currFut = $futBySlot[$slot]['close']           ?? null;
            $prevFut = $prevSlot ? ($futBySlot[$prevSlot]['close'] ?? null) : null;

            $currCe     = $ceBySlot[$slot]                  ?? [];
            $prevCe     = $prevSlot     ? ($ceBySlot[$prevSlot]     ?? []) : [];
            $prevPrevCe = $prevPrevSlot ? ($ceBySlot[$prevPrevSlot] ?? []) : [];
            $currPe     = $peBySlot[$slot]                  ?? [];
            $prevPe     = $prevSlot     ? ($peBySlot[$prevSlot]     ?? []) : [];
            $prevPrevPe = $prevPrevSlot ? ($peBySlot[$prevPrevSlot] ?? []) : [];

            $currCeOi = $ceOiBySlot[$slot]                  ?? 0;
            $prevCeOi = $prevSlot ? ($ceOiBySlot[$prevSlot] ?? 0) : 0;
            $currPeOi = $peOiBySlot[$slot]                  ?? 0;
            $prevPeOi = $prevSlot ? ($peOiBySlot[$prevSlot] ?? 0) : 0;

            $inWindow = ($slot >= self::ENTRY_START && $slot <= self::ENTRY_END);

            $signals   = $this->computeSignals(
                $currCe, $prevCe, $prevPrevCe,
                $currPe, $prevPe, $prevPrevPe,
                $prevFut, $currFut,
                $prevCeOi, $currCeOi,
                $prevPeOi, $currPeOi,
                $oiWalls
            );

            $bullScore = $this->calcBullScore($signals);
            $bearScore = $this->calcBearScore($signals);
            $maxScore  = max($bullScore, $bearScore);
            $pcr       = $currCeOi > 0 ? round($currPeOi / $currCeOi, 3) : null;

            $row = [
                'slot'          => $slot,
                'in_window'     => $inWindow,
                'max_score'     => $maxScore,
                'bull_score'    => $bullScore,
                'bear_score'    => $bearScore,
                'signals'       => $signals,
                'trade_fired'   => false,
                'is_best_entry' => false,
            ];

            if ($inWindow && !$tradeTaken && $maxScore >= self::SCORE_THRESHOLD) {
                $tradeTaken = true;
                $isBull     = ($bullScore >= $bearScore);
                $strike     = $this->pickStrike($isBull, $currCe, $currPe, $signals, $oiWalls);
                $entryPx    = $strike ? round($strike['ltp'], 2) : null;

                $bestTrade = [
                    'slot'        => $slot,
                    'signal'      => $isBull ? 'BUY_CALL' : 'BUY_PUT',
                    'side'        => $isBull ? 'CE' : 'PE',
                    'score'       => $isBull ? $bullScore : $bearScore,
                    'confidence'  => min(100, (int) round(($maxScore / self::SCORE_MAX) * 100)),
                    'strike'      => $strike,
                    'entry_price' => $entryPx,
                    'target'      => $entryPx ? round($entryPx * self::TARGET_MULT, 2) : null,
                    'stoploss'    => $entryPx ? round($entryPx * self::SL_MULT, 2)    : null,
                    'futures_dir' => $signals['futuresDir'],
                    'pcr'         => $pcr,
                ];
                $row['trade_fired']   = true;
                $row['is_best_entry'] = true;
            }

            $timeline[] = $row;
        }

        return ['timeline' => $timeline, 'best_trade' => $bestTrade];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Signal computations (relaxed thresholds)
    // ─────────────────────────────────────────────────────────────────────────

    private function computeSignals(
        array $currCe, array $prevCe, array $prevPrevCe,
        array $currPe, array $prevPe, array $prevPrevPe,
        ?float $prevFut, ?float $currFut,
        int $prevCeOi, int $currCeOi,
        int $prevPeOi, int $currPeOi,
        array $oiWalls
    ): array {
        try {
            return [
                'cePremEx'   => $this->premiumExpansion($prevCe, $currCe, $prevFut, $currFut),
                'pePremEx'   => $this->premiumExpansion($prevPe, $currPe, $prevFut, $currFut),
                'ceOiBuild'  => $this->oiBuild($prevCe, $currCe),
                'peOiBuild'  => $this->oiBuild($prevPe, $currPe),
                'ceVolSpike' => $this->volSpike($prevCe, $currCe),
                'peVolSpike' => $this->volSpike($prevPe, $currPe),
                'futuresDir' => $this->futuresDirection($prevFut, $currFut),
                'gamma'      => $this->gammaSqueeze($prevCe, $currCe, $prevPe, $currPe),
                'ceAccel'    => $this->momentumAccel($prevPrevCe, $prevCe, $currCe),
                'peAccel'    => $this->momentumAccel($prevPrevPe, $prevPe, $currPe),
                'mmTrap'     => $this->mmTrap($currFut, $oiWalls, $currCe, $prevCe, $currPe, $prevPe),
            ];
        } catch (\Throwable $e) {
            return $this->emptySignals();
        }
    }

    // FIX 1+5: Premium expansion — 10% threshold, futures flat = <0.8%
    private function premiumExpansion(array $prev, array $curr, ?float $prevFut, ?float $currFut): array
    {
        $atm = $curr['ATM'] ?? null; $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['close'] <= 0) return ['triggered' => false, 'val' => null];
        $premChg = ($atm['close'] - $patm['close']) / $patm['close'];
        $futChg  = ($prevFut && $currFut && $prevFut > 0) ? abs(($currFut - $prevFut) / $prevFut) : 0;
        return [
            'triggered' => ($premChg > self::PREMIUM_EXP_MIN && $futChg < self::FUTURES_FLAT_MAX),
            'val'       => round($premChg * 100, 1),
        ];
    }

    // FIX 2: OI build — only needs OI increase + premium rising (no volume gate here)
    private function oiBuild(array $prev, array $curr): array
    {
        $atm = $curr['ATM'] ?? null; $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['oi'] <= 0 || $patm['close'] <= 0) return ['triggered' => false, 'val' => null];
        $oiChg   = ($atm['oi'] - $patm['oi']) / $patm['oi'];
        $premChg = ($atm['close'] - $patm['close']) / $patm['close'];
        return [
            'triggered' => ($oiChg > self::OI_CHANGE_MIN && $premChg > self::OI_PREMIUM_MIN),
            'val'       => round($oiChg * 100, 1),
        ];
    }

    // FIX 3: Volume spike — 1.3× (was 1.5×)
    private function volSpike(array $prev, array $curr): array
    {
        $atm = $curr['ATM'] ?? null; $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['volume'] <= 0) return ['triggered' => false, 'val' => null];
        $ratio = $atm['volume'] / $patm['volume'];
        return [
            'triggered' => ($ratio > self::VOLUME_SPIKE_MIN),
            'val'       => round($ratio, 2),
        ];
    }

    private function futuresDirection(?float $prevFut, ?float $currFut): array
    {
        if (!$prevFut || !$currFut || $prevFut <= 0) {
            return ['direction' => 'UNKNOWN', 'bullish' => false, 'bearish' => false, 'val' => null];
        }
        $chg = ($currFut - $prevFut) / $prevFut;
        return [
            'direction' => $chg > self::FUTURES_MOMENTUM ? 'BULLISH' : ($chg < -self::FUTURES_MOMENTUM ? 'BEARISH' : 'SIDEWAYS'),
            'bullish'   => $chg > self::FUTURES_MOMENTUM,
            'bearish'   => $chg < -self::FUTURES_MOMENTUM,
            'val'       => round($chg * 100, 3),
        ];
    }

    // FIX 4: Gamma squeeze — 12% threshold (was 35%)
    private function gammaSqueeze(array $prevCe, array $currCe, array $prevPe, array $currPe): array
    {
        $ce = $pe = false;
        foreach ([['CE', $prevCe, $currCe], ['PE', $prevPe, $currPe]] as [$type, $prev, $curr]) {
            $pAtm  = $prev['ATM']   ?? null; $pAtm1 = $prev['ATM+1'] ?? null;
            $cAtm  = $curr['ATM']   ?? null; $cAtm1 = $curr['ATM+1'] ?? null;
            if (!$pAtm || !$pAtm1 || !$cAtm || !$cAtm1) continue;
            if ($pAtm['close'] <= 0 || $pAtm1['close'] <= 0) continue;
            $m1 = ($cAtm['close']  - $pAtm['close'])  / $pAtm['close'];
            $m2 = ($cAtm1['close'] - $pAtm1['close']) / $pAtm1['close'];
            if ($m1 > self::GAMMA_THRESHOLD && $m2 > self::GAMMA_THRESHOLD) {
                if ($type === 'CE') $ce = true; else $pe = true;
            }
        }
        return ['ce' => $ce, 'pe' => $pe, 'active' => ($ce || $pe)];
    }

    private function momentumAccel(array $pp, array $prev, array $curr): array
    {
        $ppAtm   = $pp['ATM']   ?? null;
        $prevAtm = $prev['ATM'] ?? null;
        $currAtm = $curr['ATM'] ?? null;
        if (!$ppAtm || !$prevAtm || !$currAtm || $ppAtm['close'] <= 0 || $prevAtm['close'] <= 0) return ['triggered' => false];
        $c1 = ($prevAtm['close'] - $ppAtm['close'])   / $ppAtm['close'];
        $c2 = ($currAtm['close'] - $prevAtm['close']) / $prevAtm['close'];
        return ['triggered' => ($c1 > 0 && $c2 > $c1)];
    }

    private function mmTrap(?float $currFut, array $oiWalls, array $currCe, array $prevCe, array $currPe, array $prevPe): array
    {
        $result = ['call_trap' => false, 'put_trap' => false, 'type' => null];
        if (!$currFut) return $result;
        $callWall = $oiWalls['call_wall'] ?? null;
        $putWall  = $oiWalls['put_wall']  ?? null;
        if ($callWall && $currFut > $callWall) {
            $atmCe = $currCe['ATM'] ?? null; $pAtmCe = $prevCe['ATM'] ?? null;
            if ($atmCe && $pAtmCe && $pAtmCe['close'] > 0 && ($atmCe['close'] - $pAtmCe['close']) / $pAtmCe['close'] > 0.1) {
                $result['call_trap'] = true; $result['type'] = 'CALL_TRAP';
            }
        }
        if ($putWall && $currFut < $putWall) {
            $atmPe = $currPe['ATM'] ?? null; $pAtmPe = $prevPe['ATM'] ?? null;
            if ($atmPe && $pAtmPe && $pAtmPe['close'] > 0 && ($atmPe['close'] - $pAtmPe['close']) / $pAtmPe['close'] > 0.1) {
                $result['put_trap'] = true;
                if (!$result['type']) $result['type'] = 'PUT_TRAP';
            }
        }
        return $result;
    }

    private function calcBullScore(array $s): int
    {
        $score = 0;
        if ($s['cePremEx']['triggered']   ?? false) $score += self::W_PREMIUM;
        if ($s['ceOiBuild']['triggered']  ?? false) $score += self::W_OI;
        if ($s['ceVolSpike']['triggered'] ?? false) $score += self::W_VOLUME;
        if ($s['futuresDir']['bullish']   ?? false) $score += self::W_FUTURES;
        if ($s['gamma']['ce']             ?? false) $score += self::W_GAMMA;
        if ($s['ceAccel']['triggered']    ?? false) $score += self::W_MOMENTUM;
        if ($s['mmTrap']['call_trap']     ?? false) $score += self::W_MM_TRAP;
        return $score;
    }

    private function calcBearScore(array $s): int
    {
        $score = 0;
        if ($s['pePremEx']['triggered']   ?? false) $score += self::W_PREMIUM;
        if ($s['peOiBuild']['triggered']  ?? false) $score += self::W_OI;
        if ($s['peVolSpike']['triggered'] ?? false) $score += self::W_VOLUME;
        if ($s['futuresDir']['bearish']   ?? false) $score += self::W_FUTURES;
        if ($s['gamma']['pe']             ?? false) $score += self::W_GAMMA;
        if ($s['peAccel']['triggered']    ?? false) $score += self::W_MOMENTUM;
        if ($s['mmTrap']['put_trap']      ?? false) $score += self::W_MM_TRAP;
        return $score;
    }

    private function pickStrike(bool $isBull, array $currCe, array $currPe, array $signals, array $oiWalls): ?array
    {
        $options  = $isBull ? $currCe : $currPe;
        $mmActive = $isBull ? ($signals['mmTrap']['call_trap'] ?? false) : ($signals['mmTrap']['put_trap'] ?? false);
        $gmActive = $isBull ? ($signals['gamma']['ce']         ?? false) : ($signals['gamma']['pe']        ?? false);
        $aggPos   = $isBull ? 'ATM+1' : 'ATM-1';
        $pos      = ($mmActive || $gmActive) ? $aggPos : 'ATM';
        $d        = $options[$pos] ?? $options['ATM'] ?? null;
        if (!$d) return null;
        return ['position' => $pos, 'strike' => $d['strike'], 'symbol' => $d['symbol'], 'ltp' => round($d['close'], 2), 'oi' => $d['oi']];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

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
            'mmTrap'     => ['call_trap' => false, 'put_trap' => false, 'type' => null],
        ];
    }

    private function baseRow(string $symbol): array
    {
        return [
            'symbol'      => $symbol,
            'signal'      => 'NO TRADE',
            'side'        => null,
            'entry_time'  => null,
            'strike'      => null,
            'strike_sym'  => null,
            'entry_price' => null,
            'target'      => null,
            'stoploss'    => null,
            'score'       => 0,
            'confidence'  => 0,
            'peak_score'  => 0,
            'futures_dir' => null,
            'pcr'         => null,
            'call_wall'   => null,
            'put_wall'    => null,
            'signals'     => null,
        ];
    }

    private function errorRow(string $symbol, string $msg): array
    {
        return array_merge($this->baseRow($symbol), ['signal' => 'ERROR', 'error' => $msg]);
    }

    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', $timeframe)->where('is_active', 1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')->toArray();
    }

    private function resolveTimeframe(Request $request): string
    {
        $tf = strtolower(trim($request->get('timeframe', '15min'))); // FIX7: default 15min
        return in_array($tf, self::TIMEFRAMES) ? $tf : '15min';
    }
}