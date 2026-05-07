<?php

namespace App\Services;

class IVAnalysisServiceNew
{
    /**
     * Analyze Futures IV (not typically used, but included for completeness)
     */
    public static function analyzeFuturesIV(
        ?float $todayIV,
        ?float $yesterdayIV,
        string $symbol
    ): array {
        // Futures don't have IV, but keeping structure consistent
        return [
            'daily_iv' => $todayIV,
            'daily_iv_prev' => $yesterdayIV,
            'daily_iv_change' => null,
            'daily_iv_change_pct' => null,
            'iv_direction' => 'N/A',
            'iv_strength' => 'N/A'
        ];
    }

    /**
     * Analyze Call Options IV
     * 
     * LOGIC:
     * - IV ↑ (≥1%) → BULLISH (fresh call buying, bullish sentiment)
     * - IV ↓ (≤-1%) → BEARISH (call premium decay, bearish sentiment)
     * - IV flat (-1% to +1%) → NEUTRAL
     */
    public static function analyzeCallOptionsIV(
        ?float $todayIV,
        ?float $yesterdayIV,
        string $symbol
    ): array {
        if ($todayIV === null || $yesterdayIV === null || $yesterdayIV <= 0) {
            return [
                'daily_iv' => $todayIV,
                'daily_iv_prev' => $yesterdayIV,
                'daily_iv_change' => null,
                'daily_iv_change_pct' => null,
                'iv_direction' => 'NEUTRAL',
                'iv_strength' => 'INSUFFICIENT_DATA'  // Changed from WEAK
            ];
        }

        $delta = $todayIV - $yesterdayIV;
        $deltaPct = ($delta / $yesterdayIV) * 100;

        // CE IV Direction - ✅ CORRECT (1% threshold)
        $direction = match(true) {
            $deltaPct >= 1 => 'BULLISH',   // IV expansion = bullish
            $deltaPct <= -1 => 'BEARISH',  // IV contraction = bearish
            default => 'NEUTRAL'
        };

        $strength = self::calculateIVStrength(
            abs($deltaPct),
            self::getIVThresholds($symbol)
        );

        return [
            'daily_iv' => $todayIV,
            'daily_iv_prev' => $yesterdayIV,
            'daily_iv_change' => round($delta, 4),
            'daily_iv_change_pct' => round($deltaPct, 2),
            'iv_direction' => $direction,
            'iv_strength' => $strength
        ];
    }

    /**
     * Analyze Put Options IV
     * 
     * LOGIC:
     * - IV ↑ (≥1%) → BEARISH (fresh put buying, bearish sentiment)
     * - IV ↓ (≤-1%) → BULLISH (put premium decay, bullish sentiment)
     * - IV flat (-1% to +1%) → NEUTRAL
     */
    public static function analyzePutOptionsIV(
        ?float $todayIV,
        ?float $yesterdayIV,
        string $symbol
    ): array {
        if ($todayIV === null || $yesterdayIV === null || $yesterdayIV <= 0) {
            return [
                'daily_iv' => $todayIV,
                'daily_iv_prev' => $yesterdayIV,
                'daily_iv_change' => null,
                'daily_iv_change_pct' => null,
                'iv_direction' => 'NEUTRAL',
                'iv_strength' => 'INSUFFICIENT_DATA'  // Changed from WEAK
            ];
        }

        $delta = $todayIV - $yesterdayIV;
        $deltaPct = ($delta / $yesterdayIV) * 100;

        // PE IV Direction (REVERSED from CE) - ✅ CORRECT (1% threshold)
        $direction = match(true) {
            $deltaPct >= 1 => 'BEARISH',   // IV expansion = bearish
            $deltaPct <= -1 => 'BULLISH',  // IV contraction = bullish
            default => 'NEUTRAL'
        };

        $strength = self::calculateIVStrength(
            abs($deltaPct),
            self::getIVThresholds($symbol)
        );

        return [
            'daily_iv' => $todayIV,
            'daily_iv_prev' => $yesterdayIV,
            'daily_iv_change' => round($delta, 4),
            'daily_iv_change_pct' => round($deltaPct, 2),
            'iv_direction' => $direction,
            'iv_strength' => $strength
        ];
    }

    /**
     * Calculate IV strength based on percentage change
     */
    private static function calculateIVStrength(float $absDeltaPct, array $thresholds): string
    {
        if ($absDeltaPct < $thresholds[0]) {
            return 'WEAK';
        } elseif ($absDeltaPct < $thresholds[1]) {
            return 'MODERATE';
        } elseif ($absDeltaPct < $thresholds[2]) {
            return 'STRONG';
        } else {
            return 'VERY_STRONG';
        }
    }

