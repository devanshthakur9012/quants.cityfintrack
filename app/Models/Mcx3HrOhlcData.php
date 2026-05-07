<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mcx3HrOhlcData extends Model
{
    protected $table = 'mcx_3hr_ohlc_data';

    protected $fillable = [
        'broker_api_id', 'trade_date', 'interval_time',
        'base_symbol', 'future_symbol', 'future_price', 'atm_strike',
        'instrument_type', 'strike', 'trading_symbol', 'instrument_token',
        'open', 'high', 'low', 'close', 'volume', 'oi',
        'strike_position', 'expiry_date', 'is_missing',
    ];

    protected $casts = [
        'future_price' => 'decimal:2',
        'atm_strike'   => 'decimal:2',
        'strike'       => 'decimal:2',
        'open'         => 'decimal:2',
        'high'         => 'decimal:2',
        'low'          => 'decimal:2',
        'close'        => 'decimal:2',
        'volume'       => 'integer',
        'oi'           => 'integer',
    ];

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}