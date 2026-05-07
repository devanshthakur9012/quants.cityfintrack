<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\SymbolLtps;

class OiBuildUpController extends Controller
{
    const MINIMUM_TIME = '09:15:00';
    const BUY_QUANTITY = 10; // Constant buy quantity

    // public function oiBuildupData()
    // {
    //     $today = Carbon::today()->toDateString();
    //     // $today = Carbon::yesterday()->toDateString();
    //     $cacheKey = 'oi_buildup_first_symbols_' . $today;

    //     $oiBuildupData = Cache::remember($cacheKey, 60, function () use ($today) {
    //         $connection = DB::connection('mysql_oi_buildup');

    //         // Subquery: symbol + MIN(inserted_at)
    //         $subQuery = $connection->table('oi_buildup_data')
    //             ->select('symbol', DB::raw('MIN(inserted_at) as first_inserted_at'))
    //             ->whereDate('inserted_at', $today)
    //             ->whereTime('inserted_at', '>=', self::MINIMUM_TIME)
    //             ->groupBy('symbol');

    //         // Join to get full rows matching symbol and first inserted_at
    //         $data = $connection->table('oi_buildup_data as main')
    //             ->joinSub($subQuery, 'first_entries', function ($join) {
    //                 $join->on('main.symbol', '=', 'first_entries.symbol')
    //                     ->on('main.inserted_at', '=', 'first_entries.first_inserted_at');
    //             })
    //             ->orderBy('main.inserted_at')
    //             ->select('main.*')
    //             ->get();

    //         // Enrich each row with TXN data and lot size
    //         return $this->enrichWithTxnData($data);
    //     });

    //     // dd($oiBuildupData);

    //     $pageTitle = 'Our Portfolios';
    //     return view($this->activeTemplate . 'user.oi_buildup.latest', compact('pageTitle', 'oiBuildupData'));
    // }

    // // NFO
    // public function fetchOiBuildupData(Request $request)
    // {
    //     $connection = DB::connection('mysql_oi_buildup');

    //     $lastLoadedTime = $request->input('last_loaded_time');
    //     $loadedSymbols = $request->input('loaded_symbols', []);
    //     $today = Carbon::today()->toDateString();

    //     // Subquery to get new symbols with their first inserted_at
    //     $subQuery = $connection->table('oi_buildup_data')
    //         ->select('symbol', DB::raw('MIN(inserted_at) as first_inserted_at'))
    //         ->whereDate('inserted_at', $today)
    //         ->whereTime('inserted_at', '>=', self::MINIMUM_TIME)
    //         ->where('inserted_at', '>', $lastLoadedTime)
    //         ->when(!empty($loadedSymbols), function ($q) use ($loadedSymbols) {
    //             $q->whereNotIn('symbol', $loadedSymbols);
    //         })
    //         ->groupBy('symbol');

    //     $newEntries = $connection->table('oi_buildup_data as main')
    //         ->joinSub($subQuery, 'first_entries', function ($join) {
    //             $join->on('main.symbol', '=', 'first_entries.symbol')
    //                 ->on('main.inserted_at', '=', 'first_entries.first_inserted_at');
    //         })
    //         ->orderBy('main.inserted_at')
    //         ->select('main.*')
    //         ->get();

    //     // Enrich new entries with TXN data and lot size
    //     $enrichedNewEntries = $this->enrichWithTxnData($newEntries);

    //     // Update cache
    //     if ($enrichedNewEntries->count()) {
    //         $cacheKey = 'oi_buildup_first_symbols_' . $today;
    //         $cached = Cache::get($cacheKey, collect());
    //         $updated = $cached->merge($enrichedNewEntries);
    //         Cache::put($cacheKey, $updated, 60);
    //     }

    //     return response()->json([
    //         'new_data' => $enrichedNewEntries
    //     ]);
    // }

    // private function enrichWithTxnData($oiBuildupCollection)
    // {
    //     $connection = DB::connection('mysql_oi_buildup');

