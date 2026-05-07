<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\BrokerApi;
use App\Models\OptionPriceCache;
use App\Models\OptionStrike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\DB;

class SymbolCamarillaController extends Controller
{
    private $kite;
    private $userId = 'ZZL808';

    /**
     * Display Camarilla Strategy Page
     */
    public function index()
    {
        $pageTitle = 'Camarilla Pivot Point Strategy - Pure Price Action';
        
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.camarilla.index', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Analyze Camarilla Signals (AJAX)
     */
    public function analyzeCamarilla(Request $request)
    {
        try {
            Log::info('=== CAMARILLA ANALYSIS START ===', [
                'inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $optionSeries = $request->get('option_series', 'current');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data' => []
                ]);
            }

            // Get symbols
            $symbolsQuery = SymbolMonitored::where('is_active', true);
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('trading_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('trading_symbol')->toArray();

            if (empty($symbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid symbols to analyze',
                    'data' => []
                ]);
            }

            $allResults = [];
            $fromDateTime = Carbon::parse($fromDate);
            $toDateTime = Carbon::parse($toDate);

            // Loop through each day
            for ($date = $fromDateTime->copy(); $date->lte($toDateTime); $date->addDay()) {
                // ✅ EXCEPTION: Sunday Feb 1, 2026 is a trading day
                if ($date->format('Y-m-d') !== '2026-02-01') {
                    // Skip other weekends
                    if ($date->isWeekend()) {
                        continue;
                    }
                }

                $currentDate = $date->format('Y-m-d');
                Log::info("📅 Analyzing Camarilla for date: {$currentDate}");

                foreach ($symbols as $symbol) {
                    $result = $this->analyzeSymbolCamarilla($symbol, $currentDate, $optionSeries);
                    
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

            Log::info('=== CAMARILLA ANALYSIS COMPLETE ===', [
                'total_results' => count($allResults),
                'signals' => array_count_values(array_column($allResults, 'signal'))
            ]);

            return response()->json([
                'success' => true,
                'data' => $allResults,
                'total_signals' => count($allResults),
                'message' => count($allResults) . ' Camarilla signals found'
            ]);

        } catch (\Exception $e) {
            Log::error('Camarilla Analysis Error', [
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
     * ✅ CORE LOGIC - Analyze single symbol with Camarilla strategy
     */
    private function analyzeSymbolCamarilla($symbol, $date, $optionSeries = 'current')
    {
        try {
            // Step 1: Get PREVIOUS day's OHLC data (calculated from 5-minute candles)
            $prevTradingDay = $this->getPreviousTradingDay($date);
            
            // ✅ FIXED: Calculate daily OHLC from 5-minute candles
            $prevDayOHLC = $this->calculateDailyOHLCFrom5Min($symbol, $prevTradingDay);

            if (!$prevDayOHLC) {
                Log::warning("⚠️ No previous day data for {$symbol} on {$prevTradingDay}");
                return null;
            }

            // Step 2: Calculate Camarilla Levels
            $levels = $this->calculateCamarilla(
                $prevDayOHLC['high'],
                $prevDayOHLC['low'],
                $prevDayOHLC['close']
            );

            Log::info("📊 Camarilla Levels for {$symbol} on {$date}", [
                'prev_date' => $prevTradingDay,
                'prev_high' => $prevDayOHLC['high'],
                'prev_low' => $prevDayOHLC['low'],
                'prev_close' => $prevDayOHLC['close'],
                'levels' => $levels
            ]);

            // Step 3: Get TODAY's 5-minute candles
            $startDateTime = $date . ' 09:15:00';
            $endDateTime = $date . ' 15:30:00';

            $candles = SymbolData::where('trading_symbol', $symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($candles->isEmpty()) {
                Log::info("⚠️ No intraday data for {$symbol} on {$date}");
                return null;
            }

            // Step 4: Get OI data from PREVIOUS trading day
            $oiData = $this->getOIDataForSymbol($symbol, $date);

            // Step 5: Apply Camarilla Signal Logic
            // ✅ Pass collection directly (not array) to preserve Carbon objects
            $signal = $this->getCamarillaSignal($candles, $levels, $oiData);

            if ($signal['signal'] === 'NO_TRADE') {
                return null;
            }

            // Step 6: Get option details
            $optionInfo = $this->getOptionSymbol(
                $symbol, 
                $signal['option_type'], 
                $signal['signal_price'], 
                $optionSeries
            );

            return array_merge([
                'date' => $date,
                'symbol' => $symbol,
                'signal' => $signal['signal'],
                'signal_type' => $signal['type'],
                'level' => $signal['level'],
                'signal_time' => $signal['signal_time'],
                'signal_price' => round($signal['signal_price'], 2),
                'option_type' => $signal['option_type'],
                'option_symbol' => $optionInfo['option_symbol'],
                'strike_price' => $optionInfo['strike_price'],
                'h3' => round($levels['H3'], 2),
                'h4' => round($levels['H4'], 2),
                'l3' => round($levels['L3'], 2),
                'l4' => round($levels['L4'], 2),
            ], $oiData);

        } catch (\Exception $e) {
            Log::error("Error analyzing Camarilla for {$symbol} on {$date}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Calculate Camarilla Pivot Points
     */
    private function calculateCamarilla($high, $low, $close)
    {
        $range = $high - $low;

        return [
            'H3' => $close + ($range * 1.1 / 4),
            'H4' => $close + ($range * 1.1 / 2),
            'L3' => $close - ($range * 1.1 / 4),
            'L4' => $close - ($range * 1.1 / 2),
        ];
    }

    /**
     * ✅ NEW - Calculate daily OHLC from 5-minute candles
     */
    private function calculateDailyOHLCFrom5Min($symbol, $date)
    {
        try {
            $startDateTime = $date . ' 09:15:00';
            $endDateTime = $date . ' 15:30:00';

            // Get all 5-minute candles for the day
            $candles = SymbolData::where('trading_symbol', $symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($candles->isEmpty()) {
                Log::warning("⚠️ No 5-minute candles found for {$symbol} on {$date}");
                return null;
            }

            // Calculate daily OHLC
            $open = $candles->first()->open;  // First candle's open
            $high = $candles->max('high');     // Highest high of the day
            $low = $candles->min('low');       // Lowest low of the day
            $close = $candles->last()->close;  // Last candle's close

            Log::info("✅ Calculated daily OHLC for {$symbol} on {$date}", [
                'candles_count' => $candles->count(),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close
            ]);

            return [
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close
            ];

        } catch (\Exception $e) {
            Log::error("Error calculating daily OHLC for {$symbol} on {$date}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ Check if candle shows rejection
     */
    private function isRejection($candle, $level, $side)
    {
        $range = $candle['high'] - $candle['low'];
        
        if ($range <= 0) return false;

        // BUY rejection at L3/L4
        if ($side === 'BUY') {
            return (
                $candle['low'] <= $level &&
                $candle['close'] > $level &&
                (($candle['close'] - $candle['low']) / $range) >= 0.6
            );
        }

        // SELL rejection at H3/H4
        if ($side === 'SELL') {
            return (
                $candle['high'] >= $level &&
                $candle['close'] < $level &&
                (($candle['high'] - $candle['close']) / $range) >= 0.6
            );
        }

        return false;
    }

    /**
     * ✅ Check confirmation candle
     */
    private function isConfirmation($prev, $curr, $side)
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
     * ✅ MAIN SIGNAL ENGINE - Get Camarilla signal from candles
     */
    private function getCamarillaSignal($candles, $levels, $oiData)
    {
        $n = $candles->count();
        
        if ($n < 3) {
            return ['signal' => 'NO_TRADE', 'reason' => 'INSUFFICIENT_DATA'];
        }

        for ($i = 1; $i < $n; $i++) {
            // ✅ Access collection items directly (preserves Carbon objects)
            $prevCandle = $candles[$i - 1];
            $currCandle = $candles[$i];

            $prev = [
                'open' => $prevCandle->open,
                'high' => $prevCandle->high,
                'low' => $prevCandle->low,
                'close' => $prevCandle->close,
            ];

            $curr = [
                'open' => $currCandle->open,
                'high' => $currCandle->high,
                'low' => $currCandle->low,
                'close' => $currCandle->close,
            ];

            /* =====================
               REVERSAL LOGIC
               ===================== */

            // BUY REVERSAL AT L3 (with OI filter)
            if (
                $this->isRejection($prev, $levels['L3'], 'BUY') &&
                $this->isConfirmation($prev, $curr, 'BUY')
            ) {
                // ✅ Apply OI filter - ce_signal must be BULLISH
                if ($oiData['ce_signal'] === 'BULLISH') {
                    return [
                        'signal' => 'BUY_CE',
                        'type' => 'REVERSAL',
                        'level' => 'L3',
                        'signal_time' => $currCandle->timestamp->format('Y-m-d H:i:s'),
                        'signal_price' => $curr['close'],
                        'option_type' => 'CE'
                    ];
                }
            }

            // SELL REVERSAL AT H3 (with OI filter)
            if (
                $this->isRejection($prev, $levels['H3'], 'SELL') &&
                $this->isConfirmation($prev, $curr, 'SELL')
            ) {
                // ✅ Apply OI filter - pe_signal must be BULLISH
                if ($oiData['pe_signal'] === 'BULLISH') {
                    return [
                        'signal' => 'BUY_PE',
                        'type' => 'REVERSAL',
                        'level' => 'H3',
                        'signal_time' => $currCandle->timestamp->format('Y-m-d H:i:s'),
                        'signal_price' => $curr['close'],
                        'option_type' => 'PE'
                    ];
                }
            }

            /* =====================
               BREAKOUT LOGIC
               ===================== */

            // BUY BREAKOUT ABOVE H4 (with OI filter)
            if (
                $prev['close'] > $levels['H4'] &&
                $curr['low'] >= $levels['H4'] &&
                $curr['close'] > $prev['close']
            ) {
                // ✅ Apply OI filter
                if ($oiData['ce_signal'] === 'BULLISH') {
                    return [
                        'signal' => 'BUY_CE',
                        'type' => 'BREAKOUT',
                        'level' => 'H4',
                        'signal_time' => $currCandle->timestamp->format('Y-m-d H:i:s'),
                        'signal_price' => $curr['close'],
                        'option_type' => 'CE'
                    ];
                }
            }

            // SELL BREAKOUT BELOW L4 (with OI filter)
            if (
                $prev['close'] < $levels['L4'] &&
                $curr['high'] <= $levels['L4'] &&
                $curr['close'] < $prev['close']
            ) {
                // ✅ Apply OI filter
                if ($oiData['pe_signal'] === 'BULLISH') {
                    return [
                        'signal' => 'BUY_PE',
                        'type' => 'BREAKOUT',
                        'level' => 'L4',
                        'signal_time' => $currCandle->timestamp->format('Y-m-d H:i:s'),
                        'signal_price' => $curr['close'],
                        'option_type' => 'PE'
                    ];
                }
            }
        }

        return ['signal' => 'NO_TRADE', 'reason' => 'NO_VALID_SETUP'];
    }

    /**
     * ✅ Get OI data from PREVIOUS trading day
     */
    private function getOIDataForSymbol($futureSymbol, $date)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futureSymbol);
            $oiDate = $this->getPreviousTradingDay($date);

            $futOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'FUT')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            $ceOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'CE_MERGED')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            $peOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'PE_MERGED')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            return [
                'fut_signal' => $futOI ? $futOI->direction : 'NEUTRAL',
                'fut_strength' => $futOI ? $futOI->strength : 'N/A',
                'ce_signal' => $ceOI ? $ceOI->direction : 'NEUTRAL',
                'pe_signal' => $peOI ? $peOI->direction : 'NEUTRAL',
                'market_bias' => $futOI ? $futOI->market_bias : 'N/A',
            ];

        } catch (\Exception $e) {
            return [
                'fut_signal' => 'ERROR',
                'fut_strength' => 'ERROR',
                'ce_signal' => 'ERROR',
                'pe_signal' => 'ERROR',
                'market_bias' => 'ERROR',
            ];
        }
    }

    /**
     * ✅ Get previous trading day (with Sunday Feb 1, 2026 exception)
     */
    private function getPreviousTradingDay($date)
    {
        $prevDate = Carbon::parse($date)->subDay();
        
        $maxAttempts = 10;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            // ✅ EXCEPTION: Sunday Feb 1, 2026 is a trading day
            if ($prevDate->format('Y-m-d') === '2026-02-01') {
                return $prevDate->format('Y-m-d');
            }

            // Skip other weekends
            if ($prevDate->isWeekend()) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            // Check holidays
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
     * Get option symbol
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
     * Calculate profit for Camarilla signals
     */
    public function calculateProfit(Request $request)
    {
        try {
            Log::info('=== CAMARILLA PROFIT CALCULATION START ===');
            
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
            $totalHighestProfit = 0;
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
                    $totalHighestProfit += $result['highest_profit'];
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
                    'total_highest_profit' => round($totalHighestProfit, 2),
                    'avg_profit_loss' => $avgProfit,
                    'roi_percent' => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0,
                    'highest_roi_percent' => $totalInvestment > 0 ? round(($totalHighestProfit / $totalInvestment) * 100, 2) : 0,
                ],
                'message' => 'Profit calculation completed'
            ]);

        } catch (\Exception $e) {
            Log::error('Camarilla Profit Calculation Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Calculate profit for single signal (reuse from SymbolOnePercentController)
     */
    private function calculateSignalProfit($signal, $exitTime)
    {
        try {
            $optionSymbol = $signal['option_symbol'];
            $signalDateTime = Carbon::parse($signal['signal_time']);
            $signalDate = $signalDateTime->format('Y-m-d');
            
            $instrument = \App\Models\ZerodhaInstrument::where('trading_symbol', $optionSymbol)
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

            $highestPriceData = $this->getHighestPriceForDay($instrument, $signalDate, $signalDateTime);
            $quantity = $instrument->lot_size ?? 1;
            
            $profitLoss = ($sellPrice - $buyPrice) * $quantity;
            $highestProfit = ($highestPriceData['price'] - $buyPrice) * $quantity;
            $investment = $buyPrice * $quantity;

            return [
                'option_symbol' => $optionSymbol,
                'signal_time' => $signalDateTime->format('Y-m-d H:i:s'),
                'exit_time' => $exitDateTime->format('Y-m-d H:i:s'),
                'buy_price' => round($buyPrice, 2),
                'sell_price' => round($sellPrice, 2),
                'highest_price' => round($highestPriceData['price'], 2),
                'highest_price_time' => $highestPriceData['time'],
                'quantity' => $quantity,
                'investment' => round($investment, 2),
                'profit_loss' => round($profitLoss, 2),
                'highest_profit' => round($highestProfit, 2),
                'profit_loss_per_lot' => round($sellPrice - $buyPrice, 2),
                'return_percent' => $buyPrice > 0 ? round((($sellPrice - $buyPrice) / $buyPrice) * 100, 2) : 0,
                'highest_return_percent' => $buyPrice > 0 ? round((($highestPriceData['price'] - $buyPrice) / $buyPrice) * 100, 2) : 0,
            ];

        } catch (\Exception $e) {
            Log::error("Error calculating profit: " . $e->getMessage());
            return null;
        }
    }

    // ✅ Reuse helper methods from SymbolOnePercentController
    private function getOptionPriceAtTime($instrument, $datetime)
    {
        // Copy implementation from SymbolOnePercentController
        try {
            if (!$this->kite) {
                $this->initializeKite();
            }

            $cached = OptionPriceCache::where('trading_symbol', $instrument->trading_symbol)
                ->where('price_datetime', $datetime)
                ->first();

            if ($cached) {
                return $cached->price;
            }

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
            return $this->getPriceFromDatabase($instrument, $datetime);
        }
    }

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
                '5minute',
                $fromDate,
                $toDate
            );

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
                    } elseif (is_numeric($candle['date'])) {
                        $candleTime = $candle['date'];
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
                if (is_object($closestCandle)) {
                    $closestCandle = (array) $closestCandle;
                }
                
                return $closestCandle['close'] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function getPriceFromDatabase($instrument, $datetime)
    {
        try {
            $candle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $datetime->copy()->subMinutes(2))
                ->where('timestamp', '<=', $datetime->copy()->addMinutes(2))
                ->orderBy('timestamp', 'ASC')
                ->first();

            if ($candle) {
                return $candle->close;
            }

            $nearestCandle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $datetime->copy()->subMinutes(10))
                ->where('timestamp', '<=', $datetime->copy()->addMinutes(10))
                ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, timestamp, ?))', [$datetime])
                ->first();

            if ($nearestCandle) {
                return $nearestCandle->close;
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function getHighestPriceForDay($instrument, $date, $startDateTime)
    {
        try {
            $marketCloseTime = Carbon::parse($date . ' 15:30:00');

            Log::info("🔍 Fetching highest price from Kite API", [
                'option_symbol' => $instrument->trading_symbol,
                'from' => $startDateTime->format('H:i'),
                'to' => '15:30'
            ]);

            // ✅ Try Kite API first (since we don't store option candles locally)
            if (!$this->kite) {
                $this->initializeKite();
            }

            if ($this->kite) {
                $highestData = $this->getHighestPriceFromKite($instrument, $startDateTime, $marketCloseTime);
                
                if ($highestData['price'] > 0) {
                    return $highestData;
                }
            }

            // Fallback: Check local database (rarely works for options)
            $highestCandle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $marketCloseTime)
                ->orderBy('high', 'DESC')
                ->first();

            if ($highestCandle) {
                Log::info("✅ Found highest price in local database", [
                    'price' => $highestCandle->high,
                    'time' => $highestCandle->timestamp->format('H:i')
                ]);
                
                return [
                    'price' => $highestCandle->high,
                    'time' => $highestCandle->timestamp->format('H:i')
                ];
            }

            Log::warning("⚠️ Could not find highest price for {$instrument->trading_symbol}");
            return ['price' => 0, 'time' => null];

        } catch (\Exception $e) {
            Log::error("❌ Error in getHighestPriceForDay: " . $e->getMessage());
            return ['price' => 0, 'time' => null];
        }
    }

    /**
     * ✅ Get highest price from Kite API
     */
    private function getHighestPriceFromKite($instrument, $startDateTime, $endDateTime)
    {
        try {
            if (!$this->kite) {
                Log::warning("Kite not available for highest price fetch");
                return ['price' => 0, 'time' => null];
            }

            $fromDate = $startDateTime->format('Y-m-d H:i:s');
            $toDate = $endDateTime->format('Y-m-d H:i:s');

            Log::info("🔍 Fetching candles from Kite for highest price", [
                'symbol' => $instrument->trading_symbol,
                'token' => $instrument->instrument_token,
                'from' => $fromDate,
                'to' => $toDate
            ]);

            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token,
                '5minute',
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
                Log::warning("No candles from Kite API for {$instrument->trading_symbol}");
                return ['price' => 0, 'time' => null];
            }

            Log::info("✅ Received " . count($candles) . " candles from Kite");

            // Find highest price among all candles
            $highestPrice = 0;
            $highestTime = null;

            foreach ($candles as $candle) {
                if (is_object($candle)) {
                    $candle = (array) $candle;
                }

                $high = $candle['high'] ?? 0;
                
                if ($high > $highestPrice) {
                    $highestPrice = $high;
                    
                    if (isset($candle['date'])) {
                        if ($candle['date'] instanceof \DateTime) {
                            $highestTime = $candle['date']->format('H:i');
                        } elseif (is_string($candle['date'])) {
                            $highestTime = date('H:i', strtotime($candle['date']));
                        }
                    }
                }
            }

            Log::info("✅ Highest price from Kite: ₹{$highestPrice} at {$highestTime}");

            return [
                'price' => $highestPrice,
                'time' => $highestTime
            ];

        } catch (\Exception $e) {
            Log::error("Error fetching highest price from Kite: " . $e->getMessage());
            return ['price' => 0, 'time' => null];
        }
    }

    private function initializeKite()
    {
        try {
            $brokerApi = BrokerApi::where('account_user_name', $this->userId)
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->where('token_expires_at', '>', now())
                ->first();

            if (!$brokerApi) {
                throw new \Exception('Valid Zerodha broker API not found');
            }

            if (!$brokerApi->api_key || !$brokerApi->access_token) {
                throw new \Exception('API credentials not configured');
            }

            $this->kite = new KiteConnect($brokerApi->api_key);
            $this->kite->setAccessToken($brokerApi->access_token);

        } catch (\Exception $e) {
            Log::error("Failed to initialize Kite: " . $e->getMessage());
        }
    }

    /**
     * Export to CSV
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

            $filename = 'camarilla_analysis_' . date('Y-m-d_His') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'Date', 'Symbol', 'Signal', 'Type', 'Level', 'Time', 'Price',
                'H3', 'H4', 'L3', 'L4', 'Option', 'Strike',
                'FUT Signal', 'CE Signal', 'PE Signal'
            ]);

            foreach ($data as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['symbol'],
                    $row['signal'],
                    $row['signal_type'] ?? '-',
                    $row['level'] ?? '-',
                    $row['signal_time'] ?? '-',
                    $row['signal_price'] ?? '-',
                    $row['h3'] ?? '-',
                    $row['h4'] ?? '-',
                    $row['l3'] ?? '-',
                    $row['l4'] ?? '-',
                    $row['option_symbol'] ?? '-',
                    $row['strike_price'] ?? '-',
                    $row['fut_signal'] ?? '-',
                    $row['ce_signal'] ?? '-',
                    $row['pe_signal'] ?? '-'
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