<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AngelApiInstrument;
use App\Models\LTP_ROUNDOFF;
use App\Traits\AngelApiAuth;
use App\Models\FinNifty;
use Carbon\Carbon;
use DateTime;

class FinNiftyCommand extends Command
{
    use AngelApiAuth;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finifty:every_minute';

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
    // (NEED)
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

    // For MCX AND NSE DATA (NEED)
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

    // FOR NSE (NEED)
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
            if (strtotime($datetime_object) > strtotime($current_date)) {
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

    // NEED
    public function get_atm_strike_symbol_angel($spt_prc, $symbol_name, $nse_symbol, $exchange_name, $expiry_dates, $ce_adjustment, $pe_adjustment){

        $angleData = AngelApiInstrument::Where('name',$symbol_name)->whereDay('created_at', now()->day)->get()->toArray();
       
        $rounded_price_ce = $this->get_rounded_price($spt_prc, $symbol_name, $ce_adjustment);
        $rounded_price_pe = $this->get_rounded_price($spt_prc, $symbol_name, $pe_adjustment);

        $filters = array_map(function ($y) {
            $y['expiry'] = strtotime($y['expiry']);
            return $y;
        }, $angleData);

        // dd($filters);

        try {
            $index_row = AngelApiInstrument::Where('name',$symbol_name)->where('exch_seg','NSE')->whereDay('created_at', now()->day)->get()->toArray();
            $index_token = $index_row[0]['token'];
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        // dd($index_row);

        if ($exchange_name == 'NSE') {
            $exchange_name = 'NFO';
        }

        // dd( $filters,$symbol_name,$exchange_name,$expiry_dates);
        $filters = array_values(array_filter($filters, function($x) use($symbol_name,$exchange_name,$expiry_dates) {
            if(($x['name'] == $symbol_name) && ($x["exch_seg"] == $exchange_name) && ($x['expiry'] <= strtotime($expiry_dates[1])) && ($x['expiry'] >= strtotime($expiry_dates[0]))){
                return $x;
            }
        }));

        // dd($filters);
       
        $filters = array_map(function ($y) {
            $y['strike'] = ($y['strike'] / 100);
            return $y;
        }, $filters);

        // dd($filters);

        try {
            $ce_filters = array_values(array_filter($filters, function($x) use($expiry_dates,$rounded_price_ce) {
                if(($x['expiry'] == strtotime($expiry_dates[1]))  && (substr($x['symbol_name'],-2) == "CE") && ($x["strike"] == $rounded_price_ce)){
                    return $x;
                }
            }));
         
            // dd($ce_filters);
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

    public function handle(){
        $timeperiod = date("Y-m-d 09:00");
        set_time_limit(0);
        $symbol_range = 1;
        $acceptedSymbols = "FINNIFTY";
        $marketHolidays = ["2024-01-22", "2024-01-26", "2024-03-08", "2024-03-25", "2024-03-29", "2024-04-11",
        "2024-04-17", "2024-05-01", "2024-06-17", "2024-07-17", "2024-08-15", "2024-10-02", "2024-11-01", "2024-11-15", "2024-12-25"];

        $currentDate = date('Y-m-d');
        // Check Today Is Holiday Or Not
        if(!in_array($currentDate,$marketHolidays)){
            // For Current Time is B\w 9:15Am to 11:30pm
            if($this->isBetween915AMto1130PM()){
                // Loop For Symbols List
                $NfoToken = array();
                $completeResponse = [];
                $angleApiInstuments = AngelApiInstrument::Where('name',$acceptedSymbols)->where(function ($query) {
                    $query->where('instrumenttype', '=', 'AMXIDX')->orWhere('instrumenttype', '=', 'COMDTY');
                })->whereDay('created_at', now()->day)->first();

                // For NSE Exch Records
                if($angleApiInstuments != NULL){
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
                
                $LeftmarketData = FinNifty::whereNotIn('token_ce',$NfoToken)->orwhereNotIn('token_pe',$NfoToken)->whereDate('created_at', '=', date('Y-m-d'))->groupBy('token_ce')->groupBy('token_pe')->get();

                if($LeftmarketData != NULL){
                    foreach ($LeftmarketData as $k => $vl) {
                        if($vl->exhange == "NFO"){
                            $angelData = AngelApiInstrument::where('token',$vl->token)->first();
                            if($angelData != NULL){
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
                }

                $tArr = [
                    'mode'=>'FULL',
                    'exchangeTokens'=>[
                        'NFO'=>$NfoToken,
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
        
                                        $marketData = new FinNifty;
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
