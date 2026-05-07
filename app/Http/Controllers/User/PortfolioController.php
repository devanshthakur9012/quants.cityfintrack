<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\HistoricalOptionsData;
use App\Models\EarlyHistoricalOptionsData;
use App\Models\HistoricalOneHour;
use Carbon\Carbon;
use App\Traits\AngelApiAuth;
use App\Helpers\SupertrendCalculator;

class PortfolioController extends Controller
{

    public function portfolioStrongBullish()
    {
        $pageTitle = 'Portfolios Strong Bullish';
        $route = route('user.portfolio-histofical-fetch');
        return view($this->activeTemplate . 'user.portfolios.strong-bulish', compact('pageTitle','route'));
    }

    public function portfolioStrongBullishFetch(Request $request)
    {
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter');

        // ✅ Latest available date from historical_options_data
        $latestDate = DB::table('historical_options_data')->orderByDesc('id')->value('date');
        $filterDate = $dateFilter ?: $latestDate;

        // ✅ Fetch CE & PE candidates
        $symbolsData = DB::table('historical_options_data')
            ->select(
                'underlying',
                'ce_symbol',
                'ce_token',
                'ce_close',
                'ce_oi_chg_pct',
                'pe_symbol',
                'pe_token',
                'pe_oi_chg_pct',
                'pe_close',
                'trend',
                'date'
            )
            ->whereDate('date', $filterDate)
            ->when($typeFilter, function ($q) use ($typeFilter) {
                $q->where('trend', $typeFilter);
            }, function ($q) {
                $q->where('trend', 'Strong Bullish');
            })
            ->get();

        if ($symbolsData->isEmpty()) {
            return response()->json([
                'positions' => [],
                'totalInvestment' => 0,
                'totalProfit' => 0,
                'profitPercentage' => 0,
                'noOfPositions' => 0,
                'totalInvestmentRaw' => 0,
                'totalProfitRaw' => 0
            ]);
        }

        // ✅ Group by underlying to avoid duplicates
        $grouped = $symbolsData->groupBy('underlying');
        $ceFiltered = collect();
        $peFiltered = collect();

        foreach ($grouped as $rows) {
            $bestCE = $rows->sortByDesc('ce_oi_chg_pct')->first();
            $bestPE = $rows->sortByDesc('pe_oi_chg_pct')->first();

            if ($bestCE && $bestCE->ce_token) {
                $ceFiltered->push($bestCE);
            }
            if ($bestPE && $bestPE->pe_token) {
                $peFiltered->push($bestPE);
            }
        }

        $topCE = $ceFiltered->sortByDesc('ce_oi_chg_pct');
        $topPE = $peFiltered->sortByDesc('pe_oi_chg_pct');

        $positions = $topCE->merge($topPE)->values();

        // ✅ Lot sizes
        $allTokens = $positions->pluck('ce_token')->merge($positions->pluck('pe_token'))->filter()->unique()->toArray();
        $lotSizes = DB::table('angel_api_instruments')
            ->whereIn('token', $allTokens)
            ->where('exch_seg','NFO')
            ->pluck('lotsize', 'token')
            ->toArray();

        // ✅ LTP data
        $ltpData = DB::table('symbol_ltps')
            ->select('symbol_token', 'ltp', 'highest_ltp', 'highest_time', 'updated_at')
            ->get()
            ->keyBy('symbol_token');

        $totalInvestment = 0;
        $totalProfit = 0;
        $finalPositions = collect();

        foreach ($positions as $pos) {
            // Decide CE or PE based on trend
            if (in_array($pos->trend, ["Strong Bullish", "Mild Bullish"])) {
                $buyToken = $pos->ce_token;
                $buySymbol = $pos->ce_symbol;
                $pos->buildup_type = 'Buy CE';
                $buyPrice = $pos->ce_close ?? 0;
            } else {
                $buyToken = $pos->pe_token;
                $buySymbol = $pos->pe_symbol;
                $pos->buildup_type = 'Buy PE';
                $buyPrice = $pos->pe_close ?? 0;
            }

            // LTP & lot
            $ltpRow =  $ltpData[$buyToken] ?? null;
            $latestLtp = $ltpRow->ltp ?? $buyPrice;
            $lotSize = $lotSizes[$buyToken] ?? 1;

            // Investment & PnL
            $investment = $lotSize * $buyPrice;
            $profit = round(($latestLtp * $lotSize) - $investment, 2);

            $totalInvestment += $investment;
            $totalProfit += $profit;

            $finalPositions->push((object)[
                'date' => $pos->date,
                'symbol_name' => $buySymbol,
                'symbol_token' => $buyToken,
                'ltp' => $latestLtp,
                'highest_ltp' => $ltpRow->highest_ltp ?? 0,
                'highest_time' => $ltpRow->highest_time ?? null,
                'transaction_type' => 'BUY',
                'lot_size' => $lotSize,
                'buy_quantity' => 1,
                'buy_price' => $buyPrice,
                'sell_quantity' => 0,
                'sell_price' => 0,
                'total_value' => $investment,
                'profit' => $profit,
                'realised_profit' => 0,
                'unrealised_profit' => $profit,
                'trend' => $pos->trend,
                'buildup_type' => $pos->buildup_type,
            ]);
        }

        $profitPercentage = $totalInvestment > 0
            ? round(($totalProfit / $totalInvestment) * 100, 2)
            : 0;

        return response()->json([
            'positions' => $finalPositions,
            'totalInvestment' => number_format($totalInvestment),
            'totalProfit' => number_format($totalProfit),
            'profitPercentage' => $profitPercentage,
            'noOfPositions' => $finalPositions->count(),
            'totalInvestmentRaw' => $totalInvestment,
            'totalProfitRaw' => $totalProfit
        ]);
    }

