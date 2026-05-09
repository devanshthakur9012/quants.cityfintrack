<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\Original30MinOhlcData;
use App\Models\Original30MinOhlcSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * LiveOriginal30MinOhlcCollector
 *
 * Collects TRUE 30-minute OHLC option chain data (not 1hr).
 * Each bar = 2 × 15-min candles aggregated in memory.
 *
 * Bar start times: 09:15, 09:45, 10:15, 10:45 … 15:15
 * (13 bars per trading day)
 *
 * ─── Expiry Logic (KEY DIFFERENCE from the 1hr collector) ────────────────
 *
 * OLD logic (1hr collector):
 *   On expiry day → immediately shift CE/PE to NEXT expiry.
 *   This caused options to be fetched for the next expiry even while
 *   the current week's contracts were still the most-traded ones.
 *
 * NEW logic (this collector):
 *   • NIFTY expiry falls on a Tuesday (weekly).
 *   • On the expiry day itself (e.g. 2026-03-10) → still fetch
 *     CE/PE for the CURRENT expiry (options are live until EOD).
 *   • From the NEXT trading day onwards (e.g. 2026-03-11) → switch
 *     to the NEXT expiry automatically.
 *   • Monthly symbols follow the same rule — on the expiry day itself
 *     we still collect for the current expiry; next day we switch.
 *   • Rollover window (dual collection) is preserved:
 *       - NIFTY/SENSEX (weekly) : 1 trading day before expiry
 *       - Monthly symbols       : 5 trading days before expiry
 *     This means the day BEFORE expiry you'll get both current + next
 *     expiry collected (same as the 1hr collector does), but ON the
 *     expiry day itself we still stay on the current expiry rather
 *     than jumping ahead.
 *
 * Cron (Kernel.php):
 *   ->cron('46 9-15 * * 1-5')      // at :46 past each hour, 9–15
 *   ->cron('16,46 * * * *')        // or every 30 mins at :16 and :46
 *   ->between('9:30', '15:50')
 *   ->timezone('Asia/Kolkata')
 *
 * Suggested entry in Kernel.php:
 *   $schedule->command('options:live-collect-original-30min')
 *       ->cron('16,46 * * * *')
 *       ->weekdays()
 *       ->timezone('Asia/Kolkata')
 *       ->between('9:30', '15:50')
 *       ->appendOutputTo(storage_path('logs/live-original-30min-ohlc.log'));
 */
class LiveOriginal30MinOhlcCollector extends Command
{
    use OptionExpiryResolver;

    // ── Change to switch the target broker ───────────────────────────────────
    private const BROKER_CLIENT_ID = 'DB0542';

    private const MARKET_START = '09:15';
    private const MARKET_END   = '15:15';

    // ─────────────────────────────────────────────────────────────────────────

    protected $signature = 'options:live-collect-original-30min
                            {--symbol=       : Specific symbol (e.g., NIFTY)}
                            {--retry=3       : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50      : Batch upsert chunk size}
                            {--force-date=   : Override date (Y-m-d), for testing}';

    protected $description = 'Original 30-min option OHLC collector — 2×15min aggregation, stay-on-expiry-day logic, gap-fill, frozen ATM';

    // ── In-memory caches ──────────────────────────────────────────────────────
    private array $instrumentCache     = [];   // "{SYMBOL}_{STRIKE}_{TYPE}_{EXPIRY}" → ZerodhaInstrument
    private array $strikeIntervalCache = [];   // "{SYMBOL}_{EXPIRY}" → float
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

