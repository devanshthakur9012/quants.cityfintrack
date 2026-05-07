<?php
// app/Helpers/IVCalculator.php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Implied Volatility Calculator using Black-Scholes Model
 * 
 * This class calculates IV using Newton-Raphson iteration method
 * Works for both CALL (CE) and PUT (PE) options
 */
class IVCalculator
{
    /**
     * Calculate Implied Volatility
     * 
     * @param float $marketPrice - Current market price of the option
     * @param float $spotPrice - Current spot/future price
     * @param float $strikePrice - Strike price
     * @param int $daysToExpiry - Days remaining to expiry
     * @param string $optionType - 'CE' or 'PE' (or 'CALL'/'PUT')
     * @param float $riskFreeRate - Risk-free rate (default 6% = 0.06)
     * @param float $initialSigma - Starting guess for IV (default 0.2 = 20%)
     * @return float|null - IV as decimal (e.g., 0.15 = 15%) or null if calculation fails
     */
    public static function calculate(
        float $marketPrice,
        float $spotPrice,
        float $strikePrice,
        int $daysToExpiry,
        string $optionType,
        float $riskFreeRate = 0.06,
        float $initialSigma = 0.2
    ): ?float {
        
        // Validation
        if ($marketPrice <= 0) {
            Log::warning("IV Calculation: Market price must be positive", [
                'marketPrice' => $marketPrice
            ]);
            return null;
        }
        
        if ($spotPrice <= 0 || $strikePrice <= 0) {
            Log::warning("IV Calculation: Spot/Strike must be positive", [
                'spot' => $spotPrice,
                'strike' => $strikePrice
            ]);
            return null;
        }
        
        if ($daysToExpiry <= 0) {
            Log::warning("IV Calculation: Days to expiry must be positive", [
                'days' => $daysToExpiry
            ]);
            return null;
        }
        
        // Convert option type to standard format
        $type = strtoupper($optionType);
        if ($type === 'CE') $type = 'CALL';
        if ($type === 'PE') $type = 'PUT';
        
        if (!in_array($type, ['CALL', 'PUT'])) {
            Log::error("IV Calculation: Invalid option type", [
                'type' => $optionType
            ]);
            return null;
        }
        
        // Convert days to years
        $timeToExpiry = $daysToExpiry / 365.0;
        
        try {
            $iv = self::impliedVolatility(
                $marketPrice,
                $spotPrice,
                $strikePrice,
                $timeToExpiry,
                $riskFreeRate,
                $type,
                $initialSigma
            );
            
            // Sanity check - cap IV at 500%
            if ($iv > 5.0) {
                Log::warning("IV Calculation: Unusually high IV detected, capping at 500%", [
                    'calculated_iv' => $iv * 100,
                    'strike' => $strikePrice,
                    'type' => $type
                ]);
                $iv = 5.0;
            }
            
            return $iv;
            
        } catch (Exception $e) {
            Log::error("IV Calculation failed", [
                'error' => $e->getMessage(),
                'marketPrice' => $marketPrice,
                'spot' => $spotPrice,
                'strike' => $strikePrice,
                'days' => $daysToExpiry,
                'type' => $type
            ]);
            return null;
        }
    }
    
    /**
     * Normal Distribution PDF (Probability Density Function)
     */
    private static function normPDF(float $x): float
    {
        return (1 / sqrt(2 * M_PI)) * exp(-0.5 * $x * $x);
    }
    
    /**
     * Normal Distribution CDF (Cumulative Distribution Function)
     * Uses Abramowitz and Stegun approximation (accurate to 1.5e-7)
     */
    private static function normCDF(float $x): float
    {
        // Use custom erf approximation if erf() function not available
        if (function_exists('erf')) {
            return 0.5 * (1 + erf($x / sqrt(2)));
        }
        
        // Custom erf implementation using Abramowitz and Stegun approximation
        return 0.5 * (1 + self::erfApprox($x / sqrt(2)));
    }
    
    /**
     * Error function approximation (Abramowitz and Stegun)
     * Accurate to 1.5e-7
     */
    private static function erfApprox(float $x): float
    {
        // Constants
        $a1 =  0.254829592;
        $a2 = -0.284496736;
        $a3 =  1.421413741;
        $a4 = -1.453152027;
        $a5 =  1.061405429;
        $p  =  0.3275911;
        
        // Save the sign of x
        $sign = ($x < 0) ? -1 : 1;
        $x = abs($x);
        
        // A&S formula 7.1.26
        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);
        
