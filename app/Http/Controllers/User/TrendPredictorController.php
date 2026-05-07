<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * TrendPredictorController
 *
 * Uses PREVIOUS day's 1-min data (from nifty_option_1min_ohlc) to predict
 * NEXT day's trend: BULLISH / BEARISH / SIDEWAYS
 *
 * Signal Sources (all from prev day):
 *   1. Close Position      → (Close - Low) / (High - Low) last candle
 *   2. Last 1-Hour Price   → direction of FUT in final 60 min (14:30–15:29)
 *   3. Last 1-Hour OI      → OI vs price divergence/confirmation
 *   4. ATM CE/PE OI        → who built more OI in last 1 hour
 *
 * Final Signal: BULLISH / BEARISH / SIDEWAYS + BUY or SELL or NO TRADE
 */
class TrendPredictorController extends Controller
{
    // Last-hour window: 14:30 → 15:29
    private const LAST_HOUR_START = '14:30:00';
    private const LAST_HOUR_END   = '15:29:00';

    // ── Page ──────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Next Day Trend Predictor';
        return view($this->activeTemplate . 'user.trend-predictor.index', compact('pageTitle'));
    }

    // ── API: predict ──────────────────────────────────────────────────────

    public function predict(Request $request)
    {
        try {
            $baseDate   = $request->get('date', date('Y-m-d'));   // "predict for this date"
            $symbol     = $request->get('symbol', 'NIFTY');

            // prev trading day = the day BEFORE $baseDate
            $prevDate = $this->getPrevTradingDate($baseDate);

            if (!$prevDate) {
                return response()->json(['success' => false, 'message' => 'No previous trading day found.']);
            }

            // ── 1. Fetch full-day FUT 1-min candles for prev day ──────────
            $futCandles = DB::table('nifty_option_1min_ohlc')
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->where('is_missing', 0)
                ->whereDate('trade_date', $prevDate)
                ->orderBy('interval_time')
                ->get(['interval_time', 'open', 'high', 'low', 'close', 'volume', 'oi']);

            if ($futCandles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No FUT data found for {$symbol} on {$prevDate}. Please collect data first.",
                ]);
            }

            // ── 2. Full-day OHLC ──────────────────────────────────────────
            $dayOpen  = (float) $futCandles->first()->open;
            $dayClose = (float) $futCandles->last()->close;
            $dayHigh  = (float) $futCandles->max('high');
            $dayLow   = (float) $futCandles->min('low');

            // ── 3. Signal A — Close Position (0–1 scale) ──────────────────
            $closePosition = $dayHigh > $dayLow
                ? round(($dayClose - $dayLow) / ($dayHigh - $dayLow), 3)
                : 0.5;

            $signalA = match(true) {
                $closePosition >= 0.70 => 'BULLISH',
                $closePosition <= 0.30 => 'BEARISH',
                default                => 'NEUTRAL',
            };

            // ── 4. Signal B — Last 1-hour price direction ─────────────────
            $lastHour = $futCandles->filter(fn($c) =>
                substr($c->interval_time, 11, 8) >= self::LAST_HOUR_START &&
                substr($c->interval_time, 11, 8) <= self::LAST_HOUR_END
            )->values();

            $lastHourOpen  = $lastHour->isNotEmpty() ? (float) $lastHour->first()->open  : 0;
            $lastHourClose = $lastHour->isNotEmpty() ? (float) $lastHour->last()->close  : 0;
            $lastHourDiff  = $lastHourClose - $lastHourOpen;

            $signalB = match(true) {
                $lastHourDiff >  5  => 'BULLISH',
                $lastHourDiff < -5  => 'BEARISH',
                default             => 'NEUTRAL',
            };

            // ── 5. Signal C — OI trend in last 1 hour ─────────────────────
            // Get ATM expiry for prev day options
            $expiry = DB::table('nifty_option_1min_ohlc')
                ->where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $prevDate)
                ->whereNotNull('expiry_date')
                ->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));

            $oiQuery = DB::table('nifty_option_1min_ohlc')
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $prevDate)
                ->where('is_missing', 0)
                ->whereNotNull('expiry_date');

            if ($expiry) $oiQuery->whereDate('expiry_date', $expiry);

            $lastHourOI = $oiQuery->clone()
                ->whereRaw("TIME(interval_time) >= ?", [self::LAST_HOUR_START])
                ->whereRaw("TIME(interval_time) <= ?", [self::LAST_HOUR_END])
                ->selectRaw("instrument_type, interval_time, SUM(oi) as total_oi")
                ->groupBy('instrument_type', 'interval_time')
                ->orderBy('interval_time')
                ->get();

            // OI at start vs end of last hour per type
            $ceOiStart = (int) $lastHourOI->where('instrument_type', 'CE')->first()?->total_oi ?? 0;
            $ceOiEnd   = (int) $lastHourOI->where('instrument_type', 'CE')->last()?->total_oi  ?? 0;
            $peOiStart = (int) $lastHourOI->where('instrument_type', 'PE')->first()?->total_oi ?? 0;
            $peOiEnd   = (int) $lastHourOI->where('instrument_type', 'PE')->last()?->total_oi  ?? 0;

            $ceOiChange = $ceOiEnd - $ceOiStart;   // positive = CE OI building
            $peOiChange = $peOiEnd - $peOiStart;   // positive = PE OI building

            // OI + Price logic:
            // Price ↑ + CE OI ↑ → long buildup → BULLISH
            // Price ↓ + PE OI ↑ → short buildup → BEARISH
            // Price ↑ + CE OI ↓ → short covering only (weak)
            $signalC = 'NEUTRAL';
            if ($lastHourDiff > 0 && $ceOiChange > 0) $signalC = 'BULLISH';
            elseif ($lastHourDiff < 0 && $peOiChange > 0) $signalC = 'BEARISH';
            elseif ($ceOiChange > $peOiChange && $ceOiChange > 0) $signalC = 'BEARISH'; // CE OI build = ceiling = bear
            elseif ($peOiChange > $ceOiChange && $peOiChange > 0) $signalC = 'BULLISH'; // PE OI build = floor = bull

            // ── 6. Signal D — ATM CE vs PE last 1-hour OI winner ─────────
            $atmOI = DB::table('nifty_option_1min_ohlc')
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $prevDate)
                ->where('strike_position', 'ATM')
                ->where('is_missing', 0)
                ->whereRaw("TIME(interval_time) >= ?", [self::LAST_HOUR_START])
                ->selectRaw("instrument_type, SUM(oi) as total_oi")
                ->groupBy('instrument_type')
                ->pluck('total_oi', 'instrument_type');

            $atmCeOI = (int) ($atmOI['CE'] ?? 0);
            $atmPeOI = (int) ($atmOI['PE'] ?? 0);

            // High ATM CE OI = resistance (BEARISH); High ATM PE OI = support (BULLISH)
            $signalD = 'NEUTRAL';
            if ($atmCeOI > 0 || $atmPeOI > 0) {
                if ($atmPeOI > $atmCeOI * 1.1) $signalD = 'BULLISH';
                elseif ($atmCeOI > $atmPeOI * 1.1) $signalD = 'BEARISH';
            }

            // ── 7. Final verdict — majority vote of 4 signals ─────────────
            $votes = ['BULLISH' => 0, 'BEARISH' => 0, 'NEUTRAL' => 0];
            foreach ([$signalA, $signalB, $signalC, $signalD] as $s) {
                $votes[$s]++;
            }

            $maxVote    = max($votes['BULLISH'], $votes['BEARISH']);
            $finalTrend = 'SIDEWAYS';
            $action     = 'NO TRADE';

            if ($votes['BULLISH'] >= 3) {
                $finalTrend = 'BULLISH';
                $action     = 'BUY CE';
            } elseif ($votes['BEARISH'] >= 3) {
                $finalTrend = 'BEARISH';
                $action     = 'BUY PE';
            } elseif ($votes['BULLISH'] === 2 && $votes['BEARISH'] <= 1) {
                $finalTrend = 'BULLISH';
                $action     = 'BUY CE';
            } elseif ($votes['BEARISH'] === 2 && $votes['BULLISH'] <= 1) {
                $finalTrend = 'BEARISH';
                $action     = 'BUY PE';
            }

            // Confidence: % of signals agreeing
            $totalSignals  = 4;
            $agreeingCount = $finalTrend === 'SIDEWAYS'
                ? max($votes['BULLISH'], $votes['BEARISH'])
                : $votes[$finalTrend];
            $confidence = round(($agreeingCount / $totalSignals) * 100);

            // ── 8. Return response ────────────────────────────────────────
            return response()->json([
                'success'    => true,
                'prev_date'  => $prevDate,
                'pred_date'  => $baseDate,
                'symbol'     => $symbol,

                // Day summary
                'day_open'   => $dayOpen,
                'day_close'  => $dayClose,
                'day_high'   => $dayHigh,
                'day_low'    => $dayLow,
                'close_position' => $closePosition,

                // 4 signals
                'signal_a'   => $signalA,   // close position
                'signal_b'   => $signalB,   // last 1-hr price
                'signal_c'   => $signalC,   // OI vs price
                'signal_d'   => $signalD,   // ATM CE vs PE OI

                // Last hour detail
                'last_hour_open'  => $lastHourOpen,
                'last_hour_close' => $lastHourClose,
                'ce_oi_change'    => $ceOiChange,
                'pe_oi_change'    => $peOiChange,
                'atm_ce_oi'       => $atmCeOI,
                'atm_pe_oi'       => $atmPeOI,

                // Final
                'trend'      => $finalTrend,
                'action'     => $action,
                'confidence' => $confidence,
                'votes'      => $votes,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── API: symbols ──────────────────────────────────────────────────────

    public function symbols()
    {
        $syms = DB::table('nifty_option_1min_ohlc')
            ->where('instrument_type', 'FUT')
            ->distinct()->orderBy('base_symbol')
            ->pluck('base_symbol')->values();
        return response()->json(['success' => true, 'symbols' => $syms]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function getPrevTradingDate(string $date): ?string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 15; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) {
                return $prev->format('Y-m-d');
            }
            $prev->subDay();
        }
        return null;
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}