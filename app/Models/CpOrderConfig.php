<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpOrderConfig extends Model
{
    protected $table = 'cp_order_configs';

    protected $fillable = [
        'user_id',
        'cp_analysis_id',
        'broker_type',
        'broker_api_id',
        'order_type',
        'product',
        'disc_ltp',
        'signal_mode',
        'quantity',
        'status',
    ];

    protected $casts = [
        'status'   => 'boolean',
        'disc_ltp' => 'float',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analysis()
    {
        return $this->belongsTo(CpAnalysis::class, 'cp_analysis_id');
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function orders()
    {
        return $this->hasMany(CpOrder::class, 'cp_order_config_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Discount-adjusted LIMIT price (no-op for MARKET orders) */
    public function applyDiscount(float $ltp): float
    {
        if ($this->order_type !== 'LIMIT' || $this->disc_ltp <= 0) {
            return round($ltp, 2);
        }
        return round($ltp - ($ltp * $this->disc_ltp / 100), 2);
    }
}