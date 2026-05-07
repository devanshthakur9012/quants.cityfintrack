<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpiryData extends Model
{
    protected $table = 'expiry_data';
    
    protected $fillable = [
        'symbol',
        'exchange',
        'instrument_token',
        'timestamp',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'atr',
        'supertrend',
        'supertrend_direction',
        'supertrend_signal',
        'upper_band',
        'lower_band'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'volume' => 'integer',
        'atr' => 'decimal:4',
        'supertrend' => 'decimal:2',
        'upper_band' => 'decimal:2',
        'lower_band' => 'decimal:2'
    ];
}
