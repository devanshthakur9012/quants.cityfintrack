<?php
// FILE: app/Http/Controllers/User/OpenHighLowController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Open=High / Open=Low Signal Analysis
 * Instruments : Stock EQ | FUT | Option (ATM CE/PE)
 * Timeframe   : 15min ONLY (internal — not shown to users)
 *
 * LOGIC:
 *   Fetch the FIRST candle of each day (09:15 slot).
 *   If |Open − High| ≤ tolerance  → OPEN=HIGH  → BUY PE
 *   If |Open − Low|  ≤ tolerance  → OPEN=LOW   → BUY CE
 */
class OpenHighLowController extends Controller
{
    private const TF = '15min';

    private const TABLES = [
        'stock'  => 'cp_stock_ohlc_15min',
        'fut'    => 'cp_fut_ohlc_15min',
        'option' => 'cp_option_ohlc_15min',
    ];

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Open=High / Open=Low Analysis';
        return view(activeTemplate() . 'user.open-high-low.index', compact('pageTitle'));
    }

    // ── Last Available Date ────────────────────────────────────────────────────
    //   Returns the most recent trade_date that actually has 09:15 candle data
    //   for the given instrument, so the frontend defaults to a date with real data.

    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'last_date' => Carbon::today()->toDateString(),
                    'is_today'  => true,
                ]);
            }

            $instrument = $this->resolveInstrument($request);
            $table      = self::TABLES[$instrument];

            $lastDate = DB::table($table)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw("TIME(interval_time) = '09:15:00'")
                ->max('trade_date');

            // Fall back to other tables if nothing found
            if (!$lastDate) {
                foreach (self::TABLES as $key => $tbl) {
                    if ($tbl === $table) continue;
                    $lastDate = DB::table($tbl)
                        ->where('analysis_config_id', $config->id)
                        ->where('is_missing', false)
                        ->whereRaw("TIME(interval_time) = '09:15:00'")
                        ->max('trade_date');
                    if ($lastDate) break;
                }
            }

            $today    = Carbon::today()->toDateString();
            $lastDate = $lastDate
                ? Carbon::parse($lastDate)->toDateString()
                : $today;

            return response()->json([
                'success'   => true,
                'last_date' => $lastDate,
                'is_today'  => $lastDate === $today,
            ]);

        } catch (\Exception $e) {
            Log::error('OpenHighLow lastDate: ' . $e->getMessage());
            return response()->json([
                'success'   => false,
                'last_date' => Carbon::today()->toDateString(),
                'is_today'  => true,
            ]);
        }
    }

    // ── Symbols API ───────────────────────────────────────────────────────────

    public function getSymbols(Request $request): JsonResponse
    {
        $instrument = $this->resolveInstrument($request);
        $symCol     = $instrument === 'stock' ? 'symbol' : 'base_symbol';

        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json(['success' => true, 'symbols' => [], 'no_config' => true]);
        }

        $configSymbols = $this->getConfigSymbols($config->id);

        try {
            $symbols = DB::table(self::TABLES[$instrument])
                ->select($symCol)
                ->distinct()
                ->whereIn($symCol, $configSymbols)
                ->orderBy($symCol)
                ->pluck($symCol)
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            $symbols = $configSymbols;
        }

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // ── Main Analyze API ──────────────────────────────────────────────────────
    //   Now accepts a single `date` param (same as pivot point page).
    //   `from_date` / `to_date` are kept as aliases for backward-compat.

    public function analyze(Request $request): JsonResponse
    {
        try {
            $instrument = $this->resolveInstrument($request);

            // Single date (preferred) — fall back to from/to if sent
            $date     = $request->get('date');
            $fromDate = $date ?? $request->get('from_date');
            $toDate   = $date ?? $request->get('to_date');

            // Accept single symbol string or array; empty = all
            $symbolRaw = $request->get('symbol', $request->get('symbols', []));
            $symbolReq = array_filter(is_array($symbolRaw) ? $symbolRaw : [$symbolRaw]);

            $tolerance = max(0, (float) $request->get('tolerance', 1));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active analysis config found. Go to Admin → Analysis Config.',
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
                'stock'  => $this->analyzeStock($config->id, $fromDate, $toDate, $symbols, $tolerance),
                'fut'    => $this->analyzeFut($config->id, $fromDate, $toDate, $symbols, $tolerance),
                'option' => $this->analyzeOption($config->id, $fromDate, $toDate, $symbols, $tolerance),
            };

            usort($results, fn($a, $b) =>
                strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol'])
            );

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'open_high'         => count(array_filter($results, fn($r) => $r['signal'] === 'OPEN=HIGH')),
                'open_low'          => count(array_filter($results, fn($r) => $r['signal'] === 'OPEN=LOW')),
                'message'           => count($results) . ' signal(s) found',
                'instrument'        => strtoupper($instrument),
                'tolerance'         => $tolerance,
                'available_symbols' => $configSymbols,
                'date'              => $fromDate,
                'is_today'          => $fromDate === Carbon::today()->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('OpenHighLow analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STOCK
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeStock(int $configId, string $from, string $to, array $symbols, float $tol): array
    {
        $table = self::TABLES['stock'];

        $opens = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->whereRaw("TIME(interval_time) = '09:15:00'")
            ->get(['symbol', 'trading_symbol', 'trade_date', 'open', 'high', 'low', 'close', 'volume'])
            ->toArray();

        if (empty($opens)) return [];

        [$stats, $ltp] = $this->dailyStats($table, $configId, 'symbol', $symbols, $from, $to);
        return $this->buildSignals($opens, $stats, $ltp, $tol);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FUT
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeFut(int $configId, string $from, string $to, array $symbols, float $tol): array
    {
        $table = self::TABLES['fut'];

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

        [$stats, $ltp] = $this->dailyStats($table, $configId, 'base_symbol', $symbols, $from, $to);
        return $this->buildSignals($opens, $stats, $ltp, $tol);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OPTION (ATM CE + PE)
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeOption(int $configId, string $from, string $to, array $symbols, float $tol): array
    {
        $table = self::TABLES['option'];

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

        $stats = DB::table($table)
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

        $ltpMap = DB::table($table)
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
            $date = substr($c->trade_date, 0, 10);
            $type = $c->instrument_type;
            $key  = $c->symbol . '|' . $date . '|' . $type;

            $open = (float) $c->open;
            $high = (float) $c->high;
            $low  = (float) $c->low;
            $dH   = abs($open - $high);
            $dL   = abs($open - $low);

            if ($dH > $tol && $dL > $tol) continue;

            $st      = $stats[$key]  ?? null;
            $ltpRow  = $ltpMap[$key] ?? null;
            $dayHigh = $st     ? round((float) $st->day_high,  2) : round($high, 2);
            $dayLow  = $st     ? round((float) $st->day_low,   2) : round($low,  2);
            $ltpVal  = $ltpRow ? round((float) $ltpRow->ltp,   2) : round((float) $c->close, 2);
            $change  = round($ltpVal - $open, 2);
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
                'ltp'         => $ltpVal,
                'change'      => $change,
                'change_pct'  => $chgPct,
                'oi'          => (int) ($c->oi ?? 0),
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

    private function dailyStats(string $table, int $configId, string $symCol, array $symbols, string $from, string $to): array
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

    private function buildSignals(array $opens, array $stats, array $ltp, float $tol): array
    {
        $results = [];

        foreach ($opens as $c) {
            $date  = substr($c->trade_date, 0, 10);
            $open  = (float) $c->open;
            $high  = (float) $c->high;
            $low   = (float) $c->low;
            $dH    = abs($open - $high);
            $dL    = abs($open - $low);

            if ($dH > $tol && $dL > $tol) continue;

            $key     = $c->symbol . '|' . $date;
            $st      = $stats[$key] ?? null;
            $ltpRow  = $ltp[$key]   ?? null;
            $dayHigh = $st     ? round((float) $st->day_high, 2) : round($high, 2);
            $dayLow  = $st     ? round((float) $st->day_low,  2) : round($low,  2);
            $ltpVal  = $ltpRow ? round((float) $ltpRow->ltp,  2) : round((float) $c->close, 2);
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
                'volume'      => (int) ($c->volume ?? 0),
                'oi'          => isset($c->oi) ? (int) $c->oi : null,
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

    // ── Config ────────────────────────────────────────────────────────────────

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', self::TF)
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

    private function resolveInstrument(Request $request): string
    {
        $inst = strtolower(trim($request->get('instrument', 'stock')));
        return in_array($inst, ['stock', 'fut', 'option']) ? $inst : 'stock';
    }
}