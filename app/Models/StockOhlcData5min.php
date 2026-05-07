<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * StockOhlcData5min
 *
 * Eloquent model for stock_ohlc_data_5min.
 * NSE EQ spot candles for stock option underlyings.
 *
 * ── QUICK REFERENCE ──────────────────────────────────────────────────────
 *
 *  Key columns:
 *    symbol          — base symbol (RELIANCE, TCS …)
 *    trade_date      — YYYY-MM-DD
 *    interval_time   — YYYY-MM-DD HH:MM:SS  (09:15, 09:20 … 15:30)
 *    series          — 'MAR' | 'APR' etc.   (month only)
 *    series_type     — 'MAR25' | 'APR25'    (month+year, ABSOLUTE — never 'current'/'next')
 *    open/high/low/close — spot OHLC
 *    volume          — traded volume
 *    is_missing      — 1 if candle was absent in API response
 *
 *  Scopes:
 *    ->forSymbol('RELIANCE')
 *    ->forDate('2025-03-20')
 *    ->forSeries('MAR25')
 *    ->realData()           — excludes is_missing = 1
 *    ->inRange('09:15', '11:00')
 *
 * ── TYPICAL SIGNAL ENGINE QUERY ──────────────────────────────────────────
 *
 *  StockOhlcData5min::forSymbol('RELIANCE')
 *      ->forDate('2025-03-20')
 *      ->forSeries('MAR25')
 *      ->realData()
 *      ->orderBy('interval_time')
 *      ->get();
 *
 * ─────────────────────────────────────────────────────────────────────────
 *
 * @property int         $id
 * @property int         $broker_api_id
 * @property string      $trade_date
 * @property string      $interval_time
 * @property string      $symbol
 * @property string      $trading_symbol
 * @property int         $instrument_token
 * @property string      $exchange
 * @property string      $series
 * @property string      $series_type
 * @property float       $open
 * @property float       $high
 * @property float       $low
 * @property float       $close
 * @property int         $volume
 * @property int         $is_missing
 * @property string      $created_at
 * @property string      $updated_at
 */
class StockOhlcData5min extends Model
{
    protected $table = 'stock_ohlc_data_5min';

    // ── Mass assignment ───────────────────────────────────────────────────
    protected $fillable = [
        'broker_api_id',
        'trade_date',
        'interval_time',
        'symbol',
        'trading_symbol',
        'instrument_token',
        'exchange',
        'series',
        'series_type',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'is_missing',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────
    protected $casts = [
        'trade_date'       => 'date:Y-m-d',
        'interval_time'    => 'datetime',
        'open'             => 'float',
        'high'             => 'float',
        'low'              => 'float',
        'close'            => 'float',
        'volume'           => 'integer',
        'instrument_token' => 'integer',
        'broker_api_id'    => 'integer',
        'is_missing'       => 'integer',
    ];

    // ══════════════════════════════════════════════════════════════════════
    // QUERY SCOPES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Filter by base symbol.
     *
     * @example StockOhlcData5min::forSymbol('RELIANCE')->...
     */
    public function scopeForSymbol(Builder $query, string $symbol): Builder
    {
        return $query->where('symbol', strtoupper($symbol));
    }

    /**
     * Filter by trade date.
     *
     * @example ->forDate('2025-03-20')
     * @example ->forDate(Carbon::today())
     */
    public function scopeForDate(Builder $query, string|\Carbon\Carbon $date): Builder
    {
        return $query->whereDate('trade_date', $date);
    }

    /**
     * Filter by absolute series_type.
     * Always use this over filtering series alone — series_type is unambiguous.
     *
     * @example ->forSeries('MAR25')
     */
    public function scopeForSeries(Builder $query, string $seriesType): Builder
    {
        return $query->where('series_type', strtoupper($seriesType));
    }

    /**
     * Exclude rows where API had no candle data.
     * Use for signal logic — missing rows have all OHLCV as zero.
     */
    public function scopeRealData(Builder $query): Builder
    {
        return $query->where('is_missing', 0);
    }

    /**
     * Filter to a time window within the trading day.
     * Times as 'H:i' strings e.g. '09:15', '11:00', '15:30'
     *
     * @example ->inRange('09:15', '11:00')
     */
    public function scopeInRange(Builder $query, string $from, string $to): Builder
    {
        return $query
            ->whereTime('interval_time', '>=', $from)
            ->whereTime('interval_time', '<=', $to);
    }

    /**
     * Filter to only missing candles (gap detection).
     */
    public function scopeMissingOnly(Builder $query): Builder
    {
        return $query->where('is_missing', 1);
    }

    // ══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The broker API account this data was collected through.
     */
    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}