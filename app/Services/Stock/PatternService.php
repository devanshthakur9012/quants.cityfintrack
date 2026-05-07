<?php

namespace App\Services\Stock;

use App\Models\StockDailyOhlcData;
use App\Models\StockPivot;
use App\Models\StockPattern;
use Illuminate\Support\Collection;

/**
 * PatternService
 *
 * Detects six chart patterns using confirmed pivot points + OHLC candles.
 *
 * Patterns:
 *   1. DOUBLE_TOP        – two pivot highs at same level (bearish reversal)
 *   2. DOUBLE_BOTTOM     – two pivot lows  at same level (bullish reversal)
 *   3. BREAKOUT          – close above resistance pivot + volume spike
 *   4. BREAKDOWN         – close below support pivot   + volume spike
 *   5. SUPPORT_BOUNCE    – price near pivot low + bullish candle
 *   6. RESISTANCE_REJECT – price near pivot high + bearish candle
 */
class PatternService
{
    /**
     * Max % difference for two pivots to be considered "same level".
     * 1.5% is standard industry practice.
     */
    private float $doubleTolerance = 1.5;

    /**
     * Volume must exceed this multiple of the 20-day avg to count as a spike.
     */
    private float $volSpikeRatio = 1.5;

    /**
     * Price must be within this % of a pivot level to count as "near".
     */
    private float $nearLevelPct = 1.5;

