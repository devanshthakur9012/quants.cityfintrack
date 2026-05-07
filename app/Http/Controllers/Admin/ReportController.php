<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\SignalHistory;
use App\Models\Transaction;
use App\Models\UserLogin;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function signal(Request $request){ 
        $pageTitle = 'Signal Logs' ;
        $signals = SignalHistory::searchable(['signal:name','user:username'])->dateFilter()->with('user', 'signal')->orderBy('id', 'DESC')->paginate(getPaginate());
        return view('admin.reports.signals', compact('pageTitle', 'signals'));
    }

    public function transaction(Request $request)
    {
        $pageTitle = 'Transaction Logs';

        $remarks = Transaction::distinct('remark')->orderBy('remark')->get('remark');

        $transactions = Transaction::searchable(['user:username'])->filter(['trx_type','remark'])->dateFilter()->with('user')->orderBy('id','desc')->paginate(getPaginate());

        return view('admin.reports.transactions', compact('pageTitle', 'transactions','remarks'));
    }

    public function loginHistory(Request $request)
    {
        $pageTitle = 'User Login History';
        $loginLogs = UserLogin::searchable(['user:username'])->orderBy('id','desc')->with('user')->paginate(getPaginate());
        return view('admin.reports.logins', compact('pageTitle', 'loginLogs'));
    }

    public function loginIpHistory($ip)
    {
        $pageTitle = 'Login by - ' . $ip;
        $loginLogs = UserLogin::where('user_ip',$ip)->orderBy('id','desc')->with('user')->paginate(getPaginate());
        return view('admin.reports.logins', compact('pageTitle', 'loginLogs','ip'));

    }

    public function notificationHistory(Request $request){
        $pageTitle = 'Notification History';
        $logs = NotificationLog::orderBy('id','desc')->searchable(['user:username'])->with('user')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle','logs'));
    }

    public function emailDetails($id){
        $pageTitle = 'Email Details';
        $email = NotificationLog::findOrFail($id);
        return view('admin.reports.email_details', compact('pageTitle','email'));
    }
}
