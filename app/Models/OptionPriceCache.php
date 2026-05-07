<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionPriceCache extends Model
{
    use HasFactory;
    protected $table = 'option_price_cache';
    
    protected $fillable = [
        'trading_symbol',
        'instrument_token',
        'price_datetime',
        'price',
        'cached_at'
    ];

    protected $casts = [
        'price_datetime' => 'datetime',
        'cached_at' => 'datetime',
        'price' => 'decimal:2'
    ];
}
