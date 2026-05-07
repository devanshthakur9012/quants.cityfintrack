<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * App\Models\StockPivot
 *
 * @property int    $id
 * @property string $symbol
 * @property string $trade_date
 * @property string $pivot_type   HIGH | LOW
 * @property float  $price
 * @property int    $strength
 */
class StockPivot extends Model
{
    protected $table = 'stock_pivots';

    protected $fillable = [
        'symbol',
        'trade_date',
        'pivot_type',
        'price',
        'strength',
    ];

    protected $casts = [
        'trade_date' => 'date:Y-m-d',
        'price'      => 'float',
        'strength'   => 'integer',
    ];

    // ── Query Scopes ──────────────────────────────────────────────────────────

    /** Filter only PIVOT HIGH rows */
    public function scopeHighs(Builder $q): Builder
    {
        return $q->where('pivot_type', 'HIGH');
    }

    /** Filter only PIVOT LOW rows */
    public function scopeLows(Builder $q): Builder
    {
        return $q->where('pivot_type', 'LOW');
    }

    /** Filter by symbol */
    public function scopeForSymbol(Builder $q, string $symbol): Builder
    {
        return $q->where('symbol', $symbol);
    }

    /** Only strong pivots (3+ bars each side) */
    public function scopeStrong(Builder $q): Builder
    {
        return $q->where('strength', '>=', 3);
    }

    /**
     * Find pivots within ±$tolerancePct% of a given price.
     * Used by SignalService to detect "price near support/resistance".
     */
    public function scopeNearPrice(Builder $q, float $price, float $tolerancePct = 1.5): Builder
    {
        $lower = $price * (1 - $tolerancePct / 100);
        $upper = $price * (1 + $tolerancePct / 100);
        return $q->whereBetween('price', [$lower, $upper]);
    }

    /** Pivots on or before a date */
    public function scopeBeforeOrOn(Builder $q, string $date): Builder
    {
        return $q->where('trade_date', '<=', $date);
    }
}
