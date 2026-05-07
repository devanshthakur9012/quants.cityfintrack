<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FutOhlAutoConfig;
use App\Models\FutOhlAutoOrder;
use App\Models\BrokerApi;
use App\Helpers\FutOhlAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Auth;

class FutOhlAutoController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function config()
    {
        $pageTitle = 'FUT Open=High/Low Auto Trading';

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = FutOhlAutoConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Recent orders (last 10 across all configs, for the summary panel)
        $recentOrders = FutOhlAutoOrder::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view($this->activeTemplate . 'user.fut-ohl.config', [
            'pageTitle'    => $pageTitle,
            'brokers'      => $brokers,
            'configs'      => $configs,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function viewOrders($configId)
    {
        $config = FutOhlAutoConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = FutOhlAutoOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.fut-ohl.orders', [
            'pageTitle' => 'FUT OHL Orders — Config #' . $config->id,
            'config'    => $config,
            'orders'    => $orders,
        ]);
    }

    // =========================================================
    //  MANUAL TRIGGER (called by "Run Now" button via AJAX)
    // =========================================================

    /**
     * POST /fut-ohl-auto/run-now
     * Runs signal detection + order placement immediately.
     * Returns JSON summary for the UI.
     */
    public function runNow(Request $request)
    {
        try {
            $testDate = $request->get('test_date'); // optional, for testing

            Log::info("FUT OHL: Manual trigger by user " . Auth::id() . ($testDate ? " | testDate={$testDate}" : ''));

            $helper = new FutOhlAutoTradingHelper();

            $detectSummary = $helper->processSignals($testDate);
            $placeSummary  = $helper->placeOrders($testDate);

            return response()->json([
                'success' => true,
                'message' => "Done! Detected: {$detectSummary['detected']} | Created: {$detectSummary['created']} | Placed: {$placeSummary['placed']} | Errors: " . ($detectSummary['errors'] + $placeSummary['failed']),
                'summary' => [
                    'detected' => $detectSummary['detected'],
                    'created'  => $detectSummary['created'],
                    'skipped'  => $detectSummary['skipped'],
                    'placed'   => $placeSummary['placed'],
                    'failed'   => $placeSummary['failed'],
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error("FUT OHL runNow: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /fut-ohl-auto/place-pending
     * Only places existing pending orders (no new signal detection).
     */
    public function placePending(Request $request)
    {
        try {
            $helper   = new FutOhlAutoTradingHelper();
            $summary  = $helper->placeOrders();

            return response()->json([
                'success' => true,
                'message' => "Placed: {$summary['placed']} | Failed: {$summary['failed']}",
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  CONFIG CRUD
    // =========================================================

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'tolerance'     => 'required|numeric|min:0|max:100',
            'signal_mode'   => 'required|in:align,opposite',
            'option_series' => 'required|in:current,next',
            'order_type'    => 'required|in:LIMIT,MARKET',
            'product'       => 'required|in:NRML,MIS',
            'disc_ltp'      => 'required|numeric|min:0|max:100',
            'ce_quantity'   => 'required|integer|min:0',
            'pe_quantity'   => 'required|integer|min:0',
            'status'        => 'required|in:1,0',
        ]);

        try {
            FutOhlAutoConfig::create(array_merge(
                $request->only([
                    'broker_api_id', 'tolerance', 'signal_mode', 'option_series',
                    'order_type', 'product', 'disc_ltp', 'ce_quantity', 'pe_quantity',
                ]),
                ['user_id' => Auth::id(), 'status' => (bool)$request->status]
            ));
            $notify[] = ['success', 'FUT OHL configuration created!'];
        } catch (\Throwable $e) {
            Log::error('FUT OHL Config Store: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration'];
        }

        return back()->withNotify($notify);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'tolerance'     => 'required|numeric|min:0|max:100',
            'signal_mode'   => 'required|in:align,opposite',
            'option_series' => 'required|in:current,next',
            'order_type'    => 'required|in:LIMIT,MARKET',
            'product'       => 'required|in:NRML,MIS',
            'disc_ltp'      => 'required|numeric|min:0|max:100',
            'ce_quantity'   => 'required|integer|min:0',
            'pe_quantity'   => 'required|integer|min:0',
            'status'        => 'required|in:1,0',
        ]);

        $config = FutOhlAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $config->update(array_merge(
            $request->only([
                'broker_api_id', 'tolerance', 'signal_mode', 'option_series',
                'order_type', 'product', 'disc_ltp', 'ce_quantity', 'pe_quantity',
            ]),
            ['status' => (bool)$request->status]
        ));

        $notify[] = ['success', 'Configuration updated!'];
        return back()->withNotify($notify);
    }

    public function toggleStatus($id)
    {
        $config = FutOhlAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $config->status = !$config->status;
        $config->save();
        $notify[] = ['success', 'Config ' . ($config->status ? 'activated' : 'deactivated') . '!'];
        return back()->withNotify($notify);
    }

    public function destroy($id)
    {
        $config  = FutOhlAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $pending = $config->orders()->where('is_order_placed', false)->where('status', true)->count();
        if ($pending > 0) {
            return back()->withNotify([['error', "Cannot delete — {$pending} orders pending."]]);
        }
        $config->delete();
        return back()->withNotify([['success', 'Configuration deleted!']]);
    }
}