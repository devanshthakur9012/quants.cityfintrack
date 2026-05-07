<?php

namespace App\Helpers;

/**
 * Supertrend + 50 MA Strategy (Zerodha-Aligned)
 * 
 * Algorithm:
 * - Calculate Supertrend (ATR-based bands)
 * - Calculate 50-period Simple Moving Average
 * - Generate EVENT signals only on trend flips with MA confirmation
 * 
 * Event Signals:
 * - BUY: Supertrend flips UP AND Close > MA50
 * - SELL: Supertrend flips DOWN AND Close < MA50
 * - null: No flip OR flip without MA confirmation
 * 
 * Direction: Always UP or DOWN (trend state)
 */
class SupertrendWithMA
{
    private array $data;
    private int $atrPeriod;
    private float $multiplier;
    private int $maPeriod;

    private array $trArray = [];
    private array $results = [];

    public function __construct(
        array $ohlcData, 
        int $atrPeriod = 10, 
        float $multiplier = 3.0, 
        int $maPeriod = 50
    ) {
        $this->data = $ohlcData;
        $this->atrPeriod = $atrPeriod;
        $this->multiplier = $multiplier;
        $this->maPeriod = $maPeriod;
    }

    /* ================= TRUE RANGE ================= */

    private function calculateTR(array $current, array $previous): float
    {
        return max([
            $current['high'] - $current['low'],
            abs($current['high'] - $previous['close']),
            abs($current['low'] - $previous['close'])
        ]);
    }

    private function buildTRArray(): void
    {
        for ($i = 1; $i < count($this->data); $i++) {
            $this->trArray[] = $this->calculateTR(
                $this->data[$i],
                $this->data[$i - 1]
            );
        }
    }

    /**
     * Calculate ATR (Average True Range)
     * 
     * IMPORTANT: TR array is zero-indexed but starts from candle 1
     * TR[0] = TR of candle[1]
     * So for candle at index $i, its TR is at $trArray[$i-1]
     */
    private function getATR(int $index): ?float
    {
        $trIndex = $index - 1;

        if ($trIndex < $this->atrPeriod - 1) {
            return null;
        }

        $slice = array_slice(
            $this->trArray,
            $trIndex - $this->atrPeriod + 1,
            $this->atrPeriod
        );

        return array_sum($slice) / $this->atrPeriod;
    }

    /* ================= MOVING AVERAGE ================= */

    /**
     * Calculate Simple Moving Average of Close prices
     */
    private function getMA(int $index): ?float
    {
        if ($index < $this->maPeriod - 1) {
            return null;
        }

        $sum = 0;
        for ($i = $index - $this->maPeriod + 1; $i <= $index; $i++) {
            $sum += $this->data[$i]['close'];
        }

        return $sum / $this->maPeriod;
    }

    /* ================= SUPERTREND CALCULATION ================= */

    public function calculate(): array
    {
        $this->buildTRArray();

        $prevSupertrend = null;
        $prevDirection = null;
        $prevUpperBand = null;
        $prevLowerBand = null;

        for ($i = $this->atrPeriod; $i < count($this->data); $i++) {

            $candle = $this->data[$i];
            $close = $candle['close'];
            $high = $candle['high'];
            $low = $candle['low'];

            // Get ATR for current candle
            $atr = $this->getATR($i);
            if ($atr === null) continue;

            /* ====== BASIC BANDS (HL2 ± Multiplier × ATR) ====== */
            $hl2 = ($high + $low) / 2;
            $basicUpperBand = $hl2 + ($this->multiplier * $atr);
            $basicLowerBand = $hl2 - ($this->multiplier * $atr);

            /* ====== FINAL BANDS (with band continuation logic) ====== */
            $finalUpperBand = $basicUpperBand;
            $finalLowerBand = $basicLowerBand;

            if ($prevUpperBand !== null) {
                $finalUpperBand = ($basicUpperBand < $prevUpperBand || $this->data[$i - 1]['close'] > $prevUpperBand)
                    ? $basicUpperBand
                    : $prevUpperBand;
            }

            if ($prevLowerBand !== null) {
                $finalLowerBand = ($basicLowerBand > $prevLowerBand || $this->data[$i - 1]['close'] < $prevLowerBand)
                    ? $basicLowerBand
                    : $prevLowerBand;
            }

            /* ====== SUPERTREND & DIRECTION (Non-Repainting Logic) ====== */
            
            if ($prevSupertrend === null) {
                // First candle - initialize based on price position
                if ($close <= $finalUpperBand) {
                    $supertrend = $finalUpperBand;
                    $direction = 'DOWN';
                } else {
                    $supertrend = $finalLowerBand;
                    $direction = 'UP';
                }
            } 
            else if ($prevSupertrend == $prevUpperBand) {
                // Was in downtrend (Supertrend = Upper Band)
                if ($close <= $finalUpperBand) {
                    $supertrend = $finalUpperBand;
                    $direction = 'DOWN';
                } else {
                    // Flip to uptrend
                    $supertrend = $finalLowerBand;
                    $direction = 'UP';
                }
            } 
            else {
                // Was in uptrend (Supertrend = Lower Band)
                if ($close >= $finalLowerBand) {
                    $supertrend = $finalLowerBand;
                    $direction = 'UP';
                } else {
                    // Flip to downtrend
                    $supertrend = $finalUpperBand;
                    $direction = 'DOWN';
                }
            }

            /* ====== 50-PERIOD MOVING AVERAGE ====== */
            $ma50 = $this->getMA($i);

            /* ====== EVENT SIGNAL (Only on Trend Flip + MA Confirmation) ====== */
            $eventSignal = null;

            // Only generate signal if:
            // 1. Previous direction exists (not first candle)
            // 2. Direction changed (trend flip)
            // 3. MA50 is available
            if ($prevDirection !== null && $prevDirection !== $direction && $ma50 !== null) {
                
                if ($direction === 'UP' && $close > $ma50) {
                    $eventSignal = 'BUY';
                } 
                else if ($direction === 'DOWN' && $close < $ma50) {
                    $eventSignal = 'SELL';
                }
                // If MA condition not met → eventSignal stays null
            }

            /* ====== STORE RESULT ====== */
            $this->results[] = [
                'id' => $candle['id'] ?? null,
                'date' => $candle['date'],
                'open' => round($candle['open'], 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
                'volume' => $candle['volume'] ?? 0,
                
                // Indicator values
                'atr' => round($atr, 4),
                'basicUpperBand' => round($basicUpperBand, 2),
                'basicLowerBand' => round($basicLowerBand, 2),
                'supertrend' => round($supertrend, 2),
                'direction' => $direction,
                'ma50' => $ma50 ? round($ma50, 2) : null,
                
                // Event signal (null or BUY/SELL)
                'event_signal' => $eventSignal
            ];

            // Update previous values for next iteration
            $prevSupertrend = $supertrend;
            $prevDirection = $direction;
            $prevUpperBand = $finalUpperBand;
            $prevLowerBand = $finalLowerBand;
        }

        return $this->results;
    }

    /**
     * Get results array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}