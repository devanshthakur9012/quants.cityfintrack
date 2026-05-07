<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\SymbolMonitored;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;

class ZerodhaBrokerController extends Controller
{
    /**
     * Display Zerodha brokers list
     */
    public function index()
    {
        $pageTitle = 'Zerodha Brokers';
        
        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->withCount('monitoredSymbols')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view($this->activeTemplate . 'user.zerodha-broker.index', compact('pageTitle', 'brokers'));
    }

    /**
     * Store new Zerodha broker
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'broker_name' => 'required|string|max:255',
            'account_user_name' => 'required|string|max:255',
            'account_password' => 'required|string',
            'api_key' => 'required|string',
            'api_secret_key' => 'required|string',
            'security_pin' => 'nullable|string',
            'totp' => 'required|string'
        ]);

        try {
            // Check if broker already exists for this user
            $exists = BrokerApi::where('user_id', auth()->id())
                ->where('client_type', 'Zerodha')
                ->where('account_user_name', $request->account_user_name)
                ->exists();

            if ($exists) {
                $notify[] = ['error', 'Broker with this username already exists'];
                return back()->withNotify($notify);
            }

            $broker = BrokerApi::create([
                'user_id' => auth()->id(),
                'client_name' => $request->client_name,
                'broker_name' => $request->broker_name,
                'account_user_name' => $request->account_user_name,
                'account_password' => $request->account_password,
                'api_key' => $request->api_key,
                'api_secret_key' => $request->api_secret_key,
                'security_pin' => $request->security_pin,
                'totp' => $request->totp,
                'client_type' => 'Zerodha',
                'is_token_valid' => false
            ]);

            $notify[] = ['success', 'Zerodha broker added successfully! Click "Login" to generate access token.'];
            return redirect()->route('zerodha-broker.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Zerodha Broker Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error adding broker: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Update broker details
     */
    public function update(Request $request, $id)
    {
        $broker = BrokerApi::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->firstOrFail();

        $request->validate([
            'client_name' => 'required|string|max:255',
            'broker_name' => 'required|string|max:255',
            'account_user_name' => 'required|string|max:255',
            'account_password' => 'nullable|string',
            'api_key' => 'required|string',
            'api_secret_key' => 'required|string',
            'security_pin' => 'nullable|string',
            'totp' => 'required|string'
        ]);

        try {
            $broker->update([
                'client_name' => $request->client_name,
                'broker_name' => $request->broker_name,
                'account_user_name' => $request->account_user_name,
                'api_key' => $request->api_key,
                'api_secret_key' => $request->api_secret_key,
                'security_pin' => $request->security_pin,
                'totp' => $request->totp
            ]);

            if ($request->filled('account_password')) {
                $broker->update(['account_password' => $request->account_password]);
            }

            $notify[] = ['success', 'Broker details updated successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Zerodha Broker Update Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error updating broker: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete broker
     */
    public function destroy($id)
    {
        try {
            $broker = BrokerApi::where('id', $id)
                ->where('user_id', auth()->id())
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            // Delete associated symbols
            SymbolMonitored::where('broker_api_id', $id)->delete();
            $broker->delete();

            $notify[] = ['success', 'Broker deleted successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Zerodha Broker Delete Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deleting broker: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Redirect to Zerodha login (manual login option)
     */
    public function login($id)
    {
        $broker = BrokerApi::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->firstOrFail();

        $loginUrl = "https://kite.zerodha.com/connect/login?api_key={$broker->api_key}";
        
        // Store broker ID in session for reference
        session(['zerodha_broker_id' => $id]);
        
        return redirect($loginUrl);
    }

    /**
     * Update access token from callback URL
     */
    public function updateToken(Request $request, $id)
    {
        $request->validate([
            'callback_url' => 'required|string|url'
        ]);

        try {
            $broker = BrokerApi::where('id', $id)
                ->where('user_id', auth()->id())
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            // Parse the callback URL to extract request_token
            $parsedUrl = parse_url($request->callback_url);
            
            if (!isset($parsedUrl['query'])) {
                throw new \Exception('Invalid callback URL format');
            }

            parse_str($parsedUrl['query'], $queryParams);
            
            if (!isset($queryParams['request_token'])) {
                throw new \Exception('Request token not found in URL');
            }

            $requestToken = $queryParams['request_token'];

            // Generate access token using KiteConnect
            $kite = new KiteConnect($broker->api_key);
            $response = $kite->generateSession($requestToken, $broker->api_secret_key);
            
            if (!isset($response->access_token)) {
                throw new \Exception('Failed to generate access token');
            }

            $accessToken = $response->access_token;

            // Update broker with access token
            $broker->update([
                'access_token' => $accessToken,
                'token_expires_at' => now()->addHours(23),
                'last_login_at' => now(),
                'is_token_valid' => true
            ]);

            Log::info("Access token generated successfully for broker ID: {$id}");

            $notify[] = ['success', 'Access token generated and saved successfully! Token valid till 6 AM tomorrow.'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Token Update Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error generating token: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Check token status
     */
    public function checkTokenStatus($id)
    {
        $broker = BrokerApi::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->firstOrFail();

        $isValid = $broker->is_token_valid && 
                   $broker->token_expires_at && 
                   $broker->token_expires_at->isFuture();

        return response()->json([
            'valid' => $isValid,
            'expires_at' => $broker->token_expires_at ? $broker->token_expires_at->format('Y-m-d H:i:s') : null,
            'last_login' => $broker->last_login_at ? $broker->last_login_at->diffForHumans() : null
        ]);
    }

    /**
     * Get broker edit form
     */
    public function getBrokerDetails($id)
    {
        $broker = BrokerApi::where('id', $id)
            ->where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->firstOrFail();

        return view($this->activeTemplate . 'user.zerodha-broker.edit-modal', compact('broker'));
    }
}