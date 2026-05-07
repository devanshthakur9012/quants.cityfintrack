<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║   OI Flow Sentiment Analyzer                                     ║
 * ║   Instruments : Option CE/PE (ATM aggregate)                    ║
 * ║   Timeframes  : 15min | 30min | 1hr                             ║
 * ║                                                                  ║
 * ║   CORE LOGIC  (mirrors OIIVAutoController::analyzePECESignals)  ║
 * ║                                                                  ║
 * ║   T   = today's candle at the analysis slot (e.g. 14:45)        ║
 * ║   T-1 = previous trading day's last candle (15:00)              ║
 * ║                                                                  ║
 * ║   CE OI change % = (T_CE_OI − T1_CE_OI) / T1_CE_OI × 100      ║
 * ║   PE OI change % = (T_PE_OI − T1_PE_OI) / T1_PE_OI × 100      ║
 * ║                                                                  ║
 * ║   Signal rules:                                                  ║
 * ║     CE↑ + PE↓ → BEARISH  (call build + put unwind)             ║
 * ║     CE↓ + PE↑ → BULLISH  (call unwind + put build)             ║
 * ║     Both↑ → CE%>PE% = BEARISH | PE%>CE% = BULLISH              ║
 * ║     Both↓ → |CE%|>|PE%| = BULLISH | |PE%|>|CE%| = BEARISH     ║
 * ║                                                                  ║
 * ║   Trade action:                                                  ║
 * ║     BULLISH → BUY CE                                            ║
 * ║     BEARISH → BUY PE                                            ║
 * ║     NEUTRAL → WAIT                                              ║
 * ║                                                                  ║
 * ║   Strength rank (|CE% − PE%| diff):                             ║
 * ║     diff > 40 → Rank 1 | >25 → Rank 2 | >10 → Rank 3           ║
 * ║     diff > 5  → Rank 4 | ≤5  → Normal                          ║
 * ║                                                                  ║
 * ║   Tables used:                                                   ║
 * ║     cp_option_ohlc_{timeframe} — CE/PE candles                  ║
 * ║   Scoped to config-assigned symbols only.                        ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */
class OIFlowSentimentController extends Controller
{
    private const TIMEFRAMES = ['15min', '30min', '1hr'];

    // The analysis slot for "today" OI reading:
    // 15min → last candle before 3PM = 14:45
    // 30min → 14:30 slot covers 14:30-15:00
    // 1hr   → 14:15 slot covers 14:15-15:15
    private const ANALYSIS_TIME = [
        '15min' => '14:45:00',
        '30min' => '14:30:00',
        '1hr'   => '14:15:00',
    ];

    // Previous day reference slot: last candle of prior session
    private const PREV_DAY_TIME = '15:00:00';

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'OI Flow Sentiment Analyzer';
        return view($this->activeTemplate . 'user.oi-flow-sentiment.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SYMBOLS API
    // ══════════════════════════════════════════════════════════════════════════

    public function getSymbols(Request $request)
    {
        $timeframe = $this->resolveTimeframe($request);
        $config    = $this->getActiveConfig($timeframe);

        if (!$config) {
            return response()->json(['success' => true, 'symbols' => [], 'no_config' => true,
                'message' => "No active config for [{$timeframe}]."]);
        }

        $symbols = $this->getConfigSymbols($config->id);
        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MAIN ANALYSIS API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /oi-flow-sentiment/analyze
     *
     * For each symbol × each trading day in the range:
     *   1. Read today's CE OI and PE OI at the analysis slot
     *   2. Read prev-day's CE OI and PE OI at 15:00
     *   3. Compute % change
     *   4. Apply signal rules → BULLISH / BEARISH / NEUTRAL
     *   5. Derive trade action, strength rank, condition label
     */
    public function analyze(Request $request)
    {
        try {
            $timeframe   = $this->resolveTimeframe($request);
            $fromDate    = $request->get('from_date');
            $toDate      = $request->get('to_date');
            $symbolReq   = array_filter((array)$request->get('symbols', []));
            $actionFilter = $request->get('filter_action', '');

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates.', 'data' => []]);
            }

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json(['success' => false, 'no_config' => true,
                    'message' => "No active Analysis Config for [{$timeframe}]. Go to Admin → Analysis Config.",
                    'data' => []]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false,
                    'message' => 'No symbols configured for this timeframe.', 'data' => []]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $optTable    = 'cp_option_ohlc_' . $timeframe;
            $analysisTime = self::ANALYSIS_TIME[$timeframe];

            // ── Get all trading dates in range that have data ──────────────
            $tradeDates = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success' => true, 'data' => [],
                    'message' => 'No data found for the selected date range.']);
            }

