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
 * 9:15 AM to 12:15 PM Intraday Auto Trading Helper
 *
 * Reads ONLY from option_ohlc_data (15-min candles).
 *
 * open candle    = 09:15 interval_time
 * current candle = 12:15 interval_time
 *
 * CE/PE OI aggregation: ATM-1 + ATM + ATM+1 strikes matched by POSITION
 *
 * ── ALIGNED SIGNAL FILTER (mirrors frontend logic) ──────────────────────────
 * Orders are ONLY placed when OI Sentiment and FUT 50MA agree:
 *   BUY CE  →  Sentiment = BULLISH  AND  50MA Signal = BULLISH (close > 50MA)
 *   BUY PE  →  Sentiment = BEARISH  AND  50MA Signal = BEARISH (close < 50MA)
 * Mismatched signals (e.g. BEARISH sentiment + Above MA) are skipped.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class PECE9to12AutoTradingHelperBackup
{
    const OPEN_TIME_HOUR      = 9;
    const OPEN_TIME_MINUTE    = 15;
    const CLOSE_TIME_HOUR     = 12;
    const CLOSE_TIME_MINUTE   = 15;
    const LOCK_WINDOW_SECONDS = 90;

    /** Rolling 50-candle MA period (matches frontend getFut50MaSignal) */
    const MA_PERIOD = 50;

    /** Index symbols — all others treated as stocks for qty lookup */
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

    // =========================================================
    //  PUBLIC ENTRY POINTS
    // =========================================================

    /**
     * Main process: aggregate OI from option_ohlc_data, detect signals,
     * apply 50MA alignment filter, and create OIIVAutoOrder records.
     *
     * LIVE MODE - uses current IST date
     * TEST MODE - uses $testDate
     */
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

            $configs = OIIVAutoConfig::where('status', true)
                ->where('config_type', '9to12')
                ->get();

            if ($configs->isEmpty()) {
                Log::info('No active 9to12 configurations found');
                return;
            }

            Log::info("Found {$configs->count()} active 9to12 config(s) | Date: {$currentDate}");

            // ── Aggregate all signals (OI + 50MA) from option_ohlc_data ──
            $aggregatedSignals = $this->aggregateSignalsFromOhlc($currentDate);

            if (empty($aggregatedSignals)) {
                Log::warning('No 9to12 signal data found in option_ohlc_data for ' . $currentDate);
                return;
            }

            // Log alignment summary
            $aligned = array_filter($aggregatedSignals, fn($s) => $s['is_aligned']);
            Log::info(sprintf(
                "Aggregated %d symbol signals | %d aligned (Sentiment + 50MA match) | %d skipped (mismatched)",
                count($aggregatedSignals),
                count($aligned),
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
                    $config, $aggregatedSignals, $broker, $currentDate, $processingDate
                );
            }

            Log::info('=== 9to12: Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('9to12 processSignals Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    /**
     * Place all pending 9to12 orders.
     */
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
    //  CORE: AGGREGATE SIGNALS FROM option_ohlc_data
    // =========================================================

    /**
     * Reads the 09:15 and 12:15 candles from option_ohlc_data for $date,
     * sums CE/PE OI across ATM-1 + ATM + ATM+1 strikes,
     * computes the rolling 50MA signal for each symbol,
     * and returns an array keyed by base_symbol.
     *
     * Each entry includes an `is_aligned` flag that mirrors the frontend
     * "Aligned Signals" logic: order fires only when Sentiment + 50MA agree.
     *
     * @return array  [ 'SYMBOL' => [...signal data...], ... ]
     */
    private function aggregateSignalsFromOhlc(string $date): array
    {
        // Single query — fetch 09:15 and 12:15 candles for FUT + CE + PE
        $candles = OptionOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) IN ('09:15:00', '12:15:00')")
            ->select([
                'base_symbol',
                'instrument_type',
                'strike',
                'strike_position',
                'close',
                'oi',
                'trading_symbol',
                'instrument_token',
                DB::raw("TIME(interval_time) as candle_time"),
            ])
            ->get();

        if ($candles->isEmpty()) return [];

        // Group: [symbol][type][time][] = candle
        $grouped = [];
        foreach ($candles as $c) {
            $time = substr($c->candle_time, 0, 5); // "09:15" | "12:15"
            $grouped[$c->base_symbol][$c->instrument_type][$time][] = $c;
        }

        $signals = [];

        foreach ($grouped as $symbol => $typeMap) {

            // ── FUT candles ──────────────────────────────────────────────
            $futOpen    = $typeMap['FUT']['09:15'][0] ?? null;
            $futCurrent = $typeMap['FUT']['12:15'][0] ?? null;

            if (!$futOpen || !$futCurrent) {
                Log::debug("9to12 {$symbol}: FUT candles missing (09:15 or 12:15) — skipping");
                continue;
            }

            $openClose    = (float) ($futOpen->close    ?? 0);
            $currentClose = (float) ($futCurrent->close ?? 0);

            if ($currentClose <= 0) {
                Log::debug("9to12 {$symbol}: FUT 12:15 close = 0 — skipping");
                continue;
            }

            // ── CE OI aggregation (position-based, mirrors frontend) ─────
            [$ceOpenOI, $ceCurOI] = $this->aggregateByPosition(
                $typeMap['CE']['09:15'] ?? [],
                $typeMap['CE']['12:15'] ?? []
            );

            // ── PE OI aggregation (position-based, mirrors frontend) ─────
            [$peOpenOI, $peCurOI] = $this->aggregateByPosition(
                $typeMap['PE']['09:15'] ?? [],
                $typeMap['PE']['12:15'] ?? []
            );

            if ($ceCurOI == 0 && $peCurOI == 0) {
                Log::debug("9to12 {$symbol}: CE+PE OI both zero — skipping");
                continue;
            }

            // ── OI change % ──────────────────────────────────────────────
            $ceOiPct = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $peOiPct = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;

            // ── OI signal / condition ────────────────────────────────────
            $oiSignal = $this->getOISignal($ceOiPct, $peOiPct);

            // ── PE/CE ratio ──────────────────────────────────────────────
            $peCeRatio = $ceCurOI > 0 ? round($peCurOI / $ceCurOI, 2) : 0;

            // ── 50MA signal (identical to frontend getFut50MaSignal) ─────
            $ma50Signal = $this->getFut50MaSignal($symbol, $date);

            // ── Alignment check (mirrors frontend isAligned()) ───────────
            // BUY CE: Sentiment BULLISH + 50MA BULLISH (close > MA)
            // BUY PE: Sentiment BEARISH + 50MA BEARISH (close < MA)
            $sentiment  = $oiSignal['signal'];
            $isAligned  = ($sentiment === 'BULLISH' && $ma50Signal === 'BULLISH')
                       || ($sentiment === 'BEARISH' && $ma50Signal === 'BEARISH');

            Log::info(sprintf(
                "9to12 %s | OI Sentiment: %s | 50MA: %s | Aligned: %s | CE%%: %.2f | PE%%: %.2f",
                $symbol,
                $sentiment,
                $ma50Signal,
                $isAligned ? 'YES 🎯' : 'NO ❌',
                $ceOiPct,
                $peOiPct
            ));

            $signals[$symbol] = [
                // FUT identifiers
                'underlying_symbol' => $symbol,
                'trading_symbol'    => $futCurrent->trading_symbol ?? $symbol,
                'instrument_token'  => $futCurrent->instrument_token ?? null,

                // FUT prices
                'open_close'    => $openClose,
                'current_close' => $currentClose,
                'spot_price'    => $currentClose,

                // OI data
                'ce_oi_change_pct' => $ceOiPct,
                'pe_oi_change_pct' => $peOiPct,
                'pe_ce_ratio'      => $peCeRatio,

                // Signal metadata
                'oi_condition'    => $oiSignal['condition'],
                'final_sentiment' => $sentiment,
                'trade_action'    => match($sentiment) {
                    'BULLISH' => 'BUY CE',
                    'BEARISH' => 'BUY PE',
                    default   => 'WAIT',
                },

                // 50MA signal
                'fut_50ma_signal' => $ma50Signal,

                // ── Alignment flag ────────────────────────────────────────
                // true  = Sentiment + 50MA confirmed → eligible for order
                // false = Mismatched → order SKIPPED (same as frontend)
                'is_aligned' => $isAligned,

                // trading_date helper
                'trading_date' => $date,
            ];
        }

        return $signals;
    }

    /**
     * Aggregate open and current OI by STRIKE POSITION (ATM / ATM+1 / ATM-1).
     *
     * ⚠️  OLD BUG: matched by strike NUMBER → when market moves intraday the
     *     09:15 ATM strike (e.g. 1210) is a completely different contract than
     *     the 12:15 ATM strike (e.g. 1220), so pairing them gives WRONG OI %.
     *
     * ✅  FIX (mirrors frontend aggregateOptionCandles):
     *     Match open ATM → current ATM, open ATM+1 → current ATM+1, etc.
     *     This is correct because the position tag always reflects the
     *     prevailing ATM at the time of the candle, exactly as the frontend does.
     *
     * @param array $openCandles  09:15 candles for a type (CE or PE)
     * @param array $curCandles   12:15 candles for a type (CE or PE)
     * @return array  [openOiTotal, curOiTotal]
     */
    private function aggregateByPosition(array $openCandles, array $curCandles): array
    {
        // Index current candles by strike POSITION ('ATM', 'ATM+1', 'ATM-1')
        $curByPosition = [];
        foreach ($curCandles as $c) {
            $pos = $c->strike_position ?? null;
            if (!$pos || $pos === 'N/A') continue;
            $curByPosition[$pos] = $c;
        }

        $openOI = 0;
        $curOI  = 0;

        foreach ($openCandles as $oc) {
            $pos = $oc->strike_position ?? null;
            if (!$pos || $pos === 'N/A') continue;

            $cc = $curByPosition[$pos] ?? null;
            if (!$cc) continue;

            // Skip if current candle has no valid close (data gap)
            if ((float)($cc->close ?? 0) <= 0) continue;

            $openOI += (int) ($oc->oi ?? 0);
            $curOI  += (int) ($cc->oi ?? 0);
        }

        return [$openOI, $curOI];
    }

    // =========================================================
    //  50MA SIGNAL  (exact port of frontend getFut50MaSignal)
    // =========================================================

    /**
     * Calculate rolling 50-candle MA values from an array of close prices.
     * Identical algorithm to the frontend PHP controller.
     */
    private function calculateRollingMA(array $values, int $period): array
    {
        $ma  = [];
        $n   = count($values);
        $sum = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];
            if ($i >= $period) $sum -= $values[$i - $period];
            $ma[] = ($i >= $period - 1) ? ($sum / $period) : null;
        }

        return $ma;
    }

    /**
     * How many calendar days back to fetch so we have ~50 trading candles.
     * Mirrors frontend historyStartDate().
     */
    private function historyStartDate(string $tradeDate, int $maPeriod = 50): string
    {
        $daysBack = (int) ceil($maPeriod / 25) + 3; // ~5 weeks of buffer
        return Carbon::parse($tradeDate)->subDays($daysBack)->toDateString();
    }

    /**
     * Compute the FUT 50MA signal for $baseSymbol on $tradeDate at ~12:15.
     *
     * Returns: 'BULLISH' | 'BEARISH' | 'NEUTRAL' | 'N/A'
     *
     * Exact port of OIIVAuto9to12Controller::getFut50MaSignal()
     */
    private function getFut50MaSignal(string $baseSymbol, string $tradeDate): string
    {
        $maPeriod     = self::MA_PERIOD;
        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->whereBetween('trade_date', [$historyStart, $tradeDate])
            ->orderBy('trade_date')
            ->orderBy('interval_time')
            ->get([
                DB::raw("DATE(trade_date) as candle_date"),
                DB::raw("TIME(interval_time) as candle_time"),
                'close',
            ]);

        if ($allCandles->isEmpty()) {
            Log::debug("9to12 50MA {$baseSymbol}: no candles found for history window");
            return 'N/A';
        }

        $closeValues = $allCandles->pluck('close')->map(fn($v) => (float) $v)->toArray();
        $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

        // Find the index of the 12:15 candle on tradeDate (fallback: last candle of the day)
        $targetIdx = null;
        foreach ($allCandles as $idx => $candle) {
            $candleDate = is_string($candle->candle_date)
                ? $candle->candle_date
                : Carbon::parse($candle->candle_date)->toDateString();

            if ($candleDate !== $tradeDate) continue;
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($time >= '12:15' && $time <= '12:29') {
                $targetIdx = $idx;
                break;
            }
        }

        // Fallback: use the last candle of tradeDate
        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $candleDate = is_string($candle->candle_date)
                    ? $candle->candle_date
                    : Carbon::parse($candle->candle_date)->toDateString();
                if ($candleDate === $tradeDate) $targetIdx = $idx;
            }
        }

        if ($targetIdx === null || !isset($closeMa[$targetIdx])) {
            Log::debug("9to12 50MA {$baseSymbol}: cannot locate target candle (idx={$targetIdx})");
            return 'N/A';
        }

        $close = $closeValues[$targetIdx];
        $ma    = $closeMa[$targetIdx];

        if ($ma === null) {
            Log::debug("9to12 50MA {$baseSymbol}: MA not yet warm (need more candles)");
            return 'N/A';
        }

        Log::debug("9to12 50MA {$baseSymbol}: close={$close} | MA50={$ma}");

        if ($close > $ma) return 'BULLISH';
        if ($close < $ma) return 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  PRIVATE: SIGNAL PROCESSING
    // =========================================================

    private function processConfigSignals(
        OIIVAutoConfig $config,
        array $aggregatedSignals,
        BrokerApi $broker,
        string $currentDate,
        Carbon $processingDateTime
    ): void {
        $created = $skipped = $skippedAlignment = $errors = 0;

        foreach ($aggregatedSignals as $symbol => $signalData) {
            try {
                // ── 1. Alignment gate (Sentiment + 50MA must match) ───────────
                if (!$signalData['is_aligned']) {
                    Log::info(sprintf(
                        "9to12 %s: SKIPPED (not aligned) | Sentiment: %s | 50MA: %s",
                        $symbol,
                        $signalData['final_sentiment'],
                        $signalData['fut_50ma_signal']
                    ));
                    $skippedAlignment++;
                    continue;
                }

                // ── 2. Rank & direction ───────────────────────────────────────
                $ce = (float) ($signalData['ce_oi_change_pct'] ?? 0);
                $pe = (float) ($signalData['pe_oi_change_pct'] ?? 0);

                $rank = OIIVAutoConfig::computeStrengthRank($ce, $pe);

                if ($rank === null) {
                    Log::debug("9to12 {$symbol}: NORMAL signal (diff <=5) - skipping");
                    $skipped++;
                    continue;
                }

                $direction = OIIVAutoConfig::computeSignalDirection($ce, $pe);

                if ($direction === 'NORMAL') {
                    Log::debug("9to12 {$symbol}: direction=NORMAL - skipping");
                    $skipped++;
                    continue;
                }

                $rawOptionType   = $direction === 'BULLISH' ? 'CE' : 'PE';
                $finalOptionType = $config->shouldReverseSignal()
                    ? ($rawOptionType === 'CE' ? 'PE' : 'CE')
                    : $rawOptionType;

                // ── 3. Quantity: index qty or stock qty only ──────────────────
                // Rank-based quantities (rank1/2/3/4) are NOT used here.
                // Index symbols  → index_ce_quantity / index_pe_quantity
                // Stock symbols  → stock_ce_quantity / stock_pe_quantity
                $isIndex = in_array(strtoupper($symbol), self::INDEX_SYMBOLS);

                if ($finalOptionType === 'CE') {
                    $quantity = $isIndex
                        ? (int) ($config->index_ce_quantity ?? 0)
                        : (int) ($config->stock_ce_quantity ?? 0);
                } else {
                    $quantity = $isIndex
                        ? (int) ($config->index_pe_quantity ?? 0)
                        : (int) ($config->stock_pe_quantity ?? 0);
                }

                if ($quantity <= 0) {
                    Log::info("9to12 {$symbol}: qty=0 (" . ($isIndex ? 'index' : 'stock') . " {$finalOptionType}) - skipping");
                    $skipped++;
                    continue;
                }

                // ── 4. Duplicate check ────────────────────────────────────────
                $exists = OIIVAutoOrder::where('config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->whereDate('signal_detected_at', $currentDate)
                    ->where('status', true)
                    ->exists();

                if ($exists) {
                    Log::debug("9to12 {$symbol}: Order already exists for today");
                    $skipped++;
                    continue;
                }

                // ── 5. Create order ───────────────────────────────────────────
                $result = $this->analyzeAndCreateOrder(
                    $config,
                    $signalData,
                    $broker,
                    $currentDate,
                    $processingDateTime,
                    $rank,
                    $direction,
                    $rawOptionType,
                    $finalOptionType,
                    $quantity
                );

                $result ? $created++ : $errors++;

            } catch (\Exception $e) {
                Log::error("9to12 Error processing {$symbol}: " . $e->getMessage());
                $errors++;
            }
        }

        Log::info(sprintf(
            "9to12 Config %d — Created: %d | Skipped (alignment): %d | Skipped (other): %d | Errors: %d",
            $config->id,
            $created,
            $skippedAlignment,
            $skipped,
            $errors
        ));
    }

    private function analyzeAndCreateOrder(
        OIIVAutoConfig $config,
        array $signalData,
        BrokerApi $broker,
        string $date,
        Carbon $processingDateTime,
        int $rank,
        string $direction,
        string $rawOptionType,
        string $finalOptionType,
        int $quantity
    ): bool {
        try {
            $symbol = $signalData['underlying_symbol'];

            Log::info(sprintf(
                "9to12 ANALYZE - Config %d | %s | Rank %d | %s => %s | Sentiment: %s | 50MA: %s [ALIGNED 🎯]",
                $config->id,
                $symbol,
                $rank,
                $direction,
                $finalOptionType,
                $signalData['final_sentiment'],
                $signalData['fut_50ma_signal']
            ));

            // Must be at or after 12:15 PM
            $lockTime = Carbon::parse(
                $date . ' ' . self::CLOSE_TIME_HOUR . ':' . self::CLOSE_TIME_MINUTE . ':00',
                'Asia/Kolkata'
            );

            if ($processingDateTime->lessThan($lockTime)) {
                Log::info("  Before 12:15 PM - skipping");
                return false;
            }

            $openingPrice = $signalData['open_close']    ?? null;
            $currentPrice = $signalData['current_close'] ?? null;

            if (!$currentPrice || $currentPrice <= 0) {
                Log::error("  12:15 PM price not available for {$symbol}");
                return false;
            }

            Log::info("  Open(9:15): ₹{$openingPrice} | Close(12:15): ₹{$currentPrice}");

            $optionDetails = $this->getATMOption(
                $broker,
                $signalData['trading_symbol'],
                $finalOptionType,
                $currentPrice,
                $config
            );

            if (!$optionDetails) {
                Log::error("  Could not find ATM option for {$symbol}");
                return false;
            }

            $ce = (float) ($signalData['ce_oi_change_pct'] ?? 0);
            $pe = (float) ($signalData['pe_oi_change_pct'] ?? 0);

            $modeLabel   = $config->shouldReverseSignal() ? 'OPPOSITE' : 'ALIGN';
            $seriesLabel = $config->useNextSeries() ? 'NEXT' : 'CURRENT';
            $signalLabel = "9TO12_RANK{$rank}_{$direction}_{$modeLabel}_{$finalOptionType}_MA50ALIGNED";

            $reason = sprintf(
                "9to12 | Rank: %d | Direction: %s | Config Mode: %s | Series: %s | Final: BUY %s | CE%%: %.2f | PE%%: %.2f | Diff: %.2f | Condition: %s | Sentiment: %s | 50MA: %s | ALIGNED: YES | Ratio: %.2f | Qty: %d",
                $rank,
                $direction,
                $modeLabel,
                $seriesLabel,
                $finalOptionType,
                $ce,
                $pe,
                abs($ce - $pe),
                $signalData['oi_condition']    ?? 'N/A',
                $signalData['final_sentiment'] ?? 'N/A',
                $signalData['fut_50ma_signal'] ?? 'N/A',
                $signalData['pe_ce_ratio']     ?? 0,
                $quantity
            );

            $signalDetectedAt = Carbon::parse($date . ' 12:15:00', 'Asia/Kolkata');

            $order = OIIVAutoOrder::create([
                'user_id'          => $config->user_id,
                'config_id'        => $config->id,
                'broker_api_id'    => $broker->id,
                'symbol'           => $symbol,
                'trading_symbol'   => $signalData['trading_symbol'],
                'instrument_token' => $signalData['instrument_token'] ?? null,

                'btst_signal'        => $signalLabel,
                'btst_confidence'    => 100,
                'btst_reason'        => $reason,
                'signal_detected_at' => $signalDetectedAt,

                'fut_oi_signal'   => "9to12 Rank{$rank} | OI: " . ($signalData['oi_condition'] ?? 'N/A'),
                'fut_oi_strength' => $signalData['final_sentiment'] ?? 'N/A',

                'ce_oi_signal'   => 'N/A',
                'pe_oi_signal'   => 'N/A',
                'ce_iv_signal'   => 'N/A',
                'ce_iv_strength' => 'N/A',
                'pe_iv_signal'   => 'N/A',
                'pe_iv_strength' => 'N/A',

                'spot_price'      => $currentPrice,
                'option_symbol'   => $optionDetails['symbol'],
                'option_token'    => $optionDetails['token'],
                'option_type'     => $finalOptionType,
                'strike_price'    => $optionDetails['strike'],
                'entry_price'     => $optionDetails['ltp'],
                'current_price'   => $optionDetails['ltp'],
                'order_type'      => $config->order_type,
                'product'         => $config->product,
                'quantity'        => $quantity,
                'is_order_placed' => false,
                'status'          => true,
            ]);

            Log::info("9to12 Order created! ID: {$order->id} | {$optionDetails['symbol']} | Rank {$rank} | Qty: {$quantity} | LTP: ₹{$optionDetails['ltp']} | 50MA: {$signalData['fut_50ma_signal']} [ALIGNED]");
            return true;

        } catch (\Exception $e) {
            Log::error("9to12 ANALYZE {$signalData['underlying_symbol']}: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    //  PRIVATE: ORDER PLACEMENT
    // =========================================================

    private function placeOrder(OIIVAutoOrder $order): void
    {
        try {
            Log::info("9to12 ORDER Placing: {$order->option_symbol}");

            $broker = $order->broker;
            if (!$broker->hasValidToken()) {
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Invalid token");
                return;
            }

            $this->ensureKiteInstance($broker);

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();
            if (!$instrument) {
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Instrument not found");
                return;
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
            $price    = number_format(
                round($raw / $instrument->tick_size) * $instrument->tick_size,
                2, '.', ''
            );
        }

        $baseSymbol      = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->trading_symbol);
        $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

        if ($freezeLimitLots && $quantity > $freezeLimitLots) {
            Log::info("9to12 FREEZE Splitting {$quantity} lots into batches of {$freezeLimitLots}");
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
            'exchange'         => 'NFO',
            'tradingsymbol'    => $order->option_symbol,
            'transaction_type' => 'BUY',
            'quantity'         => $quantity * $instrument->lot_size,
            'product'          => $order->product,
            'validity'         => 'DAY',
        ];

        if ($order->order_type == 'MARKET') {
            $params['order_type'] = 'MARKET';
        } else {
            $params['order_type'] = 'LIMIT';
            $params['price']      = $price;
        }

        $result = $kite->placeOrder("regular", $params);
        Log::info("9to12 ORDER Placed! Kite Order ID: {$result->order_id}");
        $this->saveToOrderBook($order, $result->order_id, $quantity, $price);
    }

    // =========================================================
    //  PRIVATE: ATM OPTION LOOKUP
    // =========================================================

    private function getATMOption(
        BrokerApi $broker,
        string $tradingSymbol,
        string $optionType,
        float $futurePrice,
        OIIVAutoConfig $config
    ): ?array {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);

            $strikeIntervals = [
                'NIFTY' => 100, 'BANKNIFTY' => 100, 'FINNIFTY' => 50, 'MIDCPNIFTY' => 25,
                'AXISBANK' => 10, 'ICICIBANK' => 10, 'INDUSINDBK' => 10,
                'BHARTIARTL' => 20, 'SHRIRAMFIN' => 10, 'LTF' => 5, 'PAYTM' => 20,
                'POLICYBZR' => 20, 'BAJAJFINSV' => 20, 'INFY' => 20, 'TATAELXSI' => 50,
                'TATATECH' => 10, 'HAVELLS' => 20, 'TITAN' => 20, 'ASIANPAINT' => 20,
                'TATACONSUMER' => 10, 'VOLTAS' => 20, 'AUROPHARMA' => 10, 'LAURUSLABS' => 10,
                'SRF' => 20, 'JSWSTEEL' => 10, 'LT' => 20, 'BHEL' => 5,
                'ADANIPORTS' => 20, 'HAL' => 50, 'BDL' => 20, 'MCX' => 20, 'BSE' => 50,
                'CDSL' => 20, 'LICHSG' => 5, 'DELHIVERY' => 10, 'BHARATFORG' => 20,
                'PGEL' => 10, 'TMPV' => 5, 'HINDALCO' => 10, 'VEDL' => 10,
                'DRREDDY' => 50, 'TATACONSUM' => 10, 'HEROMOTOCO' => 20,
            ];

            $interval  = $strikeIntervals[$baseSymbol] ?? 20;
            $atmStrike = round($futurePrice / $interval) * $interval;

            // ── Expiry selection ──────────────────────────────────────────────
            // 'current' → nearest expiry (orderBy ASC, first)
            // 'next'    → skip nearest, use second expiry (skip current month)
            //
            // How it works:
            //   All distinct expiries >= today are ordered ASC.
            //   current → first expiry  (e.g. 27-FEB = same month as FUT)
            //   next    → second expiry (e.g. 27-MAR = following month)
            //
            $useNext = $config->useNextSeries();

            if ($useNext) {
                // Get all distinct expiries for this symbol >= today, sorted ASC
                $expiries = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->distinct()
                    ->orderBy('expiry', 'ASC')
                    ->pluck('expiry');

                // We need at least 2 expiries; if only 1 exists fall back to current
                $targetExpiry = $expiries->get(1) ?? $expiries->get(0) ?? null;

                if (!$targetExpiry) return null;

                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->where('strike', $atmStrike)
                    ->whereDate('expiry', $targetExpiry)
                    ->first();

                // Fallback: nearest strike in target expiry
                if (!$option) {
                    $option = ZerodhaInstrument::where('name', $baseSymbol)
                        ->where('exchange', 'NFO')
                        ->where('instrument_type', $optionType)
                        ->whereDate('expiry', $targetExpiry)
                        ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                        ->orderBy('strike_diff', 'ASC')
                        ->first();
                }

                Log::debug("9to12 ATM {$baseSymbol}: NEXT series expiry={$targetExpiry} | strike={$atmStrike}");

            } else {
                // Current series — nearest expiry (original behaviour)
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->where('strike', $atmStrike)
                    ->whereDate('expiry', '>=', now())
                    ->orderBy('expiry', 'ASC')
                    ->first();

                // Fallback: nearest strike
                if (!$option) {
                    $option = ZerodhaInstrument::where('name', $baseSymbol)
                        ->where('exchange', 'NFO')
                        ->where('instrument_type', $optionType)
                        ->whereDate('expiry', '>=', now())
                        ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                        ->orderBy('strike_diff', 'ASC')
                        ->orderBy('expiry', 'ASC')
                        ->first();
                }

                Log::debug("9to12 ATM {$baseSymbol}: CURRENT series | strike={$atmStrike}");
            }

            if (!$option) return null;

            $seriesLabel = $useNext ? 'NEXT' : 'CURRENT';
            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            return [
                'symbol' => $option->trading_symbol,
                'token'  => $option->instrument_token,
                'strike' => $option->strike,
                'ltp'    => $ltp,
                'expiry' => $option->expiry,
                'series' => $seriesLabel,
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

            if (isset($quotes->$quoteKey->last_price)) {
                return (float) $quotes->$quoteKey->last_price;
            }

            $arr = json_decode(json_encode($quotes), true);
            if (isset($arr[$quoteKey]['last_price'])) {
                return (float) $arr[$quoteKey]['last_price'];
            }

        } catch (\Exception $e) {
            Log::error("9to12 LTP {$tradingSymbol}: " . $e->getMessage());
        }

        return 25.00;
    }

    // =========================================================
    //  PRIVATE: OI SIGNAL LOGIC
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup but CE stronger", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup but PE stronger", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding but CE stronger", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding but PE stronger", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // =========================================================
    //  PRIVATE: ORDER BOOK HELPERS
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

        } catch (\Exception $e) {
            Log::error("9to12 ORDER_BOOK saveToOrderBook: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(OIIVAutoOrder $order, $quantity, $price, string $error): void
    {
        try {
            OrderBook::create([
                'user_id'            => $order->user_id,
                'broker_username'    => $order->broker->account_user_name ?? 'N/A',
                'order_id'           => '-',
                'status'             => 'FAILED',
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
        } catch (\Exception $e) {
            Log::error("9to12 ORDER_BOOK saveFailedOrder: " . $e->getMessage());
        }
    }

    // =========================================================
    //  PRIVATE: UTILITIES
    // =========================================================

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
            $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
        }
    }
}