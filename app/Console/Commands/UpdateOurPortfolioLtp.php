<?php

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use App\Models\SymbolLtps;
// use App\Traits\AngelApiAuth;
// use Illuminate\Support\Facades\DB;
// use Carbon\Carbon;

// class UpdateOurPortfolioLtp extends Command
// {
//     use AngelApiAuth;

//     protected $signature = 'our-portfolio:update-ltp';
//     protected $description = 'Update LTP values for all required symbols (FUT, CE, PE) - Daily high resets each day';

//     private $defaultConnection;
//     private $currentDate;
//     private $isFirstRunToday = false;

//     public function handle()
//     {
//         // if (!$this->isBetween915AMto1535PM()) {
//         //     $this->info('Market is closed. Skipping LTP update.');
//         //     return;
//         // }

//         try {
//             $this->defaultConnection = DB::connection();
//             $this->currentDate = now()->format('Y-m-d');

//             // ✅ CRITICAL: Check if this is first run today
//             $this->checkAndResetDailyData();

//             // STEP 1: Fetch unique symbols (FUT, CE, PE)
//             $symbols = $this->getUniqueSymbols();

//             if ($symbols->isEmpty()) {
//                 $this->info('No symbols found for LTP update.');
//                 return;
//             }

//             $this->info("Found {$symbols->count()} unique FUT, CE, PE symbols to update.");

//             // STEP 2: Process symbols in batches
//             $this->processSymbolsInBatches($symbols);

//         } catch (\Exception $e) {
//             $this->error('Error updating LTP: ' . $e->getMessage());
//             \Log::error('LTP Update Error', [
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ]);
//         } finally {
//             if ($this->defaultConnection) {
//                 $this->defaultConnection->disconnect();
//             }
//         }
//     }

//     /**
//      * ✅ NEW: Check if this is first run of the day and reset/clean old data
//      */
//     private function checkAndResetDailyData()
//     {
//         // Check if any record exists with today's date
//         $todayRecordsExist = SymbolLtps::whereDate('last_updated_at', $this->currentDate)->exists();

//         if (!$todayRecordsExist) {
//             $this->isFirstRunToday = true;
//             $this->info('🔄 First run of the day - Clearing old LTP data and starting fresh');

//             // ✅ OPTION 1: Delete ALL old records (keeps only today's data)
//             // Recommended if you only need current day data
//             $deletedCount = SymbolLtps::whereDate('trade_date', '<', $this->currentDate)->delete();
//             $this->info("✅ Deleted {$deletedCount} old LTP records from previous days");

//             // Reset highest values for any remaining records
//             SymbolLtps::whereDate('trade_date', $this->currentDate)->update(['highest_ltp' => 0, 'highest_time' => null]);

//         } else {
//             $this->info('✓ Continuing LTP updates for today');
//         }
//     }

//     /**
//      * Fetch FUT, CE, PE symbols & tokens from historical_options_data.
//      */
//     private function getUniqueSymbols()
//     {
//         $latestDate = DB::table('historical_options_data')->max('date');

//         if (!$latestDate) {
//             $this->error('No data found in historical_options_data table');
//             return collect();
//         }

//         $symbols = DB::table('historical_options_data')
//             ->select(
//                 'future_symbol as symbol_name',
//                 'future_token as symbol_token'
//             )
//             ->whereNotNull('future_token')
//             ->where('future_token', '!=', '')
//             ->whereDate('date', $latestDate);

//         $ceSymbols = DB::table('historical_options_data')
//             ->select(
//                 'ce_symbol as symbol_name',
//                 'ce_token as symbol_token'
//             )
//             ->whereNotNull('ce_token')
//             ->where('ce_token', '!=', '')
//             ->whereDate('date', $latestDate);

//         $peSymbols = DB::table('historical_options_data')
//             ->select(
//                 'pe_symbol as symbol_name',
//                 'pe_token as symbol_token'
//             )
//             ->whereNotNull('pe_token')
//             ->where('pe_token', '!=', '')
//             ->whereDate('date', $latestDate);

