<?php

namespace App\Contracts;

interface CpBrokerConnectorInterface
{
    /**
     * @param array{
     *   trading_symbol: string,   // Zerodha-format symbol (your DB standard)
     *   instrument_token: int,    // Zerodha instrument_token (used for lookup chain)
     *   transaction_type: string, // BUY | SELL
     *   order_type: string,       // LIMIT | MARKET
     *   product: string,          // MIS | NRML
     *   lots: int,
     *   price: float,             // ignored for MARKET
     * } $params
     * @return array{order_id:string, lot_size:int, tick_size:float, quantity:int}
     * @throws \Exception
     */
    public function placeOrder(array $params): array;
}