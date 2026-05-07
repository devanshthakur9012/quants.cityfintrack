<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderBook extends Model
{
    use HasFactory;

     protected $fillable = [
        'zerodha_auto_order_id',
        'broker_username',
        'order_id',
        'status',
        'trading_symbol',
        'order_type',
        'transaction_type',
        'product',
        'price',
        'quantity',
        'status_message',
        'order_datetime',
        'user_id',
        'oiiv_auto_order_id'
    ];

    public function zerodhaAutoOrder()
    {
        return $this->belongsTo(ZerodhaAutoOrder::class, 'zerodha_auto_order_id');
    }

    public function expiryAutoOrder()
    {
        return $this->belongsTo(ExpiryAutoOrder::class, 'expiry_auto_order_id');
    }

    public function oiivAutoOrder(): BelongsTo
    {
        return $this->belongsTo(OIIVAutoOrder::class, 'oiiv_auto_order_id');
    }

}
