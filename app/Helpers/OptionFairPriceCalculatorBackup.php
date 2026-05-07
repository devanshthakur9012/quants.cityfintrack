<?php

namespace App\Helpers;

use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;

class OptionFairPriceCalculatorBackup
{
    /**
     * Normal Cumulative Distribution Function
     * Abramowitz & Stegun approximation
     */
    private static function normCDF($x): float
    {
        $t = 1 / (1 + 0.2316419 * abs($x));
        $d = 0.3989423 * exp(-$x * $x / 2);
        $prob = $d * $t * (
            0.3193815 +
            $t * (-0.3565638 +
            $t * (1.781478 +
            $t * (-1.821256 +
            $t * 1.330274)))
        );

        return $x > 0 ? 1 - $prob : $prob;
    }

    /**
     * Black-Scholes Fair Price Calculation
     * 
     * Formula:
     * S  = Spot price (Current Future/Spot price)
     * K  = Strike price
     * T  = Time to expiry in years = (Expiry Date - Current Date) / 365
     * r  = Risk-free rate = 0.01 (1%)
     * iv = Implied Volatility (e.g., 0.20 for 20%)
     * 
     * @param float $spot - Current spot/future price
     * @param float $strike - Strike price
     * @param int $daysToExpiry - Days remaining to expiry (Expiry Date - Current Date)
     * @param float $iv - Implied Volatility (e.g., 0.20 for 20%)
     * @param float $riskFreeRate - Risk-free rate (default 0.01 for 1%)
     * @param string $type - 'CE' or 'PE' (CALL or PUT)
     * @return float - Fair price
     */
    public static function calculateFairPrice(
        float $spot,
        float $strike,
        int $daysToExpiry,
        float $iv = 0.20,
        float $riskFreeRate = 0.01,  // ✅ Changed from 0.06 to 0.01 (1%)
        string $type = 'CE'
    ): float {
        // Handle edge cases
        if ($daysToExpiry <= 0) {
            // Intrinsic value only when expired
            if ($type === 'CE') {
                return max(0, $spot - $strike);
            }
            return max(0, $strike - $spot);
        }

        if ($iv <= 0) {
            return 0;
        }

        // ✅ Convert days to years: T = daysToExpiry / 365
        $T = $daysToExpiry / 365.0;

        // Calculate d1 and d2
        $d1 = (
            log($spot / $strike) +
            ($riskFreeRate + 0.5 * $iv * $iv) * $T
        ) / ($iv * sqrt($T));

        $d2 = $d1 - $iv * sqrt($T);

        // Calculate fair price based on type
        if ($type === 'CE' || $type === 'CALL') {
            // Call Option Fair Price
            $fairPrice = $spot * self::normCDF($d1)
                - $strike * exp(-$riskFreeRate * $T) * self::normCDF($d2);
        } else {
            // Put Option Fair Price
            $fairPrice = $strike * exp(-$riskFreeRate * $T) * self::normCDF(-$d2)
                - $spot * self::normCDF(-$d1);
        }

        return round($fairPrice, 2);
    }

    /**
     * Expected Move Method (Fast & Practical)
     * Professional traders use this for quick ATM valuations
     * 
     * @param float $spot - Current spot price
     * @param float $iv - Implied Volatility
     * @param int $days - Days to expiry
     * @return float - Expected move in points
     */
    public static function expectedMove(
        float $spot,
        float $iv,
        int $days
    ): float {
        return round(
            $spot * $iv * sqrt($days / 365),
            2
        );
    }

    /**
     * ATM Fair Premium using Expected Move
     * ATM premium ≈ 40-45% of expected move
     * 
     * @param float $spot
     * @param float $iv
     * @param int $days
     * @return float - Fair premium for ATM option
     */
    public static function atmFairPremium(
        float $spot,
        float $iv,
        int $days
    ): float {
        $move = self::expectedMove($spot, $iv, $days);
        
        // ATM premium typically 40-45% of expected move
        // Using 42% as the sweet spot
        return round($move * 0.42, 2);
    }

