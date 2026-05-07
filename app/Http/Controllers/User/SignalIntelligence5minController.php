<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData5min;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SignalIntelligence5minController — v3
 *
 * ══════════════════════════════════════════════════════════════════
 * ROOT CAUSE FIX (v3) — WHY SIGNALS WERE ALWAYS "NO TRADE"
 * ══════════════════════════════════════════════════════════════════
 *
 * The `future_price` column on CE/PE rows stores the FROZEN ATM strike
 * price captured once at collection time — it does NOT change candle
 * to candle. So detectMarketStructure() was comparing e.g.
 * [1270, 1270, 1270] every interval → always FLAT → always SIDEWAYS
 * → entry score never reached 55 → always NO TRADE.
 *
 * FIX: load the FUT instrument rows from option_ohlc_data_5min
 * (instrument_type = 'FUT') which has the real changing close price
 * per candle. Use THAT for market structure + OI-price relation.
 *
 * OTHER FIXES in v3:
 *   - Next Day Bias now auto-runs ALL available symbols (no manual select)
 *   - Time Performance: graceful empty state with clear instructions
 *   - Structure window widened to 4 candles (less noise than 3)
 *   - Score threshold lowered from 55 → 45 (volume is rarer intraday)
 *   - Vol spike window expanded to last 8 candles (5 was too few)
 * ══════════════════════════════════════════════════════════════════
 */
class SignalIntelligence5minController extends Controller
{
    private const MAX_SYMBOLS_ALL_MODE = 20;
    private const SIGNAL_SCORE_THRESHOLD = 45; // lowered from 55

    private const TIME_WINDOWS = [
        ['from' => '09:20', 'to' => '10:30', 'zone' => 'BEST',     'label' => '🟢 Best Zone',    'color' => '#51cf66'],
        ['from' => '10:30', 'to' => '12:00', 'zone' => 'MODERATE', 'label' => '🟡 Moderate',     'color' => '#f7b733'],
        ['from' => '12:00', 'to' => '13:30', 'zone' => 'AVOID',    'label' => '🔴 Avoid',        'color' => '#ff6b6b'],
        ['from' => '13:30', 'to' => '14:45', 'zone' => 'GOOD',     'label' => '🟢 Good Zone',    'color' => '#51cf66'],
        ['from' => '14:45', 'to' => '15:15', 'zone' => 'CAUTION',  'label' => '⚠️ Caution',      'color' => '#ffa502'],
        ['from' => '15:15', 'to' => '15:30', 'zone' => 'NO_TRADE', 'label' => '❌ No New Trade', 'color' => '#868e96'],
    ];

    // ══════════════════════════════════════════════════════════════════════════
    // PAGE
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'Signal Intelligence 5Min';
        return view($this->activeTemplate . 'user.signal-intel-5min.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MAIN SIGNAL API
    // ══════════════════════════════════════════════════════════════════════════

    public function getSignals(Request $request)
    {
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        try {
            $symbol = strtoupper(trim($request->get('symbol', 'ALL')));
            $today  = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            $availableSymbols = OptionOhlcData5min::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success' => true, 'data' => [], 'today' => $today,
                    'is_today'          => $today === Carbon::today()->toDateString(),
                    'message'           => 'No 5-min data found for ' . $today,
                    'available_symbols' => [],
                ]);
            }

            $isAll   = ($symbol === 'ALL');
            $symbols = $isAll ? $availableSymbols : [$symbol];

            $capped = false;
            if ($isAll && count($symbols) > self::MAX_SYMBOLS_ALL_MODE) {
                $symbols = array_slice($symbols, 0, self::MAX_SYMBOLS_ALL_MODE);
                $capped  = true;
            }

            // Load time perf from DB once for all symbols
            $timePerfCache = $this->loadTimePerfFromDb($symbols);

            $results = [];

