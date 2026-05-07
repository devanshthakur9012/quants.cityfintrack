<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\OptionPriceCache;
use App\Models\ZerodhaInstrument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

class SymbolBacktestProfitControllerold1 extends Controller
{
    private $kite;
    private $userId = 'ZZL808'; // Hardcoded user ID

    /**
     * Calculate profit/loss for backtested signals (SYMBOLS)
     * IDENTICAL TO FUTURES - except uses BrokerApi instead of ENV
     */
    public function calculateProfit(Request $request)
    {
        try {
            Log::info('=== SYMBOL BACKTEST PROFIT CALCULATION START ===');
            
            $signals = $request->input('signals', []);
            
            if (empty($signals)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No signals provided',
                    'data' => []
                ]);
            }

            Log::info('Processing ' . count($signals) . ' signals');

            // Initialize Kite Connect (uses BrokerApi instead of ENV)
            $this->initializeKite();

            $results = [];
            $totalProfit = 0;
            $totalInvestment = 0;
            $totalTrades = 0;
            $winningTrades = 0;
            $losingTrades = 0;

            foreach ($signals as $signal) {
                $result = $this->processSingleSignal($signal);
                
                if ($result) {
                    $results[] = $result;
                    $totalProfit += $result['profit_loss'];
                    $totalInvestment += $result['investment'];
                    $totalTrades++;
                    
                    if ($result['profit_loss'] > 0) {
                        $winningTrades++;
                    } elseif ($result['profit_loss'] < 0) {
                        $losingTrades++;
                    }
                }
            }

            $winRate = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;
            $avgProfit = $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0;

