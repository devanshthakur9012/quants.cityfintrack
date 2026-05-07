<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OmsConfigMaster;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\Auth;
use App\Models\OmsConfigs;
use App\Models\SymbolLtps;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradePositionController extends Controller
{
    public function tradePositionsMaster()
    {
        $pageTitle = 'Trade Positions';
        return view($this->activeTemplate . 'user.trade-positions.view', compact('pageTitle'));
    }

    public function tradePositionMasterFetch(Request $request)
    {
        // Get date filter if provided
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter');

        $userId = Auth::id();

        // Step 1: Fetch from symbol_ltps (cached for better performance)
        $ltpData = Cache::remember('symbol_ltps_data', 30, function() {
            return SymbolLtps::select('symbol_token', 'ltp', 'highest_ltp', 'highest_time')
                ->get()
                ->keyBy('symbol_token');
        }); 
        
        $query = OmsConfigs::where('user_id', $userId)
            ->with('broker:id,client_name','master:id,quantity','order:order_id,price,quantity,order_datetime,trading_symbol,user_id')
            ->orderByDesc('created_at');

        // Apply date filter if provided
        if ($dateFilter) {
            $query->whereDate('created_at', $dateFilter);
        }

        // Apply type filter if provided
        if ($typeFilter) {
            $query->where('buildup_type', $typeFilter);
        }

        $positions = $query->get();

        $totalInvestment = 0;
        $totalProfit = 0;

        // Step 3: Process positions with optimized calculation
        foreach ($positions as $position) {
            $token = $position->token;
            $ltp = $ltpData[$token] ?? null;

            $latestLtp = $ltp?->ltp ?? 0;
            $position->latest_ltp = $latestLtp;
            $position->highest_ltp = $ltp?->highest_ltp ?? 0;
            $position->highest_time = $ltp?->highest_time;

            $quantity = $position->master->quantity;
            $lotSize = $position->quantity/$quantity;

            $position->lot_size = $lotSize;

            $buyprice = (float)$position->order->price;

            $totalValue = (float)$buyprice*$position->quantity;

            $position->total_value = $totalValue;
            $position->buy_quantity = $position->master->quantity;
            $position->buy_price = $buyprice;

            if (strtolower($position->txn_type) === 'buy') {
                // Calculate profit
                $position->profit = round(($latestLtp * $lotSize * $quantity) - $totalValue,2);
            }else if(strtolower($position->txn_type) === 'sell'){
                
                $sellValue = 0;
                $currentValue = 0;
                $position->profit = round($sellValue - $currentValue, 2);
            } else {
                $position->profit = 0;
            } 

            $totalInvestment += $position->total_value;
            $totalProfit += $position->profit;
        }

        $profitPercentage = $totalInvestment > 0
            ? round(($totalProfit / $totalInvestment) * 100, 2)
            : 0;

        return response()->json([
            'positions' => $positions,
            'totalInvestment' => number_format($totalInvestment),
            'totalProfit' => number_format($totalProfit),
            'profitPercentage' => $profitPercentage,
            'noOfPositions' => count($positions),
            'totalInvestmentRaw' => $totalInvestment, // For calculations
            'totalProfitRaw' => $totalProfit // For calculations
        ]);
    }

    public function placeTradeOrder(Request $request)
    {
        try {
            $request->validate([
                'symbol' => 'required|string',
                'id' => 'required',
                'token' => 'required|string',
                'ltp' => 'required|numeric',
                'type' => 'required|in:BUY,SELL',
                'quantity' => 'required|integer|min:1',
                'order_type' => 'required|in:MARKET,LIMIT,PROFIT_PERCENTAGE',
                'price' => 'required|numeric|min:0',
            ]);
            
            $data = OmsConfigs::with('master')->where('id',$request->id)->first();
            if(! $data && $data->symbol === $request->symbol && $data->token === $request->token){
                return redirect()->back()->with('error','Something went wrong');
            }

            $userId = Auth::id();

            $pyramidSteps = $this->getPyramidSteps($data->pyramid_percent);
            $pyramids = calculatePyramids($request->quantity, $pyramidSteps);
            $cronTiming = $data->pyramid_freq > 0 
                ? Carbon::now()->subMinutes($data->pyramid_freq)
                : Carbon::now();

            $lot_size = $data->quantity / $data->master->quantity;
            $quantity = $request->quantity * ($lot_size ?? 1);

            $config = new OmsConfigs();
            $config->master_config_id = $data->master_config_id;
            $config->broker_api_id = $data->broker_api_id;
            $config->symbol_name = $data->symbol_name;
            $config->token = $data->token;
            $config->symbol_type = $data->symbol_type;
            $config->disc_ltp = $request->ltp;
            $config->portfolio_type = $data->portfolio_type;
            $config->buildup_type = $data->buildup_type;
            $config->order_type = $request->order_type;
            $config->product = $data->product;
            $config->pyramid_percent = $data->pyramid_percent;
            $config->pyramid_1 = $pyramids[0] ?? 0;
            $config->pyramid_2 = $pyramids[1] ?? 0;
            $config->pyramid_3 = $pyramids[2] ?? 0;
            $config->txn_type = $request->type;
            $config->quantity = $quantity;
            $config->pyramid_freq = $data->pyramid_freq;
            $config->quantity = $quantity;
            $config->user_id = $userId;
            $config->is_manual = 1;
            $config->status = 1;
            $config->is_api_pushed = 0;
            $config->cron_run_at = $cronTiming;
            $config->created_at = Carbon::now();
            $config->updated_at = Carbon::now();
            $config->save();

            return back()->with('success','Manual Order placed successfully.');
        } catch (\Throwable $th) {
            dd($th->getMessage());
            return back()->with('success','Something went wrong : '. $th->getMessage());
        }
    }

    private function getPyramidSteps($pyramidPercent)
    {
        return match ((int)$pyramidPercent) {
            33 => 3,
            50 => 2, 
            default => 1,
        };
    }

}
