<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OhlcDataViewerController
 *
 * Raw OHLC data viewer — see exactly what strikes, ATM, OI, OHLC
 * are stored in the DB for any symbol + date range.
 * Perfect for debugging data collection issues.
 */
class OhlcDataViewerController extends Controller
{
    public function index()
    {
        $pageTitle = 'OHLC Data Viewer — Raw DB Inspector';
        return view($this->activeTemplate . 'user.oiiv-auto.ohlc-viewer', compact('pageTitle'));
    }

    // ── Symbols list ─────────────────────────────────────────────────────────
    public function getSymbols()
    {
        $symbols = OptionOhlcData::select('base_symbol')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // ── Main data fetch ───────────────────────────────────────────────────────
    public function getData(Request $request)
    {
        try {
            $symbol    = strtoupper(trim($request->get('symbol', '')));
            $fromDate  = $request->get('from_date');
            $toDate    = $request->get('to_date');
            $instrType = $request->get('instrument_type', '');  // ALL / FUT / CE / PE
            $timeSlot  = $request->get('time_slot', '');        // e.g. 15:00, 15:15, ALL

            if (!$symbol || !$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Symbol and dates are required', 'data' => []]);
            }

            $query = OptionOhlcData::where('base_symbol', $symbol)
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select([
                    DB::raw('DATE_FORMAT(trade_date, "%Y-%m-%d") as trade_date'),
                    DB::raw('DATE_FORMAT(interval_time, "%H:%i") as interval_time'),
                    'instrument_type',
                    'trading_symbol',
                    'strike',
                    'atm_strike',
                    'strike_position',
                    DB::raw('DATE_FORMAT(expiry_date, "%Y-%m-%d") as expiry_date'),
                    'future_price',
                    'open',
                    'high',
                    'low',
                    'close',
                    'volume',
                    'oi',
                    'is_missing',
                ]);

            if ($instrType && $instrType !== 'ALL') {
                $query->where('instrument_type', $instrType);
            }

            if ($timeSlot && $timeSlot !== 'ALL') {
                $query->whereRaw("TIME(interval_time) = ?", [$timeSlot . ':00']);
            }

            $data = $query->orderBy('trade_date')
                ->orderBy('interval_time')
                ->orderBy('instrument_type')
                ->orderByRaw('CAST(strike AS DECIMAL) ASC')
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No data found for ' . $symbol . ' in selected range', 'data' => []]);
            }

            // ── Summary stats ─────────────────────────────────────────────────
            $dates        = $data->pluck('trade_date')->unique()->sort()->values();
            $times        = $data->pluck('interval_time')->unique()->sort()->values();
            $strikes      = $data->whereNotNull('strike')->pluck('strike')->unique()->sort()->values();
            $atmStrikes   = $data->whereNotNull('atm_strike')->pluck('atm_strike')->unique()->values();
            $nullOiCount  = $data->whereNull('oi')->count();
            $zeroOiCount  = $data->where('oi', 0)->count();
            $missingCount = $data->where('is_missing', 1)->count();

            // Per-date ATM summary
            $atmByDate = $data->where('instrument_type', 'FUT')
                ->groupBy('trade_date')
                ->map(function ($rows) {
                    $row = $rows->first();
                    return [
                        'atm_strike'   => $row->atm_strike,
                        'future_price' => $row->future_price,
                        'close'        => $row->close,
                    ];
                });

            return response()->json([
                'success'       => true,
                'data'          => $data->values(),
                'total_records' => $data->count(),
                'summary'       => [
                    'dates'         => $dates,
                    'total_dates'   => $dates->count(),
                    'times'         => $times,
                    'strikes'       => $strikes,
                    'atm_strikes'   => $atmStrikes,
                    'null_oi'       => $nullOiCount,
                    'zero_oi'       => $zeroOiCount,
                    'missing_rows'  => $missingCount,
                    'atm_by_date'   => $atmByDate,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('OhlcDataViewer error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── OI comparison: today vs prev day for selected time slots ─────────────
    public function getOiComparison(Request $request)
    {
        try {
            $symbol   = strtoupper(trim($request->get('symbol', '')));
            $fromDate = $request->get('from_date');
            $toDate   = $request->get('to_date');

            if (!$symbol || !$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Symbol and dates are required', 'data' => []]);
            }

            // Get all CE/PE rows at 15:00 and 15:15 for the range
            $rows = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->whereRaw("TIME(interval_time) IN ('15:00:00', '15:15:00')")
                ->select([
                    DB::raw('DATE_FORMAT(trade_date, "%Y-%m-%d") as trade_date'),
                    DB::raw('DATE_FORMAT(interval_time, "%H:%i") as interval_time'),
                    'instrument_type',
                    'strike',
                    'atm_strike',
                    'strike_position',
                    'oi',
                    'close',
                    'is_missing',
                ])
                ->orderBy('trade_date')
                ->orderBy('interval_time')
                ->orderBy('instrument_type')
                ->orderByRaw('CAST(strike AS DECIMAL) ASC')
                ->get();

            return response()->json([
                'success'       => true,
                'data'          => $rows->values(),
                'total_records' => $rows->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }
}