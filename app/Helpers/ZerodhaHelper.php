<?php

namespace App\Helpers;

use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ZerodhaHelper
{
    private $kite;
    private $apiKey;
    private $apiSecret;
    private $userId;
    private $password;
    private $totp;
    private $accessToken;

    public function __construct($skipAuth = false)
    {
        $this->apiKey = env('ZERODHA_API_KEY');
        $this->apiSecret = env('ZERODHA_API_SECRET');
        $this->userId = env('ZERODHA_USER_ID');
        $this->password = env('ZERODHA_PASSWORD');
        $this->totp = env('ZERODHA_TOTP');

        $this->kite = new KiteConnect($this->apiKey);
        
        if (!$skipAuth) {
            $this->authenticate();
        }
    }

    /**
     * Authenticate and get access token
     */
    private function authenticate()
    {
        try {
            // Check if we have a valid cached access token
            $this->accessToken = Cache::get('zerodha_access_token');

            if (!$this->accessToken) {
                Log::warning('Zerodha: No access token found in cache');
                throw new Exception('Access token not found. Please authenticate first by visiting /zerodha/login');
            }

            $this->kite->setAccessToken($this->accessToken);
            Log::info('Zerodha: Authentication successful with cached token');
            
        } catch (Exception $e) {
            Log::error('Zerodha Authentication Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate session with request token (call this manually first time)
     */
    public function generateSession($requestToken)
    {
        try {
            $response = $this->kite->generateSession($requestToken, $this->apiSecret);
            $this->accessToken = $response->access_token;
            
            // Cache access token for 24 hours (tokens are valid till 6 AM next day)
            Cache::put('zerodha_access_token', $this->accessToken, now()->addHours(23));
            
            $this->kite->setAccessToken($this->accessToken);
            
            Log::info('Zerodha session generated successfully');
            return $this->accessToken;
            
        } catch (Exception $e) {
            Log::error('Zerodha Session Generation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get instrument token for a trading symbol
     */
    public function getInstrumentToken($tradingSymbol, $exchange = 'NSE')
    {
        try {
            $instrument = \DB::table('zerodha_instruments')
                ->where('trading_symbol', $tradingSymbol)
                ->where('exchange', $exchange)
                ->where('instrument_type', 'EQ')
                ->first();

            if (!$instrument) {
                throw new Exception("Instrument not found: {$tradingSymbol}");
            }

            return $instrument->instrument_token;
            
        } catch (Exception $e) {
            Log::error('Get Instrument Token Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch historical data using instrument token directly (FOR FUTURES)
     * 
     * @param string $instrumentToken - Instrument token (e.g., '12616194')
     * @param string $interval - Interval: minute, 3minute, 5minute, 10minute, 15minute, 30minute, 60minute, day
     * @param string $fromDate - From date (Y-m-d H:i:s)
     * @param string $toDate - To date (Y-m-d H:i:s)
     * @return array
     */
    public function getHistoricalDataByToken($instrumentToken, $interval = '15minute', $fromDate = null, $toDate = null)
    {
        try {
            // Default to last 7 days if dates not provided
            if (!$fromDate) {
                $fromDate = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            if (!$toDate) {
                $toDate = date('Y-m-d H:i:s');
            }

            // Call with individual parameters
            // Set oi=1 to get Open Interest data for futures
            $historicalData = $this->kite->getHistoricalData(
                $instrumentToken,
                $interval,
                $fromDate,
                $toDate,
                0,  // continuous
                1   // oi - IMPORTANT: Set to 1 to fetch Open Interest
            );
            
            // Log::info("Fetched historical data for token {$instrumentToken}: " . count($historicalData) . " records");
            
            return $historicalData;
            
        } catch (Exception $e) {
            Log::error("Historical Data Fetch Error for token {$instrumentToken}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch historical data for a symbol (FOR STOCKS - ORIGINAL METHOD)
     * 
     * @param string $tradingSymbol - Trading symbol (e.g., 'AXISBANK')
     * @param string $interval - Interval: minute, 3minute, 5minute, 10minute, 15minute, 30minute, 60minute, day
     * @param string $fromDate - From date (Y-m-d H:i:s)
     * @param string $toDate - To date (Y-m-d H:i:s)
     * @param string $exchange - Exchange (NSE, BSE, etc.)
     * @return array
     */
    public function getHistoricalData($tradingSymbol, $interval = '15minute', $fromDate = null, $toDate = null, $exchange = 'NSE')
    {
        try {
            $instrumentToken = $this->getInstrumentToken($tradingSymbol, $exchange);
            
            // Default to last 7 days if dates not provided
            if (!$fromDate) {
                $fromDate = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            if (!$toDate) {
                $toDate = date('Y-m-d H:i:s');
            }

            // Call with individual parameters, not an array
            $historicalData = $this->kite->getHistoricalData(
                $instrumentToken,
                $interval,
                $fromDate,
                $toDate,
                0,  // continuous
                0   // oi
            );
            
            // Log::info("Fetched historical data for {$tradingSymbol}: " . count($historicalData) . " records");
            
            return $historicalData;
            
        } catch (Exception $e) {
            Log::error("Historical Data Fetch Error for {$tradingSymbol}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get LTP (Last Traded Price) for symbols
     * 
     * @param array $symbols - Array of symbols in format ['NSE:AXISBANK', 'NSE:INFY']
     * @return array
     */
    public function getLTP($symbols)
    {
        try {
            $quotes = $this->kite->getLTP($symbols);
            return $quotes;
            
        } catch (Exception $e) {
            Log::error('Get LTP Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get full quote for symbols
     * 
     * @param array $symbols - Array of symbols in format ['NSE:AXISBANK', 'NSE:INFY']
     * @return array
     */
    public function getQuote($symbols)
    {
        try {
            $quotes = $this->kite->getQuote($symbols);
            return $quotes;
            
        } catch (Exception $e) {
            Log::error('Get Quote Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get OHLC (Open, High, Low, Close) for symbols
     * 
     * @param array $symbols - Array of symbols in format ['NSE:AXISBANK', 'NSE:INFY']
     * @return array
     */
    public function getOHLC($symbols)
    {
        try {
            $ohlc = $this->kite->getOHLC($symbols);
            return $ohlc;
            
        } catch (Exception $e) {
            Log::error('Get OHLC Error: ' . $e->getMessage());
            throw $e;
        }
    }
}