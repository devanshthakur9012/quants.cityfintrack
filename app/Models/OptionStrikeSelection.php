<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OptionStrikeSelection extends Model
{
    protected $fillable = [
        'trade_date',
        'interval_time',  // ✅ NEW: stores the 15-min interval timestamp
        'future_symbol',
        'base_symbol',
        'future_price',
        'atm_strike',
        
        // CE Strikes
        'ce_atm_strike', 'ce_atm_symbol', 'ce_atm_oi', 'ce_atm_fair_price', 'ce_atm_ltp',
        'ce_atm_valuation',
        'ce_atm1_strike', 'ce_atm1_symbol', 'ce_atm1_oi', 'ce_atm1_fair_price', 'ce_atm1_ltp',
        'ce_atm1_valuation',
        'ce_atm2_strike', 'ce_atm2_symbol', 'ce_atm2_oi', 'ce_atm2_fair_price', 'ce_atm2_ltp',
        'ce_atm2_valuation',
        
        // PE Strikes
        'pe_atm_strike', 'pe_atm_symbol', 'pe_atm_oi', 'pe_atm_fair_price', 'pe_atm_ltp',
        'pe_atm_valuation',
        'pe_atm1_strike', 'pe_atm1_symbol', 'pe_atm1_oi', 'pe_atm1_fair_price', 'pe_atm1_ltp',
        'pe_atm1_valuation',
        'pe_atm2_strike', 'pe_atm2_symbol', 'pe_atm2_oi', 'pe_atm2_fair_price', 'pe_atm2_ltp',
        'pe_atm2_valuation',
        
        // Selected
        'selected_ce_symbol', 'selected_ce_strike', 'selected_ce_oi', 'selected_ce_fair_price',
        'selected_ce_valuation', 'selected_ce_recommendation',
        'selected_pe_symbol', 'selected_pe_strike', 'selected_pe_oi', 'selected_pe_fair_price',
        'selected_pe_valuation', 'selected_pe_recommendation',
        
        'option_series',
        'expiry_date',
        'calculated_at',
        'last_updated_at',
    ];

    protected $casts = [
        'trade_date' => 'date',
        'interval_time' => 'datetime',  // ✅ NEW
        'expiry_date' => 'date',
        'calculated_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get selection for specific date and symbol
     */
    public static function getForBacktest($tradeDate, $futureSymbol, $optionSeries = 'current')
    {
        return self::where('trade_date', $tradeDate)
            ->where('future_symbol', $futureSymbol)
            ->where('option_series', $optionSeries)
            ->orderBy('interval_time', 'desc')
            ->first();
    }

    /**
     * ✅ NEW: Get selection for specific interval
     */
    public static function getForInterval($tradeDate, $intervalTime, $futureSymbol, $optionSeries = 'current')
    {
        return self::where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->where('future_symbol', $futureSymbol)
            ->where('option_series', $optionSeries)
            ->first();
    }

    /**
     * ✅ NEW: Get latest selection for a date/symbol
     */
    public static function getLatestForDate($tradeDate, $futureSymbol, $optionSeries = 'current')
    {
        return self::where('trade_date', $tradeDate)
            ->where('future_symbol', $futureSymbol)
            ->where('option_series', $optionSeries)
            ->orderBy('interval_time', 'desc')
            ->first();
    }

    /**
     * ✅ NEW: Get all intervals for a date
     */
    public static function getAllIntervalsForDate($tradeDate, $futureSymbol, $optionSeries = 'current')
    {
        return self::where('trade_date', $tradeDate)
            ->where('future_symbol', $futureSymbol)
            ->where('option_series', $optionSeries)
            ->orderBy('interval_time', 'asc')
            ->get();
    }
}