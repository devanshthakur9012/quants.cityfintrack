{{-- FILE: resources/views/admin/events/index.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

<div class="row mb-3">
    @foreach([
        ['label'=>'All',      'key'=>'all',      'color'=>'primary',  'icon'=>'la-calendar'],
        ['label'=>'Upcoming', 'key'=>'upcoming',  'color'=>'success',  'icon'=>'la-calendar-check'],
        ['label'=>'Ongoing',  'key'=>'ongoing',   'color'=>'warning',  'icon'=>'la-play-circle'],
        ['label'=>'Past',     'key'=>'past',      'color'=>'secondary','icon'=>'la-history'],
    ] as $c)
    <div class="col-xl-3 col-sm-6 mb-3">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--{{ $c['color'] }}">
            <div class="widget-two__icon b-radius--10"><i class="las {{ $c['icon'] }}"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $counts[$c['key']] }}</h2>
                <p class="text-white">{{ $c['label'] }}</p>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card b-radius--10">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="card-title mb-0">Events</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.events.bookings') }}" class="btn btn--secondary btn--sm">
                <i class="las la-users"></i> Bookings
            </a>
            <a href="{{ route('admin.events.create') }}" class="btn btn--primary btn--sm">
                <i class="las la-plus"></i> Add Event
            </a>
        </div>
    </div>

    <div class="card-body border-bottom py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach(['upcoming','ongoing','past','draft'] as $s)
                        <option value="{{ $s }}" @selected(request('status') == $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search title..." value="{{ request('search') }}">
            </div>
            <button type="submit" class="btn btn--primary btn--sm">Filter</button>
            <a href="{{ route('admin.events.index') }}" class="btn btn--secondary btn--sm">Reset</a>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive--sm table-responsive">
            <table class="table table--light style--two">
                <thead>
                    <tr>
                        <th>#</th><th>Cover</th><th>Title</th><th>Badge</th>
                        <th>Status</th><th>Date</th><th>Price</th>
                        <th>Seats</th><th>Booking</th><th>Featured</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $i => $ev)
                    <tr>
                        <td>{{ $events->firstItem() + $i }}</td>
                        <td><img src="{{ $ev->thumbnail_url }}" style="width:64px;height:42px;object-fit:cover;border-radius:5px;"></td>
                        <td>
                            <strong style="font-size:13px;">{{ Str::limit($ev->title, 40) }}</strong>
                            <small class="d-block text-muted">{{ $ev->speakers_count }} speaker(s) · {{ $ev->bookings_count }} booked</small>
                        </td>
                        <td><span class="badge badge--info">{{ ucfirst($ev->badge) }}</span></td>
                        <td>
                            <a href="{{ route('admin.events.status', $ev) }}" title="Click to cycle">
                                @if($ev->status==='upcoming') <span class="badge badge--success">Upcoming</span>
                                @elseif($ev->status==='ongoing') <span class="badge badge--warning">Ongoing</span>
                                @elseif($ev->status==='past')   <span class="badge badge--secondary">Past</span>
                                @else <span class="badge badge--light">Draft</span> @endif
                            </a>
                        </td>
                        <td><small>{{ $ev->formatted_date }}</small></td>
                        <td>
                            @if($ev->type==='free') <span class="text-success fw-bold">FREE</span>
                            @else ₹{{ number_format($ev->price) }}
                                @if($ev->mrp > $ev->price)<small class="d-block text-muted text-decoration-line-through">₹{{ $ev->mrp }}</small>@endif
                            @endif
                        </td>
                        <td>
                            <small>{{ $ev->total_booked }} booked</small><br>
                            @if($ev->total_seats)
                                <small class="{{ $ev->seats_low ? 'text-danger' : 'text-muted' }}">{{ $ev->seats_left }} left</small>
                            @else
                                <small class="text-muted">Unlimited</small>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.events.booking.toggle', $ev) }}">
                                @if($ev->booking_open)
                                    <span class="badge badge--success">Open</span>
                                @else
                                    <span class="badge badge--danger">Closed</span>
                                @endif
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('admin.events.featured', $ev) }}"
                               class="text--{{ $ev->is_featured ? 'warning' : 'muted' }}">
                                <i class="las la-star{{ $ev->is_featured ? '' : '-o' }}" style="font-size:20px;"></i>
                            </a>
                        </td>
                        <td>
                            <div class="button--group">
                                <a href="{{ route('admin.events.edit', $ev) }}" class="btn btn-sm btn--primary"><i class="las la-pen"></i></a>
                                <button class="btn btn-sm btn--danger confirmationBtn"
                                        data-action="{{ route('admin.events.destroy', $ev) }}"
                                        data-question="Delete this event?"><i class="las la-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center py-4">No events. <a href="{{ route('admin.events.create') }}">Create one</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($events->hasPages())
    <div class="card-footer">{{ $events->withQueryString()->links() }}</div>
    @endif
</div>

@endsection