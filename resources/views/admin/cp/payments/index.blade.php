{{-- FILE: resources/views/admin/cp/payments/index.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">Subscription Payments</h5>
                <p class="text-muted mb-0 small">All CP subscription payment records</p>
            </div>
            <a href="{{ route('admin.cp.index') }}" class="btn btn--secondary btn-sm">
                <i class="las la-arrow-left"></i> Back
            </a>
        </div>

        {{-- Stats --}}
        <div class="row g-3 mb-4">
            @foreach([
                ['label'=>'Total Collected','val'=>'₹'.number_format($stats['total_paid']),  'bg'=>'bg--success','icon'=>'la-rupee-sign'],
                ['label'=>'Today',          'val'=>'₹'.number_format($stats['today_paid']),  'bg'=>'bg--primary','icon'=>'la-calendar-day'],
                ['label'=>'Pending',        'val'=>$stats['pending_count'],                  'bg'=>'bg--warning','icon'=>'la-clock'],
                ['label'=>'Failed',         'val'=>$stats['failed_count'],                   'bg'=>'bg--danger', 'icon'=>'la-times-circle'],
            ] as $s)
            <div class="col-md-3">
                <div class="widget-two style--two box--shadow2 b-radius--10 {{ $s['bg'] }}">
                    <div class="widget-two__icon b-radius--10">
                        <i class="las {{ $s['icon'] }}"></i>
                    </div>
                    <div class="widget-two__content">
                        <h2 class="text-white">{{ $s['val'] }}</h2>
                        <p class="text-white">{{ $s['label'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Filter --}}
        <div class="card b-radius--10 mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control form-control-sm"
                               value="{{ request('search') }}"
                               placeholder="Order number or email...">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control form-control-sm">
                            <option value="">All Status</option>
                            @foreach(['pending','paid','failed','refunded'] as $s)
                            <option value="{{ $s }}" @selected(request('status')===$s)>
                                {{ ucfirst($s) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn--primary btn-sm w-100">
                            <i class="las la-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card b-radius--10">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Order #</th>
                                <th>User</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Payment ID</th>
                                <th>Status</th>
                                <th>Paid At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $pay)
                            <tr>
                                <td>{{ $loop->iteration + ($payments->currentPage()-1)*$payments->perPage() }}</td>
                                <td><code class="small">{{ $pay->order_number }}</code></td>
                                <td>
                                    {{ $pay->user->firstname ?? '' }} {{ $pay->user->lastname ?? '' }}<br>
                                    <small class="text-muted">{{ $pay->user->email ?? '' }}</small>
                                </td>
                                <td>{{ $pay->plan->name ?? '—' }}</td>
                                <td><strong>₹{{ number_format($pay->amount) }}</strong></td>
                                <td>
                                    <code class="small">
                                        {{ $pay->gateway_payment_id ?? $pay->gateway_order_id ?? '—' }}
                                    </code>
                                </td>
                                <td>
                                    @php
                                        $pc = ['paid'=>'badge--success','pending'=>'badge--warning',
                                               'failed'=>'badge--danger','refunded'=>'badge--info'];
                                    @endphp
                                    <span class="badge {{ $pc[$pay->status] ?? '' }}">
                                        {{ ucfirst($pay->status) }}
                                    </span>
                                </td>
                                <td>{{ $pay->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="las la-receipt" style="font-size:3rem;color:#ccc;"></i>
                                    <h5 class="text-muted mt-2">No payments found</h5>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($payments->hasPages())
            <div class="card-footer">{{ paginateLinks($payments) }}</div>
            @endif
        </div>
    </div>
</div>
@endsection