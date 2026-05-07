<?php

namespace App\Console\Commands;

use App\Models\InstrumentChain;
use App\Models\InstrumentHistoricalData;
use App\Traits\AngelApiHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncInstrumentHistoricalData extends Command
{
    use AngelApiHelper;

    protected $signature = 'instruments:sync-historical {--date= : Specific date (Y-m-d)} {--symbol=* : Specific symbols}';
    protected $description = 'Sync historical OHLCV and OI data for instrument chains';

    private const API_DELAY = 1;
    private const CACHE_TTL = 900;

    private $processedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;

    public function handle(): int
    {
        $processDate = $this->getProcessDate();
        $this->info("🚀 Starting historical data sync for: {$processDate}\n");

        $dayName = Carbon::parse($processDate)->format('l');

        //----------------------------------------
        // 1️⃣ Skip Saturday & Sunday
        //----------------------------------------
        if ($dayName == "Saturday" || $dayName == "Sunday") {
            $this->info("Skipped: Weekend ($dayName)");
            return 0;
        }

        //----------------------------------------
        // 2️⃣ Skip if market holiday from DB
        //----------------------------------------
        $isHoliday = \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $processDate)
            ->exists();

        if ($isHoliday) {
            $this->info("Skipped: Market Holiday ($processDate)");
            return 0;
        }

        try {
            $underlyings = $this->getUnderlyingsToProcess();
            
            if ($underlyings->isEmpty()) {
                $this->warn('❌ No underlyings found to process.');
                return self::SUCCESS;
            }

            $this->info("📊 Found {$underlyings->count()} underlying(s) to process.\n");

            DB::beginTransaction();

            foreach ($underlyings as $underlying) {
                $this->processUnderlying($underlying, $processDate);
                sleep(self::API_DELAY);
            }

            DB::commit();
            
            $this->displaySummary($underlyings->count());

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Historical data sync critical error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Critical Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    private function getProcessDate(): string
    {
        if ($this->option('date')) {
            return Carbon::createFromFormat('Y-m-d', $this->option('date'))->format('Y-m-d');
        }
        
        return Carbon::now()->format('Y-m-d');
    }

    private function getUnderlyingsToProcess()
    {
        $symbolOption = $this->option('symbol');

        $query = InstrumentChain::select('underlying')
            ->where('is_active', true)
            ->distinct();

        if (!empty($symbolOption)) {
            $query->whereIn('underlying', $symbolOption);
        } else {
            $defaultSymbols = [
                'AXISBANK', 'BAJFINANCE', 'BHARTIARTL', 'DRREDDY',
                'CIPLA', 'SHRIRAMFIN', 'CHOALFIN', 'PAYTM',
                'NIFTY', 'BANKNIFTY', 'EICHERMOT'
            ];
            $query->whereIn('underlying', $defaultSymbols);
        }

        return $query->get();
    }

    private function processUnderlying($underlyingObj, string $date)
    {
        $underlying = $underlyingObj->underlying;
        $this->line("⏳ Processing: {$underlying}...");

        try {
            // Get all active instruments for this underlying
            $instruments = InstrumentChain::active()
                ->byUnderlying($underlying)
                ->get();

            if ($instruments->isEmpty()) {
                $this->warn("⚠️  Skipped: {$underlying} - No active instruments");
                $this->skippedCount++;
                return;
            }

            $recordsCreated = 0;
            $atmData = [];

            // Process each instrument
            foreach ($instruments as $instrument) {
                $historicalData = $this->fetchHistoricalData($instrument, $date);
                
                if (empty($historicalData)) {
                    continue;
                }

                // Get previous data for change calculation
                $previousData = $this->getPreviousData($instrument->token, $date);
                
                // Calculate changes
                $changes = $this->calculateChanges($historicalData, $previousData);
                
                // Insert historical record
                $this->insertHistoricalRecord($instrument, $date, $historicalData, $changes);
                $recordsCreated++;

                // Collect ATM data for trend calculation
                if ($instrument->is_atm) {
                    $atmData[$instrument->type] = array_merge($historicalData, $changes);
                }
            }

            // Calculate and update trend for ATM options
            if (!empty($atmData) && isset($atmData['FUT'], $atmData['CE'], $atmData['PE'])) {
                $this->calculateAndUpdateTrend($underlying, $date, $atmData);
            }

            if ($recordsCreated > 0) {
                $this->info("✅ Success: {$underlying} ({$recordsCreated} records)");
                $this->processedCount++;
            } else {
                $this->warn("⚠️  Skipped: {$underlying} - No data available");
                $this->skippedCount++;
            }

        } catch (\Exception $e) {
            Log::error("Failed to process underlying: {$underlying}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("❌ Failed: {$underlying} - {$e->getMessage()}");
            $this->failedCount++;
        }
    }

    private function fetchHistoricalData($instrument, string $date): array
    {
        $cacheKey = "historical_data:{$instrument->token}:{$date}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($instrument, $date) {
            // Check if data exists in DB
            $existing = InstrumentHistoricalData::byToken($instrument->token)
                ->byDate($date)
                ->first();
            
            if ($existing) {
                $this->line("   ⏩ {$instrument->type} data from cache");
                return [
                    'open' => $existing->open,
                    'high' => $existing->high,
                    'low' => $existing->low,
                    'close' => $existing->close,
                    'volume' => $existing->volume,
                    'oi' => $existing->oi,
                ];
            }

            // Fetch from API
            $this->line("   🌐 Fetching {$instrument->type} from API");
            return $this->fetchHistoricalAndOI($instrument->token, $date, $instrument->exchange);
        });
    }

    private function getPreviousData(string $token, string $date): ?array
    {
        $previous = InstrumentHistoricalData::getLatestByToken($token, $date);
        
        if (!$previous) {
            return null;
        }

        return [
            'close' => $previous->close,
            'oi' => $previous->oi,
        ];
    }

    private function calculateChanges(array $current, ?array $previous): array
    {
        if (!$previous) {
            return [
                'price_change' => null,
                'oi_change' => null,
                'oi_change_pct' => null,
            ];
        }

        $priceChange = isset($current['close'], $previous['close']) 
            ? round($current['close'] - $previous['close'], 2) 
            : null;

        $oiChange = isset($current['oi'], $previous['oi']) 
            ? ($current['oi'] - $previous['oi']) 
            : null;

        $oiChangePct = isset($current['oi'], $previous['oi']) && $previous['oi'] > 0
            ? round(($oiChange / $previous['oi']) * 100, 2)
            : null;

        return [
            'price_change' => $priceChange,
            'oi_change' => $oiChange,
            'oi_change_pct' => $oiChangePct,
        ];
    }

    private function insertHistoricalRecord($instrument, string $date, array $data, array $changes)
    {
        InstrumentHistoricalData::updateOrCreate(
            [
                'token' => $instrument->token,
                'type' => $instrument->type,
                'data_date' => $date,
            ],
            [
                'underlying' => $instrument->underlying,
                'symbol' => $instrument->symbol,
                'open' => $data['open'] ?? null,
                'high' => $data['high'] ?? null,
                'low' => $data['low'] ?? null,
                'close' => $data['close'] ?? null,
                'volume' => $data['volume'] ?? null,
                'oi' => $data['oi'] ?? null,
                'price_change' => $changes['price_change'],
                'oi_change' => $changes['oi_change'],
                'oi_change_pct' => $changes['oi_change_pct'],
                'strike_price' => $instrument->strike_price,
                'strike_position' => $instrument->strike_position,
            ]
        );
    }

    private function calculateAndUpdateTrend(string $underlying, string $date, array $atmData)
    {
        $futurePrice = $atmData['FUT']['price_change'] ?? 0;
        $futureOI = $atmData['FUT']['oi_change'] ?? 0;
        $ceOI = $atmData['CE']['oi_change'] ?? 0;
        $peOI = $atmData['PE']['oi_change'] ?? 0;

        // Calculate scores
        $futuresScore = $this->calculateFuturesScore($futurePrice, $futureOI);
        $optionsScore = $this->calculateOptionsScore($ceOI, $peOI);
        $finalScore = $futuresScore + $optionsScore;
        $trend = $this->getTrendLabel($finalScore);

        // Update all three records with trend data
        InstrumentHistoricalData::where('underlying', $underlying)
            ->where('data_date', $date)
            ->whereIn('type', ['FUT', 'CE', 'PE'])
            ->whereNotNull('strike_position')
            ->where('strike_position', 0) // Only ATM
            ->update([
                'trend' => $trend,
                'futures_score' => $futuresScore,
                'options_score' => $optionsScore,
                'final_score' => $finalScore,
            ]);
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

    private function displaySummary($totalUnderlyings)
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("      HISTORICAL SYNC SUMMARY       ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Underlyings', $totalUnderlyings],
                ['✅ Successfully Processed', $this->processedCount],
                ['⚠️  Skipped', $this->skippedCount],
                ['❌ Failed', $this->failedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}