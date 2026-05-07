<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SymbolList;
use App\Models\HistoricalOptionsData;
use App\Models\EarlyHistoricalOptionsData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HistoricalOptionAnalysis extends Controller
{
    public function historicalAnalysis()
    {
        $pageTitle = 'Historical Options Analysis';
        
        $symbols = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        // $symbols = SymbolList::select('symbol')
        //     ->distinct()
        //     ->orderBy('symbol')
        //     ->pluck('symbol')
        //     ->whereIn('symbol', $neededSymbol)
        //     ->toArray();
        
        return view($this->activeTemplate . 'user.option.analysis.historical-analysis', compact('pageTitle', 'symbols'));
    }

    public function historicalAnalysisFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $searchTerm   = $request->get('search_term', '');
        $tradeType    = $request->get('trade_type', 'all'); // ✅ added
        $strengthScore = $request->get('strength_score');

        // ✅ If no date provided, automatically get the latest available date
        if (empty($dateFilter)) {
            $latestDate = HistoricalOptionsData::max('date');
            if (!$latestDate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No option data available in database.'
                ]);
            }
            $dateFilter = $latestDate;
        }

        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];
        
        // Base query – only CE & PE relevant data
        $baseQuery = HistoricalOptionsData::query()
            ->select([
                'id', 'date', 'underlying',
                'ce_symbol', 'ce_oi_chg_pct', 'ce_oi_change', 'ce_price_change',
                'pe_symbol', 'pe_oi_chg_pct', 'pe_oi_change', 'pe_price_change'
            ])->whereIn('underlying', $neededSymbol);
            // ->whereDate('date', $dateFilter);
            if ($dateFilter === 'all') {
                // show all data (history)
            } else {
                $baseQuery->whereDate('date', $dateFilter);
            }

        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('ce_symbol', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('pe_symbol', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('underlying', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Fetch data for same underlying + same date
        $data = $baseQuery->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No option data found for the selected or latest date.'
            ]);
        }

        // Group by underlying symbol (each stock/index)
        // $grouped = $data->groupBy('underlying');
        $grouped = $data->groupBy(function ($item) {
            return $item->date . '|' . $item->underlying;
        });
        $results = [];

        // foreach ($grouped as $underlying => $records) {
        //     $ceOiChanges = [];
        //     $peOiChanges = [];
        //     $priceChange = null;

        //     foreach ($records as $row) {
        //         if (!is_null($row->ce_oi_chg_pct)) {
        //             $ceOiChanges[] = (float) $row->ce_oi_chg_pct;
        //         }
        //         if (!is_null($row->pe_oi_chg_pct)) {
        //             $peOiChanges[] = (float) $row->pe_oi_chg_pct;
        //         }

        //         // Capture one combined price change (optional)
        //         if (is_null($priceChange)) {
        //             $priceChange = (float) (($row->ce_price_change + $row->pe_price_change) / 2);
        //         }
        //     }

        //     // Apply your new OI analysis logic
        //     $analysis = $this->analyzeData($ceOiChanges, $peOiChanges, $priceChange);

        //     $results[] = [
        //         'underlying'       => $underlying,
        //         'date'             => $dateFilter,
        //         'avg_ce_oi_change' => $analysis['avg_ce_oi_change'],
        //         'avg_pe_oi_change' => $analysis['avg_pe_oi_change'],
        //         'sentiment'        => $analysis['sentiment'],
        //         'pattern'          => $analysis['pattern'],
        //         'strength_score'   => $analysis['strength_score'],
        //         'support_zone'     => $analysis['support_zone'],
        //         'resistance_zone'  => $analysis['resistance_zone'],
        //     ];
        // }

        foreach ($grouped as $key => $records) {
            [$date, $underlying] = explode('|', $key);

            $ceOiChanges = [];
            $peOiChanges = [];
            $priceChange = null;

            foreach ($records as $row) {
                if (!is_null($row->ce_oi_chg_pct)) {
                    $ceOiChanges[] = (float) $row->ce_oi_chg_pct;
                }
                if (!is_null($row->pe_oi_chg_pct)) {
                    $peOiChanges[] = (float) $row->pe_oi_chg_pct;
                }

                if (is_null($priceChange)) {
                    $priceChange = (float) (($row->ce_price_change + $row->pe_price_change) / 2);
                }
            }

            $analysis = $this->analyzeData($ceOiChanges, $peOiChanges, $priceChange);

            $results[] = [
                'underlying'       => $underlying,
                'date'             => $date,
                'avg_ce_oi_change' => $analysis['avg_ce_oi_change'],
                'avg_pe_oi_change' => $analysis['avg_pe_oi_change'],
                'sentiment'        => $this->swapSentimentWords($analysis['sentiment']),
                'pattern'          => $analysis['pattern'],
                'strength_score'   => $analysis['strength_score'],
                'support_zone'     => $analysis['support_zone'],
                'resistance_zone'  => $analysis['resistance_zone'],
            ];
        }

        // ✅ Apply Trend Type filter after sentiment calculation
        if ($tradeType && $tradeType !== 'all') {
            $results = collect($results)->filter(function ($item) use ($tradeType) {
                return strcasecmp($item['sentiment'], $tradeType) === 0;
            })->values()->toArray();
        }

        if (!empty($strengthScore)) {
            $results = collect($results)->filter(function ($item) use ($strengthScore) {
                return $item['strength_score'] >= (float)$strengthScore;
            })->values()->toArray();
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Option sentiment analysis for {$dateFilter} completed successfully.",
            'data'    => collect($results)->sortByDesc('date')->values()->toArray()
        ]);
    }

    private function swapSentimentWords($text)
    {
        // Safe swapping using temporary placeholder
        $text = str_ireplace('Bullish', '__TEMP_BULL__', $text);
        $text = str_ireplace('Bearish', 'Bullish', $text);
        $text = str_ireplace('__TEMP_BULL__', 'Bearish', $text);

        return $text;
    }

    private function analyzeData(array $ceOiChanges, array $peOiChanges, ?float $priceChange = null): array
    {
        $avgCe = $this->average($ceOiChanges);
        $avgPe = $this->average($peOiChanges);

        // Define sentiment based on OI dynamics
        $sentiment = "No clear trend";
        $strength = 0;
        $pattern = "";

        // === PURE OI DIRECTIONAL LOGIC ===
        if ($avgCe < 0 && $avgPe > 0) {
            // Short covering + Put writing
            $sentiment = "Strong Bullish";
            $pattern = "CE unwinding, PE writing (short covering rally)";
        } 
        elseif ($avgCe > 0 && $avgPe < 0) {
            // Call writing + Put unwinding
            $sentiment = "Strong Bearish";
            $pattern = "CE writing, PE unwinding (downside continuation)";
        } 
        elseif ($avgCe > 0 && $avgPe > 0) {
            // Breakout scenario
            if ($avgCe > $avgPe) {
                $sentiment = "Bullish Breakout Possible";
                $pattern = "Both sides writing, CE > PE (Call writers may be trapped)";
            } else {
                $sentiment = "Bearish Breakout Possible";
                $pattern = "Both sides writing, PE > CE (Put writers may be trapped)";
            }
        } 
        elseif ($avgCe < 0 && $avgPe < 0) {
            // Unwinding / neutral
            $sentiment = "Neutral / Unwinding";
            $pattern = "Both CE and PE closing positions (low conviction zone)";
        }

        // === ADD STRENGTH SCORE ===
        $strength = round(min(abs($avgPe - $avgCe) * 1.5, 100), 2);

        // === DETECT SUPPORT / RESISTANCE ===
        $support = $this->detectSupport($peOiChanges);
        $resistance = $this->detectResistance($ceOiChanges);

        // === FINAL OUTPUT ===
        return [
            'avg_ce_oi_change' => round($avgCe, 2),
            'avg_pe_oi_change' => round($avgPe, 2),
            'sentiment' => $sentiment,
            'pattern' => $pattern,
            'strength_score' => $strength,
            'support_zone' => $support,
            'resistance_zone' => $resistance
        ];
    }

    private function detectSupport(array $peOiChanges): string
    {
        $max = max($peOiChanges);
        $index = array_search($max, $peOiChanges);
        return "Support near Strike #" . ($index + 1) . " (PE OI +{$max}%)";
    }

    private function detectResistance(array $ceOiChanges): string
    {
        $max = max($ceOiChanges);
        $index = array_search($max, $ceOiChanges);
        return "Resistance near Strike #" . ($index + 1) . " (CE OI +{$max}%)";
    }

    private function average(array $arr): float
    {
        if (empty($arr)) return 0;
        return array_sum($arr) / count($arr);
    }

    // EARLY HISTOICAL 
    public function earlyHistoricalAnalysis()
    {
        $pageTitle = 'Early Historical Options Analysis';
        // $symbols = SymbolList::select('symbol')
        //     ->distinct()
        //     ->orderBy('symbol')
        //     ->pluck('symbol')
        //     ->toArray();
            
        $symbols = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];
        
        return view($this->activeTemplate . 'user.option.analysis.early-historical-analysis', compact('pageTitle', 'symbols'));
    }

    public function earlyHistoricalAnalysisFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $searchTerm   = $request->get('search_term', '');
        $tradeType    = $request->get('trade_type', 'all'); // ✅ added
        $strengthScore = $request->get('strength_score');

        // ✅ If no date provided, automatically get the latest available date
        if (empty($dateFilter)) {
            $latestDate = EarlyHistoricalOptionsData::max('date');
            if (!$latestDate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No option data available in database.'
                ]);
            }
            $dateFilter = $latestDate;
        }
        
        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        // Base query – only CE & PE relevant data
        $baseQuery = EarlyHistoricalOptionsData::query()
            ->select([
                'id', 'date', 'underlying',
                'ce_symbol', 'ce_oi_chg_pct', 'ce_oi_change', 'ce_price_change',
                'pe_symbol', 'pe_oi_chg_pct', 'pe_oi_change', 'pe_price_change'
            ])->whereIn('underlying', $neededSymbol)
            ->whereDate('date', $dateFilter);

        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('ce_symbol', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('pe_symbol', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('underlying', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Fetch data for same underlying + same date
        $data = $baseQuery->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No option data found for the selected or latest date.'
            ]);
        }

        // Group by underlying symbol (each stock/index)
        $grouped = $data->groupBy('underlying');
        $results = [];

        foreach ($grouped as $underlying => $records) {
            $ceOiChanges = [];
            $peOiChanges = [];
            $priceChange = null;

            foreach ($records as $row) {
                if (!is_null($row->ce_oi_chg_pct)) {
                    $ceOiChanges[] = (float) $row->ce_oi_chg_pct;
                }
                if (!is_null($row->pe_oi_chg_pct)) {
                    $peOiChanges[] = (float) $row->pe_oi_chg_pct;
                }

                // Capture one combined price change (optional)
                if (is_null($priceChange)) {
                    $priceChange = (float) (($row->ce_price_change + $row->pe_price_change) / 2);
                }
            }

            // Apply your new OI analysis logic
            $analysis = $this->analyzeData($ceOiChanges, $peOiChanges, $priceChange);

            $results[] = [
                'underlying'       => $underlying,
                'date'             => $dateFilter,
                'avg_ce_oi_change' => $analysis['avg_ce_oi_change'],
                'avg_pe_oi_change' => $analysis['avg_pe_oi_change'],
                'sentiment'        => $this->swapSentimentWords($analysis['sentiment']),
                'pattern'          => $analysis['pattern'],
                'strength_score'   => $analysis['strength_score'],
                'support_zone'     => $analysis['support_zone'],
                'resistance_zone'  => $analysis['resistance_zone'],
            ];
        }

        // ✅ Apply Trend Type filter after sentiment calculation
        if ($tradeType && $tradeType !== 'all') {
            $results = collect($results)->filter(function ($item) use ($tradeType) {
                return strcasecmp($item['sentiment'], $tradeType) === 0;
            })->values()->toArray();
        }

        if (!empty($strengthScore)) {
            $results = collect($results)->filter(function ($item) use ($strengthScore) {
                return $item['strength_score'] >= (float)$strengthScore;
            })->values()->toArray();
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Option sentiment analysis for {$dateFilter} completed successfully.",
            'data'    => $results
        ]);
    }


    // PORTFOLIO LOGIC
    // public function newHistoricalPortfolio()
    // {
    //     $pageTitle = 'Portfolios - New Data';
    //     $route = route('user.historical.early-new-portfolio');
    //     return view($this->activeTemplate . 'user.portfolios.new-portfolio', compact('pageTitle', 'route'));
    // }

    // public function newHistoricalPortfolioFetch(Request $request)
    // {
    //     $dateFilter = $request->get('date_filter');
    //     $typeFilter = $request->get('type_filter');

    //     // ✅ Latest available date
    //     $latestDate = DB::table('historical_options_data')->orderByDesc('id')->value('date');
    //     $filterDate = $dateFilter ?: $latestDate;

    //     // ✅ Fetch CE & PE candidates
    //     $symbolsData = DB::table('historical_options_data')
    //         ->select(
    //             'underlying',
    //             'ce_symbol', 'ce_token', 'ce_close', 'ce_oi_chg_pct',
    //             'pe_symbol', 'pe_token', 'pe_close', 'pe_oi_chg_pct',
    //             'trend', 'date'
    //         )
    //         ->whereDate('date', $filterDate)
    //         ->when($typeFilter, fn($q) => $q->where('trend', $typeFilter))
    //         ->get();

    //     if ($symbolsData->isEmpty()) {
    //         return response()->json([
    //             'positions' => [],
    //             'totalInvestment' => 0,
    //             'totalProfit' => 0,
    //             'profitPercentage' => 0,
    //             'noOfPositions' => 0,
    //             'totalInvestmentRaw' => 0,
    //             'totalProfitRaw' => 0
    //         ]);
    //     }

    //     // ✅ Group by underlying to avoid duplicates
    //     $grouped = $symbolsData->groupBy('underlying');
    //     $ceFiltered = collect();
    //     $peFiltered = collect();

    //     foreach ($grouped as $rows) {
    //         $bestCE = $rows->sortByDesc('ce_oi_chg_pct')->first();
    //         $bestPE = $rows->sortByDesc('pe_oi_chg_pct')->first();

    //         if ($bestCE && $bestCE->ce_token) $ceFiltered->push($bestCE);
    //         if ($bestPE && $bestPE->pe_token) $peFiltered->push($bestPE);
    //     }

    //     $positions = $ceFiltered->merge($peFiltered)->values();

    //     // ✅ Fetch lot sizes & LTP
    //     $allTokens = $positions->pluck('ce_token')->merge($positions->pluck('pe_token'))->filter()->unique()->toArray();

    //     $lotSizes = DB::table('angel_api_instruments')
    //         ->whereIn('token', $allTokens)
    //         ->where('exch_seg', 'NFO')
    //         ->pluck('lotsize', 'token')
    //         ->toArray();

    //     $ltpData = DB::table('symbol_ltps')
    //         ->select('symbol_token', 'ltp', 'highest_ltp', 'highest_time', 'updated_at')
    //         ->get()
    //         ->keyBy('symbol_token');

    //     // ✅ Calculate profit & investment
    //     $totalInvestment = 0;
    //     $totalProfit = 0;
    //     $finalPositions = collect();

    //     foreach ($positions as $pos) {
    //         $isBullish = in_array($pos->trend, ["Strong Bullish", "Mild Bullish"]);
    //         $buyToken = $isBullish ? $pos->ce_token : $pos->pe_token;
    //         $buySymbol = $isBullish ? $pos->ce_symbol : $pos->pe_symbol;
    //         $buyPrice = $isBullish ? $pos->ce_close : $pos->pe_close;
    //         $buildupType = $isBullish ? 'Buy CE' : 'Buy PE';

    //         $ltpRow = $ltpData[$buyToken] ?? null;
    //         $latestLtp = $ltpRow->ltp ?? $buyPrice;
    //         $lotSize = $lotSizes[$buyToken] ?? 1;

    //         $investment = $lotSize * $buyPrice;
    //         $profit = round(($latestLtp * $lotSize) - $investment, 2);

    //         $totalInvestment += $investment;
    //         $totalProfit += $profit;

    //         $finalPositions->push((object)[
    //             'date' => $pos->date,
    //             'symbol_name' => $buySymbol,
    //             'symbol_token' => $buyToken,
    //             'ltp' => $latestLtp,
    //             'highest_ltp' => $ltpRow->highest_ltp ?? 0,
    //             'highest_time' => $ltpRow->highest_time ?? null,
    //             'transaction_type' => 'BUY',
    //             'lot_size' => $lotSize,
    //             'buy_quantity' => 1,
    //             'buy_price' => $buyPrice,
    //             'sell_quantity' => 0,
    //             'sell_price' => 0,
    //             'total_value' => $investment,
    //             'profit' => $profit,
    //             'realised_profit' => 0,
    //             'unrealised_profit' => $profit,
    //             'trend' => $pos->trend,
    //             'buildup_type' => $buildupType,
    //         ]);
    //     }

    //     $profitPercentage = $totalInvestment > 0
    //         ? round(($totalProfit / $totalInvestment) * 100, 2)
    //         : 0;

    //     return response()->json([
    //         'positions' => $finalPositions,
    //         'totalInvestment' => number_format($totalInvestment),
    //         'totalProfit' => number_format($totalProfit),
    //         'profitPercentage' => $profitPercentage,
    //         'noOfPositions' => $finalPositions->count(),
    //         'totalInvestmentRaw' => $totalInvestment,
    //         'totalProfitRaw' => $totalProfit
    //     ]);
    // }


    public function portfolioSentimentBased()
    {
        $pageTitle = 'Sentiment-Based Portfolio';
        $route = route('user.portfolio-sentiment-fetch');
        return view($this->activeTemplate . 'user.portfolios.sentiment-based', compact('pageTitle', 'route'));
    }

    public function portfolioSentimentBasedFetch(Request $request)
    {
        $dateFilter = $request->get('date_filter');
        $sentimentFilter = $request->get('sentiment_filter', 'Strong Bullish');
        $minStrength = $request->get('min_strength', 0);

        // ✅ Get latest date if not provided
        if (empty($dateFilter)) {
            $latestDate = DB::table('historical_options_data')->max('date');
            if (!$latestDate) {
                return response()->json([
                    'positions' => [],
                    'totalInvestment' => 0,
                    'totalProfit' => 0,
                    'profitPercentage' => 0,
                    'noOfPositions' => 0,
                    'totalInvestmentRaw' => 0,
                    'totalProfitRaw' => 0,
                    'message' => 'No data available'
                ]);
            }
            $dateFilter = $latestDate;
        }

        // ✅ Fetch all records for the date
        $data = DB::table('historical_options_data')
            ->select([
                'id', 'date', 'underlying',
                'ce_symbol', 'ce_token', 'ce_close', 'ce_oi_chg_pct', 'ce_oi_change', 'ce_price_change',
                'pe_symbol', 'pe_token', 'pe_close', 'pe_oi_chg_pct', 'pe_oi_change', 'pe_price_change'
            ])
            ->whereDate('date', $dateFilter)
            ->get();

        if ($data->isEmpty()) {
            return response()->json([
                'positions' => [],
                'totalInvestment' => 0,
                'totalProfit' => 0,
                'profitPercentage' => 0,
                'noOfPositions' => 0,
                'totalInvestmentRaw' => 0,
                'totalProfitRaw' => 0,
                'message' => 'No data found for selected date'
            ]);
        }

        // ✅ Group by underlying and apply sentiment analysis
        $grouped = $data->groupBy('underlying');
        $analyzedData = collect();

        foreach ($grouped as $underlying => $records) {
            $ceOiChanges = [];
            $peOiChanges = [];
            $allRecords = [];

            foreach ($records as $row) {
                if (!is_null($row->ce_oi_chg_pct)) {
                    $ceOiChanges[] = (float) $row->ce_oi_chg_pct;
                }
                if (!is_null($row->pe_oi_chg_pct)) {
                    $peOiChanges[] = (float) $row->pe_oi_chg_pct;
                }
                $allRecords[] = $row;
            }

            // ✅ Apply sentiment analysis logic
            $analysis = $this->analyzeSentiment($ceOiChanges, $peOiChanges);

            // ✅ Filter by sentiment and minimum strength
            if ($analysis['sentiment'] === $sentimentFilter && $analysis['strength_score'] >= $minStrength) {
                // ✅ Select best option based on sentiment
                $bestOption = $this->selectBestOption($allRecords, $analysis['sentiment'], $analysis);
                
                if ($bestOption) {
                    $bestOption->sentiment = $analysis['sentiment'];
                    $bestOption->pattern = $analysis['pattern'];
                    $bestOption->strength_score = $analysis['strength_score'];
                    $bestOption->avg_ce_oi_change = $analysis['avg_ce_oi_change'];
                    $bestOption->avg_pe_oi_change = $analysis['avg_pe_oi_change'];
                    $analyzedData->push($bestOption);
                }
            }
        }

        if ($analyzedData->isEmpty()) {
            return response()->json([
                'positions' => [],
                'totalInvestment' => 0,
                'totalProfit' => 0,
                'profitPercentage' => 0,
                'noOfPositions' => 0,
                'totalInvestmentRaw' => 0,
                'totalProfitRaw' => 0,
                'message' => 'No positions matching criteria'
            ]);
        }

        // ✅ Get lot sizes
        $allTokens = $analyzedData->map(function($item) {
            return $item->selected_token;
        })->filter()->unique()->toArray();

        $lotSizes = DB::table('angel_api_instruments')
            ->whereIn('token', $allTokens)
            ->where('exch_seg', 'NFO')
            ->pluck('lotsize', 'token')
            ->toArray();

        // ✅ Get LTP data
        $ltpData = DB::table('symbol_ltps')
            ->select('symbol_token', 'ltp', 'highest_ltp', 'highest_time', 'updated_at')
            ->whereIn('symbol_token', $allTokens)
            ->get()
            ->keyBy('symbol_token');

        $totalInvestment = 0;
        $totalProfit = 0;
        $finalPositions = collect();

        foreach ($analyzedData as $pos) {
            $buyToken = $pos->selected_token;
            $buySymbol = $pos->selected_symbol;
            $buyPrice = $pos->selected_price;
            $buildupType = $pos->selected_type;

            // ✅ Validate price
            if (!$buyPrice || $buyPrice <= 1) {
                continue;
            }

            // ✅ Get lot size
            $lotSize = $lotSizes[$buyToken] ?? null;
            if (!$lotSize || $lotSize < 1) {
                continue;
            }

            // ✅ Get current LTP
            $ltpRow = $ltpData[$buyToken] ?? null;
            $currentLtp = $ltpRow->ltp ?? $buyPrice;

            if (!$currentLtp || $currentLtp <= 0) {
                $currentLtp = $buyPrice;
            }

            // ✅ Calculate investment and profit
            $investment = $lotSize * $buyPrice;
            $currentValue = $lotSize * $currentLtp;
            $profit = $currentValue - $investment;

            // ✅ Minimum threshold (₹10)
            $MIN_THRESHOLD = 10;
            if (abs($profit) < $MIN_THRESHOLD) {
                $profit = 0;
            }

            $totalInvestment += $investment;
            $totalProfit += $profit;

            // ✅ Build position
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
                'sentiment' => $pos->sentiment,
                'pattern' => $pos->pattern,
                'strength_score' => $pos->strength_score,
                'buildup_type' => $buildupType,
                'avg_ce_oi' => round($pos->avg_ce_oi_change, 2),
                'avg_pe_oi' => round($pos->avg_pe_oi_change, 2)
            ]);
        }

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

    // ✅ Sentiment Analysis Logic (Same as your historical analysis)
    private function analyzeSentiment(array $ceOiChanges, array $peOiChanges): array
    {
        $avgCe = $this->average($ceOiChanges);
        $avgPe = $this->average($peOiChanges);

        $sentiment = "No clear trend";
        $strength = 0;
        $pattern = "";

        // === PURE OI DIRECTIONAL LOGIC ===
        if ($avgCe < 0 && $avgPe > 0) {
            $sentiment = "Strong Bullish";
            $pattern = "CE unwinding, PE writing (short covering rally)";
        } 
        elseif ($avgCe > 0 && $avgPe < 0) {
            $sentiment = "Strong Bearish";
            $pattern = "CE writing, PE unwinding (downside continuation)";
        } 
        elseif ($avgCe > 0 && $avgPe > 0) {
            if ($avgCe > $avgPe) {
                $sentiment = "Bullish Breakout Possible";
                $pattern = "Both sides writing, CE > PE (Call writers trapped)";
            } else {
                $sentiment = "Bearish Breakout Possible";
                $pattern = "Both sides writing, PE > CE (Put writers trapped)";
            }
        } 
        elseif ($avgCe < 0 && $avgPe < 0) {
            $sentiment = "Neutral / Unwinding";
            $pattern = "Both CE and PE closing (low conviction zone)";
        }

        $strength = round(min(abs($avgPe - $avgCe) * 1.5, 100), 2);

        return [
            'avg_ce_oi_change' => round($avgCe, 2),
            'avg_pe_oi_change' => round($avgPe, 2),
            'sentiment' => $sentiment,
            'pattern' => $pattern,
            'strength_score' => $strength
        ];
    }

    // ✅ Select Best Option Based on Sentiment
    private function selectBestOption(array $records, string $sentiment, array $analysis)
    {
        $isBullish = in_array($sentiment, ['Strong Bullish', 'Bullish Breakout Possible']);
        
        if ($isBullish) {
            // ✅ For bullish sentiment: Buy CE with highest OI change
            $validCE = collect($records)->filter(function($row) {
                return $row->ce_close > 1 && 
                    $row->ce_token && 
                    $row->ce_oi_chg_pct > 0;
            });
            
            if ($validCE->isEmpty()) {
                return null;
            }
            
            $best = $validCE->sortByDesc('ce_oi_chg_pct')->first();
            $best->selected_token = $best->ce_token;
            $best->selected_symbol = $best->ce_symbol;
            $best->selected_price = $best->ce_close;
            $best->selected_type = 'Long Call (CE)';
            
            return $best;
            
        } else {
            // ✅ For bearish sentiment: Buy PE with highest OI change
            $validPE = collect($records)->filter(function($row) {
                return $row->pe_close > 1 && 
                    $row->pe_token && 
                    $row->pe_oi_chg_pct > 0;
            });
            
            if ($validPE->isEmpty()) {
                return null;
            }
            
            $best = $validPE->sortByDesc('pe_oi_chg_pct')->first();
            $best->selected_token = $best->pe_token;
            $best->selected_symbol = $best->pe_symbol;
            $best->selected_price = $best->pe_close;
            $best->selected_type = 'Long Put (PE)';
            
            return $best;
        }
    }


    // VOLUME ANAYTICS
    public function volumeAnalytics()
    {
        $pageTitle = 'Volume-Based Options Analytics';
        // $symbols = SymbolList::select('symbol')
        //     ->distinct()
        //     ->orderBy('symbol')
        //     ->pluck('symbol')
        //     ->toArray();
        
        $symbols = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        return view($this->activeTemplate . 'user.option.analysis.volume-analytics', compact('pageTitle', 'symbols'));
    }


    public function volumeAnalyticsFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $searchTerm   = $request->get('search_term', '');
        $strengthScore = $request->get('strength_score');
        $tradeType = $request->get('trade_type', 'all'); // Bullish/Bearish filter

        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        // ✅ If no date provided, automatically get the latest available date
        if (empty($dateFilter)) {
            $latestDate = HistoricalOptionsData::max('date');
            if (!$latestDate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No volume data available in database.'
                ]);
            }
            $dateFilter = $latestDate;
        }

        // Base query
        $query = HistoricalOptionsData::query()->select('id', 'date', 'underlying', 'ce_symbol', 'pe_symbol', 'ce_volume', 'pe_volume')->whereIn('underlying', $neededSymbol);

        if ($dateFilter !== 'all') {
            $query->whereDate('date', $dateFilter);
        }

        if ($symbolFilter && $symbolFilter !== 'all') {
            $query->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('underlying', 'LIKE', "%{$searchTerm}%")
                ->orWhere('ce_symbol', 'LIKE', "%{$searchTerm}%")
                ->orWhere('pe_symbol', 'LIKE', "%{$searchTerm}%");
            });
        }

        $data = $query->get();

        if ($data->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'No records found for selected date.']);
        }

        // Group by underlying + date
        $grouped = $data->groupBy(function ($item) {
            return $item->date . '|' . $item->underlying;
        });

        $results = [];

        foreach ($grouped as $key => $records) {
            [$date, $underlying] = explode('|', $key);

            $ceVolumes = [];
            $peVolumes = [];

            foreach ($records as $row) {
                if (!is_null($row->ce_volume)) $ceVolumes[] = (float)$row->ce_volume;
                if (!is_null($row->pe_volume)) $peVolumes[] = (float)$row->pe_volume;
            }

            $analysis = $this->analyzeVolumeSignal($ceVolumes, $peVolumes);

            // Default
            $analysis['signal'] = "Bearish";

            // If NOT NIFTY/BANKNIFTY
            if (!in_array($underlying, ['NIFTY', 'BANKNIFTY'])) {

                if ($analysis['volume_ratio'] > 2) {
                    $analysis['signal'] = "Bullish";
                }

            } else {

                // For NIFTY & BANKNIFTY
                if ($analysis['volume_ratio'] > 1) {
                    $analysis['signal'] = "Bullish";
                }
            }

            $results[] = [
                'underlying' => $underlying,
                'date' => $date,
                'total_ce_volume' => $analysis['total_ce_volume'],
                'total_pe_volume' => $analysis['total_pe_volume'],
                'signal' => $analysis['signal'],
                'strength_score' => $analysis['strength_score'],
                'volume_ratio' => $analysis['volume_ratio']
            ];
        }

        // ✅ Filter by sentiment (Bullish/Bearish)
        if ($tradeType && $tradeType !== 'all') {
            $results = collect($results)->filter(fn($item) => strcasecmp($item['signal'], $tradeType) === 0)->values()->toArray();
        }

        // ✅ Filter by strength threshold
        if (!empty($strengthScore)) {
            $results = collect($results)->filter(fn($item) => $item['strength_score'] >= (float)$strengthScore)->values()->toArray();
        }

        return response()->json([
            'status' => 'success',
            'data' => collect($results)->sortByDesc('date')->values()->toArray()
        ]);
    }


    private function analyzeVolumeSignal(array $ceVolumes, array $peVolumes): array
    {
        $totalCE = array_sum($ceVolumes);
        $totalPE = array_sum($peVolumes);

        $signal = 'Neutral';
        $strength = 0;
        $ratio = 0;

        if ($totalPE + $totalCE > 0) {
            $ratio = round(($totalCE / max($totalPE, 1)), 2);
            $difference = abs($totalPE - $totalCE);
            $total = $totalPE + $totalCE;
            $strength = round(($difference / $total) * 100, 2);

            if ($totalPE > $totalCE) {
                $signal = 'Bullish';
            } elseif ($totalCE > $totalPE) {
                $signal = 'Bearish';
            }
        }

        return [
            'total_ce_volume' => $totalCE,
            'total_pe_volume' => $totalPE,
            'signal' => $signal,
            'strength_score' => $strength,
            'volume_ratio' => $ratio
        ];
    }

}
