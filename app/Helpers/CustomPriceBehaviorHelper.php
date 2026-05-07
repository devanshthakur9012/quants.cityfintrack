<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

/**
 * Custom Price Behavior Analysis Helper
 * 
 * This helper implements custom metrics derived from:
 * - Price
 * - Range
 * - Volume
 * - Time
 * - Acceptance/Rejection behavior
 * 
 * NO traditional indicators are used.
 */
class CustomPriceBehaviorHelper
{
    /**
     * Calculate average of array
     */
    private static function avg(array $arr): float
    {
        return count($arr) ? array_sum($arr) / count($arr) : 0;
    }

    /**
     * 1️⃣ Price Acceptance Ratio (PAR)
     * 
     * Measures whether price is accepted or rejected at higher levels.
     * Formula: PAR = |Close − Open| / (High − Low)
     * 
     * High PAR (>0.65) → strong conviction
     * Low PAR (<0.35) → rejection / indecision
     */
    public static function priceAcceptance(float $open, float $high, float $low, float $close): float
    {
        $range = $high - $low;
        return $range > 0 ? abs($close - $open) / $range : 0;
    }

    /**
     * 2️⃣ Directional Efficiency (DE)
     * 
     * Measures how efficiently price is moving in one direction.
     * Formula: DE = |Close(last) − Close(first)| / Σ |Close(i) − Close(i−1)|
     * 
     * Close to 1.0 → trending
     * Close to 0.3–0.4 → choppy
     */
    public static function directionalEfficiency(array $closes): float
    {
        if (count($closes) < 2) {
            return 0;
        }

        $netMove = abs(end($closes) - $closes[0]);
        $totalMove = 0;

        for ($i = 1; $i < count($closes); $i++) {
            $totalMove += abs($closes[$i] - $closes[$i - 1]);
        }

        return $totalMove > 0 ? $netMove / $totalMove : 0;
    }

    /**
     * 3️⃣ Range Expansion Score (RES)
     * 
     * Detects real expansion vs fake moves.
     * Formula: RES = Current Candle Range / Avg Range (last N candles)
     * 
     * >1.5 → expansion
     * <1.0 → compression
     */
    public static function rangeExpansion(array $highs, array $lows, int $period = 10): float
    {
        $count = count($highs);
        if ($count < 2) {
            return 0;
        }

        $ranges = [];
        $start = max(0, $count - $period - 1);
        
        for ($i = $start; $i < $count - 1; $i++) {
            $ranges[] = $highs[$i] - $lows[$i];
        }

        $avgRange = self::avg($ranges);
        $currentRange = end($highs) - end($lows);

        return $avgRange > 0 ? $currentRange / $avgRange : 0;
    }

    /**
     * 4️⃣ Volume Participation Index (VPI)
     * 
     * Detects institutional participation.
     * Formula: VPI = Current Volume / Avg Volume (last N candles)
     */
    public static function volumeParticipation(array $volumes, int $period = 10): float
    {
        if (empty($volumes)) {
            return 0;
        }

        $slice = array_slice($volumes, -$period, $period);
        $avgVol = self::avg($slice);
        
        return $avgVol > 0 ? end($volumes) / $avgVol : 0;
    }

    /**
     * 5️⃣ Close Location Value (CLV)
     * 
     * Where price closes inside the candle.
     * Formula: CLV = (Close − Low) / (High − Low)
     * 
     * Near 1.0 → strong buying
     * Near 0.0 → strong selling
     */
    public static function closeLocation(float $high, float $low, float $close): float
    {
        $range = $high - $low;
        return $range > 0 ? ($close - $low) / $range : 0.5;
    }

