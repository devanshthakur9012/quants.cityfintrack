@extends($activeTemplate.'layouts.frontend')
@section('content')

@include($activeTemplate.'partials.auth-shell')

<div class="ql-page">
<div class="ql-card">

    {{-- ══ LEFT — FORM ══ --}}
    <div class="ql-left">

        <a href="{{ route('home') }}" class="ql-logo">
            <div class="ql-logo-icon">Q</div>
            <div>
                <div class="ql-logo-name">CityQuants<sup style="font-size:9px">®</sup></div>
                <div class="ql-logo-sub">Optimize Opportunities</div>
            </div>
        </a>

        <h1 class="ql-heading">Log in</h1>

        {{-- ── STEP 1: Enter email ── --}}
        <div class="ql-step active" id="stepEmail">
            <div class="ql-input-group">
                <label class="ql-input-label">EMAIL ADDRESS</label>
                <input class="ql-input-field" type="email" id="loginEmail"
                       placeholder="you@example.com" autocomplete="email">
                <div class="ql-input-error" id="emailErr" style="display:none;"></div>
            </div>

            <button class="ql-cta-btn" id="getOtpBtn" onclick="doSendOtp()">
                Get OTP
            </button>

            <div class="ql-text-link" style="text-align:right; margin-bottom:18px;">
                <a href="#" onclick="showStep('stepPassword');return false;" class="accent">
                    Use Password Instead
                </a>
            </div>

            <div class="ql-sep"></div>
            <div style="text-align:center; font-size:13px; color:#888;">
                Don't have an account?
                <a href="{{ route('user.register') }}" class="accent" style="color:#F5A623; font-weight:600;">
                    Create Account
                </a>
            </div>
        </div>

        {{-- ── STEP 2: OTP entry ── --}}
        <div class="ql-step" id="stepOtp">
            <button class="ql-back" onclick="showStep('stepEmail')">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <p style="font-size:14px; color:#555; margin-bottom:6px;">OTP sent to</p>
            <p style="font-size:15px; font-weight:700; color:#1a1a1a; margin-bottom:20px;" id="otpSentTo">—</p>

            <div class="ql-otp-row" id="otpBoxes">
                <input class="ql-otp-box" type="text" inputmode="numeric" maxlength="1">
                <input class="ql-otp-box" type="text" inputmode="numeric" maxlength="1">
                <input class="ql-otp-box" type="text" inputmode="numeric" maxlength="1">
                <input class="ql-otp-box" type="text" inputmode="numeric" maxlength="1">
            </div>

            <div class="ql-text-link" style="margin-bottom:18px;">
                Didn't receive it?
                <a href="#" onclick="doSendOtp(true);return false;" class="accent" id="resendLink">
                    Resend OTP
                </a>
                <span id="resendTimer" style="color:#bbb; display:none;"> (wait <span id="timerCount">60</span>s)</span>
            </div>

            <button class="ql-cta-btn" id="verifyOtpBtn" onclick="doVerifyOtp()">
                Verify &amp; Login
            </button>

            <div class="ql-sep"></div>
            <div class="ql-tnc">
                <div class="ql-tnc-cb"><i class="fas fa-check"></i></div>
                <div>By proceeding, you agree to <a href="#">Terms &amp; Conditions</a></div>
            </div>
        </div>

        {{-- ── STEP 3: Password login ── --}}
        <div class="ql-step" id="stepPassword">
            <button class="ql-back" onclick="showStep('stepEmail')">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <form method="POST" action="{{ route('user.login.password') }}">
                @csrf
                <div class="ql-input-group">
                    <label class="ql-input-label">EMAIL ADDRESS</label>
                    <input class="ql-input-field {{ $errors->has('email') ? 'is-error' : '' }}"
                           type="email" name="email" value="{{ old('email') }}"
                           placeholder="you@example.com" autocomplete="email">
                    @error('email') <div class="ql-input-error">{{ $message }}</div> @enderror
                </div>
                <div class="ql-input-group">
                    <label class="ql-input-label">PASSWORD</label>
                    <div class="ql-pw-wrap">
                        <input class="ql-input-field" type="password" name="password"
                               placeholder="Enter your password" autocomplete="current-password">
                        <button type="button" class="ql-pw-toggle" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="ql-cta-btn">Login</button>
            </form>
            <div class="ql-text-link" style="text-align:right; margin-bottom:16px;">
                <a href="{{ route('user.forgot.password') }}" class="accent" style="color:#F5A623;">
                    Forgot Password?
                </a>
            </div>
            <div class="ql-sep" style="margin-top:0;"></div>
            <div class="ql-tnc">
                <div class="ql-tnc-cb"><i class="fas fa-check"></i></div>
                <div>By proceeding, you agree to <a href="#">Terms &amp; Conditions</a></div>
            </div>
        </div>

        @if($errors->any() && !$errors->has('email'))
        <script>document.addEventListener('DOMContentLoaded',function(){ showStep('stepPassword'); });</script>
        @endif

    </div>{{-- /.ql-left --}}

    {{-- ══ RIGHT — PROMO ══ --}}
    @include($activeTemplate.'partials.auth-right')

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    initOtpBoxes('#otpBoxes .ql-otp-box');
});

function doSendOtp(isResend) {
    var email = document.getElementById('loginEmail').value.trim();
    var btn   = document.getElementById('getOtpBtn');
    var errEl = document.getElementById('emailErr');

    errEl.style.display = 'none';

    if (!email || !/\S+@\S+\.\S+/.test(email)) {
        errEl.textContent = 'Please enter a valid email address.';
        errEl.style.display = 'block';
        return;
    }

    if (!isResend) qlLoading(btn, true);

    fetch('{{ route("user.login.send-otp") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        if (!isResend) qlLoading(btn, false);
        if (data.success) {
            document.getElementById('otpSentTo').textContent = email;
            showStep('stepOtp');
            document.querySelector('#otpBoxes .ql-otp-box').focus();
            startResendTimer();
            qlToast(data.message, 'success');
        } else {
            errEl.textContent = data.message;
            errEl.style.display = 'block';
            qlToast(data.message, 'error');
        }
    })
    .catch(function() {
        if (!isResend) qlLoading(btn, false);
        qlToast('Something went wrong. Please try again.', 'error');
    });
}

function doVerifyOtp() {
    var otp = getOtpValue('#otpBoxes .ql-otp-box');
    if (otp.length < 4) { qlToast('Please enter the complete 4-digit OTP.', 'error'); return; }

    var btn = document.getElementById('verifyOtpBtn');
    qlLoading(btn, true);

    fetch('{{ route("user.login.verify-otp") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            email: document.getElementById('loginEmail').value.trim(),
            otp: otp
        })
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        if (data.success) {
            qlToast('Login successful!', 'success');
            window.location.href = data.redirect;
        } else {
            qlLoading(btn, false);
            qlToast(data.message, 'error');
        }
    })
    .catch(function() {
        qlLoading(btn, false);
        qlToast('Something went wrong.', 'error');
    });
}

var resendInterval;
function startResendTimer() {
    var link  = document.getElementById('resendLink');
    var timer = document.getElementById('resendTimer');
    var count = document.getElementById('timerCount');
    var sec   = 60;

    link.style.display  = 'none';
    timer.style.display = 'inline';
    count.textContent   = sec;
    clearInterval(resendInterval);

    resendInterval = setInterval(function() {
        sec--;
        count.textContent = sec;
        if (sec <= 0) {
            clearInterval(resendInterval);
            link.style.display  = 'inline';
            timer.style.display = 'none';
        }
    }, 1000);
}
</script>

@endsection