<?php
// FILE: app/Http/Controllers/User/GapReversalAnalysisController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Advanced Gap-Reversal Analyzer — 15min only (internal — not shown to users)
 *
 * ONE signal per symbol per day (like OI Flow Sentiment) — analyzed at a
 * fixed daily snapshot time, not an intraday replay.
 *
 * Client's flow, implemented in this exact order:
 *   GAP (UP/DOWN)
 *     → Initial Selloff / Initial Rally
 *     → Higher Low / Lower High
 *     → Previous-Day OI Analysis
 *     → Current OI Change
 *     → CE/PE Position Classification (ATM-1 / ATM / ATM+1)
 *     → OI Migration
 *     → OI Wall Movement
 *     → Volume Confirmation
 *     → Opening Range Breakout / Breakdown
 *     → BUY / SELL
 *
 * KEY FIX for "some stocks behave opposite" (client point #4 / the
 * Misleading vs Trend-Following stock lists):
 * Instead of a hardcoded list, each symbol's OI-vs-price POLARITY is
 * learned from its own historical data (computeStockPolarity()) and
 * cached daily. NORMAL stocks use OI signals as-is, INVERTED stocks have
 * their OI-derived score components flipped, UNRELIABLE stocks fall back
 * to price-action only (Initial Move + Reversal + Volume + Opening Range)
 * with a capped max score. This self-corrects as a stock's behaviour
 * drifts — no list to maintain by hand.
 */
class GapReversalAnalysisController extends Controller
{
    private const TF              = '15min';
    private const FUT_TABLE       = 'cp_fut_ohlc_15min';
    private const OPT_TABLE       = 'cp_option_ohlc_15min';

    private const DAY_OPEN_TIME   = '09:15:00';
    private const OR_END_TIME     = '09:30:00';   // opening range = first 2 candles
    private const INITIAL_WINDOW  = 3;             // first 3 candles = 09:15/09:30/09:45 -> "initial move" window
    private const ANALYSIS_TIME   = '14:45:00';   // fixed daily snapshot (same convention as OI Flow Sentiment)
    private const PREV_CLOSE_TIME = '15:00:00';

    private const GAP_THRESHOLD_PCT      = 0.3;
    private const POLARITY_LOOKBACK_DAYS = 20;
    private const POLARITY_MIN_SAMPLES   = 15;
    private const VOLUME_LOOKBACK_DAYS   = 10;

    private const SCORE_BUY_THRESHOLD = 40;

    public function index()
    {
        $pageTitle = 'Advanced Gap-Reversal Analyzer';
        return view(activeTemplate() . 'user.gap-reversal-analysis.index', compact('pageTitle'));
    }

