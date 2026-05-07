<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\McxOhlcData;
use App\Models\McxSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\McxExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * LiveMcxOhlcCollector
 *
 * Designed to run every 15 minutes via cron:
 *   * /15 9-23 * * 1-6  php artisan mcx:live-collect >> /dev/null 2>&1
 *
 * MCX-specific differences vs options live collector:
 *   - Market: Mon–Sat (not Mon–Fri)
 *   - Hours : 09:00 – 23:30 (not 09:15 – 15:15)
 *   - ATM freeze: 09:00 first candle close (not 09:15)
 *   - FUT expiry ≠ Option expiry (resolved separately — key MCX quirk)
 *   - segment='MCX-OPT' filter to exclude NCO-OPT duplicates
 *   - strike_interval per symbol from mcx_symbols table
 *
 * Behaviour on each run:
 *   1. Gap-fill: find ALL missing intervals from 09:00 today up to the
 *      current completed candle, fetch & store them.
 *   2. Current candle: fetch and store the CURRENT 15-min window.
 *
 * Schedule in Kernel.php:
 *   $schedule->command('mcx:live-collect')
 *       ->everyFifteenMinutes()
 *       ->days([Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY,
 *               Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY])
 *       ->between('9:00', '23:45')
 *       ->appendOutputTo(storage_path('logs/mcx-live-collect.log'));
 */
class LiveMcxOhlcCollector extends Command
{
    use McxExpiryResolver;

    protected $signature = 'mcx:live-collect
                            {--symbol=       : Specific MCX symbol e.g. CRUDEOIL}
                            {--broker=       : Specific broker ID}
                            {--retry=3       : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50      : Batch upsert chunk size}
                            {--force-date=   : Override date (Y-m-d), for testing}
                            {--debug         : Dump instrument cache info}';

    protected $description = 'Live MCX OHLC collector — gap-fill, frozen ATM at 09:00, dual FUT/OPT expiry, MCX-OPT segment filter';

    // MCX trades 09:00 – 23:30, Mon–Sat
    private const MCX_START = '09:00';
    private const MCX_END   = '23:30';

    /** In-memory instrument cache: key = "SYMBOL_STRIKE_TYPE_EXPIRY" */
    private array $instrumentCache = [];

    /** Zerodha helper cache per broker ID */
    private array $helperCache = [];

    // ══════════════════════════════════════════════════════════════════════════
    // Entry point
    // ══════════════════════════════════════════════════════════════════════════

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
        $specBroker = $this->option('broker');

        // ── Skip non-trading days ─────────────────────────────────────────────
        // MCX trades Mon-Sat, so only skip Sunday
        if ($today->isSunday()) {
            $this->warn("⏭  Sunday — MCX closed.");
            return 0;
        }

        if ($this->isMarketHoliday($today->toDateString())) {
            $this->warn("⏭  MCX Holiday — nothing to collect.");
            return 0;
        }

        $this->info("🛢️  Live MCX OHLC Collector — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Dual FUT/OPT Expiry | MCX-OPT Segment Filter | Frozen ATM@09:00 | Gap-Fill | Batch Upsert");
        $this->newLine();

        // ── Load symbols from mcx_symbols table ───────────────────────────────
        $symbolsQuery = McxSymbol::active();
        if ($specSymbol) $symbolsQuery->where('symbol', $specSymbol);
        $mcxSymbols = $symbolsQuery->get()->keyBy('symbol');

        if ($mcxSymbols->isEmpty()) {
            $this->error('❌ No active MCX symbols found in mcx_symbols table!');
            $this->line('   Run: php artisan db:seed --class=McxSymbolSeeder');
            return 1;
        }

        $this->info("   Symbols (" . $mcxSymbols->count() . "): " . $mcxSymbols->keys()->implode(', '));
        $this->newLine();

        // ── Brokers ───────────────────────────────────────────────────────────
        $brokersQuery = BrokerApi::zerodha()->validToken();
        if ($specBroker) $brokersQuery->where('id', $specBroker);
        $brokers = $brokersQuery->get();

        if ($brokers->isEmpty()) {
            $this->error('❌ No active brokers with valid tokens found!');
            return 1;
        }

        // ── Determine which intervals to process ──────────────────────────────
        $allIntervals      = $this->generateMcxIntervals($today, 15);
        $lastCompletedSlot = $this->getLastCompletedSlot($now, $today);
        $toProcess         = array_values(array_filter(
            $allIntervals,
            fn($t) => $t->lte($lastCompletedSlot)
        ));

