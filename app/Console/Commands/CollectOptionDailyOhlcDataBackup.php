<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OptionDailyOhlcData;
use App\Models\DailyOptionSymbol;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use App\Traits\OptionExpiryResolver;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * CollectOptionDailyOhlcData
 *
 * Collects daily OHLC + OI for FUT and Options (ATM ±5 strikes) – one candle per day.
 * Uses the same smart dual‑expiry logic as the 15‑minute collector.
 */
class CollectOptionDailyOhlcDataBackup extends Command
{
    use OptionExpiryResolver;

    protected $signature = 'options:collect-daily-ohlc-backup
                            {--start-date= : Start date (Y-m-d)}
                            {--end-date= : End date (Y-m-d)}
                            {--date= : Single date (Y-m-d)}
                            {--symbol= : Specific symbol (e.g., BHEL)}
                            {--broker= : Specific broker ID}
                            {--retry=3 : Number of retries on failure}
                            {--retry-delay=2 : Delay between retries in seconds}
                            {--chunk=50 : Batch insert chunk size}';

    protected $description = 'Collect daily OHLC + OI for FUT and Options – one candle per day, smart dual-expiry, frozen ATM';

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

    /** In-memory instrument cache: key = "SYMBOL_STRIKE_TYPE_EXPIRY" */
    private array $instrumentCache = [];

    /** Zerodha helper cache per broker */
    private array $zerodhaHelperCache = [];

