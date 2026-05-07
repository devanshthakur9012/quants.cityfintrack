<?php

namespace App\Console\Commands;

use App\Models\FuturesInstrument;
use App\Models\Futures15MinCandle;
use App\Models\FuturesTradingSignal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateFuturesSignals extends Command
{
    protected $signature = 'futures:generate-signals {--date= : Date (Y-m-d)} {--symbol=* : Symbols}';
    protected $description = 'Generate trading signals using HA + OI + Price structure';

    private $processedCount = 0;
    private $failedCount = 0;
    private $skippedCount = 0;

    public function handle(): int
    {
        $processDate = $this->getProcessDate();
        $this->info("🚀 Generating Trading Signals for: {$processDate}\n");

        try {
            $instruments = $this->getInstruments();
            
            if ($instruments->isEmpty()) {
                $this->warn('❌ No active instruments found');
                return 1;
            }

            $this->info("📊 Found {$instruments->count()} instrument(s)\n");

            foreach ($instruments as $instrument) {
                $this->processInstrument($instrument, $processDate);
            }

            $this->displaySummary();

        } catch (\Exception $e) {
            Log::error('Signal generation error', [
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

    private function getInstruments()
    {
        $symbolOption = $this->option('symbol');
        $query = FuturesInstrument::active();

        if (!empty($symbolOption)) {
            $query->whereIn('underlying', $symbolOption);
        }

        return $query->get();
    }

    private function processInstrument($instrument, string $date)
    {
        $this->line("⏳ Processing: {$instrument->underlying}...");

        try {
            $candles = Futures15MinCandle::byToken($instrument->token)
                ->byDate($date)
                ->orderBy('candle_time', 'asc')
                ->get()
                ->toArray();

            if (empty($candles)) {
                $this->warn("   ⚠️  Skipped - No candle data");
                $this->skippedCount++;
                return;
            }

            $this->line("   📊 Analyzing " . count($candles) . " candles");

            $signalsCreated = $this->generateSignals($candles, $instrument, $date);

            if ($signalsCreated > 0) {
                $this->info("   ✅ Success ({$signalsCreated} signals)");
                $this->processedCount++;
            } else {
                $this->warn("   ⚠️  No signals generated");
                $this->skippedCount++;
            }

        } catch (\Exception $e) {
            Log::error("Failed: {$instrument->underlying}", ['error' => $e->getMessage()]);
            $this->error("   ❌ Failed");
            $this->failedCount++;
        }
    }

    private function generateSignals(array $candles, $instrument, string $date): int
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

            // Heikin Ashi
            $ha = $this->calculateHeikinAshi($candle, $prevHa);

            // Futures Structure
            $structure = $this->futuresStructure($candle, $candles[$index - 1]);

            // Raw Signal
            $rawSignal = $this->rawTradeSignal($structure, $ha);

            // OI Signal
            $oiSignal = $this->oiOnlySignal(
                $candle['oi'] ?? 0,
                $candles[$index - 1]['oi'] ?? 0,
                $prevOiSignal
            );

            // Align OI + Raw
            if (
                ($rawSignal === 'BUY' && $oiSignal !== 'BULLISH') ||
                ($rawSignal === 'SELL' && $oiSignal !== 'BEARISH')
            ) {
                $rawSignal = 'NO TRADE';
            }

            // Final Signal
            $finalSignal = $this->revisedTradeLogic($rawSignal, $index, $tradeState);

            // Store
            $this->storeSignal([
                'underlying' => $instrument->underlying,
                'symbol' => $instrument->symbol,
                'token' => $instrument->token,
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

    // TRADING LOGIC FUNCTIONS (from ChatGPT conversation)
    
    private function calculateHeikinAshi(array $candle, ?array $prevHa): array
    {
        $haClose = ($candle['open'] + $candle['high'] + $candle['low'] + $candle['close']) / 4;
        $haOpen = $prevHa ? ($prevHa['open'] + $prevHa['close']) / 2 : ($candle['open'] + $candle['close']) / 2;
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

    private function futuresStructure(array $candle, array $prev): array
    {
        $priceChg = ($candle['close'] - $prev['close']) / max(0.0001, $prev['close']);
        $volChg = ($candle['volume'] - $prev['volume']) / max(1, $prev['volume']);
        $oiChg = ($candle['oi'] - $prev['oi']) / max(1, $prev['oi']);

        if ($priceChg > 0 && $oiChg > 0) return ['type' => 'LONG_BUILDUP', 'vol' => round($volChg, 4)];
        if ($priceChg < 0 && $oiChg > 0) return ['type' => 'SHORT_BUILDUP', 'vol' => round($volChg, 4)];
        if ($priceChg > 0 && $oiChg < 0) return ['type' => 'SHORT_COVERING', 'vol' => round($volChg, 4)];
        if ($priceChg < 0 && $oiChg < 0) return ['type' => 'LONG_UNWINDING', 'vol' => round($volChg, 4)];

        return ['type' => 'NEUTRAL', 'vol' => round($volChg, 4)];
    }

    private function rawTradeSignal(array $structure, array $ha): string
    {
        if ($ha['strength'] < 0.5) return 'NO TRADE';

        if (in_array($structure['type'], ['LONG_BUILDUP', 'SHORT_COVERING']) && $structure['vol'] > 0 && $ha['color'] === 'GREEN') {
            return 'BUY';
        }

        if (in_array($structure['type'], ['SHORT_BUILDUP', 'LONG_UNWINDING']) && $structure['vol'] > 0 && $ha['color'] === 'RED') {
            return 'SELL';
        }

        return 'NO TRADE';
    }

    private function oiOnlySignal(float $currOi, float $prevOi, ?string $prevSignal): string
    {
        if ($currOi > $prevOi) return 'BULLISH';
        if ($currOi < $prevOi) return 'BEARISH';
        return $prevSignal ?? 'NO SIGNAL';
    }

    private function revisedTradeLogic(string $rawSignal, int $index, \stdClass $state): string
    {
        $COOLDOWN = 3;
        $CONFIRM = 2;

        if ($state->current === null) {
            if (in_array($rawSignal, ['BUY', 'SELL'])) {
                $state->current = $rawSignal;
                $state->entryIndex = $index;
            }
            return $state->current ?? 'NO TRADE';
        }

        $candlesInTrade = $index - $state->entryIndex;

        if ($rawSignal !== 'NO TRADE' && $rawSignal !== $state->current) {
            $state->oppositeCount++;
        } else {
            $state->oppositeCount = 0;
        }

        if ($state->oppositeCount >= $CONFIRM && $candlesInTrade >= $COOLDOWN) {
            $state->current = $rawSignal;
            $state->entryIndex = $index;
            $state->oppositeCount = 0;
        }

        return $state->current;
    }

    private function storeSignal(array $data)
    {
        FuturesTradingSignal::updateOrCreate(
            ['token' => $data['token'], 'candle_time' => $data['candle_time']],
            $data
        );
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info("════════════════════════════════════");
        $this->info("   SIGNAL GENERATION SUMMARY        ");
        $this->info("════════════════════════════════════");
        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Processed', $this->processedCount],
                ['⚠️  Skipped', $this->skippedCount],
                ['❌ Failed', $this->failedCount]
            ]
        );
        $this->info("════════════════════════════════════\n");
    }
}