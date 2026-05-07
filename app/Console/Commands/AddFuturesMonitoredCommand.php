<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\FuturesMonitored;
use Illuminate\Support\Facades\Log;
use Exception;

class AddFuturesMonitoredCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'futures:add-monitored {symbol} {--exchange=NFO} {--intervals=15minute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a futures contract to monitored list';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = strtoupper($this->argument('symbol'));
        $exchange = $this->option('exchange');
        $intervals = $this->option('intervals');

        try {
            $this->info("🔍 Searching for {$symbol} futures contract...");

            // Find the nearest expiry futures contract
            $futuresContract = ZerodhaInstrument::where('name', $symbol)
                ->where('exchange', $exchange)
                ->where('instrument_type', 'FUT')
                ->whereNotNull('expiry')
                ->where('expiry', '>=', now()->format('Y-m-d'))
                ->orderBy('expiry', 'asc')
                ->first();

            if (!$futuresContract) {
                $this->error("❌ No futures contract found for {$symbol} on {$exchange}");
                return 1;
            }

            $this->info("✓ Found: {$futuresContract->trading_symbol}");
            $this->info("  Expiry: {$futuresContract->expiry}");
            $this->info("  Token: {$futuresContract->instrument_token}");
            $this->info("  Lot Size: {$futuresContract->lot_size}");

            // Add to monitored
            $monitored = FuturesMonitored::updateOrCreate(
                [
                    'trading_symbol' => $futuresContract->trading_symbol,
                    'exchange' => $exchange
                ],
                [
                    'instrument_token' => $futuresContract->instrument_token,
                    'intervals' => $intervals,
                    'is_active' => true,
                    'expiry_date' => $futuresContract->expiry,
                    'lot_size' => $futuresContract->lot_size
                ]
            );

            $this->info("\n✅ {$symbol} futures added to monitored list!");
            $this->info("   Trading Symbol: {$monitored->trading_symbol}");
            $this->info("   Intervals: {$monitored->intervals}");

            Log::info("Futures contract added to monitored", [
                'symbol' => $symbol,
                'trading_symbol' => $monitored->trading_symbol,
                'token' => $monitored->instrument_token
            ]);

            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('Add Futures Monitored Error: ' . $e->getMessage());
            return 1;
        }
    }
}