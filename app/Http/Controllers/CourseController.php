<?php
// FILE: app/Http/Controllers/CourseController.php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseEnrollment;
use App\Models\CourseOrder;
use App\Models\CoursePaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api as RazorpayApi;

class CourseController extends Controller
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
        $pageTitle = 'Option Courses';

        $heroBanner = [
            'title'       => 'Learn Option',
            'description' => 'Enhance your options trading skills with our structured courses.
                              From beginner to advanced, every course is designed to give you
                              practical, data-driven insights to trade smarter in the derivatives market.',
            'banners'     => [
                asset('assets/images/courses/banner1.jpg'),
                asset('assets/images/courses/banner2.jpg'),
                asset('assets/images/courses/banner3.jpg'),
                asset('assets/images/courses/banner4.jpg'),
            ],
        ];

        $categories = CourseCategory::active()
            ->withCount(['courses' => fn($q) => $q->whereNotIn('status', ['draft'])])
            ->orderBy('sort_order')->orderBy('name')->get();

        $filterLang     = $request->input('language', '');
        $filterLevel    = $request->input('level', '');
        $filterType     = $request->input('type', '');
        $filterMode     = $request->input('mode', '');
        $filterStatus   = $request->input('status', '');
        $filterCategory = $request->input('category', '');
        $filterSearch   = trim($request->input('search', ''));

        // Eager load sections (for count) and lessons (for duration + lesson count).
        // Sections also need their lessons loaded so section-level duration works
        // without extra queries — but for the listing we only need flat lessons totals,
        // so we load lessons directly on the course (flat relation).
        $allCourses = Course::with(['category', 'sections', 'lessons'])
            ->whereNotIn('status', ['draft'])
            ->when($filterLang,     fn($q) => $q->where('language', $filterLang))
            ->when($filterLevel,    fn($q) => $q->where('level', $filterLevel))
            ->when($filterType,     fn($q) => $q->where('type', $filterType))
            ->when($filterMode,     fn($q) => $q->where('mode', $filterMode))
            ->when($filterStatus,   fn($q) => $q->where('status', $filterStatus))
            ->when($filterCategory, fn($q) => $q->where('course_category_id', $filterCategory))
            ->when($filterSearch,   fn($q) => $q->where('title', 'like', '%' . $filterSearch . '%'))
            ->orderByRaw("FIELD(status, 'ongoing', 'upcoming', 'recorded')")
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        $totalCounts = [
            'all'      => Course::whereNotIn('status', ['draft'])->count(),
            'ongoing'  => Course::where('status', 'ongoing')->count(),
            'upcoming' => Course::where('status', 'upcoming')->count(),
            'recorded' => Course::where('status', 'recorded')->count(),
        ];

        return view($this->activeTemplate . 'courses', compact(
            'pageTitle', 'heroBanner', 'categories', 'allCourses',
            'totalCounts', 'filterLang', 'filterLevel', 'filterType',
            'filterMode', 'filterStatus', 'filterCategory', 'filterSearch'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DETAIL  (public — auth only needed to buy/enroll)
    // ─────────────────────────────────────────────────────────────────────────
    public function detail(string $slug)
    {
        $course = Course::with([
                'category',
                'sections'         => fn($q) => $q->orderBy('sort_order'),
                'sections.lessons' => fn($q) => $q->orderBy('sort_order'),
                'lessons',                          // flat — for total duration calc
                'trainers.employeeProfile',
                'faqs'             => fn($q) => $q->where('status', 1)->orderBy('sort_order'),
            ])
            ->whereNotIn('status', ['draft'])
            ->where('slug', $slug)
            ->firstOrFail();

        $pageTitle = $course->title;

        $isEnrolled = false;
        $enrollment = null;
        $user       = Auth::guard('web')->user();

        if ($user) {
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('status', 1)
                ->first();
            $isEnrolled = $enrollment && $enrollment->isActive();
        }

        $gateway = CoursePaymentGateway::activeGateway();

        $relatedCourses = Course::with(['category', 'lessons'])
            ->where('course_category_id', $course->course_category_id)
            ->where('id', '!=', $course->id)
            ->whereNotIn('status', ['draft'])
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        // All computed from already-loaded relations — zero extra queries.
        $totalLessons = $course->sections->sum(fn($s) => $s->lessons->count());

        $dSecs        = $course->lessons->sum('duration_seconds');
        $dH           = floor($dSecs / 3600);
        $dM           = floor(($dSecs % 3600) / 60);
        $totalDuration = $dH > 0 ? "{$dH}h {$dM}m" : "{$dM}m";

        // $freeLessons removed — free access is now handled via per-section /
        // per-lesson preview videos (preview_video_type / preview_embed_id),
        // not the old is_free_preview toggle.

        return view($this->activeTemplate . 'course-detail', compact(
            'pageTitle', 'course', 'isEnrolled', 'enrollment',
            'user', 'gateway', 'relatedCourses',
            'totalLessons', 'totalDuration'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INITIATE PAYMENT
    // ─────────────────────────────────────────────────────────────────────────
    public function initiatePayment(Request $request, Course $course)
    {
        if (!Auth::guard('web')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['redirect' => route('user.login')], 401);
            }
            return redirect()->route('user.login')
                ->with('loginRedirect', route('courses.detail', $course->slug));
        }

        $user = Auth::guard('web')->user();

        if ($course->isEnrolledBy($user)) {
            return response()->json(['success' => false, 'message' => 'You are already enrolled in this course.'], 422);
        }

        if ($course->type === 'free') {
            return $this->enrollFree($user, $course);
        }

        $gateway = CoursePaymentGateway::activeGateway();
        if (!$gateway) {
            return response()->json(['success' => false, 'message' => 'Payment gateway not configured. Please contact support.'], 500);
        }

        try {
            $order = CourseOrder::create([
                'order_number'   => CourseOrder::generateOrderNumber(),
                'user_id'        => $user->id,
                'course_id'      => $course->id,
                'gateway'        => $gateway->alias,
                'amount'         => $course->price,
                'original_price' => $course->price,
                'currency'       => 'INR',
                'status'         => 'pending',
            ]);

            $razorpay = $this->getRazorpayInstance($gateway);
            $rpOrder  = $razorpay->order->create([
                'receipt'  => $order->order_number,
                'amount'   => (int) ($course->price * 100),
                'currency' => 'INR',
                'notes'    => ['course_id' => $course->id, 'user_id' => $user->id, 'order_id' => $order->id],
            ]);

            $order->update(['gateway_order_id' => $rpOrder->id]);

            return response()->json([
                'success'      => true,
                'key'          => $gateway->getCredential('key_id'),
                'amount'       => (int) ($course->price * 100),
                'currency'     => 'INR',
                'order_id'     => $rpOrder->id,
                'our_order_id' => $order->id,
                'course_name'  => $course->title,
                'user_name'    => trim($user->firstname . ' ' . $user->lastname),
                'user_email'   => $user->email,
                'user_phone'   => $user->mobile ?? '',
                'callback_url' => route('courses.payment.verify'),
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay order creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not initiate payment. Please try again.'], 500);
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

        $order = CourseOrder::findOrFail($request->our_order_id);
        $user  = Auth::guard('web')->user();

        if ($order->user_id !== optional($user)->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($order->isPaid()) {
            return response()->json(['success' => true, 'message' => 'Already paid.', 'redirect' => route('courses.detail', $order->course->slug)]);
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

            CourseEnrollment::updateOrCreate(
                ['user_id' => $user->id, 'course_id' => $order->course_id],
                ['course_order_id' => $order->id, 'access_type' => 'paid', 'enrolled_at' => now(), 'expires_at' => null, 'status' => 1]
            );

            $order->course->increment('total_enrolled');

            return response()->json([
                'success'  => true,
                'message'  => 'Payment successful! You are now enrolled.',
                'redirect' => route('courses.detail', $order->course->slug),
            ]);

        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            $order->update(['status' => 'failed', 'gateway_response' => json_encode($request->all())]);
            return response()->json(['success' => false, 'message' => 'Verification failed. Contact support with ID: ' . $request->razorpay_payment_id], 422);
        } catch (\Exception $e) {
            Log::error('Payment verify exception: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Something went wrong. Please contact support.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function enrollFree($user, Course $course)
    {
        CourseEnrollment::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['access_type' => 'free', 'enrolled_at' => now(), 'expires_at' => null, 'status' => 1]
        );
        $course->increment('total_enrolled');
        return response()->json([
            'success'  => true,
            'message'  => 'Enrolled successfully!',
            'redirect' => route('courses.detail', $course->slug),
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