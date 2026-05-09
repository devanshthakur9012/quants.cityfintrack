<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\NextSeriesOptionOhlcData;
use App\Models\OptionSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NextSeriesLiveOptionOhlcCollector
 *
 * ═══════════════════════════════════════════════════════════════════
 * IDENTICAL to LiveOptionOhlcCollector EXCEPT:
 *   1. Targets the NEXT expiry series (April while March is live)
 *   2. Stores into next_series_option_ohlc_data table
 *   3. No dual-expiry — always exactly ONE next expiry per symbol
 *   4. Better rate limiting (rolling 60s window + 429 handler)
 *
 * Everything else is 1:1 same:
 *   ✅ ATM±5 strikes (11 total) — same as original LiveOptionOhlcCollector
 *   ✅ ATM frozen at 09:15 close
 *   ✅ Dynamic strike interval from ZerodhaInstrument table
 *   ✅ One row per 15-min slot per instrument (25 rows/day/instrument)
 *   ✅ Gap-fill: missing intervals fetched on every run
 *   ✅ Fast exit before 09:31 (zero DB overhead)
 *   ✅ Batch upsert, zero-gap fill
 * ═══════════════════════════════════════════════════════════════════
 *
 * NEXT EXPIRY RESOLUTION:
 *   Weekly (NIFTY, SENSEX):
 *     Sorted upcoming: [Mar27, Apr03, Apr10 ...]
 *     index[0] = current live → index[1] = next → collected
 *
 *   Monthly (stocks, BANKNIFTY etc.):
 *     Groups by Y-m → second month's expiry collected
 *     e.g. processing in March → collects April expiry
 *
 * RATE LIMITING (two-layer):
 *   Layer 1 — Per-call delay: 400ms live / 1000ms historical
 *   Layer 2 — Rolling 60s window: auto-pause 65s when ≥150 calls/min
 *             + direct 429 handler with window reset
 *
 * KERNEL SETUP:
 *   $schedule->command('options:next-series-collect')
 *       ->cron('1,16,31,46 * * * *')
 *       ->weekdays()
 *       ->between('9:30', '15:40')
 *       ->appendOutputTo(storage_path('logs/next-series-ohlc-collect.log'));
 *
 * HISTORICAL BACKFILL:
 *   php artisan options:next-series-collect --from-date=2026-01-01 --to-date=2026-03-26
 *   php artisan options:next-series-collect --force-date=2026-03-26
 *   php artisan options:next-series-collect --force-date=2026-03-26 --symbol=NIFTY
 */
class NextSeriesLiveOptionOhlcCollector extends Command
{
    use OptionExpiryResolver;

    // ── Broker ────────────────────────────────────────────────────────────────
    private const BROKER_CLIENT_ID = 'DB0542';

    // ── ATM freeze — same as LiveOptionOhlcCollector ──────────────────────────
    private const ATM_FREEZE_TIME       = '09:15';
    private const COLLECTION_START_TIME = '09:31';

    // ── Market hours ──────────────────────────────────────────────────────────
    private const MARKET_START = '09:15';
    private const MARKET_END   = '15:15';

    // ── Rate limiting (improved over original) ────────────────────────────────
    private const RATE_LIMIT_PER_MINUTE    = 150;   // Zerodha cap ~180/min; 150 = safe buffer
    private const RATE_LIMIT_PAUSE_SECONDS = 65;    // pause duration when cap hit
    private const DELAY_LIVE_US            = 400_000;    // 400ms between calls (live)
    private const DELAY_HISTORICAL_US      = 1_000_000;  // 1000ms between calls (historical)

    protected $signature = 'options:next-series-collect
                            {--symbol=       : Specific symbol (e.g. NIFTY)}
                            {--retry=3       : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50      : Batch upsert chunk size}
                            {--force-date=   : Single day override (Y-m-d)}
                            {--from-date=    : Historical start date (Y-m-d)}
                            {--to-date=      : Historical end date (Y-m-d)}';

    protected $description =
        'Next-series 15-min OHLC collector — same as live collector (ATM±5, 11 strikes, '
        . 'frozen ATM, gap-fill) but targets NEXT expiry series into next_series_option_ohlc_data.';

