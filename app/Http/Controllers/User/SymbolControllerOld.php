<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\IndicatorConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SymbolControllerOld extends Controller
{
    /**
     * Technical Analysis Page
     */
    public function analysis()
    {
        $pageTitle = 'Technical Analysis - Supertrend + 50 MA';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->where('interval', '15minute') // Only 15-minute symbols
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.symbols.analysis', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Fetch Latest Row for Each Symbol (Default View)
     */
    public function analysisLatest(Request $request)
    {
        try {
            $interval = '15minute'; // Fixed to 15-minute

            // Get all monitored symbols
            $symbols = SymbolMonitored::where('is_active', true)
                ->where('interval', $interval)
                ->pluck('trading_symbol')
                ->toArray();

            if (empty($symbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No monitored symbols found',
                    'data' => []
                ]);
            }

            $latestData = [];

            // Get latest record for each symbol
            foreach ($symbols as $symbol) {
                $latest = SymbolData::where('trading_symbol', $symbol)
                    ->where('interval', $interval)
                    ->whereNotNull('atr')
                    ->whereNotNull('supertrend')
                    ->whereNotNull('ma50')
                    ->orderBy('timestamp', 'DESC')
                    ->first();

                if ($latest) {
                    $latestData[] = [
                        'date' => $latest->timestamp->format('Y-m-d H:i:s'),
                        'symbol' => $latest->trading_symbol,
                        'open' => (float)$latest->open,
                        'high' => (float)$latest->high,
                        'low' => (float)$latest->low,
                        'close' => (float)$latest->close,
                        'volume' => (int)$latest->volume,
                        'atr' => $latest->atr ? round($latest->atr, 4) : null,
                        'supertrend' => $latest->supertrend ? round($latest->supertrend, 2) : null,
                        'direction' => $latest->supertrend_direction,
                        'signal' => $latest->supertrend_signal,
                        'ma50' => $latest->ma50 ? round($latest->ma50, 2) : null,
                        'upper_band' => $latest->upper_band ? round($latest->upper_band, 2) : null,
                        'lower_band' => $latest->lower_band ? round($latest->lower_band, 2) : null,
                    ];
                }
            }

            // Sort by symbol name
            usort($latestData, function($a, $b) {
                return strcmp($a['symbol'], $b['symbol']);
            });

            return response()->json([
                'success' => true,
                'mode' => 'latest',
                'interval' => $interval,
                'data' => $latestData,
                'message' => 'Latest data for all symbols retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Fetch Filtered Data for Specific Symbol
     */
    public function analysisFetch(Request $request)
    {
        try {
            $tradingSymbol = $request->get('trading_symbol');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $interval = '15minute'; // Fixed

            if (!$tradingSymbol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a trading symbol',
                    'data' => []
                ]);
            }

            $query = SymbolData::where('trading_symbol', $tradingSymbol)
                ->where('interval', $interval)
                ->whereNotNull('atr')
                ->whereNotNull('supertrend')
                ->whereNotNull('ma50')
                ->orderBy('timestamp', 'DESC');

            if ($fromDate) {
                $query->where('timestamp', '>=', Carbon::parse($fromDate)->startOfDay());
            }
            
            if ($toDate) {
                $query->where('timestamp', '<=', Carbon::parse($toDate)->endOfDay());
            }

            if (!$fromDate && !$toDate) {
                $query->whereDate('timestamp', Carbon::today());
            }

            $records = $query->limit(500)->get();

            if ($records->isEmpty()) {
                $earliest = SymbolData::where('trading_symbol', $tradingSymbol)
                    ->where('interval', $interval)
                    ->min('timestamp');
                
                $latest = SymbolData::where('trading_symbol', $tradingSymbol)
                    ->where('interval', $interval)
                    ->max('timestamp');

                $debugMessage = 'No data found for the selected filters.';
                if ($earliest && $latest) {
                    $debugMessage .= " Available data range: {$earliest} to {$latest}";
                }

                return response()->json([
                    'success' => false,
                    'message' => $debugMessage,
                    'data' => []
                ]);
            }

            $processedData = $records->reverse()->values()->map(function ($item) {
                return [
                    'date' => $item->timestamp->format('Y-m-d H:i:s'),
                    'symbol' => $item->trading_symbol,
                    'open' => (float)$item->open,
                    'high' => (float)$item->high,
                    'low' => (float)$item->low,
                    'close' => (float)$item->close,
                    'volume' => (int)$item->volume,
                    'atr' => $item->atr ? round($item->atr, 4) : null,
                    'supertrend' => $item->supertrend ? round($item->supertrend, 2) : null,
                    'direction' => $item->supertrend_direction,
                    'signal' => $item->supertrend_signal,
                    'ma50' => $item->ma50 ? round($item->ma50, 2) : null,
                    'upper_band' => $item->upper_band ? round($item->upper_band, 2) : null,
                    'lower_band' => $item->lower_band ? round($item->lower_band, 2) : null,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'mode' => 'filtered',
                'trading_symbol' => $tradingSymbol,
                'interval' => $interval,
                'data' => $processedData,
                'message' => 'Data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Export to CSV
     */
    public function export(Request $request)
    {
        $symbol = $request->get('symbol');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $interval = '15minute';

        $query = SymbolData::query()
            ->where('interval', $interval)
            ->orderBy('timestamp', 'asc');

        if ($symbol) {
            $query->where('trading_symbol', 'LIKE', '%' . strtoupper($symbol) . '%');
        }

        if ($fromDate) {
            $query->where('timestamp', '>=', $fromDate . ' 00:00:00');
        }

        if ($toDate) {
            $query->where('timestamp', '<=', $toDate . ' 23:59:59');
        }

        $data = $query->get();

        $filename = 'symbols_analysis_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Timestamp',
            'Symbol',
            'Interval',
            'Open',
            'High',
            'Low',
            'Close',
            'Volume',
            'ATR',
            'Supertrend',
            'ST Direction',
            'ST Signal',
            'MA50',
            'Upper Band',
            'Lower Band'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row->timestamp,
                $row->trading_symbol,
                $row->interval,
                $row->open,
                $row->high,
                $row->low,
                $row->close,
                $row->volume,
                $row->atr,
                $row->supertrend,
                $row->supertrend_direction,
                $row->supertrend_signal,
                $row->ma50,
                $row->upper_band,
                $row->lower_band
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Manual trigger for fetching data
     */
    public function manualFetchDaily(Request $request)
    {
        try {
            $lockFile = storage_path('app/symbols_fetch.lock');
            
            if (file_exists($lockFile)) {
                $lockTime = filemtime($lockFile);
                $timeDiff = time() - $lockTime;
                
                if ($timeDiff > 600) {
                    unlink($lockFile);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data fetch is already running. Please wait...'
                    ], 429);
                }
            }

            file_put_contents($lockFile, date('Y-m-d H:i:s'));

            // Run 15-min command only
            \Artisan::call('symbols:fetch-15min', ['--force' => true]);
            $output = \Artisan::output();

            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => true,
                'message' => '15-minute data fetched successfully!',
                'output' => $output
            ]);

        } catch (\Exception $e) {
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Backtesting Page
     */
    public function backtesting()
    {
        $pageTitle = 'Backtesting Analysis - Supertrend + 50 MA';
        
        // Get monitored symbols (15-minute only)
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->where('interval', '15minute')
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.symbols.backtesting', compact('pageTitle', 'monitoredSymbols'));
    }
   
    public function backtestingFetch(Request $request)
    {
        try {
            \Log::info('=== BACKTESTING START ===', [
                'all_inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $interval = '15minute'; // Fixed
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

            // Get symbols
            $symbolsQuery = SymbolMonitored::where('is_active', true)
                ->where('interval', $interval);
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

            // Exclude symbols without options
            $excludedSymbols = ['NIFTYNXT5026JANFUT'];
            
            $symbols = array_values(array_filter($symbols, function($symbol) use ($excludedSymbols) {
                if (in_array($symbol, $excludedSymbols)) {
                    return false;
                }
                
                if (strpos($symbol, 'NIFTYNXT50') !== false) {
                    return false;
                }
                
                return true;
            }));

            \Log::info('Processing Symbols', [
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

            $allOrderTriggers = [];

            foreach ($symbols as $symbol) {
                \Log::info("════════ Processing: {$symbol} ════════");

                $candles = SymbolData::where('trading_symbol', $symbol)
                    ->where('interval', $interval)
                    ->where('timestamp', '>=', $startDateTime)
                    ->where('timestamp', '<=', $endDateTime)
                    ->whereNotNull('atr')
                    ->whereNotNull('supertrend')
                    ->whereNotNull('ma50')
                    ->orderBy('timestamp', 'ASC')
                    ->get();

                \Log::info("Found {$candles->count()} candles for {$symbol}");

                if ($candles->count() < 2) {
                    \Log::warning("Not enough data for {$symbol}");
                    continue;
                }

                // Find sync points (signal changes)
                $syncPoints = $this->findBacktestSyncPoints($candles, $symbol);

                \Log::info("Found " . count($syncPoints) . " sync points for {$symbol}");

                // Filter to get valid alternating BUY/SELL
                $validTriggers = $this->filterValidOrderTriggers($syncPoints, $symbol, $optionSeries);

                \Log::info("Valid order triggers for {$symbol}: " . count($validTriggers));

                $allOrderTriggers = array_merge($allOrderTriggers, $validTriggers);
            }

            // Sort by timestamp
            usort($allOrderTriggers, function($a, $b) {
                return strtotime($a['signal_time']) - strtotime($b['signal_time']);
            });

            \Log::info('=== BACKTESTING COMPLETE ===', [
                'total_triggers' => count($allOrderTriggers)
            ]);

            return response()->json([
                'success' => true,
                'data' => $allOrderTriggers,
                'total_signals' => count($allOrderTriggers),
                'message' => count($allOrderTriggers) . ' order triggers found'
            ]);

        } catch (\Exception $e) {
            \Log::error('Backtesting Error', [
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
     * Find signal change points (from persistent signals in DB)
     */
    private function findBacktestSyncPoints($candles, $tradingSymbol)
    {
        $syncPoints = [];
        $lastSignal = null;

        foreach ($candles as $candle) {
            $currentSignal = $candle->supertrend_signal; // Already persistent from DB

            // Detect signal changes (BUY → SELL or SELL → BUY)
            if ($lastSignal !== null && $currentSignal !== $lastSignal && in_array($currentSignal, ['BUY', 'SELL'])) {
                $syncPoints[] = [
                    'timestamp' => $candle->timestamp,
                    'signal_type' => $currentSignal,
                    'future_price' => $candle->close,
                    'ma50' => $candle->ma50,
                    'atr' => $candle->atr
                ];
                
                \Log::info("✅ Signal change: {$lastSignal} → {$currentSignal} at {$candle->timestamp}");
            }

            $lastSignal = $currentSignal;
        }

        return $syncPoints;
    }

    /**
     * Filter valid alternating BUY/SELL triggers with option strike selection data
     */
    private function filterValidOrderTriggers($syncPoints, $symbol, $optionSeries = 'current')
    {
        if (empty($syncPoints)) {
            return [];
        }

        $validTriggers = [];
        $lastSignalType = null;

        foreach ($syncPoints as $point) {
            // Skip duplicates
            if ($lastSignalType === $point['signal_type']) {
                \Log::info("⏭️ Skipping duplicate {$point['signal_type']} at {$point['timestamp']}");
                continue;
            }

            \Log::info("✅ Valid order trigger: {$point['signal_type']} at {$point['timestamp']}");

            // ✅ Get trade date from the signal timestamp
            $tradeDate = $point['timestamp']->format('Y-m-d');
            
            // ✅ Fetch option info from pre-selected strikes table
            $optionInfo = $this->getBacktestOptionSymbol(
                $symbol, 
                $point['signal_type'], 
                $point['future_price'], 
                $optionSeries,
                $tradeDate
            );

            $validTriggers[] = [
                'future_symbol' => $symbol,
                'signal_time' => $point['timestamp']->format('Y-m-d H:i:s'),
                'signal_type' => $point['signal_type'],
                'future_price' => round($point['future_price'], 2),
                'ma50' => round($point['ma50'], 2),
                'atr' => round($point['atr'], 4),
                
                // ✅ Option details from pre-selection
                'option_type' => $optionInfo['option_type'],
                'option_symbol' => $optionInfo['option_symbol'],
                'strike_price' => $optionInfo['strike_price'],
                
                // ✅ Fair price, OI, and valuation from option_strike_selections
                'fair_price' => $optionInfo['fair_price'] ?? null,
                'ltp' => $optionInfo['ltp'] ?? null,
                'oi' => $optionInfo['oi'] ?? 0,
                'valuation' => $optionInfo['valuation'] ?? 'N/A',
                'recommendation' => $optionInfo['recommendation'] ?? 'N/A',
                
                'order_action' => 'Would place CE/PE BUY order'
            ];

            $lastSignalType = $point['signal_type'];
        }

        return $validTriggers;
    }

    /**
     * Get option symbol from pre-selected strikes (option_strike_selections table)
     */
    private function getBacktestOptionSymbol($futureSymbol, $signalType, $futurePrice, $optionSeries, $tradeDate)
    {
        try {
            // ✅ Look up pre-selected strike from morning calculation
            $selection = \App\Models\OptionStrikeSelection::where('trade_date', $tradeDate)
                ->where('future_symbol', $futureSymbol)
                ->where('option_series', $optionSeries)
                ->first();

            if ($selection) {
                if ($signalType === 'BUY') {
                    // ✅ Return CE option with all data
                    return [
                        'option_type' => 'CE',
                        'option_symbol' => $selection->selected_ce_symbol,
                        'strike_price' => $selection->selected_ce_strike,
                        'fair_price' => $selection->selected_ce_fair_price,
                        'ltp' => $selection->ce_atm_ltp, // Or selected_ce_ltp if available
                        'oi' => $selection->selected_ce_oi,
                        'valuation' => $selection->selected_ce_valuation,
                        'recommendation' => $selection->selected_ce_recommendation,
                    ];
                } else {
                    // ✅ Return PE option with all data
                    return [
                        'option_type' => 'PE',
                        'option_symbol' => $selection->selected_pe_symbol,
                        'strike_price' => $selection->selected_pe_strike,
                        'fair_price' => $selection->selected_pe_fair_price,
                        'ltp' => $selection->pe_atm_ltp, // Or selected_pe_ltp if available
                        'oi' => $selection->selected_pe_oi,
                        'valuation' => $selection->selected_pe_valuation,
                        'recommendation' => $selection->selected_pe_recommendation,
                    ];
                }
            }

            // ✅ Fallback to old logic if no pre-selection found
            \Log::warning("No pre-selection found for {$futureSymbol} on {$tradeDate}, using fallback");
            return $this->getBacktestOptionSymbolFallback($futureSymbol, $signalType, $futurePrice, $optionSeries);

        } catch (\Exception $e) {
            \Log::error("Error getting option from pre-selection: " . $e->getMessage());
            return $this->getBacktestOptionSymbolFallback($futureSymbol, $signalType, $futurePrice, $optionSeries);
        }
    }

    /**
     * Fallback method when option_strike_selections data not available
     */
    private function getBacktestOptionSymbolFallback($futureSymbol, $signalType, $futurePrice, $optionSeries)
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
            
            $query = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
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
                // Try nearest strike
                $query = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike]);

                if ($optionSeries === 'next') {
                    $query->orderBy('expiry', 'ASC')->orderBy('strike_diff', 'ASC')->skip(1)->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if ($option) {
                return [
                    'option_type' => $optionType,
                    'option_symbol' => $option->trading_symbol,
                    'strike_price' => $option->strike,
                    'fair_price' => null,
                    'ltp' => null,
                    'oi' => 0,
                    'valuation' => 'N/A',
                    'recommendation' => 'N/A',
                ];
            }

            return [
                'option_type' => $optionType,
                'option_symbol' => $baseSymbol . $calculatedStrike . $optionType,
                'strike_price' => $calculatedStrike,
                'fair_price' => null,
                'ltp' => null,
                'oi' => 0,
                'valuation' => 'N/A',
                'recommendation' => 'N/A',
            ];

        } catch (\Exception $e) {
            \Log::error("Fallback error: " . $e->getMessage());
            return [
                'option_type' => $signalType == 'BUY' ? 'CE' : 'PE',
                'option_symbol' => 'N/A',
                'strike_price' => 0,
                'fair_price' => null,
                'ltp' => null,
                'oi' => 0,
                'valuation' => 'N/A',
                'recommendation' => 'N/A',
            ];
        }
    }

}