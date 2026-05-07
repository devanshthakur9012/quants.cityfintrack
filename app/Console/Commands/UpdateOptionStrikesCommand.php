<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OptionStrike;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class UpdateOptionStrikesCommand extends Command
{
    protected $signature = 'options:update-strikes 
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force update even on holidays}';

    protected $description = 'Update ATM-1, ATM, ATM+1 option strikes for monitored symbols';

    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        // Holiday check (unless forced)
        if (!$this->option('force')) {
            if ($dayName == "Saturday" || $dayName == "Sunday") {
                $this->info("Skipped: Weekend ($dayName)");
                Log::info("Option strikes update skipped: Weekend");
                return 0;
            }

            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $today)
                ->exists();

            if ($isHoliday) {
                $this->info("Skipped: Market Holiday ($today)");
                Log::info("Option strikes update skipped: Holiday");
                return 0;
            }
        }

        try {
            $this->info("🚀 Starting Option Strikes Update");
            $this->info("   Time: " . Carbon::now()->format('Y-m-d H:i:s'));
            $this->newLine();

            // Get active brokers
            $brokersQuery = BrokerApi::zerodha()->validToken();

            if ($this->option('broker')) {
                $brokersQuery->where('id', $this->option('broker'));
            }

            $brokers = $brokersQuery->get();

            if ($brokers->isEmpty()) {
                $this->error('❌ No active Zerodha brokers with valid tokens found!');
                return 1;
            }

            $this->info("📋 Found " . $brokers->count() . " broker(s) with valid tokens\n");

            $totalProcessed = 0;
            $totalFailed = 0;

            foreach ($brokers as $broker) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

                $result = $this->processBroker($broker);
                $totalProcessed += $result['success'];
                $totalFailed += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ Strikes Update Completed!");
            $this->info("   Total Processed: {$totalProcessed}");
            $this->info("   Total Failed: {$totalFailed}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('Option Strikes Update Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Process strikes for a specific broker
     */
    private function processBroker(BrokerApi $broker)
    {
        $success = 0;
        $failed = 0;

        try {
            // Get FUT symbols that should have options
            $futureSymbolsQuery = SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('is_active', true)
                ->where('symbol', 'LIKE', '%FUT');

            if ($this->option('symbol')) {
                $futureSymbolsQuery->where('symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
            }

            $futureSymbols = $futureSymbolsQuery->get();

            if ($futureSymbols->isEmpty()) {
                $this->warn("   ⚠️  No future symbols found\n");
                return ['success' => 0, 'failed' => 0];
            }

            $this->info("   📊 Processing " . $futureSymbols->count() . " symbol(s)");
            $this->newLine();

            foreach ($futureSymbols as $futSymbol) {
                try {
                    $this->info("   └─ {$futSymbol->trading_symbol}");
                    $this->updateStrikesForSymbol($broker, $futSymbol);
                    $success++;
                    $this->info("      ✓ Completed\n");

                } catch (Exception $e) {
                    $failed++;
                    $this->error("      ✗ Failed: " . $e->getMessage() . "\n");
                    Log::error("Strike update failed: {$futSymbol->trading_symbol}", [
                        'broker_id' => $broker->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("   Summary: ✓ {$success} | ✗ {$failed}\n");

        } catch (Exception $e) {
            $this->error("   Broker processing failed: " . $e->getMessage() . "\n");
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Update strikes for a specific symbol
     */
    private function updateStrikesForSymbol(BrokerApi $broker, SymbolMonitored $futSymbol)
    {
        // Extract base symbol (e.g., NIFTY from NIFTY26JANFUT)
        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->symbol);
        
        $this->info("      Base Symbol: {$baseSymbol}");

        // Get current price from latest data
        $latestData = DB::table('symbol_data')
            ->where('broker_api_id', $broker->id)
            ->where('symbol', $futSymbol->symbol)
            ->orderBy('timestamp', 'DESC')
            ->first();

        if (!$latestData) {
            throw new Exception("No price data found for {$futSymbol->symbol}");
        }

        $currentPrice = $latestData->close;
        $this->info("      Current Price: {$currentPrice}");

        // Calculate ATM strike
        $strikeIntervals = [
            'NIFTY' => 50,
            'BANKNIFTY' => 100,
            'FINNIFTY' => 50,
            'MIDCPNIFTY' => 25,
        ];
        $strikeInterval = $strikeIntervals[$baseSymbol] ?? 100;

        $atmStrike = round($currentPrice / $strikeInterval) * $strikeInterval;
        $this->info("      ATM Strike: {$atmStrike}");

        // Calculate ATM-1 and ATM+1
        $strikes = [
            'ATM-1' => $atmStrike - $strikeInterval,
            'ATM' => $atmStrike,
            'ATM+1' => $atmStrike + $strikeInterval,
        ];

        // Get current week expiry
        $currentExpiry = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', now())
            ->orderBy('expiry', 'ASC')
            ->first();

        if (!$currentExpiry) {
            throw new Exception("No active expiry found for {$baseSymbol}");
        }

        $expiryDate = Carbon::parse($currentExpiry->expiry);
        $expiryCode = $expiryDate->format('dM'); // e.g., 25JAN
        
        $this->info("      Expiry: {$expiryCode} ({$expiryDate->format('Y-m-d')})");

        // Deactivate old strikes
        OptionStrike::where('broker_api_id', $broker->id)
            ->where('underlying_symbol', $baseSymbol)
            ->update(['is_active' => false]);

        // Create/Update strikes for CE and PE
        $insertedCount = 0;
        
        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $position => $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . $strike . $optionType;
                
                // Find instrument
                $instrument = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                    ->where('exchange', 'NFO')
                    ->first();

                if (!$instrument) {
                    $this->warn("      ⚠️  Instrument not found: {$tradingSymbol}");
                    continue;
                }

                OptionStrike::updateOrCreate(
                    [
                        'broker_api_id' => $broker->id,
                        'trading_symbol' => $tradingSymbol,
                    ],
                    [
                        'underlying_symbol' => $baseSymbol,
                        'option_type' => $optionType,
                        'strike_price' => $strike,
                        'strike_position' => $position,
                        'expiry' => $expiryCode,
                        'expiry_date' => $expiryDate,
                        'instrument_token' => $instrument->instrument_token,
                        'exchange' => 'NFO',
                        'lot_size' => $instrument->lot_size ?? 1,
                        'is_active' => true,
                        'last_synced_at' => now()
                    ]
                );

                $insertedCount++;
            }
        }

        $this->info("💾 Updated {$insertedCount} strikes");
    }
}