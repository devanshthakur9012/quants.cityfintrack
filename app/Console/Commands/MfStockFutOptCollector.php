<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\MutualFundStock;
use App\Models\MfStockFutureOhlc;
use App\Models\MfStockOptionOhlc;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * MfStockFutOptCollector
 *
 * Collects 1-hour FUT + CE/PE options OHLC data for all stocks
 * held in Mutual Fund portfolios — for the Multi-Asset MF strategy:
 *
 *   Strategy: BUY 1 FUT lot + SELL 2 OTM CE lots (same stock, same expiry)
 *   P&L = FUT_PnL + (CE_sell_price − CE_current_price) × 2 × lot_size
 *
 * ─────────────────────────────────────────────────────────────────
 * Cron (live, every 1 hour during market hours):
 *   0 9-15 * * 1-5  php artisan mf:collect-fut-opt >> /dev/null 2>&1
 *
 * Historical (backfill):
 *   php artisan mf:collect-fut-opt --mode=historical --from=2024-01-01 --to=2024-12-31
 *
 * Single symbol:
 *   php artisan mf:collect-fut-opt --symbol=HDFCBANK
 * ─────────────────────────────────────────────────────────────────
 *
 * Behaviour:
 *   1. Reads unique stock symbols from mutual_fund_stocks (active, active fund)
 *   2. For each symbol resolves the correct FUT expiry (nearest monthly)
 *   3. Freezes ATM at 09:15 FUT close — all strikes computed from this
 *   4. Stores ATM ±5 strikes for CE and PE (11 strikes × 2 types per expiry)
 *   5. Gap-fills: skips already-stored intervals (safe to re-run)
 *   6. Stores FUT OI — critical for the strategy's OI-based entry logic
 *   7. 400ms rate-limit sleep between API calls (Zerodha safe limit)
 */
class MfStockFutOptCollector extends Command
{
    use OptionExpiryResolver;

    private const BROKER_CLIENT_ID = 'ZZL808'; // ← change to your broker ID

    protected $signature = 'mf:collect-fut-opt
                            {--mode=live         : "live" = today up to last completed candle | "historical" = date range}
                            {--from=             : Start date Y-m-d (required for historical)}
                            {--to=               : End date Y-m-d (default: yesterday)}
                            {--symbol=           : Limit to one stock symbol e.g. HDFCBANK}
                            {--chunk=50          : DB upsert batch size}
                            {--retry=3           : Retries per API call}
                            {--retry-delay=2     : Seconds between retries}';

    protected $description = 'Collect 1-hour FUT + CE/PE options OHLC for MF portfolio stocks (Multi-Asset MF strategy)';

    private const MARKET_START = '09:15';
    private const MARKET_END   = '15:15';  // last 1hr candle opens at 14:15, closes at 15:15

    // ATM ±5 strikes = 11 strikes per side
    private const STRIKES_EACH_SIDE = 5;

    /** In-memory instrument cache: "SYMBOL_STRIKE_TYPE_EXPIRY" → ZerodhaInstrument */
    private array $instrumentCache = [];

    /** In-memory strike interval cache: "SYMBOL_EXPIRY" → float */
    private array $strikeIntervalCache = [];

    private ?BrokerZerodhaHelper $zerodhaHelper = null;
    private ?BrokerApi $broker = null;

