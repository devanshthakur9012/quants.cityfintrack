<?php
// FILE: app/Console/Commands/Cp/CpCollectOption.php

namespace App\Console\Commands\Cp;

use Illuminate\Console\Command;
use App\Traits\CpOhlcBase;
use App\Traits\OptionExpiryResolver;
use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CpCollectOption extends Command
{
    // ── Trait collision fix ───────────────────────────────────────────────────
    // Both CpOhlcBase and OptionExpiryResolver define isMarketHoliday().
    // OptionExpiryResolver's version is kept (used by resolveExpiries internally).
    // CpOhlcBase's version is available as cpIsMarketHoliday.
    use CpOhlcBase, OptionExpiryResolver {
        OptionExpiryResolver::isMarketHoliday insteadof CpOhlcBase;
        CpOhlcBase::cpIsMarketHoliday as cpIsMarketHoliday;
    }

    protected $signature = 'cp:collect-option
                            {--timeframe=15min   : 15min | 30min | 1hr}
                            {--from=             : Start date Y-m-d (historical)}
                            {--to=               : End date Y-m-d (default today)}
                            {--symbol=           : Limit to specific symbol}
                            {--retry=3           : API retries per call}
                            {--retry-delay=2     : Seconds between retries}
                            {--chunk=100         : Upsert batch size}';

    protected $description = 'Collect intraday CE/PE OHLC+Volume+OI into cp_option_ohlc_{timeframe} table';

    public function handle(): int
    {
        $timeframe  = $this->option('timeframe');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $optTable   = "cp_option_ohlc_{$timeframe}";
        $futTable   = "cp_fut_ohlc_{$timeframe}";
        $now        = Carbon::now();

        $this->info("════════════════════════════════════════════");
        $this->info("  🎯 CP Option OHLC Collector [{$timeframe}]");
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
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("   📊 {$symbol}");

            foreach ($tradingDays as $date) {
                $dateStr = $date->toDateString();

                // ── Single expiry ─────────────────────────────────────────────
                $expiry = $this->resolveSingleExpiry($symbol, $date);
                if (!$expiry) {
                    $this->warn("   ⚠️  No expiry for {$symbol} on {$dateStr} — skipping");
                    continue;
                }

                // CE/PE expiry may differ from FUT on expiry day
                $cepeExpiry = $this->resolveCePeExpiry($symbol, $expiry, $date);

                $this->info("   [{$dateStr}] FUT expiry: {$expiry} | CE/PE expiry: {$cepeExpiry}");

                // ── FUT instrument (for ATM freeze) ───────────────────────────
                $futInstrument = $this->resolveFutInstrument($symbol, $expiry);
                if (!$futInstrument) {
                    $this->warn("   ⚠️  FUT instrument not found — {$symbol} [{$expiry}]");
                    Log::warning("CpCollectOption: FUT not found — {$symbol} expiry {$expiry} on {$dateStr}");
                    $totalFailed++;
                    continue;
                }

                // ── Strike interval ───────────────────────────────────────────
                $strikeInterval = $this->resolveStrikeInterval($symbol, $cepeExpiry);
                if ($strikeInterval === null) {
                    $this->error("   ✗ Strike interval unknown — {$symbol} [{$cepeExpiry}]");
                    Log::error("CpCollectOption: Strike interval unknown — {$symbol} [{$cepeExpiry}] on {$dateStr}");
                    $totalFailed++;
                    continue;
                }

                // ── Prewarm option cache ──────────────────────────────────────
                $cached = $this->prewarmOptionCache($symbol, $cepeExpiry);
                $this->info("   Cached {$cached} CE/PE instruments | Interval: {$strikeInterval}");

                // ── New series backfill ────────────────────────────────────────
                $backfillDays = $this->detectNewSeriesBackfillDays(
                    $optTable, $config->id, $symbol, $cepeExpiry, $date
                );

                if (!empty($backfillDays)) {
                    $this->info("   🔄 New series [{$cepeExpiry}] — backfilling " . count($backfillDays) . " days");
                    foreach ($backfillDays as $bfDate) {
                        $upserted = $this->collectOptionDay(
                            $optTable, $futTable, $config->id,
                            $symbol, $futInstrument,
                            $expiry, $cepeExpiry, $strikeInterval,
                            $bfDate, $timeframe, $maxRetries, $retryDelay, $chunkSize,
                            true
                        );
                        $totalUpserted += $upserted;
                        $this->info("   ✅ Backfill {$bfDate->toDateString()} — {$upserted} rows");
                        usleep(500_000);
                    }
                }

                // ── Intervals for this day ────────────────────────────────────
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

                // ── Already stored check (CE check sufficient) ────────────────
                $stored = \DB::table($optTable)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->where('expiry_date', $cepeExpiry)
                    ->whereDate('trade_date', $dateStr)
                    ->where('instrument_type', 'CE')
                    ->where('is_missing', false)
                    ->pluck('interval_time')
                    ->map(fn($t) => Carbon::parse($t)->format('H:i'))
                    ->flip()
                    ->toArray();

                $missing = array_values(array_filter(
                    $allSlots, fn($t) => !isset($stored[$t->format('H:i')])
                ));

                if (empty($missing)) {
                    $this->line("   ✓ {$symbol} {$dateStr} — up to date");
                    $totalSkipped++;
                    continue;
                }

                $this->info("   Fetching {$symbol} {$dateStr} — " . count($missing) . " missing slots");

                $upserted = $this->collectOptionDay(
                    $optTable, $futTable, $config->id,
                    $symbol, $futInstrument,
                    $expiry, $cepeExpiry, $strikeInterval,
                    $date, $timeframe, $maxRetries, $retryDelay, $chunkSize,
                    false, $missing
                );

                $totalUpserted += $upserted;
                $this->info("   ✅ {$symbol} {$dateStr} — {$upserted} rows upserted");
            }
        }

        $this->printCpSummary(
            'Option OHLC', $timeframe,
            $dateFrom->toDateString(), $dateTo->toDateString(),
            $totalUpserted, $totalSkipped, $totalFailed
        );

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core: collect one symbol × one day
    // ─────────────────────────────────────────────────────────────────────────

    private function collectOptionDay(
        string            $optTable,
        string            $futTable,
        int               $configId,
        string            $symbol,
        ZerodhaInstrument $futInstrument,
        string            $futExpiry,
        string            $cepeExpiry,
        float             $strikeInterval,
        Carbon            $date,
        string            $timeframe,
        int               $maxRetries,
        int               $retryDelay,
        int               $chunkSize,
        bool              $isBackfill = false,
        array             $slotsToProcess = []
    ): int {
        $dateStr  = $date->toDateString();
        $allSlots = empty($slotsToProcess)
            ? $this->generateIntervals($date, $timeframe)
            : $slotsToProcess;

        // ── Fetch FUT candles for ATM freeze ──────────────────────────────────
        $futCandles   = $this->fetchDayCandles($futInstrument->instrument_token, $date, $timeframe, $maxRetries, $retryDelay);
        $futCandleMap = $this->indexCandlesByTime($futCandles);

        if (!isset($futCandleMap['09:15'])) {
            // Fallback: try ATM from already-stored fut table row for today
            $storedAtm = \DB::table($futTable)
                ->where('analysis_config_id', $configId)
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $dateStr)
                ->value('atm_strike');

            if (!$storedAtm) {
                $this->error("   ✗ 09:15 FUT candle missing and no stored ATM — {$symbol} {$dateStr} skipped");
                Log::error("CpCollectOption: Cannot determine ATM — {$symbol} [{$futExpiry}] on {$dateStr}");
                return 0;
            }

            $frozenAtm = (float) $storedAtm;
            $this->warn("   ⚠️  09:15 FUT missing — using stored ATM: {$frozenAtm}");
        } else {
            $frozenAtm = round($futCandleMap['09:15']->close / $strikeInterval) * $strikeInterval;
        }

        $strikes = $this->buildStrikeList($frozenAtm, $strikeInterval);
        $this->info("   ATM frozen: {$frozenAtm} | Strikes: " . implode(', ', $strikes));

        // ── Fetch CE/PE candles for each strike ───────────────────────────────
        $optionCandleCache = []; // [token => ['HH:MM' => candle]]
        $fetchCount        = 0;

        foreach (['CE', 'PE'] as $type) {
            foreach ($strikes as $strike) {
                $inst = $this->getOptionInstrument($symbol, $strike, $type, $cepeExpiry);
                if (!$inst) {
                    $this->warn("   ⚠️  Instrument missing: {$type} {$strike} [{$cepeExpiry}]");
                    Log::warning("CpCollectOption: Instrument not in cache — {$symbol} {$type} {$strike} [{$cepeExpiry}]");
                    continue;
                }

                if ($fetchCount > 0) usleep(400_000); // 400ms rate limit

                $candles   = $this->fetchDayCandles($inst->instrument_token, $date, $timeframe, $maxRetries, $retryDelay);
                $fetchCount++;
                $optionCandleCache[$inst->instrument_token] = $this->indexCandlesByTime($candles);

                $this->line("      {$type} {$strike}: " . count($candles) . " candles");
            }
        }

        // ── Build rows ────────────────────────────────────────────────────────
        $rows              = [];
        $nowStr            = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($allSlots as $slot) {
            $timeKey   = $slot->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;
            if ($futCandle !== null) {
                $lastKnownFutClose = $futCandle->close;
            }

            foreach (['CE', 'PE'] as $type) {
                foreach ($strikes as $strike) {
                    $inst = $this->getOptionInstrument($symbol, $strike, $type, $cepeExpiry);
                    if (!$inst) continue;

                    $token     = $inst->instrument_token;
                    $candle    = $optionCandleCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    $rows[] = [
                        'analysis_config_id' => $configId,
                        'broker_api_id'      => $this->cpBroker->id,
                        'base_symbol'        => $symbol,
                        'fut_trading_symbol' => $futInstrument->trading_symbol,
                        'future_price'       => $lastKnownFutClose ?? 0,
                        'trading_symbol'     => $inst->trading_symbol,
                        'instrument_token'   => $token,
                        'expiry_date'        => $cepeExpiry,
                        'instrument_type'    => $type,
                        'atm_strike'         => $frozenAtm,
                        'strike'             => $strike,
                        'strike_position'    => $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                        'trade_date'         => $dateStr,
                        'interval_time'      => $slot->toDateTimeString(),
                        'open'               => $candle ? $candle->open   : 0,
                        'high'               => $candle ? $candle->high   : 0,
                        'low'                => $candle ? $candle->low    : 0,
                        'close'              => $candle ? $candle->close  : 0,
                        'volume'             => $candle ? $candle->volume : 0,
                        'oi'                 => $candle ? ($candle->oi ?? 0) : 0,
                        'is_missing'         => $isMissing ? 1 : 0,
                        'is_backfill'        => $isBackfill ? 1 : 0,
                        'created_at'         => $nowStr,
                        'updated_at'         => $nowStr,
                    ];
                }
            }
        }

        return $this->batchUpsertRows(
            $optTable, $rows,
            ['analysis_config_id', 'trading_symbol', 'trade_date', 'interval_time'],
            ['future_price', 'atm_strike', 'open', 'high', 'low', 'close', 'volume', 'oi', 'is_missing', 'is_backfill', 'updated_at'],
            $chunkSize
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Expiry helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveSingleExpiry(string $symbol, Carbon $date): ?string
    {
        $expiries = $this->resolveExpiries($symbol, $date);
        if (empty($expiries)) return null;

        $expiry = $expiries[0];

        if (Carbon::parse($expiry)->isSameDay($date)) {
            $next = $expiries[1] ?? $this->fetchNextExpiry($symbol, $date);
            if ($next) {
                $this->warn("   ⚠️  {$symbol}: expiry day — shifted to {$next}");
                $expiry = $next;
            }
        }

        return $expiry;
    }

    private function resolveCePeExpiry(string $symbol, string $futExpiry, Carbon $date): string
    {
        if (Carbon::parse($futExpiry)->isSameDay($date)) {
            $next = $this->fetchNextExpiry($symbol, $date);
            return $next ?? $futExpiry;
        }
        return $futExpiry;
    }

    private function fetchNextExpiry(string $symbol, Carbon $afterDate): ?string
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
}