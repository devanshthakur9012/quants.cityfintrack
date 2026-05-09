<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionOhlcData;
use App\Models\OptionSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * LiveOptionOhlcCollector
 *
 * Designed to run every 15 minutes via cron:
 *   * /15 9-15 * * 1-5  php artisan options:live-collect >> /dev/null 2>&1
 *
 * Behaviour on each run:
 *   1. Gap-fill: find ALL missing intervals from 09:15 today up to the
 *      current candle window, fetch & store them.
 *   2. Current candle: fetch and store the candle for the CURRENT 15-min window.
 *
 * Key features:
 *   ✅ BROKER_CLIENT_ID constant — pin to a specific broker account
 *   ✅ Dynamic strike interval — derived from ZerodhaInstrument table (min gap
 *      between consecutive CE strikes for that symbol+expiry); no hard-coded map
 *   ✅ No SymbolMonitored dependency — uses option_symbols table + ZerodhaInstrument
 *   ✅ Smart dual-expiry — within rollover window of expiry, collects BOTH
 *      current AND next expiry in parallel; after expiry, next expiry only
 *   ✅ Weekly expiry support for NIFTY (NSE) and SENSEX (BSE/BFO)
 *   ✅ FUT instrument resolved directly from ZerodhaInstrument per expiry
 *   ✅ All other guarantees preserved: frozen ATM, batch upsert, zero-gap fill,
 *      carry-forward future_price, full-day fetch
 */
class LiveOptionOhlcCollector extends Command
{
    use OptionExpiryResolver;

    // ── Change this constant to switch the target broker ─────────────────────
    private const BROKER_CLIENT_ID = 'DB0542';

    protected $signature = 'options:live-collect
                            {--symbol= : Specific symbol}
                            {--retry=3 : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50 : Batch upsert chunk size}
                            {--force-date= : Override date (Y-m-d), single day (alias for --from-date=--to-date=)}
                            {--from-date= : Historical start date (Y-m-d) — collects full day for each trading day in range}
                            {--to-date= : Historical end date (Y-m-d), defaults to today if --from-date given}';

    protected $description = 'Live 15-min option OHLC collector — dynamic strike interval, smart dual-expiry (weekly+monthly), gap-fill, frozen ATM, full-day fetch, batch upsert, zero-gap';

    private const MARKET_START = '09:15';
    private const MARKET_END   = '15:15';

    /** In-memory instrument cache: key = "SYMBOL_STRIKE_TYPE_EXPIRY" */
    private array $instrumentCache = [];

    /** In-memory strike interval cache: key = "SYMBOL_EXPIRY" */
    private array $strikeIntervalCache = [];

    /** Zerodha helper (single broker, set in handle()) */
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
        // --force-date      → single day (backward compat)
        // --from-date       → start of range (--to-date defaults to today)
        // neither           → live mode (today, up to last completed slot)
        $forceDate = $this->option('force-date');
        $fromDate  = $this->option('from-date') ?? $forceDate;
        $toDate    = $this->option('to-date')   ?? ($fromDate ? $fromDate : null);

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

        $this->info("⚡ Live Option OHLC Collector — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Broker: " . self::BROKER_CLIENT_ID . " | Dynamic Strike | Smart Dual-Expiry | Frozen ATM | Full-Day Fetch | Batch Upsert | Zero-Gap");
        if ($isHistorical) {
            $this->info("   Mode   : HISTORICAL — {$dateFrom->toDateString()} → {$dateTo->toDateString()}");
        } else {
            $this->info("   Mode   : LIVE — today " . $dateFrom->toDateString());
        }
        $this->info("   Rollover window : " . self::ROLLOVER_TRADING_DAYS . " trading days before expiry");
        $this->newLine();

        // ── Load broker ───────────────────────────────────────────────────────
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()
            ->validToken()
            ->first();

        if (!$broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid!");
            $this->line("   Change BROKER_CLIENT_ID constant in " . class_basename($this) . " to fix.");
            return 1;
        }

