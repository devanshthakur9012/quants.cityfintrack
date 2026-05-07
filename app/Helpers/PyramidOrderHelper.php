<?php

namespace App\Helpers;

use App\Models\AngelApiInstrument;
use App\Models\BrokerApi;
use App\Models\PyramidOrder;
use App\Models\PyramidOrderDetail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PyramidOrderHelper
{
    private $broker;
    private $apiKey;
    private $clientLocalIp;
    private $clientPublicIp;
    private $macAddress;

    public function __construct(BrokerApi $broker)
    {
        $this->broker = $broker;
        $this->apiKey = $broker->api_key;
        $this->clientLocalIp = '192.168.1.31';
        $this->clientPublicIp = '122.161.67.85';
        $this->macAddress = '14-85-7F-92-D0-B0';
    }

    /**
     * Calculate effective discount for pyramid index
     */
    public function calculateEffectiveDiscount(float $baseDiscount, float $discountIncrement, int $pyramidIndex): float
    {
        // Formula: base_discount_pct × (1 + ((i − 1) × discount_increment_pct / 100))
        return round($baseDiscount * (1 + (($pyramidIndex - 1) * $discountIncrement / 100)), 4);
    }

    /**
     * Calculate order price based on transaction type and discount
     */
    public function calculateOrderPrice(float $manualLtp, float $effectiveDiscount, string $transactionType, float $tickSize): float
    {
        if ($transactionType === 'BUY') {
            // BUY: price = manual_ltp × (1 − effective_discount_pct / 100)
            $price = $manualLtp * (1 - $effectiveDiscount / 100);
        } else {
            // SELL: price = manual_ltp × (1 + effective_discount_pct / 100)
            $price = $manualLtp * (1 + $effectiveDiscount / 100);
        }

        // Round to tick size
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
     * Get Angel instrument details for option contract
     */
    public function getAngelInstrument(string $symbol, string $expiryDate, float $strikePrice, string $optionType): ?array
    {
        try {
            $expiry = Carbon::parse($expiryDate);
            
            // Strike in Angel DB is stored * 100
            $angelStrike = $strikePrice * 100;
            
            $instrument = AngelApiInstrument::where('name', $symbol)
                ->where('exch_seg', 'NFO')
                ->whereDate('expiry', $expiryDate)
                ->where('strike', 'like', $angelStrike.'%')
                ->where('instrumenttype', 'OPTSTK')
                ->where(function($query) use ($optionType) {
                    if ($optionType === 'CE') {
                        $query->where('symbol_name', 'like', '%CE');
                    } else {
                        $query->where('symbol_name', 'like', '%PE');
                    }
                })
                ->first();

            if (!$instrument) {
                Log::error("Angel instrument not found", [
                    'expiry' => $expiry,
                    'symbol' => $symbol,
                    'expiry_date' => $expiryDate,
                    'strike' => $strikePrice,
                    'angel_strike' => $angelStrike,
                    'option_type' => $optionType
                ]);
                return null;
            }

            return [
                'symbol' => $instrument->symbol_name,
                'token' => $instrument->token,
                'lot_size' => $instrument->lotsize,
                'tick_size' => $instrument->tick_size / 100, // Convert from paise to rupees
                'exchange' => $instrument->exch_seg,
            ];
        } catch (\Exception $e) {
            Log::error("Error fetching Angel instrument", [
                'error' => $e->getMessage(),
                'symbol' => $symbol
            ]);
            return null;
        }
    }

    /**
     * Generate Angel API JWT token
     */
    private function generateAngelToken(): ?string
    {
        try {
            $angelHelper = new AngelConnectCls([
                'accountUserName' => $this->broker->account_user_name,
                'apiKey' => $this->broker->api_key,
                'pin' => $this->broker->security_pin,
                'totp_secret' => $this->broker->totp,
            ]);

            $tokenArr = $angelHelper->generate_access_token();

            if (!$tokenArr || !isset($tokenArr['token'])) {
                Log::error("Failed to generate Angel token", ['broker' => $this->broker->account_user_name]);
                return null;
            }

            return $tokenArr['token'];
        } catch (\Exception $e) {
            Log::error("Exception generating Angel token", [
                'error' => $e->getMessage(),
                'broker' => $this->broker->account_user_name
            ]);
            return null;
        }
    }

    /**
     * Place order via Angel API
     */
    public function placeAngelOrder(array $orderData): array
    {
        $token = $this->generateAngelToken();
        
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to authenticate with Angel API',
                'order_id' => null
            ];
        }

        $payload = [
            'variety' => 'NORMAL',
            'tradingsymbol' => $orderData['angel_symbol'],
            'symboltoken' => $orderData['angel_token'],
            'transactiontype' => $orderData['transaction_type'],
            'exchange' => 'NFO',
            'ordertype' => 'LIMIT',
            'producttype' => 'CARRYFORWARD',
            'duration' => 'DAY',
            'price' => (string) $orderData['price'],
            'quantity' => (string) $orderData['quantity'],
            'squareoff' => '0',
            'stoploss' => '0'
        ];

        try {
            $httpHeaders = [
                'X-UserType: USER',
                'X-SourceID: WEB',
                'X-PrivateKey: ' . $this->apiKey,
                'X-ClientLocalIP: ' . $this->clientLocalIp,
                'X-ClientPublicIP: ' . $this->clientPublicIp,
                'X-MACAddress: ' . $this->macAddress,
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/order/v1/placeOrder',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $httpHeaders,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                Log::error("Angel API cURL error", ['error' => $err, 'payload' => $payload]);
                return [
                    'success' => false,
                    'message' => 'Network error: ' . $err,
                    'order_id' => null
                ];
            }

            $responseData = json_decode($response, true);

            if ($httpCode !== 200 || !$responseData) {
                Log::error("Angel API HTTP error", [
                    'http_code' => $httpCode,
                    'response' => $response,
                    'payload' => $payload
                ]);
                return [
                    'success' => false,
                    'message' => 'API request failed',
                    'order_id' => null
                ];
            }

            if (!isset($responseData['status']) || !$responseData['status']) {
                $message = $responseData['message'] ?? 'Order placement failed';
                Log::error("Angel order rejected", [
                    'response' => $responseData,
                    'payload' => $payload
                ]);
                return [
                    'success' => false,
                    'message' => $message,
                    'order_id' => null
                ];
            }

            // Extract order ID
            $dataSection = $responseData['data'] ?? null;
            if (is_string($dataSection)) {
                $dataSection = json_decode($dataSection, true);
            }

            $orderId = null;
            if (is_array($dataSection)) {
                $orderId = $dataSection['uniqueorderid'] ?? $dataSection['orderid'] ?? null;
            }

            if (!$orderId) {
                Log::warning("Order placed but no order ID received", ['response' => $responseData]);
            }

            return [
                'success' => true,
                'message' => 'Order placed successfully',
                'order_id' => $orderId
            ];

        } catch (\Exception $e) {
            Log::error("Exception placing Angel order", [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'order_id' => null
            ];
        }
    }

    /**
     * Execute pyramid order - place all orders
     */
    public function executePyramidOrder(PyramidOrder $pyramidOrder): array
    {
        // Get Angel instrument details
        $instrument = $this->getAngelInstrument(
            $pyramidOrder->symbol,
            $pyramidOrder->expiry_date->format('Y-m-d'),
            $pyramidOrder->strike_price,
            $pyramidOrder->option_type
        );

        if (!$instrument) {
            return [
                'success' => false,
                'message' => 'Contract not found in Angel instruments database',
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

            // Create detail record
            $detail = PyramidOrderDetail::create([
                'pyramid_order_id' => $pyramidOrder->id,
                'pyramid_index' => $i,
                'effective_discount_pct' => $effectiveDiscount,
                'order_price' => $orderPrice,
                'quantity' => $quantity,
                'angel_symbol' => $instrument['symbol'],
                'angel_token' => $instrument['token'],
                'order_status' => 'pending',
            ]);

            // Place order
            $result = $this->placeAngelOrder([
                'angel_symbol' => $instrument['symbol'],
                'angel_token' => $instrument['token'],
                'transaction_type' => $pyramidOrder->transaction_type,
                'price' => $orderPrice,
                'quantity' => $quantity,
            ]);

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
}