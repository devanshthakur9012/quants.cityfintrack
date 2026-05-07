<?php

namespace App\Helpers;

use App\Models\ZerodhaInstrument;
use App\Models\HistoricalPortfolio;
use App\Models\OrderBook;
use App\Models\AngelApiInstrument;
use App\Models\SymbolLtps;
use App\Helpers\KiteConnectCls;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\AngelConnectCls;
use App\Traits\AngelApiAuth;

class HistoricalOrderPlace{
    use AngelApiAuth;
    
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
        // $finalPrice = round($price, 2);
        $finalPrice = round($price, 4);

        // For BUY we round down, for SELL round up (safer for limit placement)
        // $direction = ($txnType === 'BUY') ? 'BUY' : 'SELL';
        return $this->calculateTickSize($finalPrice, $tickSize);
    }

    /**
     * FIXED: Zerodha order placement using stored token system
     */
    public function postPlaceOrder(object $broker, array $apiData)
    {
        try {
            // Initialize KiteConnect helper with broker credentials
            $params = [
                'accountUserName' => $broker->account_user_name,
                'accountPassword' => $broker->account_password,
                'totpSecret' => $broker->totp,
                'apiKey' => $broker->api_key,
                'apiSecret' => $broker->api_secret_key
            ];

            $kiteObj = new KiteConnectCls($params);
            
            // Use the generateSession method which handles token storage/retrieval automatically
            $kite = $kiteObj->generateSession();
            
            if (!$kite) {
                Log::error("Failed to get Kite session", ['broker' => $broker->account_user_name]);
                $this->saveFailedOrderKite($broker, $apiData, "Failed to authenticate with Kite");
                return 0;
            }

            // Place the order
            Log::info("Placing Kite order", [
                'broker' => $broker->account_user_name,
                'symbol' => $apiData['tradingsymbol'] ?? 'Unknown',
                'quantity' => $apiData['quantity'] ?? 'Unknown',
                'order_type' => $apiData['order_type'] ?? 'Unknown'
            ]);

            $orderResponse = $kite->placeOrder("regular", $apiData);
            
            if (!$orderResponse) {
                throw new \Exception("Empty response from placeOrder API");
            }

            // Extract order ID
            $orderId = $this->extractOrderIdKite($orderResponse);
            
            if (!$orderId) {
                Log::error("No order ID in response", [
                    'broker' => $broker->account_user_name,
                    'response' => $orderResponse
                ]);
                $this->saveFailedOrderKite($broker, $apiData, "No order ID received");
                return 0;
            }

            Log::info("Order placed successfully", [
                'broker' => $broker->account_user_name,
                'order_id' => $orderId
            ]);

            // Wait before fetching order details
            sleep(2);
            
            // Get order details
            $orderDetails = $this->getOrderDetailsKite($kite, $orderId, $broker);
            
            // Save to order book
            $this->saveOrderToBookKite($broker, $apiData, $orderId, $orderDetails);
            
            return 1; // Success
            
        } catch (\Exception $e) {
            Log::error("Kite order placement exception", [
                'broker' => $broker->account_user_name,
                'error' => $e->getMessage(),
                'payload' => $apiData,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Save failed order
            $this->saveFailedOrderKite($broker, $apiData, "Exception: " . $e->getMessage());
            
            return 0; // Temporary failure - can retry
        }
    }

    /**
     * Extract order ID from various response formats
     */
    protected function extractOrderIdKite($orderResponse)
    {
        if (is_object($orderResponse)) {
            // Try direct property access
            if (isset($orderResponse->order_id)) {
                return $orderResponse->order_id;
            }
            
            // Try nested data property
            if (isset($orderResponse->data) && is_object($orderResponse->data) && isset($orderResponse->data->order_id)) {
                return $orderResponse->data->order_id;
            }
            
            // Convert object to array and try again
            $orderResponse = json_decode(json_encode($orderResponse), true);
        }
        
        if (is_array($orderResponse)) {
            // Direct array access
            if (isset($orderResponse['order_id'])) {
                return $orderResponse['order_id'];
            }
            
            // Nested data array
            if (isset($orderResponse['data']['order_id'])) {
                return $orderResponse['data']['order_id'];
            }
        }
        
        return null;
    }

    /**
     * Get order details with proper error handling
     */
    protected function getOrderDetailsKite($kite, $orderId, $broker)
    {
        try {
            $orderHistory = $kite->getOrderHistory($orderId);
            
            if (empty($orderHistory)) {
                Log::warning("Empty order history", [
                    'order_id' => $orderId,
                    'broker' => $broker->account_user_name
                ]);
                return null;
            }
            
            // Get the latest order status
            if (is_array($orderHistory)) {
                $latestOrder = end($orderHistory);
            } else {
                $latestOrder = $orderHistory;
            }
            
            // Convert object to array for consistent handling
            if (is_object($latestOrder)) {
                $latestOrder = json_decode(json_encode($latestOrder), true);
            }
            
            return $latestOrder;
            
        } catch (\Exception $e) {
            Log::error("Failed to get order history", [
                'order_id' => $orderId,
                'broker' => $broker->account_user_name,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Save order to order book with consistent data handling
     */
    protected function saveOrderToBookKite($broker, $apiData, $orderId, $orderDetails = null)
    {
        try {
            $bookObj = new OrderBook();
            $bookObj->broker_username = $broker->account_user_name;
            $bookObj->order_id = $orderId;
            $bookObj->user_id = $broker->user_id ?? null;
            
            if ($orderDetails && is_array($orderDetails)) {
                // Use order history data
                $bookObj->status = $orderDetails['status'] ?? 'placed';
                $bookObj->trading_symbol = $orderDetails['tradingsymbol'] ?? ($apiData['tradingsymbol'] ?? '-');
                $bookObj->order_type = $orderDetails['order_type'] ?? ($apiData['order_type'] ?? '-');
                $bookObj->transaction_type = $orderDetails['transaction_type'] ?? ($apiData['transaction_type'] ?? '-');
                $bookObj->product = $orderDetails['product'] ?? ($apiData['product'] ?? '-');
                $bookObj->quantity = $orderDetails['quantity'] ?? ($apiData['quantity'] ?? '-');
                $bookObj->status_message = $orderDetails['status_message'] ?? ($orderDetails['status'] ?? 'Order processed');
                
                // Handle timestamp
                if (isset($orderDetails['order_timestamp'])) {
                    try {
                        $bookObj->order_datetime = Carbon::parse($orderDetails['order_timestamp'])->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $bookObj->order_datetime = Carbon::now()->toDateTimeString();
                    }
                } else {
                    $bookObj->order_datetime = Carbon::now()->toDateTimeString();
                }
            } else {
                // Fallback to API data
                $bookObj->status = 'placed';
                $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
                $bookObj->order_type = $apiData['order_type'] ?? '-';
                $bookObj->transaction_type = $apiData['transaction_type'] ?? '-';
                $bookObj->product = $apiData['product'] ?? '-';
                $bookObj->quantity = $apiData['quantity'] ?? '-';
                $bookObj->status_message = $orderDetails ? 'Order placed - history unavailable' : 'Order placed successfully';
                $bookObj->order_datetime = Carbon::now()->toDateTimeString();
            }
            
            $bookObj->price = $apiData['price'] ?? '-';
            $bookObj->save();
            
            Log::info("Order saved to book", [
                'broker' => $broker->account_user_name,
                'order_id' => $orderId,
                'status' => $bookObj->status
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to save order to book", [
                'broker' => $broker->account_user_name,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Save failed order with consistent error handling
     */
    protected function saveFailedOrderKite($broker, $apiData, $errorMessage)
    {
        try {
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
            $bookObj->status_message = $errorMessage;
            $bookObj->order_datetime = Carbon::now()->toDateTimeString();
            $bookObj->user_id = $broker->user_id ?? null;
            $bookObj->save();
            
            Log::info("Failed order saved to book", [
                'broker' => $broker->account_user_name,
                'error' => $errorMessage
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to save failed order", [
                'broker' => $broker->account_user_name,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Helper methods
    protected function extractOrderId($orderResponse)
    {
        if (is_object($orderResponse)) {
            if (isset($orderResponse->order_id)) return $orderResponse->order_id;
            if (isset($orderResponse->data->order_id)) return $orderResponse->data->order_id;
        } elseif (is_array($orderResponse)) {
            if (isset($orderResponse['order_id'])) return $orderResponse['order_id'];
            if (isset($orderResponse['data']['order_id'])) return $orderResponse['data']['order_id'];
        }
        return null;
    }

    protected function getOrderDetails($kiteObj, $orderId, $broker)
    {
        try {
            $kite = $kiteObj->getKite();
            $orderHistory = $kite->getOrderHistory($orderId);
            
            if (is_array($orderHistory) && !empty($orderHistory)) {
                return end($orderHistory); // Get the latest status
            }
        } catch (\Exception $e) {
            Log::error("Failed to get order history", [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'broker' => $broker->account_user_name
            ]);
        }
        return null;
    }

    protected function saveOrderToBook($broker, $apiData, $orderId, $orderDetails = null)
    {
        $bookObj = new OrderBook();
        $bookObj->broker_username = $broker->account_user_name;
        $bookObj->order_id = $orderId;
        
        if ($orderDetails) {
            $bookObj->status = $orderDetails['status'] ?? 'placed';
            $bookObj->trading_symbol = $orderDetails['tradingsymbol'] ?? $apiData['tradingsymbol'] ?? '-';
            $bookObj->order_type = $orderDetails['order_type'] ?? $apiData['order_type'] ?? '-';
            $bookObj->transaction_type = $orderDetails['transaction_type'] ?? $apiData['transaction_type'] ?? '-';
            $bookObj->product = $orderDetails['product'] ?? $apiData['product'] ?? '-';
            $bookObj->quantity = $orderDetails['quantity'] ?? $apiData['quantity'] ?? '-';
            $bookObj->status_message = $orderDetails['status_message'] ?? 'Order processed';
            
            if (isset($orderDetails['order_timestamp'])) {
                try {
                    $bookObj->order_datetime = Carbon::parse($orderDetails['order_timestamp'])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $bookObj->order_datetime = Carbon::now();
                }
            } else {
                $bookObj->order_datetime = Carbon::now();
            }
        } else {
            // Fallback data
            $bookObj->status = 'placed';
            $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
            $bookObj->order_type = $apiData['order_type'] ?? '-';
            $bookObj->transaction_type = $apiData['transaction_type'] ?? '-';
            $bookObj->product = $apiData['product'] ?? '-';
            $bookObj->quantity = $apiData['quantity'] ?? '-';
            $bookObj->status_message = 'Order placed successfully';
            $bookObj->order_datetime = Carbon::now();
        }
        
        $bookObj->price = $apiData['price'] ?? '-';
        $bookObj->user_id = $broker->user_id ?? null;
        $bookObj->save();
    }

    protected function saveFailedOrder($broker, $apiData, $errorMessage)
    {
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
        $bookObj->status_message = $errorMessage;
        $bookObj->order_datetime = Carbon::now();
        $bookObj->user_id = $broker->user_id ?? null;
        $bookObj->save();
    }

    public function getZerodhaSymLotSize($symbol)
    {
        // $lotSizeData = ZerodhaInstrument::select('lot_size', 'tick_size')
        //     ->where('trading_symbol', $symbol)
        //     ->first();
        $data = AngelApiInstrument::select('zi.trading_symbol as kiteSymbol', 'zi.exchange_token','lotsize', 'symbol_name', 'angel_api_instruments.tick_size')
        ->join('zerodha_instruments as zi', 'zi.exchange_token', '=', 'angel_api_instruments.token') // FIXED JOIN CONDITION
        ->where('angel_api_instruments.symbol_name', $symbol)
        ->first();

        if ($data) {
            $tSize = $data->tick_size / 100;
            return [
                'symbol' => $data->kiteSymbol,
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

    public function getLTPFromBuildup($symbolName)
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
    }


    public function callKiteApi(object $omsData)
    {
        $txnType = $omsData->txn_type;
        $symbolType = strtoupper($omsData->symbol_type ?? '');
        
        // Get current LTP from buildup data
        $currentLTP = $this->getLTPFromBuildup($omsData->symbol_name);
        
        if (!$currentLTP) {
            Log::warning("No LTP found for symbol", ['symbol' => $omsData->symbol_name, 'oms_id' => $omsData->id]);
            // Mark as error status to prevent continuous retries
            HistoricalPortfolio::where('id', $omsData->id)->update(['is_api_pushed' => 2]);
            return 2; // Return 2 for permanent error
        }

        $basePayload = [
            "tradingsymbol" => $omsData->symbol_name,
            "exchange" => "NFO",
            "transaction_type" => $txnType,
            "order_type" => $omsData->order_type,
            "product" => $omsData->product,
        ];

        $lotSizeArr = $this->getZerodhaSymLotSize($omsData->symbol_name);
        $basePayload['tradingsymbol'] = $lotSizeArr['symbol'] ?? $omsData->symbol_name;
        $lotSize = $lotSizeArr['lot_size'] ?? null;
        $tickSize = $lotSizeArr['tick_size'] ?? null;

        // Check if lot size data is missing
        if (is_null($lotSize) || is_null($tickSize)) {
            Log::error("Missing lot size or tick size data for symbol", [
                'symbol' => $omsData->symbol_name, 
                'oms_id' => $omsData->id,
                'lot_size' => $lotSize,
                'tick_size' => $tickSize
            ]);
            // Mark as error status to prevent continuous retries
            HistoricalPortfolio::where('id', $omsData->id)->update(['is_api_pushed' => 2]);
            return 2;
        }

        // Ensure numeric
        $lotSize = (int)($lotSize ?: 1);
        $tickSize = (float)($tickSize ?: 0.05);

        $allSucceeded = true;
        $hasAnyPyramid = false;

        // Helper to prepare and place order
        $place = function ($pyramidQty, $discountPercentage) use ($basePayload, $omsData, $currentLTP, $txnType, $lotSize, $tickSize, &$allSucceeded) {
            if ($pyramidQty <= 0) return;

            $payload = $basePayload;
            if ($omsData->order_type === "LIMIT") {
                $payload['price'] = $this->getLimitPrice($currentLTP, $discountPercentage, $txnType, $tickSize);
            }

            $payload['quantity'] = $pyramidQty * $lotSize;

            $res = $this->postPlaceOrder($omsData->broker, $payload);
            if ($res !== 1) $allSucceeded = false;
        };

        $discountLevels = [
            'pyramid_1' => $omsData->disc_ltp ?? 10,
            'pyramid_2' => ($omsData->disc_ltp ?? 10) + 5,
            'pyramid_3' => ($omsData->disc_ltp ?? 10) + 8
        ];
        
        // Process pyramids if set
        if (!is_null($omsData->pyramid_1) && $omsData->pyramid_1 > 0) {
            $place($omsData->pyramid_1, $discountLevels['pyramid_1']);
            $hasAnyPyramid = true;
        }
        if (!is_null($omsData->pyramid_2) && $omsData->pyramid_2 > 0) {
            $place($omsData->pyramid_2, $discountLevels['pyramid_2']);
            $hasAnyPyramid = true;
        }
        if (!is_null($omsData->pyramid_3) && $omsData->pyramid_3 > 0) {
            $place($omsData->pyramid_3, $discountLevels['pyramid_3']);
            $hasAnyPyramid = true;
        }

        // If no pyramids were processed, mark as error
        if (!$hasAnyPyramid) {
            Log::warning("No valid pyramids found for processing", ['oms_id' => $omsData->id]);
            HistoricalPortfolio::where('id', $omsData->id)->update(['is_api_pushed' => 2]);
            return 2;
        }

        return $allSucceeded ? 1 : 0;
    }

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
            // FIX: Handle the case where data might be a JSON string
            $dataSection = $responseArr['data'] ?? null;
            
            // If data is a string, try to decode it
            if (is_string($dataSection)) {
                $decodedData = json_decode($dataSection, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $dataSection = $decodedData;
                }
            }
            
            // Now safely access the order ID
            $orderId = null;
            if (is_array($dataSection)) {
                $orderId = $dataSection['uniqueorderid'] ?? $dataSection['orderid'] ?? null;
            }
            
            if (!$orderId) {
                Log::error("No order ID found in Angel response", ['data' => $dataSection, 'broker' => $broker->account_user_name]);
                return 0;
            }
            
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
            $orderDetailErr = curl_error($curl);
            curl_close($curl);

            if ($orderDetailErr || !$orderDetailResp) {
                \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
                Log::error("Angel get details error", ['err' => $orderDetailErr, 'broker' => $broker->account_user_name]);
                
                // Still create order book entry with available data
                $bookObj = new OrderBook();
                $bookObj->broker_username = $broker->account_user_name;
                $bookObj->order_id = $orderId;
                $bookObj->status = 'placed'; // We know it was placed successfully
                $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
                $bookObj->order_type = $apiData['ordertype'] ?? '-';
                $bookObj->transaction_type = $apiData['transactiontype'] ?? '-';
                $bookObj->product = $apiData['producttype'] ?? '-';
                $bookObj->price = $apiData['price'] ?? '-';
                $bookObj->quantity = $apiData['quantity'] ?? '-';
                $bookObj->status_message = 'Order placed but details fetch failed';
                $bookObj->order_datetime = Carbon::now()->toDateTimeString();
                $bookObj->user_id = $broker->user_id ?? null;
                $bookObj->save();
                
                return 1; // Order was placed successfully even if we can't get details
            }
            
            $detailArr = json_decode($orderDetailResp, true);
            if (!empty($detailArr['status']) && $detailArr['status'] === true) {
                $orderDetails = $detailArr['data'] ?? [];
                
                // Handle case where order details data might also be a string
                if (is_string($orderDetails)) {
                    $decodedOrderDetails = json_decode($orderDetails, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $orderDetails = $decodedOrderDetails;
                    }
                }

                $bookObj = new OrderBook();
                $bookObj->broker_username = $broker->account_user_name;
                $bookObj->order_id = $orderId;
                $bookObj->status = $orderDetails['status'] ?? 'unknown';
                $bookObj->trading_symbol = $orderDetails['tradingsymbol'] ?? ($apiData['tradingsymbol'] ?? '-');
                $bookObj->order_type = $orderDetails['ordertype'] ?? ($apiData['ordertype'] ?? '-');
                $bookObj->transaction_type = $orderDetails['transactiontype'] ?? ($apiData['transactiontype'] ?? '-');
                $bookObj->product = $orderDetails['producttype'] ?? ($apiData['producttype'] ?? '-');
                $bookObj->price = $apiData['price'] ?? '-';
                $bookObj->quantity = $orderDetails['quantity'] ?? ($apiData['quantity'] ?? '-');
                $bookObj->status_message = $orderDetails['text'] ?? 'Order placed successfully';
                $bookObj->order_datetime = isset($orderDetails['updatetime']) ? 
                    date("Y-m-d H:i:s", strtotime($orderDetails['updatetime'])) : 
                    Carbon::now()->toDateTimeString();
                $bookObj->user_id = $broker->user_id ?? null;
                $bookObj->save();
                
                return 1;
            } else {
                \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
                Log::error("Angel order details status false", ['resp' => $detailArr ?? null, 'broker' => $broker->account_user_name]);
                
                // Still create order book entry since the order was placed
                $bookObj = new OrderBook();
                $bookObj->broker_username = $broker->account_user_name;
                $bookObj->order_id = $orderId;
                $bookObj->status = 'placed';
                $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
                $bookObj->order_type = $apiData['ordertype'] ?? '-';
                $bookObj->transaction_type = $apiData['transactiontype'] ?? '-';
                $bookObj->product = $apiData['producttype'] ?? '-';
                $bookObj->price = $apiData['price'] ?? '-';
                $bookObj->quantity = $apiData['quantity'] ?? '-';
                $bookObj->status_message = 'Order placed but status check failed';
                $bookObj->order_datetime = Carbon::now()->toDateTimeString();
                $bookObj->user_id = $broker->user_id ?? null;
                $bookObj->save();
                
                return 1;
            }
        } else {
            \Cache::forget('ANGEL_API_TOKEN_'.$broker->account_user_name);
            Log::error("Angel placeOrder failed", ['response' => $responseArr, 'broker' => $broker->account_user_name]);
            
            $bookObj = new OrderBook();
            $bookObj->broker_username = $broker->account_user_name;
            $bookObj->order_id = '-';
            $bookObj->status = 'failed';
            $bookObj->trading_symbol = $apiData['tradingsymbol'] ?? '-';
            $bookObj->order_type = $apiData['ordertype'] ?? '-';
            $bookObj->transaction_type = $apiData['transactiontype'] ?? '-';
            $bookObj->product = $apiData['producttype'] ?? '-';
            $bookObj->price = $apiData['price'] ?? '-';
            $bookObj->quantity = $apiData['quantity'] ?? '-';
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
        ->join('zerodha_instruments as zi', 'zi.exchange_token', '=', 'angel_api_instruments.token') // FIXED JOIN CONDITION
        ->where('angel_api_instruments.symbol_name', $symbName)
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

    private function getLTP($type, $underlying)
    {
        try {
            $jwtToken =  $this->generate_access_token();
            $data = $this->get_average_price($type,$underlying,$jwtToken);
            
            if ($data['status'] && isset($data['data']['fetched'][0]['ltp'])) {
                return (float) $data['data']['fetched'][0]['ltp'];
            }
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    public function callAngelApi(object $omsData)
    {
        $txnType = $omsData->txn_type;
        $symbolType = strtoupper($omsData->symbol_type ?? '');
        
        // Get current LTP from buildup data
        $currentLTP = $this->getLTP('NFO',$omsData->token);
        
        if (!$currentLTP) {
            Log::warning("No LTP found for symbol (angel)", ['symbol' => $omsData->symbol_name, 'oms_id' => $omsData->id]);
            // Mark as error status to prevent continuous retries
            HistoricalPortfolio::where('id', $omsData->id)->update(['is_api_pushed' => 2]);
            return 2;
        }

        $basePayload = [
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
        // Log::warning("SYMBOL DATA : ", ['symArr' => $symArr]);
        
        // Check if symbol data is missing
        if (is_null($symArr['symbol']) || is_null($symArr['token']) || is_null($symArr['lot_size']) || is_null($symArr['tick_size'])) {
            Log::error("Missing Angel instrument data for symbol", [
                'symbol' => $omsData->symbol_name, 
                'oms_id' => $omsData->id,
                'symbol_data' => $symArr
            ]);
            // Mark as error status to prevent continuous retries
            HistoricalPortfolio::where('id', $omsData->id)->update(['is_api_pushed' => 2]);
            return 2;
        }

        $basePayload["tradingsymbol"] = $symArr['symbol'];
        $basePayload['symboltoken'] = $symArr['token'];
        $tickSize = $symArr['tick_size'] ?? 0.05;
        $lotSize = $symArr['lot_size'] ?? 1;

        $lotSize = (int)$lotSize;
        $tickSize = (float)$tickSize;

        $allSucceeded = true;
        $hasAnyPyramid = false;

        $place = function ($pyramidQty, $discountPercentage) use ($basePayload, $omsData, $currentLTP, $txnType, $lotSize, $tickSize, &$allSucceeded) {
            if ($pyramidQty <= 0) return;

            $payload = $basePayload;
            if ($omsData->order_type === "LIMIT") {
                $payload['price'] = $this->getLimitPrice($currentLTP, $discountPercentage, $txnType, $tickSize);
            }

            $payload['quantity'] = $lotSize * $pyramidQty;

            $res = $this->postPlaceOrderAngel($omsData->broker, $payload);
            if ($res !== 1) $allSucceeded = false;
        };

        $discountLevels = [
            'pyramid_1' => $omsData->disc_ltp ?? 10,     // Base discount (e.g., 10%)
            'pyramid_2' => ($omsData->disc_ltp ?? 10) + 5, // Additional 5% discount (e.g., 15%)
            'pyramid_3' => ($omsData->disc_ltp ?? 10) + 8  // Additional 8% discount (e.g., 18%)
        ];

        if (!is_null($omsData->pyramid_1) && $omsData->pyramid_1 > 0) {
            $place($omsData->pyramid_1, $discountLevels['pyramid_1']);
            $hasAnyPyramid = true;
        }
        if (!is_null($omsData->pyramid_2) && $omsData->pyramid_2 > 0) {
            $place($omsData->pyramid_2, $discountLevels['pyramid_2']);
            $hasAnyPyramid = true;
        }
        if (!is_null($omsData->pyramid_3) && $omsData->pyramid_3 > 0) {
            $place($omsData->pyramid_3, $discountLevels['pyramid_3']);
            $hasAnyPyramid = true;
        }

        // If no pyramids were processed, mark as error
        if (!$hasAnyPyramid) {
            Log::warning("No valid pyramids found for processing (Angel)", ['oms_id' => $omsData->id]);
            HistoricalPortfolio::where('id', $omsData->id)->update(['is_api_pushed' => 2]);
            return 2;
        }

        return $allSucceeded ? 1 : 0;
    }


    public function placeOrder()
    {
        $todayDate = Carbon::now()->toDateString();
        Log::info("PlaceOrder started", ['date' => $todayDate]);
        $processedIds = [];
        $errorIds = [];

        HistoricalPortfolio::select('*')
        ->with('broker')
        ->where(['is_api_pushed' => 0, 'status' => 1])
        ->chunk(100, function ($HistoricalPortfolio) use (&$processedIds, &$errorIds){
            // $ltpData = $this->getBulkLtpData($symbolTokens);
            foreach ($HistoricalPortfolio as $config) {

                // Safety: ensure broker exists
                if (!isset($config->broker) || empty($config->broker)) {
                    Log::warning("OmsConfig missing broker", ['oms_id' => $config->id]);
                    $errorIds[] = $config->id;
                    continue;
                }
                
                $result = 0;
                try {
                    if ($config->broker->client_type == "Zerodha") {
                        $result = $this->callKiteApi($config);
                    } elseif ($config->broker->client_type == "Angel") {
                        $result = $this->callAngelApi($config);
                    } else {
                        Log::warning("Unknown broker type", [
                            'config_id' => $config->id,
                            'broker' => $config->broker->client_type ?? null
                        ]);
                        $errorIds[] = $config->id;
                        continue;
                    }
                } catch (\Exception $e) {
                    Log::error("Exception processing OmsConfig", [
                        'oms_id' => $config->id, 
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $result = 0;
                }

                if ($result == 1) {
                    // Mark success and schedule next run
                    HistoricalPortfolio::where('id', $config->id)->update([
                        'is_api_pushed' => 1,
                        'last_time' => Carbon::now()->addMinutes(5)->toDateTimeString()
                    ]);
                    $processedIds[] = $config->id;
                } elseif ($result == 2) {
                    // Permanent error - already marked in the method, just track it
                    $errorIds[] = $config->id;
                    Log::info("OmsConfig marked as permanent error", ['oms_id' => $config->id]);
                } else {
                    // Temporary error - leave as retry-able (is_api_pushed = 0)
                    Log::warning("Temporary error for OmsConfig, will retry", ['oms_id' => $config->id]);
                }
            }
        });

        // Update cron_run_at only for successfully processed IDs
        if (!empty($processedIds)) {
            HistoricalPortfolio::whereIn('id', $processedIds)
                ->update([
                    'cron_run_at' => DB::raw("DATE_ADD(cron_run_at, INTERVAL pyramid_freq MINUTE)")
                ]);
        }

        // Mark broker missing errors as permanent errors
        if (!empty($errorIds)) {
            HistoricalPortfolio::whereIn('id', $errorIds)
                ->where('is_api_pushed', '!=', 2) // Don't overwrite if already marked as 2
                ->update(['is_api_pushed' => 2]);
        }

        Log::info("PlaceOrder finished", [
            'processed_count' => count($processedIds),
            'error_count' => count($errorIds),
            'total_processed' => count($processedIds) + count($errorIds)
        ]);
    }
}