<?php
// FILE: app/Http/Controllers/WebinarController.php

namespace App\Http\Controllers;

use App\Models\CoursePaymentGateway;
use App\Models\Webinar;
use App\Models\WebinarEnrollment;
use App\Models\WebinarOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api as RazorpayApi;

class WebinarController extends Controller
{
    public $activeTemplate;

    public function __construct()
    {
        $this->activeTemplate = activeTemplate();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LISTING
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $pageTitle = 'Webinars';

        $heroBanner = [
            'title'        => 'Webinar',
            'description'  => 'Our webinar series is designed to bring you cutting-edge insights, expert perspectives, and actionable tips on a wide range of Futures & Options related topics. Whether you\'re a seasoned professional looking to stay ahead of industry trends or a curious learner eager to explore new subjects, our webinars offer something for everyone.',
            'illustration' => 'https://img.freepik.com/free-vector/webinar-concept-illustration_114360-4798.jpg?w=400',
        ];

        $languages   = ['Hindi', 'English', 'Gujarati'];
        $proficiency = ['Beginner', 'Intermediate', 'Advanced'];

        // Filters
        $filterLang   = $request->input('language') ?: null;
        $filterType   = $request->input('type')     ?: null;
        $filterLevel  = $request->input('level')    ?: null;
        $filterSearch = trim($request->input('search', '')) ?: null;

        // ── Build enrolled webinar ID set for current user (ONE query) ────────
        // We pass this set to the blade so it can check enrollment
        // without calling any model method inside the loop.
        $enrolledWebinarIds = collect();
        $authUser           = Auth::guard('web')->user();

        if ($authUser) {
            $enrolledWebinarIds = WebinarEnrollment::where('user_id', $authUser->id)
                ->where('status', 1)
                ->pluck('webinar_id');
        }

        // ── UPCOMING & LIVE ───────────────────────────────────────────────────
        $upcomingWebinars = Webinar::whereIn('status', ['upcoming', 'live'])
            ->when($filterLang,   fn($q) => $q->where('language', $filterLang))
            ->when($filterType,   fn($q) => $q->where('type', $filterType))
            ->when($filterLevel,  fn($q) => $q->where('level', 'like', $filterLevel . '%'))
            ->when($filterSearch, fn($q) => $q->where('title', 'like', '%' . $filterSearch . '%'))
            ->orderByRaw("FIELD(status, 'live', 'upcoming')")
            ->orderBy('sort_order')
            ->orderBy('webinar_date')
            ->get();

        // ── PAST ──────────────────────────────────────────────────────────────
        $pastWebinars = Webinar::where('status', 'past')
            ->when($filterLang,   fn($q) => $q->where('language', $filterLang))
            ->when($filterType,   fn($q) => $q->where('type', $filterType))
            ->when($filterLevel,  fn($q) => $q->where('level', 'like', $filterLevel . '%'))
            ->when($filterSearch, fn($q) => $q->where('title', 'like', '%' . $filterSearch . '%'))
            ->orderBy('sort_order')
            ->orderByDesc('webinar_date')
            ->get();

        return view($this->activeTemplate . 'webinars', compact(
            'pageTitle',
            'heroBanner',
            'languages',
            'proficiency',
            'upcomingWebinars',
            'pastWebinars',
            'enrolledWebinarIds',  // ← pass to blade for zero-query enrollment checks
            'authUser'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DETAIL
    // ─────────────────────────────────────────────────────────────────────────
    public function detail(string $slug)
    {
        $webinar = Webinar::with([
                'speakers.employeeProfile',
                'faqs'  => fn($q) => $q->where('status', 1)->orderBy('sort_order'),
                'tools' => fn($q) => $q->orderBy('sort_order'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $pageTitle  = $webinar->title;
        $isEnrolled = false;
        $user       = Auth::guard('web')->user();

        if ($user) {
            // Single DB query — no loop, safe here
            $isEnrolled = WebinarEnrollment::where('user_id', $user->id)
                ->where('webinar_id', $webinar->id)
                ->where('status', 1)
                ->exists();
        }

        $gateway = CoursePaymentGateway::where('status', 1)->first();

        $relatedWebinars = Webinar::where('id', '!=', $webinar->id)
            ->where(function ($q) use ($webinar) {
                $q->where('language', $webinar->language)
                  ->orWhere('status', $webinar->status);
            })
            ->whereNotNull('webinar_date')
            ->orderByDesc('webinar_date')
            ->limit(3)
            ->get();

        return view($this->activeTemplate . 'webinar-detail', compact(
            'pageTitle', 'webinar', 'isEnrolled', 'user', 'gateway', 'relatedWebinars'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INITIATE PAYMENT
    // ─────────────────────────────────────────────────────────────────────────
    public function initiatePayment(Request $request, Webinar $webinar)
    {
        if (!Auth::guard('web')->check()) {
            return response()->json(['redirect' => route('user.login')], 401);
        }

        $user = Auth::guard('web')->user();

        // Check enrollment via direct query (safe — single call)
        $alreadyEnrolled = WebinarEnrollment::where('user_id', $user->id)
            ->where('webinar_id', $webinar->id)
            ->where('status', 1)
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'You are already registered for this webinar.',
            ], 422);
        }

        // Check seats
        if ($webinar->total_seats !== null && $webinar->seats_left <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, this webinar is full.',
            ], 422);
        }

        // Free — enroll directly
        if ($webinar->type === 'free') {
            return $this->enrollFree($user, $webinar);
        }

        // Paid — Razorpay
        $gateway = CoursePaymentGateway::where('status', 1)->first();

        if (!$gateway) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not configured. Please contact support.',
            ], 500);
        }

        try {
            $order = WebinarOrder::create([
                'order_number' => 'WEB-' . strtoupper(substr(uniqid(), -6)) . '-' . time(),
                'user_id'      => $user->id,
                'webinar_id'   => $webinar->id,
                'gateway'      => $gateway->alias,
                'amount'       => $webinar->price,
                'currency'     => 'INR',
                'status'       => 'pending',
            ]);

            $razorpay = $this->getRazorpayInstance($gateway);

            $rpOrder = $razorpay->order->create([
                'receipt'  => $order->order_number,
                'amount'   => (int) ($webinar->price * 100),
                'currency' => 'INR',
                'notes'    => [
                    'webinar_id' => $webinar->id,
                    'user_id'    => $user->id,
                    'order_id'   => $order->id,
                ],
            ]);

            $order->update(['gateway_order_id' => $rpOrder->id]);

            return response()->json([
                'success'      => true,
                'key'          => $gateway->getCredential('key_id'),
                'amount'       => (int) ($webinar->price * 100),
                'currency'     => 'INR',
                'order_id'     => $rpOrder->id,
                'our_order_id' => $order->id,
                'webinar_name' => $webinar->title,
                'user_name'    => trim($user->firstname . ' ' . $user->lastname),
                'user_email'   => $user->email,
                'user_phone'   => $user->mobile ?? '',
                'callback_url' => route('webinars.payment.verify'),
            ]);

        } catch (\Exception $e) {
            Log::error('Webinar Razorpay order failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not initiate payment. Please try again.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERIFY PAYMENT
    // ─────────────────────────────────────────────────────────────────────────
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
            'our_order_id'        => 'required|integer',
        ]);

        $order = WebinarOrder::with('webinar')->findOrFail($request->our_order_id);
        $user  = Auth::guard('web')->user();

        if ($order->user_id !== optional($user)->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($order->status === 'paid') {
            return response()->json([
                'success'  => true,
                'message'  => 'Already paid.',
                'redirect' => route('webinars.detail', $order->webinar->slug),
            ]);
        }

        $gateway = CoursePaymentGateway::where('alias', $order->gateway)->first();

        try {
            $razorpay = $this->getRazorpayInstance($gateway);

            $razorpay->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);

            $order->update([
                'status'             => 'paid',
                'gateway_payment_id' => $request->razorpay_payment_id,
                'gateway_signature'  => $request->razorpay_signature,
                'gateway_response'   => json_encode($request->all()),
                'paid_at'            => now(),
            ]);

            WebinarEnrollment::updateOrCreate(
                ['user_id' => $user->id, 'webinar_id' => $order->webinar_id],
                [
                    'webinar_order_id' => $order->id,
                    'access_type'      => 'paid',
                    'enrolled_at'      => now(),
                    'status'           => 1,
                ]
            );

            $order->webinar->increment('total_enrolled');

            return response()->json([
                'success'  => true,
                'message'  => 'Registration successful! You are now enrolled.',
                'redirect' => route('webinars.detail', $order->webinar->slug),
            ]);

        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            $order->update([
                'status'           => 'failed',
                'gateway_response' => json_encode($request->all()),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed. Contact support with payment ID: ' . $request->razorpay_payment_id,
            ], 422);

        } catch (\Exception $e) {
            Log::error('Webinar payment verify exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please contact support.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function enrollFree($user, Webinar $webinar)
    {
        if ($webinar->total_seats !== null && $webinar->seats_left <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, this webinar is full.',
            ], 422);
        }

        WebinarEnrollment::updateOrCreate(
            ['user_id' => $user->id, 'webinar_id' => $webinar->id],
            [
                'access_type' => 'free',
                'enrolled_at' => now(),
                'status'      => 1,
            ]
        );

        $webinar->increment('total_enrolled');

        return response()->json([
            'success'  => true,
            'message'  => 'Registered successfully!',
            'redirect' => route('webinars.detail', $webinar->slug),
        ]);
    }

    private function getRazorpayInstance(CoursePaymentGateway $gateway): RazorpayApi
    {
        $keyId     = $gateway->getCredential('key_id');
        $keySecret = $gateway->getCredential('key_secret');

        if (!$keyId || !$keySecret) {
            throw new \Exception('Razorpay credentials not configured properly.');
        }

        return new RazorpayApi($keyId, $keySecret);
    }
}