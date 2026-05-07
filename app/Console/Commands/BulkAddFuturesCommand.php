<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FuturesMonitored;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class BulkAddFuturesCommand extends Command
{
    protected $signature = 'futures:bulk-add';

    protected $description = 'Bulk add futures symbols with predefined settings';

    // Define symbols and their intervals here
    private $symbolsConfig = [
        '360ONE' => '15minute',
        'ABB' => '15minute',
        'APLAPOLLO' => '15minute',
        'AUBANK' => '15minute',
        'ADANIENSOL' => '15minute',
        'ADANIENT' => '15minute',
        'ADANIGREEN' => '15minute',
        'ADANIPORTS' => '15minute',
        'ABCAPITAL' => '15minute',
        'ALKEM' => '15minute',
        'AMBER' => '15minute',
        'AMBUJACEM' => '15minute',
        'ANGELONE' => '15minute',
        'APOLLOHOSP' => '15minute',
        'ASHOKLEY' => '15minute',
        'ASIANPAINT' => '15minute',
        'ASTRAL' => '15minute',
        'AUROPHARMA' => '15minute',
        'DMART' => '15minute',
        'AXISBANK' => '15minute',
        'BSE' => '15minute',
        'BAJAJ-AUTO' => '15minute',
        'BAJFINANCE' => '15minute',
        'BAJAJFINSV' => '15minute',
        'BAJAJHLDNG' => '15minute',
        'BANDHANBNK' => '15minute',
        'BANKBARODA' => '15minute',
        'BANKINDIA' => '15minute',
        'BDL' => '15minute',
        'BEL' => '15minute',
        'BHARATFORG' => '15minute',
        'BHEL' => '15minute',
        'BPCL' => '15minute',
        'BHARTIARTL' => '15minute',
        'BIOCON' => '15minute',
        'BLUESTARCO' => '15minute',
        'BOSCHLTD' => '15minute',
        'BRITANNIA' => '15minute',
        'CGPOWER' => '15minute',
        'CANBK' => '15minute',
        'CDSL' => '15minute',
        'CHOLAFIN' => '15minute',
        'CIPLA' => '15minute',
        'COALINDIA' => '15minute',
        'COFORGE' => '15minute',
        'COLPAL' => '15minute',
        'CAMS' => '15minute',
        'CONCOR' => '15minute',
        'CROMPTON' => '15minute',
        'CUMMINSIND' => '15minute',
        'DLF' => '15minute',
        'DABUR' => '15minute',
        'DALBHARAT' => '15minute',
        'DELHIVERY' => '15minute',
        'DIVISLAB' => '15minute',
        'DIXON' => '15minute',
        'DRREDDY' => '15minute',
        'ETERNAL' => '15minute',
        'EICHERMOT' => '15minute',
        'EXIDEIND' => '15minute',
        'NYKAA' => '15minute',
        'FORTIS' => '15minute',
        'GAIL' => '15minute',
        'GMRAIRPORT' => '15minute',
        'GLENMARK' => '15minute',
        'GODREJCP' => '15minute',
        'GODREJPROP' => '15minute',
        'GRASIM' => '15minute',
        'HCLTECH' => '15minute',
        'HDFCAMC' => '15minute',
        'HDFCBANK' => '15minute',
        'HDFCLIFE' => '15minute',
        'HAVELLS' => '15minute',
        'HEROMOTOCO' => '15minute',
        'HINDALCO' => '15minute',
        'HAL' => '15minute',
        'HINDPETRO' => '15minute',
        'HINDUNILVR' => '15minute',
        'HINDZINC' => '15minute',
        'POWERINDIA' => '15minute',
        'HUDCO' => '15minute',
        'ICICIBANK' => '15minute',
        'ICICIGI' => '15minute',
        'ICICIPRULI' => '15minute',
        'IDFCFIRSTB' => '15minute',
        'IIFL' => '15minute',
        'ITC' => '15minute',
        'INDIANB' => '15minute',
        'IEX' => '15minute',
        'IOC' => '15minute',
        'IRCTC' => '15minute',
        'IRFC' => '15minute',
        'IREDA' => '15minute',
        'INDUSTOWER' => '15minute',
        'INDUSINDBK' => '15minute',
        'NAUKRI' => '15minute',
        'INFY' => '15minute',
        'INOXWIND' => '15minute',
        'INDIGO' => '15minute',
        'JINDALSTEL' => '15minute',
        'JSWENERGY' => '15minute',
        'JSWSTEEL' => '15minute',
        'JIOFIN' => '15minute',
        'JUBLFOOD' => '15minute',
        'KEI' => '15minute',
        'KPITTECH' => '15minute',
        'KALYANKJIL' => '15minute',
        'KAYNES' => '15minute',
        'KFINTECH' => '15minute',
        'KOTAKBANK' => '15minute',
        'LTF' => '15minute',
        'LICHSGFIN' => '15minute',
        'LTIM' => '15minute',
        'LT' => '15minute',
        'LAURUSLABS' => '15minute',
        'LICI' => '15minute',
        'LODHA' => '15minute',
        'LUPIN' => '15minute',
        'M&M' => '15minute',
        'MANAPPURAM' => '15minute',
        'MANKIND' => '15minute',
        'MARICO' => '15minute',
        'MARUTI' => '15minute',
        'MFSL' => '15minute',
        'MAXHEALTH' => '15minute',
        'MAZDOCK' => '15minute',
        'MPHASIS' => '15minute',
        'MCX' => '15minute',
        'MUTHOOTFIN' => '15minute',
        'NBCC' => '15minute',
        'NHPC' => '15minute',
        'NMDC' => '15minute',
        'NTPC' => '15minute',
        'NATIONALUM' => '15minute',
        'NESTLEIND' => '15minute',
        'NUVAMA' => '15minute',
        'OBEROIRLTY' => '15minute',
        'ONGC' => '15minute',
        'OIL' => '15minute',
        'PAYTM' => '15minute',
        'OFSS' => '15minute',
        'POLICYBZR' => '15minute',
        'PGEL' => '15minute',
        'PIIND' => '15minute',
        'PNBHOUSING' => '15minute',
        'PAGEIND' => '15minute',
        'PATANJALI' => '15minute',
        'PERSISTENT' => '15minute',
        'PETRONET' => '15minute',
        'PIDILITIND' => '15minute',
        'PPLPHARMA' => '15minute',
        'POLYCAB' => '15minute',
        'PFC' => '15minute',
        'POWERGRID' => '15minute',
        'PREMIERENE' => '15minute',
        'PRESTIGE' => '15minute',
        'PNB' => '15minute',
        'RBLBANK' => '15minute',
        'RECLTD' => '15minute',
        'RVNL' => '15minute',
        'RELIANCE' => '15minute',
        'SBICARD' => '15minute',
        'SBILIFE' => '15minute',
        'SHREECEM' => '15minute',
        'SRF' => '15minute',
        'SAMMAANCAP' => '15minute',
        'MOTHERSON' => '15minute',
        'SHRIRAMFIN' => '15minute',
        'SIEMENS' => '15minute',
        'SOLARINDS' => '15minute',
        'SONACOMS' => '15minute',
        'SBIN' => '15minute',
        'SAIL' => '15minute',
        'SUNPHARMA' => '15minute',
        'SUPREMEIND' => '15minute',
        'SUZLON' => '15minute',
        'SWIGGY' => '15minute',
        'SYNGENE' => '15minute',
        'TATACONSUM' => '15minute',
        'TVSMOTOR' => '15minute',
        'TCS' => '15minute',
        'TATAELXSI' => '15minute',
        'TMPV' => '15minute',
        'TATAPOWER' => '15minute',
        'TATASTEEL' => '15minute',
        'TATATECH' => '15minute',
        'TECHM' => '15minute',
        'FEDERALBNK' => '15minute',
        'INDHOTEL' => '15minute',
        'PHOENIXLTD' => '15minute',
        'TITAN' => '15minute',
        'TORNTPHARM' => '15minute',
        'TORNTPOWER' => '15minute',
        'TRENT' => '15minute',
        'TIINDIA' => '15minute',
        'UNOMINDA' => '15minute',
        'UPL' => '15minute',
        'ULTRACEMCO' => '15minute',
        'UNIONBANK' => '15minute',
        'UNITDSPR' => '15minute',
        'VBL' => '15minute',
        'VEDL' => '15minute',
        'IDEA' => '15minute',
        'VOLTAS' => '15minute',
        'WAAREEENER' => '15minute',
        'WIPRO' => '15minute',
        'YESBANK' => '15minute',
        'ZYDUSLIFE' => '15minute',
        'NIFTY' => '15minute',
        'BANKNIFTY' => '15minute',
    ];

    public function handle()
    {
        $this->info("🚀 Starting bulk futures symbol addition...\n");

        foreach ($this->symbolsConfig as $baseSymbol => $interval) {
            $this->info("Processing {$baseSymbol}...");
            
            try {
                // Search for the symbol in zerodha_instruments
                $instruments = DB::table('zerodha_instruments')
                    ->where('instrument_type', 'FUT')
                    ->where('exchange', 'NFO')
                    ->where('trading_symbol', 'LIKE', $baseSymbol . '%')
                    ->orderBy('expiry', 'asc') // Order by ascending to get closest first
                    ->get();

                if ($instruments->isEmpty()) {
                    $this->error("  ❌ No futures found for symbol: {$baseSymbol}");
                    continue;
                }

                // Get the closest expiry (first one after today)
                $today = Carbon::today();
                $closestInstrument = null;

                foreach ($instruments as $inst) {
                    $expiryDate = Carbon::parse($inst->expiry);
                    if ($expiryDate->greaterThanOrEqualTo($today)) {
                        $closestInstrument = $inst;
                        break;
                    }
                }

                // If all contracts are expired, take the last one
                if (!$closestInstrument && $instruments->count() > 0) {
                    $closestInstrument = $instruments->last();
                }

                if (!$closestInstrument) {
                    $this->error("  ❌ No valid contract found for {$baseSymbol}");
                    continue;
                }

                $this->info("  📅 Selected: {$closestInstrument->trading_symbol} (Expiry: {$closestInstrument->expiry})");
                
                $this->addSymbol($closestInstrument, $interval);

            } catch (Exception $e) {
                $this->error("  ❌ Error processing {$baseSymbol}: " . $e->getMessage());
                continue;
            }
        }

        $this->info("\n✅ Bulk addition completed!");
        return 0;
    }

    private function addSymbol($instrument, $interval)
    {
        // Check if already exists
        $existing = FuturesMonitored::where('trading_symbol', $instrument->trading_symbol)->first();

        if ($existing) {
            $this->warn("    ⚠ {$instrument->trading_symbol} already exists!");
            
            // Auto-update if interval is different
            if ($existing->intervals !== $interval) {
                $existing->update([
                    'intervals' => $interval,
                    'is_active' => true
                ]);
                $this->info("    ✓ Updated interval to '{$interval}'");
            } else {
                $this->info("    ℹ No changes needed");
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

        $this->info("    ✓ Added {$instrument->trading_symbol} with '{$interval}' interval");
    }
}