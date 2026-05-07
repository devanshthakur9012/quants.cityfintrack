<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MutualFund;
use App\Models\MutualFundStock;
use Illuminate\Support\Facades\DB;

class SeedMutualFundHoldings extends Command
{
    protected $signature   = 'mutual_fund:seed_holdings
                                {--date=2025-03-01 : Holding snapshot date (YYYY-MM-DD)}
                                {--force           : Re-insert even if already seeded}';

    protected $description = 'Seed mutual fund list and their stock holdings (Parag Parikh, Motilal Oswal, Nippon, SBI)';

    public function handle(): int
    {
        $holdingDate = $this->option('date');
        $force       = $this->option('force');

        $this->info("Seeding mutual fund holdings for date: {$holdingDate}");

        // ─── 1. Funds ──────────────────────────────────────────────────────────
        $funds = [
            [
                'name'      => 'Parag Parikh Flexi Cap Fund Direct Growth',
                'code'      => 'PPFAS',
                'category'  => 'Flexi Cap',
                'amc'       => 'PPFAS Mutual Fund',
                'plan_type' => 'Direct',
                'option'    => 'Growth',
                'status'    => true,
            ],
            [
                'name'      => 'Motilal Oswal Midcap Fund Direct Growth',
                'code'      => 'MOAMC',
                'category'  => 'Mid Cap',
                'amc'       => 'Motilal Oswal Mutual Fund',
                'plan_type' => 'Direct',
                'option'    => 'Growth',
                'status'    => true,
            ],
            [
                'name'      => 'Nippon India Small Cap Fund Direct Growth',
                'code'      => 'NIPPON',
                'category'  => 'Small Cap',
                'amc'       => 'Nippon India Mutual Fund',
                'plan_type' => 'Direct',
                'option'    => 'Growth',
                'status'    => true,
            ],
            [
                'name'      => 'SBI Small Cap Fund Direct Growth',
                'code'      => 'SBI',
                'category'  => 'Small Cap',
                'amc'       => 'SBI Mutual Fund',
                'plan_type' => 'Direct',
                'option'    => 'Growth',
                'status'    => true,
            ],
        ];

        foreach ($funds as &$fund) {
            $existing = MutualFund::where('code', $fund['code'])->first();
            if (! $existing) {
                $existing = MutualFund::create($fund);
                $this->line("  ✅ Created fund: {$fund['name']}");
            } else {
                $this->line("  ⏭  Fund exists: {$fund['name']}");
            }
            $fund['_id'] = $existing->id;
        }
        unset($fund);

        $fundMap = collect($funds)->keyBy('code');

        // ─── 2. Stock Holdings ─────────────────────────────────────────────────
        // Data sourced from Groww screenshots (March 2025 holdings)

        $holdings = [

            // ── Parag Parikh Flexi Cap Fund ──────────────────────────────────
            'PPFAS' => [
                ['stock_name' => 'HDFC Bank Ltd',                     'stock_symbol' => 'HDFCBANK',      'sector' => 'Financial Services', 'allocation_percentage' => 8.20],
                ['stock_name' => 'Bajaj Holdings & Investment Ltd',   'stock_symbol' => 'BAJAJHLDNG',    'sector' => 'Financial Services', 'allocation_percentage' => 6.60],
                ['stock_name' => 'Coal India Ltd',                    'stock_symbol' => 'COALINDIA',     'sector' => 'Energy',             'allocation_percentage' => 5.20],
                ['stock_name' => 'Power Grid Corp Of India Ltd',      'stock_symbol' => 'POWERGRID',     'sector' => 'Utilities',          'allocation_percentage' => 5.00],
                ['stock_name' => 'Maruti Suzuki India Ltd',           'stock_symbol' => 'MARUTI',        'sector' => 'Automobile',         'allocation_percentage' => 4.60],
                ['stock_name' => 'ITC Ltd',                           'stock_symbol' => 'ITC',           'sector' => 'FMCG',               'allocation_percentage' => 4.40],
                ['stock_name' => 'HCL Technologies Ltd',              'stock_symbol' => 'HCLTECH',       'sector' => 'IT',                 'allocation_percentage' => 4.20],
                ['stock_name' => 'Alphabet Inc',                      'stock_symbol' => 'GOOGL',         'sector' => 'IT',                 'allocation_percentage' => 4.00],
                ['stock_name' => 'Microsoft Corp',                    'stock_symbol' => 'MSFT',          'sector' => 'IT',                 'allocation_percentage' => 3.80],
                ['stock_name' => 'Amazon.com Inc',                    'stock_symbol' => 'AMZN',          'sector' => 'Consumer Discretionary', 'allocation_percentage' => 2.80],
                ['stock_name' => 'Axis Bank Ltd',                     'stock_symbol' => 'AXISBANK',      'sector' => 'Financial Services', 'allocation_percentage' => 2.70],
                ['stock_name' => 'Cipla Ltd',                         'stock_symbol' => 'CIPLA',         'sector' => 'Pharma',             'allocation_percentage' => 2.50],
                ['stock_name' => 'ICICI Bank Ltd',                    'stock_symbol' => 'ICICIBANK',     'sector' => 'Financial Services', 'allocation_percentage' => 2.40],
                ['stock_name' => 'Zydus Lifesciences Ltd',            'stock_symbol' => 'ZYDUSLIFE',     'sector' => 'Pharma',             'allocation_percentage' => 2.30],
                ['stock_name' => 'Indian Railway Catering & Tourism', 'stock_symbol' => 'IRCTC',         'sector' => 'Services',           'allocation_percentage' => 2.10],
                ['stock_name' => 'Meta Platforms Inc',                'stock_symbol' => 'META',          'sector' => 'IT',                 'allocation_percentage' => 2.00],
                ['stock_name' => 'Facebook Inc (International)',      'stock_symbol' => 'META_INTL',     'sector' => 'IT',                 'allocation_percentage' => 1.80],
                ['stock_name' => 'Sona BLW Precision Forgings Ltd',  'stock_symbol' => 'SONACOMS',      'sector' => 'Automobile',         'allocation_percentage' => 1.70],
            ],

            // ── Motilal Oswal Midcap Fund ────────────────────────────────────
            'MOAMC' => [
                ['stock_name' => 'Voltas Ltd',                        'stock_symbol' => 'VOLTAS',        'sector' => 'Consumer Durables',  'allocation_percentage' => 7.40],
                ['stock_name' => 'Coforge Ltd',                       'stock_symbol' => 'COFORGE',       'sector' => 'IT',                 'allocation_percentage' => 6.90],
                ['stock_name' => 'Persistent Systems Ltd',            'stock_symbol' => 'PERSISTENT',    'sector' => 'IT',                 'allocation_percentage' => 6.30],
                ['stock_name' => 'Phoenix Mills Ltd',                 'stock_symbol' => 'PHOENIXLTD',    'sector' => 'Realty',             'allocation_percentage' => 5.20],
                ['stock_name' => 'Polycab India Ltd',                 'stock_symbol' => 'POLYCAB',       'sector' => 'Capital Goods',      'allocation_percentage' => 5.10],
                ['stock_name' => 'Kalyan Jewellers India Ltd',        'stock_symbol' => 'KALYANKJIL',    'sector' => 'Consumer Discretionary', 'allocation_percentage' => 5.00],
                ['stock_name' => 'Zomato Ltd',                        'stock_symbol' => 'ZOMATO',        'sector' => 'Services',           'allocation_percentage' => 4.90],
                ['stock_name' => 'Mankind Pharma Ltd',                'stock_symbol' => 'MANKIND',       'sector' => 'Pharma',             'allocation_percentage' => 4.70],
                ['stock_name' => 'Dixon Technologies India Ltd',      'stock_symbol' => 'DIXON',         'sector' => 'Consumer Durables',  'allocation_percentage' => 4.50],
                ['stock_name' => 'Tube Investments of India Ltd',     'stock_symbol' => 'TIINDIA',       'sector' => 'Capital Goods',      'allocation_percentage' => 4.30],
                ['stock_name' => 'Supreme Industries Ltd',            'stock_symbol' => 'SUPREMEIND',    'sector' => 'Capital Goods',      'allocation_percentage' => 4.20],
                ['stock_name' => 'Cummins India Ltd',                 'stock_symbol' => 'CUMMINSIND',    'sector' => 'Capital Goods',      'allocation_percentage' => 3.90],
                ['stock_name' => 'KPIT Technologies Ltd',             'stock_symbol' => 'KPITTECH',      'sector' => 'IT',                 'allocation_percentage' => 3.80],
                ['stock_name' => 'The Indian Hotels Co Ltd',          'stock_symbol' => 'INDHOTEL',      'sector' => 'Services',           'allocation_percentage' => 3.60],
                ['stock_name' => 'Bharat Forge Ltd',                  'stock_symbol' => 'BHARATFORG',    'sector' => 'Automobile',         'allocation_percentage' => 3.40],
                ['stock_name' => 'City Union Bank Ltd',               'stock_symbol' => 'CUB',           'sector' => 'Financial Services', 'allocation_percentage' => 2.50],
                ['stock_name' => 'Sundaram Finance Ltd',              'stock_symbol' => 'SUNDARMFIN',    'sector' => 'Financial Services', 'allocation_percentage' => 2.30],
                ['stock_name' => 'P I Industries Ltd',                'stock_symbol' => 'PIIND',         'sector' => 'Chemicals',          'allocation_percentage' => 2.20],
                ['stock_name' => 'Alkem Laboratories Ltd',            'stock_symbol' => 'ALKEM',         'sector' => 'Pharma',             'allocation_percentage' => 2.10],
                ['stock_name' => 'AU Small Finance Bank Ltd',         'stock_symbol' => 'AUBANK',        'sector' => 'Financial Services', 'allocation_percentage' => 2.00],
            ],

            // ── Nippon India Small Cap Fund ──────────────────────────────────
            'NIPPON' => [
                ['stock_name' => 'HDFC Bank Ltd',                     'stock_symbol' => 'HDFCBANK',      'sector' => 'Financial Services', 'allocation_percentage' => 3.80],
                ['stock_name' => 'Tube Investments of India Ltd',     'stock_symbol' => 'TIINDIA',       'sector' => 'Capital Goods',      'allocation_percentage' => 2.90],
                ['stock_name' => 'Kirloskar Brothers Ltd',            'stock_symbol' => 'KIRLOSBROS',    'sector' => 'Capital Goods',      'allocation_percentage' => 2.40],
                ['stock_name' => 'Apar Industries Ltd',               'stock_symbol' => 'APARINDS',      'sector' => 'Capital Goods',      'allocation_percentage' => 2.20],
                ['stock_name' => 'Multi Commodity Exchange Of India', 'stock_symbol' => 'MCX',           'sector' => 'Financial Services', 'allocation_percentage' => 2.10],
                ['stock_name' => 'Bharat Heavy Electricals Ltd',      'stock_symbol' => 'BHEL',          'sector' => 'Capital Goods',      'allocation_percentage' => 2.00],
                ['stock_name' => 'Karur Vysya Bank Ltd',              'stock_symbol' => 'KARURVYSYA',    'sector' => 'Financial Services', 'allocation_percentage' => 1.90],
                ['stock_name' => 'Techno Electric & Engineering',     'stock_symbol' => 'TECHNOE',       'sector' => 'Capital Goods',      'allocation_percentage' => 1.80],
                ['stock_name' => 'NLC India Ltd',                     'stock_symbol' => 'NLCINDIA',      'sector' => 'Energy',             'allocation_percentage' => 1.70],
                ['stock_name' => 'Tejas Networks Ltd',                'stock_symbol' => 'TEJASNET',      'sector' => 'Telecom',            'allocation_percentage' => 1.60],
                ['stock_name' => 'KPIT Technologies Ltd',             'stock_symbol' => 'KPITTECH',      'sector' => 'IT',                 'allocation_percentage' => 1.60],
                ['stock_name' => 'Bharat Electronics Ltd',            'stock_symbol' => 'BEL',           'sector' => 'Capital Goods',      'allocation_percentage' => 1.50],
                ['stock_name' => 'Ratnamani Metals & Tubes Ltd',      'stock_symbol' => 'RATNAMANI',     'sector' => 'Capital Goods',      'allocation_percentage' => 1.50],
                ['stock_name' => 'CAMS (Computer Age Mgmt Services)', 'stock_symbol' => 'CAMS',          'sector' => 'Financial Services', 'allocation_percentage' => 1.40],
                ['stock_name' => 'Suven Pharmaceuticals Ltd',         'stock_symbol' => 'SUVENPHARMA',   'sector' => 'Pharma',             'allocation_percentage' => 1.40],
                ['stock_name' => 'Navin Fluorine International Ltd',  'stock_symbol' => 'NAVINFLUOR',    'sector' => 'Chemicals',          'allocation_percentage' => 1.30],
                ['stock_name' => 'Shyam Metalics And Energy Ltd',     'stock_symbol' => 'SHYAMMETL',     'sector' => 'Metals',             'allocation_percentage' => 1.30],
                ['stock_name' => 'Neuland Laboratories Ltd',          'stock_symbol' => 'NEULANDLAB',    'sector' => 'Pharma',             'allocation_percentage' => 1.20],
                ['stock_name' => 'ERIS Lifesciences Ltd',             'stock_symbol' => 'ERIS',          'sector' => 'Pharma',             'allocation_percentage' => 1.20],
                ['stock_name' => 'Finolex Cables Ltd',                'stock_symbol' => 'FINCABLES',     'sector' => 'Capital Goods',      'allocation_percentage' => 1.10],
            ],

            // ── SBI Small Cap Fund ───────────────────────────────────────────
            'SBI' => [
                ['stock_name' => 'Techno Electric & Engineering',     'stock_symbol' => 'TECHNOE',       'sector' => 'Capital Goods',      'allocation_percentage' => 4.10],
                ['stock_name' => 'Blue Star Ltd',                     'stock_symbol' => 'BLUESTARCO',    'sector' => 'Consumer Durables',  'allocation_percentage' => 3.80],
                ['stock_name' => 'Carborundum Universal Ltd',         'stock_symbol' => 'CARBORUNIV',    'sector' => 'Capital Goods',      'allocation_percentage' => 3.60],
                ['stock_name' => 'Hawkins Cookers Ltd',               'stock_symbol' => 'HAWKINCOOK',    'sector' => 'Consumer Durables',  'allocation_percentage' => 3.50],
                ['stock_name' => 'Kalpataru Projects International',  'stock_symbol' => 'KPIL',          'sector' => 'Construction',       'allocation_percentage' => 3.40],
                ['stock_name' => 'Craftsman Automation Ltd',          'stock_symbol' => 'CRAFTSMAN',     'sector' => 'Capital Goods',      'allocation_percentage' => 3.20],
                ['stock_name' => 'V-Guard Industries Ltd',            'stock_symbol' => 'VGUARD',        'sector' => 'Consumer Durables',  'allocation_percentage' => 3.10],
                ['stock_name' => 'Ahluwalia Contracts India Ltd',     'stock_symbol' => 'AHLUCONT',      'sector' => 'Construction',       'allocation_percentage' => 3.00],
                ['stock_name' => 'Finolex Industries Ltd',            'stock_symbol' => 'FINPIPE',       'sector' => 'Capital Goods',      'allocation_percentage' => 2.90],
                ['stock_name' => 'Repco Home Finance Ltd',            'stock_symbol' => 'REPCOHOME',     'sector' => 'Financial Services', 'allocation_percentage' => 2.80],
                ['stock_name' => 'Sheela Foam Ltd',                   'stock_symbol' => 'SFL',           'sector' => 'Consumer Discretionary', 'allocation_percentage' => 2.70],
                ['stock_name' => 'Safari Industries India Ltd',       'stock_symbol' => 'SAFARI',        'sector' => 'Consumer Discretionary', 'allocation_percentage' => 2.60],
                ['stock_name' => 'Essel Propack Ltd',                 'stock_symbol' => 'ESSELPACK',     'sector' => 'Capital Goods',      'allocation_percentage' => 2.50],
                ['stock_name' => 'KNR Constructions Ltd',             'stock_symbol' => 'KNRCON',        'sector' => 'Construction',       'allocation_percentage' => 2.40],
                ['stock_name' => 'Sanghvi Movers Ltd',                'stock_symbol' => 'SANGHVIMOV',    'sector' => 'Services',           'allocation_percentage' => 2.30],
                ['stock_name' => 'Garware Technical Fibres Ltd',      'stock_symbol' => 'GARFIBRES',     'sector' => 'Textiles',           'allocation_percentage' => 2.20],
                ['stock_name' => 'PNC Infratech Ltd',                 'stock_symbol' => 'PNCINFRA',      'sector' => 'Construction',       'allocation_percentage' => 2.10],
                ['stock_name' => 'RACL Geartech Ltd',                 'stock_symbol' => 'RACLGEAR',      'sector' => 'Automobile',         'allocation_percentage' => 1.90],
                ['stock_name' => 'Sharda Cropchem Ltd',               'stock_symbol' => 'SHARDACROP',    'sector' => 'Chemicals',          'allocation_percentage' => 1.80],
                ['stock_name' => 'Chalet Hotels Ltd',                 'stock_symbol' => 'CHALET',        'sector' => 'Services',           'allocation_percentage' => 1.70],
            ],
        ];

        // ─── 3. Insert / Update Holdings ──────────────────────────────────────
        $totalInserted = 0;
        $totalSkipped  = 0;

        foreach ($holdings as $code => $stocks) {
            $fundId = $fundMap[$code]['_id'] ?? null;

            if (! $fundId) {
                $this->warn("  ⚠️  Fund not found for code: {$code}");
                continue;
            }

            foreach ($stocks as $stock) {
                $stock['mutual_fund_id'] = $fundId;
                $stock['holding_date']   = $holdingDate;
                $stock['status']         = true;

                $exists = MutualFundStock::where('mutual_fund_id', $fundId)
                    ->where('stock_symbol', $stock['stock_symbol'])
                    ->whereDate('holding_date', $holdingDate)
                    ->exists();

                if ($exists && ! $force) {
                    $totalSkipped++;
                    continue;
                }

                MutualFundStock::updateOrCreate(
                    [
                        'mutual_fund_id' => $fundId,
                        'stock_symbol'   => $stock['stock_symbol'],
                        'holding_date'   => $holdingDate,
                    ],
                    $stock
                );

                $totalInserted++;
            }

            $this->line("  ✅ Holdings inserted for: {$code}");
        }

        $this->info("✅ Done! Inserted: {$totalInserted} | Skipped: {$totalSkipped}");
        return 0;
    }
}