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

/**
 * GapFillOptionOhlcData
 *
 * Runs AFTER the main collector each day.
 *
 * Problem it solves:
 *   When today's ATM shifts from yesterday's ATM, yesterday's DB
 *   does not contain today's ATM strike — causing 0% OI change
 *   (false FLAT/NEUTRAL signal) in all analysis pages.
 *
 * What it does:
 *   For each symbol:
 *     1. Finds today's ATM strike (from today's collected data)
 *     2. Checks if yesterday's DB has ALL of today's ±2 strikes
 *     3. For any missing strikes → fetches from Zerodha API for yesterday
 *     4. Upserts the fetched candles into DB alongside existing data
 *
 * Usage:
 *   php artisan options:gap-fill                        ← fills yesterday vs today
 *   php artisan options:gap-fill --date=2026-02-19      ← fills prev day of given date
 *   php artisan options:gap-fill --start-date=2026-02-01 --end-date=2026-02-19
 *   php artisan options:gap-fill --symbol=ASIANPAINT    ← single symbol
 *   php artisan options:gap-fill --dry-run              ← just show what would be filled
 *
 * Schedule (add to App\Console\Kernel):
 *   $schedule->command('options:gap-fill')->dailyAt('15:45');
 */
class GapFillOptionOhlcData extends Command
{
    protected $signature = 'options:gap-fill
                            {--date=        : Fill gaps for prev day of this date (Y-m-d)}
                            {--start-date=  : Range start (Y-m-d)}
                            {--end-date=    : Range end (Y-m-d)}
                            {--symbol=      : Specific symbol only}
                            {--broker=      : Specific broker ID}
                            {--lookback=3   : How many prev days to gap-fill for each signal date}
                            {--dry-run      : Show gaps without fetching}
                            {--chunk=50     : Upsert batch size}';

    protected $description = 'Gap-fill missing option strikes on prev days when ATM shifts between sessions';

    private array $strikeIntervals = [
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
        'BHARATFORG'   => 20,
        'HINDALCO'     => 10,
        'VEDL'         => 10,
        'DRREDDY'      => 50,
        'TATACONSUM'   => 10,
        'SBIN'         => 10,
        'BAJFINANCE'   => 50,
        'TCS'          => 50,
        'HEROMOTOCO'   => 20,
        'PGEL'         => 10,
        'TMPV'         => 5,
        'COFORGE'      => 50,
        'EICHERMOT'    => 50,
        'LICHSGFIN'    => 5,
        'VBL'          => 20,
        'ETERNAL'      => 10,
        'FORTIS'       => 5,
        'UPL'          => 10,
        'AMBUJACEM'    => 5,
        'NATIONALUM'   => 5,
        'BPCL'         => 10,
    ];

    private array $zerodhaHelperCache = [];

    // =========================================================
    //  HANDLE
    // =========================================================

