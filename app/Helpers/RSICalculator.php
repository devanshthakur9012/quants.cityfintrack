<?php

namespace App\Helpers;

/**
 * RSI (Relative Strength Index) Calculator
 * Industry standard RSI calculation with configurable period (default: 14)
 */
class RSICalculator
{
    /**
     * Calculate RSI for entire dataset
     * 
     * @param array $ohlcData Array of OHLC data with 'close' prices
     * @param int $period RSI period (default: 14)
     * @return array Array of RSI values with null for insufficient data points
     */
    public static function calculateRSI($ohlcData, $period = 14)
    {
        $closes = array_column($ohlcData, 'close');
        $count = count($closes);
        $rsiValues = array_fill(0, $count, null);

        if ($count < $period + 1) {
            return $rsiValues;
        }

        // Calculate price changes
        $changes = [];
        for ($i = 1; $i < $count; $i++) {
            $changes[] = $closes[$i] - $closes[$i - 1];
        }

        // Calculate initial average gain and loss
        $gains = [];
        $losses = [];
        
        for ($i = 0; $i < $period; $i++) {
            $change = $changes[$i];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;

        // Calculate first RSI
        $rs = $avgLoss != 0 ? $avgGain / $avgLoss : 100;
        $rsi = 100 - (100 / (1 + $rs));
        $rsiValues[$period] = round($rsi, 2);

        // Calculate subsequent RSI values using Wilder's smoothing
        for ($i = $period; $i < count($changes); $i++) {
            $change = $changes[$i];
            $gain = $change > 0 ? $change : 0;
            $loss = $change < 0 ? abs($change) : 0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;

            $rs = $avgLoss != 0 ? $avgGain / $avgLoss : 100;
            $rsi = 100 - (100 / (1 + $rs));
            $rsiValues[$i + 1] = round($rsi, 2);
        }

        return $rsiValues;
    }

    /**
     * Calculate RSI with PERSISTENT signals for entire dataset
     * Signal logic: BUY when RSI crosses above oversold, SELL when crosses below overbought
     * Once a signal is generated, it persists until the opposite signal occurs
     * 
     * @param array $ohlcData Array of OHLC data
     * @param int $period RSI period
     * @param float $overbought Overbought threshold
     * @param float $oversold Oversold threshold
     * @return array Array of RSI data with persistent signals
     */
    public static function calculateWithSignals($ohlcData, $period = 14, $overbought = 70, $oversold = 30)
    {
        $rsiValues = self::calculateRSI($ohlcData, $period);
        $results = [];
        $currentSignal = 'HOLD'; // Initial state
        $previousRSI = null;

        foreach ($rsiValues as $index => $rsi) {
            $signal = $currentSignal; // Continue with current signal by default

            if ($rsi !== null && $previousRSI !== null) {
                // BUY Signal: RSI crosses ABOVE oversold threshold (from below to above)
                if ($previousRSI <= $oversold && $rsi > $oversold) {
                    $signal = 'BUY';
                    $currentSignal = 'BUY';
                }
                // SELL Signal: RSI crosses BELOW overbought threshold (from above to below)
                elseif ($previousRSI >= $overbought && $rsi < $overbought) {
                    $signal = 'SELL';
                    $currentSignal = 'SELL';
                }
                // Otherwise, maintain current signal
                else {
                    $signal = $currentSignal;
                }
            } elseif ($rsi !== null && $previousRSI === null) {
                // First valid RSI - set initial signal based on position
                if ($rsi <= $oversold) {
                    $signal = 'BUY';
                    $currentSignal = 'BUY';
                } elseif ($rsi >= $overbought) {
                    $signal = 'SELL';
                    $currentSignal = 'SELL';
                }
            }

            $results[] = [
                'index' => $index,
                'rsi' => $rsi,
                'signal' => $signal
            ];

            $previousRSI = $rsi;
        }

        return $results;
    }
}