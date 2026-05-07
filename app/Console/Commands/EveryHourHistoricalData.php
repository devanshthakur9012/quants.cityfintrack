<?php

// namespace App\Console\Commands;

// use App\Models\HistoricalOneHour;
// use App\Models\OptionsChain;
// use App\Traits\AngelApiAuth;
// use Carbon\Carbon;
// use Illuminate\Console\Command;
// use Illuminate\Support\Facades\Cache;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;

// class EveryHourHistoricalData extends Command
// {
//     use AngelApiAuth;

//     protected $signature = 'options:historical-one-hour {--date= : Specific date to process (Y-m-d format)}';
//     protected $description = 'Fetch historical & OI data for all CE/PE/Future in options chain after market close';

//     private const CACHE_TTL = 900;
//     private const API_DELAY = 1;
//     private const DEFAULT_EXCHANGE = 'NFO';
//     private const INSTRUMENT_TYPES = ['future', 'ce', 'pe'];
    
//     public function handle(): int
//     {
//         $processDate = $this->getProcessDate();
//         $this->info("🚀 Starting options chain update for date: {$processDate}");

//         $chains = $this->getOptionsChains();
        
//         if ($chains->isEmpty()) {
//             $this->warn('No option chains found to process.');
//             return self::SUCCESS;
//         }

//         $successCount = 0;
//         $skipCount = 0;

//         foreach ($chains as $chain) {
//             try {
//                 $result = $this->processOptionChain($chain, $processDate);
                
//                 if ($result['success']) {
//                     $successCount++;
//                     $this->info("✅ Successfully processed: {$chain->underlying}");
//                 } else {
//                     $skipCount++;
//                     $this->warn("⚠️  Skipped: {$chain->underlying} - {$result['message']}");
//                 }
//             } catch (\Exception $e) {
//                 Log::error("Error processing chain {$chain->id}: " . $e->getMessage());
//                 $this->error("❌ Error processing {$chain->underlying}: " . $e->getMessage());
//                 continue;
//             }

//             $this->addDelay();
//         }

//         $this->info("🎉 Process completed! Success: {$successCount}, Skipped: {$skipCount}");
//         return self::SUCCESS;
//     }

//     private function getProcessDate(): string
//     {
//         if ($this->option('date')) {
//             return Carbon::createFromFormat('Y-m-d', $this->option('date'))->format('Y-m-d');
//         }
        
//         return Carbon::now()->format('Y-m-d');
//     }

//     private function getOptionsChains()
//     {
//         return OptionsChain::select([
//             'id', 'underlying',
//             'ce_symbol', 'ce_token', 'ce_exch_seg',
//             'pe_symbol', 'pe_token', 'pe_exch_seg',
//             'future_symbol', 'future_token'
//         ])->take(1)->get();
//     }

//     private function processOptionChain(OptionsChain $chain, string $date): array
//     {
//         $this->info("🔄 Processing: {$chain->underlying} (ID: {$chain->id})");

//         $instruments = $this->buildInstrumentList($chain);
        
//         if (empty($instruments)) {
//             return ['success' => false, 'message' => 'No valid instruments found'];
//         }

//         $currentData = $this->fetchAllInstrumentData($instruments, $date);
        
//         if ($this->isDataEmpty($currentData)) {
//             return ['success' => false, 'message' => 'All CE/PE data is empty'];
//         }

//         $previousData = $this->fetchPreviousData($instruments);
//         $calculations = $this->calculateChangesAndTrend($currentData, $previousData);
        
//         $this->insertHistoricalData($chain->underlying, $date, $currentData, $calculations, $instruments);

//         return ['success' => true, 'message' => 'Data processed successfully'];
//     }

//     private function buildInstrumentList(OptionsChain $chain): array
//     {
//         $instruments = [];

//         if ($chain->ce_token && $chain->ce_symbol) {
//             $instruments[] = [
//                 'type' => 'ce',
//                 'symbol' => $chain->ce_symbol,
//                 'token' => $chain->ce_token,
//                 'exchange' => $chain->ce_exch_seg ?? self::DEFAULT_EXCHANGE
//             ];
//         }