    // SMART OLD PORTOFLIO
    public function smartPortfolioStrongBullish()
    {
        $pageTitle = 'Smart Portfolios';
        $route = route('user.smart-portfolio-histofical-fetch');
        return view($this->activeTemplate . 'user.portfolios.smart-portfolio', compact('pageTitle','route'));
    }

    public function smartPortfolioStrongBullishFetch(Request $request)
    {
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter', 'Strong Bullish');

        // ✅ Latest available date from historical_options_data
        $latestDate = DB::table('historical_options_data')
            ->orderByDesc('id')
            ->value('date');
        
        $filterDate = $dateFilter ?: $latestDate;

        // ✅ Fetch all records for the selected date and trend type
        $symbolsData = DB::table('historical_options_data')
            ->select(
                'underlying',
                'ce_symbol',
                'ce_token',
                'ce_close',
                'ce_oi_chg_pct',
                'pe_symbol',
                'pe_token',
                'pe_oi_chg_pct',
                'pe_close',
                'trend',
                'date'
            )
            ->whereDate('date', $filterDate)
            ->where('trend', $typeFilter)
            ->get();

        if ($symbolsData->isEmpty()) {
            return response()->json([
                'positions' => [],
                'totalInvestment' => 0,
                'totalProfit' => 0,
                'profitPercentage' => 0,
                'noOfPositions' => 0,
                'totalInvestmentRaw' => 0,
                'totalProfitRaw' => 0
            ]);
        }

        // ✅ CRITICAL: Ensure each underlying appears only ONCE per trend
        // Group by underlying and select the best option based on trend logic
        $grouped = $symbolsData->groupBy('underlying');
        $selectedPositions = collect();

        foreach ($grouped as $underlying => $rows) {
            // Determine which option type to use based on trend
            $isBullish = in_array($typeFilter, ['Strong Bullish', 'Mild Bullish']);
            
            if ($isBullish) {
                // For bullish trends, pick best CE (Call option)
                // Filter out invalid prices (0, null, or very low values < 1)
                $validCE = $rows->filter(function($row) {
                    return $row->ce_close > 1 && 
                        $row->ce_token && 
                        $row->ce_oi_chg_pct > 0;
                });
                
                if ($validCE->isNotEmpty()) {
                    // Pick the CE with highest OI change %
                    $bestOption = $validCE->sortByDesc('ce_oi_chg_pct')->first();
                    $bestOption->selected_type = 'CE';
                    $selectedPositions->push($bestOption);
                }
            } else {
                // For bearish trends, pick best PE (Put option)
                $validPE = $rows->filter(function($row) {
                    return $row->pe_close > 1 && 
                        $row->pe_token && 
                        $row->pe_oi_chg_pct > 0;
                });
                
                if ($validPE->isNotEmpty()) {
                    // Pick the PE with highest OI change %
                    $bestOption = $validPE->sortByDesc('pe_oi_chg_pct')->first();
                    $bestOption->selected_type = 'PE';
                    $selectedPositions->push($bestOption);
                }
            }
        }

        if ($selectedPositions->isEmpty()) {
            return response()->json([
                'positions' => [],
                'totalInvestment' => 0,
                'totalProfit' => 0,
                'profitPercentage' => 0,
                'noOfPositions' => 0,
                'totalInvestmentRaw' => 0,
                'totalProfitRaw' => 0
            ]);
        }

        // ✅ Get lot sizes for all tokens
        $allTokens = $selectedPositions->map(function($pos) {
            return $pos->selected_type === 'CE' ? $pos->ce_token : $pos->pe_token;
        })->filter()->unique()->toArray();

        $lotSizes = DB::table('angel_api_instruments')
            ->whereIn('token', $allTokens)
            ->where('exch_seg', 'NFO')
            ->pluck('lotsize', 'token')
            ->toArray();

        // ✅ Get LTP data for profit calculation
        $ltpData = DB::table('symbol_ltps')
            ->select('symbol_token', 'ltp', 'highest_ltp', 'highest_time', 'updated_at')
            ->whereIn('symbol_token', $allTokens)
            ->get()
            ->keyBy('symbol_token');

        $totalInvestment = 0;
        $totalProfit = 0;
        $finalPositions = collect();

        foreach ($selectedPositions as $pos) {
            // Determine which option to trade based on selection
            if ($pos->selected_type === 'CE') {
                $buyToken = $pos->ce_token;
                $buySymbol = $pos->ce_symbol;
                $buyPrice = $pos->ce_close;
                $buildupType = 'Long Call (CE)';
            } else {
                $buyToken = $pos->pe_token;
                $buySymbol = $pos->pe_symbol;
                $buyPrice = $pos->pe_close;
                $buildupType = 'Long Put (PE)';
            }

            // ✅ CRITICAL VALIDATION: Skip if buy price is invalid
            if (!$buyPrice || $buyPrice <= 1) {
                continue; // Skip positions with invalid buy prices
            }

            // Get lot size
            $lotSize = $lotSizes[$buyToken] ?? null;
            if (!$lotSize || $lotSize < 1) {
                continue; // Skip if lot size is invalid
            }

            // Get current LTP
            $ltpRow = $ltpData[$buyToken] ?? null;
            $currentLtp = $ltpRow->ltp ?? null;

            // ✅ REALISTIC PROFIT CALCULATION
            // If no LTP data or LTP is invalid, assume position is at buy price (no profit/loss)
            if (!$currentLtp || $currentLtp <= 0) {
                $currentLtp = $buyPrice; // No change scenario
            }

            // Calculate investment (1 lot)
            $investment = $lotSize * $buyPrice;

            // Calculate profit/loss based on current LTP
            $currentValue = $lotSize * $currentLtp;
            $profit = $currentValue - $investment;

            // ✅ MINIMUM PROFIT THRESHOLD: Ignore tiny profits/losses (< ₹10)
            // This accounts for bid-ask spreads and transaction costs
            $MIN_THRESHOLD = 10;
            if (abs($profit) < $MIN_THRESHOLD) {
                $profit = 0; // Treat as breakeven
            }

            // Add to totals
            $totalInvestment += $investment;
            $totalProfit += $profit;

            // ✅ Build position object with realistic data
            $finalPositions->push((object)[
                'date' => $pos->date,
                'underlying' => $pos->underlying,
                'symbol_name' => $buySymbol,
                'symbol_token' => $buyToken,
                'ltp' => round($currentLtp, 2),
                'highest_ltp' => $ltpRow->highest_ltp ?? 0,
                'highest_time' => $ltpRow->highest_time ?? null,
                'transaction_type' => 'BUY',
                'lot_size' => $lotSize,
                'buy_quantity' => 1,
                'buy_price' => round($buyPrice, 2),
                'sell_quantity' => 0,
                'sell_price' => 0,
                'total_value' => round($investment, 2),
                'current_value' => round($currentValue, 2),
                'profit' => round($profit, 2),
                'profit_percentage' => $investment > 0 ? round(($profit / $investment) * 100, 2) : 0,
                'realised_profit' => 0,
                'unrealised_profit' => round($profit, 2),
                'trend' => $pos->trend,
                'buildup_type' => $buildupType,
                'oi_change_pct' => $pos->selected_type === 'CE' ? $pos->ce_oi_chg_pct : $pos->pe_oi_chg_pct
            ]);
        }

        // ✅ Calculate overall profit percentage
        $profitPercentage = $totalInvestment > 0
            ? round(($totalProfit / $totalInvestment) * 100, 2)
            : 0;

        return response()->json([
            'positions' => $finalPositions->values(),
            'totalInvestment' => number_format($totalInvestment, 2),
            'totalProfit' => number_format($totalProfit, 2),
            'profitPercentage' => $profitPercentage,
            'noOfPositions' => $finalPositions->count(),
            'totalInvestmentRaw' => $totalInvestment,
            'totalProfitRaw' => $totalProfit
        ]);
    }