    // ════════════════════════════════════════════════════════════════
    // ENTRY
    // ════════════════════════════════════════════════════════════════
    public function handle(): int
    {
        $mode        = strtolower($this->option('mode'));
        $specSymbol  = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $chunkSize   = (int) $this->option('chunk');
        $maxRetries  = (int) $this->option('retry');
        $retryDelay  = (int) $this->option('retry-delay');

        $this->printHeader($mode);

        // ── Broker ────────────────────────────────────────────────
        $this->broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()->validToken()->first();

        if (! $this->broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid!");
            return 1;
        }
        $this->info("🔑 Broker: {$this->broker->client_name} (ID: {$this->broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($this->broker);

        // ── Get unique MF stock symbols ───────────────────────────
        $symbolsQuery = MutualFundStock::active()
            ->whereHas('fund', fn($q) => $q->active())
            ->select('stock_symbol')
            ->distinct()
            ->orderBy('stock_symbol');

        if ($specSymbol) {
            $symbolsQuery->where('stock_symbol', $specSymbol);
        }

        $symbols = $symbolsQuery->pluck('stock_symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active MF stocks found!');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', array_slice($symbols, 0, 10))
            . (count($symbols) > 10 ? ' ...' : ''));
        $this->newLine();

        // ── Build trading days list ───────────────────────────────
        $tradingDays = $this->buildTradingDays($mode);
        if (empty($tradingDays)) {
            $this->warn("⏭  No trading days to process.");
            return 0;
        }

        $this->info("   Trading days: " . count($tradingDays));
        $this->newLine();

        // ── Day loop ──────────────────────────────────────────────
        foreach ($tradingDays as $dayIdx => $date) {
            $dayNum = $dayIdx + 1;
            $total  = count($tradingDays);

            $this->info("════════════════════════════════════════════════════════");
            $this->info("📅 Day {$dayNum}/{$total} — {$date->toDateString()}");
            $this->info("════════════════════════════════════════════════════════");

            // Reset caches per day
            $this->instrumentCache    = [];
            $this->strikeIntervalCache = [];

            // Determine intervals for this day
            $allIntervals      = $this->buildIntervals($date);
            $intervalsToProcess = $this->resolveIntervalsToProcess($mode, $allIntervals, $date);

            if (empty($intervalsToProcess)) {
                $this->warn("   ⏳ No intervals to process for {$date->toDateString()}");
                continue;
            }

            $this->info("   Intervals: 09:15 → " . end($intervalsToProcess)->format('H:i')
                . " (" . count($intervalsToProcess) . " slots)");
            $this->newLine();

            // ── Symbol loop ───────────────────────────────────────
            foreach ($symbols as $symbol) {
                $this->info("━━━ {$symbol} ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

                // Resolve FUT expiry (nearest monthly expiry >= date)
                $expiry = $this->resolveFutExpiry($symbol, $date);
                if (! $expiry) {
                    $this->warn("   ⚠️  {$symbol} — no FUT expiry found, skipping");
                    continue;
                }
                $this->info("   Expiry: {$expiry}");

                // Resolve FUT instrument
                $futInstrument = $this->resolveFutInstrument($symbol, $expiry);
                if (! $futInstrument) {
                    $this->warn("   ⚠️  {$symbol} — FUT instrument not in zerodha_instruments, skipping");
                    Log::warning("MfStockFutOpt: No FUT instrument for {$symbol} expiry {$expiry}");
                    continue;
                }

                // Resolve strike interval dynamically
                $strikeInterval = $this->resolveStrikeInterval($symbol, $expiry);
                if (! $strikeInterval) {
                    $this->warn("   ⚠️  {$symbol} — could not determine strike interval, skipping");
                    Log::warning("MfStockFutOpt: Cannot determine strike interval for {$symbol} expiry {$expiry}");
                    continue;
                }

                // Pre-warm option instrument cache for this symbol+expiry
                $this->prewarmOptionCache($symbol, $expiry);

                // Process this symbol for the day
                $this->processSymbol(
                    $symbol, $futInstrument, $expiry, $strikeInterval,
                    $date, $intervalsToProcess, $maxRetries, $retryDelay, $chunkSize
                );
            }

            $this->newLine();
            $this->info("✅ Day {$dayNum}/{$total} complete — {$date->toDateString()}");
            $this->newLine();
        }

        $this->info("🏁 All done — " . Carbon::now()->format('H:i:s'));
        return 0;
    }

    // ════════════════════════════════════════════════════════════════
    // PROCESS ONE SYMBOL FOR ONE DAY
    // ════════════════════════════════════════════════════════════════
    private function processSymbol(
        string             $symbol,
        ZerodhaInstrument  $futInstrument,
        string             $expiry,
        float              $strikeInterval,
        Carbon             $date,
        array              $intervalsToProcess,
        int                $maxRetries,
        int                $retryDelay,
        int                $chunkSize
    ): void {
        // ── Check already stored FUT intervals (gap-fill) ─────────
        $storedTimes = MfStockFutureOhlc::where('trade_date', $date->toDateString())
            ->where('symbol', $symbol)
            ->where('expiry_date', $expiry)
            ->where('is_missing', false)
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->flip()->toArray();

        $missing = array_values(array_filter(
            $intervalsToProcess,
            fn($t) => ! isset($storedTimes[$t->format('H:i')])
        ));

        if (empty($missing)) {
            $this->info("   ✓ {$symbol} — already up to date");
            return;
        }

        $this->info("   {$symbol} [{$expiry}] — {$futInstrument->trading_symbol}"
            . " | missing: " . count($missing) . " interval(s)");

        // ── Step 1: Fetch full-day FUT candles ─────────────────────
        $futCandles = $this->fetchDayCandles(
            $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($futCandles)) {
            $this->error("   ✗ {$symbol} — FUT fetch failed, skipping");
            Log::error("MfStockFutOpt: FUT fetch failed for {$symbol} expiry {$expiry} on {$date->toDateString()}");
            return;
        }

        $futMap = $this->indexByTime($futCandles);

        // ── Step 2: Freeze ATM at 09:15 FUT close ─────────────────
        $open915 = $futMap['09:15'] ?? null;
        if (! $open915) {
            $this->error("   ✗ {$symbol} — 09:15 FUT candle missing, cannot freeze ATM");
            return;
        }

        $atmStrike    = round($open915->close / $strikeInterval) * $strikeInterval;
        $strikeList   = $this->buildStrikeList($atmStrike, $strikeInterval);
        $lotSize      = (int) ($futInstrument->lot_size ?? 0);

        $this->info("   ATM = {$atmStrike} | Interval = {$strikeInterval} | Lot = {$lotSize}");
        $this->info("   Strikes: " . implode(', ', $strikeList));

        // ── Step 3: Fetch all option candles for this day ──────────
        $optionDayCache = $this->fetchAllOptionCandles(
            $symbol, $strikeList, $expiry, $date, $maxRetries, $retryDelay
        );

        // ── Step 4: Build rows in memory ───────────────────────────
        $rows              = [];
        $now               = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($missing as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futMap[$timeKey] ?? null;
            if ($futCandle) $lastKnownFutClose = $futCandle->close;
            $isFutMissing = ($futCandle === null);

            if ($isFutMissing) {
                Log::warning("MfStockFutOpt: FUT candle missing at {$timeKey} for {$symbol} on {$date->toDateString()}");
            }

            // ── FUT row ────────────────────────────────────────────
            $rows[] = [
                'symbol'           => $symbol,
                'exchange'         => $this->getExchange($symbol),
                'trading_symbol'   => $futInstrument->trading_symbol,
                'instrument_token' => $futInstrument->instrument_token,
                'trade_date'       => $date->toDateString(),
                'interval_time'    => $intervalTime->toDateTimeString(),
                'expiry_date'      => $expiry,
                'lot_size'         => $lotSize,
                'open'             => $futCandle ? $futCandle->open   : 0,
                'high'             => $futCandle ? $futCandle->high   : 0,
                'low'              => $futCandle ? $futCandle->low    : 0,
                'close'            => $futCandle ? $futCandle->close  : 0,
                'volume'           => $futCandle ? $futCandle->volume : 0,
                'oi'               => $futCandle ? ($futCandle->oi ?? 0) : 0,
                'spot_price'       => null, // can be filled from NSE data if available
                'atm_strike'       => $atmStrike,
                'is_missing'       => $isFutMissing ? 1 : 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            // ── CE + PE rows ───────────────────────────────────────
            foreach (['CE', 'PE'] as $optType) {
                foreach ($strikeList as $strike) {
                    $cacheKey   = "{$symbol}_{$strike}_{$optType}_{$expiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;

                    if (! $instrument) {
                        // Instrument not in Zerodha (far OTM may not exist) — skip silently
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $optCandle = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($optCandle === null);

                    $strikePos = $this->strikePosition($strike, $atmStrike, $strikeInterval);

                    $rows[] = [
                        'symbol'           => $symbol,
                        'exchange'         => $this->getExchange($symbol),
                        'trading_symbol'   => $instrument->trading_symbol,
                        'instrument_token' => $token,
                        'option_type'      => $optType,
                        'strike_price'     => $strike,
                        'strike_position'  => $strikePos,
                        'trade_date'       => $date->toDateString(),
                        'interval_time'    => $intervalTime->toDateTimeString(),
                        'expiry_date'      => $expiry,
                        'open'             => $optCandle ? $optCandle->open   : 0,
                        'high'             => $optCandle ? $optCandle->high   : 0,
                        'low'              => $optCandle ? $optCandle->low    : 0,
                        'close'            => $optCandle ? $optCandle->close  : 0,
                        'volume'           => $optCandle ? $optCandle->volume : 0,
                        'oi'               => $optCandle ? ($optCandle->oi ?? 0) : 0,
                        'fut_price'        => $lastKnownFutClose,
                        'spot_price'       => null,
                        'atm_strike'       => $atmStrike,
                        'is_missing'       => $isMissing ? 1 : 0,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            }
        }

        // ── Step 5: Batch upsert ────────────────────────────────────
        $inserted = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            // FUT rows
            $futRows = array_filter($chunk, fn($r) => ! isset($r['option_type']));
            if (! empty($futRows)) {
                MfStockFutureOhlc::upsert(
                    array_values($futRows),
                    ['symbol', 'expiry_date', 'interval_time'],
                    ['trading_symbol','instrument_token','lot_size',
                     'open','high','low','close','volume','oi',
                     'atm_strike','is_missing','updated_at']
                );
            }

            // Option rows
            $optRows = array_filter($chunk, fn($r) => isset($r['option_type']));
            if (! empty($optRows)) {
                MfStockOptionOhlc::upsert(
                    array_values($optRows),
                    ['symbol','option_type','strike_price','expiry_date','interval_time'],
                    ['trading_symbol','instrument_token','strike_position',
                     'open','high','low','close','volume','oi',
                     'fut_price','atm_strike','is_missing','updated_at']
                );
            }

            $inserted += count($chunk);
        }

        $this->info("   ✅ {$symbol} — {$inserted} rows upserted ("
            . count($missing) . " intervals × "
            . (1 + count($strikeList) * 2) . " instruments)");
    }

    // ════════════════════════════════════════════════════════════════
    // OPTION CANDLE BATCH FETCH
    // ════════════════════════════════════════════════════════════════
    private function fetchAllOptionCandles(
        string $symbol,
        array  $strikes,
        string $expiry,
        Carbon $date,
        int    $maxRetries,
        int    $retryDelay
    ): array {
        $cache      = [];
        $fetchCount = 0;

        foreach (['CE', 'PE'] as $optType) {
            foreach ($strikes as $strike) {
                $cacheKey   = "{$symbol}_{$strike}_{$optType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (! $instrument) continue;

                // Rate limit: 400ms between calls (Zerodha: ~3 req/sec)
                if ($fetchCount > 0) usleep(400_000);

                $token   = $instrument->instrument_token;
                $candles = $this->fetchDayCandles($token, $date, $maxRetries, $retryDelay);
                $fetchCount++;

                $cache[$token] = ! empty($candles) ? $this->indexByTime($candles) : [];

                if (! empty($candles)) {
                    $this->line("      {$optType} {$strike}: " . count($candles) . " candles");
                } else {
                    $this->warn("      {$optType} {$strike}: no data — zero-filled");
                }
            }
        }

        return $cache;
    }

    // ════════════════════════════════════════════════════════════════
    // INSTRUMENT RESOLUTION
    // ════════════════════════════════════════════════════════════════

    /**
     * Resolve FUT instrument — exact monthly expiry match.
     * MF stocks only have monthly futures (no weekly FUT for stocks).
     */
    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        return ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', 'NFO')
            ->whereDate('expiry', $expiry)
            ->where(fn($q) => $q->where('name', $symbol)
                ->orWhere('trading_symbol', 'LIKE', $symbol . '%'))
            ->first();
    }

    /**
     * Resolve nearest upcoming monthly FUT expiry for a stock.
     * All individual stocks have monthly expiry only.
     */
    private function resolveFutExpiry(string $symbol, Carbon $date): ?string
    {
        $expiries = ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', 'NFO')
            ->whereDate('expiry', '>=', $date->toDateString())
            ->where(fn($q) => $q->where('name', $symbol)
                ->orWhere('trading_symbol', 'LIKE', $symbol . '%'))
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()->values()->toArray();

        // If today is expiry day → shift to next month
        if (! empty($expiries) && $expiries[0] === $date->toDateString()) {
            array_shift($expiries);
        }

        return $expiries[0] ?? null;
    }

    /**
     * Dynamic strike interval — min gap between consecutive CE strikes.
     * Same logic as LiveOptionOhlcCollector.
     */
    private function resolveStrikeInterval(string $symbol, string $expiry): ?float
    {
        $key = "{$symbol}_{$expiry}";
        if (isset($this->strikeIntervalCache[$key])) {
            return $this->strikeIntervalCache[$key];
        }

        $strikes = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($s) => (float)$s)
            ->unique()->sort()->values();

        if ($strikes->count() < 2) return null;

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) $minGap = $gap;
        }

        $interval = ($minGap === PHP_INT_MAX || $minGap <= 0) ? null : (float)$minGap;
        if ($interval) $this->strikeIntervalCache[$key] = $interval;
        return $interval;
    }

