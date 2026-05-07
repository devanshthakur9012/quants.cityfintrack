<?php

namespace App\Helpers;

/**
 * Supertrend with 50 MA Filter
 * 
 * BUY Signal: Supertrend changes to UP AND Close > 50 MA
 * SELL Signal: Supertrend changes to DOWN AND Close < 50 MA
 * Otherwise: HOLD
 */
class SupertrendWithMAOld
{
    private $data;
    private $atrPeriod;
    private $multiplier;
    private $maPeriod;

    private $trArray = [];
    private $results = [];

    public function __construct($ohlcData, $atrPeriod = 10, $multiplier = 3, $maPeriod = 50)
    {
        $this->data = $ohlcData;
        $this->atrPeriod = $atrPeriod;
        $this->multiplier = $multiplier;
        $this->maPeriod = $maPeriod;
    }

    /* ================= TRUE RANGE ================= */

    private function calculateTR($current, $previous)
    {
        return max([
            $current['high'] - $current['low'],
            abs($current['high'] - $previous['close']),
            abs($current['low'] - $previous['close'])
        ]);
    }

    private function buildTRArray()
    {
        for ($i = 1; $i < count($this->data); $i++) {
            $this->trArray[] = $this->calculateTR(
                $this->data[$i],
                $this->data[$i - 1]
            );
        }
    }

    private function getATR($index)
    {
        if ($index < $this->atrPeriod) return null;

        $slice = array_slice(
            $this->trArray,
            $index - $this->atrPeriod,
            $this->atrPeriod
        );

        return array_sum($slice) / $this->atrPeriod;
    }

    /* ================= MOVING AVERAGE ================= */

    private function getMA($index)
    {
        if ($index < $this->maPeriod - 1) return null;

        $sum = 0;
        for ($i = $index - $this->maPeriod + 1; $i <= $index; $i++) {
            $sum += $this->data[$i]['close'];
        }

        return $sum / $this->maPeriod;
    }

    /* ================= MAIN CALCULATION ================= */

    public function calculate()
    {
        $this->buildTRArray();

        $prevSupertrend = null;
        $prevDirection = null;
        $prevUpperBand = null;
        $prevLowerBand = null;

        for ($i = $this->atrPeriod; $i < count($this->data); $i++) {

            $candle = $this->data[$i];
            $close  = $candle['close'];
            $high   = $candle['high'];
            $low    = $candle['low'];

            $atr = $this->getATR($i - 1);
            if ($atr === null) continue;

            /* ====== BASIC BANDS ====== */
            $hl2 = ($high + $low) / 2;
            $basicUpper = $hl2 + ($this->multiplier * $atr);
            $basicLower = $hl2 - ($this->multiplier * $atr);

            /* ====== FINAL BANDS ====== */
            $finalUpper = $basicUpper;
            $finalLower = $basicLower;

            if ($prevUpperBand !== null) {
                $finalUpper = ($basicUpper < $prevUpperBand || $this->data[$i - 1]['close'] > $prevUpperBand)
                    ? $basicUpper
                    : $prevUpperBand;
            }

            if ($prevLowerBand !== null) {
                $finalLower = ($basicLower > $prevLowerBand || $this->data[$i - 1]['close'] < $prevLowerBand)
                    ? $basicLower
                    : $prevLowerBand;
            }

            /* ====== SUPER TREND LOGIC (NON-REPAINT) ====== */
            if ($prevSupertrend === null) {
                if ($close <= $finalUpper) {
                    $supertrend = $finalUpper;
                    $direction = 'DOWN';
                } else {
                    $supertrend = $finalLower;
                    $direction = 'UP';
                }
            }
            else if ($prevSupertrend == $prevUpperBand) {
                if ($close <= $finalUpper) {
                    $supertrend = $finalUpper;
                    $direction = 'DOWN';
                } else {
                    $supertrend = $finalLower;
                    $direction = 'UP';
                }
            }
            else {
                if ($close >= $finalLower) {
                    $supertrend = $finalLower;
                    $direction = 'UP';
                } else {
                    $supertrend = $finalUpper;
                    $direction = 'DOWN';
                }
            }

            /* ====== 50 MA ====== */
            $ma50 = $this->getMA($i);

            /* ====== COMBINED SIGNAL ====== */
            $signal = 'HOLD';

            if ($prevDirection !== null && $prevDirection !== $direction && $ma50 !== null) {

                // BUY CONDITION: Direction UP + Close > MA50
                if ($direction === 'UP' && $close > $ma50) {
                    $signal = 'BUY';
                }

                // SELL CONDITION: Direction DOWN + Close < MA50
                if ($direction === 'DOWN' && $close < $ma50) {
                    $signal = 'SELL';
                }
            }

            $this->results[] = [
                'id'              => $candle['id'] ?? null,
                'date'            => $candle['date'],
                'open'            => round($candle['open'], 2),
                'high'            => round($high, 2),
                'low'             => round($low, 2),
                'close'           => round($close, 2),
                'volume'          => $candle['volume'] ?? 0,
                'atr'             => round($atr, 4),
                'basicUpperBand'  => round($basicUpper, 2),
                'basicLowerBand'  => round($basicLower, 2),
                'supertrend'      => round($supertrend, 2),
                'direction'       => $direction,
                'ma50'            => $ma50 ? round($ma50, 2) : null,
                'signal'          => $signal
            ];

            $prevSupertrend = $supertrend;
            $prevDirection = $direction;
            $prevUpperBand = $finalUpper;
            $prevLowerBand = $finalLower;
        }

        return $this->results;
    }
}