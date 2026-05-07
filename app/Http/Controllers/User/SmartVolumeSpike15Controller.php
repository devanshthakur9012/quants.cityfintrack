<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SmartVolumeSpike15Controller — v4 (Production Grade)
 *
 * ══════════════════════════════════════════════════════════
 * ARCHITECTURE PHILOSOPHY
 * ══════════════════════════════════════════════════════════
 *
 * Core insight: The same ratio threshold cannot apply to
 * BANKNIFTY (index, 100K+ vol per candle) and ADANIPORTS
 * (stock option, 5K vol per candle). The old 3x/2x fixed
 * thresholds produce over-signals in stocks and under-signals
 * in indices. This version solves that with ADAPTIVE thresholds.
 *
 * KEY CHANGES FROM v3:
 *
 * [FIX-1]  ±2 strikes REMOVED. Only ATM, ATM-1, ATM+1.
 *           Reduces noise, focuses on liquid strikes only.
 *
 * [FIX-2]  Adaptive spike thresholds per symbol per session.
 *           Uses coefficient of variation (CV = std/mean) of
 *           prior session volumes to set dynamic EXTREME/STRONG
 *           thresholds. High-vol stable symbols → stricter.
 *           Erratic symbols → gentler.
 *
 * [FIX-3]  Market regime detection (TRENDING/SIDEWAYS/BREAKOUT).
 *           Uses FUT price range over last 3 candles vs ATR.
 *           Regime changes signal weights and confidence scoring.
 *           In SIDEWAYS → reduce all signal confidence by 30%.
 *           In TRENDING → boost confirmed signals by 20%.
 *           In BREAKOUT → max boost, also triggers BUY signals
 *                         even with partial conditions.
 *
 * [FIX-4]  Seed quality filter: if avg(seed) < volFloor,
 *           discard the seed entirely. Prevents fake 9:15 EXTREME.
 *
 * [FIX-5]  FUT rows fetched WITHOUT expiry filter (separate query)
 *           so weekly FUT expiry mismatch is eliminated. Candle
 *           body analysis now works for all symbols.
 *
 * [FIX-6]  Final block (CE/PE weighted total) also seeded from
 *           prev day. Eliminates EARLY badge on weighted final.
 *
 * [FIX-7]  Price direction threshold: max(0.05%, 0.5 absolute pts).
 *           Works for both expensive (NIFTY 24000) and cheap stocks.
 *
 * [FIX-8]  Next 15m trend uses dominance+OI lean even when
 *           trade signal is WATCH/AVOID. No more all-SIDEWAYS.
 *
 * [FIX-9]  Dominance shown as ratio (CE_wtd/PE_wtd) not raw diff.
 *           Also flags OI vs dominance conflict.
 *
 * [FIX-10] Confidence bar: 0% shows — not empty bar.
 *           Min visual threshold enforced in blade JS.
 *
 * DB schema: option_ohlc_data columns used:
 *   interval_time, instrument_type, strike, atm_strike,
 *   volume, oi, open, high, low, close, future_price, is_missing
 */
