<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FetchDailyPositions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'positions:fetch-daily 
                            {--broker_id= : Specific broker ID to fetch}
                            {--all : Fetch for all active brokers}
                            {--historical : Set purchase date to Feb 13, 2026 for current positions}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch daily positions, close old ones, and store new ones. Run at 3:15 PM daily.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting daily position fetch at ' . now()->format('H:i:s'));
        
        try {
            $brokers = $this->getBrokers();
            
            if ($brokers->isEmpty()) {
                $this->warn('⚠️  No active brokers found!');
                return Command::FAILURE;
            }

            $stats = [
                'total_fetched' => 0,
                'new_positions' => 0,
                'closed_positions' => 0,
                'success_brokers' => 0,
                'failed_brokers' => 0,
            ];

            foreach ($brokers as $broker) {
                $this->info("\n📊 Processing broker: {$broker->client_name} ({$broker->account_user_name})");
                
                try {
                    $brokerStats = $this->processBroker($broker);
                    
                    $stats['total_fetched'] += $brokerStats['fetched'];
                    $stats['new_positions'] += $brokerStats['new'];
                    $stats['closed_positions'] += $brokerStats['closed'];
                    $stats['success_brokers']++;
                    
                } catch (\Exception $e) {
                    $stats['failed_brokers']++;
                    $this->error("❌ Error for {$broker->client_name}: " . $e->getMessage());
                    Log::error("Fetch positions error for broker {$broker->id}: " . $e->getMessage());
                }
                
                sleep(1); // Rate limiting
            }

            $this->displaySummary($stats);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('FetchDailyPositions command error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Get brokers to fetch positions for
     */
    private function getBrokers()
    {
        if ($this->option('broker_id')) {
            return BrokerApi::where('id', $this->option('broker_id'))
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->get();
        }

        return BrokerApi::where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();
    }

    /**
     * Process a single broker
     */
    private function processBroker($broker)
    {
        $stats = [
            'fetched' => 0,
            'new' => 0,
            'closed' => 0,
        ];

        // Step 1: Fetch current positions from Zerodha
        $currentPositions = $this->fetchCurrentPositions($broker);
        $stats['fetched'] = count($currentPositions);
        
        $this->info("  📥 Fetched {$stats['fetched']} positions from Zerodha");

        if (empty($currentPositions)) {
            // No positions found - close all old positions
            $stats['closed'] = $this->closeAllOldPositions($broker);
            $this->info("  🔒 Closed {$stats['closed']} old positions (no current positions)");
            return $stats;
        }

        // Step 2: Get current position symbols
        $currentSymbols = array_map(function($pos) {
            return $pos['tradingsymbol'] . '|' . $pos['exchange'] . '|' . $pos['product'];
        }, $currentPositions);

        // Step 3: Close positions that are NOT in current positions (squared off)
        $stats['closed'] = $this->closeOldPositions($broker, $currentSymbols);
        $this->info("  🔒 Closed {$stats['closed']} old positions");

        // Step 4: Save/Update current positions
        $stats['new'] = $this->saveCurrentPositions($broker, $currentPositions);
        $this->info("  ✅ Saved/Updated {$stats['new']} current positions");

        return $stats;
    }

    /**
     * Fetch current positions from Zerodha API
     */
    private function fetchCurrentPositions($broker)
    {
        try {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            $positions = $kite->getPositions();
            $netPositions = $positions->net ?? [];
            
            $processedPositions = [];
            foreach ($netPositions as $position) {
                if ($position->quantity == 0) {
                    continue;
                }

                $processedPositions[] = [
                    'user_id' => $broker->user_id,
                    'broker_api_id' => $broker->id,
                    'broker_name' => $broker->client_name,
                    'tradingsymbol' => $position->tradingsymbol,
                    'exchange' => $position->exchange,
                    'instrument_token' => $position->instrument_token ?? null,
                    'product' => $position->product,
                    'quantity' => $position->quantity,
                    'overnight_quantity' => $position->overnight_quantity ?? 0,
                    'average_price' => round($position->average_price, 2),
                    'last_price' => round($position->last_price, 2),
                    'pnl' => round($position->pnl, 2),
                    'unrealised' => round($position->unrealised ?? 0, 2),
                    'realised' => round($position->realised ?? 0, 2),
                    'value' => round($position->value ?? 0, 2),
                    'buy_value' => round($position->buy_value ?? 0, 2),
                    'sell_value' => round($position->sell_value ?? 0, 2),
                    'multiplier' => $position->multiplier ?? 1,
                    'buy_quantity' => $position->buy_quantity ?? 0,
                    'sell_quantity' => $position->sell_quantity ?? 0,
                    'buy_price' => round($position->buy_price ?? 0, 2),
                    'sell_price' => round($position->sell_price ?? 0, 2),
                    'close_price' => round($position->close_price ?? 0, 2),
                    'position_type' => $position->quantity > 0 ? 'LONG' : 'SHORT',
                ];
            }
            
            return $processedPositions;
            
        } catch (\Exception $e) {
            Log::error("Error fetching positions for broker {$broker->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Close positions that are no longer in current positions
     */
    private function closeOldPositions($broker, $currentSymbols)
    {
        try {
            // Find all open positions for this broker
            $oldPositions = PortfolioPosition::where('user_id', $broker->user_id)
                ->where('broker_api_id', $broker->id)
                ->where('position_status', 'open')
                ->get();

            $closedCount = 0;

            foreach ($oldPositions as $oldPos) {
                $positionKey = $oldPos->tradingsymbol . '|' . $oldPos->exchange . '|' . $oldPos->product;
                
                // If this position is NOT in current positions, it's been squared off
                if (!in_array($positionKey, $currentSymbols)) {
                    $oldPos->update([
                        'position_status' => 'closed',
                        'square_off_status' => 'executed'
                    ]);
                    $closedCount++;
                    
                    $this->line("    🔒 Closed: {$oldPos->tradingsymbol}");
                }
            }

            return $closedCount;
            
        } catch (\Exception $e) {
            Log::error("Error closing old positions: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Close ALL old positions (when no current positions exist)
     */
    private function closeAllOldPositions($broker)
    {
        try {
            $closedCount = PortfolioPosition::where('user_id', $broker->user_id)
                ->where('broker_api_id', $broker->id)
                ->where('position_status', 'open')
                ->update([
                    'position_status' => 'closed',
                    'square_off_status' => 'executed'
                ]);

            return $closedCount;
            
        } catch (\Exception $e) {
            Log::error("Error closing all positions: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Save current positions to database
     */
    private function saveCurrentPositions($broker, $positions)
    {
        $savedCount = 0;
        $today = Carbon::today();
        
        // Check if historical flag is set
        $purchaseDate = $this->option('historical') 
            ? Carbon::create(2026, 2, 13, 0, 0, 0) // Feb 13, 2026
            : now(); // Current timestamp

        foreach ($positions as $positionData) {
            try {
                // Check if position already exists as OPEN
                $existingPosition = PortfolioPosition::where('user_id', $positionData['user_id'])
                    ->where('broker_api_id', $positionData['broker_api_id'])
                    ->where('tradingsymbol', $positionData['tradingsymbol'])
                    ->where('exchange', $positionData['exchange'])
                    ->where('product', $positionData['product'])
                    ->where('position_status', 'open')
                    ->first();

                if ($existingPosition) {
                    // Update existing OPEN position (keep original purchase_date)
                    $existingPosition->update([
                        'quantity' => $positionData['quantity'],
                        'overnight_quantity' => $positionData['overnight_quantity'],
                        'average_price' => $positionData['average_price'],
                        'last_price' => $positionData['last_price'],
                        'pnl' => $positionData['pnl'],
                        'value' => $positionData['value'],
                        'buy_sell' => $positionData['position_type'],
                        'fetched_at' => now(),
                    ]);
                    
                    $this->line("    🔄 Updated: {$positionData['tradingsymbol']}");
                } else {
                    // Create NEW position
                    PortfolioPosition::create([
                        'user_id' => $positionData['user_id'],
                        'broker_api_id' => $positionData['broker_api_id'],
                        // 'broker_name' => $positionData['broker_name'],
                        'tradingsymbol' => $positionData['tradingsymbol'],
                        'exchange' => $positionData['exchange'],
                        'instrument_token' => $positionData['instrument_token'],
                        'product' => $positionData['product'],
                        'purchase_date' => $purchaseDate, // Historical or current date
                        'purchase_price' => $positionData['average_price'],
                        'quantity' => $positionData['quantity'],
                        'overnight_quantity' => $positionData['overnight_quantity'],
                        'average_price' => $positionData['average_price'],
                        'last_price' => $positionData['last_price'],
                        'pnl' => $positionData['pnl'],
                        'value' => $positionData['value'],
                        'buy_sell' => $positionData['position_type'],
                        'position_status' => 'open', // New position is OPEN
                        'fetched_at' => now(),
                    ]);
                    
                    $this->line("    ✨ New: {$positionData['tradingsymbol']}");
                }

                $savedCount++;
                
            } catch (\Exception $e) {
                Log::error("Error saving position {$positionData['tradingsymbol']}: " . $e->getMessage());
            }
        }

        return $savedCount;
    }

    /**
     * Display summary
     */
    private function displaySummary($stats)
    {
        $this->info("\n" . str_repeat('=', 60));
        $this->info("📊 EXECUTION SUMMARY");
        $this->info(str_repeat('=', 60));
        $this->info("Brokers Processed:");
        $this->info("  • Success: " . $stats['success_brokers']);
        $this->info("  • Failed: " . $stats['failed_brokers']);
        $this->info("");
        $this->info("Positions:");
        $this->info("  • Total Fetched: " . $stats['total_fetched']);
        $this->info("  • New/Updated: " . $stats['new_positions']);
        $this->info("  • Closed (Squared Off): " . $stats['closed_positions']);
        $this->info(str_repeat('=', 60));
        
        if ($this->option('historical')) {
            $this->warn("\n📅 HISTORICAL MODE: All new positions set to Feb 13, 2026");
        }
    }
}