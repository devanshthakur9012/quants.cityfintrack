<?php
// FILE: app/Traits/CpOhlcBase.php

namespace App\Traits;

use App\Models\AnalysisConfig;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

trait CpOhlcBase
{
    private const MARKET_OPEN  = '09:15';
    private const MARKET_CLOSE = '15:15';

    private const KITE_INTERVAL_MAP = [
        '15min' => '15minute',
        '30min' => '30minute',
        '1hr'   => '60minute',
    ];

    private const TF_MINUTES = [
        '15min' => 15,
        '30min' => 30,
        '1hr'   => 60,
    ];

    private const BACKFILL_DAYS = 5;

    private ?BrokerZerodhaHelper $cpHelper = null;
    private ?BrokerApi $cpBroker = null;
    private array $cpInstrumentCache = [];

    // ─────────────────────────────────────────────────────────────────────────
    // Config + Broker
    // ─────────────────────────────────────────────────────────────────────────

    protected function loadConfig(string $timeframe): ?AnalysisConfig
    {
        $config = AnalysisConfig::with(['broker', 'symbols'])
            ->where('time_frame', $timeframe)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            $this->error("❌ No active analysis config found for timeframe [{$timeframe}].");
            $this->line("   Go to Admin → Analysis Config and create one first.");
            return null;
        }

