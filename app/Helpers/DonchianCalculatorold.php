<?php

namespace App\Helpers;

class DonchianCalculatorold
{
    /**
     * Calculate Donchian Channel
     */
    public static function calculateDonchian($ohlc, $period = 10)
    {
        $n = count($ohlc);
        if ($n < $period + 1) {
            return null;
        }

        $highs = array_column(array_slice($ohlc, $n - $period - 1, $period), 'high');
        $lows  = array_column(array_slice($ohlc, $n - $period - 1, $period), 'low');

        $upper = max($highs);
        $lower = min($lows);

        return [
            'upper' => $upper,
            'lower' => $lower,
            'middle' => ($upper + $lower) / 2
        ];
    }

    /**
     * Generate Donchian Signal with configurable risk-reward
     */
    public static function generateDonchianSignal($ohlc, $period = 10, $riskReward = 2.0)
    {
        $n = count($ohlc);
        if ($n < $period + 2) {
            return [
                'signal' => 'NO_TRADE',
                'entry' => null,
                'sl' => null,
                'target' => null,
                'upper' => null,
                'lower' => null
            ];
        }

        $current = $ohlc[$n - 1];
        $prev    = $ohlc[$n - 2];
        $don = self::calculateDonchian($ohlc, $period);

        if (!$don) {
            return [
                'signal' => 'NO_TRADE',
                'entry' => null,
                'sl' => null,
                'target' => null,
                'upper' => null,
                'lower' => null
            ];
        }

        /* ================= BUY ================= */
        if (
            $prev['close'] <= $don['upper'] &&      // was inside or at channel
            $current['close'] > $don['upper']       // breaks upper band
        ) {
            $entry = $current['high'];
            $sl = $don['lower'];
            $risk = $entry - $sl;
            
            return [
                'signal' => 'BUY',
                'entry'  => round($entry, 2),
                'sl'     => round($sl, 2),
                'target' => round($entry + ($riskReward * $risk), 2),
                'upper'  => round($don['upper'], 2),
                'lower'  => round($don['lower'], 2)
            ];
        }

        /* ================= SELL ================= */
        if (
            $prev['close'] >= $don['lower'] &&      // was inside or at channel
            $current['close'] < $don['lower']       // breaks lower band
        ) {
            $entry = $current['low'];
            $sl = $don['upper'];
            $risk = $sl - $entry;
            
            return [
                'signal' => 'SELL',
                'entry'  => round($entry, 2),
                'sl'     => round($sl, 2),
                'target' => round($entry - ($riskReward * $risk), 2),
                'upper'  => round($don['upper'], 2),
                'lower'  => round($don['lower'], 2)
            ];
        }

        // Return channel values even if no trade signal
        return [
            'signal' => 'NO_TRADE',
            'entry' => null,
            'sl' => null,
            'target' => null,
            'upper' => round($don['upper'], 2),
            'lower' => round($don['lower'], 2)
        ];
    }

    /**
     * Calculate Donchian signals for entire dataset
     */
    public static function calculateSignalsForDataset($ohlcData, $period = 10, $riskReward = 2.0)
    {
        $signals = [];
        $dataCount = count($ohlcData);

        for ($i = 0; $i < $dataCount; $i++) {
            // Get data up to current point
            $dataUpToCurrent = array_slice($ohlcData, 0, $i + 1);
            
            // Generate signal for this point
            $signal = self::generateDonchianSignal($dataUpToCurrent, $period, $riskReward);
            
            $signals[] = [
                'index' => $i,
                'date' => $ohlcData[$i]['date'] ?? null,
                'signal' => $signal['signal'],
                'entry' => $signal['entry'],
                'sl' => $signal['sl'],
                'target' => $signal['target'],
                'upper' => $signal['upper'],
                'lower' => $signal['lower']
            ];
        }

        return $signals;
    }
}