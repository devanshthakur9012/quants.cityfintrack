<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BrokerApi;
use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\Log;

class ZerodhaAutoLoginController extends Controller
{
    public function brokers(Request $request)
    {
        if ($request->header('X-API-SECRET') !== env('ZERODHA_LOGIN_SECRET')) {
            return response()->json([
                'success' => false
            ], 401);
        }

        $brokers = BrokerApi::where('client_type', 'Zerodha')
            ->get([
                'id',
                'account_user_name',
                'account_password',
                'totp',
                'api_key',
                'api_secret_key'
            ]);

        return response()->json([
            'success' => true,
            'data' => $brokers
        ]);
    }

    public function saveToken(Request $request)
    {
        try {

            if ($request->header('X-API-SECRET') !== env('ZERODHA_LOGIN_SECRET')) {
                return response()->json([
                    'success' => false
                ], 401);
            }

            $request->validate([
                'broker_id' => 'required',
                'request_token' => 'required'
            ]);

            $broker = BrokerApi::findOrFail(
                $request->broker_id
            );

            $kite = new KiteConnect(
                $broker->api_key
            );

            $response = $kite->generateSession(
                $request->request_token,
                $broker->api_secret_key
            );

            $broker->update([
                'access_token'   => $response->access_token,
                'is_token_valid' => true,
                'last_login_at'  => now(),
                'token_expires_at' => now()->addHours(23),
            ]);

            return response()->json([
                'success' => true
            ]);

        } catch (\Exception $e) {

            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}