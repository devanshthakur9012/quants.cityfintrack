<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ZerodhaInstrument;
use App\Helpers\BrokerZerodhaHelper;
use App\Models\BrokerApi;
use Carbon\Carbon;
use Exception;

/**
 * RawDataCollector
 *
 * Collects 15-min OHLC for spot, futures, options.
 * Stores in: raw_ohlc_spot / raw_ohlc_futures / raw_ohlc_options
 *
 * Spot token is resolved LIVE from zerodha_instruments table
 * (no spot_instrument_token column needed in raw_symbols).
 *
 * Cron:
 *   * /15 9-15 * * 1-5  php artisan raw:collect >> /dev/null 2>&1
 *
 * Usage:
 *   php artisan raw:collect
 *   php artisan raw:collect --symbol=AUROPHARMA
 *   php artisan raw:collect --from=2026-01-01
 *   php artisan raw:collect --from=2026-01-01 --to=2026-04-13
 */
class RawDataCollector extends Command
{
    private const BROKER_CLIENT_ID = 'DB0542';
    private const MARKET_OPEN      = '09:15';
    private const MARKET_CLOSE     = '15:15';
    private const FETCH_DELAY_MS   = 350_000; // 350ms between API calls (Zerodha: 3 req/sec max)
    private const CHUNK_SIZE       = 200;

    protected $signature = 'raw:collect
                            {--symbol= : Single symbol only}
                            {--from=   : Historical start date Y-m-d}
                            {--to=     : Historical end date Y-m-d (defaults to --from)}
                            {--retry=3 : API retries per instrument}';

    protected $description = 'Collect 15-min OHLC (spot/futures/options) into raw tables';

    private BrokerZerodhaHelper $zerodha;
    private int $maxRetries;
    private array $strikeIntervalCache = [];
    private array $instrumentCache     = [];

    // ═════════════════════════════════════════════════════════════════════════
    // ENTRY
    // ═════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $this->maxRetries = (int) $this->option('retry');

        // ── Broker auth ───────────────────────────────────────────────────────
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()->validToken()->first();

        if (!$broker) {
            $this->error('❌ Broker [' . self::BROKER_CLIENT_ID . '] not found or token expired.');
            return 1;
        }

        $this->zerodha = new BrokerZerodhaHelper($broker);
        $this->info('🔑 Broker: ' . $broker->client_name);

        // ── Date range ────────────────────────────────────────────────────────
        $fromOpt    = $this->option('from');
        $toOpt      = $this->option('to');
        $historical = $fromOpt !== null;

        if ($historical) {
            $dateFrom = Carbon::parse($fromOpt)->startOfDay();
            $dateTo   = Carbon::parse($toOpt ?? $fromOpt)->startOfDay();
            if ($dateFrom->gt($dateTo)) {
                $this->error('❌ --from must be <= --to');
                return 1;
            }
        } else {
            $dateFrom = $dateTo = Carbon::today();
        }

        // ── Trading days ──────────────────────────────────────────────────────
        $tradingDays = $this->buildTradingDays($dateFrom, $dateTo);

        if (empty($tradingDays)) {
            $this->warn('⏭  No trading days in range.');
            return 0;
        }

        // ── Active symbols ────────────────────────────────────────────────────
        $query = DB::table('raw_symbols')->where('status', 1);
        if ($sym = $this->option('symbol')) {
            $query->where('symbol', strtoupper($sym));
        }
        $symbols = $query->get();

        if ($symbols->isEmpty()) {
            $this->error('❌ No active symbols. Run: php artisan db:seed --class=RawSymbolSeeder');
            return 1;
        }

        $this->info('📋 Symbols : ' . $symbols->pluck('symbol')->implode(', '));
        $this->info('📅 Days    : ' . count($tradingDays)
            . ($historical ? " ({$dateFrom->toDateString()} → {$dateTo->toDateString()})" : ' (today)'));
        $this->newLine();

