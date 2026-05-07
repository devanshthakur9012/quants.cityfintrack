<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OptionsChain;
use App\Traits\AngelApiAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpdateOptionChains extends Command
{
    use AngelApiAuth;

    protected $signature = 'options:update-historical';
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

            $date = Carbon::createFromFormat('Y-m-d', Carbon::now()->format('Y-m') . '-26')->format('Y-m-d');

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
                sleep(1);
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
                sleep(1);
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
                sleep(1);
            }

            // ✅ Get previous day for % change calculation
            // $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
            $FutprevData = DB::table('historical_options_data')
            ->where('future_symbol', $chain->future_symbol)
            ->latest()
            ->first();
            
            $CEprevData = DB::table('historical_options_data')
            ->where('ce_symbol', $chain->ce_symbol)
            ->latest()
            ->first();
            
            $PEprevData = DB::table('historical_options_data')
            ->where('pe_symbol', $chain->pe_symbol)
            ->latest()
            ->first();

            // FOR FUTURE
            $fut_price_previous = $FutprevData->future_close ?? null;
            $fut_price_current = $futData['future_close'] ?? $futData['close'] ?? null;

            $fut_oi_previous = $FutprevData->future_oi ?? null;
            $fut_oi_current = $futData['future_oi'] ?? $futData['oi'] ?? null;

            // FOR CE
            $ce_price_previous = $CEprevData->ce_close ?? null;
            $ce_price_current = $ceData['ce_close'] ?? $ceData['close'] ?? null;

            $ce_oi_previous = $CEprevData->ce_oi ?? null;
            $ce_oi_current = $ceData['ce_oi'] ?? $ceData['oi'] ?? null;

            // FOR PE
            $pe_price_previous = $PEprevData->pe_close ?? null;
            $pe_price_current = $peData['pe_close'] ?? $peData['close'] ?? null;

            $pe_oi_previous = $PEprevData->pe_oi ?? null;
            $pe_oi_current = $peData['pe_oi'] ?? $peData['oi'] ?? null;


            // ✅ Calculate Price Change & OI Change
            $futurePriceChange = $this->calculateChange($fut_price_previous, $fut_price_current);
            $futureOIChange    = $this->calculateChange($fut_oi_previous, $fut_oi_current);

            $cePriceChange     = $this->calculateChange($ce_price_previous, $ce_price_current);
            $ceOIChange        = $this->calculateChange($ce_oi_previous, $ce_oi_current);

            $pePriceChange     = $this->calculateChange($pe_price_previous, $pe_price_current);
            $peOIChange        = $this->calculateChange($pe_oi_previous, $pe_oi_current);

            // ✅ Determine Trend
            $trendResult = $this->getTrend(
                $futurePriceChange,
                $futureOIChange,
                $ceOIChange,
                $peOIChange
            );

            $ceData  = (array) $ceData;
            $peData  = (array) $peData;

            $allCeNull = empty($ceData['ce_open'] ?? $ceData['open'])
                && empty($ceData['ce_high'] ?? $ceData['high'])
                && empty($ceData['ce_low'] ?? $ceData['low'])
                && empty($ceData['ce_close'] ?? $ceData['close'])
                && empty($ceData['ce_volume'] ?? $ceData['volume'])
                && empty($ceData['ce_oi'] ?? $ceData['oi']);

            $allPeNull = empty($peData['pe_open'] ?? $peData['open'])
                && empty($peData['pe_high'] ?? $peData['high'])
                && empty($peData['pe_low'] ?? $peData['low'])
                && empty($peData['pe_close'] ?? $peData['close'])
                && empty($peData['pe_volume'] ?? $peData['volume'])
                && empty($peData['pe_oi'] ?? $peData['oi']);

            // ✅ If ALL data is null → skip insert
            if ($allCeNull && $allPeNull) {
                $this->warn("⚠️ Skipping insert for {$chain->underlying} — all CE/PE data is NULL.");
                continue;
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
                'ce_price_change' => $cePriceChange,
                'ce_oi_change' => $ceOIChange,

                // PE fields
                'pe_symbol' => $chain->pe_symbol,
                'pe_token' => $chain->pe_token,
                'pe_open' => $peData['pe_open'] ?? $peData['open'] ?? null,
                'pe_high' => $peData['pe_high'] ?? $peData['high'] ?? null,
                'pe_low' => $peData['pe_low'] ?? $peData['low'] ?? null,
                'pe_close' => $peData['pe_close'] ?? $peData['close'] ?? null,
                'pe_volume' => $peData['pe_volume'] ?? $peData['volume'] ?? null,
                'pe_oi' => $peData['pe_oi'] ?? $peData['oi'] ?? null,
                'pe_price_change' => $pePriceChange,
                'pe_oi_change' => $peOIChange,

                // Future fields
                'future_symbol' => $chain->future_symbol,
                'future_token' => $chain->future_token,
                'future_open' => $futData['future_open'] ?? $futData['open'] ?? null,
                'future_high' => $futData['future_high'] ?? $futData['high'] ?? null,
                'future_low' => $futData['future_low'] ?? $futData['low'] ?? null,
                'future_close' => $futData['future_close'] ?? $futData['close'] ?? null,
                'future_volume' => $futData['future_volume'] ?? $futData['volume'] ?? null,
                'future_oi' => $futData['future_oi'] ?? $futData['oi'] ?? null,
                'future_price_change' => $futurePriceChange,
                'future_oi_change' => $futureOIChange,

                
                // Trend analysis
                'trend' => $trendResult['Trend'],
                'futures_score' => $trendResult['FuturesScore'],
                'final_score' => $trendResult['FinalScore'],
                'options_score' => $trendResult['OptionsScore'],
            ]);

            $this->info("✅ Saved new data for {$chain->underlying}");
            sleep(1); // Small delay before next underlying
        }

        $this->info('🎉 Option Chains updated successfully!');
    }

    private function fetchData($type, $token, $exch, $date)
    {
        $cacheKey = "historical_options:{$type}:{$token}:{$date}";

        return Cache::remember($cacheKey, 900, function () use ($token, $exch, $date, $type, $cacheKey) {
            $data = DB::table('historical_options_data')
                ->where("{$type}_token", $token)
                ->where('date', $date)
                ->first();

            if ($data) {
                return (array) $data;
            }

            $apiData = $this->fetchHistoricalAndOI($token, $date, $exch);
            Cache::put($cacheKey, $apiData, 900);

            return $apiData;
        });
    }

    private function calculateChange($previous, $current)
    {
        if (is_null($previous) || is_null($current)) {
            return null;
        }
        return round($current - $previous, 2);
    }

    private function getTrend($futurePriceChange, $futureOIChange, $ceOIChange, $peOIChange)
    {
        $futuresScore = 0;
        $optionsScore = 0;
        $trend = "";

        // Futures Logic
        if ($futurePriceChange > 0 && $futureOIChange > 0) {
            $futuresScore = 2;
        } elseif ($futurePriceChange < 0 && $futureOIChange > 0) {
            $futuresScore = -2;
        } elseif ($futurePriceChange > 0 && $futureOIChange < 0) {
            $futuresScore = 1;
        } elseif ($futurePriceChange < 0 && $futureOIChange < 0) {
            $futuresScore = -1;
        }

        // Options Confirmation
        if ($ceOIChange > 0 && $peOIChange < 0) {
            $optionsScore = 1;
        } elseif ($ceOIChange < 0 && $peOIChange > 0) {
            $optionsScore = -1;
        }

        $finalScore = $futuresScore + $optionsScore;

        if ($finalScore >= 2) {
            $trend = "Strong Bullish";
        } elseif ($finalScore == 1) {
            $trend = "Mild Bullish";
        } elseif ($finalScore == 0) {
            $trend = "Neutral / Sideways";
        } elseif ($finalScore == -1) {
            $trend = "Mild Bearish";
        } elseif ($finalScore <= -2) {
            $trend = "Strong Bearish";
        }

        return [
            "FuturesScore" => $futuresScore,
            "OptionsScore" => $optionsScore,
            "FinalScore" => $finalScore,
            "Trend" => $trend
        ];
    }
}