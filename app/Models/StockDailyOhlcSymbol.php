<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockDailyOhlcSymbol extends Model
{
    protected $table    = 'stock_daily_ohlc_symbols';
    protected $fillable = ['symbol', 'exchange', 'is_active', 'notes'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNse($query)
    {
        return $query->where('exchange', 'NSE');
    }

    public function scopeBse($query)
    {
        return $query->where('exchange', 'BSE');
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function ohlcData()
    {
        return $this->hasMany(StockDailyOhlcData::class, 'symbol', 'symbol');
    }
}