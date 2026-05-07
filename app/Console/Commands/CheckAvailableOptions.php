<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;

class CheckAvailableOptions extends Command
{
    protected $signature = 'options:check {symbol}';
    protected $description = 'Check available options for a symbol';

    public function handle()
    {
        $futureSymbol = $this->argument('symbol');
        
        // Extract base symbol
        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(\d{2})?(FUT)?$/i', '', $futureSymbol);
        
        $this->info("Future Symbol: {$futureSymbol}");
        $this->info("Base Symbol: {$baseSymbol}");
        $this->line('');
        
        // Check exact match
        $exactCount = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->count();
        
        $this->info("Exact match '{$baseSymbol}': {$exactCount} options");
        
        if ($exactCount > 0) {
            // Show sample options
            $samples = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->whereDate('expiry', '>=', now())
                ->limit(5)
                ->get(['trading_symbol', 'instrument_type', 'strike', 'expiry']);
            
            $this->line('');
            $this->info('Sample options:');
            $this->table(
                ['Trading Symbol', 'Type', 'Strike', 'Expiry'],
                $samples->map(fn($s) => [
                    $s->trading_symbol,
                    $s->instrument_type,
                    $s->strike,
                    $s->expiry
                ])
            );
            
            // Show available strikes for PE
            $peStrikes = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', 'PE')
                ->whereDate('expiry', '>=', now())
                ->distinct()
                ->pluck('strike')
                ->sort()
                ->values()
                ->toArray();
            
            $this->line('');
            $this->info('Available PE strikes: ' . implode(', ', array_slice($peStrikes, 0, 20)));
            
            // Show available strikes for CE
            $ceStrikes = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', 'CE')
                ->whereDate('expiry', '>=', now())
                ->distinct()
                ->pluck('strike')
                ->sort()
                ->values()
                ->toArray();
            
            $this->line('');
            $this->info('Available CE strikes: ' . implode(', ', array_slice($ceStrikes, 0, 20)));
            
        } else {
            // Try to find similar symbols
            $this->warn("No exact match found. Searching for similar symbols...");
            $this->line('');
            
            $similar = ZerodhaInstrument::where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('name', 'LIKE', '%' . substr($baseSymbol, 0, 6) . '%')
                ->distinct()
                ->pluck('name')
                ->toArray();
            
            if (!empty($similar)) {
                $this->info('Similar symbols found:');
                foreach ($similar as $sym) {
                    $count = ZerodhaInstrument::where('name', $sym)
                        ->where('exchange', 'NFO')
                        ->whereIn('instrument_type', ['CE', 'PE'])
                        ->count();
                    $this->line("  - {$sym} ({$count} options)");
                }
            } else {
                $this->error('No similar symbols found.');
            }
        }
        
        return 0;
    }
}