        return $config;
    }

    protected function initBroker(AnalysisConfig $config): bool
    {
        $broker = $config->broker;

        if (!$broker) {
            $this->error("❌ Broker not found for config ID {$config->id}");
            return false;
        }

        if (!$broker->is_token_valid || ($broker->token_expires_at && $broker->token_expires_at->isPast())) {
            $this->error("❌ Broker [{$broker->account_user_name}] token is invalid or expired.");
            $this->line("   Go to Admin → Zerodha Broker → Login to refresh token.");
            return false;
        }

        try {
            $this->cpHelper = new BrokerZerodhaHelper($broker);
            $this->cpBroker = $broker;
            $this->info("🔑 Broker: {$broker->account_user_name} (ID: {$broker->id})");
            return true;
        } catch (Exception $e) {
            $this->error("❌ Failed to init broker helper: {$e->getMessage()}");
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Date range
    // ─────────────────────────────────────────────────────────────────────────

    protected function resolveDateRange(): array
    {
        $from = $this->option('from');
        $to   = $this->option('to');

        if ($from) {
            $dateFrom = Carbon::parse($from)->startOfDay();
            $dateTo   = $to ? Carbon::parse($to)->startOfDay() : Carbon::today();

            if ($dateFrom->gt($dateTo)) {
                $this->error("❌ --from must be <= --to");
                return [null, null];
            }

            return [$dateFrom, $dateTo];
        }

        return [Carbon::today(), Carbon::today()];
    }

    protected function getTradingDays(Carbon $from, Carbon $to): array
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

    /**
     * Renamed from isMarketHoliday → cpIsMarketHoliday
     * to avoid collision with OptionExpiryResolver::isMarketHoliday
     * when both traits are used together in CpCollectFut / CpCollectOption.
     */
    protected function cpIsMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Intervals
    // ─────────────────────────────────────────────────────────────────────────

    protected function generateIntervals(Carbon $date, string $timeframe): array
    {
        $minutes   = self::TF_MINUTES[$timeframe] ?? 15;
        $intervals = [];
        $current   = $date->copy()->setTimeFromTimeString(self::MARKET_OPEN);
        $end       = $date->copy()->setTimeFromTimeString(self::MARKET_CLOSE);

        while ($current->lte($end)) {
            $intervals[] = $current->copy();
            $current->addMinutes($minutes);
        }

        return $intervals;
    }

    protected function getLastCompletedSlot(Carbon $now, Carbon $date, string $timeframe): Carbon
    {
        $minutes     = self::TF_MINUTES[$timeframe] ?? 15;
        $marketStart = $date->copy()->setTimeFromTimeString(self::MARKET_OPEN);
        $marketEnd   = $date->copy()->setTimeFromTimeString(self::MARKET_CLOSE);

        if ($now->lt($marketStart)) {
            return $marketStart->copy();
        }

        $flooredMin = (int)(floor((int)$now->format('i') / $minutes) * $minutes);
        $slot       = $date->copy()->setTime((int)$now->format('H'), $flooredMin, 0);
        $slot->subMinutes($minutes);

        return match (true) {
            $slot->lt($marketStart) => $marketStart->copy(),
            $slot->gt($marketEnd)   => $marketEnd->copy(),
            default                 => $slot,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Candle fetching
    // ─────────────────────────────────────────────────────────────────────────

    protected function fetchDayCandles(
        int    $token,
        Carbon $date,
        string $timeframe,
        int    $maxRetries = 3,
        int    $retryDelay = 2
    ): array {
        $kiteInterval = self::KITE_INTERVAL_MAP[$timeframe] ?? '15minute';
        $fromTime     = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $toTime       = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $data = $this->cpHelper->getHistoricalDataByToken(
                    $token, $kiteInterval, $fromTime, $toTime
                );
                return $data ?? [];
            } catch (Exception $e) {
                $isRateLimit = stripos($e->getMessage(), 'too many') !== false
                    || stripos($e->getMessage(), 'rate limit') !== false
                    || stripos($e->getMessage(), '429') !== false;

                if ($attempt < $maxRetries) {
                    $wait = $isRateLimit ? max($retryDelay, 3) : $retryDelay;
                    $this->warn("      ⏳ Retry {$attempt}/{$maxRetries}"
                        . ($isRateLimit ? ' [rate limited]' : '')
                        . ": {$e->getMessage()} — wait {$wait}s");
                    sleep($wait);
                    $attempt++;
                } else {
                    Log::error("CpOhlcBase: fetchDayCandles failed for token {$token} after {$maxRetries} retries: {$e->getMessage()}");
                    $this->error("      ✗ Fetch failed after {$maxRetries} retries: {$e->getMessage()}");
                    return [];
                }
            }
        }

        return [];
    }

    protected function indexCandlesByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $candle) {
            $map[Carbon::parse($candle->date)->format('H:i')] = $candle;
        }
        return $map;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Instrument resolution
    // ─────────────────────────────────────────────────────────────────────────

    protected function resolveStockInstrument(string $symbol, string $exchange = 'NSE'): ?ZerodhaInstrument
    {
        $key = "EQ_{$symbol}_{$exchange}";
        if (isset($this->cpInstrumentCache[$key])) {
            return $this->cpInstrumentCache[$key];
        }

        $inst = ZerodhaInstrument::where('exchange', $exchange)
            ->whereIn('instrument_type', ['EQ', 'BE', 'INDEX', 'INDICES'])
            ->where(function ($q) use ($symbol) {
                $q->where('trading_symbol', $symbol)->orWhere('name', $symbol);
            })
            ->orderByRaw("FIELD(instrument_type,'EQ','BE','INDEX','INDICES')")
            ->first();

        $this->cpInstrumentCache[$key] = $inst;
        return $inst;
    }

    protected function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        $key = "FUT_{$symbol}_{$expiry}";
        if (isset($this->cpInstrumentCache[$key])) {
            return $this->cpInstrumentCache[$key];
        }

        $exchange = $this->getCpExchange($symbol);
        $isWeekly = in_array($symbol, ['NIFTY', 'SENSEX']);

        if ($isWeekly) {
            $inst = ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', $exchange)
                ->where('name', $symbol)
                ->whereDate('expiry', '>=', $expiry)
                ->orderBy('expiry', 'ASC')
                ->first();
        } else {
            $inst = ZerodhaInstrument::where('instrument_type', 'FUT')
                ->where('exchange', $exchange)
                ->whereDate('expiry', $expiry)
                ->where(function ($q) use ($symbol) {
                    $q->where('name', $symbol)
                      ->orWhere('trading_symbol', 'LIKE', $symbol . '%');
                })
                ->first();
        }

        $this->cpInstrumentCache[$key] = $inst;
        return $inst;
    }

    protected function prewarmOptionCache(string $symbol, string $expiry): int
    {
        $exchange    = $this->getCpExchange($symbol);
        $instruments = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $expiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = "OPT_{$symbol}_{$inst->strike}_{$inst->instrument_type}_{$expiry}";
            $this->cpInstrumentCache[$key] = $inst;
        }

        return $instruments->count();
    }

    protected function getOptionInstrument(
        string $symbol,
        float  $strike,
        string $type,
        string $expiry
    ): ?ZerodhaInstrument {
        $key = "OPT_{$symbol}_{$strike}_{$type}_{$expiry}";
        return $this->cpInstrumentCache[$key] ?? null;
    }

    protected function resolveStrikeInterval(string $symbol, string $expiry): ?float
    {
        $key = "SI_{$symbol}_{$expiry}";
        if (isset($this->cpInstrumentCache[$key])) {
            return $this->cpInstrumentCache[$key];
        }

        $exchange = $this->getCpExchange($symbol);
        $strikes  = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', $expiry)
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($s) => (float) $s)
            ->unique()
            ->sort()
            ->values();

        if ($strikes->count() < 2) {
            Log::warning("CpOhlcBase: resolveStrikeInterval — < 2 CE strikes for {$symbol} [{$expiry}]");
            return null;
        }

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) {
                $minGap = $gap;
            }
        }

        if ($minGap === PHP_INT_MAX || $minGap <= 0) {
            return null;
        }

        $this->cpInstrumentCache[$key] = (float) $minGap;
        return (float) $minGap;
    }

    protected function buildStrikeList(float $atm, float $interval): array
    {
        $strikes = [];
        for ($i = -5; $i <= 5; $i++) {
            $strikes[] = $atm + ($i * $interval);
        }
        return $strikes;
    }

    protected function getStrikePosition(float $strike, float $atm, float $interval): string
    {
        $diff = (int) round(($strike - $atm) / $interval);
        if ($diff === 0) return 'ATM';
        if ($diff > 0)   return "ATM+{$diff}";
        if ($diff < 0)   return "ATM{$diff}";
        return 'N/A';
    }

    protected function getCpExchange(string $symbol): string
    {
        return in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // New series backfill detection
    // ─────────────────────────────────────────────────────────────────────────

    protected function detectNewSeriesBackfillDays(
        string $table,
        int    $configId,
        string $symbol,
        string $expiry,
        Carbon $today,
        string $symCol = 'base_symbol'
    ): array {
        $exists = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->where($symCol, $symbol)
            ->where('expiry_date', $expiry)
            ->where('is_missing', false)
            ->exists();

        if ($exists) {
            return [];
        }

        $backfillFrom = $today->copy()->subDays(14);
        $all          = $this->getTradingDays($backfillFrom, $today->copy()->subDay());

        return array_slice($all, -self::BACKFILL_DAYS);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Batch upsert
    // ─────────────────────────────────────────────────────────────────────────

    protected function batchUpsertRows(
        string $table,
        array  $rows,
        array  $uniqueBy,
        array  $updateCols,
        int    $chunkSize = 100
    ): int {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateCols);
            $total += count($chunk);
        }
        return $total;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Summary
    // ─────────────────────────────────────────────────────────────────────────

    protected function printCpSummary(
        string $type,
        string $timeframe,
        string $from,
        string $to,
        int    $upserted,
        int    $skipped,
        int    $failed
    ): void {
        $this->newLine();
        $this->info("════════════════════════════════════════════");
        $this->info("  ✅ {$type} [{$timeframe}] complete — " . Carbon::now()->format('H:i:s'));
        $this->info("     Range    : {$from} → {$to}");
        $this->info("     Upserted : {$upserted} row(s)");
        $this->info("     Skipped  : {$skipped}");
        $this->info("     Failed   : {$failed}");
        $this->info("════════════════════════════════════════════");
    }
}