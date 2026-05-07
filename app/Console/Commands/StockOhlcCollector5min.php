<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\StockOhlcData5min;
use App\Models\OptionSymbol5min;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * StockOhlcCollector5min
 *
 * Collects NSE EQ (spot) 5-minute candles for stock option underlyings.
 * Runs independently from the derivatives collector.
 *
 * Cron:
 *   * /5 9-15 * * 1-5  php artisan stocks:collect-5min >> /dev/null 2>&1
 *
 * ── WHY SEPARATE ─────────────────────────────────────────────────────────
 *   - Failure isolation: stock issue won't block options collection
 *   - Faster: no FUT/options overhead
 *   - Independent retry and scheduling
 *   - Signals use stock close as underlying for non-index option ATM
 * ─────────────────────────────────────────────────────────────────────────
 *
 * ── WHAT IT COLLECTS ─────────────────────────────────────────────────────
 *   Only NON-INDEX symbols from option_symbols_5min.
 *   INDEX symbols (NIFTY, BANKNIFTY, SENSEX, FINNIFTY, MIDCPNIFTY)
 *   use FUT price from option_ohlc_data_5min — not collected here.
 *
 * ── SERIES TAGGING ───────────────────────────────────────────────────────
 *   Each row tagged with:
 *     series      = 'MAR' | 'APR' etc.   (month only, from nearest expiry)
 *     series_type = 'MAR25' | 'APR25'    (month+year, ABSOLUTE)
 *   Matches option_ohlc_data_5min convention — enables direct JOIN.
 *   Signal engine: stock.series_type = option.series_type
 * ─────────────────────────────────────────────────────────────────────────
 */
class StockOhlcCollector5min extends Command
{
    use OptionExpiryResolver;

    private const BROKER_CLIENT_ID = 'ZZL808';

    protected $signature = 'stocks:collect-5min
                            {--symbol=      : Specific symbol only}
                            {--retry=3      : API retry attempts}
                            {--retry-delay=2: Seconds between retries}
                            {--chunk=200    : DB upsert chunk size}
                            {--force-date=  : Override today (Y-m-d)}
                            {--from-date=   : Historical start (Y-m-d)}
                            {--to-date=     : Historical end (Y-m-d)}
                            {--force-complete : Re-fetch full day ignoring stored data}';

    protected $description = '5-min NSE EQ collector for stock option underlyings — absolute series-tagged, gap-fill';

    private const MARKET_START    = '09:15';
    private const MARKET_END      = '15:30';
    private const CANDLE_INTERVAL = '5minute';
    private const CANDLE_MINUTES  = 5;

