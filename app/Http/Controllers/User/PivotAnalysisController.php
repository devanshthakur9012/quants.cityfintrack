<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║   Pivot Analysis Controller — Stock | FUT | Option              ║
 * ║   Timeframes: 15min | 30min | 1hr                               ║
 * ║                                                                  ║
 * ║   Pivot Math:                                                    ║
 * ║     PP  = (H + L + C) / 3                                       ║
 * ║     S1  = (2 × PP) − H    → BUY zone (price may bounce here)   ║
 * ║     S2  = PP − (H − L)    → Stronger support                   ║
 * ║     R1  = (2 × PP) − L    → SELL zone (price may stall here)   ║
 * ║     R2  = PP + (H − L)    → Stronger resistance                ║
 * ║                                                                  ║
 * ║   Signal rules:                                                  ║
 * ║     • Close > R1              → BREAKOUT (bullish above R1)     ║
 * ║     • Close > PP and < R1     → BULLISH                         ║
 * ║     • Close < S1              → BREAKDOWN (bearish below S1)    ║
 * ║     • Close < PP and > S1     → BEARISH                         ║
 * ║     • Close ≈ PP (±0.2%)      → NEUTRAL at pivot                ║
 * ║     • S1 match: Low ≤ S1      → Price touched support           ║
 * ║     • R1 match: High ≥ R1     → Price touched resistance        ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */
class PivotAnalysisController extends Controller
{
    // Valid timeframes and their display labels
    private const TIMEFRAMES = [
        '15min' => '15 Min',
        '30min' => '30 Min',
        '1hr'   => '1 Hour',
    ];

    // Instrument type → table prefix mapping
    private const TABLES = [
        'stock'  => 'cp_stock_ohlc',
        'fut'    => 'cp_fut_ohlc',
        'option' => 'cp_option_ohlc',
    ];

