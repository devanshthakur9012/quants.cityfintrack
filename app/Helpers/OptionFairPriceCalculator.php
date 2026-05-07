<?php

namespace App\Helpers;

use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;

class OptionFairPriceCalculator
{
    /**
     * Normal Cumulative Distribution Function
     * Abramowitz & Stegun approximation
     */
    private static function normCDF(float $x): float
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
     * Standard normal PDF
     */
    private static function normPDF(float $x): float
    {
        return exp(-0.5 * $x * $x) / sqrt(2 * M_PI);
    }

    /**
     * Black-Scholes price (internal, accepts IV as decimal)
     */
    private static function bsPrice(
        float  $spot,
        float  $strike,
        float  $T,
        float  $iv,
        float  $r,
        string $type
    ): float {
        if ($T <= 0 || $iv <= 0) {
            return $type === 'CE' ? max(0.0, $spot - $strike) : max(0.0, $strike - $spot);
        }

        $sqrtT = sqrt($T);
        $d1    = (log($spot / $strike) + ($r + 0.5 * $iv * $iv) * $T) / ($iv * $sqrtT);
        $d2    = $d1 - $iv * $sqrtT;

        if ($type === 'CE' || $type === 'CALL') {
            return $spot * self::normCDF($d1) - $strike * exp(-$r * $T) * self::normCDF($d2);
        }
        return $strike * exp(-$r * $T) * self::normCDF(-$d2) - $spot * self::normCDF(-$d1);
    }

    /**
     * Vega — derivative of BS price w.r.t. IV (used in Newton-Raphson)
     */
    private static function bsVega(float $spot, float $strike, float $T, float $iv, float $r): float
    {
        if ($T <= 0 || $iv <= 0) return 0.0;
        $d1 = (log($spot / $strike) + ($r + 0.5 * $iv * $iv) * $T) / ($iv * sqrt($T));
        return $spot * self::normPDF($d1) * sqrt($T);
    }

    // =========================================================================
    //  PUBLIC: Black-Scholes Fair Price
    // =========================================================================

    /**
     * Black-Scholes Fair Price Calculation
     *
     * @param float  $spot          Current spot/future price
     * @param float  $strike        Strike price
     * @param int    $daysToExpiry  Days remaining to expiry
     * @param float  $iv            Implied Volatility as decimal (e.g. 0.20 for 20%)
     * @param float  $riskFreeRate  Risk-free rate (default 0.01 = 1%)
     * @param string $type          'CE' or 'PE'
     * @return float
     */
    public static function calculateFairPrice(
        float  $spot,
        float  $strike,
        int    $daysToExpiry,
        float  $iv           = 0.20,
        float  $riskFreeRate = 0.01,
        string $type         = 'CE'
    ): float {
        $T = max(0, $daysToExpiry) / 365.0;
        return round(self::bsPrice($spot, $strike, $T, $iv, $riskFreeRate, $type), 2);
    }

    // =========================================================================
    //  IV SOLVER — Newton-Raphson + Bisection fallback
    // =========================================================================

    /**
     * Calculate Implied Volatility from a market price using Newton-Raphson.
     * Falls back to bisection if vega degenerates.
     *
     * This is the CANONICAL IV solver. All other IV methods delegate here.
     *
     * @param float  $spot          Spot / futures price
     * @param float  $strike        Option strike
     * @param int    $daysToExpiry  Days to expiry (must be >= 1)
     * @param float  $marketPrice   Option LTP (must be > 0)
     * @param string $type          'CE' or 'PE'
     * @param float  $riskFreeRate  Risk-free rate as decimal (default 0.01)
     * @return float|null           IV as decimal (e.g. 0.2441 = 24.41%), or null on failure
     */
    public static function calculateIV(
        float  $spot,
        float  $strike,
        int    $daysToExpiry,
        float  $marketPrice,
        string $type         = 'CE',
        float  $riskFreeRate = 0.01
    ): ?float {
        if ($daysToExpiry <= 0 || $marketPrice <= 0 || $spot <= 0 || $strike <= 0) {
            return null;
        }

        $T = $daysToExpiry / 365.0;

        // Intrinsic bound — market price must exceed intrinsic value
        $intrinsic = $type === 'CE'
            ? max(0.0, $spot - $strike)
            : max(0.0, $strike - $spot);

        if ($marketPrice <= $intrinsic) {
            // Deep in-the-money with no time value — return a minimal IV
            return 0.001;
        }

        // Seed: Brenner & Subrahmanyam (1988) approximation
        $ivSeed = sqrt(2 * M_PI / $T) * ($marketPrice / $spot);
        $iv     = max(0.001, min($ivSeed, 5.0));

        // ── Newton-Raphson ─────────────────────────────────────────────────
        for ($i = 0; $i < 100; $i++) {
            $price = self::bsPrice($spot, $strike, $T, $iv, $riskFreeRate, $type);
            $diff  = $price - $marketPrice;

            if (abs($diff) < 0.001) {
                return round($iv, 6);
            }

            $vega = self::bsVega($spot, $strike, $T, $iv, $riskFreeRate);

            if ($vega < 1e-10) {
                break; // vega degenerated → switch to bisection
            }

            $iv = $iv - $diff / $vega;
            $iv = max(0.001, min($iv, 5.0));
        }

        // ── Bisection fallback ─────────────────────────────────────────────
        $lo = 0.001;
        $hi = 5.0;

        for ($i = 0; $i < 200; $i++) {
            $mid      = ($lo + $hi) / 2.0;
            $price    = self::bsPrice($spot, $strike, $T, $mid, $riskFreeRate, $type);
            $diff     = $price - $marketPrice;

            if (abs($diff) < 0.001 || ($hi - $lo) < 0.00001) {
                return round($mid, 6);
            }

            $refPrice = self::bsPrice($spot, $strike, $T, $lo, $riskFreeRate, $type);
            if (($refPrice - $marketPrice) * $diff < 0) {
                $hi = $mid;
            } else {
                $lo = $mid;
            }
        }

        return null;
    }

