<?php

namespace App\Helpers;
require app_path('Libraries/vendor/autoload.php');
use KiteConnect\KiteConnect;
use OTPHP\TOTP;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;

class KiteConnectCls {
    private $accountUserName;
    private $accountPassword;
    private $totpSecret;
    private $apiKey;
    private $apiSecret;
    private $httpClient;
    private $cookieJar;
    private $cacheKey;
    
    public function __construct(array $params) {
        $this->accountUserName = $params['accountUserName'];
        $this->accountPassword = $params['accountPassword'];
        $this->totpSecret = $params['totpSecret'];
        $this->apiKey = $params['apiKey'];
        $this->apiSecret = $params['apiSecret'];
        
        // Setup token storage
        $this->cacheKey = 'KITE_TOKEN_' . $this->accountUserName;
        
        // Initialize HTTP client with cookie jar for session management
        $this->cookieJar = new CookieJar();
        $this->httpClient = new Client([
            'cookies' => $this->cookieJar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ],
            'timeout' => 30,
            'verify' => false, // Set to true in production
        ]);
    }
    
    /**
     * Generate TOTP token using the secret
     */
    public function getTotpToken(): string {
        try {
            $totp = TOTP::create($this->totpSecret);
            return $totp->now();
        } catch (\Exception $e) {
            Log::error("TOTP generation failed", [
                'username' => $this->accountUserName,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("TOTP generation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Store token in database with cache layer
     */
    private function storeToken(array $tokenData): void {
        try {
            // Store in database (primary storage)
            \App\Models\KiteToken::updateOrCreate(
                ['username' => $this->accountUserName],
                [
                    'access_token' => $tokenData['access_token'],
                    'expires_at' => now()->addHours(24),
                    'user_data' => $tokenData,
                    'updated_at' => now()
                ]
            );
            
            // Also cache for faster access (24 hours)
            \Cache::put($this->cacheKey, $tokenData, now()->addHours(24));
            
            Log::info("Token stored successfully", [
                'username' => $this->accountUserName,
                'expires_at' => now()->addHours(24)->toDateTimeString()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to store token", [
                'username' => $this->accountUserName,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Token storage failed: " . $e->getMessage());
        }
    }
    
    /**
     * Retrieve token from cache first, then database
     */
    private function retrieveStoredToken(): ?array {
        try {
            // Try cache first (fastest)
            $cachedToken = \Cache::get($this->cacheKey);
            if ($cachedToken && $this->isTokenValid($cachedToken)) {
                Log::info("Token retrieved from cache", ['username' => $this->accountUserName]);
                return $cachedToken;
            }
            
            // Try database
            $tokenRecord = \App\Models\KiteToken::where('username', $this->accountUserName)
                ->where('expires_at', '>', now())
                ->first();
            
            if ($tokenRecord && $tokenRecord->user_data) {
                $tokenData = $tokenRecord->user_data;
                
                if ($this->isTokenValid($tokenData)) {
                    // Restore to cache for next time
                    \Cache::put($this->cacheKey, $tokenData, $tokenRecord->expires_at);
                    
                    Log::info("Token retrieved from database", ['username' => $this->accountUserName]);
                    return $tokenData;
                }
            }
            
            Log::info("No valid stored token found", ['username' => $this->accountUserName]);
            return null;
            
        } catch (\Exception $e) {
            Log::error("Failed to retrieve token", [
                'username' => $this->accountUserName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Check if token is valid (not expired and has required fields)
     */
    private function isTokenValid(array $tokenData): bool {
        if (!isset($tokenData['access_token']) || empty($tokenData['access_token'])) {
            return false;
        }
        
        if (!isset($tokenData['expires_at'])) {
            return false;
        }
        
        // Check if token is expired (with 30 minute buffer before actual expiry)
        $expiryTime = strtotime($tokenData['expires_at']);
        $bufferTime = 30 * 60; // 30 minutes buffer
        
        return (time() + $bufferTime) < $expiryTime;
    }
    
    /**
     * Clear stored tokens from database and cache
     */
    public function clearStoredTokens(): void {
        try {
            // Clear from cache
            \Cache::forget($this->cacheKey);
            
            // Clear from database
            \App\Models\KiteToken::where('username', $this->accountUserName)->delete();
            
            Log::info("All stored tokens cleared", ['username' => $this->accountUserName]);
            
        } catch (\Exception $e) {
            Log::error("Failed to clear tokens", [
                'username' => $this->accountUserName,
                'error' => $e->getMessage()
            ]);
        }
    }
    public function generateAccessToken(): string {
        try {
            // Step 1: Get the login page to initialize session
            $loginUrl = "https://kite.trade/connect/login?api_key=" . $this->apiKey;
            
            Log::info("Initiating Kite login", ['username' => $this->accountUserName]);
            
            $response = $this->httpClient->get($loginUrl);
            
            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Failed to access login page");
            }
            
            // Step 2: Submit login credentials
            $loginResponse = $this->httpClient->post("https://kite.zerodha.com/api/login", [
                'form_params' => [
                    'user_id' => $this->accountUserName,
                    'password' => $this->accountPassword
                ]
            ]);
            
            if ($loginResponse->getStatusCode() !== 200) {
                throw new \Exception("Login request failed with status: " . $loginResponse->getStatusCode());
            }
            
            $loginData = json_decode($loginResponse->getBody()->getContents(), true);
            
            if (!isset($loginData['data']['request_id'])) {
                throw new \Exception("Login failed - no request_id received. Response: " . json_encode($loginData));
            }
            
            $requestId = $loginData['data']['request_id'];
            
            Log::info("Login successful, proceeding with 2FA", [
                'username' => $this->accountUserName,
                'request_id' => $requestId
            ]);
            
            // Step 3: Submit TOTP for 2FA
            $totpToken = $this->getTotpToken();
            
            $twoFaResponse = $this->httpClient->post("https://kite.zerodha.com/api/twofa", [
                'form_params' => [
                    'user_id' => $this->accountUserName,
                    'request_id' => $requestId,
                    'twofa_value' => $totpToken
                ]
            ]);
            
            if ($twoFaResponse->getStatusCode() !== 200) {
                throw new \Exception("2FA request failed with status: " . $twoFaResponse->getStatusCode());
            }
            
            $twoFaData = json_decode($twoFaResponse->getBody()->getContents(), true);
            
            if (isset($twoFaData['status']) && $twoFaData['status'] === 'error') {
                throw new \Exception("2FA failed: " . ($twoFaData['message'] ?? 'Unknown error'));
            }
            
            Log::info("2FA successful, extracting request token", ['username' => $this->accountUserName]);
            
            // Step 4: Get the request token by following the redirect
            $skipSessionUrl = $loginUrl . "&skip_session=true";
            
            // Use a custom redirect handler to track URLs
            $redirectUrls = [];
            
            $finalResponse = $this->httpClient->get($skipSessionUrl, [
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => false,
                    'referer' => true,
                    'on_redirect' => function($request, $response, $uri) use (&$redirectUrls) {
                        $redirectUrls[] = (string) $uri;
                    }
                ]
            ]);
            
            // Get the final URL from the last request URI
            $finalUrl = $finalResponse->getHeaderLine('Location');
            if (empty($finalUrl)) {
                // If no Location header, check the request that was made
                $finalUrl = $skipSessionUrl;
            }
            
            // Try to extract request_token from final URL first
            $requestToken = $this->extractRequestToken($finalUrl);
            
            // If not found in final URL, check all redirect URLs
            if (empty($requestToken)) {
                foreach ($redirectUrls as $redirectUrl) {
                    $requestToken = $this->extractRequestToken($redirectUrl);
                    if (!empty($requestToken)) {
                        break;
                    }
                }
            }
            
            // If still not found, try the response body (sometimes token is in response)
            if (empty($requestToken)) {
                $responseBody = $finalResponse->getBody()->getContents();
                if (preg_match('/request_token["\']?\s*[:=]\s*["\']?([a-zA-Z0-9]+)["\']?/', $responseBody, $matches)) {
                    $requestToken = $matches[1];
                }
            }
            
            if (empty($requestToken)) {
                throw new \Exception("Could not extract request_token from response. Redirect URLs: " . implode(', ', $redirectUrls));
            }
            
            Log::info("Request token extracted successfully", [
                'username' => $this->accountUserName,
                'token_preview' => substr($requestToken, 0, 10) . '...'
            ]);
            
            return $requestToken;
            
        } catch (\Exception $e) {
            Log::error("Access token generation failed", [
                'username' => $this->accountUserName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Access token generation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Extract request token from URL
     */
    private function extractRequestToken(string $url): ?string {
        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['query'])) {
            return null;
        }
        
        parse_str($parsedUrl['query'], $queryParams);
        
        return $queryParams['request_token'] ?? null;
    }
    
    /**
     * Generate KiteConnect session with token storage and retrieval
     */
    public function generateSession(): KiteConnect {
        try {
            // First, try to get stored token
            $storedToken = $this->retrieveStoredToken();
            
            if ($storedToken) {
                Log::info("Using stored token", ['username' => $this->accountUserName]);
                
                try {
                    $kite = new KiteConnect($this->apiKey);
                    $kite->setAccessToken($storedToken['access_token']);
                    
                    // Test if token is still valid by making a simple API call
                    $profile = $kite->getProfile();
                    
                    if ($profile) {
                        Log::info("Stored token is valid", ['username' => $this->accountUserName]);
                        return $kite;
                    }
                } catch (\Exception $e) {
                    Log::warning("Stored token is invalid, will generate new one", [
                        'username' => $this->accountUserName,
                        'error' => $e->getMessage()
                    ]);
                    $this->clearStoredTokens();
                }
            }
            
            // Generate new token if no valid stored token found
            Log::info("Generating new token", ['username' => $this->accountUserName]);
            
            $requestToken = $this->generateAccessToken();
            
            $kite = new KiteConnect($this->apiKey);
            $user = $kite->generateSession($requestToken, $this->apiSecret);
            
            if (!isset($user->access_token) || empty($user->access_token)) {
                throw new \Exception("No access token received from KiteConnect");
            }
            
            $kite->setAccessToken($user->access_token);
            
            // Store the token data for future use
            $tokenData = [
                'access_token' => $user->access_token,
                'user_id' => $user->user_id ?? null,
                'user_name' => $user->user_name ?? null,
                'user_shortname' => $user->user_shortname ?? null,
                'email' => $user->email ?? null,
                'user_type' => $user->user_type ?? null,
                'broker' => $user->broker ?? null,
                'exchanges' => $user->exchanges ?? [],
                'products' => $user->products ?? [],
                'order_types' => $user->order_types ?? [],
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')), // Kite tokens typically expire after 24 hours
            ];
            
            $this->storeToken($tokenData);
            
            Log::info("New Kite session generated and stored successfully", [
                'username' => $this->accountUserName,
                'user_id' => $user->user_id ?? 'N/A'
            ]);
            
            return $kite;
            
        } catch (\Exception $e) {
            Log::error("Session generation failed", [
                'username' => $this->accountUserName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Generate session manually using existing request token
     */
    public function generateSessionManual(string $token): KiteConnect|string {
        try {
            if (empty($token)) {
                Log::error("Empty token provided for Kite auth", ['username' => $this->accountUserName]);
                return 'EMPTY_TOKEN';
            }
            
            $kite = new KiteConnect($this->apiKey);
            $user = $kite->generateSession($token, $this->apiSecret);
            
            if (!isset($user->access_token) || empty($user->access_token)) {
                Log::error("No access token received from Kite", ['username' => $this->accountUserName]);
                return 'NO_ACCESS_TOKEN';
            }
            
            $kite->setAccessToken($user->access_token);
            
            Log::info("Kite authentication successful", ['username' => $this->accountUserName]);
            return $kite;
            
        } catch (\Exception $e) {
            Log::error("Kite authentication failed", [
                'username' => $this->accountUserName,
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...' // Log partial token for debugging
            ]);
            
            \Cache::forget('KITE_AUTH_' . $this->accountUserName);
            
            // Return specific error types for better handling
            if (strpos($e->getMessage(), 'token') !== false) {
                return 'TOKEN_EXPIRED';
            } elseif (strpos($e->getMessage(), 'network') !== false || strpos($e->getMessage(), 'connection') !== false) {
                return 'NETWORK_ERROR';
            } else {
                return 'AUTH_FAILED';
            }
        }
    }
    
    /**
     * Test connectivity to Kite APIs
     */
    public function testConnection(): bool {
        try {
            $response = $this->httpClient->get("https://kite.zerodha.com/");
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            Log::error("Kite connection test failed", ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Clear session and cookies
     */
    public function clearSession(): void {
        $this->cookieJar->clear();
    }
    
    /**
     * Get current session cookies (for debugging)
     */
    public function getSessionCookies(): array {
        $cookies = [];
        foreach ($this->cookieJar as $cookie) {
            $cookies[] = [
                'name' => $cookie->getName(),
                'value' => $cookie->getValue(),
                'domain' => $cookie->getDomain()
            ];
        }
        return $cookies;
    }
    
    /**
     * Get stored token info (for debugging/monitoring)
     */
    public function getStoredTokenInfo(): array {
        try {
            $tokenRecord = \App\Models\KiteToken::where('username', $this->accountUserName)->first();
            $cachedToken = \Cache::get($this->cacheKey);
            
            return [
                'username' => $this->accountUserName,
                'cache_exists' => !empty($cachedToken),
                'db_exists' => !empty($tokenRecord),
                'db_expires_at' => $tokenRecord?->expires_at?->toDateTimeString(),
                'db_updated_at' => $tokenRecord?->updated_at?->toDateTimeString(),
                'is_expired' => $tokenRecord ? $tokenRecord->isExpired() : null,
                'expires_soon' => $tokenRecord ? $tokenRecord->expiresSoon() : null,
                'valid_token_available' => !empty($this->retrieveStoredToken())
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'username' => $this->accountUserName
            ];
        }
    }
    
    /**
     * Force refresh token (clear existing and generate new)
     */
    public function forceRefreshToken(): KiteConnect {
        Log::info("Force refreshing token", ['username' => $this->accountUserName]);
        
        $this->clearStoredTokens();
        
        return $this->generateSession();
    }
}