    /**
     * IV change thresholds (in percentage) for STRENGTH classification
     * 
     * ✅ FIXED: Changed from [5, 10, 20] to [2, 5, 10]
     * 
     * Direction uses 1% (hardcoded in match statements above)
     * Strength uses these progressive thresholds for quality grading
     */
    private static function getIVThresholds(string $symbol): array
    {
        return match($symbol) {
            'NIFTY' => [2, 5, 10],          // 2% weak, 5% moderate, 10% strong
            'BANKNIFTY' => [2, 6, 12],      // Slightly higher for Bank Nifty
            'FINNIFTY' => [2, 5, 10],
            'MIDCPNIFTY' => [2, 5, 10],
            default => [1.5, 4, 8]          // Stocks: even more sensitive
        };
    }

    /**
     * Combined OI + IV Confluence Analysis
     * This gives the FINAL BTST signal
     */
    public static function getBTSTSignal(
        array $futOiAnalysis,
        array $ceOiAnalysis,
        array $ceIvAnalysis,
        array $peOiAnalysis,
        array $peIvAnalysis
    ): array {
        $futDir = $futOiAnalysis['direction'] ?? 'NEUTRAL';
        $ceOiDir = $ceOiAnalysis['direction'] ?? 'NEUTRAL';
        $ceIvDir = $ceIvAnalysis['iv_direction'] ?? 'NEUTRAL';
        $peOiDir = $peOiAnalysis['direction'] ?? 'NEUTRAL';
        $peIvDir = $peIvAnalysis['iv_direction'] ?? 'NEUTRAL';

        $ceIvStrength = $ceIvAnalysis['iv_strength'] ?? 'WEAK';
        $peIvStrength = $peIvAnalysis['iv_strength'] ?? 'WEAK';

        // 🟢 STRONG BULLISH BTST (BUY CE)
        // Conditions:
        // 1. CE OI unwinding (BULLISH)
        // 2. CE IV rising (BULLISH)
        // 3. FUT not bearish
        // 4. PE IV not rising sharply (no fear)
        if (
            $ceOiDir === 'BULLISH' &&
            $ceIvDir === 'BULLISH' &&
            $futDir !== 'BEARISH' &&
            $peIvDir !== 'BEARISH' &&
            $ceIvStrength !== 'WEAK'
        ) {
            return [
                'btst_signal' => 'BUY_CE',
                'confidence' => self::calculateConfidence([
                    $futDir, $ceOiDir, $ceIvDir, $peIvDir
                ]),
                'reason' => 'CE unwinding + IV expansion + supportive FUT'
            ];
        }

        // 🔴 STRONG BEARISH BTST (BUY PE)
        // Conditions:
        // 1. PE OI unwinding (BEARISH)
        // 2. PE IV rising (BEARISH)
        // 3. FUT not bullish
        // 4. CE IV not rising sharply (no greed)
        if (
            $peOiDir === 'BEARISH' &&
            $peIvDir === 'BEARISH' &&
            $futDir !== 'BULLISH' &&
            $ceIvDir !== 'BULLISH' &&
            $peIvStrength !== 'WEAK'
        ) {
            return [
                'btst_signal' => 'BUY_PE',
                'confidence' => self::calculateConfidence([
                    $futDir, $peOiDir, $peIvDir, $ceIvDir
                ]),
                'reason' => 'PE unwinding + IV expansion + supportive FUT'
            ];
        }

        // ⚠️ CONFLICTING SIGNALS / EVENT RISK
        if (
            $ceIvDir === 'BULLISH' &&
            $peIvDir === 'BEARISH' &&
            $ceIvStrength !== 'WEAK' &&
            $peIvStrength !== 'WEAK'
        ) {
            return [
                'btst_signal' => 'NO_TRADE',
                'confidence' => 0,
                'reason' => 'Both CE and PE IV rising - event risk / range-bound'
            ];
        }

        // 🟡 MODERATE BULLISH
        if (
            ($ceOiDir === 'BULLISH' || $ceIvDir === 'BULLISH') &&
            $futDir !== 'BEARISH'
        ) {
            return [
                'btst_signal' => 'MODERATE_BUY_CE',
                'confidence' => 50,
                'reason' => 'Partial bullish confluence'
            ];
        }

        // 🟡 MODERATE BEARISH
        if (
            ($peOiDir === 'BEARISH' || $peIvDir === 'BEARISH') &&
            $futDir !== 'BULLISH'
        ) {
            return [
                'btst_signal' => 'MODERATE_BUY_PE',
                'confidence' => 50,
                'reason' => 'Partial bearish confluence'
            ];
        }

        // ⚪ NO CLEAR SIGNAL
        return [
            'btst_signal' => 'NO_TRADE',
            'confidence' => 0,
            'reason' => 'No clear confluence'
        ];
    }

    /**
     * Calculate confidence score (0-100)
     */
    private static function calculateConfidence(array $signals): int
    {
        $bullishCount = count(array_filter($signals, fn($s) => $s === 'BULLISH'));
        $bearishCount = count(array_filter($signals, fn($s) => $s === 'BEARISH'));

        $maxCount = max($bullishCount, $bearishCount);
        $totalCount = count($signals);

        return (int) (($maxCount / $totalCount) * 100);
    }
}