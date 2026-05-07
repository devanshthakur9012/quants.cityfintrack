<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\OiivBtstConfig;
use App\Models\OiivOrderBook;
use App\Models\OiivPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OiivBtstConfigController extends Controller
{
    // ── Page ──────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'OIIV BTST Exit Config';

        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        $configs = OiivBtstConfig::where('user_id', auth()->id())
            ->with('broker')
            ->get();

        // Today's BTST orders from the existing oiiv_order_book table
        $todayOrders = OiivOrderBook::where('user_id', auth()->id())
            ->whereIn('signal_type', ['BTST_SL', 'BTST_PROFIT', 'BTST_SWEEP', 'BTST_COSTCOST'])
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->get();

        // Open positions summary
        $openPositions = OiivPosition::where('user_id', auth()->id())
            ->where('status', 'open')
            ->count();

        return view(
            $this->activeTemplate . 'user.oiiv-auto.btst-config',
            compact('pageTitle', 'brokers', 'configs', 'todayOrders', 'openPositions')
        );
    }

    // ── Store ─────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'           => 'required|exists:broker_apis,id',
            'symbol_type'             => 'required|in:CE,PE,BOTH',
            'sl_percent'              => 'required|numeric|min:1|max:100',
            'profit_percent'          => 'required|numeric|min:1|max:500',
            'min_profit_percent'      => 'required|numeric|min:0|max:500',
            'enable_10am_sweep'       => 'sometimes|boolean',
            'sweep_time'              => 'nullable|date_format:H:i',
            'old_position_sl_percent' => 'required|numeric|min:1|max:100',
            'old_position_action'     => 'required|in:cost_to_cost,close_profit',
        ]);

        try {
            BrokerApi::where('id', $request->broker_api_id)->where('user_id', auth()->id())->firstOrFail();

            $exists = OiivBtstConfig::where('broker_api_id', $request->broker_api_id)
                ->where('symbol_type', $request->symbol_type)
                ->where('user_id', auth()->id())
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Config already exists for this broker + {$request->symbol_type}. Edit it.",
                ], 422);
            }

            $config = OiivBtstConfig::create([
                'user_id'                 => auth()->id(),
                'broker_api_id'           => $request->broker_api_id,
                'symbol_type'             => $request->symbol_type,
                'sl_percent'              => $request->sl_percent,
                'profit_percent'          => $request->profit_percent,
                'min_profit_percent'      => $request->min_profit_percent,
                'enable_10am_sweep'       => $request->boolean('enable_10am_sweep', true),
                'sweep_time'              => ($request->sweep_time ?? '10:00') . ':00',
                'old_position_sl_percent' => $request->old_position_sl_percent,
                'old_position_action'     => $request->old_position_action,
                'is_active'               => true,
            ]);

            return response()->json(['success' => true, 'message' => 'Config saved!', 'data' => $config->load('broker')]);

        } catch (\Exception $e) {
            Log::error('OiivBtstConfig store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $request->validate([
            'sl_percent'              => 'required|numeric|min:1|max:100',
            'profit_percent'          => 'required|numeric|min:1|max:500',
            'min_profit_percent'      => 'required|numeric|min:0|max:500',
            'enable_10am_sweep'       => 'sometimes|boolean',
            'sweep_time'              => 'nullable|date_format:H:i',
            'old_position_sl_percent' => 'required|numeric|min:1|max:100',
            'old_position_action'     => 'required|in:cost_to_cost,close_profit',
        ]);

        try {
            $config = OiivBtstConfig::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
            $config->update([
                'sl_percent'              => $request->sl_percent,
                'profit_percent'          => $request->profit_percent,
                'min_profit_percent'      => $request->min_profit_percent,
                'enable_10am_sweep'       => $request->boolean('enable_10am_sweep', true),
                'sweep_time'              => ($request->sweep_time ?? '10:00') . ':00',
                'old_position_sl_percent' => $request->old_position_sl_percent,
                'old_position_action'     => $request->old_position_action,
            ]);

            return response()->json(['success' => true, 'message' => 'Config updated!', 'data' => $config->load('broker')]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Destroy / Toggle ──────────────────────────────────────────────────

    public function destroy($id)
    {
        try {
            OiivBtstConfig::where('id', $id)->where('user_id', auth()->id())->firstOrFail()->delete();
            return response()->json(['success' => true, 'message' => 'Deleted!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $c = OiivBtstConfig::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
            $c->is_active = !$c->is_active;
            $c->save();
            return response()->json(['success' => true, 'message' => $c->is_active ? 'Activated!' : 'Paused!', 'data' => $c]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Manual run ────────────────────────────────────────────────────────

    public function runManual(Request $request)
    {
        $request->validate([
            'phase'     => 'required|in:9am,10am',
            'broker_id' => 'nullable|exists:broker_apis,id',
            'dry_run'   => 'nullable|boolean',
        ]);

        try {
            $args = ['--phase' => $request->phase, '--force' => true];
            if ($request->broker_id)          $args['--broker_id'] = $request->broker_id;
            if ($request->boolean('dry_run')) $args['--dry-run']   = true;

            \Artisan::call('oiiv:btst-exit', $args);
            $output = \Artisan::output();

            return response()->json([
                'success' => true,
                'message' => "Phase {$request->phase} executed!",
                'output'  => $output,
            ]);

        } catch (\Exception $e) {
            Log::error('OiivBtst runManual: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Today's BTST orders (from oiiv_order_book) ────────────────────────

    public function todayOrders(Request $request)
    {
        $orders = OiivOrderBook::where('user_id', auth()->id())
            ->whereIn('signal_type', ['BTST_SL', 'BTST_PROFIT', 'BTST_SWEEP', 'BTST_COSTCOST'])
            ->when($request->broker_id, fn($q) => $q->where('broker_api_id', $request->broker_id))
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($o) => [
                'id'             => $o->id,
                'signal_type'    => $o->signal_type,
                'symbol'         => $o->trading_symbol,
                'order_type'     => $o->order_type,
                'quantity_units' => $o->quantity_units,
                'placed_price'   => $o->placed_price,
                'trigger_price'  => $o->trigger_price,    // LTP at signal time
                'avg_entry'      => $o->spot_price_at_signal,
                'avg_fill'       => $o->average_price,
                'status'         => $o->status,
                'zerodha_id'     => $o->zerodha_order_id,
                'was_modified'   => (int)($o->modify_count ?? 0) > 0,
                'modify_count'   => $o->modify_count ?? 0,
                'placed_at'      => $o->placed_at ? \Carbon\Carbon::parse($o->placed_at)->format('H:i:s') : null,
            ]);

        return response()->json(['success' => true, 'data' => $orders]);
    }
}