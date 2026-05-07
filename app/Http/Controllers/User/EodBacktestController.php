<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * EodBacktestController — 3-Mode Edition
 *
 * CORE WIN LOGIC (same for all 3 modes):
 * ─────────────────────────────────────────────────────────────────────
 * 1. Signal date → get exact strike + entry_price from EodSignalController
 * 2. Next day → fetch that strike's option OHLC candles
 * 3. Scan each candle HIGH:
 *    WIN  = any candle HIGH > entry_price
 *    LOSS = no candle HIGH ever crossed entry_price
 *    max_move_pct = (maxHigh - entry) / entry * 100
 *    max_loss_pct = (minLow  - entry) / entry * 100
 *
 * MODE 1 — NORMAL:
 *   Follow signal. BUY_CE → check CE strike. BUY_PE → check PE strike.
 *
 * MODE 2 — CONTRA:
 *   Flip option type. BUY_CE signal → buy PE instead (ATM PE strike).
 *   Tests whether opposite direction beats the signal.
 *
 * MODE 3 — BOTH (Straddle):
 *   Buy CE + PE simultaneously at ATM.
 *   WIN = either leg HIGH > its entry. LOSS = neither crossed.
 *   Shows each leg separately + combined result.
 */
class EodBacktestController extends Controller
{
    private EodSignalController $engine;

    public function __construct(EodSignalController $engine)
    {
        parent::__construct();
        $this->engine = $engine;
    }

    public function index()
    {
        $pageTitle = 'EOD Backtest';
        return view($this->activeTemplate . 'user.eod-backtest.index', compact('pageTitle'));
    }