    //     return $oiBuildupCollection->map(function ($item) use ($connection) {
    //         // Get lot size from angel_instruments_data
    //         $lotSize = $this->getLotSize($connection, $item->symbol);
            
    //         // Get options ATM data for this symbol and time
    //         $optionsData = $this->getOptionsAtmData($connection, $item->symbol, $item->inserted_at);
            
    //         if ($optionsData && count($optionsData) > 0) {
    //             // Determine CE and PE from the options data
    //             $ceData = collect($optionsData)->firstWhere(function ($option) {
    //                 return strpos($option->symbol, 'CE') !== false;
    //             });
                
    //             $peData = collect($optionsData)->firstWhere(function ($option) {
    //                 return strpos($option->symbol, 'PE') !== false;
    //             });

    //             // Determine TXN Type based on buildup_type
    //             if (in_array($item->buildup_type, ['Long Built Up', 'Short Covering'])) {
    //                 // Use CE data
    //                 if ($ceData) {
    //                     $item->txn_type = 'BUY';
    //                     $item->option_symbol = $ceData->symbol;
    //                     $item->option_token = $ceData->token;
    //                 } else {
    //                     $item->txn_type = '---';
    //                     $item->option_symbol = '---';
    //                     $item->option_token = '---';
    //                 }
    //             } else if (in_array($item->buildup_type, ['Short Built Up', 'Long Unwinding'])) {
    //                 // Use PE data
    //                 if ($peData) {
    //                     $item->txn_type = 'BUY';
    //                     $item->option_symbol = $peData->symbol;
    //                     $item->option_token = $peData->token;
    //                 } else {
    //                     $item->txn_type = '---';
    //                     $item->option_symbol = '---';
    //                     $item->option_token = '---';
    //                 }
    //             } else {
    //                 // Unknown buildup type
    //                 $item->txn_type = '---';
    //                 $item->option_symbol = '---';
    //                 $item->option_token = '---';
    //             }
    //         } else {
    //             // No options data found
    //             $item->txn_type = '---';
    //             $item->option_symbol = '---';
    //             $item->option_token = '---';
    //         }

    //         // Add lot size and other columns
    //         $item->lot_size = $lotSize;
    //         $item->buy_quantity = self::BUY_QUANTITY;
    //         $item->buy_price = '';
    //         $item->sell_qty = '';
    //         $item->sell_price = '';
    //         $item->total_value = '';
    //         $item->profit = '';
    //         $item->realised_profit = '';
    //         $item->unrealised_profit = '';

    //         return $item;
    //     });
    // }

    // private function getLotSize($connection, $symbol)
    // {
    //     try {
    //         $result = $connection->table('angel_instruments_data')
    //             ->where('symbol', $symbol)
    //             ->select('lotsize')
    //             ->first();
            
    //         return $result ? $result->lotsize : '---';
    //     } catch (\Exception $e) {
    //         \Log::error('Error fetching lot size: ' . $e->getMessage());
    //         return '---';
    //     }
    // }

    // private function getOptionsAtmData($connection, $symbol, $insertedAt)
    // {
    //     try {
    //         // Convert inserted_at to the format needed for the query
    //         $timeForQuery = Carbon::parse($insertedAt)->format('Y-m-d H:i:s');

    //         $query = "
    //             SELECT oad.*
    //             FROM options_atm_data oad
    //             WHERE oad.index_symbol = (
    //                 SELECT symbol
    //                 FROM angel_instruments_data
    //                 WHERE name = (
    //                     SELECT name
    //                     FROM angel_instruments_data
    //                     WHERE symbol = ?
    //                     LIMIT 1
    //                 ) AND exch_seg = 'NSE'
    //                 LIMIT 1
    //             )
    //             AND oad.run_id = (
    //                 SELECT run_id
    //                 FROM options_atm_data
    //                 WHERE index_symbol = oad.index_symbol
    //                 ORDER BY ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) ASC
    //                 LIMIT 1
    //             )
    //         ";

    //         $result = $connection->select($query, [$symbol, $timeForQuery]);
            
