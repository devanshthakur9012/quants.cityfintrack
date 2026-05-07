<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OIStrategyController — OI Strategy Analysis
 *
 * Columns: Date | Symbol | CE OI | CE % | PE OI | PE % | Sentiment (old logic) | Strategy (new logic)
 *
 * Sentiment logic (existing):
 *   CE ↑ + PE ↓ → BEARISH   (Call buildup + Put unwinding)
 *   CE ↓ + PE ↑ → BULLISH   (Call unwinding + Put buildup)
 *   Both ↑      → CE > PE = BEARISH, else BULLISH
 *   Both ↓      → CE < PE = BULLISH, else BEARISH
 *
 * Strategy logic (new — optionStrategy):
 *   CE < 0 && PE < 0 → LONG_STRADDLE   (Both unwinding → volatility expected)
 *   CE > 0 && PE > 0 → SHORT_STRADDLE  (Both building  → range expected)
 *   CE > 0 && PE < 0 → BEARISH_DIRECTIONAL (BUY_PE)
 *   CE < 0 && PE > 0 → BULLISH_DIRECTIONAL (BUY_CE)
 */
class OIStrategyController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'OI Strategy Analysis';
        return view($this->activeTemplate . 'user.oi-strategy.index', compact('pageTitle'));
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

    public function analyzeSignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterStrategy  = $request->get('filter_strategy');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data'    => [],
                ]);
            }

            // All trade dates in range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $results = [];

            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                $rows     = $this->buildRowsForDate($date, $prevDate, $selectedSymbols);

                foreach ($rows as $row) {
                    // Apply strategy filter if set
                    if ($filterStrategy && $row['strategy'] !== $filterStrategy) continue;
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
            Log::error('OI Strategy Analysis Error: ' . $e->getMessage());
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

    private function buildRowsForDate(string $date, string $prevDate, array $symbolFilter): array
    {
        // Today's FUT at 14:45 — defines active symbols
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');

        if ($futCandles->isEmpty()) return [];

        $symbols = $futCandles->keys()->toArray();
        $rows    = [];

        foreach ($symbols as $symbol) {
            $futCandle = $futCandles[$symbol];

            // Resolve expiry (data-driven)
            $currentExpiry = $this->getNearestExpiryForDate($symbol, $date);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;

            // Today CE/PE OI at 14:45
            $todayCeQuery = OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '14:45:00'");
            if ($currentExpiry) $todayCeQuery->whereDate('expiry_date', $currentExpiry);
            $ceCurOI = (int) $todayCeQuery->sum('oi');

            $todayPeQuery = OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '14:45:00'");
            if ($currentExpiry) $todayPeQuery->whereDate('expiry_date', $currentExpiry);
            $peCurOI = (int) $todayPeQuery->sum('oi');

            // Prev day CE/PE OI at 15:00
            $prevCeQuery = OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'");
            if ($prevExpiry) $prevCeQuery->whereDate('expiry_date', $prevExpiry);
            $ceOpenOI = (int) $prevCeQuery->sum('oi');

            $prevPeQuery = OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'");
            if ($prevExpiry) $prevPeQuery->whereDate('expiry_date', $prevExpiry);
            $peOpenOI = (int) $prevPeQuery->sum('oi');

            if ($ceCurOI == 0 && $peCurOI == 0) continue;

            $cePct = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $pePct = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;

            // ── Old Sentiment logic ──────────────────────────────────
            $sentiment = $this->getOISentiment($cePct, $pePct);

            // ── New Strategy logic (optionStrategy) ──────────────────
            $strategyResult = $this->optionStrategy($cePct, $pePct);
            $trapResult = $this->detectTrap($cePct, $pePct);

            $rows[] = [
                'date'           => $date,
                'symbol'         => $symbol,

                'ce_oi'          => $ceCurOI,
                'ce_oi_prev'     => $ceOpenOI,
                'ce_oi_pct'      => $cePct,

                'pe_oi'          => $peCurOI,
                'pe_oi_prev'     => $peOpenOI,
                'pe_oi_pct'      => $pePct,

                // Old logic
                'oi_condition'   => $sentiment['condition'],
                'sentiment'      => $sentiment['signal'],
                'sentiment_reason' => $sentiment['reason'],

                // New logic
                'strategy'       => $strategyResult['strategy'],
                'first_leg'      => $strategyResult['first_leg'],
                'strategy_remark'=> $strategyResult['remark'],

                // ← ADD THIS ONE LINE:
                'mm_trap'        => $trapResult,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  OLD SENTIMENT LOGIC (unchanged from OIIVAutoController)
    // =========================================================

    private function getOISentiment(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup CE stronger", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup PE stronger", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding CE stronger", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding PE stronger", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // =========================================================
    //  NEW STRATEGY LOGIC (optionStrategy)
    // =========================================================

    private function optionStrategy(float $cePct, float $pePct): array
    {
        if ($cePct < 0 && $pePct < 0) {
            // Both unwinding → volatility expansion expected
            return [
                'strategy'  => 'LONG_STRADDLE',
                'first_leg' => abs($cePct) > abs($pePct) ? 'BUY_CE_FIRST' : 'BUY_PE_FIRST',
                'remark'    => 'Volatility expansion expected',
            ];
        }

        if ($cePct > 0 && $pePct > 0) {
            // Both building → range/consolidation expected
            return [
                'strategy'  => 'SHORT_STRADDLE',
                'first_leg' => $cePct > $pePct ? 'SELL_CE_FIRST' : 'SELL_PE_FIRST',
                'remark'    => 'Range expected',
            ];
        }

        if ($cePct > 0 && $pePct < 0) {
            // Call writing + Put unwinding → bearish
            return [
                'strategy'  => 'BEARISH_DIRECTIONAL',
                'first_leg' => 'BUY_PE',
                'remark'    => 'Call writing + Put unwinding',
            ];
        }

        if ($cePct < 0 && $pePct > 0) {
            // Call unwinding + Put writing → bullish
            return [
                'strategy'  => 'BULLISH_DIRECTIONAL',
                'first_leg' => 'BUY_CE',
                'remark'    => 'Call unwinding + Put writing',
            ];
        }

        return [
            'strategy'  => 'NO_TRADE',
            'first_leg' => 'NONE',
            'remark'    => 'No clear signal',
        ];
    }

    // =========================================================
    //  MM TRAP LOGIC
    // =========================================================

    private function detectTrap(float $ceChange, float $pePct): array
    {
        $result = ['trap' => 'NO_TRAP', 'strength' => 'NONE'];
        $diff   = abs($ceChange - $pePct);

        if ($ceChange > 0 && $pePct > 0) {
            if ($diff > 20)      $strength = 'STRONG';
            elseif ($diff > 10)  $strength = 'MODERATE';
            else                 return $result; // weak — ignore

            $result['trap']     = $pePct > $ceChange ? 'CE_TRAP' : 'PE_TRAP';
            $result['strength'] = $strength;
        }

        return $result;
    }

    // =========================================================
    //  EXPIRY HELPERS (mirrors OIIVAutoController exactly)
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