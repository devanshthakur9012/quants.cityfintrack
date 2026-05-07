<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PositionHistory extends Model
{
    protected $table = 'position_history';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'symbol',
        'exchange',
        'product',
        'instrument_token',
        'position_type',
        'qty',
        'buy_qty',
        'sell_qty',
        'entry_price',
        'exit_price',
        'buy_value',
        'sell_value',
        'realized_pnl',
        'entry_date',
        'exit_date',
        'holding_days',
        'exit_source',
        'portfolio_position_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'exit_date' => 'date',
        'realized_pnl' => 'float',
        'entry_price' => 'float',
        'exit_price' => 'float',
    ];

    // ─── Relationships ────────────────────────────────────────────
    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────
    public function scopeToday($query)
    {
        return $query->whereDate('exit_date', today());
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForBroker($query, $brokerId)
    {
        return $query->where('broker_api_id', $brokerId);
    }

    // ─── Helpers ──────────────────────────────────────────────────
    public function getPnlColorAttribute(): string
    {
        return $this->realized_pnl >= 0 ? 'text-success' : 'text-danger';
    }

    public function getPnlSignAttribute(): string
    {
        return $this->realized_pnl >= 0 ? '+' : '';
    }

    public function getFormattedPnlAttribute(): string
    {
        return $this->pnl_sign . '₹' . number_format(abs($this->realized_pnl), 2);
    }
}