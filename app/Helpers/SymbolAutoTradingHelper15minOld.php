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

class SymbolAutoTradingHelper15minOld
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
            Log::info('=== Starting Symbol Auto Trading Signal Detection (15-Minute) ===');
            Log::info('Current Time: ' . Carbon::now()->format('Y-m-d H:i:s'));
            
            $configs = ZerodhaAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                Log::info('No active configurations found');
                return;
            }

            Log::info("✅ Found {$configs->count()} active configs");

            // Get all active monitored symbols for 15-minute interval
            $symbols = SymbolMonitored::where('is_active', true)
                ->where('interval', '15minute')
                ->get();

            if ($symbols->isEmpty()) {
                Log::info('No active 15-minute symbols found');
                return;
            }

            Log::info("✅ Found {$symbols->count()} active 15-minute symbols");

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
                ->where('interval', '15minute')
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
    //         Log::info("   Broker: {$broker->client_name}");
    //         Log::info("   Strategy: {$config->signal_strategy}");
    //         Log::info("   Symbol (base): {$symbol->symbol}");
            
    //         // ✅ Get today's candles using trading_symbol (ADANIPORTS26JANFUT)
    //         $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '15minute', $testDate);
    //         Log::info("📊 [CHECK] Found {$todayCandles->count()} candles today");

    //         if ($todayCandles->count() < 1) {
    //             Log::warning("❌ [CHECK] Not enough data");
                
    //             // Debug
    //             $totalRecords = SymbolData::where('broker_api_id', $broker->id)
    //                 ->where('trading_symbol', $symbol->trading_symbol)
    //                 ->where('interval', '15minute')
    //                 ->count();
                
    //             Log::info("   Total records in DB for {$symbol->trading_symbol}: {$totalRecords}");
                
    //             return;
    //         }

    //         // Get existing orders today using trading_symbol
    //         $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
    //             ->where('trading_symbol', $symbol->trading_symbol)
    //             ->where('status', true)
    //             ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
    //             ->orderBy('created_at', 'asc')
    //             ->get();

    //         Log::info("📋 [CHECK] Existing orders today: {$todayOrders->count()}");

    //         // Find sync points using EXACT SAME LOGIC as backtesting
    //         Log::info("🔍 [CHECK] Looking for synchronization points...");
    //         $allSyncPoints = $this->findAllSynchronizationPoints(
    //             $todayCandles, 
    //             $symbol->trading_symbol, 
    //             $config->signal_strategy
    //         );

    //         Log::info("📊 [CHECK] Raw sync points found: " . count($allSyncPoints));

    //         if (empty($allSyncPoints)) {
    //             Log::warning("❌ [CHECK] No signal synchronization found");
    //             return;
    //         }

    //         // Filter recent sync points
    //         $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints);
    //         Log::info("📊 [CHECK] Recent sync points: " . count($recentSyncPoints));

    //         if (empty($recentSyncPoints)) {
    //             Log::warning("❌ [CHECK] No recent sync points");
    //             return;
    //         }

    //         // Get valid sync point (alternating signals only)
    //         $validSyncPoint = $this->getNextValidSyncPoint($recentSyncPoints, $todayOrders, $symbol->trading_symbol);

    //         if (!$validSyncPoint) {
    //             Log::warning("❌ [CHECK] No new valid sync point");
    //             return;
    //         }

    //         Log::info("✅ [CHECK] Valid sync point found!");
    //         Log::info("   Signal: {$validSyncPoint['signal']['type']}");
    //         Log::info("   Time: {$validSyncPoint['candle']->timestamp}");
    //         Log::info("🚀 [CHECK] Creating order entry...");
            
    //         $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);

    //     } catch (\Exception $e) {
    //         Log::error("❌ [CHECK] EXCEPTION for {$symbol->trading_symbol}");
    //         Log::error("   Message: " . $e->getMessage());
    //         Log::error("   Trace: " . $e->getTraceAsString());
    //     }
    // }
    private function checkAndCreateOrder(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    {
        try {
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            Log::info("🔍 [CHECK] Starting check for: {$symbol->trading_symbol}");
            Log::info("   Strategy: {$config->signal_strategy}");
            
            $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '15minute', $testDate);
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

            // ✅ PASS PREVIOUS DAY DATA TO SYNC FINDER
            $allSyncPoints = $this->findAllSynchronizationPoints(
                $todayCandles, 
                $symbol->trading_symbol, 
                $config->signal_strategy,
                $previousDayLastCandle  // ✅ ADD THIS
            );

            Log::info("📊 [CHECK] Total sync points: " . count($allSyncPoints));

            if (empty($allSyncPoints)) {
                Log::warning("❌ [CHECK] No sync points found");
                return;
            }

            $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints, $testDate);
            Log::info("📊 [CHECK] Recent sync points: " . count($recentSyncPoints));

            if (empty($recentSyncPoints)) {
                Log::warning("❌ [CHECK] No recent sync points");
                return;
            }

            $validSyncPoint = $this->getNextValidSyncPoint($recentSyncPoints, $todayOrders, $symbol->trading_symbol);

            if (!$validSyncPoint) {
                Log::warning("❌ [CHECK] No new valid sync point");
                return;
            }

            Log::info("✅ [CHECK] Valid sync: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
            
            $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);

        } catch (\Exception $e) {
            Log::error("❌ [CHECK] Exception: " . $e->getMessage());
        }
    }

    /**
     * Get today's candles - FIXED to use trading_symbol
     */
    private function getTodayCandles($brokerId, $tradingSymbol, $interval, $testDate = null)
    {
        $query = SymbolData::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)  // ✅ Use trading_symbol
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
     * Find all synchronization points - EXACT SAME AS BACKTESTING
     */
    // private function findAllSynchronizationPoints($candles, $tradingSymbol, $strategy)
    // {
    //     $syncPoints = [];
    //     $totalCandles = count($candles);
        
    //     Log::info("🔍 [SYNC] Scanning {$totalCandles} candles for {$tradingSymbol} with strategy: {$strategy}");

    //     $currentSupertrendSignal = null;
    //     $currentVwapSignal = null;
    //     $currentRsiSignal = null;
    //     $previousSyncType = null;

    //     foreach ($candles as $index => $candle) {
    //         $recordSupertrendSignal = $candle->supertrend_signal;
    //         $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
    //         $recordRsiSignal = $candle->rsi_signal ?? 'NEUTRAL';
    //         $recordDirection = $candle->supertrend_direction;

    //         // ========== SUPERTREND LOGIC ==========
    //         $supertrendFresh = false;
            
    //         if ($recordSupertrendSignal === 'BUY' && $currentSupertrendSignal !== 'BUY') {
    //             $supertrendFresh = true;
    //             $currentSupertrendSignal = 'BUY';
    //         } elseif ($recordSupertrendSignal === 'SELL' && $currentSupertrendSignal !== 'SELL') {
    //             $supertrendFresh = true;
    //             $currentSupertrendSignal = 'SELL';
    //         } elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'UP' && $currentSupertrendSignal === 'BUY') {
    //             $currentSupertrendSignal = 'BUY';
    //         } elseif ($recordSupertrendSignal === 'HOLD' && $recordDirection === 'DOWN' && $currentSupertrendSignal === 'SELL') {
    //             $currentSupertrendSignal = 'SELL';
    //         } elseif ($currentSupertrendSignal === null) {
    //             $currentSupertrendSignal = $recordSupertrendSignal;
    //         }

    //         // ========== VWAP LOGIC ==========
    //         $vwapFresh = false;
            
    //         if ($recordVwapSignal === 'GAP_UP') {
    //             if ($currentVwapSignal !== 'BUY') {
    //                 $vwapFresh = true;
    //                 $currentVwapSignal = 'BUY';
    //                 Log::info("✅ [VWAP] GAP_UP → BUY at {$candle->timestamp}");
    //             }
    //         } elseif ($recordVwapSignal === 'GAP_DOWN') {
    //             if ($currentVwapSignal !== 'SELL') {
    //                 $vwapFresh = true;
    //                 $currentVwapSignal = 'SELL';
    //                 Log::info("✅ [VWAP] GAP_DOWN → SELL at {$candle->timestamp}");
    //             }
    //         } elseif ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
    //             $vwapFresh = true;
    //             $currentVwapSignal = 'BUY';
    //         } elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
    //             $vwapFresh = true;
    //             $currentVwapSignal = 'SELL';
    //         } elseif ($recordVwapSignal === 'HOLD') {
    //             // Keep persistent
    //         } elseif ($currentVwapSignal === null) {
    //             $currentVwapSignal = 'HOLD';
    //         }

    //         // ========== RSI LOGIC ==========
    //         $rsiFresh = false;
    //         if ($recordRsiSignal === 'BUY' && $currentRsiSignal !== 'BUY') {
    //             $rsiFresh = true;
    //             $currentRsiSignal = 'BUY';
    //         } elseif ($recordRsiSignal === 'SELL' && $currentRsiSignal !== 'SELL') {
    //             $rsiFresh = true;
    //             $currentRsiSignal = 'SELL';
    //         } elseif ($currentRsiSignal === null) {
    //             $currentRsiSignal = $recordRsiSignal;
    //         }

    //         // ========== CHECK STRATEGY ==========
    //         $shouldTrigger = false;
    //         $signalType = null;

    //         switch ($strategy) {
    //             case 'SUPERTREND':
    //                 if ($supertrendFresh) {
    //                     $shouldTrigger = true;
    //                     $signalType = $currentSupertrendSignal;
    //                 }
    //                 break;

    //             case 'VWAP':
    //                 if ($vwapFresh) {
    //                     $shouldTrigger = true;
    //                     $signalType = $currentVwapSignal;
    //                 }
    //                 break;

    //             case 'RSI':
    //                 if ($rsiFresh) {
    //                     $shouldTrigger = true;
    //                     $signalType = $currentRsiSignal;
    //                 }
    //                 break;

    //             case 'BOTH':
    //             case 'SUPERTREND_VWAP':
    //             default:
    //                 if ($supertrendFresh && $vwapFresh && $currentSupertrendSignal === $currentVwapSignal) {
    //                     $shouldTrigger = true;
    //                     $signalType = $currentSupertrendSignal;
    //                     Log::info("✅ [BOTH] ST + VWAP sync {$signalType} at {$candle->timestamp}");
    //                 }
    //                 elseif ($supertrendFresh && $currentSupertrendSignal === $currentVwapSignal && $currentVwapSignal !== 'HOLD') {
    //                     $shouldTrigger = true;
    //                     $signalType = $currentSupertrendSignal;
    //                     Log::info("✅ [BOTH] ST fresh, matches VWAP {$signalType} at {$candle->timestamp}");
    //                 }
    //                 elseif ($vwapFresh && $currentVwapSignal === $currentSupertrendSignal && $currentSupertrendSignal !== 'HOLD') {
    //                     $shouldTrigger = true;
    //                     $signalType = $currentVwapSignal;
    //                     Log::info("✅ [BOTH] VWAP fresh, matches ST {$signalType} at {$candle->timestamp}");
    //                 }
    //                 break;
    //         }

    //         // ========== CREATE SYNC POINT (PREVENT DUPLICATES) ==========
    //         if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
    //             if ($signalType !== $previousSyncType) {
    //                 Log::info("✅ [SYNC] {$signalType} sync at index {$index} ({$candle->timestamp})");
                    
    //                 $syncPoints[] = [
    //                     'index' => $index,
    //                     'candle' => $candle,
    //                     'signal' => [
    //                         'type' => $signalType,
    //                         'supertrend' => $currentSupertrendSignal,
    //                         'vwap' => $currentVwapSignal,
    //                         'rsi' => $currentRsiSignal,
    //                         'price' => $candle->close,
    //                         'strategy' => $strategy
    //                     ]
    //                 ];
                    
    //                 $previousSyncType = $signalType;
    //             } else {
    //                 Log::info("⏭️ [SYNC] Skipping duplicate {$signalType} at {$candle->timestamp}");
    //             }
    //         }
    //     }

    //     Log::info("📊 [SYNC] Total sync points found: " . count($syncPoints));
    //     return $syncPoints;
    // }
    private function findAllSynchronizationPoints($candles, $tradingSymbol, $strategy, $previousDayLastCandle = null)
    {
        $syncPoints = [];
        $totalCandles = count($candles);
        
        Log::info("🔍 [SYNC] Scanning {$totalCandles} candles with strategy: {$strategy}");

        // ✅ INITIALIZE FROM PREVIOUS DAY IF AVAILABLE
        $currentSupertrendSignal = null;
        $currentVwapSignal = null;
        $currentRsiSignal = null;

        if ($previousDayLastCandle) {
            // Get persistent signals from previous day
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
            
            // ✅ If no previous data, first valid signal triggers
            if ($currentSupertrendSignal === null && in_array($recordSupertrendSignal, ['BUY', 'SELL'])) {
                $supertrendFresh = true;
                $currentSupertrendSignal = $recordSupertrendSignal;
                Log::info("🎯 [ST] Initial signal: {$recordSupertrendSignal} at {$candle->timestamp}");
            }
            // Fresh BUY signal
            elseif ($recordSupertrendSignal === 'BUY' && $currentSupertrendSignal !== 'BUY') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'BUY';
                Log::info("🎯 [ST] Fresh BUY at {$candle->timestamp}");
            }
            // Fresh SELL signal
            elseif ($recordSupertrendSignal === 'SELL' && $currentSupertrendSignal !== 'SELL') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'SELL';
                Log::info("🎯 [ST] Fresh SELL at {$candle->timestamp}");
            }
            // Persist during HOLD with direction
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

            if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
                if ($signalType !== $previousSyncType) {
                    Log::info("✅ [SYNC] {$signalType} at index {$index} ({$candle->timestamp})");
                    
                    $syncPoints[] = [
                        'index' => $index,
                        'candle' => $candle,
                        'signal' => [
                            'type' => $signalType,
                            'supertrend' => $currentSupertrendSignal,
                            'vwap' => $currentVwapSignal,
                            'rsi' => $currentRsiSignal,
                            'price' => $candle->close,
                            'strategy' => $strategy
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
        
        // After first hour - only recent signals (45 minutes)
        $cutoffTime = $now->copy()->subMinutes(45);
        
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
    private function getNextValidSyncPoint($allSyncPoints, $existingOrders, $tradingSymbol)
    {
        if (empty($allSyncPoints)) {
            return null;
        }

        if ($existingOrders->isEmpty()) {
            Log::info("✅ [VALID] No existing orders, using first sync point");
            return $allSyncPoints[0];
        }

        $lastOrder = $existingOrders->last();
        $lastSignalType = $lastOrder->signal_type;

        foreach ($allSyncPoints as $syncPoint) {
            $syncTime = $syncPoint['candle']->timestamp;
            $syncType = $syncPoint['signal']['type'];

            if ($syncTime <= $lastOrder->signal_detected_at) {
                continue;
            }

            if ($syncType == $lastSignalType) {
                Log::info("⏭️ [VALID] Skipping same signal: {$syncType}");
                continue;
            }

            Log::info("✅ [VALID] Found alternating signal: {$syncType} (prev: {$lastSignalType})");
            return $syncPoint;
        }

        return null;
    }

    /**
     * Create order entry - FIXED column names
     */
    private function createOrderEntry(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $candle, $signal)
    {
        try {
            $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $signal['type'], $signal['price']);

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
                'symbol' => $symbol->symbol,                      // ✅ ADANIPORTS
                'trading_symbol' => $symbol->trading_symbol,      // ✅ ADANIPORTS26JANFUT
                'instrument_token' => $symbol->instrument_token,  // ✅ Token
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
    private function getATMOption(BrokerApi $broker, $tradingSymbol, $signalType, $futurePrice)
    {
        try {
            $baseSymbol = $this->extractBaseSymbol($tradingSymbol);
            $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
            $strikeInterval = $this->getStrikeInterval($baseSymbol);
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            $option = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now())
                ->orderBy('expiry', 'ASC')
                ->first();

            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike])
                    ->orderBy('strike_diff', 'ASC')
                    ->orderBy('expiry', 'ASC')
                    ->first();
            }

            if (!$option) {
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

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
            Log::info('=== Starting Symbol Order Placement (15-Min) ===');

            $pendingOrders = ZerodhaAutoOrder::where('is_order_placed', false)
                ->where('status', true)
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