<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Original30MinOhlcSymbol extends Model
{
    protected $table    = 'original_30min_ohlc_symbols';
    protected $fillable = ['symbol', 'is_active'];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}