<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Broker-specific Zerodha API Helper
 * Uses broker's own API credentials and access token
 */
class BrokerZerodhaHelper
{
    private $kite;
    private $broker;
    private $apiKey;
    private $accessToken;

    /**
     * Initialize with specific broker
     * 
     * @param BrokerApi|int $broker - Broker model or broker ID
     */
    public function __construct($broker)
    {
        if (is_numeric($broker)) {
            $this->broker = BrokerApi::findOrFail($broker);
        } else {
            $this->broker = $broker;
        }

        // Validate broker type
        if ($this->broker->client_type !== 'Zerodha') {
            throw new Exception('Broker is not a Zerodha broker');
        }

        // Check if token is valid
        if (!$this->broker->hasValidToken()) {
            throw new Exception("Broker {$this->broker->client_name} has invalid or expired access token");
        }

        $this->apiKey = $this->broker->api_key;
        $this->accessToken = $this->broker->access_token;

        // Initialize KiteConnect with broker's credentials
        $this->kite = new KiteConnect($this->apiKey);
        $this->kite->setAccessToken($this->accessToken);

        Log::info("BrokerZerodhaHelper initialized for broker: {$this->broker->client_name} (ID: {$this->broker->id})");
    }

    /**
     * Get broker information
     */
    public function getBroker()
    {
        return $this->broker;
    }

    /**
     * Fetch historical data using instrument token
     * 
     * @param string $instrumentToken - Instrument token
     * @param string $interval - Interval (minute, 5minute, 15minute, etc.)
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

            // Log::info("Fetching historical data using broker: {$this->broker->client_name}", [
            //     'broker_id' => $this->broker->id,
            //     'instrument_token' => $instrumentToken,
            //     'interval' => $interval,
            //     'from' => $fromDate,
            //     'to' => $toDate
            // ]);

            // Fetch data with OI (Open Interest) for futures
            $historicalData = $this->kite->getHistoricalData(
                $instrumentToken,
                $interval,
                $fromDate,
                $toDate,
                0,  // continuous
                1   // oi - Get Open Interest data
            );

            // Log::info("Fetched {$this->broker->client_name}: {$instrumentToken} - " . count($historicalData) . " candles");

            return $historicalData;

        } catch (Exception $e) {
            Log::error("Historical data fetch error for broker {$this->broker->client_name}: " . $e->getMessage(), [
                'broker_id' => $this->broker->id,
                'instrument_token' => $instrumentToken,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get LTP (Last Traded Price) for symbols
     * 
     * @param array $symbols - Array of symbols in format ['NSE:RELIANCE', 'NFO:NIFTY24JANFUT']
     * @return array
     */
    public function getLTP($symbols)
    {
        try {
            $quotes = $this->kite->getLTP($symbols);
            return $quotes;

        } catch (Exception $e) {
            Log::error("Get LTP error for broker {$this->broker->client_name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get full quote for symbols
     * 
     * @param array $symbols - Array of symbols
     * @return array
     */
    public function getQuote($symbols)
    {
        try {
            $quotes = $this->kite->getQuote($symbols);
            return $quotes;

        } catch (Exception $e) {
            Log::error("Get quote error for broker {$this->broker->client_name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get OHLC for symbols
     * 
     * @param array $symbols - Array of symbols
     * @return array
     */
    public function getOHLC($symbols)
    {
        try {
            $ohlc = $this->kite->getOHLC($symbols);
            return $ohlc;

        } catch (Exception $e) {
            Log::error("Get OHLC error for broker {$this->broker->client_name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate token is still working
     * 
     * @return bool
     */
    public function validateToken()
    {
        try {
            // Try to get profile to check if token works
            $profile = $this->kite->getProfile();
            
            if ($profile) {
                Log::info("Token validated for broker: {$this->broker->client_name}");
                return true;
            }

            return false;

        } catch (Exception $e) {
            Log::error("Token validation failed for broker {$this->broker->client_name}: " . $e->getMessage());
            
            // Mark token as invalid
            $this->broker->update(['is_token_valid' => false]);
            
            return false;
        }
    }

    /**
     * Get raw KiteConnect instance (for advanced usage)
     */
    public function getKiteInstance()
    {
        return $this->kite;
    }
}