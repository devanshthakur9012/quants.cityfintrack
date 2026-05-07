<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PyramidOrder;
use App\Models\BrokerApi;
use App\Models\AngelApiInstrument;
use App\Helpers\PyramidOrderHelper;
use Auth;
use DB;
use Carbon\Carbon;

class PyramidOrderControllerCopy extends Controller
{
    /**
     * Display pyramid orders list
     */
    public function index()
    {
        $pageTitle = 'Pyramid Orders';
        
        $pyramidOrders = PyramidOrder::where('user_id', Auth::id())
            ->with(['broker:id,client_name', 'details'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Angel')
            ->get();

        return view($this->activeTemplate . 'user.pyramid-order.index', compact('pageTitle', 'pyramidOrders', 'brokers'));
    }

    /**
     * Show create pyramid order form
     */
    public function create()
    {
        $pageTitle = 'Create Pyramid Order';
        
        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Angel')
            ->get();

        // Get unique symbols from Angel instruments
        $symbols = AngelApiInstrument::select('name')
            ->where('exch_seg', 'NFO')
            ->whereIn('instrumenttype', ['OPTIDX', 'OPTSTK'])
            ->distinct()
            ->orderBy('name')
            ->pluck('name');

        return view($this->activeTemplate . 'user.pyramid-order.create', compact('pageTitle', 'brokers', 'symbols'));
    }

    /**
     * Get expiry dates for a symbol (AJAX)
     */
    public function getExpiries(Request $request)
    {
        $symbol = $request->symbol;
        
        if (!$symbol) {
            return response()->json(['success' => false, 'message' => 'Symbol is required']);
        }

        $expiries = AngelApiInstrument::where('name', $symbol)
            ->where('exch_seg', 'NFO')
            ->whereIn('instrumenttype', ['OPTIDX', 'OPTSTK'])
            ->whereDate('expiry', '>=', Carbon::today())
            ->select('expiry')
            ->distinct()
            ->orderBy('expiry')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => Carbon::parse($item->expiry)->format('Y-m-d'),
                    'label' => Carbon::parse($item->expiry)->format('d-M-Y')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $expiries
        ]);
    }

