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
 * CollectMcxOhlcData — All bugs fixed based on actual DB analysis.
 *
 * ROOT CAUSES FIXED:
 * ══════════════════
 * ❌ BUG 1: "Cached 0 option instruments"
 *    CAUSE : Used FUT expiry (2026-03-30) to look up options, but MCX option
 *            expiry is DIFFERENT (2026-03-23 for ZINC). Zero results every time.
 *    FIX   : resolveMcxFutExpiries() for FUT data
 *            getNearestMcxOptionExpiry() for CE/PE data — separate query!
 *
 * ❌ BUG 2: Wrong strike interval for ZINC
 *    CAUSE : Seeder had strike_interval=5, but DB shows 277.5, 280, 282.5 → step=2.5
 *    FIX   : McxSymbolSeeder updated with correct strike_interval=2.5 for ZINC/LEAD
 *
 * ❌ BUG 3: NCO duplicate instruments polluting cache
 *    CAUSE : zerodha_instruments has both MCX-OPT and NCO-OPT for same ZINC strikes
 *    FIX   : All CE/PE queries now filter segment='MCX-OPT'
 *
 * ❌ BUG 4: Wrong symbol names in seeder
 *    CAUSE : 'NATURALGASM' doesn't exist in DB. DB has 'NATURALGAS' and 'NATGASMINI'
 *    FIX   : Seeder corrected with exact DB name values
 *
 * ❌ BUG 5: OptionExpiryResolver dependency
 *    CAUSE : isMarketHoliday() was coming from OptionExpiryResolver (NSE-only)
 *    FIX   : Fully removed. McxExpiryResolver is 100% self-contained.
 */
class CollectMcxOhlcData extends Command
{
    use McxExpiryResolver;

    protected $signature = 'mcx:collect-ohlc
                            {--start-date=   : Start date (Y-m-d)}
                            {--end-date=     : End date (Y-m-d)}
                            {--date=         : Single date (Y-m-d)}
                            {--symbol=       : Specific MCX symbol e.g. CRUDEOIL}
                            {--broker=       : Specific broker ID}
                            {--retry=3       : Retries on failure}
                            {--retry-delay=2 : Delay between retries (seconds)}
                            {--chunk=50      : Batch insert chunk size}
                            {--interval=15   : Candle interval minutes (1,3,5,15,30,60)}
                            {--debug         : Dump DB rows to diagnose issues}';

    protected $description = 'Collect MCX OHLC+OI for FUT & Options — separate FUT/OPT expiry, MCX-OPT segment filter, dual-expiry';

    private const MCX_START = '09:00';
    private const MCX_END   = '23:30';

    private array $instrumentCache    = [];
    private array $zerodhaHelperCache = [];

