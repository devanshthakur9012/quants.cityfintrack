<?php

namespace App\Services\Stock;

use App\Models\StockDailyOhlcData;
use App\Models\StockFeature;
use Illuminate\Support\Collection;

/**
 * SimilarityService  ―  The Statistical Pattern Matching Engine
 *
 * This is the "astrology" idea translated into mathematics.
 *
 * CONCEPT:
 *   "What happened in the past when this stock was in a SIMILAR state to today?"
 *
 * WHAT "SIMILAR STATE" MEANS:
 *   - Same trend (UP / DOWN / SIDEWAYS)
 *   - Same volatility bucket (HIGH / LOW)
 *   - Similar distance from 52-week high (within ±2 percentage points)
 *   - Same RSI zone (optional filter — applies when RSI is in extreme territory)
 *
 * WHAT WE MEASURE:
 *   For each matched past day, we look at the forward return +3d, +5d, +10d.
 *   We compute what % of those past days resulted in positive returns.
 *   If 70%+ = BULLISH bias. If 30%- = BEARISH bias.
 *
 * THIS IS EXACTLY HOW QUANT FUNDS WORK.
 *   No planets. Just statistical pattern repetition + probability.
 */
class SimilarityService
{
    /** Max similar past days to retrieve per query */
    private int $maxMatches = 30;

    /**
     * Tolerance for distance_from_high matching.
     * ±2.0 percentage points means a stock at -3.5% today matches past days
     * where it was between -5.5% and -1.5% from its 52w high.
     */
    private float $distTolerance = 2.0;

    /**
     * Minimum matches needed before we consider the result statistically reliable.
     * Below this we report the data but flag is_reliable = false.
     */
    private int $minReliable = 5;

