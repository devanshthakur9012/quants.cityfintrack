<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PortfolioConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PortfolioConfigController extends Controller
{
    /**
     * Display config settings page
     */
    public function index()
    {
        $pageTitle = 'Portfolio Settings';
        
        // Get user's config
        $config = PortfolioConfig::getForUser(auth()->id());

        return view($this->activeTemplate . 'user.portfolio.config', compact('pageTitle', 'config'));
    }

    /**
     * Update config settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'old_position_profit_percent' => 'required|numeric|min:0|max:100',
            'fresh_position_profit_percent' => 'required|numeric|min:0|max:100',
            'old_position_sell_profit_percent' => 'required|numeric|min:0|max:100',
            'fresh_position_sell_profit_percent' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $config = PortfolioConfig::getForUser(auth()->id());
            
            $config->update([
                'old_position_profit_percent' => $request->old_position_profit_percent,
                'fresh_position_profit_percent' => $request->fresh_position_profit_percent,
                'old_position_sell_profit_percent' => $request->old_position_sell_profit_percent,
                'fresh_position_sell_profit_percent' => $request->fresh_position_sell_profit_percent,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully!',
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Config Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset config to defaults
     */
    public function reset()
    {
        try {
            $config = PortfolioConfig::getForUser(auth()->id());
            
            $config->update([
                'old_position_profit_percent' => 20.00,
                'fresh_position_profit_percent' => 10.00,
                'old_position_sell_profit_percent' => 20.00,
                'fresh_position_sell_profit_percent' => 10.00,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings reset to defaults successfully!',
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Config Reset Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error resetting settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current config as JSON
     */
    public function getConfig()
    {
        try {
            $config = PortfolioConfig::getForUser(auth()->id());
            
            return response()->json([
                'success' => true,
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Get Config Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching config: ' . $e->getMessage()
            ], 500);
        }
    }
}