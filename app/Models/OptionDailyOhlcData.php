<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionDailyOhlcData extends Model
{
    protected $table = 'option_daily_ohlc_data';

    protected $fillable = [
        'broker_api_id',
        'trade_date',
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
        'trade_date'   => 'date',
        'expiry_date'  => 'date',
        'is_missing'   => 'boolean',
        'open'         => 'float',
        'high'         => 'float',
        'low'          => 'float',
        'close'        => 'float',
        'future_price' => 'float',
        'atm_strike'   => 'float',
        'strike'       => 'float',
    ];
}