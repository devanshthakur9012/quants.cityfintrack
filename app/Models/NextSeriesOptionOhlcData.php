<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * NextSeriesOptionOhlcData
 *
 * Stores 15-min OHLC candles for the NEXT expiry series.
 * Same structure as OptionOhlcData — just targets the upcoming expiry
 * (e.g. April data while March is currently live).
 *
 * One row per 15-min slot per instrument per day (25 rows/day/instrument).
 */
class NextSeriesOptionOhlcData extends Model
{
    protected $table = 'next_series_option_ohlc_data';

    protected $fillable = [
        'broker_api_id',
        'trade_date',
        'interval_time',
        'trading_symbol',
        'base_symbol',
        'future_symbol',
        'future_price',
        'atm_strike',
        'instrument_type',
        'strike',
        'instrument_token',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'strike_position',
        'expiry_date',
        'is_missing',
    ];

    protected $casts = [
        'trade_date'    => 'datetime',
        'interval_time' => 'datetime',
        'expiry_date'   => 'date',
        'future_price'  => 'float',
        'atm_strike'    => 'float',
        'strike'        => 'float',
        'open'          => 'float',
        'high'          => 'float',
        'low'           => 'float',
        'close'         => 'float',
        'volume'        => 'integer',
        'oi'            => 'integer',
        'is_missing'    => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeFut($query)
    {
        return $query->where('instrument_type', 'FUT');
    }

    public function scopeCe($query)
    {
        return $query->where('instrument_type', 'CE');
    }

    public function scopePe($query)
    {
        return $query->where('instrument_type', 'PE');
    }

    public function scopeAtm($query)
    {
        return $query->where('strike_position', 'ATM');
    }

    public function scopeNotMissing($query)
    {
        return $query->where('is_missing', 0);
    }

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('base_symbol', $symbol);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('trade_date', $date);
    }

    public function scopeForExpiry($query, string $expiry)
    {
        return $query->whereDate('expiry_date', $expiry);
    }

    public function scopeAtTime($query, string $time)
    {
        return $query->whereRaw("TIME(interval_time) = ?", [$time . ':00']);
    }
}