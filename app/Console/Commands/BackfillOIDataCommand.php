<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SymbolData;
use App\Helpers\OIAnalyzerSuper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class BackfillOIDataCommand extends Command
{
    protected $signature = 'oi:backfill 
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific symbol}
                            {--interval=15minute : Interval to process}
                            {--batch=1000 : Batch size for processing}';

    protected $description = 'Backfill OI change and signal data for historical records';

    public function handle()
    {
        $this->info("🚀 Starting OI Data Backfill");
        $this->info("   Interval: " . $this->option('interval'));
        $this->newLine();

        try {
            $query = SymbolData::where('interval', $this->option('interval'))
                ->whereNotNull('oi')
                ->orderBy('broker_api_id')
                ->orderBy('symbol')
                ->orderBy('timestamp');

            if ($this->option('broker')) {
                $query->where('broker_api_id', $this->option('broker'));
            }

            if ($this->option('symbol')) {
                $query->where('symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
            }

            $totalRecords = $query->count();
            $this->info("📊 Total records to process: {$totalRecords}");
            $this->newLine();

            if ($totalRecords === 0) {
                $this->warn('No records found to process.');
                return 0;
            }

            $batchSize = (int)$this->option('batch');
            $processedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;

            $bar = $this->output->createProgressBar($totalRecords);
            $bar->start();

            // Group by broker and symbol for efficient processing
            $groups = DB::table('symbol_data')
                ->select('broker_api_id', 'symbol')
                ->where('interval', $this->option('interval'))
                ->whereNotNull('oi')
                ->groupBy('broker_api_id', 'symbol')
                ->get();

            foreach ($groups as $group) {
                try {
                    $result = $this->processSymbolGroup(
                        $group->broker_api_id,
                        $group->symbol,
                        $this->option('interval'),
                        $bar
                    );

                    $processedCount += $result['processed'];
                    $updatedCount += $result['updated'];
                    $errorCount += $result['errors'];

                } catch (Exception $e) {
                    $this->error("\nError processing {$group->symbol}: " . $e->getMessage());
                    Log::error("OI Backfill error for {$group->symbol}", [
                        'broker_id' => $group->broker_api_id,
                        'symbol' => $group->symbol,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Backfill Completed!");
            $this->info("   Total Processed: {$processedCount}");
            $this->info("   Total Updated: {$updatedCount}");
            $this->info("   Total Errors: {$errorCount}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('OI Backfill Critical Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Process all records for a specific broker-symbol combination
     */
    private function processSymbolGroup($brokerId, $symbol, $interval, $bar)
    {
        $processed = 0;
        $updated = 0;
        $errors = 0;

        // Get all records for this symbol ordered by timestamp
        $records = SymbolData::where('broker_api_id', $brokerId)
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->whereNotNull('oi')
            ->orderBy('timestamp', 'ASC')
            ->get();

        $previousOI = null;

        foreach ($records as $record) {
            try {
                $processed++;
                $bar->advance();

                if ($previousOI === null) {
                    // First record - no previous OI to compare
                    $record->update([
                        'previous_oi' => null,
                        'oi_change' => 0,
                        'oi_change_percent' => 0,
                        'oi_signal' => 'NEUTRAL'
                    ]);
                    $updated++;
                } else {
                    // Calculate OI metrics
                    $oiAnalysis = OIAnalyzerSuper::analyzeFuturesOI(
                        (int)$record->oi,
                        $previousOI,
                        $symbol
                    );

                    $record->update([
                        'previous_oi' => $previousOI,
                        'oi_change' => $oiAnalysis['oi_change'],
                        'oi_change_percent' => $oiAnalysis['oi_change_percent'],
                        'oi_signal' => $oiAnalysis['oi_signal']
                    ]);
                    $updated++;
                }

                // Update previous OI for next iteration
                $previousOI = (int)$record->oi;

            } catch (Exception $e) {
                $errors++;
                Log::error("Error processing record ID {$record->id}: " . $e->getMessage());
            }
        }

        return [
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors
        ];
    }
}