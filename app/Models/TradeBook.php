<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeBook extends Model
{
    protected $table = 'trade_book';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'broker_name',
        'upload_month',
        'symbol',
        'trade_date',
        'trade_time',
        'trade_type',
        'quantity',
        'price',
        'exchange',
        'segment',
        'trade_id',
        'order_id',
        'order_execution_time',
        'expiry_date',
    ];

    protected $casts = [
        'trade_date'  => 'date',
        'expiry_date' => 'date',
        'quantity'    => 'decimal:2',
        'price'       => 'decimal:4',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}