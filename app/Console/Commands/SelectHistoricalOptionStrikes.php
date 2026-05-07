<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SymbolData;
use App\Models\ZerodhaInstrument;
use App\Models\OptionStrikeSelection;
use App\Helpers\OptionFairPriceCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SelectHistoricalOptionStrikes extends Command
{
    protected $signature = 'options:select-historical-strikes
                            {--start-date= : Start date (Y-m-d)}
                            {--end-date= : End date (Y-m-d)}
                            {--date= : Single date (Y-m-d)}
                            {--symbol= : Specific symbol (optional)}
                            {--force : Force recalculation}';

    protected $description = 'Select optimal CE/PE strikes from historical data (every 15 minutes)';

    private $strikeIntervals = [
        'NIFTY' => 50,
        'BANKNIFTY' => 100,
        'FINNIFTY' => 50,
        'MIDCPNIFTY' => 25,
        '360ONE' => 20,
        'ABB' => 50,
        'ABCAPITAL' => 5,
        'ADANIENSOL' => 10,
        'ADANIENT' => 20,
        'ADANIGREEN' => 10,
        'ADANIPORTS' => 20,
        'ALKEM' => 50,
        'AMBER' => 100,
        'AMBUJACEM' => 5,
        'ANGELONE' => 50,
        'APLAPOLLO' => 20,
        'APOLLOHOSP' => 50,
        'ASHOKLEY' => 2.5,
        'ASIANPAINT' => 20,
        'ASTRAL' => 20,
        'AUBANK' => 10,
        'AUROPHARMA' => 10,
        'AXISBANK' => 10,
        'BAJAJ-AUTO' => 100,
        'BAJAJFINSV' => 20,
        'BAJAJHLDNG' => 100,
        'BAJFINANCE' => 10,
        'BANDHANBNK' => 2.5,
        'BANKBARODA' => 2.5,
        'BANKINDIA' => 1,
        'BDL' => 20,
        'BEL' => 5,
        'BHARATFORG' => 20,
        'BHARTIARTL' => 20,
        'BHEL' => 2.5,
        'BIOCON' => 5,
        'BLUESTARCO' => 20,
        'BOSCHLTD' => 250,
        'BPCL' => 5,
        'BRITANNIA' => 50,
        'BSE' => 50,
        'CAMS' => 10,
        'CANBK' => 1,
        'CDSL' => 20,
        'CGPOWER' => 10,
        'CHOLAFIN' => 20,
        'CIPLA' => 10,
        'COALINDIA' => 2.5,
        'COFORGE' => 20,
        'COLPAL' => 20,
        'CONCOR' => 5,
        'CROMPTON' => 2.5,
        'CUMMINSIND' => 50,
        'DABUR' => 5,
        'DALBHARAT' => 20,
        'DELHIVERY' => 5,
        'DIVISLAB' => 50,
        'DIXON' => 100,
        'DLF' => 10,
        'DMART' => 50,
        'DRREDDY' => 10,
        'EICHERMOT' => 50,
        'ETERNAL' => 5,
        'EXIDEIND' => 2.5,
        'FEDERALBNK' => 2.5,
        'FORTIS' => 10,
        'GAIL' => 1,
        'GLENMARK' => 20,
        'GMRAIRPORT' => 1,
        'GODREJCP' => 10,
        'GODREJPROP' => 20,
        'GRASIM' => 20,
        'HAL' => 50,
        'HAVELLS' => 10,
        'HCLTECH' => 20,
        'HDFCAMC' => 20,
        'HDFCBANK' => 5,
        'HDFCLIFE' => 20,
        'HEROMOTOCO' => 50,
        'HINDALCO' => 10,
        'HINDPETRO' => 5,
        'HINDUNILVR' => 20,
        'HINDZINC' => 5,
        'HUDCO' => 5,
        'ICICIBANK' => 10,
        'ICICIGI' => 20,
        'ICICIPRULI' => 5,
        'IDEA' => 1,
        'IDFCFIRSTB' => 1,
        'IEX' => 1,
        'INDHOTEL' => 5,
        'INDIANB' => 10,
        'INDIGO' => 50,
        'INDUSINDBK' => 10,
        'INDUSTOWER' => 5,
        'INFY' => 20,
        'INOXWIND' => 2.5,
        'IOC' => 1,
        'IRCTC' => 5,
        'IREDA' => 1,
        'IRFC' => 1,
        'ITC' => 2.5,
        'JINDALSTEL' => 10,
        'JIOFIN' => 2.5,
        'JSWENERGY' => 5,
        'JSWSTEEL' => 10,
        'JUBLFOOD' => 5,
        'KALYANKJIL' => 5,
        'KAYNES' => 50,
        'KEI' => 50,
        'KFINTECH' => 20,
        'KOTAKBANK' => 2.5,
        'KPITTECH' => 10,
        'LAURUSLABS' => 10,
        'LICHSGFIN' => 5,
        'LICI' => 5,
        'LODHA' => 10,
        'LT' => 20,
        'LTF' => 5,
        'LTIM' => 50,
        'LUPIN' => 20,
        'M&M' => 50,
        'MANAPPURAM' => 2.5,
        'MANKIND' => 20,
        'MARICO' => 5,
        'MARUTI' => 100,
        'MAXHEALTH' => 10,
        'MAZDOCK' => 20,
        'MCX' => 20,
        'MFSL' => 20,
        'MOTHERSON' => 1,
        'MPHASIS' => 50,
        'MUTHOOTFIN' => 50,
        'NATIONALUM' => 2.5,
        'NAUKRI' => 20,
        'NBCC' => 1,
        'NESTLEIND' => 10,
        'NHPC' => 1,
        'NMDC' => 1,
        'NTPC' => 2.5,
        'NUVAMA' => 20,
        'NYKAA' => 2.5,
        'OBEROIRLTY' => 20,
        'OFSS' => 100,
        'OIL' => 5,
        'ONGC' => 1,
        'PAGEIND' => 500,
        'PATANJALI' => 5,
        'PAYTM' => 20,
        'PERSISTENT' => 100,
        'PETRONET' => 2.5,
        'PFC' => 2.5,
        'PGEL' => 10,
        'PHOENIXLTD' => 20,
        'PIDILITIND' => 10,
        'PIIND' => 20,
        'PNB' => 1,
        'PNBHOUSING' => 10,
        'POLICYBZR' => 20,
        'POLYCAB' => 100,
        'POWERGRID' => 2.5,
        'POWERINDIA' => 250,
        'PPLPHARMA' => 2.5,
        'PREMIERENE' => 10,
        'PRESTIGE' => 20,
        'RBLBANK' => 5,
        'RECLTD' => 2.5,
        'RELIANCE' => 10,
        'RVNL' => 5,
        'SAIL' => 1,
        'SAMMAANCAP' => 2.5,
        'SBICARD' => 10,
        'SBILIFE' => 20,
        'SBIN' => 5,
        'SHREECEM' => 250,
        'SHRIRAMFIN' => 10,
        'SIEMENS' => 50,
        'SOLARINDS' => 100,
        'SONACOMS' => 5,
        'SRF' => 20,
        'SUNPHARMA' => 10,
        'SUPREMEIND' => 50,
        'SUZLON' => 1,
        'SWIGGY' => 5,
        'SYNGENE' => 10,
        'TATACONSUM' => 10,
        'TATAELXSI' => 50,
        'TATAPOWER' => 2.5,
        'TATASTEEL' => 1,
        'TATATECH' => 5,
        'TCS' => 20,
        'TECHM' => 20,
        'TIINDIA' => 20,
        'TITAN' => 20,
        'TMPV' => 5,
        'TORNTPHARM' => 20,
        'TORNTPOWER' => 10,
        'TRENT' => 50,
        'TVSMOTOR' => 20,
        'ULTRACEMCO' => 100,
        'UNIONBANK' => 2.5,
        'UNITDSPR' => 10,
        'UNOMINDA' => 20,
        'UPL' => 10,
        'VBL' => 5,
        'VEDL' => 5,
        'VOLTAS' => 20,
        'WAAREEENER' => 50,
        'WIPRO' => 2.5,
        'YESBANK' => 1,
        'ZYDUSLIFE' => 10,
    ];

    public function handle()
    {
        // ✅ Determine date range
        if ($this->option('date')) {
            $startDate = Carbon::parse($this->option('date'));
            $endDate = $startDate->copy();
        } else {
            $startDate = $this->option('start-date') 
                ? Carbon::parse($this->option('start-date')) 
                : Carbon::today()->subDays(7);
            
            $endDate = $this->option('end-date') 
                ? Carbon::parse($this->option('end-date')) 
                : Carbon::today();
        }

        $specificSymbol = $this->option('symbol');

        $this->info("🎯 Historical Option Strike Selection");
        $this->info("   Date Range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        if ($specificSymbol) {
            $this->info("   Symbol: {$specificSymbol}");
        }
        $this->info("   Strategy: Highest OI among ATM±2");
        $this->info("   Series: Current Month Only");  // ✅ ADDED
        $this->info("   Pricing: Black-Scholes Fair Value (Risk-Free Rate: 1%)");
        $this->info("   NOTE: OI/LTP will be NULL (no historical options data stored)\n");

        $totalProcessed = 0;
        $totalFailed = 0;

        // ✅ Loop through each date
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            
            // Skip weekends
            if ($currentDate->isWeekend()) {
                $this->warn("⏭️  Skipping {$currentDate->format('Y-m-d')} (Weekend)");
                $currentDate->addDay();
                continue;
            }

            // Check market holidays
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $currentDate->format('Y-m-d'))
                ->exists();

            if ($isHoliday) {
                $this->warn("⏭️  Skipping {$currentDate->format('Y-m-d')} (Market Holiday)");
                $currentDate->addDay();
                continue;
            }

            $this->info("\n📅 Processing Date: {$currentDate->format('Y-m-d')}");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            try {
                // ✅ Get all 15-min candles for this date during market hours (FUTURES only)
                $candles = SymbolData::whereDate('timestamp', $currentDate)
                    ->whereTime('timestamp', '>=', '09:15:00')
                    ->whereTime('timestamp', '<=', '15:30:00')
                    ->where('interval', '15minute')
                    ->where('trading_symbol', 'like', '%FUT')  // ✅ Only futures
                    ->when($specificSymbol, function ($query) use ($specificSymbol) {
                        return $query->where('trading_symbol', 'like', $specificSymbol . '%');
                    })
                    ->orderBy('timestamp', 'ASC')
                    ->get()
                    ->groupBy(function ($item) {
                        // Group by both symbol and rounded timestamp
                        $roundedTime = $this->roundToNearest15Minutes(Carbon::parse($item->timestamp));
                        return $item->trading_symbol . '|' . $roundedTime->format('Y-m-d H:i:s');
                    });

                if ($candles->isEmpty()) {
                    $this->warn("⚠️  No data found for {$currentDate->format('Y-m-d')}");
                    $currentDate->addDay();
                    continue;
                }

                $dayProcessed = 0;
                $dayFailed = 0;

                // ✅ Process each 15-min interval
                foreach ($candles as $key => $intervalCandles) {
                    [$tradingSymbol, $intervalTimeStr] = explode('|', $key);
                    $intervalTime = Carbon::parse($intervalTimeStr);
                    
                    try {
                        $candle = $intervalCandles->first();
                        
                        $this->info("  🕐 {$intervalTime->format('H:i')} - {$tradingSymbol}");
                        
                        $this->selectStrikesForSymbol($candle, $currentDate, $intervalTime, 'current');
                        // $this->selectStrikesForSymbol($candle, $currentDate, $intervalTime, 'next');
                        
                        $dayProcessed++;
                        $totalProcessed++;
                        
                    } catch (\Exception $e) {
                        $dayFailed++;
                        $totalFailed++;
                        $this->error("     ✗ Failed: " . $e->getMessage());
                        Log::error("Historical strike selection failed", [
                            'date' => $currentDate->format('Y-m-d'),
                            'interval' => $intervalTime->format('H:i'),
                            'symbol' => $tradingSymbol,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $this->info("  ✓ Day Summary: Processed {$dayProcessed} | Failed: {$dayFailed}");

            } catch (\Exception $e) {
                $this->error("Critical Error for {$currentDate->format('Y-m-d')}: " . $e->getMessage());
                Log::error("Historical processing error", [
                    'date' => $currentDate->format('Y-m-d'),
                    'error' => $e->getMessage()
                ]);
            }

            $currentDate->addDay();
        }

        $this->info("\n✅ Historical Strike Selection Completed");
        $this->info("   Total Processed: {$totalProcessed}");
        $this->info("   Total Failed: {$totalFailed}\n");

        return 0;
    }

    private function roundToNearest15Minutes(Carbon $time): Carbon
    {
        $minutes = $time->minute;
        $roundedMinutes = floor($minutes / 15) * 15;
        
        return $time->copy()
            ->minute($roundedMinutes)
            ->second(0)
            ->microsecond(0);
    }

    private function selectStrikesForSymbol($candle, $tradeDate, $intervalTime, $optionSeries)
    {
        $futureSymbol = $candle->trading_symbol;
        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $futureSymbol);
        $futurePrice = $candle->close;
        $brokerId = $candle->broker_api_id; // ✅ Get broker ID

        $strikeInterval = $this->strikeIntervals[$baseSymbol] ?? 20;
        $atmStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

        $ceStrikes = [
            $atmStrike,
            $atmStrike + $strikeInterval,
            $atmStrike + (2 * $strikeInterval)
        ];

        $peStrikes = [
            $atmStrike,
            $atmStrike - $strikeInterval,
            $atmStrike - (2 * $strikeInterval)
        ];

        $expiry = $this->getExpiry($baseSymbol, $optionSeries, $tradeDate);
        
        // ✅ Calculate days to expiry from the historical date
        $currentDate = Carbon::parse($tradeDate);
        $expiryDate = Carbon::parse($expiry);
        $daysToExpiry = $currentDate->diffInDays($expiryDate);
        
        $iv = OptionFairPriceCalculator::getImpliedVolatility($baseSymbol);
        $riskFreeRate = 0.01;

        // ✅ Pass broker ID and trade date
        $ceOptions = $this->fetchOptionsWithOI($baseSymbol, $ceStrikes, 'CE', $expiry, $brokerId, $tradeDate);
        $peOptions = $this->fetchOptionsWithOI($baseSymbol, $peStrikes, 'PE', $expiry, $brokerId, $tradeDate);

        // Calculate fair prices for CE
        foreach ($ceOptions as &$option) {
            $option['fair_price'] = OptionFairPriceCalculator::calculateFairPrice(
                $futurePrice,
                $option['strike'],
                $daysToExpiry,
                $iv,
                $riskFreeRate,
                'CE'
            );
            
            if ($option['ltp'] !== null && $option['ltp'] > 0) {
                $option['valuation'] = OptionFairPriceCalculator::valuationStatus(
                    $option['ltp'],
                    $option['fair_price']
                );
            } else {
                $option['valuation'] = 'N/A';
            }
        }

        // Calculate fair prices for PE
        foreach ($peOptions as &$option) {
            $option['fair_price'] = OptionFairPriceCalculator::calculateFairPrice(
                $futurePrice,
                $option['strike'],
                $daysToExpiry,
                $iv,
                $riskFreeRate,
                'PE'
            );
            
            if ($option['ltp'] !== null && $option['ltp'] > 0) {
                $option['valuation'] = OptionFairPriceCalculator::valuationStatus(
                    $option['ltp'],
                    $option['fair_price']
                );
            } else {
                $option['valuation'] = 'N/A';
            }
        }

        // Find highest OI (will all be 0, so just take first)
        $selectedCE = collect($ceOptions)->sortByDesc('oi')->first();
        $selectedPE = collect($peOptions)->sortByDesc('oi')->first();

        $selectedCERecommendation = $this->getRecommendation($selectedCE['valuation']);
        $selectedPERecommendation = $this->getRecommendation($selectedPE['valuation']);

        // Store in database
        OptionStrikeSelection::updateOrCreate(
            [
                'trade_date' => $tradeDate,
                'interval_time' => $intervalTime,
                'future_symbol' => $futureSymbol,
                'option_series' => $optionSeries
            ],
            [
                'base_symbol' => $baseSymbol,
                'future_price' => $futurePrice,
                'atm_strike' => $atmStrike,
                
                // CE Data
                'ce_atm_strike' => $ceOptions[0]['strike'],
                'ce_atm_symbol' => $ceOptions[0]['symbol'],
                'ce_atm_oi' => $ceOptions[0]['oi'],
                'ce_atm_fair_price' => $ceOptions[0]['fair_price'],
                'ce_atm_ltp' => $ceOptions[0]['ltp'],
                'ce_atm_valuation' => $ceOptions[0]['valuation'],
                
                'ce_atm1_strike' => $ceOptions[1]['strike'],
                'ce_atm1_symbol' => $ceOptions[1]['symbol'],
                'ce_atm1_oi' => $ceOptions[1]['oi'],
                'ce_atm1_fair_price' => $ceOptions[1]['fair_price'],
                'ce_atm1_ltp' => $ceOptions[1]['ltp'],
                'ce_atm1_valuation' => $ceOptions[1]['valuation'],
                
                'ce_atm2_strike' => $ceOptions[2]['strike'],
                'ce_atm2_symbol' => $ceOptions[2]['symbol'],
                'ce_atm2_oi' => $ceOptions[2]['oi'],
                'ce_atm2_fair_price' => $ceOptions[2]['fair_price'],
                'ce_atm2_ltp' => $ceOptions[2]['ltp'],
                'ce_atm2_valuation' => $ceOptions[2]['valuation'],
                
                // PE Data
                'pe_atm_strike' => $peOptions[0]['strike'],
                'pe_atm_symbol' => $peOptions[0]['symbol'],
                'pe_atm_oi' => $peOptions[0]['oi'],
                'pe_atm_fair_price' => $peOptions[0]['fair_price'],
                'pe_atm_ltp' => $peOptions[0]['ltp'],
                'pe_atm_valuation' => $peOptions[0]['valuation'],
                
                'pe_atm1_strike' => $peOptions[1]['strike'],
                'pe_atm1_symbol' => $peOptions[1]['symbol'],
                'pe_atm1_oi' => $peOptions[1]['oi'],
                'pe_atm1_fair_price' => $peOptions[1]['fair_price'],
                'pe_atm1_ltp' => $peOptions[1]['ltp'],
                'pe_atm1_valuation' => $peOptions[1]['valuation'],
                
                'pe_atm2_strike' => $peOptions[2]['strike'],
                'pe_atm2_symbol' => $peOptions[2]['symbol'],
                'pe_atm2_oi' => $peOptions[2]['oi'],
                'pe_atm2_fair_price' => $peOptions[2]['fair_price'],
                'pe_atm2_ltp' => $peOptions[2]['ltp'],
                'pe_atm2_valuation' => $peOptions[2]['valuation'],
                
                // Selected (Highest OI)
                'selected_ce_symbol' => $selectedCE['symbol'],
                'selected_ce_strike' => $selectedCE['strike'],
                'selected_ce_oi' => $selectedCE['oi'],
                'selected_ce_fair_price' => $selectedCE['fair_price'],
                'selected_ce_valuation' => $selectedCE['valuation'],
                'selected_ce_recommendation' => $selectedCERecommendation,
                
                'selected_pe_symbol' => $selectedPE['symbol'],
                'selected_pe_strike' => $selectedPE['strike'],
                'selected_pe_oi' => $selectedPE['oi'],
                'selected_pe_fair_price' => $selectedPE['fair_price'],
                'selected_pe_valuation' => $selectedPE['valuation'],
                'selected_pe_recommendation' => $selectedPERecommendation,
                
                'expiry_date' => $expiry,
                'calculated_at' => now(),
                'last_updated_at' => now(),
            ]
        );
    }

    /**
     * ✅ Fetch options from ZerodhaInstrument (SAME AS BACKUP COMMAND)
     * OI and LTP will be NULL for historical data
     */
    private function fetchOptionsWithOI($baseSymbol, $strikes, $optionType, $expiry, $brokerId = null, $tradeDate = null)
    {
        $options = [];
        
        // ✅ If processing TODAY's data AND we have a broker, fetch LIVE data
        $isToday = $tradeDate && Carbon::parse($tradeDate)->isToday();
        
        if ($isToday && $brokerId) {
            $broker = \App\Models\BrokerApi::find($brokerId);
            
            if ($broker && $broker->hasValidToken()) {
                try {
                    $this->info("      🔴 Fetching LIVE OI and LTP from Zerodha API...");
                    
                    $liveOptions = \App\Helpers\ZerodhaOptionChainHelper::fetchLiveOptionData(
                        $broker,
                        $baseSymbol,
                        $strikes,
                        $optionType,
                        $expiry
                    );
                    
                    foreach ($liveOptions as $liveOption) {
                        $instrument = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
                            ->where('exchange', 'NFO')
                            ->where('instrument_type', $optionType)
                            ->where('strike', $liveOption['strike'])
                            ->whereDate('expiry', $expiry)
                            ->first();
                        
                        $options[] = [
                            'strike' => $liveOption['strike'],
                            'symbol' => $instrument ? $instrument->trading_symbol : $liveOption['symbol'],
                            'oi' => $liveOption['oi'], // ✅ LIVE OI
                            'ltp' => $liveOption['ltp'], // ✅ LIVE LTP
                            'volume' => $liveOption['volume'],
                            'fair_price' => null,
                            'valuation' => null
                        ];
                    }
                    
                    $this->info("      ✅ LIVE data fetched successfully");
                    return $options;
                    
                } catch (\Exception $e) {
                    $this->warn("      ⚠️  Live fetch failed: " . $e->getMessage());
                    $this->warn("      Falling back to database...");
                }
            }
        }
        
        // ❌ FALLBACK: Use database (for historical data or if live fetch fails)
        $this->warn("      ⚠️  Using database fallback (OI/LTP will be NULL/0)");
        
        foreach ($strikes as $strike) {
            $option = \App\Models\ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $strike)
                ->whereDate('expiry', $expiry)
                ->first();

            if ($option) {
                $options[] = [
                    'strike' => $strike,
                    'symbol' => $option->trading_symbol,
                    'oi' => 0,
                    'ltp' => null,
                    'fair_price' => null,
                    'valuation' => null
                ];
            } else {
                $options[] = [
                    'strike' => $strike,
                    'symbol' => "{$baseSymbol}{$strike}{$optionType}",
                    'oi' => 0,
                    'ltp' => null,
                    'fair_price' => null,
                    'valuation' => null
                ];
            }
        }

        return $options;
    }

    /**
     * ✅ Get expiry relative to the historical trade date
     */
    private function getExpiry($baseSymbol, $series, $tradeDate)
    {
        $query = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $tradeDate)  // ✅ Use trade date, not now()
            ->orderBy('expiry', 'ASC');

        if ($series === 'next') {
            $secondExpiry = $query->skip(1)->first();
            return $secondExpiry ? $secondExpiry->expiry : Carbon::parse($tradeDate)->addDays(14);
        }

        $firstExpiry = $query->first();
        return $firstExpiry ? $firstExpiry->expiry : Carbon::parse($tradeDate)->addDays(7);
    }

    private function getRecommendation($valuation)
    {
        switch ($valuation) {
            case 'UNDERPRICED':
                return 'GOOD TO BUY';
            case 'OVERPRICED':
                return 'GOOD TO SELL';
            case 'FAIR':
                return 'WAIT OR AVOID';
            default:
                return 'N/A';
        }
    }
}