<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * App\Models\StockPattern
 *
 * @property int         $id
 * @property string      $symbol
 * @property string      $pattern_type
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 * @property int         $confidence   0–100
 * @property array|null  $meta_json
 */
class StockPattern extends Model
{
    protected $table = 'stock_patterns';

    protected $fillable = [
        'symbol',
        'pattern_type',
        'start_date',
        'end_date',
        'confidence',
        'meta_json',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'confidence' => 'integer',
        'meta_json'  => 'array',
    ];

    // ── Pattern type constants ────────────────────────────────────────────────
    // Use these everywhere so you never typo a string.

    public const DOUBLE_TOP        = 'DOUBLE_TOP';
    public const DOUBLE_BOTTOM     = 'DOUBLE_BOTTOM';
    public const BREAKOUT          = 'BREAKOUT';
    public const BREAKDOWN         = 'BREAKDOWN';
    public const SUPPORT_BOUNCE    = 'SUPPORT_BOUNCE';
    public const RESISTANCE_REJECT = 'RESISTANCE_REJECT';

    public static function allTypes(): array
    {
        return [
            self::DOUBLE_TOP,
            self::DOUBLE_BOTTOM,
            self::BREAKOUT,
            self::BREAKDOWN,
            self::SUPPORT_BOUNCE,
            self::RESISTANCE_REJECT,
        ];
    }

    /** Patterns that are bullish signals */
    public static function bullishTypes(): array
    {
        return [self::DOUBLE_BOTTOM, self::BREAKOUT, self::SUPPORT_BOUNCE];
    }

    /** Patterns that are bearish signals */
    public static function bearishTypes(): array
    {
        return [self::DOUBLE_TOP, self::BREAKDOWN, self::RESISTANCE_REJECT];
    }

    // ── Query Scopes ──────────────────────────────────────────────────────────

    public function scopeForSymbol(Builder $q, string $symbol): Builder
    {
        return $q->where('symbol', $symbol);
    }

    public function scopeHighConfidence(Builder $q, int $min = 65): Builder
    {
        return $q->where('confidence', '>=', $min);
    }

    /** Patterns confirmed on or after a given date */
    public function scopeRecentFrom(Builder $q, string $date): Builder
    {
        return $q->where('end_date', '>=', $date);
    }

    /** Only bullish patterns */
    public function scopeBullish(Builder $q): Builder
    {
        return $q->whereIn('pattern_type', self::bullishTypes());
    }

    /** Only bearish patterns */
    public function scopeBearish(Builder $q): Builder
    {
        return $q->whereIn('pattern_type', self::bearishTypes());
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function isBullish(): bool
    {
        return in_array($this->pattern_type, self::bullishTypes());
    }

    public function isBearish(): bool
    {
        return in_array($this->pattern_type, self::bearishTypes());
    }
}
