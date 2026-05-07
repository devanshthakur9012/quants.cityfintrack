<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\Mcx3HrOhlcData;
use App\Models\Mcx3HrOhlcSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\McxExpiryResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * LiveMcx3HrOhlcCollector
 *
 * Same 3 fixes as CollectMcx3HrOhlcData:
 *   FIX 1: Separate FUT vs OPT expiry  (MCX FUT expiry != MCX option expiry)
 *   FIX 2: segment=MCX-OPT filter      (excludes NCO-OPT duplicates)
 *   FIX 3: float-safe makeCacheKey     (number_format prevents cache misses)
 *
 * Cron:
 *   $schedule->command('mcx:live-collect-3hr')->cron('2 12,15,18,21 * * 1-5');
 *   $schedule->command('mcx:live-collect-3hr')->cron('2 0 * * 2-6');
 *
 * Slot timing:
 *   09:00 slot -> closes 11:59 -> collect at 12:02
 *   12:00 slot -> closes 14:59 -> collect at 15:02
 *   15:00 slot -> closes 23:30 -> collect next day 00:02
 */
class LiveMcx3HrOhlcCollector extends Command
{
    use McxExpiryResolver;

    private const BROKER_CLIENT_ID = 'OQJ978';
    private const SLOTS            = ['09:00', '12:00', '15:00', '18:00', '21:00'];

    protected $signature = 'mcx:live-collect-3hr
                            {--symbol=       : Specific symbol e.g. CRUDEOIL}
                            {--force-date=   : Override trade date (Y-m-d)}
                            {--force-slot=   : Force slot e.g. 09:00|12:00|15:00}
                            {--retry=3       : API retries}
                            {--retry-delay=2 : Seconds between retries}
                            {--chunk=50      : DB upsert chunk size}';

    protected $description = 'Live 3-Hr MCX OHLC — gap-fill, separate FUT/OPT expiry, MCX-OPT segment';

    private array $instrumentCache = [];
    private ?BrokerZerodhaHelper $zerodhaHelper = null;

