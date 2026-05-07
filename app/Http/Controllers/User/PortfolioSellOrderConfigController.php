<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PortfolioSellOrderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PortfolioSellOrderConfigController extends Controller
{
    /**
     * Display config settings page
     */
    public function index()
    {
        $pageTitle = 'Sell Order Configuration';
        
        // Get all configs for user
        $configs = PortfolioSellOrderConfig::where('user_id', auth()->id())
            ->orderBy('symbol_type')
            ->get();

        return view($this->activeTemplate . 'user.portfolio.sell_order_config', compact('pageTitle', 'configs'));
    }

    /**
     * Store new config
     */
    public function store(Request $request)
    {
        $request->validate([
            'symbol_type' => 'required|string|in:CE,PE,EQUITY',
            'old_position_profit_percent' => 'required|numeric|min:0|max:100',
            'fresh_position_profit_percent' => 'required|numeric|min:0|max:100',
        ]);

        try {
            // Check if config already exists
            $existing = PortfolioSellOrderConfig::where('user_id', auth()->id())
                ->where('symbol_type', $request->symbol_type)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration for ' . $request->symbol_type . ' already exists. Please edit it instead.'
                ], 422);
            }

            $config = PortfolioSellOrderConfig::create([
                'user_id' => auth()->id(),
                'symbol_type' => $request->symbol_type,
                'old_position_profit_percent' => $request->old_position_profit_percent,
                'fresh_position_profit_percent' => $request->fresh_position_profit_percent,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Configuration added successfully!',
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Config Store Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error adding configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing config
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'old_position_profit_percent' => 'required|numeric|min:0|max:100',
            'fresh_position_profit_percent' => 'required|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $config = PortfolioSellOrderConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $config->update($request->only([
                'old_position_profit_percent',
                'fresh_position_profit_percent',
                'is_active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully!',
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Config Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete config
     */
    public function destroy($id)
    {
        try {
            $config = PortfolioSellOrderConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $symbolType = $config->symbol_type;
            $config->delete();

            return response()->json([
                'success' => true,
                'message' => "Configuration for {$symbolType} deleted successfully!"
            ]);

        } catch (\Exception $e) {
            Log::error('Config Delete Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status
     */
    public function toggleActive($id)
    {
        try {
            $config = PortfolioSellOrderConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $config->is_active = !$config->is_active;
            $config->save();

            $status = $config->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'message' => "Configuration {$status} successfully!",
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Config Toggle Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error toggling configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all configs as JSON
     */
    public function getConfigs()
    {
        try {
            $configs = PortfolioSellOrderConfig::where('user_id', auth()->id())
                ->orderBy('symbol_type')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $configs
            ]);

        } catch (\Exception $e) {
            Log::error('Get Configs Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching configurations: ' . $e->getMessage()
            ], 500);
        }
    }
}