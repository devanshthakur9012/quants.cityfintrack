<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

require app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;

trait ZerodhaApiHelper
{
    // Zerodha API Credentials
    private $zerodha_api_key = '99oiazl0azvr3y6g';
    private $zerodha_api_secret = 'cjv7jjg1o2zws3zrhuk5ubfq11mie7y0';
    private $zerodha_login_id = 'XXQ759';
    private $zerodha_password = 'city@123';
    private $zerodha_totp_secret = '356FIW7RUHLNHDOEXUMJOFRRYWDOSZZP';
    
    private $max_retries = 3;
    private $retry_delay = 2; // seconds

    /**
     * Generate Zerodha TOTP token
     */
    private function generateZerodhaTotpToken(): string
    {
        try {
            $totp = TOTP::create($this->zerodha_totp_secret);
            return $totp->now();
        } catch (\Exception $e) {
            Log::error('Zerodha TOTP generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get and cache Zerodha access token with retry mechanism
     */
    private function getZerodhaAccessToken(): ?string
    {
        return Cache::remember('ZERODHA_ACCESS_TOKEN', 21600, function () { // 6 hours cache
            $attempt = 0;
            
            while ($attempt < $this->max_retries) {
                try {
                    // Step 1: Generate request token via login
                    $requestToken = $this->getZerodhaRequestToken();
                    
                    if (!$requestToken) {
                        throw new \Exception("Failed to get request token");
                    }

                    // Step 2: Generate session and get access token
                    $response = Http::post('https://api.kite.trade/session/token', [
                        'api_key' => $this->zerodha_api_key,
                        'request_token' => $requestToken,
                        'checksum' => hash('sha256', $this->zerodha_api_key . $requestToken . $this->zerodha_api_secret)
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['data']['access_token'])) {
                            Log::info('Zerodha access token generated successfully');
                            return $data['data']['access_token'];
                        }
                    }

                    throw new \Exception("Invalid token response: " . $response->body());

                } catch (\Exception $e) {
                    $attempt++;
                    Log::warning("Zerodha token attempt {$attempt} failed", ['error' => $e->getMessage()]);
                    
                    if ($attempt >= $this->max_retries) {
                        Log::error('Zerodha token generation failed after max retries');
                        return null;
                    }
                    
                    sleep($this->retry_delay);
                }
            }
            
            return null;
        });
    }

    /**
     * Simulate login to get request token (Note: Zerodha requires manual login)
     * For automation, you need to implement Selenium/browser automation
     */
    private function getZerodhaRequestToken(): ?string
    {
        // IMPORTANT: Zerodha requires manual login for security
        // You need to implement one of these approaches:
        // 1. Use Selenium/Puppeteer for automated browser login
        // 2. Manually generate request token daily via Kite Connect login
        // 3. Use KiteConnect session management
        
        // For now, check if request token is stored
        $requestToken = Cache::get('ZERODHA_REQUEST_TOKEN');
        
        if (!$requestToken) {
            Log::error('Zerodha request token not found. Manual login required.');
            // In production, implement browser automation here
            return null;
        }
        
        return $requestToken;
    }

    /**
     * Make API call with retry and error handling
     */
    private function makeZerodhaApiCall(string $endpoint, array $params = [], string $method = 'GET'): ?array
    {
        $attempt = 0;
        
        while ($attempt < $this->max_retries) {
            try {
                $accessToken = $this->getZerodhaAccessToken();
                
                if (!$accessToken) {
                    throw new \Exception("No access token available");
                }

                $headers = [
                    'X-Kite-Version' => '3',
                    'Authorization' => 'token ' . $this->zerodha_api_key . ':' . $accessToken,
                ];

                $response = $method === 'GET' 
                    ? Http::withHeaders($headers)->get($endpoint, $params)
                    : Http::withHeaders($headers)->post($endpoint, $params);

                if ($response->successful()) {
                    return $response->json();
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? 5;
                    Log::warning("Rate limited. Waiting {$retryAfter} seconds");
                    sleep($retryAfter);
                    continue;
                }

                throw new \Exception("API call failed: " . $response->body());

            } catch (\Exception $e) {
                $attempt++;
                Log::warning("API call attempt {$attempt} failed", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);
                
                if ($attempt >= $this->max_retries) {
                    Log::error('API call failed after max retries', ['endpoint' => $endpoint]);
                    return null;
                }
                
                sleep($this->retry_delay * $attempt); // Exponential backoff
            }
        }
        
        return null;
    }

    /**
     * Get current LTP with validation
     */
    protected function getZerodhaLTP(array $instrumentTokens, string $exchange = 'NFO'): array
    {
        try {
            // Zerodha allows max 500 instruments per call
            $chunks = array_chunk($instrumentTokens, 500);
            $allData = [];

            foreach ($chunks as $chunk) {
                $instruments = array_map(fn($token) => "{$exchange}:{$token}", $chunk);
                $queryString = 'i=' . implode('&i=', $instruments);
                
                $response = $this->makeZerodhaApiCall(
                    "https://api.kite.trade/quote/ltp?{$queryString}"
                );

                if ($response && isset($response['data'])) {
                    $allData = array_merge($allData, $response['data']);
                }
            }

            return $allData;

        } catch (\Exception $e) {
            Log::error('Failed to fetch Zerodha LTP', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get OHLC data with validation
     */
    protected function getZerodhaOHLC(array $instrumentTokens, string $exchange = 'NFO'): array
    {
        try {
            $chunks = array_chunk($instrumentTokens, 500);
            $allData = [];

            foreach ($chunks as $chunk) {
                $instruments = array_map(fn($token) => "{$exchange}:{$token}", $chunk);
                $queryString = 'i=' . implode('&i=', $instruments);
                
                $response = $this->makeZerodhaApiCall(
                    "https://api.kite.trade/quote/ohlc?{$queryString}"
                );

                if ($response && isset($response['data'])) {
                    $allData = array_merge($allData, $response['data']);
                }
            }

            return $allData;

        } catch (\Exception $e) {
            Log::error('Failed to fetch Zerodha OHLC', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get historical candle data with retry
     */
    protected function getZerodhaHistoricalData(
        string $instrumentToken,
        string $fromDate,
        string $toDate,
        string $interval = 'day',
        string $exchange = 'NFO'
    ): array {
        try {
            $from = \Carbon\Carbon::parse($fromDate)->format('Y-m-d H:i:s');
            $to = \Carbon\Carbon::parse($toDate)->format('Y-m-d H:i:s');

            $response = $this->makeZerodhaApiCall(
                "https://api.kite.trade/instruments/historical/{$instrumentToken}/{$interval}",
                [
                    'from' => $from,
                    'to' => $to,
                    'continuous' => 0,
                    'oi' => 1
                ]
            );

            if ($response && isset($response['data']['candles'])) {
                return $this->validateAndFormatCandles($response['data']['candles']);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to fetch historical data', [
                'token' => $instrumentToken,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Validate and format candle data to ensure consistency
     */
    private function validateAndFormatCandles(array $candles): array
    {
        $formatted = [];

        foreach ($candles as $candle) {
            // Ensure all required fields exist
            if (count($candle) >= 6) {
                $formatted[] = [
                    'timestamp' => $candle[0] ?? null,
                    'open' => (float) ($candle[1] ?? 0),
                    'high' => (float) ($candle[2] ?? 0),
                    'low' => (float) ($candle[3] ?? 0),
                    'close' => (float) ($candle[4] ?? 0),
                    'volume' => (int) ($candle[5] ?? 0),
                    'oi' => isset($candle[6]) ? (int) $candle[6] : null,
                ];
            }
        }

        return $formatted;
    }

    /**
     * Get future contract from Zerodha instruments
     */
    protected function getZerodhaFutureContract(string $underlying): ?object
    {
        $instrumentType = in_array($underlying, ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY']) 
            ? 'FUT' 
            : 'FUT';

        return \DB::table('zerodha_instruments')
            ->where('name', $underlying)
            ->where('instrument_type', $instrumentType)
            ->where('exchange', 'NFO')
            ->whereNotNull('expiry')
            ->where('expiry', '>', now())
            ->orderBy('expiry', 'ASC')
            ->first();
    }

    /**
     * Get option contracts for strike
     */
    protected function getZerodhaOptionsForStrike(string $underlying, float $strike): array
    {
        $strike = (float) $strike;

        $instruments = \DB::table('zerodha_instruments')
            ->where('name', $underlying)
            ->where('instrument_type', 'OPT')
            ->where('exchange', 'NFO')
            ->where('strike', $strike)
            ->whereNotNull('expiry')
            ->where('expiry', '>', now())
            ->orderBy('expiry', 'ASC')
            ->get();

        $options = ['CE' => null, 'PE' => null];

        foreach ($instruments as $instrument) {
            $symbol = $instrument->trading_symbol ?? '';
            if (str_contains($symbol, 'CE')) {
                $options['CE'] = $instrument;
            } elseif (str_contains($symbol, 'PE')) {
                $options['PE'] = $instrument;
            }

            if ($options['CE'] && $options['PE']) {
                break;
            }
        }

        return $options;
    }

    /**
     * Calculate strikes with validation
     */
    protected function calculateStrikesZerodha(float $stepValue, float $futurePrice, int $strikeCount = 7): array
    {
        $strikeGap = $stepValue ?? 50;
        $atm = round($futurePrice / $strikeGap) * $strikeGap;

        $strikes = [];
        $midPoint = floor($strikeCount / 2);

        for ($i = 0; $i < $strikeCount; $i++) {
            $offset = $i - $midPoint;
            $strikes[$i] = $atm + ($offset * $strikeGap);
        }

        return $strikes;
    }
}