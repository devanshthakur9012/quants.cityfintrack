<?php
namespace App\Helpers;
use App\Models\ZerodhaInstrument;
use App\Models\OmsConfig;
use App\Helpers\KiteConnectCls;
use App\Models\OrderBook;
use App\Models\AngelApiInstrument;
use App\Models\SiteVariable;

class OmsConfigCronOrderRt{
    
    public function __construct()
    {
        set_time_limit(0);
    }

    public function calculateTickSize($price,$tickSize){
        $roundedPrice = round($price / $tickSize) * $tickSize;
        return $roundedPrice; 
    }

    public function getCeLimitPrice($high,$low,$per,$type,$closePrice,$tickSize){
        // echo $high.'--'.$low.'--'.$per.'--'.$closePrice;die;
        $fibonaciData = SiteVariable::select('content')->where('value','fibonaci')->first();
        $fDada = json_decode($fibonaciData->content);
        if($per=='38.20'){
            $per = $fDada->percentage_one;
        }elseif($per=='50'){
            $per = $fDada->percentage_two;
        }else{
            $per = $fDada->percentage_three;
        }
        $diff = ($high - $low) * ($per/100);
        if($type=="BUY"){
            $price = $closePrice - $diff;
        }else{
            $price = $closePrice + $diff;
        }
        $finalPrice = round($price,2);
        return $this->calculateTickSize($finalPrice,$tickSize);
    }

    public function getPeLimitPrice($high,$low,$per,$type,$closePrice,$tickSize){
        $fibonaciData = SiteVariable::select('content')->where('value','fibonaci')->first();
        $fDada = json_decode($fibonaciData->content);
        if($per=='38.20'){
            $per = $fDada->percentage_one;
        }elseif($per=='50'){
            $per = $fDada->percentage_two;
        }else{
            $per = $fDada->percentage_three;
        }
        $diff = ($high - $low) * ($per/100);
        if($type=="BUY"){
            $price = $closePrice - $diff;
        }else{
            $price = $closePrice + $diff;
        }
        $finalPrice = round($price,2);
        return $this->calculateTickSize($finalPrice,$tickSize);
    }

    public function postPlaceOrder(object $broker,array $apiData){
        // dd($apiData);
        $params = [
            'accountUserName'=>$broker->account_user_name,
            'accountPassword'=>$broker->account_password,
            'totpSecret'=>$broker->totp,
            'apiKey'=>$broker->api_key,
            'apiSecret'=>$broker->api_secret_key
        ];
        // dd($params);
        $kiteObj = new KiteConnectCls($params);
        $kite = \Cache::remember('KITE_AUTH_'.$broker->account_user_name, 18000, function () use($kiteObj,$broker) {
            // $pythonScript = '/home/forge/cityprofitedge.com/public/kite_login/app.py -u '.$broker->account_user_name;
            // $command = 'python3 ' . $pythonScript; 
            // exec($command, $output, $exitCode);
            // $tokenArr =  explode("=",implode("\n", $output));
            // $token =  $tokenArr[1];
            // return rand(1,9999999);
            $token = $broker->request_token;
            $kite = $kiteObj->generateSessionManual($token);
            return $kite;
        });
        try{
            if(is_string($kite)){
                \Cache::forget('KITE_AUTH_'.$broker->account_user_name);
                return 0;
            }
            $order = $kite->placeOrder("regular", $apiData);
            sleep(3);
            $orderData = $kite->getOrderHistory($order->order_id);
            $lastD = array_slice($orderData,-1);
            
            $bookOBj = new OrderBook();
            $bookOBj->broker_username = $lastD[0]->placed_by;
            $bookOBj->order_id = $lastD[0]->order_id;
            $bookOBj->status = $lastD[0]->status;
            $bookOBj->trading_symbol = $lastD[0]->tradingsymbol;
            $bookOBj->order_type =  $lastD[0]->order_type;
            $bookOBj->transaction_type = $lastD[0]->transaction_type;
            $bookOBj->product = $lastD[0]->product;
            $bookOBj->price = isset($apiData['price']) ? $apiData['price'] : '-';;
            $bookOBj->quantity = $lastD[0]->quantity;
            $bookOBj->status_message = $lastD[0]->status_message;
            $bookOBj->order_datetime = $lastD[0]->order_timestamp->format('Y-m-d H:i:s');
            $bookOBj->user_id = $broker->user_id;
            $bookOBj->save();
            return 1;
        }catch(\Exception $e){
            $bookOBj = new OrderBook();
            $bookOBj->broker_username = $broker->account_user_name;
            $bookOBj->order_id = '-';
            $bookOBj->status = 'failed';
            $bookOBj->trading_symbol = $apiData['tradingsymbol'];
            $bookOBj->order_type =  '-';
            $bookOBj->transaction_type = '-';
            $bookOBj->product = '-';
            $bookOBj->price = isset($apiData['price']) ? $apiData['price'] : '-';;
            $bookOBj->quantity = '-';
            $bookOBj->status_message = $e->getMessage();
            $bookOBj->order_datetime = date("Y-m-d H:i:s");
            $bookOBj->user_id = $broker->user_id;
            $bookOBj->save();
            \Cache::forget('KITE_AUTH_'.$broker->account_user_name);
            return 1;
        }
    }

