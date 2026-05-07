<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NextSeriesDailyOhlcSymbol extends Model
{
    protected $table    = 'next_series_daily_ohlc_symbols';
    protected $fillable = ['symbol', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}