    public function handle(): int
    {
        $dryRun   = $this->option('dry-run');
        $lookback = max(1, (int) $this->option('lookback'));
        $chunk    = (int) $this->option('chunk');

        $this->info('');
        $this->info('🔧 Option OHLC Gap-Fill — ATM Shift Detector');
        $this->info($dryRun ? '   [DRY RUN — no DB writes]' : '   [LIVE — will upsert missing candles]');

        // ── Date range ────────────────────────────────────────────────────
        if ($this->option('date')) {
            $signalDates = [Carbon::parse($this->option('date'))->toDateString()];
        } elseif ($this->option('start-date') && $this->option('end-date')) {
            $signalDates = $this->getDateRange(
                $this->option('start-date'),
                $this->option('end-date')
            );
        } else {
            // Default: today
            $signalDates = [Carbon::today()->toDateString()];
        }

        $specificSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $specificBroker = $this->option('broker');

        $this->info('   Signal dates : ' . implode(', ', array_slice($signalDates, 0, 5))
            . (count($signalDates) > 5 ? ' +' . (count($signalDates) - 5) . ' more' : ''));
        $this->info('   Lookback     : ' . $lookback . ' prev trading day(s) per signal date');
        $this->info('');

        // ── Brokers ───────────────────────────────────────────────────────
        $brokerQuery = BrokerApi::zerodha()->validToken();
        if ($specificBroker) $brokerQuery->where('id', $specificBroker);
        $brokers = $brokerQuery->get();

        if ($brokers->isEmpty()) {
            $this->error('❌ No active brokers with valid token found!');
            return 1;
        }

        $totalFilled  = 0;
        $totalSkipped = 0;
        $totalErrors  = 0;

        foreach ($brokers as $broker) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");
            $this->zerodhaHelperCache[$broker->id] = new BrokerZerodhaHelper($broker);

            // Get symbols to process
            $symbolQuery = \App\Models\SymbolMonitored::where('broker_api_id', $broker->id)
                ->where('is_active', true)
                ->where('interval', '5minute')
                ->where('instrument_type', 'FUT');

            if ($specificSymbol) $symbolQuery->where('symbol', $specificSymbol);
            $symbols = $symbolQuery->pluck('symbol')->toArray();

            if (empty($symbols)) {
                $this->warn('   No monitored symbols found for this broker');
                continue;
            }

            $this->info('   Symbols: ' . implode(', ', $symbols));

            foreach ($signalDates as $signalDate) {
                $this->info("\n📅 Signal date: {$signalDate}");

                // Get prev trading days (up to $lookback)
                $prevDates = $this->getPrevTradingDates($signalDate, $lookback);
                if (empty($prevDates)) {
                    $this->warn('   No prev trading dates found, skipping');
                    continue;
                }

                $this->info('   Prev dates to gap-fill: ' . implode(', ', $prevDates));

                foreach ($symbols as $baseSymbol) {
                    try {
                        $result = $this->processSymbol(
                            $broker, $baseSymbol, $signalDate, $prevDates,
                            $dryRun, $chunk
                        );
                        $totalFilled  += $result['filled'];
                        $totalSkipped += $result['skipped'];
                        $totalErrors  += $result['errors'];
                    } catch (Exception $e) {
                        $this->error("   ✗ {$baseSymbol}: " . $e->getMessage());
                        Log::error("GapFill error [{$baseSymbol}]: " . $e->getMessage());
                        $totalErrors++;
                    }
                }
            }
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Gap-fill complete");
        $this->info("   Rows filled  : {$totalFilled}");
        $this->info("   Rows skipped : {$totalSkipped} (already existed)");
        $this->info("   Errors       : {$totalErrors}");
        return 0;
    }

    // =========================================================
    //  PROCESS ONE SYMBOL
    // =========================================================

