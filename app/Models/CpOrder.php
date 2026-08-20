<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpOrder extends Model
{
    protected $table = 'cp_orders';

    protected $fillable = [
        'user_id',
        'cp_order_config_id',
        'cp_analysis_id',
        'broker_api_id',
        'broker_type',
        'symbol',
        'option_symbol',
        'option_token',
        'option_type',
        'strike',
        'signal_date',
        'signal_action',
        'transaction_type',
        'order_type',
        'product',
        'lots',
        'quantity',
        'order_price',
        'broker_order_id',
        'broker_status',
        'is_order_placed',
        'order_placed_at',
        'error_message',
        'meta',
    ];

    protected $casts = [
        'signal_date'      => 'date',
        'order_placed_at'  => 'datetime',
        'is_order_placed'  => 'boolean',
        'strike'           => 'float',
        'order_price'      => 'float',
        'meta'             => 'array',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function config()
    {
        return $this->belongsTo(CpOrderConfig::class, 'cp_order_config_id');
    }

    public function analysis()
    {
        return $this->belongsTo(CpAnalysis::class, 'cp_analysis_id');
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}