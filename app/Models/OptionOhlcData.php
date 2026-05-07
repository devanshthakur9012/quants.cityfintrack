<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OptionOhlcData extends Model
{
    protected $table = 'option_ohlc_data';

    protected $fillable = [
        'broker_api_id',
        'trade_date',
        'interval_time',
        'base_symbol',
        'future_symbol',
        'future_price',
        'atm_strike',
        'instrument_type',
        'strike',
        'trading_symbol',
        'instrument_token',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'strike_position',
        'expiry_date',
    ];

    protected $casts = [
        // 'trade_date' => 'date',
        // 'interval_time' => 'datetime',
        // 'expiry_date' => 'date',
        'future_price' => 'decimal:2',
        'atm_strike' => 'decimal:2',
        'strike' => 'decimal:2',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'volume' => 'integer',
        'oi' => 'integer',
        'fair_price' => 'decimal:2',
        'iv' => 'decimal:4',
    ];

    /**
     * Relationship to broker
     */
    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    /**
     * Get all data for a specific interval
     */
    public static function getForInterval($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->orderByRaw("FIELD(instrument_type, 'FUT', 'CE', 'PE')")
            ->orderBy('strike', 'asc')
            ->get();
    }

    /**
     * Get future data only
     */
    public static function getFuture($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->where('instrument_type', 'FUT')
            ->first();
    }

    /**
     * Get options data only
     */
    public static function getOptions($brokerId, $baseSymbol, $tradeDate, $intervalTime, $optionType = null)
    {
        $query = self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->whereIn('instrument_type', ['CE', 'PE']);

        if ($optionType) {
            $query->where('instrument_type', $optionType);
        }

        return $query->orderBy('strike', 'asc')->get();
    }

    /**
     * Get summary for a date
     */
    public static function getSummaryForDate($brokerId, $baseSymbol, $tradeDate)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->orderBy('interval_time', 'asc')
            ->get()
            ->groupBy('interval_time');
    }
}