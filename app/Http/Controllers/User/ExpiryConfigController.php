<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ExpiryConfig;
use App\Models\ExpiryMonitored;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class ExpiryConfigController extends Controller
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
     * ✅ NOW RECALCULATES BOTH EXPIRY DATA AND SYMBOL DATA
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
            $outputs = [];

            // ✅ 1. Recalculate EXPIRY DATA (future_data table)
            \Log::info("🔄 Recalculating EXPIRY indicators for {$symbol}");
            
            Artisan::call('expiry:fetch-1min', [
                '--symbol' => $symbol,
                '--force' => true,
                '--backfill' => true
            ]);

            $outputs['expiry_1min'] = Artisan::output();

            // ✅ 2. Recalculate SYMBOL DATA (symbol_data table) - All timeframes
            \Log::info("🔄 Recalculating SYMBOL indicators for {$symbol}");
            
            // Check if the symbol exists in symbol_monitored
            $symbolMonitored = \App\Models\SymbolMonitored::where('trading_symbol', $symbol)->first();
            
            if ($symbolMonitored) {
                // Recalculate for all timeframes (1min, 5min, 15min)
                Artisan::call('symbols:fetch-1min', [
                    '--symbol' => $symbol,
                    '--force' => true
                ]);
                $outputs['symbol_1min'] = Artisan::output();

                Artisan::call('symbols:fetch-5min', [
                    '--symbol' => $symbol,
                    '--force' => true
                ]);
                $outputs['symbol_5min'] = Artisan::output();

                Artisan::call('symbols:fetch-15min', [
                    '--symbol' => $symbol,
                    '--force' => true
                ]);
                $outputs['symbol_15min'] = Artisan::output();
            } else {
                $outputs['symbol_note'] = "Symbol {$symbol} not found in symbol_monitored table, skipping symbol data recalculation";
                \Log::warning($outputs['symbol_note']);
            }

            return response()->json([
                'success' => true,
                'message' => "Indicators recalculated for {$symbol} (both expiry and symbol data)",
                'output' => $outputs
            ]);

        } catch (\Exception $e) {
            \Log::error("Recalculation error for {$symbol}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate all indicators (all symbols)
     * ✅ NOW RECALCULATES BOTH EXPIRY DATA AND SYMBOL DATA
     */
    public function recalculateAll(Request $request)
    {
        try {
            $outputs = [];

            // ✅ 1. Recalculate ALL EXPIRY DATA
            \Log::info("🔄 Recalculating ALL EXPIRY indicators");
            
            Artisan::call('expiry:fetch-1min', [
                '--force' => true, 
                '--backfill' => true
            ]);
            $outputs['expiry_1min'] = Artisan::output();

            // ✅ 2. Recalculate ALL SYMBOL DATA (all timeframes)
            \Log::info("🔄 Recalculating ALL SYMBOL indicators");
            
            Artisan::call('symbols:fetch-1min', ['--force' => true]);
            $outputs['symbol_1min'] = Artisan::output();

            Artisan::call('symbols:fetch-5min', ['--force' => true]);
            $outputs['symbol_5min'] = Artisan::output();

            Artisan::call('symbols:fetch-15min', ['--force' => true]);
            $outputs['symbol_15min'] = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'All indicators recalculated successfully (both expiry and symbol data)!',
                'output' => $outputs
            ]);

        } catch (\Exception $e) {
            \Log::error("Recalculation error (all): " . $e->getMessage());
            
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