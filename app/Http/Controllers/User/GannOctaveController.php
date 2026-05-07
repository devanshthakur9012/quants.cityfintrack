<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * GannOctaveController
 *
 * OI ENGINE: 100% identical to OIIVAutoController::buildSignalRowsForDate()
 * ─────────────────────────────────────────────────────────────────────────
 *  • resolveActiveExpiry()     — mirrors LiveOptionOhlcCollector expiry-day shift
 *  • getNextSeriesExpiry()     — fallback to next expiry on expiry day
 *  • getNearestExpiryForDate() — forward-looking expiry lookup
 *  • getPrevDayExpiry()        — handles weekly rollover (new-series on prev day)
 *  • getOISignal()             — EXACT same logic: CE↑+PE↓, CE↓+PE↑, Both↑, Both↓
 *  • OI query times: today 14:45, prev 15:00  (same as original)
 *  • OI scoped to same expiry on both sides   (same as original)
 *
 * GANN LAYERS (additive on top of OI):
 *  • Gann Octave (8-level from 20-day swing H/L)
 *  • Volume confirmation (current vs 20-day avg)
 *  • Event detection (|chg%| > 5 + STRONG volume)
 *  • Astro day bias (Mon=Setup … Fri=Exit)
 *  • Time cycle (day-count mod 3/5/7)
 *
 * FINAL SIGNAL PRIORITY:
 *  1. Event Override
 *  2. OI + Gann aligned
 *  3. Strong Gann alone
 *  4. OI only (low confidence)
 *  5. WAIT
 */
