@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">

        {{-- ── Page Header ────────────────────────────────────────────────── --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">@lang('Broker Timeframe Management')</h5>
                <p class="text-muted mb-0 small">
                    Assign brokers to data-collection timeframes and manage symbols per timeframe
                </p>
            </div>
        </div>

        {{-- ── Quick Summary Cards ─────────────────────────────────────────── --}}
        <div class="row g-3 mb-4">
            @php
                $timeframeList = \App\Models\BrokerTimeframeAssignment::TIMEFRAMES;
                $activeAssignments = \App\Models\BrokerTimeframeAssignment::active()->count();
                $totalSymbols      = \App\Models\TimeframeSymbol::active()->count();
                $coveredTimeframes = \App\Models\BrokerTimeframeAssignment::active()->distinct('timeframe')->count('timeframe');
            @endphp
            <div class="col-md-3">
                <div class="widget-two style--two box--shadow2 b-radius--10 bg--primary">
                    <div class="widget-two__icon b-radius--10"><i class="las la-link"></i></div>
                    <div class="widget-two__content">
                        <h2 class="text-white">{{ $assignments->total() }}</h2>
                        <p class="text-white">@lang('Total Assignments')</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget-two style--two box--shadow2 b-radius--10 bg--success">
                    <div class="widget-two__icon b-radius--10"><i class="las la-check-circle"></i></div>
                    <div class="widget-two__content">
                        <h2 class="text-white">{{ $activeAssignments }}</h2>
                        <p class="text-white">@lang('Active Assignments')</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget-two style--two box--shadow2 b-radius--10 bg--info">
                    <div class="widget-two__icon b-radius--10"><i class="las la-clock"></i></div>
                    <div class="widget-two__content">
                        <h2 class="text-white">{{ $coveredTimeframes }} / {{ count($timeframeList) }}</h2>
                        <p class="text-white">@lang('Timeframes Covered')</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget-two style--two box--shadow2 b-radius--10 bg--warning">
                    <div class="widget-two__icon b-radius--10"><i class="las la-chart-bar"></i></div>
                    <div class="widget-two__content">
                        <h2 class="text-white">{{ $totalSymbols }}</h2>
                        <p class="text-white">@lang('Active Symbols')</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             SECTION 1 — BROKER ASSIGNMENTS
        ═══════════════════════════════════════════════════════════════════ --}}
        <div class="card b-radius--10 mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="las la-link text--primary me-1"></i> @lang('Broker → Timeframe Assignments')</h6>
                <button class="btn btn--primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                    <i class="las la-plus-circle"></i> @lang('New Assignment')
                </button>
            </div>

            {{-- Timeframe coverage overview ─────────────────────────────── --}}
            <div class="card-body border-bottom pb-3">
                <p class="text-muted small mb-2 fw-bold">@lang('Timeframe Coverage at a Glance:')</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($timeframes as $tfKey => $tfLabel)
                        @php
                            $active = $assignments->getCollection()
                                ->where('timeframe', $tfKey)
                                ->where('is_active', true)
                                ->first();
                        @endphp
                        <span class="badge {{ $active ? 'badge--success' : 'badge--danger' }} px-3 py-2"
                              title="{{ $active ? 'Assigned: '.$active->broker->client_name : 'No broker assigned' }}">
                            {{ $tfLabel }}
                            @if($active)
                                <i class="las la-check-circle ms-1"></i>
                            @else
                                <i class="las la-times-circle ms-1"></i>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('Timeframe')</th>
                                <th>@lang('Assigned Broker')</th>
                                <th>@lang('Token Status')</th>
                                <th>@lang('Label')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Created')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignments as $a)
                                <tr class="{{ $a->is_active ? '' : 'opacity-50' }}">
                                    <td>{{ $loop->iteration + ($assignments->currentPage() - 1) * $assignments->perPage() }}</td>

                                    <td>
                                        <span class="badge badge--primary px-2 py-1">
                                            {{ $a->timeframe_label }}
                                        </span>
                                    </td>

                                    <td>
                                        @if($a->broker)
                                            <strong>{{ $a->broker->client_name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $a->broker->account_user_name }}</small>
                                        @else
                                            <span class="text--danger">Broker deleted</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if($a->broker)
                                            @if($a->broker->hasValidToken())
                                                <span class="badge badge--success">
                                                    <i class="las la-check-circle"></i> Valid
                                                </span>
                                                <br>
                                                <small class="text-muted">{{ $a->broker->token_expiry_remaining }}</small>
                                            @elseif($a->broker->access_token)
                                                <span class="badge badge--warning">Expired</span>
                                            @else
                                                <span class="badge badge--danger">No Token</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td>{{ $a->label ?: '—' }}</td>

                                    <td>
                                        @if($a->is_active)
                                            <span class="badge badge--success">Active</span>
                                        @else
                                            <span class="badge badge--danger">Inactive</span>
                                        @endif
                                    </td>

                                    <td>
                                        <small>{{ $a->created_at->format('d M Y') }}</small>
                                    </td>

                                    <td>
                                        <div class="button--group">
                                            <a href="{{ route('admin.broker-timeframe.assignment.toggle', $a->id) }}"
                                               class="btn btn-sm {{ $a->is_active ? 'btn--warning' : 'btn--success' }}"
                                               title="{{ $a->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="las {{ $a->is_active ? 'la-toggle-off' : 'la-toggle-on' }}"></i>
                                            </a>
                                            <button class="btn btn-sm btn--danger delete-assignment-btn"
                                                data-id="{{ $a->id }}" title="Delete">
                                                <i class="las la-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="las la-link" style="font-size:3rem;color:#ccc;"></i>
                                        <h5 class="text-muted mt-2">@lang('No Assignments Yet')</h5>
                                        <p class="text-muted small">@lang('Click "New Assignment" to assign a broker to a timeframe')</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($assignments->hasPages())
                <div class="card-footer">{{ paginateLinks($assignments) }}</div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             SECTION 2 — SYMBOL MANAGEMENT (tabs per timeframe)
        ═══════════════════════════════════════════════════════════════════ --}}
        <div class="card b-radius--10">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="las la-chart-bar text--primary me-1"></i>
                    @lang('Symbols per Timeframe')
                </h6>
                <div class="d-flex gap-2">
                    <button class="btn btn--primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSymbolModal">
                        <i class="las la-plus-circle"></i> @lang('Add Symbol')
                    </button>
                    <button class="btn btn--info btn-sm" data-bs-toggle="modal" data-bs-target="#bulkSymbolModal">
                        <i class="las la-layer-group"></i> @lang('Bulk Add')
                    </button>
                </div>
            </div>

            <div class="card-body">
                {{-- Timeframe tabs ───────────────────────────────────────── --}}
                <ul class="nav nav-tabs mb-3" id="tfTabs" role="tablist">
                    @foreach($timeframes as $tfKey => $tfLabel)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }}"
                                    id="tab-{{ $tfKey }}"
                                    data-bs-toggle="tab"
                                    data-bs-target="#pane-{{ $tfKey }}"
                                    type="button" role="tab">
                                {{ $tfLabel }}
                                @php $count = isset($symbolsByTimeframe[$tfKey]) ? $symbolsByTimeframe[$tfKey]->count() : 0; @endphp
                                @if($count > 0)
                                    <span class="badge badge--primary ms-1">{{ $count }}</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content" id="tfTabContent">
                    @foreach($timeframes as $tfKey => $tfLabel)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                             id="pane-{{ $tfKey }}" role="tabpanel">

                            @php $tfSymbols = $symbolsByTimeframe[$tfKey] ?? collect(); @endphp

                            @if($tfSymbols->isEmpty())
                                <div class="text-center py-4">
                                    <i class="las la-inbox" style="font-size:2.5rem;color:#ccc;"></i>
                                    <p class="text-muted mt-2">No symbols added for <strong>{{ $tfLabel }}</strong> yet.</p>
                                    <button class="btn btn--primary btn-sm open-add-symbol-modal"
                                            data-tf="{{ $tfKey }}">
                                        <i class="las la-plus-circle"></i> Add Symbol
                                    </button>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table--light style--two">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>@lang('Symbol')</th>
                                                <th>@lang('Exchange')</th>
                                                <th>@lang('Status')</th>
                                                <th>@lang('Action')</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tfSymbols as $sym)
                                                <tr class="{{ $sym->is_active ? '' : 'opacity-50' }}">
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td><strong>{{ $sym->symbol }}</strong></td>
                                                    <td>
                                                        <span class="badge badge--info">{{ $sym->exchange }}</span>
                                                    </td>
                                                    <td>
                                                        @if($sym->is_active)
                                                            <span class="badge badge--success">Active</span>
                                                        @else
                                                            <span class="badge badge--danger">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="button--group">
                                                            <a href="{{ route('admin.broker-timeframe.symbol.toggle', $sym->id) }}"
                                                               class="btn btn-sm {{ $sym->is_active ? 'btn--warning' : 'btn--success' }}"
                                                               title="{{ $sym->is_active ? 'Deactivate' : 'Activate' }}">
                                                                <i class="las {{ $sym->is_active ? 'la-toggle-off' : 'la-toggle-on' }}"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn--danger delete-symbol-btn"
                                                                data-id="{{ $sym->id }}"
                                                                data-label="{{ $sym->symbol }} ({{ $tfLabel }})"
                                                                title="Remove">
                                                                <i class="las la-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════════════════════ --}}

