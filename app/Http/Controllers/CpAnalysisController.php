<?php
// FILE: app/Http/Controllers/CpAnalysisController.php
namespace App\Http\Controllers;

use App\Models\CpAnalysis;
use App\Models\CpPaymentGateway;
use App\Models\CpSubscriptionPayment;
use App\Models\CpSubscriptionPlan;
use App\Models\CpUserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class CpAnalysisController extends Controller
{
    // ── 1. LISTING ────────────────────────────────────────────────────────────
    public function index()
    {
        $analyses         = CpAnalysis::active()->orderBy('sort_order')->orderBy('name')->get();
        $plans            = CpSubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $userSubscription = $this->getActiveSubscription();
        $userTier         = $this->getUserTier($userSubscription);

        $pageTitle = "Options Trading Analysis Tools";
        return view(activeTemplate() . 'cp.analyses.index',
            compact('analyses', 'plans', 'userSubscription', 'userTier', 'pageTitle'));
    }

    // ── 2. DETAIL ─────────────────────────────────────────────────────────────
    public function detail($slug)
    {
        $analysis         = CpAnalysis::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $userSubscription = $this->getActiveSubscription();
        $userTier         = $this->getUserTier($userSubscription);
        $hasAccess        = $this->hasAccess($userTier, $analysis->plan_tier);
        $plans            = CpSubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $related          = CpAnalysis::active()
            ->where('id', '!=', $analysis->id)
            ->where('data_source', $analysis->data_source)
            ->limit(3)->get();

        return view(activeTemplate() . 'cp.analyses.detail',
            compact('analysis', 'userSubscription', 'userTier', 'hasAccess', 'plans', 'related'));
    }

    // ── 3. PRICING ────────────────────────────────────────────────────────────
    public function pricing()
    {
        $plans            = CpSubscriptionPlan::with('analyses')->where('is_active', true)->orderBy('sort_order')->get();
        $userSubscription = $this->getActiveSubscription();
        $userTier         = $this->getUserTier($userSubscription);
        $gateway          = CpPaymentGateway::active();

        $pageTitle = "Pricing";
        return view(activeTemplate() . 'cp.pricing',
            compact('plans', 'userSubscription', 'userTier', 'gateway', 'pageTitle'));
    }

    // ── 4. INITIATE PAYMENT ───────────────────────────────────────────────────
    public function initiatePayment(Request $request, CpSubscriptionPlan $plan)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Please login to subscribe.'], 401);
        }

        if ($plan->price_monthly == 0) {
            $this->activateFree(Auth::user(), $plan);
            return response()->json([
                'success'  => true,
                'free'     => true,
                'redirect' => route('cp.my-subscription'),
                'message'  => 'Free plan activated!',
            ]);
        }

        $gateway = CpPaymentGateway::active();
        if (!$gateway) {
            return response()->json(['success' => false, 'message' => 'Payment gateway not configured.'], 500);
        }

        try {
            $api         = new Api($gateway->getCredential('key_id'), $gateway->getCredential('key_secret'));
            $orderNumber = CpSubscriptionPayment::generateOrderNumber();

            $rzpOrder = $api->order->create([
                'amount'          => (int)($plan->price_monthly * 100),
                'currency'        => 'INR',
                'receipt'         => $orderNumber,
                'payment_capture' => 1,
            ]);

            CpSubscriptionPayment::create([
                'order_number'            => $orderNumber,
                'user_id'                 => Auth::id(),
                'cp_subscription_plan_id' => $plan->id,
                'gateway'                 => 'razorpay',
                'gateway_order_id'        => $rzpOrder->id,
                'amount'                  => $plan->price_monthly,
                'currency'                => 'INR',
                'status'                  => 'pending',
            ]);

            $user = Auth::user();
            return response()->json([
                'success'      => true,
                'key'          => $gateway->getCredential('key_id'),
                'amount'       => (int)($plan->price_monthly * 100),
                'order_id'     => $rzpOrder->id,
                'order_number' => $orderNumber,
                'plan_name'    => $plan->name,
                'user_name'    => trim($user->firstname . ' ' . $user->lastname),
                'user_email'   => $user->email,
                'user_mobile'  => $user->mobile ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error('CP Initiate Payment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── 5. VERIFY PAYMENT ─────────────────────────────────────────────────────
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        $gateway = CpPaymentGateway::active();

        try {
            $api = new Api($gateway->getCredential('key_id'), $gateway->getCredential('key_secret'));
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);

            $payment = CpSubscriptionPayment::where('gateway_order_id', $request->razorpay_order_id)
                ->where('user_id', Auth::id())
                ->where('status', 'pending')
                ->firstOrFail();

            // Cancel old active subscriptions
            CpUserSubscription::where('user_id', Auth::id())
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            // Create new subscription — 30 days
            $subscription = CpUserSubscription::create([
                'user_id'                 => Auth::id(),
                'cp_subscription_plan_id' => $payment->cp_subscription_plan_id,
                'status'                  => 'active',
                'starts_at'               => now(),
                'expires_at'              => now()->addDays(30),
            ]);

            $payment->update([
                'cp_user_subscription_id' => $subscription->id,
                'gateway_payment_id'      => $request->razorpay_payment_id,
                'gateway_signature'       => $request->razorpay_signature,
                'gateway_response'        => $request->all(),
                'status'                  => 'paid',
                'paid_at'                 => now(),
            ]);

            return response()->json([
                'success'  => true,
                'redirect' => route('cp.my-subscription'),
                'message'  => 'Subscription activated!',
            ]);
        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            CpSubscriptionPayment::where('gateway_order_id', $request->razorpay_order_id)
                ->update(['status' => 'failed', 'failure_reason' => 'Signature mismatch']);
            return response()->json(['success' => false, 'message' => 'Payment verification failed.'], 400);
        } catch (\Exception $e) {
            Log::error('CP Verify Payment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── 6. MY SUBSCRIPTION ────────────────────────────────────────────────────
    public function mySubscription()
    {
        $userSubscription   = $this->getActiveSubscription();
        $userTier           = $this->getUserTier($userSubscription);
        $payments           = CpSubscriptionPayment::where('user_id', Auth::id())
            ->with('plan')->latest()->paginate(10);
        $plans              = CpSubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $accessibleAnalyses = CpAnalysis::active()->forTier($userTier)->orderBy('sort_order')->get();

        $pageTitle = "My Subscription";

        return view(activeTemplate() . 'cp.subscription.my-subscription',
            compact('userSubscription', 'userTier', 'payments', 'plans', 'accessibleAnalyses', 'pageTitle'));
    }

    // ── PRIVATE HELPERS ───────────────────────────────────────────────────────
    private function getActiveSubscription(): ?CpUserSubscription
    {
        if (!Auth::check()) return null;
        return CpUserSubscription::where('user_id', Auth::id())->active()->with('plan')->latest()->first();
    }

    private function getUserTier(?CpUserSubscription $sub): string
    {
        if (!$sub) return 'free';
        return $sub->plan->slug ?? 'free';
    }

    private function hasAccess(string $userTier, string $required): bool
    {
        $rank = ['free' => 0, 'pro' => 1, 'pro_plus' => 2];
        return ($rank[$userTier] ?? 0) >= ($rank[$required] ?? 0);
    }

    private function activateFree($user, CpSubscriptionPlan $plan): void
    {
        CpUserSubscription::where('user_id', $user->id)->where('status', 'active')
            ->update(['status' => 'cancelled']);
        CpUserSubscription::create([
            'user_id'                 => $user->id,
            'cp_subscription_plan_id' => $plan->id,
            'status'                  => 'active',
            'starts_at'               => now(),
            'expires_at'              => now()->addDays(30),
        ]);
    }
}