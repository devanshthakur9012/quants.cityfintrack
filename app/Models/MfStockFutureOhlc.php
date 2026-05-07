<?php
// ═══════════════════════════════════════════════════
// File: app/Models/MfStockFutureOhlc.php
// ═══════════════════════════════════════════════════

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MfStockFutureOhlc extends Model
{
    protected $table = 'mf_stock_futures_ohlc';

    protected $fillable = [
        'symbol', 'exchange', 'trading_symbol', 'instrument_token',
        'trade_date', 'interval_time', 'expiry_date', 'lot_size',
        'open', 'high', 'low', 'close',
        'volume', 'oi',
        'spot_price', 'atm_strike',
        'is_missing',
    ];

    protected $casts = [
        'trade_date'    => 'date',
        'expiry_date'   => 'date',
        'interval_time' => 'datetime',
        'open'          => 'decimal:2',
        'high'          => 'decimal:2',
        'low'           => 'decimal:2',
        'close'         => 'decimal:2',
        'spot_price'    => 'decimal:2',
        'atm_strike'    => 'decimal:2',
        'volume'        => 'integer',
        'oi'            => 'integer',
        'lot_size'      => 'integer',
        'is_missing'    => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────
    public function scopeForSymbol($q, string $symbol)      { return $q->where('symbol', $symbol); }
    public function scopeForDate($q, string $date)          { return $q->where('trade_date', $date); }
    public function scopeForExpiry($q, string $expiry)      { return $q->where('expiry_date', $expiry); }
    public function scopeNotMissing($q)                     { return $q->where('is_missing', false); }
    public function scopeDateRange($q, string $from, string $to) { return $q->whereBetween('trade_date', [$from, $to]); }

    /**
     * Latest interval time for each symbol — used for gap-fill detection.
     */
    public static function latestIntervalPerSymbol(string $date): array
    {
        return static::where('trade_date', $date)
            ->where('is_missing', false)
            ->groupBy('symbol')
            ->selectRaw('symbol, MAX(interval_time) as latest_interval')
            ->pluck('latest_interval', 'symbol')
            ->toArray();
    }
}