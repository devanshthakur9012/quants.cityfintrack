<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockDailyOhlcSymbol;
use App\Models\StockDailyOhlcData;
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

class StockDailyOhlcCollector extends Command
{
    private const BROKER_CLIENT_ID = 'DB0542';
    private const KITE_API_BASE = 'https://api.kite.trade';
    protected $signature = 'stocks:collect-daily-ohlc
                            {--mode=live             : "live" = today only | "historical" = date range}
                            {--from=                 : Start date Y-m-d (required for historical)}
                            {--to=                   : End date Y-m-d (default: yesterday)}
                            {--symbol=               : Limit to one symbol e.g. RELIANCE}
                            {--concurrency=5         : Parallel API requests (Zerodha safe limit ≈ 3–6)}
                            {--retry=3               : Sequential retries on parallel failure}
                            {--retry-delay=2         : Seconds between sequential retries}
                            {--chunk=500             : Upsert batch size (higher = faster writes)}';

    protected $description = 'EOD OHLC for stocks/indices — parallel fetch, smart gap-fill, EQ + INDEX support';

    private array             $instrumentCache = [];  // "{SYMBOL}_{EXCHANGE}" → ZerodhaInstrument|null
    private ?BrokerZerodhaHelper $zerodhaHelper = null;
    private ?BrokerApi           $broker        = null;

