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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Collect30MinOhlcData  (Historical Backfill)
 *
 * Collects 1hr OHLC + OI for FUT, CE and PE options for a date range.
 * Symbols : loaded from 30min_ohlc_symbols table
 * Broker  : hard-coded via BROKER_CLIENT_ID constant (default: OQJ978)
 *
 * ── MATCHES Live30MinOhlcCollector exactly ────────────────────────────────
 *
 * Interval:
 *   - 1hr candles: 09:15, 10:15, 11:15 ... 15:15  (6 bars per day)
 *   - Aggregated from 4 × 15-min API candles per bar
 *   - Bar keyed by start time of the FIRST 15-min candle (09:15 etc.)
 *
 * Strike Interval:
 *   - Dynamically derived from ZerodhaInstrument table (min gap between
 *     consecutive CE strikes for symbol+expiry).
 *   - If strike interval cannot be determined → symbol+expiry is SKIPPED
 *     and logged. No fallback, no guessing.
 *
 * ATM:
 *   - Frozen at 09:15 FUT close for the entire day.
 *   - round(09:15_close / strikeInterval) * strikeInterval
 *
 * Expiry handling:
 *   - NIFTY (weekly)  : rollover window = 1 trading day before expiry
 *   - Others (monthly): rollover window = 5 trading days before expiry
 *   - On expiry day   : CE/PE lookup shifted to next expiry
 */
class Collect30MinOhlcData extends Command
{
    use OptionExpiryResolver;

    // ── Change this to switch the target broker without touching logic ────────
    private const BROKER_CLIENT_ID = 'OQJ978';

    // ─────────────────────────────────────────────────────────────────────────

    protected $signature = 'options:collect-30min-ohlc
                            {--start-date= : Start date (Y-m-d)}
                            {--end-date=   : End date (Y-m-d)}
                            {--date=       : Single date (Y-m-d)}
                            {--symbol=     : Specific symbol (e.g., NIFTY)}
                            {--retry=3     : Number of retries on failure}
                            {--retry-delay=2 : Delay between retries in seconds}
                            {--chunk=50    : Batch insert chunk size}';

    protected $description = 'Backfill 1hr OHLC + OI for options — dynamic strike, smart expiry, frozen ATM, zero-gap';

    // ── In-memory caches ──────────────────────────────────────────────────────
    private array $instrumentCache     = [];   // "{SYMBOL}_{STRIKE}_{TYPE}_{EXPIRY}" → ZerodhaInstrument
    private array $strikeIntervalCache = [];   // "{SYMBOL}_{EXPIRY}" → float
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Entry point
    // ═════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        // ── Date range ────────────────────────────────────────────────────────
        if ($this->option('date')) {
            $startDate = Carbon::parse($this->option('date'));
            $endDate   = $startDate->copy();
        } else {
            $startDate = $this->option('start-date')
                ? Carbon::parse($this->option('start-date'))
                : Carbon::today();
            $endDate = $this->option('end-date')
                ? Carbon::parse($this->option('end-date'))
                : Carbon::today();
        }

        $specificSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $maxRetries     = (int) $this->option('retry');
        $retryDelay     = (int) $this->option('retry-delay');
        $chunkSize      = (int) $this->option('chunk');