    public function handle(): int
    {
        $now        = Carbon::now();
        $maxRetries = (int)$this->option('retry');
        $retryDelay = (int)$this->option('retry-delay');
        $chunkSize  = (int)$this->option('chunk');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;

        // Midnight run (00:02) collects previous day's 15:00 bar
        if ($this->option('force-date')) {
            $tradeDate = Carbon::parse($this->option('force-date'));
        } elseif ($now->hour === 0) {
            $tradeDate = $now->copy()->subDay();
        } else {
            $tradeDate = $now->copy()->startOfDay();
        }

        $forcedSlot        = $this->option('force-slot');
        $lastCompletedSlot = $forcedSlot ?? $this->getLastCompletedSlot($now);

        if (!$lastCompletedSlot) {
            $this->warn('No completed 3-Hr slot yet.');
            return 0;
        }

        $slotsToProcess = array_values(array_filter(
            self::SLOTS, fn($s) => $s <= $lastCompletedSlot
        ));

        $this->info("MCX Live 3-Hr | {$tradeDate->toDateString()} | Slots: " . implode(', ', $slotsToProcess));
        $this->info("Fixes: separate FUT/OPT expiry | segment=MCX-OPT | float-safe cache");

        // Load broker
        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()->validToken()->first();

        if (!$broker) {
            $this->error("Broker [" . self::BROKER_CLIENT_ID . "] not found or token invalid!");
            return 1;
        }

        $this->zerodhaHelper = new BrokerZerodhaHelper($broker);
        $this->info("Broker: {$broker->client_name}");

        // Load symbols
        $symbolsQuery = Mcx3HrOhlcSymbol::active();
        if ($specSymbol) $symbolsQuery->where('symbol', $specSymbol);
        $symbolRows = $symbolsQuery->get()->keyBy('symbol');

        if ($symbolRows->isEmpty()) {
            $this->error('No active symbols in mcx_3hr_ohlc_symbols!');
            return 1;
        }

        $this->info("Symbols: " . $symbolRows->keys()->join(', '));

        foreach ($symbolRows as $symbol => $symRow) {
            $this->instrumentCache = [];
            $strikeInterval = (float)$symRow->strike_interval;

            // Which slots already stored?
            $storedSlots = Mcx3HrOhlcData::whereDate('trade_date', $tradeDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->where('is_missing', 0)
                ->pluck('interval_time')
                ->map(fn($t) => Carbon::parse($t)->format('H:i'))
                ->flip()->toArray();

            $missingSlots = array_values(array_filter(
                $slotsToProcess, fn($s) => !isset($storedSlots[$s])
            ));

            if (empty($missingSlots)) {
                $this->info("  [{$symbol}] All slots up to {$lastCompletedSlot} stored. OK");
                continue;
            }

            $this->info("  [{$symbol}] Missing: " . implode(', ', $missingSlots));

            // FIX 1: Separate FUT + OPT expiry
            $futExpiries  = $this->resolveMcxFutExpiries($symbol, $tradeDate);
            if (empty($futExpiries)) { $this->warn("  [{$symbol}] No FUT expiry"); continue; }

            $optionExpiry = $this->getNearestMcxOptionExpiry($symbol, $tradeDate);

            $this->info("  [{$symbol}] FUT:" . implode('+', $futExpiries) . " | OPT:" . ($optionExpiry ?? 'none'));

            // FIX 2+3: Pre-warm with MCX-OPT segment + float-safe keys
            if ($optionExpiry) {
                $cached = $this->prewarmOptionCache($symbol, $optionExpiry);
                $this->info("  [{$symbol}] Cached {$cached} option instruments (MCX-OPT)");
            }

            $futExpiry     = $futExpiries[0];
            $futInstrument = $this->resolveFutInstrument($symbol, $futExpiry);

            if (!$futInstrument) { $this->warn("  [{$symbol}] No FUT instrument"); continue; }

            // Fetch 60-min FUT candles
            $fut60 = $this->fetch60Min($broker, $futInstrument->instrument_token, $tradeDate, $maxRetries, $retryDelay);
            if (empty($fut60)) { $this->error("  [{$symbol}] No FUT data"); continue; }

            $futCandleMap  = $this->aggregate60to3Hr($fut60);
            $byTime        = $this->indexByTime($fut60);
            $openClose     = $byTime['09:00']->close ?? ($fut60[0]->close ?? 0);
            $frozenAtm     = round($openClose / $strikeInterval) * $strikeInterval;
            $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);

            $this->info("  [{$symbol}] ATM={$frozenAtm} FUT={$futInstrument->trading_symbol}");

            // Fetch option candles
            $optionCache = [];
            if ($optionExpiry) {
                $optionCache = $this->fetchAllOptionCandles(
                    $broker, $symbol, $frozenStrikes, $optionExpiry, $tradeDate, $maxRetries, $retryDelay
                );
            }

            // Build rows for missing slots
            $rows = [];
            $nowStr = now()->toDateTimeString();
            $lastFutClose = null;

            foreach ($missingSlots as $slotStr) {
                $intervalTime = Carbon::parse($tradeDate->toDateString() . ' ' . $slotStr);
                $futCandle    = $futCandleMap[$slotStr] ?? null;
                if ($futCandle) $lastFutClose = $futCandle->close;

                $rows[] = $this->buildFutRow(
                    $broker->id, $symbol, $futInstrument,
                    $futCandle, $frozenAtm, $futExpiry,
                    $tradeDate, $intervalTime, $nowStr, $futCandle === null
                );

                if (!$optionExpiry) continue;

                foreach (['CE', 'PE'] as $type) {
                    foreach ($frozenStrikes as $strike) {
                        $cacheKey   = $this->makeCacheKey($symbol, $strike, $type, $optionExpiry);
                        $instrument = $this->instrumentCache[$cacheKey] ?? null;
                        if (!$instrument) continue;

                        $token  = $instrument->instrument_token;
                        $candle = $optionCache[$token][$slotStr] ?? null;

                        $rows[] = $this->buildOptionRow(
                            $broker->id, $symbol,
                            $futInstrument->trading_symbol, $lastFutClose,
                            $frozenAtm, $type, $strike,
                            $this->strikePosition($strike, $frozenAtm, $strikeInterval),
                            $instrument, $candle, $optionExpiry,
                            $tradeDate, $intervalTime, $nowStr, $candle === null
                        );
                    }
                }
            }

            $total = $this->batchUpsert($rows, $chunkSize);
            $this->info("  [{$symbol}] {$total} rows upserted | " . implode(', ', $missingSlots));
        }

        $this->info("MCX Live 3-Hr done: " . Carbon::now()->format('H:i:s'));
        return 0;
    }

