<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Lib\SignalLab;
use App\Models\Package;
use App\Models\Signal;
use Carbon\Carbon;

class SignalController extends Controller{

    public function all(){
        $pageTitle = 'Manage Signals';
        $signals = $this->signalData();
        return view('admin.signal.all', compact('pageTitle', 'signals'));
    }

    public function sent(){
        $pageTitle = 'Sent Signals';
        $signals = $this->signalData('sent');
        return view('admin.signal.all', compact('pageTitle', 'signals'));
    }

    public function notSent(){
        $pageTitle = 'Not Sent Signals';
        $signals = $this->signalData('notSent');
        return view('admin.signal.all', compact('pageTitle', 'signals'));
    }

    public function addPage(){
        $pageTitle = 'Add New Signal';
        $packages = Package::orderBy('id', 'DESC')->get(['id', 'name']); 
        return view('admin.signal.add', compact('pageTitle', 'packages'));
    }

    public function add(Request $request){ 
       
        $request->validate([
            'name'=> 'required|max:250',
            'signal'=> 'required',
            'set_time'=> 'required|integer|in:0,1',
            'minute' => 'required_if:minute,==,0|integer|gt:0',
            'packages' => 'required|array|exists:packages,id',
            'send_via' => 'required|array|in:'.sendVia(true),
            'status' => 'sometimes|in:on',
        ]);

        $send = Status::SENT;
        $setTime = 0;
        $sendSignalAt = null;

        // When set minute / time
        if($request->set_time == 1){
            $setTime = $request->minute;
            $sendSignalAt = Carbon::now()->addMinute($request->minute);
            $send = Status::NOT_SENT;
        }

        $signal = new Signal();
        $signal->package_id = $request->packages;
        $signal->send_via = $request->send_via;
        $signal->name = $request->name;
        $signal->signal = $request->signal;
        $signal->minute = $setTime;
        $signal->send = $send;
        $signal->status = $request->status ? Status::ENABLE : Status::DISABLE;
        $signal->send_signal_at = $sendSignalAt;
        $signal->save();

        // When send now
        if($request->set_time == 0){
            SignalLab::send($signal);
        }

        $contact = $request->set_time == 0 ? 'sent' : 'added';
        $notify[] = ['success', 'Signal '.$contact.' successfully'];
        return to_route('admin.signal.edit', $signal->id)->withNotify($notify);
    }

    public function edit($id){
        $signal = Signal::findOrFail($id);
        $pageTitle = 'Edit Signal';
        $packages = Package::orderBy('id', 'DESC')->get(['id', 'name']); 
        $sendVia = sendVia();
        return view('admin.signal.edit', compact('pageTitle', 'signal', 'packages', 'sendVia'));
    }

    public function update(Request $request){

        $request->validate([
            'id'=> 'required|integer',
            'name'=> 'required|max:250',
            'signal'=> 'required',
            'set_time'=> 'required|in:0,1',
            'minute' => 'required_if:minute,==,0|integer|gt:0',
            'packages' => 'required|array|exists:packages,id',
            'send_via' => 'required|array|in:'.sendVia(true),
            'resend' => 'sometimes|in:on',
            'status' => 'sometimes|in:on',
        ]);

        $signal = Signal::findOrFail($request->id);
        $setTime = $request->minute ?? 0;

        $signal->package_id = $request->packages;
        $signal->send_via = $request->send_via;
        $signal->name = $request->name;
        $signal->signal = $request->signal;
        $signal->minute = $setTime;
        $signal->status = $request->status ? Status::ENABLE : Status::DISABLE;
        $signal->send = 1;

        // When send now
        if($request->set_time == 0 && !$signal->send){
            SignalLab::send($signal);
        }elseif($request->resend && $signal->send){
            SignalLab::send($signal, true);
        }

        // When set minute / time
        if($request->set_time == 1 && !$signal->send){
            $signal->send_signal_at = Carbon::now()->addMinute($request->minute);
        }

        $signal->save();

        $contact = $request->set_time == 0 ? 'sent' : 'updated';
        $notify[] = ['success', 'Signal '.$contact.' successfully'];
        return back()->withNotify($notify);
    }

    public function delete(Request $request){

        $request->validate([
            'id'=> 'required|required',
        ]);

        $signal = Signal::findOrFail($request->id);
        $signal->signalLogs->each->delete();
        $signal->delete();

        $notify[] = ['success', 'Signal deleted Successfully'];
        return back()->withNotify($notify);
    }

    protected function signalData($scope = null){

        if($scope){
            $signals = Signal::$scope();
        }else{
            $signals = Signal::query();
        }
        return $signals->orderBy('id', 'DESC')->paginate(getPaginate());
    }

}
