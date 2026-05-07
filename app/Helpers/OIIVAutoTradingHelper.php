<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\OptionStrike;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class OIIVAutoTradingHelper
{
    // ===== 🔧 CONFIGURABLE TIMING SETTINGS =====
    const CLOSE_TIME_HOUR = 15;      // 3 PM (24-hour format)
    const CLOSE_TIME_MINUTE = 0;     // ⬅️ CHANGE: 15 → 0 minutes (3:00 PM)
    const OPEN_TIME_HOUR = 9;        // 9 AM (24-hour format)  ⬅️ ADD THIS
    const OPEN_TIME_MINUTE = 30;     // ⬅️ ADD THIS (9:30 AM)
    const LOCK_WINDOW_SECONDS = 90;  // 90 seconds window to capture price
    
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Main process - Detect Price + OI signals and create orders
     *
     * NEW LOGIC (Price Movement + OI Change):
     * - Price Up + OI -ve = Short Covering (Bullish) → BUY CE
     * - Price Up + OI +ve = Long Buildup (Bullish) → BUY CE
     * - Price Down + OI -ve = Long Unwinding (Bearish) → BUY PE
     * - Price Down + OI +ve = Short Buildup (Bearish) → BUY PE
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting Price + OI Auto Trading Signal Detection ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            Log::info('Close Lock Time: ' . self::CLOSE_TIME_HOUR . ':' . self::CLOSE_TIME_MINUTE);

            $configs = OIIVAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active configs");

            // Get today's date
            $currentDate = $testDate 
                ? Carbon::parse($testDate)->format('Y-m-d') 
                : Carbon::today()->format('Y-m-d');

            Log::info("📅 Processing date: {$currentDate}");

            // Get all FUT records (base data)
            $futRecords = OptionStrike::where('strike_position', 'FUT')
                ->where('trading_date', $currentDate)
                ->get();

            if ($futRecords->isEmpty()) {
                Log::info('No FUT data found for today');
                return;
            }

            Log::info("✅ Found {$futRecords->count()} symbols to analyze");

            // Group by broker
            $recordsByBroker = $futRecords->groupBy('broker_api_id');

            foreach ($recordsByBroker as $brokerId => $brokerRecords) {
                $broker = BrokerApi::find($brokerId);

                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} has invalid token, skipping");
                    continue;
                }

                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log::info("Processing {$brokerRecords->count()} symbols for broker: {$broker->client_name}");

                // Initialize Kite instance for this broker
                if (!isset($this->kiteInstances[$broker->id])) {
                    $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                    $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
                }

                foreach ($brokerRecords as $futRecord) {
                    $config = $this->findConfigForBroker($configs, $broker);
                    
                    if ($config) {
                        $this->analyzeAndCreateOrder($config, $futRecord, $broker, $currentDate);
                    } else {
                        Log::debug("No config found for broker: {$broker->id}");
                    }
                }
            }

            Log::info('=== Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('Price+OI Signal Processing Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Find matching config for broker
     */
    private function findConfigForBroker($configs, BrokerApi $broker)
    {
        return $configs->first(function($config) use ($broker) {
            return $config->broker_api_id === $broker->id;
        });
    }

    /**
     * Analyze Price + OI signals and create order
     *
     * FIXED CANDLE LOGIC:
     * 1. Lock close price at configured time (3:00 PM by default)
     * 2. Compare: OPEN (9:30 AM) vs CLOSE (3:00 PM)
     * 3. Get OI change %
     * 4. Determine scenario and place order
     * 5. All subsequent runs use SAME locked price → consistent results
     */
    // private function analyzeAndCreateOrder(
    //     OIIVAutoConfig $config, 
    //     $futRecord, 
    //     BrokerApi $broker, 
    //     string $date
    // ) {
    //     try {
    //         $symbol = $futRecord->underlying_symbol;
    //         Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    //         Log::info("🔍 [ANALYZE] {$symbol}");

    //         // ===== 🔒 GET OR LOCK CLOSE PRICE =====
    //         $now = now('Asia/Kolkata');
    //         $lockStart = Carbon::parse(
    //             $date . ' ' . self::CLOSE_TIME_HOUR . ':' . self::CLOSE_TIME_MINUTE . ':00',
    //             'Asia/Kolkata'
    //         );
            
    //         // Check if price is already locked
    //         if (!$futRecord->close_315_price) {
    //             // ✅ FIXED: Fetch if we're anywhere in 3:00-3:45 PM range (not just 90-second window)
    //             $lockEndExtended = $lockStart->copy()->addMinutes(30); // 3:45 PM buffer
                
    //             if ($now->greaterThanOrEqualTo($lockStart) && $now->lessThan($lockEndExtended)) {
    //                 Log::info("  🔒 [FETCH] Fetching " . self::CLOSE_TIME_HOUR . ":" . self::CLOSE_TIME_MINUTE . " close price...");
                    
    //                 $kite = $this->kiteInstances[$broker->id];
    //                 // $closePrice = $this->getCurrentPrice($kite, $futRecord->trading_symbol);
    //                 $closePrice = $this->get315HistoricalPrice(
    //                     $kite, 
    //                     $futRecord->instrument_token,
    //                     $futRecord->trading_symbol,
    //                     $date
    //                 );
                    
    //                 if ($closePrice) {
    //                     // ✅ Lock with 3:00 PM timestamp (not current time)
    //                     $futRecord->update([
    //                         'close_315_price' => $closePrice,
    //                         'close_315_locked_at' => $lockStart
    //                     ]);
                        
    //                     // Refresh model to get updated value
    //                     $futRecord->refresh();
                        
    //                     Log::info("  ✅ LOCKED " . self::CLOSE_TIME_HOUR . ":" . self::CLOSE_TIME_MINUTE . " close: ₹{$closePrice}");
    //                 } else {
    //                     Log::error("  ❌ Failed to fetch close price from Zerodha");
    //                     return;
    //                 }
    //             } else {
    //                 $lockTimeStr = self::CLOSE_TIME_HOUR . ':' . self::CLOSE_TIME_MINUTE;
                    
    //                 if ($now->lessThan($lockStart)) {
    //                     Log::info("  ⏳ Before {$lockTimeStr} - waiting... (Current: {$now->format('H:i:s')})");
    //                 } else {
    //                     Log::info("  ⏰ Past trading window for today");
    //                 }
                    
    //                 return;
    //             }
    //         }

    //         // ===== USE LOCKED CLOSE PRICE =====
    //         $currentPrice = $futRecord->close_315_price;
            
    //         if (!$currentPrice) {
    //             Log::error("  ❌ Close price still not available after fetch attempt");
    //             return;
    //         }

    //         Log::info("  📊 Using LOCKED " . self::CLOSE_TIME_HOUR . ":" . self::CLOSE_TIME_MINUTE . " close: ₹{$currentPrice}");

    //         // Get CE and PE data
    //         $ceData = OptionStrike::where('broker_api_id', $broker->id)
    //             ->where('underlying_symbol', $symbol)
    //             ->where('strike_position', 'CE_MERGED')
    //             ->where('trading_date', $date)
    //             ->first();

    //         $peData = OptionStrike::where('broker_api_id', $broker->id)
    //             ->where('underlying_symbol', $symbol)
    //             ->where('strike_position', 'PE_MERGED')
    //             ->where('trading_date', $date)
    //             ->first();

    //         if (!$ceData || !$peData) {
    //             Log::debug("  ⚠️ Missing CE/PE data for {$symbol}");
    //             return;
    //         }

    //         // Get opening price (9:30 AM)
    //         $openingPrice = $futRecord->open_price ?? $futRecord->spot_price;
            
    //         if (!$openingPrice) {
    //             Log::error("  ❌ Opening price not available for {$symbol}");
    //             return;
    //         }

    //         // Calculate price change (STABLE - uses locked price!)
    //         $priceChange = $currentPrice - $openingPrice;
    //         $priceChangePercent = (($priceChange / $openingPrice) * 100);

    //         // Get OI change %
    //         $oiChange = $futRecord->daily_oi_change_pct ?? 0;

    //         // Determine directions
    //         $priceDirection = $priceChange > 0 ? 'UP' : ($priceChange < 0 ? 'DOWN' : 'FLAT');
    //         $oiDirection = $oiChange > 0 ? 'POSITIVE' : ($oiChange < 0 ? 'NEGATIVE' : 'FLAT');

    //         Log::info("  Opening Price (9:30 AM): ₹{$openingPrice}");
    //         Log::info("  Close Price (" . self::CLOSE_TIME_HOUR . ":" . self::CLOSE_TIME_MINUTE . "): ₹{$currentPrice}");
    //         Log::info("  Price Change: ₹{$priceChange} (" . number_format($priceChangePercent, 2) . "%) → {$priceDirection}");
    //         Log::info("  OI Change: {$oiChange}% → {$oiDirection}");

    //         // Skip if no clear direction
    //         if ($priceDirection === 'FLAT') {
    //             Log::debug("  ⚠️ Skipping {$symbol} - No price movement");
    //             return;
    //         }

    //         // Determine signal
    //         $signal = $this->determineSignal($priceDirection, $oiDirection, $priceChange, $oiChange);
            
    //         if (!$signal) {
    //             Log::debug("  ⚠️ No signal generated for {$symbol}");
    //             return;
    //         }

    //         // ===== 🛡️ DUPLICATE CHECK =====
    //         $anyOrderToday = OIIVAutoOrder::where('broker_api_id', $broker->id)
    //             ->where('symbol', $futRecord->underlying_symbol)
    //             ->whereDate('signal_detected_at', $date)
    //             ->where('status', true)
    //             ->first();

    //         if ($anyOrderToday) {
    //             Log::info("  ⚠️ Order already exists for {$symbol} today - ID: {$anyOrderToday->id} ({$anyOrderToday->option_type})");
    //             return;
    //         }

    //         // Create order
    //         $this->createOrder($config, $futRecord, $ceData, $peData, $broker, $signal, $currentPrice, $openingPrice);

    //     } catch (\Exception $e) {
    //         Log::error("❌ [ANALYZE] Error for {$symbol}: " . $e->getMessage());
    //     }
    // }

    /**
     * Analyze Price + OI signals and create order
     *
     * COMPLETE FIXED CANDLE LOGIC:
     * 1. Lock OPEN price at 9:30 AM (first run after 9:30)
     * 2. Lock CLOSE price at 3:00 PM (first run after 3:00)
     * 3. Compare: OPEN (9:30 AM) vs CLOSE (3:00 PM)
     * 4. Get OI change %
     * 5. Determine scenario and place order
     * 6. All subsequent runs use SAME locked prices → consistent results
     */
    private function analyzeAndCreateOrder(
        OIIVAutoConfig $config, 
        $futRecord, 
        BrokerApi $broker, 
        string $date
    ) {
        try {
            $symbol = $futRecord->underlying_symbol;
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [ANALYZE] {$symbol}");

            $now = now('Asia/Kolkata');
            $kite = $this->kiteInstances[$broker->id];
            
            // ===== 🔒 STEP 1: LOCK 9:30 AM OPEN PRICE (if needed) =====
            if (!$futRecord->open_915_price) {
                $open915Time = Carbon::parse($date . ' 09:30:00', 'Asia/Kolkata');
                $open915EndWindow = $open915Time->copy()->addMinutes(30); // 9:45 AM buffer
                
                // Only fetch if we're in the window (9:30 AM - 9:45 AM)
                if ($now->greaterThanOrEqualTo($open915Time) && $now->lessThan($open915EndWindow)) {
                    Log::info("  🔒 [FETCH] Fetching 9:30 AM HISTORICAL open price...");
                    
                    $openPrice = $this->get930HistoricalPrice(
                        $kite,
                        $futRecord->instrument_token,
                        $futRecord->trading_symbol,
                        $date
                    );
                    
                    if ($openPrice && $openPrice > 0) {
                        $futRecord->update([
                            'open_915_price' => $openPrice,
                            'open_915_locked_at' => $open915Time
                        ]);
                        $futRecord->refresh();
                        
                        Log::info("  ✅ LOCKED 9:30 AM open: ₹{$openPrice}");
                    } else {
                        Log::error("  ❌ Failed to fetch 9:30 AM open price");
                    }
                }
            }

            // ===== 🔒 STEP 2: LOCK 3:00 PM CLOSE PRICE (if needed) =====
            $lockStart = Carbon::parse(
                $date . ' ' . self::CLOSE_TIME_HOUR . ':' . self::CLOSE_TIME_MINUTE . ':00',
                'Asia/Kolkata'
            );
            
            if (!$futRecord->close_315_price) {
                $lockEndExtended = $lockStart->copy()->addMinutes(30); // 3:45 PM buffer
                
                if ($now->greaterThanOrEqualTo($lockStart) && $now->lessThan($lockEndExtended)) {
                    Log::info("  🔒 [FETCH] Fetching 3:00 PM HISTORICAL close price...");
                    
                    $closePrice = $this->get300HistoricalPrice(
                        $kite,
                        $futRecord->instrument_token,
                        $futRecord->trading_symbol,
                        $date
                    );
                    
                    if ($closePrice && $closePrice > 0) {
                        $futRecord->update([
                            'close_315_price' => $closePrice,
                            'close_315_locked_at' => $lockStart
                        ]);
                        $futRecord->refresh();
                        
                        Log::info("  ✅ LOCKED 3:00 PM close: ₹{$closePrice}");
                    } else {
                        Log::error("  ❌ Failed to fetch 3:00 PM close price");
                        return;
                    }
                } else {
                    $lockTimeStr = self::CLOSE_TIME_HOUR . ':' . self::CLOSE_TIME_MINUTE;
                    
                    if ($now->lessThan($lockStart)) {
                        Log::info("  ⏳ Before {$lockTimeStr} - waiting... (Current: {$now->format('H:i:s')})");
                    } else {
                        Log::info("  ⏰ Past trading window (after 3:45 PM)");
                    }
                    return;
                }
            }

            // ===== ✅ STEP 3: USE LOCKED PRICES =====
            // Priority: open_915_price > open_price > spot_price
            $openingPrice = $futRecord->open_915_price 
                ?? $futRecord->open_price 
                ?? $futRecord->spot_price;
            
            $currentPrice = $futRecord->close_315_price;
            
            if (!$currentPrice) {
                Log::error("  ❌ Close price (3:00 PM) still not available");
                return;
            }
            
            if (!$openingPrice) {
                Log::error("  ❌ Opening price (9:30 AM) not available");
                return;
            }

            Log::info("  📊 Using LOCKED prices:");
            Log::info("  Opening (9:30 AM): ₹{$openingPrice}");
            Log::info("  Close (3:00 PM): ₹{$currentPrice}");

            // ===== ✅ STEP 4: GET CE AND PE DATA =====
            $ceData = OptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $symbol)
                ->where('strike_position', 'CE_MERGED')
                ->where('trading_date', $date)
                ->first();

            $peData = OptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $symbol)
                ->where('strike_position', 'PE_MERGED')
                ->where('trading_date', $date)
                ->first();

            if (!$ceData || !$peData) {
                Log::debug("  ⚠️ Missing CE/PE data for {$symbol}");
                return;
            }

            // ===== ✅ STEP 5: CALCULATE PRICE CHANGE =====
            $priceChange = $currentPrice - $openingPrice;
            $priceChangePercent = (($priceChange / $openingPrice) * 100);

            // Get OI change %
            $oiChange = $futRecord->daily_oi_change_pct ?? 0;

            // Determine directions
            $priceDirection = $priceChange > 0 ? 'UP' : ($priceChange < 0 ? 'DOWN' : 'FLAT');
            $oiDirection = $oiChange > 0 ? 'POSITIVE' : ($oiChange < 0 ? 'NEGATIVE' : 'FLAT');

            Log::info("  Opening Price (9:30 AM): ₹{$openingPrice}");
            Log::info("  Close Price (3:00 PM): ₹{$currentPrice}");
            Log::info("  Price Change: ₹{$priceChange} (" . number_format($priceChangePercent, 2) . "%) → {$priceDirection}");
            Log::info("  OI Change: {$oiChange}% → {$oiDirection}");

            // ===== ✅ STEP 6: SKIP IF NO CLEAR DIRECTION =====
            if ($priceDirection === 'FLAT') {
                Log::debug("  ⚠️ Skipping {$symbol} - No price movement");
                return;
            }

            // ===== ✅ STEP 7: DETERMINE SIGNAL =====
            $signal = $this->determineSignal($priceDirection, $oiDirection, $priceChange, $oiChange);
            
            if (!$signal) {
                Log::debug("  ⚠️ No signal generated for {$symbol}");
                return;
            }

            // ===== ✅ STEP 8: DUPLICATE CHECK =====
            $anyOrderToday = OIIVAutoOrder::where('broker_api_id', $broker->id)
                ->where('symbol', $futRecord->underlying_symbol)
                ->whereDate('signal_detected_at', $date)
                ->where('status', true)
                ->first();

            if ($anyOrderToday) {
                Log::info("  ⚠️ Order already exists for {$symbol} today - ID: {$anyOrderToday->id} ({$anyOrderToday->option_type})");
                return;
            }

            // ===== ✅ STEP 9: CREATE ORDER =====
            $this->createOrder($config, $futRecord, $ceData, $peData, $broker, $signal, $currentPrice, $openingPrice);

        } catch (\Exception $e) {
            Log::error("❌ [ANALYZE] Error for {$symbol}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * ✅ UPDATED: Get actual 9:30 AM historical open price
     * Uses Zerodha Historical Data API to fetch the exact 9:30 AM candle
     */
    private function get930HistoricalPrice($kite, $instrumentToken, $tradingSymbol, $date)
    {
        try {
            // Define time range: 9:25 AM to 9:31 AM
            $from = Carbon::parse($date . ' 09:25:00', 'Asia/Kolkata');
            $to = Carbon::parse($date . ' 09:31:00', 'Asia/Kolkata');
            
            Log::info("  📊 Fetching 9:30 AM historical data from {$from->format('H:i')} to {$to->format('H:i')}");
            
            // Fetch 1-minute candles
            $historicalData = $kite->getHistoricalData(
                $instrumentToken,
                '1minute',
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s')
            );
            
            if (empty($historicalData)) {
                Log::warning("  ⚠️ No historical data returned for 9:30 AM, checking current quote");
                
                // Fallback: try to get current quote
                $currentPrice = $this->getCurrentPrice($kite, $tradingSymbol);
                if ($currentPrice) {
                    Log::info("  📍 Using current price as fallback: ₹{$currentPrice}");
                    return $currentPrice;
                }
                
                return null;
            }
            
            // Find the 9:30 AM candle
            foreach ($historicalData as $candle) {
                $candleTime = Carbon::parse($candle['date'], 'Asia/Kolkata');
                
                // Look for candle at 9:30 AM
                if ($candleTime->format('H:i') === '09:30') {
                    $openPrice = $candle['open'];
                    Log::info("  ✅ Found 9:30 AM candle: Open={$openPrice}, High={$candle['high']}, Low={$candle['low']}, Close={$candle['close']}");
                    return $openPrice;
                }
            }
            
            // If exact 9:30 candle not found, get the first available candle after 9:30
            $firstCandle = null;
            foreach ($historicalData as $candle) {
                $candleTime = Carbon::parse($candle['date'], 'Asia/Kolkata');
                if ($candleTime->format('H:i') >= '09:30') {
                    $firstCandle = $candle;
                    break;
                }
            }
            
            if ($firstCandle) {
                $openPrice = $firstCandle['open'];
                $candleTime = Carbon::parse($firstCandle['date'])->format('H:i');
                Log::warning("  ⚠️ 9:30 AM candle not found exactly, using closest at {$candleTime}: Open={$openPrice}");
                return $openPrice;
            }
            
            // Final fallback: use first candle's open
            $veryFirstCandle = reset($historicalData);
            if ($veryFirstCandle) {
                $openPrice = $veryFirstCandle['open'];
                Log::warning("  ⚠️ Using first available candle: Open={$openPrice}");
                return $openPrice;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("  ❌ Error fetching historical 9:30 AM price: " . $e->getMessage());
            
            // Fallback to current price on error
            try {
                $currentPrice = $this->getCurrentPrice($kite, $tradingSymbol);
                if ($currentPrice) {
                    Log::warning("  ⚠️ Falling back to current price: ₹{$currentPrice}");
                    return $currentPrice;
                }
            } catch (\Exception $e2) {
                Log::error("  ❌ Fallback also failed: " . $e2->getMessage());
            }
            
            return null;
        }
    }

    /**
     * ✅ UPDATED: Get actual 3:00 PM historical close price
     * Uses Zerodha Historical Data API to fetch the exact 3:00 PM candle
     */
    private function get300HistoricalPrice($kite, $instrumentToken, $tradingSymbol, $date)
    {
        try {
            // Define time range: 2:55 PM to 3:01 PM
            $from = Carbon::parse($date . ' 14:55:00', 'Asia/Kolkata');
            $to = Carbon::parse($date . ' 15:01:00', 'Asia/Kolkata');
            
            Log::info("  📊 Fetching 3:00 PM historical data from {$from->format('H:i')} to {$to->format('H:i')}");
            
            // Fetch 1-minute candles
            $historicalData = $kite->getHistoricalData(
                $instrumentToken,
                '1minute',
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s')
            );
            
            if (empty($historicalData)) {
                Log::warning("  ⚠️ No historical data returned, trying live quote as fallback");
                return $this->getCurrentPrice($kite, $tradingSymbol);
            }
            
            // Find the 3:00 PM candle
            foreach ($historicalData as $candle) {
                $candleTime = Carbon::parse($candle['date'], 'Asia/Kolkata');
                
                // Look for candle at 3:00 PM
                if ($candleTime->format('H:i') === '15:00') {
                    $closePrice = $candle['close'];
                    Log::info("  ✅ Found 3:00 PM candle: Open={$candle['open']}, High={$candle['high']}, Low={$candle['low']}, Close={$closePrice}");
                    return $closePrice;
                }
            }
            
            // If exact 3:00 candle not found, get the last available candle before 3:01
            $lastCandle = null;
            foreach ($historicalData as $candle) {
                $candleTime = Carbon::parse($candle['date'], 'Asia/Kolkata');
                if ($candleTime->format('H:i') <= '15:00') {
                    $lastCandle = $candle;
                }
            }
            
            if ($lastCandle) {
                Log::warning("  ⚠️ 3:00 PM candle not found exactly, using closest: {$lastCandle['close']} at " . Carbon::parse($lastCandle['date'])->format('H:i'));
                return $lastCandle['close'];
            }
            
            // Final fallback: use live price
            Log::warning("  ⚠️ No suitable historical candles found, using live price as fallback");
            return $this->getCurrentPrice($kite, $tradingSymbol);
            
        } catch (\Exception $e) {
            Log::error("  ❌ Error fetching historical 3:00 PM price: " . $e->getMessage());
            
            // Fallback to live price on error
            Log::warning("  ⚠️ Falling back to live price due to error");
            return $this->getCurrentPrice($kite, $tradingSymbol);
        }
    }

    /**
     * Get current price from Zerodha API
     */
    private function getCurrentPrice($kite, $tradingSymbol)
    {
        try {
            $quoteKey = "NFO:" . $tradingSymbol;
            $quotes = $kite->getQuote([$quoteKey]);

            if (isset($quotes->$quoteKey->last_price)) {
                return $quotes->$quoteKey->last_price;
            }

            $quotesArray = json_decode(json_encode($quotes), true);
            
            if (isset($quotesArray[$quoteKey]['last_price'])) {
                return $quotesArray[$quoteKey]['last_price'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error("❌ Error fetching price for {$tradingSymbol}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Determine signal based on Price + OI logic
     */
    private function determineSignal($priceDirection, $oiDirection, $priceChange, $oiChange)
    {
        // SCENARIO 1: Price UP + OI NEGATIVE = Short Covering (Bullish) → BUY CE
        if ($priceDirection === 'UP' && $oiDirection === 'NEGATIVE') {
            Log::info("  ✅ SIGNAL: Short Covering (Bullish) → BUY CE");
            return [
                'type' => 'CE',
                'action' => 'BUY_CE',
                'reason' => 'Short Covering - Bullish',
                'scenario' => 'Price Up + OI Negative',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        // SCENARIO 2: Price UP + OI POSITIVE = Long Buildup (Bullish) → BUY CE
        if ($priceDirection === 'UP' && $oiDirection === 'POSITIVE') {
            Log::info("  ✅ SIGNAL: Long Buildup (Bullish) → BUY CE");
            return [
                'type' => 'CE',
                'action' => 'BUY_CE',
                'reason' => 'Long Buildup - Bullish',
                'scenario' => 'Price Up + OI Positive',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        // SCENARIO 3: Price DOWN + OI NEGATIVE = Long Unwinding (Bearish) → BUY PE
        if ($priceDirection === 'DOWN' && $oiDirection === 'NEGATIVE') {
            Log::info("  ✅ SIGNAL: Long Unwinding (Bearish) → BUY PE");
            return [
                'type' => 'PE',
                'action' => 'BUY_PE',
                'reason' => 'Long Unwinding - Bearish',
                'scenario' => 'Price Down + OI Negative',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        // SCENARIO 4: Price DOWN + OI POSITIVE = Short Buildup (Bearish) → BUY PE
        if ($priceDirection === 'DOWN' && $oiDirection === 'POSITIVE') {
            Log::info("  ✅ SIGNAL: Short Buildup (Bearish) → BUY PE");
            return [
                'type' => 'PE',
                'action' => 'BUY_PE',
                'reason' => 'Short Buildup - Bearish',
                'scenario' => 'Price Down + OI Positive',
                'price_change' => $priceChange,
                'oi_change' => $oiChange
            ];
        }

        return null;
    }

    /**
     * Create order entry
     */
    private function createOrder(
        OIIVAutoConfig $config,
        $futRecord,
        $ceData,
        $peData,
        BrokerApi $broker,
        array $signal,
        $currentPrice,
        $openingPrice
    ) {
        try {
            $optionType = $signal['type'];

            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("✅ [CREATE] Creating {$optionType} order for: {$futRecord->underlying_symbol}");

            // Get ATM option details
            $optionDetails = $this->getATMOption(
                $broker,
                $futRecord->trading_symbol,
                $optionType,
                $currentPrice,
                $config
            );

            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find ATM option");
                return;
            }

            // ✅ Check if this EXACT option already in order_book
            $orderBookExists = OrderBook::where('user_id', $config->user_id)
                ->where('trading_symbol', $optionDetails['symbol'])
                ->whereDate('order_datetime', $futRecord->trading_date)
                ->where('transaction_type', 'BUY')
                ->where('status', '!=', 'FAILED')
                ->exists();

            if ($orderBookExists) {
                Log::info("  ⚠️ {$optionType} order already in OrderBook: {$optionDetails['symbol']}");
                return;
            }

            $quantity = $config->getQuantityForSymbol($futRecord->trading_symbol);

            $orderData = [
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $broker->id,
                'symbol' => $futRecord->underlying_symbol,
                'trading_symbol' => $futRecord->trading_symbol,
                'instrument_token' => $futRecord->instrument_token,

                // Store signal data
                'btst_signal' => $signal['action'],
                'btst_confidence' => 100,
                'btst_reason' => $signal['reason'] . ' | ' . $signal['scenario'],
                'signal_detected_at' => now(),

                // Store Price + OI data
                'fut_oi_signal' => $signal['scenario'], // e.g., "Price Up + OI Negative"
                'fut_oi_strength' => $signal['reason'], // e.g., "Short Covering - Bullish"

                // Keep other data for reference
                'ce_oi_signal' => $ceData->direction ?? 'N/A',
                'pe_oi_signal' => $peData->direction ?? 'N/A',
                'ce_iv_signal' => 'N/A',
                'ce_iv_strength' => 'N/A',
                'pe_iv_signal' => 'N/A',
                'pe_iv_strength' => 'N/A',

                'spot_price' => $currentPrice,
                'option_symbol' => $optionDetails['symbol'],
                'option_token' => $optionDetails['token'],
                'option_type' => $optionType,
                'strike_price' => $optionDetails['strike'],
                'entry_price' => $optionDetails['ltp'],
                'current_price' => $optionDetails['ltp'],
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $quantity,
                'is_order_placed' => false,
                'status' => true
            ];

            $order = OIIVAutoOrder::create($orderData);

            Log::info("✅ [CREATE] Order created successfully!");
            Log::info("  Order ID: {$order->id}");
            Log::info("  Signal: {$signal['action']}");
            Log::info("  Scenario: {$signal['scenario']}");
            Log::info("  Reason: {$signal['reason']}");
            Log::info("  Price: ₹{$openingPrice} → ₹{$currentPrice} ({$signal['price_change']})");
            Log::info("  OI Change: {$signal['oi_change']}%");
            Log::info("  Option: {$optionDetails['symbol']}");
            Log::info("  Strike: {$optionDetails['strike']}");
            Log::info("  LTP: ₹{$optionDetails['ltp']}");
            Log::info("  Quantity: {$quantity} lots");

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
            Log::error("  Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get ATM option
     */
    private function getATMOption(
        BrokerApi $broker,
        $tradingSymbol,
        $optionType,
        $futurePrice,
        OIIVAutoConfig $config
    ) {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);

            $strikeIntervals = [
                'NIFTY' => 50,
                'BANKNIFTY' => 100,
                'FINNIFTY' => 50,
                'MIDCPNIFTY' => 25,
            ];

            $strikeInterval = $strikeIntervals[$baseSymbol] ?? 20;
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now())
                ->orderBy('expiry', 'ASC');

            $option = $query->first();

            if (!$option) {
                $query = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike])
                    ->orderBy('strike_diff', 'ASC')
                    ->orderBy('expiry', 'ASC');

                $option = $query->first();
            }

            if (!$option) {
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            return [
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'strike' => $option->strike,
                'ltp' => $ltp,
                'expiry' => $option->expiry
            ];

        } catch (\Exception $e) {
            Log::error("❌ [OPTION] Error: " . $e->getMessage());
            return null;
        }
    }

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, $tradingSymbol)
    {
        try {
            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];
            $quoteKey = "NFO:" . $tradingSymbol;
            $quotes = $kite->getQuote([$quoteKey]);

            if (isset($quotes->$quoteKey->last_price)) {
                return $quotes->$quoteKey->last_price;
            }

            $quotesArray = json_decode(json_encode($quotes), true);
            
            if (isset($quotesArray[$quoteKey]['last_price'])) {
                return $quotesArray[$quoteKey]['last_price'];
            }

            return 25.00;

        } catch (\Exception $e) {
            Log::error("❌ [LTP] Error: " . $e->getMessage());
            return 25.00;
        }
    }

    /**
     * Place pending orders
     */
    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== Starting Price+OI Order Placement ===');

            $pendingOrders = OIIVAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->whereHas('config', function($query) {
                    $query->where('status', true);
                })
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('No pending orders to place');
                return;
            }

            Log::info("Found {$pendingOrders->count()} pending orders");

            $ordersByBroker = $pendingOrders->groupBy('broker_api_id');

            foreach ($ordersByBroker as $brokerId => $orders) {
                $broker = BrokerApi::find($brokerId);

                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} has invalid token, skipping");
                    continue;
                }

                Log::info("Processing {$orders->count()} orders for broker: {$broker->client_name}");

                if (!isset($this->kiteInstances[$broker->id])) {
                    $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                    $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
                }

                foreach ($orders as $order) {
                    $this->placeOrder($order);
                }
            }

            Log::info('=== Order Placement Completed ===');

        } catch (\Exception $e) {
            Log::error('Order Placement Error: ' . $e->getMessage());
        }
    }

    private function placeOrder(OIIVAutoOrder $order)
    {
        try {
            Log::info("📤 [ORDER] Placing: {$order->option_symbol}");

            $broker = $order->broker;

            if (!$broker->hasValidToken()) {
                Log::error("❌ [ORDER] Invalid broker token");
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Invalid token");
                return;
            }

            if (!isset($this->kiteInstances[$broker->id])) {
                $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
                $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
            }

            $kite = $this->kiteInstances[$broker->id];

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();

            if (!$instrument) {
                Log::error("❌ [ORDER] Instrument not found");
                $this->saveFailedOrder($order, $order->quantity ?? 0, null, "Instrument not found");
                return;
            }

            $this->placeKiteOrder($order, $order->quantity, $instrument, $kite);

            $order->update([
                'is_order_placed' => true,
                'order_placed_at' => now()
            ]);

            Log::info("✅ [ORDER] Order processed: ID {$order->id}");

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $order->quantity ?? 0, null, $e->getMessage());
        }
    }

    private function placeKiteOrder(OIIVAutoOrder $order, $quantity, $instrument, $kite)
    {
        try {
            $price = null;

            if ($order->order_type == 'LIMIT') {
                $discount = ($order->entry_price * $order->config->disc_ltp) / 100;
                $price = $order->entry_price - $discount;
                $roundedPrice = round($price / $instrument->tick_size) * $instrument->tick_size;
                $price = number_format($roundedPrice, 2, '.', '');
            }

            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->trading_symbol);
            $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

            if ($freezeLimitLots && $quantity > $freezeLimitLots) {
                Log::info("🔄 [FREEZE] Splitting {$quantity} lots");
                $numOrders = ceil($quantity / $freezeLimitLots);
                $remainingLots = $quantity;

                for ($i = 0; $i < $numOrders; $i++) {
                    $lotsToPlace = min($freezeLimitLots, $remainingLots);
                    $this->executeSingleOrder($order, $lotsToPlace, $price, $instrument, $kite);
                    $remainingLots -= $lotsToPlace;
                    if ($i < $numOrders - 1) sleep(2);
                }
            } else {
                $this->executeSingleOrder($order, $quantity, $price, $instrument, $kite);
            }

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Kite Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $quantity, $price, $e->getMessage());
        }
    }

    private function executeSingleOrder($order, $quantity, $price, $instrument, $kite)
    {
        try {
            $orderParams = [
                'exchange' => 'NFO',
                'tradingsymbol' => $order->option_symbol,
                'transaction_type' => 'BUY',
                'quantity' => $quantity * $instrument->lot_size,
                'product' => $order->product,
                'validity' => 'DAY'
            ];

            if ($order->order_type == 'MARKET') {
                $orderParams['order_type'] = 'MARKET';
            } else {
                $orderParams['order_type'] = 'LIMIT';
                $orderParams['price'] = $price;
            }

            $result = $kite->placeOrder("regular", $orderParams);

            Log::info("✅ [ORDER] Placed! Order ID: {$result->order_id}");

            $this->saveToOrderBook($order, $result->order_id, $quantity, $price);

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Execution Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function saveToOrderBook(OIIVAutoOrder $order, $orderId, $quantity, $price)
    {
        try {
            sleep(2);

            $kite = $this->kiteInstances[$order->broker_api_id] ?? null;

            if (!$kite) {
                Log::error("Kite instance not found");
                return;
            }

            $orderHistory = $kite->getOrderHistory($orderId);
            $lastOrder = end($orderHistory);

            OrderBook::create([
                'user_id' => $order->user_id,
                'broker_username' => $order->broker->account_user_name,
                'order_id' => $orderId,
                'status' => $lastOrder->status ?? 'PENDING',
                'trading_symbol' => $order->option_symbol,
                'order_type' => $order->order_type,
                'transaction_type' => 'BUY',
                'product' => $order->product,
                'price' => $price ?? '-',
                'quantity' => $quantity,
                'status_message' => $lastOrder->status_message ?? 'Order placed',
                'order_datetime' => now(),
                'oiiv_auto_order_id' => $order->id
            ]);

            Log::info("✅ [ORDER_BOOK] Saved order {$orderId}");

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(OIIVAutoOrder $order, $quantity, $price, $error)
    {
        try {
            OrderBook::create([
                'user_id' => $order->user_id,
                'broker_username' => $order->broker->account_user_name ?? 'N/A',
                'order_id' => '-',
                'status' => 'FAILED',
                'trading_symbol' => $order->option_symbol,
                'order_type' => $order->order_type,
                'transaction_type' => 'BUY',
                'product' => $order->product,
                'price' => $price ?? '-',
                'quantity' => $quantity,
                'status_message' => substr($error, 0, 500),
                'order_datetime' => now(),
                'oiiv_auto_order_id' => $order->id
            ]);

            Log::info("❌ [ORDER_BOOK] Saved failed order");

        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }
}