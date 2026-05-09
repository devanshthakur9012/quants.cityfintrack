<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\NextSeriesDailyOhlcData;
use App\Models\NextSeriesDailyOhlcSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NextSeriesDailyOhlcCollector
 *
 * Collects DAILY (one bar per trading day) option chain OHLC data for the
 * NEXT expiry series — always the series AFTER the currently live one.
 *
 * Each daily bar is built by aggregating all 15-min candles (09:15–15:15)
 * into a single OHLC bar per instrument per day.
 *
 * ─── Expiry Logic ────────────────────────────────────────────────────────────
 *
 * Monthly symbols (e.g. RELIANCE, TCS …):
 *   Always collect for the next calendar-month expiry.
 *   Example (processing any date in March):
 *     current expiry   → last Thursday of March
 *     collected expiry → last Thursday of April  ← always one month ahead
 *
 * Weekly symbols (NIFTY, SENSEX, BANKNIFTY …):
 *   Always collect for the weekly expiry that comes AFTER the currently live one.
 *   Example (processing Mon 10 Mar):
 *     current live weekly → Tue 11 Mar (NIFTY) or Thu 13 Mar
 *     collected expiry    → the following week's expiry
 *
 *   On expiry day itself:
 *     current live = today's expiry (still live until EOD)
 *     next         = the week after → collected correctly
 *
 * ─── Data stored ─────────────────────────────────────────────────────────────
 *
 * For every resolved "next" expiry, per trading day:
 *   • 1  FUT row
 *   • 11 CE rows  (ATM−5 … ATM+5)
 *   • 11 PE rows
 *   = 23 rows per symbol per day
 *
 * ATM is frozen at the FUT 09:15 open price rounded to the nearest strike
 * interval, keeping it consistent across the whole day.
 *
 * ─── Usage ───────────────────────────────────────────────────────────────────
 *
 * Live (today only — called by cron after market close):
 *   php artisan options:collect-next-series-daily
 *
 * Single historical date:
 *   php artisan options:collect-next-series-daily --from=2026-03-01
 *
 * Date range (historical backfill):
 *   php artisan options:collect-next-series-daily --from=2026-01-01 --to=2026-03-14
 *
 * Specific symbol only:
 *   php artisan options:collect-next-series-daily --symbol=NIFTY --from=2026-03-01 --to=2026-03-14
 *
 * ─── Scheduling (Kernel.php) ─────────────────────────────────────────────────
 *
 *   $schedule->command('options:collect-next-series-daily')
 *       ->dailyAt('15:45')
 *       ->weekdays()
 *       ->timezone('Asia/Kolkata')
 *       ->appendOutputTo(storage_path('logs/next-series-daily-ohlc.log'));
 */
class NextSeriesDailyOhlcCollector extends Command
{
    use OptionExpiryResolver;

    // ── Change to switch the target broker ───────────────────────────────────
    private const BROKER_CLIENT_ID = 'DB0542';

    private const MARKET_OPEN  = '09:15';
    private const MARKET_CLOSE = '15:30';

    // ─────────────────────────────────────────────────────────────────────────

    protected $signature = 'options:collect-next-series-daily
                            {--symbol=       : Specific symbol (e.g., NIFTY)}
                            {--from=         : Start date Y-m-d (default: today). For historical range.}
                            {--to=           : End date   Y-m-d (default: same as --from / today)}
                            {--retry=3       : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50      : Batch upsert chunk size}';

    protected $description = 'Next-series DAILY OHLC collector — always fetches the NEXT monthly/weekly expiry. Supports --from / --to for historical backfill.';

    // ── In-memory caches (reset per day in date loop) ─────────────────────────
    private array $instrumentCache     = [];
    private array $strikeIntervalCache = [];
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Entry point
    // ═════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;

        // ── Resolve date range ────────────────────────────────────────────────
        $fromDate = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::today();

        $toDate = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : $fromDate->copy();

        if ($toDate->lt($fromDate)) {
            $this->error("❌ --to date cannot be before --from date.");
            return 1;
        }

        $isHistorical = $this->option('from') !== null;
        $mode         = $isHistorical ? "Historical backfill" : "Live (today)";

