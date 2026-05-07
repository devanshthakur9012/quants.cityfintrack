<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Helpers\CustomPriceBehaviorHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CustomBehaviorController extends Controller
{
    /**
     * Custom Behavior Analysis Page
     */
    public function analysis()
    {
        $pageTitle = 'Custom Price Behavior Analysis';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.symbols.custom_behavior', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Fetch Custom Behavior Analysis Data (AJAX)
     */
    public function analysisFetch(Request $request)
    {
        try {
            Log::info('=== CUSTOM BEHAVIOR ANALYSIS START ===', [
                'inputs' => $request->all()
            ]);

            $tradingSymbol = $request->get('trading_symbol');
            $interval = $request->get('interval', '5minute');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            if (!$tradingSymbol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a trading symbol',
                    'data' => []
                ]);
            }

            $query = SymbolData::where('trading_symbol', $tradingSymbol)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'ASC');

            if ($fromDate) {
                $query->where('timestamp', '>=', Carbon::parse($fromDate)->startOfDay());
            }
            
            if ($toDate) {
                $query->where('timestamp', '<=', Carbon::parse($toDate)->endOfDay());
            }

            if (!$fromDate && !$toDate) {
                $query->whereDate('timestamp', Carbon::today());
            }

            $candles = $query->limit(500)->get();

            if ($candles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for the selected filters',
                    'data' => []
                ]);
            }

            Log::info("Found {$candles->count()} candles for analysis");

            // Analyze using Custom Price Behavior Helper
            $analysis = CustomPriceBehaviorHelper::analyzeCandles($candles);

            if ($analysis['error']) {
                return response()->json([
                    'success' => false,
                    'message' => $analysis['error'],
                    'data' => []
                ]);
            }

            return response()->json([
                'success' => true,
                'trading_symbol' => $tradingSymbol,
                'interval' => $interval,
                'data' => $analysis['results'],
                'message' => 'Custom behavior analysis completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Custom Behavior Analysis Error', [
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
     * Backtesting Page
     */
    public function backtesting()
    {
        $pageTitle = 'Custom Behavior Backtesting';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.symbols.custom_backtest', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Backtesting Fetch with Custom Behavior Logic
     */
    public function backtestingFetch(Request $request)
    {
        try {
            Log::info('=== CUSTOM BEHAVIOR BACKTESTING START ===', [
                'inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $interval = $request->get('interval', '5minute');
            $behaviorType = $request->get('behavior_type', 'ALL'); // Filter by behavior type
            $optionSeries = $request->get('option_series', 'current');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data' => []
                ]);
            }

            // Date range
            $startDateTime = $fromDate . ' 09:15:00';
            $endDateTime = $toDate . ' 15:30:00';

            // Get symbols to check
            $symbolsQuery = SymbolMonitored::where('is_active', true);
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

            // Exclude symbols without options
            $excludedSymbols = ['NIFTYNXT5026JANFUT'];
            $symbols = array_values(array_filter($symbols, function($symbol) use ($excludedSymbols) {
                if (in_array($symbol, $excludedSymbols) || strpos($symbol, 'NIFTYNXT50') !== false) {
                    Log::info("⚠️ Excluding {$symbol} - No options available");
                    return false;
                }
                return true;
            }));

            Log::info('Processing Symbols', [
                'total' => count($symbols),
                'symbols' => $symbols
            ]);

            if (empty($symbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid symbols to backtest',
                    'data' => []
                ]);
            }

            $allSignals = [];

            foreach ($symbols as $symbol) {
                Log::info("════════ Processing: {$symbol} ════════");

                $candles = SymbolData::where('trading_symbol', $symbol)
                    ->where('interval', $interval)
                    ->where('timestamp', '>=', $startDateTime)
                    ->where('timestamp', '<=', $endDateTime)
                    ->orderBy('timestamp', 'ASC')
                    ->get();

                Log::info("Found {$candles->count()} candles for {$symbol}");

                if ($candles->count() < 15) {
                    Log::warning("Not enough data for {$symbol}");
                    continue;
                }

                // Analyze candles with custom behavior logic
                $analysis = CustomPriceBehaviorHelper::analyzeCandles($candles);

                if ($analysis['error']) {
                    Log::warning("Analysis error for {$symbol}: {$analysis['error']}");
                    continue;
                }

                // Filter signals based on behavior type if specified
                $signals = collect($analysis['results'])->filter(function($result) use ($behaviorType) {
                    if ($behaviorType === 'ALL') {
                        return $result['signal']['signal'] !== 'HOLD';
                    }
                    return $result['classification']['type'] === $behaviorType && 
                           $result['signal']['signal'] !== 'HOLD';
                })->values()->toArray();

                Log::info("Found " . count($signals) . " signals for {$symbol}");

                // Convert to tradeable signals
                $tradeableSignals = $this->convertToTradeableSignals($signals, $symbol, $optionSeries);
                
                $allSignals = array_merge($allSignals, $tradeableSignals);
            }

            // Sort by timestamp
            usort($allSignals, function($a, $b) {
                return strtotime($a['signal_time']) - strtotime($b['signal_time']);
            });

            Log::info('=== BACKTESTING COMPLETE ===', [
                'total_signals' => count($allSignals)
            ]);

            return response()->json([
                'success' => true,
                'data' => $allSignals,
                'total_signals' => count($allSignals),
                'message' => count($allSignals) . ' trading signals found'
            ]);

        } catch (\Exception $e) {
            Log::error('Custom Backtesting Error', [
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
     * Convert analysis results to tradeable signals
     */
    private function convertToTradeableSignals($signals, $symbol, $optionSeries)
    {
        $tradeable = [];
        $lastSignalType = null;

        foreach ($signals as $signal) {
            $signalType = $signal['signal']['signal'];
            
            // Skip if same as last signal (avoid duplicates)
            if ($lastSignalType === $signalType) {
                continue;
            }

            // Get option information
            $optionInfo = $this->getOptionSymbol(
                $symbol, 
                $signalType, 
                $signal['candle']['close'],
                $optionSeries
            );

            $tradeable[] = [
                'future_symbol' => $symbol,
                'signal_time' => $signal['date'] ?? $signal['timestamp'], // Already formatted string
                'signal_type' => $signalType,
                'behavior_type' => $signal['classification']['type'],
                'behavior_confidence' => $signal['classification']['confidence'],
                'signal_strength' => $signal['signal']['strength'],
                'signal_reason' => $signal['signal']['reason'],
                'future_price' => $signal['candle']['close'],
                'option_type' => $optionInfo['option_type'],
                'option_symbol' => $optionInfo['option_symbol'],
                'strike_price' => $optionInfo['strike_price'],
                'metrics' => $signal['classification']['metrics'],
                'order_action' => 'Would place ' . $optionInfo['option_type'] . ' order'
            ];

            $lastSignalType = $signalType;
        }

        return $tradeable;
    }

    /**
     * Get option symbol for backtesting
     */
    private function getOptionSymbol($futureSymbol, $signalType, $futurePrice, $optionSeries = 'current')
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $futureSymbol);
            $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
            $strikeIntervals = [
                'NIFTY' => 50,
                'BANKNIFTY' => 100,
                'FINNIFTY' => 50,
                'MIDCPNIFTY' => 25,
            ];
            $strikeInterval = $strikeIntervals[$baseSymbol] ?? 20;
            
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;
            
            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($optionSeries === 'next') {
                $query->orderBy('expiry', 'ASC')->skip(1)->take(1);
            } else {
                $query->orderBy('expiry', 'ASC');
            }

            $option = $query->first();

            if (!$option) {
                // Fallback: find nearest strike
                $query = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike]);

                if ($optionSeries === 'next') {
                    $query->orderBy('expiry', 'ASC')
                        ->orderBy('strike_diff', 'ASC')
                        ->skip(1)
                        ->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')
                        ->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if ($option) {
                return [
                    'option_type' => $optionType,
                    'option_symbol' => $option->trading_symbol,
                    'strike_price' => $option->strike
                ];
            }

            return [
                'option_type' => $optionType,
                'option_symbol' => $baseSymbol . $calculatedStrike . $optionType,
                'strike_price' => $calculatedStrike
            ];

        } catch (\Exception $e) {
            Log::error("Error getting option symbol: " . $e->getMessage());
            return [
                'option_type' => $signalType == 'BUY' ? 'CE' : 'PE',
                'option_symbol' => 'N/A',
                'strike_price' => 0
            ];
        }
    }
}