{{-- Add Assignment ─────────────────────────────────────────────────────── --}}
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.broker-timeframe.assignment.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">@lang('New Broker Assignment')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">

                    <div class="alert alert-info p-3 b-radius--5 mb-3 small">
                        <i class="las la-info-circle me-1"></i>
                        Setting an assignment as <strong>Active</strong> will automatically
                        deactivate any existing active assignment for the same timeframe.
                        Only <strong>one active broker per timeframe</strong> is allowed.
                    </div>

                    <div class="form-group">
                        <label>@lang('Select Broker') <sup class="text--danger">*</sup></label>
                        <select name="admin_broker_api_id" class="form-control select2" required>
                            <option value="">— Choose a Broker —</option>
                            @foreach($brokers as $broker)
                                <option value="{{ $broker->id }}">
                                    {{ $broker->client_name }}
                                    ({{ $broker->account_user_name }})
                                    @if(!$broker->hasValidToken()) ⚠️ Token invalid @endif
                                </option>
                            @endforeach
                        </select>
                        @if($brokers->isEmpty())
                            <small class="text--danger">
                                No active brokers found.
                                <a href="{{ route('admin.zerodha-broker.index') }}">Add a broker first →</a>
                            </small>
                        @endif
                    </div>

                    <div class="form-group">
                        <label>@lang('Timeframe') <sup class="text--danger">*</sup></label>
                        <select name="timeframe" class="form-control" required>
                            <option value="">— Choose Timeframe —</option>
                            @foreach($timeframes as $tfKey => $tfLabel)
                                <option value="{{ $tfKey }}">{{ $tfLabel }} ({{ $tfKey }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Label')</label>
                        <input type="text" name="label" class="form-control"
                               placeholder="e.g. Primary 1HR collector">
                        <small class="text-muted">Auto-generated if left blank</small>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   id="isActiveCheck" value="1" checked>
                            <label class="form-check-label" for="isActiveCheck">
                                Set as Active immediately
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>@lang('Notes')</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-save"></i> @lang('Save Assignment')
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Single Symbol ──────────────────────────────────────────────────── --}}
<div class="modal fade" id="addSymbolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.broker-timeframe.symbol.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add Symbol to Timeframe')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>@lang('Symbol') <sup class="text--danger">*</sup></label>
                        <input type="text" name="symbol" class="form-control"
                               placeholder="e.g. NIFTY" style="text-transform:uppercase;" required>
                        <small class="text-muted">Uppercase letters and numbers only</small>
                    </div>
                    <div class="form-group">
                        <label>@lang('Timeframe') <sup class="text--danger">*</sup></label>
                        <select name="timeframe" id="addSymbolTimeframe" class="form-control" required>
                            <option value="">— Choose Timeframe —</option>
                            @foreach($timeframes as $tfKey => $tfLabel)
                                <option value="{{ $tfKey }}">{{ $tfLabel }} ({{ $tfKey }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>@lang('Exchange') <sup class="text--danger">*</sup></label>
                        <select name="exchange" class="form-control" required>
                            <option value="NFO" selected>NFO (F&O)</option>
                            <option value="NSE">NSE (Equity)</option>
                            <option value="BFO">BFO (BSE F&O)</option>
                            <option value="BSE">BSE</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-plus-circle"></i> @lang('Add Symbol')
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Add Symbols ───────────────────────────────────────────────────── --}}
<div class="modal fade" id="bulkSymbolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.broker-timeframe.symbol.bulk') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Bulk Add Symbols')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>@lang('Symbols') <sup class="text--danger">*</sup></label>
                        <textarea name="symbols" class="form-control" rows="4" required
                            placeholder="NIFTY, BANKNIFTY, SENSEX, FINNIFTY"
                            style="text-transform:uppercase;"></textarea>
                        <small class="text-muted">Comma-separated list of symbols</small>
                    </div>
                    <div class="form-group">
                        <label>@lang('Timeframe') <sup class="text--danger">*</sup></label>
                        <select name="timeframe" class="form-control" required>
                            <option value="">— Choose Timeframe —</option>
                            @foreach($timeframes as $tfKey => $tfLabel)
                                <option value="{{ $tfKey }}">{{ $tfLabel }} ({{ $tfKey }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>@lang('Exchange') <sup class="text--danger">*</sup></label>
                        <select name="exchange" class="form-control" required>
                            <option value="NFO" selected>NFO</option>
                            <option value="NSE">NSE</option>
                            <option value="BFO">BFO</option>
                            <option value="BSE">BSE</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <button type="submit" class="btn btn--primary btn-sm">
                        <i class="las la-layer-group"></i> @lang('Bulk Add')
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Assignment Confirm ──────────────────────────────────────────── --}}
<div class="modal fade" id="deleteAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white">@lang('Delete Assignment')</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <p>@lang('Are you sure you want to delete this assignment?')</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                <form id="deleteAssignmentForm" method="POST" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn--danger btn-sm">
                        <i class="las la-trash-alt"></i> @lang('Delete')
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Delete Symbol Confirm ───────────────────────────────────────────────── --}}
<div class="modal fade" id="deleteSymbolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white">@lang('Remove Symbol')</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Remove <strong id="deleteSymbolLabel"></strong> from this timeframe?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                <form id="deleteSymbolForm" method="POST" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn--danger btn-sm">
                        <i class="las la-trash-alt"></i> @lang('Remove')
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
    .badge--primary { background: #3b82f6; color: #fff; }
    .badge--info    { background: #06b6d4; color: #fff; }

    .button--group .btn { margin: 1px; }

    .alert-info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }

    .nav-tabs .nav-link { font-size: 0.875rem; }
    .opacity-50 { opacity: 0.5; }
</style>
@endpush

@push('script')
<script>
$(document).ready(function () {

    // Auto-uppercase symbol input
    $('[name="symbol"], [name="symbols"]').on('input', function () {
        $(this).val($(this).val().toUpperCase());
    });

    // Pre-select timeframe when clicking "Add Symbol" from empty-tab button
    $(document).on('click', '.open-add-symbol-modal', function () {
        const tf = $(this).data('tf');
        $('#addSymbolTimeframe').val(tf);
        $('#addSymbolModal').modal('show');
    });

    // Delete assignment
    $(document).on('click', '.delete-assignment-btn', function () {
        const id  = $(this).data('id');
        const url = "{{ route('admin.broker-timeframe.assignment.destroy', ':id') }}".replace(':id', id);
        $('#deleteAssignmentForm').attr('action', url);
        $('#deleteAssignmentModal').modal('show');
    });

    // Delete symbol
    $(document).on('click', '.delete-symbol-btn', function () {
        const id    = $(this).data('id');
        const label = $(this).data('label');
        const url   = "{{ route('admin.broker-timeframe.symbol.destroy', ':id') }}".replace(':id', id);
        $('#deleteSymbolLabel').text(label);
        $('#deleteSymbolForm').attr('action', url);
        $('#deleteSymbolModal').modal('show');
    });

    // Preserve active tab across page reload
    const activeTab = localStorage.getItem('btm_active_tab');
    if (activeTab) {
        const tab = document.getElementById('tab-' + activeTab);
        if (tab) new bootstrap.Tab(tab).show();
    }
    document.querySelectorAll('#tfTabs .nav-link').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (e) {
            const tf = e.target.id.replace('tab-', '');
            localStorage.setItem('btm_active_tab', tf);
        });
    });

});
</script>
@endpush