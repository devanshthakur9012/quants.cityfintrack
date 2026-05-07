<?php

namespace App\Http\Controllers;

use App\Lib\SignalLab;
use App\Models\Signal;
use Carbon\Carbon;
use App\Models\CronJob;
use App\Models\CronJobLog;
use App\Lib\CurlRequest;

class CronController extends Controller{

    public function cron(){ 
        
        $general = gs();
        $general->last_cron = now();
        $general->save();

        $crons = CronJob::with('schedule');
        if (request()->alias) {
            $crons->where('alias', request()->alias);
        } else {
            $crons->where('next_run', '<', now())->where('is_running', 1);
        }

        $crons = $crons->get();

        foreach ($crons as $cron) {
            $cronLog              = new CronJobLog();
            $cronLog->cron_job_id = $cron->id;
            $cronLog->start_at    = now();

            if ($cron->is_default) {
                $controller = new $cron->action[0];
                try {
                    $method = $cron->action[1];
                    $controller->$method();
                } catch (\Exception $e) {
                    $cronLog->error = $e->getMessage();
                }
            } else {
                try {
                    CurlRequest::curlContent($cron->url);
                } catch (\Exception $e) {
                    $cronLog->error = $e->getMessage();
                }
            }

            $cron->last_run = now();
            $cron->next_run = now()->addSeconds($cron->schedule->interval);
            $cron->save();

            $cronLog->end_at = $cron->last_run;

            $startTime         = Carbon::parse($cronLog->start_at);
            $endTime           = Carbon::parse($cronLog->end_at);
            $diffInSeconds     = $startTime->diffInSeconds($endTime);
            $cronLog->duration = $diffInSeconds;
            $cronLog->save();
        }

        if (request()->alias) {
            $notify[] = ['success', keyToTitle(request()->alias) . ' executed successfully'];
            return back()->withNotify($notify);
        }

    }

    public function sendSignal(){
        $signals = Signal::active()->notSent()->where('send_signal_at', '<=', Carbon::now())->limit(50)->get();

        foreach($signals as $signal){ 
            SignalLab::send($signal); 
            $signal->send = 1;
            $signal->save();
        }
    }

    public function all(){

        $general = gs();
        $general->last_cron = now();
        $general->save();

        $this->sendSignal(); 

        $notify[] = ['success', 'Manually cron run successfully'];
        return back()->withNotify($notify);
    }

}


