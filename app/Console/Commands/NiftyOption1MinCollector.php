<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NiftyOption1MinCollector
 *
 * Collects 1-minute OHLCV + OI candles for NIFTY options (CE/PE) and stores
 * them into the `nifty_option_1min_ohlc` table.
 *
 * Designed to run every minute (or on-demand):
 *   * * 9-15 * * 1-5  php artisan nifty:1min-collect >> /dev/null 2>&1
 *
 * Key behaviours:
 *   ✅ 10 CE strikes + 10 PE strikes (ATM-5 … ATM+5) — same as 15min collector
 *   ✅ ATM frozen at 09:15 FUT close — stable throughout the day
 *   ✅ Dynamic strike interval derived from ZerodhaInstrument table
 *   ✅ Gap-fill: all missing 1-min intervals from 09:15 → last completed candle
 *   ✅ Full-day historical fetch via --from-date / --to-date
 *   ✅ Carry-forward future_price across missing FUT candles
 *   ✅ Batch upsert with configurable chunk size
 *   ✅ Weekly expiry support (NIFTY weekly + monthly)
 *   ✅ Zerodha rate-limit safe: 400 ms sleep between requests
 */
class NiftyOption1MinCollector extends Command
{
    use OptionExpiryResolver;

    // ── Pin to broker account ──────────────────────────────────────────────
    private const BROKER_CLIENT_ID = 'OQJ978';

    // ── Symbol handled by this collector ──────────────────────────────────
    private const BASE_SYMBOL = 'NIFTY';

    protected $signature = 'nifty:1min-collect
                            {--retry=3 : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=100 : Batch upsert chunk size}
                            {--force-date= : Override date (Y-m-d) — alias for single-day historical}
                            {--from-date= : Historical start date (Y-m-d)}
                            {--to-date= : Historical end date (Y-m-d), defaults to today if --from-date set}';

    protected $description = 'Collect 1-min OHLC for NIFTY options — 10 CE + 10 PE strikes, frozen ATM, gap-fill, batch upsert';

    private const MARKET_START    = '09:15';
    private const MARKET_END      = '15:29'; // last 1-min candle opens at 15:29

    /** In-memory instrument cache: "{SYMBOL}_{STRIKE}_{TYPE}_{EXPIRY}" → ZerodhaInstrument */
    private array $instrumentCache = [];

    /** In-memory strike interval cache: "{SYMBOL}_{EXPIRY}" → float */
    private array $strikeIntervalCache = [];

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

        // ── Date range resolution ─────────────────────────────────────────────
        $forceDate = $this->option('force-date');
        $fromDate  = $this->option('from-date') ?? $forceDate;
        $toDate    = $this->option('to-date')   ?? ($fromDate ? $fromDate : null);

        $isHistorical = $fromDate !== null;

        if ($isHistorical) {
            $dateFrom = Carbon::parse($fromDate)->startOfDay();
            $dateTo   = Carbon::parse($toDate)->startOfDay();
            if ($dateFrom->gt($dateTo)) {
                $this->error('❌ --from-date must be <= --to-date');
                return 1;
            }
        } else {
            $dateFrom = Carbon::today();
            $dateTo   = Carbon::today();
        }

        $this->info('⚡ NIFTY 1-Min Option OHLC Collector — ' . $now->format('Y-m-d H:i:s'));
        $this->info('   Symbol : ' . self::BASE_SYMBOL . ' | Strikes: ATM±5 (10 CE + 10 PE)');
        $this->info('   Broker : ' . self::BROKER_CLIENT_ID . ' | Mode: ' . ($isHistorical ? 'HISTORICAL' : 'LIVE'));
        if ($isHistorical) {
            $this->info("   Range  : {$dateFrom->toDateString()} → {$dateTo->toDateString()}");
        }
        $this->newLine();

        // ── Load broker ───────────────────────────────────────────────────────
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()
            ->validToken()
            ->first();

