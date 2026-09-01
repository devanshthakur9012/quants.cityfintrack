<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Price + OI Buy Confirmation — STANDALONE, READ-ONLY analysis page.
 *
 * ⚠️ SCHEMA ASSUMPTION — VERIFY BEFORE USE ⚠️
 * This page needs 15-minute OHLCV+VWAP candles for the underlying
 * (spot/index) — a DIFFERENT table from cp_option_ohlc_15min (that one
 * is options chain data, used only for the OI half below). I don't
 * have your real spot-candle table/columns, so I've assumed:
 *   Table:   cp_spot_ohlc_15min
 *   Columns: base_symbol, trade_date, interval_time,
 *            open, high, low, close, volume, vwap
 * If your real table/columns differ, only the CANDLE_TABLE constant
 * and the ->select([...]) list inside evaluatePriceForSlot() need to
 * change — nothing else in this file depends on the exact names.
 * VWAP must already be a stored column per 15-min interval — this
 * page does not compute VWAP itself.
 *
 * ── WHAT THIS PAGE DOES ─────────────────────────────────────────────
 * For each symbol, at 3 intraday checkpoints (10:15, 11:15, 12:15),
 * combines TWO independent confirmations into one BUY / WAIT call:
 *
 *   1) PRICE LOGIC — ported from client's checkBuyPriceLogic(). Runs on
 *      15-MINUTE candles (matching the OI side's granularity — no
 *      separate 5-min table). Scoring UNCHANGED from what was supplied;
 *      the only adjustment is the "15 minutes ago" lookup, which is now
 *      the previous candle (t-1) instead of t-3, since each candle
 *      already spans 15 minutes:
 *        Price > VWAP                                   → +2
 *        VWAP rising (VWAP(t) > VWAP(t-1), 15m ago)      → +1
 *        Higher Low (low[t-1] > low[t-3])                → +2
 *        Breakout (close > previous 15-min candle high)  → +2
 *        Volume > avg volume of previous 5 candles       → +1
 *      Max score = 8. PRICE_SCORE_THRESHOLD (6) needed for PRICE = BUY.
 *
 *   2) OI SCORE — built from the SAME Decay Velocity + OI Signal this
 *      platform already computes in AdvancedOIMetricsController.
 *      Duplicated here intentionally (same convention as that
 *      controller — nothing shared/imported, that controller is never
 *      touched):
 *        Decay Signal BULLISH → +2, BEARISH → -2, else 0
 *        OI Signal    BULLISH → +2, BEARISH → -2, else 0
 *      Range: -4 to +4. OI_SCORE_THRESHOLD (2) needed to confirm.
 *
 *   FINAL SIGNAL = BUY only if PRICE signal = BUY AND OI score >=
 *   OI_SCORE_THRESHOLD. Otherwise WAIT.
 *
 * This is a BULLISH-ONLY (BUY CE) confirmation page — that's the exact
 * logic supplied. A mirrored bearish/PE version can be built the same
 * way later if needed.
 *
 * OI anchor = previous trading day 15:00 close, same convention as
 * OIFlowSentimentController / AdvancedOIMetricsController.
 */
class PriceOiConfirmationController extends Controller
{
    // ── OI side (options chain) ──
    private const OPT_TABLE      = 'cp_option_ohlc_15min';
    private const PREV_DAY_TIME  = '15:00:00'; // previous trading day close anchor
    private const BASKET_OFFSETS = [-1, 0, 1];

    /** Keep in sync with AdvancedOIMetricsController::DECAY_THRESHOLD if you change it there. */
    private const DECAY_THRESHOLD = 0.70;

    // ── Price side (spot/index candles) — ⚠️ ADJUST TO YOUR REAL TABLE ──
    // 15-MINUTE candles, matching the OI side's granularity. No 5-min table used anywhere.
    private const CANDLE_TABLE = 'cp_spot_ohlc_15min';

    /** The three intraday checkpoints this page evaluates. */
    private const ANALYSIS_TIMES = [
        '10:15' => '10:15:00',
        '11:15' => '11:15:00',
        '12:15' => '12:15:00',
    ];

    private const LAST_SLOT_TIME = '12:15:00';

    /** Minimum price score (out of 8) required to call PRICE = BUY. */
    private const PRICE_SCORE_THRESHOLD = 6;

