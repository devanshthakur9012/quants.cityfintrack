<?php

namespace App\Console\Commands;

use App\Models\InstrumentChain;
use App\Models\Instrument15MinData;
use App\Models\Instrument15MinSignal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Generate15MinCombinedSignals extends Command
{
    protected $signature = 'signals:generate-15min {--date= : Specific date (Y-m-d)} {--symbol=* : Specific symbols}';
    protected $description = 'Generate combined trading signals from 15-min futures data using HA + OI + Price analysis';

    private $processedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;

    public function handle(): int
    {
        $processDate = $this->getProcessDate();
        $this->info("🚀 Starting 15-min Combined Signal Generation for: {$processDate}\n");

        $dayName = Carbon::parse($processDate)->format('l');

        // Skip weekends
        if (in_array($dayName, ['Saturday', 'Sunday'])) {
            $this->info("Skipped: Weekend ($dayName)");
            return 0;
        }

        // Skip market holidays
        $isHoliday = DB::table('market_holidays')
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

            foreach ($underlyings as $underlying) {
                $this->processUnderlying($underlying, $processDate);
            }

            $this->displaySummary($underlyings->count());

        } catch (\Exception $e) {
            Log::error('15-min signal generation critical error', [
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
            ->where('type', 'FUT') // Only futures
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
            // Get futures contract
            $futureContract = InstrumentChain::active()
                ->byUnderlying($underlying)
                ->futures()
                ->first();

            if (!$futureContract) {
                $this->warn("⚠️  Skipped: {$underlying} - No active futures contract");
                $this->skippedCount++;
                return;
            }

            // Get all 15-min candles for this date
            $candles = Instrument15MinData::byToken($futureContract->token)
                ->byDate($date)
                ->orderBy('candle_time', 'asc')
                ->get()
                ->toArray();

            if (empty($candles)) {
                $this->warn("⚠️  Skipped: {$underlying} - No 15-min data available");
                $this->skippedCount++;
                return;
            }

            $this->line("   📊 Analyzing {$underlying} - " . count($candles) . " candles");

            // Generate signals
            $signals = $this->generateSignals($candles, $futureContract, $date);

            if (!empty($signals)) {
                $this->info("✅ Success: {$underlying} ({$signals} signals generated)");
                $this->processedCount++;
            } else {
                $this->warn("⚠️  Skipped: {$underlying} - No valid signals");
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

    private function generateSignals(array $candles, $futureContract, string $date): int
    {
        $signalsCreated = 0;
        $prevHa = null;
        $prevOiSignal = null;
        $tradeState = new \stdClass();
        $tradeState->current = null;
        $tradeState->entryIndex = null;
        $tradeState->oppositeCount = 0;

        foreach ($candles as $index => $candle) {
            if ($index === 0) {
                $prevHa = $this->calculateHeikinAshi($candle, null);
                $prevOiSignal = 'NO SIGNAL';
                continue;
            }

            // Calculate Heikin Ashi
            $ha = $this->calculateHeikinAshi($candle, $prevHa);

            // Futures Structure Analysis
            $structure = $this->futuresStructure($candle, $candles[$index - 1]);

            // Raw Trade Signal
            $rawSignal = $this->rawTradeSignal($structure, $ha);

            // OI-Only Signal
            $oiSignal = $this->oiOnlySignal(
                $candle['oi'] ?? 0,
                $candles[$index - 1]['oi'] ?? 0,
                $prevOiSignal
            );

            // Align OI + Raw Signal
            if (
                ($rawSignal === 'BUY' && $oiSignal !== 'BULLISH') ||
                ($rawSignal === 'SELL' && $oiSignal !== 'BEARISH')
            ) {
                $rawSignal = 'NO TRADE';
            }

            // Apply Revised Logic (Trend Lock + Confirmation)
            $finalSignal = $this->revisedTradeLogic($rawSignal, $index, $tradeState);

            // Store signal in database
            $this->storeSignal([
                'underlying' => $futureContract->underlying,
                'symbol' => $futureContract->symbol,
                'token' => $futureContract->token,
                'data_date' => $date,
                'candle_time' => $candle['candle_time'],
                'candle_index' => $index,
                'open' => $candle['open'],
                'high' => $candle['high'],
                'low' => $candle['low'],
                'close' => $candle['close'],
                'volume' => $candle['volume'],
                'oi' => $candle['oi'],
                'ha_open' => $ha['open'],
                'ha_close' => $ha['close'],
                'ha_high' => $ha['high'],
                'ha_low' => $ha['low'],
                'ha_color' => $ha['color'],
                'ha_strength' => $ha['strength'],
                'structure_type' => $structure['type'],
                'structure_vol_change' => $structure['vol'],
                'raw_signal' => $rawSignal,
                'oi_signal' => $oiSignal,
                'final_signal' => $finalSignal,
            ]);

            $signalsCreated++;
            $prevHa = $ha;
            $prevOiSignal = $oiSignal;
        }

        return $signalsCreated;
    }

    /**
     * Calculate Heikin Ashi values
     * Trend gives direction, HA gives timing
     */
    private function calculateHeikinAshi(array $candle, ?array $prevHa): array
    {
        $haClose = (
            $candle['open'] +
            $candle['high'] +
            $candle['low'] +
            $candle['close']
        ) / 4;

        $haOpen = $prevHa
            ? ($prevHa['open'] + $prevHa['close']) / 2
            : ($candle['open'] + $candle['close']) / 2;

        $haHigh = max($candle['high'], $haOpen, $haClose);
        $haLow = min($candle['low'], $haOpen, $haClose);

        $color = ($haClose > $haOpen) ? 'GREEN' : 'RED';
        $strength = abs($haClose - $haOpen) / max(0.0001, ($haHigh - $haLow));

        return [
            'open' => round($haOpen, 2),
            'close' => round($haClose, 2),
            'high' => round($haHigh, 2),
            'low' => round($haLow, 2),
            'color' => $color,
            'strength' => round($strength, 4)
        ];
    }

    /**
     * Futures Structure Analysis
     * OI gives conviction
     */
    private function futuresStructure(array $candle, array $prev): array
    {
        $priceChg = ($candle['close'] - $prev['close']) / max(0.0001, $prev['close']);
        $volChg = ($candle['volume'] - $prev['volume']) / max(1, $prev['volume']);
        $oiChg = ($candle['oi'] - $prev['oi']) / max(1, $prev['oi']);

        if ($priceChg > 0 && $oiChg > 0) {
            return ['type' => 'LONG_BUILDUP', 'vol' => round($volChg, 4)];
        }
        if ($priceChg < 0 && $oiChg > 0) {
            return ['type' => 'SHORT_BUILDUP', 'vol' => round($volChg, 4)];
        }
        if ($priceChg > 0 && $oiChg < 0) {
            return ['type' => 'SHORT_COVERING', 'vol' => round($volChg, 4)];
        }
        if ($priceChg < 0 && $oiChg < 0) {
            return ['type' => 'LONG_UNWINDING', 'vol' => round($volChg, 4)];
        }

        return ['type' => 'NEUTRAL', 'vol' => round($volChg, 4)];
    }

    /**
     * Raw Trade Signal (Pre-Filter)
     */
    private function rawTradeSignal(array $structure, array $ha): string
    {
        // Noise filter: weak HA strength
        if ($ha['strength'] < 0.5) {
            return 'NO TRADE';
        }

        // BUY conditions
        if (
            in_array($structure['type'], ['LONG_BUILDUP', 'SHORT_COVERING']) &&
            $structure['vol'] > 0 &&
            $ha['color'] === 'GREEN'
        ) {
            return 'BUY';
        }

        // SELL conditions
        if (
            in_array($structure['type'], ['SHORT_BUILDUP', 'LONG_UNWINDING']) &&
            $structure['vol'] > 0 &&
            $ha['color'] === 'RED'
        ) {
            return 'SELL';
        }

        return 'NO TRADE';
    }

    /**
     * OI-Only Continuous Signal
     * OI gives conviction
     */
    private function oiOnlySignal(float $currOi, float $prevOi, ?string $prevSignal): string
    {
        if ($currOi > $prevOi) return 'BULLISH';
        if ($currOi < $prevOi) return 'BEARISH';
        return $prevSignal ?? 'NO SIGNAL';
    }

    /**
     * Revised Professional Logic
     * - Trend lock = 3 candles (cooldown)
     * - Reversal requires 2 consecutive opposite signals (confirmation)
     * - Time + confirmation removes noise
     */
    private function revisedTradeLogic(string $rawSignal, int $index, \stdClass $state): string
    {
        $COOLDOWN = 3; // Trend lock period
        $CONFIRM = 2;  // Confirmation candles needed

        // Initial entry
        if ($state->current === null) {
            if (in_array($rawSignal, ['BUY', 'SELL'])) {
                $state->current = $rawSignal;
                $state->entryIndex = $index;
            }
            return $state->current ?? 'NO TRADE';
        }

        $candlesInTrade = $index - $state->entryIndex;

        // Track opposite signals
        if ($rawSignal !== 'NO TRADE' && $rawSignal !== $state->current) {
            $state->oppositeCount++;
        } else {
            $state->oppositeCount = 0;
        }

        // Reversal: 2 consecutive + cooldown completed
        if ($state->oppositeCount >= $CONFIRM && $candlesInTrade >= $COOLDOWN) {
            $state->current = $rawSignal;
            $state->entryIndex = $index;
            $state->oppositeCount = 0;
        }

        return $state->current;
    }

    /**
     * Store signal in database
     */
    private function storeSignal(array $data)
    {
        Instrument15MinSignal::updateOrCreate(
            [
                'token' => $data['token'],
                'candle_time' => $data['candle_time'],
            ],
            $data
        );
    }

    private function displaySummary($totalUnderlyings)
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("   COMBINED SIGNAL GENERATION      ");
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