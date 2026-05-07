<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use App\Models\IndicatorConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FuturesController extends Controller
{
    /**
     * Supertrend Analysis Page
     */
    public function supertrendAnalysis()
    {
        $pageTitle = 'Supertrend Analysis - Zerodha Futures';
        $monitoredFutures = FuturesMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        return view($this->activeTemplate . 'user.futures.supertrend-analysis', compact('pageTitle', 'monitoredFutures'));
    }

    /**
     * Fetch Supertrend Data (AJAX)
     */
    public function supertrendFetch(Request $request)
    {
        try {
            $tradingSymbol = $request->get('trading_symbol');
            $interval = $request->get('interval', 'minute');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            if (!$tradingSymbol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a trading symbol',
                    'data' => []
                ]);
            }

            // Build query
            $query = FuturesData::where('trading_symbol', $tradingSymbol)
                ->where('interval', $interval)
                ->orderBy('timestamp', 'DESC');

            // Apply date filters if provided
            if ($fromDate) {
                $query->where('timestamp', '>=', Carbon::parse($fromDate)->startOfDay());
            }
            
            if ($toDate) {
                $query->where('timestamp', '<=', Carbon::parse($toDate)->endOfDay());
            }

            // If no date filters, get today's data by default
            if (!$fromDate && !$toDate) {
                $query->whereDate('timestamp', Carbon::today());
            }

            $records = $query->limit(500)->get();

            if ($records->isEmpty()) {
                $earliest = FuturesData::where('trading_symbol', $tradingSymbol)
                    ->where('interval', $interval)
                    ->min('timestamp');
                
                $latest = FuturesData::where('trading_symbol', $tradingSymbol)
                    ->where('interval', $interval)
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

            // Filter valid records with indicator data
            $validRecords = $records->filter(function ($item) {
                return $item->atr !== null && 
                       $item->supertrend !== null && 
                       $item->supertrend_direction !== null;
            });

            // Reverse to show latest at top
            $supertrendData = $validRecords->reverse()->values()->map(function ($item) {
                return [
                    'date' => $item->timestamp->format('Y-m-d H:i:s'),
                    'symbol' => $item->trading_symbol,
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
                    'donchian_signal' => $item->donchian_signal ?? 'NO_TRADE',
                    'donchian_entry' => $item->donchian_entry ?? null,
                    'donchian_sl' => $item->donchian_sl ?? null,
                    'donchian_target' => $item->donchian_target ?? null,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'trading_symbol' => $tradingSymbol,
                'interval' => $interval,
                'data' => $supertrendData,
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
        $interval = $request->get('interval', 'minute');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = FuturesData::query()
            ->where('interval', $interval)
            ->orderBy('timestamp', 'asc');

        if ($symbol) {
            $query->where('trading_symbol', 'LIKE', '%' . strtoupper($symbol) . '%');
        }

        if ($fromDate) {
            $query->where('timestamp', '>=', $fromDate . ' 00:00:00');
        }

        if ($toDate) {
            $query->where('timestamp', '<=', $toDate . ' 23:59:59');
        }

        $data = $query->get();

        $filename = 'futures_supertrend_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Timestamp',
            'Trading Symbol',
            'Interval',
            'Open',
            'High',
            'Low',
            'Close',
            'Volume',
            'OI',
            'ATR',
            'Supertrend',
            'Direction',
            'Signal',
            'Upper Band',
            'Lower Band',
            'Donchian Signal',
            'Donchian Entry',
            'Donchian SL',
            'Donchian Target'
        ]);

        foreach ($data as $row) {
            fputcsv($output, [
                $row->timestamp,
                $row->trading_symbol,
                $row->interval,
                $row->open,
                $row->high,
                $row->low,
                $row->close,
                $row->volume,
                $row->oi,
                $row->atr,
                $row->supertrend,
                $row->supertrend_direction,
                $row->supertrend_signal,
                $row->upper_band,
                $row->lower_band,
                $row->donchian_signal,
                $row->donchian_entry,
                $row->donchian_sl,
                $row->donchian_target
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Manual trigger for fetching data
     */
    public function manualFetchDaily(Request $request)
    {
        try {
            $lockFile = storage_path('app/futures_fetch.lock');
            
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

            // Run both 1min and 5min commands
            \Artisan::call('futures:fetch-1min', ['--force' => true]);
            $output1min = \Artisan::output();
            
            \Artisan::call('futures:fetch-5min', ['--force' => true]);
            $output5min = \Artisan::output();

            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully for all timeframes!',
                'output' => "1-Min:\n" . $output1min . "\n\n5-Min:\n" . $output5min
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