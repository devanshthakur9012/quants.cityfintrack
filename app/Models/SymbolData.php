<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SymbolData extends Model
{
    use HasFactory;
        protected $table = 'symbol_data';

    protected $fillable = [
        'broker_api_id',
        'trading_symbol',
        'symbol',
        'exchange',
        'instrument_token',
        'interval',
        'timestamp',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'atr',
        'supertrend',
        'supertrend_direction',
        'supertrend_signal',
        'upper_band',
        'lower_band',
        'donchian_signal',
        'donchian_upper',
        'donchian_lower',
        'donchian_middle',
        'donchian_entry',
        'donchian_sl',
        'donchian_target',
        'rsi',
        'rsi_signal',
        'macd_line',
        'macd_signal_line',
        'macd_histogram',
        'macd_signal',
        'vwap',
        'vwap_signal',
        'vwap_upper_band',
        'vwap_lower_band',
        'previous_oi',
        'oi_change',
        'oi_change_percent',
        'oi_signal',
        'ma50'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'volume' => 'integer',
        'oi' => 'integer',
        'atr' => 'decimal:4',
        'supertrend' => 'decimal:2',
        'upper_band' => 'decimal:2',
        'lower_band' => 'decimal:2',
        'donchian_upper' => 'decimal:2',
        'donchian_lower' => 'decimal:2',
        'donchian_middle' => 'decimal:2',
        'donchian_entry' => 'decimal:2',
        'donchian_sl' => 'decimal:2',
        'donchian_target' => 'decimal:2',
        'rsi' => 'decimal:2',
        'macd_line' => 'decimal:4',
        'macd_signal_line' => 'decimal:4',
        'macd_histogram' => 'decimal:4',
        'vwap' => 'decimal:2',
        'vwap_upper_band' => 'decimal:2',
        'vwap_lower_band' => 'decimal:2'
    ];

    /**
     * Relationship: Broker
     */
    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    /**
     * Scope: By interval
     */
    public function scopeByInterval($query, $interval)
    {
        return $query->where('interval', $interval);
    }

    /**
     * Scope: By broker
     */
    public function scopeByBroker($query, $brokerId)
    {
        return $query->where('broker_api_id', $brokerId);
    }

    /**
     * Scope: By symbol
     */
    public function scopeBySymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    /**
     * Scope: Today's data
     */
    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', Carbon::today());
    }

    /**
     * Scope: Date range
     */
    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('timestamp', [$fromDate, $toDate]);
    }

    /**
     * Get latest data for a symbol
     */
    public static function getLatest($brokerId, $symbol, $interval = '15minute', $limit = 100)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get data for signal calculation
     */
    public static function getForSignalCalculation($brokerId, $symbol, $timestamp, $interval = '15minute', $limit = 50)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('timestamp', '<=', $timestamp)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->toArray();
    }

}
