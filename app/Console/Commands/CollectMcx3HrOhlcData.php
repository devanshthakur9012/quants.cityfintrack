<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\Mcx3HrOhlcData;
use App\Models\Mcx3HrOhlcSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\McxExpiryResolver;       // ← same trait used in CollectMcxOhlcData
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * CollectMcx3HrOhlcData — Historical backfill for MCX 3-Hr candles
 *
 * ROOT CAUSE FIXES (ported from CollectMcxOhlcData):
 * ════════════════════════════════════════════════════
 * ✅ FIX 1: Separate FUT vs OPT expiry
 *    MCX FUT expiry (e.g. 2026-03-30) ≠ MCX option expiry (e.g. 2026-03-23)
 *    → FUT data uses resolveMcxFutExpiries()
 *    → CE/PE data uses getNearestMcxOptionExpiry() — separate query!
 *
 * ✅ FIX 2: segment='MCX-OPT' filter
 *    zerodha_instruments has BOTH MCX-OPT and NCO-OPT for same strikes.
 *    Using wrong segment = wrong/duplicate instruments = zero cache hits.
 *    → All CE/PE queries now filter segment='MCX-OPT'
 *
 * ✅ FIX 3: float-safe cache key
 *    number_format($strike, 2) prevents "5950" vs "5950.0" mismatches
 *
 * Symbols  : CRUDEOIL, CRUDEOILM, NATGAS  (from mcx_3hr_ohlc_symbols table)
 * Broker   : hard-coded via BROKER_CLIENT_ID (default: DB0542)
 * Slots    : 09:00 | 12:00 | 15:00 | 18:00 | 21:00  (full MCX day, 09:00–23:30)
 *
 * Usage:
 *   php artisan mcx:collect-3hr-ohlc --date=2026-03-03
 *   php artisan mcx:collect-3hr-ohlc --start-date=2026-01-01 --end-date=2026-03-03
 *   php artisan mcx:collect-3hr-ohlc --date=2026-03-03 --symbol=CRUDEOIL
 *   php artisan mcx:collect-3hr-ohlc --date=2026-03-03 --debug   (diagnose instrument issues)
 */
class CollectMcx3HrOhlcData extends Command
{
    use McxExpiryResolver;

    private const BROKER_CLIENT_ID = 'OQJ978';
    private const SLOTS            = ['09:00', '12:00', '15:00', '18:00', '21:00'];
    private const MCX_START        = '09:00';
    private const MCX_END          = '23:30';   // MCX trades until 23:30 IST

    protected $signature = 'mcx:collect-3hr-ohlc
                            {--date=        : Single date (Y-m-d)}
                            {--start-date=  : Range start (Y-m-d)}
                            {--end-date=    : Range end (Y-m-d)}
                            {--symbol=      : Specific symbol e.g. CRUDEOIL}
                            {--retry=3      : API retries per call}
                            {--retry-delay=2: Seconds between retries}
                            {--chunk=50     : DB upsert chunk size}
                            {--debug        : Dump instrument rows to diagnose issues}';

    protected $description = 'Historical 3-Hr OHLC for MCX options — separate FUT/OPT expiry, MCX-OPT segment, frozen ATM';

    private array $instrumentCache = [];
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Entry point
    // ═════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;

