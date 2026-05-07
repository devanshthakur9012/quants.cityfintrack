<?php
// FILE: app/Console/Commands/Cp/CpCollectStock.php

namespace App\Console\Commands\Cp;

use Illuminate\Console\Command;
use App\Traits\CpOhlcBase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CpCollectStock extends Command
{
    use CpOhlcBase;

    protected $signature = 'cp:collect-stock
                            {--timeframe=15min   : 15min | 30min | 1hr}
                            {--from=             : Start date Y-m-d (historical)}
                            {--to=               : End date Y-m-d (default today)}
                            {--symbol=           : Limit to specific symbol}
                            {--retry=3           : API retries per call}
                            {--retry-delay=2     : Seconds between retries}
                            {--chunk=100         : Upsert batch size}';

    protected $description = 'Collect intraday stock EQ OHLC+Volume into cp_stock_ohlc_{timeframe} table';

    public function handle(): int
    {
        $timeframe  = $this->option('timeframe');
        $specSymbol = $this->option('symbol') ? strtoupper($this->option('symbol')) : null;
        $maxRetries = (int) $this->option('retry');
        $retryDelay = (int) $this->option('retry-delay');
        $chunkSize  = (int) $this->option('chunk');
        $table      = "cp_stock_ohlc_{$timeframe}";
        $now        = Carbon::now();

        $this->info("════════════════════════════════════════════");
        $this->info("  📈 CP Stock OHLC Collector [{$timeframe}]");
        $this->info("  " . $now->format('Y-m-d H:i:s'));
        $this->info("════════════════════════════════════════════");

        if (!in_array($timeframe, ['15min', '30min', '1hr'])) {
            $this->error("❌ Invalid timeframe. Use: 15min | 30min | 1hr");
            return 1;
        }

        // ── Load config + broker ──────────────────────────────────────────────
        $config = $this->loadConfig($timeframe);
        if (!$config) return 1;

        if (!$this->initBroker($config)) return 1;

        // ── Symbols ───────────────────────────────────────────────────────────
        $symbols = $config->symbols->pluck('symbol')->toArray();
        if ($specSymbol) {
            $symbols = in_array($specSymbol, $symbols) ? [$specSymbol] : [];
        }

        if (empty($symbols)) {
            $this->error("❌ No symbols in config. Add symbols via Admin → Analysis Config.");
            return 1;
        }

        $this->info("   Symbols (" . count($symbols) . "): " . implode(', ', $symbols));

        // ── Date range ────────────────────────────────────────────────────────
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        if (!$dateFrom) return 1;

        $isHistorical = $this->option('from') !== null;
        $this->info("   Mode: " . ($isHistorical
            ? "HISTORICAL {$dateFrom->toDateString()} → {$dateTo->toDateString()}"
            : "LIVE today"));

        // ── Skip weekends/holidays in live mode ───────────────────────────────
        if (!$isHistorical) {
            if ($dateFrom->isWeekend()) {
                $this->warn("⏭  Weekend — nothing to collect.");
                return 0;
            }
            if ($this->cpIsMarketHoliday($dateFrom->toDateString())) {
                $this->warn("⏭  Market holiday — nothing to collect.");
                return 0;
            }
        }

        $this->newLine();

        $tradingDays = $this->getTradingDays($dateFrom, $dateTo);
        if (empty($tradingDays)) {
            $this->warn("⏭  No trading days in range.");
            return 0;
        }

        $totalUpserted = 0;
        $totalSkipped  = 0;
        $totalFailed   = 0;

        // ── Symbol loop ───────────────────────────────────────────────────────
        foreach ($symbols as $symbol) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("   📊 {$symbol}");

            $instrument = $this->resolveStockInstrument($symbol);
            if (!$instrument) {
                $this->warn("   ⚠️  EQ instrument not found for {$symbol} — skipping");
                Log::warning("CpCollectStock: Instrument not found for {$symbol}");
                $totalFailed++;
                continue;
            }

            $this->info("   Token: {$instrument->instrument_token} | {$instrument->trading_symbol}");

            // ── Day loop ──────────────────────────────────────────────────────
            foreach ($tradingDays as $date) {
                $dateStr  = $date->toDateString();
                $allSlots = $this->generateIntervals($date, $timeframe);

                // Live: only up to last completed candle
                if (!$isHistorical) {
                    $lastSlot = $this->getLastCompletedSlot($now, $date, $timeframe);
                    $allSlots = array_values(array_filter(
                        $allSlots, fn($t) => $t->lte($lastSlot)
                    ));

                    if (empty($allSlots)) {
                        $this->warn("   ⏳ Market not started or no completed candle yet.");
                        continue;
                    }
                }

                // Gap-fill: find missing intervals
                $stored = \DB::table($table)
                    ->where('analysis_config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->whereDate('trade_date', $dateStr)
                    ->where('is_missing', false)
                    ->pluck('interval_time')
                    ->map(fn($t) => Carbon::parse($t)->format('H:i'))
                    ->flip()
                    ->toArray();

                $missing = array_values(array_filter(
                    $allSlots, fn($t) => !isset($stored[$t->format('H:i')])
                ));

                if (empty($missing)) {
                    $this->line("   ✓ {$symbol} {$dateStr} — up to date");
                    $totalSkipped++;
                    continue;
                }

                $this->info("   Fetching {$symbol} {$dateStr} — " . count($missing) . " missing slots");

                // Fetch full day candles (gap-fill approach)
                $candles   = $this->fetchDayCandles($instrument->instrument_token, $date, $timeframe, $maxRetries, $retryDelay);
                $candleMap = $this->indexCandlesByTime($candles);
                $nowStr    = now()->toDateTimeString();
                $rows      = [];

                foreach ($missing as $slot) {
                    $timeKey = $slot->format('H:i');
                    $candle  = $candleMap[$timeKey] ?? null;

                    $rows[] = [
                        'analysis_config_id' => $config->id,
                        'broker_api_id'      => $this->cpBroker->id,
                        'symbol'             => $symbol,
                        'trading_symbol'     => $instrument->trading_symbol,
                        'instrument_token'   => $instrument->instrument_token,
                        'trade_date'         => $dateStr,
                        'interval_time'      => $slot->toDateTimeString(),
                        'open'               => $candle ? $candle->open   : 0,
                        'high'               => $candle ? $candle->high   : 0,
                        'low'                => $candle ? $candle->low    : 0,
                        'close'              => $candle ? $candle->close  : 0,
                        'volume'             => $candle ? $candle->volume : 0,
                        'is_missing'         => $candle ? 0 : 1,
                        'is_backfill'        => 0,
                        'created_at'         => $nowStr,
                        'updated_at'         => $nowStr,
                    ];
                }

                $upserted = $this->batchUpsertRows(
                    $table, $rows,
                    ['analysis_config_id', 'symbol', 'trade_date', 'interval_time'],
                    ['open', 'high', 'low', 'close', 'volume', 'is_missing', 'updated_at'],
                    $chunkSize
                );

                $totalUpserted += $upserted;
                $this->info("   ✅ {$symbol} {$dateStr} — {$upserted} rows upserted");

                usleep(300_000); // 300ms rate-limit safety
            }
        }

        $this->printCpSummary(
            'Stock OHLC', $timeframe,
            $dateFrom->toDateString(), $dateTo->toDateString(),
            $totalUpserted, $totalSkipped, $totalFailed
        );

        return 0;
    }
}