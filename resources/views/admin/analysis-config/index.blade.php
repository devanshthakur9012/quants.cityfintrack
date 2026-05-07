@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">

        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">@lang('Analysis Config')</h5>
                <p class="text-muted mb-0 small">Configure broker + timeframe + symbols for analysis</p>
            </div>
            <button type="button" class="btn btn--primary btn-sm" data-bs-toggle="modal" data-bs-target="#addConfigModal">
                <i class="las la-plus-circle"></i> @lang('Add Config')
            </button>
        </div>

        {{-- Table --}}
        <div class="card b-radius--10">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>@lang('SL')</th>
                                <th>@lang('Broker')</th>
                                <th>@lang('Time Frame')</th>
                                <th>@lang('Symbols')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Created')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($configs as $config)
                                <tr>
                                    <td>{{ $loop->iteration + ($configs->currentPage() - 1) * $configs->perPage() }}</td>

                                    <td><strong>{{ $config->broker->account_user_name ?? '—' }}</strong></td>

                                    <td>
                                        <span class="badge badge--info">{{ strtoupper($config->time_frame) }}</span>
                                    </td>

                                    <td>
                                        <span class="badge badge--primary">{{ $config->symbols->count() }} symbols</span>
                                        <br>
                                        <small class="text-muted">
                                            {{ $config->symbols->take(3)->pluck('symbol')->implode(', ') }}
                                            @if($config->symbols->count() > 3)
                                                <em>+{{ $config->symbols->count() - 3 }} more</em>
                                            @endif
                                        </small>
                                    </td>

                                    <td>
                                        @if ($config->is_active)
                                            <span class="badge badge--success">@lang('Active')</span>
                                        @else
                                            <span class="badge badge--danger">@lang('Inactive')</span>
                                        @endif
                                    </td>

                                    <td>{{ $config->created_at->format('d M Y') }}</td>

                                    <td>
                                        <div class="button--group">

                                            {{-- Toggle Status --}}
                                            <a href="{{ route('admin.analysis-config.toggle-status', $config->id) }}"
                                                class="btn btn-sm {{ $config->is_active ? 'btn--warning' : 'btn--success' }}"
                                                title="{{ $config->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="las {{ $config->is_active ? 'la-toggle-off' : 'la-toggle-on' }}"></i>
                                            </a>

                                            {{-- Edit --}}
                                            <button class="btn btn-sm btn--info edit-config-btn"
                                                data-id="{{ $config->id }}"
                                                data-broker_api_id="{{ $config->broker_api_id }}"
                                                data-time_frame="{{ $config->time_frame }}"
                                                data-symbol_ids="{{ $config->symbols->pluck('id')->toJson() }}"
                                                title="Edit">
                                                <i class="las la-pencil-alt"></i>
                                            </button>

                                            {{-- Delete --}}
                                            <button class="btn btn-sm btn--danger delete-config-btn"
                                                data-id="{{ $config->id }}"
                                                data-broker="{{ $config->broker->account_user_name ?? '' }}"
                                                data-timeframe="{{ $config->time_frame }}"
                                                title="Delete">
                                                <i class="las la-trash-alt"></i>
                                            </button>

                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="las la-inbox" style="font-size:3rem; color:#ccc;"></i>
                                        <h5 class="text-muted mt-2">@lang('No Configs Found')</h5>
                                        <p class="text-muted small">@lang('Click "Add Config" to get started')</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($configs->hasPages())
                <div class="card-footer">
                    {{ paginateLinks($configs) }}
                </div>
            @endif
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════
     ADD CONFIG MODAL
