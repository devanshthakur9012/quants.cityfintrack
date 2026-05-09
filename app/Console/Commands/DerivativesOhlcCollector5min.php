<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionOhlcData5min;
use App\Models\OptionSymbol5min;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * DerivativesOhlcCollector5min
 *
 * Collects FUT + CE + PE 5-minute OHLC data for ALL symbols.
 * Stores everything in option_ohlc_data_5min.
 *
 * Cron:
 *   * /5 9-15 * * 1-5  php artisan derivatives:collect-5min >> /dev/null 2>&1
 *
 * ══════════════════════════════════════════════════════════════════
 * KEY PRINCIPLES
 * ══════════════════════════════════════════════════════════════════
 *
 * 1. ALWAYS COLLECT CURRENT + NEXT EXPIRY
 *    Even if today is March 5 and expiry is March 27, we ALSO collect
 *    April expiry. Money starts shifting 7 days before expiry.
 *    Near expiry (≤7 days): heavier next expiry collection.
 *
 * 2. STRIKE RANGE
 *    Current expiry : ATM ± 15  (31 strikes — full range for intraday)
 *    Next expiry    : ATM ± 10  (21 strikes — enough for next series)
 *
 * 3. REAL FUT PRICE PER CANDLE
 *    future_price on every CE/PE row = REAL FUT close at that interval.
 *    NOT the frozen 09:15 ATM. Signal engine uses this for rolling ATM.
 *
 * 4. PER-INTERVAL ATM
 *    atm_strike = nearest valid strike to FUT close at each interval.
 *    Stored as metadata only. Signal engine recomputes dynamically.
 *
 * 5. SERIES TAGGING
 *    Every row tagged: series = 'MAR', series_type = 'MAR25' | 'APR25' etc.
 *    series_type is ABSOLUTE (month+year from the expiry date) — never relative.
 *    'current'/'next' labels were ambiguous across months; MAR25/APR25 are not.
 *    Enables expiry-cycle-aware backtesting without any date-relative ambiguity.
 *
 * 6. COLLECTOR = DUMB CAMERA
 *    No signal logic here. Just fetch and store wide raw data.
 *    Signal engine is the brain.
 * ══════════════════════════════════════════════════════════════════
 */
class DerivativesOhlcCollector5min extends Command
{
    use OptionExpiryResolver;

    private const BROKER_CLIENT_ID = 'DB0542';

    protected $signature = 'derivatives:collect-5min
                            {--symbol=      : Specific symbol only}
                            {--retry=3      : API retry attempts}
                            {--retry-delay=2: Seconds between retries}
                            {--chunk=200    : DB upsert chunk size}
                            {--force-date=  : Override today (Y-m-d)}
                            {--from-date=   : Historical start (Y-m-d)}
                            {--to-date=     : Historical end (Y-m-d)}
                            {--force-complete : Re-fetch full day ignoring stored data}';

    protected $description = '5-min FUT+CE/PE collector — current+next expiry always, ATM±15/±10, real FUT price, absolute series-tagged';

    private const MARKET_START    = '09:15';
    private const MARKET_END      = '15:30';
    private const CANDLE_INTERVAL = '5minute';
    private const CANDLE_MINUTES  = 5;

    // Strike ranges per expiry type
    private const STRIKE_RANGE_CURRENT = 15; // ATM±15 for current expiry
    private const STRIKE_RANGE_NEXT    = 10; // ATM±10 for next expiry

    // Days before expiry when we start heavy next-expiry collection
    private const DAYS_BEFORE_EXPIRY_NEXT = 7;