    /**
     * How many recent OHLC rows to scan for BREAKOUT / SUPPORT_BOUNCE etc.
     */
    private int $scanWindow = 30;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run ALL detectors for a symbol. Returns total patterns saved/updated.
     */
    public function detectAll(string $symbol): int
    {
        $total = 0;
        $total += $this->detectDoubleTop($symbol);
        $total += $this->detectDoubleBottom($symbol);
        $total += $this->detectBreakout($symbol);
        $total += $this->detectBreakdown($symbol);
        $total += $this->detectSupportBounce($symbol);
        $total += $this->detectResistanceReject($symbol);
        return $total;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. DOUBLE TOP
    // Two consecutive Pivot HIGHs within $doubleTolerance% of each other,
    // with a genuine price dip (≥3%) between them.
    // ─────────────────────────────────────────────────────────────────────────

    public function detectDoubleTop(string $symbol): int
    {
        $pivots = StockPivot::forSymbol($symbol)
            ->highs()
            ->orderBy('trade_date')
            ->get();

        if ($pivots->count() < 2) {
            return 0;
        }

        $saved = 0;

        for ($i = 0; $i < $pivots->count() - 1; $i++) {
            $p1 = $pivots[$i];
            $p2 = $pivots[$i + 1];

            // Must be within tolerance
            $pctDiff = abs($p1->price - $p2->price) / $p1->price * 100;
            if ($pctDiff > $this->doubleTolerance) {
                continue;
            }

            // Must have a real dip between the two highs (≥3%)
            $minLow = $this->getMinLowBetween(
                $symbol,
                $p1->trade_date->format('Y-m-d'),
                $p2->trade_date->format('Y-m-d')
            );

            if ($minLow === null || $minLow >= ($p1->price * 0.97)) {
                continue; // not a real double top — just a flat range
            }

            // Confidence: tighter the two highs = higher confidence
            $confidence = (int) round(80 - ($pctDiff / $this->doubleTolerance) * 15);

            $saved += $this->savePattern(
                $symbol,
                StockPattern::DOUBLE_TOP,
                $p1->trade_date->format('Y-m-d'),
                $p2->trade_date->format('Y-m-d'),
                $confidence,
                [
                    'p1_price' => round($p1->price, 2),
                    'p2_price' => round($p2->price, 2),
                    'pct_diff' => round($pctDiff, 3),
                    'neckline' => round($minLow, 2),
                ]
            );
        }

        return $saved;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. DOUBLE BOTTOM
    // Two consecutive Pivot LOWs within $doubleTolerance% of each other,
    // with a genuine bounce (≥3%) between them.
    // ─────────────────────────────────────────────────────────────────────────

    public function detectDoubleBottom(string $symbol): int
    {
        $pivots = StockPivot::forSymbol($symbol)
            ->lows()
            ->orderBy('trade_date')
            ->get();

        if ($pivots->count() < 2) {
            return 0;
        }

        $saved = 0;

        for ($i = 0; $i < $pivots->count() - 1; $i++) {
            $p1 = $pivots[$i];
            $p2 = $pivots[$i + 1];

            $pctDiff = abs($p1->price - $p2->price) / $p1->price * 100;
            if ($pctDiff > $this->doubleTolerance) {
                continue;
            }

            // Must have a real bounce between the two lows (≥3%)
            $maxHigh = $this->getMaxHighBetween(
                $symbol,
                $p1->trade_date->format('Y-m-d'),
                $p2->trade_date->format('Y-m-d')
            );

            if ($maxHigh === null || $maxHigh <= ($p1->price * 1.03)) {
                continue;
            }

            $confidence = (int) round(80 - ($pctDiff / $this->doubleTolerance) * 15);

            $saved += $this->savePattern(
                $symbol,
                StockPattern::DOUBLE_BOTTOM,
                $p1->trade_date->format('Y-m-d'),
                $p2->trade_date->format('Y-m-d'),
                $confidence,
                [
                    'p1_price' => round($p1->price, 2),
                    'p2_price' => round($p2->price, 2),
                    'pct_diff' => round($pctDiff, 3),
                    'neckline' => round($maxHigh, 2),
                ]
            );
        }

        return $saved;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. BREAKOUT
    // A recent close is above the nearest Pivot HIGH (resistance) with volume.
    // ─────────────────────────────────────────────────────────────────────────

    public function detectBreakout(string $symbol): int
    {
        $recentRows = $this->getRecentOhlc($symbol, $this->scanWindow);
        if ($recentRows->count() < 5) {
            return 0;
        }

        $resistances = StockPivot::forSymbol($symbol)
            ->highs()
            ->orderBy('trade_date', 'desc')
            ->limit(15)
            ->get();

        if ($resistances->isEmpty()) {
            return 0;
        }

        $avgVol = $recentRows->avg('volume');
        $saved  = 0;

        foreach ($recentRows as $row) {
            $rowDate = $this->dateStr($row->trade_date);

            // Find the most recent resistance pivot BEFORE this row
            $resistance = $resistances
                ->filter(fn($p) => $this->dateStr($p->trade_date) < $rowDate)
                ->first(); // already sorted desc, so first() = most recent

            if (!$resistance) {
                continue;
            }

            if ($row->close <= $resistance->price) {
                continue; // not a breakout
            }

            $breakoutPct = ($row->close - $resistance->price) / $resistance->price * 100;
            $volRatio    = $avgVol > 0 ? $row->volume / $avgVol : 0;
            $isVolSpike  = $volRatio >= $this->volSpikeRatio;

            // Base confidence 60. Volume adds 20. Breakout magnitude adds up to 20.
            $confidence = 60;
            if ($isVolSpike) {
                $confidence += 20;
            }
            $confidence += min(20, (int) ($breakoutPct * 4));

            $saved += $this->savePattern(
                $symbol,
                StockPattern::BREAKOUT,
                $resistance->trade_date->format('Y-m-d'),
                $rowDate,
                min(95, $confidence),
                [
                    'resistance'   => round($resistance->price, 2),
                    'close'        => round($row->close, 2),
                    'breakout_pct' => round($breakoutPct, 3),
                    'vol_ratio'    => round($volRatio, 2),
                    'volume_spike' => $isVolSpike,
                ]
            );
        }

        return $saved;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. BREAKDOWN
    // A recent close is below the nearest Pivot LOW (support) with volume.
    // ─────────────────────────────────────────────────────────────────────────

    public function detectBreakdown(string $symbol): int
    {
        $recentRows = $this->getRecentOhlc($symbol, $this->scanWindow);
        if ($recentRows->count() < 5) {
            return 0;
        }

        $supports = StockPivot::forSymbol($symbol)
            ->lows()
            ->orderBy('trade_date', 'desc')
            ->limit(15)
            ->get();

        if ($supports->isEmpty()) {
            return 0;
        }

        $avgVol = $recentRows->avg('volume');
        $saved  = 0;

        foreach ($recentRows as $row) {
            $rowDate = $this->dateStr($row->trade_date);

            $support = $supports
                ->filter(fn($p) => $this->dateStr($p->trade_date) < $rowDate)
                ->first();

            if (!$support) {
                continue;
            }

            if ($row->close >= $support->price) {
                continue; // not a breakdown
            }

            $breakdownPct = ($support->price - $row->close) / $support->price * 100;
            $volRatio     = $avgVol > 0 ? $row->volume / $avgVol : 0;
            $isVolSpike   = $volRatio >= $this->volSpikeRatio;

            $confidence = 60;
            if ($isVolSpike) {
                $confidence += 20;
            }
            $confidence += min(20, (int) ($breakdownPct * 4));

            $saved += $this->savePattern(
                $symbol,
                StockPattern::BREAKDOWN,
                $support->trade_date->format('Y-m-d'),
                $rowDate,
                min(95, $confidence),
                [
                    'support'      => round($support->price, 2),
                    'close'        => round($row->close, 2),
                    'breakdown_pct'=> round($breakdownPct, 3),
                    'vol_ratio'    => round($volRatio, 2),
                    'volume_spike' => $isVolSpike,
                ]
            );
        }

        return $saved;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. SUPPORT BOUNCE
    // Price touches a Pivot LOW level AND shows a bullish candle on that day.
    // ─────────────────────────────────────────────────────────────────────────

    public function detectSupportBounce(string $symbol): int
    {
        $recentRows = $this->getRecentOhlc($symbol, $this->scanWindow);
        if ($recentRows->count() < 3) {
            return 0;
        }

        $supports = StockPivot::forSymbol($symbol)
            ->lows()
            ->orderBy('trade_date', 'desc')
            ->limit(15)
            ->get();

        $saved = 0;

        foreach ($recentRows as $row) {
            $rowDate = $this->dateStr($row->trade_date);

            // Only bullish candles (close > open)
            if ($row->close <= $row->open) {
                continue;
            }

            foreach ($supports as $pivot) {
                if ($this->dateStr($pivot->trade_date) >= $rowDate) {
                    continue;
                }

                // Is the LOW of this bar touching the support level?
                $distPct = abs($row->low - $pivot->price) / $pivot->price * 100;
                if ($distPct > $this->nearLevelPct) {
                    continue;
                }

                $candle     = $this->classifyCandle($row);
                $confidence = 60;

                if (in_array($candle, ['HAMMER', 'BULLISH_ENGULFING', 'BULLISH_MARUBOZU'])) {
                    $confidence += 20;
                }
                if ($distPct < 0.5) {
                    $confidence += 10; // extremely close to support
                }

                $saved += $this->savePattern(
                    $symbol,
                    StockPattern::SUPPORT_BOUNCE,
                    $pivot->trade_date->format('Y-m-d'),
                    $rowDate,
                    min(90, $confidence),
                    [
                        'support'     => round($pivot->price, 2),
                        'low'         => round($row->low, 2),
                        'close'       => round($row->close, 2),
                        'dist_pct'    => round($distPct, 3),
                        'candle_type' => $candle,
                    ]
                );
                break; // match only the most recent support pivot per bar
            }
        }

        return $saved;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. RESISTANCE REJECT
    // Price touches a Pivot HIGH level AND shows a bearish candle on that day.
    // ─────────────────────────────────────────────────────────────────────────

    public function detectResistanceReject(string $symbol): int
    {
        $recentRows = $this->getRecentOhlc($symbol, $this->scanWindow);
        if ($recentRows->count() < 3) {
            return 0;
        }

        $resistances = StockPivot::forSymbol($symbol)
            ->highs()
            ->orderBy('trade_date', 'desc')
            ->limit(15)
            ->get();

        $saved = 0;

        foreach ($recentRows as $row) {
            $rowDate = $this->dateStr($row->trade_date);

            // Only bearish candles (close < open)
            if ($row->close >= $row->open) {
                continue;
            }

            foreach ($resistances as $pivot) {
                if ($this->dateStr($pivot->trade_date) >= $rowDate) {
                    continue;
                }

                $distPct = abs($row->high - $pivot->price) / $pivot->price * 100;
                if ($distPct > $this->nearLevelPct) {
                    continue;
                }

                $candle     = $this->classifyCandle($row);
                $confidence = 60;

                if (in_array($candle, ['SHOOTING_STAR', 'BEARISH_ENGULFING', 'BEARISH_MARUBOZU', 'HANGING_MAN'])) {
                    $confidence += 20;
                }
                if ($distPct < 0.5) {
                    $confidence += 10;
                }

                $saved += $this->savePattern(
                    $symbol,
                    StockPattern::RESISTANCE_REJECT,
                    $pivot->trade_date->format('Y-m-d'),
                    $rowDate,
                    min(90, $confidence),
                    [
                        'resistance'  => round($pivot->price, 2),
                        'high'        => round($row->high, 2),
                        'close'       => round($row->close, 2),
                        'dist_pct'    => round($distPct, 3),
                        'candle_type' => $candle,
                    ]
                );
                break;
            }
        }

        return $saved;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Single-bar candle classifier.
     * Returns a string label stored in meta_json for UI display and confidence scoring.
     */
    private function classifyCandle(object $row): string
    {
        $totalRange = $row->high - $row->low;
        if ($totalRange <= 0) {
            return 'DOJI';
        }

        $body       = abs($row->close - $row->open);
        $bodyRatio  = $body / $totalRange;
        $upperWick  = $row->high - max($row->close, $row->open);
        $lowerWick  = min($row->close, $row->open) - $row->low;

        // Doji: tiny body
        if ($bodyRatio < 0.1) {
            return 'DOJI';
        }

        // Hammer: long lower wick, small body at the top (bullish)
        if ($lowerWick >= ($totalRange * 0.6) && $bodyRatio < 0.3) {
            return 'HAMMER';
        }

        // Shooting Star / Hanging Man: long upper wick, small body at bottom (bearish)
        if ($upperWick >= ($totalRange * 0.6) && $bodyRatio < 0.3) {
            return $row->close < $row->open ? 'HANGING_MAN' : 'SHOOTING_STAR';
        }

        // Marubozu: full body, tiny wicks
        if ($bodyRatio > 0.85) {
            return $row->close > $row->open ? 'BULLISH_MARUBOZU' : 'BEARISH_MARUBOZU';
        }

        return $row->close > $row->open ? 'BULLISH' : 'BEARISH';
    }

    /** Minimum LOW between two dates (exclusive of endpoints). */
    private function getMinLowBetween(string $symbol, string $from, string $to): ?float
    {
        return StockDailyOhlcData::where('symbol', $symbol)
            ->where('trade_date', '>', $from)
            ->where('trade_date', '<', $to)
            ->where('is_missing', 0)
            ->where('low', '>', 0)
            ->min('low');
    }

    /** Maximum HIGH between two dates (exclusive of endpoints). */
    private function getMaxHighBetween(string $symbol, string $from, string $to): ?float
    {
        return StockDailyOhlcData::where('symbol', $symbol)
            ->where('trade_date', '>', $from)
            ->where('trade_date', '<', $to)
            ->where('is_missing', 0)
            ->where('high', '>', 0)
            ->max('high');
    }

    /** Get the most recent $limit OHLC rows, sorted ascending (oldest first). */
    private function getRecentOhlc(string $symbol, int $limit): Collection
    {
        return StockDailyOhlcData::where('symbol', $symbol)
            ->where('is_missing', 0)
            ->where('close', '>', 0)
            ->orderBy('trade_date', 'desc')
            ->limit($limit)
            ->get()
            ->sortBy('trade_date')
            ->values();
    }

    /** Upsert a pattern. Returns 1 if created or changed, 0 if identical. */
    private function savePattern(
        string $symbol,
        string $type,
        string $startDate,
        string $endDate,
        int    $confidence,
        array  $meta
    ): int {
        $record = StockPattern::updateOrCreate(
            [
                'symbol'       => $symbol,
                'pattern_type' => $type,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
            ],
            [
                'confidence' => $confidence,
                'meta_json'  => $meta,
            ]
        );

        return ($record->wasRecentlyCreated || $record->wasChanged()) ? 1 : 0;
    }

    /** Safely convert a date that might be a Carbon or string to Y-m-d string. */
    private function dateStr(mixed $date): string
    {
        return is_string($date) ? $date : $date->format('Y-m-d');
    }
}