//         if ($chain->pe_token && $chain->pe_symbol) {
//             $instruments[] = [
//                 'type' => 'pe',
//                 'symbol' => $chain->pe_symbol,
//                 'token' => $chain->pe_token,
//                 'exchange' => $chain->pe_exch_seg ?? self::DEFAULT_EXCHANGE
//             ];
//         }

//         if ($chain->future_token && $chain->future_symbol) {
//             $instruments[] = [
//                 'type' => 'future',
//                 'symbol' => $chain->future_symbol,
//                 'token' => $chain->future_token,
//                 'exchange' => self::DEFAULT_EXCHANGE
//             ];
//         }

//         return $instruments;
//     }

//     private function fetchAllInstrumentData(array $instruments, string $date): array
//     {
//         $data = [];
        
//         foreach ($instruments as $instrument) {
//             $type = $instrument['type'];
//             $data[$type] = $this->fetchInstrumentData($instrument, $date);
//         }
        
//         return $data;
//     }

//     private function fetchInstrumentData(array $instrument, string $date): array
//     {
//         $cacheKey = "historical_options:{$instrument['type']}:{$instrument['token']}:{$date}";
        
//         return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($instrument, $date) {
//             $dbData = $this->getDataFromDatabase($instrument['symbol'], $date);
            
//             if ($dbData) {
//                 $this->line("⏩ {$instrument['type']} data loaded from cache/DB");
//                 return $dbData;
//             }

//             $this->line("🌐 Fetching {$instrument['type']} data from API");
//             return $this->fetchHistoricalAndOI_ONEHOUR($instrument['token'], $date, $instrument['exchange']);
//         });
//     }

//     private function getDataFromDatabase(string $symbol, string $date): ?array
//     {
//         $data = DB::table('historical_one_hours')
//             ->where('symbol', $symbol)
//             ->where('date', $date)
//             ->first();

//         return $data ? (array) $data : null;
//     }

//     private function isDataEmpty(array $currentData): bool
//     {
//         $ceEmpty = $this->isInstrumentDataEmpty($currentData['ce'] ?? []);
//         $peEmpty = $this->isInstrumentDataEmpty($currentData['pe'] ?? []);
        
//         return $ceEmpty && $peEmpty;
//     }

//     private function isInstrumentDataEmpty(array $data): bool
//     {
//         $fields = ['open', 'high', 'low', 'close', 'volume', 'oi'];
        
//         foreach ($fields as $field) {
//             if (!empty($data[$field] ?? null)) {
//                 return false;
//             }
//         }
        
//         return true;
//     }

//     private function fetchPreviousData(array $instruments): array
//     {
//         $previous = [];
        
//         foreach ($instruments as $instrument) {
//             $symbol = $instrument['symbol'];
//             $previous[$instrument['type']] = $this->getLatestDataBySymbol($symbol);
//         }
        
//         return $previous;
//     }

//     private function getLatestDataBySymbol(string $symbol): ?object
//     {
//         $date = Carbon::today();
//         return DB::table('historical_one_hours')
//             ->where('symbol', $symbol)
//             ->whereDate('date', '<', $date)
//             ->latest('date')
//             ->first();
//     }

//     private function calculateChangesAndTrend(array $currentData, array $previousData): array
//     {
//         $current = $this->extractCurrentValues($currentData);
//         $previous = $this->extractPreviousValues($previousData);

//         $changes = [
//             'future_price' => $this->calculateChange($previous['future_price'], $current['future_price']),
//             'future_oi' => $this->calculateChange($previous['future_oi'], $current['future_oi']),
//             'ce_price' => $this->calculateChange($previous['ce_price'], $current['ce_price']),
//             'ce_oi' => $this->calculateChange($previous['ce_oi'], $current['ce_oi']),
//             'pe_price' => $this->calculateChange($previous['pe_price'], $current['pe_price']),
//             'pe_oi' => $this->calculateChange($previous['pe_oi'], $current['pe_oi']),
//         ];

