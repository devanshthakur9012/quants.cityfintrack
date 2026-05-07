<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FuturesMonitored;
use Illuminate\Support\Facades\DB;
use Exception;

class AddFuturesSymbolCommand extends Command
{
    protected $signature = 'futures:add-symbol 
                            {symbol : Base symbol name (e.g., NIFTY, AXISBANK)}
                            {--interval=minute : Interval: minute, 5minute, or minute,5minute}
                            {--expiry= : Expiry date (Y-m-d) - auto-detects if not provided}';

    protected $description = 'Add a new futures symbol to monitoring';

    public function handle()
    {
        $baseSymbol = strtoupper($this->argument('symbol'));
        $interval = $this->option('interval');
        $expiryDate = $this->option('expiry');

        $this->info("🔍 Searching for {$baseSymbol} in instruments...\n");

        try {
            // Search for the symbol in zerodha_instruments
            $instruments = DB::table('zerodha_instruments')
                ->where('instrument_type', 'FUT')
                ->where('exchange', 'NFO')
                ->where('trading_symbol', 'LIKE', $baseSymbol . '%')
                ->orderBy('expiry', 'desc')
                ->get();

            if ($instruments->isEmpty()) {
                $this->error("No futures found for symbol: {$baseSymbol}");
                return 1;
            }

            // If expiry specified, filter by it
            if ($expiryDate) {
                $instruments = $instruments->filter(function($inst) use ($expiryDate) {
                    return $inst->expiry == $expiryDate;
                });
            }

            // Show available contracts
            $this->info("Found " . $instruments->count() . " contract(s):\n");
            $choices = [];
            foreach ($instruments as $index => $inst) {
                $choices[$index] = "{$inst->trading_symbol} (Expiry: {$inst->expiry}, Lot: {$inst->lot_size})";
                $this->info("  [{$index}] {$choices[$index]}");
            }

            // Let user choose
            $selected = $this->ask("\nSelect contract number (or 'all' for all)", '0');

            if ($selected === 'all') {
                foreach ($instruments as $inst) {
                    $this->addSymbol($inst, $interval);
                }
            } else {
                $instrument = $instruments->get($selected);
                if (!$instrument) {
                    $this->error("Invalid selection!");
                    return 1;
                }
                $this->addSymbol($instrument, $interval);
            }

            $this->info("\n✅ Symbol(s) added successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    private function addSymbol($instrument, $interval)
    {
        // Check if already exists
        $existing = FuturesMonitored::where('trading_symbol', $instrument->trading_symbol)->first();

        if ($existing) {
            $this->warn("  ⚠ {$instrument->trading_symbol} already exists!");
            
            if ($this->confirm("Update interval to '{$interval}'?", true)) {
                $existing->update([
                    'intervals' => $interval,
                    'is_active' => true
                ]);
                $this->info("  ✓ Updated {$instrument->trading_symbol}");
            }
            return;
        }

        // Add new symbol
        FuturesMonitored::create([
            'trading_symbol' => $instrument->trading_symbol,
            'exchange' => $instrument->exchange,
            'instrument_token' => $instrument->instrument_token,
            'intervals' => $interval,
            'is_active' => true,
            'expiry_date' => $instrument->expiry,
            'lot_size' => $instrument->lot_size
        ]);

        $this->info("  ✓ Added {$instrument->trading_symbol} with {$interval} interval");
    }
}