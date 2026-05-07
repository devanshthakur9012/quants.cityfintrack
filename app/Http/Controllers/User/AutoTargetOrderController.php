<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AutoTargetOrder;
use App\Models\BrokerApi;
use App\Services\AutoTargetOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoTargetOrderController extends Controller
{
    protected $service;

    public function __construct(AutoTargetOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * Display auto target orders dashboard
     */
    public function index()
    {
        $pageTitle = 'Auto Target Orders (20% Profit)';
        
        // Get user's active brokers
        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        // Get all auto target orders
        $autoTargets = AutoTargetOrder::with('brokerApi')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get summary statistics
        $stats = $this->service->getSummaryStats(auth()->id());
        return view('templates.basic.user.auto_targets.index', compact(
            'pageTitle',
            'brokers',
            'autoTargets',
            'stats'
        ));
    }

    /**
     * Manually trigger sync for a broker
     */
    public function syncPositions(Request $request)
    {
        $request->validate([
            'broker_id' => 'required|exists:broker_apis,id'
        ]);

        try {
            $result = $this->service->syncPositionsAndCreateTargets(
                auth()->id(),
                $request->broker_id
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Synced successfully! Created: {$result['created']}, Skipped: {$result['skipped']}",
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Sync Positions Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error syncing positions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active auto targets
     */
    public function getActiveTargets(Request $request)
    {
        try {
            $targets = AutoTargetOrder::with('brokerApi')
                ->where('user_id', auth()->id())
                ->active()
                ->forMonitoring()
                ->get()
                ->map(function ($target) {
                    return [
                        'id' => $target->id,
                        'tradingsymbol' => $target->tradingsymbol,
                        'exchange' => $target->exchange,
                        'product' => $target->product,
                        'quantity' => $target->quantity,
                        'buy_price' => $target->buy_price,
                        'target_price' => $target->target_price,
                        'current_price' => $target->current_price,
                        'current_profit' => $target->current_profit,
                        'current_profit_percentage' => $target->current_profit_percentage,
                        'target_percentage' => $target->target_percentage,
                        'order_status' => $target->order_status,
                        'target_order_id' => $target->target_order_id,
                        'is_frozen' => $target->is_frozen,
                        'created_at' => $target->created_at->format('Y-m-d H:i:s'),
                        'last_checked_at' => $target->last_checked_at?->format('Y-m-d H:i:s'),
                        'broker_name' => $target->broker_name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $targets
            ]);

        } catch (\Exception $e) {
            Log::error('Get Active Targets Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an auto target order
     */
    public function cancelTarget(Request $request, $id)
    {
        try {
            $autoTarget = AutoTargetOrder::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // If order is already placed at exchange, we need to cancel it there too
            if ($autoTarget->order_status === 'PLACED' && $autoTarget->target_order_id) {
                $broker = $autoTarget->brokerApi;
                
                if ($broker && $broker->hasValidToken()) {
                    try {
                        $kite = new \KiteConnect\KiteConnect($broker->api_key);
                        $kite->setAccessToken($broker->access_token);
                        
                        // Cancel at exchange
                        $kite->cancelOrder('regular', $autoTarget->target_order_id);
                        
                        Log::info("Cancelled order at exchange: {$autoTarget->target_order_id}");
                    } catch (\Exception $e) {
                        Log::error("Error cancelling order at exchange: " . $e->getMessage());
                        // Continue with local cancellation even if exchange cancellation fails
                    }
                }
            }

            // Mark as cancelled locally
            $autoTarget->markAsCancelled('Cancelled by user');

            return response()->json([
                'success' => true,
                'message' => 'Auto target order cancelled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Cancel Target Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling target: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get target order details
     */
    public function getTargetDetails($id)
    {
        try {
            $autoTarget = AutoTargetOrder::with('brokerApi')
                ->where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $autoTarget->id,
                    'tradingsymbol' => $autoTarget->tradingsymbol,
                    'exchange' => $autoTarget->exchange,
                    'product' => $autoTarget->product,
                    'quantity' => $autoTarget->quantity,
                    'buy_price' => $autoTarget->buy_price,
                    'target_price' => $autoTarget->target_price,
                    'target_percentage' => $autoTarget->target_percentage,
                    'current_price' => $autoTarget->current_price,
                    'current_profit' => $autoTarget->current_profit,
                    'current_profit_percentage' => $autoTarget->current_profit_percentage,
                    'entry_value' => $autoTarget->entry_value,
                    'order_status' => $autoTarget->order_status,
                    'target_order_id' => $autoTarget->target_order_id,
                    'exchange_order_id' => $autoTarget->exchange_order_id,
                    'is_frozen' => $autoTarget->is_frozen,
                    'position_entry_at' => $autoTarget->position_entry_at?->format('Y-m-d H:i:s'),
                    'target_placed_at' => $autoTarget->target_placed_at?->format('Y-m-d H:i:s'),
                    'target_triggered_at' => $autoTarget->target_triggered_at?->format('Y-m-d H:i:s'),
                    'completed_at' => $autoTarget->completed_at?->format('Y-m-d H:i:s'),
                    'last_checked_at' => $autoTarget->last_checked_at?->format('Y-m-d H:i:s'),
                    'error_message' => $autoTarget->error_message,
                    'retry_count' => $autoTarget->retry_count,
                    'broker_name' => $autoTarget->broker_name,
                    'created_at' => $autoTarget->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $autoTarget->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Target Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics
     */
    public function getStats()
    {
        try {
            $stats = $this->service->getSummaryStats(auth()->id());

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Get Stats Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually trigger monitoring
     */
    public function triggerMonitoring()
    {
        try {
            $results = $this->service->monitorAndPlaceTargets();

            return response()->json([
                'success' => true,
                'message' => 'Monitoring completed successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Trigger Monitoring Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error triggering monitoring: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete/cleanup old completed targets
     */
    public function cleanup(Request $request)
    {
        try {
            $daysOld = $request->input('days', 30); // Default 30 days

            $deleted = AutoTargetOrder::where('user_id', auth()->id())
                ->whereIn('order_status', ['COMPLETED', 'CANCELLED', 'EXPIRED'])
                ->where('updated_at', '<', now()->subDays($daysOld))
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deleted} old target orders"
            ]);

        } catch (\Exception $e) {
            Log::error('Cleanup Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error cleaning up: ' . $e->getMessage()
            ], 500);
        }
    }
}