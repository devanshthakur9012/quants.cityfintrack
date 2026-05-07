<?php

namespace App\Services\Stock;

use App\Models\StockDailyOhlcData;
use App\Models\StockFeature;
use App\Models\StockPattern;
use App\Models\StockPivot;
use App\Models\StockSignal;
use Carbon\Carbon;

/**
 * SignalService  ―  Confluence Scoring Engine
 *
 * Combines all analysis layers into a single BUY / SELL / HOLD signal.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SCORING SYSTEM
 *
 * Each factor contributes a signed score. Raw total is mapped to 0-100 confidence.
 *
 * Factor                       Bullish max    Bearish max
 * ─────────────────────────────────────────────────────────
 * 1. Pattern match              +30            -30
 * 2. Similarity engine          +30            -30
 * 3. Pivot support/resistance   +20            -20
 * 4. Volume confirmation        +10            -10
 * 5. Trend alignment            +10            -10
 * ─────────────────────────────────────────────────────────
 * Raw range:                   -100 to +100
 *
 * Confidence = clamp(rawScore + 50, 0, 100)
 *   raw  +50 → confidence 100   (all factors fully bullish)
 *   raw    0 → confidence  50   (neutral / balanced)
 *   raw  -50 → confidence   0   (all factors fully bearish)
 *
 * Signal thresholds:
 *   confidence ≥ 65  → BUY
 *   confidence 36-64 → HOLD
 *   confidence ≤ 35  → SELL
 * ──────────────────────────────────────────────────────────────────────────────
 */
class SignalService
{
    public function __construct(
        private readonly SimilarityService $similarity
    ) {}

