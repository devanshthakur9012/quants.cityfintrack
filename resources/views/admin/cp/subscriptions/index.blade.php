{{-- FILE: resources/views/admin/cp/subscriptions/index.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">User Subscriptions</h5>
                <p class="text-muted mb-0 small">All CP subscription records</p>
            </div>
            <a href="{{ route('admin.cp.index') }}" class="btn btn--secondary btn-sm">
                <i class="las la-arrow-left"></i> Back
            </a>
        </div>

        {{-- Filters --}}
        <div class="card b-radius--10 mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control form-control-sm"
                               value="{{ request('search') }}"
                               placeholder="Search by email or name...">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-control form-control-sm">
                            <option value="">All Status</option>
                            @foreach(['active','expired','cancelled','pending'] as $s)
                            <option value="{{ $s }}" @selected(request('status')===$s)>
                                {{ ucfirst($s) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="plan" class="form-control form-control-sm">
                            <option value="">All Plans</option>
                            @foreach($plans as $p)
                            <option value="{{ $p->id }}" @selected(request('plan')==$p->id)>
                                {{ $p->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn--primary btn-sm w-100">
                            <i class="las la-filter"></i> Filter
                        </button>
                    </div>
                    @if(request()->hasAny(['search','status','plan']))
                    <div class="col-md-1">
                        <a href="{{ route('admin.cp.subscriptions.index') }}"
                           class="btn btn--secondary btn-sm w-100">Reset</a>
                    </div>
                    @endif
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
                                <th>User</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Expires</th>
                                <th>Days Left</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subscriptions as $sub)
                            <tr>
                                <td>{{ $loop->iteration + ($subscriptions->currentPage()-1)*$subscriptions->perPage() }}</td>
                                <td>
                                    <strong>{{ $sub->user->firstname ?? '' }} {{ $sub->user->lastname ?? '' }}</strong><br>
                                    <small class="text-muted">{{ $sub->user->email ?? '' }}</small>
                                </td>
                                <td>
                                    <span style="background:{{ $sub->plan->badge_color ?? '#999' }};
                                                 color:#fff;padding:3px 10px;border-radius:4px;
                                                 font-size:12px;font-weight:700;">
                                        {{ $sub->plan->name ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $sc = ['active'=>'badge--success','expired'=>'badge--warning',
                                               'cancelled'=>'badge--danger','pending'=>'badge--info'];
                                    @endphp
                                    <span class="badge {{ $sc[$sub->status] ?? '' }}">
                                        {{ ucfirst($sub->status) }}
                                    </span>
                                </td>
                                <td>{{ $sub->starts_at?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $sub->expires_at?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    @if($sub->isActive())
                                        <strong style="color:#059669;">{{ $sub->days_remaining }}d</strong>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="button--group">
                                        <form action="{{ route('admin.cp.subscriptions.extend', $sub->id) }}"
                                              method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn--success btn--sm"
                                                    title="Extend 30 days"
                                                    onclick="return confirm('Extend by 30 days?')">
                                                <i class="las la-calendar-plus"></i>
                                            </button>
                                        </form>
                                        @if($sub->status !== 'cancelled')
                                        <form action="{{ route('admin.cp.subscriptions.cancel', $sub->id) }}"
                                              method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn--danger btn--sm"
                                                    title="Cancel"
                                                    onclick="return confirm('Cancel subscription?')">
                                                <i class="las la-times-circle"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="las la-users" style="font-size:3rem;color:#ccc;"></i>
                                    <h5 class="text-muted mt-2">No subscriptions found</h5>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($subscriptions->hasPages())
            <div class="card-footer">{{ paginateLinks($subscriptions) }}</div>
            @endif
        </div>
    </div>
</div>
@endsection