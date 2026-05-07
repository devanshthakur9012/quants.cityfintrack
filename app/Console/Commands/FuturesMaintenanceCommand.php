<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FuturesMonitored;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class FuturesMaintenanceCommand extends Command
{
    protected $signature = 'futures:maintenance 
                            {--clean-days=30 : Delete data older than X days}
                            {--auto-rollover : Automatically add next month contracts}
                            {--rollover-days=7 : Days before expiry to add next contract}';

    protected $description = 'Daily maintenance: update expiry status, rollover contracts, clean old data';

    public function handle()
    {
        $this->info("🔧 Running Futures Maintenance...\n");

        try {
            // 1. Update expiry status
            $this->updateExpiryStatus();

            // 2. Auto-rollover to next month contracts (if enabled)
            if ($this->option('auto-rollover')) {
                $this->autoRolloverContracts();
            }

            // 3. Clean old data
            $this->cleanOldData();

            // 4. Show summary
            $this->showSummary();

            $this->info("\n✅ Maintenance completed successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Maintenance Error: " . $e->getMessage());
            Log::error('Futures Maintenance Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function updateExpiryStatus()
    {
        $this->info("1. Checking expiry dates...");

        $today = Carbon::today('Asia/Kolkata');

        // Deactivate expired contracts
        $expiredContracts = FuturesMonitored::where('is_active', true)
            ->where('expiry_date', '<', $today)
            ->get();

        $expiredCount = 0;
        foreach ($expiredContracts as $contract) {
            $contract->update(['is_active' => false]);
            $this->warn("   ⚠ Deactivated: {$contract->trading_symbol} (Expired: {$contract->expiry_date})");
            Log::info("Deactivated expired contract: {$contract->trading_symbol}");
            $expiredCount++;
        }

        if ($expiredCount > 0) {
            $this->info("   ✓ Deactivated {$expiredCount} expired contract(s)");
        } else {
            $this->info("   ✓ No expired contracts found");
        }

        // Show upcoming expiries (next 7 days)
        $upcomingExpiries = FuturesMonitored::where('is_active', true)
            ->whereBetween('expiry_date', [$today, $today->copy()->addDays(7)])
            ->get();

        if ($upcomingExpiries->isNotEmpty()) {
            $this->warn("\n   ⚠ Upcoming Expiries (Next 7 Days):");
            foreach ($upcomingExpiries as $future) {
                $daysLeft = Carbon::parse($future->expiry_date)->diffInDays(Carbon::today());
                $this->warn("   - {$future->trading_symbol}: {$future->expiry_date} ({$daysLeft} days left)");
            }
        }
    }

    private function autoRolloverContracts()
    {
        $this->info("\n2. Auto-rollover to next contracts...");

        $rolloverDays = $this->option('rollover-days');
        $today = Carbon::today('Asia/Kolkata');
        $rolloverDate = $today->copy()->addDays($rolloverDays);

        // Find contracts expiring soon
        $expiringContracts = FuturesMonitored::where('is_active', true)
            ->where('expiry_date', '<=', $rolloverDate)
            ->get();

        if ($expiringContracts->isEmpty()) {
            $this->info("   ✓ No contracts need rollover");
            return;
        }

        $this->info("   Found " . $expiringContracts->count() . " contract(s) expiring within {$rolloverDays} days");

        $addedCount = 0;
        $skippedCount = 0;

        foreach ($expiringContracts as $oldContract) {
            $this->info("\n   Processing: {$oldContract->trading_symbol}");

            // Extract base symbol (e.g., NIFTY from NIFTY26JANFUT)
            $baseSymbol = $this->extractBaseSymbol($oldContract->trading_symbol);
            
            if (!$baseSymbol) {
                $this->warn("     ⚠ Could not extract base symbol, skipping");
                $skippedCount++;
                continue;
            }

            // Find next available contract
            $nextContract = $this->findNextContract($baseSymbol, $oldContract->expiry_date);

            if (!$nextContract) {
                $this->warn("     ⚠ No next contract found for {$baseSymbol}");
                $skippedCount++;
                continue;
            }

            // Check if already added
            $existing = FuturesMonitored::where('trading_symbol', $nextContract->trading_symbol)->first();

            if ($existing) {
                $this->info("     ℹ Already exists: {$nextContract->trading_symbol}");
                
                // Make sure it's active with same intervals
                if (!$existing->is_active || $existing->intervals != $oldContract->intervals) {
                    $existing->update([
                        'is_active' => true,
                        'intervals' => $oldContract->intervals
                    ]);
                    $this->info("     ✓ Updated: {$nextContract->trading_symbol}");
                }
                $skippedCount++;
                continue;
            }

            // Add new contract with same settings as old one
            // FuturesMonitored::create([
            //     'trading_symbol' => $nextContract->trading_symbol,
            //     'exchange' => $nextContract->exchange,
            //     'instrument_token' => $nextContract->instrument_token,
            //     'intervals' => $oldContract->intervals, // Copy interval settings
            //     'is_active' => true,
            //     'expiry_date' => $nextContract->expiry,
            //     'lot_size' => $nextContract->lot_size
            // ]);

            FuturesMonitored::create([
                'trading_symbol' => $nextContract->trading_symbol,
                'exchange' => $nextContract->exchange,
                'instrument_token' => $nextContract->instrument_token,
                'intervals' => $oldContract->intervals,
                'is_active' => true,
                'expiry_date' => $nextContract->expiry,
                'lot_size' => $nextContract->lot_size
            ]);

            $this->backfillNewSymbol($nextContract->trading_symbol, $nextContract->instrument_token, $oldContract->intervals);

            $this->info("     ✓ Added: {$nextContract->trading_symbol} (Expiry: {$nextContract->expiry})");
            $this->info("       Intervals: {$oldContract->intervals}");
            Log::info("Auto-rollover: Added {$nextContract->trading_symbol} to replace {$oldContract->trading_symbol}");
            
            $addedCount++;
        }

        $this->info("\n   ✓ Rollover complete: {$addedCount} added, {$skippedCount} skipped");
    }

    private function backfillNewSymbol($tradingSymbol, $instrumentToken, $intervals)
    {
        $this->info("     🔄 Backfilling 3 days of history for {$tradingSymbol}...");
        
        try {
            $zerodha = new \App\Helpers\ZerodhaHelper();
            $fromDate = Carbon::now('Asia/Kolkata')->subDays(3)->format('Y-m-d 09:15:00');
            $toDate = Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s');
            
            // Determine which intervals to fetch
            $intervalsArray = explode(',', $intervals);
            
            foreach ($intervalsArray as $interval) {
                $interval = trim($interval);
                
                $this->info("       - Fetching {$interval} data...");
                
                $data = $zerodha->getHistoricalDataByToken(
                    $instrumentToken,
                    $interval,
                    $fromDate,
                    $toDate
                );
                
                if (empty($data)) {
                    $this->warn("       ⚠ No historical data available");
                    continue;
                }
                
                // Store data (reuse existing storeHistoricalData method)
                $future = FuturesMonitored::where('trading_symbol', $tradingSymbol)->first();
                $insertedCount = $this->storeHistoricalData($future, $data, $interval);
                
                $this->info("       ✓ Stored {$insertedCount} historical records");
                
                // Calculate indicators
                if ($insertedCount > 0) {
                    $config = IndicatorConfig::getForSymbol($tradingSymbol);
                    $this->calculateSupertrendForInterval($future, $interval, $config);
                    $this->info("       ✓ Indicators calculated");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("       ✗ Backfill error: " . $e->getMessage());
            Log::error("Backfill error for {$tradingSymbol}: " . $e->getMessage());
        }
    }

    private function extractBaseSymbol($tradingSymbol)
    {        
        // Remove FUT suffix
        $symbol = str_replace('FUT', '', $tradingSymbol);
        
        // Remove date pattern (e.g., 26JAN, 26FEB, etc.)
        $symbol = preg_replace('/\d{2}[A-Z]{3}/', '', $symbol);
        
        return $symbol ?: null;
    }

    private function findNextContract($baseSymbol, $currentExpiry)
    {
        // Search for next month's contract
        $currentExpiryDate = Carbon::parse($currentExpiry);
        $nextMonth = $currentExpiryDate->copy()->addMonth();

        // Search in zerodha_instruments for next contract
        $nextContract = DB::table('zerodha_instruments')
            ->where('instrument_type', 'FUT')
            ->where('exchange', 'NFO')
            ->where('trading_symbol', 'LIKE', $baseSymbol . '%')
            ->where('expiry', '>', $currentExpiry)
            ->orderBy('expiry', 'asc')
            ->first();

        return $nextContract;
    }

    private function cleanOldData()
    {
        $days = $this->option('clean-days');
        $this->info("\n3. Cleaning data older than {$days} days...");

        $cutoffDate = Carbon::today('Asia/Kolkata')->subDays($days);

        $deletedCount = DB::table('futures_data')
            ->where('timestamp', '<', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            $this->info("   ✓ Deleted {$deletedCount} old record(s)");
            Log::info("Cleaned {$deletedCount} old futures data records");
        } else {
            $this->info("   ✓ No old data to clean");
        }
    }

    private function showSummary()
    {
        $this->info("\n4. Current Status:");

        // Active contracts
        $activeContracts = FuturesMonitored::where('is_active', true)->count();
        $inactiveContracts = FuturesMonitored::where('is_active', false)->count();
        $this->info("   - Active contracts: {$activeContracts}");
        $this->info("   - Inactive contracts: {$inactiveContracts}");

        // By interval
        $by1Min = FuturesMonitored::where('is_active', true)
            ->where(function($q) {
                $q->where('intervals', 'minute')
                  ->orWhere('intervals', 'LIKE', '%minute%');
            })
            ->count();

        $by5Min = FuturesMonitored::where('is_active', true)
            ->where(function($q) {
                $q->where('intervals', '5minute')
                  ->orWhere('intervals', 'LIKE', '%5minute%');
            })
            ->count();

        $this->info("   - 1-minute tracking: {$by1Min}");
        $this->info("   - 5-minute tracking: {$by5Min}");

        // Data count
        $totalRecords = DB::table('futures_data')->count();
        $this->info("   - Total data records: " . number_format($totalRecords));

        // Today's data
        $todayRecords = DB::table('futures_data')
            ->whereDate('timestamp', Carbon::today('Asia/Kolkata'))
            ->count();
        $this->info("   - Today's data records: " . number_format($todayRecords));

        // Last fetch times
        $lastFetch1Min = FuturesMonitored::where('is_active', true)
            ->where('intervals', 'LIKE', '%minute%')
            ->whereNotNull('last_fetched_at')
            ->orderBy('last_fetched_at', 'desc')
            ->value('last_fetched_at');

        $lastFetch5Min = FuturesMonitored::where('is_active', true)
            ->where('intervals', 'LIKE', '%5minute%')
            ->whereNotNull('last_fetched_at')
            ->orderBy('last_fetched_at', 'desc')
            ->value('last_fetched_at');

        if ($lastFetch1Min) {
            $this->info("   - Last 1-min fetch: " . Carbon::parse($lastFetch1Min)->diffForHumans());
        }

        if ($lastFetch5Min) {
            $this->info("   - Last 5-min fetch: " . Carbon::parse($lastFetch5Min)->diffForHumans());
        }

        // Show active symbols grouped by interval
        $this->info("\n   Active Symbols:");
        
        $oneMinSymbols = FuturesMonitored::where('is_active', true)
            ->where(function($q) {
                $q->where('intervals', 'minute')
                  ->orWhere('intervals', 'LIKE', 'minute,%');
            })
            ->pluck('trading_symbol')
            ->take(5);
        
        if ($oneMinSymbols->isNotEmpty()) {
            $this->info("   [1-min] " . $oneMinSymbols->implode(', ') . ($by1Min > 5 ? " ... and " . ($by1Min - 5) . " more" : ""));
        }

        $fiveMinSymbols = FuturesMonitored::where('is_active', true)
            ->where(function($q) {
                $q->where('intervals', '5minute')
                  ->orWhere('intervals', 'LIKE', '%5minute%');
            })
            ->pluck('trading_symbol')
            ->take(5);
        
        if ($fiveMinSymbols->isNotEmpty()) {
            $this->info("   [5-min] " . $fiveMinSymbols->implode(', ') . ($by5Min > 5 ? " ... and " . ($by5Min - 5) . " more" : ""));
        }
    }
}