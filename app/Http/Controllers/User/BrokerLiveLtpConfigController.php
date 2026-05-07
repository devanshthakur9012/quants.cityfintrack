<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerLiveLtpConfig;
use App\Models\BrokerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrokerLiveLtpConfigController extends Controller
{
    /**
     * Display Live LTP config settings page
     */
    public function index()
    {
        $pageTitle = 'Live LTP SELL Order Configuration';
        
        // Get user's brokers
        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        // Get all configs
        $configs = BrokerLiveLtpConfig::where('user_id', auth()->id())
            ->with('brokerApi')
            ->get();

        return view($this->activeTemplate . 'user.portfolio.live_ltp_config', compact('pageTitle', 'brokers', 'configs'));
    }

    /**
     * Store new Live LTP config
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'symbol_type' => 'required|string|in:CE,PE,BOTH',
            'profit_percent' => 'required|numeric|min:0|max:100',
        ]);

        try {
            // Verify broker belongs to user
            $broker = BrokerApi::where('id', $request->broker_api_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // Check if config already exists
            $existing = BrokerLiveLtpConfig::where('broker_api_id', $request->broker_api_id)
                ->where('symbol_type', $request->symbol_type)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration already exists for this broker and symbol type. Please edit it instead.'
                ], 422);
            }

            $config = BrokerLiveLtpConfig::create([
                'user_id' => auth()->id(),
                'broker_api_id' => $request->broker_api_id,
                'symbol_type' => $request->symbol_type,
                'profit_percent' => $request->profit_percent,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Live LTP Configuration added successfully!',
                'data' => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('Live LTP Config Store Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error adding configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing Live LTP config
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'profit_percent' => 'required|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $config = BrokerLiveLtpConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $config->update($request->only([
                'profit_percent',
                'is_active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Live LTP Configuration updated successfully!',
                'data' => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('Live LTP Config Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Live LTP config
     */
    public function destroy($id)
    {
        try {
            $config = BrokerLiveLtpConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $config->delete();

            return response()->json([
                'success' => true,
                'message' => 'Live LTP Configuration deleted successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Live LTP Config Delete Error: ' . $e->getMessage());
            
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
            $config = BrokerLiveLtpConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $config->is_active = !$config->is_active;
            $config->save();

            $status = $config->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'message' => "Configuration {$status} successfully!",
                'data' => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('Live LTP Config Toggle Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error toggling configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute Live LTP orders manually
     */
    public function execute()
    {
        try {
            // Run the Live LTP command
            $exitCode = \Artisan::call('positions:place-live-ltp-orders');
            $output = \Artisan::output();

            // Parse the output for summary
            $summary = $this->parseCommandOutput($output);

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Live LTP orders executed successfully!',
                    'summary' => $summary,
                    'output' => $output
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Live LTP execution completed with errors. Check logs for details.',
                    'summary' => $summary,
                    'output' => $output
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Manual Live LTP Execution Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error executing Live LTP orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse command output to extract summary
     */
    private function parseCommandOutput($output)
    {
        $summary = [
            'total_positions' => 0,
            'ce_orders' => 0,
            'pe_orders' => 0,
            'failed_orders' => 0
        ];

        // Extract numbers from output
        if (preg_match('/Total Positions:\s*(\d+)/', $output, $matches)) {
            $summary['total_positions'] = (int)$matches[1];
        }
        if (preg_match('/CE:\s*(\d+)/', $output, $matches)) {
            $summary['ce_orders'] = (int)$matches[1];
        }
        if (preg_match('/PE:\s*(\d+)/', $output, $matches)) {
            $summary['pe_orders'] = (int)$matches[1];
        }
        if (preg_match('/Failed Orders:\s*(\d+)/', $output, $matches)) {
            $summary['failed_orders'] = (int)$matches[1];
        }

        return $summary;
    }
}