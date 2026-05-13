{{-- FILE: resources/views/admin/courses/index.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

<div class="row">
    {{-- ── STAT CARDS ── --}}
    @php
        use App\Models\Course;
        $total    = Course::count();
        $upcoming = Course::where('status','upcoming')->count();
        $ongoing  = Course::where('status','ongoing')->count();
        $recorded = Course::where('status','recorded')->count();
    @endphp
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--success">
            <div class="widget-two__icon b-radius--10"><i class="las la-book-open"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $total }}</h2>
                <p class="text-white">Total Courses</p>
            </div>
            <a href="{{ route('admin.courses.index') }}" class="widget-two__btn">View All</a>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--primary">
            <div class="widget-two__icon b-radius--10"><i class="las la-clock"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $upcoming }}</h2>
                <p class="text-white">Upcoming</p>
            </div>
            <a href="{{ route('admin.courses.index', ['status'=>'upcoming']) }}" class="widget-two__btn">View</a>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--warning">
            <div class="widget-two__icon b-radius--10"><i class="las la-play-circle"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $ongoing }}</h2>
                <p class="text-white">Ongoing</p>
            </div>
            <a href="{{ route('admin.courses.index', ['status'=>'ongoing']) }}" class="widget-two__btn">View</a>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-30">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--info">
            <div class="widget-two__icon b-radius--10"><i class="las la-video"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $recorded }}</h2>
                <p class="text-white">Recorded</p>
            </div>
            <a href="{{ route('admin.courses.index', ['status'=>'recorded']) }}" class="widget-two__btn">View</a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0">@lang('All Courses')</h5>
                <a href="{{ route('admin.courses.create') }}" class="btn btn--primary btn--sm">
                    <i class="las la-plus"></i> Add New Course
                </a>
            </div>

            {{-- ── FILTERS ── --}}
            <div class="card-body border-bottom pb-3">
                <form method="GET" action="{{ route('admin.courses.index') }}" class="row g-2">
                    <div class="col-md-4 col-sm-6">
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-control form-control-sm" placeholder="Search title...">
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" @selected(request('status') == $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 d-flex gap-2">
                        <button class="btn btn--primary btn--sm w-100" type="submit">
                            <i class="las la-search"></i> Filter
                        </button>
                        <a href="{{ route('admin.courses.index') }}" class="btn btn--secondary btn--sm w-100">
                            <i class="las la-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- ── TABLE ── --}}
            <div class="card-body p-0">
                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>@lang('#')</th>
                                <th>@lang('Course')</th>
                                <th>@lang('Category')</th>
                                <th>@lang('Mode / Level')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Price')</th>
                                <th>@lang('Lessons')</th>
                                <th>@lang('Featured')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($courses as $i => $course)
                            <tr>
                                <td>{{ $courses->firstItem() + $i }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $course->thumbnail_url }}"
                                             alt="{{ $course->title }}"
                                             class="rounded" width="56" height="40"
                                             style="object-fit:cover; flex-shrink:0;">
                                        <div>
                                            <strong class="d-block" style="font-size:13px;max-width:220px;white-space:normal;">
                                                {{ $course->title }}
                                            </strong>
                                            @if($course->batch_name)
                                            <small class="text--muted">{{ $course->batch_name }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge--info">{{ $course->category->name ?? '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge badge--secondary">{{ ucfirst($course->mode) }}</span><br>
                                    <small class="text--muted">{{ ucfirst($course->level) }}</small>
                                </td>
                                <td>{!! $course->status_badge !!}</td>
                                <td>
                                    @if($course->type === 'free')
                                        <span class="badge badge--success">FREE</span>
                                    @else
                                        <strong>{{ $course->formatted_price }}</strong><br>
                                        @if($course->discount_label)
                                        <small class="text--success">{{ $course->discount_label }}</small>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge--primary">
                                        {{ $course->lessons_count }} lessons
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.courses.featured', $course) }}" class="text--{{ $course->is_featured ? 'warning' : 'muted' }}" title="Toggle Featured">
                                        <i class="las la-star{{ $course->is_featured ? '' : '-o' }}" style="font-size:20px;"></i>
                                    </a>
                                </td>
                                <td>
                                    <div class="button--group">
                                        {{-- Status quick change --}}
                                        <button type="button" class="btn btn-sm btn--light-secondary statusBtn"
                                                data-route="{{ route('admin.courses.status', $course) }}"
                                                data-current="{{ $course->status }}">
                                            <i class="las la-sync-alt"></i>
                                        </button>

                                        <a href="{{ route('admin.courses.curriculum', $course) }}"
                                           class="btn btn-sm btn--info" title="Curriculum">
                                            <i class="las la-layer-group"></i>
                                        </a>

                                        <a href="{{ route('admin.courses.edit', $course) }}"
                                           class="btn btn-sm btn--primary" title="Edit">
                                            <i class="las la-pen"></i>
                                        </a>

                                        <button class="btn btn-sm btn--danger confirmationBtn"
                                                data-action="{{ route('admin.courses.destroy', $course) }}"
                                                data-question="Delete this course? All lessons and videos will be permanently removed."
                                                title="Delete">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="las la-book-open" style="font-size:48px;opacity:.3;display:block;margin-bottom:8px;"></i>
                                    No courses found.
                                    <a href="{{ route('admin.courses.create') }}">Create one</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($courses->hasPages())
            <div class="card-footer">{{ $courses->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>

{{-- STATUS MODAL --}}
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Status</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="statusForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="status" class="form-select">
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="recorded">Recorded</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--secondary btn--sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--primary btn--sm">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
// Status quick change
document.querySelectorAll('.statusBtn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var modal  = document.getElementById('statusModal');
        var form   = document.getElementById('statusForm');
        var select = form.querySelector('[name="status"]');

        form.action = this.dataset.route;
        select.value = this.dataset.current;

        var bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    });
});
</script>
@endpush