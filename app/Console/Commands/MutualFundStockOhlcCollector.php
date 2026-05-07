<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MutualFundStock;
use App\Models\MutualFundStockOhlc;
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

class MutualFundStockOhlcCollector extends Command
{
    private const BROKER_CLIENT_ID = 'ZZL808';
    private const KITE_API_BASE    = 'https://api.kite.trade';

    protected $signature = 'mf:collect-ohlc
                            {--mode=live       : "live" = today only | "historical" = date range}
                            {--from=           : Start date Y-m-d (required for historical)}
                            {--to=             : End date Y-m-d (default: yesterday)}
                            {--symbol=         : Limit to one symbol e.g. HDFCBANK}
                            {--concurrency=5   : Parallel requests (Zerodha safe ≈ 3–6)}
                            {--retry=3         : Sequential retries on parallel failure}
                            {--retry-delay=2   : Seconds between retries}
                            {--chunk=500       : DB upsert batch size}';

    protected $description = 'Collect daily OHLC + Volume for all unique MF stocks (deduped across funds)';

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

        // ── Unique symbols across ALL active funds ─────────────────────────
        // Step 1: get all active funds
        // Step 2: get all their active stocks
        // Step 3: deduplicate by stock_symbol → only 1 API call + 1 DB row per symbol per day
        $query = MutualFundStock::active()
            ->whereHas('fund', fn($q) => $q->active())
            ->select('id', 'mutual_fund_id', 'stock_symbol', 'stock_name')
            ->orderBy('stock_symbol');

        if ($specSymbol) {
            $query->where('stock_symbol', $specSymbol);
        }

        // Deduplicate — same stock in 2 funds = 1 entry
        $uniqueSymbols = $query->get()
            ->unique('stock_symbol')
            ->values();

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
    // LIVE MODE
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

        $this->info("📅 Live mode — collecting for: {$todayStr}");
        $this->newLine();

        // Symbols already stored today
        $alreadyStored = MutualFundStockOhlc::where('trade_date', $todayStr)
            ->where('is_missing', false)
            ->pluck('symbol')
            ->flip()
            ->toArray();

        $workItems = [];
        $skipped   = 0;

        foreach ($uniqueSymbols as $stock) {
            $symbol = $stock->stock_symbol;

            if (isset($alreadyStored[$symbol])) {
                $this->line("   ✓ {$symbol} already stored today — skipped");
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
                'from'       => $today->copy()->setTime(0, 0),
                'to'         => $today->copy()->setTime(23, 59),
            ];
        }

        $this->info("   To fetch : " . count($workItems) . " | Skipped (already stored) : {$skipped}");
        $this->newLine();

        if (empty($workItems)) {
            $this->info("   All symbols already up to date.");
            $this->printSummary('Live', $todayStr, $todayStr, 0, $skipped, 0);
            return 0;
        }

        [$inserted, $failed] = $this->parallelFetch(
            $workItems, $todayStr, $maxRetries, $retryDelay, $concurrency, [], $chunkSize
        );

        $this->printSummary('Live', $todayStr, $todayStr, $inserted, $skipped, $failed);
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    // HISTORICAL MODE
    // ══════════════════════════════════════════════════════════════════════

