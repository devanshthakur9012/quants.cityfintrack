<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuturesData extends Model
{
    use HasFactory;
    
    protected $table = 'futures_data';

    protected $fillable = [
        'trading_symbol',
        'exchange',
        'instrument_token',
        'interval',
        'timestamp',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'signal',
        'trend',
        'entry',
        'sl',
        'target',
        'vwap'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'volume' => 'integer',
        'oi' => 'integer',
        'entry' => 'decimal:2',
        'sl' => 'decimal:2',
        'target' => 'decimal:2',
        'vwap' => 'decimal:2'
    ];

    /**
     * Get latest data for a symbol
     */
    public static function getLatest($tradingSymbol, $interval = '15minute', $limit = 100)
    {
        return self::where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get data for a date range
     */
    public static function getRange($tradingSymbol, $fromDate, $toDate, $interval = '15minute')
    {
        return self::where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->whereBetween('timestamp', [$fromDate, $toDate])
            ->orderBy('timestamp', 'asc')
            ->get();
    }

    /**
     * Get data for signal calculation (last 50 candles)
     */
    public static function getForSignalCalculation($tradingSymbol, $timestamp, $interval = '15minute', $limit = 50)
    {
        return self::where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->where('timestamp', '<=', $timestamp)
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->toArray();
    }
}