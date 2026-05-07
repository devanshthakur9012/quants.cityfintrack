<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuroDailyVerdict;
use App\Models\OptionOhlcData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AuroController v4
 *
 * Changes vs v3 (all driven by AuroQuantEngine v4):
 *
 * CTRL-1 — formatVerdict() exposes 6 new fields from post_trade_notes:
 *   range_override, range_high, range_low, stock_move_day_pct,
 *   sig_c_mode, sig_d_divergence
 *   These let the blade show the range gate status and divergence note.
 *
 * CTRL-2 — formatVerdict() exposes sig_c_mode and sig_d_divergence
 *   as top-level keys (not buried in post_notes JSON) so the blade
 *   can use them directly without extra JS parsing.
 *
 * CTRL-3 — signalBreakdown() context block now includes:
 *   sig_c_mode, sig_d_divergence, range_high, range_low, range_override
 *   so the Signal Breakdown panel on the dashboard shows the new state.
 *
 * CTRL-4 — confBadge logic in blade now has RANGE_WAIT; controller
 *   passes confidence as-is (no change needed here, blade handles it).
 *
 * CTRL-5 — history() now maps the new post_notes fields so the
 *   history table can show range/divergence columns without extra fetches.
 *
 * All v3 bug fixes (BUG 1–5) are preserved unchanged.
 */
class AuroController extends Controller
{
    private const SYMBOL = 'AUROPHARMA';

    // ══════════════════════════════════════════════════════════════════════════
    // Main Dashboard
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'Auropharma Quant Engine';

        $from60 = now()->subDays(60)->toDateString();
        $stats  = AuroDailyVerdict::stats($from60);

        $missReasons = AuroDailyVerdict::wrong()
            ->whereNotNull('miss_reason')
            ->select('miss_reason', DB::raw('COUNT(*) as cnt'))
            ->groupBy('miss_reason')
            ->orderByDesc('cnt')
            ->get();