    private function processSymbol(
        BrokerApi $broker,
        string    $baseSymbol,
        string    $signalDate,
        array     $prevDates,
        bool      $dryRun,
        int       $chunk
    ): array {
        $filled  = 0;
        $skipped = 0;
        $errors  = 0;

        $strikeInterval = $this->strikeIntervals[$baseSymbol] ?? 20;

        // ── Step 1: Get today's ATM from DB (already collected) ───────────
        $todayAtmRow = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->whereDate('trade_date', $signalDate)
            ->where('instrument_type', 'FUT')
            ->whereNotNull('atm_strike')
            ->orderBy('interval_time')
            ->first();

        if (!$todayAtmRow) {
            $this->warn("   ⚠️  {$baseSymbol}: No FUT data found for {$signalDate} — skipping");
            return ['filled' => 0, 'skipped' => 0, 'errors' => 1];
        }

        $todayAtm = (float) $todayAtmRow->atm_strike;

        // ── Step 2: Build the ±2 strike set we NEED on prev days ─────────
        $neededStrikes = [
            $todayAtm - ($strikeInterval * 2),
            $todayAtm - $strikeInterval,
            $todayAtm,
            $todayAtm + $strikeInterval,
            $todayAtm + ($strikeInterval * 2),
        ];

        $this->line("   📊 {$baseSymbol} — today ATM: {$todayAtm} | needed strikes: " . implode(', ', $neededStrikes));

        // ── Step 3: Check each prev day for missing strikes ───────────────
        foreach ($prevDates as $prevDate) {

            // What strikes already exist on prev day?
            $existingStrikes = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->whereDate('trade_date', $prevDate)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('strike')
                ->distinct()
                ->pluck('strike')
                ->map(fn($s) => (float) $s)
                ->toArray();

            // Which of our needed strikes are missing?
            $missingStrikes = array_filter($neededStrikes, function ($needed) use ($existingStrikes) {
                foreach ($existingStrikes as $existing) {
                    if (abs($existing - $needed) < 0.01) return false;
                }
                return true;
            });

            $missingStrikes = array_values($missingStrikes);

            if (empty($missingStrikes)) {
                $this->line("      ✓ {$prevDate}: All strikes present — no gap-fill needed");
                $skipped += count($neededStrikes) * 2; // CE + PE
                continue;
            }

            $this->line("      ⚠️  {$prevDate}: Missing strikes → " . implode(', ', $missingStrikes));

            if ($dryRun) {
                $this->warn("      [DRY RUN] Would fetch " . (count($missingStrikes) * 2) . " instruments (CE+PE) for {$prevDate}");
                continue;
            }

            // ── Step 4: Get expiry for prev date ─────────────────────────
            $expiry = $this->getExpiry($baseSymbol, Carbon::parse($prevDate));
            if (!$expiry) {
                $this->warn("      ✗ {$prevDate}: Could not determine expiry for {$baseSymbol}");
                $errors++;
                continue;
            }

            // ── Step 5: Get prev day's FUT candle data for context ────────
            $prevFutRow = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->whereDate('trade_date', $prevDate)
                ->where('instrument_type', 'FUT')
                ->orderBy('interval_time')
                ->first();

            $prevAtm       = $prevFutRow ? (float) $prevFutRow->atm_strike : $todayAtm;
            $prevFutSymbol = $prevFutRow ? $prevFutRow->trading_symbol : $baseSymbol;

            // ── Step 6: Fetch & insert each missing strike ────────────────
            $intervals   = $this->generateTradingIntervals(Carbon::parse($prevDate));
            $rows        = [];
            $now         = now()->toDateTimeString();

            foreach ($missingStrikes as $strike) {
                foreach (['CE', 'PE'] as $optType) {

                    // Find instrument in zerodha_instruments table
                    $instrument = ZerodhaInstrument::where('name', $baseSymbol)
                        ->where('exchange', 'NFO')
                        ->where('instrument_type', $optType)
                        ->where('strike', $strike)
                        ->whereDate('expiry', $expiry)
                        ->first();

                    if (!$instrument) {
                        $this->warn("         ⚠️  Instrument not found: {$baseSymbol} {$strike} {$optType} expiry:{$expiry}");
                        $errors++;
                        continue;
                    }

                    // Fetch full day candles from Zerodha
                    $candles = $this->fetchDayCandles(
                        $broker,
                        $instrument->instrument_token,
                        Carbon::parse($prevDate)
                    );

                    if (empty($candles)) {
                        $this->warn("         ⚠️  No candles returned for {$baseSymbol} {$strike} {$optType} on {$prevDate}");
                        // Still write zero-fill rows so the gap is marked
                        foreach ($intervals as $intervalTime) {
                            $rows[] = $this->buildRow(
                                $broker->id, $baseSymbol, $prevFutSymbol,
                                null, $prevAtm, $optType, $strike,
                                $instrument, null, $expiry,
                                Carbon::parse($prevDate), $intervalTime, $now,
                                true // is_missing
                            );
                        }
                        continue;
                    }

                    $candleMap = $this->indexCandlesByTime($candles);
                    $this->info("         ✅ {$optType} {$strike}: Fetched " . count($candles) . " candles");

                    // Build carry-forward FUT close map for this prev day
                    // (use existing FUT rows from DB)
                    $futCloseMap = $this->getFutCloseMap($baseSymbol, $prevDate);

                    foreach ($intervals as $intervalTime) {
                        $timeKey  = $intervalTime->format('H:i');
                        $candle   = $candleMap[$timeKey] ?? null;
                        $futClose = $futCloseMap[$timeKey] ?? null;

                        $strikePos = $this->getStrikePosition($strike, $prevAtm, $strikeInterval);

                        $rows[] = $this->buildRow(
                            $broker->id, $baseSymbol, $prevFutSymbol,
                            $futClose, $prevAtm, $optType, $strike,
                            $instrument, $candle, $expiry,
                            Carbon::parse($prevDate), $intervalTime, $now,
                            $candle === null // is_missing
                        );
                    }

                    $filled += count($rows);
                }
            }

            // ── Step 7: Batch upsert ──────────────────────────────────────
            if (!empty($rows)) {
                foreach (array_chunk($rows, $chunk) as $batch) {
                    OptionOhlcData::upsert(
                        $batch,
                        ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                        [
                            'base_symbol', 'future_symbol', 'future_price', 'atm_strike',
                            'instrument_type', 'strike', 'instrument_token',
                            'open', 'high', 'low', 'close', 'volume', 'oi',
                            'strike_position', 'expiry_date', 'is_missing',
                            'updated_at',
                        ]
                    );
                }
                $this->info("      ✅ {$prevDate}: Upserted " . count($rows) . " rows for missing strikes");
                $filled += count($rows);
            }
        }

        return ['filled' => $filled, 'skipped' => $skipped, 'errors' => $errors];
    }

