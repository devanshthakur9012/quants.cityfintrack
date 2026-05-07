<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionDailyOhlcData;
use App\Models\DailyOptionSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * CollectOptionDailyOhlcData  (Historical Backfill — Daily)
 *
 * Collects daily OHLC + OI for FUT and Options (ATM ±5 strikes) — one candle per day.
 * Symbols : loaded from daily_option_symbols table
 * Broker  : hard-coded via BROKER_CLIENT_ID constant (default: OQJ978)
 *
 * ── MATCHES Collect30MinOhlcData patterns exactly ─────────────────────────
 *
 * Strike Interval:
 *   - Dynamically derived from ZerodhaInstrument table (min gap between
 *     consecutive CE strikes for symbol+expiry).
 *   - If strike interval cannot be determined → symbol+expiry is SKIPPED
 *     and logged. No fallback, no guessing.
 *
 * ATM:
 *   - Frozen at the day's FUT open price.
 *   - round(open / strikeInterval) * strikeInterval
 *
 * Expiry handling:
 *   - NIFTY / SENSEX (weekly) : rollover window = 1 trading day before expiry
 *   - Others (monthly)        : rollover window = 5 trading days before expiry
 *   - On expiry day           : CE/PE lookup shifted to next expiry
 */
class CollectOptionDailyOhlcData extends Command
{
    use OptionExpiryResolver;

    // ── Change this to switch the target broker without touching logic ────────
    private const BROKER_CLIENT_ID = 'OQJ978';

    // ─────────────────────────────────────────────────────────────────────────

    protected $signature = 'options:collect-daily-ohlc
                            {--start-date= : Start date (Y-m-d)}
                            {--end-date=   : End date (Y-m-d)}
                            {--date=       : Single date (Y-m-d)}
                            {--symbol=     : Specific symbol (e.g., NIFTY)}
                            {--retry=3     : Number of retries on failure}
                            {--retry-delay=2 : Delay between retries in seconds}
                            {--chunk=50    : Batch insert chunk size}';

    protected $description = 'Backfill daily OHLC + OI for FUT+Options — dynamic strike, smart expiry, frozen ATM (±5 strikes)';

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

