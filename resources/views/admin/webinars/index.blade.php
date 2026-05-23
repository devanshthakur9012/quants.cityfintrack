{{-- FILE: resources/views/admin/webinars/index.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

<div class="row mb-3">
    @foreach([
        ['label'=>'All',      'key'=>'all',      'color'=>'primary', 'icon'=>'la-video'],
        ['label'=>'Upcoming', 'key'=>'upcoming',  'color'=>'success', 'icon'=>'la-clock'],
        ['label'=>'Live',     'key'=>'live',      'color'=>'danger',  'icon'=>'la-broadcast-tower'],
        ['label'=>'Past',     'key'=>'past',      'color'=>'secondary','icon'=>'la-history'],
    ] as $c)
    <div class="col-xl-3 col-sm-6 mb-3">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--{{ $c['color'] }}">
            <div class="widget-two__icon b-radius--10"><i class="las {{ $c['icon'] }}"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $counts[$c['key']] }}</h2>
                <p class="text-white">{{ $c['label'] }}</p>
            </div>
            <a href="{{ route('admin.webinars.index', $c['key'] !== 'all' ? ['status'=>$c['key']] : []) }}"
               class="widget-two__btn">View</a>
        </div>
    </div>
    @endforeach
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Webinars</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.webinars.orders') }}" class="btn btn--secondary btn--sm">
                        <i class="las la-shopping-cart"></i> Orders
                    </a>
                    <a href="{{ route('admin.webinars.enrollments') }}" class="btn btn--secondary btn--sm">
                        <i class="las la-users"></i> Enrollments
                    </a>
                    <a href="{{ route('admin.webinars.create') }}" class="btn btn--primary btn--sm">
                        <i class="las la-plus"></i> Add Webinar
                    </a>
                </div>
            </div>

            {{-- Filters --}}
            <div class="card-body border-bottom py-2">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label small mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach(['upcoming','live','past'] as $s)
                                <option value="{{ $s }}" @selected(request('status') == $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Type</label>
                        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="free" @selected(request('type')=='free')>Free</option>
                            <option value="paid" @selected(request('type')=='paid')>Paid</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Language</label>
                        <select name="language" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach(['Hindi','English','Gujarati'] as $lang)
                                <option value="{{ $lang }}" @selected(request('language') == $lang)>{{ $lang }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Search title..." value="{{ request('search') }}">
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm">Filter</button>
                    <a href="{{ route('admin.webinars.index') }}" class="btn btn--secondary btn--sm">Reset</a>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Thumbnail</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Type / Mode</th>
                                <th>Date</th>
                                <th>Price / MRP</th>
                                <th>Seats</th>
                                <th>Featured</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($webinars as $i => $w)
                            <tr>
                                <td>{{ $webinars->firstItem() + $i }}</td>
                                <td>
                                    <img src="{{ $w->thumbnail_url }}" alt=""
                                         style="width:64px;height:42px;object-fit:cover;border-radius:5px;">
                                </td>
                                <td>
                                    <strong style="font-size:13px;">{{ Str::limit($w->title, 45) }}</strong>
                                </td>
                                <td>
                                    <a href="{{ route('admin.webinars.status', $w) }}" title="Click to cycle">
                                        @if($w->status==='live')    <span class="badge badge--danger">Live</span>
                                        @elseif($w->status==='upcoming') <span class="badge badge--success">Upcoming</span>
                                        @else                            <span class="badge badge--secondary">Past</span>
                                        @endif
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge--{{ $w->type==='free' ? 'success' : 'warning' }}">{{ ucfirst($w->type) }}</span>
                                    <span class="badge badge--info">{{ ucfirst($w->mode) }}</span>
                                </td>
                                <td><small>{{ $w->webinar_date ? $w->webinar_date->format('d M Y, H:i') : '—' }}</small></td>
                                <td>
                                    @if($w->price == 0)<span class="text-success fw-bold">FREE</span>
                                    @else ₹{{ number_format($w->price) }} @endif
                                    @if($w->mrp > $w->price)
                                        <small class="d-block text-muted text-decoration-line-through">₹{{ $w->mrp }}</small>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $w->total_enrolled }} enrolled</small><br>
                                    @if($w->total_seats)
                                        <small class="{{ $w->seats_left < 10 ? 'text-danger' : 'text-muted' }}">{{ $w->seats_left }} left / {{ $w->total_seats }}</small>
                                    @else
                                        <small class="text-muted">Unlimited</small>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.webinars.featured', $w) }}"
                                       class="text--{{ $w->is_featured ? 'warning' : 'muted' }}">
                                        <i class="las la-star{{ $w->is_featured ? '' : '-o' }}" style="font-size:20px;"></i>
                                    </a>
                                </td>
                                <td>
                                    <div class="button--group">
                                        <a href="{{ route('admin.webinars.edit', $w) }}" class="btn btn-sm btn--primary" title="Edit">
                                            <i class="las la-pen"></i>
                                        </a>
                                        <button class="btn btn-sm btn--danger confirmationBtn"
                                                data-action="{{ route('admin.webinars.destroy', $w) }}"
                                                data-question="Delete this webinar?">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    No webinars found. <a href="{{ route('admin.webinars.create') }}">Create one</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($webinars->hasPages())
            <div class="card-footer">{{ $webinars->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
