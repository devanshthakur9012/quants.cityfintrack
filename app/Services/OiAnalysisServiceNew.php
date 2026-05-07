<?php

namespace App\Services;

class OiAnalysisServiceNew
{
    /**
     * Analyze Futures OI Change
     */
    public static function analyzeFuturesOI(
        int $todayOI,
        int $yesterdayOI,
        string $symbol
    ): array {
        $delta = $todayOI - $yesterdayOI;  // 5973600 - 2671875 = 3301725 
        $deltaPct = $yesterdayOI > 0 ? ($delta / $yesterdayOI) * 100 : 0; // 123.57

        $direction = match(true) {
            $delta > 0 => 'BUILDUP',
            $delta < 0 => 'UNWINDING',
            default => 'NEUTRAL'
        };

        $strength = self::calculateStrength(
            abs($delta),
            self::getFuturesThresholds($symbol)
        );

        return [
            'daily_oi' => $todayOI,
            'daily_oi_prev' => $yesterdayOI,
            'daily_oi_change' => $delta,
            'daily_oi_change_pct' => round($deltaPct, 2),
            'direction' => $direction,
            'strength' => $strength
        ];
    }

    /**
     * Analyze Call Options OI (ATM-1 + ATM + ATM+1 merged)
     * CE ↑ = Bearish (call writing), CE ↓ = Bullish (call unwinding)
     */
    public static function analyzeCallOptionsOI(
        int $todayOISum,
        int $yesterdayOISum,
        string $symbol
    ): array {
        $delta = $todayOISum - $yesterdayOISum;
        $deltaPct = $yesterdayOISum > 0 ? ($delta / $yesterdayOISum) * 100 : 0;

        // CE interpretation: ↑ = BEARISH, ↓ = BULLISH
        $direction = match(true) {
            $delta > 0 => 'BEARISH', 
            $delta < 0 => 'BULLISH', 
            default => 'NEUTRAL'
        };

        $strength = self::calculateStrength(
            abs($delta),
            self::getOptionsThresholds($symbol)
        );

        return [
            'daily_oi' => $todayOISum,
            'daily_oi_prev' => $yesterdayOISum,
            'daily_oi_change' => $delta,
            'daily_oi_change_pct' => round($deltaPct, 2),
            'direction' => $direction,
            'strength' => $strength
        ];
    }

    /**
     * Analyze Put Options OI (ATM-1 + ATM + ATM+1 merged)
     * PE ↑ = Bullish (put writing), PE ↓ = Bearish (put unwinding)
     */
    public static function analyzePutOptionsOI(
        int $todayOISum,
        int $yesterdayOISum,
        string $symbol
    ): array {
        $delta = $todayOISum - $yesterdayOISum;
        $deltaPct = $yesterdayOISum > 0 ? ($delta / $yesterdayOISum) * 100 : 0;

        // PE interpretation: ↑ = BULLISH, ↓ = BEARISH
        $direction = match(true) {
            $delta > 0 => 'BULLISH', // Put writing
            $delta < 0 => 'BEARISH', // Put unwinding
            default => 'NEUTRAL'
        };

        $strength = self::calculateStrength(
            abs($delta),
            self::getOptionsThresholds($symbol)
        );

        return [
            'daily_oi' => $todayOISum,
            'daily_oi_prev' => $yesterdayOISum,
            'daily_oi_change' => $delta,
            'daily_oi_change_pct' => round($deltaPct, 2),
            'direction' => $direction,
            'strength' => $strength
        ];
    }

    /**
     * Calculate final market bias from FUT + CE + PE
     */
    public static function calculateMarketBias(
        array $futAnalysis,
        array $ceAnalysis,
        array $peAnalysis
    ): string {
        $futDir = $futAnalysis['direction'];
        $futStr = $futAnalysis['strength'];
        $ceDir = $ceAnalysis['direction'];
        $peDir = $peAnalysis['direction'];

        // Strong Bullish: FUT buildup + CE bullish + PE bullish
        if (
            $futDir === 'BUILDUP' &&
            $ceDir === 'BULLISH' &&
            $peDir === 'BULLISH' &&
            $futStr !== 'WEAK'
        ) {
            return 'STRONG_BULLISH_' . $futStr;
        }

        // Strong Bearish: FUT buildup + CE bearish + PE bearish
        if (
            $futDir === 'BUILDUP' &&
            $ceDir === 'BEARISH' &&
            $peDir === 'BEARISH' &&
            $futStr !== 'WEAK'
        ) {
            return 'STRONG_BEARISH_' . $futStr;
        }

        // Moderate Bullish: 2 out of 3 agree on bullish
        $bullishCount = 0;
        if ($futDir === 'BUILDUP') $bullishCount++;
        if ($ceDir === 'BULLISH') $bullishCount++;
        if ($peDir === 'BULLISH') $bullishCount++;

        if ($bullishCount >= 2) {
            return 'MODERATE_BULLISH';
        }

        // Moderate Bearish: 2 out of 3 agree on bearish
        $bearishCount = 0;
        if ($futDir === 'UNWINDING') $bearishCount++;
        if ($ceDir === 'BEARISH') $bearishCount++;
        if ($peDir === 'BEARISH') $bearishCount++;

        if ($bearishCount >= 2) {
            return 'MODERATE_BEARISH';
        }

        // Default: Mixed or Range-bound
        return 'MIXED_OR_RANGE';
    }

    /**
     * Calculate strength based on absolute contract change
     */
    private static function calculateStrength(int $absDelta, array $thresholds): string
    {
        if ($absDelta < $thresholds[0]) {
            return 'WEAK';
        } elseif ($absDelta < $thresholds[1]) {
            return 'MODERATE';
        } elseif ($absDelta < $thresholds[2]) {
            return 'STRONG';
        } else {
            return 'VERY_STRONG';
        }
    }

    /**
     * Get futures thresholds by symbol
     */
    private static function getFuturesThresholds(string $symbol): array
    {
        return match($symbol) {
            'NIFTY' => [5000, 20000, 50000],
            'BANKNIFTY' => [3000, 12000, 30000],
            'FINNIFTY' => [2000, 8000, 20000],
            'MIDCPNIFTY' => [2000, 8000, 20000],
            default => [5000, 20000, 50000]
        };
    }

    /**
     * Get options thresholds by symbol
     */
    private static function getOptionsThresholds(string $symbol): array
    {
        return match($symbol) {
            'NIFTY' => [10000, 40000, 100000],
            'BANKNIFTY' => [8000, 30000, 80000],
            'FINNIFTY' => [5000, 20000, 50000],
            'MIDCPNIFTY' => [5000, 20000, 50000],
            default => [10000, 40000, 100000]
        };
    }
}