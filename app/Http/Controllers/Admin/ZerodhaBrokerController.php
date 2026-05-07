<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\SymbolMonitored;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Auth;

class ZerodhaBrokerController extends Controller
{
    /**
     * Display all Zerodha brokers
     */
    public function index()
    {
        $pageTitle = 'Zerodha Broker Management';

        $brokers = BrokerApi::where('client_type', 'Zerodha')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.zerodha-broker.index', compact('pageTitle', 'brokers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_user_name' => 'required|string|max:255',
            'account_password'  => 'required|string',
            'api_key'           => 'required|string',
            'api_secret_key'    => 'required|string',
            'security_pin'      => 'nullable|string',
            'totp'              => 'required|string',
        ]);

        try {
            $exists = BrokerApi::where('client_type', 'Zerodha')
                ->where('account_user_name', $request->account_user_name)
                ->exists();

            if ($exists) {
                $notify[] = ['error', 'Broker with this username already exists'];
                return back()->withNotify($notify);
            }

            $admin = auth('admin')->user()->id;

            BrokerApi::create([
                'user_id'           => $admin,
                'client_name'       => 'Zerodha',   // hardcoded
                'broker_name'       => 'Zerodha',   // hardcoded
                'client_type'       => 'Zerodha',   // hardcoded
                'account_user_name' => $request->account_user_name,
                'account_password'  => $request->account_password,
                'api_key'           => $request->api_key,
                'api_secret_key'    => $request->api_secret_key,
                'security_pin'      => $request->security_pin,
                'totp'              => $request->totp,
                'is_token_valid'    => false,
            ]);

            $notify[] = ['success', 'Zerodha broker added successfully!'];
            return redirect()->route('admin.zerodha-broker.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Admin Zerodha Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error adding broker: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id)
    {
        $broker = BrokerApi::where('id', $id)
            ->where('client_type', 'Zerodha')
            ->firstOrFail();

        $request->validate([
            'account_user_name' => 'required|string|max:255',
            'account_password'  => 'nullable|string',
            'api_key'           => 'required|string',
            'api_secret_key'    => 'required|string',
            'security_pin'      => 'nullable|string',
            'totp'              => 'required|string',
        ]);

        try {
            $broker->update([
                'client_name'       => 'Zerodha',   // hardcoded
                'broker_name'       => 'Zerodha',   // hardcoded
                'account_user_name' => $request->account_user_name,
                'api_key'           => $request->api_key,
                'api_secret_key'    => $request->api_secret_key,
                'security_pin'      => $request->security_pin,
                'totp'              => $request->totp,
            ]);

            if ($request->filled('account_password')) {
                $broker->update(['account_password' => $request->account_password]);
            }

            $notify[] = ['success', 'Broker updated successfully!'];
            return redirect()->route('admin.zerodha-broker.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Admin Zerodha Update Error: ' . $e->getMessage());
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
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            SymbolMonitored::where('broker_api_id', $id)->delete();
            $broker->delete();

            $notify[] = ['success', 'Broker deleted successfully!'];
            return redirect()->route('admin.zerodha-broker.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Admin Zerodha Delete Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deleting broker: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Redirect to Zerodha login
     */
    public function login($id)
    {
        $broker = BrokerApi::where('id', $id)
            ->where('client_type', 'Zerodha')
            ->firstOrFail();

        session(['admin_zerodha_broker_id' => $id]);

        return redirect("https://kite.zerodha.com/connect/login?api_key={$broker->api_key}");
    }

    /**
     * Update access token from callback URL
     */
    public function updateToken(Request $request, $id)
    {
        $request->validate([
            'callback_url' => 'required|url'
        ]);

        try {
            $broker = BrokerApi::where('id', $id)
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            $parsedUrl = parse_url($request->callback_url);

            if (!isset($parsedUrl['query'])) {
                throw new \Exception('Invalid callback URL format');
            }

            parse_str($parsedUrl['query'], $queryParams);

            if (!isset($queryParams['request_token'])) {
                throw new \Exception('Request token not found in URL');
            }

            $kite     = new KiteConnect($broker->api_key);
            $response = $kite->generateSession($queryParams['request_token'], $broker->api_secret_key);

            if (!isset($response->access_token)) {
                throw new \Exception('Failed to generate access token');
            }

            $broker->update([
                'access_token'     => $response->access_token,
                'token_expires_at' => now()->addHours(23),
                'last_login_at'    => now(),
                'is_token_valid'   => true,
            ]);

            $notify[] = ['success', 'Access token generated and saved successfully!'];
            return redirect()->route('admin.zerodha-broker.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Admin Token Update Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error generating token: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}