//         $trend = $this->calculateTrend(
//             $changes['future_price'],
//             $changes['future_oi'],
//             $changes['ce_oi'],
//             $changes['pe_oi']
//         );

//         return array_merge($changes, $trend);
//     }

//     private function extractCurrentValues(array $currentData): array
//     {
//         return [
//             'future_price' => $currentData['future']['close'] ?? null,
//             'future_oi' => $currentData['future']['oi'] ?? null,
//             'ce_price' => $currentData['ce']['close'] ?? null,
//             'ce_oi' => $currentData['ce']['oi'] ?? null,
//             'pe_price' => $currentData['pe']['close'] ?? null,
//             'pe_oi' => $currentData['pe']['oi'] ?? null,
//         ];
//     }

//     private function extractPreviousValues(array $previousData): array
//     {
//         return [
//             'future_price' => $previousData['future']->close ?? null,
//             'future_oi' => $previousData['future']->oi ?? null,
//             'ce_price' => $previousData['ce']->close ?? null,
//             'ce_oi' => $previousData['ce']->oi ?? null,
//             'pe_price' => $previousData['pe']->close ?? null,
//             'pe_oi' => $previousData['pe']->oi ?? null,
//         ];
//     }

//     private function calculateChange(?float $previous, ?float $current): ?float
//     {
//         if (is_null($previous) || is_null($current)) {
//             return null;
//         }
        
//         return round($current - $previous, 2);
//     }

//     private function calculateTrend(?float $futurePriceChange, ?float $futureOIChange, ?float $ceOIChange, ?float $peOIChange): array
//     {
//         $futuresScore = $this->calculateFuturesScore($futurePriceChange, $futureOIChange);
//         $optionsScore = $this->calculateOptionsScore($ceOIChange, $peOIChange);
//         $finalScore = $futuresScore + $optionsScore;
//         $trend = $this->getTrendLabel($finalScore);

//         return [
//             'futures_score' => $futuresScore,
//             'options_score' => $optionsScore,
//             'final_score' => $finalScore,
//             'trend' => $trend
//         ];
//     }

//     private function calculateFuturesScore(?float $priceChange, ?float $oiChange): int
//     {
//         if (is_null($priceChange) || is_null($oiChange)) {
//             return 0;
//         }

//         if ($priceChange > 0 && $oiChange > 0) return 2;
//         if ($priceChange < 0 && $oiChange > 0) return -2;
//         if ($priceChange > 0 && $oiChange < 0) return 1;
//         if ($priceChange < 0 && $oiChange < 0) return -1;
        
//         return 0;
//     }

//     private function calculateOptionsScore(?float $ceOIChange, ?float $peOIChange): int
//     {
//         if (is_null($ceOIChange) || is_null($peOIChange)) {
//             return 0;
//         }

//         if ($ceOIChange > 0 && $peOIChange < 0) return 1;
//         if ($ceOIChange < 0 && $peOIChange > 0) return -1;
        
//         return 0;
//     }

//     private function getTrendLabel(int $finalScore): string
//     {
//         return match (true) {
//             $finalScore >= 2 => 'Strong Bullish',
//             $finalScore == 1 => 'Mild Bullish',
//             $finalScore == 0 => 'Neutral / Sideways',
//             $finalScore == -1 => 'Mild Bearish',
//             $finalScore <= -2 => 'Strong Bearish',
//             default => 'Unknown'
//         };
//     }

//     private function insertHistoricalData(string $underlying, string $date, array $currentData, array $calculations, array $instruments): void
//     {
//         $records = [];

//         foreach (self::INSTRUMENT_TYPES as $type) {
//             $instrument = $this->findInstrument($instruments, $type);
            
//             if (!$instrument || empty($currentData[$type])) {
//                 continue;
//             }

