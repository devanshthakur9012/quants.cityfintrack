{{-- FILE: resources/views/themes/{active_theme}/login.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ─── BASE ─────────────────────────────────────────────────────────────── */
.qa-auth-page {
    font-family: 'Exo 2', sans-serif;
    min-height: 100vh;
    background: #f5e6c8; /* warm beige like old design */
    display: flex;
    align-items: stretch;
}
.qa-auth-page * { box-sizing: border-box; }
.qa-auth-page h1,.qa-auth-page h2,.qa-auth-page h3 { font-family: 'Rajdhani', sans-serif; }
.qa-auth-page a { text-decoration: none; }

@keyframes authFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.qa-auth-anim    { animation: authFadeUp .5s ease both; }
.qa-auth-anim.d1 { animation-delay: .1s; }
.qa-auth-anim.d2 { animation-delay: .2s; }
@keyframes spin  { to { transform: rotate(360deg); } }

/* ─── WRAPPER ────────────────────────────────────────────────────────────── */
.qa-auth-inner {
    width: 100%;
    max-width: 1260px;
    margin: auto;
    padding: 40px 24px;
    display: flex;
    align-items: center;
    gap: 40px;
}

/* ─── LEFT — LOGIN CARD ──────────────────────────────────────────────────── */
.qa-auth-card {
    width: 420px;
    flex-shrink: 0;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 48px rgba(0,0,0,.10);
    padding: 40px 36px 32px;
}

/* Logo row inside card */
.qa-card-logo {
    display: flex; align-items: center; gap: 12px; margin-bottom: 28px;
}
.qa-card-logo-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: #7DFF00; color: #fff; font-size: 20px; font-weight: 700;
    font-family: 'Rajdhani', sans-serif;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.qa-card-logo-text { line-height: 1.2; }
.qa-card-logo-name {
    font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700; color: #1a1a2e;
}
.qa-card-logo-sub { font-size: 12px; color: #999; }

/* Heading */
.qa-auth-card h2 {
    font-size: 28px; font-weight: 700; color: #1a1a2e;
    margin: 0 0 6px; line-height: 1.15;
}
.qa-auth-card-sub { font-size: 13px; color: #888; margin-bottom: 24px; line-height: 1.6; }

/* Alert */
.qa-auth-alert {
    border-radius: 8px; padding: 11px 14px; font-size: 13px;
    margin-bottom: 16px; display: none; align-items: center; gap: 9px;
}
.qa-auth-alert.show { display: flex; }
.qa-auth-alert.success { background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; }
.qa-auth-alert.error   { background: #ffeaea; border: 1px solid #ffcdd2; color: #c62828; }

/* Form */
.qa-form-group { margin-bottom: 16px; }
.qa-form-label {
    display: block; font-size: 11px; font-weight: 700; color: #999;
    text-transform: uppercase; letter-spacing: .07em; margin-bottom: 7px;
}
.qa-form-input {
    width: 100%; padding: 12px 14px; border-radius: 9px;
    border: 1.5px solid #e5e9f2; font-size: 14px; color: #1a1a2e;
    font-family: 'Exo 2', sans-serif; outline: none;
    transition: border-color .2s, box-shadow .2s; background: #fff;
}
.qa-form-input:focus {
    border-color: #7DFF00;
    box-shadow: 0 0 0 3px rgba(245,166,35,.12);
}
.qa-form-input-wrap { position: relative; }
.qa-form-input-wrap .qa-form-input { padding-right: 44px; }
.qa-pass-toggle {
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: #bbb; cursor: pointer;
    font-size: 15px; display: flex; align-items: center; padding: 0;
}
.qa-pass-toggle:hover { color: #7DFF00; }
.qa-form-error { font-size: 12px; color: #e53935; margin-top: 5px; display: none; }
.qa-form-error.show { display: block; }

/* OTP input */
.qa-otp-input {
    width: 100%; padding: 12px 14px; border-radius: 9px;
    border: 1.5px solid #e5e9f2; font-size: 22px; font-weight: 700;
    letter-spacing: .35em; text-align: center;
    font-family: 'Rajdhani', sans-serif; outline: none; color: #1a1a2e;
    transition: border-color .2s, box-shadow .2s;
}
.qa-otp-input:focus {
    border-color: #7DFF00;
    box-shadow: 0 0 0 3px rgba(245,166,35,.12);
}

/* Submit btn */
.qa-auth-submit {
    width: 100%; padding: 13px; border-radius: 10px;
    background: #7DFF00; border: none; color: #000;
    font-size: 15px; font-weight: 700; font-family: 'Rajdhani', sans-serif;
    letter-spacing: .04em; cursor: pointer; transition: background .2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-top: 4px;
}
.qa-auth-submit:hover { background: #d4890e; }
.qa-auth-submit:disabled { opacity: .65; pointer-events: none; }
.qa-auth-submit .spinner {
    width: 16px; height: 16px; border: 2px solid rgba(0,0,0,.25);
    border-top-color: #000; border-radius: 50%;
    animation: spin .7s linear infinite; display: none;
}
.qa-auth-submit.loading .spinner { display: block; }
.qa-auth-submit.loading .btn-text { display: none; }

/* Secondary link-style btn */
.qa-auth-link-btn {
    background: none; border: none; color: #7DFF00; font-size: 13px;
    font-weight: 600; cursor: pointer; font-family: 'Exo 2', sans-serif;
    padding: 0; transition: color .2s; display: inline;
}
.qa-auth-link-btn:hover { color: #d4890e; }

/* Method toggle row */
.qa-method-toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0;
}
.qa-method-toggle-row span { font-size: 13px; color: #aab; }

/* Step dots */
.qa-step-dots { display: flex; gap: 6px; margin-bottom: 20px; }
.qa-step-dot  { width: 8px; height: 8px; border-radius: 50%; background: #e5e9f2; transition: all .3s; }
.qa-step-dot.active { background: #7DFF00; width: 22px; border-radius: 4px; }

/* Bottom links */
.qa-auth-bottom {
    margin-top: 20px; text-align: center;
    font-size: 13px; color: #aab;
}
.qa-auth-bottom a { color: #7DFF00; font-weight: 600; }
.qa-auth-bottom a:hover { color: #d4890e; }

/* Remember / forgot row */
.qa-form-row-between {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 18px; font-size: 13px; color: #666;
}
.qa-form-row-between label { display: flex; align-items: center; gap: 6px; cursor: pointer; }

/* ─── RIGHT — PROMO PANEL ────────────────────────────────────────────────── */
.qa-auth-promo {
    flex: 1; min-width: 0;
    display: flex; flex-direction: column; gap: 28px;
}

/* Promo heading */
.qa-promo-heading {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(26px, 3vw, 38px); font-weight: 700;
    color: #1a1a2e; line-height: 1.2;
}
.qa-promo-heading span { color: #7DFF00; }

/* Video */
.qa-promo-video {
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 12px 48px rgba(0,0,0,.15);
    aspect-ratio: 16/9; background: #000;
    border: 1px solid rgba(0,0,0,.08);
}
.qa-promo-video iframe { width: 100%; height: 100%; display: block; border: none; }

/* Features pills row */
.qa-promo-features {
    display: flex; flex-wrap: wrap; gap: 10px;
}
.qa-promo-feature-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.75);
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 30px; padding: 8px 18px;
    font-size: 13px; font-weight: 600; color: #1a1a2e;
    backdrop-filter: blur(4px);
}
.qa-promo-feature-pill i { color: #7DFF00; font-size: 12px; }

/* Brokers section */
.qa-promo-brokers-label {
    font-size: 12px; color: #999; font-weight: 700;
    text-transform: uppercase; letter-spacing: .08em;
    text-align: center; margin-bottom: 12px;
}
.qa-promo-brokers-divider {
    display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
}
.qa-promo-brokers-divider::before,
.qa-promo-brokers-divider::after {
    content: ''; flex: 1; height: 1px; background: rgba(0,0,0,.1);
}
.qa-promo-brokers { display: flex; flex-wrap: nowrap; gap: 10px; overflow-x: auto; padding-bottom: 4px; }
.qa-promo-brokers::-webkit-scrollbar { height: 3px; }
.qa-promo-brokers::-webkit-scrollbar-thumb { background: #e5e9f2; border-radius: 2px; }
.qa-promo-broker-pill {
    display: inline-flex; align-items: center; gap: 7px;
    flex-shrink: 0;
    padding: 7px 14px; border-radius: 30px;
    background: rgba(255,255,255,.8); border: 1px solid rgba(0,0,0,.08);
    font-size: 12.5px; font-weight: 600; color: #333;
    white-space: nowrap;
}
.qa-promo-broker-letter {
    width: 22px; height: 22px; border-radius: 6px;
    font-size: 11px; font-weight: 700; color: #fff;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

/* ─── RESPONSIVE ─────────────────────────────────────────────────────────── */
@media(max-width:900px) {
    .qa-auth-inner { flex-direction: column; padding: 24px 16px 40px; }
    .qa-auth-card  { width: 100%; max-width: 480px; margin: 0 auto; }
    .qa-auth-promo { width: 100%; }
}
</style>

<div class="qa-auth-page">
    <div class="qa-auth-inner">

        {{-- ══════════════════════════════════════════════
             LEFT — LOGIN CARD
        ══════════════════════════════════════════════ --}}
        <div class="qa-auth-card qa-auth-anim">

            {{-- Logo --}}
            <div class="qa-card-logo">
                <div class="qa-card-logo-icon">Q</div>
                <div class="qa-card-logo-text">
                    <div class="qa-card-logo-name">CityQuants®</div>
                    <div class="qa-card-logo-sub">Optimize Opportunities</div>
                </div>
            </div>

            {{-- Heading --}}
            <h2>{{ $loginHeading ?? 'Log in' }}</h2>
            @if(!empty($loginSubheading))
                <p class="qa-auth-card-sub">{{ $loginSubheading }}</p>
            @endif

            {{-- Alert --}}
            <div class="qa-auth-alert" id="loginAlert">
                <i class="fas fa-info-circle"></i>
                <span id="loginAlertMsg"></span>
            </div>

            {{-- ── OTP METHOD ── --}}
            <div id="otpMethod">

                {{-- Step 1: Email --}}
                <div id="otpStep1">
                    <div class="qa-form-group">
                        <label class="qa-form-label">Email Address</label>
                        <input type="email" id="otpEmail" class="qa-form-input"
                               placeholder="you@example.com" autocomplete="email">
                        <div class="qa-form-error" id="otpEmailErr">
                            Please enter a valid email.
                        </div>
                    </div>
                    <button class="qa-auth-submit" onclick="sendOtp()" id="sendOtpBtn">
                        <span class="spinner"></span>
                        <span class="btn-text">Get OTP</span>
                    </button>
                    <div style="text-align:center;margin-top:14px;font-size:13px;color:#aab;">
                        <button class="qa-auth-link-btn" onclick="switchToPassword()">
                            Use Password Instead
                        </button>
                    </div>
                </div>

                {{-- Step 2: OTP --}}
                <div id="otpStep2" style="display:none;">
                    <div class="qa-step-dots">
                        <div class="qa-step-dot"></div>
                        <div class="qa-step-dot active"></div>
                    </div>
                    <p style="font-size:13px;color:#888;margin-bottom:16px;">
                        OTP sent to <strong id="otpEmailDisplay"></strong>
                    </p>
                    <div class="qa-form-group">
                        <label class="qa-form-label">Enter OTP</label>
                        <input type="text" id="otpCode" class="qa-otp-input"
                               maxlength="4" inputmode="numeric" placeholder="••••">
                        <div class="qa-form-error" id="otpCodeErr">Invalid OTP.</div>
                    </div>
                    <button class="qa-auth-submit" onclick="verifyOtp()" id="verifyOtpBtn">
                        <span class="spinner"></span>
                        <span class="btn-text">Verify &amp; Login</span>
                    </button>
                    <div style="text-align:center;margin-top:12px;">
                        <button class="qa-auth-link-btn" onclick="backToEmail()">
                            ← Change Email
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── PASSWORD METHOD ── --}}
            <div id="passwordMethod" style="display:none;">
                <form method="POST" action="{{ route('user.login.password') }}">
                    @csrf
                    @if($errors->any())
                    <div class="qa-auth-alert error show">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                    @endif
                    <div class="qa-form-group">
                        <label class="qa-form-label">Email Address</label>
                        <input type="email" name="email" class="qa-form-input"
                               value="{{ old('email') }}"
                               placeholder="you@example.com" autocomplete="email" required>
                    </div>
                    <div class="qa-form-group">
                        <label class="qa-form-label">Password</label>
                        <div class="qa-form-input-wrap">
                            <input type="password" name="password" id="pwdInput"
                                   class="qa-form-input" placeholder="••••••••"
                                   autocomplete="current-password" required>
                            <button type="button" class="qa-pass-toggle" onclick="togglePwd()">
                                <i class="fas fa-eye" id="pwdIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="qa-form-row-between">
                        <label>
                            <input type="checkbox" name="remember"> Remember me
                        </label>
                        <a href="{{ route('user.forgot.password') }}"
                           style="color:#7DFF00;font-weight:600;font-size:13px;">
                            Forgot password?
                        </a>
                    </div>
                    <button type="submit" class="qa-auth-submit">
                        <span class="btn-text">Login</span>
                    </button>
                    <div style="text-align:center;margin-top:14px;font-size:13px;color:#aab;">
                        <button type="button" class="qa-auth-link-btn" onclick="switchToOtp()">
                            Use OTP Instead
                        </button>
                    </div>
                </form>
            </div>

            {{-- Bottom link --}}
            <div class="qa-auth-bottom">
                Don't have an account?
                <a href="{{ route('user.register') }}">Create Account</a>
            </div>

        </div>

        {{-- ══════════════════════════════════════════════
             RIGHT — PROMO PANEL
        ══════════════════════════════════════════════ --}}
        <div class="qa-auth-promo qa-auth-anim d1">

            {{-- Heading --}}
            <div class="qa-promo-heading">
                Options Trading <span>Analytical</span> Platform
            </div>

            {{-- Promo video — dynamic from AuthPageCms --}}
            @if(!empty($promoVideo))
            <div class="qa-promo-video">
                <iframe src="{{ $promoVideo }}"
                        allow="autoplay; encrypted-media"
                        allowfullscreen>
                </iframe>
            </div>
            @endif

            {{-- Features pills — dynamic from AuthPageCms --}}
            @if(!empty($features))
            <div class="qa-promo-features">
                @foreach($features as $feature)
                <div class="qa-promo-feature-pill">
                    <i class="fas fa-check-circle"></i>
                    {{ $feature }}
                </div>
                @endforeach
            </div>
            @endif

            {{-- Brokers — dynamic from AuthPageCms --}}
            @if(!empty($brokers))
            <div>
                <div class="qa-promo-brokers-divider">
                    <span class="qa-promo-brokers-label">Trade With</span>
                </div>
                <div class="qa-promo-brokers">
                    @foreach($brokers as $broker)
                    <div class="qa-promo-broker-pill">
                        <div class="qa-promo-broker-letter"
                             style="background:{{ $broker['bg'] ?? '#455a64' }}">
                            {{ $broker['letter'] ?? strtoupper(substr($broker['name'], 0, 1)) }}
                        </div>
                        {{ $broker['name'] }}
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

    </div>
</div>

<script>
/* ── SWITCH METHODS ── */
function switchToPassword() {
    document.getElementById('otpMethod').style.display      = 'none';
    document.getElementById('passwordMethod').style.display = '';
    clearAlert();
}
function switchToOtp() {
    document.getElementById('passwordMethod').style.display = 'none';
    document.getElementById('otpMethod').style.display      = '';
    clearAlert();
}

/* ── ALERT ── */
function showAlert(msg, type) {
    var el = document.getElementById('loginAlert');
    document.getElementById('loginAlertMsg').textContent = msg;
    el.className = 'qa-auth-alert show ' + type;
}
function clearAlert() {
    document.getElementById('loginAlert').className = 'qa-auth-alert';
}

/* ── SEND OTP ── */
function sendOtp() {
    clearAlert();
    var email = document.getElementById('otpEmail').value.trim();
    var errEl = document.getElementById('otpEmailErr');
    errEl.classList.remove('show');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errEl.classList.add('show'); return;
    }
    var btn = document.getElementById('sendOtpBtn');
    btn.classList.add('loading'); btn.disabled = true;
    fetch('{{ route("user.login.send-otp") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ email: email })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('otpEmailDisplay').textContent = email;
            document.getElementById('otpStep1').style.display = 'none';
            document.getElementById('otpStep2').style.display = '';
            showAlert('OTP sent! Check your inbox.', 'success');
        } else {
            showAlert(data.message || 'Something went wrong.', 'error');
        }
    })
    .catch(function() { showAlert('Network error. Please try again.', 'error'); })
    .finally(function() { btn.classList.remove('loading'); btn.disabled = false; });
}

/* ── VERIFY OTP ── */
function verifyOtp() {
    clearAlert();
    var email = document.getElementById('otpEmail').value.trim();
    var otp   = document.getElementById('otpCode').value.trim();
    var errEl = document.getElementById('otpCodeErr');
    errEl.classList.remove('show');
    if (!otp || otp.length !== 4) { errEl.classList.add('show'); return; }
    var btn = document.getElementById('verifyOtpBtn');
    btn.classList.add('loading'); btn.disabled = true;
    fetch('{{ route("user.login.verify-otp") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ email: email, otp: otp })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showAlert('Login successful! Redirecting…', 'success');
            window.location.href = data.redirect || '{{ route("user.dashboard") }}';
        } else {
            errEl.textContent = data.message || 'Invalid OTP.';
            errEl.classList.add('show');
        }
    })
    .catch(function() { showAlert('Network error. Please try again.', 'error'); })
    .finally(function() { btn.classList.remove('loading'); btn.disabled = false; });
}

/* ── BACK TO EMAIL ── */
function backToEmail() {
    document.getElementById('otpStep2').style.display = 'none';
    document.getElementById('otpStep1').style.display = '';
    document.getElementById('otpCode').value = '';
    clearAlert();
}

/* ── PASSWORD TOGGLE ── */
function togglePwd() {
    var input = document.getElementById('pwdInput');
    var icon  = document.getElementById('pwdIcon');
    if (input.type === 'password') {
        input.type = 'text'; icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password'; icon.className = 'fas fa-eye';
    }
}

/* ── AUTO SUBMIT ON 4 DIGITS ── */
document.getElementById('otpCode').addEventListener('input', function() {
    if (this.value.length === 4) verifyOtp();
});

/* ── If Laravel errors, show password tab ── */
@if($errors->any())
    switchToPassword();
@endif
</script>
@endsection