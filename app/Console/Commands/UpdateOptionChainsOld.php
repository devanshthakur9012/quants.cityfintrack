<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OptionsChain;
use App\Traits\AngelApiAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpdateOptionChainsOld extends Command
{
    use AngelApiAuth;

    protected $signature = 'options:update-historical-old';
    protected $description = 'Fetch historical & OI data for all CE/PE/Future in options chain';

    public function handle()
    {
        $chains = OptionsChain::select(
            'id', 'underlying',
            'ce_symbol', 'ce_token', 'ce_exch_seg',
            'pe_symbol', 'pe_token', 'pe_exch_seg',
            'future_symbol', 'future_token'
        )->get();

        foreach ($chains as $chain) {
            $this->info("🔄 Processing Option Chain ID: {$chain->id} ({$chain->underlying})");

            $date = Carbon::createFromFormat('Y-m-d', Carbon::now()->format('Y-m') . '-21')->format('Y-m-d');

            /**
             * ✅ 1. Fetch CE Data (Cache → DB → API)
             */
            $ceCacheKey = "historical_options:ce:{$chain->ce_token}:{$date}";
            $ceData = Cache::remember($ceCacheKey, 900, function () use ($chain, $date) {
                return DB::table('historical_options_data')
                    ->where('ce_token', $chain->ce_token)
                    ->where('date', $date)
                    ->first();
            });

            if ($ceData) {
                $this->info("⏩ CE data loaded from cache/DB for {$chain->underlying}");
                $ceData = (array) $ceData;
            } else {
                $ceData = $this->fetchHistoricalAndOI($chain->ce_token, $date, $chain->ce_exch_seg);
                Cache::put($ceCacheKey, $ceData, 900);
                // sleep(1);
            }

            /**
             * ✅ 2. Fetch PE Data (Cache → DB → API)
             */
            $peCacheKey = "historical_options:pe:{$chain->pe_token}:{$date}";
            $peData = Cache::remember($peCacheKey, 900, function () use ($chain, $date) {
                return DB::table('historical_options_data')
                    ->where('pe_token', $chain->pe_token)
                    ->where('date', $date)
                    ->first();
            });

            if ($peData) {
                $this->info("⏩ PE data loaded from cache/DB for {$chain->underlying}");
                $peData = (array) $peData;
            } else {
                $peData = $this->fetchHistoricalAndOI($chain->pe_token, $date, $chain->pe_exch_seg);
                Cache::put($peCacheKey, $peData, 900);
                // sleep(1);
            }

            /**
             * ✅ 3. Fetch Future Data (Cache → DB → API)
             */
            $futCacheKey = "historical_options:future:{$chain->future_token}:{$date}";
            $futData = Cache::remember($futCacheKey, 900, function () use ($chain, $date) {
                return DB::table('historical_options_data')
                    ->where('future_token', $chain->future_token)
                    ->where('date', $date)
                    ->first();
            });

            if ($futData) {
                $this->info("⏩ Future data loaded from cache/DB for {$chain->underlying}");
                $futData = (array) $futData;
            } else {
                $futData = $this->fetchHistoricalAndOI($chain->future_token, $date, 'NFO');
                Cache::put($futCacheKey, $futData, 900);
                // sleep(1);
            }

            /**
             * ✅ 5. Insert fresh data
             */
            DB::table('historical_options_data')->insert([
                'underlying'  => $chain->underlying,
                'date'        => $date,

                // CE fields
                'ce_symbol' => $chain->ce_symbol,
                'ce_token' => $chain->ce_token,
                'ce_open' => $ceData['ce_open'] ?? $ceData['open'] ?? null,
                'ce_high' => $ceData['ce_high'] ?? $ceData['high'] ?? null,
                'ce_low' => $ceData['ce_low'] ?? $ceData['low'] ?? null,
                'ce_close' => $ceData['ce_close'] ?? $ceData['close'] ?? null,
                'ce_volume' => $ceData['ce_volume'] ?? $ceData['volume'] ?? null,
                'ce_oi' => $ceData['ce_oi'] ?? $ceData['oi'] ?? null,

                // PE fields
                'pe_symbol' => $chain->pe_symbol,
                'pe_token' => $chain->pe_token,
                'pe_open' => $peData['pe_open'] ?? $peData['open'] ?? null,
                'pe_high' => $peData['pe_high'] ?? $peData['high'] ?? null,
                'pe_low' => $peData['pe_low'] ?? $peData['low'] ?? null,
                'pe_close' => $peData['pe_close'] ?? $peData['close'] ?? null,
                'pe_volume' => $peData['pe_volume'] ?? $peData['volume'] ?? null,
                'pe_oi' => $peData['pe_oi'] ?? $peData['oi'] ?? null,

                // Future fields
                'future_symbol' => $chain->future_symbol,
                'future_token' => $chain->future_token,
                'future_open' => $futData['future_open'] ?? $futData['open'] ?? null,
                'future_high' => $futData['future_high'] ?? $futData['high'] ?? null,
                'future_low' => $futData['future_low'] ?? $futData['low'] ?? null,
                'future_close' => $futData['future_close'] ?? $futData['close'] ?? null,
                'future_volume' => $futData['future_volume'] ?? $futData['volume'] ?? null,
                'future_oi' => $futData['future_oi'] ?? $futData['oi'] ?? null,
            ]);

            $this->info("✅ Saved new data for {$chain->underlying}");
            sleep(2); // Small delay before next underlying
        }

        $this->info('🎉 Option Chains updated successfully!');
    }
}