    private function getLastCompletedSlot(Carbon $now): ?string
    {
        $hour = (int)$now->format('H');
        if ($hour === 0)  return '15:00';
        if ($hour >= 15)  return '12:00';
        if ($hour >= 12)  return '09:00';
        return null;
    }

    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
        return ZerodhaInstrument::where('exchange', 'MCX')
            ->where('segment', 'MCX-FUT')
            ->where('instrument_type', 'FUT')
            ->where('name', $symbol)
            ->whereDate('expiry', $expiry)
            ->first();
    }

    private function prewarmOptionCache(string $symbol, string $optionExpiry): int
    {
        $instruments = ZerodhaInstrument::where('exchange', 'MCX')
            ->where('segment', 'MCX-OPT')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('name', $symbol)
            ->whereDate('expiry', $optionExpiry)
            ->get();

        foreach ($instruments as $inst) {
            $key = $this->makeCacheKey($symbol, (float)$inst->strike, $inst->instrument_type, $optionExpiry);
            $this->instrumentCache[$key] = $inst;
        }
        return $instruments->count();
    }

    private function makeCacheKey(string $symbol, float $strike, string $type, string $expiry): string
    {
        return "{$symbol}_" . number_format($strike, 2, '.', '') . "_{$type}_{$expiry}";
    }

    private function aggregate60to3Hr(array $candles60): array
    {
        $byTime = $this->indexByTime($candles60);
        ksort($byTime);
        $slotMap = [];
        foreach ($byTime as $timeStr => $candle) {
            $hour = (int)explode(':', $timeStr)[0];
            $slot = match (true) {
                $hour >= 9  && $hour < 12 => '09:00',
                $hour >= 12 && $hour < 15 => '12:00',
                $hour >= 15 && $hour < 18 => '15:00',
                $hour >= 18 && $hour < 21 => '18:00',
                $hour >= 21               => '21:00',
                default                   => null,
            };
            if ($slot) $slotMap[$slot][] = $candle;
        }
        $result = [];
        foreach ($slotMap as $slot => $candles) {
            $bar         = new \stdClass();
            $bar->open   = $candles[0]->open;
            $bar->high   = max(array_map(fn($c) => $c->high,   $candles));
            $bar->low    = min(array_map(fn($c) => $c->low,    $candles));
            $bar->close  = end($candles)->close;
            $bar->volume = array_sum(array_map(fn($c) => $c->volume ?? 0, $candles));
            $bar->oi     = end($candles)->oi ?? 0;
            $result[$slot] = $bar;
        }
        return $result;
    }

    private function fetchAllOptionCandles(
        BrokerApi $broker, string $symbol, array $strikes,
        string $optionExpiry, Carbon $date, int $maxRetries, int $retryDelay
    ): array {
        $cache = [];
        foreach (['CE', 'PE'] as $type) {
            foreach ($strikes as $strike) {
                $key        = $this->makeCacheKey($symbol, $strike, $type, $optionExpiry);
                $instrument = $this->instrumentCache[$key] ?? null;
                if (!$instrument) continue;
                $token         = $instrument->instrument_token;
                $c60           = $this->fetch60Min($broker, $token, $date, $maxRetries, $retryDelay);
                $cache[$token] = !empty($c60) ? $this->aggregate60to3Hr($c60) : [];
            }
        }
        return $cache;
    }

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;
        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            Mcx3HrOhlcData::upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                ['open','high','low','close','volume','oi','future_price','atm_strike',
                 'strike_position','expiry_date','is_missing','updated_at']
            );
            $total += count($chunk);
        }
        return $total;
    }

    private function buildFutRow(
        int $brokerId, string $symbol, ZerodhaInstrument $futInst,
        $candle, float $atmStrike, string $expiry,
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'base_symbol'      => $symbol,
            'future_symbol'    => $futInst->trading_symbol,
            'future_price'     => $candle?->close  ?? 0,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'trading_symbol'   => $futInst->trading_symbol,
            'instrument_token' => $futInst->instrument_token,
            'open'             => $candle?->open   ?? 0,
            'high'             => $candle?->high   ?? 0,
            'low'              => $candle?->low    ?? 0,
            'close'            => $candle?->close  ?? 0,
            'volume'           => $candle?->volume ?? 0,
            'oi'               => $candle?->oi     ?? 0,
            'strike_position'  => 'N/A',
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function buildOptionRow(
        int $brokerId, string $symbol, string $futSymbol, ?float $futPrice,
        float $atmStrike, string $type, float $strike, string $strikePos,
        ZerodhaInstrument $inst, $candle, string $expiry,
        Carbon $tradeDate, Carbon $intervalTime, string $now, bool $isMissing
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'interval_time'    => $intervalTime->toDateTimeString(),
            'base_symbol'      => $symbol,
            'future_symbol'    => $futSymbol,
            'future_price'     => $futPrice,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => $type,
            'strike'           => $strike,
            'trading_symbol'   => $inst->trading_symbol,
            'instrument_token' => $inst->instrument_token,
            'open'             => $candle?->open   ?? 0,
            'high'             => $candle?->high   ?? 0,
            'low'              => $candle?->low    ?? 0,
            'close'            => $candle?->close  ?? 0,
            'volume'           => $candle?->volume ?? 0,
            'oi'               => $candle?->oi     ?? 0,
            'strike_position'  => $strikePos,
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function fetch60Min(BrokerApi $broker, int $token, Carbon $date, int $maxRetries, int $retryDelay): array
    {
        $from = $date->copy()->setTime(9, 0)->format('Y-m-d H:i:s');
        $to   = $date->copy()->setTime(23, 30)->format('Y-m-d H:i:s');
        for ($i = 1; $i <= $maxRetries; $i++) {
            try {
                return $this->zerodhaHelper->getHistoricalDataByToken($token, '60minute', $from, $to) ?? [];
            } catch (Exception $e) {
                if ($i < $maxRetries) { sleep($retryDelay); continue; }
                return [];
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

    private function buildStrikeList(float $atm, float $interval): array
    {
        $strikes = [];
        for ($i = -5; $i <= 5; $i++) {
            $strikes[] = round($atm + ($i * $interval), 2);
        }
        return $strikes;
    }

    private function strikePosition(float $strike, float $atm, float $interval): string
    {
        if (abs($strike - $atm) < 0.001) return 'ATM';
        $diff = (int) round(($strike - $atm) / $interval);
        return $diff > 0 ? "ATM+{$diff}" : "ATM{$diff}";
    }
}