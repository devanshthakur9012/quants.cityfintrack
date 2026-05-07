<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class McxOhlcData extends Model
{
    protected $fillable = [
        'broker_api_id', 'trade_date', 'interval_time', 'trading_symbol',
        'base_symbol', 'future_symbol', 'future_price', 'atm_strike',
        'instrument_type', 'strike', 'instrument_token', 'strike_position',
        'expiry_date', 'open', 'high', 'low', 'close', 'volume', 'oi', 'is_missing',
    ];

    protected $casts = [
        'trade_date'    => 'date',
        'interval_time' => 'datetime',
        'expiry_date'   => 'date',
        'future_price'  => 'float',
        'atm_strike'    => 'float',
        'strike'        => 'float',
        'open'          => 'float',
        'high'          => 'float',
        'low'           => 'float',
        'close'         => 'float',
        'is_missing'    => 'boolean',
    ];

    public function scopeForSymbol(Builder $q, string $symbol): Builder  { return $q->where('base_symbol', $symbol); }
    public function scopeForDate(Builder $q, string $date): Builder      { return $q->whereDate('trade_date', $date); }
    public function scopeFutOnly(Builder $q): Builder                    { return $q->where('instrument_type', 'FUT'); }
    public function scopeOptionsOnly(Builder $q): Builder                { return $q->whereIn('instrument_type', ['CE', 'PE']); }
    public function scopeNotMissing(Builder $q): Builder                 { return $q->where('is_missing', 0); }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}