    /**
     * Pre-warm instrument cache for all CE+PE of a symbol+expiry.
     * Prevents N×2 DB lookups per strike inside the interval loop.
     */
    private function prewarmOptionCache(string $symbol, string $expiry): void
    {
        $instruments = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = "{$symbol}_{$inst->strike}_{$inst->instrument_type}_{$expiry}";
            $this->instrumentCache[$key] = $inst;
        }

        $this->info("   Cached {$instruments->count()} option instruments for {$symbol} [{$expiry}]");
    }

    // ════════════════════════════════════════════════════════════════
    // FETCH + INDEX HELPERS
    // ════════════════════════════════════════════════════════════════
    private function fetchDayCandles(int $token, Carbon $date, int $maxRetries, int $retryDelay): array
    {
        $from = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $to   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                return $this->zerodhaHelper->getHistoricalDataByToken($token, '60minute', $from, $to) ?? [];
            } catch (Exception $e) {
                $isRateLimit = stripos($e->getMessage(), '429') !== false
                    || stripos($e->getMessage(), 'too many') !== false;

                if ($attempt < $maxRetries) {
                    $wait = $isRateLimit ? max($retryDelay, 2) : $retryDelay;
                    $this->warn("      ⏳ Retry {$attempt}/{$maxRetries}: {$e->getMessage()} — wait {$wait}s");
                    sleep($wait);
                    $attempt++;
                } else {
                    Log::error("MfStockFutOpt: fetch failed for token {$token}: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    private function indexByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $c) {
            $map[Carbon::parse($c->date)->format('H:i')] = $c;
        }
        return $map;
    }

    // ════════════════════════════════════════════════════════════════
    // UTILITY
    // ════════════════════════════════════════════════════════════════
    private function buildStrikeList(float $atm, float $interval): array
    {
        $strikes = [];
        for ($i = -self::STRIKES_EACH_SIDE; $i <= self::STRIKES_EACH_SIDE; $i++) {
            $strikes[] = $atm + ($i * $interval);
        }
        return $strikes;
    }

    private function strikePosition(float $strike, float $atm, float $interval): string
    {
        $diff = (int)round(($strike - $atm) / $interval);
        if ($diff === 0) return 'ATM';
        $sign = $diff > 0 ? '+' : '';
        return "ATM{$sign}{$diff}";
    }

    private function buildIntervals(Carbon $date): array
    {
        // 1-hour candles: 09:15, 10:15, 11:15, 12:15, 13:15, 14:15 = 6 candles per day
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(14, 15); // last 1hr candle that opens before close
        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes(60); // ← 1 hour
        }
        return $intervals;
    }

    private function resolveIntervalsToProcess(string $mode, array $allIntervals, Carbon $date): array
    {
        if ($mode === 'historical') return $allIntervals;

        $now        = Carbon::now('Asia/Kolkata');
        // Floor to the last completed 1-hour candle:
        // Candles open at :15 every hour, so floor to nearest hour then subtract 60 min
        $flooredMin = (int)(floor((int)$now->format('i') / 60) * 60);
        // If minutes < 15, we haven't even completed the 09:15 candle yet → use previous hour
        $lastSlot   = $date->copy()->setTime((int)$now->format('H'), 15)->subMinutes(60);

        $marketStart = $date->copy()->setTime(9, 15);
        $marketEnd   = $date->copy()->setTime(14, 15); // last valid 1hr open

        if ($lastSlot->lt($marketStart)) $lastSlot = $marketStart->copy();
        if ($lastSlot->gt($marketEnd))   $lastSlot = $marketEnd->copy();

        return array_values(array_filter($allIntervals, fn($t) => $t->lte($lastSlot)));
    }

    private function buildTradingDays(string $mode): array
    {
        if ($mode === 'live') {
            $today = Carbon::today('Asia/Kolkata');
            if ($today->isWeekend() || $this->isMarketHoliday($today->toDateString())) {
                $this->warn("⏭  Today is weekend/holiday — nothing to collect.");
                return [];
            }
            return [$today];
        }

        // Historical mode
        $fromStr = $this->option('from');
        $toStr   = $this->option('to') ?: Carbon::yesterday('Asia/Kolkata')->toDateString();

        if (! $fromStr) {
            $this->error("❌ --from=YYYY-MM-DD required for historical mode.");
            return [];
        }

        try {
            $from = Carbon::createFromFormat('Y-m-d', $fromStr)->startOfDay();
            $to   = Carbon::createFromFormat('Y-m-d', $toStr)->startOfDay();
        } catch (Exception $e) {
            $this->error("❌ Invalid date format.");
            return [];
        }

        if ($from->gt($to)) {
            $this->error("❌ --from must be ≤ --to.");
            return [];
        }

        $holidays = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')->flip()->toArray();

        $days    = [];
        $current = $from->copy();
        while ($current->lte($to)) {
            if (! $current->isWeekend() && ! isset($holidays[$current->toDateString()])) {
                $days[] = $current->copy();
            }
            $current->addDay();
        }
        return $days;
    }

    // Override: stock options are always NFO, not BFO
    protected function getExchange(string $symbol): string
    {
        return 'NFO'; // All MF stocks → NFO (not BSE)
    }

    private function printHeader(string $mode): void
    {
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->info("  📊 MF Stock FUT + Options OHLC Collector (1-Hour)  |  Mode: " . strtoupper($mode));
        $this->info("  Strategy: BUY 1 FUT + SELL 2 OTM CE (Multi-Asset MF)");
        $this->info("  Candles: 6 per day (09:15, 10:15, 11:15, 12:15, 13:15, 14:15)");
        $this->info("  " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->newLine();
    }
}