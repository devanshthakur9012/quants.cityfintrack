<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FutOhlAutoOrder extends Model
{
    protected $table = 'fut_ohl_auto_orders';

    protected $fillable = [
        'user_id', 'config_id', 'broker_api_id',
        'symbol', 'trading_symbol', 'series_expiry',
        'signal_type', 'trade_action', 'tolerance_used',
        'open_price', 'high_915', 'low_915', 'spot_price',
        'signal_detected_at', 'signal_date',
        'option_symbol', 'option_token', 'option_type', 'strike_price',
        'order_type', 'product', 'quantity', 'entry_price', 'current_price',
        'is_order_placed', 'order_placed_at', 'status',
        'zerodha_order_id', 'failure_reason',
    ];

    protected $casts = [
        'tolerance_used'     => 'decimal:2',
        'open_price'         => 'decimal:2',
        'high_915'           => 'decimal:2',
        'low_915'            => 'decimal:2',
        'spot_price'         => 'decimal:2',
        'strike_price'       => 'decimal:2',
        'entry_price'        => 'decimal:2',
        'current_price'      => 'decimal:2',
        'quantity'           => 'integer',
        'option_token'       => 'integer',
        'is_order_placed'    => 'boolean',
        'status'             => 'boolean',
        'signal_detected_at' => 'datetime',
        'order_placed_at'    => 'datetime',
        'signal_date'        => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(FutOhlAutoConfig::class, 'config_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}