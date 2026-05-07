<?php

namespace App\Helpers;

use App\Models\SymbolData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Helper for fetching HISTORICAL option data (for backtesting)
 * Uses existing symbol_data table - NO LIVE API calls
 */
class OptionHistoricalDataHelper
{
    /**
     * Get option LTP at or before a specific time (for backtesting)
     * 
     * @param string $optionSymbol - e.g., "ADANIENT26FEB2300CE"
     * @param Carbon|string $signalTime - e.g., "2026-02-09 09:15:00"
     * @param string $interval - e.g., "15minute"
     * @return float|null - Option close price (LTP) at that time
     */
    public static function getLtpAtTime(
        string $optionSymbol,
        $signalTime,
        string $interval = '15minute'
    ): ?float {
        try {
            $timestamp = $signalTime instanceof Carbon 
                ? $signalTime 
                : Carbon::parse($signalTime);
            
            // Query existing symbol_data table
            $candle = SymbolData::where('trading_symbol', $optionSymbol)
                ->where('interval', $interval)
                ->where('timestamp', '<=', $timestamp)
                ->orderBy('timestamp', 'DESC')
                ->first();
            
            if ($candle && $candle->close > 0) {
                return (float) $candle->close;
            }
            
            Log::debug("No historical LTP found for {$optionSymbol} at {$timestamp}");
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error getting LTP at time: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get option OI at or before a specific time (for backtesting)
     */
    public static function getOiAtTime(
        string $optionSymbol,
        $signalTime,
        string $interval = '15minute'
    ): int {
        try {
            $timestamp = $signalTime instanceof Carbon 
                ? $signalTime 
                : Carbon::parse($signalTime);
            
            $candle = SymbolData::where('trading_symbol', $optionSymbol)
                ->where('interval', $interval)
                ->where('timestamp', '<=', $timestamp)
                ->orderBy('timestamp', 'DESC')
                ->first();
            
            if ($candle) {
                return (int) ($candle->oi ?? 0);
            }
            
            return 0;
            
        } catch (\Exception $e) {
            Log::error("Error getting OI at time: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get complete option candle data at specific time
     */
    public static function getCandleAtTime(
        string $optionSymbol,
        $signalTime,
        string $interval = '15minute'
    ): ?array {
        try {
            $timestamp = $signalTime instanceof Carbon 
                ? $signalTime 
                : Carbon::parse($signalTime);
            
            $candle = SymbolData::where('trading_symbol', $optionSymbol)
                ->where('interval', $interval)
                ->where('timestamp', '<=', $timestamp)
                ->orderBy('timestamp', 'DESC')
                ->first();
            
            if ($candle) {
                return [
                    'timestamp' => $candle->timestamp,
                    'open' => (float) $candle->open,
                    'high' => (float) $candle->high,
                    'low' => (float) $candle->low,
                    'close' => (float) $candle->close,
                    'volume' => (int) $candle->volume,
                    'oi' => (int) ($candle->oi ?? 0),
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error getting candle at time: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if historical option data exists for a symbol on a date
     */
    public static function hasHistoricalData(
        string $optionSymbol,
        $date
    ): bool {
        try {
            $checkDate = $date instanceof Carbon ? $date : Carbon::parse($date);
            
            return SymbolData::where('trading_symbol', $optionSymbol)
                ->whereDate('timestamp', $checkDate)
                ->exists();
                
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * ✅ NEW: Check if option has valid tradable data
     */
    public static function hasValidData(
        string $optionSymbol,
        $signalTime,
        string $interval = '15minute'
    ): bool {
        $ltp = self::getLtpAtTime($optionSymbol, $signalTime, $interval);
        $oi = self::getOiAtTime($optionSymbol, $signalTime, $interval);
        
        return ($ltp !== null && $ltp > 0 && $oi > 0);
    }
}