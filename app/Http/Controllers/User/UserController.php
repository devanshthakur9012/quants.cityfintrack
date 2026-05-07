<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Lib\GoogleAuthenticator;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\FOPortfolios;
use App\Models\Form;
use App\Models\GlobalStockPortfolio;
use App\Models\Ledger;
use App\Models\MetalsPortfolio;
use App\Models\Package;
use App\Models\PortfolioTopGainer;
use App\Models\PortfolioTopLoser;
use App\Models\Referral;
use App\Models\SignalHistory;
use App\Models\StockPortfolio;
use App\Models\ThematicPortfolio;
use App\Models\BrokerApi;
use App\Models\WatchList;
use App\Models\Transaction;
use App\Models\AngleOhlcData;
use App\Models\AngleHistoricalApi;
use App\Models\WatchTradePosition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\OmsConfig;
use App\Models\OrderBook;
use App\Models\StoreMarketData;
use App\Models\ZerodhaInstrument;
use App\Helpers\KiteConnectCls;
use App\Helpers\AngelConnectCls;
use App\Jobs\PlaceOmsOrder;
use App\Models\Strategy;
use App\Traits\AngelApiAuth;
use App\Models\WishlistData;
use App\Models\StengthTb;
use Illuminate\Support\Facades\DB;
use App\Models\OiBuildUp;
use App\Helpers\OiAnalyzer;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    use AngelApiAuth;

    public function home(Request $request)
    {
        $user = auth()->user();
        $pageTitle = 'Dashboard';

        // $StrengthsymbolArr = ['CRUDEOIL','BANKNIFTY','FINNIFTY','NIFTY','MIDCPNIFTY','NATURALGAS'];

        
        $totalTrx = Transaction::where('user_id', $user->id)->count();
        $totalSignal = SignalHistory::where('user_id', $user->id)->count();
        $latestTrx = Transaction::where('user_id', $user->id)->orderBy('id', 'DESC')->limit(10)->get();
        $totalDeposit = Deposit::where('user_id', $user->id)->where('status', Status::PAYMENT_SUCCESS)->sum('amount');
        $portfolioTopGainers = PortfolioTopGainer::all();
        $portfolioTopLosers = PortfolioTopLoser::all();

        $date1 = date("Y-m-01");
        $date2 = date("Y-m-t");
        $stockPortFolioBuyVal = 0;
        $stockPortFolioCurrVal = 0;

        $investGraphArr = [];

        $stockPortFolio =  StockPortfolio::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();

        foreach($stockPortFolio as $v){
            $investGraphArr[$v->buy_date] = [
                'buy_value'=>$v->buy_value,
                'current_value'=>$v->current_value
            ];
            $stockPortFolioBuyVal += $v->buy_value;
            $stockPortFolioCurrVal += $v->current_value;
        }

        $stockPortFolio->buy_value = $stockPortFolioBuyVal;
        $stockPortFolio->current_value = $stockPortFolioCurrVal;

        $globalstockPortFolioBuyVal = 0;
        $globalstockPortFolioCurrVal = 0;


        $globalStockPortFolio =  GlobalStockPortFolio::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();
        foreach($globalStockPortFolio as $v){
            if(isset($investGraphArr[$v->buy_date])){
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value + $investGraphArr[$v->buy_date]['buy_value'],
                    'current_value'=>$v->current_value + $investGraphArr[$v->buy_date]['current_value']
                ];
            }else{
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value,
                    'current_value'=>$v->current_value
                ];
            }            
            $globalstockPortFolioBuyVal += $v->buy_value;
            $globalstockPortFolioCurrVal += $v->current_value;
        }
        $globalStockPortFolio->buy_value = $globalstockPortFolioBuyVal;
        $globalStockPortFolio->current_value = $globalstockPortFolioCurrVal;


        $foglobalstockPortFolioBuyVal = 0;
        $foglobalstockPortFolioCurrVal = 0;


        $foglobalStockPortFolio =  FOPortfolios::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();
        foreach($foglobalStockPortFolio as $v){
            if(isset($investGraphArr[$v->buy_date])){
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value + $investGraphArr[$v->buy_date]['buy_value'],
                    'current_value'=>$v->current_value + $investGraphArr[$v->buy_date]['current_value']
                ];
            }else{
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value,
                    'current_value'=>$v->current_value
                ];
            }            
            $foglobalstockPortFolioBuyVal += $v->buy_value;
            $foglobalstockPortFolioCurrVal += $v->current_value;
        }


        $foglobalStockPortFolio->buy_value = $foglobalstockPortFolioBuyVal;
        $foglobalStockPortFolio->current_value = $foglobalstockPortFolioCurrVal;


        $metalsPortFolioBuyVal = 0;
        $metalsPortFolioCurrVal = 0;


        $metalsPortFolio =  MetalsPortfolio::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();

        foreach($metalsPortFolio as $v){
            if(isset($investGraphArr[$v->buy_date])){
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value + $investGraphArr[$v->buy_date]['buy_value'],
                    'current_value'=>$v->current_value + $investGraphArr[$v->buy_date]['current_value']
                ];
            }else{
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value,
                    'current_value'=>$v->current_value
                ];
            }            
            $metalsPortFolioBuyVal += $v->buy_value;
            $metalsPortFolioCurrVal += $v->current_value;
        }

        $metalsPortFolio->buy_value = $metalsPortFolioBuyVal;
        $metalsPortFolio->current_value = $metalsPortFolioCurrVal;


        $totalInvestedAmount = $stockPortFolio->buy_value + $globalStockPortFolio->buy_value + $foglobalStockPortFolio->buy_value + $metalsPortFolio->buy_value;
        $totalCurrentAmount = $stockPortFolio->current_value + $globalStockPortFolio->current_value + $foglobalStockPortFolio->current_value + $metalsPortFolio->current_value;

        $buyArr = [];
        $currArr = [];
        $datesArr = [];

        if(!empty($investGraphArr)){
           
            $datesArr = array_keys($investGraphArr);
            $datesArr = array_map(function($kk){
                return date("M-Y",strtotime($kk));
            },$datesArr);
            $buyArr = array_column($investGraphArr,'buy_value');
            $currArr = array_column($investGraphArr,'current_value');
        }

        $chrtArr = [
            $stockPortFolio->buy_value,$metalsPortFolio->buy_value,$globalStockPortFolio->buy_value,$foglobalStockPortFolio->buy_value
        ];

        $symbolArray = [];
        foreach ($portfolioTopGainers as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }

        $symbolArray2 = [];
        foreach ($portfolioTopLosers as $val) {
           array_push($symbolArray2 , $val['stock_name'].".NS");
        }     
        
        $fullUrl = $request->fullUrl();

        $StrengthsymbolArr = allTradeSymbols(); ;
        $stock_name = "";
        $timeframe = "5";
        $currentDate = date('Y-m-d');

        $greekSentiments = collect();

        try {
            $greekSentimentQuery = \DB::connection('mysql_iv_theta')->table('IV Theta Sentiments')
                ->select('*')
                ->where('date', $currentDate)
                ->orderBy('id', 'DESC');

            if ($request->stock_name != null) {
                $stock_name = $request->stock_name;
                $greekSentimentQuery->where('symbol', $stock_name);
            }

            $greekSentimentQuery->where('timeframe', $timeframe);
            $greekSentiments = $greekSentimentQuery->paginate(30);

            if ($greekSentiments->isEmpty()) {
                $fallbackQuery = \DB::connection('mysql_iv_theta')->table('IV Theta Sentiments')
                    ->select('*')
                    ->orderBy('id', 'DESC');

                if ($request->stock_name != null) {
                    $stock_name = $request->stock_name;
                    $fallbackQuery->where('symbol', $stock_name);
                }

                $fallbackQuery->where('timeframe', $timeframe);
                $greekSentiments = $fallbackQuery->paginate(30);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to fetch IV Theta Sentiments: " . $e->getMessage());
        }

        // $greekSentiments = \DB::connection('mysql_rm')->table('IV Theta Sentiments')->select('*')->where('date',$currentDate)->orderBy('id','DESC');
        // if($request->stock_name != NULL){
        //     $stock_name = $request->stock_name;
        //     $greekSentiments =  $greekSentiments->where('symbol',$stock_name);
        // }
        // if($request->timeframe != NULL){
        //     $timeframe = $request->timeframe;
        // } 
        // $greekSentiments =  $greekSentiments->where('timeframe',$timeframe);
        // $greekSentiments =  $greekSentiments->paginate(30);

        // if($greekSentiments == NULL){
        //     $greekSentiments = \DB::connection('mysql_rm')->table('IV Theta Sentiments')->select('*')->orderBy('id','DESC');
        //     if($request->stock_name != NULL){
        //         $stock_name = $request->stock_name;
        //     }
        //     $greekSentiments =  $greekSentiments->where('symbol',$stock_name);
        //     if($request->timeframe != NULL){
        //         $timeframe = $request->timeframe;
        //     } 
        //     $greekSentiments =  $greekSentiments->where('timeframe',$timeframe);
        //     $greekSentiments =  $greekSentiments->paginate(30);
        // }

        return view($this->activeTemplate . 'user.dashboard', compact('pageTitle', 'user', 'totalDeposit', 'totalTrx', 'latestTrx', 'totalSignal', 'portfolioTopGainers', 'portfolioTopLosers','stockPortFolio','globalStockPortFolio','foglobalStockPortFolio','metalsPortFolio','totalInvestedAmount','totalCurrentAmount','datesArr','buyArr','currArr','chrtArr','symbolArray','symbolArray2','StrengthsymbolArr','fullUrl','stock_name','greekSentiments','timeframe')); 
    }

    public function homeajax(Request $request)
    {
        $user = auth()->user();
        $pageTitle = 'Dashboard';

        $StrengthsymbolArr = allTradeSymbols(); ;
        $stock_name = "";
        $timeframe = "5";
        $currentDate = date('Y-m-d');
        $greekSentiments = \DB::connection('mysql_iv_theta')->table('IV Theta Sentiments')->select('*')->where('date',$currentDate)->orderBy('id','DESC');
        if($request->stock_name != NULL){
            $stock_name = $request->stock_name;
            $greekSentiments =  $greekSentiments->where('symbol',$stock_name);
        }
        if($request->timeframe != NULL){
            $timeframe = $request->timeframe;
        } 
        $greekSentiments =  $greekSentiments->where('timeframe',$timeframe);
        $greekSentiments =  $greekSentiments->paginate(30);

        if($greekSentiments == NULL){
            $greekSentiments = \DB::connection('mysql_iv_theta')->table('IV Theta Sentiments')->select('*')->orderBy('id','DESC');
            if($request->stock_name != NULL){
                $stock_name = $request->stock_name;
            }
            $greekSentiments =  $greekSentiments->where('symbol',$stock_name);
            if($request->timeframe != NULL){
                $timeframe = $request->timeframe;
            } 
            $greekSentiments =  $greekSentiments->where('timeframe',$timeframe);
            $greekSentiments =  $greekSentiments->paginate(30);
        }

        $totalTrx = Transaction::where('user_id', $user->id)->count();
        $totalSignal = SignalHistory::where('user_id', $user->id)->count();
        $latestTrx = Transaction::where('user_id', $user->id)->orderBy('id', 'DESC')->limit(10)->get();
        $totalDeposit = Deposit::where('user_id', $user->id)->where('status', Status::PAYMENT_SUCCESS)->sum('amount');
        $portfolioTopGainers = PortfolioTopGainer::all();
        $portfolioTopLosers = PortfolioTopLoser::all();

        $date1 = date("Y-m-01");
        $date2 = date("Y-m-t");
        $stockPortFolioBuyVal = 0;
        $stockPortFolioCurrVal = 0;

        $investGraphArr = [];

        $stockPortFolio =  StockPortfolio::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();

        foreach($stockPortFolio as $v){
            $investGraphArr[$v->buy_date] = [
                'buy_value'=>$v->buy_value,
                'current_value'=>$v->current_value
            ];
            $stockPortFolioBuyVal += $v->buy_value;
            $stockPortFolioCurrVal += $v->current_value;
        }

        $stockPortFolio->buy_value = $stockPortFolioBuyVal;
        $stockPortFolio->current_value = $stockPortFolioCurrVal;

        $globalstockPortFolioBuyVal = 0;
        $globalstockPortFolioCurrVal = 0;


        $globalStockPortFolio =  GlobalStockPortFolio::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();
        foreach($globalStockPortFolio as $v){
            if(isset($investGraphArr[$v->buy_date])){
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value + $investGraphArr[$v->buy_date]['buy_value'],
                    'current_value'=>$v->current_value + $investGraphArr[$v->buy_date]['current_value']
                ];
            }else{
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value,
                    'current_value'=>$v->current_value
                ];
            }            
            $globalstockPortFolioBuyVal += $v->buy_value;
            $globalstockPortFolioCurrVal += $v->current_value;
        }
        $globalStockPortFolio->buy_value = $globalstockPortFolioBuyVal;
        $globalStockPortFolio->current_value = $globalstockPortFolioCurrVal;


        $foglobalstockPortFolioBuyVal = 0;
        $foglobalstockPortFolioCurrVal = 0;


        $foglobalStockPortFolio =  FOPortfolios::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();
        foreach($foglobalStockPortFolio as $v){
            if(isset($investGraphArr[$v->buy_date])){
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value + $investGraphArr[$v->buy_date]['buy_value'],
                    'current_value'=>$v->current_value + $investGraphArr[$v->buy_date]['current_value']
                ];
            }else{
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value,
                    'current_value'=>$v->current_value
                ];
            }            
            $foglobalstockPortFolioBuyVal += $v->buy_value;
            $foglobalstockPortFolioCurrVal += $v->current_value;
        }


        $foglobalStockPortFolio->buy_value = $foglobalstockPortFolioBuyVal;
        $foglobalStockPortFolio->current_value = $foglobalstockPortFolioCurrVal;


        $metalsPortFolioBuyVal = 0;
        $metalsPortFolioCurrVal = 0;


        $metalsPortFolio =  MetalsPortfolio::select(\DB::raw('SUM(quantity*buy_price) as buy_value'),\DB::raw('SUM(quantity*cmp) as current_value'),\DB::raw('DATE_FORMAT(buy_date,"%M-%Y") as buy_date'))->where('user_id',$user->id)->groupBy('buy_date')->get();

        foreach($metalsPortFolio as $v){
            if(isset($investGraphArr[$v->buy_date])){
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value + $investGraphArr[$v->buy_date]['buy_value'],
                    'current_value'=>$v->current_value + $investGraphArr[$v->buy_date]['current_value']
                ];
            }else{
                $investGraphArr[$v->buy_date] = [
                    'buy_value'=>$v->buy_value,
                    'current_value'=>$v->current_value
                ];
            }            
            $metalsPortFolioBuyVal += $v->buy_value;
            $metalsPortFolioCurrVal += $v->current_value;
        }

        $metalsPortFolio->buy_value = $metalsPortFolioBuyVal;
        $metalsPortFolio->current_value = $metalsPortFolioCurrVal;


        $totalInvestedAmount = $stockPortFolio->buy_value + $globalStockPortFolio->buy_value + $foglobalStockPortFolio->buy_value + $metalsPortFolio->buy_value;
        $totalCurrentAmount = $stockPortFolio->current_value + $globalStockPortFolio->current_value + $foglobalStockPortFolio->current_value + $metalsPortFolio->current_value;

        $buyArr = [];
        $currArr = [];
        $datesArr = [];

        if(!empty($investGraphArr)){
           
            $datesArr = array_keys($investGraphArr);
            $datesArr = array_map(function($kk){
                return date("M-Y",strtotime($kk));
            },$datesArr);
            $buyArr = array_column($investGraphArr,'buy_value');
            $currArr = array_column($investGraphArr,'current_value');
        }

        $chrtArr = [
            $stockPortFolio->buy_value,$metalsPortFolio->buy_value,$globalStockPortFolio->buy_value,$foglobalStockPortFolio->buy_value
        ];

        $symbolArray = [];
        foreach ($portfolioTopGainers as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }

        $symbolArray2 = [];
        foreach ($portfolioTopLosers as $val) {
           array_push($symbolArray2 , $val['stock_name'].".NS");
        }    

        $fullUrl = $request->fullUrl();
        return view($this->activeTemplate . 'user.dashboard', compact('pageTitle', 'user', 'totalDeposit', 'totalTrx', 'latestTrx', 'totalSignal', 'portfolioTopGainers', 'portfolioTopLosers','stockPortFolio','globalStockPortFolio','foglobalStockPortFolio','metalsPortFolio','totalInvestedAmount','totalCurrentAmount','datesArr','buyArr','currArr','chrtArr','symbolArray','symbolArray2','StrengthsymbolArr','fullUrl','stock_name','greekSentiments','timeframe'));
    }

    public function depositHistory(Request $request)
    {
        $pageTitle = 'Deposit History';
        $deposits = auth()->user()->deposits()->searchable(['trx'])->with(['gateway'])->orderBy('id','desc')->paginate(getPaginate());
        return view($this->activeTemplate.'user.deposit_history', compact('pageTitle', 'deposits'));
    }

    public function show2faForm()
    {
        $ga = new GoogleAuthenticator();
        $user = auth()->user();
        $secret = $ga->createSecret();
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($user->username . '@' . gs('site_name'), $secret);
        $pageTitle = '2FA Setting';
        return view($this->activeTemplate.'user.twofactor', compact('pageTitle', 'secret', 'qrCodeUrl'));
    }

    public function create2fa(Request $request)
    {
        $user = auth()->user();
        $this->validate($request, [
            'key' => 'required',
            'code' => 'required',
        ]);
        $response = verifyG2fa($user,$request->code,$request->key);
        if ($response) {
            $user->tsc = $request->key;
            $user->ts = 1;
            $user->save();
            $notify[] = ['success', 'Google authenticator activated successfully'];
            return back()->withNotify($notify);
        } else {
            $notify[] = ['error', 'Wrong verification code'];
            return back()->withNotify($notify);
        }
    }

    public function disable2fa(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
        ]);

        $user = auth()->user();
        $response = verifyG2fa($user,$request->code);
        if ($response) {
            $user->tsc = null;
            $user->ts = 0;
            $user->save();
            $notify[] = ['success', 'Two factor authenticator deactivated successfully'];
        } else {
            $notify[] = ['error', 'Wrong verification code'];
        }
        return back()->withNotify($notify);
    }

    public function transactions()
    {
        $pageTitle = 'Stock Transactions';
        $transactions = Transaction::where('user_id',auth()->id());
        $remarks = Transaction::where('remark', '!=', null)->distinct('remark')->orderBy('remark')->get('remark');

        $transactions = Transaction::where('user_id',auth()->id())->searchable(['trx'])->filter(['trx_type','remark'])->orderBy('id','desc')->paginate(getPaginate());
        return view($this->activeTemplate.'user.transactions', compact('pageTitle','transactions','remarks'));
    }

    public function kycForm()
    {
        if (auth()->user()->kv == 2) {
            $notify[] = ['error','Your KYC is under review'];
            return to_route('user.home')->withNotify($notify);
        }
        if (auth()->user()->kv == 1) {
            $notify[] = ['error','You are already KYC verified'];
            return to_route('user.home')->withNotify($notify);
        }
        $pageTitle = 'KYC Form';
        $form = Form::where('act','kyc')->first();
        return view($this->activeTemplate.'user.kyc.form', compact('pageTitle','form'));
    }

    public function kycData()
    {
        $user = auth()->user();
        $pageTitle = 'KYC Data';
        return view($this->activeTemplate.'user.kyc.info', compact('pageTitle','user'));
    }

    public function kycSubmit(Request $request)
    {
        $form = Form::where('act','kyc')->first();
        $formData = $form->form_data;
        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);
        $user = auth()->user();
        $user->kyc_data = $userData;
        $user->kv = 2;
        $user->save();

        $notify[] = ['success','KYC data submitted successfully'];
        return to_route('user.home')->withNotify($notify);

    }

    public function attachmentDownload($fileHash)
    {
        $filePath = decrypt($fileHash);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $general = gs();
        $title = slug($general->site_name).'- attachments.'.$extension;
        $mimetype = mime_content_type($filePath);
        header('Content-Disposition: attachment; filename="' . $title);
        header("Content-Type: " . $mimetype);
        return readfile($filePath);
    }

    public function userData()
    {
        $user = auth()->user();
        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }
        $pageTitle = 'User Data';
        return view($this->activeTemplate.'user.user_data', compact('pageTitle','user'));
    }

    public function userDataSubmit(Request $request)
    {
        $user = auth()->user();
        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }
        $request->validate([
            'firstname'=>'required',
            'lastname'=>'required',
        ]);
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
        $user->profile_complete = Status::YES;
        $user->save();

        $notify[] = ['success','Registration process completed successfully'];
        return to_route('user.info')->withNotify($notify);
    }

    public function purchasePackage(Request $request){

        $request->validate([
            'id' => 'required|integer'
        ]);

        $package = Package::active()->findOrFail($request->id);
        $user = auth()->user();

        if($package->price > $user->balance){
            $notify[] = ['info', 'Sorry, Insufficient balance'];
            return back()->withNotify($notify);
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

        $notify[] = ['success', 'You have purchased '.$package->name.' successfully'];
        return to_route('user.transactions', ['search'=>$transaction->trx])->withNotify($notify);
    }

    public function renewPackage(Request $request){

        $request->validate([
            'id' => 'required|integer'
        ]);

        $package = Package::findOrFail($request->id);

        if(!$package->status){
            $notify[] = ['info', 'Sorry, '.$package->name .' is not available to renew right now'];
            return to_route('user.home')->withNotify($notify);
        }

        $user = auth()->user();

        if($user->package_id != $package->id){
            $notify[] = ['error', 'Sorry, There is no Product to renew'];
            return back()->withNotify($notify);
        }

        if($package->price > $user->balance){
            $notify[] = ['info', 'Sorry, Insufficient balance'];
            return back()->withNotify($notify);
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

        $notify[] = ['success', 'You have renewed '.$package->name.' successfully'];
        return to_route('user.transactions', ['search'=>$transaction->trx])->withNotify($notify);
    }

    public function signals(Request $request){
        $pageTitle = 'Signals';
        $signals  = SignalHistory::where('user_id', auth()->user()->id);

        if ($request->search) {
            $signals = $signals->whereHas('signal', function($signal) use ($request){
                $signal->where('name', 'LIKE', '%'.$request->search.'%');
            });
        }

        $signals = $signals->orderBy('id','desc')->with('signal')->paginate(getPaginate());
        return view($this->activeTemplate.'user.signals', compact('pageTitle', 'signals'));
    }

    public function referrals(){
        $user = auth()->user();
        $pageTitle = 'Referrals';
        $maxLevel  = Referral::max('level');
        return view($this->activeTemplate.'user.referrals', compact('pageTitle', 'user', 'maxLevel'));
    }

    public function ledgers()
    {
        $pageTitle = 'Ledgers';

        // TODO:: modify commented code to implement searchable and filterable.
        $ledgers = Ledger::with('poolingAccountPortfolio')->where('user_id', auth()->id())->searchable(['stock_name'])/* ->filter(['trx_type', 'remark']) */->orderBy('id', 'desc')->paginate(getPaginate());

        return view($this->activeTemplate . 'user.ledgers', compact('pageTitle', 'ledgers'));
    }

    public function stockPortfolios(Request $request)
    {
        $pageTitle = 'Stock Portfolio';
        $fullUrl = $request->fullUrl();

        // TODO:: modify commented code to implement searchable and filterable.
        $stockPortfolios = StockPortfolio::with('poolingAccountPortfolio')->where('user_id', auth()->id())->searchable(['broker_name', 'stock_name'])/* ->filter(['trx_type', 'remark']) */->orderBy('id', 'desc')->paginate(getPaginate());

        $symbolArray = [];
        foreach ($stockPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }
        if($request->ajax()){
            return view($this->activeTemplate . 'user.stock_portfolio_ajax', compact('pageTitle', 'stockPortfolios','symbolArray','fullUrl'));
        }
        return view($this->activeTemplate . 'user.stock_portfolio', compact('pageTitle', 'stockPortfolios','symbolArray','fullUrl'));
    }

    public function thematicPortfolios(Request $request)
    {
        $pageTitle = 'Thematic Portfolios';

        // TODO:: modify commented code to implement searchable and filterable.
        $thematicPortfolios = ThematicPortfolio::searchable(['stock_name'])/* ->filter(['trx_type', 'remark']) */->orderBy('id', 'desc')->paginate(getPaginate());

        $symbolArray = [];
        foreach ($thematicPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }
        $fullUrl = $request->fullUrl();
        if($request->ajax()){
            return view($this->activeTemplate . 'user.thematic_portfolios_ajax', compact('pageTitle', 'thematicPortfolios','symbolArray','fullUrl'));
        }
        return view($this->activeTemplate . 'user.thematic_portfolios', compact('pageTitle', 'thematicPortfolios','symbolArray','fullUrl'));
    }

    public function globalStockPortfolio(Request $request)
    {
        $pageTitle = 'Global Stock Portfolio';
        $fullUrl = $request->fullUrl();
        // TODO:: modify commented code to implement searchable and filterable.
        $globalStockPortfolios = GlobalStockPortfolio::with('poolingAccountPortfolio')->where('user_id', auth()->id())->searchable(['broker_name', 'stock_price'])->orderBy('id', 'desc')->paginate(getPaginate());

        
        $symbolArray = [];
        foreach ($globalStockPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        // array_push($symbolArray , $val['stock_name']);
        }
        // $getToken = AngelApiInstrument::select('token')->WhereIn(['symbol',$symbolArray])->get();

        // dd($getToken);
        // $date = \DB::connection('mysql_pr')->table('LTP')->select('*')->get();
        if($request->ajax()){
            return view($this->activeTemplate . 'user.global_stock_portfolio_ajax', compact('pageTitle', 'globalStockPortfolios','symbolArray','fullUrl'));
        }
        return view($this->activeTemplate . 'user.global_stock_portfolio', compact('pageTitle', 'globalStockPortfolios','symbolArray','fullUrl'));
    }

    public function foPortFolioHedging(Request $request)
    {
        $pageTitle = 'FO Portfolio Hedging';
        $fullUrl = $request->fullUrl();
        // TODO:: modify commented code to implement searchable and filterable.
        $foPortFolioHedgings = FOPortfolios::with('poolingAccountPortfolio')->where('user_id', auth()->id())->searchable(['broker_name', 'stock_price'])/* ->filter(['trx_type', 'remark']) */->orderBy('id', 'desc')->paginate(getPaginate());
        
        $symbolArray = [];
        foreach ($foPortFolioHedgings as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }
        if($request->ajax()){
            return view($this->activeTemplate . 'user.fo_portfolio_hedging_ajax', compact('pageTitle', 'foPortFolioHedgings','symbolArray','fullUrl'));
        }
        return view($this->activeTemplate . 'user.fo_portfolio_hedging', compact('pageTitle', 'foPortFolioHedgings','symbolArray','fullUrl'));
    }

    public function metalsPortfolio(Request $request)
    {
        $pageTitle = 'Metals Portfolio';
        $fullUrl = $request->fullUrl();
        // TODO:: modify commented code to implement searchable and filterable.
        $metalsPortfolios = MetalsPortfolio::with('poolingAccountPortfolio')->where('user_id', auth()->id())->searchable(['broker_name', 'stock_price'])/* ->filter(['trx_type', 'remark']) */->orderBy('id', 'desc')->paginate(getPaginate());

        $symbolArray = [];
        foreach ($metalsPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }
        if($request->ajax()){
            return view($this->activeTemplate . 'user.metals_portfolio_ajax', compact('pageTitle', 'metalsPortfolios','symbolArray','fullUrl'));
        }

        return view($this->activeTemplate . 'user.metals_portfolio', compact('pageTitle', 'metalsPortfolios','symbolArray','fullUrl'));
    }

    public function portfolioTopGainers(Request $request)
    {
        $fullUrl =  $request->fullUrl();
        $pageTitle = 'Trade Desk Signal';
        $portfolioTopGainers = [];
       
        $todayDate = date("Y-m-d");
        // $todayDate = "2024-02-16";
          // $todayDate = "2024-04-03";
        $stockName = $request->stock_name;
        $timeFrame = $request->time_frame ? : 5;
        $symbolArr = allTradeSymbols();
        // if($request->ajax()){
        //     return view($this->activeTemplate . 'user.portfolio_top_gainers_ajax', compact('pageTitle', 'portfolioTopGainers','todayDate','timeFrame','stockName','fullUrl','symbolArr'));
        // }
        return view($this->activeTemplate . 'user.portfolio_top_gainers', compact('pageTitle', 'portfolioTopGainers','symbolArr','todayDate','timeFrame','stockName','fullUrl'));
    }

    public function portfolioTopGainersAjx(Request $request)
    {

        $fullUrl =  $request->fullUrl();
        $pageTitle = 'Trade Desk Signal';
        $portfolioTopGainers = [];
        $todayDate = date("Y-m-d");
        $stockName = $request->stock_name;
        $timeFrame = $request->time_frame ? : 5;
        $symbolArr = allTradeSymbols();
        return view($this->activeTemplate . 'user.portfolio_top_gainers_ajax', compact('pageTitle', 'portfolioTopGainers','todayDate','timeFrame','stockName','fullUrl','symbolArr'));
    }




    public function portfolioGreeks(Request $request)
    {

        $fullUrl =  $request->fullUrl();
        $pageTitle = 'Greeks Desk Signal';
        $portfolioTopGainers = [];
       
        $todayDate = date("Y-m-d");
        $stockName = $request->stock_name;
        $timeFrame = $request->time_frame ? : 5;
        $symbolArr = allTradeSymbols();        
        return view($this->activeTemplate . 'user.portfolio-greeks', compact('pageTitle', 'portfolioTopGainers','symbolArr','todayDate','timeFrame','stockName','fullUrl'));
    }

    public function portfolioGreeksGraphs(Request $request){
        $fullUrl =  $request->fullUrl();
        $pageTitle = 'Greeks Desk Signal';
        $portfolioTopGainers = [];
       
        $todayDate = date("Y-m-d");
        $stockName = $request->stock_name;
        $timeFrame = $request->time_frame ? : 5;
        $symbolArr = allTradeSymbols();   
        
        
       

        return view($this->activeTemplate . 'user.portfolio-greeks-graphs', compact('pageTitle', 'portfolioTopGainers','symbolArr','todayDate','timeFrame','stockName','fullUrl'));
    }


    public function portfolioTopGainersStock(Request $request)
    {

        $fullUrl =  $request->fullUrl();
        $pageTitle = 'Trade Desk Signal(Stock)';
        $portfolioTopGainers = [];
       
        $todayDate = date("Y-m-d");
        // $todayDate = "2024-02-16";
        $stockName = $request->stock_name;
        $timeFrame = $request->time_frame ? : 15;
        $symbolArr = allTradeSymbols();
        if($request->ajax()){
            return view($this->activeTemplate . 'user.portfolio_top_gainers_ajax_stock', compact('pageTitle', 'portfolioTopGainers','todayDate','timeFrame','stockName','fullUrl','symbolArr'));
        }
        
        return view($this->activeTemplate . 'user.portfolio_top_gainers_stock', compact('pageTitle', 'portfolioTopGainers','symbolArr','todayDate','timeFrame','stockName','fullUrl'));
    }

    public function portfolioTopGainersStockAjx(Request $request){
        $fullUrl =  $request->fullUrl();
        $pageTitle = 'Trade Desk Signal(Stock)';
        $portfolioTopGainers = [];
        $todayDate = date("Y-m-d");
        $stockName = $request->stock_name;
        $timeFrame = $request->time_frame ? : 15;
        $symbolArr = allTradeSymbols();
        return view($this->activeTemplate . 'user.portfolio_top_gainers_ajax_stock', compact('pageTitle', 'portfolioTopGainers','todayDate','timeFrame','stockName','fullUrl','symbolArr'));
    }

    public function brokerDetails(){
        $data['pageTitle'] = 'Broker Details';
        $data['broker_data'] = BrokerApi::where('user_id',auth()->user()->id)->get();
        return view($this->activeTemplate . 'user.broker_details',$data);
    }

    public function orderBooks(Request $request){
        $fullUrl =  $request->fullUrl();
        $data['pageTitle'] = 'Order Boook';
        $broker_data = BrokerApi::where('user_id',auth()->user()->id)->get();
        $data['broker_data'] = $broker_data;
        $brokerId = 0;
        $orderData = [];
        $filterBrokderD = $broker_data;

        if(!empty($request->broker_name)){
            foreach($broker_data as $vl){
                if($vl->id==$request->broker_name){
                    $filterBrokderD = [];
                    $filterBrokderD[] = $vl;
                    $brokerId = $vl->id;
                    // echo'true<br>';
                    break;
                }
            }
            if($request->broker_name=="OMS_ORDERS"){
                $filterBrokderD = [];
                $brokerId = 'OMS_ORDERS';
            }
        }

        $data['brokerId'] = $brokerId;

        if(empty($request->broker_name) || $request->broker_name=="OMS_ORDERS"){
            $orderData['OMS CONFIG'] = OrderBook::select('*')->where('user_id',auth()->user()->id)->paginate(50);
        }

        
        foreach($filterBrokderD as $userData){
            if($userData->client_type=='Zerodha'){
                $orderData[$userData->account_user_name.' ('.$userData->client_name.')'] = [];
            }elseif($userData->client_type=='Angel'){
                $param = [
                    'accountUserName'=>$userData->account_user_name,
                    'apiKey'=>$userData->api_key,
                    'pin'=>$userData->security_pin,
                    'totp_secret'=>$userData->totp,
                ];
                $angelObj = new AngelConnectCls($param);
                $angelTokenArr = $angelObj->generate_access_token();
                if(is_null($angelTokenArr)){
                    $orderData[$userData->account_user_name.' ('.$userData->client_name.')'] = [];
                }else{
                    
                    // echo $angelTokenArr['token'];die;
                    $tokenA = $angelTokenArr['token'];
                    $clientLocalIp = $angelTokenArr['clientLocalIp'];
                    $clientPublicIp = $angelTokenArr['clientPublicIp'];
                    $macAddress = $angelTokenArr['macAddress'];
                    $httpHeaders = array(
                        'X-UserType: USER',
                        'X-SourceID: WEB',
                        'X-PrivateKey: '.$userData->api_key,
                        'X-ClientLocalIP: '.$clientLocalIp,
                        'X-ClientPublicIP: '.$clientPublicIp,
                        'X-MACAddress: '.$macAddress,
                        'Content-Type: application/json',
                        'Authorization: Bearer '.$tokenA
                    );
                    $fDada = [];
    
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/order/v1/getOrderBook',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'GET',
                        CURLOPT_HTTPHEADER => $httpHeaders,
                    ));
                    $response = curl_exec($curl);
                    curl_close($curl);
                    $dataArr = json_decode($response);
                    // dd($dataArr);
                    if($dataArr!=null && isset($dataArr->status) &&  $dataArr->status==true){
                        if(!is_null($dataArr->data)){
                            foreach($dataArr->data as $vl){
                                $fDada[] = (object)[
                                    "variety"=> $vl->variety,
                                    "ordertype"=> $vl->ordertype,
                                    "producttype"=> $vl->producttype,
                                    "duration"=> $vl->duration,
                                    "price"=> $vl->price,
                                    "quantity"=> $vl->quantity,
                                    "tradingsymbol"=> $vl->tradingsymbol,
                                    "transactiontype"=> $vl->transactiontype,
                                    "lotsize"=> $vl->lotsize,
                                    "averageprice"=> $vl->averageprice,
                                    "orderid"=> $vl->orderid,
                                    "status"=> $vl->status,
                                    "orderstatus"=> $vl->orderstatus,
                                    "updatetime"=> $vl->updatetime,
                                ];
                            }
                        }
                    }
                    $orderData[$userData->account_user_name.' ('.$userData->client_name.')'] = $fDada;
                }
               
            }
        }

        $data['order_data'] = $orderData;
        $data['fullUrl'] = $fullUrl;
        // if($request->ajax()){
            
        //     return view($this->activeTemplate . 'user.order_books_ajax',$data);
        // }
        return view($this->activeTemplate . 'user.order_books',$data);
    }

    public function orderBooksAjax(Request $request){
        $fullUrl =  $request->fullUrl();
        $data['pageTitle'] = 'Order Boook';
        $broker_data = BrokerApi::where('user_id',auth()->user()->id)->get();
        $data['broker_data'] = $broker_data;
        $brokerId = 0;
        $orderData = [];
        $filterBrokderD = $broker_data;

        if(!empty($request->broker_name)){
            foreach($broker_data as $vl){
                if($vl->id==$request->broker_name){
                    $filterBrokderD = [];
                    $filterBrokderD[] = $vl;
                    $brokerId = $vl->id;
                    // echo'true<br>';
                    break;
                }
            }
            if($request->broker_name=="OMS_ORDERS"){
                $filterBrokderD = [];
                $brokerId = 'OMS_ORDERS';
            }
        }

        $data['brokerId'] = $brokerId;

        if(empty($request->broker_name) || $request->broker_name=="OMS_ORDERS"){
            $orderData['OMS CONFIG'] = OrderBook::select('*')->where('user_id',auth()->user()->id)->paginate(50);
        }

        
        foreach($filterBrokderD as $userData){
            if($userData->client_type=='Zerodha'){
                $orderData[$userData->account_user_name.' ('.$userData->client_name.')'] = [];
            }elseif($userData->client_type=='Angel'){
                $param = [
                    'accountUserName'=>$userData->account_user_name,
                    'apiKey'=>$userData->api_key,
                    'pin'=>$userData->security_pin,
                    'totp_secret'=>$userData->totp,
                ];
                $angelObj = new AngelConnectCls($param);
                $angelTokenArr = $angelObj->generate_access_token();
                if(is_null($angelTokenArr)){
                    $orderData[$userData->account_user_name.' ('.$userData->client_name.')'] = [];
                }else{
                    
                    // echo $angelTokenArr['token'];die;
                    $tokenA = $angelTokenArr['token'];
                    $clientLocalIp = $angelTokenArr['clientLocalIp'];
                    $clientPublicIp = $angelTokenArr['clientPublicIp'];
                    $macAddress = $angelTokenArr['macAddress'];
                    $httpHeaders = array(
                        'X-UserType: USER',
                        'X-SourceID: WEB',
                        'X-PrivateKey: '.$userData->api_key,
                        'X-ClientLocalIP: '.$clientLocalIp,
                        'X-ClientPublicIP: '.$clientPublicIp,
                        'X-MACAddress: '.$macAddress,
                        'Content-Type: application/json',
                        'Authorization: Bearer '.$tokenA
                    );
                    $fDada = [];
    
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/order/v1/getOrderBook',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'GET',
                        CURLOPT_HTTPHEADER => $httpHeaders,
                    ));
                    $response = curl_exec($curl);
                    curl_close($curl);
                    $dataArr = json_decode($response);
                    // dd($dataArr);
                    if($dataArr!=null && isset($dataArr->status) &&  $dataArr->status==true){
                        if(!is_null($dataArr->data)){
                            foreach($dataArr->data as $vl){
                                $fDada[] = (object)[
                                    "variety"=> $vl->variety,
                                    "ordertype"=> $vl->ordertype,
                                    "producttype"=> $vl->producttype,
                                    "duration"=> $vl->duration,
                                    "price"=> $vl->price,
                                    "quantity"=> $vl->quantity,
                                    "tradingsymbol"=> $vl->tradingsymbol,
                                    "transactiontype"=> $vl->transactiontype,
                                    "lotsize"=> $vl->lotsize,
                                    "averageprice"=> $vl->averageprice,
                                    "orderid"=> $vl->orderid,
                                    "status"=> $vl->status,
                                    "orderstatus"=> $vl->orderstatus,
                                    "updatetime"=> $vl->updatetime,
                                ];
                            }
                        }
                    }
                    $orderData[$userData->account_user_name.' ('.$userData->client_name.')'] = $fDada;
                }
               
            }
        }

        $data['order_data'] = $orderData;
        $data['fullUrl'] = $fullUrl;
        return view($this->activeTemplate . 'user.order_books_ajax',$data);
    }

    

    public function tradePositions(Request $request){
        // echo date("Y-m-d H:i:s");die;
        $data['pageTitle'] = 'Trade Positions';
        $broker_data = BrokerApi::where('user_id',auth()->user()->id)->get();
        $data['broker_data'] = $broker_data;
        $tradeBookData = [];
        $brokerId = 0;
        $filterBrokderD = $broker_data;
        if(!empty($request->broker_name)){
            foreach($broker_data as $vl){
                if($vl->id==$request->broker_name){
                    $filterBrokderD = [];
                    $filterBrokderD[] = $vl;
                    $brokerId = $vl->id;
                    // echo'true<br>';
                    break;
                }
            }
        }
        // dd($filterBrokderD);
        foreach($filterBrokderD as $userData){
            if($userData->client_type=='Zerodha'){
                $tradeBookData[$userData->account_user_name.' ('.$userData->client_name.')'] = [];
            }elseif($userData->client_type=='Angel'){
                $param = [
                    'accountUserName'=>$userData->account_user_name,
                    'apiKey'=>$userData->api_key,
                    'pin'=>$userData->security_pin,
                    'totp_secret'=>$userData->totp,
                ];
                $angelObj = new AngelConnectCls($param);
                $angelTokenArr = $angelObj->generate_access_token();
                // echo $angelTokenArr['token'];die;
                $tokenA = $angelTokenArr['token'];
                $clientLocalIp = $angelTokenArr['clientLocalIp'];
                $clientPublicIp = $angelTokenArr['clientPublicIp'];
                $macAddress = $angelTokenArr['macAddress'];
                $httpHeaders = array(
                    'X-UserType: USER',
                    'X-SourceID: WEB',
                    'X-PrivateKey: '.$userData->api_key,
                    'X-ClientLocalIP: '.$clientLocalIp,
                    'X-ClientPublicIP: '.$clientPublicIp,
                    'X-MACAddress: '.$macAddress,
                    'Content-Type: application/json',
                    'Authorization: Bearer '.$tokenA
                );
                $fDada = [];

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/order/v1/getPosition',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => $httpHeaders,
                ));
                $response = curl_exec($curl);
                curl_close($curl);
                $dataArr = json_decode($response);
                // dd($dataArr);
                if($dataArr!=null && $dataArr->status==true){
                    if(!is_null($dataArr->data)){
                        $realisedTotal = 0;
                        $unRealisedTotal = 0;
                        foreach($dataArr->data as $vl){
                            $realisedTotal = $realisedTotal + ($vl->realised);
                            $unRealisedTotal = $unRealisedTotal + ($vl->unrealised);
                            $fDada[] = (object)[
                                "producttype"=> $vl->producttype,
                                "cfbuyqty"=> $vl->cfbuyqty,
                                "cfsellqty"=> $vl->cfsellqty,
                                "buyavgprice"=> $vl->buyavgprice,
                                "sellavgprice"=> $vl->sellavgprice,
                                "avgnetprice"=> $vl->avgnetprice,
                                "netvalue"=> $vl->netvalue,
                                "netqty"=> $vl->netqty,
                                "totalbuyvalue"=> $vl->totalbuyvalue,
                                "totalsellvalue"=> $vl->totalsellvalue,
                                "cfbuyavgprice"=> $vl->cfbuyavgprice,
                                "cfsellavgprice"=> $vl->cfsellavgprice,
                                "totalbuyavgprice"=> $vl->totalbuyavgprice,
                                "totalsellavgprice"=> $vl->totalsellavgprice,
                                "netprice"=> $vl->netprice,
                                "buyqty"=> $vl->buyqty,
                                "sellqty"=> $vl->sellqty,
                                "buyamount"=> $vl->buyamount,
                                "sellamount"=> $vl->sellamount,
                                "pnl"=> $vl->pnl,
                                "realised"=> $vl->realised,
                                "unrealised"=> $vl->unrealised,
                                "ltp"=> $vl->ltp,
                                "close"=> $vl->close
                            ];
                        }
                        $fDada['realised'] = $realisedTotal;
                        $fDada['un_realised'] = $unRealisedTotal;
                    }
                }
                $tradeBookData[$userData->account_user_name.' ('.$userData->client_name.')'] = $fDada;
            }
        }
        $data['trade_book_data'] = $tradeBookData;
       
        $data['brokerId'] = $brokerId;
        return view($this->activeTemplate . 'user.trade_positions',$data);
    }



    public function getBrokerDetails(Request $request,$id){
        $data['broker_data'] = BrokerApi::where(['user_id'=>auth()->user()->id,'id'=>$id])->first();
        return view($this->activeTemplate.'user.get_broker_details',$data);
    }



    public function portfolioTopLosers()
    {
        $pageTitle = 'Portfolio Top Losers';

        // TODO:: modify commented code to implement searchable and filterable.
        $portfolioTopLosers = PortfolioTopLoser::searchable(['stock_name'])/* filter(['trx_type', 'remark']) ->*/->orderBy('id', 'desc')->paginate(getPaginate());

        $symbolArray = [];
        foreach ($portfolioTopLosers as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }

        return view($this->activeTemplate . 'user.portfolio_top_losers', compact('pageTitle', 'portfolioTopLosers','symbolArray'));
    }

    public function storeBrokerDetails(Request $request){
        $brokerApi = new BrokerApi();
        $brokerApi->client_name = $request->client_name;
        $brokerApi->broker_name = $request->broker_name;
        $brokerApi->account_user_name = $request->account_user_name;
        $brokerApi->account_password = $request->account_password;
        $brokerApi->api_key = $request->api_key;
        $brokerApi->api_secret_key = $request->api_secret_key;
        $brokerApi->security_pin = $request->security_pin;
        $brokerApi->totp = $request->totp;
        $brokerApi->client_type = $request->client_type;
        $brokerApi->user_id = auth()->user()->id;
        $brokerApi->save();
        $notify[] = ['success', 'Broker Details Added Successfully...'];
        return to_route('user.portfolio.broker-details')->withNotify($notify);
    }

    public function updateBrokerDetails(Request $request,$id){
        $brokerApi = BrokerApi::find($id);
        if($brokerApi->user_id!=auth()->user()->id){
            return to_route('user.portfolio.broker-details');
        }
        $brokerApi->client_name = $request->client_name;
        $brokerApi->broker_name = $request->broker_name;
        $brokerApi->account_user_name = $request->account_user_name;
        $brokerApi->account_password = $request->account_password;
        $brokerApi->api_key = $request->api_key;
        $brokerApi->api_secret_key = $request->api_secret_key;
        $brokerApi->security_pin = $request->security_pin;
        $brokerApi->totp = $request->totp;
        $brokerApi->request_token = $request->request_token;
        $brokerApi->client_type = $request->client_type;
        $brokerApi->user_id = auth()->user()->id;
        $brokerApi->save();
        $notify[] = ['success', 'Broker Details Updated Successfully...'];
        return to_route('user.portfolio.broker-details')->withNotify($notify);
    }

    public function removeBrokerDetails(Request $request,$id){
        BrokerApi::where(['id'=>$id,'user_id'=>auth()->user()->id])->delete();
        OmsConfig::where(['broker_api_id' => $id])->delete();
        $notify[] = ['success', 'Broker Details Deleted Successfully...'];
        return to_route('user.portfolio.broker-details')->withNotify($notify);
    }

    public function tradeBook(Request $request){
        $pageTitle = 'Trade Book';
        $data['pageTitle'] = $pageTitle;
        $data['fullUrl'] = $request->fullUrl();
        
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $array = explode('/' ,$request->buyDate);
            $dataFrom = $array[0];
            $dateTo = $array[1];
        }

        $Ledger = Ledger::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $Ledger->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date',[$dataFrom,$dateTo]);
        }
        $Ledger = $Ledger->get();

        $stock = Ledger::select('stock_name')->where('user_id',auth()->user()->id)->get();


        $currentYear = date('Y');
        $datas = Ledger::select('bought_date as date', \DB::raw('COUNT(*) as count'))->where('user_id',auth()->user()->id)->whereYear('bought_date', $currentYear)->groupBy('bought_date')->orderBy('bought_date')->get();

        // dd($datas);
        // if($request->ajax()){
        //     return view($this->activeTemplate . 'user.trade-book-ajax',$data,compact('Ledger','stock','datas'));
        // }
        return view($this->activeTemplate . 'user.trade-book',$data,compact('Ledger','stock','datas'));
    }

    public function tradeBookAjax(Request $request){
        $pageTitle = 'Trade Book';
        $data['pageTitle'] = $pageTitle;
        $data['fullUrl'] = $request->fullUrl();
        
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $array = explode('/' ,$request->buyDate);
            $dataFrom = $array[0];
            $dateTo = $array[1];
        }

        $Ledger = Ledger::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $Ledger->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date',[$dataFrom,$dateTo]);
        }
        $Ledger = $Ledger->get();

        $stock = Ledger::select('stock_name')->where('user_id',auth()->user()->id)->get();


        $currentYear = date('Y');
        $datas = Ledger::select('bought_date as date', \DB::raw('COUNT(*) as count'))->where('user_id',auth()->user()->id)->whereYear('bought_date', $currentYear)->groupBy('bought_date')->orderBy('bought_date')->get();
        return view($this->activeTemplate . 'user.trade-book-ajax',$data,compact('Ledger','stock','datas'));
    }

    public function getStockName(){
        $Ledger = Ledger::select('stock_name')->where('user_id',auth()->user()->id)->get();
        $MetalsPortfolio = MetalsPortfolio::select('stock_name')->where('user_id',auth()->user()->id)->get();
        $FOPortfolios = FOPortfolios::select('stock_name')->where('user_id',auth()->user()->id)->get();
        $GlobalStockPortfolio = GlobalStockPortfolio::select('stock_name')->where('user_id',auth()->user()->id)->get();
        $StockPortfolio = StockPortfolio::select('stock_name')->where('user_id',auth()->user()->id)->get();

        // Merge Data
        $combinedArray = array_merge($Ledger->toArray(), $MetalsPortfolio->toArray(), $FOPortfolios->toArray(), $GlobalStockPortfolio->toArray() , $StockPortfolio->toArray());

        // Sort Data
        $dates = array_column($combinedArray, 'stock_name');
        array_multisort($dates, SORT_ASC, $combinedArray);

        return $combinedArray;

    }

    public function plReports(Request $request){
        $fullUrl = $request->fullUrl();
        $pageTitle = 'PL Reports';
        $data['pageTitle'] = $pageTitle;
        $data['fullUrl'] = $request->fullUrl();

        $segments = "all";
        $type = "all";
        $symbol = 'all';
        $buyDate = 'all';
        $array = [];  

        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $array = explode('/' ,$request->buyDate);
            $dataFrom = $array[0];
            $dateTo = $array[1];
        }

        $Ledger = Ledger::select(['*', 'bought_date as buy_date'])->where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $Ledger->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date',[$dataFrom,$dateTo]);
        }
        $Ledger = $Ledger->get();


        $MetalsPortfolio = MetalsPortfolio::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $MetalsPortfolio->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $MetalsPortfolio->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $MetalsPortfolio->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $MetalsPortfolio = $MetalsPortfolio->get();


        $FOPortfolios = FOPortfolios::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $FOPortfolios->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $FOPortfolios->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $FOPortfolios->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $FOPortfolios = $FOPortfolios->get();


        $GlobalStockPortfolio = GlobalStockPortfolio::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $GlobalStockPortfolio->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $GlobalStockPortfolio->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $GlobalStockPortfolio->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $GlobalStockPortfolio = $GlobalStockPortfolio->get();


        $StockPortfolio = StockPortfolio::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $StockPortfolio->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $StockPortfolio->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $StockPortfolio->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $StockPortfolio = $StockPortfolio->get();

        // Type = Realised (Lagyer) , Unrelized = (Except Lagyer)
        if(!empty($request->type) && $request->type!='all'){
            if($request->type == "unrealized"){
                if(!empty($request->segments) && $request->segments!='all'){
                    if($request->segments == "global"){
                        $combinedArray = array_merge($GlobalStockPortfolio->toArray());
                    }else if($request->segments == "fQ"){
                        $combinedArray = array_merge($FOPortfolios->toArray());
                    }else if($request->segments == "metals"){
                        $combinedArray = array_merge($MetalsPortfolio->toArray());
                    }else if($request->segments == "stock"){
                        $combinedArray = array_merge($StockPortfolio->toArray());
                    }
                }else{
                    $combinedArray = array_merge($MetalsPortfolio->toArray(), $FOPortfolios->toArray(), $GlobalStockPortfolio->toArray() , $StockPortfolio->toArray());
                }
            }else{
                // Merge All Data
                if($request->segments=='all'){
                  $combinedArray = array_merge($Ledger->toArray());
                }else{
                    $combinedArray = [];
                }

            }
        }else{
            // Merge All Data
            if(!empty($request->segments) && $request->segments!='all'){
                if($request->segments == "global"){
                    $combinedArray = array_merge($GlobalStockPortfolio->toArray());
                }else if($request->segments == "fQ"){
                    $combinedArray = array_merge($FOPortfolios->toArray());
                }else if($request->segments == "metals"){
                    $combinedArray = array_merge($MetalsPortfolio->toArray());
                }else if($request->segments == "stock"){
                    $combinedArray = array_merge($StockPortfolio->toArray());
                }
            }else{
                $combinedArray = array_merge($Ledger->toArray(), $MetalsPortfolio->toArray(), $FOPortfolios->toArray(), $GlobalStockPortfolio->toArray() , $StockPortfolio->toArray());
            }
        }

        // Sort Data
        $dates = array_column($combinedArray, 'buy_date');
        array_multisort($dates, SORT_ASC, $combinedArray);

        $allData = $this->getStockName();
        if($request->ajax()){
            return view($this->activeTemplate . 'user.pl-reports-ajax',$data,compact('combinedArray','allData')); 
        }

        return view($this->activeTemplate . 'user.pl-reports',$data,compact('combinedArray','allData'));
    }

    public function plReportsAjax(Request $request){
        $fullUrl = $request->fullUrl();
        $pageTitle = 'PL Reports';
        $data['pageTitle'] = $pageTitle;
        $data['fullUrl'] = $request->fullUrl();

        $segments = "all";
        $type = "all";
        $symbol = 'all';
        $buyDate = 'all';
        $array = [];  

        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $array = explode('/' ,$request->buyDate);
            $dataFrom = $array[0];
            $dateTo = $array[1];
        }

        $Ledger = Ledger::select(['*', 'bought_date as buy_date'])->where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $Ledger->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $Ledger->whereBetween('bought_date',[$dataFrom,$dateTo]);
        }
        $Ledger = $Ledger->get();


        $MetalsPortfolio = MetalsPortfolio::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $MetalsPortfolio->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $MetalsPortfolio->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $MetalsPortfolio->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $MetalsPortfolio = $MetalsPortfolio->get();


        $FOPortfolios = FOPortfolios::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $FOPortfolios->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $FOPortfolios->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $FOPortfolios->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $FOPortfolios = $FOPortfolios->get();


        $GlobalStockPortfolio = GlobalStockPortfolio::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $GlobalStockPortfolio->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $GlobalStockPortfolio->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $GlobalStockPortfolio->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $GlobalStockPortfolio = $GlobalStockPortfolio->get();


        $StockPortfolio = StockPortfolio::where('user_id',auth()->user()->id);
        if(!empty($request->symbol) && $request->symbol!='all'){
            $StockPortfolio->where('stock_name',$request->symbol);
        }
        if(empty($request->symbol) && $request->symbol!='all' && empty($request->type) && $request->type!='all' && empty($request->symbol) && $request->symbol!='all'  && empty($request->buyDate) && $request->buyDate!='all'){
            $StockPortfolio->whereBetween('buy_date', [Carbon::now()->subMonth(6), Carbon::now()]);
        }
        if(!empty($request->buyDate) && $request->buyDate!='all'){
            $StockPortfolio->whereBetween('buy_date',[$dataFrom,$dateTo]);
        }
        $StockPortfolio = $StockPortfolio->get();

        // Type = Realised (Lagyer) , Unrelized = (Except Lagyer)
        if(!empty($request->type) && $request->type!='all'){
            if($request->type == "unrealized"){
                if(!empty($request->segments) && $request->segments!='all'){
                    if($request->segments == "global"){
                        $combinedArray = array_merge($GlobalStockPortfolio->toArray());
                    }else if($request->segments == "fQ"){
                        $combinedArray = array_merge($FOPortfolios->toArray());
                    }else if($request->segments == "metals"){
                        $combinedArray = array_merge($MetalsPortfolio->toArray());
                    }else if($request->segments == "stock"){
                        $combinedArray = array_merge($StockPortfolio->toArray());
                    }
                }else{
                    $combinedArray = array_merge($MetalsPortfolio->toArray(), $FOPortfolios->toArray(), $GlobalStockPortfolio->toArray() , $StockPortfolio->toArray());
                }
            }else{
                // Merge All Data
                if($request->segments=='all'){
                  $combinedArray = array_merge($Ledger->toArray());
                }else{
                    $combinedArray = [];
                }

            }
        }else{
            // Merge All Data
            if(!empty($request->segments) && $request->segments!='all'){
                if($request->segments == "global"){
                    $combinedArray = array_merge($GlobalStockPortfolio->toArray());
                }else if($request->segments == "fQ"){
                    $combinedArray = array_merge($FOPortfolios->toArray());
                }else if($request->segments == "metals"){
                    $combinedArray = array_merge($MetalsPortfolio->toArray());
                }else if($request->segments == "stock"){
                    $combinedArray = array_merge($StockPortfolio->toArray());
                }
            }else{
                $combinedArray = array_merge($Ledger->toArray(), $MetalsPortfolio->toArray(), $FOPortfolios->toArray(), $GlobalStockPortfolio->toArray() , $StockPortfolio->toArray());
            }
        }

        // Sort Data
        $dates = array_column($combinedArray, 'buy_date');
        array_multisort($dates, SORT_ASC, $combinedArray);

        $allData = $this->getStockName();
        return view($this->activeTemplate . 'user.pl-reports-ajax',$data,compact('combinedArray','allData')); 
    }

    public function omsConfig(){
        $pageTitle = 'OMS CONFIG';
        $data['pageTitle'] = $pageTitle;
        $brokers = BrokerApi::select('client_name','id')->where('user_id',auth()->user()->id)->get();
        $data['brokers'] = $brokers;
        $data['omsData'] = OmsConfig::where('user_id',auth()->user()->id)->with('broker:id,client_name')->paginate(50);
        return view($this->activeTemplate . 'user.oms-config',$data);
    }

    

    public function getPeCeSymbolNames(Request $request){
        $symbol = $request->symbol;
        $signal = $request->signal;
       

        $todayDate = date("Y-m-d");
        // $todayDate = date("2024-01-20");
        $data = \DB::connection('mysql_rm')->table($symbol)->select('*')->where(['date'=>$todayDate,'timeframe'=>$signal])->get(); 
        
        $atmData = [];
        foreach($data as $vvl){
            if(isset($vvl->atm) && ($vvl->atm=="ATM" || $vvl->atm=="ATM-1" || $vvl->atm=="ATM+1")){
                $atmData[] = $vvl;
            }
        }

        $fData = [];
        foreach($atmData as $val){
            $arrData = json_decode($val->data,true);   
            $CE = array_unique($arrData['CE']);
            $PE = $arrData['PE'];
            foreach ($CE as $k=>$item){
                $fData[] = [
                    'ce'=>$item,
                    'pe'=>$PE[$k]
                ];
            }
        }

        return response()->json(['s'=>1,'data'=>$fData]);
   
    }

    public function checkTradingSymbolExists($symbol){
        // if(!empty($symbol)){
        //     $cnt = ZerodhaInstrument::where('trading_symbol',$symbol)->count();
        //     if($cnt){
        //         return true;
        //     }
        //     return false;
        // }
        return true;
    }

    public function storeOmsConfig(Request $request){
        $txnType = '';
        // ce symbol
        if(!$this->checkTradingSymbolExists($request->ce_symbol_name)){
            $notify[] = ['error', 'Enter valid trading symbol'];
            return redirect()->back()->withNotify($notify);
        }
        if(!$this->checkTradingSymbolExists($request->pe_symbol_name)){
            $notify[] = ['error', 'Enter valid trading symbol'];
            return redirect()->back()->withNotify($notify);
        }
        $ce_symbol_name = $request->ce_symbol_name;
        $pe_symbol_name = $request->pe_symbol_name;
        $strategyName = $request->strategy_name;
        switch($request->strategy_name){
            case 'Short Straddle':
                $txnType = 'SELL';
            break;
            case 'Long Straddle':
                $txnType = 'BUY';
            break;
            case 'Buy CE':
                $txnType = 'BUY';
                $pe_symbol_name = null;
            break;
            case 'Buy PE':
                $txnType = 'BUY';
                $ce_symbol_name = null;
            break;
            case 'Sell CE':
                $txnType = 'SELL';
                $pe_symbol_name = null;
            break;
            case 'Sell PE':
                $txnType = 'SELL';
                $ce_symbol_name = null;
            break;
            case 'Bullish CE':
                $txnType = 'BUY';
                $pe_symbol_name = null;
                $strategyName = 'Bullish';
            break;
            case 'Bullish PE':
                $txnType = 'BUY';
                $ce_symbol_name = null;
                $strategyName = 'Bullish';
            break;
            case 'Bearish CE':
                $txnType = 'SELL';
                $pe_symbol_name = null;
                $strategyName = 'Bearish';
            break;
            case 'Bearish PE':
                $txnType = 'SELL';
                $ce_symbol_name = null;
                $strategyName = 'Bearish';
            break;
        }
        $ce_pyramid_1 = null;
        $ce_pyramid_2 = null;
        $ce_pyramid_3 = null;
        $pe_pyramid_1 = null;
        $pe_pyramid_2 = null;
        $pe_pyramid_3 = null;
        // if($request->order_type=="LIMIT"){
            $ce_quantity = $request->ce_quantity > 0 ? $request->ce_quantity : 0;
            $numbertodivise = $ce_quantity;
            $no=1;
            if($request->pyramid_percent==33){
                $no = 3;
            }elseif($request->pyramid_percent==50){
                $no = 2;
            }
            $pData = calculatePyramids($numbertodivise,$no);
            if($no==3){
                $ce_pyramid_1 = $pData[0];
                $ce_pyramid_2 = $pData[1];
                $ce_pyramid_3 = $pData[2];
            }
            if($no==2){
                $ce_pyramid_1 = $pData[0];
                $ce_pyramid_2 = $pData[1];
            }
            if($no==1){
                $ce_pyramid_1 = $pData[0];
            }
            //
            $pe_quantity = $request->pe_quantity >0 ? $request->pe_quantity : 0;
            $numbertodivise = $pe_quantity;
            $pData = calculatePyramids($numbertodivise,$no);
            if($no==3){
                $pe_pyramid_1 = $pData[0];
                $pe_pyramid_2 = $pData[1];
                $pe_pyramid_3 = $pData[2];
            }
            if($no==2){
                $pe_pyramid_1 = $pData[0];
                $pe_pyramid_2 = $pData[1];
            }
            if($no==1){
                $pe_pyramid_1 = $pData[0];
            }
        // }
        $omsObj = new OmsConfig();
        $omsObj->symbol_name = $request->symbol_name;
        $omsObj->signal_tf = $request->signal_tf;
        $omsObj->ce_symbol_name = $ce_symbol_name;
        $omsObj->pe_symbol_name = $pe_symbol_name;
        $omsObj->broker_api_id = $request->client_name;
        $omsObj->entry_point = $request->entry_point;
        $omsObj->strategy_name = $strategyName;
        $omsObj->product = $request->product;
        $omsObj->order_type = $request->order_type;
        $omsObj->pyramid_percent = $request->pyramid_percent;
        $omsObj->ce_pyramid_1 = $ce_pyramid_1 > 0 ? $ce_pyramid_1 : null;
        $omsObj->ce_pyramid_2 = $ce_pyramid_2 > 0 ? $ce_pyramid_2 : null;
        $omsObj->ce_pyramid_3 = $ce_pyramid_3 > 0 ? $ce_pyramid_3 : null;
        $omsObj->pe_pyramid_1 = $pe_pyramid_1 > 0 ? $pe_pyramid_1 : null;
        $omsObj->pe_pyramid_2 = $pe_pyramid_2 > 0 ? $pe_pyramid_2 : null;
        $omsObj->pe_pyramid_3 = $pe_pyramid_3 > 0 ? $pe_pyramid_3 : null;
        $omsObj->txn_type = $txnType;
        $omsObj->ce_quantity = $request->ce_quantity;
        $omsObj->pe_quantity = $request->pe_quantity;
        $omsObj->pyramid_freq = $request->pyramid_freq ?? 0;
        $omsObj->exit_1_qty = $request->exit_1_qty;
        $omsObj->exit_1_target = $request->exit_1_target;
        $omsObj->exit_2_qty = $request->exit_2_qty;
        $omsObj->exit_2_target = $request->exit_2_target;
        $omsObj->user_id = auth()->user()->id;
        $omsObj->status = $request->status;
        $omsObj->cron_run_at = $request->pyramid_freq > 0 ? date("Y-m-d H:i:s",strtotime('-'.$request->pyramid_freq.' minutes')) : date("Y-m-d H:i:s");
        $omsObj->save();
        $notify[] = ['success', 'Data added Successfully...'];
        return redirect()->back()->withNotify($notify);
    }

    public function getOmgConfigData(Request $request){
        $id = $request->id;

        $brokers = BrokerApi::select('client_name','id')->where('user_id',auth()->user()->id)->get();
        $data['brokers'] = $brokers;
        $data['omgData'] = OmsConfig::where(['id'=>$id,'user_id'=>auth()->user()->id])->first();
        $symbol = $data['omgData']->symbol_name;
        $signal = $data['omgData']->signal_tf;
        $todayDate = date("Y-m-d");
        $Symdata = \DB::connection('mysql_rm')->table($symbol)->select('*')->where(['date'=>$todayDate,'timeframe'=>$signal])->get(); 
        $atmData = [];
        foreach($Symdata as $vvl){
            if(isset($vvl->atm) && ($vvl->atm=="ATM" || $vvl->atm=="ATM-1" || $vvl->atm=="ATM+1")){
                $atmData[] = $vvl;
            }
        }
        $fData = [];
        foreach($atmData as $val){
            $arrData = json_decode($val->data,true);   
            $CE = array_unique($arrData['CE']);
            $PE = $arrData['PE'];
            foreach ($CE as $k=>$item){
                $fData[] = [
                    'ce'=>$item,
                    'pe'=>$PE[$k]
                ];
            }
        }
        $data['fData'] = $fData;

        return view($this->activeTemplate . 'user.get-omg-config-data',$data);
    }

    public function removeOmsConfig(Request $request){
        $id = $request->id;
        OmsConfig::where(['id'=>$id,'user_id'=>auth()->user()->id])->delete();
        $notify[] = ['success', 'Data removed Successfully...'];
        return to_route('user.portfolio.oms-config')->withNotify($notify);
    }

    // public function OptionAnalysis(Request $request){
    //     $pageTitle = 'Option Analysis';
    //     $symbolArr = allTradeSymbols();
    //     // For Chart 1
    //     $Atmtype1 = $request->atmRange2 ?? "ATM";
    //     $table1 =  $request->symbol2 ?? "CRUDEOIL";
    //     $timeFrame1 = $request->timeframe2 ? : 5;
    //     // For Chart 2
    //     $Atmtype2 = $Atmtype1;
    //     $table2 =  $table1;
    //     $timeFrame2 = $timeFrame1;
    //     // For Chart 3
    //     $Atmtype3 = $Atmtype1;
    //     $table3 =  $table1;
    //     $timeFrame3 = $timeFrame1;

    //     // For Chart 4
    //     $Atmtype4 = $Atmtype1;
    //     $table4 =  $table1;
    //     $timeFrame4 = $timeFrame1;

    //     // For Chart 5
    //     $Atmtype5 = $Atmtype1;
    //     $table5 =  $table1;
    //     $timeFrame5 = $timeFrame1;

    //     $todayDate = date("Y-m-d");
    //     // $Symdata = \DB::connection('mysql_rm')->table($symbol)->select('*')->where(['date'=>

    //     // For Chart 1
    //     $date = date("Y-m-d",strtotime('-5 days'));
    //     $data1 = \DB::connection('mysql_rm')->table($table1)->select('*')->where('timeframe',$timeFrame1)->whereDate('date','>',$date)->get();
    //     // For Chart 2
    //     $data2 = \DB::connection('mysql_rm')->table($table2)->select('*')->where('timeframe',$timeFrame2)->whereDate('date','>',$date)->get();
    //     // For Chart 3
    //     $data3 = \DB::connection('mysql_rm')->table($table3)->select('*')->where('timeframe',$timeFrame3)->whereDate('date','>',$date)->get();
    //     // For Chart 4
    //     $data4 = \DB::connection('mysql_rm')->table($table4)->select('*')->where('timeframe',$timeFrame4)->whereDate('date','>',$date)->get();
    //     // For Chart 5
    //     $data5 = \DB::connection('mysql_rm')->table($table5)->select('*')->where('timeframe',$timeFrame5)->whereDate('date','>',$date)->get();
     
    //     $fullUrl =  $request->fullUrl();

    //     return view($this->activeTemplate . 'user.option-analysis', compact('pageTitle','symbolArr','data1','data2','data3','data4','data5','Atmtype1','timeFrame1','table1','Atmtype2','timeFrame2','table2','Atmtype3','timeFrame3','table3','Atmtype4','timeFrame4','table4','Atmtype5','timeFrame5','table5','fullUrl'));
    // }

    public function OptionAnalysis(Request $request)
    {
        $pageTitle = 'Option Analysis';
        $symbolArr = allTradeSymbols();
        $fullUrl = $request->fullUrl();
        
        // Initialize default values
        $defaultSymbol = "CRUDEOIL";
        $defaultTimeFrame = 5;
        $defaultAtmType = "ATM";
        
        // Common parameters for all charts
        $Atmtype = $request->atmRange2 ?? $defaultAtmType;
        $table = $request->symbol2 ?? $defaultSymbol;
        $timeFrame = $request->timeframe2 ?? $defaultTimeFrame;
        $date = date("Y-m-d", strtotime('-5 days'));
        
        // Initialize empty collections for chart data
        $chartData = [
            'data1' => collect(),
            'data2' => collect(),
            'data3' => collect(),
            'data4' => collect(),
            'data5' => collect()
        ];
        
        try {
            // Get data for all charts in a single loop (more efficient)
            foreach ($chartData as $key => $_) {
                $chartData[$key] = \DB::connection('mysql_rm')
                    ->table($table)
                    ->select('*')
                    ->where('timeframe', $timeFrame)
                    ->whereDate('date', '>', $date)
                    ->get();
            }
        } catch (\Exception $e) {
            \Log::error("OptionAnalysis DB Error (mysql_rm): " . $e->getMessage());
            session()->flash('error', 'Could not load option analysis data. Please try again later.');
            
            if (config('app.debug')) {
                \Log::debug("Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all(),
                    'table' => $table
                ]);
            }
        }
        
        // Prepare view data
        $viewData = [
            'pageTitle' => $pageTitle,
            'symbolArr' => $symbolArr,
            'fullUrl' => $fullUrl,
            'Atmtype1' => $Atmtype,
            'timeFrame1' => $timeFrame,
            'table1' => $table,
            // Repeat for other charts (they all use same parameters in this case)
            'Atmtype2' => $Atmtype,
            'timeFrame2' => $timeFrame,
            'table2' => $table,
            'Atmtype3' => $Atmtype,
            'timeFrame3' => $timeFrame,
            'table3' => $table,
            'Atmtype4' => $Atmtype,
            'timeFrame4' => $timeFrame,
            'table4' => $table,
            'Atmtype5' => $Atmtype,
            'timeFrame5' => $timeFrame,
            'table5' => $table
        ];
        
        // Merge chart data with view data
        $viewData = array_merge($viewData, $chartData);
        
        return view($this->activeTemplate . 'user.option-analysis', $viewData);
    }

    // public function OptionAnalysisAjax(Request $request){
    //     $pageTitle = 'Option Analysis';
    //     $symbolArr = allTradeSymbols();
    //     // For Chart 1
    //     $Atmtype1 = $request->atmRange2 ?? "ATM";
    //     $table1 =  $request->symbol2 ?? "CRUDEOIL";
    //     $timeFrame1 = $request->timeframe2 ? : 5;
    //     // For Chart 2
    //     $Atmtype2 = $Atmtype1;
    //     $table2 =  $table1;
    //     $timeFrame2 = $timeFrame1;
    //     // For Chart 3
    //     $Atmtype3 = $Atmtype1;
    //     $table3 =  $table1;
    //     $timeFrame3 = $timeFrame1;

    //     // For Chart 4
    //     $Atmtype4 = $Atmtype1;
    //     $table4 =  $table1;
    //     $timeFrame4 = $timeFrame1;

    //     // For Chart 5
    //     $Atmtype5 = $Atmtype1;
    //     $table5 =  $table1;
    //     $timeFrame5 = $timeFrame1;

    //     $todayDate = date("Y-m-d");
    //     // $Symdata = \DB::connection('mysql_rm')->table($symbol)->select('*')->where(['date'=>

    //     // For Chart 1
    //     $data1 = \DB::connection('mysql_rm')->table($table1)->select('*')->where('timeframe',$timeFrame1)->get();
    //     // For Chart 2
    //     $data2 = \DB::connection('mysql_rm')->table($table2)->select('*')->where('timeframe',$timeFrame2)->get();
    //     // For Chart 3
    //     $data3 = \DB::connection('mysql_rm')->table($table3)->select('*')->where('timeframe',$timeFrame3)->get();
    //     // For Chart 4
    //     $data4 = \DB::connection('mysql_rm')->table($table4)->select('*')->where('timeframe',$timeFrame4)->get();
    //     // For Chart 5
    //     $data5 = \DB::connection('mysql_rm')->table($table5)->select('*')->where('timeframe',$timeFrame5)->get();
    //     $fullUrl =  $request->fullUrl();

    //     return view($this->activeTemplate . 'user.option-analysis-ajax', compact('pageTitle','symbolArr','data1','data2','data3','data4','data5','Atmtype1','timeFrame1','table1','Atmtype2','timeFrame2','table2','Atmtype3','timeFrame3','table3','Atmtype4','timeFrame4','table4','Atmtype5','timeFrame5','table5','fullUrl'));
    // }

    public function OptionAnalysisAjax(Request $request)
    {
        $pageTitle = 'Option Analysis';
        $symbolArr = allTradeSymbols();
        $fullUrl = $request->fullUrl();
        
        // Initialize default values
        $defaultSymbol = "CRUDEOIL";
        $defaultTimeFrame = 5;
        $defaultAtmType = "ATM";
        
        // Common parameters for all charts
        $Atmtype = $request->atmRange2 ?? $defaultAtmType;
        $table = $request->symbol2 ?? $defaultSymbol;
        $timeFrame = $request->timeframe2 ?? $defaultTimeFrame;
        
        // Initialize empty collections for chart data
        $chartData = [
            'data1' => collect(),
            'data2' => collect(),
            'data3' => collect(),
            'data4' => collect(),
            'data5' => collect()
        ];
        
        try {
            // Get data for all charts in a single loop (more efficient)
            foreach ($chartData as $key => $_) {
                $chartData[$key] = \DB::connection('mysql_rm')
                    ->table($table)
                    ->select('*')
                    ->where('timeframe', $timeFrame)
                    ->get();
            }
        } catch (\Exception $e) {
            \Log::error("OptionAnalysisAjax DB Error (mysql_rm): " . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not load option analysis data',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                    'table' => $table
                ], 500);
            }
            
            if (config('app.debug')) {
                \Log::debug("AJAX Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all(),
                    'table' => $table
                ]);
            }
        }
        
        // Prepare view data
        $viewData = [
            'pageTitle' => $pageTitle,
            'symbolArr' => $symbolArr,
            'fullUrl' => $fullUrl,
            'Atmtype1' => $Atmtype,
            'timeFrame1' => $timeFrame,
            'table1' => $table,
            // Repeat for other charts (they all use same parameters in this case)
            'Atmtype2' => $Atmtype,
            'timeFrame2' => $timeFrame,
            'table2' => $table,
            'Atmtype3' => $Atmtype,
            'timeFrame3' => $timeFrame,
            'table3' => $table,
            'Atmtype4' => $Atmtype,
            'timeFrame4' => $timeFrame,
            'table4' => $table,
            'Atmtype5' => $Atmtype,
            'timeFrame5' => $timeFrame,
            'table5' => $table
        ];
        
        // Merge chart data with view data
        $viewData = array_merge($viewData, $chartData);
        
        return view($this->activeTemplate . 'user.option-analysis-ajax', $viewData);
    }

    public function fetchTradeRecord(){
        // try {
            return response()->json($this->getTradeDeskData());
        // } catch (\Throwable $th) {
        //    return response()->json(
        //     [ 'data' => ['status'=>false] ]
        //    );
        // }
    }

    // public function calculateSuperTrendWithStrength($high, $low, $close,$period, $index, $multiplier = 3){
    //     $basicUpperBand = ($high[$period - 1] + $low[$period - 1]) / 2;
    //     $basicLowerBand = ($high[$period - 1] + $low[$period - 1]) / 2;
    //     $finalUpperBand = 0;
    //     $finalLowerBand = 0;
    //     $trend = "";
    //     $strength = "";
        
    //     // Calculate ATR
    //     $atr = [];
    //     $atr[0] = 0;
    //     for ($i = 1; $i < count($close); $i++) {
    //         $tr1 = max($high[$i] - $low[$i], abs($high[$i] - $close[$i - 1]), abs($low[$i] - $close[$i - 1]));
    //         $atr[$i] = ($atr[$i - 1] * ($period - 1) + $tr1) / $period;
    //     }
    
    //     // Calculate Super Trend
    //     // for ($i = $period; $i < count($close); $i++) {
    //         if(count($close) > $index){
    //             $basicUpperBand = ($high[$index] + $low[$index]) / 2 + $multiplier * $atr[$index];
    //             $basicLowerBand = ($high[$index] + $low[$index]) / 2 - $multiplier * $atr[$index];
                
    //             if ($basicUpperBand < $finalUpperBand || $close[$index - 1] > $finalUpperBand) {
    //                 $finalUpperBand = $basicUpperBand;
    //             } else {
    //                 $finalUpperBand = $finalUpperBand;
    //             }
                
    //             if ($basicLowerBand > $finalLowerBand || $close[$index - 1] < $finalLowerBand) {
    //                 $finalLowerBand = $basicLowerBand;
    //             } else {
    //                 $finalLowerBand = $finalLowerBand;
    //             }
        
    //             if ($close[$index] <= $finalUpperBand) {
    //                 $trend = 'Bullish';
    //                 $strength = ($finalUpperBand - $close[$index]) / $atr[$index];
    //             } elseif ($close[$index] >= $finalLowerBand) {
    //                 $trend = 'Bearish';
    //                 $strength = ($close[$index] - $finalLowerBand) / $atr[$index];
    //             }
    //         }
            
    //     // } 
    
    //     return ['trend' => $trend, 'strength' => $strength];
    // }

    // public function calculateHeikinAshi(){
    //     set_time_limit(0);
    //     $previousClose = null;
    //     $period = 21;
    //     $multiplier = 3;
    //     $highArr = array();
    //     $lowArr = array();
    //     $closeArr = array();
    //     $historicalData = AngleHistoricalApi::get()->toArray();

    //     foreach ($historicalData as $key => $historical) {
    //         // dd($historical['timestamp'])->format('');
         
    //         $open = ($historical['open'] + $historical['close']) / 2;
    //         $close = ($historical['open'] + $historical['high'] + $historical['low'] + $historical['close']) / 4;

    //         if ($previousClose !== null) {
    //             $high = max($historical['high'], $open, $close);
    //             $low = min($historical['low'], $open, $close);
    //         } else {
    //             $high = $historical['high'];
    //             $low = $historical['low'];
    //         }           

    //         array_push($highArr,$high);
    //         array_push($lowArr,$low);
    //         array_push($closeArr,$close);
    //         $newData = new AngleOhlcData;
    //         $newData->historical_id = $historical['id'];
    //         $newData->date = $historical['timestamp'];
    //         $newData->new_open = $open;
    //         $newData->new_high = $high;
    //         $newData->new_low = $low;
    //         $newData->new_close = $close;
           
    //         $totalRecord = AngleOhlcData::count();
    //         if($totalRecord >= $period){
    //             $val = $this->calculateSuperTrendWithStrength($highArr,$lowArr,$closeArr,$period,$key-1);
    //             $newData->trend = $val['trend'];
    //             $newData->strength = $val['strength'];
    //         }

    //         $newData->save();
    //         $previousClose = $close;

    //        if($key % 100 == 0){
    //             sleep(4);
    //        }
    //     }

    //     return "Completed";die;

    // }

    // function calculateSuperTrendWithStrength($high, $low, $close, $multiplier = 3, $period = 21) {
    //     $basicUpperBand = ($high[$period - 1] + $low[$period - 1]) / 2;
    //     $basicLowerBand = ($high[$period - 1] + $low[$period - 1]) / 2;
    //     $finalUpperBand = 0;
    //     $finalLowerBand = 0;
    //     $trend = [];
    //     $strength = [];
        
    //     // Calculate ATR
    //     $atr = [];
    //     $atr[0] = 0;
    //     for ($i = 1; $i < count($close); $i++) {
    //         $tr1 = max($high[$i] - $low[$i], abs($high[$i] - $close[$i - 1]), abs($low[$i] - $close[$i - 1]));
    //         $atr[$i] = ($atr[$i - 1] * ($period - 1) + $tr1) / $period;
    //     }
    
    //     // Calculate Super Trend
    //     for ($i = $period; $i < count($close); $i++) {
    //         $basicUpperBand = ($high[$i] + $low[$i]) / 2 + $multiplier * $atr[$i];
    //         $basicLowerBand = ($high[$i] + $low[$i]) / 2 - $multiplier * $atr[$i];
            
    //         if ($basicUpperBand < $finalUpperBand or $close[$i - 1] > $finalUpperBand) {
    //             $finalUpperBand = $basicUpperBand;
    //         } else {
    //             $finalUpperBand = $finalUpperBand;
    //         }
            
    //         if ($basicLowerBand > $finalLowerBand or $close[$i - 1] < $finalLowerBand) {
    //             $finalLowerBand = $basicLowerBand;
    //         } else {
    //             $finalLowerBand = $finalLowerBand;
    //         }
    
    //         if ($close[$i] <= $finalUpperBand) {
    //             $trend[] = 'Bullish';
    //             $strength[] = ($finalUpperBand - $close[$i]) / $atr[$i];
    //         } elseif ($close[$i] >= $finalLowerBand) {
    //             $trend[] = 'Bearish';
    //             $strength[] = ($close[$i] - $finalLowerBand) / $atr[$i];
    //         }
    //     }
    
    //     return ['trend' => $trend, 'strength' => $strength];
    // }

    // Get New high,low,close,etc
    public function getNewData($ohlcData){
        $heikinAshiData = [];
        $previousClose = null;
    
        foreach ($ohlcData as $ohlc) {
            $open = ($ohlc['open'] + $ohlc['close']) / 2;
            $close = ($ohlc['open'] + $ohlc['high'] + $ohlc['low'] + $ohlc['close']) / 4;
    
            if ($previousClose !== null) {
                $high = max($ohlc['high'], $open, $close);
                $low = min($ohlc['low'], $open, $close);
            } else {
                $high = $ohlc['high'];
                $low = $ohlc['low'];
            }
    
            $heikinAshiData[] = [
                'date' => $ohlc['timestamp'],
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close
            ];
    
            $previousClose = $close;
        }
    
        return $heikinAshiData;
    }

    public function calculateSuperTrendWithStrength2($high, $low, $close, $multiplier = 3, $period = 21){
        $basicUpperBand = ($high[$period - 1] + $low[$period - 1]) / 2;
        $basicLowerBand = ($high[$period - 1] + $low[$period - 1]) / 2;
        $finalUpperBand = 0;
        $finalLowerBand = 0;
        $trend = [];
        $strength = [];
        
        // Calculate ATR
        $atr = [];
        $atr[0] = 0;
        for ($i = 1; $i < count($close); $i++) {
            $tr1 = max($high[$i] - $low[$i], abs($high[$i] - $close[$i - 1]), abs($low[$i] - $close[$i - 1]));
            $atr[$i] = ($atr[$i - 1] * ($period - 1) + $tr1) / $period;
        }
    
        // Calculate Super Trend
        for ($i = $period; $i < count($close); $i++) {
            $basicUpperBand = ($high[$i] + $low[$i]) / 2 + $multiplier * $atr[$i];
            $basicLowerBand = ($high[$i] + $low[$i]) / 2 - $multiplier * $atr[$i];
            
            if ($basicUpperBand < $finalUpperBand or $close[$i - 1] > $finalUpperBand) {
                $finalUpperBand = $basicUpperBand;
            } else {
                $finalUpperBand = $finalUpperBand;
            }
            
            if ($basicLowerBand > $finalLowerBand or $close[$i - 1] < $finalLowerBand) {
                $finalLowerBand = $basicLowerBand;
            } else {
                $finalLowerBand = $finalLowerBand;
            }
    
            if ($close[$i] <= $finalUpperBand) {
                $trend[$i] = 'Bullish';
                $strength[$i] = ($finalUpperBand - $close[$i]) / $atr[$i];
            } elseif ($close[$i] >= $finalLowerBand) {
                $trend[$i] = 'Bearish';
                $strength[$i] = ($close[$i] - $finalLowerBand) / $atr[$i];
            }
        }
    
        return ['trend' => $trend, 'strength' => $strength];
    }


    public function storenewData(){
        set_time_limit(0);
        $historicalData = AngleHistoricalApi::get()->toArray();
        $newData = $this->getNewData($historicalData);

        $high = array_map(function($val){
            return $val['high'];
        },$newData);

        $low = array_map(function($val){
            return $val['low'];
        },$newData);

        $close = array_map(function($val){
            return $val['close'];
        },$newData);

        $open = array_map(function($val){
            return $val['open'];
        },$newData);
        $return =  $this->calculateSuperTrendWithStrength2($high,$low,$close);

        foreach ($historicalData as $key => $value) {
            $ohlcNew = new AngleOhlcData;
            $ohlcNew->historical_id = $value['id'];
            $ohlcNew->symbol = $value['symbol'];
            $ohlcNew->date = $value['timestamp'];
            $ohlcNew->new_open = $open[$key];
            $ohlcNew->new_high = $high[$key];
            $ohlcNew->new_low = $low[$key];
            $ohlcNew->new_close = $close[$key];
            $ohlcNew->trend = $return['trend'][$key] ?? null;  
            $ohlcNew->strength = number_format($return['strength'][$key],2,",",".") ?? null; 
            $ohlcNew->save();

            if($key % 100 == 0){
                sleep(4);
            }
        }
        dd('Completed');die;
    }

    public function updateOmsConfig(Request $request){
        $txnType = '';
        // ce symbol
        if(!$this->checkTradingSymbolExists($request->ce_symbol_name_up)){
            $notify[] = ['error', 'Enter valid trading symbol'];
            return redirect()->back()->withNotify($notify);
        }
        if(!$this->checkTradingSymbolExists($request->pe_symbol_name_up)){
            $notify[] = ['error', 'Enter valid trading symbol'];
            return redirect()->back()->withNotify($notify);
        }
        $ce_symbol_name = $request->ce_symbol_name_up;
        $pe_symbol_name = $request->pe_symbol_name_up;
        $strategyName = $request->strategy_name_up;
        switch($request->strategy_name_up){
            case 'Short Straddle':
                $txnType = 'SELL';
            break;
            case 'Long Straddle':
                $txnType = 'BUY';
            break;
            case 'Buy CE':
                $txnType = 'BUY';
                $pe_symbol_name = null;
            break;
            case 'Buy PE':
                $txnType = 'BUY';
                $ce_symbol_name = null;
            break;
            case 'Sell CE':
                $txnType = 'SELL';
                $pe_symbol_name = null;
            break;
            case 'Sell PE':
                $txnType = 'SELL';
                $ce_symbol_name = null;
            break;
            case 'Bullish CE':
                $txnType = 'BUY';
                $pe_symbol_name = null;
                $strategyName = 'Bullish';
            break;
            case 'Bullish PE':
                $txnType = 'BUY';
                $ce_symbol_name = null;
                $strategyName = 'Bullish';
            break;
            case 'Bearish CE':
                $txnType = 'SELL';
                $pe_symbol_name = null;
                $strategyName = 'Bearish';
            break;
            case 'Bearish PE':
                $txnType = 'SELL';
                $ce_symbol_name = null;
                $strategyName = 'Bearish';
            break;
        }
        $ce_pyramid_1 = null;
        $ce_pyramid_2 = null;
        $ce_pyramid_3 = null;
        $pe_pyramid_1 = null;
        $pe_pyramid_2 = null;
        $pe_pyramid_3 = null;
        // if($request->order_type=="LIMIT"){
            $ce_quantity = $request->ce_quantity_up > 0 ? $request->ce_quantity_up : 0;
            $numbertodivise = $ce_quantity;
            $no=1;
            if($request->pyramid_percent_up==33){
                $no = 3;
            }elseif($request->pyramid_percent_up==50){
                $no = 2;
            }
            $pData = calculatePyramids($numbertodivise,$no);
            if($no==3){
                $ce_pyramid_1 = $pData[0];
                $ce_pyramid_2 = $pData[1];
                $ce_pyramid_3 = $pData[2];
            }
            if($no==2){
                $ce_pyramid_1 = $pData[0];
                $ce_pyramid_2 = $pData[1];
            }
            if($no==1){
                $ce_pyramid_1 = $pData[0];
            }
            //
            $pe_quantity = $request->pe_quantity_up >0 ? $request->pe_quantity_up : 0;
            $numbertodivise = $pe_quantity;
            $pData = calculatePyramids($numbertodivise,$no);
            if($no==3){
                $pe_pyramid_1 = $pData[0];
                $pe_pyramid_2 = $pData[1];
                $pe_pyramid_3 = $pData[2];
            }
            if($no==2){
                $pe_pyramid_1 = $pData[0];
                $pe_pyramid_2 = $pData[1];
            }
            if($no==1){
                $pe_pyramid_1 = $pData[0];
            }
        // }
        $omsObj = OmsConfig::find($request->id);
        $omsObj->symbol_name = $request->symbol_name_up;
        $omsObj->signal_tf = $request->signal_tf_up;
        $omsObj->ce_symbol_name = $ce_symbol_name;
        $omsObj->pe_symbol_name = $pe_symbol_name;
        $omsObj->broker_api_id = $request->client_name_up;
        $omsObj->entry_point = $request->entry_point_up;
        $omsObj->strategy_name = $strategyName;
        $omsObj->product = $request->product_up;
        $omsObj->order_type = $request->order_type_up;
        $omsObj->pyramid_percent = $request->pyramid_percent_up;
        $omsObj->ce_pyramid_1 = $ce_pyramid_1 > 0 ? $ce_pyramid_1 : null;
        $omsObj->ce_pyramid_2 = $ce_pyramid_2 > 0 ? $ce_pyramid_2 : null;
        $omsObj->ce_pyramid_3 = $ce_pyramid_3 > 0 ? $ce_pyramid_3 : null;
        $omsObj->pe_pyramid_1 = $pe_pyramid_1 > 0 ? $pe_pyramid_1 : null;
        $omsObj->pe_pyramid_2 = $pe_pyramid_2 > 0 ? $pe_pyramid_2 : null;
        $omsObj->pe_pyramid_3 = $pe_pyramid_3 > 0 ? $pe_pyramid_3 : null;
        $omsObj->txn_type = $txnType;
        $omsObj->ce_quantity = $request->ce_quantity_up;
        $omsObj->pe_quantity = $request->pe_quantity_up;
        $omsObj->pyramid_freq = $request->pyramid_freq_up;
        $omsObj->user_id = auth()->user()->id;
        $omsObj->status = $request->status;
        // $omsObj->is_api_pushed = 0;
        // $omsObj->last_time = null;
        $omsObj->cron_run_at = $request->pyramid_freq_up > 0 ? date("Y-m-d H:i:s",strtotime('-'.$request->pyramid_freq_up.' minutes')) : date("Y-m-d H:i:s");
        $omsObj->save();
        $notify[] = ['success', 'Data updated Successfully...'];
        return redirect()->back()->withNotify($notify);
    }
    

    // public function watchList(Request $request){

    //     $pageTitle = "Watch List";
        
    //     $symbolArr = allTradeSymbols();
    //     $todayDate = date("Y-m-d");
    //     // $todayDate = date("2024-02-28");
    //     $stockName = $request->stock_name;
    //     $timeFrame = $request->time_frame ? : 5;
    //     $allSymbols = [];
    //     foreach ($symbolArr as $key => $v) {
    //         $data = \DB::connection('mysql_rm')->table($v)->select('*')->where(['date' => $todayDate, 'timeframe' => $timeFrame])->get();
    //         $atmData = [];
    //         foreach ($data as $vvl) {
    //             if (isset($vvl->atm) && $vvl->atm == 'ATM') {
    //                 $atmData[] = $vvl;
    //             }
    //         }
    //         foreach ($atmData as $val) {
    //             array_push($allSymbols,$val->ce);
    //             array_push($allSymbols,$val->pe);
    //         }
    //     }

    //     $zehrodha = ZerodhaInstrument::whereIN('trading_symbol',$allSymbols)->get();
    //     $MCXpayload = [];
    //     $NFOpayload = [];
    //     if($zehrodha != NULL){
    //         foreach ($zehrodha as $key => $value) {
    //             if($value->exchange == "MCX"){
    //                 array_push($MCXpayload,$value->exchange_token);
    //             }else if($value->exchange == "NFO"){
    //                 array_push($NFOpayload,$value->exchange_token);
    //             }
    //         }
    //     }

    //     $payload = [
    //         'MCX'=>$MCXpayload,
    //         'NFO'=>$NFOpayload
    //     ];

    //     $payload = json_encode($payload,true);
    //     $respond = $this->getWatchListRecords($payload);
    //     if(isset($respond)){
    //         if($respond['status'] == true){
    //             $finalResponse = $respond['data']['fetched'];
    //         }else{
    //             $finalResponse = false;
    //         }
    //     }else{
    //         $finalResponse = false;
    //     }

    //     // $data = StoreMarketData::whereDate('created_at', now()->today())->where(function($q){
    //     //     $q->where('exchange','MCX')->orWhere('exchange','NFO');
    //     // })->GROUPBY('token')->get();
    //     // dd($data);
    //     // $MCXpayload = [];
    //     // $NFOpayload = [];
    //     // if($data != NULL){
    //     //     foreach ($data as $key => $value) {
    //     //         if($value->exchange == "MCX"){
    //     //             array_push($MCXpayload,$value->token);
    //     //         }else if($value->exchange == "NFO"){
    //     //             array_push($NFOpayload,$value->token);
    //     //         }
    //     //     }
    //     // }

    //     // $MCXpayload = array_unique($MCXpayload);
    //     // $NFOpayload = array_unique($NFOpayload);

    //     // // dd(count($MCXpayload)).'--'.count($NFOpayload);

    //     // $MCXpayload = array_chunk($MCXpayload, 10);
    //     // $NFOpayload = array_chunk($NFOpayload , 10);
        
    //     // $finalResponse = [];
    //     // foreach ($NFOpayload as $key => $value) {
    //     //     $payload = [
    //     //         'NFO'=>$value
    //     //     ];
            
    //     //     $payload = json_encode($payload,true);
    //     //     $respond = $this->getWatchListRecords($payload);

    //     //     if($respond != NULL){
    //     //         if($respond['status'] == true){
    //     //             $fetchedData = $respond['data']['fetched'];
    //     //             array_unshift($finalResponse,$fetchedData);
    //     //         }
    //     //     }else{
    //     //         $payload = json_encode($payload,true);
    //     //         $respond = $this->getWatchListRecords($payload);
    //     //         if($respond != NULL){
    //     //             if($respond['status'] == true){
    //     //                 $fetchedData = $respond['data']['fetched'];
    //     //                 array_unshift($finalResponse,$fetchedData);
    //     //             }
    //     //         }
    //     //     }
    //     // }

    //     // foreach ($MCXpayload as $key => $value) {
    //     //     $payload = [
    //     //         'MCX'=>$value
    //     //     ];

    //     //     $payload = json_encode($payload,true);
    //     //     $respond = $this->getWatchListRecords($payload);
    //     //     if($respond != NULL){
    //     //         if($respond['status'] == true){
    //     //             $fetchedData = $respond['data']['fetched'];
    //     //             array_unshift($finalResponse,$fetchedData);
    //     //         }
    //     //     }else{
    //     //         $payload = json_encode($payload,true);
    //     //         $respond = $this->getWatchListRecords($payload);
    //     //         if($respond != NULL){
    //     //             if($respond['status'] == true){
    //     //                 $fetchedData = $respond['data']['fetched'];
    //     //                 array_unshift($finalResponse,$fetchedData);
    //     //             }
    //     //         }
    //     //     }
    //     // }
    //     // $finalResponse = call_user_func_array('array_merge', $finalResponse);

     
    //     $fullUrl = $request->fullUrl();

    //     if($request->ajax()){
    //         // return 'NO_DATA';
    //         if(!empty($finalResponse)){
    //             return view($this->activeTemplate . 'user.watch-list-ajax',compact('pageTitle','fullUrl','finalResponse'));
    //         }
    //         return 'NO_DATA';
            
    //     }

    //     return view($this->activeTemplate . 'user.watch-list',compact('pageTitle','finalResponse','fullUrl'));
    // }

    // public function oiBuildupNew(Request $request)
    // {
    //     $pageTitle = "Open Interest Buildup Data New";
    //     return view($this->activeTemplate . 'user.oi_buildup.index_new', compact('pageTitle'));
    // }

    public function oiBuildupNew()
    {
        $connection = DB::connection('mysql_oi_buildup');

        // Get today's main data (oi_buildup_data table)
        $data = $connection->table('oi_buildup_data')->get();

        // For PCR: fetch both PE and CE option data from options_atm_data
        $optionData = $connection->table('options_atm_data')->get();

        // For volume trend: get past 7-day data from dynamic table
        $history = $connection->table('7_day_data_782_symbols_04082025')->get();

        $results = [];

        foreach ($data as $item) {
            $historyData = $history->where('symbol', $item->symbol)->sortByDesc('created_at')->values();
            $prevClose = $historyData[1]->ltp ?? 0;
            $ltpChange = OiAnalyzer::calculateLtpChange($item->ltp, $prevClose);

            $currentOi = $item->oi ?? 0;
            $prevOi = $historyData[1]->oi ?? 0;
            $oiChange = $currentOi - $prevOi;

            $oiCategory = OiAnalyzer::inferOiCategory($ltpChange, $oiChange);
            $pcr = OiAnalyzer::calculatePcr($optionData);
            $volumeTrend = OiAnalyzer::calculateVolumeTrend($historyData, $item->volume ?? 0);
            $signal = OiAnalyzer::generateSignal($oiCategory, $pcr, $volumeTrend);
            $score = OiAnalyzer::calculateScore($signal);

            $results[] = (object) [
                'symbol' => $item->symbol,
                'ltp' => $item->ltp,
                'ltp_change' => round($ltpChange, 2),
                'oi_change' => $oiChange,
                'oi_category' => $oiCategory,
                'pcr' => $pcr,
                'volume_trend' => $volumeTrend,
                'signal' => $signal,
                'score' => $score,
            ];
        }

        $pageTitle = "Open Interest Buildup Data";
        return view($this->activeTemplate . 'user.oi_buildup.index_new', compact('pageTitle','results'));
    }

    public function oiBuildupFresh(Request $request)
    {
        $pageTitle = "Open Interest Buildup Fresh";

        $symbols = Cache::remember('oi_symbols', 300, function () {
            return OiBuildUp::select('symbol')
                ->distinct()
                ->orderBy('symbol')
                ->pluck('symbol');
        });

        return view($this->activeTemplate . 'user.oi_buildup.fresh', compact('pageTitle', 'symbols'));
    }

    // Add this method to your controller
    public function showOiTransitions(Request $request)
    {
        $pageTitle = "Fresh OI Buildup Transitions";
        
        // Fetch all distinct symbols for dropdown filters
        $symbols = OiBuildUp::select('symbol')
            ->distinct()
            ->orderBy('symbol', 'asc')
            ->pluck('symbol');
        
        // Fetch all distinct types for dropdown filters
        $types = OiBuildUp::select('type')
            ->distinct()
            ->whereNotNull('type')
            ->orderBy('type', 'asc')
            ->pluck('type');
        
        $symbol = $request->get('symbol');
        $type = $request->get('type');
        
        return view($this->activeTemplate . 'user.oi_buildup.transition', compact('symbols', 'types', 'symbol', 'type', 'pageTitle'));
    }

    public function oiBuildup(Request $request)
    {
        $pageTitle = "Open Interest Buildup Data";
        return view($this->activeTemplate . 'user.oi_buildup.index', compact('pageTitle'));
    }

    public function oiBuildupDetail($symbol, $type, Request $request)
    {
        $pageTitle = "OI Buildup $type - $symbol";
        $filter = $request->get('filter', 'today');

        $query = OiBuildUp::where('symbol', $symbol)
            ->where('type', $type);

        if ($filter === 'today') {
            $query->whereDate('created_at', now());
        } elseif ($filter === '1d') {
            $query->where('created_at', '>=', now()->subDay());
        } elseif ($filter === '5d') {
            $query->where('created_at', '>=', now()->subDays(5));
        }

        // ✅ Filter only rows where minute is multiple of 5
        // $query->whereRaw('MINUTE(created_at) % 5 = 0');

        // Optional: also ensure no duplicate for same 5-min slot (latest per 5-min block)
        // $query->orderBy('created_at', 'desc');

        $rows = $query->get();

        return view($this->activeTemplate . 'user.oi_buildup.detail', compact(
            'rows', 'symbol', 'type', 'pageTitle', 'filter'
        ));
    }

    public function watchList(Request $request){
        $pageTitle = "Watch List";
        $pagination = true;
        $todayDate  = date("Y-m-d");
        $symbolName = !empty($request->symbol_name) ? $request->symbol_name : '';
        $lastDateData = WishlistData::select('created_at')->orderBy('id','DESC')->first();
        if($lastDateData){
            $todayDate = date("Y-m-d",strtotime($lastDateData->created_at));
        }
        // echo $todayDate;die;
        
        $finalResponse = WishlistData::whereDate('created_at', $todayDate);
        if(!empty($symbolName)){
            $finalResponse->where('symbol_name','LIKE',$symbolName."%");
        }
        $finalResponse = $finalResponse->orderBy('symbol_name','ASC')->groupBy('symbol_name')->paginate(50);
        // dd($finalResponse);
        // if(!count($finalResponse)){
        //     $pagination = false;
        //     $finalResponse = WishlistData::orderBy('id','DESC')->limit(50)->get();
        // }
        $fullUrl = $request->fullUrl();
        $symbolArr = allTradeSymbols();
        return view($this->activeTemplate . 'user.watch-list',compact('pageTitle','finalResponse','fullUrl','pagination','symbolArr','symbolName'));
    }
    
    public function watchListAjax(Request $request){
        $pageTitle = "Watch List";
        $pagination = true;
        $todayDate  = date("Y-m-d");
        $symbolName = !empty($request->symbol_name) ? $request->symbol_name : '';
        $lastDateData = WishlistData::select('created_at')->orderBy('id','DESC')->first();
        if($lastDateData){
            $todayDate = date("Y-m-d",strtotime($lastDateData->created_at));
        }
        $finalResponse = WishlistData::whereDate('created_at', $todayDate);
        if(!empty($symbolName)){
            $finalResponse->where('symbol_name','LIKE',$symbolName."%");
        }
        $finalResponse = $finalResponse->orderBy('symbol_name','ASC')->groupBy('symbol_name')->paginate(50);
        // if(!count($finalResponse)){
        //     $pagination = false;
        //     $finalResponse = WishlistData::orderBy('id','DESC')->limit(50)->get();
        // }
        $fullUrl = $request->fullUrl();
        if(!empty($finalResponse)){
            return view($this->activeTemplate . 'user.watch-list-ajax',compact('pageTitle','fullUrl','finalResponse','pagination'));
        }
        return 'NO_DATA';
    }

    public function buywishlist(Request $request){
        $request->validate([
            'price'=>'required',
            'quantity'=>'required',
            'token'=>'required',
            'symbol'=>'required',
            'ltp'=>'required',
            'type'=>'required',
            'exchange'=>'required',
            'order_type'=>'required'
        ]);
        $userId = \Auth::id();
        $status = "executed";
        if($request->order_type == "limit"){
            $status = "pending";
        }

        // / CHECK CONDITION FOR SELL
        if($request->type == "SELL"){
            $watchTradePosition = WatchTradePosition::Where('token',$request->token)->WHERE('user_id',$userId)->where('type','BUY')->first();
            if($watchTradePosition){
                if($watchTradePosition->buy_quantity < $request->quantity){
                    $notify[] = ['error', 'You Donot Have Enough Quantity To Sell...'];
                    return redirect()->back()->withNotify($notify);
                }
            }
        }

        // Check For Previous BUY OR SELL FOR A PARTICULAR STOCK
        $makeAvgPrice = WatchList::WHERE('status','executed')->Where('token',$request->token)->WHERE('user_id',$userId)->get();
        $totalBuyPrice = 0;
        $totalBuyQuantity = 0;
        $totalSellPrice = 0;
        $totalSellQuantity = 0;
        if(count($makeAvgPrice)){
            foreach ($makeAvgPrice as $key => $value) {
                if($value->type == "BUY"){
                    $totalBuyPrice += ($value->quantity * $value->buy_price);
                    $totalBuyQuantity += $value->quantity;
                }
                if($value->type == "SELL"){
                    $totalSellPrice += ($value->quantity * $value->buy_price);
                    $totalSellQuantity += $value->quantity;
                }
            }
        }
        // For CURRENT RECORD
        if ($request->type == "BUY") {
            $totalBuyPrice += ($request->quantity * $request->price);
            $totalBuyQuantity += $request->quantity;
        }
        if ($request->type == "SELL") {
            $totalSellPrice += ($request->quantity * $request->price);
            $totalSellQuantity += $request->quantity;
        }
        if($totalBuyQuantity > 0){
            $BuyavgPrice = round($totalBuyPrice / $totalBuyQuantity,2);
        }else{
            $BuyavgPrice = 0;
        }
        if($totalSellQuantity > 0){
            $SellavgPrice = round($totalSellPrice / $totalSellQuantity,2);
        }else{
            $SellavgPrice = 0;
        }
        $netChange = ($totalBuyQuantity * $totalBuyPrice) - ($totalSellPrice * $totalSellQuantity);
        $orderId = "WL".strtotime('d-m-y h:i:s').rand(100,10000000).rand(100,10000000);
        $order = new WatchList;
        $order->order_id = $orderId;
        $order->user_id =  $userId;
        $order->buy_price = $request->price;
        $order->exchange = $request->exchange;
        $order->quantity = $request->quantity;
        $order->token = $request->token;
        $order->symbol = $request->symbol;
        $order->type = $request->type;
        $order->ltp = $request->ltp;
        $order->order_type = $request->order_type;
        $order->status = $status;
        $order->save();
        // Watch Trade Position
        if($status == "executed"){
            if($request->type == "BUY"){
                $watchTradePosition = WatchTradePosition::Where('token',$request->token)->WHERE('user_id',$userId)->where('type','BUY')->first();
                if($watchTradePosition != NULL){
                    $watchTradePosition->buy_quantity = $totalBuyQuantity;
                    $watchTradePosition->buy_price = $BuyavgPrice;
                    $watchTradePosition->net_change = $netChange;
                    $watchTradePosition->sell_quantity = 0;
                    $watchTradePosition->sell_price = 0;
                    $watchTradePosition->ltp = $request->ltp;
                    $watchTradePosition->save();
                }else{
                    $tradePostion = new WatchTradePosition;
                    $tradePostion->user_id = $userId;
                    $tradePostion->token = $request->token;
                    $tradePostion->symbol = $request->symbol;
                    $tradePostion->exchange = $request->exchange;
                    $tradePostion->buy_quantity = $totalBuyQuantity;
                    $tradePostion->buy_price = $BuyavgPrice;
                    $tradePostion->sell_quantity = 0;
                    $tradePostion->sell_price = 0;
                    $tradePostion->ltp = $request->ltp;
                    $tradePostion->net_change = $netChange;
                    $tradePostion->type="BUY";
                    $tradePostion->save();
                }
            }else if($request->type == "SELL"){
                $watchTradePosition = WatchTradePosition::Where('token',$request->token)->WHERE('user_id',$userId)->where('type','SELL')->first();
                $newData = WatchTradePosition::Where('token',$request->token)->WHERE('user_id',$userId)->where('type','BUY')->first();
                if($newData){
                  $newData->buy_quantity = $totalBuyQuantity-$request->quantity;
                  $newData->save();
                }
                if($watchTradePosition != NULL){
                    $watchTradePosition->buy_quantity = 0;
                    $watchTradePosition->buy_price = 0;
                    $watchTradePosition->net_change = $netChange;
                    $watchTradePosition->sell_quantity = $totalSellQuantity;
                    $watchTradePosition->sell_price = $SellavgPrice;
                    $watchTradePosition->ltp = $request->ltp;
                    $watchTradePosition->save();
                }else{
                    $tradePostion = new WatchTradePosition;
                    $tradePostion->user_id = $userId;
                    $tradePostion->token = $request->token;
                    $tradePostion->symbol = $request->symbol;
                    $tradePostion->exchange = $request->exchange;
                    $tradePostion->buy_quantity = 0;
                    $tradePostion->buy_price = 0;
                    $tradePostion->sell_quantity = $totalSellQuantity;
                    $tradePostion->sell_price = $SellavgPrice;
                    $tradePostion->ltp = $request->ltp;
                    $tradePostion->net_change = $netChange;
                    $tradePostion->type="SELL";
                    $tradePostion->save();
                }
            }
            // if($watchTradePosition != NULL){
            //     $watchTradePosition->buy_quantity = $totalBuyQuantity;
            //     $watchTradePosition->buy_price = $BuyavgPrice;
            //     $watchTradePosition->net_change = $netChange;
            //     $watchTradePosition->sell_quantity = $totalSellQuantity;
            //     $watchTradePosition->sell_price = $SellavgPrice;
            //     $watchTradePosition->ltp = $request->ltp;
            //     $watchTradePosition->save();
            // }else{
            //     $tradePostion = new WatchTradePosition;
            //     $tradePostion->user_id = $userId;
            //     $tradePostion->token = $request->token;
            //     $tradePostion->symbol = $request->symbol;
            //     $tradePostion->exchange = $request->exchange;
            //     $tradePostion->buy_quantity = $totalBuyQuantity;
            //     $tradePostion->buy_price = $BuyavgPrice;
            //     $tradePostion->sell_quantity = $totalSellQuantity;
            //     $tradePostion->sell_price = $SellavgPrice;
            //     $tradePostion->ltp = $request->ltp;
            //     $tradePostion->net_change = $netChange;
            //     $tradePostion->save();
            // }
        }
        $notify[] = ['success', 'Order Placed Successfully'];
        return back()->withNotify($notify);
    }

    // public function fetchwatchList(Request $request){
        // dd(json_encode($request->all()));
        // try {
            // $symbolArr = allTradeSymbols();
            // $todayDate = date("Y-m-d");
            // $stockName = $request->stock_name;
            // $timeFrame = $request->time_frame ? : 5;
            // $allSymbols = [];
            // foreach ($symbolArr as $key => $v) {
            //     $data = \DB::connection('mysql_rm')->table($v)->select('*')->where(['date' => $todayDate, 'timeframe' => $timeFrame])->get();
            //     $atmData = [];
            //     foreach ($data as $vvl) {
            //         if (isset($vvl->atm) && $vvl->atm == 'ATM') {
            //             $atmData[] = $vvl;
            //         }
            //     }
            //     foreach ($atmData as $val) {
            //         array_push($allSymbols,$val->ce);
            //         array_push($allSymbols,$val->pe);
            //     }
            // }

            // $zehrodha = ZerodhaInstrument::whereIN('trading_symbol',$allSymbols)->get();


    //         return response()->json($this->getWatchListRecords($allSymbols));
    //     } catch (\Throwable $th) {
    //        return response()->json(
    //         [ 'data' => ['status'=>false] ]
    //        );
    //     }
    // }


    public function watchListOrder(Request $request){
        $pageTitle = "Watch List Order";
        $userId = \Auth::id();
        $wishlistorder = WatchList::where('user_id',$userId)->orderBy('id','DESC')->get();
        $fullUrl = $request->fullUrl();
        if($request->ajax()){
            return view($this->activeTemplate . 'user.watch-list-order-ajax',compact('pageTitle','wishlistorder','fullUrl'));
        }
        return view($this->activeTemplate . 'user.watch-list-order',compact('pageTitle','wishlistorder','fullUrl'));
    }

    public function watchListOrderAjax(Request $request){
        $pageTitle = "Watch List Order";
        $userId = \Auth::id();
        $wishlistorder = WatchList::where('user_id',$userId)->orderBy('id','DESC')->get();
        $fullUrl = $request->fullUrl();
        return view($this->activeTemplate . 'user.watch-list-order-ajax',compact('pageTitle','wishlistorder','fullUrl'));
    }

    public function watchListPosition(Request $request){
        $pageTitle = "Watch List Position";
        $userId = \Auth::id();
        $wishlistorder = WatchTradePosition::where('user_id',$userId)->orderBy('id','DESC')->paginate(50);

        $MCXpayload = [];
        $NFOpayload = [];
        if($wishlistorder != NULL){
            foreach ($wishlistorder as $key => $value) {
                if($value->exchange == "MCX"){
                    array_push($MCXpayload,$value->token);
                }else if($value->exchange == "NFO"){
                    array_push($NFOpayload,$value->token);
                }
            }
        }

        $payload = [
            'MCX'=>$MCXpayload,
            'NFO'=>$NFOpayload
        ];
        $payload = json_encode($payload,true);
        $respond = $this->getWatchListRecords($payload);

        if($respond == NULL){
            $respond = $this->getWatchListRecords($payload);
        }

        if(isset($respond)){
            if($respond['status'] == true){
                $watchList = $respond['data']['fetched'];
                foreach ($wishlistorder as $item) {
                    $positionData = WatchTradePosition::where('user_id',$userId)->where('id',$item->id)->first();
                    $key = array_search($item->token, array_column($watchList, 'symbolToken'));
                    $currentLtp = $watchList[$key]['ltp'];
                    $positionData->ltp = $currentLtp;
                    $positionData->save();
                }
            }
        }

        $wishlistorder = WatchTradePosition::where('user_id',$userId)->orderBy('id','DESC')->paginate(50);

        $fullUrl = $request->fullUrl();
        
        return view($this->activeTemplate . 'user.watch-list-position',compact('pageTitle','wishlistorder','fullUrl'));
    }


    public function watchListPositionAjax(Request $request){
        $pageTitle = "Watch List Position";
        $userId = \Auth::id();
        $wishlistorder = WatchTradePosition::where('user_id',$userId)->orderBy('id','DESC')->paginate(50);

        $MCXpayload = [];
        $NFOpayload = [];
        if($wishlistorder != NULL){
            foreach ($wishlistorder as $key => $value) {
                if($value->exchange == "MCX"){
                    array_push($MCXpayload,$value->token);
                }else if($value->exchange == "NFO"){
                    array_push($NFOpayload,$value->token);
                }
            }
        }

        $payload = [
            'MCX'=>$MCXpayload,
            'NFO'=>$NFOpayload
        ];
        $payload = json_encode($payload,true);
        $respond = $this->getWatchListRecords($payload);

        if($respond == NULL){
            $respond = $this->getWatchListRecords($payload);
        }

        if(isset($respond)){
            if($respond['status'] == true){
                $watchList = $respond['data']['fetched'];
                foreach ($wishlistorder as $item) {
                    $positionData = WatchTradePosition::where('user_id',$userId)->where('id',$item->id)->first();
                    $key = array_search($item->token, array_column($watchList, 'symbolToken'));
                    $currentLtp = $watchList[$key]['ltp'];
                    $positionData->ltp = $currentLtp;
                    $positionData->save();
                }
            }
        }

        $wishlistorder = WatchTradePosition::where('user_id',$userId)->orderBy('id','DESC')->paginate(50);

        $fullUrl = $request->fullUrl();
        return view($this->activeTemplate . 'user.watch-list-position-ajax',compact('pageTitle','wishlistorder','fullUrl'));
    }


    public function optionStatergy(){
        $pageTitle = "Option Statergies";
        $records = Strategy::where('is_deleted',1)->where('strategy_status','Enable')->get();
        $groupedRecords  = $records->groupBy('market_trend');
        // dd($list);
        return view($this->activeTemplate . 'user.option-statergy',compact('pageTitle','groupedRecords'));
    }



    public function stratergyDetails($id){
        $pageTitle = "Stratergy Details";
        $data=Strategy::where('id',$id)->first();
        $related = Strategy::where('is_deleted',1)->where('strategy_status','Enable')->where('market_trend',$data->market_trend)->whereNotIn('id',[$id])->get();
        return view($this->activeTemplate . 'user.stratergies-details',compact('pageTitle','data','related'));
    }

    public function getDatesBetweenDates($start_date, $end_date) {
        $dates = array();
        $current_date = new \DateTime($start_date);
        $end_date = new \DateTime($end_date);
    
        while ($current_date <= $end_date) {
            $dates[] = $current_date->format('Y-m-d');
            $current_date->modify('+1 day');
        }
    
        return $dates;
    }

    // public function predictions(Request $request){
    //     $data['pageTitle'] = "AI/ML PREDICTIONS";
    //     $fromDate = date("Y-m-d",strtotime('+ 1 day'));
    //     $toDate = date("Y-m-d",strtotime('+ 1 day'));

    //     $alDataSS = \DB::connection('mysql_rm')->table('Predictions')->select('predicted_date')->orderBy('id','DESC')->first();
    //     if($alDataSS){
    //         $fromDate = date("Y-m-d",strtotime($alDataSS->predicted_date));
    //         $toDate = date("Y-m-d",strtotime($alDataSS->predicted_date));
    //     }

    //     if(!empty($request->from_date)){
    //         $fromDate = date("Y-m-d",strtotime($request->from_date));
    //     }
    //     if(!empty($request->to_date)){
    //         $toDate = date("Y-m-d",strtotime($request->to_date));
    //     }
    //     $selSymbol = '';
    //     $dates = $this->getDatesBetweenDates($fromDate,$toDate);
    //     $alData = \DB::connection('mysql_rm')->table('Predictions')->select('*')->where('atm_status','ATM')->whereIn('predicted_date',$dates);
    //     if(!empty($request->symbol)){
    //         $alData->where('main_symbol',$request->symbol);
    //         $selSymbol = $request->symbol;
    //     }
    //     $alData = $alData->get();
        
    //     $symbolsArr = \DB::connection('mysql_rm')->table('Predictions')->select('main_symbol')->groupBy('main_symbol')->pluck('main_symbol');
    //     $newArr = [];
    //     foreach($alData as $val){
    //         $newArr[$val->predicted_date][$val->main_symbol][$val->model][] = $val;
    //     }
    //     // dd($newArr);
    //     $data['data'] = $newArr;
    //     $data['fromDate'] = $fromDate;
    //     $data['toDate'] = $toDate;
    //     $data['selSymbol'] = $selSymbol;
    //     $data['symbolArr'] = $symbolsArr;
    //     return view($this->activeTemplate . 'user.predictions',$data);
    // }

    public function predictions(Request $request)
    {
        $data = [
            'pageTitle' => "AI/ML PREDICTIONS",
            'data' => [],
            'fromDate' => date("Y-m-d", strtotime('+1 day')),
            'toDate' => date("Y-m-d", strtotime('+1 day')),
            'selSymbol' => '',
            'symbolArr' => collect()
        ];

        try {
            // Get the latest prediction date
            $latestPrediction = \DB::connection('mysql_aiml')
                ->table('Predictions')
                ->select('predicted_date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestPrediction) {
                $data['fromDate'] = date("Y-m-d", strtotime($latestPrediction->predicted_date));
                $data['toDate'] = date("Y-m-d", strtotime($latestPrediction->predicted_date));
            }

            // Handle date filters
            if (!empty($request->from_date)) {
                $data['fromDate'] = date("Y-m-d", strtotime($request->from_date));
            }
            if (!empty($request->to_date)) {
                $data['toDate'] = date("Y-m-d", strtotime($request->to_date));
            }

            // Handle symbol filter
            if (!empty($request->symbol)) {
                $data['selSymbol'] = $request->symbol;
            }

            // Get dates between range
            $dates = $this->getDatesBetweenDates($data['fromDate'], $data['toDate']);

            // Get prediction data
            $query = \DB::connection('mysql_aiml')
                ->table('Predictions')
                ->select('*')
                ->where('atm_status', 'ATM')
                ->whereIn('predicted_date', $dates);

            if ($data['selSymbol']) {
                $query->where('main_symbol', $data['selSymbol']);
            }

            $predictionData = $query->get();

            // Get all available symbols
            $data['symbolArr'] = \DB::connection('mysql_aiml')
                ->table('Predictions')
                ->select('main_symbol')
                ->groupBy('main_symbol')
                ->pluck('main_symbol');

            // Organize data by date, symbol, and model
            $organizedData = [];
            foreach ($predictionData as $prediction) {
                $organizedData[$prediction->predicted_date][$prediction->main_symbol][$prediction->model][] = $prediction;
            }
            $data['data'] = $organizedData;

        } catch (\Exception $e) {
            \Log::error("Predictions DB Error (mysql_rm): " . $e->getMessage());
            session()->flash('error', 'Could not load prediction data. Please try again later.');

            if (config('app.debug')) {
                \Log::debug("Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all(),
                    'dates' => $dates ?? null
                ]);
            }
        }

        return view($this->activeTemplate . 'user.predictions', $data);
    }

    // Paper Trading
    // public function paperTrading(Request $request){
    //     $pageTitle = "Paper Trading";
    //     $todayDate = date("Y-m-d");
    //     $searchSymbol = "";

    //     // FOR DAILY DATA
    //     $paperTrade = \DB::connection('mysql_rm')->table('Paper Trade')->select('*')->orderBy('symbol','ASC');
    //     if($request->symbols){
    //         $searchSymbol = $request->symbols;
    //         $paperTrade = $paperTrade->where('symbol',$request->symbols);
    //     }
    //     $paperTrade = $paperTrade->paginate(50); 
    //     if(!count($paperTrade)){
    //         $paperTrade = \DB::connection('mysql_rm')->table('Paper Trade')->select('*')->orderBy('symbol','ASC');
    //         if($request->symbols){
    //             $searchSymbol = $request->symbols;
    //             $paperTrade = $paperTrade->where('symbol',$request->symbols);
    //         }
    //         $paperTrade = $paperTrade->paginate(50);  
    //     }
        
    //     $allSymbols = \DB::connection('mysql_rm')->table('Paper Trade')->select('*')->orderBy('symbol','ASC')->groupBy('symbol')->get()->pluck('symbol')->toArray();

    //     $fullUrl = $request->fullUrl();

    //     return view($this->activeTemplate . 'user.paper-trading',compact('pageTitle','paperTrade','fullUrl','searchSymbol','allSymbols'));
    // }

    public function paperTrading(Request $request)
    {
        $pageTitle = "Paper Trading";
        $todayDate = date("Y-m-d");
        $searchSymbol = "";
        $paperTrade = collect(); // default empty collection
        $allSymbols = [];
        $fullUrl = $request->fullUrl();

        try {
            // FIRST FETCH (with optional symbol filter)
            $query = \DB::connection('mysql_rm')->table('Paper Trade')->select('*')->orderBy('symbol', 'ASC');
            if ($request->symbols) {
                $searchSymbol = $request->symbols;
                $query = $query->where('symbol', $searchSymbol);
            }
            $paperTrade = $query->paginate(50);

            // FALLBACK IF EMPTY
            if ($paperTrade->isEmpty()) {
                $fallbackQuery = \DB::connection('mysql_rm')->table('Paper Trade')->select('*')->orderBy('symbol', 'ASC');
                if ($request->symbols) {
                    $fallbackQuery = $fallbackQuery->where('symbol', $request->symbols);
                }
                $paperTrade = $fallbackQuery->paginate(50);
            }

            // GET ALL SYMBOLS
            $allSymbols = \DB::connection('mysql_rm')->table('Paper Trade')
                ->select('symbol')
                ->orderBy('symbol', 'ASC')
                ->groupBy('symbol')
                ->pluck('symbol')
                ->toArray();

        } catch (\Exception $e) {
            \Log::error("Paper Trading DB Error (mysql_rm): " . $e->getMessage());
            // No crash. paperTrade will be empty and allSymbols = []
        }

        return view($this->activeTemplate . 'user.paper-trading', compact(
            'pageTitle', 'paperTrade', 'fullUrl', 'searchSymbol', 'allSymbols'
        ));
    }


    // public function paperTradingAjax(Request $request){
    //     $pageTitle = "Paper Trading";
    //     $todayDate = date("Y-m-d");
    //     // FOR DAILY DATA
    //     $paperTrade = \DB::connection('mysql_rm')->table('Paper Trade')->select('*')->orderBy('symbol','ASC');
    //     if($request->input('symbols')){
    //         $searchSymbol = $request->symbols;
    //         $paperTrade = $paperTrade->where('symbol',$request->symbols);
    //     }
    //     $paperTrade = $paperTrade->paginate(50); 
    //     if(!count($paperTrade)){
    //         $paperTrade = \DB::connection('mysql_rm')->table('Paper Trade')->select('*')->orderBy('symbol','ASC');
    //         if($request->symbols){
    //             $searchSymbol = $request->symbols;
    //             $paperTrade = $paperTrade->where('symbol',$request->symbols);
    //         }
    //         $paperTrade = $paperTrade->paginate(50);  
    //     }
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.paper-trading-ajax',compact('pageTitle','paperTrade','fullUrl'));
    // }

    public function paperTradingAjax(Request $request)
    {
        $pageTitle = "Paper Trading";
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // Default empty collection
        $fullUrl = $request->fullUrl();

        try {
            // Primary query
            $query = \DB::connection('mysql_rm')
                ->table('Paper Trade')
                ->select('*')
                ->orderBy('symbol', 'ASC');

            if ($request->input('symbols')) {
                $searchSymbol = $request->input('symbols');
                $query = $query->where('symbol', $searchSymbol);
            }

            $paperTrade = $query->paginate(50);

            // Fallback if no results
            if ($paperTrade->isEmpty()) {
                $fallbackQuery = \DB::connection('mysql_rm')
                    ->table('Paper Trade')
                    ->select('*')
                    ->orderBy('symbol', 'ASC');

                if ($request->input('symbols')) {
                    $fallbackQuery = $fallbackQuery->where('symbol', $request->input('symbols'));
                }

                $paperTrade = $fallbackQuery->paginate(50);
            }

        } catch (\Exception $e) {
            \Log::error("PaperTradingAjax DB Error (mysql_rm): " . $e->getMessage());
            // Keeps $paperTrade as an empty collection
        }

        return view($this->activeTemplate . 'user.paper-trading-ajax', compact(
            'pageTitle', 'paperTrade', 'fullUrl'
        ));
    }

    // public function matchDelta(Request $request){
    //     $pageTitle = 'DELTA';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-DELTA')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-DELTA')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     // dd($data);
    //     return view($this->activeTemplate . 'user.match-delta',compact('data','fullUrl','pageTitle'));
    // } 

    public function matchDelta(Request $request)
    {
        $pageTitle = 'DELTA';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // default empty collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-DELTA')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-DELTA')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("matchDelta DB Error (mysql_rm): " . $e->getMessage());
            // Optionally notify UI or fallback
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-delta', compact('data', 'fullUrl', 'pageTitle'));
    }


    // public function ajaxmatchDelta(Request $request){
    //     $pageTitle = 'DELTA';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-DELTA')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-DELTA')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.match-delta-ajax',compact('data','fullUrl','pageTitle'));
    // } 

    public function ajaxmatchDelta(Request $request)
    {
        $pageTitle = 'DELTA';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // fallback collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-DELTA')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-DELTA')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("ajaxmatchDelta DB Error (mysql_rm): " . $e->getMessage());
            // Optional: return a JSON error if it's strictly an AJAX response
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-delta-ajax', compact('data', 'fullUrl', 'pageTitle'));
    }

    // public function matchTheta(Request $request){
    //     $pageTitle = 'THETA';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-THETA')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-THETA')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.match-theta',compact('data','fullUrl','pageTitle'));
    // } 

    public function matchTheta(Request $request)
    {
        $pageTitle = 'THETA';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // fallback empty collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-THETA')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-THETA')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("matchTheta DB Error (mysql_rm): " . $e->getMessage());
            // You might want to add a flash message to inform the user
            session()->flash('error', 'Could not load THETA data. Please try again later.');
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-theta', compact('data', 'fullUrl', 'pageTitle'));
    }

    // public function ajaxmatchTheta(Request $request){
    //     $pageTitle = 'THETA';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-THETA')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-THETA')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.match-theta-ajax',compact('data','fullUrl','pageTitle'));
    // } 

    public function ajaxmatchTheta(Request $request)
    {
        $pageTitle = 'THETA';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // fallback empty collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-THETA')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-THETA')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("ajaxmatchTheta DB Error (mysql_rm): " . $e->getMessage());
            // For AJAX requests, you might want to return a JSON response
            if ($request->ajax()) {
                return response()->json(['error' => 'Could not load data'], 500);
            }
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-theta-ajax', compact('data', 'fullUrl', 'pageTitle'));
    }

    // public function matchPremium(Request $request){
    //     $pageTitle = 'PREMIUM';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.match-premium',compact('data','fullUrl','pageTitle'));
    // } 

    // public function ajaxmatchPremium(Request $request){
    //     $pageTitle = 'PREMIUM';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.match-premium-ajax',compact('data','fullUrl','pageTitle'));
    // } 

    public function matchPremium(Request $request)
    {
        $pageTitle = 'PREMIUM';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // fallback empty collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("matchPremium DB Error (mysql_rm): " . $e->getMessage());
            // Add flash message to inform the user
            session()->flash('error', 'Could not load PREMIUM data. Please try again later.');
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-premium', compact('data', 'fullUrl', 'pageTitle'));
    }

    public function ajaxmatchPremium(Request $request)
    {
        $pageTitle = 'PREMIUM';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // fallback empty collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-PREMIUM')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("ajaxmatchPremium DB Error (mysql_rm): " . $e->getMessage());
            // For AJAX requests, return a JSON response
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'Could not load PREMIUM data',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-premium-ajax', compact('data', 'fullUrl', 'pageTitle'));
    }

    // public function matchIv(Request $request){
    //     $pageTitle = 'IV';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-IV')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-IV')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.match-iv',compact('data','fullUrl','pageTitle'));
    // } 

    // public function ajaxmatchIv(Request $request){
    //     $pageTitle = 'IV';
    //     $latestData = \DB::connection('mysql_rm')->table('MATCH-IV')->select('date')->orderBy('id','DESC')->first();
    //     $todayDate = date("Y-m-d");
    //     if($latestData){
    //         $todayDate = $latestData->date;
    //     }
    //     $paperTrade = \DB::connection('mysql_rm')->table('MATCH-IV')->select('*')->where('date',$todayDate)->orderBy('symbol','ASC');
    //     $paperTrade = $paperTrade->paginate(50); 
    //     $data['paperTrade'] = $paperTrade;
    //     $fullUrl = $request->fullUrl();
    //     return view($this->activeTemplate . 'user.match-iv-ajax',compact('data','fullUrl','pageTitle'));
    // } 

    public function matchIv(Request $request)
    {
        $pageTitle = 'IV';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // fallback empty collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-IV')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-IV')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("matchIv DB Error (mysql_rm): " . $e->getMessage());
            // Add flash message to inform the user
            session()->flash('error', 'Could not load IV data. Please try again later.');
            
            // Optional: Log additional debug information in development
            if (config('app.debug')) {
                \Log::debug("Error details: ", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-iv', compact('data', 'fullUrl', 'pageTitle'));
    }

    public function ajaxmatchIv(Request $request)
    {
        $pageTitle = 'IV';
        $todayDate = date("Y-m-d");
        $paperTrade = collect(); // fallback empty collection

        try {
            $latestData = \DB::connection('mysql_rm')->table('MATCH-IV')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();

            if ($latestData) {
                $todayDate = $latestData->date;
            }

            $paperTrade = \DB::connection('mysql_rm')->table('MATCH-IV')
                ->select('*')
                ->where('date', $todayDate)
                ->orderBy('symbol', 'ASC')
                ->paginate(50);

        } catch (\Exception $e) {
            \Log::error("ajaxmatchIv DB Error (mysql_rm): " . $e->getMessage());
            
            // For AJAX requests, return a JSON response
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not load IV data',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                    'date' => $todayDate
                ], 500);
            }
            
            // Additional debug logging in development
            if (config('app.debug')) {
                \Log::debug("AJAX Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all()
                ]);
            }
        }

        $data['paperTrade'] = $paperTrade;
        $fullUrl = $request->fullUrl();

        return view($this->activeTemplate . 'user.match-iv-ajax', compact('data', 'fullUrl', 'pageTitle'));
    }

    // public function paperXFactor(Request $request)
    // {
    //     $pageTitle = 'Paper X Factor';
    //     $isFiltered = 0;
    //     $checkTodayD = \DB::connection('mysql_rm')->table('PaperXFactor')->select('date')->orderBy('id','DESC')->first();
    //     $today = $checkTodayD ? date("Y-m-d",strtotime($checkTodayD->date)) : date("Y-m-d");
        
    //     $paperFactorQuery = \DB::connection('mysql_rm') ->table('PaperXFactor')->select('symbol','date','data');
    //     if ($request->has('factor_date') && $request->factor_date != '') {
    //         $paperFactorQuery->whereDate('date', $request->factor_date);
    //     }else{
    //         $paperFactorQuery->whereDate('date', $today);
    //     }
    //     if ($request->has('factor_symbol') && $request->factor_symbol != '') {
    //         $paperFactorQuery->where('symbol', $request->factor_symbol);
    //         $isFiltered = 1;
    //     }
    //     $paperFactor = $paperFactorQuery->get();
    //     $allSymbols = \DB::connection('mysql_rm')->table('PaperXFactor')->select('symbol')->orderBy('symbol','ASC')->groupBy('symbol')->get();
    //     // Prepare the data array
    //     $data['paperFactor'] = $paperFactor;
    //     $data['symbolArr'] = $allSymbols;
    //     $data['pageTitle'] = $pageTitle;
    //     $data['isFiltered'] = $isFiltered;

    //     // Return the view with the data and page title
    //     return view($this->activeTemplate . 'user.paper-x-factor', $data);
    // }

    public function paperXFactor(Request $request)
    {
        $pageTitle = 'Paper X Factor';
        $isFiltered = 0;
        $today = date("Y-m-d");
        $paperFactor = collect();
        $allSymbols = collect();
        
        try {
            // Get the latest date
            $checkTodayD = \DB::connection('mysql_x_factor')
                ->table('PaperXFactor')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();
                
            $today = $checkTodayD ? date("Y-m-d", strtotime($checkTodayD->date)) : $today;

            // Build main query
            $paperFactorQuery = \DB::connection('mysql_x_factor')
                ->table('PaperXFactor')
                ->select('symbol', 'date', 'data');

            // Apply date filter
            if ($request->has('factor_date') && $request->factor_date != '') {
                $paperFactorQuery->whereDate('date', $request->factor_date);
            } else {
                $paperFactorQuery->whereDate('date', $today);
            }

            // Apply symbol filter
            if ($request->has('factor_symbol') && $request->factor_symbol != '') {
                $paperFactorQuery->where('symbol', $request->factor_symbol);
                $isFiltered = 1;
            }

            $paperFactor = $paperFactorQuery->get();

            // Get all symbols
            $allSymbols = \DB::connection('mysql_x_factor')
                ->table('PaperXFactor')
                ->select('symbol')
                ->orderBy('symbol', 'ASC')
                ->groupBy('symbol')
                ->get();

        } catch (\Exception $e) {
            \Log::error("paperXFactor DB Error (mysql_rm): " . $e->getMessage());
            session()->flash('error', 'Could not load Paper X Factor data. Please try again later.');
            
            if (config('app.debug')) {
                \Log::debug("Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all()
                ]);
            }
        }

        // Prepare the data array
        $data = [
            'paperFactor' => $paperFactor,
            'symbolArr' => $allSymbols,
            'pageTitle' => $pageTitle,
            'isFiltered' => $isFiltered,
            'today' => $today
        ];

        return view($this->activeTemplate . 'user.paper-x-factor', $data);
    }

    public function paperxFactorAjax(Request $request)
    {
        $pageTitle = 'Paper X Factor';
        $isFiltered = 0;
        $today = date("Y-m-d");
        $paperFactor = collect();
        $allSymbols = collect();

        try {
            // Get the latest date
            $checkTodayD = \DB::connection('mysql_x_factor')
                ->table('PaperXFactor')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();
                
            $today = $checkTodayD ? date("Y-m-d", strtotime($checkTodayD->date)) : $today;

            // Build main query
            $paperFactorQuery = \DB::connection('mysql_x_factor')
                ->table('PaperXFactor')
                ->select('symbol', 'date', 'data');

            // Apply date filter
            if ($request->has('factor_date') && $request->factor_date != '') {
                $paperFactorQuery->whereDate('date', $request->factor_date);
            } else {
                $paperFactorQuery->whereDate('date', $today);
            }

            // Apply symbol filter
            if ($request->has('factor_symbol') && $request->factor_symbol != '') {
                $paperFactorQuery->where('symbol', $request->factor_symbol);
                $isFiltered = 1;
            }

            $paperFactor = $paperFactorQuery->get();

            // Get all symbols
            $allSymbols = \DB::connection('mysql_x_factor')
                ->table('PaperXFactor')
                ->select('symbol')
                ->orderBy('symbol', 'ASC')
                ->groupBy('symbol')
                ->get();

        } catch (\Exception $e) {
            \Log::error("paperxFactorAjax DB Error (mysql_rm): " . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not load Paper X Factor data',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                    'date' => $today
                ], 500);
            }
            
            if (config('app.debug')) {
                \Log::debug("AJAX Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all()
                ]);
            }
        }

        // Prepare the data array
        $data = [
            'paperFactor' => $paperFactor,
            'symbolArr' => $allSymbols,
            'pageTitle' => $pageTitle,
            'isFiltered' => $isFiltered,
            'today' => $today
        ];

        return view($this->activeTemplate . 'user.paper-x-factor-ajax', $data);
    }

    // public function paperxFactorAjax(Request $request){
    //     $pageTitle = 'Paper X Factor';
    //     $isFiltered = 0;
    //     $checkTodayD = \DB::connection('mysql_rm')->table('PaperXFactor')->select('date')->orderBy('id','DESC')->first();
    //     $today = $checkTodayD ? date("Y-m-d",strtotime($checkTodayD->date)) : date("Y-m-d");
        
    //     $paperFactorQuery = \DB::connection('mysql_rm') ->table('PaperXFactor')->select('symbol','date','data');
    //     if ($request->has('factor_date') && $request->factor_date != '') {
    //         $paperFactorQuery->whereDate('date', $request->factor_date);
    //     }else{
    //         $paperFactorQuery->whereDate('date', $today);
    //     }
    //     if ($request->has('factor_symbol') && $request->factor_symbol != '') {
    //         $paperFactorQuery->where('symbol', $request->factor_symbol);
    //         $isFiltered = 1;
    //     }
    //     $paperFactor = $paperFactorQuery->get();
    //     $allSymbols = \DB::connection('mysql_rm')->table('PaperXFactor')->select('symbol')->orderBy('symbol','ASC')->groupBy('symbol')->get();
    //     // Prepare the data array
    //     $data['paperFactor'] = $paperFactor;
    //     $data['symbolArr'] = $allSymbols;
    //     $data['pageTitle'] = $pageTitle;
    //     $data['isFiltered'] = $isFiltered;

    //     // Return the view with the data and page title
    //     return view($this->activeTemplate . 'user.paper-x-factor-ajax', $data);
    // }

    // public function paperXFactorCombined(Request $request)
    // {
        
    //     $isFiltered = 0;
    //     $checkTodayD = \DB::connection('mysql_rm')->table('PaperXFactor_Combined')->select('date')->orderBy('id','DESC')->first();
    //     $today = $checkTodayD ? date("Y-m-d",strtotime($checkTodayD->date)) : date("Y-m-d");

    //     $paperFactorQuery = \DB::connection('mysql_rm') ->table('PaperXFactor_Combined')->select('total_investment','total_profit','date','json_data','updated_at');
    //     if ($request->has('factor_date') && $request->factor_date != '') {
    //         $paperFactorQuery->whereDate('date', $request->factor_date);
    //         $today = $request->factor_date;
    //     }else{
    //         $paperFactorQuery->whereDate('date', $today);
    //     }
    //     $paperFactor = $paperFactorQuery->get();
    //     $allSymbols = [];
    //     if (!$paperFactor->isEmpty()) {
    //         $allSymbols = array_unique(array_column(json_decode($paperFactor[0]->json_data, true), 'config_symbol')); 
    //     }
        
    //     $pageTitle = 'XFactor Model Portfolio '.date("d-M-Y",strtotime($today));
        
    //     // if ($request->has('factor_symbol') && $request->factor_symbol != '') {
    //     //     $paperFactorQuery->where('symbol', $request->factor_symbol);
    //     //     $isFiltered = 1;
    //     // }
        
    
    //     // Prepare the data array
    //     $data['paperFactor'] = $paperFactor;
    //     $data['symbolArr'] = $allSymbols;
    //     $data['pageTitle'] = $pageTitle;
    //     $data['isFiltered'] = 0;
    //     $data['today'] = $today;
    //     return view($this->activeTemplate . 'user.paper-x-factor-combined', $data);
    // }

    public function paperXFactorCombined(Request $request)
    {
        $isFiltered = 0;
        $today = date("Y-m-d");
        $paperFactor = collect();
        $allSymbols = [];
        
        try {
            // Get the latest date
            $checkTodayD = \DB::connection('mysql_rm')
                ->table('PaperXFactor_Combined')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();
                
            $today = $checkTodayD ? date("Y-m-d", strtotime($checkTodayD->date)) : $today;

            // Build main query
            $paperFactorQuery = \DB::connection('mysql_rm')
                ->table('PaperXFactor_Combined')
                ->select('total_investment', 'total_profit', 'date', 'json_data', 'updated_at');

            // Apply date filter
            if ($request->has('factor_date') && $request->factor_date != '') {
                $paperFactorQuery->whereDate('date', $request->factor_date);
                $today = $request->factor_date;
            } else {
                $paperFactorQuery->whereDate('date', $today);
            }

            $paperFactor = $paperFactorQuery->get();

            // Extract symbols from JSON data if available
            if (!$paperFactor->isEmpty() && isset($paperFactor[0]->json_data)) {
                $jsonData = json_decode($paperFactor[0]->json_data, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($jsonData)) {
                    $allSymbols = array_unique(array_column($jsonData, 'config_symbol'));
                }
            }

        } catch (\Exception $e) {
            \Log::error("paperXFactorCombined DB Error (mysql_rm): " . $e->getMessage());
            session()->flash('error', 'Could not load XFactor Combined data. Please try again later.');
            
            if (config('app.debug')) {
                \Log::debug("Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all()
                ]);
            }
        }

        $pageTitle = 'XFactor Model Portfolio ' . date("d-M-Y", strtotime($today));
        
        // Prepare the data array
        $data = [
            'paperFactor' => $paperFactor,
            'symbolArr' => $allSymbols,
            'pageTitle' => $pageTitle,
            'isFiltered' => $isFiltered,
            'today' => $today
        ];

        return view($this->activeTemplate . 'user.paper-x-factor-combined', $data);
    }

    // public function paperXFactorCombinedAjax(Request $request)
    // {
        
    //     $isFiltered = 0;
    //     $checkTodayD = \DB::connection('mysql_rm')->table('PaperXFactor_Combined')->select('date')->orderBy('id','DESC')->first();
    //     $today = $checkTodayD ? date("Y-m-d",strtotime($checkTodayD->date)) : date("Y-m-d");
    //     $paperFactorQuery = \DB::connection('mysql_rm') ->table('PaperXFactor_Combined')->select('total_investment','total_profit','date','json_data','updated_at');
    //     if ($request->has('factor_date') && $request->factor_date != '') {
    //         $paperFactorQuery->whereDate('date', $request->factor_date);
    //         $today = $request->factor_date;
    //     }else{
    //         $paperFactorQuery->whereDate('date', $today);
    //     }
        
    //     // if ($request->has('factor_symbol') && $request->factor_symbol != '') {
    //     //     $paperFactorQuery->where('symbol', $request->factor_symbol);
    //     //     $isFiltered = 1;
    //     // }
    //     $paperFactor = $paperFactorQuery->get();
    //     $allSymbols = [];
    //     // Prepare the data array
    //     $data['paperFactor'] = $paperFactor;
    //     $data['symbolArr'] = $allSymbols;
    //     $data['isFiltered'] = 0;
    //     $data['today'] = $today;
    //     return view($this->activeTemplate . 'user.paper-x-factor-combined-ajax', $data);
    // }

    public function paperXFactorCombinedAjax(Request $request)
    {
        $isFiltered = 0;
        $today = date("Y-m-d");
        $paperFactor = collect();
        $allSymbols = [];

        try {
            // Get the latest date
            $checkTodayD = \DB::connection('mysql_rm')
                ->table('PaperXFactor_Combined')
                ->select('date')
                ->orderBy('id', 'DESC')
                ->first();
                
            $today = $checkTodayD ? date("Y-m-d", strtotime($checkTodayD->date)) : $today;

            // Build main query
            $paperFactorQuery = \DB::connection('mysql_rm')
                ->table('PaperXFactor_Combined')
                ->select('total_investment', 'total_profit', 'date', 'json_data', 'updated_at');

            // Apply date filter
            if ($request->has('factor_date') && $request->factor_date != '') {
                $paperFactorQuery->whereDate('date', $request->factor_date);
                $today = $request->factor_date;
            } else {
                $paperFactorQuery->whereDate('date', $today);
            }

            $paperFactor = $paperFactorQuery->get();

        } catch (\Exception $e) {
            \Log::error("paperXFactorCombinedAjax DB Error (mysql_rm): " . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not load XFactor Combined data',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                    'date' => $today
                ], 500);
            }
            
            if (config('app.debug')) {
                \Log::debug("AJAX Error details: ", [
                    'error' => $e->getMessage(),
                    'request' => $request->all()
                ]);
            }
        }

        // Prepare the data array
        $data = [
            'paperFactor' => $paperFactor,
            'symbolArr' => $allSymbols,
            'isFiltered' => $isFiltered,
            'today' => $today
        ];

        return view($this->activeTemplate . 'user.paper-x-factor-combined-ajax', $data);
    }

}