    // ══════════════════════════════════════════════════════════════════════════
    // Entry point
    // ══════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        if ($this->option('date')) {
            $startDate = Carbon::parse($this->option('date'));
            $endDate   = $startDate->copy();
        } else {
            $startDate = $this->option('start-date') ? Carbon::parse($this->option('start-date')) : Carbon::today();
            $endDate   = $this->option('end-date')   ? Carbon::parse($this->option('end-date'))   : Carbon::today();
        }

        $specificSymbol  = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $specificBroker  = $this->option('broker');
        $maxRetries      = (int) $this->option('retry');
        $retryDelay      = (int) $this->option('retry-delay');
        $chunkSize       = (int) $this->option('chunk');
        $intervalMinutes = (int) $this->option('interval');
        $candleInterval  = $intervalMinutes . 'minute';

        $this->info("🛢️  MCX OHLC Collector");
        $this->info("   Date     : {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        $this->info("   Interval : {$candleInterval}");
        $this->newLine();

        if ($this->option('debug')) {
            $this->runDebugDump($specificSymbol ?? 'ZINC');
            return 0;
        }

        // ── Load active MCX symbols ───────────────────────────────────────────
        $query = McxSymbol::active();
        if ($specificSymbol) $query->where('symbol', $specificSymbol);
        $mcxSymbols = $query->get()->keyBy('symbol');

        if ($mcxSymbols->isEmpty()) {
            $this->error('❌ No active MCX symbols found!');
            $this->line('   Run: php artisan db:seed --class=McxSymbolSeeder');
            return 1;
        }

        $this->info("   Symbols (" . $mcxSymbols->count() . "): " . $mcxSymbols->keys()->implode(', '));
        $this->newLine();

        // ── Brokers ───────────────────────────────────────────────────────────
        $brokersQuery = BrokerApi::zerodha()->validToken();
        if ($specificBroker) $brokersQuery->where('id', $specificBroker);
        $brokers = $brokersQuery->get();

        if ($brokers->isEmpty()) {
            $this->error('❌ No active brokers found!');
            return 1;
        }

        $totalProcessed = 0;
        $totalFailed    = 0;

        foreach ($brokers as $broker) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");
            $this->zerodhaHelperCache[$broker->id] = new BrokerZerodhaHelper($broker);

            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {

                if ($currentDate->isSunday()) {
                    $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (Sunday)");
                    $currentDate->addDay();
                    continue;
                }

                if ($this->isMarketHoliday($currentDate->toDateString())) {
                    $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (MCX Holiday)");
                    $currentDate->addDay();
                    continue;
                }

                $this->info("\n📅 {$currentDate->format('Y-m-d')}");

                try {
                    $result = $this->processDate(
                        $broker, $currentDate, $mcxSymbols,
                        $maxRetries, $retryDelay, $chunkSize,
                        $candleInterval, $intervalMinutes
                    );
                    $totalProcessed += $result['success'];
                    $totalFailed    += $result['failed'];
                } catch (Exception $e) {
                    $this->error("Date error: " . $e->getMessage());
                    Log::error('CollectMcxOhlcData', [
                        'date'  => $currentDate->format('Y-m-d'),
                        'error' => $e->getMessage(),
                    ]);
                }

                $currentDate->addDay();
            }
        }

        $this->newLine();
        $this->info("✅ Done — Processed: {$totalProcessed} | Failed: {$totalFailed}");
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Process one date
    // ══════════════════════════════════════════════════════════════════════════

    private function processDate(
        BrokerApi $broker,
        Carbon $date,
        \Illuminate\Support\Collection $mcxSymbols,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize,
        string $candleInterval,
        int $intervalMinutes
    ): array {
        $intervals = $this->generateMcxIntervals($date, $intervalMinutes);
        $this->info("   Intervals: " . count($intervals));

        $success = 0;
        $failed  = 0;

        foreach ($mcxSymbols as $baseSymbol => $mcxSymbol) {

            // ── Resolve FUT expiries (may be 1 or 2 during rollover) ──────────
            $futExpiries = $this->resolveMcxFutExpiries($baseSymbol, $date);

            if (empty($futExpiries)) {
                $this->warn("   ⚠️  No FUT expiry for {$baseSymbol} — skipping");
                $failed++;
                continue;
            }

            // ── Resolve OPTION expiry (separate from FUT!) ────────────────────
            // e.g. ZINC FUT=2026-03-30 but ZINC OPT=2026-03-23
            $optionExpiry = $this->getNearestMcxOptionExpiry($baseSymbol, $date);

            $this->info("\n   🛢️  {$baseSymbol} | strike_interval={$mcxSymbol->strike_interval} | unit={$mcxSymbol->unit}");
            $this->info("       FUT expir"   . (count($futExpiries) > 1 ? 'ies' : 'y') . ': ' . implode(' + ', $futExpiries));
            $this->info("       OPT expiry : " . ($optionExpiry ?? '❌ none found'));

            // Pre-warm CE/PE cache using OPTION expiry (not FUT expiry!)
            if ($optionExpiry) {
                $cached = $this->prewarmMcxOptionCache($baseSymbol, $optionExpiry);
                $this->info("       Cached {$cached} option instruments (segment=MCX-OPT)");
            } else {
                $this->warn("       ⚠️  No option expiry found — only FUT rows will be stored");
            }

            foreach ($futExpiries as $futExpiry) {

                $futInstrument = $this->resolveMcxFutInstrument($baseSymbol, $futExpiry);

                if (!$futInstrument) {
                    $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} expiry {$futExpiry}");
                    $failed += count($intervals);
                    continue;
                }

                $this->info("      FUT : {$futInstrument->trading_symbol} | lot_size={$futInstrument->lot_size} | token={$futInstrument->instrument_token}");

                $result = $this->processSymbolExpiry(
                    $broker, $baseSymbol, $mcxSymbol,
                    $futInstrument, $futExpiry, $optionExpiry,
                    $date, $intervals,
                    $maxRetries, $retryDelay, $chunkSize, $candleInterval
                );

                $success += $result['success'];
                $failed  += $result['failed'];
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Process one symbol × one FUT expiry
    // ══════════════════════════════════════════════════════════════════════════

    private function processSymbolExpiry(
        BrokerApi $broker,
        string $baseSymbol,
        McxSymbol $mcxSymbol,
        ZerodhaInstrument $futInstrument,
        string $futExpiry,
        ?string $optionExpiry,
        Carbon $date,
        array $intervals,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize,
        string $candleInterval
    ): array {
        $strikeInterval = $mcxSymbol->strike_interval;

        // ── Step 1: Fetch FUT candles ─────────────────────────────────────────
        $allFutCandles = $this->fetchCandles(
            $broker, $futInstrument->instrument_token,
            $date, $maxRetries, $retryDelay, $candleInterval
        );

        if (empty($allFutCandles)) {
            $this->error("      ✗ No FUT candles for {$futExpiry}");
            return ['success' => 0, 'failed' => count($intervals)];
        }

        $futCandleMap = $this->indexByTime($allFutCandles);
        $this->info("      FUT candles: " . count($allFutCandles));

        // ── Step 2: Freeze ATM at 09:00 open ─────────────────────────────────
        $openKey = self::MCX_START;
        if (!isset($futCandleMap[$openKey])) {
            $openKey = array_key_first($futCandleMap);
            $this->warn("      ⚠️  09:00 missing — using {$openKey} for ATM");
        }

        $frozenAtm     = round($futCandleMap[$openKey]->close / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      ATM @ {$frozenAtm} | strikes: " . implode(', ', $frozenStrikes));

        // ── Step 3: Fetch option candles (using OPTION expiry, not FUT expiry!) 
        $optionDayCache = [];
        if ($optionExpiry) {
            $optionDayCache = $this->fetchAllOptionCandles(
                $broker, $baseSymbol, $frozenStrikes, $optionExpiry,
                $date, $maxRetries, $retryDelay, $candleInterval
            );
        }

        // ── Step 4: Build rows ────────────────────────────────────────────────
        $rows              = [];
        $now               = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($intervals as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;

            if ($futCandle !== null) $lastKnownFutClose = $futCandle->close;

            $isFutMissing = ($futCandle === null);

            $rows[] = $this->buildFutRow(
                $broker->id, $baseSymbol, $futInstrument,
                $futCandle, $frozenAtm, $futExpiry, $date, $intervalTime, $now, $isFutMissing
            );

            // Only add CE/PE rows if we have an option expiry
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

        // ── Step 5: Upsert ────────────────────────────────────────────────────
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [FUT:{$futExpiry}" . ($optionExpiry ? " | OPT:{$optionExpiry}" : '') . "] — {$inserted} rows upserted");

        return ['success' => $inserted, 'failed' => 0];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Instrument resolution
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveMcxFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        return ZerodhaInstrument::where('exchange', 'MCX')
            ->where('instrument_type', 'FUT')
            ->where('segment', 'MCX-FUT')           // ← strict segment filter
            ->where('name', $symbol)
            ->whereDate('expiry', $expiry)
            ->first();
    }

    /**
     * Pre-warm CE/PE option cache.
     * CRITICAL FIXES:
     *  1. Uses OPTION expiry, not FUT expiry
     *  2. Filters segment='MCX-OPT' to exclude NCO duplicates
     *  3. Cache key uses number_format for float consistency
     */
    private function prewarmMcxOptionCache(string $baseSymbol, string $optionExpiry): int
    {
        $instruments = ZerodhaInstrument::where('exchange', 'MCX')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('segment', 'MCX-OPT')           // ← excludes NCO-OPT duplicates
            ->where('name', $baseSymbol)
            ->whereDate('expiry', $optionExpiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = $this->makeCacheKey($baseSymbol, (float)$inst->strike, $inst->instrument_type, $optionExpiry);
            $this->instrumentCache[$key] = $inst;
        }

        return $instruments->count();
    }

    private function makeCacheKey(string $symbol, float $strike, string $type, string $expiry): string
    {
        return "{$symbol}_" . number_format($strike, 2, '.', '') . "_{$type}_{$expiry}";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Option candle fetch
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionCandles(
        BrokerApi $broker,
        string $baseSymbol,
        array $strikes,
        string $optionExpiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay,
        string $candleInterval
    ): array {
        $cache = [];

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = $this->makeCacheKey($baseSymbol, $strike, $optionType, $optionExpiry);
                $instrument = $this->instrumentCache[$cacheKey] ?? null;
                if (!$instrument) continue;

                $token   = $instrument->instrument_token;
                $candles = $this->fetchCandles($broker, $token, $date, $maxRetries, $retryDelay, $candleInterval);

                $cache[$token] = empty($candles) ? [] : $this->indexByTime($candles);

                $msg = empty($candles) ? "no data" : count($candles) . " candles";
                $this->info("      {$optionType} {$strike}: {$msg}");
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
            $strikes[] = round($atm + ($i * $interval), 2); // round to avoid float drift
        }
        return $strikes;
    }

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

    private function fetchCandles(
        BrokerApi $broker, int $token, Carbon $date,
        int $maxRetries, int $retryDelay, string $candleInterval
    ): array {
        [$sh, $sm] = explode(':', self::MCX_START);
        [$eh, $em] = explode(':', self::MCX_END);

        $from    = $date->copy()->setTime((int)$sh, (int)$sm)->format('Y-m-d H:i:s');
        $to      = $date->copy()->setTime((int)$eh, (int)$em)->format('Y-m-d H:i:s');
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $helper = $this->zerodhaHelperCache[$broker->id];
                return $helper->getHistoricalDataByToken($token, $candleInterval, $from, $to) ?? [];
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Retry {$attempt}/{$maxRetries}: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    $this->error("      ✗ Failed after {$maxRetries} attempts");
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

    // ══════════════════════════════════════════════════════════════════════════
    // Debug dump
    // ══════════════════════════════════════════════════════════════════════════

    private function runDebugDump(string $symbol): void
    {
        $this->info("🔍 DEBUG for: {$symbol}");
        $this->newLine();

        $futs = DB::table('zerodha_instruments')
            ->where('exchange', 'MCX')->where('instrument_type', 'FUT')
            ->where('name', $symbol)->orderBy('expiry')->limit(3)
            ->get(['trading_symbol', 'name', 'expiry', 'lot_size', 'segment']);

        $this->info("FUT rows:");
        foreach ($futs as $r) {
            $this->line("  {$r->trading_symbol} | name={$r->name} | expiry={$r->expiry} | segment={$r->segment}");
        }

        $this->newLine();
        $opts = DB::table('zerodha_instruments')
            ->where('name', $symbol)->whereIn('instrument_type', ['CE', 'PE'])
            ->orderBy('expiry')->orderBy('strike')->limit(10)
            ->get(['trading_symbol', 'name', 'strike', 'expiry', 'instrument_type', 'segment']);

        $this->info("CE/PE rows (all segments):");
        foreach ($opts as $r) {
            $this->line("  {$r->instrument_type} {$r->trading_symbol} | strike={$r->strike} | expiry={$r->expiry} | segment={$r->segment}");
        }
    }
}