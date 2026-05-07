<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * OptionOhlcData5min
 *
 * Eloquent model for option_ohlc_data_5min.
 * Stores FUT + CE + PE 5-minute OHLC data for all derivatives symbols.
 *
 * ── QUICK REFERENCE ──────────────────────────────────────────────────────
 *
 *  Key columns:
 *    base_symbol      — NIFTY, BANKNIFTY, RELIANCE etc.
 *    instrument_type  — 'FUT' | 'CE' | 'PE'
 *    trade_date       — YYYY-MM-DD
 *    interval_time    — YYYY-MM-DD HH:MM:SS  (09:15, 09:20 … 15:30)
 *    expiry_date      — actual expiry date of this instrument
 *    series           — 'MAR' | 'APR' etc.      (month only)
 *    series_type      — 'MAR25' | 'APR25' etc.  (month+year, ABSOLUTE)
 *    strike           — strike price (null for FUT rows)
 *    strike_position  — 'ATM' | 'ATM+1' | 'ATM-2' etc. (metadata)
 *    future_price     — real FUT close at this interval (never frozen at 09:15)
 *    atm_strike       — nearest strike to future_price at this interval (metadata)
 *    open/high/low/close — OHLC
 *    volume           — traded volume
 *    oi               — open interest (null = API didn't return; 0 = actual zero)
 *    is_missing       — 1 if candle was absent in API response
 *
 *  Scopes:
 *    ->forSymbol('NIFTY')
 *    ->forDate('2025-03-20')
 *    ->forSeries('MAR25')
 *    ->futures()
 *    ->calls()
 *    ->puts()
 *    ->options()                    — CE + PE together
 *    ->atStrike(23000)
 *    ->atmOnly()                    — strike_position = 'ATM'
 *    ->realData()                   — excludes is_missing = 1
 *    ->inRange('09:15', '11:00')
 *    ->withOi()                     — oi is not null
 *
 * ── TYPICAL SIGNAL ENGINE QUERIES ────────────────────────────────────────
 *
 *  // All CE+PE for NIFTY MAR25 on a date, ordered by time + strike
 *  OptionOhlcData5min::forSymbol('NIFTY')
 *      ->forDate('2025-03-20')
 *      ->forSeries('MAR25')
 *      ->options()
 *      ->realData()
 *      ->orderBy('interval_time')
 *      ->orderBy('strike')
 *      ->get();
 *
 *  // FUT candles only (for rolling ATM computation)
 *  OptionOhlcData5min::forSymbol('NIFTY')
 *      ->forDate('2025-03-20')
 *      ->forSeries('MAR25')
 *      ->futures()
 *      ->realData()
 *      ->orderBy('interval_time')
 *      ->get();
 *
 *  // ATM CE + PE at a specific interval
 *  OptionOhlcData5min::forSymbol('NIFTY')
 *      ->forDate('2025-03-20')
 *      ->forSeries('MAR25')
 *      ->options()
 *      ->atmOnly()
 *      ->where('interval_time', '2025-03-20 10:30:00')
 *      ->get();
 *
 * ─────────────────────────────────────────────────────────────────────────
 *
 * @property int         $id
 * @property int         $broker_api_id
 * @property string      $trade_date
 * @property string      $interval_time
 * @property string      $base_symbol
 * @property string      $trading_symbol
 * @property string|null $future_symbol
 * @property string      $instrument_type   FUT | CE | PE
 * @property float|null  $strike
 * @property string      $exchange
 * @property int         $instrument_token
 * @property string|null $expiry_date
 * @property string      $series            MAR | APR …
 * @property string      $series_type       MAR25 | APR25 …  (ABSOLUTE)
 * @property float       $future_price
 * @property float|null  $atm_strike
 * @property string      $strike_position   ATM | ATM+1 | ATM-2 …
 * @property float       $open
 * @property float       $high
 * @property float       $low
 * @property float       $close
 * @property int         $volume
 * @property int|null    $oi                null = no OI from API; 0 = actual zero
 * @property int         $is_missing
 * @property string      $created_at
 * @property string      $updated_at
 */
class OptionOhlcData5min extends Model
{
    protected $table = 'option_ohlc_data_5min';

    // ── Mass assignment ───────────────────────────────────────────────────
    protected $fillable = [
        'broker_api_id',
        'trade_date',
        'interval_time',
        'base_symbol',
        'trading_symbol',
        'future_symbol',
        'instrument_type',
        'strike',
        'exchange',
        'instrument_token',
        'expiry_date',
        'series',
        'series_type',
        'future_price',
        'atm_strike',
        'strike_position',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'is_missing',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────
    protected $casts = [
        'trade_date'       => 'date:Y-m-d',
        'expiry_date'      => 'date:Y-m-d',
        'interval_time'    => 'datetime',
        'strike'           => 'float',
        'future_price'     => 'float',
        'atm_strike'       => 'float',
        'open'             => 'float',
        'high'             => 'float',
        'low'              => 'float',
        'close'            => 'float',
        'volume'           => 'integer',
        'oi'               => 'integer',   // stays null if null — cast only applies when not null
        'instrument_token' => 'integer',
        'broker_api_id'    => 'integer',
        'is_missing'       => 'integer',
    ];

    // ══════════════════════════════════════════════════════════════════════
    // QUERY SCOPES — SYMBOL + DATE + SERIES
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Filter by base symbol.
     *
     * @example ->forSymbol('NIFTY')
     */
    public function scopeForSymbol(Builder $query, string $symbol): Builder
    {
        return $query->where('base_symbol', strtoupper($symbol));
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
     * Always prefer this over filtering series (month-only) — series_type is unambiguous.
     *
     * @example ->forSeries('MAR25')
     */
    public function scopeForSeries(Builder $query, string $seriesType): Builder
    {
        return $query->where('series_type', strtoupper($seriesType));
    }

    /**
     * Filter by expiry date.
     *
     * @example ->forExpiry('2025-03-27')
     */
    public function scopeForExpiry(Builder $query, string $expiry): Builder
    {
        return $query->whereDate('expiry_date', $expiry);
    }

    // ══════════════════════════════════════════════════════════════════════
    // QUERY SCOPES — INSTRUMENT TYPE
    // ══════════════════════════════════════════════════════════════════════

    /**
     * FUT rows only.
     * Use to fetch rolling ATM or underlying price per interval.
     */
    public function scopeFutures(Builder $query): Builder
    {
        return $query->where('instrument_type', 'FUT');
    }

    /**
     * CE (Call) rows only.
     */
    public function scopeCalls(Builder $query): Builder
    {
        return $query->where('instrument_type', 'CE');
    }

    /**
     * PE (Put) rows only.
     */
    public function scopePuts(Builder $query): Builder
    {
        return $query->where('instrument_type', 'PE');
    }

    /**
     * CE + PE rows together (excludes FUT).
     * Most common options analysis query.
     */
    public function scopeOptions(Builder $query): Builder
    {
        return $query->whereIn('instrument_type', ['CE', 'PE']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // QUERY SCOPES — STRIKE
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Filter to a specific strike price.
     *
     * @example ->atStrike(23000)
     */
    public function scopeAtStrike(Builder $query, float $strike): Builder
    {
        return $query->where('strike', $strike);
    }

    /**
     * ATM rows only (strike_position = 'ATM').
     * Note: strike_position is metadata stored at collection time.
     * Signal engine may recompute ATM dynamically from future_price.
     */
    public function scopeAtmOnly(Builder $query): Builder
    {
        return $query->where('strike_position', 'ATM');
    }

    /**
     * Filter strikes within N steps of ATM.
     * Useful for signal engine to load a narrow window around current ATM.
     *
     * @example ->nearAtm(3)  → ATM-3 to ATM+3
     */
    public function scopeNearAtm(Builder $query, int $steps): Builder
    {
        // Build all strike_position values: ATM, ATM+1 … ATM+N, ATM-1 … ATM-N
        $positions = ['ATM'];
        for ($i = 1; $i <= $steps; $i++) {
            $positions[] = "ATM+{$i}";
            $positions[] = "ATM-{$i}";
        }
        return $query->whereIn('strike_position', $positions);
    }

    // ══════════════════════════════════════════════════════════════════════
    // QUERY SCOPES — DATA QUALITY
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Exclude rows where API had no candle data.
     * Missing rows have all OHLCV as zero. Always use for signal logic.
     */
    public function scopeRealData(Builder $query): Builder
    {
        return $query->where('is_missing', 0);
    }

    /**
     * Missing candles only — for gap detection and re-fetch logic.
     */
    public function scopeMissingOnly(Builder $query): Builder
    {
        return $query->where('is_missing', 1);
    }

    /**
     * Rows where OI data was returned by API (not null).
     * Excludes rows where API didn't provide OI (null).
     * Note: rows with oi = 0 (actual zero OI) ARE included.
     */
    public function scopeWithOi(Builder $query): Builder
    {
        return $query->whereNotNull('oi');
    }

    // ══════════════════════════════════════════════════════════════════════
    // QUERY SCOPES — TIME
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Filter to a time window within the trading day.
     *
     * @example ->inRange('09:15', '11:00')
     * @example ->inRange('14:00', '15:30')
     */
    public function scopeInRange(Builder $query, string $from, string $to): Builder
    {
        return $query
            ->whereTime('interval_time', '>=', $from)
            ->whereTime('interval_time', '<=', $to);
    }

    /**
     * Filter to a specific interval time.
     *
     * @example ->atTime('10:30')
     */
    public function scopeAtTime(Builder $query, string $time): Builder
    {
        return $query->whereTime('interval_time', $time);
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