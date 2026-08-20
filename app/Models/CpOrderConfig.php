<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpOrderConfig extends Model
{
    protected $fillable = [
        'user_id','cp_analysis_id','broker_type','broker_api_id',
        'order_type','product','disc_ltp','signal_mode',
        'ce_quantity','pe_quantity','status',
    ];
    protected $casts = ['status' => 'boolean', 'disc_ltp' => 'float'];

    public function analysis() { return $this->belongsTo(CpAnalysis::class, 'cp_analysis_id'); }
    public function broker()   { return $this->belongsTo(BrokerApi::class, 'broker_api_id'); }
    public function orders()   { return $this->hasMany(CpOrder::class); }

    /** Discount-adjusted LIMIT price */
    public function applyDiscount(float $ltp): float
    {
        if ($this->order_type !== 'LIMIT' || $this->disc_ltp <= 0) return round($ltp, 2);
        return round($ltp - ($ltp * $this->disc_ltp / 100), 2);
    }
}