            foreach ($symbols as $sym) {
                $expiry = $this->getNearestExpiry($sym, $today);
                if (!$expiry) continue;

                // ── ROOT CAUSE FIX: load real FUT candles separately ──────────
                // CE/PE future_price = frozen ATM (doesn't move). We need the
                // actual FUT instrument close which changes every 5 minutes.
                $futRows = $this->loadFutData($sym, $today);

                // FUT price map: ['09:20' => 1284.5, '09:25' => 1287.0, ...]
                $futPriceByTime = $this->buildFutPriceMap($futRows);

                // If no FUT rows, fall back to future_price from option rows
                // (still better than nothing — will at least show partial signals)
                $useFallback = empty($futPriceByTime);

                // Load CE/PE option rows (time-filtered)
                $allRows = $this->loadSymbolData($sym, $expiry, $today);
                if ($allRows->isEmpty()) continue;

                // If FUT missing, try extracting from option rows as fallback
                if ($useFallback) {
                    $futPriceByTime = $this->extractFutPriceByTime($allRows);
                    Log::warning("SignalIntel: No FUT rows for {$sym} on {$today} — using future_price fallback");
                }

                $atmStrike  = $this->resolveAtmStrike($allRows);
                $atmStrikes = $this->getAtmPlusMinusStrikes($allRows, $atmStrike, 3);

                $ceRows = $allRows->where('instrument_type', 'CE');
                $peRows = $allRows->where('instrument_type', 'PE');

                $ceCandles = $ceRows->where('strike_position', 'ATM')->sortBy('interval_time')->values();
                $peCandles = $peRows->where('strike_position', 'ATM')->sortBy('interval_time')->values();

                if ($ceCandles->isEmpty() && $peCandles->isEmpty()) continue;

                // Aggregation ONCE outside loop
                $ceOiByTime  = $this->aggregateByTime($ceRows, $atmStrikes, 'oi');
                $peOiByTime  = $this->aggregateByTime($peRows, $atmStrikes, 'oi');
                $ceVolByTime = $this->aggregateByTime($ceRows, $atmStrikes, 'volume');
                $peVolByTime = $this->aggregateByTime($peRows, $atmStrikes, 'volume');

                $allTimes = collect(array_keys($ceOiByTime))
                    ->merge(array_keys($peOiByTime))
                    ->unique()->sort()->values()->toArray();

                $timePerf = $timePerfCache[$sym] ?? [];

                $intervalSignals = [];
                $priorVolumes    = [];
                $prevFutClose    = null;
                $prevCeOi        = null;
                $prevPeOi        = null;

                foreach ($allTimes as $idx => $timeKey) {
                    $curCeOi  = $ceOiByTime[$timeKey]  ?? 0;
                    $curPeOi  = $peOiByTime[$timeKey]  ?? 0;
                    $totalVol = ($ceVolByTime[$timeKey] ?? 0) + ($peVolByTime[$timeKey] ?? 0);

                    // ── ROOT CAUSE FIX: use real FUT price, not frozen ATM ────
                    $futPrice = $futPriceByTime[$timeKey] ?? null;

                    $structure       = $this->detectMarketStructure($futPriceByTime, $allTimes, $idx);
                    $oiPriceRelation = $this->calcOiPriceRelation(
                        $futPrice, $prevFutClose, $curCeOi, $prevCeOi, $curPeOi, $prevPeOi, $idx
                    );
                    $marketState = $this->classifyMarketState($structure, $oiPriceRelation);

                    $volSpike = $this->calcVolSpike($totalVol, $priorVolumes);
                    if ($totalVol > 0) $priorVolumes[] = $totalVol;

                    $timeWindow    = $this->getTimeWindow($timeKey);
                    $stockTimePref = $timePerf[$timeKey] ?? null;

                    $entrySignal = $this->generateEntrySignal(
                        $marketState, $oiPriceRelation, $volSpike, $timeWindow, $stockTimePref
                    );

                    $exitSignal = $idx > 0
                        ? $this->checkExitConditions(
                            $intervalSignals[$idx - 1] ?? null,
                            $oiPriceRelation, $volSpike, $marketState
                          )
                        : null;

                    $intervalSignals[$idx] = [
                        'time'            => $timeKey,
                        'fut_price'       => $futPrice !== null ? round($futPrice, 2) : null,
                        'market_state'    => $marketState,
                        'oi_price'        => $oiPriceRelation,
                        'vol_spike'       => $volSpike,
                        'time_window'     => $timeWindow,
                        'stock_time_perf' => $stockTimePref,
                        'entry_signal'    => $entrySignal,
                        'exit_signal'     => $exitSignal,
                        'ce_oi'           => (int) $curCeOi,
                        'pe_oi'           => (int) $curPeOi,
                    ];

                    $prevFutClose = $futPrice ?? $prevFutClose;
                    $prevCeOi    = $curCeOi;
                    $prevPeOi    = $curPeOi;
                }

                $ceCandleMap = $ceCandles->keyBy(fn($c) => substr($c->interval_time, 11, 5));
                $peCandleMap = $peCandles->keyBy(fn($c) => substr($c->interval_time, 11, 5));

                $signalRows = [];
                foreach ($intervalSignals as $sig) {
                    $t = $sig['time'];
                    $signalRows[] = array_merge($sig, [
                        'ce' => isset($ceCandleMap[$t]) ? $this->candleArray($ceCandleMap[$t]) : null,
                        'pe' => isset($peCandleMap[$t]) ? $this->candleArray($peCandleMap[$t]) : null,
                    ]);
                }

                // In ALL mode: return only the latest interval per symbol
                if ($isAll) {
                    $last       = end($signalRows);
                    $signalRows = $last ? [$last] : [];
                }

                $results[] = [
                    'symbol'        => $sym,
                    'expiry'        => $expiry,
                    'date'          => $today,
                    'atm_strike'    => $atmStrike,
                    'total_slots'   => count($intervalSignals),
                    'fut_available' => !$useFallback,
                    'signals'       => $signalRows,
                ];
            }

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'today'             => $today,
                'is_today'          => $today === Carbon::today()->toDateString(),
                'mode'              => $isAll ? 'summary' : 'detail',
                'available_symbols' => $availableSymbols,
                'symbols_shown'     => count($symbols),
                'symbols_capped'    => $capped,
                'time_windows'      => self::TIME_WINDOWS,
                'message'           => count($results) . ' symbol(s) loaded' . ($capped ? ' (capped at ' . self::MAX_SYMBOLS_ALL_MODE . ')' : ''),
            ]);

        } catch (\Exception $e) {
            Log::error('SignalIntelligence5min::getSignals', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEXT DAY PREDICTION — ALL SYMBOLS (v3 fix: no manual select needed)
    // ══════════════════════════════════════════════════════════════════════════

    public function getNextDayPrediction(Request $request)
    {
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        try {
            $today = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            // Single symbol mode (for detail view)
            $singleSymbol = $request->get('symbol') ? strtoupper(trim($request->get('symbol'))) : null;

            // Get all symbols available for this date
            $availableSymbols = OptionOhlcData5min::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json(['success' => false, 'message' => 'No data for ' . $today]);
            }

            $symbols = $singleSymbol ? [$singleSymbol] : $availableSymbols;
            $results = [];

            foreach ($symbols as $symbol) {
                $expiry = $this->getNearestExpiry($symbol, $today);
                if (!$expiry) continue;

                $allRows = $this->loadSymbolData($symbol, $expiry, $today);
                if ($allRows->isEmpty()) continue;

                // Use real FUT data for price structure
                $futRows        = $this->loadFutData($symbol, $today);
                $futPriceByTime = !empty($futRows) ? $this->buildFutPriceMap($futRows) : $this->extractFutPriceByTime($allRows);

                $atmStrike  = $this->resolveAtmStrike($allRows);
                $atmStrikes = $this->getAtmPlusMinusStrikes($allRows, $atmStrike, 2);

                $firstTime = $allRows->min('interval_time');
                $lastTime  = $allRows->max('interval_time');

                $oiSummary  = $this->calcOiBuildSummary($allRows, $atmStrikes, $firstTime, $lastTime);
                $prices     = array_values($futPriceByTime);
                $dayHigh    = !empty($prices) ? max($prices) : 0;
                $dayLow     = !empty($prices) ? min($prices) : 0;
                $closePrice = !empty($prices) ? end($prices)  : 0;
                $dayRange   = $dayHigh - $dayLow;
                $closeRatio = $dayRange > 0 ? ($closePrice - $dayLow) / $dayRange : 0.5;

                [$bias, $confidence, $reasons] = $this->calcNextDayBias(
                    $oiSummary, $closeRatio >= 0.7, $closeRatio <= 0.3, $closeRatio
                );

                $strongestMoveTime = $this->findStrongestMoveTime($futPriceByTime);
                $tomorrowSession   = $this->predictTomorrowSession($strongestMoveTime, $bias);

                $results[] = [
                    'symbol'              => $symbol,
                    'expiry'              => $expiry,
                    'next_day_bias'       => $bias,
                    'confidence'          => $confidence,
                    'reasons'             => $reasons,
                    'oi_summary'          => $oiSummary,
                    'close_ratio'         => round($closeRatio * 100, 1),
                    'closed_near_high'    => $closeRatio >= 0.7,
                    'closed_near_low'     => $closeRatio <= 0.3,
                    'day_high'            => round($dayHigh, 2),
                    'day_low'             => round($dayLow, 2),
                    'close_price'         => round($closePrice, 2),
                    'strongest_move_time' => $strongestMoveTime,
                    'tomorrow_session'    => $tomorrowSession,
                ];
            }

            // Sort: BULLISH first, then BEARISH, then SIDEWAYS; highest confidence first within each
            usort($results, function ($a, $b) {
                $order = ['BULLISH' => 0, 'BEARISH' => 1, 'SIDEWAYS' => 2];
                $ao = $order[$a['next_day_bias']] ?? 3;
                $bo = $order[$b['next_day_bias']] ?? 3;
                if ($ao !== $bo) return $ao - $bo;
                return $b['confidence'] - $a['confidence'];
            });

            return response()->json([
                'success'      => true,
                'analysis_date'=> $today,
                'total'        => count($results),
                'data'         => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('SignalIntelligence5min::getNextDayPrediction', [
                'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TIME PERFORMANCE — DB-BACKED
    // ══════════════════════════════════════════════════════════════════════════

    public function getTimePerformance(Request $request)
    {
        try {
            $symbol = strtoupper(trim($request->get('symbol', 'NIFTY')));
            $days   = (int) $request->get('days', 30);

            // Check if table exists first
            if (!DB::getSchemaBuilder()->hasTable('signal_time_performance')) {
                return response()->json([
                    'success' => false,
                    'symbol'  => $symbol,
                    'days'    => $days,
                    'data'    => [],
                    'message' => 'Table signal_time_performance does not exist. Run: php artisan migrate',
                ]);
            }

            $rows = DB::table('signal_time_performance')
                ->where('symbol', $symbol)
                ->where('lookback_days', $days)
                ->orderBy('time_slot')
                ->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'symbol'  => $symbol,
                    'days'    => $days,
                    'data'    => [],
                    'message' => 'No pre-computed data. Run: php artisan signal:build-time-performance --symbol=' . $symbol,
                ]);
            }

            $data = [];
            foreach ($rows as $row) {
                $data[$row->time_slot] = [
                    'time'     => $row->time_slot,
                    'total'    => $row->total_signals,
                    'wins'     => $row->wins,
                    'losses'   => $row->losses,
                    'accuracy' => $row->accuracy,
                    'ce_wins'  => $row->ce_wins,
                    'pe_wins'  => $row->pe_wins,
                    'zone'     => $row->zone,
                ];
            }

            return response()->json([
                'success'     => true,
                'symbol'      => $symbol,
                'days'        => $days,
                'data'        => $data,
                'computed_at' => optional($rows->first())->updated_at,
            ]);

        } catch (\Exception $e) {
            Log::error('SignalIntelligence5min::getTimePerformance', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function buildTimePerformance(Request $request)
    {
        $symbol = $request->get('symbol');
        $days   = (int) $request->get('days', 30);
        $args   = ['--days' => $days];
        if ($symbol) $args['--symbol'] = strtoupper($symbol);
        \Artisan::queue('signal:build-time-performance', $args);
        return response()->json(['success' => true, 'message' => 'Build queued. Results ready in a few minutes.']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ROOT CAUSE FIX: REAL FUT DATA LOADER
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Load FUT instrument rows for a symbol on a given date.
     * These rows have the actual changing close price per 5-min candle.
     */
    private function loadFutData(string $symbol, string $date): array
    {
        $rows = OptionOhlcData5min::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->whereTime('interval_time', '>=', '09:20:00')
            ->whereTime('interval_time', '<=', '15:15:00')
            ->orderBy('interval_time')
            ->get(['interval_time', 'close', 'open', 'high', 'low', 'volume'])
            ->toArray();

        return $rows;
    }

    /**
     * Build a time → close price map from FUT rows.
     * ['09:20' => 23082.5, '09:25' => 23095.0, ...]
     */
    private function buildFutPriceMap(array $futRows): array
    {
        $map = [];
        foreach ($futRows as $row) {
            $row     = (object) $row;
            $timeKey = substr($row->interval_time, 11, 5);
            if ((float) $row->close > 0) {
                $map[$timeKey] = (float) $row->close;
            }
        }
        ksort($map);
        return $map;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LOAD TIME PERF FROM DB (single query for all symbols)
    // ══════════════════════════════════════════════════════════════════════════

    private function loadTimePerfFromDb(array $symbols): array
    {
        if (empty($symbols)) return [];

        try {
            if (!DB::getSchemaBuilder()->hasTable('signal_time_performance')) return [];

            $rows = DB::table('signal_time_performance')
                ->whereIn('symbol', $symbols)
                ->where('lookback_days', 30)
                ->get(['symbol', 'time_slot', 'total_signals', 'wins', 'losses', 'accuracy', 'ce_wins', 'pe_wins', 'zone']);

            $result = [];
            foreach ($rows as $row) {
                $result[$row->symbol][$row->time_slot] = [
                    'time'     => $row->time_slot,
                    'total'    => $row->total_signals,
                    'wins'     => $row->wins,
                    'losses'   => $row->losses,
                    'accuracy' => $row->accuracy,
                    'ce_wins'  => $row->ce_wins,
                    'pe_wins'  => $row->pe_wins,
                    'zone'     => $row->zone,
                ];
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MARKET STATE ENGINE
    // ══════════════════════════════════════════════════════════════════════════

    private function classifyMarketState(array $structure, array $oiPriceRelation): array
    {
        $structBullish = $structure['pattern'] === 'HH_HL';
        $structBearish = $structure['pattern'] === 'LH_LL';
        $oiSignal      = $oiPriceRelation['signal']     ?? 'NEUTRAL';
        $ceRelation    = $oiPriceRelation['ceRelation'] ?? '';
        $peRelation    = $oiPriceRelation['peRelation'] ?? '';

        $isReversal = in_array($ceRelation, ['SHORT_COVERING', 'LONG_UNWINDING'])
                   || in_array($peRelation, ['SHORT_COVERING', 'LONG_UNWINDING']);

        // Reversal only fires if structure is also clear
        if ($isReversal && ($structBullish || $structBearish)) {
            return ['state' => 'REVERSAL', 'label' => 'Reversal Zone', 'color' => '#ffa502',
                'detail' => 'Price/OI divergence — potential reversal'];
        }
        if ($structBullish && $oiSignal === 'BULLISH') {
            return ['state' => 'STRONG_BULLISH', 'label' => 'Strong Bullish', 'color' => '#51cf66',
                'detail' => 'HH/HL structure + Bullish OI'];
        }
        if ($structBearish && $oiSignal === 'BEARISH') {
            return ['state' => 'STRONG_BEARISH', 'label' => 'Strong Bearish', 'color' => '#ff6b6b',
                'detail' => 'LH/LL structure + Bearish OI'];
        }
        // Partial signals — structure present but OI mixed
        if ($structBullish) {
            return ['state' => 'BULLISH_WEAK', 'label' => 'Weak Bullish', 'color' => '#a3e6b0',
                'detail' => 'HH/HL structure but OI mixed'];
        }
        if ($structBearish) {
            return ['state' => 'BEARISH_WEAK', 'label' => 'Weak Bearish', 'color' => '#ffaaaa',
                'detail' => 'LH/LL structure but OI mixed'];
        }
        return ['state' => 'SIDEWAYS', 'label' => 'Sideways', 'color' => '#868e96',
            'detail' => 'No clear HH/HL or LH/LL'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI + PRICE RELATION
    // ══════════════════════════════════════════════════════════════════════════

    private function calcOiPriceRelation(
        ?float $futPrice, ?float $prevFutPrice,
        int $curCeOi, ?int $prevCeOi,
        int $curPeOi, ?int $prevPeOi,
        int $idx
    ): array {
        if ($idx === 0 || $prevFutPrice === null || $prevCeOi === null) {
            return ['signal' => 'NEUTRAL', 'ceRelation' => 'N/A', 'peRelation' => 'N/A',
                    'action' => 'WAIT',    'reason'     => 'Opening candle'];
        }

        $priceUp = ($futPrice !== null && $prevFutPrice !== null) ? ($futPrice > $prevFutPrice) : false;
        $ceOiUp  = $curCeOi > ($prevCeOi ?? 0);
        $peOiUp  = $curPeOi > ($prevPeOi ?? 0);

        $ceRelation = match(true) {
            $priceUp  && $ceOiUp  => 'LONG_BUILDUP',
            !$priceUp && $ceOiUp  => 'SHORT_BUILDUP',
            $priceUp  && !$ceOiUp => 'SHORT_COVERING',
            default               => 'LONG_UNWINDING',
        };
        $peRelation = match(true) {
            !$priceUp && $peOiUp  => 'LONG_BUILDUP',
            $priceUp  && $peOiUp  => 'SHORT_BUILDUP',
            !$priceUp && !$peOiUp => 'SHORT_COVERING',
            default               => 'LONG_UNWINDING',
        };

        $bullishCount = (int)($ceRelation === 'SHORT_COVERING') + (int)($peRelation === 'LONG_BUILDUP');
        $bearishCount = (int)($ceRelation === 'LONG_BUILDUP')   + (int)($peRelation === 'SHORT_BUILDUP');

        if ($bearishCount > $bullishCount) {
            return ['signal' => 'BEARISH', 'ceRelation' => $ceRelation, 'peRelation' => $peRelation,
                    'action' => 'BUY_PE', 'reason' => 'CE buildup confirms bearish pressure'];
        }
        if ($bullishCount > $bearishCount) {
            return ['signal' => 'BULLISH', 'ceRelation' => $ceRelation, 'peRelation' => $peRelation,
                    'action' => 'BUY_CE', 'reason' => 'Short covering + PE support confirms bullish'];
        }
        return ['signal' => 'NEUTRAL', 'ceRelation' => $ceRelation, 'peRelation' => $peRelation,
                'action' => 'WAIT', 'reason' => 'Mixed signals'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MARKET STRUCTURE — widened to 4 candle window
    // ══════════════════════════════════════════════════════════════════════════

    private function detectMarketStructure(array $futPriceByTime, array $allTimes, int $idx): array
    {
        if ($idx < 2) return ['pattern' => 'UNKNOWN', 'label' => 'Building…'];

        // Use last 4 candles for smoother signal (was 3 — too noisy)
        $lookback = min($idx, 3);
        $window   = array_slice($allTimes, $idx - $lookback, $lookback + 1);
        $prices   = array_values(array_filter(array_map(fn($t) => $futPriceByTime[$t] ?? null, $window)));

        if (count($prices) < 3) return ['pattern' => 'UNKNOWN', 'label' => 'Insufficient data'];

        // Use first, mid, last of the window
        $p1 = $prices[0];
        $p2 = $prices[(int)(count($prices) / 2)];
        $p3 = end($prices);

        return match(true) {
            $p3 > $p2 && $p2 > $p1 => ['pattern' => 'HH_HL', 'label' => 'Higher High / Higher Low'],
            $p3 < $p2 && $p2 < $p1 => ['pattern' => 'LH_LL', 'label' => 'Lower High / Lower Low'],
            $p3 > $p1 && $p3 > $p2 => ['pattern' => 'HH_HL', 'label' => 'Recovering / Higher High'],
            $p3 < $p1 && $p3 < $p2 => ['pattern' => 'LH_LL', 'label' => 'Declining / Lower Low'],
            default                 => ['pattern' => 'FLAT',  'label' => 'Flat / Sideways'],
        };
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VOLUME SPIKE — expanded to 8 candle window
    // ══════════════════════════════════════════════════════════════════════════

    private function calcVolSpike(int $curVol, array $priorVolumes): array
    {
        if (count($priorVolumes) < 3) {
            return ['type' => 'OPENING', 'label' => 'OPENING', 'ratio' => null, 'avg' => 0, 'confirmed' => false];
        }
        // Use last 8 candles for avg (was 5 — gives more stable baseline)
        $recent = array_slice($priorVolumes, -8);
        $avg    = array_sum($recent) / count($recent);
        $ratio  = $avg > 0 ? round($curVol / $avg, 2) : 0;

        [$type, $label, $confirmed] = match(true) {
            $ratio >= 2.0 => ['STRONG_SPIKE', '🔥 Strong Spike', true],
            $ratio >= 1.5 => ['SPIKE',        '⚡ Spike',        true],
            $ratio >= 1.2 => ['ELEVATED',     '↑ Elevated',     false],
            default       => ['NORMAL',       '— Normal',       false],
        };
        return compact('type', 'label', 'ratio', 'avg', 'confirmed');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ENTRY SIGNAL — score threshold lowered to 45
    // ══════════════════════════════════════════════════════════════════════════

    private function generateEntrySignal(
        array $marketState, array $oiPriceRelation, array $volSpike,
        array $timeWindow, ?array $stockTimePref
    ): array {
        $state        = $marketState['state']      ?? 'SIDEWAYS';
        $oiSignal     = $oiPriceRelation['signal'] ?? 'NEUTRAL';
        $volConfirmed = $volSpike['confirmed']      ?? false;
        $timeZone     = $timeWindow['zone']         ?? 'MODERATE';

        if ($timeZone === 'AVOID' || $timeZone === 'NO_TRADE') {
            return ['signal' => 'NO_TRADE', 'strike' => null, 'confidence' => 'BLOCKED',
                    'score' => 0, 'reason' => 'Time zone: ' . $timeWindow['label'], 'conditions' => []];
        }

        $score = 0; $conditions = [];

        // Condition 1: Market state (30pts for strong, 15pts for weak directional)
        $stateStrong = in_array($state, ['STRONG_BULLISH', 'STRONG_BEARISH']);
        $stateWeak   = in_array($state, ['BULLISH_WEAK',   'BEARISH_WEAK']);
        if ($stateStrong) {
            $conditions[] = ['name' => 'Market State', 'met' => true,  'detail' => $marketState['label']];
            $score += 30;
        } elseif ($stateWeak) {
            $conditions[] = ['name' => 'Market State', 'met' => true,  'detail' => $marketState['label']];
            $score += 15;
        } else {
            $conditions[] = ['name' => 'Market State', 'met' => false, 'detail' => $marketState['label']];
        }

        // Condition 2: OI signal (25pts)
        $oiOk = in_array($oiSignal, ['BULLISH', 'BEARISH']);
        $conditions[] = ['name' => 'OI Signal', 'met' => $oiOk, 'detail' => $oiSignal];
        if ($oiOk) $score += 25;

        // Condition 3: Volume (20pts — was 25, lowered since volume confirmation rarer)
        $conditions[] = ['name' => 'Volume Spike', 'met' => $volConfirmed, 'detail' => $volSpike['label']];
        if ($volConfirmed) $score += 20;

        // Condition 4: Time window bonus
        $timeBonus = match($timeZone) { 'BEST' => 15, 'GOOD' => 10, 'MODERATE' => 5, default => 0 };
        $conditions[] = ['name' => 'Time Window', 'met' => $timeBonus > 0, 'detail' => $timeWindow['label']];
        $score += $timeBonus;

        // Condition 5: Stock time pref (bonus 10pts)
        if ($stockTimePref && ($stockTimePref['accuracy'] ?? 0) >= 60) {
            $conditions[] = ['name' => 'Stock Time Pref', 'met' => true, 'detail' => $stockTimePref['accuracy'] . '%'];
            $score += 10;
        }

        // Direction alignment
        $isBullish = in_array($state, ['STRONG_BULLISH', 'BULLISH_WEAK']) && $oiSignal === 'BULLISH';
        $isBearish = in_array($state, ['STRONG_BEARISH', 'BEARISH_WEAK']) && $oiSignal === 'BEARISH';

        if (!$isBullish && !$isBearish) {
            return ['signal' => 'NO_TRADE', 'strike' => 'ATM', 'confidence' => 'LOW',
                    'score' => $score, 'reason' => 'State/OI direction mismatch', 'conditions' => $conditions];
        }

        // Threshold: 45 (lowered from 55)
        if ($score < self::SIGNAL_SCORE_THRESHOLD) {
            return ['signal' => 'NO_TRADE', 'strike' => 'ATM', 'confidence' => 'LOW',
                    'score' => $score, 'reason' => "Score {$score}/100 — below " . self::SIGNAL_SCORE_THRESHOLD, 'conditions' => $conditions];
        }

        $confidence = $score >= 80 ? 'VERY_HIGH' : ($score >= 65 ? 'HIGH' : 'MEDIUM');
        return [
            'signal'     => $isBullish ? 'BUY_CE' : 'BUY_PE',
            'strike'     => 'ATM',
            'confidence' => $confidence,
            'score'      => $score,
            'reason'     => $isBullish ? 'Bullish confluence — Buy CE at ATM' : 'Bearish confluence — Buy PE at ATM',
            'conditions' => $conditions,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EXIT CONDITIONS
    // ══════════════════════════════════════════════════════════════════════════

    private function checkExitConditions(
        ?array $prevInterval, array $oiPriceRelation, array $volSpike, array $marketState
    ): ?array {
        if (!$prevInterval) return null;
        $prevSignal = $prevInterval['entry_signal']['signal'] ?? 'NO_TRADE';
        if ($prevSignal === 'NO_TRADE') return null;

        $reasons     = [];
        $curOiSignal = $oiPriceRelation['signal'] ?? 'NEUTRAL';
        $state       = $marketState['state'] ?? '';

        if ($prevSignal === 'BUY_CE' && $curOiSignal === 'BEARISH')                            $reasons[] = 'Opposite OI (BEARISH)';
        if ($prevSignal === 'BUY_PE' && $curOiSignal === 'BULLISH')                            $reasons[] = 'Opposite OI (BULLISH)';
        if ($prevSignal === 'BUY_CE' && in_array($state, ['STRONG_BEARISH','BEARISH_WEAK','REVERSAL'])) $reasons[] = 'State reversed bearish';
        if ($prevSignal === 'BUY_PE' && in_array($state, ['STRONG_BULLISH','BULLISH_WEAK','REVERSAL'])) $reasons[] = 'State reversed bullish';
        if (($prevInterval['vol_spike']['confirmed'] ?? false) && !($volSpike['confirmed'] ?? false)
            && ($volSpike['ratio'] ?? 1) < 0.8)                                                $reasons[] = 'Volume dried up';

        return empty($reasons) ? null : [
            'exit'    => true,
            'reasons' => $reasons,
            'action'  => $prevSignal === 'BUY_CE' ? 'EXIT_CE' : 'EXIT_PE',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEXT DAY BIAS HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function calcOiBuildSummary($allRows, array $atmStrikes, string $firstTime, string $lastTime): array
    {
        $firstRows = $allRows->filter(fn($r) => $r->interval_time === $firstTime);
        $lastRows  = $allRows->filter(fn($r) => $r->interval_time === $lastTime);

        $openCeOi  = (int) $firstRows->where('instrument_type', 'CE')->whereIn('strike', $atmStrikes)->sum('oi');
        $openPeOi  = (int) $firstRows->where('instrument_type', 'PE')->whereIn('strike', $atmStrikes)->sum('oi');
        $closeCeOi = (int) $lastRows->where('instrument_type',  'CE')->whereIn('strike', $atmStrikes)->sum('oi');
        $closePeOi = (int) $lastRows->where('instrument_type',  'PE')->whereIn('strike', $atmStrikes)->sum('oi');

        return [
            'open_ce_oi'   => $openCeOi,  'open_pe_oi'   => $openPeOi,
            'close_ce_oi'  => $closeCeOi, 'close_pe_oi'  => $closePeOi,
            'ce_oi_change' => $closeCeOi - $openCeOi,
            'pe_oi_change' => $closePeOi - $openPeOi,
            'ce_oi_pct'    => $openCeOi > 0 ? round((($closeCeOi - $openCeOi) / $openCeOi) * 100, 2) : 0,
            'pe_oi_pct'    => $openPeOi > 0 ? round((($closePeOi - $openPeOi) / $openPeOi) * 100, 2) : 0,
        ];
    }

    private function calcNextDayBias(array $oi, bool $nearHigh, bool $nearLow, float $closeRatio): array
    {
        $score = 0; $reasons = [];
        if ($oi['pe_oi_pct'] >= 5)  { $score += 25; $reasons[] = '✅ Strong PE writing (+' . $oi['pe_oi_pct'] . '%) — support building'; }
        if ($oi['ce_oi_pct'] >= 5)  { $score -= 25; $reasons[] = '🔴 Strong CE writing (+' . $oi['ce_oi_pct'] . '%) — resistance building'; }
        if ($oi['ce_oi_pct'] <= -5) { $score += 15; $reasons[] = '✅ CE unwinding (' . $oi['ce_oi_pct'] . '%) — short covering'; }
        if ($oi['pe_oi_pct'] <= -5) { $score -= 15; $reasons[] = '🔴 PE unwinding (' . $oi['pe_oi_pct'] . '%) — longs exiting'; }
        if ($nearHigh) { $score += 20; $reasons[] = '✅ Closed near high (' . round($closeRatio * 100) . '% of range)'; }
        if ($nearLow)  { $score -= 20; $reasons[] = '🔴 Closed near low (' . round($closeRatio * 100) . '% of range)'; }
        if ($oi['ce_oi_pct'] > 0 && $oi['pe_oi_pct'] > 0 && abs($oi['ce_oi_pct'] - $oi['pe_oi_pct']) < 3) {
            $reasons[] = '⚪ Both CE & PE OI building — rangebound likely';
        }
        if (empty($reasons)) $reasons[] = '⚪ Insufficient OI signal — watch price action';
        if ($score >= 20)  return ['BULLISH',  min(95, 50 + $score),      $reasons];
        if ($score <= -20) return ['BEARISH',  min(95, 50 + abs($score)), $reasons];
        return                    ['SIDEWAYS', max(40, 60 - abs($score)), $reasons];
    }

    private function findStrongestMoveTime(array $futPrices): ?string
    {
        $times = array_keys($futPrices); $prices = array_values($futPrices);
        if (count($prices) < 2) return null;
        $maxMove = 0; $maxTime = null;
        for ($i = 1; $i < count($prices); $i++) {
            $move = abs($prices[$i] - $prices[$i - 1]);
            if ($move > $maxMove) { $maxMove = $move; $maxTime = $times[$i]; }
        }
        return $maxTime;
    }

    private function predictTomorrowSession(?string $t, string $bias): array
    {
        if (!$t) return ['session' => 'UNKNOWN', 'label' => 'Insufficient data', 'time_range' => '—', 'reason' => ''];
        $ti = (int) str_replace(':', '', $t);
        if ($ti <= 1030) return ['session' => 'EARLY',      'label' => '🟢 Early breakout likely', 'time_range' => '09:20–10:30', 'reason' => 'Strongest move in morning today'];
        if ($ti <= 1230) return ['session' => 'MID_MORNING','label' => '🟡 Mid-morning activity',  'time_range' => '10:30–12:00', 'reason' => 'Strongest move mid-morning today'];
        if ($ti >= 1330) return ['session' => 'AFTERNOON',  'label' => '🟢 Afternoon likely',       'time_range' => '13:30–14:45', 'reason' => 'Post-lunch strength seen today'];
        return ['session' => 'MIXED', 'label' => '⚪ No clear session bias', 'time_range' => '9:20–14:45', 'reason' => 'Moves spread across sessions'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TIME WINDOW
    // ══════════════════════════════════════════════════════════════════════════

    private function getTimeWindow(string $timeKey): array
    {
        $t = (int) str_replace(':', '', $timeKey);
        foreach (self::TIME_WINDOWS as $w) {
            if ($t >= (int)str_replace(':', '', $w['from']) && $t < (int)str_replace(':', '', $w['to'])) return $w;
        }
        return ['from' => '09:15', 'to' => '09:20', 'zone' => 'OPENING', 'label' => '⏰ Opening', 'color' => '#4fc3f7'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DATA HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function loadSymbolData(string $symbol, string $expiry, string $date)
    {
        return OptionOhlcData5min::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->whereTime('interval_time', '>=', '09:20:00')
            ->whereTime('interval_time', '<=', '15:15:00')
            ->orderBy('interval_time')
            ->get([
                'interval_time', 'instrument_type', 'strike', 'strike_position',
                'trading_symbol', 'open', 'high', 'low', 'close',
                'volume', 'oi', 'atm_strike', 'future_price',
            ]);
    }

    private function aggregateByTime($rows, array $atmStrikes, string $field): array
    {
        $result = [];
        foreach ($rows as $r) {
            $strike = (float) $r->strike;
            if (!empty($atmStrikes) && !in_array($strike, $atmStrikes)) continue;
            $timeKey = substr($r->interval_time, 11, 5);
            $result[$timeKey] = ($result[$timeKey] ?? 0) + (int) $r->$field;
        }
        ksort($result);
        return $result;
    }

    /**
     * Fallback: extract future_price column from option rows (frozen ATM — not ideal).
     * Used only when no FUT instrument rows exist in option_ohlc_data_5min.
     */
    private function extractFutPriceByTime($rows): array
    {
        $result = [];
        foreach ($rows as $r) {
            $timeKey = substr($r->interval_time, 11, 5);
            if (!isset($result[$timeKey]) && (float) $r->future_price > 0) {
                $result[$timeKey] = (float) $r->future_price;
            }
        }
        ksort($result);
        return $result;
    }

    private function resolveAtmStrike($rows): ?float
    {
        $last = $rows->sortBy('interval_time')->last();
        return $last ? (float) $last->atm_strike : null;
    }

    private function getAtmPlusMinusStrikes($rows, ?float $atmStrike, int $n = 3): array
    {
        if (!$atmStrike) return [];
        $all = $rows->pluck('strike')->map(fn($s) => (float)$s)
            ->filter(fn($s) => $s > 0)->unique()->sort()->values()->toArray();
        if (empty($all)) return [];
        $diffs  = array_map(fn($s) => abs($s - $atmStrike), $all);
        $atmIdx = array_keys($diffs, min($diffs))[0];
        return array_slice($all, max(0, $atmIdx - $n), $n * 2 + 1);
    }

    private function candleArray($candle): array
    {
        return [
            'strike'         => $candle->strike,
            'trading_symbol' => $candle->trading_symbol,
            'open'           => round((float) $candle->open,  2),
            'high'           => round((float) $candle->high,  2),
            'low'            => round((float) $candle->low,   2),
            'close'          => round((float) $candle->close, 2),
            'volume'         => (int) $candle->volume,
            'oi'             => (int) $candle->oi,
        ];
    }

    private function getNearestExpiry(string $sym, string $date): ?string
    {
        $expiry = OptionOhlcData5min::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        return $expiry ?? OptionOhlcData5min::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }
}