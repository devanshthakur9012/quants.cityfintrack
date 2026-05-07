<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

require_once app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

trait IndependentAngelApiTrait
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

    private function generateTotpToken(): string
    {
        $totp = TOTP::create($this->totp_secret);
        return $totp->now();
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('FUTURES_SIGNAL_API_TOKEN', 72000, function () {
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

                Log::error('Futures Signal API: Invalid token response', ['response' => $response]);
                return null;

            } catch (\Exception $e) {
                Log::error('Futures Signal API: Token generation failed', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

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

    private function getAuthorizedHeaders(string $token): array
    {
        return array_merge($this->getAuthHeaders(), [
            'Authorization: Bearer ' . $token
        ]);
    }

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
     * Get futures contract from angel_api_instruments (NO DEPENDENCY on instrument_chains)
     */
    protected function getFutureContractDirect(string $underlying): ?object
    {
        $instrumentType = in_array($underlying, ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY']) 
            ? 'FUTIDX' 
            : 'FUTSTK';

        return DB::table('angel_api_instruments')
            ->where('name', $underlying)
            ->where('instrumenttype', $instrumentType)
            ->where('exch_seg', 'NFO')
            ->orderByRaw("STR_TO_DATE(expiry_raw, '%d%b%Y') ASC")
            ->first();
    }

    /**
     * Fetch ALL 15-minute candles for a given token and date
     */
    protected function fetchFutures15MinData(string $symbolToken, string $date, string $exchange = 'NFO'): array
    {
        try {
            $jwtToken = $this->getAccessToken();
            if (!$jwtToken) {
                Log::error("Failed to get JWT token");
                return [];
            }

            $fromDate = $date . " 09:15";
            $toDate   = $date . " 15:30";
            
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

            return $this->mapFuturesData(
                $candleResponse['data'] ?? [],
                $oiResponse['data'] ?? [],
                $symbolToken
            );

        } catch (\Exception $e) {
            Log::error("Failed to fetch futures 15-min data for token: {$symbolToken}", [
                'error' => $e->getMessage(),
                'date' => $date
            ]);
            return [];
        }
    }

    /**
     * Map candle and OI data
     */
    private function mapFuturesData(array $candleData, array $oiData, string $symbolToken): array
    {
        if (empty($candleData)) {
            return [];
        }

        // Build OI lookup
        $oiLookup = [];
        
        foreach ($oiData as $oi) {
            if (isset($oi['timestamp'])) {
                $oiLookup[$oi['timestamp']] = $oi['oi'] ?? null;
            } elseif (is_array($oi) && isset($oi[0], $oi[1])) {
                $oiLookup[$oi[0]] = $oi[1];
            }
        }

        $mappedData = [];

        foreach ($candleData as $index => $candle) {
            $timestamp = $candle[0] ?? null;
            
            if (!$timestamp) {
                continue;
            }

            $oiValue = $oiLookup[$timestamp] ?? null;
            
            // Fallback: position-based matching
            if ($oiValue === null && count($oiData) === count($candleData) && isset($oiData[$index])) {
                $oiRecord = $oiData[$index];
                if (is_array($oiRecord)) {
                    $oiValue = $oiRecord['oi'] ?? $oiRecord[1] ?? null;
                }
            }

            $mappedData[] = [
                'candle_time' => Carbon::parse($timestamp)->format('Y-m-d H:i:s'),
                'open' => $candle[1] ?? null,
                'high' => $candle[2] ?? null,
                'low' => $candle[3] ?? null,
                'close' => $candle[4] ?? null,
                'volume' => $candle[5] ?? null,
                'oi' => $oiValue,
            ];
        }

        return $mappedData;
    }
}