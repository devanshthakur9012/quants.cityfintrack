{{--
    Partial: admin/zerodha-broker/edit-modal.blade.php
    Loaded via AJAX into #editModalContent
--}}
<form action="{{ route('admin.zerodha-broker.update', $broker->id) }}" method="POST">
    @csrf
    <div class="modal-header">
        <h5 class="modal-title">@lang('Edit Broker') — {{ $broker->client_name }}</h5>
        <button type="button" class="close" data-bs-dismiss="modal">
            <i class="las la-times"></i>
        </button>
    </div>

    <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="required">@lang('Client Name') <sup class="text--danger">*</sup></label>
                    <input type="text" name="client_name" class="form-control"
                        value="{{ $broker->client_name }}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="required">@lang('Broker Name') <sup class="text--danger">*</sup></label>
                    <input type="text" name="broker_name" class="form-control"
                        value="{{ $broker->broker_name }}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="required">@lang('Account Username') <sup class="text--danger">*</sup></label>
                    <input type="text" name="account_user_name" class="form-control"
                        value="{{ $broker->account_user_name }}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>@lang('Account Password')</label>
                    <input type="password" name="account_password" class="form-control"
                        placeholder="Leave blank to keep current password">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="required">@lang('API Key') <sup class="text--danger">*</sup></label>
                    <input type="text" name="api_key" class="form-control"
                        value="{{ $broker->api_key }}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="required">@lang('API Secret') <sup class="text--danger">*</sup></label>
                    <input type="text" name="api_secret_key" class="form-control"
                        value="{{ $broker->api_secret_key }}" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>@lang('Security PIN')</label>
                    <input type="text" name="security_pin" class="form-control"
                        value="{{ $broker->security_pin }}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="required">@lang('TOTP Secret') <sup class="text--danger">*</sup></label>
                    <input type="text" name="totp" class="form-control"
                        value="{{ $broker->totp }}" required>
                    <small class="text-muted">@lang('Base32-encoded 2FA secret')</small>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('Notes')</label>
                    <textarea name="notes" class="form-control" rows="2"
                        placeholder="Optional notes">{{ $broker->notes }}</textarea>
                </div>
            </div>
        </div>

        {{-- Token info box --}}
        <div class="alert {{ $broker->hasValidToken() ? 'alert-success' : 'alert-warning' }} p-3 b-radius--5 mt-2">
            <div class="d-flex align-items-center gap-2">
                <i class="las la-key fs-5"></i>
                <div>
                    @if ($broker->hasValidToken())
                        <strong>@lang('Token Status:') </strong> @lang('Valid') —
                        {{ $broker->token_expiry_remaining }}
                    @elseif ($broker->access_token)
                        <strong>@lang('Token Status:') </strong> @lang('Expired') —
                        @lang('Please login again to refresh.')
                    @else
                        <strong>@lang('Token Status:') </strong> @lang('No token set yet.') —
                        @lang('Use the Login button after saving.')
                    @endif
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