        if (empty($toProcess)) {
            $this->warn("   ⏳ MCX market hasn't opened yet or no completed candle available.");
            return 0;
        }

        $this->info("   Last completed slot : " . $lastCompletedSlot->format('H:i'));
        $this->info("   Intervals to cover  : 09:00 → " . $lastCompletedSlot->format('H:i')
            . " (" . count($toProcess) . " slots)");
        $this->newLine();

        // ── Run per broker ────────────────────────────────────────────────────
        foreach ($brokers as $broker) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

            $this->helperCache[$broker->id] = new BrokerZerodhaHelper($broker);

            foreach ($mcxSymbols as $baseSymbol => $mcxSymbol) {

                // ── Resolve FUT expiries (1 or 2 during rollover window) ──────
                $futExpiries = $this->resolveMcxFutExpiries($baseSymbol, $today);

                // ── Resolve OPTION expiry (different from FUT expiry in MCX!) ─
                $optionExpiry = $this->getNearestMcxOptionExpiry($baseSymbol, $today);

                $this->info("\n   🛢️  {$baseSymbol} | strike_interval={$mcxSymbol->strike_interval}");
                $this->info("       FUT expir" . (count($futExpiries) > 1 ? 'ies' : 'y') . ': '
                    . (empty($futExpiries) ? 'NONE' : implode(' + ', $futExpiries)));
                $this->info("       OPT expiry : " . ($optionExpiry ?? '❌ none'));

                if (empty($futExpiries)) {
                    $this->warn("       ⚠️  No FUT expiry found — skipping {$baseSymbol}");
                    continue;
                }

                // Pre-warm option cache if we have an option expiry
                if ($optionExpiry) {
                    $cached = $this->prewarmOptionCache($baseSymbol, $optionExpiry);
                    $this->info("       Cached {$cached} option instruments (segment=MCX-OPT)");

                    if ($cached === 0) {
                        $this->warn("       ⚠️  0 options cached for {$baseSymbol} [{$optionExpiry}]");
                        $this->warn("       ⚠️  Run: php artisan mcx:live-collect --debug --symbol={$baseSymbol}");
                    }
                }

                foreach ($futExpiries as $futExpiry) {
                    $futInstrument = $this->resolveMcxFutInstrument($baseSymbol, $futExpiry);

                    if (!$futInstrument) {
                        $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} expiry {$futExpiry} — skipping");
                        continue;
                    }

                    $this->processSymbolExpiry(
                        $broker, $baseSymbol, $mcxSymbol,
                        $futInstrument, $futExpiry, $optionExpiry,
                        $today, $toProcess,
                        $maxRetries, $retryDelay, $chunkSize
                    );
                }
            }
        }

        $this->newLine();
        $this->info("✅ MCX live collection run complete — " . Carbon::now()->format('H:i:s'));
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Process one symbol × one FUT expiry — gap-fill + current candle
    // ══════════════════════════════════════════════════════════════════════════

    private function processSymbolExpiry(
        BrokerApi $broker,
        string $baseSymbol,
        McxSymbol $mcxSymbol,
        ZerodhaInstrument $futInstrument,
        string $futExpiry,
        ?string $optionExpiry,
        Carbon $date,
        array $toProcess,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): void {
        $strikeInterval = (float) $mcxSymbol->strike_interval;

        // ── Find already-stored intervals for THIS specific FUT expiry ────────
        // Scope to trading_symbol to avoid mixing two expiries during rollover
        $storedTimes = McxOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('trading_symbol', $futInstrument->trading_symbol)
            ->where('is_missing', 0)
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->flip()
            ->toArray();

        $missingIntervals = array_values(array_filter(
            $toProcess,
            fn($t) => !isset($storedTimes[$t->format('H:i')])
        ));

        if (empty($missingIntervals)) {
            $lastSlot = end($toProcess);
            $this->info("      ✓ {$baseSymbol} [{$futExpiry}] — up to date ("
                . ($lastSlot ? $lastSlot->format('H:i') : '—') . ")");
            return;
        }

        $missingCount = count($missingIntervals);
        $currentSlot  = end($missingIntervals);
        $gapCount     = $missingCount - 1;

        if ($gapCount > 0) {
            $this->info("      🔄 {$baseSymbol} [{$futExpiry}] — gap-filling {$gapCount} + current ("
                . $currentSlot->format('H:i') . ")");
        } else {
            $this->info("      📥 {$baseSymbol} [{$futExpiry}] — current interval "
                . $currentSlot->format('H:i'));
        }

        // ── Step 1: Fetch FULL DAY FUT candles (09:00 – 23:30) ───────────────
        $allFutCandles = $this->fetchDayCandles(
            $broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($allFutCandles)) {
            $this->error("      ✗ {$baseSymbol} [{$futExpiry}] — FUT fetch failed, skipping");
            return;
        }

        $futCandleMap = $this->indexByTime($allFutCandles);

        // ── Step 2: FREEZE ATM at 09:00 (MCX opens at 09:00) ─────────────────
        $openKey = '09:00';
        if (!isset($futCandleMap[$openKey])) {
            // Fallback: first available candle
            $openKey = array_key_first($futCandleMap);
            $this->warn("      ⚠️  09:00 candle missing — using {$openKey} for ATM freeze");
        }

        $frozenAtm     = round($futCandleMap[$openKey]->close / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      ATM frozen @ {$frozenAtm} (strikes: " . implode(', ', $frozenStrikes) . ")");

        // ── Step 3: Fetch full-day option candles (if we have an option expiry) 
        $optionDayCache = [];
        if ($optionExpiry) {
            $optionDayCache = $this->fetchAllOptionDayCandles(
                $broker, $baseSymbol, $frozenStrikes, $optionExpiry,
                $date, $maxRetries, $retryDelay
            );
        }

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

            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                $this->warn("      ⚠️  {$timeKey} — FUT candle missing (is_missing=1)");
            }

            // FUT row
            $rows[] = $this->buildFutRow(
                $broker->id, $baseSymbol, $futInstrument,
                $futCandle, $frozenAtm, $futExpiry,
                $date, $intervalTime, $now, $isFutMissing
            );

            // CE + PE rows (only if option expiry resolved)
            if (!$optionExpiry) continue;

            foreach (['CE', 'PE'] as $optionType) {
                foreach ($frozenStrikes as $strike) {
                    $cacheKey   = $this->makeCacheKey($baseSymbol, $strike, $optionType, $optionExpiry);
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;
                    if (!$instrument) continue;

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    $rows[] = $this->buildOptionRow(
                        $broker->id, $baseSymbol,
                        $futInstrument->trading_symbol,
                        $lastKnownFutClose,
                        $frozenAtm, $optionType, $strike,
                        $this->strikePosition($strike, $frozenAtm, $strikeInterval),
                        $instrument, $candle, $optionExpiry,
                        $date, $intervalTime, $now, $isMissing
                    );
                }
            }
        }

        // ── Step 5: Batch upsert ──────────────────────────────────────────────
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [{$futExpiry}"
            . ($optionExpiry ? "|OPT:{$optionExpiry}" : '') . "] — {$inserted} rows ({$missingCount} intervals)");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Option candle batch fetch
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionDayCandles(
        BrokerApi $broker,
        string $baseSymbol,
        array $strikes,
        string $optionExpiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $cache = [];

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = $this->makeCacheKey($baseSymbol, $strike, $optionType, $optionExpiry);
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    $this->warn("      ⚠️  Not in cache: {$cacheKey}");
                    continue;
                }

                $token   = $instrument->instrument_token;
                $candles = $this->fetchDayCandles($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($candles)) {
                    $cache[$token] = $this->indexByTime($candles);
                    $this->info("      {$optionType} {$strike}: " . count($candles) . " candles");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data — gaps zero-filled");
                }
            }
        }

        return $cache;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Instrument resolution
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveMcxFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        return ZerodhaInstrument::where('exchange', 'MCX')
            ->where('instrument_type', 'FUT')
            ->where('segment', 'MCX-FUT')
            ->where('name', $symbol)
            ->whereDate('expiry', $expiry)
            ->first();
    }

    /**
     * Pre-warm CE/PE cache for a symbol+expiry.
     * CRITICAL: filters segment='MCX-OPT' to exclude NCO-OPT duplicates.
     * Cache key uses number_format() for float-safe strike matching.
     */
    private function prewarmOptionCache(string $baseSymbol, string $optionExpiry): int
    {
        $instruments = ZerodhaInstrument::where('exchange', 'MCX')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('segment', 'MCX-OPT')
            ->where('name', $baseSymbol)
            ->whereDate('expiry', $optionExpiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = $this->makeCacheKey($baseSymbol, (float) $inst->strike, $inst->instrument_type, $optionExpiry);
            $this->instrumentCache[$key] = $inst;
        }

        return $instruments->count();
    }

    private function makeCacheKey(string $symbol, float $strike, string $type, string $expiry): string
    {
        // number_format avoids float mismatch: 330 vs 330.00 vs 330.0
        return "{$symbol}_" . number_format($strike, 2, '.', '') . "_{$type}_{$expiry}";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Batch upsert
    // ══════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            McxOhlcData::upsert(
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
    // Row builders
    // ══════════════════════════════════════════════════════════════════════════

    private function buildFutRow(
        int $brokerId, string $baseSymbol, ZerodhaInstrument $futInstrument,
        $candle, float $atmStrike, string $expiry,
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'trading_symbol'   => $futInstrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futInstrument->trading_symbol,
            'future_price'     => $candle?->close  ?? 0,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'instrument_token' => $futInstrument->instrument_token,
            'open'             => $candle?->open   ?? 0,
            'high'             => $candle?->high   ?? 0,
            'low'              => $candle?->low    ?? 0,
            'close'            => $candle?->close  ?? 0,
            'volume'           => $candle?->volume ?? 0,
            'oi'               => $candle?->oi     ?? 0,
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
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing
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
            'open'             => $candle?->open   ?? 0,
            'high'             => $candle?->high   ?? 0,
            'low'              => $candle?->low    ?? 0,
            'close'            => $candle?->close  ?? 0,
            'volume'           => $candle?->volume ?? 0,
            'oi'               => $candle?->oi     ?? 0,
            'strike_position'  => $strikePosition,
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Utilities
    // ══════════════════════════════════════════════════════════════════════════

    private function buildStrikeList(float $atm, float $interval): array
    {
        $strikes = [];
        for ($i = -5; $i <= 5; $i++) {
            $strikes[] = round($atm + ($i * $interval), 2);
        }
        return $strikes;
    }

    /**
     * Last completed 15-min slot, clamped to MCX hours (09:00–23:30).
     * e.g. now=09:12 → 09:00 | now=14:47 → 14:45 | now=23:50 → 23:30
     */
    private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    {
        $marketStart = $date->copy()->setTimeFromTimeString(self::MCX_START);
        $marketEnd   = $date->copy()->setTimeFromTimeString(self::MCX_END);

        if ($now->lt($marketStart)) {
            return $marketStart->copy();
        }

        $flooredMin = (int)(floor((int)$now->format('i') / 15) * 15);
        $slot       = $date->copy()->setTime((int)$now->format('H'), $flooredMin, 0);

        return match (true) {
            $slot->lt($marketStart) => $marketStart->copy(),
            $slot->gt($marketEnd)   => $marketEnd->copy(),
            default                 => $slot,
        };
    }

    /**
     * Generate every 15-minute slot from MCX_START to MCX_END.
     */
    private function generateMcxIntervals(Carbon $date, int $minutes): array
    {
        [$sh, $sm] = explode(':', self::MCX_START);
        [$eh, $em] = explode(':', self::MCX_END);

        $current   = $date->copy()->setTime((int)$sh, (int)$sm);
        $end       = $date->copy()->setTime((int)$eh, (int)$em);
        $intervals = [];

        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes($minutes);
        }
        return $intervals;
    }

    /**
     * Fetch full-day candles (09:00 – 23:30) for an instrument token.
     */
    private function fetchDayCandles(
        BrokerApi $broker,
        int $instrumentToken,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $fromTime = $date->copy()->setTime(9, 0)->format('Y-m-d H:i:s');
        $toTime   = $date->copy()->setTime(23, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->helperCache[$broker->id]->getHistoricalDataByToken(
                    $instrumentToken, '15minute', $fromTime, $toTime
                );
                return $data ?? [];
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Fetch {$attempt}/{$maxRetries} failed: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts");
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

    private function strikePosition(float $strike, float $atm, float $interval): string
    {
        if (abs($strike - $atm) < 0.001) return 'ATM';
        $diff = (int) round(($strike - $atm) / $interval);
        return $diff > 0 ? "ATM+{$diff}" : "ATM{$diff}";
    }
}