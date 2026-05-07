<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InstrumentHistoricalData;
use Illuminate\Support\Facades\DB;

class InstrumentVolumeAnalyticsController extends Controller
{
    public function volumeAnalytics()
    {
        $pageTitle = 'Volume-Based Instrument Analytics';
        
        $symbols = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        return view($this->activeTemplate . 'user.instrument.analysis.volume-analytics', compact('pageTitle', 'symbols'));
    }

    public function volumeAnalyticsFetch(Request $request)
    {
        $dateFilter   = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $searchTerm   = $request->get('search_term', '');
        $strengthScore = $request->get('strength_score');
        $tradeType = $request->get('trade_type', 'all');

        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        // Auto-select latest date if not provided
        if (empty($dateFilter)) {
            $hasData = InstrumentHistoricalData::exists();
            if (!$hasData) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No volume data available in database.'
                ]);
            }
            // Don't set $dateFilter - keep it empty to show all data
        }

        // Base query - Get CE & PE volume data
        $query = InstrumentHistoricalData::query()
            ->select('id', 'data_date', 'underlying', 'symbol', 'type', 'volume')
            ->whereIn('underlying', $neededSymbol)
            ->whereIn('type', ['CE', 'PE']);

        if (!empty($dateFilter) && $dateFilter !== 'all') {
            $query->where('data_date', $dateFilter);
        }

        if ($symbolFilter && $symbolFilter !== 'all') {
            $query->where('underlying', $symbolFilter);
        }

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('underlying', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('symbol', 'LIKE', "%{$searchTerm}%");
            });
        }

        $data = $query->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No records found for selected date.'
            ]);
        }

        // Group by underlying + date
        $grouped = $data->groupBy(function ($item) {
            return $item->data_date . '|' . $item->underlying;
        });

        $results = [];

        foreach ($grouped as $key => $records) {
            [$date, $underlying] = explode('|', $key);

            $ceVolumes = [];
            $peVolumes = [];

            foreach ($records as $row) {
                if ($row->type === 'CE' && !is_null($row->volume)) {
                    $ceVolumes[] = (float)$row->volume;
                }
                if ($row->type === 'PE' && !is_null($row->volume)) {
                    $peVolumes[] = (float)$row->volume;
                }
            }

            $analysis = $this->analyzeVolumeSignal($ceVolumes, $peVolumes);

            // Default signal
            $analysis['signal'] = "Bearish";

            // Different logic for indices vs stocks
            if (!in_array($underlying, ['NIFTY', 'BANKNIFTY'])) {
                // For stocks: CE/PE ratio > 2 means Bullish
                if ($analysis['volume_ratio'] > 2) {
                    $analysis['signal'] = "Bullish";
                }
            } else {
                // For indices: CE/PE ratio > 1 means Bullish
                if ($analysis['volume_ratio'] > 1) {
                    $analysis['signal'] = "Bullish";
                }
            }

            $results[] = [
                'underlying' => $underlying,
                // 'date' => $date,
                'date' => date('Y-m-d', strtotime($date)),
                'total_ce_volume' => $analysis['total_ce_volume'],
                'total_pe_volume' => $analysis['total_pe_volume'],
                'signal' => $analysis['signal'],
                'strength_score' => $analysis['strength_score'],
                'volume_ratio' => $analysis['volume_ratio']
            ];
        }

        // Filter by sentiment (Bullish/Bearish)
        if ($tradeType && $tradeType !== 'all') {
            $results = collect($results)->filter(function($item) use ($tradeType) {
                return strcasecmp($item['signal'], $tradeType) === 0;
            })->values()->toArray();
        }

        // Filter by strength threshold
        if (!empty($strengthScore)) {
            $results = collect($results)->filter(function($item) use ($strengthScore) {
                return $item['strength_score'] >= (float)$strengthScore;
            })->values()->toArray();
        }

        return response()->json([
            'status' => 'success',
            'message' => "Volume analysis for {$dateFilter} completed successfully.",
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