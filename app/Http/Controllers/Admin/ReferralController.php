<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Models\Referral;
use Illuminate\Http\Request;

class ReferralController extends Controller{

    public function setting(){
        $pageTitle = 'Referral Setting';
        $referrals = Referral::get();
        return view('admin.referral.setting', compact('pageTitle', 'referrals'));
    }

    public function settingUpdate(Request $request){
      
        $request->validate([
            'level' => 'required|array',
            'percent' => 'required|array',
            'percent.*' => 'required|numeric|min:1'
        ]);
        
        Referral::truncate();
          
        for($index = 0; $index < count($request->level); $index++){
            $referral = new Referral();
            $referral->level = $request->level[$index];
            $referral->percent = $request->percent[$index]; 
            $referral->save(); 
        }

        $notify[] = ['success', 'Referral setting updated successfully'];
        return back()->withNotify($notify);
    }

    public function settingStatus(){

        $general = GeneralSetting::first();

        if($general->deposit_commission == 1){
            $general->deposit_commission = 0;
            $message = 'Deposit commission system disabled successfully';
        }else{
            $general->deposit_commission = 1;
            $message = 'Deposit commission system enabled successfully';
        }

        $general->save();

        $notify[] = ['success', $message];
        return back()->withNotify($notify);
    }

}
