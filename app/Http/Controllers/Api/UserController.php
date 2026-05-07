<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\DeviceToken;
use App\Models\Form;
use App\Models\GeneralSetting;
use App\Models\Package;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use App\Models\SignalHistory;
use App\Models\User;
use Carbon\Carbon;

class UserController extends Controller
{
    public function dashboard(){

        $notify[] = 'Dashboard';
        $user = auth()->user()->load(['package']);
        $referralLink = route('home', ['reference'=>$user->username]);

        $totalTrx = Transaction::where('user_id', $user->id)->count();
        $totalSignal = SignalHistory::where('user_id', $user->id)->count();
        $totalDeposit = Deposit::where('user_id', $user->id)->where('status', 1)->sum('amount');
        $latestSignals = SignalHistory::where('user_id', $user->id)->with('signal')->orderBy('id', 'DESC')->take(10)->get();

        return response()->json([
            'remark'=>'dashboard',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'user'=>$user,
                'referral_link'=>$referralLink,
                'total_trx'=>$totalTrx,
                'total_signal'=>$totalSignal,
                'total_referral'=>$user->referrals->count(),
                'total_deposit'=>$totalDeposit,
                'latest_signals'=>$latestSignals,
            ]
        ]);
    }

    public function userDataSubmit(Request $request)
    {
        $user = auth()->user();
        if ($user->profile_complete == 1) {
            $notify[] = 'You\'ve already completed your profile';
            return response()->json([
                'remark'=>'already_completed',
                'status'=>'error',
                'message'=>['error'=>$notify],
            ]);
        }
        $validator = Validator::make($request->all(), [
            'firstname'=>'required',
            'lastname'=>'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }


        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;
        $user->address = [
            'country'=>@$user->address->country,
            'address'=>$request->address,
            'state'=>$request->state,
            'zip'=>$request->zip,
            'city'=>$request->city,
        ];
        $user->profile_complete = 1;
        $user->save();

        $notify[] = 'Profile completed successfully';
        return response()->json([
            'remark'=>'profile_completed',
            'status'=>'success',
            'message'=>['success'=>$notify],
        ]);
    }

    public function kycForm()
    {
        if (auth()->user()->kv == 2) {
            $notify[] = 'Your KYC is under review';
            return response()->json([
                'remark'=>'under_review',
                'status'=>'error',
                'message'=>['error'=>$notify],
            ]);
        }
        if (auth()->user()->kv == 1) {
            $notify[] = 'You are already KYC verified';
            return response()->json([
                'remark'=>'already_verified',
                'status'=>'error',
                'message'=>['error'=>$notify],
            ]);
        }
        $form = Form::where('act','kyc')->first();
        $notify[] = 'KYC field is below';
        return response()->json([
            'remark'=>'kyc_form',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'form'=>$form->form_data
            ]
        ]);
    }

    public function kycSubmit(Request $request)
    {
        $form = Form::where('act','kyc')->first();
        $formData = $form->form_data;
        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);

        $validator = Validator::make($request->all(), $validationRule);

        if ($validator->fails()) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }

        $userData = $formProcessor->processFormData($request, $formData);
        $user = auth()->user();
        $user->kyc_data = $userData;
        $user->kv = 2;
        $user->save();

        $notify[] = 'KYC data submitted successfully';
        return response()->json([
            'remark'=>'kyc_submitted',
            'status'=>'success',
            'message'=>['success'=>$notify],
        ]);

    }

    public function depositHistory(Request $request)
    {
        $deposits = auth()->user()->deposits();
        if ($request->search) {
            $deposits = $deposits->where('trx',$request->search);
        }
        $deposits = $deposits->with(['gateway'])->orderBy('id','desc')->paginate(getPaginate());
        $notify[] = 'Deposit data';
        return response()->json([
            'remark'=>'deposits',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'deposits'=>$deposits
            ]
        ]);
    }

    public function transactions(Request $request)
    {
        // dd('transactions route');
        $remarks = Transaction::where('remark', '!=', null)->distinct('remark')->get('remark');
        $transactions = Transaction::where('user_id',auth()->id());

        if ($request->search) {
            $transactions = $transactions->where('trx',$request->search);
        }

        if ($request->type) {
            $type = $request->type == 'plus' ? '+' : '-';
            $transactions = $transactions->where('trx_type',$type);
        }

        if ($request->remark) {
            $transactions = $transactions->where('remark',$request->remark);
        }

        $transactions = $transactions->orderBy('id','desc')->paginate(getPaginate());
        $notify[] = 'Transactions data';

        return response()->json([
            'remark'=>'transactions',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'transactions'=>$transactions,
                'remarks'=>$remarks,
            ]
        ]);
    }
    public function submitProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname'=>'required',
            'lastname'=>'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }

        $user = auth()->user();

        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;
        $user->telegram_username = $request->telegram_username;
        $user->address = [
            'country'=>@$user->address->country,
            'address'=>$request->address,
            'state'=>$request->state,
            'zip'=>$request->zip,
            'city'=>$request->city,
        ];
        $user->save();

        $notify[] = 'Profile updated successfully';
        return response()->json([
            'remark'=>'profile_updated',
            'status'=>'success',
            'message'=>['success'=>$notify],
        ]);
    }

    public function submitPassword(Request $request)
    {
        $passwordValidation = Password::min(6);
        $general = GeneralSetting::first();
        if ($general->secure_password) {
            $passwordValidation = $passwordValidation->mixedCase()->numbers()->symbols()->uncompromised();
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => ['required','confirmed',$passwordValidation]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }

        $user = auth()->user();
        if (Hash::check($request->current_password, $user->password)) {
            $password = Hash::make($request->password);
            $user->password = $password;
            $user->save();
            $notify[] = 'Password changed successfully';
            return response()->json([
                'remark'=>'password_changed',
                'status'=>'success',
                'message'=>['success'=>$notify],
            ]);
        } else {
            $notify[] = 'The password doesn\'t match!';
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$notify],
            ]);
        }
    }

    public function packages(){

        $packages = Package::active()->paginate(getPaginate());
        $notify[] = 'Packages';

        return response()->json([
            'remark'=>'packages',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'balance'=>auth()->user()->balance,
                'packages'=>$packages
            ]
        ]);
    }

    public function purchasePackage(Request $request){

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }

        $package = Package::active()->find($request->id);

        if(!$package){
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>["Product not found"]],
            ]);
        }

        $user = auth()->user();

        if($package->price > $user->balance){
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>["Sorry, Insufficient balance"]],
            ]);
        }

        $user->package_id = $package->id;
        $user->validity = Carbon::now()->addDay($package->validity);
        $user->balance -= $package->price;
        $user->save();

        $transaction = new Transaction();
        $transaction->user_id = $user->id;
        $transaction->amount = $package->price;
        $transaction->post_balance = $user->balance;
        $transaction->charge = 0;
        $transaction->trx_type = '-';
        $transaction->details = 'Purchased ' .$package->name;
        $transaction->trx =  getTrx();
        $transaction->remark = 'purchase';
        $transaction->save();

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = $user->id;
        $adminNotification->title = $user->username.' has purchased '.$package->name;
        $adminNotification->click_url = urlPath('admin.report.transaction', ['search'=>$transaction->trx]);
        $adminNotification->save();

        notify($user, 'PURCHASE_COMPLETE', [
            'trx' => $transaction->trx,
            'package' => $package->name,
            'amount' => showAmount($package->price, 2),
            'post_balance' => showAmount($user->balance, 2),
            'validity' => $package->validity.' Days',
            'expired_validity' => showDateTime($user->validity),
            'purchased_at' => showDateTime($transaction->created_at),
        ]);

        $notify[] = 'You have purchased '.$package->name.' successfully';
        return response()->json([
            'remark'=>'purchase_package',
            'status'=>'success',
            'message'=>['success'=>$notify],
        ]);
    }

    public function renewPackage(Request $request){

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }

        $package = Package::active()->find($request->id);

        if(!$package){
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>["Product not found"]],
            ]);
        }

        $user = auth()->user();

        if($user->package_id != $package->id){
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>["There is no Product to renew"]],
            ]);
        }

        if($package->price > $user->balance){
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>["Insufficient balance"]],
            ]);
        }

        $user->validity = Carbon::parse($user->validity)->addDay($package->validity);
        $user->balance -= $package->price;
        $user->save();

        $transaction = new Transaction();
        $transaction->user_id = $user->id;
        $transaction->amount = $package->price;
        $transaction->post_balance = $user->balance;
        $transaction->charge = 0;
        $transaction->trx_type = '-';
        $transaction->details = 'Renewed ' .$package->name;
        $transaction->trx =  getTrx();
        $transaction->remark =  'renew';
        $transaction->save();

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = $user->id;
        $adminNotification->title = $user->username.' has renewed '.$package->name;
        $adminNotification->click_url = urlPath('admin.report.transaction', ['search'=>$transaction->trx]);
        $adminNotification->save();

        notify($user, 'RENEW_COMPLETE', [
            'trx' => $transaction->trx,
            'package' => $package->name,
            'amount' => showAmount($package->price, 2),
            'post_balance' => showAmount($user->balance, 2),
            'validity' => $package->validity.' Days',
            'expired_validity' => showDateTime($user->validity),
            'renew_at' => showDateTime($transaction->created_at),
        ]);

        $notify[] = 'You have renewed '.$package->name.' successfully';
        return response()->json([
            'remark'=>'renew_package',
            'status'=>'success',
            'message'=>['success'=>$notify],
        ]);
    }

    public function signals(Request $request){

        $signals = SignalHistory::where('user_id', auth()->user()->id);
        $notify[] = 'Signals';

        if ($request->search) {
            $signals = $signals->whereHas('signal', function($signal) use ($request){
                $signal->where('name', 'LIKE', '%'.$request->search.'%');
            });
        }

        $signals = $signals->orderBy('id','desc')->with('signal')->paginate(getPaginate());

        return response()->json([
            'remark'=>'signals',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'signals'=>$signals
            ]
        ]);
    }

    public function referrals(Request $request){

        $notify[] = 'Referrals';
        $user = auth()->user();
        $referralLink = route('home', ['reference'=>$user->username]);

        $referrals = User::where('ref_by', $user->id);

        if($request->search){
            $referrals = $referrals->where('username', 'LIKE', '%'.$request->search.'%');
        }

        $referrals = $referrals->orderBy('id', 'DESC')->paginate(getPaginate());

        return response()->json([
            'remark'=>'referrals',
            'status'=>'success',
            'message'=>['success'=>$notify],
            'data'=>[
                'referral_link'=>$referralLink,
                'referrals'=>$referrals
            ]
        ]);
    }

    public function getDeviceToken(Request $request){

        $validator = Validator::make($request->all(), [
            'token'=> 'required'
        ]);

        if($validator->fails()){
            return response()->json([
                'remark'=>'validation_error',
                'status'=>'error',
                'message'=>['error'=>$validator->errors()->all()],
            ]);
        }

        $deviceToken = DeviceToken::where('token', $request->token)->first();

        if($deviceToken){
            $notify[] = 'Already exists';
            return response()->json([
                'remark'=>'get_device_token',
                'status'=>'success',
                'message'=>['success'=>$notify],
            ]);
        }

        $deviceToken = new DeviceToken();
        $deviceToken->user_id = auth()->user()->id;
        $deviceToken->token = $request->token;
        $deviceToken->is_app = 1;
        $deviceToken->save();

        $notify[] = 'Token save successfully';
        return response()->json([
            'remark'=>'get_device_token',
            'status'=>'success',
            'message'=>['success'=>$notify],
        ]);
    }

}
