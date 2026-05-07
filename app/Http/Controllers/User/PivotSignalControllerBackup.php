<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ThirtyMinOhlcData;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * Unified Pivot Signal + Config Controller
 *
 * - 30-min candles (from 30min_ohlc_data table via ThirtyMinOhlcData model)
 * - Supports any symbol (NIFTY, BANKNIFTY, MCX, BSE from 30min_ohlc_symbols)
 * - By default shows latest row for ALL symbols; filter to show all rows for a specific symbol
 * - Layer-wise order placement: up to 3 S1 layers + 3 R1 layers
 * - PP = (H+L+C)/3 of CURRENT 30-min candle
 * - Each config now has a `symbols` field — orders only placed for selected symbols
 */
class PivotSignalControllerBackup extends Controller
{
    /**
     * Master list of all symbols available for selection.
     * Keep in sync with your 30min_ohlc_symbols table / FREEZE_LIMITS in the command.
     */
    public const ALL_SYMBOLS = [
        'NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY',
        'ADANIPORTS', 'AMBUJACEM', 'ASIANPAINT', 'AUROPHARMA',
        'AXISBANK', 'BAJAJFINSV', 'BAJFINANCE', 'BHARATFORG',
        'BHARTIARTL', 'BHEL', 'BPCL', 'BSE',
        'CDSL', 'COFORGE', 'BDL', 'DELHIVERY',
        'DRREDDY', 'ETERNAL', 'FORTIS', 'HAL',
        'HAVELLS', 'HEROMOTOCO', 'HINDALCO', 'ICICIBANK',
        'INDUSINDBK', 'INFY', 'JSWSTEEL', 'LAURUSLABS',
        'LICHSGFIN', 'LT', 'LTF', 'M&M',
        'NATIONALUM', 'PAYTM', 'PGEL', 'POLICYBZR',
        'SBIN', 'SHRIRAMFIN', 'SRF', 'TATACONSUM',
        'TATAELXSI', 'TATATECH', 'TITAN', 'TMPV',
        'TCS', 'UPL', 'VBL', 'VEDL',
        'VOLTAS', 'MCX',
    ];

