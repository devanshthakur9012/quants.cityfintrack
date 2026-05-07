<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionOiData extends Model
{
    protected $fillable = [
        'option_strike_id',
        'timestamp',
        'oi',
        'oi_change',
        'oi_change_percent',
        'volume',
        'ltp',
        'open',
        'high',
        'low',
        'close'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'oi_change_percent' => 'decimal:2',
        'ltp' => 'decimal:2',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2'
    ];

    /**
     * Strike relationship
     */
    public function strike(): BelongsTo
    {
        return $this->belongsTo(OptionStrike::class, 'option_strike_id');
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope: Today's data
     */
    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', today());
    }

    /**
     * Get OI time series for a strike
     */
    public static function getOITimeSeries(int $strikeId, $startDate, $endDate)
    {
        return self::where('option_strike_id', $strikeId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'ASC')
            ->pluck('oi')
            ->toArray();
    }
}