    // ── Caches ────────────────────────────────────────────────────────────────
    private array $instrumentCache     = [];
    private array $strikeIntervalCache = [];

    // ── API helper ────────────────────────────────────────────────────────────
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ── Rate limiter state ────────────────────────────────────────────────────
    private array $apiCallTimestamps = [];
    private bool  $isHistoricalMode  = false;
    private int   $totalApiCalls     = 0;

    // ══════════════════════════════════════════════════════════════════════════
    // ENTRY POINT
    // ══════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $now      = Carbon::now();
        $fromDate = $this->option('from-date') ?? $this->option('force-date');

        // ── Fast exit before 09:31 (zero DB overhead) ─────────────────────────
        if ($fromDate === null) {
            $collectionStart = Carbon::today()->setTimeFromTimeString(self::COLLECTION_START_TIME);
            if ($now->lt($collectionStart)) {
                $this->line('⏳ [' . $now->format('H:i') . '] Waiting for ATM freeze candle at '
                    . self::ATM_FREEZE_TIME . ' — nothing to do yet. Exiting.');
                return 0;
            }
        }

        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $toDate     = $this->option('to-date') ?? ($fromDate ? $fromDate : null);

        $this->isHistoricalMode = ($fromDate !== null);

        if ($this->isHistoricalMode) {
            $dateFrom = Carbon::parse($fromDate)->startOfDay();
            $dateTo   = Carbon::parse($toDate)->startOfDay();
            if ($dateFrom->gt($dateTo)) {
                $this->error('❌ --from-date must be <= --to-date');
                return 1;
            }
        } else {
            $dateFrom = $dateTo = Carbon::today();
        }

        $delayLabel = $this->isHistoricalMode
            ? (self::DELAY_HISTORICAL_US / 1000) . 'ms (historical)'
            : (self::DELAY_LIVE_US / 1000) . 'ms (live)';

        $this->info('⚡ Next-Series 15-Min OHLC Collector — ' . $now->format('Y-m-d H:i:s'));
        $this->info('   Broker   : ' . self::BROKER_CLIENT_ID
            . ' | Target: NEXT expiry'
            . ' | Strikes: ATM±5 (11 total)'
            . ' | ATM freeze: ' . self::ATM_FREEZE_TIME
            . ' | Rate cap: ' . self::RATE_LIMIT_PER_MINUTE . '/min'
            . ' | Delay: ' . $delayLabel);

        if ($this->isHistoricalMode) {
            $this->info('   Mode     : HISTORICAL — ' . $dateFrom->toDateString() . ' → ' . $dateTo->toDateString());
            $this->warn('   ⚠️  Run historical backfills AFTER 15:30 to avoid consuming live API quota!');
        } else {
            $this->info('   Mode     : LIVE — ' . $dateFrom->toDateString());
        }
        $this->info('   Rollover : ' . self::ROLLOVER_TRADING_DAYS . ' trading days before expiry');
        $this->newLine();

        // ── Load broker ───────────────────────────────────────────────────────
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()->validToken()->first();

        if (!$broker) {
            $this->error('❌ Broker [' . self::BROKER_CLIENT_ID . '] not found or token invalid!');
            $this->line('   Change BROKER_CLIENT_ID constant to fix.');
            return 1;
        }
        $this->info("🔑 Broker  : {$broker->client_name} (ID: {$broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);

        // ── Load symbols (same option_symbols table as live collector) ─────────
        $symbolsQuery = OptionSymbol::active();
        if ($specSymbol) $symbolsQuery->where('symbol', $specSymbol);
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols found in option_symbols table!');
            return 1;
        }
        $this->info('   Symbols  : ' . implode(', ', $symbols) . ' (' . count($symbols) . ')');
        $this->newLine();

