<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\ThirtyMinOhlcData;
use App\Models\ThirtyMinOhlcSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Live30MinOhlcCollector
 *
 * Designed to run every hour via cron (scheduled in Kernel.php):
 *   16 9-16 * * 1-5  php artisan options:live-collect-30min
 *
 * NOTE: Cron must be scheduled between 9:30 and 16:20 to capture the
 *       15:15 candle (which completes at 16:15).
 *       ->cron('16 * * * *')->between('9:30', '16:20')
 *
 * Behaviour on each run:
 *   1. Gap-fill : find ALL missing 1hr intervals from 09:15 today up to
 *      the last completed slot, fetch & store them.
 *   2. Current  : fetch and store the candle for the CURRENT completed slot.
 *
 * Data flow:
 *   - Fetches 15-min candles from Zerodha API
 *   - Aggregates four 15-min candles into 1hr bars in memory
 *   - Upserts into 30min_ohlc_data table
 *
 * Strike Interval:
 *   - Dynamically derived from ZerodhaInstrument table (min gap between
 *     consecutive strikes for that symbol+expiry).
 *   - If strike interval cannot be determined, the symbol+expiry is SKIPPED
 *     and logged — no fallback, no guessing.
 *
 * Broker  : hard-coded via BROKER_CLIENT_ID constant (default: OQJ978)
 * Symbols : loaded from 30min_ohlc_symbols table
 *
 * Expiry handling:
 *   - NIFTY (weekly)     : rollover window = 1 trading day before expiry
 *   - Others (monthly)   : rollover window = 5 trading days before expiry
 *   - On expiry day      : automatically shifts CE/PE lookup to next expiry
 */
class Live30MinOhlcCollector extends Command
{
    use OptionExpiryResolver;

    // ── Change this constant to switch the target broker ─────────────────────
    private const BROKER_CLIENT_ID = 'OQJ978';

    private const MARKET_START = '09:15';
    private const MARKET_END   = '15:15';

    // ─────────────────────────────────────────────────────────────────────────

    protected $signature = 'options:live-collect-30min
                            {--symbol=      : Specific symbol (e.g., NIFTY)}
                            {--retry=3      : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50     : Batch upsert chunk size}
                            {--force-date=  : Override date (Y-m-d), for testing}';

    protected $description = 'Live 1hr option OHLC collector — dynamic strike interval, smart expiry, gap-fill, frozen ATM, batch upsert';

    // ── In-memory caches ──────────────────────────────────────────────────────
    private array $instrumentCache  = [];   // "{SYMBOL}_{STRIKE}_{TYPE}_{EXPIRY}" → ZerodhaInstrument
    private array $strikeIntervalCache = []; // "{SYMBOL}_{EXPIRY}" → float
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Entry point
    // ═════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $today      = $this->option('force-date')
            ? Carbon::parse($this->option('force-date'))
            : Carbon::today();
        $now        = Carbon::now();
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;

        // ── Sanity checks ─────────────────────────────────────────────────────
        if ($today->isWeekend()) {
            $this->warn("⏭  Weekend — nothing to collect.");
            return 0;
        }
        if ($this->isMarketHoliday($today->toDateString())) {
            $this->warn("⏭  Market holiday — nothing to collect.");
            return 0;
        }

        $this->info("⚡ Live 1hr OHLC Collector — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Broker: " . self::BROKER_CLIENT_ID . " | Dynamic Strike | Smart Expiry | Gap-Fill | Frozen ATM");
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

