<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class ManualOrderController extends Controller
{
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Manual Order Placement Page
     */
    public function index()
    {
        $pageTitle = 'Manual Order Placement (5-Min Signals)';
        
        // Get user's active configs
        $configs = ZerodhaAutoConfig::where('user_id', auth()->id())
            ->where('status', true)
            ->with('broker:id,client_name')
            ->get();
        
        return view($this->activeTemplate . 'user.manual-orders.index', compact('pageTitle', 'configs'));
    }

    /**
     * Fetch today's 5-minute signals (same logic as cron)
     */
    // public function manualOrdersFetch(Request $request)
    // {
    //     try {
    //         Log::info('=== MANUAL ORDER FETCH START ===');

    //         // Get all active configs for user
    //         $configs = ZerodhaAutoConfig::where('user_id', auth()->id())
    //             ->where('status', true)
    //             ->get();

    //         if ($configs->isEmpty()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No active configurations found. Please create a config first.',
    //                 'data' => []
    //             ]);
    //         }

    //         // Get all active 5-minute symbols for user's brokers
    //         $brokerIds = $configs->pluck('broker_api_id')->unique()->toArray();
            
    //         $symbols = SymbolMonitored::where('is_active', true)
    //             ->where('interval', '5minute')
    //             ->whereIn('broker_api_id', $brokerIds)
    //             ->get();

    //         if ($symbols->isEmpty()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No active 5-minute symbols found',
    //                 'data' => []
    //             ]);
    //         }

    //         $allSignals = [];
    //         $today = Carbon::yesterday();

    //         // Process each symbol
    //         foreach ($symbols as $symbol) {
    //             // Find matching config
    //             $config = $this->findConfigForSymbol($configs, $symbol);

    //             if (!$config) {
    //                 continue;
    //             }

    //             $broker = BrokerApi::find($symbol->broker_api_id);
                
    //             if (!$broker || !$broker->hasValidToken()) {
    //                 continue;
    //             }

    //             // Get today's candles
    //             $todayCandles = $this->getTodayCandles(
    //                 $broker->id, 
    //                 $symbol->trading_symbol, 
    //                 '5minute', 
    //                 null
    //             );

    //             if ($todayCandles->count() < 1) {
    //                 continue;
    //             }

    //             // Get previous day's last candle
    //             $previousDayLastCandle = $this->getPreviousDayLastCandle(
    //                 $broker->id, 
    //                 $symbol->trading_symbol, 
    //                 null
    //             );

    //             // Find all sync points
    //             $allSyncPoints = $this->findAllSynchronizationPointsForManual(
    //                 $todayCandles,
    //                 $symbol->trading_symbol,
    //                 $config->signal_strategy,
    //                 $config,
    //                 $previousDayLastCandle
    //             );

    //             if (empty($allSyncPoints)) {
    //                 continue;
    //             }

    //             // Get existing orders for today
    //             $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
    //                 ->where('trading_symbol', $symbol->trading_symbol)
    //                 ->where('status', true)
    //                 ->whereDate('created_at', $today)
    //                 ->orderBy('created_at', 'asc')
    //                 ->get();

    //             // Get ALL valid sync points
    //             $validSyncPoints = $this->getAllValidSyncPoints(
    //                 $allSyncPoints, 
    //                 $todayOrders, 
    //                 $symbol->trading_symbol
    //             );

    //             if (empty($validSyncPoints)) {
    //                 continue;
    //             }

    //             // Batch collect all option tokens for THIS symbol
    //             $optionTokens = [];
    //             $syncPointsWithOptions = [];

    //             foreach ($validSyncPoints as $syncPoint) {
    //                 $candle = $syncPoint['candle'];
    //                 $signal = $syncPoint['signal'];

    //                 // Get option details
    //                 $optionDetails = $this->getATMOption(
    //                     $broker, 
    //                     $symbol->trading_symbol, 
    //                     $signal['type'], 
    //                     $signal['price'], 
    //                     $config
    //                 );

    //                 if (!$optionDetails) {
    //                     continue;
    //                 }

    //                 $optionTokens[] = $optionDetails['token'];
    //                 $syncPointsWithOptions[] = [
    //                     'optionDetails' => $optionDetails,
    //                     'candle' => $candle,
    //                     'signal' => $signal,
    //                     'symbol' => $symbol,      // Store symbol reference
    //                     'config' => $config,      // Store config reference
    //                     'broker' => $broker       // Store broker reference
    //                 ];
    //             }

    //             // Skip if no valid options found for this symbol
    //             if (empty($optionTokens)) {
    //                 continue;
    //             }

    //             // Batch fetch ALL LTPs in ONE API call for THIS symbol's options
    //             $liveLTPs = $this->getBatchOptionLTPs($broker, $optionTokens);

    //             // Convert to manual order format
    //             foreach ($syncPointsWithOptions as $data) {
    //                 $candle = $data['candle'];
    //                 $signal = $data['signal'];
    //                 $optionDetails = $data['optionDetails'];
    //                 $symbolRef = $data['symbol'];
    //                 $configRef = $data['config'];
    //                 $brokerRef = $data['broker'];
                    
    //                 // Get LIVE LTP from batch result
    //                 $liveLTP = $liveLTPs[$optionDetails['token']] ?? 25.00;

    //                 // Check if order exists
    //                 $existingOrder = ZerodhaAutoOrder::where('trading_symbol', $symbolRef->trading_symbol)
    //                     ->where('signal_detected_at', $candle->timestamp)
    //                     ->where('user_id', auth()->id())
    //                     ->first();

    //                 $allSignals[] = [
    //                     'id' => $existingOrder ? $existingOrder->id : null,
    //                     'config_id' => $configRef->id,
    //                     'config_name' => "Config #{$configRef->id} ({$configRef->broker->client_name})",
    //                     'broker_id' => $brokerRef->id,
    //                     'broker_name' => $brokerRef->client_name,
    //                     'symbol_id' => $symbolRef->id,
    //                     'future_symbol' => $symbolRef->trading_symbol,
    //                     'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
    //                     'signal_type' => $signal['type'],
    //                     'strategy' => $signal['strategy'],
    //                     'supertrend_signal' => $signal['supertrend'],
    //                     'vwap_signal' => $signal['vwap'],
    //                     'future_price' => round($signal['price'], 2),
    //                     'option_symbol' => $optionDetails['symbol'],
    //                     'option_token' => $optionDetails['token'],
    //                     'option_type' => $optionDetails['type'],
    //                     'strike_price' => $optionDetails['strike'],
    //                     'signal_ltp' => round($optionDetails['ltp'], 2),
    //                     'current_ltp' => round($liveLTP, 2),
    //                     'quantity' => $configRef->getQuantityForSymbol($symbolRef->trading_symbol),
    //                     'has_order' => $existingOrder ? true : false,
    //                     'is_order_placed' => $existingOrder ? $existingOrder->is_order_placed : false,
    //                     'order_placed_at' => $existingOrder && $existingOrder->is_order_placed ? 
    //                         $existingOrder->order_placed_at->format('H:i:s') : null
    //                 ];
    //             }
    //         } // End of main symbol loop

    //         // Sort by signal time
    //         usort($allSignals, function($a, $b) {
    //             return strtotime($a['signal_time']) - strtotime($b['signal_time']);
    //         });

    //         Log::info('=== MANUAL ORDER FETCH COMPLETE ===', [
    //             'total_signals' => count($allSignals)
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'data' => $allSignals,
    //             'total_signals' => count($allSignals),
    //             'message' => count($allSignals) . ' signals found for today'
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Manual Orders Fetch Error', [
    //             'message' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => []
    //         ], 500);
    //     }
    // }

    /**
     * Fetch today's 5-minute signals (same logic as cron)
     */
    // public function manualOrdersFetch(Request $request)
    // {
    //     try {
    //         Log::info('=== MANUAL ORDER FETCH START ===');

    //         // Get all active configs for user
    //         $configs = ZerodhaAutoConfig::where('user_id', auth()->id())
    //             ->where('status', true)
    //             ->get();

    //         if ($configs->isEmpty()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No active configurations found. Please create a config first.',
    //                 'data' => []
    //             ]);
    //         }

    //         // Get all active 5-minute symbols for user's brokers
    //         $brokerIds = $configs->pluck('broker_api_id')->unique()->toArray();
            
    //         $symbols = SymbolMonitored::where('is_active', true)
    //             ->where('interval', '5minute')
    //             ->whereIn('broker_api_id', $brokerIds)
    //             ->get();

    //         if ($symbols->isEmpty()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No active 5-minute symbols found',
    //                 'data' => []
    //             ]);
    //         }

    //         $allSignals = [];
    //         $today = Carbon::yesterday();

    //         // ✅ STEP 1: Collect ALL signals WITHOUT live LTP first (fast response)
    //         foreach ($symbols as $symbol) {
    //             $config = $this->findConfigForSymbol($configs, $symbol);
    //             if (!$config) continue;

    //             $broker = BrokerApi::find($symbol->broker_api_id);
    //             if (!$broker || !$broker->hasValidToken()) continue;

    //             $todayCandles = $this->getTodayCandles(
    //                 $broker->id, 
    //                 $symbol->trading_symbol, 
    //                 '5minute', 
    //                 null
    //             );

    //             if ($todayCandles->count() < 1) continue;

    //             $previousDayLastCandle = $this->getPreviousDayLastCandle(
    //                 $broker->id, 
    //                 $symbol->trading_symbol, 
    //                 null
    //             );

    //             $allSyncPoints = $this->findAllSynchronizationPointsForManual(
    //                 $todayCandles,
    //                 $symbol->trading_symbol,
    //                 $config->signal_strategy,
    //                 $config,
    //                 $previousDayLastCandle
    //             );

    //             if (empty($allSyncPoints)) continue;

    //             $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
    //                 ->where('trading_symbol', $symbol->trading_symbol)
    //                 ->where('status', true)
    //                 ->whereDate('created_at', $today)
    //                 ->orderBy('created_at', 'asc')
    //                 ->get();

    //             $validSyncPoints = $this->getAllValidSyncPoints(
    //                 $allSyncPoints, 
    //                 $todayOrders, 
    //                 $symbol->trading_symbol
    //             );

    //             if (empty($validSyncPoints)) continue;

    //             // Process each sync point
    //             foreach ($validSyncPoints as $syncPoint) {
    //                 $candle = $syncPoint['candle'];
    //                 $signal = $syncPoint['signal'];

    //                 $optionDetails = $this->getATMOption(
    //                     $broker, 
    //                     $symbol->trading_symbol, 
    //                     $signal['type'], 
    //                     $signal['price'], 
    //                     $config
    //                 );

    //                 if (!$optionDetails) continue;

    //                 // ✅ Get Signal-Time LTP (historical - from when signal was generated)
    //                 $signalTimeLTP = $this->getHistoricalLTP(
    //                     $candle->timestamp,
    //                     $optionDetails['token'],
    //                     $optionDetails['symbol'],
    //                     $broker  // ✅ Add broker parameter
    //                 );

    //                 $existingOrder = ZerodhaAutoOrder::where('trading_symbol', $symbol->trading_symbol)
    //                     ->where('signal_detected_at', $candle->timestamp)
    //                     ->where('user_id', auth()->id())
    //                     ->first();

    //                 $allSignals[] = [
    //                     'id' => $existingOrder ? $existingOrder->id : null,
    //                     'config_id' => $config->id,
    //                     'config_name' => "Config #{$config->id} ({$config->broker->client_name})",
    //                     'broker_id' => $broker->id,
    //                     'broker_name' => $broker->client_name,
    //                     'symbol_id' => $symbol->id,
    //                     'future_symbol' => $symbol->trading_symbol,
    //                     'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
    //                     'signal_type' => $signal['type'],
    //                     'strategy' => $signal['strategy'],
    //                     'supertrend_signal' => $signal['supertrend'],
    //                     'vwap_signal' => $signal['vwap'],
    //                     'future_price' => round($signal['price'], 2),
    //                     'option_symbol' => $optionDetails['symbol'],
    //                     'option_token' => $optionDetails['token'],
    //                     'option_type' => $optionDetails['type'],
    //                     'strike_price' => $optionDetails['strike'],
    //                     'signal_ltp' => round($signalTimeLTP, 2), // ✅ Historical LTP
    //                     'current_ltp' => 0.00, // ✅ Will be updated by frontend progressively
    //                     'quantity' => $config->getQuantityForSymbol($symbol->trading_symbol),
    //                     'has_order' => $existingOrder ? true : false,
    //                     'is_order_placed' => $existingOrder ? $existingOrder->is_order_placed : false,
    //                     'order_placed_at' => $existingOrder && $existingOrder->is_order_placed ? 
    //                         $existingOrder->order_placed_at->format('H:i:s') : null
    //                 ];
    //             }
    //         }

    //         // Sort by signal time
    //         usort($allSignals, function($a, $b) {
    //             return strtotime($a['signal_time']) - strtotime($b['signal_time']);
    //         });

    //         Log::info('=== MANUAL ORDER FETCH COMPLETE ===', [
    //             'total_signals' => count($allSignals)
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'data' => $allSignals,
    //             'total_signals' => count($allSignals),
    //             'message' => count($allSignals) . ' signals found for today'
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Manual Orders Fetch Error', [
    //             'message' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => []
    //         ], 500);
    //     }
    // }

    /**
     * Fetch today's 5-minute signals (FAST - no LTP fetching)
     */
    public function manualOrdersFetch(Request $request)
    {
        try {
            Log::info('=== MANUAL ORDER FETCH START ===');

            // Get all active configs for user
            $configs = ZerodhaAutoConfig::where('user_id', auth()->id())
                ->where('status', true)
                ->get();

            if ($configs->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active configurations found. Please create a config first.',
                    'data' => []
                ]);
            }

            // Get all active 5-minute symbols for user's brokers
            $brokerIds = $configs->pluck('broker_api_id')->unique()->toArray();
            
            $symbols = SymbolMonitored::where('is_active', true)
                ->where('interval', '5minute')
                ->whereIn('broker_api_id', $brokerIds)
                ->get();

            if ($symbols->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active 5-minute symbols found',
                    'data' => []
                ]);
            }

            $allSignals = [];
            // $today = Carbon::yesterday();
            $today = Carbon::today();

            // ✅ STEP 1: Collect ALL signals WITHOUT ANY LTP fetching (ultra-fast)
            foreach ($symbols as $symbol) {
                $config = $this->findConfigForSymbol($configs, $symbol);
                if (!$config) continue;

                $broker = BrokerApi::find($symbol->broker_api_id);
                if (!$broker || !$broker->hasValidToken()) continue;

                $todayCandles = $this->getTodayCandles(
                    $broker->id, 
                    $symbol->trading_symbol, 
                    '5minute', 
                    null
                );

                if ($todayCandles->count() < 1) continue;

                $previousDayLastCandle = $this->getPreviousDayLastCandle(
                    $broker->id, 
                    $symbol->trading_symbol, 
                    null
                );

                $allSyncPoints = $this->findAllSynchronizationPointsForManual(
                    $todayCandles,
                    $symbol->trading_symbol,
                    $config->signal_strategy,
                    $config,
                    $previousDayLastCandle
                );

                if (empty($allSyncPoints)) continue;

                $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
                    ->where('trading_symbol', $symbol->trading_symbol)
                    ->where('status', true)
                    ->whereDate('created_at', $today)
                    ->orderBy('created_at', 'asc')
                    ->get();

                $validSyncPoints = $this->getAllValidSyncPoints(
                    $allSyncPoints, 
                    $todayOrders, 
                    $symbol->trading_symbol
                );

                if (empty($validSyncPoints)) continue;

                // Process each sync point WITHOUT LTP fetching
                foreach ($validSyncPoints as $syncPoint) {
                    $candle = $syncPoint['candle'];
                    $signal = $syncPoint['signal'];

                    $optionDetails = $this->getATMOption(
                        $broker, 
                        $symbol->trading_symbol, 
                        $signal['type'], 
                        $signal['price'], 
                        $config
                    );

                    if (!$optionDetails) continue;

                    $existingOrder = ZerodhaAutoOrder::where('trading_symbol', $symbol->trading_symbol)
                        ->where('signal_detected_at', $candle->timestamp)
                        ->where('user_id', auth()->id())
                        ->first();

                    $allSignals[] = [
                        'id' => $existingOrder ? $existingOrder->id : null,
                        'config_id' => $config->id,
                        'config_name' => "Config #{$config->id} ({$config->broker->client_name})",
                        'broker_id' => $broker->id,
                        'broker_name' => $broker->client_name,
                        'symbol_id' => $symbol->id,
                        'future_symbol' => $symbol->trading_symbol,
                        'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
                        'signal_timestamp' => $candle->timestamp->timestamp, // ✅ For signal LTP fetch
                        'signal_type' => $signal['type'],
                        'strategy' => $signal['strategy'],
                        'supertrend_signal' => $signal['supertrend'],
                        'vwap_signal' => $signal['vwap'],
                        'future_price' => round($signal['price'], 2),
                        'option_symbol' => $optionDetails['symbol'],
                        'option_token' => $optionDetails['token'],
                        'option_type' => $optionDetails['type'],
                        'strike_price' => $optionDetails['strike'],
                        'signal_ltp' => 0.00, // ✅ Will be fetched by frontend
                        'current_ltp' => 0.00, // ✅ Will be fetched by frontend
                        'quantity' => $config->getQuantityForSymbol($symbol->trading_symbol),
                        'has_order' => $existingOrder ? true : false,
                        'is_order_placed' => $existingOrder ? $existingOrder->is_order_placed : false,
                        'order_placed_at' => $existingOrder && $existingOrder->is_order_placed ? 
                            $existingOrder->order_placed_at->format('H:i:s') : null
                    ];
                }
            }

            // Sort by signal time
            usort($allSignals, function($a, $b) {
                return strtotime($a['signal_time']) - strtotime($b['signal_time']);
            });

            Log::info('=== MANUAL ORDER FETCH COMPLETE (FAST) ===', [
                'total_signals' => count($allSignals),
                'load_time' => 'instant'
            ]);

            return response()->json([
                'success' => true,
                'data' => $allSignals,
                'total_signals' => count($allSignals),
                'message' => count($allSignals) . ' signals found for today'
            ]);

        } catch (\Exception $e) {
            Log::error('Manual Orders Fetch Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Fetch signal-time LTPs for multiple tokens (called by frontend progressively)
     */
    public function fetchSignalLTPsBatch(Request $request)
    {
        try {
            $request->validate([
                'broker_id' => 'required|exists:broker_apis,id',
                'signals' => 'required|array',
                'signals.*.token' => 'required|string',
                'signals.*.symbol' => 'required|string',
                'signals.*.timestamp' => 'required|integer'
            ]);

            $broker = BrokerApi::findOrFail($request->broker_id);

            if (!$broker->hasValidToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Broker token invalid'
                ], 401);
            }

            $signalLTPs = [];

            foreach ($request->signals as $signal) {
                $signalTime = Carbon::createFromTimestamp($signal['timestamp']);
                
                $ltp = $this->getHistoricalLTP(
                    $signalTime,
                    $signal['token'],
                    $signal['symbol'],
                    $broker
                );

                $signalLTPs[$signal['token']] = $ltp;
            }

            return response()->json([
                'success' => true,
                'data' => $signalLTPs
            ]);

        } catch (\Exception $e) {
            Log::error('Batch Signal LTP Fetch Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get historical LTP at signal time (from database or estimate)
     */
    // private function getHistoricalLTP($signalTime, $optionToken, $optionSymbol)
    // {
    //     try {
    //         // Try to find historical data from symbol_data table (if you store option prices)
    //         $historicalData = SymbolData::where('trading_symbol', $optionSymbol)
    //             ->where('timestamp', '<=', $signalTime)
    //             ->orderBy('timestamp', 'DESC')
    //             ->first();

    //         if ($historicalData && $historicalData->close) {
    //             return $historicalData->close;
    //         }

    //         // Fallback: Check if order exists with entry_price
    //         $existingOrder = ZerodhaAutoOrder::where('option_token', $optionToken)
    //             ->where('signal_detected_at', $signalTime)
    //             ->first();

    //         if ($existingOrder && $existingOrder->entry_price) {
    //             return $existingOrder->entry_price;
    //         }

    //         // Last resort: return placeholder
    //         return 0.00;

    //     } catch (\Exception $e) {
    //         Log::error("❌ [Historical LTP] Error: " . $e->getMessage());
    //         return 0.00;
    //     }
    // }

    /**
     * Get historical LTP at signal time (from Kite API 5-min candle or cache)
     */
    private function getHistoricalLTP($signalTime, $optionToken, $optionSymbol, BrokerApi $broker)
    {
        try {
            // ✅ Cache key for signal-time LTP (never expires because it's historical)
            $cacheKey = "signal_ltp_{$optionToken}_{$signalTime->timestamp}";
            
            // Check cache first
            $cachedLTP = \Cache::get($cacheKey);
            if ($cachedLTP !== null) {
                Log::info("📦 Cache HIT: {$optionSymbol} @ {$signalTime} = ₹{$cachedLTP}");
                return $cachedLTP;
            }

            Log::info("🔍 Cache MISS: Fetching historical LTP for {$optionSymbol} @ {$signalTime}");

            // ✅ Strategy 1: Check if order exists with entry_price
            $existingOrder = ZerodhaAutoOrder::where('option_token', $optionToken)
                ->where('signal_detected_at', $signalTime)
                ->first();

            if ($existingOrder && $existingOrder->entry_price > 0) {
                \Cache::forever($cacheKey, $existingOrder->entry_price);
                Log::info("✅ Found from existing order: {$optionSymbol} = ₹{$existingOrder->entry_price}");
                return $existingOrder->entry_price;
            }

            // ✅ Strategy 2: Fetch from Kite API (5-minute candle at signal time)
            $historicalPrice = $this->fetchHistoricalPriceFromKite($broker, $optionToken, $optionSymbol, $signalTime);
            
            if ($historicalPrice > 0) {
                \Cache::forever($cacheKey, $historicalPrice);
                Log::info("✅ [Signal LTP] Fetched from Kite API: {$optionSymbol} @ {$signalTime} = ₹{$historicalPrice}");
                return $historicalPrice;
            }

            // ✅ Strategy 3: Final fallback - current LTP
            $currentLTP = $this->getOptionLTP($broker, $optionToken, $optionSymbol);
            if ($currentLTP > 0) {
                \Cache::forever($cacheKey, $currentLTP);
                Log::warning("⚠️ [Signal LTP] Using current LTP as fallback: {$optionSymbol} = ₹{$currentLTP}");
                return $currentLTP;
            }

            Log::error("❌ [Signal LTP] All strategies failed for: {$optionSymbol} @ {$signalTime}");
            return 0.00;

        } catch (\Exception $e) {
            Log::error("❌ [Historical LTP] Error: " . $e->getMessage());
            return 0.00;
        }
    }

    /**
     * Fetch historical price from Kite API using 5-minute candles
     * (Same logic as SymbolBacktestProfitController)
     */
    private function fetchHistoricalPriceFromKite(BrokerApi $broker, $optionToken, $optionSymbol, $datetime)
    {
        try {
            // Initialize Kite instance for this broker
            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];

            // Get instrument details
            $instrument = ZerodhaInstrument::where('instrument_token', $optionToken)
                ->where('exchange', 'NFO')
                ->first();

            if (!$instrument) {
                Log::warning("Instrument not found for token: {$optionToken}");
                return null;
            }

            // Fetch candles around the signal time (±30 minutes window)
            $fromDate = $datetime->copy()->subMinutes(30)->format('Y-m-d H:i:s');
            $toDate = $datetime->copy()->addMinutes(30)->format('Y-m-d H:i:s');

            Log::info("🔍 Fetching 5-min candles for {$optionSymbol}");
            Log::info("📅 Time range: {$fromDate} to {$toDate}");

            // Fetch historical candle data
            $response = $kite->getHistoricalData(
                $instrument->instrument_token,
                '5minute',
                $fromDate,
                $toDate
            );

            // Handle the response properly
            $candles = [];
            
            if (is_object($response)) {
                if (isset($response->candles)) {
                    $candles = $response->candles;
                } else {
                    $candles = (array) $response;
                }
            } elseif (is_array($response)) {
                $candles = $response;
            }

            Log::info("📊 Received " . count($candles) . " candles");

            if (empty($candles)) {
                Log::warning("No candles returned for {$optionSymbol}");
                return null;
            }

            // Find closest candle to signal time
            $targetTimestamp = $datetime->timestamp;
            $closestCandle = null;
            $minDiff = PHP_INT_MAX;

            foreach ($candles as $index => $candle) {
                // Convert candle to array if it's an object
                if (is_object($candle)) {
                    $candle = (array) $candle;
                }
                
                // Parse candle timestamp
                $candleTime = null;
                
                if (isset($candle['date'])) {
                    if ($candle['date'] instanceof \DateTime) {
                        $candleTime = $candle['date']->getTimestamp();
                    } elseif (is_object($candle['date']) && method_exists($candle['date'], 'getTimestamp')) {
                        $candleTime = $candle['date']->getTimestamp();
                    } elseif (is_string($candle['date'])) {
                        $candleTime = strtotime($candle['date']);
                    } elseif (is_numeric($candle['date'])) {
                        $candleTime = $candle['date'];
                    }
                }
                
                if ($candleTime === null) {
                    Log::warning("⚠️ Could not parse candle date at index {$index}");
                    continue;
                }
                
                // Calculate time difference
                $diff = abs($candleTime - $targetTimestamp);
                
                // Keep track of closest candle
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $closestCandle = $candle;
                }
            }

            // Extract price from closest candle
            if ($closestCandle) {
                if (is_object($closestCandle)) {
                    $closestCandle = (array) $closestCandle;
                }
                
                $price = $closestCandle['close'] ?? null;
                
                if ($price === null) {
                    Log::error("❌ Close price not found in candle");
                    return null;
                }
                
                // Format candle date for logging
                $candleDateStr = 'Unknown';
                if (isset($closestCandle['date'])) {
                    if ($closestCandle['date'] instanceof \DateTime) {
                        $candleDateStr = $closestCandle['date']->format('Y-m-d H:i:s');
                    } elseif (is_string($closestCandle['date'])) {
                        $candleDateStr = $closestCandle['date'];
                    }
                }
                
                Log::info("✅ Found close price: ₹{$price} for {$optionSymbol}");
                Log::info("📍 Closest candle at {$candleDateStr} (diff: {$minDiff} seconds)");
                
                return $price;
            }

            Log::warning("❌ No suitable candle found for {$optionSymbol}");
            return null;

        } catch (\Exception $e) {
            Log::error("🚨 Kite API Error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Batch fetch LTPs for multiple instruments in ONE API call
     */
    private function getBatchOptionLTPs(BrokerApi $broker, array $instrumentTokens)
    {
        try {
            if (empty($instrumentTokens)) {
                return [];
            }

            // Remove duplicates
            $uniqueTokens = array_unique($instrumentTokens);

            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];

            // Get instrument details to build quote keys
            $instruments = ZerodhaInstrument::whereIn('instrument_token', $uniqueTokens)
                ->where('exchange', 'NFO')
                ->get()
                ->keyBy('instrument_token');

            // Build quote keys array
            $quoteKeys = [];
            foreach ($uniqueTokens as $token) {
                if (isset($instruments[$token])) {
                    $quoteKeys[] = "NFO:" . $instruments[$token]->trading_symbol;
                }
            }

            if (empty($quoteKeys)) {
                return [];
            }

            // Single batch API call for ALL instruments
            $quotes = $kite->getQuote($quoteKeys);
            $quotesArray = json_decode(json_encode($quotes), true);

            // Map results back to instrument tokens
            $ltpMap = [];
            foreach ($instruments as $token => $instrument) {
                $quoteKey = "NFO:" . $instrument->trading_symbol;
                
                if (isset($quotesArray[$quoteKey]['last_price'])) {
                    $ltpMap[$token] = $quotesArray[$quoteKey]['last_price'];
                } else {
                    $ltpMap[$token] = 25.00; // fallback
                }
            }

            Log::info("✅ Batch LTP fetch: " . count($ltpMap) . " instruments");
            return $ltpMap;

        } catch (\Exception $e) {
            Log::error("❌ [BATCH LTP] Error: " . $e->getMessage());
            
            // Return fallback values for all tokens
            $fallback = [];
            foreach ($instrumentTokens as $token) {
                $fallback[$token] = 25.00;
            }
            return $fallback;
        }
    }

    /**
     * Place manual order (1-2 click placement)
     */
    public function placeManualOrder(Request $request)
    {
        $request->merge([
            'force' => filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $request->validate([
            'config_id' => 'required|exists:zerodha_auto_configs,id',
            'symbol_id' => 'required|exists:symbols_monitored,id',
            'future_symbol' => 'required|string',
            'signal_time' => 'required|date',
            'signal_type' => 'required|in:BUY,SELL',
            'option_symbol' => 'required|string',
            'option_token' => 'required|string',
            'strike_price' => 'required|numeric',
            'future_price' => 'required|numeric',
            'force' => 'sometimes|boolean'
        ]);

        try {
            Log::info('=== MANUAL ORDER PLACEMENT START ===', $request->all());
            $force = $request->boolean('force');

            // Check if order already exists (unless force=true)
            if (!$force) {
                $existingOrder = ZerodhaAutoOrder::where('trading_symbol', $request->future_symbol)
                    ->where('signal_detected_at', $request->signal_time)
                    ->where('user_id', auth()->id())
                    ->first();

                if ($existingOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order already exists for this signal. Check "Force Order" to place again.',
                        'existing_order_id' => $existingOrder->id
                    ], 409);
                }
            }

            $config = ZerodhaAutoConfig::where('id', $request->config_id)
                ->where('user_id', auth()->id())
                ->where('status', true)
                ->firstOrFail();

            $symbol = SymbolMonitored::findOrFail($request->symbol_id);
            $broker = $config->broker;

            if (!$broker->hasValidToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Broker token is invalid or expired'
                ], 401);
            }

            // Get option instrument
            $optionInstrument = ZerodhaInstrument::where('instrument_token', $request->option_token)
                ->where('exchange', 'NFO')
                ->first();

            if (!$optionInstrument) {
                return response()->json([
                    'success' => false,
                    'message' => 'Option instrument not found'
                ], 404);
            }

            // Get LIVE LTP
            $ltp = $this->getOptionLTP($broker, $optionInstrument->instrument_token, $request->option_symbol);

            // Calculate quantities
            $quantity = $config->getQuantityForSymbol($request->future_symbol);
            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($quantity);

            // Create order entry
            $orderData = [
                'user_id' => auth()->id(),
                'config_id' => $config->id,
                'broker_api_id' => $broker->id,
                'symbol' => $symbol->symbol,
                'trading_symbol' => $request->future_symbol,
                'instrument_token' => $symbol->instrument_token,
                'signal_type' => $request->signal_type,
                'signal_strategy' => $config->signal_strategy,
                'supertrend_signal' => $request->signal_type,
                'vwap_signal' => $request->signal_type,
                'signal_detected_at' => $request->signal_time,
                'option_symbol' => $request->option_symbol,
                'option_token' => $optionInstrument->instrument_token,
                'option_type' => $request->signal_type == 'BUY' ? 'CE' : 'PE',
                'strike_price' => $request->strike_price,
                'atm_price' => $request->future_price,
                'entry_price' => $ltp,
                'current_price' => $ltp,
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $quantity,
                'pyramid_1' => $pyramid1,
                'pyramid_2' => $pyramid2,
                'pyramid_3' => $pyramid3,
                'is_order_placed' => false,
                'status' => true
            ];

            $order = ZerodhaAutoOrder::create($orderData);

            // Place order IMMEDIATELY (like cron)
            $this->placeOrderNow($order);

            Log::info('✅ Manual order created and placed: ' . $order->id);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'data' => [
                    'order_id' => $order->id,
                    'option_symbol' => $request->option_symbol,
                    'quantity' => $quantity,
                    'ltp' => $ltp
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Manual Order Placement Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error placing order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch live LTPs for multiple tokens (called by frontend progressively)
     */
    public function fetchLiveLTPsBatch(Request $request)
    {
        try {
            $request->validate([
                'broker_id' => 'required|exists:broker_apis,id',
                'tokens' => 'required|array',
                'tokens.*' => 'required|string'
            ]);

            $broker = BrokerApi::findOrFail($request->broker_id);

            if (!$broker->hasValidToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Broker token invalid'
                ], 401);
            }

            $liveLTPs = $this->getBatchOptionLTPs($broker, $request->tokens);

            return response()->json([
                'success' => true,
                'data' => $liveLTPs
            ]);

        } catch (\Exception $e) {
            Log::error('Batch LTP Fetch Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    private function findConfigForSymbol($configs, SymbolMonitored $symbol)
    {
        $config = $configs->first(function($config) use ($symbol) {
            return $config->user_id === $symbol->user_id 
                && $config->broker_api_id === $symbol->broker_api_id;
        });

        if ($config) {
            return $config;
        }

        return $configs->first(function($config) use ($symbol) {
            return $config->broker_api_id === $symbol->broker_api_id;
        });
    }

    private function getTodayCandles($brokerId, $tradingSymbol, $interval, $testDate = null)
    {
        $query = SymbolData::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->whereNotNull('atr')
            ->whereNotNull('supertrend')
            ->whereNotNull('supertrend_direction')
            ->orderBy('timestamp', 'ASC');

        if ($testDate) {
            if (strpos($testDate, ':') !== false) {
                $date = Carbon::parse($testDate)->startOfDay();
                $query->whereDate('timestamp', $date)
                      ->where('timestamp', '<=', $testDate);
            } else {
                $query->whereDate('timestamp', $testDate);
            }
        } else {
            $today = Carbon::today()->setTime(9, 15, 0);
            $now = Carbon::now();
            $query->whereBetween('timestamp', [$today, $now]);

            // $yesterday = Carbon::yesterday()->setTime(9, 15, 0);
            // $yesterdayEnd = Carbon::yesterday()->setTime(15, 30, 0); // or endOfDay()
            // $query->whereBetween('timestamp', [$yesterday, $yesterdayEnd]);
        }

        return $query->get();
    }

    private function getPreviousDayLastCandle($brokerId, $tradingSymbol, $testDate = null)
    {
        try {
            $targetDate = $testDate ? Carbon::parse($testDate) : Carbon::today();
            // $targetDate = $testDate ? Carbon::parse($testDate) : Carbon::yesterday();

            $lastCandle = SymbolData::where('broker_api_id', $brokerId)
                ->where('trading_symbol', $tradingSymbol)
                ->where('interval', '5minute')
                ->where('timestamp', '<', $targetDate->format('Y-m-d 00:00:00'))
                ->whereNotNull('supertrend')
                ->whereNotNull('vwap')
                ->orderBy('timestamp', 'DESC')
                ->first();

            return $lastCandle;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function findAllSynchronizationPointsForManual($candles, $tradingSymbol, $strategy, ZerodhaAutoConfig $config, $previousDayLastCandle = null)
    {
        $syncPoints = [];
        $currentSupertrendSignal = null;
        $currentVwapSignal = null;

        // Initialize from previous day
        if ($previousDayLastCandle) {
            if ($previousDayLastCandle->supertrend_signal == 'BUY' || 
                $previousDayLastCandle->supertrend_signal == 'SELL') {
                $currentSupertrendSignal = $previousDayLastCandle->supertrend_signal;
            }

            $prevVwap = $previousDayLastCandle->vwap_signal ?? 'HOLD';
            if ($prevVwap === 'GAP_UP' || $prevVwap === 'BUY') {
                $currentVwapSignal = 'BUY';
            } elseif ($prevVwap === 'GAP_DOWN' || $prevVwap === 'SELL') {
                $currentVwapSignal = 'SELL';
            }
        }

        $previousSyncType = null;

        foreach ($candles as $index => $candle) {
            $recordSupertrendSignal = $candle->supertrend_signal;
            $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
            $recordDirection = $candle->supertrend_direction;

            // SUPERTREND LOGIC
            $supertrendFresh = false;
            
            if ($currentSupertrendSignal === null && in_array($recordSupertrendSignal, ['BUY', 'SELL'])) {
                $supertrendFresh = true;
                $currentSupertrendSignal = $recordSupertrendSignal;
            }
            elseif ($recordSupertrendSignal === 'BUY' && $currentSupertrendSignal !== 'BUY') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'BUY';
            }
            elseif ($recordSupertrendSignal === 'SELL' && $currentSupertrendSignal !== 'SELL') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'SELL';
            }
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'UP') {
                if ($currentSupertrendSignal !== 'BUY') {
                    $currentSupertrendSignal = 'BUY';
                }
            }
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'DOWN') {
                if ($currentSupertrendSignal !== 'SELL') {
                    $currentSupertrendSignal = 'SELL';
                }
            }

            // VWAP LOGIC
            $vwapFresh = false;
            
            if ($currentVwapSignal === null) {
                if ($recordVwapSignal === 'GAP_UP' || $recordVwapSignal === 'BUY') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'BUY';
                } elseif ($recordVwapSignal === 'GAP_DOWN' || $recordVwapSignal === 'SELL') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'SELL';
                } else {
                    $currentVwapSignal = 'HOLD';
                }
            }
            elseif ($recordVwapSignal === 'GAP_UP' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
            }
            elseif ($recordVwapSignal === 'GAP_DOWN' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
            }
            elseif ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
            }
            elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
            }

            // CHECK STRATEGY
            $shouldTrigger = false;
            $signalType = null;

            switch ($strategy) {
                case 'SUPERTREND':
                    if ($supertrendFresh && in_array($currentSupertrendSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    break;

                case 'VWAP':
                    if ($vwapFresh && in_array($currentVwapSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                    }
                    break;

                case 'BOTH':
                case 'SUPERTREND_VWAP':
                default:
                    if ($supertrendFresh && $vwapFresh && $currentSupertrendSignal === $currentVwapSignal) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    elseif ($supertrendFresh && $currentSupertrendSignal === $currentVwapSignal && 
                            in_array($currentVwapSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    elseif ($vwapFresh && $currentVwapSignal === $currentSupertrendSignal && 
                            in_array($currentSupertrendSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                    }
                    break;
            }

            // CREATE SYNC POINT (NO QUALITY FILTER FOR MANUAL - SHOW ALL)
            if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
                if ($signalType !== $previousSyncType) {
                    $syncPoints[] = [
                        'index' => $index,
                        'candle' => $candle,
                        'signal' => [
                            'type' => $signalType,
                            'supertrend' => $currentSupertrendSignal,
                            'vwap' => $currentVwapSignal,
                            'price' => $candle->close,
                            'strategy' => $strategy
                        ]
                    ];
                    
                    $previousSyncType = $signalType;
                }
            }
        }

        return $syncPoints;
    }

    private function getAllValidSyncPoints($allSyncPoints, $existingOrders, $tradingSymbol)
    {
        if (empty($allSyncPoints)) {
            return [];
        }

        $lastSignalTypeFromDB = null;
        $lastProcessedTime = null;
        
        if ($existingOrders->isNotEmpty()) {
            $lastOrder = $existingOrders->last();
            $lastSignalTypeFromDB = $lastOrder->signal_type;
            $lastProcessedTime = $lastOrder->signal_detected_at;
        }

        $newSyncPoints = array_filter($allSyncPoints, function($point) use ($lastProcessedTime) {
            if (!$lastProcessedTime) {
                return true;
            }
            return $point['candle']->timestamp > $lastProcessedTime;
        });

        if (empty($newSyncPoints)) {
            return [];
        }

        $validSignals = [];
        $lastSignalType = $lastSignalTypeFromDB;

        foreach ($newSyncPoints as $syncPoint) {
            $syncType = $syncPoint['signal']['type'];

            if ($lastSignalType === $syncType) {
                continue;
            }

            $validSignals[] = $syncPoint;
            $lastSignalType = $syncType;
        }

        return $validSignals;
    }

    private function getATMOption(BrokerApi $broker, $tradingSymbol, $signalType, $futurePrice, ZerodhaAutoConfig $config)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
            
            // NO OPTION_FILTER CHECK - PLACE DIRECTLY WHAT USER CONFIRMS
            $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
            $intervals = [
                'NIFTY' => 50,
                'BANKNIFTY' => 100,
                'FINNIFTY' => 50,
                'MIDCPNIFTY' => 25,
            ];
            $strikeInterval = $intervals[$baseSymbol] ?? 20;
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($config->option_series === 'next') {
                $query->orderBy('expiry', 'ASC')->skip(1)->take(1);
            } else {
                $query->orderBy('expiry', 'ASC');
            }

            $option = $query->first();

            if (!$option) {
                $query = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike]);

                if ($config->option_series === 'next') {
                    $query->orderBy('expiry', 'ASC')->orderBy('strike_diff', 'ASC')->skip(1)->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if (!$option) {
                return null;
            }

            // $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            return [
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'type' => $optionType,
                'strike' => $option->strike,
                'ltp' => 00, // Placeholder - will be updated by batch call
                'expiry' => $option->expiry
            ];

        } catch (\Exception $e) {
            Log::error("❌ [OPTION] Error: " . $e->getMessage());
            return null;
        }
    }

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, $tradingSymbol)
    {
        try {
            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];
            $quoteKey = "NFO:" . $tradingSymbol;
            $quotes = $kite->getQuote([$quoteKey]);

            if (isset($quotes->$quoteKey->last_price)) {
                return $quotes->$quoteKey->last_price;
            }

            $quotesArray = json_decode(json_encode($quotes), true);
            if (isset($quotesArray[$quoteKey]['last_price'])) {
                return $quotesArray[$quoteKey]['last_price'];
            }

            return 25.00;

        } catch (\Exception $e) {
            Log::error("❌ [LTP] Error: " . $e->getMessage());
            return 25.00;
        }
    }

    private function placeOrderNow(ZerodhaAutoOrder $order)
    {
        try {
            $broker = $order->broker;

            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();

            if (!$instrument) {
                throw new \Exception('Instrument not found');
            }

            $this->placePyramidOrders($order, $instrument, $kite);

            $order->update([
                'is_order_placed' => true,
                'order_placed_at' => now()
            ]);

            Log::info("✅ Order placed successfully: ID {$order->id}");

        } catch (\Exception $e) {
            Log::error("❌ Error placing order: " . $e->getMessage());
            throw $e;
        }
    }

    private function placePyramidOrders($order, $instrument, $kite)
    {
        $pyramids = [$order->pyramid_1, $order->pyramid_2, $order->pyramid_3];
        $delays = [0];

        if ($order->pyramid_2) $delays[] = $order->config->pyramid_freq * 60;
        if ($order->pyramid_3) $delays[] = $order->config->pyramid_freq * 60 * 2;

        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->trading_symbol);
        $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

        foreach ($pyramids as $pyramidIndex => $pyramidQty) {
            if (!$pyramidQty) continue;

            if (($delays[$pyramidIndex] ?? 0) > 0) {
                sleep($delays[$pyramidIndex]);
            }

            $price = null;
            if ($order->order_type == 'LIMIT') {
                $pyramidLevel = $pyramidIndex + 1;
                $price = $this->calculateLimitPrice(
                    $order->entry_price,
                    $order->config->disc_ltp,
                    $order->signal_type,
                    $instrument->tick_size,
                    $pyramidLevel
                );
            }

            if ($freezeLimitLots && $pyramidQty > $freezeLimitLots) {
                $numOrders = ceil($pyramidQty / $freezeLimitLots);
                $remainingLots = $pyramidQty;

                for ($i = 0; $i < $numOrders; $i++) {
                    $lotsToPlace = min($freezeLimitLots, $remainingLots);
                    $this->placeKiteOrder($order, $lotsToPlace, $price, $instrument, $kite);
                    $remainingLots -= $lotsToPlace;

                    if ($i < $numOrders - 1) sleep(2);
                }
            } else {
                $this->placeKiteOrder($order, $pyramidQty, $price, $instrument, $kite);
            }
        }
    }

    private function calculateLimitPrice($entryPrice, $discLtp, $signalType, $tickSize, $pyramidLevel = 1)
    {
        $effectiveDiscount = $discLtp;

        if ($pyramidLevel == 2) {
            $effectiveDiscount = $discLtp + ($discLtp * 0.5);
        } elseif ($pyramidLevel == 3) {
            $pyramid2Discount = $discLtp + ($discLtp * 0.5);
            $effectiveDiscount = $pyramid2Discount + ($pyramid2Discount * 0.5);
        }

        $discount = ($entryPrice * $effectiveDiscount) / 100;
        $price = $entryPrice - $discount;
        $roundedPrice = round($price / $tickSize) * $tickSize;

        return number_format($roundedPrice, 2, '.', '');
    }

    private function placeKiteOrder(ZerodhaAutoOrder $order, $quantity, $price, $instrument, $kite)
    {
        try {
            $orderParams = [
                'exchange' => 'NFO',
                'tradingsymbol' => $order->option_symbol,
                'transaction_type' => 'BUY',
                'quantity' => $quantity * $instrument->lot_size,
                'product' => $order->product,
                'validity' => 'DAY'
            ];

            if ($order->order_type == 'MARKET') {
                $orderParams['order_type'] = 'MARKET';
            } else {
                $orderParams['order_type'] = 'LIMIT';
                $orderParams['price'] = $price;
            }

            $result = $kite->placeOrder("regular", $orderParams);
            Log::info("✅ Order placed! Order ID: {$result->order_id}");

            sleep(1);
            $orderHistory = $kite->getOrderHistory($result->order_id);
            $lastOrder = end($orderHistory);

            OrderBook::create([
                'user_id' => $order->user_id,
                'broker_username' => $order->broker->account_user_name,
                'order_id' => $result->order_id,
                'status' => $lastOrder->status ?? 'PENDING',
                'trading_symbol' => $order->option_symbol,
                'order_type' => $order->order_type,
                'transaction_type' => 'BUY',
                'product' => $order->product,
                'price' => $price ?? '-',
                'quantity' => $quantity,
                'status_message' => $lastOrder->status_message ?? 'Order placed',
                'order_datetime' => now(),
                'symbol_auto_order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Kite Order Error: " . $e->getMessage());
            throw $e;
        }
    }
}