        return $sign * $y;
    }
    
    /**
     * Black-Scholes Option Pricing Formula
     * 
     * @param float $S - Spot price
     * @param float $K - Strike price
     * @param float $T - Time to expiry (in years)
     * @param float $r - Risk-free rate
     * @param float $sigma - Volatility
     * @param string $type - 'CALL' or 'PUT'
     * @return float - Theoretical option price
     */
    private static function blackScholes(
        float $S,
        float $K,
        float $T,
        float $r,
        float $sigma,
        string $type
    ): float {
        
        if ($T <= 0 || $sigma <= 0) {
            return 0;
        }
        
        $d1 = (log($S / $K) + ($r + 0.5 * $sigma * $sigma) * $T) 
              / ($sigma * sqrt($T));
        $d2 = $d1 - $sigma * sqrt($T);
        
        if ($type === 'CALL') {
            return $S * self::normCDF($d1) - $K * exp(-$r * $T) * self::normCDF($d2);
        } else { // PUT
            return $K * exp(-$r * $T) * self::normCDF(-$d2) - $S * self::normCDF(-$d1);
        }
    }
    
    /**
     * Calculate Vega (sensitivity of option price to volatility)
     * 
     * @param float $S - Spot price
     * @param float $K - Strike price
     * @param float $T - Time to expiry (in years)
     * @param float $r - Risk-free rate
     * @param float $sigma - Volatility
     * @return float - Vega value
     */
    private static function vega(
        float $S,
        float $K,
        float $T,
        float $r,
        float $sigma
    ): float {
        
        if ($T <= 0) {
            return 0;
        }
        
        $d1 = (log($S / $K) + ($r + 0.5 * $sigma * $sigma) * $T)
              / ($sigma * sqrt($T));
        
        return $S * self::normPDF($d1) * sqrt($T);
    }
    
    /**
     * Calculate Implied Volatility using Newton-Raphson Method
     * 
     * @param float $marketPrice - Observed market price
     * @param float $S - Spot price
     * @param float $K - Strike price
     * @param float $T - Time to expiry (in years)
     * @param float $r - Risk-free rate
     * @param string $type - 'CALL' or 'PUT'
     * @param float $initialSigma - Initial guess for sigma
     * @return float - Implied volatility
     */
    private static function impliedVolatility(
        float $marketPrice,
        float $S,
        float $K,
        float $T,
        float $r,
        string $type,
        float $initialSigma = 0.2
    ): float {
        
        $sigma = $initialSigma;
        $maxIterations = 100;
        $tolerance = 1e-6;
        
        for ($i = 0; $i < $maxIterations; $i++) {
            
            // Calculate theoretical price using current sigma
            $price = self::blackScholes($S, $K, $T, $r, $sigma, $type);
            
            // Difference between theoretical and market price
            $diff = $price - $marketPrice;
            
            // Check if we've converged
            if (abs($diff) < $tolerance) {
                return $sigma;
            }
            
            // Calculate Vega
            $v = self::vega($S, $K, $T, $r, $sigma);
            
            // If Vega too small, we can't continue
            if ($v < 1e-6) {
                Log::warning("IV Calculation: Vega too small, stopping iteration", [
                    'iteration' => $i,
                    'sigma' => $sigma,
                    'vega' => $v
                ]);
                break;
            }
            
            // Newton-Raphson update
            $sigma = $sigma - ($diff / $v);
            
            // Safety bounds to prevent explosion
            if ($sigma <= 0) $sigma = 0.001;
            if ($sigma > 5) $sigma = 5;
        }
        
        // Return best guess even if not fully converged
        return $sigma;
    }
    
    /**
     * Batch calculate IV for multiple options
     * 
     * @param array $options - Array of options data
     * @param float $spotPrice - Current spot/future price
     * @param float $riskFreeRate - Risk-free rate
     * @return array - Array with IV values added
     */
    public static function batchCalculate(
        array $options,
        float $spotPrice,
        float $riskFreeRate = 0.06
    ): array {
        
        $results = [];
        
        foreach ($options as $option) {
            
            $iv = self::calculate(
                $option['ltp'] ?? $option['market_price'],
                $spotPrice,
                $option['strike'],
                $option['days_to_expiry'],
                $option['option_type'],
                $riskFreeRate
            );
            
            $option['iv'] = $iv;
            $option['iv_percentage'] = $iv ? round($iv * 100, 2) : null;
            
            $results[] = $option;
        }
        
        return $results;
    }
    
    /**
     * Get risk-free rate based on country
     * Can be extended to fetch live rates
     * 
     * @param string $country - Country code
     * @return float - Risk-free rate
     */
    public static function getRiskFreeRate(string $country = 'IN'): float
    {
        $rates = [
            'IN' => 0.06,  // India - 6% (approximate RBI repo rate)
            'US' => 0.05,  // USA - 5% (approximate Fed rate)
            'UK' => 0.05,  // UK - 5%
            'EU' => 0.04,  // Europe - 4%
        ];
        
        return $rates[$country] ?? 0.06;
    }
}