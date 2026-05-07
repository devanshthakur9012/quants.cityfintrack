<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Helpers\ZerodhaHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ZerodhaAuthController extends Controller
{
    /**
     * Redirect to Zerodha login page
     */
    public function redirectToZerodha()
    {
        $apiKey = env('ZERODHA_API_KEY');
        $loginUrl = "https://kite.zerodha.com/connect/login?api_key={$apiKey}";
        
        return redirect($loginUrl);
    }

    /**
     * Handle Zerodha callback and generate session
     */
    public function handleCallback(Request $request)
    {
        try {
            $requestToken = $request->get('request_token');
            
            if (!$requestToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request token not found'
                ], 400);
            }

            // Skip authentication in constructor since we're generating the token now
            $zerodha = new ZerodhaHelper(true);
            $accessToken = $zerodha->generateSession($requestToken);

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'access_token' => $accessToken,
                'expires_at' => now()->addHours(23)->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Zerodha callback and generate session
     */
    public function handleCallback2(Request $request)
    {
        try {
            $requestToken = $request->get('request_token');
            
            if (!$requestToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request token not found'
                ], 400);
            }

            // Skip authentication in constructor since we're generating the token now
            $zerodha = new ZerodhaHelper(true);
            $accessToken = $zerodha->generateSession($requestToken);

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'access_token' => $accessToken,
                'expires_at' => now()->addHours(23)->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual access token setup (for first time or testing)
     */
    public function setAccessToken(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string'
        ]);

        try {
            $accessToken = $request->input('access_token');
            
            // Cache for 23 hours
            Cache::put('zerodha_access_token', $accessToken, now()->addHours(23));

            return response()->json([
                'success' => true,
                'message' => 'Access token set successfully',
                'expires_at' => now()->addHours(23)->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set access token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check authentication status
     */
    public function checkAuthStatus()
    {
        $accessToken = Cache::get('zerodha_access_token');
        
        return response()->json([
            'authenticated' => !empty($accessToken),
            'access_token' => $accessToken ? substr($accessToken, 0, 10) . '...' : null,
            'expires_at' => Cache::get('zerodha_access_token') ? 'Available' : 'Not set'
        ]);
    }
}