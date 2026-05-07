<?php


namespace App\Helpers;

use App\Models\HistoricalOrder;
use App\Models\HistoricalPortfolio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HistoricalSymbolSyncHelper
{
    public function syncSymbols()
    {
        $addedCount = 0;
        $errorCount = 0;
        
        // Get all active master configurations
        $today = Carbon::today();
        $masterConfigs = HistoricalOrder::where('status', 1)->whereDate('created_at', $today)->with('broker')->get();

        foreach ($masterConfigs as $master) {
            try {
                $result = $this->syncSymbolsForMaster($master);
                $addedCount += $result['added'];
                $errorCount += $result['errors'];
                
                // Update last sync time
                $master->update(['last_sync_at' => Carbon::now()]);
                
            } catch (\Exception $e) {
                Log::error("Error syncing symbols for master config", [
                    'master_id' => $master->id,
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }

        return ['added' => $addedCount, 'errors' => $errorCount];
    }

    private function syncSymbolsForMaster(HistoricalOrder $master)
    {
        $addedCount = 0;
        $errorCount = 0;

        $symbolsData = $this->getSymbolsFromBuildup($master->buildup_type,$master->order_date);

        if (empty($symbolsData)) {
            Log::info("No symbols found for master config", [
                'master_id' => $master->id,
                'buildup_type' => $master->buildup_type,
            ]);
            return ['added' => 0, 'errors' => 0];
        }

        // Get existing symbols for this master to avoid duplicates
        $existingSymbols = HistoricalPortfolio::where('config_id', $master->id)->pluck('symbol_name')->toArray();
        $insertData = [];
        $pyramidSteps = $this->getPyramidSteps($master->pyramid_percent);

        foreach ($symbolsData as $symbolData) {
            $symbolName = strtoupper(trim($symbolData->symbol_name));
            
            // Skip if symbol already exists for this master config
            if (in_array($symbolName, $existingSymbols)) {
                continue;
            }

            try {
                $symbolType = $this->getSymbolType($symbolName);
                if (!$symbolType) {
                    Log::warning("Invalid symbol type", ['symbol' => $symbolName]);
                    $errorCount++;
                    continue;
                }

                $txnType = $this->determineTransactionType($symbolData->trend, $symbolType);
                if (!$txnType) {
                    continue;
                }

                // Calculate pyramid quantities
                $pyramids = calculatePyramids($master->quantity, $pyramidSteps);
                $cronTiming = $master->pyramid_freq > 0 ? Carbon::now()->subMinutes($master->pyramid_freq) : Carbon::now();

                $quantity = $master->quantity * ($symbolData->lot_size ?? 1);

                $insertData[] = [
                    'config_id' => $master->id,
                    'symbol_name' => $symbolName,
                    'token' => $symbolData->symbol_token,
                    'symbol_type' => $symbolType,
                    'broker_api_id' => $master->broker_api_id,
                    'disc_ltp' => $master->disc_ltp,
                    'buildup_type' => $master->buildup_type,
                    'product' => $master->product,
                    'order_type' => $master->order_type,
                    'pyramid_percent' => $master->pyramid_percent,
                    'quantity' => $quantity,
                    'txn_type' => $txnType,
                    'pyramid_freq' => $master->pyramid_freq,
                    'pyramid_1' => $pyramids[0] ?? 0,
                    'pyramid_2' => $pyramids[1] ?? 0,
                    'pyramid_3' => $pyramids[2] ?? 0,
                    'exit_1_qty' => $master->exit_1_qty,
                    'exit_1_target' => $master->exit_1_target,
                    'exit_2_qty' => $master->exit_2_qty,
                    'exit_2_target' => $master->exit_2_target,
                    'user_id' => $master->user_id,
                    'status' => 1,
                    'is_api_pushed' => 0,
                    'cron_run_at' => $cronTiming,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];

                $addedCount++;

            } catch (\Exception $e) {
                Log::error("Error processing symbol for master", [
                    'master_id' => $master->id,
                    'symbol' => $symbolName,
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }

        // Bulk insert new symbols
        if (!empty($insertData)) {
            DB::transaction(function () use ($insertData) {
                foreach (array_chunk($insertData, 1000) as $chunk) {
                    HistoricalPortfolio::insert($chunk);
                }
            });

            Log::info("Added symbols for master config", [
                'master_id' => $master->id,
                'added_count' => count($insertData)
            ]);
        }

        return ['added' => $addedCount, 'errors' => $errorCount];
    }

    // THIS PICK ALL 
    // private function getSymbolsFromBuildup($buildupType,$date)
    // {
    //     try {
    //         $symbolsData = DB::table('historical_options_data')
    //             ->select(
    //                 'future_symbol',
    //                 'future_token',
    //                 'future_volume',
    //                     'ce_symbol',
    //                     'ce_token',
    //                     'pe_symbol',
    //                     'pe_token',
    //                     'trend'
    //             )
    //             ->whereDate('date', $date) // ✅ Fetch yesterday's data only
    //             ->where('trend', $buildupType) 
    //             ->get();

    //         $allTokens = [];
    //         foreach ($symbolsData as $row) {
    //             if (!empty($row->future_token)) $allTokens[] = $row->future_token;
    //             if (!empty($row->ce_token))     $allTokens[] = $row->ce_token;
    //             // if (!empty($row->pe_token))     $allTokens[] = $row->pe_token;
    //         }

    //         $lotSizes = DB::table('angel_api_instruments')
    //             ->whereIn('token', array_unique($allTokens))
    //             ->pluck('lotsize', 'token')
    //             ->toArray();

    //         $finalSymbols = collect();

    //         foreach ($symbolsData as $row) {
    //             // FUTURE symbol
    //             if (!empty($row->future_symbol)) {
    //                 $finalSymbols->push((object) [
    //                     'symbol_name'      => strtoupper(trim($row->future_symbol)),
    //                     'symbol_token'     => $row->future_token,
    //                     'lot_size'         => $lotSizes[$row->future_token] ?? 1,
    //                     'trend'            => $row->trend,
    //                 ]);
    //             }

    //             // CE option
    //             if (!empty($row->ce_symbol)) {
    //                 $finalSymbols->push((object) [
    //                     'symbol_name'      => strtoupper(trim($row->ce_symbol)),
    //                     'symbol_token'     => $row->ce_token,
    //                     'lot_size'         => $lotSizes[$row->ce_token] ?? 1,
    //                     'trend'            => $row->trend,
    //                 ]);
    //             }

    //             // PE option
    //             if (!empty($row->pe_symbol)) {
    //                 $finalSymbols->push((object) [
    //                     'symbol_name'      => strtoupper(trim($row->pe_symbol)),
    //                     'symbol_token'     => $row->pe_token,
    //                     'lot_size'         => $lotSizes[$row->pe_token] ?? 1,
    //                     'trend'            => $row->trend,
    //                 ]);
    //             }
    //         }

    //         // Return distinct symbols only
    //         return $finalSymbols->unique('symbol_name')->values();

    //     } catch (\Exception $e) {
    //         Log::error("Error fetching symbols from buildup", [
    //             'buildup_type' => $buildupType,
    //             'error' => $e->getMessage()
    //         ]);

    //         return collect();
    //     }
    // }

    // THIS PICK EACH UNDERLINE HIGHEST CE & PE 
    // private function getSymbolsFromBuildup($buildupType, $date)
    // {
    //     try {
    //         // ✅ Fetch all rows for that date & buildup type
    //         $symbolsData = DB::table('historical_options_data')
    //             ->select(
    //                 'underlying',
    //                 'future_symbol',
    //                 'future_token',
    //                 'future_volume',
    //                 'ce_symbol',
    //                 'ce_token',
    //                 'ce_oi_chg_pct',
    //                 'pe_symbol',
    //                 'pe_token',
    //                 'pe_oi_chg_pct',
    //                 'trend'
    //             )
    //             ->whereDate('date', $date)
    //             ->where('trend', $buildupType)
    //             ->get();

    //         if ($symbolsData->isEmpty()) {
    //             return collect();
    //         }

    //         // ✅ Pick only the highest CE/PE per underlying
    //         $grouped = $symbolsData->groupBy('underlying');
    //         $filtered = collect();

    //         foreach ($grouped as $underlying => $rows) {
    //             $future = $rows->first(); // same FUT for all strikes of this underlying

    //             // Get CE row with max OI% change
    //             $bestCE = $rows->sortByDesc('ce_oi_chg_pct')->first();

    //             // Get PE row with max OI% change
    //             $bestPE = $rows->sortByDesc('pe_oi_chg_pct')->first();

    //             $filtered->push((object)[
    //                 'underlying'     => $underlying,
    //                 'future_symbol'  => $future->future_symbol,
    //                 'future_token'   => $future->future_token,
    //                 'future_volume'  => $future->future_volume,
    //                 'ce_symbol'      => $bestCE->ce_symbol ?? null,
    //                 'ce_token'       => $bestCE->ce_token ?? null,
    //                 'pe_symbol'      => $bestPE->pe_symbol ?? null,
    //                 'pe_token'       => $bestPE->pe_token ?? null,
    //                 'trend'          => $future->trend,
    //             ]);
    //         }

    //         // ✅ Collect all tokens for lot size lookup
    //         $allTokens = [];
    //         foreach ($filtered as $row) {
    //             if (!empty($row->future_token)) $allTokens[] = $row->future_token;
    //             if (!empty($row->ce_token))     $allTokens[] = $row->ce_token;
    //             if (!empty($row->pe_token))     $allTokens[] = $row->pe_token;
    //         }

    //         $lotSizes = DB::table('angel_api_instruments')
    //             ->whereIn('token', array_unique($allTokens))
    //             ->pluck('lotsize', 'token')
    //             ->toArray();

    //         // ✅ Final symbols in same structure as before
    //         $finalSymbols = collect();

    //         foreach ($filtered as $row) {
    //             // FUTURE symbol
    //             // if (!empty($row->future_symbol)) {
    //             //     $finalSymbols->push((object) [
    //             //         'symbol_name'  => strtoupper(trim($row->future_symbol)),
    //             //         'symbol_token' => $row->future_token,
    //             //         'lot_size'     => $lotSizes[$row->future_token] ?? 1,
    //             //         'trend'        => $row->trend,
    //             //     ]);
    //             // }

    //             // CE option
    //             if (!empty($row->ce_symbol)) {
    //                 $finalSymbols->push((object) [
    //                     'symbol_name'  => strtoupper(trim($row->ce_symbol)),
    //                     'symbol_token' => $row->ce_token,
    //                     'lot_size'     => $lotSizes[$row->ce_token] ?? 1,
    //                     'trend'        => $row->trend,
    //                 ]);
    //             }

    //             // PE option
    //             if (!empty($row->pe_symbol)) {
    //                 $finalSymbols->push((object) [
    //                     'symbol_name'  => strtoupper(trim($row->pe_symbol)),
    //                     'symbol_token' => $row->pe_token,
    //                     'lot_size'     => $lotSizes[$row->pe_token] ?? 1,
    //                     'trend'        => $row->trend,
    //                 ]);
    //             }
    //         }

    //         return $finalSymbols->unique('symbol_name')->values();

    //     } catch (\Exception $e) {
    //         Log::error("Error fetching symbols from buildup", [
    //             'buildup_type' => $buildupType,
    //             'error' => $e->getMessage()
    //         ]);

    //         return collect();
    //     }
    // }

    // THIS PICK TOP HIGEST CE(5) AND PE(5)
    private function getSymbolsFromBuildup($buildupType, $date)
    {
        try {
            // ✅ Fetch all rows for that date & buildup type
            $symbolsData = DB::table('historical_options_data')
                ->select(
                    'underlying',
                    'future_symbol',
                    'future_token',
                    'future_volume',
                    'ce_symbol',
                    'ce_token',
                    'ce_oi_chg_pct',
                    'pe_symbol',
                    'pe_token',
                    'pe_oi_chg_pct',
                    'trend'
                )
                ->whereDate('date', $date)
                ->where('trend', $buildupType)
                ->get();

            if ($symbolsData->isEmpty()) {
                return collect();
            }

            // ✅ Pick only the highest CE/PE per underlying
            $grouped = $symbolsData->groupBy('underlying');
            $ceFiltered = collect();
            $peFiltered = collect();

            foreach ($grouped as $underlying => $rows) {
                $future = $rows->first();

                // Highest CE per underlying
                $bestCE = $rows->sortByDesc('ce_oi_chg_pct')->first();
                if ($bestCE && $bestCE->ce_token) {
                    $ceFiltered->push((object)[
                        'symbol_name'  => strtoupper(trim($bestCE->ce_symbol)),
                        'symbol_token' => $bestCE->ce_token,
                        'oi_chg_pct'   => $bestCE->ce_oi_chg_pct,
                        'trend'        => $future->trend,
                    ]);
                }

                // Highest PE per underlying
                $bestPE = $rows->sortByDesc('pe_oi_chg_pct')->first();
                if ($bestPE && $bestPE->pe_token) {
                    $peFiltered->push((object)[
                        'symbol_name'  => strtoupper(trim($bestPE->pe_symbol)),
                        'symbol_token' => $bestPE->pe_token,
                        'oi_chg_pct'   => $bestPE->pe_oi_chg_pct,
                        'trend'        => $future->trend,
                    ]);
                }
            }

            // ✅ Take Top 5 CE & Top 5 PE
            $topCE = $ceFiltered->sortByDesc('oi_chg_pct')->take(5);
            $topPE = $peFiltered->sortByDesc('oi_chg_pct')->take(5);

            // ✅ Merge results
            $topSymbols = $topCE->merge($topPE);

            // ✅ Collect all tokens for lot size lookup
            $allTokens = $topSymbols->pluck('symbol_token')->filter()->unique()->toArray();

            $lotSizes = DB::table('angel_api_instruments')
                ->whereIn('token', $allTokens)
                ->pluck('lotsize', 'token')
                ->toArray();

            // ✅ Attach lot size
            $finalSymbols = $topSymbols->map(function ($row) use ($lotSizes) {
                return (object)[
                    'symbol_name'  => $row->symbol_name,
                    'symbol_token' => $row->symbol_token,
                    'lot_size'     => $lotSizes[$row->symbol_token] ?? 1,
                    'oi_chg_pct'   => $row->oi_chg_pct,
                    'trend'        => $row->trend,
                ];
            });

            return $finalSymbols->values();

        } catch (\Exception $e) {
            Log::error("Error fetching symbols from buildup", [
                'buildup_type' => $buildupType,
                'error' => $e->getMessage()
            ]);

            return collect();
        }
    }

    private function getSymbolType($symbolName)
    {
        if (substr($symbolName, -2) === 'CE') return 'CE';
        if (substr($symbolName, -2) === 'PE') return 'PE';
        if (strpos($symbolName, 'FUT') !== false) return 'FUT';
        return null;
    }

    private function getPyramidSteps($pyramidPercent)
    {
        return match ((int)$pyramidPercent) {
            33 => 3,
            50 => 2, 
            default => 1,
        };
    }

    private function determineTransactionType($trend, $symbolType)
    {
        $trend = strtolower($trend);

        // Bullish → BUY CE
        if (($trend === 'strong bullish' || $trend === 'mild bullish') && $symbolType === 'CE') {
            return 'BUY';
        }

        // Bearish → BUY PE
        if (($trend === 'strong bearish' || $trend === 'mild bearish') && $symbolType === 'PE') {
            return 'BUY';
        }

        return null;
    }

}