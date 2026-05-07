<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NiftyDrivenBreakoutConfig;
use App\Models\NiftyDrivenBreakoutOrder;
use App\Models\BrokerApi;
use App\Models\OptionOhlcData;
use App\Helpers\NiftyDrivenBreakoutHelper;
use Illuminate\Support\Facades\Log;
use Auth;

class NiftyDrivenBreakoutConfigController extends Controller
{
    // =========================================================
    //  CONFIG PAGE
    // =========================================================

    public function config()
    {
        $pageTitle = 'NIFTY Breakout — Auto Trade Configs';

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = NiftyDrivenBreakoutConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        $availableSymbols = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->where('oi', '>', 0)
            ->distinct()->orderBy('base_symbol')
            ->pluck('base_symbol')->values();

        return view(
            $this->activeTemplate . 'user.nifty-driven-breakout.config',
            compact('pageTitle', 'brokers', 'configs', 'availableSymbols')
        );
    }

    // =========================================================
    //  SHARED VALIDATION RULES
    // =========================================================

    private function validationRules(): array
    {
        return [
            'broker_api_id'       => 'required|exists:broker_apis,id',
            'threshold'           => 'required|numeric|min:1|max:500',
            'filter'              => 'required|in:CE,PE,BOTH',
            'signal_mode'         => 'required|in:align,opposite',
            'order_type'          => 'required|in:LIMIT,MARKET',
            'product'             => 'required|in:NRML,MIS',
            'disc_ltp'            => 'required|numeric|min:0|max:100',
            'quantity_mode'       => 'required|in:lots,investment',
            'index_ce_quantity'   => 'nullable|integer|min:0',
            'index_pe_quantity'   => 'nullable|integer|min:0',
            'stock_ce_quantity'   => 'nullable|integer|min:0',
            'stock_pe_quantity'   => 'nullable|integer|min:0',
            'index_ce_investment' => 'nullable|numeric|min:0',
            'index_pe_investment' => 'nullable|numeric|min:0',
            'stock_ce_investment' => 'nullable|numeric|min:0',
            'stock_pe_investment' => 'nullable|numeric|min:0',
            // stop-loss
            'enable_stoploss'     => 'nullable|boolean',
            'stoploss_type'       => 'nullable|in:pct,points',
            'stoploss_value'      => 'nullable|numeric|min:0.01',
            'stoploss_order_type' => 'nullable|in:SL,SL-M',
            // profit target
            'enable_target'       => 'nullable|boolean',
            'target_type'         => 'nullable|in:pct,points',
            'target_value'        => 'nullable|numeric|min:0.01',
            'target_order_type'   => 'nullable|in:LIMIT,SL,SL-M',
            // misc
            'status'              => 'required|in:1,0',
        ];
    }

    private function buildPayload(Request $request): array
    {
        $allowedSymbols = null;
        if ($request->filled('allowed_symbols')) {
            $raw = is_array($request->allowed_symbols)
                ? implode(',', $request->allowed_symbols)
                : $request->allowed_symbols;
            $allowedSymbols = strtoupper(trim($raw, ', '));
        }

        return [
            'broker_api_id'       => $request->broker_api_id,
            'threshold'           => $request->threshold,
            'filter'              => $request->filter,
            'signal_mode'         => $request->signal_mode,
            'order_type'          => $request->order_type,
            'product'             => $request->product,
            'disc_ltp'            => $request->disc_ltp,
            'quantity_mode'       => $request->quantity_mode,
            'index_ce_quantity'   => $request->index_ce_quantity   ?? 0,
            'index_pe_quantity'   => $request->index_pe_quantity   ?? 0,
            'stock_ce_quantity'   => $request->stock_ce_quantity   ?? 0,
            'stock_pe_quantity'   => $request->stock_pe_quantity   ?? 0,
            'index_ce_investment' => $request->index_ce_investment ?? 0,
            'index_pe_investment' => $request->index_pe_investment ?? 0,
            'stock_ce_investment' => $request->stock_ce_investment ?? 0,
            'stock_pe_investment' => $request->stock_pe_investment ?? 0,
            // stop-loss (downside)
            'enable_stoploss'     => $request->boolean('enable_stoploss'),
            'stoploss_type'       => $request->stoploss_type       ?? 'pct',
            'stoploss_value'      => $request->stoploss_value      ?? 30,
            'stoploss_order_type' => $request->stoploss_order_type ?? 'SL-M',
            // profit target (upside)
            'enable_target'       => $request->boolean('enable_target'),
            'target_type'         => $request->target_type         ?? 'pct',
            'target_value'        => $request->target_value        ?? 50,
            'target_order_type'   => $request->target_order_type   ?? 'LIMIT',
            // misc
            'allowed_symbols'     => $allowedSymbols,
            'status'              => (bool) $request->status,
        ];
    }

