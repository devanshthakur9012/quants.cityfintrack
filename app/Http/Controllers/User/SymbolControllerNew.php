<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\IndicatorConfig;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use App\Models\BrokerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

class SymbolControllerNew extends Controller
{
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

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
        
        return view($this->activeTemplate . 'user.data.analysis', compact('pageTitle', 'monitoredSymbols'));
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
     */
    private function applyPersistentVWAPSignals($records)
    {
        $currentVwapSignal = null;
        
        foreach ($records as $record) {
            $originalSignal = $record->vwap_signal ?? 'HOLD';
            
            if ($originalSignal === 'GAP_UP') {
                $currentVwapSignal = 'BUY';
            } elseif ($originalSignal === 'GAP_DOWN') {
                $currentVwapSignal = 'SELL';
            }
            
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
            'ATR',
            'Supertrend',
            'ST Direction',
            'ST Signal',
            'Upper Band',
            'Lower Band',
            'Donchian Signal',
            'Donchian Entry',
            'Donchian SL',
            'Donchian Target',
            'Donchian Upper',
            'Donchian Lower',
            'Donchian Middle',
            'RSI',
            'RSI Signal',
            'MACD Line',
            'MACD Signal Line',
            'MACD Histogram',
            'MACD Signal',
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
                $row->atr,
                $row->supertrend,
                $row->supertrend_direction,
                $row->supertrend_signal,
                $row->upper_band,
                $row->lower_band,
                $row->donchian_signal,
                $row->donchian_entry,
                $row->donchian_sl,
                $row->donchian_target,
                $row->donchian_upper,
                $row->donchian_lower,
                $row->donchian_middle,
                $row->rsi,
                $row->rsi_signal,
                $row->macd_line,
                $row->macd_signal_line,
                $row->macd_histogram,
                $row->macd_signal,
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

            \Artisan::call('symbols:fetch-15min', ['--force' => true]);
            $output15min = \Artisan::output();

            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully for all timeframes!',
                'output' => "15-Min:\n" . $output15min
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
        
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        // Get user's brokers for order placement
        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();
        
        return view($this->activeTemplate . 'user.data.backtesting', compact('pageTitle', 'monitoredSymbols', 'brokers'));
    }
   
    public function backtestingFetch(Request $request)
    {
        try {
            Log::info('=== SYMBOLS BACKTESTING (ORDER SIMULATION) START ===', [
                'all_inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $strategy = $request->get('strategy', 'SUPERTREND');
            $interval = $request->get('interval', '5minute');
            $optionSeries = $request->get('option_series', 'current');
            
            $enableQualityFilter = filter_var(
                $request->get('enable_quality_filter', true), 
                FILTER_VALIDATE_BOOLEAN
            );

            Log::info('Quality Filter Status', [
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

            $startDateTime = $fromDate . ' 09:15:00';
            $endDateTime = $toDate . ' 15:30:00';

            $symbolsQuery = SymbolMonitored::where('is_active', true);
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

            $excludedSymbols = ['NIFTYNXT5026JANFUT'];
            
            $symbols = array_values(array_filter($symbols, function($symbol) use ($excludedSymbols) {
                if (in_array($symbol, $excludedSymbols)) {
                    Log::info("⚠️ Excluding {$symbol} - No options available");
                    return false;
                }
                
                if (strpos($symbol, 'NIFTYNXT50') !== false) {
                    Log::info("⚠️ Excluding {$symbol} - NIFTYNXT50 has no options");
                    return false;
                }
                
                return true;
            }));

            Log::info('Processing Symbols (after exclusions)', [
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
                Log::info("════════ Processing: {$symbol} ════════");

                $candles = SymbolData::where('trading_symbol', $symbol)
                    ->where('interval', $interval)
                    ->where('timestamp', '>=', $startDateTime)
                    ->where('timestamp', '<=', $endDateTime)
                    ->whereNotNull('atr')
                    ->whereNotNull('supertrend')
                    ->whereNotNull('supertrend_direction')
                    ->orderBy('timestamp', 'ASC')
                    ->get();

                Log::info("Found {$candles->count()} candles for {$symbol}");

                if ($candles->count() < 2) {
                    Log::warning("Not enough data for {$symbol}");
                    continue;
                }

                $syncPoints = $this->findBacktestSyncPoints($candles, $symbol, $strategy, $enableQualityFilter);

                Log::info("Found " . count($syncPoints) . " sync points for {$symbol}");

                $validTriggers = $this->filterValidOrderTriggers($syncPoints, $symbol, $optionSeries);

                Log::info("Valid order triggers for {$symbol}: " . count($validTriggers));

                $allOrderTriggers = array_merge($allOrderTriggers, $validTriggers);
            }

            usort($allOrderTriggers, function($a, $b) {
                return strtotime($a['signal_time']) - strtotime($b['signal_time']);
            });

            // ✅ ADD: Check which signals already have orders
            foreach ($allOrderTriggers as &$trigger) {
                $existingOrder = ZerodhaAutoOrder::where('trading_symbol', $trigger['future_symbol'])
                    ->where('signal_detected_at', $trigger['signal_time'])
                    ->where('user_id', auth()->id())
                    ->first();
                
                $trigger['has_order'] = $existingOrder ? true : false;
                $trigger['order_id'] = $existingOrder ? $existingOrder->id : null;
                $trigger['is_order_placed'] = $existingOrder ? $existingOrder->is_order_placed : false;
            }

            Log::info('=== BACKTESTING COMPLETE ===', [
                'total_order_triggers' => count($allOrderTriggers)
            ]);

            return response()->json([
                'success' => true,
                'data' => $allOrderTriggers,
                'total_signals' => count($allOrderTriggers),
                'message' => count($allOrderTriggers) . ' order triggers found'
            ]);

        } catch (\Exception $e) {
            Log::error('Backtesting Error', [
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
        
        Log::info("🔍 Scanning {$totalCandles} candles for {$tradingSymbol} with strategy: {$strategy}");

        $currentSupertrendSignal = null;
        $currentVwapSignal = null;
        $currentRsiSignal = null;

        foreach ($candles as $index => $candle) {
            $recordSupertrendSignal = $candle->supertrend_signal;
            $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
            $recordRsiSignal = $candle->rsi_signal ?? 'NEUTRAL';
            $recordDirection = $candle->supertrend_direction;

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
                // Keep currentVwapSignal
            } elseif ($currentVwapSignal === null) {
                $currentVwapSignal = 'HOLD';
            }

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
                    if ($supertrendFresh && $vwapFresh && $currentSupertrendSignal === $currentVwapSignal) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    break;

                case 'VWAP_RSI':
                case 'RSI_VWAP':
                    if ($vwapFresh && $rsiFresh && $currentVwapSignal === $currentRsiSignal) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                    }
                    break;

                case 'SUPERTREND_VWAP_RSI':
                    if ($supertrendFresh && $vwapFresh && $rsiFresh && 
                        $currentSupertrendSignal === $currentVwapSignal && 
                        $currentVwapSignal === $currentRsiSignal) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    break;
            }

            if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
        
                $hasQuality = true;
                if ($enableQualityFilter) {
                    $hasQuality = $this->hasQualityMomentumBacktest($candles, $index, $signalType);
                    
                    if (!$hasQuality) {
                        Log::info("⚠️ {$signalType} at {$candle->timestamp} REJECTED (low quality)");
                        continue;
                    }
                    Log::info("✅ {$signalType} sync at {$candle->timestamp} - QUALITY APPROVED");
                } else {
                    Log::info("✅ {$signalType} sync at {$candle->timestamp} - QUALITY FILTER BYPASSED");
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
                    'quality_approved' => $hasQuality
                ];
            }
        }

        Log::info("✅ Total QUALITY sync points found: " . count($syncPoints));
        return $syncPoints;
    }

    private function hasQualityMomentumBacktest($candles, $currentIndex, $signalType)
    {
        if ($currentIndex < 6) {
            Log::info("   [QUALITY] Not enough history to validate (index: {$currentIndex})");
            return false;
        }

        $recentCandles = [];
        for ($i = max(0, $currentIndex - 5); $i <= $currentIndex; $i++) {
            $recentCandles[] = $candles[$i];
        }

        $volumeSMA = null;
        if ($currentIndex >= 14) {
            $volumeSum = 0;
            for ($i = $currentIndex - 14; $i <= $currentIndex; $i++) {
                $volumeSum += $candles[$i]->volume;
            }
            $volumeSMA = $volumeSum / 15;
        }

        Log::info("   [QUALITY] Checking momentum for {$signalType} signal");
        
        $volumePassCount = 0;
        if ($volumeSMA) {
            foreach ($recentCandles as $candle) {
                if ($candle->volume > $volumeSMA) {
                    $volumePassCount++;
                }
            }
            Log::info("   [QUALITY] Volume check: {$volumePassCount}/6 candles above SMA");
            
            if ($volumePassCount < 4) {
                Log::warning("   [QUALITY] ❌ REJECTED: Insufficient volume ({$volumePassCount}/6)");
                return false;
            }
        }

        $priceConsistencyCount = 0;
        
        for ($i = 1; $i < count($recentCandles); $i++) {
            $prevClose = $recentCandles[$i - 1]->close;
            $currClose = $recentCandles[$i]->close;
            
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
        
        Log::info("   [QUALITY] Price momentum: {$priceConsistencyCount}/5 consistent closes");
        
        if ($priceConsistencyCount < 4) {
            Log::warning("   [QUALITY] ❌ REJECTED: Weak price momentum ({$priceConsistencyCount}/5)");
            return false;
        }

        Log::info("   [QUALITY] ✅ APPROVED: Strong momentum confirmed");
        Log::info("   [QUALITY]   - Volume: {$volumePassCount}/6 above SMA");
        Log::info("   [QUALITY]   - Price: {$priceConsistencyCount}/5 consistent");
        
        return true;
    }

    private function filterValidOrderTriggers($syncPoints, $symbol, $optionSeries = 'current')
    {
        if (empty($syncPoints)) {
            return [];
        }

        $validTriggers = [];
        $lastSignalType = null;

        foreach ($syncPoints as $point) {
            if ($lastSignalType === $point['signal_type']) {
                Log::info("⏭️ Skipping duplicate {$point['signal_type']} at {$point['timestamp']}");
                continue;
            }

            Log::info("✅ Valid order trigger: {$point['signal_type']} at {$point['timestamp']}");

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
                'order_action' => 'Would place CE/PE BUY order'
            ];

            $lastSignalType = $point['signal_type'];
        }

        return $validTriggers;
    }

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
            
            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($optionSeries === 'next') {
                $query->orderBy('expiry', 'ASC')
                    ->skip(1)
                    ->take(1);
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

    /**
     * ✅ NEW: Place order from backtesting signal
     */
    public function placeBacktestOrder(Request $request)
    {
        $request->validate([
            'future_symbol' => 'required|string',
            'signal_time' => 'required|date',
            'signal_type' => 'required|in:BUY,SELL',
            'option_symbol' => 'required|string',
            'strike_price' => 'required|numeric',
            'future_price' => 'required|numeric',
            'force' => 'nullable|boolean'
        ]);

        try {
            Log::info('=== BACKTEST ORDER PLACEMENT START ===', $request->all());

            // Check if order already exists (unless force=true)
            if (!$request->force) {
                $existingOrder = ZerodhaAutoOrder::where('trading_symbol', $request->future_symbol)
                    ->where('signal_detected_at', $request->signal_time)
                    ->where('user_id', auth()->id())
                    ->first();

                if ($existingOrder) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order already exists for this signal. Use "Force Order" to place again.',
                        'existing_order_id' => $existingOrder->id
                    ], 409);
                }
            }

            // Get user's latest config for this symbol's broker
            $monitoredSymbol = SymbolMonitored::where('trading_symbol', $request->future_symbol)
                ->where('is_active', true)
                ->first();

            if (!$monitoredSymbol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Symbol not found in monitored list'
                ], 404);
            }

            $config = ZerodhaAutoConfig::where('user_id', auth()->id())
                ->where('broker_api_id', $monitoredSymbol->broker_api_id)
                ->where('status', true)
                ->orderByDesc('created_at')
                ->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active config found for this broker. Please create a config first.'
                ], 404);
            }

