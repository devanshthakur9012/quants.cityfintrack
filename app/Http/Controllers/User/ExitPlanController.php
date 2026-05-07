<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OIIVAutoOrder;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Auth;
use Carbon\Carbon;

/**
 * ExitPlanController — BTST Exit Signal Analysis
 *
 * Logic:
 *   - We took a trade on "signal_date" (e.g. 9 March) after 3 PM
 *   - On the NEXT trading day "exit_check_date" (e.g. 10 March) we check:
 *       EXIT CANDLE 09:15  = exit_check_date  09:15 CE/PE OI
 *       EXIT CANDLE 09:30  = exit_check_date  09:30 CE/PE OI
 *       OPEN CANDLE        = signal_date      15:15 CE/PE OI  (prev day 15:15)
 *   - Apply same OI signal logic to get EXIT SENTIMENT (BULLISH/BEARISH/NEUTRAL)
 *   - Compare with ORIGINAL TRADE DIRECTION:
 *       Same direction  → HOLD (still in same direction)
 *       Opposite        → EXIT  (signal reversed)
 *       NEUTRAL         → MONITOR (unclear)
 *
 * DB notes:
 *   trade_date    = DATETIME → use whereDate()
 *   interval_time = DATETIME → use whereRaw("TIME(interval_time) = 'HH:MM:SS'")
 */
