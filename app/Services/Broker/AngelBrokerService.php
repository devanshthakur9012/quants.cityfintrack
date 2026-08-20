<?php

namespace App\Services\Broker;

use App\Models\AngelApiInstrument;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

require_once app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

/**
 * AngelBrokerService — FINAL once verified. All Angel One auth + order logic
 * lives here, driven entirely by the broker_apis row passed to the
 * constructor (no .env credentials — every Angel account is a DB row).
 *
 * Token lifecycle is self-managing:
 *   - getValidAccessToken() reuses broker_apis.access_token if it's still
 *     marked valid and not expired — no repeat logins per call.
 *   - login() is only triggered when the stored token is missing/expired/
 *     invalid, and persists the fresh token straight back to broker_apis.
 *
 * Nothing outside this file should ever build an Angel payload directly —
 * call login() or placeOrder() only.
 */
class AngelBrokerService
{
    private const BASE_URL        = 'https://apiconnect.angelbroking.com';
    private const TOKEN_TTL_HOURS = 20; // Angel JWT is practically valid until next trading session

    private array $instrumentCache = [];

    public function __construct(private BrokerApi $broker) {}

    // ── Public: token lifecycle ─────────────────────────────────────────

    /** Reuse the stored token if valid; otherwise perform a fresh login. */
    public function getValidAccessToken(): string
    {
        if ($this->hasValidStoredToken()) {
            return $this->broker->access_token;
        }
        return $this->login();
    }

    /** Force a fresh TOTP login, persist the result to broker_apis, return the JWT. */
    public function login(): string
    {
        $clientCode = $this->broker->account_user_name;
        $pin        = $this->broker->security_pin ?: $this->broker->account_password;
        $apiKey     = $this->broker->api_key;
        $totpSecret = $this->broker->totp;

        if (!$clientCode || !$pin || !$apiKey || !$totpSecret) {
            throw new \Exception("AngelBrokerService: broker #{$this->broker->id} is missing client code / pin / api key / totp secret.");
        }

        $totp = TOTP::create($totpSecret);

        $payload = [
            'clientcode' => $clientCode,
            'password'   => $pin,
            'totp'       => $totp->now(),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => self::BASE_URL . '/rest/auth/angelbroking/user/v1/loginByPassword',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $this->headers(),
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            $this->markInvalid("cURL error: {$err}");
            throw new \Exception("AngelBrokerService login cURL error: {$err}");
        }

        $data = json_decode($response, true);

        if (empty($data['status']) || empty($data['data']['jwtToken'])) {
            $msg = $data['message'] ?? json_encode($data);
            $this->markInvalid($msg);
            throw new \Exception("AngelBrokerService login failed for [{$clientCode}]: {$msg}");
        }

        $jwtToken = $data['data']['jwtToken'];

        $this->broker->update([
            'access_token'     => $jwtToken,
            'request_token'    => $data['data']['refreshToken'] ?? null,
            'token_expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
            'last_login_at'    => now(),
            'is_token_valid'   => true,
        ]);
        $this->broker->refresh();

        Log::info("AngelBrokerService: login OK for broker #{$this->broker->id} ({$clientCode})");

        return $jwtToken;
    }

    // ── Public: order placement (the ONLY method callers should use) ────

    public function placeOrder(array $params): array
    {
        $jwtToken = $this->getValidAccessToken();

        [$angelSymbol, $angelToken, $lotSize, $tickSize] =
            $this->getInstrumentInfo($params['trading_symbol'], $params['instrument_token']);

        if (empty($angelToken)) {
            throw new \Exception("AngelBrokerService: [{$angelSymbol}] not found in angel_api_instruments. Run angel_instrument:daily_update.");
        }

        $qty = $params['lots'] * $lotSize;
        $orderId = $this->sendOrder(
            $jwtToken, $angelSymbol, $angelToken,
            $params['transaction_type'], $params['order_type'], $params['product'],
            $qty, $params['price'] ?? 0, $tickSize
        );

        return ['order_id' => $orderId, 'lot_size' => $lotSize, 'tick_size' => $tickSize, 'quantity' => $qty];
    }

    // ── Private: instrument lookup (Zerodha exchange_token bridge) ──────

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

    private function sendOrder(string $jwtToken, string $symbol, string $token, string $txType, string $orderType, string $product, int $qty, float $price, float $tickSize): string
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

        $resp = $this->callApi('/rest/secure/angelbroking/order/v1/placeOrder', $payload, $jwtToken);
        if (empty($resp['data']['orderid'])) {
            throw new \Exception("Angel placeOrder failed for {$symbol}: " . ($resp['message'] ?? json_encode($resp)));
        }
        return $resp['data']['orderid'];
    }

    private function callApi(string $endpoint, array $payload, string $jwtToken): array
    {
        $headers = $this->headers();
        $headers[] = 'Authorization: Bearer ' . $jwtToken;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => self::BASE_URL . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) throw new \Exception("AngelBrokerService cURL error [{$endpoint}]: {$err}");
        $data = json_decode($response, true);
        if (!$data) throw new \Exception("AngelBrokerService: invalid JSON from [{$endpoint}]");
        return $data;
    }

    private function headers(): array
    {
        return [
            'X-UserType: USER', 'X-SourceID: WEB',
            'X-PrivateKey: '     . $this->broker->api_key,
            'X-ClientLocalIP: '  . env('ANGEL_CLIENT_LOCAL_IP', '192.168.1.1'),
            'X-ClientPublicIP: ' . env('ANGEL_CLIENT_PUBLIC_IP', '1.1.1.1'),
            'X-MACAddress: '     . env('ANGEL_MAC_ADDRESS', '00-00-00-00-00-00'),
            'Content-Type: application/json', 'Accept: application/json',
        ];
    }

    private function hasValidStoredToken(): bool
    {
        return $this->broker->is_token_valid
            && !empty($this->broker->access_token)
            && !empty($this->broker->token_expires_at)
            && Carbon::parse($this->broker->token_expires_at)->isFuture();
    }

    private function markInvalid(string $reason): void
    {
        $this->broker->update(['is_token_valid' => false]);
        Log::error("AngelBrokerService: broker #{$this->broker->id} token invalidated — {$reason}");
    }
}