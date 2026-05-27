<?php
// FILE: app/Http/Controllers/Admin/AnalysisConfigController.php  — REPLACE EXISTING
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

        $symbols = SymbolList::orderBy('underlying')->get(['id', 'underlying', 'symbol']);

        // timeframe is always 15min — not passed to view as a dropdown option
        return view('admin.analysis-config.index',
            compact('pageTitle', 'configs', 'brokers', 'symbols'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'symbol_ids'    => 'required|array|min:1|max:50',
            'symbol_ids.*'  => 'exists:symbol_lists,id',
        ], [
            'symbol_ids.required' => 'Please select at least 1 symbol.',
            'symbol_ids.max'      => 'Maximum 50 symbols allowed.',
        ]);

        try {
            // Only ONE config for 15min — globally
            if (AnalysisConfig::where('time_frame', '15min')->exists()) {
                $notify[] = ['error', '15min config already exists. Edit the existing one.'];
                return back()->withNotify($notify);
            }

            DB::transaction(function () use ($request) {
                $config = AnalysisConfig::create([
                    'broker_api_id' => $request->broker_api_id,
                    // time_frame forced to 15min by model boot — no need to pass it
                    'is_active'     => true,
                ]);
                $config->symbols()->sync($request->symbol_ids);
            });

            $notify[] = ['success', 'Config created!'];
            return redirect()->route('admin.analysis-config.index')->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('AnalysisConfig Store: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id)
    {
        $config = AnalysisConfig::findOrFail($id);

        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'symbol_ids'    => 'required|array|min:1|max:50',
            'symbol_ids.*'  => 'exists:symbol_lists,id',
        ]);

        try {
            DB::transaction(function () use ($request, $config) {
                $config->update(['broker_api_id' => $request->broker_api_id]);
                $config->symbols()->sync($request->symbol_ids);
            });
            $notify[] = ['success', 'Config updated!'];
            return redirect()->route('admin.analysis-config.index')->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('AnalysisConfig Update: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function destroy($id)
    {
        try {
            $config = AnalysisConfig::findOrFail($id);
            $config->symbols()->detach();
            $config->delete();
            $notify[] = ['success', 'Config deleted.'];
            return redirect()->route('admin.analysis-config.index')->withNotify($notify);
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function toggleStatus($id)
    {
        $config = AnalysisConfig::findOrFail($id);
        $config->update(['is_active' => !$config->is_active]);
        $status   = $config->is_active ? 'activated' : 'deactivated';
        $notify[] = ['success', "Config {$status}."];
        return redirect()->route('admin.analysis-config.index')->withNotify($notify);
    }
}