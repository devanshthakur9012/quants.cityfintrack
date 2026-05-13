{{-- FILE: resources/views/admin/courses/payment-gateway/order-detail.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

<div class="row justify-content-center">
    <div class="col-xl-8">

        {{-- Order Info Card --}}
        <div class="card b-radius--10 mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">
                    <i class="las la-receipt me-1"></i> Order: {{ $order->order_number }}
                </h5>
                {!! $order->status_badge !!}
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Order Number</span>
                        <strong>{{ $order->order_number }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Amount</span>
                        <strong>₹{{ number_format($order->amount) }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Original Price</span>
                        <span>₹{{ number_format($order->original_price) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Currency</span>
                        <span>{{ $order->currency }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Gateway</span>
                        <span class="badge badge--info">{{ ucfirst($order->gateway) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Status</span>
                        {!! $order->status_badge !!}
                    </li>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Order Date</span>
                        <span>{{ $order->created_at->format('d M Y, h:i A') }}</span>
                    </li>
                    @if($order->paid_at)
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Paid At</span>
                        <span class="text--success">{{ $order->paid_at->format('d M Y, h:i A') }}</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- Gateway Details --}}
        @if($order->gateway_order_id || $order->gateway_payment_id)
        <div class="card b-radius--10 mb-3">
            <div class="card-header"><h6 class="card-title mb-0"><i class="las la-plug me-1"></i> Gateway Details</h6></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @if($order->gateway_order_id)
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Gateway Order ID</span>
                        <code>{{ $order->gateway_order_id }}</code>
                    </li>
                    @endif
                    @if($order->gateway_payment_id)
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span class="text-muted">Payment ID</span>
                        <code>{{ $order->gateway_payment_id }}</code>
                    </li>
                    @endif
                    @if($order->gateway_signature)
                    <li class="list-group-item">
                        <span class="text-muted d-block mb-1">Signature</span>
                        <small style="word-break:break-all;font-size:11px;color:#888;">{{ $order->gateway_signature }}</small>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
        @endif

        {{-- User + Course --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card b-radius--10 mb-3">
                    <div class="card-header"><h6 class="card-title mb-0"><i class="las la-user me-1"></i> User</h6></div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ $order->user->name ?? '—' }}</strong></p>
                        <p class="mb-1 text-muted small">{{ $order->user->email ?? '' }}</p>
                        <p class="mb-0 text-muted small">{{ $order->user->mobile ?? '' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card b-radius--10 mb-3">
                    <div class="card-header"><h6 class="card-title mb-0"><i class="las la-book me-1"></i> Course</h6></div>
                    <div class="card-body">
                        <p class="mb-1"><strong>{{ $order->course->title ?? '—' }}</strong></p>
                        <p class="mb-0 text-muted small">₹{{ number_format($order->course->price ?? 0) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Enrollment --}}
        <div class="card b-radius--10 mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="card-title mb-0"><i class="las la-graduation-cap me-1"></i> Enrollment</h6>
                @if(!$order->isPaid())
                <form action="{{ route('admin.courses.gateway.orders.enroll', $order) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn--success btn--sm"
                            onclick="return confirm('Manually mark this order as paid and enroll the user?')">
                        <i class="las la-user-check"></i> Manual Enroll
                    </button>
                </form>
                @endif
            </div>
            <div class="card-body p-0">
                @if($order->enrollment)
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Access Type</span>
                        <span class="badge badge--info">{{ ucfirst($order->enrollment->access_type) }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Enrolled At</span>
                        <span>{{ $order->enrollment->enrolled_at?->format('d M Y, h:i A') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Expires At</span>
                        <span>{{ $order->enrollment->expires_at ? $order->enrollment->expires_at->format('d M Y') : 'Lifetime' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Status</span>
                        @if($order->enrollment->status)
                            <span class="badge badge--success">Active</span>
                        @else
                            <span class="badge badge--danger">Revoked</span>
                        @endif
                    </li>
                </ul>
                @else
                <div class="text-center py-4 text-muted">
                    <i class="las la-user-times" style="font-size:32px;opacity:.4;display:block;margin-bottom:8px;"></i>
                    No enrollment record yet.
                    @if(!$order->isPaid())
                    <br><small>Use "Manual Enroll" to grant access.</small>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <div class="text-center">
            <a href="{{ route('admin.courses.gateway.orders') }}" class="btn btn--secondary btn--sm">
                <i class="las la-arrow-left"></i> Back to Orders
            </a>
        </div>
    </div>
</div>

@endsection