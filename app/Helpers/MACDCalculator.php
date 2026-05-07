<?php

namespace App\Helpers;

/**
 * MACD (Moving Average Convergence Divergence) Calculator
 * Industry standard MACD calculation with configurable periods
 * Default: Fast=12, Slow=26, Signal=9
 */
class MACDCalculator
{
    /**
     * Calculate EMA (Exponential Moving Average)
     * 
     * @param array $data Array of values
     * @param int $period EMA period
     * @return array Array of EMA values
     */
    private static function calculateEMA($data, $period)
    {
        $count = count($data);
        $ema = array_fill(0, $count, null);
        
        if ($count < $period) {
            return $ema;
        }

        // Calculate first SMA as initial EMA
        $sma = array_sum(array_slice($data, 0, $period)) / $period;
        $ema[$period - 1] = $sma;

        // Multiplier: 2 / (period + 1)
        $multiplier = 2 / ($period + 1);

        // Calculate EMA for remaining values
        for ($i = $period; $i < $count; $i++) {
            $ema[$i] = ($data[$i] - $ema[$i - 1]) * $multiplier + $ema[$i - 1];
        }

        return $ema;
    }

    /**
     * Calculate MACD
     * 
     * @param array $ohlcData Array of OHLC data with 'close' prices
     * @param int $fastPeriod Fast EMA period (default: 12)
     * @param int $slowPeriod Slow EMA period (default: 26)
     * @param int $signalPeriod Signal line period (default: 9)
     * @return array Array with MACD line, Signal line, and Histogram
     */
    public static function calculateMACD($ohlcData, $fastPeriod = 12, $slowPeriod = 26, $signalPeriod = 9)
    {
        $closes = array_column($ohlcData, 'close');
        $count = count($closes);

        // Initialize result arrays
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = [
                'macd_line' => null,
                'signal_line' => null,
                'histogram' => null
            ];
        }

        if ($count < $slowPeriod) {
            return $results;
        }

        // Calculate Fast and Slow EMA
        $fastEMA = self::calculateEMA($closes, $fastPeriod);
        $slowEMA = self::calculateEMA($closes, $slowPeriod);

        // Calculate MACD Line (Fast EMA - Slow EMA)
        $macdLine = [];
        for ($i = 0; $i < $count; $i++) {
            if ($fastEMA[$i] !== null && $slowEMA[$i] !== null) {
                $macdLine[$i] = $fastEMA[$i] - $slowEMA[$i];
                $results[$i]['macd_line'] = round($macdLine[$i], 4);
            } else {
                $macdLine[$i] = null;
            }
        }

        // Calculate Signal Line (EMA of MACD Line)
        $validMacdValues = array_filter($macdLine, function($v) { return $v !== null; });
        
        if (count($validMacdValues) >= $signalPeriod) {
            // Get starting index where MACD is not null
            $startIdx = 0;
            for ($i = 0; $i < $count; $i++) {
                if ($macdLine[$i] !== null) {
                    $startIdx = $i;
                    break;
                }
            }

            // Calculate Signal Line EMA
            $macdForSignal = array_slice($macdLine, $startIdx);
            $macdForSignal = array_values(array_filter($macdForSignal, function($v) { return $v !== null; }));
            
            if (count($macdForSignal) >= $signalPeriod) {
                $signalEMA = self::calculateEMA($macdForSignal, $signalPeriod);
                
                $signalIdx = 0;
                for ($i = $startIdx; $i < $count; $i++) {
                    if ($macdLine[$i] !== null && isset($signalEMA[$signalIdx])) {
                        if ($signalEMA[$signalIdx] !== null) {
                            $results[$i]['signal_line'] = round($signalEMA[$signalIdx], 4);
                            
                            // Calculate Histogram (MACD Line - Signal Line)
                            $results[$i]['histogram'] = round($results[$i]['macd_line'] - $results[$i]['signal_line'], 4);
                        }
                        $signalIdx++;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Calculate MACD with PERSISTENT signals for entire dataset
     * Signal logic: BUY when MACD line crosses above Signal line, SELL when crosses below
     * Once a signal is generated, it persists until the opposite signal occurs
     * 
     * @param array $ohlcData Array of OHLC data
     * @param int $fastPeriod Fast EMA period
     * @param int $slowPeriod Slow EMA period
     * @param int $signalPeriod Signal line period
     * @return array Array of MACD data with persistent signals
     */
    public static function calculateWithSignals($ohlcData, $fastPeriod = 12, $slowPeriod = 26, $signalPeriod = 9)
    {
        $macdData = self::calculateMACD($ohlcData, $fastPeriod, $slowPeriod, $signalPeriod);
        $results = [];
        $currentSignal = 'HOLD'; // Initial state
        $previousMACD = null;

        foreach ($macdData as $index => $data) {
            $signal = $currentSignal; // Continue with current signal by default

            // Only generate signals when we have valid MACD and Signal line data
            if ($data['macd_line'] !== null && $data['signal_line'] !== null) {
                
                if ($previousMACD !== null && 
                    $previousMACD['macd_line'] !== null && 
                    $previousMACD['signal_line'] !== null) {
                    
                    // BUY Signal: MACD line crosses ABOVE Signal line
                    if ($previousMACD['macd_line'] <= $previousMACD['signal_line'] && 
                        $data['macd_line'] > $data['signal_line']) {
                        $signal = 'BUY';
                        $currentSignal = 'BUY';
                    }
                    // SELL Signal: MACD line crosses BELOW Signal line
                    elseif ($previousMACD['macd_line'] >= $previousMACD['signal_line'] && 
                            $data['macd_line'] < $data['signal_line']) {
                        $signal = 'SELL';
                        $currentSignal = 'SELL';
                    }
                    // Otherwise, maintain current signal
                    else {
                        $signal = $currentSignal;
                    }
                } else {
                    // First valid MACD data - set initial signal based on position
                    if ($data['macd_line'] > $data['signal_line']) {
                        $signal = 'BUY';
                        $currentSignal = 'BUY';
                    } else {
                        $signal = 'SELL';
                        $currentSignal = 'SELL';
                    }
                }
            }

            $results[] = [
                'index' => $index,
                'macd_line' => $data['macd_line'],
                'signal_line' => $data['signal_line'],
                'histogram' => $data['histogram'],
                'signal' => $signal
            ];

            $previousMACD = $data;
        }

        return $results;
    }
}