//             $data = $currentData[$type];
//             $oi = $data['oi'] ?? 0;
//             $oiChange = $calculations["{$type}_oi"] ?? 0;
//             $oiChgPct = $oi > 0 ? ($oiChange / $oi) * 100 : 0;

//             $records[] = [
//                 'underlying' => $underlying,
//                 'date' => $date,
//                 'symbol' => $instrument['symbol'],
//                 'token' => $instrument['token'],
//                 'type' => $type,
//                 'open' => $data['open'] ?? null,
//                 'high' => $data['high'] ?? null,
//                 'low' => $data['low'] ?? null,
//                 'close' => $data['close'] ?? null,
//                 'volume' => $data['volume'] ?? null,
//                 'oi' => $oi,
//                 'oi_change' => $oiChange,
//                 'oi_chg_pct' => round($oiChgPct, 2),
//                 'price_change' => $calculations["{$type}_price"] ?? null,
//                 // 'trend' => $type === 'future' ? $calculations['trend'] : null,
//                 // 'futures_score' => $type === 'future' ? $calculations['futures_score'] : null,
//                 // 'options_score' => $type === 'ce' ? $calculations['options_score'] : null,
//                 // 'final_score' => $type === 'future' ? $calculations['final_score'] : null,
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ];
//         }

//         if (!empty($records)) {
//             DB::table('historical_one_hours')->insertOrIgnore($records);
//         }
//     }

//     private function findInstrument(array $instruments, string $type): ?array
//     {
//         foreach ($instruments as $instrument) {
//             if ($instrument['type'] === $type) {
//                 return $instrument;
//             }
//         }
//         return null;
//     }

//     private function addDelay(): void
//     {
//         sleep(self::API_DELAY);
//     }
// }

namespace App\Console\Commands;

