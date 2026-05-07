<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\OiivOrderBook;
use App\Models\OiivPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * OiivOrderBookController
 *
 * Handles the Order Book and Positions screens for OIIV auto-trading.
 * Mirrors Zerodha's UX: Open / Executed / Cancelled tabs for orders,
 * and Open / Closed tabs for positions.
 *
 * CHANGES:
 *   - fetchLtps()   → fast AJAX endpoint; reads current_ltp from DB (written by oiiv:fetch-ltps command)
 *   - formatOrder() → now exposes original_placed_price, last_modified_price, current_ltp
 *   - modifyOrder() → correct KiteConnect signature: modifyOrder($variety, $orderId, $params)
 *                     also preserves original_placed_price on first modify
 *   - cancelOrder() → correct KiteConnect signature: cancelOrder($variety, $orderId)
 *   - triggerSync() → also fires oiiv:fetch-ltps after sync
 */
class OiivOrderBookController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function ordersPage()
    {
        $pageTitle = 'OIIV Order Book';
        $brokers   = $this->getBrokers();
        return view($this->activeTemplate . 'user.oiiv-auto.order-book', compact('pageTitle', 'brokers'));
    }

    public function positionsPage()
    {
        $pageTitle = 'OIIV Positions';
        $brokers   = $this->getBrokers();
        return view($this->activeTemplate . 'user.oiiv-auto.positions', compact('pageTitle', 'brokers'));
    }

    // =========================================================
    //  ORDER BOOK  — fetch
    // =========================================================

    public function fetchOrders(Request $request)
    {
        $request->validate([
            'broker_id' => 'required|exists:broker_apis,id',
            'tab'       => 'nullable|in:open,executed,cancelled,all',
            'date'      => 'nullable|date',
        ]);

        try {
            $brokerId = $request->broker_id;
            $tab      = $request->get('tab', 'all');
            $date     = $request->get('date', today()->toDateString());

            $this->assertBrokerOwnership($brokerId);

            $query = OiivOrderBook::where('broker_api_id', $brokerId)
                ->where('user_id', auth()->id());

            if ($date === today()->toDateString()) {
                $query->whereDate('created_at', $date);
            } else {
                $query->where('signal_date', $date);
            }

            switch ($tab) {
                case 'open':
                    $query->whereIn('status', [OiivOrderBook::STATUS_OPEN, OiivOrderBook::STATUS_TRIGGER_PENDING]);
                    break;
                case 'executed':
                    $query->where('status', OiivOrderBook::STATUS_COMPLETE);
                    break;
                case 'cancelled':
                    $query->whereIn('status', [OiivOrderBook::STATUS_CANCELLED, OiivOrderBook::STATUS_REJECTED]);
                    break;
            }

            $orders = $query->orderByDesc('created_at')->get();

            // Counts for tab badges
            $baseQ = OiivOrderBook::where('broker_api_id', $brokerId)
                ->where('user_id', auth()->id())
                ->whereDate('created_at', $date);

            $counts = [
                'open'      => (clone $baseQ)->whereIn('status', [OiivOrderBook::STATUS_OPEN, OiivOrderBook::STATUS_TRIGGER_PENDING])->count(),
                'executed'  => (clone $baseQ)->where('status', OiivOrderBook::STATUS_COMPLETE)->count(),
                'cancelled' => (clone $baseQ)->whereIn('status', [OiivOrderBook::STATUS_CANCELLED, OiivOrderBook::STATUS_REJECTED])->count(),
                'all'       => (clone $baseQ)->count(),
            ];

            $formatted = $orders->map(fn($o) => $this->formatOrder($o));

            $executedOrders  = $orders->where('status', OiivOrderBook::STATUS_COMPLETE);
            $totalInvestment = $executedOrders->sum(fn($o) =>
                (float)$o->average_price * ($o->quantity_units ?? $o->filled_quantity)
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'orders'           => $formatted->values(),
                    'counts'           => $counts,
                    'total_investment' => round($totalInvestment, 2),
                    'synced_at'        => now()->format('H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('OiivOrderBook::fetchOrders — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  FAST LTP FETCH  (polled by UI every ~15 sec, reads DB cache)
    // =========================================================

    /**
     * Lightweight endpoint — just reads current_ltp from DB.
     * The oiiv:fetch-ltps command keeps this DB column fresh.
     * No Zerodha API call here → fast, no rate-limit risk.
     */
    public function fetchLtps(Request $request)
    {
        $request->validate(['broker_id' => 'required|exists:broker_apis,id']);
        $this->assertBrokerOwnership($request->broker_id);

        $rows = OiivOrderBook::where('broker_api_id', $request->broker_id)
            ->where('user_id', auth()->id())
            ->whereIn('status', [OiivOrderBook::STATUS_OPEN, OiivOrderBook::STATUS_TRIGGER_PENDING])
            ->whereDate('created_at', today())
            ->select('id', 'current_ltp', 'ltp_updated_at', 'status',
                     'average_price', 'filled_quantity', 'pending_quantity',
                     'placed_price', 'last_modified_price', 'original_placed_price')
            ->get()
            ->keyBy('id')
            ->map(fn($r) => [
                'ltp'                   => $r->current_ltp ? round((float)$r->current_ltp, 2) : null,
                'ltp_at'                => $r->ltp_updated_at?->format('H:i:s'),
                'status'                => $r->status,
                'average_price'         => $r->average_price ? round((float)$r->average_price, 2) : null,
                'filled_quantity'       => $r->filled_quantity,
                'pending_quantity'      => $r->pending_quantity,
                'placed_price'          => $r->placed_price ? round((float)$r->placed_price, 2) : null,
                'last_modified_price'   => $r->last_modified_price ? round((float)$r->last_modified_price, 2) : null,
                'original_placed_price' => $r->original_placed_price ? round((float)$r->original_placed_price, 2) : null,
            ]);

        return response()->json([
            'success'   => true,
            'ltps'      => $rows,
            'server_ts' => now()->format('H:i:s'),
        ]);
    }

    // =========================================================
    //  MODIFY ORDER PRICE  (Zerodha modifyOrder)
    // =========================================================

    public function modifyOrder(Request $request)
    {
        $request->validate([
            'order_id'  => 'required|exists:oiiv_order_book,id',
            'new_price' => 'required|numeric|min:0.05',
            'order_type'=> 'nullable|in:LIMIT,MARKET',
        ]);

        try {
            $order = OiivOrderBook::where('id', $request->order_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            if (!$order->is_live) {
                return response()->json(['success' => false, 'message' => "Order is {$order->status} — cannot modify"], 422);
            }

            $broker = $this->getBrokerForOrder($order);
            $kite   = $this->kite($broker);

            $params = [
                'price'      => (float) $request->new_price,
                'order_type' => $request->get('order_type', $order->order_type),
                'quantity'   => $order->quantity_units,
                'validity'   => 'DAY',
            ];

            // KiteConnect signature: modifyOrder($variety, $orderId, $params)
            $result = $kite->modifyOrder('regular', $order->zerodha_order_id, $params);
            Log::info("[OiivOrderBook] Modified order {$order->zerodha_order_id} → ₹{$request->new_price}");

            // Preserve original_placed_price on first modification only
            $updates = ['last_synced_at' => now()];
            if (is_null($order->original_placed_price)) {
                $updates['original_placed_price'] = $order->placed_price;
            }

            // recordModification updates last_modified_price + placed_price + modify_count
            $order->update($updates);
            $order->recordModification((float) $request->new_price, 'USER');

            return response()->json([
                'success' => true,
                'message' => "Order modified to ₹{$request->new_price}",
                'order'   => $this->formatOrder($order->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('OiivOrderBook::modifyOrder — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  CANCEL ORDER
    // =========================================================

    public function cancelOrder(Request $request)
    {
        $request->validate(['order_id' => 'required|exists:oiiv_order_book,id']);

        try {
            $order = OiivOrderBook::where('id', $request->order_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            if (!$order->is_live) {
                return response()->json(['success' => false, 'message' => "Order is already {$order->status}"], 422);
            }

            $broker = $this->getBrokerForOrder($order);
            $kite   = $this->kite($broker);

            // KiteConnect signature: cancelOrder($variety, $orderId)
            $kite->cancelOrder('regular', $order->zerodha_order_id);
            Log::info("[OiivOrderBook] Cancelled order {$order->zerodha_order_id}");

            $order->update([
                'status'          => OiivOrderBook::STATUS_CANCELLED,
                'internal_status' => OiivOrderBook::INT_CANCELLED,
                'cancelled_at'    => now(),
                'status_message'  => 'Cancelled by user via OIIV portal',
                'last_synced_at'  => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Order for {$order->trading_symbol} cancelled",
                'order'   => $this->formatOrder($order->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('OiivOrderBook::cancelOrder — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  POSITIONS — fetch
    // =========================================================

    public function fetchPositions(Request $request)
    {
        $request->validate([
            'broker_id' => 'required|exists:broker_apis,id',
            'tab'       => 'nullable|in:open,closed',
            'from_date' => 'nullable|date',
            'to_date'   => 'nullable|date',
        ]);

        try {
            $brokerId = $request->broker_id;
            $tab      = $request->get('tab', 'open');

            $this->assertBrokerOwnership($brokerId);

            $query = OiivPosition::where('broker_api_id', $brokerId)
                ->where('user_id', auth()->id());

            if ($tab === 'open') {
                $query->where('status', OiivPosition::STATUS_OPEN);
            } else {
                $query->where('status', OiivPosition::STATUS_CLOSED);
                if ($request->filled('from_date')) $query->where('signal_date', '>=', $request->from_date);
                if ($request->filled('to_date'))   $query->where('signal_date', '<=', $request->to_date);
            }

            $positions = $query->orderByDesc('entry_at')->get();

            $formatted = $positions->map(fn($p) => $this->formatPosition($p));

            $counts = [
                'open'   => OiivPosition::where('broker_api_id', $brokerId)->where('user_id', auth()->id())->where('status', 'open')->count(),
                'closed' => OiivPosition::where('broker_api_id', $brokerId)->where('user_id', auth()->id())->where('status', 'closed')->count(),
            ];

            $totalUnrealizedPnl = $positions->sum(fn($p) => (float)$p->unrealized_pnl);
            $totalRealizedPnl   = $positions->sum(fn($p) => (float)$p->realized_pnl);

            return response()->json([
                'success' => true,
                'data'    => [
                    'positions'            => $formatted->values(),
                    'counts'               => $counts,
                    'total_unrealized_pnl' => round($totalUnrealizedPnl, 2),
                    'total_realized_pnl'   => round($totalRealizedPnl, 2),
                    'synced_at'            => now()->format('H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('OiivOrderBook::fetchPositions — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  SQUARE OFF POSITION
    // =========================================================

    public function squareOffPosition(Request $request)
    {
        $request->validate([
            'position_id' => 'required|exists:oiiv_positions,id',
            'order_type'  => 'required|in:MARKET,LIMIT',
            'price'       => 'nullable|numeric|min:0.05',
        ]);

        try {
            $position = OiivPosition::where('id', $request->position_id)
                ->where('user_id', auth()->id())
                ->where('status', OiivPosition::STATUS_OPEN)
                ->firstOrFail();

            $broker = $this->getBrokerById($position->broker_api_id);
            $kite   = $this->kite($broker);

            $params = [
                'exchange'         => $position->exchange,
                'tradingsymbol'    => $position->trading_symbol,
                'transaction_type' => 'SELL',
                'quantity'         => $position->quantity_units,
                'product'          => $position->product,
                'order_type'       => $request->order_type,
                'validity'         => 'DAY',
            ];

            if ($request->order_type === 'LIMIT') {
                if (!$request->filled('price')) {
                    return response()->json(['success' => false, 'message' => 'Price required for LIMIT order'], 422);
                }
                $params['price'] = (float) $request->price;
            }

            $result         = $kite->placeOrder('regular', $params);
            $zerodhaOrderId = $result->order_id ?? null;

            Log::info("[OiivOrderBook] Square-off placed for position {$position->id} | Zerodha: {$zerodhaOrderId}");

            $sellOrder = OiivOrderBook::create([
                'user_id'              => $position->user_id,
                'broker_api_id'        => $position->broker_api_id,
                'oiiv_auto_order_id'   => $position->oiiv_auto_order_id,
                'zerodha_order_id'     => $zerodhaOrderId,
                'trading_symbol'       => $position->trading_symbol,
                'base_symbol'          => $position->base_symbol,
                'exchange'             => $position->exchange,
                'option_type'          => $position->option_type,
                'strike_price'         => $position->strike_price,
                'expiry_date'          => $position->expiry_date,
                'instrument_token'     => $position->instrument_token,
                'signal_date'          => $position->signal_date,
                'signal_type'          => 'SQUARE_OFF',
                'sentiment'            => $position->sentiment,
                'transaction_type'     => 'SELL',
                'order_type'           => $request->order_type,
                'product'              => $position->product,
                'validity'             => 'DAY',
                'quantity'             => $position->quantity,
                'quantity_units'       => $position->quantity_units,
                'lot_size'             => $position->lot_size,
                'placed_price'         => $request->price ?? 0,
                'original_placed_price'=> $request->price ?? 0,
                'status'               => OiivOrderBook::STATUS_OPEN,
                'internal_status'      => OiivOrderBook::INT_PLACED,
                'placed_at'            => now(),
                'last_synced_at'       => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Square-off order placed for {$position->trading_symbol}. Status will update automatically.",
                'data'    => [
                    'zerodha_order_id' => $zerodhaOrderId,
                    'sell_order_id'    => $sellOrder->id,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('OiivOrderBook::squareOffPosition — ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  MANUAL SYNC TRIGGER
    // =========================================================

    public function triggerSync(Request $request)
    {
        $request->validate(['broker_id' => 'required|exists:broker_apis,id']);
        $this->assertBrokerOwnership($request->broker_id);

        try {
            \Artisan::call('oiiv:sync-orders', ['--broker_id' => $request->broker_id]);
            // Also fire LTP refresh immediately after sync
            \Artisan::call('oiiv:fetch-ltps',  ['--broker_id' => $request->broker_id]);
            return response()->json(['success' => true, 'message' => 'Sync complete', 'synced_at' => now()->format('H:i:s')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  AVAILABLE DATES FOR HISTORY FILTER
    // =========================================================

    public function availableDates(Request $request)
    {
        $request->validate(['broker_id' => 'required|exists:broker_apis,id']);
        $this->assertBrokerOwnership($request->broker_id);

        $dates = OiivOrderBook::where('broker_api_id', $request->broker_id)
            ->where('user_id', auth()->id())
            ->selectRaw('DATE(created_at) as d')
            ->groupBy('d')
            ->orderByDesc('d')
            ->limit(60)
            ->pluck('d');

        return response()->json(['success' => true, 'dates' => $dates]);
    }

    // =========================================================
    //  SAFE DATE HELPER
    //  PDO returns datetime columns as plain strings when the model has no cast.
    //  This helper safely handles Carbon objects, strings, and nulls.
    // =========================================================

    private function safeFormat($value, string $format): ?string
    {
        if ($value === null || $value === '') return null;
        if ($value instanceof \Carbon\Carbon)  return $value->format($format);
        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }

    // =========================================================
    //  FORMATTERS
    // =========================================================

    private function formatOrder(OiivOrderBook $o): array
    {
        $filledUnits = $o->filled_quantity ?? 0;
        $investment  = $filledUnits > 0
            ? round((float)$o->average_price * $filledUnits, 2)
            : round((float)$o->placed_price * ($o->quantity_units ?? 0), 2);

        $origPrice    = $o->original_placed_price ?? $o->placed_price;
        $currentPrice = $o->placed_price;
        $modPrice     = $o->last_modified_price;
        $wasModified  = (int)($o->modify_count ?? 0) > 0;

        return [
            'id'                     => $o->id,
            'zerodha_order_id'       => $o->zerodha_order_id,
            'trading_symbol'         => $o->trading_symbol,
            'base_symbol'            => $o->base_symbol,
            'exchange'               => $o->exchange,
            'option_type'            => $o->option_type,
            'strike_price'           => $o->strike_price,
            'expiry_date'            => $this->safeFormat($o->expiry_date, 'd M Y'),
            'signal_date'            => $o->signal_date,
            'sentiment'              => $o->sentiment,
            'oi_condition'           => $o->oi_condition,
            'transaction_type'       => $o->transaction_type,
            'order_type'             => $o->order_type,
            'product'                => $o->product,
            'quantity'               => $o->quantity,
            'quantity_units'         => $o->quantity_units,
            'lot_size'               => $o->lot_size,
            'trigger_price'          => $o->trigger_price,

            'original_placed_price'  => $origPrice   ? round((float)$origPrice, 2)    : null,
            'placed_price'           => $currentPrice ? round((float)$currentPrice, 2) : null,
            'last_modified_price'    => $modPrice     ? round((float)$modPrice, 2)     : null,
            'was_modified'           => $wasModified,
            'modify_count'           => (int)($o->modify_count ?? 0),

            'average_price'          => $o->average_price ? round((float)$o->average_price, 2) : null,
            'filled_quantity'        => $filledUnits,
            'pending_quantity'       => $o->pending_quantity,
            'cancelled_quantity'     => $o->cancelled_quantity,

            'current_ltp'            => $o->current_ltp ? round((float)$o->current_ltp, 2) : null,
            'ltp_updated_at'         => $this->safeFormat($o->ltp_updated_at, 'H:i:s'),

            'status'                 => $o->status,
            'status_message'         => $o->status_message,
            'internal_status'        => $o->internal_status,
            'status_color'           => $o->status_color,
            'status_icon'            => $o->status_icon,
            'is_live'                => $o->is_live,

            'modification_history'   => $o->modification_history ?? [],
            'lot_chunk_number'       => $o->lot_chunk_number,
            'lot_chunk_total'        => $o->lot_chunk_total,
            'investment'             => $investment,

            'placed_at'              => $this->safeFormat($o->placed_at,      'H:i:s'),
            'filled_at'              => $this->safeFormat($o->filled_at,      'H:i:s'),
            'cancelled_at'           => $this->safeFormat($o->cancelled_at,   'H:i:s'),
            'last_synced_at'         => $this->safeFormat($o->last_synced_at, 'H:i:s'),

            'signal_type'            => $o->signal_type,
            'ce_oi_change_pct'       => $o->ce_oi_change_pct,
            'pe_oi_change_pct'       => $o->pe_oi_change_pct,
        ];
    }

    private function formatPosition(OiivPosition $p): array
    {
        $pnl    = (float) ($p->status === 'open' ? $p->unrealized_pnl : $p->realized_pnl);
        $pnlPct = (float) $p->pnl_percentage;
        $ltp    = (float) ($p->last_price ?? $p->entry_price);
        $entry  = (float) $p->entry_price;

        return [
            'id'                   => $p->id,
            'trading_symbol'       => $p->trading_symbol,
            'base_symbol'          => $p->base_symbol,
            'exchange'             => $p->exchange,
            'option_type'          => $p->option_type,
            'strike_price'         => $p->strike_price,
            'expiry_date'          => $this->safeFormat($p->expiry_date, 'd M Y'),
            'signal_date'          => $this->safeFormat($p->signal_date, 'Y-m-d'),
            'signal_type'          => $p->signal_type,
            'sentiment'            => $p->sentiment,
            'oi_condition'         => $p->oi_condition,
            'position_type'        => $p->position_type,
            'product'              => $p->product,
            'quantity'             => $p->quantity,
            'quantity_units'       => $p->quantity_units,
            'lot_size'             => $p->lot_size,
            'entry_price'          => round($entry, 2),
            'ltp'                  => round($ltp, 2),
            'exit_price'           => $p->exit_price ? round((float)$p->exit_price, 2) : null,
            'pnl'                  => round($pnl, 2),
            'pnl_percentage'       => round($pnlPct, 2),
            'pnl_color'            => $p->pnl_color,
            'status'               => $p->status,
            'is_btst'              => $p->is_btst,
            'holding_days'         => $p->holding_days,
            'holding_label'        => $p->holding_days === 0 ? 'Today' : ($p->holding_days === 1 ? '1 day' : "{$p->holding_days} days"),
            'entry_at'             => $this->safeFormat($p->entry_at,      'd M Y H:i'),
            'exit_at'              => $this->safeFormat($p->exit_at,       'd M Y H:i'),
            'exit_source'          => $p->exit_source,
            'last_synced_at'       => $this->safeFormat($p->last_synced_at, 'H:i:s'),
            'spot_price_at_signal' => $p->spot_price_at_signal,
            'ce_oi_change_pct'     => $p->ce_oi_change_pct,
            'pe_oi_change_pct'     => $p->pe_oi_change_pct,
        ];
    }

    // =========================================================
    //  SHARED HELPERS
    // =========================================================

    private function getBrokers()
    {
        return BrokerApi::select('id', 'client_name', 'account_user_name')
            ->where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->get();
    }

    private function getBrokerForOrder(OiivOrderBook $order): BrokerApi
    {
        return BrokerApi::where('id', $order->broker_api_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function getBrokerById(int $id): BrokerApi
    {
        return BrokerApi::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private function assertBrokerOwnership(int $brokerId): void
    {
        BrokerApi::where('id', $brokerId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    private array $kiteInstances = [];

    private function kite(BrokerApi $broker): \KiteConnect\KiteConnect
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $k = new KiteConnect($broker->api_key);
            $k->setAccessToken($broker->access_token);
            $this->kiteInstances[$broker->id] = $k;
        }
        return $this->kiteInstances[$broker->id];
    }
}