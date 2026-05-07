<?php
// app/Services/IVAnalysisServiceBackup.php

namespace App\Services;

use App\Models\OptionIVData;
use Carbon\Carbon;

class IVAnalysisServiceBackup
{
    /**
     * Get comprehensive IV analysis for a symbol
     */
    public function analyzeIV($symbol, $expiry, $timestamp = null)
    {
        $timestamp = $timestamp ?? now();
        
        // Get current IV data
        $currentData = $this->getCurrentIVData($symbol, $expiry, $timestamp);
        
        if ($currentData['avg_iv_total'] == 0) {
            return $this->getEmptyAnalysis($symbol, $expiry, $timestamp);
        }
        
        // Get historical IV data
        $iv5minAgo = $this->getHistoricalIVData($symbol, $expiry, $timestamp, 5);
        $iv15minAgo = $this->getHistoricalIVData($symbol, $expiry, $timestamp, 15);
        $ivDayAgo = $this->getHistoricalIVData($symbol, $expiry, $timestamp, 1440);
        
        // Calculate changes
        $ivChange5m = $currentData['avg_iv_total'] - $iv5minAgo['avg_iv_total'];
        $ivChange15m = $currentData['avg_iv_total'] - $iv15minAgo['avg_iv_total'];
        $ivChangeDay = $currentData['avg_iv_total'] - $ivDayAgo['avg_iv_total'];
        
        // Determine regime, trend, speed
        $ivRegime = $this->determineIVRegime($symbol, $expiry, $currentData['avg_iv_total']);
        $ivTrend = $this->determineIVTrend($ivChange5m, $ivChange15m);
        $ivSpeed = $this->determineIVSpeed($ivChange5m);
        
        // Get expected behavior
        $expectedBehavior = $this->getExpectedBehavior($ivRegime, $ivTrend, $ivSpeed);
        
        return [
            'symbol' => $symbol,
            'expiry' => $expiry,
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'atm_strike' => $currentData['atm_strike'],
            
            // Current IV
            'avg_iv_ce' => round($currentData['avg_iv_ce'], 2),
            'avg_iv_pe' => round($currentData['avg_iv_pe'], 2),
            'avg_iv_total' => round($currentData['avg_iv_total'], 2),
            'iv_skew' => round($currentData['avg_iv_pe'] - $currentData['avg_iv_ce'], 2),
            
            // IV Changes
            'iv_change_5m' => round($ivChange5m, 2),
            'iv_change_15m' => round($ivChange15m, 2),
            'iv_change_day' => round($ivChangeDay, 2),
            
            // IV Analysis
            'iv_regime' => $ivRegime,
            'iv_trend' => $ivTrend,
            'iv_speed' => $ivSpeed,
            
            // OI Analysis
            'oi_ce' => $currentData['total_oi_ce'],
            'oi_pe' => $currentData['total_oi_pe'],
            'oi_pcr' => $currentData['oi_pcr'],
            
            // Trading Signals
            'expected_behavior' => $expectedBehavior,
            'recommendation' => $this->getRecommendation($ivRegime, $ivTrend, $ivSpeed),
        ];
    }
    
    /**
     * Get current IV data for ATM strikes
     */
    private function getCurrentIVData($symbol, $expiry, $timestamp)
    {
        $data = OptionIVData::where('symbol', $symbol)
            ->where('expiry', $expiry)
            ->atmOnly()
            ->where('timestamp', '>=', $timestamp->copy()->subMinutes(2))
            ->where('timestamp', '<=', $timestamp->copy()->addMinutes(2))
            ->get();
        
        if ($data->isEmpty()) {
            return [
                'avg_iv_ce' => 0,
                'avg_iv_pe' => 0,
                'avg_iv_total' => 0,
                'atm_strike' => 0,
                'total_oi_ce' => 0,
                'total_oi_pe' => 0,
                'oi_pcr' => 0,
            ];
        }
        
        $ceData = $data->where('option_type', 'CE');
        $peData = $data->where('option_type', 'PE');
        
        $avgIVCE = $ceData->avg('iv') ?? 0;
        $avgIVPE = $peData->avg('iv') ?? 0;
        $atmStrike = $data->where('atm_position', 'ATM')->first()->strike ?? 0;
        
        $totalOICE = $ceData->sum('oi');
        $totalOIPE = $peData->sum('oi');
        $oiPCR = $totalOICE > 0 ? ($totalOIPE / $totalOICE) : 0;
        
        return [
            'avg_iv_ce' => $avgIVCE,
            'avg_iv_pe' => $avgIVPE,
            'avg_iv_total' => ($avgIVCE + $avgIVPE) / 2,
            'atm_strike' => $atmStrike,
            'total_oi_ce' => $totalOICE,
            'total_oi_pe' => $totalOIPE,
            'oi_pcr' => round($oiPCR, 2),
        ];
    }
    
