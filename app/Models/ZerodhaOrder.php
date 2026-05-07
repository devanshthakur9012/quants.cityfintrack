<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZerodhaOrder extends Model
{
    use HasFactory;
    protected $table = 'zerodha_orders';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'buildup_type',
        'order_type',
        'product',
        'disc_ltp',
        'quantity',
        'pyramid_percent',
        'pyramid_freq',
        'exit_1_qty',
        'exit_1_target',
        'exit_2_qty',
        'exit_2_target',
        'status',
        'order_date',
        'last_sync_at',
        'last_checked_at'
    ];

    protected $casts = [
        'status' => 'boolean',
        'order_date' => 'date',
        'last_sync_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function zerodhaPortfolio()
    {
        return $this->hasMany(ZerodhaPortfolio::class, 'config_id');
    }

    /**
     * Calculate pyramid quantities based on percentage
     */
    public function calculatePyramids($totalQuantity)
    {
        if (!$this->pyramid_percent || $this->pyramid_percent == 100) {
            return [$totalQuantity, null, null];
        }

        if ($this->pyramid_percent == 50) {
            $half = floor($totalQuantity / 2);
            return [$half, $totalQuantity - $half, null];
        }

        if ($this->pyramid_percent == 33) {
            $third = floor($totalQuantity / 3);
            return [$third, $third, $totalQuantity - ($third * 2)];
        }

        return [$totalQuantity, null, null];
    }
}