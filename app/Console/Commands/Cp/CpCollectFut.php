<?php
// FILE: app/Console/Commands/Cp/CpCollectFut.php

namespace App\Console\Commands\Cp;

use Illuminate\Console\Command;
use App\Traits\CpOhlcBase;
use App\Traits\OptionExpiryResolver;
use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CpCollectFut extends Command
{
    // ── Trait collision fix ───────────────────────────────────────────────────
    // Both CpOhlcBase and OptionExpiryResolver define isMarketHoliday().
    // We keep OptionExpiryResolver's version (used internally by the trait's
    // resolveExpiries / tradingDaysBetween methods).
    // CpOhlcBase's version is renamed cpIsMarketHoliday — used explicitly here.
    use CpOhlcBase, OptionExpiryResolver {
        OptionExpiryResolver::isMarketHoliday insteadof CpOhlcBase;
        CpOhlcBase::cpIsMarketHoliday as cpIsMarketHoliday;
    }

    protected $signature = 'cp:collect-fut
                            {--timeframe=15min   : 15min | 30min | 1hr}
                            {--from=             : Start date Y-m-d (historical)}
                            {--to=               : End date Y-m-d (default today)}
                            {--symbol=           : Limit to specific symbol}
                            {--retry=3           : API retries per call}
                            {--retry-delay=2     : Seconds between retries}
                            {--chunk=100         : Upsert batch size}';

    protected $description = 'Collect intraday FUT OHLC+Volume+OI into cp_fut_ohlc_{timeframe} table';

    public function handle(): int
    {
        $timeframe  = $this->option('timeframe');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $table      = "cp_fut_ohlc_{$timeframe}";
        $now        = Carbon::now();

        $this->info("════════════════════════════════════════════");
        $this->info("  📊 CP FUT OHLC Collector [{$timeframe}]");
        $this->info("  " . $now->format('Y-m-d H:i:s'));
        $this->info("════════════════════════════════════════════");

        if (!in_array($timeframe, ['15min', '30min', '1hr'])) {
            $this->error("❌ Invalid timeframe. Use: 15min | 30min | 1hr");
            return 1;
        }

        $config = $this->loadConfig($timeframe);
        if (!$config) return 1;
        if (!$this->initBroker($config)) return 1;

        $symbols = $config->symbols->pluck('symbol')->toArray();
        if ($specSymbol) {
            $symbols = in_array($specSymbol, $symbols) ? [$specSymbol] : [];
        }

        if (empty($symbols)) {
            $this->error("❌ No symbols in config.");
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));

        [$dateFrom, $dateTo] = $this->resolveDateRange();
        if (!$dateFrom) return 1;

        $isHistorical = $this->option('from') !== null;
        $this->info("   Mode: " . ($isHistorical
            ? "HISTORICAL {$dateFrom->toDateString()} → {$dateTo->toDateString()}"
            : "LIVE today"));

        if (!$isHistorical) {
            if ($dateFrom->isWeekend() || $this->cpIsMarketHoliday($dateFrom->toDateString())) {
                $this->warn("⏭  Weekend or market holiday — nothing to collect.");
                return 0;
            }
        }

        $this->newLine();

        $tradingDays = $this->getTradingDays($dateFrom, $dateTo);
        if (empty($tradingDays)) {
            $this->warn("⏭  No trading days in range.");
            return 0;
        }

        $totalUpserted = 0;
        $totalSkipped  = 0;
        $totalFailed   = 0;

        foreach ($symbols as $symbol) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("   📊 {$symbol}");

            foreach ($tradingDays as $date) {
                $dateStr = $date->toDateString();

                // ── Single expiry only ────────────────────────────────────────
                $expiry = $this->resolveSingleExpiry($symbol, $date);
                if (!$expiry) {
                    $this->warn("   ⚠️  No expiry for {$symbol} on {$dateStr} — skipping");
                    continue;
                }

                // ── FUT instrument ────────────────────────────────────────────
                $futInstrument = $this->resolveFutInstrument($symbol, $expiry);
                if (!$futInstrument) {
                    $this->warn("   ⚠️  FUT instrument not found — {$symbol} [{$expiry}] on {$dateStr}");
                    Log::warning("CpCollectFut: FUT not found — {$symbol} expiry {$expiry} on {$dateStr}");
                    $totalFailed++;
                    continue;
                }

                $this->info("   [{$dateStr}] Expiry: {$expiry} | {$futInstrument->trading_symbol}");

                // ── New series backfill ────────────────────────────────────────
                $backfillDays = $this->detectNewSeriesBackfillDays(
                    $table, $config->id, $symbol, $expiry, $date, 'base_symbol'
                );

                if (!empty($backfillDays)) {
                    $this->info("   🔄 New series [{$expiry}] — backfilling " . count($backfillDays) . " days");
                    foreach ($backfillDays as $bfDate) {
                        $upserted = $this->collectFutDay(
                            $table, $config->id, $symbol, $futInstrument,
                            $expiry, $bfDate, $timeframe, $maxRetries, $retryDelay, $chunkSize, true
                        );
                        $totalUpserted += $upserted;
                        $this->info("   ✅ Backfill {$bfDate->toDateString()} — {$upserted} rows");
                        usleep(400_000);
                    }
                }

                // ── Normal collection ─────────────────────────────────────────
                $allSlots = $this->generateIntervals($date, $timeframe);

                if (!$isHistorical) {
                    $lastSlot = $this->getLastCompletedSlot($now, $date, $timeframe);
                    $allSlots = array_values(array_filter(
                        $allSlots, fn($t) => $t->lte($lastSlot)
                    ));
                    if (empty($allSlots)) {
                        $this->warn("   ⏳ No completed candle yet.");
                        continue;
                    }
                }

                $stored = \DB::table($table)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->where('expiry_date', $expiry)
                    ->whereDate('trade_date', $dateStr)
                    ->where('is_missing', false)
                    ->pluck('interval_time')
                    ->map(fn($t) => Carbon::parse($t)->format('H:i'))
                    ->flip()
                    ->toArray();

                $missing = array_values(array_filter(
                    $allSlots, fn($t) => !isset($stored[$t->format('H:i')])
                ));

                if (empty($missing)) {
                    $this->line("   ✓ {$symbol} {$dateStr} [{$expiry}] — up to date");
                    $totalSkipped++;
                    continue;
                }

                $upserted = $this->collectFutDay(
                    $table, $config->id, $symbol, $futInstrument,
                    $expiry, $date, $timeframe, $maxRetries, $retryDelay, $chunkSize,
                    false, $missing
                );

                $totalUpserted += $upserted;
                $this->info("   ✅ {$symbol} {$dateStr} [{$expiry}] — {$upserted} rows");
                usleep(300_000);
            }
        }

        $this->printCpSummary(
            'FUT OHLC', $timeframe,
            $dateFrom->toDateString(), $dateTo->toDateString(),
            $totalUpserted, $totalSkipped, $totalFailed
        );

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Single expiry (no dual-expiry)
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveSingleExpiry(string $symbol, Carbon $date): ?string
    {
        $expiries = $this->resolveExpiries($symbol, $date);
        if (empty($expiries)) return null;

        $expiry = $expiries[0];

        if (Carbon::parse($expiry)->isSameDay($date)) {
            $next = $expiries[1] ?? $this->fetchNextSingleExpiry($symbol, $date);
            if ($next) {
                $this->warn("   ⚠️  {$symbol}: expiry day — shifted to {$next}");
                $expiry = $next;
            }
        }

        return $expiry;
    }

    private function fetchNextSingleExpiry(string $symbol, Carbon $afterDate): ?string
    {
        $exchange = $this->getCpExchange($symbol);
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);

        $expiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>', $afterDate)
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) return null;

        if (!$isWeekly) {
            $byMonth = [];
            foreach ($expiries as $exp) {
                $byMonth[Carbon::parse($exp)->format('Y-m')] = $exp;
            }
            $expiries = array_values($byMonth);
        }

        return $expiries[0] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Collect FUT for one day (used for both normal + backfill)
    // ─────────────────────────────────────────────────────────────────────────

    private function collectFutDay(
        string $table,
        int    $configId,
        string $symbol,
        ZerodhaInstrument $futInstrument,
        string $expiry,
        Carbon $date,
        string $timeframe,
        int    $maxRetries,
        int    $retryDelay,
        int    $chunkSize,
        bool   $isBackfill = false,
        array  $slotsToProcess = []
    ): int {
        $dateStr  = $date->toDateString();
        $allSlots = empty($slotsToProcess)
            ? $this->generateIntervals($date, $timeframe)
            : $slotsToProcess;

        $candles   = $this->fetchDayCandles($futInstrument->instrument_token, $date, $timeframe, $maxRetries, $retryDelay);
        $candleMap = $this->indexCandlesByTime($candles);
        $nowStr    = now()->toDateTimeString();

        // Freeze ATM at 09:15 close
        $atmStrike = 0;
        if (isset($candleMap['09:15'])) {
            $interval  = $this->resolveStrikeInterval($symbol, $expiry) ?? 50;
            $atmStrike = round($candleMap['09:15']->close / $interval) * $interval;
        }

        $rows = [];
        foreach ($allSlots as $slot) {
            $timeKey = $slot->format('H:i');
            $candle  = $candleMap[$timeKey] ?? null;

            $rows[] = [
                'analysis_config_id' => $configId,
                'broker_api_id'      => $this->cpBroker->id,
                'base_symbol'        => $symbol,
                'trading_symbol'     => $futInstrument->trading_symbol,
                'instrument_token'   => $futInstrument->instrument_token,
                'expiry_date'        => $expiry,
                'atm_strike'         => $atmStrike,
                'trade_date'         => $dateStr,
                'interval_time'      => $slot->toDateTimeString(),
                'open'               => $candle ? $candle->open   : 0,
                'high'               => $candle ? $candle->high   : 0,
                'low'                => $candle ? $candle->low    : 0,
                'close'              => $candle ? $candle->close  : 0,
                'volume'             => $candle ? $candle->volume : 0,
                'oi'                 => $candle ? ($candle->oi ?? 0) : 0,
                'is_missing'         => $candle ? 0 : 1,
                'is_backfill'        => $isBackfill ? 1 : 0,
                'created_at'         => $nowStr,
                'updated_at'         => $nowStr,
            ];
        }

        return $this->batchUpsertRows(
            $table, $rows,
            ['analysis_config_id', 'base_symbol', 'expiry_date', 'trade_date', 'interval_time'],
            ['open', 'high', 'low', 'close', 'volume', 'oi', 'atm_strike', 'is_missing', 'is_backfill', 'updated_at'],
            $chunkSize
        );
    }
}