<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║   Open=High / Open=Low Signal Analysis                          ║
 * ║   Instruments: Stock EQ | FUT | Option (ATM CE/PE)             ║
 * ║   Timeframes : 15min | 30min | 1hr                              ║
 * ║                                                                  ║
 * ║   LOGIC:                                                         ║
 * ║   Fetch the FIRST candle of each trading day (09:15 slot).      ║
 * ║   If |Open − High| ≤ tolerance  → OPEN=HIGH  → BUY PE          ║
 * ║   If |Open − Low|  ≤ tolerance  → OPEN=LOW   → BUY CE          ║
 * ║                                                                  ║
 * ║   WHY TIMEFRAME MATTERS:                                         ║
 * ║   The 09:15 slot is the first bar recorded. Its OHLC range      ║
 * ║   grows with timeframe:                                          ║
 * ║     15min → 09:15–09:30  (narrow)                               ║
 * ║     30min → 09:15–09:45  (medium)                               ║
 * ║     1hr   → 09:15–10:15  (wide)                                 ║
 * ║   Wider bars naturally push High/Low further from Open, so      ║
 * ║   results ARE different per timeframe — all three are shown.    ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */
class OpenHighLowController extends Controller
{
    private const TIMEFRAMES = ['15min', '30min', '1hr'];

