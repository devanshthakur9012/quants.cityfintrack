{{-- FILE: resources/views/admin/analysis-config/index.blade.php — REPLACE EXISTING --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">Analysis Config</h5>
                <p class="text-muted mb-0 small">
                    Configure broker + symbols for data collection
                    <span class="badge badge--info ms-2" style="font-size:11px;">
                        <i class="las la-clock"></i> 15min fixed
                    </span>
                </p>
            </div>
            <button type="button" class="btn btn--primary btn-sm"
                    data-bs-toggle="modal" data-bs-target="#addConfigModal">
                <i class="las la-plus-circle"></i> Add Config
            </button>
        </div>

        <div class="card b-radius--10">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Broker</th>
                                <th>Timeframe</th>
                                <th>Symbols</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($configs as $config)
                            <tr>
                                <td>{{ $loop->iteration + ($configs->currentPage()-1)*$configs->perPage() }}</td>
                                <td>
                                    <strong>{{ $config->broker->account_user_name ?? '—' }}</strong>
                                </td>
                                <td>
                                    {{-- Always 15min — shown as badge, no dropdown --}}
                                    <span class="badge badge--info">15MIN</span>
                                </td>
                                <td>
                                    <span class="badge badge--primary">
                                        {{ $config->symbols->count() }} symbols
                                    </span><br>
                                    <small class="text-muted">
                                        {{ $config->symbols->take(3)->pluck('symbol')->implode(', ') }}
                                        @if($config->symbols->count() > 3)
                                            +{{ $config->symbols->count() - 3 }} more
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    @if($config->is_active)
                                        <span class="badge badge--success">Active</span>
                                    @else
                                        <span class="badge badge--danger">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $config->created_at->format('d M Y') }}</td>
                                <td>
                                    <div class="button--group">
                                        <a href="{{ route('admin.analysis-config.toggle-status', $config->id) }}"
                                           class="btn btn-sm {{ $config->is_active ? 'btn--warning' : 'btn--success' }}"
                                           title="{{ $config->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="las {{ $config->is_active ? 'la-toggle-off' : 'la-toggle-on' }}"></i>
                                        </a>
                                        <button class="btn btn-sm btn--info edit-config-btn"
                                                data-id="{{ $config->id }}"
                                                data-broker="{{ $config->broker_api_id }}"
                                                data-symbols="{{ $config->symbols->pluck('id')->toJson() }}"
                                                title="Edit">
                                            <i class="las la-pencil-alt"></i>
                                        </button>
                                        <button class="btn btn-sm btn--danger delete-config-btn"
                                                data-id="{{ $config->id }}"
                                                data-label="{{ $config->broker->account_user_name ?? '' }}"
                                                title="Delete">
                                            <i class="las la-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="las la-inbox" style="font-size:3rem;color:#ccc;"></i>
                                    <h5 class="text-muted mt-2">No config found</h5>
                                    <p class="text-muted small">
                                        Click "Add Config" to get started
                                    </p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($configs->hasPages())
            <div class="card-footer">{{ paginateLinks($configs) }}</div>
            @endif
        </div>
    </div>
</div>