            // ── Build prev-date map ────────────────────────────────────────
            $prevDateMap = [];
            foreach ($tradeDates as $date) {
                $prevDateMap[$date] = $this->getPreviousTradingDate($date);
            }

            // ── Bulk load today OI (at analysis slot) ─────────────────────
            $todayRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [$analysisTime])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type',
                          DB::raw('DATE(trade_date) as trade_day'),
                          DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
                ->get();

            // ── Bulk load prev-day OI (at 15:00) ──────────────────────────
            $prevDates = array_values(array_unique(array_values($prevDateMap)));
            $prevRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $prevDates)
                ->whereRaw("TIME(interval_time) = ?", [self::PREV_DAY_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type',
                          DB::raw('DATE(trade_date) as trade_day'),
                          DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
                ->get();

            // ── Index into maps ────────────────────────────────────────────
            // Key format: "SYMBOL|DATE|TYPE"
            $todayMap = [];
            foreach ($todayRows as $r) {
                $todayMap[$r->base_symbol . '|' . $r->trade_day . '|' . $r->instrument_type] = (int)$r->total_oi;
            }

            $prevMap = [];
            foreach ($prevRows as $r) {
                $prevMap[$r->base_symbol . '|' . $r->trade_day . '|' . $r->instrument_type] = (int)$r->total_oi;
            }

            // ── Also load latest close price and ATM strike ────────────────
            $priceRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [$analysisTime])
                ->where('instrument_type', 'CE') // CE as price proxy
                ->where('strike_position', 'ATM')
                ->where('is_missing', false)
                ->select(['base_symbol',
                          DB::raw('DATE(trade_date) as trade_day'),
                          'atm_strike', 'future_price', 'expiry_date'])
                ->orderBy('interval_time')
                ->get();

            $priceMap = [];
            foreach ($priceRows as $r) {
                $priceMap[$r->base_symbol . '|' . $r->trade_day] = $r;
            }

            // ── Build results ─────────────────────────────────────────────
            $results = [];

            foreach ($tradeDates as $date) {
                $prevDate = $prevDateMap[$date];

                foreach ($symbols as $symbol) {
                    // Today OI
                    $ceTodayOI = $todayMap[$symbol . '|' . $date . '|CE'] ?? 0;
                    $peTodayOI = $todayMap[$symbol . '|' . $date . '|PE'] ?? 0;

                    // Skip if no data at all for this symbol today
                    if ($ceTodayOI === 0 && $peTodayOI === 0) continue;

                    // Prev day OI
                    $cePrevOI  = $prevMap[$symbol . '|' . $prevDate . '|CE'] ?? 0;
                    $pePrevOI  = $prevMap[$symbol . '|' . $prevDate . '|PE'] ?? 0;

                    // % change — 0 if no prev data (first day in data)
                    $cePct = $cePrevOI > 0
                        ? round((($ceTodayOI - $cePrevOI) / $cePrevOI) * 100, 2)
                        : 0;
                    $pePct = $pePrevOI > 0
                        ? round((($peTodayOI - $pePrevOI) / $pePrevOI) * 100, 2)
                        : 0;

                    // Signal
                    $signal = $this->calcOISignal($cePct, $pePct);

                    // Trade action
                    $tradeAction = match($signal['sentiment']) {
                        'BULLISH' => 'BUY CE',
                        'BEARISH' => 'BUY PE',
                        default   => 'WAIT',
                    };

                    // Apply action filter
                    if ($actionFilter && $tradeAction !== $actionFilter) continue;

                    // Strength rank
                    $diff = round(abs($cePct - $pePct), 2);
                    $strengthRank = match(true) {
                        $diff > 40 => 'Rank 1',
                        $diff > 25 => 'Rank 2',
                        $diff > 10 => 'Rank 3',
                        $diff > 5  => 'Rank 4',
                        default    => 'Normal',
                    };

                    // Price / ATM info
                    $priceRow  = $priceMap[$symbol . '|' . $date] ?? null;
                    $atmStrike = $priceRow ? $priceRow->atm_strike : null;
                    $futPrice  = $priceRow ? round((float)$priceRow->future_price, 2) : null;
                    $expiry    = $priceRow ? substr($priceRow->expiry_date, 0, 10) : null;

                    // P/C Ratio
                    $pcRatio = $ceTodayOI > 0 ? round($peTodayOI / $ceTodayOI, 2) : 0;

                    $results[] = [
                        'date'          => $date,
                        'symbol'        => $symbol,
                        'expiry'        => $expiry,
                        'atm_strike'    => $atmStrike,
                        'fut_price'     => $futPrice,
                        // OI values
                        'ce_oi'         => $ceTodayOI,
                        'pe_oi'         => $peTodayOI,
                        'ce_oi_prev'    => $cePrevOI,
                        'pe_oi_prev'    => $pePrevOI,
                        // % changes
                        'ce_oi_pct'     => $cePct,
                        'pe_oi_pct'     => $pePct,
                        'oi_diff'       => $diff,
                        // Signal
                        'sentiment'     => $signal['sentiment'],
                        'condition'     => $signal['condition'],
                        'reason'        => $signal['reason'],
                        'trade_action'  => $tradeAction,
                        'strength_rank' => $strengthRank,
                        // Ratios
                        'pc_ratio'      => $pcRatio,
                    ];
                }
            }

            // Sort: newest date first, then symbol
            usort($results, fn($a, $b) =>
                strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol'])
            );

            // Stats
            $buyCE   = count(array_filter($results, fn($r) => $r['trade_action'] === 'BUY CE'));
            $buyPE   = count(array_filter($results, fn($r) => $r['trade_action'] === 'BUY PE'));
            $wait    = count(array_filter($results, fn($r) => $r['trade_action'] === 'WAIT'));
            $bullish = count(array_filter($results, fn($r) => $r['sentiment'] === 'BULLISH'));
            $bearish = count(array_filter($results, fn($r) => $r['sentiment'] === 'BEARISH'));

            return response()->json([
                'success'            => true,
                'data'               => $results,
                'total_records'      => count($results),
                'buy_ce_count'       => $buyCE,
                'buy_pe_count'       => $buyPE,
                'wait_count'         => $wait,
                'bullish_count'      => $bullish,
                'bearish_count'      => $bearish,
                'message'            => count($results) . ' record(s) found',
                'timeframe'          => $timeframe,
                'available_symbols'  => $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('OIFlowSentiment analyze: ' . $e->getMessage());
            return response()->json(['success' => false,
                'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SIGNAL ENGINE
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Core CE/PE OI signal logic — identical to OIIVAutoController::getOISignal().
     *
     * Rules:
     *   CE↑ + PE↓ → BEARISH  (call writers building + put unwinding = bears in control)
     *   CE↓ + PE↑ → BULLISH  (call unwinding + put writers building = bulls in control)
     *   Both↑     → stronger side wins (more % = more conviction)
     *   Both↓     → larger absolute unwind wins (shorts/longs covering)
     */
    private function calcOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp && $peDown) {
            return [
                'sentiment' => 'BEARISH',
                'condition' => 'CE ↑ + PE ↓',
                'reason'    => 'Call buildup + Put unwinding → Resistance forming',
            ];
        }

        if ($ceDown && $peUp) {
            return [
                'sentiment' => 'BULLISH',
                'condition' => 'CE ↓ + PE ↑',
                'reason'    => 'Call unwinding + Put buildup → Support forming',
            ];
        }

        if ($ceUp && $peUp) {
            if ($cePct > $pePct) {
                return [
                    'sentiment' => 'BEARISH',
                    'condition' => 'Both ↑ (CE > PE)',
                    'reason'    => "Call buildup stronger (+{$cePct}% vs PE +{$pePct}%) → Bearish",
                ];
            }
            return [
                'sentiment' => 'BULLISH',
                'condition' => 'Both ↑ (PE > CE)',
                'reason'    => "Put buildup stronger (+{$pePct}% vs CE +{$cePct}%) → Bullish",
            ];
        }

        if ($ceDown && $peDown) {
            if ($cePct < $pePct) {
                return [
                    'sentiment' => 'BULLISH',
                    'condition' => 'Both ↓ (CE < PE)',
                    'reason'    => "Call unwinding larger ({$cePct}% vs PE {$pePct}%) → Short covering → Bullish",
                ];
            }
            return [
                'sentiment' => 'BEARISH',
                'condition' => 'Both ↓ (PE < CE)',
                'reason'    => "Put unwinding larger ({$pePct}% vs CE {$cePct}%) → Long covering → Bearish",
            ];
        }

        return [
            'sentiment' => 'NEUTRAL',
            'condition' => 'Flat',
            'reason'    => 'No clear OI direction',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', $timeframe)
            ->where('is_active', 1)
            ->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')
            ->toArray();
    }

    private function resolveTimeframe(Request $request): string
    {
        $tf = strtolower(trim($request->get('timeframe', '15min')));
        return in_array($tf, self::TIMEFRAMES) ? $tf : '15min';
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
            $attempts++;
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}