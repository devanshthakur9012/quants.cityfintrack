<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuroDailyVerdict extends Model
{
    protected $table = 'auro_daily_verdicts';

    protected $fillable = [
        'trade_date', 'expiry_date',
        'direction', 'net_score', 'confidence',
        'atm_strike', 'recommended_strike', 'recommended_strike_position', 'recommended_strike_ltp',
        'sig_a_score', 'ce_oi_today', 'ce_oi_5day_avg', 'ce_oi_change_pct',
        'pe_oi_today', 'pe_oi_5day_avg', 'pe_oi_change_pct', 'ce_pe_ratio', 'sig_a_verdict',
        'sig_b_score', 'sig_b_far_otm_detail', 'sig_b_hidden_bear_days', 'sig_b_hidden_bull_days', 'sig_b_verdict',
        'sig_c_score', 'fut_close_3pm', 'support_20d', 'resistance_20d',
        'dist_to_support_pct', 'dist_to_resistance_pct', 'fut_oi_type', 'sig_c_verdict',
        'sig_d_score', 'nifty_5d_trend', 'pharma_5d_trend', 'nifty_close_3pm', 'sig_d_verdict',
        'sig_e_verdict', 'sig_e_detail', 'sig_e_suggested_strike',
        'auro_volatility_5d', 'market_regime',
        'veto_market_opposing', 'veto_low_volume', 'veto_expiry_week', 'veto_conflicting_signals',
        'actual_open_next_day', 'actual_option_open', 'actual_option_high',
        'actual_pnl_pct', 'was_correct', 'miss_reason', 'post_trade_notes',
        'generated_at', 'is_backtest',
    ];

    protected $casts = [
        'trade_date'          => 'date',
        'sig_b_far_otm_detail'=> 'array',
        'sig_e_detail'        => 'array',
        'net_score'           => 'float',
        'sig_a_score'         => 'float',
        'sig_b_score'         => 'float',
        'sig_c_score'         => 'float',
        'sig_d_score'         => 'float',
        'veto_market_opposing'      => 'boolean',
        'veto_low_volume'           => 'boolean',
        'veto_expiry_week'          => 'boolean',
        'veto_conflicting_signals'  => 'boolean',
        'was_correct'               => 'boolean',
        'is_backtest'               => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeTraded($q)      { return $q->whereIn('direction', ['BUY_CE', 'BUY_PE']); }
    public function scopeNoTrade($q)     { return $q->where('direction', 'NO_TRADE'); }
    public function scopeCorrect($q)     { return $q->where('was_correct', 1); }
    public function scopeWrong($q)       { return $q->where('was_correct', 0); }
    public function scopeHighConf($q)    { return $q->whereIn('confidence', ['VERY_HIGH', 'HIGH']); }
    public function scopeLive($q)        { return $q->where('is_backtest', 0); }
    public function scopeBacktest($q)    { return $q->where('is_backtest', 1); }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getScoreColorAttribute(): string
    {
        if ($this->net_score >= 8)  return '#00e676';
        if ($this->net_score >= 6)  return '#69f0ae';
        if ($this->net_score <= -8) return '#ff1744';
        if ($this->net_score <= -6) return '#ff5252';
        return '#78909c';
    }

    public function getAnyVetoAttribute(): bool
    {
        return $this->veto_market_opposing
            || $this->veto_low_volume
            || $this->veto_expiry_week
            || $this->veto_conflicting_signals;
    }

    public function getWinRateAttribute(): ?float
    {
        // For collections — use static method
        return null;
    }

    // ── Static Stats ──────────────────────────────────────────────────────────
    public static function stats(?string $from = null, ?string $to = null): array
    {
        $q = self::traded();
        if ($from) $q->where('trade_date', '>=', $from);
        if ($to)   $q->where('trade_date', '<=', $to);

        $all     = $q->whereNotNull('was_correct')->get();
        $total   = $all->count();
        $wins    = $all->where('was_correct', 1)->count();
        $losses  = $total - $wins;
        $winRate = $total > 0 ? round(($wins / $total) * 100, 1) : 0;

        $avgWin  = $all->where('was_correct', 1)->avg('actual_pnl_pct') ?? 0;
        $avgLoss = $all->where('was_correct', 0)->avg('actual_pnl_pct') ?? 0;
        $rr      = ($avgLoss != 0) ? round(abs($avgWin / $avgLoss), 2) : 0;

        $noTrade = self::noTrade()
            ->when($from, fn($q) => $q->where('trade_date', '>=', $from))
            ->when($to,   fn($q) => $q->where('trade_date', '<=', $to))
            ->count();

        return compact('total', 'wins', 'losses', 'winRate', 'avgWin', 'avgLoss', 'rr', 'noTrade');
    }
}