    // NEW TREND
    public function trendAnalyst()
    {
        $pageTitle = 'Trend Analyst';
        return view($this->activeTemplate . 'user.portfolios.trend-analyst', compact('pageTitle'));
    }

    public function trendAnalystFetch(Request $request)
    {
        try {
            $dateFilter    = $request->get('date_filter', Carbon::today()->toDateString());
            $symbolFilter  = $request->get('symbol_filter', 'all');
            $tradeType     = $request->get('trade_type', 'all');
            $oi_change     = $request->get('oi_change', 'all');
            $strengthScore = $request->get('strength_score', 'all');

            // ✅ Get last 3 available trading dates
            $lastThreeDates = HistoricalOptionsData::whereDate('date', '<=', $dateFilter)
                ->where('ce_oi', '>', 0)
                ->where('pe_oi', '>', 0)
                ->distinct('date')
                ->orderBy('date', 'desc')
                ->limit(3)
                ->pluck('date')
                ->map(fn($date) => Carbon::parse($date)->toDateString())
                ->toArray();

            if (count($lastThreeDates) < 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient data available. Need at least 2 trading days.',
                    'positions' => []
                ]);
            }

            // ✅ Build base query
            $query = HistoricalOptionsData::whereIn('date', $lastThreeDates)
                ->where('ce_oi', '>', 0)
                ->where('pe_oi', '>', 0);

