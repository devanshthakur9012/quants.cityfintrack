<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FutContrarianConfig;
use App\Models\FutContrarianOrder;
use App\Models\FutContrarianOrderBook;
use App\Models\BrokerApi;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FutContrarianConfigController extends Controller
{
    // =========================================================
    //  CONFIG PAGE
    // =========================================================

    public function config()
    {
        $pageTitle = 'FUT Contrarian OI — Auto Trading Config';

        $brokers = BrokerApi::select('client_name', 'id', 'client_type')
            ->where('user_id', Auth::id())
            ->whereIn('client_type', ['Zerodha', 'Angel', 'AngelOne'])
            ->get();

        $configs = FutContrarianConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name,client_type')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.fut-contrarian.config', compact(
            'pageTitle', 'brokers', 'configs'
        ));
    }

    // =========================================================
    //  VIEW ORDERS FOR A CONFIG
    // =========================================================

    public function viewOrders($configId)
    {
        $pageTitle = 'FUT Contrarian — Orders';

        $config = FutContrarianConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = FutContrarianOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->orderByDesc('signal_date')
            ->paginate(50);

        return view($this->activeTemplate . 'user.fut-contrarian.orders', compact(
            'pageTitle', 'config', 'orders'
        ));
    }

    // =========================================================
    //  VIEW ORDER BOOK FOR ONE SIGNAL ORDER
    // =========================================================

    public function viewOrderBook($orderId)
    {
        $pageTitle = 'FUT Contrarian — Order Book';

        $order = FutContrarianOrder::where('user_id', Auth::id())
            ->where('id', $orderId)
            ->firstOrFail();

        $books = FutContrarianOrderBook::where('fc_order_id', $orderId)
            ->orderBy('signal_window')
            ->orderBy('lot_chunk_number')
            ->get();

        return view($this->activeTemplate . 'user.fut-contrarian.order-book', compact(
            'pageTitle', 'order', 'books'
        ));
    }

    // =========================================================
    //  SYMBOLS  (same FUT list)
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()->orderBy('base_symbol')->pluck('base_symbol')->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  STORE
    // =========================================================

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'      => 'required|exists:broker_apis,id',
            'trade_30min'        => 'nullable|boolean',
            'trade_1hr'          => 'nullable|boolean',
            'order_type'         => 'required|in:LIMIT,MARKET',
            'product'            => 'required|in:NRML,MIS',
            'disc_ltp'           => 'required|numeric|min:0|max:100',
            'index_ce_quantity'  => 'required|integer|min:0',
            'index_pe_quantity'  => 'required|integer|min:0',
            'stock_ce_quantity'  => 'required|integer|min:0',
            'stock_pe_quantity'  => 'required|integer|min:0',
            'status'             => 'required|in:1,0',
            'allowed_symbols'    => 'nullable|array',
            'allowed_symbols.*'  => 'string|max:32',
        ]);

        // At least one window must be enabled
        $trade30 = (bool) $request->trade_30min;
        $trade1h = (bool) $request->trade_1hr;
        if (!$trade30 && !$trade1h) {
            $notify[] = ['error', 'Please enable at least one trading window (30-min or 1-HR).'];
            return back()->withNotify($notify);
        }

        // Window mode is determined by trade_30min / trade_1hr toggles:
        //   30min only  → FUT + 30min OI must match → buy at 10:00
        //   1hr only    → FUT + 1hr OI must match   → buy at 10:30
        //   Both        → all three must match       → buy at 10:00 AND 10:30

        try {
            $allowedSymbols = $this->normalizeSymbols($request);

            FutContrarianConfig::create([
                'user_id'           => Auth::id(),
                'broker_api_id'     => $request->broker_api_id,
                'trade_30min'       => $trade30,
                'trade_1hr'         => $trade1h,
                'order_type'        => $request->order_type,
                'product'           => $request->product,
                'disc_ltp'          => $request->disc_ltp,
                'index_ce_quantity' => $request->index_ce_quantity,
                'index_pe_quantity' => $request->index_pe_quantity,
                'stock_ce_quantity' => $request->stock_ce_quantity,
                'stock_pe_quantity' => $request->stock_pe_quantity,
                'status'            => (bool) $request->status,
                'allowed_symbols'   => $allowedSymbols,
            ]);

            $notify[] = ['success', 'FUT Contrarian config created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('FutContrarianConfig store: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    // =========================================================
    //  UPDATE
    // =========================================================

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id'      => 'required|exists:broker_apis,id',
            'trade_30min'        => 'nullable|boolean',
            'trade_1hr'          => 'nullable|boolean',
            'order_type'         => 'required|in:LIMIT,MARKET',
            'product'            => 'required|in:NRML,MIS',
            'disc_ltp'           => 'required|numeric|min:0|max:100',
            'index_ce_quantity'  => 'required|integer|min:0',
            'index_pe_quantity'  => 'required|integer|min:0',
            'stock_ce_quantity'  => 'required|integer|min:0',
            'stock_pe_quantity'  => 'required|integer|min:0',
            'status'             => 'required|in:1,0',
            'allowed_symbols'    => 'nullable|array',
            'allowed_symbols.*'  => 'string|max:32',
        ]);

        $config = FutContrarianConfig::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $trade30 = (bool) $request->trade_30min;
        $trade1h = (bool) $request->trade_1hr;
        if (!$trade30 && !$trade1h) {
            $notify[] = ['error', 'Please enable at least one trading window (30-min or 1-HR).'];
            return back()->withNotify($notify);
        }

        try {
            $allowedSymbols = $this->normalizeSymbols($request);

            $config->update([
                'broker_api_id'     => $request->broker_api_id,
                'trade_30min'       => $trade30,
                'trade_1hr'         => $trade1h,
                'order_type'        => $request->order_type,
                'product'           => $request->product,
                'disc_ltp'          => $request->disc_ltp,
                'index_ce_quantity' => $request->index_ce_quantity,
                'index_pe_quantity' => $request->index_pe_quantity,
                'stock_ce_quantity' => $request->stock_ce_quantity,
                'stock_pe_quantity' => $request->stock_pe_quantity,
                'status'            => (bool) $request->status,
                'allowed_symbols'   => $allowedSymbols,
            ]);

            $notify[] = ['success', 'Configuration updated successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('FutContrarianConfig update: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    // =========================================================
    //  TOGGLE STATUS
    // =========================================================

    public function toggleStatus($id)
    {
        try {
            $config = FutContrarianConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->status = !$config->status;
            $config->save();

            $label   = $config->status ? 'activated' : 'deactivated';
            $notify[] = ['success', "Configuration {$label} successfully!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating configuration.'];
            return back()->withNotify($notify);
        }
    }

    // =========================================================
    //  DESTROY
    // =========================================================

    public function destroy($id)
    {
        try {
            $config = FutContrarianConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $pendingOrders = FutContrarianOrder::where('config_id', $id)
                ->where('is_order_placed', false)
                ->where('status', true)
                ->count();

            if ($pendingOrders > 0) {
                $notify[] = ['error', "Cannot delete — {$pendingOrders} pending orders."];
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

    // =========================================================
    //  HELPER
    // =========================================================

    private function normalizeSymbols(Request $request): ?array
    {
        if (!$request->has('allowed_symbols')
            || !is_array($request->allowed_symbols)
            || count($request->allowed_symbols) === 0) {
            return null; // null = trade ALL
        }

        return array_values(array_map('strtoupper', array_filter($request->allowed_symbols)));
    }
}