class ExitPlanController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'BTST Exit Plan — OI Signal Check';
        return view($this->activeTemplate . 'user.oiiv-auto.exit-plan', compact('pageTitle'));
    }

    // =========================================================
    //  MAIN API — GET EXIT SIGNALS FOR DATE RANGE
    // =========================================================

    public function getExitSignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterDecision  = $request->get('filter_decision'); // HOLD / EXIT / MONITOR

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            $exitCheckDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $results = [];

            foreach ($exitCheckDates as $exitCheckDate) {
                $signalDate = $this->getPreviousTradingDate($exitCheckDate);
                $rows       = $this->buildExitRows($exitCheckDate, $signalDate, $selectedSymbols, $filterDecision);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }

            usort($results, fn($a, $b) =>
                $b['exit_check_date'] <=> $a['exit_check_date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' exit signal records found',
            ]);

        } catch (\Exception $e) {
            Log::error('ExitPlan getExitSignals Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD EXIT ROWS FOR ONE DATE PAIR
    // =========================================================

    private function buildExitRows(
        string $exitCheckDate,
        string $signalDate,
        array  $symbolFilter,
        ?string $filterDecision
    ): array {

        // ── 1a. Load exit_check_date 09:15 candles (FUT + CE + PE) ──────────
        $morningQuery = OptionOhlcData::whereDate('trade_date', $exitCheckDate)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '09:15:00'");

        if (!empty($symbolFilter)) {
            $morningQuery->whereIn('base_symbol', $symbolFilter);
        }

        $morningCandles = $morningQuery->get();

        if ($morningCandles->isEmpty()) return [];

        $symbols = $morningCandles->pluck('base_symbol')->unique()->values()->toArray();

        // ── 1b. Load exit_check_date 09:30 candles (FUT + CE + PE) ──────────
        $morningCandles930 = OptionOhlcData::whereDate('trade_date', $exitCheckDate)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        // ── 2. Load signal_date 15:15 candles (CE + PE) — baseline for both ─
        $eodCandles15 = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        // Fallback 15:00
        $eodCandles00 = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        // ── 3. Group candles ─────────────────────────────────────────────────
        $morningGrouped = [];
        foreach ($morningCandles as $c) {
            $morningGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        $morningGrouped930 = [];
        foreach ($morningCandles930 as $c) {
            $morningGrouped930[$c->base_symbol][$c->instrument_type][] = $c;
        }

        // Build prev OI map: prefer 15:15, fallback 15:00 per symbol
        $prevGrouped = [];
        foreach ($eodCandles15 as $c) {
            $prevGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }
        foreach ($eodCandles00 as $c) {
            if (!isset($prevGrouped[$c->base_symbol][$c->instrument_type])) {
                $prevGrouped[$c->base_symbol][$c->instrument_type][] = $c;
            }
        }

        // ── 4. Load signal_date 14:45 context candles ────────────────────────
        $signalFutCandles = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->whereIn('base_symbol', $symbols)
            ->get()
            ->keyBy('base_symbol');

        $signalOptionCandles = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        $signalOptionGrouped = [];
        foreach ($signalOptionCandles as $c) {
            $signalOptionGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        $signalPrevDate    = $this->getPreviousTradingDate($signalDate);
        $signalPrevCandles = OptionOhlcData::whereDate('trade_date', $signalPrevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        $signalPrevGrouped = [];
        foreach ($signalPrevCandles as $c) {
            $key = (string)($c->strike ?? '');
            if ($key !== '') {
                $signalPrevGrouped[$c->base_symbol][$c->instrument_type][$key] = $c;
            }
        }

        $rows = [];

        foreach ($morningGrouped as $symbol => $typeMap) {

            $futCandle = ($typeMap['FUT'] ?? [])[0] ?? null;
            if (!$futCandle || (float)$futCandle->close <= 0) continue;

            // ── PREV OI baseline (same for 09:15 and 09:30) ─────────────────
            $exitCePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $prevGrouped[$symbol]['CE'] ?? []));
            $exitPePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $prevGrouped[$symbol]['PE'] ?? []));

            // ── EXIT SIGNAL 09:15 ────────────────────────────────────────────
            $exitCeCurOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $typeMap['CE'] ?? []));
            $exitPeCurOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $typeMap['PE'] ?? []));

            if ($exitCeCurOI == 0 && $exitPeCurOI == 0) continue;

            $exitCeOiPct = $exitCePrevOI > 0 ? round((($exitCeCurOI - $exitCePrevOI) / $exitCePrevOI) * 100, 2) : 0;
            $exitPeOiPct = $exitPePrevOI > 0 ? round((($exitPeCurOI - $exitPePrevOI) / $exitPePrevOI) * 100, 2) : 0;

            $exitOiSignal  = $this->getOISignal($exitCeOiPct, $exitPeOiPct);
            $exitSentiment = $exitOiSignal['signal'];

            // ── EXIT SIGNAL 09:30 ────────────────────────────────────────────
            $typeMap930      = $morningGrouped930[$symbol] ?? [];
            $exit930CeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $typeMap930['CE'] ?? []));
            $exit930PeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $typeMap930['PE'] ?? []));

            $exit930CeOiPct  = $exitCePrevOI > 0 ? round((($exit930CeCurOI - $exitCePrevOI) / $exitCePrevOI) * 100, 2) : 0;
            $exit930PeOiPct  = $exitPePrevOI > 0 ? round((($exit930PeCurOI - $exitPePrevOI) / $exitPePrevOI) * 100, 2) : 0;

            $exit930OiSignal  = $this->getOISignal($exit930CeOiPct, $exit930PeOiPct);
            $exit930Sentiment = $exit930OiSignal['signal'];

            // ── ORIGINAL SIGNAL (signal_date 14:45 vs prev day 15:00) ────────
            $origCeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $signalOptionGrouped[$symbol]['CE'] ?? []));
            $origPeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $signalOptionGrouped[$symbol]['PE'] ?? []));
            $origCePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), array_values($signalPrevGrouped[$symbol]['CE'] ?? [])));
            $origPePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), array_values($signalPrevGrouped[$symbol]['PE'] ?? [])));

            $origCeOiPct = $origCePrevOI > 0 ? round((($origCeCurOI - $origCePrevOI) / $origCePrevOI) * 100, 2) : 0;
            $origPeOiPct = $origPePrevOI > 0 ? round((($origPeCurOI - $origPePrevOI) / $origPePrevOI) * 100, 2) : 0;

            $origOiSignal  = $this->getOISignal($origCeOiPct, $origPeOiPct);
            $origSentiment = $origOiSignal['signal'];

            $origTradeAction = match($origSentiment) {
                'BULLISH' => 'BUY CE',
                'BEARISH' => 'BUY PE',
                default   => 'WAIT',
            };

            if ($origTradeAction === 'WAIT') continue;

            // ── EXIT DECISIONS ───────────────────────────────────────────────
            [$exitDecision, $exitReason] = $this->determineExitDecision(
                $origSentiment, $exitSentiment, $origTradeAction
            );

            // 09:30 decision
            [$exit930Decision, $exit930Reason] = ($exit930CeCurOI == 0 && $exit930PeCurOI == 0)
                ? ['N/A', 'No 09:30 OI data available']
                : $this->determineExitDecision($origSentiment, $exit930Sentiment, $origTradeAction);

            if (!empty($filterDecision) && $exitDecision !== $filterDecision) continue;

            // ── Prices ───────────────────────────────────────────────────────
            $exitFutPrice   = (float)$futCandle->close;
            $signalFutClose = $signalFutCandles[$symbol] ?? null;
            $signalPrice    = $signalFutClose ? (float)$signalFutClose->close : 0;

            $rows[] = [
                'signal_date'     => $signalDate,
                'exit_check_date' => $exitCheckDate,
                'symbol'          => $symbol,
                'fut_symbol'      => $futCandle->trading_symbol ?? $symbol,

                // Original signal
                'orig_sentiment'    => $origSentiment,
                'orig_trade_action' => $origTradeAction,
                'orig_condition'    => $origOiSignal['condition'],
                'orig_ce_oi_pct'    => $origCeOiPct,
                'orig_pe_oi_pct'    => $origPeOiPct,
                'orig_ce_oi'        => $origCeCurOI,
                'orig_pe_oi'        => $origPeCurOI,

                // Exit signal 09:15
                'exit_sentiment'  => $exitSentiment,
                'exit_condition'  => $exitOiSignal['condition'],
                'exit_ce_oi_pct'  => $exitCeOiPct,
                'exit_pe_oi_pct'  => $exitPeOiPct,
                'exit_ce_oi'      => $exitCeCurOI,
                'exit_pe_oi'      => $exitPeCurOI,
                'exit_decision'   => $exitDecision,
                'exit_reason'     => $exitReason,

                // Exit signal 09:30
                'exit930_sentiment' => $exit930Sentiment,
                'exit930_condition' => $exit930OiSignal['condition'],
                'exit930_ce_oi_pct' => $exit930CeOiPct,
                'exit930_pe_oi_pct' => $exit930PeOiPct,
                'exit930_ce_oi'     => $exit930CeCurOI,
                'exit930_pe_oi'     => $exit930PeCurOI,
                'exit930_decision'  => $exit930Decision,
                'exit930_reason'    => $exit930Reason,

                // Prices
                'signal_price'      => round($signalPrice, 2),
                'exit_check_price'  => round($exitFutPrice, 2),
                'price_change'      => $signalPrice > 0 ? round($exitFutPrice - $signalPrice, 2) : 0,
                'price_change_pct'  => $signalPrice > 0
                    ? round((($exitFutPrice - $signalPrice) / $signalPrice) * 100, 2)
                    : 0,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  LIVE EXIT CHECK — for a specific date (AJAX for "today")
    // =========================================================

    public function getTodayExitCheck(Request $request)
    {
        try {
            $checkDate       = $request->get('check_date', Carbon::today()->toDateString());
            $selectedSymbols = $request->get('symbols', []);

            $signalDate = $this->getPreviousTradingDate($checkDate);

            $rows = $this->buildExitRows($checkDate, $signalDate, $selectedSymbols, null);

            usort($rows, fn($a, $b) => $a['symbol'] <=> $b['symbol']);

            $hold    = array_filter($rows, fn($r) => $r['exit_decision'] === 'HOLD');
            $exit    = array_filter($rows, fn($r) => $r['exit_decision'] === 'EXIT');
            $monitor = array_filter($rows, fn($r) => $r['exit_decision'] === 'MONITOR');

            return response()->json([
                'success'       => true,
                'data'          => array_values($rows),
                'check_date'    => $checkDate,
                'signal_date'   => $signalDate,
                'total'         => count($rows),
                'hold_count'    => count($hold),
                'exit_count'    => count($exit),
                'monitor_count' => count($monitor),
                'message'       => count($rows) . ' symbols checked',
            ]);

        } catch (\Exception $e) {
            Log::error('ExitPlan getTodayExitCheck Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  OI SIGNAL LOGIC
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
    //  EXIT DECISION LOGIC
    // =========================================================

    private function determineExitDecision(
        string $origSentiment,
        string $exitSentiment,
        string $tradeAction
    ): array {

        if ($exitSentiment === 'NEUTRAL') {
            return [
                'MONITOR',
                'Exit OI signal is NEUTRAL — unclear direction, monitor closely',
            ];
        }

        if ($origSentiment === $exitSentiment) {
            $dir = $exitSentiment === 'BULLISH' ? '📈 BULLISH' : '📉 BEARISH';
            return [
                'HOLD',
                "Exit OI confirms same direction ({$dir}) — HOLD your {$tradeAction} position",
            ];
        }

        $origDir = $origSentiment === 'BULLISH' ? '📈 BULLISH' : '📉 BEARISH';
        $exitDir = $exitSentiment === 'BULLISH' ? '📈 BULLISH' : '📉 BEARISH';
        return [
            'EXIT',
            "Signal REVERSED: original={$origDir}, exit check={$exitDir} — Consider EXITING {$tradeAction}",
        ];
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
}