<?php
namespace App\Services\Broker;

use App\Contracts\CpBrokerConnectorInterface;
use App\Models\BrokerApi;
use App\Models\AngelApiInstrument;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

require_once app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

/**
 * AngelBrokerConnector — FINAL. Do not modify once verified working.
 * Every caller (any analysis, any config) goes through placeOrder() only.
 */
class AngelBrokerConnector implements CpBrokerConnectorInterface
{
    private string $clientCode, $password, $pin, $apiKey, $apiSecret, $totpSecret;
    private string $clientLocalIp, $clientPublicIp, $macAddress;
    private string $jwtToken;
    private array $baseHeaders;
    private array $instrumentCache = [];

    private const BASE_URL = 'https://apiconnect.angelbroking.com';

    public function __construct(private BrokerApi $broker)
    {
        // Credentials come from .env — same convention as AngelPivotHelper
        $this->clientCode     = env('ANGEL_CLIENT_CODE', '');
        $this->password       = env('ANGEL_PASSWORD', '');
        $this->pin            = env('ANGEL_PIN', '');
        $this->apiKey         = env('ANGEL_API_KEY', '');
        $this->apiSecret      = env('ANGEL_API_SECRET', '');
        $this->totpSecret     = env('ANGEL_TOTP_SECRET', '');
        $this->clientLocalIp  = env('ANGEL_CLIENT_LOCAL_IP', '192.168.1.1');
        $this->clientPublicIp = env('ANGEL_CLIENT_PUBLIC_IP', '1.1.1.1');
        $this->macAddress     = env('ANGEL_MAC_ADDRESS', '00-00-00-00-00-00');

        $missing = array_keys(array_filter([
            'ANGEL_CLIENT_CODE' => $this->clientCode,
            'ANGEL_PIN'         => $this->pin,
            'ANGEL_API_KEY'     => $this->apiKey,
            'ANGEL_TOTP_SECRET' => $this->totpSecret,
        ], fn($v) => empty($v)));
        if ($missing) throw new \Exception('AngelBrokerConnector: missing .env keys: ' . implode(', ', $missing));

        $this->baseHeaders = [
            'X-UserType: USER', 'X-SourceID: WEB',
            'X-PrivateKey: ' . $this->apiKey,
            'X-ClientLocalIP: ' . $this->clientLocalIp,
            'X-ClientPublicIP: ' . $this->clientPublicIp,
            'X-MACAddress: ' . $this->macAddress,
            'Content-Type: application/json', 'Accept: application/json',
        ];

        $this->jwtToken = $this->generateAccessToken();
    }

    public static function isValid(BrokerApi $broker): bool
    {
        return $broker->client_type === 'AngelOne'
            && !empty(env('ANGEL_API_KEY'))
            && !empty(env('ANGEL_TOTP_SECRET'))
            && !empty(env('ANGEL_CLIENT_CODE'));
    }

    // ── Public: THE single entry point everything calls ────────────────────
    public function placeOrder(array $params): array
    {
        [$angelSymbol, $angelToken, $lotSize, $tickSize] =
            $this->getInstrumentInfo($params['trading_symbol'], $params['instrument_token']);

        if (empty($angelToken)) {
            throw new \Exception("AngelBrokerConnector: [{$angelSymbol}] not found in angel_api_instruments. Run angel_instrument:daily_update.");
        }

        $qty = $params['lots'] * $lotSize;
        $orderId = $this->sendOrder(
            $angelSymbol, $angelToken,
            $params['transaction_type'], $params['order_type'], $params['product'],
            $qty, $params['price'] ?? 0, $tickSize
        );

        return ['order_id' => $orderId, 'lot_size' => $lotSize, 'tick_size' => $tickSize, 'quantity' => $qty];
    }

    // ── Instrument lookup chain (exact copy of AngelPivotHelper's) ─────────
    private function getInstrumentInfo(string $tradingSymbol, int $instrumentToken): array
    {
        $key = (string) $instrumentToken;
        if (isset($this->instrumentCache[$key])) return $this->instrumentCache[$key];

        $angelRow = null;
        $zerodhaRow = ZerodhaInstrument::where('instrument_token', $instrumentToken)->first();
        if ($zerodhaRow) {
            $angelRow = AngelApiInstrument::where('token', (string) $zerodhaRow->exchange_token)->first();
        }
        if (!$angelRow) {
            $angelRow = AngelApiInstrument::where('symbol_name', strtoupper($tradingSymbol))->first();
        }

        $angelSymbolName = $angelRow->symbol_name ?? strtoupper($tradingSymbol);
        $angelToken      = $angelRow->token ?? '';
        $lotSize         = max(1, (int) ($angelRow->lotsize ?? 1));

        return $this->instrumentCache[$key] = [$angelSymbolName, $angelToken, $lotSize, 0.05];
    }

    private function sendOrder(string $symbol, string $token, string $txType, string $orderType, string $product, int $qty, float $price, float $tickSize): string
    {
        $rounded = number_format(round($price / $tickSize) * $tickSize, 2, '.', '');

        $payload = [
            'variety'         => 'NORMAL',
            'tradingsymbol'   => $symbol,
            'symboltoken'     => $token,
            'transactiontype' => $txType,
            'exchange'        => 'NFO',
            'ordertype'       => $orderType === 'LIMIT' ? 'LIMIT' : 'MARKET',
            'producttype'     => $product === 'NRML' ? 'CARRYFORWARD' : 'INTRADAY',
            'duration'        => 'DAY',
            'quantity'        => (string) $qty,
            'price'           => $orderType === 'LIMIT' ? $rounded : '0',
            'squareoff'       => '0',
            'stoploss'        => '0',
        ];

        $resp = $this->callApi('/rest/secure/angelbroking/order/v1/placeOrder', $payload);
        if (empty($resp['data']['orderid'])) {
            throw new \Exception("Angel placeOrder failed for {$symbol}: " . ($resp['message'] ?? json_encode($resp)));
        }
        return $resp['data']['orderid'];
    }

    // ── Auth (from your AngelConnectCls, cached) ────────────────────────────
    private function generateAccessToken(): string
    {
        return Cache::remember('ANGEL_API_TOKEN_' . $this->clientCode, 72000, function () {
            $totp = TOTP::create($this->totpSecret);
            $payload = ['clientcode' => $this->clientCode, 'password' => $this->pin, 'totp' => $totp->now()];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => self::BASE_URL . '/rest/auth/angelbroking/user/v1/loginByPassword',
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $this->baseHeaders,
            ]);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) throw new \Exception("AngelBrokerConnector login cURL error: {$err}");
            $data = json_decode($response, true);
            if (empty($data['data']['jwtToken'])) {
                throw new \Exception("AngelBrokerConnector login failed for [{$this->clientCode}]: " . json_encode($data));
            }
            return $data['data']['jwtToken'];
        });
    }

    private function callApi(string $endpoint, array $payload): array
    {
        $headers = $this->baseHeaders;
        $headers[] = 'Authorization: Bearer ' . $this->jwtToken;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => self::BASE_URL . $endpoint,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) throw new \Exception("AngelBrokerConnector cURL error [{$endpoint}]: {$err}");
        $data = json_decode($response, true);
        if (!$data) throw new \Exception("AngelBrokerConnector: invalid JSON from [{$endpoint}]");
        return $data;
    }
}