    /** Forward windows (in trading days) to compute outcomes for. */
    private array $windows = [3, 5, 10];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find historically similar days and compute their outcomes.
     *
     * @param  string $symbol
     * @param  string $currentDate  Y-m-d
     * @return array  Full result with match_count, bullish_pct, avg returns, bias, score
     */
    public function analyze(string $symbol, string $currentDate): array
    {
        $empty = $this->emptyResult();

        // Load today's feature vector
        $current = StockFeature::forSymbol($symbol)
            ->where('trade_date', $currentDate)
            ->first();

        if (!$current) {
            return $empty;
        }

        // Build similarity query — look back ONLY (no future data leakage)
        $query = StockFeature::forSymbol($symbol)
            ->before($currentDate)
            ->where('trend',      $current->trend)
            ->where('volatility', $current->volatility)
            ->whereBetween('distance_from_high', [
                $current->distance_from_high - $this->distTolerance,
                $current->distance_from_high + $this->distTolerance,
            ]);

        // Add RSI zone filter when in extreme territory (adds precision).
        // We skip for NEUTRAL because too many bars are neutral — restricts matches.
        if ($current->rsi_zone && $current->rsi_zone !== 'NEUTRAL') {
            $query->where('rsi_zone', $current->rsi_zone);
        }

        $similarDays = $query
            ->orderBy('trade_date', 'desc')
            ->limit($this->maxMatches)
            ->get();

        if ($similarDays->isEmpty()) {
            return $empty;
        }

        // Compute forward outcomes for each matched day
        $outcomes = $this->computeOutcomes($symbol, $similarDays);

        if (empty($outcomes)) {
            return $empty;
        }

        $n            = count($outcomes);
        $bullishCount = count(array_filter($outcomes, fn($o) => $o['r5'] > 0));
        $bullishPct   = round($bullishCount / $n * 100, 1);

        $avg3  = round(array_sum(array_column($outcomes, 'r3'))  / $n, 2);
        $avg5  = round(array_sum(array_column($outcomes, 'r5'))  / $n, 2);
        $avg10 = round(array_sum(array_column($outcomes, 'r10')) / $n, 2);

        $reliable = $n >= $this->minReliable;

        // Determine directional bias
        $bias = 'NEUTRAL';
        if ($reliable) {
            if ($bullishPct >= 65 && $avg5 > 0) {
                $bias = 'BULLISH';
            } elseif ($bullishPct <= 35 && $avg5 < 0) {
                $bias = 'BEARISH';
            }
        }

        // Translate bias into a score contribution for SignalService (range: -30 to +30)
        // Logic: 65% bullish = +15 score, 80% = +30 score (linear between 50-80%)
        //        35% bullish = -15 score, 20% = -30 score (symmetric)
        $score = 0;
        if ($reliable) {
            $deviation = $bullishPct - 50.0; // +30 = all bullish, -30 = all bearish
            $score     = (int) round(max(-30, min(30, $deviation)));
        }

        return [
            'match_count'      => $n,
            'bullish_count'    => $bullishCount,
            'bullish_pct'      => $bullishPct,
            'avg_return_3d'    => $avg3,
            'avg_return_5d'    => $avg5,
            'avg_return_10d'   => $avg10,
            'is_reliable'      => $reliable,
            'signal_bias'      => $bias,
            'similarity_score' => $score,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * For each past similar day, look up the close N trading days later
     * and compute the percentage return.
     *
     * To avoid N+1 queries, we load all OHLC into memory once, then index
     * by date for O(1) lookups.
     *
     * IMPORTANT: We skip matches that are "too recent" — if a matched day
     * is within 10 trading days of the end of the data, we can't compute
     * a +10d forward return. Including it would skew the bullish% calculation.
     */
    private function computeOutcomes(string $symbol, Collection $similarDays): array
    {
        // Load all OHLC as an ordered date array
        $allOhlc = StockDailyOhlcData::where('symbol', $symbol)
            ->where('is_missing', 0)
            ->where('close', '>', 0)
            ->orderBy('trade_date')
            ->select(['trade_date', 'close'])
            ->get();

        // Build two structures:
        //   $dateToClose: 'Y-m-d' → close price (float)
        //   $dates:       sorted array of all date strings for positional lookups
        $dateToClose = [];
        $dates       = [];

        foreach ($allOhlc as $r) {
            $d              = is_string($r->trade_date) ? $r->trade_date : $r->trade_date->format('Y-m-d');
            $dateToClose[$d] = (float) $r->close;
            $dates[]         = $d;
        }

        $dateIndex  = array_flip($dates); // date string → array position
        $totalDates = count($dates);
        $maxWindow  = max($this->windows); // 10

        $outcomes = [];

        foreach ($similarDays as $day) {
            $d = is_string($day->trade_date)
                ? $day->trade_date
                : $day->trade_date->format('Y-m-d');

            if (!isset($dateIndex[$d])) {
                continue;
            }

            $pos = $dateIndex[$d];

            // Skip if we don't have enough forward data for the largest window
            if ($pos + $maxWindow >= $totalDates) {
                continue;
            }

            $baseClose = $dateToClose[$d];
            if ($baseClose <= 0) {
                continue;
            }

            $r3  = $r5  = $r10 = 0.0;

            foreach ($this->windows as $w) {
                $fwdDate  = $dates[$pos + $w] ?? null;
                $fwdClose = $fwdDate ? ($dateToClose[$fwdDate] ?? 0.0) : 0.0;

                $ret = $fwdClose > 0 ? round(($fwdClose - $baseClose) / $baseClose * 100, 4) : 0.0;

                match ($w) {
                    3  => $r3  = $ret,
                    5  => $r5  = $ret,
                    10 => $r10 = $ret,
                    default => null,
                };
            }

            $outcomes[] = [
                'date' => $d,
                'r3'   => $r3,
                'r5'   => $r5,
                'r10'  => $r10,
            ];
        }

        return $outcomes;
    }

    private function emptyResult(): array
    {
        return [
            'match_count'      => 0,
            'bullish_count'    => 0,
            'bullish_pct'      => 0.0,
            'avg_return_3d'    => 0.0,
            'avg_return_5d'    => 0.0,
            'avg_return_10d'   => 0.0,
            'is_reliable'      => false,
            'signal_bias'      => 'NEUTRAL',
            'similarity_score' => 0,
        ];
    }
}
