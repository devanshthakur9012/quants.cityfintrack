<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\IndicatorConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SymbolController extends Controller
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

    public function analysisLatest(Request $request)
    {
        try {
            $interval = '15minute';

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
                        
                        // ✅ NEW: Event signal vs Position
                        'event_signal' => $latest->supertrend_event_signal, // BUY/SELL/null
                        'position' => $latest->trade_position,              // LONG/SHORT/FLAT
                        
                        // Legacy field for backward compatibility
                        'signal' => $latest->supertrend_event_signal ?? 'HOLD',
                        
                        'ma50' => $latest->ma50 ? round($latest->ma50, 2) : null,
                        'upper_band' => $latest->upper_band ? round($latest->upper_band, 2) : null,
                        'lower_band' => $latest->lower_band ? round($latest->lower_band, 2) : null,
                    ];
                }
            }

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

    public function analysisFetch(Request $request)
    {
        try {
            $tradingSymbol = $request->get('trading_symbol');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $interval = '15minute';

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
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for the selected filters.',
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
                    
                    // ✅ NEW: Show both event signal and position
                    'event_signal' => $item->supertrend_event_signal,
                    'position' => $item->trade_position,
                    
                    // ✅ FIXED: Changed $latest to $item
                    'signal' => $item->supertrend_event_signal ?? 'HOLD',
                    
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
            'Event Signal',
            'Position',
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
                $row->supertrend_event_signal,
                $row->trade_position,
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
     * ✅ FIXED: Find event-based signal points (BUY/SELL crossovers only)
     * 
     * This method now correctly uses supertrend_event_signal which is only set
     * on actual crossover candles (not on every candle like the old system)
     */
    private function findBacktestSyncPoints($candles, $tradingSymbol)
    {
        $syncPoints = [];
        $lastSignal = null;

        foreach ($candles as $candle) {
            // 🔥 NEW: Use event_signal which is only BUY/SELL on crossover
            $eventSignal = $candle->supertrend_event_signal;
            
            // Only process if there's an actual event signal
            if (in_array($eventSignal, ['BUY', 'SELL'])) {
                
                // Extra safety: avoid duplicate consecutive signals (shouldn't happen but good to check)
                if ($eventSignal !== $lastSignal) {
                    $syncPoints[] = [
                        'timestamp' => $candle->timestamp,
                        'signal_type' => $eventSignal,
                        'future_price' => $candle->close,
                        'ma50' => $candle->ma50,
                        'atr' => $candle->atr
                    ];
                    
                    \Log::info("🎯 Event signal detected: {$eventSignal} at {$candle->timestamp} | Price: {$candle->close}");
                    
                    $lastSignal = $eventSignal;
                }
            }
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
            if ($lastSignalType === $point['signal_type']) {
                \Log::info("⏭️ Skipping duplicate {$point['signal_type']} at {$point['timestamp']}");
                continue;
            }

            \Log::info("✅ Valid order trigger: {$point['signal_type']} at {$point['timestamp']}");

            // ✅ Pass the full timestamp (not just date)
            $optionInfo = $this->getBacktestOptionSymbol(
                $symbol, 
                $point['signal_type'], 
                $point['future_price'], 
                $optionSeries,
                $point['timestamp']  // ✅ Pass full timestamp
            );

            $validTriggers[] = [
                'future_symbol' => $symbol,
                'signal_time' => $point['timestamp']->format('Y-m-d H:i:s'),
                'signal_type' => $point['signal_type'],
                'future_price' => round($point['future_price'], 2),
                'ma50' => round($point['ma50'], 2),
                'atr' => round($point['atr'], 4),
                
                'option_type' => $optionInfo['option_type'],
                'option_symbol' => $optionInfo['option_symbol'],
                'strike_price' => $optionInfo['strike_price'],
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
     * ✅ FIXED: Get option data for the EXACT signal time interval
     */
    private function getBacktestOptionSymbol($futureSymbol, $signalType, $futurePrice, $optionSeries, $signalTimestamp)
    {
        try {
            // ✅ Parse the signal timestamp
            $signalTime = Carbon::parse($signalTimestamp);
            $tradeDate = $signalTime->format('Y-m-d');
            
            // ✅ Round signal time to nearest 15-minute interval
            $intervalTime = $this->roundToNearest15Minutes($signalTime);
            
            \Log::info("Looking for option data", [
                'future_symbol' => $futureSymbol,
                'signal_time' => $signalTime->format('Y-m-d H:i:s'),
                'interval_time' => $intervalTime->format('Y-m-d H:i:s'),
                'trade_date' => $tradeDate,
                'option_series' => $optionSeries
            ]);

            // ✅ Look up pre-selected strike for the EXACT interval time
            $selection = \App\Models\OptionStrikeSelection::where('trade_date', $tradeDate)
                ->where('interval_time', $intervalTime)
                ->where('future_symbol', $futureSymbol)
                ->where('option_series', $optionSeries)
                ->first();

            if ($selection) {
                \Log::info("Found option selection for {$futureSymbol} at {$intervalTime->format('H:i')}");
                
                if ($signalType === 'BUY') {
                    // ✅ Determine which CE strike was selected and get corresponding LTP
                    $selectedStrike = $selection->selected_ce_strike;
                    $ltp = null;
                    
                    if ($selectedStrike == $selection->ce_atm_strike) {
                        $ltp = $selection->ce_atm_ltp;
                    } elseif ($selectedStrike == $selection->ce_atm1_strike) {
                        $ltp = $selection->ce_atm1_ltp;
                    } elseif ($selectedStrike == $selection->ce_atm2_strike) {
                        $ltp = $selection->ce_atm2_ltp;
                    }
                    
                    return [
                        'option_type' => 'CE',
                        'option_symbol' => $selection->selected_ce_symbol,
                        'strike_price' => $selection->selected_ce_strike,
                        'fair_price' => $selection->selected_ce_fair_price,
                        'ltp' => $ltp,
                        'oi' => $selection->selected_ce_oi,
                        'valuation' => $selection->selected_ce_valuation,
                        'recommendation' => $selection->selected_ce_recommendation,
                    ];
                } else {
                    // ✅ Determine which PE strike was selected and get corresponding LTP
                    $selectedStrike = $selection->selected_pe_strike;
                    $ltp = null;
                    
                    if ($selectedStrike == $selection->pe_atm_strike) {
                        $ltp = $selection->pe_atm_ltp;
                    } elseif ($selectedStrike == $selection->pe_atm1_strike) {
                        $ltp = $selection->pe_atm1_ltp;
                    } elseif ($selectedStrike == $selection->pe_atm2_strike) {
                        $ltp = $selection->pe_atm2_ltp;
                    }
                    
                    return [
                        'option_type' => 'PE',
                        'option_symbol' => $selection->selected_pe_symbol,
                        'strike_price' => $selection->selected_pe_strike,
                        'fair_price' => $selection->selected_pe_fair_price,
                        'ltp' => $ltp,
                        'oi' => $selection->selected_pe_oi,
                        'valuation' => $selection->selected_pe_valuation,
                        'recommendation' => $selection->selected_pe_recommendation,
                    ];
                }
            }

            // ✅ Fallback to old logic if no pre-selection found
            \Log::warning("No pre-selection found for {$futureSymbol} at {$intervalTime->format('Y-m-d H:i')}, using fallback");
            return $this->getBacktestOptionSymbolFallback($futureSymbol, $signalType, $futurePrice, $optionSeries);

        } catch (\Exception $e) {
            \Log::error("Error getting option from pre-selection: " . $e->getMessage());
            return $this->getBacktestOptionSymbolFallback($futureSymbol, $signalType, $futurePrice, $optionSeries);
        }
    }

    /**
     * ✅ Helper: Round time to nearest 15-minute interval
     */
    private function roundToNearest15Minutes($time)
    {
        $carbon = Carbon::parse($time);
        $minutes = $carbon->minute;
        $roundedMinutes = floor($minutes / 15) * 15;
        
        return $carbon->copy()
            ->minute($roundedMinutes)
            ->second(0)
            ->microsecond(0);
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

    /**
     * Option Strike Selections Page
     */
    public function optionStrikes()
    {
        $pageTitle = 'Option Strike Selections - Fair Price Analysis';
        
        return view($this->activeTemplate . 'user.symbols.option-strikes', compact('pageTitle'));
    }

    /**
     * Fetch Option Strike Selection Data
     */
    // public function optionStrikesFetch(Request $request)
    // {
    //     try {
    //         $tradeDate = $request->get('trade_date', Carbon::today()->format('Y-m-d'));
    //         $futureSymbol = $request->get('future_symbol');
    //         $intervalTime = $request->get('interval_time');

    //         // Always use 'current' series
    //         $query = \App\Models\OptionStrikeSelection::where('trade_date', $tradeDate)
    //             ->where('option_series', 'current');

    //         if ($futureSymbol) {
    //             $query->where('future_symbol', $futureSymbol);
    //         }

    //         if ($intervalTime) {
    //             $query->where('interval_time', $intervalTime);
    //         }

    //         // Get latest entry for each symbol if no specific interval selected
    //         if (!$intervalTime) {
    //             $selections = $query->orderBy('future_symbol', 'ASC')
    //                 ->orderBy('interval_time', 'DESC')
    //                 ->get()
    //                 ->groupBy('future_symbol')
    //                 ->map(function($group) {
    //                     return $group->first(); // Get the latest entry for each symbol
    //                 })
    //                 ->values();
    //         } else {
    //             $selections = $query->orderBy('interval_time', 'DESC')
    //                 ->orderBy('future_symbol', 'ASC')
    //                 ->get();
    //         }

    //         if ($selections->isEmpty()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'No option strike data found for selected filters',
    //                 'data' => []
    //             ]);
    //         }

    //         $processedData = $selections->map(function ($item) {
    //             return [
    //                 'id' => $item->id,
    //                 'trade_date' => $item->trade_date,
    //                 'interval_time' => $item->interval_time ? Carbon::parse($item->interval_time)->format('H:i') : 'N/A',
    //                 'future_symbol' => $item->future_symbol,
    //                 'base_symbol' => $item->base_symbol,
    //                 'future_price' => $item->future_price,
    //                 'atm_strike' => $item->atm_strike,
    //                 'option_series' => $item->option_series,
    //                 'expiry_date' => $item->expiry_date ? Carbon::parse($item->expiry_date)->format('d M Y') : 'N/A',
                    
    //                 // CE Strikes
    //                 'ce_atm' => [
    //                     'strike' => $item->ce_atm_strike,
    //                     'symbol' => $item->ce_atm_symbol,
    //                     'oi' => $item->ce_atm_oi,
    //                     'fair_price' => $item->ce_atm_fair_price,
    //                     'ltp' => $item->ce_atm_ltp,
    //                     'valuation' => $item->ce_atm_valuation,
    //                 ],
    //                 'ce_atm1' => [
    //                     'strike' => $item->ce_atm1_strike,
    //                     'symbol' => $item->ce_atm1_symbol,
    //                     'oi' => $item->ce_atm1_oi,
    //                     'fair_price' => $item->ce_atm1_fair_price,
    //                     'ltp' => $item->ce_atm1_ltp,
    //                     'valuation' => $item->ce_atm1_valuation,
    //                 ],
    //                 'ce_atm2' => [
    //                     'strike' => $item->ce_atm2_strike,
    //                     'symbol' => $item->ce_atm2_symbol,
    //                     'oi' => $item->ce_atm2_oi,
    //                     'fair_price' => $item->ce_atm2_fair_price,
    //                     'ltp' => $item->ce_atm2_ltp,
    //                     'valuation' => $item->ce_atm2_valuation,
    //                 ],
                    
    //                 // PE Strikes
    //                 'pe_atm' => [
    //                     'strike' => $item->pe_atm_strike,
    //                     'symbol' => $item->pe_atm_symbol,
    //                     'oi' => $item->pe_atm_oi,
    //                     'fair_price' => $item->pe_atm_fair_price,
    //                     'ltp' => $item->pe_atm_ltp,
    //                     'valuation' => $item->pe_atm_valuation,
    //                 ],
    //                 'pe_atm1' => [
    //                     'strike' => $item->pe_atm1_strike,
    //                     'symbol' => $item->pe_atm1_symbol,
    //                     'oi' => $item->pe_atm1_oi,
    //                     'fair_price' => $item->pe_atm1_fair_price,
    //                     'ltp' => $item->pe_atm1_ltp,
    //                     'valuation' => $item->pe_atm1_valuation,
    //                 ],
    //                 'pe_atm2' => [
    //                     'strike' => $item->pe_atm2_strike,
    //                     'symbol' => $item->pe_atm2_symbol,
    //                     'oi' => $item->pe_atm2_oi,
    //                     'fair_price' => $item->pe_atm2_fair_price,
    //                     'ltp' => $item->pe_atm2_ltp,
    //                     'valuation' => $item->pe_atm2_valuation,
    //                 ],
                    
    //                 // Selected Strikes
    //                 'selected_ce' => [
    //                     'symbol' => $item->selected_ce_symbol,
    //                     'strike' => $item->selected_ce_strike,
    //                     'oi' => $item->selected_ce_oi,
    //                     'fair_price' => $item->selected_ce_fair_price,
    //                     'valuation' => $item->selected_ce_valuation,
    //                     'recommendation' => $item->selected_ce_recommendation,
    //                 ],
    //                 'selected_pe' => [
    //                     'symbol' => $item->selected_pe_symbol,
    //                     'strike' => $item->selected_pe_strike,
    //                     'oi' => $item->selected_pe_oi,
    //                     'fair_price' => $item->selected_pe_fair_price,
    //                     'valuation' => $item->selected_pe_valuation,
    //                     'recommendation' => $item->selected_pe_recommendation,
    //                 ],
    //             ];
    //         })->toArray();

    //         return response()->json([
    //             'success' => true,
    //             'data' => $processedData,
    //             'total_records' => count($processedData),
    //             'message' => count($processedData) . ' records found'
    //         ]);

    //     } catch (\Exception $e) {
    //         \Log::error('Option Strikes Fetch Error: ' . $e->getMessage());
            
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => []
    //         ], 500);
    //     }
    // }
    /**
     * Fetch Option Strike Selection Data
     */
    public function optionStrikesFetch(Request $request)
    {
        try {
            $tradeDate = $request->get('trade_date', Carbon::today()->format('Y-m-d'));
            $futureSymbol = $request->get('future_symbol');
            $intervalTime = $request->get('interval_time');

            // Always use 'current' series
            $query = \App\Models\OptionStrikeSelection::where('trade_date', $tradeDate)
                ->where('option_series', 'current');

            if ($futureSymbol) {
                $query->where('future_symbol', $futureSymbol);
            }

            if ($intervalTime) {
                $query->where('interval_time', $intervalTime);
            }

            // ✅ NEW LOGIC: If specific symbol selected, show ALL records
            // If no symbol selected, show latest entry per symbol
            if ($futureSymbol) {
                // Show all records for selected symbol, ordered by time DESC
                $selections = $query->orderBy('interval_time', 'DESC')->get();
            } elseif (!$intervalTime) {
                // No symbol selected + no interval = show latest entry per symbol
                $selections = $query->orderBy('future_symbol', 'ASC')
                    ->orderBy('interval_time', 'DESC')
                    ->get()
                    ->groupBy('future_symbol')
                    ->map(function($group) {
                        return $group->first();
                    })
                    ->values();
            } else {
                // Specific interval selected = show all symbols for that interval
                $selections = $query->orderBy('future_symbol', 'ASC')->get();
            }

            if ($selections->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No option strike data found for selected filters',
                    'data' => []
                ]);
            }

            $processedData = $selections->map(function ($item) {
                // ✅ Determine selected CE and PE LTP
                $selectedCELtp = null;
                $selectedPELtp = null;

                // Find CE LTP based on selected strike
                if ($item->selected_ce_strike == $item->ce_atm_strike) {
                    $selectedCELtp = $item->ce_atm_ltp;
                } elseif ($item->selected_ce_strike == $item->ce_atm1_strike) {
                    $selectedCELtp = $item->ce_atm1_ltp;
                } elseif ($item->selected_ce_strike == $item->ce_atm2_strike) {
                    $selectedCELtp = $item->ce_atm2_ltp;
                }

                // Find PE LTP based on selected strike
                if ($item->selected_pe_strike == $item->pe_atm_strike) {
                    $selectedPELtp = $item->pe_atm_ltp;
                } elseif ($item->selected_pe_strike == $item->pe_atm1_strike) {
                    $selectedPELtp = $item->pe_atm1_ltp;
                } elseif ($item->selected_pe_strike == $item->pe_atm2_strike) {
                    $selectedPELtp = $item->pe_atm2_ltp;
                }

                return [
                    'id' => $item->id,
                    'trade_date' => $item->trade_date,
                    'interval_time' => $item->interval_time ? Carbon::parse($item->interval_time)->format('H:i') : 'N/A',
                    'future_symbol' => $item->future_symbol,
                    'base_symbol' => $item->base_symbol,
                    'future_price' => $item->future_price,
                    'atm_strike' => $item->atm_strike,
                    'option_series' => $item->option_series,
                    'expiry_date' => $item->expiry_date ? Carbon::parse($item->expiry_date)->format('d M Y') : 'N/A',
                    
                    // Selected Strikes with LTP
                    'selected_ce' => [
                        'symbol' => $item->selected_ce_symbol,
                        'strike' => $item->selected_ce_strike,
                        'oi' => $item->selected_ce_oi,
                        'fair_price' => $item->selected_ce_fair_price,
                        'ltp' => $selectedCELtp, // ✅ Added
                        'valuation' => $item->selected_ce_valuation,
                        'recommendation' => $item->selected_ce_recommendation,
                    ],
                    'selected_pe' => [
                        'symbol' => $item->selected_pe_symbol,
                        'strike' => $item->selected_pe_strike,
                        'oi' => $item->selected_pe_oi,
                        'fair_price' => $item->selected_pe_fair_price,
                        'ltp' => $selectedPELtp, // ✅ Added
                        'valuation' => $item->selected_pe_valuation,
                        'recommendation' => $item->selected_pe_recommendation,
                    ],
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $processedData,
                'total_records' => count($processedData),
                'message' => count($processedData) . ' records found'
            ]);

        } catch (\Exception $e) {
            \Log::error('Option Strikes Fetch Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get unique symbols for filter dropdown
     */
    public function optionStrikesSymbols(Request $request)
    {
        try {
            $tradeDate = $request->get('trade_date', Carbon::today()->format('Y-m-d'));
            
            $symbols = \App\Models\OptionStrikeSelection::where('trade_date', $tradeDate)
                ->where('option_series', 'current')
                ->distinct()
                ->pluck('future_symbol')
                ->sort()
                ->values();

            return response()->json([
                'success' => true,
                'symbols' => $symbols
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}