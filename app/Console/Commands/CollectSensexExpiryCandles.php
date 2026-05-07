<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * CollectSensexExpiryCandles
 *
 * On SENSEX expiry day, collects ALL 15-min candles (09:15 → 15:15)
 * for CE and PE options (11 strikes around ATM, frozen at 09:15 close)
 * and stores them into `sensex_expiry_candles` table.
 *
 * Usage:
 *   php artisan sensex:collect-expiry-candles
 *   php artisan sensex:collect-expiry-candles --date=2026-04-02   (override date)
 *   php artisan sensex:collect-expiry-candles --expiry=2026-04-02 (override expiry)
 */
class CollectSensexExpiryCandles extends Command
{
    // ── Pin to your broker account ────────────────────────────────────────────
    private const BROKER_CLIENT_ID = 'OQJ978';
    private const BASE_SYMBOL      = 'SENSEX';
    private const EXCHANGE         = 'BFO';
    private const MARKET_START     = '09:15';
    private const MARKET_END       = '15:29';

    protected $signature = 'sensex:collect-expiry-candles
                            {--date=    : Trade date (Y-m-d), defaults to today}
                            {--expiry=  : Option expiry date (Y-m-d), defaults to today}
                            {--retry=3  : Retries per API call}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50 : Batch upsert chunk size}';

    protected $description = 'Collect all 15-min CE & PE candles for SENSEX expiry day into sensex_expiry_candles table';

    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    // ══════════════════════════════════════════════════════════════════════════
    // Entry point
    // ══════════════════════════════════════════════════════════════════════════

    public function handle(): int
    {
        $now        = Carbon::now();
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');

        $tradeDate  = Carbon::parse($this->option('date')   ?: now()->toDateString())->startOfDay();
        $expiry     = Carbon::parse($this->option('expiry') ?: now()->toDateString())->toDateString();

        $this->info("═══════════════════════════════════════════════════════════");
        $this->info("📊 SENSEX Expiry Candle Collector — " . $now->format('Y-m-d H:i:s'));
        $this->info("   Broker  : " . self::BROKER_CLIENT_ID);
        $this->info("   Date    : " . $tradeDate->toDateString());
        $this->info("   Expiry  : {$expiry}");
        $this->info("   Target  : sensex_expiry_candles table");
        $this->info("═══════════════════════════════════════════════════════════");
        $this->newLine();

        // ── 1. Load broker ────────────────────────────────────────────────────
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()
            ->validToken()
            ->first();

        if (!$broker) {
            $this->error("❌ Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid!");
            return 1;
        }

        $this->info("🔑 Broker authenticated: {$broker->client_name}");
        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);

        // ── 2. Resolve FUT instrument (nearest monthly FUT >= expiry) ─────────
        $futInstrument = ZerodhaInstrument::where('instrument_type', 'FUT')
            ->where('exchange', self::EXCHANGE)
            ->where('name', self::BASE_SYMBOL)
            ->whereDate('expiry', '>=', $expiry)
            ->orderBy('expiry', 'ASC')
            ->first();

        if (!$futInstrument) {
            $this->error("❌ No FUT instrument found for SENSEX with expiry >= {$expiry}");
            return 1;
        }

        $this->info("📈 FUT instrument : {$futInstrument->trading_symbol} (expiry: {$futInstrument->expiry}, token: {$futInstrument->instrument_token})");

        // ── 3. Resolve strike interval ────────────────────────────────────────
        $strikeInterval = $this->resolveStrikeInterval($expiry);

        if ($strikeInterval === null) {
            $this->error("❌ Cannot determine strike interval for SENSEX expiry {$expiry}. Check ZerodhaInstrument table.");
            return 1;
        }

        $this->info("📐 Strike interval : {$strikeInterval}");

        // ── 4. Fetch 09:15 FUT candle to freeze ATM ───────────────────────────
        $this->info("⏳ Fetching FUT candles to freeze ATM at 09:15 close...");

        $futCandles = $this->fetchDayCandles($futInstrument->instrument_token, $tradeDate, $maxRetries, $retryDelay);

        if (empty($futCandles)) {
            $this->error("❌ Could not fetch FUT candles for {$tradeDate->toDateString()}");
            return 1;
        }

        $futCandleMap = $this->indexCandlesByTime($futCandles);

        if (!isset($futCandleMap['09:15'])) {
            $this->error("❌ 09:15 FUT candle missing — cannot freeze ATM");
            return 1;
        }

        $openCandle  = $futCandleMap['09:15'];
        $frozenAtm   = round($openCandle->close / $strikeInterval) * $strikeInterval;
        $strikes     = $this->buildStrikeList($frozenAtm, $strikeInterval);

        $this->info("✅ FUT 09:15 close = {$openCandle->close} | ATM frozen = {$frozenAtm}");
        $this->info("   Strikes (" . count($strikes) . "): " . implode(', ', $strikes));
        $this->newLine();

        // ── 5. Generate all 15-min intervals ──────────────────────────────────
        $intervals = $this->generateTradingIntervals($tradeDate);
        $this->info("🕐 Intervals to collect: " . count($intervals) . " (09:15 → 15:29, 1-min candles)");
        $this->newLine();

        // ── 6. Pre-warm instrument cache ──────────────────────────────────────
        $this->info("📦 Loading CE/PE instruments from ZerodhaInstrument...");

        $instruments = ZerodhaInstrument::where('name', self::BASE_SYMBOL)
            ->where('exchange', self::EXCHANGE)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->whereIn('strike', $strikes)
            ->get()
            ->keyBy(fn($i) => "{$i->strike}_{$i->instrument_type}");

        $this->info("   Found {$instruments->count()} option instruments");
        $this->newLine();

        // ── 7. Fetch all option candles ───────────────────────────────────────
        $this->info("📡 Fetching CE/PE candles from Zerodha historical API...");
        $this->newLine();

        $optionCandleMap = []; // token => [timeKey => candle]
        $fetchCount      = 0;

        foreach (['CE', 'PE'] as $optionType) {
            $this->info("   ── {$optionType} ──────────────────────────────────────");

            foreach ($strikes as $strike) {
                $instKey    = "{$strike}_{$optionType}";
                $instrument = $instruments[$instKey] ?? null;

                if (!$instrument) {
                    $this->warn("   ⚠️  {$optionType} {$strike} — no instrument found in DB, skipping");
                    Log::warning("CollectSensexExpiryCandles: Instrument not found — SENSEX {$optionType} {$strike} expiry {$expiry}");
                    continue;
                }

                // Rate-limit guard: 400ms between requests
                if ($fetchCount > 0) {
                    usleep(400_000);
                }

                $candles = $this->fetchDayCandles($instrument->instrument_token, $tradeDate, $maxRetries, $retryDelay);
                $fetchCount++;

                if (!empty($candles)) {
                    $optionCandleMap[$instrument->instrument_token] = $this->indexCandlesByTime($candles);
                    $this->info("   {$optionType} {$strike}: ✅ " . count($candles) . " candles");
                } else {
                    $optionCandleMap[$instrument->instrument_token] = [];
                    $this->warn("   {$optionType} {$strike}: ⚠️  no data — zero-filled");
                    Log::warning("CollectSensexExpiryCandles: No candles for SENSEX {$optionType} {$strike} on {$tradeDate->toDateString()}");
                }
            }
        }

        $this->newLine();

        // ── 8. Build rows ─────────────────────────────────────────────────────
        $this->info("🔨 Building rows...");

        $rows       = [];
        $nowStr     = now()->toDateTimeString();
        $lastFutClose = null;

        foreach ($intervals as $intervalTime) {
            $timeKey   = $intervalTime->format('H:i');
            $futCandle = $futCandleMap[$timeKey] ?? null;

            if ($futCandle !== null) {
                $lastFutClose = $futCandle->close;
            }

            foreach (['CE', 'PE'] as $optionType) {
                foreach ($strikes as $strike) {
                    $instKey    = "{$strike}_{$optionType}";
                    $instrument = $instruments[$instKey] ?? null;

                    if (!$instrument) {
                        continue; // Already warned above
                    }

                    $token     = $instrument->instrument_token;
                    $candle    = $optionCandleMap[$token][$timeKey] ?? null;
                    $isMissing = ($candle === null);

                    $rows[] = [
                        'broker_api_id'    => $broker->id,
                        'trade_date'       => $tradeDate->toDateString(),
                        'expiry_date'      => $expiry,
                        'interval_time'    => $intervalTime->toDateTimeString(),
                        'base_symbol'      => self::BASE_SYMBOL,
                        'future_symbol'    => $futInstrument->trading_symbol,
                        'future_price'     => $lastFutClose,
                        'atm_strike'       => $frozenAtm,
                        'instrument_type'  => $optionType,
                        'strike'           => $strike,
                        'strike_position'  => $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                        'trading_symbol'   => $instrument->trading_symbol,
                        'instrument_token' => $token,
                        'open'             => $candle ? $candle->open   : 0,
                        'high'             => $candle ? $candle->high   : 0,
                        'low'              => $candle ? $candle->low    : 0,
                        'close'            => $candle ? $candle->close  : 0,
                        'volume'           => $candle ? $candle->volume : 0,
                        'oi'               => $candle ? ($candle->oi ?? 0) : 0,
                        'is_missing'       => $isMissing ? 1 : 0,
                        'created_at'       => $nowStr,
                        'updated_at'       => $nowStr,
                    ];
                }
            }
        }

        $this->info("   Total rows to upsert: " . count($rows));
        $this->newLine();

        // ── 9. Batch upsert ───────────────────────────────────────────────────
        $this->info("💾 Upserting into sensex_expiry_candles...");

        $upserted = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table('sensex_expiry_candles')->upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                [
                    'future_price', 'atm_strike', 'open', 'high', 'low', 'close',
                    'volume', 'oi', 'is_missing', 'updated_at',
                ]
            );
            $upserted += count($chunk);
        }

        $this->newLine();
        $this->info("✅ Done! {$upserted} rows upserted into sensex_expiry_candles.");
        $this->info("   Trade date : {$tradeDate->toDateString()}");
        $this->info("   Expiry     : {$expiry}");
        $this->info("   ATM        : {$frozenAtm} | Interval: {$strikeInterval}");
        $this->info("   Completed  : " . Carbon::now()->format('H:i:s'));

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveStrikeInterval(string $expiry): ?float
    {
        $strikes = ZerodhaInstrument::where('name', self::BASE_SYMBOL)
            ->where('exchange', self::EXCHANGE)
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($s) => (float) $s)
            ->unique()
            ->sort()
            ->values();

        if ($strikes->count() < 2) {
            return null;
        }

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) {
                $minGap = $gap;
            }
        }

        return ($minGap === PHP_INT_MAX || $minGap <= 0) ? null : (float) $minGap;
    }

    private function buildStrikeList(float $atm, float $interval): array
    {
        $list = [];
        for ($i = -7; $i <= 7; $i++) {
            $list[] = $atm + ($interval * $i);
        }
        return $list; // 15 strikes: ATM-7 … ATM … ATM+7
    }

    private function generateTradingIntervals(Carbon $date): array
    {
        $intervals = [];
        $current   = $date->copy()->setTime(9, 15);
        $end       = $date->copy()->setTime(15, 15);

        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinute();
        }

        return $intervals; // 375 slots (09:15 → 15:29, 1-min candles)
    }

    private function fetchDayCandles(int $token, Carbon $date, int $maxRetries, int $retryDelay): array
    {
        $from    = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $to      = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');
        $attempt = 1;

        while ($attempt <= $maxRetries) {
            try {
                $data = $this->zerodhaHelper->getHistoricalDataByToken($token, 'minute', $from, $to);
                return $data ?? [];
            } catch (Exception $e) {
                $isRate = stripos($e->getMessage(), 'too many') !== false
                    || stripos($e->getMessage(), 'rate limit') !== false
                    || stripos($e->getMessage(), '429') !== false;

                if ($attempt < $maxRetries) {
                    $wait = $isRate ? max($retryDelay, 2) : $retryDelay;
                    $this->warn("      ⏳ Attempt {$attempt}/{$maxRetries} failed" . ($isRate ? ' [rate limited]' : '') . " — retrying in {$wait}s");
                    sleep($wait);
                    $attempt++;
                } else {
                    $this->error("      ✗ Failed after {$maxRetries} attempts: {$e->getMessage()}");
                    Log::error("CollectSensexExpiryCandles: fetchDayCandles failed for token {$token}: {$e->getMessage()}");
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
        $diff = (int) round(($strike - $atm) / $interval);
        return match (true) {
            $diff === 0  => 'ATM',
            $diff === 1  => 'ATM+1',
            $diff === -1 => 'ATM-1',
            default      => 'N/A',
        };
    }
}