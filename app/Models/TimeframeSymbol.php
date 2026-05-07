<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeframeSymbol extends Model
{
    protected $table = 'timeframe_symbols';

    protected $fillable = [
        'symbol',
        'timeframe',
        'exchange',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTimeframe($query, string $timeframe)
    {
        return $query->where('timeframe', $timeframe);
    }

    // ── Helper used by commands ───────────────────────────────────────────────

    /**
     * Get active symbols for a timeframe as a plain array of symbol strings.
     */
    public static function activeSymbolsFor(string $timeframe): array
    {
        return static::active()
            ->forTimeframe($timeframe)
            ->pluck('symbol')
            ->toArray();
    }
}