<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalysisController extends Controller
{
    public function analysis()
    {
        $pageTitle = 'Analysis Data';
        $symbols = DB::table('symbol_lists')->select('symbol')->distinct()->orderBy('symbol')->pluck('symbol')->toArray();

        return view($this->activeTemplate . 'user.analysis.index', compact('pageTitle', 'symbols'));
    }
    
    public function getSymbolData(Request $request)
    {
        $symbol = $request->input('symbol');

        $data = DB::table('historical_options_data')
            ->select(
                'date',
                'future_oi','ce_oi','pe_oi',
                'future_oi_change','ce_oi_change','pe_oi_change',
                'future_close','ce_close','pe_close',
                'future_symbol','ce_symbol','pe_symbol'
            )
            ->where('underlying', $symbol)
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

}