{{-- ════ ADD CONFIG MODAL ════ --}}
<div class="modal fade" id="addConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('admin.analysis-config.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Analysis Config</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Broker</label>
                            <select name="broker_api_id" class="form-control" required>
                                <option value="">-- Select Broker --</option>
                                @foreach($brokers as $b)
                                <option value="{{ $b->id }}">{{ $b->account_user_name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only brokers with valid tokens shown</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="alert alert-info p-2 mb-0 w-100" style="font-size:13px;">
                                <i class="las la-info-circle"></i>
                                Timeframe is <strong>15min fixed</strong>.
                                30min / 1hr analysis will be derived from this data later.
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label required">
                                Select Symbols
                                <small class="text-muted">(min 1, max 50)</small>
                            </label>
                            <input type="text" id="addSymSearch"
                                   class="form-control form-control-sm mb-2"
                                   placeholder="Search symbols...">
                            <div class="sym-list border rounded p-2"
                                 style="max-height:300px;overflow-y:auto;">
                                @foreach($symbols as $sym)
                                <div class="form-check sym-item">
                                    <input class="form-check-input add-sym-check"
                                           type="checkbox" name="symbol_ids[]"
                                           value="{{ $sym->id }}"
                                           id="a_sym{{ $sym->id }}">
                                    <label class="form-check-label"
                                           for="a_sym{{ $sym->id }}">
                                        <strong>{{ $sym->symbol }}</strong>
                                        <small class="text-muted">{{ $sym->underlying }}</small>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                            <small class="text-muted">
                                Selected: <span id="addSymCount">0</span> / 50
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-plus-circle"></i> Create Config
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ════ EDIT CONFIG MODAL ════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editConfigForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Analysis Config</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Broker</label>
                            <select name="broker_api_id" id="ec_broker"
                                    class="form-control" required>
                                <option value="">-- Select Broker --</option>
                                @foreach($brokers as $b)
                                <option value="{{ $b->id }}">{{ $b->account_user_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="alert alert-info p-2 mb-0 w-100" style="font-size:13px;">
                                <i class="las la-clock"></i>
                                Timeframe: <strong>15min (fixed)</strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label required">
                                Select Symbols
                                <small class="text-muted">(max 50)</small>
                            </label>
                            <input type="text" id="editSymSearch"
                                   class="form-control form-control-sm mb-2"
                                   placeholder="Search symbols...">
                            <div class="sym-list border rounded p-2"
                                 style="max-height:300px;overflow-y:auto;">
                                @foreach($symbols as $sym)
                                <div class="form-check sym-item">
                                    <input class="form-check-input edit-sym-check"
                                           type="checkbox" name="symbol_ids[]"
                                           value="{{ $sym->id }}"
                                           id="e_sym{{ $sym->id }}">
                                    <label class="form-check-label"
                                           for="e_sym{{ $sym->id }}">
                                        <strong>{{ $sym->symbol }}</strong>
                                        <small class="text-muted">{{ $sym->underlying }}</small>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                            <small class="text-muted">
                                Selected: <span id="editSymCount">0</span> / 50
                            </small>
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
<div class="modal fade" id="deleteConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white">Confirm Delete</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <i class="las la-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Delete config for <strong id="del_config_label"></strong>?</p>
                <p class="text--danger">
                    <strong>Warning:</strong> This will stop data collection.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                <form id="deleteConfigForm" method="POST" style="display:inline;">
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

@push('style')
<style>
.sym-list .form-check { padding:5px 8px; border-bottom:1px solid #f1f5f9; }
.sym-list .form-check:last-child { border-bottom:none; }
.sym-list .form-check:hover { background:#f8fafc; }
.sym-list .form-check-label { cursor:pointer; width:100%; }
</style>
@endpush

@push('script')
<script>
$(document).ready(function () {
    // Search — Add modal
    $('#addSymSearch').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#addConfigModal .sym-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });
    // Search — Edit modal
    $('#editSymSearch').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#editConfigModal .sym-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });
    // Count — Add
    $(document).on('change', '.add-sym-check', function () {
        const c = $('.add-sym-check:checked').length;
        if (c > 50) { $(this).prop('checked', false); return; }
        $('#addSymCount').text(c);
    });
    // Count — Edit
    $(document).on('change', '.edit-sym-check', function () {
        const c = $('.edit-sym-check:checked').length;
        if (c > 50) { $(this).prop('checked', false); return; }
        $('#editSymCount').text(c);
    });
    // Edit open
    $(document).on('click', '.edit-config-btn', function () {
        const btn     = $(this);
        const id      = btn.data('id');
        const broker  = btn.data('broker');
        const symIds  = btn.data('symbols');
        const url     = "{{ route('admin.analysis-config.update', ':id') }}".replace(':id', id);

        $('#editConfigForm').attr('action', url);
        $('#ec_broker').val(broker);
        $('.edit-sym-check').prop('checked', false);
        if (Array.isArray(symIds)) {
            symIds.forEach(sid => $('#e_sym' + sid).prop('checked', true));
        }
        $('#editSymCount').text($('.edit-sym-check:checked').length);
        $('#editConfigModal').modal('show');
    });
    // Delete
    $(document).on('click', '.delete-config-btn', function () {
        const id    = $(this).data('id');
        const label = $(this).data('label');
        const url   = "{{ route('admin.analysis-config.destroy', ':id') }}".replace(':id', id);
        $('#del_config_label').text(label + ' / 15MIN');
        $('#deleteConfigForm').attr('action', url);
        $('#deleteConfigModal').modal('show');
    });
});
</script>
@endpush