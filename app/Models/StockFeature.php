<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * App\Models\StockFeature
 *
 * @property int         $id
 * @property string      $symbol
 * @property \Carbon\Carbon $trade_date
 * @property string      $trend            UP | DOWN | SIDEWAYS
 * @property string      $volatility       HIGH | LOW
 * @property float       $distance_from_high   signed %
 * @property float       $distance_from_low    signed %
 * @property bool        $volume_spike
 * @property string|null $rsi_zone         OVERBOUGHT | OVERSOLD | NEUTRAL
 * @property float|null  $rsi_value        Wilder 14-period RSI
 * @property array|null  $features_json
 */
class StockFeature extends Model
{
    protected $table = 'stock_features';

    protected $fillable = [
        'symbol',
        'trade_date',
        'trend',
        'volatility',
        'distance_from_high',
        'distance_from_low',
        'volume_spike',
        'rsi_zone',
        'rsi_value',
        'features_json',
    ];

    protected $casts = [
        'trade_date'         => 'date:Y-m-d',
        'distance_from_high' => 'float',
        'distance_from_low'  => 'float',
        'volume_spike'       => 'boolean',
        'rsi_value'          => 'float',
        'features_json'      => 'array',
    ];

    // ── Query Scopes ──────────────────────────────────────────────────────────

    public function scopeForSymbol(Builder $q, string $symbol): Builder
    {
        return $q->where('symbol', $symbol);
    }

    /** Rows strictly before a date (for similarity look-back) */
    public function scopeBefore(Builder $q, string $date): Builder
    {
        return $q->where('trade_date', '<', $date);
    }

    public function scopeUptrend(Builder $q): Builder
    {
        return $q->where('trend', 'UP');
    }

    public function scopeVolumeSpike(Builder $q): Builder
    {
        return $q->where('volume_spike', true);
    }

    /**
     * Find rows where distance_from_high is within ±$tolerance percentage points.
     * e.g. current is -3.5 with tolerance 2.0 → WHERE distance_from_high BETWEEN -5.5 AND -1.5
     */
    public function scopeNearHighDistance(Builder $q, float $value, float $tolerance = 2.0): Builder
    {
        return $q->whereBetween('distance_from_high', [
            $value - $tolerance,
            $value + $tolerance,
        ]);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /** Helper: get a value from features_json safely */
    public function getFeature(string $key, mixed $default = null): mixed
    {
        return ($this->features_json[$key] ?? $default);
    }
}
