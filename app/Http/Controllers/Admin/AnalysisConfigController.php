<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisConfig;
use App\Models\BrokerApi;
use App\Models\SymbolList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalysisConfigController extends Controller
{
    public function index()
    {
        $pageTitle = 'Analysis Config';

        $configs = AnalysisConfig::with(['broker', 'symbols'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $brokers = BrokerApi::where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get(['id', 'account_user_name']);

        $symbols    = SymbolList::orderBy('underlying')->get(['id', 'underlying', 'symbol']);
        $timeFrames = ['15min', '30min', '1hr'];

        // Which timeframes are already taken (globally)
        $usedTimeFrames = AnalysisConfig::pluck('time_frame')->toArray();

        return view('admin.analysis-config.index', compact(
            'pageTitle', 'configs', 'brokers', 'symbols', 'timeFrames', 'usedTimeFrames'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'time_frame'    => 'required|in:15min,30min,1hr',
            'symbol_ids'    => 'required|array|min:1|max:40',
            'symbol_ids.*'  => 'exists:symbol_lists,id',
        ], [
            'symbol_ids.required' => 'Please select at least 1 symbol.',
            'symbol_ids.min'      => 'Please select at least 1 symbol.',
            'symbol_ids.max'      => 'You can select maximum 40 symbols.',
        ]);

        try {
            // Timeframe can only be used ONCE globally — across all brokers
            $timeframeExists = AnalysisConfig::where('time_frame', $request->time_frame)->exists();

            if ($timeframeExists) {
                $existing   = AnalysisConfig::where('time_frame', $request->time_frame)->with('broker')->first();
                $brokerName = $existing->broker->account_user_name ?? 'another broker';
                $notify[]   = ['error', strtoupper($request->time_frame) . ' timeframe is already assigned to broker "' . $brokerName . '". Delete that config first.'];
                return back()->withNotify($notify);
            }

            DB::transaction(function () use ($request) {
                $config = AnalysisConfig::create([
                    'broker_api_id' => $request->broker_api_id,
                    'time_frame'    => $request->time_frame,
                    'is_active'     => true,
                ]);
                $config->symbols()->sync($request->symbol_ids);
            });

            $notify[] = ['success', 'Analysis config created successfully!'];
            return redirect()->route('admin.analysis-config.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Analysis Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating config: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id)
    {
        $config = AnalysisConfig::findOrFail($id);

        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'time_frame'    => 'required|in:15min,30min,1hr',
            'symbol_ids'    => 'required|array|min:1|max:40',
            'symbol_ids.*'  => 'exists:symbol_lists,id',
        ], [
            'symbol_ids.required' => 'Please select at least 1 symbol.',
            'symbol_ids.min'      => 'Please select at least 1 symbol.',
            'symbol_ids.max'      => 'You can select maximum 40 symbols.',
        ]);

        try {
            // Timeframe globally unique — exclude current record
            $timeframeExists = AnalysisConfig::where('time_frame', $request->time_frame)
                ->where('id', '!=', $id)
                ->exists();

            if ($timeframeExists) {
                $existing   = AnalysisConfig::where('time_frame', $request->time_frame)
                    ->where('id', '!=', $id)
                    ->with('broker')
                    ->first();
                $brokerName = $existing->broker->account_user_name ?? 'another broker';
                $notify[]   = ['error', strtoupper($request->time_frame) . ' timeframe is already assigned to broker "' . $brokerName . '". Each timeframe can only be used once globally.'];
                return back()->withNotify($notify);
            }

            DB::transaction(function () use ($request, $config) {
                $config->update([
                    'broker_api_id' => $request->broker_api_id,
                    'time_frame'    => $request->time_frame,
                ]);
                $config->symbols()->sync($request->symbol_ids);
            });

            $notify[] = ['success', 'Analysis config updated successfully!'];
            return redirect()->route('admin.analysis-config.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Analysis Config Update Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error updating config: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function destroy($id)
    {
        try {
            $config = AnalysisConfig::findOrFail($id);
            $config->symbols()->detach();
            $config->delete();

            $notify[] = ['success', 'Config deleted permanently!'];
            return redirect()->route('admin.analysis-config.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Analysis Config Delete Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deleting config: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $config = AnalysisConfig::findOrFail($id);
            $config->update(['is_active' => !$config->is_active]);

            $status   = $config->is_active ? 'activated' : 'deactivated';
            $notify[] = ['success', "Config {$status} successfully!"];
            return redirect()->route('admin.analysis-config.index')->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating status: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}