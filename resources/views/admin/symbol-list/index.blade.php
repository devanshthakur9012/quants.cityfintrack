@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">

        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">@lang('Symbol Management')</h5>
                <p class="text-muted mb-0 small">Manage trading symbols available for analysis</p>
            </div>
            <button type="button" class="btn btn--primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSymbolModal">
                <i class="las la-plus-circle"></i> @lang('Add Symbol')
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
                                <th>@lang('Underlying')</th>
                                <th>@lang('Symbol')</th>
                                <th>@lang('Created At')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($symbols as $symbol)
                                <tr>
                                    <td>{{ $loop->iteration + ($symbols->currentPage() - 1) * $symbols->perPage() }}</td>
                                    <td><strong>{{ $symbol->underlying }}</strong></td>
                                    <td><code>{{ $symbol->symbol }}</code></td>
                                    <td>{{ $symbol->created_at->format('d M Y') }}</td>
                                    <td>
                                        <div class="button--group">
                                            <button class="btn btn-sm btn--info edit-symbol-btn"
                                                data-id="{{ $symbol->id }}"
                                                data-underlying="{{ $symbol->underlying }}"
                                                data-symbol="{{ $symbol->symbol }}"
                                                title="Edit">
                                                <i class="las la-pencil-alt"></i>
                                            </button>
                                            <button class="btn btn-sm btn--danger delete-symbol-btn"
                                                data-id="{{ $symbol->id }}"
                                                data-name="{{ $symbol->symbol }}"
                                                title="Delete">
                                                <i class="las la-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="las la-inbox" style="font-size:3rem; color:#ccc;"></i>
                                        <h5 class="text-muted mt-2">@lang('No Symbols Found')</h5>
                                        <p class="text-muted small">@lang('Click "Add Symbol" to get started')</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($symbols->hasPages())
                <div class="card-footer">
                    {{ paginateLinks($symbols) }}
                </div>
            @endif
        </div>

    </div>
</div>

{{-- ADD SYMBOL MODAL --}}
<div class="modal fade" id="addSymbolModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.symbol-list.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add Symbol')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>@lang('Underlying') <sup class="text--danger">*</sup></label>
                        <input type="text" name="underlying" class="form-control"
                            placeholder="e.g. NIFTY" required>
                        <small class="text-muted">Parent index or stock (e.g. NIFTY, BANKNIFTY)</small>
                    </div>
                    <div class="form-group">
                        <label>@lang('Symbol') <sup class="text--danger">*</sup></label>
                        <input type="text" name="symbol" class="form-control"
                            placeholder="e.g. NIFTY50" required>
                        <small class="text-muted">Unique symbol identifier</small>
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

{{-- EDIT SYMBOL MODAL --}}
<div class="modal fade" id="editSymbolModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editSymbolForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Edit Symbol')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>@lang('Underlying') <sup class="text--danger">*</sup></label>
                        <input type="text" name="underlying" id="edit_underlying" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>@lang('Symbol') <sup class="text--danger">*</sup></label>
                        <input type="text" name="symbol" id="edit_symbol" class="form-control" required>
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

{{-- DELETE CONFIRM MODAL --}}
<div class="modal fade" id="deleteSymbolModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white">@lang('Confirm Delete')</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <p>@lang('Are you sure you want to delete symbol') <strong id="deleteSymbolName"></strong>?</p>
                <p class="text--danger">
                    <strong>@lang('Warning: ')</strong>
                    @lang('This will permanently delete the symbol and all analysis configurations linked to it from the database. This action cannot be undone.')
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                <form id="deleteSymbolForm" method="POST" style="display:inline;">
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
    .button--group .btn { margin: 1px; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-size: 0.85rem; }
</style>
@endpush

@push('script')
<script>
$(document).ready(function () {

    // ── Edit Symbol ───────────────────────────────────────────────────────
    $(document).on('click', '.edit-symbol-btn', function () {
        const btn = $(this);
        const id  = btn.data('id');
        const url = "{{ route('admin.symbol-list.update', ':id') }}".replace(':id', id);

        $('#editSymbolForm').attr('action', url);
        $('#edit_underlying').val(btn.data('underlying'));
        $('#edit_symbol').val(btn.data('symbol'));

        $('#editSymbolModal').modal('show');
    });

    // ── Delete Symbol ─────────────────────────────────────────────────────
    $(document).on('click', '.delete-symbol-btn', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        const url  = "{{ route('admin.symbol-list.destroy', ':id') }}".replace(':id', id);

        $('#deleteSymbolName').text(name);
        $('#deleteSymbolForm').attr('action', url);
        $('#deleteSymbolModal').modal('show');
    });

});
</script>
@endpush