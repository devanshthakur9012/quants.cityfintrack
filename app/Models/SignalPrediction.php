<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignalPrediction extends Model
{
    protected $fillable = [
        'symbol', 'signal_date', 'trade_date', 'action', 'bias',
        'confidence', 'strength',
        'pcr_eod', 'pcr_change', 'pcr_bias',
        'oi_long_buildup', 'oi_short_buildup', 'oi_short_covering', 'oi_long_unwind', 'oi_bias',
        'price_change_pct', 'price_direction', 'price_strength',
        'last_hour_change_pct', 'last_hour_direction',
        'indicators_aligned', 'atm_strike', 'fut_close',
        'outcome', 'next_day_open', 'next_day_close', 'next_day_high', 'next_day_low',
        'next_day_change_pct', 'hit_t1', 'hit_t2', 'hit_sl',
        'reasons', 'indicators_detail', 'version',
    ];

    protected $casts = [
        'signal_date'    => 'date',
        'trade_date'     => 'date',
        'reasons'        => 'array',
        'indicators_detail' => 'array',
        'hit_t1'         => 'boolean',
        'hit_t2'         => 'boolean',
        'hit_sl'         => 'boolean',
    ];

    public function isWin(): bool  { return $this->outcome === 'WIN'; }
    public function isLoss(): bool { return $this->outcome === 'LOSS'; }
    public function isPending(): bool { return $this->outcome === 'PENDING'; }

    public function scopeCompleted($q) { return $q->whereIn('outcome', ['WIN', 'LOSS', 'FLAT']); }
    public function scopePending($q)   { return $q->where('outcome', 'PENDING'); }
    public function scopeActionable($q){ return $q->whereIn('action', ['BUY_CE', 'BUY_PE']); }
}