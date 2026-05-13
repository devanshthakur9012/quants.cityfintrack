{{-- FILE: resources/views/admin/courses/payment-gateway/edit.blade.php --}}
@extends('admin.layouts.app')

@section('panel')

<div class="row justify-content-center">
    <div class="col-xl-7">

        <div class="card b-radius--10">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-cog me-1"></i> Configure {{ $gateway->name }}
                </h5>
            </div>

            <form action="{{ route('admin.courses.gateway.update', $gateway) }}" method="POST">
                @csrf @method('PUT')

                <div class="card-body">

                    {{-- Status + Mode --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Gateway Status</label>
                            <select name="status" class="form-select">
                                <option value="1" @selected($gateway->status == 1)>Active</option>
                                <option value="0" @selected($gateway->status == 0)>Inactive</option>
                            </select>
                            <small class="text-muted">Only one gateway can be active at a time.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mode</label>
                            <select name="test_mode" class="form-select">
                                <option value="1" @selected($gateway->test_mode == 1)>Test Mode</option>
                                <option value="0" @selected($gateway->test_mode == 0)>Live Mode</option>
                            </select>
                            <small class="text-muted">Use test keys in test mode, live keys in live mode.</small>
                        </div>
                    </div>

                    {{-- Razorpay Keys --}}
                    <div class="alert alert--info mb-4 d-flex align-items-start gap-2">
                        <i class="las la-info-circle mt-1" style="font-size:18px;flex-shrink:0;"></i>
                        <div>
                            <strong>Razorpay Setup:</strong> Log in to your
                            <a href="https://dashboard.razorpay.com/app/keys" target="_blank" class="text--primary">Razorpay Dashboard</a>,
                            go to <strong>Settings → API Keys</strong>, generate or copy your keys, and paste them below.
                            Use <strong>test keys</strong> when Test Mode is active.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required fw-bold">
                            <i class="las la-key me-1"></i>
                            Key ID
                            <small class="text-muted fw-normal">(starts with rzp_test_ or rzp_live_)</small>
                        </label>
                        <input type="text" name="key_id"
                               class="form-control @error('key_id') is-invalid @enderror"
                               value="{{ old('key_id', $creds['key_id'] ?? '') }}"
                               placeholder="rzp_test_xxxxxxxxxxxxxx" required>
                        @error('key_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label required fw-bold">
                            <i class="las la-lock me-1"></i>
                            Key Secret
                        </label>
                        <div class="input-group">
                            <input type="password" name="key_secret" id="keySecretInput"
                                   class="form-control @error('key_secret') is-invalid @enderror"
                                   value="{{ old('key_secret', $creds['key_secret'] ?? '') }}"
                                   placeholder="••••••••••••••••••••••••" required>
                            <button type="button" class="btn btn--secondary" id="toggleSecret">
                                <i class="las la-eye" id="toggleSecretIcon"></i>
                            </button>
                        </div>
                        <small class="text-muted">Your key secret is encrypted before storage.</small>
                        @error('key_secret')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.courses.gateway.index') }}" class="btn btn--secondary btn--sm">
                        <i class="las la-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="btn btn--primary btn--sm">
                        <i class="las la-save"></i> Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
document.getElementById('toggleSecret').addEventListener('click', function () {
    var input = document.getElementById('keySecretInput');
    var icon  = document.getElementById('toggleSecretIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'las la-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'las la-eye';
    }
});
</script>
@endpush