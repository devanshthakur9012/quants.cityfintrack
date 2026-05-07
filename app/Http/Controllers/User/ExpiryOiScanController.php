<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Original30MinOhlcData;
use App\Models\Original30MinOhlcSymbol;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExpiryOiScanController — Multi-Symbol Scanner
 *
 * Runs the same Smart Entry Engine logic across ALL active symbols
 * for a given date and returns a simple summary table:
 * Symbol | Signal | Entry Time | Strike | Entry Price | Target | SL | Score | Confidence
 */
class ExpiryOiScanController extends Controller
{
    // ── Signal thresholds (same as ExpiryOiController) ────────────────────
    private const PREMIUM_EXP_MIN  = 0.40;
    private const FUTURES_FLAT_MAX = 0.003;
    private const OI_CHANGE_MIN    = 0.05;
    private const OI_PREMIUM_MIN   = 0.25;
    private const VOLUME_SPIKE_MIN = 1.5;
    private const FUTURES_MOMENTUM = 0.0025;
    private const GAMMA_THRESHOLD  = 0.35;

    // ── Weights ────────────────────────────────────────────────────────────
    private const W_PREMIUM   = 3;
    private const W_OI        = 2;
    private const W_VOLUME    = 2;
    private const W_FUTURES   = 2;
    private const W_GAMMA     = 2;
    private const W_MOMENTUM  = 2;
    private const W_MM_TRAP   = 4;
    private const SCORE_MAX   = 17;

    // ── Trade rules ────────────────────────────────────────────────────────
    private const SCORE_THRESHOLD = 8;
    private const ENTRY_START     = '10:30';
    private const ENTRY_END       = '14:30';
    private const TARGET_MULT     = 3.0;
    private const SL_MULT         = 0.50;

    public function index()
    {
        $pageTitle = 'Multi-Symbol Scanner';
        return view($this->activeTemplate . 'user.expiry-oi.scan', compact('pageTitle'));
    }

