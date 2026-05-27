<?php
// FILE: app/Http/Controllers/Admin/CpSubscriptionPlanController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CpAnalysis;
use App\Models\CpPaymentGateway;
use App\Models\CpSubscriptionPlan;
use App\Models\CpSubscriptionPayment;
use App\Models\CpUserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CpSubscriptionPlanController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // PLANS
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'Subscription Plans';
        $plans     = CpSubscriptionPlan::with('analyses')
            ->withCount(['subscriptions as active_count' => fn($q) => $q->active()])
            ->orderBy('sort_order')->get();
        $analyses  = CpAnalysis::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.cp.plans.index', compact('pageTitle', 'plans', 'analyses'));
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'required|string|max:50|unique:cp_subscription_plans,slug',
            'description'   => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'badge_color'   => 'nullable|string|max:20',
            'sort_order'    => 'nullable|integer|min:0',
            'is_active'     => 'nullable',
        ]);
        $data['features']   = $this->parseLines($request->input('features_text', ''));
        $data['is_active']  = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($request->sort_order ?? 0);

        try {
            $plan = CpSubscriptionPlan::create($data);
            $plan->analyses()->sync($request->input('analysis_ids', []));
            $notify[] = ['success', "Plan '{$plan->name}' created!"];
            return redirect()->route('admin.cp.plans.index')->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpPlan Store: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function updatePlan(Request $request, CpSubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'badge_color'   => 'nullable|string|max:20',
            'sort_order'    => 'nullable|integer|min:0',
            'is_active'     => 'nullable',
        ]);
        $data['features']   = $this->parseLines($request->input('features_text', ''));
        $data['is_active']  = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($request->sort_order ?? 0);

        try {
            $plan->update($data);
            $plan->analyses()->sync($request->input('analysis_ids', []));
            $notify[] = ['success', 'Plan updated!'];
            return redirect()->route('admin.cp.plans.index')->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpPlan Update: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function destroyPlan(CpSubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->active()->exists()) {
            $notify[] = ['error', 'Cannot delete a plan with active subscribers.'];
            return back()->withNotify($notify);
        }
        $plan->analyses()->detach();
        $plan->delete();
        $notify[] = ['success', 'Plan deleted.'];
        return redirect()->route('admin.cp.plans.index')->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // USER SUBSCRIPTIONS
    // ══════════════════════════════════════════════════════════════════════════

    public function subscriptions(Request $request)
    {
        $pageTitle = 'User Subscriptions';
        $query     = CpUserSubscription::with(['user', 'plan'])->latest();

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('plan'))   $query->where('cp_subscription_plan_id', $request->plan);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) =>
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
            );
        }

        $subscriptions = $query->paginate(20)->withQueryString();
        $plans         = CpSubscriptionPlan::orderBy('sort_order')->get();

        return view('admin.cp.subscriptions.index',
            compact('pageTitle', 'subscriptions', 'plans'));
    }

    public function cancelSubscription(CpUserSubscription $subscription)
    {
        $subscription->update(['status' => 'cancelled']);
        $notify[] = ['success', 'Subscription cancelled.'];
        return back()->withNotify($notify);
    }

    public function extendSubscription(CpUserSubscription $subscription)
    {
        $expiry = $subscription->expires_at
            ? $subscription->expires_at->addDays(30)
            : now()->addDays(30);

        $subscription->update(['expires_at' => $expiry, 'status' => 'active']);
        $notify[] = ['success', 'Subscription extended by 30 days.'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PAYMENTS
    // ══════════════════════════════════════════════════════════════════════════

    public function payments(Request $request)
    {
        $pageTitle = 'Subscription Payments';
        $query     = CpSubscriptionPayment::with(['user', 'plan'])->latest();

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhereHas('user', fn($uq) =>
                      $uq->where('email', 'like', "%{$s}%"))
            );
        }

        $payments = $query->paginate(20)->withQueryString();
        $stats    = [
            'total_paid'    => CpSubscriptionPayment::where('status', 'paid')->sum('amount'),
            'today_paid'    => CpSubscriptionPayment::where('status', 'paid')
                                  ->whereDate('paid_at', today())->sum('amount'),
            'pending_count' => CpSubscriptionPayment::where('status', 'pending')->count(),
            'failed_count'  => CpSubscriptionPayment::where('status', 'failed')->count(),
        ];

        return view('admin.cp.payments.index', compact('pageTitle', 'payments', 'stats'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PAYMENT GATEWAY
    // ══════════════════════════════════════════════════════════════════════════

    public function gateway()
    {
        $pageTitle = 'CP Payment Gateway';
        $gateway   = CpPaymentGateway::first() ?? new CpPaymentGateway();
        return view('admin.cp.gateway', compact('pageTitle', 'gateway'));
    }

    public function gatewayUpdate(Request $request)
    {
        $request->validate([
            'key_id'     => 'required|string',
            'key_secret' => 'required|string',
        ]);
        CpPaymentGateway::updateOrCreate(['id' => 1], [
            'name'        => 'Razorpay',
            'alias'       => 'razorpay',
            'credentials' => [
                'key_id'     => $request->key_id,
                'key_secret' => $request->key_secret,
            ],
            'status' => $request->boolean('status', false),
        ]);
        $notify[] = ['success', 'Gateway settings saved.'];
        return back()->withNotify($notify);
    }

    // ── Private ────────────────────────────────────────────────────────────────
    private function parseLines(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }
}