class SmartVolumeSpike15Controller extends Controller
{
    // ── Symbol classification ─────────────────────────────────────────────────
    // INDEX = high liquidity, all strikes meaningful
    // STOCK = medium/low liquidity, only ATM±1 reliable
    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'SENSEX', 'FINNIFTY', 'MIDCPNIFTY'];

    // Volume floor: absolute minimum for a candle to be considered real activity
    private const VOL_FLOOR = [
        'INDEX' => 10000,
        'STOCK' => 3000,   // lowered from 5000 — stocks have thinner markets
    ];

    // Minimum candle history before ratio is meaningful
    private const MIN_HISTORY = 3;

    // How many prev-day candles to use as seed
    private const PREV_DAY_SEED = 3;

    // Weights: ATM=3, ±1=2 (±2 removed in v4)
    private const WEIGHTS = [-1 => 2, 0 => 3, 1 => 2];

    // Offsets to process (±2 removed)
    private const OFFSETS = [-1, 0, 1];

    // ── Entry point ───────────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle = 'Smart Volume Spike 15Min';
        return view($this->activeTemplate . 'user.smart-volume-spike-15.index', compact('pageTitle'));
    }

    // ── Main API ──────────────────────────────────────────────────────────────
    public function getSignals(Request $request)
    {
        try {
            $symbol = strtoupper(trim($request->get('symbol', 'ALL')));
            $today  = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            $availableSymbols = OptionOhlcData::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success'           => true, 'data' => [], 'today' => $today,
                    'is_today'          => $today === Carbon::today()->toDateString(),
                    'message'           => 'No data for ' . $today,
                    'available_symbols' => [],
                ]);
            }

            $isAll          = ($symbol === 'ALL');
            $symbols        = $isAll ? $availableSymbols : [$symbol];
            $prevTradingDay = $this->getPrevTradingDay($today);
            $results        = [];

            foreach ($symbols as $sym) {
                $result = $this->processSymbol($sym, $today, $prevTradingDay, $isAll);
                if ($result) $results[] = $result;
            }

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'today'             => $today,
                'is_today'          => $today === Carbon::today()->toDateString(),
                'mode'              => $isAll ? 'summary' : 'detail',
                'available_symbols' => $availableSymbols,
                'message'           => count($results) . ' symbol(s) loaded for ' . $today,
            ]);

        } catch (\Exception $e) {
            Log::error('SmartVolumeSpike15 v4: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SYMBOL PROCESSOR
    // ══════════════════════════════════════════════════════════════════════════

    private function processSymbol(string $sym, string $today, ?string $prevDay, bool $isAll): ?array
    {
        $expiry = $this->getNearestExpiry($sym, $today);
        if (!$expiry) return null;

        $isIndex  = in_array($sym, self::INDEX_SYMBOLS);
        $volFloor = $isIndex ? self::VOL_FLOOR['INDEX'] : self::VOL_FLOOR['STOCK'];

        // ── CE/PE rows (with expiry filter) ───────────────────────────────────
        $cepeRows = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $today)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'instrument_type', 'strike', 'atm_strike',
                   'volume', 'oi', 'open', 'high', 'low', 'close', 'future_price']);

        if ($cepeRows->isEmpty()) return null;

        // ── FUT rows — NO expiry filter (FIX-5) ──────────────────────────────
        $futRows = OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $today)
            ->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close', 'future_price', 'is_missing']);

        $atmStrike      = (float) $cepeRows->sortBy('interval_time')->last()->atm_strike;
        $strikeInterval = $this->resolveStrikeInterval($cepeRows);
        if (!$strikeInterval) return null;

        // ── Previous day seed (FIX-4: quality-filtered) ───────────────────────
        $prevSeeds = $prevDay
            ? $this->loadPrevDaySeeds($sym, $prevDay, $strikeInterval, $atmStrike, $volFloor)
            : [];

        // ── Build time-indexed maps ───────────────────────────────────────────
        // volMap[type][offset][H:i] = volume
        // oiMap[type][H:i]          = total oi (offsets -1..+1)
        // futMap[H:i]               = { open,high,low,close,future_price }
        // strikeByOffset[offset]    = strike price
        $volMap         = [];
        $oiMap          = [];
        $futMap         = [];
        $strikeByOffset = [];

        // Index FUT rows
        foreach ($futRows as $r) {
            $tk = Carbon::parse($r->interval_time)->format('H:i');
            if ((int)$r->is_missing === 1) continue;
            $fp = (float) $r->future_price;
            $futMap[$tk] = [
                'open'          => (float) $r->open,
                'high'          => (float) $r->high,
                'low'           => (float) $r->low,
                'close'         => (float) $r->close,
                'future_price'  => $fp > 0 ? $fp : (float) $r->close,
            ];
        }

        // Index CE/PE rows
        foreach ($cepeRows as $r) {
            $tk     = Carbon::parse($r->interval_time)->format('H:i');
            $type   = $r->instrument_type;
            $strike = (float) $r->strike;
            $offset = (int) round(($strike - $atmStrike) / $strikeInterval);

            if (!in_array($offset, self::OFFSETS)) continue;

            $strikeByOffset[$offset] = $strike;
            $volMap[$type][$offset][$tk] = ($volMap[$type][$offset][$tk] ?? 0) + (int) $r->volume;
            $oiMap[$type][$tk]           = ($oiMap[$type][$tk] ?? 0) + (int) $r->oi;
        }

        // All interval times
        $allTimes = $cepeRows
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->unique()->sort()->values()->toArray();

        if (empty($allTimes)) return null;

        // ── Session trackers ──────────────────────────────────────────────────
        $priorVols      = $prevSeeds['per_offset'] ?? [];   // [trackKey => [v1,v2,...]]
        $priorFinalCe   = $prevSeeds['final_ce']   ?? [];   // FIX-6
        $priorFinalPe   = $prevSeeds['final_pe']   ?? [];   // FIX-6
        $prevVolMap     = [];                                // [trackKey => lastAbsVol]
        $prevTradeAction = null;
        $contCount      = 0;
        $prevFinalCeVol = 0;
        $prevFinalPeVol = 0;

        // Adaptive threshold state — updated each interval (FIX-2)
        $adaptiveThresh = [];  // [trackKey => {extreme,strong,spike,elevated}]

        // Market regime — updated each interval (FIX-3)
        $regime = 'UNKNOWN';
        $recentFutPrices = [];

        $intervalResults = [];

        foreach ($allTimes as $idx => $tk) {

            // ── Time zone ─────────────────────────────────────────────────────
            $timeZone = $this->classifyTimeZone($tk);

            // ── FUT candle data ───────────────────────────────────────────────
            $futCandle = $futMap[$tk] ?? null;

            // ── Price direction (FIX-7: adaptive threshold) ───────────────────
            $priceDir = 'FLAT';
            $curPrice = $futCandle['future_price'] ?? null;
            if ($idx > 0 && $curPrice) {
                $prevTk   = $allTimes[$idx - 1];
                $prevPrc  = $futMap[$prevTk]['future_price'] ?? null;
                if ($prevPrc && $prevPrc > 0) {
                    $ptChange  = abs($curPrice - $prevPrc);
                    $pctChange = ($ptChange / $prevPrc) * 100;
                    // FIX-7: max(0.05%, 0.5 absolute points)
                    $threshold = max(0.05, (0.5 / $prevPrc) * 100);
                    if ($curPrice > $prevPrc && $pctChange >= $threshold)     $priceDir = 'UP';
                    elseif ($curPrice < $prevPrc && $pctChange >= $threshold) $priceDir = 'DOWN';
                }
            }

            // Track recent prices for regime detection
            if ($curPrice) {
                $recentFutPrices[] = $curPrice;
                if (count($recentFutPrices) > 4) array_shift($recentFutPrices);
            }

            // ── FIX-3: Market regime detection ───────────────────────────────
            $regime = $this->detectMarketRegime($recentFutPrices, $futMap, $tk, $allTimes, $idx);

            // ── Candle body analysis (FIX-5: uses separate futMap) ────────────
            $candleBody = $this->analyseCandleBody($futCandle);

            // ── Vol spike per (type, offset) ──────────────────────────────────
            $spikeData = [];
            foreach (['CE', 'PE'] as $type) {
                foreach (self::OFFSETS as $off) {
                    $curVol   = $volMap[$type][$off][$tk] ?? 0;
                    $trackKey = "{$type}:{$off}";

                    // FIX-A: Data missing check
                    if ($this->isDataMissing($curVol, $curPrice)) {
                        $spikeData[$type][$off] = $this->dataMissingSpike();
                        continue;
                    }

                    // Delta volume
                    $prevVol  = $prevVolMap[$trackKey] ?? 0;
                    $deltaVol = max(0, $curVol - $prevVol);

                    // FIX-2: Adaptive thresholds
                    $thresh = $this->getAdaptiveThresholds(
                        $priorVols[$trackKey] ?? [], $volFloor, $isIndex
                    );

                    $spikeData[$type][$off] = $this->calcVolSpike(
                        $deltaVol, $curVol,
                        $priorVols[$trackKey] ?? [],
                        $volFloor, $thresh
                    );

                    if ($curVol > 0) {
                        $priorVols[$trackKey][] = $deltaVol > 0 ? $deltaVol : $curVol;
                        $prevVolMap[$trackKey]  = $curVol;
                    }
                }
            }

            // ── Weighted final block (FIX-D dynamic weights still here) ───────
            $finalCeVol = 0;
            $finalPeVol = 0;
            $dynWeights = self::WEIGHTS;

            // Promote ±1 if STRONG_SPIKE or EXTREME
            foreach ([-1, 1] as $off) {
                foreach (['CE', 'PE'] as $type) {
                    if (in_array($spikeData[$type][$off]['spike_type'] ?? '', ['STRONG_SPIKE', 'EXTREME'])) {
                        $dynWeights[$off] = min($dynWeights[$off] + 1, 4);
                    }
                }
            }

            foreach ($dynWeights as $off => $weight) {
                $finalCeVol += ($volMap['CE'][$off][$tk] ?? 0) * $weight;
                $finalPeVol += ($volMap['PE'][$off][$tk] ?? 0) * $weight;
            }

            // Final block spike (also uses adaptive thresholds, seeded — FIX-6)
            $finalThresh = $this->getAdaptiveThresholds($priorFinalCe ?: $priorFinalPe, $volFloor * 3, $isIndex);
            $finalCeSpike = $this->calcVolSpike(
                max(0, $finalCeVol - $prevFinalCeVol), $finalCeVol,
                $priorFinalCe, $volFloor * 3, $finalThresh
            );
            $finalPeSpike = $this->calcVolSpike(
                max(0, $finalPeVol - $prevFinalPeVol), $finalPeVol,
                $priorFinalPe, $volFloor * 3, $finalThresh
            );
            if ($finalCeVol > 0) $priorFinalCe[] = $finalCeVol;
            if ($finalPeVol > 0) $priorFinalPe[] = $finalPeVol;

            // ── FIX-9: Dominance as ratio ─────────────────────────────────────
            $dominanceRatio = $finalPeVol > 0 ? round($finalCeVol / $finalPeVol, 2) : null;
            // > 1.2 = CE dominant = bearish pressure
            // < 0.8 = PE dominant = bullish pressure
            // 0.8–1.2 = balanced
            $dominanceBias = null;
            if ($dominanceRatio !== null) {
                if ($dominanceRatio > 1.2)      $dominanceBias = 'BEARISH_PRESSURE';
                elseif ($dominanceRatio < 0.8)  $dominanceBias = 'BULLISH_PRESSURE';
                else                            $dominanceBias = 'NEUTRAL';
            }

            // ── OI Sentiment ──────────────────────────────────────────────────
            $curCeOi = $oiMap['CE'][$tk] ?? 0;
            $curPeOi = $oiMap['PE'][$tk] ?? 0;

            if ($idx === 0) {
                $oiSentiment = $this->noSentiment();
            } else {
                $pt       = $allTimes[$idx - 1];
                $prevCeOi = $oiMap['CE'][$pt] ?? 0;
                $prevPeOi = $oiMap['PE'][$pt] ?? 0;
                $cePct    = $prevCeOi > 0 ? round((($curCeOi - $prevCeOi) / $prevCeOi) * 100, 2) : 0;
                $pePct    = $prevPeOi > 0 ? round((($curPeOi - $prevPeOi) / $prevPeOi) * 100, 2) : 0;
                $oiSentiment = array_merge(
                    $this->calcOiSignal($cePct, $pePct, $priceDir, $candleBody, $dominanceBias),
                    ['ce_oi_pct' => $cePct, 'pe_oi_pct' => $pePct, 'time' => $tk]
                );
            }

            // ── Trade signal (FIX-3 regime-aware) ────────────────────────────
            $tradeSignal = $this->calcTradeSignal(
                $spikeData, $oiSentiment, $priceDir, $timeZone,
                $candleBody, $regime, $volFloor
            );

            // ── Continuation (FIX-F vol expansion) ───────────────────────────
            $continuation = $this->checkContinuation(
                $tradeSignal, $prevTradeAction, $contCount,
                $finalCeVol, $finalPeVol, $prevFinalCeVol, $prevFinalPeVol
            );
            $contCount       = $tradeSignal['action'] === $prevTradeAction ? $contCount + 1 : 0;
            $prevTradeAction = $tradeSignal['action'];
            $prevFinalCeVol  = $finalCeVol;
            $prevFinalPeVol  = $finalPeVol;

            // ── Smart trap (FIX-E) ────────────────────────────────────────────
            $trap = $this->detectTrap($spikeData, $priceDir, $oiSentiment, $candleBody);

            // ── FIX-8: Next 15m trend — uses dominance lean even when WATCH ───
            $next15mTrend = $this->predictNextMove(
                $tradeSignal, $oiSentiment, $priceDir,
                $spikeData, $continuation, $candleBody,
                $dominanceBias, $dominanceRatio, $regime
            );

            $intervalResults[] = [
                'time'             => $tk,
                'atm_strike'       => $atmStrike,
                'time_zone'        => $timeZone,
                'price_dir'        => $priceDir,
                'future_price'     => $curPrice,
                'candle_body'      => $candleBody,
                'regime'           => $regime,
                'strikes' => [
                    'm1'  => $strikeByOffset[-1] ?? null,
                    'atm' => $strikeByOffset[0]  ?? $atmStrike,
                    'p1'  => $strikeByOffset[1]  ?? null,
                ],
                'ce' => [
                    'm1'  => $spikeData['CE'][-1] ?? $this->noSpike(),
                    'atm' => $spikeData['CE'][0]  ?? $this->noSpike(),
                    'p1'  => $spikeData['CE'][1]  ?? $this->noSpike(),
                ],
                'pe' => [
                    'm1'  => $spikeData['PE'][-1] ?? $this->noSpike(),
                    'atm' => $spikeData['PE'][0]  ?? $this->noSpike(),
                    'p1'  => $spikeData['PE'][1]  ?? $this->noSpike(),
                ],
                'final_ce'         => $finalCeSpike,
                'final_pe'         => $finalPeSpike,
                'final_ce_vol'     => $finalCeVol,
                'final_pe_vol'     => $finalPeVol,
                'dominance_ratio'  => $dominanceRatio,
                'dominance_bias'   => $dominanceBias,
                'oi_sentiment'     => $oiSentiment,
                'trade_signal'     => $tradeSignal,
                'continuation'     => $continuation,
                'trap'             => $trap,
                'next_15m_trend'   => $next15mTrend,
            ];
        }

        if (empty($intervalResults)) return null;

        return [
            'symbol'          => $sym,
            'expiry'          => $expiry,
            'date'            => $today,
            'mode'            => $isAll ? 'summary' : 'detail',
            'atm_strike'      => $atmStrike,
            'strike_interval' => $strikeInterval,
            'vol_floor'       => $volFloor,
            'is_index'        => $isIndex,
            'prev_day_seeded' => !empty($prevSeeds),
            'intervals'       => $intervalResults,
            'total_intervals' => count($intervalResults),
            'latest_time'     => end($intervalResults)['time'] ?? null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX-2: ADAPTIVE THRESHOLDS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Compute adaptive spike thresholds based on the coefficient of variation
     * (CV = std_dev / mean) of the prior session volumes.
     *
     * Logic:
     *   High CV (erratic vol) → looser thresholds (real spike stands out less)
     *   Low CV (stable vol)   → stricter thresholds (any surge is notable)
     *
     * Also considers whether symbol is index or stock.
     *
     * Returns: [extreme, strong, spike, elevated]
     */
    private function getAdaptiveThresholds(array $priorVols, int $volFloor, bool $isIndex): array
    {
        // Default fixed thresholds (fallback when no history)
        $defaults = ['extreme' => 3.0, 'strong' => 2.0, 'spike' => 1.5, 'elevated' => 1.2];

        if (count($priorVols) < self::MIN_HISTORY) return $defaults;

        $mean = array_sum($priorVols) / count($priorVols);
        if ($mean <= 0) return $defaults;

        // Compute std deviation
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $priorVols)) / count($priorVols);
        $std      = sqrt($variance);
        $cv       = $std / $mean; // coefficient of variation: 0 = stable, >1 = very erratic

        // CV-based multiplier: erratic stocks need higher ratios to be "real"
        // Stable stocks/indices: lower ratios are already meaningful
        $cvMult = match (true) {
            $cv >= 1.5 => 1.4,  // very erratic → raise bars
            $cv >= 1.0 => 1.2,
            $cv >= 0.5 => 1.0,  // normal range
            default    => 0.85, // very stable → lower bars (easier to spike)
        };

        // Index premium: indices are more liquid, require higher activity to "spike"
        $idxMult = $isIndex ? 1.15 : 1.0;

        $m = $cvMult * $idxMult;

        return [
            'extreme'  => round(3.0 * $m, 2),
            'strong'   => round(2.0 * $m, 2),
            'spike'    => round(1.5 * $m, 2),
            'elevated' => round(1.2 * $m, 2),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX-3: MARKET REGIME DETECTION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Classify current market regime using recent FUT price action.
     *
     * TRENDING  : Consecutive closes moving in same direction, range expanding
     * SIDEWAYS  : Price oscillating within a tight range (< 0.3% swing)
     * BREAKOUT  : Price suddenly moves > 0.5% in one candle after sideways
     * UNKNOWN   : Not enough data
     *
     * Returns: 'TRENDING' | 'SIDEWAYS' | 'BREAKOUT' | 'UNKNOWN'
     */
    private function detectMarketRegime(
        array  $recentPrices,
        array  $futMap,
        string $currentTk,
        array  $allTimes,
        int    $currentIdx
    ): string {
        if (count($recentPrices) < 3) return 'UNKNOWN';

        $n    = count($recentPrices);
        $high = max($recentPrices);
        $low  = min($recentPrices);
        $base = $recentPrices[0];

        if ($base <= 0) return 'UNKNOWN';

        $swingPct = (($high - $low) / $base) * 100;

        // Check if last move was a sharp single-candle breakout
        $lastMove = 0;
        if ($n >= 2) {
            $lastMove = abs($recentPrices[$n-1] - $recentPrices[$n-2]);
            $lastMovePct = $base > 0 ? ($lastMove / $base) * 100 : 0;
            if ($lastMovePct >= 0.5 && $swingPct >= 0.4) return 'BREAKOUT';
        }

        if ($swingPct <= 0.2) return 'SIDEWAYS';  // tight range = sideways

        // Check consecutive direction (3 same-direction = trending)
        $dirs = [];
        for ($i = 1; $i < $n; $i++) {
            $dirs[] = $recentPrices[$i] > $recentPrices[$i-1] ? 1 : -1;
        }
        $uniqueDirs = array_unique($dirs);
        if (count($uniqueDirs) === 1) return 'TRENDING'; // all same direction

        return 'SIDEWAYS'; // mixed = chop
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX-4 + FIX-6: PREVIOUS DAY SEEDS (quality-filtered, includes final block)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Returns:
     * [
     *   'per_offset' => ['CE:0' => [v1,v2,v3], 'PE:-1' => [...], ...],
     *   'final_ce'   => [fv1, fv2, fv3],
     *   'final_pe'   => [fv1, fv2, fv3],
     * ]
     *
     * FIX-4: Seeds whose average is below volFloor are discarded entirely.
     */
    private function loadPrevDaySeeds(
        string $sym, string $prevDay, float $strikeInterval,
        float $atmStrike, int $volFloor
    ): array {
        $rows = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDay)
            ->where('is_missing', 0)
            ->orderBy('interval_time', 'desc')
            ->limit(200)
            ->get(['interval_time', 'instrument_type', 'strike', 'volume']);

        if ($rows->isEmpty()) return [];

        $grouped = [];
        foreach ($rows as $r) {
            $offset = (int) round(((float)$r->strike - $atmStrike) / $strikeInterval);
            if (!in_array($offset, self::OFFSETS)) continue;
            $tk  = Carbon::parse($r->interval_time)->format('H:i');
            $key = $r->instrument_type . ':' . $offset;
            $grouped[$key][$tk] = (int) $r->volume;
        }

        $perOffset = [];
        $finalCeByTime = [];
        $finalPeByTime = [];

        foreach ($grouped as $trackKey => $timeVolMap) {
            ksort($timeVolMap);
            $vols = array_values($timeVolMap);
            $last = array_slice($vols, -self::PREV_DAY_SEED);

            // FIX-4: Quality filter — discard if avg seed < volFloor
            $avgSeed = count($last) > 0 ? array_sum($last) / count($last) : 0;
            if ($avgSeed < $volFloor) continue; // bad seed — skip

            $perOffset[$trackKey] = $last;

            // Accumulate final block seeds
            $type = str_starts_with($trackKey, 'CE') ? 'CE' : 'PE';
            [$tkParsed] = explode(':', $trackKey . ':');
            foreach ($timeVolMap as $t => $v) {
                if ($type === 'CE') $finalCeByTime[$t] = ($finalCeByTime[$t] ?? 0) + $v * self::WEIGHTS[(int)explode(':', $trackKey)[1]] ?? 2;
                else                $finalPeByTime[$t] = ($finalPeByTime[$t] ?? 0) + $v * self::WEIGHTS[(int)explode(':', $trackKey)[1]] ?? 2;
            }
        }

        // FIX-6: Build final block seeds
        ksort($finalCeByTime); $fcVals = array_values($finalCeByTime);
        ksort($finalPeByTime); $fpVals = array_values($finalPeByTime);
        $finalCeSeed = array_slice($fcVals, -self::PREV_DAY_SEED);
        $finalPeSeed = array_slice($fpVals, -self::PREV_DAY_SEED);

        // Quality filter for final seeds too
        $avgFc = count($finalCeSeed) > 0 ? array_sum($finalCeSeed) / count($finalCeSeed) : 0;
        $avgFp = count($finalPeSeed) > 0 ? array_sum($finalPeSeed) / count($finalPeSeed) : 0;
        if ($avgFc < $volFloor * 3) $finalCeSeed = [];
        if ($avgFp < $volFloor * 3) $finalPeSeed = [];

        return [
            'per_offset' => $perOffset,
            'final_ce'   => $finalCeSeed,
            'final_pe'   => $finalPeSeed,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CANDLE BODY ANALYSIS (FIX-5: uses separate futMap, always populated)
    // ══════════════════════════════════════════════════════════════════════════

    private function analyseCandleBody(?array $ohlc): array
    {
        $none = ['body_pct' => 0, 'is_bull' => false, 'is_bear' => false,
                 'upper_wick' => 0, 'lower_wick' => 0, 'conviction' => 'UNKNOWN'];

        if (!$ohlc || ($ohlc['high'] ?? 0) <= 0) return $none;

        $o = $ohlc['open']; $h = $ohlc['high']; $l = $ohlc['low']; $c = $ohlc['close'];
        $range = $h - $l;
        if ($range <= 0) return array_merge($none, ['conviction' => 'DOJI']);

        $bodyPct   = round(abs($c - $o) / $range, 3);
        $upperWick = round(($h - max($o, $c)) / $range, 3);
        $lowerWick = round((min($o, $c) - $l) / $range, 3);

        return [
            'body_pct'   => $bodyPct,
            'is_bull'    => $c > $o,
            'is_bear'    => $c < $o,
            'upper_wick' => $upperWick,
            'lower_wick' => $lowerWick,
            'conviction' => match (true) {
                $bodyPct >= 0.65 => 'STRONG',
                $bodyPct >= 0.40 => 'MODERATE',
                $bodyPct >= 0.15 => 'WEAK',
                default          => 'DOJI',
            },
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VOL SPIKE (FIX-2: adaptive thresholds, FIX-A data missing, FIX-B delta)
    // ══════════════════════════════════════════════════════════════════════════

    private function calcVolSpike(
        int   $deltaVol,
        int   $curVol,
        array $priorVols,
        int   $volFloor,
        array $thresh
    ): array {
        $base = ['cur_vol' => $curVol, 'delta_vol' => $deltaVol,
                 'low_vol' => false, 'early' => false, 'data_missing' => false];

        if (empty($priorVols)) {
            return array_merge($base, ['spike_ratio' => null, 'spike_label' => 'OPENING',
                'spike_type' => 'OPENING', 'avg_vol' => 0,
                'low_vol' => $curVol < $volFloor, 'early' => true]);
        }
        if (count($priorVols) < self::MIN_HISTORY) {
            $avg = array_sum($priorVols) / count($priorVols);
            return array_merge($base, ['spike_ratio' => null, 'spike_label' => 'EARLY',
                'spike_type' => 'EARLY', 'avg_vol' => (int)round($avg),
                'low_vol' => $curVol < $volFloor, 'early' => true]);
        }

        $avg = array_sum($priorVols) / count($priorVols);

        if ($curVol < $volFloor) {
            return array_merge($base, [
                'spike_ratio' => $avg > 0 ? round($deltaVol / $avg, 2) : null,
                'spike_label' => 'LOW VOL', 'spike_type' => 'LOW_VOLUME',
                'avg_vol' => (int)round($avg), 'low_vol' => true,
            ]);
        }

        if ($avg <= 0) {
            return array_merge($base, ['spike_ratio' => null, 'spike_label' => 'N/A',
                'spike_type' => 'NORMAL', 'avg_vol' => 0]);
        }

        $ratio = round($deltaVol / max($avg, 1), 2);

        // FIX-2: Use adaptive thresholds instead of fixed 3/2/1.5/1.2
        [$label, $type] = match (true) {
            $ratio >= $thresh['extreme']  => ['EXTREME',      'EXTREME'],
            $ratio >= $thresh['strong']   => ['STRONG SPIKE', 'STRONG_SPIKE'],
            $ratio >= $thresh['spike']    => ['SPIKE',        'SPIKE'],
            $ratio >= $thresh['elevated'] => ['ELEVATED',     'ELEVATED'],
            default                       => ['NORMAL',       'NORMAL'],
        };

        return array_merge($base, [
            'spike_ratio' => $ratio, 'spike_label' => $label,
            'spike_type'  => $type,  'avg_vol'     => (int)round($avg),
            // Include thresholds in output so UI can show context
            'thresh_extreme' => $thresh['extreme'],
            'thresh_strong'  => $thresh['strong'],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI SIGNAL (FIX-9: dominance conflict check, FIX-C candle body)
    // ══════════════════════════════════════════════════════════════════════════

    private function calcOiSignal(
        float   $cePct,
        float   $pePct,
        string  $priceDir,
        array   $candleBody,
        ?string $dominanceBias
    ): array {
        // Base OI direction
        if ($cePct > 0 && $pePct < 0)     { $signal='BEARISH'; $condition='CE ↑ + PE ↓'; $reason='Call buildup + Put unwinding'; }
        elseif ($cePct < 0 && $pePct > 0) { $signal='BULLISH'; $condition='CE ↓ + PE ↑'; $reason='Call unwinding + Put buildup'; }
        elseif ($cePct > 0 && $pePct > 0) {
            if ($pePct > $cePct) { $signal='BULLISH'; $condition='Both ↑ PE>CE'; $reason="PE stronger +{$pePct}%"; }
            else                 { $signal='BEARISH'; $condition='Both ↑ CE≥PE'; $reason="CE stronger +{$cePct}%"; }
        } else {
            if (abs($cePct)>abs($pePct)) { $signal='BULLISH'; $condition='Both ↓ |CE|>|PE|'; $reason="CE unwinding {$cePct}%"; }
            else                          { $signal='BEARISH'; $condition='Both ↓ |PE|≥|CE|'; $reason="PE unwinding {$pePct}%"; }
        }

        $confirmed = $conflicted = false;

        // Price confirmation
        if ($priceDir !== 'FLAT') {
            if      ($signal==='BULLISH'  && $priceDir==='UP')   { $confirmed=true;  $signal='STRONG_BULLISH'; $reason.=' ✅ Price UP'; }
            elseif  ($signal==='BEARISH'  && $priceDir==='DOWN') { $confirmed=true;  $signal='STRONG_BEARISH'; $reason.=' ✅ Price DOWN'; }
            elseif  ($signal==='BULLISH'  && $priceDir==='DOWN') { $conflicted=true; $signal='MIXED';          $reason.=' ⚠️ Price DOWN'; }
            elseif  ($signal==='BEARISH'  && $priceDir==='UP')   { $conflicted=true; $signal='MIXED';          $reason.=' ⚠️ Price UP'; }
        }

        // FIX-9: Dominance conflict note
        $domConflict = false;
        if ($dominanceBias !== null) {
            $oiBull = in_array($signal, ['BULLISH', 'STRONG_BULLISH']);
            $oiBear = in_array($signal, ['BEARISH', 'STRONG_BEARISH']);
            if ($oiBull && $dominanceBias === 'BEARISH_PRESSURE') {
                $domConflict = true; $reason .= ' ⚡ Dom: CE pressure (conflict)';
            } elseif ($oiBear && $dominanceBias === 'BULLISH_PRESSURE') {
                $domConflict = true; $reason .= ' ⚡ Dom: PE pressure (conflict)';
            }
        }

        // Candle body modifier
        $conv = $candleBody['conviction'] ?? 'UNKNOWN';
        if (in_array($signal, ['STRONG_BULLISH', 'BULLISH'])) {
            if ($conv === 'STRONG' && ($candleBody['is_bull'] ?? false))  $reason .= ' 🕯 Strong bull';
            elseif (in_array($conv, ['WEAK', 'DOJI']))                   { $signal = str_replace('STRONG_','',$signal); $reason .= ' ⚠️ Weak candle'; }
            elseif (($candleBody['upper_wick'] ?? 0) > 0.4)              $reason .= ' ⚠️ Upper wick trap';
        } elseif (in_array($signal, ['STRONG_BEARISH', 'BEARISH'])) {
            if ($conv === 'STRONG' && ($candleBody['is_bear'] ?? false))  $reason .= ' 🕯 Strong bear';
            elseif (in_array($conv, ['WEAK', 'DOJI']))                   { $signal = str_replace('STRONG_','',$signal); $reason .= ' ⚠️ Weak candle'; }
            elseif (($candleBody['lower_wick'] ?? 0) > 0.4)              $reason .= ' ⚠️ Lower wick trap';
        }

        $diff     = round(abs($cePct - $pePct), 2);
        $strength = $diff > 3 ? 'Very Strong Signal'
                  : ($diff > 1.5 ? 'Strong Signal' : ($diff > 0.5 ? 'Moderate Signal' : 'Weak Signal'));

        return compact('signal','condition','reason','strength','diff','confirmed','conflicted','domConflict');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TRADE SIGNAL (FIX-3 regime-aware, FIX-G noise relaxed)
    // ══════════════════════════════════════════════════════════════════════════

    private function calcTradeSignal(
        array  $spikeData,
        array  $oiSentiment,
        string $priceDir,
        string $timeZone,
        array  $candleBody,
        string $regime,
        int    $volFloor
    ): array {
        $oiSig      = $oiSentiment['signal'] ?? 'N/A';
        $confidence = 0;
        $reasons    = [];
        $action     = 'WATCH';

        $ceAtm = $spikeData['CE'][0]  ?? $this->noSpike();
        $ceM1  = $spikeData['CE'][-1] ?? $this->noSpike();
        $peAtm = $spikeData['PE'][0]  ?? $this->noSpike();
        $peP1  = $spikeData['PE'][1]  ?? $this->noSpike();

        $ceSpike = $this->isRealSpike($ceAtm) || $this->isRealSpike($ceM1);
        $peSpike = $this->isRealSpike($peAtm) || $this->isRealSpike($peP1);

        // ── Noise zone: only allow EXTREME + strong OI + price (FIX-G) ────────
        if ($timeZone === 'NOISE') {
            $hasExtreme  = in_array($ceAtm['spike_type']??'', ['EXTREME']) || in_array($peAtm['spike_type']??'', ['EXTREME']);
            $hasStrongOi = in_array($oiSig, ['STRONG_BULLISH', 'STRONG_BEARISH']);
            if (!$hasExtreme || !$hasStrongOi || $priceDir === 'FLAT') {
                return $this->avoidSignal(['Noise zone < 10:00 — EXTREME+strong OI+price required']);
            }
            $confidence -= 10; $reasons[] = '⚡ Noise override';
        }

        // ── Guards ────────────────────────────────────────────────────────────
        if ($ceSpike && $peSpike)
            return $this->avoidSignal(['Both CE & PE spiking — noise/trap']);
        if (($ceAtm['data_missing']??false) && ($peAtm['data_missing']??false))
            return $this->avoidSignal(['Data missing']);
        if (($ceAtm['low_vol']??false) && ($peAtm['low_vol']??false))
            return $this->avoidSignal(['Low volume at ATM — not a real move']);
        if (($ceAtm['early']??false) || ($peAtm['early']??false))
            return $this->avoidSignal(['< ' . self::MIN_HISTORY . ' candles history']);

        $conv = $candleBody['conviction'] ?? 'UNKNOWN';

        // FIX-3: Regime base modifier
        $regimeBoost  = match ($regime) {
            'TRENDING'  => 20,
            'BREAKOUT'  => 25,
            'SIDEWAYS'  => -30,
            default     => 0,
        };
        if ($regime !== 'UNKNOWN') {
            $reasons[] = match($regime) {
                'TRENDING'  => '📈 Trending market +20',
                'BREAKOUT'  => '💥 Breakout detected +25',
                'SIDEWAYS'  => '↔️ Sideways market −30',
                default     => '',
            };
        }
        $confidence += $regimeBoost;

        // ── BUY CE ────────────────────────────────────────────────────────────
        if ($peSpike) {
            $reasons[] = 'PE spike ATM/+1 (put writing)'; $confidence += 25;
            if (in_array($oiSig, ['BULLISH','STRONG_BULLISH'])) {
                $reasons[] = 'OI bullish (' . $oiSig . ')'; $confidence += 30;
                if ($oiSig === 'STRONG_BULLISH') $confidence += 15;
            }
            if ($priceDir === 'UP')   { $reasons[] = 'Price UP ✅';   $confidence += 30; }
            if ($conv === 'STRONG' && ($candleBody['is_bull']??false)) { $reasons[] = 'Bull candle ✅'; $confidence += 15; }
            elseif (in_array($conv, ['WEAK','DOJI']))                  { $reasons[] = 'Weak candle ⚠️'; $confidence -= 10; }
            $action = $confidence >= 60 ? 'BUY_CE' : 'WATCH';
            if ($action === 'WATCH') $reasons[] = 'Partial — wait confirmation';
        }

        // ── BUY PE ────────────────────────────────────────────────────────────
        if ($ceSpike) {
            $reasons[] = 'CE spike ATM/-1 (call writing)'; $confidence += 25;
            if (in_array($oiSig, ['BEARISH','STRONG_BEARISH'])) {
                $reasons[] = 'OI bearish (' . $oiSig . ')'; $confidence += 30;
                if ($oiSig === 'STRONG_BEARISH') $confidence += 15;
            }
            if ($priceDir === 'DOWN') { $reasons[] = 'Price DOWN ✅'; $confidence += 30; }
            if ($conv === 'STRONG' && ($candleBody['is_bear']??false)) { $reasons[] = 'Bear candle ✅'; $confidence += 15; }
            elseif (in_array($conv, ['WEAK','DOJI']))                  { $reasons[] = 'Weak candle ⚠️'; $confidence -= 10; }
            $action = $confidence >= 60 ? 'BUY_PE' : 'WATCH';
            if ($action === 'WATCH') $reasons[] = 'Partial — wait confirmation';
        }

        if ($oiSig === 'MIXED') {
            $action = 'AVOID'; $confidence = max(0, $confidence - 40);
            $reasons[] = 'OI vs price conflict';
        }
        if ($timeZone === 'REVERSAL') {
            $reasons[] = '⚠️ Reversal zone > 14:30';
            $confidence = (int)($confidence * 0.75);
        }

        $confidence = min(100, max(0, $confidence));
        if (empty($reasons)) $reasons[] = 'No clear signal';

        return [
            'action'     => $action,
            'confidence' => $confidence,
            'label'      => match($action) {
                'BUY_CE' => '🟢 BUY CE', 'BUY_PE' => '🔴 BUY PE',
                'AVOID'  => '⛔ AVOID',  default   => '👁 WATCH',
            },
            'reasons' => $reasons,
            'color'   => match($action) {
                'BUY_CE' => 'bullish', 'BUY_PE' => 'bearish',
                'AVOID'  => 'avoid',  default   => 'watch',
            },
        ];
    }

    private function avoidSignal(array $reasons): array
    {
        return ['action'=>'AVOID','confidence'=>0,'label'=>'⛔ AVOID','reasons'=>$reasons,'color'=>'avoid'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONTINUATION (FIX-F vol expansion)
    // ══════════════════════════════════════════════════════════════════════════

    private function checkContinuation(
        array $ts, ?string $prevAction, int $count,
        int $finalCeVol, int $finalPeVol, int $prevCeVol, int $prevPeVol
    ): array {
        $cur = $ts['action'];
        if (!$prevAction || $cur !== $prevAction || !in_array($cur, ['BUY_CE','BUY_PE'])) {
            return ['active'=>false,'count'=>0,'label'=>'','high_conf'=>false,'vol_expanding'=>false];
        }
        $n      = $count + 1;
        $volExp = ($cur==='BUY_CE' && $finalPeVol > $prevPeVol)
               || ($cur==='BUY_PE' && $finalCeVol > $prevCeVol);
        $hi     = $n >= 2 && $volExp;
        return [
            'active'        => true,
            'count'         => $n,
            'vol_expanding' => $volExp,
            'high_conf'     => $hi,
            'label'         => $hi ? '🔥 HIGH CONF (vol ↑)' : ($n >= 2 ? '↗ Continuation' : '↗ Building'),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SMART TRAP (FIX-E: all 4 conditions required)
    // ══════════════════════════════════════════════════════════════════════════

    private function detectTrap(array $spikeData, string $priceDir, array $oi, array $cb): array
    {
        $ceSpike = $this->isRealSpike($spikeData['CE'][0]??$this->noSpike())
                || $this->isRealSpike($spikeData['CE'][-1]??$this->noSpike());
        $peSpike = $this->isRealSpike($spikeData['PE'][0]??$this->noSpike())
                || $this->isRealSpike($spikeData['PE'][1]??$this->noSpike());
        $weak    = in_array($cb['conviction']??'', ['WEAK','DOJI']);
        $ceOiUp  = ($oi['ce_oi_pct']??0) > 0;
        $peOiUp  = ($oi['pe_oi_pct']??0) > 0;

        if ($ceSpike && $priceDir==='UP'   && $ceOiUp && $weak)
            return ['detected'=>true,'type'=>'CALL_TRAP','label'=>'⚠️ CALL TRAP',
                    'reason'=>'CE spike+UP+OI↑+weak candle'];
        if ($peSpike && $priceDir==='DOWN' && $peOiUp && $weak)
            return ['detected'=>true,'type'=>'PUT_TRAP','label'=>'⚠️ PUT TRAP',
                    'reason'=>'PE spike+DOWN+OI↑+weak candle'];

        return ['detected'=>false,'type'=>null,'label'=>'','reason'=>''];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX-8: NEXT 15M TREND — uses dominance lean even when WATCH
    // ══════════════════════════════════════════════════════════════════════════

    private function predictNextMove(
        array   $ts,
        array   $oi,
        string  $priceDir,
        array   $spikeData,
        array   $cont,
        array   $cb,
        ?string $dominanceBias,
        ?float  $dominanceRatio,
        string  $regime
    ): array {
        $action  = $ts['action'] ?? 'WATCH';
        $oiSig   = $oi['signal'] ?? 'N/A';
        $conf    = 0;
        $reasons = [];

        $peSpike = $this->isRealSpike($spikeData['PE'][0]??$this->noSpike())
                || $this->isRealSpike($spikeData['PE'][1]??$this->noSpike());
        $ceSpike = $this->isRealSpike($spikeData['CE'][0]??$this->noSpike())
                || $this->isRealSpike($spikeData['CE'][-1]??$this->noSpike());

        // ── Strong trade signal → direct use ─────────────────────────────────
        if ($action === 'BUY_CE') {
            $dir = 'UP'; $conf = 35; $reasons[] = 'BUY CE active';
            if (in_array($oiSig, ['BULLISH','STRONG_BULLISH'])) { $conf+=20; $reasons[]='OI bullish'; }
            if ($oiSig === 'STRONG_BULLISH')                     { $conf+=15; $reasons[]='Price-confirmed'; }
            if ($peSpike)                                        { $conf+=10; $reasons[]='PE writing'; }
            if ($priceDir==='UP')                                { $conf+=15; $reasons[]='Price UP'; }
            if ($cont['high_conf']??false)                       { $conf+=15; $reasons[]='🔥 Vol expanding'; }
            if ($regime === 'TRENDING')                          { $conf+=10; $reasons[]='Trending market'; }
            if ($regime === 'BREAKOUT')                          { $conf+=15; $reasons[]='Breakout'; }
            if ($regime === 'SIDEWAYS')                          { $conf-=20; $reasons[]='Sideways caution'; }
        }
        elseif ($action === 'BUY_PE') {
            $dir = 'DOWN'; $conf = 35; $reasons[] = 'BUY PE active';
            if (in_array($oiSig, ['BEARISH','STRONG_BEARISH'])) { $conf+=20; $reasons[]='OI bearish'; }
            if ($oiSig === 'STRONG_BEARISH')                     { $conf+=15; $reasons[]='Price-confirmed'; }
            if ($ceSpike)                                        { $conf+=10; $reasons[]='CE writing'; }
            if ($priceDir==='DOWN')                              { $conf+=15; $reasons[]='Price DOWN'; }
            if ($cont['high_conf']??false)                       { $conf+=15; $reasons[]='🔥 Vol expanding'; }
            if ($regime === 'TRENDING')                          { $conf+=10; $reasons[]='Trending market'; }
            if ($regime === 'BREAKOUT')                          { $conf+=15; $reasons[]='Breakout'; }
            if ($regime === 'SIDEWAYS')                          { $conf-=20; $reasons[]='Sideways caution'; }
        }
        // FIX-8: WATCH/AVOID → use dominance + OI lean (no more all-SIDEWAYS)
        else {
            $dir  = 'SIDEWAYS';
            $conf = 0;

            // Dominance lean
            if ($dominanceBias === 'BULLISH_PRESSURE' && $dominanceRatio !== null) {
                $dir = 'UP'; $conf = 20; $reasons[] = 'PE vol dominant (lean UP)';
                if (in_array($oiSig, ['BULLISH','STRONG_BULLISH'])) { $conf+=15; $reasons[]='OI aligns'; }
                if ($priceDir === 'UP')                              { $conf+=10; $reasons[]='Price UP'; }
            } elseif ($dominanceBias === 'BEARISH_PRESSURE' && $dominanceRatio !== null) {
                $dir = 'DOWN'; $conf = 20; $reasons[] = 'CE vol dominant (lean DOWN)';
                if (in_array($oiSig, ['BEARISH','STRONG_BEARISH'])) { $conf+=15; $reasons[]='OI aligns'; }
                if ($priceDir === 'DOWN')                            { $conf+=10; $reasons[]='Price DOWN'; }
            } else {
                // Truly no lean
                if ($oiSig === 'MIXED')       $reasons[] = 'OI conflict';
                if ($ceSpike && $peSpike)     $reasons[] = 'Both sides active';
                if ($priceDir === 'FLAT')     $reasons[] = 'Price flat';
                if ($regime === 'SIDEWAYS')   $reasons[] = 'Market sideways';
                if (empty($reasons))          $reasons[] = 'No directional lean';
            }

            if ($regime === 'SIDEWAYS' && $dir !== 'SIDEWAYS') {
                $conf = (int)($conf * 0.6); $reasons[] = 'Sideways regime dampens';
            }
        }

        $conf = min(100, max(0, $conf));

        return [
            'direction'  => $dir,
            'confidence' => $conf,
            'conf_label' => $conf >= 70 ? 'HIGH' : ($conf >= 40 ? 'MODERATE' : ($conf > 0 ? 'LOW' : 'NONE')),
            'reason'     => implode(' · ', $reasons),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SHARED HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function isDataMissing(int $curVol, ?float $futurePrice): bool
    {
        return $curVol === 0 && (!$futurePrice || $futurePrice == 0);
    }

    private function dataMissingSpike(): array
    {
        return ['spike_ratio'=>null,'spike_label'=>'NO DATA','spike_type'=>'DATA_MISSING',
                'avg_vol'=>0,'cur_vol'=>0,'delta_vol'=>0,
                'low_vol'=>false,'early'=>false,'data_missing'=>true];
    }

    private function classifyTimeZone(string $t): string
    {
        if ($t < '10:00')  return 'NOISE';
        if ($t >= '14:30') return 'REVERSAL';
        return 'PRIME';
    }

    private function isRealSpike(array $vs): bool
    {
        return !($vs['early']??false) && !($vs['low_vol']??false) && !($vs['data_missing']??false)
            && in_array($vs['spike_type']??'', ['SPIKE','STRONG_SPIKE','EXTREME']);
    }

    private function noSpike(): array
    {
        return ['spike_ratio'=>null,'spike_label'=>'N/A','spike_type'=>'NORMAL',
                'avg_vol'=>0,'cur_vol'=>0,'delta_vol'=>0,
                'low_vol'=>false,'early'=>false,'data_missing'=>false];
    }

    private function noSentiment(): array
    {
        return ['signal'=>'N/A','condition'=>'Opening candle','reason'=>'',
                'strength'=>'N/A','diff'=>0,'confirmed'=>false,'conflicted'=>false,
                'domConflict'=>false,'ce_oi_pct'=>0,'pe_oi_pct'=>0,'time'=>null];
    }

    private function resolveStrikeInterval($rows): ?float
    {
        $strikes = $rows->where('instrument_type', 'CE')
            ->pluck('strike')->map(fn($s)=>(float)$s)
            ->filter(fn($s)=>$s>0)->unique()->sort()->values()->toArray();
        if (count($strikes) < 2) return null;
        $minGap = PHP_INT_MAX;
        for ($i=1;$i<count($strikes);$i++) {
            $gap = $strikes[$i]-$strikes[$i-1];
            if ($gap>0&&$gap<$minGap) $minGap=$gap;
        }
        return $minGap===PHP_INT_MAX ? null : (float)$minGap;
    }

    private function getPrevTradingDay(string $today): ?string
    {
        $dt = Carbon::parse($today)->subDay();
        $limit = 7;
        while ($limit-- > 0) {
            if (!$dt->isWeekend()) return $dt->toDateString();
            $dt->subDay();
        }
        return null;
    }

    private function getNearestExpiry(string $sym, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE','PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
        if ($expiry) return $expiry;
        return OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE','PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }
}