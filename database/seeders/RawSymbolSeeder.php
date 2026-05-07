<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RawSymbolSeeder
 *
 * Seeds raw_symbols with 20 symbols:
 *   4  indices / index futures  (NIFTY, BANKNIFTY, SENSEX, FINNIFTY)
 *   16 blue-chip NSE F&O stocks (most liquid as of 2026)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * IMPORTANT — spot_instrument_token:
 *   These tokens are the KNOWN stable NSE EQ tokens from Zerodha's instrument
 *   file. However Zerodha CAN reassign tokens after corporate actions or when
 *   instruments are re-listed. Always verify AFTER running:
 *
 *     php artisan zerodha_instrument:insert
 *
 *   Then cross-check with:
 *     SELECT instrument_token, trading_symbol
 *     FROM zerodha_instruments
 *     WHERE name = 'RELIANCE' AND exchange = 'NSE' AND instrument_type = 'EQ';
 *
 *   And update this seeder or update the table directly if any token differs.
 *   Tokens marked NULL below are ones we could not confirm — set them manually.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * strikes_depth:
 *   → 5  = ATM±5 = 11 strikes × 2 types = 22 option instruments per expiry
 *   → 10 = ATM±10 = 21 strikes × 2 types = 42 option instruments per expiry
 *   Indices use depth=10 (more strike coverage needed due to fast moves).
 *   Stocks use depth=5 (tighter range, manageable API call count).
 *
 * Run:
 *   php artisan db:seed --class=RawSymbolSeeder
 */
class RawSymbolSeeder extends Seeder
{
    public function run(): void
    {
        $symbols = [

            // ══════════════════════════════════════════════════════════════════
            // INDICES  (collect_spot = index cash price, collect_futures = FUT,
            //           collect_options = CE/PE chain)
            // ══════════════════════════════════════════════════════════════════

            [
                // NSE Nifty 50 — most liquid index, weekly+monthly options
                'symbol'                => 'NIFTY',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 256265,   // NSE:NIFTY 50 index token — very stable
                'strikes_depth'         => 10,
                'status'                => 1,
            ],
            [
                // NSE Bank Nifty — second most liquid, monthly options only (weekly discontinued Nov 2024)
                'symbol'                => 'BANKNIFTY',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 260105,   // NSE:NIFTY BANK index token — very stable
                'strikes_depth'         => 10,
                'status'                => 1,
            ],
            [
                // BSE Sensex — weekly options (BFO exchange), growing liquidity
                'symbol'                => 'SENSEX',
                'exchange'              => 'BSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => null,     // ← verify: BSE SENSEX index token
                'strikes_depth'         => 10,
                'status'                => 1,
            ],
            [
                // NSE Fin Nifty — monthly options, growing F&O interest in 2025-26
                'symbol'                => 'FINNIFTY',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 257801,   // NSE:NIFTY FIN SERVICE index token
                'strikes_depth'         => 5,
                'status'                => 1,
            ],

            // ══════════════════════════════════════════════════════════════════
            // BLUE-CHIP F&O STOCKS — top 16 by liquidity / OI / Nifty weight
            // Sorted by approximate Nifty 50 weight / market cap as of 2026
            // ══════════════════════════════════════════════════════════════════

            [
                // Reliance Industries — #1 Nifty weight (~10%), most traded stock F&O
                'symbol'                => 'RELIANCE',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 738561,   // NSE:RELIANCE EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // HDFC Bank — #2 Nifty weight (~6.2%), largest private bank
                'symbol'                => 'HDFCBANK',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 341249,   // NSE:HDFCBANK EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Bharti Airtel — #3 Nifty weight (~6%), telecom leader
                'symbol'                => 'BHARTIARTL',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 2714625,  // NSE:BHARTIARTL EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // TCS — top IT stock, consistent F&O liquidity
                'symbol'                => 'TCS',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 2953217,  // NSE:TCS EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // ICICI Bank — #5 Nifty weight (~4.8%), most active bank stock in F&O
                'symbol'                => 'ICICIBANK',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 1270529,  // NSE:ICICIBANK EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Infosys — second largest IT, heavy F&O volume
                'symbol'                => 'INFY',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 408065,   // NSE:INFY EQ — confirmed via Kite docs
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // SBI — largest PSU bank, very high retail F&O participation
                'symbol'                => 'SBIN',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 779521,   // NSE:SBIN EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Bajaj Finance — high-beta NBFC, favourite for options traders
                'symbol'                => 'BAJFINANCE',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 81153,    // NSE:BAJFINANCE EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // HCL Tech — consistent IT F&O, rising Nifty weight
                'symbol'                => 'HCLTECH',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 1850625,  // NSE:HCLTECH EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                'symbol'                => 'TMPV',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => null,  // ← NEW listing, verify token
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                'symbol'                => 'TMCV',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => null,  // ← NEW listing, verify token
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Axis Bank — consistently in top 10 by F&O open interest
                'symbol'                => 'AXISBANK',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 1510401,  // NSE:AXISBANK EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Asian Paints — defensive blue chip, good options data
                'symbol'                => 'ASIANPAINT',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 60417,    // NSE:ASIANPAINT EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Dr Reddy's — pharma leader, consistent F&O volumes
                'symbol'                => 'DRREDDY',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 225537,   // NSE:DRREDDY EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Aurobindo Pharma — your primary stock, pharma F&O
                'symbol'                => 'AUROPHARMA',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => null,     // ← verify after zerodha_instrument:insert
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Cipla — pharma blue chip, active options chain
                'symbol'                => 'CIPLA',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 177665,   // NSE:CIPLA EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Adani Ports — infrastructure, heavy F&O participation
                'symbol'                => 'ADANIPORTS',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => 3861249,  // NSE:ADANIPORTS EQ
                'strikes_depth'         => 5,
                'status'                => 1,
            ],
            [
                // Wipro — IT sector, steady F&O volumes
                'symbol'                => 'WIPRO',
                'exchange'              => 'NSE',
                'collect_spot'          => true,
                'collect_futures'       => true,
                'collect_options'       => true,
                'spot_instrument_token' => null,     // ← verify after zerodha_instrument:insert
                'strikes_depth'         => 5,
                'status'                => 1,
            ],

        ];

        $inserted = 0;
        $updated  = 0;

        foreach ($symbols as $row) {
            $exists = DB::table('raw_symbols')->where('symbol', $row['symbol'])->exists();

            DB::table('raw_symbols')->updateOrInsert(
                ['symbol' => $row['symbol']],
                array_merge($row, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            $exists ? $updated++ : $inserted++;
        }

        $this->command->info("✅ RawSymbolSeeder done — {$inserted} inserted, {$updated} updated.");
        $this->command->newLine();
        $this->command->warn('⚠️  Verify spot_instrument_token for tokens marked NULL:');
        $this->command->line('   AUROPHARMA, SENSEX, WIPRO → run zerodha_instrument:insert then check zerodha_instruments table.');
        $this->command->newLine();
        $this->command->line('   Query to verify any token:');
        $this->command->line("   SELECT instrument_token, trading_symbol FROM zerodha_instruments");
        $this->command->line("   WHERE name = 'AUROPHARMA' AND exchange = 'NSE' AND instrument_type = 'EQ';");
    }
}