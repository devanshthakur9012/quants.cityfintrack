<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SmartVolumeDailyEodController — v1
 *
 * PURPOSE:
 *   Takes ALL 15-min candles from prev-day 14:45 → today 14:45
 *   Produces ONE end-of-day signal for NEXT DAY trading.
 *   Signal only shown after 15:00 (last candle = 14:45 close).
 *
 * WINDOW:
 *   prev_day 14:45 candle  → carry-forward context
 *   today    09:15 → 14:45 → full session scoring
 *
 * ZONE WEIGHTS (for scoring):
 *   NOISE    < 10:00       → 0.5  (low reliability)
 *   PRIME    10:00–14:30   → 1.0  (normal weight)
 *   REVERSAL ≥ 14:30       → 1.5  (most predictive for next day)
 *
 * SPIKE SCORES:
 *   EXTREME = 5, STRONG_SPIKE = 4, SPIKE = 3, ELEVATED = 2, NORMAL = 0
 *
 * SIGNAL:
 *   BUY_CE  = PE writing dominant + OI bullish + price up
 *   BUY_PE  = CE writing dominant + OI bearish + price down
 *   AVOID   = balanced / conflicted
 *
 * SIGNAL STRENGTH TIERS:
 *   STRONG  ≥ 75 confidence
 *   MODERATE 50–74
 *   WEAK    25–49
 *   AVOID   < 25
 */
