<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\BrokerApi;
use App\Models\OptionPriceCache;
use App\Models\OptionStrike; // ✅ NEW - For OI data
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\DB;

class SymbolOnePercentController extends Controller
{
    private $kite;
    private $userId = 'DB0542'; // Hardcoded user ID for Zerodha API

    /**
     * Display the 1% Move Analysis Page
     */
    public function index()
    {
        $pageTitle = '1% Move Detection - Day-wise Analysis';
        
        // Get monitored symbols
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.one_percent.index', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * Fetch and analyze 1% move signals (AJAX)
     */
    public function analyzeOnePercent(Request $request)
    {
        try {
            Log::info('=== 1% MOVE ANALYSIS START ===', [
                'inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $optionSeries = $request->get('option_series', 'current');
            $percentage = (float) $request->get('percentage', 1.0);

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

            // Exclude symbols without options
            // $excludedSymbols = ['NIFTYNXT5026JANFUT'];
            // $symbols = array_values(array_filter($symbols, function($symbol) use ($excludedSymbols) {
            //     if (in_array($symbol, $excludedSymbols) || strpos($symbol, 'NIFTYNXT50') !== false) {
            //         Log::info("⚠️ Excluding {$symbol} - No options available");
            //         return false;
            //     }
            //     return true;
            // }));

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

            // Analyze each symbol day by day
            $allResults = [];
            $fromDateTime = Carbon::parse($fromDate);
            $toDateTime = Carbon::parse($toDate);

            // Loop through each day in the date range
            for ($date = $fromDateTime->copy(); $date->lte($toDateTime); $date->addDay()) {
                // Skip weekends
                // if ($date->isWeekend()) {
                //     continue;
                // }
                if (!$this->isTradableDate($date)) {
                    continue;
                }

                $currentDate = $date->format('Y-m-d');
                Log::info("📅 Analyzing date: {$currentDate}");

                foreach ($symbols as $symbol) {
                    // ✅ UPDATED: Removed broker_id parameter
                    $result = $this->analyzeSymbolForDay($symbol, $currentDate, $optionSeries, $percentage);
                    
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

            Log::info('=== 1% MOVE ANALYSIS COMPLETE ===', [
                'total_results' => count($allResults),
                'signals' => array_count_values(array_column($allResults, 'signal'))
            ]);

            return response()->json([
                'success' => true,
                'data' => $allResults,
                'total_signals' => count($allResults),
                'message' => count($allResults) . ' day-wise results found'
            ]);

        } catch (\Exception $e) {
            Log::error('1% Move Analysis Error', [
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
     * Analyze a single symbol for a single day
     * ✅ UPDATED - Now includes OI data
     */
    private function analyzeSymbolForDay($symbol, $date, $optionSeries = 'current', $percentage = 1.0)
    {
        try {
            // Fetch 1-minute candles for the specific day
            $startDateTime = $date . ' 09:15:00';
            $endDateTime = $date . ' 15:30:00';

            $candles = SymbolData::where('trading_symbol', $symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($candles->isEmpty()) {
                Log::info("⚠️ No data for {$symbol} on {$date}");
                return null;
            }

            Log::info("📊 Processing {$symbol} on {$date} with {$candles->count()} candles (±{$percentage}%)");

            // Step 1: Get day opening price
            $dayOpenCandle = $candles->first();
            $dayOpenPrice = $dayOpenCandle->open;

            if (!$dayOpenPrice) {
                Log::warning("⚠️ No opening price for {$symbol} on {$date}");
                return null;
            }

            // ✅ UPDATED: Fetch OI data (removed broker_id parameter)
            $oiData = $this->getOIDataForSymbol($symbol, $date);

            // Step 2: Scan through candles to detect +X% or -X% move
            foreach ($candles as $candle) {
                $currentPrice = $candle->close;
                $changePct = (($currentPrice - $dayOpenPrice) / $dayOpenPrice) * 100;

                // Step 3: BUY CE condition (+X%)
                if ($changePct >= $percentage) {
                    $optionInfo = $this->getOptionSymbol($symbol, 'CE', $currentPrice, $optionSeries);
                    
                    return array_merge([
                        'date' => $date,
                        'symbol' => $symbol,
                        'day_open_price' => round($dayOpenPrice, 2),
                        'signal' => 'BUY_CE',
                        'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
                        'signal_price' => round($currentPrice, 2),
                        'change_pct' => round($changePct, 2),
                        'option_type' => 'CE',
                        'option_symbol' => $optionInfo['option_symbol'],
                        'strike_price' => $optionInfo['strike_price']
                    ], $oiData);
                }

                // Step 4: BUY PE condition (-X%)
                if ($changePct <= -$percentage) {
                    $optionInfo = $this->getOptionSymbol($symbol, 'PE', $currentPrice, $optionSeries);
                    
                    return array_merge([
                        'date' => $date,
                        'symbol' => $symbol,
                        'day_open_price' => round($dayOpenPrice, 2),
                        'signal' => 'BUY_PE',
                        'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
                        'signal_price' => round($currentPrice, 2),
                        'change_pct' => round($changePct, 2),
                        'option_type' => 'PE',
                        'option_symbol' => $optionInfo['option_symbol'],
                        'strike_price' => $optionInfo['strike_price']
                    ], $oiData);
                }
            }

            // Step 5: No signal for this day
            return array_merge([
                'date' => $date,
                'symbol' => $symbol,
                'day_open_price' => round($dayOpenPrice, 2),
                'signal' => 'NO_TRADE',
                'signal_time' => null,
                'signal_price' => null,
                'change_pct' => null,
                'option_type' => null,
                'option_symbol' => null,
                'strike_price' => null
            ], $oiData);

        } catch (\Exception $e) {
            Log::error("Error analyzing {$symbol} for {$date}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ UPDATED - Get OI SIGNALS (not raw numbers) for a symbol on a specific date
     */
    // private function getOIDataForSymbol($futureSymbol, $date)
    // {
    //     try {
    //         // Extract base symbol (e.g., ADANIENT from ADANIENT26JANFUT)
    //         $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futureSymbol);

    //         // ✅ KEY FIX: Get OI data from PREVIOUS trading day
    //         $oiDate = $this->getPreviousTradingDay($date);

    //         // Fetch from option_strikes table using PREVIOUS day's data
    //         $futOI = OptionStrike::where('underlying_symbol', $baseSymbol)
    //             ->where('strike_position', 'FUT')
    //             ->where('trading_date', $oiDate)  // ✅ Use previous day
    //             ->orderBy('id', 'DESC')
    //             ->first();

    //         $ceOI = OptionStrike::where('underlying_symbol', $baseSymbol)
    //             ->where('strike_position', 'CE_MERGED')
    //             ->where('trading_date', $oiDate)  // ✅ Use previous day
    //             ->orderBy('id', 'DESC')
    //             ->first();

    //         $peOI = OptionStrike::where('underlying_symbol', $baseSymbol)
    //             ->where('strike_position', 'PE_MERGED')
    //             ->where('trading_date', $oiDate)  // ✅ Use previous day
    //             ->orderBy('id', 'DESC')
    //             ->first();

    //         // Return signals
    //         return [
    //             'fut_signal' => $futOI ? $futOI->direction : 'NEUTRAL',
    //             'fut_strength' => $futOI ? $futOI->strength : 'N/A',
    //             'ce_signal' => $ceOI ? $ceOI->direction : 'NEUTRAL',
    //             'pe_signal' => $peOI ? $peOI->direction : 'NEUTRAL',
    //             'market_bias' => $futOI ? $futOI->market_bias : 'N/A',
    //         ];

    //     } catch (\Exception $e) {
    //         return [
    //             'fut_signal' => 'ERROR',
    //             'fut_strength' => 'ERROR',
    //             'ce_signal' => 'ERROR',
    //             'pe_signal' => 'ERROR',
    //             'market_bias' => 'ERROR',
    //         ];
    //     }
    // }

    /**
     * ✅ UPDATED - Get OI + IV signals from PREVIOUS trading day
     */
    // private function getOIDataForSymbol($futureSymbol, $date)
    // {
    //     try {
    //         $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futureSymbol);
    //         $oiDate = $this->getPreviousTradingDay($date);

    //         $futOI = OptionStrike::where('underlying_symbol', $baseSymbol)
    //             ->where('strike_position', 'FUT')
    //             ->where('trading_date', $oiDate)
    //             ->orderBy('id', 'DESC')
    //             ->first();

    //         $ceOI = OptionStrike::where('underlying_symbol', $baseSymbol)
    //             ->where('strike_position', 'CE_MERGED')
    //             ->where('trading_date', $oiDate)
    //             ->orderBy('id', 'DESC')
    //             ->first();

    //         $peOI = OptionStrike::where('underlying_symbol', $baseSymbol)
    //             ->where('strike_position', 'PE_MERGED')
    //             ->where('trading_date', $oiDate)
    //             ->orderBy('id', 'DESC')
    //             ->first();

    //         return [
    //             'fut_signal' => $futOI ? $futOI->direction : 'NEUTRAL',
    //             'fut_strength' => $futOI ? $futOI->strength : 'N/A',
    //             'ce_signal' => $ceOI ? $ceOI->direction : 'NEUTRAL',
    //             'pe_signal' => $peOI ? $peOI->direction : 'NEUTRAL',
    //             'market_bias' => $futOI ? $futOI->market_bias : 'N/A',
    //             // ✅ NEW - Add IV data
    //             'ce_iv_change_pct' => $ceOI ? $ceOI->daily_iv_change_pct : null,
    //             'pe_iv_change_pct' => $peOI ? $peOI->daily_iv_change_pct : null,
    //         ];

    //     } catch (\Exception $e) {
    //         return [
    //             'fut_signal' => 'ERROR',
    //             'fut_strength' => 'ERROR',
    //             'ce_signal' => 'ERROR',
    //             'pe_signal' => 'ERROR',
    //             'market_bias' => 'ERROR',
    //             'ce_iv_change_pct' => null,
    //             'pe_iv_change_pct' => null,
    //         ];
    //     }
    // }

    /**
     * ✅ FINAL - Get OI + IV signals with full IV data from PREVIOUS trading day
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
                // Signal data
                'fut_signal' => $futOI ? $futOI->direction : 'NEUTRAL',
                'fut_strength' => $futOI ? $futOI->strength : 'N/A',
                'ce_signal' => $ceOI ? $ceOI->direction : 'NEUTRAL',
                'pe_signal' => $peOI ? $peOI->direction : 'NEUTRAL',
                'market_bias' => $futOI ? $futOI->market_bias : 'N/A',
                
                // ✅ FIX: Explicit float casting with null safety
                'ce_iv' => $ceOI && $ceOI->daily_iv !== null ? (float) $ceOI->daily_iv : null,
                'pe_iv' => $peOI && $peOI->daily_iv !== null ? (float) $peOI->daily_iv : null,
                
                'ce_iv_prev' => $ceOI && $ceOI->daily_iv_prev !== null ? (float) $ceOI->daily_iv_prev : null,
                'pe_iv_prev' => $peOI && $peOI->daily_iv_prev !== null ? (float) $peOI->daily_iv_prev : null,
                
                'ce_iv_change' => $ceOI && $ceOI->daily_iv_change !== null ? (float) $ceOI->daily_iv_change : null,
                'pe_iv_change' => $peOI && $peOI->daily_iv_change !== null ? (float) $peOI->daily_iv_change : null,
                
                // ✅ CRITICAL: Explicit float casting for percentage values
                'ce_iv_change_pct' => $ceOI && $ceOI->daily_iv_change_pct !== null ? (float) $ceOI->daily_iv_change_pct : null,
                'pe_iv_change_pct' => $peOI && $peOI->daily_iv_change_pct !== null ? (float) $peOI->daily_iv_change_pct : null,
            ];

        } catch (\Exception $e) {
            return [
                'fut_signal' => 'ERROR',
                'fut_strength' => 'ERROR',
                'ce_signal' => 'ERROR',
                'pe_signal' => 'ERROR',
                'market_bias' => 'ERROR',
                'ce_iv' => null,
                'pe_iv' => null,
                'ce_iv_prev' => null,
                'pe_iv_prev' => null,
                'ce_iv_change' => null,
                'pe_iv_change' => null,
                'ce_iv_change_pct' => null,
                'pe_iv_change_pct' => null,
            ];
        }
    }

    /**
     * ✅ NEW - Get previous trading day (skip weekends/holidays)
     */
    private function getPreviousTradingDay($date)
    {
        $prevDate = Carbon::parse($date)->subDay();
        
        // Keep going back until we find a trading day
        $maxAttempts = 10; // Safety limit
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            // Skip weekends
            // if ($prevDate->isWeekend()) {
            //     $prevDate->subDay();
            //     $attempts++;
            //     continue;
            // }
            if (!$this->isTradableDate($prevDate)) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            // Check if it's a holiday
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $prevDate->format('Y-m-d'))
                ->exists();
            
            if ($isHoliday) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            // Found a trading day!
            return $prevDate->format('Y-m-d');
        }
        
        // Fallback: just return yesterday if we can't find a trading day
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    /**
     * Get option symbol for the signal
     */
    private function getOptionSymbol($futureSymbol, $optionType, $futurePrice, $optionSeries = 'current')
    {
        try {
            // Extract base symbol
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $futureSymbol);
            
            // Strike intervals
            $strikeIntervals = [
                'NIFTY' => 50,
                'BANKNIFTY' => 100,
                'FINNIFTY' => 50,
                'MIDCPNIFTY' => 25,
            ];
            $strikeInterval = $strikeIntervals[$baseSymbol] ?? 20;
            
            // Calculate strike
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;
            
            // Build query
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

            // Fallback: find nearest strike
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
                    'option_symbol' => $option->trading_symbol,
                    'strike_price' => $option->strike
                ];
            }

            // Final fallback
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
     * Calculate profit/loss for detected signals
     * ✅ UPDATED - Now includes highest price tracking (from signal time to 3:30 PM)
     */
    public function calculateProfit(Request $request)
    {
        try {
            Log::info('=== 1% MOVE PROFIT CALCULATION START ===');
            
            $signals = $request->input('signals', []);
            $exitTime = $request->input('exit_time', '15:30');
            
            if (empty($signals)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No signals provided',
                    'data' => []
                ]);
            }

            Log::info("Processing " . count($signals) . " signals with exit time: {$exitTime}");

            $results = [];
            $totalProfit = 0;
            $totalHighestProfit = 0;
            $totalInvestment = 0;
            $totalTrades = 0;
            $winningTrades = 0;
            $losingTrades = 0;
            $highestWinningTrades = 0;

            foreach ($signals as $signal) {
                // Skip NO_TRADE signals
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

                    if ($result['highest_profit'] > 0) {
                        $highestWinningTrades++;
                    }
                }
            }

            $winRate = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;
            $highestWinRate = $totalTrades > 0 ? round(($highestWinningTrades / $totalTrades) * 100, 2) : 0;
            $avgProfit = $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0;
            $avgHighestProfit = $totalTrades > 0 ? round($totalHighestProfit / $totalTrades, 2) : 0;

            Log::info('=== 1% MOVE PROFIT CALCULATION COMPLETE ===', [
                'total_trades' => $totalTrades,
                'total_profit' => $totalProfit,
                'total_highest_profit' => $totalHighestProfit,
                'win_rate' => $winRate,
                'highest_win_rate' => $highestWinRate
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total_trades' => $totalTrades,
                    'winning_trades' => $winningTrades,
                    'losing_trades' => $losingTrades,
                    'win_rate' => $winRate,
                    'highest_win_rate' => $highestWinRate,
                    'total_investment' => round($totalInvestment, 2),
                    'total_profit_loss' => round($totalProfit, 2),
                    'total_highest_profit' => round($totalHighestProfit, 2),
                    'avg_profit_loss' => $avgProfit,
                    'avg_highest_profit' => $avgHighestProfit,
                    'roi_percent' => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0,
                    'highest_roi_percent' => $totalInvestment > 0 ? round(($totalHighestProfit / $totalInvestment) * 100, 2) : 0,
                ],
                'message' => 'Profit calculation completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Profit Calculation Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
 * Calculate profit for a single signal
 * ✅ UPDATED - Highest price is ALWAYS from signal time to 3:30 PM (market close)
 */
private function calculateSignalProfit($signal, $exitTime)
{
    try {
        $optionSymbol = $signal['option_symbol'];
        $signalDateTime = Carbon::parse($signal['signal_time']);
        $signalDate = $signalDateTime->format('Y-m-d');
        
        Log::info("💰 Calculating profit for: {$optionSymbol}", [
            'signal_time' => $signalDateTime->format('H:i:s'),
            'exit_time' => $exitTime
        ]);

        // Get instrument details
        $instrument = \App\Models\ZerodhaInstrument::where('trading_symbol', $optionSymbol)
            ->where('exchange', 'NFO')
            ->first();

        if (!$instrument) {
            Log::warning("⚠️ Instrument not found: {$optionSymbol}");
            return null;
        }

        // Get BUY price (at signal time)
        $buyPrice = $this->getOptionPriceAtTime($instrument, $signalDateTime);
        
        if (!$buyPrice) {
            Log::warning("⚠️ Could not get buy price for {$optionSymbol}");
            return null;
        }

        // Calculate SELL datetime (based on user's exit time filter)
        $exitDateTime = Carbon::parse($signalDate . ' ' . $exitTime . ':00');
        
        // Get SELL price (at user's selected exit time)
        $sellPrice = $this->getOptionPriceAtTime($instrument, $exitDateTime);
        
        if (!$sellPrice) {
            Log::warning("⚠️ Could not get sell price for {$optionSymbol}");
            return null;
        }

        // ✅ CRITICAL: Get highest price from signal time to 3:30 PM (INDEPENDENT of exit time filter)
        $highestPriceData = $this->getHighestPriceForDay($instrument, $signalDate, $signalDateTime);

        // Calculate P/L
        $quantity = $instrument->lot_size ?? 1;
        
        // Exit P/L (based on user's exit time)
        $profitLoss = ($sellPrice - $buyPrice) * $quantity;
        
        // Highest P/L (best possible profit from signal time to market close)
        $highestProfit = ($highestPriceData['price'] - $buyPrice) * $quantity;
        
        $investment = $buyPrice * $quantity;

        Log::info("✅ {$optionSymbol} Results:", [
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'highest_price' => $highestPriceData['price'],
            'highest_time' => $highestPriceData['time'],
            'exit_pl' => $profitLoss,
            'highest_pl' => $highestProfit
        ]);

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
            'highest_profit_per_lot' => round($highestPriceData['price'] - $buyPrice, 2),
            'return_percent' => $buyPrice > 0 ? round((($sellPrice - $buyPrice) / $buyPrice) * 100, 2) : 0,
            'highest_return_percent' => $buyPrice > 0 ? round((($highestPriceData['price'] - $buyPrice) / $buyPrice) * 100, 2) : 0,
        ];

    } catch (\Exception $e) {
        Log::error("❌ Error calculating profit for signal: " . $e->getMessage());
        return null;
    }
}

    /**
 * ✅ FIXED - Get highest price from signal time until market close (3:30 PM)
 * This is INDEPENDENT of exit time filter
 */
private function getHighestPriceForDay($instrument, $date, $startDateTime)
{
    try {
        // Always check until market close (3:30 PM), NOT dependent on exit time filter
        $marketCloseTime = Carbon::parse($date . ' 15:30:00');

        Log::info("🔍 Finding highest price for {$instrument->trading_symbol}", [
            'from' => $startDateTime->format('H:i:s'),
            'to' => '15:30:00',
            'date' => $date
        ]);

        // Get the candle with highest 'high' value between signal time and market close
        $highestCandle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
            ->where('interval', '5minute')
            ->where('timestamp', '>=', $startDateTime)
            ->where('timestamp', '<=', $marketCloseTime)
            ->orderBy('high', 'DESC')
            ->first();

        if ($highestCandle) {
            Log::info("✅ Highest price found: ₹{$highestCandle->high} at {$highestCandle->timestamp->format('H:i')}");
            
            return [
                'price' => $highestCandle->high,
                'time' => $highestCandle->timestamp->format('H:i')
            ];
        }

        Log::warning("⚠️ No candles found for highest price calculation");
        
        // Fallback: try to get from Kite API
        return $this->getHighestPriceFromKite($instrument, $startDateTime, $marketCloseTime);

    } catch (\Exception $e) {
        Log::error("❌ Error getting highest price: " . $e->getMessage());
        return [
            'price' => 0,
            'time' => null
        ];
    }
}

/**
 * ✅ NEW - Fallback: Get highest price from Kite API
 */
private function getHighestPriceFromKite($instrument, $startDateTime, $endDateTime)
{
    try {
        if (!$this->kite) {
            $this->initializeKite();
        }

        if (!$this->kite) {
            Log::warning("Kite not available for highest price fetch");
            return ['price' => 0, 'time' => null];
        }

        $fromDate = $startDateTime->format('Y-m-d H:i:s');
        $toDate = $endDateTime->format('Y-m-d H:i:s');

        Log::info("🔍 Fetching highest price from Kite API", [
            'symbol' => $instrument->trading_symbol,
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
            Log::warning("No candles from Kite API");
            return ['price' => 0, 'time' => null];
        }

        // Find candle with highest 'high' value
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

    /**
     * Get option price at specific time using Kite Connect API
     * Falls back to database candles if Kite fails
     */
    private function getOptionPriceAtTime($instrument, $datetime)
    {
        try {
            // Initialize Kite Connect if not already initialized
            if (!$this->kite) {
                $this->initializeKite();
            }

            // Check cache first
            $cached = OptionPriceCache::where('trading_symbol', $instrument->trading_symbol)
                ->where('price_datetime', $datetime)
                ->first();

            if ($cached) {
                Log::info("📦 Cache HIT: {$instrument->trading_symbol} at {$datetime}");
                return $cached->price;
            }

            Log::info("🔍 Cache MISS: Fetching from Kite API...");

            // Fetch from Kite API
            $price = $this->fetchPriceFromKite($instrument, $datetime);

            if ($price) {
                // Cache the price
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

            // Fallback to database candles
            Log::warning("Kite API failed, trying database candles...");
            return $this->getPriceFromDatabase($instrument, $datetime);

        } catch (\Exception $e) {
            Log::error("Error getting option price: " . $e->getMessage());
            // Fallback to database
            return $this->getPriceFromDatabase($instrument, $datetime);
        }
    }

    /**
     * Fetch price from Kite API (historical candle data)
     */
    private function fetchPriceFromKite($instrument, $datetime)
    {
        try {
            if (!$this->kite) {
                Log::error("Kite not initialized");
                return null;
            }

            $fromDate = $datetime->copy()->subMinutes(30)->format('Y-m-d H:i:s');
            $toDate = $datetime->copy()->addMinutes(30)->format('Y-m-d H:i:s');

            Log::info("🔍 Fetching candles for {$instrument->trading_symbol}");
            Log::info("📅 Time range: {$fromDate} to {$toDate}");

            // Fetch historical candle data
            $response = $this->kite->getHistoricalData(
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

            // if (empty($candles)) {
            //     Log::warning("No candles returned for {$instrument->trading_symbol}");
            //     return $this->getCurrentLTP($instrument);
            // }

            if (empty($candles)) {
                Log::warning("No candles returned for {$instrument->trading_symbol}");
                return null;
            }

            // Find closest candle
            $targetTimestamp = $datetime->timestamp;
            $closestCandle = null;
            $minDiff = PHP_INT_MAX;

            foreach ($candles as $index => $candle) {
                if (is_object($candle)) {
                    $candle = (array) $candle;
                }
                
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
                
                $price = $closestCandle['close'] ?? null;
                
                if ($price === null) {
                    Log::error("❌ Close price not found in candle");
                    return $this->getCurrentLTP($instrument);
                }
                
                return $price;
            }

            // Log::warning("❌ No suitable candle found, using LTP fallback");
            // return $this->getCurrentLTP($instrument);
            Log::warning("❌ No suitable candle found");
            return null; // ✅ Return null instead of current price

        } catch (\Exception $e) {
            Log::error("🚨 Kite API Error: " . $e->getMessage());
            
            // try {
            //     Log::info("🔄 Attempting LTP fallback...");
            //     return $this->getCurrentLTP($instrument);
            // } catch (\Exception $e2) {
            //     Log::error("❌ Fallback LTP failed: " . $e2->getMessage());
            //     return null;
            // }
            return null;
        }
    }

    /**
     * Get current LTP from Kite
     */
    private function getCurrentLTP($instrument)
    {
        try {
            if (!$this->kite) {
                Log::error("Kite not initialized");
                return null;
            }

            $quoteKey = "NFO:" . $instrument->trading_symbol;
            $quotes = $this->kite->getQuote([$quoteKey]);

            $quotesArray = json_decode(json_encode($quotes), true);

            if (isset($quotesArray[$quoteKey]['last_price'])) {
                $ltp = $quotesArray[$quoteKey]['last_price'];
                Log::info("✅ Got LTP {$ltp} for {$instrument->trading_symbol}");
                return $ltp;
            }

            if (is_object($quotes) && isset($quotes->$quoteKey)) {
                $quoteData = $quotes->$quoteKey;
                if (isset($quoteData->last_price)) {
                    $ltp = $quoteData->last_price;
                    Log::info("✅ Got LTP {$ltp} for {$instrument->trading_symbol}");
                    return $ltp;
                }
            }

            Log::warning("Could not extract LTP from quote");
            return null;

        } catch (\Exception $e) {
            Log::error("Error getting LTP: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback: Get price from database candles
     */
    private function getPriceFromDatabase($instrument, $datetime)
    {
        try {
            Log::info("Attempting to get price from database for {$instrument->trading_symbol}");
            
            // Try to find exact 1-minute candle
            $candle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $datetime->copy()->subMinutes(2))
                ->where('timestamp', '<=', $datetime->copy()->addMinutes(2))
                ->orderBy('timestamp', 'ASC')
                ->first();

            if ($candle) {
                Log::info("Found candle in database for {$instrument->trading_symbol} at {$candle->timestamp}");
                return $candle->close;
            }

            // If exact candle not found, try to find nearest candle
            $nearestCandle = SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $datetime->copy()->subMinutes(10))
                ->where('timestamp', '<=', $datetime->copy()->addMinutes(10))
                ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, timestamp, ?))', [$datetime])
                ->first();

            if ($nearestCandle) {
                Log::info("Found nearest candle in database for {$instrument->trading_symbol} at {$nearestCandle->timestamp}");
                return $nearestCandle->close;
            }

            Log::warning("No candle data found in database for {$instrument->trading_symbol} near {$datetime}");
            return null;

        } catch (\Exception $e) {
            Log::error("Error getting price from database: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize Kite Connect using BrokerApi model
     */
    private function initializeKite()
    {
        try {
            // Get broker API credentials from database
            $brokerApi = BrokerApi::where('account_user_name', $this->userId)
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->where('token_expires_at', '>', now())
                ->first();

            if (!$brokerApi) {
                throw new \Exception('Valid Zerodha broker API not found for user ' . $this->userId);
            }

            if (!$brokerApi->api_key || !$brokerApi->access_token) {
                throw new \Exception('API credentials not configured');
            }

            $this->kite = new KiteConnect($brokerApi->api_key);
            $this->kite->setAccessToken($brokerApi->access_token);
            
            Log::info("✅ Kite Connect initialized using BrokerApi for user: {$this->userId}");

        } catch (\Exception $e) {
            Log::error("Failed to initialize Kite: " . $e->getMessage());
            Log::info("Will fallback to database candles if available");
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

            $filename = 'one_percent_analysis_' . date('Y-m-d_His') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($output, [
                'Date',
                'Symbol',
                'Signal',
                'Signal Time',
                'Signal Price',
                'Change %',
                'Option Symbol',
                'Strike Price',
                'FUT OI',
                'CE OI',
                'PE OI'
            ]);

            // CSV Data
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['symbol'],
                    $row['signal'],
                    $row['signal_time'] ?? '-',
                    $row['signal_price'] ?? '-',
                    $row['change_pct'] ?? '-',
                    $row['option_symbol'] ?? '-',
                    $row['strike_price'] ?? '-',
                    $row['fut_oi'] ?? 0,
                    $row['ce_oi'] ?? 0,
                    $row['pe_oi'] ?? 0
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

    /**
     * ✅ NEW - Order Picker Page (Shows EXACT orders that CRON will place)
     */
    public function orderPicker()
    {
        $pageTitle = '1% Move - Order Picker (CRON Preview)';
        
        $monitoredSymbols = SymbolMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.one_percent.order_picker', compact('pageTitle', 'monitoredSymbols'));
    }

    /**
     * ✅ NEW - Analyze with EXACT CRON logic (OI filtering)
     */
    public function analyzeOrderPicker(Request $request)
    {
        try {
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $optionSeries = $request->get('option_series', 'current');
            $percentage = (float) $request->get('percentage', 1.0);

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data' => []
                ]);
            }

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

            for ($date = $fromDateTime->copy(); $date->lte($toDateTime); $date->addDay()) {
                // if ($date->isWeekend()) {
                //     continue;
                // }

                if (!$this->isTradableDate($date)) {
                    continue;
                }

                $currentDate = $date->format('Y-m-d');

                foreach ($symbols as $symbol) {
                    // ✅ CRITICAL: Use CRON logic with OI filtering
                    $result = $this->analyzeSymbolWithOIFilter($symbol, $currentDate, $optionSeries, $percentage);
                    
                    if ($result) {
                        $allResults[] = $result;
                    }
                }
            }

            usort($allResults, function($a, $b) {
                if ($a['date'] === $b['date']) {
                    if ($a['signal_time'] === null) return 1;
                    if ($b['signal_time'] === null) return -1;
                    return strtotime($a['signal_time']) - strtotime($b['signal_time']);
                }
                return strcmp($a['date'], $b['date']);
            });

            return response()->json([
                'success' => true,
                'data' => $allResults,
                'total_signals' => count($allResults),
                'message' => count($allResults) . ' orders will be picked by CRON'
            ]);

        } catch (\Exception $e) {
            Log::error('Order Picker Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    private function isTradableDate(Carbon $date): bool
    {
        $dateStr = $date->format('Y-m-d');

        // ✅ Explicit NSE special trading Sunday
        if ($dateStr === '2026-02-01') {
            return true;
        }

        // ❌ Block normal weekends
        if ($date->isWeekend()) {
            return false;
        }

        // ❌ Block holidays (unless marked trading day)
        $record = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $dateStr)
            ->first();

        if ($record && ($record->is_trading_day ?? 0) == 1) {
            return true;
        }

        return !$record;
    }

    /**
     * ✅ NEW - Analyze with EXACT CRON LOGIC (OI Filtering Applied)
     */
    private function analyzeSymbolWithOIFilter($symbol, $date, $optionSeries = 'current', $percentage = 1.0)
    {
        try {
            $startDateTime = $date . ' 09:15:00';
            $endDateTime = $date . ' 15:30:00';

            $candles = SymbolData::where('trading_symbol', $symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($candles->isEmpty()) {
                return null;
            }

            $dayOpenCandle = $candles->first();
            $dayOpenPrice = $dayOpenCandle->open;

            if (!$dayOpenPrice) {
                return null;
            }

            // ✅ Get OI data from PREVIOUS trading day
            $oiData = $this->getOIDataForSymbol($symbol, $date);

            // ✅ CRITICAL: Apply CRON logic - Check OI signals
            foreach ($candles as $candle) {
                $currentPrice = $candle->close;
                $changePct = (($currentPrice - $dayOpenPrice) / $dayOpenPrice) * 100;

                // ✅ BUY CE: +X% move AND ce_signal === 'BULLISH'
                if ($changePct >= $percentage) {
                    if ($oiData['ce_signal'] === 'BULLISH') {  // ✅ CRON FILTER
                        $optionInfo = $this->getOptionSymbol($symbol, 'CE', $currentPrice, $optionSeries);
                        
                        return array_merge([
                            'date' => $date,
                            'symbol' => $symbol,
                            'day_open_price' => round($dayOpenPrice, 2),
                            'signal' => 'BUY_CE',
                            'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
                            'signal_price' => round($currentPrice, 2),
                            'change_pct' => round($changePct, 2),
                            'option_type' => 'CE',
                            'option_symbol' => $optionInfo['option_symbol'],
                            'strike_price' => $optionInfo['strike_price'],
                            'order_will_be_placed' => true  // ✅ Flag
                        ], $oiData);
                    } else {
                        // ✅ Signal exists but will NOT be picked (OI filter failed)
                        return [
                            'date' => $date,
                            'symbol' => $symbol,
                            'day_open_price' => round($dayOpenPrice, 2),
                            'signal' => 'REJECTED_CE',
                            'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
                            'signal_price' => round($currentPrice, 2),
                            'change_pct' => round($changePct, 2),
                            'rejection_reason' => 'CE Signal is ' . $oiData['ce_signal'] . ' (need BULLISH)',
                            'fut_signal' => $oiData['fut_signal'],
                            'ce_signal' => $oiData['ce_signal'],
                            'pe_signal' => $oiData['pe_signal'],
                            'order_will_be_placed' => false  // ✅ Flag
                        ];
                    }
                }

                // ✅ BUY PE: -X% move AND pe_signal === 'BULLISH'
                if ($changePct <= -$percentage) {
                    if ($oiData['pe_signal'] === 'BULLISH') {  // ✅ CRON FILTER
                        $optionInfo = $this->getOptionSymbol($symbol, 'PE', $currentPrice, $optionSeries);
                        
                        return array_merge([
                            'date' => $date,
                            'symbol' => $symbol,
                            'day_open_price' => round($dayOpenPrice, 2),
                            'signal' => 'BUY_PE',
                            'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
                            'signal_price' => round($currentPrice, 2),
                            'change_pct' => round($changePct, 2),
                            'option_type' => 'PE',
                            'option_symbol' => $optionInfo['option_symbol'],
                            'strike_price' => $optionInfo['strike_price'],
                            'order_will_be_placed' => true  // ✅ Flag
                        ], $oiData);
                    } else {
                        // ✅ Signal exists but will NOT be picked (OI filter failed)
                        return [
                            'date' => $date,
                            'symbol' => $symbol,
                            'day_open_price' => round($dayOpenPrice, 2),
                            'signal' => 'REJECTED_PE',
                            'signal_time' => $candle->timestamp->format('Y-m-d H:i:s'),
                            'signal_price' => round($currentPrice, 2),
                            'change_pct' => round($changePct, 2),
                            'rejection_reason' => 'PE Signal is ' . $oiData['pe_signal'] . ' (need BULLISH)',
                            'fut_signal' => $oiData['fut_signal'],
                            'ce_signal' => $oiData['ce_signal'],
                            'pe_signal' => $oiData['pe_signal'],
                            'order_will_be_placed' => false  // ✅ Flag
                        ];
                    }
                }
            }

            // No signal triggered
            return null;

        } catch (\Exception $e) {
            Log::error("Error analyzing {$symbol} for {$date}: " . $e->getMessage());
            return null;
        }
    }
}