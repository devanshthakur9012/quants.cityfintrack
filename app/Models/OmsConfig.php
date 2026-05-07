<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmsConfig extends Model
{
    use HasFactory;

    public function broker(){
        return $this->belongsTo(BrokerApi::class,'broker_api_id','id');
    }

    public function masterConfig()
    {
        return $this->belongsTo(OmsConfigMaster::class, 'master_config_id', 'id');
    }
    
}
