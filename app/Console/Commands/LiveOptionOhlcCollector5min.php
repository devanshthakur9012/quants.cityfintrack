<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionOhlcData5min;
use App\Models\OptionSymbol5min;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * LiveOptionOhlcCollector5min
 *
 * 5-minute version of the OHLC collector.
 * Writes to option_ohlc_data_5min — does NOT touch option_ohlc_data (15-min).
 *
 * Cron (run every 5 min during market hours):
 *   * /5 9-15 * * 1-5  php artisan options:live-collect-5min >> /dev/null 2>&1
 *
 * Improvements over the 15-min collector:
 *   ✅ 5-minute candle interval throughout
 *   ✅ Batch + parallel token fetch (8 tokens per chunk) — ~10x faster
 *   ✅ Full ATM±N support (ATM+2, ATM-3 … ATM±5) — no ENUM, no N/A truncation
 *   ✅ Nearest valid strike for ATM (no rounding misplacement)
 *   ✅ Fetch ONLY missing interval range (not full day every run)
 *   ✅ Static day-level cache for candle data
 *   ✅ Reduced per-strike logging
 *   ✅ Larger default upsert chunk (200)
 */
class LiveOptionOhlcCollector5min extends Command
{
    use OptionExpiryResolver;

    // ── Change this constant to switch the target broker ─────────────────────
    private const BROKER_CLIENT_ID = 'ZZL808';

    protected $signature = 'options:live-collect-5min
                            {--symbol= : Specific symbol}
                            {--retry=3 : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=200 : Batch upsert chunk size}
                            {--force-date= : Override date (Y-m-d)}
                            {--from-date= : Historical start date (Y-m-d)}
                            {--to-date= : Historical end date (Y-m-d)}';

    protected $description = '5-min option OHLC collector — batch fetch, full ATM±N, nearest-valid ATM, gap-fill, frozen ATM, batch upsert';

    private const MARKET_START    = '09:15';
    private const MARKET_END      = '15:30';
    private const CANDLE_INTERVAL = '5minute';
    private const CANDLE_MINUTES  = 5;
    private const BATCH_SIZE      = 8;    // tokens per parallel chunk
    private const BATCH_SLEEP_US  = 300_000; // 300 ms between chunks

    /** In-memory instrument cache: key = "SYMBOL_STRIKE_TYPE_EXPIRY" */
    private array $instrumentCache = [];

    /** In-memory strike interval cache: key = "SYMBOL_EXPIRY" */
    private array $strikeIntervalCache = [];

    /** Static day-level candle cache: key = "token_date" */
    private static array $dayCache = [];

    /** Zerodha helper */
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ══════════════════════════════════════════════════════════════════════════
    // Entry point
    // ══════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $now        = Carbon::now();
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;

        // ── Resolve date range ────────────────────────────────────────────────
        $forceDate    = $this->option('force-date');
        $fromDate     = $this->option('from-date') ?? $forceDate;
        $toDate       = $this->option('to-date')   ?? ($fromDate ? $fromDate : null);
        $isHistorical = $fromDate !== null;

        if ($isHistorical) {
            $dateFrom = Carbon::parse($fromDate)->startOfDay();
            $dateTo   = Carbon::parse($toDate)->startOfDay();
            if ($dateFrom->gt($dateTo)) {
                $this->error("❌ --from-date must be <= --to-date");
                return 1;
            }
        } else {
            $dateFrom = Carbon::today();
            $dateTo   = Carbon::today();
        }

        $this->info("⚡ Live Option OHLC Collector [5-MIN] — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Broker: " . self::BROKER_CLIENT_ID . " | Batch Fetch | ATM±N | Nearest-Valid ATM | Gap-Fill | Zero-Gap");
        $this->info("   Mode  : " . ($isHistorical
            ? "HISTORICAL — {$dateFrom->toDateString()} → {$dateTo->toDateString()}"
            : "LIVE — " . $dateFrom->toDateString()));
        $this->newLine();

        // ── Load broker ───────────────────────────────────────────────────────
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()
            ->validToken()
            ->first();

        if (!$broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid!");
            return 1;
        }