        // ── Load symbols ──────────────────────────────────────────────────────
        $symbolsQuery = ThirtyMinOhlcSymbol::active();
        if ($specSymbol) {
            $symbolsQuery->where('symbol', $specSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols in 30min_ohlc_symbols table!');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));
        $this->newLine();

        // ── Determine which intervals to process ──────────────────────────────
        $allIntervals       = $this->generateIntervals($today);
        $lastCompletedSlot  = $this->getLastCompletedSlot($now, $today);
        $intervalsToProcess = array_values(array_filter(
            $allIntervals,
            fn($t) => $t->lte($lastCompletedSlot)
        ));

        if (empty($intervalsToProcess)) {
            $this->warn("   ⏳ No completed candle available yet.");
            return 0;
        }

        $this->info("   Last completed slot : " . $lastCompletedSlot->format('H:i'));
        $this->info("   Intervals to cover  : 09:15 → " . $lastCompletedSlot->format('H:i')
            . " (" . count($intervalsToProcess) . " slots)");
        $this->newLine();

        // ── Symbol loop ───────────────────────────────────────────────────────
        $totalNewIntervals = 0;

        foreach ($symbols as $baseSymbol) {
            $expiries = $this->resolveExpiriesFor30Min($baseSymbol, $today);

            $this->info("   📊 {$baseSymbol} — expir" . (count($expiries) > 1 ? 'ies' : 'y')
                . ': ' . implode(' + ', $expiries));

            foreach ($expiries as $expiry) {
                $cepeExpiry = $this->getCePeExpiry($baseSymbol, $expiry, $today);
                $this->prewarmInstrumentCache($baseSymbol, $cepeExpiry);
            }

            foreach ($expiries as $expiry) {
                $futInstrument = $this->resolveFutInstrument($baseSymbol, $expiry, $today);

                if (!$futInstrument) {
                    $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} expiry {$expiry} — skipping");
                    Log::warning("Live30MinOhlc: No FUT instrument for {$baseSymbol} expiry {$expiry}");
                    continue;
                }

                $cepeExpiry = $this->getCePeExpiry($baseSymbol, $expiry, $today);

                // ── Resolve strike interval dynamically ───────────────────────
                $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $cepeExpiry);

                if ($strikeInterval === null) {
                    $msg = "Live30MinOhlc: Cannot determine strike interval for {$baseSymbol} expiry {$cepeExpiry} — skipping. "
                         . "Check ZerodhaInstrument table has CE/PE rows for this symbol+expiry.";
                    $this->error("      ✗ {$baseSymbol} [{$expiry}] — strike interval unknown, SKIPPED. (see logs)");
                    Log::error($msg);
                    continue;
                }

                $this->info("      Strike interval for {$baseSymbol} [{$cepeExpiry}]: {$strikeInterval}");

                $totalNewIntervals += $this->processSymbolExpiry(
                    $broker, $baseSymbol, $futInstrument,
                    $expiry, $cepeExpiry, $strikeInterval,
                    $today, $intervalsToProcess,
                    $maxRetries, $retryDelay, $chunkSize
                );
            }
        }

        $this->newLine();
        $this->info("✅ Live 1hr collection complete — " . Carbon::now()->format('H:i:s'));

        if ($totalNewIntervals > 0) {
            \Artisan::call('pivot:place-orders');
            $this->info("🚀 Triggered pivot:place-orders ({$totalNewIntervals} new interval(s) inserted).");
        } else {
            $this->warn("⏭  No new intervals inserted — data already up to date. Order placement skipped.");
        }

        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Dynamic strike interval resolution
    // ═════════════════════════════════════════════════════════════════════════

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
            Log::warning("Live30MinOhlc: resolveStrikeInterval — fewer than 2 CE strikes found "
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
            Log::warning("Live30MinOhlc: resolveStrikeInterval — could not compute valid gap "
                . "for {$symbol} expiry {$expiry}.");
            return null;
        }

