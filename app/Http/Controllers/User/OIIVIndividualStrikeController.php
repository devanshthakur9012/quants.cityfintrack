<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\IndividualOptionStrike;
use App\Models\OptionStrike;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OIIVIndividualStrikeController extends Controller
{
    /**
     * Display PE/CE Individual Strike Analysis page
     */
    public function index()
    {
        $pageTitle = 'Individual Strike CE/PE OI Analysis';
        return view($this->activeTemplate . 'user.oiiv.individual_strike_analysis', compact('pageTitle'));
    }

    /**
     * Get available symbols for filter
     */
    public function getSymbols()
    {
        try {
            $broker = BrokerApi::zerodha()
                ->validToken()
                ->where('user_id', auth()->id())
                ->first();

            if (!$broker) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active broker found'
                ]);
            }

            $symbols = IndividualOptionStrike::where('broker_api_id', $broker->id)
                ->distinct()
                ->pluck('underlying_symbol')
                ->sort()
                ->values();

            return response()->json([
                'success' => true,
                'symbols' => $symbols
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching symbols: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching symbols'
            ]);
        }
    }

    /**
     * Analyze Individual Strike CE/PE OI Signals
     * Returns BOTH 3-strike (ATM-1, ATM, ATM+1) and 2-strike (CE: ATM, ATM+1 | PE: ATM-1, ATM) analysis
     */
    public function analyzeIndividualStrikes(Request $request)
    {
        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $symbols = $request->input('symbols', []);
            $filterAction = $request->input('filter_action');

            $broker = BrokerApi::zerodha()
                ->validToken()
                ->where('user_id', auth()->id())
                ->first();

            if (!$broker) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active broker found'
                ]);
            }

            // Build query for individual strikes
            $query = IndividualOptionStrike::where('broker_api_id', $broker->id)
                ->whereBetween('trading_date', [$fromDate, $toDate])
                ->whereIn('option_type', ['CE', 'PE'])
                ->whereIn('strike_position', ['ATM-2', 'ATM-1', 'ATM', 'ATM+1', 'ATM+2'])
                ->whereNotNull('daily_oi')
                ->whereNotNull('daily_oi_change_pct');

            // Filter by symbols if provided
            if (!empty($symbols)) {
                $query->whereIn('underlying_symbol', $symbols);
            }

            $strikes = $query->orderBy('trading_date', 'desc')
                ->orderBy('underlying_symbol')
                ->orderBy('strike_position')
                ->get();

            if ($strikes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No individual strike data found for selected date range'
                ]);
            }

            // Group by date and symbol
            $groupedData = $strikes->groupBy(function ($item) {
                return $item->trading_date . '_' . $item->underlying_symbol;
            });

            $threeStrikeResults = [];
            $twoStrikeResults = [];

            foreach ($groupedData as $key => $group) {
                list($date, $symbol) = explode('_', $key);

                // Get FUT data
                $futData = OptionStrike::where('broker_api_id', $broker->id)
                    ->where('underlying_symbol', $symbol)
                    ->where('trading_date', $date)
                    ->where('strike_position', 'FUT')
                    ->first();

                if (!$futData) {
                    continue;
                }

                // ===== 3-STRIKE ANALYSIS (ATM-1, ATM, ATM+1) =====
                $threeStrikeAnalysis = $this->analyzeWithStrikes(
                    $group, 
                    ['ATM-1', 'ATM', 'ATM+1'], 
                    ['ATM-1', 'ATM', 'ATM+1'],
                    $date, 
                    $symbol, 
                    $futData,
                    $filterAction
                );
                if ($threeStrikeAnalysis) {
                    $threeStrikeResults[] = $threeStrikeAnalysis;
                }

                // ===== 2-STRIKE ANALYSIS (CE: ATM, ATM+1 | PE: ATM-1, ATM) =====
                $twoStrikeAnalysis = $this->analyzeWithStrikes(
                    $group, 
                    ['ATM', 'ATM+1'],  // CE strikes
                    ['ATM-1', 'ATM'],  // PE strikes
                    $date, 
                    $symbol, 
                    $futData,
                    $filterAction
                );
                if ($twoStrikeAnalysis) {
                    $twoStrikeResults[] = $twoStrikeAnalysis;
                }
            }

            return response()->json([
                'success' => true,
                'three_strike_data' => $threeStrikeResults,
                'two_strike_data' => $twoStrikeResults,
                'message' => count($threeStrikeResults) . ' (3-strike) + ' . count($twoStrikeResults) . ' (2-strike) signals found'
            ]);

        } catch (\Exception $e) {
            Log::error('Individual Strike Analysis Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error analyzing individual strikes: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Analyze with specific CE and PE strikes
     */
    private function analyzeWithStrikes($group, $cePositions, $pePositions, $date, $symbol, $futData, $filterAction)
    {
        // Get CE strikes
        $ceStrikes = $group->filter(function ($strike) use ($cePositions) {
            return $strike->option_type === 'CE' && in_array($strike->strike_position, $cePositions);
        });

        // Get PE strikes
        $peStrikes = $group->filter(function ($strike) use ($pePositions) {
            return $strike->option_type === 'PE' && in_array($strike->strike_position, $pePositions);
        });

        if ($ceStrikes->isEmpty() || $peStrikes->isEmpty()) {
            return null;
        }

        // Calculate aggregated metrics
        $ceOI = $ceStrikes->sum('daily_oi');
        $peOI = $peStrikes->sum('daily_oi');
        $ceOIChangePct = $ceStrikes->avg('daily_oi_change_pct');
        $peOIChangePct = $peStrikes->avg('daily_oi_change_pct');

        // Calculate PE/CE Ratio
        $peCeRatio = $ceOI > 0 ? round($peOI / $ceOI, 2) : 0;

        // Get OI Signal
        $oiSignal = $this->getOISignal($ceOIChangePct, $peOIChangePct);

        // Determine interpretation based on PE/CE Ratio
        $ratioInterpretation = 'Balanced OI';
        if ($peCeRatio > 1.2) {
            $ratioInterpretation = 'Put Writing';
        } elseif ($peCeRatio < 0.8) {
            $ratioInterpretation = 'Call Writing';
        }

        // Trade Action
        $tradeAction = 'WAIT';
        if ($oiSignal['signal'] === 'BULLISH') {
            $tradeAction = 'BUY CE';
        } elseif ($oiSignal['signal'] === 'BEARISH') {
            $tradeAction = 'BUY PE';
        }

        // Apply action filter if provided
        if ($filterAction && $tradeAction !== $filterAction) {
            return null;
        }

        // ✅ Determine STRONG SIGNAL for "Both" scenarios
        $strongSignal = '';
        if (str_contains($oiSignal['condition'], 'Both')) {
            if (abs($ceOIChangePct) > abs($peOIChangePct)) {
                // CE is stronger
                $strongSignal = $ceOIChangePct > 0 ? 'CE Strong' : 'CE Strong';
            } else {
                // PE is stronger
                $strongSignal = $peOIChangePct > 0 ? 'PE Strong' : 'PE Strong';
            }
        } else {
            $strongSignal = $oiSignal['signal']; // Same as main signal for clear scenarios
        }

        return [
            'date' => Carbon::parse($date)->format('Y-m-d'), // ✅ Clean date format
            'symbol' => $symbol,
            'ce_oi' => $ceOI,
            'ce_oi_change_pct' => round($ceOIChangePct, 2),
            'pe_oi' => $peOI,
            'pe_oi_change_pct' => round($peOIChangePct, 2),
            'oi_condition' => $oiSignal['condition'],
            'oi_reason' => $oiSignal['reason'], // ✅ Full interpretation text
            'pe_ce_ratio' => $peCeRatio,
            'ratio_interpretation' => $ratioInterpretation,
            'fut_oi_change_pct' => round($futData->daily_oi_change_pct ?? 0, 2),
            'final_sentiment' => $oiSignal['signal'],
            'strong_signal' => $strongSignal, // ✅ NEW: For "Both" scenarios
            'trade_action' => $tradeAction,
            'ce_strikes_data' => $ceStrikes->map(function ($strike) {
                return [
                    'position' => $strike->strike_position,
                    'strike_price' => $strike->strike_price,
                    'oi' => $strike->daily_oi,
                    'oi_change_pct' => $strike->daily_oi_change_pct,
                    'iv' => $strike->daily_iv,
                    'close' => $strike->daily_close,
                ];
            })->values(),
            'pe_strikes_data' => $peStrikes->map(function ($strike) {
                return [
                    'position' => $strike->strike_position,
                    'strike_price' => $strike->strike_price,
                    'oi' => $strike->daily_oi,
                    'oi_change_pct' => $strike->daily_oi_change_pct,
                    'iv' => $strike->daily_iv,
                    'close' => $strike->daily_close,
                ];
            })->values(),
        ];
    }

    /**
     * Get OI Signal based on CE% and PE% changes (SAME LOGIC AS COMMAND)
     */
    private function getOISignal(float $ceChangePct, float $peChangePct): array
    {
        $ceUp = $ceChangePct > 0;
        $ceDown = $ceChangePct < 0;
        $peUp = $peChangePct > 0;
        $peDown = $peChangePct < 0;

        // Bearish: CE up + PE down
        if ($ceUp && $peDown) {
            return [
                'signal' => 'BEARISH',
                'reason' => 'Call buildup + Put unwinding',
                'condition' => 'CE ↑ + PE ↓'
            ];
        }

        // Bullish: CE down + PE up
        if ($ceDown && $peUp) {
            return [
                'signal' => 'BULLISH',
                'reason' => 'Call unwinding + Put buildup',
                'condition' => 'CE ↓ + PE ↑'
            ];
        }

        // Both up → Compare strength
        if ($ceUp && $peUp) {
            if ($ceChangePct > $peChangePct) {
                return [
                    'signal' => 'BEARISH',
                    'reason' => "Both buildup but CE stronger (CE: +" . round($ceChangePct, 2) . "% > PE: +" . round($peChangePct, 2) . "%)",
                    'condition' => 'Both ↑ (CE > PE)'
                ];
            } else {
                return [
                    'signal' => 'BULLISH',
                    'reason' => "Both buildup but PE stronger (PE: +" . round($peChangePct, 2) . "% > CE: +" . round($ceChangePct, 2) . "%)",
                    'condition' => 'Both ↑ (PE > CE)'
                ];
            }
        }

        // Both down → Compare which is more negative
        if ($ceDown && $peDown) {
            if ($ceChangePct < $peChangePct) {
                return [
                    'signal' => 'BULLISH',
                    'reason' => "Both unwinding but CE stronger (CE: " . round($ceChangePct, 2) . "% < PE: " . round($peChangePct, 2) . "%)",
                    'condition' => 'Both ↓ (CE < PE)'
                ];
            } else {
                return [
                    'signal' => 'BEARISH',
                    'reason' => "Both unwinding but PE stronger (PE: " . round($peChangePct, 2) . "% < CE: " . round($ceChangePct, 2) . "%)",
                    'condition' => 'Both ↓ (PE < CE)'
                ];
            }
        }

        return [
            'signal' => 'NEUTRAL',
            'reason' => 'No clear OI direction (flat movement)',
            'condition' => 'Flat'
        ];
    }

    /**
     * Calculate Bulk Profit for Individual Strikes
     */
    public function calculateBulkProfit(Request $request)
    {
        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $symbols = $request->input('symbols', []);
            $filterAction = $request->input('filter_action');
            $tableType = $request->input('table_type', 'three_strike'); // three_strike or two_strike

            $broker = BrokerApi::zerodha()
                ->validToken()
                ->where('user_id', auth()->id())
                ->first();

            if (!$broker) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active broker found'
                ]);
            }

            // Get analysis data first
            $analysisRequest = new Request([
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'symbols' => $symbols,
                'filter_action' => $filterAction
            ]);

            $analysisResponse = $this->analyzeIndividualStrikes($analysisRequest);
            $analysisData = json_decode($analysisResponse->getContent(), true);

            if (!$analysisData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No analysis data to calculate profit'
                ]);
            }

            // Select the correct dataset based on table type
            $dataToUse = $tableType === 'two_strike' ? $analysisData['two_strike_data'] : $analysisData['three_strike_data'];

            if (empty($dataToUse)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data available for profit calculation'
                ]);
            }

            $profitData = [];
            $totalInvestment = 0;
            $totalProfitLoss = 0;
            $totalHighestProfit = 0;
            $winningTrades = 0;

            foreach ($dataToUse as $signal) {
                // Skip WAIT signals
                if ($signal['trade_action'] === 'WAIT') {
                    continue;
                }

                $date = $signal['date'];
                $symbol = $signal['symbol'];
                $action = $signal['trade_action'];

                // Determine which strikes to use based on action
                $strikesToUse = $action === 'BUY CE' ? $signal['ce_strikes_data'] : $signal['pe_strikes_data'];

                // Use the middle strike for profit calculation
                $middleStrike = $strikesToUse[floor(count($strikesToUse) / 2)] ?? null;

                if (!$middleStrike) {
                    continue;
                }

                // Get the actual option strike record
                $optionStrike = IndividualOptionStrike::where('broker_api_id', $broker->id)
                    ->where('underlying_symbol', $symbol)
                    ->where('trading_date', $date)
                    ->where('strike_position', $middleStrike['position'])
                    ->where('option_type', $action === 'BUY CE' ? 'CE' : 'PE')
                    ->first();

                if (!$optionStrike || !$optionStrike->daily_close) {
                    continue;
                }

                // Calculate profit
                $entryPrice = $optionStrike->daily_close;
                $lotSize = $optionStrike->lot_size ?? 1;
                $investment = $entryPrice * $lotSize;

                // Get next day's data for exit
                $nextDate = Carbon::parse($date)->addDay();
                $exitStrike = IndividualOptionStrike::where('broker_api_id', $broker->id)
                    ->where('underlying_symbol', $symbol)
                    ->where('trading_date', '>=', $nextDate->format('Y-m-d'))
                    ->where('strike_position', $middleStrike['position'])
                    ->where('option_type', $action === 'BUY CE' ? 'CE' : 'PE')
                    ->whereNotNull('daily_close')
                    ->orderBy('trading_date')
                    ->first();

                $exitPrice = $exitStrike->daily_close ?? $entryPrice;
                $highestPrice = $exitPrice;

                $profitLoss = ($exitPrice - $entryPrice) * $lotSize;
                $highestProfit = ($highestPrice - $entryPrice) * $lotSize;
                $returnPercent = $investment > 0 ? ($profitLoss / $investment) * 100 : 0;
                $highestReturnPercent = $investment > 0 ? ($highestProfit / $investment) * 100 : 0;

                $totalInvestment += $investment;
                $totalProfitLoss += $profitLoss;
                $totalHighestProfit += $highestProfit;

                if ($profitLoss > 0) {
                    $winningTrades++;
                }

                $profitData[] = [
                    'date' => $date,
                    'symbol' => $symbol,
                    'option_symbol' => $optionStrike->trading_symbol,
                    'strike_position' => $middleStrike['position'],
                    'investment' => round($investment, 2),
                    'buy_price' => round($entryPrice, 2),
                    'sell_price' => round($exitPrice, 2),
                    'highest_price' => round($highestPrice, 2),
                    'profit_loss' => round($profitLoss, 2),
                    'highest_profit' => round($highestProfit, 2),
                    'return_percent' => round($returnPercent, 2),
                    'highest_return_percent' => round($highestReturnPercent, 2),
                ];
            }

            $summary = [
                'total_trades' => count($profitData),
                'total_investment' => round($totalInvestment, 2),
                'total_profit_loss' => round($totalProfitLoss, 2),
                'total_highest_profit' => round($totalHighestProfit, 2),
                'winning_trades' => $winningTrades,
                'roi_percent' => $totalInvestment > 0 ? round(($totalProfitLoss / $totalInvestment) * 100, 2) : 0,
                'highest_roi_percent' => $totalInvestment > 0 ? round(($totalHighestProfit / $totalInvestment) * 100, 2) : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $profitData,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk Profit Calculation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error calculating profit: ' . $e->getMessage()
            ]);
        }
    }
}