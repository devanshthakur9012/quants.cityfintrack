<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ZerodhaAutoConfig;
use App\Models\ZerodhaAutoOrder;
use App\Models\FuturesData;
use App\Models\FuturesMonitored;
use App\Models\ZerodhaInstrument;
use App\Helpers\ZerodhaAutoTradingHelper;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ZerodhaCheckMissedOrdersCommand extends Command
{
    protected $signature = 'zerodha:check-missed-orders {--date= : Check specific date (Y-m-d)}';

    protected $description = 'Check for missed signal synchronizations and create orders';

    private $helper;

    public function __construct()
    {
        parent::__construct();
        $this->helper = new ZerodhaAutoTradingHelper();
    }

    public function handle()
    {
        $date = $this->option('date') ?? Carbon::today()->format('Y-m-d');
        
        $this->info("🔍 Checking for missed orders on: {$date}");
        Log::info("=== Checking Missed Orders: {$date} ===");

        try {
            // Get active configurations
            $configs = ZerodhaAutoConfig::getActiveConfigs();

            if ($configs->isEmpty()) {
                $this->warn('No active configurations found');
                return 0;
            }

            // Get monitored futures
            $futures = FuturesMonitored::where('is_active', true)->get();

            if ($futures->isEmpty()) {
                $this->warn('No active futures found');
                return 0;
            }

            $this->info("📊 Analyzing {$futures->count()} futures...\n");

            $missedCount = 0;
            $config = $configs->first();

            foreach ($futures as $future) {
                $this->line("Checking: {$future->trading_symbol}");
                
                $result = $this->checkMissedSignal($config, $future, $date);
                
                if ($result) {
                    $missedCount++;
                    $this->info("  ✅ Created {$result['signal_type']} order at {$result['sync_time']}");
                } else {
                    $this->line("  ⏭️  No missed signals");
                }
                
                $this->newLine();
            }

            if ($missedCount > 0) {
                $this->info("✅ Created {$missedCount} missed order(s)");
                
                // Now place the orders
                $this->info("\n📤 Placing pending orders...");
                $this->helper->placeOrders();
                $this->info("✅ Orders placed successfully!");
            } else {
                $this->info("✅ No missed orders found");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Missed Orders Check Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Check for missed signal synchronization for a specific future
     */
    private function checkMissedSignal(ZerodhaAutoConfig $config, FuturesMonitored $future, $date)
    {
        try {
            Log::info("Checking missed signals for: {$future->trading_symbol}");

            // Get the last candle from previous day (for comparison baseline)
            $previousDayLastCandle = FuturesData::where('trading_symbol', $future->trading_symbol)
                ->where('interval', 'minute')
                ->whereDate('timestamp', '<', $date)
                ->whereNotNull('atr')
                ->whereNotNull('supertrend')
                ->whereNotNull('supertrend_direction')
                ->orderBy('timestamp', 'DESC')
                ->first();

            // Get all candles for the specified date
            $todaysCandles = FuturesData::where('trading_symbol', $future->trading_symbol)
                ->where('interval', 'minute')
                ->whereDate('timestamp', $date)
                ->whereNotNull('atr')
                ->whereNotNull('supertrend')
                ->whereNotNull('supertrend_direction')
                ->orderBy('timestamp', 'ASC')
                ->get();

            if ($todaysCandles->count() < 1) {
                Log::info("No candles found for {$future->trading_symbol} on {$date}");
                return false;
            }

            // Combine: [previous day last candle] + [today's candles]
            $candles = collect();
            if ($previousDayLastCandle) {
                $candles->push($previousDayLastCandle);
                Log::info("Including previous day candle: {$previousDayLastCandle->timestamp}");
            }
            $candles = $candles->concat($todaysCandles);

            Log::info("Analyzing {$candles->count()} candles for {$future->trading_symbol} (including previous day)");

            // Analyze each candle to find signal synchronization
            $previousCandle = null;
            $syncFound = false;
            $syncCandle = null;
            $signalType = null;

            foreach ($candles as $index => $candle) {
                if (!$previousCandle) {
                    $previousCandle = $candle;
                    continue;
                }

                // Get signals for both candles
                $prevST = $this->getSupertrendSignal($previousCandle, $future->trading_symbol);
                $prevDon = $this->getDonchianSignal($previousCandle, $future->trading_symbol);
                
                $currST = $this->getSupertrendSignal($candle, $future->trading_symbol);
                $currDon = $this->getDonchianSignal($candle, $future->trading_symbol);

                // Only log today's candles to reduce noise
                if ($candle->timestamp->format('Y-m-d') == $date) {
                    Log::info("{$candle->timestamp}: Prev({$previousCandle->timestamp->format('Y-m-d H:i')}: ST={$prevST}, Don={$prevDon}) → Curr(ST={$currST}, Don={$currDon})");
                }

                // Check if signals synchronized in this candle (but weren't synchronized before)
                // Only check for today's candles (not the previous day baseline)
                if ($candle->timestamp->format('Y-m-d') == $date) {
                    if (($currST == 'BUY' && $currDon == 'BUY') && !($prevST == 'BUY' && $prevDon == 'BUY')) {
                        $syncFound = true;
                        $syncCandle = $candle;
                        $signalType = 'BUY';
                        Log::info("🎯 BUY signal synchronized at {$candle->timestamp} (changed from Prev: ST={$prevST}, Don={$prevDon})");
                        break;
                    }

                    if (($currST == 'SELL' && $currDon == 'SELL') && !($prevST == 'SELL' && $prevDon == 'SELL')) {
                        $syncFound = true;
                        $syncCandle = $candle;
                        $signalType = 'SELL';
                        Log::info("🎯 SELL signal synchronized at {$candle->timestamp} (changed from Prev: ST={$prevST}, Don={$prevDon})");
                        break;
                    }
                }

                $previousCandle = $candle;
            }

            if (!$syncFound) {
                Log::info("No signal synchronization found for {$future->trading_symbol}");
                return false;
            }

            // Check if order already exists for this signal
            $existingOrder = ZerodhaAutoOrder::where('future_symbol', $future->trading_symbol)
                ->where('signal_type', $signalType)
                ->where('status', true)
                ->where('created_at', '>=', $syncCandle->timestamp->subMinutes(30))
                ->first();

            if ($existingOrder) {
                Log::info("Order already exists (ID: {$existingOrder->id}) for {$signalType} signal at {$syncCandle->timestamp}");
                return false;
            }

            // Create the missed order
            Log::info("✅ Creating missed {$signalType} order for {$future->trading_symbol}");
            
            $signal = [
                'type' => $signalType,
                'supertrend' => $signalType,
                'donchian' => $signalType,
                'price' => $syncCandle->close
            ];

            $this->createOrderEntry($config, $future, $syncCandle, $signal);

            return [
                'signal_type' => $signalType,
                'sync_time' => $syncCandle->timestamp->format('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            Log::error("Error checking missed signal for {$future->trading_symbol}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get persistent Supertrend signal
     */
    private function getSupertrendSignal($candle, $tradingSymbol)
    {
        if (in_array($candle->supertrend_signal, ['BUY', 'SELL'])) {
            return $candle->supertrend_signal;
        }

        $lastSignal = FuturesData::where('trading_symbol', $tradingSymbol)
            ->where('interval', 'minute')
            ->where('timestamp', '<', $candle->timestamp)
            ->whereIn('supertrend_signal', ['BUY', 'SELL'])
            ->orderBy('timestamp', 'DESC')
            ->first();

        return $lastSignal ? $lastSignal->supertrend_signal : 'HOLD';
    }

    /**
     * Get persistent Donchian signal
     */
    private function getDonchianSignal($candle, $tradingSymbol)
    {
        try {
            $candles = FuturesData::where('trading_symbol', $tradingSymbol)
                ->where('interval', 'minute')
                ->where('timestamp', '<=', $candle->timestamp)
                ->orderBy('timestamp', 'DESC')
                ->limit(50)
                ->get()
                ->reverse()
                ->values();

            if ($candles->count() < 20) {
                return 'NO_TRADE';
            }

            $ohlcData = $candles->map(function ($item) {
                return [
                    'date' => $item->timestamp->format('Y-m-d H:i:s'),
                    'open' => (float)$item->open,
                    'high' => (float)$item->high,
                    'low' => (float)$item->low,
                    'close' => (float)$item->close,
                ];
            })->toArray();

            $donchianSignals = \App\Helpers\DonchianCalculator::calculateSignalsForDataset($ohlcData, 20);
            $currentIndex = count($donchianSignals) - 1;
            $currentSignal = $donchianSignals[$currentIndex] ?? null;

            if (!$currentSignal) {
                return 'NO_TRADE';
            }

            if (in_array($currentSignal['signal'], ['BUY', 'SELL'])) {
                return $currentSignal['signal'];
            }

            for ($i = $currentIndex - 1; $i >= 0; $i--) {
                if (in_array($donchianSignals[$i]['signal'], ['BUY', 'SELL'])) {
                    return $donchianSignals[$i]['signal'];
                }
            }

            return 'NO_TRADE';

        } catch (\Exception $e) {
            Log::error("Error calculating Donchian signal: " . $e->getMessage());
            return 'NO_TRADE';
        }
    }

    /**
     * Create order entry
     */
    private function createOrderEntry(ZerodhaAutoConfig $config, FuturesMonitored $future, $candle, $signal)
    {
        try {
            Log::info("📝 Creating order entry for {$future->trading_symbol}");
            
            // Use helper to get option details
            $reflection = new \ReflectionClass($this->helper);
            $method = $reflection->getMethod('getATMOption');
            $method->setAccessible(true);
            
            $optionDetails = $method->invoke($this->helper, $future->trading_symbol, $signal['type'], $signal['price']);

            if (!$optionDetails) {
                Log::error("Could not find ATM option for {$future->trading_symbol}");
                return;
            }

            [$pyramid1, $pyramid2, $pyramid3] = $config->calculatePyramids($config->quantity);

            $orderData = [
                'user_id' => $config->user_id,
                'config_id' => $config->id,
                'broker_api_id' => $config->broker_api_id,
                'future_symbol' => $future->trading_symbol,
                'future_token' => $future->instrument_token,
                'signal_type' => $signal['type'],
                'supertrend_signal' => $signal['supertrend'],
                'donchian_signal' => $signal['donchian'],
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
                'quantity' => $config->quantity,
                'pyramid_1' => $pyramid1,
                'pyramid_2' => $pyramid2,
                'pyramid_3' => $pyramid3,
                'is_order_placed' => false,
                'status' => true
            ];

            $order = ZerodhaAutoOrder::create($orderData);

            Log::info("✅ Order entry created: ID={$order->id}, {$signal['type']} {$optionDetails['symbol']}");

        } catch (\Exception $e) {
            Log::error("Error creating order entry: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }
}