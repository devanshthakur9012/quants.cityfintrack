<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;;
use App\Models\AngelApiInstrument;
use App\Models\LTP_ROUNDOFF;
use App\Traits\AngelApiAuth;
use App\Models\Crudeoil;
use Carbon\Carbon;
use DateTime;

class CrudeoilCommand extends Command
{
    use AngelApiAuth;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crudeoil:every_minute';

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

    
    // Function to calculate Average True Range (ATR)
    function calculateATR($ltpData, $period) {
        // Calculate True Range (TR) for each period
        $trueRanges = array();
        for ($i = 1; $i < count($ltpData); $i++) {
            $trueRanges[] = max($ltpData[$i]["high"] - $ltpData[$i]["low"], abs($ltpData[$i]["high"] - $ltpData[$i - 1]["close"]), abs($ltpData[$i]["low"] - $ltpData[$i - 1]["close"]));
        }
        // Calculate Average True Range (ATR) over the period
        $atr = array_sum(array_slice($trueRanges, 0, $period)) / $period;
        return $atr;

    }

    // Function to calculate SuperTrend bands and signals
    // function calculateSuperTrend($ltpData, $period, $multiplier) {
    //     $signals = array(); // Array to store buy/sell signals
    //     $ub = 0; // Initial Upper Band
    //     $lb = 0; // Initial Lower Band
    //     foreach ($ltpData as $index => $ltp) {
    //         if ($index >= $period) {
    //             $atr = $this->calculateATR(array_slice($ltpData, $index - $period, $period), $period);
    //             if ($index == $period) {
    //                 $ub = $ltp["high"] + ($multiplier * $atr);
    //                 $lb = $ltp["low"] - ($multiplier * $atr);
    //             } else {
    //                 $ub = min($ltp["high"] + ($multiplier * $atr), $ub);
    //                 $lb = max($ltp["low"] - ($multiplier * $atr), $lb);
    //             }
    //             if ($ltp["close"] > $ub) {
    //                 $signals[] = "Buy"; // Generate Buy Signal
    //             } elseif ($ltp["close"] < $lb) {
    //                 $signals[] = "Sell"; // Generate Sell Signal
    //             } else {
    //                 $signals[] = "Hold"; // No Signal
    //             }
    //         } else {
    //             $signals[] = "Hold"; // No Signal during initialization period
    //         }
    //     }

    //     return $signals;
    // }

