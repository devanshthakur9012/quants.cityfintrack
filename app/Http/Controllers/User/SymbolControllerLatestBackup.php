<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\IndicatorConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SymbolControllerLatestBackup extends Controller
{
    /**
     * Technical Analysis Page
     */
    public function analysis()
    {
        $pageTitle = 'Technical Analysis - Symbols';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.symbols.analysis', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Fetch Supertrend Data (AJAX) - With Persistent VWAP
     */
    public function analysisFetch(Request $request)
    {
        try {
            $tradingSymbol = $request->get('trading_symbol');
            $interval = $request->get('interval', '5minute');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            $query = SymbolData::where('trading_symbol', $tradingSymbol)
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
                    'oi' => $item->oi ?? 0,
                    'oi_change_percent' => $item->oi_change_percent ? round($item->oi_change_percent, 2) : 0,
                    'oi_signal' => $item->oi_signal ?? 'NEUTRAL',
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
     * Export to CSV
     */
    public function export(Request $request)
    {
        $symbol = $request->get('symbol');
        $interval = $request->get('interval', '5minute');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

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
            'Trading Symbol',
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
                $row->symbol,
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

            // Run all interval commands
            \Artisan::call('symbols:fetch-15min', ['--force' => true]);
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
        $pageTitle = 'Backtesting Analysis - Symbols';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.symbols.backtesting', compact('pageTitle', 'monitoredSymbols'));
    }
   
    public function backtestingFetch(Request $request)
    {
        try {
            \Log::info('=== SYMBOLS BACKTESTING (ORDER SIMULATION) START ===', [
                'all_inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $strategy = $request->get('strategy', 'SUPERTREND');
            $interval = $request->get('interval', '5minute');
            $optionSeries = $request->get('option_series', 'current');
            
            // ✅ FIXED: Properly convert to boolean
            $enableQualityFilter = filter_var(
                $request->get('enable_quality_filter', true), 
                FILTER_VALIDATE_BOOLEAN
            );

            \Log::info('Quality Filter Status', [
                'raw_value' => $request->get('enable_quality_filter'),
                'converted_value' => $enableQualityFilter,
                'type' => gettype($enableQualityFilter)
            ]);

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
            $symbolsQuery = SymbolMonitored::where('is_active', true);
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

            // ✅ FIXED: Filter BEFORE logging
            $excludedSymbols = ['NIFTYNXT5026JANFUT'];
            
            $symbols = array_values(array_filter($symbols, function($symbol) use ($excludedSymbols) {
                // Remove exact matches
                if (in_array($symbol, $excludedSymbols)) {
                    \Log::info("⚠️ Excluding {$symbol} - No options available");
                    return false;
                }
                
                // Remove any symbol containing NIFTYNXT50
                if (strpos($symbol, 'NIFTYNXT50') !== false) {
                    \Log::info("⚠️ Excluding {$symbol} - NIFTYNXT50 has no options");
                    return false;
                }
                
                return true;
            }));

            // ✅ NOW log the correct count
            \Log::info('Processing Symbols (after exclusions)', [
                'total' => count($symbols),
                'symbols' => $symbols
            ]);

            if (empty($symbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid symbols to backtest (all excluded)',
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
                    ->whereNotNull('supertrend_direction')
                    ->orderBy('timestamp', 'ASC')
                    ->get();

                \Log::info("Found {$candles->count()} candles for {$symbol}");

                if ($candles->count() < 2) {
                    \Log::warning("Not enough data for {$symbol}");
                    continue;
                }

                // Find synchronization points
                $syncPoints = $this->findBacktestSyncPoints($candles, $symbol, $strategy, $enableQualityFilter);

                \Log::info("Found " . count($syncPoints) . " sync points for {$symbol}");

                // Filter to get only valid order triggers (alternating BUY/SELL)
                // $validTriggers = $this->filterValidOrderTriggers($syncPoints, $symbol);
                $validTriggers = $this->filterValidOrderTriggers($syncPoints, $symbol, $optionSeries);

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

    private function findBacktestSyncPoints($candles, $tradingSymbol, $strategy, $enableQualityFilter = true)
    {
        $syncPoints = [];
        $totalCandles = count($candles);
        
        \Log::info("🔍 Scanning {$totalCandles} candles for {$tradingSymbol} with strategy: {$strategy}");

        // Track persistent trends
        $currentSupertrendSignal = null;
        $currentVwapSignal = null;
        $currentRsiSignal = null;

        foreach ($candles as $index => $candle) {
            $recordSupertrendSignal = $candle->supertrend_signal;
            $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
            $recordRsiSignal = $candle->rsi_signal ?? 'NEUTRAL';
            $recordDirection = $candle->supertrend_direction;

            // ========== SUPERTREND LOGIC ==========
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
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'UP' && $currentSupertrendSignal === 'BUY') {
                $currentSupertrendSignal = 'BUY';
            }
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'DOWN' && $currentSupertrendSignal === 'SELL') {
                $currentSupertrendSignal = 'SELL';
            }
            elseif ($currentSupertrendSignal === null) {
                $currentSupertrendSignal = $recordSupertrendSignal;
            }

            // ========== VWAP LOGIC (HANDLES GAP_UP/GAP_DOWN) ==========
            $vwapFresh = false;
            
            if ($recordVwapSignal === 'GAP_UP') {
                if ($currentVwapSignal !== 'BUY') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'BUY';
                }
            } elseif ($recordVwapSignal === 'GAP_DOWN') {
                if ($currentVwapSignal !== 'SELL') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'SELL';
                }
            } elseif ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
            } elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
            } elseif ($recordVwapSignal === 'HOLD') {
                // Keep currentVwapSignal as is (persistent behavior)
            } elseif ($currentVwapSignal === null) {
                $currentVwapSignal = 'HOLD';
            }

            // ========== RSI LOGIC ==========
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

            // ========== CHECK STRATEGY ==========
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

            // ========== CREATE SYNC POINT WITH QUALITY CHECK ==========
            if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
        
                // ✅ CHANGE THIS BLOCK
                $hasQuality = true;
                if ($enableQualityFilter) {
                    // $hasQuality = $this->hasQualityMomentumBacktest($candles->toArray(), $index, $signalType);
                    $hasQuality = $this->hasQualityMomentumBacktest($candles, $index, $signalType);
                    
                    if (!$hasQuality) {
                        \Log::info("⚠️ {$signalType} at {$candle->timestamp} REJECTED (low quality)");
                        continue;
                    }
                    \Log::info("✅ {$signalType} sync at {$candle->timestamp} - QUALITY APPROVED");
                } else {
                    \Log::info("✅ {$signalType} sync at {$candle->timestamp} - QUALITY FILTER BYPASSED");
                }
                
                $syncPoints[] = [
                    'index' => $index,
                    'timestamp' => $candle->timestamp,
                    'signal_type' => $signalType,
                    'supertrend' => $currentSupertrendSignal,
                    'vwap' => $currentVwapSignal,
                    'rsi' => $currentRsiSignal,
                    'future_price' => $candle->close,
                    'strategy' => $strategy,
                    'quality_approved' => $hasQuality,
                    // ✅ ADD THESE LINES
                    'oi_change_percent' => $candle->oi_change_percent ?? 0,
                    'oi_signal' => $candle->oi_signal ?? 'NEUTRAL',
                ];
            }
        }

        \Log::info("✅ Total QUALITY sync points found: " . count($syncPoints));
        return $syncPoints;
    }

    private function hasQualityMomentumBacktest($candles, $currentIndex, $signalType)
    {
        // Need at least 6 previous candles to check
        if ($currentIndex < 6) {
            \Log::info("   [QUALITY] Not enough history to validate (index: {$currentIndex})");
            return false;
        }

        // Get last 6 candles including current
        $recentCandles = [];
        for ($i = max(0, $currentIndex - 5); $i <= $currentIndex; $i++) {
            $recentCandles[] = $candles[$i]; // ✅ Access directly from collection
        }

        // Calculate volume SMA(15)
        $volumeSMA = null;
        if ($currentIndex >= 14) {
            $volumeSum = 0;
            for ($i = $currentIndex - 14; $i <= $currentIndex; $i++) {
                $volumeSum += $candles[$i]->volume; // ✅ Access as object property
            }
            $volumeSMA = $volumeSum / 15;
        }

        \Log::info("   [QUALITY] Checking momentum for {$signalType} signal");
        
        // CHECK 1: Volume confirmation
        $volumePassCount = 0;
        if ($volumeSMA) {
            foreach ($recentCandles as $candle) {
                if ($candle->volume > $volumeSMA) { // ✅ Object property
                    $volumePassCount++;
                }
            }
            \Log::info("   [QUALITY] Volume check: {$volumePassCount}/6 candles above SMA");
            
            if ($volumePassCount < 4) {
                \Log::warning("   [QUALITY] ❌ REJECTED: Insufficient volume ({$volumePassCount}/6)");
                return false;
            }
        }

        // CHECK 2: Price momentum consistency
        $priceConsistencyCount = 0;
        
        for ($i = 1; $i < count($recentCandles); $i++) {
            $prevClose = $recentCandles[$i - 1]->close; // ✅ Object property
            $currClose = $recentCandles[$i]->close; // ✅ Object property
            
            if ($signalType === 'BUY') {
                if ($currClose > $prevClose) {
                    $priceConsistencyCount++;
                }
            } elseif ($signalType === 'SELL') {
                if ($currClose < $prevClose) {
                    $priceConsistencyCount++;
                }
            }
        }
        
        \Log::info("   [QUALITY] Price momentum: {$priceConsistencyCount}/5 consistent closes");
        
        if ($priceConsistencyCount < 4) {
            \Log::warning("   [QUALITY] ❌ REJECTED: Weak price momentum ({$priceConsistencyCount}/5)");
            return false;
        }

        // BOTH CHECKS PASSED
        \Log::info("   [QUALITY] ✅ APPROVED: Strong momentum confirmed");
        \Log::info("   [QUALITY]   - Volume: {$volumePassCount}/6 above SMA");
        \Log::info("   [QUALITY]   - Price: {$priceConsistencyCount}/5 consistent");
        
        return true;
    }

    // private function hasQualityMomentumBacktest($candles, $currentIndex, $signalType)
    // {
    //     // Reduced from 6 to 3 candles needed
    //     if ($currentIndex < 3) {
    //         \Log::info("   [QUALITY] Not enough history to validate (index: {$currentIndex})");
    //         return false;
    //     }

    //     // Get last 3 candles including current (reduced from 6)
    //     $recentCandles = [];
    //     for ($i = max(0, $currentIndex - 2); $i <= $currentIndex; $i++) {
    //         $recentCandles[] = $candles[$i];
    //     }

    //     // Calculate volume SMA(8) - reduced from SMA(15)
    //     $volumeSMA = null;
    //     if ($currentIndex >= 7) {
    //         $volumeSum = 0;
    //         for ($i = $currentIndex - 7; $i <= $currentIndex; $i++) {
    //             $volumeSum += $candles[$i]->volume;
    //         }
    //         $volumeSMA = $volumeSum / 8;
    //     }

    //     \Log::info("   [QUALITY] Checking momentum for {$signalType} signal");
        
    //     // CHECK 1: Volume confirmation (1 out of 3 candles - 33%)
    //     $volumePassCount = 0;
    //     if ($volumeSMA) {
    //         foreach ($recentCandles as $candle) {
    //             if ($candle->volume > $volumeSMA) {
    //                 $volumePassCount++;
    //             }
    //         }
    //         \Log::info("   [QUALITY] Volume check: {$volumePassCount}/3 candles above SMA");
            
    //         if ($volumePassCount < 1) {
    //             \Log::warning("   [QUALITY] ❌ REJECTED: Insufficient volume ({$volumePassCount}/3)");
    //             return false;
    //         }
    //     }

    //     // CHECK 2: Price momentum consistency (1 out of 2 comparisons - 50%)
    //     $priceConsistencyCount = 0;
        
    //     for ($i = 1; $i < count($recentCandles); $i++) {
    //         $prevClose = $recentCandles[$i - 1]->close;
    //         $currClose = $recentCandles[$i]->close;
            
    //         if ($signalType === 'BUY') {
    //             if ($currClose > $prevClose) {
    //                 $priceConsistencyCount++;
    //             }
    //         } elseif ($signalType === 'SELL') {
    //             if ($currClose < $prevClose) {
    //                 $priceConsistencyCount++;
    //             }
    //         }
    //     }
        
    //     \Log::info("   [QUALITY] Price momentum: {$priceConsistencyCount}/2 consistent closes");
        
    //     if ($priceConsistencyCount < 1) {
    //         \Log::warning("   [QUALITY] ❌ REJECTED: Weak price momentum ({$priceConsistencyCount}/2)");
    //         return false;
    //     }

    //     // BOTH CHECKS PASSED
    //     \Log::info("   [QUALITY] ✅ APPROVED: Momentum confirmed");
    //     \Log::info("   [QUALITY]   - Volume: {$volumePassCount}/3 above SMA");
    //     \Log::info("   [QUALITY]   - Price: {$priceConsistencyCount}/2 consistent");
        
    //     return true;
    // }

    /**
     * Filter to get valid order triggers (alternating BUY/SELL only)
     */
    private function filterValidOrderTriggers($syncPoints, $symbol, $optionSeries = 'current') // ✅ ADD PARAMETER
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

            // ✅ PASS optionSeries to getBacktestOptionSymbol
            $optionInfo = $this->getBacktestOptionSymbol($symbol, $point['signal_type'], $point['future_price'], $optionSeries);

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
                'order_action' => 'Would place CE/PE BUY order',
                // ✅ ADD THESE LINES
                'oi_change_percent' => $point['oi_change_percent'] ?? 0,
                'oi_signal' => $point['oi_signal'] ?? 'NEUTRAL',
            ];

            $lastSignalType = $point['signal_type'];
        }

        return $validTriggers;
    }

    /**
     * Get option symbol for backtesting (simulation only)
     */
    private function getBacktestOptionSymbol($futureSymbol, $signalType, $futurePrice, $optionSeries = 'current')
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
            
            // ✅ BUILD QUERY BASED ON option_series
            $query = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($optionSeries === 'next') {
                // ✅ SKIP FIRST EXPIRY, GET SECOND ONE
                $query->orderBy('expiry', 'ASC')
                    ->skip(1)
                    ->take(1);
            } else {
                // ✅ CURRENT SERIES - GET NEAREST EXPIRY
                $query->orderBy('expiry', 'ASC');
            }

            $option = $query->first();

            if (!$option) {
                // Fallback: find nearest strike
                $query = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
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

    /**
     * Quality Momentum Scanner Page
     */
    public function qualityMomentum()
    {
        $pageTitle = 'Quality Momentum Scanner';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.symbols.quality_momentum', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Fetch Quality Momentum Signals (AJAX)
     */
    public function qualityMomentumFetch(Request $request)
    {
        try {
            \Log::info('=== QUALITY MOMENTUM SCANNER START ===', [
                'all_inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $interval = $request->get('interval', '5minute');
            $momentumType = $request->get('momentum_type', 'both'); // both, buy, sell

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
            $symbolsQuery = SymbolMonitored::where('is_active', true);
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

            // Filter out excluded symbols
            $excludedSymbols = ['NIFTYNXT5026JANFUT'];
            
            $symbols = array_values(array_filter($symbols, function($symbol) use ($excludedSymbols) {
                if (in_array($symbol, $excludedSymbols)) {
                    \Log::info("⚠️ Excluding {$symbol} - No options available");
                    return false;
                }
                
                if (strpos($symbol, 'NIFTYNXT50') !== false) {
                    \Log::info("⚠️ Excluding {$symbol} - NIFTYNXT50 has no options");
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
                    'message' => 'No valid symbols to scan',
                    'data' => []
                ]);
            }

            $allQualitySignals = [];

            foreach ($symbols as $symbol) {
                \Log::info("════════ Scanning: {$symbol} ════════");

                $candles = SymbolData::where('trading_symbol', $symbol)
                    ->where('interval', $interval)
                    ->where('timestamp', '>=', $startDateTime)
                    ->where('timestamp', '<=', $endDateTime)
                    ->orderBy('timestamp', 'ASC')
                    ->get();

                \Log::info("Found {$candles->count()} candles for {$symbol}");

                if ($candles->count() < 8) { // Need at least 8 for volume SMA
                    \Log::warning("Not enough data for {$symbol}");
                    continue;
                }

                // Scan for quality momentum signals
                $qualitySignals = $this->scanQualityMomentum($candles, $symbol, $momentumType);

                \Log::info("Found " . count($qualitySignals) . " quality signals for {$symbol}");

                $allQualitySignals = array_merge($allQualitySignals, $qualitySignals);
            }

            // Sort by timestamp
            usort($allQualitySignals, function($a, $b) {
                return strtotime($a['timestamp']) - strtotime($b['timestamp']);
            });

            \Log::info('=== QUALITY MOMENTUM SCAN COMPLETE ===', [
                'total_signals' => count($allQualitySignals)
            ]);

            return response()->json([
                'success' => true,
                'data' => $allQualitySignals,
                'total_signals' => count($allQualitySignals),
                'message' => count($allQualitySignals) . ' quality momentum signals found'
            ]);

        } catch (\Exception $e) {
            \Log::error('Quality Momentum Scanner Error', [
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
     * Scan for quality momentum signals (BUY/SELL)
     */
    private function scanQualityMomentum($candles, $tradingSymbol, $momentumType = 'both')
    {
        $qualitySignals = [];
        $totalCandles = count($candles);
        
        \Log::info("🔍 Scanning {$totalCandles} candles for quality momentum in {$tradingSymbol}");

        foreach ($candles as $index => $candle) {
            // Skip if not enough history
            if ($index < 3) {
                continue;
            }

            // Check for BUY momentum
            if ($momentumType === 'both' || $momentumType === 'buy') {
                if ($this->hasQualityMomentumBacktest($candles, $index, 'BUY')) {
                    $qualitySignals[] = [
                        'symbol' => $tradingSymbol,
                        'timestamp' => $candle->timestamp->format('Y-m-d H:i:s'),
                        'signal_type' => 'BUY',
                        'open' => round($candle->open, 2),
                        'high' => round($candle->high, 2),
                        'low' => round($candle->low, 2),
                        'close' => round($candle->close, 2),
                        'volume' => $candle->volume,
                        'oi_change_percent' => $candle->oi_change_percent ?? 0,
                        'oi_signal' => $candle->oi_signal ?? 'NEUTRAL',
                    ];
                    
                    \Log::info("✅ Quality BUY signal at {$candle->timestamp}");
                }
            }

            // Check for SELL momentum
            if ($momentumType === 'both' || $momentumType === 'sell') {
                if ($this->hasQualityMomentumBacktest($candles, $index, 'SELL')) {
                    $qualitySignals[] = [
                        'symbol' => $tradingSymbol,
                        'timestamp' => $candle->timestamp->format('Y-m-d H:i:s'),
                        'signal_type' => 'SELL',
                        'open' => round($candle->open, 2),
                        'high' => round($candle->high, 2),
                        'low' => round($candle->low, 2),
                        'close' => round($candle->close, 2),
                        'volume' => $candle->volume,
                        'oi_change_percent' => $candle->oi_change_percent ?? 0,
                        'oi_signal' => $candle->oi_signal ?? 'NEUTRAL',
                    ];
                    
                    \Log::info("✅ Quality SELL signal at {$candle->timestamp}");
                }
            }
        }

        return $qualitySignals;
    }

}