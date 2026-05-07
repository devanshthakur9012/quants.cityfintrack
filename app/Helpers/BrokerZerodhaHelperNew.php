<?php
// app/Helpers/BrokerZerodhaHelperNew.php

namespace App\Helpers;

use App\Models\BrokerApi;
use KiteConnect\KiteConnect;
use Illuminate\Support\Facades\Log;
use Exception;

class BrokerZerodhaHelperNew
{
    private $kite;
    private $broker;
    private $apiKey;
    private $accessToken;

    public function __construct($broker)
    {
        if (is_numeric($broker)) {
            $this->broker = BrokerApi::findOrFail($broker);
        } else {
            $this->broker = $broker;
        }

        if ($this->broker->client_type !== 'Zerodha') {
            throw new Exception('Broker is not a Zerodha broker');
        }

        if (!$this->broker->hasValidToken()) {
            throw new Exception("Broker {$this->broker->client_name} has invalid or expired access token");
        }

        $this->apiKey = $this->broker->api_key;
        $this->accessToken = $this->broker->access_token;

        $this->kite = new KiteConnect($this->apiKey);
        $this->kite->setAccessToken($this->accessToken);

        Log::info("BrokerZerodhaHelper initialized for broker: {$this->broker->client_name} (ID: {$this->broker->id})");
    }

    public function getBroker()
    {
        return $this->broker;
    }

    public function getHistoricalDataByToken($instrumentToken, $interval = '15minute', $fromDate = null, $toDate = null)
    {
        try {
            if (!$fromDate) {
                $fromDate = date('Y-m-d H:i:s', strtotime('-7 days'));
            }
            if (!$toDate) {
                $toDate = date('Y-m-d H:i:s');
            }

            $historicalData = $this->kite->getHistoricalData(
                $instrumentToken,
                $interval,
                $fromDate,
                $toDate,
                0,  // continuous
                1   // oi - Get Open Interest data
            );

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

    public function getQuote($symbols)
    {
        try {
            if (is_string($symbols)) {
                $symbolsArray = [$symbols];
                $quotes = $this->kite->getQuote($symbolsArray);
                return $quotes[$symbols] ?? null;
            }
            
            $quotes = $this->kite->getQuote($symbols);
            return $quotes;

        } catch (Exception $e) {
            Log::error("Get quote error for broker {$this->broker->client_name}", [
                'symbols' => $symbols,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getQuotes(array $tradingSymbols)
    {
        try {
            $quotes = $this->kite->getQuote($tradingSymbols);
            return $quotes;
        } catch (Exception $e) {
            Log::error('Get Quotes Error', [
                'broker' => $this->broker->client_name,
                'symbols' => $tradingSymbols,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

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

    public function validateToken()
    {
        try {
            $profile = $this->kite->getProfile();
            
            if ($profile) {
                Log::info("Token validated for broker: {$this->broker->client_name}");
                return true;
            }

            return false;

        } catch (Exception $e) {
            Log::error("Token validation failed for broker {$this->broker->client_name}: " . $e->getMessage());
            $this->broker->update(['is_token_valid' => false]);
            return false;
        }
    }

    public function getKiteInstance()
    {
        return $this->kite;
    }
}