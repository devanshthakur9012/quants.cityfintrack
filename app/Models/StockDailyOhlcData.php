<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockDailyOhlcData extends Model
{
    protected $table    = 'stock_daily_ohlc_data';

    protected $fillable = [
        'broker_api_id',
        'symbol',
        'exchange',
        'trading_symbol',
        'instrument_token',
        'trade_date',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'is_missing',
    ];

    protected $casts = [
        'open'       => 'decimal:2',
        'high'       => 'decimal:2',
        'low'        => 'decimal:2',
        'close'      => 'decimal:2',
        'volume'     => 'integer',
        'is_missing' => 'integer',
        'trade_date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function symbolConfig()
    {
        return $this->belongsTo(StockDailyOhlcSymbol::class, 'symbol', 'symbol');
    }

    // ── Query helpers ─────────────────────────────────────────────────────────

    public static function getForSymbolDate(string $symbol, string $tradeDate)
    {
        return self::where('symbol', $symbol)
            ->where('trade_date', $tradeDate)
            ->first();
    }

    public static function getForSymbolRange(string $symbol, string $from, string $to)
    {
        return self::where('symbol', $symbol)
            ->whereBetween('trade_date', [$from, $to])
            ->orderBy('trade_date', 'asc')
            ->get();
    }

    public static function getLatestForSymbol(string $symbol)
    {
        return self::where('symbol', $symbol)
            ->where('is_missing', 0)
            ->orderBy('trade_date', 'desc')
            ->first();
    }

    public static function getMissingDates(string $symbol, string $from, string $to): array
    {
        return self::where('symbol', $symbol)
            ->where('is_missing', 1)
            ->whereBetween('trade_date', [$from, $to])
            ->pluck('trade_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString())
            ->toArray();
    }
}