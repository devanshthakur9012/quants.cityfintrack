<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BrokerStopLossConfig extends Model
{
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'symbol_type',
        'price_type',
        'stop_loss_percent',
        'quantity_percent',
        'position_filter',
        'skip_old_positions',
        'skip_fresh_positions',
        'is_active',
    ];

    protected $casts = [
        'stop_loss_percent'    => 'float',
        'quantity_percent'     => 'integer',
        'skip_old_positions'   => 'boolean',
        'skip_fresh_positions' => 'boolean',
        'is_active'            => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─────────────────────────────────────────────────────────────
    // Static helpers (mirrored from BrokerSellOrderConfig)
    // ─────────────────────────────────────────────────────────────

    /**
     * Get active configs for a broker, keyed by symbol_type.
     * Returns a Collection keyed by symbol_type (CE/PE/BOTH).
     */
    public static function getForBroker(int $brokerId)
    {
        return static::where('broker_api_id', $brokerId)
            ->where('is_active', true)
            ->get()
            ->keyBy('symbol_type');
    }

    /**
     * Extract CE / PE from a trading symbol like NIFTY24FEB22000CE.
     * Returns 'CE', 'PE', or null (for non-option instruments).
     */
    public static function extractSymbolType(string $tradingSymbol): ?string
    {
        if (str_ends_with(strtoupper($tradingSymbol), 'CE')) return 'CE';
        if (str_ends_with(strtoupper($tradingSymbol), 'PE')) return 'PE';
        return null;
    }

    /**
     * A position is "fresh" if it was entered today or on the previous trading day (T-1).
     * Anything older than T-1 is "old".
     */
    public static function isFreshPosition($purchaseDate, ?Carbon $today = null): bool
    {
        $today        = $today ?? Carbon::today();
        $purchaseDate = Carbon::parse($purchaseDate)->startOfDay();
        $prevDay      = static::getPreviousTradingDay($today);

        return $purchaseDate->gte($prevDay);
    }

    /**
     * Returns the previous trading day (skips weekends).
     */
    public static function getPreviousTradingDay(Carbon $today): Carbon
    {
        $prev = $today->copy()->subDay();
        while ($prev->isWeekend()) {
            $prev->subDay();
        }
        return $prev;
    }
}