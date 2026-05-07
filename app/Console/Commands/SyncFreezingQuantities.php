<?php

namespace App\Console\Commands;

use App\Models\FreezingQuantity;
use App\Models\ZerodhaInstrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncFreezingQuantities extends Command
{
    protected $signature = 'freezing:sync
                            {--force : Force update existing records}';

    protected $description = 'Sync freezing quantities from Zerodha instruments data';

    public function handle()
    {
        $this->info('🔄 Syncing freezing quantities from Zerodha instruments...');

        try {
            // Get unique symbols with their lot sizes from NFO exchange
            $symbols = ZerodhaInstrument::select('name', DB::raw('MAX(lot_size) as max_lot_size'))
                ->where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE', 'FUT'])
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->groupBy('name')
                ->get();

            if ($symbols->isEmpty()) {
                $this->warn('⚠️  No NFO instruments found in database');
                $this->info('💡 Run: php artisan zerodha_instrument:insert first');
                return Command::FAILURE;
            }

            $this->info("📊 Found {$symbols->count()} unique symbols in NFO");

            $updated = 0;
            $created = 0;
            $skipped = 0;

            $progressBar = $this->output->createProgressBar($symbols->count());

            foreach ($symbols as $symbol) {
                // Calculate freezing quantity
                // Typical freezing limits are around 1800 lots for options
                // We'll use lot_size to estimate, but keep reasonable defaults
                $freezingQty = $this->calculateFreezingQuantity($symbol->name, $symbol->max_lot_size);

                $existing = FreezingQuantity::where('symbol', $symbol->name)->first();

                if ($existing) {
                    if ($this->option('force')) {
                        $existing->update(['freezing_quantity' => $freezingQty]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    FreezingQuantity::create([
                        'symbol' => $symbol->name,
                        'freezing_quantity' => $freezingQty,
                        'is_active' => true,
                    ]);
                    $created++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("✅ Sync completed!");
            $this->info("   • Created: {$created}");
            $this->info("   • Updated: {$updated}");
            $this->info("   • Skipped: {$skipped}");

            if ($skipped > 0 && !$this->option('force')) {
                $this->info("\n💡 Use --force to update existing records");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Freezing quantity sync error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Calculate appropriate freezing quantity for a symbol
     * Based on typical NSE limits and lot sizes
     */
    private function calculateFreezingQuantity($symbolName, $lotSize)
    {
        // Known high-value symbols with lower limits
        $lowLimitSymbols = [
            'NIFTY' => 1800,
            'BANKNIFTY' => 900,
            'FINNIFTY' => 1800,
            'MIDCPNIFTY' => 2800,
            'TCS' => 300,
            'MARUTI' => 100,
            'NESTLEIND' => 50,
            'BOSCHLTD' => 50,
            'TRENT' => 50,
            'LTIM' => 200,
            'TATAELXSI' => 125,
            'BAJFINANCE' => 125,
            'ULTRACEMCO' => 125,
        ];

        if (isset($lowLimitSymbols[$symbolName])) {
            return $lowLimitSymbols[$symbolName];
        }

        // For other symbols, use standard defaults based on typical ranges
        // Most stocks have limits between 500-3000
        if ($lotSize >= 1000) {
            return 1800; // High lot size stocks
        } elseif ($lotSize >= 500) {
            return 1200;
        } elseif ($lotSize >= 200) {
            return 900;
        } elseif ($lotSize >= 100) {
            return 500;
        } else {
            return 300; // Low lot size stocks
        }
    }
}