        $this->info("🔑 Broker : {$broker->client_name} (ID: {$broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);

        // ── Load symbols from option_symbols table ────────────────────────────
        $symbolsQuery = OptionSymbol::active();
        if ($specSymbol) {
            $symbolsQuery->where('symbol', $specSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols found in option_symbols table!');
            $this->line('   Run: php artisan db:seed --class=OptionSymbolSeeder');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));
        $this->newLine();

        // ── Build list of trading days to process ─────────────────────────────
        $tradingDays = [];
        $cursor = $dateFrom->copy();
        while ($cursor->lte($dateTo)) {
            if (!$cursor->isWeekend() && !$this->isMarketHoliday($cursor->toDateString())) {
                $tradingDays[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        if (empty($tradingDays)) {
            $this->warn("⏭  No trading days in the given range (weekends/holidays only).");
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

            // Reset per-day caches so stale instrument/strike data doesn't bleed across days
            $this->instrumentCache    = [];
            $this->strikeIntervalCache = [];

            // ── Determine intervals for this day ──────────────────────────
            $allIntervals = $this->generateTradingIntervals($date);

            if ($isHistorical) {
                // Historical: always collect full day (09:15 → 15:15)
                $intervalsToProcess = $allIntervals;
            } else {
                // Live: only up to last completed candle
                $lastCompletedSlot  = $this->getLastCompletedSlot($now, $date);
                $intervalsToProcess = array_values(array_filter(
                    $allIntervals,
                    fn($t) => $t->lte($lastCompletedSlot)
                ));

                if (empty($intervalsToProcess)) {
                    $this->warn("   ⏳ Market hasn't started yet or no completed candle available.");
                    return 0;
                }

                $this->info("   Last completed slot : " . $lastCompletedSlot->format('H:i'));
                $this->info("   Intervals to cover  : 09:15 → " . $lastCompletedSlot->format('H:i')
                    . " (" . count($intervalsToProcess) . " slots)");
            }

            $this->info("   Intervals : 09:15 → 15:15 (" . count($intervalsToProcess) . " slots)");
            $this->newLine();

            // ── Symbol loop ───────────────────────────────────────────────
            foreach ($symbols as $baseSymbol) {
                $expiries = $this->resolveExpiriesFor15Min($baseSymbol, $date);

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
                        Log::warning("LiveOptionOhlcCollector: No FUT instrument for {$baseSymbol} expiry {$expiry}");
                        continue;
                    }

                    $cepeExpiry = $this->getCePeExpiry($baseSymbol, $expiry, $date);

                    $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $cepeExpiry);

                    if ($strikeInterval === null) {
                        $this->error("      ✗ {$baseSymbol} [{$expiry}] — strike interval unknown, SKIPPED. (see logs)");
                        Log::error("LiveOptionOhlcCollector: Cannot determine strike interval for {$baseSymbol} "
                            . "expiry {$cepeExpiry} — skipping. Check ZerodhaInstrument table has CE/PE rows for this symbol+expiry.");
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

        // ── Trigger pivot15:place-orders after live data fetch ────────────────
        if (!$isHistorical) {
            $this->info("🚀 Triggering pivot15:place-orders...");
            $exitCode = $this->call('pivot15:place-orders');
            if ($exitCode === 0) {
                $this->info("✅ pivot15:place-orders completed successfully.");
            } else {
                $this->warn("⚠️  pivot15:place-orders exited with code: {$exitCode}");
            }
        }

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Process one symbol × one expiry — gap-fill + current candle
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
        // ── Find already-stored intervals (O(1) lookup) ───────────────────────
        $storedTimes = OptionOhlcData::whereDate('trade_date', $date)
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
        $currentSlot  = end($missingIntervals);
        $gapCount     = $missingCount - 1;

        if ($gapCount > 0) {
            $this->info("      🔄 {$baseSymbol} [{$futExpiry}] — gap-filling {$gapCount} missed + current ("
                . $currentSlot->format('H:i') . ")"
                . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ""));
        } else {
            $this->info("      📥 {$baseSymbol} [{$futExpiry}] — fetching current interval "
                . $currentSlot->format('H:i')
                . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ""));
        }

        // ── Step 1: Fetch full-day FUT candles ────────────────────────────────
        $allFutCandles = $this->fetchDayCandles(
            $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($allFutCandles)) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — could not fetch FUT data, skipping");
            Log::error("LiveOptionOhlcCollector: Could not fetch FUT candles for {$baseSymbol} expiry {$futExpiry} on {$date->toDateString()}");
            return;
        }

        $futCandleMap = $this->indexCandlesByTime($allFutCandles);

        // ── Step 2: FREEZE ATM at 09:15 close ────────────────────────────────
        if (!isset($futCandleMap['09:15'])) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — 09:15 FUT candle missing, cannot freeze ATM");
            Log::error("LiveOptionOhlcCollector: 09:15 FUT candle missing for {$baseSymbol} expiry {$futExpiry} on {$date->toDateString()}");
            return;
        }

        $frozenAtm     = round($futCandleMap['09:15']->close / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      FUT 09:15 close = {$futCandleMap['09:15']->close} | ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");
        $this->info("      Strikes: " . implode(', ', $frozenStrikes));

        // ── Step 3: Fetch full-day option candles ─────────────────────────────
        $optionDayCache = $this->fetchAllOptionDayCandles(
            $baseSymbol, $frozenStrikes, $cepeExpiry, $date, $maxRetries, $retryDelay
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

            // — FUT row —
            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                $this->warn("      ⚠️  {$timeKey} — FUT candle missing, storing zeros (is_missing=1)");
                Log::warning("LiveOptionOhlcCollector: FUT candle missing at {$timeKey} for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
            }
            $rows[] = $this->buildFutRow(
                $broker->id, $baseSymbol, $futInstrument,
                $futCandle, $frozenAtm, $futExpiry,
                $date, $intervalTime, $now, $isFutMissing
            );

            // — CE + PE rows —
            foreach (['CE', 'PE'] as $optionType) {
                foreach ($frozenStrikes as $strike) {
                    $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$cepeExpiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;

                    if (!$instrument) {
                        Log::warning("LiveOptionOhlcCollector: Instrument not in cache — {$cacheKey} at {$timeKey} on {$date->toDateString()}. "
                            . "Strike may not exist for this expiry.");
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        $this->warn("      ⚠️  {$timeKey} {$optionType} {$strike} — missing, storing zeros");
                        Log::warning("LiveOptionOhlcCollector: Option candle missing — {$optionType} {$strike} at {$timeKey} "
                            . "for {$baseSymbol} [{$cepeExpiry}] on {$date->toDateString()}");
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
    // Dynamic strike interval resolution
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Derive the strike interval for a symbol+expiry by finding the minimum
     * gap between consecutive CE strike prices in ZerodhaInstrument.
     *
     * Returns null if it cannot be determined (caller must skip + log).
     */
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
            ->unique()
            ->sort()
            ->values();

        if ($strikes->count() < 2) {
            Log::warning("LiveOptionOhlcCollector: resolveStrikeInterval — fewer than 2 CE strikes found "
                . "for {$symbol} expiry {$expiry}. Cannot compute interval.");
            return null;
        }

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) {
                $minGap = $gap;
            }
        }

        if ($minGap === PHP_INT_MAX || $minGap <= 0) {
            Log::warning("LiveOptionOhlcCollector: resolveStrikeInterval — could not compute valid gap "
                . "for {$symbol} expiry {$expiry}.");
            return null;
        }

        $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
        return (float) $minGap;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Expiry resolution — with weekly support + expiry-day shift
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolve expiries for 15-min collection.
     *
     * Same as resolveExpiriesFor30Min in Live30MinOhlcCollector:
     *   - On expiry day, shift to next expiry (current contract has 0 liquidity)
     *   - Otherwise delegate to trait's resolveExpiries()
     */
    private function resolveExpiriesFor15Min(string $symbol, Carbon $date): array
    {
        $expiries = $this->resolveExpiries($symbol, $date);

        // If the first resolved expiry IS today → shift forward
        if (
            count($expiries) >= 1
            && Carbon::parse($expiries[0])->isSameDay($date)
        ) {
            array_shift($expiries);

            if (empty($expiries)) {
                $next = $this->fetchNextExpiry($symbol, $date);
                if ($next) {
                    $expiries = [$next];
                }
            }

            $this->warn("      ⚠️  {$symbol}: today is expiry day — shifted to: " . ($expiries[0] ?? 'none'));
        }

        return $expiries ?: [$date->copy()->addDays(7)->toDateString()];
    }

    /**
     * Determine the CE/PE expiry to use for a given FUT expiry.
     *
     * For weekly symbols (NIFTY/SENSEX): on expiry day, CE/PE of the expiring
     * weekly contract have near-zero value, so we use the NEXT expiry's CE/PE.
     * For monthly: always same as FUT expiry.
     */
    private function getCePeExpiry(string $symbol, string $futExpiry, Carbon $date): string
    {
        if (Carbon::parse($futExpiry)->isSameDay($date)) {
            $next = $this->fetchNextExpiry($symbol, $date);
            return $next ?? $futExpiry;
        }
        return $futExpiry;
    }

    /**
     * Fetch the next available expiry for a symbol after $afterDate.
     *
     * For weekly symbols: returns the next weekly expiry date.
     * For monthly symbols: returns the next month-end expiry date.
     */
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
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) return null;

        if (!$isWeekly) {
            // For monthly: keep only the last expiry per calendar month
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
    // Option candle batch fetch
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionDayCandles(
        string $baseSymbol,
        array $strikes,
        string $expiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $cache       = [];
        $fetchCount  = 0;

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    $this->warn("      ⚠️  Instrument not found: {$cacheKey}");
                    Log::warning("LiveOptionOhlcCollector: fetchAllOptionDayCandles — instrument missing from cache: {$cacheKey}");
                    continue;
                }

                // Zerodha rate limit: ~3 req/sec for historical API.
                // Sleep 400ms every request to stay safely under the limit.
                if ($fetchCount > 0) {
                    usleep(400_000); // 400ms
                }

                $token   = $instrument->instrument_token;
                $candles = $this->fetchDayCandles($token, $date, $maxRetries, $retryDelay);
                $fetchCount++;

                if (!empty($candles)) {
                    $cache[$token] = $this->indexCandlesByTime($candles);
                    $this->info("      {$optionType} {$strike}: " . count($candles) . " candles");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data — intervals zero-filled");
                    Log::warning("LiveOptionOhlcCollector: No candle data for {$optionType} {$strike} [{$expiry}] on {$date->toDateString()}");
                }
            }
        }

        return $cache;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Batch upsert
    // ══════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            OptionOhlcData::upsert(
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
    // Instrument resolution
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolve the FUT instrument for a symbol + exact expiry date.
     *
     * For MONTHLY symbols: looks up FUT by exact expiry date.
     *
     * For WEEKLY symbols (NIFTY, SENSEX): weekly options have NO corresponding
     * FUT. Falls back to the nearest monthly FUT whose expiry is >= the weekly
     * expiry date. E.g. weekly expiry 2026-03-04 → uses NIFTY26MARFUT (2026-03-27).
     * This is standard practice — near-month FUT price is used as ATM reference.
     */
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

        // Monthly: exact expiry match
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
    // Utility helpers
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

    /**
     * Last completed 15-min slot, clamped to market hours.
     *
     * The current forming candle is NOT included — we step back 15 mins
     * so only fully-closed bars are fetched.
     *
     * e.g. now=09:28 → 09:15 | now=09:30 → 09:15 | now=09:31 → 09:15
     *      now=09:45 → 09:30 | now=12:40 → 12:30 | now=15:30 → 15:15
     */
    private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    {
        $marketStart = $date->copy()->setTimeFromTimeString(self::MARKET_START);
        $marketEnd   = $date->copy()->setTimeFromTimeString(self::MARKET_END);

        if ($now->lt($marketStart)) {
            return $marketStart;
        }

        $flooredMin = (int)(floor((int)$now->format('i') / 15) * 15);
        $slot       = $date->copy()->setTime((int)$now->format('H'), $flooredMin, 0);

        // Step back by 15 min — the current slot is still forming
        $slot->subMinutes(15);

        return match (true) {
            $slot->lt($marketStart) => $marketStart->copy(),
            $slot->gt($marketEnd)   => $marketEnd->copy(),
            default                 => $slot,
        };
    }

    private function generateTradingIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 15);
        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes(15);
        }
        return $intervals;
    }

    private function fetchDayCandles(
        int $instrumentToken,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $fromTime = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $toTime   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->zerodhaHelper->getHistoricalDataByToken(
                    $instrumentToken, '15minute', $fromTime, $toTime
                );
                return $data ?? [];
            } catch (Exception $e) {
                $isRateLimit = stripos($e->getMessage(), 'too many') !== false
                    || stripos($e->getMessage(), 'rate limit') !== false
                    || stripos($e->getMessage(), '429') !== false;

                if ($attempt < $maxRetries) {
                    // Rate limit errors need a longer back-off (2 sec) vs normal retry delay
                    $waitSec = $isRateLimit ? max($retryDelay, 2) : $retryDelay;
                    $this->warn("      ⏳ Fetch attempt {$attempt}/{$maxRetries} failed"
                        . ($isRateLimit ? ' [rate limited]' : '')
                        . ": {$e->getMessage()} — waiting {$waitSec}s");
                    sleep($waitSec);
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts: {$e->getMessage()}");
                    Log::error("LiveOptionOhlcCollector: Fetch failed for token {$instrumentToken} after {$maxRetries} attempts: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    private function indexCandlesByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $candle) {
            $map[Carbon::parse($candle->date)->format('H:i')] = $candle;
        }
        return $map;
    }

    private function getStrikePosition(float $strike, float $atm, float $interval): string
    {
        // Cast to int — round() returns float, so === 0 would fail (0.0 !== 0)
        $diff = (int) round(($strike - $atm) / $interval);
        if ($diff === 0)  return 'ATM';
        if ($diff === 1)  return 'ATM+1';
        if ($diff === -1) return 'ATM-1';
        // Anything beyond ATM±1 stored as N/A to match strike_position ENUM definition
        // (ATM+2 through ATM+5 and ATM-2 through ATM-5 are not in the ENUM)
        return 'N/A';
    }
}