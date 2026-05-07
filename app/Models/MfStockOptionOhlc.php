<?php
// ═══════════════════════════════════════════════════
// File: app/Models/MfStockOptionOhlc.php
// ═══════════════════════════════════════════════════

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MfStockOptionOhlc extends Model
{
    protected $table = 'mf_stock_options_ohlc';

    protected $fillable = [
        'symbol', 'exchange', 'trading_symbol', 'instrument_token',
        'option_type', 'strike_price', 'strike_position',
        'trade_date', 'interval_time', 'expiry_date',
        'open', 'high', 'low', 'close',
        'volume', 'oi',
        'fut_price', 'spot_price', 'atm_strike',
        'is_missing',
    ];

    protected $casts = [
        'trade_date'    => 'date',
        'expiry_date'   => 'date',
        'interval_time' => 'datetime',
        'strike_price'  => 'decimal:2',
        'open'          => 'decimal:2',
        'high'          => 'decimal:2',
        'low'           => 'decimal:2',
        'close'         => 'decimal:2',
        'fut_price'     => 'decimal:2',
        'spot_price'    => 'decimal:2',
        'atm_strike'    => 'decimal:2',
        'volume'        => 'integer',
        'oi'            => 'integer',
        'is_missing'    => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────
    public function scopeForSymbol($q, string $symbol)           { return $q->where('symbol', $symbol); }
    public function scopeForDate($q, string $date)               { return $q->where('trade_date', $date); }
    public function scopeForExpiry($q, string $expiry)           { return $q->where('expiry_date', $expiry); }
    public function scopeCalls($q)                               { return $q->where('option_type', 'CE'); }
    public function scopePuts($q)                                { return $q->where('option_type', 'PE'); }
    public function scopeAtm($q)                                 { return $q->where('strike_position', 'ATM'); }
    public function scopeNotMissing($q)                          { return $q->where('is_missing', false); }
    public function scopeStrike($q, float $strike)               { return $q->where('strike_price', $strike); }
    public function scopeDateRange($q, string $from, string $to) { return $q->whereBetween('trade_date', [$from, $to]); }

    /**
     * Get OTM call strikes (above ATM) — what the strategy sells.
     * Typically ATM+1 or ATM+2.
     */
    public function scopeOtmCalls($q, int $strikesAbove = 2)
    {
        return $q->where('option_type', 'CE')
                 ->whereIn('strike_position', collect(range(1, $strikesAbove))
                     ->map(fn($n) => "ATM+{$n}")
                     ->toArray());
    }
}