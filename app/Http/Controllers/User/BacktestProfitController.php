<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OptionPriceCache;
use App\Models\ZerodhaInstrument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

class BacktestProfitController extends Controller
{
    private $kite;

    /**
     * Calculate profit/loss for backtested signals
     */
    public function calculateProfit(Request $request)
    {
        try {
            Log::info('=== BACKTEST PROFIT CALCULATION START ===');
            
            $signals = $request->input('signals', []);
            
            if (empty($signals)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No signals provided',
                    'data' => []
                ]);
            }

            Log::info('Processing ' . count($signals) . ' signals');

            // Initialize Kite Connect
            $this->initializeKite();

            // $results = [];
            // $totalProfit = 0;
            // $totalTrades = 0;
            // $winningTrades = 0;
            // $losingTrades = 0;

            $results = [];
            $totalProfit = 0;
            $totalInvestment = 0; // NEW: Track total investment
            $totalTrades = 0;
            $winningTrades = 0;
            $losingTrades = 0;

            foreach ($signals as $signal) {
                $result = $this->processSingleSignal($signal);
                
                // if ($result) {
                //     $results[] = $result;
                //     $totalProfit += $result['profit_loss'];
                //     $totalTrades++;
                    
                //     if ($result['profit_loss'] > 0) {
                //         $winningTrades++;
                //     } elseif ($result['profit_loss'] < 0) {
                //         $losingTrades++;
                //     }
                // }

                if ($result) {
                    $results[] = $result;
                    $totalProfit += $result['profit_loss'];
                    $totalInvestment += $result['investment']; // NEW: Add to total investment
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

            Log::info('=== BACKTEST PROFIT CALCULATION COMPLETE ===');
            Log::info("Total Trades: {$totalTrades}, Total P/L: {$totalProfit}, Win Rate: {$winRate}%");

            // return response()->json([
            //     'success' => true,
            //     'data' => $results,
            //     'summary' => [
            //         'total_trades' => $totalTrades,
            //         'winning_trades' => $winningTrades,
            //         'losing_trades' => $losingTrades,
            //         'win_rate' => $winRate,
            //         'total_profit_loss' => round($totalProfit, 2),
            //         'avg_profit_loss' => $avgProfit
            //     ],
            //     'message' => 'Profit calculation completed successfully'
            // ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total_trades' => $totalTrades,
                    'winning_trades' => $winningTrades,
                    'losing_trades' => $losingTrades,
                    'win_rate' => $winRate,
                    'total_investment' => round($totalInvestment, 2), // NEW: Total investment
                    'total_profit_loss' => round($totalProfit, 2),
                    'avg_profit_loss' => $avgProfit,
                    'roi_percent' => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0 // NEW: ROI %
                ],
                'message' => 'Profit calculation completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Profit Calculation Error: ' . $e->getMessage());
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

            // Calculate exit datetime (next day 10:30 AM)
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

            // return [
            //     'option_symbol' => $optionSymbol,
            //     'signal_time' => $signalTime->format('Y-m-d H:i:s'),
            //     'exit_time' => $exitDateTime->format('Y-m-d H:i:s'),
            //     'entry_price' => round($entryPrice, 2),
            //     'exit_price' => round($exitPrice, 2),
            //     'quantity' => $quantity,
            //     'profit_loss' => round($profitLoss, 2),
            //     'profit_loss_per_lot' => round($exitPrice - $entryPrice, 2),
            //     'return_percent' => $entryPrice > 0 ? round((($exitPrice - $entryPrice) / $entryPrice) * 100, 2) : 0
            // ];

