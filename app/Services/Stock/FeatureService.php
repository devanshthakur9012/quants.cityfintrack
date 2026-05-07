<?php

namespace App\Services\Stock;

use App\Models\StockDailyOhlcData;
use App\Models\StockFeature;
use Illuminate\Support\Collection;

/**
 * FeatureService
 *
 * Computes a normalised feature vector for every trading day of a symbol.
 * These vectors are stored in stock_features and consumed by SimilarityService.
 *
 * Features:
 *   trend              UP | DOWN | SIDEWAYS   (based on 52-week position)
 *   volatility         HIGH | LOW             (daily range % of close)
 *   distance_from_high signed %              (close vs rolling 52w high)
 *   distance_from_low  signed %              (close vs rolling 52w low)
 *   volume_spike       bool                  (today > 1.5× 20-day avg volume)
 *   rsi_zone           OVERBOUGHT|OVERSOLD|NEUTRAL  (Wilder 14-period RSI)
 *   rsi_value          float                 (actual RSI value)
 *   features_json      JSON                  (sma_20, sma_50, day_range_pct, etc.)
 */
class FeatureService
{
    private int   $rsiPeriod    = 14;
    private int   $avgVolPeriod = 20;
    private int   $smaShort     = 20;
    private int   $smaLong      = 50;
    private int   $yearWindow   = 252;   // approximate trading days in a year

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate and upsert feature rows for a symbol.
     *
     * @param  string   $symbol   e.g. 'BSE'
     * @param  int|null $days     Only process last N calendar days. null = all history.
     * @return int      Number of rows upserted.
     */
    public function generate(string $symbol, ?int $days = null): int
    {
        // We always load extra history beyond $days so rolling windows
        // (52-week high, RSI, SMA-50, etc.) are accurate even for recent bars.
        $bufferDays = max($this->rsiPeriod, $this->avgVolPeriod, $this->smaLong, $this->yearWindow) + 10;

        $query = StockDailyOhlcData::where('symbol', $symbol)
            ->where('is_missing', 0)
            ->where('close', '>', 0)
            ->orderBy('trade_date');

        if ($days !== null) {
            $cutoff = now()->subDays($days + $bufferDays)->toDateString();
            $query->where('trade_date', '>=', $cutoff);
        }

        $allRows = $query->select(['trade_date', 'open', 'high', 'low', 'close', 'volume'])
                         ->get()
                         ->values(); // ensure 0-based numeric keys

        if ($allRows->count() < $this->rsiPeriod + 1) {
            return 0;
        }

        // Pre-compute RSI for the ENTIRE loaded array in one O(n) pass.
        // (Avoids recomputing for every single row.)
        $rsiArray = $this->computeRsiArray(
            $allRows->pluck('close')->map(fn($v) => (float) $v)->toArray()
        );

        // Determine which rows to actually save (skip buffer-only rows).
        $saveFrom = 0;
        if ($days !== null) {
            $saveCutoff = now()->subDays($days)->toDateString();
            foreach ($allRows as $idx => $row) {
                if ($this->dateStr($row->trade_date) >= $saveCutoff) {
                    $saveFrom = $idx;
                    break;
                }
            }
        }

        $upserted = 0;

        for ($i = $saveFrom; $i < $allRows->count(); $i++) {
            $row = $allRows[$i];

            // ── 52-week rolling window ────────────────────────────────────────
            $wStart  = max(0, $i - $this->yearWindow + 1);
            $window  = $allRows->slice($wStart, $i - $wStart + 1);

            $high52  = (float) $window->max('high');
            $low52   = (float) $window->min('low');
            $close   = (float) $row->close;

            // ── Distances (signed %) ──────────────────────────────────────────
            // distHigh is negative when close < 52w high (normal situation)
            $distHigh = $high52 > 0 ? (($close - $high52) / $high52 * 100) : 0.0;
            // distLow is positive when close > 52w low (normal situation)
            $distLow  = $low52  > 0 ? (($close - $low52)  / $low52  * 100) : 0.0;

            // ── Trend ─────────────────────────────────────────────────────────
            // UP:       close within 10% below 52w high → strong uptrend zone
            // DOWN:     close within 10% above 52w low  → downtrend zone
            // SIDEWAYS: everything in between
            $trend = 'SIDEWAYS';
            if ($distHigh >= -10.0) {
                $trend = 'UP';
            } elseif ($distLow <= 10.0) {
                $trend = 'DOWN';
            }

            // ── Volatility ────────────────────────────────────────────────────
            $dayRangePct = $close > 0
                ? (((float)$row->high - (float)$row->low) / $close * 100)
                : 0.0;
            $volatility = $dayRangePct > 2.0 ? 'HIGH' : 'LOW';

            // ── Volume spike ──────────────────────────────────────────────────
            // Compare today's volume against the 20-day average EXCLUDING today.
            $volWinStart = max(0, $i - $this->avgVolPeriod);
            $avgVol20    = (float) ($allRows->slice($volWinStart, $i - $volWinStart)->avg('volume') ?? 0);
            $todayVol    = (int)   $row->volume;
            $volSpike    = $avgVol20 > 0 && ($todayVol > $avgVol20 * 1.5);

            // ── RSI ───────────────────────────────────────────────────────────
            $rsiVal  = $rsiArray[$i] ?? null;
            $rsiZone = 'NEUTRAL';
            if ($rsiVal !== null) {
                if ($rsiVal >= 70) {
                    $rsiZone = 'OVERBOUGHT';
                } elseif ($rsiVal <= 30) {
                    $rsiZone = 'OVERSOLD';
                }
            }

            // ── SMAs ──────────────────────────────────────────────────────────
            $sma20 = $this->rollingAvgClose($allRows, $i, $this->smaShort);
            $sma50 = $this->rollingAvgClose($allRows, $i, $this->smaLong);

            // ── Close vs Open ─────────────────────────────────────────────────
            $openF          = (float) $row->open;
            $closeVsOpenPct = $openF > 0 ? (($close - $openF) / $openF * 100) : 0.0;

            // ── Upsert ────────────────────────────────────────────────────────
            StockFeature::updateOrCreate(
                [
                    'symbol'     => $symbol,
                    'trade_date' => $this->dateStr($row->trade_date),
                ],
                [
                    'trend'              => $trend,
                    'volatility'         => $volatility,
                    'distance_from_high' => round($distHigh, 4),
                    'distance_from_low'  => round($distLow, 4),
                    'volume_spike'       => $volSpike,
                    'rsi_zone'           => $rsiZone,
                    'rsi_value'          => $rsiVal !== null ? round($rsiVal, 2) : null,
                    'features_json'      => [
                        'day_range_pct'     => round($dayRangePct, 4),
                        'close_vs_open_pct' => round($closeVsOpenPct, 4),
                        'avg_vol_20d'       => (int) round($avgVol20),
                        'volume'            => $todayVol,
                        'sma_20'            => $sma20 !== null ? round($sma20, 2) : null,
                        'sma_50'            => $sma50 !== null ? round($sma50, 2) : null,
                        'above_sma20'       => $sma20 !== null ? ($close > $sma20) : null,
                        'above_sma50'       => $sma50 !== null ? ($close > $sma50) : null,
                        'high_52w'          => round($high52, 2),
                        'low_52w'           => round($low52, 2),
                        'open'              => round($openF, 2),
                        'high'              => round((float)$row->high, 2),
                        'low'               => round((float)$row->low, 2),
                        'close'             => round($close, 2),
                    ],
                ]
            );

            $upserted++;
        }

        return $upserted;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RSI — Wilder's Smoothed Method
    // This is the same formula used by TradingView, Zerodha Kite Charts,
    // and most professional platforms. Values will match to within 0.1.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute RSI for an array of close prices.
     *
     * Returns an array of the same length where:
     *   - Indices 0..($period-1) are null (not enough data yet)
     *   - Index $period onward has a float RSI value
     *
     * Algorithm:
     *   1. Compute N changes: change[i] = close[i] - close[i-1]
     *   2. Split into gains and losses
     *   3. Seed with simple average of first $period gains/losses
     *   4. Apply Wilder smoothing: avg = (prev * (N-1) + today) / N
     *   5. RSI = 100 - 100 / (1 + avgGain/avgLoss)
     */
    private function computeRsiArray(array $closes): array
    {
        $n      = count($closes);
        $result = array_fill(0, $n, null);

        if ($n < $this->rsiPeriod + 1) {
            return $result;
        }

        // Step 1 & 2: build gains/losses arrays (length = $n - 1)
        $gains  = [];
        $losses = [];

        for ($i = 1; $i < $n; $i++) {
            $change   = $closes[$i] - $closes[$i - 1];
            $gains[]  = $change > 0 ? $change : 0.0;
            $losses[] = $change < 0 ? abs($change) : 0.0;
        }

        // Step 3: seed averages with simple mean of first $rsiPeriod changes
        $avgGain = array_sum(array_slice($gains, 0, $this->rsiPeriod)) / $this->rsiPeriod;
        $avgLoss = array_sum(array_slice($losses, 0, $this->rsiPeriod)) / $this->rsiPeriod;

        // RSI at index $rsiPeriod (close index, gains index is $rsiPeriod - 1)
        $result[$this->rsiPeriod] = $this->rsiFromAvgs($avgGain, $avgLoss);

        // Step 4 & 5: Wilder smoothing for the rest
        for ($i = $this->rsiPeriod + 1; $i < $n; $i++) {
            // gains[$i-1] corresponds to close[$i] vs close[$i-1]
            $gIdx    = $i - 1;
            $avgGain = ($avgGain * ($this->rsiPeriod - 1) + $gains[$gIdx])  / $this->rsiPeriod;
            $avgLoss = ($avgLoss * ($this->rsiPeriod - 1) + $losses[$gIdx]) / $this->rsiPeriod;

            $result[$i] = $this->rsiFromAvgs($avgGain, $avgLoss);
        }

        return $result;
    }

    private function rsiFromAvgs(float $avgGain, float $avgLoss): float
    {
        if ($avgLoss == 0.0) {
            return 100.0;
        }
        $rs = $avgGain / $avgLoss;
        return round(100.0 - (100.0 / (1.0 + $rs)), 4);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Rolling simple average of 'close' for the $period bars ending at index $i.
     * Returns null if there are fewer than $period bars available.
     */
    private function rollingAvgClose(Collection $rows, int $i, int $period): ?float
    {
        if ($i < $period - 1) {
            return null;
        }
        return (float) $rows->slice($i - $period + 1, $period)->avg('close');
    }

    /** Normalize a trade_date that might be Carbon or string to Y-m-d string. */
    private function dateStr(mixed $date): string
    {
        return is_string($date) ? $date : $date->format('Y-m-d');
    }
}
