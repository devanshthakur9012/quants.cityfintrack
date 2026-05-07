<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\BrokerApi;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SymbolAutoTrade5MinSimulateCommand extends Command
{
    protected $signature = 'symbol:simulate-day 
                            {--date= : Date to simulate (Y-m-d)}
                            {--time= : Simulate specific time (H:i) - simulates real-time market}
                            {--symbol= : Specific symbol (optional)}
                            {--interval=5 : Minutes between cron runs (for full day simulation)}
                            {--clean : Clean previous test data}';

    protected $description = '🎬 SIMULATE DAY - Full day OR specific time point';

    // ✅ Same time window as live helper
    const ORDER_PLACEMENT_WINDOW_MINUTES = 5;

    public function handle()
    {
        $date = $this->option('date') ?: Carbon::today()->format('Y-m-d');
        $specificTime = $this->option('time');
        $specificSymbol = $this->option('symbol');
        $interval = (int)$this->option('interval');

        try {
            // ✅ NEW: Two simulation modes
            if ($specificTime) {
                // MODE 1: Simulate specific time (like "current time")
                return $this->simulateSpecificTime($date, $specificTime, $specificSymbol);
            } else {
                // MODE 2: Simulate full trading day
                return $this->simulateFullDay($date, $specificSymbol, $interval);
            }

        } catch (Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Simulation Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * ✅ NEW: Simulate specific time point (treats as "current time")
     */
    private function simulateSpecificTime($date, $time, $specificSymbol = null)
    {
        $currentTime = Carbon::parse($date . ' ' . $time);

        $this->info("🎬 ═══════════════════════════════════════════════════");
        $this->info("   SIMULATING SPECIFIC TIME POINT");
        $this->info("   Date: {$date}");
        $this->info("   Simulated Current Time: {$currentTime->format('H:i:s')}");
        if ($specificSymbol) {
            $this->info("   Symbol Filter: {$specificSymbol}");
        }
        $this->info("   Order Window: Last " . self::ORDER_PLACEMENT_WINDOW_MINUTES . " minutes");
        $this->info("═══════════════════════════════════════════════════\n");

        // Clean previous test data if requested
        if ($this->option('clean')) {
            $deleted = ZerodhaAutoOrder::where('is_order_placed', false)
                ->whereDate('signal_detected_at', $date)
                ->delete();
            
            if ($deleted > 0) {
                $this->warn("🗑️  Cleared {$deleted} previous test orders for {$date}\n");
            }
        }

        $configs = ZerodhaAutoConfig::getActiveConfigs();

        if ($configs->isEmpty()) {
            $this->warn("No active configs found");
            return 0;
        }

        $symbolsQuery = SymbolMonitored::where('is_active', true)
            ->where('interval', '5minute');

        if ($specificSymbol) {
            $symbolsQuery->where('trading_symbol', $specificSymbol);
        }

        $symbols = $symbolsQuery->get();

        if ($symbols->isEmpty()) {
            $this->warn("No active symbols found");
            return 0;
        }

        $this->info("✅ Processing {$symbols->count()} symbols\n");

        $totalOrdersCreated = 0;
        $symbolsByBroker = $symbols->groupBy('broker_api_id');

        foreach ($symbolsByBroker as $brokerId => $brokerSymbols) {
            $broker = BrokerApi::find($brokerId);
            
            if (!$broker || !$broker->hasValidToken()) {
                continue;
            }

            foreach ($brokerSymbols as $symbol) {
                $config = $this->findConfigForSymbol($configs, $symbol);
                
                if (!$config) {
                    continue;
                }

                $this->info("🔍 Processing: {$symbol->trading_symbol}");
                
                $ordersCreated = $this->processSymbolAtTime(
                    $config, 
                    $symbol, 
                    $broker, 
                    $currentTime, 
                    $date
                );
                
                if ($ordersCreated > 0) {
                    $this->info("   ✅ Created {$ordersCreated} orders");
                    $totalOrdersCreated += $ordersCreated;
                } else {
                    $this->info("   ⏭️  No recent signals for order placement");
                }
            }
        }

        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 SIMULATION SUMMARY (Time: {$currentTime->format('H:i')})");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("   Total Orders Created: {$totalOrdersCreated}");

        if ($totalOrdersCreated > 0) {
            $this->showSampleOrders($date);
        }

        $this->info("\n✅ Simulation completed!");
        return 0;
    }

    /**
     * ✅ Process symbol at specific time (matches live helper logic)
     */
    private function processSymbolAtTime(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $currentTime, $date)
    {
        try {
            // Get candles UP TO current time
            $todayCandles = $this->getCandlesUpToTime($broker->id, $symbol->trading_symbol, '5minute', $currentTime);

            if ($todayCandles->count() < 1) {
                return 0;
            }

            // Get existing orders
            $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('status', true)
                ->where('is_order_placed', false)
                ->whereDate('signal_detected_at', $date)
                ->orderBy('signal_detected_at', 'asc')
                ->get();

            // ✅ STAGE 1: Find ALL sync points (entire day)
            $allSyncPoints = $this->findAllSyncPoints(
                $todayCandles, 
                $symbol->trading_symbol, 
                $config->signal_strategy,
                $config
            );

            if (empty($allSyncPoints)) {
                return 0;
            }

            // ✅ STAGE 2: Get ALL valid alternating signals
            $validSyncPoints = $this->getAllValidSyncPoints($allSyncPoints, $todayOrders);

            if (empty($validSyncPoints)) {
                return 0;
            }

            // ✅ STAGE 3: Filter RECENT signals for order placement
            $recentSignalsForOrders = $this->filterRecentForOrderPlacement($validSyncPoints, $currentTime);

            if (empty($recentSignalsForOrders)) {
                return 0;
            }

            // ✅ STAGE 4: Create orders for recent signals
            $ordersCreated = 0;
            foreach ($recentSignalsForOrders as $syncPoint) {
                $this->createOrderEntry($config, $symbol, $broker, $syncPoint['candle'], $syncPoint['signal'], $currentTime);
                $ordersCreated++;
            }

            return $ordersCreated;

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * ✅ Filter recent signals for order placement (matches live helper)
     */
    private function filterRecentForOrderPlacement($validSyncPoints, $currentTime)
    {
        $cutoffTime = $currentTime->copy()->subMinutes(self::ORDER_PLACEMENT_WINDOW_MINUTES);

        $recentSignals = array_filter($validSyncPoints, function($point) use ($cutoffTime) {
            $signalTime = Carbon::parse($point['candle']->timestamp);
            return $signalTime->gte($cutoffTime);
        });

        return array_values($recentSignals);
    }

    /**
     * Simulate full trading day
     */
    private function simulateFullDay($date, $specificSymbol, $interval)
    {
        $this->info("🎬 ═══════════════════════════════════════════════════");
        $this->info("   SIMULATING COMPLETE TRADING DAY");
        $this->info("   Date: {$date}");
        $this->info("   Cron Interval: Every {$interval} minutes");
        if ($specificSymbol) {
            $this->info("   Symbol Filter: {$specificSymbol}");
        }
        $this->info("═══════════════════════════════════════════════════\n");

        // Clean previous test data if requested
        if ($this->option('clean')) {
            $deleted = ZerodhaAutoOrder::where('is_order_placed', false)
                ->whereDate('signal_detected_at', $date)
                ->delete();
            
            if ($deleted > 0) {
                $this->warn("🗑️  Cleared {$deleted} previous test orders for {$date}\n");
            }
        }

        $marketOpen = Carbon::parse($date . ' 09:15:00');
        $marketClose = Carbon::parse($date . ' 15:30:00');

        $currentTime = $marketOpen->copy();
        $cronRunCount = 0;
        $totalOrdersCreated = 0;

        $this->info("📅 Trading Session: {$marketOpen->format('H:i')} to {$marketClose->format('H:i')}\n");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Simulate cron runs every X minutes
        while ($currentTime->lte($marketClose)) {
            $cronRunCount++;
            
            $this->info("🕐 CRON RUN #{$cronRunCount} - {$currentTime->format('H:i:s')}");

            $ordersCreated = $this->processCronRun($currentTime, $date, $specificSymbol);
            $totalOrdersCreated += $ordersCreated;

            if ($ordersCreated > 0) {
                $this->info("   ✅ Orders created: {$ordersCreated}");
            } else {
                $this->info("   ⏭️  No new orders");
            }

            $this->newLine();

            // Move to next cron run
            $currentTime->addMinutes($interval);
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 SIMULATION SUMMARY");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("   Total Cron Runs: {$cronRunCount}");
        $this->info("   Total Orders Created: {$totalOrdersCreated}");

        // Get statistics
        $buyOrders = ZerodhaAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->where('signal_type', 'BUY')
            ->count();

        $sellOrders = ZerodhaAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->where('signal_type', 'SELL')
            ->count();

        $uniqueSymbols = ZerodhaAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->distinct('trading_symbol')
            ->count('trading_symbol');

        $this->info("   Unique Symbols: {$uniqueSymbols}");
        $this->info("   CE Orders (BUY): {$buyOrders}");
        $this->info("   PE Orders (SELL): {$sellOrders}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Show sample orders
        $this->info("📋 SAMPLE ORDERS (First 15):");
        $this->showSampleOrders($date);

        $this->info("\n✅ Simulation completed!");
        $this->info("💡 Compare with backtesting results");

        return 0;
    }

    private function processCronRun($currentTime, $date, $specificSymbol = null)
    {
        $configs = ZerodhaAutoConfig::getActiveConfigs();

        if ($configs->isEmpty()) {
            return 0;
        }

        $symbolsQuery = SymbolMonitored::where('is_active', true)
            ->where('interval', '5minute');

        if ($specificSymbol) {
            $symbolsQuery->where('trading_symbol', $specificSymbol);
        }

        $symbols = $symbolsQuery->get();

        if ($symbols->isEmpty()) {
            return 0;
        }

        $totalOrdersCreated = 0;
        $symbolsByBroker = $symbols->groupBy('broker_api_id');

        foreach ($symbolsByBroker as $brokerId => $brokerSymbols) {
            $broker = BrokerApi::find($brokerId);
            
            if (!$broker || !$broker->hasValidToken()) {
                continue;
            }

            foreach ($brokerSymbols as $symbol) {
                $config = $this->findConfigForSymbol($configs, $symbol);
                
                if (!$config) {
                    continue;
                }

                $ordersCreated = $this->processSymbolAtTime($config, $symbol, $broker, $currentTime, $date);
                
                if ($ordersCreated > 0) {
                    $this->info("      ✓ {$symbol->trading_symbol} ({$ordersCreated} orders)");
                    $totalOrdersCreated += $ordersCreated;
                }
            }
        }

        return $totalOrdersCreated;
    }

    private function findConfigForSymbol($configs, SymbolMonitored $symbol)
    {
        $config = $configs->first(function($config) use ($symbol) {
            return $config->user_id === $symbol->user_id 
                && $config->broker_api_id === $symbol->broker_api_id;
        });

        if ($config) {
            return $config;
        }

        return $configs->first(function($config) use ($symbol) {
            return $config->broker_api_id === $symbol->broker_api_id;
        });
    }

    /**
     * Get ALL valid sync points (alternating signals)
     */
    private function getAllValidSyncPoints($allSyncPoints, $existingOrders)
    {
        if (empty($allSyncPoints)) {
            return [];
        }

        // Get last DB order info
        $lastSignalTypeFromDB = null;
        $lastProcessedTime = null;
        
        if ($existingOrders->isNotEmpty()) {
            $lastOrder = $existingOrders->last();
            $lastSignalTypeFromDB = $lastOrder->signal_type;
            $lastProcessedTime = $lastOrder->signal_detected_at;
        }

        // Filter signals AFTER last processed time
        $newSyncPoints = array_filter($allSyncPoints, function($point) use ($lastProcessedTime) {
            if (!$lastProcessedTime) {
                return true;
            }
            return $point['candle']->timestamp > $lastProcessedTime;
        });

        if (empty($newSyncPoints)) {
            return [];
        }

        // Process ALL signals with alternating logic
        $validSignals = [];
        $lastSignalType = $lastSignalTypeFromDB;

        foreach ($newSyncPoints as $syncPoint) {
            $syncType = $syncPoint['signal']['type'];

            if ($lastSignalType === $syncType) {
                continue;
            }

            $validSignals[] = $syncPoint;
            $lastSignalType = $syncType;
        }

        return $validSignals;
    }

    private function getCandlesUpToTime($brokerId, $tradingSymbol, $interval, $currentTime)
    {
        $marketOpen = $currentTime->copy()->startOfDay()->setTime(9, 15, 0);

        return SymbolData::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->whereNotNull('atr')
            ->whereNotNull('supertrend')
            ->whereNotNull('supertrend_direction')
            ->whereBetween('timestamp', [$marketOpen, $currentTime])
            ->orderBy('timestamp', 'ASC')
            ->get();
    }

    private function findAllSyncPoints($candles, $tradingSymbol, $strategy, ZerodhaAutoConfig $config)
    {
        $syncPoints = [];
        $currentSupertrendSignal = null;
        $currentVwapSignal = null;
        $previousSyncType = null;

        foreach ($candles as $index => $candle) {
            $recordSupertrendSignal = $candle->supertrend_signal;
            $recordVwapSignal = $candle->vwap_signal ?? 'HOLD';
            $recordDirection = $candle->supertrend_direction;

            // SUPERTREND LOGIC
            $supertrendFresh = false;
            
            if ($currentSupertrendSignal === null && in_array($recordSupertrendSignal, ['BUY', 'SELL'])) {
                $supertrendFresh = true;
                $currentSupertrendSignal = $recordSupertrendSignal;
            }
            elseif ($recordSupertrendSignal === 'BUY' && $currentSupertrendSignal !== 'BUY') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'BUY';
            }
            elseif ($recordSupertrendSignal === 'SELL' && $currentSupertrendSignal !== 'SELL') {
                $supertrendFresh = true;
                $currentSupertrendSignal = 'SELL';
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

            // VWAP LOGIC
            $vwapFresh = false;
            
            if ($currentVwapSignal === null) {
                if ($recordVwapSignal === 'GAP_UP' || $recordVwapSignal === 'BUY') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'BUY';
                } elseif ($recordVwapSignal === 'GAP_DOWN' || $recordVwapSignal === 'SELL') {
                    $vwapFresh = true;
                    $currentVwapSignal = 'SELL';
                } else {
                    $currentVwapSignal = 'HOLD';
                }
            }
            elseif ($recordVwapSignal === 'GAP_UP' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
            }
            elseif ($recordVwapSignal === 'GAP_DOWN' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
            }
            elseif ($recordVwapSignal === 'BUY' && $currentVwapSignal !== 'BUY') {
                $vwapFresh = true;
                $currentVwapSignal = 'BUY';
            }
            elseif ($recordVwapSignal === 'SELL' && $currentVwapSignal !== 'SELL') {
                $vwapFresh = true;
                $currentVwapSignal = 'SELL';
            }

            // CHECK STRATEGY
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

                case 'BOTH':
                case 'SUPERTREND_VWAP':
                default:
                    if ($supertrendFresh && $vwapFresh && $currentSupertrendSignal === $currentVwapSignal) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    elseif ($supertrendFresh && $currentSupertrendSignal === $currentVwapSignal && 
                            in_array($currentVwapSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentSupertrendSignal;
                    }
                    elseif ($vwapFresh && $currentVwapSignal === $currentSupertrendSignal && 
                            in_array($currentSupertrendSignal, ['BUY', 'SELL'])) {
                        $shouldTrigger = true;
                        $signalType = $currentVwapSignal;
                    }
                    break;
            }

            if ($shouldTrigger && $signalType && in_array($signalType, ['BUY', 'SELL'])) {
                if ($signalType !== $previousSyncType) {
                    $syncPoints[] = [
                        'index' => $index,
                        'candle' => $candle,
                        'signal' => [
                            'type' => $signalType,
                            'supertrend' => $currentSupertrendSignal,
                            'vwap' => $currentVwapSignal,
                            'price' => $candle->close,
                            'strategy' => $strategy
                        ]
                    ];
                    
                    $previousSyncType = $signalType;
                }
            }
        }

        return $syncPoints;
    }

    private function createOrderEntry(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $candle, $signal, $currentTime)
    {
        try {
            $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $signal['type'], $signal['price'], $config);
            
            if (!$optionDetails) {
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
                'entry_price' => 25.00,
                'current_price' => 25.00,
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $quantity,
                'pyramid_1' => $pyramid1,
                'pyramid_2' => $pyramid2,
                'pyramid_3' => $pyramid3,
                'is_order_placed' => false,
                'status' => true
            ];

            ZerodhaAutoOrder::create($orderData);

        } catch (Exception $e) {
            // Silent fail
        }
    }

    private function getATMOption(BrokerApi $broker, $tradingSymbol, $signalType, $futurePrice, ZerodhaAutoConfig $config)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
            
            if ($config->option_filter === 'CE') {
                if ($signalType !== 'BUY') {
                    return null;
                }
                $optionType = 'CE';
            } elseif ($config->option_filter === 'PE') {
                if ($signalType !== 'SELL') {
                    return null;
                }
                $optionType = 'PE';
            } else {
                $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            }
            
            $intervals = [
                'NIFTY' => 50,
                'BANKNIFTY' => 100,
                'FINNIFTY' => 50,
                'MIDCPNIFTY' => 25,
            ];
            $strikeInterval = $intervals[$baseSymbol] ?? 20;
            $calculatedStrike = round($futurePrice / $strikeInterval) * $strikeInterval;

            $query = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $calculatedStrike)
                ->whereDate('expiry', '>=', now());

            if ($config->option_series === 'next') {
                $query->orderBy('expiry', 'ASC')->skip(1)->take(1);
            } else {
                $query->orderBy('expiry', 'ASC');
            }

            $option = $query->first();

            if (!$option) {
                $query = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', now())
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$calculatedStrike]);

                if ($config->option_series === 'next') {
                    $query->orderBy('expiry', 'ASC')->orderBy('strike_diff', 'ASC')->skip(1)->take(1);
                } else {
                    $query->orderBy('strike_diff', 'ASC')->orderBy('expiry', 'ASC');
                }

                $option = $query->first();
            }

            if (!$option) {
                return null;
            }

            return [
                'symbol' => $option->trading_symbol,
                'token' => $option->instrument_token,
                'type' => $optionType,
                'strike' => $option->strike,
                'expiry' => $option->expiry
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    private function showSampleOrders($date)
    {
        $orders = ZerodhaAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->orderBy('signal_detected_at', 'asc')
            ->limit(15)
            ->get();

        if ($orders->isEmpty()) {
            $this->warn("   No orders found");
            return;
        }

        $this->table(
            ['#', 'Time', 'Symbol', 'Signal', 'Option'],
            $orders->map(function($order, $index) {
                return [
                    $index + 1,
                    $order->signal_detected_at->format('H:i'),
                    $order->trading_symbol,
                    $order->signal_type,
                    $order->option_symbol
                ];
            })
        );
    }
}