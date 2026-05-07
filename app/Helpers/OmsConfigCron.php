<?php
namespace App\Helpers;
use App\Models\ZerodhaInstrument;
use App\Models\OmsConfig;
use App\Helpers\KiteConnectCls;
use App\Models\OrderBook;
use App\Models\AngelApiInstrument;
use App\Models\SiteVariable;

class OmsConfigCron{
    
    public function __construct()
    {
        set_time_limit(0);
    }

    public function calculateTickSize($price,$tickSize){
        $roundedPrice = round($price / $tickSize) * $tickSize;
        return $roundedPrice; 
    }

    public function getCeLimitPrice($high,$low,$per,$type,$closePrice,$tickSize){
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

            $vwap_CE_signal = array_reverse($data['vwap_CE_signal']);
            $vwap_PE_signal = array_reverse($data['vwap_PE_signal']);


            if($vvl->ce==$omsData->ce_symbol_name){
                foreach($sellAct as $k=>$v){
                    $strtgName = strtolower($omsData->strategy_name);
                    if(in_array($strtgName,['bullish','bearish'])){
                        if((strtolower($vwap_CE_signal[$k])==$strtgName || strtolower($vwap_PE_signal[$k])==$strtgName)){
                            // dd('matched');
                            if((strtolower($vwap_CE_signal[$k])=='bullish' && strtolower($vwap_PE_signal[$k])=='bearish') || (strtolower($vwap_CE_signal[$k])=='bearish' && strtolower($vwap_PE_signal[$k])=='bullish')){
                                $ceHigh = $datahigh_CE[$k];
                                $ceLow = $datalow_CE[$k];
                                $ceClosePrice = $dataclose_CE[$k];
                                break;
                            }
                        }
                    }else{
                        if((strtolower($v)==$strtgName || strtolower($buyAct[$k])==$strtgName)){
                            $ceHigh = $datahigh_CE[$k];
                            $ceLow = $datalow_CE[$k];
                            $ceClosePrice = $dataclose_CE[$k];
                            break;
                        }
                    }
                }
            }
            if($vvl->pe==$omsData->pe_symbol_name){
                foreach($sellAct as $k=>$v){
                    $strtgName = strtolower($omsData->strategy_name);
                    if(in_array($strtgName,['bullish','bearish'])){
                        if((strtolower($vwap_CE_signal[$k])==$strtgName || strtolower($vwap_PE_signal[$k])==$strtgName)){
                            if((strtolower($vwap_CE_signal[$k])=='bullish' && strtolower($vwap_PE_signal[$k])=='bearish') || (strtolower($vwap_CE_signal[$k])=='bearish' && strtolower($vwap_PE_signal[$k])=='bullish')){
                                $peHigh = $datahigh_PE[$k];
                                $peLow = $datalow_PE[$k];
                                $peClosePrice = $dataclose_PE[$k];
                                break;

                            }
                        }
                    }else{
                        if(strtolower($v)==strtolower($omsData->strategy_name) || strtolower($buyAct[$k])==strtolower($omsData->strategy_name)){
                            $peHigh = $datahigh_PE[$k];
                            $peLow = $datalow_PE[$k];
                            $peClosePrice = $dataclose_PE[$k];
                            break;
                        }
                    }

                }
            }
        }

