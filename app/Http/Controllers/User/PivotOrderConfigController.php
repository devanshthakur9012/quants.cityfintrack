<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PivotOrderConfig;
use App\Models\BrokerApi;
use App\Models\OptionDailyOhlcData;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

/**
 * PivotOrderConfigController
 *
 * QUANTITY NOTE:
 *   Config stores user-entered LOTS (e.g. 5 = 5 lots).
 *   Actual quantity sent to Kite = lots × lot_size from ZerodhaInstrument.
 *   e.g. ADANIPORTS lot_size=475 → 5 lots = 2375 qty.
 *
 * PRICE NOTE:
 *   S3 pivot levels can go negative for cheap options.
 *   Any order with price ≤ 0 is automatically skipped.
 */
class PivotOrderConfigController extends Controller
{
    // =========================================================
    //  INDEX
    // =========================================================
    public function index()
    {
        $pageTitle = 'Pivot Order Config';

        $brokers = BrokerApi::where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        $configs = PivotOrderConfig::where('user_id', Auth::id())
            ->with('brokerApi')
            ->get()
            ->keyBy('broker_api_id');

        return view(
            $this->activeTemplate . 'user.pivot-order-config.index',
            compact('pageTitle', 'brokers', 'configs')
        );
    }

    // =========================================================
    //  SAVE / UPDATE
    // =========================================================
    public function save(Request $request)
    {
        $validated = $request->validate([
            'broker_api_id'    => 'required|exists:broker_apis,id',
            'model_type'       => 'required|in:Standard,Camarilla',
            'instrument_type'  => 'required|in:CE,PE,Both',
            's1_qty'           => 'required|integer|min:0',
            's2_qty'           => 'required|integer|min:0',
            's3_qty'           => 'required|integer|min:0',
            's1_discount'      => 'required|numeric|min:0',
            's1_discount_type' => 'required|in:points,percent',
            's2_discount'      => 'required|numeric|min:0',
            's2_discount_type' => 'required|in:points,percent',
            's3_buffer'        => 'required|numeric|min:0',
            's3_buffer_type'   => 'required|in:points,percent',
        ]);

        $broker = BrokerApi::where('id', $validated['broker_api_id'])
            ->where('user_id', Auth::id())
            ->first();

        if (!$broker) {
            return response()->json(['success' => false, 'message' => 'Broker not found.'], 403);
        }

        $validated['user_id']   = Auth::id();
        $validated['is_active'] = true;

        $config = PivotOrderConfig::updateOrCreate(
            ['user_id' => Auth::id(), 'broker_api_id' => $validated['broker_api_id']],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => "Config saved for {$broker->client_name}.",
            'config'  => $config->load('brokerApi'),
        ]);
    }

    // =========================================================
    //  GET all configs
    // =========================================================
    public function getConfig()
    {
        $configs = PivotOrderConfig::where('user_id', Auth::id())
            ->with('brokerApi')
            ->get();

        return response()->json(['success' => true, 'configs' => $configs]);
    }

    // =========================================================
    //  DELETE config for a broker
    // =========================================================
    public function reset(Request $request)
    {
        $request->validate(['broker_api_id' => 'required|exists:broker_apis,id']);

        PivotOrderConfig::where('user_id', Auth::id())
            ->where('broker_api_id', $request->broker_api_id)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Config deleted.']);
    }

    // =========================================================
    //  TOGGLE active
    // =========================================================
    public function toggle(Request $request)
    {
        $request->validate(['broker_api_id' => 'required|exists:broker_apis,id']);

        $config = PivotOrderConfig::where('user_id', Auth::id())
            ->where('broker_api_id', $request->broker_api_id)
            ->firstOrFail();

        $config->is_active = !$config->is_active;
        $config->save();

        return response()->json([
            'success'   => true,
            'is_active' => $config->is_active,
            'message'   => $config->is_active ? 'Activated.' : 'Deactivated.',
        ]);
    }

    // =========================================================
    //  PREVIEW — compute order prices, no real orders
    // =========================================================
    public function preview(Request $request)
    {
        $request->validate(['broker_api_id' => 'required|exists:broker_apis,id']);

        $config = PivotOrderConfig::where('user_id', Auth::id())
            ->where('broker_api_id', $request->broker_api_id)
            ->first();

        if (!$config) {
            return response()->json(['success' => false, 'message' => 'No config saved for this broker.']);
        }

        $pivotData = $this->fetchLatestPivotData();
        if (empty($pivotData)) {
            return response()->json(['success' => false, 'message' => 'No pivot data found in DB.']);
        }

        $orders = $this->buildOrders($pivotData, $config);

        return response()->json([
            'success'    => true,
            'data_date'  => $pivotData[0]['date'] ?? null,
            'model'      => $config->model_type,
            'instrument' => $config->instrument_type,
            'orders'     => $orders,
            'total'      => count($orders),
        ]);
    }

    // =========================================================
    //  EXECUTE — place real AMO orders
    // =========================================================
    public function execute(Request $request)
    {
        $request->validate(['broker_api_id' => 'required|exists:broker_apis,id']);

        $config = PivotOrderConfig::where('user_id', Auth::id())
            ->where('broker_api_id', $request->broker_api_id)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json(['success' => false, 'message' => 'No active config for this broker.']);
        }

        $broker = BrokerApi::where('id', $request->broker_api_id)
            ->where('user_id', Auth::id())
            ->where('is_token_valid', true)
            ->first();

        if (!$broker) {
            return response()->json(['success' => false, 'message' => 'Broker token invalid.']);
        }

        $pivotData = $this->fetchLatestPivotData();
        if (empty($pivotData)) {
            return response()->json(['success' => false, 'message' => 'No pivot data found.']);
        }

        $orders = $this->buildOrders($pivotData, $config);
        if (empty($orders)) {
            return response()->json(['success' => false, 'message' => 'No valid orders to place (all qty=0 or prices ≤ 0).']);
        }

        $results = $this->placeViaKite($broker, $orders, $config);

        $placed  = collect($results)->where('success', true)->count();
        $skipped = collect($results)->where('skipped', true)->count();
        $failed  = collect($results)->where('success', false)->where('skipped', false)->count();

        return response()->json([
            'success' => true,
            'message' => "{$placed} placed, {$skipped} skipped, {$failed} failed.",
            'results' => $results,
            'placed'  => $placed,
            'skipped' => $skipped,
            'failed'  => $failed,
        ]);
    }

    // =========================================================
    //  FETCH LATEST PIVOT DATA
    // =========================================================
    private function fetchLatestPivotData(): array
    {
        $expiry = $this->resolveLatestExpiry();
        if (!$expiry) return [];

        $latestDate = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->where('is_missing', 0)
            ->whereNotNull('strike_position')
            ->max('trade_date');

        if (!$latestDate) return [];
        $latestDate = Carbon::parse($latestDate)->toDateString();

        $rows = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $latestDate)
            ->whereDate('expiry_date', $expiry)
            ->where('is_missing', 0)
            ->whereNotNull('strike_position')
            ->get([
                'base_symbol', 'instrument_type',
                'high', 'low', 'close', 'volume',
                'trading_symbol', 'strike_position',
            ]);

        if ($rows->isEmpty()) return [];

        // Deduplicate — keep highest volume per strike_position
        $grouped = [];
        foreach ($rows as $r) {
            $existing = $grouped[$r->base_symbol][$r->instrument_type][$r->strike_position] ?? null;
            if (!$existing || ($r->volume ?? 0) >= ($existing->volume ?? 0)) {
                $grouped[$r->base_symbol][$r->instrument_type][$r->strike_position] = $r;
            }
        }

        $results = [];
        foreach ($grouped as $symbol => $typeMap) {
            $ceRow = $this->bestRow($typeMap['CE'] ?? []);
            $peRow = $this->bestRow($typeMap['PE'] ?? []);
            if (!$ceRow && !$peRow) continue;

            // Keys: ce_std, ce_cam, pe_std, pe_cam
            $results[] = [
                'symbol'         => $symbol,
                'date'           => $latestDate,
                'ce_trading_sym' => $ceRow?->trading_symbol,
                'pe_trading_sym' => $peRow?->trading_symbol,
                'ce_std'         => $ceRow ? $this->calcStandard($ceRow->high, $ceRow->low, $ceRow->close)  : null,
                'ce_cam'         => $ceRow ? $this->calcCamarilla($ceRow->high, $ceRow->low, $ceRow->close) : null,
                'pe_std'         => $peRow ? $this->calcStandard($peRow->high, $peRow->low, $peRow->close)  : null,
                'pe_cam'         => $peRow ? $this->calcCamarilla($peRow->high, $peRow->low, $peRow->close) : null,
            ];
        }

        return $results;
    }

    // =========================================================
    //  BUILD ORDERS — resolves lot_size, skips invalid prices
    // =========================================================
    private function buildOrders(array $pivotData, PivotOrderConfig $config): array
    {
        $orders      = [];
        $types       = $config->instrument_type === 'Both' ? ['CE', 'PE'] : [$config->instrument_type];
        $modelSuffix = $config->model_type === 'Standard' ? 'std' : 'cam';

        // Cache lot_size + tick_size per trading symbol to avoid N+1
        $instrumentCache = [];

        foreach ($pivotData as $row) {
            foreach ($types as $type) {
                $pivotKey = strtolower($type) . '_' . $modelSuffix;  // ce_std / ce_cam / pe_std / pe_cam
                $symKey   = strtolower($type) . '_trading_sym';      // ce_trading_sym / pe_trading_sym

                $levels = $row[$pivotKey] ?? null;
                $tsym   = $row[$symKey]   ?? null;

                if (!$levels || !$tsym) continue;

                // ── Resolve lot_size & tick_size ─────────────────
                if (!isset($instrumentCache[$tsym])) {
                    $inst = ZerodhaInstrument::where('trading_symbol', $tsym)
                        ->where('exchange', 'NFO')
                        ->first(['lot_size', 'tick_size']);
                    $instrumentCache[$tsym] = [
                        'lot_size'  => (int) ($inst?->lot_size  ?? 1),
                        'tick_size' => (float) ($inst?->tick_size ?? 0.05),
                    ];
                }
                $lotSize  = $instrumentCache[$tsym]['lot_size'];
                $tickSize = $instrumentCache[$tsym]['tick_size'];

                foreach (['S1', 'S2', 'S3'] as $level) {
                    $lots = $config->qtyFor($level);   // user-entered lots
                    if ($lots <= 0) continue;

                    $rawPrice = $levels[$level] ?? null;
                    if ($rawPrice === null) continue;

                    $orderPrice = $config->effectivePrice((float) $rawPrice, $level);

                    // ── Skip non-positive prices (S3 can go negative) ─
                    if ($orderPrice <= 0) {
                        Log::info("Pivot AMO: Skipping {$type} {$level} {$tsym} — price ₹{$orderPrice} ≤ 0");
                        continue;
                    }

                    // ── Round to tick size, enforce minimum ──────────
                    $finalPrice = $tickSize > 0
                        ? round(round($orderPrice / $tickSize) * $tickSize, 2)
                        : round($orderPrice, 2);
                    $finalPrice = max(0.05, $finalPrice);

                    // ── Actual quantity = lots × lot_size ────────────
                    $actualQty = $lots * $lotSize;

                    $orders[] = [
                        'symbol'      => $row['symbol'],
                        'type'        => $type,
                        'level'       => $level,
                        'trading_sym' => $tsym,
                        'exchange'    => 'NFO',
                        'raw_price'   => round((float) $rawPrice, 2),
                        'order_price' => $finalPrice,
                        'lots'        => $lots,       // display only
                        'lot_size'    => $lotSize,    // display only
                        'qty'         => $actualQty,  // sent to Kite
                    ];
                }
            }
        }

        return $orders;
    }

    // =========================================================
    //  PLACE ORDERS VIA KITE
    // =========================================================
    private function placeViaKite(BrokerApi $broker, array $orders, PivotOrderConfig $config): array
    {
        $kite = new KiteConnect($broker->api_key);
        $kite->setAccessToken($broker->access_token);

        $results = [];

        foreach ($orders as $order) {
            // Final guard — skip if price or qty invalid
            if ($order['order_price'] <= 0 || $order['qty'] <= 0) {
                $results[] = [
                    'success'     => false,
                    'skipped'     => true,
                    'symbol'      => $order['symbol'],
                    'type'        => $order['type'],
                    'level'       => $order['level'],
                    'trading_sym' => $order['trading_sym'],
                    'price'       => $order['order_price'],
                    'qty'         => $order['qty'],
                    'lots'        => $order['lots'],
                    'lot_size'    => $order['lot_size'],
                    'message'     => "Skipped: price ₹{$order['order_price']} or qty {$order['qty']} invalid",
                ];
                continue;
            }

            try {
                $params = [
                    'exchange'         => $order['exchange'],
                    'tradingsymbol'    => $order['trading_sym'],
                    'transaction_type' => 'BUY',
                    'quantity'         => $order['qty'],        // lots × lot_size
                    'product'          => 'MIS',
                    'order_type'       => 'LIMIT',
                    'price'            => $order['order_price'],
                    'validity'         => 'DAY',
                    'variety'          => 'amo',
                ];

                $result  = $kite->placeOrder('amo', $params);
                $orderId = $result->order_id ?? ($result['order_id'] ?? 'unknown');

                $this->saveOrderBook($broker, $orderId, $order, $config, 'PENDING');

                $results[] = [
                    'success'     => true,
                    'skipped'     => false,
                    'order_id'    => $orderId,
                    'symbol'      => $order['symbol'],
                    'type'        => $order['type'],
                    'level'       => $order['level'],
                    'trading_sym' => $order['trading_sym'],
                    'price'       => $order['order_price'],
                    'raw_price'   => $order['raw_price'],
                    'order_price' => $order['order_price'],
                    'qty'         => $order['qty'],
                    'lots'        => $order['lots'],
                    'lot_size'    => $order['lot_size'],
                    'message'     => "Placed @ ₹{$order['order_price']} | {$order['lots']} lots × {$order['lot_size']} = {$order['qty']} qty",
                ];

                usleep(400000);

            } catch (\Exception $e) {
                Log::error("Pivot AMO failed {$order['trading_sym']}: " . $e->getMessage());

                $this->saveOrderBook($broker, null, $order, $config, 'FAILED', $e->getMessage());

                $results[] = [
                    'success'     => false,
                    'skipped'     => false,
                    'symbol'      => $order['symbol'],
                    'type'        => $order['type'],
                    'level'       => $order['level'],
                    'trading_sym' => $order['trading_sym'],
                    'price'       => $order['order_price'],
                    'raw_price'   => $order['raw_price'],
                    'order_price' => $order['order_price'],
                    'qty'         => $order['qty'],
                    'lots'        => $order['lots'],
                    'lot_size'    => $order['lot_size'],
                    'message'     => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    // =========================================================
    //  HELPERS
    // =========================================================
    private function bestRow(array $map): ?object
    {
        $best = null; $bv = -1;
        foreach ($map as $r) {
            $v = $r->volume ?? 0;
            if ($v > $bv) { $bv = $v; $best = $r; }
        }
        return $best;
    }

    private function resolveLatestExpiry(): ?string
    {
        $today  = Carbon::today()->toDateString();
        $expiry = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->orderBy('expiry_date', 'ASC')
            ->value('expiry_date');

        if ($expiry) return Carbon::parse($expiry)->toDateString();

        $expiry = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date', 'DESC')
            ->value('expiry_date');

        return $expiry ? Carbon::parse($expiry)->toDateString() : null;
    }

    private function calcStandard(float $H, float $L, float $C): array
    {
        $P = ($H + $L + $C) / 3; $R = $H - $L;
        return [
            'P'  => round($P, 2), 'BC' => round(($H+$L)/2, 2), 'TC' => round(2*$P-($H+$L)/2, 2),
            'R1' => round(2*$P-$L, 2), 'R2' => round($P+$R, 2), 'R3' => round($H+2*($P-$L), 2),
            'S1' => round(2*$P-$H, 2), 'S2' => round($P-$R, 2), 'S3' => round($L-2*($H-$P), 2),
        ];
    }

    private function calcCamarilla(float $H, float $L, float $C): array
    {
        $R = $H - $L;
        return [
            'R4' => round($C+$R*1.1/2, 2),  'R3' => round($C+$R*1.1/4, 2),
            'R2' => round($C+$R*1.1/6, 2),  'R1' => round($C+$R*1.1/12, 2),
            'S1' => round($C-$R*1.1/12, 2), 'S2' => round($C-$R*1.1/6, 2),
            'S3' => round($C-$R*1.1/4, 2),  'S4' => round($C-$R*1.1/2, 2),
        ];
    }

    private function saveOrderBook(
        BrokerApi $broker,
        ?string $orderId,
        array $order,
        PivotOrderConfig $config,
        string $status,
        string $errorMsg = ''
    ): void {
        try {
            OrderBook::create([
                'user_id'          => $broker->user_id,
                'broker_username'  => $broker->account_user_name,
                'order_id'         => $orderId ?? '-',
                'status'           => $status,
                'trading_symbol'   => $order['trading_sym'],
                'order_type'       => 'LIMIT',
                'transaction_type' => 'BUY',
                'product'          => 'MIS',
                'price'            => $order['order_price'],
                'quantity'         => $order['qty'],
                'status_message'   => $status === 'FAILED'
                    ? "Pivot AMO FAILED {$order['type']} {$order['level']}: " . substr($errorMsg, 0, 200)
                    : "Pivot AMO {$order['type']} {$order['level']} ({$config->model_type}) | {$order['lots']} lots × {$order['lot_size']} = {$order['qty']} qty",
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("OrderBook save failed: " . $e->getMessage());
        }
    }
}