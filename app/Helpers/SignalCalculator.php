<?php

namespace App\Helpers;

class SignalCalculator
{
    /**
     * Calculate midpoint
     */
    private static function midpoint($h, $l)
    {
        return ($h + $l) / 2;
    }

    /**
     * Calculate VWAP (Daily Reset)
     */
    private static function calculateVWAP($ohlc)
    {
        $vwap = [];
        $cumPV = 0;
        $cumVol = 0;
        $currentDay = null;

        foreach ($ohlc as $i => $c) {
            $day = date('Y-m-d', strtotime($c['timestamp']));

            if ($day !== $currentDay) {
                $cumPV = 0;
                $cumVol = 0;
                $currentDay = $day;
            }

            $tp = ($c['high'] + $c['low'] + $c['close']) / 3;
            $cumPV += $tp * $c['volume'];
            $cumVol += $c['volume'];

            $vwap[$i] = $cumPV / max($cumVol, 1);
        }

        return $vwap;
    }

    /**
     * Detect SuperTrend (Simplified)
     */
    private static function detectSupertrend($ohlc, $period = 10, $mult = 3)
    {
        $n = count($ohlc);
        if ($n < $period + 2) {
            return "SIDEWAYS";
        }

        $tr = [];
        for ($i = 1; $i < $n; $i++) {
            $tr[] = max(
                $ohlc[$i]['high'] - $ohlc[$i]['low'],
                abs($ohlc[$i]['high'] - $ohlc[$i - 1]['close']),
                abs($ohlc[$i]['low'] - $ohlc[$i - 1]['close'])
            );
        }

        $atr = array_sum(array_slice($tr, -$period)) / $period;
        $hl2 = ($ohlc[$n - 1]['high'] + $ohlc[$n - 1]['low']) / 2;

        $upper = $hl2 + ($mult * $atr);
        $lower = $hl2 - ($mult * $atr);

        if ($ohlc[$n - 1]['close'] > $upper) {
            return "UP";
        }
        if ($ohlc[$n - 1]['close'] < $lower) {
            return "DOWN";
        }

        return "SIDEWAYS";
    }

    /**
     * Generate Merged Signal (VWAP + SuperTrend + Reaction Low)
     */
    public static function generateMergedSignal($ohlc)
    {
        $i = count($ohlc) - 1;
        if ($i < 15) {
            return [
                'signal' => 'NO_TRADE',
                'trend' => 'SIDEWAYS',
                'entry' => null,
                'sl' => null,
                'target' => null,
                'vwap' => null
            ];
        }

        $c = $ohlc[$i];

        // VWAP
        $vwapArr = self::calculateVWAP($ohlc);
        $vwap = $vwapArr[$i];

        // Supertrend
        $trend = self::detectSupertrend($ohlc);

        // Candle anatomy
        $body = abs($c['close'] - $c['open']);
        $upperWick = $c['high'] - max($c['open'], $c['close']);
        $lowerWick = min($c['open'], $c['close']) - $c['low'];
        $mid = self::midpoint($c['high'], $c['low']);

        /* ========= BUY ========= */
        if (
            $trend === "UP" &&
            $c['close'] > $vwap &&
            $c['close'] > $mid &&
            $lowerWick > $body
        ) {
            $entry = $c['high'];
            $sl = $c['low'];
            $risk = $entry - $sl;

            return [
                'signal' => 'BUY',
                'trend' => $trend,
                'entry' => round($entry, 2),
                'sl' => round($sl, 2),
                'target' => round($entry + 2 * $risk, 2),
                'vwap' => round($vwap, 2)
            ];
        }

        /* ========= SELL ========= */
        if (
            $trend === "DOWN" &&
            $c['close'] < $vwap &&
            $c['close'] < $mid &&
            $upperWick > $body
        ) {
            $entry = $c['low'];
            $sl = $c['high'];
            $risk = $sl - $entry;

            return [
                'signal' => 'SELL',
                'trend' => $trend,
                'entry' => round($entry, 2),
                'sl' => round($sl, 2),
                'target' => round($entry - 2 * $risk, 2),
                'vwap' => round($vwap, 2)
            ];
        }

        return [
            'signal' => 'NO_TRADE',
            'trend' => $trend,
            'entry' => null,
            'sl' => null,
            'target' => null,
            'vwap' => round($vwap, 2)
        ];
    }
}