<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionsOhlcData extends Model
{
    protected $table = 'options_ohlc_data';

    protected $fillable = [
        'broker_api_id',
        'underlying_symbol',
        'trading_symbol',
        'option_type',
        'strike_position',
        'strike_price',
        'expiry',
        'expiry_date',
        'instrument_token',
        'exchange',
        'lot_size',
        'timestamp',
        'trading_date',
        'interval',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'previous_oi',
        'oi_change',
        'oi_change_percent',
        'oi_signal',
        'spot_price',
        'is_active',
        'last_synced_at'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'trading_date' => 'date',
        'expiry_date' => 'date',
        'strike_price' => 'decimal:2',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'spot_price' => 'decimal:2',
        'oi_change_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime'
    ];

    /**
     * Relationship to BrokerApi
     */
    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    /**
     * Scope: Filter by broker
     */
    public function scopeForBroker($query, $brokerId)
    {
        return $query->where('broker_api_id', $brokerId);
    }

    /**
     * Scope: Filter by underlying symbol
     */
    public function scopeForSymbol($query, $symbol)
    {
        return $query->where('underlying_symbol', $symbol);
    }

    /**
     * Scope: Filter by trading date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('trading_date', $date);
    }

    /**
     * Scope: Filter by interval
     */
    public function scopeForInterval($query, $interval = '15minute')
    {
        return $query->where('interval', $interval);
    }

    /**
     * Scope: Only FUT records
     */
    public function scopeFuturesOnly($query)
    {
        return $query->where('option_type', 'FUT');
    }

    /**
     * Scope: Only CE records
     */
    public function scopeCEOnly($query)
    {
        return $query->where('option_type', 'CE');
    }

    /**
     * Scope: Only PE records
     */
    public function scopePEOnly($query)
    {
        return $query->where('option_type', 'PE');
    }

    /**
     * Scope: Active records only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get latest candle for a trading symbol
     */
    public static function getLatestCandle($brokerId, $tradingSymbol, $interval = '15minute')
    {
        return static::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->orderBy('timestamp', 'DESC')
            ->first();
    }

    /**
     * Get previous OI for OI change calculation
     */
    public static function getPreviousOI($brokerId, $tradingSymbol, $interval, $beforeTimestamp)
    {
        $record = static::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->where('timestamp', '<', $beforeTimestamp)
            ->orderBy('timestamp', 'DESC')
            ->first();

        return $record ? $record->oi : null;
    }
}