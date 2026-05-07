<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionOhlcData;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CollectOptionOhlcDataBackup extends Command
{
    protected $signature = 'options:collect-ohlc-backup
                            {--start-date= : Start date (Y-m-d)}
                            {--end-date= : End date (Y-m-d)}
                            {--date= : Single date (Y-m-d)}
                            {--symbol= : Specific symbol (e.g., BHEL)}
                            {--broker= : Specific broker ID}
                            {--retry=3 : Number of retries on failure (default: 3)}
                            {--retry-delay=2 : Delay between retries in seconds (default: 2)}
                            {--chunk=50 : Batch insert chunk size (default: 50)}';

    protected $description = 'Collect OHLC + OI for FUT and Options (ATM, ATM±1) — FROZEN ATM, FULL-DAY FETCH, BATCH INSERT, ZERO GAP';

    private const ALLOWED_SYMBOLS = [
        'ADANIPORTS', 'ASIANPAINT', 'AUROPHARMA', 'AXISBANK',
        'BAJAJFINSV', 'BDL', 'BHARATFORG', 'BHARTIARTL', 'BSE',
        'CDSL', 'DRREDDY', 'HAL', 'HAVELLS', 'HEROMOTOCO',
        'HINDALCO', 'INFY', 'JSWSTEEL', 'LAURUSLABS', 'LTF',
        'PAYTM', 'POLICYBZR', 'SHRIRAMFIN', 'SRF', 'TATACONSUM',
        'VEDL',
    ];

    private $strikeIntervals = [
        'NIFTY'        => 100,
        'BANKNIFTY'    => 100,
        'FINNIFTY'     => 50,
        'MIDCPNIFTY'   => 25,
        'AXISBANK'     => 10,
        'ICICIBANK'    => 10,
        'INDUSINDBK'   => 10,
        'BHARTIARTL'   => 20,
        'SHRIRAMFIN'   => 10,
        'LTF'          => 5,
        'PAYTM'        => 20,
        'POLICYBZR'    => 20,
        'BAJAJFINSV'   => 20,
        'INFY'         => 20,
        'TATAELXSI'    => 50,
        'TATATECH'     => 10,
        'HAVELLS'      => 20,
        'TITAN'        => 20,
        'ASIANPAINT'   => 20,
        'TATACONSUMER' => 10,
        'VOLTAS'       => 20,
        'AUROPHARMA'   => 10,
        'LAURUSLABS'   => 10,
        'SRF'          => 20,
        'JSWSTEEL'     => 10,
        'LT'           => 20,
        'BHEL'         => 5,
        'ADANIPORTS'   => 20,
        'HAL'          => 50,
        'BDL'          => 20,
        'MCX'          => 20,
        'BSE'          => 50,
        'CDSL'         => 20,
        'LICHSG'       => 5,
        'DELHIVERY'    => 10,
        'BHARATFORG'   => 20,
        'PGEL'         => 10,
        'TMPV'         => 5,
        'HINDALCO'     => 10,
        'VEDL'         => 10,
        'DRREDDY'      => 50,
        'LICHSGFIN'    => 5,
        'TATACONSUM'   => 10,
        'ABCCAPITAL'   => 10,
        'SBIN'         => 10,
        'VBL'          => 20,
        'BAJFINANCE'   => 50,
        'TCS'          => 50,
        'COFORGE'      => 50,
        'EICHERMOT'    => 50,
        'HEROMOTOCO'   => 20,
        'AMBUJACEM'    => 5,
        'FORTIS'       => 5,
        'UPL'          => 10,
        'M&M'          => 20,
        'NATIONALUM'   => 5,
        'BPCL'         => 10,
        'ETERNAL'      => 10,
    ];

    /** In-memory instrument cache: key = "SYMBOL_STRIKE_TYPE_EXPIRY" */
    private array $instrumentCache = [];

    /** Zerodha helper cache per broker */
    private array $zerodhaHelperCache = [];

    public function handle()
    {
        // ── Date range ──────────────────────────────────────────────────────
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

        $specificSymbol = $this->option('symbol')
            ? strtoupper($this->option('symbol'))
            : null;

        $specificBroker = $this->option('broker');
        $maxRetries     = (int) $this->option('retry');
        $retryDelay     = (int) $this->option('retry-delay');
        $chunkSize      = (int) $this->option('chunk');

        $this->info("🚀 Option OHLC Collector — FROZEN ATM + FULL-DAY FETCH + BATCH INSERT");
        $this->info("   Date   : {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        $this->info("   Symbols: " . ($specificSymbol ?: implode(', ', self::ALLOWED_SYMBOLS)));
        $this->info("   Retry  : {$maxRetries}x / {$retryDelay}s delay | Batch: {$chunkSize}");
        $this->newLine();

        // ── Brokers ─────────────────────────────────────────────────────────
        $brokersQuery = BrokerApi::zerodha()->validToken();
        if ($specificBroker) {
            $brokersQuery->where('id', $specificBroker);
        }
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

            // ── Preload expiry map ONCE per broker — not per date ────────────
            // For a 30-day × 20-symbol run this saves ~600 redundant DB queries.
            // Expiry is keyed by symbol; each entry is the nearest future expiry
            // from the start of our date range. If processing a range that spans
            // an expiry rollover you should extend this to be date-aware, but for
            // typical weekly/monthly runs this is correct and efficient.
            $brokerSymbolsForExpiry = \App\Models\SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('is_active', true)
                ->where('interval', '5minute')
                ->where('instrument_type', 'FUT')
                ->when($specificSymbol, fn($q) => $q->where('symbol', $specificSymbol))
                ->pluck('symbol');

            $preloadedExpiryMap = [];
            foreach ($brokerSymbolsForExpiry as $sym) {
                $preloadedExpiryMap[$sym] = $this->getExpiry($sym, $startDate);
            }
            $this->info("   Expiry map preloaded for " . count($preloadedExpiryMap) . " symbol(s)");

            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {

                if ($currentDate->isWeekend()) {
                    $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (Weekend)");
                    $currentDate->addDay();
                    continue;
                }

                if ($this->isMarketHoliday($currentDate->format('Y-m-d'))) {
                    $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (Holiday)");
                    $currentDate->addDay();
                    continue;
                }

                $this->info("\n📅 {$currentDate->format('Y-m-d')}");

                try {
                    $result = $this->processDate(
                        $broker, $currentDate, $specificSymbol,
                        $maxRetries, $retryDelay, $chunkSize,
                        $preloadedExpiryMap
                    );
                    $totalProcessed += $result['success'];
                    $totalFailed    += $result['failed'];
                } catch (Exception $e) {
                    $this->error("Date error: " . $e->getMessage());
                    Log::error("CollectOptionOhlcData date error", [
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

    // ═══════════════════════════════════════════════════════════════════════
    // Core date processing
    // ═══════════════════════════════════════════════════════════════════════

    private function processDate(
        BrokerApi $broker,
        Carbon $date,
        ?string $specificSymbol,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize,
        array $preloadedExpiryMap = []
    ): array {
        // ── Monitored futures ────────────────────────────────────────────────
        $symbolsQuery = \App\Models\SymbolMonitored::where('broker_api_id', $broker->id)
            ->where('is_active', true)
            ->where('interval', '5minute')
            ->where('instrument_type', 'FUT');

        if ($specificSymbol) {
            $symbolsQuery->where('symbol', $specificSymbol);
        }

        $monitoredSymbols = $symbolsQuery->get();

        if ($monitoredSymbols->isEmpty()) {
            $this->warn("   ⚠️  No monitored futures found");
            return ['success' => 0, 'failed' => 0];
        }

        $this->info("   Found " . $monitoredSymbols->count() . " symbol(s): "
            . $monitoredSymbols->pluck('symbol')->implode(', '));

        // ── 26 intervals: 09:15 → 15:15 ─────────────────────────────────────
        $intervals = $this->generateTradingIntervals($date);
        $this->info("   Intervals: " . count($intervals) . " (09:15 → 15:15)");

        // ── Use preloaded expiry map (no per-date DB queries) ─────────────────
        // Falls back to a live lookup only for symbols missing from the preload
        // (e.g. symbols added mid-run or when called without a preloaded map).
        $expiryMap = [];
        foreach ($monitoredSymbols as $ms) {
            $expiryMap[$ms->symbol] = $preloadedExpiryMap[$ms->symbol]
                ?? $this->getExpiry($ms->symbol, $date);
        }

        // ── Pre-warm instrument cache (one DB query per symbol) ──────────────
        $this->prewarmInstrumentCache($monitoredSymbols, $expiryMap);

        $success = 0;
        $failed  = 0;

        foreach ($monitoredSymbols as $monitoredSymbol) {
            $baseSymbol     = $monitoredSymbol->symbol;
            $tradingSymbol  = $monitoredSymbol->trading_symbol;
            $expiry         = $expiryMap[$baseSymbol];
            $strikeInterval = $this->strikeIntervals[$baseSymbol] ?? 20;

            $this->info("   📊 {$baseSymbol} ({$tradingSymbol}) — expiry: {$expiry}");

            // ── Step 1: Fetch full-day FUT candles (ONE API call) ─────────────
            $allFutCandles = $this->fetchDayCandles(
                $broker,
                $monitoredSymbol->instrument_token,
                $date,
                $maxRetries,
                $retryDelay
            );

            if (empty($allFutCandles)) {
                $this->error("      ✗ Could not fetch any FUT data for {$baseSymbol} — skipping symbol");
                $failed += count($intervals);
                continue;
            }

            $this->info("      FUT: Fetched " . count($allFutCandles) . " candles");
            $futCandleMap = $this->indexCandlesByTime($allFutCandles);

            // ── Step 2: FREEZE ATM at 09:15 close ────────────────────────────
            //    Industry standard: fix strikes at first 15-min candle close.
            //    This ensures the same instruments are tracked all day for
            //    valid OI comparison, premium decay, and intraday analysis.
            if (!isset($futCandleMap['09:15'])) {
                $this->error("      ✗ 09:15 candle missing for {$baseSymbol} — cannot freeze ATM, skipping");
                $failed += count($intervals);
                continue;
            }

            $openCandle    = $futCandleMap['09:15'];
            $frozenAtm     = round($openCandle->close / $strikeInterval) * $strikeInterval;
            $frozenStrikes = [
                $frozenAtm - ($strikeInterval * 5),
                $frozenAtm - ($strikeInterval * 4),
                $frozenAtm - ($strikeInterval * 3),
                $frozenAtm - ($strikeInterval * 2),
                $frozenAtm - $strikeInterval,
                $frozenAtm,
                $frozenAtm + $strikeInterval,
                $frozenAtm + ($strikeInterval * 2),
                $frozenAtm + ($strikeInterval * 3),
                $frozenAtm + ($strikeInterval * 4),
                $frozenAtm + ($strikeInterval * 5),
            ];

            $this->info("      ATM frozen at {$frozenAtm} (strikes: " . implode(', ', $frozenStrikes) . ")");

            // ── Step 3: Fetch full-day option candles (ONE API call per instrument)
            //    6 instruments × 1 call = 6 calls total (vs 156 previously)
            $optionDayCache = $this->fetchAllOptionDayCandles(
                $broker,
                $baseSymbol,
                $frozenStrikes,
                $expiry,
                $date,
                $maxRetries,
                $retryDelay
            );

            // ── Step 4: Build all rows in memory, then batch-insert ───────────
            $rows = [];
            $now  = now()->toDateTimeString();

            // Carry-forward tracker: stores the last seen FUT close so that
            // option rows are never stored with future_price = 0.
            // Initialised to null — option rows whose interval precedes the
            // first available FUT candle will store null, which is analytically
            // honest (unknown), rather than the misleading value 0.
            $lastKnownFutClose = null;

            foreach ($intervals as $intervalTime) {
                $timeKey  = $intervalTime->format('H:i');
                $futCandle = $futCandleMap[$timeKey] ?? null;

                // Update carry-forward whenever a real candle is present
                if ($futCandle !== null) {
                    $lastKnownFutClose = $futCandle->close;
                }

                // — FUT row —
                if ($futCandle !== null) {
                    $rows[] = $this->buildFutRow(
                        $broker->id, $baseSymbol, $monitoredSymbol,
                        $futCandle, $frozenAtm, $date, $intervalTime, $now
                    );
                } else {
                    // Zero-fill missing FUT candle — no silent skips
                    $this->warn("      ⚠️ {$timeKey} — FUT candle missing, storing zeros (is_missing=1)");
                    $rows[] = $this->buildFutRow(
                        $broker->id, $baseSymbol, $monitoredSymbol,
                        null, $frozenAtm, $date, $intervalTime, $now, true
                    );
                }

                // — CE + PE rows for each frozen strike —
                foreach (['CE', 'PE'] as $optionType) {
                    foreach ($frozenStrikes as $strike) {
                        $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                        $instrument = $this->instrumentCache[$cacheKey] ?? null;

                        if (!$instrument) {
                            continue; // instrument not in DB — genuinely missing
                        }

                        $token  = $instrument->instrument_token;
                        $candle = $optionDayCache[$token][$timeKey] ?? null;

                        $isMissing = ($candle === null);
                        if ($isMissing) {
                            $this->warn("      ⚠️ {$timeKey} {$optionType} {$strike} — missing, storing zeros");
                        }

                        $strikePos = $this->getStrikePosition($strike, $frozenAtm, $strikeInterval);
                        $rows[]    = $this->buildOptionRow(
                            $broker->id, $baseSymbol, $tradingSymbol,
                            // Use carry-forward close — never store 0 as future_price.
                            // null means "no FUT data seen yet for this day" which is
                            // analytically correct and filterable downstream.
                            $lastKnownFutClose,
                            $frozenAtm, $optionType, $strike, $strikePos,
                            $instrument, $candle, $expiry,
                            $date, $intervalTime, $now, $isMissing
                        );
                    }
                }
            } // end foreach intervals

            // ── Step 5: Batch upsert ──────────────────────────────────────────
            $inserted = $this->batchUpsert($rows, $chunkSize);
            $success += $inserted;
            $this->info("      ✅ {$baseSymbol} — {$inserted} rows upserted");
        }

        return ['success' => $success, 'failed' => $failed];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fetch full-day option candles for ALL 6 instruments at once
    // Returns: [instrument_token => ['H:i' => candle]]
    // ═══════════════════════════════════════════════════════════════════════

    private function fetchAllOptionDayCandles(
        BrokerApi $broker,
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
                    $this->warn("      ⚠️ Instrument not found: {$cacheKey}");
                    continue;
                }

                $token   = $instrument->instrument_token;
                $candles = $this->fetchDayCandles($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($candles)) {
                    $cache[$token] = $this->indexCandlesByTime($candles);
                    $this->info("      {$optionType} {$strike}: " . count($candles) . " candles");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data (all intervals will be zero-filled)");
                }
            }
        }

        return $cache; // 6 API calls total per symbol per day
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Batch upsert helper — much faster than updateOrCreate per row
    // ═══════════════════════════════════════════════════════════════════════

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            // updateOrInsert via upsert (Laravel 8+)
            // Unique key: broker_api_id + trade_date + interval_time + trading_symbol
            OptionOhlcData::upsert(
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

    // ═══════════════════════════════════════════════════════════════════════
    // Row builders
    // ═══════════════════════════════════════════════════════════════════════

    private function buildFutRow(
        int $brokerId,
        string $baseSymbol,
        $monitoredSymbol,
        $candle,
        float $atmStrike,
        Carbon $tradeDate,
        Carbon $intervalTime,
        string $now,
        bool $isMissing = false
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'trading_symbol'   => $monitoredSymbol->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $monitoredSymbol->trading_symbol,
            'future_price'     => $candle ? $candle->close : 0,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'instrument_token' => $monitoredSymbol->instrument_token,
            'open'             => $candle ? $candle->open   : 0,
            'high'             => $candle ? $candle->high   : 0,
            'low'              => $candle ? $candle->low    : 0,
            'close'            => $candle ? $candle->close  : 0,
            'volume'           => $candle ? $candle->volume : 0,
            'oi'               => $candle ? ($candle->oi ?? 0) : 0,
            'strike_position'  => 'N/A',
            'expiry_date'      => null,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function buildOptionRow(
        int $brokerId,
        string $baseSymbol,
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
            'base_symbol'      => $baseSymbol,
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

    // ═══════════════════════════════════════════════════════════════════════
    // Generate 26 trading intervals: 09:15 → 15:15
    // ═══════════════════════════════════════════════════════════════════════

    private function generateTradingIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 15);

        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes(15);
        }

        return $intervals; // 26 slots
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Fetch entire day candles in ONE API call
    // ═══════════════════════════════════════════════════════════════════════

    private function fetchDayCandles(
        BrokerApi $broker,
        int $instrumentToken,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $fromTime = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $toTime   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $helper = $this->zerodhaHelperCache[$broker->id];
                $data   = $helper->getHistoricalDataByToken(
                    $instrumentToken, '15minute', $fromTime, $toTime
                );
                return $data ?? [];
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Fetch attempt {$attempt}/{$maxRetries} failed: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts");
                    return [];
                }
            }
        }

        return [];
    }

    /**
     * Index candle array by "H:i" time key
     */
    private function indexCandlesByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $candle) {
            $time       = Carbon::parse($candle->date)->format('H:i');
            $map[$time] = $candle;
        }
        return $map;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Pre-warm instrument cache (one DB query per symbol/expiry)
    // ═══════════════════════════════════════════════════════════════════════

    private function prewarmInstrumentCache($monitoredSymbols, array $expiryMap): void
    {
        foreach ($monitoredSymbols as $ms) {
            $baseSymbol = $ms->symbol;
            $expiry     = $expiryMap[$baseSymbol];

            $instruments = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('expiry', $expiry)
                ->get();

            foreach ($instruments as $instrument) {
                $key = "{$baseSymbol}_{$instrument->strike}_{$instrument->instrument_type}_{$expiry}";
                $this->instrumentCache[$key] = $instrument;
            }

            $this->info("      Cached " . $instruments->count() . " instruments for {$baseSymbol}");
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════

    private function getStrikePosition(float $strike, float $atmStrike, float $strikeInterval): string
    {
        if ($strike == $atmStrike)                    return 'ATM';
        if ($strike == $atmStrike + $strikeInterval)  return 'ATM+1';
        if ($strike == $atmStrike - $strikeInterval)  return 'ATM-1';
        return 'N/A';
    }

    private function getExpiry(string $baseSymbol, Carbon $tradeDate): string
    {
        $first = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $tradeDate)
            ->orderBy('expiry', 'ASC')
            ->first();

        return $first
            ? Carbon::parse($first->expiry)->toDateString()
            : $tradeDate->copy()->addDays(7)->toDateString();
    }

    private function isMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}