<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZerodhaAutoOrder extends Model
{
    use HasFactory;
    
    protected $table = 'zerodha_auto_orders';
    
    protected $fillable = [
        'user_id',
        'config_id',
        'broker_api_id',
        'symbol',                    // ✅ ADANIPORTS
        'trading_symbol',            // ✅ ADANIPORTS26JANFUT
        'instrument_token',          // ✅ Token for the FUT
        'signal_type',
        'signal_strategy',
        'supertrend_signal',
        'vwap_signal',
        'signal_detected_at',
        'option_symbol',
        'option_token',
        'option_type',
        'strike_price',
        'atm_price',
        'entry_price',
        'current_price',
        'order_type',
        'product',
        'quantity',
        'pyramid_1',
        'pyramid_2',
        'pyramid_3',
        'is_order_placed',
        'order_placed_at',
        'status'
    ];

    protected $casts = [
        'signal_detected_at' => 'datetime',
        'order_placed_at' => 'datetime',
        'strike_price' => 'decimal:2',
        'atm_price' => 'decimal:2',
        'entry_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'quantity' => 'integer',
        'pyramid_1' => 'integer',
        'pyramid_2' => 'integer',
        'pyramid_3' => 'integer',
        'is_order_placed' => 'boolean',
        'status' => 'boolean'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function config()
    {
        return $this->belongsTo(ZerodhaAutoConfig::class, 'config_id');
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function orderBooks()
    {
        return $this->hasMany(OrderBook::class, 'symbol_auto_order_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('is_order_placed', false)
            ->where('status', true);
    }

    public function scopePlaced($query)
    {
        return $query->where('is_order_placed', true);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('signal_detected_at', today());
    }
}