    /**
     * 🧠 Stock Type Classifier
     * 
     * Classifies stock behavior into one of four types:
     * - TREND_DOMINANT: High DE + High PAR
     * - ACCEPTANCE_BASED: Medium DE + High CLV near balance
     * - VOLATILE_EXPANSION: Low DE early + Sudden RES + VPI spike
     * - CHOP_ZONE: Low DE + Low PAR + No sustained RES
     */
    public static function classifyStock(
        array $opens,
        array $highs,
        array $lows,
        array $closes,
        array $volumes,
        int $lookback = 15
    ): array {
        // Get recent data
        $recentCloses = array_slice($closes, -$lookback);
        
        // Calculate all metrics
        $par = self::priceAcceptance(end($opens), end($highs), end($lows), end($closes));
        $de = self::directionalEfficiency($recentCloses);
        $res = self::rangeExpansion($highs, $lows);
        $vpi = self::volumeParticipation($volumes);
        $clv = self::closeLocation(end($highs), end($lows), end($closes));

        // Classification logic
        $type = 'CHOP_ZONE';
        $confidence = 0;

        if ($de > 0.6 && $par > 0.6) {
            $type = 'TREND_DOMINANT';
            $confidence = min(100, ($de + $par) * 50);
        } elseif ($de > 0.4 && $clv > 0.6) {
            $type = 'ACCEPTANCE_BASED';
            $confidence = min(100, ($de + $clv) * 50);
        } elseif ($res > 1.5 && $vpi > 1.5) {
            $type = 'VOLATILE_EXPANSION';
            $confidence = min(100, (($res - 1) + ($vpi - 1)) * 40);
        } else {
            $confidence = max(0, (1 - $de) * 50);
        }

        return [
            'type' => $type,
            'confidence' => round($confidence, 2),
            'metrics' => [
                'par' => round($par, 4),
                'de' => round($de, 4),
                'res' => round($res, 4),
                'vpi' => round($vpi, 4),
                'clv' => round($clv, 4)
            ]
        ];
    }

    /**
     * Generate Trading Signal based on Stock Type
     */
    public static function generateSignal(string $stockType, array $metrics, array $candle): array
    {
        $signal = 'HOLD';
        $reason = '';
        $strength = 0;

        switch ($stockType) {
            case 'TREND_DOMINANT':
                // Trade continuations, ignore reversals
                if ($metrics['par'] > 0.7 && $metrics['de'] > 0.65) {
                    $signal = $metrics['clv'] > 0.6 ? 'BUY' : 'SELL';
                    $reason = 'Strong trend continuation';
                    $strength = min(100, ($metrics['par'] + $metrics['de']) * 50);
                }
                break;

            case 'ACCEPTANCE_BASED':
                // Trade re-acceptance after pullback
                if ($metrics['clv'] > 0.55 && $metrics['clv'] < 0.75) {
                    $signal = $candle['close'] > $candle['open'] ? 'BUY' : 'SELL';
                    $reason = 'Price re-acceptance zone';
                    $strength = abs($metrics['clv'] - 0.5) * 200;
                }
                break;

            case 'VOLATILE_EXPANSION':
                // Trade range expansion with volume
                if ($metrics['res'] > 1.5 && $metrics['vpi'] > 1.3) {
                    $signal = $metrics['clv'] > 0.6 ? 'BUY' : 'SELL';
                    $reason = 'Expansion with volume spike';
                    $strength = min(100, (($metrics['res'] - 1) + ($metrics['vpi'] - 1)) * 40);
                }
                break;

            case 'CHOP_ZONE':
                // Mostly NO TRADE, only late forced expansion
                if ($metrics['res'] > 2.0 && $metrics['vpi'] > 2.0) {
                    $signal = $metrics['clv'] > 0.7 ? 'BUY' : ($metrics['clv'] < 0.3 ? 'SELL' : 'HOLD');
                    $reason = 'Forced breakout from chop';
                    $strength = min(100, (($metrics['res'] - 2) + ($metrics['vpi'] - 2)) * 50);
                }
                break;
        }

        return [
            'signal' => $signal,
            'reason' => $reason,
            'strength' => round($strength, 2)
        ];
    }

