<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AngelApiInstrument;
use App\Models\LTP_ROUNDOFF;
use App\Traits\AngelApiAuth;
use App\Models\Crudeoil;
use Carbon\Carbon;
use DateTime;

// MCX = 9:00
// NFO = 9:30

class AngelHistorical extends Command
{
    use AngelApiAuth;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'angleHistorical:every_minute';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */

    // For MCX DATA
    function isBetween915AMto1130PM() {
        $currentTime = time();
        $startTime = strtotime('9:15 AM');
        $endTime = strtotime('11:30 PM');
        
        if ($currentTime >= $startTime && $currentTime <= $endTime) {
            return true; 
        } else {
            return false; 
        }
    }

    // For MCX AND NSE DATA
    function getLTP($exhange , $symbol , $token){
        $jwtToken =  $this->generate_access_token(); 
        $errData = [];
        if($jwtToken!=null){
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://apiconnect.angelbroking.com/order-service/rest/secure/angelbroking/order/v1/getLtpData',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "exchange": "'.$exhange.'",
                "tradingsymbol": "'.$symbol.'",
                "symboltoken": "'.$token.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'X-UserType: USER',
                'X-SourceID: WEB',
                'X-PrivateKey: '.$this->apiKey,
                'X-ClientLocalIP: '.$this->clientLocalIp,
                'X-ClientPublicIP: '.$this->clientPublicIp,
                'X-MACAddress: '.$this->macAddress,
                'Content-Type: application/json',
                'Authorization: Bearer '.$jwtToken
            ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return $errData;
            }
            $errData = json_decode($response,true);
            return $errData;
        }
        return $errData;
    }

    // For MCX
    function getUniqueStrikes($data) {
        $uniqueStrikes = array();
        $newArray = array();
        foreach ($data as $item) {
            if (!in_array($item['strike'], $uniqueStrikes, true)) {
                $uniqueStrikes[] = $item['strike'];
                $newArray[] = $item;
            }
        }
        return $newArray;
    }

    // For MCX DATA
    function getStrickData($name , $exhange , $givenLtp , $ce_adjustment, $pe_adjustment){
        $angleData = AngelApiInstrument::Where('name',$name)->where('exch_seg',$exhange)->orderBy('expiry','ASC')->whereDay('created_at', now()->day)->get()->toArray();
      
        $angleData = array_map(function ($y) {
            $y['expiry'] = strtotime($y['expiry']);
            return $y;
        }, $angleData);

        array_multisort(array_column($angleData ,'expiry'),SORT_ASC ,$angleData);

        $angleData = array_map(function ($y) {
            $y['strike'] = ($y['strike'] / 100);
            return $y;
        }, $angleData);

        if($name == "NATURALGAS"){
            $angleData = array_values(array_filter($angleData, function($x) {
                return ($x['strike'] % 60 == 0 || $x['strike'] % 70 == 0);
            }));    
        }else{
            $angleData = array_values(array_filter($angleData, function($x) {
                return $x['strike'] % 100==0;
            }));
        }

        // New Change
        $futComData = array_values(array_filter($angleData, function($x) {
            return ($x['instrumenttype'] == 'FUTCOM' && $x['expiry'] >= strtotime(date('d-m-Y')));
        }));
          
        $futComData = array_map(function ($y) {
            $y['ts_len'] = strlen($y['symbol_name']);
            return $y;
        }, $futComData);

        $expiry = [];
        $ts_len = [];

        array_multisort(array_column($futComData ,'expiry'),SORT_ASC,array_column($futComData ,'ts_len'),SORT_ASC,$futComData );
       
        $latest_expriyDate = $futComData[0]['expiry'];
        $token = $futComData[0]['token'];
        $tradingsymbol = $futComData[0]['symbol_name'];

        if(!$givenLtp){
            $ltpByApi = $this->getLTP($exchangeVal,$nameVal,$tokenVal);
            if($ltpByApi['status'] == true){
                $ltp = $ltpByApi['data']['ltp'];
            }
        }else{
            $ltp = $givenLtp;
        }
        
        $strikes = $this->getUniqueStrikes($angleData);
        

        $strikes = array_values(array_filter($strikes, function($value) {
            return $value['strike'] != null;
        })); 

        array_multisort(array_column($strikes, 'strike'),SORT_ASC,$strikes);
       
        $absprc = array_map(function ($y) use($ltp) {
            return abs($y['strike'] - $ltp);
        }, $strikes);

        $min_index = array_search(min($absprc), $absprc);

        if($min_index + $ce_adjustment < count($strikes) && $min_index + $pe_adjustment < count($strikes)){
            $closest_strike_ce = $strikes[$min_index + ($ce_adjustment)]['strike'];
            $closest_strike_pe = $strikes[$min_index + ($pe_adjustment)]['strike'];

            if ($closest_strike_ce != 0.0 && $closest_strike_pe != 0.0) {

                $strike_filter_ce  = array_filter($angleData,function($x) use($closest_strike_ce){
                    return $x['strike'] == $closest_strike_ce;
                });  
                
                $strike_filter_pe  = array_filter($angleData,function($x) use($closest_strike_pe){
                    return $x['strike'] == $closest_strike_pe;
                });  

                $ce_instrument = array_values(array_filter($strike_filter_ce, function($x) {
                    return substr($x['symbol_name'],-2) == "CE";
                }));

                $pe_instrument = array_values(array_filter($strike_filter_pe, function($x) {
                    return substr($x['symbol_name'],-2) == "PE";
                }));

                $ce_instrument = $ce_instrument[0];
                $pe_instrument = $pe_instrument[0];

                $ce_token = $ce_instrument["token"];
                $ce_symbol = $ce_instrument["symbol_name"];
                $ce_exchange = $ce_instrument["exch_seg"];
    
                $pe_token = $pe_instrument["token"];
                $pe_symbol = $pe_instrument["symbol_name"];
                $pe_exchange = $pe_instrument["exch_seg"];

                $instrumenttype = ($exhange == 'MCX') ? 'COMDTY' : 'AMXIDX';
                $index = AngelApiInstrument::Where('name',$name)->where('exch_seg',$exhange)->where('instrumenttype',$instrumenttype)->whereDay('created_at', now()->day)->first()->toArray();

                $index_token = ($index != NULL) ? $index['token'] : null;

                return  array(array($ce_symbol, $ce_token,$ce_exchange, $ce_adjustment), array($pe_symbol, $pe_token,$pe_exchange, $pe_adjustment), $index_token); 
            }else{
                return array(array(null, null), array(null, null), null);
            }
        }else{
            return array(array(null, null), array(null, null), null);
        }
    }

    // FOR NSE
    public function get_upcoming_expiry($name, $exchange){
        if ($exchange == 'NSE') {
            $exchange = 'NFO';
        }

        $angleData = AngelApiInstrument::Where('name',$name)->where('exch_seg',$exchange)->whereDay('created_at', now()->day)->get()->toArray();
        $angleData = array_map(function ($y) {
            $y['expiry'] = date("d-m-Y", strtotime($y['expiry']));  
            return $y;
        }, $angleData);

        $expiry_list = array_map(function ($y) {
            return $y['expiry']; 
        }, $angleData);

        $final_expiry = array_unique($expiry_list);
        usort($final_expiry, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });
        
        $current_date = date('d-m-Y');
        $current_day = date('d');
        $current_month = date('m');
        $current_year = date('Y');
        $upcoming_exp_date = "";
        foreach ($final_expiry as $expiry) {
            $datetime_object = date($expiry);
            if ($datetime_object > $current_date) {
                if ($current_year == date("Y", strtotime($datetime_object))) {
                    if ($current_month == date("m", strtotime($datetime_object))) {
                        $upcoming_exp_date = $datetime_object;
                        break;
                    } else {
                        $next_month = $current_month + 1;
                        if ($next_month == date("m", strtotime($datetime_object))) {
                            $upcoming_exp_date = $datetime_object;
                            break;
                        }
                    }
                }
            }
        }
        $upcoming_exp_date_str = date("d-m-Y", strtotime($upcoming_exp_date));
        $current_date_str = date("d-m-Y", strtotime($current_date));
        return array($current_date_str, $upcoming_exp_date_str);

    }

    public function get_rounded_price($price,$symbol_name, $adjustment){

        $ltp_roundoff = LTP_ROUNDOFF::WHERE('name',$symbol_name)->first();
        return round(($price / $ltp_roundoff->value) + (int)$adjustment) * $ltp_roundoff->value;
    }


    public function get_atm_strike_symbol_angel($spt_prc, $symbol_name, $nse_symbol, $exchange_name, $expiry_dates, $ce_adjustment, $pe_adjustment){

        $angleData = AngelApiInstrument::Where('name',$symbol_name)->whereDay('created_at', now()->day)->get()->toArray();
       
        $rounded_price_ce = $this->get_rounded_price($spt_prc, $symbol_name, $ce_adjustment);
        $rounded_price_pe = $this->get_rounded_price($spt_prc, $symbol_name, $pe_adjustment);

        $filters = array_map(function ($y) {
            $y['expiry'] = strtotime($y['expiry']);
            return $y;
        }, $angleData);

        try {
            $index_row = AngelApiInstrument::Where('name',$symbol_name)->where('exch_seg','NSE')->whereDay('created_at', now()->day)->get()->toArray();
            $index_token = $index_row[0]['token'];
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        if ($exchange_name == 'NSE') {
            $exchange_name = 'NFO';
        }

        $filters = array_values(array_filter($filters, function($x) use($symbol_name,$exchange_name,$expiry_dates) {
            if(($x['name'] == $symbol_name) && ($x["exch_seg"] == $exchange_name) && ($x['expiry'] <= strtotime($expiry_dates[1])) && ($x['expiry'] >= strtotime($expiry_dates[0]))){
                return $x;
            }
        }));
       
        $filters = array_map(function ($y) {
            $y['strike'] = ($y['strike'] / 100);
            return $y;
        }, $filters);

        try {
            $ce_filters = array_values(array_filter($filters, function($x) use($expiry_dates,$rounded_price_ce) {
                if(($x['expiry'] == strtotime($expiry_dates[1]))  && (substr($x['symbol_name'],-2) == "CE") && ($x["strike"] == $rounded_price_ce)){
                    return $x;
                }
            }));
         
            $ce_symbol = $ce_filters[0]["symbol_name"];
            $ce_instrument_token = $ce_filters[0]["token"];

            $pe_filters = array_values(array_filter($filters, function($x) use($expiry_dates,$rounded_price_pe) {
                if($x['expiry'] == strtotime($expiry_dates[1]) && (substr($x['symbol_name'],-2) == "PE") && ($x["strike"] == $rounded_price_pe)){
                    return $x;
                }
            }));

            $pe_symbol = $pe_filters[0]["symbol_name"];
            $pe_instrument_token = $pe_filters[0]["token"];

            return array(array($ce_symbol, $ce_instrument_token, $ce_adjustment), array($pe_symbol, $pe_instrument_token, $pe_adjustment), $index_token);

        } catch (IndexError $e) {
            return array(array(null, null), array(null, null), NULL);
        }
    }

    // AVERAGE PRICE
    // public function get_average_price($exchange , $token, $jwtToken){
    //     $errData = [];
    //     if($jwtToken!=null){
    //         $curl = curl_init();
    //         curl_setopt_array($curl, array(
    //         CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/market/v1/quote/',
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => 'POST',
    //         CURLOPT_POSTFIELDS => '{
    //             "mode": "FULL",
    //             "exchangeTokens": {
    //                 "'.$exchange.'": ["'.$token.'"]
    //             }
    //         }',
    //         CURLOPT_HTTPHEADER => array(
    //             'X-UserType: USER',
    //             'X-SourceID: WEB',
    //             'X-PrivateKey: '.$this->apiKey,
    //             'X-ClientLocalIP: '.$this->clientLocalIp,
    //             'X-ClientPublicIP: '.$this->clientPublicIp,
    //             'X-MACAddress: '.$this->macAddress,
    //             'Content-Type: application/json',
    //             'Authorization: Bearer '.$jwtToken
    //         ),
    //         ));

    //         $response = curl_exec($curl);
    //         $err = curl_error($curl);
    //         curl_close($curl);
    //         if ($err) {
    //             return $errData;
    //         }
    //         $errData = json_decode($response,true);
    //         return $errData;
    //     }
    //     return $errData;
    // }

    public function calcNewData($params,$multiplier=3,$period = 21){

        $allOHLCData = AngleHistoricalApi::where('token', $params['token'])->get()->toArray(); 
        $previousData = array_slice($allOHLCData,-1);
        $previousClose = null;
        $newhigh = $params['high'];
        $newlow = $params['low'];
        $newopen = $params['open'];
        $newclose = $params['close'];

        $trend = null;
        $strength  = null;

        if($previousData){
            $previousClose = $previousData['close'];
            $newopen = round(($params['open'] + $params['close']) / 2,2);
            $newclose = round(($params['open'] + $params['high'] + $params['low'] + $params['close']) / 4,2);
            $newhigh = max($ohlc['high'], $newopen, $newclose);
            $newlow = min($ohlc['low'], $newopen, $newclose);
        }

        
        if(count($allOHLCData) >= $period){
            $rowNum = count($allOHLCData);
            $HighAll = array_map(function($val){
                return $val['new_high'];
            },$allOHLCData);
            array_push($HighAll,$newhigh);
    
            $LowAll = array_map(function($val){
                return $val['new_low'];
            },$allOHLCData);
            array_push($LowAll,$newlow);
    
            $CloseAll = array_map(function($val){
                return $val['new_close'];
            },$allOHLCData);
            array_push($CloseAll,$newclose);

            $basicUpperBand = ($HighAll[$rowNum - 1] + $LowAll[$rowNum - 1]) / 2;  // 20
            $basicLowerBand = ($HighAll[$rowNum - 1] + $LowAll[$rowNum - 1]) / 2;  // 20
            $finalUpperBand = 0;
            $finalLowerBand = 0;
            
            // Calculate ATR
            $atr = [];
            $atr[0] = 0;

            for ($i = 1; $i < count($CloseAll); $i++) {
                $tr1 = max($HighAll[$i] - $LowAll[$i], abs($HighAll[$i] - $CloseAll[$i - 1]), abs($LowAll[$i] - $CloseAll[$i - 1]));
                $atr[$i] = ($atr[$i - 1] * ($rowNum - 1) + $tr1) / $rowNum;
                if($i % 100){
                    sleep(1);
                }
            }

            // Calculate Super Trend
            if(count($CloseAll) >= $rowNum){

                $basicUpperBand = (($HighAll[$rowNum] + $LowAll[$rowNum]) / 2 ) + ($multiplier * $atr[$rowNum]);
                $basicLowerBand = (($HighAll[$rowNum] + $LowAll[$rowNum]) / 2 ) - ($multiplier * $atr[$rowNum]);
                
                if ($basicUpperBand < $finalUpperBand || $CloseAll[$rowNum - 1] > $finalUpperBand) {
                    $finalUpperBand = $basicUpperBand;
                } else {
                    $finalUpperBand = $finalUpperBand;
                }
                
                if ($basicLowerBand > $finalLowerBand || $CloseAll[$rowNum - 1] < $finalLowerBand) {
                    $finalLowerBand = $basicLowerBand;
                } else {
                    $finalLowerBand = $finalLowerBand;
                }            
        
                if ($CloseAll[$rowNum] <= $finalUpperBand) {
                    $trend = 'Bullish';
                    $strength  = ($finalUpperBand - $CloseAll[$rowNum]) / $atr[$rowNum];
                } elseif ($CloseAll[$rowNum] >= $finalLowerBand) {
                    $trend = 'Bearish';
                    $strength = ($CloseAll[$rowNum] - $finalLowerBand) / $atr[$rowNum];
                } 
            }   
        }
        return ['trend'=>$trend,'strength'=>$strength,'high'=>$newhigh,'low'=>$newlow,'open'=>$newopen,'close'=>$newclose];
    }


    // For Both NSE AND MCX
    // public function get_historical_api_data($symbolDetails, $alltoken,$atmRange){
    //     $jwtToken =  $this->generate_access_token();
    //     $currentDate = date("Y-m-d H:i");
    //     $previousDate =  date('Y-m-d H:i',  strtotime($currentDate.' -1 minutes')); 
    //     foreach ($symbolDetails as $k => $sym){
    //         $getDetails = AngelApiInstrument::Where('token',$alltoken[$k])->first();
    //         $timeFrame = ['ONE_MINUTE','THREE_MINUTE','FIVE_MINUTE'];
    //         if($getDetails != NULL){
    //             foreach ($timeFrame as $interval) {
    //                 $currentSymbol = $sym; 
    //                 $token = $alltoken[$k]; 
    //                 $atmStr = $atmRange[$k]; 
    //                 $currentExchange =  $getDetails->exch_seg;
    //                 $errData = [];   
    //                 if($jwtToken!=null){
    //                     $curl = curl_init();
    //                     curl_setopt_array($curl, array(
    //                         CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/historical/v1/getCandleData',
    //                         CURLOPT_RETURNTRANSFER => true,
    //                         CURLOPT_ENCODING => '',
    //                         CURLOPT_MAXREDIRS => 10,
    //                         CURLOPT_TIMEOUT => 0,
    //                         CURLOPT_FOLLOWLOCATION => true,
    //                         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //                         CURLOPT_CUSTOMREQUEST => 'POST',
    //                         CURLOPT_POSTFIELDS => '{
    //                             "exchange": "'.$currentExchange.'",
    //                             "symboltoken": "'.$token.'",
    //                             "interval": "'.$interval.'",
    //                             "fromdate": "'.$previousDate.'",
    //                             "todate": "'.$currentDate.'"
    //                         }',
    //                         CURLOPT_HTTPHEADER => array(
    //                             'X-UserType: USER',
    //                             'X-SourceID: WEB',
    //                             'X-PrivateKey: '.$this->apiKey,
    //                             'X-ClientLocalIP: '.$this->clientLocalIp,
    //                             'X-ClientPublicIP: '.$this->clientPublicIp,
    //                             'X-MACAddress: '.$this->macAddress,
    //                             'Content-Type: application/json',
    //                             'Authorization: Bearer '.$jwtToken
    //                         ),
    //                     ));
            
    //                     $response = curl_exec($curl);
    //                     $err = curl_error($curl);
    //                     curl_close($curl);

    //                     if ($err) {
    //                         return $errData;
    //                     }

    //                     $response = json_decode($response,true);
                        
    //                     try {
    //                         if($response['status'] == true){
    //                             $data = $response['data'];
    //                             $res = $this->get_average_price($currentExchange,$token,$jwtToken);
    //                             if($res['status'] ==true){
    //                                 $marketData = $res['data']['fetched'];
    //                                 $avgprice = $marketData[0]['avgPrice'];
    //                                 $opnInterest = $marketData[0]['opnInterest'];
    //                             }else{
    //                                 $avgprice = NULL;
    //                                 $opnInterest = NULL;
    //                             }

    //                             $data = array_slice($data,-1);

    //                             foreach($data as $key => $item){
    //                                 if($interval == 'ONE_MINUTE'){
    //                                     $in = 1;
    //                                 }else if($interval == 'THREE_MINUTE'){
    //                                     $in = 3;
    //                                 }else{
    //                                     $in = 5;
    //                                 }

    //                                 // new calc
    //                                 $params = [
    //                                     'open'=>$data[$key][1],
    //                                     'high'=>$data[$key][2],
    //                                     'low'=>$data[$key][3],
    //                                     'close'=>$data[$key][4],
    //                                     'token'=>$token
    //                                 ];
    //                                 $newD = $this->calcNewData($params);                                    

    //                                 $timestampNew = $data[$key][0];
    //                                 $apiData = new AngleHistoricalApi;
    //                                 $apiData->token = $token;
    //                                 $apiData->symbol = $currentSymbol;
    //                                 $apiData->time_interval = $in;
    //                                 $apiData->exchange = $currentExchange;
    //                                 $apiData->fromdate = $previousDate;
    //                                 $apiData->todate = $currentDate;
    //                                 $apiData->timestamp = date('Y-m-d h:i:s', strtotime($timestampNew));
    //                                 $apiData->open = $data[$key][1];
    //                                 $apiData->high = $data[$key][2];
    //                                 $apiData->low = $data[$key][3];
    //                                 $apiData->close = $data[$key][4];
    //                                 $apiData->volume = $data[$key][5];
    //                                 $apiData->avgPrice = $avgprice;
    //                                 $apiData->opnInterest = $opnInterest;
    //                                 $apiData->atm = $atmStr;
    //                                 $apiData->trend = $newD['trend'];
    //                                 $apiData->strength = $newD['strength'];
    //                                 $apiData->new_high = $newD['high'];
    //                                 $apiData->new_low = $newD['low'];
    //                                 $apiData->new_open = $newD['open'];
    //                                 $apiData->new_close = $newD['close'];
    //                                 $apiData->save();
                                   
    //                             }
    //                         }
    //                     } catch (\Exception $th) {
    //                         //throw $th;
    //                     }
    //                 }
    //             sleep(1);
    //             }
    //         }
    //     }
    //     return $errData;
    // }

    public function handle()
    {

        $timeperiod = date("Y-m-d 09:00");
        set_time_limit(0);
        $symbol_range = 1;
        $acceptedSymbols = ['CRUDEOIL','BANKNIFTY','FINNIFTY','NATURALGAS','NIFTY','MIDCPNIFTY'];
        // $acceptedSymbols = ['CRUDEOIL'];
        $marketHolidays = ["2024-01-22", "2024-01-26", "2024-03-08", "2024-03-25", "2024-03-29", "2024-04-11",
        "2024-04-17", "2024-05-01", "2024-06-17", "2024-07-17", "2024-08-15", "2024-10-02", "2024-11-01", "2024-11-15", "2024-12-25"];

        $currentDate = date('Y-m-d');

        // Check Today Is Holiday Or Not
        if(!in_array($currentDate,$marketHolidays)){
            // For Current Time is B\w 9:15Am to 11:30pm
            if($this->isBetween915AMto1130PM()){
                // Loop For Symbols List
                $McxToken = array();
                $NfoToken = array();
                $completeResponse = [];
                // $BpoToken = array();
                foreach ($acceptedSymbols as $key => $symbolName) {
                    $angleApiInstuments = AngelApiInstrument::Where('name',$symbolName)->where(function ($query) {
                        $query->where('instrumenttype', '=', 'AMXIDX')->orWhere('instrumenttype', '=', 'COMDTY');
                    })->whereDay('created_at', now()->day)->orderBY('id','DESC')->first();

                    if($angleApiInstuments->exch_seg == "MCX"){
                        for ($i=(-$symbol_range); $i <= $symbol_range ; $i++) { 
                            $exchangeVal = $angleApiInstuments->exch_seg;
                            $tokenVal = $angleApiInstuments->token;
                            $nameVal = $angleApiInstuments->name;
                            // GET LTP by Angle Api
                            $ltpByApi = $this->getLTP($exchangeVal,$nameVal,$tokenVal);
                            if(!isset($ltpByApi['data'])){
                                continue;
                            }
                            $givenLtp = $ltpByApi['data']['ltp'];
                            $response = $this->getStrickData($nameVal,$exchangeVal,$givenLtp ,$i , $i);
                            $completeResponse[$response[0][1]] = $response[0][3];
                            $completeResponse[$response[1][1]] = $response[1][3];
                            array_push($McxToken,$response[0][1]);
                            array_push($McxToken,$response[1][1]);
                        }
                    }

                    // For NSE Exch Records
                    if($angleApiInstuments->exch_seg == "NSE"){
                        $exchangeVal = $angleApiInstuments->exch_seg;
                        $tokenVal = $angleApiInstuments->token;
                        $nameVal = $angleApiInstuments->name;
                        $ltpByApi = $this->getLTP($exchangeVal,$nameVal,$tokenVal);
                        $givenLtp = $ltpByApi['data']['ltp'];
                        $expiry_dates = $this->get_upcoming_expiry($nameVal,$exchangeVal);
                        for ($i=(-$symbol_range); $i <= $symbol_range ; $i++) { 
                            $response = $this->get_atm_strike_symbol_angel($givenLtp ,$nameVal, $nameVal , $exchangeVal , $expiry_dates, $i , $i);
                            $completeResponse[$response[0][1]] = $response[0][2];
                            $completeResponse[$response[1][1]] = $response[1][2];
                            array_push($NfoToken,$response[0][1]);
                            array_push($NfoToken,$response[1][1]);
                        }
                    }
                }

                $allTokens  =  array_merge($NfoToken,$McxToken);
                $LeftmarketData = Crudeoil::whereNotIn('token_ce',$allTokens)->orwhereNotIn('token_pe',$allTokens)->whereDate('created_at', '=', date('Y-m-d'))->groupBy('token_ce')->groupBy('token_pe')->get();

                if(count($LeftmarketData)){
                    foreach ($LeftmarketData as $k => $vl) {
                        if($vl->exhange == "MCX"){
                            $angelData = AngelApiInstrument::where('token',$vl->token)->first();
                            for ($i=(-$symbol_range); $i <= $symbol_range ; $i++) { 
                                $exchangeVal = $angelData->exch_seg;
                                $tokenVal = $angelData->token;
                                $nameVal = $angelData->name;
                                // GET LTP by Angle Api
                                $ltpByApi = $this->getLTP($exchangeVal,$nameVal,$tokenVal);
                                if(!isset($ltpByApi['data'])){
                                    continue;
                                }
                                $givenLtp = $ltpByApi['data']['ltp'];
                                $response = $this->getStrickData($nameVal,$exchangeVal,$givenLtp ,$i , $i);
                                $completeResponse[$response[0][1]] = $response[0][3];
                                $completeResponse[$response[1][1]] = $response[1][3];
    
                                array_push($McxToken,$response[0][1]);
                                array_push($McxToken,$response[1][1]);
                            }

                        }else if($vl->exhange == "NFO"){
                            $angelData = AngelApiInstrument::where('token',$vl->token)->first();
                            $exchangeVal = $angelData->exch_seg;
                            $tokenVal = $angelData->token;
                            $nameVal = $angelData->name;
                            $ltpByApi = $this->getLTP($exchangeVal,$nameVal,$tokenVal);
                            $givenLtp = $ltpByApi['data']['ltp'];
                            $expiry_dates = $this->get_upcoming_expiry($nameVal,$exchangeVal);
                            for ($i=(-$symbol_range); $i <= $symbol_range ; $i++) { 
                                $response = $this->get_atm_strike_symbol_angel($givenLtp ,$nameVal, $nameVal , $exchangeVal , $expiry_dates, $i , $i);
                                $completeResponse[$response[0][1]] = $response[0][2];
                                $completeResponse[$response[1][1]] = $response[1][2];
                                array_push($NfoToken,$response[0][1]);
                                array_push($NfoToken,$response[1][1]);
                            }

                        }
                    }
                }

                $tArr = [
                    'mode'=>'FULL',
                    'exchangeTokens'=>[
                        'NFO'=>$NfoToken,
                        'MCX'=>$McxToken
                    ]
                ];

                $jwtToken =  $this->generate_access_token();
                $errData = [];
                if($jwtToken!=null){
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/market/v1/quote/',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS =>json_encode($tArr),
                    CURLOPT_HTTPHEADER => array(
                        'X-UserType: USER',
                        'X-SourceID: WEB',
                        'X-PrivateKey: '.$this->apiKey,
                        'X-ClientLocalIP: '.$this->clientLocalIp,
                        'X-ClientPublicIP: '.$this->clientPublicIp,
                        'X-MACAddress: '.$this->macAddress,
                        'Content-Type: application/json',
                        'Authorization: Bearer '.$jwtToken
                    ),
                    ));

                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);
                    if ($err) {
                        return $errData;
                    }

                    if($response != NULL){
                        $errData = json_decode($response,true);
                        if($errData == NULL){
                            $errData['status']== false;
                        }
                    if($errData['status']== true){
                        $result = $errData['data']['fetched'];
                        array_multisort(array_column($result ,'tradingSymbol'),SORT_ASC ,$result);
                        $passedSymbols = [];
                        foreach ($result as $key => $value) {
                            if(!in_array($value['symbolToken'],$passedSymbols)){

                                $marketData = new Crudeoil;
                                $atm = "";
                                if (array_key_exists($value['symbolToken'], $completeResponse)) {
                                    $atm = $completeResponse[$value['symbolToken']];
                                }

                                // For PE Symbols
                                $date1 = new DateTime($timeperiod);
                                $t = $value['exchFeedTime'];
                                $date2 = new DateTime($t);
                                $interval = $date1->diff($date2);
                                $interval->format('%H:%I');
                                $hour  = $interval->h;
                                $minute  = $interval->i;
                                $finalTimeFrame = $hour*60 + $minute;

                                $for3min = "";
                                if($finalTimeFrame % 3 == 0){
                                    $for3min = 3;
                                }

                                $for5min = "";
                                if($finalTimeFrame % 5 == 0){
                                    $for5min = 5;
                                }
                                // dd($finalTimeFrame,$hour,$minute);
                                // $time1 = strtotime($timeperiod);
                                // $time2 = strtotime($value['lastTradeQty']);
                                // $timeDiff = date('H:i',$time2-$time1);

                                // list($hour, $minute) = explode(':', $timeDiff);
                                // $finalTimeFrame = $hour*60 + $minute;

                                // $for3min = "";
                                // if($finalTimeFrame % 3 == 0){
                                //     $for3min = 3;
                                // }

                                // $for5min = "";
                                // if($finalTimeFrame % 5 == 0){
                                //     $for5min = 5;
                                // }

                                // For CE & PE SYMBOLS
                                $getSymbolType = substr($value['tradingSymbol'],-2);
                                if($getSymbolType == "PE"){
                                    $baseValue = substr($value['tradingSymbol'],0,-2);
                                    $baseValue = $baseValue."CE";
                                    $symbolSibling = array_search($baseValue, array_column($result, 'tradingSymbol'));

                                    $vmap_pe = "Bearish";
                                    if($value['ltp'] > $value['avgPrice']){
                                        $vmap_pe = "Bullish";
                                    }

                                    $vmap_ce = "Bearish";
                                    if($result[$symbolSibling]['ltp'] > $result[$symbolSibling]['avgPrice']){
                                        $vmap_ce = "Bullish";
                                    }

                                    array_push($passedSymbols,$value['symbolToken']);
                                    array_push($passedSymbols,$result[$symbolSibling]['symbolToken']);

                                    $marketData->token_pe = $value['symbolToken'];
                                    $marketData->token_ce = $result[$symbolSibling]['symbolToken'];
                                    $marketData->symbol_pe = $value['tradingSymbol'];
                                    $marketData->symbol_ce = $result[$symbolSibling]['tradingSymbol'];
                                    $marketData->exchange = $value['exchange'];
                                    $marketData->atm = $atm;
                                    $marketData->for3Min = $for3min;
                                    $marketData->for5Min = $for5min;
                                    $marketData->ltp_pe = $value['ltp'];
                                    $marketData->ltp_ce = $result[$symbolSibling]['ltp'];
                                    $marketData->open_pe = $value['open'];
                                    $marketData->open_ce = $result[$symbolSibling]['open'];
                                    $marketData->high_pe = $value['high'];
                                    $marketData->high_ce = $result[$symbolSibling]['high'];
                                    $marketData->low_pe = $value['low'];
                                    $marketData->low_ce = $result[$symbolSibling]['low'];
                                    $marketData->close_pe = $value['close'];
                                    $marketData->close_ce = $result[$symbolSibling]['close'];
                                    $marketData->lastTradeQty_pe = $value['lastTradeQty'];
                                    $marketData->lastTradeQty_ce = $result[$symbolSibling]['lastTradeQty'];
                                    $marketData->exchFeedTime_pe = $value['exchFeedTime'];
                                    $marketData->exchFeedTime_ce = $result[$symbolSibling]['exchFeedTime'];
                                    $marketData->exchTradeTime_pe = $value['exchTradeTime'];
                                    $marketData->exchTradeTime_ce = $result[$symbolSibling]['exchTradeTime'];
                                    $marketData->netChange_pe = $value['netChange'];
                                    $marketData->netChange_ce = $result[$symbolSibling]['netChange'];
                                    $marketData->percentChange_pe = $value['percentChange'];
                                    $marketData->percentChange_ce = $result[$symbolSibling]['percentChange'];
                                    $marketData->avgPrice_pe = $value['avgPrice'];
                                    $marketData->avgPrice_ce = $result[$symbolSibling]['avgPrice'];
                                    $marketData->tradeVolume_pe = $value['tradeVolume'];
                                    $marketData->tradeVolume_ce = $result[$symbolSibling]['tradeVolume'];
                                    $marketData->opnInterest_pe = $value['opnInterest'];
                                    $marketData->opnInterest_ce = $result[$symbolSibling]['opnInterest'];
                                    $marketData->lowerCircuit_pe = $value['lowerCircuit'];
                                    $marketData->lowerCircuit_ce = $result[$symbolSibling]['lowerCircuit'];
                                    $marketData->upperCircuit_pe = $value['upperCircuit'];
                                    $marketData->upperCircuit_ce = $result[$symbolSibling]['upperCircuit'];
                                    $marketData->totBuyQuan_pe = $value['totBuyQuan'];
                                    $marketData->totBuyQuan_ce = $result[$symbolSibling]['totBuyQuan'];
                                    $marketData->totSellQuan_pe = $value['totSellQuan'];
                                    $marketData->totSellQuan_ce = $result[$symbolSibling]['totSellQuan'];
                                    $marketData->WeekLow52_pe = $value['52WeekLow'];
                                    $marketData->WeekLow52_ce = $result[$symbolSibling]['52WeekLow'];
                                    $marketData->WeekHigh52_pe = $value['52WeekHigh'];
                                    $marketData->WeekHigh52_ce = $result[$symbolSibling]['52WeekHigh'];
                                    $marketData->vmap_pe = $vmap_pe;
                                    $marketData->vmap_ce = $vmap_ce;
                                    $marketData->save();

                                }else{
                                    $baseValue = substr($value['tradingSymbol'],0,-2);
                                    $baseValue = $baseValue."PE";

                                    $symbolSibling = array_search($baseValue, array_column($result, 'tradingSymbol'));

                                    $vmap_ce = "Bearish";
                                    if($value['ltp'] > $value['avgPrice']){
                                        $vmap_ce = "Bullish";
                                    }

                                    $vmap_pe = "Bearish";
                                    if($result[$symbolSibling]['ltp'] > $result[$symbolSibling]['avgPrice']){
                                        $vmap_pe = "Bullish";
                                    }

                                    array_push($passedSymbols,$value['symbolToken']);
                                    array_push($passedSymbols,$result[$symbolSibling]['symbolToken']);

                                    // For CE Symbols
                                    $marketData->token_ce = $value['symbolToken'];
                                    $marketData->token_pe = $result[$symbolSibling]['symbolToken'];
                                    $marketData->symbol_ce = $value['tradingSymbol'];
                                    $marketData->symbol_pe = $result[$symbolSibling]['tradingSymbol'];
                                    $marketData->exchange = $value['exchange'];
                                    $marketData->atm = $atm;
                                    $marketData->for3Min = $for3min;
                                    $marketData->for5Min = $for5min;
                                    $marketData->ltp_ce = $value['ltp'];
                                    $marketData->ltp_pe = $result[$symbolSibling]['ltp'];
                                    $marketData->open_ce = $value['open'];
                                    $marketData->open_pe = $result[$symbolSibling]['open'];
                                    $marketData->high_ce = $value['high'];
                                    $marketData->high_pe = $result[$symbolSibling]['high'];
                                    $marketData->low_ce = $value['low'];
                                    $marketData->low_pe = $result[$symbolSibling]['low'];
                                    $marketData->close_ce = $value['close'];
                                    $marketData->close_pe = $result[$symbolSibling]['close'];
                                    $marketData->lastTradeQty_ce = $value['lastTradeQty'];
                                    $marketData->lastTradeQty_pe = $result[$symbolSibling]['lastTradeQty'];
                                    $marketData->exchFeedTime_ce = $value['exchFeedTime'];
                                    $marketData->exchFeedTime_pe = $result[$symbolSibling]['exchFeedTime'];
                                    $marketData->exchTradeTime_ce = $value['exchTradeTime'];
                                    $marketData->exchTradeTime_pe = $result[$symbolSibling]['exchTradeTime'];
                                    $marketData->netChange_ce = $value['netChange'];
                                    $marketData->netChange_pe = $result[$symbolSibling]['netChange'];
                                    $marketData->percentChange_ce = $value['percentChange'];
                                    $marketData->percentChange_pe = $result[$symbolSibling]['percentChange'];
                                    $marketData->avgPrice_ce = $value['avgPrice'];
                                    $marketData->avgPrice_pe = $result[$symbolSibling]['avgPrice'];
                                    $marketData->tradeVolume_ce = $value['tradeVolume'];
                                    $marketData->tradeVolume_pe = $result[$symbolSibling]['tradeVolume'];
                                    $marketData->opnInterest_ce = $value['opnInterest'];
                                    $marketData->opnInterest_pe = $result[$symbolSibling]['opnInterest'];
                                    $marketData->lowerCircuit_ce = $value['lowerCircuit'];
                                    $marketData->lowerCircuit_pe = $result[$symbolSibling]['lowerCircuit'];
                                    $marketData->upperCircuit_ce = $value['upperCircuit'];
                                    $marketData->upperCircuit_pe = $result[$symbolSibling]['upperCircuit'];
                                    $marketData->totBuyQuan_ce = $value['totBuyQuan'];
                                    $marketData->totBuyQuan_pe = $result[$symbolSibling]['totBuyQuan'];
                                    $marketData->totSellQuan_ce = $value['totSellQuan'];
                                    $marketData->totSellQuan_pe = $result[$symbolSibling]['totSellQuan'];
                                    $marketData->WeekLow52_ce = $value['52WeekLow'];
                                    $marketData->WeekLow52_pe = $result[$symbolSibling]['52WeekLow'];
                                    $marketData->WeekHigh52_ce = $value['52WeekHigh'];
                                    $marketData->WeekHigh52_pe = $result[$symbolSibling]['52WeekHigh'];
                                    $marketData->vmap_ce = $vmap_ce;
                                    $marketData->vmap_pe = $vmap_pe;
                                    $marketData->save();
                                    
                                }
                            }

                            // $marketData->token_ce = $value['symbolToken'];
                            // $marketData->token_pe = $result[$symbolSibling]['symbolToken'];

                            // $marketData->exchange = $value['exchange'];
                            // $marketData->atm = $atm;
                            // $marketData->ltp_ce = $value['ltp'];
                            // $marketData->ltp_pe = $value['ltp'];
                            // $marketData->open_ce = $value['open'];
                            // $marketData->open_pe = $value['open'];
                            // $marketData->high_ce = $value['high'];
                            // $marketData->high_pe = $value['high'];
                            // $marketData->low_ce = $value['low'];
                            // $marketData->low_pe = $value['low'];
                            // $marketData->close_ce = $value['close'];
                            // $marketData->close_pe = $value['close'];




                            // $marketData->lastTradeQty_ce = $value['lastTradeQty'];
                            // $marketData->lastTradeQty_pe = $value['lastTradeQty'];
                            // $marketData->exchFeedTime_ce = $value['exchFeedTime'];
                            // $marketData->exchFeedTime_pe = $value['exchFeedTime'];
                            // $marketData->exchTradeTime_ce = $value['exchTradeTime'];
                            // $marketData->exchTradeTime_pe = $value['exchTradeTime'];
                            // $marketData->netChange_ce = $value['netChange'];
                            // $marketData->netChange_pe = $value['netChange'];
                            // $marketData->percentChange_ce = $value['percentChange'];
                            // $marketData->percentChange_pe = $value['percentChange'];
                            // $marketData->avgPrice_ce = $value['avgPrice'];
                            // $marketData->avgPrice_pe = $value['avgPrice'];
                            // $marketData->tradeVolume_ce = $value['tradeVolume'];
                            // $marketData->tradeVolume_pe = $value['tradeVolume'];
                            // $marketData->opnInterest_ce = $value['opnInterest'];
                            // $marketData->opnInterest_pe = $value['opnInterest'];
                            // $marketData->lowerCircuit_ce = $value['lowerCircuit'];
                            // $marketData->lowerCircuit_pe = $value['lowerCircuit'];
                            // $marketData->upperCircuit_ce = $value['upperCircuit'];
                            // $marketData->upperCircuit_pe = $value['upperCircuit'];
                            // $marketData->totBuyQuan_ce = $value['totBuyQuan'];
                            // $marketData->totBuyQuan_pe = $value['totBuyQuan'];
                            // $marketData->totSellQuan_ce = $value['totSellQuan'];
                            // $marketData->totSellQuan_pe = $value['totSellQuan'];
                            // $marketData->WeekLow52_ce = $value['52WeekLow'];
                            // $marketData->WeekLow52_pe = $value['52WeekLow'];
                            // $marketData->WeekHigh52_ce = $value['52WeekHigh'];
                            // $marketData->WeekHigh52_pe = $value['52WeekHigh'];
                            // $marketData->vmap_ce = $vmap;
                            // $marketData->vmap_pe = $vmap;
                            // $marketData->save();
                        }
                        // dd($passedSymbols);
                    }
                    }
                }
                return "Completed";
            }else{
                return null;
            }
        }else{
           return null;
        }   
    }
}
