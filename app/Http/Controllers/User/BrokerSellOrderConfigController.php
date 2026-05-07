<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerSellOrderConfig;
use App\Models\BrokerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrokerSellOrderConfigController extends Controller
{
    /**
     * Display SELL config settings page
     */
    public function index()
    {
        $pageTitle = 'Normal SELL Order Configuration';

        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        $configs = BrokerSellOrderConfig::where('user_id', auth()->id())
            ->with('brokerApi')
            ->get();

        return view($this->activeTemplate . 'user.portfolio.broker_sell_order_config', compact('pageTitle', 'brokers', 'configs'));
    }

    /**
     * Store new SELL config
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'                 => 'required|exists:broker_apis,id',
            'symbol_type'                   => 'required|string|in:CE,PE,BOTH',
            'price_type'                    => 'required|string|in:AVG,LTP',
            'quantity_percent'              => 'required|integer|min:1|max:100',
            'position_filter'               => 'required|string|in:PROFIT,LOSS,BOTH',
            'old_position_profit_percent'   => 'required|numeric|min:-10|max:100',
            'fresh_position_profit_percent' => 'required|numeric|min:-10|max:100',
            'skip_old_positions'            => 'sometimes|boolean',
            'skip_fresh_positions'          => 'sometimes|boolean',
        ]);

        try {
            $broker = BrokerApi::where('id', $request->broker_api_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $existing = BrokerSellOrderConfig::where('broker_api_id', $request->broker_api_id)
                ->where('symbol_type', $request->symbol_type)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration already exists for this broker and symbol type. Please edit it instead.'
                ], 422);
            }

            $config = BrokerSellOrderConfig::create([
                'user_id'                       => auth()->id(),
                'broker_api_id'                 => $request->broker_api_id,
                'symbol_type'                   => $request->symbol_type,
                'price_type'                    => $request->price_type,
                'quantity_percent'              => $request->quantity_percent ?? 100,
                'position_filter'               => $request->position_filter ?? 'PROFIT',
                'old_position_profit_percent'   => $request->old_position_profit_percent,
                'fresh_position_profit_percent' => $request->fresh_position_profit_percent,
                'skip_old_positions'            => $request->skip_old_positions ?? false,
                'skip_fresh_positions'          => $request->skip_fresh_positions ?? false,
                'is_active'                     => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SELL Order Configuration added successfully!',
                'data'    => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('SELL Config Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error adding configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing SELL config
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'price_type'                    => 'sometimes|string|in:AVG,LTP',
            'quantity_percent'              => 'sometimes|integer|min:1|max:100',
            'position_filter'               => 'sometimes|string|in:PROFIT,LOSS,BOTH',
            'old_position_profit_percent'   => 'required|numeric|min:-10|max:100',
            'fresh_position_profit_percent' => 'required|numeric|min:-10|max:100',
            'skip_old_positions'            => 'sometimes|boolean',
            'skip_fresh_positions'          => 'sometimes|boolean',
            'is_active'                     => 'sometimes|boolean',
        ]);

        try {
            $config = BrokerSellOrderConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $config->update($request->only([
                'price_type',
                'quantity_percent',
                'position_filter',
                'old_position_profit_percent',
                'fresh_position_profit_percent',
                'skip_old_positions',
                'skip_fresh_positions',
                'is_active'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'SELL Configuration updated successfully!',
                'data'    => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('SELL Config Update Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete SELL config
     */
    public function destroy($id)
    {
        try {
            $config = BrokerSellOrderConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $config->delete();

            return response()->json([
                'success' => true,
                'message' => 'SELL Configuration deleted successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('SELL Config Delete Error: ' . $e->getMessage());

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
            $config = BrokerSellOrderConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $config->is_active = !$config->is_active;
            $config->save();

            $status = $config->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'message' => "Configuration {$status} successfully!",
                'data'    => $config->load('brokerApi')
            ]);

        } catch (\Exception $e) {
            Log::error('SELL Config Toggle Error: ' . $e->getMessage());

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
            $configs = BrokerSellOrderConfig::where('user_id', auth()->id())
                ->with('brokerApi')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $configs
            ]);

        } catch (\Exception $e) {
            Log::error('Get SELL Configs Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching configurations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute SELL orders manually
     */
    public function execute()
    {
        try {
            $exitCode = \Artisan::call('positions:place-sell-orders');
            $output   = \Artisan::output();

            $summary = $this->parseCommandOutput($output);

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'SELL orders executed successfully!',
                    'summary' => $summary,
                    'output'  => $output
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'SELL execution completed with errors. Check logs for details.',
                    'summary' => $summary,
                    'output'  => $output
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Manual SELL Execution Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error executing SELL orders: ' . $e->getMessage()
            ], 500);
        }
    }

    private function parseCommandOutput($output)
    {
        $summary = [
            'total_positions' => 0,
            'ce_orders'       => 0,
            'pe_orders'       => 0,
            'failed_orders'   => 0
        ];

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