<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OptionSymbol5min
 *
 * Symbol config for the 5-minute OHLC collector.
 * Separate from OptionSymbol (15-min) — does NOT extend it.
 */
class OptionSymbol5min extends Model
{
    protected $table    = 'option_symbols_5min';
    protected $fillable = ['symbol', 'is_active'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}