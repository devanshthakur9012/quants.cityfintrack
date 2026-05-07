<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InstrumentHistoricalData;
use Illuminate\Support\Facades\DB;

class InstrumentHistoricalAnalysisController extends Controller
{
    public function historicalAnalysis()
    {
        $pageTitle = 'Instrument Historical Analysis';
        
        $symbols = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        return view($this->activeTemplate . 'user.instrument.analysis.historical-analysis', compact('pageTitle', 'symbols'));
    }

    public function historicalAnalysisFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $searchTerm   = $request->get('search_term', '');
        $tradeType    = $request->get('trade_type', 'all');
        $strengthScore = $request->get('strength_score');

        // Auto-select latest date if not provided
        if (empty($dateFilter)) {
            $hasData = InstrumentHistoricalData::exists();
            if (!$hasData) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No instrument data available in database.'
                ]);
            }
            // Don't set $dateFilter - keep it empty to show all data
        }

        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];
        
        // Base query - Get CE & PE data
        $baseQuery = InstrumentHistoricalData::query()
            ->select([
                'id', 'data_date', 'underlying', 'symbol', 'type',
                'oi_change_pct', 'oi_change', 'price_change'
            ])
            ->whereIn('underlying', $neededSymbol)
            ->whereIn('type', ['CE', 'PE']); // Only options, not futures

        if (!empty($dateFilter) && $dateFilter !== 'all') {
            $baseQuery->where('data_date', $dateFilter);
        }

        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('symbol', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('underlying', 'LIKE', "%{$searchTerm}%");
            });
        }

        $data = $baseQuery->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No instrument data found for the selected date.'
            ]);
        }

        // Group by date + underlying
        $grouped = $data->groupBy(function ($item) {
            return $item->data_date . '|' . $item->underlying;
        });

        $results = [];

        foreach ($grouped as $key => $records) {
            [$date, $underlying] = explode('|', $key);

            $ceOiChanges = [];
            $peOiChanges = [];
            $priceChange = null;

            foreach ($records as $row) {
                if ($row->type === 'CE' && !is_null($row->oi_change_pct)) {
                    $ceOiChanges[] = (float) $row->oi_change_pct;
                }
                if ($row->type === 'PE' && !is_null($row->oi_change_pct)) {
                    $peOiChanges[] = (float) $row->oi_change_pct;
                }

                // Calculate average price change
                if (is_null($priceChange) && !is_null($row->price_change)) {
                    $cePrice = $records->where('type', 'CE')->avg('price_change') ?? 0;
                    $pePrice = $records->where('type', 'PE')->avg('price_change') ?? 0;
                    $priceChange = ($cePrice + $pePrice) / 2;
                }
            }

            $analysis = $this->analyzeData($ceOiChanges, $peOiChanges, $priceChange);

            $results[] = [
                'underlying'       => $underlying,
                // 'date'             => $date,
                'date' => date('Y-m-d', strtotime($date)),
                'avg_ce_oi_change' => $analysis['avg_ce_oi_change'],
                'avg_pe_oi_change' => $analysis['avg_pe_oi_change'],
                'sentiment'        => $this->swapSentimentWords($analysis['sentiment']),
                'pattern'          => $analysis['pattern'],
                'strength_score'   => $analysis['strength_score'],
                'support_zone'     => $analysis['support_zone'],
                'resistance_zone'  => $analysis['resistance_zone'],
            ];
        }

        // Apply Trend Type filter
        if ($tradeType && $tradeType !== 'all') {
            $results = collect($results)->filter(function ($item) use ($tradeType) {
                return strcasecmp($item['sentiment'], $tradeType) === 0;
            })->values()->toArray();
        }

        // Apply strength filter
        if (!empty($strengthScore)) {
            $results = collect($results)->filter(function ($item) use ($strengthScore) {
                return $item['strength_score'] >= (float)$strengthScore;
            })->values()->toArray();
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Instrument sentiment analysis for {$dateFilter} completed successfully.",
            'data'    => collect($results)->sortByDesc('date')->values()->toArray()
        ]);
    }

    private function swapSentimentWords($text)
    {
        $text = str_ireplace('Bullish', '__TEMP_BULL__', $text);
        $text = str_ireplace('Bearish', 'Bullish', $text);
        $text = str_ireplace('__TEMP_BULL__', 'Bearish', $text);
        return $text;
    }

    private function analyzeData(array $ceOiChanges, array $peOiChanges, ?float $priceChange = null): array
    {
        $avgCe = $this->average($ceOiChanges);
        $avgPe = $this->average($peOiChanges);

        $sentiment = "No clear trend";
        $strength = 0;
        $pattern = "";

        // OI directional logic
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
                $pattern = "Both sides writing, CE > PE (Call writers may be trapped)";
            } else {
                $sentiment = "Bearish Breakout Possible";
                $pattern = "Both sides writing, PE > CE (Put writers may be trapped)";
            }
        } 
        elseif ($avgCe < 0 && $avgPe < 0) {
            $sentiment = "Neutral / Unwinding";
            $pattern = "Both CE and PE closing positions (low conviction zone)";
        }

        // Strength score
        $strength = round(min(abs($avgPe - $avgCe) * 1.5, 100), 2);

        // Support/Resistance zones
        $support = $this->detectSupport($peOiChanges);
        $resistance = $this->detectResistance($ceOiChanges);

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
        if (empty($peOiChanges)) return "N/A";
        $max = max($peOiChanges);
        $index = array_search($max, $peOiChanges);
        return "Support near Strike #" . ($index + 1) . " (PE OI +{$max}%)";
    }

    private function detectResistance(array $ceOiChanges): string
    {
        if (empty($ceOiChanges)) return "N/A";
        $max = max($ceOiChanges);
        $index = array_search($max, $ceOiChanges);
        return "Resistance near Strike #" . ($index + 1) . " (CE OI +{$max}%)";
    }

    private function average(array $arr): float
    {
        if (empty($arr)) return 0;
        return array_sum($arr) / count($arr);
    }
}