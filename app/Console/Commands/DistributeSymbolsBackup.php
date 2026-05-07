<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\SymbolList;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DistributeSymbolsBackup extends Command
{
    protected $signature = 'symbols:distribute-symbol-backup {--reset} {--dry-run}';
    protected $description = 'Distribute symbols from symbol_lists to Zerodha brokers with latest FUT expiry';
    
    protected string $defaultInterval = '5minute';

    // ONLY these symbols will be processed - all others will be skipped
    // protected $specificAssignments = [
    //     'OQJ978' => [
    //         '360ONE','ABB','APLAPOLLO','AUBANK','ADANIENSOL','ADANIENT','ADANIGREEN','ADANIPORTS',
    //         'ABCAPITAL','ALKEM','AMBER','AMBUJACEM','ANGELONE','APOLLOHOSP','ASHOKLEY','ASIANPAINT',
    //         'ASTRAL','AUROPHARMA','DMART','AXISBANK','BSE','BAJAJ-AUTO','BAJFINANCE','BAJAJFINSV',
    //         'BAJAJHLDNG','BANDHANBNK','BANKBARODA','BANKINDIA','BDL','BEL','BHARATFORG','BHEL',
    //         'BPCL','BHARTIARTL','BIOCON','BLUESTARCO','BOSCHLTD','BRITANNIA','CGPOWER','CANBK',
    //         'CDSL','CHOLAFIN','CIPLA','COALINDIA','COFORGE','COLPAL','CAMS','CONCOR','CROMPTON',
    //         'CUMMINSIND','DLF','DABUR','DALBHARAT','DELHIVERY','DIVISLAB','DIXON','DRREDDY',
    //         'ETERNAL','EICHERMOT','EXIDEIND','NYKAA','FORTIS','GAIL','GMRAIRPORT','GLENMARK',
    //         'GODREJCP','GODREJPROP','GRASIM','HCLTECH','HDFCAMC','HDFCBANK','HDFCLIFE','HAVELLS',
    //         'HEROMOTOCO','HINDALCO','HAL','HINDPETRO','HINDUNILVR','HINDZINC','POWERINDIA',
    //         'HUDCO','ICICIBANK','ICICIGI','ICICIPRULI','IDFCFIRSTB','IIFL','ITC','INDIANB',
    //         'IEX','IOC','IRCTC','IRFC','IREDA','INDUSTOWER'
    //     ],

    //     'OQJ978' => [
    //         'INDUSINDBK','NAUKRI','INFY','INOXWIND','INDIGO','JINDALSTEL','JSWENERGY','JSWSTEEL',
    //         'JIOFIN','JUBLFOOD','KEI','KPITTECH','KALYANKJIL','KAYNES','KFINTECH','KOTAKBANK',
    //         'LTF','LICHSGFIN','LTIM','LT','LAURUSLABS','LICI','LODHA','LUPIN','M&M','MANAPPURAM',
    //         'MANKIND','MARICO','MARUTI','MFSL','MAXHEALTH','MAZDOCK','MPHASIS','MCX','MUTHOOTFIN',
    //         'NBCC','NHPC','NMDC','NTPC','NATIONALUM','NESTLEIND','NUVAMA','OBEROIRLTY','ONGC',
    //         'OIL','PAYTM','OFSS','POLICYBZR','PGEL','PIIND','PNBHOUSING','PAGEIND','PATANJALI',
    //         'PERSISTENT','PETRONET','PIDILITIND','PPLPHARMA','POLYCAB','PFC','POWERGRID',
    //         'PREMIERENE','PRESTIGE','PNB','RBLBANK','RECLTD','RVNL','RELIANCE','SBICARD',
    //         'SBILIFE','SHREECEM','SRF','SAMMAANCAP','MOTHERSON','SHRIRAMFIN','SIEMENS',
    //         'SOLARINDS','SONACOMS','SBIN','SAIL','SUNPHARMA','SUPREMEIND','SUZLON','SWIGGY',
    //         'SYNGENE','TATACONSUM','TVSMOTOR','TCS','TATAELXSI','TMPV','TATAPOWER','TATASTEEL',
    //         'TATATECH','TECHM','FEDERALBNK','INDHOTEL','PHOENIXLTD','TITAN','TORNTPHARM',
    //         'TORNTPOWER','TRENT','TIINDIA','UNOMINDA','UPL','ULTRACEMCO','UNIONBANK','UNITDSPR',
    //         'VBL','VEDL','IDEA','VOLTAS','WAAREEENER','WIPRO','YESBANK','ZYDUSLIFE'
    //     ]
    // ];

    protected $specificAssignments = [
        'OQJ978' => [
            'APLAPOLLO', 'ASHOKLEY', 'ASIANPAINT', 'AXISBANK', 'BANKINDIA', 
            'BHARATFORG', 'BSE', 'HAVELLS', 'HDFCAMC', 'INDIGO', 
            'JSWENERGY', 'KALYANKJIL', 'KOTAKBANK', 'KPITTECH', 'LUPIN', 
            'M&M', 'MCX', 'NATIONALUM', 'PGEL', 'SAIL', 
            'BPCL', 'ETERNAL', 'HINDZINC', 'IRCTC', 'LODHA', 
            'MPHASIS', 'PAYTM', 'POLICYBZR', 'SYNGENE', 'UNITDSPR', 
            '360ONE', 'BHEL', 'IIFL', 'NAUKRI', 'MAXHEALTH', 
            'NUVAMA', 'PRESTIGE', 'SWIGGY', 'TVSMOTOR', 'UNIONBANK', 
            'PFC', 'PREMIERENE', 'SRF', 'AMBER', 'ANGELONE', 
            'HINDALCO', 'MANKIND', 'MAZDOCK', 'WAAREEENER', 'BDL', 
            'INDHOTEL', 'JINDALSTEL', 'KAYNES', 'LICHSGFIN', 'MANAPPURAM', 
            'MARUTI', 'TMPV', 'VEDL', 'BAJAJ-AUTO', 'COALINDIA', 
            'CROMPTON', 'DABUR', 'DELHIVERY', 'EICHERMOT', 'HEROMOTOCO',
            'HAL', 'KFINTECH', 'MARICO', 'POWERGRID', 'SHRIRAMFIN',
            'TATACONSUM', 'TRENT', 'CAMS', 'PATANJALI', 'RVNL', 
            'TIINDIA','NIFTY','BANKNIFTY'
        ],
        'OQJ978' => [
            'AMBUJACEM', 'PNBHOUSING', 'INDIANB', 'RELIANCE', 'SUNPHARMA', 
            'INDUSINDBK', 'RBLBANK', 'HUDCO', 'OIL', 'ADANIENT', 
            'SONACOMS', 'ICICIBANK', 'ADANIPORTS', 'INFY', 'TCS', 
            'COFORGE', 'OFSS', 'GRASIM', 'PERSISTENT', 'CDSL', 
            'FORTIS', 'OBEROIRLTY', 'UNOMINDA', 'GLENMARK', 'ZYDUSLIFE', 
            'BHARTIARTL', 'JSWSTEEL', 'ABCAPITAL', 'LTF', 'KEI', 
            'UPL', 'VBL', 'SBICARD', 'DLF', 'IOC', 
            'PHOENIXLTD', 'CUMMINSIND', 'DIVISLAB', 'TECHM', 'POLYCAB', 
            'SUPREMEIND', 'LAURUSLABS', 'PETRONET', 'CANBK', 'LT', 
            'NTPC', 'ICICIPRULI', 'BLUESTARCO', 'TATASTEEL', 'BAJAJFINSV', 
            'TATATECH', 'TITAN', 'BAJFINANCE', 'BIOCON', 'CIPLA', 
            'JUBLFOOD', 'BRITANNIA', 'CHOLAFIN', 'TORNTPOWER', 'TATAELXSI', 
            'HINDUNILVR', 'SIEMENS', 'VOLTAS', 'EXIDEIND', 'SBIN', 
            'SBILIFE', 'PIIND', 'AUROPHARMA', 'PPLPHARMA', 'MFSL', 
            'BEL', 'GAIL', 'ICICIGI'
        ]
    ];

    // protected $specificAssignments = [
    //     'OQJ978' => [
    //         'APLAPOLLO', 'ASHOKLEY', 'ASIANPAINT', 'AXISBANK', 'BANKINDIA', 
    //         'BHARATFORG', 'BSE', 'HAVELLS', 'HDFCAMC', 'INDIGO', 
    //         'JSWENERGY', 'KALYANKJIL', 'KOTAKBANK', 'KPITTECH', 'LUPIN', 
    //         'M&M', 'MCX', 'NATIONALUM', 'PGEL', 'SAIL', 
    //         'BPCL', 'ETERNAL', 'HINDZINC', 'IRCTC', 'LODHA', 
    //         'MPHASIS', 'PAYTM', 'POLICYBZR', 'SYNGENE', 'UNITDSPR', 
    //         '360ONE', 'BHEL', 'IIFL', 'NAUKRI', 'MAXHEALTH', 
    //         'NUVAMA', 'PRESTIGE', 'SWIGGY', 'TVSMOTOR', 'UNIONBANK', 
    //         'PFC', 'PREMIERENE', 'SRF', 'AMBER', 'ANGELONE', 
    //         'HINDALCO', 'MANKIND', 'MAZDOCK', 'WAAREEENER', 'BDL', 
    //         'INDHOTEL', 'JINDALSTEL', 'KAYNES', 'LICHSGFIN', 'MANAPPURAM', 
    //         'MARUTI', 'TMPV', 'VEDL', 'BAJAJ-AUTO', 'COALINDIA', 
    //         'CROMPTON', 'DABUR', 'DELHIVERY', 'EICHERMOT', 'HEROMOTOCO',
    //         'HAL', 'KFINTECH', 'MARICO', 'POWERGRID', 'SHRIRAMFIN',
    //         'TATACONSUM', 'TRENT', 'CAMS', 'PATANJALI'
    //     ],

    //     'OQJ978' => [
    //         'RVNL', 'TIINDIA', 'AMBUJACEM', 'PNBHOUSING', 'INDIANB', 
    //         'RELIANCE', 'SUNPHARMA', 'INDUSINDBK', 'RBLBANK', 'HUDCO', 
    //         'OIL', 'ADANIENT', 'SONACOMS', 'ICICIBANK', 'ADANIPORTS', 
    //         'INFY', 'TCS', 'COFORGE', 'OFSS', 'GRASIM', 
    //         'PERSISTENT', 'CDSL', 'FORTIS', 'OBEROIRLTY', 'UNOMINDA', 
    //         'GLENMARK', 'ZYDUSLIFE', 'BHARTIARTL', 'JSWSTEEL', 'ABCAPITAL', 
    //         'LTF', 'KEI', 'UPL', 'VBL', 'SBICARD', 
    //         'DLF', 'IOC', 'PHOENIXLTD', 'CUMMINSIND', 'DIVISLAB', 
    //         'TECHM', 'POLYCAB', 'SUPREMEIND', 'LAURUSLABS', 'PETRONET', 
    //         'CANBK', 'LT', 'NTPC', 'ICICIPRULI', 'BLUESTARCO', 
    //         'TATASTEEL', 'BAJAJFINSV', 'TATATECH', 'TITAN', 'BAJFINANCE', 
    //         'BIOCON', 'CIPLA', 'JUBLFOOD', 'BRITANNIA', 'CHOLAFIN', 
    //         'TORNTPOWER', 'TATAELXSI', 'HINDUNILVR', 'SIEMENS', 'VOLTAS', 
    //         'EXIDEIND', 'SBIN', 'SBILIFE', 'PIIND', 'AUROPHARMA', 
    //         'PPLPHARMA', 'MFSL', 'BEL', 'GAIL', 'ICICIGI'
    //     ]
    // ];

    public function handle()
    {
        $this->info('🚀 Starting Symbol Distribution (Specific Symbols Only)...');
        
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

        $specificResults = $this->processSpecific($brokers, $specificSymbols);
        
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

    protected function processSpecific($brokers, $specificSymbols)
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
                $result = $this->addSymbol($broker, $data);
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

    protected function addSymbol($broker, $data)
    {
        try {
            if (SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('underlying_name', $data['underlying'])
                ->where('interval', $this->defaultInterval)
                ->exists()) {
                $this->line("   ⏭️  Already exists: {$data['underlying']}");
                return true;
            }

            $baseSymbol = $data['symbol']; // THIS is tradable
            $fut = ZerodhaInstrument::where(function ($q) use ($baseSymbol) {
                    $q->where('name', $baseSymbol)
                    ->orWhere('trading_symbol', 'LIKE', $baseSymbol . '%');
                })
                ->where('instrument_type', 'FUT')
                ->where('exchange', 'NFO')
                ->whereDate('expiry', '>=', now()->toDateString())
                ->orderBy('expiry', 'asc')
                ->first();

            if (!$fut) {
                $fut = ZerodhaInstrument::where('trading_symbol', 'LIKE', $data['underlying'] . '%')
                    ->where('instrument_type', 'FUT')
                    ->where('exchange', 'NFO')
                    ->whereNotNull('expiry')
                    ->orderBy('expiry', 'asc')
                    ->first();
            }

            if (!$fut) {
                $this->warn("   ⚠️  No FUT found: {$data['underlying']}");
                return false;
            }

            if ($this->option('dry-run')) {
                $this->line("   [DRY-RUN] Would add: {$fut->trading_symbol}");
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

            $this->line("   ✓ Added: {$fut->trading_symbol}");
            return true;

        } catch (\Exception $e) {
            $this->error("   ✗ Error with {$data['underlying']}: {$e->getMessage()}");
            Log::error("Distribution error: {$e->getMessage()}");
            return false;
        }
    }
}