<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OptionSignalController — v3
 *
 * All fixes from v2 review applied:
 *
 *  FIX-A — Silent Accumulation: multi-candle contraction check.
 *           Last 3 candles must ALL have ranges below compression threshold.
 *           Single narrow candle no longer triggers ACCUM.
 *
 *  FIX-B — Breakout + Range Expand mutual exclusion.
 *           A breakout candle no longer double-counts SCORE_RANGE_EXPAND.
 *           Range expansion is treated as breakout confirmation, not a separate signal.
 *
 *  FIX-C — PCR lookback uses rolling array of computed PCR values (not time map re-lookup).
 *           Immune to missing FUT candles skewing the 3-candle offset.
 *
 *  FIX-D — Trend uses SMA SLOPE (SMA[i] > SMA[i-1] > SMA[i-2]) not just price vs SMA.
 *           Prevents flat markets with slight tilt triggering UP/DOWN.
 *
 *  FIX-E — Trap: prev OI spike validated against avgOIChange (not just % change).
 *           Moderate OI increases no longer qualify as trap setup.
 *
 *  FIX-F — Win rate also calculated on MFE basis (mfe_n > threshold).
 *           Close-to-close alone underestimates signal strength.
 *
 *  FIX-G — Volatility Regime Filter: daily ATR vs 5-day ATR.
 *           Low-vol days reduce spike signal scores.
 *           High-vol expansion days boost breakout signal scores.
 *
 *  FIX-H — Session Phase Awareness: morning vs afternoon signal weighting.
 *           Signals before 10:30 (first hour) get +1 morning bonus.
 *           Signals after 14:30 get -1 late-day penalty.
 *
 * Also retained from v2:
 *  FIX 1–10 (all previous fixes remain intact).
 */
class OptionSignalController extends Controller
{
    // ── Signal score weights ──────────────────────────────────────────────────
    private const SCORE_SILENT_ACCUM   =  3;   // OI up + multi-candle range compression + vol above avg
    private const SCORE_LONG_BUILDUP   =  2;   // price ↑ + OI ↑
    private const SCORE_SHORT_BUILDUP  =  2;   // price ↓ + OI ↑
    private const SCORE_SHORT_COVER    =  1;   // price ↑ + OI ↓
    private const SCORE_LONG_UNWIND    =  1;   // price ↓ + OI ↓
    private const SCORE_OI_SPIKE       =  2;   // OI change > N × rolling avg
    private const SCORE_VOL_SPIKE      =  2;   // volume > N × rolling avg
    private const SCORE_PCR_SHIFT      =  2;   // CE/PE ratio shifts fast
    private const SCORE_BREAKOUT       =  3;   // CLOSE breaks prev 3c high/low + vol (FIX 4)
    private const SCORE_RANGE_EXPAND   =  2;   // candle range > N × avg — NOT awarded on breakout candles (FIX-B)
    private const SCORE_TREND_ALIGN    =  1;   // signal aligns with SMA slope trend (FIX-D)
    private const SCORE_TRAP_PENALTY   = -2;   // prev had price+OI spike → OI drops (FIX 6 / FIX-E)
    private const SCORE_TREND_AGAINST  = -1;   // signal opposes SMA slope trend
    private const SCORE_MORNING_BONUS  =  1;   // FIX-H: signal in first-hour session
    private const SCORE_LATEDAY_PENALTY = -1;  // FIX-H: signal in late session (after 14:30)
    private const SCORE_VOL_REGIME_BOOST = 1;  // FIX-G: breakout in high-vol expansion day
    private const SCORE_VOL_REGIME_CUT  = -1;  // FIX-G: spike signal in low-vol regime

    // ══════════════════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $pageTitle      = 'Smart Money Signal Engine';
        $selectedDate   = $request->get('trade_date');
        $selectedSymbol = $request->get('symbol', '');
        $params         = $this->getParams($request);

        $latestDate = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE', 'FUT'])->max('trade_date');
        if (!$latestDate) {
            return $this->emptyView($pageTitle, $params);
        }

        $tradeDate      = $selectedDate ?? $latestDate;
        $availableDates = OptionOhlcData::select('trade_date')->distinct()
                            ->orderByDesc('trade_date')->limit(30)->pluck('trade_date');
        $allSymbols     = OptionOhlcData::whereDate('trade_date', $tradeDate)
                            ->where('instrument_type', 'FUT')
                            ->distinct()->orderBy('base_symbol')->pluck('base_symbol');

