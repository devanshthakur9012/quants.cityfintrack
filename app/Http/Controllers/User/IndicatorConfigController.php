<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\IndicatorConfig;
use App\Models\FuturesMonitored;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IndicatorConfigController extends Controller
{
    /**
     * Show indicator configuration page
     */
    public function index()
    {
        $pageTitle = 'Indicator Configuration';
        
        $globalConfig = IndicatorConfig::where('scope', 'global')
            ->where('name', 'default')
            ->first();
        
        $symbolConfigs = IndicatorConfig::where('scope', 'symbol')
            ->orderBy('trading_symbol')
            ->get();
        
        $monitoredFutures = FuturesMonitored::where('is_active', true)
            ->orderBy('trading_symbol')
            ->get();
        
        $descriptions = IndicatorConfig::getIndicatorDescriptions();
        
        return view($this->activeTemplate . 'user.futures.indicator-config', compact(
            'pageTitle',
            'globalConfig',
            'symbolConfigs',
            'monitoredFutures',
            'descriptions'
        ));
    }

    public function updateGlobal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supertrend_atr_period' => 'required|integer|min:1|max:100',
            'supertrend_multiplier' => 'required|numeric|min:0.1|max:10',
            'donchian_high_period' => 'required|integer|min:1|max:100',
            'donchian_low_period' => 'required|integer|min:1|max:100',
            'donchian_risk_reward' => 'required|numeric|min:0.5|max:10',
            'rsi_period' => 'required|integer|min:2|max:100',
            'rsi_overbought' => 'required|numeric|min:50|max:100',
            'rsi_oversold' => 'required|numeric|min:0|max:50',
            'macd_fast_period' => 'required|integer|min:1|max:50',
            'macd_slow_period' => 'required|integer|min:1|max:100',
            'macd_signal_period' => 'required|integer|min:1|max:50',
            'vwap_reset_daily' => 'required|boolean',
            'vwap_band_multiplier' => 'required|numeric|min:0.1|max:5',
            'vwap_band_period' => 'required|integer|min:5|max:100',
            'vwap_distance_percent' => 'required|numeric|min:0.1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $config = IndicatorConfig::where('scope', 'global')
                ->where('name', 'default')
                ->first();

            if (!$config) {
                $config = new IndicatorConfig([
                    'scope' => 'global',
                    'name' => 'default',
                    'is_active' => true
                ]);
            }

            $config->fill($request->only([
                'supertrend_atr_period',
                'supertrend_multiplier',
                'donchian_high_period',
                'donchian_low_period',
                'donchian_risk_reward',
                'rsi_period',
                'rsi_overbought',
                'rsi_oversold',
                'macd_fast_period',
                'macd_slow_period',
                'macd_signal_period',
                'vwap_reset_daily',
                'vwap_band_multiplier',
                'vwap_band_period',
                'vwap_distance_percent',
            ]));

            $config->save();

            IndicatorConfig::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Global configuration updated successfully!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update or create symbol-specific configuration
     */
    public function updateSymbol(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trading_symbol' => 'required|string',
            'supertrend_atr_period' => 'required|integer|min:1|max:100',
            'supertrend_multiplier' => 'required|numeric|min:0.1|max:10',
            'donchian_high_period' => 'required|integer|min:1|max:100',
            'donchian_low_period' => 'required|integer|min:1|max:100',
            'donchian_risk_reward' => 'required|numeric|min:0.5|max:10',
            'rsi_period' => 'required|integer|min:2|max:100',
            'rsi_overbought' => 'required|numeric|min:50|max:100',
            'rsi_oversold' => 'required|numeric|min:0|max:50',
            'macd_fast_period' => 'required|integer|min:1|max:50',
            'macd_slow_period' => 'required|integer|min:1|max:100',
            'macd_signal_period' => 'required|integer|min:1|max:50',
            'vwap_reset_daily' => 'required|boolean',
            'vwap_band_multiplier' => 'required|numeric|min:0.1|max:5',
            'vwap_band_period' => 'required|integer|min:5|max:100',
            'vwap_distance_percent' => 'required|numeric|min:0.1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $tradingSymbol = $request->trading_symbol;

            $config = IndicatorConfig::updateOrCreate(
                [
                    'scope' => 'symbol',
                    'trading_symbol' => $tradingSymbol
                ],
                [
                    'name' => $tradingSymbol,
                    'supertrend_atr_period' => $request->supertrend_atr_period,
                    'supertrend_multiplier' => $request->supertrend_multiplier,
                    'donchian_high_period' => $request->donchian_high_period,
                    'donchian_low_period' => $request->donchian_low_period,
                    'donchian_risk_reward' => $request->donchian_risk_reward,
                    'rsi_period' => $request->rsi_period,
                    'rsi_overbought' => $request->rsi_overbought,
                    'rsi_oversold' => $request->rsi_oversold,
                    'macd_fast_period' => $request->macd_fast_period,
                    'macd_slow_period' => $request->macd_slow_period,
                    'macd_signal_period' => $request->macd_signal_period,
                    'vwap_reset_daily' => $request->vwap_reset_daily,
                    'vwap_band_multiplier' => $request->vwap_band_multiplier,
                    'vwap_band_period' => $request->vwap_band_period,
                    'vwap_distance_percent' => $request->vwap_distance_percent,
                    'is_active' => true
                ]
            );

            IndicatorConfig::clearCache($tradingSymbol);

            return response()->json([
                'success' => true,
                'message' => "Configuration saved for {$tradingSymbol}!",
                'config' => $config
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete symbol-specific configuration
     */
    public function deleteSymbol($id)
    {
        try {
            $config = IndicatorConfig::findOrFail($id);
            $symbol = $config->trading_symbol;
            
            $config->delete();
            IndicatorConfig::clearCache($symbol);

            return response()->json([
                'success' => true,
                'message' => "Configuration deleted for {$symbol}. It will now use global defaults."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate indicators for a specific symbol
     */
    public function recalculateIndicators(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trading_symbol' => 'required|string',
            'interval' => 'required|in:minute,5minute,15minute'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $symbol = $request->trading_symbol;
            $interval = $request->interval;

            // $command = match($interval) {
            //     'minute' => 'futures:fetch-1min',
            //     '5minute' => 'futures:fetch-5min',
            //     '15minute' => 'futures:fetch-15min',
            //     default => 'futures:fetch-1min'
            // };

            Artisan::call('symbols:fetch-15min', [
                // '--symbol' => $symbol,
                '--force' => true,
                '--backfill' => true
            ]);

            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => "Indicators recalculated for {$symbol} ({$interval})",
                'output' => $output
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate all indicators (all symbols, all intervals)
     */
    // public function recalculateAll(Request $request)
    // {
    //     try {
    //         // Artisan::call('futures:fetch-1min', ['--force' => true, '--backfill' => true]);
    //         // 

    //         // Artisan::call('futures:fetch-5min', ['--force' => true, '--backfill' => true]);
    //         // $output5min = Artisan::output();

    //         // Artisan::call('futures:fetch-15min', ['--force' => true, '--backfill' => true]);
    //         // $output15min = Artisan::output();

    //         Artisan::call('symbols:fetch-5min', [ '--force' => true, '--backfill' => true ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'All indicators recalculated successfully!',
    //             // 'output' => "1-Min:\n" . $output1min . "\n\n5-Min:\n" . $output5min . "\n\n15-Min:\n" . $output15min
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Recalculate all indicators based on existing data (no new fetch)
     */
    public function recalculateAll(Request $request)
    {
        try {
            Artisan::call('indicators:recalculate --interval=5minute');
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Supertrend recalculated successfully for all 5-minute data!',
                'output' => $output
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

}