    // These symbols use FUT as underlying — EQ not needed for them
    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'SENSEX', 'FINNIFTY', 'MIDCPNIFTY'];

    private static array $fetchCache = [];
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
        $specSymbol    = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $forceComplete = (bool)$this->option('force-complete');

        // Date range
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

        $this->info("📈 Stock EQ 5-Min Collector — " . $now->format('Y-m-d H:i:s'));
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

        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);

        // Symbols — exclude index symbols (they use FUT, not EQ)
        $query = OptionSymbol5min::active();
        if ($specSymbol) $query->where('symbol', $specSymbol);
        $allSymbols   = $query->pluck('symbol')->toArray();
        $stockSymbols = array_values(array_diff($allSymbols, self::INDEX_SYMBOLS));

        if (empty($stockSymbols)) {
            $this->warn("⏭  No stock symbols to collect (all are index symbols).");
            return 0;
        }

        $this->info("   Stock symbols (" . count($stockSymbols) . "): " . implode(', ', $stockSymbols));
        $this->newLine();

        // Trading days
        $tradingDays = [];
        for ($d = $dateFrom->copy(); $d->lte($dateTo); $d->addDay()) {
            if (!$d->isWeekend() && !$this->isMarketHoliday($d->toDateString())) {
                $tradingDays[] = $d->copy();
            }
        }

        if (empty($tradingDays)) {
            $this->warn("⏭  No trading days in range.");
            return 0;
        }

        $this->info("   Trading days: " . count($tradingDays));
        $this->newLine();

        // ── Date loop ─────────────────────────────────────────────────────────
        foreach ($tradingDays as $dayIndex => $date) {
            $dayNum = $dayIndex + 1;
            $total  = count($tradingDays);
            self::$fetchCache = [];

            $this->info("════════════════════════════════════════");
            $this->info("📅 Day {$dayNum}/{$total} — {$date->toDateString()}");
            $this->info("════════════════════════════════════════");

            $allIntervals = $this->generateIntervals($date);

            if ($isHistorical) {
                $intervalsToProcess = $allIntervals;
            } else {
                $lastSlot = $this->getLastCompletedSlot($now, $date);
                $intervalsToProcess = array_values(
                    array_filter($allIntervals, fn($t) => $t->lte($lastSlot))
                );
                if (empty($intervalsToProcess)) {
                    $this->warn("   ⏳ No completed candle yet.");
                    return 0;
                }
                $this->info("   Last completed: " . $lastSlot->format('H:i'));
            }

            // Resolve expiry-based series info for all stock symbols on this date.
            // Computed once per day — same expiry applies to all intervals that day.
            $seriesMap = $this->resolveSeriesMap($stockSymbols, $date);

            foreach ($stockSymbols as $symbol) {
                $this->collectStock(
                    $broker, $symbol, $date,
                    $intervalsToProcess, $maxRetries, $retryDelay, $chunkSize,
                    $seriesMap[$symbol] ?? [], $forceComplete
                );
            }

            $this->newLine();
            $this->info("✅ Day {$dayNum}/{$total} complete");
            $this->newLine();
        }

        $this->info("🏁 Done — " . Carbon::now()->format('H:i:s'));
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // COLLECT ONE STOCK
    // ══════════════════════════════════════════════════════════════════════════

    private function collectStock(
        BrokerApi $broker, string $symbol, Carbon $date,
        array $allIntervals, int $maxRetries, int $retryDelay, int $chunkSize,
        array $seriesInfo, bool $forceComplete = false
    ): void {
        // Find NSE EQ instrument
        $instrument = ZerodhaInstrument::where('trading_symbol', $symbol)
            ->where('exchange', 'NSE')
            ->where('instrument_type', 'EQ')
            ->first();

        if (!$instrument) {
            $this->warn("   ⚠️  {$symbol}: No NSE EQ instrument found — skip");
            Log::warning("StockOhlcCollector5min: No NSE EQ for {$symbol}");
            return;
        }

        // Check already stored
        $stored = StockOhlcData5min::whereDate('trade_date', $date)
            ->where('symbol', $symbol)->where('is_missing', 0)
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->flip()->toArray();

        $missing = $forceComplete
            ? $allIntervals
            : array_values(array_filter($allIntervals, fn($t) => !isset($stored[$t->format('H:i')])));

        if (empty($missing)) {
            $last = end($allIntervals);
            $this->info("   ✓ {$symbol} up to date (" . ($last ? $last->format('H:i') : '—') . ")");
            return;
        }

        $fromTime = $missing[0]->format('Y-m-d H:i:s');
        $toTime   = end($missing)->copy()->addMinute()->format('Y-m-d H:i:s');

        $candles = $this->fetchCandles($instrument->instrument_token, $fromTime, $toTime, $maxRetries, $retryDelay);

        if (empty($candles)) {
            $this->warn("   ⚠️  {$symbol}: No data returned");
            return;
        }

        $candleMap = $this->buildCandleMap($candles);
        $rows      = [];
        $now       = now()->toDateTimeString();

        // series_type is ABSOLUTE (MAR25/APR25) — derived from actual expiry date.
        // Matches option_ohlc_data_5min.series_type so signal engine can JOIN directly.
        // Never use relative labels ('current'/'next') — they break backtesting queries.
        $seriesType = $seriesInfo['series_type'] ?? '';
        $series     = $seriesInfo['current_series'] ?? '';

        foreach ($missing as $slot) {
            $key    = $slot->format('H:i');
            $candle = $candleMap[$key] ?? null;

            // Validate candle — reject garbage data from API
            if ($candle !== null) {
                if ($candle->high < $candle->low || ($candle->open == 0 && $candle->close == 0)) {
                    Log::warning("StockOhlcCollector5min: Bad candle at {$key} for {$symbol} — discarded");
                    $candle = null;
                }
            }

            $rows[] = [
                'broker_api_id'    => $broker->id,
                'trade_date'       => $date->toDateString(),
                'interval_time'    => $slot->toDateTimeString(),
                'symbol'           => $symbol,
                'trading_symbol'   => $instrument->trading_symbol,
                'instrument_token' => $instrument->instrument_token,
                'exchange'         => 'NSE',
                'series'           => $series,
                'series_type'      => $seriesType,
                'open'             => $candle ? (float)$candle->open   : 0,
                'high'             => $candle ? (float)$candle->high   : 0,
                'low'              => $candle ? (float)$candle->low    : 0,
                'close'            => $candle ? (float)$candle->close  : 0,
                'volume'           => $candle ? (int)$candle->volume   : 0,
                'is_missing'       => $candle ? 0 : 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            StockOhlcData5min::upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'interval_time', 'symbol'],
                ['open', 'high', 'low', 'close', 'volume', 'series', 'series_type', 'is_missing', 'updated_at']
            );
        }

        $this->info("   ✅ {$symbol} — " . count($rows) . " rows (series: {$seriesType} → next: " . ($seriesInfo['next_series_type'] ?? 'N/A') . ")");
        Log::info("StockOhlcCollector5min: {$symbol} upserted " . count($rows) . " rows series_type={$seriesType}");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SERIES RESOLUTION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * For each stock symbol, resolve current + next expiry series labels.
     *
     * series_type is ABSOLUTE (MAR25/APR25) derived from the actual expiry date.
     * Computed once per trading day — same expiry applies to all intervals.
     *
     * Returns:
     *   [
     *     'RELIANCE' => [
     *       'current_series'   => 'MAR',
     *       'series_type'      => 'MAR25',     ← use this in DB rows
     *       'next_series'      => 'APR',
     *       'next_series_type' => 'APR25',
     *       'current_expiry'   => '2025-03-27',
     *       'next_expiry'      => '2025-04-24',
     *     ],
     *     ...
     *   ]
     */
    private function resolveSeriesMap(array $symbols, Carbon $date): array
    {
        $map = [];
        foreach ($symbols as $symbol) {
            // Stock options are on NFO (not NSE EQ)
            $expiries = ZerodhaInstrument::where('name', $symbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', 'CE')
                ->whereDate('expiry', '>=', $date)
                ->orderBy('expiry')
                ->pluck('expiry')
                ->map(fn($e) => Carbon::parse($e)->toDateString())
                ->unique()->values();

            // Keep the LAST expiry per calendar month = the actual monthly expiry.
            // Last-write-wins: later date in same month overwrites earlier (weekly) dates.
            $monthly = [];
            foreach ($expiries as $exp) {
                $monthly[Carbon::parse($exp)->format('Y-m')] = $exp;
            }
            $monthly = array_values($monthly);

            // Build absolute series_type: 'MAR25', 'APR25' etc.
            $currentExpiry = $monthly[0] ?? null;
            $nextExpiry    = $monthly[1] ?? null;

            $map[$symbol] = [
                'current_series'   => $currentExpiry ? strtoupper(Carbon::parse($currentExpiry)->format('M')) : '',
                'series_type'      => $currentExpiry
                    ? strtoupper(Carbon::parse($currentExpiry)->format('M')) . Carbon::parse($currentExpiry)->format('y')
                    : '',
                'next_series'      => $nextExpiry ? strtoupper(Carbon::parse($nextExpiry)->format('M')) : '',
                'next_series_type' => $nextExpiry
                    ? strtoupper(Carbon::parse($nextExpiry)->format('M')) . Carbon::parse($nextExpiry)->format('y')
                    : '',
                'current_expiry'   => $currentExpiry,
                'next_expiry'      => $nextExpiry,
            ];
        }
        return $map;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FETCH + TIME HELPERS
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
                    $this->warn("   ⏳ Attempt {$attempt}/{$maxRetries}" . ($isRate ? ' [rate limit]' : '') . " → wait {$wait}s");
                    sleep($wait);
                    $attempt++;
                } else {
                    Log::error("StockOhlcCollector5min: token {$token} failed: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    private function buildCandleMap(array $candles): array
    {
        $map = [];
        foreach ($candles as $c) $map[Carbon::parse($c->date)->format('H:i')] = $c;
        return $map;
    }

    private function generateIntervals(Carbon $date): array
    {
        $slots = [];
        $cur   = $date->copy()->setTime(9, 15);
        $end   = $date->copy()->setTime(15, 30);
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
        return match(true) {
            $slot->lt($start) => $start->copy(),
            $slot->gt($end)   => $end->copy(),
            default           => $slot,
        };
    }

    private function isMarketHoliday(string $date): bool
    {
        return \Illuminate\Support\Facades\DB::table('market_holidays')
            ->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}