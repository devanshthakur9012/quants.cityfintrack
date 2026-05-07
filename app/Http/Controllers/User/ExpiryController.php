<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ExpiryData;
use App\Models\ExpiryMonitored;
use App\Models\ExpiryConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpiryController extends Controller
{
    /**
     * Expiry Analysis Page
     */
    public function analysis()
    {
        $pageTitle = 'Expiry Trading Analysis (1-Minute)';
        $expiringToday = ExpiryMonitored::getExpiringToday();
        $allSymbols = ExpiryMonitored::where('is_active', true)
            ->orderBy('symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.expiry.analysis', compact('pageTitle', 'expiringToday', 'allSymbols'));
    }

    /**
     * Fetch Expiry Data (AJAX) - With Persistent Signals
     */
    public function fetch(Request $request)
    {
        try {
            $symbol = $request->get('symbol');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            if (!$symbol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a symbol',
                    'data' => []
                ]);
            }

            $query = ExpiryData::where('symbol', $symbol)
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
                $earliest = ExpiryData::where('symbol', $symbol)
                    ->min('timestamp');
                
                $latest = ExpiryData::where('symbol', $symbol)
                    ->max('timestamp');

                $debugMessage = 'No data found for the selected filters.';
                if ($earliest && $latest) {
                    $debugMessage .= " Available data range: {$earliest} to {$latest}";
                }

                return response()->json([
                    'success' => false,
                    'message' => $debugMessage,
                    'data' => []
                ]);
            }

            $validRecords = $records->filter(function ($item) {
                return $item->atr !== null && 
                       $item->supertrend !== null && 
                       $item->supertrend_direction !== null;
            });

            $data = $validRecords->reverse()->values()->map(function ($item) {
                return [
                    'date' => $item->timestamp->format('Y-m-d H:i:s'),
                    'symbol' => $item->symbol,
                    'open' => (float)$item->open,
                    'high' => (float)$item->high,
                    'low' => (float)$item->low,
                    'close' => (float)$item->close,
                    'volume' => (int)$item->volume,
                    'atr' => $item->atr ? round($item->atr, 4) : null,
                    'supertrend' => $item->supertrend ? round($item->supertrend, 2) : null,
                    'direction' => $item->supertrend_direction,
                    'signal' => $item->supertrend_signal,
                    'upper_band' => $item->upper_band ? round($item->upper_band, 2) : null,
                    'lower_band' => $item->lower_band ? round($item->lower_band, 2) : null,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'symbol' => $symbol,
                'data' => $data,
                'message' => 'Data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Export to CSV
     */
    public function export(Request $request)
    {
        $symbol = $request->get('symbol');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = ExpiryData::query()
            ->orderBy('timestamp', 'asc');

        if ($symbol) {
            $query->where('symbol', $symbol);
        }

        if ($fromDate) {
            $query->where('timestamp', '>=', $fromDate . ' 00:00:00');
        }

        if ($toDate) {
            $query->where('timestamp', '<=', $toDate . ' 23:59:59');
        }

        $data = $query->get();

        $filename = 'expiry_analysis_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Timestamp',
            'Symbol',
            'Open',
            'High',
            'Low',
            'Close',
            'Volume',
            'ATR',
            'Supertrend',
            'ST Direction',
            'ST Signal',
            'Upper Band',
            'Lower Band'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row->timestamp,
                $row->symbol,
                $row->open,
                $row->high,
                $row->low,
                $row->close,
                $row->volume,
                $row->atr,
                $row->supertrend,
                $row->supertrend_direction,
                $row->supertrend_signal,
                $row->upper_band,
                $row->lower_band
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Manual trigger for fetching data
     */
    public function manualFetch(Request $request)
    {
        try {
            $lockFile = storage_path('app/expiry_fetch.lock');
            
            if (file_exists($lockFile)) {
                $lockTime = filemtime($lockFile);
                $timeDiff = time() - $lockTime;
                
                if ($timeDiff > 600) {
                    unlink($lockFile);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data fetch is already running. Please wait...'
                    ], 429);
                }
            }

            file_put_contents($lockFile, date('Y-m-d H:i:s'));

            // Run 1-min fetch command for expiry
            \Artisan::call('expiry:fetch-1min', ['--force' => true]);
            $output = \Artisan::output();

            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => true,
                'message' => 'Expiry data fetched successfully!',
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
}