use App\Models\HistoricalOneHour;
use App\Models\OptionsChain;
use App\Traits\AngelApiAuth;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EveryHourHistoricalData extends Command
{
    use AngelApiAuth;

    protected $signature = 'options:historical-one-hour {--date= : Specific date to process (Y-m-d format)}';
    protected $description = 'Fetch historical & OI data for all CE/PE/Future in options chain after market close';

    private const CACHE_TTL = 900;
    private const API_DELAY = 1;
    private const DEFAULT_EXCHANGE = 'NFO';
    private const INSTRUMENT_TYPES = ['future', 'ce', 'pe'];
    
    public function handle(): int
    {
        $processDate = $this->getProcessDate();
        $this->info("🚀 Starting options chain update for date: {$processDate}");

        $chains = $this->getOptionsChains();
        
        if ($chains->isEmpty()) {
            $this->warn('No option chains found to process.');
            return self::SUCCESS;
        }

        $successCount = 0;
        $skipCount = 0;

        foreach ($chains as $chain) {
            try {
                $result = $this->processOptionChain($chain, $processDate);
                
                if ($result['success']) {
                    $successCount++;
                    $this->info("✅ Successfully processed: {$chain->underlying}");
                } else {
                    $skipCount++;
                    $this->warn("⚠️  Skipped: {$chain->underlying} - {$result['message']}");
                }
            } catch (\Exception $e) {
                Log::error("Error processing chain {$chain->id}: " . $e->getMessage());
                $this->error("❌ Error processing {$chain->underlying}: " . $e->getMessage());
                continue;
            }

            $this->addDelay();
        }

        $this->info("🎉 Process completed! Success: {$successCount}, Skipped: {$skipCount}");
        return self::SUCCESS;
    }

    private function getProcessDate(): string
    {
        if ($this->option('date')) {
            return Carbon::createFromFormat('Y-m-d', $this->option('date'))->format('Y-m-d');
        }
        
        return Carbon::now()->format('Y-m-d');
    }

    private function getOptionsChains()
    {        
        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        return OptionsChain::select([
            'id', 'underlying',
            'ce_symbol', 'ce_token', 'ce_exch_seg',
            'pe_symbol', 'pe_token', 'pe_exch_seg',
            'future_symbol', 'future_token'
        ])->WhereIn('underlying', $neededSymbol)->get();
    }

    private function processOptionChain(OptionsChain $chain, string $date): array
    {
        $this->info("🔄 Processing: {$chain->underlying} (ID: {$chain->id})");

        $instruments = $this->buildInstrumentList($chain);
        
        if (empty($instruments)) {
            return ['success' => false, 'message' => 'No valid instruments found'];
        }

        $currentData = $this->fetchAllInstrumentData($instruments, $date);
        
        if ($this->isDataEmpty($currentData)) {
            return ['success' => false, 'message' => 'All CE/PE data is empty'];
        }

        $previousData = $this->fetchPreviousData($instruments);
        
        // Insert ALL hourly records with calculations
        $this->insertHistoricalDataForAllHours($chain->underlying, $date, $currentData, $previousData, $instruments);

        return ['success' => true, 'message' => 'Data processed successfully'];
    }

    private function buildInstrumentList(OptionsChain $chain): array
    {
        $instruments = [];

        if ($chain->ce_token && $chain->ce_symbol) {
            $instruments[] = [
                'type' => 'ce',
                'symbol' => $chain->ce_symbol,
                'token' => $chain->ce_token,
                'exchange' => $chain->ce_exch_seg ?? self::DEFAULT_EXCHANGE
            ];
        }

        if ($chain->pe_token && $chain->pe_symbol) {
            $instruments[] = [
                'type' => 'pe',
                'symbol' => $chain->pe_symbol,
                'token' => $chain->pe_token,
                'exchange' => $chain->pe_exch_seg ?? self::DEFAULT_EXCHANGE
            ];
        }

        if ($chain->future_token && $chain->future_symbol) {
            $instruments[] = [
                'type' => 'future',
                'symbol' => $chain->future_symbol,
                'token' => $chain->future_token,
                'exchange' => self::DEFAULT_EXCHANGE
            ];
        }

        return $instruments;
    }

    private function fetchAllInstrumentData(array $instruments, string $date): array
    {
        $data = [];
        
        foreach ($instruments as $instrument) {
            $type = $instrument['type'];
            $data[$type] = $this->fetchInstrumentData($instrument, $date);
        }
        
        return $data;
    }

    private function fetchInstrumentData(array $instrument, string $date): array
    {
        $cacheKey = "historical_options:{$instrument['type']}:{$instrument['token']}:{$date}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($instrument, $date) {
            $this->line("🌐 Fetching {$instrument['type']} data from API");
            return $this->fetchHistoricalAndOI_ONEHOUR($instrument['token'], $date, $instrument['exchange']);
        });
    }

    private function isDataEmpty(array $currentData): bool
    {
        $ceEmpty = $this->isInstrumentDataEmpty($currentData['ce'] ?? []);
        $peEmpty = $this->isInstrumentDataEmpty($currentData['pe'] ?? []);
        
        return $ceEmpty && $peEmpty;
    }

    private function isInstrumentDataEmpty(array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        // Check if it's a collection of hourly data (array of arrays)
        $firstItem = reset($data);
        if (is_array($firstItem)) {
            foreach ($data as $item) {
                if (!empty($item['close']) || !empty($item['volume'])) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    private function fetchPreviousData(array $instruments): array
    {
        $previous = [];
        
        foreach ($instruments as $instrument) {
            $symbol = $instrument['symbol'];
            $previous[$instrument['type']] = $this->getLatestDataBySymbol($symbol);
        }
        
        return $previous;
    }

    private function getLatestDataBySymbol(string $symbol): ?object
    {
        $date = Carbon::today();
        return DB::table('historical_one_hours')
            ->where('symbol', $symbol)
            ->whereDate('date', '<', $date)
            ->latest('date')
            ->first();
    }

    private function insertHistoricalDataForAllHours(string $underlying, string $date, array $currentData, array $previousData, array $instruments): void
    {
        $records = [];
        $hourCount = 0;

        // Get the number of hours from the API response
        $futureHours = $currentData['future'] ?? [];
        $hoursCount = count($futureHours);

        // Process each hour
        for ($hourIndex = 0; $hourIndex < $hoursCount; $hourIndex++) {
            foreach (self::INSTRUMENT_TYPES as $type) {
                $instrument = $this->findInstrument($instruments, $type);
                
                if (!$instrument || empty($currentData[$type])) {
                    continue;
                }

                $hourData = $currentData[$type][$hourIndex] ?? null;
                
                if (!$hourData) {
                    continue;
                }

                // Extract time and create proper datetime
                $timeString = $hourData['time'] ?? null;
                $dateTime = $this->parseDateTime($timeString);

                // Calculate OI change (compare with previous hour or previous day)
                $oiChange = 0;
                $oiChgPct = 0;

                if ($hourIndex > 0 && isset($currentData[$type][$hourIndex - 1])) {
                    $prevHourOI = $currentData[$type][$hourIndex - 1]['oi'] ?? 0;
                    $currentOI = $hourData['oi'] ?? 0;
                    $oiChange = round($currentOI - $prevHourOI, 2);
                    $oiChgPct = $currentOI > 0 ? round(($oiChange / $currentOI) * 100, 2) : 0;
                } else if ($hourIndex === 0 && $previousData[$type]) {
                    $prevOI = $previousData[$type]->oi ?? 0;
                    $currentOI = $hourData['oi'] ?? 0;
                    $oiChange = round($currentOI - $prevOI, 2);
                    $oiChgPct = $currentOI > 0 ? round(($oiChange / $currentOI) * 100, 2) : 0;
                }

                // Calculate price change
                $priceChange = null;
                if ($hourIndex > 0 && isset($currentData[$type][$hourIndex - 1])) {
                    $prevClose = $currentData[$type][$hourIndex - 1]['close'] ?? null;
                    $currentClose = $hourData['close'] ?? null;
                    if ($prevClose && $currentClose) {
                        $priceChange = round($currentClose - $prevClose, 2);
                    }
                } else if ($hourIndex === 0 && $previousData[$type]) {
                    $prevClose = $previousData[$type]->close ?? null;
                    $currentClose = $hourData['close'] ?? null;
                    if ($prevClose && $currentClose) {
                        $priceChange = round($currentClose - $prevClose, 2);
                    }
                }

                $records[] = [
                    'underlying' => $underlying,
                    'date' => $dateTime,
                    'symbol' => $instrument['symbol'],
                    'token' => $instrument['token'],
                    'type' => $type,
                    'open' => $hourData['open'] ?? null,
                    'high' => $hourData['high'] ?? null,
                    'low' => $hourData['low'] ?? null,
                    'close' => $hourData['close'] ?? null,
                    'volume' => $hourData['volume'] ?? null,
                    'oi' => $hourData['oi'] ?? 0,
                    'oi_change' => $oiChange,
                    'oi_chg_pct' => $oiChgPct,
                    'price_change' => $priceChange,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $hourCount++;
            }
        }

        if (!empty($records)) {
            DB::table('historical_one_hours')->insertOrIgnore($records);
            $this->line("✨ Inserted {$hourCount} hourly records across all instruments");
        }
    }

    private function parseDateTime(?string $timeString): string
    {
        if (!$timeString) {
            return Carbon::now()->format('Y-m-d H:i:s');
        }

        try {
            // Parse ISO 8601 format like "2025-10-06T09:15:00+05:30"
            $dateTime = Carbon::parse($timeString);
            return $dateTime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::warning("Could not parse time string: {$timeString}", ['error' => $e->getMessage()]);
            return Carbon::now()->format('Y-m-d H:i:s');
        }
    }

    private function findInstrument(array $instruments, string $type): ?array
    {
        foreach ($instruments as $instrument) {
            if ($instrument['type'] === $type) {
                return $instrument;
            }
        }
        return null;
    }

    private function addDelay(): void
    {
        sleep(self::API_DELAY);
    }
}