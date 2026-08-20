<?php

namespace App\Services\Broker;

use App\Contracts\CpBrokerConnectorInterface;
use App\Models\BrokerApi;
use KiteConnect\KiteConnect;

/**
 * ZerodhaBrokerConnector — FINAL once verified. Mirrors ZerodhaPivotHelper's
 * placeOrder pattern from your pivot module; wire the real KiteConnect calls
 * in here and never touch it from business logic again.
 */
class ZerodhaBrokerConnector implements CpBrokerConnectorInterface
{
    private KiteConnect $kite;

    public function __construct(private BrokerApi $broker)
    {
        $this->kite = new KiteConnect(env('KITE_API_KEY'));
        $this->kite->setAccessToken($broker->access_token);
    }

    public static function isValid(BrokerApi $broker): bool
    {
        return $broker->client_type === 'Zerodha' && !empty($broker->access_token);
    }

    public function placeOrder(array $params): array
    {
        $orderId = $this->kite->placeOrder(
            $this->kite::VARIETY_REGULAR,
            [
                'exchange'         => 'NFO',
                'tradingsymbol'    => $params['trading_symbol'],
                'transaction_type' => $params['transaction_type'],
                'order_type'       => $params['order_type'],
                'quantity'         => $params['lots'] * $this->getLotSize($params['trading_symbol']),
                'product'          => $params['product'],
                'price'            => $params['order_type'] === 'LIMIT' ? $params['price'] : null,
            ]
        );

        return ['order_id' => $orderId, 'lot_size' => $this->getLotSize($params['trading_symbol']), 'tick_size' => 0.05, 'quantity' => $params['lots'] * $this->getLotSize($params['trading_symbol'])];
    }

    private function getLotSize(string $tradingSymbol): int
    {
        // TODO: same lookup pattern as ZerodhaPivotHelper::getInstrumentInfo()
        return \App\Models\ZerodhaInstrument::where('tradingsymbol', $tradingSymbol)->value('lot_size') ?? 1;
    }
}