══════════════════════════════════════════ --}}
<div class="modal fade" id="addConfigModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.analysis-config.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add Analysis Config')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Broker') <sup class="text--danger">*</sup></label>
                                <select name="broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach ($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->account_user_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Only brokers with valid tokens shown</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Time Frame') <sup class="text--danger">*</sup></label>
                                <select name="time_frame" class="form-control" required>
                                    <option value="">-- Select Time Frame --</option>
                                    @foreach ($timeFrames as $tf)
                                        <option value="{{ $tf }}">{{ strtoupper($tf) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>
                                    @lang('Select Symbols')
                                    <sup class="text--danger">*</sup>
                                    <small class="text-muted">(min 1, max 40)</small>
                                </label>
                                <div class="symbol-search-wrap mb-2">
                                    <input type="text" id="addSymbolSearch" class="form-control form-control-sm"
                                        placeholder="Search symbols...">
                                </div>
                                <div class="symbol-checkbox-list border rounded p-2" style="max-height:260px; overflow-y:auto;">
                                    @foreach ($symbols as $sym)
                                        <div class="form-check symbol-item">
                                            <input class="form-check-input add-symbol-check" type="checkbox"
                                                name="symbol_ids[]"
                                                value="{{ $sym->id }}"
                                                id="add_sym_{{ $sym->id }}">
                                            <label class="form-check-label" for="add_sym_{{ $sym->id }}">
                                                <strong>{{ $sym->symbol }}</strong>
                                                <small class="text-muted">{{ $sym->underlying }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="text-muted">
                                    Selected: <span id="addSelectedCount">0</span> / 40
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-plus-circle"></i> @lang('Create Config')
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════
     EDIT CONFIG MODAL
══════════════════════════════════════════ --}}
<div class="modal fade" id="editConfigModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="editConfigForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Edit Analysis Config')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Broker') <sup class="text--danger">*</sup></label>
                                <select name="broker_api_id" id="edit_broker_api_id" class="form-control" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach ($brokers as $broker)
                                        <option value="{{ $broker->id }}">{{ $broker->account_user_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Time Frame') <sup class="text--danger">*</sup></label>
                                <select name="time_frame" id="edit_time_frame" class="form-control" required>
                                    <option value="">-- Select Time Frame --</option>
                                    @foreach ($timeFrames as $tf)
                                        <option value="{{ $tf }}">{{ strtoupper($tf) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>
                                    @lang('Select Symbols')
                                    <sup class="text--danger">*</sup>
                                    <small class="text-muted">(min 1, max 40)</small>
                                </label>
                                <div class="symbol-search-wrap mb-2">
                                    <input type="text" id="editSymbolSearch" class="form-control form-control-sm"
                                        placeholder="Search symbols...">
                                </div>
                                <div class="symbol-checkbox-list border rounded p-2" style="max-height:260px; overflow-y:auto;">
                                    @foreach ($symbols as $sym)
                                        <div class="form-check symbol-item">
                                            <input class="form-check-input edit-symbol-check" type="checkbox"
                                                name="symbol_ids[]"
                                                value="{{ $sym->id }}"
                                                id="edit_sym_{{ $sym->id }}">
                                            <label class="form-check-label" for="edit_sym_{{ $sym->id }}">
                                                <strong>{{ $sym->symbol }}</strong>
                                                <small class="text-muted">{{ $sym->underlying }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="text-muted">
                                    Selected: <span id="editSelectedCount">0</span> / 40
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-save"></i> @lang('Save Changes')
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════
     DELETE CONFIRM MODAL
══════════════════════════════════════════ --}}
<div class="modal fade" id="deleteConfigModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white">@lang('Confirm Delete')</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <p>@lang('Are you sure you want to delete the config for')
                    <strong id="deleteConfigLabel"></strong>?
                </p>
                <p class="text--danger">
                    <strong>@lang('Warning: ')</strong>
                    @lang('This will permanently delete this config and all symbol associations linked to it from the database. This action cannot be undone.')
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                <form id="deleteConfigForm" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn--danger btn-sm">
                        <i class="las la-trash-alt"></i> @lang('Yes, Delete Permanently')
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('style')
<style>
    .badge { font-size: 0.75rem; padding: 4px 10px; border-radius: 4px; display: inline-block; }
    .badge--success { background: #10b981; color: #fff; }
    .badge--warning { background: #f59e0b; color: #fff; }
    .badge--danger  { background: #ef4444; color: #fff; }
    .badge--info    { background: #3b82f6; color: #fff; }
    .badge--primary { background: #6366f1; color: #fff; }
    .button--group .btn { margin: 1px; }

    .symbol-checkbox-list .form-check { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
    .symbol-checkbox-list .form-check:last-child { border-bottom: none; }
    .symbol-checkbox-list .form-check:hover { background: #f8fafc; }
    .symbol-checkbox-list .form-check-label { cursor: pointer; width: 100%; }
</style>
@endpush

@push('script')
<script>
$(document).ready(function () {

    // ── Symbol search filter (Add modal) ──────────────────────────────────
    $('#addSymbolSearch').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#addConfigModal .symbol-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    // ── Symbol search filter (Edit modal) ────────────────────────────────
    $('#editSymbolSearch').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#editConfigModal .symbol-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    // ── Count selected symbols (Add) ──────────────────────────────────────
    $(document).on('change', '.add-symbol-check', function () {
        const count = $('.add-symbol-check:checked').length;
        $('#addSelectedCount').text(count);
        if (count > 40) {
            $(this).prop('checked', false);
            $('#addSelectedCount').text(40);
            alert('Maximum 40 symbols allowed.');
        }
    });

    // ── Count selected symbols (Edit) ─────────────────────────────────────
    $(document).on('change', '.edit-symbol-check', function () {
        const count = $('.edit-symbol-check:checked').length;
        $('#editSelectedCount').text(count);
        if (count > 40) {
            $(this).prop('checked', false);
            $('#editSelectedCount').text(40);
            alert('Maximum 40 symbols allowed.');
        }
    });

    // ── Edit Config ───────────────────────────────────────────────────────
    $(document).on('click', '.edit-config-btn', function () {
        const btn        = $(this);
        const id         = btn.data('id');
        const brokerId   = btn.data('broker_api_id');
        const timeFrame  = btn.data('time_frame');
        const symbolIds  = btn.data('symbol_ids'); // JSON array
        const url        = "{{ route('admin.analysis-config.update', ':id') }}".replace(':id', id);

        $('#editConfigForm').attr('action', url);
        $('#edit_broker_api_id').val(brokerId);
        $('#edit_time_frame').val(timeFrame);

        // Uncheck all first, then check the saved ones
        $('.edit-symbol-check').prop('checked', false);
        if (Array.isArray(symbolIds)) {
            symbolIds.forEach(function (sid) {
                $('#edit_sym_' + sid).prop('checked', true);
            });
        }
        $('#editSelectedCount').text($('.edit-symbol-check:checked').length);

        $('#editConfigModal').modal('show');
    });

    // ── Delete Config ─────────────────────────────────────────────────────
    $(document).on('click', '.delete-config-btn', function () {
        const id        = $(this).data('id');
        const broker    = $(this).data('broker');
        const timeframe = $(this).data('timeframe');
        const url       = "{{ route('admin.analysis-config.destroy', ':id') }}".replace(':id', id);

        $('#deleteConfigLabel').text(broker + ' / ' + timeframe.toUpperCase());
        $('#deleteConfigForm').attr('action', url);
        $('#deleteConfigModal').modal('show');
    });

});
</script>
@endpush