    /**
     * Get historical IV data X minutes ago
     */
    private function getHistoricalIVData($symbol, $expiry, $timestamp, $minutesAgo)
    {
        $targetTime = $timestamp->copy()->subMinutes($minutesAgo);
        
        $data = OptionIVData::where('symbol', $symbol)
            ->where('expiry', $expiry)
            ->atmOnly()
            ->where('timestamp', '>=', $targetTime->copy()->subMinutes(2))
            ->where('timestamp', '<=', $targetTime->copy()->addMinutes(2))
            ->get();
        
        if ($data->isEmpty()) {
            return [
                'avg_iv_ce' => 0,
                'avg_iv_pe' => 0,
                'avg_iv_total' => 0,
            ];
        }
        
        $avgIVCE = $data->where('option_type', 'CE')->avg('iv') ?? 0;
        $avgIVPE = $data->where('option_type', 'PE')->avg('iv') ?? 0;
        
        return [
            'avg_iv_ce' => $avgIVCE,
            'avg_iv_pe' => $avgIVPE,
            'avg_iv_total' => ($avgIVCE + $avgIVPE) / 2,
        ];
    }
    
    /**
     * Determine IV regime (LOW / NORMAL / HIGH)
     */
    private function determineIVRegime($symbol, $expiry, $currentIV)
    {
        $baseline = OptionIVData::getBaselineIV($symbol, $expiry);
        
        if ($baseline == 0) return 'UNKNOWN';
        
        $percentile = ($currentIV / $baseline) * 100;
        
        $lowThreshold = config('iv_analysis.regime.low_threshold', 80);
        $highThreshold = config('iv_analysis.regime.high_threshold', 120);
        
        if ($percentile < $lowThreshold) return 'LOW';
        if ($percentile > $highThreshold) return 'HIGH';
        return 'NORMAL';
    }
    
    /**
     * Determine IV trend (RISING / FALLING / FLAT)
     */
    private function determineIVTrend($change5m, $change15m)
    {
        $rising5m = config('iv_analysis.trend.rising_threshold_5m', 0.5);
        $rising15m = config('iv_analysis.trend.rising_threshold_15m', 1.0);
        $falling5m = config('iv_analysis.trend.falling_threshold_5m', -0.5);
        $falling15m = config('iv_analysis.trend.falling_threshold_15m', -1.0);
        
        if ($change5m > $rising5m && $change15m > $rising15m) return 'RISING';
        if ($change5m < $falling5m && $change15m < $falling15m) return 'FALLING';
        return 'FLAT';
    }
    
    /**
     * Determine IV speed (FAST / SLOW)
     */
    private function determineIVSpeed($change5m)
    {
        $fastThreshold = config('iv_analysis.speed.fast_threshold', 1.0);
        return abs($change5m) > $fastThreshold ? 'FAST' : 'SLOW';
    }
    
    /**
     * Get expected market behavior
     */
    private function getExpectedBehavior($regime, $trend, $speed)
    {
        if ($regime === 'HIGH' && $trend === 'RISING' && $speed === 'FAST') {
            return 'RANGE / TRAP - High volatility expansion';
        }
        
        if ($regime === 'LOW' && $trend === 'FALLING') {
            return 'DECAY MODE - Low volatility compression';
        }
        
        if ($regime === 'NORMAL' && $trend === 'FLAT') {
            return 'NEUTRAL - Stable volatility';
        }
        
        if ($trend === 'RISING') {
            return 'VOLATILITY EXPANSION - Fear entering market';
        }
        
        if ($trend === 'FALLING') {
            return 'VOLATILITY CONTRACTION - Risk being priced out';
        }
        
        return 'MONITOR - Mixed signals';
    }
    
    /**
     * Get trading recommendation
     */
    private function getRecommendation($regime, $trend, $speed)
    {
        if ($regime === 'HIGH' && $trend === 'RISING') {
            return '⚠️ AVOID BUYING PREMIUM - Options expensive';
        }
        
        if ($regime === 'LOW' && $trend === 'FALLING') {
            return '✅ FAVOR SELLING PREMIUM - Good decay environment';
        }
        
        if ($regime === 'LOW' && $trend === 'RISING') {
            return '✅ GOOD TIME FOR OPTION BUYING - IV starting to rise';
        }
        
        if ($regime === 'HIGH' && $trend === 'FALLING') {
            return '⚠️ WAIT - IV cooling down, better entry ahead';
        }
        
        return '📊 NEUTRAL - Monitor IV movement';
    }
    
    /**
     * Get empty analysis when no data
     */
    private function getEmptyAnalysis($symbol, $expiry, $timestamp)
    {
        return [
            'symbol' => $symbol,
            'expiry' => $expiry,
            'timestamp' => $timestamp->format('Y-m-d H:i:s'),
            'atm_strike' => 0,
            'avg_iv_ce' => 0,
            'avg_iv_pe' => 0,
            'avg_iv_total' => 0,
            'iv_skew' => 0,
            'iv_change_5m' => 0,
            'iv_change_15m' => 0,
            'iv_change_day' => 0,
            'iv_regime' => 'NO DATA',
            'iv_trend' => 'NO DATA',
            'iv_speed' => 'NO DATA',
            'oi_ce' => 0,
            'oi_pe' => 0,
            'oi_pcr' => 0,
            'expected_behavior' => 'NO DATA AVAILABLE',
            'recommendation' => '⚠️ Insufficient data for analysis',
        ];
    }
}