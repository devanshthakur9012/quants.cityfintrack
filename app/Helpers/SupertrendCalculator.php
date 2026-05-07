<?php

namespace App\Helpers;
use Illuminate\Support\Facades\Log;

class SupertrendCalculatorOld
{
    private $data;
    private $atrPeriod;
    private $multiplier;
    private $results = [];
    private $trArray = [];

    public function __construct($ohlcData, $atrPeriod = 10, $multiplier = 1.7)
    {
        $this->data = $ohlcData;
        $this->atrPeriod = $atrPeriod;
        $this->multiplier = $multiplier;
    }

    /**
     * Calculate True Range for a candle
     */
    private function calculateTR($current, $previous)
    {
        $high = $current['high'] ?? 0;
        $low = $current['low'] ?? 0;
        $prevClose = $previous ? ($previous['close'] ?? 0) : $current['close'];

        return max([
            $high - $low,
            abs($high - $prevClose),
            abs($low - $prevClose)
        ]);
    }

    /**
     * Calculate all True Range values
     */
    private function calculateTRArray()
    {
        $trs = [];

        for ($i = 1; $i < count($this->data); $i++) {
            $tr = $this->calculateTR($this->data[$i], $this->data[$i - 1]);
            $trs[] = $tr;
        }

        $this->trArray = $trs;
    }

    /**
     * Get Average ATR up to index
     */
    private function getAverageTR($index)
    {
        $startIndex = max(0, $index - $this->atrPeriod);
        $slice = array_slice($this->trArray, $startIndex, $index - $startIndex + 1);
        
        return count($slice) > 0 ? array_sum($slice) / count($slice) : 0;
    }

    /**
     * Main Supertrend calculation
     */
    public function calculateSupertrend()
    {
        $this->calculateTRArray();

        $prevSupertrend = null;
        $prevClose = null;
        $prevDirection = 'UP';
        $prevUpperBand = null;
        $prevLowerBand = null;

        for ($i = $this->atrPeriod; $i < count($this->data); $i++) {
            $current = $this->data[$i];
            $high = $current['high'];
            $low = $current['low'];
            $close = $current['close'];
            $open = $current['open'];
            $volume = $current['volume'];
            $date = $current['date'];

            // Calculate ATR
            $atr = $this->getAverageTR($i);

            // Calculate bands
            $hl2 = ($high + $low) / 2;
            $basicUpperBand = $hl2 + ($this->multiplier * $atr);
            $basicLowerBand = $hl2 - ($this->multiplier * $atr);

            // Adjust bands based on previous values
            $finalUpperBand = $basicUpperBand;
            $finalLowerBand = $basicLowerBand;

            if ($prevUpperBand !== null) {
                $finalUpperBand = $basicUpperBand < $prevUpperBand || $this->data[$i - 1]['close'] > $prevUpperBand 
                    ? $basicUpperBand 
                    : $prevUpperBand;
            }

            if ($prevLowerBand !== null) {
                $finalLowerBand = $basicLowerBand > $prevLowerBand || $this->data[$i - 1]['close'] < $prevLowerBand 
                    ? $basicLowerBand 
                    : $prevLowerBand;
            }

            // Determine supertrend and direction
            $supertrend = null;
            $direction = 'UP';

            if ($prevSupertrend === null) {
                $supertrend = $finalUpperBand;
                $direction = 'DOWN';
            } else {
                if ($close > $prevSupertrend) {
                    $supertrend = $finalLowerBand;
                    $direction = 'UP';
                } else {
                    $supertrend = $finalUpperBand;
                    $direction = 'DOWN';
                }
            }

            // Generate signal (compare current close with supertrend, and previous close with previous supertrend)
            $signal = 'HOLD';

            if ($prevClose !== null && $prevSupertrend !== null) {
                // BUY Signal: Price crosses above supertrend
                if ($prevClose <= $prevSupertrend && $close > $supertrend) {
                    $signal = 'BUY';
                }
                // SELL Signal: Price crosses below supertrend
                else if ($prevClose >= $prevSupertrend && $close < $supertrend) {
                    $signal = 'SELL';
                }
                // If previous direction was different, it's a signal
                else if ($prevDirection !== $direction) {
                    $signal = $direction === 'UP' ? 'BUY' : 'SELL';
                }
            } else if ($prevClose !== null && $prevSupertrend !== null) {
                // First candle signal
                if ($prevDirection !== $direction) {
                    $signal = $direction === 'UP' ? 'BUY' : 'SELL';
                }
            }

            $this->results[] = [
                'id' => $current['id'] ?? null,
                'date' => $date,
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
                'volume' => $volume,
                'atr' => round($atr, 4),
                'basicUpperBand' => round($basicUpperBand, 2),
                'basicLowerBand' => round($basicLowerBand, 2),
                'supertrend' => round($supertrend, 2),
                'direction' => $direction,
                'signal' => $signal
            ];

            $prevSupertrend = $supertrend;
            $prevClose = $close;
            $prevDirection = $direction;
            $prevUpperBand = $finalUpperBand;
            $prevLowerBand = $finalLowerBand;
        }

        return $this->results;
    }
}