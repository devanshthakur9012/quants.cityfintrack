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

class SymbolBacktestProfitController extends Controller
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

            // Calculate exit datetime (same day 3:15 PM or 3:30 PM)
            $exitDateTime = $this->calculateExitDateTime($signalTime);
            
            // Get exit price
            $exitPrice = $this->getExitPrice($instrument, $exitDateTime);
            
            if (!$exitPrice) {
                Log::warning("Could not get exit price for {$optionSymbol}");
                return null;
            }

            // Calculate P/L (assuming 1 lot = lot_size quantity)
            $quantity = $instrument->lot_size ?? 1;
            $profitLoss = ($exitPrice - $entryPrice) * $quantity;

            Log::info("✅ {$optionSymbol}: Entry={$entryPrice}, Exit={$exitPrice}, P/L={$profitLoss}");

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
     * Calculate exit datetime (same day 3:15 PM or 3:30 PM based on signal time)
     * If signal time is before or at 3:14 PM → exit at 3:15 PM same day
     * If signal time is after 3:14 PM → exit at 3:30 PM same day
     */
    private function calculateExitDateTime($signalTime)
    {
        $cutoffTime = $signalTime->copy()->setTime(15, 14, 59); // 3:14:59 PM
        
        if ($signalTime->lessThanOrEqualTo($cutoffTime)) {
            // Signal before or at 3:14 PM → exit at 3:15 PM same day
            $exitDate = $signalTime->copy()->setTime(15, 15, 0);
            Log::info("📍 Signal at {$signalTime->format('H:i:s')} → Exit at 3:15 PM same day");
        } else {
            // Signal after 3:14 PM → exit at 3:30 PM same day
            $exitDate = $signalTime->copy()->setTime(15, 30, 0);
            Log::info("📍 Signal at {$signalTime->format('H:i:s')} → Exit at 3:30 PM same day");
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
     * Get exit price (3:15 PM or 3:30 PM same day or current LTP if future date)
     */
    private function getExitPrice($instrument, $exitDateTime)
    {
        try {
            $now = Carbon::now();

            // If exit time is in the future, get current LTP
            if ($exitDateTime->isFuture()) {
                Log::info("📍 Exit time is future, getting current LTP");
                return $this->getCurrentLTP($instrument);
            }

            // Otherwise get historical price
            return $this->getHistoricalPrice($instrument, $exitDateTime);

        } catch (\Exception $e) {
            Log::error("Error getting exit price: " . $e->getMessage());
            return null;
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