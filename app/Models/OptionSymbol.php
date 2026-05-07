<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionSymbol extends Model
{
    protected $table    = 'option_symbols';
    protected $fillable = ['symbol', 'is_active'];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}