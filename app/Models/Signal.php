<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Signal extends Model{

    use HasFactory;

    protected $casts = ['send_via'=>'array', 'package_id'=>'array'];

    public function showSendStatus(): Attribute{
        return new Attribute(
            get:function(){
                if($this->send == Status::SENT){
                    $html = "<span class='text--small badge font-weight-normal badge--success'>".trans('Send')."</span>";
                }else{
                    $html  = "<span class='text--small badge font-weight-normal badge--warning'>".trans('Not Send')."</span>";
                }
                return $html;
            },
        );
    }

    public function showStatus(): Attribute{
        return new Attribute(
            get:function(){
                if($this->status == Status::ENABLE){
                    $html = "<span class='text--small badge font-weight-normal badge--success'>".trans('Enabled')."</span>";
                }else{
                    $html  = "<span class='text--small badge font-weight-normal badge--warning'>".trans('Disabled')."</span>";
                }
                return $html;
            },
        );
    }

    public function signalLogs(){
        return $this->hasMany(SignalHistory::class);
    }

    public function scopeSent($query){
        return $query->where('send', Status::SENT);
    }

    public function scopeNotSent($query){
        return $query->where('send', Status::NOT_SENT);
    }

    public function scopeActive($query){
        return $query->where('status', Status::ENABLE);
    }

}
