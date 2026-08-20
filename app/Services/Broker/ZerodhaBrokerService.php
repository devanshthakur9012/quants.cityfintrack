<?php

namespace App\Services\Broker;

use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;

require_once app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

/**
 * ZerodhaBrokerService — FINAL once verified. All Zerodha auth + order logic
 * lives here, driven entirely by the broker_apis row passed to the
 * constructor.
 *
 * login() replicates Kite's own web login flow to avoid a manual
 * request_token step:
 *   1. POST /api/login   (user_id + password)        → request_id
 *   2. POST /api/twofa   (request_id + TOTP code)     → authenticated session cookies
 *   3. GET  /connect/login?api_key=...  (with cookies) → redirect carries request_token
 *   4. KiteConnect::generateSession(request_token, api_secret) → access_token
 *
 * Token lifecycle mirrors AngelBrokerService: getValidAccessToken() reuses
 * the DB-stored token while it's valid; login() only runs when needed and
 * writes the result straight back to broker_apis.
 */
class ZerodhaBrokerService
{
    private const LOGIN_URL       = 'https://kite.zerodha.com/api/login';
    private const TWOFA_URL       = 'https://kite.zerodha.com/api/twofa';
    private const CONNECT_URL     = 'https://kite.zerodha.com/connect/login';
    private const TOKEN_TTL_HOURS = 20; // Kite access_token is valid until ~7:30am next trading day

    private array $instrumentCache = [];

    public function __construct(private BrokerApi $broker) {}

    // ── Public: token lifecycle ─────────────────────────────────────────

    public function getValidAccessToken(): string
    {
        if ($this->hasValidStoredToken()) {
            return $this->broker->access_token;
        }
        return $this->login();
    }

    public function login(): string
    {
        $userId     = $this->broker->account_user_name;
        $password   = $this->broker->account_password;
        $totpSecret = $this->broker->totp;
        $apiKey     = $this->broker->api_key;
        $apiSecret  = $this->broker->api_secret_key;

        if (!$userId || !$password || !$totpSecret || !$apiKey || !$apiSecret) {
            throw new \Exception("ZerodhaBrokerService: broker #{$this->broker->id} is missing required credentials.");
        }

        $cookieFile = tempnam(sys_get_temp_dir(), 'kite_cookie_');

        try {
            // Step 1: user_id + password → request_id
            $step1 = $this->curlPost(self::LOGIN_URL, [
                'user_id'  => $userId,
                'password' => $password,
            ], $cookieFile);

            if (empty($step1['data']['request_id'])) {
                throw new \Exception('Zerodha login step 1 (credentials) failed: ' . json_encode($step1));
            }
            $requestId = $step1['data']['request_id'];

            // Step 2: TOTP 2FA
            $totp = TOTP::create($totpSecret);
            $step2 = $this->curlPost(self::TWOFA_URL, [
                'user_id'     => $userId,
                'request_id'  => $requestId,
                'twofa_value' => $totp->now(),
                'twofa_type'  => 'totp',
            ], $cookieFile);

            if (empty($step2['status']) || $step2['status'] !== 'success') {
                throw new \Exception('Zerodha login step 2 (TOTP) failed: ' . json_encode($step2));
            }

            // Step 3: authenticated session → request_token via redirect
            $requestToken = $this->fetchRequestToken($apiKey, $cookieFile);
            if (!$requestToken) {
                throw new \Exception('Zerodha login: could not extract request_token from redirect.');
            }

            // Step 4: exchange for access_token via official SDK
            $kite    = new KiteConnect($apiKey);
            $session = $kite->generateSession($requestToken, $apiSecret);
            $accessToken = $session->access_token ?? null;

            if (!$accessToken) {
                throw new \Exception('Zerodha generateSession returned no access_token: ' . json_encode($session));
            }

            $this->broker->update([
                'request_token'    => $requestToken,
                'access_token'     => $accessToken,
                'token_expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
                'last_login_at'    => now(),
                'is_token_valid'   => true,
            ]);
            $this->broker->refresh();

            Log::info("ZerodhaBrokerService: login OK for broker #{$this->broker->id} ({$userId})");

            return $accessToken;

        } catch (\Exception $e) {
            $this->broker->update(['is_token_valid' => false]);
            Log::error("ZerodhaBrokerService: login FAILED for broker #{$this->broker->id} — {$e->getMessage()}");
            throw $e;
        } finally {
            @unlink($cookieFile);
        }
    }

    // ── Public: order placement (the ONLY method callers should use) ────

    public function placeOrder(array $params): array
    {
        $accessToken = $this->getValidAccessToken();

        $kite = new KiteConnect($this->broker->api_key);
        $kite->setAccessToken($accessToken);

        $lotSize = $this->getLotSize($params['trading_symbol']);
        $qty     = $params['lots'] * $lotSize;

        $orderId = $kite->placeOrder($kite::VARIETY_REGULAR, [
            'exchange'         => 'NFO',
            'tradingsymbol'    => $params['trading_symbol'],
            'transaction_type' => $params['transaction_type'],
            'order_type'       => $params['order_type'],
            'quantity'         => $qty,
            'product'          => $params['product'],
            'price'            => $params['order_type'] === 'LIMIT' ? ($params['price'] ?? null) : null,
        ]);

        return ['order_id' => $orderId, 'lot_size' => $lotSize, 'tick_size' => 0.05, 'quantity' => $qty];
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private function getLotSize(string $tradingSymbol): int
    {
        if (isset($this->instrumentCache[$tradingSymbol])) return $this->instrumentCache[$tradingSymbol];
        $lotSize = ZerodhaInstrument::where('tradingsymbol', $tradingSymbol)->value('lot_size') ?? 1;
        return $this->instrumentCache[$tradingSymbol] = max(1, (int) $lotSize);
    }

    private function fetchRequestToken(string $apiKey, string $cookieFile): ?string
    {
        $url = self::CONNECT_URL . '?v=3&api_key=' . urlencode($apiKey);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, // need the raw redirect, not the destination
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) throw new \Exception("Zerodha connect/login cURL error: {$err}");

        if (preg_match('/^Location:\s*(.+)$/mi', $response, $m)) {
            $query = parse_url(trim($m[1]), PHP_URL_QUERY);
            parse_str($query ?? '', $params);
            return $params['request_token'] ?? null;
        }

        return null;
    }

    private function curlPost(string $url, array $fields, string $cookieFile): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) throw new \Exception("Zerodha cURL error [{$url}]: {$err}");
        $data = json_decode($response, true);
        if (!$data) throw new \Exception("Zerodha: invalid JSON from [{$url}]: " . substr($response, 0, 300));
        return $data;
    }

    private function hasValidStoredToken(): bool
    {
        return $this->broker->is_token_valid
            && !empty($this->broker->access_token)
            && !empty($this->broker->token_expires_at)
            && Carbon::parse($this->broker->token_expires_at)->isFuture();
    }
}