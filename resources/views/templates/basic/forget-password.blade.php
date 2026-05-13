@extends($activeTemplate.'layouts.frontend')
@section('content')

@include($activeTemplate.'partials.auth-shell')

<div class="ql-page">
<div class="ql-card">

    <div class="ql-left">

        <a href="{{ route('home') }}" class="ql-logo">
            <div class="ql-logo-icon">Q</div>
            <div>
                <div class="ql-logo-name">CityQuants<sup style="font-size:9px">®</sup></div>
                <div class="ql-logo-sub">Optimize Opportunities</div>
            </div>
        </a>

        {{-- ── STEP 1: Enter email ── --}}
        <div class="ql-step active" id="stepForgot">
            <h1 class="ql-heading">Forgot Password</h1>
            <p style="font-size:14px; color:#666; margin-bottom:24px;">
                Enter your registered email and we'll send you a reset link.
            </p>

            <div class="ql-input-group">
                <label class="ql-input-label">EMAIL ADDRESS</label>
                <input class="ql-input-field" type="email" id="forgotEmail"
                       placeholder="you@example.com" autocomplete="email">
                <div class="ql-input-error" id="forgotErr" style="display:none;"></div>
            </div>

            <button class="ql-cta-btn" id="forgotBtn" onclick="doSendReset()">
                Send Reset Link
            </button>

            <div class="ql-text-link" style="text-align:center; margin-top:8px;">
                <a href="{{ route('user.login') }}">
                    <i class="fas fa-arrow-left" style="font-size:11px;"></i> Back to Login
                </a>
            </div>
        </div>

        {{-- ── STEP 2: Success ── --}}
        <div class="ql-step" id="stepForgotDone">
            <div style="text-align:center; padding:20px 0;">
                <div style="width:70px;height:70px;border-radius:50%;background:#F5A623;
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 20px; font-size:30px; color:#fff;">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h2 style="font-family:'Rajdhani',sans-serif; font-size:24px; font-weight:700;
                            color:#1a1a1a; margin-bottom:12px;">Link Sent!</h2>
                <p style="font-size:14px; color:#666; line-height:1.6; margin-bottom:20px;">
                    If an account exists for <strong id="forgotEmailConfirm">—</strong>,
                    you'll receive a reset link shortly.<br>
                    The link expires in <strong>1 hour</strong>.
                </p>
                <a href="{{ route('user.login') }}" class="ql-outline-btn" style="display:block;">
                    Back to Login
                </a>
                <div style="font-size:13px; color:#888; margin-top:12px;">
                    Check your spam folder if you don't see it.
                </div>
            </div>
        </div>

    </div>

    @include($activeTemplate.'partials.auth-right')

</div>
</div>

<script>
function doSendReset() {
    var email = document.getElementById('forgotEmail').value.trim();
    var btn   = document.getElementById('forgotBtn');
    var errEl = document.getElementById('forgotErr');
    errEl.style.display = 'none';

    if (!email || !/\S+@\S+\.\S+/.test(email)) {
        errEl.textContent = 'Please enter a valid email address.';
        errEl.style.display = 'block';
        return;
    }

    qlLoading(btn, true);

    fetch('{{ route("user.forgot.password.send") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        qlLoading(btn, false);
        document.getElementById('forgotEmailConfirm').textContent = email;
        showStep('stepForgotDone');
    })
    .catch(function() {
        qlLoading(btn, false);
        qlToast('Something went wrong. Try again.', 'error');
    });
}
</script>

@endsection