        $this->info("⚡ Original 30-Min OHLC Collector — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Broker: " . self::BROKER_CLIENT_ID . " | 2×15min bars | Stay-on-expiry-day | Gap-Fill | Frozen ATM");
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
        $symbolsQuery = Original30MinOhlcSymbol::active();
        if ($specSymbol) {
            $symbolsQuery->where('symbol', $specSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols in original_30min_ohlc_symbols table!');
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
            $this->warn("   ⏳ No completed 30-min candle available yet.");
            return 0;
        }

        $this->info("   Last completed slot : " . $lastCompletedSlot->format('H:i'));
        $this->info("   Intervals to cover  : 09:15 → " . $lastCompletedSlot->format('H:i')
            . " (" . count($intervalsToProcess) . " slots)");
        $this->newLine();

        // ── Symbol loop ───────────────────────────────────────────────────────
        $totalNewIntervals = 0;

        foreach ($symbols as $baseSymbol) {
            // ── Resolve expiries using the NEW "stay-on-expiry-day" logic ─────
            $expiries = $this->resolveExpiriesOriginal30Min($baseSymbol, $today);

            $this->info("   📊 {$baseSymbol} — expir" . (count($expiries) > 1 ? 'ies' : 'y')
                . ': ' . implode(' + ', $expiries));

            // Pre-warm instrument cache for each expiry's CE/PE lookup expiry
            foreach ($expiries as $expiry) {
                $cepeExpiry = $this->resolveCePeExpiryOriginal30Min($baseSymbol, $expiry, $today);
                $this->prewarmInstrumentCache($baseSymbol, $cepeExpiry);
            }

            foreach ($expiries as $expiry) {
                $futInstrument = $this->resolveFutInstrument($baseSymbol, $expiry, $today);

                if (!$futInstrument) {
                    $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} expiry {$expiry} — skipping");
                    Log::warning("LiveOriginal30Min: No FUT instrument for {$baseSymbol} expiry {$expiry}");
                    continue;
                }

                $cepeExpiry = $this->resolveCePeExpiryOriginal30Min($baseSymbol, $expiry, $today);

                // ── Resolve strike interval dynamically ───────────────────────
                $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $cepeExpiry);

                if ($strikeInterval === null) {
                    $this->error("      ✗ {$baseSymbol} [{$expiry}] — strike interval unknown, SKIPPED. (see logs)");
                    Log::error("LiveOriginal30Min: Cannot determine strike interval for {$baseSymbol} expiry {$cepeExpiry}. "
                        . "Check ZerodhaInstrument has CE/PE rows for this symbol+expiry.");
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
        $this->info("✅ Original 30-min collection complete — " . Carbon::now()->format('H:i:s'));

        if ($totalNewIntervals > 0) {
            \Artisan::call('pivot:place-orders');
            $this->info("🚀 Triggered pivot:place-orders ({$totalNewIntervals} new interval(s) inserted).");
        } else {
            $this->warn("⏭  No new intervals inserted — data already up to date. Order placement skipped.");
        }

        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // NEW Expiry Logic — "Stay on current expiry on expiry day itself"
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Resolve which expiries to collect for today, using the NEW rule:
     *
     *   • If today == expiry day  → collect for CURRENT expiry (don't skip ahead).
     *     (Options are still live and tradeable on their expiry date until EOD.)
     *   • If today >  expiry day  → that expiry is past, move to next expiry.
     *   • Rollover window (day BEFORE expiry): collect BOTH current + next expiry.
     *     - Weekly (NIFTY, SENSEX): 1 trading day before
     *     - Monthly (all others)  : 5 trading days before
     *
     * Contrast with the 1hr collector which calls resolveExpiriesFor30Min()
     * and immediately discards today's expiry, jumping to next.
     *
     * @return string[]  Array of 'Y-m-d' expiry strings (1 or 2 elements)
     */
    private function resolveExpiriesOriginal30Min(string $symbol, Carbon $today): array
    {
        // resolveExpiries() from the trait returns expiries starting from >= today
        // (or the nearest upcoming one). It already handles the rollover window.
        $expiries = $this->resolveExpiries($symbol, $today);

        if (empty($expiries)) {
            $fallback = $this->isWeeklyExpirySymbol($symbol)
                ? $today->copy()->addDays(7)->toDateString()
                : $today->copy()->addDays(30)->toDateString();
            $this->warn("      ⚠️  {$symbol}: No expiries found in DB — using fallback {$fallback}");
            return [$fallback];
        }

        // The trait's resolveExpiries() already handles:
        //   • nearest expiry >= tradeDate  (which INCLUDES today if today == expiry)
        //   • rollover window (dual expiry)
        // So we just return as-is — today's expiry is kept on expiry day.
        return $expiries;
    }

    /**
     * Determine which expiry to use for CE/PE option lookup.
     *
     * NEW RULE:
     *   • If today == expiry day → use THIS expiry for CE/PE (stay on it).
     *   • If today >  expiry day → that's already filtered upstream; should
     *     not reach here. But guard anyway — return next expiry.
     *
     * This is the critical difference from the 1hr collector's getCePeExpiry(),
     * which would jump to next expiry the moment today == expiry day.
     */
    private function resolveCePeExpiryOriginal30Min(string $symbol, string $futExpiry, Carbon $today): string
    {
        $expiryDate = Carbon::parse($futExpiry);

        // If the fut expiry has ALREADY PASSED (today is strictly after expiry)
        // we need the next expiry. Normally this shouldn't happen because
        // resolveExpiriesOriginal30Min() wouldn't include a past expiry,
        // but guard just in case.
        if ($expiryDate->lt($today->copy()->startOfDay())) {
            $next = $this->fetchNextExpiryAfter($symbol, $expiryDate);
            return $next ?? $futExpiry;
        }

        // today == expiry day OR today is before expiry → use current expiry
        return $futExpiry;
    }

    /**
     * Fetch the next available expiry strictly after $afterDate from DB.
     */
    private function fetchNextExpiryAfter(string $symbol, Carbon $afterDate): ?string
    {
        $isWeekly = $this->isWeeklyExpirySymbol($symbol);

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

        if (empty($expiries)) {
            return null;
        }

        // Monthly: pick the last expiry of the nearest month
        if (!$isWeekly) {
            $byMonth = [];
            foreach ($expiries as $exp) {
                $key           = Carbon::parse($exp)->format('Y-m');
                $byMonth[$key] = $exp;
            }
            $expiries = array_values($byMonth);
        }

        return $expiries[0] ?? null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Dynamic strike interval resolution
    // ═════════════════════════════════════════════════════════════════════════

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
            Log::warning("LiveOriginal30Min: resolveStrikeInterval — fewer than 2 CE strikes found "
                . "for {$symbol} expiry {$expiry}.");
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
            Log::warning("LiveOriginal30Min: resolveStrikeInterval — could not compute valid gap "
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
        // ── Which slots are already stored? ───────────────────────────────────
        $storedTimes = Original30MinOhlcData::whereDate('trade_date', $date)
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
                . $currentSlot->format('H:i') . ")"
                . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ""));
        } else {
            $this->info("      📥 {$baseSymbol} [{$futExpiry}] — fetching current slot "
                . $currentSlot->format('H:i')
                . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ""));
        }

        // ── Fetch full-day FUT candles (15-min raw) ───────────────────────────
        $allFutCandles15 = $this->fetchDayCandles(
            $broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($allFutCandles15)) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — could not fetch FUT data, skipping");
            Log::error("LiveOriginal30Min: No FUT candles for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
            return 0;
        }

        // ── Freeze ATM at 09:15 close ─────────────────────────────────────────
        $first15Map = $this->indexCandlesByTime($allFutCandles15);
        if (!isset($first15Map['09:15'])) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — 09:15 FUT candle missing, cannot freeze ATM");
            Log::error("LiveOriginal30Min: 09:15 FUT candle missing for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
            return 0;
        }

        $openPrice09   = $first15Map['09:15']->close;
        $frozenAtm     = round($openPrice09 / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      FUT 09:15 close = {$openPrice09} | ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");
        $this->info("      Strikes: " . implode(', ', $frozenStrikes));

        // Aggregate FUT to 30-min map (2 × 15-min candles)
        $futCandleMap = $this->aggregate15to30($allFutCandles15);
        $this->info("      FUT: " . count($allFutCandles15) . " × 15-min → " . count($futCandleMap) . " × 30-min");

        // ── Fetch all option candles ──────────────────────────────────────────
        $optionDayCache = $this->fetchAllOptionDayCandles(
            $broker, $baseSymbol, $frozenStrikes, $cepeExpiry, $date, $maxRetries, $retryDelay
        );

        // ── Build rows ────────────────────────────────────────────────────────
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
                Log::warning("LiveOriginal30Min: FUT candle missing at {$timeKey} for {$baseSymbol} [{$futExpiry}] on {$date->toDateString()}");
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
                        Log::warning("LiveOriginal30Min: Instrument not in cache — {$cacheKey} at {$timeKey} on {$date->toDateString()}.");
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        $this->warn("      ⚠️  {$timeKey} {$optionType} {$strike} — missing, storing zeros");
                        Log::warning("LiveOriginal30Min: Option candle missing — {$optionType} {$strike} at {$timeKey} "
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

        // ── Batch upsert ──────────────────────────────────────────────────────
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [{$futExpiry}] — {$inserted} rows upserted "
            . "({$newIntervalCount} new interval(s))");

        return $newIntervalCount;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FUT instrument resolution
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveFutInstrument(string $symbol, string $expiry, Carbon $date): ?ZerodhaInstrument
    {
        $isWeekly = $this->isWeeklyExpirySymbol($symbol);

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
                    Log::warning("LiveOriginal30Min: fetchAllOptionDayCandles — instrument missing: {$cacheKey}");
                    continue;
                }

                $token     = $instrument->instrument_token;
                $candles15 = $this->fetchDayCandles($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($candles15)) {
                    $cache[$token] = $this->aggregate15to30($candles15);
                    $this->info("      {$optionType} {$strike}: " . count($candles15)
                        . " × 15-min → " . count($cache[$token]) . " × 30-min");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data — zero-filled");
                    Log::warning("LiveOriginal30Min: No 15-min data for {$optionType} {$strike} [{$expiry}] on {$date->toDateString()}");
                }
            }
        }