        $this->info("⚡ Next-Series Daily OHLC Collector");
        $this->info("   Mode   : {$mode}");
        $this->info("   Range  : {$fromDate->toDateString()} → {$toDate->toDateString()}");
        $this->info("   Broker : " . self::BROKER_CLIENT_ID);
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
        $symbolsQuery = NextSeriesDailyOhlcSymbol::active();
        if ($specSymbol) {
            $symbolsQuery->where('symbol', $specSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols in next_series_daily_ohlc_symbols table!');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));
        $this->newLine();

        // ── Build list of trading days in range ───────────────────────────────
        $tradingDays = $this->buildTradingDays($fromDate, $toDate);

        if (empty($tradingDays)) {
            $this->warn("⏭  No trading days found in the given range (weekends / holidays only).");
            return 0;
        }

        $this->info("   Trading days to process: " . count($tradingDays));
        $this->newLine();

        // ── Date loop ─────────────────────────────────────────────────────────
        foreach ($tradingDays as $day) {
            $this->info("═══ Processing {$day->toDateString()} ════════════════════════════════");

            // Reset per-day instrument cache (expiries shift as we move across dates)
            $this->instrumentCache     = [];
            $this->strikeIntervalCache = [];

            foreach ($symbols as $baseSymbol) {
                $this->processSymbolForDay(
                    $broker, $baseSymbol, $day,
                    $maxRetries, $retryDelay, $chunkSize
                );
            }

            $this->newLine();
        }

        $this->info("✅ Next-series daily collection complete — " . Carbon::now()->format('Y-m-d H:i:s'));

        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Per-day per-symbol processing
    // ═════════════════════════════════════════════════════════════════════════

    private function processSymbolForDay(
        BrokerApi $broker,
        string $baseSymbol,
        Carbon $date,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): void {
        // ── Resolve the NEXT expiry for this symbol on this date ──────────────
        $nextExpiry = $this->resolveNextSeriesExpiry($baseSymbol, $date);

        if ($nextExpiry === null) {
            $this->error("   ✗ {$baseSymbol} — could not determine next expiry for {$date->toDateString()}. Skipping.");
            Log::error("NextSeriesDaily: Cannot resolve next expiry for {$baseSymbol} on {$date->toDateString()}");
            return;
        }

        $this->info("   📊 {$baseSymbol} — next expiry: {$nextExpiry}");

        // ── Already collected for this day? ───────────────────────────────────
        $alreadyExists = NextSeriesDailyOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->exists();

        if ($alreadyExists) {
            $this->info("      ✓ {$baseSymbol} [{$nextExpiry}] — already collected for {$date->toDateString()}, skipping.");
            return;
        }

        // ── Pre-warm instrument cache for this expiry ─────────────────────────
        $this->prewarmInstrumentCache($baseSymbol, $nextExpiry);

        // ── Resolve FUT instrument ────────────────────────────────────────────
        $futInstrument = $this->resolveFutInstrument($baseSymbol, $nextExpiry, $date);

        if (!$futInstrument) {
            $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} next expiry {$nextExpiry} — skipping");
            Log::warning("NextSeriesDaily: No FUT instrument for {$baseSymbol} expiry {$nextExpiry}");
            return;
        }

        // ── Resolve strike interval ───────────────────────────────────────────
        $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $nextExpiry);

        if ($strikeInterval === null) {
            $this->error("      ✗ {$baseSymbol} [{$nextExpiry}] — strike interval unknown, SKIPPED.");
            Log::error("NextSeriesDaily: Cannot determine strike interval for {$baseSymbol} expiry {$nextExpiry}.");
            return;
        }

        $this->info("      Strike interval: {$strikeInterval}");

        // ── Fetch full-day FUT candles (15-min) ───────────────────────────────
        $futCandles15 = $this->fetchDayCandles(
            $broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($futCandles15)) {
            $this->error("      ✗ {$baseSymbol} [{$nextExpiry}] — no FUT candles on {$date->toDateString()}, skipping.");
            Log::error("NextSeriesDaily: No FUT candles for {$baseSymbol} [{$nextExpiry}] on {$date->toDateString()}");
            return;
        }

        // ── Freeze ATM at 09:15 open ──────────────────────────────────────────
        $candleMap    = $this->indexCandlesByTime($futCandles15);
        $firstCandle  = $candleMap[self::MARKET_OPEN] ?? reset($candleMap);

        if (!$firstCandle) {
            $this->error("      ✗ {$baseSymbol} [{$nextExpiry}] — cannot determine opening price, skipping.");
            return;
        }

        $openPrice    = $firstCandle->open;
        $frozenAtm    = round($openPrice / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        // ── Aggregate all 15-min candles → single daily bar ───────────────────
        $futDailyBar = $this->aggregateToDailyBar($futCandles15);

        $this->info("      FUT open = {$openPrice} | ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");
        $this->info("      Strikes: " . implode(', ', $frozenStrikes));
        $this->info("      FUT daily bar: O={$futDailyBar->open} H={$futDailyBar->high} L={$futDailyBar->low} C={$futDailyBar->close}");

        // ── Fetch & aggregate option candles ──────────────────────────────────
        $optionBars = $this->fetchAllOptionDailyBars(
            $broker, $baseSymbol, $frozenStrikes, $nextExpiry, $date, $maxRetries, $retryDelay
        );

        // ── Build rows ────────────────────────────────────────────────────────
        $now  = now()->toDateTimeString();
        $rows = [];

        // FUT row
        $rows[] = $this->buildFutRow(
            $broker->id, $baseSymbol, $futInstrument,
            $futDailyBar, $frozenAtm, $nextExpiry, $date, $now
        );

        // CE / PE rows
        foreach (['CE', 'PE'] as $optionType) {
            foreach ($frozenStrikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$nextExpiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    Log::warning("NextSeriesDaily: Instrument not in cache — {$cacheKey} on {$date->toDateString()}.");
                    continue;
                }

                $token     = $instrument->instrument_token;
                $bar       = $optionBars[$token] ?? null;
                $isMissing = ($bar === null);

                if ($isMissing) {
                    $this->warn("      ⚠️  {$optionType} {$strike} — no data, storing zeros");
                    Log::warning("NextSeriesDaily: Option bar missing — {$optionType} {$strike} "
                        . "for {$baseSymbol} [{$nextExpiry}] on {$date->toDateString()}");
                }

                $rows[] = $this->buildOptionRow(
                    $broker->id, $baseSymbol,
                    $futInstrument->trading_symbol,
                    $futDailyBar->close,
                    $frozenAtm, $optionType, $strike,
                    $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                    $instrument, $bar, $nextExpiry, $date, $now, $isMissing
                );
            }
        }

        // ── Batch upsert ──────────────────────────────────────────────────────
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [{$nextExpiry}] — {$inserted} rows upserted for {$date->toDateString()}");
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Core Logic: Resolve the NEXT series expiry for a given date
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Returns the expiry that is ONE step ahead of the currently live expiry
     * on $date.
     *
     * Weekly → next weekly expiry (index [1] in sorted upcoming list)
     * Monthly → next month's expiry
     */
    private function resolveNextSeriesExpiry(string $symbol, Carbon $date): ?string
    {
        $isWeekly = $this->isWeeklyExpirySymbol($symbol);

        $allExpiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($allExpiries)) {
            return null;
        }

        return $isWeekly
            ? $this->resolveNextWeeklyExpiry($allExpiries)
            : $this->resolveNextMonthlyExpiry($allExpiries);
    }