        // ═════════════════════════════════════════════════════════════════════
        // DATE LOOP
        // ═════════════════════════════════════════════════════════════════════
        foreach ($tradingDays as $date) {
            $this->info('════════════════════════════════════════');
            $this->info("📅 {$date->toDateString()}");
            $this->info('════════════════════════════════════════');

            $this->strikeIntervalCache = [];
            $this->instrumentCache     = [];

            $slots = $historical ? $this->allSlots($date) : $this->completedSlots($date);

            if (empty($slots)) {
                $this->warn('   ⏳ No completed candle yet.');
                continue;
            }

            $this->info('   Slots: ' . reset($slots)->format('H:i')
                . ' → ' . end($slots)->format('H:i')
                . ' (' . count($slots) . ')');
            $this->newLine();

            foreach ($symbols as $sym) {
                $this->processSymbol($sym, $date, $slots);
            }
        }

        $this->newLine();
        $this->info('🏁 Done — ' . now()->format('H:i:s'));
        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PER-SYMBOL  (order: spot → futures → options)
    // ═════════════════════════════════════════════════════════════════════════

    private function processSymbol(object $sym, Carbon $date, array $slots): void
    {
        $this->info("  ▶ {$sym->symbol}");

        // ── Spot token resolved live from zerodha_instruments ─────────────────
        $spotToken = $this->resolveSpotToken($sym->symbol, $sym->exchange);

        if ($spotToken) {
            $this->collectSpot($sym->symbol, $sym->exchange, $spotToken, $date, $slots);
        } else {
            $this->warn("    ⚠️  SPOT token not found for {$sym->symbol} in zerodha_instruments — skipping spot");
        }

        $this->collectFutures($sym->symbol, $date, $slots);
        $this->collectOptions($sym->symbol, $date, $slots, (int)$sym->strikes_depth);

        $this->newLine();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RESOLVE SPOT TOKEN — live from zerodha_instruments
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Fetch the EQ instrument token for a symbol from zerodha_instruments.
     *
     * For indices (NIFTY, BANKNIFTY, SENSEX, FINNIFTY) Zerodha uses
     * instrument_type = 'EQ' with segment = 'INDICES' in the instrument file.
     * We try EQ first, then fall back to INDICES segment.
     */
    private function resolveSpotToken(string $symbol, string $exchange): ?int
    {
        // Indices — Zerodha DB name differs from our symbol name (confirmed from zerodha_instruments)
        //   NIFTY       → name = "NIFTY 50"          token = 256265
        //   BANKNIFTY   → name = "NIFTY BANK"        token = 260105
        //   FINNIFTY    → name = "NIFTY FIN SERVICE" token = 257801
        //   SENSEX      → name = "SENSEX"             token = 265
        $indexNameMap = [
            'NIFTY'     => 'NIFTY 50',
            'BANKNIFTY' => 'NIFTY BANK',
            'FINNIFTY'  => 'NIFTY FIN SERVICE',
            'SENSEX'    => 'SENSEX',
        ];

        if (isset($indexNameMap[$symbol])) {
            $token = ZerodhaInstrument::where('name', $indexNameMap[$symbol])
                ->where('exchange', $exchange)
                ->where('instrument_type', 'EQ')
                ->value('instrument_token');
            return $token ? (int) $token : null;
        }

        // Stocks — try name = symbol with EQ first (works for most stocks)
        $token = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->where('instrument_type', 'EQ')
            ->value('instrument_token');

        if ($token) return (int) $token;

        // Fallback — try trading_symbol match (some stocks differ)
        $token = ZerodhaInstrument::where('trading_symbol', $symbol)
            ->where('exchange', $exchange)
            ->value('instrument_token');

        return $token ? (int) $token : null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SPOT COLLECTOR
    // ═════════════════════════════════════════════════════════════════════════

    private function collectSpot(
        string $symbol,
        string $exchange,
        int $token,
        Carbon $date,
        array $slots
    ): void {
        $missing = $this->missingSlots($slots, $this->storedSpotTimes($symbol, $date));

        if (empty($missing)) {
            $this->info("    ✓ SPOT {$symbol} — up to date");
            return;
        }

        $this->info("    📥 SPOT {$symbol} — " . count($missing) . " candles");

        $candles = $this->fetchCandles($token, $date);

        if (empty($candles)) {
            $this->error("    ✗ SPOT {$symbol} — no data");
            return;
        }

        $map  = $this->indexByTime($candles);
        $rows = [];
        $now  = now()->toDateTimeString();

        foreach ($missing as $slot) {
            $candle = $map[$slot->format('H:i')] ?? null;
            if (!$candle) continue;

            $rows[] = [
                'symbol'           => $symbol,
                'exchange'         => $exchange,
                'instrument_token' => $token,
                'trade_date'       => $date->toDateString(),
                'candle_time'      => $slot->toDateTimeString(),
                'open'             => $candle->open,
                'high'             => $candle->high,
                'low'              => $candle->low,
                'close'            => $candle->close,
                'volume'           => $candle->volume ?? 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        $n = $this->upsertRows('raw_ohlc_spot', $rows, ['symbol', 'candle_time'], [
            'open', 'high', 'low', 'close', 'volume', 'updated_at',
        ]);

        $this->info("    ✅ SPOT {$symbol} — {$n} rows stored");
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FUTURES COLLECTOR
    // ═════════════════════════════════════════════════════════════════════════

    private function collectFutures(string $symbol, Carbon $date, array $slots): void
    {
        $expiries = $this->resolveActiveExpiries($symbol, $date);

        if (empty($expiries)) {
            $this->warn("    ⚠️  FUT {$symbol} — no expiry found");
            return;
        }

        foreach ($expiries as $expiry) {
            $inst = $this->resolveFutInstrument($symbol, $expiry);

            if (!$inst) {
                $this->warn("    ⚠️  FUT {$symbol} [{$expiry}] — instrument not found");
                continue;
            }

            $missing = $this->missingSlots($slots, $this->storedFutTimes($symbol, $expiry, $date));

            if (empty($missing)) {
                $this->info("    ✓ FUT {$symbol} [{$expiry}] — up to date");
                continue;
            }

            $this->info("    📥 FUT {$symbol} [{$expiry}] — " . count($missing) . " candles");

            usleep(self::FETCH_DELAY_MS);
            $candles = $this->fetchCandles($inst->instrument_token, $date);

            if (empty($candles)) {
                $this->error("    ✗ FUT {$symbol} [{$expiry}] — no data");
                continue;
            }

            $candleMap = $this->indexByTime($candles);
            $prevOiMap = $this->buildPrevOiMap(
                'raw_ohlc_futures',
                ['symbol' => $symbol, 'expiry_date' => $expiry],
                $date, $slots, $candleMap
            );

            $rows = [];
            $now  = now()->toDateTimeString();

            foreach ($missing as $slot) {
                $candle = $candleMap[$slot->format('H:i')] ?? null;
                if (!$candle) continue;

                $currentOi = (int)($candle->oi ?? 0);
                $prevOi    = $prevOiMap[$slot->format('H:i')] ?? 0;

                $rows[] = [
                    'symbol'           => $symbol,
                    'trading_symbol'   => $inst->trading_symbol,
                    'instrument_token' => $inst->instrument_token,
                    'expiry_date'      => $expiry,
                    'trade_date'       => $date->toDateString(),
                    'candle_time'      => $slot->toDateTimeString(),
                    'open'             => $candle->open,
                    'high'             => $candle->high,
                    'low'              => $candle->low,
                    'close'            => $candle->close,
                    'volume'           => $candle->volume ?? 0,
                    'open_interest'    => $currentOi,
                    'oi_change'        => $currentOi - $prevOi,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            $n = $this->upsertRows(
                'raw_ohlc_futures', $rows,
                ['symbol', 'expiry_date', 'candle_time'],
                ['open', 'high', 'low', 'close', 'volume', 'open_interest', 'oi_change', 'updated_at']
            );

            $this->info("    ✅ FUT {$symbol} [{$expiry}] — {$n} rows stored");
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // OPTIONS COLLECTOR
    // ═════════════════════════════════════════════════════════════════════════

    private function collectOptions(string $symbol, Carbon $date, array $slots, int $depth): void
    {
        $expiries = $this->resolveActiveExpiries($symbol, $date);

        if (empty($expiries)) {
            $this->warn("    ⚠️  OPT {$symbol} — no expiry found");
            return;
        }

        foreach ($expiries as $expiry) {
            $interval = $this->resolveStrikeInterval($symbol, $expiry);

            if ($interval === null) {
                $this->error("    ✗ OPT {$symbol} [{$expiry}] — strike interval unknown");
                continue;
            }

            // ATM frozen at 09:15 — determines which strikes to fetch
            $atmAtOpen = $this->getAtmAtSlot($symbol, $expiry, $date, '09:15', $interval);

            if ($atmAtOpen === null) {
                $this->warn("    ⚠️  OPT {$symbol} [{$expiry}] — FUT 09:15 not stored yet, skipping options");
                continue;
            }

            $strikes = $this->buildStrikes($atmAtOpen, $interval, $depth);

            $this->info("    📊 OPT {$symbol} [{$expiry}] ATM={$atmAtOpen} interval={$interval}");
            $this->info("       Strikes: " . implode(', ', $strikes));

            $this->prewarmOptionCache($symbol, $expiry);

            // Per-slot ATM map (recalculated from stored FUT closes)
            $atmPerSlot = $this->buildAtmPerSlotMap($symbol, $expiry, $date, $slots, $interval);

            $rows       = [];
            $fetchCount = 0;
            $now        = now()->toDateTimeString();

            foreach (['CE', 'PE'] as $type) {
                foreach ($strikes as $strike) {
                    $cacheKey = "{$symbol}_{$strike}_{$type}_{$expiry}";
                    $inst     = $this->instrumentCache[$cacheKey] ?? null;

                    if (!$inst) continue;

                    $missing = $this->missingSlots(
                        $slots,
                        $this->storedOptionTimes($symbol, $expiry, $strike, $type, $date)
                    );

                    if (empty($missing)) continue;

                    if ($fetchCount > 0) usleep(self::FETCH_DELAY_MS);

                    $candles = $this->fetchCandles($inst->instrument_token, $date);
                    $fetchCount++;

                    $candleMap = $this->indexByTime($candles);
                    $prevOiMap = $this->buildPrevOiMap(
                        'raw_ohlc_options',
                        ['symbol' => $symbol, 'expiry_date' => $expiry, 'strike' => $strike, 'option_type' => $type],
                        $date, $slots, $candleMap
                    );

                    foreach ($missing as $slot) {
                        $timeKey   = $slot->format('H:i');
                        $candle    = $candleMap[$timeKey] ?? null;
                        if (!$candle) continue;

                        $currentOi  = (int)($candle->oi ?? 0);
                        $prevOi     = $prevOiMap[$timeKey] ?? 0;
                        $atmCandle  = $atmPerSlot[$timeKey] ?? $atmAtOpen;
                        $strikeDist = $interval > 0
                            ? round(($strike - $atmCandle) / $interval, 2)
                            : 0.0;

                        $rows[] = [
                            'symbol'           => $symbol,
                            'trading_symbol'   => $inst->trading_symbol,
                            'instrument_token' => $inst->instrument_token,
                            'expiry_date'      => $expiry,
                            'trade_date'       => $date->toDateString(),
                            'candle_time'      => $slot->toDateTimeString(),
                            'strike'           => $strike,
                            'option_type'      => $type,
                            'open'             => $candle->open,
                            'high'             => $candle->high,
                            'low'              => $candle->low,
                            'close'            => $candle->close,
                            'volume'           => $candle->volume ?? 0,
                            'open_interest'    => $currentOi,
                            'oi_change'        => $currentOi - $prevOi,
                            'atm_at_open'      => $atmAtOpen,
                            'atm_at_candle'    => $atmCandle,
                            'strike_distance'  => $strikeDist,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ];
                    }

                    $this->line("       {$type} {$strike}: " . count($candles) . " candles");
                }
            }

            $n = $this->upsertRows(
                'raw_ohlc_options', $rows,
                ['symbol', 'expiry_date', 'strike', 'option_type', 'candle_time'],
                ['open', 'high', 'low', 'close', 'volume', 'open_interest', 'oi_change',
                 'atm_at_open', 'atm_at_candle', 'strike_distance', 'updated_at']
            );

            $this->info("    ✅ OPT {$symbol} [{$expiry}] — {$n} rows stored ({$fetchCount} API calls)");
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ATM HELPERS
    // ═════════════════════════════════════════════════════════════════════════

    private function getAtmAtSlot(string $symbol, string $expiry, Carbon $date, string $hhmm, float $interval): ?float
    {
        $close = DB::table('raw_ohlc_futures')
            ->where('symbol', $symbol)
            ->where('trade_date', $date->toDateString())
            ->whereDate('expiry_date', '>=', $expiry)
            ->where('candle_time', $date->copy()->setTimeFromTimeString($hhmm)->toDateTimeString())
            ->orderBy('expiry_date', 'ASC')
            ->value('close');

        return $close !== null ? round((float)$close / $interval) * $interval : null;
    }

    private function buildAtmPerSlotMap(string $symbol, string $expiry, Carbon $date, array $slots, float $interval): array
    {
        $futCloses = DB::table('raw_ohlc_futures')
            ->where('symbol', $symbol)
            ->where('trade_date', $date->toDateString())
            ->whereDate('expiry_date', '>=', $expiry)
            ->orderBy('expiry_date', 'ASC')
            ->orderBy('candle_time', 'ASC')
            ->get(['candle_time', 'close'])
            ->keyBy(fn($r) => Carbon::parse($r->candle_time)->format('H:i'));

        $atmMap       = [];
        $lastKnownAtm = null;

        foreach ($slots as $slot) {
            $key    = $slot->format('H:i');
            $futRow = $futCloses[$key] ?? null;
            if ($futRow !== null) {
                $lastKnownAtm = round((float)$futRow->close / $interval) * $interval;
            }
            $atmMap[$key] = $lastKnownAtm;
        }

        return $atmMap;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // OI CHANGE HELPER
    // ═════════════════════════════════════════════════════════════════════════

    private function buildPrevOiMap(string $table, array $filters, Carbon $date, array $slots, array $candleMap): array
    {
        $query = DB::table($table)->where('trade_date', $date->toDateString());
        foreach ($filters as $col => $val) $query->where($col, $val);
        $storedToday = $query->orderBy('candle_time', 'ASC')
            ->pluck('open_interest', 'candle_time')
            ->mapWithKeys(fn($oi, $dt) => [Carbon::parse($dt)->format('H:i') => (int)$oi])
            ->toArray();

        $prevDayQuery = DB::table($table)->where('trade_date', '<', $date->toDateString())
            ->orderBy('trade_date', 'DESC')->orderBy('candle_time', 'DESC');
        foreach ($filters as $col => $val) $prevDayQuery->where($col, $val);
        $prevDayOi = (int)($prevDayQuery->value('open_interest') ?? 0);

        $prevMap = [];
        $slots   = array_values($slots);

        foreach ($slots as $i => $slot) {
            $key = $slot->format('H:i');
            if ($i === 0) {
                $prevMap[$key] = $prevDayOi;
            } else {
                $prevKey       = $slots[$i - 1]->format('H:i');
                $prevMap[$key] = (int)($candleMap[$prevKey]->oi ?? $storedToday[$prevKey] ?? 0);
            }
        }

        return $prevMap;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // EXPIRY RESOLUTION
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveActiveExpiries(string $symbol, Carbon $date): array
    {
        $isWeekly     = in_array($symbol, ['NIFTY', 'SENSEX']);
        $exchange     = in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
        $rolloverDays = $isWeekly ? 1 : 5;

        // For both weekly and monthly: fetch expiries >= $date
        // (startOfWeek was wrong for backfill — it returned wrong past expiry)
        $rawExpiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()->values()->toArray();

        if (empty($rawExpiries)) return [];

        // Monthly symbols: keep only last expiry per calendar month
        if (!$isWeekly) {
            $byMonth = [];
            foreach ($rawExpiries as $exp) {
                $byMonth[Carbon::parse($exp)->format('Y-m')] = $exp;
            }
            $rawExpiries = array_values($byMonth);
        }

        // Nearest expiry >= date
        $currentExpiry = $rawExpiries[0] ?? null;
        $nextExpiry    = $rawExpiries[1] ?? null;

        if ($currentExpiry === null) return [];

        // Today IS expiry → skip it, use next
        if (Carbon::parse($currentExpiry)->isSameDay($date)) {
            if ($nextExpiry) return [$nextExpiry];
            $further = ZerodhaInstrument::where('name', $symbol)->where('exchange', $exchange)
                ->where('instrument_type', 'CE')->whereDate('expiry', '>', $currentExpiry)
                ->orderBy('expiry', 'ASC')->value('expiry');
            return $further ? [Carbon::parse($further)->toDateString()] : [];
        }

        // Within rollover window → collect both current + next
        $daysLeft = $this->tradingDaysBetween($date, Carbon::parse($currentExpiry));
        $result   = [$currentExpiry];
        if ($daysLeft <= $rolloverDays && $nextExpiry) $result[] = $nextExpiry;
        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // INSTRUMENT HELPERS
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        $exchange = in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';

        if (in_array($symbol, ['NIFTY', 'SENSEX'])) {
            return ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', $exchange)->where('name', $symbol)
                ->whereDate('expiry', '>=', $expiry)->orderBy('expiry', 'ASC')->first();
        }

        return ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', $exchange)->where('name', $symbol)
            ->whereDate('expiry', $expiry)->first();
    }

    private function prewarmOptionCache(string $symbol, string $expiry): void
    {
        $exchange    = in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
        $instruments = ZerodhaInstrument::where('name', $symbol)->where('exchange', $exchange)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereDate('expiry', $expiry)->get();

        foreach ($instruments as $inst) {
            $this->instrumentCache["{$symbol}_{$inst->strike}_{$inst->instrument_type}_{$expiry}"] = $inst;
        }

        $this->line("       Cached {$instruments->count()} option instruments [{$expiry}]");
    }

    private function resolveStrikeInterval(string $symbol, string $expiry): ?float
    {
        $cacheKey = "{$symbol}_{$expiry}";
        if (isset($this->strikeIntervalCache[$cacheKey])) return $this->strikeIntervalCache[$cacheKey];

        $exchange = in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
        $strikes  = ZerodhaInstrument::where('name', $symbol)->where('exchange', $exchange)
            ->where('instrument_type', 'CE')->whereDate('expiry', $expiry)
            ->orderBy('strike')->pluck('strike')
            ->map(fn($s) => (float)$s)->unique()->sort()->values();

        if ($strikes->count() < 2) return null;

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) $minGap = $gap;
        }

        if ($minGap === PHP_INT_MAX || $minGap <= 0) return null;

        $this->strikeIntervalCache[$cacheKey] = (float)$minGap;
        return (float)$minGap;
    }

    private function buildStrikes(float $atm, float $interval, int $depth): array
    {
        $strikes = [];
        for ($i = -$depth; $i <= $depth; $i++) {
            $strikes[] = round($atm + ($i * $interval), 2);
        }
        return $strikes;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // GAP DETECTION
    // ═════════════════════════════════════════════════════════════════════════

    private function storedSpotTimes(string $symbol, Carbon $date): array
    {
        return DB::table('raw_ohlc_spot')->where('symbol', $symbol)
            ->where('trade_date', $date->toDateString())->pluck('candle_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))->flip()->toArray();
    }

    private function storedFutTimes(string $symbol, string $expiry, Carbon $date): array
    {
        return DB::table('raw_ohlc_futures')->where('symbol', $symbol)
            ->where('expiry_date', $expiry)->where('trade_date', $date->toDateString())
            ->pluck('candle_time')->map(fn($t) => Carbon::parse($t)->format('H:i'))->flip()->toArray();
    }

    private function storedOptionTimes(string $symbol, string $expiry, float $strike, string $type, Carbon $date): array
    {
        return DB::table('raw_ohlc_options')->where('symbol', $symbol)
            ->where('expiry_date', $expiry)->where('strike', $strike)
            ->where('option_type', $type)->where('trade_date', $date->toDateString())
            ->pluck('candle_time')->map(fn($t) => Carbon::parse($t)->format('H:i'))->flip()->toArray();
    }

    private function missingSlots(array $slots, array $stored): array
    {
        return array_values(array_filter($slots, fn($s) => !isset($stored[$s->format('H:i')])));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // API FETCH
    // ═════════════════════════════════════════════════════════════════════════

    private function fetchCandles(int $token, Carbon $date): array
    {
        $from = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $to   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->zerodha->getHistoricalDataByToken($token, '15minute', $from, $to) ?? [];
            } catch (Exception $e) {
                $isRate = str_contains($e->getMessage(), '429')
                    || stripos($e->getMessage(), 'rate') !== false
                    || stripos($e->getMessage(), 'too many') !== false;

                if ($attempt < $this->maxRetries) {
                    sleep($isRate ? 3 : 1);
                } else {
                    $this->error("       ✗ Failed after {$this->maxRetries} attempts: {$e->getMessage()}");
                    Log::error("RawDataCollector: token={$token} — {$e->getMessage()}");
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

    // ═════════════════════════════════════════════════════════════════════════
    // SLOTS & TRADING DAYS
    // ═════════════════════════════════════════════════════════════════════════

    private function allSlots(Carbon $date): array
    {
        $slots = [];
        $cur   = $date->copy()->setTime(9, 15);
        $end   = $date->copy()->setTime(15, 15);
        while ($cur->lte($end)) { $slots[] = $cur->copy(); $cur->addMinutes(15); }
        return $slots;
    }

    private function completedSlots(Carbon $date): array
    {
        $now         = Carbon::now();
        $marketOpen  = $date->copy()->setTimeFromTimeString(self::MARKET_OPEN);
        $marketClose = $date->copy()->setTimeFromTimeString(self::MARKET_CLOSE);

        if ($now->lt($marketOpen)) return [];

        $floored    = (int)floor((int)$now->format('i') / 15) * 15;
        $lastClosed = $date->copy()->setTime((int)$now->format('H'), $floored)->subMinutes(15);
        $lastClosed = match(true) {
            $lastClosed->lt($marketOpen)  => $marketOpen->copy(),
            $lastClosed->gt($marketClose) => $marketClose->copy(),
            default                       => $lastClosed,
        };

        $slots = [];
        $cur   = $marketOpen->copy();
        while ($cur->lte($lastClosed)) { $slots[] = $cur->copy(); $cur->addMinutes(15); }
        return $slots;
    }

    private function buildTradingDays(Carbon $from, Carbon $to): array
    {
        $holidays = DB::table('market_holidays')->where('market_name', 'NSE')
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')->flip()->toArray();

        $days = [];
        $cur  = $from->copy();
        while ($cur->lte($to)) {
            if (!$cur->isWeekend() && !isset($holidays[$cur->toDateString()])) $days[] = $cur->copy();
            $cur->addDay();
        }
        return $days;
    }

    private function tradingDaysBetween(Carbon $from, Carbon $to): int
    {
        if ($from->gte($to)) return 0;

        $holidays = DB::table('market_holidays')->where('market_name', 'NSE')
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')->flip()->toArray();

        $count = 0;
        $cur   = $from->copy()->addDay();
        while ($cur->lte($to)) {
            if (!$cur->isWeekend() && !isset($holidays[$cur->toDateString()])) $count++;
            $cur->addDay();
        }
        return $count;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // BATCH UPSERT
    // ═════════════════════════════════════════════════════════════════════════

    private function upsertRows(string $table, array $rows, array $uniqueBy, array $updateCols): int
    {
        if (empty($rows)) return 0;
        $total = 0;
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateCols);
            $total += count($chunk);
        }
        return $total;
    }
}