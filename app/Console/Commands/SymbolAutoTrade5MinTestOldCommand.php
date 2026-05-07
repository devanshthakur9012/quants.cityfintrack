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

class SymbolAutoTrade5MinTestOldCommand extends Command
{
    protected $signature = 'symbol:test-old {--test-date= : Test date (Y-m-d H:i:s)}';
    protected $description = '🧪 TEST OLD LOGIC - Detect signals and store in DB (NO Zerodha orders)';

    public function handle()
    {
        $testDate = $this->option('test-date');

        try {
            $this->info("🧪 ═══════════════════════════════════════════════════");
            $this->info("   TEST MODE - OLD LOGIC (Current Code)");
            $this->info("   Database entries ONLY - NO Zerodha orders");
            $this->info("═══════════════════════════════════════════════════");
            
            if ($testDate) {
                $this->info("📅 Test Date: {$testDate}");
                Log::info("Symbol Test OLD - Date: {$testDate}");
            } else {
                $this->info("📅 Using current time");
                Log::info("Symbol Test OLD - LIVE TIME");
            }

            $this->processSignalsOldLogic($testDate);

            $this->info("\n✅ Test completed! Check zerodha_auto_orders table");
            $this->info("💡 Orders marked as is_order_placed = false (test mode)");

            return 0;

        } catch (Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Test OLD Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function processSignalsOldLogic($testDate = null)
    {
        $configs = ZerodhaAutoConfig::getActiveConfigs();

        if ($configs->isEmpty()) {
            $this->warn('No active configurations found');
            return;
        }

        $this->info("✅ Found {$configs->count()} active configs\n");

        $symbols = SymbolMonitored::where('is_active', true)
            ->where('interval', '5minute')
            ->get();

        if ($symbols->isEmpty()) {
            $this->warn('No active 5-minute symbols found');
            return;
        }

        $this->info("✅ Found {$symbols->count()} active 5-minute symbols\n");

        $symbolsByBroker = $symbols->groupBy('broker_api_id');
        $totalOrdersCreated = 0;

        foreach ($symbolsByBroker as $brokerId => $brokerSymbols) {
            $broker = BrokerApi::find($brokerId);
            
            if (!$broker || !$broker->hasValidToken()) {
                $this->warn("⚠️ Broker {$brokerId} has invalid token, skipping");
                continue;
            }

            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

            foreach ($brokerSymbols as $symbol) {
                $config = $this->findConfigForSymbol($configs, $symbol);
                
                if (!$config) {
                    continue;
                }

                $ordersCreated = $this->checkAndCreateOrderOldLogic($config, $symbol, $broker, $testDate);
                $totalOrdersCreated += $ordersCreated;
            }
        }

        $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 SUMMARY (OLD LOGIC)");
        $this->info("   Total Orders Created: {$totalOrdersCreated}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
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

    private function checkAndCreateOrderOldLogic(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $testDate = null)
    {
        try {
            $this->info("   🔍 Checking: {$symbol->trading_symbol}");

            $todayCandles = $this->getTodayCandles($broker->id, $symbol->trading_symbol, '5minute', $testDate);

            if ($todayCandles->count() < 1) {
                $this->warn("      ⚠️ Not enough data");
                return 0;
            }

            $todayOrders = ZerodhaAutoOrder::where('broker_api_id', $broker->id)
                ->where('trading_symbol', $symbol->trading_symbol)
                ->where('status', true)
                ->whereDate('created_at', $testDate ? Carbon::parse($testDate) : Carbon::today())
                ->orderBy('created_at', 'asc')
                ->get();

            $allSyncPoints = $this->findAllSyncPoints($todayCandles, $symbol->trading_symbol, $config->signal_strategy);

            if (empty($allSyncPoints)) {
                $this->warn("      ⚠️ No sync points");
                return 0;
            }

            $recentSyncPoints = $this->filterRecentSyncPoints($allSyncPoints, $testDate);

            if (empty($recentSyncPoints)) {
                $this->warn("      ⚠️ No recent sync points");
                return 0;
            }

            // ❌ OLD LOGIC: Compare against last DATABASE order
            $validSyncPoint = $this->getNextValidSyncPointOLD($recentSyncPoints, $todayOrders, $symbol->trading_symbol);

            if (!$validSyncPoint) {
                $this->warn("      ⚠️ No new valid sync point (OLD LOGIC)");
                return 0;
            }

            $this->info("      ✅ Valid sync: {$validSyncPoint['signal']['type']} @ {$validSyncPoint['candle']->timestamp}");
            
            $this->createOrderEntry($config, $symbol, $broker, $validSyncPoint['candle'], $validSyncPoint['signal']);

            return 1;

        } catch (Exception $e) {
            $this->error("      ❌ Error: " . $e->getMessage());
            return 0;
        }
    }

    // ❌ OLD LOGIC: Compares against LAST DATABASE ORDER
    private function getNextValidSyncPointOLD($allSyncPoints, $existingOrders, $tradingSymbol)
    {
        if ($existingOrders->isEmpty()) {
            $this->info("         [OLD] No existing orders, using first sync point");
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

            // ❌ OLD LOGIC: Skip if same as LAST ORDER from DB
            if ($syncType == $lastSignalType) {
                $this->info("         [OLD] ⏭️ Skipping same signal: {$syncType}");
                continue;
            }

            $this->info("         [OLD] ✅ Found alternating signal: {$syncType} (prev: {$lastSignalType})");
            return $syncPoint;
        }

        return null;
    }

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

    private function findAllSyncPoints($candles, $tradingSymbol, $strategy)
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

    private function filterRecentSyncPoints($allSyncPoints, $testDate = null)
    {
        if (empty($allSyncPoints)) {
            return [];
        }

        // ✅ TEST MODE: Return ALL sync points (no time filtering)
        if ($testDate) {
            $this->info("      [FILTER] Test mode - keeping ALL " . count($allSyncPoints) . " sync points");
            return $allSyncPoints;
        }

        // Only apply time filter in LIVE mode
        $now = Carbon::now();
        $marketOpen = Carbon::today()->setTime(9, 15, 0);
        
        // First hour - keep all
        if ($now->diffInMinutes($marketOpen) <= 60 && $now->gte($marketOpen)) {
            return $allSyncPoints;
        }
        
        // After first hour - filter last 25 minutes
        $cutoffTime = $now->copy()->subMinutes(25);
        
        $recentSyncPoints = array_filter($allSyncPoints, function($syncPoint) use ($cutoffTime) {
            $candleTime = Carbon::parse($syncPoint['candle']->timestamp);
            return $candleTime->gte($cutoffTime);
        });

        return array_values($recentSyncPoints);
    }

    private function createOrderEntry(ZerodhaAutoConfig $config, SymbolMonitored $symbol, BrokerApi $broker, $candle, $signal)
    {
        try {
            $optionDetails = $this->getATMOption($broker, $symbol->trading_symbol, $signal['type'], $signal['price'], $config);
            
            if (!$optionDetails) {
                $this->error("      ❌ Could not find ATM option");
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
                'entry_price' => 25.00, // Test mode - fixed price
                'current_price' => 25.00,
                'order_type' => $config->order_type,
                'product' => $config->product,
                'quantity' => $quantity,
                'pyramid_1' => $pyramid1,
                'pyramid_2' => $pyramid2,
                'pyramid_3' => $pyramid3,
                'is_order_placed' => false, // ✅ TEST MODE - NOT PLACED
                'status' => true
            ];

            $order = ZerodhaAutoOrder::create($orderData);
            
            $this->info("      ✅ Test order created! ID: {$order->id}");
            $this->info("         Option: {$optionDetails['symbol']}");

        } catch (Exception $e) {
            $this->error("      ❌ Error: " . $e->getMessage());
        }
    }

    private function getATMOption(BrokerApi $broker, $tradingSymbol, $signalType, $futurePrice, ZerodhaAutoConfig $config)
    {
        try {
            $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);
            $optionType = $signalType == 'BUY' ? 'CE' : 'PE';
            
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
}