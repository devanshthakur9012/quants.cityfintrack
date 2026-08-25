<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpOrderConfig extends Model
{
    protected $table = 'cp_order_configs';

    protected $fillable = [
    'user_id', 'cp_analysis_id', 'broker_type', 'broker_api_id',
        'order_type', 'product', 'disc_ltp', 'signal_mode', 'quantity',
        'status', 'last_run_date',
    ];

    protected $casts = [
        'status'        => 'boolean',
        'disc_ltp'      => 'float',
        'last_run_date' => 'date',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   public function analysis()
    {
        return $this->belongsTo(\App\Models\CpAnalysis::class, 'cp_analysis_id');
    }
    
    public function broker()
    {
        return $this->belongsTo(\App\Models\BrokerApi::class, 'broker_api_id');
    }
    
    public function orders()
    {
        return $this->hasMany(\App\Models\CpOrder::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Discount-adjusted LIMIT price (no-op for MARKET orders) */
    public function applyDiscount(float $ltp): float
    {
        if ($this->order_type !== 'LIMIT' || $ltp <= 0) {
            return 0;
        }
    
        $discountPct = (float) ($this->disc_ltp ?? 0);
        $price = $ltp * (1 - ($discountPct / 100));
    
        return round(max($price, 0.05), 2); // never quote a non-positive/absurd price
    }

}