    /**
     * GET /expiry-oi/scan/data?date=Y-m-d
     */
    public function getData(Request $request)
    {
        try {
            $date = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            // Get all active symbols
            $symbols = Original30MinOhlcSymbol::active()
                ->orderBy('symbol')
                ->pluck('symbol')
                ->toArray();

            if (empty($symbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active symbols found.',
                ]);
            }

            $results = [];

            foreach ($symbols as $symbol) {
                try {
                    $row = $this->scanSymbol($symbol, $date);
                    $results[] = $row;
                } catch (\Throwable $e) {
                    Log::warning("Scan failed for {$symbol}: " . $e->getMessage());
                    $results[] = [
                        'symbol'      => $symbol,
                        'signal'      => 'ERROR',
                        'side'        => null,
                        'entry_time'  => null,
                        'strike'      => null,
                        'entry_price' => null,
                        'target'      => null,
                        'stoploss'    => null,
                        'score'       => 0,
                        'confidence'  => 0,
                        'is_expiry'   => false,
                        'peak_score'  => 0,
                        'futures_dir' => null,
                        'pcr'         => null,
                        'call_wall'   => null,
                        'put_wall'    => null,
                        'error'       => $e->getMessage(),
                    ];
                }
            }

            // Sort: trades first (by score desc), then no-trade by peak score desc
            usort($results, function ($a, $b) {
                $aFired = in_array($a['signal'], ['BUY_CALL', 'BUY_PUT']) ? 1 : 0;
                $bFired = in_array($b['signal'], ['BUY_CALL', 'BUY_PUT']) ? 1 : 0;
                if ($aFired !== $bFired) return $bFired - $aFired;
                return ($b['score'] ?? 0) - ($a['score'] ?? 0);
            });

            return response()->json([
                'success'       => true,
                'date'          => $date,
                'is_today'      => $date === Carbon::today()->toDateString(),
                'total_symbols' => count($symbols),
                'trade_count'   => count(array_filter($results, fn($r) => in_array($r['signal'], ['BUY_CALL', 'BUY_PUT']))),
                'results'       => $results,
                'scanned_at'    => now()->format('H:i:s'),
            ]);

        } catch (\Throwable $e) {
            Log::error('ExpiryOiScanController@getData: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scan a single symbol — returns one result row
    // ─────────────────────────────────────────────────────────────────────────

    private function scanSymbol(string $symbol, string $date): array
    {
        $base = [
            'symbol'      => $symbol,
            'signal'      => 'NO TRADE',
            'side'        => null,
            'entry_time'  => null,
            'strike'      => null,
            'entry_price' => null,
            'target'      => null,
            'stoploss'    => null,
            'score'       => 0,
            'confidence'  => 0,
            'is_expiry'   => false,
            'peak_score'  => 0,
            'futures_dir' => null,
            'pcr'         => null,
            'call_wall'   => null,
            'put_wall'    => null,
        ];

        $futRows    = $this->loadFutRows($symbol, $date);
        $optionRows = $this->loadOptionRows($symbol, $date);

        if ($futRows->isEmpty() && $optionRows->isEmpty()) {
            $base['signal'] = 'NO DATA';
            return $base;
        }

        $base['is_expiry'] = $this->isExpiryDate($symbol, $date);

        $futBySlot  = $this->buildFutMap($futRows);
        $ceBySlot   = $this->buildOptionMap($optionRows, 'CE');
        $peBySlot   = $this->buildOptionMap($optionRows, 'PE');
        $ceOiBySlot = $this->totalOiBySlot($optionRows, 'CE');
        $peOiBySlot = $this->totalOiBySlot($optionRows, 'PE');
        $oiWalls    = $this->findOIWalls($optionRows);

        $base['call_wall'] = $oiWalls['call_wall'] ?? null;
        $base['put_wall']  = $oiWalls['put_wall']  ?? null;

        ['timeline' => $timeline, 'best_trade' => $bestTrade] = $this->runPressureEngine(
            $futBySlot, $ceBySlot, $peBySlot,
            $ceOiBySlot, $peOiBySlot, $oiWalls
        );

        // Peak score across all slots
        $peakScore = 0;
        foreach ($timeline as $row) {
            if ($row['max_score'] > $peakScore) $peakScore = $row['max_score'];
        }
        $base['peak_score'] = $peakScore;

        if (!$bestTrade) {
            $peakRow = null;
            foreach ($timeline as $row) {
                if ($peakRow === null || $row['max_score'] > $peakRow['max_score']) $peakRow = $row;
            }
            $base['signals'] = $peakRow['signals'] ?? null;
            return $base;
        }

        $firedRow = null;
        foreach ($timeline as $row) {
            if ($row['is_best_entry']) { $firedRow = $row; break; }
        }

        return array_merge($base, [
            'signal'      => $bestTrade['signal'],
            'side'        => $bestTrade['side'],
            'entry_time'  => $bestTrade['slot'],
            'strike'      => $bestTrade['strike']['strike']   ?? null,
            'strike_sym'  => $bestTrade['strike']['symbol']   ?? null,
            'entry_price' => $bestTrade['entry_price'],
            'target'      => $bestTrade['target'],
            'stoploss'    => $bestTrade['stoploss'],
            'score'       => $bestTrade['score'],
            'confidence'  => $bestTrade['confidence'],
            'futures_dir' => $bestTrade['futures_dir']['direction'] ?? null,
            'pcr'         => $bestTrade['pcr'],
            'signals'     => $firedRow['signals'] ?? null,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DATA LOADERS (identical to ExpiryOiController)
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
            $slot = Carbon::parse($r->interval_time)->format('H:i');
            $map[$slot] = ['close' => (float)$r->close, 'open' => (float)$r->open, 'volume' => (int)$r->volume];
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
                $map[$slot][$pos] = ['close' => 0.0, 'oi' => 0, 'strike' => $r->strike, 'symbol' => $r->trading_symbol, 'volume' => 0];
            }
            $map[$slot][$pos]['close']  += (float)$r->close;
            $map[$slot][$pos]['oi']     += (int)$r->oi;
            $map[$slot][$pos]['volume'] += (int)$r->volume;
        }
        ksort($map);
        return $map;
    }

    private function totalOiBySlot($rows, string $type): array
    {
        $map = [];
        foreach ($rows as $r) {
            if ($r->instrument_type !== $type) continue;
            $slot = Carbon::parse($r->interval_time)->format('H:i');
            $map[$slot] = ($map[$slot] ?? 0) + (int)$r->oi;
        }
        ksort($map);
        return $map;
    }

    private function findOIWalls($rows): array
    {
        $ceOi = []; $peOi = [];
        foreach ($rows as $r) {
            $strike = (float)$r->strike;
            if ($strike <= 0) continue;
            if ($r->instrument_type === 'CE') $ceOi[$strike] = ($ceOi[$strike] ?? 0) + (int)$r->oi;
            if ($r->instrument_type === 'PE') $peOi[$strike] = ($peOi[$strike] ?? 0) + (int)$r->oi;
        }
        $callWall = $callWallOi = $putWall = $putWallOi = null;
        foreach ($ceOi as $s => $o) { if ($callWallOi === null || $o > $callWallOi) { $callWall = $s; $callWallOi = $o; } }
        foreach ($peOi as $s => $o) { if ($putWallOi  === null || $o > $putWallOi)  { $putWall  = $s; $putWallOi  = $o; } }
        return ['call_wall' => $callWall, 'call_wall_oi' => $callWallOi, 'put_wall' => $putWall, 'put_wall_oi' => $putWallOi];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PRESSURE ENGINE
    // ═════════════════════════════════════════════════════════════════════════

    private function runPressureEngine(array $futBySlot, array $ceBySlot, array $peBySlot, array $ceOiBySlot, array $peOiBySlot, array $oiWalls): array
    {
        $allSlots = collect(array_keys($futBySlot))->merge(array_keys($ceBySlot))->merge(array_keys($peBySlot))->unique()->sort()->values()->toArray();
        $timeline = []; $tradeTaken = false; $bestTrade = null;

        foreach ($allSlots as $idx => $slot) {
            $prevSlot     = $idx > 0 ? $allSlots[$idx - 1] : null;
            $prevPrevSlot = $idx > 1 ? $allSlots[$idx - 2] : null;

            $currFut = $futBySlot[$slot]['close'] ?? null;
            $prevFut = $prevSlot ? ($futBySlot[$prevSlot]['close'] ?? null) : null;

            $currCe     = $ceBySlot[$slot] ?? [];
            $prevCe     = $prevSlot     ? ($ceBySlot[$prevSlot]     ?? []) : [];
            $prevPrevCe = $prevPrevSlot ? ($ceBySlot[$prevPrevSlot] ?? []) : [];
            $currPe     = $peBySlot[$slot] ?? [];
            $prevPe     = $prevSlot     ? ($peBySlot[$prevSlot]     ?? []) : [];
            $prevPrevPe = $prevPrevSlot ? ($peBySlot[$prevPrevSlot] ?? []) : [];

            $currCeOi = $ceOiBySlot[$slot] ?? 0;
            $prevCeOi = $prevSlot ? ($ceOiBySlot[$prevSlot] ?? 0) : 0;
            $currPeOi = $peOiBySlot[$slot] ?? 0;
            $prevPeOi = $prevSlot ? ($peOiBySlot[$prevSlot] ?? 0) : 0;

            $inWindow = ($slot >= self::ENTRY_START && $slot <= self::ENTRY_END);

            $signals   = $this->computeAllSignals($currCe, $prevCe, $prevPrevCe, $currPe, $prevPe, $prevPrevPe, $prevFut, $currFut, $prevCeOi, $currCeOi, $prevPeOi, $currPeOi, $oiWalls);
            $bullScore = $this->calcBullScore($signals);
            $bearScore = $this->calcBearScore($signals);
            $maxScore  = max($bullScore, $bearScore);
            $side      = $bullScore >= $bearScore ? 'CE' : 'PE';
            $pcr       = $currCeOi > 0 ? round($currPeOi / $currCeOi, 3) : null;

            $row = [
                'slot'          => $slot,
                'in_window'     => $inWindow,
                'is_first_hour' => $slot < '10:00',
                'max_score'     => $maxScore,
                'bull_score'    => $bullScore,
                'bear_score'    => $bearScore,
                'dominant_side' => $side,
                'signals'       => $signals,
                'trade_fired'   => false,
                'is_best_entry' => false,
            ];

            if ($inWindow && !$tradeTaken && $maxScore >= self::SCORE_THRESHOLD) {
                $tradeTaken = true;
                $isBull     = $bullScore >= $bearScore;
                $strike     = $this->pickBestStrike($isBull, $currCe, $currPe, $signals, $oiWalls);
                $entryPx    = $strike ? round($strike['ltp'], 2) : null;

                $bestTrade = [
                    'slot'        => $slot,
                    'signal'      => $isBull ? 'BUY_CALL' : 'BUY_PUT',
                    'side'        => $isBull ? 'CE' : 'PE',
                    'score'       => $isBull ? $bullScore : $bearScore,
                    'confidence'  => min(100, (int)round((max($bullScore, $bearScore) / self::SCORE_MAX) * 100)),
                    'bull_score'  => $bullScore,
                    'bear_score'  => $bearScore,
                    'strike'      => $strike,
                    'entry_price' => $entryPx,
                    'target'      => $entryPx ? round($entryPx * self::TARGET_MULT, 2) : null,
                    'stoploss'    => $entryPx ? round($entryPx * self::SL_MULT, 2) : null,
                    'futures_dir' => $signals['futuresDir'],
                    'pcr'         => $pcr,
                ];
                $row['trade_fired'] = true; $row['is_best_entry'] = true;
            }
            $timeline[] = $row;
        }
        return ['timeline' => $timeline, 'best_trade' => $bestTrade];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIGNALS (identical to ExpiryOiController)
    // ═════════════════════════════════════════════════════════════════════════

    private function computeAllSignals(array $currCe, array $prevCe, array $prevPrevCe, array $currPe, array $prevPe, array $prevPrevPe, ?float $prevFut, ?float $currFut, int $prevCeOi, int $currCeOi, int $prevPeOi, int $currPeOi, array $oiWalls): array
    {
        try {
            return [
                'cePremEx'   => $this->isPremiumExpansion($prevCe, $currCe, $prevFut, $currFut),
                'pePremEx'   => $this->isPremiumExpansion($prevPe, $currPe, $prevFut, $currFut),
                'ceOiBuild'  => $this->isOiBuild($prevCe, $currCe),
                'peOiBuild'  => $this->isOiBuild($prevPe, $currPe),
                'ceVolSpike' => $this->isVolSpike($prevCe, $currCe),
                'peVolSpike' => $this->isVolSpike($prevPe, $currPe),
                'futuresDir' => $this->getFuturesDirection($prevFut, $currFut),
                'gamma'      => $this->detectGamma($prevCe, $currCe, $prevPe, $currPe),
                'ceAccel'    => $this->isMomentumAccel($prevPrevCe, $prevCe, $currCe),
                'peAccel'    => $this->isMomentumAccel($prevPrevPe, $prevPe, $currPe),
                'mmTrap'     => $this->detectMMTrap($currFut, $oiWalls, $currCe, $prevCe, $currPe, $prevPe),
                'cePremChg'  => null, 'pePremChg' => null, 'ceOiChg' => null, 'peOiChg' => null, 'ceVolRatio' => null, 'peVolRatio' => null,
            ];
        } catch (\Throwable $e) {
            return $this->emptySignals();
        }
    }

    private function emptySignals(): array
    {
        return [
            'cePremEx' => ['triggered' => false, 'val' => null], 'pePremEx' => ['triggered' => false, 'val' => null],
            'ceOiBuild' => ['triggered' => false, 'val' => null], 'peOiBuild' => ['triggered' => false, 'val' => null],
            'ceVolSpike' => ['triggered' => false, 'val' => null], 'peVolSpike' => ['triggered' => false, 'val' => null],
            'futuresDir' => ['direction' => 'UNKNOWN', 'bullish' => false, 'bearish' => false, 'val' => null],
            'gamma' => ['ce' => false, 'pe' => false, 'active' => false],
            'ceAccel' => ['triggered' => false], 'peAccel' => ['triggered' => false],
            'mmTrap' => ['call_trap' => false, 'put_trap' => false, 'type' => null, 'detail' => null],
            'cePremChg' => null, 'pePremChg' => null, 'ceOiChg' => null, 'peOiChg' => null, 'ceVolRatio' => null, 'peVolRatio' => null,
        ];
    }

    private function isPremiumExpansion(array $prev, array $curr, ?float $prevFut, ?float $currFut): array
    {
        $atm = $curr['ATM'] ?? null; $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['close'] <= 0) return ['triggered' => false, 'val' => null];
        $premChg = ($atm['close'] - $patm['close']) / $patm['close'];
        $futChg  = ($prevFut && $currFut && $prevFut > 0) ? abs(($currFut - $prevFut) / $prevFut) : 0;
        return ['triggered' => $premChg > self::PREMIUM_EXP_MIN && $futChg < self::FUTURES_FLAT_MAX, 'val' => round($premChg * 100, 1)];
    }

    private function isOiBuild(array $prev, array $curr): array
    {
        $atm = $curr['ATM'] ?? null; $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['oi'] <= 0 || $patm['close'] <= 0) return ['triggered' => false, 'val' => null];
        $oiChg   = ($atm['oi'] - $patm['oi']) / $patm['oi'];
        $premChg = ($atm['close'] - $patm['close']) / $patm['close'];
        $volRatio = $patm['volume'] > 0 ? ($atm['volume'] / $patm['volume']) : 0;
        return ['triggered' => $oiChg > self::OI_CHANGE_MIN && $premChg > self::OI_PREMIUM_MIN && $volRatio > self::VOLUME_SPIKE_MIN, 'val' => round($oiChg * 100, 1)];
    }

    private function isVolSpike(array $prev, array $curr): array
    {
        $atm = $curr['ATM'] ?? null; $patm = $prev['ATM'] ?? null;
        if (!$atm || !$patm || $patm['volume'] <= 0) return ['triggered' => false, 'val' => null];
        $ratio = $atm['volume'] / $patm['volume'];
        return ['triggered' => $ratio > self::VOLUME_SPIKE_MIN, 'val' => round($ratio, 2)];
    }

    private function getFuturesDirection(?float $prevFut, ?float $currFut): array
    {
        if (!$prevFut || !$currFut || $prevFut <= 0) return ['direction' => 'UNKNOWN', 'bullish' => false, 'bearish' => false, 'val' => null];
        $chg = ($currFut - $prevFut) / $prevFut;
        return ['direction' => $chg > self::FUTURES_MOMENTUM ? 'BULLISH' : ($chg < -self::FUTURES_MOMENTUM ? 'BEARISH' : 'SIDEWAYS'), 'bullish' => $chg > self::FUTURES_MOMENTUM, 'bearish' => $chg < -self::FUTURES_MOMENTUM, 'val' => round($chg * 100, 3)];
    }

    private function detectGamma(array $prevCe, array $currCe, array $prevPe, array $currPe): array
    {
        $ce = $pe = false;
        foreach ([['CE', $prevCe, $currCe], ['PE', $prevPe, $currPe]] as [$type, $prev, $curr]) {
            $pAtm = $prev['ATM'] ?? null; $pAtm1 = $prev['ATM+1'] ?? null;
            $cAtm = $curr['ATM'] ?? null; $cAtm1 = $curr['ATM+1'] ?? null;
            if (!$pAtm || !$pAtm1 || !$cAtm || !$cAtm1 || $pAtm['close'] <= 0 || $pAtm1['close'] <= 0) continue;
            $m1 = ($cAtm['close'] - $pAtm['close']) / $pAtm['close'];
            $m2 = ($cAtm1['close'] - $pAtm1['close']) / $pAtm1['close'];
            if ($m1 > self::GAMMA_THRESHOLD && $m2 > self::GAMMA_THRESHOLD) { if ($type === 'CE') $ce = true; else $pe = true; }
        }
        return ['ce' => $ce, 'pe' => $pe, 'active' => $ce || $pe];
    }

    private function isMomentumAccel(array $pp, array $prev, array $curr): array
    {
        $ppAtm = $pp['ATM'] ?? null; $prevAtm = $prev['ATM'] ?? null; $currAtm = $curr['ATM'] ?? null;
        if (!$ppAtm || !$prevAtm || !$currAtm || $ppAtm['close'] <= 0 || $prevAtm['close'] <= 0) return ['triggered' => false];
        $c1 = ($prevAtm['close'] - $ppAtm['close']) / $ppAtm['close'];
        $c2 = ($currAtm['close'] - $prevAtm['close']) / $prevAtm['close'];
        return ['triggered' => ($c1 > 0 && $c2 > $c1)];
    }

    private function detectMMTrap(?float $currFut, array $oiWalls, array $currCe, array $prevCe, array $currPe, array $prevPe): array
    {
        $result = ['call_trap' => false, 'put_trap' => false, 'type' => null, 'detail' => null];
        if (!$currFut) return $result;
        $callWall = $oiWalls['call_wall'] ?? null;
        $putWall  = $oiWalls['put_wall']  ?? null;
        if ($callWall && $currFut > $callWall) {
            $atmCe = $currCe['ATM'] ?? null; $pAtmCe = $prevCe['ATM'] ?? null;
            $premRising = $atmCe && $pAtmCe && $pAtmCe['close'] > 0 && ($atmCe['close'] - $pAtmCe['close']) / $pAtmCe['close'] > 0.1;
            $result['call_trap'] = $premRising;
            if ($premRising) { $result['type'] = 'CALL_TRAP'; $result['detail'] = "Price {$currFut} > Call Wall {$callWall}"; }
        }
        if ($putWall && $currFut < $putWall) {
            $atmPe = $currPe['ATM'] ?? null; $pAtmPe = $prevPe['ATM'] ?? null;
            $premRising = $atmPe && $pAtmPe && $pAtmPe['close'] > 0 && ($atmPe['close'] - $pAtmPe['close']) / $pAtmPe['close'] > 0.1;
            $result['put_trap'] = $premRising;
            if ($premRising && !$result['type']) { $result['type'] = 'PUT_TRAP'; $result['detail'] = "Price {$currFut} < Put Wall {$putWall}"; }
        }
        return $result;
    }

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

    private function pickBestStrike(bool $isBull, array $currCe, array $currPe, array $signals, array $oiWalls): ?array
    {
        $options  = $isBull ? $currCe : $currPe;
        $mmActive = $isBull ? ($signals['mmTrap']['call_trap'] ?? false) : ($signals['mmTrap']['put_trap'] ?? false);
        $gmActive = $isBull ? ($signals['gamma']['ce'] ?? false) : ($signals['gamma']['pe'] ?? false);
        $aggPos   = $isBull ? 'ATM+1' : 'ATM-1';
        $pos      = ($mmActive || $gmActive) ? $aggPos : 'ATM';
        $d        = $options[$pos] ?? $options['ATM'] ?? null;
        if (!$d) return null;
        return ['position' => $pos, 'strike' => $d['strike'], 'symbol' => $d['symbol'], 'ltp' => round($d['close'], 2), 'oi' => $d['oi']];
    }

    private function isExpiryDate(string $symbol, string $date): bool
    {
        $fut = Original30MinOhlcData::where('base_symbol', $symbol)->where('instrument_type', 'FUT')->whereDate('trade_date', $date)->whereDate('expiry_date', $date)->exists();
        if ($fut) return true;
        $exchange = in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
        return \App\Models\ZerodhaInstrument::where('name', $symbol)->where('exchange', $exchange)->whereIn('instrument_type', ['CE', 'PE'])->whereDate('expiry', $date)->exists();
    }
}