        $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
        return (float) $minGap;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Process one symbol × expiry
    // ═════════════════════════════════════════════════════════════════════════

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
    ): int {
        // ── Snapshot: which H:i slots are ALREADY in the DB? ─────────────────
        $storedTimes = ThirtyMinOhlcData::whereDate('trade_date', $date)
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

        $newIntervalCount = count($missingIntervals);

        if ($newIntervalCount === 0) {
            $lastSlot = end($intervalsToProcess);
            $this->info("      ✓ {$baseSymbol} [{$futExpiry}] — up to date (latest: "
                . ($lastSlot ? $lastSlot->format('H:i') : '—') . ") — no new intervals");
            return 0;
        }

        $currentSlot = end($missingIntervals);
        $gapCount    = $newIntervalCount - 1;

        if ($gapCount > 0) {
            $this->info("      🔄 {$baseSymbol} [{$futExpiry}] — gap-filling {$gapCount} missed + current ("
                . $currentSlot->format('H:i') . ")" . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ""));
        } else {
            $this->info("      📥 {$baseSymbol} [{$futExpiry}] — fetching current interval "
                . $currentSlot->format('H:i') . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ""));
        }

        // ── Step 1: Fetch full-day FUT candles (15-min) ───────────────────────
        $allFutCandles15 = $this->fetchDayCandles(
            $broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($allFutCandles15)) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — could not fetch FUT data, skipping");
            Log::error("Live30MinOhlc: Could not fetch FUT candles for {$baseSymbol} expiry {$futExpiry} on {$date->toDateString()}");
            return 0;
        }

        // ── Step 2: Freeze ATM at 09:15 close ────────────────────────────────
        $first15Map = $this->indexCandlesByTime($allFutCandles15);
        if (!isset($first15Map['09:15'])) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — 09:15 FUT candle missing, cannot freeze ATM");
            Log::error("Live30MinOhlc: 09:15 FUT candle missing for {$baseSymbol} expiry {$futExpiry} on {$date->toDateString()}");
            return 0;
        }

        $openPrice09  = $first15Map['09:15']->close;
        $frozenAtm    = round($openPrice09 / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      FUT 09:15 close = {$openPrice09} | ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");
        $this->info("      Strikes: " . implode(', ', $frozenStrikes));

        // Aggregate FUT to 1hr map
        $futCandleMap = $this->aggregate15to30($allFutCandles15);
        $this->info("      FUT: " . count($allFutCandles15) . " × 15-min → " . count($futCandleMap) . " × 1hr");

        // ── Step 3: Fetch all option candles ──────────────────────────────────
        $optionDayCache = $this->fetchAllOptionDayCandles(
            $broker, $baseSymbol, $frozenStrikes, $cepeExpiry, $date, $maxRetries, $retryDelay
        );

        // ── Step 4: Build rows ────────────────────────────────────────────────
        $rows              = [];
        $now               = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($missingIntervals as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;

            if ($futCandle !== null) {
                $lastKnownFutClose = $futCandle->close;
            }

            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                $this->warn("      ⚠️  {$timeKey} — FUT candle missing, storing zeros");
                Log::warning("Live30MinOhlc: FUT candle missing at {$timeKey} for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
            }

            $rows[] = $this->buildFutRow(
                $broker->id, $baseSymbol, $futInstrument,
                $futCandle, $frozenAtm, $futExpiry,
                $date, $intervalTime, $now, $isFutMissing
            );

            foreach (['CE', 'PE'] as $optionType) {
                foreach ($frozenStrikes as $strike) {
                    $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$cepeExpiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;

                    if (!$instrument) {
                        // Instrument missing from cache — skip and log
                        Log::warning("Live30MinOhlc: Instrument not in cache — {$cacheKey} at {$timeKey} on {$date->toDateString()}. "
                            . "Strike may not exist for this expiry.");
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        $this->warn("      ⚠️  {$timeKey} {$optionType} {$strike} — missing, storing zeros");
                        Log::warning("Live30MinOhlc: Option candle missing — {$optionType} {$strike} at {$timeKey} "
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
        $this->info("      ✅ {$baseSymbol} [{$futExpiry}] — {$inserted} rows upserted "
            . "({$newIntervalCount} new interval(s))");

        return $newIntervalCount;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Expiry resolution
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveExpiriesFor30Min(string $symbol, Carbon $date): array
    {
        $expiries = $this->resolveExpiries($symbol, $date);

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
            ->unique()
            ->values()
            ->toArray();

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

    // ═════════════════════════════════════════════════════════════════════════
    // FUT instrument resolution
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveFutInstrument(string $symbol, string $expiry, Carbon $date): ?ZerodhaInstrument
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

    private function getExchange(string $symbol): string
    {
        return in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Instrument cache pre-warm
    // ═════════════════════════════════════════════════════════════════════════

    private function prewarmInstrumentCache(string $baseSymbol, string $expiry): void
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

    // ═════════════════════════════════════════════════════════════════════════
    // Option candle fetch
    // ═════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionDayCandles(
        BrokerApi $broker,
        string $baseSymbol,
        array $strikes,
        string $expiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $cache = [];

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    $this->warn("      ⚠️  Instrument not in cache: {$cacheKey}");
                    Log::warning("Live30MinOhlc: fetchAllOptionDayCandles — instrument missing from cache: {$cacheKey}");
                    continue;
                }

                $token     = $instrument->instrument_token;
                $candles15 = $this->fetchDayCandles($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($candles15)) {
                    $cache[$token] = $this->aggregate15to30($candles15);
                    $this->info("      {$optionType} {$strike}: " . count($candles15)
                        . " × 15-min → " . count($cache[$token]) . " × 1hr");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data — zero-filled");
                    Log::warning("Live30MinOhlc: No 15-min candle data for {$optionType} {$strike} [{$expiry}] on {$date->toDateString()}");
                }
            }
        }

        return $cache;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 15-min → 1hr aggregation (4 candles per bar)
    // ═════════════════════════════════════════════════════════════════════════

    private function aggregate15to30(array $candles15): array
    {
        $byTime = $this->indexCandlesByTime($candles15);
        ksort($byTime);

        $times  = array_keys($byTime);
        $result = [];
        $i      = 0;

        while ($i < count($times)) {
            $t1 = $times[$i];
            $t2 = $times[$i + 1] ?? null;
            $t3 = $times[$i + 2] ?? null;
            $t4 = $times[$i + 3] ?? null;

            $c1 = $byTime[$t1];
            $c2 = $c3 = $c4 = null;

            $expected2 = Carbon::createFromFormat('H:i', $t1)->addMinutes(15)->format('H:i');
            $expected3 = Carbon::createFromFormat('H:i', $t1)->addMinutes(30)->format('H:i');
            $expected4 = Carbon::createFromFormat('H:i', $t1)->addMinutes(45)->format('H:i');

            if ($t2 === $expected2) $c2 = $byTime[$t2];
            if ($t3 === $expected3) $c3 = $byTime[$t3];
            if ($t4 === $expected4) $c4 = $byTime[$t4];

            $last = $c4 ?? $c3 ?? $c2 ?? $c1;

            $bar         = new \stdClass();
            $bar->open   = $c1->open;
            $bar->high   = max($c1->high, $c2?->high ?? 0, $c3?->high ?? 0, $c4?->high ?? 0);
            $bar->low    = min($c1->low,  $c2?->low  ?? PHP_INT_MAX, $c3?->low ?? PHP_INT_MAX, $c4?->low ?? PHP_INT_MAX);
            $bar->close  = $last->close;
            $bar->volume = ($c1->volume ?? 0) + ($c2?->volume ?? 0) + ($c3?->volume ?? 0) + ($c4?->volume ?? 0);
            $bar->oi     = $last->oi ?? 0;
            $bar->date   = $c1->date;

            $result[$t1] = $bar;

            $usedCount = 1 + ($c2 ? 1 : 0) + ($c3 ? 1 : 0) + ($c4 ? 1 : 0);
            $i += $usedCount;
        }

        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Batch upsert
    // ═════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            ThirtyMinOhlcData::upsert(
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

    // ═════════════════════════════════════════════════════════════════════════
    // Row builders
    // ═════════════════════════════════════════════════════════════════════════

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

    // ═════════════════════════════════════════════════════════════════════════
    // Utility helpers
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Generate interval start times: 09:15, 10:15, 11:15 … 15:15
     * (1hr bars, each starting on the :15 mark)
     */
    private function generateIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 15);
        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes(60);
        }
        return $intervals;
    }

    /**
     * Last COMPLETED 1hr slot, clamped to market hours.
     *
     * Bars start at 09:15, 10:15 ... 15:15
     * Bar at T is complete when now >= T + 60 minutes.
     *
     * IMPORTANT: The 15:15 candle completes at 16:15.
     * Cron must be scheduled to run at 16:16 to capture it.
     * Use ->between('9:30', '16:20') in Kernel.php
     */
    private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    {
        $marketStart  = $date->copy()->setTimeFromTimeString(self::MARKET_START);
        $marketEnd    = $date->copy()->setTimeFromTimeString(self::MARKET_END);
        $nowTotalMins = (int)$now->format('H') * 60 + (int)$now->format('i');

        $result = null;

        $t   = (int)$marketStart->format('H') * 60 + (int)$marketStart->format('i');
        $end = (int)$marketEnd->format('H') * 60 + (int)$marketEnd->format('i');

        while ($t <= $end) {
            if ($t + 60 <= $nowTotalMins) {
                $result = $t;
            }
            $t += 60;
        }

        if ($result === null) {
            return $marketStart->copy()->subMinute();
        }

        $result = min($result, $end);

        return $date->copy()->setTime(intdiv($result, 60), $result % 60, 0);
    }

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

    private function fetchDayCandles(
        BrokerApi $broker,
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
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Attempt {$attempt}/{$maxRetries} failed: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts: {$e->getMessage()}");
                    Log::error("Live30MinOhlc: Fetch failed for token {$instrumentToken} after {$maxRetries} attempts: {$e->getMessage()}");
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
        if ($diff === 2)  return 'ATM+2';
        if ($diff === -2) return 'ATM-2';
        return ($diff > 0 ? "ATM+{$diff}" : "ATM{$diff}");
    }

    private function isMarketHoliday(string $date): bool
    {
        return \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}