            if ($symbolFilter && $symbolFilter !== 'all') {
                $query->where('underlying', 'LIKE', '%' . strtoupper($symbolFilter) . '%');
            }

            $rows = $query->select(['date', 'underlying', 'future_oi', 'ce_oi', 'pe_oi'])->get();

            // ✅ Group by symbol + date
            $grouped = [];
            foreach ($rows as $row) {
                $underlying = $row->underlying;
                $date       = Carbon::parse($row->date)->toDateString();

                if (!isset($grouped[$underlying][$date])) {
                    $grouped[$underlying][$date] = [
                        'future_oi' => 0,
                        'ce_oi'     => [],
                        'pe_oi'     => []
                    ];
                }

                $grouped[$underlying][$date]['future_oi'] += $row->future_oi;
                $grouped[$underlying][$date]['ce_oi'][]   = $row->ce_oi;
                $grouped[$underlying][$date]['pe_oi'][]   = $row->pe_oi;
            }

            $signals = [];

            // ✅ Calculate sentiment
            foreach ($grouped as $underlying => $datesData) {
                $sortedDates = array_reverse($lastThreeDates); // oldest first
                $data = [];

                foreach ($sortedDates as $date) {
                    if (isset($datesData[$date])) {
                        $data[] = [
                            'date'       => $date,
                            'future_oi'  => $datesData[$date]['future_oi'],
                            'ce_oi'      => $datesData[$date]['ce_oi'],
                            'pe_oi'      => $datesData[$date]['pe_oi'],
                        ];
                    }
                }

                if (count($data) < 2) continue;

                $score = 0;
                $latestDate = '';
                $futureOiSum = 0;
                $ceOiSum = 0;
                $peOiSum = 0;

                for ($i = 1; $i < count($data); $i++) {
                    $prev = $data[$i - 1];
                    $curr = $data[$i];

                    $prev_ce_sum = array_sum($prev['ce_oi']);
                    $prev_pe_sum = array_sum($prev['pe_oi']);
                    $curr_ce_sum = array_sum($curr['ce_oi']);
                    $curr_pe_sum = array_sum($curr['pe_oi']);

                    $ce_change  = $curr_ce_sum - $prev_ce_sum;
                    $pe_change  = $curr_pe_sum - $prev_pe_sum;
                    $fut_change = $curr['future_oi'] - $prev['future_oi'];

                    // ✅ Score logic
                    if ($pe_change > 0 && $ce_change <= 0) {
                        $score += 2; // Strong Bullish
                    } elseif ($pe_change > 0 && $ce_change > 0 && $pe_change > $ce_change) {
                        $score += 1; // Mild Bullish
                    } elseif ($ce_change > 0 && $pe_change <= 0) {
                        $score -= 2; // Strong Bearish
                    } elseif ($ce_change > 0 && $pe_change > 0 && $ce_change > $pe_change) {
                        $score -= 1; // Mild Bearish
                    }

                    // ✅ Future OI confirmation
                    if ($fut_change > 0 && $score > 0) {
                        $score += 1;
                    } elseif ($fut_change > 0 && $score < 0) {
                        $score -= 1;
                    }

                    if ($i === count($data) - 1) {
                        $latestDate = $curr['date'];
                        $futureOiSum = $curr['future_oi'];
                        $ceOiSum = $curr_ce_sum;
                        $peOiSum = $curr_pe_sum;
                    }
                }

                // ✅ Sentiment classification
                if ($score >= 4) {
                    $signal = "Strong Bullish";
                } elseif ($score >= 2 && $score < 4) {
                    $signal = "Bullish Breakout Possible";
                } elseif ($score <= -4) {
                    $signal = "Strong Bearish";
                } elseif ($score <= -2 && $score > -4) {
                    $signal = "Bearish Breakout Possible";
                } else {
                    $signal = "Neutral";
                }

                // ✅ Sentiment filter
                if ($tradeType !== 'all' && $signal !== $tradeType) continue;

                // ✅ Strength score filter
                if ($strengthScore !== 'all') {
                    if ($strengthScore === 'gt2' && $score <= 2) continue;
                    if ($strengthScore === 'lt-2' && $score >= -2) continue;
                    if ($strengthScore === 'between' && ($score < -1 || $score > 1)) continue;
                }

                $signals[] = [
                    'date'          => $latestDate,
                    'symbol_name'   => $underlying,
                    'future_oi_sum' => number_format($futureOiSum),
                    'ce_oi_sum'     => number_format($ceOiSum),
                    'pe_oi_sum'     => number_format($peOiSum),
                    'signal'        => $signal,
                    'score'         => $score,
                ];
            }