    // =========================================================
    //  CREATE
    // =========================================================

    public function store(Request $request)
    {
        $request->validate($this->validationRules());

        try {
            NiftyDrivenBreakoutConfig::create(
                array_merge(['user_id' => Auth::id()], $this->buildPayload($request))
            );
            $notify[] = ['success', 'Configuration created successfully!'];
        } catch (\Exception $e) {
            Log::error('NiftyDrivenBreakoutConfig store: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration: ' . $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    // =========================================================
    //  UPDATE
    // =========================================================

    public function update(Request $request, $id)
    {
        $request->validate($this->validationRules());

        $config = NiftyDrivenBreakoutConfig::where('id', $id)
            ->where('user_id', Auth::id())->firstOrFail();

        $config->update($this->buildPayload($request));

        $notify[] = ['success', 'Configuration updated successfully!'];
        return back()->withNotify($notify);
    }

    // =========================================================
    //  TOGGLE STATUS
    // =========================================================

    public function toggleStatus($id)
    {
        try {
            $config = NiftyDrivenBreakoutConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail();
            $config->status = !$config->status;
            $config->save();
            $notify[] = ['success', 'Config ' . ($config->status ? 'activated' : 'deactivated') . '!'];
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating status.'];
        }
        return back()->withNotify($notify);
    }

    // =========================================================
    //  DELETE
    // =========================================================

    public function destroy($id)
    {
        try {
            $config = NiftyDrivenBreakoutConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail();

            $pending = $config->orders()->where('is_order_placed', false)->where('status', true)->count();
            if ($pending > 0) {
                $notify[] = ['error', "Cannot delete — {$pending} pending order(s) exist."];
                return back()->withNotify($notify);
            }

            $config->delete();
            $notify[] = ['success', 'Configuration deleted!'];
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error deleting configuration.'];
        }
        return back()->withNotify($notify);
    }

    // =========================================================
    //  VIEW ORDERS
    // =========================================================

    public function orders($configId)
    {
        $pageTitle = 'NIFTY Breakout — Orders';

        $config = NiftyDrivenBreakoutConfig::where('id', $configId)
            ->where('user_id', Auth::id())->firstOrFail();

        $orders = NiftyDrivenBreakoutOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view(
            $this->activeTemplate . 'user.nifty-driven-breakout.orders',
            compact('pageTitle', 'config', 'orders')
        );
    }

    // =========================================================
    //  MANUAL RUN
    // =========================================================

    public function runNow(Request $request)
    {
        try {
            $testDate = $request->get('test_date');
            $helper   = new NiftyDrivenBreakoutHelper();
            $helper->processSignals($testDate ?: null);
            $helper->placeOrders($testDate ?: null);
            $notify[] = ['success', 'NIFTY breakout signals processed and orders placed!'];
        } catch (\Exception $e) {
            Log::error('NiftyDrivenBreakout manual run: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
        }
        return back()->withNotify($notify);
    }
}