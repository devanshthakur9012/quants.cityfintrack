<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    protected $casts = ['mail_config' => 'object','sms_config' => 'object','global_shortcodes' => 'object', 'firebase_config'=>'object', 'telegram_config'=>'object'];

    protected $hidden = ['email_template','mail_config','sms_config','system_info'];

    public function scopeSiteName($query, $pageTitle){
        $pageTitle = empty($pageTitle) ? '' : ' - ' . $pageTitle;
        return $this->site_name . $pageTitle;
    }

    protected static function boot() {
        parent::boot();
        static::saved(function(){
            \Cache::forget('GeneralSetting');
        });
    }

    // Show deposit commission status
    public function showComStatus(): Attribute{
        return new Attribute(
            get:function(){
                if($this->deposit_commission == 1){
                    $html = "<span class='text--small badge font-weight-normal badge--success'>".trans('Enabled')."</span>";
                }else{
                    $html  = "<span class='text--small badge font-weight-normal badge--warning'>".trans('Disabled')."</span>";
                }
                return $html;
            },
        );
    }

}
