<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ManualOptionData;
use App\Models\HistoricalOptionsData;
use App\Models\SymbolList;
use Carbon\Carbon;

class ManualOptionDataController extends Controller
{
    
    public function index()
    {
        $pageTitle = 'Manual Historical Options Data';
        $symbols = SymbolList::select('symbol')
            ->distinct()
            ->orderBy('symbol')
            ->pluck('symbol')
            ->toArray();
        
        return view($this->activeTemplate . 'user.option.analysis.manual-historical-data', compact('pageTitle', 'symbols'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'underlying' => 'required|string|max:50',
            'date' => 'required|date',
            'atm_minus_2_ce_oi' => 'nullable|numeric',
            'atm_minus_2_pe_oi' => 'nullable|numeric',
            'atm_minus_1_ce_oi' => 'nullable|numeric',
            'atm_minus_1_pe_oi' => 'nullable|numeric',
            'atm_ce_oi' => 'nullable|numeric',
            'atm_pe_oi' => 'nullable|numeric',
            'atm_plus_1_ce_oi' => 'nullable|numeric',
            'atm_plus_1_pe_oi' => 'nullable|numeric',
            'atm_plus_2_ce_oi' => 'nullable|numeric',
            'atm_plus_2_pe_oi' => 'nullable|numeric',
        ]);

        // Check if record already exists for this date + underlying
        $existing = ManualOptionData::where('underlying', $validated['underlying'])
            ->where('date', $validated['date'])
            ->first();

        if ($existing) {
            $existing->update($validated);
            $record = $existing;
            $message = 'Manual data updated successfully!';
        } else {
            $record = ManualOptionData::create($validated);
            $message = 'Manual data added successfully!';
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $record
        ]);
    }

    public function fetch(Request $request)
    {
        $dateFilter = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $searchTerm = $request->get('search_term', '');
        $tradeType = $request->get('trade_type', 'all');
        $strengthScore = $request->get('strength_score');

        // If no date provided, get latest
        if (empty($dateFilter)) {
            $latestDate = ManualOptionData::max('date');
            if (!$latestDate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No manual option data available in database.'
                ]);
            }
            $dateFilter = $latestDate;
        }

        $query = ManualOptionData::query();
        $query->whereDate('date', $dateFilter);

        if ($symbolFilter && $symbolFilter !== 'all') {
            $query->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $query->where('underlying', 'LIKE', "%{$searchTerm}%");
        }

        $data = $query->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No manual option data found for the selected or latest date.'
            ]);
        }

        $results = [];

        foreach ($data as $row) {
            // ✅ CRITICAL FIX: Get OI percentage changes from historical_options_data
            // This ensures we use the EXACT SAME source data as historical analysis
            $ceOiChanges = [];
            $peOiChanges = [];

            // Fetch all records for this underlying and date from historical_options_data
            $historicalRecords = HistoricalOptionsData::where('underlying', $row->underlying)
                ->whereDate('date', $row->date)
                ->get();

            // Extract only non-zero ce_oi_chg_pct and pe_oi_chg_pct values
            foreach ($historicalRecords as $record) {
                if (!is_null($record->ce_oi_chg_pct) && $record->ce_oi_chg_pct != 0) {
                    $ceOiChanges[] = (float) $record->ce_oi_chg_pct;
                }
                if (!is_null($record->pe_oi_chg_pct) && $record->pe_oi_chg_pct != 0) {
                    $peOiChanges[] = (float) $record->pe_oi_chg_pct;
                }
            }

            // Use same analysis logic
            $analysis = $this->analyzeData($ceOiChanges, $peOiChanges);

            $results[] = [
                'underlying' => $row->underlying,
                'date' => $dateFilter,
                'avg_ce_oi_change' => $analysis['avg_ce_oi_change'],
                'avg_pe_oi_change' => $analysis['avg_pe_oi_change'],
                'sentiment' => $analysis['sentiment'],
                'pattern' => $analysis['pattern'],
                'strength_score' => $analysis['strength_score'],
                'support_zone' => $analysis['support_zone'],
                'resistance_zone' => $analysis['resistance_zone'],
            ];
        }

        // Apply filters
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
            'status' => 'success',
            'message' => "Manual option sentiment analysis for {$dateFilter} completed successfully.",
            'data' => $results
        ]);
    }

    // Same analysis logic as HistoricalAnalysisController
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
                $pattern = "Both sides writing, CE > PE (Call writers may be trapped)";
            } else {
                $sentiment = "Bearish Breakout Possible";
                $pattern = "Both sides writing, PE > CE (Put writers may be trapped)";
            }
        } elseif ($avgCe < 0 && $avgPe < 0) {
            $sentiment = "Neutral / Unwinding";
            $pattern = "Both CE and PE closing positions (low conviction zone)";
        }

        $strength = round(min(abs($avgPe - $avgCe) * 1.5, 100), 2);

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
        if (empty($peOiChanges)) return "No data";
        $max = max($peOiChanges);
        $index = array_search($max, $peOiChanges);
        return "Support near Strike #" . ($index + 1) . " (PE OI +" . round($max, 2) . "%)";
    }

    private function detectResistance(array $ceOiChanges): string
    {
        if (empty($ceOiChanges)) return "No data";
        $max = max($ceOiChanges);
        $index = array_search($max, $ceOiChanges);
        return "Resistance near Strike #" . ($index + 1) . " (CE OI +" . round($max, 2) . "%)";
    }

    private function average(array $arr): float
    {
        if (empty($arr)) return 0;
        return array_sum($arr) / count($arr);
    }

}