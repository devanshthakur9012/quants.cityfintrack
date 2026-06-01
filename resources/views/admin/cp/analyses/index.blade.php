{{-- FILE: resources/views/admin/cp/analyses/index.blade.php --}}
@extends('admin.layouts.app')

@push('style')
<style>
.tier-badge { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; color:#fff; }
.tier-free  { background:#059669; }
.tier-pro   { background:#1a56db; }
.tier-pro_plus { background:#7c3aed; }
.faq-row { background:#f8fafc; border-radius:8px; padding:10px; margin-bottom:8px; }
</style>
@endpush

@section('panel')
<div class="row">
    <div class="col-lg-12">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">Analysis Tools</h5>
                <p class="text-muted mb-0 small">
                    Create analysis modules and assign them to subscription tiers
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.cp.index') }}" class="btn btn--secondary btn-sm">
                    <i class="las la-arrow-left"></i> Back
                </a>
                <button class="btn btn--primary btn-sm"
                        data-bs-toggle="modal" data-bs-target="#addAnalysisModal">
                    <i class="las la-plus-circle"></i> Add Analysis
                </button>
            </div>
        </div>

        {{-- Stats --}}
        <div class="row g-3 mb-4">
            @php
                $all      = $analyses->total();
                $free     = $analyses->getCollection()->where('plan_tier','free')->count();
                $pro      = $analyses->getCollection()->where('plan_tier','pro')->count();
                $proPlus  = $analyses->getCollection()->where('plan_tier','pro_plus')->count();
            @endphp
            @foreach([
                ['label'=>'Total','val'=>$all,     'bg'=>'bg--primary','icon'=>'la-brain'],
                ['label'=>'Free', 'val'=>$free,    'bg'=>'bg--success','icon'=>'la-gift'],
                ['label'=>'Pro',  'val'=>$pro,     'bg'=>'bg--info',   'icon'=>'la-crown'],
                ['label'=>'Pro+', 'val'=>$proPlus, 'bg'=>'bg--warning','icon'=>'la-gem'],
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

        <div class="card b-radius--10">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Analysis</th>
                                <th>Route</th>
                                <th>Tier</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Sort</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($analyses as $a)
                            <tr>
                                <td>{{ $loop->iteration + ($analyses->currentPage()-1) * $analyses->perPage() }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($a->thumbnail)
                                            <img src="{{ $a->thumbnail_url }}"
                                                 style="width:40px;height:40px;
                                                        border-radius:8px;object-fit:cover;">
                                        @else
                                            <div style="width:40px;height:40px;border-radius:8px;
                                                        background:linear-gradient(135deg,#0f1b2d,#1a3050);
                                                        display:flex;align-items:center;
                                                        justify-content:center;
                                                        color:#7DFF00;font-size:18px;">
                                                <i class="las la-chart-bar"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <strong>{{ $a->name }}</strong><br>
                                            <small class="text-muted">{{ $a->slug }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><code class="small">{{ $a->route_name ?: '—' }}</code></td>
                                <td>
                                    @php $b = $a->plan_badge; @endphp
                                    <span class="tier-badge tier-{{ $a->plan_tier }}">
                                        {{ $b['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge--info" style="font-size:11px;">
                                        {{ strtoupper($a->data_source) }}
                                    </span>
                                </td>
                                <td>
                                    @if($a->is_active)
                                        <span class="badge badge--success">Active</span>
                                    @else
                                        <span class="badge badge--danger">Inactive</span>
                                    @endif
                                    @if($a->is_featured)
                                        <span class="badge badge--warning">⭐</span>
                                    @endif
                                </td>
                                <td>{{ $a->sort_order }}</td>
                                <td>
                                    <div class="button--group">
                                        <a href="{{ route('admin.cp.analyses.toggle', $a->id) }}"
                                           class="btn btn-sm {{ $a->is_active ? 'btn--warning' : 'btn--success' }}"
                                           title="{{ $a->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="las {{ $a->is_active ? 'la-toggle-off' : 'la-toggle-on' }}"></i>
                                        </a>
                                        <button class="btn btn-sm btn--info edit-analysis-btn"
                                                data-id="{{ $a->id }}"
                                                data-name="{{ $a->name }}"
                                                data-route="{{ $a->route_name }}"
                                                data-short="{{ $a->short_description }}"
                                                data-tier="{{ $a->plan_tier }}"
                                                data-source="{{ $a->data_source }}"
                                                data-active="{{ $a->is_active ? 1 : 0 }}"
                                                data-featured="{{ $a->is_featured ? 1 : 0 }}"
                                                data-sort="{{ $a->sort_order }}"
                                                data-tags="{{ implode(', ', $a->tags ?? []) }}"
                                                title="Edit">
                                            <i class="las la-pen"></i>
                                        </button>
                                        <button class="btn btn-sm btn--danger delete-analysis-btn"
                                                data-id="{{ $a->id }}"
                                                data-name="{{ $a->name }}"
                                                title="Delete">
                                            <i class="las la-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="las la-brain" style="font-size:3rem;color:#ccc;"></i>
                                    <h5 class="text-muted mt-2">No analyses yet</h5>
                                    <p class="text-muted small">Click "Add Analysis" to create one</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($analyses->hasPages())
            <div class="card-footer">{{ paginateLinks($analyses) }}</div>
            @endif
        </div>
    </div>
</div>

{{-- ════ ADD MODAL ════ --}}
<div class="modal fade" id="addAnalysisModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form action="{{ route('admin.cp.analyses.store') }}"
                  method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Analysis Tool</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Row 1 --}}
                        <div class="col-md-4">
                            <label class="form-label required">Name</label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="e.g. Pivot Analysis" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Route Name</label>
                            <input type="text" name="route_name" class="form-control"
                                   placeholder="pivot-analysis.index">
                            <small class="text-muted">Laravel named route for this tool</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Thumbnail</label>
                            <input type="file" name="thumbnail" class="form-control"
                                   accept="image/*">
                        </div>
                        {{-- Row 2 --}}
                        <div class="col-md-3">
                            <label class="form-label required">Plan Tier</label>
                            <select name="plan_tier" class="form-control" required>
                                <option value="free">Free</option>
                                <option value="pro">Pro</option>
                                <option value="pro_plus">Pro Plus</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Data Source</label>
                            <select name="data_source" class="form-control" required>
                                <option value="option">Option</option>
                                <option value="fut">FUT</option>
                                <option value="stock">Stock EQ</option>
                                <option value="mixed">Mixed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order"
                                   class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Active</label>
                            <select name="is_active" class="form-control">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Featured</label>
                            <select name="is_featured" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        {{-- Descriptions --}}
                        <div class="col-12">
                            <label class="form-label">Short Description</label>
                            <input type="text" name="short_description" class="form-control"
                                   placeholder="One-line description for listing page">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Full Description</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="Detailed description of what this analysis does..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">
                                Tags <small class="text-muted">(comma separated)</small>
                            </label>
                            <input type="text" name="tags" class="form-control"
                                   placeholder="Options, OI, Intraday">
                        </div>
                        {{-- FAQs --}}
                        <div class="col-12">
                            <label class="form-label">
                                FAQs
                                <button type="button" class="btn btn--secondary btn--sm ms-2"
                                        onclick="addFaqRow('addFaqContainer')">
                                    <i class="las la-plus"></i> Add
                                </button>
                            </label>
                            <div id="addFaqContainer">
                                @include('admin.cp.analyses.partials.faq-row')
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-plus-circle"></i> Create Analysis
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ════ EDIT MODAL ════ --}}
<div class="modal fade" id="editAnalysisModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="editAnalysisForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Analysis Tool</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Name</label>
                            <input type="text" name="name" id="e_name"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Route Name</label>
                            <input type="text" name="route_name" id="e_route"
                                   class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Replace Thumbnail</label>
                            <input type="file" name="thumbnail" class="form-control"
                                   accept="image/*">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Plan Tier</label>
                            <select name="plan_tier" id="e_tier" class="form-control">
                                <option value="free">Free</option>
                                <option value="pro">Pro</option>
                                <option value="pro_plus">Pro Plus</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Source</label>
                            <select name="data_source" id="e_source" class="form-control">
                                <option value="option">Option</option>
                                <option value="fut">FUT</option>
                                <option value="stock">Stock EQ</option>
                                <option value="mixed">Mixed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" id="e_sort"
                                   class="form-control" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Active</label>
                            <select name="is_active" id="e_active" class="form-control">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Featured</label>
                            <select name="is_featured" id="e_featured" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short Description</label>
                            <input type="text" name="short_description" id="e_short"
                                   class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Full Description</label>
                            <textarea name="description" id="e_desc"
                                      class="form-control" rows="4"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tags</label>
                            <input type="text" name="tags" id="e_tags"
                                   class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">
                                FAQs
                                <button type="button" class="btn btn--secondary btn--sm ms-2"
                                        onclick="addFaqRow('editFaqContainer')">
                                    <i class="las la-plus"></i> Add
                                </button>
                            </label>
                            <div id="editFaqContainer"></div>
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

{{-- ════ DELETE MODAL ════ --}}
<div class="modal fade" id="deleteAnalysisModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white">Confirm Delete</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <i class="las la-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Delete analysis <strong id="del_name"></strong>?</p>
                <p class="text--danger"><strong>This cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                <form id="deleteAnalysisForm" method="POST" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn--danger btn-sm">
                        <i class="las la-trash-alt"></i> Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
$(document).on('click', '.edit-analysis-btn', function () {
    const btn = $(this);
    const id  = btn.data('id');
    const url = "{{ route('admin.cp.analyses.update', ':id') }}".replace(':id', id);

    $('#editAnalysisForm').attr('action', url);
    $('#e_name').val(btn.data('name'));
    $('#e_route').val(btn.data('route'));
    $('#e_tier').val(btn.data('tier'));
    $('#e_source').val(btn.data('source'));
    $('#e_sort').val(btn.data('sort'));
    $('#e_active').val(String(btn.data('active')));
    $('#e_featured').val(String(btn.data('featured')));
    $('#e_short').val(btn.data('short'));
    $('#e_tags').val(btn.data('tags'));

    // Load description + FAQs via AJAX
    fetch('{{ url("admin/cp/analyses") }}/' + id + '/data')
        .then(r => r.json())
        .then(d => {
            $('#e_desc').val(d.description || '');
            const container = document.getElementById('editFaqContainer');
            container.innerHTML = '';
            (d.faqs || []).forEach(f => addFaqRowData('editFaqContainer', f.question, f.answer));
        });

    $('#editAnalysisModal').modal('show');
});

$(document).on('click', '.delete-analysis-btn', function () {
    const id   = $(this).data('id');
    const name = $(this).data('name');
    const url  = "{{ route('admin.cp.analyses.destroy', ':id') }}".replace(':id', id);
    $('#del_name').text(name);
    $('#deleteAnalysisForm').attr('action', url);
    $('#deleteAnalysisModal').modal('show');
});

function addFaqRow(containerId) { addFaqRowData(containerId, '', ''); }

function addFaqRowData(containerId, q, a) {
    document.getElementById(containerId).insertAdjacentHTML('beforeend', `
        <div class="faq-row row g-2 mb-2 align-items-start">
            <div class="col-5">
                <input type="text" name="faq_question[]" class="form-control form-control-sm"
                       placeholder="Question" value="${esc(q)}">
            </div>
            <div class="col-6">
                <textarea name="faq_answer[]" class="form-control form-control-sm"
                          rows="2" placeholder="Answer">${esc(a)}</textarea>
            </div>
            <div class="col-1">
                <button type="button" class="btn btn--danger btn--sm" style="padding:4px 8px;"
                        onclick="this.closest('.faq-row').remove()">
                    <i class="las la-trash"></i>
                </button>
            </div>
        </div>`);
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
@endpush