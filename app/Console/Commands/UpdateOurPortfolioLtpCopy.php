<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SymbolLtps;
use App\Traits\AngelApiAuth;
use Illuminate\Support\Facades\DB;

class UpdateOurPortfolioLtpCopy extends Command
{
    use AngelApiAuth;

    protected $signature = 'our-portfolio:update-ltp-old';
    protected $description = 'Update LTP values for all required symbols (FUT, CE, PE)';

    private $defaultConnection;

    public function handle()
    {
        if (!$this->isBetween915AMto1535PM()) {
            $this->info('Market is closed. Skipping LTP update.');
            return;
        }

        try {
            $this->defaultConnection = DB::connection();

            // STEP 1: Fetch unique symbols (FUT, CE, PE)
            $symbols = $this->getUniqueSymbols();

            if ($symbols->isEmpty()) {
                $this->info('No symbols found for LTP update.');
                return;
            }

            $this->info("Found {$symbols->count()} unique FUT, CE, PE symbols to update.");

            // STEP 2: Process symbols in batches
            $this->processSymbolsInBatches($symbols);

        } catch (\Exception $e) {
            $this->error('Error updating LTP: ' . $e->getMessage());
        } finally {
            if ($this->defaultConnection) {
                $this->defaultConnection->disconnect();
            }
        }
    }

    /**
     * Fetch FUT, CE, PE symbols & tokens from historical_options_data.
     */
    private function getUniqueSymbols()
    {
        $latestDate = DB::table('historical_options_data')->max('date');

        $symbols = DB::table('historical_options_data')
            ->select(
                'future_symbol as symbol_name',
                'future_token as symbol_token'
            )
            ->whereNotNull('future_token')
            ->whereDate('date', $latestDate);

        $ceSymbols = DB::table('historical_options_data')
            ->select(
                'ce_symbol as symbol_name',
                'ce_token as symbol_token'
            )
            ->whereNotNull('ce_token')
            ->whereDate('date', $latestDate);

        $peSymbols = DB::table('historical_options_data')
            ->select(
                'pe_symbol as symbol_name',
                'pe_token as symbol_token'
            )
            ->whereNotNull('pe_token')
            ->whereDate('date', $latestDate);

        // Merge FUT + CE + PE into one collection & remove duplicates
        return $symbols->union($ceSymbols)->union($peSymbols)->distinct()->get();
    }

    /**
     * Process symbols in optimized batches.
     */
    private function processSymbolsInBatches($symbols)
    {
        $chunkSize = 25;
        $processed = 0;
        $totalChunks = ceil($symbols->count() / $chunkSize);
        $currentChunk = 0;

        $symbols->chunk($chunkSize)->each(function ($chunk) use (&$processed, &$currentChunk, $totalChunks) {
            $currentChunk++;
            $symbolTokens = $chunk->pluck('symbol_token')->toArray();

            $this->info("Processing chunk {$currentChunk}/{$totalChunks}: " . count($symbolTokens) . " symbols");

            // Get LTP data from Angel API
            $ltpData = $this->getBulkLtpData($symbolTokens);

            if (empty($ltpData)) {
                $this->error("Failed to fetch LTP data for chunk {$currentChunk}");
                return;
            }

            $processed += $this->updateLtpDataInTransaction($chunk, $ltpData);

            // Sleep between chunks to avoid hitting API limits
            if ($currentChunk < $totalChunks) {
                $this->info("Processed chunk {$currentChunk}, sleeping 2s...");
                sleep(2);
            }
        });

        $this->info("LTP update completed successfully. Updated {$processed} symbols.");
    }

    /**
     * Update symbol_ltps table.
     */
    private function updateLtpDataInTransaction($chunk, $ltpData)
    {
        $processed = 0;

        $this->defaultConnection->transaction(function () use ($chunk, $ltpData, &$processed) {
            $symbolTokens = collect($ltpData)->pluck('symbolToken')->toArray();
            $existingRecords = SymbolLtps::whereIn('symbol_token', $symbolTokens)
                ->get()
                ->keyBy('symbol_token');

            $now = now();
            $currentTime = $now->format('H:i:s');

            foreach ($ltpData as $item) {
                if (empty($item['ltp'])) {
                    continue;
                }

                $symbol = $chunk->firstWhere('symbol_token', $item['symbolToken']);
                if (!$symbol) {
                    continue;
                }

                $existingRecord = $existingRecords->get($item['symbolToken']);

                $updateData = [
                    'symbol_name' => $symbol->symbol_name,
                    'ltp' => $item['ltp'],
                    'last_updated_at' => $now
                ];

                if ($existingRecord) {
                    if ($item['ltp'] > ($existingRecord->highest_ltp ?: 0)) {
                        $updateData['highest_ltp'] = $item['ltp'];
                        $updateData['highest_time'] = $currentTime;
                    } else {
                        $updateData['highest_ltp'] = $existingRecord->highest_ltp;
                        $updateData['highest_time'] = $existingRecord->highest_time;
                    }
                } else {
                    $updateData['highest_ltp'] = $item['ltp'];
                    $updateData['highest_time'] = $currentTime;
                }

                SymbolLtps::updateOrCreate(
                    ['symbol_token' => $item['symbolToken']],
                    $updateData
                );

                $processed++;
            }
        });

        return $processed;
    }

    /**
     * Check if current time is between market hours.
     */
    private function isBetween915AMto1535PM()
    {
        $now = now();
        $start = now()->setTime(9, 15, 0);
        $end = now()->setTime(15, 35, 0);

        return $now->between($start, $end);
    }
}