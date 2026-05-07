<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * NextSeriesDailyOhlcData
 *
 * Stores one DAILY OHLC bar per instrument per trading day for the
 * NEXT expiry series (next monthly or next weekly, depending on the symbol).
 */
class NextSeriesDailyOhlcData extends Model
{
    protected $table = 'next_series_daily_ohlc_data';

    protected $fillable = [
        'broker_api_id',
        'trade_date',
        'base_symbol',
        'future_symbol',
        'future_price',
        'atm_strike',
        'instrument_type',
        'strike',
        'trading_symbol',
        'instrument_token',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'strike_position',
        'expiry_date',
        'is_missing',
    ];

    protected $casts = [
        'future_price' => 'decimal:2',
        'atm_strike'   => 'decimal:2',
        'strike'       => 'decimal:2',
        'open'         => 'decimal:2',
        'high'         => 'decimal:2',
        'low'          => 'decimal:2',
        'close'        => 'decimal:2',
        'volume'       => 'integer',
        'oi'           => 'integer',
        'is_missing'   => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    // ── Query helpers ────────────────────────────────────────────────────────

    public static function getForDate($brokerId, $baseSymbol, $tradeDate)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->orderByRaw("FIELD(instrument_type, 'FUT', 'CE', 'PE')")
            ->orderBy('strike', 'asc')
            ->get();
    }

    public static function getFuture($brokerId, $baseSymbol, $tradeDate)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('instrument_type', 'FUT')
            ->first();
    }

    public static function getOptions($brokerId, $baseSymbol, $tradeDate, $optionType = null)
    {
        $query = self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->whereIn('instrument_type', ['CE', 'PE']);

        if ($optionType) {
            $query->where('instrument_type', $optionType);
        }

        return $query->orderBy('strike', 'asc')->get();
    }

    public static function getDateRange($brokerId, $baseSymbol, $fromDate, $toDate)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->whereBetween('trade_date', [$fromDate, $toDate])
            ->orderBy('trade_date', 'asc')
            ->orderByRaw("FIELD(instrument_type, 'FUT', 'CE', 'PE')")
            ->orderBy('strike', 'asc')
            ->get();
    }
}