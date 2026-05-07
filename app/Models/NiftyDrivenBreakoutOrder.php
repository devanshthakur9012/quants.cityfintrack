<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NiftyDrivenBreakoutOrder extends Model
{
    protected $table = 'nifty_driven_breakout_orders';

    protected $fillable = [
        'user_id',
        'config_id',
        'broker_api_id',
        'signal_date',
        'symbol',
        'signal_type',
        'nifty_open',
        'nifty_trigger',
        'trigger_time',
        'nifty_move',
        'threshold',
        'option_symbol',
        'option_token',
        'option_type',
        'strike_price',
        'expiry_date',
        'entry_price',
        'current_price',
        'spot_price',
        'lot_size',
        'quantity',
        'investment',
        // stop-loss (downside)
        'stoploss_enabled',
        'stoploss_price',
        'stoploss_order_id',
        'stoploss_placed',
        // profit target (upside)
        'target_price',
        'target_enabled',
        'target_placed',
        'target_order_id',
        // order meta
        'order_type',
        'product',
        'kite_order_id',
        'kite_order_status',
        'is_order_placed',
        'order_placed_at',
        'signal_detected_at',
        'signal_reason',
        'error_message',
        'status',
    ];

    protected $casts = [
        'nifty_open'         => 'decimal:2',
        'nifty_trigger'      => 'decimal:2',
        'nifty_move'         => 'decimal:2',
        'threshold'          => 'decimal:2',
        'strike_price'       => 'decimal:2',
        'entry_price'        => 'decimal:2',
        'current_price'      => 'decimal:2',
        'spot_price'         => 'decimal:2',
        'investment'         => 'decimal:2',
        'stoploss_price'     => 'decimal:2',
        'target_price'       => 'decimal:2',
        'stoploss_enabled'   => 'boolean',
        'stoploss_placed'    => 'boolean',
        'target_enabled'     => 'boolean',
        'target_placed'      => 'boolean',
        'is_order_placed'    => 'boolean',
        'status'             => 'boolean',
        'signal_detected_at' => 'datetime',
        'order_placed_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(NiftyDrivenBreakoutConfig::class, 'config_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}