<?php
// app/Services/Cp/CpMultiTimeOrderPlacementService.php
namespace App\Services\Cp;

use App\Models\CpMultiTimeOrderConfig;
use App\Models\CpMultiTimeOrder;
use App\Services\Broker\AngelBrokerService;
use App\Services\Broker\ZerodhaBrokerService;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated placement service for OI Flow Multi-Snapshot. Talks ONLY to
 * OIFlowMultiTimeService (signals) and the common broker services
 * (AngelBrokerService / ZerodhaBrokerService) for execution. Nothing
 * here touches cp_order_configs / cp_orders / CpAnalysisSignalResolver —
 * fully isolated from the OI Flow Sentiment (single-snapshot) flow.
 */
class CpMultiTimeOrderPlacementService
{
    public function __construct(private OIFlowMultiTimeService $signals) {}

    public function run(string $date): array
    {
        $totals = ['placed' => 0, 'skipped' => 0, 'errors' => 0];

        $configs = CpMultiTimeOrderConfig::with('broker')->where('status', true)->get();
        foreach ($configs as $config) {
            $result = $this->runForConfig($config, $date);
            $totals['placed']  += $result['placed'];
            $totals['skipped'] += $result['skipped'];
            $totals['errors']  += $result['errors'];
        }
        return $totals;
    }

    public function runForConfig(CpMultiTimeOrderConfig $config, string $date): array
    {
        $placed = $skipped = $errors = 0;

        $signals = $this->signals->getSignalsForDate($date);

        foreach ($signals as $signal) {
            $optionType = $this->decideSide($config->signal_mode, $signal['trade_action']);
            $lots = $config->quantity;
            if ($lots <= 0) { $skipped++; continue; }

            $signalTime = $signal['signal_time'];

            // Dedupe: exact same snapshot for this symbol+side already handled today?
            $exactExists = CpMultiTimeOrder::where('cp_multi_time_order_config_id', $config->id)
                ->where('symbol', $signal['symbol'])
                ->where('signal_date', $date)
                ->where('option_type', $optionType)
                ->where('signal_time', $signalTime)
                ->exists();
            if ($exactExists) { $skipped++; continue; }

            // Rule 1: option price ceiling as % of underlying
            if ($config->max_price_pct_of_underlying !== null) {
                $ceiling = $signal['underlying_price'] * ($config->max_price_pct_of_underlying / 100);
                if ($signal['option_price'] <= 0 || $signal['option_price'] > $ceiling) { $skipped++; continue; }
            }

            // Rule 2: re-entry needs a lower price than the last SAME-SIDE order today
            if ($config->reentry_min_drop_pct !== null) {
                $lastSameSide = CpMultiTimeOrder::where('cp_multi_time_order_config_id', $config->id)
                    ->where('symbol', $signal['symbol'])
                    ->where('signal_date', $date)
                    ->where('option_type', $optionType)
                    ->where('is_order_placed', true)
                    ->orderByDesc('order_placed_at')
                    ->first();

                if ($lastSameSide) {
                    $requiredCeiling = (float) $lastSameSide->order_price * (1 - $config->reentry_min_drop_pct / 100);
                    if ($signal['option_price'] <= 0 || $signal['option_price'] > $requiredCeiling) { $skipped++; continue; }
                }
            }

            try {
                $connector  = $this->getConnector($config);
                $instrument = $this->resolveAtmInstrument($signal['symbol'], $optionType, $date);
                $ltp        = (float) ($instrument->close ?? $instrument->open ?? 0);
                $price      = $config->applyDiscount($ltp);

                $result = $connector->placeOrder([
                    'trading_symbol'   => $instrument->trading_symbol,
                    'instrument_token' => $instrument->instrument_token,
                    'base_symbol'      => $instrument->base_symbol,
                    'expiry_date'      => $instrument->expiry_date,
                    'strike'           => $instrument->strike,
                    'instrument_type'  => $optionType,
                    'transaction_type' => 'BUY',
                    'order_type'       => $config->order_type,
                    'product'          => $config->product,
                    'lots'             => $lots,
                    'price'            => $price,
                ]);

                CpMultiTimeOrder::create([
                    'user_id'                       => $config->user_id,
                    'cp_multi_time_order_config_id' => $config->id,
                    'broker_api_id'                 => $config->broker_api_id,
                    'broker_type'                   => $config->broker_type,
                    'symbol'                        => $signal['symbol'],
                    'option_symbol'                 => $instrument->trading_symbol,
                    'option_token'                  => $instrument->instrument_token,
                    'option_type'                   => $optionType,
                    'strike'                        => $instrument->strike ?? null,
                    'signal_date'                   => $date,
                    'signal_time'                   => $signalTime,
                    'signal_action'                 => $signal['trade_action'],
                    'transaction_type'               => 'BUY',
                    'order_type'                     => $config->order_type,
                    'product'                        => $config->product,
                    'lots'                           => $lots,
                    'quantity'                        => $result['quantity'],
                    'order_price'                     => $price,
                    'broker_order_id'                 => $result['order_id'],
                    'broker_status'                   => 'OPEN',
                    'is_order_placed'                 => true,
                    'order_placed_at'                 => now(),
                    'meta'                             => $signal,
                ]);
                $placed++;

            } catch (\Exception $e) {
                Log::error("CpMultiTimeOrderPlacementService: {$config->id}/{$signal['symbol']}/{$optionType}/{$signalTime}: {$e->getMessage()}");
                CpMultiTimeOrder::create([
                    'user_id' => $config->user_id, 'cp_multi_time_order_config_id' => $config->id,
                    'broker_api_id' => $config->broker_api_id, 'broker_type' => $config->broker_type,
                    'symbol' => $signal['symbol'], 'option_type' => $optionType,
                    'signal_date' => $date, 'signal_time' => $signalTime,
                    'signal_action' => $signal['trade_action'], 'transaction_type' => 'BUY',
                    'order_type' => $config->order_type, 'product' => $config->product,
                    'lots' => $lots, 'quantity' => 0, 'broker_status' => 'ERROR',
                    'is_order_placed' => false, 'error_message' => $e->getMessage(),
                    'meta' => $signal,
                ]);
                $errors++;
            }
        }

        return compact('placed', 'skipped', 'errors');
    }

    private function decideSide(string $mode, string $tradeAction): string
    {
        $side = $tradeAction === 'BUY CE' ? 'CE' : 'PE';
        return $mode === 'align' ? $side : ($side === 'CE' ? 'PE' : 'CE');
    }

    private function getConnector(CpMultiTimeOrderConfig $config): AngelBrokerService|ZerodhaBrokerService
    {
        return match ($config->broker_type) {
            'AngelOne' => new AngelBrokerService($config->broker),
            'Zerodha'  => new ZerodhaBrokerService($config->broker),
        };
    }

    private function resolveAtmInstrument(string $symbol, string $optionType, string $date)
    {
        return \App\Models\OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->where('strike_position', 'ATM')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderByDesc('interval_time')
            ->firstOrFail();
    }
}