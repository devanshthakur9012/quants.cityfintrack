<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\SymbolList;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DistributeSymbols extends Command
{
    protected $signature = 'symbols:distribute-symbol {--reset} {--dry-run} {--expiry=0 : Expiry offset (0=current/closest, 1=next month, 2=second next, etc.)}';
    protected $description = 'Distribute symbols from symbol_lists to Zerodha brokers with configurable FUT expiry';
    
    protected string $defaultInterval = '5minute';

    // protected $specificAssignments = [
    //     'ZZL808' => [
    //         'HINDALCO',
    //         'VEDL',
    //         'DRREDDY',
    //         'LICHSGFIN',
    //         'TATACONSUM',
    //         'ABCCAPITAL',
    //         'SBIN',
    //         'VBL',
    //         'BAJFINANCE',
    //         'TCS',
    //         'COFORGE',
    //         'EICHERMOT',
    //         'HEROMOTOCO',
    //         'AMBUJACEM',
    //         'FORTIS',
    //         'UPL',
    //         'M&M',
    //         'NATIONALUM',
    //         'BPCL',
    //         'ETERNAL',
    //     ]
    // ];

    protected $specificAssignments = [
        'ZZL808' => [
            'ADANIPORTS',
            'AMBUJACEM',
            'ASIANPAINT',
            'AUROPHARMA',
            'AXISBANK',
            'BAJAJFINSV',
            'BAJFINANCE',
            'BANKNIFTY',
            'BDL',
            'BHARATFORG',
            'BHARTIARTL',
            'BHEL',
            'BPCL',
            'BSE',
            'CDSL',
            'COFORGE',
            'DELHIVERY',
            'DRREDDY',
            'EICHERMOT',
            'ETERNAL',
            'FORTIS',
            'HAL',
            'HAVELLS',
            'HEROMOTOCO',
            'HINDALCO',
            'ICICIBANK',
            'INDUSINDBK',
            'INFY',
            'JSWSTEEL',
            'LAURUSLABS',
            'LICHSGFIN',
            'LTF',
            'M&M',
            'NATIONALUM',
            'NIFTY',
            'PAYTM',
            'PGEL',
            'POLICYBZR',
            'SBIN',
            'SHRIRAMFIN',
            'SRF',
            'TATACONSUM',
            'TATAELXSI',
            'TATATECH',
            'TCS',
            'TITAN',
            'TMPV',
            'UPL',
            'VBL',
            'VEDL',
        ]
    ];

    public function handle()
    {
        $expiryOffset = (int) $this->option('expiry');
        $expiryLabel = $expiryOffset === 0 ? 'CURRENT/CLOSEST' : 'NEXT +' . $expiryOffset;
        
        $this->info('🚀 Starting Symbol Distribution (Specific Symbols Only)...');
        $this->info("📅 Expiry Selection: {$expiryLabel}");
        
        $brokers = BrokerApi::where('client_type', 'Zerodha')->where('is_token_valid', true)->get();
        if ($brokers->isEmpty()) {
            $this->error('❌ No active Zerodha brokers!');
            return 1;
        }

        if ($this->option('reset') && $this->confirm('Delete existing symbols?', false)) {
            SymbolMonitored::whereIn('broker_api_id', $brokers->pluck('id'))->delete();
        }

        $allSymbols = SymbolList::all();
        $specificSymbols = [];
        $skippedCount = 0;

        // ONLY collect symbols that are in the specificAssignments list
        foreach ($allSymbols as $rec) {
            $symbol = strtoupper(trim($rec->symbol));
            $underlying = strtoupper(trim($rec->underlying));
            $assigned = false;
            
            foreach ($this->specificAssignments as $username => $list) {
                if (in_array($symbol, $list) || in_array($underlying, $list)) {
                    $specificSymbols[$username][] = ['symbol' => $symbol, 'underlying' => $underlying];
                    $assigned = true;
                    break;
                }
            }
            
            // Skip all symbols not in the specific assignments
            if (!$assigned) {
                $skippedCount++;
            }
        }

        $this->info("\n📋 Processing only {$this->getTotalSpecificCount($specificSymbols)} specific symbols");
        $this->info("⏭️  Skipping {$skippedCount} general symbols\n");

        $specificResults = $this->processSpecific($brokers, $specificSymbols, $expiryOffset);
        
        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Final Summary:");
        $this->info("   ✓ Successfully Processed: {$specificResults['success']}");
        $this->info("   ✗ Failed: {$specificResults['failed']}");
        $this->info("   ⏭️  Skipped: {$skippedCount}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
        
        return 0;
    }

    protected function getTotalSpecificCount($specificSymbols)
    {
        $total = 0;
        foreach ($specificSymbols as $symbols) {
            $total += count($symbols);
        }
        return $total;
    }

    protected function processSpecific($brokers, $specificSymbols, $expiryOffset)
    {
        $success = $failed = 0;
        
        foreach ($specificSymbols as $username => $symbols) {
            $broker = $brokers->firstWhere('account_user_name', $username);
            
            if (!$broker) {
                $this->error("❌ Broker not found: {$username}");
                $failed += count($symbols);
                continue;
            }

            $this->info("🔑 Processing broker: {$broker->client_name} ({$username})");
            $this->info("   Symbols to process: " . count($symbols) . "\n");
            
            foreach ($symbols as $data) {
                $result = $this->addSymbol($broker, $data, $expiryOffset);
                if ($result) {
                    $success++;
                } else {
                    $failed++;
                }
            }
            
            $this->newLine();
        }
        
        return ['success' => $success, 'failed' => $failed];
    }

    protected function addSymbol($broker, $data, $expiryOffset)
    {
        try {
            if (SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('underlying_name', $data['underlying'])
                ->where('interval', $this->defaultInterval)
                ->exists()) {
                $this->line("   ⏭️  Already exists: {$data['underlying']}");
                return true;
            }

            $baseSymbol = $data['symbol'];
            
            // Get all valid FUT contracts ordered by expiry
            $futQuery = ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', 'NFO')
                ->whereDate('expiry', '>=', now()->toDateString())
                ->orderBy('expiry', 'asc');

            // Fix for NIFTY and BANKNIFTY: Use exact name match to avoid NIFTYNXT, BANKNIFTYNXT etc
            if (in_array($baseSymbol, ['NIFTY', 'BANKNIFTY'])) {
                $futQuery->where('name', $baseSymbol);
            } else {
                // For other symbols, use LIKE for flexibility
                $futQuery->where(function ($q) use ($baseSymbol) {
                    $q->where('name', $baseSymbol)
                      ->orWhere('trading_symbol', 'LIKE', $baseSymbol . '%');
                });
            }

            $allFutures = $futQuery->get();

            // If no results with baseSymbol, try with underlying
            if ($allFutures->isEmpty()) {
                if (in_array($data['underlying'], ['NIFTY', 'BANKNIFTY'])) {
                    $allFutures = ZerodhaInstrument::where('name', $data['underlying'])
                        ->where('instrument_type', 'FUT')
                        ->where('exchange', 'NFO')
                        ->whereDate('expiry', '>=', now()->toDateString())
                        ->orderBy('expiry', 'asc')
                        ->get();
                } else {
                    $allFutures = ZerodhaInstrument::where('trading_symbol', 'LIKE', $data['underlying'] . '%')
                        ->where('instrument_type', 'FUT')
                        ->where('exchange', 'NFO')
                        ->whereNotNull('expiry')
                        ->whereDate('expiry', '>=', now()->toDateString())
                        ->orderBy('expiry', 'asc')
                        ->get();
                }
            }

            if ($allFutures->isEmpty()) {
                $this->warn("   ⚠️  No FUT found: {$data['underlying']}");
                return false;
            }

            // Get the future at the specified offset (0 = closest, 1 = next, etc.)
            $fut = $allFutures->skip($expiryOffset)->first();

            if (!$fut) {
                $this->warn("   ⚠️  No FUT found at offset {$expiryOffset} for: {$data['underlying']} (Available: {$allFutures->count()})");
                return false;
            }

            if ($this->option('dry-run')) {
                $this->line("   [DRY-RUN] Would add: {$fut->trading_symbol} (Expiry: {$fut->expiry})");
                return true;
            }

            SymbolMonitored::create([
                'broker_api_id' => $broker->id,
                'symbol' => $data['symbol'],
                'underlying_name' => $data['underlying'],
                'exchange' => $fut->exchange,
                'instrument_type' => $fut->instrument_type,
                'interval'        => $this->defaultInterval,
                'trading_symbol' => $fut->trading_symbol,
                'instrument_token' => $fut->instrument_token,
                'is_active' => true,
                'last_synced_at' => now()
            ]);

            $this->line("   ✓ Added: {$fut->trading_symbol} (Expiry: {$fut->expiry})");
            return true;

        } catch (\Exception $e) {
            $this->error("   ✗ Error with {$data['underlying']}: {$e->getMessage()}");
            Log::error("Distribution error: {$e->getMessage()}");
            return false;
        }
    }
}