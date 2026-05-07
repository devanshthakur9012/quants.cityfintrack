<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirtyMinOhlcSymbol extends Model
{
    protected $table    = '30min_ohlc_symbols';
    protected $fillable = ['symbol', 'is_active'];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}