            return [
                'option_symbol' => $optionSymbol,
                'signal_time' => $signalTime->format('Y-m-d H:i:s'),
                'exit_time' => $exitDateTime->format('Y-m-d H:i:s'),
                'entry_price' => round($entryPrice, 2),
                'exit_price' => round($exitPrice, 2),
                'quantity' => $quantity,
                'investment' => round($entryPrice * $quantity, 2), // NEW: Investment amount
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
     * Fetch price from Kite API (historical candle data) - FIXED
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

            // **FIX 1: Handle the response properly**
            // The response might be wrapped in an object with 'candles' property
            $candles = [];
            
            if (is_object($response)) {
                // Check if it has a 'candles' property
                if (isset($response->candles)) {
                    $candles = $response->candles;
                } else {
                    // Otherwise treat the whole response as candles array
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

            // Log first candle for debugging
            if (isset($candles[0])) {
                Log::info("🔬 First candle: " . json_encode($candles[0]));
            }

            // **FIX 2: Find closest candle with proper DateTime handling**
            $targetTimestamp = $datetime->timestamp;
            $closestCandle = null;
            $minDiff = PHP_INT_MAX;

            foreach ($candles as $index => $candle) {
                // Convert to array if it's an object
                if (is_object($candle)) {
                    $candle = (array) $candle;
                }
                
                // **FIX 3: Handle different date formats from Kite API**
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
                // Ensure it's an array
                if (is_object($closestCandle)) {
                    $closestCandle = (array) $closestCandle;
                }
                
                // Get the close price
                $price = $closestCandle['close'] ?? null;
                
                if ($price === null) {
                    Log::error("❌ Close price not found in candle");
                    return $this->getCurrentLTP($instrument);
                }
                
                // Format date for logging
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
                Log::info("📊 Candle: O={$closestCandle['open']}, H={$closestCandle['high']}, L={$closestCandle['low']}, C={$closestCandle['close']}");
                
                return $price;
            }

            Log::warning("❌ No suitable candle found, using LTP fallback");
            return $this->getCurrentLTP($instrument);

        } catch (\Exception $e) {
            Log::error("🚨 Kite API Error: " . $e->getMessage());
            Log::error("📍 Error at line: " . $e->getLine());
            Log::error("🔍 Full trace: " . $e->getTraceAsString());
            
            // Fallback to LTP
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

            // Convert to array
            $quotesArray = json_decode(json_encode($quotes), true);

            if (isset($quotesArray[$quoteKey]['last_price'])) {
                $ltp = $quotesArray[$quoteKey]['last_price'];
                Log::info("✅ Got LTP {$ltp} for {$instrument->trading_symbol}");
                return $ltp;
            }

            // Try object access
            if (is_object($quotes) && isset($quotes->$quoteKey)) {
                $quoteData = $quotes->$quoteKey;
                if (isset($quoteData->last_price)) {
                    $ltp = $quoteData->last_price;
                    Log::info("✅ Got LTP {$ltp} for {$instrument->trading_symbol}");
                    return $ltp;
                }
            }

            Log::warning("Could not extract LTP from quote");
            Log::debug("Quote response: " . json_encode($quotesArray));
            return null;

        } catch (\Exception $e) {
            Log::error("Error getting LTP: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize Kite Connect
     */
    private function initializeKite()
    {
        try {
            $apiKey = env('ZERODHA_API_KEY');
            $apiSecret = env('ZERODHA_API_SECRET');

            if (!$apiKey || !$apiSecret) {
                throw new \Exception('Zerodha credentials not configured');
            }

            $this->kite = new KiteConnect($apiKey);

            $accessToken = Cache::get('zerodha_access_token');

            if (!$accessToken) {
                throw new \Exception('Access token not available. Please login first.');
            }

            $this->kite->setAccessToken($accessToken);
            Log::info("✅ Kite Connect initialized");

        } catch (\Exception $e) {
            Log::error("Failed to initialize Kite: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Store access token manually
     */
    public function storeAccessToken(Request $request)
    {
        $accessToken = $request->input('access_token');
        
        if ($accessToken) {
            Cache::put('zerodha_access_token', $accessToken, now()->addHours(23));
            
            return response()->json([
                'success' => true,
                'message' => 'Access token stored successfully',
                'expires_at' => now()->addHours(23)->toDateTimeString()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Access token is required'
        ], 400);
    }
}