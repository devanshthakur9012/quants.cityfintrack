<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExitPlanOrder extends Model
{
    protected $fillable = [
        'user_id', 'config_id', 'broker_api_id', 'oiiv_order_id',
        'symbol', 'trading_symbol',
        'signal_date', 'exit_check_date',
        'original_sentiment', 'original_trade_action', 'original_oi_condition',
        'exit_sentiment', 'exit_oi_condition', 'exit_ce_oi_pct', 'exit_pe_oi_pct',
        'exit_decision', 'exit_reason',
        'option_symbol', 'option_token', 'option_type', 'strike_price',
        'exit_price', 'current_price',
        'order_type', 'product', 'quantity',
        'is_order_placed', 'order_placed_at', 'status', 'signal_detected_at',
    ];

    protected $casts = [
        'is_order_placed'    => 'boolean',
        'status'             => 'boolean',
        'order_placed_at'    => 'datetime',
        'signal_detected_at' => 'datetime',
        'exit_price'         => 'float',
        'current_price'      => 'float',
        'strike_price'       => 'float',
        'exit_ce_oi_pct'     => 'float',
        'exit_pe_oi_pct'     => 'float',
    ];

    public function config()
    {
        return $this->belongsTo(ExitPlanConfig::class, 'config_id');
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function originalOrder()
    {
        return $this->belongsTo(OIIVAutoOrder::class, 'oiiv_order_id');
    }
}