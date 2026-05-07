<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AngelInstrument extends Command
{
    protected $signature = 'angel_instrument:daily_update';
    protected $description = 'Fetch Angel Broking instrument list and store in DB';

    public function handle()
    {        
        set_time_limit(0);

        $today     = date('Y-m-d');
        $dayName   = date('l');

        // -----------------------------------------
        // 1️⃣ Skip Saturday & Sunday
        // -----------------------------------------
        // if (in_array($dayName, ['Saturday', 'Sunday'])) {
        //     $this->info("Skipped: Weekend ($dayName)");
        //     return 0;
        // }

        // -----------------------------------------
        // 2️⃣ Skip NSE Holidays from DB
        // -----------------------------------------
        $isHoliday = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $today)
            ->exists();

        // if ($isHoliday) {
        //     $this->info("Skipped: Market Holiday ($today)");
        //     return 0;
        // }

        $url = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';
        $this->info("Fetching data from Angel Broking...");

        // $response = Http::timeout(60)->get($url);
        try {
            $response = Http::timeout(60)->retry(3, 2000)->get($url);
        } catch (\Throwable $e) {
            Log::error("AngelInstrument API error: " . $e->getMessage());
            $this->error("API Error: " . $e->getMessage());
            return 1;
        }

        if ($response->failed()) {
            Log::error("AngelInstrument API returned invalid response.");
            $this->error("Failed to fetch data from API.");
            return 1;
        }

        $data = $response->json();

        if (!is_array($data) || empty($data)) {
            Log::error("AngelInstrument received empty/invalid JSON.");
            $this->error("Invalid JSON.");
            return 1;
        }

        $this->info("Records received: " . count($data));

        // ------------------------------------------------------
        // 4️⃣ TRUNCATE outside the transaction (IMPORTANT FIX)
        // ------------------------------------------------------
        DB::table('angel_api_instruments')->truncate();

        // ------------------------------------------------------
        // 5️⃣ Insert using transaction (safe)
        // ------------------------------------------------------
        DB::beginTransaction();

        try {
            $batchSize = 1000;
            $chunks = array_chunk($data, $batchSize);

            foreach ($chunks as $index => $chunk) {

                $insertRows = [];

                foreach ($chunk as $d) {

                    if (!isset($d['token']) || trim($d['token']) == "") {
                        continue;
                    }

                    // FIX: Extract expiry_raw from the data array first
                    $expiryRaw = $d['expiry'] ?? null;
                    $expiryDate = null;

                    if (!empty($expiryRaw)) {
                        try {
                            $expiryDate = \Carbon\Carbon::createFromFormat(
                                'dMY',
                                strtoupper($expiryRaw)
                            )->format('Y-m-d');
                        } catch (\Exception $e) {
                            $expiryDate = null; // fallback safety
                        }
                    }

                    $insertRows[] = [
                        'token'          => $d['token'] ?? null,
                        'symbol_name'    => $d['symbol'] ?? null,
                        'name'           => $d['name'] ?? null,
                        'strike'         => $d['strike'] ?? null,
                        'lotsize'        => $d['lotsize'] ?? null,
                        'instrumenttype' => $d['instrumenttype'] ?? null,
                        'exch_seg'       => $d['exch_seg'] ?? null,
                        'expiry_raw'     => $expiryRaw,
                        'expiry'         => $expiryDate,
                        'tick_size'      => $d['tick_size'] ?? null,
                    ];
                }

                if (!empty($insertRows)) {
                    DB::table('angel_api_instruments')->insert($insertRows);
                }

                $this->info("Inserted batch " . ($index + 1) . " of " . count($chunks));
            }

            DB::commit();
            $this->info("✔ Angel Instrument Update Completed Successfully!");

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error("AngelInstrument DB error: " . $e->getMessage());
            $this->error("DB Error: " . $e->getMessage());

            return 1;
        }

        return 0;
    }
}