    /**
     * Generate and persist a signal for a symbol on a given date.
     *
     * @param  string $symbol  e.g. 'BSE'
     * @param  string $date    Y-m-d
     * @return StockSignal     The upserted signal record
     */
    public function generate(string $symbol, string $date): StockSignal
    {
        $reasons    = [];
        $scoreBreak = [
            'pattern_score'    => 0,
            'similarity_score' => 0,
            'pivot_score'      => 0,
            'volume_score'     => 0,
            'trend_score'      => 0,
        ];
        $extraMeta  = [];

        // ── LAYER 1: Pattern ──────────────────────────────────────────────────
        [$patScore, $patMeta, $patReason] = $this->layerPattern($symbol, $date);
        $scoreBreak['pattern_score'] = $patScore;
        $extraMeta = array_merge($extraMeta, $patMeta);
        if ($patReason) {
            $reasons[] = $patReason;
        }

        // ── LAYER 2: Similarity ───────────────────────────────────────────────
        $sim      = $this->similarity->analyze($symbol, $date);
        $simScore = $sim['similarity_score'];
        $scoreBreak['similarity_score'] = $simScore;
        $extraMeta['similar_count']       = $sim['match_count'];
        $extraMeta['similar_bullish_pct'] = $sim['bullish_pct'];
        $extraMeta['avg_return_5d']       = $sim['avg_return_5d'];

        if ($sim['match_count'] > 0) {
            $reasons[] = sprintf(
                'Similarity: %d past matches, %s%% bullish (bias: %s, avg 5d: %+.1f%%)',
                $sim['match_count'],
                $sim['bullish_pct'],
                $sim['signal_bias'],
                $sim['avg_return_5d']
            );
        }

        // ── LAYER 3: Pivot ────────────────────────────────────────────────────
        [$pivScore, $pivReason, $pivMeta] = $this->layerPivot($symbol, $date);
        $scoreBreak['pivot_score'] = $pivScore;
        $extraMeta = array_merge($extraMeta, $pivMeta);
        if ($pivReason) {
            $reasons[] = $pivReason;
        }

        // ── LAYER 4: Volume ───────────────────────────────────────────────────
        [$volScore, $volReason] = $this->layerVolume($symbol, $date);
        $scoreBreak['volume_score'] = $volScore;
        if ($volReason) {
            $reasons[] = $volReason;
        }

        // ── LAYER 5: Trend ────────────────────────────────────────────────────
        [$trendScore, $trendReason] = $this->layerTrend($symbol, $date);
        $scoreBreak['trend_score'] = $trendScore;
        if ($trendReason) {
            $reasons[] = $trendReason;
        }

        // ── Final calculation ─────────────────────────────────────────────────
        $rawScore   = $patScore + $simScore + $pivScore + $volScore + $trendScore;
        $confidence = max(0, min(100, $rawScore + 50));

        $signalType = match (true) {
            $confidence >= 65 => StockSignal::BUY,
            $confidence <= 35 => StockSignal::SELL,
            default           => StockSignal::HOLD,
        };

        $scoreBreak['raw_score']  = $rawScore;
        $scoreBreak['confidence'] = $confidence;
        $scoreJson = array_merge($scoreBreak, $extraMeta);

        // ── Persist ───────────────────────────────────────────────────────────
        $signal = StockSignal::updateOrCreate(
            [
                'symbol'      => $symbol,
                'signal_date' => $date,
            ],
            [
                'signal_type' => $signalType,
                'confidence'  => $confidence,
                'reason'      => implode(' | ', array_filter($reasons)),
                'score_json'  => $scoreJson,
            ]
        );

        return $signal;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SCORING LAYERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * LAYER 1 – Pattern
     * Looks for a pattern that was CONFIRMED (end_date) within the last 5 days.
     * Bullish pattern → +30 (scaled by pattern confidence)
     * Bearish pattern → -30 (scaled)
     * No pattern → 0
     */
    private function layerPattern(string $symbol, string $date): array
    {
        $lookbackDate = Carbon::parse($date)->subDays(5)->toDateString();

        $pattern = StockPattern::forSymbol($symbol)
            ->where('end_date', '>=', $lookbackDate)
            ->where('end_date', '<=', $date)
            ->orderByDesc('confidence')
            ->orderByDesc('end_date')
            ->first();

        if (!$pattern) {
            return [0, [], null];
        }

        // Scale score: a pattern with 80% confidence contributes 80% of max ±30
        $baseScore  = (int) round($pattern->confidence / 100 * 30);
        $score      = $pattern->isBullish() ? $baseScore : ($pattern->isBearish() ? -$baseScore : 0);
        $direction  = $pattern->isBullish() ? 'bullish' : ($pattern->isBearish() ? 'bearish' : 'neutral');

        $meta = [
            'pattern'            => $pattern->pattern_type,
            'pattern_confidence' => $pattern->confidence,
            'pattern_end_date'   => $pattern->end_date->format('Y-m-d'),
        ];

        if ($pattern->meta_json) {
            // Pull key levels into the top-level score_json for the API
            foreach (['p1_price', 'p2_price', 'neckline', 'resistance', 'support'] as $key) {
                if (isset($pattern->meta_json[$key])) {
                    $meta["pattern_{$key}"] = $pattern->meta_json[$key];
                }
            }
        }

        $reason = "Pattern: {$pattern->pattern_type} (conf: {$pattern->confidence}%, {$direction})";

        return [$score, $meta, $reason];
    }

    /**
     * LAYER 3 – Pivot Support / Resistance
     * +20 if today's close is within 1.5% of a Pivot LOW  (at support)
     * -20 if today's close is within 1.5% of a Pivot HIGH (at resistance)
     *  0  otherwise
     */
    private function layerPivot(string $symbol, string $date): array
    {
        $today = StockDailyOhlcData::where('symbol', $symbol)
            ->where('trade_date', $date)
            ->where('is_missing', 0)
            ->first();

        if (!$today || $today->close <= 0) {
            return [0, null, []];
        }

        // Check support first (bullish case)
        $nearSupport = StockPivot::forSymbol($symbol)
            ->lows()
            ->beforeOrOn($date)
            ->nearPrice((float) $today->close, 1.5)
            ->orderByDesc('trade_date')
            ->first();

        if ($nearSupport) {
            $reason = sprintf(
                'Near support: %.2f (pivot low on %s)',
                $nearSupport->price,
                $nearSupport->trade_date->format('Y-m-d')
            );
            return [20, $reason, ['nearest_support' => $nearSupport->price]];
        }

        // Check resistance (bearish case)
        $nearResistance = StockPivot::forSymbol($symbol)
            ->highs()
            ->beforeOrOn($date)
            ->nearPrice((float) $today->close, 1.5)
            ->orderByDesc('trade_date')
            ->first();

        if ($nearResistance) {
            $reason = sprintf(
                'Near resistance: %.2f (pivot high on %s)',
                $nearResistance->price,
                $nearResistance->trade_date->format('Y-m-d')
            );
            return [-20, $reason, ['nearest_resistance' => $nearResistance->price]];
        }

        return [0, null, []];
    }

    /**
     * LAYER 4 – Volume
     * +10 if volume spike AND price closed UP (accumulation)
     * -10 if volume spike AND price closed DOWN (distribution)
     *  0  if no volume spike
     */
    private function layerVolume(string $symbol, string $date): array
    {
        $feature = StockFeature::forSymbol($symbol)
            ->where('trade_date', $date)
            ->first();

        if (!$feature || !$feature->volume_spike) {
            return [0, null];
        }

        $today = StockDailyOhlcData::where('symbol', $symbol)
            ->where('trade_date', $date)
            ->where('is_missing', 0)
            ->first();

        if (!$today) {
            return [0, null];
        }

        $isUp = $today->close > $today->open;

        return $isUp
            ? [10,  'Volume spike + bullish close (accumulation)']
            : [-10, 'Volume spike + bearish close (distribution)'];
    }

    /**
     * LAYER 5 – Trend Alignment
     * +10 if trend is UP   (close near 52-week high → uptrend)
     * -10 if trend is DOWN (close near 52-week low  → downtrend)
     *  0  if SIDEWAYS
     */
    private function layerTrend(string $symbol, string $date): array
    {
        $feature = StockFeature::forSymbol($symbol)
            ->where('trade_date', $date)
            ->first();

        if (!$feature) {
            return [0, null];
        }

        return match ($feature->trend) {
            'UP'      => [10,  "Trend: UP (within 10% of 52w high)"],
            'DOWN'    => [-10, "Trend: DOWN (within 10% of 52w low)"],
            default   => [0,    "Trend: SIDEWAYS"],
        };
    }
}
