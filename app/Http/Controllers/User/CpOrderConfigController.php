<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\CpAnalysis;
use App\Models\CpOrderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CpOrderConfigController extends Controller
{
    public function index()
    {
        $pageTitle = 'Order Placement Configs';

        $configs = CpOrderConfig::where('user_id', Auth::id())
            ->with(['analysis:id,name,route_name', 'broker:id,client_name,client_type'])
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        $analyses = CpAnalysis::where('is_active', 1)->orderBy('name')->get(['id', 'name', 'route_name']);

        // Global broker pool — NOT filtered by user_id
        $brokers = BrokerApi::whereIn('client_type', ['Zerodha', 'AngelOne'])
            ->orderBy('client_name')
            ->get(['id', 'client_name', 'client_type', 'is_token_valid']);

        return view(activeTemplate() . 'user.cp.order-configs.index', compact('pageTitle', 'configs', 'analyses', 'brokers'));
    }

    // AJAX — load full data for edit modal
    public function getData(CpOrderConfig $config)
    {
        if ($config->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'config' => $config]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = Auth::id();
        $data['status']  = $request->boolean('status', true);

        try {
            CpOrderConfig::create($data);
            $notify[] = ['success', 'Order config created!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpOrderConfig Store: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, CpOrderConfig $config)
    {
        if ($config->user_id !== Auth::id()) {
            $notify[] = ['error', 'Unauthorized.'];
            return back()->withNotify($notify);
        }

        $data = $this->validated($request);
        $data['status'] = $request->boolean('status', true);

        try {
            $config->update($data);
            $notify[] = ['success', 'Order config updated!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpOrderConfig Update: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function toggleStatus(CpOrderConfig $config)
    {
        if ($config->user_id !== Auth::id()) {
            $notify[] = ['error', 'Unauthorized.'];
            return back()->withNotify($notify);
        }

        $config->update(['status' => !$config->status]);
        $notify[] = ['success', 'Status updated.'];
        return back()->withNotify($notify);
    }

    public function destroy(CpOrderConfig $config)
    {
        if ($config->user_id !== Auth::id()) {
            $notify[] = ['error', 'Unauthorized.'];
            return back()->withNotify($notify);
        }

        $config->delete();
        $notify[] = ['success', 'Order config deleted.'];
        return back()->withNotify($notify);
    }

    // ── Private ──────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'cp_analysis_id' => 'required|exists:cp_analyses,id',
            'broker_type'    => 'required|in:Zerodha,AngelOne',
            'broker_api_id'  => 'required|exists:broker_apis,id',
            'order_type'     => 'required|in:LIMIT,MARKET',
            'product'        => 'required|in:MIS,NRML',
            'disc_ltp'       => 'nullable|numeric|min:0|max:100',
            'signal_mode'    => 'required|in:align,opposite',
            'quantity'       => 'required|integer|min:1|max:5',
        ]);
    }
}