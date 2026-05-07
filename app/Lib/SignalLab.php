<?php

namespace App\Lib;

use Carbon\Carbon;
use App\Models\User;
use App\Models\SignalHistory;

class SignalLab{

    public static function send($signal, $update = false){

        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $packagesId = $signal->package_id;
        $users = User::whereIn('package_id', $packagesId)->where('validity','>=',now())->with('package')->get();

        $signal->send_signal_at = Carbon::now();
        $signal->save();

        foreach($users as $user){

            if(!$update){
                $signalHistory = new SignalHistory();
                $signalHistory->user_id = $user->id;
                $signalHistory->signal_id = $signal->id;
                $signalHistory->save();
            }

            // $redirectUrl = route('user.signals', ['search'=>$signal->name]);

            // notify($user, 'SIGNAL_NOTIFICATION', [
            //     'package'=> $user->package->name,
            //     'validity'=> showDateTime($user->validity),
            //     'signal_name'=> $signal->name,
            //     'signal_details'=> $signal->signal,
            // ],$signal->send_via,redirectUrl:$redirectUrl);
        }
    }

}