    /**
     * Get strike prices for symbol and expiry (AJAX)
     */
    public function getStrikes(Request $request)
    {
        $symbol = $request->symbol;
        $expiry = $request->expiry;
        
        if (!$symbol || !$expiry) {
            return response()->json(['success' => false, 'message' => 'Symbol and expiry are required']);
        }

        $strikes = AngelApiInstrument::where('name', $symbol)
            ->where('exch_seg', 'NFO')
            ->whereDate('expiry', $expiry)
            ->whereIn('instrumenttype', ['OPTIDX', 'OPTSTK'])
            ->select('strike')
            ->distinct()
            ->orderBy('strike')
            ->get()
            ->map(function ($item) {
                $strike = $item->strike / 100; // Convert from paise
                return [
                    'value' => $strike,
                    'label' => number_format($strike, 2)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $strikes
        ]);
    }

    /**
     * Get lot size for symbol (AJAX)
     */
    public function getLotSize(Request $request)
    {
        $symbol = $request->symbol;
        
        if (!$symbol) {
            return response()->json(['success' => false, 'message' => 'Symbol is required']);
        }

        $instrument = AngelApiInstrument::where('name', $symbol)
            ->where('exch_seg', 'NFO')
            ->whereIn('instrumenttype', ['OPTIDX', 'OPTSTK'])
            ->first();

        if (!$instrument) {
            return response()->json(['success' => false, 'message' => 'Symbol not found']);
        }

        return response()->json([
            'success' => true,
            'lot_size' => $instrument->lotsize
        ]);
    }

    /**
     * Store new pyramid order
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'symbol' => 'required|string',
            'expiry_date' => 'required|date|after_or_equal:today',
            'strike_price' => 'required|numeric|min:0',
            'option_type' => 'required|in:CE,PE',
            'transaction_type' => 'required|in:BUY,SELL',
            'manual_ltp' => 'required|numeric|min:0.01',
            'base_discount_pct' => 'required|numeric|min:0|max:50',
            'discount_increment_pct' => 'required|numeric|min:0|max:100',
            'lots_per_order' => 'required|integer|min:1',
            'num_pyramids' => 'required|integer|min:1|max:10',
            'lot_size' => 'required|integer|min:1',
        ]);

        // Verify broker belongs to user
        $broker = BrokerApi::where('id', $request->broker_api_id)
            ->where('user_id', Auth::id())
            ->where('client_type', 'Angel')
            ->first();

        if (!$broker) {
            $notify[] = ['error', 'Invalid broker selected'];
            return back()->withNotify($notify)->withInput();
        }

        try {
            DB::beginTransaction();

            // Create pyramid order
            $pyramidOrder = PyramidOrder::create([
                'user_id' => Auth::id(),
                'broker_api_id' => $request->broker_api_id,
                'symbol' => strtoupper($request->symbol),
                'expiry_date' => $request->expiry_date,
                'strike_price' => $request->strike_price,
                'option_type' => $request->option_type,
                'transaction_type' => $request->transaction_type,
                'manual_ltp' => $request->manual_ltp,
                'base_discount_pct' => $request->base_discount_pct,
                'discount_increment_pct' => $request->discount_increment_pct,
                'lots_per_order' => $request->lots_per_order,
                'num_pyramids' => $request->num_pyramids,
                'lot_size' => $request->lot_size,
                'status' => 'pending',
            ]);

            DB::commit();

            // Execute order placement
            $helper = new PyramidOrderHelper($broker);
            $result = $helper->executePyramidOrder($pyramidOrder);

            if ($result['success']) {
                $notify[] = ['success', $result['message']];
            } else {
                $notify[] = ['error', $result['message']];
            }

            return redirect()->route('user.pyramid-orders.show', $pyramidOrder->id)->withNotify($notify);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Pyramid order creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            $notify[] = ['error', 'Failed to create order: ' . $e->getMessage()];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * Show pyramid order details
     */
    public function show($id)
    {
        $pageTitle = 'Pyramid Order Details';
        
        $pyramidOrder = PyramidOrder::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['broker', 'details' => function($query) {
                $query->orderBy('pyramid_index');
            }])
            ->firstOrFail();

        return view($this->activeTemplate . 'user.pyramid-order.show', compact('pageTitle', 'pyramidOrder'));
    }

    /**
     * Preview pyramid calculations (AJAX)
     */
    public function preview(Request $request)
    {
        $request->validate([
            'manual_ltp' => 'required|numeric|min:0.01',
            'base_discount_pct' => 'required|numeric|min:0',
            'discount_increment_pct' => 'required|numeric|min:0',
            'lots_per_order' => 'required|integer|min:1',
            'num_pyramids' => 'required|integer|min:1|max:10',
            'lot_size' => 'required|integer|min:1',
            'transaction_type' => 'required|in:BUY,SELL',
        ]);

        $tickSize = 0.05; // Default tick size
        $previews = [];

        for ($i = 1; $i <= $request->num_pyramids; $i++) {
            $effectiveDiscount = $request->base_discount_pct * (1 + (($i - 1) * $request->discount_increment_pct / 100));
            
            if ($request->transaction_type === 'BUY') {
                $price = $request->manual_ltp * (1 - $effectiveDiscount / 100);
            } else {
                $price = $request->manual_ltp * (1 + $effectiveDiscount / 100);
            }
            
            $price = round($price / $tickSize) * $tickSize;
            $quantity = $request->lots_per_order * $request->lot_size;

            $previews[] = [
                'pyramid' => $i,
                'discount' => round($effectiveDiscount, 2) . '%',
                'price' => number_format($price, 2),
                'quantity' => $quantity,
                'value' => number_format($price * $quantity, 2)
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $previews
        ]);
    }
}