    public function getZerodhaSymLotSize($symbol){
        $lotSizeDat = ZerodhaInstrument::select('lot_size','tick_size')->where('trading_symbol',$symbol)->first();
        if($lotSizeDat){
            return [
                'lot_size'=>$lotSizeDat->lot_size,
                'tick_size'=>$lotSizeDat->tick_size
            ];
        }
        return [
            'lot_size'=>null,
            'tick_size'=>null
        ];
    }

    public function callKiteApi($signalData,object $omsData){
        $mcxSymArr = ['CRUDEOIL','NATURALGAS','GOLD','SILVER'];
        $txnType = $omsData->txn_type;
        $fData = [
            "exchange" => in_array($omsData->symbol_name,$mcxSymArr) ? "MCX" : "NFO",//crude oil,naturalgas,gold,silver--mcx .. remaining --NFO
            "transaction_type" => $txnType,
            "order_type" => $omsData->order_type,
            "product" => $omsData->product
        ];

        $breakForeach = 0;
        $ceHigh = 0;
        $ceLow = 0;
        $ceClosePrice = 0;

        $peHigh = 0;
        $peLow = 0;
        $peClosePrice = 0;
        
        foreach($signalData as $vvl){
            $data = json_decode($vvl->data,true);
            $datahigh_CE = array_reverse($data['high_CE']);
            $datalow_CE = array_reverse($data['low_CE']);
            $datahigh_PE = array_reverse($data['high_PE']);
            $datalow_PE = array_reverse($data['low_PE']);

            $dataclose_CE = array_reverse($data['close_CE']);
            $dataclose_PE = array_reverse($data['close_PE']);
            $buyAct = array_reverse($data['BUY_Action']);
            $sellAct = array_reverse($data['SELL_Action']);
            if($vvl->ce==$omsData->ce_symbol_name){
                foreach($sellAct as $k=>$v){
                    if(strtolower($v)==strtolower($omsData->strategy_name) || strtolower($buyAct[$k])==strtolower($omsData->strategy_name)){
                        $ceHigh = $datahigh_CE[$k];
                        $ceLow = $datalow_CE[$k];
                        $ceClosePrice = $dataclose_CE[$k];
                        break;
                    }
                }
            }
            if($vvl->pe==$omsData->pe_symbol_name){
                foreach($sellAct as $k=>$v){
                    if(strtolower($v)==strtolower($omsData->strategy_name) || strtolower($buyAct[$k])==strtolower($omsData->strategy_name)){
                        $peHigh = $datahigh_PE[$k];
                        $peLow = $datalow_PE[$k];
                        $peClosePrice = $dataclose_PE[$k];
                        break;
                    }
                }
            }
        }

        foreach($signalData as $vvl){
            if($breakForeach == 1){
                break;
            }
            $data = json_decode($vvl->data,true);
            $strategyArrFF = $data['Strategy_name'];
            $buyActionArrFF = $data['BUY_Action'];
            $sellActionArrFF = $data['SELL_Action'];
            $timeActArrFF = $data['time'];
            $dateActArrFF = $data['Date'];
            $cntLoop = 0;
            $placeOrderRept = 0;

            $strategyArr = [];
            $buyActionArr = [];
            $sellActionArr = [];
            $timeActArr = [];
            $dateActArr = [];

            foreach($timeActArrFF as $kk=>$vv){
                $dateStr = date("Y-m-d",($dateActArrFF[$kk]/1000)).' '.$vv;
                if(strtotime($dateStr) > strtotime($omsData->last_time)){
                    if($cntLoop==1){
                        if((strtolower($buyActionArrFF[$kk])==strtolower($omsData->strategy_name)) || (strtolower($sellActionArrFF[$kk])==strtolower($omsData->strategy_name))){
                            $strategyArr[] = $strategyArrFF[$kk];
                            $buyActionArr[] = $buyActionArrFF[$kk];
                            $sellActionArr[] = $sellActionArrFF[$kk];
                            $timeActArr[] = $timeActArrFF[$kk];
                            $dateActArr[] = $dateActArrFF[$kk];
                            $placeOrderRept = 1;
                            break;
                        }
                    }else{
                        if((strtolower($buyActionArrFF[$kk])==strtolower($omsData->strategy_name)) || (strtolower($sellActionArrFF[$kk])==strtolower($omsData->strategy_name))){
                            $cntLoop = 0;
                        }else{
                            $cntLoop = 1;
                        }
                    }
                }
            }
            if($placeOrderRept==1){
                foreach($strategyArr as $key=>$v){
                    // if(strtolower($v)==strtolower($omsData->strategy_name)){
                    if((strtolower($buyActionArr[$key])==strtolower($omsData->strategy_name)) || (strtolower($sellActionArr[$key])==strtolower($omsData->strategy_name))){
                        // $high = $highCEArr[$key];
                        // $low = $lowCEArr[$key];
                        // $closePrice = $closeCEArr[$key];

                        $high =$ceHigh;
                        $low = $ceLow;
                        $closePrice = $ceClosePrice;

                        $dateStr = date("Y-m-d",($dateActArr[$key]/1000));

                            $timeFrmTm = $dateStr." ".$timeActArr[$key];


                        $fData["tradingsymbol"] = $omsData->ce_symbol_name;

                    


                        $lotSizeArr = $this->getZerodhaSymLotSize($omsData->ce_symbol_name);
                        $lotSize = $lotSizeArr['lot_size'];
                        $tickSize = $lotSizeArr['tick_size'];
                        $updateDb = 1;
                        if(!is_null($omsData->ce_pyramid_1)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getCeLimitPrice($high,$low,38.20,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                        
                        $fData['quantity'] = $omsData->ce_pyramid_1;
                        $updateDb = $this->postPlaceOrder($omsData->broker,$fData);
                        }
                        if(!is_null($omsData->ce_pyramid_2)){
                            //50%
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getCeLimitPrice($high,$low,50,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            $fData['quantity'] = $omsData->ce_pyramid_2;
                            $updateDb = $this->postPlaceOrder($omsData->broker,$fData);
                        }
                        if(!is_null($omsData->ce_pyramid_3)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getCeLimitPrice($high,$low,61.80,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            $fData['quantity'] = $omsData->ce_pyramid_3;
                            $updateDb = $this->postPlaceOrder($omsData->broker,$fData);
                        }

                        //
                        $fData["tradingsymbol"] = $omsData->pe_symbol_name;
                        
                        $lotSizeArr = $this->getZerodhaSymLotSize($omsData->pe_symbol_name);
                        $lotSize = $lotSizeArr['lot_size'];
                        $tickSize = $lotSizeArr['tick_size'];


                        $high =$peHigh;
                        $low = $peLow;
                        $closePrice = $peClosePrice;

                        if(!is_null($omsData->pe_pyramid_1)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getPeLimitPrice($high,$low,38.20,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            $fData['quantity'] = $omsData->pe_pyramid_1;
                            $updateDb = $this->postPlaceOrder($omsData->broker,$fData);
                        }
                        if(!is_null($omsData->pe_pyramid_2)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getPeLimitPrice($high,$low,50,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            $fData['quantity'] = $omsData->pe_pyramid_2;
                            $updateDb = $this->postPlaceOrder($omsData->broker,$fData);
                        }
                        if(!is_null($omsData->pe_pyramid_3)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getPeLimitPrice($high,$low,61.80,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            $fData['quantity'] = $omsData->pe_pyramid_3;
                            $updateDb = $this->postPlaceOrder($omsData->broker,$fData);
                        }

                        if($updateDb==1){
                            OmsConfig::where("id",$omsData->id)->update([
                                'is_api_pushed'=>1,
                                // 'last_time'=>$timeFrmTm
                                'last_time'=>date("Y-m-d H:i",strtotime($timeFrmTm.' +5 minutes'))
                            ]);
                            $breakForeach = 1;
                            break;
                        }
                        
                    }
                }
            }
        }
       
    }

    //angel api start

    public function postPlaceOrderAngel(object $broker,array $apiData){
        // dd($apiData);
        $params = [
            'accountUserName'=>$broker->account_user_name,
            'apiKey'=>$broker->api_key,
            'pin'=>$broker->security_pin,
            'totp_secret'=>$broker->totp,
        ];
        
        $angelTokenArrObj = new AngelConnectCls($params);
        $angelTokenArr = $angelTokenArrObj->generate_access_token();


        if(is_null($angelTokenArr)){
            \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
            return 0;
        }else{
            $tokenA = $angelTokenArr['token'];
            $clientLocalIp = $angelTokenArr['clientLocalIp'];
            $clientPublicIp = $angelTokenArr['clientPublicIp'];
            $macAddress = $angelTokenArr['macAddress'];
            $httpHeaders = array(
                'X-UserType: USER',
                'X-SourceID: WEB',
                'X-PrivateKey: '.$broker->api_key,
                'X-ClientLocalIP: '.$clientLocalIp,
                'X-ClientPublicIP: '.$clientPublicIp,
                'X-MACAddress: '.$macAddress,
                'Content-Type: application/json',
                'Authorization: Bearer '.$tokenA
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/order/v1/placeOrder',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($apiData),
                CURLOPT_HTTPHEADER => $httpHeaders,
            ));    
            $response = curl_exec($curl);
            // echo $response;die;
            $err = curl_error($curl);
            curl_close($curl);
            if ($response=="" || is_null($response)) {
                \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
                $bookOBj = new OrderBook();
                $bookOBj->broker_username = $broker->account_user_name;
                $bookOBj->order_id = '-';
                $bookOBj->status = 'failed';
                $bookOBj->trading_symbol = $apiData['tradingsymbol'];
                $bookOBj->order_type =  '-';
                $bookOBj->transaction_type = '-';
                $bookOBj->product = '-';
                $bookOBj->price = isset($apiData['price']) ? $apiData['price'] : '-';;
                $bookOBj->quantity = '-';
                $bookOBj->status_message = "order failed-".$response;
                $bookOBj->order_datetime = date("Y-m-d H:i:s");
                $bookOBj->user_id = $broker->user_id;
                $bookOBj->save();
                return 1;
            }else{
                $response = json_decode($response,true);
                
                if($response['status']==true){
                    $orderId = $response['data']['uniqueorderid'];
                    sleep(3);
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/order/v1/details/'.$orderId,
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
                    
                    $response = json_decode($response,true);
                    if($response['status']==true){
                        $lastD = $response['data'];

                        $bookOBj = new OrderBook();
                        $bookOBj->broker_username = $broker->account_user_name;
                        $bookOBj->order_id = $orderId;
                        $bookOBj->status = $lastD['status'];
                        $bookOBj->trading_symbol = $lastD['tradingsymbol'];
                        $bookOBj->order_type =  $lastD['ordertype'];
                        $bookOBj->transaction_type = $lastD['transactiontype'];
                        $bookOBj->product = $lastD['producttype'];
                        $bookOBj->price = isset($apiData['price']) ? $apiData['price'] : '-';
                        $bookOBj->quantity = $lastD['quantity'];
                        $bookOBj->status_message = $lastD['text'];
                        $bookOBj->order_datetime = date("Y-m-d H:i:s",strtotime($lastD['updatetime']));
                        $bookOBj->user_id = $broker->user_id;
                        $bookOBj->save();
                        return 1;
                    }

                }else{
                    \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
                    $bookOBj = new OrderBook();
                    $bookOBj->broker_username = $broker->account_user_name;
                    $bookOBj->order_id = '-';
                    $bookOBj->status = 'failed';
                    $bookOBj->trading_symbol = $apiData['tradingsymbol'];
                    $bookOBj->order_type =  '-';
                    $bookOBj->transaction_type = '-';
                    $bookOBj->product = '-';
                    $bookOBj->price = isset($apiData['price']) ? $apiData['price'] : '-';;
                    $bookOBj->quantity = '-';
                    $bookOBj->status_message = "order failed-".$response;
                    $bookOBj->order_datetime = date("Y-m-d H:i:s");
                    $bookOBj->user_id = $broker->user_id;
                    $bookOBj->save();
                    return 1;
                }
            }
        }

    }

    public function getTokenBySymbolName($symbName){
        $data =  AngelApiInstrument::select('token as exchange_token','lotsize','symbol_name','tick_size')->where('symbol_name',$symbName)->first();
        if($data){
             $tSize = $data->tick_size/100;
             return [
                 'symbol'=> $data->symbol_name,
                 'token'=> $data->exchange_token,
                 'lot_size'=>$data->lotsize,
                 'tick_size'=>$tSize
             ];
            
        }
        return [
         'symbol'=>null,
         'token'=>null,
         'lot_size'=>null,
         'tick_size'=>null,
        ];
    }

    public function callAngelApi(object $omsData){
        // dd($signalData);
        $mcxSymArr = ['CRUDEOIL','NATURALGAS','GOLD','SILVER'];
        $txnType = $omsData->txn_type;
        $extType = in_array($omsData->symbol_name,$mcxSymArr) ? "MCX" : "NFO";
        $atmArr = [1,0,-1];
        $todayDate = date("Y-m-d");
        $fData = [
            'variety'=>'NORMAL',
            "exchange" => $extType,//crude oil,naturalgas,gold,silver--mcx .. remaining --NFO
            "transactiontype" => $txnType,
            "ordertype" => $omsData->order_type,
            // "producttype" => $omsData->product,
            "producttype" => 'CARRYFORWARD',
            'duration'=>'DAY',
            'squareoff'=>0,
            'stoploss'=>0
        ];

        $ceHigh = 0;
        $ceLow = 0;
        $ceClosePrice = 0;

        $peHigh = 0;
        $peLow = 0;
        $peClosePrice = 0;
        
        $dbTableName = strtolower($omsData->symbol_name);
        $strtgName = strtolower($omsData->strategy_name);

        // get symbol high low
        if(!is_null($omsData->ce_symbol_name)){
            $highLowData = DB::table($dbTableName)->select('high_ce','low_ce','close_ce')->where('symbol_ce',$omsData->ce_symbol_name)->whereIn('atm',$atmArr)->whereDate('created_at',$todayDate)->orderBy('id','DESC')->first();
            if($highLowData){
                $ceHigh = $highLowData->high_ce;
                $ceLow = $highLowData->low_ce;
                $ceClosePrice = $highLowData->close_ce;
            }
        }else{
            $highLowData = DB::table($dbTableName)->select('high_pe','low_pe','close_pe')->where('symbol_pe',$omsData->ce_symbol_name)->whereIn('atm',$atmArr)->whereDate('created_at',$todayDate)->orderBy('id','DESC')->first();
            if($highLowData){
                $peHigh = $highLowData->high_pe;
                $peLow = $highLowData->low_pe;
                $peClosePrice = $highLowData->close_pe;
            }
        }

        $tradeDeskData = null;

        $allTradesData = DB::table($dbTableName)->select('vmap_ce','vmap_pe','oi_ce','oi_pe')->where('id','>',$omsData->last_id)->get();
        if(in_array($strtgName,['bullish','bearish'])){
            if($omsData->ce_symbol_name!=null){
                $cnPlc = 0;
                foreach($allTradesData as $vl){
                    if(strtolower($vl->vmap_ce)==$strtgName){
                        if($cnPlc==0){
                            continue;
                        }
                    }
                    $cnPlc = 1;
                    if($strtgName=='bullish'){
                        $tradeDeskData = DB::table($dbTableName)->select('id','symbol_ce','symbol_pe','token_ce','token_pe','created_at')->where(['symbol_ce'=>$omsData->ce_symbol_name,'atm'=>0,'vmap_ce'=>'Bullish','vmap_pe'=>'Bearish'])->whereDate('created_at',$todayDate)->orderBy('id','DESC')->first();
                    }else{
                        $tradeDeskData = DB::table($dbTableName)->select('id','symbol_ce','symbol_pe','token_ce','token_pe','created_at')->where(['symbol_ce'=>$omsData->ce_symbol_name,'atm'=>0,'vmap_pe'=>'Bullish','vmap_ce'=>'Bearish'])->whereDate('created_at',$todayDate)->orderBy('id','DESC')->first();
                    }
                    break;
                }
            }
            if($omsData->pe_symbol_name!=null){
                $cnPlc = 0;
                foreach($allTradesData as $vl){
                    if(strtolower($vl->vmap_pe)==$strtgName){
                        if($cnPlc==0){
                            continue;
                        }
                    }
                    $cnPlc = 1;
                    if($strtgName=='bullish'){
                        $tradeDeskData = DB::table($dbTableName)->select('id','symbol_ce','symbol_pe','token_ce','token_pe','created_at')->where(['symbol_pe'=>$omsData->ce_symbol_name,'atm'=>0,'vmap_pe'=>'Bullish','vmap_ce'=>'Bearish'])->whereDate('created_at',$todayDate)->orderBy('id','DESC')->first();
                    }else{
                        $tradeDeskData = DB::table($dbTableName)->select('id','symbol_ce','symbol_pe','token_ce','token_pe','created_at')->where(['symbol_pe'=>$omsData->ce_symbol_name,'atm'=>0,'vmap_ce'=>'Bullish','vmap_pe'=>'Bearish'])->whereDate('created_at',$todayDate)->orderBy('id','DESC')->first();
                        
                    }
                    break;
                }
            }
        }else{
            $cnPlc = 0;
            foreach($allTradesData as $vl){
                if(strtolower($vl->oi_ce)==$strtgName || strtolower($vl->oi_pe)==$strtgName){
                    if($cnPlc==0){
                        continue;
                    }
                }
                $cnPlc = 1;
                $tradeDeskData = DB::table($dbTableName)->select('id','symbol_ce','symbol_pe','token_ce','token_pe','created_at')->where(function($q) use($strtgName){
                    $q->where('oi_ce',$strtgName)->orWhere('oi_pe',$strtgName);
                })->where('atm',0)->whereDate('created_at',$todayDate)->orderBy('id','DESC')->first();
                break;
            }
        }  
        
        if($tradeDeskData){
            //place orders
            $timeFrmTm = $tradeDeskData->created_at;
            $updateDb = 1;
            if($omsData->ce_symbol_name!=null){
                $symArr =  $this->getTokenBySymbolName($omsData->ce_symbol_name);
                $fData["tradingsymbol"] = $symArr['symbol'];
                $fData['symboltoken'] = $symArr['token'];
                $tickSize = $symArr['tick_size'];
                $lotSize = $symArr['lot_size'];
    
                $high = $ceHigh;
                $low = $ceLow;
                $closePrice = $ceClosePrice;
               
                if(!is_null($omsData->ce_pyramid_1)){
                    if($omsData->order_type=="LIMIT"){ 
                        $price =  $this->getCeLimitPrice($high,$low,38.20,$txnType,$closePrice,$tickSize);
                        $fData['price'] = $price;
                    }
                    //     $fData['quantity'] = $omsData->ce_pyramid_1;
                    $fData['quantity'] = $lotSize * $omsData->ce_pyramid_1;
                    $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                }
                if(!is_null($omsData->ce_pyramid_2)){
                    //50%
                    if($omsData->order_type=="LIMIT"){ 
                        $price =  $this->getCeLimitPrice($high,$low,50,$txnType,$closePrice,$tickSize);
                        $fData['price'] = $price;
                    }
                    // $fData['quantity'] = $omsData->ce_pyramid_2;
                    $fData['quantity'] = $lotSize * $omsData->ce_pyramid_2;
                    $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                }
                if(!is_null($omsData->ce_pyramid_3)){
                    if($omsData->order_type=="LIMIT"){ 
                        $price =  $this->getCeLimitPrice($high,$low,61.80,$txnType,$closePrice,$tickSize);
                        $fData['price'] = $price;
                    }
                    // $fData['quantity'] = $omsData->ce_pyramid_3;
                    $fData['quantity'] = $lotSize * $omsData->ce_pyramid_3;
                    $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                }
            }

            if($omsData->pe_symbol_name!=null){
                $symArr =  $this->getTokenBySymbolName($omsData->pe_symbol_name);
                $fData["tradingsymbol"] = $symArr['symbol'];
                $fData['symboltoken'] = $symArr['token'];
                $tickSize = $symArr['tick_size'];
                $lotSize = $symArr['lot_size'];
    
                $high = $peHigh;
                $low = $peLow;
                $closePrice = $peClosePrice;
               
                if(!is_null($omsData->pe_pyramid_1)){
                    if($omsData->order_type=="LIMIT"){ 
                        $price =  $this->getCeLimitPrice($high,$low,38.20,$txnType,$closePrice,$tickSize);
                        $fData['price'] = $price;
                    }
                    //     $fData['quantity'] = $omsData->pe_pyramid_1;
                    $fData['quantity'] = $lotSize * $omsData->pe_pyramid_1;
                    $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                }
                if(!is_null($omsData->pe_pyramid_2)){
                    //50%
                    if($omsData->order_type=="LIMIT"){ 
                        $price =  $this->getCeLimitPrice($high,$low,50,$txnType,$closePrice,$tickSize);
                        $fData['price'] = $price;
                    }
                    // $fData['quantity'] = $omsData->pe_pyramid_2;
                    $fData['quantity'] = $lotSize * $omsData->pe_pyramid_2;
                    $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                }
                if(!is_null($omsData->pe_pyramid_3)){
                    if($omsData->order_type=="LIMIT"){ 
                        $price =  $this->getCeLimitPrice($high,$low,61.80,$txnType,$closePrice,$tickSize);
                        $fData['price'] = $price;
                    }
                    // $fData['quantity'] = $omsData->pe_pyramid_3;
                    $fData['quantity'] = $lotSize * $omsData->pe_pyramid_3;
                    $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                }
            }

            if($updateDb==1){
                OmsConfig::where("id",$omsData->id)->update([
                    'is_api_pushed'=>1,
                    'last_time'=>date("Y-m-d H:i",strtotime($timeFrmTm.' +1 minutes')),
                    'last_id'=>$tradeDeskData->id
                ]);                            
                
            }
        }

        OmsConfig::where("id",$omsData->id)->update([
            'cron_run_at'=>date("Y-m-d H:i:s",strtotime($omsData->cron_run_at.'+ '.$omsData->pyramid_freq.'minutes'))
        ]);        
    }

    // angel api ends

    public function placeOrder(){
        $startDateTime = strtotime(date("Y-m-d 15:30:00"));
        $endDateTime = strtotime(date("Y-m-d 23:30:00"));
        $currentDateTime = strtotime(date("Y-m-d H:i:s"));
        // $todayDate="2024-02-02";
        $omsDt = OmsConfig::select('*')->with('broker')
        ->whereNotNull('last_time')->where('status',1);
        if($currentDateTime > $startDateTime && $currentDateTime < $endDateTime){
            $omsDt->whereIn('symbol_name',['CRUDEOIL','NATURALGAS','GOLD','SILVER']);
        }
        $omsDt->chunk(100, function($omgData){
            foreach ($omgData as $val) {
                if($val->broker->client_type=="Zerodha"){
                    // $this->callKiteApi($val);
                }     
                elseif($val->broker->client_type=="Angel"){
                    $this->callAngelApi($val);
                } 
            }
        });
    }
}