    public function handle(): int
    {
        $mode        = strtolower($this->option('mode'));
        $specSymbol  = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $maxRetries  = (int) $this->option('retry');
        $retryDelay  = (int) $this->option('retry-delay');
        $chunkSize   = (int) $this->option('chunk');
        $concurrency = max(1, (int) $this->option('concurrency'));

        if (!in_array($mode, ['live', 'historical'])) {
            $this->error("❌ Invalid --mode. Use 'live' or 'historical'.");
            return 1;
        }

        $this->info("═══════════════════════════════════════════════════════════════");
        $this->info("  📈 Stock Daily OHLC Collector v2  |  Mode: " . strtoupper($mode));
        $this->info("  Broker: " . self::BROKER_CLIENT_ID
            . "  |  Concurrency: {$concurrency}"
            . "  |  " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->newLine();

        // ── Broker ────────────────────────────────────────────────────────────
        $this->broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()
            ->validToken()
            ->first();

        if (!$this->broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid!");
            return 1;
        }

        $this->info("🔑 Broker : {$this->broker->client_name} (ID: {$this->broker->id})");
        $this->zerodhaHelper = new BrokerZerodhaHelper($this->broker);

        // ── Symbols ───────────────────────────────────────────────────────────
        $symbolsQuery = StockDailyOhlcSymbol::active();
        if ($specSymbol) {
            $symbolsQuery->where('symbol', $specSymbol);
        }
        $symbolRecords = $symbolsQuery->orderBy('symbol')->get();

        if ($symbolRecords->isEmpty()) {
            $this->error('❌ No active symbols in stock_daily_ohlc_symbols table!');
            return 1;
        }

        $this->info("   Symbols (" . $symbolRecords->count() . "): "
            . $symbolRecords->pluck('symbol')->implode(', '));
        $this->newLine();

        if ($mode === 'live') {
            return $this->runLiveMode($symbolRecords, $maxRetries, $retryDelay, $chunkSize, $concurrency);
        }

        return $this->runHistoricalMode($symbolRecords, $maxRetries, $retryDelay, $chunkSize, $concurrency);
    }

    private function runLiveMode(
        $symbolRecords,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize,
        int $concurrency
    ): int {
        $today    = Carbon::today('Asia/Kolkata');
        $todayStr = $today->toDateString();

        if ($today->isWeekend()) {
            $this->warn("⏭  Weekend — nothing to collect.");
            return 0;
        }
        if ($this->isMarketHoliday($todayStr)) {
            $this->warn("⏭  Market holiday — nothing to collect.");
            return 0;
        }

        $this->info("📅 Live mode — collecting for: {$todayStr}");
        $this->newLine();

        // Single DB query for all already-stored symbols today
        $alreadyStored = StockDailyOhlcData::where('broker_api_id', $this->broker->id)
            ->where('trade_date', $todayStr)
            ->where('is_missing', 0)
            ->pluck('symbol')
            ->flip()
            ->toArray();

        $workItems = [];
        $skipped   = 0;

        foreach ($symbolRecords as $symbolRecord) {
            $symbol = $symbolRecord->symbol;

            if (isset($alreadyStored[$symbol])) {
                $this->line("   ✓ {$symbol} already stored today — skipped");
                $skipped++;
                continue;
            }

            $instrument = $this->resolveInstrument($symbol, $symbolRecord->exchange);

            if (!$instrument) {
                $this->warn("   ⚠️  {$symbol} — no EQ/INDEX instrument found, skipping");
                Log::warning("StockDailyOhlc: Instrument not found for {$symbol} [{$symbolRecord->exchange}]");
                continue;
            }

            $workItems[] = [
                'symbolRecord' => $symbolRecord,
                'instrument'   => $instrument,
                'from'         => $today->copy()->setTime(0, 0),
                'to'           => $today->copy()->setTime(23, 59),
            ];
        }

        $this->info("   To fetch: " . count($workItems) . " | Skipped: {$skipped}");
        $this->newLine();

        if (empty($workItems)) {
            $this->info("   All symbols already up to date.");
            $this->printSummary('Live', $todayStr, $todayStr, 0, $skipped, 0);
            return 0;
        }

        // Live mode: pass $tradeDateOverride = today so every candle is stored as today
        [$inserted, $failedCount] = $this->parallelFetch(
            $workItems, $todayStr, $maxRetries, $retryDelay, $concurrency, [], $chunkSize
        );

        $this->printSummary('Live', $todayStr, $todayStr, $inserted, $skipped, $failedCount);

        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // HISTORICAL MODE
    // ═════════════════════════════════════════════════════════════════════════

    private function runHistoricalMode(
        $symbolRecords,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize,
        int $concurrency
    ): int {
        $fromStr = $this->option('from');
        $toStr   = $this->option('to') ?: Carbon::yesterday('Asia/Kolkata')->toDateString();

        if (!$fromStr) {
            $this->error("❌ --from=YYYY-MM-DD is required for historical mode.");
            $this->line("   Example: php artisan stocks:collect-daily-ohlc --mode=historical --from=2024-01-01");
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

        $this->info("📅 Historical — requested: {$fromDate->toDateString()} → {$toDate->toDateString()}");
        $this->newLine();

        $latestBySymbol = StockDailyOhlcData::where('broker_api_id', $this->broker->id)
            ->where('is_missing', 0)
            ->whereBetween('trade_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->groupBy('symbol')
            ->selectRaw('symbol, MAX(trade_date) as latest_date')
            ->pluck('latest_date', 'symbol')
            ->toArray();

        // ── Build per-symbol work items ───────────────────────────────────────
        $workItems  = [];
        $skippedAll = 0;

        foreach ($symbolRecords as $symbolRecord) {
            $symbol = $symbolRecord->symbol;

            // Effective start = day after last stored, clamped to global $fromDate
            $effectiveFrom = $fromDate->copy();
            if (isset($latestBySymbol[$symbol])) {
                $lastStored    = Carbon::parse($latestBySymbol[$symbol]);
                $effectiveFrom = $lastStored->copy()->addDay();
            }

            if ($effectiveFrom->gt($toDate)) {
                $this->line("   ✓ {$symbol} — fully up to date (latest: {$latestBySymbol[$symbol]})");
                $skippedAll++;
                continue;
            }

            $instrument = $this->resolveInstrument($symbol, $symbolRecord->exchange);

            if (!$instrument) {
                $this->warn("   ⚠️  {$symbol} — no EQ/INDEX instrument found, skipping");
                Log::warning("StockDailyOhlc: Instrument not found for {$symbol} [{$symbolRecord->exchange}]");
                continue;
            }

            $storedUpTo = $latestBySymbol[$symbol] ?? 'none';
            $this->line("   📌 {$symbol} [{$symbolRecord->exchange}:{$instrument->instrument_type}]"
                . " stored up to: {$storedUpTo}"
                . " → fetch: {$effectiveFrom->toDateString()} → {$toDate->toDateString()}");

            $workItems[] = [
                'symbolRecord' => $symbolRecord,
                'instrument'   => $instrument,
                'from'         => $effectiveFrom->copy()->setTime(0, 0),
                'to'           => $toDate->copy()->setTime(23, 59),
            ];
        }

        $this->newLine();
        $this->info("   To fetch        : " . count($workItems) . " symbol(s)");
        $this->info("   Already up to date: {$skippedAll} symbol(s)");
        $this->newLine();

        if (empty($workItems)) {
            $this->info("   All symbols already up to date.");
            $this->printSummary('Historical', $fromDate->toDateString(), $toDate->toDateString(), 0, $skippedAll, 0);
            return 0;
        }

        // Trading days list used for missing-candle detection
        $tradingDays = $this->getTradingDays($fromDate, $toDate);
        $this->info("   Trading days in full range: " . count($tradingDays));
        $this->newLine();

        // Historical mode: $tradeDateOverride = null so each candle uses its own date
        [$inserted, $failedCount] = $this->parallelFetch(
            $workItems, null, $maxRetries, $retryDelay, $concurrency, $tradingDays, $chunkSize
        );

        $this->printSummary('Historical', $fromDate->toDateString(), $toDate->toDateString(),
            $inserted, $skippedAll, $failedCount);

        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PARALLEL FETCH  ← core of v2
    // ═════════════════════════════════════════════════════════════════════════
    private function parallelFetch(
        array   $workItems,
        ?string $tradeDateOverride,
        int     $maxRetries,
        int     $retryDelay,
        int     $concurrency,
        array   $allTradingDays,
        int     $chunkSize = 500
    ): array {
        $now         = now()->toDateTimeString();
        $allRows     = [];
        $failedCount = 0;
        $totalUpserted = 0;   // Fix 5: track flushed rows so final return is accurate

        // Guzzle client with Zerodha auth — reused across all pool requests
        $client = new Client([
            'base_uri' => self::KITE_API_BASE,
            'timeout'  => 30,
            'headers'  => [
                'X-Kite-Version' => '3',
                'Authorization'  => 'token '
                    . $this->broker->api_key . ':'
                    . $this->broker->access_token,
            ],
        ]);

        // Raw API results keyed by work-item index — populated in callbacks
        $rawResults = [];

        // ── Build lazy request generator for the Pool ─────────────────────────
        $requests = function () use ($workItems) {
            foreach ($workItems as $i => $item) {
                $token   = $item['instrument']->instrument_token;
                $fromStr = $item['from']->format('Y-m-d H:i:s');
                $toStr   = $item['to']->format('Y-m-d H:i:s');

                // Zerodha daily candle endpoint
                $uri = "/instruments/historical/{$token}/day"
                    . '?from='  . urlencode($fromStr)
                    . '&to='    . urlencode($toStr)
                    . '&oi=1';

                yield $i => new Request('GET', $uri);
            }
        };

        // ── Pool with fulfilled / rejected callbacks ───────────────────────────
        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,

            'fulfilled' => function ($response, $i) use (&$rawResults) {
                try {
                    $body    = json_decode($response->getBody()->getContents(), true);
                    $candles = $body['data']['candles'] ?? [];
                    $rawResults[$i] = ['candles' => $this->parseKiteCandles($candles)];
                } catch (Exception $e) {
                    $rawResults[$i] = ['error' => 'JSON parse error: ' . $e->getMessage()];
                }
            },

            'rejected' => function ($reason, $i) use (&$rawResults) {
                $msg = $reason instanceof RequestException
                    ? $reason->getMessage()
                    : (string) $reason;
                $rawResults[$i] = ['error' => $msg];
            },
        ]);

        // Block until all requests complete (fulfilled or rejected)
        $pool->promise()->wait();

        // ── Process each result into rows ─────────────────────────────────────
        foreach ($workItems as $i => $item) {
            $symbolRecord = $item['symbolRecord'];
            $instrument   = $item['instrument'];
            $symbol       = $symbolRecord->symbol;
            $itemFrom     = $item['from'];
            $itemTo       = $item['to'];

            $result = $rawResults[$i] ?? ['error' => 'Response not captured'];

            // ── On parallel failure: sequential retry ─────────────────────────
            if (isset($result['error'])) {
                $this->warn("   ⚠️  {$symbol} parallel fail: {$result['error']} — retrying sequentially...");

                $retryCandles = $this->fetchSequential(
                    $instrument->instrument_token, $itemFrom, $itemTo, $maxRetries, $retryDelay
                );

                if (empty($retryCandles)) {
                    $this->error("   ✗ {$symbol} — failed after {$maxRetries} retries, skipping");
                    Log::error("StockDailyOhlc: Final failure for {$symbol} "
                        . "{$itemFrom->toDateString()} → {$itemTo->toDateString()}");
                    $failedCount++;
                    continue;
                }

                $result = ['candles' => $retryCandles];
            }

            // Index candles by their own trade date
            $candlesByDate = [];
            foreach ($result['candles'] as $candle) {
                $dateKey = Carbon::parse($candle->date)->toDateString();
                $candlesByDate[$dateKey] = $candle;
            }

            $this->info("   ✅ {$symbol} [{$symbolRecord->exchange}:{$instrument->instrument_type}]"
                . " — " . count($result['candles']) . " candle(s)");

            // Which days to store?
            if ($tradeDateOverride !== null) {
                // Live: just today's date
                $daysToStore = [Carbon::parse($tradeDateOverride)];
            } else {
                // Historical: trading days falling within THIS symbol's effective range
                $daysToStore = array_values(array_filter(
                    $allTradingDays,
                    fn($d) => $d->between($itemFrom, $itemTo)
                ));
            }

            foreach ($daysToStore as $day) {
                $dateStr = $day->toDateString();
                $candle  = $candlesByDate[$dateStr] ?? null;

                if ($candle === null) {
                    Log::warning("StockDailyOhlc: No candle for {$symbol} on {$dateStr}");
                    $allRows[] = $this->buildMissingRow(
                        $this->broker->id, $symbolRecord, $instrument, $dateStr, $now
                    );
                    continue;
                }

                // KEY: store the candle against its OWN date — correct for historical
                $allRows[] = $this->buildRow(
                    $this->broker->id, $symbolRecord, $instrument, $candle, $dateStr, $now
                );
            }

            // Fix 5 — memory protection: flush every 2000 rows to prevent
            // $allRows from growing unboundedly when processing 500+ symbols.
            // Each flush writes to DB immediately and resets the buffer.
            if (count($allRows) >= 2000) {
                $totalUpserted += $this->batchUpsert($allRows, $chunkSize);
                $allRows = [];
                $this->line("   💾 Flushed 2000 rows to DB (memory protection)");
            }
        }

        // Flush any remaining rows that didn't hit the 2000 threshold
        if (!empty($allRows)) {
            $totalUpserted += $this->batchUpsert($allRows, $chunkSize);
            $allRows = [];
        }

        return [$totalUpserted, $failedCount];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Sequential fallback fetch (retry on parallel failure)
    // ═════════════════════════════════════════════════════════════════════════

    private function fetchSequential(
        int    $instrumentToken,
        Carbon $from,
        Carbon $to,
        int    $maxRetries,
        int    $retryDelay
    ): array {
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr   = $to->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->zerodhaHelper->getHistoricalDataByToken(
                    $instrumentToken, 'day', $fromStr, $toStr
                );
                return $data ?? [];
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Retry {$attempt}/{$maxRetries}: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    Log::error("StockDailyOhlc: Sequential fallback exhausted for token {$instrumentToken}: {$e->getMessage()}");
                    return [];
                }
            }
        }
        return [];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Parse raw Kite candle arrays → stdClass objects
    // ═════════════════════════════════════════════════════════════════════════
    private function parseKiteCandles(array $rawCandles): array
    {
        $result = [];
        foreach ($rawCandles as $c) {
            $obj         = new \stdClass();
            $obj->date   = $c[0] ?? null;
            $obj->open   = (float) ($c[1] ?? 0);
            $obj->high   = (float) ($c[2] ?? 0);
            $obj->low    = (float) ($c[3] ?? 0);
            $obj->close  = (float) ($c[4] ?? 0);
            $obj->volume = (int)   ($c[5] ?? 0);
            $obj->oi     = (int)   ($c[6] ?? 0);
            $result[]    = $obj;
        }
        return $result;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Index alias map
    // ═════════════════════════════════════════════════════════════════════════
    private const INDEX_ALIAS_MAP = [
        // NSE indices
        'NIFTY'          => 'NIFTY 50',
        'BANKNIFTY'      => 'NIFTY BANK',
        'MIDCPNIFTY'     => 'NIFTY MID SELECT',
        'FINNIFTY'       => 'NIFTY FIN SERVICE',
        'NIFTYNXT50'     => 'NIFTY NEXT 50',
        'NIFTY100'       => 'NIFTY 100',
        'NIFTY200'       => 'NIFTY 200',
        'NIFTY500'       => 'NIFTY 500',
        'NIFTYMIDCAP50'  => 'NIFTY MIDCAP 50',
        'NIFTYMIDCAP100' => 'NIFTY MIDCAP 100',
        'NIFTYSMLCAP50'  => 'NIFTY SMLCAP 50',
        'NIFTYSMLCAP100' => 'NIFTY SMLCAP 100',
        'INDIAVIX'       => 'INDIA VIX',
        // BSE indices
        'SENSEX'         => 'SENSEX',   // same — listed for documentation
        'BANKEX'         => 'BANKEX',   // same
        'BSESENSEX'      => 'SENSEX',
    ];

    // ═════════════════════════════════════════════════════════════════════════
    // Instrument resolution
    // ═════════════════════════════════════════════════════════════════════════

    private function resolveInstrument(string $symbol, string $exchange): ?ZerodhaInstrument
    {
        $cacheKey = "{$symbol}_{$exchange}";

        if (array_key_exists($cacheKey, $this->instrumentCache)) {
            return $this->instrumentCache[$cacheKey];
        }

        $zerodhaExchange = strtoupper($exchange);
        $instrument = ZerodhaInstrument::where('exchange', $zerodhaExchange)
            ->whereIn('instrument_type', ['EQ', 'BE'])
            ->where('trading_symbol', $symbol)
            ->orderByRaw("FIELD(instrument_type, 'EQ', 'BE')")   // EQ preferred over BE
            ->first();

        if ($instrument) {
            $this->instrumentCache[$cacheKey] = $instrument;
            return $instrument;
        }

        $mappedSymbol = self::INDEX_ALIAS_MAP[$symbol] ?? null;

        if ($mappedSymbol !== null) {
            $instrument = ZerodhaInstrument::where('exchange', $zerodhaExchange)
                ->whereIn('instrument_type', ['INDEX', 'INDICES'])
                ->where(function ($q) use ($symbol, $mappedSymbol) {
                    $q->where('trading_symbol', $mappedSymbol)
                      ->orWhere('trading_symbol', $symbol)
                      ->orWhere('name', $mappedSymbol)
                      ->orWhere('name', $symbol);
                })
                ->first();

            if ($instrument) {
                $this->instrumentCache[$cacheKey] = $instrument;
                return $instrument;
            }
        }

        // ── Pass 3: Index direct (no alias needed) ────────────────────────────
        // SENSEX, BANKEX and any future index whose Zerodha name = your stored name.
        // Also covers instrument_type = 'INDICES' variant.
        $instrument = ZerodhaInstrument::where('exchange', $zerodhaExchange)
            ->whereIn('instrument_type', ['INDEX', 'INDICES'])
            ->where(function ($q) use ($symbol) {
                $q->where('trading_symbol', $symbol)
                  ->orWhere('name', $symbol);
            })
            ->first();

        if ($instrument) {
            $this->instrumentCache[$cacheKey] = $instrument;
            return $instrument;
        }

        // ── Pass 4: Last resort — any instrument_type, same exchange ─────────
        // Catches SM (trade-for-trade), MF, warrants, and anything unusual.
        // Also tries both trading_symbol AND name so "BSE" company (trading_symbol=BSE
        // but listed on NSE) resolves even if the exchange in our symbols table says BSE.
        $instrument = ZerodhaInstrument::where('exchange', $zerodhaExchange)
            ->where(function ($q) use ($symbol) {
                $q->where('trading_symbol', $symbol)
                  ->orWhere('name', $symbol);
            })
            ->whereNotIn('instrument_type', ['CE', 'PE', 'FUT'])  // never pick derivatives
            ->first();

        if ($instrument) {
            $this->instrumentCache[$cacheKey] = $instrument;
            return $instrument;
        }

        $otherExchange = ($zerodhaExchange === 'NSE') ? 'BSE' : 'NSE';
        $instrument = ZerodhaInstrument::where('exchange', $otherExchange)
            ->whereIn('instrument_type', ['EQ', 'BE', 'INDEX', 'INDICES'])
            ->where('trading_symbol', $symbol)
            ->orderByRaw("FIELD(instrument_type, 'EQ', 'BE', 'INDEX', 'INDICES')")
            ->first();

        if ($instrument) {
            $this->warn("   ⚠️  {$symbol} resolved on {$otherExchange} (not {$zerodhaExchange}) — check exchange in symbols table");
            $this->instrumentCache[$cacheKey] = $instrument;
            return $instrument;
        }

        // ── All passes failed — emit a ready-to-run SQL for debugging ─────────
        Log::warning(
            "StockDailyOhlc: resolveInstrument — all 5 passes failed for "
            . "[{$symbol}] tried exchanges=[{$zerodhaExchange}, {$otherExchange}]. "
            . "Alias map entry: " . ($mappedSymbol ? "\"{$mappedSymbol}\"" : "none") . ". "
            . "Run this SQL to inspect what Zerodha actually has: "
            . "SELECT trading_symbol, name, exchange, segment, instrument_type "
            . "FROM zerodha_instruments "
            . "WHERE trading_symbol = '{$symbol}' "
            . "OR trading_symbol LIKE '{$symbol}%' "
            . "OR name LIKE '%{$symbol}%' "
            . "LIMIT 20;"
        );

        $this->warn("   ❓ {$symbol} [{$zerodhaExchange}] — not found after 5 passes. SQL hint in laravel.log.");
        $this->line("      -- Paste in phpMyAdmin to find the actual row:");
        $this->line("      SELECT trading_symbol, name, exchange, segment, instrument_type");
        $this->line("      FROM zerodha_instruments");
        $this->line("      WHERE trading_symbol = '{$symbol}'");
        $this->line("      OR trading_symbol LIKE '{$symbol}%'");
        $this->line("      OR name LIKE '%{$symbol}%'");
        $this->line("      LIMIT 20;");

        $this->instrumentCache[$cacheKey] = null;

        return null;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Row builders
    // ═════════════════════════════════════════════════════════════════════════

    private function buildRow(
        int $brokerId,
        StockDailyOhlcSymbol $symbolRecord,
        ZerodhaInstrument $instrument,
        object $candle,
        string $tradeDate,
        string $now
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'symbol'           => $symbolRecord->symbol,
            'exchange'         => $symbolRecord->exchange,
            'trading_symbol'   => $instrument->trading_symbol,
            'instrument_token' => $instrument->instrument_token,
            'trade_date'       => $tradeDate,
            'open'             => $candle->open   ?? 0,
            'high'             => $candle->high   ?? 0,
            'low'              => $candle->low    ?? 0,
            'close'            => $candle->close  ?? 0,
            'volume'           => $candle->volume ?? 0,
            'is_missing'       => 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    /** Build a placeholder row when the API returns nothing for a trading day. */
    private function buildMissingRow(
        int $brokerId,
        StockDailyOhlcSymbol $symbolRecord,
        ZerodhaInstrument $instrument,
        string $tradeDate,
        string $now
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'symbol'           => $symbolRecord->symbol,
            'exchange'         => $symbolRecord->exchange,
            'trading_symbol'   => $instrument->trading_symbol,
            'instrument_token' => $instrument->instrument_token,
            'trade_date'       => $tradeDate,
            'open'             => 0,
            'high'             => 0,
            'low'              => 0,
            'close'            => 0,
            'volume'           => 0,
            'is_missing'       => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Batch upsert
    // ═════════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            StockDailyOhlcData::upsert(
                $chunk,
                ['broker_api_id', 'symbol', 'trade_date'],
                [
                    'exchange', 'trading_symbol', 'instrument_token',
                    'open', 'high', 'low', 'close', 'volume',
                    'is_missing', 'updated_at',
                ]
            );
            $total += count($chunk);
        }

        return $total;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════════════════

    /** All trading days (Carbon[]) in range, excluding weekends + NSE holidays. */
    private function getTradingDays(Carbon $from, Carbon $to): array
    {
        $holidays = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')
            ->flip()
            ->toArray();

        $days    = [];
        $current = $from->copy();

        while ($current->lte($to)) {
            if (!$current->isWeekend() && !isset($holidays[$current->toDateString()])) {
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

    private function printSummary(
        string $mode,
        string $from,
        string $to,
        int    $inserted,
        int    $skipped,
        int    $failed
    ): void {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->info("  ✅ {$mode} complete — " . Carbon::now()->format('H:i:s'));
        $this->info("     Range    : {$from} → {$to}");
        $this->info("     Upserted : {$inserted} row(s)");
        $this->info("     Skipped  : {$skipped} (already up to date)");
        $this->info("     Failed   : {$failed} (instrument not found / API error)");
        $this->info("═══════════════════════════════════════════════════════════════");
    }
}