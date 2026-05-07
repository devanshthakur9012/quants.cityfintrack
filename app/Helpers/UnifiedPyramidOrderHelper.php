<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\PyramidOrder;
use App\Models\PyramidOrderDetail;
use Illuminate\Support\Facades\Log;

class UnifiedPyramidOrderHelper
{
    private $broker;
    private $angelHelper;
    private $zerodhaHelper;

    public function __construct(BrokerApi $broker)
    {
        $this->broker = $broker;
        
        if ($broker->client_type === 'Angel') {
            $this->angelHelper = new PyramidOrderHelper($broker);
        } elseif ($broker->client_type === 'Zerodha') {
            $this->zerodhaHelper = new ZerodhaOrderHelper($broker);
        }
    }

    /**
     * Calculate effective discount for pyramid index
     */
    public function calculateEffectiveDiscount(float $baseDiscount, float $discountIncrement, int $pyramidIndex): float
    {
        return round($baseDiscount * (1 + (($pyramidIndex - 1) * $discountIncrement / 100)), 4);
    }

    /**
     * Calculate order price based on transaction type and discount
     */
    public function calculateOrderPrice(float $manualLtp, float $effectiveDiscount, string $transactionType, float $tickSize): float
    {
        if ($transactionType === 'BUY') {
            $price = $manualLtp * (1 - $effectiveDiscount / 100);
        } else {
            $price = $manualLtp * (1 + $effectiveDiscount / 100);
        }

        return $this->roundToTickSize($price, $tickSize);
    }

    /**
     * Round price to exchange tick size
     */
    private function roundToTickSize(float $price, float $tickSize): float
    {
        return round($price / $tickSize) * $tickSize;
    }

    /**
     * Calculate quantity
     */
    public function calculateQuantity(int $lotsPerOrder, int $lotSize): int
    {
        return $lotsPerOrder * $lotSize;
    }

    /**
     * Get instrument details based on broker type
     */
    private function getInstrument(string $symbol, string $expiryDate, float $strikePrice, string $optionType): ?array
    {
        if ($this->broker->client_type === 'Angel') {
            return $this->angelHelper->getAngelInstrument($symbol, $expiryDate, $strikePrice, $optionType);
        } elseif ($this->broker->client_type === 'Zerodha') {
            return $this->zerodhaHelper->getZerodhaInstrument($symbol, $expiryDate, $strikePrice, $optionType);
        }
        
        return null;
    }

    /**
     * Place order based on broker type
     */
    private function placeOrder(array $orderData): array
    {
        if ($this->broker->client_type === 'Angel') {
            return $this->angelHelper->placeAngelOrder($orderData);
        } elseif ($this->broker->client_type === 'Zerodha') {
            return $this->zerodhaHelper->placeZerodhaOrder($orderData);
        }
        
        return [
            'success' => false,
            'message' => 'Invalid broker type',
            'order_id' => null
        ];
    }

    /**
     * Execute pyramid order - place all orders
     */
    public function executePyramidOrder(PyramidOrder $pyramidOrder): array
    {
        // Get instrument details
        $instrument = $this->getInstrument(
            $pyramidOrder->symbol,
            $pyramidOrder->expiry_date->format('Y-m-d'),
            $pyramidOrder->strike_price,
            $pyramidOrder->option_type
        );

        if (!$instrument) {
            return [
                'success' => false,
                'message' => 'Contract not found in instruments database',
                'orders_placed' => 0
            ];
        }

        $successCount = 0;
        $results = [];

        // Update status to processing
        $pyramidOrder->update(['status' => 'processing']);

        // Place each pyramid order
        for ($i = 1; $i <= $pyramidOrder->num_pyramids; $i++) {
            // Calculate parameters
            $effectiveDiscount = $this->calculateEffectiveDiscount(
                $pyramidOrder->base_discount_pct,
                $pyramidOrder->discount_increment_pct,
                $i
            );

            $orderPrice = $this->calculateOrderPrice(
                $pyramidOrder->manual_ltp,
                $effectiveDiscount,
                $pyramidOrder->transaction_type,
                $instrument['tick_size']
            );

            $quantity = $this->calculateQuantity(
                $pyramidOrder->lots_per_order,
                $instrument['lot_size']
            );

            // Prepare order data based on broker type
            $orderData = $this->prepareOrderData($instrument, $pyramidOrder->transaction_type, $orderPrice, $quantity);

            // Create detail record
            $detail = PyramidOrderDetail::create([
                'pyramid_order_id' => $pyramidOrder->id,
                'pyramid_index' => $i,
                'effective_discount_pct' => $effectiveDiscount,
                'order_price' => $orderPrice,
                'quantity' => $quantity,
                'angel_symbol' => $orderData['symbol_name'] ?? null,
                'angel_token' => $orderData['token'] ?? null,
                'order_status' => 'pending',
            ]);

            // Place order
            $result = $this->placeOrder($orderData);

            // Update detail with result
            $detail->update([
                'angel_order_id' => $result['order_id'],
                'order_status' => $result['success'] ? 'placed' : 'failed',
                'status_message' => $result['message'],
                'placed_at' => now(),
                'updated_at' => now(),
            ]);

            if ($result['success']) {
                $successCount++;
            }

            $results[] = [
                'pyramid' => $i,
                'success' => $result['success'],
                'message' => $result['message'],
                'order_id' => $result['order_id'],
                'price' => $orderPrice,
                'quantity' => $quantity
            ];

            // Small delay between orders
            if ($i < $pyramidOrder->num_pyramids) {
                sleep(1);
            }
        }

        // Update main order status
        $finalStatus = 'failed';
        if ($successCount === $pyramidOrder->num_pyramids) {
            $finalStatus = 'completed';
        } elseif ($successCount > 0) {
            $finalStatus = 'partial';
        }

        $pyramidOrder->update([
            'status' => $finalStatus,
            'orders_placed' => $successCount,
            'executed_at' => now()
        ]);

        return [
            'success' => $successCount > 0,
            'message' => sprintf('%d of %d orders placed successfully', $successCount, $pyramidOrder->num_pyramids),
            'orders_placed' => $successCount,
            'details' => $results
        ];
    }

    /**
     * Prepare order data based on broker type
     */
    private function prepareOrderData(array $instrument, string $transactionType, float $price, int $quantity): array
    {
        if ($this->broker->client_type === 'Angel') {
            return [
                'angel_symbol' => $instrument['symbol'],
                'angel_token' => $instrument['token'],
                'transaction_type' => $transactionType,
                'price' => $price,
                'quantity' => $quantity,
                'symbol_name' => $instrument['symbol'],
                'token' => $instrument['token']
            ];
        } elseif ($this->broker->client_type === 'Zerodha') {
            return [
                'trading_symbol' => $instrument['trading_symbol'],
                'transaction_type' => $transactionType,
                'price' => $price,
                'quantity' => $quantity,
                'symbol_name' => $instrument['trading_symbol'],
                'token' => $instrument['instrument_token']
            ];
        }
        
        return [];
    }
}