        $sigBEffect = AuroDailyVerdict::traded()
            ->whereNotNull('was_correct')
            ->where('sig_b_score', '!=', 0)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(was_correct) as wins'),
                DB::raw('AVG(actual_pnl_pct) as avg_pnl')
            )
            ->first();

        return view(
            $this->activeTemplate . 'user.auro.index',
            compact('pageTitle', 'stats', 'missReasons', 'sigBEffect')
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Today's Verdict (AJAX)
    // ══════════════════════════════════════════════════════════════════════════

    public function todayVerdict()
    {
        $verdict = AuroDailyVerdict::where('trade_date', now()->toDateString())->first();

        if (!$verdict) {
            return response()->json([
                'success' => false,
                'message' => 'No verdict for today. Run: php artisan auro:daily-verdict',
            ]);
        }

        return response()->json(['success' => true, 'data' => $this->formatVerdict($verdict)]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Verdict Detail by date (AJAX)
    // ══════════════════════════════════════════════════════════════════════════

    public function verdictDetail(Request $request)
    {
        $date    = $request->get('date', now()->toDateString());
        $verdict = AuroDailyVerdict::where('trade_date', $date)->first();

        if (!$verdict) {
            return response()->json(['success' => false, 'message' => 'No verdict for ' . $date]);
        }

        return response()->json(['success' => true, 'data' => $this->formatVerdict($verdict)]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // History Table (AJAX)
    // ══════════════════════════════════════════════════════════════════════════

    public function history(Request $request)
    {
        $from   = $request->get('from', now()->subDays(60)->toDateString());
        $to     = $request->get('to', now()->toDateString());
        $filter = $request->get('filter', 'ALL');

        $q = AuroDailyVerdict::whereBetween('trade_date', [$from, $to])
            ->orderByDesc('trade_date');

        match ($filter) {
            'TRADED'    => $q->traded(),
            'NO_TRADE'  => $q->noTrade(),
            'CORRECT'   => $q->correct(),
            'WRONG'     => $q->wrong(),
            'HIGH_CONF' => $q->highConf(),
            default     => null,
        };

        $verdicts = $q->get();
        $stats    = AuroDailyVerdict::stats($from, $to);

        return response()->json([
            'success' => true,
            'data'    => $verdicts->map(fn($v) => $this->formatVerdict($v)),
            'stats'   => $stats,
            'count'   => $verdicts->count(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Update Actual Result (AJAX)
    // BUG 2 fix: actual_option_open nullable
    // BUG 3 fix: merge notes, never overwrite engine risk params
    // ══════════════════════════════════════════════════════════════════════════

    public function updateResult(Request $request)
    {
        $request->validate([
            'trade_date'         => 'required|date',
            'actual_option_open' => 'nullable|numeric',
            'actual_pnl_pct'     => 'required|numeric',
            'was_correct'        => 'required|boolean',
            'miss_reason'        => 'nullable|string|max:100',
            'post_trade_notes'   => 'nullable|string|max:500',
        ]);

        $verdict = AuroDailyVerdict::where('trade_date', $request->trade_date)->first();

        if (!$verdict) {
            return response()->json(['success' => false, 'message' => 'No verdict found for ' . $request->trade_date]);
        }

        // BUG 3: Merge — preserve engine risk params (stop_loss_pct, target_pct,
        // range_override, sig_c_mode, etc.), only update user-entered fields
        $existingNotes = $this->safeJsonDecode($verdict->post_trade_notes);
        $mergedNotes   = array_merge($existingNotes, [
            'user_notes' => $request->post_trade_notes ?? '',
            'result_at'  => now()->toDateTimeString(),
        ]);

        $verdict->update([
            'actual_option_open' => $request->actual_option_open,
            'actual_pnl_pct'     => $request->actual_pnl_pct,
            'was_correct'        => $request->was_correct,
            'miss_reason'        => $request->miss_reason,
            'post_trade_notes'   => json_encode($mergedNotes),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Result saved for ' . $request->trade_date,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Live OI Snapshot (AJAX)
    // ══════════════════════════════════════════════════════════════════════════

    public function liveOI(Request $request)
    {
        $date   = $request->get('date', now()->toDateString());
        $expiry = $this->resolveExpiry($date);

        if (!$expiry) {
            return response()->json(['success' => false, 'message' => 'No expiry data for ' . $date]);
        }

        $latestTime = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->max(DB::raw('TIME(interval_time)'));

        if (!$latestTime) {
            return response()->json(['success' => false, 'message' => 'No option OI data for ' . $date]);
        }

        $rows = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $expiry)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = ?", [$latestTime])
            ->whereNotNull('strike')
            ->orderBy('strike')
            ->get(['instrument_type', 'strike', 'oi', 'volume', 'close', 'strike_position']);

        $futClose = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:15:00'")
            ->value('close');

        $strikeMap = [];
        foreach ($rows as $row) {
            $s = (string)(float)$row->strike;
            $strikeMap[$s][$row->instrument_type] = [
                'oi'       => (int)$row->oi,
                'volume'   => (int)$row->volume,
                'ltp'      => (float)$row->close,
                'position' => $row->strike_position,
            ];
        }

        return response()->json([
            'success'   => true,
            'date'      => $date,
            'expiry'    => $expiry,
            'time'      => $latestTime,
            'fut_close' => $futClose,
            'strikes'   => $strikeMap,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI Intraday (AJAX)
    // ══════════════════════════════════════════════════════════════════════════

    public function oiIntraday(Request $request)
    {
        $date   = $request->get('date', now()->toDateString());
        $expiry = $this->resolveExpiry($date);
        $strike = $request->get('strike');

        $q = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $expiry)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->orderBy('interval_time');

        if ($strike) $q->where('strike', $strike);

        $rows    = $q->get(['instrument_type', 'oi', 'interval_time']);
        $timeMap = [];

        foreach ($rows as $row) {
            $t  = Carbon::parse($row->interval_time)->format('H:i');
            $ty = $row->instrument_type;
            $timeMap[$t][$ty] = ($timeMap[$t][$ty] ?? 0) + $row->oi;
        }

        $labels = array_keys($timeMap);
        sort($labels);

        return response()->json([
            'success' => true,
            'labels'  => $labels,
            'ce_oi'   => array_map(fn($t) => $timeMap[$t]['CE'] ?? 0, $labels),
            'pe_oi'   => array_map(fn($t) => $timeMap[$t]['PE'] ?? 0, $labels),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Smart Money Monitor (AJAX)
    // BUG 4 fix: ATM reference uses 14:45 not 09:15
    // ══════════════════════════════════════════════════════════════════════════

    public function smartMoneyMonitor(Request $request)
    {
        $date   = $request->get('date', now()->toDateString());
        $expiry = $this->resolveExpiry($date);

        if (!$expiry) {
            return response()->json(['success' => false, 'message' => 'No expiry for ' . $date]);
        }

        $dates = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', '<=', $date)
            ->select(DB::raw('DATE(trade_date) as d'))
            ->distinct()
            ->orderByDesc('d')
            ->limit(7)
            ->pluck('d')
            ->toArray();

        if (empty($dates)) {
            return response()->json(['success' => false, 'message' => 'No FUT data up to ' . $date]);
        }

        // BUG 4: use 14:45 to match engine ATM
        $futClose = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $dates[0])
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->value('close');

        if (!$futClose) {
            $futClose = OptionOhlcData::where('base_symbol', self::SYMBOL)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $dates[0])
                ->whereRaw("TIME(interval_time) = '09:15:00'")
                ->value('close');
        }

        if (!$futClose) {
            return response()->json(['success' => false, 'message' => 'No FUT price data for ' . $dates[0]]);
        }

        $strikeInterval = $this->getStrikeInterval($expiry);
        $atm            = round((float)$futClose / $strikeInterval) * $strikeInterval;

        $relStrikes = [-5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5];
        $result     = [];

        foreach ($relStrikes as $rel) {
            $strike = $atm + ($rel * $strikeInterval);
            $type   = $rel <= 0 ? 'PE' : 'CE';
            $label  = $rel === 0 ? 'ATM' : ($rel > 0 ? "ATM+{$rel}" : "ATM{$rel}");

            $oiSeries = [];
            foreach ($dates as $d) {
                $time = ($d === $dates[0]) ? '14:45:00' : '15:00:00';
                $oi   = (int)(OptionOhlcData::where('base_symbol', self::SYMBOL)
                    ->where('instrument_type', $type)
                    ->whereDate('trade_date', $d)
                    ->whereDate('expiry_date', $expiry)
                    ->where('strike', $strike)
                    ->whereRaw("TIME(interval_time) = ?", [$time])
                    ->value('oi') ?? 0);
                $oiSeries[$d] = $oi;
            }

            $vals        = array_values($oiSeries);
            $consecutive = 0;
            for ($i = 0; $i < count($vals) - 1; $i++) {
                if ($vals[$i] > $vals[$i + 1] && $vals[$i + 1] > 0) $consecutive++;
                else break;
            }

            $alertLevel = $consecutive >= 3 ? 'HIGH' : ($consecutive >= 2 ? 'MEDIUM' : 'NONE');

            $result[] = [
                'strike'             => $strike,
                'position'           => $label,
                'type'               => $type,
                'oi_by_date'         => $oiSeries,
                'consecutive_growth' => $consecutive,
                'is_accumulating'    => $consecutive >= 2,
                'alert_level'        => $alertLevel,
            ];
        }

        return response()->json([
            'success'         => true,
            'atm'             => $atm,
            'strike_interval' => $strikeInterval,
            'dates'           => $dates,
            'strikes'         => $result,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Signal Score Breakdown (AJAX)
    // CTRL-3: context now includes sig_c_mode, sig_d_divergence, range info
    // ══════════════════════════════════════════════════════════════════════════

    public function signalBreakdown(Request $request)
    {
        $date    = $request->get('date', now()->toDateString());
        $verdict = AuroDailyVerdict::where('trade_date', $date)->first();

        if (!$verdict) {
            return response()->json(['success' => false, 'message' => 'No verdict for ' . $date]);
        }

        // BUG 1: safe sig_e_detail accessor
        $sigEDetail = $this->getSigEDetail($verdict);

        // BUG 3 / CTRL-3: parse post_trade_notes for all engine fields
        $postNotes = $this->safeJsonDecode($verdict->post_trade_notes);

        return response()->json([
            'success' => true,
            'data'    => [
                'date'      => $date,
                'net_score' => $verdict->net_score,
                'direction' => $verdict->direction,
                'signals'   => [
                    ['name' => 'A · OI Pressure',     'score' => $verdict->sig_a_score, 'verdict' => $verdict->sig_a_verdict, 'max' => 3],
                    ['name' => 'B · Smart Money',     'score' => $verdict->sig_b_score, 'verdict' => $verdict->sig_b_verdict, 'max' => 3],
                    ['name' => 'C · Price Structure', 'score' => $verdict->sig_c_score, 'verdict' => $verdict->sig_c_verdict, 'max' => 2],
                    ['name' => 'D · Market Align',    'score' => $verdict->sig_d_score, 'verdict' => $verdict->sig_d_verdict, 'max' => 3],
                ],
                'vetos' => [
                    'market_opposing' => (bool) $verdict->veto_market_opposing,
                    'low_volume'      => (bool) $verdict->veto_low_volume,
                    'expiry_week'     => (bool) $verdict->veto_expiry_week,
                    'conflicting'     => (bool) $verdict->veto_conflicting_signals,
                ],
                'strike' => [
                    'atm'         => $verdict->atm_strike,
                    'recommended' => $verdict->recommended_strike,
                    'position'    => $verdict->recommended_strike_position,
                    'ltp'         => $verdict->recommended_strike_ltp,
                    'sig_e'       => $verdict->sig_e_verdict,
                    'reason'      => $sigEDetail['reason'] ?? null,
                ],
                // CTRL-3: extended context block
                'context' => [
                    'regime'           => $verdict->market_regime,
                    'vol_5d'           => $verdict->auro_volatility_5d,
                    'oi_type'          => $verdict->fut_oi_type,
                    'support'          => $verdict->support_20d,
                    'resistance'       => $verdict->resistance_20d,
                    // v4 new fields
                    'sig_c_mode'       => $postNotes['sig_c_mode']       ?? 'NORMAL',
                    'sig_d_divergence' => $postNotes['sig_d_divergence'] ?? 0,
                    'range_override'   => $postNotes['range_override']   ?? false,
                    'range_high'       => $postNotes['range_high']       ?? null,
                    'range_low'        => $postNotes['range_low']        ?? null,
                    'stock_move_day'   => $postNotes['stock_move_day_pct'] ?? null,
                ],
                'risk' => [
                    'stop_loss_pct'    => $postNotes['stop_loss_pct']    ?? null,
                    'target_pct'       => $postNotes['target_pct']       ?? null,
                    'threshold_used'   => $postNotes['threshold_used']   ?? null,
                    'max_hold_candles' => $postNotes['max_hold_candles'] ?? null,
                ],
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Backtest Stats (AJAX — unchanged from v3)
    // ══════════════════════════════════════════════════════════════════════════

    public function backtestStats(Request $request)
    {
        $from = $request->get('from', now()->subDays(90)->toDateString());
        $to   = $request->get('to',   now()->toDateString());

        $stats = AuroDailyVerdict::stats($from, $to);

        $pnlRows = AuroDailyVerdict::traded()
            ->whereBetween('trade_date', [$from, $to])
            ->whereNotNull('actual_pnl_pct')
            ->orderBy('trade_date')
            ->select('trade_date', 'direction', 'confidence', 'actual_pnl_pct', 'was_correct', 'net_score')
            ->get();

        $running = 0;
        $curve   = [];
        foreach ($pnlRows as $row) {
            $running += (float)$row->actual_pnl_pct;
            $curve[]  = [
                'date'      => $row->trade_date,
                'pnl'       => round((float)$row->actual_pnl_pct, 2),
                'running'   => round($running, 2),
                'direction' => $row->direction,
                'score'     => $row->net_score,
                'correct'   => $row->was_correct,
            ];
        }

        $scoreBuckets = AuroDailyVerdict::traded()
            ->whereBetween('trade_date', [$from, $to])
            ->whereNotNull('was_correct')
            ->select('net_score', 'was_correct')
            ->get()
            ->groupBy(fn($r) => (int)round((float)$r->net_score));

        $byScore = [];
        foreach ($scoreBuckets as $score => $rows) {
            $wins    = $rows->where('was_correct', 1)->count();
            $total   = $rows->count();
            $byScore[$score] = [
                'score'    => $score,
                'total'    => $total,
                'wins'     => $wins,
                'win_rate' => $total > 0 ? round($wins / $total * 100, 1) : 0,
            ];
        }
        ksort($byScore);

        return response()->json([
            'success'   => true,
            'stats'     => $stats,
            'pnl_curve' => $curve,
            'by_score'  => array_values($byScore),
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // formatVerdict — single source of truth for all AJAX response shapes
    //
    // CTRL-1: 6 new top-level keys exposed from post_trade_notes
    // CTRL-2: sig_c_mode and sig_d_divergence as top-level keys for easy blade use
    // ══════════════════════════════════════════════════════════════════════════

    private function formatVerdict(AuroDailyVerdict $v): array
    {
        // BUG 1: safe parse for sig_e_detail
        $sigEDetail = $this->getSigEDetail($v);

        // BUG 3 / CTRL-1: parse post_trade_notes — engine fields + user notes coexist
        $postNotes = $this->safeJsonDecode($v->post_trade_notes);

        return [
            // ── Core identity ────────────────────────────────────────────
            'id'                   => $v->id,
            'trade_date'           => $v->trade_date?->format('Y-m-d'),
            'expiry_date'          => $v->expiry_date,
            'direction'            => $v->direction,
            'net_score'            => $v->net_score,
            'confidence'           => $v->confidence,
            'atm_strike'           => $v->atm_strike,
            'recommended_strike'   => $v->recommended_strike,
            'recommended_position' => $v->recommended_strike_position,
            'recommended_ltp'      => $v->recommended_strike_ltp,

            // ── Signal A ─────────────────────────────────────────────────
            'sig_a_score'          => $v->sig_a_score,
            'sig_a_verdict'        => $v->sig_a_verdict,

            // ── Signal B ─────────────────────────────────────────────────
            'sig_b_score'          => $v->sig_b_score,
            'sig_b_verdict'        => $v->sig_b_verdict,
            'sig_b_bear_days'      => (int)($v->sig_b_hidden_bear_days ?? 0),
            'sig_b_bull_days'      => (int)($v->sig_b_hidden_bull_days ?? 0),

            // ── Signal C — CTRL-2: mode exposed as top-level key ─────────
            'sig_c_score'          => $v->sig_c_score,
            'sig_c_verdict'        => $v->sig_c_verdict,
            'sig_c_mode'           => $postNotes['sig_c_mode'] ?? 'NORMAL',

            // ── Signal D — CTRL-2: divergence exposed as top-level key ───
            'sig_d_score'          => $v->sig_d_score,
            'sig_d_verdict'        => $v->sig_d_verdict,
            'sig_d_divergence'     => $postNotes['sig_d_divergence'] ?? 0,

            // ── Signal E (BUG 1) ─────────────────────────────────────────
            'sig_e_verdict'        => $v->sig_e_verdict,
            'sig_e_reason'         => $sigEDetail['reason'] ?? null,

            // ── OI data ──────────────────────────────────────────────────
            'ce_oi_pct'            => $v->ce_oi_change_pct,
            'pe_oi_pct'            => $v->pe_oi_change_pct,
            'ce_pe_ratio'          => $v->ce_pe_ratio,
            'fut_oi_type'          => $v->fut_oi_type,

            // ── Price structure ───────────────────────────────────────────
            'support_20d'          => $v->support_20d,
            'resistance_20d'       => $v->resistance_20d,

            // ── Market context ────────────────────────────────────────────
            'market_regime'        => $v->market_regime,
            'vol_5d'               => $v->auro_volatility_5d,
            'nifty_trend'          => $v->nifty_5d_trend,
            'pharma_trend'         => $v->pharma_5d_trend,

            // ── Vetos ────────────────────────────────────────────────────
            'veto_any'             => (bool)$v->any_veto,
            'veto_market'          => (bool)$v->veto_market_opposing,
            'veto_volume'          => (bool)$v->veto_low_volume,
            'veto_expiry'          => (bool)$v->veto_expiry_week,
            'veto_conflict'        => (bool)$v->veto_conflicting_signals,

            // ── CTRL-1: v4 new fields from post_trade_notes ──────────────
            'range_override'       => (bool)($postNotes['range_override']     ?? false),
            'range_high'           => $postNotes['range_high']                ?? null,
            'range_low'            => $postNotes['range_low']                 ?? null,
            'stock_move_day_pct'   => $postNotes['stock_move_day_pct']        ?? null,

            // ── Actual results ────────────────────────────────────────────
            'actual_pnl_pct'       => $v->actual_pnl_pct,
            'was_correct'          => $v->was_correct,
            'miss_reason'          => $v->miss_reason,

            // BUG 3: raw JSON string — blade's parseRisk() will JSON.parse() this
            'post_notes'           => $v->post_trade_notes,

            // ── Meta ──────────────────────────────────────────────────────
            'is_backtest'          => (bool)$v->is_backtest,
            'generated_at'         => $v->generated_at,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Private helpers (unchanged from v3)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * BUG 1: Safe sig_e_detail accessor.
     * Handles both JSON string and already-decoded array gracefully.
     */
    private function getSigEDetail(AuroDailyVerdict $v): array
    {
        $raw = $v->getRawOriginal('sig_e_detail') ?? $v->sig_e_detail;

        if (is_array($raw)) return $raw;

        if (is_string($raw) && !empty($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * BUG 3: Safe JSON decode — always returns array, never crashes.
     */
    private function safeJsonDecode(?string $json): array
    {
        if (empty($json)) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveExpiry(string $date): ?string
    {
        return OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getStrikeInterval(string $expiry): float
    {
        $strikes = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'CE')
            ->whereDate('expiry_date', $expiry)
            ->whereNotNull('strike')
            ->distinct()
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($s) => (float)$s)
            ->values();

        if ($strikes->count() < 2) return 10.0;

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0) $minGap = min($minGap, $gap);
        }

        return $minGap === PHP_INT_MAX ? 10.0 : (float)$minGap;
    }

    private function getOIHistory(int $days): array
    {
        $from = now()->subDays($days)->toDateString();

        $rows = DB::select("
            SELECT
                DATE(trade_date) as d,
                SUM(CASE WHEN instrument_type = 'CE' THEN oi ELSE 0 END) as ce_oi,
                SUM(CASE WHEN instrument_type = 'PE' THEN oi ELSE 0 END) as pe_oi
            FROM option_ohlc_data
            WHERE base_symbol = ?
              AND DATE(trade_date) >= ?
              AND TIME(interval_time) = '14:45:00'
              AND instrument_type IN ('CE','PE')
            GROUP BY DATE(trade_date)
            ORDER BY d ASC
        ", [self::SYMBOL, $from]);

        return [
            'labels' => array_column($rows, 'd'),
            'ce_oi'  => array_map(fn($r) => (int)$r->ce_oi, $rows),
            'pe_oi'  => array_map(fn($r) => (int)$r->pe_oi, $rows),
        ];
    }
}