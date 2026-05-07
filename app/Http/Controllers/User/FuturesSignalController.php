<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FuturesInstrument;
use App\Models\FuturesTradingSignal;
use Illuminate\Support\Facades\DB;

class FuturesSignalController extends Controller
{
    public function index()
    {
        $pageTitle = 'Combined Signal Analysis';
        
        // Get unique symbols
        $symbols = FuturesInstrument::active()
            ->distinct()
            ->orderBy('underlying')
            ->pluck('underlying')
            ->toArray();
        
        return view($this->activeTemplate . 'user.futures-signal.index', compact('pageTitle', 'symbols'));
    }

    public function fetch(Request $request)
    {
        $dateFilter = $request->get('date_filter');
        $symbolFilter = $request->get('symbol_filter', 'all');
        $signalFilter = $request->get('signal_filter', 'all');
        $structureFilter = $request->get('structure_filter', 'all');
        $haColorFilter = $request->get('ha_color_filter', 'all');
        $searchTerm = $request->get('search_term', '');

        // Base query
        $baseQuery = FuturesTradingSignal::query();

        // Date filter
        if ($dateFilter) {
            $baseQuery->where('data_date', $dateFilter);
        }

        // Symbol filter
        if ($symbolFilter && $symbolFilter !== 'all') {
            $baseQuery->where('underlying', $symbolFilter);
        }

        // Search
        if ($searchTerm) {
            $baseQuery->where(function ($q) use ($searchTerm) {
                $q->where('symbol', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('underlying', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Clone for data query
        $dataQuery = clone $baseQuery;

        // Signal filter
        if ($signalFilter && $signalFilter !== 'all') {
            $dataQuery->where('final_signal', $signalFilter);
        }

        // Structure filter
        if ($structureFilter && $structureFilter !== 'all') {
            $dataQuery->where('structure_type', $structureFilter);
        }

        // HA color filter
        if ($haColorFilter && $haColorFilter !== 'all') {
            $dataQuery->where('ha_color', $haColorFilter);
        }

        // Order & paginate
        $dataQuery->orderBy('data_date', 'desc')
                 ->orderBy('candle_time', 'desc');

        $data = $dataQuery->paginate(50);

        // Summary counts
        $totalRecords = $baseQuery->count();

        $signalCounts = $baseQuery
            ->select('final_signal', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('final_signal')
            ->groupBy('final_signal')
            ->pluck('cnt', 'final_signal');

        $buyCount = $signalCounts['BUY'] ?? 0;
        $sellCount = $signalCounts['SELL'] ?? 0;
        $noTradeCount = $signalCounts['NO TRADE'] ?? 0;

        $structureCounts = $baseQuery
            ->select('structure_type', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('structure_type')
            ->groupBy('structure_type')
            ->pluck('cnt', 'structure_type');

        $longBuildupCount = $structureCounts['LONG_BUILDUP'] ?? 0;
        $shortBuildupCount = $structureCounts['SHORT_BUILDUP'] ?? 0;
        $shortCoveringCount = $structureCounts['SHORT_COVERING'] ?? 0;
        $longUnwindingCount = $structureCounts['LONG_UNWINDING'] ?? 0;

        $haColorCounts = $baseQuery
            ->select('ha_color', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('ha_color')
            ->groupBy('ha_color')
            ->pluck('cnt', 'ha_color');

        $greenHACount = $haColorCounts['GREEN'] ?? 0;
        $redHACount = $haColorCounts['RED'] ?? 0;

        // Render table
        $html = view($this->activeTemplate . 'user.futures-signal.table', [
            'signals' => $data
        ])->render();

        return response()->json([
            'html' => $html,
            'pagination' => $data->links()->render(),
            'summary' => [
                'total' => $totalRecords,
                'buy' => $buyCount,
                'sell' => $sellCount,
                'no_trade' => $noTradeCount,
                'long_buildup' => $longBuildupCount,
                'short_buildup' => $shortBuildupCount,
                'short_covering' => $shortCoveringCount,
                'long_unwinding' => $longUnwindingCount,
                'green_ha' => $greenHACount,
                'red_ha' => $redHACount,
            ],
        ]);
    }

    public function detail($id)
    {
        $signal = FuturesTradingSignal::findOrFail($id);
        
        $surroundingSignals = FuturesTradingSignal::where('token', $signal->token)
            ->where('data_date', $signal->data_date)
            ->whereBetween('candle_index', [$signal->candle_index - 5, $signal->candle_index + 5])
            ->orderBy('candle_index', 'asc')
            ->get();
        
        return response()->json([
            'signal' => $signal,
            'context' => $surroundingSignals
        ]);
    }
}