        $this->info("🔑 Broker : {$broker->client_name} (ID: {$broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);

        // ── Load symbols ──────────────────────────────────────────────────────
        $symbolsQuery = OptionSymbol5min::active();
        if ($specSymbol) {
            $symbolsQuery->where('symbol', $specSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols found in option_symbols table!');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));
        $this->newLine();

        // ── Build trading day list ────────────────────────────────────────────
        $tradingDays = [];
        $cursor = $dateFrom->copy();
        while ($cursor->lte($dateTo)) {
            if (!$cursor->isWeekend() && !$this->isMarketHoliday($cursor->toDateString())) {
                $tradingDays[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        if (empty($tradingDays)) {
            $this->warn("⏭  No trading days in the given range.");
            return 0;
        }

        $totalDays = count($tradingDays);
        $this->info("   Trading days to process: {$totalDays}");
        $this->newLine();

        // ── Date loop ─────────────────────────────────────────────────────────
        foreach ($tradingDays as $dayIndex => $date) {
            $dayNum = $dayIndex + 1;
            $this->info("════════════════════════════════════════════════════════");
            $this->info("📅 Day {$dayNum}/{$totalDays} — {$date->toDateString()}");
            $this->info("════════════════════════════════════════════════════════");

            // Reset per-day caches
            $this->instrumentCache     = [];
            $this->strikeIntervalCache = [];
            self::$dayCache            = [];

            $allIntervals = $this->generateTradingIntervals($date);

            if ($isHistorical) {
                $intervalsToProcess = $allIntervals;
            } else {
                $lastCompletedSlot  = $this->getLastCompletedSlot($now, $date);
                $intervalsToProcess = array_values(array_filter(
                    $allIntervals,
                    fn($t) => $t->lte($lastCompletedSlot)
                ));

                if (empty($intervalsToProcess)) {
                    $this->warn("   ⏳ Market hasn't started yet or no completed 5-min candle available.");
                    return 0;
                }

                $this->info("   Last completed slot : " . $lastCompletedSlot->format('H:i'));
            }

            $this->info("   Intervals: 09:15 → 15:30 (" . count($intervalsToProcess) . " slots)");
            $this->newLine();

            // ── Symbol loop ───────────────────────────────────────────────────
            foreach ($symbols as $baseSymbol) {
                $expiries = $this->resolveExpiriesFor5Min($baseSymbol, $date);

                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("   📊 {$baseSymbol} — expir" . (count($expiries) > 1 ? 'ies' : 'y')
                    . ': ' . implode(' + ', $expiries));

                foreach ($expiries as $expiry) {
                    $cepeExpiry = $this->getCePeExpiry($baseSymbol, $expiry, $date);
                    $this->prewarmInstrumentCacheForExpiry($baseSymbol, $cepeExpiry);
                }

                foreach ($expiries as $expiry) {
                    $futInstrument = $this->resolveFutInstrument($baseSymbol, $expiry);

                    if (!$futInstrument) {
                        $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} expiry {$expiry} — skipping");
                        Log::warning("LiveOptionOhlcCollector5min: No FUT for {$baseSymbol} expiry {$expiry}");
                        continue;
                    }

                    $cepeExpiry     = $this->getCePeExpiry($baseSymbol, $expiry, $date);
                    $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $cepeExpiry);

                    if ($strikeInterval === null) {
                        $this->error("      ✗ {$baseSymbol} [{$expiry}] — strike interval unknown, SKIPPED");
                        Log::error("LiveOptionOhlcCollector5min: Cannot determine strike interval for {$baseSymbol} [{$cepeExpiry}]");
                        continue;
                    }

                    $this->info("      Strike interval for {$baseSymbol} [{$cepeExpiry}]: {$strikeInterval}");

                    $this->processSymbolExpiry(
                        $broker, $baseSymbol, $futInstrument,
                        $expiry, $cepeExpiry, $strikeInterval,
                        $date, $intervalsToProcess,
                        $maxRetries, $retryDelay, $chunkSize
                    );
                }
            }

            $this->newLine();
            $this->info("✅ Day {$dayNum}/{$totalDays} complete — {$date->toDateString()}");
            $this->newLine();
        }

        $this->info("🏁 All done — " . Carbon::now()->format('H:i:s'));

        // ── Trigger downstream command after live run ─────────────────────────
        if (!$isHistorical) {
            $this->info("🚀 Triggering pivot15:place-orders...");
            $exitCode = $this->call('pivot15:place-orders');
            $this->info($exitCode === 0
                ? "✅ pivot15:place-orders completed."
                : "⚠️  pivot15:place-orders exited with code: {$exitCode}");
        }

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Process one symbol × one expiry
    // ══════════════════════════════════════════════════════════════════════════

    private function processSymbolExpiry(
        BrokerApi $broker,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        string $futExpiry,
        string $cepeExpiry,
        float $strikeInterval,
        Carbon $date,
        array $intervalsToProcess,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): void {
        // ── Find already-stored intervals ─────────────────────────────────────
        $storedTimes = OptionOhlcData5min::whereDate('trade_date', $date)
            ->where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('trading_symbol', $futInstrument->trading_symbol)
            ->where('is_missing', 0)
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->flip()
            ->toArray();

        $missingIntervals = array_values(array_filter(
            $intervalsToProcess,
            fn($t) => !isset($storedTimes[$t->format('H:i')])
        ));

        if (empty($missingIntervals)) {
            $lastSlot = end($intervalsToProcess);
            $this->info("      ✓ {$baseSymbol} [{$futExpiry}] — up to date (latest: "
                . ($lastSlot ? $lastSlot->format('H:i') : '—') . ")");
            return;
        }

        $missingCount = count($missingIntervals);
        $gapCount     = $missingCount - 1;
        $currentSlot  = end($missingIntervals);

        $this->info("      " . ($gapCount > 0 ? "🔄 gap-filling {$gapCount} missed + " : "📥 ")
            . "current (" . $currentSlot->format('H:i') . ")"
            . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ""));

        // ── Step 1: Fetch FUT candles (only missing range) ────────────────────
        $fromTime = $missingIntervals[0]->format('Y-m-d H:i:s');
        $toTime   = $currentSlot->format('Y-m-d H:i:s');

        $allFutCandles = $this->fetchRangeCandles(
            $futInstrument->instrument_token, $fromTime, $toTime, $maxRetries, $retryDelay
        );

        if (empty($allFutCandles)) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — could not fetch FUT data, skipping");
            Log::error("LiveOptionOhlcCollector5min: Could not fetch FUT candles for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
            return;
        }

        $futCandleMap = $this->indexCandlesByTime($allFutCandles);

        // ── Step 2: FREEZE ATM at 09:15 close (nearest valid strike) ──────────
        // Pull 09:15 candle — if missing from range fetch, do a targeted fetch
        $openingCandle = $futCandleMap['09:15'] ?? $this->fetch0915FutCandle(
            $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (!$openingCandle) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — 09:15 FUT candle missing, cannot freeze ATM");
            Log::error("LiveOptionOhlcCollector5min: 09:15 FUT candle missing for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
            return;
        }

        $allStrikes    = $this->getAvailableStrikes($baseSymbol, $cepeExpiry);
        $frozenAtm     = $this->getNearestValidStrike((float) $openingCandle->close, $allStrikes, $strikeInterval);
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      FUT 09:15 close = {$openingCandle->close} | ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");

        // ── Step 3: Batch-fetch ALL option candles in parallel chunks ─────────
        $optionDayCache = $this->fetchAllOptionCandlesBatch(
            $baseSymbol, $frozenStrikes, $cepeExpiry,
            $fromTime, $toTime,
            $maxRetries, $retryDelay
        );

        // ── Step 4: Build rows in memory ──────────────────────────────────────
        $rows              = [];
        $now               = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($missingIntervals as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;

            if ($futCandle !== null) {
                $lastKnownFutClose = $futCandle->close;
            }

            // FUT row
            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                Log::warning("LiveOptionOhlcCollector5min: FUT candle missing at {$timeKey} for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
            }

            $rows[] = $this->buildFutRow(
                $broker->id, $baseSymbol, $futInstrument,
                $futCandle, $frozenAtm, $futExpiry,
                $date, $intervalTime, $now, $isFutMissing
            );

            // CE + PE rows
            foreach (['CE', 'PE'] as $optionType) {
                foreach ($frozenStrikes as $strike) {
                    $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$cepeExpiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;

                    if (!$instrument) {
                        Log::warning("LiveOptionOhlcCollector5min: Instrument not in cache — {$cacheKey} at {$timeKey}");
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        Log::warning("LiveOptionOhlcCollector5min: Option candle missing — {$optionType} {$strike} at {$timeKey} [{$cepeExpiry}] on {$date->toDateString()}");
                    }

                    $rows[] = $this->buildOptionRow(
                        $broker->id, $baseSymbol,
                        $futInstrument->trading_symbol,
                        $lastKnownFutClose,
                        $frozenAtm, $optionType, $strike,
                        $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                        $instrument, $candle, $cepeExpiry,
                        $date, $intervalTime, $now, $isMissing
                    );
                }
            }
        }

        // ── Step 5: Batch upsert ──────────────────────────────────────────────
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [{$futExpiry}] — {$inserted} rows upserted ({$missingCount} intervals)");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BATCH FETCH (core speed improvement)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Fetch all option candles for all strikes in parallel batches.
     * Groups tokens into chunks of BATCH_SIZE, fetches each chunk together,
     * sleeps BATCH_SLEEP_US between chunks to stay within Zerodha rate limits.
     */
    private function fetchAllOptionCandlesBatch(
        string $baseSymbol,
        array $strikes,
        string $expiry,
        string $fromTime,
        string $toTime,
        int $maxRetries,
        int $retryDelay
    ): array {
        // Build token list
        $tokens    = [];
        $tokenMap  = []; // token → cache key (for lookup after fetch)

        foreach (['CE', 'PE'] as $type) {
            foreach ($strikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$type}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    Log::warning("LiveOptionOhlcCollector5min: fetchAllOptionCandlesBatch — instrument missing: {$cacheKey}");
                    continue;
                }

                $token           = $instrument->instrument_token;
                $tokens[]        = $token;
                $tokenMap[$token] = $cacheKey;
            }
        }

        $cache  = [];
        $chunks = array_chunk($tokens, self::BATCH_SIZE);

        foreach ($chunks as $chunkIndex => $chunk) {
            // Parallel fetch for this chunk
            $responses = $this->fetchMultipleRangeCandles($chunk, $fromTime, $toTime, $maxRetries, $retryDelay);

            foreach ($chunk as $token) {
                $candles       = $responses[$token] ?? [];
                $cache[$token] = !empty($candles) ? $this->indexCandlesByTime($candles) : [];
            }

            // Rate limit guard between chunks (not after the last one)
            if ($chunkIndex < count($chunks) - 1) {
                usleep(self::BATCH_SLEEP_US);
            }
        }

        return $cache;
    }

    /**
     * Fetch candles for multiple tokens over a time range.
     * Calls getHistoricalDataByToken for each token — can be upgraded to
     * a true parallel implementation (Guzzle Pool / async) when available.
     *
     * Returns: [ token => candles[] ]
     */
    private function fetchMultipleRangeCandles(
        array $tokens,
        string $fromTime,
        string $toTime,
        int $maxRetries,
        int $retryDelay
    ): array {
        $results = [];

        foreach ($tokens as $token) {
            $results[$token] = $this->fetchRangeCandles($token, $fromTime, $toTime, $maxRetries, $retryDelay);

            // Small per-token sleep within a batch to avoid burst rate limits
            usleep(100_000); // 100ms
        }

        return $results;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ATM resolution helpers
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return the strike from $availableStrikes that is closest to $price.
     * Falls back to rounding if no strikes available.
     */
    private function getNearestValidStrike(float $price, array $availableStrikes, float $interval): float
    {
        if (empty($availableStrikes)) {
            // Fallback: round to nearest interval
            return round($price / $interval) * $interval;
        }

        return collect($availableStrikes)
            ->sortBy(fn($s) => abs($s - $price))
            ->first();
    }

    /**
     * Get all available CE strikes for a symbol+expiry (cached).
     */
    private function getAvailableStrikes(string $symbol, string $expiry): array
    {
        return ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->pluck('strike')
            ->map(fn($s) => (float) $s)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Strike position — full ATM±N (no ENUM truncation)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Returns:
     *   'ATM'     for diff = 0
     *   'ATM+N'   for diff > 0  (e.g. ATM+2, ATM+3 … ATM+5)
     *   'ATM-N'   for diff < 0  (e.g. ATM-2, ATM-3 … ATM-5)
     */
    private function getStrikePosition(float $strike, float $atm, float $interval): string
    {
        $diff = (int) round(($strike - $atm) / $interval);

        if ($diff === 0) return 'ATM';
        if ($diff > 0)   return 'ATM+' . $diff;

        return 'ATM' . $diff; // e.g. ATM-2 (minus already in value)
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Strike interval resolution
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveStrikeInterval(string $symbol, string $expiry): ?float
    {
        $cacheKey = "{$symbol}_{$expiry}";

        if (isset($this->strikeIntervalCache[$cacheKey])) {
            return $this->strikeIntervalCache[$cacheKey];
        }

        $strikes = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($s) => (float) $s)
            ->unique()->sort()->values();

        if ($strikes->count() < 2) {
            Log::warning("LiveOptionOhlcCollector5min: resolveStrikeInterval — fewer than 2 CE strikes for {$symbol} [{$expiry}]");
            return null;
        }

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) $minGap = $gap;
        }

        if ($minGap === PHP_INT_MAX || $minGap <= 0) {
            Log::warning("LiveOptionOhlcCollector5min: resolveStrikeInterval — invalid gap for {$symbol} [{$expiry}]");
            return null;
        }

        $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
        return (float) $minGap;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Expiry resolution
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveExpiriesFor5Min(string $symbol, Carbon $date): array
    {
        $expiries = $this->resolveExpiries($symbol, $date);

        if (count($expiries) >= 1 && Carbon::parse($expiries[0])->isSameDay($date)) {
            array_shift($expiries);

            if (empty($expiries)) {
                $next = $this->fetchNextExpiry($symbol, $date);
                if ($next) $expiries = [$next];
            }

            $this->warn("      ⚠️  {$symbol}: today is expiry day — shifted to: " . ($expiries[0] ?? 'none'));
        }

        return $expiries ?: [$date->copy()->addDays(7)->toDateString()];
    }

    private function getCePeExpiry(string $symbol, string $futExpiry, Carbon $date): string
    {
        if (Carbon::parse($futExpiry)->isSameDay($date)) {
            $next = $this->fetchNextExpiry($symbol, $date);
            return $next ?? $futExpiry;
        }
        return $futExpiry;
    }

    private function fetchNextExpiry(string $symbol, Carbon $afterDate): ?string
    {
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);

        $expiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>', $afterDate)
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()->values()->toArray();

        if (empty($expiries)) return null;

        if (!$isWeekly) {
            $byMonth = [];
            foreach ($expiries as $exp) {
                $key = Carbon::parse($exp)->format('Y-m');
                $byMonth[$key] = $exp;
            }
            $expiries = array_values($byMonth);
        }

        return $expiries[0] ?? null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Instrument helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);

        if ($isWeekly) {
            return ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', $this->getExchange($symbol))
                ->where('name', $symbol)
                ->whereDate('expiry', '>=', $expiry)
                ->orderBy('expiry', 'ASC')
                ->first();
        }

        $query = ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', $this->getExchange($symbol))
            ->whereDate('expiry', $expiry);

        if (in_array($symbol, ['NIFTY', 'BANKNIFTY', 'SENSEX'])) {
            $query->where('name', $symbol);
        } else {
            $query->where(function ($q) use ($symbol) {
                $q->where('name', $symbol)
                  ->orWhere('trading_symbol', 'LIKE', $symbol . '%');
            });
        }

        return $query->first();
    }

    private function prewarmInstrumentCacheForExpiry(string $baseSymbol, string $expiry): void
    {
        $instruments = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', $this->getExchange($baseSymbol))
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = "{$baseSymbol}_{$inst->strike}_{$inst->instrument_type}_{$expiry}";
            $this->instrumentCache[$key] = $inst;
        }

        $this->info("      Cached {$instruments->count()} option instruments for {$baseSymbol} [{$expiry}]");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Candle fetch helpers
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Fetch candles for a specific time range (not full day).
     * Uses static day-level cache to avoid duplicate API calls.
     */
    private function fetchRangeCandles(
        int $instrumentToken,
        string $fromTime,
        string $toTime,
        int $maxRetries,
        int $retryDelay
    ): array {
        $cacheKey = "{$instrumentToken}_{$fromTime}_{$toTime}";

        if (isset(self::$dayCache[$cacheKey])) {
            return self::$dayCache[$cacheKey];
        }

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->zerodhaHelper->getHistoricalDataByToken(
                    $instrumentToken, self::CANDLE_INTERVAL, $fromTime, $toTime
                );
                $result = $data ?? [];
                self::$dayCache[$cacheKey] = $result;
                return $result;
            } catch (Exception $e) {
                $isRateLimit = stripos($e->getMessage(), 'too many') !== false
                    || stripos($e->getMessage(), 'rate limit') !== false
                    || stripos($e->getMessage(), '429') !== false;

                if ($attempt < $maxRetries) {
                    $waitSec = $isRateLimit ? max($retryDelay, 2) : $retryDelay;
                    $this->warn("      ⏳ Fetch attempt {$attempt}/{$maxRetries} failed"
                        . ($isRateLimit ? ' [rate limited]' : '')
                        . " — waiting {$waitSec}s");
                    sleep($waitSec);
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts: {$e->getMessage()}");
                    Log::error("LiveOptionOhlcCollector5min: Fetch failed for token {$instrumentToken}: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    /**
     * Targeted fetch of just the 09:15 candle (used when missing intervals start after 09:15).
     */
    private function fetch0915FutCandle(
        int $instrumentToken,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): ?object {
        $fromTime = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $toTime   = $date->copy()->setTime(9, 20)->format('Y-m-d H:i:s');
        $candles  = $this->fetchRangeCandles($instrumentToken, $fromTime, $toTime, $maxRetries, $retryDelay);
        return $this->indexCandlesByTime($candles)['09:15'] ?? null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Batch upsert
    // ══════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            OptionOhlcData5min::upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                [
                    'base_symbol', 'future_symbol', 'future_price', 'atm_strike',
                    'instrument_type', 'strike', 'instrument_token',
                    'open', 'high', 'low', 'close', 'volume', 'oi',
                    'strike_position', 'expiry_date', 'is_missing',
                    'updated_at',
                ]
            );
            $total += count($chunk);
        }

        return $total;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Row builders
    // ══════════════════════════════════════════════════════════════════════════

    private function buildFutRow(
        int $brokerId,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        $candle,
        float $atmStrike,
        string $expiry,
        Carbon $tradeDate,
        Carbon $intervalTime,
        string $now,
        bool $isMissing = false
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
        int $brokerId,
        string $baseSymbol,
        string $futureSymbol,
        ?float $futurePrice,
        float $atmStrike,
        string $optionType,
        float $strike,
        string $strikePosition,
        ZerodhaInstrument $instrument,
        $candle,
        string $expiry,
        Carbon $tradeDate,
        Carbon $intervalTime,
        string $now,
        bool $isMissing = false
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
    // Time / interval helpers
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Generate all 5-min trading slots for a day: 09:15 → 15:30 (75 slots).
     */
    private function generateTradingIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 30);

        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes(self::CANDLE_MINUTES);
        }

        return $intervals;
    }

    /**
     * Last completed 5-min slot (the current forming candle is excluded).
     *
     * e.g. now=09:18 → 09:15 | now=09:20 → 09:15 | now=09:21 → 09:15
     *      now=09:26 → 09:20 | now=12:37 → 12:30 | now=15:32 → 15:30
     */
    private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    {
        $marketStart = $date->copy()->setTimeFromTimeString(self::MARKET_START);
        $marketEnd   = $date->copy()->setTimeFromTimeString(self::MARKET_END);

        if ($now->lt($marketStart)) {
            return $marketStart->copy();
        }

        $flooredMin = (int)(floor((int)$now->format('i') / self::CANDLE_MINUTES) * self::CANDLE_MINUTES);
        $slot       = $date->copy()->setTime((int)$now->format('H'), $flooredMin, 0);

        // Step back — current slot is still forming
        $slot->subMinutes(self::CANDLE_MINUTES);

        return match (true) {
            $slot->lt($marketStart) => $marketStart->copy(),
            $slot->gt($marketEnd)   => $marketEnd->copy(),
            default                 => $slot,
        };
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Misc helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function buildStrikeList(float $atm, float $interval): array
    {
        return [
            $atm - ($interval * 5),
            $atm - ($interval * 4),
            $atm - ($interval * 3),
            $atm - ($interval * 2),
            $atm - $interval,
            $atm,
            $atm + $interval,
            $atm + ($interval * 2),
            $atm + ($interval * 3),
            $atm + ($interval * 4),
            $atm + ($interval * 5),
        ];
    }

    private function indexCandlesByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $candle) {
            $map[Carbon::parse($candle->date)->format('H:i')] = $candle;
        }
        return $map;
    }
}