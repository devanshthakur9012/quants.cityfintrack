<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SymbolList;
use App\Models\HistoricalOptionsData;
use App\Models\EarlyHistoricalOptionsData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class HistoricalOptionController extends Controller
{
    public function historicalOptions()
    {
        $pageTitle = 'Historical Options Data';
        
        // Get unique symbols for dropdown filter
        $symbols = SymbolList::select('symbol')
            ->distinct()
            ->orderBy('symbol')
            ->pluck('symbol')
            ->toArray();
        
        return view($this->activeTemplate . 'user.option.historical-data', compact('pageTitle', 'symbols'));
    }

    public function historicalOptionsFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $trendType    = $request->get('trade_type', 'all');
        $searchTerm   = $request->get('search_term', '');
        $changeType   = $request->get('change_type', 'all'); // % change sorting
        $oiChange     = $request->get('oi_change', 'all');   // absolute OI sorting

        // Base filters (NO trend filter here)
        $baseQuery = HistoricalOptionsData::query();

        if ($dateFilter) {
            $baseQuery->whereDate('date', $dateFilter);
        }

        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('future_symbol', 'LIKE', "%{$searchTerm}%")
                ->orWhere('ce_symbol', 'LIKE', "%{$searchTerm}%")
                ->orWhere('pe_symbol', 'LIKE', "%{$searchTerm}%")
                ->orWhere('underlying', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Table data query (APPLY trend filter here so table respects it)
        $dataQuery = (clone $baseQuery)
            ->select([
                'id','date','underlying',
                'future_symbol','future_volume','future_oi','future_price_change','future_oi_change','future_oi_chg_pct',
                'ce_symbol','ce_volume','ce_oi','ce_price_change','ce_oi_change','ce_oi_chg_pct',
                'pe_symbol','pe_volume','pe_oi','pe_price_change','pe_oi_change','pe_oi_chg_pct',
                'trend','futures_score','options_score','final_score'
            ]);

        // ✅ Apply trend filter
        if ($trendType && $trendType !== 'all') {
            $dataQuery->where('trend', $trendType);
        }

        // ✅ Sorting Priority:
        // 1) If oi_change given → sort by absolute OI values
        // 2) Else if change_type given → sort by % change fields
        // 3) Else default sort by date desc
        if ($oiChange && $oiChange !== 'all') {
            switch ($oiChange) {
                case 'ce_low_to_high':
                    $dataQuery->orderBy('ce_oi', 'asc');
                    break;

                case 'ce_high_to_low':
                    $dataQuery->orderBy('ce_oi', 'desc');
                    break;

                case 'pe_low_to_high':
                    $dataQuery->orderBy('pe_oi', 'asc');
                    break;

                case 'pe_high_to_low':
                    $dataQuery->orderBy('pe_oi', 'desc');
                    break;

                case 'fut_low_to_high':
                    $dataQuery->orderBy('future_oi', 'asc');
                    break;

                case 'fut_high_to_low':
                    $dataQuery->orderBy('future_oi', 'desc');
                    break;
            }
        } elseif ($changeType && $changeType !== 'all') {
            switch ($changeType) {
                case 'ce_low_to_high':
                    $dataQuery->orderBy('ce_oi_chg_pct', 'asc');
                    break;

                case 'ce_high_to_low':
                    $dataQuery->orderBy('ce_oi_chg_pct', 'desc');
                    break;

                case 'pe_low_to_high':
                    $dataQuery->orderBy('pe_oi_chg_pct', 'asc');
                    break;

                case 'pe_high_to_low':
                    $dataQuery->orderBy('pe_oi_chg_pct', 'desc');
                    break;

                case 'fut_low_to_high':
                    $dataQuery->orderBy('future_oi_chg_pct', 'asc');
                    break;

                case 'fut_high_to_low':
                    $dataQuery->orderBy('future_oi_chg_pct', 'desc');
                    break;
            }
        } else {
            // ✅ Default sorting only when no sorting params passed
            $dataQuery->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        // Paginate results
        $data = $dataQuery->paginate(50);

        // ---- Summary (counts across ALL pages) ----
        // Total across base filters (ignores trendType so cards show full breakdown)
        $totalRecords = (clone $baseQuery)->count();

        // Group counts by trend across the full filtered set (ignores trendType)
        $trendCounts = (clone $baseQuery)
            ->select('trend', DB::raw('COUNT(*) as cnt'))
            ->groupBy('trend')
            ->pluck('cnt', 'trend');

        $bullishMCount = $trendCounts['Mild Bullish']       ?? 0;
        $bullishSCount = $trendCounts['Strong Bullish']     ?? 0;
        $bearishMCount = $trendCounts['Mild Bearish']       ?? 0;
        $bearishSCount = $trendCounts['Strong Bearish']     ?? 0;
        $neutralCount  = $trendCounts['Neutral / Sideways'] ?? 0;

        // Render table HTML
        $html = view($this->activeTemplate . 'user.option.historical-data-table', [
            'data' => $data,
            'tradeType' => $trendType
        ])->render();

        return response()->json([
            'html' => $html,
            'pagination' => $data->links()->render(),
            'summary' => [
                'total'      => $totalRecords,
                'bullish_m'  => $bullishMCount,
                'bullish_s'  => $bullishSCount,
                'bearish_m'  => $bearishMCount,
                'bearish_s'  => $bearishSCount,
                'neutral'    => $neutralCount,
            ],
        ]);
    }

    public function earlyHistoricalOptions()
    {
        $pageTitle = 'Early Historical Options Data';
        
        // Get unique symbols for dropdown filter
        $symbols = SymbolList::select('symbol')
            ->distinct()
            ->orderBy('symbol')
            ->pluck('symbol')
            ->toArray();
        
        return view($this->activeTemplate . 'user.option.early-historical-data', compact('pageTitle', 'symbols'));
    }

    public function earlyHistoricalOptionsFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $trendType    = $request->get('trade_type', 'all');
        $searchTerm   = $request->get('search_term', '');
        $changeType   = $request->get('change_type', 'all');

        // Base filters (NO trend filter here)
        $baseQuery = EarlyHistoricalOptionsData::query();

        // ✅ Apply date filter if present
        if ($dateFilter) {
            $baseQuery->whereDate('date', $dateFilter);
        }

        // ✅ Apply symbol filter if present
        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        // ✅ Apply search term filter
        if ($searchTerm) {
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('future_symbol', 'LIKE', "%{$searchTerm}%")
                ->orWhere('ce_symbol', 'LIKE', "%{$searchTerm}%")
                ->orWhere('pe_symbol', 'LIKE', "%{$searchTerm}%")
                ->orWhere('underlying', 'LIKE', "%{$searchTerm}%");
            });
        }

        // ✅ Prepare main data query
        $dataQuery = (clone $baseQuery)
            ->select([
                'id', 'date', 'underlying',
                'future_symbol', 'future_volume', 'future_oi', 'future_price_change', 'future_oi_change', 'future_oi_chg_pct',
                'ce_symbol', 'ce_volume', 'ce_oi', 'ce_price_change', 'ce_oi_change', 'ce_oi_chg_pct',
                'pe_symbol', 'pe_volume', 'pe_oi', 'pe_price_change', 'pe_oi_change', 'pe_oi_chg_pct',
                'trend', 'futures_score', 'options_score', 'final_score'
            ]);

        // ✅ Apply trend filter if selected
        if ($trendType && $trendType !== 'all') {
            $dataQuery->where('trend', $trendType);
        }

        // ✅ Apply sorting based on change type
        if ($changeType && $changeType !== 'all') {
            switch ($changeType) {
                case 'ce_low_to_high':
                    $dataQuery->orderBy('ce_oi_chg_pct', 'asc');
                    break;

                case 'ce_high_to_low':
                    $dataQuery->orderBy('ce_oi_chg_pct', 'desc');
                    break;

                case 'pe_low_to_high':
                    $dataQuery->orderBy('pe_oi_chg_pct', 'asc');
                    break;

                case 'pe_high_to_low':
                    $dataQuery->orderBy('pe_oi_chg_pct', 'desc');
                    break;

                case 'fut_low_to_high':
                    $dataQuery->orderBy('future_oi_chg_pct', 'asc');
                    break;

                case 'fut_high_to_low':
                    $dataQuery->orderBy('future_oi_chg_pct', 'desc');
                    break;
            }
        } else {
            // ✅ Default sorting when no sorting filter is applied
            $dataQuery->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        // ✅ Get paginated data
        $data = $dataQuery->paginate(50);

        // ---- Summary (counts across ALL pages) ----
        // Total across base filters (ignores trendType so cards show the full breakdown)
        $totalRecords = (clone $baseQuery)->count();

        // ✅ Group counts by trend across the full filtered set (ignores trendType)
        $trendCounts = (clone $baseQuery)
            ->select('trend', DB::raw('COUNT(*) as cnt'))
            ->groupBy('trend')
            ->pluck('cnt', 'trend');

        $bullishMCount = $trendCounts['Mild Bullish']        ?? 0;
        $bullishSCount = $trendCounts['Strong Bullish']      ?? 0;
        $bearishMCount = $trendCounts['Mild Bearish']        ?? 0;
        $bearishSCount = $trendCounts['Strong Bearish']      ?? 0;
        $neutralCount  = $trendCounts['Neutral / Sideways']  ?? 0;

        // ✅ Render table HTML
        $html = view($this->activeTemplate . 'user.option.early-historical-data-table', [
            'data' => $data,
            'tradeType' => $trendType
        ])->render();

        return response()->json([
            'html' => $html,
            'pagination' => $data->links()->render(),
            'summary' => [
                'total'      => $totalRecords,
                'bullish_m'  => $bullishMCount,
                'bullish_s'  => $bullishSCount,
                'bearish_m'  => $bearishMCount,
                'bearish_s'  => $bearishSCount,
                'neutral'    => $neutralCount,
            ],
        ]);
    }

    public function runEarlyHistoricalData()
    {
        // Run the command manually
        Artisan::call('options:early-historical-data');
        return back()->with('success', "Early Historical Options Data updated successfully!");
    }

    private function swapSentimentWords($text)
    {
        // Safe swapping using temporary placeholder
        $text = str_ireplace('Bullish', '__TEMP_BULL__', $text);
        $text = str_ireplace('Bearish', 'Bullish', $text);
        $text = str_ireplace('__TEMP_BULL__', 'Bearish', $text);

        return $text;
    }

    // Unified Analysis
    public function unifiedAnalysis()
    {
        $pageTitle = 'Unified Options Analysis';
        // $symbols = SymbolList::select('symbol')
        //     ->distinct()
        //     ->orderBy('symbol')
        //     ->pluck('symbol')
        //     ->toArray();
        
        $symbols = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        return view($this->activeTemplate . 'user.option.analysis.unified-analysis', compact('pageTitle', 'symbols'));
    }

    public function unifiedAnalysisFetch(Request $request)
    {
        $dateFilter = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $searchTerm = $request->get('search_term', '');
        
        // Filter options
        $histTrendFilter = $request->get('hist_trend', 'all');
        $sentimentFilter = $request->get('sentiment', 'all');
        $volSignalFilter = $request->get('vol_signal', 'all');
        $minHistStrength = $request->get('min_hist_strength');
        $minVolStrength = $request->get('min_vol_strength');
        $consensusFilter = $request->get('consensus', 'all'); // all_bullish, all_bearish, mixed

        // Auto-select latest date if not provided
        if (empty($dateFilter)) {
            $latestDate = HistoricalOptionsData::max('date');
            if (!$latestDate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No data available in database.'
                ]);
            }
            $dateFilter = $latestDate;
        }

        // Base query
        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];
        $baseQuery = HistoricalOptionsData::query()->whereIn('underlying', $neededSymbol);

        if ($dateFilter !== 'all') {
            $baseQuery->whereDate('date', $dateFilter);
        }

        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $baseQuery->where('underlying', 'LIKE', "%{$searchTerm}%");
        }

        $allData = $baseQuery->select([
            'id', 'date', 'underlying', 'trend',
            'ce_symbol', 'ce_volume', 'ce_oi', 'ce_oi_chg_pct', 'ce_oi_change', 'ce_price_change',
            'pe_symbol', 'pe_volume', 'pe_oi', 'pe_oi_chg_pct', 'pe_oi_change', 'pe_price_change'
        ])->get();

        if ($allData->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No data found for selected criteria.'
            ]);
        }

        // Group by date + underlying
        $grouped = $allData->groupBy(function ($item) {
            return $item->date . '|' . $item->underlying;
        });

        $results = [];

        foreach ($grouped as $key => $records) {
            [$date, $underlying] = explode('|', $key);

            // 1️⃣ GET MIDDLE ROW TREND (Historical Options Data)
            $recordsArray = $records->values()->all();
            $count = count($recordsArray);
            $middleIndex = ($count % 2 == 0) ? floor($count / 2) - 1 : floor($count / 2);
            $middleRow = $recordsArray[$middleIndex];
            $histTrend = $middleRow->trend ?? 'N/A';

            // 2️⃣ CALCULATE OI ANALYSIS (Historical Analysis Logic)
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

            $oiAnalysis = $this->analyzeData($ceOiChanges, $peOiChanges, $priceChange);

            // 3️⃣ CALCULATE VOLUME ANALYSIS (Volume Analytics Logic)
            $ceVolumes = [];
            $peVolumes = [];

            foreach ($records as $row) {
                if (!is_null($row->ce_volume)) $ceVolumes[] = (float) $row->ce_volume;
                if (!is_null($row->pe_volume)) $peVolumes[] = (float) $row->pe_volume;
            }

            $volumeAnalysis = $this->analyzeVolumeSignal($ceVolumes, $peVolumes);
            
            // Default
            $volumeAnalysis['signal'] = "Bearish";

            // If NOT NIFTY/BANKNIFTY
            if (!in_array($underlying, ['NIFTY', 'BANKNIFTY'])) {

                if ($volumeAnalysis['volume_ratio'] > 2) {
                    $volumeAnalysis['signal'] = "Bullish";
                }

            } else {

                // For NIFTY & BANKNIFTY
                if ($volumeAnalysis['volume_ratio'] > 1) {
                    $volumeAnalysis['signal'] = "Bullish";
                }
            }

            // Build unified result
            $result = [
                'date' => $date,
                'underlying' => $underlying,
                // 'hist_trend' => $histTrend,
                'hist_trend' => $this->swapSentimentWords($histTrend),
                'sentiment' => $this->swapSentimentWords($oiAnalysis['sentiment']),
                'hist_strength' => $oiAnalysis['strength_score'],
                'vol_signal' => $volumeAnalysis['signal'],
                'vol_strength' => $volumeAnalysis['strength_score'],
            ];

            // Apply filters
            $passFilter = true;

            if ($histTrendFilter !== 'all' && strcasecmp($histTrend, $histTrendFilter) !== 0) {
                $passFilter = false;
            }

            if ($sentimentFilter !== 'all' && stripos($result['sentiment'], $sentimentFilter) === false) {
                $passFilter = false;
            }

            if ($volSignalFilter !== 'all' && strcasecmp($result['vol_signal'], $volSignalFilter) !== 0) {
                $passFilter = false;
            }

            if (!empty($minHistStrength) && $result['hist_strength'] < (float) $minHistStrength) {
                $passFilter = false;
            }

            if (!empty($minVolStrength) && $result['vol_strength'] < (float) $minVolStrength) {
                $passFilter = false;
            }

            // Consensus filter (all bullish / all bearish)
            if ($consensusFilter === 'all_bullish') {
                $isBullish = (
                    stripos($histTrend, 'bullish') !== false &&
                    stripos($result['sentiment'], 'bullish') !== false &&
                    strcasecmp($result['vol_signal'], 'bullish') === 0
                );
                if (!$isBullish) $passFilter = false;
            } elseif ($consensusFilter === 'all_bearish') {
                $isBearish = (
                    stripos($histTrend, 'bearish') !== false &&
                    stripos($result['sentiment'], 'bearish') !== false &&
                    strcasecmp($result['vol_signal'], 'bearish') === 0
                );
                if (!$isBearish) $passFilter = false;
            } elseif ($consensusFilter === 'mixed') {
                $signals = [
                    stripos($histTrend, 'bullish') !== false ? 'bull' : (stripos($histTrend, 'bearish') !== false ? 'bear' : 'neutral'),
                    stripos($result['sentiment'], 'bullish') !== false ? 'bull' : (stripos($result['sentiment'], 'bearish') !== false ? 'bear' : 'neutral'),
                    strcasecmp($result['vol_signal'], 'bullish') === 0 ? 'bull' : (strcasecmp($result['vol_signal'], 'bearish') === 0 ? 'bear' : 'neutral')
                ];
                $uniqueSignals = array_unique($signals);
                if (count($uniqueSignals) <= 1) $passFilter = false; // Not mixed
            }

            if ($passFilter) {
                $results[] = $result;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Unified analysis completed for {$dateFilter}.",
            'data' => collect($results)->sortByDesc('date')->values()->toArray()
        ]);
    }

    // Reuse existing helper methods
    private function analyzeData(array $ceOiChanges, array $peOiChanges, ?float $priceChange = null): array
    {
        $avgCe = $this->average($ceOiChanges);
        $avgPe = $this->average($peOiChanges);

        $sentiment = "No clear trend";
        $strength = 0;
        $pattern = "";

        if ($avgCe < 0 && $avgPe > 0) {
            $sentiment = "Strong Bullish";
            $pattern = "CE unwinding, PE writing (short covering rally)";
        } elseif ($avgCe > 0 && $avgPe < 0) {
            $sentiment = "Strong Bearish";
            $pattern = "CE writing, PE unwinding (downside continuation)";
        } elseif ($avgCe > 0 && $avgPe > 0) {
            if ($avgCe > $avgPe) {
                $sentiment = "Bullish Breakout Possible";
                $pattern = "Both sides writing, CE > PE";
            } else {
                $sentiment = "Bearish Breakout Possible";
                $pattern = "Both sides writing, PE > CE";
            }
        } elseif ($avgCe < 0 && $avgPe < 0) {
            $sentiment = "Neutral / Unwinding";
            $pattern = "Both CE and PE closing positions";
        }

        $strength = round(min(abs($avgPe - $avgCe) * 1.5, 100), 2);

        return [
            'avg_ce_oi_change' => round($avgCe, 2),
            'avg_pe_oi_change' => round($avgPe, 2),
            'sentiment' => $sentiment,
            'pattern' => $pattern,
            'strength_score' => $strength,
        ];
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

    private function average(array $arr): float
    {
        if (empty($arr)) return 0;
        return array_sum($arr) / count($arr);
    }

}