<?php
// app/Http/Controllers/User/OptionIVController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OptionIVData;
use App\Models\SymbolMonitored;
use App\Services\IVAnalysisService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OptionIVController extends Controller
{
    protected $ivAnalysisService;

    public function __construct(IVAnalysisService $ivAnalysisService)
    {
        $this->ivAnalysisService = $ivAnalysisService;
    }

    /**
     * IV Analysis Dashboard
     */
    public function index()
    {
        $pageTitle = 'IV Analysis - Option Chain';
        
        // Get available symbols (only index options)
        $symbols = SymbolMonitored::where('is_active', true)
            ->whereIn('symbol', ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'])
            ->distinct('symbol')
            ->pluck('symbol')
            ->toArray();

        return view('templates.basic.user.options.iv_analysis', compact('pageTitle', 'symbols'));
    }

    /**
     * Fetch IV Analysis Data (AJAX)
     */
    public function fetchIVAnalysis(Request $request)
    {
        try {
            $symbol = $request->get('symbol');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            
            if (!$symbol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a symbol',
                ], 400);
            }

            // Get expiry for symbol
            $expiry = $this->getNearestExpiry($symbol);
            
            if (!$expiry) {
                return response()->json([
                    'success' => false,
                    'message' => 'No expiry found for ' . $symbol,
                ], 404);
            }

            // Build query
            $query = OptionIVData::where('symbol', $symbol)
                ->where('expiry', $expiry)
                ->atmOnly()
                ->orderBy('timestamp', 'DESC');

            if ($fromDate) {
                $query->where('timestamp', '>=', Carbon::parse($fromDate)->startOfDay());
            }
            
            if ($toDate) {
                $query->where('timestamp', '<=', Carbon::parse($toDate)->endOfDay());
            }

            if (!$fromDate && !$toDate) {
                $query->whereDate('timestamp', Carbon::today());
            }

            $records = $query->limit(500)->get();

            if ($records->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No IV data found for the selected filters',
                    'data' => []
                ]);
            }

            // Group data by timestamp for better analysis
            $groupedData = $records->groupBy(function($item) {
                return $item->timestamp->format('Y-m-d H:i');
            });

            $ivData = [];
            
            foreach ($groupedData as $timestamp => $group) {
                $ceData = $group->where('option_type', 'CE');
                $peData = $group->where('option_type', 'PE');
                
                $atmStrike = $group->where('atm_position', 'ATM')->first()->strike ?? 0;
                
                $ivData[] = [
                    'timestamp' => $timestamp,
                    'atm_strike' => $atmStrike,
                    
                    // CE Data
                    'ce_atm_minus_1_iv' => $ceData->where('atm_position', 'ATM-1')->first()->iv ?? null,
                    'ce_atm_iv' => $ceData->where('atm_position', 'ATM')->first()->iv ?? null,
                    'ce_atm_plus_1_iv' => $ceData->where('atm_position', 'ATM+1')->first()->iv ?? null,
                    'ce_avg_iv' => round($ceData->avg('iv'), 2),
                    'ce_total_oi' => $ceData->sum('oi'),
                    
                    // PE Data
                    'pe_atm_minus_1_iv' => $peData->where('atm_position', 'ATM-1')->first()->iv ?? null,
                    'pe_atm_iv' => $peData->where('atm_position', 'ATM')->first()->iv ?? null,
                    'pe_atm_plus_1_iv' => $peData->where('atm_position', 'ATM+1')->first()->iv ?? null,
                    'pe_avg_iv' => round($peData->avg('iv'), 2),
                    'pe_total_oi' => $peData->sum('oi'),
                    
                    // Combined
                    'avg_iv' => round(($ceData->avg('iv') + $peData->avg('iv')) / 2, 2),
                    'iv_skew' => round($peData->avg('iv') - $ceData->avg('iv'), 2),
                    'oi_pcr' => $ceData->sum('oi') > 0 ? round($peData->sum('oi') / $ceData->sum('oi'), 2) : 0,
                ];
            }

            // Get latest IV analysis
            $latestAnalysis = $this->ivAnalysisService->analyzeIV(
                $symbol, 
                $expiry, 
                Carbon::parse($ivData[0]['timestamp'])
            );

            return response()->json([
                'success' => true,
                'symbol' => $symbol,
                'expiry' => $expiry,
                'data' => $ivData,
                'analysis' => $latestAnalysis,
                'message' => count($ivData) . ' records found'
            ]);

        } catch (\Exception $e) {
            Log::error('IV Analysis Fetch Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get IV Trend Chart Data (AJAX)
     */
    public function getIVTrendChart(Request $request)
    {
        try {
            $symbol = $request->get('symbol');
            $fromDate = $request->get('from_date', Carbon::today()->format('Y-m-d'));
            $toDate = $request->get('to_date', Carbon::today()->format('Y-m-d'));
            
            $expiry = $this->getNearestExpiry($symbol);
            
            if (!$expiry) {
                return response()->json([
                    'success' => false,
                    'message' => 'No expiry found',
                ], 404);
            }

            // Get time-series IV data
            $records = OptionIVData::where('symbol', $symbol)
                ->where('expiry', $expiry)
                ->atmOnly()
                ->whereBetween('timestamp', [
                    Carbon::parse($fromDate)->startOfDay(),
                    Carbon::parse($toDate)->endOfDay()
                ])
                ->orderBy('timestamp', 'ASC')
                ->get();

            $groupedData = $records->groupBy(function($item) {
                return $item->timestamp->format('Y-m-d H:i');
            });

            $chartData = [
                'labels' => [],
                'ce_iv' => [],
                'pe_iv' => [],
                'avg_iv' => [],
                'oi_pcr' => [],
            ];

            foreach ($groupedData as $timestamp => $group) {
                $ceData = $group->where('option_type', 'CE');
                $peData = $group->where('option_type', 'PE');
                
                $ceAvgIV = $ceData->avg('iv');
                $peAvgIV = $peData->avg('iv');
                
                $chartData['labels'][] = $timestamp;
                $chartData['ce_iv'][] = round($ceAvgIV, 2);
                $chartData['pe_iv'][] = round($peAvgIV, 2);
                $chartData['avg_iv'][] = round(($ceAvgIV + $peAvgIV) / 2, 2);
                
                $oiPCR = $ceData->sum('oi') > 0 ? ($peData->sum('oi') / $ceData->sum('oi')) : 0;
                $chartData['oi_pcr'][] = round($oiPCR, 2);
            }

            return response()->json([
                'success' => true,
                'data' => $chartData
            ]);

        } catch (\Exception $e) {
            Log::error('IV Chart Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export IV Data to CSV
     */
    public function exportIVData(Request $request)
    {
        $symbol = $request->get('symbol');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $expiry = $this->getNearestExpiry($symbol);

        $query = OptionIVData::where('symbol', $symbol)
            ->where('expiry', $expiry)
            ->atmOnly()
            ->orderBy('timestamp', 'asc');

        if ($fromDate) {
            $query->where('timestamp', '>=', $fromDate . ' 00:00:00');
        }

        if ($toDate) {
            $query->where('timestamp', '<=', $toDate . ' 23:59:59');
        }

        $data = $query->get();

        $filename = 'iv_analysis_' . $symbol . '_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Timestamp',
            'Symbol',
            'Expiry',
            'Strike',
            'Option Type',
            'ATM Position',
            'LTP',
            'IV',
            'OI',
            'Volume',
            'Future Price',
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row->timestamp,
                $row->symbol,
                $row->expiry,
                $row->strike,
                $row->option_type,
                $row->atm_position,
                $row->ltp,
                $row->iv,
                $row->oi,
                $row->volume,
                $row->future_price,
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Manual Trigger for IV Data Collection
     */
    public function manualFetchIV(Request $request)
    {
        try {
            $lockFile = storage_path('app/iv_fetch.lock');
            
            if (file_exists($lockFile)) {
                $lockTime = filemtime($lockFile);
                $timeDiff = time() - $lockTime;
                
                if ($timeDiff > 300) {
                    unlink($lockFile);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'IV data fetch is already running. Please wait...'
                    ], 429);
                }
            }

            file_put_contents($lockFile, date('Y-m-d H:i:s'));

            // Run IV fetch command
            \Artisan::call('options:fetch-iv', ['--force' => true]);
            $output = \Artisan::output();

            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => true,
                'message' => 'IV data fetched successfully!',
                'output' => $output
            ]);

        } catch (\Exception $e) {
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get nearest expiry
     */
    private function getNearestExpiry($symbol)
    {
        return \App\Models\ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', '>=', now())
            ->orderBy('expiry', 'asc')
            ->value('expiry');
    }
}