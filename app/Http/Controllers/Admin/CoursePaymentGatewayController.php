<?php
// FILE: app/Http/Controllers/Admin/CoursePaymentGatewayController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseOrder;
use App\Models\CoursePaymentGateway;
use Illuminate\Http\Request;

class CoursePaymentGatewayController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GATEWAY SETTINGS PAGE
    // ─────────────────────────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle = 'Course Payment Gateway';
        $gateways  = CoursePaymentGateway::orderBy('id')->get();

        // Seed Razorpay if not exists
        if ($gateways->isEmpty()) {
            CoursePaymentGateway::create([
                'name'        => 'Razorpay',
                'alias'       => 'razorpay',
                'description' => 'Accept payments via Razorpay — UPI, Cards, NetBanking, Wallets.',
                'status'      => 0,
                'test_mode'   => 1,
            ]);
            $gateways = CoursePaymentGateway::orderBy('id')->get();
        }

        return view('admin.courses.payment-gateway.index', compact('pageTitle', 'gateways'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT PAGE
    // ─────────────────────────────────────────────────────────────────────────
    public function edit(CoursePaymentGateway $gateway)
    {
        $pageTitle = 'Configure: ' . $gateway->name;
        $creds     = $gateway->getRawCredentials();
        return view('admin.courses.payment-gateway.edit', compact('pageTitle', 'gateway', 'creds'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, CoursePaymentGateway $gateway)
    {
        $request->validate([
            'status'    => 'required|in:0,1',
            'test_mode' => 'required|in:0,1',
            'key_id'    => 'required|string|max:200',
            'key_secret'=> 'required|string|max:200',
        ]);

        // If activating this gateway, deactivate others
        if ($request->status == 1) {
            CoursePaymentGateway::where('id', '!=', $gateway->id)->update(['status' => 0]);
        }

        $gateway->update([
            'status'      => $request->status,
            'test_mode'   => $request->test_mode,
            'credentials' => [
                'key_id'     => trim($request->key_id),
                'key_secret' => trim($request->key_secret),
            ],
        ]);

        $notify[] = ['success', $gateway->name . ' settings updated successfully'];
        return redirect()->route('admin.courses.gateway.index')->withNotify($notify);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATUS TOGGLE
    // ─────────────────────────────────────────────────────────────────────────
    public function statusToggle(CoursePaymentGateway $gateway)
    {
        $newStatus = $gateway->status == 1 ? 0 : 1;

        if ($newStatus == 1) {
            CoursePaymentGateway::where('id', '!=', $gateway->id)->update(['status' => 0]);
        }

        $gateway->update(['status' => $newStatus]);
        $notify[] = ['success', 'Gateway status updated'];
        return back()->withNotify($notify);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ORDERS LIST
    // ─────────────────────────────────────────────────────────────────────────
    public function orders(Request $request)
    {
        $pageTitle = 'Course Orders';

        $orders = CourseOrder::with(['user', 'course'])
            ->when($request->status,  fn($q) => $q->where('status', $request->status))
            ->when($request->search,  fn($q) => $q->where('order_number', 'like', '%' . $request->search . '%')
                                                    ->orWhereHas('user', fn($u) => $u->where('email', 'like', '%' . $request->search . '%')))
            ->orderByDesc('created_at')
            ->paginate(getPaginate());

        $statusList = ['pending', 'paid', 'failed', 'refunded'];

        // Summary counts
        $summary = [
            'total_revenue' => CourseOrder::where('status', 'paid')->sum('amount'),
            'total_orders'  => CourseOrder::count(),
            'paid_orders'   => CourseOrder::where('status', 'paid')->count(),
            'pending_orders'=> CourseOrder::where('status', 'pending')->count(),
        ];

        return view('admin.courses.payment-gateway.orders', compact(
            'pageTitle', 'orders', 'statusList', 'summary'
        ));
    }

    // ── Order detail / manual verify ────────────────────────────────────────
    public function orderDetail(CourseOrder $order)
    {
        $pageTitle = 'Order: ' . $order->order_number;
        $order->load(['user', 'course', 'enrollment']);
        return view('admin.courses.payment-gateway.order-detail', compact('pageTitle', 'order'));
    }

    // Manual enrollment (admin can grant access)
    public function manualEnroll(CourseOrder $order)
    {
        if ($order->isPaid()) {
            $notify[] = ['error', 'Order is already paid and enrolled.'];
            return back()->withNotify($notify);
        }

        $order->update(['status' => 'paid', 'paid_at' => now()]);

        \App\Models\CourseEnrollment::updateOrCreate(
            ['user_id' => $order->user_id, 'course_id' => $order->course_id],
            [
                'course_order_id' => $order->id,
                'access_type'     => 'manual',
                'enrolled_at'     => now(),
                'status'          => 1,
            ]
        );

        $order->course->increment('total_enrolled');

        $notify[] = ['success', 'User manually enrolled in the course.'];
        return back()->withNotify($notify);
    }
}