<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OmsConfigs;
use App\Models\BrokerApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\AngelConnectCls;

class OMSConfigController extends Controller
{
    public function omsConfig(){
        $pageTitle = 'OMS CONFIG';
        $data['pageTitle'] = $pageTitle;
        $brokers = BrokerApi::select('client_name','id')->where('user_id',auth()->user()->id)->get();
        $data['brokers'] = $brokers;
        $data['omsData'] = OmsConfigs::where('user_id',auth()->user()->id)->with('broker:id,client_name')->paginate(50);
        return view($this->activeTemplate . 'user.oms-config.view',$data);
    }

    public function getSymbolData($buildupType,$portfolioType)
    {
        $connection = DB::connection('mysql_oi_buildup');

        $query = $connection->table('positions_on_buildup')
            ->select(
                'id',
                'created_at',
                'symbol_name',
                'symbol_token',
                'transaction_type',
                'lot_size',
                'buy_quantity',
                'buy_price',
                'sell_quantity',
                'sell_price',
                'ltp',
                'profit',
                'total_value',
                'realised_profit',
                'unrealised_profit',
                'portfolio_type',
                'buildUp_type'
            )
            ->where('portfolio_type', $portfolioType)
            ->whereDate('created_at', Carbon::today());
            // ->whereDate('created_at', '2025-08-08'); // Testing

        if (strtolower($buildupType) !== 'all') {
            $query->where('buildUp_type', $buildupType);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function storeOmsConfig(Request $request)
    {
        try {
            $request->validate([
                'portfolio_type'  => 'required|string|in:PF_1,PF_2,Portfolio-Futures-Direct,Portfolio-Options-Opposite,Portfolio-Futures-Opposite',
                'buildup_type'    => 'required|string|in:all,Long Built Up,Short Built Up,Short Covering,Long Unwinding',
                'client_name'     => 'required|exists:broker_apis,id',
                'dis_ltp'         => 'required|numeric|min:0|max:100',
                'order_type'      => 'required|in:LIMIT,MARKET',
                'pyramid_percent' => 'nullable|numeric|in:33,50,100',
                'product'         => 'required|in:NRML,MIS',
                'quantity'        => 'required|integer|min:1',
                'pyramid_freq'    => 'required|integer|min:0',
                'status'          => 'required|in:1,0',
            ]);

            $symbolsData = $this->getSymbolData($request->buildup_type,$request->portfolio_type);
            if (empty($symbolsData) || count($symbolsData) === 0) {
                $notify[] = ['error', 'No symbol data found for the selected buildup type.'];
                return back()->withNotify($notify);
            }

            $insertData = [];
            $uniqueKeys = [];

            // Determine pyramid step count
            $pyramidSteps = match ((int)$request->pyramid_percent) {
                33 => 3,
                50 => 2,
                default => 1,
            };

            foreach ($symbolsData as $row) {
                $symbol = strtoupper(trim($row->symbol_name));
                $symbolType = (substr($symbol, -2) === 'CE') ? 'CE' : ((substr($symbol, -2) === 'PE') ? 'PE' : 'FUT');

                if (!in_array($symbolType, ['CE', 'PE', 'FUT'])) {
                    continue;
                }

                // Prevent duplicates (unique by symbol_name + token + client_name)
                $uniqueKey = $symbol . '|' . $row->symbol_token . '|' . $request->client_name;
                if (isset($uniqueKeys[$uniqueKey])) {
                    continue;
                }
                $uniqueKeys[$uniqueKey] = true;

                // Calculate pyramid quantities
                $pData = calculatePyramids($request->quantity, $pyramidSteps);
                $pyramid1 = $pData[0] ?? null;
                $pyramid2 = $pData[1] ?? null;
                $pyramid3 = $pData[2] ?? null;

                $cronTiming =  $request->pyramid_freq > 0 ? date("Y-m-d H:i:s",strtotime('-'.$request->pyramid_freq.' minutes')) : date("Y-m-d H:i:s");
                $quantity = $request->quantity*$row->lot_size;

                $insertData[] = [
                    'symbol_name'     => $row->symbol_name,
                    'token'           => $row->symbol_token,
                    'symbol_type'     => $symbolType,
                    'broker_api_id'   => $request->client_name,
                    'disc_ltp'        => $request->dis_ltp,
                    'portfolio_type'  => $request->portfolio_type,
                    'buildup_type'    => $request->buildup_type,
                    'product'         => $request->product,
                    'order_type'      => $request->order_type,
                    'pyramid_percent' => $request->pyramid_percent,
                    'quantity'        => $quantity,
                    'txn_type'        => $row->transaction_type,
                    'pyramid_freq'    => $request->pyramid_freq ?? 0,
                    'pyramid_1'       => $pyramid1 ?? 0,
                    'pyramid_2'       => $pyramid2 ?? 0,
                    'pyramid_3'       => $pyramid3 ?? 0,
                    'exit_1_qty'      => $request->exit_1_qty ?? 0,
                    'exit_1_target'   => $request->exit_1_target ?? 0,
                    'exit_2_qty'      => $request->exit_2_qty ?? 0,
                    'exit_2_target'   => $request->exit_2_target ?? 0,
                    'user_id'         => Auth::id(),
                    'status'          => $request->status,
                    'cron_run_at'     => $cronTiming,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }

            if (!empty($insertData)) {
                // Chunk insert to handle thousands of rows
                DB::transaction(function () use ($insertData) {
                    foreach (array_chunk($insertData, 1000) as $chunk) {
                        OmsConfigs::insert($chunk);
                    }
                });
            }

            $notify[] = ['success', 'OMS Config saved successfully.'];
            return back()->withNotify($notify);
        } catch (\Throwable $e) {
            \Log::error('OMS Config Store Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $notify[] = ['error', 'An error occurred while saving OMS Config: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function storeOmsConfigOld(Request $request){

        $symbolNames = "";

        // OMS CONFID TB COLMNS
        // 1. symbol_name
        // 2. token
        // 3. symbol_type (CE/PE)
        // 4. broker_api_id (Already Have)
        // 5. entry_point (Already Have)
        // 6. strategy_name (BuildUp Name)
        // 7. product (Already Have)
        // 8. order_type (Already Have)
        // 9. pyramid_percent (Already Have)
        // 10. order_type (Already Have)

        $txnType = '';

        // ONLY 1 AT A TIME
        // $ce_symbol_name = $request->ce_symbol_name; // N/A
        // $pe_symbol_name = $request->pe_symbol_name; // N/A

        $strategyName = $request->strategy_name;

        switch($request->strategy_name){
            case 'long':
                $txnType = 'BUY';
            break;
            case 'short_covering':
                $txnType = 'BUY';
            break;
            case 'short':
                $txnType = 'BUY';
            break;
            case 'long_unwinding':
                $txnType = 'BUY';
            break;
        }

        // FOR CE 
        $ce_pyramid_1 = null; 
        $ce_pyramid_2 = null;
        $ce_pyramid_3 = null;

        // FOR PE 
        $pe_pyramid_1 = null; 
        $pe_pyramid_2 = null; 
        $pe_pyramid_3 = null; 
        
        $ce_quantity = $request->ce_quantity > 0 ? $request->ce_quantity : 0;
        $numbertodivise = $ce_quantity;

        $no = 1;
        if($request->pyramid_percent == 33){
            $no = 3;
        }elseif($request->pyramid_percent == 50){
            $no = 2;
        }

        $pData = calculatePyramids($numbertodivise,$no);

        if($no == 3){
            $ce_pyramid_1 = $pData[0];
            $ce_pyramid_2 = $pData[1];
            $ce_pyramid_3 = $pData[2];
        }

        if($no == 2){
            $ce_pyramid_1 = $pData[0];
            $ce_pyramid_2 = $pData[1];
        }

        if($no == 1){
            $ce_pyramid_1 = $pData[0];
        }
    
        $pe_quantity = $request->pe_quantity > 0 ? $request->pe_quantity : 0;
        $numbertodivise = $pe_quantity;

        $pData = calculatePyramids($numbertodivise,$no);
        if($no == 3){
            $pe_pyramid_1 = $pData[0];
            $pe_pyramid_2 = $pData[1];
            $pe_pyramid_3 = $pData[2];
        }
        if($no == 2){
            $pe_pyramid_1 = $pData[0];
            $pe_pyramid_2 = $pData[1];
        }
        if($no == 1){
            $pe_pyramid_1 = $pData[0];
        }
        
        $omsObj = new OmsConfig();
        $omsObj->symbol_name = $request->symbol_name; // FROM TUHIN DB
        $omsObj->signal_tf = $request->signal_tf; // NOT REQ.
        // $omsObj->ce_symbol_name = $ce_symbol_name; // CHANGE
        // $omsObj->pe_symbol_name = $pe_symbol_name; // CHANGE
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
        $omsObj->txn_type = $txnType; // WE GET FROM TUHIN DB

        // NEW FEILD FOR QUANTITY 

        // $omsObj->ce_quantity = $request->ce_quantity; // CHANGE
        // $omsObj->pe_quantity = $request->pe_quantity; // CHANGE

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

    public function updateOmsConfig(Request $request){
        $txnType = '';

        // $ce_symbol_name = $request->ce_symbol_name_up;
        // $pe_symbol_name = $request->pe_symbol_name_up;

        $strategyName = $request->strategy_name_up;
        
         switch($request->strategy_name){
            case 'long':
                $txnType = 'BUY';
            break;
            case 'short_covering':
                $txnType = 'BUY';
            break;
            case 'short':
                $txnType = 'BUY';
            break;
            case 'long_unwinding':
                $txnType = 'BUY';
            break;
        }

        // FOR CE 
        $ce_pyramid_1 = null;
        $ce_pyramid_2 = null;
        $ce_pyramid_3 = null;

        // FOR PE
        $pe_pyramid_1 = null;
        $pe_pyramid_2 = null;
        $pe_pyramid_3 = null;
        
        $ce_quantity = $request->ce_quantity_up > 0 ? $request->ce_quantity_up : 0;
        $numbertodivise = $ce_quantity;
        $no=1;
        
        if($request->pyramid_percent_up == 33){
            $no = 3;
        }elseif($request->pyramid_percent_up == 50){
            $no = 2;
        }

        $pData = calculatePyramids($numbertodivise, $no);
        if($no == 3){
            $ce_pyramid_1 = $pData[0];
            $ce_pyramid_2 = $pData[1];
            $ce_pyramid_3 = $pData[2];
        }
        if($no == 2){
            $ce_pyramid_1 = $pData[0];
            $ce_pyramid_2 = $pData[1];
        }
        if($no == 1){
            $ce_pyramid_1 = $pData[0];
        }

        $pe_quantity = $request->pe_quantity_up > 0 ? $request->pe_quantity_up : 0;
        $numbertodivise = $pe_quantity;
        $pData = calculatePyramids($numbertodivise,$no);
        if($no == 3){
            $pe_pyramid_1 = $pData[0];
            $pe_pyramid_2 = $pData[1];
            $pe_pyramid_3 = $pData[2];
        }
        if($no == 2){
            $pe_pyramid_1 = $pData[0];
            $pe_pyramid_2 = $pData[1];
        }
        if($no == 1){
            $pe_pyramid_1 = $pData[0];
        }

        $omsObj = OmsConfig::find($request->id);
        $omsObj->symbol_name = $request->symbol_name_up;
        // $omsObj->signal_tf = $request->signal_tf_up;
        // $omsObj->ce_symbol_name = $ce_symbol_name;
        // $omsObj->pe_symbol_name = $pe_symbol_name;

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
        // $omsObj->ce_quantity = $request->ce_quantity_up;
        // $omsObj->pe_quantity = $request->pe_quantity_up;
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
    
    // NEED TO BE CHANGE
    public function getOmgConfigData(Request $request){
        $id = $request->id;

        $brokers = BrokerApi::select('client_name','id')->where('user_id',auth()->user()->id)->get();
        $data['brokers'] = $brokers;

        $data['omgData'] = OmsConfig::where(['id'=>$id,'user_id'=>auth()->user()->id])->first();
        $symbol = $data['omgData']->symbol_name;
        // $signal = $data['omgData']->signal_tf;

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

}
