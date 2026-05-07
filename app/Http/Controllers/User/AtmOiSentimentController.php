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
 * AtmOiSentimentController — ATM Strike Only, 3-Column OI Sentiment
 *
 * Uses SINGLE ATM strike (strike_position = 'ATM') — no aggregation.
 *
 * ┌──────────┬──────────────────────────────────────────────────────────────────┐
 * │ Column 1 │ Original    — OIIVAutoController::getOISignal() logic, UNCHANGED │
 * │          │ Data: OptionOhlcData ATM CE/PE 14:45 today vs 15:15 prev day    │
 * ├──────────┼──────────────────────────────────────────────────────────────────┤
 * │ Column 2 │ Pivot Daily — removed from PivotSignalController                │
 * │          │ Data: OptionDailyOhlcData ATM strike, prevDay vs dayBeforePrev  │
 * ├──────────┼──────────────────────────────────────────────────────────────────┤
 * │ Column 3 │ New Logic   — same ATM CE%/PE% as Col 1, corrected 4-case logic │
 * │          │ + Strength = |CE% − PE%|                                        │
 * └──────────┴──────────────────────────────────────────────────────────────────┘
 */
class AtmOiSentimentController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'ATM OI Sentiment — Single Strike';
        return view($this->activeTemplate . 'user.atm-oi-sentiment.index', compact('pageTitle'));
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
    //  MAIN ANALYSIS
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
            Log::error('AtmOiSentiment::analyze — ' . $e->getMessage());
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

        // ── Col 1 & 3: today 14:45 ATM CE/PE candles ────────────────────────
        $todayQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('strike_position', 'ATM')
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->whereNotNull('strike')
            ->whereNotNull('expiry_date');

        if (!empty($symbolFilter)) {
            $todayQuery->whereIn('base_symbol', $symbolFilter);
        }

        // Order by expiry_date so first candle per symbol+type = nearest expiry
        $todayCandles = $todayQuery->orderBy('expiry_date')->get();

        if ($todayCandles->isEmpty()) return [];

        // Keep only nearest expiry per symbol+type
        $atmToday = [];
        foreach ($todayCandles as $c) {
            $key = $c->base_symbol . '|' . $c->instrument_type;
            if (!isset($atmToday[$key])) {
                $atmToday[$key] = $c;
            }
        }

        $todaySymbols = collect($atmToday)
            ->map(fn($c) => $c->base_symbol)
            ->unique()->values()->toArray();

        if (empty($todaySymbols)) return [];

        // ── Col 1 & 3: prev day 15:15 — same strike as today's ATM ─────────
        $prevCandles = OptionOhlcData::whereDate('trade_date', $prevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $todaySymbols)
            ->get();

        // Index: [symbol][type][strike] = candle
        $prevByStrike = [];
        foreach ($prevCandles as $c) {
            $prevByStrike[$c->base_symbol][$c->instrument_type][(string) $c->strike] = $c;
        }

        // ── Col 2: OptionDailyOhlcData ATM strike, prevDay vs dayBeforePrev ─
        $pivotData = $this->loadPivotDailyAtmData($atmToday, $prevDate, $dayBeforePrev);

        // ── Build one row per symbol ──────────────────────────────────────
        $rows = [];

        foreach ($todaySymbols as $symbol) {
            $ceToday = $atmToday[$symbol . '|CE'] ?? null;
            $peToday = $atmToday[$symbol . '|PE'] ?? null;

            if (!$ceToday && !$peToday) continue;

            // CE OI vs prev same strike
            $ceTodayOI = (int) ($ceToday->oi ?? 0);
            $ceStrike  = $ceToday ? (string) $ceToday->strike : null;
            $cePrevOI  = ($ceStrike && isset($prevByStrike[$symbol]['CE'][$ceStrike]))
                ? (int) ($prevByStrike[$symbol]['CE'][$ceStrike]->oi ?? 0)
                : 0;

            // PE OI vs prev same strike
            $peTodayOI = (int) ($peToday->oi ?? 0);
            $peStrike  = $peToday ? (string) $peToday->strike : null;
            $pePrevOI  = ($peStrike && isset($prevByStrike[$symbol]['PE'][$peStrike]))
                ? (int) ($prevByStrike[$symbol]['PE'][$peStrike]->oi ?? 0)
                : 0;

            if ($ceTodayOI == 0 && $peTodayOI == 0) continue;

            $cePct = $cePrevOI > 0 ? round((($ceTodayOI - $cePrevOI) / $cePrevOI) * 100, 4) : 0;
            $pePct = $pePrevOI > 0 ? round((($peTodayOI - $pePrevOI) / $pePrevOI) * 100, 4) : 0;

            $atmStrike = $ceToday->atm_strike ?? $peToday->atm_strike ?? $ceStrike ?? $peStrike;

            // Col 1 — Original (unchanged)
            $col1 = $this->getOriginalOISignal($cePct, $pePct);

            // Col 2 — Pivot Daily (OptionDailyOhlcData ATM)
            $col2 = $this->getPivotDailySignal($pivotData[$symbol] ?? []);

            // Col 3 — New corrected 4-case logic + strength
            $col3 = $this->getNewLogicSignal($cePct, $pePct);

            $rows[] = [
                'date'       => $date,
                'symbol'     => $symbol,
                'atm_strike' => $atmStrike,
                'ce_strike'  => $ceStrike,
                'pe_strike'  => $peStrike,

                // Raw OI
                'ce_oi'      => $ceTodayOI,
                'ce_oi_prev' => $cePrevOI,
                'pe_oi'      => $peTodayOI,
                'pe_oi_prev' => $pePrevOI,
                'ce_oi_pct'  => round($cePct, 2),
                'pe_oi_pct'  => round($pePct, 2),

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

                // Col 3 — New Logic + Strength
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
    //  COLUMN 2 — PIVOT DAILY (OptionDailyOhlcData, ATM strike)
    // =========================================================

    /**
     * Load OptionDailyOhlcData for the ATM strike of each symbol.
     * ATM strike = the strike from today's ATM candle.
     * Compare: prevDate OI vs dayBeforePrev OI for that same strike.
     */
    private function loadPivotDailyAtmData(array $atmToday, string $prevDate, string $dayBeforePrev): array
    {
        $data = [];

        // Build per-symbol ATM strikes for CE and PE
        $ceStrikes = [];
        $peStrikes = [];

        foreach ($atmToday as $key => $candle) {
            if ($candle->instrument_type === 'CE') {
                $ceStrikes[$candle->base_symbol] = (string) $candle->strike;
            }
            if ($candle->instrument_type === 'PE') {
                $peStrikes[$candle->base_symbol] = (string) $candle->strike;
            }
        }

        $symbols = array_unique(array_merge(array_keys($ceStrikes), array_keys($peStrikes)));

        foreach ($symbols as $symbol) {
            $ceStrike = $ceStrikes[$symbol] ?? null;
            $peStrike = $peStrikes[$symbol] ?? null;

            // prevDate OI for ATM CE strike
            $prevCeOi = 0;
            if ($ceStrike) {
                $prevCeOi = (float) OptionDailyOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', 'CE')
                    ->where('strike', $ceStrike)
                    ->whereDate('trade_date', $prevDate)
                    ->where('is_missing', 0)
                    ->sum('oi');
            }

            // prevDate OI for ATM PE strike
            $prevPeOi = 0;
            if ($peStrike) {
                $prevPeOi = (float) OptionDailyOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', 'PE')
                    ->where('strike', $peStrike)
                    ->whereDate('trade_date', $prevDate)
                    ->where('is_missing', 0)
                    ->sum('oi');
            }

            // dayBeforePrev OI for ATM CE strike
            $baseCeOi = 0;
            if ($ceStrike) {
                $baseCeOi = (float) OptionDailyOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', 'CE')
                    ->where('strike', $ceStrike)
                    ->whereDate('trade_date', $dayBeforePrev)
                    ->where('is_missing', 0)
                    ->sum('oi');
            }

            // dayBeforePrev OI for ATM PE strike
            $basePeOi = 0;
            if ($peStrike) {
                $basePeOi = (float) OptionDailyOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', 'PE')
                    ->where('strike', $peStrike)
                    ->whereDate('trade_date', $dayBeforePrev)
                    ->where('is_missing', 0)
                    ->sum('oi');
            }

            $data[$symbol] = [
                'prevCeOi' => $prevCeOi,
                'prevPeOi' => $prevPeOi,
                'baseCeOi' => $baseCeOi,
                'basePeOi' => $basePeOi,
            ];
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

        if (($prevCeOi == 0 && $prevPeOi == 0) || ($baseCeOi == 0 && $basePeOi == 0)) {
            return $na;
        }

        $cePct = $baseCeOi > 0 ? round((($prevCeOi - $baseCeOi) / $baseCeOi) * 100, 2) : 0;
        $pePct = $basePeOi > 0 ? round((($prevPeOi - $basePeOi) / $basePeOi) * 100, 2) : 0;

        // Uses corrected new-logic signal (same as PivotSignalController)
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
    //  COLUMN 1 — ORIGINAL (OIIVAutoController::getOISignal — EXACT)
    // =========================================================

    private function getOriginalOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp && $peDown) {
            return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓',     'reason' => 'Call buildup + Put unwinding'];
        }
        if ($ceDown && $peUp) {
            return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑',     'reason' => 'Call unwinding + Put buildup'];
        }
        if ($ceUp && $peUp) {
            return $cePct > $pePct
                ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)', 'reason' => "Both buildup CE stronger (CE:+{$cePct}% > PE:+{$pePct}%)"]
                : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)', 'reason' => "Both buildup PE stronger (PE:+{$pePct}% > CE:+{$cePct}%)"];
        }
        if ($ceDown && $peDown) {
            return $cePct < $pePct
                ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)', 'reason' => "Both unwinding CE stronger (CE:{$cePct}% < PE:{$pePct}%)"]
                : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)', 'reason' => "Both unwinding PE stronger (PE:{$pePct}% < CE:{$cePct}%)"];
        }

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat', 'reason' => 'No clear OI direction'];
    }

    // =========================================================
    //  COLUMN 3 — NEW CORRECTED 4-CASE LOGIC + STRENGTH
    // =========================================================

    private function getNewLogicSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0) {
            $signal    = 'BEARISH';
            $condition = 'CE ↑ + PE ↓';
            $reason    = 'Call buildup + Put unwinding → Resistance forming';

        } elseif ($cePct < 0 && $pePct > 0) {
            $signal    = 'BULLISH';
            $condition = 'CE ↓ + PE ↑';
            $reason    = 'Call unwinding + Put buildup → Support forming';

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

        $difference = round(abs($cePct - $pePct), 2);

        if      ($difference > 3.0) $strength = 'Very Strong';
        elseif  ($difference > 1.5) $strength = 'Strong';
        elseif  ($difference > 0.5) $strength = 'Moderate';
        else                        $strength = 'Weak';

        return compact('signal', 'condition', 'reason', 'strength', 'difference');
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
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}