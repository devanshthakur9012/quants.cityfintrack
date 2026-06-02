{{-- FILE: resources/views/themes/{active_theme}/login.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
.qa-auth-page{font-family:'Exo 2',sans-serif;min-height:100vh;background:#f5e6c8;display:flex;align-items:stretch;}
.qa-auth-page *{box-sizing:border-box;}
.qa-auth-page h1,.qa-auth-page h2,.qa-auth-page h3{font-family:'Rajdhani',sans-serif;}
.qa-auth-page a{text-decoration:none;}
@keyframes authFadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.qa-auth-anim{animation:authFadeUp .5s ease both;}
.qa-auth-anim.d1{animation-delay:.1s;}
.qa-auth-anim.d2{animation-delay:.2s;}
@keyframes spin{to{transform:rotate(360deg);}}

.qa-auth-inner{width:100%;max-width:1260px;margin:auto;padding:40px 24px;display:flex;align-items:center;gap:40px;}

/* CARD */
.qa-auth-card{width:420px;flex-shrink:0;background:#fff;border-radius:20px;box-shadow:0 8px 48px rgba(0,0,0,.10);padding:40px 36px 32px;}
.qa-card-logo{display:flex;align-items:center;gap:12px;margin-bottom:28px;}
.qa-card-logo-icon{width:48px;height:48px;border-radius:12px;background:#7DFF00;color:#000;font-size:20px;font-weight:700;font-family:'Rajdhani',sans-serif;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.qa-card-logo-text{line-height:1.2;}
.qa-card-logo-name{font-family:'Rajdhani',sans-serif;font-size:18px;font-weight:700;color:#1a1a2e;}
.qa-card-logo-sub{font-size:12px;color:#999;}
.qa-auth-card h2{font-size:28px;font-weight:700;color:#1a1a2e;margin:0 0 6px;line-height:1.15;}
.qa-auth-card-sub{font-size:13px;color:#888;margin-bottom:24px;line-height:1.6;}

/* ALERT */
.qa-auth-alert{border-radius:8px;padding:11px 14px;font-size:13px;margin-bottom:16px;display:none;align-items:center;gap:9px;}
.qa-auth-alert.show{display:flex;}
.qa-auth-alert.success{background:#e8f5e9;border:1px solid #c8e6c9;color:#2e7d32;}
.qa-auth-alert.error{background:#ffeaea;border:1px solid #ffcdd2;color:#c62828;}

/* FORM */
.qa-form-group{margin-bottom:16px;}
.qa-form-label{display:block;font-size:11px;font-weight:700;color:#999;text-transform:uppercase;letter-spacing:.07em;margin-bottom:7px;}
.qa-form-input{width:100%;padding:12px 14px;border-radius:9px;border:1.5px solid #e5e9f2;font-size:14px;color:#1a1a2e;font-family:'Exo 2',sans-serif;outline:none;transition:border-color .2s,box-shadow .2s;background:#fff;}
.qa-form-input:focus{border-color:#7DFF00;box-shadow:0 0 0 3px rgba(125,255,0,.12);}
.qa-form-error{font-size:12px;color:#e53935;margin-top:5px;display:none;}
.qa-form-error.show{display:block;}

/* OTP */
.qa-otp-input{width:100%;padding:12px 14px;border-radius:9px;border:1.5px solid #e5e9f2;font-size:28px;font-weight:700;letter-spacing:.35em;text-align:center;font-family:'Rajdhani',sans-serif;outline:none;color:#1a1a2e;transition:border-color .2s,box-shadow .2s;}
.qa-otp-input:focus{border-color:#7DFF00;box-shadow:0 0 0 3px rgba(125,255,0,.12);}

/* TIMER */
.qa-otp-timer{font-size:12px;color:#aab;text-align:center;margin-top:8px;}
.qa-otp-timer span{color:#e53935;font-weight:700;}

/* SUBMIT */
.qa-auth-submit{width:100%;padding:13px;border-radius:10px;background:#7DFF00;border:none;color:#000;font-size:15px;font-weight:700;font-family:'Rajdhani',sans-serif;letter-spacing:.04em;cursor:pointer;transition:background .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;}
.qa-auth-submit:hover{background:#6be000;}
.qa-auth-submit:disabled{opacity:.65;pointer-events:none;}
.qa-auth-submit .spinner{width:16px;height:16px;border:2px solid rgba(0,0,0,.25);border-top-color:#000;border-radius:50%;animation:spin .7s linear infinite;display:none;}
.qa-auth-submit.loading .spinner{display:block;}
.qa-auth-submit.loading .btn-text{display:none;}
.qa-auth-link-btn{background:none;border:none;color:#7DFF00;font-size:13px;font-weight:600;cursor:pointer;font-family:'Exo 2',sans-serif;padding:0;transition:color .2s;}
.qa-auth-link-btn:hover{color:#6be000;}

/* STEP DOTS */
.qa-step-dots{display:flex;gap:6px;margin-bottom:20px;}
.qa-step-dot{width:8px;height:8px;border-radius:50%;background:#e5e9f2;transition:all .3s;}
.qa-step-dot.active{background:#7DFF00;width:22px;border-radius:4px;}

/* PROMO */
.qa-auth-promo{flex:1;min-width:0;display:flex;flex-direction:column;gap:28px;}
.qa-promo-heading{font-family:'Rajdhani',sans-serif;font-size:clamp(26px,3vw,38px);font-weight:700;color:#1a1a2e;line-height:1.2;}
.qa-promo-heading span{color:#7DFF00;}
.qa-promo-video{border-radius:16px;overflow:hidden;box-shadow:0 12px 48px rgba(0,0,0,.15);aspect-ratio:16/9;background:#000;border:1px solid rgba(0,0,0,.08);}
.qa-promo-video iframe{width:100%;height:100%;display:block;border:none;}
.qa-promo-features{display:flex;flex-wrap:wrap;gap:10px;}
.qa-promo-feature-pill{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.75);border:1px solid rgba(0,0,0,.08);border-radius:30px;padding:8px 18px;font-size:13px;font-weight:600;color:#1a1a2e;backdrop-filter:blur(4px);}
.qa-promo-feature-pill i{color:#7DFF00;font-size:12px;}
.qa-promo-brokers-label{font-size:12px;color:#999;font-weight:700;text-transform:uppercase;letter-spacing:.08em;text-align:center;margin-bottom:12px;}
.qa-promo-brokers-divider{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.qa-promo-brokers-divider::before,.qa-promo-brokers-divider::after{content:'';flex:1;height:1px;background:rgba(0,0,0,.1);}
.qa-promo-brokers{display:flex;flex-wrap:nowrap;gap:10px;overflow-x:auto;padding-bottom:4px;}
.qa-promo-brokers::-webkit-scrollbar{height:3px;}
.qa-promo-brokers::-webkit-scrollbar-thumb{background:#e5e9f2;border-radius:2px;}
.qa-promo-broker-pill{display:inline-flex;align-items:center;gap:7px;flex-shrink:0;padding:7px 14px;border-radius:30px;background:rgba(255,255,255,.8);border:1px solid rgba(0,0,0,.08);font-size:12.5px;font-weight:600;color:#333;white-space:nowrap;}
.qa-promo-broker-letter{width:22px;height:22px;border-radius:6px;font-size:11px;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

@media(max-width:900px){
    .qa-auth-inner{flex-direction:column;padding:24px 16px 40px;}
    .qa-auth-card{width:100%;max-width:480px;margin:0 auto;}
    .qa-auth-promo{width:100%;}
}
</style>

<div class="qa-auth-page">
<div class="qa-auth-inner">

    {{-- ── LOGIN CARD ── --}}
    <div class="qa-auth-card qa-auth-anim">

        <div class="qa-card-logo">
            <div class="qa-card-logo-icon">Q</div>
            <div class="qa-card-logo-text">
                <div class="qa-card-logo-name">CityQuants®</div>
                <div class="qa-card-logo-sub">Optimize Opportunities</div>
            </div>
        </div>

        <h2>{{ $loginHeading ?? 'Welcome Back' }}</h2>
        @if(!empty($loginSubheading))
        <p class="qa-auth-card-sub">{{ $loginSubheading }}</p>
        @endif

        {{-- Alert --}}
        <div class="qa-auth-alert" id="loginAlert">
            <i class="fas fa-info-circle"></i>
            <span id="loginAlertMsg"></span>
        </div>

        {{-- Step 1: Enter email --}}
        <div id="step1">
            <div class="qa-form-group">
                <label class="qa-form-label">Email Address</label>
                <input type="email" id="loginEmail" class="qa-form-input"
                       placeholder="you@example.com" autocomplete="email"
                       onkeydown="if(event.key==='Enter')sendOtp()">
                <div class="qa-form-error" id="emailErr">Please enter a valid email address.</div>
            </div>
            <button class="qa-auth-submit" onclick="sendOtp()" id="sendOtpBtn">
                <span class="spinner"></span>
                <span class="btn-text"><i class="fas fa-paper-plane" style="margin-right:6px;"></i>Send OTP</span>
            </button>
            <p style="font-size:12px;color:#bbb;text-align:center;margin-top:14px;line-height:1.6;">
                A 4-digit OTP will be sent to your email.<br>
                New here? An account is created automatically.
            </p>
        </div>

        {{-- Step 2: Enter OTP --}}
        <div id="step2" style="display:none;">
            <div class="qa-step-dots">
                <div class="qa-step-dot"></div>
                <div class="qa-step-dot active"></div>
            </div>
            <p style="font-size:13px;color:#888;margin-bottom:16px;line-height:1.5;">
                OTP sent to <strong id="emailDisplay" style="color:#1a1a2e;"></strong>
            </p>
            <div class="qa-form-group">
                <label class="qa-form-label">Enter 4-Digit OTP</label>
                <input type="text" id="otpInput" class="qa-otp-input"
                       maxlength="4" inputmode="numeric" placeholder="••••"
                       autocomplete="one-time-code">
                <div class="qa-form-error" id="otpErr">Invalid OTP. Please try again.</div>
            </div>
            <div class="qa-otp-timer" id="otpTimerWrap">
                Expires in <span id="otpTimer">10:00</span>
            </div>
            <button class="qa-auth-submit" onclick="verifyOtp()" id="verifyBtn" style="margin-top:14px;">
                <span class="spinner"></span>
                <span class="btn-text"><i class="fas fa-check" style="margin-right:6px;"></i>Verify &amp; Login</span>
            </button>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:13px;">
                <button class="qa-auth-link-btn" onclick="backToEmail()">← Change Email</button>
                <button class="qa-auth-link-btn" id="resendBtn" onclick="resendOtp()" style="display:none;">Resend OTP</button>
            </div>
        </div>

    </div>

    {{-- ── PROMO PANEL ── --}}
    <div class="qa-auth-promo qa-auth-anim d1">
        <div class="qa-promo-heading">
            Options Trading <span>Analytical</span> Platform
        </div>
        @if(!empty($promoVideo))
        <div class="qa-promo-video">
            <iframe src="{{ $promoVideo }}" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
        @endif
        @if(!empty($features))
        <div class="qa-promo-features">
            @foreach($features as $feature)
            <div class="qa-promo-feature-pill">
                <i class="fas fa-check-circle"></i> {{ $feature }}
            </div>
            @endforeach
        </div>
        @endif
        @if(!empty($brokers))
        <div>
            <div class="qa-promo-brokers-divider">
                <span class="qa-promo-brokers-label">Trade With</span>
            </div>
            <div class="qa-promo-brokers">
                @foreach($brokers as $broker)
                <div class="qa-promo-broker-pill">
                    <div class="qa-promo-broker-letter" style="background:{{ $broker['bg'] ?? '#455a64' }}">
                        {{ $broker['letter'] ?? strtoupper(substr($broker['name'],0,1)) }}
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
var timerInterval = null;
var timerSeconds  = 600; // 10 minutes

/* ── SEND OTP ── */
function sendOtp() {
    clearAlert();
    var email = document.getElementById('loginEmail').value.trim();
    var errEl = document.getElementById('emailErr');
    errEl.classList.remove('show');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errEl.classList.add('show');
        document.getElementById('loginEmail').focus();
        return;
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
            document.getElementById('emailDisplay').textContent = email;
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = '';
            document.getElementById('otpInput').focus();
            startTimer();
            showAlert('OTP sent! Please check your inbox.', 'success');
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
    var email = document.getElementById('loginEmail').value.trim();
    var otp   = document.getElementById('otpInput').value.trim();
    var errEl = document.getElementById('otpErr');
    errEl.classList.remove('show');
    if (!otp || otp.length !== 4) {
        errEl.textContent = 'Please enter the 4-digit OTP.';
        errEl.classList.add('show');
        return;
    }
    var btn = document.getElementById('verifyBtn');
    btn.classList.add('loading'); btn.disabled = true;

    fetch('{{ route("user.login.verify-otp") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ email: email, otp: otp })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            clearInterval(timerInterval);
            showAlert('Login successful! Redirecting…', 'success');
            window.location.href = data.redirect || '{{ route("user.dashboard") }}';
        } else {
            errEl.textContent = data.message || 'Invalid OTP.';
            errEl.classList.add('show');
            document.getElementById('otpInput').value = '';
            document.getElementById('otpInput').focus();
        }
    })
    .catch(function() { showAlert('Network error. Please try again.', 'error'); })
    .finally(function() { btn.classList.remove('loading'); btn.disabled = false; });
}

/* ── RESEND OTP ── */
function resendOtp() {
    document.getElementById('otpInput').value = '';
    document.getElementById('resendBtn').style.display = 'none';
    timerSeconds = 600;
    sendOtpSilent();
}

function sendOtpSilent() {
    var email = document.getElementById('loginEmail').value.trim();
    fetch('{{ route("user.login.send-otp") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ email: email })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showAlert('New OTP sent!', 'success');
            startTimer();
        } else {
            showAlert(data.message || 'Could not resend OTP.', 'error');
        }
    })
    .catch(function() { showAlert('Network error.', 'error'); });
}

/* ── BACK TO EMAIL ── */
function backToEmail() {
    clearInterval(timerInterval);
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = '';
    document.getElementById('otpInput').value = '';
    document.getElementById('otpErr').classList.remove('show');
    clearAlert();
}

/* ── TIMER ── */
function startTimer() {
    clearInterval(timerInterval);
    timerSeconds = 600;
    document.getElementById('otpTimerWrap').style.display = '';
    document.getElementById('resendBtn').style.display = 'none';
    timerInterval = setInterval(function() {
        timerSeconds--;
        var m = Math.floor(timerSeconds / 60);
        var s = timerSeconds % 60;
        document.getElementById('otpTimer').textContent = m + ':' + (s < 10 ? '0' : '') + s;
        if (timerSeconds <= 0) {
            clearInterval(timerInterval);
            document.getElementById('otpTimerWrap').style.display = 'none';
            document.getElementById('resendBtn').style.display = 'inline';
        }
    }, 1000);
}

/* ── AUTO SUBMIT ON 4 DIGITS ── */
document.getElementById('otpInput').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, ''); // digits only
    if (this.value.length === 4) verifyOtp();
});

/* ── ENTER KEY on email ── */
document.getElementById('loginEmail').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') sendOtp();
});

/* ── ALERT HELPERS ── */
function showAlert(msg, type) {
    var el = document.getElementById('loginAlert');
    document.getElementById('loginAlertMsg').textContent = msg;
    el.className = 'qa-auth-alert show ' + type;
}
function clearAlert() {
    document.getElementById('loginAlert').className = 'qa-auth-alert';
}
</script>
@endsection