//         // Merge FUT + CE + PE into one collection & remove duplicates
//         $allSymbols = $symbols->union($ceSymbols)->union($peSymbols)->distinct()->get();

//         $this->info("Latest data date: {$latestDate}");
        
//         return $allSymbols;
//     }

//     /**
//      * Process symbols in optimized batches.
//      */
//     private function processSymbolsInBatches($symbols)
//     {
//         $chunkSize = 25;
//         $processed = 0;
//         $totalChunks = ceil($symbols->count() / $chunkSize);
//         $currentChunk = 0;

//         $symbols->chunk($chunkSize)->each(function ($chunk) use (&$processed, &$currentChunk, $totalChunks) {
//             $currentChunk++;
//             $symbolTokens = $chunk->pluck('symbol_token')->toArray();

//             $this->info("Processing chunk {$currentChunk}/{$totalChunks}: " . count($symbolTokens) . " symbols");

//             // Get LTP data from Angel API
//             $ltpData = $this->getBulkLtpData($symbolTokens);

//             if (empty($ltpData)) {
//                 $this->error("Failed to fetch LTP data for chunk {$currentChunk}");
//                 return;
//             }

//             $processed += $this->updateLtpDataInTransaction($chunk, $ltpData);

//             // Sleep between chunks to avoid hitting API limits
//             if ($currentChunk < $totalChunks) {
//                 $this->info("Processed chunk {$currentChunk}, sleeping 2s...");
//                 sleep(2);
//             }
//         });

//         $this->info("✅ LTP update completed successfully. Updated {$processed} symbols.");
//     }

//     /**
//      * ✅ FIXED: Update symbol_ltps table with DAILY high reset logic
//      */
//     private function updateLtpDataInTransaction($chunk, $ltpData)
//     {
//         $processed = 0;

//         $this->defaultConnection->transaction(function () use ($chunk, $ltpData, &$processed) {
//             $symbolTokens = collect($ltpData)->pluck('symbolToken')->toArray();
//             $existingRecords = SymbolLtps::whereIn('symbol_token', $symbolTokens)
//                 ->get()
//                 ->keyBy('symbol_token');

//             $now = now();
//             $currentTime = $now->format('H:i:s');
//             $currentDate = $now->format('Y-m-d');

//             foreach ($ltpData as $item) {
//                 if (empty($item['ltp']) || $item['ltp'] <= 0) {
//                     continue;
//                 }

//                 $symbol = $chunk->firstWhere('symbol_token', $item['symbolToken']);
//                 if (!$symbol) {
//                     continue;
//                 }

//                 $existingRecord = $existingRecords->get($item['symbolToken']);

//                 $updateData = [
//                     'symbol_name' => $symbol->symbol_name,
//                     'ltp' => $item['ltp'],
//                     'last_updated_at' => $now
//                 ];

//                 // ✅ CRITICAL FIX: Daily high logic
//                 if ($existingRecord) {
//                     // Check if existing record is from today
//                     $existingDate = Carbon::parse($existingRecord->last_updated_at)->format('Y-m-d');
                    
//                     if ($existingDate === $currentDate) {
//                         // Same day - compare with today's high
//                         if ($item['ltp'] > ($existingRecord->highest_ltp ?: 0)) {
//                             $updateData['highest_ltp'] = $item['ltp'];
//                             $updateData['highest_time'] = $currentTime;
//                         } else {
//                             // Keep existing today's high
//                             $updateData['highest_ltp'] = $existingRecord->highest_ltp;
//                             $updateData['highest_time'] = $existingRecord->highest_time;
//                         }
//                     } else {
//                         // ✅ NEW DAY: Reset high to current LTP (fresh start)
//                         $updateData['highest_ltp'] = $item['ltp'];
//                         $updateData['highest_time'] = $currentTime;
//                         $this->info("🔄 Reset daily high for {$symbol->symbol_name}: ₹{$item['ltp']}");
//                     }
//                 } else {
//                     // ✅ New record - set current LTP as first high of the day
//                     $updateData['highest_ltp'] = $item['ltp'];
//                     $updateData['highest_time'] = $currentTime;
//                 }