            // ✅ Sorting logic
            if ($oi_change !== 'all') {
                usort($signals, function ($a, $b) use ($oi_change) {
                    switch ($oi_change) {
                        case 'ce_low_to_high': return $a['ce_oi_sum'] <=> $b['ce_oi_sum'];
                        case 'ce_high_to_low': return $b['ce_oi_sum'] <=> $a['ce_oi_sum'];
                        case 'pe_low_to_high': return $a['pe_oi_sum'] <=> $b['pe_oi_sum'];
                        case 'pe_high_to_low': return $b['pe_oi_sum'] <=> $a['pe_oi_sum'];
                        case 'fut_low_to_high': return $a['future_oi_sum'] <=> $b['future_oi_sum'];
                        case 'fut_high_to_low': return $b['future_oi_sum'] <=> $a['future_oi_sum'];
                        default: return 0;
                    }
                });
            } else {
                // Default sort: Strong Bullish → Bullish → Neutral → Bearish
                $order = [
                    'Strong Bullish'           => 1,
                    'Bullish Breakout Possible'=> 2,
                    'Neutral'                  => 3,
                    'Bearish Breakout Possible'=> 4,
                    'Strong Bearish'           => 5,
                ];
                usort($signals, fn($a, $b) => $order[$a['signal']] <=> $order[$b['signal']]);
            }

