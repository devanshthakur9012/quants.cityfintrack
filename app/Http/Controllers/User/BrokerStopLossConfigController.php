<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerStopLossConfig;
use App\Models\BrokerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrokerStopLossConfigController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // INDEX PAGE
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Stop Loss Order Configuration';

        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        $configs = BrokerStopLossConfig::where('user_id', auth()->id())
            ->with('brokerApi')
            ->get();

        return view(
            $this->activeTemplate . 'user.portfolio.broker_stop_loss_config',
            compact('pageTitle', 'brokers', 'configs')
        );
    }

    // ─────────────────────────────────────────────────────────────
    // STORE
    // ─────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'        => 'required|exists:broker_apis,id',
            'symbol_type'          => 'required|string|in:CE,PE,BOTH',
            'price_type'           => 'required|string|in:AVG,LTP',
            'stop_loss_percent'    => 'required|numeric|min:-100|max:100',
            'quantity_percent'     => 'required|integer|min:1|max:100',
            'position_filter'      => 'required|string|in:PROFIT,LOSS,BOTH',
            'skip_old_positions'   => 'sometimes|boolean',
            'skip_fresh_positions' => 'sometimes|boolean',
        ]);

        try {
            // Ownership check
            BrokerApi::where('id', $request->broker_api_id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $existing = BrokerStopLossConfig::where('broker_api_id', $request->broker_api_id)
                ->where('symbol_type', $request->symbol_type)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'A Stop Loss config already exists for this broker + symbol type. Please edit it instead.',
                ], 422);
            }

            $config = BrokerStopLossConfig::create([
                'user_id'              => auth()->id(),
                'broker_api_id'        => $request->broker_api_id,
                'symbol_type'          => $request->symbol_type,
                'price_type'           => $request->price_type,
                'stop_loss_percent'    => $request->stop_loss_percent,
                'quantity_percent'     => $request->quantity_percent,
                'position_filter'      => $request->position_filter,
                'skip_old_positions'   => $request->boolean('skip_old_positions'),
                'skip_fresh_positions' => $request->boolean('skip_fresh_positions'),
                'is_active'            => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stop Loss configuration added successfully!',
                'data'    => $config->load('brokerApi'),
            ]);

        } catch (\Exception $e) {
            Log::error('SL Config Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $request->validate([
            'price_type'           => 'sometimes|string|in:AVG,LTP',
            'stop_loss_percent'    => 'required|numeric|min:-100|max:100',
            'quantity_percent'     => 'sometimes|integer|min:1|max:100',
            'position_filter'      => 'sometimes|string|in:PROFIT,LOSS,BOTH',
            'skip_old_positions'   => 'sometimes|boolean',
            'skip_fresh_positions' => 'sometimes|boolean',
            'is_active'            => 'sometimes|boolean',
        ]);

        try {
            $config = BrokerStopLossConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $config->update($request->only([
                'price_type',
                'stop_loss_percent',
                'quantity_percent',
                'position_filter',
                'skip_old_positions',
                'skip_fresh_positions',
                'is_active',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Stop Loss configuration updated!',
                'data'    => $config->load('brokerApi'),
            ]);

        } catch (\Exception $e) {
            Log::error('SL Config Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────

    public function destroy($id)
    {
        try {
            BrokerStopLossConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail()
                ->delete();

            return response()->json(['success' => true, 'message' => 'Configuration deleted!']);

        } catch (\Exception $e) {
            Log::error('SL Config Delete Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // TOGGLE ACTIVE
    // ─────────────────────────────────────────────────────────────

    public function toggleActive($id)
    {
        try {
            $config = BrokerStopLossConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $config->is_active = !$config->is_active;
            $config->save();

            $status = $config->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'message' => "Configuration {$status}!",
                'data'    => $config->load('brokerApi'),
            ]);

        } catch (\Exception $e) {
            Log::error('SL Config Toggle Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GET ALL (JSON)
    // ─────────────────────────────────────────────────────────────

    public function getConfigs()
    {
        try {
            $configs = BrokerStopLossConfig::where('user_id', auth()->id())
                ->with('brokerApi')
                ->get();

            return response()->json(['success' => true, 'data' => $configs]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EXECUTE ALL ACTIVE CONFIGS
    // ─────────────────────────────────────────────────────────────

    public function execute()
    {
        try {
            $exitCode = \Artisan::call('positions:place-stop-loss-orders');
            $output   = \Artisan::output();

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0
                    ? 'Stop Loss orders executed successfully!'
                    : 'Execution completed with errors. Check logs.',
                'output'  => $output,
            ], $exitCode === 0 ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('SL Manual Execute Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EXECUTE SINGLE CONFIG
    // ─────────────────────────────────────────────────────────────

    public function executeOne($id)
    {
        try {
            $config = BrokerStopLossConfig::where('id', $id)
                ->where('user_id', auth()->id())
                ->with('brokerApi')
                ->firstOrFail();

            $exitCode = \Artisan::call('positions:place-stop-loss-orders', [
                '--broker_id'   => $config->broker_api_id,
                '--symbol_type' => $config->symbol_type,
                '--config_id'   => $config->id,
            ]);

            $output = \Artisan::output();

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0
                    ? "Stop Loss orders executed for {$config->symbol_type} ({$config->brokerApi->client_name})!"
                    : 'Execution completed with errors.',
                'output'  => $output,
            ], $exitCode === 0 ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('SL Execute One Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}