<?php
// app/Http/Controllers/User/ExpiryAutoConfigController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ExpiryAutoConfig;
use App\Models\ExpiryAutoOrder;
use App\Models\BrokerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Auth;

class ExpiryAutoConfigController extends Controller
{
    /**
     * Display expiry auto trading configurations
     */
    public function index()
    {
        $pageTitle = 'Expiry Auto Trading Configurations (1-Minute)';
        
        $brokers = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();
            
        $configs = ExpiryAutoConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->paginate(50);

        return view($this->activeTemplate . 'user.expiry.auto-config', compact('pageTitle', 'brokers', 'configs'));
    }

    /**
     * Store new expiry auto trading configuration
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'order_type' => 'required|in:LIMIT,MARKET',
            'product' => 'required|in:NRML,MIS',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'nifty_quantity' => 'required|integer|min:1',
            'banknifty_quantity' => 'required|integer|min:1',
            'sensex_quantity' => 'required|integer|min:1',
            'pyramid_percent' => 'required|in:33,50,100',
            'pyramid_freq' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
        ]);

        // Verify broker is Zerodha
        $broker = BrokerApi::where('id', $request->broker_api_id)
            ->where('client_type', 'Zerodha')
            ->where('user_id', Auth::id())
            ->first();

        if (!$broker) {
            $notify[] = ['error', 'Invalid broker selected. Only Zerodha brokers are allowed.'];
            return back()->withNotify($notify);
        }

        try {
            ExpiryAutoConfig::create([
                'user_id' => Auth::id(),
                'broker_api_id' => $request->broker_api_id,
                'order_type' => $request->order_type,
                'product' => $request->product,
                'disc_ltp' => $request->disc_ltp,
                'nifty_quantity' => $request->nifty_quantity,
                'banknifty_quantity' => $request->banknifty_quantity,
                'sensex_quantity' => $request->sensex_quantity,
                'pyramid_percent' => $request->pyramid_percent,
                'pyramid_freq' => $request->pyramid_freq,
                'status' => $request->status,
            ]);

            $notify[] = ['success', 'Expiry trading configuration created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Expiry Auto Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Toggle configuration status
     */
    public function toggleStatus($id)
    {
        try {
            $config = ExpiryAutoConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->status = !$config->status;
            $config->save();

            return response()->json([
                'success' => true,
                'message' => 'Configuration status updated successfully!',
                'new_status' => $config->status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View orders for a configuration
     */
    public function orders($configId)
    {
        $pageTitle = 'Expiry Trading Orders';

        $config = ExpiryAutoConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->first();

        if (!$config) {
            $notify[] = ['error', 'Invalid Request'];
            return redirect()->route('expiry.auto.index')->withNotify($notify);
        }

        $orders = ExpiryAutoOrder::where('user_id', Auth::id())
            ->where('config_id', $configId)
            ->with('broker:id,client_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.expiry.orders', compact('pageTitle', 'config', 'orders'));
    }

    /**
     * Delete/Deactivate configuration
     */
    public function destroy($id)
    {
        try {
            $config = ExpiryAutoConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->status = 0;
            $config->save();

            $notify[] = ['success', 'Configuration deactivated successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Expiry Auto Config Deactivate Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deactivating configuration.'];
            return back()->withNotify($notify);
        }
    }
}