        $this->info("🚀 Daily Option OHLC Backfill — Broker: " . self::BROKER_CLIENT_ID);
        $this->info("   Date range : {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        $this->info("   Strike     : Dynamic (derived from ZerodhaInstrument table) | ATM ±5");
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
        $symbolsQuery = DailyOptionSymbol::active();
        if ($specificSymbol) {
            $symbolsQuery->where('symbol', $specificSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols in daily_option_symbols table!');
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

            // Reset per-date strike interval cache
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
                Log::error("CollectOptionDailyOhlcData: date-level error", [
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
        $success = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($symbols as $baseSymbol) {
            $expiries = $this->resolveExpiriesForDaily($baseSymbol, $date);

            $this->info("\n   📊 {$baseSymbol} — expir" . (count($expiries) > 1 ? 'ies' : 'y')
                . ': ' . implode(' + ', $expiries));

            foreach ($expiries as $expiry) {
                $futInstrument = $this->resolveFutInstrument($baseSymbol, $expiry, $date);

                if (!$futInstrument) {
                    $msg = "CollectOptionDailyOhlcData: No FUT instrument for {$baseSymbol} expiry {$expiry} on {$date->toDateString()}";
                    $this->warn("      ⚠️  No FUT instrument — SKIPPED. (see logs)");
                    Log::warning($msg);
                    $skipped++;
                    continue;
                }

                $cepeExpiry = $this->getCePeExpiry($baseSymbol, $expiry, $date);

                // ── Resolve strike interval dynamically — no fallback ─────────
                $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $cepeExpiry);

                if ($strikeInterval === null) {
                    $msg = "CollectOptionDailyOhlcData: Cannot determine strike interval for {$baseSymbol} "
                         . "expiry {$cepeExpiry} on {$date->toDateString()} — SKIPPED. "
                         . "Check ZerodhaInstrument has CE rows for this symbol+expiry.";
                    $this->error("      ✗ {$baseSymbol} [{$expiry}] — strike interval unknown, SKIPPED. (see logs)");
                    Log::error($msg);
                    $skipped++;
                    continue;
                }

                $this->info("      Strike interval for {$baseSymbol} [{$cepeExpiry}]: {$strikeInterval}");

                $this->prewarmInstrumentCache($baseSymbol, $cepeExpiry);

                $result = $this->processSymbolExpiry(
                    $broker, $baseSymbol, $futInstrument,
                    $expiry, $cepeExpiry, $strikeInterval,
                    $date, $maxRetries, $retryDelay, $chunkSize
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
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): array {
        $this->info("      FUT  : {$futInstrument->trading_symbol} (token: {$futInstrument->instrument_token})");
        $this->info("      CE/PE expiry: {$cepeExpiry}" . ($cepeExpiry !== $futExpiry ? ' ← shifted (expiry day)' : ''));

        // ── Step 1: Fetch FUT daily candle ────────────────────────────────────
        $futCandle = $this->fetchDailyCandle($futInstrument->instrument_token, $date, $maxRetries, $retryDelay);

        if (!$futCandle) {
            $msg = "CollectOptionDailyOhlcData: Could not fetch FUT candle for {$baseSymbol} "
                 . "expiry {$futExpiry} on {$date->toDateString()}";
            $this->error("      ✗ Could not fetch FUT data — skipping");
            Log::error($msg);
            return ['success' => 0, 'skipped' => 0, 'failed' => 1];
        }

        $this->info("      FUT daily: O={$futCandle->open} H={$futCandle->high} L={$futCandle->low} C={$futCandle->close}");

        // ── Step 2: Freeze ATM at day open ────────────────────────────────────
        $frozenAtm     = round($futCandle->open / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      ATM frozen = {$frozenAtm} | interval = {$strikeInterval}");
        $this->info("      Strikes: " . implode(', ', $frozenStrikes));

        // ── Step 3: Fetch all option daily candles ────────────────────────────
        $optionCandles = $this->fetchAllOptionDailyCandles(
            $baseSymbol, $frozenStrikes, $cepeExpiry, $date, $maxRetries, $retryDelay
        );

        // ── Step 4: Build rows ────────────────────────────────────────────────
        $rows = [];
        $now  = now()->toDateTimeString();

        // FUT row
        $rows[] = $this->buildFutRow(
            $broker->id, $baseSymbol, $futInstrument,
            $futCandle, $frozenAtm, $futExpiry, $date, $now
        );

        // Option rows (CE + PE, ATM ±5)
        foreach (['CE', 'PE'] as $optionType) {
            foreach ($frozenStrikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$cepeExpiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    Log::warning("CollectOptionDailyOhlcData: Instrument missing from cache — "
                        . "{$cacheKey} on {$date->toDateString()}. Strike may not exist for this expiry.");
                    continue;
                }

                $token     = $instrument->instrument_token;
                $candle    = $optionCandles[$token] ?? null;
                $isMissing = ($candle === null);

                if ($isMissing) {
                    $this->warn("      ⚠️  {$optionType} {$strike} — missing, storing zeros");
                    Log::warning("CollectOptionDailyOhlcData: Option candle missing — "
                        . "{$optionType} {$strike} for {$baseSymbol} [{$cepeExpiry}] on {$date->toDateString()}");
                }

                $rows[] = $this->buildOptionRow(
                    $broker->id, $baseSymbol,
                    $futInstrument->trading_symbol,
                    $futCandle->close,
                    $frozenAtm, $optionType, $strike,
                    $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                    $instrument, $candle, $cepeExpiry,
                    $date, $now, $isMissing
                );
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
     * Derive strike interval from min gap between consecutive CE strikes
     * in ZerodhaInstrument for symbol+expiry.
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
            Log::warning("CollectOptionDailyOhlcData: resolveStrikeInterval — fewer than 2 CE strikes "
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
            Log::warning("CollectOptionDailyOhlcData: resolveStrikeInterval — could not compute valid "
                . "gap for {$symbol} expiry {$expiry}.");
            return null;
        }

        $this->strikeIntervalCache[$cacheKey] = (float) $minGap;
        return (float) $minGap;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Expiry resolution
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveExpiriesForDaily(string $symbol, Carbon $date): array
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
    // Candle fetch
    // ═════════════════════════════════════════════════════════════════════════

    private function fetchDailyCandle(
        int $instrumentToken,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): ?object {
        $from = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $to   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->zerodhaHelper->getHistoricalDataByToken(
                    $instrumentToken, 'day', $from, $to
                );
                return $data[0] ?? null;
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Attempt {$attempt}/{$maxRetries} failed: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts: {$e->getMessage()}");
                    Log::error("CollectOptionDailyOhlcData: Fetch failed for token {$instrumentToken} "
                        . "after {$maxRetries} attempts on {$date->toDateString()}: {$e->getMessage()}");
                    return null;
                }
            }
        }
        return null;
    }

    private function fetchAllOptionDailyCandles(
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
                    Log::warning("CollectOptionDailyOhlcData: fetchAllOptionDailyCandles — "
                        . "instrument missing: {$cacheKey} on {$date->toDateString()}");
                    continue;
                }

                $token  = $instrument->instrument_token;
                $candle = $this->fetchDailyCandle($token, $date, $maxRetries, $retryDelay);

                if ($candle) {
                    $cache[$token] = $candle;
                    $this->info("      {$optionType} {$strike}: daily candle fetched");
                } else {
                    $cache[$token] = null;
                    $this->warn("      {$optionType} {$strike}: no data — zero-filled");
                    Log::warning("CollectOptionDailyOhlcData: No daily candle for "
                        . "{$optionType} {$strike} [{$expiry}] on {$date->toDateString()}");
                }
            }
        }

        return $cache;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Batch upsert
    // ═════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            OptionDailyOhlcData::upsert(
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
        object $candle,
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
            'future_price'     => $candle->close,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'instrument_token' => $futInstrument->instrument_token,
            'open'             => $candle->open,
            'high'             => $candle->high,
            'low'              => $candle->low,
            'close'            => $candle->close,
            'volume'           => $candle->volume ?? 0,
            'oi'               => $candle->oi ?? 0,
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
        float $futurePrice,
        float $atmStrike,
        string $optionType,
        float $strike,
        string $strikePosition,
        ZerodhaInstrument $instrument,
        ?object $candle,
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
            'open'             => $candle->open  ?? 0,
            'high'             => $candle->high  ?? 0,
            'low'              => $candle->low   ?? 0,
            'close'            => $candle->close ?? 0,
            'volume'           => $candle->volume ?? 0,
            'oi'               => $candle->oi ?? 0,
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