<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrokerTimeframeAssignment extends Model
{
    protected $table = 'broker_timeframe_assignments';

    protected $fillable = [
        'admin_broker_api_id',
        'timeframe',
        'label',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Supported timeframes (display label → DB value) ───────────────────────
    public const TIMEFRAMES = [
        '1min'  => '1 Minute',
        '3min'  => '3 Minutes',
        '5min'  => '5 Minutes',
        '10min' => '10 Minutes',
        '15min' => '15 Minutes',
        '30min' => '30 Minutes',
        '1hr'   => '1 Hour',
        '1day'  => '1 Day',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function broker()
    {
        return $this->belongsTo(AdminBrokerApi::class, 'admin_broker_api_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTimeframe($query, string $timeframe)
    {
        return $query->where('timeframe', $timeframe);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Human-readable timeframe label.
     */
    public function getTimeframeLabelAttribute(): string
    {
        return self::TIMEFRAMES[$this->timeframe] ?? strtoupper($this->timeframe);
    }

    /**
     * Fetch the one active assignment for a given timeframe.
     * Used by artisan commands to resolve their broker.
     */
    public static function activeBrokerForTimeframe(string $timeframe): ?AdminBrokerApi
    {
        $assignment = static::active()
            ->forTimeframe($timeframe)
            ->with('broker')
            ->first();

        if (!$assignment || !$assignment->broker) {
            return null;
        }

        return $assignment->broker->hasValidToken() ? $assignment->broker : null;
    }
}