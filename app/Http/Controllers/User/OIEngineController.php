<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OIEngineController — Advanced CE/PE OI Phase + Speed + Trend + Intent Engine
 *
 * NEW LOGIC LAYERS (on top of existing OI signals):
 *   Layer 1: Phase         — What stage is the OI in (Aggressive/Buildup/Unwinding)
 *   Layer 2: Change Speed  — Momentum: Accelerating / Decelerating / Stable
 *   Layer 3: Trend         — Direction consistency over last 2–3 days
 *   Layer 4: Intent        — Single spike (Writing) vs Multi-day (Accumulation) vs Unwinding
 *
 * KEY INSIGHT:
 *   CE OI ↑ consistently for 3+ days → Bullish Accumulation (not just writing)
 *   CE OI sudden spike (single day) → Call Writing → Bearish
 *   CE OI unwinding → Bullish Reversal
 *   PE OI logic is symmetric/mirrored
 *
 * COLUMN USAGE:
 *   trade_date    = DATETIME e.g. "2026-02-02 09:15:00"
 *   interval_time = DATETIME e.g. "2026-02-02 09:15:00"
 *   Always use whereDate() for date comparisons — NOT whereBetween on raw strings
 */
class OIEngineController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'OI Engine — CE/PE Phase + Intent Analysis';
        return view($this->activeTemplate . 'user.oi-engine.index', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  MAIN ANALYSIS ENDPOINT
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data'    => [],
                ]);
            }

            // Get all unique trade dates in range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No trading data found for selected date range',
                    'data'    => [],
                ]);
            }

            $results = [];

            foreach ($tradeDates as $date) {
                $rows = $this->buildEngineRowsForDate($date, $selectedSymbols);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }

            // Sort: newest date first, then symbol
            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records analysed',
            ]);

        } catch (\Exception $e) {
            Log::error('OI Engine Analysis Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR A SINGLE DATE
    // =========================================================

    private function buildEngineRowsForDate(string $date, array $symbolFilter): array
    {
        // Today's FUT candle at 14:45 — drives the active symbol list
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) {
            $futQuery->whereIn('base_symbol', $symbolFilter);
        }

        $futCandles = $futQuery->get()->keyBy('base_symbol');
        if ($futCandles->isEmpty()) return [];

        // We need up to 3 trading days (today + 2 prior) for trend/speed calculation
        $prevDate1 = $this->getPreviousTradingDate($date);
        $prevDate2 = $this->getPreviousTradingDate($prevDate1);

        $rows = [];

        foreach ($futCandles as $symbol => $futCandle) {
            if ((float) $futCandle->close <= 0) continue;

            try {
                // ── Resolve active expiry ─────────────────────────────
                $expiry = $this->getNearestExpiry($symbol, $date);

                // ── CE OI for today, prev1, prev2 at EOD candles ──────
                $ceOiToday = $this->sumOI($symbol, 'CE', $date,      '14:45:00', $expiry);
                $ceOiPrev1 = $this->sumOI($symbol, 'CE', $prevDate1, '15:00:00', $expiry);
                $ceOiPrev2 = $this->sumOI($symbol, 'CE', $prevDate2, '15:00:00', $expiry);

                // ── PE OI for today, prev1, prev2 ─────────────────────
                $peOiToday = $this->sumOI($symbol, 'PE', $date,      '14:45:00', $expiry);
                $peOiPrev1 = $this->sumOI($symbol, 'PE', $prevDate1, '15:00:00', $expiry);
                $peOiPrev2 = $this->sumOI($symbol, 'PE', $prevDate2, '15:00:00', $expiry);

                // Skip if genuinely no option data today
                if ($ceOiToday == 0 && $peOiToday == 0) continue;

                // ── OI Change % (today vs prev1, prev1 vs prev2) ──────
                $cePctToday = $ceOiPrev1 > 0 ? (($ceOiToday - $ceOiPrev1) / $ceOiPrev1) * 100 : 0;
                $cePctPrev1 = $ceOiPrev2 > 0 ? (($ceOiPrev1 - $ceOiPrev2) / $ceOiPrev2) * 100 : 0;

                $pePctToday = $peOiPrev1 > 0 ? (($peOiToday - $peOiPrev1) / $peOiPrev1) * 100 : 0;
                $pePctPrev1 = $peOiPrev2 > 0 ? (($peOiPrev1 - $peOiPrev2) / $peOiPrev2) * 100 : 0;

                // ── Classic OI signal (existing logic kept) ───────────
                $classicSignal = $this->getClassicOISignal($cePctToday, $pePctToday);

                // ── CE Engine ─────────────────────────────────────────
                $ceChanges = array_filter(
                    [$cePctToday, $cePctPrev1],
                    fn($v) => $v !== 0
                );
                // Ensure we pass at least 2 values (use 0 if prev not available)
                $ceChangesArr = [$cePctToday, $cePctPrev1];

                $cePhase       = $this->getPhase($cePctToday);
                $ceSpeed       = $this->getSpeed($cePctToday, $cePctPrev1);
                $ceTrend       = $this->getTrend($cePctToday, $cePctPrev1, null);
                $ceEngineSignal = $this->getEngineSignal($cePhase, $ceSpeed, $ceTrend);

                $ceIntentResult = $this->detectIntent($ceChangesArr);

                // ── PE Engine ─────────────────────────────────────────
                $peChangesArr = [$pePctToday, $pePctPrev1];

                $pePhase       = $this->getPhase($pePctToday);
                $peSpeed       = $this->getSpeed($pePctToday, $pePctPrev1);
                $peTrend       = $this->getTrend($pePctToday, $pePctPrev1, null);
                $peEngineSignal = $this->getEngineSignal($pePhase, $peSpeed, $peTrend);

                $peIntentResult = $this->detectIntent($peChangesArr);

                // ── Combined Engine Signal ────────────────────────────
                $combinedSignal = $this->getCombinedSignal(
                    $ceEngineSignal,
                    $ceIntentResult['signal'],
                    $peEngineSignal,
                    $peIntentResult['signal']
                );

                $rows[] = [
                    'date'   => $date,
                    'symbol' => $symbol,

                    // Classic
                    'ce_oi_pct'      => round($cePctToday, 2),
                    'pe_oi_pct'      => round($pePctToday, 2),
                    'classic_signal' => $classicSignal,

                    // CE Engine
                    'ce_phase'        => $cePhase,
                    'ce_speed'        => $ceSpeed,
                    'ce_trend'        => $ceTrend,
                    'ce_engine'       => $ceEngineSignal,
                    'ce_intent'       => $ceIntentResult['signal'],
                    'ce_consistency'  => $ceIntentResult['consistency'],
                    'ce_pct_prev1'    => round($cePctPrev1, 2),

                    // PE Engine
                    'pe_phase'        => $pePhase,
                    'pe_speed'        => $peSpeed,
                    'pe_trend'        => $peTrend,
                    'pe_engine'       => $peEngineSignal,
                    'pe_intent'       => $peIntentResult['signal'],
                    'pe_consistency'  => $peIntentResult['consistency'],
                    'pe_pct_prev1'    => round($pePctPrev1, 2),

                    // Combined
                    'combined_signal' => $combinedSignal['signal'],
                    'combined_action' => $combinedSignal['action'],
                    'combined_reason' => $combinedSignal['reason'],
                    'combined_confidence' => $combinedSignal['confidence'],

                    // Raw OI
                    'ce_oi'      => $ceOiToday,
                    'pe_oi'      => $peOiToday,
                    'ce_oi_prev' => $ceOiPrev1,
                    'pe_oi_prev' => $peOiPrev1,

                    'spot_price' => round((float) $futCandle->close, 2),
                    'expiry'     => $expiry,
                ];

            } catch (\Exception $e) {
                Log::error("OI Engine row error ({$symbol} {$date}): " . $e->getMessage());
            }
        }

        return $rows;
    }

    // =========================================================
    //  LAYER 1: PHASE
    // =========================================================

    private function getPhase(float $change): string
    {
        if ($change > 50)  return 'AGGRESSIVE_BUILDUP';
        if ($change > 15)  return 'BUILDUP';
        if ($change > 0)   return 'SLOW_BUILDUP';
        if ($change < -20) return 'STRONG_UNWINDING';
        if ($change < 0)   return 'UNWINDING';
        return 'NEUTRAL';
    }

    // =========================================================
    //  LAYER 2: CHANGE SPEED
    // =========================================================

    private function getSpeed(float $today, float $yesterday): string
    {
        if ($today > $yesterday) return 'ACCELERATING';
        if ($today < $yesterday) return 'DECELERATING';
        return 'STABLE';
    }

    // =========================================================
    //  LAYER 3: TREND
    // =========================================================

    private function getTrend(float $today, float $yesterday, ?float $dayBefore): string
    {
        if ($dayBefore === null) {
            if ($today > 0 && $yesterday > 0) return 'BUILDUP_TREND';
            if ($today < 0 && $yesterday < 0) return 'UNWINDING_TREND';
            return 'SIDEWAYS';
        }

        if ($today > 0 && $yesterday > 0 && $dayBefore > 0) return 'STRONG_BUILDUP_TREND';
        if ($today < 0 && $yesterday < 0 && $dayBefore < 0) return 'STRONG_UNWINDING_TREND';
        return 'SIDEWAYS';
    }

    // =========================================================
    //  ENGINE SIGNAL (Phase + Speed + Trend)
    // =========================================================

    private function getEngineSignal(string $phase, string $speed, string $trend): string
    {
        // 🔴 STRONG BEARISH: Aggressive/Buildup + consistent trend
        if (
            in_array($phase, ['AGGRESSIVE_BUILDUP', 'BUILDUP']) &&
            in_array($trend, ['BUILDUP_TREND', 'STRONG_BUILDUP_TREND'])
        ) {
            return 'BEARISH_STRONG';
        }

        // 🔴 WEAK BEARISH: Slow buildup but decelerating (saturation)
        if ($phase === 'SLOW_BUILDUP' && $speed === 'DECELERATING') {
            return 'BEARISH_WEAK';
        }

        // 🟢 STRONG BULLISH: Strong unwinding + consistent trend
        if (
            $phase === 'STRONG_UNWINDING' &&
            in_array($trend, ['UNWINDING_TREND', 'STRONG_UNWINDING_TREND'])
        ) {
            return 'BULLISH_STRONG';
        }

        // 🟢 BULLISH: Any unwinding
        if ($phase === 'UNWINDING') {
            return 'BULLISH';
        }

        // ⚠️ Sideways / confused
        if ($trend === 'SIDEWAYS') {
            return 'WAIT';
        }

        return 'WAIT';
    }

    // =========================================================
    //  INTENT DETECTION (Spike vs Accumulation vs Unwinding)
    // =========================================================

    /**
     * $changes — [today, prev1, prev2, ...] (latest first)
     */
    private function detectIntent(array $changes): array
    {
        $phase       = $this->getPhaseFromChanges($changes);
        $trend       = $this->getTrendFromChanges($changes);
        $consistency = $this->getConsistency($changes);

        $signal = $this->getIntentSignal($phase, $trend, $consistency);

        return [
            'phase'       => $phase,
            'trend'       => $trend,
            'consistency' => $consistency,
            'signal'      => $signal,
        ];
    }

    private function getPhaseFromChanges(array $changes): string
    {
        $latest = $changes[0] ?? 0;
        return $this->getPhase($latest);
    }

    private function getTrendFromChanges(array $changes): string
    {
        $positive = 0;
        $negative = 0;
        foreach ($changes as $ch) {
            if ($ch > 0) $positive++;
            if ($ch < 0) $negative++;
        }

        if ($positive >= 3) return 'STRONG_BUILDUP_TREND';
        if ($negative >= 3) return 'STRONG_UNWINDING_TREND';
        if ($positive > $negative) return 'BUILDUP_TREND';
        if ($negative > $positive) return 'UNWINDING_TREND';
        return 'SIDEWAYS';
    }

    private function getConsistency(array $changes): string
    {
        foreach ($changes as $ch) {
            if ($ch <= 0) return 'INCONSISTENT';
        }
        return 'CONSISTENT_BUILDUP';
    }

    private function getIntentSignal(string $phase, string $trend, string $consistency): string
    {
        // 🔴 CASE 1: Aggressive single spike → Writing (Bearish)
        if ($phase === 'AGGRESSIVE_BUILDUP' && $consistency !== 'CONSISTENT_BUILDUP') {
            return 'BEARISH_STRONG';
        }

        // 🟢 CASE 2: Multi-day consistent buildup → Accumulation (Bullish)
        if (
            in_array($trend, ['STRONG_BUILDUP_TREND', 'BUILDUP_TREND']) &&
            $consistency === 'CONSISTENT_BUILDUP'
        ) {
            return 'BULLISH_ACCUMULATION';
        }

        // 🔴 CASE 3: Normal buildup
        if ($phase === 'BUILDUP') {
            return 'BEARISH';
        }

        // ⚠️ CASE 4: Slow buildup → Saturation zone
        if ($phase === 'SLOW_BUILDUP') {
            return 'BEARISH_WEAK';
        }

        // 🟢 CASE 5: Unwinding
        if ($phase === 'UNWINDING') {
            return 'BULLISH';
        }

        // 🟢 CASE 6: Strong unwinding
        if ($phase === 'STRONG_UNWINDING') {
            return 'BULLISH_STRONG';
        }

        return 'WAIT';
    }

    // =========================================================
    //  COMBINED SIGNAL (CE engine + PE engine)
    // =========================================================

    private function getCombinedSignal(
        string $ceEngine,
        string $ceIntent,
        string $peEngine,
        string $peIntent
    ): array {
        $bullish = ['BULLISH', 'BULLISH_STRONG', 'BULLISH_ACCUMULATION'];
        $bearish = ['BEARISH', 'BEARISH_STRONG', 'BEARISH_WEAK'];

        $ceBull = in_array($ceEngine, $bullish) || in_array($ceIntent, $bullish);
        $ceBear = in_array($ceEngine, $bearish) || in_array($ceIntent, $bearish);
        $peBull = in_array($peEngine, $bullish) || in_array($peIntent, $bullish);
        $peBear = in_array($peEngine, $bearish) || in_array($peIntent, $bearish);

        // Highest confidence: both CE bullish + PE bullish (PE buildup = market expecting rise)
        if ($ceBull && $peBull) {
            return [
                'signal'     => 'BULLISH',
                'action'     => 'BUY CE',
                'reason'     => 'CE unwinding + PE accumulation (dual confirmation)',
                'confidence' => 'HIGH',
            ];
        }

        // Highest confidence: CE bearish + PE bearish
        if ($ceBear && $peBear) {
            return [
                'signal'     => 'BEARISH',
                'action'     => 'BUY PE',
                'reason'     => 'CE writing/buildup + PE unwinding (dual confirmation)',
                'confidence' => 'HIGH',
            ];
        }

        // CE bullish alone
        if ($ceBull && !$peBull) {
            return [
                'signal'     => 'BULLISH',
                'action'     => 'BUY CE',
                'reason'     => 'CE OI unwinding / accumulation',
                'confidence' => 'MEDIUM',
            ];
        }

        // PE bullish alone
        if ($peBull && !$ceBull) {
            return [
                'signal'     => 'BULLISH',
                'action'     => 'BUY CE',
                'reason'     => 'PE OI buildup (Put accumulation = bullish)',
                'confidence' => 'MEDIUM',
            ];
        }

        // CE bearish alone
        if ($ceBear && !$peBear) {
            return [
                'signal'     => 'BEARISH',
                'action'     => 'BUY PE',
                'reason'     => 'CE OI writing / buildup',
                'confidence' => 'LOW',
            ];
        }

        // PE bearish alone
        if ($peBear && !$ceBull) {
            return [
                'signal'     => 'BEARISH',
                'action'     => 'BUY PE',
                'reason'     => 'PE OI unwinding (Put exit = bearish)',
                'confidence' => 'LOW',
            ];
        }

        return [
            'signal'     => 'NEUTRAL',
            'action'     => 'WAIT',
            'reason'     => 'Mixed or no clear signal',
            'confidence' => 'NONE',
        ];
    }

    // =========================================================
    //  CLASSIC OI SIGNAL (existing logic, kept as reference)
    // =========================================================

    private function getClassicOISignal(float $cePct, float $pePct): string
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp && $peDown)   return 'BEARISH';
        if ($ceDown && $peUp)   return 'BULLISH';
        if ($ceUp && $peUp)     return $cePct > $pePct ? 'BEARISH' : 'BULLISH';
        if ($ceDown && $peDown) return $cePct < $pePct ? 'BULLISH' : 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  OI FETCH HELPER
    // =========================================================

    private function sumOI(
        string  $symbol,
        string  $type,
        string  $date,
        string  $time,
        ?string $expiry
    ): int {
        $q = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = ?", [$time]);

        if ($expiry) {
            $q->whereDate('expiry_date', $expiry);
        }

        return (int) $q->sum('oi');
    }

    // =========================================================
    //  EXPIRY HELPER
    // =========================================================

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) {
                return $prev->format('Y-m-d');
            }
            $prev->subDay();
            $attempts++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}