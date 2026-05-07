<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OmsConfigs extends Model
{
    use HasFactory;
    protected $table = "oms_configs_db";
    protected $fillable = [
        'master_config_id',
        'symbol_name',
        'token',
        'symbol_type',
        'broker_api_id',
        'disc_ltp',
        'portfolio_type',
        'buildup_type',
        'product',
        'order_type',
        'pyramid_percent',
        'pyramid_1',
        'pyramid_2',
        'pyramid_3',
        'txn_type',
        'quantity',
        'pyramid_freq',
        'exit_1_qty',
        'exit_1_target',
        'exit_2_qty',
        'exit_2_target',
        'user_id',
        'cron_run_at',
        'last_time',
        'is_manual',
        'is_api_pushed',
        'status'
    ];

    public function broker(){
        return $this->belongsTo(BrokerApi::class,'broker_api_id','id');
    }

    public function master(){
        return $this->belongsTo(OmsConfigMaster::class,'master_config_id','id');
    }

    // In OmsConfigs.php
    public function order()
    {
        return $this->hasOne(OrderBook::class, 'trading_symbol', 'symbol_name')->where('user_id', Auth::id())->latest('created_at');
    }

}