    //         return $result;
    //     } catch (\Exception $e) {
    //         // Log the error and return empty array
    //         \Log::error('Error fetching options ATM data: ' . $e->getMessage());
    //         return [];
    //     }
    // }

    // Directional START
    public function directional()
    {
        $pageTitle = 'Portfolios Directional';
        
        // Just return the view without data - AJAX will load it
        return view($this->activeTemplate . 'user.oi_buildup.directional', compact('pageTitle'));
    }

    public function directionalFetch(Request $request)
    {
        // Get date filter if provided
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter');
        
        // Step 1: Fetch from symbol_ltps (cached for better performance)
        $ltpData = Cache::remember('symbol_ltps_data', 30, function() {
            return SymbolLtps::select('symbol_token', 'ltp', 'highest_ltp', 'highest_time')
                ->get()
                ->keyBy('symbol_token');
        });

        // Step 2: Fetch positions from second DB with date filter
        $connection = DB::connection('mysql_oi_buildup');
        $query = $connection->table('positions_on_buildup')
            ->select(
                'id',
                'created_at',
                'symbol_name',
                'symbol_token',
                'transaction_type',
                'lot_size',
                'buy_quantity',
                'buy_price',
                'sell_quantity',
                'sell_price',
                'ltp',
                'profit',
                'total_value',
                'realised_profit',
                'unrealised_profit',
                'portfolio_type',
                'buildup_type'
            )
            ->where('portfolio_type', 'PF_1')
            ->orderBy('created_at', 'desc');

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
            $token = $position->symbol_token;
            $ltp = $ltpData[$token] ?? null;

            $latestLtp = $ltp?->ltp ?? 0;
            $position->latest_ltp = $latestLtp;
            $position->highest_ltp = $ltp?->highest_ltp ?? 0;
            $position->highest_time = $ltp?->highest_time;

            if (strtolower($position->transaction_type) === 'buy') {
                // Calculate profit
                $position->profit = round(
                    ($latestLtp * $position->lot_size * $position->buy_quantity) - $position->total_value,
                    2
                );
            }else if(strtolower($position->transaction_type) === 'sell'){
                // SELL: Profit = (Sell value) - (Current LTP value)
                $sellValue = $position->sell_price * $position->sell_quantity * $position->lot_size;
                $currentValue = $latestLtp * $position->sell_quantity * $position->lot_size;
                $position->profit = round($sellValue - $currentValue, 2);
            } else {
                $position->profit = 0; // fallback if type unknown
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
    // Directional END

    // Bi Directional START
    public function biDirectional()
    {
        $pageTitle = 'Portfolios Bi-Directional';
        
        // Just return the view without data - AJAX will load it
        return view($this->activeTemplate . 'user.oi_buildup.bi-directional', compact('pageTitle'));
    }

    public function biDirectionalFetch(Request $request)
    {
        // Get date filter if provided
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter');
        
        // Step 1: Fetch from symbol_ltps (cached for better performance)
        $ltpData = Cache::remember('symbol_ltps_data', 30, function() {
            return SymbolLtps::select('symbol_token', 'ltp', 'highest_ltp', 'highest_time')
                ->get()
                ->keyBy('symbol_token');
        });

        // Step 2: Fetch positions from second DB with date filter
        $connection = DB::connection('mysql_oi_buildup');
        $query = $connection->table('positions_on_buildup')
            ->select(
                'id',
                'created_at',
                'symbol_name',
                'symbol_token',
                'transaction_type',
                'lot_size',
                'buy_quantity',
                'buy_price',
                'sell_quantity',
                'sell_price',
                'ltp',
                'profit',
                'total_value',
                'realised_profit',
                'unrealised_profit',
                'portfolio_type',
                'buildup_type'
            )
            ->where('portfolio_type', 'PF_2')
            ->orderBy('created_at', 'desc');

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
            $token = $position->symbol_token;
            $ltp = $ltpData[$token] ?? null;

            $latestLtp = $ltp?->ltp ?? 0;
            $position->latest_ltp = $latestLtp;
            $position->highest_ltp = $ltp?->highest_ltp ?? 0;
            $position->highest_time = $ltp?->highest_time;

            if (strtolower($position->transaction_type) === 'buy') {
                // Calculate profit
                $position->profit = round(
                    ($latestLtp * $position->lot_size * $position->buy_quantity) - $position->total_value,
                    2
                );
            }else if(strtolower($position->transaction_type) === 'sell'){
                // SELL: Profit = (Sell value) - (Current LTP value)
                $sellValue = $position->sell_price * $position->sell_quantity * $position->lot_size;
                $currentValue = $latestLtp * $position->sell_quantity * $position->lot_size;
                $position->profit = round($sellValue - $currentValue, 2);
            } else {
                $position->profit = 0; // fallback if type unknown
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
    // Bi Directional END

    // Futures-Direct START
    public function futuresDirect()
    {
        $pageTitle = 'Portfolios Futures Direct';
        
        // Just return the view without data - AJAX will load it
        return view($this->activeTemplate . 'user.oi_buildup.futures-direct', compact('pageTitle'));
    }

    public function futuresDirectFetch(Request $request)
    {
        // Get date filter if provided
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter');
        
        // Step 1: Fetch from symbol_ltps (cached for better performance)
        $ltpData = Cache::remember('symbol_ltps_data', 30, function() {
            return SymbolLtps::select('symbol_token', 'ltp', 'highest_ltp', 'highest_time')
                ->get()
                ->keyBy('symbol_token');
        });

        // Step 2: Fetch positions from second DB with date filter
        $connection = DB::connection('mysql_oi_buildup');
        $query = $connection->table('positions_on_buildup')
            ->select(
                'id',
                'created_at',
                'symbol_name',
                'symbol_token',
                'transaction_type',
                'lot_size',
                'buy_quantity',
                'buy_price',
                'sell_quantity',
                'sell_price',
                'ltp',
                'profit',
                'total_value',
                'realised_profit',
                'unrealised_profit',
                'portfolio_type',
                'buildup_type'
            )
            ->where('portfolio_type', 'Portfolio-Futures-Direct')
            ->orderBy('created_at', 'desc');

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
            $token = $position->symbol_token;
            $ltp = $ltpData[$token] ?? null;

            $latestLtp = $ltp?->ltp ?? 0;
            $position->latest_ltp = $latestLtp;
            $position->highest_ltp = $ltp?->highest_ltp ?? 0;
            $position->highest_time = $ltp?->highest_time;

            if (strtolower($position->transaction_type) === 'buy') {
                // Calculate profit
                $position->profit = round(
                    ($latestLtp * $position->lot_size * $position->buy_quantity) - $position->total_value,
                    2
                );
            }else if(strtolower($position->transaction_type) === 'sell'){
                // SELL: Profit = (Sell value) - (Current LTP value)
                $sellValue = $position->sell_price * $position->sell_quantity * $position->lot_size;
                $currentValue = $latestLtp * $position->sell_quantity * $position->lot_size;
                $position->profit = round($sellValue - $currentValue, 2);
            } else {
                $position->profit = 0; // fallback if type unknown
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
    // Futures-Direct END

    // Options-Opposite START
    public function optionsOpposite()
    {
        $pageTitle = 'Portfolios Options Opposite';
        
        // Just return the view without data - AJAX will load it
        return view($this->activeTemplate . 'user.oi_buildup.options-opposite', compact('pageTitle'));
    }

    public function optionsOppositeFetch(Request $request)
    {
        // Get date filter if provided
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter');
        
        // Step 1: Fetch from symbol_ltps (cached for better performance)
        $ltpData = Cache::remember('symbol_ltps_data', 30, function() {
            return SymbolLtps::select('symbol_token', 'ltp', 'highest_ltp', 'highest_time')
                ->get()
                ->keyBy('symbol_token');
        });

        // Step 2: Fetch positions from second DB with date filter
        $connection = DB::connection('mysql_oi_buildup');
        $query = $connection->table('positions_on_buildup')
            ->select(
                'id',
                'created_at',
                'symbol_name',
                'symbol_token',
                'transaction_type',
                'lot_size',
                'buy_quantity',
                'buy_price',
                'sell_quantity',
                'sell_price',
                'ltp',
                'profit',
                'total_value',
                'realised_profit',
                'unrealised_profit',
                'portfolio_type',
                'buildup_type'
            )
            ->where('portfolio_type', 'Portfolio-Options-Opposite')
            ->orderBy('created_at', 'desc');

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
            $token = $position->symbol_token;
            $ltp = $ltpData[$token] ?? null;

            $latestLtp = $ltp?->ltp ?? 0;
            $position->latest_ltp = $latestLtp;
            $position->highest_ltp = $ltp?->highest_ltp ?? 0;
            $position->highest_time = $ltp?->highest_time;

            if (strtolower($position->transaction_type) === 'buy') {
                // Calculate profit
                $position->profit = round(
                    ($latestLtp * $position->lot_size * $position->buy_quantity) - $position->total_value,
                    2
                );
            }else if(strtolower($position->transaction_type) === 'sell'){
                // SELL: Profit = (Sell value) - (Current LTP value)
                $sellValue = $position->sell_price * $position->sell_quantity * $position->lot_size;
                $currentValue = $latestLtp * $position->sell_quantity * $position->lot_size;
                $position->profit = round($sellValue - $currentValue, 2);
            } else {
                $position->profit = 0; // fallback if type unknown
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
    // Options-Opposite END
    
    // Futures-Opposite START
    public function futuresOpposite()
    {
        $pageTitle = 'Portfolios Futures Opposite';
        
        // Just return the view without data - AJAX will load it
        return view($this->activeTemplate . 'user.oi_buildup.futures-opposite', compact('pageTitle'));
    }

    public function futuresOppositeFetch(Request $request)
    {
        // Get date filter if provided
        $dateFilter = $request->get('date_filter');
        $typeFilter = $request->get('type_filter');
        
        // Step 1: Fetch from symbol_ltps (cached for better performance)
        $ltpData = Cache::remember('symbol_ltps_data', 30, function() {
            return SymbolLtps::select('symbol_token', 'ltp', 'highest_ltp', 'highest_time')
                ->get()
                ->keyBy('symbol_token');
        });

        // Step 2: Fetch positions from second DB with date filter
        $connection = DB::connection('mysql_oi_buildup');
        $query = $connection->table('positions_on_buildup')
            ->select(
                'id',
                'created_at',
                'symbol_name',
                'symbol_token',
                'transaction_type',
                'lot_size',
                'buy_quantity',
                'buy_price',
                'sell_quantity',
                'sell_price',
                'ltp',
                'profit',
                'total_value',
                'realised_profit',
                'unrealised_profit',
                'portfolio_type',
                'buildup_type'
            )
            ->where('portfolio_type', 'Portfolio-Futures-Opposite')
            ->orderBy('created_at', 'desc');

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
            $token = $position->symbol_token;
            $ltp = $ltpData[$token] ?? null;

            $latestLtp = $ltp?->ltp ?? 0;
            $position->latest_ltp = $latestLtp;
            $position->highest_ltp = $ltp?->highest_ltp ?? 0;
            $position->highest_time = $ltp?->highest_time;

            if (strtolower($position->transaction_type) === 'buy') {
                // Calculate profit
                $position->profit = round(
                    ($latestLtp * $position->lot_size * $position->buy_quantity) - $position->total_value,
                    2
                );
            }else if(strtolower($position->transaction_type) === 'sell'){
                // SELL: Profit = (Sell value) - (Current LTP value)
                $sellValue = $position->sell_price * $position->sell_quantity * $position->lot_size;
                $currentValue = $latestLtp * $position->sell_quantity * $position->lot_size;
                $position->profit = round($sellValue - $currentValue, 2);
            } else {
                $position->profit = 0; // fallback if type unknown
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
    // Futures-Opposite END


}