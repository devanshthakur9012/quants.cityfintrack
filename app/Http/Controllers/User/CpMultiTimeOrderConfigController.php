<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\CpMultiTimeOrderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CpMultiTimeOrderConfigController extends Controller
{
    public function index()
    {
        $pageTitle = 'Multi-Snapshot Order Configs';

        $configs = CpMultiTimeOrderConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name,client_type')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        $brokers = BrokerApi::whereIn('client_type', ['Zerodha', 'AngelOne'])
            ->orderBy('client_name')
            ->get(['id', 'client_name', 'client_type', 'is_token_valid']);

        return view(activeTemplate() . 'user.cp.multi-time-configs.index', compact('pageTitle', 'configs', 'brokers'));
    }

    public function getData(CpMultiTimeOrderConfig $config)
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
            CpMultiTimeOrderConfig::create($data);
            $notify[] = ['success', 'Multi-Snapshot config created!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpMultiTimeOrderConfig Store: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, CpMultiTimeOrderConfig $config)
    {
        if ($config->user_id !== Auth::id()) {
            $notify[] = ['error', 'Unauthorized.'];
            return back()->withNotify($notify);
        }

        $data = $this->validated($request);
        $data['status'] = $request->boolean('status', true);

        try {
            $config->update($data);
            $notify[] = ['success', 'Multi-Snapshot config updated!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpMultiTimeOrderConfig Update: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function toggleStatus(CpMultiTimeOrderConfig $config)
    {
        if ($config->user_id !== Auth::id()) {
            $notify[] = ['error', 'Unauthorized.'];
            return back()->withNotify($notify);
        }

        $config->update(['status' => !$config->status]);
        $notify[] = ['success', 'Status updated.'];
        return back()->withNotify($notify);
    }

    public function destroy(CpMultiTimeOrderConfig $config)
    {
        if ($config->user_id !== Auth::id()) {
            $notify[] = ['error', 'Unauthorized.'];
            return back()->withNotify($notify);
        }

        $config->delete();
        $notify[] = ['success', 'Multi-Snapshot config deleted.'];
        return back()->withNotify($notify);
    }

    // ── Private ──────────────────────────────────────────────────────────
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'broker_api_id'  => 'required|exists:broker_apis,id',
            'order_type'     => 'required|in:LIMIT,MARKET',
            'product'        => 'required|in:MIS,NRML',
            'disc_ltp'       => 'nullable|numeric|min:0|max:100',
            'signal_mode'    => 'required|in:align,opposite',
            'quantity'       => 'required|integer|min:1|max:5',
            'max_price_pct_of_underlying' => 'nullable|numeric|min:0|max:100',
            'reentry_min_drop_pct'        => 'nullable|numeric|min:0|max:100',
            'snapshot_times'   => 'required|array|min:1',
            'snapshot_times.*' => 'in:10:15,11:15,12:15',
        ]);

        $broker = BrokerApi::findOrFail($data['broker_api_id']);
        if (!in_array($broker->client_type, ['Zerodha', 'AngelOne'])) {
            abort(422, 'Selected broker is not a supported type.');
        }
        $data['broker_type'] = $broker->client_type;

        return $data;
    }
}