<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mcx3HrOhlcData;
use App\Models\McxPivotOrderConfig;
use App\Models\McxPivotOrder;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * McxPivotController
 *
 * Handles:
 *  - Analysis page  : /mcx-pivot/analysis    (3-Hr candles + PP/S1/R1 signals)
 *  - Config page    : /mcx-pivot/config       (CRUD for McxPivotOrderConfig)
 *  - Orders page    : /mcx-pivot/config/{id}/orders
 *
 * Data source: mcx_3hr_ohlc_data table (3 slots per day: 09:00 / 12:00 / 15:00)
 * Symbols    : CRUDEOIL, CRUDEOILM, NATURALGAS
 */
class McxPivotController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // Analysis Pages & API
    // ══════════════════════════════════════════════════════════════════════════

    public function analysis()
    {
        $pageTitle = 'MCX Pivot Analysis';
        return view($this->activeTemplate . 'user.mcx-pivot.analysis', compact('pageTitle'));
    }

    /**
     * GET /mcx-pivot/signals?symbol=ALL|CRUDEOIL|CRUDEOILM|NATURALGAS&date=Y-m-d
     *
     * symbol=ALL  → latest candle per symbol (summary)
     * symbol=CRUDEOIL → all 3-Hr candles for that symbol (detail)
     */
    public function getSignals(Request $request)
    {
        try {
            $symbol = strtoupper(trim($request->get('symbol', 'ALL')));
            $date   = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            // Discover which MCX symbols have 3Hr data on this date
            $availableSymbols = Mcx3HrOhlcData::whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success'           => true,
                    'data'              => [],
                    'date'              => $date,
                    'available_symbols' => [],
                    'message'           => 'No 3-Hr MCX data for ' . $date,
                ]);
            }

            $isAll   = ($symbol === 'ALL');
            $symbols = $isAll ? $availableSymbols : [$symbol];
            $results = [];

            foreach ($symbols as $sym) {
                $expiry = $this->getNearestExpiry($sym, $date);
                if (!$expiry) continue;

                $ceCandles = $this->getCandles($sym, 'CE', $expiry, $date);
                $peCandles = $this->getCandles($sym, 'PE', $expiry, $date);

                if ($ceCandles->isEmpty() && $peCandles->isEmpty()) continue;

                // Summary mode → latest candle only; detail → all 3 slots
                $ceSlice = $isAll
                    ? ($ceCandles->last() ? collect([$ceCandles->last()]) : collect())
                    : $ceCandles;
                $peSlice = $isAll
                    ? ($peCandles->last() ? collect([$peCandles->last()]) : collect())
                    : $peCandles;

                $ceSignals  = $this->buildSignals($ceSlice->toArray(), 'CE');
                $peSignals  = $this->buildSignals($peSlice->toArray(), 'PE');
                $latestCe   = $ceCandles->last();
                $latestPe   = $peCandles->last();
                $allSignals = collect(array_merge($ceSignals, $peSignals))
                    ->sortBy('time')->values()->toArray();

                // Get FUT close for context
                $futRow = Mcx3HrOhlcData::where('base_symbol', $sym)
                    ->where('instrument_type', 'FUT')
                    ->whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->orderByDesc('interval_time')
                    ->first();

                $results[] = [
                    'symbol'        => $sym,
                    'expiry'        => $expiry,
                    'date'          => $date,
                    'mode'          => $isAll ? 'summary' : 'detail',
                    'total_slots'   => $ceCandles->count(),
                    'fut_ltp'       => $futRow ? round((float)$futRow->close, 2) : null,
                    'atm_strike'    => $futRow ? $futRow->atm_strike : null,
                    'ce_symbol'     => $latestCe->trading_symbol ?? null,
                    'ce_strike'     => $latestCe->strike         ?? null,
                    'ce_ltp'        => $latestCe ? round((float)$latestCe->close, 2) : null,
                    'pe_symbol'     => $latestPe->trading_symbol ?? null,
                    'pe_strike'     => $latestPe->strike         ?? null,
                    'pe_ltp'        => $latestPe ? round((float)$latestPe->close, 2) : null,
                    'latest_slot'   => $latestCe
                        ? substr($latestCe->interval_time, 11, 5)
                        : null,
                    'signals'       => $allSignals,
                    'signal_count'  => count($allSignals),
                ];
            }

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'date'              => $date,
                'is_today'          => $date === Carbon::today()->toDateString(),
                'mode'              => $isAll ? 'summary' : 'detail',
                'available_symbols' => $availableSymbols,
                'message'           => count($results) . ' symbol(s) loaded for ' . $date,
            ]);

        } catch (\Exception $e) {
            Log::error('McxPivot getSignals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Config Pages
    // ══════════════════════════════════════════════════════════════════════════

    public function configIndex()
    {
        $pageTitle = 'MCX Pivot Config';

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = McxPivotOrderConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.mcx-pivot.config', compact(
            'pageTitle', 'brokers', 'configs'
        ));
    }

    public function configOrders($configId)
    {
        $pageTitle = 'MCX Pivot Orders';

        $config = McxPivotOrderConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = McxPivotOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.mcx-pivot.orders', compact(
            'pageTitle', 'config', 'orders'
        ));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Config CRUD
    // ══════════════════════════════════════════════════════════════════════════

    public function configStore(Request $request)
    {
        $request->validate($this->layerRules());

        try {
            McxPivotOrderConfig::create([
                'user_id'       => Auth::id(),
                'broker_api_id' => $request->broker_api_id,
                'order_type'    => $request->order_type,
                'product'       => $request->product,
                's1_ce_layers'  => $request->s1_ce_layers,
                's1_pe_layers'  => $request->s1_pe_layers,
                'r1_ce_layers'  => $request->r1_ce_layers,
                'r1_pe_layers'  => $request->r1_pe_layers,
                'status'        => $request->status,
            ]);

            return response()->json(['success' => true, 'message' => 'MCX config created!']);
        } catch (\Exception $e) {
            Log::error('McxPivot configStore: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configUpdate(Request $request, $id)
    {
        $request->validate($this->layerRules());

        try {
            $config = McxPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->update([
                'broker_api_id' => $request->broker_api_id,
                'order_type'    => $request->order_type,
                'product'       => $request->product,
                's1_ce_layers'  => $request->s1_ce_layers,
                's1_pe_layers'  => $request->s1_pe_layers,
                'r1_ce_layers'  => $request->r1_ce_layers,
                'r1_pe_layers'  => $request->r1_pe_layers,
                'status'        => $request->status,
            ]);

            return response()->json(['success' => true, 'message' => 'MCX config updated!']);
        } catch (\Exception $e) {
            Log::error('McxPivot configUpdate: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configToggle($id)
    {
        try {
            $config = McxPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail();
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
            $config = McxPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail();

            $pending = $config->orders()->where('is_order_placed', false)->where('status', true)->count();

            $config->delete();
            return back()->withNotify([['success', 'Config deleted!']]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error deleting config.']]);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function layerRules(): array
    {
        $rules = [];
        foreach (['s1_ce_layers', 's1_pe_layers', 'r1_ce_layers', 'r1_pe_layers'] as $field) {
            $rules[$field]                               = 'required|array|min:1|max:5';
            $rules["{$field}.*.discount_direction"]      = 'required|in:positive,negative';
            $rules["{$field}.*.discount_pct"]            = 'required|numeric|min:0|max:100';
            $rules["{$field}.*.quantity"]                = 'required|integer|min:0';
        }
        $rules['broker_api_id'] = 'required|exists:broker_apis,id';
        $rules['order_type']    = 'required|in:LIMIT,MARKET';
        $rules['product']       = 'required|in:NRML,MIS';
        $rules['status']        = 'required|in:0,1';
        return $rules;
    }

    private function getNearestExpiry(string $sym, string $date): ?string
    {
        $e = Mcx3HrOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        return $e ?? Mcx3HrOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getCandles(string $sym, string $type, string $expiry, string $date)
    {
        return Mcx3HrOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['trading_symbol', 'strike', 'open', 'high', 'low', 'close', 'interval_time'])
            ->values();
    }

    /**
     * Build pivot signals from 3-Hr candles.
     * PP = (H+L+C)/3   R1 = 2*PP − L   S1 = 2*PP − H
     */
    private function buildSignals(array $candles, string $type): array
    {
        $signals = [];
        foreach ($candles as $candle) {
            $H = (float)$candle['high'];
            $L = (float)$candle['low'];
            $C = (float)$candle['close'];
            $PP = round(($H + $L + $C) / 3, 2);
            $S1 = round((2 * $PP) - $H, 2);
            $R1 = round((2 * $PP) - $L, 2);

            $signals[] = [
                'time'          => substr($candle['interval_time'], 11, 5),
                'type'          => $type,
                'option_symbol' => $candle['trading_symbol'],
                'strike'        => $candle['strike'],
                'open'          => round((float)$candle['open'], 2),
                'high'          => round($H, 2),
                'low'           => round($L, 2),
                'close'         => round($C, 2),
                'PP'            => $PP,
                'R1'            => $R1,
                'S1'            => $S1,
                'range'         => round($H - $L, 2),
                's1_match'      => $S1 >= $L,
                'r1_match'      => $R1 >= $H,
            ];
        }
        return $signals;
    }
}