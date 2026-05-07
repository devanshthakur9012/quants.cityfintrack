<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OIIVAutoOrder extends Model
{
    protected $table = 'oiiv_auto_orders';

    protected $fillable = [
        'user_id',
        'config_id',
        'broker_api_id',
        'symbol',
        'trading_symbol',
        'instrument_token',
        'btst_signal',
        'btst_confidence',
        'btst_reason',
        'signal_detected_at',
        'fut_oi_signal',
        'fut_oi_strength',
        'ce_oi_signal',
        'pe_oi_signal',
        'ce_iv_signal',
        'ce_iv_strength',
        'pe_iv_signal',
        'pe_iv_strength',
        'spot_price',
        'option_symbol',
        'option_token',
        'option_type',
        'strike_price',
        'entry_price',
        'current_price',
        'order_type',
        'product',
        'quantity',
        'is_order_placed',
        'order_placed_at',
        'status',
    ];

    protected $casts = [
        'instrument_token' => 'integer',
        'option_token' => 'integer',
        'btst_confidence' => 'integer',
        'spot_price' => 'decimal:2',
        'strike_price' => 'decimal:2',
        'entry_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'quantity' => 'integer',
        'is_order_placed' => 'boolean',
        'status' => 'boolean',
        'signal_detected_at' => 'datetime',
        'order_placed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(OIIVAutoConfig::class, 'config_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}