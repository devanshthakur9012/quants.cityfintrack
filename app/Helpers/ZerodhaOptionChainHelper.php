<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use Illuminate\Support\Facades\Log;
use Exception;

class ZerodhaOptionChainHelper
{
    /**
     * Fetch live option chain data with OI and LTP
     * 
     * @param BrokerApi $broker
     * @param string $baseSymbol (e.g., NIFTY, RELIANCE)
     * @param array $strikes [20000, 20050, 20100]
     * @param string $optionType (CE or PE)
     * @param string $expiry (Y-m-d format)
     * @return array
     */
    public static function fetchLiveOptionData(
        BrokerApi $broker,
        string $baseSymbol,
        array $strikes,
        string $optionType,
        string $expiry
    ): array {
        try {
            // ✅ Add rate limit protection
            static $lastApiCall = 0;
            $minDelayMs = 350; // 350ms = ~3 requests per second
            
            $now = microtime(true) * 1000;
            $timeSinceLastCall = $now - $lastApiCall;
            
            if ($timeSinceLastCall < $minDelayMs) {
                $sleepTime = ($minDelayMs - $timeSinceLastCall) * 1000;
                usleep((int)$sleepTime);
            }
            
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            $kite = $zerodhaHelper->getKiteInstance();
            
            // Build symbols
            $symbolsToFetch = [];
            $strikeMapping = [];
            
            foreach ($strikes as $strike) {
                // ✅ Format: RELIANCE26FEB1280CE (uppercase month, no year digits)
                $expiryFormatted = strtoupper(date('yM', strtotime($expiry)));
                $tradingSymbol = "{$baseSymbol}{$expiryFormatted}{$strike}{$optionType}";
                
                $symbolsToFetch[] = "NFO:{$tradingSymbol}";
                $strikeMapping["NFO:{$tradingSymbol}"] = $strike;
            }
            
            Log::info("Batch fetching options", [
                'base_symbol' => $baseSymbol,
                'option_type' => $optionType,
                'strikes' => $strikes,
                'symbols' => $symbolsToFetch
            ]);
            
            try {
                // ✅ Fetch all strikes at once
                $quotes = $kite->getQuote($symbolsToFetch);
                
                // ✅ Update last API call time
                $lastApiCall = microtime(true) * 1000;
                
                $optionsData = [];
                
                foreach ($symbolsToFetch as $symbol) {
                    $strike = $strikeMapping[$symbol];
                    
                    if (isset($quotes[$symbol]) && $quotes[$symbol]) {
                        $data = $quotes[$symbol]; // ✅ This is an stdClass object
                        
                        // ✅ Access object properties with -> not []
                        $hasValidData = isset($data->last_price) || isset($data->oi);
                        
                        // ✅ Extract bid/ask safely
                        $bid = null;
                        $ask = null;
                        if (isset($data->depth)) {
                            if (isset($data->depth->buy) && is_array($data->depth->buy) && count($data->depth->buy) > 0) {
                                $bid = $data->depth->buy[0]->price ?? null;
                            }
                            if (isset($data->depth->sell) && is_array($data->depth->sell) && count($data->depth->sell) > 0) {
                                $ask = $data->depth->sell[0]->price ?? null;
                            }
                        }
                        
                        $optionsData[] = [
                            'strike' => $strike,
                            'symbol' => str_replace('NFO:', '', $symbol),
                            'ltp' => $data->last_price ?? null,
                            'oi' => $data->oi ?? 0,
                            'volume' => $data->volume ?? 0,
                            'bid' => $bid,
                            'ask' => $ask,
                            'change' => $data->net_change ?? null,
                        ];
                        
                        if ($hasValidData) {
                            Log::info("✅ {$symbol}: LTP=" . ($data->last_price ?? 'N/A') . ", OI=" . ($data->oi ?? 0));
                        } else {
                            Log::warning("⚠️ {$symbol}: No valid data in response");
                        }
                    } else {
                        $optionsData[] = [
                            'strike' => $strike,
                            'symbol' => str_replace('NFO:', '', $symbol),
                            'ltp' => null,
                            'oi' => 0,
                            'volume' => 0,
                            'bid' => null,
                            'ask' => null,
                            'change' => null,
                        ];
                        
                        Log::warning("⚠️ No data for {$symbol}");
                    }
                }
                
                return $optionsData;
                
            } catch (Exception $e) {
                // ✅ Better error handling
                $errorMsg = $e->getMessage();
                
                if (strpos($errorMsg, 'Too many requests') !== false) {
                    Log::error("⛔ RATE LIMIT HIT! Sleeping 2 seconds...");
                    sleep(2);
                }
                
                Log::error("API Error: " . $errorMsg);
                
                // Return empty structure
                $optionsData = [];
                foreach ($strikes as $strike) {
                    $optionsData[] = [
                        'strike' => $strike,
                        'symbol' => "{$baseSymbol}{$strike}{$optionType}",
                        'ltp' => null,
                        'oi' => 0,
                        'volume' => 0,
                        'bid' => null,
                        'ask' => null,
                        'change' => null,
                    ];
                }
                
                return $optionsData;
            }
            
        } catch (Exception $e) {
            Log::error("Critical error in fetchLiveOptionData: " . $e->getMessage());
            throw $e;
        }
    }
}