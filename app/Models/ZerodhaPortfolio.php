<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZerodhaPortfolio extends Model
{
    use HasFactory;
    protected $table = 'zerodha_portfolios';

    protected $fillable = [
        'user_id',
        'config_id',
        'broker_api_id',
        'symbol_name',
        'trading_symbol',
        'instrument_token',
        'lot_size',
        'supertrend_signal',
        'donchian_signal',
        'combined_signal',
        'txn_type',
        'order_type',
        'product',
        'quantity',
        'disc_ltp',
        'pyramid_1',
        'pyramid_2',
        'pyramid_3',
        'pyramid_percent',
        'pyramid_freq',
        'entry_price',
        'current_price',
        'is_order_placed',
        'status',
        'signal_detected_at',
        'order_placed_at',
        'last_checked_at'
    ];

    protected $casts = [
        'is_order_placed' => 'boolean',
        'status' => 'boolean',
        'signal_detected_at' => 'datetime',
        'order_placed_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function config()
    {
        return $this->belongsTo(ZerodhaOrder::class, 'config_id');
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function orderBooks()
    {
        return $this->hasMany(OrderBook::class, 'portfolio_id');
    }
}
