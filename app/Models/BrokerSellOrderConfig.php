<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrokerSellOrderConfig extends Model
{
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'symbol_type',
        'price_type',
        'quantity_percent',
        'position_filter',
        'old_position_profit_percent',
        'fresh_position_profit_percent',
        'skip_old_positions',
        'skip_fresh_positions',
        'is_active',
    ];

    protected $casts = [
        'old_position_profit_percent'   => 'decimal:2',
        'fresh_position_profit_percent' => 'decimal:2',
        'skip_old_positions'            => 'boolean',
        'skip_fresh_positions'          => 'boolean',
        'is_active'                     => 'boolean',
        'quantity_percent'              => 'integer',
    ];

    /**
     * Get previous trading day (T-1)
     * Skips weekends and market holidays
     */
    public static function getPreviousTradingDay($date = null)
    {
        $date        = $date ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::today();
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
        $referenceDate = $referenceDate ? \Carbon\Carbon::parse($referenceDate) : \Carbon\Carbon::today();
        $purchaseDate  = \Carbon\Carbon::parse($purchaseDate);

        if ($purchaseDate->isSameDay($referenceDate)) {
            return true;
        }

        $previousTradingDay = static::getPreviousTradingDay($referenceDate);
        if ($purchaseDate->isSameDay($previousTradingDay)) {
            return true;
        }

        return false;
    }

    /**
     * Get configs for specific broker
     */
    public static function getForBroker($brokerId)
    {
        return static::where('broker_api_id', $brokerId)
            ->where('is_active', true)
            ->get()
            ->keyBy('symbol_type');
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