            $broker = $config->broker;

            if (!$broker || !$broker->hasValidToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Broker token is invalid or expired'
                ], 401);
            }

            // Get option details
            $optionInstrument = ZerodhaInstrument::where('trading_symbol', $request->option_symbol)
                ->where('exchange', 'NFO')
                ->first();

            if (!$optionInstrument) {
                return response()->json([
                    'success' => false,
                    'message' => 'Option instrument not found'
                ], 404);
            }

            // Get LTP
            $ltp = $this->getOptionLTP($broker, $optionInstrument->instrument_token, $request->option_symbol);

            // Calculate quantities
            $quantity = $config->getQuantityForSymbol($request->future_symbol);
            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($quantity);

            // Create order entry
            $orderData = [
                'user_id' => auth()->id(),
                'config_id' => $config->id,
                'broker_api_id' => $broker->id,
                'symbol' => $monitoredSymbol->symbol,
                'trading_symbol' => $request->future_symbol,
                'instrument_token' => $monitoredSymbol->instrument_token,
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

            // Place order immediately
            $this->placeOrderNow($order);

            Log::info('✅ Backtest order created and placed: ' . $order->id);

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
            Log::error('Backtest Order Placement Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error placing order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ NEW: Square off backtest order
     */
    public function squareOffBacktestOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:zerodha_auto_orders,id'
        ]);

        try {
            $order = ZerodhaAutoOrder::where('id', $request->order_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            if (!$order->is_order_placed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order has not been placed yet'
                ], 400);
            }

            // Get executed orders from order book
            $executedOrders = OrderBook::where('symbol_auto_order_id', $order->id)
                ->where('status', 'COMPLETE')
                ->get();

            if ($executedOrders->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No executed orders found to square off'
                ], 404);
            }

            $broker = $order->broker;

            if (!$broker || !$broker->hasValidToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Broker token is invalid'
                ], 401);
            }

            // Initialize Kite
            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];

            // Group orders and square off
            $totalQuantity = $executedOrders->sum('quantity');
            $chunkSize = 20;
            $numOrders = ceil($totalQuantity / $chunkSize);
            $remainingQty = $totalQuantity;

            $placedOrders = [];
            $failedOrders = [];

            for ($i = 0; $i < $numOrders; $i++) {
                $qtyToPlace = min($chunkSize, $remainingQty);

                $orderParams = [
                    'exchange' => 'NFO',
                    'tradingsymbol' => $order->option_symbol,
                    'transaction_type' => 'SELL',
                    'quantity' => $qtyToPlace,
                    'product' => $order->product,
                    'order_type' => 'MARKET',
                    'validity' => 'DAY'
                ];

                try {
                    $result = $kite->placeOrder("regular", $orderParams);

                    if (isset($result->order_id)) {
                        $placedOrders[] = [
                            'order_id' => $result->order_id,
                            'quantity' => $qtyToPlace
                        ];

                        // Save to order book
                        sleep(1);
                        $orderHistory = $kite->getOrderHistory($result->order_id);
                        $lastOrder = end($orderHistory);

                        OrderBook::create([
                            'user_id' => auth()->id(),
                            'broker_username' => $broker->account_user_name,
                            'order_id' => $result->order_id,
                            'status' => $lastOrder->status ?? 'PENDING',
                            'trading_symbol' => $order->option_symbol,
                            'order_type' => 'MARKET',
                            'transaction_type' => 'SELL',
                            'product' => $order->product,
                            'price' => '-',
                            'quantity' => $qtyToPlace,
                            'status_message' => 'Square-off order',
                            'order_datetime' => now(),
                            'symbol_auto_order_id' => $order->id
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error("Square-off order failed: " . $e->getMessage());
                    $failedOrders[] = [
                        'quantity' => $qtyToPlace,
                        'error' => $e->getMessage()
                    ];
                }

                $remainingQty -= $qtyToPlace;

                if ($i < $numOrders - 1) {
                    sleep(1);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Square-off orders placed successfully!',
                'data' => [
                    'placed_orders' => $placedOrders,
                    'failed_orders' => $failedOrders,
                    'total_quantity' => $totalQuantity
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Square-off Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
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
            Log::error("❌ LTP Error: " . $e->getMessage());
            return 25.00;
        }
    }
}