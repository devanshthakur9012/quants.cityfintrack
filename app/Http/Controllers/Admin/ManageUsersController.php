<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\NotificationLog;
use App\Models\SignalHistory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Constants\Status;
use App\Models\UserEnquiry;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class ManageUsersController extends Controller
{ 
    public function addUser()
    {
        $pageTitle = 'Add User';
        $roles = Role::where('name', '!=', 'admin')->pluck('name', 'id');
        $traders = User::role('trader')->get(['id', 'firstname', 'lastname']);
        return view('admin.users.add-user', compact('pageTitle', 'roles', 'traders'));
    }

    // public function storeUser(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'firstname' => 'required|string|max:255',
    //             'lastname' => 'required|string|max:255',
    //             'email' => 'required|email|unique:users',
    //             'password' => 'required|min:6|confirmed',
    //             'mobile' => 'required|numeric|unique:users',
    //             'role' => 'required|exists:roles,id',
    //         ]);

    //         $userCode = 'CPE' . mt_rand(1000, 9999);
    //         $user = User::create([
    //             'firstname' => $request->firstname,
    //             'lastname' => $request->lastname,
    //             'email' => $request->email,
    //             'country_code' => "IN",
    //             'user_code' => $userCode,
    //             'username' => $userCode,
    //             'mobile' => $request->mobile,
    //             'password' => Hash::make($request->password),
    //             'status' => 1,
    //             'ev' => 1,
    //             'sv' => 1,
    //         ]);

    //         // Assign role
    //         $role = Role::findById($request->role);
    //         $user->assignRole($role);

    //         if ($request->role == Role::where('name', 'investor')->first()->id && $request->trader_id) {
    //             \App\Models\UserParentLink::create([
    //                 'user_id' => $user->id,
    //                 'parent_id' => $request->trader_id,
    //             ]);
    //         }

    //         $notify[] = ['success','User created and role assigned successfully.'];
    //         return redirect()->route('admin.users.all')->withNotify($notify);
    //     } catch (\Throwable $th) {
    //         $notify[] = ['error','Error : '.$th->getMessage()];
    //         return back()->withNotify($notify);
    //     }
    // }
    
    public function storeUser(Request $request)
    {
        try {
            $request->validate([
                'firstname' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6|confirmed',
                'mobile' => 'required|numeric|unique:users',
            ]);

            $userCode = 'CPE' . mt_rand(1000, 9999);
            $user = User::create([
                'firstname'    => $request->firstname,
                'lastname'     => $request->lastname,
                'email'        => $request->email,
                'country_code' => "IN",
                'user_code'    => $userCode,
                'username'     => $userCode,
                'mobile'       => $request->mobile,
                'password'     => Hash::make($request->password),
                'status'       => 1,
                'ev'           => 1,
                'sv'           => 1,
            ]);

            // Always assign trader role by default
            $user->assignRole(Role::where('name', 'trader')->first());

            if ($request->trader_id) {
                \App\Models\UserParentLink::create([
                    'user_id'   => $user->id,
                    'parent_id' => $request->trader_id,
                ]);
            }

            $notify[] = ['success', 'User created and role assigned successfully.'];
            return redirect()->route('admin.users.all')->withNotify($notify);

        } catch (\Throwable $th) {
            $notify[] = ['error', 'Error: ' . $th->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function allUsers()
    {
        $pageTitle = 'All Users';
        $users = $this->userData();
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function activeUsers()
    {
        $pageTitle = 'Active Users';
        $users = $this->userData('active');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function bannedUsers()
    {
        $pageTitle = 'Banned Users';
        $users = $this->userData('banned');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailUnverifiedUsers()
    {
        $pageTitle = 'Email Unverified Users';
        $users = $this->userData('emailUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailVerifiedUsers()
    {
        $pageTitle = 'Email Verified Users';
        $users = $this->userData('emailVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    public function mobileUnverifiedUsers()
    {
        $pageTitle = 'Mobile Unverified Users';
        $users = $this->userData('mobileUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    public function mobileVerifiedUsers()
    {
        $pageTitle = 'Mobile Verified Users';
        $users = $this->userData('mobileVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    public function usersWithBalance()
    {
        $pageTitle = 'Users with Balance';
        $users = $this->userData('withBalance');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }


    protected function userData($scope = null, $userId = null){
        if ($scope) {
            $users = User::$scope();
        }elseif($userId){
            $users = User::where('ref_by', $userId);
        }else{
            $users = User::query();
        }
        return $users->searchable(['username','email'])->with('package')->orderBy('id','desc')->paginate(getPaginate());
    }


    public function detail($id)
    {
        $user = User::findOrFail($id);
        $pageTitle = 'User Detail - '.$user->username;

        $totalDeposit = Deposit::where('user_id',$user->id)->where('status',Status::PAYMENT_SUCCESS)->sum('amount');
        $totalTransaction = Transaction::where('user_id',$user->id)->count();
        $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $totalSignal = SignalHistory::where('user_id', $user->id)->count();
        return view('admin.users.detail', compact('pageTitle', 'user','totalDeposit','totalTransaction','countries', 'totalSignal'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $countryData = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryArray   = (array)$countryData;
        $countries      = implode(',', array_keys($countryArray));

        $countryCode    = $request->country;
        $country        = $countryData->$countryCode->country;
        $dialCode       = $countryData->$countryCode->dial_code;

        $request->validate([
            'firstname' => 'required|string|max:40',
            'lastname' => 'required|string|max:40',
            'email' => 'required|email|string|max:40|unique:users,email,' . $user->id,
            'mobile' => 'required|string|max:40|unique:users,mobile,' . $user->id,
            'country' => 'required|in:'.$countries,
        ]);

        $user->telegram_username = $request->telegram_username;

        $user->mobile = $dialCode.$request->mobile;
        $user->country_code = $countryCode;
        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;
        $user->email = $request->email;
        $user->address = [
                            'address' => $request->address,
                            'city' => $request->city,
                            'state' => $request->state,
                            'zip' => $request->zip,
                            'country' => @$country,
                        ];
        $user->ev = $request->ev ? Status::VERIFIED : Status::UNVERIFIED;
        $user->sv = $request->sv ? Status::VERIFIED : Status::UNVERIFIED;
        $user->ts = $request->ts ? Status::ENABLE : Status::DISABLE;
        $user->save();

        $notify[] = ['success', 'User details updated successfully'];
        return back()->withNotify($notify);
    }

    public function addSubBalance(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'act' => 'required|in:add,sub',
            'remark' => 'required|string|max:255',
        ]);

        $user = User::findOrFail($id);
        $amount = $request->amount;
        $trx = getTrx();

        $transaction = new Transaction();

        if ($request->act == 'add') {
            $user->balance += $amount;

            $transaction->trx_type = '+';
            $transaction->remark = 'balance_add';

            $notifyTemplate = 'BAL_ADD';

            $notify[] = ['success', gs('cur_sym') . $amount . ' added successfully'];

        } else {
            if ($amount > $user->balance) {
                $notify[] = ['error', $user->username . ' doesn\'t have sufficient balance.'];
                return back()->withNotify($notify);
            }

            $user->balance -= $amount;

            $transaction->trx_type = '-';
            $transaction->remark = 'balance_subtract';

            $notifyTemplate = 'BAL_SUB';
            $notify[] = ['success', gs('cur_sym') . $amount . ' subtracted successfully'];
        }

        $user->save();

        $transaction->user_id = $user->id;
        $transaction->amount = $amount;
        $transaction->post_balance = $user->balance;
        $transaction->charge = 0;
        $transaction->trx =  $trx;
        $transaction->details = $request->remark;
        $transaction->save();

        notify($user, $notifyTemplate, [
            'trx' => $trx,
            'amount' => showAmount($amount),
            'remark' => $request->remark,
            'post_balance' => showAmount($user->balance)
        ]);

        return back()->withNotify($notify);
    }

    public function login($id){
        Auth::loginUsingId($id);
        return to_route('user.home');
    }

    public function status(Request $request,$id)
    {
        $user = User::findOrFail($id);
        if ($user->status == Status::USER_ACTIVE) {
            $request->validate([
                'reason'=>'required|string|max:255'
            ]);
            $user->status = Status::USER_BAN;
            $user->ban_reason = $request->reason;
            $notify[] = ['success','User banned successfully'];
        }else{
            $user->status = Status::USER_ACTIVE;
            $user->ban_reason = null;
            $notify[] = ['success','User unbanned successfully'];
        }
        $user->save();
        return back()->withNotify($notify);

    }


    public function showNotificationSingleForm($id)
    {
        $user = User::findOrFail($id);
        $general = gs();
        if (!$general->en && !$general->sn) {
            $notify[] = ['warning','Notification options are disabled currently'];
            return to_route('admin.users.detail',$user->id)->withNotify($notify);
        }
        $pageTitle = 'Send Notification to ' . $user->username;
        return view('admin.users.notification_single', compact('pageTitle', 'user'));
    }

    public function sendNotificationSingle(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
            'subject' => 'required|string',
        ]);

        $user = User::findOrFail($id);
        notify($user,'DEFAULT',[
            'subject'=>$request->subject,
            'message'=>$request->message,
        ]);
        $notify[] = ['success', 'Notification sent successfully'];
        return back()->withNotify($notify);
    }

    public function showNotificationAllForm()
    {
        $general = gs();
        if (!$general->en && !$general->sn) {
            $notify[] = ['warning','Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }
        $notifyToUser = User::notifyToUser();
        $users = User::active()->count();
        $pageTitle = 'Notification to Verified Users';
        return view('admin.users.notification_all', compact('pageTitle','users','notifyToUser'));
    }

    public function sendNotificationAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message'                      => 'required',
            'subject'                      => 'required',
            'start'                        => 'required',
            'batch'                        => 'required',
            'being_sent_to'                => 'required',
            'user'                         => 'required_if:being_sent_to,selectedUsers',
            'number_of_top_deposited_user' => 'required_if:being_sent_to,topDepositedUsers|integer|gte:0',
            'number_of_days'               => 'required_if:being_sent_to,notLoginUsers|integer|gte:0',
        ], [
            'number_of_days.required_if'               => "Number of days field is required",
            'number_of_top_deposited_user.required_if' => "Number of top deposited user field is required",
        ]);

        if ($validator->fails()) return response()->json(['error' => $validator->errors()->all()]);
        $scope = $request->being_sent_to;
        $users = User::oldest()->active()->$scope()->skip($request->start)->limit($request->batch)->get();
        foreach ($users as $user) {
            notify($user, 'DEFAULT', [
                'subject' => $request->subject,
                'message' => $request->message,
            ]);
        }

        return response()->json([
            'total_sent' => $users->count(),
        ]);
    }

    public function list()
    {
        $query = User::active();

        if (request()->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', '%' . request()->search . '%')->orWhere('username', 'like', '%' . request()->search . '%');
            });
        }
        $users = $query->orderBy('id', 'desc')->paginate(getPaginate());
        return response()->json([
            'success' => true,
            'users'   => $users,
            'more'    => $users->hasMorePages()
        ]);
    }

    public function notificationLog($id){
        $user = User::findOrFail($id);
        $pageTitle = 'Notifications Sent to '.$user->username;
        $logs = NotificationLog::where('user_id',$id)->with('user')->orderBy('id','desc')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle','logs','user'));
    }

    public function signalLog($id){
        $user = User::findOrFail($id);
        $pageTitle = 'Signal History';
        $signals = SignalHistory::where('user_id', $user->id)->with('user', 'signal')->orderBy('id', 'DESC')->paginate(getPaginate());
        return view('admin.reports.signals', compact('pageTitle', 'signals', 'user'));
    }

    public function referrals($id){
        $getUser = User::findOrFail($id);
        $pageTitle = 'Referrals';
        $users = $this->userData(null, $id);
        return view('admin.users.list', compact('pageTitle', 'users', 'getUser'));
    }

    public function updateValidity(Request $request){

        $request->validate([
            'user_id'=> 'required',
            'validity_day'=> 'required|integer|min:1',
        ]);

        $user = User::where('package_id', '!=', 0)->findOrFail($request->user_id);

        if($request->day_type){
            $newValidity = Carbon::parse($user->validity)->addDays($request->validity_day);
        }else{
            $newValidity = Carbon::parse($user->validity)->subDay($request->validity_day);
        }

        $user->validity = $newValidity;
        $user->save();

        $notify[] = ['success', 'Product validity updated successfully'];
        return back()->withNotify($notify);
    }

    public function getuserEnquiry(){
        $pageTitle = 'Users Enquiry';
        $enquiry = UserEnquiry::with('user','package')->orderBy('id','desc')->paginate(getPaginate());
        return view('admin.users.user-enquiry', compact('pageTitle','enquiry'));
    }

}
