<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;

/**
 * ZerodhaPivotHelper
 *
 * Handles order placement for Zerodha (KiteConnect) broker accounts.
 * Used by PlacePivotOrders command.
 *
 * Symbol format : NIFTY2550518000CE  (YY + MM_number + DD + strike + CE/PE)
 * Token source  : zerodha_instruments table (instrument_token column)
 * Product names : MIS | NRML  (sent as-is to Kite)
 * Auth          : api_key + access_token via KiteConnect SDK
 */
class ZerodhaPivotHelper
{
    private KiteConnect $kite;
    private BrokerApi   $broker;

    /** @var array<string, array{int, float}> [lot_size, tick_size] keyed by instrument_token */
    private array $instrumentCache = [];

    public function __construct(BrokerApi $broker)
    {
        $this->broker = $broker;
        $this->kite   = new KiteConnect($broker->api_key);
        $this->kite->setAccessToken($broker->access_token);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether a BrokerApi record is a valid Zerodha account.
     */
    public static function isValid(BrokerApi $broker): bool
    {
        return $broker->client_type === 'Zerodha'
            && !empty($broker->api_key)
            && !empty($broker->access_token);
    }

    /**
     * Place an order (or chunked orders if over freeze limit) on Zerodha.
     *
     * @param  string   $tradingSymbol    Zerodha format e.g. NIFTY2550518000CE
     * @param  int      $instrumentToken  Zerodha instrument_token (for lot/tick lookup)
     * @param  string   $transactionType  BUY | SELL
     * @param  string   $orderType        LIMIT | MARKET
     * @param  string   $product          MIS | NRML
     * @param  int      $lots             Number of lots (multiplied by lot_size internally)
     * @param  float    $orderPrice       Raw price (tick-rounded for LIMIT orders)
     * @param  int|null $freezeLimitLots  Max lots per single order; null = no chunking
     * @return array    ['order_ids' => string[], 'lot_size' => int, 'tick_size' => float, 'total_qty' => int]
     * @throws \Exception on Kite API failure
     */
    public function placeOrder(
        string  $tradingSymbol,
        int     $instrumentToken,
        string  $transactionType,
        string  $orderType,
        string  $product,
        int     $lots,
        float   $orderPrice,
        ?int    $freezeLimitLots = null
    ): array {
        [$lotSize, $tickSize] = $this->getInstrumentInfo($tradingSymbol, $instrumentToken);
        $totalQty = $lots * $lotSize;

        if ($freezeLimitLots && $lots > $freezeLimitLots) {
            return $this->placeChunked(
                $tradingSymbol, $transactionType, $orderType, $product,
                $lots, $lotSize, $tickSize, $freezeLimitLots, $orderPrice
            );
        }

        $orderId = $this->sendOrder(
            $tradingSymbol, $transactionType, $orderType,
            $product, $totalQty, $orderPrice, $tickSize
        );

        return [
            'order_ids' => [$orderId],
            'lot_size'  => $lotSize,
            'tick_size' => $tickSize,
            'total_qty' => $totalQty,
        ];
    }

    /**
     * Get [lot_size, tick_size] for a Zerodha instrument.
     * Cached per instrument_token string.
     */
    public function getInstrumentInfo(string $tradingSymbol, int $instrumentToken): array
    {
        $key = (string) $instrumentToken;

        if (isset($this->instrumentCache[$key])) {
            return $this->instrumentCache[$key];
        }

        $row = ZerodhaInstrument::where('instrument_token', $instrumentToken)->first()
            ?? ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                ->where('exchange', 'NFO')
                ->first();

        $lotSize  = $row ? (int)   $row->lot_size  : 1;
        $tickSize = $row ? (float) $row->tick_size : 0.05;

        if ($lotSize  <= 0) $lotSize  = 1;
        if ($tickSize <= 0) $tickSize = 0.05;

        $this->instrumentCache[$key] = [$lotSize, $tickSize];
        return $this->instrumentCache[$key];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────────

    private function placeChunked(
        string $tradingSymbol,
        string $transactionType,
        string $orderType,
        string $product,
        int    $lots,
        int    $lotSize,
        float  $tickSize,
        int    $freezeLimitLots,
        float  $orderPrice
    ): array {
        $remaining   = $lots;
        $orderIds    = [];
        $chunkNum    = 0;
        $totalChunks = (int) ceil($lots / $freezeLimitLots);

        while ($remaining > 0) {
            $chunkLots  = min($freezeLimitLots, $remaining);
            $chunkQty   = $chunkLots * $lotSize;
            $chunkNum++;

            $orderId    = $this->sendOrder(
                $tradingSymbol, $transactionType, $orderType,
                $product, $chunkQty, $orderPrice, $tickSize
            );
            $orderIds[] = $orderId;

            Log::info("ZerodhaPivotHelper chunk {$chunkNum}/{$totalChunks} | {$tradingSymbol} qty={$chunkQty} order_id={$orderId}");

            $remaining -= $chunkLots;
            if ($remaining > 0) sleep(2);
        }

        return [
            'order_ids' => $orderIds,
            'lot_size'  => $lotSize,
            'tick_size' => $tickSize,
            'total_qty' => $lots * $lotSize,
        ];
    }

    private function sendOrder(
        string $tradingSymbol,
        string $transactionType,
        string $orderType,
        string $product,
        int    $qty,
        float  $orderPrice,
        float  $tickSize
    ): string {
        // Round to nearest tick
        $roundedPrice = number_format(
            round($orderPrice / $tickSize) * $tickSize, 2, '.', ''
        );

        $params = [
            'tradingsymbol'    => $tradingSymbol,   // e.g. NIFTY2550518000CE
            'exchange'         => 'NFO',
            'transaction_type' => $transactionType,  // BUY | SELL
            'order_type'       => $orderType,         // LIMIT | MARKET
            'quantity'         => $qty,               // int (lots × lot_size)
            'product'          => $product,           // MIS | NRML
            'validity'         => 'DAY',
        ];

        if ($orderType === 'LIMIT') {
            $params['price'] = $roundedPrice;
        }

        $response = $this->kite->placeOrder('regular', $params);

        if (empty($response->order_id)) {
            throw new \Exception(
                "Zerodha returned empty order_id for {$tradingSymbol}"
            );
        }

        return $response->order_id;
    }
}