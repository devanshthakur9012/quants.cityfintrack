<?php

namespace App\Services\Broker;

use App\Models\AngelApiInstrument;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

require_once app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

/**
 * AngelBrokerService — all Angel One auth + order logic lives here, driven
 * entirely by the broker_apis row passed to the constructor.
 *
 * Token lifecycle:
 *   - getValidAccessToken() reuses the cached session (token + the exact
 *     IP/MAC used when that token was issued) while it's still valid.
 *   - login() is only triggered when nothing valid is cached, and caches
 *     the fresh token TOGETHER with the IP/MAC used to obtain it — this
 *     is critical: Angel's order-placement API appears to validate the
 *     session against the IP/MAC it was issued under, so if .env's
 *     ANGEL_CLIENT_PUBLIC_IP ever changes between login and a later
 *     order call, sending the NEW ip with an OLD token gets rejected
 *     (AG8001) even though both values are individually "valid".
 *     Caching them as one unit makes that class of bug impossible.
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

    /** Reuse the cached session if valid; otherwise perform a fresh login. */
    public function getValidAccessToken(): array
    {
        $cacheKey = $this->sessionCacheKey();
        $session  = Cache::get($cacheKey);

        if ($session && $this->hasValidStoredToken()) {
            return $session;
        }

        return $this->login();
    }

    /**
     * Force a fresh TOTP login, cache the token TOGETHER with the exact
     * IP/MAC used to obtain it, and persist just the token/expiry to
     * broker_apis (for hasValidStoredToken()'s DB-side expiry check).
     * Returns the full session array: ['token'=>, 'localIp'=>, 'publicIp'=>, 'mac'=>]
     */
    public function login(): array
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

        // These are the values THIS login attempt uses — frozen into the
        // cached session below so every future call replays the same ones.
        $localIp  = env('ANGEL_CLIENT_LOCAL_IP', '192.168.1.1');
        $publicIp = env('ANGEL_CLIENT_PUBLIC_IP', '1.1.1.1');
        $mac      = env('ANGEL_MAC_ADDRESS', '00-00-00-00-00-00');

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => self::BASE_URL . '/rest/auth/angelbroking/user/v1/loginByPassword',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $this->loginHeaders($apiKey, $localIp, $publicIp, $mac),
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

        $session = [
            'token'    => $jwtToken,
            'localIp'  => $localIp,
            'publicIp' => $publicIp,
            'mac'      => $mac,
        ];

        // Cache the whole session as one unit — this is what makes
        // login() and every later placeOrder() call agree on IP/MAC.
        Cache::put($this->sessionCacheKey(), $session, now()->addHours(self::TOKEN_TTL_HOURS));

        // DB row still tracks token/expiry so hasValidStoredToken() and
        // any admin UI showing "last login" keep working as before.
        $this->broker->update([
            'access_token'     => $jwtToken,
            'request_token'    => $data['data']['refreshToken'] ?? null,
            'token_expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
            'last_login_at'    => now(),
            'is_token_valid'   => true,
        ]);
        $this->broker->refresh();

        Log::info("AngelBrokerService: login OK for broker #{$this->broker->id} ({$clientCode}) — session cached with publicIp={$publicIp}");

        return $session;
    }

    // ── Public: order placement (the ONLY method callers should use) ────

    public function placeOrder(array $params): array
    {
        $session = $this->getValidAccessToken();

        [$angelSymbol, $angelToken, $lotSize, $tickSize] = $this->getInstrumentInfo($params);

        if (empty($angelToken)) {
            throw new \Exception("AngelBrokerService: [{$angelSymbol}] not found in angel_api_instruments. Run angel_instrument:daily_update.");
        }

        $qty = $params['lots'] * $lotSize;
        $orderId = $this->sendOrder(
            $session, $angelSymbol, $angelToken,
            $params['transaction_type'], $params['order_type'], $params['product'],
            $qty, $params['price'] ?? 0, $tickSize
        );

        return ['order_id' => $orderId, 'lot_size' => $lotSize, 'tick_size' => $tickSize, 'quantity' => $qty];
    }

    // ── Private: instrument lookup (Zerodha exchange_token bridge) ──────

    private function getInstrumentInfo(array $params): array
    {
        $key = $params['instrument_token'] . '|' . $params['instrument_type'];
        if (isset($this->instrumentCache[$key])) return $this->instrumentCache[$key];

        $angelRow = null;

        $zerodhaRow = ZerodhaInstrument::where('instrument_token', $params['instrument_token'])->first();
        if ($zerodhaRow) {
            $angelRow = AngelApiInstrument::where('token', (string) $zerodhaRow->exchange_token)->first();
        }

        if (!$angelRow) {
            $angelRow = $this->matchAngelOption(
                $params['base_symbol'],
                $params['expiry_date'],
                (float) $params['strike'],
                $params['instrument_type']
            );
        }

        $angelSymbolName = $angelRow->symbol_name ?? strtoupper($params['trading_symbol']);
        $angelToken      = $angelRow->token ?? '';
        $lotSize         = max(1, (int) ($angelRow->lotsize ?? 1));

        return $this->instrumentCache[$key] = [$angelSymbolName, $angelToken, $lotSize, 0.05];
    }

    private function matchAngelOption(string $baseSymbol, string $expiryDate, float $strike, string $optionType): ?object
    {
        $expiry = \Carbon\Carbon::parse($expiryDate)->format('Y-m-d');
        $instrumentType = in_array($baseSymbol, ['NIFTY', 'BANKNIFTY', 'SENSEX']) ? 'OPTIDX' : 'OPTSTK';

        return AngelApiInstrument::where('name', strtoupper($baseSymbol))
            ->where('expiry', $expiry)
            ->where('exch_seg', 'NFO')
            ->where('instrumenttype', $instrumentType)
            ->where('symbol_name', 'LIKE', '%' . strtoupper($optionType))
            ->where(function ($q) use ($strike) {
                $q->whereRaw('ABS(CAST(strike AS DECIMAL(15,2)) - ?) < 0.01', [$strike])
                  ->orWhereRaw('ABS(CAST(strike AS DECIMAL(15,2)) - ?) < 0.01', [$strike * 100]);
            })
            ->first();
    }

    private function sendOrder(array $session, string $symbol, string $token, string $txType, string $orderType, string $product, int $qty, float $price, float $tickSize): string
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

        Log::info("ANGEL_DEBUG payload", [
            'symbol'      => $symbol,
            'symboltoken' => $token,
            'session_publicIp' => $session['publicIp'],
        ]);

        $resp = $this->callApi('/rest/secure/angelbroking/order/v1/placeOrder', $payload, $session);

        Log::info("ANGEL_DEBUG raw response", ['resp' => $resp]);

        if (empty($resp['data']['orderid'])) {
            throw new \Exception("Angel placeOrder failed for {$symbol}: " . ($resp['message'] ?? json_encode($resp)));
        }
        return $resp['data']['orderid'];
    }

    private function callApi(string $endpoint, array $payload, array $session): array
    {
        $headers = $this->orderHeaders($session);

        Log::info("ANGEL_DEBUG headers", ['headers' => $headers, 'endpoint' => $endpoint]);

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

    /** Headers for the LOGIN call — no Authorization header exists yet. */
    private function loginHeaders(string $apiKey, string $localIp, string $publicIp, string $mac): array
    {
        return [
            'X-UserType: USER', 'X-SourceID: WEB',
            'X-PrivateKey: '     . $apiKey,
            'X-ClientLocalIP: '  . $localIp,
            'X-ClientPublicIP: ' . $publicIp,
            'X-MACAddress: '     . $mac,
            'Content-Type: application/json', 'Accept: application/json',
        ];
    }

    /** Headers for authenticated calls — MUST reuse the IP/MAC frozen into $session at login. */
    private function orderHeaders(array $session): array
    {
        return [
            'X-UserType: USER', 'X-SourceID: WEB',
            'X-PrivateKey: '     . $this->broker->api_key,
            'X-ClientLocalIP: '  . $session['localIp'],
            'X-ClientPublicIP: ' . $session['publicIp'],
            'X-MACAddress: '     . $session['mac'],
            'Content-Type: application/json', 'Accept: application/json',
            'Authorization: Bearer ' . $session['token'],
        ];
    }

    private function sessionCacheKey(): string
    {
        return 'ANGEL_SESSION_' . $this->broker->id;
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
        Cache::forget($this->sessionCacheKey());
        $this->broker->update(['is_token_valid' => false]);
        Log::error("AngelBrokerService: broker #{$this->broker->id} token invalidated — {$reason}");
    }
}