        // ── Build trading days ────────────────────────────────────────────────
        $tradingDays = [];
        $cursor = $dateFrom->copy();
        while ($cursor->lte($dateTo)) {
            if (!$cursor->isWeekend() && !$this->isMarketHoliday($cursor->toDateString())) {
                $tradingDays[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        if (empty($tradingDays)) {
            $this->warn('⏭  No trading days in range.');
            return 0;
        }
        $this->info('   Trading days: ' . count($tradingDays));
        $this->newLine();

        // ── Date loop ─────────────────────────────────────────────────────────
        $totalDays = count($tradingDays);
        foreach ($tradingDays as $dayIdx => $date) {
            $dayNum = $dayIdx + 1;
            $this->info('════════════════════════════════════════════════════════');
            $this->info("📅 Day {$dayNum}/{$totalDays} — {$date->toDateString()}");
            $this->info('════════════════════════════════════════════════════════');

            // Reset per-day caches
            $this->instrumentCache     = [];
            $this->strikeIntervalCache = [];

            // Determine intervals for this day
            $allIntervals = $this->generateTradingIntervals($date);

            if ($this->isHistoricalMode) {
                $intervalsToProcess = $allIntervals;
            } else {
                $lastCompletedSlot  = $this->getLastCompletedSlot($now, $date);
                $intervalsToProcess = array_values(array_filter(
                    $allIntervals,
                    fn($t) => $t->lte($lastCompletedSlot)
                ));

                if (empty($intervalsToProcess)) {
                    $this->warn('   ⏳ Market not started or no completed candle yet.');
                    return 0;
                }

                $this->info('   Last completed slot : ' . $lastCompletedSlot->format('H:i'));
                $this->info('   Intervals to cover  : 09:15 → ' . $lastCompletedSlot->format('H:i')
                    . ' (' . count($intervalsToProcess) . ' slots)');
            }

            $this->info('   Intervals : 09:15 → 15:15 (' . count($intervalsToProcess) . ' slots)');
            $this->newLine();

            // ── Symbol loop ───────────────────────────────────────────────────
            foreach ($symbols as $baseSymbol) {
                // Resolve NEXT expiry for this symbol on this date
                $nextExpiry = $this->resolveNextSeriesExpiry($baseSymbol, $date);

                if ($nextExpiry === null) {
                    $this->warn("   ⚠️  {$baseSymbol} — cannot resolve next expiry for {$date->toDateString()}, skipping");
                    Log::warning("NextSeriesCollector: Cannot resolve next expiry for {$baseSymbol} on {$date->toDateString()}");
                    continue;
                }

                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info("   📊 {$baseSymbol} — next expiry: {$nextExpiry}");

                // Prewarm instrument cache for next expiry
                $this->prewarmInstrumentCacheForExpiry($baseSymbol, $nextExpiry);

                // Resolve FUT instrument for next expiry
                $futInstrument = $this->resolveFutInstrument($baseSymbol, $nextExpiry);

                if (!$futInstrument) {
                    $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} next expiry {$nextExpiry} — skipping");
                    Log::warning("NextSeriesCollector: No FUT for {$baseSymbol} next expiry {$nextExpiry}");
                    continue;
                }

                $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $nextExpiry);

                if ($strikeInterval === null) {
                    $this->error("      ✗ {$baseSymbol} [{$nextExpiry}] — strike interval unknown, SKIPPED");
                    Log::error("NextSeriesCollector: Strike interval unknown for {$baseSymbol} {$nextExpiry}");
                    continue;
                }

                $this->info("      Strike interval for {$baseSymbol} [{$nextExpiry}]: {$strikeInterval}");

                $this->processSymbolExpiry(
                    $broker, $baseSymbol, $futInstrument,
                    $nextExpiry, $strikeInterval,
                    $date, $intervalsToProcess,
                    $maxRetries, $retryDelay, $chunkSize
                );
            }

            $this->newLine();
            $this->info("✅ Day {$dayNum}/{$totalDays} complete — {$date->toDateString()} | API calls: {$this->totalApiCalls}");
            $this->newLine();
        }

        $this->info('🏁 All done — ' . Carbon::now()->format('H:i:s') . " | Total API calls: {$this->totalApiCalls}");

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEXT EXPIRY RESOLUTION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Returns the expiry ONE step ahead of the currently live one.
     *
     * Weekly (NIFTY, SENSEX):
     *   Sorted upcoming: [Mar27, Apr03, Apr10 ...]
     *   index[0] = current live → index[1] = next → returned
     *
     * Monthly (stocks, BANKNIFTY etc.):
     *   Groups by Y-m → second month group returned
     *   Processing in March → returns April expiry
     */
    private function resolveNextSeriesExpiry(string $symbol, Carbon $date): ?string
    {
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);

        $allExpiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()->values()->toArray();

        if (empty($allExpiries)) return null;

        if ($isWeekly) {
            // index[0] = current live weekly, index[1] = next weekly
            return $allExpiries[1] ?? $allExpiries[0] ?? null;
        }

        // Monthly: group by calendar month, take second month
        $byMonth = [];
        foreach ($allExpiries as $exp) {
            $byMonth[Carbon::parse($exp)->format('Y-m')] = $exp;
        }
        $months = array_values($byMonth);
        return $months[1] ?? $months[0] ?? null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RATE LIMITER
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Call BEFORE every API fetch.
     * 1. Purge timestamps older than 60s from rolling window.
     * 2. If calls in last 60s >= cap → pause 65s → clear window.
     * 3. Sleep per-call delay (skip for first call in a batch).
     */
    private function rateLimitedSleep(bool $isFirstInBatch = false): void
    {
        $now = microtime(true);

        $this->apiCallTimestamps = array_values(array_filter(
            $this->apiCallTimestamps,
            fn($t) => ($now - $t) < 60.0
        ));

        $inWindow = count($this->apiCallTimestamps);
        if ($inWindow >= self::RATE_LIMIT_PER_MINUTE) {
            $pause = self::RATE_LIMIT_PAUSE_SECONDS;
            $this->warn("      ⏸  Rate cap: {$inWindow} calls/60s (cap " . self::RATE_LIMIT_PER_MINUTE . ") — pausing {$pause}s");
            Log::warning("NextSeriesCollector: Rate cap hit ({$inWindow}). Pausing {$pause}s.");
            sleep($pause);
            $this->apiCallTimestamps = [];
        }

        if (!$isFirstInBatch) {
            usleep($this->isHistoricalMode ? self::DELAY_HISTORICAL_US : self::DELAY_LIVE_US);
        }
    }

    private function recordApiCall(): void
    {
        $this->apiCallTimestamps[] = microtime(true);
        $this->totalApiCalls++;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PROCESS ONE SYMBOL × ONE (NEXT) EXPIRY
    // ══════════════════════════════════════════════════════════════════════════

    private function processSymbolExpiry(
        BrokerApi $broker,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        string $nextExpiry,
        float $strikeInterval,
        Carbon $date,
        array $intervalsToProcess,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): void {
        // ── Already-stored check (O(1) lookup) ────────────────────────────────
        $storedTimes = NextSeriesOptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('trading_symbol', $futInstrument->trading_symbol)
            ->where('is_missing', 0)
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->flip()->toArray();

        $missingIntervals = array_values(array_filter(
            $intervalsToProcess,
            fn($t) => !isset($storedTimes[$t->format('H:i')])
        ));

        if (empty($missingIntervals)) {
            $last = end($intervalsToProcess);
            $this->info("      ✓ {$baseSymbol} [{$nextExpiry}] — up to date (latest: "
                . ($last ? $last->format('H:i') : '—') . ')');
            return;
        }

        $missingCount = count($missingIntervals);
        $gapCount     = $missingCount - 1;
        $currentSlot  = end($missingIntervals);

        if ($gapCount > 0) {
            $this->info("      🔄 {$baseSymbol} [{$nextExpiry}] — gap-filling {$gapCount} missed + current ("
                . $currentSlot->format('H:i') . ')');
        } else {
            $this->info("      📥 {$baseSymbol} [{$nextExpiry}] — fetching current interval "
                . $currentSlot->format('H:i'));
        }

        // ── Step 1: Fetch full-day FUT candles ────────────────────────────────
        $allFutCandles = $this->fetchDayCandles(
            $futInstrument->instrument_token, $date, $maxRetries, $retryDelay, true
        );

        if (empty($allFutCandles)) {
            $this->error("      ✗ {$baseSymbol} [{$nextExpiry}] — FUT fetch failed, skipping");
            Log::error("NextSeriesCollector: FUT fetch failed for {$baseSymbol} {$nextExpiry} on {$date->toDateString()}");
            return;
        }

        $futCandleMap = $this->indexCandlesByTime($allFutCandles);

        // ── Step 2: Freeze ATM at 09:15 close (same as LiveOptionOhlcCollector) ─
        if (!isset($futCandleMap[self::ATM_FREEZE_TIME])) {
            $this->error("      ✗ {$baseSymbol} [{$nextExpiry}] — "
                . self::ATM_FREEZE_TIME . " FUT candle missing, cannot freeze ATM");
            Log::error("NextSeriesCollector: ATM freeze candle missing for {$baseSymbol} {$nextExpiry} on {$date->toDateString()}");
            return;
        }

        $frozenAtm     = round($futCandleMap[self::ATM_FREEZE_TIME]->close / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);  // ATM±5, 11 strikes

        $this->info('      FUT ' . self::ATM_FREEZE_TIME . ' close = '
            . $futCandleMap[self::ATM_FREEZE_TIME]->close
            . " | ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");
        $this->info('      Strikes (ATM±5, 11 total): ' . implode(', ', $frozenStrikes));

        // ── Step 3: Fetch full-day option candles for next expiry ─────────────
        $optionDayCache = $this->fetchAllOptionDayCandles(
            $baseSymbol, $frozenStrikes, $nextExpiry, $date, $maxRetries, $retryDelay
        );

        // ── Step 4: Build rows ────────────────────────────────────────────────
        $rows              = [];
        $nowStr            = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($missingIntervals as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;
            if ($futCandle !== null) $lastKnownFutClose = $futCandle->close;

            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                $this->warn("      ⚠️  {$timeKey} — FUT candle missing, storing zeros (is_missing=1)");
                Log::warning("NextSeriesCollector: FUT missing at {$timeKey} for {$baseSymbol} [{$nextExpiry}] on {$date->toDateString()}");
            }

            $rows[] = $this->buildFutRow(
                $broker->id, $baseSymbol, $futInstrument,
                $futCandle, $frozenAtm, $nextExpiry,
                $date, $intervalTime, $nowStr, $isFutMissing
            );

            foreach (['CE', 'PE'] as $optionType) {
                foreach ($frozenStrikes as $strike) {
                    $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$nextExpiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;

                    if (!$instrument) {
                        Log::warning("NextSeriesCollector: Instrument not in cache — {$cacheKey} at {$timeKey} on {$date->toDateString()}");
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        $this->warn("      ⚠️  {$timeKey} {$optionType} {$strike} — missing, storing zeros");
                        Log::warning("NextSeriesCollector: Option missing — {$optionType} {$strike} at {$timeKey} for {$baseSymbol} [{$nextExpiry}] on {$date->toDateString()}");
                    }

                    $rows[] = $this->buildOptionRow(
                        $broker->id, $baseSymbol,
                        $futInstrument->trading_symbol,
                        $lastKnownFutClose,
                        $frozenAtm, $optionType, $strike,
                        $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                        $instrument, $candle, $nextExpiry,
                        $date, $intervalTime, $nowStr, $isMissing
                    );
                }
            }
        }

        // ── Step 5: Batch upsert ──────────────────────────────────────────────
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [{$nextExpiry}] — {$inserted} rows upserted ({$missingCount} intervals) | API calls: {$this->totalApiCalls}");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DYNAMIC STRIKE INTERVAL — identical to LiveOptionOhlcCollector
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveStrikeInterval(string $symbol, string $expiry): ?float
    {
        $cacheKey = "{$symbol}_{$expiry}";
        if (isset($this->strikeIntervalCache[$cacheKey])) return $this->strikeIntervalCache[$cacheKey];

        $strikes = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($s) => (float) $s)
            ->unique()->sort()->values();

        if ($strikes->count() < 2) {
            Log::warning("NextSeriesCollector: resolveStrikeInterval — < 2 CE strikes for {$symbol} {$expiry}");
            return null;
        }

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) $minGap = $gap;
        }

