{{-- FILE: resources/views/admin/courses/payment-gateway/index.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0">
                    <i class="las la-credit-card me-1"></i> @lang('Course Payment Gateways')
                </h5>
                <a href="{{ route('admin.courses.gateway.orders') }}" class="btn btn--primary btn--sm">
                    <i class="las la-list"></i> View All Orders
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>@lang('Gateway')</th>
                                <th>@lang('Description')</th>
                                <th>@lang('Mode')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gateways as $gw)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:42px;height:42px;border-radius:8px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;">
                                            <i class="las la-credit-card" style="font-size:22px;color:#1a56db;"></i>
                                        </div>
                                        <div>
                                            <strong>{{ $gw->name }}</strong>
                                            <small class="d-block text-muted">{{ $gw->alias }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $gw->description }}</small>
                                </td>
                                <td>
                                    @if($gw->test_mode)
                                        <span class="badge badge--warning">Test Mode</span>
                                    @else
                                        <span class="badge badge--success">Live Mode</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.courses.gateway.status', $gw) }}" title="Toggle Status">
                                        @if($gw->status)
                                            <span class="badge badge--success">Active</span>
                                        @else
                                            <span class="badge badge--danger">Inactive</span>
                                        @endif
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.courses.gateway.edit', $gw) }}"
                                       class="btn btn-sm btn--primary">
                                        <i class="las la-cog"></i> Configure
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">No gateways found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── QUICK STATS ────────────────────────────────────────────── --}}
@php
    $totalRev  = \App\Models\CourseOrder::where('status','paid')->sum('amount');
    $paidCount = \App\Models\CourseOrder::where('status','paid')->count();
    $pendCount = \App\Models\CourseOrder::where('status','pending')->count();
    $failCount = \App\Models\CourseOrder::where('status','failed')->count();
@endphp
<div class="row mt-4">
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--success">
            <div class="widget-two__icon b-radius--10"><i class="las la-rupee-sign"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">₹{{ number_format($totalRev) }}</h2>
                <p class="text-white">Total Revenue</p>
            </div>
            <a href="{{ route('admin.courses.gateway.orders') }}" class="widget-two__btn">View Orders</a>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--primary">
            <div class="widget-two__icon b-radius--10"><i class="las la-check-circle"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $paidCount }}</h2>
                <p class="text-white">Paid Orders</p>
            </div>
            <a href="{{ route('admin.courses.gateway.orders', ['status'=>'paid']) }}" class="widget-two__btn">View</a>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--warning">
            <div class="widget-two__icon b-radius--10"><i class="las la-clock"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $pendCount }}</h2>
                <p class="text-white">Pending Orders</p>
            </div>
            <a href="{{ route('admin.courses.gateway.orders', ['status'=>'pending']) }}" class="widget-two__btn">View</a>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--danger">
            <div class="widget-two__icon b-radius--10"><i class="las la-times-circle"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $failCount }}</h2>
                <p class="text-white">Failed Orders</p>
            </div>
            <a href="{{ route('admin.courses.gateway.orders', ['status'=>'failed']) }}" class="widget-two__btn">View</a>
        </div>
    </div>
</div>

@endsection