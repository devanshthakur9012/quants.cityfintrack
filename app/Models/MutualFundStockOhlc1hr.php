<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutualFundStockOhlc1hr extends Model
{
    protected $table = 'mutual_fund_stock_ohlc_1hr';

    protected $fillable = [
        'symbol',
        'exchange',
        'instrument_token',
        'trading_symbol',
        'trade_date',
        'candle_time',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'is_missing',
    ];

    protected $casts = [
        'trade_date'  => 'date',
        'candle_time' => 'datetime',
        'open'        => 'decimal:2',
        'high'        => 'decimal:2',
        'low'         => 'decimal:2',
        'close'       => 'decimal:2',
        'volume'      => 'integer',
        'is_missing'  => 'boolean',
    ];

    // ─── Scopes ───────────────────────────────────────────

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('trade_date', $date);
    }

    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('trade_date', [$from, $to]);
    }

    public function scopeNotMissing($query)
    {
        return $query->where('is_missing', false);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('candle_time', 'desc');
    }
}