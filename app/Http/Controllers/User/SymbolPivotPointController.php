<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\BrokerApi;
use App\Models\OptionPriceCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\DB;

class SymbolPivotPointController extends Controller
{
    private $kite;
    private $userId = 'DB0542'; // Hardcoded user ID for Zerodha API

    /**
     * Display the Pivot Point Analysis Page
     */
    public function index()
    {
        $pageTitle = 'Pivot Point Trading Strategy';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.pivot_point.index', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Analyze Pivot Point signals (AJAX)
     */
    public function analyzePivotPoints(Request $request)
    {
        try {
            Log::info('=== PIVOT POINT ANALYSIS START ===', [
                'inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $optionSeries = $request->get('option_series', 'current');

            // Validation
            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data' => []
                ]);
            }

            // Get symbols to analyze
            $symbolsQuery = SymbolMonitored::where('is_active', true);
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

            Log::info('Processing Symbols', [
                'total' => count($symbols),
                'symbols' => $symbols
            ]);

            if (empty($symbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid symbols to analyze',
                    'data' => []
                ]);
            }

            $fromDateTime = Carbon::parse($fromDate);
            $toDateTime = Carbon::parse($toDate);

            // ✅ OPTIMIZATION: Fetch ALL candle data in ONE query
            $startDateTime = $fromDateTime->copy()->subDays(10)->format('Y-m-d 09:15:00'); // Extra days for previous day OHLC
            $endDateTime = $toDateTime->format('Y-m-d 15:30:00');

            Log::info('Fetching all candle data in single query', [
                'from' => $startDateTime,
                'to' => $endDateTime
            ]);

            $allCandlesData = SymbolData::whereIn('trading_symbol', $symbols)
                ->where('interval', '15minute')
                ->whereBetween('timestamp', [$startDateTime, $endDateTime])
                ->orderBy('trading_symbol')
                ->orderBy('timestamp')
                ->get();

            // Group by symbol and date for easy access
            $candlesBySymbolAndDate = [];
            foreach ($allCandlesData as $candle) {
                $symbol = $candle->trading_symbol;
                $date = $candle->timestamp->format('Y-m-d');
                
                if (!isset($candlesBySymbolAndDate[$symbol])) {
                    $candlesBySymbolAndDate[$symbol] = [];
                }
                if (!isset($candlesBySymbolAndDate[$symbol][$date])) {
                    $candlesBySymbolAndDate[$symbol][$date] = [];
                }
                
                $candlesBySymbolAndDate[$symbol][$date][] = [
                    'open' => $candle->open,
                    'high' => $candle->high,
                    'low' => $candle->low,
                    'close' => $candle->close,
                    'timestamp' => $candle->timestamp->format('Y-m-d H:i:s')
                ];
            }

            Log::info('Candle data cached in memory', [
                'total_candles' => $allCandlesData->count(),
                'symbols_found' => count($candlesBySymbolAndDate)
            ]);

            // Analyze each symbol day by day (using cached data)
            $allResults = [];

            for ($date = $fromDateTime->copy(); $date->lte($toDateTime); $date->addDay()) {
                if ($date->isWeekend()) {
                    continue;
                }

                $currentDate = $date->format('Y-m-d');
                Log::info("📅 Analyzing date: {$currentDate}");

                foreach ($symbols as $symbol) {
                    $result = $this->analyzeSymbolForDayOptimized(
                        $symbol, 
                        $currentDate, 
                        $optionSeries,
                        $candlesBySymbolAndDate
                    );
                    
                    if ($result) {
                        $allResults[] = $result;
                    }
                }
            }

            // Sort by date and time
            usort($allResults, function($a, $b) {
                if ($a['date'] === $b['date']) {
                    if ($a['signal_time'] === null) return 1;
                    if ($b['signal_time'] === null) return -1;
                    return strtotime($a['signal_time']) - strtotime($b['signal_time']);
                }
                return strcmp($a['date'], $b['date']);
            });

            Log::info('=== PIVOT POINT ANALYSIS COMPLETE ===', [
                'total_results' => count($allResults),
                'signals' => array_count_values(array_column($allResults, 'signal'))
            ]);

            return response()->json([
                'success' => true,
                'data' => $allResults,
                'total_signals' => count($allResults),
                'message' => count($allResults) . ' pivot signals found'
            ]);

        } catch (\Exception $e) {
            Log::error('Pivot Point Analysis Error', [
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
     * ✅ OPTIMIZED: Analyze symbol using pre-cached candle data
     */
    private function analyzeSymbolForDayOptimized($symbol, $date, $optionSeries, $candlesBySymbolAndDate)
    {
        try {
            // Step 1: Get previous day's OHLC from cached data
            $prevDate = $this->getPreviousTradingDay($date);
            
            if (!isset($candlesBySymbolAndDate[$symbol][$prevDate])) {
                Log::warning("⚠️ No cached data for {$symbol} on previous day {$prevDate}");
                return null;
            }

            $prevDayCandles = $candlesBySymbolAndDate[$symbol][$prevDate];
            
            if (empty($prevDayCandles)) {
                return null;
            }

            // Calculate OHLC from cached candles
            $open = $prevDayCandles[0]['open'];
            $high = max(array_column($prevDayCandles, 'high'));
            $low = min(array_column($prevDayCandles, 'low'));
            $close = end($prevDayCandles)['close'];

            $prevDayOHLC = [
                'date' => $prevDate,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close
            ];

            // Step 2: Calculate pivot points
            $pivots = $this->calculateStandardPivots(
                $prevDayOHLC['high'],
                $prevDayOHLC['low'],
                $prevDayOHLC['close']
            );

            Log::info("📊 Pivot Points calculated for {$symbol} on {$date}", [
                'prev_day' => $prevDate,
                'pivots' => $pivots
            ]);

            // Step 3: Get current day candles from cache
            if (!isset($candlesBySymbolAndDate[$symbol][$date])) {
                Log::info("⚠️ No cached data for {$symbol} on {$date}");
                return null;
            }

            $candles = $candlesBySymbolAndDate[$symbol][$date];

            if (empty($candles)) {
                return null;
            }

            Log::info("📊 Processing {$symbol} on {$date} with " . count($candles) . " candles");

            // Step 4: Run pivot signal detection
            $signalResult = $this->pivotTradeSignal($candles, $pivots);

            // Step 5: If signal found, get option symbol
            if ($signalResult['signal'] !== 'NO_TRADE') {
                $optionType = $signalResult['signal'] === 'BUY' ? 'CE' : 'PE';
                $optionInfo = $this->getOptionSymbol($symbol, $optionType, $signalResult['entry_price'], $optionSeries);
                
                return [
                    'date' => $date,
                    'symbol' => $symbol,
                    'signal' => $signalResult['signal'],
                    'signal_type' => $signalResult['type'],
                    'signal_time' => $signalResult['timestamp'],
                    'entry_price' => round($signalResult['entry_price'], 2),
                    'pivot_level' => $signalResult['level'],
                    'pivot_price' => $signalResult['level_price'],
                    'pp' => $pivots['PP'],
                    'r1' => $pivots['R1'],
                    'r2' => $pivots['R2'],
                    'r3' => $pivots['R3'],
                    's1' => $pivots['S1'],
                    's2' => $pivots['S2'],
                    's3' => $pivots['S3'],
                    'prev_day_high' => round($prevDayOHLC['high'], 2),
                    'prev_day_low' => round($prevDayOHLC['low'], 2),
                    'prev_day_close' => round($prevDayOHLC['close'], 2),
                    'option_type' => $optionType,
                    'option_symbol' => $optionInfo['option_symbol'],
                    'strike_price' => $optionInfo['strike_price']
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Error analyzing {$symbol} for {$date}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ STEP 1: Calculate Standard Pivot Points from previous day OHLC (with S3 and R3)
     */
    private function calculateStandardPivots(float $high, float $low, float $close): array
    {
        $pp = ($high + $low + $close) / 3;
        $r1 = (2 * $pp) - $low;
        $s1 = (2 * $pp) - $high;
        $r2 = $pp + ($high - $low);
        $s2 = $pp - ($high - $low);
        $r3 = $high + 2 * ($pp - $low);
        $s3 = $low - 2 * ($high - $pp);

        return [
            'PP' => round($pp, 2),
            'R1' => round($r1, 2),
            'R2' => round($r2, 2),
            'R3' => round($r3, 2),
            'S1' => round($s1, 2),
            'S2' => round($s2, 2),
            'S3' => round($s3, 2)
        ];
    }

    /**
     * ✅ STEP 2: Get previous day's OHLC (calculated from 15-minute candles)
     */
    private function getPreviousDayOHLC($symbol, $currentDate)
    {
        try {
            $prevDate = $this->getPreviousTradingDay($currentDate);
            
            Log::info("🔍 Fetching previous day OHLC", [
                'symbol' => $symbol,
                'current_date' => $currentDate,
                'previous_date' => $prevDate
            ]);

            // ✅ Calculate daily OHLC from 15-minute candles
            $prevStartDateTime = $prevDate . ' 09:15:00';
            $prevEndDateTime = $prevDate . ' 15:30:00';

            // Get all 15-minute candles for previous day
            $prevDayCandles = SymbolData::where('trading_symbol', $symbol)
                ->where('interval', '15minute')
                ->where('timestamp', '>=', $prevStartDateTime)
                ->where('timestamp', '<=', $prevEndDateTime)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($prevDayCandles->isEmpty()) {
                Log::warning("⚠️ No 15-minute candles found for {$symbol} on {$prevDate}");
                return null;
            }

            // Calculate OHLC from all candles
            $open = $prevDayCandles->first()->open;
            $high = $prevDayCandles->max('high');
            $low = $prevDayCandles->min('low');
            $close = $prevDayCandles->last()->close;

            Log::info("✅ Previous day OHLC calculated from {$prevDayCandles->count()} candles", [
                'date' => $prevDate,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close
            ]);

            return [
                'date' => $prevDate,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close
            ];

        } catch (\Exception $e) {
            Log::error("Error getting previous day OHLC: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ IMPROVED: Check for rejection at pivot level with proper sequence
     */
    private function isRejection(array $candle, float $level, string $side, float $tolerance = 0.0005): bool
    {
        $range = $candle['high'] - $candle['low'];
        if ($range <= 0) return false;

        // Add tolerance buffer (0.05% default)
        $upperBand = $level * (1 + $tolerance);
        $lowerBand = $level * (1 - $tolerance);

        if ($side === 'BUY') {
            // Bullish rejection: touched level from below, closed above
            return (
                $candle['low'] <= $upperBand &&
                $candle['close'] > $level &&
                (($candle['close'] - $candle['low']) / $range) >= 0.6
            );
        }

        if ($side === 'SELL') {
            // Bearish rejection: touched level from above, closed below
            return (
                $candle['high'] >= $lowerBand &&
                $candle['close'] < $level &&
                (($candle['high'] - $candle['close']) / $range) >= 0.6
            );
        }

        return false;
    }

    /**
     * ✅ STEP 4: Check for confirmation candle
     */
    private function isConfirmation(array $prev, array $curr, string $side): bool
    {
        if ($side === 'BUY') {
            return $curr['close'] > $prev['high'];
        }

        if ($side === 'SELL') {
            return $curr['close'] < $prev['low'];
        }

        return false;
    }

    /**
     * ✅ IMPROVED: Proper 3-candle pattern detection
     */
    private function pivotTradeSignal(array $candles, array $pivots): array
    {
        $n = count($candles);
        if ($n < 3) {
            return ['signal' => 'NO_TRADE', 'reason' => 'INSUFFICIENT_DATA'];
        }

        // Need at least 3 candles for proper pattern
        for ($i = 2; $i < $n; $i++) {
            $approach = $candles[$i - 2];  // Candle approaching level
            $rejection = $candles[$i - 1]; // Candle rejecting at level
            $confirmation = $candles[$i];   // Confirmation candle

            /* =========================
            BUY LOGIC (Above PP)
            ========================= */
            if ($approach['close'] > $pivots['PP']) {

                // Pullback to PP or S1
                foreach (['PP', 'S1'] as $levelName) {
                    $level = $pivots[$levelName];
                    
                    if (
                        $this->isRejection($rejection, $level, 'BUY') &&
                        $this->isConfirmation($rejection, $confirmation, 'BUY')
                    ) {
                        return [
                            'signal' => 'BUY',
                            'type' => 'PULLBACK',
                            'level' => $levelName,
                            'level_price' => $level,
                            'rejection_candle' => $rejection,
                            'confirmation_candle' => $confirmation,
                            'entry_price' => $confirmation['close'],
                            'timestamp' => $confirmation['timestamp']
                        ];
                    }
                }

                // Break & Retest R1
                if (
                    $rejection['close'] > $pivots['R1'] &&
                    $confirmation['low'] >= $pivots['R1'] &&
                    $confirmation['close'] > $rejection['close']
                ) {
                    return [
                        'signal' => 'BUY',
                        'type' => 'BREAK_RETEST',
                        'level' => 'R1',
                        'level_price' => $pivots['R1'],
                        'rejection_candle' => $rejection,
                        'confirmation_candle' => $confirmation,
                        'entry_price' => $confirmation['close'],
                        'timestamp' => $confirmation['timestamp']
                    ];
                }
            }

            /* =========================
            SELL LOGIC (Below PP)
            ========================= */
            if ($approach['close'] < $pivots['PP']) {

                // Pullback to PP or R1
                foreach (['PP', 'R1'] as $levelName) {
                    $level = $pivots[$levelName];
                    
                    if (
                        $this->isRejection($rejection, $level, 'SELL') &&
                        $this->isConfirmation($rejection, $confirmation, 'SELL')
                    ) {
                        return [
                            'signal' => 'SELL',
                            'type' => 'PULLBACK',
                            'level' => $levelName,
                            'level_price' => $level,
                            'rejection_candle' => $rejection,
                            'confirmation_candle' => $confirmation,
                            'entry_price' => $confirmation['close'],
                            'timestamp' => $confirmation['timestamp']
                        ];
                    }
                }

                // Break & Retest S1
                if (
                    $rejection['close'] < $pivots['S1'] &&
                    $confirmation['high'] <= $pivots['S1'] &&
                    $confirmation['close'] < $rejection['close']
                ) {
                    return [
                        'signal' => 'SELL',
                        'type' => 'BREAK_RETEST',
                        'level' => 'S1',
                        'level_price' => $pivots['S1'],
                        'rejection_candle' => $rejection,
                        'confirmation_candle' => $confirmation,
                        'entry_price' => $confirmation['close'],
                        'timestamp' => $confirmation['timestamp']
                    ];
                }
            }
        }

        return ['signal' => 'NO_TRADE', 'reason' => 'NO_VALID_SETUP'];
    }

    /**
     * Analyze a single symbol for a single day using Pivot Points
     */
    private function analyzeSymbolForDay($symbol, $date, $optionSeries = 'current')
    {
        try {
            // Step 1: Get previous day's OHLC
            $prevDayOHLC = $this->getPreviousDayOHLC($symbol, $date);
            
            if (!$prevDayOHLC) {
                Log::warning("⚠️ Cannot calculate pivots for {$symbol} on {$date} - no previous day data");
                return null;
            }

            // Step 2: Calculate pivot points (now includes S3 and R3)
            $pivots = $this->calculateStandardPivots(
                $prevDayOHLC['high'],
                $prevDayOHLC['low'],
                $prevDayOHLC['close']
            );

            Log::info("📊 Pivot Points calculated for {$symbol} on {$date}", [
                'prev_day' => $prevDayOHLC['date'],
                'pivots' => $pivots
            ]);

            // Step 3: Fetch 15-minute candles for current day
            $startDateTime = $date . ' 09:15:00';
            $endDateTime = $date . ' 15:30:00';

            $candlesData = SymbolData::where('trading_symbol', $symbol)
                ->where('interval', '15minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($candlesData->isEmpty()) {
                Log::info("⚠️ No 15-minute data for {$symbol} on {$date}");
                return null;
            }

            // Convert to array format for pivot logic
            $candles = $candlesData->map(function($candle) {
                return [
                    'open' => $candle->open,
                    'high' => $candle->high,
                    'low' => $candle->low,
                    'close' => $candle->close,
                    'timestamp' => $candle->timestamp->format('Y-m-d H:i:s')
                ];
            })->toArray();

            Log::info("📊 Processing {$symbol} on {$date} with " . count($candles) . " candles");

            // Step 4: Run pivot signal detection
            $signalResult = $this->pivotTradeSignal($candles, $pivots);

            // Step 5: If signal found, get option symbol
            if ($signalResult['signal'] !== 'NO_TRADE') {
                $optionType = $signalResult['signal'] === 'BUY' ? 'CE' : 'PE';
                $optionInfo = $this->getOptionSymbol($symbol, $optionType, $signalResult['entry_price'], $optionSeries);
                
                return [
                    'date' => $date,
                    'symbol' => $symbol,
                    'signal' => $signalResult['signal'],
                    'signal_type' => $signalResult['type'],
                    'signal_time' => $signalResult['timestamp'],
                    'entry_price' => round($signalResult['entry_price'], 2),
                    'pivot_level' => $signalResult['level'],
                    'pivot_price' => $signalResult['level_price'],
                    'pp' => $pivots['PP'],
                    'r1' => $pivots['R1'],
                    'r2' => $pivots['R2'],
                    'r3' => $pivots['R3'],
                    's1' => $pivots['S1'],
                    's2' => $pivots['S2'],
                    's3' => $pivots['S3'],
                    'prev_day_high' => round($prevDayOHLC['high'], 2),
                    'prev_day_low' => round($prevDayOHLC['low'], 2),
                    'prev_day_close' => round($prevDayOHLC['close'], 2),
                    'option_type' => $optionType,
                    'option_symbol' => $optionInfo['option_symbol'],
                    'strike_price' => $optionInfo['strike_price']
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Error analyzing {$symbol} for {$date}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get previous trading day (skip weekends/holidays)
     */
    private function getPreviousTradingDay($date)
    {
        $prevDate = Carbon::parse($date)->subDay();
        
        // Keep going back until we find a trading day
        $maxAttempts = 10;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            if ($prevDate->isWeekend()) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $prevDate->format('Y-m-d'))
                ->exists();
            
            if ($isHoliday) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            return $prevDate->format('Y-m-d');
        }
        
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    /**
     * Get option symbol for the signal
     */
    private function getOptionSymbol($futureSymbol, $optionType, $futurePrice, $optionSeries = 'current')
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $futureSymbol);
            
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
                $query = ZerodhaInstrument::where('name', $baseSymbol)
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
                    'option_symbol' => $option->trading_symbol,
                    'strike_price' => $option->strike
                ];
            }

            return [
                'option_symbol' => $baseSymbol . $calculatedStrike . $optionType,
                'strike_price' => $calculatedStrike
            ];

        } catch (\Exception $e) {
            Log::error("Error getting option symbol: " . $e->getMessage());
            return [
                'option_symbol' => 'N/A',
                'strike_price' => 0
            ];
        }
    }

    /**
     * Calculate profit/loss for pivot signals
     */
    public function calculateProfit(Request $request)
    {
        try {
            Log::info('=== PIVOT POINT PROFIT CALCULATION START ===');
            
            $signals = $request->input('signals', []);
            $exitTime = $request->input('exit_time', '15:30');
            
            if (empty($signals)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No signals provided',
                    'data' => []
                ]);
            }

            $results = [];
            $totalProfit = 0;
            $totalInvestment = 0;
            $totalTrades = 0;
            $winningTrades = 0;
            $losingTrades = 0;

            foreach ($signals as $signal) {
                if ($signal['signal'] === 'NO_TRADE' || !$signal['option_symbol']) {
                    continue;
                }

                $result = $this->calculateSignalProfit($signal, $exitTime);
                
                if ($result) {
                    $results[] = $result;
                    $totalProfit += $result['profit_loss'];
                    $totalInvestment += $result['investment'];
                    $totalTrades++;
                    
                    if ($result['profit_loss'] > 0) {
                        $winningTrades++;
                    } elseif ($result['profit_loss'] < 0) {
                        $losingTrades++;
                    }
                }
            }

            $winRate = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;
            $avgProfit = $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total_trades' => $totalTrades,
                    'winning_trades' => $winningTrades,
                    'losing_trades' => $losingTrades,
                    'win_rate' => $winRate,
                    'total_investment' => round($totalInvestment, 2),
                    'total_profit_loss' => round($totalProfit, 2),
                    'avg_profit_loss' => $avgProfit,
                    'roi_percent' => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0,
                ],
                'message' => 'Profit calculation completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Pivot Profit Calculation Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Calculate profit for a single signal
     */
    private function calculateSignalProfit($signal, $exitTime)
    {
        try {
            $this->initializeKite();
            
            $optionSymbol = $signal['option_symbol'];
            $signalDateTime = Carbon::parse($signal['signal_time']);
            $signalDate = $signalDateTime->format('Y-m-d');
            
            $instrument = ZerodhaInstrument::where('trading_symbol', $optionSymbol)
                ->where('exchange', 'NFO')
                ->first();

            if (!$instrument) {
                return null;
            }

            $buyPrice = $this->getOptionPriceAtTime($instrument, $signalDateTime);
            
            if (!$buyPrice) {
                return null;
            }

            $exitDateTime = Carbon::parse($signalDate . ' ' . $exitTime . ':00');
            $sellPrice = $this->getOptionPriceAtTime($instrument, $exitDateTime);
            
            if (!$sellPrice) {
                return null;
            }

            $quantity = $instrument->lot_size ?? 1;
            $profitLoss = ($sellPrice - $buyPrice) * $quantity;
            $investment = $buyPrice * $quantity;

            return [
                'option_symbol' => $optionSymbol,
                'signal_time' => $signalDateTime->format('Y-m-d H:i:s'),
                'exit_time' => $exitDateTime->format('Y-m-d H:i:s'),
                'buy_price' => round($buyPrice, 2),
                'sell_price' => round($sellPrice, 2),
                'quantity' => $quantity,
                'investment' => round($investment, 2),
                'profit_loss' => round($profitLoss, 2),
                'profit_loss_per_lot' => round($sellPrice - $buyPrice, 2),
                'return_percent' => $buyPrice > 0 ? round((($sellPrice - $buyPrice) / $buyPrice) * 100, 2) : 0,
            ];

        } catch (\Exception $e) {
            Log::error("Error calculating profit for signal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get option price at specific time
     */
    private function getOptionPriceAtTime($instrument, $datetime)
    {
        try {
            // Check cache first
            $cached = OptionPriceCache::where('trading_symbol', $instrument->trading_symbol)
                ->where('price_datetime', $datetime)
                ->first();

            if ($cached) {
                return $cached->price;
            }

            // Fetch from Kite API or database
            $price = $this->fetchPriceFromKite($instrument, $datetime);

            if ($price) {
                OptionPriceCache::updateOrCreate(
                    [
                        'trading_symbol' => $instrument->trading_symbol,
                        'price_datetime' => $datetime
                    ],
                    [
                        'instrument_token' => $instrument->instrument_token,
                        'price' => $price,
                        'cached_at' => now()
                    ]
                );
                
                return $price;
            }

            return $this->getPriceFromDatabase($instrument, $datetime);

        } catch (\Exception $e) {
            Log::error("Error getting option price: " . $e->getMessage());
            return $this->getPriceFromDatabase($instrument, $datetime);
        }
    }

    /**
     * Fetch price from Kite API
     */
    private function fetchPriceFromKite($instrument, $datetime)
    {
        try {
            if (!$this->kite) {
                return null;
            }

            $fromDate = $datetime->copy()->subMinutes(30)->format('Y-m-d H:i:s');
            $toDate = $datetime->copy()->addMinutes(30)->format('Y-m-d H:i:s');

            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token,
                '15minute',
                $fromDate,
                $toDate
            );

            $candles = [];
            
            if (is_object($response)) {
                $candles = isset($response->candles) ? $response->candles : (array) $response;
            } elseif (is_array($response)) {
                $candles = $response;
            }

            if (empty($candles)) {
                return null;
            }

            $targetTimestamp = $datetime->timestamp;
            $closestCandle = null;
            $minDiff = PHP_INT_MAX;

            foreach ($candles as $candle) {
                if (is_object($candle)) {
                    $candle = (array) $candle;
                }
                
                $candleTime = null;
                
                if (isset($candle['date'])) {
                    if ($candle['date'] instanceof \DateTime) {
                        $candleTime = $candle['date']->getTimestamp();
                    } elseif (is_string($candle['date'])) {
                        $candleTime = strtotime($candle['date']);
                    }
                }
                
                if ($candleTime === null) {
                    continue;
                }
                
                $diff = abs($candleTime - $targetTimestamp);
                
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $closestCandle = $candle;
                }
            }

            if ($closestCandle) {
                return $closestCandle['close'] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Kite API Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get price from database
     */
    private function getPriceFromDatabase($instrument, $datetime)
    {
        try {
            $candle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '15minute')
                ->where('timestamp', '>=', $datetime->copy()->subMinutes(5))
                ->where('timestamp', '<=', $datetime->copy()->addMinutes(5))
                ->orderBy('timestamp', 'ASC')
                ->first();

            if ($candle) {
                return $candle->close;
            }

            $nearestCandle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '15minute')
                ->where('timestamp', '>=', $datetime->copy()->subMinutes(30))
                ->where('timestamp', '<=', $datetime->copy()->addMinutes(30))
                ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, timestamp, ?))', [$datetime])
                ->first();

            if ($nearestCandle) {
                return $nearestCandle->close;
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Error getting price from database: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize Kite Connect
     */
    private function initializeKite()
    {
        try {
            $brokerApi = BrokerApi::where('account_user_name', $this->userId)
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->where('token_expires_at', '>', now())
                ->first();

            if (!$brokerApi) {
                return;
            }

            if (!$brokerApi->api_key || !$brokerApi->access_token) {
                return;
            }

            $this->kite = new KiteConnect($brokerApi->api_key);
            $this->kite->setAccessToken($brokerApi->access_token);

        } catch (\Exception $e) {
            Log::error("Failed to initialize Kite: " . $e->getMessage());
        }
    }

    /**
     * Export results to CSV
     */
    public function exportCSV(Request $request)
    {
        try {
            $data = json_decode($request->input('data'), true);

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data to export'
                ], 400);
            }

            $filename = 'pivot_point_analysis_' . date('Y-m-d_His') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'Date',
                'Symbol',
                'Signal',
                'Type',
                'Time',
                'Entry Price',
                'Pivot Level',
                'PP',
                'R1',
                'R2',
                'R3',
                'S1',
                'S2',
                'S3',
                'Option Symbol',
                'Strike'
            ]);

            foreach ($data as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['symbol'],
                    $row['signal'],
                    $row['signal_type'] ?? '-',
                    $row['signal_time'] ?? '-',
                    $row['entry_price'] ?? '-',
                    $row['pivot_level'] ?? '-',
                    $row['pp'] ?? '-',
                    $row['r1'] ?? '-',
                    $row['r2'] ?? '-',
                    $row['r3'] ?? '-',
                    $row['s1'] ?? '-',
                    $row['s2'] ?? '-',
                    $row['s3'] ?? '-',
                    $row['option_symbol'] ?? '-',
                    $row['strike_price'] ?? '-'
                ]);
            }

            fclose($output);
            exit;

        } catch (\Exception $e) {
            Log::error('Export CSV Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error exporting data'
            ], 500);
        }
    }
}