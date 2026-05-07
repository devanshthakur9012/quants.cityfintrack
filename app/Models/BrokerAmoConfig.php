<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BrokerAmoConfig extends Model
{
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'symbol_type',
        'old_position_profit_percent',
        'fresh_position_profit_percent',
        'skip_old_positions',
        'skip_fresh_positions',
        'config_date',
        'is_active',
    ];

    protected $casts = [
        'old_position_profit_percent' => 'decimal:2',
        'fresh_position_profit_percent' => 'decimal:2',
        'skip_old_positions' => 'boolean',
        'skip_fresh_positions' => 'boolean',
        'config_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get config for specific broker and date
     */
    public static function getForBrokerAndDate($brokerId, $date = null)
    {
        $date = $date ?? Carbon::today();
        
        return static::where('broker_api_id', $brokerId)
            ->where('config_date', $date)
            ->where('is_active', true)
            ->get()
            ->keyBy('symbol_type');
    }

    /**
     * Get config for specific broker, symbol type and date
     */
    public static function getForBrokerSymbolDate($brokerId, $symbolType, $date = null)
    {
        $date = $date ?? Carbon::today();
        
        return static::where('broker_api_id', $brokerId)
            ->where('symbol_type', $symbolType)
            ->where('config_date', $date)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if symbol matches config type
     */
    public static function symbolMatchesType($tradingSymbol, $configType)
    {
        if ($configType === 'BOTH') {
            return str_ends_with($tradingSymbol, 'CE') || str_ends_with($tradingSymbol, 'PE');
        }
        
        return str_ends_with($tradingSymbol, $configType);
    }

    /**
     * Extract symbol type from trading symbol
     */
    public static function extractSymbolType($tradingSymbol)
    {
        if (str_ends_with($tradingSymbol, 'CE')) {
            return 'CE';
        } elseif (str_ends_with($tradingSymbol, 'PE')) {
            return 'PE';
        }
        
        return null; // Not an option
    }

    /**
     * Get all active brokers with configs for today
     */
    public static function getBrokersWithTodayConfig()
    {
        return static::with('brokerApi')
            ->where('config_date', Carbon::today())
            ->where('is_active', true)
            ->get()
            ->groupBy('broker_api_id');
    }

    /**
     * Get previous trading day (T-1)
     * Skips weekends and market holidays
     */
    public static function getPreviousTradingDay($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $previousDay = $date->copy()->subDay();
        
        // Skip weekends
        while ($previousDay->isWeekend()) {
            $previousDay->subDay();
        }
        
        // Check if it's a market holiday
        $isHoliday = \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $previousDay->format('Y-m-d'))
            ->exists();
        
        // If holiday, recursively get previous trading day
        if ($isHoliday) {
            return static::getPreviousTradingDay($previousDay);
        }
        
        return $previousDay;
    }

    /**
     * Check if position is fresh (today or T-1)
     */
    public static function isFreshPosition($purchaseDate, $referenceDate = null)
    {
        $referenceDate = $referenceDate ? Carbon::parse($referenceDate) : Carbon::today();
        $purchaseDate = Carbon::parse($purchaseDate);
        
        // Check if purchased today
        if ($purchaseDate->isSameDay($referenceDate)) {
            return true;
        }
        
        // Check if purchased on previous trading day
        $previousTradingDay = static::getPreviousTradingDay($referenceDate);
        if ($purchaseDate->isSameDay($previousTradingDay)) {
            return true;
        }
        
        return false;
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class);
    }
}