    // Batch fetch config
    private const BATCH_SIZE     = 12;      // FIX6: was 8 → ~30% faster
    private const BATCH_SLEEP_US = 350_000; // 350ms between batches
    private const TOKEN_SLEEP_US = 70_000;  // FIX6: was 120ms → 70ms

    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'SENSEX', 'FINNIFTY', 'MIDCPNIFTY'];

    private array $instrumentCache     = [];
    private array $strikeIntervalCache = [];
    private static array $fetchCache   = [];
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ══════════════════════════════════════════════════════════════════════════
    // ENTRY POINT
    // ══════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $now        = Carbon::now();
        $maxRetries = (int)$this->option('retry');
        $retryDelay = (int)$this->option('retry-delay');
        $chunkSize  = (int)$this->option('chunk');
        $specSymbol     = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $forceComplete  = (bool)$this->option('force-complete');

        $forceDate    = $this->option('force-date');
        $fromDate     = $this->option('from-date') ?? $forceDate;
        $toDate       = $this->option('to-date')   ?? ($fromDate ?? null);
        $isHistorical = $fromDate !== null;

        if ($isHistorical) {
            $dateFrom = Carbon::parse($fromDate)->startOfDay();
            $dateTo   = Carbon::parse($toDate)->startOfDay();
            if ($dateFrom->gt($dateTo)) {
                $this->error("❌ --from-date must be ≤ --to-date");
                return 1;
            }
        } else {
            $dateFrom = $dateTo = Carbon::today();
        }

        $this->info("⚡ Derivatives 5-Min Collector — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Current expiry  : ATM±" . self::STRIKE_RANGE_CURRENT . " strikes");
        $this->info("   Next expiry     : ATM±" . self::STRIKE_RANGE_NEXT . " strikes");
        $this->info("   Next expiry trigger: ≤" . self::DAYS_BEFORE_EXPIRY_NEXT . " days before expiry");
        $this->info("   Mode: " . ($isHistorical
            ? "HISTORICAL {$dateFrom->toDateString()} → {$dateTo->toDateString()}"
            : "LIVE {$dateFrom->toDateString()}"));
        $this->newLine();

        // Broker
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()->validToken()->first();

        if (!$broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid");
            return 1;
        }

        $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);

        $query   = OptionSymbol5min::active();
        if ($specSymbol) $query->where('symbol', $specSymbol);
        $symbols = $query->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols in option_symbols_5min.');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));
        $this->newLine();

        // Trading days
        $tradingDays = [];
        for ($d = $dateFrom->copy(); $d->lte($dateTo); $d->addDay()) {
            if (!$d->isWeekend() && !$this->isMarketHoliday($d->toDateString())) {
                $tradingDays[] = $d->copy();
            }
        }

        if (empty($tradingDays)) { $this->warn("⏭  No trading days."); return 0; }

        $this->info("   Trading days: " . count($tradingDays));
        $this->newLine();

        // ── Date loop ─────────────────────────────────────────────────────────
        foreach ($tradingDays as $di => $date) {
            $dn = $di + 1; $total = count($tradingDays);

            $this->info("════════════════════════════════════════════════════════");
            $this->info("📅 Day {$dn}/{$total} — {$date->toDateString()}");
            $this->info("════════════════════════════════════════════════════════");

            $this->instrumentCache     = [];
            $this->strikeIntervalCache = [];
            self::$fetchCache          = [];

            $allIntervals = $this->generateIntervals($date);

            if ($isHistorical) {
                $intervalsToProcess = $allIntervals;
            } else {
                $lastSlot = $this->getLastCompletedSlot($now, $date);
                $intervalsToProcess = array_values(
                    array_filter($allIntervals, fn($t) => $t->lte($lastSlot))
                );
                if (empty($intervalsToProcess)) { $this->warn("   ⏳ No completed candle yet."); return 0; }
                $this->info("   Last completed: " . $lastSlot->format('H:i'));
            }

            $this->info("   Slots: " . count($intervalsToProcess) . " (09:15 → 15:30)");
            $this->newLine();

            foreach ($symbols as $baseSymbol) {
                $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("   📊 {$baseSymbol}");

                // ── Resolve BOTH current and next expiry ──────────────────────
                $expirySets = $this->resolveExpiryPairs($baseSymbol, $date);

                foreach ($expirySets as $expirySet) {
                    // Pre-warm instrument cache
                    $cepeExpiry = $this->getCePeExpiry($baseSymbol, $expirySet['fut_expiry'], $date);
                    $this->prewarmInstrumentCache($baseSymbol, $cepeExpiry);

                    $futInstrument = $this->resolveFutInstrument($baseSymbol, $expirySet['fut_expiry']);
                    if (!$futInstrument) {
                        $this->warn("      ⚠️  No FUT for {$baseSymbol} [{$expirySet['fut_expiry']}] — skip");
                        continue;
                    }

                    $strikeInterval = $this->resolveStrikeInterval($baseSymbol, $cepeExpiry);
                    if ($strikeInterval === null) {
                        $this->error("      ✗ Strike interval unknown for {$baseSymbol} [{$cepeExpiry}] — skip");
                        continue;
                    }

                    $this->info("      [{$expirySet['series_type']}] expiry={$expirySet['fut_expiry']} series={$expirySet['series']} interval={$strikeInterval} range=ATM±{$expirySet['strike_range']}");

                    $this->collectDerivatives(
                        $broker, $baseSymbol,
                        $futInstrument, $expirySet['fut_expiry'],
                        $cepeExpiry, $strikeInterval,
                        $expirySet['series'], $expirySet['series_type'],
                        $expirySet['strike_range'],
                        $date, $intervalsToProcess, $maxRetries, $retryDelay, $chunkSize,
                        $forceComplete
                    );
                }
            }

            $this->newLine();
            $this->info("✅ Day {$dn}/{$total} complete");
            $this->newLine();
        }

        $this->info("🏁 Done — " . Carbon::now()->format('H:i:s'));
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // COLLECT DERIVATIVES FOR ONE SYMBOL × ONE EXPIRY
    // ══════════════════════════════════════════════════════════════════════════

    private function collectDerivatives(
        BrokerApi $broker, string $baseSymbol,
        ZerodhaInstrument $futInstrument, string $futExpiry,
        string $cepeExpiry, float $strikeInterval,
        string $series, string $seriesType, int $strikeRange,
        Carbon $date, array $allIntervals, int $maxRetries, int $retryDelay, int $chunkSize,
        bool $forceComplete = false
    ): void {
        // ISSUE2 FIX: Check FUT + CE + PE presence together — not FUT alone.
        // FUT may exist but all CE/PE rows could have failed silently.
        // We count ALL instrument types per interval. An interval is "complete"
        // only if it has FUT row AND at least some CE/PE rows.
        $storedFut = OptionOhlcData5min::whereDate('trade_date', $date)
            ->where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('trading_symbol', $futInstrument->trading_symbol)
            ->where('series_type', $seriesType)
            ->where('is_missing', 0)
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->flip()->toArray();

        // Intervals that have FUT but fewer than 9 total rows = incomplete
        $incompleteIntervals = [];
        if (!empty($storedFut)) {
            $counts = OptionOhlcData5min::whereDate('trade_date', $date)
                ->where('base_symbol', $baseSymbol)
                ->where('series_type', $seriesType)
                ->where('is_missing', 0)
                ->selectRaw('TIME(interval_time) as t, COUNT(*) as cnt')
                ->groupBy('t')
                ->pluck('cnt', 't')
                ->toArray();

            foreach ($storedFut as $timeKey => $_) {
                $cnt = $counts[$timeKey . ':00'] ?? ($counts[$timeKey] ?? 0);
                if ($cnt < 9) $incompleteIntervals[$timeKey] = true;
            }
        }

        // --force-complete: re-fetch everything. Otherwise: fetch missing + incomplete.
        $missing = $forceComplete
            ? $allIntervals
            : array_values(array_filter(
                $allIntervals,
                fn($t) => !isset($storedFut[$t->format('H:i')])
                       || isset($incompleteIntervals[$t->format('H:i')])
            ));

        if (empty($missing) && !$forceComplete) {
            // FIX4: Verify partial data integrity at the last stored slot.
            // Min expected: 1 FUT + 4 CE + 4 PE = 9 rows (even for very narrow range).
            // $wideStrikes is NOT available here (computed later) — use safe threshold of 9.
            $last     = end($allIntervals);
            $lastTime = $last ? $last->toDateTimeString() : null;
            if ($lastTime) {
                $actualCount = OptionOhlcData5min::whereDate('trade_date', $date)
                    ->where('base_symbol', $baseSymbol)
                    ->where('series_type', $seriesType)
                    ->where('interval_time', $lastTime)
                    ->count();
                // Fewer than 9 rows at the last slot = partial data loss — re-queue
                if ($actualCount > 0 && $actualCount < 9) {
                    $this->warn("      ⚠️  {$baseSymbol} [{$seriesType}] partial data at last slot ({$actualCount} rows < 9) — re-queuing");
                    Log::warning("DerivativesCollector5min: Partial data {$baseSymbol} [{$seriesType}] last slot {$lastTime}: {$actualCount} rows");
                    $missing = [$last];
                }
            }
            if (empty($missing)) {
                $this->info("      ✓ {$baseSymbol} [{$seriesType}] up to date (" . ($last ? $last->format('H:i') : '—') . ")");
                return;
            }
        }

        $currentSlot = end($missing);
        $gapCount    = count($missing) - 1;
        $this->info("      " . ($gapCount > 0 ? "🔄 gap-fill {$gapCount} + " : "📥 ")
            . "fetch to " . $currentSlot->format('H:i'));

        // ── Step 1: Fetch FUT candles ─────────────────────────────────────────
        $fromTime = $missing[0]->format('Y-m-d H:i:s');
        $toTime   = $currentSlot->copy()->addMinute()->format('Y-m-d H:i:s'); // +1 min Zerodha fix

        $futCandles = $this->fetchCandles($futInstrument->instrument_token, $fromTime, $toTime, $maxRetries, $retryDelay);

        if (empty($futCandles)) {
            $this->error("      ✗ FUT fetch failed for {$baseSymbol} [{$futExpiry}]");
            Log::error("DerivativesCollector5min: FUT fetch failed {$baseSymbol} [{$futExpiry}] {$date->toDateString()}");
            return;
        }

        $futMap = $this->buildCandleMap($futCandles);

        // ── Step 2: Get 09:15 FUT for strike center ───────────────────────────
        $opening = $futMap['09:15'] ?? $this->fetch0915Candle(
            $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (!$opening) {
            $this->error("      ✗ 09:15 FUT candle missing for {$baseSymbol}");
            return;
        }

        // ── Step 3: Smart center from FUT range (RISK1 FIX: trend-aware) ─────
        //
        // OLD (buggy): center = midpoint(dayHigh, dayLow)
        //   Problem: if NIFTY trends from 23000 → 23600, midpoint = 23300
        //   but real active strikes are 23400–23600. Band misses upside.
        //
        // NEW (fixed): smart weighted center
        //   70% weight on latest FUT price  → follows current market reality
        //   30% weight on day midpoint       → keeps range stability
        //
        // This ensures the strike band is always centered where market IS,
        // not where it averaged out across the day.
        $allStrikes = $this->getAvailableStrikes($baseSymbol, $cepeExpiry);

        // Compute day's FUT price range.
        // Always anchor with $opening->close (09:15) so the range is never purely
        // based on a small recent window. This gives a stable, day-aware midpoint.
        $futPrices   = array_map(fn($c) => (float)$c->close, array_values($futMap));
        $futPrices[] = (float)$opening->close; // always include 09:15 as anchor
        $dayFutHigh  = max($futPrices);
        $dayFutLow   = min($futPrices);
        $dayMidpoint = ($dayFutHigh + $dayFutLow) / 2;

        // Latest FUT price = candle at $currentSlot (the most recent processed interval).
        // IMPORTANT: end($futMap) is NOT safe — PHP arrays are insertion-ordered, not
        // time-ordered. Using $currentSlot->format('H:i') guarantees we pick the actual
        // latest candle regardless of how Zerodha returned the data.
        $latestFutPrice = (float)($futMap[$currentSlot->format('H:i')]->close ?? $opening->close);

        // Smart center: 70% current price + 30% day midpoint
        // — Follows trend but stays anchored to full-day range
        $smartCenter = (0.7 * $latestFutPrice) + (0.3 * $dayMidpoint);
        $centerAtm   = $this->nearestStrike($smartCenter, $allStrikes, $strikeInterval);

        // Dynamic band: RISK2 FIX — static band misses strikes on volatile days.
        // Use max(configured range, 75% of actual day range) so band auto-expands
        // on high-VIX days and stays lean on low-VIX days.
        // Cap at 2× the configured range to prevent API overload on extreme days.
        $dayRange  = $dayFutHigh - $dayFutLow;
        $bandLimit = min(
            max($strikeRange * $strikeInterval, $dayRange * 0.75),
            $strikeRange * $strikeInterval * 2
        );

        $wideStrikes = array_values(array_filter(
            $allStrikes,
            fn($s) => abs($s - $centerAtm) <= $bandLimit
        ));

        // Safety: if market moved so far that the band has < 5 strikes, expand it
        if (count($wideStrikes) < 5) {
            $bandLimit   = $bandLimit * 2;
            $wideStrikes = array_values(array_filter(
                $allStrikes,
                fn($s) => abs($s - $centerAtm) <= $bandLimit
            ));
            $this->warn("      ⚠️  {$baseSymbol}: band expanded to ±{$bandLimit} (market moved wide)");
        }

        $this->info("      09:15={$opening->close} latest={$latestFutPrice} dayRange=[{$dayFutLow}–{$dayFutHigh}] smartCenter={$smartCenter} ATM={$centerAtm} band=±{$bandLimit} → " . count($wideStrikes) . " strikes");

        // ── Step 4: Batch-fetch options ───────────────────────────────────────
        $optionCache = $this->batchFetchOptions(
            $baseSymbol, $wideStrikes, $cepeExpiry,
            $fromTime, $toTime, $maxRetries, $retryDelay
        );

        // ── Step 5: Build rows ────────────────────────────────────────────────
        $rows              = [];
        $now               = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($missing as $slot) {
            $key    = $slot->format('H:i');
            $futRow = $futMap[$key] ?? null;

            // FIX2: Validate FUT candle — reject garbage data
            if ($futRow !== null) {
                if ($futRow->high < $futRow->low || ($futRow->open == 0 && $futRow->close == 0)) {
                    Log::warning("DerivativesCollector5min: Bad FUT candle at {$key} for {$baseSymbol} — discarded");
                    $futRow = null;
                } else {
                    $lastKnownFutClose = (float)$futRow->close;
                }
            }

            // Real FUT price at this interval — NOT frozen
            $realFutPrice = $lastKnownFutClose;

            // Per-interval ATM (metadata — signal engine recomputes)
            $intervalAtm = $realFutPrice
                ? $this->nearestStrike($realFutPrice, $allStrikes, $strikeInterval)
                : $centerAtm;

            // ── FUT row ───────────────────────────────────────────────────────
            $rows[] = [
                'broker_api_id'    => $broker->id,
                'trade_date'       => $date->toDateString(),
                'interval_time'    => $slot->toDateTimeString(),
                'trading_symbol'   => $futInstrument->trading_symbol,
                'base_symbol'      => $baseSymbol,
                'future_symbol'    => $futInstrument->trading_symbol,
                'instrument_type'  => 'FUT',
                'strike'           => null,
                'exchange'         => $futInstrument->exchange,
                'instrument_token' => $futInstrument->instrument_token,
                'expiry_date'      => $futExpiry,
                'series'           => $series,
                'series_type'      => $seriesType,
                'future_price'     => $futRow ? (float)$futRow->close : 0,
                'atm_strike'       => $intervalAtm,
                'strike_position'  => 'N/A',
                'open'             => $futRow ? (float)$futRow->open   : 0,
                'high'             => $futRow ? (float)$futRow->high   : 0,
                'low'              => $futRow ? (float)$futRow->low    : 0,
                'close'            => $futRow ? (float)$futRow->close  : 0,
                'volume'           => $futRow ? (int)$futRow->volume   : 0,
                // ISSUE4 FIX: null = no OI data from API; 0 = actual zero OI. Distinct for backtest.
                'oi'               => $futRow ? (isset($futRow->oi) ? (int)$futRow->oi : null) : null,
                'is_missing'       => $futRow ? 0 : 1,
                'created_at'       => $now, 'updated_at' => $now,
            ];

            // ── CE + PE rows ──────────────────────────────────────────────────
            foreach (['CE', 'PE'] as $optType) {
                foreach ($wideStrikes as $strike) {
                    $cacheKey   = "{$baseSymbol}_{$strike}_{$optType}_{$cepeExpiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;
                    if (!$instrument) continue;

                    $token     = $instrument->instrument_token;
                    $optCandle = $optionCache[$token][$key] ?? null;
                    // FIX2: Validate option candle — reject garbage
                    if ($optCandle !== null) {
                        if ($optCandle->high < $optCandle->low || ($optCandle->open == 0 && $optCandle->close == 0)) {
                            $optCandle = null;
                        }
                    }

                    // Strike position relative to this interval's ATM
                    $diff      = $intervalAtm > 0 ? (int)round(($strike - $intervalAtm) / $strikeInterval) : 0;
                    $strikePos = $diff === 0 ? 'ATM' : ($diff > 0 ? 'ATM+' . $diff : 'ATM' . $diff);

                    $rows[] = [
                        'broker_api_id'    => $broker->id,
                        'trade_date'       => $date->toDateString(),
                        'interval_time'    => $slot->toDateTimeString(),
                        'trading_symbol'   => $instrument->trading_symbol,
                        'base_symbol'      => $baseSymbol,
                        'future_symbol'    => $futInstrument->trading_symbol,
                        'instrument_type'  => $optType,
                        'strike'           => $strike,
                        'exchange'         => $instrument->exchange,
                        'instrument_token' => $token,
                        'expiry_date'      => $cepeExpiry,
                        'series'           => $series,
                        'series_type'      => $seriesType,
                        // ✅ REAL FUT close at this interval — never frozen
                        'future_price'     => $realFutPrice ?? 0,
                        // ✅ Per-interval ATM — never frozen at 09:15
                        'atm_strike'       => $intervalAtm,
                        'strike_position'  => $strikePos,
                        'open'             => $optCandle ? (float)$optCandle->open   : 0,
                        'high'             => $optCandle ? (float)$optCandle->high   : 0,
                        'low'              => $optCandle ? (float)$optCandle->low    : 0,
                        'close'            => $optCandle ? (float)$optCandle->close  : 0,
                        'volume'           => $optCandle ? (int)$optCandle->volume   : 0,
                        // ISSUE4 FIX: null = no OI from API; 0 = actual zero.
                        'oi'               => $optCandle ? (isset($optCandle->oi) ? (int)$optCandle->oi : null) : null,
                        'is_missing'       => $optCandle ? 0 : 1,
                        'created_at'       => $now, 'updated_at' => $now,
                    ];
                }
            }
        }

        // ── Step 6: Upsert ────────────────────────────────────────────────────
        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            OptionOhlcData5min::upsert(
                $chunk,
                // RISK2 FIX: include expiry_date in unique key.
                // trading_symbol usually encodes expiry (e.g. NIFTY2632023000CE)
                // but adding expiry_date makes this broker-format-independent.
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol', 'expiry_date'],
                [
                    'base_symbol', 'future_symbol', 'future_price', 'atm_strike',
                    'instrument_type', 'strike', 'instrument_token',
                    'open', 'high', 'low', 'close', 'volume', 'oi',
                    'strike_position', 'expiry_date', 'series', 'series_type',
                    'is_missing', 'updated_at',
                ]
            );
            $total += count($chunk);
        }

        $this->info("      ✅ {$baseSymbol} [{$seriesType}/{$series}] — {$total} rows (" . count($missing) . " slots × " . (count($wideStrikes) * 2 + 1) . " instruments)");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EXPIRY PAIR RESOLUTION — ALWAYS CURRENT + NEXT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolve both current AND next expiry for a symbol on a given date.
     *
     * series_type is now ABSOLUTE: 'MAR25', 'APR25' etc. derived from the
     * expiry date itself — never relative labels like 'current'/'next'.
     * This means backtesting queries in any future month are unambiguous.
     *
     * Strike range is determined by expiry position (index in sorted list):
     *   index 0 (nearest expiry)  → ATM±15 (full intraday range)
     *   index 1+ (further expiry) → ATM±10 (enough for next series)
     *
     * Returns array of expiry sets, each with:
     *   fut_expiry, cepe_expiry, series, series_type, strike_range
     */
    private function resolveExpiryPairs(string $symbol, Carbon $date): array
    {
        $sets = [];

        // Get all upcoming expiries
        $isWeekly    = in_array($symbol, ['NIFTY', 'SENSEX']);
        $allExpiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $date)
            ->orderBy('expiry')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()->values()->toArray();

        if (empty($allExpiries)) return [];

        // For monthly symbols: keep last-of-month only
        if (!$isWeekly) {
            $byMonth = [];
            foreach ($allExpiries as $e) {
                $byMonth[Carbon::parse($e)->format('Y-m')] = $e;
            }
            $allExpiries = array_values($byMonth);
        }

        $currentExpiry = $allExpiries[0];
        $nextExpiry    = $allExpiries[1] ?? null;
        $isExpiryDay   = Carbon::parse($currentExpiry)->isSameDay($date);

        // ── Expiry day: collect current (expiring today) + next + far ─────────
        // Current expiry still trades until 15:30 — real volume exists. Must capture.
        // Shift happens AFTER market close. We collect all three in parallel.
        if ($isExpiryDay) {
            $sets[] = $this->makeExpirySet($symbol, $currentExpiry, 0, $date); // expiring today — full range
            if ($nextExpiry) {
                $sets[] = $this->makeExpirySet($symbol, $nextExpiry, 1, $date);
            }
            if (isset($allExpiries[2])) {
                $sets[] = $this->makeExpirySet($symbol, $allExpiries[2], 2, $date);
            }
            $this->warn("      ⚠️  {$symbol}: expiry day — collecting all 3 expiry series");
            return $sets;
        }

        // ── Normal day: always collect current + next ─────────────────────────
        // Big players ALWAYS watch next expiry. Money shifts well before expiry.
        $sets[] = $this->makeExpirySet($symbol, $currentExpiry, 0, $date);
        if ($nextExpiry) {
            $sets[] = $this->makeExpirySet($symbol, $nextExpiry, 1, $date);
        }

        return $sets;
    }

    /**
     * Build a single expiry set descriptor.
     *
     * @param int $positionIndex  0 = nearest expiry (full range), 1+ = further expiry (reduced range)
     *
     * series_type = 'MAR25' | 'APR25' etc. — derived from the expiry date.
     * Absolute and unambiguous. Never changes meaning regardless of when you query.
     */
    private function makeExpirySet(string $symbol, string $expiry, int $positionIndex, Carbon $date): array
    {
        $expiryCarbon = Carbon::parse($expiry);
        $seriesLabel  = strtoupper($expiryCarbon->format('M'));          // MAR, APR …
        $seriesType   = strtoupper($expiryCarbon->format('M')) .         // MAR25, APR25 …
                        $expiryCarbon->format('y');

        // Nearest expiry gets full strike range; all further expiries get reduced range
        $strikeRange = $positionIndex === 0
            ? self::STRIKE_RANGE_CURRENT
            : self::STRIKE_RANGE_NEXT;

        return [
            'fut_expiry'   => $expiry,
            'cepe_expiry'  => $this->getCePeExpiry($symbol, $expiry, $date),
            'series'       => $seriesLabel,
            'series_type'  => $seriesType,
            'strike_range' => $strikeRange,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BATCH FETCH
    // ══════════════════════════════════════════════════════════════════════════

    private function batchFetchOptions(
        string $baseSymbol, array $strikes, string $expiry,
        string $fromTime, string $toTime,
        int $maxRetries, int $retryDelay
    ): array {
        $tokens = [];
        foreach (['CE', 'PE'] as $type) {
            foreach ($strikes as $strike) {
                $inst = $this->instrumentCache["{$baseSymbol}_{$strike}_{$type}_{$expiry}"] ?? null;
                if ($inst) $tokens[] = $inst->instrument_token;
            }
        }

        $cache  = [];
        $chunks = array_chunk(array_unique($tokens), self::BATCH_SIZE);

        foreach ($chunks as $ci => $chunk) {
            foreach ($chunk as $token) {
                $candles       = $this->fetchCandles($token, $fromTime, $toTime, $maxRetries, $retryDelay);
                $cache[$token] = !empty($candles) ? $this->buildCandleMap($candles) : [];
                usleep(self::TOKEN_SLEEP_US);
            }
            if ($ci < count($chunks) - 1) usleep(self::BATCH_SLEEP_US);
        }

        return $cache;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FETCH HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchCandles(int $token, string $fromTime, string $toTime, int $maxRetries, int $retryDelay): array
    {
        $cacheKey = "{$token}|{$fromTime}|{$toTime}";
        if (isset(self::$fetchCache[$cacheKey])) return self::$fetchCache[$cacheKey];

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data   = $this->zerodhaHelper->getHistoricalDataByToken($token, self::CANDLE_INTERVAL, $fromTime, $toTime);
                $result = $data ?? [];
                self::$fetchCache[$cacheKey] = $result;
                return $result;
            } catch (Exception $e) {
                $isRate = stripos($e->getMessage(), 'too many') !== false || stripos($e->getMessage(), '429') !== false;
                if ($attempt < $maxRetries) {
                    $wait = $isRate ? max($retryDelay, 3) : $retryDelay;
                    $this->warn("      ⏳ Attempt {$attempt}/{$maxRetries}" . ($isRate ? ' [rate limit]' : '') . " → {$wait}s");
                    sleep($wait);
                    $attempt++;
                } else {
                    Log::error("DerivativesCollector5min: token {$token} failed: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    private function fetch0915Candle(int $token, Carbon $date, int $maxRetries, int $retryDelay): ?object
    {
        $from    = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $to      = $date->copy()->setTime(9, 21)->format('Y-m-d H:i:s');
        $candles = $this->fetchCandles($token, $from, $to, $maxRetries, $retryDelay);
        return $this->buildCandleMap($candles)['09:15'] ?? null;
    }

    private function buildCandleMap(array $candles): array
    {
        $map = [];
        foreach ($candles as $c) $map[Carbon::parse($c->date)->format('H:i')] = $c;
        return $map;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INSTRUMENT + STRIKE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function prewarmInstrumentCache(string $baseSymbol, string $expiry): void
    {
        $instruments = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', $this->getExchange($baseSymbol))
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)->get();

        foreach ($instruments as $inst) {
            $this->instrumentCache["{$baseSymbol}_{$inst->strike}_{$inst->instrument_type}_{$expiry}"] = $inst;
        }
        $this->info("      Cached {$instruments->count()} instruments [{$expiry}]");
    }

    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);
        if ($isWeekly) {
            return ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', $this->getExchange($symbol))
                ->where('name', $symbol)
                ->whereDate('expiry', '>=', $expiry)
                ->orderBy('expiry')->first();
        }
        $q = ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', $this->getExchange($symbol))
            ->whereDate('expiry', $expiry);
        if (in_array($symbol, self::INDEX_SYMBOLS)) $q->where('name', $symbol);
        else $q->where(fn($q) => $q->where('name', $symbol)->orWhere('trading_symbol', 'LIKE', $symbol . '%'));
        return $q->first();
    }

    private function getCePeExpiry(string $symbol, string $futExpiry, Carbon $date): string
    {
        // ISSUE6 FIX: Only shift CE/PE expiry AFTER market close on expiry day.
        // Before 15:30 on expiry day, current expiry CE/PE are still actively trading
        // with real volume. Shifting early loses that data.
        if (Carbon::parse($futExpiry)->isSameDay($date)) {
            $marketClosed = Carbon::now()->setTimezone('Asia/Kolkata')->format('H:i') >= '15:30';
            if ($marketClosed) {
                $next = $this->getNextExpiry($symbol, $date);
                return $next ?? $futExpiry;
            }
            // Before close: keep current expiry for CE/PE (still trading)
        }
        return $futExpiry;
    }

    private function getNextExpiry(string $symbol, Carbon $afterDate): ?string
    {
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);
        $expiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>', $afterDate)
            ->orderBy('expiry')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()->values()->toArray();

        if (empty($expiries)) return null;
        if (!$isWeekly) {
            $byMonth = [];
            foreach ($expiries as $e) $byMonth[Carbon::parse($e)->format('Y-m')] = $e;
            $expiries = array_values($byMonth);
        }
        return $expiries[0] ?? null;
    }

    private function resolveStrikeInterval(string $symbol, string $expiry): ?float
    {
        $key = "{$symbol}_{$expiry}";
        if (isset($this->strikeIntervalCache[$key])) return $this->strikeIntervalCache[$key];

        $strikes = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->orderBy('strike')->pluck('strike')
            ->map(fn($s) => (float)$s)->unique()->sort()->values();

        if ($strikes->count() < 2) return null;
        $min = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $min) $min = $gap;
        }
        if ($min === PHP_INT_MAX || $min <= 0) return null;
        return $this->strikeIntervalCache[$key] = (float)$min;
    }

    private function getAvailableStrikes(string $symbol, string $expiry): array
    {
        return ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->pluck('strike')->map(fn($s) => (float)$s)
            ->unique()->sort()->values()->toArray();
    }

    /**
     * @deprecated FIX1: Not used — replaced by real exchange strike filter.
     * Kept for reference only. Use getAvailableStrikes() + array_filter() instead.
     */
    private function buildWideStrikeList(float $center, float $interval, int $range): array
    {
        $strikes = [];
        for ($i = -$range; $i <= $range; $i++) $strikes[] = round($center + ($i * $interval), 2);
        return $strikes;
    }

    private function nearestStrike(float $price, array $available, float $interval): float
    {
        if (empty($available)) return round($price / $interval) * $interval;
        $closest = null; $minDiff = PHP_INT_MAX;
        foreach ($available as $s) { $d = abs($s - $price); if ($d < $minDiff) { $minDiff = $d; $closest = $s; } }
        return (float)$closest;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TIME HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function generateIntervals(Carbon $date): array
    {
        $slots = []; $cur = $date->copy()->setTime(9, 15); $end = $date->copy()->setTime(15, 30);
        while ($cur->lte($end)) { $slots[] = $cur->copy(); $cur->addMinutes(self::CANDLE_MINUTES); }
        return $slots;
    }

    private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    {
        $start = $date->copy()->setTimeFromTimeString(self::MARKET_START);
        $end   = $date->copy()->setTimeFromTimeString(self::MARKET_END);
        if ($now->lt($start)) return $start->copy();
        $min  = (int)(floor((int)$now->format('i') / self::CANDLE_MINUTES) * self::CANDLE_MINUTES);
        $slot = $date->copy()->setTime((int)$now->format('H'), $min, 0)->subMinutes(self::CANDLE_MINUTES);
        return match(true) { $slot->lt($start) => $start->copy(), $slot->gt($end) => $end->copy(), default => $slot };
    }

    private function isMarketHoliday(string $date): bool
    {
        return \Illuminate\Support\Facades\DB::table('market_holidays')
            ->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}