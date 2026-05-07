<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\AngelApiInstrument;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;

/**
 * AngelPivotHelper
 *
 * Handles order placement for Angel One (SmartAPI) broker accounts.
 * Used by PlacePivotOrders command.
 *
 * ── CREDENTIALS ──────────────────────────────────────────────────────────────
 * All Angel credentials are stored in .env (NOT in broker_apis table).
 *
 * Add these to your .env file:
 * ─────────────────────────────
 *   ANGEL_CLIENT_CODE=R834343
 *   ANGEL_PASSWORD=Awesome@999
 *   ANGEL_PIN=1997
 *   ANGEL_API_KEY=1oYcjlsn
 *   ANGEL_API_SECRET=ae79f58d-8cc6-4813-ad4f-037fc7c8c2da
 *   ANGEL_TOTP_SECRET=M46VHZKUIVRYBWO3CBF4B4BGLM
 *   ANGEL_CLIENT_LOCAL_IP=192.168.1.31
 *   ANGEL_CLIENT_PUBLIC_IP=122.161.67.85
 *   ANGEL_MAC_ADDRESS=14-85-7F-92-D0-B0
 *
 * ── TOKEN LOOKUP (getInstrumentInfo) ─────────────────────────────────────────
 *
 * Zerodha and Angel share the same exchange_token for the same instrument:
 *
 *   zerodha_instruments.exchange_token  ==  angel_api_instruments.token
 *
 * Correct lookup chain:
 *   Step 1: zerodha_instruments WHERE instrument_token = $instrumentToken
 *           → get exchange_token (e.g. 52562)
 *   Step 2: angel_api_instruments WHERE token = '52562'
 *           → get Angel's own symbol_name + token + lotsize
 *
 * Verified by DB:
 *   zerodha: instrument_token=13455874, exchange_token=52562, trading_symbol=BANKNIFTY26MAR59200CE
 *   angel  : token=52562, symbol_name=BANKNIFTY30MAR2659200CE, lotsize=30
 *
 * IMPORTANT: We use angel_api_instruments.symbol_name (not toAngelSymbol()) for
 * the actual order tradingsymbol — Angel's own DB has the authoritative name.
 *
 * ── KEY DIFFERENCES vs Zerodha ───────────────────────────────────────────────
 * 1. SYMBOL FORMAT
 *    Zerodha : NIFTY2550518000CE  (YY + MM_number + DD + strike + CE/PE)
 *    Angel   : NIFTY25MAY18000CE  (YY + MON_text  + strike + CE/PE)
 *    → For order placement we use angel_api_instruments.symbol_name from DB.
 *    → toAngelSymbol() is only used as a last-resort fallback.
 *
 * 2. PRODUCT TYPE NAMES
 *    Zerodha : MIS | NRML
 *    Angel   : INTRADAY | CARRYFORWARD
 *
 * 3. ORDER PAYLOAD KEYS (all different — handled internally)
 *    transaction_type → transactiontype
 *    order_type       → ordertype
 *    product          → producttype
 *    validity         → duration
 *    quantity (int)   → quantity (STRING)
 *    [extra fields]   → variety, symboltoken, squareoff, stoploss
 *
 * 4. AUTH
 *    Zerodha: long-lived access_token via KiteConnect SDK.
 *    Angel  : JWT via TOTP login — generated fresh on each command run.
 */
class AngelPivotHelper
{
    // ── ENV-backed credentials ────────────────────────────────────────────────
    private string $clientCode;
    private string $password;
    private string $pin;
    private string $apiKey;
    private string $apiSecret;
    private string $totpSecret;
    private string $clientLocalIp;
    private string $clientPublicIp;
    private string $macAddress;

    private BrokerApi $broker;
    private string    $jwtToken;

    private const BASE_URL = 'https://apiconnect.angelone.in';

    private const MONTH_MAP = [
        '01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APR',
        '05' => 'MAY', '06' => 'JUN', '07' => 'JUL', '08' => 'AUG',
        '09' => 'SEP', '10' => 'OCT', '11' => 'NOV', '12' => 'DEC',
    ];