        return $cache;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 15-min → 30-min aggregation (exactly 2 candles per bar)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Aggregate 15-min candles into 30-min bars.
     *
     * Bar alignment:
     *   09:15 + 09:30  → bar keyed "09:15"
     *   09:45 + 10:00  → bar keyed "09:45"
     *   10:15 + 10:30  → bar keyed "10:15"
     *   …
     *   15:15 (single)  → bar keyed "15:15"  (market close — only one 15-min candle)
     *
     * If the second 15-min candle is missing (e.g. data gap), the bar is still
     * formed from the first candle alone so no slot is silently dropped.
     */
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

            $c1 = $byTime[$t1];
            $c2 = null;

            // Only pair with the next candle if it is exactly 15 minutes later
            $expected2 = Carbon::createFromFormat('H:i', $t1)->addMinutes(15)->format('H:i');
            if ($t2 === $expected2) {
                $c2 = $byTime[$t2];
            }

            $last = $c2 ?? $c1;

            $bar         = new \stdClass();
            $bar->open   = $c1->open;
            $bar->high   = max($c1->high, $c2?->high ?? 0);
            $bar->low    = min($c1->low,  $c2?->low  ?? PHP_INT_MAX);
            $bar->close  = $last->close;
            $bar->volume = ($c1->volume ?? 0) + ($c2?->volume ?? 0);
            $bar->oi     = $last->oi ?? 0;
            $bar->date   = $c1->date;

