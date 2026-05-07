<?php

namespace App\Services;

use App\Models\AutoTargetOrder;
use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\OrderBook;
use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutoTargetOrderService
{
    /**
     * Sync positions and create auto target orders
     */
    public function syncPositionsAndCreateTargets(int $userId, int $brokerId): array
    {
        try {
            $broker = BrokerApi::where('id', $brokerId)
                ->where('user_id', $userId)
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->firstOrFail();

            // Initialize Kite Connect
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Fetch positions
            $positions = $kite->getPositions();

            if (!$positions || (!isset($positions->net) && !isset($positions->day))) {
                throw new \Exception('No positions data received from Zerodha');
            }

            $netPositions = $positions->net ?? [];
            $created = 0;
            $skipped = 0;
            $errors = [];

            foreach ($netPositions as $position) {
                // Skip if quantity is 0 or it's a SHORT position
                if ($position->quantity <= 0) {
                    $skipped++;
                    continue;
                }

                try {
                    $result = $this->createTargetOrderForPosition($userId, $broker, $position);
                    if ($result) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'symbol' => $position->tradingsymbol,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Error creating target for {$position->tradingsymbol}: " . $e->getMessage());
                }
            }

            return [
                'success' => true,
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
                'total_positions' => count($netPositions)
            ];

        } catch (\Exception $e) {
            Log::error('Sync Positions Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create auto target order for a position
     */
    private function createTargetOrderForPosition(int $userId, BrokerApi $broker, $position): ?AutoTargetOrder
    {
        // Check if target already exists for this position
        $existingTarget = AutoTargetOrder::where('user_id', $userId)
            ->where('broker_api_id', $broker->id)
            ->where('tradingsymbol', $position->tradingsymbol)
            ->where('exchange', $position->exchange)
            ->where('product', $position->product)
            ->whereIn('order_status', ['PENDING', 'PLACED'])
            ->where('is_active', true)
            ->first();

        if ($existingTarget) {
            Log::info("Target order already exists for {$position->tradingsymbol}");
            return null;
        }

        // Calculate target price (20% profit)
        $targetPercentage = 20.00;
        $buyPrice = round($position->average_price, 2);
        $targetPrice = round($buyPrice * (1 + ($targetPercentage / 100)), 2);
        $entryValue = round($buyPrice * $position->quantity, 2);

        // Check for freeze quantity
        $isFrozen = $this->checkFreezeQuantity($position->exchange, $position->quantity);

        // Create auto target order record
        $autoTarget = AutoTargetOrder::create([
            'user_id' => $userId,
            'broker_api_id' => $broker->id,
            'broker_name' => $broker->client_name,
            'tradingsymbol' => $position->tradingsymbol,
            'exchange' => $position->exchange,
            'product' => $position->product,
            'instrument_token' => $position->instrument_token ?? null,
            'quantity' => $position->quantity,
            'buy_price' => $buyPrice,
            'entry_value' => $entryValue,
            'target_percentage' => $targetPercentage,
            'target_price' => $targetPrice,
            'current_price' => round($position->last_price, 2),
            'position_entry_at' => now(),
            'order_status' => 'PENDING',
            'is_active' => true,
            'is_frozen' => $isFrozen,
        ]);

        // Calculate current profit
        $autoTarget->updateCurrentMetrics($position->last_price);

        Log::info("Created auto target order for {$position->tradingsymbol}: Buy @ {$buyPrice}, Target @ {$targetPrice}");

        return $autoTarget;
    }

    /**
     * Monitor and place target orders
     */
    public function monitorAndPlaceTargets(): array
    {
        $pendingOrders = AutoTargetOrder::forMonitoring()->get();
        
        $results = [
            'checked' => 0,
            'placed' => 0,
            'triggered' => 0,
            'completed' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($pendingOrders as $autoTarget) {
            try {
                $results['checked']++;
                
                $broker = $autoTarget->brokerApi;
                
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Invalid broker token for auto target #{$autoTarget->id}");
                    continue;
                }

                // Fetch current LTP
                $currentPrice = $this->getCurrentPrice($broker, $autoTarget);
                
                if (!$currentPrice) {
                    Log::warning("Could not fetch current price for {$autoTarget->tradingsymbol}");
                    continue;
                }

                // Update current metrics
                $autoTarget->updateCurrentMetrics($currentPrice);

                // Check if position still exists
                if (!$this->checkPositionExists($broker, $autoTarget)) {
                    $autoTarget->markAsExpired('Position no longer exists');
                    Log::info("Position expired for {$autoTarget->tradingsymbol}");
                    continue;
                }

                // Place target order if pending and target not reached yet
                if ($autoTarget->order_status === 'PENDING') {
                    $placed = $this->placeTargetOrder($broker, $autoTarget);
                    if ($placed) {
                        $results['placed']++;
                    }
                }
                
                // Check if placed order is triggered/completed
                if ($autoTarget->order_status === 'PLACED') {
                    $status = $this->checkOrderStatus($broker, $autoTarget);
                    
                    if ($status === 'TRIGGERED') {
                        $results['triggered']++;
                    } elseif ($status === 'COMPLETED') {
                        $results['completed']++;
                    }
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'id' => $autoTarget->id,
                    'symbol' => $autoTarget->tradingsymbol,
                    'error' => $e->getMessage()
                ];
                Log::error("Error monitoring auto target #{$autoTarget->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Place target order at Zerodha
     */
    private function placeTargetOrder(BrokerApi $broker, AutoTargetOrder $autoTarget): bool
    {
        try {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Determine if we need to split orders (freeze quantity)
            if ($autoTarget->is_frozen) {
                return $this->placeMultipleTargetOrders($kite, $broker, $autoTarget);
            }

            // Place single LIMIT order with target price
            $orderParams = [
                'exchange' => $autoTarget->exchange,
                'tradingsymbol' => $autoTarget->tradingsymbol,
                'transaction_type' => 'SELL', // Selling to book profit
                'quantity' => $autoTarget->quantity,
                'product' => $autoTarget->product,
                'order_type' => 'LIMIT',
                'price' => $autoTarget->target_price,
                'validity' => 'DAY',
                'tag' => 'AUTO_TARGET_20PCT'
            ];

            Log::info("Placing auto target order", $orderParams);

            $result = $kite->placeOrder("regular", $orderParams);

            if (!isset($result->order_id)) {
                throw new \Exception('No order ID received from Zerodha');
            }

            // Mark as placed
            $autoTarget->markAsPlaced($result->order_id);

            // Save to order book
            $this->saveToOrderBook($broker, $result->order_id, $orderParams, $autoTarget);

            Log::info("✅ Auto target order placed: {$result->order_id} for {$autoTarget->tradingsymbol} @ {$autoTarget->target_price}");

            return true;

        } catch (\Exception $e) {
            $autoTarget->markAsFailed($e->getMessage());
            Log::error("Failed to place auto target order: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Place multiple target orders for frozen quantity
     */
    private function placeMultipleTargetOrders(KiteConnect $kite, BrokerApi $broker, AutoTargetOrder $autoTarget): bool
    {
        $chunkSize = 20; // Fixed chunk size
        $totalQuantity = $autoTarget->quantity;
        $numOrders = ceil($totalQuantity / $chunkSize);
        $remainingQty = $totalQuantity;
        
        $placedOrders = [];
        $firstOrderId = null;

        Log::info("🔄 Placing {$numOrders} split target orders for {$autoTarget->tradingsymbol}");

        for ($i = 0; $i < $numOrders; $i++) {
            $qtyToPlace = min($chunkSize, $remainingQty);
            
            $orderParams = [
                'exchange' => $autoTarget->exchange,
                'tradingsymbol' => $autoTarget->tradingsymbol,
                'transaction_type' => 'SELL',
                'quantity' => $qtyToPlace,
                'product' => $autoTarget->product,
                'order_type' => 'LIMIT',
                'price' => $autoTarget->target_price,
                'validity' => 'DAY',
                'tag' => "AUTO_TARGET_SPLIT_{$i}"
            ];

            try {
                $result = $kite->placeOrder("regular", $orderParams);
                
                if (isset($result->order_id)) {
                    $placedOrders[] = $result->order_id;
                    
                    if ($i === 0) {
                        $firstOrderId = $result->order_id;
                    }
                    
                    $this->saveToOrderBook($broker, $result->order_id, $orderParams, $autoTarget);
                    
                    // Log::info("✅ Split order [{$i+1}/{$numOrders}] placed: {$result->order_id}");
                }
                
            } catch (\Exception $e) {
                // Log::error("❌ Split order [{$i+1}/{$numOrders}] failed: " . $e->getMessage());
            }
            
            $remainingQty -= $qtyToPlace;
            
            if ($i < $numOrders - 1) {
                sleep(1); // Rate limiting
            }
        }

        if (count($placedOrders) > 0) {
            // Mark with first order ID
            $autoTarget->markAsPlaced(
                $firstOrderId, 
                implode(',', $placedOrders) // Store all order IDs in exchange_order_id
            );
            return true;
        }

        return false;
    }

    /**
     * Check order status
     */
    private function checkOrderStatus(BrokerApi $broker, AutoTargetOrder $autoTarget): ?string
    {
        try {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Get order history
            $orderHistory = $kite->getOrderHistory($autoTarget->target_order_id);
            
            if (!$orderHistory || count($orderHistory) === 0) {
                return null;
            }

            $latestOrder = end($orderHistory);
            $status = $latestOrder->status;

            Log::info("Order status for {$autoTarget->target_order_id}: {$status}");

            // Update based on status
            if ($status === 'COMPLETE') {
                $autoTarget->markAsCompleted();
                return 'COMPLETED';
            } elseif ($status === 'TRIGGER PENDING') {
                $autoTarget->markAsTriggered();
                return 'TRIGGERED';
            } elseif (in_array($status, ['CANCELLED', 'REJECTED'])) {
                $autoTarget->markAsCancelled($latestOrder->status_message ?? $status);
                return 'CANCELLED';
            }

            return $status;

        } catch (\Exception $e) {
            Log::error("Error checking order status: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current LTP
     */
    private function getCurrentPrice(BrokerApi $broker, AutoTargetOrder $autoTarget): ?float
    {
        try {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            // Fetch current position to get LTP
            $positions = $kite->getPositions();
            $netPositions = $positions->net ?? [];

            foreach ($netPositions as $position) {
                if ($position->tradingsymbol === $autoTarget->tradingsymbol &&
                    $position->exchange === $autoTarget->exchange) {
                    return round($position->last_price, 2);
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Error fetching current price: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if position still exists
     */
    private function checkPositionExists(BrokerApi $broker, AutoTargetOrder $autoTarget): bool
    {
        try {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            $positions = $kite->getPositions();
            $netPositions = $positions->net ?? [];

            foreach ($netPositions as $position) {
                if ($position->tradingsymbol === $autoTarget->tradingsymbol &&
                    $position->exchange === $autoTarget->exchange &&
                    $position->product === $autoTarget->product &&
                    $position->quantity > 0) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Error checking position existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check for freeze quantity
     */
    private function checkFreezeQuantity(string $exchange, int $quantity): bool
    {
        // Freeze limits by exchange (approximate values)
        $freezeLimits = [
            'NSE' => 10000,
            'BSE' => 10000,
            'NFO' => 1800,
            'MCX' => 1000,
            'CDS' => 1000,
        ];

        $limit = $freezeLimits[$exchange] ?? 10000;
        return $quantity > $limit;
    }

    /**
     * Save to order book
     */
    private function saveToOrderBook(BrokerApi $broker, string $orderId, array $orderParams, AutoTargetOrder $autoTarget): void
    {
        try {
            OrderBook::create([
                'user_id' => $autoTarget->user_id,
                'broker_username' => $broker->account_user_name,
                'order_id' => $orderId,
                'status' => 'PENDING',
                'trading_symbol' => $orderParams['tradingsymbol'],
                'order_type' => $orderParams['order_type'],
                'transaction_type' => $orderParams['transaction_type'],
                'product' => $orderParams['product'],
                'price' => $orderParams['price'],
                'quantity' => $orderParams['quantity'],
                'status_message' => "Auto target order (20% profit @ {$orderParams['price']})",
                'order_datetime' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving to order book: ' . $e->getMessage());
        }
    }

    /**
     * Get summary statistics
     */
    public function getSummaryStats(int $userId): array
    {
        return [
            'total_targets' => AutoTargetOrder::where('user_id', $userId)->count(),
            'active_targets' => AutoTargetOrder::where('user_id', $userId)->active()->count(),
            'pending' => AutoTargetOrder::where('user_id', $userId)->where('order_status', 'PENDING')->count(),
            'placed' => AutoTargetOrder::where('user_id', $userId)->where('order_status', 'PLACED')->count(),
            'triggered' => AutoTargetOrder::where('user_id', $userId)->where('order_status', 'TRIGGERED')->count(),
            'completed' => AutoTargetOrder::where('user_id', $userId)->where('order_status', 'COMPLETED')->count(),
            'failed' => AutoTargetOrder::where('user_id', $userId)->where('order_status', 'FAILED')->count(),
            'cancelled' => AutoTargetOrder::where('user_id', $userId)->where('order_status', 'CANCELLED')->count(),
            'total_potential_profit' => AutoTargetOrder::where('user_id', $userId)
                ->active()
                ->sum(DB::raw('entry_value * target_percentage / 100')),
        ];
    }
}