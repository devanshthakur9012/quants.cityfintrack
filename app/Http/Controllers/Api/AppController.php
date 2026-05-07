<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Frontend;
use App\Models\GeneralSetting;

class AppController extends Controller{

    public function policyPages(){
        $policyPages = Frontend::where('data_keys', 'policy_pages.element')->get();
        $notify[] = 'Policy Pages';

        return response()->json([
            'remark'=>'policy_pages',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'policy_pages'=>$policyPages,
                'user'=>auth()->user()
            ]
        ]);
    }
    
    public function generalSetting(){
        $general = GeneralSetting::first();
        $notify[] = 'General setting data';

        return response()->json([
            'remark'=>'general_setting',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'general_setting'=>$general,
            ],
        ]);
    }
    
    public function getCountries(){ 
        $allCountry = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $notify[] = 'General setting data';

        foreach($allCountry as $k => $country){
            $countries[] = [
                'country'=>$country->country,
                'dial_code'=>$country->dial_code,
                'country_code'=>$k,
            ];
        }

        return response()->json([
            'remark'=>'country_data',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'countries'=>$countries,
            ],
        ]);
    }

}