    private const TABLES = [
        'stock'  => 'cp_stock_ohlc',
        'fut'    => 'cp_fut_ohlc',
        'option' => 'cp_option_ohlc',
    ];

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Open=High / Open=Low Analysis';
        return view($this->activeTemplate . 'user.open-high-low.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SYMBOLS API
    // ══════════════════════════════════════════════════════════════════════════

    public function getSymbols(Request $request)
    {
        $timeframe  = $this->resolveTimeframe($request);
        $instrument = $this->resolveInstrument($request);
        $col        = ($instrument === 'stock') ? 'symbol' : 'base_symbol';
        $table      = self::TABLES[$instrument] . '_' . $timeframe;

        $config = $this->getActiveConfig($timeframe);
        if (!$config) {
            return response()->json(['success' => true, 'symbols' => [], 'no_config' => true]);
        }

        $configSymbols = $this->getConfigSymbols($config->id);

        try {
            $symbols = DB::table($table)
                ->select($col)
                ->distinct()
                ->whereIn($col, $configSymbols)
                ->orderBy($col)
                ->pluck($col)
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            $symbols = $configSymbols;
        }

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MAIN ANALYZE API
    // ══════════════════════════════════════════════════════════════════════════

    public function analyze(Request $request)
    {
        try {
            $timeframe  = $this->resolveTimeframe($request);
            $instrument = $this->resolveInstrument($request);
            $fromDate   = $request->get('from_date');
            $toDate     = $request->get('to_date');
            $symbolReq  = array_filter((array)$request->get('symbols', []));
            $tolerance  = max(0, (float)$request->get('tolerance', 1));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates.', 'data' => []]);
            }

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => "No active config for [{$timeframe}]. Go to Admin → Analysis Config.",
                    'data'      => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $results = match ($instrument) {
                'stock'  => $this->analyzeStock($config->id, $timeframe, $fromDate, $toDate, $symbols, $tolerance),
                'fut'    => $this->analyzeFut($config->id, $timeframe, $fromDate, $toDate, $symbols, $tolerance),
                'option' => $this->analyzeOption($config->id, $timeframe, $fromDate, $toDate, $symbols, $tolerance),
            };

            usort($results, fn($a, $b) =>
                strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol'])
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'open_high'     => count(array_filter($results, fn($r) => $r['signal'] === 'OPEN=HIGH')),
                'open_low'      => count(array_filter($results, fn($r) => $r['signal'] === 'OPEN=LOW')),
                'message'       => count($results) . ' signal(s) found',
                'timeframe'     => $timeframe,
                'instrument'    => strtoupper($instrument),
                'tolerance'     => $tolerance,
                'available_symbols' => $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('OpenHighLow analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STOCK EQ
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeStock(int $configId, string $tf, string $from, string $to, array $symbols, float $tol): array
    {
        $table = self::TABLES['stock'] . '_' . $tf;

        $opens = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->whereRaw("TIME(interval_time) = '09:15:00'")
            ->get(['symbol', 'trading_symbol', 'trade_date', 'open', 'high', 'low', 'close', 'volume'])
            ->toArray();

        if (empty($opens)) return [];

        [$stats, $ltp] = $this->dailyStatsGeneric($table, $configId, 'symbol', $symbols, $from, $to);
        return $this->buildSignals($opens, $stats, $ltp, $tol, 'symbol');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FUT
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeFut(int $configId, string $tf, string $from, string $to, array $symbols, float $tol): array
    {
        $table = self::TABLES['fut'] . '_' . $tf;

        $opens = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->whereRaw("TIME(interval_time) = '09:15:00'")
            ->get(['base_symbol as symbol', 'trading_symbol', 'trade_date',
                   'expiry_date', 'atm_strike', 'open', 'high', 'low', 'close', 'volume', 'oi'])
            ->toArray();

        if (empty($opens)) return [];

        [$stats, $ltp] = $this->dailyStatsGeneric($table, $configId, 'base_symbol', $symbols, $from, $to);
        return $this->buildSignals($opens, $stats, $ltp, $tol, 'symbol');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OPTION (ATM CE + PE separate)
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeOption(int $configId, string $tf, string $from, string $to, array $symbols, float $tol): array
    {
        $table = self::TABLES['option'] . '_' . $tf;

        $opens = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->where('strike_position', 'ATM')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '09:15:00'")
            ->get(['base_symbol as symbol', 'trading_symbol', 'instrument_type',
                   'trade_date', 'expiry_date', 'atm_strike', 'strike',
                   'open', 'high', 'low', 'close', 'volume', 'oi'])
            ->toArray();

        if (empty($opens)) return [];

        // Stats keyed by symbol|date|type
        $statsRaw = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->where('strike_position', 'ATM')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->select([
                'base_symbol as symbol', 'instrument_type',
                DB::raw("DATE(trade_date) as trade_day"),
                DB::raw("MAX(high) as day_high"),
                DB::raw("MIN(low)  as day_low"),
            ])
            ->groupBy('base_symbol', 'instrument_type', DB::raw("DATE(trade_date)"))
            ->get()
            ->keyBy(fn($r) => $r->symbol . '|' . $r->trade_day . '|' . $r->instrument_type)
            ->toArray();

        $ltpRaw = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->where('strike_position', 'ATM')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->select([
                'base_symbol as symbol', 'instrument_type',
                DB::raw("DATE(trade_date) as trade_day"),
                DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(`close` ORDER BY interval_time DESC), ',', 1) as ltp"),
            ])
            ->groupBy('base_symbol', 'instrument_type', DB::raw("DATE(trade_date)"))
            ->get()
            ->keyBy(fn($r) => $r->symbol . '|' . $r->trade_day . '|' . $r->instrument_type)
            ->toArray();

        $results = [];
        foreach ($opens as $c) {
            $date = substr(is_string($c->trade_date) ? $c->trade_date : Carbon::parse($c->trade_date)->toDateString(), 0, 10);
            $type = $c->instrument_type;
            $key  = $c->symbol . '|' . $date . '|' . $type;

            $open = (float)$c->open;
            $high = (float)$c->high;
            $low  = (float)$c->low;
            $dH   = abs($open - $high);
            $dL   = abs($open - $low);

            if ($dH > $tol && $dL > $tol) continue;

            $st      = $statsRaw[$key] ?? null;
            $ltpRow  = $ltpRaw[$key]   ?? null;
            $dayHigh = $st     ? round((float)$st->day_high,  2) : round($high, 2);
            $dayLow  = $st     ? round((float)$st->day_low,   2) : round($low,  2);
            $ltp     = $ltpRow ? round((float)$ltpRow->ltp,   2) : round((float)$c->close, 2);
            $change  = round($ltp - $open, 2);
            $chgPct  = $open > 0 ? round(($change / $open) * 100, 2) : 0;

            $base = [
                'date'        => $date,
                'symbol'      => $c->symbol,
                'opt_type'    => $type,
                'atm_strike'  => $c->atm_strike ?? null,
                'trading_sym' => $c->trading_symbol,
                'expiry'      => isset($c->expiry_date) ? substr($c->expiry_date, 0, 10) : null,
                'open'        => round($open, 2),
                'high_open'   => round($high, 2),
                'low_open'    => round($low,  2),
                'day_high'    => $dayHigh,
                'day_low'     => $dayLow,
                'ltp'         => $ltp,
                'change'      => $change,
                'change_pct'  => $chgPct,
                'oi'          => (int)($c->oi ?? 0),
            ];

            if ($dH <= $tol) {
                $results[] = array_merge($base, [
                    'signal'       => 'OPEN=HIGH',
                    'trade_action' => $type === 'CE' ? 'SELL CE' : 'BUY PE',
                ]);
            }
            if ($dL <= $tol) {
                $results[] = array_merge($base, [
                    'signal'       => 'OPEN=LOW',
                    'trade_action' => $type === 'CE' ? 'BUY CE' : 'SELL PE',
                ]);
            }
        }

        return $results;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SHARED HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Generic day-level stats: MAX(high), MIN(low), last close (LTP)
     * keyed by "{symbol}|{date}"
     */
    private function dailyStatsGeneric(string $table, int $configId, string $symCol, array $symbols, string $from, string $to): array
    {
        $stats = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn($symCol, $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->select([
                "{$symCol} as symbol",
                DB::raw("DATE(trade_date) as trade_day"),
                DB::raw("MAX(high) as day_high"),
                DB::raw("MIN(low)  as day_low"),
            ])
            ->groupBy($symCol, DB::raw("DATE(trade_date)"))
            ->get()
            ->keyBy(fn($r) => $r->symbol . '|' . $r->trade_day)
            ->toArray();

        $ltp = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn($symCol, $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->select([
                "{$symCol} as symbol",
                DB::raw("DATE(trade_date) as trade_day"),
                DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(`close` ORDER BY interval_time DESC), ',', 1) as ltp"),
            ])
            ->groupBy($symCol, DB::raw("DATE(trade_date)"))
            ->get()
            ->keyBy(fn($r) => $r->symbol . '|' . $r->trade_day)
            ->toArray();

        return [$stats, $ltp];
    }

    /**
     * Build OPEN=HIGH / OPEN=LOW signals from opening candles.
     */
    private function buildSignals(array $opens, array $stats, array $ltp, float $tol, string $symKey): array
    {
        $results = [];

        foreach ($opens as $c) {
            $date   = substr(is_string($c->trade_date) ? $c->trade_date : Carbon::parse($c->trade_date)->toDateString(), 0, 10);
            $open   = (float)$c->open;
            $high   = (float)$c->high;
            $low    = (float)$c->low;
            $dH     = abs($open - $high);
            $dL     = abs($open - $low);

            if ($dH > $tol && $dL > $tol) continue;

            $key     = $c->symbol . '|' . $date;
            $st      = $stats[$key] ?? null;
            $ltpRow  = $ltp[$key]   ?? null;
            $dayHigh = $st     ? round((float)$st->day_high, 2) : round($high, 2);
            $dayLow  = $st     ? round((float)$st->day_low,  2) : round($low,  2);
            $ltpVal  = $ltpRow ? round((float)$ltpRow->ltp,  2) : round((float)$c->close, 2);
            $change  = round($ltpVal - $open, 2);
            $chgPct  = $open > 0 ? round(($change / $open) * 100, 2) : 0;

            $base = [
                'date'        => $date,
                'symbol'      => $c->symbol,
                'trading_sym' => $c->trading_symbol,
                'expiry'      => isset($c->expiry_date) ? substr($c->expiry_date, 0, 10) : null,
                'atm_strike'  => $c->atm_strike ?? null,
                'open'        => round($open, 2),
                'high_open'   => round($high, 2),
                'low_open'    => round($low,  2),
                'day_high'    => $dayHigh,
                'day_low'     => $dayLow,
                'ltp'         => $ltpVal,
                'change'      => $change,
                'change_pct'  => $chgPct,
                'volume'      => (int)($c->volume ?? 0),
                'oi'          => isset($c->oi) ? (int)$c->oi : null,
            ];

            if ($dH <= $tol) {
                $results[] = array_merge($base, ['signal' => 'OPEN=HIGH', 'trade_action' => 'BUY PE']);
            }
            if ($dL <= $tol) {
                $results[] = array_merge($base, ['signal' => 'OPEN=LOW',  'trade_action' => 'BUY CE']);
            }
        }

        return $results;
    }

    // ── Config helpers ────────────────────────────────────────────────────────

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

    private function resolveInstrument(Request $request): string
    {
        $inst = strtolower(trim($request->get('instrument', 'stock')));
        return in_array($inst, ['stock', 'fut', 'option']) ? $inst : 'stock';
    }
}