        foreach($signalData as $vvl){
            if($breakForeach == 1){
                break;
            }
            $data = json_decode($vvl->data,true);
            $strategyArr = array_slice($data['Strategy_name'],-1);
            $buyActionArr = array_slice($data['BUY_Action'],-1);
            $sellActionArr = array_slice($data['SELL_Action'],-1);
            $timeActArr = array_slice($data['time'],-1);
            $dateActArr = array_slice($data['Date'],-1);

            $vwap_CE_signal = array_slice($data['vwap_CE_signal'],-1);
                $vwap_PE_signal = array_slice($data['vwap_PE_signal'],-1);

            foreach($strategyArr as $key=>$v){
                // if(strtolower($v)==strtolower($omsData->strategy_name)){

                $isPlaceOrderB = 0;
                $strtgName = strtolower($omsData->strategy_name);
                if(in_array($strtgName,['bullish','bearish'])){
                    // dd(strtolower($vwap_PE_signal[$key]));
                    if((strtolower($vwap_CE_signal[$key])=='bullish' && strtolower($vwap_PE_signal[$key])=='bearish') || (strtolower($vwap_CE_signal[$key])=='bearish' && strtolower($vwap_PE_signal[$key])=='bullish')){
                        $isPlaceOrderB = 1;
                    }
                    if($isPlaceOrderB!=1){
                        continue;
                    }
                }
                if((strtolower($buyActionArr[$key])==strtolower($omsData->strategy_name)) || (strtolower($sellActionArr[$key])==strtolower($omsData->strategy_name)) || $isPlaceOrderB==1){
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
                            'last_time'=>date("Y-m-d H:i",strtotime($timeFrmTm.' +5 minutes'))
                        ]);
                        $breakForeach = 1;
                        break;
                    }
                    
                }
            }
        }
        OmsConfig::where("id",$omsData->id)->update([
            'cron_run_at'=>date("Y-m-d H:i:s",strtotime($omsData->cron_run_at.'+ '.$omsData->pyramid_freq.'minutes'))
        ]);
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
                $bookOBj->status_message = "order failed-".$response.'-'.json_encode($apiData);
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
                    // dd($response);
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
                    $bookOBj->status_message = "order failed-".$response['message'].'-'.json_encode($apiData);
                    $bookOBj->order_datetime = date("Y-m-d H:i:s");
                    $bookOBj->user_id = $broker->user_id;
                    $bookOBj->save();
                    return 1;
                }
            }
        }

    }

    public function getTokenBySymbolName($symbName){
       $data =  AngelApiInstrument::select('trading_symbol','zi.exchange_token','lotsize','symbol_name','angel_api_instruments.tick_size')->join('zerodha_instruments as zi','zi.exchange_token','token')->where('trading_symbol',$symbName)->first();
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

    public function callAngelApi($signalData,object $omsData){
        $mcxSymArr = ['CRUDEOIL','NATURALGAS','GOLD','SILVER'];
        $txnType = $omsData->txn_type;
        $extType = in_array($omsData->symbol_name,$mcxSymArr) ? "MCX" : "NFO";
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

        $breakForeach = 0;

        $ceHigh = 0;
        $ceLow = 0;
        $ceClosePrice = 0;

        $peHigh = 0;
        $peLow = 0;
        $peClosePrice = 0;
        
        // dd($signalData);
        foreach($signalData as $vvl){
            $data = json_decode($vvl->data,true);
            $datahigh_CE = array_reverse($data['high_CE']);
            $datalow_CE = array_reverse($data['low_CE']);
            $datahigh_PE = array_reverse($data['high_PE']);
            $datalow_PE = array_reverse($data['low_PE']);

            $dataclose_CE = array_reverse($data['close_CE']);
            // dd($dataclose_CE);
            $dataclose_PE = array_reverse($data['close_PE']);
            $buyAct = array_reverse($data['BUY_Action']);
            $sellAct = array_reverse($data['SELL_Action']);
            
            $vwap_CE_signal = array_reverse($data['vwap_CE_signal']);
            $vwap_PE_signal = array_reverse($data['vwap_PE_signal']);


            if($vvl->ce==$omsData->ce_symbol_name){
                foreach($sellAct as $k=>$v){
                    $strtgName = strtolower($omsData->strategy_name);
                    if(in_array($strtgName,['bullish','bearish'])){
                        if((strtolower($vwap_CE_signal[$k])==$strtgName)){
                            // dd('matched');
                            if($strtgName=='bullish'){
                                if((strtolower($vwap_CE_signal[$k])=='bullish' && strtolower($vwap_PE_signal[$k])=='bearish')){
                                    $ceHigh = $datahigh_CE[$k];
                                    $ceLow = $datalow_CE[$k];
                                    $ceClosePrice = $dataclose_CE[$k];
                                    break;
                                }
                            }else{
                                if((strtolower($vwap_CE_signal[$k])=='bearish' && strtolower($vwap_PE_signal[$k])=='bullish')){
                                    $ceHigh = $datahigh_CE[$k];
                                    $ceLow = $datalow_CE[$k];
                                    $ceClosePrice = $dataclose_CE[$k];
                                    break;
                                }
                            }
                            
                        }
                    }else{
                        if((strtolower($v)==$strtgName || strtolower($buyAct[$k])==$strtgName)){
                            $ceHigh = $datahigh_CE[$k];
                            $ceLow = $datalow_CE[$k];
                            $ceClosePrice = $dataclose_CE[$k];
                            break;
                        }
                    }
                }
            }

            // echo $ceHigh.''.$ceLow.''.$ceClosePrice;die;


            if(($vvl->pe==$omsData->pe_symbol_name) && !is_null($omsData->pe_symbol_name)){
                foreach($sellAct as $k=>$v){
                    $strtgName = strtolower($omsData->strategy_name);
                    if(in_array($strtgName,['bullish','bearish'])){
                        if(strtolower($vwap_PE_signal[$k])==$strtgName){
                            if($strtgName=='bullish'){
                                if((strtolower($vwap_PE_signal[$k])=='bullish' && strtolower($vwap_CE_signal[$k])=='bearish')){
                                    $peHigh = $datahigh_PE[$k];
                                    $peLow = $datalow_PE[$k];
                                    $peClosePrice = $dataclose_PE[$k];
                                    break;
                                }
                            }else{
                                if((strtolower($vwap_PE_signal[$k])=='bearish' && strtolower($vwap_CE_signal[$k])=='bullish')){
                                    $peHigh = $datahigh_PE[$k];
                                    $peLow = $datalow_PE[$k];
                                    $peClosePrice = $dataclose_PE[$k];
                                    break;
                                }
                            }

                        }
                    }else{
                        if(strtolower($v)==strtolower($omsData->strategy_name) || strtolower($buyAct[$k])==strtolower($omsData->strategy_name)){
                            $peHigh = $datahigh_PE[$k];
                            $peLow = $datalow_PE[$k];
                            $peClosePrice = $dataclose_PE[$k];
                            break;
                        }
                    }

                }
            }
        }

        // echo $ceHigh.''.$ceLow.''.$ceClosePrice;die;
        
        foreach($signalData as $vvl){
           if(isset($vvl->atm) && $vvl->atm=="ATM"){
                if($breakForeach == 1){
                    break;
                }
                $data = json_decode($vvl->data,true);
                $strategyArr = array_slice($data['Strategy_name'],-1);
                $buyActionArr = array_slice($data['BUY_Action'],-1);
                $sellActionArr = array_slice($data['SELL_Action'],-1);
                $timeActArr = array_slice($data['time'],-1);
                $dateActArr = array_slice($data['Date'],-1);

                $vwap_CE_signal = array_slice($data['vwap_CE_signal'],-1);
                $vwap_PE_signal = array_slice($data['vwap_PE_signal'],-1);

                // dd($vwap_PE_signal);

                foreach($strategyArr as $key=>$v){
                    // if(strtolower($v)==strtolower($omsData->strategy_name)){
                    $isPlaceOrderB = 0;
                    $strtgName = strtolower($omsData->strategy_name);
                    // echo $strtgName;die;
                    if(in_array($strtgName,['bullish','bearish'])){

                        // dd(strtolower($vwap_PE_signal[$key]));
                        // if((strtolower($vwap_CE_signal[$key])=='bullish' && strtolower($vwap_PE_signal[$key])=='bearish') || (strtolower($vwap_CE_signal[$key])=='bearish' && strtolower($vwap_PE_signal[$key])=='bullish')){
                        //     $isPlaceOrderB = 1;
                        // }
                        if($omsData->ce_symbol_name!=null){
                            if($strtgName=='bullish'){
                               
                                if((strtolower($vwap_CE_signal[$key])=='bullish' && strtolower($vwap_PE_signal[$key])=='bearish')){
                                    $isPlaceOrderB = 1;
                                }
                            }else{
                                if((strtolower($vwap_CE_signal[$key])=='bearish' && strtolower($vwap_PE_signal[$key])=='bullish')){
                                    $isPlaceOrderB = 1;
                                }
                            }
                        }

                        if($omsData->pe_symbol_name!=null){
                            if($strtgName=='bullish'){
                                if((strtolower($vwap_CE_signal[$key])=='bearish' && strtolower($vwap_PE_signal[$key])=='bullish')){
                                    $isPlaceOrderB = 1;
                                }
                            }else{
                                if((strtolower($vwap_CE_signal[$key])=='bullish' && strtolower($vwap_PE_signal[$key])=='bearish')){
                                    $isPlaceOrderB = 1;
                                }
                            }
                        }


                        if($isPlaceOrderB!=1){
                            continue;
                        }
                    }

                    // dd('asdfasdfsdaf');

                    if((strtolower($buyActionArr[$key])==strtolower($omsData->strategy_name)) || (strtolower($sellActionArr[$key])==strtolower($omsData->strategy_name)) || $isPlaceOrderB==1){
                        $fData["tradingsymbol"] = $omsData->ce_symbol_name;
                        $symArr =  $this->getTokenBySymbolName($omsData->ce_symbol_name);
                        $fData["tradingsymbol"] = $symArr['symbol'];
                        $fData['symboltoken'] = $symArr['token'];
                        $tickSize = $symArr['tick_size'];

                        $dateStr = date("Y-m-d",($dateActArr[$key]/1000));

                        $timeFrmTm = $dateStr." ".$timeActArr[$key];
                        
                        // $lotSize = $extType=='MCX' ? ($symArr['lot_size']/100) : $symArr['lot_size'];
                        $lotSize = $symArr['lot_size'];

                        $high = $ceHigh;
                        $low = $ceLow;
                        $closePrice = $ceClosePrice;

                        $updateDb = 1;

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

                        //

                        $symArr =  $this->getTokenBySymbolName($omsData->pe_symbol_name);
                        // dd($symArr);
                        $fData["tradingsymbol"] = $symArr['symbol'];
                        $fData['symboltoken'] = $symArr['token'];
                        $tickSize = $symArr['tick_size'];
                        // $high = $highPEArr[$key];
                        // $low = $lowPEArr[$key];
                        // $closePrice = $closePEArr[$key];

                        $high = $peHigh;
                        $low = $peLow;
                        $closePrice = $peClosePrice;

                        // $lotSize = $extType=='MCX' ? ($symArr['lot_size']/100) : $symArr['lot_size'];
                        $lotSize = $symArr['lot_size'];

                        if(!is_null($omsData->pe_pyramid_1)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getPeLimitPrice($high,$low,38.20,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            // $fData['quantity'] = $omsData->pe_pyramid_1;
                            $fData['quantity'] = $lotSize *  $omsData->pe_pyramid_1;
                            $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                        }
                        if(!is_null($omsData->pe_pyramid_2)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getPeLimitPrice($high,$low,50,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            // $fData['quantity'] = $omsData->pe_pyramid_2;
                            $fData['quantity'] = $lotSize * $omsData->pe_pyramid_2;
                            $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                        }
                        if(!is_null($omsData->pe_pyramid_3)){
                            if($omsData->order_type=="LIMIT"){ 
                                $price =  $this->getPeLimitPrice($high,$low,61.80,$txnType,$closePrice,$tickSize);
                                $fData['price'] = $price;
                            }
                            // $fData['quantity'] = $omsData->pe_pyramid_3;
                            $fData['quantity'] = $lotSize * $omsData->pe_pyramid_3;
                            $updateDb = $this->postPlaceOrderAngel($omsData->broker,$fData);
                        }

                        if($updateDb==1){
                            OmsConfig::where("id",$omsData->id)->update([
                                'is_api_pushed'=>1,
                                // 'last_time'=>$timeFrmTm
                                'last_time'=>date("Y-m-d H:i",strtotime($timeFrmTm.' +5 minutes'))
                            ]);                            
                            
                        }
                        $breakForeach = 1;
                            break;
                    }
                }
            }
        }
        OmsConfig::where("id",$omsData->id)->update([
            'cron_run_at'=>date("Y-m-d H:i:s",strtotime($omsData->cron_run_at.'+ '.$omsData->pyramid_freq.'minutes'))
        ]);
    }

    // angel api ends

    public function placeOrder(){
        $todayDate=date("Y-m-d");
        $startDateTime = strtotime(date("Y-m-d 15:30:00"));
        $endDateTime = strtotime(date("Y-m-d 23:30:00"));
        $currentDateTime = strtotime(date("Y-m-d H:i:s"));
        // $todayDate="2024-02-09";
        $omsDt = OmsConfig::select('*')->with('broker')
        ->where(['is_api_pushed'=>0,'status'=>1]);
        if($currentDateTime > $startDateTime && $currentDateTime < $endDateTime){
            $omsDt->whereIn('symbol_name',['CRUDEOIL','NATURALGAS','GOLD','SILVER']);
        }
        $omsDt->chunk(100, function($omgData) use($todayDate){
            foreach ($omgData as $val) {
                $signalData = \DB::connection('mysql_rm')->table($val->symbol_name)->select('*')->where(['date'=>$todayDate,'timeframe'=>$val->signal_tf])->get();
                // dd($signalData);
                $pFreq = "-".$val->pyramid_freq." minutes";
                $nextRun = strtotime(date("Y-m-d H:i:s",strtotime($pFreq)));
                $lstRun = strtotime($val->cron_run_at);
                // if($nextRun > $lstRun){
                    if(count($signalData)){
                        $fffData = [];
                       
                        foreach($signalData as $vvvl){
                            if(isset($vvvl->atm) && ($vvvl->atm=="ATM" || $vvvl->atm=="ATM-1" || $vvvl->atm=="ATM+1")){
                                $fffData[] = $vvvl;
                            }
                        }    
                        if(count($fffData)){
                            $omsData = $val;
                            if($omsData->broker->client_type=="Zerodha"){
                                $this->callKiteApi($fffData,$omsData);
                            }     
                            elseif($omsData->broker->client_type=="Angel"){
                                $this->callAngelApi($fffData,$omsData);
                            }   
                        }
                    }
                // }
            }
        });
    }
}