    /**
     * Determine if option is Fair/Overpriced/Underpriced
     * 
     * @param float $marketPrice - Current market price (LTP)
     * @param float $fairPrice - Calculated fair price
     * @param float $tolerance - Tolerance percentage (default 15%)
     * @return string - 'FAIR', 'OVERPRICED', or 'UNDERPRICED'
     */
    public static function valuationStatus(
        float $marketPrice,
        float $fairPrice,
        float $tolerance = 0.15
    ): string {
        if ($marketPrice > $fairPrice * (1 + $tolerance)) {
            return 'OVERPRICED'; // Good for SELL
        }

        if ($marketPrice < $fairPrice * (1 - $tolerance)) {
            return 'UNDERPRICED'; // Good for BUY
        }

        return 'FAIR';
    }

    /**
     * Get implied volatility for a symbol
     * This should ideally fetch from NSE/live data
     * For now, using historical/default values
     */
    public static function getImpliedVolatility(string $baseSymbol): float
    {
        // Default IV values based on indices
        $defaultIVs = [
            'NIFTY' => 0.15,        // 15%
            'BANKNIFTY' => 0.18,     // 18%
            'FINNIFTY' => 0.16,      // 16%
            'MIDCPNIFTY' => 0.20,    // 20%
        ];

        return $defaultIVs[$baseSymbol] ?? 0.20; // Default 20%
    }

    /**
     * Get LTP (Last Traded Price) from database or API
     * 
     * @param string $tradingSymbol
     * @return float|null
     */
    public static function getLTP(string $tradingSymbol): ?float
    {
        try {
            // First try: Check if you have live data in your system
            $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                ->first();
            
            if ($instrument && isset($instrument->last_price) && $instrument->last_price > 0) {
                return (float) $instrument->last_price;
            }

            // TODO: Integrate with Zerodha WebSocket or Ticker for live LTP
            // For now, return null if not available
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error fetching LTP for {$tradingSymbol}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Comprehensive option valuation with all metrics
     * Returns array with fair price, status, and recommendations
     */
    public static function comprehensiveValuation(
        float $spot,
        float $strike,
        int $daysToExpiry,
        string $type,
        float $marketPrice = null,
        string $baseSymbol = 'NIFTY'
    ): array {
        $iv = self::getImpliedVolatility($baseSymbol);
        $riskFreeRate = 0.01; // ✅ 1% as per requirement

        // Black-Scholes fair price
        $fairPriceBS = self::calculateFairPrice(
            $spot,
            $strike,
            $daysToExpiry,
            $iv,
            $riskFreeRate,
            $type
        );

        // Expected Move method (for comparison)
        $expectedMove = self::expectedMove($spot, $iv, $daysToExpiry);
        $atmFair = self::atmFairPremium($spot, $iv, $daysToExpiry);

        $result = [
            'fair_price_bs' => $fairPriceBS,
            'expected_move' => $expectedMove,
            'atm_fair_premium' => $atmFair,
            'iv_used' => $iv,
            'days_to_expiry' => $daysToExpiry,
        ];

        // If market price provided, add valuation status
        if ($marketPrice !== null && $marketPrice > 0) {
            $result['market_price'] = $marketPrice;
            $result['valuation_status'] = self::valuationStatus($marketPrice, $fairPriceBS);
            $result['price_difference'] = round($marketPrice - $fairPriceBS, 2);
            $result['price_difference_percent'] = round(
                (($marketPrice - $fairPriceBS) / $fairPriceBS) * 100,
                2
            );

            // Trading recommendation
            if ($result['valuation_status'] === 'UNDERPRICED') {
                $result['recommendation'] = 'GOOD TO BUY';
            } elseif ($result['valuation_status'] === 'OVERPRICED') {
                $result['recommendation'] = 'GOOD TO SELL';
            } else {
                $result['recommendation'] = 'WAIT OR AVOID';
            }
        }

        return $result;
    }
}