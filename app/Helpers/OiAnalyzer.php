<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class OiAnalyzer
{
    /**
     * Calculate LTP Change Percentage
     */
    public static function calculateLtpChange($currentClose, $previousClose): float
    {
        if ($previousClose == 0) return 0;
        return (($currentClose - $previousClose) / $previousClose) * 100;
    }

    /**
     * Infer OI Category
     */
    public static function inferOiCategory($ltpChange, $oiChange): string
    {
        if ($ltpChange > 0 && $oiChange > 0) {
            return 'Long Buildup';
        } elseif ($ltpChange < 0 && $oiChange > 0) {
            return 'Short Buildup';
        } elseif ($ltpChange > 0 && $oiChange < 0) {
            return 'Short Covering';
        } elseif ($ltpChange < 0 && $oiChange < 0) {
            return 'Long Unwinding';
        }
        return 'Neutral';
    }

    /**
     * Calculate PCR (Put/Call Ratio)
     */
    public static function calculatePcr(Collection $data): float
    {
        $putOi = $data->filter(fn($d) => str_contains($d->symbol, 'PE'))->sum('oi');
        $callOi = $data->filter(fn($d) => str_contains($d->symbol, 'CE'))->sum('oi');

        if ($callOi == 0) return 0;
        return round($putOi / $callOi, 2);
    }

    /**
     * Calculate Volume Trend (vs 7-day average)
     */
    public static function calculateVolumeTrend(Collection $history, $currentVolume): string
    {
        $avgVolume = $history->avg('volume');
        if ($avgVolume == 0) return 'No Trend';

        if ($currentVolume > $avgVolume * 1.5) return 'Very High';
        if ($currentVolume > $avgVolume) return 'High';
        if ($currentVolume < $avgVolume * 0.5) return 'Low';

        return 'Normal';
    }

    /**
     * Generate Signal Based on Composite Logic
     */
    public static function generateSignal($oiCategory, $pcr, $volumeTrend): string
    {
        if ($oiCategory == 'Long Buildup' && $pcr >= 1 && $volumeTrend == 'Very High') {
            return 'Strong Bullish';
        } elseif ($oiCategory == 'Short Buildup' && $pcr < 1 && $volumeTrend == 'Very High') {
            return 'Strong Bearish';
        } elseif ($oiCategory == 'Short Covering' && $pcr > 1) {
            return 'Moderate Bullish';
        } elseif ($oiCategory == 'Long Unwinding' && $pcr < 1) {
            return 'Moderate Bearish';
        }
        return 'Neutral';
    }

    /**
     * Score logic (optional)
     */
    public static function calculateScore(string $signal): int
    {
        return match ($signal) {
            'Strong Bullish' => 5,
            'Moderate Bullish' => 3,
            'Neutral' => 0,
            'Moderate Bearish' => -3,
            'Strong Bearish' => -5,
            default => 0,
        };
    }
}