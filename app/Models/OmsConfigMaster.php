<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmsConfigMaster extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'portfolio_type',
        'buildup_type', 
        'broker_api_id',
        'disc_ltp',
        'order_type',
        'pyramid_percent',
        'product',
        'quantity',
        'pyramid_freq',
        'exit_1_qty',
        'exit_1_target',
        'exit_2_qty', 
        'exit_2_target',
        'user_id',
        'status',
        'last_sync_at'
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'disc_ltp' => 'decimal:2',
        'exit_1_qty' => 'decimal:2',
        'exit_1_target' => 'decimal:2',
        'exit_2_qty' => 'decimal:2',
        'exit_2_target' => 'decimal:2',
    ];

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function omsConfigs()
    {
        return $this->hasMany(OmsConfigs::class, 'master_config_id', 'id');
    }
    
}