            Log::info('=== SYMBOL BACKTEST PROFIT CALCULATION COMPLETE ===');
            Log::info("Total Trades: {$totalTrades}, Total P/L: {$totalProfit}, Win Rate: {$winRate}%");

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total_trades' => $totalTrades,
                    'winning_trades' => $winningTrades,
                    'losing_trades' => $losingTrades,
                    'win_rate' => $winRate,
                    'total_investment' => round($totalInvestment, 2),
                    'total_profit_loss' => round($totalProfit, 2),
                    'avg_profit_loss' => $avgProfit,
                    'roi_percent' => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0
                ],
                'message' => 'Profit calculation completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Symbol Profit Calculation Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Process single signal for profit calculation
     * IDENTICAL TO FUTURES VERSION
     */
    // private function processSingleSignal($signal)
    // {
    //     try {
    //         $optionSymbol = $signal['option_symbol'];
    //         $signalTime = Carbon::parse($signal['signal_time']);
            
    //         Log::info("Processing: {$optionSymbol} at {$signalTime}");

    //         // Get instrument details
    //         $instrument = ZerodhaInstrument::where('trading_symbol', $optionSymbol)
    //             ->where('exchange', 'NFO')
    //             ->first();

    //         if (!$instrument) {
    //             Log::warning("Instrument not found: {$optionSymbol}");
    //             return null;
    //         }

    //         // Get entry price (at signal time)
    //         $entryPrice = $this->getHistoricalPrice($instrument, $signalTime);
            
    //         if (!$entryPrice) {
    //             Log::warning("Could not get entry price for {$optionSymbol}");
    //             return null;
    //         }

    //         // Calculate exit datetime (next day 10:30 AM)
    //         $exitDateTime = $this->calculateExitDateTime($signalTime);
            
    //         // Get exit price
    //         $exitPrice = $this->getExitPrice($instrument, $exitDateTime);
            
    //         if (!$exitPrice) {
    //             Log::warning("Could not get exit price for {$optionSymbol}");
    //             return null;
    //         }

    //         // Calculate P/L (assuming 1 lot = lot_size quantity)
    //         $quantity = $instrument->lot_size ?? 1;
    //         $profitLoss = ($exitPrice - $entryPrice) * $quantity;

    //         Log::info("✅ {$optionSymbol}: Entry={$entryPrice}, Exit={$exitPrice}, P/L={$profitLoss}");

    //         return [
    //             'option_symbol' => $optionSymbol,
    //             'signal_time' => $signalTime->format('Y-m-d H:i:s'),
    //             'exit_time' => $exitDateTime->format('Y-m-d H:i:s'),
    //             'entry_price' => round($entryPrice, 2),
    //             'exit_price' => round($exitPrice, 2),
    //             'quantity' => $quantity,
    //             'investment' => round($entryPrice * $quantity, 2),
    //             'profit_loss' => round($profitLoss, 2),
    //             'profit_loss_per_lot' => round($exitPrice - $entryPrice, 2),
    //             'return_percent' => $entryPrice > 0 ? round((($exitPrice - $entryPrice) / $entryPrice) * 100, 2) : 0
    //         ];

    //     } catch (\Exception $e) {
    //         Log::error("Error processing signal: " . $e->getMessage());
    //         return null;
    //     }
    // }
    private function processSingleSignal($signal)
    {
        try {
            $optionSymbol = $signal['option_symbol'];
            $signalTime = Carbon::parse($signal['signal_time']);
            
            Log::info("Processing: {$optionSymbol} at {$signalTime}");

            // Get instrument details
            $instrument = ZerodhaInstrument::where('trading_symbol', $optionSymbol)
                ->where('exchange', 'NFO')
                ->first();

            if (!$instrument) {
                Log::warning("Instrument not found: {$optionSymbol}");
                return null;
            }

            // Get entry price (at signal time)
            $entryPrice = $this->getHistoricalPrice($instrument, $signalTime);
            
            if (!$entryPrice) {
                Log::warning("Could not get entry price for {$optionSymbol}");
                return null;
            }

            // Calculate exit datetime (next day 10:30 AM)
            $exitDateTime = $this->calculateExitDateTime($signalTime);
            
            // Get exit price - NOW PASSING SIGNAL TIME
            $exitPrice = $this->getExitPrice($instrument, $exitDateTime, $signalTime);
            
            if (!$exitPrice) {
                Log::warning("Could not get exit price for {$optionSymbol}");
                return null;
            }

            // Calculate P/L (assuming 1 lot = lot_size quantity)
            $quantity = $instrument->lot_size ?? 1;
            $profitLoss = ($exitPrice - $entryPrice) * $quantity;

            Log::info("✅ {$optionSymbol}: Entry={$entryPrice}, Exit (Day's High AFTER signal)={$exitPrice}, P/L={$profitLoss}");

            return [
                'option_symbol' => $optionSymbol,
                'signal_time' => $signalTime->format('Y-m-d H:i:s'),
                'exit_time' => $exitDateTime->format('Y-m-d H:i:s'),
                'entry_price' => round($entryPrice, 2),
                'exit_price' => round($exitPrice, 2),
                'quantity' => $quantity,
                'investment' => round($entryPrice * $quantity, 2),
                'profit_loss' => round($profitLoss, 2),
                'profit_loss_per_lot' => round($exitPrice - $entryPrice, 2),
                'return_percent' => $entryPrice > 0 ? round((($exitPrice - $entryPrice) / $entryPrice) * 100, 2) : 0
            ];

        } catch (\Exception $e) {
            Log::error("Error processing signal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate exit datetime (next day 10:30 AM, skip weekends)
     * IDENTICAL TO FUTURES VERSION
     */
    private function calculateExitDateTime($signalTime)
    {
        $exitDate = $signalTime->copy()->addDay()->setTime(10, 30, 0);
        
        // Skip Saturday (6) and Sunday (0)
        while ($exitDate->dayOfWeek == 0 || $exitDate->dayOfWeek == 6) {
            $exitDate->addDay();
        }
        
        return $exitDate;
    }

    /**
     * Get historical price at specific time
     * IDENTICAL TO FUTURES VERSION
     */
    private function getHistoricalPrice($instrument, $datetime)
    {
        try {
            // Check cache first
            $cached = OptionPriceCache::where('trading_symbol', $instrument->trading_symbol)
                ->where('price_datetime', $datetime)
                ->first();

            if ($cached) {
                Log::info("📦 Cache HIT: {$instrument->trading_symbol} at {$datetime}");
                return $cached->price;
            }

            Log::info("🔍 Cache MISS: Fetching from Kite API...");

            // Fetch from Kite API
            $price = $this->fetchPriceFromKite($instrument, $datetime);

            if ($price) {
                // Cache the price
                OptionPriceCache::updateOrCreate(
                    [
                        'trading_symbol' => $instrument->trading_symbol,
                        'price_datetime' => $datetime
                    ],
                    [
                        'instrument_token' => $instrument->instrument_token,
                        'price' => $price,
                        'cached_at' => now()
                    ]
                );
            }

            return $price;

        } catch (\Exception $e) {
            Log::error("Error getting historical price: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get exit price (10:30 AM next day or current LTP if future date)
     * IDENTICAL TO FUTURES VERSION
     */
    // private function getExitPrice($instrument, $exitDateTime)
    // {
    //     try {
    //         $now = Carbon::now();

    //         // If exit time is in the future, get current LTP
    //         if ($exitDateTime->isFuture()) {
    //             Log::info("📍 Exit time is future, getting current LTP");
    //             return $this->getCurrentLTP($instrument);
    //         }

    //         // Otherwise get historical price
    //         return $this->getHistoricalPrice($instrument, $exitDateTime);

    //     } catch (\Exception $e) {
    //         Log::error("Error getting exit price: " . $e->getMessage());
    //         return null;
    //     }
    // }

    /**
     * Get exit price - now returns the day's HIGH price AFTER signal time
     */
    private function getExitPrice($instrument, $exitDateTime, $signalTime = null)
    {
        try {
            $now = Carbon::now();

            // If exit time is in the future (including today), get current day's high so far AFTER signal time
            if ($exitDateTime->isFuture() || $exitDateTime->isToday()) {
                Log::info("📍 Exit date is today or future, getting current day's high so far AFTER signal time");
                return $this->getDayHighPriceAfterSignal($instrument, now(), $signalTime);
            }

            // For past dates, get historical day's high AFTER signal time
            return $this->getHistoricalDayHighAfterSignal($instrument, $exitDateTime, $signalTime);

        } catch (\Exception $e) {
            Log::error("Error getting exit price: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get historical day's high price AFTER signal time from Kite API
     */
    private function getHistoricalDayHighAfterSignal($instrument, $date, $signalTime = null)
    {
        try {
            $cacheKey = "day_high_after_signal_{$instrument->trading_symbol}_{$date->format('Y-m-d')}_" . ($signalTime ? $signalTime->format('H-i') : 'full');
            $cached = Cache::get($cacheKey);
            
            if ($cached) {
                Log::info("📦 Day High (After Signal) Cache HIT: {$instrument->trading_symbol} for {$date->format('Y-m-d')}: {$cached}");
                return $cached;
            }

            Log::info("🔍 Fetching day's high AFTER signal for {$instrument->trading_symbol} on {$date->format('Y-m-d')}");

            if (!$this->kite) {
                Log::error("Kite not initialized");
                return null;
            }

            // Set time range: from signal time (or 9:15 if no signal time) to 3:30 PM
            $fromDate = $signalTime 
                ? Carbon::parse($date->format('Y-m-d') . ' ' . $signalTime->format('H:i:s'))
                : $date->copy()->setTime(9, 15, 0);
                
            $toDate = $date->copy()->setTime(15, 30, 0);
            
            // If signal time is after market close, return null (no trading after signal)
            if ($fromDate->gte($toDate)) {
                Log::info("⏰ Signal time is after market close, no exit price available");
                return null;
            }
            
            Log::info("📅 Fetching candles from signal time: {$fromDate->format('Y-m-d H:i:s')} to {$toDate->format('Y-m-d H:i:s')}");

            // Fetch minute candles to get high AFTER signal time
            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token,
                '5minute',
                $fromDate->format('Y-m-d H:i:s'),
                $toDate->format('Y-m-d H:i:s')
            );

            $dayHighAfterSignal = $this->parseHighFromCandlesAfterSignal($response, $instrument, $fromDate);

            if ($dayHighAfterSignal) {
                // Cache for 7 days
                Cache::put($cacheKey, $dayHighAfterSignal, now()->addDays(7));
                Log::info("✅ Day's high AFTER signal found: {$dayHighAfterSignal} for {$instrument->trading_symbol}");
                return $dayHighAfterSignal;
            }

            Log::warning("⚠️ No high price found after signal time");
            return null;

        } catch (\Exception $e) {
            Log::error("Error getting historical day high after signal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse high price from candles AFTER signal time
     */
    private function parseHighFromCandlesAfterSignal($response, $instrument, $signalTime)
    {
        $candles = [];
        
        if (is_object($response)) {
            if (isset($response->candles)) {
                $candles = $response->candles;
            } else {
                $candles = (array) $response;
            }
        } elseif (is_array($response)) {
            $candles = $response;
        }

        if (empty($candles)) {
            Log::warning("No candles returned for {$instrument->trading_symbol} after signal time");
            return null;
        }

        $highAfterSignal = 0;
        $signalTimestamp = $signalTime->timestamp;
        
        foreach ($candles as $candle) {
            if (is_object($candle)) {
                $candle = (array) $candle;
            }
            
            // Get candle timestamp
            $candleTime = null;
            if (isset($candle['date'])) {
                if ($candle['date'] instanceof \DateTime) {
                    $candleTime = $candle['date']->getTimestamp();
                } elseif (is_string($candle['date'])) {
                    $candleTime = strtotime($candle['date']);
                } elseif (is_numeric($candle['date'])) {
                    $candleTime = $candle['date'];
                }
            }
            
            // Only consider candles AFTER signal time
            if ($candleTime && $candleTime >= $signalTimestamp) {
                // For array format [date, open, high, low, close]
                if (isset($candle[2]) && $candle[2] > $highAfterSignal) {
                    $highAfterSignal = $candle[2];
                }
                
                // For object/named format
                if (isset($candle['high']) && $candle['high'] > $highAfterSignal) {
                    $highAfterSignal = $candle['high'];
                }
            }
        }

        return $highAfterSignal > 0 ? $highAfterSignal : null;
    }

    /**
     * Get current day's high price AFTER signal time
     */
    private function getDayHighPriceAfterSignal($instrument, $datetime, $signalTime = null)
    {
        try {
            $quoteKey = "NFO:" . $instrument->trading_symbol;
            $quotes = $this->kite->getQuote([$quoteKey]);

            $quotesArray = json_decode(json_encode($quotes), true);
            
            // If we have real-time intraday candles in quote, use those
            if (isset($quotesArray[$quoteKey]['depth']['buy']) || isset($quotesArray[$quoteKey]['ohlc'])) {
                // For current day with quotes, we need to get intraday candles after signal time
                Log::info("🔍 Getting intraday candles after signal time for current day");
                return $this->getDayHighFromIntradayCandlesAfterSignal($instrument, $datetime, $signalTime);
            }

            // Fallback to current LTP if we can't get intraday data
            Log::info("🔄 Using current LTP as fallback");
            return $this->getCurrentLTP($instrument);

        } catch (\Exception $e) {
            Log::error("Error getting current day high after signal: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get day high from intraday candles AFTER signal time
     */
    private function getDayHighFromIntradayCandlesAfterSignal($instrument, $datetime, $signalTime = null)
    {
        try {
            // Start from signal time or market open
            $todayStart = $signalTime 
                ? Carbon::parse($datetime->format('Y-m-d') . ' ' . $signalTime->format('H:i:s'))
                : $datetime->copy()->setTime(9, 15, 0);
            
            // Ensure we don't start after current time
            if ($todayStart->gt($datetime)) {
                Log::info("⏰ Signal time is in the future, using current time");
                $todayStart = $datetime->copy()->subMinutes(5);
            }
            
            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token,
                '5minute',
                $todayStart->format('Y-m-d H:i:s'),
                $datetime->format('Y-m-d H:i:s')
            );

            $candles = [];
            if (is_object($response)) {
                $candles = isset($response->candles) ? $response->candles : (array) $response;
            } elseif (is_array($response)) {
                $candles = $response;
            }

            if (empty($candles)) {
                Log::warning("No intraday candles for today after signal time");
                return $this->getCurrentLTP($instrument);
            }

            $signalTimestamp = $todayStart->timestamp;
            $dayHighAfterSignal = 0;
            
            foreach ($candles as $candle) {
                if (is_object($candle)) {
                    $candle = (array) $candle;
                }
                
                // Get candle time to ensure it's after signal
                $candleTime = null;
                if (isset($candle['date'])) {
                    if ($candle['date'] instanceof \DateTime) {
                        $candleTime = $candle['date']->getTimestamp();
                    } elseif (is_string($candle['date'])) {
                        $candleTime = strtotime($candle['date']);
                    }
                }
                
                // Skip if candle is before signal time
                if ($candleTime && $candleTime < $signalTimestamp) {
                    continue;
                }
                
                if (isset($candle[2]) && $candle[2] > $dayHighAfterSignal) {
                    $dayHighAfterSignal = $candle[2];
                } elseif (isset($candle['high']) && $candle['high'] > $dayHighAfterSignal) {
                    $dayHighAfterSignal = $candle['high'];
                }
            }

            return $dayHighAfterSignal > 0 ? $dayHighAfterSignal : $this->getCurrentLTP($instrument);

        } catch (\Exception $e) {
            Log::error("Error getting intraday day high after signal: " . $e->getMessage());
            return $this->getCurrentLTP($instrument);
        }
    }

    /**
     * Fetch price from Kite API (historical candle data)
     * IDENTICAL TO FUTURES VERSION
     */
    private function fetchPriceFromKite($instrument, $datetime)
    {
        try {
            if (!$this->kite) {
                Log::error("Kite not initialized");
                return null;
            }

            $fromDate = $datetime->copy()->subMinutes(30)->format('Y-m-d H:i:s');
            $toDate = $datetime->copy()->addMinutes(30)->format('Y-m-d H:i:s');

            Log::info("🔍 Fetching candles for {$instrument->trading_symbol}");
            Log::info("📅 Time range: {$fromDate} to {$toDate}");

            // Fetch historical candle data
            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token,
                '5minute',
                $fromDate,
                $toDate
            );

            // Handle the response properly
            $candles = [];
            
            if (is_object($response)) {
                if (isset($response->candles)) {
                    $candles = $response->candles;
                } else {
                    $candles = (array) $response;
                }
            } elseif (is_array($response)) {
                $candles = $response;
            }

            Log::info("📊 Received " . count($candles) . " candles");

            if (empty($candles)) {
                Log::warning("No candles returned for {$instrument->trading_symbol}");
                return $this->getCurrentLTP($instrument);
            }

            if (isset($candles[0])) {
                Log::info("🔬 First candle: " . json_encode($candles[0]));
            }

            // Find closest candle
            $targetTimestamp = $datetime->timestamp;
            $closestCandle = null;
            $minDiff = PHP_INT_MAX;

            foreach ($candles as $index => $candle) {
                if (is_object($candle)) {
                    $candle = (array) $candle;
                }
                
                $candleTime = null;
                
                if (isset($candle['date'])) {
                    if ($candle['date'] instanceof \DateTime) {
                        $candleTime = $candle['date']->getTimestamp();
                    } elseif (is_object($candle['date']) && method_exists($candle['date'], 'getTimestamp')) {
                        $candleTime = $candle['date']->getTimestamp();
                    } elseif (is_string($candle['date'])) {
                        $candleTime = strtotime($candle['date']);
                    } elseif (is_numeric($candle['date'])) {
                        $candleTime = $candle['date'];
                    }
                }
                
                if ($candleTime === null) {
                    Log::warning("⚠️ Could not parse candle date at index {$index}");
                    continue;
                }
                
                $diff = abs($candleTime - $targetTimestamp);
                
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $closestCandle = $candle;
                }
            }

            if ($closestCandle) {
                if (is_object($closestCandle)) {
                    $closestCandle = (array) $closestCandle;
                }
                
                $price = $closestCandle['close'] ?? null;
                
                if ($price === null) {
                    Log::error("❌ Close price not found in candle");
                    return $this->getCurrentLTP($instrument);
                }
                
                $candleDateStr = 'Unknown';
                if (isset($closestCandle['date'])) {
                    if ($closestCandle['date'] instanceof \DateTime) {
                        $candleDateStr = $closestCandle['date']->format('Y-m-d H:i:s');
                    } elseif (is_string($closestCandle['date'])) {
                        $candleDateStr = $closestCandle['date'];
                    }
                }
                
                Log::info("✅ Found price {$price} for {$instrument->trading_symbol}");
                Log::info("📍 Closest candle at {$candleDateStr} (diff: {$minDiff} seconds)");
                
                return $price;
            }

            Log::warning("❌ No suitable candle found, using LTP fallback");
            return $this->getCurrentLTP($instrument);

        } catch (\Exception $e) {
            Log::error("🚨 Kite API Error: " . $e->getMessage());
            
            try {
                Log::info("🔄 Attempting LTP fallback...");
                return $this->getCurrentLTP($instrument);
            } catch (\Exception $e2) {
                Log::error("❌ Fallback LTP failed: " . $e2->getMessage());
                return null;
            }
        }
    }

    /**
     * Get current LTP from Kite
     * IDENTICAL TO FUTURES VERSION
     */
    private function getCurrentLTP($instrument)
    {
        try {
            if (!$this->kite) {
                Log::error("Kite not initialized");
                return null;
            }

            $quoteKey = "NFO:" . $instrument->trading_symbol;
            $quotes = $this->kite->getQuote([$quoteKey]);

            $quotesArray = json_decode(json_encode($quotes), true);

            if (isset($quotesArray[$quoteKey]['last_price'])) {
                $ltp = $quotesArray[$quoteKey]['last_price'];
                Log::info("✅ Got LTP {$ltp} for {$instrument->trading_symbol}");
                return $ltp;
            }

            if (is_object($quotes) && isset($quotes->$quoteKey)) {
                $quoteData = $quotes->$quoteKey;
                if (isset($quoteData->last_price)) {
                    $ltp = $quoteData->last_price;
                    Log::info("✅ Got LTP {$ltp} for {$instrument->trading_symbol}");
                    return $ltp;
                }
            }

            Log::warning("Could not extract LTP from quote");
            return null;

        } catch (\Exception $e) {
            Log::error("Error getting LTP: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize Kite Connect using BrokerApi model
     * THIS IS THE ONLY DIFFERENCE FROM FUTURES VERSION
     */
    private function initializeKite()
    {
        try {
            // Get broker API credentials from database
            $brokerApi = BrokerApi::where('account_user_name', $this->userId)
                ->where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->where('token_expires_at', '>', now())
                ->first();

            if (!$brokerApi) {
                throw new \Exception('Valid Zerodha broker API not found for user ' . $this->userId);
            }

            if (!$brokerApi->api_key || !$brokerApi->access_token) {
                throw new \Exception('API credentials not configured');
            }

            $this->kite = new KiteConnect($brokerApi->api_key);
            $this->kite->setAccessToken($brokerApi->access_token);
            
            Log::info("✅ Kite Connect initialized using BrokerApi for user: {$this->userId}");

        } catch (\Exception $e) {
            Log::error("Failed to initialize Kite: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Store access token for broker API
     */
    public function storeAccessToken(Request $request)
    {
        $accessToken = $request->input('access_token');
        
        if ($accessToken) {
            $brokerApi = BrokerApi::where('account_user_name', $this->userId)
                ->where('client_type', 'Zerodha')
                ->first();
            
            if ($brokerApi) {
                $brokerApi->update([
                    'access_token' => $accessToken,
                    'token_expires_at' => now()->addHours(23),
                    'is_token_valid' => true
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Access token updated successfully',
                    'expires_at' => now()->addHours(23)->toDateTimeString()
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Broker API not found for user'
            ], 404);
        }

        return response()->json([
            'success' => false,
            'message' => 'Access token is required'
        ], 400);
    }
}