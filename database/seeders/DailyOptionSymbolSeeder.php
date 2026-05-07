<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DailyOptionSymbol;

class DailyOptionSymbolSeeder extends Seeder
{
    /**
     * The canonical list of symbols tracked by the option OHLC pipeline.
     * Update this list as needed — it is the single source of truth.
    */
    private const SYMBOLS = [
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
        'ABB',
        'ACC',
        'ADANIENT',
        'ADANIGREEN',
        'ADANIPOWER',
        'ALKEM',
        'APOLLOHOSP',
        'APOLLOTYRE',
        'ASHOKLEY',
        'ASTRAL',
        'ATGL',
        'BALKRISIND',
        'BANDHANBNK',
        'BEL',
        'BERGEPAINT',
        'BIOCON',
        'BOSCHLTD',
        'BRITANNIA',
        'CANBK',
        'CHOLAFIN',
        'CIPLA',
        'COALINDIA',
        'COLPAL',
        'CONCOR',
        'CROMPTON',
        'DABUR',
        'DIVISLAB',
        'DLF',
        'ESCORTS',
        'GAIL',
        'GLENMARK',
        'GODREJCP',
        'GRASIM',
        'HCLTECH',
        'HDFCBANK',
        'HDFCLIFE',
        'HINDUNILVR',
        'IOC',
        'IRCTC',
        'ITC',
        'JINDALSTEL',
        'JUBLFOOD',
        'KOTAKBANK',
        'LT',
        'LTIM',
        'MARUTI',
        'MOTHERSON',
        'MPHASIS',
        'NMDC',
        'NTPC'
    ];

    public function run(): void
    {
        foreach (self::SYMBOLS as $symbol) {
            DailyOptionSymbol::firstOrCreate(
                ['symbol' => $symbol],
                ['is_active' => true]
            );
        }

        $this->command->info('✅ DailyOptionSymbol seeder complete — ' . count(self::SYMBOLS) . ' symbols loaded.');
    }
}
