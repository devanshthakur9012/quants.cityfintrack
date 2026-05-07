<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\McxSymbol;

/**
 * McxSymbolSeeder — corrected from actual zerodha_instruments DB data
 *
 * Verified from DB:
 *   name column values: CRUDEOIL, CRUDEOILM, NATURALGAS, NATGASMINI,
 *                       GOLD, GOLDM, SILVER, SILVERM,
 *                       COPPER, ALUMINIUM, ALUMINI, ZINC, LEAD, NICKEL
 *
 *   Strike intervals verified from actual strike values in DB:
 *   ZINC        → 2.5   (DB: 277.5, 280, 282.5 ...)
 *   COPPER      → 5     (verify from your data)
 *   CRUDEOIL    → 50    (DB: 3850, 3900, 3950 ...)
 *   NATURALGAS  → 10    (verify)
 *   GOLD        → 100   (verify)
 *   SILVER      → 100   (verify)
 */
class McxSymbolSeeder extends Seeder
{
    public function run(): void
    {
        $symbols = [
            // ── Energy ────────────────────────────────────────────────────────
            // name='CRUDEOIL'  in zerodha_instruments, strike step=50 (3850,3900,3950)
            ['symbol' => 'CRUDEOIL',   'exchange' => 'MCX', 'strike_interval' => 50,  'unit' => 'BBL',   'is_active' => true],
            // name='CRUDEOILM' in zerodha_instruments
            ['symbol' => 'CRUDEOILM',  'exchange' => 'MCX', 'strike_interval' => 50,  'unit' => 'BBL',   'is_active' => true],
            // name='NATURALGAS' in zerodha_instruments (NOT 'NATURALGASM')
            ['symbol' => 'NATURALGAS', 'exchange' => 'MCX', 'strike_interval' => 10,  'unit' => 'MMBTU', 'is_active' => true],
            // name='NATGASMINI' in zerodha_instruments (NOT 'NATURALGASM')
            ['symbol' => 'NATGASMINI', 'exchange' => 'MCX', 'strike_interval' => 5,   'unit' => 'MMBTU', 'is_active' => true],

            // ── Precious Metals ───────────────────────────────────────────────
            // name='GOLD' in zerodha_instruments
            ['symbol' => 'GOLD',       'exchange' => 'MCX', 'strike_interval' => 100, 'unit' => 'KG',    'is_active' => true],
            // name='GOLDM' in zerodha_instruments
            ['symbol' => 'GOLDM',      'exchange' => 'MCX', 'strike_interval' => 100, 'unit' => 'GRAM',  'is_active' => true],
            // name='SILVER' in zerodha_instruments
            ['symbol' => 'SILVER',     'exchange' => 'MCX', 'strike_interval' => 100, 'unit' => 'KG',    'is_active' => true],
            // name='SILVERM' in zerodha_instruments
            ['symbol' => 'SILVERM',    'exchange' => 'MCX', 'strike_interval' => 100, 'unit' => 'KG',    'is_active' => true],

            // ── Base Metals ───────────────────────────────────────────────────
            // name='COPPER' in zerodha_instruments
            ['symbol' => 'COPPER',     'exchange' => 'MCX', 'strike_interval' => 5,   'unit' => 'KG',    'is_active' => true],
            // name='ALUMINIUM' in zerodha_instruments
            ['symbol' => 'ALUMINIUM',  'exchange' => 'MCX', 'strike_interval' => 5,   'unit' => 'KG',    'is_active' => true],
            // name='ALUMINI' in zerodha_instruments (mini contract — separate from ALUMINIUM)
            ['symbol' => 'ALUMINI',    'exchange' => 'MCX', 'strike_interval' => 5,   'unit' => 'KG',    'is_active' => true],
            // name='ZINC' in zerodha_instruments, strike step=2.5 (277.5, 280, 282.5 ...)
            ['symbol' => 'ZINC',       'exchange' => 'MCX', 'strike_interval' => 2.5, 'unit' => 'KG',    'is_active' => true],
            // name='LEAD' in zerodha_instruments
            ['symbol' => 'LEAD',       'exchange' => 'MCX', 'strike_interval' => 2.5, 'unit' => 'KG',    'is_active' => true],
            // name='NICKEL' in zerodha_instruments
            ['symbol' => 'NICKEL',     'exchange' => 'MCX', 'strike_interval' => 50,  'unit' => 'KG',    'is_active' => true],
        ];

        foreach ($symbols as $data) {
            McxSymbol::updateOrCreate(
                ['symbol' => $data['symbol']],
                $data
            );
        }

        $this->command->info('✅ MCX symbols seeded: ' . count($symbols) . ' records.');
        $this->command->newLine();
        $this->command->info('Symbol → zerodha_instruments name mapping:');
        $this->command->info('  NATURALGASM (old) → NATURALGAS  ✅ fixed');
        $this->command->info('  NATURALGASM (old) → NATGASMINI  ✅ fixed');
        $this->command->info('  ZINC strike_interval = 2.5       ✅ fixed (was 5)');
        $this->command->info('  ALUMINI added as separate symbol  ✅');
    }
}