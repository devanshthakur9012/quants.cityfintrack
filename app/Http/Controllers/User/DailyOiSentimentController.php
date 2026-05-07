<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Models\OptionDailyOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * DailyOiSentimentController — 3-Column OI Sentiment Comparison
 *
 * ┌──────────┬────────────────────────────────────────────────────────────────────┐
 * │ Column 1 │ Original   — OIIVAutoController::getOISignal() logic, UNCHANGED   │
 * │          │ Data source: OptionOhlcData CE/PE, 14:45 today vs 15:15 prev day  │
 * ├──────────┼────────────────────────────────────────────────────────────────────┤
 * │ Column 2 │ Pivot Daily — removed from PivotSignalController                  │
 * │          │ Data source: OptionDailyOhlcData, prevDay vs dayBeforePrev        │
 * ├──────────┼────────────────────────────────────────────────────────────────────┤
 * │ Column 3 │ New Logic  — same CE%/PE% as Col 1, corrected 4-case + strength   │
 * └──────────┴────────────────────────────────────────────────────────────────────┘
 */
class DailyOiSentimentController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Daily OI Sentiment — 3 Signals';
        return view($this->activeTemplate . 'user.daily-oi-sentiment.index', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS  (identical to OIIVAutoController)
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
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $symbolFilter = $request->get('symbols', []);

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both dates',
                    'data'    => [],
                ]);
            }

            // Trade dates in range — same DATETIME-safe pattern as OIIVAutoController
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
                    'success' => true,
                    'data'    => [],
                    'message' => 'No trading dates found in selected range.',
                ]);
            }

            $results = [];

            foreach ($tradeDates as $date) {
                $prevDate      = $this->getPreviousTradingDate($date);
                $dayBeforePrev = $this->getPreviousTradingDate($prevDate);

                foreach ($this->buildRowsForDate($date, $prevDate, $dayBeforePrev, $symbolFilter) as $row) {
                    $results[] = $row;
                }
            }

            // Newest first, then symbol a→z
            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records loaded',
            ]);

        } catch (\Exception $e) {
            Log::error('DailyOiSentiment::analyze — ' . $e->getMessage());
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

    private function buildRowsForDate(
        string $date,
        string $prevDate,
        string $dayBeforePrev,
        array  $symbolFilter
    ): array {

        // ─── Col 1 & 3: today 14:45 CE/PE candles ────────────────────────────
        // Identical query to OIIVAutoController::buildSignalRowsForDate
        $todayQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) {
            $todayQuery->whereIn('base_symbol', $symbolFilter);
        }

        $todayCandles = $todayQuery->get();
        if ($todayCandles->isEmpty()) return [];

        $todaySymbols = $todayCandles->pluck('base_symbol')->unique()->values()->toArray();

        // ─── Col 1 & 3: prev day 15:15 CE/PE candles (strike-level baseline) ──
        $prevCandles = OptionOhlcData::whereDate('trade_date', $prevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $todaySymbols)
            ->get();

        // Index: [symbol][type][strike] = candle
        $prevByStrike = [];
        foreach ($prevCandles as $c) {
            $prevByStrike[$c->base_symbol][$c->instrument_type][(string) $c->strike] = $c;
        }

        // Group today: [symbol][type][] = candle
        $todayGrouped = [];
        foreach ($todayCandles as $c) {
            $todayGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        // ─── Col 2: OptionDailyOhlcData — batch load for all symbols ─────────
        $pivotData = $this->loadPivotDailyData($todaySymbols, $prevDate, $dayBeforePrev);

        // ─── Build one row per symbol ─────────────────────────────────────────
        $rows = [];

        foreach ($todayGrouped as $symbol => $typeMap) {

            // Sum OI vs prev — exact same logic as OIIVAutoController::sumOIVsPrev
            [$ceOpenOI, $ceCurOI] = $this->sumOIVsPrev(
                $typeMap['CE'] ?? [],
                $prevByStrike[$symbol]['CE'] ?? []
            );
            [$peOpenOI, $peCurOI] = $this->sumOIVsPrev(
                $typeMap['PE'] ?? [],
                $prevByStrike[$symbol]['PE'] ?? []
            );

            if ($ceCurOI == 0 && $peCurOI == 0) continue;

            $cePct = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $pePct = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;

            // Col 1 — ORIGINAL (unchanged OIIVAutoController logic)
            $col1 = $this->getOriginalOISignal($cePct, $pePct);

            // Col 2 — PIVOT DAILY (OptionDailyOhlcData)
            $col2 = $this->getPivotDailySignal($pivotData[$symbol] ?? []);

            // Col 3 — NEW LOGIC (corrected 4-case + strength)
            $col3 = $this->getNewLogicSignal($cePct, $pePct);

            $rows[] = [
                'date'   => $date,
                'symbol' => $symbol,

                // Shared raw data (Col 1 & 3 basis)
                'ce_oi'     => (int)   $ceCurOI,
                'pe_oi'     => (int)   $peCurOI,
                'ce_oi_pct' => round($cePct, 2),
                'pe_oi_pct' => round($pePct, 2),

                // Col 1 — Original
                'orig_signal'    => $col1['signal'],
                'orig_condition' => $col1['condition'],
                'orig_reason'    => $col1['reason'],

                // Col 2 — Pivot Daily
                'pivot_signal'    => $col2['signal'],
                'pivot_condition' => $col2['condition'],
                'pivot_reason'    => $col2['reason'],
                'pivot_ce_pct'    => $col2['ce_pct'],
                'pivot_pe_pct'    => $col2['pe_pct'],

                // Col 3 — New Logic
                'new_signal'     => $col3['signal'],
                'new_condition'  => $col3['condition'],
                'new_reason'     => $col3['reason'],
                'new_strength'   => $col3['strength'],
                'new_difference' => $col3['difference'],
            ];
        }

        return $rows;
    }

    // =========================================================
    //  COLUMN 1 — ORIGINAL  (OIIVAutoController::getOISignal — EXACT COPY)
    // =========================================================

    private function getOriginalOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp && $peDown) {
            return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓',        'reason' => 'Call buildup + Put unwinding'];
        }
        if ($ceDown && $peUp) {
            return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑',        'reason' => 'Call unwinding + Put buildup'];
        }
        if ($ceUp && $peUp) {
            return $cePct > $pePct
                ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)',    'reason' => "Both buildup CE stronger (CE:+{$cePct}% > PE:+{$pePct}%)"]
                : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)',    'reason' => "Both buildup PE stronger (PE:+{$pePct}% > CE:+{$cePct}%)"];
        }
        if ($ceDown && $peDown) {
            return $cePct < $pePct
                ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)',    'reason' => "Both unwinding CE stronger (CE:{$cePct}% < PE:{$pePct}%)"]
                : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)',    'reason' => "Both unwinding PE stronger (PE:{$pePct}% < CE:{$cePct}%)"];
        }

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat', 'reason' => 'No clear OI direction'];
    }

    // =========================================================
    //  COLUMN 2 — PIVOT DAILY  (OptionDailyOhlcData prev vs day-before)
    // =========================================================

    private function loadPivotDailyData(array $symbols, string $prevDate, string $dayBeforePrev): array
    {
        // Single batch query for prev day
        $prevRows = OptionDailyOhlcData::whereIn('base_symbol', $symbols)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->where('is_missing', 0)
            ->selectRaw('base_symbol, instrument_type, SUM(oi) as total_oi')
            ->groupBy('base_symbol', 'instrument_type')
            ->get();

        // Single batch query for day-before-prev
        $baseRows = OptionDailyOhlcData::whereIn('base_symbol', $symbols)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $dayBeforePrev)
            ->where('is_missing', 0)
            ->selectRaw('base_symbol, instrument_type, SUM(oi) as total_oi')
            ->groupBy('base_symbol', 'instrument_type')
            ->get();

        $data = [];
        foreach ($symbols as $sym) {
            $data[$sym] = ['prevCeOi' => 0.0, 'prevPeOi' => 0.0, 'baseCeOi' => 0.0, 'basePeOi' => 0.0];
        }
        foreach ($prevRows as $r) {
            if ($r->instrument_type === 'CE') $data[$r->base_symbol]['prevCeOi'] = (float) $r->total_oi;
            if ($r->instrument_type === 'PE') $data[$r->base_symbol]['prevPeOi'] = (float) $r->total_oi;
        }
        foreach ($baseRows as $r) {
            if ($r->instrument_type === 'CE') $data[$r->base_symbol]['baseCeOi'] = (float) $r->total_oi;
            if ($r->instrument_type === 'PE') $data[$r->base_symbol]['basePeOi'] = (float) $r->total_oi;
        }

        return $data;
    }

    private function getPivotDailySignal(array $d): array
    {
        $na = ['signal' => 'N/A', 'condition' => 'N/A', 'reason' => 'No data', 'ce_pct' => 0, 'pe_pct' => 0];

        $prevCeOi = $d['prevCeOi'] ?? 0;
        $prevPeOi = $d['prevPeOi'] ?? 0;
        $baseCeOi = $d['baseCeOi'] ?? 0;
        $basePeOi = $d['basePeOi'] ?? 0;

        if (($prevCeOi == 0 && $prevPeOi == 0) || ($baseCeOi == 0 && $basePeOi == 0)) return $na;

        $cePct = $baseCeOi > 0 ? round((($prevCeOi - $baseCeOi) / $baseCeOi) * 100, 2) : 0;
        $pePct = $basePeOi > 0 ? round((($prevPeOi - $basePeOi) / $basePeOi) * 100, 2) : 0;

        // Pivot uses the corrected new-logic signal (same as PivotSignalController used)
        $sig = $this->getNewLogicSignal($cePct, $pePct);

        return [
            'signal'    => $sig['signal'],
            'condition' => $sig['condition'],
            'reason'    => $sig['reason'],
            'ce_pct'    => $cePct,
            'pe_pct'    => $pePct,
        ];
    }

    // =========================================================
    //  COLUMN 3 — NEW CORRECTED 4-CASE LOGIC + STRENGTH
    // =========================================================

    private function getNewLogicSignal(float $cePct, float $pePct): array
    {
        // ── Case 1: CE up, PE down → BEARISH (call buildup + put unwinding) ──
        if ($cePct > 0 && $pePct < 0) {
            $signal    = 'BEARISH';
            $condition = 'CE ↑ + PE ↓';
            $reason    = 'Call buildup + Put unwinding → Resistance forming';

        // ── Case 2: CE down, PE up → BULLISH (call unwinding + put buildup) ──
        } elseif ($cePct < 0 && $pePct > 0) {
            $signal    = 'BULLISH';
            $condition = 'CE ↓ + PE ↑';
            $reason    = 'Call unwinding + Put buildup → Support forming';

        // ── Case 3: Both up → dominant side wins ──────────────────────────────
        } elseif ($cePct > 0 && $pePct > 0) {
            if ($pePct > $cePct) {
                $signal    = 'BULLISH';
                $condition = 'Both ↑ (PE > CE)';
                $reason    = "Put buildup stronger (+{$pePct}% vs +{$cePct}%) → Bullish";
            } else {
                $signal    = 'BEARISH';
                $condition = 'Both ↑ (CE ≥ PE)';
                $reason    = "Call buildup stronger (+{$cePct}% vs +{$pePct}%) → Bearish";
            }

        // ── Case 4: Both down → larger unwind side wins ───────────────────────
        } else {
            if (abs($cePct) > abs($pePct)) {
                $signal    = 'BULLISH';
                $condition = 'Both ↓ (|CE| > |PE|)';
                $reason    = "Call unwinding larger ({$cePct}% vs {$pePct}%) → Short covering → Bullish";
            } else {
                $signal    = 'BEARISH';
                $condition = 'Both ↓ (|PE| ≥ |CE|)';
                $reason    = "Put unwinding larger ({$pePct}% vs {$cePct}%) → Long covering → Bearish";
            }
        }

        // ── Strength ─────────────────────────────────────────────────────────
        $difference = round(abs($cePct - $pePct), 2);

        if      ($difference > 3.0) $strength = 'Very Strong';
        elseif  ($difference > 1.5) $strength = 'Strong';
        elseif  ($difference > 0.5) $strength = 'Moderate';
        else                        $strength = 'Weak';

        return compact('signal', 'condition', 'reason', 'strength', 'difference');
    }

    // =========================================================
    //  OI HELPER  (exact copy of OIIVAutoController::sumOIVsPrev)
    // =========================================================

    private function sumOIVsPrev(array $todayCandles, array $prevByStrike): array
    {
        $prevOI  = 0;
        $todayOI = 0;
        foreach ($todayCandles as $tc) {
            $key      = (string) $tc->strike;
            $todayOI += (int) ($tc->oi ?? 0);
            if (isset($prevByStrike[$key])) {
                $prevOI += (int) ($prevByStrike[$key]->oi ?? 0);
            }
        }
        return [$prevOI, $todayOI];
    }

    // =========================================================
    //  DATE HELPERS  (exact copy of OIIVAutoController helpers)
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