        $this->info("🚀 1hr OHLC Backfill Collector — Broker: " . self::BROKER_CLIENT_ID);
        $this->info("   Date range : {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        $this->info("   Candle     : 1hr (4 × 15-min aggregated) | 09:15, 10:15 ... 15:15");
        $this->info("   Strike     : Dynamic (derived from ZerodhaInstrument table)");
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
        if ($specificSymbol) {
            $symbolsQuery->where('symbol', $specificSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols in 30min_ohlc_symbols table!');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));
        $this->newLine();

        $totalProcessed = 0;
        $totalSkipped   = 0;
        $totalFailed    = 0;

        // ── Date loop ─────────────────────────────────────────────────────────
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {

            if ($currentDate->isWeekend()) {
                $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (Weekend)");
                $currentDate->addDay();
                continue;
            }

            if ($this->isMarketHoliday($currentDate->toDateString())) {
                $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (Holiday)");
                $currentDate->addDay();
                continue;
            }

            $this->info("\n📅 {$currentDate->format('Y-m-d')}");

            // Reset per-date strike interval cache so each date gets
            // fresh instrument data (expiry set changes day to day)
            $this->strikeIntervalCache = [];

            try {
                $result = $this->processDate(
                    $broker, $currentDate, $symbols,
                    $maxRetries, $retryDelay, $chunkSize
                );
                $totalProcessed += $result['success'];
                $totalSkipped   += $result['skipped'];
                $totalFailed    += $result['failed'];
            } catch (Exception $e) {
                $this->error("Date error: " . $e->getMessage());
                Log::error("Collect30MinOhlcData: date-level error", [
                    'date'  => $currentDate->format('Y-m-d'),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $currentDate->addDay();
        }

        $this->newLine();
        $this->info("✅ Done — Processed: {$totalProcessed} | Skipped: {$totalSkipped} | Failed: {$totalFailed}");
        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Process one date across all symbols
    // ═════════════════════════════════════════════════════════════════════════

    private function processDate(
        BrokerApi $broker,
        Carbon $date,
        array $symbols,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): array {
        $intervals = $this->generateIntervals($date);
        $this->info("   1hr intervals: " . count($intervals) . " (09:15 → 15:15)");

        $success = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($symbols as $baseSymbol) {
            $expiries = $this->resolveExpiriesFor30Min($baseSymbol, $date);

            $this->info("\n   📊 {$baseSymbol} — expir" . (count($expiries) > 1 ? 'ies' : 'y')
                . ': ' . implode(' + ', $expiries));

            foreach ($expiries as $expiry) {
                $futInstrument = $this->resolveFutInstrument($baseSymbol, $expiry, $date);

                if (!$futInstrument) {
                    $msg = "Collect30MinOhlcData: No FUT instrument for {$baseSymbol} expiry {$expiry} on {$date->toDateString()}";
                    $this->warn("      ⚠️  No FUT instrument — SKIPPED. (see logs)");
                    Log::warning($msg);
                    $skipped += count($intervals);
                    continue;
                }

                $cepeExpiry = $this->getCePeExpiry($baseSymbol, $expiry, $date);

                // ── Resolve strike interval dynamically — no fallback ─────────
                $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $cepeExpiry);

                if ($strikeInterval === null) {
                    $msg = "Collect30MinOhlcData: Cannot determine strike interval for {$baseSymbol} "
                         . "expiry {$cepeExpiry} on {$date->toDateString()} — SKIPPED. "
                         . "Check ZerodhaInstrument has CE rows for this symbol+expiry.";
                    $this->error("      ✗ {$baseSymbol} [{$expiry}] — strike interval unknown, SKIPPED. (see logs)");
                    Log::error($msg);
                    $skipped += count($intervals);
                    continue;
                }

                $this->info("      Strike interval for {$baseSymbol} [{$cepeExpiry}]: {$strikeInterval}");

                $this->prewarmInstrumentCache($baseSymbol, $cepeExpiry);

                $result = $this->processSymbolExpiry(
                    $broker, $baseSymbol, $futInstrument,
                    $expiry, $cepeExpiry, $strikeInterval,
                    $date, $intervals,
                    $maxRetries, $retryDelay, $chunkSize
                );

                $success += $result['success'];
                $skipped += $result['skipped'];
                $failed  += $result['failed'];
            }
        }

        return ['success' => $success, 'skipped' => $skipped, 'failed' => $failed];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Process one symbol × expiry on one date
    // ═════════════════════════════════════════════════════════════════════════

    private function processSymbolExpiry(
        BrokerApi $broker,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        string $futExpiry,
        string $cepeExpiry,
        float $strikeInterval,
        Carbon $date,
        array $intervals,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): array {
        $this->info("      FUT  : {$futInstrument->trading_symbol} (token: {$futInstrument->instrument_token})");
        $this->info("      CE/PE expiry: {$cepeExpiry}" . ($cepeExpiry !== $futExpiry ? ' ← shifted (expiry day)' : ''));

        // ── Step 1: Fetch full-day FUT candles (15-min) ───────────────────────
        $allFutCandles15 = $this->fetchDayCandles(
            $broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($allFutCandles15)) {
            $msg = "Collect30MinOhlcData: Could not fetch FUT candles for {$baseSymbol} "
                 . "expiry {$futExpiry} on {$date->toDateString()}";
            $this->error("      ✗ Could not fetch FUT data — skipping");
            Log::error($msg);
            return ['success' => 0, 'skipped' => 0, 'failed' => count($intervals)];
        }

        // ── Step 2: Freeze ATM at 09:15 close ────────────────────────────────
        $first15Map = $this->indexCandlesByTime($allFutCandles15);

        if (!isset($first15Map['09:15'])) {
            $msg = "Collect30MinOhlcData: 09:15 FUT candle missing for {$baseSymbol} "
                 . "expiry {$futExpiry} on {$date->toDateString()} — cannot freeze ATM";
            $this->error("      ✗ 09:15 FUT candle missing — cannot freeze ATM, skipping");
            Log::error($msg);
            return ['success' => 0, 'skipped' => 0, 'failed' => count($intervals)];
        }

        $openPrice09   = $first15Map['09:15']->close;
        $frozenAtm     = round($openPrice09 / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      FUT 09:15 close = {$openPrice09} | ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");
        $this->info("      Strikes: " . implode(', ', $frozenStrikes));

        // Aggregate FUT 15-min → 1hr
        $futCandleMap = $this->aggregate15to1hr($allFutCandles15);
        $this->info("      FUT: " . count($allFutCandles15) . " × 15-min → " . count($futCandleMap) . " × 1hr candles");

        // ── Step 3: Fetch all option day candles ──────────────────────────────
        $optionDayCache = $this->fetchAllOptionDayCandles(
            $broker, $baseSymbol, $frozenStrikes, $cepeExpiry, $date, $maxRetries, $retryDelay
        );

        // ── Step 4: Build rows ────────────────────────────────────────────────
        $rows              = [];
        $now               = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($intervals as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;

            if ($futCandle !== null) {
                $lastKnownFutClose = $futCandle->close;
            }

            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                $this->warn("      ⚠️  {$timeKey} — FUT candle missing, storing zeros");
                Log::warning("Collect30MinOhlcData: FUT candle missing at {$timeKey} for "
                    . "{$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
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
                        Log::warning("Collect30MinOhlcData: Instrument missing from cache — "
                            . "{$cacheKey} at {$timeKey} on {$date->toDateString()}. "
                            . "Strike may not exist for this expiry.");
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        $this->warn("      ⚠️  {$timeKey} {$optionType} {$strike} — missing, storing zeros");
                        Log::warning("Collect30MinOhlcData: Option candle missing — "
                            . "{$optionType} {$strike} at {$timeKey} "
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
        $this->info("      ✅ {$baseSymbol} [{$futExpiry}] — {$inserted} rows upserted");

        return ['success' => $inserted, 'skipped' => 0, 'failed' => 0];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Dynamic strike interval resolution
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Derive the strike interval by finding the minimum gap between
     * consecutive CE strike prices in ZerodhaInstrument for symbol+expiry.
     *
     * Returns null → caller SKIPS + logs. No fallback. No guessing.
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
            Log::warning("Collect30MinOhlcData: resolveStrikeInterval — fewer than 2 CE strikes "
                . "found for {$symbol} expiry {$expiry}. Cannot compute interval.");
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
            Log::warning("Collect30MinOhlcData: resolveStrikeInterval — could not compute valid "
                . "gap for {$symbol} expiry {$expiry}.");
            return null;
        }

        $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
        return (float) $minGap;
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
                    Log::warning("Collect30MinOhlcData: fetchAllOptionDayCandles — "
                        . "instrument missing: {$cacheKey} on {$date->toDateString()}");
                    continue;
                }

                $token     = $instrument->instrument_token;
                $candles15 = $this->fetchDayCandles($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($candles15)) {
                    $cache[$token] = $this->aggregate15to1hr($candles15);
                    $this->info("      {$optionType} {$strike}: " . count($candles15)
                        . " × 15-min → " . count($cache[$token]) . " × 1hr");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data — zero-filled");
                    Log::warning("Collect30MinOhlcData: No 15-min data for "
                        . "{$optionType} {$strike} [{$expiry}] on {$date->toDateString()}");
                }
            }
        }

        return $cache;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 15-min → 1hr aggregation  (4 candles per bar — matches Live collector)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Aggregate 15-minute candles into 1hr candles.
     *
     * Bar labels (start time of the FIRST 15-min candle):
     *   09:15 + 09:30 + 09:45 + 10:00 → keyed "09:15"
     *   10:15 + 10:30 + 10:45 + 11:00 → keyed "10:15"
     *   ...
     *   15:15 (single or partial) → keyed "15:15"
     *
     * Only chains a candle if it is EXACTLY 15 min after the previous.
     * OI = last candle in the group. Volume = sum of all.
     */
    private function aggregate15to1hr(array $candles15): array
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
     * Generate 1hr interval start times: 09:15, 10:15, 11:15 … 15:15
     * Exactly matches Live30MinOhlcCollector::generateIntervals()
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
                    Log::error("Collect30MinOhlcData: Fetch failed for token {$instrumentToken} "
                        . "after {$maxRetries} attempts on {$date->toDateString()}: {$e->getMessage()}");
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
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}