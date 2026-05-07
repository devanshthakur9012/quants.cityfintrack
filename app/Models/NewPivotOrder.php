<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewPivotOrder extends Model
{
    protected $table = 'new_pivot_orders';

    protected $fillable = [
        'user_id',
        'config_id',
        'broker_api_id',
        'symbol',
        'option_symbol',
        'option_token',
        'option_type',
        'strike_price',
        'trigger_level',
        'layer_index',      // ← NEW: which layer (1, 2, 3...)
        'transaction_type',
        'raw_level_price',
        'order_price',
        'candle_time',
        'order_type',
        'product',
        'quantity',
        'kite_order_id',
        'kite_status',
        'is_order_placed',
        'order_placed_at',
        'error_message',
        'status',
    ];

    protected $casts = [
        'strike_price'    => 'decimal:2',
        'raw_level_price' => 'decimal:2',
        'order_price'     => 'decimal:2',
        'option_token'    => 'integer',
        'quantity'        => 'integer',
        'layer_index'     => 'integer',
        'is_order_placed' => 'boolean',
        'status'          => 'boolean',
        'order_placed_at' => 'datetime',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function config(): BelongsTo { return $this->belongsTo(NewPivotOrderConfig::class, 'config_id'); }
    public function broker(): BelongsTo { return $this->belongsTo(BrokerApi::class, 'broker_api_id'); }
}