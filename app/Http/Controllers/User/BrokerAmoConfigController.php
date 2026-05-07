<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerAmoConfig;
use App\Models\BrokerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BrokerAmoConfigController extends Controller
{
    /**
     * Display AMO config settings page
     */
    public function index()
    {
        $pageTitle = 'AMO Configuration';
        
        // Get user's brokers
        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        // Get today's configs
        $configs = BrokerAmoConfig::where('user_id', auth()->id())
            ->where('config_date', Carbon::today())
            ->with('brokerApi')
            ->get();

        return view($this->activeTemplate . 'user.portfolio.amo_config', compact('pageTitle', 'brokers', 'configs'));
    }

    /**
     * Store new AMO config
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'symbol_type' => 'required|string|in:CE,PE,BOTH',
            'old_position_profit_percent' => 'required|numeric|min:0|max:100',
            'fresh_position_profit_percent' => 'required|numeric|min:0|max:100',
            'skip_old_positions' => 'sometimes|boolean',
            'skip_fresh_positions' => 'sometimes|boolean',
            'config_date' => 'required|date',
        ]);

        try {
            // Verify broker belongs to user
            $broker = BrokerApi::where('id', $request->broker_api_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            // Check if config already exists
            $existing = BrokerAmoConfig::where('broker_api_id', $request->broker_api_id)
                ->where('symbol_type', $request->symbol_type)
                ->where('config_date', $request->config_date)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration already exists for this broker, symbol type and date. Please edit it instead.'
                ], 422);
            }

            $config = BrokerAmoConfig::create([
                'user_id' => auth()->id(),
                'broker_api_id' => $request->broker_api_id,
                'symbol_type' => $request->symbol_type,
                'old_position_profit_percent' => $request->old_position_profit_percent,
                'fresh_position_profit_percent' => $request->fresh_position_profit_percent,
                'skip_old_positions' => $request->skip_old_positions ?? false,
                'skip_fresh_positions' => $request->skip_fresh_positions ?? false,
                'config_date' => $request->config_date,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'AMO Configuration added successfully!',
                'data' => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('AMO Config Store Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error adding configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing AMO config
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'old_position_profit_percent' => 'required|numeric|min:0|max:100',
            'fresh_position_profit_percent' => 'required|numeric|min:0|max:100',
            'skip_old_positions' => 'sometimes|boolean',
            'skip_fresh_positions' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $config = BrokerAmoConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $config->update($request->only([
                'old_position_profit_percent',
                'fresh_position_profit_percent',
                'skip_old_positions',
                'skip_fresh_positions',
                'is_active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'AMO Configuration updated successfully!',
                'data' => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('AMO Config Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete AMO config
     */
    public function destroy($id)
    {
        try {
            $config = BrokerAmoConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();
            
            $config->delete();

            return response()->json([
                'success' => true,
                'message' => 'AMO Configuration deleted successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('AMO Config Delete Error: ' . $e->getMessage());
            
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
            $config = BrokerAmoConfig::where('id', $id)
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
            Log::error('AMO Config Toggle Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error toggling configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configs for date
     */
    public function getConfigsForDate(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        try {
            $configs = BrokerAmoConfig::where('user_id', auth()->id())
                ->where('config_date', $request->date)
                ->with('brokerApi')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $configs
            ]);

        } catch (\Exception $e) {
            Log::error('Get AMO Configs Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching configurations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Copy configs from one date to another
     */
    public function copyConfigs(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:today',
        ]);

        try {
            $sourceConfigs = BrokerAmoConfig::where('user_id', auth()->id())
                ->where('config_date', $request->from_date)
                ->get();

            if ($sourceConfigs->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No configurations found for the source date.'
                ], 404);
            }

            $copiedCount = 0;
            foreach ($sourceConfigs as $source) {
                // Check if config already exists for target date
                $exists = BrokerAmoConfig::where('broker_api_id', $source->broker_api_id)
                    ->where('symbol_type', $source->symbol_type)
                    ->where('config_date', $request->to_date)
                    ->exists();

                if (!$exists) {
                    BrokerAmoConfig::create([
                        'user_id' => $source->user_id,
                        'broker_api_id' => $source->broker_api_id,
                        'symbol_type' => $source->symbol_type,
                        'old_position_profit_percent' => $source->old_position_profit_percent,
                        'fresh_position_profit_percent' => $source->fresh_position_profit_percent,
                        'config_date' => $request->to_date,
                        'is_active' => $source->is_active,
                    ]);
                    $copiedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully copied {$copiedCount} configuration(s) to " . Carbon::parse($request->to_date)->format('d M Y')
            ]);

        } catch (\Exception $e) {
            Log::error('Copy AMO Configs Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error copying configurations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute AMO orders manually
     */
    public function execute()
    {
        try {
            // Run the AMO command
            $exitCode = \Artisan::call('positions:auto-square-off-amo', [
                '--date' => Carbon::today()->format('Y-m-d')
            ]);

            $output = \Artisan::output();

            // Parse the output for summary
            $summary = $this->parseCommandOutput($output);

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'AMO orders executed successfully!',
                    'summary' => $summary,
                    'output' => $output
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'AMO execution completed with errors. Check logs for details.',
                    'summary' => $summary,
                    'output' => $output
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Manual AMO Execution Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error executing AMO orders: ' . $e->getMessage()
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
        if (preg_match('/Total Positions Processed:\s*(\d+)/', $output, $matches)) {
            $summary['total_positions'] = (int)$matches[1];
        }
        if (preg_match('/CE \(Call Options\):\s*(\d+)/', $output, $matches)) {
            $summary['ce_orders'] = (int)$matches[1];
        }
        if (preg_match('/PE \(Put Options\):\s*(\d+)/', $output, $matches)) {
            $summary['pe_orders'] = (int)$matches[1];
        }
        if (preg_match('/Failed Orders:\s*(\d+)/', $output, $matches)) {
            $summary['failed_orders'] = (int)$matches[1];
        }

        return $summary;
    }
}