//                 SymbolLtps::updateOrCreate(
//                     // ['symbol_token' => $item['symbolToken']],
//                     [
//                         'symbol_token' => $item['symbolToken'],
//                         'trade_date' => $currentDate, // <-- Add this
//                     ],
//                     $updateData
//                 );

//                 $processed++;
//             }
//         });

//         return $processed;
//     }

//     /**
//      * Check if current time is between market hours.
//      */
//     private function isBetween915AMto1535PM()
//     {
//         $now = now();
//         $start = now()->setTime(9, 15, 0);
//         $end = now()->setTime(15, 35, 0);

//         return $now->between($start, $end);
//     }
// }

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SymbolLtps;
use App\Traits\AngelApiAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class UpdateOurPortfolioLtp extends Command
{
    use AngelApiAuth;

    protected $signature = 'our-portfolio:update-ltp
                            {--force : Force run outside market hours}
                            {--chunk=25 : Number of symbols to process per chunk}';

    protected $description = 'Update LTP values for all required symbols (FUT, CE, PE) while keeping 7 days of data.';

    private $defaultConnection;
    private $currentDate;
    private $isFirstRunToday = false;

    public function handle()
    {
        $force = $this->option('force');
        $chunkSize = 25;

        if (!$force && !$this->isBetween915AMto1535PM()) {
            $this->info('Market is closed. Use --force to override. Exiting.');
            return 0;
        }

        try {
            $this->defaultConnection = DB::connection();
            $this->currentDate = now()->format('Y-m-d');

            $this->info("📅 Starting LTP update for date: {$this->currentDate}");
            $this->resetAndCleanupOldData();

            $symbols = $this->getUniqueSymbols();
            if ($symbols->isEmpty()) {
                $this->warn('⚠️ No symbols found for LTP update.');
                return 0;
            }

            $this->info("Found {$symbols->count()} unique FUT/CE/PE symbols.");
            $this->processSymbolsInBatches($symbols, $chunkSize);

            $this->info('✅ LTP update completed successfully.');
            return 0;

        } catch (Exception $e) {
            $this->error('❌ Error updating LTP: ' . $e->getMessage());
            Log::error('LTP Update Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        } finally {
            if ($this->defaultConnection) {
                try {
                    $this->defaultConnection->disconnect();
                } catch (Exception $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * Reset today's LTP and delete older than 7 days.
     */
    private function resetAndCleanupOldData(): void
    {
        $today = $this->currentDate;
        $cutoff = now()->subDays(7)->format('Y-m-d');

        // Delete data older than 7 days
        $deleted = SymbolLtps::whereDate('trade_date', '<', $cutoff)->delete();
        $this->info("🧹 Deleted {$deleted} records older than {$cutoff} (7-day retention).");

        $todayRecordsExist = SymbolLtps::whereDate('trade_date', $today)->exists();

        if (!$todayRecordsExist) {
            $this->isFirstRunToday = true;
            $this->info("🔄 First run of the day — resetting today's highs.");
            SymbolLtps::whereDate('trade_date', $today)->update([
                'highest_ltp' => 0,
                'highest_time' => null
            ]);
        } else {
            $this->info("⏩ Continuing LTP updates for {$today}");
        }
    }

    /**
     * Get unique FUT, CE, PE symbols from historical_options_data (latest date).
     */
    private function getUniqueSymbols()
    {
        $latestDate = DB::table('historical_options_data')->max('date');

        if (!$latestDate) {
            $this->error('No data found in historical_options_data table.');
            return collect();
        }

        $future = DB::table('historical_options_data')
            ->select('future_symbol as symbol_name', 'future_token as symbol_token')
            ->whereNotNull('future_token')
            ->where('future_token', '!=', '')
            ->whereDate('date', $latestDate);

        $ce = DB::table('historical_options_data')
            ->select('ce_symbol as symbol_name', 'ce_token as symbol_token')
            ->whereNotNull('ce_token')
            ->where('ce_token', '!=', '')
            ->whereDate('date', $latestDate);

        $pe = DB::table('historical_options_data')
            ->select('pe_symbol as symbol_name', 'pe_token as symbol_token')
            ->whereNotNull('pe_token')
            ->where('pe_token', '!=', '')
            ->whereDate('date', $latestDate);

        $allSymbols = $future->union($ce)->union($pe)->distinct()->get();

        $this->info("📊 Latest source data date: {$latestDate}");
        return $allSymbols;
    }

    /**
     * Chunked symbol processing
     */
    private function processSymbolsInBatches($symbols, int $chunkSize): void
    {
        $total = $symbols->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $chunkIndex = 0;
        $totalChunks = ceil($total / $chunkSize);

        $symbols->chunk($chunkSize)->each(function ($chunk) use (&$processed, &$chunkIndex, $totalChunks, $bar) {
            $chunkIndex++;
            $this->info("\n🚀 Processing chunk {$chunkIndex}/{$totalChunks} ({$chunk->count()} symbols)");
            $symbolTokens = $chunk->pluck('symbol_token')->toArray();

            $ltpData = $this->getBulkLtpData($symbolTokens);
            if (empty($ltpData)) {
                $this->warn("⚠️ Failed to fetch LTP data for chunk {$chunkIndex}");
                $bar->advance($chunk->count());
                return;
            }

            try {
                $updatedCount = $this->updateLtpDataInTransaction($chunk, $ltpData);
                $processed += $updatedCount;
                $this->info("✅ Updated {$updatedCount} rows for chunk {$chunkIndex}");
            } catch (Exception $e) {
                $this->error("Chunk {$chunkIndex} error: " . $e->getMessage());
                Log::error('LTP Chunk Error', ['chunk' => $chunkIndex, 'error' => $e->getMessage()]);
            }

            $bar->advance($chunk->count());
            sleep(1);
        });

        $bar->finish();
        $this->info("\n📈 Total processed: {$processed}");
    }

    /**
     * Insert/update symbols for today.
     */
    private function updateLtpDataInTransaction($chunk, array $ltpData): int
    {
        $now = now();
        $today = $now->format('Y-m-d');
        $time = $now->format('H:i:s');

        $ltpByToken = collect($ltpData)->keyBy('symbolToken');

        $tokens = $chunk->pluck('symbol_token')->filter(fn($t) => isset($ltpByToken[$t]) && $ltpByToken[$t]['ltp'] > 0)->values()->toArray();

        if (empty($tokens)) return 0;

        $existing = SymbolLtps::whereIn('symbol_token', $tokens)
            ->whereDate('trade_date', $today)
            ->get()
            ->keyBy('symbol_token');

        $rows = [];
        foreach ($tokens as $token) {
            $data = $ltpByToken[$token];
            $symbol = $chunk->firstWhere('symbol_token', $token);
            $ltp = $data['ltp'];

            $current = $existing->get($token);
            $high = $ltp;
            $highTime = $time;

            if ($current && $current->highest_ltp > $ltp) {
                $high = $current->highest_ltp;
                $highTime = $current->highest_time;
            }

            $rows[] = [
                'symbol_token'   => $token,
                'symbol_name'    => $symbol->symbol_name,
                'ltp'            => $ltp,
                'highest_ltp'    => $high,
                'highest_time'   => $highTime,
                'trade_date'     => $today,
                'last_updated_at'=> $now,
                'updated_at'     => $now,
                'created_at'     => $now,
            ];
        }

        if (empty($rows)) return 0;

        return DB::transaction(function () use ($rows) {
            SymbolLtps::upsert(
                $rows,
                ['symbol_token', 'trade_date'],
                ['symbol_name', 'ltp', 'highest_ltp', 'highest_time', 'last_updated_at', 'updated_at']
            );
            return count($rows);
        });
    }

    /**
     * Only allow between 9:15 - 15:35 unless --force used
     */
    private function isBetween915AMto1535PM(): bool
    {
        $now = now();
        $start = now()->setTime(9, 15);
        $end = now()->setTime(15, 35);
        return $now->between($start, $end);
    }
}