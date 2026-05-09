<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MutualFundStock;
use App\Models\MutualFundStockOhlc1hr;
use App\Models\ZerodhaInstrument;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class MutualFundStockOhlc1hrCollector extends Command
{
    private const BROKER_CLIENT_ID = 'DB0542';
    private const KITE_API_BASE    = 'https://api.kite.trade';

    // Zerodha allows max 60 days per request for 60minute interval
    private const ZERODHA_MAX_DAYS_PER_REQUEST = 60;

    // NSE market hours (IST)
    private const MARKET_OPEN_HOUR   = 9;
    private const MARKET_OPEN_MINUTE = 15;
    private const MARKET_CLOSE_HOUR  = 15;
    private const MARKET_CLOSE_MINUTE = 30;

    protected $signature = 'mf:collect-ohlc-1hr
                            {--mode=live       : "live" = today only | "historical" = date range}
                            {--from=           : Start date Y-m-d (required for historical)}
                            {--to=             : End date Y-m-d (default: yesterday)}
                            {--symbol=         : Limit to one symbol e.g. HDFCBANK}
                            {--concurrency=3   : Parallel requests (lower for 1hr — more data per request)}
                            {--retry=3         : Sequential retries on parallel failure}
                            {--retry-delay=3   : Seconds between retries}
                            {--chunk=1000      : DB upsert batch size}';

    protected $description = 'Collect 1-hour OHLC candles for all unique MF stocks (Zerodha 60minute interval)';

    private array                $instrumentCache = [];
    private ?BrokerZerodhaHelper $zerodhaHelper   = null;
    private ?BrokerApi           $broker          = null;

    private const INDEX_ALIAS_MAP = [
        'NIFTY'      => 'NIFTY 50',
        'BANKNIFTY'  => 'NIFTY BANK',
        'FINNIFTY'   => 'NIFTY FIN SERVICE',
        'MIDCPNIFTY' => 'NIFTY MID SELECT',
        'INDIAVIX'   => 'INDIA VIX',
        'SENSEX'     => 'SENSEX',
        'BANKEX'     => 'BANKEX',
    ];

    // ══════════════════════════════════════════════════════════════════════
    // ENTRY
    // ══════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $mode        = strtolower($this->option('mode'));
        $specSymbol  = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $maxRetries  = (int) $this->option('retry');
        $retryDelay  = (int) $this->option('retry-delay');
        $chunkSize   = (int) $this->option('chunk');
        $concurrency = max(1, (int) $this->option('concurrency'));

        if (! in_array($mode, ['live', 'historical'])) {
            $this->error("❌ Invalid --mode. Use 'live' or 'historical'.");
            return 1;
        }

        $this->printHeader($mode, $concurrency);

        // ── Broker ─────────────────────────────────────────────────────────
        $this->broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()
            ->validToken()
            ->first();

        if (! $this->broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token expired!");
            return 1;
        }

        $this->info("🔑 Broker : {$this->broker->client_name} (ID: {$this->broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($this->broker);

        // ── Unique symbols across all active funds ─────────────────────────
        $query = MutualFundStock::active()
            ->whereHas('fund', fn($q) => $q->active())
            ->select('id', 'mutual_fund_id', 'stock_symbol', 'stock_name')
            ->orderBy('stock_symbol');

        if ($specSymbol) {
            $query->where('stock_symbol', $specSymbol);
        }

        $uniqueSymbols = $query->get()->unique('stock_symbol')->values();

        if ($uniqueSymbols->isEmpty()) {
            $this->error('❌ No active stocks found across active mutual funds!');
            return 1;
        }

        $this->info("   Unique symbols to track : " . $uniqueSymbols->count());
        $this->newLine();

        if ($mode === 'live') {
            return $this->runLiveMode($uniqueSymbols, $maxRetries, $retryDelay, $chunkSize, $concurrency);
        }

        return $this->runHistoricalMode($uniqueSymbols, $maxRetries, $retryDelay, $chunkSize, $concurrency);
    }

    // ══════════════════════════════════════════════════════════════════════
    // LIVE MODE — collect today's 1hr candles
    // ══════════════════════════════════════════════════════════════════════

    private function runLiveMode($uniqueSymbols, int $maxRetries, int $retryDelay, int $chunkSize, int $concurrency): int
    {
        $today    = Carbon::today('Asia/Kolkata');
        $todayStr = $today->toDateString();

        if ($today->isWeekend()) {
            $this->warn("⏭  Weekend — nothing to collect.");
            return 0;
        }
        if ($this->isMarketHoliday($todayStr)) {
            $this->warn("⏭  Market holiday on {$todayStr} — nothing to collect.");
            return 0;
        }

        $this->info("📅 Live mode — collecting 1hr candles for: {$todayStr}");
        $this->newLine();

        // Symbols that already have ALL today's candles stored
        // A full trading day = 6 candles (09:15, 10:15, 11:15, 12:15, 13:15, 14:15)
        // We just check if at least 1 candle exists for today — re-fetch will upsert/update
        $alreadyStored = MutualFundStockOhlc1hr::where('trade_date', $todayStr)
            ->where('is_missing', false)
            ->pluck('symbol')
            ->unique()
            ->flip()
            ->toArray();

        $workItems = [];
        $skipped   = 0;

        foreach ($uniqueSymbols as $stock) {
            $symbol = $stock->stock_symbol;

            // For live mode we always re-fetch today to catch the latest candle
            // (market may still be open) — skip only if market is closed and data is complete
            $now         = Carbon::now('Asia/Kolkata');
            $marketClose = $today->copy()->setTime(self::MARKET_CLOSE_HOUR, self::MARKET_CLOSE_MINUTE);

            if (isset($alreadyStored[$symbol]) && $now->gt($marketClose)) {
                $this->line("   ✓ {$symbol} market closed + already stored — skipped");
                $skipped++;
                continue;
            }

            $instrument = $this->resolveInstrument($symbol, 'NSE');

            if (! $instrument) {
                $this->warn("   ⚠️  {$symbol} — instrument not found, skipping");
                continue;
            }

            $workItems[] = [
                'symbol'     => $symbol,
                'instrument' => $instrument,
                'from'       => $today->copy()->setTime(self::MARKET_OPEN_HOUR, self::MARKET_OPEN_MINUTE),
                'to'         => $today->copy()->setTime(self::MARKET_CLOSE_HOUR, self::MARKET_CLOSE_MINUTE),
            ];
        }

        $this->info("   To fetch : " . count($workItems) . " | Skipped : {$skipped}");
        $this->newLine();

        if (empty($workItems)) {
            $this->info("   All symbols already up to date.");
            $this->printSummary('Live', $todayStr, $todayStr, 0, $skipped, 0);
            return 0;
        }

        [$inserted, $failed] = $this->parallelFetch(
            $workItems, $maxRetries, $retryDelay, $concurrency, $chunkSize
        );

        $this->printSummary('Live', $todayStr, $todayStr, $inserted, $skipped, $failed);
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    // HISTORICAL MODE — chunked into 60-day windows per Zerodha limit
    // ══════════════════════════════════════════════════════════════════════

    private function runHistoricalMode($uniqueSymbols, int $maxRetries, int $retryDelay, int $chunkSize, int $concurrency): int
    {
        $fromStr = $this->option('from');
        $toStr   = $this->option('to') ?: Carbon::yesterday('Asia/Kolkata')->toDateString();

        if (! $fromStr) {
            $this->error("❌ --from=YYYY-MM-DD is required for historical mode.");
            $this->line("   php artisan mf:collect-ohlc-1hr --mode=historical --from=2024-01-01");
            return 1;
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $fromStr)->startOfDay();
            $toDate   = Carbon::createFromFormat('Y-m-d', $toStr)->startOfDay();
        } catch (Exception $e) {
            $this->error("❌ Invalid date format. Use Y-m-d.");
            return 1;
        }

        if ($fromDate->gt($toDate)) {
            $this->error("❌ --from must be ≤ --to.");
            return 1;
        }

        $this->info("📅 Historical (1hr) — {$fromDate->toDateString()} → {$toDate->toDateString()}");
        $this->info("   ⚠️  Note: Zerodha allows max " . self::ZERODHA_MAX_DAYS_PER_REQUEST . " days per 1hr request — auto-chunking enabled.");
        $this->newLine();

        // Latest stored candle_time per symbol — for gap-fill
        $latestBySymbol = MutualFundStockOhlc1hr::where('is_missing', false)
            ->groupBy('symbol')
            ->selectRaw('symbol, MAX(candle_time) as latest_candle')
            ->pluck('latest_candle', 'symbol')
            ->toArray();

        $totalInserted = 0;
        $totalFailed   = 0;
        $skippedAll    = 0;

        foreach ($uniqueSymbols as $stock) {
            $symbol = $stock->stock_symbol;

            // Smart gap-fill: start from next candle after last stored
            $effectiveFrom = $fromDate->copy()->setTime(self::MARKET_OPEN_HOUR, self::MARKET_OPEN_MINUTE);

            if (isset($latestBySymbol[$symbol])) {
                $lastCandle    = Carbon::parse($latestBySymbol[$symbol]);
                $effectiveFrom = $lastCandle->copy()->addHour(); // next 1hr candle
            }

            $effectiveTo = $toDate->copy()->setTime(self::MARKET_CLOSE_HOUR, self::MARKET_CLOSE_MINUTE);

            if ($effectiveFrom->gt($effectiveTo)) {
                $this->line("   ✓ {$symbol} — fully up to date");
                $skippedAll++;
                continue;
            }

            $instrument = $this->resolveInstrument($symbol, 'NSE');

            if (! $instrument) {
                $this->warn("   ⚠️  {$symbol} — instrument not found, skipping");
                continue;
            }

            // ── Split into 60-day chunks ──────────────────────────────────
            $chunks    = $this->buildDateChunks($effectiveFrom, $effectiveTo);
            $storedUpTo = $latestBySymbol[$symbol] ?? 'none';

            $this->line("   📌 {$symbol} [{$instrument->exchange}:{$instrument->instrument_type}]"
                . " stored up to: {$storedUpTo}"
                . " → " . count($chunks) . " chunk(s)");

            // Build work items for this symbol's chunks
            $workItems = [];
            foreach ($chunks as $chunk) {
                $workItems[] = [
                    'symbol'     => $symbol,
                    'instrument' => $instrument,
                    'from'       => $chunk['from'],
                    'to'         => $chunk['to'],
                ];
            }

            // Fetch all chunks for this symbol (parallel within chunks)
            [$inserted, $failed] = $this->parallelFetch(
                $workItems, $maxRetries, $retryDelay, $concurrency, $chunkSize
            );

            $totalInserted += $inserted;
            $totalFailed   += $failed;
        }

        $this->printSummary('Historical', $fromDate->toDateString(), $toDate->toDateString(),
            $totalInserted, $skippedAll, $totalFailed);

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PARALLEL FETCH — Guzzle Pool
    // ══════════════════════════════════════════════════════════════════════

    private function parallelFetch(
        array $workItems,
        int   $maxRetries,
        int   $retryDelay,
        int   $concurrency,
        int   $chunkSize
    ): array {
        $now           = now()->toDateTimeString();
        $allRows       = [];
        $failedCount   = 0;
        $totalUpserted = 0;

        $client = new Client([
            'base_uri' => self::KITE_API_BASE,
            'timeout'  => 30,
            'headers'  => [
                'X-Kite-Version' => '3',
                'Authorization'  => 'token ' . $this->broker->api_key . ':' . $this->broker->access_token,
            ],
        ]);

        $rawResults = [];

        $requests = function () use ($workItems) {
            foreach ($workItems as $i => $item) {
                $token = $item['instrument']->instrument_token;

                // 60minute = 1-hour candles in Zerodha API
                $uri = "/instruments/historical/{$token}/60minute"
                    . '?from=' . urlencode($item['from']->format('Y-m-d H:i:s'))
                    . '&to='   . urlencode($item['to']->format('Y-m-d H:i:s'))
                    . '&oi=1';

                yield $i => new Request('GET', $uri);
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,

            'fulfilled' => function ($response, $i) use (&$rawResults) {
                try {
                    $body           = json_decode($response->getBody()->getContents(), true);
                    $rawResults[$i] = ['candles' => $this->parseCandles($body['data']['candles'] ?? [])];
                } catch (Exception $e) {
                    $rawResults[$i] = ['error' => 'Parse error: ' . $e->getMessage()];
                }
            },

            'rejected' => function ($reason, $i) use (&$rawResults) {
                $rawResults[$i] = ['error' => $reason instanceof RequestException
                    ? $reason->getMessage()
                    : (string) $reason];
            },
        ]);

        $pool->promise()->wait();

        // ── Process results ─────────────────────────────────────────────────
        foreach ($workItems as $i => $item) {
            $symbol     = $item['symbol'];
            $instrument = $item['instrument'];
            $itemFrom   = $item['from'];
            $itemTo     = $item['to'];

            $result = $rawResults[$i] ?? ['error' => 'Response not captured'];

            // Sequential retry on failure
            if (isset($result['error'])) {
                $this->warn("   ⚠️  {$symbol} [{$itemFrom->toDateString()}] parallel fail: {$result['error']} — retrying...");

                $retryCandles = $this->fetchSequential(
                    $instrument->instrument_token, $itemFrom, $itemTo, $maxRetries, $retryDelay
                );

                if (empty($retryCandles)) {
                    $this->error("   ✗ {$symbol} — failed after {$maxRetries} retries");
                    Log::error("MF-OHLC-1HR: Final failure for {$symbol} {$itemFrom} → {$itemTo}");
                    $failedCount++;
                    continue;
                }

                $result = ['candles' => $retryCandles];
            }

            $candleCount = count($result['candles']);

            if ($candleCount > 0) {
                $this->info("   ✅ {$symbol} [{$instrument->exchange}] "
                    . "[{$itemFrom->toDateString()} → {$itemTo->toDateString()}]"
                    . " — {$candleCount} candle(s)");
            }

            foreach ($result['candles'] as $candle) {
                $candleTime = Carbon::parse($candle->date, 'Asia/Kolkata');
                $allRows[]  = $this->buildRow($symbol, $instrument, $candle, $candleTime, $now);
            }

            // Memory flush
            if (count($allRows) >= 2000) {
                $totalUpserted += $this->batchUpsert($allRows, $chunkSize);
                $allRows = [];
                $this->line("   💾 Flushed 2000 rows to DB");
            }
        }

        if (! empty($allRows)) {
            $totalUpserted += $this->batchUpsert($allRows, $chunkSize);
        }

        return [$totalUpserted, $failedCount];
    }

    // ══════════════════════════════════════════════════════════════════════
    // SEQUENTIAL FALLBACK
    // ══════════════════════════════════════════════════════════════════════

    private function fetchSequential(int $token, Carbon $from, Carbon $to, int $maxRetries, int $retryDelay): array
    {
        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                return $this->zerodhaHelper->getHistoricalDataByToken(
                    $token, '60minute',
                    $from->format('Y-m-d H:i:s'),
                    $to->format('Y-m-d H:i:s')
                ) ?? [];
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Retry {$attempt}/{$maxRetries}: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    Log::error("MF-OHLC-1HR: Sequential exhausted for token {$token}: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    // ══════════════════════════════════════════════════════════════════════
    // BUILD 60-DAY CHUNKS
    // Zerodha max allowed window for 60minute = 60 days per request
    // ══════════════════════════════════════════════════════════════════════

    private function buildDateChunks(Carbon $from, Carbon $to): array
    {
        $chunks  = [];
        $current = $from->copy();

        while ($current->lte($to)) {
            $chunkEnd = $current->copy()->addDays(self::ZERODHA_MAX_DAYS_PER_REQUEST - 1)
                ->setTime(self::MARKET_CLOSE_HOUR, self::MARKET_CLOSE_MINUTE);

            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy();
            }

            $chunks[] = [
                'from' => $current->copy(),
                'to'   => $chunkEnd->copy(),
            ];

            $current = $chunkEnd->copy()->addDay()
                ->setTime(self::MARKET_OPEN_HOUR, self::MARKET_OPEN_MINUTE);
        }

        return $chunks;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PARSE — [date, open, high, low, close, volume, oi(ignored)]
    // ══════════════════════════════════════════════════════════════════════

    private function parseCandles(array $raw): array
    {
        return array_map(function ($c) {
            $o         = new \stdClass();
            $o->date   = $c[0] ?? null;
            $o->open   = (float) ($c[1] ?? 0);
            $o->high   = (float) ($c[2] ?? 0);
            $o->low    = (float) ($c[3] ?? 0);
            $o->close  = (float) ($c[4] ?? 0);
            $o->volume = (int)   ($c[5] ?? 0);
            // $c[6] = oi — always 0 for equities, discarded
            return $o;
        }, $raw);
    }

    // ══════════════════════════════════════════════════════════════════════
    // INSTRUMENT RESOLUTION
    // ══════════════════════════════════════════════════════════════════════

    private function resolveInstrument(string $symbol, string $exchange): ?ZerodhaInstrument
    {
        $cacheKey = "{$symbol}_{$exchange}";

        if (array_key_exists($cacheKey, $this->instrumentCache)) {
            return $this->instrumentCache[$cacheKey];
        }

        // Pass 1 — EQ / BE
        $inst = ZerodhaInstrument::where('exchange', $exchange)
            ->whereIn('instrument_type', ['EQ', 'BE'])
            ->where('trading_symbol', $symbol)
            ->orderByRaw("FIELD(instrument_type, 'EQ', 'BE')")
            ->first();

        if ($inst) return $this->cache($cacheKey, $inst);

        // Pass 2 — INDEX alias
        if ($alias = self::INDEX_ALIAS_MAP[$symbol] ?? null) {
            $inst = ZerodhaInstrument::where('exchange', $exchange)
                ->whereIn('instrument_type', ['INDEX', 'INDICES'])
                ->where(fn($q) => $q->where('trading_symbol', $alias)
                    ->orWhere('name', $alias)
                    ->orWhere('trading_symbol', $symbol)
                    ->orWhere('name', $symbol))
                ->first();

            if ($inst) return $this->cache($cacheKey, $inst);
        }

        // Pass 3 — Cross-exchange fallback
        $other = $exchange === 'NSE' ? 'BSE' : 'NSE';
        $inst  = ZerodhaInstrument::where('exchange', $other)
            ->whereIn('instrument_type', ['EQ', 'BE'])
            ->where('trading_symbol', $symbol)
            ->first();

        if ($inst) {
            $this->warn("   ⚠️  {$symbol} resolved on {$other} (tried {$exchange})");
            return $this->cache($cacheKey, $inst);
        }

        Log::warning("MF-OHLC-1HR: Instrument not found for [{$symbol}].");
        $this->instrumentCache[$cacheKey] = null;
        return null;
    }

    private function cache(string $key, ZerodhaInstrument $inst): ZerodhaInstrument
    {
        $this->instrumentCache[$key] = $inst;
        return $inst;
    }

    // ══════════════════════════════════════════════════════════════════════
    // ROW BUILDER
    // ══════════════════════════════════════════════════════════════════════

    private function buildRow(
        string            $symbol,
        ZerodhaInstrument $inst,
        object            $candle,
        Carbon            $candleTime,
        string            $now
    ): array {
        return [
            'symbol'           => $symbol,
            'exchange'         => $inst->exchange,
            'instrument_token' => $inst->instrument_token,
            'trading_symbol'   => $inst->trading_symbol,
            'trade_date'       => $candleTime->toDateString(),
            'candle_time'      => $candleTime->format('Y-m-d H:i:s'),
            'open'             => $candle->open,
            'high'             => $candle->high,
            'low'              => $candle->low,
            'close'            => $candle->close,
            'volume'           => $candle->volume,
            'is_missing'       => 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // BATCH UPSERT
    // ══════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            MutualFundStockOhlc1hr::upsert(
                $chunk,
                ['symbol', 'exchange', 'candle_time'],   // unique key
                ['instrument_token', 'trading_symbol', 'trade_date',
                 'open', 'high', 'low', 'close', 'volume', 'is_missing', 'updated_at']
            );
            $total += count($chunk);
        }
        return $total;
    }

    // ══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════

    private function isMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    private function printHeader(string $mode, int $concurrency): void
    {
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->info("  📊 MF Stock 1Hr OHLC Collector  |  Mode: " . strtoupper($mode));
        $this->info("  Concurrency: {$concurrency}  |  " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->newLine();
    }

    private function printSummary(string $mode, string $from, string $to, int $inserted, int $skipped, int $failed): void
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->info("  ✅ {$mode} complete — " . Carbon::now()->format('H:i:s'));
        $this->info("     Range    : {$from} → {$to}");
        $this->info("     Upserted : {$inserted} row(s)");
        $this->info("     Skipped  : {$skipped} (already up to date)");
        $this->info("     Failed   : {$failed} (not found / API error)");
        $this->info("═══════════════════════════════════════════════════════════════");
    }
}