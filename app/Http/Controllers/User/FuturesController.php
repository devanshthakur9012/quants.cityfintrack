<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use App\Models\IndicatorConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FuturesController extends Controller
{
    /**
     * Supertrend Analysis Page
     */
    public function supertrendAnalysis()
    {
        $pageTitle = 'Technical Analysis - Futures';
        $monitoredFutures = FuturesMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.futures.supertrend-analysis', compact('pageTitle', 'monitoredFutures'));
    }

    /**
     * Fetch Supertrend Data (AJAX) - UPDATED WITH VWAP
     */
    // public function supertrendFetch(Request $request)
    // {
    //     try {
    //         $tradingSymbol = $request->get('trading_symbol');
    //         $interval = $request->get('interval', '15minute');
    //         $fromDate = $request->get('from_date');
    //         $toDate = $request->get('to_date');

    //         if (!$tradingSymbol) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Please select a trading symbol',
    //                 'data' => []
    //             ]);
    //         }

    //         $query = FuturesData::where('trading_symbol', $tradingSymbol)
    //             ->where('interval', $interval)
    //             ->orderBy('timestamp', 'DESC');

    //         if ($fromDate) {
    //             $query->where('timestamp', '>=', Carbon::parse($fromDate)->startOfDay());
    //         }
            
    //         if ($toDate) {
    //             $query->where('timestamp', '<=', Carbon::parse($toDate)->endOfDay());
    //         }

    //         if (!$fromDate && !$toDate) {
    //             $query->whereDate('timestamp', Carbon::today());
    //         }

    //         $records = $query->limit(500)->get();

    //         if ($records->isEmpty()) {
    //             $earliest = FuturesData::where('trading_symbol', $tradingSymbol)
    //                 ->where('interval', $interval)
    //                 ->min('timestamp');
                
    //             $latest = FuturesData::where('trading_symbol', $tradingSymbol)
    //                 ->where('interval', $interval)
    //                 ->max('timestamp');

    //             $debugMessage = 'No data found for the selected filters.';
    //             if ($earliest && $latest) {
    //                 $debugMessage .= " Available data range: {$earliest} to {$latest}";
    //             }

    //             return response()->json([
    //                 'success' => false,
    //                 'message' => $debugMessage,
    //                 'data' => []
    //             ]);
    //         }

    //         $validRecords = $records->filter(function ($item) {
    //             return $item->atr !== null && 
    //                    $item->supertrend !== null && 
    //                    $item->supertrend_direction !== null;
    //         });

    //         $supertrendData = $validRecords->reverse()->values()->map(function ($item) {
    //             return [
    //                 'date' => $item->timestamp->format('Y-m-d H:i:s'),
    //                 'symbol' => $item->trading_symbol,
    //                 'open' => (float)$item->open,
    //                 'high' => (float)$item->high,
    //                 'low' => (float)$item->low,
    //                 'close' => (float)$item->close,
    //                 'volume' => (int)$item->volume,
    //                 'atr' => $item->atr ? round($item->atr, 4) : null,
    //                 'supertrend' => $item->supertrend ? round($item->supertrend, 2) : null,
    //                 'direction' => $item->supertrend_direction,
    //                 'signal' => $item->supertrend_signal,
    //                 'upper_band' => $item->upper_band ? round($item->upper_band, 2) : null,
    //                 'lower_band' => $item->lower_band ? round($item->lower_band, 2) : null,
    //                 // Donchian
    //                 'donchian_signal' => $item->donchian_signal ?? 'NO_TRADE',
    //                 'donchian_entry' => $item->donchian_entry ?? null,
    //                 'donchian_sl' => $item->donchian_sl ?? null,
    //                 'donchian_target' => $item->donchian_target ?? null,
    //                 'donchian_upper' => $item->donchian_upper ?? null,
    //                 'donchian_lower' => $item->donchian_lower ?? null,
    //                 'donchian_middle' => $item->donchian_middle ?? null,
    //                 // RSI
    //                 'rsi' => $item->rsi ?? null,
    //                 'rsi_signal' => $item->rsi_signal ?? 'NEUTRAL',
    //                 // MACD
    //                 'macd_line' => $item->macd_line ?? null,
    //                 'macd_signal_line' => $item->macd_signal_line ?? null,
    //                 'macd_histogram' => $item->macd_histogram ?? null,
    //                 'macd_signal' => $item->macd_signal ?? 'HOLD',
    //                 // VWAP
    //                 'vwap' => $item->vwap ? round($item->vwap, 2) : null,
    //                 'vwap_signal' => $item->vwap_signal ?? 'HOLD',
    //                 'vwap_upper_band' => $item->vwap_upper_band ? round($item->vwap_upper_band, 2) : null,
    //                 'vwap_lower_band' => $item->vwap_lower_band ? round($item->vwap_lower_band, 2) : null,
    //             ];
    //         })->toArray();

    //         return response()->json([
    //             'success' => true,
    //             'trading_symbol' => $tradingSymbol,
    //             'interval' => $interval,
    //             'data' => $supertrendData,
    //             'message' => 'Data retrieved successfully'
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error: ' . $e->getMessage(),
    //             'data' => []
    //         ], 500);
    //     }
    // }

    /**
     * Export to CSV - UPDATED WITH VWAP
     */
    // public function export(Request $request)
    // {
    //     $symbol = $request->get('symbol');
    //     $interval = $request->get('interval', '15minute');
    //     $fromDate = $request->get('from_date');
    //     $toDate = $request->get('to_date');

    //     $query = FuturesData::query()
    //         ->where('interval', $interval)
    //         ->orderBy('timestamp', 'asc');

    //     if ($symbol) {
    //         $query->where('trading_symbol', 'LIKE', '%' . strtoupper($symbol) . '%');
    //     }

    //     if ($fromDate) {
    //         $query->where('timestamp', '>=', $fromDate . ' 00:00:00');
    //     }

    //     if ($toDate) {
    //         $query->where('timestamp', '<=', $toDate . ' 23:59:59');
    //     }

    //     $data = $query->get();

    //     $filename = 'futures_analysis_' . date('Y-m-d_His') . '.csv';
        
    //     header('Content-Type: text/csv');
    //     header('Content-Disposition: attachment; filename="' . $filename . '"');

    //     $output = fopen('php://output', 'w');

    //     fputcsv($output, [
    //         'Timestamp',
    //         'Symbol',
    //         'Interval',
    //         'Open',
    //         'High',
    //         'Low',
    //         'Close',
    //         'Volume',
    //         'OI',
    //         // Supertrend
    //         'ATR',
    //         'Supertrend',
    //         'ST Direction',
    //         'ST Signal',
    //         'Upper Band',
    //         'Lower Band',
    //         // Donchian
    //         'Donchian Signal',
    //         'Donchian Entry',
    //         'Donchian SL',
    //         'Donchian Target',
    //         'Donchian Upper',
    //         'Donchian Lower',
    //         'Donchian Middle',
    //         // RSI
    //         'RSI',
    //         'RSI Signal',
    //         // MACD
    //         'MACD Line',
    //         'MACD Signal Line',
    //         'MACD Histogram',
    //         'MACD Signal',
    //         // VWAP
    //         'VWAP',
    //         'VWAP Signal',
    //         'VWAP Upper Band',
    //         'VWAP Lower Band'
    //     ]);

    //     foreach ($data as $row) {
    //         fputcsv($output, [
    //             $row->timestamp,
    //             $row->trading_symbol,
    //             $row->interval,
    //             $row->open,
    //             $row->high,
    //             $row->low,
    //             $row->close,
    //             $row->volume,
    //             $row->oi,
    //             // Supertrend
    //             $row->atr,
    //             $row->supertrend,
    //             $row->supertrend_direction,
    //             $row->supertrend_signal,
    //             $row->upper_band,
    //             $row->lower_band,
    //             // Donchian
    //             $row->donchian_signal,
    //             $row->donchian_entry,
    //             $row->donchian_sl,
    //             $row->donchian_target,
    //             $row->donchian_upper,
    //             $row->donchian_lower,
    //             $row->donchian_middle,
    //             // RSI
    //             $row->rsi,
    //             $row->rsi_signal,
    //             // MACD
    //             $row->macd_line,
    //             $row->macd_signal_line,
    //             $row->macd_histogram,
    //             $row->macd_signal,
    //             // VWAP
    //             $row->vwap,
    //             $row->vwap_signal,
    //             $row->vwap_upper_band,
    //             $row->vwap_lower_band
    //         ]);
    //     }

    //     fclose($output);
    //     exit;
    // }

     /**
     * Fetch Supertrend Data (AJAX) - UPDATED WITH PERSISTENT VWAP
     */
    public function supertrendFetch(Request $request)
    {
        try {
            $tradingSymbol = $request->get('trading_symbol');
            $interval = $request->get('interval', '15minute');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            if (!$tradingSymbol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a trading symbol',
                    'data' => []
                ]);
            }

            $query = FuturesData::where('trading_symbol', $tradingSymbol)
                ->where('interval', $interval)
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
                $earliest = FuturesData::where('trading_symbol', $tradingSymbol)
                    ->where('interval', $interval)
                    ->min('timestamp');
                
                $latest = FuturesData::where('trading_symbol', $tradingSymbol)
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

            $validRecords = $records->filter(function ($item) {
                return $item->atr !== null && 
                       $item->supertrend !== null && 
                       $item->supertrend_direction !== null;
            });

            // Apply persistent signal logic for VWAP (similar to Supertrend)
            $processedData = $this->applyPersistentVWAPSignals($validRecords->reverse()->values());

            $supertrendData = $processedData->map(function ($item) {
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
                    'upper_band' => $item->upper_band ? round($item->upper_band, 2) : null,
                    'lower_band' => $item->lower_band ? round($item->lower_band, 2) : null,
                    // Donchian
                    'donchian_signal' => $item->donchian_signal ?? 'NO_TRADE',
                    'donchian_entry' => $item->donchian_entry ?? null,
                    'donchian_sl' => $item->donchian_sl ?? null,
                    'donchian_target' => $item->donchian_target ?? null,
                    'donchian_upper' => $item->donchian_upper ?? null,
                    'donchian_lower' => $item->donchian_lower ?? null,
                    'donchian_middle' => $item->donchian_middle ?? null,
                    // RSI
                    'rsi' => $item->rsi ?? null,
                    'rsi_signal' => $item->rsi_signal ?? 'NEUTRAL',
                    // MACD
                    'macd_line' => $item->macd_line ?? null,
                    'macd_signal_line' => $item->macd_signal_line ?? null,
                    'macd_histogram' => $item->macd_histogram ?? null,
                    'macd_signal' => $item->macd_signal ?? 'HOLD',
                    // VWAP - Now with persistent signal
                    'vwap' => $item->vwap ? round($item->vwap, 2) : null,
                    'vwap_signal' => $item->vwap_persistent_signal ?? 'HOLD',
                    'vwap_upper_band' => $item->vwap_upper_band ? round($item->vwap_upper_band, 2) : null,
                    'vwap_lower_band' => $item->vwap_lower_band ? round($item->vwap_lower_band, 2) : null,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'trading_symbol' => $tradingSymbol,
                'interval' => $interval,
                'data' => $supertrendData,
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
     * Apply persistent VWAP signals (like Supertrend)
     * When GAP_UP signal occurs, continue showing BUY until GAP_DOWN
     * When GAP_DOWN signal occurs, continue showing SELL until GAP_UP
     */
    private function applyPersistentVWAPSignals($records)
    {
        $currentVwapSignal = null;
        
        foreach ($records as $record) {
            $originalSignal = $record->vwap_signal ?? 'HOLD';
            
            // Check if this is a fresh signal change
            if ($originalSignal === 'GAP_UP') {
                $currentVwapSignal = 'BUY';
            } elseif ($originalSignal === 'GAP_DOWN') {
                $currentVwapSignal = 'SELL';
            }
            
            // If we have an active signal, continue showing it
            if ($currentVwapSignal !== null) {
                $record->vwap_persistent_signal = $currentVwapSignal;
            } else {
                $record->vwap_persistent_signal = 'HOLD';
            }
        }
        
        return $records;
    }

    /**
     * Export to CSV - UPDATED WITH VWAP
     */
    public function export(Request $request)
    {
        $symbol = $request->get('symbol');
        $interval = $request->get('interval', '15minute');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = FuturesData::query()
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

        $filename = 'futures_analysis_' . date('Y-m-d_His') . '.csv';
        
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
            'OI',
            // Supertrend
            'ATR',
            'Supertrend',
            'ST Direction',
            'ST Signal',
            'Upper Band',
            'Lower Band',
            // Donchian
            'Donchian Signal',
            'Donchian Entry',
            'Donchian SL',
            'Donchian Target',
            'Donchian Upper',
            'Donchian Lower',
            'Donchian Middle',
            // RSI
            'RSI',
            'RSI Signal',
            // MACD
            'MACD Line',
            'MACD Signal Line',
            'MACD Histogram',
            'MACD Signal',
            // VWAP
            'VWAP',
            'VWAP Signal',
            'VWAP Upper Band',
            'VWAP Lower Band'
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
                $row->oi,
                // Supertrend
                $row->atr,
                $row->supertrend,
                $row->supertrend_direction,
                $row->supertrend_signal,
                $row->upper_band,
                $row->lower_band,
                // Donchian
                $row->donchian_signal,
                $row->donchian_entry,
                $row->donchian_sl,
                $row->donchian_target,
                $row->donchian_upper,
                $row->donchian_lower,
                $row->donchian_middle,
                // RSI
                $row->rsi,
                $row->rsi_signal,
                // MACD
                $row->macd_line,
                $row->macd_signal_line,
                $row->macd_histogram,
                $row->macd_signal,
                // VWAP
                $row->vwap,
                $row->vwap_signal,
                $row->vwap_upper_band,
                $row->vwap_lower_band
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
            $lockFile = storage_path('app/futures_fetch.lock');
            
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

            // Run all interval commands
            \Artisan::call('futures:fetch-1min', ['--force' => true]);
            $output1min = \Artisan::output();
            
            \Artisan::call('futures:fetch-5min', ['--force' => true]);
            $output5min = \Artisan::output();
            
            \Artisan::call('futures:fetch-15min', ['--force' => true]);
            $output15min = \Artisan::output();

            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully for all timeframes!',
                'output' => "1-Min:\n" . $output1min . "\n\n5-Min:\n" . $output5min . "\n\n15-Min:\n" . $output15min
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
        $pageTitle = 'Backtesting Analysis';
        $monitoredFutures = FuturesMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.futures.backtesting', compact('pageTitle', 'monitoredFutures'));
    }

/**
 * Backtesting - Simulate Auto Trading Order Placement
 * NOW SUPPORTS: SUPERTREND, VWAP, RSI, and combinations
 */
public function backtestingFetch(Request $request)
{
    try {
        \Log::info('=== BACKTESTING (ORDER SIMULATION) START ===', [
            'all_inputs' => $request->all()
        ]);

        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $selectedSymbols = $request->get('symbols', []);
        $strategy = $request->get('strategy', 'SUPERTREND');
        $interval = $request->get('interval', '15minute');

        \Log::info('Backtest Parameters', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'selected_symbols' => $selectedSymbols,
            'strategy' => $strategy,
            'interval' => $interval
        ]);

        // Validation
        if (!$fromDate || !$toDate) {
            return response()->json([
                'success' => false,
                'message' => 'Please select both From and To dates',
                'data' => []
            ]);
        }

        // Date range - Full day (9:15 AM to 3:30 PM trading hours)
        $startDateTime = $fromDate . ' 09:15:00';
        $endDateTime = $toDate . ' 15:30:00';

        // Get symbols to check
        $symbolsQuery = FuturesMonitored::where('is_active', true);
        if (!empty($selectedSymbols)) {
            $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
        }
        $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

        \Log::info('Processing Symbols', [
            'total' => count($symbols),
            'symbols' => $symbols
        ]);

        $allOrderTriggers = [];

        foreach ($symbols as $symbol) {
            \Log::info("════════ Processing: {$symbol} ════════");

            $candles = FuturesData::where('trading_symbol', $symbol)
                ->where('interval', $interval)
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->whereNotNull('atr')
                ->whereNotNull('supertrend')
                ->whereNotNull('supertrend_direction')
                ->orderBy('timestamp', 'ASC')
                ->get();

            \Log::info("Found {$candles->count()} candles for {$symbol}");

            if ($candles->count() < 2) {
                \Log::warning("Not enough data for {$symbol}");
                continue;
            }

            // Find synchronization points
            $syncPoints = $this->findBacktestSyncPoints($candles, $symbol, $strategy);

            \Log::info("Found " . count($syncPoints) . " sync points for {$symbol}");

            // Filter to get only valid order triggers (alternating BUY/SELL)
            $validTriggers = $this->filterValidOrderTriggers($syncPoints, $symbol);

            \Log::info("Valid order triggers for {$symbol}: " . count($validTriggers));

            $allOrderTriggers = array_merge($allOrderTriggers, $validTriggers);
        }

        // Sort by timestamp
        usort($allOrderTriggers, function($a, $b) {
            return strtotime($a['signal_time']) - strtotime($b['signal_time']);
        });

        \Log::info('=== BACKTESTING COMPLETE ===', [
            'total_order_triggers' => count($allOrderTriggers)
        ]);

        return response()->json([
            'success' => true,
            'data' => $allOrderTriggers,
            'total_signals' => count($allOrderTriggers),
            'message' => count($allOrderTriggers) . ' order triggers found (would place ' . count($allOrderTriggers) . ' CE/PE orders)'
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
 * Find synchronization points - UPDATED WITH VWAP
 */
private function findBacktestSyncPoints($candles, $tradingSymbol, $strategy)
{
    $syncPoints = [];
    $totalCandles = count($candles);
    
    \Log::info("🔍 Scanning {$totalCandles} candles with strategy: {$strategy}");

    // Track persistent trends
    $currentSupertrendSignal = null;
    $currentVwapSignal = null;
    $currentRsiSignal = null;

    foreach ($candles as $index => $candle) {
        $recordSupertrendSignal = $candle->supertrend_signal;
        $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
        $recordRsiSignal = $candle->rsi_signal ?? 'NEUTRAL';
        $recordDirection = $candle->supertrend_direction;

        // SUPERTREND LOGIC
        $supertrendFresh = false;
        if ($recordSupertrendSignal === 'BUY' && $currentSupertrendSignal !== 'BUY') {
            $supertrendFresh = true;
            $currentSupertrendSignal = 'BUY';
        } elseif ($recordSupertrendSignal === 'SELL' && $currentSupertrendSignal !== 'SELL') {
            $supertrendFresh = true;
            $currentSupertrendSignal = 'SELL';
        } elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'UP' && $currentSupertrendSignal === 'BUY') {
            $currentSupertrendSignal = 'BUY';
        } elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'DOWN' && $currentSupertrendSignal === 'SELL') {
            $currentSupertrendSignal = 'SELL';
        } elseif ($currentSupertrendSignal === null) {
            $currentSupertrendSignal = $recordSupertrendSignal;
        }

        // VWAP LOGIC
        // $vwapFresh = false;
        // if ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
        //     $vwapFresh = true;
        //     $currentVwapSignal = 'BUY';
        // } elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
        //     $vwapFresh = true;
        //     $currentVwapSignal = 'SELL';
        // } elseif ($currentVwapSignal === null) {
        //     $currentVwapSignal = $recordVwapSignal;
        // }
        // ========== VWAP LOGIC (HANDLES GAP_UP/GAP_DOWN) ==========
        $vwapFresh = false;
        
        // Convert GAP_UP to BUY signal
        if ($recordVwapSignal === 'GAP_UP') {
            if ($currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
            }
        }
        // Convert GAP_DOWN to SELL signal
        elseif ($recordVwapSignal === 'GAP_DOWN') {
            if ($currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
            }
        }
        // Handle direct BUY signal (if exists)
        elseif ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
            $vwapFresh = true;
            $currentVwapSignal = 'BUY';
        }
        // Handle direct SELL signal (if exists)
        elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
            $vwapFresh = true;
            $currentVwapSignal = 'SELL';
        }
        // HOLD - maintain current signal (persistent behavior)
        elseif ($recordVwapSignal === 'HOLD') {
            // Keep currentVwapSignal as is (BUY stays BUY, SELL stays SELL)
            // No fresh signal
        }
        // Initialize if null
        elseif ($currentVwapSignal === null) {
            $currentVwapSignal = 'HOLD';
        }

        // RSI LOGIC
        $rsiFresh = false;
        if ($recordRsiSignal === 'BUY' && $currentRsiSignal !== 'BUY') {
            $rsiFresh = true;
            $currentRsiSignal = 'BUY';
        } elseif ($recordRsiSignal === 'SELL' && $currentRsiSignal !== 'SELL') {
            $rsiFresh = true;
            $currentRsiSignal = 'SELL';
        } elseif ($currentRsiSignal === null) {
            $currentRsiSignal = $recordRsiSignal;
        }

        // NOW CHECK STRATEGY
        $shouldTrigger = false;
        $signalType = null;

        switch ($strategy) {
            case 'SUPERTREND':
                if ($supertrendFresh) {
                    $shouldTrigger = true;
                    $signalType = $currentSupertrendSignal;
                }
                break;

            case 'VWAP':
                if ($vwapFresh) {
                    $shouldTrigger = true;
                    $signalType = $currentVwapSignal;
                }
                break;

            case 'RSI':
                if ($rsiFresh) {
                    $shouldTrigger = true;
                    $signalType = $currentRsiSignal;
                }
                break;

            case 'SUPERTREND_VWAP':
                // Both must be fresh AND aligned
                if ($supertrendFresh && $vwapFresh && $currentSupertrendSignal === $currentVwapSignal) {
                    $shouldTrigger = true;
                    $signalType = $currentSupertrendSignal;
                }
                break;

            case 'VWAP_RSI':
            case 'RSI_VWAP':
                // VWAP and RSI must be fresh AND aligned
                if ($vwapFresh && $rsiFresh && $currentVwapSignal === $currentRsiSignal) {
                    $shouldTrigger = true;
                    $signalType = $currentVwapSignal;
                }
                break;

            case 'SUPERTREND_VWAP_RSI':
                // All three must be fresh AND aligned
                if ($supertrendFresh && $vwapFresh && $rsiFresh && 
                    $currentSupertrendSignal === $currentVwapSignal && 
                    $currentVwapSignal === $currentRsiSignal) {
                    $shouldTrigger = true;
                    $signalType = $currentSupertrendSignal;
                }
                break;
        }

        if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
            \Log::info("✅ {$signalType} sync at {$candle->timestamp}");
            
            $syncPoints[] = [
                'index' => $index,
                'timestamp' => $candle->timestamp,
                'signal_type' => $signalType,
                'supertrend' => $currentSupertrendSignal,
                'vwap' => $currentVwapSignal,
                'rsi' => $currentRsiSignal,
                'future_price' => $candle->close,
                'strategy' => $strategy
            ];
        }
    }

    \Log::info("✅ Total sync points found: " . count($syncPoints));
    return $syncPoints;
}

/**
 * Filter to get valid order triggers (alternating BUY/SELL only)
 */
private function filterValidOrderTriggers($syncPoints, $symbol)
{
    if (empty($syncPoints)) {
        return [];
    }

    $validTriggers = [];
    $lastSignalType = null;

    foreach ($syncPoints as $point) {
        // Skip if same as last signal (we only want alternating signals)
        if ($lastSignalType === $point['signal_type']) {
            \Log::info("⏭️ Skipping duplicate {$point['signal_type']} at {$point['timestamp']}");
            continue;
        }

        \Log::info("✅ Valid order trigger: {$point['signal_type']} at {$point['timestamp']}");

        // Get option symbol that would be selected
        $optionInfo = $this->getBacktestOptionSymbol($symbol, $point['signal_type'], $point['future_price']);

        $validTriggers[] = [
            'future_symbol' => $symbol,
            'signal_time' => $point['timestamp']->format('Y-m-d H:i:s'),
            'signal_type' => $point['signal_type'],
            'strategy' => $point['strategy'],
            'supertrend_signal' => $point['supertrend'],
            'vwap_signal' => $point['vwap'],
            'rsi_signal' => $point['rsi'],
            'future_price' => round($point['future_price'], 2),
            'option_type' => $optionInfo['option_type'],
            'option_symbol' => $optionInfo['option_symbol'],
            'strike_price' => $optionInfo['strike_price'],
            'order_action' => 'Would place CE/PE BUY order'
        ];

        $lastSignalType = $point['signal_type'];
    }

    return $validTriggers;
}

/**
 * Get option symbol for backtesting (simulation only)
 */
private function getBacktestOptionSymbol($futureSymbol, $signalType, $futurePrice)
{
    try {
        // Extract base symbol (remove date suffix)
        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $futureSymbol);
        
        // Determine option type
        $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
        
        // Get strike interval
        $strikeIntervals = [
            'NIFTY' => 50,
            'BANKNIFTY' => 100,
            'FINNIFTY' => 50,
            'MIDCPNIFTY' => 25,
        ];
        $strikeInterval = $strikeIntervals[$baseSymbol] ?? 20;
        
        // Calculate ATM strike
        $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;
        
        // Find the option (use current expiry for historical simulation)
        $option = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', $optionType)
            ->where('strike', $calculatedStrike)
            ->whereDate('expiry', '>=', now())
            ->orderBy('expiry', 'ASC')
            ->first();

        if (!$option) {
            // Fallback: find nearest strike
            $option = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->whereDate('expiry', '>=', now())
                ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike])
                ->orderBy('strike_diff', 'ASC')
                ->orderBy('expiry', 'ASC')
                ->first();
        }

        if ($option) {
            return [
                'option_type' => $optionType,
                'option_symbol' => $option->trading_symbol,
                'strike_price' => $option->strike
            ];
        }

        // If no option found, return simulated data
        return [
            'option_type' => $optionType,
            'option_symbol' => $baseSymbol . $calculatedStrike . $optionType,
            'strike_price' => $calculatedStrike
        ];

    } catch (\Exception $e) {
        \Log::error("Error getting option symbol: " . $e->getMessage());
        return [
            'option_type' => $signalType == 'BUY' ? 'CE' : 'PE',
            'option_symbol' => 'N/A',
            'strike_price' => 0
        ];
    }
}

}