            return response()->json([
                'status'          => 'success',
                'message'         => 'Data fetched successfully',
                'date_filter'     => $dateFilter,
                'last_three_dates'=> $lastThreeDates,
                'positions'       => $signals,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing data: ' . $e->getMessage(),
                'positions' => []
            ]);
        }
    }

    // NEW EARLY TREND    
    public function earlyTrendAnalyst()
    {
        $pageTitle = 'Early Trend Analyst';
        return view($this->activeTemplate . 'user.portfolios.early-trend-analyst', compact('pageTitle'));
    }

    public function earlyTrendAnalystFetch(Request $request)
    {
        try {
            $dateFilter    = $request->get('date_filter', Carbon::today()->toDateString());
            $symbolFilter  = $request->get('symbol_filter', 'all');
            $tradeType     = $request->get('trade_type', 'all');
            $oi_change     = $request->get('oi_change', 'all');
            $strengthScore = $request->get('strength_score', 'all');

            // ✅ Get last 3 available trading dates
            $lastThreeDates = EarlyHistoricalOptionsData::whereDate('date', '<=', $dateFilter)
                ->where('ce_oi', '>', 0)
                ->where('pe_oi', '>', 0)
                ->distinct('date')
                ->orderBy('date', 'desc')
                ->limit(3)
                ->pluck('date')
                ->map(fn($date) => Carbon::parse($date)->toDateString())
                ->toArray();

            if (count($lastThreeDates) < 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient data available. Need at least 2 trading days.',
                    'positions' => []
                ]);
            }

            // ✅ Build base query
            $query = EarlyHistoricalOptionsData::whereIn('date', $lastThreeDates)
                ->where('ce_oi', '>', 0)
                ->where('pe_oi', '>', 0);

            if ($symbolFilter && $symbolFilter !== 'all') {
                $query->where('underlying', 'LIKE', '%' . strtoupper($symbolFilter) . '%');
            }

            $rows = $query->select(['date', 'underlying', 'future_oi', 'ce_oi', 'pe_oi'])->get();

            // ✅ Group by symbol + date
            $grouped = [];
            foreach ($rows as $row) {
                $underlying = $row->underlying;
                $date       = Carbon::parse($row->date)->toDateString();

                if (!isset($grouped[$underlying][$date])) {
                    $grouped[$underlying][$date] = [
                        'future_oi' => 0,
                        'ce_oi'     => [],
                        'pe_oi'     => []
                    ];
                }

                $grouped[$underlying][$date]['future_oi'] += $row->future_oi;
                $grouped[$underlying][$date]['ce_oi'][]   = $row->ce_oi;
                $grouped[$underlying][$date]['pe_oi'][]   = $row->pe_oi;
            }

            $signals = [];

            // ✅ Calculate sentiment
            foreach ($grouped as $underlying => $datesData) {
                $sortedDates = array_reverse($lastThreeDates); // oldest first
                $data = [];

                foreach ($sortedDates as $date) {
                    if (isset($datesData[$date])) {
                        $data[] = [
                            'date'       => $date,
                            'future_oi'  => $datesData[$date]['future_oi'],
                            'ce_oi'      => $datesData[$date]['ce_oi'],
                            'pe_oi'      => $datesData[$date]['pe_oi'],
                        ];
                    }
                }

                if (count($data) < 2) continue;

                $score = 0;
                $latestDate = '';
                $futureOiSum = 0;
                $ceOiSum = 0;
                $peOiSum = 0;

                for ($i = 1; $i < count($data); $i++) {
                    $prev = $data[$i - 1];
                    $curr = $data[$i];

                    $prev_ce_sum = array_sum($prev['ce_oi']);
                    $prev_pe_sum = array_sum($prev['pe_oi']);
                    $curr_ce_sum = array_sum($curr['ce_oi']);
                    $curr_pe_sum = array_sum($curr['pe_oi']);

                    $ce_change  = $curr_ce_sum - $prev_ce_sum;
                    $pe_change  = $curr_pe_sum - $prev_pe_sum;
                    $fut_change = $curr['future_oi'] - $prev['future_oi'];

                    // ✅ Score logic
                    if ($pe_change > 0 && $ce_change <= 0) {
                        $score += 2; // Strong Bullish
                    } elseif ($pe_change > 0 && $ce_change > 0 && $pe_change > $ce_change) {
                        $score += 1; // Mild Bullish
                    } elseif ($ce_change > 0 && $pe_change <= 0) {
                        $score -= 2; // Strong Bearish
                    } elseif ($ce_change > 0 && $pe_change > 0 && $ce_change > $pe_change) {
                        $score -= 1; // Mild Bearish
                    }

                    // ✅ Future OI confirmation
                    if ($fut_change > 0 && $score > 0) {
                        $score += 1;
                    } elseif ($fut_change > 0 && $score < 0) {
                        $score -= 1;
                    }

                    if ($i === count($data) - 1) {
                        $latestDate = $curr['date'];
                        $futureOiSum = $curr['future_oi'];
                        $ceOiSum = $curr_ce_sum;
                        $peOiSum = $curr_pe_sum;
                    }
                }

                // ✅ Sentiment classification
                if ($score >= 4) {
                    $signal = "Strong Bullish";
                } elseif ($score >= 2 && $score < 4) {
                    $signal = "Bullish Breakout Possible";
                } elseif ($score <= -4) {
                    $signal = "Strong Bearish";
                } elseif ($score <= -2 && $score > -4) {
                    $signal = "Bearish Breakout Possible";
                } else {
                    $signal = "Neutral";
                }

                // ✅ Sentiment filter
                if ($tradeType !== 'all' && $signal !== $tradeType) continue;

                // ✅ Strength score filter
                if ($strengthScore !== 'all') {
                    if ($strengthScore === 'gt2' && $score <= 2) continue;
                    if ($strengthScore === 'lt-2' && $score >= -2) continue;
                    if ($strengthScore === 'between' && ($score < -1 || $score > 1)) continue;
                }

                $signals[] = [
                    'date'          => $latestDate,
                    'symbol_name'   => $underlying,
                    'future_oi_sum' => number_format($futureOiSum),
                    'ce_oi_sum'     => number_format($ceOiSum),
                    'pe_oi_sum'     => number_format($peOiSum),
                    'signal'        => $signal,
                    'score'         => $score,
                ];
            }

            // ✅ Sorting logic
            if ($oi_change !== 'all') {
                usort($signals, function ($a, $b) use ($oi_change) {
                    switch ($oi_change) {
                        case 'ce_low_to_high': return $a['ce_oi_sum'] <=> $b['ce_oi_sum'];
                        case 'ce_high_to_low': return $b['ce_oi_sum'] <=> $a['ce_oi_sum'];
                        case 'pe_low_to_high': return $a['pe_oi_sum'] <=> $b['pe_oi_sum'];
                        case 'pe_high_to_low': return $b['pe_oi_sum'] <=> $a['pe_oi_sum'];
                        case 'fut_low_to_high': return $a['future_oi_sum'] <=> $b['future_oi_sum'];
                        case 'fut_high_to_low': return $b['future_oi_sum'] <=> $a['future_oi_sum'];
                        default: return 0;
                    }
                });
            } else {
                // Default sort: Strong Bullish → Bullish → Neutral → Bearish
                $order = [
                    'Strong Bullish'           => 1,
                    'Bullish Breakout Possible'=> 2,
                    'Neutral'                  => 3,
                    'Bearish Breakout Possible'=> 4,
                    'Strong Bearish'           => 5,
                ];
                usort($signals, fn($a, $b) => $order[$a['signal']] <=> $order[$b['signal']]);
            }

            return response()->json([
                'status'          => 'success',
                'message'         => 'Data fetched successfully',
                'date_filter'     => $dateFilter,
                'last_three_dates'=> $lastThreeDates,
                'positions'       => $signals,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error processing data: ' . $e->getMessage(),
                'positions' => []
            ]);
        }
    }

    // SUPER TREND CONTROLELR 
    public function supertrendAnalysis()
    {
        $pageTitle = 'Supertrend Analysis';
        $route = route('user.supertrend-fetch');

        // Get available underlyings for filter
        $underlyings = HistoricalOneHour::distinct()
            ->pluck('underlying')
            ->sort()
            ->values();

        return view($this->activeTemplate . 'user.portfolios.supertrend-analysis', compact(
            'pageTitle',
            'route',
            'underlyings'
        ));
    }

    public function supertrendFetch(Request $request)
    {
        $underlying = $request->get('underlying', 'NIFTY');
        $instrumentType = $request->get('type', 'future'); // future, ce, pe
        $days = $request->get('days', 50);
        $atrPeriod = $request->get('atr_period', 10);
        $multiplier = $request->get('multiplier', 3);

        // Fetch data based on instrument type
        $query = HistoricalOneHour::where('underlying', $underlying)
            ->where('type', $instrumentType)
            ->whereDate('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date', 'ASC')
            ->get();

        if ($query->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No data found for the selected criteria.',
                'data' => []
            ]);
        }

        // Map the data
        $historicalData = $query->map(function ($item) {
            // Handle date - could be string or Carbon object
            $date = $item->date;
            if (is_object($date) && method_exists($date, 'format')) {
                $date = $date->format('Y-m-d H:i:s');
            }
            
            return [
                'date' => (string)$date,
                'symbol' => $item->symbol ?? 'N/A',
                'open' => (float)$item->open,
                'high' => (float)$item->high,
                'low' => (float)$item->low,
                'close' => (float)$item->close,
                'volume' => (int)$item->volume,
            ];
        })->toArray();

        // Filter out null values
        $historicalData = array_filter($historicalData, function ($item) {
            return !empty($item['open']) && !empty($item['high']) && !empty($item['low']) && !empty($item['close']);
        });

        if (count($historicalData) < $atrPeriod) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient data for calculation. Need at least ' . $atrPeriod . ' candles.',
                'data' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'underlying' => $underlying,
            'type' => $instrumentType,
            'atr_period' => $atrPeriod,
            'multiplier' => $multiplier,
            'data' => array_values($historicalData),
            'message' => 'Data retrieved successfully'
        ]);
    }

    public function calculateAndSaveSupertrend(Request $request)
    {
        $underlying = $request->get('underlying', 'NIFTY');
        $instrumentType = $request->get('type', 'future');
        $atrPeriod = $request->get('atr_period', 10);
        $multiplier = $request->get('multiplier', 3);

        // Fetch latest records
        $records = HistoricalOneHour::where('underlying', $underlying)
            ->where('type', $instrumentType)
            ->orderBy('date', 'ASC')
            ->get();

        if ($records->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No data found'
            ]);
        }

        // Convert to array format
        $ohlcData = $records->map(function ($item) {
            return [
                'date' => $item->date,
                'open' => (float)$item->open,
                'high' => (float)$item->high,
                'low' => (float)$item->low,
                'close' => (float)$item->close,
                'volume' => (int)$item->volume,
                'id' => $item->id
            ];
        })->toArray();

        // Calculate Supertrend
        $supertrendCalculator = new SupertrendCalculator($ohlcData, $atrPeriod, $multiplier);
        $results = $supertrendCalculator->calculateSupertrend();

        // Save results to database
        foreach ($results as $result) {
            HistoricalOneHour::where('id', $result['id'])
                ->update([
                    'atr' => $result['atr'],
                    'supertrend' => $result['supertrend'],
                    'supertrend_direction' => $result['direction'],
                    'supertrend_signal' => $result['signal'],
                    'upper_band' => $result['basicUpperBand'],
                    'lower_band' => $result['basicLowerBand'],
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Supertrend calculations saved',
            'count' => count($results)
        ]);
    }

}