{{-- FILE: resources/views/admin/cp/gateway.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-8">

        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">CP Payment Gateway</h5>
                <p class="text-muted mb-0 small">Configure Razorpay credentials for CP subscriptions</p>
            </div>
            <a href="{{ route('admin.cp.plans.index') }}" class="btn btn--secondary btn-sm">
                <i class="las la-arrow-left"></i> Back
            </a>
        </div>

        {{-- Status Banner --}}
        <div class="alert {{ $gateway->status ? 'alert-success' : 'alert-warning' }} b-radius--10 mb-4 d-flex align-items-center gap-2">
            <i class="las {{ $gateway->status ? 'la-check-circle' : 'la-exclamation-triangle' }}" style="font-size:1.4rem;"></i>
            <div>
                <strong>Gateway Status:</strong>
                {{ $gateway->status ? 'Active — payments are live.' : 'Inactive — payments are disabled.' }}
            </div>
        </div>

        {{-- Gateway Form --}}
        <div class="card b-radius--10">
            <div class="card-header">
                <div class="d-flex align-items-center gap-2">
                    {{-- Razorpay logo placeholder icon --}}
                    <span style="background:#072654;border-radius:6px;width:36px;height:36px;
                                 display:flex;align-items:center;justify-content:center;">
                        <i class="las la-credit-card" style="color:#fff;font-size:1.1rem;"></i>
                    </span>
                    <div>
                        <h6 class="mb-0">Razorpay</h6>
                        <small class="text-muted">API Credentials</small>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cp.gateway.update') }}" method="POST">
                    @csrf

                    {{-- Key ID --}}
                    <div class="form-group mb-3">
                        <label class="form-label">
                            Key ID <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="las la-key"></i></span>
                            <input type="text"
                                   name="key_id"
                                   class="form-control @error('key_id') is-invalid @enderror"
                                   value="{{ old('key_id', $gateway->credentials['key_id'] ?? '') }}"
                                   placeholder="rzp_live_XXXXXXXXXXXXXX"
                                   autocomplete="off"
                                   required>
                            @error('key_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <small class="text-muted">Your Razorpay Key ID from the dashboard.</small>
                    </div>

                    {{-- Key Secret --}}
                    <div class="form-group mb-3">
                        <label class="form-label">
                            Key Secret <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="las la-lock"></i></span>
                            <input type="password"
                                   name="key_secret"
                                   id="keySecretInput"
                                   class="form-control @error('key_secret') is-invalid @enderror"
                                   value="{{ old('key_secret', $gateway->credentials['key_secret'] ?? '') }}"
                                   placeholder="••••••••••••••••••••••••"
                                   autocomplete="new-password"
                                   required>
                            <button type="button" class="input-group-text" id="toggleSecret"
                                    title="Show/Hide Secret" style="cursor:pointer;">
                                <i class="las la-eye" id="toggleIcon"></i>
                            </button>
                            @error('key_secret')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <small class="text-muted">Never share your Key Secret publicly.</small>
                    </div>

                    <hr class="my-4">

                    {{-- Status Toggle --}}
                    <div class="form-group mb-4">
                        <div class="d-flex align-items-center justify-content-between
                                    border b-radius--10 px-3 py-3">
                            <div>
                                <label class="form-label mb-0 fw-600">Enable Gateway</label>
                                <p class="text-muted small mb-0">
                                    Toggle to activate or deactivate Razorpay payments.
                                </p>
                            </div>
                            <div class="form-check form-switch ms-3">
                                <input class="form-check-input" type="checkbox"
                                       name="status" id="gatewayStatus"
                                       style="width:48px;height:26px;cursor:pointer;"
                                       {{ $gateway->status ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>

                    {{-- Info Box --}}
                    <div class="alert alert-info b-radius--10 d-flex gap-2 mb-4" style="font-size:13px;">
                        <i class="las la-info-circle mt-1" style="font-size:1.2rem;flex-shrink:0;"></i>
                        <div>
                            Find your API keys in the
                            <a href="https://dashboard.razorpay.com/app/keys" target="_blank"
                               rel="noopener" class="alert-link">
                                Razorpay Dashboard → Settings → API Keys
                            </a>.
                            Use <strong>Test</strong> keys during development and
                            <strong>Live</strong> keys in production.
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('admin.cp.plans.index') }}" class="btn btn--secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn--primary">
                            <i class="las la-save"></i> Save Gateway Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Connection Test Card (read-only info) --}}
        @if($gateway->id && $gateway->status)
        <div class="card b-radius--10 mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="las la-plug me-1"></i> Connection Info</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="border b-radius--10 px-3 py-3 text-center">
                            <p class="text-muted small mb-1">Gateway</p>
                            <strong>{{ $gateway->name ?? 'Razorpay' }}</strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="border b-radius--10 px-3 py-3 text-center">
                            <p class="text-muted small mb-1">Key ID (masked)</p>
                            <strong>
                                @php
                                    $kid = $gateway->credentials['key_id'] ?? '';
                                    echo $kid ? substr($kid, 0, 8) . str_repeat('•', max(0, strlen($kid) - 8)) : '—';
                                @endphp
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

@push('script')
<script>
    // Toggle key secret visibility
    document.getElementById('toggleSecret').addEventListener('click', function () {
        const input = document.getElementById('keySecretInput');
        const icon  = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('la-eye', 'la-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('la-eye-slash', 'la-eye');
        }
    });
</script>
@endpush