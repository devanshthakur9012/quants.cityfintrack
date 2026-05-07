<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionOhlcData;
use App\Models\OptionSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * LiveOptionOhlcCollectorWorkNew
 *
 * Designed to run every 15 minutes via cron:
 *   * /15 9-15 * * 1-5  php artisan options:live-collect >> /dev/null 2>&1
 *
 * Behaviour on each run:
 *   1. Gap-fill: find ALL missing intervals from 09:15 today up to the
 *      current candle window, fetch & store them.
 *   2. Current candle: fetch and store the candle for the CURRENT 15-min window.
 *
 * Key changes vs previous version:
 *   ✅ No SymbolMonitored dependency — uses option_symbols table + ZerodhaInstrument
 *   ✅ Smart dual-expiry — within 1 trading week of expiry, collects BOTH
 *      current AND next month in parallel; after expiry, next month only
 *   ✅ FUT instrument resolved directly from ZerodhaInstrument per expiry
 *   ✅ All other guarantees preserved: frozen ATM, batch upsert, zero-gap fill,
 *      carry-forward future_price, full-day fetch
 */
class LiveOptionOhlcCollectorWorkNew extends Command
{
    use OptionExpiryResolver;

    protected $signature = 'options:live-collect-work-new
                            {--symbol= : Specific symbol}
                            {--broker= : Specific broker ID}
                            {--retry=3 : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50 : Batch upsert chunk size}
                            {--force-date= : Override date (Y-m-d), for testing}';

    protected $description = 'Live 15-min option OHLC collector — smart dual-expiry, gap-fill, frozen ATM, full-day fetch, batch upsert, zero-gap';

    private const STRIKE_INTERVALS = [
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
        // NEW
        'ABB'         => 20,
        'ACC'         => 10,
        'ADANIENT'    => 20,
        'ADANIGREEN'  => 20,
        'ADANIPOWER'  => 10,
        'ALKEM'       => 20,
        'APOLLOHOSP'  => 10,
        'APOLLOTYRE'  => 5,
        'ASHOKLEY'    => 5,
        'ASTRAL'      => 20,
        'ATGL'        => 20,
        'BALKRISIND'  => 20,
        'BANDHANBNK'  => 10,
        'BEL'         => 5,
        'BERGEPAINT'  => 10,
        'BIOCON'      => 5,
        'BOSCHLTD'    => 50,
        'BRITANNIA'   => 50,
        'CANBK'       => 10,
        'CHOLAFIN'    => 20,
        'CIPLA'       => 10,
        'COALINDIA'   => 5,
        'COLPAL'      => 10,
        'CONCOR'      => 10,
        'CROMPTON'    => 5,
        'DABUR'       => 10,
        'DIVISLAB'    => 50,
        'DLF'         => 10,
        'ESCORTS'     => 10,
        'GAIL'        => 10,
        'GLENMARK'    => 10,
        'GODREJCP'    => 10,
        'GRASIM'      => 20,
        'HCLTECH'     => 20,
        'HDFCBANK'    => 10,
        'HDFCLIFE'    => 10,
        'HINDUNILVR'  => 20,
        'IOC'         => 5,
        'IRCTC'       => 20,
        'ITC'         => 5,
        'JINDALSTEL'  => 10,
        'JUBLFOOD'    => 20,
        'KOTAKBANK'   => 10,
        'LTIM'        => 20,
        'MARUTI'      => 50,
        'MOTHERSON'   => 5,
        'MPHASIS'     => 20,
        'NMDC'        => 5,
        'NTPC'        => 10,
    ];

    private const MARKET_START = '09:15';
    private const MARKET_END   = '15:15';

    /** In-memory instrument cache: key = "SYMBOL_STRIKE_TYPE_EXPIRY" */
    private array $instrumentCache = [];

    /** Zerodha helper cache per broker */
    private array $helperCache = [];

    // ══════════════════════════════════════════════════════════════════════════
    // Entry point
    // ══════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $today      = $this->option('force-date')
            ? Carbon::parse($this->option('force-date'))
            : Carbon::today();
        $now        = Carbon::now();
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $specBroker = $this->option('broker');

        // ── Sanity checks ─────────────────────────────────────────────────────
        if ($today->isWeekend()) {
            $this->warn("⏭  Weekend — nothing to collect.");
            return 0;
        }
        if ($this->isMarketHoliday($today->toDateString())) {
            $this->warn("⏭  Market holiday — nothing to collect.");
            return 0;
        }

        $this->info("⚡ Live Option OHLC Collector — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Smart Dual-Expiry | Frozen ATM | Full-Day Fetch | Batch Upsert | Zero-Gap");
        $this->info("   Rollover window : " . self::ROLLOVER_TRADING_DAYS . " trading days before expiry");
        $this->newLine();

