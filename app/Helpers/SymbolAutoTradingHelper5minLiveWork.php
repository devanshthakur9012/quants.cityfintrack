<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

class SymbolAutoTradingHelper5minLiveWork
{
    private $kiteInstances = [];

    const FREEZE_LIMITS = [
        'NIFTY' => 18,
        'BANKNIFTY' => 20,
        'FINNIFTY' => 24,
        'MIDCPNIFTY' => 24,
    ];

    /**
     * Main process - Detect signals and create orders
     */
    public function processSignals($testDate = null)
    {
        try {
            Log::info('=== Starting Symbol Auto Trading Signal Detection (5-Minute) ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = ZerodhaAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active configs");

            // Get all active monitored symbols for 5-minute interval
            $symbols = SymbolMonitored::where('is_active', true)
                ->where('interval', '5minute')
                ->get();

            if ($symbols->isEmpty()) {
                Log::info('No active 5-minute symbols found');
                return;
            }

            Log::info("✅ Found {$symbols->count()} active 5-minute symbols");

            // Group symbols by broker
            $symbolsByBroker = $symbols->groupBy('broker_api_id');

            foreach ($symbolsByBroker as $brokerId => $brokerSymbols) {
                $broker = BrokerApi::find($brokerId);
                
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("Broker {$brokerId} has invalid token, skipping");
                    continue;
                }

                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                Log::info("Processing {$brokerSymbols->count()} symbols for broker: {$broker->client_name}");

                foreach ($brokerSymbols as $symbol) {
                    Log::info("🔍 Checking symbol: {$symbol->trading_symbol} (Symbol: {$symbol->symbol})");
                    
                    $config = $this->findConfigForSymbol($configs, $symbol);
                    
                    if ($config) {
                        Log::info("   ✅ Config found: ID {$config->id}, Strategy: {$config->signal_strategy}");
                        $this->checkAndCreateOrder($config, $symbol, $broker, $testDate);
                    } else {
                        Log::warning("   ❌ NO CONFIG FOUND for user_id: {$symbol->user_id}");
                    }
                }
            }

            Log::info('=== Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('Signal Processing Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Find matching config for symbol
     */
    private function findConfigForSymbol($configs, SymbolMonitored $symbol)
    {
        // Match by user_id AND broker_api_id
        $config = $configs->first(function($config) use ($symbol) {
            return $config->user_id === $symbol->user_id 
                && $config->broker_api_id === $symbol->broker_api_id;
        });

        if ($config) {
            return $config;
        }

        // Fallback to broker_api_id only
        $config = $configs->first(function($config) use ($symbol) {
            return $config->broker_api_id === $symbol->broker_api_id;
        });

        if ($config) {
            Log::info("   ⚠️ Using broker-level config (user_id mismatch)");
            return $config;
        }

        return null;
    }

    /**
     * Get previous trading day's last candle
     */
    private function getPreviousDayLastCandle($brokerId, $tradingSymbol, $testDate = null)
    {
        try {
            $targetDate = $testDate ? Carbon::parse($testDate) : Carbon::today();

            $lastCandle = SymbolData::where('broker_api_id', $brokerId)
                ->where('trading_symbol', $tradingSymbol)
                ->where('interval', '5minute')
                ->where('timestamp', '<', $targetDate->format('Y-m-d 00:00:00'))
                ->whereNotNull('supertrend')
                ->whereNotNull('vwap')
                ->orderBy('timestamp', 'DESC')
                ->first();

            if ($lastCandle) {
                Log::info("📅 [PREV] Previous day candle: {$lastCandle->timestamp}");
                Log::info("  ST: {$lastCandle->supertrend_signal}, VWAP: {$lastCandle->vwap_signal}");
            } else {
                Log::info("📅 [PREV] No previous day data found");
            }

            return $lastCandle;

        } catch (\Exception $e) {
            Log::error("❌ [PREV] Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check and create order
     */
    // private function checkAndCreateOrder(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    // {
    //     try {
    //         Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    //         Log::info("🔍 [CHECK] Starting check for: {$symbol->trading_symbol}");
    //         Log::info("   Strategy: {$config->signal_strategy}");
    //         Log::info("   Quality Filter: " . ($config->enable_quality_filter ? 'ENABLED ✅' : 'DISABLED ⚠️'));
            
    //         $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '5minute', $testDate);
    //         Log::info("📊 [CHECK] Found {$todayCandles->count()} candles today");

    //         if ($todayCandles->count() < 1) {
    //             Log::warning("❌ [CHECK] Not enough data");
    //             return;
    //         }

    //         // ✅ GET PREVIOUS DAY'S LAST CANDLE
    //         $previousDayLastCandle = $this->getPreviousDayLastCandle($broker->id, $symbol->trading_symbol, $testDate);

    //         $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
    //             ->where('trading_symbol', $symbol->trading_symbol)
    //             ->where('status', true)
    //             ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
    //             ->orderBy('created_at', 'asc')
    //             ->get();

    //         Log::info("📋 [CHECK] Existing orders: {$todayOrders->count()}");

    //         // ✅ PASS CONFIG TO SYNC FINDER WITH QUALITY FILTER
    //         $allSyncPoints = $this->findAllSynchronizationPointsWithConfig(
    //             $todayCandles, 
    //             $symbol->trading_symbol, 
    //             $config->signal_strategy,
    //             $config, // ✅ PASS CONFIG
    //             $previousDayLastCandle
    //         );

    //         Log::info("📊 [CHECK] Total sync points: " . count($allSyncPoints));

    //         if (empty($allSyncPoints)) {
    //             Log::warning("❌ [CHECK] No sync points found");
    //             return;
    //         }

    //         $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints, $testDate);
    //         Log::info("📊 [CHECK] Recent sync points: " . count($recentSyncPoints));

    //         if (empty($recentSyncPoints)) {
    //             Log::warning("❌ [CHECK] No recent sync points");
    //             return;
    //         }

    //         $validSyncPoint = $this->getNextValidSyncPoint($recentSyncPoints, $todayOrders, $symbol->trading_symbol);

    //         if (!$validSyncPoint) {
    //             Log::warning("❌ [CHECK] No new valid sync point");
    //             return;
    //         }

    //         Log::info("✅ [CHECK] Valid sync: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
            
    //         $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);

    //     } catch (\Exception $e) {
    //         Log::error("❌ [CHECK] Exception: " . $e->getMessage());
    //     }
    // }
    // private function checkAndCreateOrder(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    // {
    //     try {
    //         Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    //         Log::info("🔍 [CHECK] Starting check for: {$symbol->trading_symbol}");
            
    //         $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '5minute', $testDate);
    //         Log::info("📊 [CHECK] Found {$todayCandles->count()} candles today");

    //         if ($todayCandles->count() < 1) {
    //             Log::warning("❌ [CHECK] Not enough data");
    //             return;
    //         }

    //         $previousDayLastCandle = $this->getPreviousDayLastCandle($broker->id, $symbol->trading_symbol, $testDate);

    //         $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
    //             ->where('trading_symbol', $symbol->trading_symbol)
    //             ->where('status', true)
    //             ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
    //             ->orderBy('created_at', 'asc')
    //             ->get();

    //         Log::info("📋 [CHECK] Existing orders: {$todayOrders->count()}");

    //         $allSyncPoints = $this->findAllSynchronizationPointsWithConfig(
    //             $todayCandles, 
    //             $symbol->trading_symbol, 
    //             $config->signal_strategy,
    //             $config,
    //             $previousDayLastCandle
    //         );

    //         Log::info("📊 [CHECK] Total sync points: " . count($allSyncPoints));

    //         if (empty($allSyncPoints)) {
    //             Log::warning("❌ [CHECK] No sync points found");
    //             return;
    //         }

    //         // $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints, $testDate);
    //         // Log::info("📊 [CHECK] Recent sync points: " . count($recentSyncPoints));

    //         // if (empty($recentSyncPoints)) {
    //         //     Log::warning("❌ [CHECK] No recent sync points");
    //         //     return;
    //         // }

    //         // // ✅ NEW: Process ALL valid signals (like backtesting)
    //         // $validSyncPoints = $this->getAllValidSyncPoints($recentSyncPoints, $todayOrders, $symbol->trading_symbol);

    //         // if (empty($validSyncPoints)) {
    //         //     Log::warning("❌ [CHECK] No new valid sync points");
    //         //     return;
    //         // }

    //         // Log::info("✅ [CHECK] Found {count($validSyncPoints)} valid signals to process");

    //         // // ✅ NEW: Create orders for ALL valid signals
    //         // foreach ($validSyncPoints as $validSyncPoint) {
    //         //     Log::info("✅ [CHECK] Processing: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
    //         //     $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);
    //         // }

    //         $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints, $testDate);

    //         if (empty($recentSyncPoints)) {
    //             Log::warning("❌ [CHECK] No recent sync points");
    //             return;
    //         }

    //         // ✅ NEW: Process ALL valid signals (like simulation)
    //         $validSyncPoints = $this->getAllValidSyncPoints($recentSyncPoints, $todayOrders, $symbol->trading_symbol);

    //         if (empty($validSyncPoints)) {
    //             Log::warning("❌ [CHECK] No new valid sync points");
    //             return;
    //         }

    //         Log::info("✅ [CHECK] Found " . count($validSyncPoints) . " valid signals to process");

    //         // ✅ NEW: Create orders for ALL valid signals
    //         foreach ($validSyncPoints as $validSyncPoint) {
    //             Log::info("✅ [CHECK] Processing: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
    //             $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);
    //         }

    //     } catch (\Exception $e) {
    //         Log::error("❌ [CHECK] Exception: " . $e->getMessage());
    //     }
    // }
    /**
     * Check and create order
     */
    private function checkAndCreateOrder(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    {
        try {
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [CHECK] Starting check for: {$symbol->trading_symbol}");
            Log::info("   Strategy: {$config->signal_strategy}");
            Log::info("   Quality Filter: " . ($config->enable_quality_filter ? 'ENABLED ✅' : 'DISABLED ⚠️'));
            
            $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '5minute', $testDate);
            Log::info("📊 [CHECK] Found {$todayCandles->count()} candles today");

            if ($todayCandles->count() < 1) {
                Log::warning("❌ [CHECK] Not enough data");
                return;
            }

            // ✅ GET PREVIOUS DAY'S LAST CANDLE
            $previousDayLastCandle = $this->getPreviousDayLastCandle($broker->id, $symbol->trading_symbol, $testDate);

            $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('status', true)
                ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info("📋 [CHECK] Existing orders: {$todayOrders->count()}");

            // ✅ FIND ALL SYNC POINTS WITH CONFIG
            $allSyncPoints = $this->findAllSynchronizationPointsWithConfig(
                $todayCandles, 
                $symbol->trading_symbol, 
                $config->signal_strategy,
                $config,
                $previousDayLastCandle
            );

            Log::info("📊 [CHECK] Total sync points: " . count($allSyncPoints));

            if (empty($allSyncPoints)) {
                Log::warning("❌ [CHECK] No sync points found");
                return;
            }

            // ✅ REMOVED TIME FILTER - Process ALL valid signals (like simulation)
            // This matches the simulation behavior exactly
            $validSyncPoints = $this->getAllValidSyncPoints($allSyncPoints, $todayOrders, $symbol->trading_symbol);

            if (empty($validSyncPoints)) {
                Log::warning("❌ [CHECK] No new valid sync points");
                return;
            }

            Log::info("✅ [CHECK] Found " . count($validSyncPoints) . " valid signals to process");

            // ✅ CREATE ORDERS FOR ALL VALID SIGNALS
            foreach ($validSyncPoints as $validSyncPoint) {
                Log::info("✅ [CHECK] Processing: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
                $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);
            }

        } catch (\Exception $e) {
            Log::error("❌ [CHECK] Exception: " . $e->getMessage());
            Log::error("   Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get ALL valid sync points (alternating signals) - MATCHES BACKTESTING LOGIC
     * This processes the ENTIRE batch like backtesting does
     */
    // private function getAllValidSyncPoints($allSyncPoints, $existingOrders, $tradingSymbol)
    // {
    //     if (empty($allSyncPoints)) {
    //         return [];
    //     }

    //     Log::info("🔄 [VALID] Processing " . count($allSyncPoints) . " sync points");

    //     // ✅ Get last DB order info
    //     $lastSignalTypeFromDB = null;
    //     $lastProcessedTime = null;
        
    //     if ($existingOrders->isNotEmpty()) {
    //         $lastOrder = $existingOrders->last();
    //         $lastSignalTypeFromDB = $lastOrder->signal_type;
    //         $lastProcessedTime = $lastOrder->signal_detected_at;
    //         Log::info("🔄 [VALID] Last DB order: {$lastSignalTypeFromDB} @ {$lastProcessedTime}");
    //     } else {
    //         Log::info("🔄 [VALID] No previous orders - processing from scratch");
    //     }

    //     // ✅ Filter signals AFTER last processed time
    //     $newSyncPoints = array_filter($allSyncPoints, function($point) use ($lastProcessedTime) {
    //         if (!$lastProcessedTime) {
    //             return true; // No previous orders, all are new
    //         }
    //         return $point['candle']->timestamp > $lastProcessedTime;
    //     });

    //     if (empty($newSyncPoints)) {
    //         Log::info("🔄 [VALID] No new sync points after {$lastProcessedTime}");
    //         return [];
    //     }

    //     Log::info("🔄 [VALID] Found " . count($newSyncPoints) . " new sync points to evaluate");

    //     // ✅ CRITICAL: Process ALL signals with alternating logic (LIKE BACKTESTING)
    //     $validSignals = [];
    //     $lastSignalType = $lastSignalTypeFromDB; // ✅ Start from DB state

    //     foreach ($newSyncPoints as $syncPoint) {
    //         $syncType = $syncPoint['signal']['type'];
    //         $syncTime = $syncPoint['candle']->timestamp;

    //         // Skip if same as last signal type
    //         if ($lastSignalType === $syncType) {
    //             Log::info("🔄 [VALID] ⏭️ Skipping duplicate {$syncType} @ {$syncTime}");
    //             continue;
    //         }

    //         // ✅ VALID: Different from last signal
    //         Log::info("🔄 [VALID] ✅ Accepting {$syncType} @ {$syncTime} (prev: " . ($lastSignalType ?? 'null') . ")");
            
    //         $validSignals[] = $syncPoint;
    //         $lastSignalType = $syncType; // ✅ UPDATE for next iteration
    //     }

    //     Log::info("🔄 [VALID] Result: " . count($validSignals) . " valid alternating signals");
        
    //     return $validSignals;
    // }

    private function getAllValidSyncPoints($allSyncPoints, $existingOrders, $tradingSymbol)
    {
        if (empty($allSyncPoints)) {
            return [];
        }

        Log::info("🔄 [VALID] Processing " . count($allSyncPoints) . " sync points");

        // Get last DB order info
        $lastSignalTypeFromDB = null;
        $lastProcessedTime = null;
        
        if ($existingOrders->isNotEmpty()) {
            $lastOrder = $existingOrders->last();
            $lastSignalTypeFromDB = $lastOrder->signal_type;
            $lastProcessedTime = $lastOrder->signal_detected_at;
            Log::info("🔄 [VALID] Last DB order: {$lastSignalTypeFromDB} @ {$lastProcessedTime}");
        }

        // Filter signals AFTER last processed time
        $newSyncPoints = array_filter($allSyncPoints, function($point) use ($lastProcessedTime) {
            if (!$lastProcessedTime) {
                return true;
            }
            return $point['candle']->timestamp > $lastProcessedTime;
        });

        if (empty($newSyncPoints)) {
            Log::info("🔄 [VALID] No new sync points after last processed time");
            return [];
        }

        Log::info("🔄 [VALID] Found " . count($newSyncPoints) . " new sync points to evaluate");

        // ✅ Process ALL signals with alternating logic
        $validSignals = [];
        $lastSignalType = $lastSignalTypeFromDB;

        foreach ($newSyncPoints as $syncPoint) {
            $syncType = $syncPoint['signal']['type'];
            $syncTime = $syncPoint['candle']->timestamp;

            // Skip if same as last signal type
            if ($lastSignalType === $syncType) {
                Log::info("🔄 [VALID] ⏭️ Skipping duplicate {$syncType} @ {$syncTime}");
                continue;
            }

            // ✅ VALID: Different from last signal
            Log::info("🔄 [VALID] ✅ Accepting {$syncType} @ {$syncTime}");
            
            $validSignals[] = $syncPoint;
            $lastSignalType = $syncType; // Update for next iteration
        }

        Log::info("🔄 [VALID] Result: " . count($validSignals) . " valid alternating signals");
        
        return $validSignals;
    }

    /**
     * Get today's candles
     */
    private function getTodayCandles($brokerId, $tradingSymbol, $interval, $testDate = null)
    {
        $query = SymbolData::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->whereNotNull('atr')
            ->whereNotNull('supertrend')
            ->whereNotNull('supertrend_direction')
            ->orderBy('timestamp', 'ASC');

        if ($testDate) {
            if (strpos($testDate, ':') !== false) {
                $date = Carbon::parse($testDate)->startOfDay();
                $query->whereDate('timestamp', $date)
                      ->where('timestamp', '<=', $testDate);
            } else {
                $query->whereDate('timestamp', $testDate);
            }
        } else {
            $today = Carbon::today()->setTime(9, 15, 0);
            $now = Carbon::now();
            $query->whereBetween('timestamp', [$today, $now]);
        }

        return $query->get();
    }

    /**
     * Find all synchronization points WITH CONFIG (Quality Filter Support)
     */
    private function findAllSynchronizationPointsWithConfig($candles, $tradingSymbol, $strategy, ZerodhaAutoConfig $config, $previousDayLastCandle = null)
    {
        $syncPoints = [];
        $totalCandles = count($candles);
        
        Log::info("🔍 [SYNC] Scanning {$totalCandles} candles with strategy: {$strategy}");
        Log::info("🔍 [SYNC] Quality Filter: " . ($config->enable_quality_filter ? 'ENABLED ✅' : 'DISABLED ⚠️'));

        // ✅ INITIALIZE FROM PREVIOUS DAY IF AVAILABLE
        $currentSupertrendSignal = null;
        $currentVwapSignal = null;
        $currentRsiSignal = null;

        if ($previousDayLastCandle) {
            $currentSupertrendSignal = $this->getPersistentSupertrendSignal($previousDayLastCandle, collect());
            $currentVwapSignal = $this->getPersistentVWAPSignal($previousDayLastCandle, collect());
            $currentRsiSignal = $previousDayLastCandle->rsi_signal ?? 'NEUTRAL';
            
            Log::info("📅 [SYNC] Starting with previous day signals:");
            Log::info("  ST: {$currentSupertrendSignal}, VWAP: {$currentVwapSignal}, RSI: {$currentRsiSignal}");
        }

        $previousSyncType = null;

        foreach ($candles as $index => $candle) {
            $recordSupertrendSignal = $candle->supertrend_signal;
            $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
            $recordRsiSignal = $candle->rsi_signal ?? 'NEUTRAL';
            $recordDirection = $candle->supertrend_direction;

            // ========== SUPERTREND LOGIC ==========
            $supertrendFresh = false;
            
            if ($currentSupertrendSignal === null && in_array($recordSupertrendSignal, ['BUY', 'SELL'])) {
                $supertrendFresh = true;
                $currentSupertrendSignal = $recordSupertrendSignal;
                Log::info("🎯 [ST] Initial signal: {$recordSupertrendSignal} at {$candle->timestamp}");
            }
            elseif ($recordSupertrendSignal === 'BUY' && $currentSupertrendSignal !== 'BUY') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'BUY';
                Log::info("🎯 [ST] Fresh BUY at {$candle->timestamp}");
            }
            elseif ($recordSupertrendSignal === 'SELL' && $currentSupertrendSignal !== 'SELL') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'SELL';
                Log::info("🎯 [ST] Fresh SELL at {$candle->timestamp}");
            }
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'UP') {
                if ($currentSupertrendSignal !== 'BUY') {
                    $currentSupertrendSignal = 'BUY';
                }
            }
            elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'DOWN') {
                if ($currentSupertrendSignal !== 'SELL') {
                    $currentSupertrendSignal = 'SELL';
                }
            }

            // ========== VWAP LOGIC ==========
            $vwapFresh = false;
            
            if ($currentVwapSignal === null) {
                if ($recordVwapSignal === 'GAP_UP' || $recordVwapSignal === 'BUY') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'BUY';
                    Log::info("🎯 [VWAP] Initial BUY at {$candle->timestamp}");
                } elseif ($recordVwapSignal === 'GAP_DOWN' || $recordVwapSignal === 'SELL') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'SELL';
                    Log::info("🎯 [VWAP] Initial SELL at {$candle->timestamp}");
                } else {
                    $currentVwapSignal = 'HOLD';
                }
            }
            elseif ($recordVwapSignal === 'GAP_UP' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
                Log::info("🎯 [VWAP] GAP_UP → BUY at {$candle->timestamp}");
            }
            elseif ($recordVwapSignal === 'GAP_DOWN' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
                Log::info("🎯 [VWAP] GAP_DOWN → SELL at {$candle->timestamp}");
            }
            elseif ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
                Log::info("🎯 [VWAP] Fresh BUY at {$candle->timestamp}");
            }
            elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
                Log::info("🎯 [VWAP] Fresh SELL at {$candle->timestamp}");
            }

            // ========== RSI LOGIC ==========
            $rsiFresh = false;
            
            if ($currentRsiSignal === null || $currentRsiSignal === 'NEUTRAL') {
                if (in_array($recordRsiSignal, ['BUY', 'SELL'])) {
                    $rsiFresh = true;
                    $currentRsiSignal = $recordRsiSignal;
                    Log::info("🎯 [RSI] Initial {$recordRsiSignal} at {$candle->timestamp}");
                }
            }
            elseif ($recordRsiSignal === 'BUY' && $currentRsiSignal !== 'BUY') {
                $rsiFresh = true;
                $currentRsiSignal = 'BUY';
            }
            elseif ($recordRsiSignal === 'SELL' && $currentRsiSignal !== 'SELL') {
                $rsiFresh = true;
                $currentRsiSignal = 'SELL';
            }

            // ========== CHECK STRATEGY ==========
            $shouldTrigger = false;
            $signalType = null;

            switch ($strategy) {
                case 'SUPERTREND':
                    if ($supertrendFresh && in_array($currentSupertrendSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    break;

                case 'VWAP':
                    if ($vwapFresh && in_array($currentVwapSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                    }
                    break;

                case 'RSI':
                    if ($rsiFresh && in_array($currentRsiSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentRsiSignal;
                    }
                    break;

                case 'BOTH':
                case 'SUPERTREND_VWAP':
                default:
                    if ($supertrendFresh && $vwapFresh && $currentSupertrendSignal === $currentVwapSignal) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                        Log::info("✅ [BOTH] ST + VWAP sync {$signalType} at {$candle->timestamp}");
                    }
                    elseif ($supertrendFresh && $currentSupertrendSignal === $currentVwapSignal && 
                            in_array($currentVwapSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                        Log::info("✅ [BOTH] ST fresh, matches VWAP {$signalType} at {$candle->timestamp}");
                    }
                    elseif ($vwapFresh && $currentVwapSignal === $currentSupertrendSignal && 
                            in_array($currentSupertrendSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                        Log::info("✅ [BOTH] VWAP fresh, matches ST {$signalType} at {$candle->timestamp}");
                    }
                    break;
            }

            // ========== CREATE SYNC POINT WITH CONDITIONAL QUALITY CHECK ==========
            if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
                if ($signalType !== $previousSyncType) {
                    
                    // ✅ APPLY QUALITY FILTER ONLY IF ENABLED
                    $hasQuality = true;
                    if ($config->enable_quality_filter) {
                        $hasQuality = $this->hasQualityMomentum($candles, $index, $signalType);
                        
                        if (!$hasQuality) {
                            Log::info("⚠️ [SYNC] {$signalType} signal at {$candle->timestamp} REJECTED (quality filter)");
                            continue;
                        }
                        Log::info("✅ [SYNC] {$signalType} at index {$index} ({$candle->timestamp}) - QUALITY APPROVED");
                    } else {
                        Log::info("✅ [SYNC] {$signalType} at index {$index} ({$candle->timestamp}) - QUALITY FILTER BYPASSED");
                    }
                    
                    $syncPoints[] = [
                        'index' => $index,
                        'candle' => $candle,
                        'signal' => [
                            'type' => $signalType,
                            'supertrend' => $currentSupertrendSignal,
                            'vwap' => $currentVwapSignal,
                            'rsi' => $currentRsiSignal,
                            'price' => $candle->close,
                            'strategy' => $strategy,
                            'quality_approved' => $hasQuality
                        ]
                    ];
                    
                    $previousSyncType = $signalType;
                } else {
                    Log::info("⏭️ [SYNC] Skip duplicate {$signalType} at {$candle->timestamp}");
                }
            }
        }

        Log::info("📊 [SYNC] Total sync points: " . count($syncPoints));
        return $syncPoints;
    }

    /**
     * Check if signal has quality momentum (volume + price consistency)
     * Adapted for 5-minute timeframe
     */
    private function hasQualityMomentum($candles, $currentIndex, $signalType)
    {
        // Need at least 10 previous candles to check (for 5-min, this is ~50 minutes)
        if ($currentIndex < 10) {
            Log::info("   [QUALITY] Not enough history to validate");
            return false;
        }

        // Get last 10 candles including current (for 5-min, this is 50 minutes of data)
        $recentCandles = [];
        for ($i = max(0, $currentIndex - 9); $i <= $currentIndex; $i++) {
            $recentCandles[] = $candles[$i];
        }

        // Calculate volume SMA(20) - need 20 candles for this (100 minutes)
        $volumeSMA = null;
        if ($currentIndex >= 19) {
            $volumeSum = 0;
            for ($i = $currentIndex - 19; $i <= $currentIndex; $i++) {
                $volumeSum += $candles[$i]->volume;
            }
            $volumeSMA = $volumeSum / 20;
        }

        Log::info("   [QUALITY] Checking momentum for {$signalType} signal");
        
        // CHECK 1: Volume confirmation (at least 6 out of 10 candles)
        $volumePassCount = 0;
        if ($volumeSMA) {
            foreach ($recentCandles as $candle) {
                if ($candle->volume > $volumeSMA) {
                    $volumePassCount++;
                }
            }
            Log::info("   [QUALITY] Volume check: {$volumePassCount}/10 candles above SMA");
            
            if ($volumePassCount < 6) {
                Log::warning("   [QUALITY] ❌ REJECTED: Insufficient volume ({$volumePassCount}/10)");
                return false;
            }
        }

        // CHECK 2: Price momentum consistency (at least 6 out of 9 recent closes)
        $priceConsistencyCount = 0;
        
        for ($i = 1; $i < count($recentCandles); $i++) {
            $prevClose = $recentCandles[$i - 1]->close;
            $currClose = $recentCandles[$i]->close;
            
            if ($signalType === 'BUY') {
                // For BUY: Check if close is trending up
                if ($currClose > $prevClose) {
                    $priceConsistencyCount++;
                }
            } elseif ($signalType === 'SELL') {
                // For SELL: Check if close is trending down
                if ($currClose < $prevClose) {
                    $priceConsistencyCount++;
                }
            }
        }
        
        Log::info("   [QUALITY] Price momentum: {$priceConsistencyCount}/9 consistent closes");
        
        if ($priceConsistencyCount < 6) {
            Log::warning("   [QUALITY] ❌ REJECTED: Weak price momentum ({$priceConsistencyCount}/9)");
            return false;
        }

        // BOTH CHECKS PASSED
        Log::info("   [QUALITY] ✅ APPROVED: Strong momentum confirmed");
        Log::info("   [QUALITY]   - Volume: {$volumePassCount}/10 above SMA");
        Log::info("   [QUALITY]   - Price: {$priceConsistencyCount}/9 consistent");
        
        return true;
    }

    /**
     * Get persistent Supertrend signal
     */
    private function getPersistentSupertrendSignal($candle, $candlesUpToCurrent = null)
    {
        if ($candle->supertrend_signal == 'BUY' || $candle->supertrend_signal == 'SELL') {
            return $candle->supertrend_signal;
        }

        if ($candlesUpToCurrent && $candlesUpToCurrent->count() > 0) {
            $reversed = $candlesUpToCurrent->reverse();
            foreach ($reversed as $pastCandle) {
                if (in_array($pastCandle->supertrend_signal, ['BUY', 'SELL'])) {
                    return $pastCandle->supertrend_signal;
                }
            }
        }

        return 'HOLD';
    }

    /**
     * Get persistent VWAP signal
     */
    private function getPersistentVWAPSignal($candle, $candlesUpToCurrent = null)
    {
        $currentSignal = $candle->vwap_signal;

        if ($currentSignal === 'GAP_UP' || $currentSignal === 'BUY') {
            return 'BUY';
        } elseif ($currentSignal === 'GAP_DOWN' || $currentSignal === 'SELL') {
            return 'SELL';
        }

        if ($candlesUpToCurrent && $candlesUpToCurrent->count() > 0) {
            $reversed = $candlesUpToCurrent->reverse();
            foreach ($reversed as $pastCandle) {
                $pastSignal = $pastCandle->vwap_signal;
                if ($pastSignal === 'GAP_UP' || $pastSignal === 'BUY') {
                    return 'BUY';
                } elseif ($pastSignal === 'GAP_DOWN' || $pastSignal === 'SELL') {
                    return 'SELL';
                }
            }
        }

        return 'HOLD';
    }

    /**
     * Filter to recent sync points
     */
    private function filterRecentSyncPoints($allSyncPoints, $testDate = null)
    {
        if (empty($allSyncPoints)) {
            return [];
        }

        $now = Carbon::now();
        $marketOpen = Carbon::today()->setTime(9, 15, 0);
        
        // First hour of trading - keep ALL signals
        if ($now->diffInMinutes($marketOpen) <= 60 && $now->gte($marketOpen)) {
            Log::info("🕐 [FILTER] First hour - keeping ALL " . count($allSyncPoints) . " sync points");
            return $allSyncPoints;
        }
        
        // After first hour - only recent signals (25 minutes for 5-min timeframe)
        $cutoffTime = $now->copy()->subMinutes(25);
        
        $recentSyncPoints = array_filter($allSyncPoints, function($syncPoint) use ($cutoffTime) {
            $candleTime = Carbon::parse($syncPoint['candle']->timestamp);
            return $candleTime->gte($cutoffTime);
        });

        $recentSyncPoints = array_values($recentSyncPoints);
        
        if (count($recentSyncPoints) < count($allSyncPoints)) {
            $filtered = count($allSyncPoints) - count($recentSyncPoints);
            Log::info("🕐 [FILTER] Filtered out {$filtered} old sync points");
        }

        return $recentSyncPoints;
    }

    /**
     * Get next valid sync point (alternating signals only)
     */
    // private function getNextValidSyncPoint($allSyncPoints, $existingOrders, $tradingSymbol)
    // {
    //     if (empty($allSyncPoints)) {
    //         return null;
    //     }

    //     if ($existingOrders->isEmpty()) {
    //         Log::info("✅ [VALID] No existing orders, using first sync point");
    //         return $allSyncPoints[0];
    //     }

    //     $lastOrder = $existingOrders->last();
    //     $lastSignalType = $lastOrder->signal_type;

    //     foreach ($allSyncPoints as $syncPoint) {
    //         $syncTime = $syncPoint['candle']->timestamp;
    //         $syncType = $syncPoint['signal']['type'];

    //         if ($syncTime <= $lastOrder->signal_detected_at) {
    //             continue;
    //         }

    //         if ($syncType == $lastSignalType) {
    //             Log::info("⏭️ [VALID] Skipping same signal: {$syncType}");
    //             continue;
    //         }

    //         Log::info("✅ [VALID] Found alternating signal: {$syncType} (prev: {$lastSignalType})");
    //         return $syncPoint;
    //     }

    //     return null;
    // }

    private function getNextValidSyncPoint($allSyncPoints, $existingOrders, $tradingSymbol)
    {
        if (empty($allSyncPoints)) {
            return null;
        }

        // ✅ Get last DB order info
        $lastSignalTypeFromDB = null;
        $lastProcessedTime = null;
        
        if ($existingOrders->isNotEmpty()) {
            $lastOrder = $existingOrders->last();
            $lastSignalTypeFromDB = $lastOrder->signal_type;
            $lastProcessedTime = $lastOrder->signal_detected_at;
        }

        // ✅ Filter signals AFTER last processed time
        $newSyncPoints = array_filter($allSyncPoints, function($point) use ($lastProcessedTime) {
            if (!$lastProcessedTime) {
                return true;
            }
            return $point['candle']->timestamp > $lastProcessedTime;
        });

        if (empty($newSyncPoints)) {
            return null;
        }

        // ✅ IMPORTANT: Return FIRST alternating signal only
        foreach ($newSyncPoints as $syncPoint) {
            $syncType = $syncPoint['signal']['type'];

            if ($lastSignalTypeFromDB === null) {
                return $syncPoint; // First ever order
            }

            if ($syncType !== $lastSignalTypeFromDB) {
                return $syncPoint; // ✅ First alternating signal
            }
        }

        return null;
    }

    /**
     * Create order entry
     */
    private function createOrderEntry(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $candle, $signal)
    {
        try {
            // $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $signal['type'], $signal['price']);
            $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $signal['type'], $signal['price'], $config);
            if (!$optionDetails) {
                Log::error("❌ [CREATE] Could not find ATM option");
                return;
            }

            $quantity = $config->getQuantityForSymbol($symbol->trading_symbol);
            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($quantity);

            $orderData = [
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $broker->id,
                'symbol' => $symbol->symbol,
                'trading_symbol' => $symbol->trading_symbol,
                'instrument_token' => $symbol->instrument_token,
                'signal_type' => $signal['type'],
                'signal_strategy' => $signal['strategy'],
                'supertrend_signal' => $signal['supertrend'],
                'vwap_signal' => $signal['vwap'],
                'signal_detected_at' => $candle->timestamp,
                'option_symbol' => $optionDetails['symbol'],
                'option_token' => $optionDetails['token'],
                'option_type' => $optionDetails['type'],
                'strike_price' => $optionDetails['strike'],
                'atm_price' => $signal['price'],
                'entry_price' => $optionDetails['ltp'],
                'current_price' => $optionDetails['ltp'],
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $quantity,
                'pyramid_1' => $pyramid1,
                'pyramid_2' => $pyramid2,
                'pyramid_3' => $pyramid3,
                'is_order_placed' => false,
                'status' => true
            ];

            $order = ZerodhaAutoOrder::create($orderData);
            Log::info("✅ [CREATE] Order created! ID: {$order->id}");
            Log::info("   Future: {$symbol->trading_symbol}");
            Log::info("   Option: {$optionDetails['symbol']}");
            Log::info("   Signal: {$signal['type']} @ ₹{$signal['price']}");

        } catch (\Exception $e) {
            Log::error("❌ [CREATE] Error: " . $e->getMessage());
            Log::error("   Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get ATM option
     */
    // private function getATMOption(BrokerApi $broker, $tradingSymbol, $signalType, $futurePrice)
    // {
    //     try {
    //         $baseSymbol = $this->extractBaseSymbol($tradingSymbol);
    //         $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
    //         $strikeInterval = $this->getStrikeInterval($baseSymbol);
    //         $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

    //         $option = ZerodhaInstrument::where('name', $baseSymbol)
    //             ->where('exchange', 'NFO')
    //             ->where('instrument_type', $optionType)
    //             ->where('strike', $calculatedStrike)
    //             ->whereDate('expiry', '>=', now())
    //             ->orderBy('expiry', 'ASC')
    //             ->first();

    //         if (!$option) {
    //             $option = ZerodhaInstrument::where('name', $baseSymbol)
    //                 ->where('exchange', 'NFO')
    //                 ->where('instrument_type', $optionType)
    //                 ->whereDate('expiry', '>=', now())
    //                 ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike])
    //                 ->orderBy('strike_diff', 'ASC')
    //                 ->orderBy('expiry', 'ASC')
    //                 ->first();
    //         }

    //         if (!$option) {
    //             return null;
    //         }

    //         $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

    //         return [
    //             'symbol' => $option->trading_symbol,
    //             'token' => $option->instrument_token,
    //             'type' => $optionType,
    //             'strike' => $option->strike,
    //             'ltp' => $ltp,
    //             'expiry' => $option->expiry
    //         ];

    //     } catch (\Exception $e) {
    //         Log::error("❌ [OPTION] Error: " . $e->getMessage());
    //         return null;
    //     }
    // }

    /**
     * Get ATM option (respects option_series config)
     */
    private function getATMOption(BrokerApi $broker, $tradingSymbol, $signalType, $futurePrice, ZerodhaAutoConfig $config)
    {
        try {
            $baseSymbol = $this->extractBaseSymbol($tradingSymbol);
            // $optionType = $signalType == 'BUY' ? 'CE' : 'PE';

            // ✅ ADD: Respect option_filter (COPY FROM LIVE HELPER)
            if ($config->option_filter === 'CE') {
                if ($signalType !== 'BUY') {
                    return null; // Block SELL signals
                }
                $optionType = 'CE';
            } elseif ($config->option_filter === 'PE') {
                if ($signalType !== 'SELL') {
                    return null; // Block BUY signals
                }
                $optionType = 'PE';
            } else {
                // BOTH - default logic
                $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            }
            
            $strikeInterval = $this->getStrikeInterval($baseSymbol);
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            // ✅ BUILD QUERY BASED ON option_series
            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($config->option_series === 'next') {
                // ✅ SKIP FIRST EXPIRY, GET SECOND ONE
                $query->orderBy('expiry', 'ASC')
                    ->skip(1) // Skip the nearest expiry
                    ->take(1);
            } else {
                // ✅ CURRENT SERIES - GET NEAREST EXPIRY
                $query->orderBy('expiry', 'ASC');
            }

            $option = $query->first();

            // Fallback: Find nearest strike if exact not found
            if (!$option) {
                $query = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike]);

                if ($config->option_series === 'next') {
                    $query->orderBy('expiry', 'ASC')
                        ->orderBy('strike_diff', 'ASC')
                        ->skip(1)
                        ->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')
                        ->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if (!$option) {
                Log::error("❌ [OPTION] No option found for {$baseSymbol} {$optionType} @ {$calculatedStrike}");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            Log::info("✅ [OPTION] Selected: {$option->trading_symbol}");
            Log::info("   Series: {$config->option_series}");
            Log::info("   Expiry: {$option->expiry}");

            return [
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'type' => $optionType,
                'strike' => $option->strike,
                'ltp' => $ltp,
                'expiry' => $option->expiry
            ];

        } catch (\Exception $e) {
            Log::error("❌ [OPTION] Error: " . $e->getMessage());
            return null;
        }
    }

    private function extractBaseSymbol($tradingSymbol)
    {
        return preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
    }

    private function getStrikeInterval($symbol)
    {
        $intervals = [
            'NIFTY' => 50,
            'BANKNIFTY' => 100,
            'FINNIFTY' => 50,
            'MIDCPNIFTY' => 25,
        ];
        return $intervals[$symbol] ?? 20;
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
     * Place orders
     */
    public function placeOrders($testDate = null)
    {
        try {
            Log::info('=== Starting Symbol Order Placement (5-Min) ===');

            $pendingOrders = ZerodhaAutoOrder::where('is_order_placed', false)
            ->where('status', true)
            ->whereHas('config', function($query) {
                $query->where('status', true); // ✅ ONLY ACTIVE CONFIGS
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

    private function placeOrder(ZerodhaAutoOrder $order)
    {
        try {
            Log::info("📤 [ORDER] Placing: {$order->option_symbol}");

            $broker = $order->broker;
            
            if (!$broker->hasValidToken()) {
                Log::error("❌ [ORDER] Invalid broker token");
                $this->saveFailedOrder($order, $order->pyramid_1 ?? 0, null, "Invalid token");
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
                $this->saveFailedOrder($order, $order->pyramid_1 ?? 0, null, "Instrument not found");
                return;
            }

            $this->placePyramidOrders($order, $instrument, $kite);

            $order->update([
                'is_order_placed' => true,
                'order_placed_at' => now()
            ]);

            Log::info("✅ [ORDER] Order processed: ID {$order->id}");

        } catch (\Exception $e) {
            Log::error("❌ [ORDER] Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $order->pyramid_1 ?? 0, null, $e->getMessage());
        }
    }

    private function placePyramidOrders($order, $instrument, $kite)
    {
        $pyramids = [$order->pyramid_1, $order->pyramid_2, $order->pyramid_3];
        $delays = [0];
        
        if ($order->pyramid_2) $delays[] = $order->config->pyramid_freq * 60;
        if ($order->pyramid_3) $delays[] = $order->config->pyramid_freq * 60 * 2;

        $baseSymbol = $this->extractBaseSymbol($order->trading_symbol);
        $freezeLimitLots = self::FREEZE_LIMITS[$baseSymbol] ?? null;

        foreach ($pyramids as $pyramidIndex => $pyramidQty) {
            if (!$pyramidQty) continue;

            if (($delays[$pyramidIndex] ?? 0) > 0) {
                sleep($delays[$pyramidIndex]);
            }

            $price = null;
            if ($order->order_type == 'LIMIT') {
                $pyramidLevel = $pyramidIndex + 1;
                $price = $this->calculateLimitPrice(
                    $order->entry_price,
                    $order->config->disc_ltp,
                    $order->signal_type,
                    $instrument->tick_size,
                    $pyramidLevel
                );
            }

            if ($freezeLimitLots && $pyramidQty > $freezeLimitLots) {
                Log::info("🔄 [FREEZE] Splitting {$pyramidQty} lots");
                
                $numOrders = ceil($pyramidQty / $freezeLimitLots);
                $remainingLots = $pyramidQty;
                
                for ($i = 0; $i < $numOrders; $i++) {
                    $lotsToPlace = min($freezeLimitLots, $remainingLots);
                    $this->placeKiteOrder($order, $lotsToPlace, $price, $instrument, $kite);
                    $remainingLots -= $lotsToPlace;
                    
                    if ($i < $numOrders - 1) sleep(2);
                }
            } else {
                $this->placeKiteOrder($order, $pyramidQty, $price, $instrument, $kite);
            }
        }
    }

    private function calculateLimitPrice($entryPrice, $discLtp, $signalType, $tickSize, $pyramidLevel = 1)
    {
        $effectiveDiscount = $discLtp;
        
        if ($pyramidLevel == 2) {
            $effectiveDiscount = $discLtp + ($discLtp * 0.5);
        } elseif ($pyramidLevel == 3) {
            $pyramid2Discount = $discLtp + ($discLtp * 0.5);
            $effectiveDiscount = $pyramid2Discount + ($pyramid2Discount * 0.5);
        }
        
        $discount = ($entryPrice * $effectiveDiscount) / 100;
        $price = $entryPrice - $discount;
        $roundedPrice = round($price / $tickSize) * $tickSize;
        
        Log::info("💰 [PRICE] Pyramid {$pyramidLevel}: {$effectiveDiscount}% → ₹{$roundedPrice}");
        
        return number_format($roundedPrice, 2, '.', '');
    }

    private function placeKiteOrder(ZerodhaAutoOrder $order, $quantity, $price, $instrument, $kite)
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
            Log::error("❌ [ORDER] Kite Error: " . $e->getMessage());
            $this->saveFailedOrder($order, $quantity, $price, $e->getMessage());
        }
    }

    private function saveToOrderBook(ZerodhaAutoOrder $order, $orderId, $quantity, $price)
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
                'symbol_auto_order_id' => $order->id
            ]);

            Log::info("✅ [ORDER_BOOK] Saved order {$orderId}");

        } catch (\Exception $e) {
            Log::error("Error saving to order book: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(ZerodhaAutoOrder $order, $quantity, $price, $error)
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
                'symbol_auto_order_id' => $order->id
            ]);

            Log::info("❌ [ORDER_BOOK] Saved failed order");

        } catch (\Exception $e) {
            Log::error("Error saving failed order: " . $e->getMessage());
        }
    }
}