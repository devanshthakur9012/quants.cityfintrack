<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mcx3HrOhlcSymbol extends Model
{
    protected $table    = 'mcx_3hr_ohlc_symbols';
    protected $fillable = ['symbol', 'strike_interval', 'exchange', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}