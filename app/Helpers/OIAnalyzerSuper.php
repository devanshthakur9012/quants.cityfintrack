<?php

namespace App\Helpers;

class OIAnalyzerSuper
{
    /**
     * Analyze Futures Open Interest changes
     * 
     * @param int $currentOI Current Open Interest
     * @param int $previousOI Previous Open Interest
     * @param string $symbol Symbol name for logging
     * @return array Contains change, change_percent, and signal
     */
    public static function analyzeFuturesOI( 
        int $currentOI, 
        int $previousOI, 
        string $symbol 
    ): array { 
        $delta = $currentOI - $previousOI;
        $deltaPct = $previousOI > 0 ? ($delta / $previousOI) * 100 : 0;
 
        $direction = match(true) { 
            $delta > 0 => 'BULLISH', 
            $delta < 0 => 'BEARISH', 
            default => 'NEUTRAL'
        };

        return [
            'oi_change' => $delta,
            'oi_change_percent' => round($deltaPct, 2),
            'oi_signal' => $direction,
            'previous_oi' => $previousOI
        ];
    }

    /**
     * Get previous OI for a symbol at a specific timestamp
     * 
     * @param int $brokerId
     * @param string $symbol
     * @param string $interval
     * @param string $currentTimestamp
     * @return int|null
     */
    public static function getPreviousOI(
        int $brokerId,
        string $symbol,
        string $interval,
        string $currentTimestamp
    ): ?int {
        $previousRecord = \App\Models\SymbolData::where('broker_api_id', $brokerId)
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('timestamp', '<', $currentTimestamp)
            ->orderBy('timestamp', 'desc')
            ->first();

        return $previousRecord ? (int)$previousRecord->oi : null;
    }
}