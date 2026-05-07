<?php

namespace App\Console\Commands;

use App\Models\Early1DayOptionChain;
use App\Traits\AngelApiAuth;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EarlyHistoricalOptionsData extends Command
{
    use AngelApiAuth;

    protected $signature = 'options:early-historical-data {--date= : Specific date to process (Y-m-d format)}';
    protected $description = 'Fetch historical & OI data for all CE/PE/Future in options chain before market close';

    
    private const CACHE_TTL = 900; 
    private const API_DELAY = 1;
    private const DEFAULT_EXCHANGE = 'NFO';

    public function handle(): int
    {
        $processDate = $this->getProcessDate();
        $this->info("🚀 Starting options chain update for date: {$processDate}");

        $chains = $this->getEarly1DayOptionChains();
        
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

    private function getEarly1DayOptionChains()
    {
        $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

        return Early1DayOptionChain::select([
            'id', 'underlying',
            'ce_symbol', 'ce_token', 'ce_exch_seg',
            'pe_symbol', 'pe_token', 'pe_exch_seg',
            'future_symbol', 'future_token'
        ])->whereIn('underlying',$neededSymbol)->get();
    }

    private function processOptionChain(Early1DayOptionChain $chain, string $date): array
    {
        $this->info("🔄 Processing: {$chain->underlying} (ID: {$chain->id})");

        // Fetch current data for all instruments
        $currentData = $this->fetchAllInstrumentData($chain, $date);
        
        // Check if we have meaningful data
        if ($this->isDataEmpty($currentData)) {
            return [
                'success' => false,
                'message' => 'All CE/PE data is empty'
            ];
        }

        // Fetch previous data for change calculations
        $previousData = $this->fetchPreviousData($chain);
        
        // Calculate changes and trends
        $calculations = $this->calculateChangesAndTrend($currentData, $previousData);
        
        // Insert the data
        $this->insertHistoricalData($chain, $date, $currentData, $calculations);

        return ['success' => true, 'message' => 'Data processed successfully'];
    }

    private function fetchAllInstrumentData(Early1DayOptionChain $chain, string $date): array
    {
        return [
            'ce' => $this->fetchInstrumentData('ce', $chain->ce_token, $chain->ce_exch_seg, $date),
            'pe' => $this->fetchInstrumentData('pe', $chain->pe_token, $chain->pe_exch_seg, $date),
            'future' => $this->fetchInstrumentData('future', $chain->future_token, self::DEFAULT_EXCHANGE, $date)
        ];
    }

    private function fetchInstrumentData(string $type, ?string $token, ?string $exchange, string $date): array
    {
        if (!$token) {
            return [];
        }

        $cacheKey = "early_historical_options:{$type}:{$token}:{$date}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($type, $token, $date, $exchange) {
            // Try to get from database first
            $dbData = $this->getDataFromDatabase($type, $token, $date);
            
            if ($dbData) {
                $this->line("⏩ {$type} data loaded from cache/DB");
                return $dbData;
            }

            // Fetch from API if not in database
            $this->line("🌐 Fetching {$type} data from API");
            return $this->EarlyFetchHistoricalAndOI($token, $date, $exchange);
        });
    }

    private function getDataFromDatabase(string $type, string $token, string $date): ?array
    {
        $columnName = $type . '_token';
        
        $data = DB::table('early_historical_options_data')
            ->where($columnName, $token)
            ->where('date', $date)
            ->first();

        return $data ? (array) $data : null;
    }

    private function isDataEmpty(array $currentData): bool
    {
        $ceEmpty = $this->isInstrumentDataEmpty($currentData['ce'], 'ce');
        $peEmpty = $this->isInstrumentDataEmpty($currentData['pe'], 'pe');
        
        return $ceEmpty && $peEmpty;
    }

    private function isInstrumentDataEmpty(array $data, string $prefix): bool
    {
        $fields = ['open', 'high', 'low', 'close', 'volume', 'oi'];
        
        foreach ($fields as $field) {
            $value = $data[$prefix . '_' . $field] ?? $data[$field] ?? null;
            if (!empty($value)) {
                return false;
            }
        }
        
        return true;
    }

    private function fetchPreviousData(Early1DayOptionChain $chain): array
    {
        return [
            'future' => $this->getLatestDataBySymbol('future_symbol', $chain->future_symbol),
            'ce' => $this->getLatestDataBySymbol('ce_symbol', $chain->ce_symbol),
            'pe' => $this->getLatestDataBySymbol('pe_symbol', $chain->pe_symbol)
        ];
    }

    private function getLatestDataBySymbol(string $column, ?string $symbol): ?object
    {
        if (!$symbol) {
            return null;
        }

        $date = Carbon::today();
        // $date = Carbon::createFromFormat('d-m-Y', '03-09-2025')->format('Y-m-d');

        return DB::table('early_historical_options_data')
            ->where($column, $symbol)
            ->whereDate('date', '<', $date)
            ->latest('id')
            ->first();
    }

    private function calculateChangesAndTrend(array $currentData, array $previousData): array
    {
        // Extract current and previous values
        $current = $this->extractCurrentValues($currentData);
        $previous = $this->extractPreviousValues($previousData);

        // Calculate changes
        $changes = [
            'future_price' => $this->calculateChange($previous['future_price'], $current['future_price']),
            'future_oi' => $this->calculateChange($previous['future_oi'], $current['future_oi']),
            'ce_price' => $this->calculateChange($previous['ce_price'], $current['ce_price']),
            'ce_oi' => $this->calculateChange($previous['ce_oi'], $current['ce_oi']),
            'pe_price' => $this->calculateChange($previous['pe_price'], $current['pe_price']),
            'pe_oi' => $this->calculateChange($previous['pe_oi'], $current['pe_oi']),
        ];

        // Calculate trend
        $trend = $this->calculateTrend(
            $changes['future_price'],
            $changes['future_oi'],
            $changes['ce_oi'],
            $changes['pe_oi']
        );

        return array_merge($changes, $trend);
    }

    private function extractCurrentValues(array $currentData): array
    {
        return [
            'future_price' => $currentData['future']['future_close'] ?? $currentData['future']['close'] ?? null,
            'future_oi' => $currentData['future']['future_oi'] ?? $currentData['future']['oi'] ?? null,
            'ce_price' => $currentData['ce']['ce_close'] ?? $currentData['ce']['close'] ?? null,
            'ce_oi' => $currentData['ce']['ce_oi'] ?? $currentData['ce']['oi'] ?? null,
            'pe_price' => $currentData['pe']['pe_close'] ?? $currentData['pe']['close'] ?? null,
            'pe_oi' => $currentData['pe']['pe_oi'] ?? $currentData['pe']['oi'] ?? null,
        ];
    }

    private function extractPreviousValues(array $previousData): array
    {
        return [
            'future_price' => $previousData['future']->future_close ?? null,
            'future_oi' => $previousData['future']->future_oi ?? null,
            'ce_price' => $previousData['ce']->ce_close ?? null,
            'ce_oi' => $previousData['ce']->ce_oi ?? null,
            'pe_price' => $previousData['pe']->pe_close ?? null,
            'pe_oi' => $previousData['pe']->pe_oi ?? null,
        ];
    }

    private function calculateChange(?float $previous, ?float $current): ?float
    {
        if (is_null($previous) || is_null($current)) {
            return null;
        }
        
        return round($current - $previous, 2);
    }

    private function calculateTrend(?float $futurePriceChange, ?float $futureOIChange, ?float $ceOIChange, ?float $peOIChange): array
    {
        $futuresScore = $this->calculateFuturesScore($futurePriceChange, $futureOIChange);
        $optionsScore = $this->calculateOptionsScore($ceOIChange, $peOIChange);
        $finalScore = $futuresScore + $optionsScore;
        $trend = $this->getTrendLabel($finalScore);

        return [
            'futures_score' => $futuresScore,
            'options_score' => $optionsScore,
            'final_score' => $finalScore,
            'trend' => $trend
        ];
    }

    private function calculateFuturesScore(?float $priceChange, ?float $oiChange): int
    {
        if (is_null($priceChange) || is_null($oiChange)) {
            return 0;
        }

        if ($priceChange > 0 && $oiChange > 0) return 2;
        if ($priceChange < 0 && $oiChange > 0) return -2;
        if ($priceChange > 0 && $oiChange < 0) return 1;
        if ($priceChange < 0 && $oiChange < 0) return -1;
        
        return 0;
    }

    private function calculateOptionsScore(?float $ceOIChange, ?float $peOIChange): int
    {
        if (is_null($ceOIChange) || is_null($peOIChange)) {
            return 0;
        }

        if ($ceOIChange > 0 && $peOIChange < 0) return 1;
        if ($ceOIChange < 0 && $peOIChange > 0) return -1;
        
        return 0;
    }

    private function getTrendLabel(int $finalScore): string
    {
        return match (true) {
            $finalScore >= 2 => 'Strong Bullish',
            $finalScore == 1 => 'Mild Bullish',
            $finalScore == 0 => 'Neutral / Sideways',
            $finalScore == -1 => 'Mild Bearish',
            $finalScore <= -2 => 'Strong Bearish',
            default => 'Unknown'
        };
    }

    private function insertHistoricalData(Early1DayOptionChain $chain, string $date, array $currentData, array $calculations): void
    {
        // Skip if CE or PE data missing
        if (empty($chain->ce_symbol) || empty($chain->ce_token)) {
            \Log::warning("Skipping {$chain->underlying} — CE data missing", [
                'ce_symbol' => $chain->ce_symbol,
                'ce_token'  => $chain->ce_token,
            ]);
            return;
        }

        if (empty($chain->pe_symbol) || empty($chain->pe_token)) {
            \Log::warning("Skipping {$chain->underlying} — PE data missing", [
                'pe_symbol' => $chain->pe_symbol,
                'pe_token'  => $chain->pe_token,
            ]);
            return;
        }
        
        // Current OI values
        $ceOi = $currentData['ce']['ce_oi'] ?? $currentData['ce']['oi'] ?? 0;
        $peOi = $currentData['pe']['pe_oi'] ?? $currentData['pe']['oi'] ?? 0;
        $futureOi = $currentData['future']['future_oi'] ?? $currentData['future']['oi'] ?? 0;

        // OI change values
        $ceOiChange = $calculations['ce_oi'] ?? 0;
        $peOiChange = $calculations['pe_oi'] ?? 0;
        $futureOiChange = $calculations['future_oi'] ?? 0;

        // Calculate OI change percentages safely
        $ceOiChgPct = $ceOi > 0 ? ($ceOiChange / $ceOi) * 100 : 0;
        $peOiChgPct = $peOi > 0 ? ($peOiChange / $peOi) * 100 : 0;
        $futureOiChgPct = $futureOi > 0 ? ($futureOiChange / $futureOi) * 100 : 0;
        
        $insertData = [
            'underlying' => $chain->underlying,
            'date' => $date,
            
            // CE fields
            'ce_symbol' => $chain->ce_symbol,
            'ce_token' => $chain->ce_token,
            'ce_open' => $currentData['ce']['ce_open'] ?? $currentData['ce']['open'] ?? null,
            'ce_high' => $currentData['ce']['ce_high'] ?? $currentData['ce']['high'] ?? null,
            'ce_low' => $currentData['ce']['ce_low'] ?? $currentData['ce']['low'] ?? null,
            'ce_close' => $currentData['ce']['ce_close'] ?? $currentData['ce']['close'] ?? null,
            'ce_volume' => $currentData['ce']['ce_volume'] ?? $currentData['ce']['volume'] ?? null,
            'ce_oi' => $currentData['ce']['ce_oi'] ?? $currentData['ce']['oi'] ?? null,
            'ce_price_change' => $calculations['ce_price'],
            'ce_oi_change' => $calculations['ce_oi'],
            'ce_oi_chg_pct' => round($ceOiChgPct, 2),

            // PE fields
            'pe_symbol' => $chain->pe_symbol,
            'pe_token' => $chain->pe_token,
            'pe_open' => $currentData['pe']['pe_open'] ?? $currentData['pe']['open'] ?? null,
            'pe_high' => $currentData['pe']['pe_high'] ?? $currentData['pe']['high'] ?? null,
            'pe_low' => $currentData['pe']['pe_low'] ?? $currentData['pe']['low'] ?? null,
            'pe_close' => $currentData['pe']['pe_close'] ?? $currentData['pe']['close'] ?? null,
            'pe_volume' => $currentData['pe']['pe_volume'] ?? $currentData['pe']['volume'] ?? null,
            'pe_oi' => $currentData['pe']['pe_oi'] ?? $currentData['pe']['oi'] ?? null,
            'pe_price_change' => $calculations['pe_price'],
            'pe_oi_change' => $calculations['pe_oi'],
            'pe_oi_chg_pct' => round($peOiChgPct, 2),

            // Future fields
            'future_symbol' => $chain->future_symbol,
            'future_token' => $chain->future_token,
            'future_open' => $currentData['future']['future_open'] ?? $currentData['future']['open'] ?? null,
            'future_high' => $currentData['future']['future_high'] ?? $currentData['future']['high'] ?? null,
            'future_low' => $currentData['future']['future_low'] ?? $currentData['future']['low'] ?? null,
            'future_close' => $currentData['future']['future_close'] ?? $currentData['future']['close'] ?? null,
            'future_volume' => $currentData['future']['future_volume'] ?? $currentData['future']['volume'] ?? null,
            'future_oi' => $currentData['future']['future_oi'] ?? $currentData['future']['oi'] ?? null,
            'future_price_change' => $calculations['future_price'],
            'future_oi_change' => $calculations['future_oi'],
            'future_oi_chg_pct' => round($futureOiChgPct, 2),

            // Trend analysis
            'trend' => $calculations['trend'],
            'futures_score' => $calculations['futures_score'],
            'final_score' => $calculations['final_score'],
            'options_score' => $calculations['options_score'],
        ];

        DB::table('early_historical_options_data')->insert($insertData);
    }

    private function addDelay(): void
    {
        sleep(self::API_DELAY);
    }
}
