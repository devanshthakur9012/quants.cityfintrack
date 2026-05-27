<?php
// FILE: app/Http/Controllers/User/PivotAnalysisController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pivot Point Analysis — Stock | FUT | Option
 * Timeframe : 15min ONLY
 *
 * Pivot Math:
 *   PP = (H + L + C) / 3
 *   R1 = (2 × PP) − L       R2 = PP + (H − L)
 *   S1 = (2 × PP) − H       S2 = PP − (H − L)
 */
class PivotAnalysisController extends Controller
{
    // Fixed to 15min — no timeframe switching in UI
    private const TF = '15min';

    private const TABLES = [
        'stock'  => 'cp_stock_ohlc_15min',
        'fut'    => 'cp_fut_ohlc_15min',
        'option' => 'cp_option_ohlc_15min',
    ];

    // ── Page ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Pivot Point Analysis';
        return view(activeTemplate() . 'user.pivot-analysis.index', compact('pageTitle'));
    }

    // ── Stock signals ─────────────────────────────────────────────────────────

    public function stockSignals(Request $request): JsonResponse
    {
        try {
            $today     = $this->resolveDate($request);
            $symbolReq = strtoupper(trim($request->get('symbol', 'ALL')));

            $config = $this->getActiveConfig();
            if (!$config) return $this->noConfig('stock');

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) return $this->emptyResponse($today, []);

            $isAll   = ($symbolReq === 'ALL');
            $symbols = ($isAll || !in_array($symbolReq, $configSymbols))
                ? $configSymbols : [$symbolReq];

            $results = [];

            foreach ($symbols as $symbol) {
                $rows = DB::table(self::TABLES['stock'])
                    ->where('analysis_config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->whereDate('trade_date', $today)
                    ->where('is_missing', false)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'trading_symbol', 'open', 'high', 'low', 'close', 'volume'])
                    ->toArray();

                if (empty($rows)) continue;

                $latest       = end($rows);
                $rowsToSignal = $isAll ? [$latest] : $rows;

                $results[] = [
                    'symbol'        => $symbol,
                    'trading_symbol'=> $latest->trading_symbol ?? $symbol,
                    'date'          => $today,
                    'instrument'    => 'STOCK',
                    'mode'          => $isAll ? 'summary' : 'detail',
                    'total_candles' => count($rows),
                    'latest_time'   => substr($latest->interval_time, 11, 5),
                    'latest_close'  => round((float)$latest->close, 2),
                    'signals'       => $this->buildSignals($rowsToSignal),
                ];
            }

            return $this->successResponse($results, $today, $configSymbols);

        } catch (\Exception $e) {
            Log::error('PivotAnalysis stockSignals: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    // ── FUT signals ───────────────────────────────────────────────────────────

    public function futSignals(Request $request): JsonResponse
    {
        try {
            $today     = $this->resolveDate($request);
            $symbolReq = strtoupper(trim($request->get('symbol', 'ALL')));

            $config = $this->getActiveConfig();
            if (!$config) return $this->noConfig('fut');

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) return $this->emptyResponse($today, []);

            $isAll   = ($symbolReq === 'ALL');
            $symbols = ($isAll || !in_array($symbolReq, $configSymbols))
                ? $configSymbols : [$symbolReq];

            $results = [];

            foreach ($symbols as $symbol) {
                $expiry = DB::table(self::TABLES['fut'])
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $today)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

                if (!$expiry) continue;

                $rows = DB::table(self::TABLES['fut'])
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('expiry_date', $expiry)
                    ->whereDate('trade_date', $today)
                    ->where('is_missing', false)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'trading_symbol', 'expiry_date', 'atm_strike',
                           'open', 'high', 'low', 'close', 'volume', 'oi'])
                    ->toArray();

                if (empty($rows)) continue;

                $latest       = end($rows);
                $rowsToSignal = $isAll ? [$latest] : $rows;

                $results[] = [
                    'symbol'        => $symbol,
                    'trading_symbol'=> $latest->trading_symbol ?? $symbol,
                    'expiry'        => $expiry,
                    'date'          => $today,
                    'instrument'    => 'FUT',
                    'mode'          => $isAll ? 'summary' : 'detail',
                    'total_candles' => count($rows),
                    'atm_strike'    => $latest->atm_strike ?? null,
                    'latest_time'   => substr($latest->interval_time, 11, 5),
                    'latest_close'  => round((float)$latest->close, 2),
                    'latest_oi'     => (int)$latest->oi,
                    'signals'       => $this->buildSignals($rowsToSignal, true),
                ];
            }

            return $this->successResponse($results, $today, $configSymbols);

        } catch (\Exception $e) {
            Log::error('PivotAnalysis futSignals: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    // ── Option signals ────────────────────────────────────────────────────────

    public function optionSignals(Request $request): JsonResponse
    {
        try {
            $today     = $this->resolveDate($request);
            $symbolReq = strtoupper(trim($request->get('symbol', 'ALL')));

            $config = $this->getActiveConfig();
            if (!$config) return $this->noConfig('option');

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) return $this->emptyResponse($today, []);

            $isAll   = ($symbolReq === 'ALL');
            $symbols = ($isAll || !in_array($symbolReq, $configSymbols))
                ? $configSymbols : [$symbolReq];

            $results = [];

            foreach ($symbols as $symbol) {
                $expiry = DB::table(self::TABLES['option'])
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $today)
                    ->orderBy('expiry_date')
                    ->value('expiry_date');

                if (!$expiry) continue;

                $allRows = DB::table(self::TABLES['option'])
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->whereDate('expiry_date', $expiry)
                    ->whereDate('trade_date', $today)
                    ->where('strike_position', 'ATM')
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->where('is_missing', false)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'trading_symbol', 'expiry_date', 'instrument_type',
                           'atm_strike', 'strike', 'future_price',
                           'open', 'high', 'low', 'close', 'volume', 'oi'])
                    ->toArray();

                if (empty($allRows)) continue;

                $ceRows = array_values(array_filter($allRows, fn($r) => $r->instrument_type === 'CE'));
                $peRows = array_values(array_filter($allRows, fn($r) => $r->instrument_type === 'PE'));

                $latestCe = !empty($ceRows) ? end($ceRows) : null;
                $latestPe = !empty($peRows) ? end($peRows) : null;

                $ceToSignal = $isAll ? ($latestCe ? [$latestCe] : []) : $ceRows;
                $peToSignal = $isAll ? ($latestPe ? [$latestPe] : []) : $peRows;

                $results[] = [
                    'symbol'       => $symbol,
                    'expiry'       => $expiry,
                    'date'         => $today,
                    'instrument'   => 'OPTION',
                    'mode'         => $isAll ? 'summary' : 'detail',
                    'atm_strike'   => $allRows[0]->atm_strike ?? null,
                    'total_candles'=> count($ceRows),
                    'ce_sym'       => $latestCe?->trading_symbol,
                    'pe_sym'       => $latestPe?->trading_symbol,
                    'ce_ltp'       => $latestCe ? round((float)$latestCe->close, 2) : null,
                    'pe_ltp'       => $latestPe ? round((float)$latestPe->close, 2) : null,
                    'latest_time'  => $latestCe ? substr($latestCe->interval_time, 11, 5) : null,
                    'ce_signals'   => $this->buildSignals($ceToSignal),
                    'pe_signals'   => $this->buildSignals($peToSignal),
                ];
            }

            return $this->successResponse($results, $today, $configSymbols);

        } catch (\Exception $e) {
            Log::error('PivotAnalysis optionSignals: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PIVOT CALCULATOR
    // ══════════════════════════════════════════════════════════════════════════

    private function buildSignals(array $rows, bool $includOi = false): array
    {
        $signals = [];

        foreach ($rows as $candle) {
            $O = (float)($candle->open  ?? 0);
            $H = (float)($candle->high  ?? 0);
            $L = (float)($candle->low   ?? 0);
            $C = (float)($candle->close ?? 0);

            if ($H === 0.0 && $L === 0.0) continue;

            $PP = round(($H + $L + $C) / 3, 2);
            $R1 = round((2 * $PP) - $L, 2);
            $R2 = round($PP + ($H - $L), 2);
            $S1 = round((2 * $PP) - $H, 2);
            $S2 = round($PP - ($H - $L), 2);

            $sig = $this->calcSignal($C, $PP, $R1, $R2, $S1, $S2);

            $row = [
                'time'           => substr($candle->interval_time, 11, 5),
                'trading_symbol' => $candle->trading_symbol ?? '',
                'open'           => round($O, 2),
                'high'           => round($H, 2),
                'low'            => round($L, 2),
                'close'          => round($C, 2),
                'volume'         => (int)($candle->volume ?? 0),
                'PP'             => $PP,
                'R1'             => $R1, 'R2' => $R2,
                'S1'             => $S1, 'S2' => $S2,
                'range'          => round($H - $L, 2),
                'signal'         => $sig['label'],
                'bias'           => $sig['bias'],
                'strength'       => $sig['strength'],
                's1_match'       => $L <= $S1,
                'r1_match'       => $H >= $R1,
                'pp_cross'       => ($O < $PP && $C > $PP) || ($O > $PP && $C < $PP),
            ];

            if ($includOi || isset($candle->oi)) {
                $row['oi'] = (int)($candle->oi ?? 0);
            }
            if (isset($candle->instrument_type)) {
                $row['instrument_type'] = $candle->instrument_type;
                $row['strike']          = $candle->strike ?? null;
                $row['atm_strike']      = $candle->atm_strike ?? null;
                $row['future_price']    = isset($candle->future_price)
                    ? round((float)$candle->future_price, 2) : null;
            }

            $signals[] = $row;
        }

        return $signals;
    }

    private function calcSignal(float $C, float $PP, float $R1, float $R2, float $S1, float $S2): array
    {
        if (abs($C - $PP) <= $PP * 0.002) {
            return ['bias' => 'NEUTRAL', 'label' => 'At Pivot', 'strength' => 'WEAK'];
        }
        if ($C >= $R2) return ['bias' => 'BULLISH', 'label' => 'Above R2', 'strength' => 'STRONG'];
        if ($C >= $R1) return ['bias' => 'BULLISH', 'label' => 'Above R1', 'strength' => 'STRONG'];
        if ($C > $PP) {
            return $C >= ($PP + $R1) / 2
                ? ['bias' => 'BULLISH', 'label' => 'Near R1',   'strength' => 'MODERATE']
                : ['bias' => 'BULLISH', 'label' => 'Above PP',  'strength' => 'WEAK'];
        }
        if ($C <= $S2) return ['bias' => 'BEARISH', 'label' => 'Below S2', 'strength' => 'STRONG'];
        if ($C <= $S1) return ['bias' => 'BEARISH', 'label' => 'Below S1', 'strength' => 'STRONG'];
        return $C <= ($PP + $S1) / 2
            ? ['bias' => 'BEARISH', 'label' => 'Near S1',  'strength' => 'MODERATE']
            : ['bias' => 'BEARISH', 'label' => 'Below PP', 'strength' => 'WEAK'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

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

    private function resolveDate(Request $request): string
    {
        $d = $request->get('date');
        return $d ? Carbon::parse($d)->toDateString() : Carbon::today()->toDateString();
    }

    private function noConfig(string $instrument): JsonResponse
    {
        return response()->json([
            'success'    => false,
            'no_config'  => true,
            'data'       => [],
            'message'    => 'No active 15min analysis config found. Go to Admin → Analysis Config to create one.',
            'instrument' => $instrument,
        ]);
    }

    private function emptyResponse(string $today, array $symbols): JsonResponse
    {
        return response()->json([
            'success'           => true,
            'data'              => [],
            'today'             => $today,
            'is_today'          => $today === Carbon::today()->toDateString(),
            'available_symbols' => $symbols,
            'message'           => 'No data found for this date.',
        ]);
    }

    private function successResponse(array $results, string $today, array $symbols): JsonResponse
    {
        return response()->json([
            'success'           => true,
            'data'              => $results,
            'today'             => $today,
            'is_today'          => $today === Carbon::today()->toDateString(),
            'available_symbols' => $symbols,
            'message'           => count($results) . ' symbol(s) loaded for ' . $today,
        ]);
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'data' => [], 'message' => $message], 500);
    }
}