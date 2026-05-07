<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

require app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

trait AngelApi15MinHelper
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
        return Cache::remember('ANGEL_API_TOKEN_15MIN', 72000, function () {
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

                Log::error('Angel API (15min): Invalid token response', ['response' => $response]);
                return null;

            } catch (\Exception $e) {
                Log::error('Angel API (15min): Token generation failed', ['error' => $e->getMessage()]);
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
     * Fetch ALL 15-minute historical candle data and OI data for a given symbol token
     */
    public function fetch15MinHistoricalData(string $symbolToken, string $date, string $exchange = 'NFO'): array
    {
        try {
            $jwtToken = $this->getAccessToken();
            if (!$jwtToken) {
                Log::error("Failed to get JWT token for 15-min historical data");
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

            // Debug: Log candle response structure
            if (empty($candleResponse['data'])) {
                Log::warning("Empty candle data for token: {$symbolToken}", [
                    'response' => $candleResponse
                ]);
            }

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

            // Debug: Log OI response structure
            if (empty($oiResponse['data'])) {
                Log::warning("Empty OI data for token: {$symbolToken}", [
                    'response' => $oiResponse
                ]);
            } else {
                // Log first OI record to see structure
                Log::info("OI Data Sample for {$symbolToken}", [
                    'first_record' => $oiResponse['data'][0] ?? 'no data',
                    'total_records' => count($oiResponse['data'])
                ]);
            }

            return $this->mapAll15MinData(
                $candleResponse['data'] ?? [],
                $oiResponse['data'] ?? [],
                $symbolToken
            );

        } catch (\Exception $e) {
            Log::error("Failed to fetch 15-min data for token: {$symbolToken}", [
                'error' => $e->getMessage(),
                'date' => $date
            ]);
            return [];
        }
    }

    /**
     * Map ALL candle and OI data into array of 15-min records
     * FIXED: Multiple ways to match OI data with candle data
     */
    private function mapAll15MinData(array $candleData, array $oiData, string $symbolToken): array
    {
        if (empty($candleData)) {
            return [];
        }

        // Try multiple methods to create OI lookup
        $oiLookup = [];

        // Method 1: By timestamp field (if exists)
        foreach ($oiData as $index => $oi) {
            if (isset($oi['timestamp'])) {
                $oiLookup[$oi['timestamp']] = $oi['oi'] ?? null;
            }
        }

        // Method 2: By array index (if OI array structure is [timestamp, oi])
        if (empty($oiLookup)) {
            foreach ($oiData as $oi) {
                if (is_array($oi) && isset($oi[0], $oi[1])) {
                    $oiLookup[$oi[0]] = $oi[1]; // timestamp => oi
                }
            }
        }

        // Method 3: Match by position if timestamps match
        if (empty($oiLookup) && count($oiData) === count($candleData)) {
            Log::info("Using position-based OI matching for token: {$symbolToken}");
        }

        $mappedData = [];

        // Process ALL candles
        foreach ($candleData as $index => $candle) {
            // Candle structure: [timestamp, open, high, low, close, volume]
            $timestamp = $candle[0] ?? null;
            
            if (!$timestamp) {
                continue;
            }

            // Try to get OI value
            $oiValue = null;
            
            // Try lookup by timestamp first
            if (isset($oiLookup[$timestamp])) {
                $oiValue = $oiLookup[$timestamp];
            }
            // Fallback: use position-based matching if counts match
            elseif (count($oiData) === count($candleData) && isset($oiData[$index])) {
                $oiRecord = $oiData[$index];
                if (is_array($oiRecord)) {
                    // Structure might be [timestamp, oi] or ['timestamp' => ..., 'oi' => ...]
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

        // Log summary for debugging
        $oiCount = count(array_filter(array_column($mappedData, 'oi')));
        Log::info("Mapped data for token {$symbolToken}", [
            'total_candles' => count($mappedData),
            'candles_with_oi' => $oiCount,
            'oi_data_received' => count($oiData)
        ]);

        return $mappedData;
    }
}