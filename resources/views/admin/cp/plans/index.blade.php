{{-- FILE: resources/views/admin/cp/plans/index.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">Subscription Plans</h5>
                <p class="text-muted mb-0 small">
                    Define plans and assign which analyses are included in each
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.cp.index') }}" class="btn btn--secondary btn-sm">
                    <i class="las la-arrow-left"></i> Back
                </a>
                <button class="btn btn--primary btn-sm"
                        data-bs-toggle="modal" data-bs-target="#addPlanModal">
                    <i class="las la-plus-circle"></i> Add Plan
                </button>
            </div>
        </div>

        {{-- Plan cards --}}
        <div class="row g-4 mb-4">
            @forelse($plans as $plan)
            <div class="col-md-4">
                <div class="card b-radius--10 h-100"
                     style="border-top:4px solid {{ $plan->badge_color }};
                            border:1px solid #e5e9f2;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span style="background:{{ $plan->badge_color }};color:#fff;
                                             padding:4px 14px;border-radius:20px;font-size:12px;
                                             font-weight:700;display:inline-block;margin-bottom:8px;">
                                    {{ strtoupper($plan->name) }}
                                </span>
                                <h4 style="font-family:'Rajdhani',sans-serif;font-weight:700;
                                           color:#1a1a2e;margin:0;">
                                    @if($plan->price_monthly == 0)
                                        FREE
                                    @else
                                        ₹{{ number_format($plan->price_monthly) }}
                                        <small style="font-size:14px;color:#888;">/mo</small>
                                    @endif
                                </h4>
                            </div>
                            <div class="d-flex gap-1">
                                <button class="btn btn--info btn--sm edit-plan-btn"
                                        data-id="{{ $plan->id }}"
                                        data-name="{{ $plan->name }}"
                                        data-desc="{{ $plan->description }}"
                                        data-price="{{ $plan->price_monthly }}"
                                        data-color="{{ $plan->badge_color }}"
                                        data-sort="{{ $plan->sort_order }}"
                                        data-active="{{ $plan->is_active ? 1 : 0 }}"
                                        data-features="{{ implode("\n", $plan->features ?? []) }}"
                                        data-analysis_ids="{{ $plan->analyses->pluck('id')->toJson() }}">
                                    <i class="las la-pen"></i>
                                </button>
                                <form action="{{ route('admin.cp.plans.destroy', $plan->id) }}"
                                      method="POST" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--sm"
                                            onclick="return confirm('Delete plan {{ $plan->name }}?')">
                                        <i class="las la-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <p style="font-size:13px;color:#666;margin-bottom:12px;">
                            {{ $plan->description }}
                        </p>

                        @if(!empty($plan->features))
                        <ul style="list-style:none;padding:0;margin:0 0 14px;">
                            @foreach($plan->features as $f)
                            <li style="padding:4px 0;font-size:13px;color:#444;
                                       display:flex;align-items:center;gap:8px;">
                                <i class="las la-check-circle"
                                   style="color:{{ $plan->badge_color }};"></i>
                                {{ $f }}
                            </li>
                            @endforeach
                        </ul>
                        @endif

                        <div style="background:#f4f6fb;border-radius:8px;
                                    padding:8px 14px;font-size:13px;color:#555;">
                            <strong>{{ $plan->analyses->count() }}</strong> analyses
                            &nbsp;·&nbsp;
                            <strong>{{ $plan->active_count }}</strong> active subscribers
                        </div>

                        @if($plan->analyses->count())
                        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:5px;">
                            @foreach($plan->analyses->take(5) as $a)
                            <span style="background:#e8f5e9;color:#2e7d32;border-radius:4px;
                                         padding:2px 8px;font-size:11px;font-weight:600;">
                                {{ $a->name }}
                            </span>
                            @endforeach
                            @if($plan->analyses->count() > 5)
                            <span style="background:#f0f0f0;color:#888;border-radius:4px;
                                         padding:2px 8px;font-size:11px;">
                                +{{ $plan->analyses->count() - 5 }} more
                            </span>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="text-center py-5 text-muted">
                    <i class="las la-crown" style="font-size:3rem;color:#ccc;display:block;margin-bottom:12px;"></i>
                    No plans yet. Create Free, Pro, Pro Plus.
                </div>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ════ ADD PLAN MODAL ════ --}}
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('admin.cp.plans.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Subscription Plan</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required">Name</label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="Pro" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Slug</label>
                            <input type="text" name="slug" class="form-control"
                                   placeholder="pro" required>
                            <small class="text-muted">Unique: free / pro / pro_plus</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price/Month (₹)</label>
                            <input type="number" name="price_monthly" class="form-control"
                                   value="0" min="0" step="1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Badge Color</label>
                            <input type="color" name="badge_color"
                                   class="form-control form-control-color w-100"
                                   value="#1a56db">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Sort</label>
                            <input type="number" name="sort_order" class="form-control"
                                   value="0" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="What this plan includes..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                Features
                                <small class="text-muted">(one per line)</small>
                            </label>
                            <textarea name="features_text" class="form-control" rows="5"
                                      placeholder="Unlimited OI Analysis&#10;Priority Support&#10;All Premium Tools"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                Assign Analyses
                                <small class="text-muted">(tick which tools belong to this plan)</small>
                            </label>
                            <div class="border rounded p-2"
                                 style="max-height:180px;overflow-y:auto;">
                                @foreach($analyses as $a)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="analysis_ids[]"
                                           value="{{ $a->id }}"
                                           id="ap_a{{ $a->id }}">
                                    <label class="form-check-label small"
                                           for="ap_a{{ $a->id }}">
                                        {{ $a->name }}
                                        <span style="font-size:10px;
                                            color:{{ $a->plan_tier==='free'?'#059669':($a->plan_tier==='pro'?'#1a56db':'#7c3aed') }}">
                                            ({{ strtoupper($a->plan_tier) }})
                                        </span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Active</label>
                            <select name="is_active" class="form-control">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-plus-circle"></i> Create Plan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ════ EDIT PLAN MODAL ════ --}}
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editPlanForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Plan</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="ep_name"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price/Month (₹)</label>
                            <input type="number" name="price_monthly" id="ep_price"
                                   class="form-control" min="0" step="1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Badge Color</label>
                            <input type="color" name="badge_color" id="ep_color"
                                   class="form-control form-control-color w-100">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Sort</label>
                            <input type="number" name="sort_order" id="ep_sort"
                                   class="form-control" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Active</label>
                            <select name="is_active" id="ep_active" class="form-control">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="ep_desc"
                                      class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Features (one per line)</label>
                            <textarea name="features_text" id="ep_features"
                                      class="form-control" rows="5"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign Analyses</label>
                            <div class="border rounded p-2"
                                 style="max-height:180px;overflow-y:auto;">
                                @foreach($analyses as $a)
                                <div class="form-check">
                                    <input class="form-check-input ep-check"
                                           type="checkbox" name="analysis_ids[]"
                                           value="{{ $a->id }}" id="ep_a{{ $a->id }}">
                                    <label class="form-check-label small"
                                           for="ep_a{{ $a->id }}">
                                        {{ $a->name }}
                                        <span style="font-size:10px;
                                            color:{{ $a->plan_tier==='free'?'#059669':($a->plan_tier==='pro'?'#1a56db':'#7c3aed') }}">
                                            ({{ strtoupper($a->plan_tier) }})
                                        </span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
$(document).on('click', '.edit-plan-btn', function () {
    const btn = $(this);
    const id  = btn.data('id');
    const url = "{{ route('admin.cp.plans.update', ':id') }}".replace(':id', id);

    $('#editPlanForm').attr('action', url);
    $('#ep_name').val(btn.data('name'));
    $('#ep_price').val(btn.data('price'));
    $('#ep_color').val(btn.data('color'));
    $('#ep_sort').val(btn.data('sort'));
    $('#ep_active').val(String(btn.data('active')));
    $('#ep_desc').val(btn.data('desc'));
    $('#ep_features').val(btn.data('features'));

    const ids = btn.data('analysis_ids');
    $('.ep-check').prop('checked', false);
    if (Array.isArray(ids)) {
        ids.forEach(id => $('#ep_a' + id).prop('checked', true));
    }

    $('#editPlanModal').modal('show');
});
</script>
@endpush