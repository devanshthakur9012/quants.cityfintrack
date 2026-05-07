<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InstrumentChain;
use App\Models\InstrumentHistoricalData;
use Illuminate\Support\Facades\DB;

class InstrumentHistoricalController extends Controller
{
    public function instrumentHistoricalData()
    {
        $pageTitle = 'Instrument Historical Data';
        
        // Get unique symbols from active instrument chains
        $symbols = InstrumentChain::select('underlying')
            ->where('is_active', true)
            ->distinct()
            ->orderBy('underlying')
            ->pluck('underlying')
            ->toArray();
        
        return view($this->activeTemplate . 'user.instrument.historical-data', compact('pageTitle', 'symbols'));
    }

    public function instrumentHistoricalDataFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $trendType    = $request->get('trade_type', 'all');
        $searchTerm   = $request->get('search_term', '');
        $changeType   = $request->get('change_type', 'all');
        $oiChange     = $request->get('oi_change', 'all');

        // Base query (without trend filter for summary)
        $baseQuery = InstrumentHistoricalData::query();

        // Apply date filter
        if ($dateFilter) {
            $baseQuery->where('data_date', $dateFilter);
        }

        // Apply symbol filter
        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        // Apply search filter
        if ($searchTerm) {
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('symbol', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('underlying', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Clone for data query
        $dataQuery = clone $baseQuery;

        // Apply trend filter only to data query
        if ($trendType && $trendType !== 'all') {
            $dataQuery->where('trend', $trendType);
        }

        // Apply sorting - OI Change takes priority
        if ($oiChange && $oiChange !== 'all') {
            switch ($oiChange) {
                case 'ce_low_to_high':
                    $dataQuery->where('type', 'CE')->orderBy('oi', 'asc');
                    break;
                case 'ce_high_to_low':
                    $dataQuery->where('type', 'CE')->orderBy('oi', 'desc');
                    break;
                case 'pe_low_to_high':
                    $dataQuery->where('type', 'PE')->orderBy('oi', 'asc');
                    break;
                case 'pe_high_to_low':
                    $dataQuery->where('type', 'PE')->orderBy('oi', 'desc');
                    break;
                case 'fut_low_to_high':
                    $dataQuery->where('type', 'FUT')->orderBy('oi', 'asc');
                    break;
                case 'fut_high_to_low':
                    $dataQuery->where('type', 'FUT')->orderBy('oi', 'desc');
                    break;
            }
        } elseif ($changeType && $changeType !== 'all') {
            switch ($changeType) {
                case 'ce_low_to_high':
                    $dataQuery->where('type', 'CE')->orderBy('oi_change_pct', 'asc');
                    break;
                case 'ce_high_to_low':
                    $dataQuery->where('type', 'CE')->orderBy('oi_change_pct', 'desc');
                    break;
                case 'pe_low_to_high':
                    $dataQuery->where('type', 'PE')->orderBy('oi_change_pct', 'asc');
                    break;
                case 'pe_high_to_low':
                    $dataQuery->where('type', 'PE')->orderBy('oi_change_pct', 'desc');
                    break;
                case 'fut_low_to_high':
                    $dataQuery->where('type', 'FUT')->orderBy('oi_change_pct', 'asc');
                    break;
                case 'fut_high_to_low':
                    $dataQuery->where('type', 'FUT')->orderBy('oi_change_pct', 'desc');
                    break;
            }
        } else {
            // Default sorting
            $dataQuery->orderBy('data_date', 'desc')
                     ->orderBy('underlying')
                     ->orderBy('type');
        }

        // Get paginated data
        $data = $dataQuery->paginate(100);

        // Calculate summary counts from base query
        $totalRecords = $baseQuery->count();

        // Group by trend
        $trendCounts = $baseQuery
            ->select('trend', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('trend')
            ->groupBy('trend')
            ->pluck('cnt', 'trend');

        $bullishMCount = $trendCounts['Mild Bullish']       ?? 0;
        $bullishSCount = $trendCounts['Strong Bullish']     ?? 0;
        $bearishMCount = $trendCounts['Mild Bearish']       ?? 0;
        $bearishSCount = $trendCounts['Strong Bearish']     ?? 0;
        $neutralCount  = $trendCounts['Neutral / Sideways'] ?? 0;

        // Transform data for grouped display
        $groupedData = $this->groupDataByUnderlying($data);

        // Render table HTML
        $html = view($this->activeTemplate . 'user.instrument.historical-data-table', [
            'groupedData' => $groupedData,
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

    /**
     * Group data by underlying and date for display
     */
    // private function groupDataByUnderlying($paginatedData)
    // {
    //     $grouped = [];

    //     foreach ($paginatedData as $record) {
    //         $key = $record->underlying . '_' . $record->data_date;
            
    //         if (!isset($grouped[$key])) {
    //             $grouped[$key] = [
    //                 'underlying' => $record->underlying,
    //                 'date' => $record->data_date,
    //                 'strike_price' => $record->strike_price,
    //                 'strike_position' => $record->strike_position,
    //                 'trend' => $record->trend,
    //                 'futures_score' => $record->futures_score,
    //                 'options_score' => $record->options_score,
    //                 'final_score' => $record->final_score,
    //                 'FUT' => null,
    //                 'CE' => null,
    //                 'PE' => null,
    //             ];
    //         }

    //         // Assign data based on type
    //         $grouped[$key][$record->type] = $record;
    //     }

    //     return collect($grouped)->values();
    // }

    // private function groupDataByUnderlying($paginatedData)
    // {
    //     $grouped = [];

    //     foreach ($paginatedData as $record) {
    //         $key = $record->underlying . '_' . $record->data_date;
            
    //         if (!isset($grouped[$key])) {
    //             $grouped[$key] = [
    //                 'underlying' => $record->underlying,
    //                 'date' => $record->data_date,
    //                 'strike_price' => null,
    //                 'strike_position' => null,
    //                 'trend' => null,
    //                 'futures_score' => null,
    //                 'options_score' => null,
    //                 'final_score' => null,
    //                 'FUT' => null,
    //                 'CE' => null,
    //                 'PE' => null,
    //             ];
    //         }

    //         // Update shared fields with non-null values (prefer FUT record)
    //         if ($record->type === 'FUT' || $grouped[$key]['trend'] === null) {
    //             if ($record->trend !== null) {
    //                 $grouped[$key]['trend'] = $record->trend;
    //             }
    //             if ($record->futures_score !== null) {
    //                 $grouped[$key]['futures_score'] = $record->futures_score;
    //             }
    //             if ($record->options_score !== null) {
    //                 $grouped[$key]['options_score'] = $record->options_score;
    //             }
    //             if ($record->final_score !== null) {
    //                 $grouped[$key]['final_score'] = $record->final_score;
    //             }
    //             if ($record->strike_price !== null) {
    //                 $grouped[$key]['strike_price'] = $record->strike_price;
    //             }
    //             if ($record->strike_position !== null) {
    //                 $grouped[$key]['strike_position'] = $record->strike_position;
    //             }
    //         }

    //         // Assign data based on type
    //         $grouped[$key][$record->type] = $record;
    //     }

    //     return collect($grouped)->values();
    // }

    private function groupDataByUnderlying($paginatedData)
    {
        $grouped = [];

        foreach ($paginatedData as $record) {
            $key = $record->underlying . '_' . $record->data_date;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'underlying' => $record->underlying,
                    'date' => $record->data_date,
                    'strike_price' => null,
                    'strike_position' => null,
                    'trend' => null,
                    'futures_score' => null,
                    'options_score' => null,
                    'final_score' => null,
                    'FUT' => null,
                    'CE' => null,
                    'PE' => null,
                ];
            }

            // Assign data based on type
            $grouped[$key][$record->type] = $record;
            
            // Update shared fields from any record
            if ($record->strike_price !== null) {
                $grouped[$key]['strike_price'] = $record->strike_price;
            }
            if ($record->strike_position !== null) {
                $grouped[$key]['strike_position'] = $record->strike_position;
            }
            
            // Use existing trend/scores if available
            if ($record->trend !== null) {
                $grouped[$key]['trend'] = $record->trend;
            }
            if ($record->futures_score !== null) {
                $grouped[$key]['futures_score'] = $record->futures_score;
            }
            if ($record->options_score !== null) {
                $grouped[$key]['options_score'] = $record->options_score;
            }
            if ($record->final_score !== null) {
                $grouped[$key]['final_score'] = $record->final_score;
            }
        }

        // Calculate trend and scores if not already present
        foreach ($grouped as $key => &$group) {
            if ($group['trend'] === null) {
                $this->calculateTrendAndScores($group);
            }
        }

        return collect($grouped)->values();
    }

    /**
     * Calculate trend and scores based on price and OI changes
     */
    private function calculateTrendAndScores(&$group)
    {
        $futureScore = 0;
        $optionsScore = 0;
        
        // 1. Calculate Future Score
        if ($group['FUT']) {
            $fut = $group['FUT'];
            $priceChange = $fut->price_change ?? 0;
            $oiChange = $fut->oi_change ?? 0;
            $oiChangePct = $fut->oi_change_pct ?? 0;
            
            // Price direction score
            if ($priceChange > 0) {
                $futureScore += 2; // Bullish
            } elseif ($priceChange < 0) {
                $futureScore -= 2; // Bearish
            }
            
            // OI build-up with price rise = Strong bullish
            // OI build-up with price fall = Strong bearish
            if (abs($oiChangePct) > 5) { // Significant OI change
                if ($oiChange > 0) {
                    $futureScore += ($priceChange > 0) ? 2 : -2;
                } else {
                    $futureScore += ($priceChange > 0) ? 1 : -1;
                }
            }
        }
        
        // 2. Calculate Options Score
        $ceOiChange = 0;
        $ceOiChangePct = 0;
        $peOiChange = 0;
        $peOiChangePct = 0;
        
        if ($group['CE']) {
            $ceOiChange = $group['CE']->oi_change ?? 0;
            $ceOiChangePct = $group['CE']->oi_change_pct ?? 0;
        }
        
        if ($group['PE']) {
            $peOiChange = $group['PE']->oi_change ?? 0;
            $peOiChangePct = $group['PE']->oi_change_pct ?? 0;
        }
        
        // Compare CE vs PE OI changes
        // High PE OI buildup = Bullish (support building)
        // High CE OI buildup = Bearish (resistance building)
        if (abs($peOiChangePct) > 5 || abs($ceOiChangePct) > 5) {
            if ($peOiChange > $ceOiChange) {
                $optionsScore += 2; // More PUT writing = Bullish
            } elseif ($ceOiChange > $peOiChange) {
                $optionsScore -= 2; // More CALL writing = Bearish
            }
        }
        
        // Strong OI changes indicate conviction
        if (abs($peOiChangePct) > 10) {
            $optionsScore += ($peOiChange > 0) ? 1 : -1;
        }
        if (abs($ceOiChangePct) > 10) {
            $optionsScore -= ($ceOiChange > 0) ? 1 : -1;
        }
        
        // 3. Calculate Final Score
        $finalScore = $futureScore + $optionsScore;
        
        // 4. Determine Trend based on final score
        $trend = 'Neutral / Sideways';
        
        if ($finalScore >= 4) {
            $trend = 'Strong Bullish';
        } elseif ($finalScore >= 2) {
            $trend = 'Mild Bullish';
        } elseif ($finalScore <= -4) {
            $trend = 'Strong Bearish';
        } elseif ($finalScore <= -2) {
            $trend = 'Mild Bearish';
        }
        
        // Assign calculated values
        $group['futures_score'] = $futureScore;
        $group['options_score'] = $optionsScore;
        $group['final_score'] = $finalScore;
        $group['trend'] = $trend;
    }
}