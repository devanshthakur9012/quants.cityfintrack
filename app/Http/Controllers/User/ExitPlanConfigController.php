<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExitPlanConfig;
use App\Models\ExitPlanOrder;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExitPlanConfigController extends Controller
{
    // =========================================================
    //  CONFIG PAGE
    // =========================================================

    public function config()
    {
        $pageTitle = 'Exit Plan — Auto Trading Configuration';

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = ExitPlanConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.oiiv-auto.exit-plan-config', compact(
            'pageTitle', 'brokers', 'configs'
        ));
    }

    // =========================================================
    //  VIEW ORDERS
    // =========================================================

    public function viewOrders(int $configId)
    {
        $config = ExitPlanConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = ExitPlanOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.oiiv-auto.exit-plan-orders', [
            'pageTitle' => 'Exit Plan Orders — ' . $config->broker->client_name,
            'config'    => $config,
            'orders'    => $orders,
        ]);
    }

    // =========================================================
    //  STORE
    // =========================================================

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'signal_mode'       => 'required|in:align,opposite',
            'status'            => 'required|in:1,0',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
        ]);

        try {
            ExitPlanConfig::create([
                'user_id'           => Auth::id(),
                'broker_api_id'     => $request->broker_api_id,
                'order_type'        => $request->order_type,
                'product'           => $request->product,
                'disc_ltp'          => $request->disc_ltp,
                'signal_mode'       => $request->signal_mode,
                'status'            => $request->status,
                'index_ce_quantity' => $request->index_ce_quantity,
                'index_pe_quantity' => $request->index_pe_quantity,
                'stock_ce_quantity' => $request->stock_ce_quantity,
                'stock_pe_quantity' => $request->stock_pe_quantity,
            ]);

            $notify[] = ['success', 'Exit Plan configuration created successfully!'];

        } catch (\Exception $e) {
            Log::error('ExitPlanConfig Store: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration: ' . $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    // =========================================================
    //  UPDATE
    // =========================================================

    public function update(Request $request, int $id)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'signal_mode'       => 'required|in:align,opposite',
            'status'            => 'required|in:1,0',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
        ]);

        $config = ExitPlanConfig::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $config->update([
            'broker_api_id'     => $request->broker_api_id,
            'order_type'        => $request->order_type,
            'product'           => $request->product,
            'disc_ltp'          => $request->disc_ltp,
            'signal_mode'       => $request->signal_mode,
            'status'            => $request->status,
            'index_ce_quantity' => $request->index_ce_quantity,
            'index_pe_quantity' => $request->index_pe_quantity,
            'stock_ce_quantity' => $request->stock_ce_quantity,
            'stock_pe_quantity' => $request->stock_pe_quantity,
        ]);

        $notify[] = ['success', 'Configuration updated successfully!'];
        return back()->withNotify($notify);
    }

    // =========================================================
    //  TOGGLE STATUS
    // =========================================================

    public function toggleStatus(int $id)
    {
        try {
            $config = ExitPlanConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->status = !$config->status;
            $config->save();

            $label   = $config->status ? 'activated' : 'deactivated';
            $notify[] = ['success', "Configuration {$label}!"];

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating configuration.'];
        }

        return back()->withNotify($notify);
    }

    // =========================================================
    //  DELETE
    // =========================================================

    public function destroy(int $id)
    {
        try {
            $config = ExitPlanConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $pending = $config->orders()
                ->where('is_order_placed', false)
                ->where('status', true)
                ->count();

            if ($pending > 0) {
                $notify[] = ['error', "Cannot delete — {$pending} pending orders still exist."];
                return back()->withNotify($notify);
            }

            $config->delete();
            $notify[] = ['success', 'Configuration deleted successfully!'];

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error deleting configuration.'];
        }

        return back()->withNotify($notify);
    }

    // =========================================================
    //  MANUAL RUN — per config button
    // =========================================================

    public function runManually(Request $request, int $id)
    {
        try {
            ExitPlanConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $testDate = $request->get('test_date') ?: null;

            $helper = new \App\Helpers\ExitPlanTradingHelper();
            $helper->processSignals($testDate);
            $helper->placeOrders($testDate);

            $notify[] = ['success', "Exit Plan signals processed and orders placed for config #{$id}!"];

        } catch (\Exception $e) {
            Log::error("ExitPlan manual run config {$id}: " . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    // =========================================================
    //  RUN ALL — global button
    // =========================================================

    public function runAllSignals(Request $request)
    {
        try {
            $testDate = $request->get('test_date') ?: null;

            $helper = new \App\Helpers\ExitPlanTradingHelper();
            $helper->processSignals($testDate);
            $helper->placeOrders($testDate);

            $notify[] = ['success', 'Exit Plan signals processed and orders placed!'];

        } catch (\Exception $e) {
            Log::error('ExitPlan runAll: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
        }

        return back()->withNotify($notify);
    }
}