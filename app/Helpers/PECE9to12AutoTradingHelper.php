<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\OptionOhlcData;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * 9:30 AM to 12:15 PM Intraday Auto Trading Helper
 *
 * ── TWO-SERIES ARCHITECTURE (Key Fix) ────────────────────────────────────────
 *
 * "Data series"       = expiry_date used to READ OI candles from option_ohlc_data.
 *                       On expiry day (e.g. 2026-02-24) this is 2026-02-24
 *                       because the day's candle data IS in the DB.
 *
 * "Instrument series" = expiry used to PLACE orders via zerodha_instruments.
 *                       On expiry day Zerodha has ALREADY DELISTED the expiring
 *                       options, so we must use the NEXT available expiry
 *                       (e.g. 2026-03-27) to find a tradeable contract.
 *
 * These two series can differ on expiry day — this file separates them cleanly.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * ── ORDER PLACEMENT FILTER (v3) ──────────────────────────────────────────────
 * Orders are placed based on SENTIMENT ONLY (OI signal):
 *   BUY CE  →  Sentiment = BULLISH
 *   BUY PE  →  Sentiment = BEARISH
 *
 * 50MA filter is DISABLED for order placement.
 * (50MA is still calculated and shown in the UI for reference only.)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * ── BUG FIXES (v2) ────────────────────────────────────────────────────────────
 * FIX 1: OPEN_TIME changed from 09:15 → 09:30 to match the controller/UI logic.
 * FIX 2: Strike interval lookup key normalised (LICHSGFIN, single source STRIKE_INTERVALS).
 * FIX 3: Option type written to the order always mirrors $finalOptionType.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class PECE9to12AutoTradingHelper
{
    const OPEN_TIME_HOUR      = 9;
    const OPEN_TIME_MINUTE    = 30;
    const CLOSE_TIME_HOUR     = 12;
    const CLOSE_TIME_MINUTE   = 00;
    const MA_PERIOD           = 50;

    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'SENSEX', 'BANKEX'];

    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY'       => 18,  'BANKNIFTY'  => 20,  'FINNIFTY'   => 24,  'MIDCPNIFTY' => 24,
        'ADANIPORTS'  => 30,  'AMBUJACEM'  => 40,  'ASIANPAINT' => 40,  'AUROPHARMA' => 40,
        'AXISBANK'    => 30,  'BAJAJFINSV' => 50,  'BAJFINANCE' => 30,  'BHARATFORG' => 30,
        'BHARTIARTL'  => 30,  'BHEL'       => 30,  'BPCL'       => 30,  'BSE'        => 30,
        'CDSL'        => 30,  'COFORGE'    => 30,  'BDL'        => 40,  'DELHIVERY'  => 30,
        'DRREDDY'     => 30,  'ETERNAL'    => 30,  'FORTIS'     => 40,  'HAL'        => 40,
        'HAVELLS'     => 30,  'HEROMOTOCO' => 30,  'HINDALCO'   => 40,  'ICICIBANK'  => 30,
        'INDUSINDBK'  => 40,  'INFY'       => 40,  'JSWSTEEL'   => 30,  'LAURUSLABS' => 30,
        'LICHSGFIN'   => 40,  'LT'         => 40,  'LTF'        => 40,  'M&M'        => 30,
        'NATIONALUM'  => 20,  'PAYTM'      => 30,  'PGEL'       => 40,  'POLICYBZR'  => 40,
        'SBIN'        => 30,  'SHRIRAMFIN' => 30,  'SRF'        => 40,  'TATACONSUM' => 40,
        'TATAELXSI'   => 40,  'TATATECH'   => 50,  'TITAN'      => 40,  'TMPV'       => 50,
        'TCS'         => 40,  'UPL'        => 30,  'VBL'        => 40,  'VEDL'       => 30,
        'VOLTAS'      => 40,  'MCX'        => 20,
    ];

    const STRIKE_INTERVALS = [
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
        'TATACONSUM'   => 10,
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
        'LICHSGFIN'    => 5,
        'DELHIVERY'    => 10,
        'BHARATFORG'   => 20,
        'PGEL'         => 10,
        'TMPV'         => 5,
        'HINDALCO'     => 10,
        'VEDL'         => 10,
        'DRREDDY'      => 50,
        'HEROMOTOCO'   => 20,
        'AMBUJACEM'    => 5,
        'FORTIS'       => 5,
        'UPL'          => 10,
        'M&M'          => 20,
        'NATIONALUM'   => 5,
        'BPCL'         => 10,
        'ETERNAL'      => 10,
        'SBIN'         => 10,
        'VBL'          => 20,
        'BAJFINANCE'   => 50,
        'TCS'          => 50,
        'COFORGE'      => 50,
        'EICHERMOT'    => 50,
        'ABCCAPITAL'   => 10,
    ];

    // =========================================================
    //  PUBLIC ENTRY POINTS
    // =========================================================

    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== 9to12: Starting CE/PE OI Change Auto Trading Signal Detection ===');

            $processingDate = $testDate
                ? Carbon::parse($testDate . ' 12:30:00', 'Asia/Kolkata')
                : Carbon::now('Asia/Kolkata');

            $mode        = $testDate ? 'TEST' : 'LIVE';
            $currentDate = $processingDate->format('Y-m-d');

            Log::info("{$mode} MODE - Processing Time: " . $processingDate->format('Y-m-d H:i:s'));

            $configs = OIIVAutoConfig::where('status', true)->where('config_type', '9to12')->get();

            if ($configs->isEmpty()) {
                Log::info('No active 9to12 configurations found');
                return;
            }

            Log::info("Found {$configs->count()} active 9to12 config(s) | Date: {$currentDate}");

            $dataSeries       = $this->resolveDataSeries($currentDate);
            $instrumentSeries = $this->resolveInstrumentSeries($currentDate);

            if (!$dataSeries) {
                Log::warning("9to12: Could not resolve data series for {$currentDate} — aborting");
                return;
            }
            if (!$instrumentSeries) {
                Log::warning("9to12: Could not resolve instrument series for {$currentDate} — aborting");
                return;
            }

            $isExpiryDay = ($dataSeries === $currentDate);
            Log::info(sprintf(
                "DataSeries (OI reads): %s | InstrumentSeries (orders): %s | ExpiryDay: %s | OpenCandle: %02d:%02d",
                $dataSeries, $instrumentSeries, $isExpiryDay ? 'YES' : 'NO',
                self::OPEN_TIME_HOUR, self::OPEN_TIME_MINUTE
            ));

            $aggregatedSignals = $this->aggregateSignalsFromOhlc($currentDate, $dataSeries);

            if (empty($aggregatedSignals)) {
                Log::warning("No 9to12 signal data found for date={$currentDate} dataSeries={$dataSeries}");
                return;
            }

            $aligned = array_filter($aggregatedSignals, fn($s) => $s['is_aligned']);
            Log::info(sprintf(
                "Aggregated %d symbol signals | %d sentiment-eligible | %d skipped (NEUTRAL/WAIT)",
                count($aggregatedSignals), count($aligned),
                count($aggregatedSignals) - count($aligned)
            ));

            foreach ($configs as $config) {
                Log::info("Config ID: {$config->id} | User: {$config->user_id} | Mode: {$config->signal_mode}");

                $broker = $config->broker;
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Config {$config->id}: Invalid broker token - skipping");
                    continue;
                }

                $this->ensureKiteInstance($broker);
                $this->processConfigSignals(
                    $config, $aggregatedSignals, $broker,
                    $currentDate, $processingDate, $instrumentSeries
                );
            }

            Log::info('=== 9to12: Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('9to12 processSignals Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== 9to12: Starting PE/CE Order Placement ===');

            $pendingOrders = OIIVAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->whereHas('config', fn($q) => $q->where('status', true)->where('config_type', '9to12'))
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('No pending 9to12 orders to place');
                return;
            }

            Log::info("Found {$pendingOrders->count()} pending 9to12 orders");

            foreach ($pendingOrders->groupBy('broker_api_id') as $brokerId => $orders) {
                $broker = BrokerApi::find($brokerId);
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} invalid token - skipping");
                    continue;
                }
                $this->ensureKiteInstance($broker);
                foreach ($orders as $order) {
                    $this->placeOrder($order);
                }
            }

            Log::info('=== 9to12: Order Placement Completed ===');

        } catch (\Exception $e) {
            Log::error('9to12 placeOrders Error: ' . $e->getMessage());
        }
    }

    // =========================================================
    //  SERIES RESOLUTION
    // =========================================================

    private function resolveDataSeries(string $currentDate): ?string
    {
        $allSeries = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->distinct()
            ->orderBy('expiry_date', 'ASC')
            ->pluck('expiry_date')
            ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($allSeries)) return null;

        $active = collect($allSeries)->first(fn($d) => $d >= $currentDate);
        if (!$active) $active = end($allSeries);

        if (in_array($currentDate, $allSeries)) {
            $active = $currentDate;
        }

        Log::info("resolveDataSeries({$currentDate}) → {$active}");
        return $active;
    }

    private function resolveInstrumentSeries(string $currentDate): ?string
    {
        $isTodayExpiry = ZerodhaInstrument::where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $currentDate)
            ->exists();

        $comparator = $isTodayExpiry ? '>' : '>=';

        $expiry = ZerodhaInstrument::where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $comparator, $currentDate)
            ->orderBy('expiry', 'ASC')
            ->value('expiry');

        if (!$expiry) {
            $expiry = ZerodhaInstrument::where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('expiry', '>', $currentDate)
                ->orderBy('expiry', 'ASC')
                ->value('expiry');
        }

        $expiry = $expiry
            ? (is_string($expiry) ? substr($expiry, 0, 10) : Carbon::parse($expiry)->toDateString())
            : null;

        Log::info("resolveInstrumentSeries({$currentDate}) isTodayExpiry={$isTodayExpiry} → {$expiry}");
        return $expiry;
    }

    private function resolveSymbolExpiry(string $baseSymbol, string $optionType, string $currentDate): ?string
    {
        $isSymbolExpiryDay = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', $optionType)
            ->whereDate('expiry', $currentDate)
            ->exists();

        $comparator = $isSymbolExpiryDay ? '>' : '>=';

        $expiry = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', $optionType)
            ->whereDate('expiry', $comparator, $currentDate)
            ->orderBy('expiry', 'ASC')
            ->value('expiry');

        if (!$expiry) {
            $expiry = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->whereDate('expiry', '>', $currentDate)
                ->orderBy('expiry', 'ASC')
                ->value('expiry');
        }

        return $expiry
            ? (is_string($expiry) ? substr($expiry, 0, 10) : Carbon::parse($expiry)->toDateString())
            : null;
    }

    // =========================================================
    //  CORE: AGGREGATE SIGNALS FROM option_ohlc_data
    // =========================================================

    private function aggregateSignalsFromOhlc(string $date, string $dataSeries): array
    {
        $openTime  = sprintf('%02d:%02d:00', self::OPEN_TIME_HOUR, self::OPEN_TIME_MINUTE); // '09:30:00'
        $closeTime = sprintf('%02d:%02d:00', self::CLOSE_TIME_HOUR, self::CLOSE_TIME_MINUTE); // '12:15:00'

        $candles = OptionOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) IN ('{$openTime}', '{$closeTime}')")
            ->where('is_missing', 0)
            ->where(function ($q) use ($dataSeries) {
                $q->whereDate('expiry_date', $dataSeries)
                  ->orWhere(function ($q2) {
                      $q2->where('instrument_type', 'FUT')->whereNull('expiry_date');
                  });
            })
            ->select([
                'base_symbol', 'instrument_type', 'strike', 'strike_position',
                'close', 'oi', 'trading_symbol', 'instrument_token',
                DB::raw("TIME(interval_time) as candle_time"),
            ])
            ->get();

        if ($candles->isEmpty()) return [];

        $openKey  = substr($openTime,  0, 5); // '09:30'
        $closeKey = substr($closeTime, 0, 5); // '12:15'

        $grouped = [];
        foreach ($candles as $c) {
            $time = substr($c->candle_time, 0, 5);
            $grouped[$c->base_symbol][$c->instrument_type][$time][] = $c;
        }

        $signals = [];

        foreach ($grouped as $symbol => $typeMap) {
            $futOpen    = $typeMap['FUT'][$openKey][0]  ?? null;
            $futCurrent = $typeMap['FUT'][$closeKey][0] ?? null;

            if (!$futOpen || !$futCurrent) continue;

            $openClose    = (float) ($futOpen->close    ?? 0);
            $currentClose = (float) ($futCurrent->close ?? 0);
            if ($currentClose <= 0) continue;

            [$ceOpenOI, $ceCurOI] = $this->aggregateByPosition(
                $typeMap['CE'][$openKey]  ?? [], $typeMap['CE'][$closeKey] ?? []
            );
            [$peOpenOI, $peCurOI] = $this->aggregateByPosition(
                $typeMap['PE'][$openKey]  ?? [], $typeMap['PE'][$closeKey] ?? []
            );

            if ($ceCurOI == 0 && $peCurOI == 0 && $ceOpenOI == 0 && $peOpenOI == 0) continue;

            $ceOiPct   = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $peOiPct   = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;
            $oiSignal  = $this->getOISignal($ceOiPct, $peOiPct);
            $peCeRatio = $ceCurOI > 0 ? round($peCurOI / $ceCurOI, 2) : 0;

            $sentiment = $oiSignal['signal'];

            // ── 50MA is DISABLED for order placement. ─────────────────────
            // It is still shown in the UI (controller handles that separately).
            // Here we set it to N/A so the reason string stays informative.
            // $ma50Signal = $this->getFut50MaSignal($symbol, $date, $dataSeries);
            $ma50Signal = 'N/A'; // 50MA disabled — order placement uses sentiment only

            // ── is_aligned = sentiment is actionable (BULLISH or BEARISH). ─
            // Previously: required sentiment AND 50MA to match.
            // Now: any non-NEUTRAL/non-WAIT sentiment qualifies.
            // $isAligned = ($sentiment === 'BULLISH' && $ma50Signal === 'BULLISH')
            //           || ($sentiment === 'BEARISH' && $ma50Signal === 'BEARISH');
            $isAligned = ($sentiment === 'BULLISH' || $sentiment === 'BEARISH');

            Log::info(sprintf(
                "9to12 %s | DataSeries: %s | Sentiment: %s | Aligned(sentiment-only): %s | CE%%: %.2f | PE%%: %.2f",
                $symbol, $dataSeries, $sentiment,
                $isAligned ? 'YES 🎯' : 'NO ❌', $ceOiPct, $peOiPct
            ));

            $signals[$symbol] = [
                'underlying_symbol' => $symbol,
                'trading_symbol'    => $futCurrent->trading_symbol ?? $symbol,
                'instrument_token'  => $futCurrent->instrument_token ?? null,
                'open_close'        => $openClose,
                'current_close'     => $currentClose,
                'spot_price'        => $currentClose,
                'ce_oi_change_pct'  => $ceOiPct,
                'pe_oi_change_pct'  => $peOiPct,
                'pe_ce_ratio'       => $peCeRatio,
                'oi_condition'      => $oiSignal['condition'],
                'final_sentiment'   => $sentiment,
                'trade_action'      => match($sentiment) { 'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT' },
                'fut_50ma_signal'   => $ma50Signal, // always 'N/A' — disabled
                'is_aligned'        => $isAligned,
                'data_series'       => $dataSeries,
                'trading_date'      => $date,
            ];
        }

        return $signals;
    }

    private function aggregateByPosition(array $openCandles, array $curCandles): array
    {
        $curByPosition = [];
        foreach ($curCandles as $c) {
            $pos = $c->strike_position ?? null;
            if (!$pos || $pos === 'N/A') continue;
            $curByPosition[$pos] = $c;
        }

        $openOI = $curOI = 0;
        foreach ($openCandles as $oc) {
            $pos = $oc->strike_position ?? null;
            if (!$pos || $pos === 'N/A') continue;
            $cc = $curByPosition[$pos] ?? null;
            if (!$cc) continue;
            $openOI += (int) ($oc->oi ?? 0);
            $curOI  += (int) ($cc->oi ?? 0);
        }

        return [$openOI, $curOI];
    }

    // =========================================================
    //  50MA SIGNAL  (kept for future use / UI reference)
    //  NOT called during order placement — disabled above.
    // =========================================================

    private function calculateRollingMA(array $values, int $period): array
    {
        $ma = []; $n = count($values); $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];
            if ($i >= $period) $sum -= $values[$i - $period];
            $ma[] = ($i >= $period - 1) ? ($sum / $period) : null;
        }
        return $ma;
    }

    private function historyStartDate(string $tradeDate, int $maPeriod = 50): string
    {
        return Carbon::parse($tradeDate)->subDays((int) ceil($maPeriod * 2.5) + 15)->toDateString();
    }

    private function getFut50MaSignal(string $baseSymbol, string $tradeDate, string $dataSeries): string
    {
        $maPeriod     = self::MA_PERIOD;
        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereBetween('trade_date', [$historyStart, $tradeDate])
            ->where(function ($q) use ($dataSeries) {
                $q->whereDate('expiry_date', $dataSeries)->orWhereNull('expiry_date');
            })
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get([
                DB::raw("DATE(trade_date) as candle_date"),
                DB::raw("TIME(interval_time) as candle_time"),
                'close',
            ]);

        if ($allCandles->isEmpty()) return 'N/A';

        $closeValues = $allCandles->pluck('close')->map(fn($v) => (float) $v)->toArray();
        $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

        $targetIdx = null;
        foreach ($allCandles as $idx => $candle) {
            $candleDate = is_string($candle->candle_date) ? $candle->candle_date : Carbon::parse($candle->candle_date)->toDateString();
            if ($candleDate !== $tradeDate) continue;
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($time >= '12:15' && $time <= '12:29') { $targetIdx = $idx; break; }
        }
        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $candleDate = is_string($candle->candle_date) ? $candle->candle_date : Carbon::parse($candle->candle_date)->toDateString();
                if ($candleDate === $tradeDate) $targetIdx = $idx;
            }
        }

        if ($targetIdx === null || !isset($closeMa[$targetIdx])) return 'N/A';

        $close = $closeValues[$targetIdx];
        $ma    = $closeMa[$targetIdx];
        if ($ma === null) return 'N/A';

        return $close > $ma ? 'BULLISH' : ($close < $ma ? 'BEARISH' : 'NEUTRAL');
    }

    // =========================================================
    //  SIGNAL PROCESSING
    // =========================================================

    private function processConfigSignals(
        OIIVAutoConfig $config,
        array $aggregatedSignals,
        BrokerApi $broker,
        string $currentDate,
        Carbon $processingDateTime,
        string $instrumentSeries
    ): void {
        $created = $skipped = $skippedSentiment = $errors = 0;

        foreach ($aggregatedSignals as $symbol => $signalData) {
            try {
                // ── Only skip if sentiment is NEUTRAL (no clear direction). ──
                // 50MA alignment is NOT checked here — sentiment alone drives orders.
                if (!$signalData['is_aligned']) {
                    Log::info("9to12 {$symbol}: SKIPPED (sentiment NEUTRAL/WAIT — no actionable direction)");
                    $skippedSentiment++;
                    continue;
                }

                $ce   = (float) ($signalData['ce_oi_change_pct'] ?? 0);
                $pe   = (float) ($signalData['pe_oi_change_pct'] ?? 0);

                // ── Rank check DISABLED — place order for ALL BULLISH/BEARISH signals ──
                // Previously: skipped when diff <= 5 (rank = null / NORMAL).
                // Now: rank is for logging only, never blocks an order.
                $rank = OIIVAutoConfig::computeStrengthRank($ce, $pe) ?? 5; // 5 = "Any" / below Rank4

                // Direction is derived from sentiment directly — no NORMAL skip.
                $direction = $signalData['final_sentiment']; // 'BULLISH' or 'BEARISH'

                $rawOptionType   = $signalData['final_sentiment'] === 'BULLISH' ? 'CE' : 'PE';
                $finalOptionType = $config->shouldReverseSignal()
                    ? ($rawOptionType === 'CE' ? 'PE' : 'CE') : $rawOptionType;

                $isIndex  = in_array(strtoupper($symbol), self::INDEX_SYMBOLS);
                $quantity = $finalOptionType === 'CE'
                    ? ($isIndex ? (int)($config->index_ce_quantity ?? 0) : (int)($config->stock_ce_quantity ?? 0))
                    : ($isIndex ? (int)($config->index_pe_quantity ?? 0) : (int)($config->stock_pe_quantity ?? 0));

                if ($quantity <= 0) { Log::info("9to12 {$symbol}: qty=0 - skip"); $skipped++; continue; }

                $exists = OIIVAutoOrder::where('config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->whereDate('signal_detected_at', $currentDate)
                    ->where('status', true)->exists();

                if ($exists) { Log::debug("9to12 {$symbol}: duplicate"); $skipped++; continue; }

                $result = $this->analyzeAndCreateOrder(
                    $config, $signalData, $broker, $currentDate, $processingDateTime,
                    $rank, $direction, $rawOptionType, $finalOptionType, $quantity, $instrumentSeries
                );

                $result ? $created++ : $errors++;

            } catch (\Exception $e) {
                Log::error("9to12 Error {$symbol}: " . $e->getMessage()); $errors++;
            }
        }

        Log::info("9to12 Config {$config->id} — Created: {$created} | SkippedNeutral: {$skippedSentiment} | SkippedOther: {$skipped} | Errors: {$errors}");
    }

    private function analyzeAndCreateOrder(
        OIIVAutoConfig $config, array $signalData, BrokerApi $broker,
        string $date, Carbon $processingDateTime,
        int $rank, string $direction, string $rawOptionType,
        string $finalOptionType, int $quantity, string $instrumentSeries
    ): bool {
        try {
            $symbol   = $signalData['underlying_symbol'];
            $lockTime = Carbon::parse($date . ' ' . self::CLOSE_TIME_HOUR . ':' . self::CLOSE_TIME_MINUTE . ':00', 'Asia/Kolkata');

            Log::info("9to12 ANALYZE Config {$config->id} | {$symbol} | Rank {$rank} | {$direction} => {$finalOptionType} | InstrSeries: {$instrumentSeries} [SENTIMENT ✅]");

            if ($processingDateTime->lessThan($lockTime)) {
                Log::info("  Before 12:15 PM - skip"); return false;
            }

            $currentPrice = $signalData['current_close'] ?? null;
            if (!$currentPrice || $currentPrice <= 0) {
                Log::error("  No 12:15 price for {$symbol}"); return false;
            }

            $optionDetails = $this->getATMOption(
                $broker, $signalData['trading_symbol'], $finalOptionType,
                $currentPrice, $config, $instrumentSeries
            );

            if (!$optionDetails) {
                Log::error("  No ATM option for {$symbol} type={$finalOptionType} on {$instrumentSeries}"); return false;
            }

            if ($optionDetails['ltp'] <= 0) {
                Log::error("  LTP unavailable (0) for {$optionDetails['symbol']} — skipping order"); return false;
            }

            $ce = (float)($signalData['ce_oi_change_pct'] ?? 0);
            $pe = (float)($signalData['pe_oi_change_pct'] ?? 0);
            $modeLabel = $config->shouldReverseSignal() ? 'OPPOSITE' : 'ALIGN';

            $reason = sprintf(
                "9to12 | Rank:%d | Dir:%s | Mode:%s | BUY %s | CE%%:%.2f | PE%%:%.2f | Diff:%.2f | %s | Sentiment:%s | 50MA:DISABLED | DataSeries:%s | InstrSeries:%s | Qty:%d",
                $rank, $direction, $modeLabel, $finalOptionType,
                $ce, $pe, abs($ce - $pe),
                $signalData['oi_condition']    ?? 'N/A',
                $signalData['final_sentiment'] ?? 'N/A',
                $signalData['data_series']     ?? 'N/A',
                $instrumentSeries, $quantity
            );

            $order = OIIVAutoOrder::create([
                'user_id'            => $config->user_id,
                'config_id'          => $config->id,
                'broker_api_id'      => $broker->id,
                'symbol'             => $symbol,
                'trading_symbol'     => $signalData['trading_symbol'],
                'instrument_token'   => $signalData['instrument_token'] ?? null,
                'btst_signal'        => "9TO12_RANK{$rank}_{$direction}_{$modeLabel}_{$finalOptionType}_SENTIMENT_ONLY",
                'btst_confidence'    => 100,
                'btst_reason'        => $reason,
                'signal_detected_at' => Carbon::parse($date . ' 12:00:00', 'Asia/Kolkata'),
                'fut_oi_signal'      => "9to12 Rank{$rank} | " . ($signalData['oi_condition'] ?? 'N/A'),
                'fut_oi_strength'    => $signalData['final_sentiment'] ?? 'N/A',
                'ce_oi_signal'       => 'N/A', 'pe_oi_signal'   => 'N/A',
                'ce_iv_signal'       => 'N/A', 'ce_iv_strength' => 'N/A',
                'pe_iv_signal'       => 'N/A', 'pe_iv_strength' => 'N/A',
                'spot_price'         => $currentPrice,
                'option_symbol'      => $optionDetails['symbol'],
                'option_token'       => $optionDetails['token'],
                'option_type'        => $finalOptionType,
                'strike_price'       => $optionDetails['strike'],
                'entry_price'        => $optionDetails['ltp'],
                'current_price'      => $optionDetails['ltp'],
                'order_type'         => $config->order_type,
                'product'            => $config->product,
                'quantity'           => $quantity,
                'is_order_placed'    => false,
                'status'             => true,
            ]);

            Log::info("9to12 Order created! ID:{$order->id} | {$optionDetails['symbol']} | Type:{$finalOptionType} | Strike:{$optionDetails['strike']} | Rank{$rank} | Qty:{$quantity} | InstrSeries:{$instrumentSeries}");
            return true;

        } catch (\Exception $e) {
            Log::error("9to12 ANALYZE {$signalData['underlying_symbol']}: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    //  ORDER PLACEMENT
    // =========================================================

    private function placeOrder(OIIVAutoOrder $order): void
    {
        try {
            Log::info("9to12 ORDER Placing: {$order->option_symbol}");
            $broker = $order->broker;
            if (!$broker->hasValidToken()) {
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Invalid token"); return;
            }
            $this->ensureKiteInstance($broker);
            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();
            if (!$instrument) {
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Instrument not found"); return;
            }
            $this->placeKiteOrder($order, $order->quantity, $instrument, $this->kiteInstances[$broker->id]);
            $order->update(['is_order_placed' => true, 'order_placed_at' => now()]);
            Log::info("9to12 ORDER Processed: ID {$order->id}");
        } catch (\Exception $e) {
            Log::error("9to12 ORDER {$order->option_symbol}: " . $e->getMessage());
            $this->saveFailedOrder($order, $order->quantity ?? 0, null, $e->getMessage());
        }
    }

    private function placeKiteOrder(OIIVAutoOrder $order, $quantity, $instrument, $kite): void
    {
        $price = null;
        if ($order->order_type == 'LIMIT') {
            $discount = ($order->entry_price * $order->config->disc_ltp) / 100;
            $raw      = $order->entry_price - $discount;
            $price    = number_format(round($raw / $instrument->tick_size) * $instrument->tick_size, 2, '.', '');
        }

        $baseSymbol      = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->trading_symbol);
        $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

        if ($freezeLimitLots && $quantity > $freezeLimitLots) {
            $remaining = $quantity;
            while ($remaining > 0) {
                $lots = min($freezeLimitLots, $remaining);
                $this->executeSingleOrder($order, $lots, $price, $instrument, $kite);
                $remaining -= $lots;
                if ($remaining > 0) sleep(2);
            }
        } else {
            $this->executeSingleOrder($order, $quantity, $price, $instrument, $kite);
        }
    }

    private function executeSingleOrder($order, $quantity, $price, $instrument, $kite): void
    {
        $params = [
            'exchange'          => 'NFO',
            'tradingsymbol'     => $order->option_symbol,
            'transaction_type'  => 'BUY',
            'quantity'          => $quantity * $instrument->lot_size,
            'product'           => $order->product,
            'validity'          => 'DAY',
        ];
        $params['order_type'] = $order->order_type == 'MARKET' ? 'MARKET' : 'LIMIT';
        if ($order->order_type != 'MARKET') $params['price'] = $price;

        $result = $kite->placeOrder("regular", $params);
        Log::info("9to12 ORDER Placed! Kite ID: {$result->order_id}");
        $this->saveToOrderBook($order, $result->order_id, $quantity, $price);
    }

    // =========================================================
    //  ATM OPTION LOOKUP
    // =========================================================

    private function getATMOption(
        BrokerApi $broker,
        string $tradingSymbol,
        string $optionType,
        float $futurePrice,
        OIIVAutoConfig $config,
        string $instrumentSeries
    ): ?array {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
            $interval   = self::STRIKE_INTERVALS[$baseSymbol] ?? 20;
            $atmStrike  = round($futurePrice / $interval) * $interval;

            // ── Strike shift: CE → ATM+1 (one interval above), PE → ATM-1 (one interval below) ──
            // Example PAYTM (interval=20, FUT=1140): CE=1160, PE=1120
            $atmStrike = $optionType === 'CE'
                ? $atmStrike + $interval   // ATM+1
                : $atmStrike - $interval;  // ATM-1

            $allExpiries = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->whereDate('expiry', '>=', $instrumentSeries)
                ->distinct()
                ->orderBy('expiry', 'ASC')
                ->pluck('expiry')
                ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            if ($allExpiries->isEmpty()) {
                Log::warning("9to12 ATM {$baseSymbol}: no expiries found >= {$instrumentSeries} for type={$optionType}");
                return null;
            }

            $targetExpiry = $config->useNextSeries()
                ? ($allExpiries->get(1) ?? $allExpiries->get(0))
                : $allExpiries->get(0);

            Log::debug(sprintf(
                "9to12 ATM %s [%s]: interval=%d | atmStrike=%d | expiries=[%s] | selected=%s | useNext=%s",
                $baseSymbol, $optionType, $interval, $atmStrike,
                $allExpiries->implode(','), $targetExpiry,
                $config->useNextSeries() ? 'Y' : 'N'
            ));

            $option = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $atmStrike)
                ->whereDate('expiry', $targetExpiry)
                ->first();

            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', $targetExpiry)
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                    ->orderBy('strike_diff')
                    ->first();
            }

            if (!$option) {
                Log::warning("9to12 ATM {$baseSymbol}: no option found type={$optionType} strike≈{$atmStrike} expiry={$targetExpiry}");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            Log::info("9to12 ATM {$baseSymbol}: FOUND {$option->trading_symbol} | type={$optionType} | strike={$option->strike} | expiry={$targetExpiry} | LTP={$ltp}");

            return [
                'symbol' => $option->trading_symbol,
                'token'  => $option->instrument_token,
                'strike' => $option->strike,
                'ltp'    => $ltp,
                'expiry' => $option->expiry,
            ];

        } catch (\Exception $e) {
            Log::error("9to12 ATM {$tradingSymbol}: " . $e->getMessage());
            return null;
        }
    }

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, $tradingSymbol): float
    {
        try {
            $this->ensureKiteInstance($broker);
            $kite     = $this->kiteInstances[$broker->id];
            $quoteKey = "NFO:{$tradingSymbol}";
            $quotes   = $kite->getQuote([$quoteKey]);
            if (isset($quotes->$quoteKey->last_price)) return (float) $quotes->$quoteKey->last_price;
            $arr = json_decode(json_encode($quotes), true);
            if (isset($arr[$quoteKey]['last_price'])) return (float) $arr[$quoteKey]['last_price'];
        } catch (\Exception $e) {
            Log::error("9to12 LTP {$tradingSymbol}: " . $e->getMessage());
        }
        return 0.0; // LTP unavailable — caller will skip this order
    }

    // =========================================================
    //  OI SIGNAL LOGIC
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  ORDER BOOK HELPERS
    // =========================================================

    private function saveToOrderBook(OIIVAutoOrder $order, $orderId, $quantity, $price): void
    {
        try {
            sleep(2);
            $kite         = $this->kiteInstances[$order->broker_api_id] ?? null;
            $orderHistory = $kite ? $kite->getOrderHistory($orderId) : [];
            $last         = end($orderHistory) ?: null;
            OrderBook::create([
                'user_id'            => $order->user_id,
                'broker_username'    => $order->broker->account_user_name,
                'order_id'           => $orderId,
                'status'             => $last->status ?? 'PENDING',
                'trading_symbol'     => $order->option_symbol,
                'order_type'         => $order->order_type,
                'transaction_type'   => 'BUY',
                'product'            => $order->product,
                'price'              => $price ?? '-',
                'quantity'           => $quantity,
                'status_message'     => $last->status_message ?? 'Order placed',
                'order_datetime'     => now(),
                'oiiv_auto_order_id' => $order->id,
            ]);
        } catch (\Exception $e) { Log::error("9to12 ORDER_BOOK: " . $e->getMessage()); }
    }

    private function saveFailedOrder(OIIVAutoOrder $order, $quantity, $price, string $error): void
    {
        try {
            OrderBook::create([
                'user_id'            => $order->user_id,
                'broker_username'    => $order->broker->account_user_name ?? 'N/A',
                'order_id'           => '-', 'status' => 'FAILED',
                'trading_symbol'     => $order->option_symbol,
                'order_type'         => $order->order_type,
                'transaction_type'   => 'BUY',
                'product'            => $order->product,
                'price'              => $price ?? '-',
                'quantity'           => $quantity,
                'status_message'     => substr($error, 0, 500),
                'order_datetime'     => now(),
                'oiiv_auto_order_id' => $order->id,
            ]);
        } catch (\Exception $e) { Log::error("9to12 ORDER_BOOK Failed: " . $e->getMessage()); }
    }

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
            $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
        }
    }
}