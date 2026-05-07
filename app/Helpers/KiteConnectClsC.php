<?php

namespace App\Helpers;

use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class KiteConnectClsC
{
    protected $kite;
    protected $apiKey;
    protected $apiSecret;
    protected $accessToken;
    protected $username;
    protected $password;
    protected $totpSecret;
    protected $cacheKey;

    public function __construct($params)
    {
        $this->apiKey = $params['apiKey'];
        $this->apiSecret = $params['apiSecret'];
        $this->username = $params['accountUserName'] ?? 'default';
        $this->password = $params['accountPassword'] ?? null;
        $this->totpSecret = $params['totpSecret'] ?? null;
        $this->cacheKey = 'KITE_AUTH_' . $this->username;

        $this->kite = new KiteConnect($this->apiKey);
        // $this->kite->setDebug(true);
        
        $this->loadAccessToken();
    }

    /**
     * Automated login with credentials + TOTP
     */
    public function autoLogin()
    {
        // If already authenticated, return true
        if ($this->isAuthenticated()) {
            return true;
        }

        // Check if we have credentials for auto-login
        if (empty($this->password) || empty($this->totpSecret)) {
            Log::error("Auto-login failed: Missing credentials for " . $this->username);
            return false;
        }

        try {
            // Generate current TOTP
            $totp = $this->generateTOTP($this->totpSecret);
            
            // Get request token
            $requestToken = $this->getRequestTokenViaLogin($totp);
            
            if (!$requestToken) {
                Log::error("Failed to get request token via login for " . $this->username);
                return false;
            }

            Log::info("Generated request token", [
                'request_token' => $requestToken,
                'broker' => $this->username
            ]);

            // Generate session with request token
            $session = $this->kite->generateSession($requestToken, $this->apiSecret);
            
            $this->accessToken = $session['access_token'];
            $this->kite->setAccessToken($this->accessToken);
            
            // Cache the access token
            $this->cacheAccessToken($this->accessToken);
            
            Log::info("Auto-login successful for: " . $this->username);
            return true;

        } catch (\Exception $e) {
            Log::error("Auto-login failed for {$this->username}: " . $e->getMessage());
            return false;
        }
    }

    public function debugAuthStatus()
    {
        $status = [
            'has_credentials' => !empty($this->password) && !empty($this->totpSecret),
            'has_access_token' => !empty($this->accessToken),
            'username' => $this->username,
            'cache_key' => $this->cacheKey,
            'cached_token' => Cache::get($this->cacheKey)
        ];
        
        Log::debug("Auth debug info", $status);
        return $status;
    }

    /**
     * Generate TOTP code
     */
    protected function generateTOTP($secret)
    {
        // Use PHPGangsta's GoogleAuthenticator or similar if available
        // For now, use a simple implementation
        
        $secretKey = $this->base32Decode($secret);
        $timeSlice = floor(time() / 30);
        
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $secretKey, true);
        
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        
        $value = unpack('N', $hashpart);
        $value = $value[1] & 0x7FFFFFFF;
        
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decode for TOTP secret
     */
    protected function base32Decode($secret)
    {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32CharsFlipped = array_flip(str_split($base32Chars));
        
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = [6, 4, 3, 1, 0];
        
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }
        
        $values = [];
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        
        for ($i = 0; $i < count($secret); $i += 8) {
            $bytes = [];
            $val = 0;
            $bits = 0;
            
            for ($j = 0; $j < 8; $j++) {
                if (isset($secret[$i + $j])) {
                    $val <<= 5;
                    $val |= $base32CharsFlipped[$secret[$i + $j]] & 31;
                    $bits += 5;
                }
            }
            
            while ($bits >= 8) {
                $bits -= 8;
                $bytes[] = ($val & (0xFF << $bits)) >> $bits;
            }
            
            $values = array_merge($values, $bytes);
        }
        
        return pack('C*', ...$values);
    }

    /**
     * Get request token via login API (direct HTTP call)
     */
    // protected function getRequestTokenViaLogin($totp)
    // {
    //     try {
    //         // Kite's login API endpoint
    //         $loginUrl = 'https://kite.zerodha.com/api/login';
            
    //         $response = Http::withoutVerifying()->asForm()->post($loginUrl, [
    //             'user_id' => $this->username,
    //             'password' => $this->password,
    //             'twofa_value' => $totp,
    //             'twofa_type' => 'totp'
    //         ]);
            
    //         if ($response->successful()) {
    //             $data = $response->json();
    //             if (isset($data['data']['request_token'])) {
    //                 return $data['data']['request_token'];
    //             }
    //         }
            
    //         Log::error("Login API failed: " . $response->body());
    //         return null;
            
    //     } catch (\Exception $e) {
    //         Log::error("Login API exception: " . $e->getMessage());
    //         return null;
    //     }
    // }

    protected function getRequestTokenViaLogin($totp)
    {
        try {
            $loginUrl = 'https://kite.telemetry.zerodha.com/api/login';
            
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'X-Kite-Version' => '3',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])
                ->asForm()
                ->post($loginUrl, [
                    'user_id' => $this->username,
                    'password' => $this->password,
                    'twofa_value' => $totp
                ]);
            
            Log::info("Login API Response", [
                'status' => $response->status(),
                'body' => $response->body(),
                'broker' => $this->username
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Debug: Log the full response structure
                Log::debug("Login API full response", ['data' => $data]);
                
                // Modern Kite API returns request_token directly
                if (isset($data['data']['request_token'])) {
                    return $data['data']['request_token'];
                }
                // Alternative response format
                elseif (isset($data['request_token'])) {
                    return $data['request_token'];
                }
                // Sometimes it's in a different location
                elseif (isset($data['data']['request_id'])) {
                    return $data['data']['request_id'];
                }
                else {
                    Log::error("No request token found in login response", [
                        'response_keys' => array_keys($data),
                        'broker' => $this->username
                    ]);
                    return null;
                }
            } else {
                Log::error("Login API failed with status: " . $response->status(), [
                    'response' => $response->body(),
                    'broker' => $this->username
                ]);
                return null;
            }
            
        } catch (\Exception $e) {
            Log::error("Login API exception: " . $e->getMessage(), [
                'broker' => $this->username,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Check authentication status
     */
    public function isAuthenticated()
    {
        if (empty($this->accessToken)) {
            return false;
        }

        // Quick test to validate token
        try {
            $this->kite->getMargins();
            return true;
        } catch (\Exception $e) {
            Log::warning("Token validation failed: " . $e->getMessage());
            $this->clearAccessToken();
            return false;
        }
    }

    /**
     * Load access token from cache
     */
    protected function loadAccessToken()
    {
        $cachedToken = Cache::get($this->cacheKey);
        
        if ($cachedToken) {
            $this->accessToken = $cachedToken;
            $this->kite->setAccessToken($this->accessToken);
        }
    }

    /**
     * Cache access token
     */
    protected function cacheAccessToken($accessToken)
    {
        Cache::put($this->cacheKey, $accessToken, now()->addHours(23));
    }

    /**
     * Clear cached access token
     */
    public function clearAccessToken()
    {
        Cache::forget($this->cacheKey);
        $this->accessToken = null;
    }

    /**
     * Get Kite instance with auto-authentication
     */
    public function getAuthenticatedKite()
    {
        if (!$this->isAuthenticated()) {
            if (!$this->autoLogin()) {
                throw new \Exception("Failed to authenticate with KiteConnect");
            }
        }
        return $this->kite;
    }

    // ... rest of the methods from previous implementation
    /**
     * Get Login URL for manual authentication
     */
    public function getLoginUrl()
    {
        return $this->kite->getLoginURL();
    }

    /**
     * Generate session with request token (first-time setup)
     */
    public function generateSession($requestToken)
    {
        try {
            $session = $this->kite->generateSession($requestToken, $this->apiSecret);
            
            $this->accessToken = $session['access_token'];
            $this->kite->setAccessToken($this->accessToken);
            
            // Cache the access token for future use (valid for 24 hours)
            $this->cacheAccessToken($this->accessToken);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Kite session generation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load access token from cache
     */
    // protected function loadAccessToken()
    // {
    //     $cachedToken = Cache::get($this->cacheKey);
        
    //     if ($cachedToken) {
    //         $this->accessToken = $cachedToken;
    //         $this->kite->setAccessToken($this->accessToken);
    //     }
    // }

    /**
     * Cache access token
     */
    // protected function cacheAccessToken($accessToken)
    // {
    //     // Cache for 23 hours (Kite tokens typically last 24 hours)
    //     Cache::put($this->cacheKey, $accessToken, now()->addHours(23));
    // }

    /**
     * Clear cached access token
     */
    // public function clearAccessToken()
    // {
    //     Cache::forget($this->cacheKey);
    //     $this->accessToken = null;
    // }

    /**
     * Get Kite instance
     */
    public function getKite()
    {
        return $this->kite;
    }

    /**
     * Get positions with proper error handling
     */
    public function getPositions()
    {
        if (!$this->isAuthenticated()) {
            throw new \Exception("Not authenticated with KiteConnect");
        }

        try {
            return $this->kite->getPositions();
        } catch (\Exception $e) {
            // If it's an auth error, clear the token
            if (strpos($e->getMessage(), 'token') !== false || 
                strpos($e->getMessage(), 'auth') !== false ||
                strpos($e->getMessage(), 'Invalid') !== false) {
                $this->clearAccessToken();
            }
            throw $e;
        }
    }

    /**
     * Place order with proper error handling
     */
    public function placeOrder($variety, $params)
    {
        if (!$this->isAuthenticated()) {
            throw new \Exception("Not authenticated with KiteConnect");
        }

        try {
            return $this->kite->placeOrder($variety, $params);
        } catch (\Exception $e) {
            // If it's an auth error, clear the token
            if (strpos($e->getMessage(), 'token') !== false || 
                strpos($e->getMessage(), 'auth') !== false ||
                strpos($e->getMessage(), 'Invalid') !== false) {
                $this->clearAccessToken();
            }
            throw $e;
        }
    }
}

// class KiteConnectCls
// {
//     protected $kite;
//     protected $apiKey;
//     protected $apiSecret;
//     protected $accessToken;
//     protected $username;
//     protected $cacheKey;

//     public function __construct($params)
//     {
//         $this->apiKey = $params['apiKey'];
//         $this->apiSecret = $params['apiSecret'];
//         $this->username = $params['accountUserName'] ?? 'default';
//         $this->cacheKey = 'KITE_AUTH_' . $this->username;

//         $this->kite = new KiteConnect($this->apiKey);

//         // Set debug mode for better error tracking
//         // $this->kite->setDebug(true);
        
//         // Try to load access token from cache
//         $this->loadAccessToken();
//     }

//     /**
//      * Check if we have a valid access token
//      */
//     public function isAuthenticated()
//     {
//         return !empty($this->accessToken);
//     }

    
// }