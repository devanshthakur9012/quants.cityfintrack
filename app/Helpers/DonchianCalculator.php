<?php

namespace App\Helpers;

/**
 * Donchian Channel Calculator (Industry Standard)
 * Uses separate high and low periods as per Zerodha and trading view standards
 */
class DonchianCalculator
{
    /**
     * Calculate Donchian Channel with separate high/low periods
     * 
     * @param array $ohlc OHLC data array
     * @param int $highPeriod Period for upper channel (default: 20)
     * @param int $lowPeriod Period for lower channel (default: 20)
     * @return array|null Channel values or null if insufficient data
     */
    public static function calculateDonchian($ohlc, $highPeriod = 20, $lowPeriod = 20)
    {
        $n = count($ohlc);
        
        // Need at least period + 1 candles
        if ($n < max($highPeriod, $lowPeriod) + 1) {
            return null;
        }

        // Get the lookback periods
        $highLookback = array_slice($ohlc, $n - $highPeriod - 1, $highPeriod);
        $lowLookback = array_slice($ohlc, $n - $lowPeriod - 1, $lowPeriod);

        $highs = array_column($highLookback, 'high');
        $lows = array_column($lowLookback, 'low');

        $upper = max($highs);
        $lower = min($lows);
        $middle = ($upper + $lower) / 2;

        return [
            'upper' => $upper,
            'lower' => $lower,
            'middle' => $middle
        ];
    }

    /**
     * Generate Donchian breakout signal with configurable risk-reward
     * 
     * @param array $ohlc OHLC data array
     * @param int $highPeriod Period for upper channel
     * @param int $lowPeriod Period for lower channel
     * @param float $riskReward Risk:Reward ratio (default: 2.0)
     * @return array Signal with entry, SL, target, and channel values
     */
    public static function generateDonchianSignal($ohlc, $highPeriod = 20, $lowPeriod = 20, $riskReward = 2.0)
    {
        $n = count($ohlc);
        
        // Need at least 2 candles beyond period
        if ($n < max($highPeriod, $lowPeriod) + 2) {
            return [
                'signal' => 'NO_TRADE',
                'entry' => null,
                'sl' => null,
                'target' => null,
                'upper' => null,
                'lower' => null,
                'middle' => null
            ];
        }

        $current = $ohlc[$n - 1];
        $prev = $ohlc[$n - 2];
        
        $channel = self::calculateDonchian($ohlc, $highPeriod, $lowPeriod);

        if (!$channel) {
            return [
                'signal' => 'NO_TRADE',
                'entry' => null,
                'sl' => null,
                'target' => null,
                'upper' => null,
                'lower' => null,
                'middle' => null
            ];
        }

        /* ================= BUY SIGNAL ================= */
        // Breakout above upper channel
        if ($prev['close'] <= $channel['upper'] && $current['close'] > $channel['upper']) {
            $entry = $current['high'];
            $sl = $channel['lower'];
            $risk = $entry - $sl;
            
            return [
                'signal' => 'BUY',
                'entry' => round($entry, 2),
                'sl' => round($sl, 2),
                'target' => round($entry + ($riskReward * $risk), 2),
                'upper' => round($channel['upper'], 2),
                'lower' => round($channel['lower'], 2),
                'middle' => round($channel['middle'], 2)
            ];
        }

        /* ================= SELL SIGNAL ================= */
        // Breakdown below lower channel
        if ($prev['close'] >= $channel['lower'] && $current['close'] < $channel['lower']) {
            $entry = $current['low'];
            $sl = $channel['upper'];
            $risk = $sl - $entry;
            
            return [
                'signal' => 'SELL',
                'entry' => round($entry, 2),
                'sl' => round($sl, 2),
                'target' => round($entry - ($riskReward * $risk), 2),
                'upper' => round($channel['upper'], 2),
                'lower' => round($channel['lower'], 2),
                'middle' => round($channel['middle'], 2)
            ];
        }

        // No signal but return channel values
        return [
            'signal' => 'NO_TRADE',
            'entry' => null,
            'sl' => null,
            'target' => null,
            'upper' => round($channel['upper'], 2),
            'lower' => round($channel['lower'], 2),
            'middle' => round($channel['middle'], 2)
        ];
    }

    /**
     * Calculate Donchian signals for entire dataset with PERSISTENT signals
     * Signal logic: BUY on upper breakout, SELL on lower breakdown
     * Once a signal is generated, it persists until the opposite signal occurs
     * 
     * @param array $ohlcData Complete OHLC dataset
     * @param int $highPeriod Upper channel period
     * @param int $lowPeriod Lower channel period
     * @param float $riskReward Risk:Reward ratio
     * @return array Array of signals for each data point
     */
    public static function calculateSignalsForDataset($ohlcData, $highPeriod = 20, $lowPeriod = 20, $riskReward = 2.0)
    {
        $signals = [];
        $dataCount = count($ohlcData);
        $currentSignal = 'HOLD'; // Initial state

        for ($i = 0; $i < $dataCount; $i++) {
            // Get data up to current point
            $dataUpToCurrent = array_slice($ohlcData, 0, $i + 1);
            
            // Generate signal for this point
            $signal = self::generateDonchianSignal($dataUpToCurrent, $highPeriod, $lowPeriod, $riskReward);
            
            // Update current signal if a new signal is generated
            if ($signal['signal'] === 'BUY') {
                $currentSignal = 'BUY';
            } elseif ($signal['signal'] === 'SELL') {
                $currentSignal = 'SELL';
            } else {
                // If no new signal, persist the current signal
                $signal['signal'] = $currentSignal;
            }
            
            $signals[] = [
                'index' => $i,
                'date' => $ohlcData[$i]['date'] ?? null,
                'signal' => $signal['signal'],
                'entry' => $signal['entry'],
                'sl' => $signal['sl'],
                'target' => $signal['target'],
                'upper' => $signal['upper'],
                'lower' => $signal['lower'],
                'middle' => $signal['middle']
            ];
        }

        return $signals;
    }
}