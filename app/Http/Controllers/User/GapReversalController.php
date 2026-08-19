<?php
// FILE: app/Http/Controllers/User/GapReversalController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Gap Reversal Strategy Analyzer — 15min only (internal — not shown to users)
 *
 * Decision is made EARLY at 10:30, not end-of-day. Exact sequence (client spec):
 *
 *   BUY (Gap Down → Buy CE):
 *     Prev day OI baseline
 *       → 09:15 GAP DOWN
 *       → 09:15–09:45 Initial sell-off + OI builds
 *       → 09:45 Low established
 *       → 09:45–10:15 Price stops falling → Higher Low, OI starts falling
 *       → 10:15–10:30 Short covering (OI confirms unwind)
 *       → 10:30 Range breakout → 🟢 BUY
 *
 *   SELL (Gap Up → Buy PE) — mirror:
 *     Prev day OI baseline
 *       → 09:15 GAP UP
 *       → 09:15–09:45 Initial rally + OI builds
 *       → 09:45 High established
 *       → 09:45–10:15 Price stops rising → Lower High, OI starts falling
 *       → 10:15–10:30 Long unwinding (OI confirms unwind)
 *       → 10:30 Range breakdown → 🔴 SELL
 *
 * Score (out of 100):
 *   Gap (Down/Up)          +20
 *   Initial Move           +10
 *   Reversal (HL / LH)     +20
 *   OI Confirmation        +20
 *   Option Reversal        +15   (short covering / long unwinding)
 *   Range Break(out/down)  +10
 *   Volume                 +5
 *
 * A BUY/SELL signal only fires when ALL SIX mandatory conditions are true
 * (Volume is a bonus point only, not mandatory — matches the client spec
 * where the AND-list has six items and Volume appears only in the score).
 *
 * Pivots (informational only — NOT part of the 100pt score) are shown as
 * FOUR separate levels: R2, R1, S1, S2 (classic floor-pivot formula from
 * the previous day's H/L/C), each flagged individually if today's price
 * touched it, and tagged with which side (CE/PE) it's relevant to:
 *   BUY  → S1/S2 tagged CE (support test), R1/R2 tagged PE
 *   SELL → S1/S2 tagged PE (support test), R1/R2 tagged CE
 */
class GapReversalController extends Controller
{
    private const TF        = '15min';
    private const OPT_TABLE = 'cp_option_ohlc_15min';

    // ── Session windows (candle interval_time boundaries) ──────────────────
    // Retimed per client sequence — decision now happens at 10:30, not EOD.
    private const MARKET_OPEN   = '09:15:00'; // open candle — gap + OI baseline reference
    private const INITIAL_END   = '09:45:00'; // end of Initial Sell-off/Rally window → "Low/High established"
    private const REVERSAL_END  = '10:15:00'; // end of Higher-Low/Lower-High window → OI starts falling; also end of range-building window
    private const ANALYSIS_TIME = '10:30:00'; // DECISION CANDLE — short covering/long unwinding + range breakout checked here
    private const PREV_DAY_TIME = '15:00:00'; // previous day close / OI baseline reference (last candle of prior session)

    // ── Tunable thresholds (%) — adjust to taste without touching logic ────
    private const GAP_MIN_PCT     = 0.10; // min gap vs prev close to qualify as gap up/down
    private const INITIAL_MIN_PCT = 0.05; // min push in initial window vs open
    private const REVERSAL_BUFFER = 0.02; // buffer for higher-low / lower-high confirmation
    private const BREAK_MIN_PCT   = 0.05; // min push beyond range to count as breakout/breakdown
    private const PIVOT_TOUCH_TOL = 0.15; // % tolerance to consider a pivot level "touched"

    // ── Score weights (client spec, sums to 100) ────────────────────────────
    private const W_GAP        = 20;
    private const W_INITIAL    = 10;
    private const W_REVERSAL   = 20;
    private const W_OI_CONFIRM = 20;
    private const W_OPT_REV    = 15;
    private const W_RANGE      = 10;
    private const W_VOLUME     = 5;

    private static ?bool $hasVolumeColumn = null;

    public function index()
    {
        $pageTitle = 'Gap Reversal Strategy Analyzer';
        return view(activeTemplate() . 'user.gap-reversal.index', compact('pageTitle'));
    }

    // ── Last Available Date ──────────────────────────────────────────────────

    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true,
                ]);
            }

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->max('trade_date');

            if (!$lastDate) {
                $lastDate = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $config->id)
                    ->where('is_missing', false)
                    ->max('trade_date');
            }

            $today    = Carbon::today()->toDateString();
            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

            return response()->json([
                'success' => true, 'last_date' => $lastDate, 'is_today' => $lastDate === $today,
            ]);
        } catch (\Exception $e) {
            Log::error('GapReversal lastDate: ' . $e->getMessage());
            return response()->json([
                'success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true,
            ]);
        }
    }

    // ── Symbols API ───────────────────────────────────────────────────────────

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json([
                'success' => true, 'symbols' => [], 'no_config' => true,
                'message' => 'No active analysis config found.',
            ]);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    // ── Analyze API ───────────────────────────────────────────────────────────
    //   Accepts a single `date` param + optional `symbols[]` + `filter_setup` (BUY|SELL).

    public function analyze(Request $request): JsonResponse
    {
        try {
            $date        = $request->get('date');
            $symbolReq   = array_filter((array) $request->get('symbols', []));
            $setupFilter = $request->get('filter_setup', ''); // BUY | SELL | ''

            if (!$date) {
                return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success' => false, 'no_config' => true,
                    'message' => 'No active Analysis Config found. Go to Admin → Analysis Config.',
                    'data' => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols  = !empty($symbolReq) ? array_values(array_intersect($symbolReq, $configSymbols)) : $configSymbols;
            $prevDate = $this->getPreviousTradingDate($date);

            // Today's full-day series (ATM CE/PE rows carry the underlying future_price)
            $todaySeries = $this->fetchDaySeries($config->id, $symbols, $date);
            // Previous day's full-day series (prev close / prev H-L / pivots / prev OI trend)
            $prevSeries  = $this->fetchDaySeries($config->id, $symbols, $prevDate);

            if (empty($todaySeries)) {
                return response()->json([
                    'success' => true, 'data' => [], 'date' => $date,
                    'is_today' => $date === Carbon::today()->toDateString(),
                    'available_symbols' => $configSymbols,
                    'message' => 'No data found for the selected date.',
                ]);
            }

            $results = [];
            foreach ($symbols as $symbol) {
                if (empty($todaySeries[$symbol])) continue;
                $setup = $this->buildSetup($symbol, $date, $todaySeries[$symbol], $prevSeries[$symbol] ?? []);
                if (!$setup) continue;
                if ($setupFilter && $setup['setup'] !== $setupFilter) continue;
                $results[] = $setup;
            }

            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'buy_count'         => count(array_filter($results, fn($r) => $r['setup'] === 'BUY')),
                'sell_count'        => count(array_filter($results, fn($r) => $r['setup'] === 'SELL')),
                'wait_count'        => count(array_filter($results, fn($r) => $r['setup'] === 'WAIT')),
                'message'           => count($results) . ' symbol(s) analyzed for ' . $date,
                'available_symbols' => $configSymbols,
                'date'              => $date,
                'is_today'          => $date === Carbon::today()->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error('GapReversal analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Core setup builder ──────────────────────────────────────────────────

    private function buildSetup(string $symbol, string $date, array $today, array $prev): ?array
    {
        if (empty($today['candles'])) return null;

        $candles = $today['candles']; // interval_time => ['price','ce_oi','pe_oi','volume']
        ksort($candles);
        $times = array_keys($candles);
        if (empty($times)) return null;

        $todayOpen = $candles[$times[0]]['price'] ?? null;

        // Previous day close / high / low (for gap + pivots)
        $prevClose = $this->lastPrice($prev, self::PREV_DAY_TIME) ?? $this->lastPrice($prev, null);
        $prevHigh  = $this->extremePrice($prev['candles'] ?? [], 'max');
        $prevLow   = $this->extremePrice($prev['candles'] ?? [], 'min');
        if (!$prevClose || !$todayOpen) return null;

        // ── 1. GAP ───────────────────────────────────────────────────────────
        $gapPct  = round((($todayOpen - $prevClose) / $prevClose) * 100, 2);
        $gapDown = $gapPct <= -self::GAP_MIN_PCT;
        $gapUp   = $gapPct >=  self::GAP_MIN_PCT;
        if (!$gapDown && !$gapUp) {
            return $this->waitResult($symbol, $date, $gapPct, 'No qualifying gap');
        }
        $bias = $gapDown ? 'BUY' : 'SELL'; // BUY = gap-down reversal (long/CE), SELL = gap-up reversal (short/PE)

        // ── 2. INITIAL SELL-OFF / RALLY (09:15 → 09:45) → Low/High established ─
        $initialWindow  = $this->sliceWindow($candles, self::MARKET_OPEN, self::INITIAL_END);
        $initialExtreme = $bias === 'BUY'
            ? $this->extremePrice($initialWindow, 'min')
            : $this->extremePrice($initialWindow, 'max');

        $initialMovePct = $initialExtreme !== null
            ? round((($initialExtreme - $todayOpen) / $todayOpen) * 100, 2) : 0;

        $initialOk = $bias === 'BUY'
            ? ($initialExtreme !== null && $initialMovePct <= -self::INITIAL_MIN_PCT)
            : ($initialExtreme !== null && $initialMovePct >=  self::INITIAL_MIN_PCT);

        // ── 3. HIGHER LOW / LOWER HIGH (09:45 → 10:15) — price stops falling/rising ─
        $reversalWindow  = $this->sliceWindow($candles, self::INITIAL_END, self::REVERSAL_END);
        $reversalExtreme = $bias === 'BUY'
            ? $this->extremePrice($reversalWindow, 'min')
            : $this->extremePrice($reversalWindow, 'max');

        $bufferAmt  = $todayOpen * (self::REVERSAL_BUFFER / 100);
        $reversalOk = $bias === 'BUY'
            ? ($reversalExtreme !== null && $initialExtreme !== null && $reversalExtreme > ($initialExtreme + $bufferAmt))
            : ($reversalExtreme !== null && $initialExtreme !== null && $reversalExtreme < ($initialExtreme - $bufferAmt));

        // ── 4. RANGE (09:15 → 10:15) + BREAKOUT/BREAKDOWN (10:15 → 10:30) ──
        //      Range forms across the initial + reversal windows; the final
        //      10:15→10:30 candle(s) confirm the break — this is the decision step.
        $rangeWindow = $this->sliceWindow($candles, self::MARKET_OPEN, self::REVERSAL_END);
        $rangeHigh   = $this->extremePrice($rangeWindow, 'max');
        $rangeLow    = $this->extremePrice($rangeWindow, 'min');

        $breakoutWindow = $this->sliceWindow($candles, self::REVERSAL_END, self::ANALYSIS_TIME);
        $breakoutHigh   = $this->extremePrice($breakoutWindow, 'max');
        $breakoutLow    = $this->extremePrice($breakoutWindow, 'min');

        $breakMinAmt  = $todayOpen * (self::BREAK_MIN_PCT / 100);
        $rangeBreakOk = $bias === 'BUY'
            ? ($breakoutHigh !== null && $rangeHigh !== null && $breakoutHigh > ($rangeHigh + $breakMinAmt))
            : ($breakoutLow  !== null && $rangeLow  !== null && $breakoutLow  < ($rangeLow  - $breakMinAmt));

        // ── 5. CE/PE OI CONFIRMATION (today @10:30 decision vs prev day @15:00) ─
        $ceToday = $this->latestOi($candles, 'ce_oi', self::ANALYSIS_TIME);
        $peToday = $this->latestOi($candles, 'pe_oi', self::ANALYSIS_TIME);
        $cePrev  = $this->latestOi($prev['candles'] ?? [], 'ce_oi', self::PREV_DAY_TIME);
        $pePrev  = $this->latestOi($prev['candles'] ?? [], 'pe_oi', self::PREV_DAY_TIME);

        $cePct = $cePrev > 0 ? round((($ceToday - $cePrev) / $cePrev) * 100, 2) : 0;
        $pePct = $pePrev > 0 ? round((($peToday - $pePrev) / $pePrev) * 100, 2) : 0;

        // BUY expects a bullish OI signature (CE unwinding / PE building), same
        // family of logic as the OI Flow Sentiment analyzer's BULLISH/BEARISH check.
        $oiSentiment = $this->oiSentiment($cePct, $pePct);
        $oiConfirmOk = $bias === 'BUY' ? $oiSentiment === 'BULLISH' : $oiSentiment === 'BEARISH';

        // Previous-day buildup context ("identify any previous long buildup or
        // buildup after gap down") — shown for trader context, not scored directly.
        $prevOpenCe  = $this->earliestOi($prev['candles'] ?? [], 'ce_oi');
        $prevOpenPe  = $this->earliestOi($prev['candles'] ?? [], 'pe_oi');
        $prevTrendCe = $this->buildupTag($prevOpenCe, $cePrev);
        $prevTrendPe = $this->buildupTag($prevOpenPe, $pePrev);

        // ── 6. OPTION REVERSAL — "Short Covering" (BUY) / "Long Unwinding" (SELL) ─
        //      Phase 1 (09:15→09:45): OI builds in the gap direction.
        //      Phase 2 (09:45→10:30): OI unwinds — "starts falling" through 10:15,
        //      confirmed as short covering / long unwinding by the 10:30 decision candle.
        $ceMid   = $this->latestOi($candles, 'ce_oi', self::INITIAL_END);
        $peMid   = $this->latestOi($candles, 'pe_oi', self::INITIAL_END);
        $ceOpenT = $this->earliestOi($candles, 'ce_oi');
        $peOpenT = $this->earliestOi($candles, 'pe_oi');

        $ceFirstHalfPct  = $ceOpenT > 0 ? (($ceMid - $ceOpenT) / $ceOpenT) * 100 : 0;
        $ceSecondHalfPct = $ceMid   > 0 ? (($ceToday - $ceMid) / $ceMid) * 100 : 0;
        $peFirstHalfPct  = $peOpenT > 0 ? (($peMid - $peOpenT) / $peOpenT) * 100 : 0;
        $peSecondHalfPct = $peMid   > 0 ? (($peToday - $peMid) / $peMid) * 100 : 0;

        // BUY: CE trend flips from building(+) early to unwinding(-) later (short
        //      covering), or PE trend flips from unwinding(-) early to building(+) later.
        // SELL is the mirror image (long unwinding).
        $optionReversalOk = $bias === 'BUY'
            ? (($ceFirstHalfPct > 0 && $ceSecondHalfPct < 0) || ($peFirstHalfPct < 0 && $peSecondHalfPct > 0))
            : (($peFirstHalfPct > 0 && $peSecondHalfPct < 0) || ($ceFirstHalfPct < 0 && $ceSecondHalfPct > 0));

        // ── 7. VOLUME (bonus, only if the column exists) ────────────────────
        $volumeAvailable = $this->hasVolume();
        $volumeOk = false;
        if ($volumeAvailable) {
            $todayFull = $this->sliceWindow($candles, self::MARKET_OPEN, self::ANALYSIS_TIME);
            $todayVol  = array_sum(array_column($todayFull, 'volume'));
            $prevVol   = array_sum(array_column($prev['candles'] ?? [], 'volume'));
            $volumeOk  = $prevVol > 0 && $todayVol > $prevVol; // above-prev-day-average check
        }

        // ── Score ─────────────────────────────────────────────────────────────
        $score  = 0;
        $score += ($gapDown || $gapUp) ? self::W_GAP : 0;
        $score += $initialOk ? self::W_INITIAL : 0;
        $score += $reversalOk ? self::W_REVERSAL : 0;
        $score += $oiConfirmOk ? self::W_OI_CONFIRM : 0;
        $score += $optionReversalOk ? self::W_OPT_REV : 0;
        $score += $rangeBreakOk ? self::W_RANGE : 0;
        $score += $volumeOk ? self::W_VOLUME : 0;

        $mandatoryOk = ($gapDown || $gapUp) && $initialOk && $reversalOk && $oiConfirmOk && $optionReversalOk && $rangeBreakOk;
        $setup = $mandatoryOk ? $bias : 'WAIT';

        // ── Pivots (previous day H/L/C, classic floor pivots) — kept as 4 SEPARATE
        //    levels (R2, R1, S1, S2), each individually flagged if today's price
        //    touched it, and tagged with which side (CE/PE) it's relevant to.
        $pivots = ($prevHigh && $prevLow && $prevClose) ? $this->calcPivots($prevHigh, $prevLow, $prevClose) : null;
        $dayLow  = $this->extremePrice($candles, 'min');
        $dayHigh = $this->extremePrice($candles, 'max');
        $pivotNote = null;
        if ($pivots) {
            $tol = self::PIVOT_TOUCH_TOL / 100;
            $touchedS1 = $dayLow  !== null && $dayLow  <= $pivots['s1'] * (1 + $tol);
            $touchedS2 = $dayLow  !== null && $dayLow  <= $pivots['s2'] * (1 + $tol);
            $touchedR1 = $dayHigh !== null && $dayHigh >= $pivots['r1'] * (1 - $tol);
            $touchedR2 = $dayHigh !== null && $dayHigh >= $pivots['r2'] * (1 - $tol);

            // BUY  → S1/S2 relevant to CE (support test), R1/R2 relevant to PE
            // SELL → S1/S2 relevant to PE (support test), R1/R2 relevant to CE
            $supportSide    = $bias === 'BUY' ? 'CE' : 'PE';
            $resistanceSide = $bias === 'BUY' ? 'PE' : 'CE';
            $pivotNote = [
                'r2' => ['touched' => $touchedR2, 'side' => $resistanceSide],
                'r1' => ['touched' => $touchedR1, 'side' => $resistanceSide],
                's1' => ['touched' => $touchedS1, 'side' => $supportSide],
                's2' => ['touched' => $touchedS2, 'side' => $supportSide],
            ];
        }

        $atmRow = $today['atm'] ?? null;

        return [
            'date'               => $date,
            'symbol'             => $symbol,
            'setup'              => $setup,   // BUY | SELL | WAIT
            'bias'               => $bias,    // which side the gap suggests
            'score'              => $score,
            'gap_pct'            => $gapPct,
            'gap_type'           => $gapDown ? 'GAP DOWN' : ($gapUp ? 'GAP UP' : 'FLAT'),
            'initial_move_pct'   => $initialMovePct,
            'initial_ok'         => $initialOk,
            'reversal_ok'        => $reversalOk,
            'reversal_type'      => $bias === 'BUY' ? 'HIGHER LOW' : 'LOWER HIGH',
            'range_high'         => $rangeHigh ? round($rangeHigh, 2) : null,
            'range_low'          => $rangeLow ? round($rangeLow, 2) : null,
            'range_break_ok'     => $rangeBreakOk,
            'range_break_type'   => $bias === 'BUY' ? 'RANGE BREAKOUT' : 'RANGE BREAKDOWN',
            'ce_oi'              => $ceToday,
            'pe_oi'              => $peToday,
            'ce_oi_pct'          => $cePct,
            'pe_oi_pct'          => $pePct,
            'oi_sentiment'       => $oiSentiment,
            'oi_confirm_ok'      => $oiConfirmOk,
            'prev_ce_trend'      => $prevTrendCe,
            'prev_pe_trend'      => $prevTrendPe,
            'option_reversal_ok' => $optionReversalOk,
            'volume_ok'          => $volumeOk,
            'volume_available'   => $volumeAvailable,
            'pivots'             => $pivots ? array_map(fn($v) => round($v, 2), $pivots) : null,
            'pivot_note'         => $pivotNote,
            'atm_strike'         => $atmRow->atm_strike ?? null,
            'fut_price'          => round($candles[end($times)]['price'], 2),
            'expiry'             => $atmRow ? substr($atmRow->expiry_date, 0, 10) : null,
            'reason'             => $this->buildReason($bias, $setup, $gapPct, $initialOk, $reversalOk, $oiConfirmOk, $optionReversalOk, $rangeBreakOk),
        ];
    }

    private function waitResult(string $symbol, string $date, float $gapPct, string $reason): array
    {
        return [
            'date' => $date, 'symbol' => $symbol, 'setup' => 'WAIT', 'bias' => null, 'score' => 0,
            'gap_pct' => $gapPct, 'gap_type' => 'FLAT', 'initial_move_pct' => 0, 'initial_ok' => false,
            'reversal_ok' => false, 'reversal_type' => '-', 'range_high' => null, 'range_low' => null,
            'range_break_ok' => false, 'range_break_type' => '-', 'ce_oi' => 0, 'pe_oi' => 0,
            'ce_oi_pct' => 0, 'pe_oi_pct' => 0, 'oi_sentiment' => 'NEUTRAL', 'oi_confirm_ok' => false,
            'prev_ce_trend' => '-', 'prev_pe_trend' => '-', 'option_reversal_ok' => false,
            'volume_ok' => false, 'volume_available' => $this->hasVolume(), 'pivots' => null,
            'pivot_note' => null, 'atm_strike' => null, 'fut_price' => null, 'expiry' => null,
            'reason' => $reason,
        ];
    }

    private function buildReason(string $bias, string $setup, float $gapPct, bool $i, bool $r, bool $o, bool $opt, bool $rb): string
    {
        if ($setup === 'WAIT') {
            $missing = [];
            if (!$i)   $missing[] = $bias === 'BUY' ? 'initial sell-off' : 'initial rally';
            if (!$r)   $missing[] = $bias === 'BUY' ? 'higher low' : 'lower high';
            if (!$o)   $missing[] = 'OI confirmation';
            if (!$opt) $missing[] = 'option reversal';
            if (!$rb)  $missing[] = $bias === 'BUY' ? 'range breakout' : 'range breakdown';
            return 'Setup incomplete — missing: ' . implode(', ', $missing);
        }
        return $setup === 'BUY'
            ? "Gap down ({$gapPct}%) reversed with higher low, OI + option flow confirming, range broke out → BUY CE"
            : "Gap up ({$gapPct}%) reversed with lower high, OI + option flow confirming, range broke down → BUY PE";
    }

    // ── OI helpers ────────────────────────────────────────────────────────────

    private function oiSentiment(float $cePct, float $pePct): string
    {
        // Reversed per client feedback: CE up + PE down was showing as BEARISH
        // (BUY PE) but actually plays out as bullish in practice. Swapped.
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;
        if ($ceUp && $peDown) return 'BULLISH';
        if ($ceDown && $peUp) return 'BEARISH';
        if ($ceUp && $peUp)   return $cePct > $pePct ? 'BULLISH' : 'BEARISH';
        if ($ceDown && $peDown) return $cePct < $pePct ? 'BEARISH' : 'BULLISH';
        return 'NEUTRAL';
    }

    private function buildupTag(?float $openOi, ?float $closeOi): string
    {
        if (!$openOi || !$closeOi || $openOi <= 0) return '-';
        $chg = (($closeOi - $openOi) / $openOi) * 100;
        return $chg > 1 ? 'Long Buildup' : ($chg < -1 ? 'Unwinding' : 'Flat');
    }

    // ── Series helpers ───────────────────────────────────────────────────────

    /** Fetch base_symbol => ['candles' => [HH:MM:SS => [price, ce_oi, pe_oi, volume?]], 'atm' => row] for one date */
    private function fetchDaySeries(int $configId, array $symbols, string $date): array
    {
        $withVolume = $this->hasVolume();
        $cols = ['base_symbol', 'instrument_type', 'interval_time', 'oi', 'future_price', 'atm_strike', 'expiry_date', 'strike_position'];
        if ($withVolume) $cols[] = 'volume';

        $rows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('strike_position', 'ATM')
            ->where('is_missing', false)
            ->select($cols)
            ->orderBy('interval_time')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $t = substr($r->interval_time, -8); // HH:MM:SS
            $out[$r->base_symbol]['candles'][$t]['price'] = (float) $r->future_price;
            if ($r->instrument_type === 'CE') {
                $out[$r->base_symbol]['candles'][$t]['ce_oi'] = (int) $r->oi;
                $out[$r->base_symbol]['atm'] = $r;
            } else {
                $out[$r->base_symbol]['candles'][$t]['pe_oi'] = (int) $r->oi;
            }
            if ($withVolume) {
                $out[$r->base_symbol]['candles'][$t]['volume'] = (float) ($r->volume ?? 0);
            }
        }
        return $out;
    }

    private function sliceWindow(array $candles, string $from, string $to): array
    {
        return array_filter($candles, fn($k) => $k >= $from && $k <= $to, ARRAY_FILTER_USE_KEY);
    }

    private function extremePrice(array $candles, string $type): ?float
    {
        $prices = array_column($candles, 'price');
        if (empty($prices)) return null;
        return $type === 'max' ? max($prices) : min($prices);
    }

    private function lastPrice(array $day, ?string $atTime): ?float
    {
        $candles = $day['candles'] ?? [];
        if (empty($candles)) return null;
        if ($atTime && isset($candles[$atTime])) return $candles[$atTime]['price'];
        ksort($candles);
        $last = end($candles);
        return $last['price'] ?? null;
    }

    private function latestOi(array $candles, string $field, string $upToTime): int
    {
        $best = null; $bestTime = null;
        foreach ($candles as $t => $c) {
            if ($t <= $upToTime && isset($c[$field]) && ($bestTime === null || $t > $bestTime)) {
                $best = $c[$field]; $bestTime = $t;
            }
        }
        return (int) ($best ?? 0);
    }

    private function earliestOi(array $candles, string $field): int
    {
        ksort($candles);
        foreach ($candles as $c) {
            if (isset($c[$field])) return (int) $c[$field];
        }
        return 0;
    }

    private function calcPivots(float $high, float $low, float $close): array
    {
        $pp = ($high + $low + $close) / 3;
        return [
            'pp' => $pp,
            'r1' => 2 * $pp - $low,
            'r2' => $pp + ($high - $low),
            's1' => 2 * $pp - $high,
            's2' => $pp - ($high - $low),
        ];
    }

    private function hasVolume(): bool
    {
        if (self::$hasVolumeColumn === null) {
            try {
                self::$hasVolumeColumn = Schema::hasColumn(self::OPT_TABLE, 'volume');
            } catch (\Exception $e) {
                self::$hasVolumeColumn = false;
            }
        }
        return self::$hasVolumeColumn;
    }

    // ── Config helpers (same convention as OIFlowSentimentController) ──────

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', self::TF)
            ->where('is_active', 1)
            ->first();
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