    /**
     * Cache keyed by Zerodha instrument_token (string).
     * Stores: [angel_symbol_name, angel_token, lot_size, tick_size]
     *
     * @var array<string, array{string, string, int, float}>
     */
    private array $instrumentCache = [];

    /** Headers shared across every Angel API request (except Authorization) */
    private array $baseHeaders = [];

    public function __construct(BrokerApi $broker)
    {
        $this->broker = $broker;

        // ── Load all credentials from .env ────────────────────────────────────
        $this->clientCode     = env('ANGEL_CLIENT_CODE',      '');
        $this->password       = env('ANGEL_PASSWORD',          '');
        $this->pin            = env('ANGEL_PIN',               '');
        $this->apiKey         = env('ANGEL_API_KEY',           '');
        $this->apiSecret      = env('ANGEL_API_SECRET',        '');
        $this->totpSecret     = env('ANGEL_TOTP_SECRET',       '');
        $this->clientLocalIp  = env('ANGEL_CLIENT_LOCAL_IP',  '192.168.1.1');
        $this->clientPublicIp = env('ANGEL_CLIENT_PUBLIC_IP', '1.1.1.1');
        $this->macAddress     = env('ANGEL_MAC_ADDRESS',       '00-00-00-00-00-00');

        // ── Throw early if required ENV keys are missing ───────────────────────
        $this->validateEnv();

        // ── Build static headers once ─────────────────────────────────────────
        $this->baseHeaders = [
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-PrivateKey: '     . $this->apiKey,
            'X-ClientLocalIP: '  . $this->clientLocalIp,
            'X-ClientPublicIP: ' . $this->clientPublicIp,
            'X-MACAddress: '     . $this->macAddress,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // ── Generate fresh JWT token (one login per command run) ──────────────
        $this->jwtToken = $this->generateFreshToken();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether a BrokerApi record is a valid Angel One account.
     * Credentials come from ENV — just check client_type + ENV is configured.
     */
    public static function isValid(BrokerApi $broker): bool
    {
        return $broker->client_type === 'AngelOne'
            && !empty(env('ANGEL_API_KEY'))
            && !empty(env('ANGEL_TOTP_SECRET'))
            && !empty(env('ANGEL_CLIENT_CODE'));
    }

    /**
     * Place an order (or chunked orders if over freeze limit) on Angel One.
     *
     * NOTE: $tradingSymbol is Zerodha format (from 30min_ohlc_data.trading_symbol).
     *       The correct Angel symbol_name and token are fetched from angel_api_instruments
     *       via the exchange_token lookup chain. toAngelSymbol() is a fallback only.
     *
     * @param  string   $tradingSymbol    Zerodha format e.g. NIFTY2631024650CE
     * @param  int      $instrumentToken  Zerodha instrument_token — key for exchange_token lookup
     * @param  string   $transactionType  BUY | SELL
     * @param  string   $orderType        LIMIT | MARKET
     * @param  string   $product          MIS | NRML  (mapped to INTRADAY|CARRYFORWARD internally)
     * @param  int      $lots             Number of lots (multiplied by lot_size internally)
     * @param  float    $orderPrice       Raw price (tick-rounded for LIMIT orders)
     * @param  int|null $freezeLimitLots  Max lots per single order; null = no chunking
     * @return array    ['order_ids'=>string[], 'lot_size'=>int, 'tick_size'=>float, 'total_qty'=>int]
     * @throws \Exception on Angel API failure
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
        [$angelSymbolName, $angelToken, $lotSize, $tickSize] = $this->getInstrumentInfo($tradingSymbol, $instrumentToken);

        $totalQty = $lots * $lotSize;

        if ($freezeLimitLots && $lots > $freezeLimitLots) {
            return $this->placeChunked(
                $angelSymbolName, $angelToken, $transactionType, $orderType, $product,
                $lots, $lotSize, $tickSize, $freezeLimitLots, $orderPrice
            );
        }

        $orderId = $this->sendOrder(
            $angelSymbolName, $angelToken, $transactionType,
            $orderType, $product, $totalQty, $orderPrice, $tickSize
        );

        return [
            'order_ids' => [$orderId],
            'lot_size'  => $lotSize,
            'tick_size' => $tickSize,
            'total_qty' => $totalQty,
        ];
    }

    /**
     * Get [angel_symbol_name, angel_token, lot_size, tick_size] for an instrument.
     *
     * LOOKUP CHAIN:
     * ─────────────
     * Step 1: zerodha_instruments WHERE instrument_token = $instrumentToken
     *         → get exchange_token (e.g. 52562 for BANKNIFTY26MAR59200CE)
     *
     * Step 2: angel_api_instruments WHERE token = exchange_token
     *         → get Angel's symbol_name (e.g. BANKNIFTY30MAR2659200CE) + token + lotsize
     *         angel_api_instruments.token == zerodha_instruments.exchange_token (confirmed by DB)
     *
     * Fallback: toAngelSymbol() conversion + symbol_name lookup.
     *           Used only when instrument_token is missing from zerodha_instruments.
     *
     * Result cached per instrument_token to avoid repeat DB hits within one run.
     *
     * @param  string $tradingSymbol   Zerodha format e.g. NIFTY2631024650CE
     * @param  int    $instrumentToken Zerodha instrument_token (NOT exchange_token)
     * @return array  [angel_symbol_name, angel_token, lot_size, tick_size]
     */
    public function getInstrumentInfo(string $tradingSymbol, int $instrumentToken): array
    {
        $cacheKey = (string) $instrumentToken;

        if (isset($this->instrumentCache[$cacheKey])) {
            return $this->instrumentCache[$cacheKey];
        }

        $angelRow = null;

        // ── Step 1: Zerodha instrument_token → exchange_token ─────────────────
        $zerodhaRow = ZerodhaInstrument::where('instrument_token', $instrumentToken)->first();

        if ($zerodhaRow) {
            $exchangeToken = (string) $zerodhaRow->exchange_token;

            // ── Step 2: exchange_token → Angel row ────────────────────────────
            // zerodha_instruments.exchange_token == angel_api_instruments.token
            // Verified by DB:
            //   zerodha exchange_token=52562 → angel token=52562 → BANKNIFTY30MAR2659200CE
            $angelRow = AngelApiInstrument::where('token', $exchangeToken)->first();

            if ($angelRow) {
                Log::info(
                    "AngelPivotHelper: found [{$angelRow->symbol_name}] (token={$angelRow->token}) " .
                    "via exchange_token={$exchangeToken} (instrument_token={$instrumentToken})"
                );
            } else {
                Log::warning(
                    "AngelPivotHelper: no angel_api_instruments row for exchange_token={$exchangeToken} " .
                    "(instrument_token={$instrumentToken}, trading_symbol={$tradingSymbol}). " .
                    "Run: php artisan angel_instrument:daily_update"
                );
            }
        } else {
            Log::warning(
                "AngelPivotHelper: instrument_token={$instrumentToken} not found in zerodha_instruments. " .
                "Falling back to symbol_name lookup for trading_symbol=[{$tradingSymbol}]."
            );
        }

        // ── Fallback: convert symbol and lookup by symbol_name ────────────────
        // Only reached when zerodha_instruments is missing the instrument_token.
        if (!$angelRow) {
            $angelSymbol = $this->toAngelSymbol($tradingSymbol);
            $angelRow    = AngelApiInstrument::where('symbol_name', $angelSymbol)->first();

            if ($angelRow) {
                Log::info(
                    "AngelPivotHelper: found [{$angelRow->symbol_name}] via fallback symbol_name match " .
                    "converted_symbol=[{$angelSymbol}] instrument_token=[{$instrumentToken}]"
                );
            } else {
                Log::error(
                    "AngelPivotHelper: instrument NOT found — trading_symbol=[{$tradingSymbol}] " .
                    "instrument_token=[{$instrumentToken}] converted_angel_symbol=[{$angelSymbol}]. " .
                    "Ensure both zerodha_instrument:insert and angel_instrument:daily_update ran today."
                );
            }
        }

        // ── Extract values (safe defaults if still not found) ─────────────────
        $angelSymbolName = $angelRow ? (string) $angelRow->symbol_name : $this->toAngelSymbol($tradingSymbol);
        $angelToken      = $angelRow ? (string) $angelRow->token        : '';
        $lotSize         = $angelRow ? (int)    $angelRow->lotsize       : 1;
        $tickSize        = 0.05; // Angel NFO options tick is always 0.05

        if ($lotSize <= 0) $lotSize = 1;

        $this->instrumentCache[$cacheKey] = [$angelSymbolName, $angelToken, $lotSize, $tickSize];
        return $this->instrumentCache[$cacheKey];
    }

    /**
     * Convert any Zerodha-format option symbol → Angel One format.
     * Used ONLY as a fallback when exchange_token lookup fails.
     *
     * FORMAT 1 — Monthly expiry (already Angel-compatible):
     *   BANKNIFTY26MAR59200CE  → already Angel format, uppercase and return.
     *
     * FORMAT 2 — Weekly expiry, SINGLE-digit month (Zerodha weekly):
     *   NIFTY2631024650CE  → YY(26) + M(3=March) + DD(10) + STRIKE(24650)
     *   → Strip DD, convert M → MON → NIFTY26MAR24650CE
     *
     * FORMAT 3 — Weekly/other, TWO-digit month + two-digit day:
     *   NIFTY2503054650CE  → YY(25) + MM(03) + DD(05) + STRIKE(4650)
     *   → Strip DD, convert MM → MON → NIFTY25MAR4650CE
     */
    public function toAngelSymbol(string $zerodhaSymbol): string
    {
        // FORMAT 1: Already has 3-letter month → already Angel format
        if (preg_match('/^([A-Z0-9&]+)(\d{2})(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)(\d+)(CE|PE)$/i', $zerodhaSymbol, $m)) {
            return strtoupper($zerodhaSymbol);
        }

        // FORMAT 2: Single-digit month (1-9) + 2-digit day (Zerodha weekly)
        if (preg_match('/^([A-Z0-9&]+?)(\d{2})([1-9])(\d{2})(\d+)(CE|PE)$/i', $zerodhaSymbol, $m)) {
            [, $sym, $yy, $m1, , $strike, $optType] = $m;
            $mm  = str_pad($m1, 2, '0', STR_PAD_LEFT);
            $mon = self::MONTH_MAP[$mm] ?? null;
            if ($mon) {
                return strtoupper("{$sym}{$yy}{$mon}{$strike}{$optType}");
            }
        }

        // FORMAT 3: Two-digit month + two-digit day
        if (preg_match('/^([A-Z0-9&]+?)(\d{2})(\d{2})(\d{2})(\d+)(CE|PE)$/i', $zerodhaSymbol, $m)) {
            [, $sym, $yy, $mm, , $strike, $optType] = $m;
            $mon = self::MONTH_MAP[$mm] ?? null;
            if ($mon) {
                return strtoupper("{$sym}{$yy}{$mon}{$strike}{$optType}");
            }
        }

        Log::warning("AngelPivotHelper: toAngelSymbol could not parse [{$zerodhaSymbol}], using as-is.");
        return strtoupper($zerodhaSymbol);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: chunking
    // ─────────────────────────────────────────────────────────────────────────

    private function placeChunked(
        string $angelSymbolName,
        string $angelToken,
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
                $angelSymbolName, $angelToken, $transactionType,
                $orderType, $product, $chunkQty, $orderPrice, $tickSize
            );
            $orderIds[] = $orderId;

            Log::info("AngelPivotHelper chunk {$chunkNum}/{$totalChunks} | {$angelSymbolName} qty={$chunkQty} order_id={$orderId}");

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

    // ─────────────────────────────────────────────────────────────────────────
    // Private: single Angel API order
    // ─────────────────────────────────────────────────────────────────────────

    private function sendOrder(
        string $angelSymbolName,
        string $angelToken,
        string $transactionType,
        string $orderType,
        string $product,
        int    $qty,
        float  $orderPrice,
        float  $tickSize
    ): string {
        // Fail fast with clear message if token is empty
        if (empty($angelToken)) {
            throw new \Exception(
                "AngelPivotHelper: cannot place order for [{$angelSymbolName}] — " .
                "angel symboltoken is empty. Instrument not found in angel_api_instruments. " .
                "Run: php artisan angel_instrument:daily_update"
            );
        }

        $roundedPrice = number_format(
            round($orderPrice / $tickSize) * $tickSize, 2, '.', ''
        );

        $payload = [
            'variety'         => 'NORMAL',
            'tradingsymbol'   => $angelSymbolName,                 // from angel_api_instruments.symbol_name
            'symboltoken'     => $angelToken,                      // from angel_api_instruments.token
            'transactiontype' => $transactionType,                 // BUY | SELL
            'exchange'        => 'NFO',
            'ordertype'       => $this->mapOrderType($orderType),  // LIMIT | MARKET
            'producttype'     => $this->mapProduct($product),      // INTRADAY | CARRYFORWARD
            'duration'        => 'DAY',
            'quantity'        => (string) $qty,                    // Angel requires STRING not int
            'price'           => $orderType === 'LIMIT' ? $roundedPrice : '0',
            'squareoff'       => '0',
            'stoploss'        => '0',
        ];

        $response = $this->callApi('/rest/secure/angelbroking/order/v1/placeOrder', $payload);

        if (empty($response['data']['orderid'])) {
            $msg = $response['message'] ?? json_encode($response);
            throw new \Exception("Angel placeOrder failed for {$angelSymbolName}: {$msg}");
        }

        return $response['data']['orderid'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: JWT login via TOTP
    // ─────────────────────────────────────────────────────────────────────────

    private function generateFreshToken(): string
    {
        require_once app_path('Libraries/vendor/autoload.php');
        $totp      = \OTPHP\TOTP::create($this->totpSecret);
        $totpToken = $totp->now();

        $payload = [
            'clientcode' => $this->clientCode,
            'password'   => $this->pin,
            'totp'       => $totpToken,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => self::BASE_URL . '/rest/auth/angelbroking/user/v1/loginByPassword',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $this->baseHeaders,
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new \Exception("AngelPivotHelper: TOTP login cURL error: {$err}");
        }

        $data = json_decode($response, true);

        if (empty($data['data']['jwtToken'])) {
            throw new \Exception(
                "AngelPivotHelper: Login failed for [{$this->clientCode}]: " . json_encode($data)
            );
        }

        Log::info("AngelPivotHelper: JWT generated successfully for [{$this->clientCode}]");

        return $data['data']['jwtToken'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: generic POST helper
    // ─────────────────────────────────────────────────────────────────────────

    private function callApi(string $endpoint, array $payload): array
    {
        $headers   = $this->baseHeaders;
        $headers[] = 'Authorization: Bearer ' . $this->jwtToken;

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
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new \Exception("AngelPivotHelper cURL error [{$endpoint}]: {$err}");
        }

        if ($httpCode !== 200) {
            throw new \Exception(
                "AngelPivotHelper HTTP {$httpCode} [{$endpoint}]: " . substr($response, 0, 400)
            );
        }

        $data = json_decode($response, true);

        if (!$data) {
            throw new \Exception("AngelPivotHelper: invalid JSON from [{$endpoint}]");
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: validation + mapping
    // ─────────────────────────────────────────────────────────────────────────

    private function validateEnv(): void
    {
        $required = [
            'ANGEL_CLIENT_CODE' => $this->clientCode,
            'ANGEL_PIN'         => $this->pin,
            'ANGEL_API_KEY'     => $this->apiKey,
            'ANGEL_TOTP_SECRET' => $this->totpSecret,
        ];

        $missing = array_keys(array_filter($required, fn($v) => empty($v)));

        if (!empty($missing)) {
            throw new \Exception(
                'AngelPivotHelper: Missing required .env keys: ' . implode(', ', $missing)
            );
        }
    }

    private function mapProduct(string $product): string
    {
        return match(strtoupper($product)) {
            'MIS'  => 'INTRADAY',
            'NRML' => 'CARRYFORWARD',
            'CNC'  => 'DELIVERY',
            default => 'INTRADAY',
        };
    }

    private function mapOrderType(string $orderType): string
    {
        return match(strtoupper($orderType)) {
            'LIMIT'  => 'LIMIT',
            'MARKET' => 'MARKET',
            'SL'     => 'STOPLOSS_LIMIT',
            'SL-M'   => 'STOPLOSS_MARKET',
            default  => 'MARKET',
        };
    }
}