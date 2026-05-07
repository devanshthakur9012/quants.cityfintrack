<?php

namespace App\Http\Controllers\Admin;

require_once app_path('kiteconnect/autoload.php');

use KiteConnect\KiteConnect;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AngelApiInstrument;

class TradeController extends Controller
{
    public function tradeDeskSignal(Request $request){
        $pageTitle = 'Trade Desk Signal';
        $symbolArr = allTradeSymbols();
        $timeFrame = $request->time_frame ?: 5;
        $symbol = $request->symbol ?: 'CRUDEOIL';
        $todayDate = date("Y-m-d");
        try{
            $data = \DB::connection('mysql_rm')->table($symbol)->select('*')->where(['date'=>$todayDate,'timeframe'=>$timeFrame])->get();
        }catch(\Exception $e){
            $data = [];
        }
        return view('admin.trade.trade-desk-signal', compact('pageTitle','symbolArr','data','timeFrame','symbol'));
     
    }
    public function tradePosition(){
        $pageTitle = 'Trade Position';
        // $kiteObj = new KiteConnectCls($params);
        // $kite = \Cache::remember('KITE_AUTH_'.$broker->account_user_name, 3600, function () use($kiteObj) {
        //     $kite = $kiteObj->generateSession();
        //     return $kite;
        // });
        return view('admin.trade.trade-position', compact('pageTitle'));
     
    }
    
    public function brokerDetails(){
        $pageTitle = 'Broker Details';
        return view('admin.trade.broker-details', compact('pageTitle'));
    }

    public function orderBook(){
        $pageTitle = 'Order book';
        return view('admin.trade.order-book', compact('pageTitle'));
     
    }
    public function omsConfig(){
        $pageTitle = 'OMS Config';
        return view('admin.trade.oms-config', compact('pageTitle'));
     
    }

    public function saveAllAngelInstruments(){
        
        $response = file_get_contents('https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json');
        $dataArr = json_decode($response);
        set_time_limit(0);
        $dtTime = date("Y-m-d H:i:s");
        AngelApiInstrument::truncate();
        foreach($dataArr as $k=>$val){
            AngelApiInstrument::insert([
                'symbol_name'=>$val->symbol,
                'token'=>$val->token,
                'exch_seg'=>$val->exch_seg,
                'created_at'=>$dtTime
            ]);
            if($k%1000==0){
                sleep(5);
            }
        }

        die("inserted");
        dd($homepage);
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => "https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept: */*",
        ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
        echo "cURL Error #:" . $err;
        } else {
            $dataArr = json_decode($response);
            dd($dataArr);
            set_time_limit(300);
            $insData = [];
            $dtTime = date("Y-m-d H:i:s");
            AngelApiInstrument::truncate();
            foreach($dataArr as $k=>$val){
                // $insData[] = [
                //     'symbol_name'=>$val->symbol,
                //     'token'=>$val->token,
                //     'exch_seg'=>$val->exch_seg,
                //     'created_at'=>$dtTime
                // ];
                AngelApiInstrument::insert([
                    'symbol_name'=>$val->symbol,
                    'token'=>$val->token,
                    'exch_seg'=>$val->exch_seg,
                    'created_at'=>$dtTime
                ]);
                if($k%100==0){
                    sleep(5);
                }
            }
           
        }
    }

    // Upload Zerodha Instuments Data 
    function uploadZerodhaInstruments(){
        
    }
}
