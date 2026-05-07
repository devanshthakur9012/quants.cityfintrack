<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\PositionHistory;
use App\Models\OrderBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class ZerodhaPortfolioController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // MAIN PAGE
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Portfolio & Positions';

        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        return view($this->activeTemplate . 'user.portfolio.index', compact('pageTitle', 'brokers'));
    }

    // ─────────────────────────────────────────────────────────────
    // FETCH OPEN POSITIONS (with live LTP from Zerodha)
    // ─────────────────────────────────────────────────────────────

    public function fetchPositions(Request $request)
    {
        $request->validate([
            'broker_id'    => 'required|exists:broker_apis,id',
            'purchase_date' => 'nullable|date',
        ]);

        try {
            $broker = BrokerApi::where('id', $request->broker_id)
                ->where('user_id', auth()->id())
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            if (!$broker->hasValidToken()) {
                return response()->json(['success' => false, 'message' => 'Broker token expired. Please login again.'], 401);
            }

            // ── Step 1: Run a quick sync to update our DB from Zerodha ──
            $this->quickSync($broker);

            // ── Step 2: Get live LTP for all open positions ──────────
            $liveQuotes = $this->fetchLiveLTP($broker);

            // ── Step 3: Read from DB (now fresh after sync) ──────────
            $query = PortfolioPosition::where('broker_api_id', $broker->id)
                ->where('user_id', auth()->id())
                ->where('position_status', 'open');

            if ($request->filled('purchase_date')) {
                $query->whereDate('purchase_date', $request->purchase_date);
            }

            $positions = $query->orderBy('purchase_date', 'desc')->get();

            // ── Step 4: Enrich with live data ─────────────────────────
            $processedPositions = $positions->map(function ($pos) use ($liveQuotes) {

                $ltp         = $liveQuotes[$pos->tradingsymbol] ?? $pos->last_price;
                // $entryPrice  = $pos->purchase_price ?: $pos->average_price;
                $entryPrice = $pos->average_price ?: $pos->purchase_price;
                $qty         = abs($pos->quantity);

                // Unrealized PnL using live LTP
                // if ($pos->buy_sell === 'LONG') {
                //     $unrealizedPnl = ($ltp - $entryPrice) * $qty;
                // } else {
                //     $unrealizedPnl = ($entryPrice - $ltp) * $qty;
                // }

                $unrealizedPnl = $pos->pnl ?? (($ltp - $entryPrice) * $qty);

                $pnlPercent  = $entryPrice > 0
                    ? round((($ltp - $entryPrice) / $entryPrice) * 100, 2)
                    : 0;

                $holdingDays = Carbon::parse($pos->purchase_date)->diffInDays(now());

                return [
                    'id'                    => $pos->id,
                    'tradingsymbol'         => $pos->tradingsymbol,
                    'exchange'              => $pos->exchange,
                    'product'               => $pos->product,
                    'position_type'         => $pos->buy_sell,
                    'quantity'              => $pos->quantity,
                    'overnight_quantity'    => $pos->overnight_quantity,

                    // Prices
                    'entry_price'           => $entryPrice,
                    'average_price'         => $pos->average_price,
                    'ltp'                   => $ltp, // LIVE from Zerodha API

                    // P&L
                    'unrealized_pnl'        => round($unrealizedPnl, 2),
                    'pnl_percentage'        => $pnlPercent,

                    // Dates
                    'purchase_date'         => Carbon::parse($pos->purchase_date)->format('d M Y, h:i A'),
                    'purchase_date_raw'     => Carbon::parse($pos->purchase_date)->format('Y-m-d'),
                    'holding_days'          => $holdingDays,
                    'holding_label'         => $holdingDays === 0 ? 'Today' : ($holdingDays === 1 ? '1 day' : "{$holdingDays} days"),
                ];
            });

            // Summary
            $totalPnl       = $processedPositions->sum('unrealized_pnl');
            $availableDates = $this->getAvailablePurchaseDates($broker->id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'positions'       => $processedPositions->values(),
                    'total_pnl'       => round($totalPnl, 2),
                    'total_positions' => $processedPositions->count(),
                    'fetched_at'      => now()->format('H:i:s'),
                    'available_dates' => $availableDates,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('fetchPositions Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // FETCH CLOSED POSITIONS (from position_history)
    // ─────────────────────────────────────────────────────────────

    public function fetchClosedPositions(Request $request)
    {
        $request->validate([
            'broker_id'  => 'required|exists:broker_apis,id',
            'from_date'  => 'nullable|date',
            'to_date'    => 'nullable|date',
        ]);

        try {
            $broker = BrokerApi::where('id', $request->broker_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $query = PositionHistory::where('broker_api_id', $broker->id)
                ->where('user_id', auth()->id())
                ->orderBy('exit_date', 'desc')
                ->orderBy('created_at', 'desc');

            if ($request->filled('from_date')) {
                $query->whereDate('exit_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('exit_date', '<=', $request->to_date);
            }

            $history = $query->get();

            $formatted = $history->map(function ($h) {
                return [
                    'id'            => $h->id,
                    'symbol'        => $h->symbol,
                    'exchange'      => $h->exchange,
                    'product'       => $h->product,
                    'position_type' => $h->position_type,
                    'qty'           => $h->qty,

                    // ✅ The data you wanted: buy price, sell price, PnL
                    'entry_price'   => $h->entry_price,
                    'exit_price'    => $h->exit_price,
                    'realized_pnl'  => $h->realized_pnl,
                    'pnl_sign'      => $h->realized_pnl >= 0 ? '+' : '',
                    'pnl_color'     => $h->realized_pnl >= 0 ? 'success' : 'danger',

                    // Dates
                    'entry_date'    => Carbon::parse($h->entry_date)->format('d M Y'),
                    'exit_date'     => Carbon::parse($h->exit_date)->format('d M Y'),
                    'holding_days'  => $h->holding_days,
                    'holding_label' => $h->holding_days === 0 ? 'Intraday' : ($h->holding_days . ($h->holding_days === 1 ? ' day' : ' days')),

                    'exit_source'   => $h->exit_source,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => [
                    'history'          => $formatted->values(),
                    'total_realized'   => round($history->sum('realized_pnl'), 2),
                    'total_trades'     => $history->count(),
                    'winning_trades'   => $history->where('realized_pnl', '>', 0)->count(),
                    'losing_trades'    => $history->where('realized_pnl', '<', 0)->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('fetchClosedPositions Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // TODAY'S ACTIVITY (what happened today)
    // ─────────────────────────────────────────────────────────────

    public function fetchTodayActivity(Request $request)
    {
        $request->validate(['broker_id' => 'required|exists:broker_apis,id']);

        try {
            $broker = BrokerApi::where('id', $request->broker_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // Positions opened today (still open)
            $openedToday = PortfolioPosition::where('broker_api_id', $broker->id)
                ->where('user_id', auth()->id())
                ->where('position_status', 'open')
                ->whereDate('purchase_date', today())
                ->get()
                ->map(fn($p) => [
                    'symbol'      => $p->tradingsymbol,
                    'qty'         => $p->quantity,
                    'entry_price' => $p->purchase_price ?: $p->average_price,
                    'ltp'         => $p->last_price,
                    'pnl'         => $p->pnl,
                    'time'        => Carbon::parse($p->purchase_date)->format('h:i A'),
                    'type'        => 'OPENED',
                ]);

            // Positions closed today
            $closedToday = PositionHistory::where('broker_api_id', $broker->id)
                ->where('user_id', auth()->id())
                ->whereDate('exit_date', today())
                ->get()
                ->map(fn($h) => [
                    'symbol'       => $h->symbol,
                    'qty'          => $h->qty,
                    'entry_price'  => $h->entry_price,
                    'exit_price'   => $h->exit_price,
                    'realized_pnl' => $h->realized_pnl,
                    'pnl_color'    => $h->realized_pnl >= 0 ? 'success' : 'danger',
                    'holding_days' => $h->holding_days,
                    'time'         => Carbon::parse($h->updated_at)->format('h:i A'),
                    'type'         => 'CLOSED',
                ]);

            $totalBookedToday = $closedToday->sum('realized_pnl');
            $totalMtmToday    = $openedToday->sum('pnl');

            return response()->json([
                'success' => true,
                'data'    => [
                    'opened_today'        => $openedToday->values(),
                    'closed_today'        => $closedToday->values(),
                    'total_booked_pnl'    => round($totalBookedToday, 2),
                    'total_mtm_pnl'       => round($totalMtmToday, 2),
                    'total_combined_pnl'  => round($totalBookedToday + $totalMtmToday, 2),
                    'date'                => today()->format('d M Y'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('fetchTodayActivity Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SELL POSITION (with freeze lot splitting)
    // ─────────────────────────────────────────────────────────────

    public function sellPosition(Request $request)
    {
        $request->validate([
            'broker_id'     => 'required|exists:broker_apis,id',
            'tradingsymbol' => 'required|string',
            'exchange'      => 'required|string',
            'product'       => 'required|string|in:MIS,NRML,CNC',
            'quantity'      => 'required|integer|min:1',
            'position_type' => 'required|string|in:LONG,SHORT',
            'order_type'    => 'required|string|in:MARKET,LIMIT',
            'price'         => 'nullable|numeric|min:0',
        ]);

        try {
            $broker = BrokerApi::where('id', $request->broker_id)
                ->where('user_id', auth()->id())
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            if (!$broker->hasValidToken()) {
                return response()->json(['success' => false, 'message' => 'Broker token expired'], 401);
            }

            $kite            = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            $transactionType = $request->position_type === 'LONG' ? 'SELL' : 'BUY';
            $chunkSize       = 20; // Zerodha freeze limit per order
            $totalQty        = $request->quantity;
            $numOrders       = (int) ceil($totalQty / $chunkSize);
            $remainingQty    = $totalQty;

            $baseOrderParams = [
                'exchange'         => $request->exchange,
                'tradingsymbol'    => $request->tradingsymbol,
                'transaction_type' => $transactionType,
                'product'          => $request->product,
                'order_type'       => $request->order_type,
                'validity'         => 'DAY',
            ];

            if ($request->order_type === 'LIMIT') {
                if (!$request->filled('price')) {
                    return response()->json(['success' => false, 'message' => 'Price required for LIMIT orders'], 400);
                }
                $baseOrderParams['price'] = $request->price;
            }

            $placedOrders = [];
            $failedOrders = [];

            Log::info("🔄 [SELL] {$request->tradingsymbol} | Total: {$totalQty} | Chunks: {$numOrders}");

            for ($i = 0; $i < $numOrders; $i++) {
                $chunkQty                 = min($chunkSize, $remainingQty);
                $baseOrderParams['quantity'] = $chunkQty;

                try {
                    $result = $kite->placeOrder('regular', $baseOrderParams);

                    if (isset($result->order_id)) {
                        $placedOrders[] = [
                            'order_id'     => $result->order_id,
                            'quantity'     => $chunkQty,
                            'order_number' => $i + 1,
                        ];

                        $this->logToOrderBook($broker, $result->order_id, $baseOrderParams);
                        // Log::info("  ✅ [{$i+1}/{$numOrders}] Order: {$result->order_id} | Qty: {$chunkQty}");
                    }

                } catch (\Exception $e) {
                    $failedOrders[] = [
                        'order_number' => $i + 1,
                        'quantity'     => $chunkQty,
                        'error'        => $e->getMessage(),
                    ];
                    // Log::error("  ❌ [{$i+1}/{$numOrders}] Failed: " . $e->getMessage());
                }

                $remainingQty -= $chunkQty;
                if ($i < $numOrders - 1) sleep(1);
            }

            $successCount = count($placedOrders);
            $failedCount  = count($failedOrders);

            if ($successCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All orders failed',
                    'data'    => ['failed_orders' => $failedOrders],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $failedCount > 0
                    ? "Partially done: {$successCount}/{$numOrders} orders placed"
                    : "All {$successCount} orders placed successfully",
                'data' => [
                    'placed_orders'      => $placedOrders,
                    'failed_orders'      => $failedOrders,
                    'total_orders'       => $numOrders,
                    'successful_orders'  => $successCount,
                    'transaction_type'   => $transactionType,
                    'note'               => 'Position will auto-update on next sync (within 1-2 min)',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('sellPosition Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Quick sync: fetch from Zerodha and update our DB before reading it.
     * This ensures the portfolio screen always shows fresh data.
     */
    private function quickSync(BrokerApi $broker): void
    {
        try {
            // Fire the sync command programmatically
            \Artisan::call('positions:sync', ['--broker_id' => $broker->id]);
        } catch (\Exception $e) {
            Log::warning("quickSync failed for broker {$broker->id}: " . $e->getMessage());
            // Non-fatal: we still show DB data even if sync fails
        }
    }

    /**
     * Fetch live LTP for all open positions in one API call (efficient).
     * Returns: ['SYMBOL' => ltp_price]
     */
    private function fetchLiveLTP(BrokerApi $broker): array
    {
        try {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            $openPositions = PortfolioPosition::where('broker_api_id', $broker->id)
                ->where('user_id', auth()->id())
                ->where('position_status', 'open')
                ->pluck('exchange', 'tradingsymbol');

            if ($openPositions->isEmpty()) return [];

            // Build quote keys like ["NFO:NIFTY24FEB22000CE", "NSE:RELIANCE"]
            $quoteKeys = $openPositions->map(fn($exchange, $symbol) => "{$exchange}:{$symbol}")->values()->toArray();

            $quotes = $kite->getQuote($quoteKeys);
            $quotes = json_decode(json_encode($quotes), true); // Convert stdClass to array

            $ltpMap = [];
            foreach ($quotes as $key => $quoteData) {
                $symbol         = explode(':', $key)[1] ?? $key;
                $ltpMap[$symbol] = $quoteData['last_price'] ?? 0;
            }

            return $ltpMap;

        } catch (\Exception $e) {
            Log::warning("fetchLiveLTP failed: " . $e->getMessage());
            return [];
        }
    }

    private function getAvailablePurchaseDates(int $brokerId): array
    {
        return PortfolioPosition::where('broker_api_id', $brokerId)
            ->where('user_id', auth()->id())
            ->where('position_status', 'open')
            ->whereNotNull('purchase_date')
            ->selectRaw('DATE(purchase_date) as date')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();
    }

    private function logToOrderBook(BrokerApi $broker, string $orderId, array $params): void
    {
        try {
            sleep(1);
            $kite         = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);
            $history      = $kite->getOrderHistory($orderId);
            $last         = end($history);

            OrderBook::create([
                'user_id'          => auth()->id(),
                'broker_username'  => $broker->account_user_name,
                'order_id'         => $orderId,
                'status'           => $last->status ?? 'PENDING',
                'trading_symbol'   => $params['tradingsymbol'],
                'order_type'       => $params['order_type'],
                'transaction_type' => $params['transaction_type'],
                'product'          => $params['product'],
                'price'            => $params['price'] ?? '-',
                'quantity'         => $params['quantity'],
                'status_message'   => $last->status_message ?? 'Square-off order',
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('logToOrderBook Error: ' . $e->getMessage());
        }
    }

    // Holdings (long term)
    public function getHoldings(Request $request)
    {
        $request->validate(['broker_id' => 'required|exists:broker_apis,id']);

        try {
            $broker = BrokerApi::where('id', $request->broker_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            if (!$broker->hasValidToken()) {
                return response()->json(['success' => false, 'message' => 'Invalid broker token'], 401);
            }

            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);
            $holdings = $kite->getHoldings();

            return response()->json(['success' => true, 'data' => $holdings]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}