    // =========================================================
    //  GET FUT CLOSE MAP for a prev date (from existing DB rows)
    // =========================================================

    private function getFutCloseMap(string $baseSymbol, string $date): array
    {
        $futRows = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'close']);

        $map  = [];
        $last = null;

        foreach ($futRows as $row) {
            $time = Carbon::parse($row->interval_time)->format('H:i');
            if ((float) $row->close > 0) $last = (float) $row->close;
            $map[$time] = $last;
        }

        return $map;
    }

    // =========================================================
    //  BUILD ROW
    // =========================================================

    private function buildRow(
        int    $brokerId,
        string $baseSymbol,
        string $futureSymbol,
        ?float $futurePrice,
        float  $atmStrike,
        string $optionType,
        float  $strike,
        ZerodhaInstrument $instrument,
        $candle,
        string $expiry,
        Carbon $tradeDate,
        Carbon $intervalTime,
        string $now,
        bool   $isMissing = false
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
            'strike_position'  => $this->getStrikePosition($strike, $atmStrike, $this->strikeIntervals[$baseSymbol] ?? 20),
            'expiry_date'      => $expiry,
            'is_missing'       => $isMissing ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    // =========================================================
    //  FETCH DAY CANDLES FROM ZERODHA
    // =========================================================

    private function fetchDayCandles(BrokerApi $broker, int $token, Carbon $date, int $retries = 3): array
    {
        $fromTime = $date->copy()->setTime(9, 15)->format('Y-m-d H:i:s');
        $toTime   = $date->copy()->setTime(15, 30)->format('Y-m-d H:i:s');

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $helper = $this->zerodhaHelperCache[$broker->id];
                $data   = $helper->getHistoricalDataByToken($token, '15minute', $fromTime, $toTime);
                return $data ?? [];
            } catch (Exception $e) {
                if ($attempt < $retries) {
                    $this->warn("         ⏳ Fetch attempt {$attempt}/{$retries} failed: " . $e->getMessage());
                    sleep(2);
                } else {
                    $this->error("         ✗ Fetch failed after {$retries} attempts: " . $e->getMessage());
                    return [];
                }
            }
        }
        return [];
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    private function indexCandlesByTime(array $candles): array
    {
        $map = [];
        foreach ($candles as $c) {
            $map[Carbon::parse($c->date)->format('H:i')] = $c;
        }
        return $map;
    }

    private function generateTradingIntervals(Carbon $date): array
    {
        $slots   = [];
        $current = $date->copy()->setTime(9, 15);
        $end     = $date->copy()->setTime(15, 15);
        while ($current->lte($end)) {
            $slots[] = $current->copy();
            $current->addMinutes(15);
        }
        return $slots;
    }

    private function getStrikePosition(float $strike, float $atm, float $interval): string
    {
        $diff = round(($strike - $atm) / $interval);
        if ($diff === 0)  return 'ATM';
        if ($diff === 1)  return 'ATM+1';
        if ($diff === -1) return 'ATM-1';
        if ($diff === 2)  return 'ATM+2';
        if ($diff === -2) return 'ATM-2';
        return 'OTM';
    }

    private function getExpiry(string $baseSymbol, Carbon $refDate): ?string
    {
        $row = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $refDate)
            ->orderBy('expiry')
            ->first();

        return $row ? Carbon::parse($row->expiry)->toDateString() : null;
    }

    private function getDateRange(string $from, string $to): array
    {
        $dates   = [];
        $current = Carbon::parse($from);
        $end     = Carbon::parse($to);
        while ($current->lte($end)) {
            if (!$current->isWeekend() && !$this->isHoliday($current->format('Y-m-d'))) {
                $dates[] = $current->toDateString();
            }
            $current->addDay();
        }
        return $dates;
    }

    private function getPrevTradingDates(string $fromDate, int $count): array
    {
        $dates   = [];
        $current = Carbon::parse($fromDate)->subDay();
        $tries   = 0;

        while (count($dates) < $count && $tries < 30) {
            if (!$current->isWeekend() && !$this->isHoliday($current->format('Y-m-d'))) {
                $dates[] = $current->toDateString();
            }
            $current->subDay();
            $tries++;
        }

        return $dates;
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}