    /**
     * Process Collection of Candles for Analysis
     */
    public static function analyzeCandles(Collection $candles): array
    {
        if ($candles->count() < 15) {
            return [
                'error' => 'Insufficient data (need at least 15 candles)',
                'results' => []
            ];
        }

        $opens = $candles->pluck('open')->toArray();
        $highs = $candles->pluck('high')->toArray();
        $lows = $candles->pluck('low')->toArray();
        $closes = $candles->pluck('close')->toArray();
        $volumes = $candles->pluck('volume')->toArray();

        $results = [];
        
        // Analyze each candle with lookback window
        foreach ($candles as $index => $candle) {
            if ($index < 14) {
                continue; // Need at least 15 candles for reliable analysis
            }

            $windowOpens = array_slice($opens, 0, $index + 1);
            $windowHighs = array_slice($highs, 0, $index + 1);
            $windowLows = array_slice($lows, 0, $index + 1);
            $windowCloses = array_slice($closes, 0, $index + 1);
            $windowVolumes = array_slice($volumes, 0, $index + 1);

            // Classify stock behavior
            $classification = self::classifyStock(
                $windowOpens,
                $windowHighs,
                $windowLows,
                $windowCloses,
                $windowVolumes
            );

            // Generate trading signal
            $signalData = self::generateSignal(
                $classification['type'],
                $classification['metrics'],
                [
                    'open' => $candle->open,
                    'high' => $candle->high,
                    'low' => $candle->low,
                    'close' => $candle->close,
                    'volume' => $candle->volume
                ]
            );

            $results[] = [
                'date' => $candle->timestamp->format('Y-m-d H:i:s'),
                'timestamp' => $candle->timestamp->format('Y-m-d H:i:s'),
                'symbol' => $candle->trading_symbol,
                'candle' => [
                    'open' => round($candle->open, 2),
                    'high' => round($candle->high, 2),
                    'low' => round($candle->low, 2),
                    'close' => round($candle->close, 2),
                    'volume' => $candle->volume
                ],
                'classification' => $classification,
                'signal' => $signalData
            ];
        }

        return [
            'error' => null,
            'results' => $results
        ];
    }

    /**
     * Get Stock Type Description
     */
    public static function getStockTypeDescription(string $type): string
    {
        $descriptions = [
            'TREND_DOMINANT' => 'Strong trending behavior - Trade continuations only',
            'ACCEPTANCE_BASED' => 'Balance/VWAP-like - Trade pullback entries',
            'VOLATILE_EXPANSION' => 'Momentum bursts - Trade break + momentum',
            'CHOP_ZONE' => 'Mean reversion - Mostly NO TRADE'
        ];

        return $descriptions[$type] ?? 'Unknown behavior type';
    }

    /**
     * Get recommended trading style
     */
    public static function getRecommendedTradingStyle(string $type): array
    {
        $styles = [
            'TREND_DOMINANT' => [
                'style' => 'Continuation only',
                'entry' => 'On momentum pullbacks in trend direction',
                'avoid' => 'Counter-trend trades',
                'color' => 'success'
            ],
            'ACCEPTANCE_BASED' => [
                'style' => 'Pullback entries',
                'entry' => 'After price rejects extremes',
                'avoid' => 'Breakout trades',
                'color' => 'info'
            ],
            'VOLATILE_EXPANSION' => [
                'style' => 'Break + momentum',
                'entry' => 'On range expansion with volume',
                'avoid' => 'During compression phases',
                'color' => 'warning'
            ],
            'CHOP_ZONE' => [
                'style' => 'No trade',
                'entry' => 'Only extreme forced breakouts',
                'avoid' => 'All normal setups',
                'color' => 'danger'
            ]
        ];

        return $styles[$type] ?? [
            'style' => 'Unknown',
            'entry' => 'N/A',
            'avoid' => 'N/A',
            'color' => 'secondary'
        ];
    }
}