    public function handle(): int
    {
        // Date range
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

        $specificSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $specificBroker = $this->option('broker');
        $maxRetries     = (int) $this->option('retry');
        $retryDelay     = (int) $this->option('retry-delay');
        $chunkSize      = (int) $this->option('chunk');

        $this->info('📆 Daily Option OHLC Collector – One candle per day');
        $this->info("   Date range : {$startDate->format('Y-m-d')} → {$endDate->format('Y-m-d')}");
        $this->newLine();

        // Symbols
        $symbolsQuery = DailyOptionSymbol::active();
        if ($specificSymbol) {
            $symbolsQuery->where('symbol', $specificSymbol);
        }
        $symbols = $symbolsQuery->pluck('symbol')->toArray();

        if (empty($symbols)) {
            $this->error('❌ No active symbols found in option_symbols table!');
            return 1;
        }

        $this->info('   Symbols (' . count($symbols) . '): ' . implode(', ', $symbols));
        $this->newLine();

        // Brokers
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
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

            $this->zerodhaHelperCache[$broker->id] = new BrokerZerodhaHelper($broker);

            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                if ($currentDate->isWeekend()) {
                    $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (Weekend)");
                    $currentDate->addDay();
                    continue;
                }

                if ($this->isMarketHoliday($currentDate->toDateString())) {
                    $this->warn("⏭  Skip {$currentDate->format('Y-m-d')} (Holiday)");
                    $currentDate->addDay();
                    continue;
                }

                $this->info("\n📅 {$currentDate->format('Y-m-d')}");

                try {
                    $result = $this->processDate(
                        $broker, $currentDate, $symbols,
                        $maxRetries, $retryDelay, $chunkSize
                    );
                    $totalProcessed += $result['success'];
                    $totalFailed    += $result['failed'];
                } catch (Exception $e) {
                    $this->error("Date error: " . $e->getMessage());
                    Log::error("CollectOptionDailyOhlcData date error", [
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

    private function processDate(
        BrokerApi $broker,
        Carbon $date,
        array $symbols,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): array {
        $success = 0;
        $failed  = 0;

        foreach ($symbols as $baseSymbol) {
            $expiries = $this->resolveExpiries($baseSymbol, $date);
            $this->info("\n   📊 {$baseSymbol} — expir" . (count($expiries) > 1 ? 'ies' : 'y') . ': ' . implode(' + ', $expiries));

            foreach ($expiries as $expiry) {
                $futInstrument = $this->resolveFutInstrument($baseSymbol, $expiry);
                if (!$futInstrument) {
                    $this->warn("      ⚠️  No FUT instrument for {$baseSymbol} expiry {$expiry} – skipping");
                    $failed++;
                    continue;
                }

                $this->prewarmInstrumentCacheForExpiry($baseSymbol, $expiry);
                $result = $this->processSymbolExpiry(
                    $broker, $baseSymbol, $futInstrument,
                    $expiry, $date, $maxRetries, $retryDelay, $chunkSize
                );

                $success += $result['success'];
                $failed  += $result['failed'];
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function processSymbolExpiry(
        BrokerApi $broker,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        string $expiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay,
        int $chunkSize
    ): array {
        $strikeInterval = self::STRIKE_INTERVALS[$baseSymbol] ?? 20;
        $this->info("      FUT: {$futInstrument->trading_symbol} (token: {$futInstrument->instrument_token})");

        // 1. Fetch FUT daily candle
        $futCandle = $this->fetchDailyCandle($broker, $futInstrument->instrument_token, $date, $maxRetries, $retryDelay);
        if (!$futCandle) {
            $this->error("      ✗ Could not fetch FUT daily candle – skipping expiry {$expiry}");
            return ['success' => 0, 'failed' => 1];
        }

        $this->info("      FUT daily: O={$futCandle->open} H={$futCandle->high} L={$futCandle->low} C={$futCandle->close}");

        // 2. Freeze ATM using the day's OPEN price
        $frozenAtm     = round($futCandle->open / $strikeInterval) * $strikeInterval;
        $frozenStrikes = $this->buildStrikeList($frozenAtm, $strikeInterval);
        $this->info("      ATM frozen at {$frozenAtm} (strikes: " . implode(', ', $frozenStrikes) . ")");

        // 3. Fetch option daily candles for all required strikes
        $optionCandles = $this->fetchAllOptionDailyCandles(
            $broker, $baseSymbol, $frozenStrikes, $expiry, $date, $maxRetries, $retryDelay
        );

        // 4. Build rows
        $rows = [];
        $now  = now()->toDateTimeString();

        // FUT row
        $rows[] = $this->buildFutRow(
            $broker->id, $baseSymbol, $futInstrument,
            $futCandle, $frozenAtm, $expiry, $date, $now, false
        );

        // Option rows (CE + PE)
        foreach (['CE', 'PE'] as $optionType) {
            foreach ($frozenStrikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;
                if (!$instrument) {
                    $this->warn("      ⚠️  Instrument not found: {$cacheKey}");
                    continue;
                }

                $candle    = $optionCandles[$instrument->instrument_token] ?? null;
                $isMissing = ($candle === null);

                if ($isMissing) {
                    $this->warn("      ⚠️  {$optionType} {$strike} – no data, storing zeros");
                }

                $rows[] = $this->buildOptionRow(
                    $broker->id, $baseSymbol,
                    $futInstrument->trading_symbol,
                    $futCandle->close,                     // carry‑forward FUT close
                    $frozenAtm, $optionType, $strike,
                    $this->getStrikePosition($strike, $frozenAtm, $strikeInterval),
                    $instrument, $candle, $expiry,
                    $date, $now, $isMissing
                );
            }
        }

        // 5. Batch upsert
        $inserted = $this->batchUpsert($rows, $chunkSize);
        $this->info("      ✅ {$baseSymbol} [{$expiry}] – {$inserted} rows upserted");

        return ['success' => $inserted, 'failed' => 0];
    }

    /**
     * Resolve FUT instrument by exact expiry
     */
    private function resolveFutInstrument(string $symbol, string $expiry): ?ZerodhaInstrument
    {
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

    /**
     * Pre‑warm instrument cache for all CE/PE options of a given expiry
     */
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

    /**
     * Fetch daily candles for all required option instruments
     */
    private function fetchAllOptionDailyCandles(
        BrokerApi $broker,
        string $baseSymbol,
        array $strikes,
        string $expiry,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): array {
        $candlesByToken = [];

        foreach (['CE', 'PE'] as $optionType) {
            foreach ($strikes as $strike) {
                $cacheKey   = "{$baseSymbol}_{$strike}_{$optionType}_{$expiry}";
                $instrument = $this->instrumentCache[$cacheKey] ?? null;

                if (!$instrument) {
                    continue;
                }

                $token   = $instrument->instrument_token;
                $candle  = $this->fetchDailyCandle($broker, $token, $date, $maxRetries, $retryDelay);

                if ($candle) {
                    $candlesByToken[$token] = $candle;
                    $this->info("      {$optionType} {$strike}: daily candle fetched");
                } else {
                    $candlesByToken[$token] = null;  // explicitly mark missing
                    $this->warn("      {$optionType} {$strike}: no data");
                }
            }
        }

        return $candlesByToken;
    }

    /**
     * Fetch a single daily candle for a given instrument token and date
     */
    private function fetchDailyCandle(
        BrokerApi $broker,
        int $instrumentToken,
        Carbon $date,
        int $maxRetries,
        int $retryDelay
    ): ?object {
        $from = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $to   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                $helper = $this->zerodhaHelperCache[$broker->id];
                $data   = $helper->getHistoricalDataByToken(
                    $instrumentToken, 'day', $from, $to
                );
                // API returns an array of candles (should be one)
                return $data[0] ?? null;
            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->warn("      ⏳ Fetch attempt {$attempt}/{$maxRetries} failed: {$e->getMessage()}");
                    sleep($retryDelay);
                    $attempt++;
                } else {
                    $this->error("      ✗ Fetch failed after {$maxRetries} attempts");
                    return null;
                }
            }
        }

        return null;
    }

    private function batchUpsert(array $rows, int $chunkSize): int
    {
        if (empty($rows)) return 0;

        $total = 0;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            OptionDailyOhlcData::upsert(
                $chunk,
                ['broker_api_id', 'trade_date', 'trading_symbol'],
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

    private function buildFutRow(
        int $brokerId,
        string $baseSymbol,
        ZerodhaInstrument $futInstrument,
        object $candle,
        float $atmStrike,
        string $expiry,
        Carbon $tradeDate,
        string $now,
        bool $isMissing = false
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'trading_symbol'   => $futInstrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futInstrument->trading_symbol,
            'future_price'     => $candle->close,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => 'FUT',
            'strike'           => null,
            'instrument_token' => $futInstrument->instrument_token,
            'open'             => $candle->open,
            'high'             => $candle->high,
            'low'              => $candle->low,
            'close'            => $candle->close,
            'volume'           => $candle->volume ?? 0,
            'oi'               => $candle->oi ?? 0,
            'strike_position'  => 'N/A',
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function buildOptionRow(
        int $brokerId,
        string $baseSymbol,
        string $futureSymbol,
        float $futurePrice,
        float $atmStrike,
        string $optionType,
        float $strike,
        string $strikePosition,
        ZerodhaInstrument $instrument,
        ?object $candle,
        string $expiry,
        Carbon $tradeDate,
        string $now,
        bool $isMissing = false
    ): array {
        return [
            'broker_api_id'    => $brokerId,
            'trade_date'       => $tradeDate->toDateString(),
            'trading_symbol'   => $instrument->trading_symbol,
            'base_symbol'      => $baseSymbol,
            'future_symbol'    => $futureSymbol,
            'future_price'     => $futurePrice,
            'atm_strike'       => $atmStrike,
            'instrument_type'  => $optionType,
            'strike'           => $strike,
            'instrument_token' => $instrument->instrument_token,
            'open'             => $candle->open ?? 0,
            'high'             => $candle->high ?? 0,
            'low'              => $candle->low ?? 0,
            'close'            => $candle->close ?? 0,
            'volume'           => $candle->volume ?? 0,
            'oi'               => $candle->oi ?? 0,
            'strike_position'  => $strikePosition,
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    private function buildStrikeList(float $atm, float $interval): array
    {
        return [
            $atm - $interval,
            $atm,
            $atm + $interval,
        ];
    }

    private function getStrikePosition(float $strike, float $atm, float $interval): string
    {
        if ($strike == $atm)               return 'ATM';
        if ($strike == $atm + $interval)   return 'ATM+1';
        if ($strike == $atm - $interval)   return 'ATM-1';
        return 'N/A';
    }
}