        // ── DETAIL MODE ───────────────────────────────────────────────────────
        if ($selectedSymbol && $allSymbols->contains($selectedSymbol)) {
            $detailData = $this->computeDetailSignals($selectedSymbol, $tradeDate, $params);
            return view($this->activeTemplate . 'user.signal-engine.index', [
                'pageTitle'      => $pageTitle,     'rows'            => collect(),
                'latestDate'     => $latestDate,    'selectedDate'    => $tradeDate,
                'availableDates' => $availableDates,'availableSymbols'=> $allSymbols,
                'selectedSymbol' => $selectedSymbol,'params'          => $params,
                'summaryStats'   => [],             'detailMode'      => true,
                'detailData'     => $detailData,
            ]);
        }

        // ── OVERVIEW MODE ─────────────────────────────────────────────────────
        $rows         = collect();
        $summaryStats = ['super' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'trap' => 0, 'total' => 0];

        foreach ($allSymbols as $symbol) {
            $row = $this->computeOverviewRow($symbol, $tradeDate, $params);
            if ($row) {
                $rows->push($row);
                $summaryStats['total']++;
                if     ($row['score'] >= $params['super_high_score']) $summaryStats['super']++;
                elseif ($row['score'] >= $params['high_score'])       $summaryStats['high']++;
                elseif ($row['score'] >= $params['medium_score'])     $summaryStats['medium']++;
                elseif ($row['score'] <= $params['trap_score'])       $summaryStats['trap']++;
                else                                                   $summaryStats['low']++;
            }
        }

        $rows = $rows->sortByDesc('score')->values();