    // ── Signal Pages ──────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Pivot Signal';
        return view($this->activeTemplate . 'user.pivot-signal.index', compact('pageTitle'));
    }

    // ── Signal API ────────────────────────────────────────────────────────────

    /**
     * GET /pivot-signal/signals?symbol=ALL|NIFTY|BANKNIFTY|...
     *
     * - symbol=ALL  → return only the LATEST candle row per symbol (summary mode)
     * - symbol=NIFTY → return ALL candle rows for that symbol (detail mode)
     */
    public function getSignals(Request $request)
    {
        try {
            $symbol    = strtoupper(trim($request->get('symbol', 'ALL')));
            $dateInput = $request->get('date');
            $today     = $dateInput
                ? Carbon::parse($dateInput)->toDateString()
                : Carbon::today()->toDateString();

            // Discover which symbols have data on the selected date (from 30min table)
            $availableSymbols = ThirtyMinOhlcData::whereDate('trade_date', $today)
                ->where('is_missing', 0)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->pluck('base_symbol')
                ->sort()
                ->values()
                ->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success'           => true,
                    'data'              => [],
                    'today'             => $today,
                    'is_today'          => $today === Carbon::today()->toDateString(),
                    'message'           => 'No 30-min data found for ' . $today,
                    'available_symbols' => [],
                ]);
            }

            $isAll   = ($symbol === 'ALL');
            $symbols = $isAll ? $availableSymbols : [$symbol];
            $results = [];

            foreach ($symbols as $sym) {

                $expiry = $this->getNearestExpiryForDate($sym, $today);
                if (!$expiry) continue;

                $ceCandles = $this->getCandles($sym, 'CE', $expiry, $today);
                $peCandles = $this->getCandles($sym, 'PE', $expiry, $today);

                if ($ceCandles->isEmpty() && $peCandles->isEmpty()) continue;

                // ALL mode → latest candle only (summary); specific symbol → all candles (detail)
                if ($isAll) {
                    $ceSlice = $ceCandles->last() ? collect([$ceCandles->last()]) : collect();
                    $peSlice = $peCandles->last() ? collect([$peCandles->last()]) : collect();
                } else {
                    $ceSlice = $ceCandles;
                    $peSlice = $peCandles;
                }

                $ceSignals  = $this->buildSignals($ceSlice->toArray(), 'CE');
                $peSignals  = $this->buildSignals($peSlice->toArray(), 'PE');
                $latestCe   = $ceCandles->last();
                $latestPe   = $peCandles->last();
                $allSignals = collect(array_merge($ceSignals, $peSignals))
                    ->sortBy('time')->values()->toArray();

                $results[] = [
                    'symbol'        => $sym,
                    'expiry'        => $expiry,
                    'date'          => $today,
                    'mode'          => $isAll ? 'summary' : 'detail',
                    'total_candles' => $ceCandles->count(),
                    'ce_symbol'     => $latestCe->trading_symbol ?? null,
                    'ce_strike'     => $latestCe->strike         ?? null,
                    'ce_ltp'        => $latestCe ? round((float)$latestCe->close, 2) : null,
                    'pe_symbol'     => $latestPe->trading_symbol ?? null,
                    'pe_strike'     => $latestPe->strike         ?? null,
                    'pe_ltp'        => $latestPe ? round((float)$latestPe->close, 2) : null,
                    'latest_time'   => $latestCe ? substr($latestCe->interval_time, 11, 5) : null,
                    'signals'       => $allSignals,
                    'signal_count'  => count($allSignals),
                ];
            }

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'today'             => $today,
                'is_today'          => $today === Carbon::today()->toDateString(),
                'mode'              => $isAll ? 'summary' : 'detail',
                'available_symbols' => $availableSymbols,
                'message'           => count($results) . ' symbol(s) loaded for ' . $today,
            ]);

        } catch (\Exception $e) {
            Log::error('PivotSignal getSignals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ── Expiry helpers ────────────────────────────────────────────────────────

    private function getNearestExpiryForDate(string $sym, string $date): ?string
    {
        $expiry = ThirtyMinOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return ThirtyMinOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getNearestExpiry(string $sym, string $today): ?string
    {
        return $this->getNearestExpiryForDate($sym, $today);
    }

    // ── Config Pages ──────────────────────────────────────────────────────────

    public function configIndex()
    {
        $pageTitle  = 'Pivot Order Config';
        $allSymbols = self::ALL_SYMBOLS;

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->whereIn('client_type', ['Zerodha', 'AngelOne'])
            ->get();

        $configs = NewPivotOrderConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.pivot-signal.config', compact(
            'pageTitle', 'brokers', 'configs', 'allSymbols'
        ));
    }

    public function configOrders($configId)
    {
        $pageTitle = 'Pivot Orders';

        $config = NewPivotOrderConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = NewPivotOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.pivot-signal.orders', compact(
            'pageTitle', 'config', 'orders'
        ));
    }

    // ── Config CRUD ───────────────────────────────────────────────────────────

    public function configStore(Request $request)
    {
        $request->validate([
            'broker_api_id'                     => 'required|exists:broker_apis,id',
            'symbols'                            => 'required|array|min:1',
            'symbols.*'                          => 'required|string|in:' . implode(',', self::ALL_SYMBOLS),
            'order_type'                         => 'required|in:LIMIT,MARKET',
            'product'                            => 'required|in:NRML,MIS',
            'status'                             => 'required|in:0,1',
            's1_ce_layers'                       => 'required|array|min:1|max:5',
            's1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_ce_layers.*.quantity'            => 'required|integer|min:0',
            's1_pe_layers'                       => 'required|array|min:1|max:5',
            's1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_pe_layers.*.quantity'            => 'required|integer|min:0',
            'r1_ce_layers'                       => 'required|array|min:1|max:5',
            'r1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_ce_layers.*.quantity'            => 'required|integer|min:0',
            'r1_pe_layers'                       => 'required|array|min:1|max:5',
            'r1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_pe_layers.*.quantity'            => 'required|integer|min:0',
        ]);

        try {
            NewPivotOrderConfig::create([
                'user_id'       => Auth::id(),
                'broker_api_id' => $request->broker_api_id,
                'symbols'       => array_map('strtoupper', $request->symbols),
                'order_type'    => $request->order_type,
                'product'       => $request->product,
                's1_ce_layers'  => $request->s1_ce_layers,
                's1_pe_layers'  => $request->s1_pe_layers,
                'r1_ce_layers'  => $request->r1_ce_layers,
                'r1_pe_layers'  => $request->r1_pe_layers,
                'status'        => $request->status,
            ]);

            return response()->json(['success' => true, 'message' => 'Config created successfully!']);
        } catch (\Exception $e) {
            Log::error('PivotSignal configStore: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configUpdate(Request $request, $id)
    {
        $request->validate([
            'broker_api_id'                     => 'required|exists:broker_apis,id',
            'symbols'                            => 'required|array|min:1',
            'symbols.*'                          => 'required|string|in:' . implode(',', self::ALL_SYMBOLS),
            'order_type'                         => 'required|in:LIMIT,MARKET',
            'product'                            => 'required|in:NRML,MIS',
            'status'                             => 'required|in:0,1',
            's1_ce_layers'                       => 'required|array|min:1|max:5',
            's1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_ce_layers.*.quantity'            => 'required|integer|min:0',
            's1_pe_layers'                       => 'required|array|min:1|max:5',
            's1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_pe_layers.*.quantity'            => 'required|integer|min:0',
            'r1_ce_layers'                       => 'required|array|min:1|max:5',
            'r1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_ce_layers.*.quantity'            => 'required|integer|min:0',
            'r1_pe_layers'                       => 'required|array|min:1|max:5',
            'r1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_pe_layers.*.quantity'            => 'required|integer|min:0',
        ]);

        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->update([
                'broker_api_id' => $request->broker_api_id,
                'symbols'       => array_map('strtoupper', $request->symbols),
                'order_type'    => $request->order_type,
                'product'       => $request->product,
                's1_ce_layers'  => $request->s1_ce_layers,
                's1_pe_layers'  => $request->s1_pe_layers,
                'r1_ce_layers'  => $request->r1_ce_layers,
                'r1_pe_layers'  => $request->r1_pe_layers,
                'status'        => $request->status,
            ]);

            return response()->json(['success' => true, 'message' => 'Config updated!']);
        } catch (\Exception $e) {
            Log::error('PivotSignal configUpdate: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configToggle($id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $config->status = !$config->status;
            $config->save();
            $label = $config->status ? 'activated' : 'deactivated';
            return back()->withNotify([['success', "Config {$label}!"]]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error updating status.']]);
        }
    }

    public function configDestroy($id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $pending = $config->orders()->where('is_order_placed', false)->where('status', true)->count();

            $config->delete();
            return back()->withNotify([['success', 'Config deleted!']]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error deleting config.']]);
        }
    }

    // ── Data helpers ──────────────────────────────────────────────────────────

    private function getCandles(string $sym, string $type, string $expiry, string $today)
    {
        return ThirtyMinOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $today)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['trading_symbol', 'strike', 'open', 'high', 'low', 'close', 'interval_time'])
            ->values();
    }

    /**
     * Build pivot signals for a set of 30-min candles.
     * PP = (H+L+C)/3 of the CURRENT 30-min candle.
     * R1 = 2*PP − L
     * S1 = 2*PP − H
     */
    private function buildSignals(array $candles, string $type): array
    {
        $signals = [];
        foreach ($candles as $candle) {
            $O = (float)$candle['open'];
            $H = (float)$candle['high'];
            $L = (float)$candle['low'];
            $C = (float)$candle['close'];

            $PP    = round(($H + $L + $C) / 3, 2);
            $R1    = round((2 * $PP) - $L, 2);
            $S1    = round((2 * $PP) - $H, 2);
            $range = round($H - $L, 2);

            $signals[] = [
                'time'          => substr($candle['interval_time'], 11, 5),
                'type'          => $type,
                'option_symbol' => $candle['trading_symbol'],
                'strike'        => $candle['strike'],
                'open'          => round($O, 2),
                'high'          => round($H, 2),
                'low'           => round($L, 2),
                'close'         => round($C, 2),
                'PP'            => $PP,
                'R1'            => $R1,
                'S1'            => $S1,
                'range'         => $range,
                's1_match'      => $S1 >= $L,
                'r1_match'      => $R1 >= $H,
            ];
        }
        return $signals;
    }

    public function configRunNow(Request $request, $id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            if (!$config->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Config is inactive. Activate it first.',
                ], 422);
            }

            if (!$config->hasSymbols()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No symbols selected for this config. Please edit and select at least one symbol.',
                ], 422);
            }

            $exitCode = \Artisan::call('pivot:place-orders', ['--config' => $id]);
            $output   = trim(\Artisan::output());

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0
                    ? 'Orders triggered successfully!'
                    : 'Command finished with warnings.',
                'output'  => $output ?: 'No output.',
            ]);

        } catch (\Exception $e) {
            Log::error('PivotSignal configRunNow: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}