    function calculateSuperTrend($ltpData, $period, $multiplier) {
        $signals = array(); // Array to store buy/sell signals
        $previousClose = 0;
        $previousFinalUpperBand = 0;
        $previousFinalLowerBand = 0;
        $finalUpperBand = 0;
        $finalLowerBand = 0;
        $previousSuperTrend = 0;
        $superTrend = 0;
        foreach ($ltpData as $index => $ltp) {
            if ($index >= $period) {
                // For ATR
                $atr = $this->calculateATR(array_slice($ltpData, $index - $period, $period), $period);

                // BASIC UPPER & LOWER BAND
                $basicUpperBand = ($ltp["high"] + $ltp["low"]) / 2 + ($multiplier * $atr);
                $basicLowerBand = ($ltp["high"] + $ltp["low"]) / 2 - ($multiplier * $atr);

                // FINAL UPPER & LOWER BAND
                if (($basicUpperBand < $previousFinalUpperBand) || ($previousClose > $previousFinalUpperBand)) {
                    $finalUpperBand = $basicUpperBand;
                } else {
                    $finalUpperBand = $previousFinalUpperBand;
                }
                
                if (($basicLowerBand > $previousFinalLowerBand) || ($previousClose < $previousFinalLowerBand)) {
                    $finalLowerBand = $basicLowerBand;
                } else {
                    $finalLowerBand = $previousFinalLowerBand;
                }   

                if(($previousSuperTrend == $previousFinalUpperBand) && ($ltp["close"] <= $finalUpperBand)){
                    $superTrend = $finalUpperBand;
                }else{
                    if(($previousSuperTrend == $previousFinalUpperBand) && ($ltp["close"] > $finalUpperBand)){
                        $superTrend = $finalLowerBand;
                    }else{
                        if(($previousSuperTrend == $previousFinalLowerBand) && ($ltp["close"] >= $finalLowerBand)){
                            $superTrend = $finalLowerBand;
                        }else{
                            if(($previousSuperTrend == $previousFinalLowerBand) && ($ltp["close"] < $finalLowerBand)){
                                $superTrend = $finalLowerBand;
                            }
                        }
                    }
                }

                if($ltp["close"] > $superTrend){
                    $signals[] = "Buy";
                }else{
                    $signals[] = "Sell";
                }
            } else {
                $signals[] = "Hold"; // No Signal during initialization period
            }
            $previousSuperTrend = $superTrend;
            $previousFinalUpperBand = $finalUpperBand;
            $previousFinalLowerBand = $finalLowerBand;
            $previousClose = $ltp["close"];
        }
        return $signals;
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

    public function handle()
    {
        $timeperiod = date("Y-m-d 09:00");
        
        set_time_limit(0);
        $symbol_range = 1;
        $acceptedSymbols = "CRUDEOIL";
        $marketHolidays = ["2024-01-22", "2024-01-26", "2024-03-08", "2024-03-25", "2024-03-29", "2024-04-11",
        "2024-04-17", "2024-05-01", "2024-06-17", "2024-07-17", "2024-08-15", "2024-10-02", "2024-11-01", "2024-11-15", "2024-12-25"];

        $currentDate = date('Y-m-d');
        // Check Today Is Holiday Or Not
        if(!in_array($currentDate,$marketHolidays)){
            // For Current Time is B\w 9:15Am to 11:30pm
            if($this->isBetween915AMto1130PM()){
                // Loop For Symbols List
                $McxToken = array(); 
                $completeResponse = [];
                $angleApiInstuments = AngelApiInstrument::Where('name',$acceptedSymbols)->where(function ($query) {
                    $query->where('instrumenttype', '=', 'AMXIDX')->orWhere('instrumenttype', '=', 'COMDTY');
                })->whereDay('created_at', now()->day)->first();
                if($angleApiInstuments != NULL){
                    if($angleApiInstuments->exch_seg == "MCX"){
                        for ($i=(-$symbol_range); $i <= $symbol_range ; $i++) { 
                            $exchangeVal = $angleApiInstuments->exch_seg;
                            $tokenVal = $angleApiInstuments->token;
                            $nameVal = $angleApiInstuments->name;
    
                            // GET LTP by Angle Api
                            $ltpByApi = $this->getLTP($exchangeVal,$nameVal,$tokenVal);
                            if($ltpByApi['status'] == true){
                                $givenLtp = $ltpByApi['data']['ltp'];
                            }else{
                                $givenLtp = NULL;
                            }
                            $response = $this->getStrickData($nameVal,$exchangeVal,$givenLtp ,$i , $i);
                            $completeResponse[$response[0][1]] = $response[0][3];
                            $completeResponse[$response[1][1]] = $response[1][3];
                            array_push($McxToken,$response[0][1]);
                            array_push($McxToken,$response[1][1]);
                        }
                    }
                }

                $LeftmarketData = Crudeoil::whereNotIn('token_ce',$McxToken)->orwhereNotIn('token_pe',$McxToken)->whereDate('created_at', '=', date('Y-m-d'))->groupBy('token_ce')->groupBy('token_pe')->get();
                if($LeftmarketData != NULL){
                    foreach ($LeftmarketData as $k => $vl) {
                        $angelData = AngelApiInstrument::where('token',$vl->token)->first();
                        if($angelData != NULL){
                            for ($i=(-$symbol_range); $i <= $symbol_range ; $i++) { 
                                $exchangeVal = $angelData->exch_seg;
                                $tokenVal = $angelData->token;
                                $nameVal = $angelData->name;
                                // GET LTP by Angle Api
                                $ltpByApi = $this->getLTP($exchangeVal,$nameVal,$tokenVal);
                                if($ltpByApi['status'] == true){
                                    $givenLtp = $ltpByApi['data']['ltp'];
                                }else{
                                    $givenLtp = NULL;
                                }
                                $response = $this->getStrickData($nameVal,$exchangeVal,$givenLtp ,$i , $i);
                                $completeResponse[$response[0][1]] = $response[0][3];
                                $completeResponse[$response[1][1]] = $response[1][3];
    
                                array_push($McxToken,$response[0][1]);
                                array_push($McxToken,$response[1][1]);
                            }

                        }
                    }
                }

                $tArr = [
                    'mode'=>'FULL',
                    'exchangeTokens'=>[
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
                        if($errData != NULL){
                            if($errData['status']== true){
                                $result = $errData['data']['fetched'];
                                array_multisort(array_column($result ,'tradingSymbol'),SORT_ASC ,$result);
                                $passedSymbols = [];
                                foreach ($result as $key => $value) {
                                    if(!in_array($value['symbolToken'],$passedSymbols)){
                                        // For Buy Signal
                                        $previousData = Crudeoil::where('symbol_ce',$value['symbolToken'])->orWhere('symbol_pe',$value['symbolToken'])->orderby('id','DESC')->first();                                     
                                        $marketData = new Crudeoil;
                                        $atm = "";
                                        if (array_key_exists($value['symbolToken'], $completeResponse)) {
                                            $atm = $completeResponse[$value['symbolToken']];
                                        }

                                        // Timeframe
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

                                            // FOR CE
                                            $allLtp_ce = Crudeoil::select('ltp_ce as ltp','high_ce as high','low_ce as low','close_ce as close')->Where('symbol_ce',$result[$symbolSibling]['tradingSymbol'])->get()->toArray();
                                            $latestData_ce = [
                                                "ltp" => $result[$symbolSibling]['ltp'],
                                                "high" => $result[$symbolSibling]['high'],
                                                "low" => $result[$symbolSibling]['low'],
                                                "close" => $result[$symbolSibling]['close']
                                            ];
                                            array_push($allLtp_ce,$latestData_ce);
                                            $res_ce[] =  $this->calculateSuperTrend($allLtp_ce,21,3);
                                            $ce_super = array_slice($res_ce[0],-1);
                                            // dd($ce_super);
                                            $supertrend_ce = $ce_super[0];

                                            // FOR PE
                                            $allLtp_pe = Crudeoil::select('ltp_pe as ltp','high_pe as high','low_pe as low','close_pe as close')->Where('symbol_pe',$value['tradingSymbol'])->get()->toArray();
                                            $latestData_pe = [
                                                "ltp" => $value['ltp'],
                                                "high" => $value['high'],
                                                "low" => $value['low'],
                                                "close" => $value['close']
                                            ];
                                            array_push($allLtp_pe,$latestData_pe);
                                            $res_pe[] =  $this->calculateSuperTrend($allLtp_pe,21,3);
                                            $pe_super = array_slice($res_pe[0],-1);
                                            $supertrend_pe = $pe_super[0];
                                            

                                            // For BUY PRICE 
                                            $currentOI_pe = $value['opnInterest'];
                                            $currentOI_ce = $result[$symbolSibling]['opnInterest'];
                                            $currentPrice_pe = $value['ltp'];
                                            $currentPrice_ce = $result[$symbolSibling]['ltp'];

                                            $previousOI_pe = NULL;
                                            $previousOI_ce = NULL;
                                            $previousPrice_ce = NULL;
                                            $previousPrice_pe = NULL;
                                            if($previousData != NULL){
                                                $previousOI_pe = $previousData->opnInterest_pe;
                                                $previousOI_ce = $previousData->opnInterest_ce;
                                                $previousPrice_ce = $previousData->ltp_ce;
                                                $previousPrice_pe = $previousData->ltp_pe;
                                            }

                                            $oi_ce = "";
                                            if(($currentOI_ce > $previousOI_ce) && ($currentPrice_ce > $previousPrice_ce)){
                                                $oi_ce = "BUY CE";
                                            }else{
                                                $oi_ce = "SELL CE";
                                            }

                                            $oi_pe = "";
                                            if(($currentOI_pe < $previousOI_pe) && ($currentPrice_pe < $previousPrice_pe)){
                                                $oi_pe = "SELL PE";
                                            }else{
                                                $oi_pe = "BUY PE";
                                            }

                                            // For SELL PRICE 
                                            // $sellPrice_ce = "";
                                            // if(($currentOI_ce < $previousOI_ce) && ($currentPrice_ce < $previousPrice_ce)){
                                            //     $sellPrice_ce = "SELL CE";
                                            // }

                                            // $sellPrice_pe = "";
                                            // if(($currentOI_pe < $previousOI_pe) && ($currentPrice_pe < $previousPrice_pe)){
                                            //     $sellPrice_pe = "SELL PE";
                                            // }


                                            // For PE Symbols
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
                                            $marketData->oi_ce = $oi_ce;
                                            $marketData->oi_pe = $oi_pe;
                                            $marketData->supertrend_pe = $supertrend_pe;
                                            $marketData->supertrend_ce = $supertrend_ce;
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

                                            // FOR CE
                                            $allLtp_ce = Crudeoil::select('ltp_ce as ltp','high_ce as high','low_ce as low','close_ce as close')->Where('symbol_ce',$value['tradingSymbol'])->get()->toArray();
                                            $latestData_ce = [
                                                "ltp" => $value['ltp'],
                                                "high" => $value['high'],
                                                "low" => $value['low'],
                                                "close" => $value['close']
                                            ];
                                            array_push($allLtp_ce,$latestData_ce);
                                            $res_ce[] =  $this->calculateSuperTrend($allLtp_ce,21,3);
                                            $ce_super = array_slice($res_ce[0],-1);
                                            $supertrend_ce = $ce_super[0];

                                            // FOR PE
                                            $allLtp_pe = Crudeoil::select('ltp_pe as ltp','high_pe as high','low_pe as low','close_pe as close')->Where('symbol_pe',$result[$symbolSibling]['tradingSymbol'])->get()->toArray();
                                            $latestData_pe = [
                                                "ltp" => $result[$symbolSibling]['ltp'],
                                                "high" => $result[$symbolSibling]['high'],
                                                "low" => $result[$symbolSibling]['low'],
                                                "close" => $result[$symbolSibling]['close']
                                            ];
                                            array_push($allLtp_pe,$latestData_pe);
                                            $res_pe[] =  $this->calculateSuperTrend($allLtp_pe,21,3);
                                            $pe_super = array_slice($res_pe[0],-1);
                                            $supertrend_pe = $pe_super[0];

                                            // For BUY PRICE 
                                            $currentOI_ce = $value['opnInterest'];
                                            $currentOI_pe = $result[$symbolSibling]['opnInterest'];
                                            $currentPrice_ce = $value['ltp'];
                                            $currentPrice_pe = $result[$symbolSibling]['ltp'];

                                            $previousOI_pe = NULL;
                                            $previousOI_ce = NULL;
                                            $previousPrice_ce = NULL;
                                            $previousPrice_pe = NULL;
                                            if($previousData != NULL){
                                                $previousOI_pe = $previousData->opnInterest_pe;
                                                $previousOI_ce = $previousData->opnInterest_ce;
                                                $previousPrice_ce = $previousData->ltp_ce;
                                                $previousPrice_pe = $previousData->ltp_pe;
                                            }

                                            $oi_ce = "";
                                            if(($currentOI_ce > $previousOI_ce) && ($currentPrice_ce > $previousPrice_ce)){
                                                $oi_ce = "BUY CE";
                                            }else{
                                                $oi_ce = "SELL CE";
                                            }

                                            $oi_pe = "";
                                            if(($currentOI_pe < $previousOI_pe) && ($currentPrice_pe < $previousPrice_pe)){
                                                $oi_pe = "SELL PE";
                                            }else{
                                                $oi_pe = "BUY PE";
                                            }

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
                                            $marketData->oi_ce = $oi_ce;
                                            $marketData->oi_pe = $oi_pe;
                                            $marketData->supertrend_pe = $supertrend_pe;
                                            $marketData->supertrend_ce = $supertrend_ce;
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
                            
                            }
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
