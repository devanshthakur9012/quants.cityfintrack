<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\BrokerApi;
use Auth;
use Illuminate\Support\Facades\Log;

class ZerodhaAutoController extends Controller
{
    /**
     * Display auto config list
     */
    public function index()
    {
        $pageTitle = 'Zerodha Auto Trading Configuration';
        
        $brokers = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();
            
        $configs = ZerodhaAutoConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.zerodha-auto.index', [
            'pageTitle' => $pageTitle,
            'brokers' => $brokers,
            'configs' => $configs
        ]);
    }

    /**
     * Store new auto config
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'order_type' => 'required|in:LIMIT,MARKET',
            'product' => 'required|in:NRML,MIS',
            'signal_strategy' => 'required|in:SUPERTREND,VWAP,BOTH',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'profit_percent' => 'required|numeric|min:0|max:1000',
            'index_quantity' => 'required|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'pyramid_percent' => 'required|in:100,50,33',
            'pyramid_freq' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
            // 'enable_quality_filter' => 'nullable|boolean',
            'option_series' => 'required|in:current,next',
            'option_filter' => 'required|in:CE,PE,BOTH', // ✅ ADDED
        ]);

        $broker = BrokerApi::where('id', $request->broker_api_id)
            ->where('client_type', 'Zerodha')
            ->where('user_id', Auth::id())
            ->first();

        if (!$broker) {
            $notify[] = ['error', 'Invalid broker selected.'];
            return back()->withNotify($notify);
        }

        try {
            ZerodhaAutoConfig::create([
                'user_id' => Auth::id(),
                'broker_api_id' => $request->broker_api_id,
                'order_type' => $request->order_type,
                'product' => $request->product,
                'signal_strategy' => $request->signal_strategy,
                'disc_ltp' => $request->disc_ltp,
                'profit_percent' => $request->profit_percent,
                'index_quantity' => $request->index_quantity,
                'stock_quantity' => $request->stock_quantity,
                'pyramid_percent' => $request->pyramid_percent,
                'pyramid_freq' => $request->pyramid_freq,
                'status' => $request->status,
                'option_series' => $request->option_series,
                'option_filter' => $request->option_filter, // ✅ ADDED
                'enable_quality_filter' => false,
            ]);

            $notify[] = ['success', 'Auto trading configuration created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Zerodha Auto Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration'];
            return back()->withNotify($notify);
        }
    }

    /**
     * View orders
     */
    public function viewOrders($configId)
    {
        $pageTitle = 'Auto Trading Orders';
        
        $config = ZerodhaAutoConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = ZerodhaAutoOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with(['broker:id,client_name'])
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.zerodha-auto.orders', [
            'pageTitle' => $pageTitle,
            'config' => $config,
            'orders' => $orders
        ]);
    }

    /**
     * Toggle status
     */
    public function toggleStatus($id)
    {
        try {
            $config = ZerodhaAutoConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->status = !$config->status;
            $config->save();

            $status = $config->status ? 'activated' : 'deactivated';
            $notify[] = ['success', "Configuration {$status} successfully!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating configuration.'];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete config
     */
    public function destroy($id)
    {
        try {
            $config = ZerodhaAutoConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $pendingOrders = $config->orders()
                ->where('is_order_placed', false)
                ->where('status', true)
                ->count();

            if ($pendingOrders > 0) {
                $notify[] = ['error', "Cannot delete. {$pendingOrders} orders pending."];
                return back()->withNotify($notify);
            }

            $config->delete();

            $notify[] = ['success', 'Configuration deleted successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error deleting configuration.'];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'order_type' => 'required|in:LIMIT,MARKET',
            'product' => 'required|in:NRML,MIS',
            'signal_strategy' => 'required|in:SUPERTREND,VWAP,BOTH',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'profit_percent' => 'required|numeric|min:0|max:1000',
            'index_quantity' => 'required|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'pyramid_percent' => 'required|in:100,50,33',
            'pyramid_freq' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
            'option_series' => 'required|in:current,next',
            'option_filter' => 'required|in:CE,PE,BOTH',
        ]);

        $config = ZerodhaAutoConfig::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $config->update($request->only([
            'broker_api_id',
            'order_type',
            'product',
            'signal_strategy',
            'disc_ltp',
            'profit_percent',
            'index_quantity',
            'stock_quantity',
            'pyramid_percent',
            'pyramid_freq',
            'status',
            'option_series',
            'option_filter',
        ]));

        $notify[] = ['success', 'Configuration updated successfully!'];
        return back()->withNotify($notify);
    }

}