    public function getAvailableDates(): \Illuminate\Http\JsonResponse
    {
        try {
            $dates = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')
                ->pluck('d')->map(fn($d) => (string)$d)->toArray();
            return response()->json(['success' => true, 'dates' => $dates]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'dates' => []]);
        }
    }

    // =========================================================================
    // MODE 1: NORMAL
    // =========================================================================
    public function runForDate(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            [$symbols, $available, $signalDate, $prevDate, $nextDate] = $this->setup($request);
            if (empty($symbols)) return $this->emptyResponse($signalDate, $nextDate, $available);

            $results = [];
            foreach ($symbols as $sym) {
                $analysis = $this->engine->analyseSymbol($sym, $signalDate, $prevDate, $nextDate, true);
                if (!$analysis || !empty($analysis['data_incomplete'])) continue;

                $order = $analysis['order'] ?? null;
                if (!$order || empty($order['strike']) || empty($order['entry_price'])) {
                    $results[] = $this->noOrderRow($sym, $signalDate, $nextDate, $analysis);
                    continue;
                }

                $leg = $this->checkLeg(
                    $sym, $nextDate,
                    $order['expiry'] ?? $analysis['expiry'],
                    (float)$order['strike'],
                    (string)$order['option_type'],
                    (float)$order['entry_price']
                );

                $results[] = $this->buildRow($analysis, $sym, $signalDate, $nextDate, 'normal', [
                    'traded_option_type' => $order['option_type'],
                    'traded_strike'      => (float)$order['strike'],
                    'traded_entry_price' => (float)$order['entry_price'],
                ] + $leg);
            }

            usort($results, $this->sorter());
            return $this->jsonResponse($results, $signalDate, $nextDate, $available, 'normal');

        } catch (\Exception $e) {
            Log::error('Backtest normal: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================================
    // MODE 2: CONTRA
    // =========================================================================
    public function runContra(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            [$symbols, $available, $signalDate, $prevDate, $nextDate] = $this->setup($request);
            if (empty($symbols)) return $this->emptyResponse($signalDate, $nextDate, $available);

            $results = [];
            foreach ($symbols as $sym) {
                $analysis = $this->engine->analyseSymbol($sym, $signalDate, $prevDate, $nextDate, true);
                if (!$analysis || !empty($analysis['data_incomplete'])) continue;

                $order = $analysis['order'] ?? null;
                if (!$order || empty($order['strike'])) {
                    $results[] = $this->noOrderRow($sym, $signalDate, $nextDate, $analysis);
                    continue;
                }

                // Flip the option type
                $signalType  = (string)$order['option_type'];
                $contraType  = $signalType === 'CE' ? 'PE' : 'CE';
                $expiry      = $order['expiry'] ?? $analysis['expiry'];
                $atmStrike   = (float)($analysis['atm_strike'] ?? $order['strike']);
                $interval    = (float)($analysis['strike_interval'] ?? 50);

                [$contraStrike, $contraEntry] = $this->pickBestStrike(
                    $sym, $signalDate, $expiry, $contraType, $atmStrike, $interval
                );

                if (!$contraStrike || !$contraEntry) {
                    $results[] = $this->noOrderRow($sym, $signalDate, $nextDate, $analysis);
                    continue;
                }

                $leg = $this->checkLeg($sym, $nextDate, $expiry, $contraStrike, $contraType, $contraEntry);

                $results[] = $this->buildRow($analysis, $sym, $signalDate, $nextDate, 'contra', [
                    'original_signal_type' => $signalType,
                    'original_strike'      => (float)$order['strike'],
                    'traded_option_type'   => $contraType,
                    'traded_strike'        => $contraStrike,
                    'traded_entry_price'   => $contraEntry,
                ] + $leg);
            }

            usort($results, $this->sorter());
            return $this->jsonResponse($results, $signalDate, $nextDate, $available, 'contra');

        } catch (\Exception $e) {
            Log::error('Backtest contra: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================================
    // MODE 3: BOTH (straddle)
    // =========================================================================
    public function runBoth(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            [$symbols, $available, $signalDate, $prevDate, $nextDate] = $this->setup($request);
            if (empty($symbols)) return $this->emptyResponse($signalDate, $nextDate, $available);

            $results = [];
            foreach ($symbols as $sym) {
                $analysis = $this->engine->analyseSymbol($sym, $signalDate, $prevDate, $nextDate, true);
                if (!$analysis || !empty($analysis['data_incomplete'])) continue;

                $order = $analysis['order'] ?? null;
                if (!$order || empty($order['strike'])) {
                    $results[] = $this->noOrderRow($sym, $signalDate, $nextDate, $analysis);
                    continue;
                }

                $expiry    = $order['expiry'] ?? $analysis['expiry'];
                $atmStrike = (float)($analysis['atm_strike'] ?? $order['strike']);
                $interval  = (float)($analysis['strike_interval'] ?? 50);
                $signalType = (string)$order['option_type'];
                $otherType  = $signalType === 'CE' ? 'PE' : 'CE';

                // Signal leg — use order block strike + entry
                $signalStrike = (float)$order['strike'];
                $signalEntry  = (float)$order['entry_price'];

                // Other leg — pick best strike for opposite type
                [$otherStrike, $otherEntry] = $this->pickBestStrike(
                    $sym, $signalDate, $expiry, $otherType, $atmStrike, $interval
                );

                // Check both legs
                $signalLeg = $this->checkLeg($sym, $nextDate, $expiry, $signalStrike, $signalType, $signalEntry);
                $otherLeg  = ($otherStrike && $otherEntry)
                    ? $this->checkLeg($sym, $nextDate, $expiry, $otherStrike, $otherType, $otherEntry)
                    : ['outcome' => 'NO_DATA', 'max_move_pct' => null, 'max_loss_pct' => null,
                       'max_high' => null, 'min_low' => null, 'candles_above' => 0,
                       'candles_total' => 0, 'first_candle_above' => null,
                       'max_move_candle' => null, 'candles' => []];

                // Map to CE/PE
                $ceIsSignal = $signalType === 'CE';
                $ceLeg      = $ceIsSignal ? $signalLeg : $otherLeg;
                $peLeg      = $ceIsSignal ? $otherLeg  : $signalLeg;
                $ceStrike   = $ceIsSignal ? $signalStrike : ($otherStrike ?? null);
                $peStrike   = $ceIsSignal ? ($otherStrike ?? null) : $signalStrike;
                $ceEntry    = $ceIsSignal ? $signalEntry  : ($otherEntry ?? null);
                $peEntry    = $ceIsSignal ? ($otherEntry ?? null) : $signalEntry;

                $ceOut = $ceLeg['outcome'] ?? 'NO_DATA';
                $peOut = $peLeg['outcome'] ?? 'NO_DATA';

                // Combined: WIN = either leg crossed its entry
                $combinedOutcome = match(true) {
                    $ceOut === 'WIN' || $peOut === 'WIN'             => 'WIN',
                    $ceOut === 'PENDING' || $peOut === 'PENDING'     => 'PENDING',
                    $ceOut === 'NO_DATA' && $peOut === 'NO_DATA'     => 'NO_DATA',
                    default                                          => 'LOSS',
                };

                $bestMove = max($ceLeg['max_move_pct'] ?? 0, $peLeg['max_move_pct'] ?? 0);

                $results[] = $this->buildRow($analysis, $sym, $signalDate, $nextDate, 'both', [
                    'outcome'          => $combinedOutcome,
                    'best_move_pct'    => $bestMove,
                    'best_leg'         => ($ceLeg['max_move_pct'] ?? -999) >= ($peLeg['max_move_pct'] ?? -999) ? 'CE' : 'PE',
                    // CE leg
                    'ce_strike'        => $ceStrike,
                    'ce_entry_price'   => $ceEntry,
                    'ce_outcome'       => $ceOut,
                    'ce_max_move_pct'  => $ceLeg['max_move_pct']  ?? null,
                    'ce_max_loss_pct'  => $ceLeg['max_loss_pct']  ?? null,
                    'ce_max_high'      => $ceLeg['max_high']      ?? null,
                    'ce_candles_above' => $ceLeg['candles_above'] ?? 0,
                    'ce_candles_total' => $ceLeg['candles_total'] ?? 0,
                    'ce_first_above'   => $ceLeg['first_candle_above'] ?? null,
                    'ce_max_candle'    => $ceLeg['max_move_candle']   ?? null,
                    'ce_candles'       => $ceLeg['candles'] ?? [],
                    // PE leg
                    'pe_strike'        => $peStrike,
                    'pe_entry_price'   => $peEntry,
                    'pe_outcome'       => $peOut,
                    'pe_max_move_pct'  => $peLeg['max_move_pct']  ?? null,
                    'pe_max_loss_pct'  => $peLeg['max_loss_pct']  ?? null,
                    'pe_max_high'      => $peLeg['max_high']      ?? null,
                    'pe_candles_above' => $peLeg['candles_above'] ?? 0,
                    'pe_candles_total' => $peLeg['candles_total'] ?? 0,
                    'pe_first_above'   => $peLeg['first_candle_above'] ?? null,
                    'pe_max_candle'    => $peLeg['max_move_candle']   ?? null,
                    'pe_candles'       => $peLeg['candles'] ?? [],
                ]);
            }

            usort($results, $this->sorter());
            return $this->jsonResponse($results, $signalDate, $nextDate, $available, 'both');

        } catch (\Exception $e) {
            Log::error('Backtest both: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================================
    // CORE: CHECK ONE OPTION LEG — candle HIGH vs entry price
    // =========================================================================
    private function checkLeg(
        string  $sym,
        ?string $tradeDate,
        ?string $expiry,
        float   $strike,
        string  $optionType,
        float   $entryPrice
    ): array {
        $empty = [
            'outcome' => 'PENDING', 'max_move_pct' => null, 'max_loss_pct' => null,
            'max_high' => null, 'min_low' => null, 'candles_above' => 0,
            'candles_total' => 0, 'first_candle_above' => null,
            'max_move_candle' => null, 'day_open' => null, 'day_close' => null, 'candles' => [],
        ];

        if (!$tradeDate)        return $empty;
        if ($entryPrice <= 0)   return array_merge($empty, ['outcome' => 'NO_DATA']);

        // Fetch next-day candles for this exact strike
        $q = OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $optionType)
            ->where('strike', $strike)
            ->whereDate('trade_date', $tradeDate)
            ->where('is_missing', 0)
            ->orderBy('interval_time');
        if ($expiry) $q->whereDate('expiry_date', $expiry);
        $candles = $q->get(['interval_time', 'open', 'high', 'low', 'close', 'volume']);

        // Fallback without expiry filter
        if ($candles->isEmpty() && $expiry) {
            $candles = OptionOhlcData::where('base_symbol', $sym)
                ->where('instrument_type', $optionType)
                ->where('strike', $strike)
                ->whereDate('trade_date', $tradeDate)
                ->where('is_missing', 0)
                ->orderBy('interval_time')
                ->get(['interval_time', 'open', 'high', 'low', 'close', 'volume']);
        }
        if ($candles->isEmpty()) return array_merge($empty, ['outcome' => 'NO_DATA']);

        $candleData = [];
        $candlesAbove = 0;
        $maxHigh = 0.0;
        $minLow  = PHP_FLOAT_MAX;
        $firstCandleAbove = null;
        $maxMoveCandle    = null;

        foreach ($candles as $c) {
            $time  = Carbon::parse($c->interval_time)->format('H:i');
            $high  = (float)$c->high;
            $low   = (float)$c->low;
            $open  = (float)$c->open;
            $close = (float)$c->close;

            $aboveEntry   = $high > $entryPrice;
            $highMovePct  = round((($high  - $entryPrice) / $entryPrice) * 100, 2);
            $lowMovePct   = round((($low   - $entryPrice) / $entryPrice) * 100, 2);
            $closeMovePct = round((($close - $entryPrice) / $entryPrice) * 100, 2);

            if ($aboveEntry) { $candlesAbove++; if (!$firstCandleAbove) $firstCandleAbove = $time; }
            if ($high > $maxHigh) { $maxHigh = $high; $maxMoveCandle = $time; }
            if ($low  < $minLow)  { $minLow  = $low; }

            $candleData[] = [
                'time'           => $time,
                'open'           => $open,
                'high'           => $high,
                'low'            => $low,
                'close'          => $close,
                'volume'         => (int)$c->volume,
                'above_entry'    => $aboveEntry,
                'high_move_pct'  => $highMovePct,
                'low_move_pct'   => $lowMovePct,
                'close_move_pct' => $closeMovePct,
            ];
        }

        $minLowFinal = $minLow < PHP_FLOAT_MAX ? $minLow : 0;

        return [
            'outcome'            => $candlesAbove > 0 ? 'WIN' : 'LOSS',
            'max_move_pct'       => round((($maxHigh      - $entryPrice) / $entryPrice) * 100, 2),
            'max_loss_pct'       => round((($minLowFinal  - $entryPrice) / $entryPrice) * 100, 2),
            'max_high'           => round($maxHigh, 2),
            'min_low'            => round($minLowFinal, 2),
            'candles_above'      => $candlesAbove,
            'candles_total'      => count($candleData),
            'first_candle_above' => $firstCandleAbove,
            'max_move_candle'    => $maxMoveCandle,
            'day_open'           => round((float)($candles->first()->open  ?? 0), 2),
            'day_close'          => round((float)($candles->last()->close  ?? 0), 2),
            'candles'            => $candleData,
        ];
    }

    // =========================================================================
    // Pick highest-volume strike for a given option type at signal date EOD
    // =========================================================================
    private function pickBestStrike(
        string $sym, string $signalDate, ?string $expiry,
        string $optionType, float $atmStrike, float $interval
    ): array {
        $candidates = [$atmStrike - $interval, $atmStrike, $atmStrike + $interval];

        $q = OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $signalDate)
            ->whereIn('strike', $candidates)
            ->whereTime('interval_time', '=', '14:45:00')
            ->where('is_missing', 0)
            ->orderByDesc('volume');
        if ($expiry) $q->whereDate('expiry_date', $expiry);

        $best = $q->first(['strike', 'close']);

        if ($best && (float)$best->close > 0) {
            return [(float)$best->strike, (float)$best->close];
        }

        // Fallback: ATM without time filter
        $atm = OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $signalDate)
            ->where('strike', $atmStrike)
            ->where('is_missing', 0)
            ->orderByDesc('interval_time')
            ->first(['strike', 'close']);

        if ($atm && (float)$atm->close > 0) {
            return [(float)$atm->strike, (float)$atm->close];
        }

        return [null, null];
    }

    // =========================================================================
    // SHARED SETUP
    // =========================================================================
    private function setup(Request $request): array
    {
        $signalDate = Carbon::parse($request->get('date', Carbon::yesterday()->toDateString()))->toDateString();
        $symbol     = strtoupper(trim($request->get('symbol', 'ALL')));

        $allDates = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereBetween(DB::raw('DATE(trade_date)'), [
                Carbon::parse($signalDate)->subDays(10)->toDateString(),
                Carbon::parse($signalDate)->addDays(10)->toDateString(),
            ])
            ->select(DB::raw('DATE(trade_date) as d'))
            ->distinct()->orderBy('d')
            ->pluck('d')->map(fn($d) => (string)$d)->toArray();

        $idx      = array_search($signalDate, $allDates);
        $prevDate = ($idx !== false && $idx > 0)                   ? $allDates[$idx - 1] : null;
        $nextDate = ($idx !== false && isset($allDates[$idx + 1])) ? $allDates[$idx + 1] : null;

        $available = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $signalDate)
            ->whereNotNull('base_symbol')
            ->distinct()->orderBy('base_symbol')
            ->pluck('base_symbol')->toArray();

        $symbols = ($symbol === 'ALL') ? $available : [$symbol];
        return [$symbols, $available, $signalDate, $prevDate, $nextDate];
    }

    private function buildRow(array $analysis, string $sym, string $signalDate, ?string $nextDate, string $mode, array $extra): array
    {
        $sig = $analysis['signal'];
        return array_merge([
            'symbol'             => $sym,
            'signal_date'        => $signalDate,
            'trade_date'         => $nextDate,
            'mode'               => $mode,
            'action'             => $sig['action'],
            'confidence'         => $sig['confidence'],
            'strength'           => $sig['strength'],
            'composite'          => $sig['composite']   ?? 0,
            'aligned'            => $sig['aligned']     ?? 0,
            'pcr_bias'           => $sig['pcr_bias']    ?? null,
            'oi_bias'            => $sig['oi_bias']     ?? null,
            'price_bias'         => $sig['price_bias']  ?? null,
            'day_change_pct'     => $analysis['day']['change_pct'] ?? null,
            'pcr_eod'            => $analysis['day']['pcr_eod']    ?? null,
            'signal_strike'      => $analysis['order']['strike']          ?? null,
            'signal_option_type' => $analysis['order']['option_type']     ?? null,
            'signal_entry_price' => $analysis['order']['entry_price']     ?? null,
            'signal_strike_pos'  => $analysis['order']['strike_position'] ?? null,
            'premium_status'     => $analysis['order']['premium_status']  ?? null,
            'expiry'             => $analysis['expiry']      ?? null,
            'atm_strike'         => $analysis['atm_strike']  ?? null,
            'indicators'         => $analysis['indicators'],
            'reasons'            => $sig['reasons'] ?? [],
        ], $extra);
    }

    private function noOrderRow(string $sym, string $signalDate, ?string $nextDate, array $analysis): array
    {
        $sig = $analysis['signal'];
        return [
            'symbol' => $sym, 'signal_date' => $signalDate, 'trade_date' => $nextDate,
            'mode' => 'n/a', 'action' => $sig['action'],
            'confidence' => $sig['confidence'], 'strength' => $sig['strength'],
            'composite' => $sig['composite'] ?? 0, 'aligned' => $sig['aligned'] ?? 0,
            'day_change_pct' => $analysis['day']['change_pct'] ?? null,
            'pcr_eod' => $analysis['day']['pcr_eod'] ?? null,
            'outcome' => 'NO_ORDER', 'candles' => [],
            'indicators' => $analysis['indicators'], 'reasons' => $sig['reasons'] ?? [],
        ];
    }

    private function buildSummary(array $rows, string $mode): array
    {
        $tradeable = array_filter($rows, fn($r) => in_array($r['outcome'] ?? '', ['WIN', 'LOSS']));
        $wins      = array_filter($tradeable, fn($r) => $r['outcome'] === 'WIN');
        $losses    = array_filter($tradeable, fn($r) => $r['outcome'] === 'LOSS');
        $comp      = count($tradeable);

        $movePctKey = $mode === 'both' ? 'best_move_pct' : 'max_move_pct';
        $maxMoves   = array_filter(array_column(array_values($wins), $movePctKey), fn($v) => $v !== null);

        $extra = [];
        if ($mode === 'both') {
            $extra = [
                'ce_wins' => count(array_filter($tradeable, fn($r) => ($r['ce_outcome'] ?? '') === 'WIN')),
                'pe_wins' => count(array_filter($tradeable, fn($r) => ($r['pe_outcome'] ?? '') === 'WIN')),
            ];
        }

        return array_merge([
            'total'        => count($rows),
            'wins'         => count($wins),
            'losses'       => count($losses),
            'pending'      => count(array_filter($rows, fn($r) => ($r['outcome'] ?? '') === 'PENDING')),
            'no_data'      => count(array_filter($rows, fn($r) => in_array($r['outcome'] ?? '', ['NO_DATA', 'NO_ORDER']))),
            'completed'    => $comp,
            'win_rate'     => $comp > 0 ? round(count($wins) / $comp * 100, 1) : null,
            'avg_max_move' => count($maxMoves) > 0 ? round(array_sum($maxMoves) / count($maxMoves), 2) : null,
            'best_move'    => count($maxMoves) > 0 ? max($maxMoves) : null,
        ], $extra);
    }

    private function sorter(): \Closure
    {
        return function ($a, $b) {
            $o = ['STRONG' => 0, 'MODERATE' => 1, 'WEAK' => 2, 'SPECULATIVE' => 3];
            $as = $o[$a['strength'] ?? 'SPECULATIVE'] ?? 3;
            $bs = $o[$b['strength'] ?? 'SPECULATIVE'] ?? 3;
            if ($as !== $bs) return $as <=> $bs;
            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        };
    }

    private function jsonResponse(array $results, string $signalDate, ?string $nextDate, array $available, string $mode): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true, 'data' => $results,
            'summary' => $this->buildSummary($results, $mode),
            'signal_date' => $signalDate, 'trade_date' => $nextDate,
            'available_symbols' => $available, 'mode' => $mode,
            'message' => count($results) . ' signals | mode: ' . $mode,
        ]);
    }

    private function emptyResponse(string $signalDate, ?string $nextDate, array $available): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true, 'data' => [], 'summary' => [],
            'signal_date' => $signalDate, 'trade_date' => $nextDate,
            'available_symbols' => $available, 'message' => 'No data for ' . $signalDate,
        ]);
    }
}