    /**
     * Weekly: index [0] = current live, index [1] = next week → return [1].
     */
    private function resolveNextWeeklyExpiry(array $sortedExpiries): ?string
    {
        return $sortedExpiries[1] ?? $sortedExpiries[0] ?? null;
    }

    /**
     * Monthly: group by Y-m, take the second month group.
     */
    private function resolveNextMonthlyExpiry(array $sortedExpiries): ?string
    {
        $byMonth = [];
        foreach ($sortedExpiries as $exp) {
            $key           = Carbon::parse($exp)->format('Y-m');
            $byMonth[$key] = $exp; // last expiry in that month wins
        }

        $months = array_values($byMonth);

        return $months[1] ?? $months[0] ?? null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Aggregate 15-min candles → single daily bar
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Collapses all 15-min candles for the day into one OHLC bar.
     * O = open of first candle
     * H = max high
     * L = min low
     * C = close of last candle
     * V = sum of all volumes
     * OI = OI of last candle
     */
    private function aggregateToDailyBar(array $candles15): \stdClass
    {
        $open   = null;
        $high   = PHP_INT_MIN;
        $low    = PHP_INT_MAX;
        $close  = null;
        $volume = 0;
        $oi     = 0;
        $date   = null;

        foreach ($candles15 as $c) {
            if ($open === null) {
                $open = $c->open;
                $date = $c->date;
            }
            $high   = max($high,  $c->high);
            $low    = min($low,   $c->low);
            $close  = $c->close;
            $volume += ($c->volume ?? 0);
            $oi     = $c->oi ?? 0;
        }

        $bar         = new \stdClass();
        $bar->open   = $open   ?? 0;
        $bar->high   = $high   === PHP_INT_MIN ? 0 : $high;
        $bar->low    = $low    === PHP_INT_MAX ? 0 : $low;
        $bar->close  = $close  ?? 0;
        $bar->volume = $volume;
        $bar->oi     = $oi;
        $bar->date   = $date;

        return $bar;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Fetch + aggregate all option daily bars
    // ═════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionDailyBars(
        BrokerApi $broker,
        string $baseSymbol,
        array $strikes,
        string $expiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $bars = [];

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    $this->warn("      ⚠️  Instrument not in cache: {$cacheKey}");
                    Log::warning("NextSeriesDaily: fetchAllOptionDailyBars — instrument missing: {$cacheKey}");
                    continue;
                }

                $token     = $instrument->instrument_token;
                $candles15 = $this->fetchDayCandles($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($candles15)) {
                    $bars[$token] = $this->aggregateToDailyBar($candles15);
                    $this->info("      {$optionType} {$strike}: " . count($candles15) . " candles → daily bar aggregated");
                } else {
                    $bars[$token] = null;
                    $this->warn("      {$optionType} {$strike}: no data — zero-filled");
                    Log::warning("NextSeriesDaily: No 15-min data for {$optionType} {$strike} [{$expiry}] on {$date->toDateString()}");
                }
            }
        }

        return $bars;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Build list of trading days in a date range
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Returns Carbon instances for every weekday in [$fromDate, $toDate] that
     * is not a market holiday. Past and future dates are both accepted so the
     * same logic handles live and historical runs.
     */
    private function buildTradingDays(Carbon $fromDate, Carbon $toDate): array
    {
        $days   = [];
        $period = CarbonPeriod::create($fromDate, '1 day', $toDate);

        foreach ($period as $day) {
            if ($day->isWeekend()) {
                continue;
            }
            if ($this->isMarketHoliday($day->toDateString())) {
                $this->line("   ⏭  {$day->toDateString()} is a market holiday — skipped.");
                continue;
            }
            $days[] = $day->copy()->startOfDay();
        }

        return $days;
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
            Log::warning("NextSeriesDaily: resolveStrikeInterval — fewer than 2 CE strikes found "
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
            Log::warning("NextSeriesDaily: resolveStrikeInterval — could not compute valid gap "
                . "for {$symbol} expiry {$expiry}.");
            return null;
        }

        $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
        return (float) $minGap;
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
    // Batch upsert
    // ═════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            NextSeriesDailyOhlcData::upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'trading_symbol'],
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
        \stdClass $bar,
        float $atmStrike,
        string $expiry,
        Carbon $tradeDate,
        string $now
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'trading_symbol'   => $futInstrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futInstrument->trading_symbol,
            'future_price'     => $bar->close,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'instrument_token' => $futInstrument->instrument_token,
            'open'             => $bar->open,
            'high'             => $bar->high,
            'low'              => $bar->low,
            'close'            => $bar->close,
            'volume'           => $bar->volume,
            'oi'               => $bar->oi,
            'strike_position'  => 'N/A',
            'expiry_date'      => $expiry,
            'is_missing'       => 0,
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
        ?\stdClass $bar,
        string $expiry,
        Carbon $tradeDate,
        string $now,
        bool $isMissing = false
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'trading_symbol'   => $instrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futureSymbol,
            'future_price'     => $futurePrice,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => $optionType,
            'strike'           => $strike,
            'instrument_token' => $instrument->instrument_token,
            'open'             => $bar ? $bar->open   : 0,
            'high'             => $bar ? $bar->high   : 0,
            'low'              => $bar ? $bar->low    : 0,
            'close'            => $bar ? $bar->close  : 0,
            'volume'           => $bar ? $bar->volume : 0,
            'oi'               => $bar ? $bar->oi     : 0,
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
     * Fetch all 15-min candles for a given instrument token on a given day.
     * Used for both FUT and options — caller then aggregates to a daily bar.
     */
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
                    Log::error("NextSeriesDaily: Fetch failed for token {$instrumentToken} after {$maxRetries} attempts: {$e->getMessage()}");
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