<?php
// FILE: app/Services/Cp/CpMultiTimeOrderPlacementService.php

namespace App\Services\Cp;

use App\Models\CpMultiTimeOrderConfig;
use App\Models\CpMultiTimeOrder;
use App\Services\Broker\AngelBrokerService;
use App\Services\Broker\ZerodhaBrokerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated placement service for OI Flow Multi-Snapshot. Talks ONLY to
 * OIFlowMultiTimeService (signals) and the common broker services
 * (AngelBrokerService / ZerodhaBrokerService) for execution. Nothing
 * here touches cp_order_configs / cp_orders / CpAnalysisSignalResolver —
 * fully isolated from the OI Flow Sentiment (single-snapshot) flow.
 *
 * Paced with usleep() between each broker call — with up to 17 symbols x
 * 3 snapshots, this can fire 50+ orders in a single run; without pacing,
 * Angel's API intermittently returns a non-JSON/empty response under
 * rapid-fire load (surfaces as "invalid JSON" errors here), which looks
 * like a bug but is actually rate limiting. Matches the pacing already
 * used in CpCollectOption/CpCollectFut for the same reason.
 */
class CpMultiTimeOrderPlacementService
{
    // Delay between each individual broker order call. 300ms mirrors the
    // pacing already used in CpCollectFut for the same class of problem.
    private const ORDER_PACE_MICROSECONDS = 300_000;

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

        // Only act on the snapshot(s) this config opted into. An empty/null
        // snapshot_times is treated as "all snapshots" for backward
        // compatibility with any row created before this field existed.
        $allowedTimes = $config->snapshot_times ?? [];

        foreach ($signals as $signal) {
            $signalTime = $signal['signal_time'];

            if (!empty($allowedTimes) && !in_array($signalTime, $allowedTimes, true)) {
                continue; // not this config's concern — not counted as skipped
            }

            $optionType = $this->decideSide($config->signal_mode, $signal['trade_action']);
            $lots = $config->quantity;
            if ($lots <= 0) { $skipped++; continue; }

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
                    'symbol'                         => $signal['symbol'],
                    'option_symbol'                  => $instrument->trading_symbol,
                    'option_token'                   => $instrument->instrument_token,
                    'option_type'                    => $optionType,
                    'strike'                          => $instrument->strike ?? null,
                    'signal_date'                     => $date,
                    'signal_time'                     => $signalTime,
                    'signal_action'                   => $signal['trade_action'],
                    'transaction_type'                => 'BUY',
                    'order_type'                       => $config->order_type,
                    'product'                          => $config->product,
                    'lots'                             => $lots,
                    'quantity'                         => $result['quantity'],
                    'order_price'                      => $price,
                    'broker_order_id'                  => $result['order_id'],
                    'broker_status'                    => 'OPEN',
                    'is_order_placed'                  => true,
                    'order_placed_at'                  => now(),
                    'meta'                              => $signal,
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

            // Pace every broker call — success or failure — so Angel's API
            // isn't hit with 50+ requests back-to-back. See class docblock.
            usleep(self::ORDER_PACE_MICROSECONDS);
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

    /**
     * Queries cp_option_ohlc_15min directly, scoped by analysis_config_id —
     * NOT App\Models\OptionOhlcData (a stale/unrelated model). Same fix
     * applied earlier to CpOrderPlacementService's version of this method.
     */
    private function resolveAtmInstrument(string $symbol, string $optionType, string $date)
    {
        $config = DB::table('analysis_configs')
            ->where('time_frame', '15min')
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            throw new \Exception("resolveAtmInstrument: no active 15min analysis_config found for {$symbol}");
        }

        $row = DB::table('cp_option_ohlc_15min')
            ->where('analysis_config_id', $config->id)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->where('strike_position', 'ATM')
            ->whereDate('trade_date', $date)
            ->where('is_missing', false)
            ->orderByDesc('interval_time')
            ->first();

        if (!$row) {
            throw new \Exception("resolveAtmInstrument: no ATM {$optionType} row for {$symbol} on {$date}");
        }

        return $row;
    }
}