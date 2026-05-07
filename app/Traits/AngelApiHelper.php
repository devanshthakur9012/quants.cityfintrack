<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

require app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

trait AngelApiHelper
{
    // Angel API Credentials
    private $accountUserName = 'R834343';
    private $accountPassword = 'Awesome@999';
    private $totp_secret = 'M46VHZKUIVRYBWO3CBF4B4BGLM';
    private $apiKey = '1oYcjlsn';
    private $pin = '1997';
    private $apiSecret = 'ae79f58d-8cc6-4813-ad4f-037fc7c8c2da';
    private $clientLocalIp = '192.168.1.31';
    private $clientPublicIp = '122.161.67.85';
    private $macAddress = '14-85-7F-92-D0-B0';

    /**
     * Generate TOTP token
     */
    private function generateTotpToken(): string
    {
        $totp = TOTP::create($this->totp_secret);
        return $totp->now();
    }

    /**
     * Generate and cache Angel API access token
     */
    private function getAccessToken(): ?string
    {
        return Cache::remember('ANGEL_API_TOKEN', 72000, function () {
            try {
                $payload = [
                    "clientcode" => $this->accountUserName,
                    "password" => $this->pin,
                    "totp" => $this->generateTotpToken(),
                ];

                $response = $this->curlPost(
                    'https://apiconnect.angelbroking.com/rest/auth/angelbroking/user/v1/loginByPassword',
                    $payload,
                    $this->getAuthHeaders()
                );

                if (isset($response['data']['jwtToken'])) {
                    return $response['data']['jwtToken'];
                }

                Log::error('Angel API: Invalid token response', ['response' => $response]);
                return null;

            } catch (\Exception $e) {
                Log::error('Angel API: Token generation failed', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get auth headers without token
     */
    private function getAuthHeaders(): array
    {
        return [
            'X-UserType: USER',
            'X-SourceID: WEB',
            'X-PrivateKey: ' . $this->apiKey,
            'X-ClientLocalIP: ' . $this->clientLocalIp,
            'X-ClientPublicIP: ' . $this->clientPublicIp,
            'X-MACAddress: ' . $this->macAddress,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }

    /**
     * Get headers with authorization token
     */
    private function getAuthorizedHeaders(string $token): array
    {
        return array_merge($this->getAuthHeaders(), [
            'Authorization: Bearer ' . $token
        ]);
    }

    /**
     * Execute CURL POST request
     */
    private function curlPost(string $url, array $payload, array $headers): array
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new \Exception("CURL Error: {$error}");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Get future contract for underlying symbol
     */
    protected function getFutureContract(string $underlying): ?object
    {
        $instrumentType = in_array($underlying, ['NIFTY', 'BANKNIFTY']) ? 'FUTIDX' : 'FUTSTK';

        return DB::table('angel_api_instruments')
            ->where('name', $underlying)
            ->where('instrumenttype', $instrumentType)
            ->where('exch_seg', 'NFO')
            ->orderByRaw("STR_TO_DATE(expiry_raw, '%d%b%Y') ASC")
            ->first();
    }

    /**
     * Get current price from Angel API
     */
    protected function getCurrentPrice(string $token): ?float
    {
        try {
            $jwtToken = $this->getAccessToken();
            if (!$jwtToken) {
                return null;
            }

            $payload = [
                "mode" => "FULL",
                "exchangeTokens" => [
                    "NSE" => [$token]
                ]
            ];

            $response = $this->curlPost(
                'https://apiconnect.angelbroking.com/rest/secure/angelbroking/market/v1/quote/',
                $payload,
                $this->getAuthorizedHeaders($jwtToken)
            );

            if (isset($response['status']) && $response['status'] === true) {
                return (float) ($response['data']['fetched'][0]['ltp'] ?? null);
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Failed to fetch price for token: {$token}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Calculate strike prices around ATM
     */
    protected function calculateStrikes(float $stepValue, float $futurePrice): array
    {
        $strikeGap = $stepValue ?? 50;
        $atm = round($futurePrice / $strikeGap) * $strikeGap;

        return [
            0 => $atm - (3 * $strikeGap),
            1 => $atm - (2 * $strikeGap),
            2 => $atm - $strikeGap,
            3 => $atm, // ATM
            4 => $atm + $strikeGap,
            5 => $atm + (2 * $strikeGap),
            6 => $atm + (3 * $strikeGap),
        ];
    }

    /**
     * Get CE and PE options for a specific strike
     */
    protected function getOptionsForStrike(string $underlying, float $strike): array
    {
        $instrumentType = in_array($underlying, ['NIFTY', 'BANKNIFTY']) ? 'OPTIDX' : 'OPTSTK';
        $formattedStrike = number_format($strike * 100, 6, '.', '');

        $instruments = DB::table('angel_api_instruments')
            ->where('name', $underlying)
            ->where('instrumenttype', $instrumentType)
            ->where('exch_seg', 'NFO')
            ->where('strike', $formattedStrike)
            ->orderByRaw("STR_TO_DATE(expiry_raw, '%d%b%Y') ASC")
            ->get();

        $options = ['CE' => null, 'PE' => null];

        foreach ($instruments as $instrument) {
            $symbol = $instrument->symbol_name ?? '';
            if (str_ends_with($symbol, 'CE')) {
                $options['CE'] = $instrument;
            } elseif (str_ends_with($symbol, 'PE')) {
                $options['PE'] = $instrument;
            }

            if ($options['CE'] && $options['PE']) {
                break;
            }
        }

        return $options;
    }

    /**
     * Get bulk LTP data for multiple tokens
     */
    public function getBulkLtpData(array $symbolTokens, string $exchange = 'NFO'): array
    {
        try {
            $jwtToken = $this->getAccessToken();
            if (!$jwtToken) {
                Log::error("Failed to get JWT token for bulk LTP");
                return [];
            }

            $payload = [
                "mode" => "LTP",
                "exchangeTokens" => [$exchange => $symbolTokens]
            ];

            $response = $this->curlPost(
                'https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/',
                $payload,
                $this->getAuthorizedHeaders($jwtToken)
            );

            if (!isset($response['status']) || !$response['status']) {
                Log::error("Bulk LTP API returned error", ['response' => $response]);
                return [];
            }

            if (!empty($response['data']['unfetched'])) {
                Log::warning("Some tokens could not be fetched", [
                    'unfetched' => $response['data']['unfetched']
                ]);
            }

            return $response['data']['fetched'] ?? [];

        } catch (\Exception $e) {
            Log::error("Failed to fetch bulk LTP data", [
                'error' => $e->getMessage(),
                'tokens' => $symbolTokens
            ]);
            return [];
        }
    }

    /**
     * Fetch historical candle data and OI data for a given symbol token
     */
    public function fetchHistoricalAndOI(string $symbolToken, string $date, string $exchange = 'NFO'): array
    {
        try {
            $jwtToken = $this->getAccessToken();
            if (!$jwtToken) {
                Log::error("Failed to get JWT token for historical data");
                return [];
            }

            $fromDate = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d') . " 09:15";
            $toDate = $date . " 15:30";

            // Fetch candle data
            $candlePayload = [
                "exchange" => $exchange,
                "symboltoken" => $symbolToken,
                "interval" => "FIFTEEN_MINUTE",
                "fromdate" => $fromDate,
                "todate" => $toDate,
            ];

            $candleResponse = $this->curlPost(
                'https://apiconnect.angelone.in/rest/secure/angelbroking/historical/v1/getCandleData',
                $candlePayload,
                $this->getAuthorizedHeaders($jwtToken)
            );

            // Fetch OI data
            $oiPayload = [
                "exchange" => $exchange,
                "symboltoken" => $symbolToken,
                "interval" => "FIFTEEN_MINUTE",
                "fromdate" => $fromDate,
                "todate" => $toDate,
            ];

            $oiResponse = $this->curlPost(
                'https://apiconnect.angelone.in/rest/secure/angelbroking/historical/v1/getOIData',
                $oiPayload,
                $this->getAuthorizedHeaders($jwtToken)
            );

            return $this->mapHistoricalData(
                $candleResponse['data'] ?? [],
                $oiResponse['data'] ?? []
            );

        } catch (\Exception $e) {
            Log::error("Failed to fetch historical data for token: {$symbolToken}", [
                'error' => $e->getMessage(),
                'date' => $date
            ]);
            return [];
        }
    }

    /**
     * Map candle and OI data into a single array
     */
    private function mapHistoricalData(array $candleData, array $oiData): array
    {
        if (empty($candleData)) {
            return [];
        }

        $latestCandle = end($candleData);
        $latestOI = end($oiData);

        return [
            'open' => $latestCandle[1] ?? null,
            'high' => $latestCandle[2] ?? null,
            'low' => $latestCandle[3] ?? null,
            'close' => $latestCandle[4] ?? null,
            'volume' => $latestCandle[5] ?? null,
            'oi' => $latestOI['oi'] ?? null,
        ];
    }
}