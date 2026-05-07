<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\KiteConnectCls;
use App\Models\BrokerApi;

class KiteAuthController extends Controller
{
    public function initiateAuth(Request $request, $brokerId)
    {
        $broker = BrokerApi::findOrFail($brokerId);
        
        $params = [
            'accountUserName' => $broker->account_user_name,
            'apiKey' => $broker->api_key,
            'apiSecret' => $broker->api_secret_key
        ];
        
        $kiteObj = new KiteConnectCls($params);
        $loginUrl = $kiteObj->getLoginUrl();
        
        // Store broker ID in session for callback
        session(['kite_auth_broker' => $brokerId]);
        
        return redirect($loginUrl);
    }
    
    public function authCallback(Request $request)
    {
        $requestToken = $request->get('request_token');
        $brokerId = session('kite_auth_broker');
        
        if (!$requestToken || !$brokerId) {
            return redirect('/brokers')->with('error', 'Authentication failed');
        }
        
        $broker = BrokerApi::findOrFail($brokerId);
        
        $params = [
            'accountUserName' => $broker->account_user_name,
            'apiKey' => $broker->api_key,
            'apiSecret' => $broker->api_secret_key
        ];
        
        $kiteObj = new KiteConnectCls($params);
        
        if ($kiteObj->generateSession($requestToken)) {
            return redirect('/brokers')->with('success', 'KiteConnect authentication successful');
        } else {
            return redirect('/brokers')->with('error', 'KiteConnect authentication failed');
        }
    }
}
