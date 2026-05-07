<?php

namespace Database\Seeders;

use App\Models\OptionSymbol;
use Illuminate\Database\Seeder;

class OptionSymbolSeeder extends Seeder
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
    ];

    public function run(): void
    {
        foreach (self::SYMBOLS as $symbol) {
            OptionSymbol::firstOrCreate(
                ['symbol' => $symbol],
                ['is_active' => true]
            );
        }

        $this->command->info('✅ OptionSymbol seeder complete — ' . count(self::SYMBOLS) . ' symbols loaded.');
    }
}