        if ($minGap === PHP_INT_MAX || $minGap <= 0) {
            Log::warning("NextSeriesCollector: resolveStrikeInterval — invalid gap for {$symbol} {$expiry}");
            return null;
        }

        return $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OPTION CANDLE BATCH FETCH
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionDayCandles(
        string $baseSymbol, array $strikes, string $expiry,
        Carbon $date, int $maxRetries, int $retryDelay
    ): array {
        $cache      = [];
        $firstFetch = true;

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    $this->warn("      ⚠️  Instrument not found: {$cacheKey}");
                    Log::warning("NextSeriesCollector: instrument missing from cache: {$cacheKey}");
                    continue;
                }

                $token   = $instrument->instrument_token;
                $candles = $this->fetchDayCandles($token, $date, $maxRetries, $retryDelay, $firstFetch);
                $firstFetch = false;

                if (!empty($candles)) {
                    $cache[$token] = $this->indexCandlesByTime($candles);
                    $this->info("      {$optionType} {$strike}: " . count($candles) . ' candles');
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data — zero-filled");
                    Log::warning("NextSeriesCollector: no data {$optionType} {$strike} [{$expiry}] {$date->toDateString()}");
                }
            }
        }

        return $cache;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CORE FETCH WITH RATE LIMITER
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchDayCandles(
        int $instrumentToken, Carbon $date,
        int $maxRetries, int $retryDelay,
        bool $isFirstInBatch = false
    ): array {
        $fromTime = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $toTime   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $this->rateLimitedSleep($isFirstInBatch);

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->zerodhaHelper->getHistoricalDataByToken(
                    $instrumentToken, '15minute', $fromTime, $toTime
                );
                $this->recordApiCall();
                return $data ?? [];

            } catch (Exception $e) {
                $this->recordApiCall();

                $is429 = stripos($e->getMessage(), 'too many') !== false
                    || stripos($e->getMessage(), 'rate limit') !== false
                    || stripos($e->getMessage(), '429') !== false;

                if ($attempt < $maxRetries) {
                    if ($is429) {
                        $pause = self::RATE_LIMIT_PAUSE_SECONDS;
                        $this->warn("      ⏸  429 from Zerodha — pausing {$pause}s (attempt {$attempt}/{$maxRetries})");
                        Log::warning("NextSeriesCollector: 429 on token {$instrumentToken}. Pausing {$pause}s.");
                        sleep($pause);
                        $this->apiCallTimestamps = [];
                    } else {
                        $isRateLimit = $is429;
                        $waitSec = $isRateLimit ? max($retryDelay, 2) : $retryDelay;
                        $this->warn("      ⏳ Attempt {$attempt}/{$maxRetries} failed: {$e->getMessage()} — waiting {$waitSec}s");
                        sleep($waitSec);
                    }
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts: {$e->getMessage()}");
                    Log::error("NextSeriesCollector: Fetch failed token {$instrumentToken}: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BATCH UPSERT → next_series_option_ohlc_data
    // ══════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;
        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            NextSeriesOptionOhlcData::upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                [
                    'base_symbol', 'future_symbol', 'future_price', 'atm_strike',
                    'instrument_type', 'strike', 'instrument_token',
                    'open', 'high', 'low', 'close', 'volume', 'oi',
                    'strike_position', 'expiry_date', 'is_missing', 'updated_at',
                ]
            );
            $total += count($chunk);
        }
        return $total;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ROW BUILDERS — identical structure to LiveOptionOhlcCollector
    // ══════════════════════════════════════════════════════════════════════════

    private function buildFutRow(
        int $brokerId, string $baseSymbol, ZerodhaInstrument $futInstrument,
        $candle, float $atmStrike, string $expiry,
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing = false
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'trading_symbol'   => $futInstrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futInstrument->trading_symbol,
            'future_price'     => $candle ? $candle->close : 0,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'instrument_token' => $futInstrument->instrument_token,
            'open'             => $candle ? $candle->open   : 0,
            'high'             => $candle ? $candle->high   : 0,
            'low'              => $candle ? $candle->low    : 0,
            'close'            => $candle ? $candle->close  : 0,
            'volume'           => $candle ? $candle->volume : 0,
            'oi'               => $candle ? ($candle->oi ?? 0) : 0,
            'strike_position'  => 'N/A',
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function buildOptionRow(
        int $brokerId, string $baseSymbol, string $futureSymbol, ?float $futurePrice,
        float $atmStrike, string $optionType, float $strike, string $strikePosition,
        ZerodhaInstrument $instrument, $candle, string $expiry,
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing = false
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'trading_symbol'   => $instrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futureSymbol,
            'future_price'     => $futurePrice,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => $optionType,
            'strike'           => $strike,
            'instrument_token' => $instrument->instrument_token,
            'open'             => $candle ? $candle->open   : 0,
            'high'             => $candle ? $candle->high   : 0,
            'low'              => $candle ? $candle->low    : 0,
            'close'            => $candle ? $candle->close  : 0,
            'volume'           => $candle ? $candle->volume : 0,
            'oi'               => $candle ? ($candle->oi ?? 0) : 0,
            'strike_position'  => $strikePosition,
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INSTRUMENT RESOLUTION — same as LiveOptionOhlcCollector
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);

        if ($isWeekly) {
            // Weekly options have no FUT — use nearest monthly FUT >= expiry
            return ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', $this->getExchange($symbol))
                ->where('name', $symbol)
                ->whereDate('expiry', '>=', $expiry)
                ->orderBy('expiry', 'ASC')->first();
        }

        $query = ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', $this->getExchange($symbol))
            ->whereDate('expiry', $expiry);

        if (in_array($symbol, ['NIFTY', 'BANKNIFTY', 'SENSEX'])) {
            $query->where('name', $symbol);
        } else {
            $query->where(fn($q) => $q
                ->where('name', $symbol)
                ->orWhere('trading_symbol', 'LIKE', $symbol . '%'));
        }

        return $query->first();
    }

    private function prewarmInstrumentCacheForExpiry(string $baseSymbol, string $expiry): void
    {
        $instruments = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', $this->getExchange($baseSymbol))
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)->get();

        foreach ($instruments as $inst) {
            $this->instrumentCache["{$baseSymbol}_{$inst->strike}_{$inst->instrument_type}_{$expiry}"] = $inst;
        }

        $this->info("      Cached {$instruments->count()} option instruments for {$baseSymbol} [{$expiry}]");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // UTILITY — identical to LiveOptionOhlcCollector
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * ATM±5 = 11 strikes — same as original LiveOptionOhlcCollector.
     * Change here if you want a different range for next series.
     */
    private function buildStrikeList(float $atm, float $interval): array
    {
        return [
            $atm - ($interval * 5),  // ATM-5
            $atm - ($interval * 4),  // ATM-4
            $atm - ($interval * 3),  // ATM-3
            $atm - ($interval * 2),  // ATM-2
            $atm - $interval,         // ATM-1
            $atm,                     // ATM
            $atm + $interval,         // ATM+1
            $atm + ($interval * 2),  // ATM+2
            $atm + ($interval * 3),  // ATM+3
            $atm + ($interval * 4),  // ATM+4
            $atm + ($interval * 5),  // ATM+5
        ];
    }

    private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    {
        $marketStart = $date->copy()->setTimeFromTimeString(self::MARKET_START);
        $marketEnd   = $date->copy()->setTimeFromTimeString(self::MARKET_END);

        if ($now->lt($marketStart)) return $marketStart;

        $flooredMin = (int)(floor((int)$now->format('i') / 15) * 15);
        $slot = $date->copy()->setTime((int)$now->format('H'), $flooredMin, 0)->subMinutes(15);

        return match (true) {
            $slot->lt($marketStart) => $marketStart->copy(),
            $slot->gt($marketEnd)   => $marketEnd->copy(),
            default                 => $slot,
        };
    }

    private function generateTradingIntervals(Carbon $date): array
    {
        $intervals = [];
        $cur = $date->copy()->setTime(9, 15);
        $end = $date->copy()->setTime(15, 15);
        while ($cur->lte($end)) { $intervals[] = $cur->copy(); $cur->addMinutes(15); }
        return $intervals;
    }

    private function indexCandlesByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $c) $map[Carbon::parse($c->date)->format('H:i')] = $c;
        return $map;
    }

    private function getStrikePosition(float $strike, float $atm, float $interval): string
    {
        $diff = (int) round(($strike - $atm) / $interval);
        if ($diff === 0)  return 'ATM';
        if ($diff === 1)  return 'ATM+1';
        if ($diff === -1) return 'ATM-1';
        // ATM±2 through ATM±5 — store as label if your ENUM supports it,
        // otherwise they fall through to 'N/A' — update ENUM as needed
        return ($diff > 0 ? "ATM+{$diff}" : "ATM{$diff}");
    }
}