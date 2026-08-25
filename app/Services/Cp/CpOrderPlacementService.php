<?php
// FILE: app/Services/Cp/CpOrderPlacementService.php
// REPLACES your existing file — same behavior, refactored so a single
// config can be run in isolation (needed by the per-minute cron), while
// run() is kept as a "run everything active right now" convenience method
// for manual testing / artisan tinker / an admin "Run Now" button.

namespace App\Services\Cp;

use App\Models\CpOrderConfig;
use App\Models\CpOrder;
use App\Services\Broker\AngelBrokerService;
use App\Services\Broker\ZerodhaBrokerService;
use Illuminate\Support\Facades\Log;

class CpOrderPlacementService
{
    public function __construct(private CpAnalysisSignalResolver $resolver) {}

    /**
     * Run EVERY active config regardless of trigger time.
     * Useful for manual testing (`php artisan tinker` / an admin "Run Now"
     * button) — the cron does NOT call this, it calls runForConfig() below
     * only when a config's analysis trigger_time matches the clock.
     */
    public function run(string $date): array
    {
        $totals = ['placed' => 0, 'skipped' => 0, 'errors' => 0];

        $configs = CpOrderConfig::with(['analysis', 'broker'])
            ->where('status', true)
            ->get();

        foreach ($configs as $config) {
            $result = $this->runForConfig($config, $date);
            $totals['placed']  += $result['placed'];
            $totals['skipped'] += $result['skipped'];
            $totals['errors']  += $result['errors'];
        }

        return $totals;
    }

    /**
     * Run ONE config: resolve its analysis's signals for $date, place
     * orders for whichever symbols/sides qualify, skip duplicates.
     * This is the method the per-minute cron calls.
     */
    public function runForConfig(CpOrderConfig $config, string $date): array
    {
        $placed = $skipped = $errors = 0;

        if (!$config->analysis) {
            Log::warning("CpOrderPlacementService: config #{$config->id} has no linked analysis, skipping.");
            return compact('placed', 'skipped', 'errors');
        }

        $signals = $this->resolver->resolve($config->analysis, $date);

        foreach ($signals as $signal) {
            $optionType = $this->decideSide($config->signal_mode, $signal['action']);
            $lots = $config->quantity;
            if ($lots <= 0) { $skipped++; continue; }

            $exists = CpOrder::where('cp_order_config_id', $config->id)
                ->where('symbol', $signal['symbol'])
                ->where('signal_date', $date)
                ->where('option_type', $optionType)
                ->exists();
            if ($exists) { $skipped++; continue; }

            try {
                $connector  = $this->getConnector($config);
                $instrument = $this->resolveAtmInstrument($signal['symbol'], $optionType, $date);
                $ltp        = (float) ($instrument->close ?? $instrument->open ?? 0);
                $price      = $config->applyDiscount($ltp);

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

        return compact('placed', 'skipped', 'errors');
    }

    /** align → same side as signal | opposite → flip CE/PE */
    private function decideSide(string $mode, string $action): string
    {
        $side = $action === 'BUY_CE' ? 'CE' : 'PE';
        return $mode === 'align' ? $side : ($side === 'CE' ? 'PE' : 'CE');
    }

    private function getConnector(CpOrderConfig $config): AngelBrokerService|ZerodhaBrokerService
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