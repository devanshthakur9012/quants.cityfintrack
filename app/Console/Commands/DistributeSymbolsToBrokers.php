<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\SymbolList;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DistributeSymbolsToBrokers extends Command
{
    protected $signature = 'symbols:distribute {--reset} {--dry-run}';
    protected $description = 'Distribute symbols from symbol_lists to Zerodha brokers with latest FUT expiry';
    
    protected string $defaultInterval = 'minute';
    protected $specificAssignments = [
        'DB0542' => [
            '360ONE','ABB','APLAPOLLO','AUBANK','ADANIENSOL','ADANIENT','ADANIGREEN','ADANIPORTS',
            'ABCAPITAL','ALKEM','AMBER','AMBUJACEM','ANGELONE','APOLLOHOSP','ASHOKLEY','ASIANPAINT',
            'ASTRAL','AUROPHARMA','DMART','AXISBANK','BSE','BAJAJ-AUTO','BAJFINANCE','BAJAJFINSV',
            'BAJAJHLDNG','BANDHANBNK','BANKBARODA','BANKINDIA','BDL','BEL','BHARATFORG','BHEL',
            'BPCL','BHARTIARTL','BIOCON','BLUESTARCO','BOSCHLTD','BRITANNIA','CGPOWER','CANBK',
            'CDSL','CHOLAFIN','CIPLA','COALINDIA','COFORGE','COLPAL','CAMS','CONCOR','CROMPTON',
            'CUMMINSIND','DLF','DABUR','DALBHARAT','DELHIVERY','DIVISLAB','DIXON','DRREDDY',
            'ETERNAL','EICHERMOT','EXIDEIND','NYKAA','FORTIS','GAIL','GMRAIRPORT','GLENMARK',
            'GODREJCP','GODREJPROP','GRASIM','HCLTECH','HDFCAMC','HDFCBANK','HDFCLIFE','HAVELLS',
            'HEROMOTOCO','HINDALCO','HAL','HINDPETRO','HINDUNILVR','HINDZINC','POWERINDIA',
            'HUDCO','ICICIBANK','ICICIGI','ICICIPRULI','IDFCFIRSTB','IIFL','ITC','INDIANB',
            'IEX','IOC','IRCTC','IRFC','IREDA','INDUSTOWER'
        ],

        'DB0542' => [
            'INDUSINDBK','NAUKRI','INFY','INOXWIND','INDIGO','JINDALSTEL','JSWENERGY','JSWSTEEL',
            'JIOFIN','JUBLFOOD','KEI','KPITTECH','KALYANKJIL','KAYNES','KFINTECH','KOTAKBANK',
            'LTF','LICHSGFIN','LTIM','LT','LAURUSLABS','LICI','LODHA','LUPIN','M&M','MANAPPURAM',
            'MANKIND','MARICO','MARUTI','MFSL','MAXHEALTH','MAZDOCK','MPHASIS','MCX','MUTHOOTFIN',
            'NBCC','NHPC','NMDC','NTPC','NATIONALUM','NESTLEIND','NUVAMA','OBEROIRLTY','ONGC',
            'OIL','PAYTM','OFSS','POLICYBZR','PGEL','PIIND','PNBHOUSING','PAGEIND','PATANJALI',
            'PERSISTENT','PETRONET','PIDILITIND','PPLPHARMA','POLYCAB','PFC','POWERGRID',
            'PREMIERENE','PRESTIGE','PNB','RBLBANK','RECLTD','RVNL','RELIANCE','SBICARD',
            'SBILIFE','SHREECEM','SRF','SAMMAANCAP','MOTHERSON','SHRIRAMFIN','SIEMENS',
            'SOLARINDS','SONACOMS','SBIN','SAIL','SUNPHARMA','SUPREMEIND','SUZLON','SWIGGY',
            'SYNGENE','TATACONSUM','TVSMOTOR','TCS','TATAELXSI','TMPV','TATAPOWER','TATASTEEL',
            'TATATECH','TECHM','FEDERALBNK','INDHOTEL','PHOENIXLTD','TITAN','TORNTPHARM',
            'TORNTPOWER','TRENT','TIINDIA','UNOMINDA','UPL','ULTRACEMCO','UNIONBANK','UNITDSPR',
            'VBL','VEDL','IDEA','VOLTAS','WAAREEENER','WIPRO','YESBANK','ZYDUSLIFE'
        ]
    ];


    // protected $specificAssignments = [
    //     'DB0542' => [
    //         'AXISBANK', 'BHARTIARTL', 'HINDUNILVR', 'SHRIRAMFIN', 'TMPV', 'HAL',
    //         'INDUSTOWER', 'LTF', 'TITAN', 'HINDZINC', 'M&M', 'PAYTM',
    //         'SRF', 'SBIN'
    //     ]
    // ];

    public function handle()
    {
        $this->info('🚀 Starting Symbol Distribution...');
        
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
        $generalSymbols = [];

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
            
            if (!$assigned) {
                $generalSymbols[] = ['symbol' => $symbol, 'underlying' => $underlying];
            }
        }

        $specificResults = $this->processSpecific($brokers, $specificSymbols);
        $generalResults = $this->processGeneral($brokers, $generalSymbols);
        
        $this->info("\n📊 Summary: Processed {$specificResults['success']}+{$generalResults['success']}, Failed {$specificResults['failed']}+{$generalResults['failed']}");
        return 0;
    }

    protected function processSpecific($brokers, $specificSymbols)
    {
        $success = $failed = 0;
        foreach ($specificSymbols as $username => $symbols) {
            $broker = $brokers->firstWhere('account_user_name', $username);
            if (!$broker) {
                $failed += count($symbols);
                continue;
            }
            foreach ($symbols as $data) {
                $this->addSymbol($broker, $data) ? $success++ : $failed++;
            }
        }
        return ['success' => $success, 'failed' => $failed];
    }

    protected function processGeneral($brokers, $generalSymbols)
    {
        if (empty($generalSymbols)) return ['success' => 0, 'failed' => 0];
        
        $success = $failed = 0;
        $count = $brokers->count();
        $base = floor(count($generalSymbols) / $count);
        $offset = 0;

        foreach ($brokers as $i => $broker) {
            $take = $base + ($i < (count($generalSymbols) % $count) ? 1 : 0);
            $batch = array_slice($generalSymbols, $offset, $take);
            $offset += $take;
            
            foreach ($batch as $data) {
                $this->addSymbol($broker, $data) ? $success++ : $failed++;
            }
        }
        return ['success' => $success, 'failed' => $failed];
    }

    protected function addSymbol($broker, $data)
    {
        try {
            if (SymbolMonitored::where('broker_api_id', $broker->id)->where('underlying_name', $data['underlying'])->where('interval', $this->defaultInterval)->exists()) {
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
                $this->warn("⚠️  No FUT: {$data['underlying']}");
                return false;
            }

            if ($this->option('dry-run')) {
                $this->line("[DRY] {$fut->trading_symbol}");
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

            $this->line("✓ {$fut->trading_symbol}");
            return true;

        } catch (\Exception $e) {
            $this->error("✗ {$data['underlying']}: {$e->getMessage()}");
            Log::error("Distribution error: {$e->getMessage()}");
            return false;
        }
    }
}