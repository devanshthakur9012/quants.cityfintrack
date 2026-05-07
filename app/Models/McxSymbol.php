<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * McxSymbol
 *
 * Stores ONLY custom config that Zerodha does not provide:
 *   symbol, exchange, strike_interval, unit, is_active
 *
 * Everything else (lot_size, tick_size, instrument_token, expiry …)
 * is read live from zerodha_instruments at collection time.
 */
class McxSymbol extends Model
{
    protected $fillable = [
        'symbol',
        'exchange',
        'strike_interval',
        'unit',
        'is_active',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'strike_interval' => 'float',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Pull lot_size for this symbol's nearest FUT from zerodha_instruments.
     * Since lot_size lives there, we fetch it on demand.
     */
    public function getLotSize(?string $expiry = null): ?int
    {
        $query = ZerodhaInstrument::where('exchange', $this->exchange)
            ->where('instrument_type', 'FUT')
            ->where(function ($q) {
                $q->where('name', $this->symbol)
                  ->orWhere('trading_symbol', 'LIKE', $this->symbol . '%');
            });

        if ($expiry) {
            $query->whereDate('expiry', $expiry);
        } else {
            $query->whereDate('expiry', '>=', now()->toDateString())
                  ->orderBy('expiry');
        }

        return $query->value('lot_size');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function ohlcData()
    {
        return $this->hasMany(McxOhlcData::class, 'base_symbol', 'symbol');
    }
}