class GannOctaveController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Gann Octave Analysis';
        return view($this->activeTemplate . 'user.gann.index', compact('pageTitle'));
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
            $filterAction    = $request->get('filter_action');

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success' => false, 'message' => 'No trading data found for selected dates', 'data' => []]);
            }

            $results = [];
            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                foreach ($this->buildGannRowsForDate($date, $prevDate, $selectedSymbols, $filterAction) as $row) {
                    $results[] = $row;
                }
            }

            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('Gann Octave Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS — OI ENGINE IS 100% FROM OIIVAutoController
    // =========================================================

    private function buildGannRowsForDate(string $date, string $prevDate, array $symbolFilter, ?string $actionFilter): array
    {
        // FUT candles at 14:45 — same time as OIIVAutoController
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');

        if ($futCandles->isEmpty()) return [];

        $symbols  = $futCandles->keys()->toArray();
        $epoch    = Carbon::parse('2020-01-01');
        $dayCount = (int) $epoch->diffInDays(Carbon::parse($date));
        $rows     = [];

        foreach ($symbols as $symbol) {
            $futCandle = $futCandles[$symbol];
            $ltp       = (float) $futCandle->close;
            if ($ltp <= 0) continue;

            // ═══════════════════════════════════════════════════════
            //  OI ENGINE — EXACT COPY FROM OIIVAutoController
            //  Same expiry resolution, same query times, same logic
            // ═══════════════════════════════════════════════════════

            // Step 1: Resolve expiry (expiry-day aware)
            $rawExpiry     = $this->getNearestExpiryForDate($symbol, $date);
            $isExpiryDay   = ($rawExpiry !== null && $rawExpiry === $date);
            $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;

            // Step 2: Today CE/PE at 14:45 scoped to active expiry
            $todayCeQuery = OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '14:45:00'");
            if ($currentExpiry) $todayCeQuery->whereDate('expiry_date', $currentExpiry);
            $todayCeCandles = $todayCeQuery->get();

            $todayPeQuery = OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '14:45:00'");
            if ($currentExpiry) $todayPeQuery->whereDate('expiry_date', $currentExpiry);
            $todayPeCandles = $todayPeQuery->get();

            // Step 3: Prev day CE/PE at 15:00 scoped to prev expiry
            $prevCeQuery = OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'");
            if ($prevExpiry) $prevCeQuery->whereDate('expiry_date', $prevExpiry);
            $prevCeCandles = $prevCeQuery->get();

            $prevPeQuery = OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'");
            if ($prevExpiry) $prevPeQuery->whereDate('expiry_date', $prevExpiry);
            $prevPeCandles = $prevPeQuery->get();

            // Step 4: Sum OI (all strikes, no strike-matching — same as original)
            $ceCurOI  = (int) $todayCeCandles->sum('oi');
            $peCurOI  = (int) $todayPeCandles->sum('oi');
            $ceOpenOI = (int) $prevCeCandles->sum('oi');
            $peOpenOI = (int) $prevPeCandles->sum('oi');

            // Skip only when today genuinely has no option data
            if ($ceCurOI == 0 && $peCurOI == 0) continue;

            // Step 5: OI % change
            $ceOiPct = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $peOiPct = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;

            // Step 6: OI Signal — EXACT same function as OIIVAutoController
            $oiSignal    = $this->getOISignal($ceOiPct, $peOiPct);
            $oiSentiment = $oiSignal['signal'];    // BULLISH / BEARISH / NEUTRAL
            $oiCondition = $oiSignal['condition']; // e.g. "CE ↑ + PE ↓"

            // ═══════════════════════════════════════════════════════
            //  GANN LAYERS  (added on top, OI is not modified)
            // ═══════════════════════════════════════════════════════

            $swing = $this->getSwingHighLow($symbol, $date, 20);
            if (!$swing) continue;

            $gannLevels  = $this->getGannLevels($swing['high'], $swing['low']);
            $gannBias    = $this->getGannBias($ltp, $gannLevels);
            $nearLevel   = $this->getNearestGannLevel($ltp, $gannLevels);

            $volumeData  = $this->getVolumeData($symbol, $date);
            $volStrength = $this->getVolumeStrength($volumeData['current'], $volumeData['average']);

            $openCandle    = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->first();
            $openPrice     = $openCandle ? (float) $openCandle->open : $ltp;
            $changePercent = $openPrice > 0 ? round((($ltp - $openPrice) / $openPrice) * 100, 2) : 0;

            $event     = $this->detectEvent($changePercent, $volStrength);
            $astroBias = $this->getAstroBias($date);
            $timeCycle = $this->getTimeCycle($dayCount);

            $final = $this->getFinalSignal([
                'ltp'            => $ltp,
                'change_percent' => $changePercent,
                'gann_bias'      => $gannBias,
                'volume'         => $volStrength,
                'event'          => $event,
                'astro'          => $astroBias,
                'time_cycle'     => $timeCycle,
                'oi_sentiment'   => $oiSentiment,
            ]);

            if (!empty($actionFilter) && $final['signal'] !== $actionFilter) continue;

            $rows[] = [
                'date'   => $date,
                'symbol' => $symbol,

                // Price
                'ltp'            => round($ltp, 2),
                'open_price'     => round($openPrice, 2),
                'change_percent' => $changePercent,

                // OI — same field names as OIIVAutoController output
                'oi_sentiment'   => $oiSentiment,
                'oi_condition'   => $oiCondition,
                'oi_reason'      => $oiSignal['reason'],
                'ce_oi'          => $ceCurOI,
                'pe_oi'          => $peCurOI,
                'ce_oi_pct'      => round($ceOiPct, 2),
                'pe_oi_pct'      => round($peOiPct, 2),
                'current_expiry' => $currentExpiry,
                'prev_expiry'    => $prevExpiry,
                'is_expiry_day'  => $isExpiryDay,

                // Swing
                'swing_high'   => round($swing['high'], 2),
                'swing_low'    => round($swing['low'], 2),
                'swing_period' => $swing['period'],

                // Gann
                'gann_levels' => array_map(fn($v) => round($v, 2), $gannLevels),
                'gann_bias'   => $gannBias,
                'near_level'  => $nearLevel,

                // Volume
                'volume_current' => $volumeData['current'],
                'volume_avg'     => $volumeData['average'],
                'vol_strength'   => $volStrength,

                // Context
                'event'      => $event,
                'astro_bias' => $astroBias,
                'time_cycle' => $timeCycle,
                'day_count'  => $dayCount,

                // Final
                'signal'     => $final['signal'],
                'reason'     => $final['reason'],
                'confidence' => $final['confidence'],
            ];
        }

        return $rows;
    }

    // =========================================================
    //  EXPIRY HELPERS — 100% IDENTICAL TO OIIVAutoController
    //  Do NOT simplify these. The logic prevents:
    //   - Expiry-day zero OI (shifts to next series)
    //   - Weekly rollover zero match (prev day may have different expiry)
    // =========================================================

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = $this->getNearestExpiryForDate($symbol, $date);

        if (!$expiry) return null;

        if ($expiry === $date) {
            $next = $this->getNextSeriesExpiry($symbol, $date, $expiry);
            if ($next) {
                Log::info("GannOctaveController::resolveActiveExpiry — expiry day shift for {$symbol} on {$date}: {$expiry} → {$next}");
                return $next;
            }
        }

        return $expiry;
    }

    private function getNextSeriesExpiry(string $symbol, string $date, string $currentExpiry): ?string
    {
        $next = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($next) return $next;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)
            ->where('is_missing', 0)
            ->exists();

        if ($exists) return $currentExpiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  OI SIGNAL — 100% IDENTICAL TO OIIVAutoController::getOISignal()
    //  ⚠ DO NOT CHANGE — must stay in sync with the OI/PE/CE page
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
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup CE stronger (CE:+{$cePct}% > PE:+{$pePct}%)", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup PE stronger (PE:+{$pePct}% > CE:+{$cePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding CE stronger (CE:{$cePct}% < PE:{$pePct}%)", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding PE stronger (PE:{$pePct}% < CE:{$cePct}%)", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // =========================================================
    //  GANN OCTAVE HELPERS
    // =========================================================

    private function getGannLevels(float $high, float $low): array
    {
        $range  = $high - $low;
        $octave = $range / 8;
        $levels = [];
        for ($i = 0; $i <= 8; $i++) {
            $levels[$i] = $low + ($octave * $i);
        }
        return $levels;
    }

    private function getGannBias(float $ltp, array $levels): string
    {
        if ($ltp >= $levels[6]) return 'STRONG_BULLISH';
        if ($ltp >= $levels[4]) return 'BULLISH';
        if ($ltp <= $levels[2]) return 'STRONG_BEARISH';
        if ($ltp <= $levels[4]) return 'BEARISH';
        return 'NEUTRAL';
    }

    private function getNearestGannLevel(float $ltp, array $levels): array
    {
        $labels  = ['0/8','1/8','2/8','3/8','4/8','5/8','6/8','7/8','8/8'];
        $minDiff = PHP_FLOAT_MAX;
        $nearIdx = 0;
        foreach ($levels as $i => $lv) {
            $d = abs($ltp - $lv);
            if ($d < $minDiff) { $minDiff = $d; $nearIdx = $i; }
        }
        return [
            'index'    => $nearIdx,
            'label'    => $labels[$nearIdx],
            'value'    => round($levels[$nearIdx], 2),
            'distance' => round($minDiff, 2),
        ];
    }

    private function getSwingHighLow(string $symbol, string $date, int $days = 20): ?array
    {
        $start   = Carbon::parse($date)->subDays($days * 2)->toDateString();
        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $start)
            ->whereDate('trade_date', '<=', $date)
            ->select(DB::raw('DATE(trade_date) as d'), DB::raw('MAX(high) as h'), DB::raw('MIN(low) as l'))
            ->groupBy('d')
            ->orderByDesc('d')
            ->limit($days)
            ->get();

        if ($candles->isEmpty()) return null;

        return [
            'high'   => (float) $candles->max('h'),
            'low'    => (float) $candles->min('l'),
            'period' => $candles->count(),
        ];
    }

    // =========================================================
    //  VOLUME HELPERS
    // =========================================================

    private function getVolumeData(string $symbol, string $date): array
    {
        $current = (int) OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->sum('volume');

        $start = Carbon::parse($date)->subDays(40)->toDateString();
        $avg   = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', '>=', $start)
            ->whereDate('trade_date', '<',  $date)
            ->select(DB::raw('DATE(trade_date) as d'), DB::raw('SUM(volume) as dv'))
            ->groupBy('d')
            ->orderByDesc('d')
            ->limit(20)
            ->get()
            ->avg('dv');

        return [
            'current' => $current,
            'average' => (int) round($avg ?? 0),
        ];
    }

    private function getVolumeStrength(int $current, int $average): string
    {
        if ($average <= 0)               return 'WEAK';
        if ($current > 2   * $average)  return 'STRONG';
        if ($current > 1.3 * $average)  return 'MODERATE';
        return 'WEAK';
    }

    // =========================================================
    //  EVENT / ASTRO / TIME CYCLE
    // =========================================================

    private function detectEvent(float $changePercent, string $volumeStrength): string
    {
        if (abs($changePercent) > 5 && $volumeStrength === 'STRONG') return 'EVENT_MOVE';
        if (abs($changePercent) > 3 && $volumeStrength === 'STRONG') return 'BIG_MOVE';
        return 'NORMAL';
    }

    private function getAstroBias(string $date): string
    {
        $day = (int) Carbon::parse($date)->dayOfWeekIso;
        return match($day) {
            1 => 'SETUP',
            2 => 'BULLISH',
            3 => 'TRAP',
            4 => 'EXPANSION',
            5 => 'EXIT',
            default => 'NONE',
        };
    }

    private function getTimeCycle(int $dayCount): string
    {
        if ($dayCount % 7 === 0) return 'MAJOR_REVERSAL';
        if ($dayCount % 5 === 0) return 'VOLATILE';
        if ($dayCount % 3 === 0) return 'REVERSAL_ZONE';
        return 'NORMAL';
    }

    // =========================================================
    //  FINAL SIGNAL ENGINE
    // =========================================================

    private function getFinalSignal(array $data): array
    {
        $gann   = $data['gann_bias'];
        $vol    = $data['volume'];
        $event  = $data['event'];
        $astro  = $data['astro'];
        $cycle  = $data['time_cycle'];
        $oi     = $data['oi_sentiment'];
        $chgPct = $data['change_percent'];

        // 1. Event Override
        if ($event === 'EVENT_MOVE') {
            return ['signal' => $chgPct > 0 ? 'BUY CE' : 'BUY PE', 'reason' => 'EVENT_OVERRIDE', 'confidence' => 'HIGH'];
        }

        // 2. OI + Gann aligned
        if ($oi === 'BULLISH' && in_array($gann, ['STRONG_BULLISH', 'BULLISH']) && $vol !== 'WEAK') {
            return ['signal' => 'BUY CE', 'reason' => 'OI_GANN_BULL', 'confidence' => $vol === 'STRONG' ? 'HIGH' : 'MEDIUM'];
        }
        if ($oi === 'BEARISH' && in_array($gann, ['STRONG_BEARISH', 'BEARISH']) && $vol !== 'WEAK') {
            return ['signal' => 'BUY PE', 'reason' => 'OI_GANN_BEAR', 'confidence' => $vol === 'STRONG' ? 'HIGH' : 'MEDIUM'];
        }

        // 3. Strong Gann alone
        if ($gann === 'STRONG_BULLISH' && $vol !== 'WEAK') {
            return ['signal' => 'BUY CE', 'reason' => 'GANN_BREAKOUT', 'confidence' => 'MEDIUM'];
        }
        if ($gann === 'STRONG_BEARISH' && $vol !== 'WEAK') {
            return ['signal' => 'BUY PE', 'reason' => 'GANN_BREAKDOWN', 'confidence' => 'MEDIUM'];
        }

        // 4. OI only
        if ($oi === 'BULLISH') {
            return ['signal' => 'BUY CE', 'reason' => 'OI_ONLY_BULL', 'confidence' => 'LOW'];
        }
        if ($oi === 'BEARISH') {
            return ['signal' => 'BUY PE', 'reason' => 'OI_ONLY_BEAR', 'confidence' => 'LOW'];
        }

        // 5. WAIT
        if ($astro === 'TRAP') {
            return ['signal' => 'WAIT', 'reason' => 'TRAP_DAY', 'confidence' => 'N/A'];
        }
        if (in_array($cycle, ['MAJOR_REVERSAL', 'REVERSAL_ZONE'])) {
            return ['signal' => 'WAIT', 'reason' => 'TIME_CYCLE_' . $cycle, 'confidence' => 'N/A'];
        }

        return ['signal' => 'WAIT', 'reason' => 'NO_CLEAR_EDGE', 'confidence' => 'N/A'];
    }

    // =========================================================
    //  DATE HELPERS — identical to OIIVAutoController
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
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}