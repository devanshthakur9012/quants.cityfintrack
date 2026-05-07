<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\Log;
use Auth;

class NewPivotOrderConfigController extends Controller
{
    // ── Pages ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Pivot Order Config — NIFTY & BANKNIFTY';

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = NewPivotOrderConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.pivot-signal.config', compact(
            'pageTitle', 'brokers', 'configs'
        ));
    }

    public function viewOrders($configId)
    {
        $pageTitle = 'Pivot Orders';

        $config = NewPivotOrderConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = NewPivotOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.pivot-signal.orders', compact(
            'pageTitle', 'config', 'orders'
        ));
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'          => 'required|exists:broker_apis,id',
            'symbols'                => 'required|in:BOTH,NIFTY,BANKNIFTY',
            'option_type'            => 'required|in:BOTH,CE,PE',
            'order_type'             => 'required|in:LIMIT,MARKET',
            'product'                => 'required|in:NRML,MIS',
            's1_discount_direction'  => 'required|in:positive,negative',
            's1_discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_discount_direction'  => 'required|in:positive,negative',
            'r1_discount_pct'        => 'required|numeric|min:0|max:100',
            's1_ce_quantity'         => 'required|integer|min:0',
            's1_pe_quantity'         => 'required|integer|min:0',
            'r1_ce_quantity'         => 'required|integer|min:0',
            'r1_pe_quantity'         => 'required|integer|min:0',
            'status'                 => 'required|in:0,1',
        ]);

        try {
            NewPivotOrderConfig::create(array_merge(
                $request->only([
                    'broker_api_id', 'symbols', 'option_type', 'order_type', 'product',
                    's1_discount_direction', 's1_discount_pct',
                    'r1_discount_direction', 'r1_discount_pct',
                    's1_ce_quantity', 's1_pe_quantity',
                    'r1_ce_quantity', 'r1_pe_quantity', 'status',
                ]),
                ['user_id' => Auth::id()]
            ));

            return back()->withNotify([['success', 'Pivot order config created successfully!']]);
        } catch (\Exception $e) {
            Log::error('NewPivotOrderConfig store: ' . $e->getMessage());
            return back()->withNotify([['error', 'Error creating config: ' . $e->getMessage()]]);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id'          => 'required|exists:broker_apis,id',
            'symbols'                => 'required|in:BOTH,NIFTY,BANKNIFTY',
            'option_type'            => 'required|in:BOTH,CE,PE',
            'order_type'             => 'required|in:LIMIT,MARKET',
            'product'                => 'required|in:NRML,MIS',
            's1_discount_direction'  => 'required|in:positive,negative',
            's1_discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_discount_direction'  => 'required|in:positive,negative',
            'r1_discount_pct'        => 'required|numeric|min:0|max:100',
            's1_ce_quantity'         => 'required|integer|min:0',
            's1_pe_quantity'         => 'required|integer|min:0',
            'r1_ce_quantity'         => 'required|integer|min:0',
            'r1_pe_quantity'         => 'required|integer|min:0',
            'status'                 => 'required|in:0,1',
        ]);

        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->update($request->only([
                'broker_api_id', 'symbols', 'option_type', 'order_type', 'product',
                's1_discount_direction', 's1_discount_pct',
                'r1_discount_direction', 'r1_discount_pct',
                's1_ce_quantity', 's1_pe_quantity',
                'r1_ce_quantity', 'r1_pe_quantity', 'status',
            ]));

            return back()->withNotify([['success', 'Config updated successfully!']]);
        } catch (\Exception $e) {
            Log::error('NewPivotOrderConfig update: ' . $e->getMessage());
            return back()->withNotify([['error', 'Error updating config.']]);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $config->status = !$config->status;
            $config->save();
            $label = $config->status ? 'activated' : 'deactivated';
            return back()->withNotify([['success', "Config {$label} successfully!"]]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error updating status.']]);
        }
    }

    public function destroy($id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $pending = $config->orders()->where('is_order_placed', false)->where('status', true)->count();
            if ($pending > 0) {
                return back()->withNotify([['error', "Cannot delete — {$pending} pending order(s)."]]);
            }

            $config->delete();
            return back()->withNotify([['success', 'Config deleted successfully!']]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error deleting config.']]);
        }
    }
}