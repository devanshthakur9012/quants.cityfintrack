<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SymbolData;
use App\Models\SymbolMonitored;
use App\Models\BrokerApi;
use App\Models\OnePercentAutoConfig;
use App\Models\OnePercentAutoOrder;
use App\Models\ZerodhaInstrument;
use App\Models\OptionStrike;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class OnePercentSimulateCommand extends Command
{
    protected $signature = 'onepercent:simulate-day 
                            {--date= : Date to simulate (Y-m-d)}
                            {--time= : Simulate specific time (H:i) - simulates real-time market}
                            {--symbol= : Specific symbol (optional)}
                            {--interval=5 : Minutes between cron runs (for full day simulation)}
                            {--clean : Clean previous test data}';

    protected $description = '🎬 SIMULATE One-Percent Trading - Full day OR specific time point';

    // ✅ Same time window as live helper
    const ORDER_PLACEMENT_WINDOW_MINUTES = 10;

    public function handle()
    {
        $date = $this->option('date') ?: Carbon::today()->format('Y-m-d');
        $specificTime = $this->option('time');
        $specificSymbol = $this->option('symbol');
        $interval = (int)$this->option('interval');

        try {
            // ✅ Two simulation modes
            if ($specificTime) {
                // MODE 1: Simulate specific time (like "current time")
                return $this->simulateSpecificTime($date, $specificTime, $specificSymbol);
            } else {
                // MODE 2: Simulate full trading day
                return $this->simulateFullDay($date, $specificSymbol, $interval);
            }

        } catch (Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('One-Percent Simulation Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * ✅ Simulate specific time point (treats as "current time")
     */
    private function simulateSpecificTime($date, $time, $specificSymbol = null)
    {
        $currentTime = Carbon::parse($date . ' ' . $time);

        $this->info("🎬 ═══════════════════════════════════════════════════");
        $this->info("   SIMULATING SPECIFIC TIME POINT (One-Percent)");
        $this->info("   Date: {$date}");
        $this->info("   Simulated Current Time: {$currentTime->format('H:i:s')}");
        if ($specificSymbol) {
            $this->info("   Symbol Filter: {$specificSymbol}");
        }
        $this->info("   Order Window: Last " . self::ORDER_PLACEMENT_WINDOW_MINUTES . " minutes");
        $this->info("═══════════════════════════════════════════════════\n");

        // Clean previous test data if requested
        if ($this->option('clean')) {
            $deleted = OnePercentAutoOrder::where('is_order_placed', false)
                ->whereDate('signal_detected_at', $date)
                ->delete();
            
            if ($deleted > 0) {
                $this->warn("🗑️  Cleared {$deleted} previous test orders for {$date}\n");
            }
        }

        $configs = OnePercentAutoConfig::getActiveConfigs();

        if ($configs->isEmpty()) {
            $this->warn("No active one-percent configs found");
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
    private function processSymbolAtTime(OnePercentAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $currentTime, $date)
    {
        try {
            // Get candles UP TO current time
            $todayCandles = $this->getCandlesUpToTime($broker->id, $symbol->trading_symbol, '5minute', $currentTime);

            if ($todayCandles->count() < 1) {
                return 0;
            }

            // Get day opening price
            $dayOpenCandle = $todayCandles->first();
            $dayOpenPrice = $dayOpenCandle->open;

            if (!$dayOpenPrice) {
                return 0;
            }

            // Get existing orders
            $todayOrders = OnePercentAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('status', true)
                ->where('is_order_placed', false)
                ->whereDate('signal_detected_at', $date)
                ->orderBy('signal_detected_at', 'asc')
                ->get();

            // Get OI data
            $oiData = $this->getOIDataForSymbol($symbol->trading_symbol, $date);

            // Get last processed signal info
            $lastSignalType = null;
            $lastSignalTime = null;
            
            if ($todayOrders->isNotEmpty()) {
                $lastOrder = $todayOrders->last();
                $lastSignalType = $lastOrder->signal_type;
                $lastSignalTime = $lastOrder->signal_detected_at;
            }

            // ✅ Collect ALL valid signals (entire day processing)
            $validSignals = [];

            foreach ($todayCandles as $candle) {
                $currentPrice = $candle->close;
                $changePct = (($currentPrice - $dayOpenPrice) / $dayOpenPrice) * 100;

                // Skip if this candle is before last processed time
                if ($lastSignalTime && $candle->timestamp <= $lastSignalTime) {
                    continue;
                }

                // Check for +X% move (BUY CE signal)
                if ($changePct >= $config->move_threshold) {
                    // Skip if last signal was already BUY_CE
                    if ($lastSignalType === 'BUY_CE') {
                        continue;
                    }

                    // Only if CE Signal is BULLISH
                    if ($oiData['ce_signal'] === 'BULLISH') {
                        $validSignals[] = [
                            'candle' => $candle,
                            'type' => 'BUY_CE',
                            'day_open' => $dayOpenPrice,
                            'price' => $currentPrice,
                            'change' => $changePct,
                            'oi' => $oiData
                        ];
                        $lastSignalType = 'BUY_CE';
                    }
                }

                // Check for -X% move (BUY PE signal)
                if ($changePct <= -$config->move_threshold) {
                    // Skip if last signal was already BUY_PE
                    if ($lastSignalType === 'BUY_PE') {
                        continue;
                    }

                    // Only if PE Signal is BULLISH
                    if ($oiData['pe_signal'] === 'BULLISH') {
                        $validSignals[] = [
                            'candle' => $candle,
                            'type' => 'BUY_PE',
                            'day_open' => $dayOpenPrice,
                            'price' => $currentPrice,
                            'change' => $changePct,
                            'oi' => $oiData
                        ];
                        $lastSignalType = 'BUY_PE';
                    }
                }
            }

            if (empty($validSignals)) {
                return 0;
            }

            // ✅ FILTER RECENT SIGNALS FOR ORDER PLACEMENT
            $recentSignalsForOrders = $this->filterRecentForOrderPlacement($validSignals, $currentTime);

            if (empty($recentSignalsForOrders)) {
                return 0;
            }

            // ✅ CREATE ORDERS FOR RECENT SIGNALS
            $ordersCreated = 0;
            foreach ($recentSignalsForOrders as $signal) {
                $this->createOrderEntry($config, $symbol, $broker, $signal, $currentTime);
                $ordersCreated++;
            }

            return $ordersCreated;

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * ✅ Filter recent signals for order placement
     */
    private function filterRecentForOrderPlacement($validSignals, $currentTime)
    {
        $cutoffTime = $currentTime->copy()->subMinutes(self::ORDER_PLACEMENT_WINDOW_MINUTES);

        $recentSignals = array_filter($validSignals, function($signal) use ($cutoffTime) {
            $signalTime = Carbon::parse($signal['candle']->timestamp);
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
        $this->info("   SIMULATING COMPLETE TRADING DAY (One-Percent)");
        $this->info("   Date: {$date}");
        $this->info("   Cron Interval: Every {$interval} minutes");
        if ($specificSymbol) {
            $this->info("   Symbol Filter: {$specificSymbol}");
        }
        $this->info("═══════════════════════════════════════════════════\n");

        // Clean previous test data if requested
        if ($this->option('clean')) {
            $deleted = OnePercentAutoOrder::where('is_order_placed', false)
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
        $ceOrders = OnePercentAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->where('signal_type', 'BUY_CE')
            ->count();

        $peOrders = OnePercentAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->where('signal_type', 'BUY_PE')
            ->count();

        $uniqueSymbols = OnePercentAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->distinct('trading_symbol')
            ->count('trading_symbol');

        $this->info("   Unique Symbols: {$uniqueSymbols}");
        $this->info("   CE Orders (BUY_CE): {$ceOrders}");
        $this->info("   PE Orders (BUY_PE): {$peOrders}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        // Show sample orders
        $this->info("📋 SAMPLE ORDERS (First 15):");
        $this->showSampleOrders($date);

        $this->info("\n✅ Simulation completed!");

        return 0;
    }

    private function processCronRun($currentTime, $date, $specificSymbol = null)
    {
        $configs = OnePercentAutoConfig::getActiveConfigs();

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

    private function getCandlesUpToTime($brokerId, $tradingSymbol, $interval, $currentTime)
    {
        $marketOpen = $currentTime->copy()->startOfDay()->setTime(9, 15, 0);

        return SymbolData::where('broker_api_id', $brokerId)
            ->where('trading_symbol', $tradingSymbol)
            ->where('interval', $interval)
            ->whereBetween('timestamp', [$marketOpen, $currentTime])
            ->orderBy('timestamp', 'ASC')
            ->get();
    }

    private function getOIDataForSymbol($futureSymbol, $date)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futureSymbol);
            $oiDate = $this->getPreviousTradingDay($date);

            $futOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'FUT')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            $ceOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'CE_MERGED')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            $peOI = OptionStrike::where('underlying_symbol', $baseSymbol)
                ->where('strike_position', 'PE_MERGED')
                ->where('trading_date', $oiDate)
                ->orderBy('id', 'DESC')
                ->first();

            return [
                'fut_signal' => $futOI ? $futOI->direction : 'NEUTRAL',
                'fut_strength' => $futOI ? $futOI->strength : 'N/A',
                'ce_signal' => $ceOI ? $ceOI->direction : 'NEUTRAL',
                'pe_signal' => $peOI ? $peOI->direction : 'NEUTRAL',
                'market_bias' => $futOI ? $futOI->market_bias : 'N/A',
            ];

        } catch (Exception $e) {
            return [
                'fut_signal' => 'ERROR',
                'fut_strength' => 'ERROR',
                'ce_signal' => 'ERROR',
                'pe_signal' => 'ERROR',
                'market_bias' => 'ERROR',
            ];
        }
    }

    private function getPreviousTradingDay($date)
    {
        $prevDate = Carbon::parse($date)->subDay();
        $maxAttempts = 10;
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            if ($prevDate->isWeekend()) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            $isHoliday = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->where('holiday_date', $prevDate->format('Y-m-d'))
                ->exists();
            
            if ($isHoliday) {
                $prevDate->subDay();
                $attempts++;
                continue;
            }
            
            return $prevDate->format('Y-m-d');
        }
        
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function createOrderEntry(OnePercentAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $signal, $currentTime)
    {
        try {
            $optionType = $signal['type'] === 'BUY_CE' ? 'CE' : 'PE';
            $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $optionType, $signal['price'], $config);
            
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
                'move_threshold' => $config->move_threshold,
                'signal_detected_at' => $signal['candle']->timestamp,
                'day_open_price' => $signal['day_open'],
                'signal_price' => $signal['price'],
                'change_pct' => $signal['change'],
                'fut_signal' => $signal['oi']['fut_signal'],
                'fut_strength' => $signal['oi']['fut_strength'],
                'ce_signal' => $signal['oi']['ce_signal'],
                'pe_signal' => $signal['oi']['pe_signal'],
                'market_bias' => $signal['oi']['market_bias'],
                'option_symbol' => $optionDetails['symbol'],
                'option_token' => $optionDetails['token'],
                'option_type' => $optionType,
                'strike_price' => $optionDetails['strike'],
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

            OnePercentAutoOrder::create($orderData);

        } catch (Exception $e) {
            // Silent fail
        }
    }

    private function getATMOption(BrokerApi $broker, $tradingSymbol, $optionType, $futurePrice, OnePercentAutoConfig $config)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
            
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
                'strike' => $option->strike,
                'expiry' => $option->expiry
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    private function showSampleOrders($date)
    {
        $orders = OnePercentAutoOrder::where('is_order_placed', false)
            ->whereDate('signal_detected_at', $date)
            ->orderBy('signal_detected_at', 'asc')
            ->limit(15)
            ->get();

        if ($orders->isEmpty()) {
            $this->warn("   No orders found");
            return;
        }

        $this->table(
            ['#', 'Time', 'Symbol', 'Signal', 'Move%', 'CE OI', 'PE OI', 'Option'],
            $orders->map(function($order, $index) {
                return [
                    $index + 1,
                    $order->signal_detected_at->format('H:i'),
                    $order->trading_symbol,
                    $order->signal_type,
                    ($order->change_pct > 0 ? '+' : '') . number_format($order->change_pct, 2) . '%',
                    $order->ce_signal,
                    $order->pe_signal,
                    $order->option_symbol
                ];
            })
        );
    }
}