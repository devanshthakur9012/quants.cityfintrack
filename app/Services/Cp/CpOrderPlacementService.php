<?php

namespace App\Services\Cp;

use App\Contracts\CpBrokerConnectorInterface;
use App\Models\CpOrderConfig;
use App\Models\CpOrder;
use App\Services\Broker\AngelBrokerConnector;
use App\Services\Broker\ZerodhaBrokerConnector;
use Illuminate\Support\Facades\Log;

class CpOrderPlacementService
{
    public function __construct(private CpAnalysisSignalResolver $resolver) {}

    public function run(string $date): array
    {
        $placed = $skipped = $errors = 0;

        $configs = CpOrderConfig::with(['analysis', 'broker'])
            ->where('status', true)
            ->get();

        foreach ($configs as $config) {
            $signals = $this->resolver->resolve($config->analysis, $date);

            foreach ($signals as $signal) {
                $optionType = $this->decideSide($config->signal_mode, $signal['action']);
                $lots = $optionType === 'CE' ? $config->ce_quantity : $config->pe_quantity;
                if ($lots <= 0) { $skipped++; continue; }

                $exists = CpOrder::where('cp_order_config_id', $config->id)
                    ->where('symbol', $signal['symbol'])
                    ->where('signal_date', $date)
                    ->where('option_type', $optionType)
                    ->exists();
                if ($exists) { $skipped++; continue; }

                try {
                    $connector = $this->getConnector($config);
                    $instrument = $this->resolveAtmInstrument($signal['symbol'], $optionType, $date); // your existing ATM-lookup logic
                    $ltp = (float) ($instrument->close ?? $instrument->open ?? 0);
                    $price = $config->applyDiscount($ltp);

                    $result = $connector->placeOrder([
                        'trading_symbol'   => $instrument->trading_symbol,
                        'instrument_token' => $instrument->instrument_token,
                        'transaction_type' => 'BUY',
                        'order_type'       => $config->order_type,
                        'product'          => $config->product,
                        'lots'             => $lots,
                        'price'            => $price,
                    ]);

                    CpOrder::create([
                        'user_id'            => $config->user_id,
                        'cp_order_config_id' => $config->id,
                        'cp_analysis_id'     => $config->cp_analysis_id,
                        'broker_api_id'      => $config->broker_api_id,
                        'broker_type'        => $config->broker_type,
                        'symbol'             => $signal['symbol'],
                        'option_symbol'      => $instrument->trading_symbol,
                        'option_token'       => $instrument->instrument_token,
                        'option_type'        => $optionType,
                        'strike'             => $instrument->strike ?? null,
                        'signal_date'        => $date,
                        'signal_action'      => $signal['action'],
                        'transaction_type'   => 'BUY',
                        'order_type'         => $config->order_type,
                        'product'            => $config->product,
                        'lots'               => $lots,
                        'quantity'           => $result['quantity'],
                        'order_price'        => $price,
                        'broker_order_id'    => $result['order_id'],
                        'broker_status'      => 'OPEN',
                        'is_order_placed'    => true,
                        'order_placed_at'    => now(),
                        'meta'               => $signal['meta'],
                    ]);
                    $placed++;

                } catch (\Exception $e) {
                    Log::error("CpOrderPlacementService: {$config->id}/{$signal['symbol']}/{$optionType}: {$e->getMessage()}");
                    CpOrder::create([
                        'user_id' => $config->user_id, 'cp_order_config_id' => $config->id,
                        'cp_analysis_id' => $config->cp_analysis_id, 'broker_api_id' => $config->broker_api_id,
                        'broker_type' => $config->broker_type, 'symbol' => $signal['symbol'],
                        'option_type' => $optionType, 'signal_date' => $date,
                        'signal_action' => $signal['action'], 'transaction_type' => 'BUY',
                        'order_type' => $config->order_type, 'product' => $config->product,
                        'lots' => $lots, 'quantity' => 0, 'broker_status' => 'ERROR',
                        'is_order_placed' => false, 'error_message' => $e->getMessage(),
                        'meta' => $signal['meta'],
                    ]);
                    $errors++;
                }
            }
        }

        return compact('placed', 'skipped', 'errors');
    }

    /** align → same side as signal | opposite → flip CE/PE */
    private function decideSide(string $mode, string $action): string
    {
        $side = $action === 'BUY_CE' ? 'CE' : 'PE';
        return $mode === 'align' ? $side : ($side === 'CE' ? 'PE' : 'CE');
    }

    private function getConnector(CpOrderConfig $config): CpBrokerConnectorInterface
    {
        return match ($config->broker_type) {
            'AngelOne' => new AngelBrokerConnector($config->broker),
            'Zerodha'  => new ZerodhaBrokerConnector($config->broker),
        };
    }

    private function resolveAtmInstrument(string $symbol, string $optionType, string $date)
    {
        // Reuse your existing ATM-candle lookup (same pattern as
        // PlacePivotOrders::getAtmCandle / OIIVAutoController's ATM query)
        return \App\Models\OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->where('strike_position', 'ATM')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderByDesc('interval_time')
            ->firstOrFail();
    }
}