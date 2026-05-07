<?php

namespace App\Helpers;

use App\Models\ZerodhaInstrument;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ZerodhaOrderHelper
{
    private $broker;
    private $apiKey;
    private $accessToken;

    public function __construct(BrokerApi $broker)
    {
        $this->broker = $broker;
        $this->apiKey = $broker->api_key;
        $this->accessToken = $broker->access_token ?? null;
    }

    /**
     * Get Zerodha instrument details
     */
    public function getZerodhaInstrument(string $symbol, string $expiryDate, float $strikePrice, string $optionType): ?array
    {
        try {
            $instrument = ZerodhaInstrument::where('name', $symbol)
                ->where('exchange', 'NFO')
                ->whereDate('expiry', $expiryDate)
                ->where('strike', $strikePrice)
                ->where('instrument_type', $optionType)
                ->first();

            if (!$instrument) {
                Log::error("Zerodha instrument not found", [
                    'symbol' => $symbol,
                    'expiry_date' => $expiryDate,
                    'strike' => $strikePrice,
                    'option_type' => $optionType
                ]);
                return null;
            }

            return [
                'trading_symbol' => $instrument->trading_symbol,
                'instrument_token' => $instrument->instrument_token,
                'lot_size' => $instrument->lot_size,
                'tick_size' => $instrument->tick_size,
                'exchange' => $instrument->exchange,
            ];
        } catch (\Exception $e) {
            Log::error("Error fetching Zerodha instrument", [
                'error' => $e->getMessage(),
                'symbol' => $symbol
            ]);
            return null;
        }
    }

    /**
     * Place order via Zerodha API
     */
    public function placeZerodhaOrder(array $orderData): array
    {
        if (!$this->accessToken) {
            return [
                'success' => false,
                'message' => 'Access token not available. Please login to Zerodha.',
                'order_id' => null
            ];
        }

        $payload = [
            'tradingsymbol' => $orderData['trading_symbol'],
            'exchange' => 'NFO',
            'transaction_type' => $orderData['transaction_type'],
            'order_type' => 'LIMIT',
            'quantity' => $orderData['quantity'],
            'price' => $orderData['price'],
            'product' => 'NRML',
            'validity' => 'DAY',
        ];

        try {
            $response = Http::withHeaders([
                'X-Kite-Version' => '3',
                'Authorization' => 'token ' . $this->apiKey . ':' . $this->accessToken
            ])->asForm()->post('https://api.kite.trade/orders', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['data']['order_id'])) {
                return [
                    'success' => true,
                    'message' => 'Order placed successfully',
                    'order_id' => $responseData['data']['order_id']
                ];
            }

            $errorMessage = $responseData['message'] ?? 'Order placement failed';
            Log::error("Zerodha order rejected", [
                'response' => $responseData,
                'payload' => $payload
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'order_id' => null
            ];

        } catch (\Exception $e) {
            Log::error("Exception placing Zerodha order", [
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
}