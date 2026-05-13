{{-- FILE: resources/views/admin/courses/payment-gateway/orders.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

{{-- Summary --}}
<div class="row">
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--success">
            <div class="widget-two__icon b-radius--10"><i class="las la-rupee-sign"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">₹{{ number_format($summary['total_revenue']) }}</h2>
                <p class="text-white">Total Revenue</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--primary">
            <div class="widget-two__icon b-radius--10"><i class="las la-shopping-cart"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $summary['total_orders'] }}</h2>
                <p class="text-white">Total Orders</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--warning">
            <div class="widget-two__icon b-radius--10"><i class="las la-clock"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $summary['pending_orders'] }}</h2>
                <p class="text-white">Pending</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--info">
            <div class="widget-two__icon b-radius--10"><i class="las la-check-circle"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $summary['paid_orders'] }}</h2>
                <p class="text-white">Paid Orders</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0"><i class="las la-list me-1"></i> @lang('Course Orders')</h5>
                <a href="{{ route('admin.courses.gateway.index') }}" class="btn btn--secondary btn--sm">
                    <i class="las la-arrow-left"></i> Gateway Settings
                </a>
            </div>

            {{-- Filter --}}
            <div class="card-body border-bottom pb-3">
                <form method="GET" class="row g-2">
                    <div class="col-md-5">
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-control form-control-sm"
                               placeholder="Search order number or user email…">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            @foreach($statusList as $s)
                                <option value="{{ $s }}" @selected(request('status') == $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button class="btn btn--primary btn--sm w-100"><i class="las la-search"></i> Filter</button>
                        <a href="{{ route('admin.courses.gateway.orders') }}" class="btn btn--secondary btn--sm w-100">Reset</a>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('Order')</th>
                                <th>@lang('User')</th>
                                <th>@lang('Course')</th>
                                <th>@lang('Amount')</th>
                                <th>@lang('Gateway')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Date')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $i => $order)
                            <tr>
                                <td>{{ $orders->firstItem() + $i }}</td>
                                <td>
                                    <strong>{{ $order->order_number }}</strong>
                                    @if($order->gateway_payment_id)
                                    <small class="d-block text-muted" style="font-size:10px;">{{ $order->gateway_payment_id }}</small>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $order->user->name ?? '—' }}</strong>
                                    <small class="d-block text-muted">{{ $order->user->email ?? '' }}</small>
                                </td>
                                <td>
                                    <span style="max-width:180px;display:block;white-space:normal;font-size:12.5px;">
                                        {{ Str::limit($order->course->title ?? '—', 40) }}
                                    </span>
                                </td>
                                <td><strong>₹{{ number_format($order->amount) }}</strong></td>
                                <td><span class="badge badge--info">{{ ucfirst($order->gateway) }}</span></td>
                                <td>{!! $order->status_badge !!}</td>
                                <td>
                                    {{ $order->created_at->format('d M Y') }}<br>
                                    <small class="text-muted">{{ $order->created_at->format('h:i A') }}</small>
                                </td>
                                <td>
                                    <a href="{{ route('admin.courses.gateway.orders.detail', $order) }}"
                                       class="btn btn-sm btn--primary">
                                        <i class="las la-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">No orders found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($orders->hasPages())
            <div class="card-footer">{{ $orders->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection