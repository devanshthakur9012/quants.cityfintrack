@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">

            {{-- ── Page Header ──────────────────────────────────────────── --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="mb-1">@lang('Zerodha Broker Management')</h5>
                    <p class="text-muted mb-0 small">Manage Zerodha broker accounts used for automated trading</p>
                </div>
                <button type="button" class="btn btn--primary btn-sm"
                    data-bs-toggle="modal" data-bs-target="#addBrokerModal">
                    <i class="las la-plus-circle"></i> @lang('Add Broker')
                </button>
            </div>

            {{-- ── Stats Row ────────────────────────────────────────────── --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="widget-two style--two box--shadow2 b-radius--10 bg--primary">
                        <div class="widget-two__icon b-radius--10">
                            <i class="las la-server"></i>
                        </div>
                        <div class="widget-two__content">
                            <h2 class="text-white">{{ $brokers->total() }}</h2>
                            <p class="text-white">@lang('Total Brokers')</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="widget-two style--two box--shadow2 b-radius--10 bg--success">
                        <div class="widget-two__icon b-radius--10">
                            <i class="las la-check-circle"></i>
                        </div>
                        <div class="widget-two__content">
                            <h2 class="text-white">{{ $brokers->getCollection()->filter(fn($b) => $b->hasValidToken())->count() }}</h2>
                            <p class="text-white">@lang('Valid Tokens')</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="widget-two style--two box--shadow2 b-radius--10 bg--warning">
                        <div class="widget-two__icon b-radius--10">
                            <i class="las la-exclamation-triangle"></i>
                        </div>
                        <div class="widget-two__content">
                            <h2 class="text-white">{{ $brokers->getCollection()->filter(fn($b) => !$b->hasValidToken())->count() }}</h2>
                            <p class="text-white">@lang('Expired / No Token')</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="widget-two style--two box--shadow2 b-radius--10 bg--info">
                        <div class="widget-two__icon b-radius--10">
                            <i class="las la-toggle-on"></i>
                        </div>
                        <div class="widget-two__content">
                            <h2 class="text-white">{{ $brokers->getCollection()->where('is_active', true)->count() }}</h2>
                            <p class="text-white">@lang('Active Brokers')</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Brokers Table ─────────────────────────────────────────── --}}
            <div class="card b-radius--10">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('SL')</th>
                                    <th>@lang('Account Username')</th>
                                    <th>@lang('API Key')</th>
                                    <th>@lang('Token Status')</th>
                                    <th>@lang('Last Login')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($brokers as $broker)
                                    <tr>
                                        <td>{{ $loop->iteration + ($brokers->currentPage() - 1) * $brokers->perPage() }}</td>

                                        <td>{{ $broker->account_user_name }}</td>

                                        <td>
                                            <code class="small">{{ $broker->api_key }}</code>
                                        </td>

                                        <td>
                                            @if ($broker->hasValidToken())
                                                <span class="badge badge--success">
                                                    <i class="las la-check-circle"></i> @lang('Valid')
                                                </span>
                                                <br>
                                                <small class="text-muted">{{ $broker->token_expiry_remaining }}</small>
                                            @elseif ($broker->access_token)
                                                <span class="badge badge--warning">
                                                    <i class="las la-exclamation-triangle"></i> @lang('Expired')
                                                </span>
                                            @else
                                                <span class="badge badge--danger">
                                                    <i class="las la-times-circle"></i> @lang('Not Set')
                                                </span>
                                            @endif
                                        </td>

                                        {{-- <td>
                                            @if ($broker->is_active)
                                                <span class="badge badge--success">@lang('Active')</span>
                                            @else
                                                <span class="badge badge--danger">@lang('Inactive')</span>
                                            @endif
                                        </td> --}}

                                        <td>
                                            @if ($broker->last_login_at)
                                                {{ $broker->last_login_at->diffForHumans() }}
                                            @else
                                                <span class="text-muted">@lang('Never')</span>
                                            @endif
                                        </td>

                                        <td>
                                            <div class="button--group">

                                                {{-- Manual Login → Zerodha OAuth --}}
                                                <a href="{{ route('admin.zerodha-broker.login', $broker->id) }}"
                                                    class="btn btn-sm btn--success"
                                                    title="Login to Zerodha (manual)"
                                                    target="_blank">
                                                    <i class="las la-sign-in-alt"></i> @lang('Login')
                                                </a>

                                                {{-- Update Token (paste callback URL) --}}
                                                <button class="btn btn-sm btn--warning update-token-btn"
                                                    data-id="{{ $broker->id }}"
                                                    title="Manually update access token">
                                                    <i class="las la-key"></i> @lang('Token')
                                                </button>

                                                {{-- Edit --}}
                                                <button class="btn btn-sm btn--info edit-broker-btn"
                                                    data-id="{{ $broker->id }}"
                                                    data-account_user_name="{{ $broker->account_user_name }}"
                                                    data-api_key="{{ $broker->api_key }}"
                                                    data-api_secret_key="{{ $broker->api_secret_key }}"
                                                    data-security_pin="{{ $broker->security_pin }}"
                                                    data-totp="{{ $broker->totp }}"
                                                    title="Edit broker">
                                                    <i class="las la-pencil-alt"></i>
                                                </button>

                                                {{-- Toggle Active/Inactive --}}
                                                {{-- <a href="{{ route('admin.zerodha-broker.toggle-status', $broker->id) }}"
                                                    class="btn btn-sm {{ $broker->is_active ? 'btn--danger' : 'btn--success' }}"
                                                    title="{{ $broker->is_active ? 'Deactivate' : 'Activate' }}">
                                                    <i class="las {{ $broker->is_active ? 'la-toggle-off' : 'la-toggle-on' }}"></i>
                                                </a> --}}

                                                {{-- Delete --}}
                                                <button class="btn btn-sm btn--danger delete-broker-btn"
                                                    data-id="{{ $broker->id }}"
                                                    title="Delete broker">
                                                    <i class="las la-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="las la-inbox" style="font-size:3rem; color:#ccc;"></i>
                                            <h5 class="text-muted mt-2">@lang('No Zerodha Brokers Found')</h5>
                                            <p class="text-muted small">@lang('Click "Add Broker" to get started')</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($brokers->hasPages())
                    <div class="card-footer">
                        {{ paginateLinks($brokers) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         ADD BROKER MODAL
    ══════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="addBrokerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form action="{{ route('admin.zerodha-broker.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Add Zerodha Broker')</h5>
                        <button type="button" class="close" data-bs-dismiss="modal">
                            <i class="las la-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">@lang('Account Username') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="account_user_name" class="form-control"
                                        placeholder="Zerodha client ID (e.g. AB1234)" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">@lang('Account Password') <sup class="text--danger">*</sup></label>
                                    <input type="password" name="account_password" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">@lang('API Key') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="api_key" class="form-control"
                                        placeholder="Zerodha API key" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">@lang('API Secret') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="api_secret_key" class="form-control"
                                        placeholder="Zerodha API secret" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Security PIN')</label>
                                    <input type="text" name="security_pin" class="form-control"
                                        placeholder="Optional PIN">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">@lang('TOTP Secret') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="totp" class="form-control"
                                        placeholder="Base32 TOTP secret" required>
                                    <small class="text-muted">Base32-encoded 2FA secret from Zerodha</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Notes')</label>
                                    <textarea name="notes" class="form-control" rows="2"
                                        placeholder="Optional notes about this broker account"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                        <button type="submit" class="btn btn--primary btn-sm">
                            <i class="las la-plus-circle"></i> @lang('Add Broker')
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         UPDATE TOKEN MODAL
    ══════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="updateTokenModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="updateTokenForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Update Access Token')</h5>
                        <button type="button" class="close" data-bs-dismiss="modal">
                            <i class="las la-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info p-3 mb-3 b-radius--5">
                            <strong>@lang('Steps to generate token:')</strong>
                            <ol class="mb-0 mt-2 ps-3">
                                <li>Click <strong>@lang('Login')</strong> button (opens Zerodha in a new tab)</li>
                                <li>Complete Zerodha authentication</li>
                                <li>After login, copy the <strong>full URL</strong> from the browser address bar</li>
                                <li>Paste it below and click <strong>@lang('Generate & Save')</strong></li>
                            </ol>
                            <p class="mt-2 mb-0 small">
                                <strong>@lang('Example URL:')</strong><br>
                                <code class="small">https://kite.zerodha.com/connect/login?status=success&request_token=XXXXXX&action=login</code>
                            </p>
                        </div>
                        <div class="form-group">
                            <label>@lang('Callback URL') <sup class="text--danger">*</sup></label>
                            <textarea name="callback_url" class="form-control" rows="3" required
                                placeholder="Paste the full callback URL here"></textarea>
                            <small class="text-muted">@lang('Paste the entire redirect URL from your browser after Zerodha login')</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                        <button type="submit" class="btn btn--primary btn-sm">
                            <i class="las la-key"></i> @lang('Generate & Save Token')
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- EDIT BROKER MODAL --}}
    <div class="modal fade" id="editBrokerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form id="editBrokerForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Edit Zerodha Broker')</h5>
                        <button type="button" class="close" data-bs-dismiss="modal">
                            <i class="las la-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Account Username') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="account_user_name" id="edit_account_user_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Account Password') <small class="text-muted">(leave blank to keep current)</small></label>
                                    <input type="password" name="account_password" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('API Key') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="api_key" id="edit_api_key" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('API Secret') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="api_secret_key" id="edit_api_secret_key" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Security PIN')</label>
                                    <input type="text" name="security_pin" id="edit_security_pin" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('TOTP Secret') <sup class="text--danger">*</sup></label>
                                    <input type="text" name="totp" id="edit_totp" class="form-control" required>
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

    {{-- ══════════════════════════════════════════════════════════════════════
         DELETE CONFIRM MODAL
    ══════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg--danger">
                    <h5 class="modal-title text-white">@lang('Confirm Delete')</h5>
                    <button type="button" class="close text-white" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>@lang('Are you sure you want to delete this broker?')</p>
                    <p class="text--danger">
                        <strong>@lang('Warning:')  </strong>
                        @lang('This action cannot be undone. All associated data will be permanently removed.')
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                    <form id="deleteForm" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn-sm">
                            <i class="las la-trash-alt"></i> @lang('Yes, Delete')
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
    .badge--success  { background: #10b981; color: #fff; }
    .badge--warning  { background: #f59e0b; color: #fff; }
    .badge--danger   { background: #ef4444; color: #fff; }
    .badge--info     { background: #3b82f6; color: #fff; }

    .button--group .btn { margin: 1px; }

    code {
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.8rem;
        word-break: break-all;
    }

    .alert-info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }

    .alert-info code {
        background: #dbeafe;
        color: #1e3a8a;
        display: block;
        margin-top: 4px;
        white-space: normal;
    }
</style>
@endpush

@push('script')
<script>
$(document).ready(function () {

    const storeUrl  = "{{ route('admin.zerodha-broker.store') }}";
    const updateUrl = "{{ route('admin.zerodha-broker.update', ':id') }}";

    // ── Reset modal to ADD mode ───────────────────────────────────────────
    $('#addBrokerBtn').on('click', function () {
        $('#brokerModalTitle').text('Add Zerodha Broker');
        $('#brokerSubmitBtn').html('<i class="las la-plus-circle"></i> Add Broker');
        $('#brokerForm')[0].reset();
        $('#brokerForm').attr('action', storeUrl);
        $('#account_password').attr('required', true);
        $('#passwordHint').addClass('d-none');
    });

    // ── Edit broker — fill modal from data-* attributes ──────────────────
    $(document).on('click', '.edit-broker-btn', function () {
        const btn = $(this);
        const id  = btn.data('id');
        const url = "{{ route('admin.zerodha-broker.update', ':id') }}".replace(':id', id);

        $('#editBrokerForm').attr('action', url);
        $('#edit_account_user_name').val(btn.data('account_user_name'));
        $('#edit_api_key').val(btn.data('api_key'));
        $('#edit_api_secret_key').val(btn.data('api_secret_key'));
        $('#edit_security_pin').val(btn.data('security_pin'));
        $('#edit_totp').val(btn.data('totp'));

        $('#editBrokerModal').modal('show');
    });

    // ── Update Token ─────────────────────────────────────────────────────
    $(document).on('click', '.update-token-btn', function () {
        const id  = $(this).data('id');
        const url = "{{ route('admin.zerodha-broker.update-token', ':id') }}".replace(':id', id);
        $('#updateTokenForm').attr('action', url);
        $('#updateTokenModal').modal('show');
    });

    // ── Delete ───────────────────────────────────────────────────────────
    $(document).on('click', '.delete-broker-btn', function () {
        const id  = $(this).data('id');
        const url = "{{ route('admin.zerodha-broker.destroy', ':id') }}".replace(':id', id);
        $('#deleteForm').attr('action', url);
        $('#deleteModal').modal('show');
    });

    // ── Show/Hide password in table ───────────────────────────────────────
    $(document).on('click', '.toggle-pass', function () {
        const span = $(this).prev('.pass-mask');
        const pass = span.data('pass');
        if (span.text() === '••••••••') {
            span.text(pass);
            $(this).removeClass('la-eye').addClass('la-eye-slash');
        } else {
            span.text('••••••••');
            $(this).removeClass('la-eye-slash').addClass('la-eye');
        }
    });

});
</script>
@endpush