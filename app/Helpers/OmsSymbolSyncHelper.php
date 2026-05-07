<?php


namespace App\Helpers;

use App\Models\OmsConfigMaster;
use App\Models\OmsConfigs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OmsSymbolSyncHelper
{
    public function syncSymbols()
    {
        $addedCount = 0;
        $errorCount = 0;
        
        // Get all active master configurations
        $masterConfigs = OmsConfigMaster::where('status', 1)->with('broker')->get();

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

    private function syncSymbolsForMaster(OmsConfigMaster $master)
    {
        $addedCount = 0;
        $errorCount = 0;

        // Get symbols from buildup data that match this master config
        $symbolsData = $this->getSymbolsFromBuildup(
            $master->buildup_type, 
            $master->portfolio_type
        );

        if (empty($symbolsData)) {
            Log::info("No symbols found for master config", [
                'master_id' => $master->id,
                'buildup_type' => $master->buildup_type,
                'portfolio_type' => $master->portfolio_type
            ]);
            return ['added' => 0, 'errors' => 0];
        }

        // Get existing symbols for this master to avoid duplicates
        $existingSymbols = OmsConfigs::where('master_config_id', $master->id)
            ->pluck('symbol_name')
            ->toArray();

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

                // Calculate pyramid quantities
                $pyramids = calculatePyramids($master->quantity, $pyramidSteps);
                $cronTiming = $master->pyramid_freq > 0 
                    ? Carbon::now()->subMinutes($master->pyramid_freq)
                    : Carbon::now();

                $quantity = $master->quantity * ($symbolData->lot_size ?? 1);

                $insertData[] = [
                    'master_config_id' => $master->id,
                    'symbol_name' => $symbolName,
                    'token' => $symbolData->symbol_token,
                    'symbol_type' => $symbolType,
                    'broker_api_id' => $master->broker_api_id,
                    'disc_ltp' => $master->disc_ltp,
                    'portfolio_type' => $master->portfolio_type,
                    'buildup_type' => $master->buildup_type,
                    'product' => $master->product,
                    'order_type' => $master->order_type,
                    'pyramid_percent' => $master->pyramid_percent,
                    'quantity' => $quantity,
                    'txn_type' => $symbolData->transaction_type,
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
                    'is_api_pushed' => 0, // Ready for order placement
                    'cron_run_at' => $cronTiming,
                    // 'symbol_first_seen_at' => Carbon::now(),
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
                    OmsConfigs::insert($chunk);
                }
            });

            Log::info("Added symbols for master config", [
                'master_id' => $master->id,
                'added_count' => count($insertData)
            ]);
        }

        return ['added' => $addedCount, 'errors' => $errorCount];
    }

    private function getSymbolsFromBuildup($buildupType, $portfolioType)
    {
        try {
            $connection = DB::connection('mysql_oi_buildup');
            
            $query = $connection->table('positions_on_buildup')
                ->select(
                    'symbol_name',
                    'symbol_token', 
                    'transaction_type',
                    'lot_size'
                )
                ->where('portfolio_type', $portfolioType)
                ->whereDate('created_at', Carbon::today())
                ->distinct();

            if (strtolower($buildupType) !== 'all') {
                $query->where('buildUp_type', $buildupType);
            }

            return $query->get();
            
        } catch (\Exception $e) {
            Log::error("Error fetching symbols from buildup", [
                'buildup_type' => $buildupType,
                'portfolio_type' => $portfolioType,
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
}