class SmartVolumeDailyEodController extends Controller
{
    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'SENSEX', 'FINNIFTY', 'MIDCPNIFTY'];

    private const VOL_FLOOR = [
        'INDEX' => 10000,
        'STOCK' => 3000,
    ];

    private const MIN_HISTORY = 3;

    // Strike offsets: ATM-1, ATM, ATM+1
    private const OFFSETS = [-1, 0, 1];

    // Strike weights for weighted volume
    private const WEIGHTS = [-1 => 2, 0 => 3, 1 => 2];

    // Zone weights for EOD scoring
    private const ZONE_WEIGHTS = [
        'NOISE'    => 0.5,
        'PRIME'    => 1.0,
        'REVERSAL' => 1.5,
    ];

    // Spike scores
    private const SPIKE_SCORES = [
        'EXTREME'      => 5,
        'STRONG_SPIKE' => 4,
        'SPIKE'        => 3,
        'ELEVATED'     => 2,
        'NORMAL'       => 0,
    ];

    // ── Entry point ───────────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle = 'Daily EOD Signal (Next Day Prediction)';
        return view($this->activeTemplate . 'user.smart-volume-daily-eod.index', compact('pageTitle'));
    }

    // ── Main API ──────────────────────────────────────────────────────────────
    public function getSignals(Request $request)
    {
        try {
            $symbol = strtoupper(trim($request->get('symbol', 'ALL')));
            $today  = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            // Check if market is past 15:00 today
            $now          = Carbon::now();
            $isToday      = $today === Carbon::today()->toDateString();
            $marketClosed = !$isToday || $now->format('H:i') >= '15:00';

            $availableSymbols = OptionOhlcData::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success'           => true,
                    'data'              => [],
                    'today'             => $today,
                    'is_today'          => $isToday,
                    'market_closed'     => $marketClosed,
                    'current_time'      => $now->format('H:i'),
                    'message'           => 'No data for ' . $today,
                    'available_symbols' => [],
                ]);
            }

            $isAll         = ($symbol === 'ALL');
            $symbols       = $isAll ? $availableSymbols : [$symbol];
            $prevTradingDay = $this->getPrevTradingDay($today);
            $nextTradingDay = $this->getNextTradingDay($today);
            $results       = [];

            foreach ($symbols as $sym) {
                $result = $this->processSymbol($sym, $today, $prevTradingDay, $nextTradingDay, $marketClosed);
                if ($result) $results[] = $result;
            }

            // Sort by confidence descending
            usort($results, fn($a, $b) => ($b['eod_signal']['confidence'] ?? 0) <=> ($a['eod_signal']['confidence'] ?? 0));

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'today'             => $today,
                'is_today'          => $isToday,
                'market_closed'     => $marketClosed,
                'current_time'      => $now->format('H:i'),
                'next_trading_day'  => $nextTradingDay,
                'available_symbols' => $availableSymbols,
                'message'           => count($results) . ' symbol(s) analyzed for ' . $today,
            ]);

        } catch (\Exception $e) {
            Log::error('SmartVolumeDailyEod: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SYMBOL PROCESSOR
    // ══════════════════════════════════════════════════════════════════════════

    private function processSymbol(
        string $sym,
        string $today,
        ?string $prevDay,
        ?string $nextDay,
        bool $marketClosed
    ): ?array {
        $expiry = $this->getNearestExpiry($sym, $today);
        if (!$expiry) return null;

        $isIndex  = in_array($sym, self::INDEX_SYMBOLS);
        $volFloor = $isIndex ? self::VOL_FLOOR['INDEX'] : self::VOL_FLOOR['STOCK'];

        // ── Load today's CE/PE candles ────────────────────────────────────────
        $cepeRows = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $today)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'instrument_type', 'strike', 'atm_strike',
                   'volume', 'oi', 'open', 'high', 'low', 'close', 'future_price']);

        if ($cepeRows->isEmpty()) return null;

        // ── Load today's FUT candles (no expiry filter) ───────────────────────
        $futRows = OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $today)
            ->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close', 'future_price', 'is_missing']);

        // ── Load prev-day carry-forward candles (14:30 and 14:45) ────────────
        $prevCepeRows = $prevDay
            ? OptionOhlcData::where('base_symbol', $sym)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $prevDay)
                ->where('is_missing', 0)
                ->whereTime('interval_time', '>=', '14:00:00')
                ->orderBy('interval_time')
                ->get(['interval_time', 'instrument_type', 'strike', 'atm_strike',
                       'volume', 'oi', 'open', 'high', 'low', 'close', 'future_price'])
            : collect();

        $prevFutRows = $prevDay
            ? OptionOhlcData::where('base_symbol', $sym)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDay)
                ->whereTime('interval_time', '>=', '14:00:00')
                ->orderBy('interval_time')
                ->get(['interval_time', 'open', 'high', 'low', 'close', 'future_price', 'is_missing'])
            : collect();

        $atmStrike      = (float) $cepeRows->sortBy('interval_time')->last()->atm_strike;
        $strikeInterval = $this->resolveStrikeInterval($cepeRows);
        if (!$strikeInterval) return null;

        // ── Build time maps ───────────────────────────────────────────────────
        $todayFutMap  = $this->buildFutMap($futRows);
        $prevFutMap   = $this->buildFutMap($prevFutRows);
        $todayVolMap  = $this->buildVolMap($cepeRows, $atmStrike, $strikeInterval);
        $prevVolMap   = $this->buildVolMap($prevCepeRows, $atmStrike, $strikeInterval);
        $todayOiMap   = $this->buildOiMap($cepeRows);
        $prevOiMap    = $this->buildOiMap($prevCepeRows);

        $allTimes = $cepeRows
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->unique()->sort()->values()->toArray();

        if (empty($allTimes)) return null;

        // ── Per-candle scoring ────────────────────────────────────────────────
        $candleScores   = [];
        $priorVols      = [];     // [trackKey => [v1,v2,...]]  running history
        $prevVolAbsMap  = [];     // [trackKey => lastAbsVol]
        $futPrices      = [];     // all FUT close prices (for trend)
        $regimeCounts   = ['TRENDING' => 0, 'SIDEWAYS' => 0, 'BREAKOUT' => 0, 'UNKNOWN' => 0];
        $prevOiByTime   = [];
        $recentFutPrices = [];

        // Seed priorVols from prev-day last 3 candles
        foreach (['CE', 'PE'] as $type) {
            foreach (self::OFFSETS as $off) {
                $key  = "{$type}:{$off}";
                $pvMap = $prevVolMap[$type][$off] ?? [];
                if (!empty($pvMap)) {
                    ksort($pvMap);
                    $vals = array_values($pvMap);
                    // Only use prev day seeds if they pass volume floor
                    $last = array_slice($vals, -3);
                    $avg  = count($last) > 0 ? array_sum($last) / count($last) : 0;
                    if ($avg >= $volFloor) {
                        $priorVols[$key] = $last;
                    }
                }
            }
        }

        foreach ($allTimes as $idx => $tk) {
            $timeZone   = $this->classifyTimeZone($tk);
            $zoneWeight = self::ZONE_WEIGHTS[$timeZone] ?? 1.0;
            $futCandle  = $todayFutMap[$tk] ?? null;
            $curPrice   = $futCandle['future_price'] ?? ($futCandle['close'] ?? null);

            if ($curPrice) {
                $futPrices[]       = (float) $curPrice;
                $recentFutPrices[] = (float) $curPrice;
                if (count($recentFutPrices) > 4) array_shift($recentFutPrices);
            }

            // Price direction this candle
            $priceDir = 'FLAT';
            if ($idx > 0 && $curPrice) {
                $prevTk  = $allTimes[$idx - 1];
                $prevPrc = $todayFutMap[$prevTk]['future_price'] ?? ($todayFutMap[$prevTk]['close'] ?? null);
                if ($prevPrc && $prevPrc > 0) {
                    $ptChange  = $curPrice - $prevPrc;
                    $pctChange = abs($ptChange / $prevPrc) * 100;
                    $threshold = max(0.05, (0.5 / $prevPrc) * 100);
                    if ($ptChange > 0 && $pctChange >= $threshold)       $priceDir = 'UP';
                    elseif ($ptChange < 0 && $pctChange >= $threshold)   $priceDir = 'DOWN';
                }
            }

            // Market regime
            $regime = $this->detectMarketRegime($recentFutPrices);
            $regimeCounts[$regime] = ($regimeCounts[$regime] ?? 0) + 1;

            // Candle body
            $candleBody = $this->analyseCandleBody($futCandle);

            // Compute spike scores for CE and PE
            $ceSpikeScore = 0;
            $peSpikeScore = 0;
            $ceHasSpike   = false;
            $peHasSpike   = false;
            $ceTopSpike   = 'NORMAL';
            $peTopSpike   = 'NORMAL';

            foreach (['CE', 'PE'] as $type) {
                $totalScore = 0;
                $topSpike   = 'NORMAL';

                foreach (self::OFFSETS as $off) {
                    $key    = "{$type}:{$off}";
                    $curVol = $todayVolMap[$type][$off][$tk] ?? 0;

                    if ($curVol === 0) continue;

                    $prevVol  = $prevVolAbsMap[$key] ?? 0;
                    $deltaVol = max(0, $curVol - $prevVol);

                    $thresh = $this->getAdaptiveThresholds(
                        $priorVols[$key] ?? [], $volFloor, $isIndex
                    );

                    $spikeResult = $this->calcVolSpike(
                        $deltaVol, $curVol,
                        $priorVols[$key] ?? [],
                        $volFloor, $thresh
                    );

                    $spikeType  = $spikeResult['spike_type'] ?? 'NORMAL';
                    $spikeScore = self::SPIKE_SCORES[$spikeType] ?? 0;

                    // Zone-weighted score, ATM gets extra weight
                    $strikeWeight = self::WEIGHTS[$off] ?? 1;
                    $totalScore  += $spikeScore * $zoneWeight * $strikeWeight;

                    if (($spikeScore > (self::SPIKE_SCORES[$topSpike] ?? 0))) {
                        $topSpike = $spikeType;
                    }

                    // Update history
                    if ($curVol > 0) {
                        $priorVols[$key][]     = $deltaVol > 0 ? $deltaVol : $curVol;
                        $prevVolAbsMap[$key]   = $curVol;
                    }
                }

                if ($type === 'CE') {
                    $ceSpikeScore = $totalScore;
                    $ceTopSpike   = $topSpike;
                    $ceHasSpike   = $totalScore > 0;
                } else {
                    $peSpikeScore = $totalScore;
                    $peTopSpike   = $topSpike;
                    $peHasSpike   = $totalScore > 0;
                }
            }

            // OI change this candle
            $prevTkOi = $idx > 0 ? ($allTimes[$idx - 1]) : null;
            $ceOi     = $todayOiMap['CE'][$tk] ?? 0;
            $peOi     = $todayOiMap['PE'][$tk] ?? 0;
            $cePrevOi = $prevTkOi ? ($todayOiMap['CE'][$prevTkOi] ?? 0) : 0;
            $pePrevOi = $prevTkOi ? ($todayOiMap['PE'][$prevTkOi] ?? 0) : 0;
            $ceOiPct  = $cePrevOi > 0 ? (($ceOi - $cePrevOi) / $cePrevOi) * 100 : 0;
            $peOiPct  = $pePrevOi > 0 ? (($peOi - $pePrevOi) / $pePrevOi) * 100 : 0;

            $candleScores[] = [
                'time'          => $tk,
                'time_zone'     => $timeZone,
                'zone_weight'   => $zoneWeight,
                'regime'        => $regime,
                'price_dir'     => $priceDir,
                'future_price'  => $curPrice,
                'ce_score'      => $ceSpikeScore,
                'pe_score'      => $peSpikeScore,
                'ce_top_spike'  => $ceTopSpike,
                'pe_top_spike'  => $peTopSpike,
                'ce_oi_pct'     => round($ceOiPct, 2),
                'pe_oi_pct'     => round($peOiPct, 2),
                'candle_body'   => $candleBody,
            ];
        }

        if (empty($candleScores)) return null;

        // ── Aggregate scores ──────────────────────────────────────────────────
        $totalCeScore = 0;
        $totalPeScore = 0;
        $ceOiBuild    = 0;  // count candles where CE OI increased (bearish)
        $peOiBuild    = 0;  // count candles where PE OI increased (bullish)
        $upCandles    = 0;
        $downCandles  = 0;
        $reversalCeScore = 0;
        $reversalPeScore = 0;
        $primeCeScore    = 0;
        $primePeScore    = 0;
        $extremeCount    = ['CE' => 0, 'PE' => 0];

        foreach ($candleScores as $cs) {
            $totalCeScore += $cs['ce_score'];
            $totalPeScore += $cs['pe_score'];

            if ($cs['ce_oi_pct'] > 0) $ceOiBuild++;
            if ($cs['pe_oi_pct'] > 0) $peOiBuild++;
            if ($cs['price_dir'] === 'UP')   $upCandles++;
            if ($cs['price_dir'] === 'DOWN') $downCandles++;

            if ($cs['time_zone'] === 'REVERSAL') {
                $reversalCeScore += $cs['ce_score'];
                $reversalPeScore += $cs['pe_score'];
            }
            if ($cs['time_zone'] === 'PRIME') {
                $primeCeScore += $cs['ce_score'];
                $primePeScore += $cs['pe_score'];
            }
            if (in_array($cs['ce_top_spike'], ['EXTREME', 'STRONG_SPIKE'])) $extremeCount['CE']++;
            if (in_array($cs['pe_top_spike'], ['EXTREME', 'STRONG_SPIKE'])) $extremeCount['PE']++;
        }

        $nCandles     = count($candleScores);
        $totalScore   = $totalCeScore + $totalPeScore;

        // ── Price trend ───────────────────────────────────────────────────────
        $priceTrend     = $this->calcPriceTrend($futPrices);
        $last3Momentum  = $this->calcLast3Momentum($futPrices);

        // ── Dominant regime ───────────────────────────────────────────────────
        arsort($regimeCounts);
        $dominantRegime = array_key_first($regimeCounts);

        // ── EOD Signal synthesis ──────────────────────────────────────────────
        $eodSignal = $this->synthesizeEodSignal(
            $totalCeScore, $totalPeScore,
            $reversalCeScore, $reversalPeScore,
            $primeCeScore, $primePeScore,
            $ceOiBuild, $peOiBuild,
            $upCandles, $downCandles,
            $nCandles,
            $priceTrend, $last3Momentum,
            $dominantRegime,
            $extremeCount,
            $isIndex,
            $marketClosed
        );

        // ── Last candle snapshot ──────────────────────────────────────────────
        $lastCandle    = end($candleScores);
        $firstCandle   = $candleScores[0];
        $dayOpen       = $firstCandle['future_price'] ?? null;
        $dayClose      = $lastCandle['future_price']  ?? null;
        $dayChangePct  = ($dayOpen && $dayClose && $dayOpen > 0)
            ? round((($dayClose - $dayOpen) / $dayOpen) * 100, 2)
            : null;

        return [
            'symbol'            => $sym,
            'expiry'            => $expiry,
            'date'              => $today,
            'next_trading_day'  => $nextDay,
            'atm_strike'        => $atmStrike,
            'is_index'          => $isIndex,
            'vol_floor'         => $volFloor,
            'total_candles'     => $nCandles,
            'market_closed'     => $marketClosed,

            // Day summary
            'day_summary' => [
                'open'           => $dayOpen,
                'close'          => $dayClose,
                'change_pct'     => $dayChangePct,
                'up_candles'     => $upCandles,
                'down_candles'   => $downCandles,
                'dominant_regime'=> $dominantRegime,
                'regime_counts'  => $regimeCounts,
                'price_trend'    => $priceTrend,
                'last3_momentum' => $last3Momentum,
            ],

            // Vol scoring
            'vol_scoring' => [
                'total_ce_score'      => round($totalCeScore, 2),
                'total_pe_score'      => round($totalPeScore, 2),
                'reversal_ce_score'   => round($reversalCeScore, 2),
                'reversal_pe_score'   => round($reversalPeScore, 2),
                'prime_ce_score'      => round($primeCeScore, 2),
                'prime_pe_score'      => round($primePeScore, 2),
                'extreme_ce_count'    => $extremeCount['CE'],
                'extreme_pe_count'    => $extremeCount['PE'],
            ],

            // OI summary
            'oi_summary' => [
                'ce_oi_build_candles' => $ceOiBuild,
                'pe_oi_build_candles' => $peOiBuild,
                'ce_build_pct'        => $nCandles > 0 ? round(($ceOiBuild / $nCandles) * 100) : 0,
                'pe_build_pct'        => $nCandles > 0 ? round(($peOiBuild / $nCandles) * 100) : 0,
            ],

            // Candle breakdown
            'candle_scores' => $candleScores,

            // THE FINAL EOD SIGNAL
            'eod_signal' => $eodSignal,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EOD SIGNAL SYNTHESIS
    // ══════════════════════════════════════════════════════════════════════════

    private function synthesizeEodSignal(
        float $totalCe, float $totalPe,
        float $revCe,   float $revPe,
        float $primeCe, float $primePe,
        int   $ceOiBuild, int $peOiBuild,
        int   $upCandles, int $downCandles,
        int   $nCandles,
        array $priceTrend,
        array $last3Mom,
        string $regime,
        array $extremes,
        bool  $isIndex,
        bool  $marketClosed
    ): array {

        if (!$marketClosed) {
            return [
                'action'     => 'WAIT',
                'label'      => '⏳ Market Open',
                'direction'  => null,
                'confidence' => 0,
                'strength'   => 'NOT_READY',
                'reasons'    => ['Market still open — EOD signal available after 15:00'],
                'color'      => 'wait',
                'valid_for'  => 'Next trading session (9:15 – 10:30)',
                'entry_time' => 'Wait for 9:15 candle confirmation',
            ];
        }

        $action     = 'AVOID';
        $confidence = 0;
        $reasons    = [];

        $totalScore  = $totalCe + $totalPe;
        if ($totalScore <= 0) {
            return [
                'action'     => 'AVOID',
                'label'      => '⛔ AVOID',
                'direction'  => null,
                'confidence' => 0,
                'strength'   => 'AVOID',
                'reasons'    => ['No meaningful volume activity detected'],
                'color'      => 'avoid',
                'valid_for'  => 'Next trading session',
                'entry_time' => 'No trade recommended',
            ];
        }

        // ── Step 1: Volume dominance (primary signal) ─────────────────────────
        $cePct    = round(($totalCe / $totalScore) * 100);
        $pePct    = 100 - $cePct;
        $ceRevPct = ($revCe + $revPe) > 0 ? round(($revCe / ($revCe + $revPe)) * 100) : 50;
        $peRevPct = 100 - $ceRevPct;

        // CE dominant = call writing = BEARISH = BUY PE
        // PE dominant = put writing = BULLISH = BUY CE
        $volBias = 'NEUTRAL';
        if ($cePct >= 60) {
            $volBias = 'CE_DOMINANT'; // bearish bias → BUY PE
            $reasons[] = "CE vol dominant ({$cePct}% of total)";
            $confidence += 25;
        } elseif ($pePct >= 60) {
            $volBias = 'PE_DOMINANT'; // bullish bias → BUY CE
            $reasons[] = "PE vol dominant ({$pePct}% of total)";
            $confidence += 25;
        } else {
            $reasons[] = "Balanced vol (CE:{$cePct}% PE:{$pePct}%)";
        }

        // Reversal zone confirmation (most important for next day)
        if ($ceRevPct >= 60) {
            $confidence += 20;
            $reasons[] = "Reversal zone CE dominant ({$ceRevPct}%)";
            if ($volBias === 'CE_DOMINANT') $confidence += 10; // double confirmation
        } elseif ($peRevPct >= 60) {
            $confidence += 20;
            $reasons[] = "Reversal zone PE dominant ({$peRevPct}%)";
            if ($volBias === 'PE_DOMINANT') $confidence += 10;
        }

        // Extreme spike count
        if ($extremes['CE'] >= 2) {
            $reasons[] = "CE had {$extremes['CE']} extreme/strong spikes";
            if ($volBias === 'CE_DOMINANT') $confidence += 10;
        }
        if ($extremes['PE'] >= 2) {
            $reasons[] = "PE had {$extremes['PE']} extreme/strong spikes";
            if ($volBias === 'PE_DOMINANT') $confidence += 10;
        }

        // ── Step 2: OI direction bias ─────────────────────────────────────────
        $oiBias = 'NEUTRAL';
        if ($nCandles > 0) {
            $ceOiPct = round(($ceOiBuild / $nCandles) * 100);
            $peOiPct = round(($peOiBuild / $nCandles) * 100);

            if ($ceOiPct >= 60 && $peOiPct < 40) {
                $oiBias = 'BEARISH'; // CE OI building = bearish
                $reasons[] = "OI: CE building {$ceOiPct}% candles (bearish)";
                $confidence += 15;
            } elseif ($peOiPct >= 60 && $ceOiPct < 40) {
                $oiBias = 'BULLISH'; // PE OI building = bullish
                $reasons[] = "OI: PE building {$peOiPct}% candles (bullish)";
                $confidence += 15;
            } elseif ($peOiPct >= 50 && $ceOiPct >= 50) {
                $oiBias = 'MIXED';
                $reasons[] = 'OI: Both sides building (mixed)';
                $confidence -= 10;
            }
        }

        // ── Step 3: Price trend ───────────────────────────────────────────────
        $priceBias = $priceTrend['direction'] ?? 'FLAT';
        $trendStr  = $priceTrend['strength']  ?? 'WEAK';
        if ($priceBias === 'UP') {
            $reasons[] = "Price trend UP ({$trendStr})";
            $confidence += $trendStr === 'STRONG' ? 20 : 10;
        } elseif ($priceBias === 'DOWN') {
            $reasons[] = "Price trend DOWN ({$trendStr})";
            $confidence += $trendStr === 'STRONG' ? 20 : 10;
        } else {
            $reasons[] = 'Price trend: FLAT';
        }

        // Last 3 candle momentum
        $momDir = $last3Mom['direction'] ?? 'FLAT';
        if ($momDir !== 'FLAT') {
            $reasons[] = "Last 3-candle momentum: {$momDir}";
            $confidence += 5;
        }

        // ── Step 4: Regime modifier ───────────────────────────────────────────
        if ($regime === 'TRENDING') {
            $confidence += 10;
            $reasons[] = '📈 Day was TRENDING (+10)';
        } elseif ($regime === 'BREAKOUT') {
            $confidence += 15;
            $reasons[] = '💥 Day had BREAKOUT (+15)';
        } elseif ($regime === 'SIDEWAYS') {
            $confidence -= 15;
            $reasons[] = '↔️ Day was SIDEWAYS (−15)';
        }

        // ── Step 5: Determine action ──────────────────────────────────────────
        // BUY_CE = put writing dominant + bullish OI + price up
        // BUY_PE = call writing dominant + bearish OI + price down

        $buyCeScore = 0;
        $buyPeScore = 0;

        if ($volBias === 'PE_DOMINANT')   $buyCeScore += 3;
        if ($volBias === 'CE_DOMINANT')   $buyPeScore += 3;
        if ($oiBias  === 'BULLISH')       $buyCeScore += 2;
        if ($oiBias  === 'BEARISH')       $buyPeScore += 2;
        if ($priceBias === 'UP')          $buyCeScore += 2;
        if ($priceBias === 'DOWN')        $buyPeScore += 2;
        if ($momDir    === 'UP')          $buyCeScore += 1;
        if ($momDir    === 'DOWN')        $buyPeScore += 1;

        // Conflict detection
        $isConflicted = false;
        if (abs($buyCeScore - $buyPeScore) <= 1) {
            $isConflicted = true;
            $reasons[] = '⚠️ Signals conflicted — avoid';
            $action = 'AVOID';
            $confidence = max(0, $confidence - 30);
        } elseif ($buyCeScore > $buyPeScore) {
            $action = 'BUY_CE';
        } else {
            $action = 'BUY_PE';
        }

        // OI conflict downgrade
        if ($oiBias === 'MIXED') {
            $confidence -= 15;
            if ($confidence < 30) $action = 'AVOID';
        }

        // Cross-confirm: price aligns with vol?
        if ($action === 'BUY_CE' && $priceBias === 'DOWN') {
            $reasons[] = '⚡ Conflict: price down but CE signal — caution';
            $confidence -= 10;
        }
        if ($action === 'BUY_PE' && $priceBias === 'UP') {
            $reasons[] = '⚡ Conflict: price up but PE signal — caution';
            $confidence -= 10;
        }

        $confidence = min(100, max(0, $confidence));
        if ($confidence < 25 && $action !== 'WAIT') $action = 'AVOID';

        // ── Signal strength tier ──────────────────────────────────────────────
        $strength = match (true) {
            $action === 'AVOID' || $action === 'WAIT' => 'AVOID',
            $confidence >= 75                         => 'STRONG',
            $confidence >= 50                         => 'MODERATE',
            $confidence >= 25                         => 'WEAK',
            default                                   => 'AVOID',
        };

        $direction = match ($action) {
            'BUY_CE' => 'BULLISH',
            'BUY_PE' => 'BEARISH',
            default  => null,
        };

        $label = match ($action) {
            'BUY_CE' => '🟢 BUY CE (Next Day)',
            'BUY_PE' => '🔴 BUY PE (Next Day)',
            'AVOID'  => '⛔ AVOID',
            default  => '⏳ WAIT',
        };

        $color = match ($action) {
            'BUY_CE' => 'bullish',
            'BUY_PE' => 'bearish',
            default  => 'avoid',
        };

        return [
            'action'       => $action,
            'label'        => $label,
            'direction'    => $direction,
            'confidence'   => $confidence,
            'strength'     => $strength,
            'reasons'      => $reasons,
            'color'        => $color,
            'scores'       => ['buy_ce' => $buyCeScore, 'buy_pe' => $buyPeScore],
            'vol_bias'     => $volBias,
            'oi_bias'      => $oiBias,
            'price_bias'   => $priceBias,
            'conflicted'   => $isConflicted,
            'valid_for'    => 'Next trading session (9:15 – 10:30 entry window)',
            'entry_time'   => $action !== 'AVOID' ? '9:15 candle confirmation recommended' : 'No trade',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PRICE TREND (linear direction over all candles)
    // ══════════════════════════════════════════════════════════════════════════

    private function calcPriceTrend(array $prices): array
    {
        $n = count($prices);
        if ($n < 3) return ['direction' => 'FLAT', 'strength' => 'NONE', 'change_pct' => 0];

        $first = $prices[0];
        $last  = $prices[$n - 1];
        if ($first <= 0) return ['direction' => 'FLAT', 'strength' => 'NONE', 'change_pct' => 0];

        $changePct = (($last - $first) / $first) * 100;

        // Simple linear regression slope
        $xMean = ($n - 1) / 2;
        $yMean = array_sum($prices) / $n;
        $num   = 0;
        $den   = 0;
        foreach ($prices as $i => $p) {
            $num += ($i - $xMean) * ($p - $yMean);
            $den += ($i - $xMean) ** 2;
        }
        $slope = $den != 0 ? $num / $den : 0;

        // Normalize slope as pct
        $slopePct = $yMean > 0 ? ($slope / $yMean) * 100 : 0;

        $direction = abs($changePct) < 0.1 ? 'FLAT' : ($changePct > 0 ? 'UP' : 'DOWN');
        $strength  = match (true) {
            abs($changePct) >= 1.0 => 'STRONG',
            abs($changePct) >= 0.4 => 'MODERATE',
            abs($changePct) >= 0.1 => 'WEAK',
            default                => 'NONE',
        };

        return [
            'direction'  => $direction,
            'strength'   => $strength,
            'change_pct' => round($changePct, 2),
            'slope_pct'  => round($slopePct, 4),
        ];
    }

    private function calcLast3Momentum(array $prices): array
    {
        $n = count($prices);
        if ($n < 4) return ['direction' => 'FLAT', 'change_pct' => 0];

        $start = $prices[$n - 4];
        $end   = $prices[$n - 1];
        if ($start <= 0) return ['direction' => 'FLAT', 'change_pct' => 0];

        $pct = (($end - $start) / $start) * 100;
        return [
            'direction'  => abs($pct) < 0.05 ? 'FLAT' : ($pct > 0 ? 'UP' : 'DOWN'),
            'change_pct' => round($pct, 2),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MAP BUILDERS
    // ══════════════════════════════════════════════════════════════════════════

    private function buildFutMap($rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            if ((int)($r->is_missing ?? 0) === 1) continue;
            $tk  = Carbon::parse($r->interval_time)->format('H:i');
            $fp  = (float) $r->future_price;
            $map[$tk] = [
                'open'         => (float) $r->open,
                'high'         => (float) $r->high,
                'low'          => (float) $r->low,
                'close'        => (float) $r->close,
                'future_price' => $fp > 0 ? $fp : (float) $r->close,
            ];
        }
        return $map;
    }

    private function buildVolMap($rows, float $atmStrike, float $strikeInterval): array
    {
        $map = [];
        foreach ($rows as $r) {
            $tk     = Carbon::parse($r->interval_time)->format('H:i');
            $type   = $r->instrument_type;
            $strike = (float) $r->strike;
            $offset = (int) round(($strike - $atmStrike) / $strikeInterval);
            if (!in_array($offset, self::OFFSETS)) continue;
            $map[$type][$offset][$tk] = ($map[$type][$offset][$tk] ?? 0) + (int) $r->volume;
        }
        return $map;
    }

    private function buildOiMap($rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $tk   = Carbon::parse($r->interval_time)->format('H:i');
            $type = $r->instrument_type;
            $map[$type][$tk] = ($map[$type][$tk] ?? 0) + (int) $r->oi;
        }
        return $map;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADAPTIVE THRESHOLDS (same as 15min controller)
    // ══════════════════════════════════════════════════════════════════════════

    private function getAdaptiveThresholds(array $priorVols, int $volFloor, bool $isIndex): array
    {
        $defaults = ['extreme' => 3.0, 'strong' => 2.0, 'spike' => 1.5, 'elevated' => 1.2];
        if (count($priorVols) < self::MIN_HISTORY) return $defaults;

        $mean = array_sum($priorVols) / count($priorVols);
        if ($mean <= 0) return $defaults;

        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $priorVols)) / count($priorVols);
        $std      = sqrt($variance);
        $cv       = $std / $mean;

        $cvMult = match (true) {
            $cv >= 1.5 => 1.4,
            $cv >= 1.0 => 1.2,
            $cv >= 0.5 => 1.0,
            default    => 0.85,
        };

        $idxMult = $isIndex ? 1.15 : 1.0;
        $m       = $cvMult * $idxMult;

        return [
            'extreme'  => round(3.0 * $m, 2),
            'strong'   => round(2.0 * $m, 2),
            'spike'    => round(1.5 * $m, 2),
            'elevated' => round(1.2 * $m, 2),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VOL SPIKE (same core as 15min controller)
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
            return array_merge($base, ['spike_ratio' => null, 'spike_type' => 'OPENING',
                'avg_vol' => 0, 'low_vol' => $curVol < $volFloor, 'early' => true]);
        }
        if (count($priorVols) < self::MIN_HISTORY) {
            $avg = array_sum($priorVols) / count($priorVols);
            return array_merge($base, ['spike_ratio' => null, 'spike_type' => 'EARLY',
                'avg_vol' => (int)round($avg), 'low_vol' => $curVol < $volFloor, 'early' => true]);
        }

        $avg = array_sum($priorVols) / count($priorVols);
        if ($curVol < $volFloor) {
            return array_merge($base, ['spike_ratio' => null, 'spike_type' => 'LOW_VOLUME',
                'avg_vol' => (int)round($avg), 'low_vol' => true]);
        }
        if ($avg <= 0) {
            return array_merge($base, ['spike_ratio' => null, 'spike_type' => 'NORMAL', 'avg_vol' => 0]);
        }

        $ratio = round($deltaVol / max($avg, 1), 2);
        $type  = match (true) {
            $ratio >= $thresh['extreme']  => 'EXTREME',
            $ratio >= $thresh['strong']   => 'STRONG_SPIKE',
            $ratio >= $thresh['spike']    => 'SPIKE',
            $ratio >= $thresh['elevated'] => 'ELEVATED',
            default                       => 'NORMAL',
        };

        return array_merge($base, [
            'spike_ratio' => $ratio,
            'spike_type'  => $type,
            'avg_vol'     => (int)round($avg),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MARKET REGIME
    // ══════════════════════════════════════════════════════════════════════════

    private function detectMarketRegime(array $recentPrices): string
    {
        if (count($recentPrices) < 3) return 'UNKNOWN';

        $n    = count($recentPrices);
        $high = max($recentPrices);
        $low  = min($recentPrices);
        $base = $recentPrices[0];
        if ($base <= 0) return 'UNKNOWN';

        $swingPct = (($high - $low) / $base) * 100;

        if ($n >= 2) {
            $lastMovePct = (abs($recentPrices[$n-1] - $recentPrices[$n-2]) / $base) * 100;
            if ($lastMovePct >= 0.5 && $swingPct >= 0.4) return 'BREAKOUT';
        }
        if ($swingPct <= 0.2) return 'SIDEWAYS';

        $dirs = [];
        for ($i = 1; $i < $n; $i++) {
            $dirs[] = $recentPrices[$i] > $recentPrices[$i-1] ? 1 : -1;
        }
        if (count(array_unique($dirs)) === 1) return 'TRENDING';

        return 'SIDEWAYS';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CANDLE BODY
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
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function classifyTimeZone(string $t): string
    {
        if ($t < '10:00')  return 'NOISE';
        if ($t >= '14:30') return 'REVERSAL';
        return 'PRIME';
    }

    private function resolveStrikeInterval($rows): ?float
    {
        $strikes = $rows->where('instrument_type', 'CE')
            ->pluck('strike')->map(fn($s) => (float)$s)
            ->filter(fn($s) => $s > 0)->unique()->sort()->values()->toArray();
        if (count($strikes) < 2) return null;
        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < count($strikes); $i++) {
            $gap = $strikes[$i] - $strikes[$i-1];
            if ($gap > 0 && $gap < $minGap) $minGap = $gap;
        }
        return $minGap === PHP_INT_MAX ? null : (float)$minGap;
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

    private function getNextTradingDay(string $today): ?string
    {
        $dt = Carbon::parse($today)->addDay();
        $limit = 7;
        while ($limit-- > 0) {
            if (!$dt->isWeekend()) return $dt->toDateString();
            $dt->addDay();
        }
        return null;
    }

    private function getNearestExpiry(string $sym, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
        if ($expiry) return $expiry;
        return OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }
}