    // ── Page ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Pivot Point Analysis';
        return view($this->activeTemplate . 'user.pivot-analysis.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STOCK PIVOT API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /pivot-analysis/stock/signals
     * Returns pivot candle signals for the stock EQ table.
     */
    public function stockSignals(Request $request)
    {
        try {
            $timeframe  = $this->resolveTimeframe($request);
            $dateInput  = $request->get('date');
            $today      = $dateInput ? Carbon::parse($dateInput)->toDateString() : Carbon::today()->toDateString();
            $symbolReq  = strtoupper(trim($request->get('symbol', 'ALL')));

            $table = self::TABLES['stock'] . '_' . $timeframe;

            // Get active config for this timeframe
            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return $this->noConfig($timeframe, 'stock');
            }

            // Symbols from config — joined through symbol_lists (pivot has no 'symbol' column)
            $configSymbols = $this->getConfigSymbols($config->id);

            if (empty($configSymbols)) {
                return $this->emptyResponse($today, [], 'No symbols configured for this timeframe.');
            }

            // ALL → one row per symbol (latest candle only)
            // Specific symbol → all candles from 9:15 to 15:15
            $isAll   = ($symbolReq === 'ALL');
            $symbols = (!$isAll && in_array($symbolReq, $configSymbols))
                ? [$symbolReq]
                : $configSymbols;

            $results = [];

            foreach ($symbols as $symbol) {
                $rows = DB::table($table)
                    ->where('analysis_config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->whereDate('trade_date', $today)
                    ->where('is_missing', false)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'trade_date', 'trading_symbol',
                           'open', 'high', 'low', 'close', 'volume'])
                    ->toArray();

                if (empty($rows)) continue;

                $latestRow = end($rows);

                // ALL mode  → only latest candle per symbol (summary view)
                // Detail mode → all candles for that symbol (full day view)
                $rowsToSignal = $isAll ? [$latestRow] : $rows;
                $signals      = $this->buildPivotSignals($rowsToSignal, 'stock', $symbol);

                $results[] = [
                    'symbol'         => $symbol,
                    'trading_symbol' => $latestRow->trading_symbol ?? $symbol,
                    'date'           => $today,
                    'instrument'     => 'STOCK',
                    'timeframe'      => $timeframe,
                    'mode'           => $isAll ? 'summary' : 'detail',
                    'total_candles'  => count($rows),
                    'latest_time'    => substr($latestRow->interval_time, 11, 5),
                    'latest_close'   => round((float)$latestRow->close, 2),
                    'signals'        => $signals,
                ];
            }

            return $this->successResponse($results, $today, $configSymbols, $timeframe);

        } catch (\Exception $e) {
            Log::error('PivotAnalysis stockSignals: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FUT PIVOT API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /pivot-analysis/fut/signals
     * Returns pivot candle signals for the FUT table.
     */
    public function futSignals(Request $request)
    {
        try {
            $timeframe = $this->resolveTimeframe($request);
            $dateInput = $request->get('date');
            $today     = $dateInput ? Carbon::parse($dateInput)->toDateString() : Carbon::today()->toDateString();
            $symbolReq = strtoupper(trim($request->get('symbol', 'ALL')));

            $table = self::TABLES['fut'] . '_' . $timeframe;

            $config = $this->getActiveConfig($timeframe);
            if (!$config) return $this->noConfig($timeframe, 'fut');

            // JOIN symbol_lists — pivot table has no 'symbol' column directly
            $configSymbols = $this->getConfigSymbols($config->id);

            if (empty($configSymbols)) {
                return $this->emptyResponse($today, [], 'No symbols configured for this timeframe.');
            }

            // ALL → one row per symbol (latest candle only)
            // Specific symbol → all candles from 9:15 to 15:15
            $isAll   = ($symbolReq === 'ALL');
            $symbols = (!$isAll && in_array($symbolReq, $configSymbols))
                ? [$symbolReq]
                : $configSymbols;

            $results = [];

            foreach ($symbols as $symbol) {
                // Get nearest expiry for this date
                $expiry = DB::table($table)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $today)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

                if (!$expiry) continue;

                $rows = DB::table($table)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('expiry_date', $expiry)
                    ->whereDate('trade_date', $today)
                    ->where('is_missing', false)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'trade_date', 'trading_symbol', 'expiry_date',
                           'atm_strike', 'open', 'high', 'low', 'close', 'volume', 'oi'])
                    ->toArray();

                if (empty($rows)) continue;

                $latestRow    = end($rows);
                $rowsToSignal = $isAll ? [$latestRow] : $rows;
                $signals      = $this->buildPivotSignals($rowsToSignal, 'fut', $symbol);

                $results[] = [
                    'symbol'         => $symbol,
                    'trading_symbol' => $latestRow->trading_symbol ?? $symbol,
                    'expiry'         => $expiry,
                    'date'           => $today,
                    'instrument'     => 'FUT',
                    'timeframe'      => $timeframe,
                    'mode'           => $isAll ? 'summary' : 'detail',
                    'total_candles'  => count($rows),
                    'atm_strike'     => $latestRow->atm_strike ?? null,
                    'latest_time'    => substr($latestRow->interval_time, 11, 5),
                    'latest_close'   => round((float)$latestRow->close, 2),
                    'latest_oi'      => (int)$latestRow->oi,
                    'signals'        => $signals,
                ];
            }

            return $this->successResponse($results, $today, $configSymbols, $timeframe);

        } catch (\Exception $e) {
            Log::error('PivotAnalysis futSignals: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OPTION PIVOT API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /pivot-analysis/option/signals
     * Returns pivot candle signals for ATM CE and PE options.
     */
    public function optionSignals(Request $request)
    {
        try {
            $timeframe = $this->resolveTimeframe($request);
            $dateInput = $request->get('date');
            $today     = $dateInput ? Carbon::parse($dateInput)->toDateString() : Carbon::today()->toDateString();
            $symbolReq = strtoupper(trim($request->get('symbol', 'ALL')));

            $table = self::TABLES['option'] . '_' . $timeframe;

            $config = $this->getActiveConfig($timeframe);
            if (!$config) return $this->noConfig($timeframe, 'option');

            // JOIN symbol_lists — pivot table has no 'symbol' column directly
            $configSymbols = $this->getConfigSymbols($config->id);

            if (empty($configSymbols)) {
                return $this->emptyResponse($today, [], 'No symbols configured for this timeframe.');
            }

            // ALL → one row per symbol (latest candle only)
            // Specific symbol → all candles from 9:15 to 15:15
            $isAll   = ($symbolReq === 'ALL');
            $symbols = (!$isAll && in_array($symbolReq, $configSymbols))
                ? [$symbolReq]
                : $configSymbols;

            $results = [];

            foreach ($symbols as $symbol) {
                // Get nearest expiry for this date
                $expiry = DB::table($table)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $today)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

                if (!$expiry) continue;

                // Load ATM CE and PE rows for this date+expiry
                $allRows = DB::table($table)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('expiry_date', $expiry)
                    ->whereDate('trade_date', $today)
                    ->where('strike_position', 'ATM')
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->where('is_missing', false)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'trade_date', 'trading_symbol', 'expiry_date',
                           'instrument_type', 'atm_strike', 'strike', 'future_price',
                           'open', 'high', 'low', 'close', 'volume', 'oi'])
                    ->toArray();

                if (empty($allRows)) continue;

                // Split by type
                $ceRows = array_values(array_filter($allRows, fn($r) => $r->instrument_type === 'CE'));
                $peRows = array_values(array_filter($allRows, fn($r) => $r->instrument_type === 'PE'));

                $latestCe = !empty($ceRows) ? end($ceRows) : null;
                $latestPe = !empty($peRows) ? end($peRows) : null;

                // ALL mode → only latest candle per type; detail mode → all candles
                $ceToSignal = $isAll ? ($latestCe ? [$latestCe] : []) : $ceRows;
                $peToSignal = $isAll ? ($latestPe ? [$latestPe] : []) : $peRows;

                $ceSignals = $this->buildPivotSignals($ceToSignal, 'option_ce', $symbol);
                $peSignals = $this->buildPivotSignals($peToSignal, 'option_pe', $symbol);

                $atmStrike = $allRows[0]->atm_strike ?? null;

                $results[] = [
                    'symbol'         => $symbol,
                    'expiry'         => $expiry,
                    'date'           => $today,
                    'instrument'     => 'OPTION',
                    'timeframe'      => $timeframe,
                    'mode'           => $isAll ? 'summary' : 'detail',
                    'atm_strike'     => $atmStrike,
                    'total_candles'  => count($ceRows),
                    'ce_trading_sym' => $latestCe ? $latestCe->trading_symbol : null,
                    'pe_trading_sym' => $latestPe ? $latestPe->trading_symbol : null,
                    'ce_ltp'         => $latestCe ? round((float)$latestCe->close, 2) : null,
                    'pe_ltp'         => $latestPe ? round((float)$latestPe->close, 2) : null,
                    'latest_time'    => $latestCe ? substr($latestCe->interval_time, 11, 5) : null,
                    'ce_signals'     => $ceSignals,
                    'pe_signals'     => $peSignals,
                ];
            }

            return $this->successResponse($results, $today, $configSymbols, $timeframe);

        } catch (\Exception $e) {
            Log::error('PivotAnalysis optionSignals: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CORE PIVOT CALCULATOR
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Build pivot signals from an array of OHLC rows (stdClass objects).
     *
     * Pivot formulas:
     *   PP = (H + L + C) / 3
     *   S1 = 2×PP − H            (first support)
     *   S2 = PP − (H − L)        (second support)
     *   R1 = 2×PP − L            (first resistance)
     *   R2 = PP + (H − L)        (second resistance)
     *
     * Candle signal:
     *   Close vs PP / R1 / S1 determines bias.
     *   S1/R1 match tells if price actually touched the level.
     *
     * @param  array  $rows   Array of stdClass with open/high/low/close etc.
     * @param  string $type   'stock' | 'fut' | 'option_ce' | 'option_pe'
     * @param  string $symbol Base symbol name
     * @return array
     */
    private function buildPivotSignals(array $rows, string $type, string $symbol): array
    {
        $signals = [];

        foreach ($rows as $candle) {
            $O = (float)($candle->open  ?? 0);
            $H = (float)($candle->high  ?? 0);
            $L = (float)($candle->low   ?? 0);
            $C = (float)($candle->close ?? 0);

            if ($H === 0.0 && $L === 0.0) continue; // skip empty rows

            // Core pivot levels
            $PP = round(($H + $L + $C) / 3, 2);
            $R1 = round((2 * $PP) - $L, 2);
            $R2 = round($PP + ($H - $L), 2);
            $S1 = round((2 * $PP) - $H, 2);
            $S2 = round($PP - ($H - $L), 2);

            $range  = round($H - $L, 2);
            $midR1  = round(($PP + $R1) / 2, 2); // mid between PP and R1
            $midS1  = round(($PP + $S1) / 2, 2); // mid between PP and S1

            // Price position signal
            $signal    = $this->calcPriceSignal($C, $PP, $R1, $R2, $S1, $S2);
            $bias      = $signal['bias'];
            $label     = $signal['label'];
            $strength  = $signal['strength'];

            // Level touches
            $s1Match   = ($L <= $S1);              // Low touched S1 support
            $r1Match   = ($H >= $R1);              // High touched R1 resistance
            $s2Match   = ($L <= $S2);
            $r2Match   = ($H >= $R2);
            $ppCross   = ($O < $PP && $C > $PP) || ($O > $PP && $C < $PP); // PP crossover candle

            $time = substr($candle->interval_time, 11, 5);

            $row = [
                'time'          => $time,
                'type'          => strtoupper($type),
                'trading_symbol'=> $candle->trading_symbol ?? $symbol,
                'open'          => round($O, 2),
                'high'          => round($H, 2),
                'low'           => round($L, 2),
                'close'         => round($C, 2),
                'volume'        => (int)($candle->volume ?? 0),
                'PP'            => $PP,
                'R1'            => $R1,
                'R2'            => $R2,
                'S1'            => $S1,
                'S2'            => $S2,
                'mid_r1'        => $midR1,
                'mid_s1'        => $midS1,
                'range'         => $range,
                'signal'        => $label,
                'bias'          => $bias,
                'strength'      => $strength,
                's1_match'      => $s1Match,
                'r1_match'      => $r1Match,
                's2_match'      => $s2Match,
                'r2_match'      => $r2Match,
                'pp_cross'      => $ppCross,
            ];

            // Extra fields for FUT
            if (isset($candle->oi)) {
                $row['oi'] = (int)$candle->oi;
            }

            // Extra fields for options
            if (isset($candle->instrument_type)) {
                $row['instrument_type'] = $candle->instrument_type;
                $row['strike']          = $candle->strike ?? null;
                $row['atm_strike']      = $candle->atm_strike ?? null;
                $row['future_price']    = isset($candle->future_price) ? round((float)$candle->future_price, 2) : null;
                $row['oi']              = (int)($candle->oi ?? 0);
            }

            $signals[] = $row;
        }

        return $signals;
    }

    /**
     * Determine price signal relative to pivot levels.
     *
     * Returns:
     *   bias     : BULLISH | BEARISH | NEUTRAL
     *   label    : Human-readable signal name
     *   strength : STRONG | MODERATE | WEAK
     */
    private function calcPriceSignal(
        float $close,
        float $PP,
        float $R1,
        float $R2,
        float $S1,
        float $S2
    ): array {
        // Neutral band = PP ± 0.2%
        $neutralBand = $PP * 0.002;

        if (abs($close - $PP) <= $neutralBand) {
            return ['bias' => 'NEUTRAL', 'label' => 'At Pivot', 'strength' => 'WEAK'];
        }

        if ($close >= $R2) {
            return ['bias' => 'BULLISH', 'label' => 'Above R2', 'strength' => 'STRONG'];
        }

        if ($close >= $R1) {
            return ['bias' => 'BULLISH', 'label' => 'Above R1', 'strength' => 'STRONG'];
        }

        if ($close > $PP) {
            // Between PP and R1
            $midR1 = ($PP + $R1) / 2;
            if ($close >= $midR1) {
                return ['bias' => 'BULLISH', 'label' => 'Near R1', 'strength' => 'MODERATE'];
            }
            return ['bias' => 'BULLISH', 'label' => 'Above PP', 'strength' => 'WEAK'];
        }

        if ($close <= $S2) {
            return ['bias' => 'BEARISH', 'label' => 'Below S2', 'strength' => 'STRONG'];
        }

        if ($close <= $S1) {
            return ['bias' => 'BEARISH', 'label' => 'Below S1', 'strength' => 'STRONG'];
        }

        // Between S1 and PP
        $midS1 = ($PP + $S1) / 2;
        if ($close <= $midS1) {
            return ['bias' => 'BEARISH', 'label' => 'Near S1', 'strength' => 'MODERATE'];
        }

        return ['bias' => 'BEARISH', 'label' => 'Below PP', 'strength' => 'WEAK'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Get the active AnalysisConfig for a given timeframe.
     *
     * NOTE: is_active is stored as tinyint(1) — comparing with 1 works for
     * both MySQL and SQLite. The cast in the Model only applies to Eloquent;
     * DB::table() returns raw values.
     */
    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', $timeframe)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Get configured symbols for a given analysis config ID.
     *
     * analysis_config_symbols pivot table schema:
     *   - analysis_config_id  (FK → analysis_configs.id)
     *   - symbol_list_id      (FK → symbol_lists.id)
     *
     * symbol_lists table schema:
     *   - id
     *   - underlying   (e.g. "NIFTY 50")
     *   - symbol       (e.g. "NIFTY")   ← this is what we need
     *
     * We must JOIN symbol_lists to get the symbol string.
     * Directly plucking from analysis_config_symbols will fail because
     * that pivot table has NO "symbol" column.
     *
     * @return string[]  e.g. ['NIFTY', 'BANKNIFTY', 'RELIANCE']
     */
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
        return in_array($tf, array_keys(self::TIMEFRAMES)) ? $tf : '15min';
    }

    private function noConfig(string $timeframe, string $instrument): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success'    => false,
            'data'       => [],
            'message'    => "No active analysis config found for timeframe [{$timeframe}]. Please create one in Admin → Analysis Config.",
            'no_config'  => true,
            'timeframe'  => $timeframe,
            'instrument' => $instrument,
        ]);
    }

    private function emptyResponse(string $today, array $symbols, string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success'            => true,
            'data'               => [],
            'today'              => $today,
            'is_today'           => $today === Carbon::today()->toDateString(),
            'available_symbols'  => $symbols,
            'message'            => $message,
        ]);
    }

    private function successResponse(array $results, string $today, array $configSymbols, string $timeframe): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success'            => true,
            'data'               => $results,
            'today'              => $today,
            'is_today'           => $today === Carbon::today()->toDateString(),
            'timeframe'          => $timeframe,
            'available_symbols'  => $configSymbols,
            'message'            => count($results) . ' symbol(s) loaded for ' . $today,
        ]);
    }

    private function errorResponse(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $message,
            'data'    => [],
        ], 500);
    }
}