        // ── Load symbols from option_symbols table ────────────────────────────
        $symbolsQuery = OptionSymbol::active();
        if ($specSymbol) $symbolsQuery->where('symbol', $specSymbol);
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols found in option_symbols table!');
            $this->line('   Run: php artisan db:seed --class=OptionSymbolSeeder');
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));
        $this->newLine();

        // ── Brokers ───────────────────────────────────────────────────────────
        $brokersQuery = BrokerApi::zerodha()->validToken();
        if ($specBroker) $brokersQuery->where('id', $specBroker);
        $brokers = $brokersQuery->get();

        if ($brokers->isEmpty()) {
            $this->error('❌ No active brokers found!');
            return 1;
        }

        // ── Determine which intervals to process ──────────────────────────────
        $allIntervals       = $this->generateTradingIntervals($today);
        $lastCompletedSlot  = $this->getLastCompletedSlot($now, $today);
        $intervalsToProcess = array_values(array_filter(
            $allIntervals,
            fn($t) => $t->lte($lastCompletedSlot)
        ));

        if (empty($intervalsToProcess)) {
            $this->warn("   ⏳ Market hasn't started yet or no completed candle available.");
            return 0;
        }

        $this->info("   Last completed slot : " . $lastCompletedSlot->format('H:i'));
        $this->info("   Intervals to cover  : 09:15 → " . $lastCompletedSlot->format('H:i')
            . " (" . count($intervalsToProcess) . " slots)");
        $this->newLine();

        foreach ($brokers as $broker) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

            $this->helperCache[$broker->id] = new BrokerZerodhaHelper($broker);

            foreach ($symbols as $baseSymbol) {
                // ── Resolve expiries for today (1 or 2 during rollover window) ─
                $expiries = $this->resolveExpiries($baseSymbol, $today);

                $this->info("\n   📊 {$baseSymbol} — expir" . (count($expiries) > 1 ? 'ies' : 'y')
                    . ': ' . implode(' + ', $expiries));

                // Pre-warm instrument cache for all relevant expiries at once
                foreach ($expiries as $expiry) {
                    $this->prewarmInstrumentCacheForExpiry($baseSymbol, $expiry);
                }

                foreach ($expiries as $expiry) {
                    $futInstrument = $this->resolveFutInstrument($baseSymbol, $expiry);

                    if (!$futInstrument) {
                        $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} expiry {$expiry} — skipping");
                        continue;
                    }

                    $this->processSymbolExpiry(
                        $broker, $baseSymbol, $futInstrument,
                        $expiry, $today, $intervalsToProcess,
                        $maxRetries, $retryDelay, $chunkSize
                    );
                }
            }
        }

        $this->newLine();
        $this->info("✅ Live collection run complete — " . Carbon::now()->format('H:i:s'));
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Process one symbol × one expiry — gap-fill + current candle
    // ══════════════════════════════════════════════════════════════════════════

    private function processSymbolExpiry(
        BrokerApi $broker,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        string $expiry,
        Carbon $date,
        array $intervalsToProcess,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): void {
        $strikeInterval = self::STRIKE_INTERVALS[$baseSymbol] ?? 20;

        // ── Find already-stored intervals (O(1) lookup) ───────────────────────
        // Use trading_symbol scoped to this expiry's FUT to avoid cross-expiry
        // false positives when both expiries are running in parallel.
        $storedTimes = OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('trading_symbol', $futInstrument->trading_symbol)
            ->where('is_missing', 0)
            ->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->flip()
            ->toArray();

        $missingIntervals = array_values(array_filter(
            $intervalsToProcess,
            fn($t) => !isset($storedTimes[$t->format('H:i')])
        ));

        if (empty($missingIntervals)) {
            $lastSlot = end($intervalsToProcess);
            $this->info("      ✓ {$baseSymbol} [{$expiry}] — up to date (latest: "
                . ($lastSlot ? $lastSlot->format('H:i') : '—') . ")");
            return;
        }

        $missingCount = count($missingIntervals);
        $currentSlot  = end($missingIntervals);
        $gapCount     = $missingCount - 1;

        if ($gapCount > 0) {
            $this->info("      🔄 {$baseSymbol} [{$expiry}] — gap-filling {$gapCount} missed + current ("
                . $currentSlot->format('H:i') . ")");
        } else {
            $this->info("      📥 {$baseSymbol} [{$expiry}] — fetching current interval "
                . $currentSlot->format('H:i'));
        }

        // ── Step 1: Fetch full-day FUT candles ────────────────────────────────
        $allFutCandles = $this->fetchDayCandles(
            $broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay
        );

        if (empty($allFutCandles)) {
            $this->error("      ✗ {$baseSymbol} [{$expiry}] — could not fetch FUT data, skipping");
            return;
        }

        $futCandleMap = $this->indexCandlesByTime($allFutCandles);

        // ── Step 2: FREEZE ATM at 09:15 close ────────────────────────────────
        if (!isset($futCandleMap['09:15'])) {
            $this->error("      ✗ {$baseSymbol} [{$expiry}] — 09:15 FUT candle missing, cannot freeze ATM");
            return;
        }

        $frozenAtm     = round($futCandleMap['09:15']->close / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("      ATM frozen at {$frozenAtm} (strikes: " . implode(', ', $frozenStrikes) . ")");

        // ── Step 3: Fetch full-day option candles ─────────────────────────────
        $optionDayCache = $this->fetchAllOptionDayCandles(
            $broker, $baseSymbol, $frozenStrikes, $expiry, $date, $maxRetries, $retryDelay
        );

        // ── Step 4: Build rows in memory ──────────────────────────────────────
        $rows              = [];
        $now               = now()->toDateTimeString();
        $lastKnownFutClose = null;

        foreach ($missingIntervals as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;

            if ($futCandle !== null) {
                $lastKnownFutClose = $futCandle->close;
            }

            // — FUT row —
            $isFutMissing = ($futCandle === null);
            if ($isFutMissing) {
                $this->warn("      ⚠️  {$timeKey} — FUT candle missing, storing zeros (is_missing=1)");
            }
            $rows[] = $this->buildFutRow(
                $broker->id, $baseSymbol, $futInstrument,
                $futCandle, $frozenAtm, $expiry,
                $date, $intervalTime, $now, $isFutMissing
            );

            // — CE + PE rows —
            foreach (['CE', 'PE'] as $optionType) {
                foreach ($frozenStrikes as $strike) {
                    $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                    $instrument = $this->instrumentCache[$cacheKey] ?? null;
                    if (!$instrument) continue;

                    $token     = $instrument->instrument_token;
                    $candle    = $optionDayCache[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    if ($isMissing) {
                        $this->warn("      ⚠️  {$timeKey} {$optionType} {$strike} — missing, storing zeros");
                    }

                    $rows[] = $this->buildOptionRow(
                        $broker->id, $baseSymbol,
                        $futInstrument->trading_symbol,
                        $lastKnownFutClose,
                        $frozenAtm, $optionType, $strike,
                        $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                        $instrument, $candle, $expiry,
                        $date, $intervalTime, $now, $isMissing
                    );
                }
            }
        }

        // ── Step 5: Batch upsert ──────────────────────────────────────────────
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [{$expiry}] — {$inserted} rows upserted ({$missingCount} intervals)");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Option candle batch fetch
    // ══════════════════════════════════════════════════════════════════════════

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
                    $this->warn("      ⚠️  Instrument not found: {$cacheKey}");
                    continue;
                }

                $token   = $instrument->instrument_token;
                $candles = $this->fetchDayCandles($broker, $token, $date, $maxRetries, $retryDelay);

                if (!empty($candles)) {
                    $cache[$token] = $this->indexCandlesByTime($candles);
                    $this->info("      {$optionType} {$strike}: " . count($candles) . " candles");
                } else {
                    $cache[$token] = [];
                    $this->warn("      {$optionType} {$strike}: no data — intervals zero-filled");
                }
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

    // ══════════════════════════════════════════════════════════════════════════
    // Row builders
    // ══════════════════════════════════════════════════════════════════════════

    private function buildFutRow(
        int $brokerId,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        $candle,
        float $atmStrike,
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
            'trading_symbol'   => $futInstrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futInstrument->trading_symbol,
            'future_price'     => $candle ? $candle->close : 0,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'instrument_token' => $futInstrument->instrument_token,
            'open'             => $candle ? $candle->open   : 0,
            'high'             => $candle ? $candle->high   : 0,
            'low'              => $candle ? $candle->low    : 0,
            'close'            => $candle ? $candle->close  : 0,
            'volume'           => $candle ? $candle->volume : 0,
            'oi'               => $candle ? ($candle->oi ?? 0) : 0,
            'strike_position'  => 'N/A',
            'expiry_date'      => $expiry,          // ← now stored on FUT rows too
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

    // ══════════════════════════════════════════════════════════════════════════
    // Instrument resolution
    // ══════════════════════════════════════════════════════════════════════════

    // private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    // {
    //     $query = ZerodhaInstrument::where('instrument_type', 'FUT')
    //         ->where('exchange', 'NFO')
    //         ->whereDate('expiry', $expiry);

    //     if (in_array($symbol, ['NIFTY', 'BANKNIFTY'])) {
    //         $query->where('name', $symbol);
    //     } else {
    //         $query->where(function ($q) use ($symbol) {
    //             $q->where('name', $symbol)
    //               ->orWhere('trading_symbol', 'LIKE', $symbol . '%');
    //         });
    //     }

    //     return $query->first();
    // }

    /**
     * Resolve the FUT instrument for a symbol + exact expiry date.
     *
     * For MONTHLY symbols: looks up FUT by exact expiry date (original logic).
     *
     * For WEEKLY symbols (NIFTY): weekly options have NO corresponding FUT.
     * Falls back to the nearest monthly FUT whose expiry is >= the weekly
     * expiry date. E.g. weekly expiry 2026-03-02 → uses NIFTY26MARFUT (2026-03-30).
     * This is standard practice — near-month FUT price is used as ATM reference.
     */
    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        $isWeekly = in_array($symbol, ['NIFTY']); // matches WEEKLY_EXPIRY_SYMBOLS in trait

        // ── For weekly symbols: check if a FUT exists for this exact expiry ───
        // If not (which is always the case for mid-month weekly expiries),
        // fall back to the nearest monthly FUT >= weekly expiry date.
        if ($isWeekly) {
            $query = ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', 'NFO')
                ->where('name', $symbol)
                ->whereDate('expiry', '>=', $expiry)   // nearest monthly FUT on or after weekly expiry
                ->orderBy('expiry', 'ASC');             // pick the closest one

            return $query->first();
        }

        // ── For monthly symbols: exact expiry match (original logic) ──────────
        $query = ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', 'NFO')
            ->whereDate('expiry', $expiry);

        if (in_array($symbol, ['NIFTY', 'BANKNIFTY'])) {
            $query->where('name', $symbol);
        } else {
            $query->where(function ($q) use ($symbol) {
                $q->where('name', $symbol)
                  ->orWhere('trading_symbol', 'LIKE', $symbol . '%');
            });
        }

        return $query->first();
    }

    private function prewarmInstrumentCacheForExpiry(string $baseSymbol, string $expiry): void
    {
        $instruments = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = "{$baseSymbol}_{$inst->strike}_{$inst->instrument_type}_{$expiry}";
            $this->instrumentCache[$key] = $inst;
        }

        $this->info("      Cached " . $instruments->count() . " option instruments for {$baseSymbol} [{$expiry}]");
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Utility helpers
    // ══════════════════════════════════════════════════════════════════════════

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

    /**
     * Last completed 15-min slot, clamped to market hours.
     * e.g. now=09:28 → 09:15 | now=12:40 → 12:30 | now=15:30 → 15:15
     */
    // private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    // {
    //     $marketStart = $date->copy()->setTimeFromTimeString(self::MARKET_START);
    //     $marketEnd   = $date->copy()->setTimeFromTimeString(self::MARKET_END);

    //     if ($now->lt($marketStart)) {
    //         return $marketStart;
    //     }

    //     $flooredMin = (int)(floor((int)$now->format('i') / 15) * 15);
    //     $slot       = $date->copy()->setTime((int)$now->format('H'), $flooredMin, 0);

    //     return match (true) {
    //         $slot->lt($marketStart) => $marketStart->copy(),
    //         $slot->gt($marketEnd)   => $marketEnd->copy(),
    //         default                 => $slot,
    //     };
    // }

    private function getLastCompletedSlot(Carbon $now, Carbon $date): Carbon
    {
        $marketStart = $date->copy()->setTimeFromTimeString(self::MARKET_START);
        $marketEnd   = $date->copy()->setTimeFromTimeString(self::MARKET_END);

        if ($now->lt($marketStart)) {
            return $marketStart;
        }

        $flooredMin = (int)(floor((int)$now->format('i') / 15) * 15);
        $slot       = $date->copy()->setTime((int)$now->format('H'), $flooredMin, 0);

        // ✅ Step back by 15 min — the current slot is still forming
        $slot->subMinutes(15);

        return match (true) {
            $slot->lt($marketStart) => $marketStart->copy(),
            $slot->gt($marketEnd)   => $marketEnd->copy(),
            default                 => $slot,
        };
    }

    private function generateTradingIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 15);
        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes(15);
        }
        return $intervals;
    }

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
                $data = $this->helperCache[$broker->id]->getHistoricalDataByToken(
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

    private function indexCandlesByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $candle) {
            $map[Carbon::parse($candle->date)->format('H:i')] = $candle;
        }
        return $map;
    }

    private function getStrikePosition(float $strike, float $atm, float $interval): string
    {
        if ($strike == $atm)             return 'ATM';
        if ($strike == $atm + $interval) return 'ATM+1';
        if ($strike == $atm - $interval) return 'ATM-1';
        return 'N/A';
    }
}