    // -- Last Available Date --------------------------------------------

    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json(['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true]);
            }

            $lastDate = DB::table(self::FUT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->max('trade_date');

            if (!$lastDate) {
                $lastDate = DB::table(self::FUT_TABLE)
                    ->where('analysis_config_id', $config->id)->where('is_missing', false)->max('trade_date');
            }

            $today    = Carbon::today()->toDateString();
            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

            return response()->json(['success' => true, 'last_date' => $lastDate, 'is_today' => $lastDate === $today]);
        } catch (\Exception $e) {
            Log::error('GapReversal lastDate: ' . $e->getMessage());
            return response()->json(['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true]);
        }
    }

    // -- Symbols -----------------------------------------------------------

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json(['success' => true, 'symbols' => [], 'no_config' => true, 'message' => 'No active analysis config found.']);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    // -- Analyze - one signal per symbol for the given date ---------------

    public function analyze(Request $request): JsonResponse
    {
        try {
            $date         = $request->get('date');
            $symbolReq    = array_filter((array) $request->get('symbols', []));
            $actionFilter = $request->get('filter_action', '');

            if (!$date) {
                return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json(['success' => false, 'no_config' => true, 'message' => 'No active Analysis Config found. Go to Admin -> Analysis Config.', 'data' => []]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols  = !empty($symbolReq) ? array_values(array_intersect($symbolReq, $configSymbols)) : $configSymbols;
            $prevDate = $this->getPreviousTradingDate($date);

            $results = [];
            foreach ($symbols as $symbol) {
                $row = $this->analyzeSymbol($symbol, $config->id, $date, $prevDate);
                if (!$row) continue;
                if ($actionFilter && $row['final_action'] !== $actionFilter) continue;
                $results[] = $row;
            }

            usort($results, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'buy_count'         => count(array_filter($results, fn($r) => $r['final_action'] === 'BUY')),
                'sell_count'        => count(array_filter($results, fn($r) => $r['final_action'] === 'SELL')),
                'wait_count'        => count(array_filter($results, fn($r) => $r['final_action'] === 'WAIT')),
                'no_setup_count'    => count(array_filter($results, fn($r) => $r['final_action'] === 'NO SETUP')),
                'message'           => count($results) . ' symbol(s) analyzed for ' . $date . ' (snapshot ' . substr(self::ANALYSIS_TIME, 0, 5) . ')',
                'available_symbols' => $configSymbols,
                'date'              => $date,
                'is_today'          => $date === Carbon::today()->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('GapReversal analyze: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // -----------------------------------------------------------------------
    // Core per-symbol analysis - follows the client's flow in order
    // -----------------------------------------------------------------------

    private function analyzeSymbol(string $symbol, int $configId, string $date, string $prevDate): ?array
    {
        $candles = $this->getDayCandles($symbol, $configId, $date); // already trimmed to <= ANALYSIS_TIME
        if (empty($candles)) return null;
        $last = end($candles);

        // -- GAP --
        $gap = $this->getGapInfo($symbol, $configId, $date, $prevDate);
        if ($gap['gap_type'] === 'NONE') {
            return $this->noSetupRow($symbol, $date, $gap, $last);
        }
        $wantBullish = $gap['gap_type'] === 'GAP_DOWN';

        // -- Initial Selloff / Initial Rally --
        $initialMove = $this->detectInitialMove($candles, $gap['gap_type'], $gap['today_open']);

        // -- Higher Low / Lower High --
        $or       = $this->getOpeningRange($candles);
        $reversal = $this->detectReversalPattern($candles, $gap['gap_type']);

        // -- Previous-Day OI Analysis (ATM) --
        $prevOi = $this->getOiTrend($symbol, $configId, $prevDate, self::DAY_OPEN_TIME, self::PREV_CLOSE_TIME);

        // -- Current OI Change (ATM) --
        $currOi = $this->getOiTrend($symbol, $configId, $date, self::DAY_OPEN_TIME, self::ANALYSIS_TIME);

        // -- CE/PE Position Classification (ATM-1 / ATM / ATM+1) --
        $positions = $this->getPositionBreakdown($symbol, $configId, $date);

        // -- OI Migration + OI Wall Movement --
        $wall = $this->getWallMovement($symbol, $configId, $date);

        // -- Volume Confirmation --
        $volume = $this->getVolumeConfirmation($symbol, $configId, $date);

        // -- Opening Range Breakout / Breakdown --
        $orBreak = $this->getOrBreakout($last, $or, $gap['gap_type']);

        // -- Learned polarity --
        $polarity = $this->computeStockPolarity($symbol, $configId);

        // -- Score -> BUY/SELL/WAIT --
        $scoring = $this->calculateScore($wantBullish, $initialMove, $reversal, $prevOi, $currOi, $positions, $wall, $volume, $orBreak, $polarity);

        return [
            'date'         => $date,
            'symbol'       => $symbol,
            'snapshot'     => substr(self::ANALYSIS_TIME, 0, 5),
            'gap_type'     => $gap['gap_type'],
            'gap_pct'      => $gap['gap_pct'],
            'prev_close'   => $gap['prev_close'],
            'today_open'   => $gap['today_open'],
            'ltp'          => round((float) $last->close, 2),
            'atm_strike'   => $last->atm_strike,
            'or_high'      => $or['high'],
            'or_low'       => $or['low'],
            'initial_move' => $initialMove,
            'reversal'     => $reversal,
            'prev_oi'      => $prevOi,
            'curr_oi'      => $currOi,
            'positions'    => $positions,
            'wall'         => $wall,
            'volume'       => $volume,
            'or_breakout'  => $orBreak,
            'polarity'     => $polarity,
            'score'        => $scoring['score'],
            'max_score'    => $scoring['max_score'],
            'confidence'   => $scoring['confidence'],
            'final_action' => $scoring['action'],
            'breakdown'    => $scoring['breakdown'],
        ];
    }

    private function noSetupRow(string $symbol, string $date, array $gap, object $last): array
    {
        return [
            'date' => $date, 'symbol' => $symbol, 'snapshot' => substr(self::ANALYSIS_TIME, 0, 5),
            'gap_type' => 'NONE', 'gap_pct' => $gap['gap_pct'], 'prev_close' => $gap['prev_close'],
            'today_open' => $gap['today_open'], 'ltp' => round((float) $last->close, 2),
            'atm_strike' => $last->atm_strike, 'or_high' => null, 'or_low' => null,
            'initial_move' => null, 'reversal' => null, 'prev_oi' => null, 'curr_oi' => null,
            'positions' => null, 'wall' => null, 'volume' => null, 'or_breakout' => null, 'polarity' => null,
            'score' => 0, 'max_score' => 0, 'confidence' => 0,
            'final_action' => 'NO SETUP', 'breakdown' => ['No qualifying gap (< ' . self::GAP_THRESHOLD_PCT . '%) - flow does not apply.'],
        ];
    }

    // -----------------------------------------------------------------------
    // GAP
    // -----------------------------------------------------------------------

    private function getGapInfo(string $symbol, int $configId, string $date, string $prevDate): array
    {
        $prevClose = DB::table(self::FUT_TABLE)
            ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
            ->whereDate('trade_date', $prevDate)->where('is_missing', false)
            ->orderByDesc('interval_time')->value('close');

        $todayOpen = DB::table(self::FUT_TABLE)
            ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)->whereRaw('TIME(interval_time) = ?', [self::DAY_OPEN_TIME])
            ->where('is_missing', false)->value('open');

        if (!$prevClose || !$todayOpen) {
            return ['gap_type' => 'NONE', 'gap_pct' => 0, 'prev_close' => $prevClose, 'today_open' => $todayOpen];
        }

        $gapPct  = round((($todayOpen - $prevClose) / $prevClose) * 100, 2);
        $gapType = $gapPct >= self::GAP_THRESHOLD_PCT ? 'GAP_UP'
                 : ($gapPct <= -self::GAP_THRESHOLD_PCT ? 'GAP_DOWN' : 'NONE');

        return ['gap_type' => $gapType, 'gap_pct' => $gapPct, 'prev_close' => round((float) $prevClose, 2), 'today_open' => round((float) $todayOpen, 2)];
    }

    // -----------------------------------------------------------------------
    // Candles / Opening Range
    // -----------------------------------------------------------------------

    private function getDayCandles(string $symbol, int $configId, string $date): array
    {
        return DB::table(self::FUT_TABLE)
            ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)->whereRaw('TIME(interval_time) <= ?', [self::ANALYSIS_TIME])
            ->where('is_missing', false)->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close', 'volume', 'oi', 'atm_strike'])
            ->all();
    }

    private function getOpeningRange(array $candles): array
    {
        $or = array_filter($candles, function ($c) {
            $t = substr($c->interval_time, 11, 5);
            return $t === substr(self::DAY_OPEN_TIME, 0, 5) || $t === substr(self::OR_END_TIME, 0, 5);
        });
        if (empty($or)) return ['high' => null, 'low' => null];

        return [
            'high' => round((float) max(array_map(fn($c) => $c->high, $or)), 2),
            'low'  => round((float) min(array_map(fn($c) => $c->low, $or)), 2),
        ];
    }

    // -----------------------------------------------------------------------
    // STEP - Initial Selloff / Initial Rally
    // -----------------------------------------------------------------------

    private function detectInitialMove(array $candles, string $gapType, ?float $todayOpen): array
    {
        if (!$todayOpen || empty($candles)) {
            return ['label' => null, 'confirmed' => false, 'extreme' => null, 'pct' => 0];
        }

        $window = array_slice($candles, 0, self::INITIAL_WINDOW);

        if ($gapType === 'GAP_DOWN') {
            $minLow    = min(array_map(fn($c) => (float) $c->low, $window));
            $confirmed = $minLow < $todayOpen;
            $pct       = $todayOpen > 0 ? round((($todayOpen - $minLow) / $todayOpen) * 100, 2) : 0;
            return ['label' => 'Initial Selloff', 'confirmed' => $confirmed, 'extreme' => round($minLow, 2), 'pct' => $pct];
        }

        $maxHigh   = max(array_map(fn($c) => (float) $c->high, $window));
        $confirmed = $maxHigh > $todayOpen;
        $pct       = $todayOpen > 0 ? round((($maxHigh - $todayOpen) / $todayOpen) * 100, 2) : 0;
        return ['label' => 'Initial Rally', 'confirmed' => $confirmed, 'extreme' => round($maxHigh, 2), 'pct' => $pct];
    }

    // -----------------------------------------------------------------------
    // STEP - Higher Low / Lower High
    // -----------------------------------------------------------------------

    private function detectReversalPattern(array $candles, string $gapType): array
    {
        if (count($candles) < 2) {
            return ['label' => null, 'confirmed' => false, 'extreme' => null, 'current' => null, 'recovery_pct' => 0];
        }
        $last = end($candles);

        if ($gapType === 'GAP_DOWN') {
            $lows       = array_map(fn($c) => (float) $c->low, $candles);
            $extremeIdx = array_keys($lows, min($lows))[0];
            $extreme    = $lows[$extremeIdx];
            $current    = (float) $last->low;
            $confirmed  = $extremeIdx < count($candles) - 1 && $current > $extreme;
            $recovery   = $extreme > 0 ? round((($current - $extreme) / $extreme) * 100, 2) : 0;
            return ['label' => 'Higher Low', 'confirmed' => $confirmed, 'extreme' => round($extreme, 2), 'current' => round($current, 2), 'recovery_pct' => $recovery];
        }

        $highs      = array_map(fn($c) => (float) $c->high, $candles);
        $extremeIdx = array_keys($highs, max($highs))[0];
        $extreme    = $highs[$extremeIdx];
        $current    = (float) $last->high;
        $confirmed  = $extremeIdx < count($candles) - 1 && $current < $extreme;
        $pullback   = $extreme > 0 ? round((($extreme - $current) / $extreme) * 100, 2) : 0;
        return ['label' => 'Lower High', 'confirmed' => $confirmed, 'extreme' => round($extreme, 2), 'current' => round($current, 2), 'recovery_pct' => $pullback];
    }

    // -----------------------------------------------------------------------
    // STEP - Previous-Day OI Analysis / Current OI Change (ATM)
    // -----------------------------------------------------------------------

    private function getOiTrend(string $symbol, int $configId, string $date, string $fromTime, string $toTime): array
    {
        $rows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)->where('strike_position', 'ATM')
            ->whereIn('instrument_type', ['CE', 'PE'])->where('is_missing', false)
            ->whereRaw('TIME(interval_time) IN (?, ?)', [$fromTime, $toTime])
            ->select(['instrument_type', DB::raw('TIME(interval_time) as t'), DB::raw('SUM(oi) as oi')])
            ->groupBy('instrument_type', 't')->get();

        $ceOpen = $peOpen = $ceNow = $peNow = 0;
        foreach ($rows as $r) {
            if ($r->t === $fromTime && $r->instrument_type === 'CE') $ceOpen = (int) $r->oi;
            if ($r->t === $fromTime && $r->instrument_type === 'PE') $peOpen = (int) $r->oi;
            if ($r->t === $toTime   && $r->instrument_type === 'CE') $ceNow  = (int) $r->oi;
            if ($r->t === $toTime   && $r->instrument_type === 'PE') $peNow  = (int) $r->oi;
        }

        $cePct = $ceOpen > 0 ? round((($ceNow - $ceOpen) / $ceOpen) * 100, 2) : 0;
        $pePct = $peOpen > 0 ? round((($peNow - $peOpen) / $peOpen) * 100, 2) : 0;

        return [
            'ce_open' => $ceOpen, 'pe_open' => $peOpen, 'ce_now' => $ceNow, 'pe_now' => $peNow,
            'ce_pct' => $cePct, 'pe_pct' => $pePct,
            'ce_trend' => $this->buildupTag($ceOpen, $ceNow), 'pe_trend' => $this->buildupTag($peOpen, $peNow),
            'signal' => $this->textbookOiSignal($cePct, $pePct),
        ];
    }

    private function buildupTag(?float $openOi, ?float $closeOi): string
    {
        if (!$openOi || !$closeOi || $openOi <= 0) return 'Flat';
        $chg = (($closeOi - $openOi) / $openOi) * 100;
        return $chg > 1 ? 'Buildup' : ($chg < -1 ? 'Unwinding' : 'Flat');
    }

    /** Textbook OI theory: CE up + PE down = Bearish (resistance), CE down + PE up = Bullish (support). */
    private function textbookOiSignal(float $cePct, float $pePct): string
    {
        $ceUp = $cePct > 0.5; $ceDown = $cePct < -0.5;
        $peUp = $pePct > 0.5; $peDown = $pePct < -0.5;

        if ($ceUp && $peDown) return 'BEARISH';
        if ($ceDown && $peUp) return 'BULLISH';
        if ($ceUp && $peUp)   return $cePct > $pePct ? 'BEARISH' : 'BULLISH';
        if ($ceDown && $peDown) return $cePct < $pePct ? 'BULLISH' : 'BEARISH';
        return 'NEUTRAL';
    }

    /** Same textbook logic, but from Buildup/Unwinding/Flat tags - used for per-strike position classification. */
    private function positionSignal(string $ceTrend, string $peTrend): string
    {
        if ($ceTrend === 'Buildup'   && $peTrend === 'Unwinding') return 'BEARISH';
        if ($ceTrend === 'Unwinding' && $peTrend === 'Buildup')   return 'BULLISH';
        if ($ceTrend === 'Unwinding' && $peTrend === 'Flat')      return 'BULLISH';
        if ($ceTrend === 'Flat'      && $peTrend === 'Buildup')   return 'BULLISH';
        if ($ceTrend === 'Buildup'   && $peTrend === 'Flat')      return 'BEARISH';
        if ($ceTrend === 'Flat'      && $peTrend === 'Unwinding') return 'BEARISH';
        return 'NEUTRAL';
    }

    // -----------------------------------------------------------------------
    // STEP - CE/PE Position Classification (ATM-1 / ATM / ATM+1)
    // -----------------------------------------------------------------------

    private function getPositionBreakdown(string $symbol, int $configId, string $date): array
    {
        $positions = ['ATM-1', 'ATM', 'ATM+1'];
        $out = [];
        foreach ($positions as $pos) {
            $rows = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                ->whereDate('trade_date', $date)->where('strike_position', $pos)
                ->whereIn('instrument_type', ['CE', 'PE'])->where('is_missing', false)
                ->whereRaw('TIME(interval_time) IN (?, ?)', [self::DAY_OPEN_TIME, self::ANALYSIS_TIME])
                ->select(['instrument_type', DB::raw('TIME(interval_time) as t'), DB::raw('SUM(oi) as oi')])
                ->groupBy('instrument_type', 't')->get();

            $ceOpen = $peOpen = $ceNow = $peNow = 0;
            foreach ($rows as $r) {
                if ($r->t === self::DAY_OPEN_TIME && $r->instrument_type === 'CE') $ceOpen = (int) $r->oi;
                if ($r->t === self::DAY_OPEN_TIME && $r->instrument_type === 'PE') $peOpen = (int) $r->oi;
                if ($r->t === self::ANALYSIS_TIME && $r->instrument_type === 'CE') $ceNow = (int) $r->oi;
                if ($r->t === self::ANALYSIS_TIME && $r->instrument_type === 'PE') $peNow = (int) $r->oi;
            }
            $ceTrend = $this->buildupTag($ceOpen, $ceNow);
            $peTrend = $this->buildupTag($peOpen, $peNow);
            $out[$pos] = ['ce_trend' => $ceTrend, 'pe_trend' => $peTrend, 'signal' => $this->positionSignal($ceTrend, $peTrend)];
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // STEP - OI Migration + OI Wall Movement (max-OI strike, full chain)
    // -----------------------------------------------------------------------

    private function getOiWallAt(string $symbol, int $configId, string $date, string $time): array
    {
        $rows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)->whereRaw('TIME(interval_time) = ?', [$time])
            ->whereIn('instrument_type', ['CE', 'PE'])->where('is_missing', false)
            ->select(['instrument_type', 'strike', DB::raw('SUM(oi) as oi')])
            ->groupBy('instrument_type', 'strike')->get();

        $ce = $rows->where('instrument_type', 'CE')->sortByDesc('oi')->first();
        $pe = $rows->where('instrument_type', 'PE')->sortByDesc('oi')->first();

        return ['ce_wall_strike' => $ce->strike ?? null, 'pe_wall_strike' => $pe->strike ?? null];
    }

    private function getWallMovement(string $symbol, int $configId, string $date): array
    {
        $open = $this->getOiWallAt($symbol, $configId, $date, self::DAY_OPEN_TIME);
        $now  = $this->getOiWallAt($symbol, $configId, $date, self::ANALYSIS_TIME);

        return [
            'ce_wall_open' => $open['ce_wall_strike'], 'ce_wall_now' => $now['ce_wall_strike'],
            'ce_wall_dir'  => $this->wallDirection($open['ce_wall_strike'], $now['ce_wall_strike']),
            'pe_wall_open' => $open['pe_wall_strike'], 'pe_wall_now' => $now['pe_wall_strike'],
            'pe_wall_dir'  => $this->wallDirection($open['pe_wall_strike'], $now['pe_wall_strike']),
        ];
    }

    private function wallDirection($openStrike, $nowStrike): string
    {
        if ($openStrike === null || $nowStrike === null) return 'FLAT';
        if ($nowStrike > $openStrike) return 'UP';
        if ($nowStrike < $openStrike) return 'DOWN';
        return 'FLAT';
    }

    // -----------------------------------------------------------------------
    // STEP - Volume Confirmation (vs N-day average up to the same snapshot time)
    // -----------------------------------------------------------------------

    private function getVolumeConfirmation(string $symbol, int $configId, string $date): array
    {
        $todayVol = (int) DB::table(self::FUT_TABLE)
            ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)->whereRaw('TIME(interval_time) <= ?', [self::ANALYSIS_TIME])
            ->where('is_missing', false)->sum('volume');

        $pastDates = DB::table(self::FUT_TABLE)
            ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
            ->where('trade_date', '<', $date)->where('is_missing', false)
            ->select(DB::raw('DISTINCT DATE(trade_date) as d'))->orderByDesc('d')
            ->limit(self::VOLUME_LOOKBACK_DAYS)->pluck('d')->toArray();

        $avgVol = 0;
        if (!empty($pastDates)) {
            $avgVol = (int) round(
                DB::table(self::FUT_TABLE)
                    ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                    ->whereIn(DB::raw('DATE(trade_date)'), $pastDates)
                    ->whereRaw('TIME(interval_time) <= ?', [self::ANALYSIS_TIME])
                    ->where('is_missing', false)->sum('volume') / count($pastDates)
            );
        }

        $ratio     = $avgVol > 0 ? round($todayVol / $avgVol, 2) : 0;
        $confirmed = $ratio >= 1.2;

        return ['today_volume' => $todayVol, 'avg_volume' => $avgVol, 'ratio' => $ratio, 'confirmed' => $confirmed];
    }

    // -----------------------------------------------------------------------
    // STEP - Opening Range Breakout / Breakdown
    // -----------------------------------------------------------------------

    private function getOrBreakout(object $last, array $or, string $gapType): array
    {
        $close = (float) $last->close;
        if ($gapType === 'GAP_DOWN') {
            $confirmed = $or['high'] !== null && $close > $or['high'];
            return ['type' => 'Breakout', 'level' => $or['high'], 'ltp' => round($close, 2), 'confirmed' => $confirmed];
        }
        $confirmed = $or['low'] !== null && $close < $or['low'];
        return ['type' => 'Breakdown', 'level' => $or['low'], 'ltp' => round($close, 2), 'confirmed' => $confirmed];
    }

    // -----------------------------------------------------------------------
    // Learned polarity - replaces the client's static "misleading stocks" list
    // -----------------------------------------------------------------------

    private function computeStockPolarity(string $symbol, int $configId): array
    {
        return Cache::remember("gap_rev_polarity_{$configId}_{$symbol}", now()->addHours(24), function () use ($symbol, $configId) {
            $dates = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                ->where('is_missing', false)
                ->select(DB::raw('DISTINCT DATE(trade_date) as d'))
                ->orderByDesc('d')->limit(self::POLARITY_LOOKBACK_DAYS)->pluck('d')->toArray();

            $matches = 0; $mismatches = 0;

            foreach ($dates as $date) {
                $oiRows = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $date)->where('strike_position', 'ATM')
                    ->whereIn('instrument_type', ['CE', 'PE'])->where('is_missing', false)
                    ->select(['instrument_type', DB::raw('TIME(interval_time) as t'), DB::raw('SUM(oi) as oi')])
                    ->groupBy('instrument_type', 't')->orderBy('t')->get()
                    ->groupBy('t');

                $priceRows = DB::table(self::FUT_TABLE)
                    ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $date)->where('is_missing', false)
                    ->orderBy('interval_time')->get(['interval_time', 'close'])
                    ->keyBy(fn($r) => substr($r->interval_time, 11, 8));

                $times = $oiRows->keys()->sort()->values()->all();
                for ($i = 1; $i < count($times); $i++) {
                    $prevT = $times[$i - 1]; $curT = $times[$i];
                    $ceP = optional($oiRows[$prevT]->firstWhere('instrument_type', 'CE'))->oi ?? 0;
                    $peP = optional($oiRows[$prevT]->firstWhere('instrument_type', 'PE'))->oi ?? 0;
                    $ceC = optional($oiRows[$curT]->firstWhere('instrument_type', 'CE'))->oi ?? 0;
                    $peC = optional($oiRows[$curT]->firstWhere('instrument_type', 'PE'))->oi ?? 0;

                    $cePct = $ceP > 0 ? (($ceC - $ceP) / $ceP) * 100 : 0;
                    $pePct = $peP > 0 ? (($peC - $peP) / $peP) * 100 : 0;
                    $signal = $this->textbookOiSignal($cePct, $pePct);
                    if ($signal === 'NEUTRAL') continue;

                    $priceBefore = $priceRows[$prevT]->close ?? null;
                    $priceAfter  = $priceRows[$curT]->close ?? null;
                    if ($priceBefore === null || $priceAfter === null) continue;

                    $priceChg = $priceAfter - $priceBefore;
                    if ($priceChg == 0) continue;

                    $matched = ($signal === 'BULLISH' && $priceChg > 0) || ($signal === 'BEARISH' && $priceChg < 0);
                    $matched ? $matches++ : $mismatches++;
                }
            }

            $sample = $matches + $mismatches;
            if ($sample < self::POLARITY_MIN_SAMPLES) {
                return ['polarity' => 'UNRELIABLE', 'match_rate' => null, 'sample_size' => $sample];
            }

            $rate = round(($matches / $sample) * 100, 1);
            $polarity = $rate >= 55 ? 'NORMAL' : ($rate <= 45 ? 'INVERTED' : 'UNRELIABLE');

            return ['polarity' => $polarity, 'match_rate' => $rate, 'sample_size' => $sample];
        });
    }

    // -----------------------------------------------------------------------
    // Scoring - combines every step in the client's flow, applies polarity
    // -----------------------------------------------------------------------

    private function calculateScore(bool $wantBullish, array $initialMove, array $reversal, array $prevOi, array $currOi, array $positions, array $wall, array $volume, array $orBreak, array $polarity): array
    {
        $breakdown = [];
        $score = 0; $maxScore = 0;

        // -- Price-action steps - always scored, independent of OI polarity --
        $maxScore += 10;
        if ($initialMove['confirmed']) { $score += 10; $breakdown[] = "OK {$initialMove['label']} confirmed - extended to {$initialMove['extreme']} ({$initialMove['pct']}%) (+10)"; }
        else { $breakdown[] = "NO {$initialMove['label']} not confirmed (0)"; }

        $maxScore += 20;
        if ($reversal['confirmed']) { $score += 20; $breakdown[] = "OK {$reversal['label']} confirmed - {$reversal['extreme']} -> {$reversal['current']} (+20)"; }
        else { $breakdown[] = "NO {$reversal['label']} not confirmed (0)"; }

        $maxScore += 10;
        if ($volume['confirmed']) { $score += 10; $breakdown[] = "OK Volume Confirmation - {$volume['ratio']}x average (+10)"; }
        else { $breakdown[] = "NO Volume Confirmation not met - {$volume['ratio']}x average (0)"; }

        $maxScore += 10;
        if ($orBreak['confirmed']) { $score += 10; $breakdown[] = "OK Opening Range {$orBreak['type']} confirmed (+10)"; }
        else { $breakdown[] = "NO Opening Range {$orBreak['type']} not confirmed (0)"; }

        // -- OI-derived steps - weight 0 if this stock's OI is UNRELIABLE --
        $oiUsable = $polarity['polarity'] !== 'UNRELIABLE';
        $invert   = $polarity['polarity'] === 'INVERTED';
        $wantSignal = $wantBullish ? 'BULLISH' : 'BEARISH';
        $oppSignal  = $wantBullish ? 'BEARISH' : 'BULLISH';

        if ($oiUsable) {
            $maxScore += 15;
            [$aligned, $opposed] = $this->alignedOpposed($prevOi['signal'], $wantSignal, $oppSignal, $invert);
            if ($aligned) { $score += 15; $breakdown[] = "OK Previous-Day OI Analysis supports thesis ({$prevOi['ce_trend']} CE / {$prevOi['pe_trend']} PE) (+15)"; }
            elseif ($opposed) { $score -= 15; $breakdown[] = "NO Previous-Day OI Analysis contradicts thesis ({$prevOi['ce_trend']} CE / {$prevOi['pe_trend']} PE) (-15)"; }
            else { $breakdown[] = "-- Previous-Day OI Analysis neutral (0)"; }

            $maxScore += 15;
            [$aligned, $opposed] = $this->alignedOpposed($currOi['signal'], $wantSignal, $oppSignal, $invert);
            if ($aligned) { $score += 15; $breakdown[] = "OK Current OI Change supports thesis (CE {$currOi['ce_pct']}% / PE {$currOi['pe_pct']}%) (+15)"; }
            elseif ($opposed) { $score -= 15; $breakdown[] = "NO Current OI Change contradicts thesis (CE {$currOi['ce_pct']}% / PE {$currOi['pe_pct']}%) (-15)"; }
            else { $breakdown[] = "-- Current OI Change neutral (0)"; }

            $maxScore += 10;
            $bull = 0; $bear = 0;
            foreach ($positions as $p) { if ($p['signal'] === 'BULLISH') $bull++; if ($p['signal'] === 'BEARISH') $bear++; }
            $majority = $bull > $bear ? 'BULLISH' : ($bear > $bull ? 'BEARISH' : 'NEUTRAL');
            [$aligned, $opposed] = $this->alignedOpposed($majority, $wantSignal, $oppSignal, $invert);
            if ($aligned) { $score += 10; $breakdown[] = "OK CE/PE Position Classification aligned across ATM-1/ATM/ATM+1 (+10)"; }
            elseif ($opposed) { $score -= 10; $breakdown[] = "NO CE/PE Position Classification opposed across ATM-1/ATM/ATM+1 (-10)"; }
            else { $breakdown[] = "-- CE/PE Position Classification mixed (0)"; }

            $maxScore += 10;
            $peUp = $wall['pe_wall_dir'] === 'UP'; $ceUp = $wall['ce_wall_dir'] === 'UP';
            $ceDown = $wall['ce_wall_dir'] === 'DOWN'; $peDown = $wall['pe_wall_dir'] === 'DOWN';
            $wallFavorsBull = $peUp || $ceUp;
            $wallFavorsBear = $ceDown || $peDown;
            $wallSignal = $wallFavorsBull && !$wallFavorsBear ? 'BULLISH' : ($wallFavorsBear && !$wallFavorsBull ? 'BEARISH' : 'NEUTRAL');
            [$aligned, $opposed] = $this->alignedOpposed($wallSignal, $wantSignal, $oppSignal, $invert);
            if ($aligned) { $score += 10; $breakdown[] = "OK OI Migration / Wall Movement favorable (CE wall {$wall['ce_wall_dir']}, PE wall {$wall['pe_wall_dir']}) (+10)"; }
            elseif ($opposed) { $score -= 10; $breakdown[] = "NO OI Migration / Wall Movement unfavorable (CE wall {$wall['ce_wall_dir']}, PE wall {$wall['pe_wall_dir']}) (-10)"; }
            else { $breakdown[] = "-- OI Migration / Wall Movement flat (0)"; }
        } else {
            $breakdown[] = "WARN OI signals unreliable for this stock (match rate ~50% over {$polarity['sample_size']} samples) - scored on price action only";
        }

        if ($invert) {
            array_unshift($breakdown, "INV Stock polarity: INVERTED (OI match rate {$polarity['match_rate']}%) - OI-derived steps flipped");
        } elseif ($polarity['polarity'] === 'NORMAL') {
            array_unshift($breakdown, "OK Stock polarity: NORMAL (OI match rate {$polarity['match_rate']}%)");
        }

        $confidence = $maxScore > 0 ? round((abs($score) / $maxScore) * 100, 1) : 0;
        $action = 'WAIT';
        if ($score >= self::SCORE_BUY_THRESHOLD) $action = $wantBullish ? 'BUY' : 'SELL';

        return ['score' => $score, 'max_score' => $maxScore, 'confidence' => $confidence, 'action' => $action, 'breakdown' => $breakdown];
    }

    private function alignedOpposed(string $signal, string $wantSignal, string $oppSignal, bool $invert): array
    {
        if ($invert) { [$wantSignal, $oppSignal] = [$oppSignal, $wantSignal]; }
        return [$signal === $wantSignal, $signal === $oppSignal];
    }

    // -----------------------------------------------------------------------
    // Config helpers
    // -----------------------------------------------------------------------

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', self::TF)->where('is_active', 1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')->toArray();
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