    private function runHistoricalMode($uniqueSymbols, int $maxRetries, int $retryDelay, int $chunkSize, int $concurrency): int
    {
        $fromStr = $this->option('from');
        $toStr   = $this->option('to') ?: Carbon::yesterday('Asia/Kolkata')->toDateString();

        if (! $fromStr) {
            $this->error("❌ --from=YYYY-MM-DD is required for historical mode.");
            $this->line("   php artisan mf:collect-ohlc --mode=historical --from=2024-01-01");
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

        $this->info("📅 Historical — {$fromDate->toDateString()} → {$toDate->toDateString()}");
        $this->newLine();

        // Latest stored date per symbol — for smart gap-fill
        $latestBySymbol = MutualFundStockOhlc::where('is_missing', false)
            ->whereBetween('trade_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->groupBy('symbol')
            ->selectRaw('symbol, MAX(trade_date) as latest_date')
            ->pluck('latest_date', 'symbol')
            ->toArray();

        $workItems  = [];
        $skippedAll = 0;

        foreach ($uniqueSymbols as $stock) {
            $symbol = $stock->stock_symbol;

            // Start from day after last stored (gap-fill logic)
            $effectiveFrom = $fromDate->copy();
            if (isset($latestBySymbol[$symbol])) {
                $effectiveFrom = Carbon::parse($latestBySymbol[$symbol])->addDay();
            }

            if ($effectiveFrom->gt($toDate)) {
                $this->line("   ✓ {$symbol} — up to date (latest: {$latestBySymbol[$symbol]})");
                $skippedAll++;
                continue;
            }

            $instrument = $this->resolveInstrument($symbol, 'NSE');

            if (! $instrument) {
                $this->warn("   ⚠️  {$symbol} — instrument not found, skipping");
                continue;
            }

            $storedUpTo = $latestBySymbol[$symbol] ?? 'none';
            $this->line("   📌 {$symbol} [{$instrument->exchange}:{$instrument->instrument_type}]"
                . " stored up to: {$storedUpTo}"
                . " → fetch: {$effectiveFrom->toDateString()} → {$toDate->toDateString()}");

            $workItems[] = [
                'symbol'     => $symbol,
                'instrument' => $instrument,
                'from'       => $effectiveFrom->copy()->setTime(0, 0),
                'to'         => $toDate->copy()->setTime(23, 59),
            ];
        }

        $this->newLine();
        $this->info("   To fetch          : " . count($workItems) . " symbol(s)");
        $this->info("   Already up to date: {$skippedAll} symbol(s)");
        $this->newLine();

        if (empty($workItems)) {
            $this->printSummary('Historical', $fromDate->toDateString(), $toDate->toDateString(), 0, $skippedAll, 0);
            return 0;
        }

        $tradingDays = $this->getTradingDays($fromDate, $toDate);
        $this->info("   Trading days in range: " . count($tradingDays));
        $this->newLine();

        [$inserted, $failed] = $this->parallelFetch(
            $workItems, null, $maxRetries, $retryDelay, $concurrency, $tradingDays, $chunkSize
        );

        $this->printSummary('Historical', $fromDate->toDateString(), $toDate->toDateString(), $inserted, $skippedAll, $failed);
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    // PARALLEL FETCH
    // ══════════════════════════════════════════════════════════════════════

    private function parallelFetch(
        array   $workItems,
        ?string $tradeDateOverride,
        int     $maxRetries,
        int     $retryDelay,
        int     $concurrency,
        array   $allTradingDays,
        int     $chunkSize
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
                $uri   = "/instruments/historical/{$token}/day"
                    . '?from=' . urlencode($item['from']->format('Y-m-d H:i:s'))
                    . '&to='   . urlencode($item['to']->format('Y-m-d H:i:s'))
                    . '&oi=1'; // Zerodha always returns oi=0 for equities — we just ignore it

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

        // ── Process each result ─────────────────────────────────────────────
        foreach ($workItems as $i => $item) {
            $symbol     = $item['symbol'];
            $instrument = $item['instrument'];
            $itemFrom   = $item['from'];
            $itemTo     = $item['to'];

            $result = $rawResults[$i] ?? ['error' => 'Response not captured'];

            // Sequential retry on parallel failure
            if (isset($result['error'])) {
                $this->warn("   ⚠️  {$symbol} parallel fail: {$result['error']} — retrying...");
                $retryCandles = $this->fetchSequential(
                    $instrument->instrument_token, $itemFrom, $itemTo, $maxRetries, $retryDelay
                );

                if (empty($retryCandles)) {
                    $this->error("   ✗ {$symbol} — failed after {$maxRetries} retries");
                    Log::error("MF-OHLC: Final failure for {$symbol} {$itemFrom->toDateString()} → {$itemTo->toDateString()}");
                    $failedCount++;
                    continue;
                }

                $result = ['candles' => $retryCandles];
            }

            // Index candles by trade date
            $candlesByDate = [];
            foreach ($result['candles'] as $candle) {
                $candlesByDate[Carbon::parse($candle->date)->toDateString()] = $candle;
            }

            $this->info("   ✅ {$symbol} [{$instrument->exchange}:{$instrument->instrument_type}]"
                . " — " . count($result['candles']) . " candle(s)");

            $daysToStore = $tradeDateOverride !== null
                ? [Carbon::parse($tradeDateOverride)]
                : array_values(array_filter($allTradingDays, fn($d) => $d->between($itemFrom, $itemTo)));

            foreach ($daysToStore as $day) {
                $dateStr = $day->toDateString();
                $candle  = $candlesByDate[$dateStr] ?? null;

                $allRows[] = $candle
                    ? $this->buildRow($symbol, $instrument, $candle, $dateStr, $now)
                    : $this->buildMissingRow($symbol, $instrument, $dateStr, $now);

                if (! $candle) {
                    Log::warning("MF-OHLC: No candle for {$symbol} on {$dateStr}");
                }
            }

            // Memory protection flush
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
                    $token, 'day',
                    $from->format('Y-m-d H:i:s'),
                    $to->format('Y-m-d H:i:s')
                ) ?? [];
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Retry {$attempt}/{$maxRetries}: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    Log::error("MF-OHLC: Sequential exhausted for token {$token}: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    // ══════════════════════════════════════════════════════════════════════
    // PARSE  — [date, open, high, low, close, volume, oi(ignored)]
    // ══════════════════════════════════════════════════════════════════════

    private function parseCandles(array $raw): array
    {
        return array_map(function ($c) {
            $o = new \stdClass();
            $o->date   = $c[0] ?? null;
            $o->open   = (float) ($c[1] ?? 0);
            $o->high   = (float) ($c[2] ?? 0);
            $o->low    = (float) ($c[3] ?? 0);
            $o->close  = (float) ($c[4] ?? 0);
            $o->volume = (int)   ($c[5] ?? 0);
            // $c[6] = oi — always 0 for equities, intentionally discarded
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

        // Pass 1 — EQ / BE on requested exchange
        $inst = ZerodhaInstrument::where('exchange', $exchange)
            ->whereIn('instrument_type', ['EQ', 'BE'])
            ->where('trading_symbol', $symbol)
            ->orderByRaw("FIELD(instrument_type, 'EQ', 'BE')")
            ->first();

        if ($inst) return $this->cache($cacheKey, $inst);

        // Pass 2 — INDEX via alias map
        if ($alias = self::INDEX_ALIAS_MAP[$symbol] ?? null) {
            $inst = ZerodhaInstrument::where('exchange', $exchange)
                ->whereIn('instrument_type', ['INDEX', 'INDICES'])
                ->where(fn($q) => $q->where('trading_symbol', $alias)
                    ->orWhere('trading_symbol', $symbol)
                    ->orWhere('name', $alias)
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

        Log::warning("MF-OHLC: Instrument not found for [{$symbol}]. "
            . "SQL: SELECT trading_symbol,exchange,instrument_type FROM zerodha_instruments "
            . "WHERE trading_symbol='{$symbol}' OR name LIKE '%{$symbol}%' LIMIT 10;");

        $this->instrumentCache[$cacheKey] = null;
        return null;
    }

    private function cache(string $key, ZerodhaInstrument $inst): ZerodhaInstrument
    {
        $this->instrumentCache[$key] = $inst;
        return $inst;
    }

    // ══════════════════════════════════════════════════════════════════════
    // ROW BUILDERS
    // ══════════════════════════════════════════════════════════════════════

    private function buildRow(string $symbol, ZerodhaInstrument $inst, object $candle, string $date, string $now): array
    {
        return [
            'symbol'           => $symbol,
            'exchange'         => $inst->exchange,
            'instrument_token' => $inst->instrument_token,
            'trading_symbol'   => $inst->trading_symbol,
            'trade_date'       => $date,
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

    private function buildMissingRow(string $symbol, ZerodhaInstrument $inst, string $date, string $now): array
    {
        return [
            'symbol'           => $symbol,
            'exchange'         => $inst->exchange,
            'instrument_token' => $inst->instrument_token,
            'trading_symbol'   => $inst->trading_symbol,
            'trade_date'       => $date,
            'open' => 0, 'high' => 0, 'low' => 0, 'close' => 0, 'volume' => 0,
            'is_missing'       => 1,
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
            MutualFundStockOhlc::upsert(
                $chunk,
                ['symbol', 'exchange', 'trade_date'],
                ['instrument_token', 'trading_symbol', 'open', 'high', 'low', 'close', 'volume', 'is_missing', 'updated_at']
            );
            $total += count($chunk);
        }
        return $total;
    }

    // ══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════

    private function getTradingDays(Carbon $from, Carbon $to): array
    {
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
        $this->info("  📊 MF Stock OHLC Collector  |  Mode: " . strtoupper($mode));
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