    /**
     * Short-name alias for calculateIV().
     *
     * The controller uses calcIV() — this alias ensures backward and forward
     * compatibility without any rename required in either file.
     *
     * @see self::calculateIV()
     */
    public static function calcIV(
        float  $spot,
        float  $strike,
        int    $daysToExpiry,
        float  $marketPrice,
        string $type         = 'CE',
        float  $riskFreeRate = 0.01
    ): ?float {
        return self::calculateIV($spot, $strike, $daysToExpiry, $marketPrice, $type, $riskFreeRate);
    }

    // =========================================================================
    //  DYNAMIC IV — 3-tier priority
    // =========================================================================

    /**
     * Tier 1: Try to get IV from the broker instrument record (last_iv field)
     */
    private static function ivFromBroker(string $tradingSymbol): ?float
    {
        try {
            $row = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)->first();
            if ($row) {
                foreach (['iv', 'implied_volatility', 'last_iv'] as $col) {
                    if (isset($row->{$col}) && $row->{$col} > 0) {
                        $val = (float) $row->{$col};
                        // Accept values either as % (e.g. 18.5) or decimal (0.185)
                        return $val > 1 ? $val / 100.0 : $val;
                    }
                }
            }
        } catch (\Throwable $e) {
            // silently ignore — broker data not available
        }
        return null;
    }

    /**
     * Master IV resolver — 3-tier priority
     *
     * 1. Broker API column            (live feed IV)
     * 2. ATM IV passed by caller      (pre-computed from ATM strike price)
     * 3. Static defaults per symbol   (last resort)
     *
     * IMPORTANT: $atmIv must ONLY be computed from the ATM strike option price.
     * For OTM/ITM strikes (ATM+1, ATM-1), the caller must pass the pre-computed
     * ATM IV here — never the OTM/ITM option's own price.
     * This prevents the circular loop: LTP → IV → BS(IV) ≈ LTP → Diff ≈ 0.
     *
     * @param string      $baseSymbol     e.g. 'NIFTY', 'BANKNIFTY'
     * @param string|null $tradingSymbol  e.g. 'NIFTY2527000CE'
     * @param float|null  $atmIv          Pre-computed ATM IV (decimal). Pass for all strikes.
     * @param string      $type           'CE' or 'PE'
     * @return array  ['iv' => float, 'source' => 'broker'|'atm'|'default']
     */
    public static function resolveIV(
        string  $baseSymbol,
        ?string $tradingSymbol = null,
        ?float  $atmIv         = null,
        string  $type          = 'CE'
    ): array {
        // ── Tier 1: Broker API ────────────────────────────────────────────
        if ($tradingSymbol) {
            $brokerIV = self::ivFromBroker($tradingSymbol);
            if ($brokerIV !== null && $brokerIV > 0.001) {
                return ['iv' => $brokerIV, 'source' => 'broker'];
            }
        }

        // ── Tier 2: ATM IV passed by caller ──────────────────────────────
        if ($atmIv !== null && $atmIv > 0.001 && $atmIv < 5.0) {
            return ['iv' => $atmIv, 'source' => 'atm'];
        }

        // ── Tier 3: Static defaults ───────────────────────────────────────
        $defaultIVs = [
            'NIFTY'      => 0.15,
            'BANKNIFTY'  => 0.18,
            'FINNIFTY'   => 0.16,
            'MIDCPNIFTY' => 0.20,
            'SENSEX'     => 0.15,
            'BANKEX'     => 0.18,
        ];
        $iv = $defaultIVs[strtoupper($baseSymbol)] ?? 0.20;

        return ['iv' => $iv, 'source' => 'default'];
    }

    /**
     * Calculate ATM IV from the ATM CE price (preferred) or PE price.
     * This single IV is reused for ALL nearby strikes (ATM, ATM±1).
     *
     * Returns null if IV cannot be solved — caller should use static default.
     */
    public static function calcAtmIV(
        float  $spot,
        float  $atmStrike,
        int    $daysToExpiry,
        ?float $atmCeLtp,
        ?float $atmPeLtp,
        float  $riskFreeRate = 0.01
    ): ?float {
        // Prefer CE (more liquid at ATM) — fall back to PE
        foreach ([['CE', $atmCeLtp], ['PE', $atmPeLtp]] as [$type, $ltp]) {
            if ($ltp && $ltp > 0) {
                $iv = self::calculateIV($spot, $atmStrike, $daysToExpiry, $ltp, $type, $riskFreeRate);
                if ($iv !== null && $iv > 0.001 && $iv < 5.0) {
                    return $iv;
                }
            }
        }
        return null;
    }

    /**
     * Legacy-compatible: returns just the IV float
     */
    public static function getImpliedVolatility(string $baseSymbol): float
    {
        return self::resolveIV($baseSymbol)['iv'];
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    public static function expectedMove(float $spot, float $iv, int $days): float
    {
        return round($spot * $iv * sqrt($days / 365), 2);
    }

    public static function atmFairPremium(float $spot, float $iv, int $days): float
    {
        return round(self::expectedMove($spot, $iv, $days) * 0.42, 2);
    }

    /**
     * Valuation status with tight 5% tolerance.
     *
     * OVERPRICED  : market > fair × 1.05
     * UNDERPRICED : market < fair × 0.95
     * FAIR        : within ±5%
     */
    public static function valuationStatus(
        float $marketPrice,
        float $fairPrice,
        float $tolerance = 0.05
    ): string {
        if ($fairPrice <= 0) return 'FAIR';
        if ($marketPrice > $fairPrice * (1 + $tolerance)) return 'OVERPRICED';
        if ($marketPrice < $fairPrice * (1 - $tolerance)) return 'UNDERPRICED';
        return 'FAIR';
    }

    public static function getLTP(string $tradingSymbol): ?float
    {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)->first();
            if ($instrument && isset($instrument->last_price) && $instrument->last_price > 0) {
                return (float) $instrument->last_price;
            }
        } catch (\Exception $e) {
            Log::error("Error fetching LTP for {$tradingSymbol}: " . $e->getMessage());
        }
        return null;
    }

    // =========================================================================
    //  COMPREHENSIVE VALUATION — main entry point
    // =========================================================================

    /**
     * Full valuation with dynamic IV resolution.
     *
     * @param float       $spot
     * @param float       $strike
     * @param int         $daysToExpiry
     * @param string      $type           'CE' or 'PE'
     * @param float|null  $marketPrice    Option LTP (required for comparison)
     * @param string      $baseSymbol
     * @param string|null $tradingSymbol  Broker symbol for Tier-1 IV lookup
     * @param float|null  $atmIv          Pre-computed ATM IV (decimal) — MUST be passed for ALL strikes
     * @return array
     */
    public static function comprehensiveValuation(
        float   $spot,
        float   $strike,
        int     $daysToExpiry,
        string  $type,
        ?float  $marketPrice   = null,
        string  $baseSymbol    = 'NIFTY',
        ?string $tradingSymbol = null,
        ?float  $atmIv         = null
    ): array {
        $riskFreeRate = 0.01;

        // Resolve IV using ATM-based priority
        $ivResult = self::resolveIV(
            $baseSymbol,
            $tradingSymbol,
            $atmIv,
            $type
        );

        $iv = $ivResult['iv'];

        // Black-Scholes fair price
        $fairPriceBS = self::calculateFairPrice(
            $spot, $strike, $daysToExpiry, $iv, $riskFreeRate, $type
        );

        $expectedMove = self::expectedMove($spot, $iv, $daysToExpiry);
        $atmFair      = self::atmFairPremium($spot, $iv, $daysToExpiry);

        $result = [
            'fair_price_bs'    => $fairPriceBS,
            'expected_move'    => $expectedMove,
            'atm_fair_premium' => $atmFair,
            'iv_used'          => round($iv * 100, 2),
            'iv_source'        => $ivResult['source'],
            'days_to_expiry'   => $daysToExpiry,
        ];

        if ($marketPrice !== null && $marketPrice > 0) {
            $diff    = round($marketPrice - $fairPriceBS, 2);
            $diffPct = $fairPriceBS > 0
                ? round(($diff / $fairPriceBS) * 100, 2)
                : 0.0;

            $status = self::valuationStatus($marketPrice, $fairPriceBS);

            $result['market_price']             = $marketPrice;
            $result['valuation_status']         = $status;
            $result['price_difference']         = $diff;
            $result['price_difference_percent'] = $diffPct;
            $result['recommendation']           = match ($status) {
                'UNDERPRICED' => 'GOOD TO BUY',
                'OVERPRICED'  => 'GOOD TO SELL',
                default       => 'WAIT OR AVOID',
            };
        }

        return $result;
    }
}