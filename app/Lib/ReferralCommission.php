<?php

namespace App\Lib;


use App\Models\User;
use App\Models\Referral;
use App\Models\Transaction;

class ReferralCommission{

    public static function calculate($userId, $amount){

        set_time_limit(0); 
        ini_set('max_execution_time', 0);
        
        $i = 1;
        $usr = $userId;

        $user = User::find($userId);
        $level = Referral::count();
       
        while($usr != '' || $usr != '0' || $i < $level){

            $me = User::find($usr);
            $refer = User::find($me->ref_by);
             
            if($refer == ''){
                break;
            }

            $referralSetting = Referral::where('level', $i)->first();
              
            if(!$referralSetting){
                break;
            }

            if($refer->package_id == 0){
                $usr = $refer->id;
                $i++;
                continue;
            }

            $commission = ($amount * $referralSetting->percent) / 100;
                
            $refer->balance += $commission;
            $refer->save();
    
            $transaction = new Transaction();
            $transaction->user_id = $refer->id;
            $transaction->amount = getAmount($commission);
            $transaction->commission_percent = $referralSetting->percent;
            $transaction->post_balance = $refer->balance;
            $transaction->charge = 0;
            $transaction->trx_type = '+';
            $transaction->details =' Level '.$i.' referral commission from ' . $user->username;
            $transaction->trx = getTrx();
            $transaction->remark = 'referral_commission';
            $transaction->save();
    
            notify($refer, 'REFERRAL_COMMISSION', [
                'trx' => $transaction->trx,
                'post_balance' => showAmount($refer->balance),
                'commission' => showAmount($commission),
                'level' => $i . ' level Referral Commission'
            ]);
    
            $usr = $refer->id;
            $i++;
        }

        return 0;
    }

}