        return view($this->activeTemplate . 'user.signal-engine.index', [
            'pageTitle'      => $pageTitle,     'rows'            => $rows,
            'latestDate'     => $latestDate,    'selectedDate'    => $tradeDate,
            'availableDates' => $availableDates,'availableSymbols'=> $allSymbols,
            'selectedSymbol' => $selectedSymbol,'params'          => $params,
            'summaryStats'   => $summaryStats,  'detailMode'      => false,
            'detailData'     => [],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OVERVIEW — last-candle signal summary for one symbol
    // ══════════════════════════════════════════════════════════════════════════
    private function computeOverviewRow(string $symbol, string $tradeDate, array $p): ?array
    {
        $futCandles = $this->loadCandles($symbol, $tradeDate, 'FUT');
        if ($futCandles->isEmpty()) return null;

        $ceOIMap = $this->loadOptionOIByTime($symbol, $tradeDate, 'CE');
        $peOIMap = $this->loadOptionOIByTime($symbol, $tradeDate, 'PE');

        $futArr     = $futCandles->values()->toArray();
        $volRegime  = $this->computeVolRegime($futArr);                    // FIX-G
        $signals    = $this->buildCandleSignals($futArr, $ceOIMap, $peOIMap, $p, $volRegime);
        $lastSignal = end($signals);
        $last       = $futCandles->last();

        $score     = $lastSignal['score']     ?? 0;
        $breakdown = $lastSignal['breakdown'] ?? [];

        return [
            'symbol'             => $symbol,
            'last_time'          => $last ? substr($last->candle_time, 0, 5) : null,
            'fut_close'          => (float)($last->close  ?? 0),
            'fut_oi'             => (int)($last->oi        ?? 0),
            'fut_vol'            => (int)($last->volume    ?? 0),
            'oi_class'           => $lastSignal['oi_class']   ?? '—',
            'pcr'                => $lastSignal['pcr']         ?? null,
            'pcr_change'         => $lastSignal['pcr_change']  ?? null,
            'trend'              => $lastSignal['trend']       ?? 'FLAT',
            'vol_regime'         => $volRegime,                             // FIX-G
            'score'              => $score,
            'score_label'        => $this->scoreLabel($score, $p),
            'breakdown'          => $breakdown,
            'candles'            => count($futArr),
            'high_score_candles' => collect($signals)->filter(fn($s) => $s['score'] >= $p['high_score'])->count(),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DETAIL — per-candle breakdown + tiered backtest
    // ══════════════════════════════════════════════════════════════════════════
    private function computeDetailSignals(string $symbol, string $tradeDate, array $p): array
    {
        $futCandles = $this->loadCandles($symbol, $tradeDate, 'FUT');
        $ceOIMap    = $this->loadOptionOIByTime($symbol, $tradeDate, 'CE');
        $peOIMap    = $this->loadOptionOIByTime($symbol, $tradeDate, 'PE');

        if ($futCandles->isEmpty()) {
            return ['candles' => [], 'backtest' => [], 'symbol' => $symbol,
                    'trade_date' => $tradeDate, 'total_candles' => 0, 'vol_regime' => 'NORMAL'];
        }

        $futArr    = $futCandles->values()->toArray();
        $volRegime = $this->computeVolRegime($futArr);                     // FIX-G
        $signals   = $this->buildCandleSignals($futArr, $ceOIMap, $peOIMap, $p, $volRegime);

        $fwdN = max(1, (int)$p['forward_lookahead']);

        $withBacktest = [];
        foreach ($signals as $i => $sig) {
            $entryClose = (float)($futArr[$i]->close ?? 0);

            $sig['fwd_return_1'] = isset($futArr[$i + 1])
                ? round((((float)$futArr[$i + 1]->close - $entryClose) / max($entryClose, 0.01)) * 100, 2)
                : null;
            $sig['fwd_return_3'] = isset($futArr[$i + 3])
                ? round((((float)$futArr[$i + 3]->close - $entryClose) / max($entryClose, 0.01)) * 100, 2)
                : null;

            // MFE / MAE over dynamic $fwdN candles
            $maxHigh = $entryClose;
            $minLow  = $entryClose;
            for ($j = 1; $j <= $fwdN; $j++) {
                if (isset($futArr[$i + $j])) {
                    $maxHigh = max($maxHigh, (float)($futArr[$i + $j]->high ?? 0));
                    $minLow  = min($minLow,  (float)($futArr[$i + $j]->low  ?? 0));
                }
            }

            $sig['mfe_n'] = $entryClose > 0
                ? round((($maxHigh - $entryClose) / $entryClose) * 100, 2) : null;
            $sig['mae_n'] = $entryClose > 0
                ? round((($minLow  - $entryClose) / $entryClose) * 100, 2) : null;

            $absMfe = abs($sig['mfe_n'] ?? 0);
            $absMae = abs($sig['mae_n'] ?? 0);
            $sig['expansion_ratio'] = $absMfe > 0
                ? round($absMfe / max($absMae, 0.01), 2) : 0;

            $sig['move_quality'] = match(true) {
                $sig['mfe_n'] === null                                   => '—',
                $absMfe >= 0.8 && ($sig['expansion_ratio'] ?? 0) >= 1.5 => 'STRONG',
                $absMfe >= 0.4 && ($sig['expansion_ratio'] ?? 0) >= 1.0 => 'MODERATE',
                $absMfe >= 0.1                                           => 'WEAK',
                default                                                  => 'FLAT',
            };

            $withBacktest[] = $sig;
        }

        $backtest = $this->buildTieredBacktest($withBacktest, $p, $fwdN);

        return [
            'candles'       => $withBacktest,
            'backtest'      => $backtest,
            'symbol'        => $symbol,
            'trade_date'    => $tradeDate,
            'total_candles' => count($withBacktest),
            'vol_regime'    => $volRegime,                                 // FIX-G
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Tiered backtest: SUPER / HIGH / MEDIUM / LOW
    // FIX-F: win rate also calculated on MFE basis
    // ══════════════════════════════════════════════════════════════════════════
    private function buildTieredBacktest(array $candles, array $p, int $fwdN): array
    {
        $all = collect($candles);

        $tiers = [];
        foreach (['SUPER', 'HIGH', 'MEDIUM', 'LOW'] as $tier) {
            $subset = $all->filter(function ($s) use ($tier, $p) {
                return match ($tier) {
                    'SUPER'  => $s['score'] >= $p['super_high_score'],
                    'HIGH'   => $s['score'] >= $p['high_score']   && $s['score'] < $p['super_high_score'],
                    'MEDIUM' => $s['score'] >= $p['medium_score'] && $s['score'] < $p['high_score'],
                    'LOW'    => $s['score'] >  $p['trap_score']   && $s['score'] < $p['medium_score'],
                    default  => false,
                };
            });

            $cnt = $subset->count();
            // FIX-F: mfe_win_rate = candles where MFE > configured mfe_win_threshold
            $mfeThreshold = $p['mfe_win_threshold'] ?? 0.3;
            $tiers[$tier] = [
                'count'         => $cnt,
                'avg_fwd_1'     => $cnt > 0 ? round($subset->avg('fwd_return_1'), 3) : null,
                'avg_fwd_3'     => $cnt > 0 ? round($subset->avg('fwd_return_3'), 3) : null,
                'avg_mfe'       => $cnt > 0 ? round($subset->avg('mfe_n'), 2)        : null,
                'avg_mae'       => $cnt > 0 ? round($subset->avg('mae_n'), 2)        : null,
                'avg_expansion' => $cnt > 0 ? round($subset->avg('expansion_ratio'), 2) : null,
                'win_rate_1'    => $cnt > 0
                    ? round($subset->filter(fn($s) => ($s['fwd_return_1'] ?? 0) > 0)->count() / $cnt * 100, 1)
                    : 0,
                'win_rate_3'    => $cnt > 0
                    ? round($subset->filter(fn($s) => ($s['fwd_return_3'] ?? 0) > 0)->count() / $cnt * 100, 1)
                    : 0,
                // FIX-F: MFE-based win rate — did price actually move in our favour by threshold?
                'mfe_win_rate'  => $cnt > 0
                    ? round($subset->filter(fn($s) => ($s['mfe_n'] ?? 0) >= $mfeThreshold)->count() / $cnt * 100, 1)
                    : 0,
                'strong_moves'  => $cnt > 0
                    ? $subset->filter(fn($s) => ($s['move_quality'] ?? '') === 'STRONG')->count()
                    : 0,
                // FIX-H: Phase distribution
                'morning_count' => $cnt > 0
                    ? $subset->filter(fn($s) => ($s['session_phase'] ?? '') === 'MORNING')->count()
                    : 0,
                'lateday_count' => $cnt > 0
                    ? $subset->filter(fn($s) => ($s['session_phase'] ?? '') === 'LATEDAY')->count()
                    : 0,
            ];
        }

        return ['forward_n' => $fwdN, 'tiers' => $tiers];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX-G: Volatility Regime — today ATR vs 5-candle rolling ATR
    // Returns: 'HIGH_VOL' | 'LOW_VOL' | 'NORMAL'
    // ══════════════════════════════════════════════════════════════════════════
    private function computeVolRegime(array $candles): string
    {
        $n = count($candles);
        if ($n < 6) return 'NORMAL';

        // Full-day ATR (simple average of all candle ranges)
        $allRanges = array_map(fn($c) => max(0.0, (float)($c->high ?? 0) - (float)($c->low ?? 0)), $candles);
        $dayAtr    = array_sum($allRanges) / $n;

        // First 5 candles as baseline (opening volatility reference)
        $baseRanges = array_slice($allRanges, 0, 5);
        $baseAtr    = array_sum($baseRanges) / 5;

        if ($baseAtr <= 0) return 'NORMAL';

        $ratio = $dayAtr / $baseAtr;

        if ($ratio >= 1.4) return 'HIGH_VOL';
        if ($ratio <= 0.7) return 'LOW_VOL';
        return 'NORMAL';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CORE ENGINE — compute signal for every candle
    // ══════════════════════════════════════════════════════════════════════════
    private function buildCandleSignals(
        array $candles,
        array $ceOIMap,
        array $peOIMap,
        array $p,
        string $volRegime = 'NORMAL'     // FIX-G
    ): array {
        $results  = [];
        $n        = count($candles);
        $lookback = $p['lookback'];

        // FIX-C: rolling PCR history (keyed by loop index) to avoid time-map re-lookup
        $pcrHistory = [];

        for ($i = 0; $i < $n; $i++) {
            $c         = $candles[$i];
            $score     = 0;
            $breakdown = [];

            $currClose = (float)($c->close  ?? 0);
            $currOI    = (float)($c->oi     ?? 0);
            $currVol   = (float)($c->volume ?? 0);
            $currHigh  = (float)($c->high   ?? 0);
            $currLow   = (float)($c->low    ?? 0);
            $prevClose = $i > 0 ? (float)($candles[$i - 1]->close ?? 0) : $currClose;
            $prevOI    = $i > 0 ? (float)($candles[$i - 1]->oi    ?? 0) : $currOI;

            $pricePct = $prevClose > 0 ? (($currClose - $prevClose) / $prevClose) * 100 : 0;
            $oiPct    = $prevOI    > 0 ? (($currOI   - $prevOI)    / $prevOI)    * 100 : 0;

            // ── Rolling window (excludes current candle — FIX 2 ✓)
            $window      = array_slice($candles, max(0, $i - $lookback), $lookback);
            $avgVol      = count($window) > 0
                ? collect($window)->avg(fn($x) => (float)($x->volume ?? 0))
                : $currVol;
            $avgOIChange = $this->avgAbsOIChange($window);
            $avgRange    = count($window) > 0
                ? collect($window)->avg(fn($x) => max(0.0, (float)($x->high ?? 0) - (float)($x->low ?? 0)))
                : 0.0;

            $currRange  = $currHigh - $currLow;
            $rangeRatio = $avgRange > 0 ? round($currRange / $avgRange, 2) : 0;

            $oiClass = $this->classifyOI($pricePct, $oiPct);
            $breakdown['oi_class'] = $oiClass;

            // ── FIX-D: SMA SLOPE trend (not just price vs SMA) ────────────
            $trend = $this->computeTrendSlope($candles, $i, $p['trend_period']);
            $breakdown['trend'] = $trend;

            // ── FIX-H: Session phase ─────────────────────────────────────
            $timeKey      = substr($c->candle_time ?? '', 0, 5);
            $sessionPhase = $this->getSessionPhase($timeKey);
            $breakdown['session_phase'] = $sessionPhase;

            // ── FIX-A: Silent Accumulation — MULTI-CANDLE contraction ─────
            // Old (v2): single candle range < avgRange * compression_ratio
            //           → one small candle could trigger it anywhere.
            // New (v3): LAST 3 CANDLES must ALL be below compression_ratio threshold.
            //           Confirms a genuine multi-candle squeeze, not random noise.
            $priceFlat = abs($pricePct) < $p['price_flat_pct'];
            $oiRising  = $oiPct > $p['oi_min_pct'];
            $volAbove  = $avgVol > 0 && $currVol > ($avgVol * $p['vol_multiplier']);

            $multiCandleCompressed = false;
            if ($i >= 3 && $avgRange > 0) {
                $last3 = array_slice($candles, $i - 2, 3); // candles[i-2], [i-1], [i]
                $threshold = $avgRange * $p['compression_ratio'];
                $allNarrow = true;
                foreach ($last3 as $lc) {
                    $r = (float)($lc->high ?? 0) - (float)($lc->low ?? 0);
                    if ($r >= $threshold) { $allNarrow = false; break; }
                }
                $multiCandleCompressed = $allNarrow;
            }

            if ($priceFlat && $oiRising && $volAbove && $multiCandleCompressed) {
                $score += self::SCORE_SILENT_ACCUM;
                $breakdown['silent_accumulation'] = true;
            }

            // ── Signal: Directional Build-up ──────────────────────────────
            if ($oiClass === 'LONG_BUILDUP')  { $score += self::SCORE_LONG_BUILDUP;  $breakdown['long_buildup']  = true; }
            if ($oiClass === 'SHORT_BUILDUP') { $score += self::SCORE_SHORT_BUILDUP; $breakdown['short_buildup'] = true; }
            if ($oiClass === 'SHORT_COVER')   { $score += self::SCORE_SHORT_COVER;   $breakdown['short_cover']   = true; }
            if ($oiClass === 'LONG_UNWIND')   { $score += self::SCORE_LONG_UNWIND;   $breakdown['long_unwind']   = true; }

            // ── Signal: OI Spike ──────────────────────────────────────────
            $oiChange = abs($currOI - $prevOI);
            $oiSpiked = false;
            if ($avgOIChange > 0 && $oiChange > ($avgOIChange * $p['oi_spike_multiplier'])) {
                $score    += self::SCORE_OI_SPIKE;
                $oiSpiked  = true;
                $breakdown['oi_spike'] = round($oiChange / $avgOIChange, 1) . 'x avg';
                // FIX-G: low-vol regime reduces spike score reliability
                if ($volRegime === 'LOW_VOL') {
                    $score += self::SCORE_VOL_REGIME_CUT;
                    $breakdown['vol_regime_cut'] = true;
                }
            }

            // ── Signal: Volume Spike ──────────────────────────────────────
            if ($avgVol > 0 && $currVol > ($avgVol * $p['vol_spike_multiplier'])) {
                $score += self::SCORE_VOL_SPIKE;
                $breakdown['volume_spike'] = round($currVol / $avgVol, 1) . 'x avg';
                if ($volRegime === 'LOW_VOL') {
                    $score += self::SCORE_VOL_REGIME_CUT;
                    $breakdown['vol_regime_cut'] = true;
                }
            }

            // ── FIX-C: PCR Shift — rolling array, not time-map re-lookup ──
            // Compute current PCR and store in $pcrHistory[$i].
            // 3-candle lookback reads $pcrHistory[$i-3] directly — safe even
            // when FUT candles have gaps, because we index by loop position.
            $pcrResult = null;
            $pcrChange = null;
            $timeKeyLocal = substr($c->candle_time ?? '', 0, 5);
            if (isset($ceOIMap[$timeKeyLocal]) && isset($peOIMap[$timeKeyLocal])) {
                $ce  = max((float)$ceOIMap[$timeKeyLocal], 1.0);
                $pe  = (float)$peOIMap[$timeKeyLocal];
                $pcr = $pe / $ce;
                $pcrHistory[$i] = $pcr;

                $prevPcr   = isset($pcrHistory[$i - 3]) ? $pcrHistory[$i - 3] : $pcr;
                $pcrChange = $prevPcr > 0 ? (($pcr - $prevPcr) / $prevPcr) * 100 : 0;
                $pcrResult = ['pcr' => round($pcr, 3), 'change' => round($pcrChange, 2)];

                if (abs($pcrChange) > $p['pcr_shift_pct']) {
                    $score += self::SCORE_PCR_SHIFT;
                    $breakdown['pcr_shift'] = round($pcrChange, 1) . '%';
                }
            }

            // ── FIX-B + FIX 4: Breakout — CLOSE breaks prev 3c high/low ──
            // FIX-B: If breakout fires, we do NOT also add SCORE_RANGE_EXPAND.
            //        Range expansion is treated as part of breakout confirmation,
            //        not an independent additive signal on the same candle.
            $isBreakoutCandle = false;
            if ($i >= 3) {
                $recent3  = array_slice($candles, $i - 3, 3);
                $prevHigh = max(array_map(fn($x) => (float)($x->high ?? 0), $recent3));
                $prevLow  = min(array_map(fn($x) => (float)($x->low  ?? 0), $recent3));
                $volOk    = $avgVol > 0 && $currVol > ($avgVol * $p['vol_multiplier']);

                if ($currClose > $prevHigh && $volOk) {
                    $score += self::SCORE_BREAKOUT;
                    $isBreakoutCandle = true;
                    $breakdown['breakout'] = 'BULLISH_BO';
                    // FIX-G: high-vol regime boosts breakout confidence
                    if ($volRegime === 'HIGH_VOL') {
                        $score += self::SCORE_VOL_REGIME_BOOST;
                        $breakdown['vol_regime_boost'] = true;
                    }
                } elseif ($currClose < $prevLow && $volOk) {
                    $score += self::SCORE_BREAKOUT;
                    $isBreakoutCandle = true;
                    $breakdown['breakout'] = 'BEARISH_BO';
                    if ($volRegime === 'HIGH_VOL') {
                        $score += self::SCORE_VOL_REGIME_BOOST;
                        $breakdown['vol_regime_boost'] = true;
                    }
                }
            }

            // ── Range Expansion — FIX-B: skip on breakout candles ─────────
            if (!$isBreakoutCandle && $avgRange > 0 && $currRange > ($avgRange * $p['range_expand_multiplier'])) {
                $score += self::SCORE_RANGE_EXPAND;
                $breakdown['range_expand'] = $rangeRatio . 'x avg';
            }

            // ── FIX-D: Trend Alignment Bonus / Penalty (slope-based) ──────
            $isBullish = isset($breakdown['long_buildup'])  || isset($breakdown['short_cover'])
                || ($breakdown['breakout'] ?? '') === 'BULLISH_BO';
            $isBearish = isset($breakdown['short_buildup']) || isset($breakdown['long_unwind'])
                || ($breakdown['breakout'] ?? '') === 'BEARISH_BO';

            if      ($trend === 'UP'   && $isBullish) { $score += self::SCORE_TREND_ALIGN;   $breakdown['trend_align']   = 'WITH_UP';   }
            elseif  ($trend === 'DOWN' && $isBearish) { $score += self::SCORE_TREND_ALIGN;   $breakdown['trend_align']   = 'WITH_DOWN'; }
            elseif  ($trend === 'UP'   && $isBearish) { $score += self::SCORE_TREND_AGAINST; $breakdown['trend_against'] = true;        }
            elseif  ($trend === 'DOWN' && $isBullish) { $score += self::SCORE_TREND_AGAINST; $breakdown['trend_against'] = true;        }

            // ── FIX-E + FIX 6: Trap Detection — OI spike validated vs avg ──
            // Old (v2): prev OI spike checked as % change only.
            //           Moderate organic OI increases could trigger trap.
            // New (v3): prev OI change must ALSO exceed avgOIChange × spike multiplier.
            //           This confirms a genuine institutional-level OI event, not just activity.
            if ($i >= 2) {
                $pp  = $candles[$i - 2];
                $pv  = $candles[$i - 1];

                $prevPricePct = (float)($pp->close ?? 0) > 0
                    ? (((float)($pv->close ?? 0) - (float)($pp->close ?? 0)) / (float)($pp->close ?? 0.01)) * 100
                    : 0;

                $prevOIChange = abs((float)($pv->oi ?? 0) - (float)($pp->oi ?? 0));
                $prevWindow   = array_slice($candles, max(0, $i - $lookback - 1), $lookback);
                $prevAvgOI    = $this->avgAbsOIChange($prevWindow);

                // FIX-E: require genuine OI spike (vs avg), not just % change
                $prevHadOISpike = $prevAvgOI > 0
                    && $prevOIChange > ($prevAvgOI * $p['oi_spike_multiplier']);

                $prevOIPct = (float)($pp->oi ?? 0) > 0
                    ? (((float)($pv->oi ?? 0) - (float)($pp->oi ?? 0)) / (float)($pp->oi ?? 0.01)) * 100
                    : 0;

                if (abs($prevPricePct) > $p['trap_price_spike_pct']
                    && $prevHadOISpike                // FIX-E: genuine OI spike
                    && $prevOIPct > $p['oi_min_pct']
                    && $oiPct < 0) {
                    $score += self::SCORE_TRAP_PENALTY;
                    $breakdown['trap_warning'] = true;
                }
            }

            // ── FIX-H: Session Phase Bonus / Penalty ─────────────────────
            $hasDirectionalSignal = $isBullish || $isBearish || isset($breakdown['silent_accumulation']);
            if ($hasDirectionalSignal) {
                if ($sessionPhase === 'MORNING') {
                    $score += self::SCORE_MORNING_BONUS;
                    $breakdown['morning_bonus'] = true;
                } elseif ($sessionPhase === 'LATEDAY') {
                    $score += self::SCORE_LATEDAY_PENALTY;
                    $breakdown['lateday_penalty'] = true;
                }
            }

            $results[] = [
                'time'                 => $timeKey,
                'open'                 => (float)($c->open ?? 0),
                'high'                 => $currHigh,
                'low'                  => $currLow,
                'close'                => $currClose,
                'oi'                   => (int)$currOI,
                'volume'               => (int)$currVol,
                'price_pct'            => round($pricePct, 3),
                'oi_pct'               => round($oiPct, 2),
                'avg_vol'              => round($avgVol),
                'vol_vs_avg'           => $avgVol > 0 ? round($currVol / $avgVol, 2) : null,
                'range'                => round($currRange, 2),
                'range_ratio'          => $rangeRatio,
                'range_compressed'     => $multiCandleCompressed,     // FIX-A
                'trend'                => $trend,                     // FIX-D
                'session_phase'        => $sessionPhase,             // FIX-H
                'vol_regime'           => $volRegime,                 // FIX-G
                'oi_class'             => $oiClass,
                'score'                => $score,
                'score_label'          => $this->scoreLabel($score, $p),
                'breakdown'            => $breakdown,
                'pcr'                  => $pcrResult['pcr']    ?? null,
                'pcr_change'           => $pcrChange,
                // Filled downstream in computeDetailSignals
                'fwd_return_1'         => null,
                'fwd_return_3'         => null,
                'mfe_n'                => null,
                'mae_n'                => null,
                'expansion_ratio'      => null,
                'move_quality'         => '—',
            ];
        }

        return $results;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function classifyOI(float $pricePct, float $oiPct): string
    {
        if ($pricePct >= 0 && $oiPct >= 0) return 'LONG_BUILDUP';
        if ($pricePct <= 0 && $oiPct >= 0) return 'SHORT_BUILDUP';
        if ($pricePct >= 0 && $oiPct <= 0) return 'SHORT_COVER';
        if ($pricePct <= 0 && $oiPct <= 0) return 'LONG_UNWIND';
        return 'NEUTRAL';
    }

    /**
     * Average absolute OI change across window.
     * Window is always built from candles BEFORE index i — FIX 2 confirmed. ✓
     */
    private function avgAbsOIChange(array $window): float
    {
        if (count($window) < 2) return 0;
        $changes = [];
        for ($i = 1; $i < count($window); $i++) {
            $changes[] = abs((float)($window[$i]->oi ?? 0) - (float)($window[$i - 1]->oi ?? 0));
        }
        return count($changes) > 0 ? array_sum($changes) / count($changes) : 0;
    }

    /**
     * FIX-D: SMA SLOPE trend direction.
     *
     * Old (v2): checked price position vs SMA (price > SMA*1.001 = UP).
     *           Flat markets with a slight upward drift triggered UP constantly.
     *
     * New (v3): computes SMA at candle i, i-1, i-2 and checks the SLOPE direction.
     *           All three SMA values must agree on direction for a confirmed trend.
     *           This measures momentum, not just location.
     *
     * Returns 'UP', 'DOWN', or 'FLAT'.
     */
    private function computeTrendSlope(array $candles, int $i, int $period): string
    {
        if ($i < $period + 2) return 'FLAT';

        $smaAt = function (int $idx) use ($candles, $period): float {
            $w = array_slice($candles, $idx - $period, $period);
            return count($w) > 0 ? collect($w)->avg(fn($x) => (float)($x->close ?? 0)) : 0.0;
        };

        $s0 = $smaAt($i);
        $s1 = $smaAt($i - 1);
        $s2 = $smaAt($i - 2);

        if ($s0 <= 0 || $s1 <= 0 || $s2 <= 0) return 'FLAT';

        $rising  = $s0 > $s1 && $s1 > $s2;
        $falling = $s0 < $s1 && $s1 < $s2;

        if ($rising)  return 'UP';
        if ($falling) return 'DOWN';
        return 'FLAT';
    }

    /**
     * FIX-H: Session phase from candle time string 'HH:MM'.
     * MORNING  = 09:15 – 10:29 (first ~75 min — highest institutional activity)
     * MIDDAY   = 10:30 – 14:29
     * LATEDAY  = 14:30 – close  (expiry unwinding noise, reduced signal quality)
     */
    private function getSessionPhase(string $time): string
    {
        if (!$time || strlen($time) < 5) return 'MIDDAY';
        [$h, $m] = explode(':', $time);
        $mins = (int)$h * 60 + (int)$m;

        if ($mins < 630)  return 'MORNING'; // before 10:30
        if ($mins >= 870) return 'LATEDAY'; // 14:30+
        return 'MIDDAY';
    }

    /**
     * Load CE/PE OI keyed by 'HH:MM' candle_time — not by numeric index.
     * FIX 3: pluck('total_oi', 'candle_time') returns ['09:15' => 123456, ...]
     */
    private function loadOptionOIByTime(string $symbol, string $tradeDate, string $type): array
    {
        return OptionOhlcData::whereDate('trade_date', $tradeDate)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereNotNull('strike')
            ->select(
                DB::raw("TIME_FORMAT(interval_time, '%H:%i') as candle_time"),
                DB::raw('SUM(oi) as total_oi')
            )
            ->groupBy(DB::raw("TIME_FORMAT(interval_time, '%H:%i')"))  // ← GROUP BY expression, not alias
            ->orderBy(DB::raw("TIME_FORMAT(interval_time, '%H:%i')"))
            ->pluck('total_oi', 'candle_time')
            ->map(fn($v) => (float)$v)
            ->toArray();
    }
    /**
     * Load FUT OHLC candles ordered by time.
     */
    private function loadCandles(string $symbol, string $tradeDate, string $type)
    {
        return OptionOhlcData::whereDate('trade_date', $tradeDate)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->orderBy('interval_time')
            ->select(
                DB::raw("TIME_FORMAT(interval_time, '%H:%i') as candle_time"),
                'open', 'high', 'low', 'close', 'oi', 'volume'
            )
            ->get();
    }

    /**
     * FIX 7: 5-level scoring — SUPER / HIGH / MEDIUM / LOW / TRAP.
     */
    private function scoreLabel(int $score, array $p): string
    {
        if ($score >= $p['super_high_score']) return 'SUPER';
        if ($score >= $p['high_score'])       return 'HIGH';
        if ($score >= $p['medium_score'])     return 'MEDIUM';
        if ($score <= $p['trap_score'])       return 'TRAP';
        return 'LOW';
    }

    /**
     * All user-tunable params with sensible defaults.
     *
     * FIX 10 — Statistical validation:
     * Run across 30+ trading days before trusting any tier's win rate or expansion ratio.
     * 1-day backtest is anecdote, not evidence.
     */
    private function getParams(Request $r): array
    {
        return [
            // ── Silent Accumulation (FIX-A: multi-candle) ────────────────────
            'price_flat_pct'          => (float) $r->get('price_flat_pct',            0.5),
            'oi_min_pct'              => (float) $r->get('oi_min_pct',                3.0),
            'vol_multiplier'          => (float) $r->get('vol_multiplier',            1.3),
            'compression_ratio'       => (float) $r->get('compression_ratio',         0.7),  // all 3 candles must be below N× avg

            // ── Spike detection ───────────────────────────────────────────────
            'oi_spike_multiplier'     => (float) $r->get('oi_spike_multiplier',       2.0),
            'vol_spike_multiplier'    => (float) $r->get('vol_spike_multiplier',      1.8),

            // ── Range expansion (FIX-B: skipped on breakout candles) ─────────
            'range_expand_multiplier' => (float) $r->get('range_expand_multiplier',   1.8),

            // ── PCR (FIX-C: rolling array now) ───────────────────────────────
            'pcr_shift_pct'           => (float) $r->get('pcr_shift_pct',            15.0),

            // ── Trap (FIX-E: OI spike validated vs avg) ───────────────────────
            'trap_price_spike_pct'    => (float) $r->get('trap_price_spike_pct',      1.0),

            // ── Trend (FIX-D: slope-based) ────────────────────────────────────
            'trend_period'            => (int)   $r->get('trend_period',               10),

            // ── Session (FIX-H) ───────────────────────────────────────────────
            'morning_cutoff'          => $r->get('morning_cutoff', '10:30'),   // HH:MM
            'lateday_cutoff'          => $r->get('lateday_cutoff', '14:30'),   // HH:MM

            // ── Lookback / forward ────────────────────────────────────────────
            'lookback'                => (int)   $r->get('lookback',                    5),
            'forward_lookahead'       => (int)   $r->get('forward_lookahead',           3),

            // ── Backtest (FIX-F: mfe win threshold) ──────────────────────────
            'mfe_win_threshold'       => (float) $r->get('mfe_win_threshold',          0.3), // % MFE = win

            // ── Score tiers ───────────────────────────────────────────────────
            'super_high_score'        => (int)   $r->get('super_high_score',            9),
            'high_score'              => (int)   $r->get('high_score',                  6),
            'medium_score'            => (int)   $r->get('medium_score',                3),
            'trap_score'              => (int)   $r->get('trap_score',                  0),
        ];
    }

    private function emptyView(string $pageTitle, array $params)
    {
        return view($this->activeTemplate . 'user.signal-engine.index', [
            'pageTitle'      => $pageTitle,  'rows'            => collect(),
            'latestDate'     => null,        'selectedDate'    => null,
            'availableDates' => collect(),   'availableSymbols'=> collect(),
            'selectedSymbol' => '',          'params'          => $params,
            'summaryStats'   => [],          'detailMode'      => false,
            'detailData'     => [],
        ]);
    }
}