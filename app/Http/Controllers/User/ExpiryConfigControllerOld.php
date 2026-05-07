<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ExpiryConfig;
use App\Models\ExpiryMonitored;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class ExpiryConfigControllerOld extends Controller
{
    /**
     * Show expiry indicator configuration page
     */
    public function index()
    {
        $pageTitle = 'Expiry Trading - Supertrend Configuration';
        
        $globalConfig = ExpiryConfig::where('scope', 'global')
            ->where('name', 'default')
            ->first();
        
        $symbolConfigs = ExpiryConfig::where('scope', 'symbol')
            ->orderBy('symbol')
            ->get();
        
        $monitoredSymbols = ExpiryMonitored::where('is_active', true)
            ->orderBy('symbol')
            ->get();
        
        $descriptions = $this->getIndicatorDescriptions();
        
        return view($this->activeTemplate . 'user.expiry.config', compact(
            'pageTitle',
            'globalConfig',
            'symbolConfigs',
            'monitoredSymbols',
            'descriptions'
        ));
    }

    /**
     * Update global configuration
     */
    public function updateGlobal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supertrend_atr_period' => 'required|integer|min:1|max:100',
            'supertrend_multiplier' => 'required|numeric|min:0.1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $config = ExpiryConfig::where('scope', 'global')
                ->where('name', 'default')
                ->first();

            if (!$config) {
                $config = new ExpiryConfig([
                    'scope' => 'global',
                    'name' => 'default',
                    'is_active' => true
                ]);
            }

            $config->supertrend_atr_period = $request->supertrend_atr_period;
            $config->supertrend_multiplier = $request->supertrend_multiplier;
            $config->save();

            ExpiryConfig::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Global expiry configuration updated successfully!'
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
            'symbol' => 'required|string',
            'supertrend_atr_period' => 'required|integer|min:1|max:100',
            'supertrend_multiplier' => 'required|numeric|min:0.1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $symbol = $request->symbol;

            $config = ExpiryConfig::updateOrCreate(
                [
                    'scope' => 'symbol',
                    'symbol' => $symbol
                ],
                [
                    'name' => $symbol,
                    'supertrend_atr_period' => $request->supertrend_atr_period,
                    'supertrend_multiplier' => $request->supertrend_multiplier,
                    'is_active' => true
                ]
            );

            ExpiryConfig::clearCache($symbol);

            return response()->json([
                'success' => true,
                'message' => "Configuration saved for {$symbol}!",
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
            $config = ExpiryConfig::findOrFail($id);
            $symbol = $config->symbol;
            
            $config->delete();
            ExpiryConfig::clearCache($symbol);

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
            'symbol' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $symbol = $request->symbol;

            Artisan::call('expiry:fetch-1min', [
                '--symbol' => $symbol,
                '--force' => true,
                '--backfill' => true
            ]);

            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => "Indicators recalculated for {$symbol}",
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
     * Recalculate all indicators (all symbols)
     */
    public function recalculateAll(Request $request)
    {
        try {
            Artisan::call('expiry:fetch-1min', ['--force' => true, '--backfill' => true]);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'All expiry indicators recalculated successfully!',
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
     * Get indicator descriptions
     */
    private function getIndicatorDescriptions()
    {
        return [
            'supertrend' => [
                'atr_period' => 'Number of periods for ATR calculation. Higher values make the indicator less sensitive to short-term price changes. Default: 10',
                'multiplier' => 'Multiplier applied to ATR to calculate bands. Higher values create wider bands. Default: 3.0'
            ]
        ];
    }
}
