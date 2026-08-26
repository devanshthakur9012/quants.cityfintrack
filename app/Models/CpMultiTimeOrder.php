<?php
// app/Models/CpMultiTimeOrder.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpMultiTimeOrder extends Model
{
    protected $fillable = [
        'user_id', 'cp_multi_time_order_config_id', 'broker_api_id', 'broker_type',
        'symbol', 'option_symbol', 'option_token', 'option_type', 'strike',
        'signal_date', 'signal_time', 'signal_action', 'transaction_type',
        'order_type', 'product', 'lots', 'quantity', 'order_price',
        'broker_order_id', 'broker_status', 'is_order_placed', 'error_message',
        'meta', 'order_placed_at',
    ];

    protected $casts = [
        'signal_date'     => 'date',
        'is_order_placed' => 'boolean',
        'meta'            => 'array',
        'order_placed_at' => 'datetime',
    ];

    public function config()
    {
        return $this->belongsTo(CpMultiTimeOrderConfig::class, 'cp_multi_time_order_config_id');
    }
}