<?php

namespace App\Helpers;

use App\Models\ZerodhaInstrument;
use App\Models\OmsConfigs;
use App\Models\OrderBook;
use App\Models\AngelApiInstrument;
use App\Models\SiteVariable;
use App\Models\SymbolLtps;
use App\Helpers\KiteConnectCls;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NewOmsConfigCronOrderRt
{
    public function __construct()
    {
        set_time_limit(0);
    }

    public function calculateTickSize($price, $tickSize)
    {
        $roundedPrice = round($price / $tickSize) * $tickSize;
        return $roundedPrice;
    }

    public function getLimitPrice($ltp, $discountPercent, $txnType, $tickSize)
    {
        if ($txnType == "BUY") {
            $price = $ltp - ($ltp * ($discountPercent / 100));
        } else {
            $price = $ltp + ($ltp * ($discountPercent / 100));
        }
        $finalPrice = round($price, 4);
        return $this->calculateTickSize($finalPrice, $tickSize);
    }

    public function postPlaceOrder(object $broker, array $apiData)
    {
        $params = [
            'accountUserName' => $broker->account_user_name,
            'accountPassword' => $broker->account_password,
            'totpSecret' => $broker->totp,
            'apiKey' => $broker->api_key,
            'apiSecret' => $broker->api_secret_key
        ];

        $kiteObj = new KiteConnectCls($params);
        $kite = \Cache::remember('KITE_AUTH_'.$broker->account_user_name, 18000, function () use($kiteObj, $broker) {
            $token = $broker->request_token;
            $kite = $kiteObj->generateSessionManual($token);
            return $kite;
        });

        try {
            if (is_string($kite)) {
                \Cache::forget('KITE_AUTH_'.$broker->account_user_name);
                Log::warning("Kite auth invalid/expired", ['broker' => $broker->account_user_name, 'kite' => $kite]);
                return 0;
            }

            $order = $kite->placeOrder("regular", $apiData);
            sleep(2);

            $orderId = is_object($order) && isset($order->order_id) ? $order->order_id : (is_array($order) && isset($order['order_id']) ? $order['order_id'] : null);
            $orderData = $orderId ? $kite->getOrderHistory($orderId) : null;
            
            $lastD = null;
            if (is_array($orderData)) {
                $lastSlice = array_slice($orderData, -1);
                $lastD = $lastSlice[0] ?? null;
            } elseif (is_object($orderData)) {
                $lastD = is_array($orderData) ? array_slice($orderData, -1)[0] ?? null : $orderData;
            }
            
            $bookObj = new OrderBook();
            $bookObj->broker_username = $broker->account_user_name;
            $bookObj->order_id = $lastD->order_id ?? ($orderId ?? '-');
            $bookObj->status = $lastD->status ?? 'unknown';
            $bookObj->trading_symbol = $lastD->tradingsymbol ?? ($apiData['tradingsymbol'] ?? '-');
            $bookObj->order_type = $lastD->order_type ?? ($apiData['order_type'] ?? '-');
            $bookObj->transaction_type = $lastD->transaction_type ?? ($apiData['transaction_type'] ?? '-');
            $bookObj->product = $lastD->product ?? ($apiData['product'] ?? '-');
            $bookObj->price = $apiData['price'] ?? '-';
            $bookObj->quantity = $lastD->quantity ?? ($apiData['quantity'] ?? '-');
            $bookObj->status_message = $lastD->status_message ?? ($lastD->status ?? '-');
            $bookObj->order_datetime = isset($lastD->order_timestamp) && method_exists($lastD->order_timestamp, 'format') ? $lastD->order_timestamp->format('Y-m-d H:i:s') : Carbon::now()->toDateTimeString();
            $bookObj->user_id = $broker->user_id ?? null;
            $bookObj->save();
            
            return 1;
        } catch (\Exception $e) {
            $bookObj = new OrderBook();
            $bookObj->broker_username = $broker->account_user_name;
            $bookObj->order_id = '-';
            $bookObj->status = 'failed';
            $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
            $bookObj->order_type = $apiData['order_type'] ?? '-';
            $bookObj->transaction_type = $apiData['transaction_type'] ?? '-';
            $bookObj->product = $apiData['product'] ?? '-';
            $bookObj->price = $apiData['price'] ?? '-';
            $bookObj->quantity = $apiData['quantity'] ?? '-';
            $bookObj->status_message = $e->getMessage();
            $bookObj->order_datetime = Carbon::now()->toDateTimeString();
            $bookObj->user_id = $broker->user_id ?? null;
            $bookObj->save();
            
            \Cache::forget('KITE_AUTH_'.$broker->account_user_name);
            return 1;
        }
    }

    public function getZerodhaSymLotSize($symbol)
    {
        $lotSizeData = ZerodhaInstrument::select('lot_size', 'tick_size')
            ->where('trading_symbol', $symbol)
            ->first();
            
        if ($lotSizeData) {
            return [
                'lot_size' => $lotSizeData->lot_size,
                'tick_size' => $lotSizeData->tick_size
            ];
        }
        
        return [
            'lot_size' => null,
            'tick_size' => null
        ];
    }

    public function getLTPFromBuildup($symbolName, $portfolioType)
    {
        // Try primary source
        $today = Carbon::now();
        $currentLTP = SymbolLtps::select('ltp')
            ->where('symbol_name', $symbolName)
            ->whereDate('created_at', $today->toDateString())
            ->latest()
            ->first();

        if ($currentLTP && !empty($currentLTP->ltp)) {
            return $currentLTP->ltp;
        }

        // Fallback to mysql_oi_buildup (latest entry for symbol+portfolio)
        try {
            $connection = DB::connection('mysql_oi_buildup');
            $data = $connection->table('positions_on_buildup')
                ->select('ltp')
                ->where('symbol_name', $symbolName)
                ->where('portfolio_type', $portfolioType)
                ->orderBy('created_at', 'desc')
                ->first();

            return $data ? $data->ltp : null;
        } catch (\Exception $e) {
            Log::error("Failed to fetch buildup LTP", ['symbol' => $symbolName, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function callKiteApi($signalData, object $omsData)
    {
        $txnType = $omsData->txn_type;
        $symbolType = strtoupper($omsData->symbol_type ?? '');
        
        // Get current LTP from buildup data
        $currentLTP = $this->getLTPFromBuildup($omsData->symbol_name, $omsData->portfolio_type);
        
        if (!$currentLTP) {
            Log::warning("No LTP found for symbol", ['symbol' => $omsData->symbol_name, 'oms_id' => $omsData->id]);
            return 0;
        }

        $fData = [
            "exchange" => "NFO",
            "tradingsymbol" => $omsData->symbol_name,
            "transaction_type" => $txnType,
            "order_type" => $omsData->order_type,
            "product" => $omsData->product
        ];

        $lotSizeArr = $this->getZerodhaSymLotSize($omsData->symbol_name);
        $lotSize = $lotSizeArr['lot_size'] ?? 1;
        $tickSize = $lotSizeArr['tick_size'] ?? 0.05;

        $lotSize = (int)($lotSize ?: 1);
        $tickSize = (float)($tickSize ?: 0.05);

        $breakForeach = 0;

        foreach($signalData as $vvl) {
            if($breakForeach == 1) {
                break;
            }
            
            $data = json_decode($vvl->data, true);
            if (!$data) continue;

            $strategyArrFF = $data['Strategy_name'] ?? [];
            $buyActionArrFF = $data['BUY_Action'] ?? [];
            $sellActionArrFF = $data['SELL_Action'] ?? [];
            $timeActArrFF = $data['time'] ?? [];
            $dateActArrFF = $data['Date'] ?? [];
            
            $cntLoop = 0;
            $placeOrderRept = 0;

            $strategyArr = [];
            $buyActionArr = [];
            $sellActionArr = [];
            $timeActArr = [];
            $dateActArr = [];

            // Filter signals newer than last_time
            foreach($timeActArrFF as $kk => $vv) {
                if (!isset($dateActArrFF[$kk])) continue;
                
                $dateStr = date("Y-m-d", ($dateActArrFF[$kk]/1000)).' '.$vv;
                
                if(strtotime($dateStr) > strtotime($omsData->last_time ?? '1970-01-01 00:00:00')) {
                    if($cntLoop == 1) {
                        if((strtolower($buyActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type)) || 
                           (strtolower($sellActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type))) {
                            $strategyArr[] = $strategyArrFF[$kk] ?? '';
                            $buyActionArr[] = $buyActionArrFF[$kk] ?? '';
                            $sellActionArr[] = $sellActionArrFF[$kk] ?? '';
                            $timeActArr[] = $timeActArrFF[$kk] ?? '';
                            $dateActArr[] = $dateActArrFF[$kk] ?? '';
                            $placeOrderRept = 1;
                            break;
                        }
                    } else {
                        if((strtolower($buyActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type)) || 
                           (strtolower($sellActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type))) {
                            $cntLoop = 0;
                        } else {
                            $cntLoop = 1;
                        }
                    }
                }
            }

            if($placeOrderRept == 1) {
                foreach($strategyArr as $key => $v) {
                    if((strtolower($buyActionArr[$key] ?? '') == strtolower($omsData->buildup_type)) || 
                       (strtolower($sellActionArr[$key] ?? '') == strtolower($omsData->buildup_type))) {
                        
                        $dateStr = date("Y-m-d", ($dateActArr[$key]/1000));
                        $timeFrmTm = $dateStr." ".($timeActArr[$key] ?? '');

                        $allSucceeded = true;

                        // Helper to prepare and place order
                        $place = function ($pyramidQty) use ($fData, $omsData, $currentLTP, $txnType, $lotSize, $tickSize, &$allSucceeded) {
                            if ($pyramidQty <= 0) return;

                            $payload = $fData;
                            if ($omsData->order_type === "LIMIT") {
                                $payload['price'] = $this->getLimitPrice($currentLTP, $omsData->disc_ltp, $txnType, $tickSize);
                            }

                            $payload['quantity'] = $pyramidQty * $lotSize;

                            $res = $this->postPlaceOrder($omsData->broker, $payload);
                            if ($res !== 1) $allSucceeded = false;
                        };

                        // Process pyramids if set
                        if (!is_null($omsData->pyramid_1) && $omsData->pyramid_1 > 0) {
                            $place($omsData->pyramid_1);
                        }
                        if (!is_null($omsData->pyramid_2) && $omsData->pyramid_2 > 0) {
                            $place($omsData->pyramid_2);
                        }
                        if (!is_null($omsData->pyramid_3) && $omsData->pyramid_3 > 0) {
                            $place($omsData->pyramid_3);
                        }

                        if ($allSucceeded) {
                            OmsConfigs::where("id", $omsData->id)->update([
                                'is_api_pushed' => 1,
                                'last_time' => date("Y-m-d H:i", strtotime($timeFrmTm.' +5 minutes'))
                            ]);
                            $breakForeach = 1;
                            break;
                        }
                    }
                }
            }
        }
        
        return 1;
    }

    // Angel API methods
    public function postPlaceOrderAngel(object $broker, array $apiData)
    {
        $params = [
            'accountUserName' => $broker->account_user_name,
            'apiKey' => $broker->api_key,
            'pin' => $broker->security_pin,
            'totp_secret' => $broker->totp,
        ];
        
        $angelTokenArrObj = new AngelConnectCls($params);
        $angelTokenArr = $angelTokenArrObj->generate_access_token();

        if (is_null($angelTokenArr)) {
            \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
            Log::warning("Angel token generation failed", ['broker' => $broker->account_user_name]);
            return 0;
        }

        $tokenA = $angelTokenArr['token'] ?? null;
        $clientLocalIp = $angelTokenArr['clientLocalIp'] ?? '';
        $clientPublicIp = $angelTokenArr['clientPublicIp'] ?? '';
        $macAddress = $angelTokenArr['macAddress'] ?? '';
        
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
        $err = curl_error($curl);
        curl_close($curl);

        if ($response == "" || is_null($response)) {
            \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
            Log::error("Angel placeOrder curl error", ['err' => $err, 'broker' => $broker->account_user_name, 'payload' => $apiData]);
            
            $bookObj = new OrderBook();
            $bookObj->broker_username = $broker->account_user_name;
            $bookObj->order_id = '-';
            $bookObj->status = 'failed';
            $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
            $bookObj->order_type = '-';
            $bookObj->transaction_type = '-';
            $bookObj->product = '-';
            $bookObj->price = $apiData['price'] ?? '-';
            $bookObj->quantity = '-';
            $bookObj->status_message = "order failed-".$response.'-'.json_encode($apiData);
            $bookObj->order_datetime = Carbon::now()->toDateTimeString();
            $bookObj->user_id = $broker->user_id;
            $bookObj->save();
            
            return 1;
        }

        $responseArr = json_decode($response, true);
        if (!is_array($responseArr)) {
            \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
            Log::error("Angel response decode error", ['raw' => $response, 'broker' => $broker->account_user_name]);
            return 0;
        }
        
        if (!empty($responseArr['status']) && $responseArr['status'] === true) {
            $orderId = $responseArr['data']['uniqueorderid'] ?? null;
            sleep(2);
            
            // Get order details
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
            
            $orderDetailResp = curl_exec($curl);
            curl_close($curl);

            if ($err || !$orderDetailResp) {
                \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
                Log::error("Angel get details error", ['err' => $err, 'broker' => $broker->account_user_name]);
                return 0;
            }
            
            $detailArr = json_decode($orderDetailResp, true);
            if (!empty($detailArr['status']) && $detailArr['status'] === true) {
                $lastD = $detailArr['data'] ?? [];

                $bookObj = new OrderBook();
                $bookObj->broker_username = $broker->account_user_name;
                $bookObj->order_id = $orderId ?? '-';
                $bookObj->status = $lastD['status'] ?? 'unknown';
                $bookObj->trading_symbol = $lastD['tradingsymbol'] ?? ($apiData['tradingsymbol'] ?? '-');
                $bookObj->order_type = $lastD['ordertype'] ?? '-';
                $bookObj->transaction_type = $lastD['transactiontype'] ?? '-';
                $bookObj->product = $lastD['producttype'] ?? '-';
                $bookObj->price = $apiData['price'] ?? '-';
                $bookObj->quantity = $lastD['quantity'] ?? ($apiData['quantity'] ?? '-');
                $bookObj->status_message = $lastD['text'] ?? '-';
                $bookObj->order_datetime = isset($lastD['updatetime']) ? date("Y-m-d H:i:s", strtotime($lastD['updatetime'])) : Carbon::now()->toDateTimeString();
                $bookObj->user_id = $broker->user_id ?? null;
                $bookObj->save();
                return 1;
            }

            \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
            Log::error("Angel order details status false", ['resp' => $detailArr ?? null, 'broker' => $broker->account_user_name]);
            return 0;
        } else {
            \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
            Log::error("Angel placeOrder failed", ['response' => $responseArr, 'broker' => $broker->account_user_name]);
            
            $bookObj = new OrderBook();
            $bookObj->broker_username = $broker->account_user_name;
            $bookObj->order_id = '-';
            $bookObj->status = 'failed';
            $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
            $bookObj->order_type = '-';
            $bookObj->transaction_type = '-';
            $bookObj->product = '-';
            $bookObj->price = $apiData['price'] ?? '-';
            $bookObj->quantity = '-';
            $bookObj->status_message = "order failed-".($responseArr['message'] ?? json_encode($responseArr));
            $bookObj->order_datetime = Carbon::now()->toDateTimeString();
            $bookObj->user_id = $broker->user_id ?? null;
            $bookObj->save();
            return 1;
        }
    }

    public function getTokenBySymbolName($symbName)
    {
        $data = AngelApiInstrument::select('trading_symbol', 'zi.exchange_token', 'lotsize', 'symbol_name', 'angel_api_instruments.tick_size')
            ->join('zerodha_instruments as zi', 'zi.exchange_token', 'token')
            ->where('trading_symbol', $symbName)
            ->first();
            
        if ($data) {
            $tSize = $data->tick_size / 100;
            return [
                'symbol' => $data->symbol_name,
                'token' => $data->exchange_token,
                'lot_size' => $data->lotsize,
                'tick_size' => $tSize
            ];
        }
        
        return [
            'symbol' => null,
            'token' => null,
            'lot_size' => null,
            'tick_size' => null,
        ];
    }

    public function callAngelApi($signalData, object $omsData)
    {
        $txnType = $omsData->txn_type;
        $symbolType = strtoupper($omsData->symbol_type ?? '');
        
        // Get current LTP from buildup data
        $currentLTP = $this->getLTPFromBuildup($omsData->symbol_name, $omsData->portfolio_type);
        
        if (!$currentLTP) {
            Log::warning("No LTP found for symbol (angel)", ['symbol' => $omsData->symbol_name, 'oms_id' => $omsData->id]);
            return 0;
        }

        $fData = [
            'variety' => 'NORMAL',
            "exchange" => "NFO",
            "transactiontype" => $txnType,
            "ordertype" => $omsData->order_type,
            "producttype" => 'CARRYFORWARD',
            'duration' => 'DAY',
            'squareoff' => 0,
            'stoploss' => 0
        ];

        $symArr = $this->getTokenBySymbolName($omsData->symbol_name);
        $fData["tradingsymbol"] = $symArr['symbol'];
        $fData['symboltoken'] = $symArr['token'];
        $tickSize = $symArr['tick_size'] ?? 0.05;
        $lotSize = $symArr['lot_size'] ?? 1;

        $lotSize = (int)$lotSize;
        $tickSize = (float)$tickSize;

        $breakForeach = 0;

        foreach($signalData as $vvl) {
            if(isset($vvl->atm) && $vvl->atm == "ATM") {
                if($breakForeach == 1) {
                    break;
                }
                
                $data = json_decode($vvl->data, true);
                if (!$data) continue;

                $strategyArrFF = $data['Strategy_name'] ?? [];
                $buyActionArrFF = $data['BUY_Action'] ?? [];
                $sellActionArrFF = $data['SELL_Action'] ?? [];
                $timeActArrFF = $data['time'] ?? [];
                $dateActArrFF = $data['Date'] ?? [];
                
                $cntLoop = 0;
                $placeOrderRept = 0;

                $strategyArr = [];
                $buyActionArr = [];
                $sellActionArr = [];
                $timeActArr = [];
                $dateActArr = [];

                // Filter signals newer than last_time
                foreach($timeActArrFF as $kk => $vv) {
                    if (!isset($dateActArrFF[$kk])) continue;
                    
                    $dateStr = date("Y-m-d", ($dateActArrFF[$kk]/1000)).' '.$vv;
                    
                    if(strtotime($dateStr) > strtotime($omsData->last_time ?? '1970-01-01 00:00:00')) {
                        if($cntLoop == 1) {
                            if((strtolower($buyActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type)) || 
                               (strtolower($sellActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type))) {
                                $strategyArr[] = $strategyArrFF[$kk] ?? '';
                                $buyActionArr[] = $buyActionArrFF[$kk] ?? '';
                                $sellActionArr[] = $sellActionArrFF[$kk] ?? '';
                                $timeActArr[] = $timeActArrFF[$kk] ?? '';
                                $dateActArr[] = $dateActArrFF[$kk] ?? '';
                                $placeOrderRept = 1;
                                break;
                            }
                        } else {
                            if((strtolower($buyActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type)) || 
                               (strtolower($sellActionArrFF[$kk] ?? '') == strtolower($omsData->buildup_type))) {
                                $cntLoop = 0;
                            } else {
                                $cntLoop = 1;
                            }
                        }
                    }
                }

                if($placeOrderRept == 1) {
                    foreach($strategyArr as $key => $v) {
                        if((strtolower($buyActionArr[$key] ?? '') == strtolower($omsData->buildup_type)) || 
                           (strtolower($sellActionArr[$key] ?? '') == strtolower($omsData->buildup_type))) {
                            
                            $dateStr = date("Y-m-d", ($dateActArr[$key]/1000));
                            $timeFrmTm = $dateStr." ".($timeActArr[$key] ?? '');

                            $allSucceeded = true;

                            $place = function ($pyramidQty) use ($fData, $omsData, $currentLTP, $txnType, $lotSize, $tickSize, &$allSucceeded) {
                                if ($pyramidQty <= 0) return;

                                $payload = $fData;
                                if ($omsData->order_type === "LIMIT") {
                                    $payload['price'] = $this->getLimitPrice($currentLTP, $omsData->disc_ltp, $txnType, $tickSize);
                                }

                                $payload['quantity'] = $lotSize * $pyramidQty;

                                $res = $this->postPlaceOrderAngel($omsData->broker, $payload);
                                if ($res !== 1) $allSucceeded = false;
                            };

                            if (!is_null($omsData->pyramid_1) && $omsData->pyramid_1 > 0) {
                                $place($omsData->pyramid_1);
                            }
                            if (!is_null($omsData->pyramid_2) && $omsData->pyramid_2 > 0) {
                                $place($omsData->pyramid_2);
                            }
                            if (!is_null($omsData->pyramid_3) && $omsData->pyramid_3 > 0) {
                                $place($omsData->pyramid_3);
                            }

                            if ($allSucceeded) {
                                OmsConfigs::where("id", $omsData->id)->update([
                                    'is_api_pushed' => 1,
                                    'last_time' => date("Y-m-d H:i", strtotime($timeFrmTm.' +5 minutes'))
                                ]);
                            }
                            $breakForeach = 1;
                            break;
                        }
                    }
                }
            }
        }
        
        return 1;
    }

    public function placeOrder()
    {
        $todayDate = Carbon::now()->toDateString();
        Log::info("Real-time PlaceOrder started", ['date' => $todayDate]);

        OmsConfigs::select('*')
            ->with('broker')
            ->whereNotNull('last_time')
            ->where('status', 1)
            ->chunk(100, function ($omsConfigs) use ($todayDate) {
                foreach ($omsConfigs as $config) {
                    // Safety: ensure broker exists
                    if (!isset($config->broker) || empty($config->broker)) {
                        Log::warning("OmsConfig missing broker (RT)", ['oms_id' => $config->id]);
                        continue;
                    }

                    try {
                        // Get signal data from mysql_rm connection
                        $signalData = \DB::connection('mysql_rm')
                            ->table($config->symbol_name)
                            ->select('*')
                            ->where(['date' => $todayDate, 'timeframe' => $config->signal_tf ?? '1m'])
                            ->get();

                        if (count($signalData)) {
                            $filteredData = [];
                            foreach($signalData as $signal) {
                                if (isset($signal->atm) && ($signal->atm == "ATM" || $signal->atm == "ATM-1" || $signal->atm == "ATM+1")) {
                                    $filteredData[] = $signal;
                                }
                            }

                            if (count($filteredData)) {
                                if ($config->broker->client_type == "Zerodha") {
                                    $this->callKiteApi($filteredData, $config);
                                } elseif ($config->broker->client_type == "Angel") {
                                    $this->callAngelApi($filteredData, $config);
                                } else {
                                    Log::warning("Unknown broker type (RT)", [
                                        'config_id' => $config->id,
                                        'broker' => $config->broker->client_type ?? null
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Exception processing OmsConfig (RT)", [
                            'oms_id' => $config->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            });

        // Update cron_run_at for all processed configs
        OmsConfigs::where('status', 1)
            ->whereNotNull('last_time')
            ->update([
                'cron_run_at' => DB::raw("DATE_ADD(cron_run_at, INTERVAL pyramid_freq MINUTE)")
            ]);

        Log::info("Real-time PlaceOrder finished");
    }
}