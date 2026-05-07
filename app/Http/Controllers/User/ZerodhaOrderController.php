<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ZerodhaOrder;
use App\Models\ZerodhaPortfolio;
use App\Models\BrokerApi;
use Auth;
use Illuminate\Support\Facades\Log;

class ZerodhaOrderController extends Controller
{
    public function orderZerodhaMaster()
    {
        $pageTitle = 'Zerodha Order Master';
        
        // Get only Zerodha brokers
        $brokers = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();
            
        $masterConfigs = ZerodhaOrder::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->paginate(50);

        return view($this->activeTemplate . 'user.zerodha-order.config', [
            'pageTitle' => $pageTitle,
            'brokers' => $brokers,
            'masterConfigs' => $masterConfigs
        ]);
    }

    public function storeZerodhaMaster(Request $request)
    {
        $request->validate([
            'buildup_type' => 'required|string|in:Strong Bullish,Mild Bullish,Mild Bearish,Strong Bearish',
            'broker_api_id' => 'required|exists:broker_apis,id',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'order_type' => 'required|in:LIMIT,MARKET',
            'pyramid_percent' => 'nullable|numeric|in:33,50,100',
            'product' => 'required|in:NRML,MIS',
            'quantity' => 'required|integer|min:1',
            'pyramid_freq' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
            'order_date' => 'required|date',
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
            ZerodhaOrder::create([
                'buildup_type' => $request->buildup_type,
                'broker_api_id' => $request->broker_api_id,
                'disc_ltp' => $request->disc_ltp,
                'order_type' => $request->order_type,
                'order_date' => $request->order_date,
                'pyramid_percent' => $request->pyramid_percent ?? 100,
                'product' => $request->product,
                'quantity' => $request->quantity,
                'pyramid_freq' => $request->pyramid_freq,
                'exit_1_qty' => $request->exit_1_qty ?? 0,
                'exit_1_target' => $request->exit_1_target ?? 0,
                'exit_2_qty' => $request->exit_2_qty ?? 0,
                'exit_2_target' => $request->exit_2_target ?? 0,
                'user_id' => Auth::id(),
                'status' => $request->status,
            ]);

            $notify[] = ['success', 'Zerodha master configuration created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Zerodha Master Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating master configuration: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function zerodhaOrderMaster($masterId)
    {
        $pageTitle = 'Zerodha Config Orders';
        $userId = Auth::id();

        $masterConfig = ZerodhaOrder::where('user_id', $userId)
            ->where('id', $masterId)
            ->first();

        if (!$masterConfig) {
            $notify[] = ['error', 'Invalid Request'];
            return redirect()->route('user.order-zerodha-master')->withNotify($notify);
        }

        // Fetch portfolios linked to this master config
        $configOrders = ZerodhaPortfolio::where('user_id', $userId)
            ->where('config_id', $masterId)
            ->with('broker:id,client_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.zerodha-order.config-order', [
            'pageTitle' => $pageTitle,
            'configOrders' => $configOrders,
            'masterConfig' => $masterConfig
        ]);
    }
    
    public function zerodhaOrderDestroy($id)
    {
        try {
            $masterConfig = ZerodhaOrder::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $masterConfig->status = 0;
            $masterConfig->save();

            $notify[] = ['success', 'Master configuration made inactive successfully!'];
            return redirect()->back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Zerodha Master Config Inactive Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deactivating master configuration.'];
            return redirect()->back()->withNotify($notify);
        }
    }
}