        if (! $broker) {
            $this->error('❌ Broker [' . self::BROKER_CLIENT_ID . '] not found or token invalid!');
            return 1;
        }

        $this->info("🔑 Broker : {$broker->client_name} (ID: {$broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);

        // ── Build trading day list ────────────────────────────────────────────
        $tradingDays = [];
        $cursor = $dateFrom->copy();
        while ($cursor->lte($dateTo)) {
            if (! $cursor->isWeekend() && ! $this->isMarketHoliday($cursor->toDateString())) {
                $tradingDays[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        if (empty($tradingDays)) {
            $this->warn('⏭  No trading days in range (weekends / holidays only).');
            return 0;
        }

        $totalDays = count($tradingDays);
        $this->info("   Trading days to process: {$totalDays}");
        $this->newLine();

        // ── Day loop ──────────────────────────────────────────────────────────
        foreach ($tradingDays as $dayIndex => $date) {
            $dayNum = $dayIndex + 1;

            $this->info('════════════════════════════════════════════════════════');
            $this->info("📅 Day {$dayNum}/{$totalDays} — {$date->toDateString()}");
            $this->info('════════════════════════════════════════════════════════');

            // Reset per-day caches
            $this->instrumentCache    = [];
            $this->strikeIntervalCache = [];

            $this->processDay($broker, $date, $isHistorical, $now, $maxRetries, $retryDelay, $chunkSize);

            $this->newLine();
            $this->info("✅ Day {$dayNum}/{$totalDays} complete — {$date->toDateString()}");
            $this->newLine();
        }

        $this->info('🏁 All done — ' . Carbon::now()->format('H:i:s'));
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Day processing
    // ══════════════════════════════════════════════════════════════════════════

    private function processDay(
        BrokerApi $broker,
        Carbon $date,
        bool $isHistorical,
        Carbon $now,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): void {
        $allIntervals = $this->generate1MinIntervals($date);

        if ($isHistorical) {
            $intervalsToProcess = $allIntervals;
        } else {
            $lastCompletedSlot = $this->getLastCompletedSlot1Min($now, $date);
            $intervalsToProcess = array_values(array_filter(
                $allIntervals,
                fn ($t) => $t->lte($lastCompletedSlot)
            ));

            if (empty($intervalsToProcess)) {
                $this->warn('   ⏳ Market not started or no completed 1-min candle yet.');
                return;
            }
            $this->info('   Last completed slot : ' . $lastCompletedSlot->format('H:i'));
        }

        $this->info('   Total 1-min intervals : ' . count($intervalsToProcess)
            . ' (' . $intervalsToProcess[0]->format('H:i') . ' → '
            . end($intervalsToProcess)->format('H:i') . ')');
        $this->newLine();

        // ── Resolve expiries ──────────────────────────────────────────────────
        $expiries = $this->resolveExpiriesFor1Min(self::BASE_SYMBOL, $date);

        $this->info('   Expir' . (count($expiries) > 1 ? 'ies' : 'y') . ': ' . implode(' + ', $expiries));

        foreach ($expiries as $expiry) {
            $cepeExpiry = $this->getCePeExpiry(self::BASE_SYMBOL, $expiry, $date);
            $this->prewarmInstrumentCache(self::BASE_SYMBOL, $cepeExpiry);
        }

        // ── Process each expiry ───────────────────────────────────────────────
        foreach ($expiries as $expiry) {
            $futInstrument = $this->resolveFutInstrument(self::BASE_SYMBOL, $expiry);

            if (! $futInstrument) {
                $this->warn("   ⚠️  No FUT instrument for expiry {$expiry} — skipping");
                Log::warning("NiftyOption1MinCollector: No FUT instrument for " . self::BASE_SYMBOL . " expiry {$expiry}");
                continue;
            }

            $cepeExpiry     = $this->getCePeExpiry(self::BASE_SYMBOL, $expiry, $date);
            $strikeInterval = $this->resolveStrikeInterval(self::BASE_SYMBOL, $cepeExpiry);

            if ($strikeInterval === null) {
                $this->error("   ✗ Cannot determine strike interval for {$cepeExpiry} — SKIPPED");
                Log::error("NiftyOption1MinCollector: Cannot determine strike interval for " . self::BASE_SYMBOL . " expiry {$cepeExpiry}");
                continue;
            }

            $this->info("   Strike interval: {$strikeInterval} | FUT: {$futInstrument->trading_symbol}");

            $this->processExpiry(
                $broker, $futInstrument,
                $expiry, $cepeExpiry, $strikeInterval,
                $date, $intervalsToProcess,
                $maxRetries, $retryDelay, $chunkSize
            );
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Process one expiry
    // ══════════════════════════════════════════════════════════════════════════

    private function processExpiry(
        BrokerApi $broker,
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
        $storedTimes = DB::table('nifty_option_1min_ohlc')
            ->whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->where('trading_symbol', $futInstrument->trading_symbol)
            ->where('is_missing', 0)
            ->pluck('interval_time')
            ->map(fn ($t) => Carbon::parse($t)->format('H:i'))
            ->flip()
            ->toArray();

        $missingIntervals = array_values(array_filter(
            $intervalsToProcess,
            fn ($t) => ! isset($storedTimes[$t->format('H:i')])
        ));

        if (empty($missingIntervals)) {
            $last = end($intervalsToProcess);
            $this->info("   ✓ [{$futExpiry}] — up to date (latest: " . ($last ? $last->format('H:i') : '—') . ')');
            return;
        }

        $missingCount = count($missingIntervals);
        $gapCount     = $missingCount - 1;
        $currentSlot  = end($missingIntervals);

        if ($gapCount > 0) {
            $this->info("   🔄 [{$futExpiry}] — gap-filling {$gapCount} + current ({$currentSlot->format('H:i')})"
                . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ''));
        } else {
            $this->info("   📥 [{$futExpiry}] — fetching {$currentSlot->format('H:i')}"
                . ($cepeExpiry !== $futExpiry ? " | CE/PE → {$cepeExpiry}" : ''));
        }

        // ── Fetch full-day FUT candles ─────────────────────────────────────────
        $allFutCandles = $this->fetchDayCandles(
            $futInstrument->instrument_token, $date, 'minute', $maxRetries, $retryDelay
        );

        if (empty($allFutCandles)) {
            $this->error("   ✗ [{$futExpiry}] — FUT fetch failed, skipping");
            Log::error("NiftyOption1MinCollector: FUT fetch failed for " . self::BASE_SYMBOL . " [{$futExpiry}] on {$date->toDateString()}");
            return;
        }

        $futCandleMap = $this->indexCandlesByTime($allFutCandles);

        // ── Freeze ATM at 09:15 close ─────────────────────────────────────────
        if (! isset($futCandleMap['09:15'])) {
            $this->error("   ✗ [{$futExpiry}] — 09:15 FUT candle missing, cannot freeze ATM");
            Log::error("NiftyOption1MinCollector: 09:15 FUT candle missing for " . self::BASE_SYMBOL . " [{$futExpiry}] on {$date->toDateString()}");
            return;
        }

        $frozenAtm     = round($futCandleMap['09:15']->close / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval); // ATM-5 … ATM+5 (11 strikes)

        $this->info("   FUT 09:15 close = {$futCandleMap['09:15']->close} | ATM frozen = {$frozenAtm}");
        $this->info('   Strikes: ' . implode(', ', $frozenStrikes));

        // ── Fetch all option candles ───────────────────────────────────────────
        $optionDayCache = $this->fetchAllOptionDayCandles(
            $frozenStrikes, $cepeExpiry, $date, $maxRetries, $retryDelay
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

            // FUT row
            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                Log::warning("NiftyOption1MinCollector: FUT candle missing at {$timeKey} for [{$futExpiry}] on {$date->toDateString()}");
            }
            $rows[] = $this->buildFutRow(
                $broker->id, $futInstrument,
                $futCandle, $frozenAtm, $futExpiry,
                $date, $intervalTime, $now, $isFutMissing
            );

            // CE + PE rows (10 CE + 10 PE = ATM-5..ATM-1 + ATM..ATM+5 each)
            foreach (['CE', 'PE'] as $optionType) {
                foreach ($frozenStrikes as $strike) {
                    $cacheKey   = self::BASE_SYMBOL . "_{$strike}_{$optionType}_{$cepeExpiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;

                    if (! $instrument) {
                        Log::warning("NiftyOption1MinCollector: Instrument not in cache — {$cacheKey} at {$timeKey}");
                        continue;
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        Log::warning("NiftyOption1MinCollector: Option candle missing — {$optionType} {$strike} at {$timeKey} [{$cepeExpiry}] on {$date->toDateString()}");
                    }

                    $rows[] = $this->buildOptionRow(
                        $broker->id,
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
        $this->info("   ✅ [{$futExpiry}] — {$inserted} rows upserted ({$missingCount} intervals × "
            . (1 + count($frozenStrikes) * 2) . ' rows each)');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Option candle fetch
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionDayCandles(
        array $strikes,
        string $expiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $cache      = [];
        $fetchCount = 0;

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = self::BASE_SYMBOL . "_{$strike}_{$optionType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (! $instrument) {
                    $this->warn("   ⚠️  Instrument not found: {$cacheKey}");
                    Log::warning("NiftyOption1MinCollector: Instrument missing from cache: {$cacheKey}");
                    continue;
                }

                // Zerodha historical API: ~3 req/sec. 400ms sleep keeps us safe.
                if ($fetchCount > 0) {
                    usleep(400_000);
                }

                $token   = $instrument->instrument_token;
                $candles = $this->fetchDayCandles($token, $date, 'minute', $maxRetries, $retryDelay);
                $fetchCount++;

                if (! empty($candles)) {
                    $cache[$token] = $this->indexCandlesByTime($candles);
                    $this->info("   {$optionType} {$strike}: " . count($candles) . ' candles');
                } else {
                    $cache[$token] = [];
                    $this->warn("   {$optionType} {$strike}: no data — zero-filled");
                    Log::warning("NiftyOption1MinCollector: No data for {$optionType} {$strike} [{$expiry}] on {$date->toDateString()}");
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
            DB::table('nifty_option_1min_ohlc')->upsert(
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
            'base_symbol'      => self::BASE_SYMBOL,
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
            'base_symbol'      => self::BASE_SYMBOL,
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
    // Expiry resolution
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveExpiriesFor1Min(string $symbol, Carbon $date): array
    {
        $expiries = $this->resolveExpiries($symbol, $date);

        // On expiry day — shift to next expiry (expiring contract has no liquidity)
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

            $this->warn('   ⚠️  Today is expiry day — shifted to: ' . ($expiries[0] ?? 'none'));
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
        $isWeekly = ($symbol === 'NIFTY' || $symbol === 'SENSEX');

        $expiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>', $afterDate)
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn ($e) => Carbon::parse($e)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) return null;

        if (! $isWeekly) {
            $byMonth = [];
            foreach ($expiries as $exp) {
                $key         = Carbon::parse($exp)->format('Y-m');
                $byMonth[$key] = $exp;
            }
            $expiries = array_values($byMonth);
        }

        return $expiries[0] ?? null;
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
            ->map(fn ($s) => (float) $s)
            ->unique()
            ->sort()
            ->values();

        if ($strikes->count() < 2) {
            Log::warning("NiftyOption1MinCollector: resolveStrikeInterval — fewer than 2 CE strikes for {$symbol} [{$expiry}]");
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
            Log::warning("NiftyOption1MinCollector: resolveStrikeInterval — invalid gap for {$symbol} [{$expiry}]");
            return null;
        }

        $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
        return (float) $minGap;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Instrument resolution
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        // NIFTY is weekly — use nearest monthly FUT on/after the weekly expiry
        return ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', $this->getExchange($symbol))
            ->where('name', $symbol)
            ->whereDate('expiry', '>=', $expiry)
            ->orderBy('expiry', 'ASC')
            ->first();
    }

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

        $this->info("   Cached {$instruments->count()} option instruments for [{$expiry}]");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Utility helpers
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Build 11 strikes: ATM-5 … ATM … ATM+5.
     * This gives 10 OTM + 1 ATM per side — caller uses all 11 for both CE and PE
     * so the result is effectively 10 CE strikes + 10 PE strikes (ATM counted once).
     *
     * If you need strictly 10 CE + 10 PE (no ATM duplication), remove $atm from
     * one of the option-type loops in processExpiry(). Current approach matches
     * the 15-min collector convention.
     */
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
     * Last completed 1-min slot as of $now, clamped to market hours.
     *
     * We step back 1 min so the currently forming candle is never fetched.
     * e.g. now=09:16:30 → 09:15 | now=09:17:00 → 09:16 | now=15:30:00 → 15:29
     */
    private function getLastCompletedSlot1Min(Carbon $now, Carbon $date): Carbon
    {
        $marketStart = $date->copy()->setTimeFromTimeString(self::MARKET_START);
        $marketEnd   = $date->copy()->setTimeFromTimeString(self::MARKET_END);

        if ($now->lt($marketStart)) {
            return $marketStart->copy();
        }

        // Floor to last completed minute (strip seconds), then subtract 1 min
        $slot = $date->copy()
            ->setTime((int) $now->format('H'), (int) $now->format('i'), 0)
            ->subMinute();

        return match (true) {
            $slot->lt($marketStart) => $marketStart->copy(),
            $slot->gt($marketEnd)   => $marketEnd->copy(),
            default                 => $slot,
        };
    }

    /**
     * Generate all 1-min interval timestamps from 09:15 → 15:29 (375 slots).
     */
    private function generate1MinIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 29);

        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinute();
        }

        return $intervals;
    }

    /**
     * Fetch full-day candles for any interval (1minute / 15minute / etc.)
     */
    private function fetchDayCandles(
        int $instrumentToken,
        Carbon $date,
        string $interval,
        int $maxRetries,
        int $retryDelay
    ): array {
        $fromTime = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $toTime   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->zerodhaHelper->getHistoricalDataByToken(
                    $instrumentToken, $interval, $fromTime, $toTime
                );
                return $data ?? [];
            } catch (Exception $e) {
                $isRateLimit = stripos($e->getMessage(), 'too many')  !== false
                    || stripos($e->getMessage(), 'rate limit') !== false
                    || stripos($e->getMessage(), '429')        !== false;

                if ($attempt < $maxRetries) {
                    $waitSec = $isRateLimit ? max($retryDelay, 2) : $retryDelay;
                    $this->warn("   ⏳ Attempt {$attempt}/{$maxRetries} failed"
                        . ($isRateLimit ? ' [rate limited]' : '')
                        . ": {$e->getMessage()} — waiting {$waitSec}s");
                    sleep($waitSec);
                    $attempt++;
                } else {
                    $this->error("   ✗ Fetch failed after {$maxRetries} attempts: {$e->getMessage()}");
                    Log::error("NiftyOption1MinCollector: Fetch failed for token {$instrumentToken}: {$e->getMessage()}");
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
        return match ($diff) {
            0       => 'ATM',
            1       => 'ATM+1',
            -1      => 'ATM-1',
            2       => 'ATM+2',
            -2      => 'ATM-2',
            3       => 'ATM+3',
            -3      => 'ATM-3',
            4       => 'ATM+4',
            -4      => 'ATM-4',
            5       => 'ATM+5',
            -5      => 'ATM-5',
            default => 'N/A',
        };
    }
}