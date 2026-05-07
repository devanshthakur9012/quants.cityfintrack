<?php

namespace App\Services;

/**
 * SmartMoneySignalService
 *
 * Implements Smart Money Concept (SMC) analysis:
 *   - Trend structure (HH/HL vs LH/LL)
 *   - Volume spike vs 20-day average
 *   - Liquidity sweep (stop-hunt above highs / below lows)
 *   - Fair Value Gap (FVG) — bullish & bearish imbalance
 *   - Order Block detection (last bearish candle before rally / bullish before drop)
 *   - EMA-20 context
 *
 * Input : array of candles, each with keys: open, high, low, close, volume
 *         ordered oldest → newest, minimum 50 candles required.
 *
 * Output: associative array with all intermediate analysis values + final signal.
 */
class SmartMoneySignalService
{
    /**
     * Run the full SMC analysis pipeline on a candle array.
     *
     * @param  array  $candles  Ordered oldest → newest
     * @return array
     */
    public function analyse(array $candles): array
    {
        $count = count($candles);

        if ($count < 50) {
            return [
                'signal'              => 'NO_DATA',
                'reason'              => 'Need at least 50 candles (have ' . $count . ')',
                'trend'               => null,
                'volume_spike'        => false,
                'avg_volume'          => null,
                'liquidity_sweep_high'=> false,
                'liquidity_sweep_low' => false,
                'recent_high'         => null,
                'recent_low'          => null,
                'fvg_bullish'         => false,
                'fvg_bearish'         => false,
                'order_block_bull'    => null,
                'order_block_bear'    => null,
                'ema20'               => null,
            ];
        }

        $lastIndex = $count - 1;
        $last  = $candles[$lastIndex];
        $prev  = $candles[$lastIndex - 1];
        $prev2 = $candles[$lastIndex - 2];

        // ── Trend structure ───────────────────────────────────────────────────
        $hh = $last['high'] > $prev['high'];
        $hl = $last['low']  > $prev['low'];
        $lh = $last['high'] < $prev['high'];
        $ll = $last['low']  < $prev['low'];

        if ($hh && $hl) {
            $trend = 'UPTREND';
        } elseif ($lh && $ll) {
            $trend = 'DOWNTREND';
        } else {
            $trend = 'SIDEWAYS';
        }

        // ── Volume spike (last vs 20-day avg) ─────────────────────────────────
        $volumePeriod = 20;
        $volumeSum    = 0;
        for ($i = $count - $volumePeriod - 1; $i < $count - 1; $i++) {
            $volumeSum += $candles[$i]['volume'];
        }
        $avgVolume   = $volumeSum / $volumePeriod;
        $volumeSpike = $last['volume'] > ($avgVolume * 1.5);

        // ── Liquidity sweep (stop-hunt) ───────────────────────────────────────
        $recentHigh = PHP_FLOAT_MIN;
        $recentLow  = PHP_FLOAT_MAX;
        $lookback   = 20;

        for ($i = $count - $lookback; $i < $count - 1; $i++) {
            if ($candles[$i]['high'] > $recentHigh) $recentHigh = $candles[$i]['high'];
            if ($candles[$i]['low']  < $recentLow)  $recentLow  = $candles[$i]['low'];
        }

        // Sweep high: wick above recent high but closes BACK below → sell-side trap
        $liquiditySweepHigh = ($last['high'] > $recentHigh && $last['close'] < $recentHigh);
        // Sweep low : wick below recent low but closes BACK above → buy-side trap
        $liquiditySweepLow  = ($last['low']  < $recentLow  && $last['close'] > $recentLow);

        // ── Fair Value Gap ────────────────────────────────────────────────────
        // Bullish FVG: gap between prev2 high and prev low (price moved up too fast)
        $fvgBullish = ($prev2['high'] < $prev['low']);
        // Bearish FVG: gap between prev2 low and prev high (price moved down too fast)
        $fvgBearish = ($prev2['low']  > $prev['high']);

        // ── Order Block detection ─────────────────────────────────────────────
        // Bull OB: last BEARISH candle before a bullish move (demand zone low)
        // Bear OB: last BULLISH candle before a bearish move (supply zone high)
        $orderBlockBull = null;
        $orderBlockBear = null;

        for ($i = $count - 10; $i < $count - 1; $i++) {
            if ($candles[$i]['close'] < $candles[$i]['open']) {
                $orderBlockBull = $candles[$i]['low'];
            }
            if ($candles[$i]['close'] > $candles[$i]['open']) {
                $orderBlockBear = $candles[$i]['high'];
            }
        }

        // ── EMA-20 ────────────────────────────────────────────────────────────
        $emaPeriod = 20;
        $k         = 2 / ($emaPeriod + 1);
        $ema       = $candles[$count - $emaPeriod]['close'];

        for ($i = $count - $emaPeriod + 1; $i < $count; $i++) {
            $ema = ($candles[$i]['close'] * $k) + ($ema * (1 - $k));
        }

        // ── Signal logic ──────────────────────────────────────────────────────
        $signal = 'NO_TRADE';
        $reason = 'No strong institutional setup detected';

        if ($trend === 'UPTREND') {
            // Strong BUY: sweep low + FVG + volume + above EMA
            if ($liquiditySweepLow && $volumeSpike && $fvgBullish && $last['close'] > $ema) {
                $signal = 'BUY';
                $reason = 'Liquidity sweep below support + bullish FVG + volume expansion above EMA';
            }
            // Pullback BUY: price retesting a bullish order block with volume
            if ($orderBlockBull !== null && $last['low'] <= $orderBlockBull && $volumeSpike) {
                $signal = 'BUY_PULLBACK';
                $reason = 'Pullback into bullish order block with volume support';
            }
        }

        if ($trend === 'DOWNTREND') {
            // Strong SELL: sweep high + FVG + volume + below EMA
            if ($liquiditySweepHigh && $volumeSpike && $fvgBearish && $last['close'] < $ema) {
                $signal = 'SELL';
                $reason = 'Liquidity sweep above resistance + bearish FVG + volume expansion below EMA';
            }
            // Pullback SELL: price retesting a bearish order block with volume
            if ($orderBlockBear !== null && $last['high'] >= $orderBlockBear && $volumeSpike) {
                $signal = 'SELL_PULLBACK';
                $reason = 'Pullback into bearish order block with volume support';
            }
        }

        return [
            'signal'               => $signal,
            'reason'               => $reason,
            'trend'                => $trend,
            'volume_spike'         => $volumeSpike,
            'avg_volume'           => round($avgVolume),
            'liquidity_sweep_high' => $liquiditySweepHigh,
            'liquidity_sweep_low'  => $liquiditySweepLow,
            'recent_high'          => round($recentHigh, 2),
            'recent_low'           => round($recentLow,  2),
            'fvg_bullish'          => $fvgBullish,
            'fvg_bearish'          => $fvgBearish,
            'order_block_bull'     => $orderBlockBull ? round($orderBlockBull, 2) : null,
            'order_block_bear'     => $orderBlockBear ? round($orderBlockBear, 2) : null,
            'ema20'                => round($ema, 2),
        ];
    }
}