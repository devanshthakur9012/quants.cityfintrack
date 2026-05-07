<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\BrokerApi;
use App\Models\OptionStrike;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

class OIIVAutoControllerBackup extends Controller
{
    /**
     * Display analysis page - Raw OI+IV data viewer
     */
    public function index()
    {
        $pageTitle = 'OI + IV Signal Analysis';
        
        return view($this->activeTemplate . 'user.oiiv-auto.index', compact('pageTitle'));
    }

    /**
     * Analyze OI+IV signals - Get raw data grouped by symbol and date
     */
    public function analyzeSignals(Request $request)
    {
        try {
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data' => []
                ]);
            }

            Log::info('=== OI+IV ANALYSIS START ===', [
                'from' => $fromDate,
                'to' => $toDate,
                'symbols' => $selectedSymbols
            ]);

            // Get all FUT records first (base data)
            $query = OptionStrike::where('strike_position', 'FUT')
                ->whereBetween('trading_date', [$fromDate, $toDate]);

            // Filter by symbols if selected
            if (!empty($selectedSymbols)) {
                $query->whereIn('underlying_symbol', $selectedSymbols);
            }

            $futRecords = $query->orderBy('trading_date', 'desc')
                ->orderBy('underlying_symbol', 'asc')
                ->get();

            Log::info("Found {$futRecords->count()} symbols");

            $results = [];
            foreach ($futRecords as $futRecord) {
                $results[] = $this->formatAnalysisData($futRecord);
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'total_records' => count($results),
                'message' => count($results) . ' records found'
            ]);

        } catch (\Exception $e) {
            Log::error('OI+IV Analysis Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Format analysis data - Combine FUT, CE, PE data for one symbol
     * ✅ UPDATED: Fetch live price from Zerodha API
     */
    private function formatAnalysisData($futRecord)
    {
        // Get CE and PE details
        $ceData = OptionStrike::where('underlying_symbol', $futRecord->underlying_symbol)
            ->where('strike_position', 'CE_MERGED')
            ->where('trading_date', $futRecord->trading_date)
            ->first();

        $peData = OptionStrike::where('underlying_symbol', $futRecord->underlying_symbol)
            ->where('strike_position', 'PE_MERGED')
            ->where('trading_date', $futRecord->trading_date)
            ->first();

        // ✅ Get opening price (stored in spot_price from morning data)
        $openPrice = $futRecord->spot_price;
        
        // ✅ Get LIVE current price from Zerodha API
        $currentPrice = $this->getLivePrice($futRecord);
        
        // ✅ Calculate price movement
        $priceChange = $currentPrice - $openPrice;
        $priceChangePercent = $openPrice > 0 ? (($priceChange / $openPrice) * 100) : 0;
        
        // ✅ Get OI change %
        $oiChange = $futRecord->daily_oi_change_pct ?? 0;
        
        // Determine directions
        $priceDirection = $priceChange > 0 ? 'UP' : ($priceChange < 0 ? 'DOWN' : 'FLAT');
        $oiDirection = $oiChange > 0 ? 'POSITIVE' : ($oiChange < 0 ? 'NEGATIVE' : 'FLAT');
        
        // Determine signal based on Price + OI logic
        $signal = $this->determineSignalForDisplay($priceDirection, $oiDirection);

        return [
            // Basic Info
            'date' => Carbon::parse($futRecord->trading_date)->format('Y-m-d'),
            'symbol' => $futRecord->underlying_symbol,
            'fut_symbol' => $futRecord->trading_symbol,
            'spot_price' => round($currentPrice, 2), // Current price for existing UI
            
            // ✅ NEW: Price Movement Data
            'open_price' => round($openPrice, 2),
            'current_price' => round($currentPrice, 2),
            'price_change' => round($priceChange, 2),
            'price_change_percent' => round($priceChangePercent, 2),
            'price_direction' => $priceDirection,
            
            // ✅ NEW: OI Data for new logic
            'oi_change_pct' => round($oiChange, 2),
            'oi_direction' => $oiDirection,
            
            // ✅ NEW: Signal Data
            'signal' => $signal['signal'] ?? 'NO_SIGNAL',
            'signal_type' => $signal['type'] ?? 'NONE',
            'signal_reason' => $signal['reason'] ?? 'No clear signal',
            'signal_scenario' => $signal['scenario'] ?? 'N/A',
            'order_picked' => $signal['signal'] !== 'NO_SIGNAL' ? 'YES' : 'NO',
            
            // ✅ KEEP OLD: For existing UI compatibility
            'fut_oi_signal' => $futRecord->direction ?? 'N/A',
            'fut_oi_strength' => $futRecord->strength ?? 'N/A',
            'fut_oi_change_pct' => round($futRecord->daily_oi_change_pct ?? 0, 2),
            'fut_oi' => $futRecord->daily_oi ?? 0,
            'fut_oi_prev' => $futRecord->daily_oi_prev ?? 0,
            
            // CE OI Data
            'ce_oi_signal' => $ceData->direction ?? 'N/A',
            'ce_oi_strength' => $ceData->strength ?? 'N/A',
            'ce_oi_change_pct' => round($ceData->daily_oi_change_pct ?? 0, 2),
            'ce_oi' => $ceData->daily_oi ?? 0,
            'ce_oi_prev' => $ceData->daily_oi_prev ?? 0,
            
            // CE IV Data
            'ce_iv_signal' => $ceData->iv_direction ?? 'N/A',
            'ce_iv_strength' => $ceData->iv_strength ?? 'N/A',
            'ce_iv_change_pct' => round($ceData->daily_iv_change_pct ?? 0, 2),
            'ce_iv' => round($ceData->daily_iv ?? 0, 4),
            'ce_iv_prev' => round($ceData->daily_iv_prev ?? 0, 4),
            
            // PE OI Data
            'pe_oi_signal' => $peData->direction ?? 'N/A',
            'pe_oi_strength' => $peData->strength ?? 'N/A',
            'pe_oi_change_pct' => round($peData->daily_oi_change_pct ?? 0, 2),
            'pe_oi' => $peData->daily_oi ?? 0,
            'pe_oi_prev' => $peData->daily_oi_prev ?? 0,
            
            // PE IV Data
            'pe_iv_signal' => $peData->iv_direction ?? 'N/A',
            'pe_iv_strength' => $peData->iv_strength ?? 'N/A',
            'pe_iv_change_pct' => round($peData->daily_iv_change_pct ?? 0, 2),
            'pe_iv' => round($peData->daily_iv ?? 0, 4),
            'pe_iv_prev' => round($peData->daily_iv_prev ?? 0, 4),
        ];
    }

    /**
     * ✅ NEW: Get live price from Zerodha API
     */
    private function getLivePrice($futRecord)
    {
        try {
            // Get broker for this record
            $broker = BrokerApi::find($futRecord->broker_api_id);
            
            if (!$broker || !$broker->hasValidToken()) {
                Log::warning("No valid broker found for {$futRecord->trading_symbol}, using stored price");
                return $futRecord->spot_price;
            }

            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            $quoteKey = "NFO:" . $futRecord->trading_symbol;
            $quotes = $kite->getQuote([$quoteKey]);
            
            if (isset($quotes->$quoteKey->last_price)) {
                Log::info("✅ Live price for {$futRecord->trading_symbol}: {$quotes->$quoteKey->last_price}");
                return $quotes->$quoteKey->last_price;
            }
            
            $quotesArray = json_decode(json_encode($quotes), true);
            if (isset($quotesArray[$quoteKey]['last_price'])) {
                Log::info("✅ Live price for {$futRecord->trading_symbol}: {$quotesArray[$quoteKey]['last_price']}");
                return $quotesArray[$quoteKey]['last_price'];
            }
            
            Log::warning("Could not get live price for {$futRecord->trading_symbol}, using stored price");
            return $futRecord->spot_price;
            
        } catch (\Exception $e) {
            Log::error("Error fetching live price for {$futRecord->trading_symbol}: " . $e->getMessage());
            return $futRecord->spot_price;
        }
    }

    /**
     * ✅ NEW: Determine signal based on Price + OI logic
     */
    private function determineSignalForDisplay($priceDirection, $oiDirection)
    {
        // Price UP + OI NEGATIVE = Short Covering (Bullish) → BUY CE
        if ($priceDirection === 'UP' && $oiDirection === 'NEGATIVE') {
            return [
                'signal' => 'BUY_CE',
                'type' => 'CE',
                'reason' => 'Short Covering - Bullish',
                'scenario' => 'Price Up + OI Negative'
            ];
        }

        // Price UP + OI POSITIVE = Long Buildup (Bullish) → BUY CE
        if ($priceDirection === 'UP' && $oiDirection === 'POSITIVE') {
            return [
                'signal' => 'BUY_CE',
                'type' => 'CE',
                'reason' => 'Long Buildup - Bullish',
                'scenario' => 'Price Up + OI Positive'
            ];
        }

        // Price DOWN + OI NEGATIVE = Long Unwinding (Bearish) → BUY PE
        if ($priceDirection === 'DOWN' && $oiDirection === 'NEGATIVE') {
            return [
                'signal' => 'BUY_PE',
                'type' => 'PE',
                'reason' => 'Long Unwinding - Bearish',
                'scenario' => 'Price Down + OI Negative'
            ];
        }

        // Price DOWN + OI POSITIVE = Short Buildup (Bearish) → BUY PE
        if ($priceDirection === 'DOWN' && $oiDirection === 'POSITIVE') {
            return [
                'signal' => 'BUY_PE',
                'type' => 'PE',
                'reason' => 'Short Buildup - Bearish',
                'scenario' => 'Price Down + OI Positive'
            ];
        }

        return [
            'signal' => 'NO_SIGNAL',
            'type' => 'NONE',
            'reason' => 'No clear signal',
            'scenario' => 'N/A'
        ];
    }

    /**
     * Get unique symbols for filter
     */
    public function getSymbols()
    {
        $symbols = OptionStrike::where('strike_position', 'FUT')
            ->distinct()
            ->pluck('underlying_symbol')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'symbols' => $symbols
        ]);
    }

    /**
     * Config management page
     */
    public function config()
    {
        $pageTitle = 'OI + IV Auto Trading Configuration';
        
        $brokers = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();
            
        $configs = OIIVAutoConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.oiiv-auto.config', [
            'pageTitle' => $pageTitle,
            'brokers' => $brokers,
            'configs' => $configs
        ]);
    }

    /**
     * Store new config
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'order_type' => 'required|in:LIMIT,MARKET',
            'product' => 'required|in:NRML,MIS',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'index_quantity' => 'required|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
        ]);

        try {
            OIIVAutoConfig::create([
                'user_id' => Auth::id(),
                'broker_api_id' => $request->broker_api_id,
                'order_type' => $request->order_type,
                'product' => $request->product,
                'disc_ltp' => $request->disc_ltp,
                'index_quantity' => $request->index_quantity,
                'stock_quantity' => $request->stock_quantity,
                'status' => $request->status,
            ]);

            $notify[] = ['success', 'OI+IV auto trading configuration created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('OI+IV Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration'];
            return back()->withNotify($notify);
        }
    }

    /**
     * Update config
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'order_type' => 'required|in:LIMIT,MARKET',
            'product' => 'required|in:NRML,MIS',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'index_quantity' => 'required|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
        ]);

        $config = OIIVAutoConfig::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $config->update($request->only([
            'broker_api_id',
            'order_type',
            'product',
            'disc_ltp',
            'index_quantity',
            'stock_quantity',
            'status',
        ]));

        $notify[] = ['success', 'Configuration updated successfully!'];
        return back()->withNotify($notify);
    }

    /**
     * View orders
     */
    public function viewOrders($configId)
    {
        $pageTitle = 'OI+IV Auto Trading Orders';
        
        $config = OIIVAutoConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = OIIVAutoOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with(['broker:id,client_name'])
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.oiiv-auto.orders', [
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
            $config = OIIVAutoConfig::where('id', $id)
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
            $config = OIIVAutoConfig::where('id', $id)
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
}