    /** Minimum OI score (out of ±4) required to confirm. */
    private const OI_SCORE_THRESHOLD = 2;

    /** Reused only to pull the shared active config / symbol list. */
    private const TF = '15min';

    public function index()
    {
        $pageTitle = 'Price + OI Buy Confirmation';
        return view(activeTemplate() . 'user.price-oi-confirmation.index', compact('pageTitle'));
    }

    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            $today  = Carbon::today()->toDateString();

            if (!$config) {
                return response()->json(['success' => false, 'last_date' => $today, 'is_today' => true]);
            }

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw('TIME(interval_time) = ?', [self::LAST_SLOT_TIME])
                ->max('trade_date');

            if (!$lastDate) {
                $lastDate = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $config->id)
                    ->where('is_missing', false)
                    ->max('trade_date');
            }

            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

            return response()->json(['success' => true, 'last_date' => $lastDate, 'is_today' => $lastDate === $today]);
        } catch (\Exception $e) {
            Log::error('PriceOiConfirmationController lastDate: ' . $e->getMessage());
            return response()->json(['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true]);
        }
    }

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json(['success' => true, 'symbols' => [], 'no_config' => true, 'message' => 'No active analysis config found.']);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    public function analyze(Request $request): JsonResponse
    {
        $date = $request->get('date');
        if (!$date) {
            return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
        }

        $symbolReq = array_filter((array) $request->get('symbols', []));

        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success' => false, 'no_config' => true,
                    'message' => 'No active Analysis Config found. Go to Admin → Analysis Config.', 'data' => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            if (empty($symbols)) {
                return response()->json(['success' => false, 'message' => 'Symbol not in active config.', 'available_symbols' => $configSymbols, 'data' => []]);
            }

            $rows = [];
            foreach ($symbols as $symbol) {
                $result = $this->runForSymbol($config, $symbol, $date);
                $rows[] = array_merge(['symbol' => $symbol, 'date' => $date], $result);
            }

            usort($rows, fn ($a, $b) => strcmp($a['symbol'], $b['symbol']));

            return response()->json([
                'success'           => true,
                'data'              => $rows,
                'date'              => $date,
                'is_today'          => $date === Carbon::today()->toDateString(),
                'available_symbols' => $configSymbols,
                'total_records'     => count($rows),
                'message'           => count($rows) . ' symbol(s) analyzed for ' . $date,
            ]);
        } catch (\Exception $e) {
            Log::error('PriceOiConfirmationController analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ═════════════════════════ PER-SYMBOL/DAY COMPUTATION ═════════════════════════

    private function runForSymbol(object $config, string $symbol, string $date): array
    {
        try {
            $prevDate = $this->getPreviousTradingDate($date);
            $anchorOi = $this->fetchAnchorOi($config, $symbol, $prevDate);

            $slots          = [];
            $anySlotHasData = false;

            foreach (self::ANALYSIS_TIMES as $label => $time) {
                $priceResult = $this->evaluatePriceForSlot($symbol, $date, $time);
                $oiResult    = $this->evaluateOiForSlot($config, $symbol, $date, $time, $anchorOi);

                if ($priceResult['has_data'] || $oiResult['has_data']) $anySlotHasData = true;

                $final = 'WAIT';
                if ($priceResult['has_data'] && $oiResult['has_data']) {
                    $final = ($priceResult['signal'] === 'BUY' && $oiResult['score'] >= self::OI_SCORE_THRESHOLD)
                        ? 'BUY'
                        : 'WAIT';
                }

                $slots[$label] = [
                    'time'         => $time,
                    'price'        => $priceResult,
                    'oi'           => $oiResult,
                    'final_signal' => $final,
                ];
            }

            if (!$anySlotHasData) {
                return ['success' => true, 'no_data' => true, 'message' => "No data found for {$symbol} on {$date}.", 'slots' => $this->emptySlots()];
            }

            return ['success' => true, 'no_data' => false, 'slots' => $slots];
        } catch (\Exception $e) {
            Log::error('PriceOiConfirmationController runForSymbol (' . $symbol . '): ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'slots' => $this->emptySlots()];
        }
    }

    // ═════════════════════════ PRICE SIDE (15-MIN CANDLES) ═════════════════════════

    /**
     * Pulls 15-min candles for `date` up to and including `time`, then runs
     * the client's checkBuyPriceLogic against them.
     */
    private function evaluatePriceForSlot(string $symbol, string $date, string $time): array
    {
        $candles = DB::table(self::CANDLE_TABLE)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)
            ->whereTime('interval_time', '<=', $time)
            ->orderBy('interval_time')
            ->select(['interval_time as time', 'open', 'high', 'low', 'close', 'volume', 'vwap']) // ⚠️ adjust column names here if needed
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        if (count($candles) < 4) {
            return [
                'has_data' => false,
                'signal'   => 'INSUFFICIENT_DATA',
                'score'    => null,
                'reasons'  => [],
                'message'  => 'Not enough candle data',
            ];
        }

        $result = $this->checkBuyPriceLogic($candles);

        return [
            'has_data'            => true,
            'signal'              => $result['signal'],
            'score'               => $result['price_score'],
            'price'               => $result['price'],
            'vwap'                => $result['vwap'],
            'vwap_15min_ago'      => $result['vwap_15min_ago'],
            'price_above_vwap'    => $result['price_above_vwap'],
            'vwap_rising'         => $result['vwap_rising'],
            'higher_low'          => $result['higher_low'],
            'breakout'            => $result['breakout'],
            'volume_confirmation' => $result['volume_confirmation'],
            'reasons'             => $result['reasons'],
        ];
    }

    /**
     * Ported from the client's checkBuyPriceLogic() — SCORING LOGIC
     * UNCHANGED. Only the "15 minutes ago" lookup was adjusted: the
     * client's original formula assumed 5-min candles (t-3 = 15 min
     * back). On 15-min candles, each candle already IS 15 minutes, so
     * "15 minutes ago" = the immediately previous candle, t-1 — not
     * t-3. Higher Low still compares t-1 vs t-3 (unchanged, per the
     * client's original swing-comparison intent). Volume average
     * window (previous 5 candles) is also unchanged, now spanning
     * 75 minutes instead of 25.
     *
     * $candles must be chronologically ordered, each entry with keys:
     * time, open, high, low, close, volume, vwap.
     */
    private function checkBuyPriceLogic(array $candles): array
    {
        $count = count($candles);

        if ($count < 4) {
            return ['signal' => 'WAIT', 'reason' => 'Not enough candle data'];
        }

        $t = $count - 1;
        $current = $candles[$t];

        // 15-min candles: "15 minutes ago" = previous candle (t-1), not t-3.
        $previous15 = $candles[$t - 1];

        $price        = (float) $current['close'];
        $vwap         = (float) $current['vwap'];
        $vwap15MinAgo = (float) $previous15['vwap'];

        /*
         * --------------------------------------------------
         * 1. PRICE ABOVE VWAP
         * --------------------------------------------------
         */
        $priceAboveVWAP = $price > $vwap;

        /*
         * --------------------------------------------------
         * 2. VWAP IS RISING
         * VWAP(t) > VWAP(t-1)  [t-1 = 15 min ago on 15-min bars]
         *
         * At 10:15:
         * VWAP(10:15) > VWAP(10:00)
         * --------------------------------------------------
         */
        $vwapRising = $vwap > $vwap15MinAgo;

        /*
         * --------------------------------------------------
         * 3. HIGHER LOW
         *
         * Compare the previous swing low with the low
         * before it.
         *
         * Simple implementation:
         * candle t-1 low > candle t-3 low
         * --------------------------------------------------
         */
        $higherLow = false;
        if ($count >= 4) {
            $lowRecent   = (float) $candles[$t - 1]['low'];
            $lowPrevious = (float) $candles[$t - 3]['low'];
            $higherLow   = $lowRecent > $lowPrevious;
        }

        /*
         * --------------------------------------------------
         * 4. BREAKOUT OF PREVIOUS 15-MIN CANDLE HIGH
         * --------------------------------------------------
         */
        $previousHigh = (float) $candles[$t - 1]['high'];
        $breakout     = $price > $previousHigh;

        /*
         * --------------------------------------------------
         * 5. VOLUME CONFIRMATION
         *
         * Current volume > average volume
         * of previous 5 candles
         * --------------------------------------------------
         */
        $volumeConfirmation = false;
        if ($count >= 6) {
            $totalVolume = 0;
            for ($i = $t - 5; $i < $t; $i++) {
                $totalVolume += (float) $candles[$i]['volume'];
            }
            $averageVolume = $totalVolume / 5;
            $currentVolume = (float) $current['volume'];
            $volumeConfirmation = $currentVolume > $averageVolume;
        }

        /*
         * --------------------------------------------------
         * SCORE
         * --------------------------------------------------
         */
        $score   = 0;
        $reasons = [];

        if ($priceAboveVWAP) {
            $score += 2;
            $reasons[] = 'Price above VWAP';
        }
        if ($vwapRising) {
            $score += 1;
            $reasons[] = 'VWAP rising';
        }
        if ($higherLow) {
            $score += 2;
            $reasons[] = 'Higher Low formed';
        }
        if ($breakout) {
            $score += 2;
            $reasons[] = 'Breakout above previous 15-min high';
        }
        if ($volumeConfirmation) {
            $score += 1;
            $reasons[] = 'Volume confirmation';
        }

        /*
         * --------------------------------------------------
         * FINAL PRICE SIGNAL
         *
         * Maximum price score = 8
         * Require at least PRICE_SCORE_THRESHOLD (6) points.
         * --------------------------------------------------
         */
        $signal = $score >= self::PRICE_SCORE_THRESHOLD ? 'BUY' : 'WAIT';

        return [
            'time'                => $current['time'],
            'price'               => $price,
            'vwap'                => $vwap,
            'vwap_15min_ago'      => $vwap15MinAgo,
            'price_above_vwap'    => $priceAboveVWAP,
            'vwap_rising'         => $vwapRising,
            'higher_low'          => $higherLow,
            'breakout'            => $breakout,
            'volume_confirmation' => $volumeConfirmation,
            'price_score'         => $score,
            'signal'              => $signal,
            'reasons'             => $reasons,
        ];
    }

    // ═════════════════════════ OI SIDE (duplicated from AdvancedOIMetricsController, on purpose) ═════════════════════════

    private function fetchAnchorOi(object $config, string $symbol, string $prevDate): array
    {
        $anchorRows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $config->id)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', $prevDate)
            ->whereRaw('TIME(interval_time) = ?', [self::PREV_DAY_TIME])
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('is_missing', false)
            ->select(['instrument_type', 'strike', 'oi'])
            ->get();

        $anchorOi = ['CE' => [], 'PE' => []];
        foreach ($anchorRows as $r) {
            $key = $this->strikeKey($r->strike);
            $anchorOi[$r->instrument_type][$key] = (int) $r->oi;
        }
        return $anchorOi;
    }

    private function evaluateOiForSlot(object $config, string $symbol, string $date, string $time, array $anchorOi): array
    {
        $liveRows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $config->id)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)
            ->whereRaw('TIME(interval_time) = ?', [$time])
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('is_missing', false)
            ->select(['instrument_type', 'strike', 'atm_strike', 'oi'])
            ->get();

        if ($liveRows->isEmpty()) {
            return ['has_data' => false, 'score' => null, 'decay_signal' => 'INSUFFICIENT_DATA', 'oi_sentiment' => 'INSUFFICIENT_DATA'];
        }

        $liveOi    = ['CE' => [], 'PE' => []];
        $atmStrike = null;

        foreach ($liveRows as $r) {
            $key = $this->strikeKey($r->strike);
            $liveOi[$r->instrument_type][$key] = (int) $r->oi;
            if ($atmStrike === null) $atmStrike = (float) $r->atm_strike;
        }

        $ladder = collect(array_keys($liveOi['CE']))->map(fn ($s) => (float) $s)->unique()->sort()->values()->toArray();
        $atmIndex = $this->findAtmIndex($ladder, $atmStrike);

        $ce = $this->basketVelocity($ladder, $atmIndex, $liveOi['CE'], $anchorOi['CE']);
        $pe = $this->basketVelocity($ladder, $atmIndex, $liveOi['PE'], $anchorOi['PE']);
        $decaySignal = $this->deriveDecaySignal($ce, $pe);
        $oiSentiment = $this->buildOiSentiment($liveOi, $anchorOi);

        $score = 0;
        if ($decaySignal === 'BULLISH') $score += 2;
        if ($decaySignal === 'BEARISH') $score -= 2;
        if ($oiSentiment === 'BULLISH') $score += 2;
        if ($oiSentiment === 'BEARISH') $score -= 2;

        return [
            'has_data'     => true,
            'score'        => $score,
            'decay_ce'     => $ce,
            'decay_pe'     => $pe,
            'decay_signal' => $decaySignal,
            'oi_sentiment' => $oiSentiment,
            'atm_strike'   => $atmStrike,
        ];
    }

    private function basketVelocity(array $ladder, ?int $atmIndex, array $liveMap, array $anchorMap): ?float
    {
        if ($atmIndex === null) return null;
        $liveSum = 0; $anchorSum = 0;

        foreach (self::BASKET_OFFSETS as $offset) {
            $idx = $atmIndex + $offset;
            $strike = $ladder[$idx] ?? null;
            if ($strike === null) return null;

            $key = $this->strikeKey($strike);
            if (!array_key_exists($key, $liveMap) || !array_key_exists($key, $anchorMap)) return null;

            $liveSum   += $liveMap[$key];
            $anchorSum += $anchorMap[$key];
        }

        if ($anchorSum <= 0) return null;
        return round($liveSum / $anchorSum, 4);
    }

    /** Direction only (no strength tier needed for the score calc here). */
    private function deriveDecaySignal(?float $ce, ?float $pe): string
    {
        if ($ce === null && $pe === null) return 'INSUFFICIENT_DATA';
        if ($ce === null) return $pe <= self::DECAY_THRESHOLD ? 'BEARISH' : 'INSUFFICIENT_DATA';
        if ($pe === null) return $ce <= self::DECAY_THRESHOLD ? 'BULLISH' : 'INSUFFICIENT_DATA';
        if ($ce == $pe) return 'NEUTRAL';
        return $ce < $pe ? 'BULLISH' : 'BEARISH';
    }

    /**
     * Same directional logic as OIFlowSentimentController::calcOISignal(),
     * collapsed to sentiment only (this page doesn't need condition/reason text).
     */
    private function buildOiSentiment(array $liveOi, array $anchorOi): string
    {
        $ceToday = array_sum($liveOi['CE']);
        $peToday = array_sum($liveOi['PE']);
        $cePrev  = array_sum($anchorOi['CE']);
        $pePrev  = array_sum($anchorOi['PE']);

        $cePct = $cePrev > 0 ? (($ceToday - $cePrev) / $cePrev) * 100 : 0;
        $pePct = $pePrev > 0 ? (($peToday - $pePrev) / $pePrev) * 100 : 0;

        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp && $peDown) return 'BULLISH';
        if ($ceDown && $peUp) return 'BEARISH';
        if ($ceUp && $peUp) return $cePct > $pePct ? 'BULLISH' : 'BEARISH';
        if ($ceDown && $peDown) return $cePct < $pePct ? 'BEARISH' : 'BULLISH';
        return 'NEUTRAL';
    }

    private function strikeKey($strike): string
    {
        return number_format((float) $strike, 4, '.', '');
    }

    private function findAtmIndex(array $ladder, ?float $atmStrike): ?int
    {
        if ($atmStrike === null || empty($ladder)) return null;
        foreach ($ladder as $i => $s) {
            if (abs($s - $atmStrike) < 0.01) return $i;
        }
        $closestIdx = null; $closestDiff = null;
        foreach ($ladder as $i => $s) {
            $diff = abs($s - $atmStrike);
            if ($closestDiff === null || $diff < $closestDiff) { $closestDiff = $diff; $closestIdx = $i; }
        }
        return $closestIdx;
    }

    private function emptySlots(): array
    {
        $slots = [];
        foreach (self::ANALYSIS_TIMES as $label => $time) {
            $slots[$label] = [
                'time'  => $time,
                'price' => ['has_data' => false, 'signal' => 'INSUFFICIENT_DATA', 'score' => null, 'reasons' => []],
                'oi'    => ['has_data' => false, 'score' => null, 'decay_signal' => 'INSUFFICIENT_DATA', 'oi_sentiment' => 'INSUFFICIENT_DATA'],
                'final_signal' => 'WAIT',
            ];
        }
        return $slots;
    }

    // ═════════════════════════ SHARED HELPERS ═════════════════════════

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', self::TF)->where('is_active', 1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')
            ->toArray();
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->toDateString())) return $prev->toDateString();
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}