        // ── Debug mode ────────────────────────────────────────────────────────
        if ($this->option('debug')) {
            $this->runDebugDump($specSymbol ?? 'CRUDEOIL');
            return 0;
        }

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
                : $startDate->copy();
        }

        $maxRetries = (int)$this->option('retry');
        $retryDelay = (int)$this->option('retry-delay');
        $chunkSize  = (int)$this->option('chunk');

        $this->info("╔══════════════════════════════════════════════════════╗");
        $this->info("║    MCX 3-Hr OHLC Historical Collector (Fixed)        ║");
        $this->info("╚══════════════════════════════════════════════════════╝");
        $this->info("  Range  : {$startDate->toDateString()} → {$endDate->toDateString()}");
        $this->info("  Broker : " . self::BROKER_CLIENT_ID);
        $this->info("  Slots  : 09:00 | 12:00 | 15:00 | 18:00 | 21:00  (full MCX day 09:00–23:30)");
        $this->info("  Fixes  : separate FUT/OPT expiry | segment=MCX-OPT | float-safe cache");
        $this->info('');

        // ── Load broker ───────────────────────────────────────────────────────
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()->validToken()->first();

        if (!$broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid!");
            return 1;
        }

        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);
        $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

        // ── Load symbols ──────────────────────────────────────────────────────
        $symbolsQuery = Mcx3HrOhlcSymbol::active();
        if ($specSymbol) $symbolsQuery->where('symbol', $specSymbol);
        $symbolRows = $symbolsQuery->get()->keyBy('symbol');

        if ($symbolRows->isEmpty()) {
            $this->error('❌ No active symbols in mcx_3hr_ohlc_symbols!');
            return 1;
        }

        $this->info("  Symbols: " . $symbolRows->keys()->join(', '));
        $this->info('');

        // ── Date loop ─────────────────────────────────────────────────────────
        $current = $startDate->copy();
        while ($current->lte($endDate)) {

            if ($current->isWeekend()) {
                $this->line("  ⏭  {$current->toDateString()} — weekend, skip");
                $current->addDay();
                continue;
            }

            if ($this->isMarketHoliday($current->toDateString())) {
                $this->line("  ⏭  {$current->toDateString()} — MCX holiday, skip");
                $current->addDay();
                continue;
            }

            $this->info("── {$current->toDateString()} ──────────────────────────────────");

            foreach ($symbolRows as $symbol => $symRow) {
                $this->instrumentCache = []; // reset per symbol per date
                $this->processSymbolDate($broker, $symbol, $symRow, $current, $maxRetries, $retryDelay, $chunkSize);
            }

            $current->addDay();
        }

        $this->info('');
        $this->info("✅ MCX 3-Hr historical collection complete.");
        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Process one symbol × one date
    // ═════════════════════════════════════════════════════════════════════════

    private function processSymbolDate(
        BrokerApi        $broker,
        string           $symbol,
        Mcx3HrOhlcSymbol $symRow,
        Carbon           $date,
        int              $maxRetries,
        int              $retryDelay,
        int              $chunkSize
    ): void {
        $strikeInterval = (float)$symRow->strike_interval;

        // ── FIX 1: Resolve FUT expiry and OPT expiry SEPARATELY ──────────────
        $futExpiries = $this->resolveMcxFutExpiries($symbol, $date);

        if (empty($futExpiries)) {
            $this->warn("  [{$symbol}] No FUT expiry found — skipping");
            return;
        }

        // Option expiry is DIFFERENT from FUT expiry for MCX
        $optionExpiry = $this->getNearestMcxOptionExpiry($symbol, $date);

        $this->info("  [{$symbol}]");
        $this->info("    FUT expir" . (count($futExpiries) > 1 ? 'ies' : 'y') . ": " . implode(' + ', $futExpiries));
        $this->info("    OPT expiry: " . ($optionExpiry ?? '❌ none — only FUT rows will be stored'));

        // ── FIX 2+3: Pre-warm CE/PE cache using OPT expiry + MCX-OPT segment ─
        if ($optionExpiry) {
            $cached = $this->prewarmOptionCache($symbol, $optionExpiry);
            $this->info("    Cached {$cached} option instruments (segment=MCX-OPT, expiry={$optionExpiry})");

            if ($cached === 0) {
                $this->warn("    ⚠️  Still 0 instruments after fix — run --debug to inspect DB");
            }
        }

        // ── Process each FUT expiry ───────────────────────────────────────────
        foreach ($futExpiries as $futExpiry) {

            $futInstrument = $this->resolveFutInstrument($symbol, $futExpiry);

            if (!$futInstrument) {
                $this->warn("    No FUT instrument for expiry {$futExpiry} — skip");
                continue;
            }

            $this->info("    FUT: {$futInstrument->trading_symbol} | token={$futInstrument->instrument_token}");

            // ── Fetch full-day 60-min FUT candles ─────────────────────────────
            $fut60 = $this->fetch60Min($broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay);

            if (empty($fut60)) {
                $this->error("    ✗ No FUT candle data — skipping");
                continue;
            }

            // ── Aggregate 60-min → 3-Hr ───────────────────────────────────────
            $futCandleMap = $this->aggregate60to3Hr($fut60);
            $this->info("    FUT: " . count($fut60) . " × 60-min → " . count($futCandleMap) . " × 3-Hr");

            // ── Freeze ATM at 09:00 FUT close ─────────────────────────────────
            $byTime     = $this->indexByTime($fut60);
            $openClose  = $byTime['09:00']->close ?? ($fut60[0]->close ?? 0);
            $frozenAtm  = round($openClose / $strikeInterval) * $strikeInterval;
            $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

            $this->info("    ATM frozen at {$frozenAtm} | interval={$strikeInterval}");

            // ── Fetch all option 60-min candles → aggregate to 3-Hr ───────────
            $optionCache = [];
            if ($optionExpiry) {
                $optionCache = $this->fetchAllOptionCandles(
                    $broker, $symbol, $frozenStrikes, $optionExpiry,
                    $date, $maxRetries, $retryDelay
                );
            }

            // ── Build rows for all 3 slots ────────────────────────────────────
            $rows   = [];
            $now    = now()->toDateTimeString();
            $lastFutClose = null;

            foreach (self::SLOTS as $slotStr) {
                $intervalTime = Carbon::parse($date->toDateString() . ' ' . $slotStr);
                $futCandle    = $futCandleMap[$slotStr] ?? null;
                $isFutMissing = ($futCandle === null);

                if ($futCandle) $lastFutClose = $futCandle->close;

                // FUT row
                $rows[] = $this->buildFutRow(
                    $broker->id, $symbol, $futInstrument,
                    $futCandle, $frozenAtm, $futExpiry,
                    $date, $intervalTime, $now, $isFutMissing
                );

                if (!$optionExpiry) continue;

                // CE + PE rows
                foreach (['CE', 'PE'] as $type) {
                    foreach ($frozenStrikes as $strike) {
                        $cacheKey   = $this->makeCacheKey($symbol, $strike, $type, $optionExpiry);
                        $instrument = $this->instrumentCache[$cacheKey] ?? null;
                        if (!$instrument) continue;

                        $token     = $instrument->instrument_token;
                        $candle    = $optionCache[$token][$slotStr] ?? null;

                        $rows[] = $this->buildOptionRow(
                            $broker->id, $symbol,
                            $futInstrument->trading_symbol,
                            $lastFutClose,
                            $frozenAtm, $type, $strike,
                            $this->strikePosition($strike, $frozenAtm, $strikeInterval),
                            $instrument, $candle, $optionExpiry,
                            $date, $intervalTime, $now, $candle === null
                        );
                    }
                }
            }

            // ── Batch upsert ──────────────────────────────────────────────────
            $total = $this->batchUpsert($rows, $chunkSize);
            $this->info("    ✅ [{$symbol}] FUT:{$futExpiry}" . ($optionExpiry ? " | OPT:{$optionExpiry}" : '') . " → {$total} rows upserted");
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Instrument resolution — FIXED
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        return ZerodhaInstrument::where('exchange', 'MCX')
            ->where('segment', 'MCX-FUT')               // ← strict segment
            ->where('instrument_type', 'FUT')
            ->where('name', $symbol)
            ->whereDate('expiry', $expiry)
            ->first();
    }

    /**
     * Pre-warm CE/PE cache.
     * KEY FIXES:
     *   1. Uses optionExpiry (NOT futExpiry)
     *   2. segment='MCX-OPT' excludes NCO-OPT duplicates
     *   3. number_format for float-safe cache key
     */
    private function prewarmOptionCache(string $symbol, string $optionExpiry): int
    {
        $instruments = ZerodhaInstrument::where('exchange', 'MCX')
            ->where('segment', 'MCX-OPT')               // ← FIX: excludes NCO duplicates
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('name', $symbol)
            ->whereDate('expiry', $optionExpiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = $this->makeCacheKey($symbol, (float)$inst->strike, $inst->instrument_type, $optionExpiry);
            $this->instrumentCache[$key] = $inst;
        }

        return $instruments->count();
    }

    /**
     * Float-safe cache key — prevents "5950" vs "5950.0" mismatches
     */
    private function makeCacheKey(string $symbol, float $strike, string $type, string $expiry): string
    {
        return "{$symbol}_" . number_format($strike, 2, '.', '') . "_{$type}_{$expiry}";
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 60-min → 3-Hr aggregation
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Groups 60-min candles into 3-Hr bars:
     *   09:00 slot = 09:00 + 10:00 + 11:00
     *   12:00 slot = 12:00 + 13:00 + 14:00
     *   15:00 slot = 15:00 + 16:00 + ... + 23:00
     */
    private function aggregate60to3Hr(array $candles60): array
    {
        $byTime = $this->indexByTime($candles60);
        ksort($byTime);

        $slotMap = [];
        foreach ($byTime as $timeStr => $candle) {
            $hour = (int)explode(':', $timeStr)[0];
            $slot = match (true) {
                $hour >= 9  && $hour < 12 => '09:00',
                $hour >= 12 && $hour < 15 => '12:00',
                $hour >= 15 && $hour < 18 => '15:00',
                $hour >= 18 && $hour < 21 => '18:00',
                $hour >= 21               => '21:00',
                default                   => null,
            };
            if ($slot) $slotMap[$slot][] = $candle;
        }

        $result = [];
        foreach ($slotMap as $slot => $candles) {
            $bar         = new \stdClass();
            $bar->open   = $candles[0]->open;
            $bar->high   = max(array_map(fn($c) => $c->high,   $candles));
            $bar->low    = min(array_map(fn($c) => $c->low,    $candles));
            $bar->close  = end($candles)->close;
            $bar->volume = array_sum(array_map(fn($c) => $c->volume ?? 0, $candles));
            $bar->oi     = end($candles)->oi ?? 0;
            $result[$slot] = $bar;
        }
        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Option candle fetch
    // ═════════════════════════════════════════════════════════════════════════

    private function fetchAllOptionCandles(
        BrokerApi $broker, string $symbol, array $strikes,
        string $optionExpiry, Carbon $date, int $maxRetries, int $retryDelay
    ): array {
        $cache = [];

        foreach (['CE', 'PE'] as $type) {
            foreach ($strikes as $strike) {
                $cacheKey   = $this->makeCacheKey($symbol, $strike, $type, $optionExpiry);
                $instrument = $this->instrumentCache[$cacheKey] ?? null;
                if (!$instrument) continue;

                $token  = $instrument->instrument_token;
                $c60    = $this->fetch60Min($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($c60)) {
                    $cache[$token] = $this->aggregate60to3Hr($c60);
                    $this->info("      {$type} {$strike}: " . count($c60) . " × 60-min → " . count($cache[$token]) . " × 3-Hr");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$type} {$strike}: no data — zero-filled");
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
            Mcx3HrOhlcData::upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                ['open','high','low','close','volume','oi','future_price','atm_strike',
                 'strike_position','expiry_date','is_missing','updated_at']
            );
            $total += count($chunk);
        }
        return $total;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Row builders
    // ═════════════════════════════════════════════════════════════════════════

    private function buildFutRow(
        int $brokerId, string $symbol, ZerodhaInstrument $futInst,
        $candle, float $atmStrike, string $expiry,
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'base_symbol'      => $symbol,
            'future_symbol'    => $futInst->trading_symbol,
            'future_price'     => $candle?->close  ?? 0,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'trading_symbol'   => $futInst->trading_symbol,
            'instrument_token' => $futInst->instrument_token,
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
        int $brokerId, string $symbol, string $futSymbol, ?float $futPrice,
        float $atmStrike, string $type, float $strike, string $strikePos,
        ZerodhaInstrument $inst, $candle, string $expiry,
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'base_symbol'      => $symbol,
            'future_symbol'    => $futSymbol,
            'future_price'     => $futPrice,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => $type,
            'strike'           => $strike,
            'trading_symbol'   => $inst->trading_symbol,
            'instrument_token' => $inst->instrument_token,
            'open'             => $candle?->open   ?? 0,
            'high'             => $candle?->high   ?? 0,
            'low'              => $candle?->low    ?? 0,
            'close'            => $candle?->close  ?? 0,
            'volume'           => $candle?->volume ?? 0,
            'oi'               => $candle?->oi     ?? 0,
            'strike_position'  => $strikePos,
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Utilities
    // ═════════════════════════════════════════════════════════════════════════

    private function fetch60Min(BrokerApi $broker, int $token, Carbon $date, int $maxRetries, int $retryDelay): array
    {
        $from = $date->copy()->setTime(9, 0)->format('Y-m-d H:i:s');
        $to   = $date->copy()->setTime(23, 30)->format('Y-m-d H:i:s');  // Full MCX session end

        for ($i = 1; $i <= $maxRetries; $i++) {
            try {
                return $this->zerodhaHelper->getHistoricalDataByToken($token, '60minute', $from, $to) ?? [];
            } catch (Exception $e) {
                if ($i < $maxRetries) { sleep($retryDelay); continue; }
                $this->error("    ✗ Fetch failed token={$token}: " . $e->getMessage());
                return [];
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

    private function buildStrikeList(float $atm, float $interval): array
    {
        $strikes = [];
        for ($i = -5; $i <= 5; $i++) {
            $strikes[] = round($atm + ($i * $interval), 2);
        }
        return $strikes;
    }

    private function strikePosition(float $strike, float $atm, float $interval): string
    {
        if (abs($strike - $atm) < 0.001) return 'ATM';
        $diff = (int) round(($strike - $atm) / $interval);
        return $diff > 0 ? "ATM+{$diff}" : "ATM{$diff}";
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Debug dump — diagnose instrument issues
    // ═════════════════════════════════════════════════════════════════════════

    private function runDebugDump(string $symbol): void
    {
        $this->info("🔍 DEBUG instrument check for: {$symbol}");
        $this->newLine();

        $futs = DB::table('zerodha_instruments')
            ->where('exchange', 'MCX')
            ->where('instrument_type', 'FUT')
            ->where('name', $symbol)
            ->orderBy('expiry')
            ->limit(3)
            ->get(['trading_symbol', 'name', 'expiry', 'lot_size', 'segment']);

        $this->info("FUT rows:");
        foreach ($futs as $r) {
            $this->line("  {$r->trading_symbol} | expiry={$r->expiry} | segment={$r->segment}");
        }

        $this->newLine();

        $opts = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->orderBy('expiry')->orderBy('strike')
            ->limit(10)
            ->get(['trading_symbol', 'name', 'strike', 'expiry', 'instrument_type', 'segment']);

        $this->info("CE/PE rows (all segments):");
        foreach ($opts as $r) {
            $this->line("  {$r->instrument_type} {$r->trading_symbol} | strike={$r->strike} | expiry={$r->expiry} | segment={$r->segment}");
        }

        $this->newLine();
        $mcxOptCount = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('segment', 'MCX-OPT')
            ->count();
        $this->info("MCX-OPT segment count for {$symbol}: {$mcxOptCount}");
        $this->info("If this is 0, your instruments table needs MCX options synced from Zerodha.");
    }
}