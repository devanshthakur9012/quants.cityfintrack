<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfPosition extends Model
{
    protected $table = 'mf_positions';

    protected $fillable = [
        'symbol', 'exchange', 'mutual_fund_id',
        'allocation_pct', 'invested_amount', 'quantity',
        'buy_price', 'buy_time', 'buy_signal_reason',
        'sell_price', 'sell_time', 'sell_signal_reason',
        'booked_profit', 'booked_profit_pct',
        'status',
    ];

    protected $casts = [
        'buy_time'            => 'datetime',
        'sell_time'           => 'datetime',
        'buy_price'           => 'decimal:2',
        'sell_price'          => 'decimal:2',
        'invested_amount'     => 'decimal:2',
        'quantity'            => 'decimal:4',
        'booked_profit'       => 'decimal:2',
        'booked_profit_pct'   => 'decimal:4',
        'allocation_pct'      => 'decimal:2',
    ];

    // ─── Relationships ───────────────────────────────────────

    public function fund(): BelongsTo
    {
        return $this->belongsTo(MutualFund::class, 'mutual_fund_id');
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Running P&L against current price (for OPEN positions)
     * running_pnl = (current_price - buy_price) × quantity
     */
    public function runningPnl(float $currentPrice): float
    {
        if ($this->status !== 'OPEN' || $this->quantity <= 0) return 0;
        return round(($currentPrice - (float)$this->buy_price) * (float)$this->quantity, 2);
    }

    public function runningPnlPct(float $currentPrice): float
    {
        if ($this->buy_price <= 0) return 0;
        return round((($currentPrice - (float)$this->buy_price) / (float)$this->buy_price) * 100, 2);
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'OPEN');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'CLOSED');
    }

    public function scopeForFund($query, int $fundId)
    {
        return $query->where('mutual_fund_id', $fundId);
    }

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }
}