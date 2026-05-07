<?php

namespace App\Helpers;

class VWAPCalculator
{
    /**
     * Calculate VWAP with bands and dominance detection
     * 
     * @param array $ohlcData Array of OHLC data
     * @param float $stdDevMultiplier Multiplier for standard deviation (default 1.0)
     * @param int $period Period for calculating standard deviation (default 20)
     * @param bool $resetDaily Reset daily
     * @param float $distancePercent Minimum % distance from VWAP for gap signals (default 0.4)
     * @return array Results with VWAP bands and signals
     */
    public static function calculateWithBands(
        array $ohlcData, 
        float $stdDevMultiplier = 1.0, 
        int $period = 20,
        bool $resetDaily = true,
        float $distancePercent = 0.4
    ) {
        $results = [];
        $cumulativeTPV = 0;
        $cumulativeVolume = 0;
        $currentDate = null;
        $dailyCloses = [];
        $dailyVWAPs = [];

        foreach ($ohlcData as $index => $candle) {
            $high = (float)$candle['high'];
            $low = (float)$candle['low'];
            $close = (float)$candle['close'];
            $volume = (int)$candle['volume'];

            // Calculate typical price
            $typicalPrice = ($high + $low + $close) / 3;

            // Reset cumulative values at start of new day if resetDaily is true
            if ($resetDaily && isset($candle['date'])) {
                $candleDate = date('Y-m-d', strtotime($candle['date']));
                if ($currentDate !== $candleDate) {
                    $currentDate = $candleDate;
                    $cumulativeTPV = 0;
                    $cumulativeVolume = 0;
                    $dailyCloses = [];
                    $dailyVWAPs = [];
                }
            }

            // Update cumulative values
            $cumulativeTPV += ($typicalPrice * $volume);
            $cumulativeVolume += $volume;

            // Calculate VWAP
            $vwap = $cumulativeVolume > 0 ? $cumulativeTPV / $cumulativeVolume : null;

            // Store daily data for dominance detection
            if ($vwap !== null) {
                $dailyCloses[] = $close;
                $dailyVWAPs[] = $vwap;
            }

            // Calculate standard deviation and bands
            $startIndex = max(0, $index - $period + 1);
            $periodData = array_slice($ohlcData, $startIndex, min($period, $index + 1));
            $vwapPeriodData = array_slice($results, max(0, count($results) - $period));

            $deviations = [];
            foreach ($periodData as $i => $pCandle) {
                if (isset($vwapPeriodData[$i]['vwap']) && $vwapPeriodData[$i]['vwap'] !== null) {
                    $deviations[] = $pCandle['close'] - $vwapPeriodData[$i]['vwap'];
                }
            }

            $stdDev = !empty($deviations) ? self::standardDeviation($deviations) : 0;
            $upperBand = $vwap !== null ? $vwap + ($stdDev * $stdDevMultiplier) : null;
            $lowerBand = $vwap !== null ? $vwap - ($stdDev * $stdDevMultiplier) : null;

            // Detect VWAP Dominance (Gap-Up/Gap-Down)
            $dominanceSignal = self::detectVWAPDominance($dailyCloses, $dailyVWAPs, $distancePercent);

            $results[] = [
                'id' => $candle['id'] ?? null,
                'date' => $candle['date'] ?? null,
                'vwap' => $vwap,
                'upper_band' => $upperBand,
                'lower_band' => $lowerBand,
                'std_dev' => $stdDev,
                'signal' => $dominanceSignal,
                'typical_price' => $typicalPrice,
                'cumulative_tpv' => $cumulativeTPV,
                'cumulative_volume' => $cumulativeVolume,
                'price_above_vwap' => $vwap ? ($close > $vwap) : null,
                'distance_from_vwap' => $vwap ? (($close - $vwap) / $vwap * 100) : null
            ];
        }

        return $results;
    }

    /**
     * Detect VWAP Dominance Bias (Gap-Up / Gap-Down)
     *
     * @param array $closes Candle close prices for the current day
     * @param array $vwaps VWAP values (same index)
     * @param float $distancePercent Minimum % distance from VWAP (e.g. 0.4%)
     * @return string GAP_UP | GAP_DOWN | HOLD
     */
    private static function detectVWAPDominance(
        array $closes,
        array $vwaps,
        float $distancePercent = 0.4
    ): string {
        $count = count($closes);
        if ($count < 10) {
            return 'HOLD'; // Not enough data
        }

        $aboveVWAP = 0;
        $belowVWAP = 0;

        for ($i = 0; $i < $count; $i++) {
            if (!isset($vwaps[$i])) continue;
            if ($closes[$i] > $vwaps[$i]) {
                $aboveVWAP++;
            } elseif ($closes[$i] < $vwaps[$i]) {
                $belowVWAP++;
            }
        }

        // Percentage of day spent above/below VWAP
        $abovePercent = ($aboveVWAP / $count) * 100;
        $belowPercent = ($belowVWAP / $count) * 100;

        // Late-day candle (last candle)
        $lastClose = $closes[$count - 1];
        $lastVWAP = $vwaps[$count - 1];
        $distanceFromVWAP = (($lastClose - $lastVWAP) / $lastVWAP) * 100;

        /** GAP-UP CONDITIONS */
        if (
            $abovePercent >= 85 &&               // Stayed above VWAP most of the day
            $distanceFromVWAP >= $distancePercent // Closed far above VWAP
        ) {
            return 'GAP_UP';
        }

        /** GAP-DOWN CONDITIONS */
        if (
            $belowPercent >= 85 &&               // Stayed below VWAP most of the day
            abs($distanceFromVWAP) >= $distancePercent &&
            $distanceFromVWAP < 0                // Closed far below VWAP
        ) {
            return 'GAP_DOWN';
        }

        return 'HOLD';
    }

    /**
     * Calculate standard deviation
     * 
     * @param array $values Array of numeric values
     * @return float Standard deviation
     */
    private static function standardDeviation(array $values)
    {
        $count = count($values);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(function($val) use ($mean) {
            return pow($val - $mean, 2);
        }, $values)) / $count;

        return sqrt($variance);
    }

    /**
     * Get VWAP description for tooltips
     * 
     * @return array Description array
     */
    public static function getDescription()
    {
        return [
            'vwap' => 'Volume Weighted Average Price - Shows the average price weighted by volume. Price above VWAP indicates bullish sentiment, below indicates bearish.',
            'reset_daily' => 'Reset VWAP calculation at the start of each trading day (9:15 AM). Recommended for intraday trading.',
            'bands' => 'VWAP Bands show standard deviation channels around VWAP, useful for identifying overbought/oversold conditions.',
            'distance_percent' => 'Minimum percentage distance from VWAP required to trigger Gap-Up/Gap-Down signals. Higher values = more conservative signals.'
        ];
    }
}