            $result[$t1] = $bar;

            // Advance by 2 if both candles were consumed, else 1
            $i += ($c2 !== null ? 2 : 1);
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
            Original30MinOhlcData::upsert(
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
    // Interval generation — 30-min bars
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Generate 30-min bar start times for the trading day.
     * 09:15, 09:45, 10:15, 10:45, 11:15, 11:45, 12:15, 12:45,
     * 13:15, 13:45, 14:15, 14:45, 15:15  → 13 bars
     */
    private function generateIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 15);
        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes(30);
        }
        return $intervals;
    }

    /**
     * Last COMPLETED 30-min slot, clamped to market hours.
     *
     * A 30-min bar starting at T is complete when now >= T + 30 minutes.
     * E.g. the 09:15 bar completes at 09:45, the 15:15 bar completes at 15:45.
     *
     * Cron at :16 and :46 past the hour captures bars that close at :15 and :45.
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
            if ($t + 30 <= $nowTotalMins) {
                $result = $t;
            }
            $t += 30;
        }

        if ($result === null) {
            return $marketStart->copy()->subMinute();
        }

        $result = min($result, $end);

        return $date->copy()->setTime(intdiv($result, 60), $result % 60, 0);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Utility helpers
    // ═════════════════════════════════════════════════════════════════════════

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
                    Log::error("LiveOriginal30Min: Fetch failed for token {$instrumentToken} after {$maxRetries} attempts: {$e->getMessage()}");
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
        $diff = (int) round(($strike - $atm) / $interval);
        if ($diff === 0)  return 'ATM';
        if ($diff === 1)  return 'ATM+1';
        if ($diff === -1) return 'ATM-1';
        if ($diff === 2)  return 'ATM+2';
        if ($diff === -2) return 'ATM-2';
        return ($diff > 0 ? "ATM+{$diff}" : "ATM{$diff}");
    }
}