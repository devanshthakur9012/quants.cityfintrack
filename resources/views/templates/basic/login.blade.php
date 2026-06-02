{{-- FILE: resources/views/themes/{active_theme}/login.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — LOGIN PAGE  v2.0
   Dark terminal · TradingView-inspired
══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --l-bg:       #0B0E11;
    --l-surface:  #131722;
    --l-panel:    #1C2030;
    --l-border:   rgba(255,255,255,.07);
    --l-border2:  rgba(255,255,255,.12);
    --l-lime:     #7DFF00;
    --l-lime-dim: rgba(125,255,0,.1);
    --l-lime-glo: rgba(125,255,0,.18);
    --l-text:     #D1D4DC;
    --l-muted:    #787B86;
    --l-red:      #EF5350;
    --l-green:    #26A69A;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cq-login-page {
    font-family: var(--f-sans);
    min-height: 100vh;
    background: var(--l-bg);
    color: var(--l-text);
    display: flex;
    align-items: stretch;
    position: relative;
    overflow: hidden;
}
.cq-login-page * { box-sizing: border-box; }
.cq-login-page a { text-decoration: none; color: inherit; }

/* ── BACKGROUND FX ────────────────────────── */
.cq-login-grid {
    position: fixed; inset: 0; z-index: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.025) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 90% 90% at 50% 50%, black, transparent);
    pointer-events: none;
}
.cq-login-orb1 {
    position: fixed; z-index: 0;
    width: 500px; height: 500px; border-radius: 50%;
    background: radial-gradient(circle, rgba(125,255,0,.06) 0%, transparent 70%);
    top: -100px; right: -100px; pointer-events: none;
}
.cq-login-orb2 {
    position: fixed; z-index: 0;
    width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(0,184,212,.04) 0%, transparent 70%);
    bottom: -80px; left: -80px; pointer-events: none;
}

/* ── LAYOUT ───────────────────────────────── */
.cq-login-inner {
    position: relative; z-index: 1;
    width: 100%; max-width: 1240px;
    margin: 0 auto; padding: 40px 24px;
    display: flex; align-items: center;
    gap: 56px; flex-wrap: wrap;
    min-height: 100vh;
}

/* ── LEFT — CARD ──────────────────────────── */
.cq-login-card {
    width: 420px; flex-shrink: 0;
    background: var(--l-surface);
    border: 1px solid var(--l-border);
    border-radius: 14px;
    padding: 36px 32px 32px;
    box-shadow: 0 24px 64px rgba(0,0,0,.5), 0 0 0 1px rgba(125,255,0,.04);
    position: relative; overflow: hidden;
}
/* Top green line */
.cq-login-card::before {
    content: '';
    position: absolute; top: 0; left: 24px; right: 24px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--l-lime), transparent);
    opacity: .6;
}

/* ── CARD LOGO ────────────────────────────── */
.cq-login-logo {
    display: flex; align-items: center; gap: 11px; margin-bottom: 28px;justify-content:center;
}
.cq-login-logo img {
    height: 50px; width: auto; display: block;
}
.cq-login-logo-text {}
.cq-login-logo-name {
    font-family: var(--f-display); font-size: 15px;
    font-weight: 700; color: #fff; line-height: 1;
}
.cq-login-logo-sub {
    font-size: 10px; color: var(--l-muted);
    letter-spacing: .06em; text-transform: uppercase; margin-top: 2px;
}

/* ── STEP INDICATOR ───────────────────────── */
.cq-step-row {
    display: flex; gap: 5px; margin-bottom: 22px;
}
.cq-step-seg {
    height: 2px; flex: 1; border-radius: 2px;
    background: var(--l-border2);
    transition: background .35s;
}
.cq-step-seg.done { background: var(--l-lime); }
.cq-step-seg.active {
    background: linear-gradient(90deg, var(--l-lime), rgba(125,255,0,.3));
}

/* ── HEADINGS ─────────────────────────────── */
.cq-login-h2 {
    font-family: var(--f-display);
    font-size: 26px; font-weight: 700; color: #fff;
    margin-bottom: 6px; line-height: 1.15;
}
.cq-login-sub {
    font-size: 13px; color: var(--l-muted);
    margin-bottom: 24px; line-height: 1.65;
}
.cq-login-sub strong { color: var(--l-text); }

/* ── ALERT ────────────────────────────────── */
.cq-alert {
    display: none; align-items: flex-start; gap: 10px;
    padding: 11px 14px; border-radius: 8px;
    font-size: 13px; margin-bottom: 18px; line-height: 1.5;
}
.cq-alert.show { display: flex; }
.cq-alert.success {
    background: rgba(38,166,154,.1);
    border: 1px solid rgba(38,166,154,.25);
    color: #4DB6AC;
}
.cq-alert.error {
    background: rgba(239,83,80,.1);
    border: 1px solid rgba(239,83,80,.25);
    color: #EF9A9A;
}
.cq-alert i { margin-top: 1px; flex-shrink: 0; }

/* ── FORM ELEMENTS ────────────────────────── */
.cq-form-group { margin-bottom: 16px; }
.cq-form-label {
    display: block; font-size: 11px; font-weight: 600;
    letter-spacing: .1em; text-transform: uppercase;
    color: var(--l-muted); margin-bottom: 8px;
}
.cq-form-input {
    width: 100%; padding: 12px 14px;
    background: var(--l-panel);
    border: 1px solid var(--l-border2);
    border-radius: 8px;
    font-size: 14px; color: #fff;
    font-family: var(--f-sans);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.cq-form-input::placeholder { color: var(--l-muted); }
.cq-form-input:focus {
    border-color: rgba(125,255,0,.45);
    box-shadow: 0 0 0 3px rgba(125,255,0,.08);
}
.cq-form-err {
    font-size: 12px; color: #EF9A9A;
    margin-top: 6px; display: none;
}
.cq-form-err.show { display: block; }

/* ── OTP INPUT ────────────────────────────── */
.cq-otp-input {
    width: 100%; padding: 14px;
    background: var(--l-panel);
    border: 1px solid var(--l-border2);
    border-radius: 8px;
    font-family: var(--f-mono);
    font-size: 32px; font-weight: 600;
    letter-spacing: .5em; text-align: center;
    color: #fff; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.cq-otp-input:focus {
    border-color: rgba(125,255,0,.45);
    box-shadow: 0 0 0 3px rgba(125,255,0,.08);
}
.cq-otp-input::placeholder { color: rgba(255,255,255,.15); letter-spacing: .4em; }

/* ── TIMER ────────────────────────────────── */
.cq-otp-timer {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    font-family: var(--f-mono); font-size: 12px;
    color: var(--l-muted); margin-top: 10px;
}
.cq-otp-timer-val {
    font-weight: 600;
    color: var(--l-lime);
    background: var(--l-lime-dim);
    padding: 2px 8px; border-radius: 4px;
}
.cq-otp-timer-val.warn { color: var(--l-red); background: rgba(239,83,80,.1); }

/* ── SUBMIT BUTTON ────────────────────────── */
.cq-submit-btn {
    width: 100%; padding: 13px;
    background: var(--l-lime); color: #000;
    font-family: var(--f-display);
    font-size: 15px; font-weight: 700; letter-spacing: .05em;
    border: none; border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: background .2s, box-shadow .2s, transform .15s;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
    margin-top: 6px;
}
.cq-submit-btn:hover:not(:disabled) {
    background: #8FFF1A;
    box-shadow: 0 0 30px rgba(125,255,0,.35);
    transform: translateY(-1px);
}
.cq-submit-btn:disabled { opacity: .55; cursor: not-allowed; }

/* Spinner inside button */
@keyframes cqSpin { to { transform: rotate(360deg); } }
.cq-spinner {
    width: 15px; height: 15px;
    border: 2px solid rgba(0,0,0,.2);
    border-top-color: #000;
    border-radius: 50%;
    animation: cqSpin .65s linear infinite;
    display: none; flex-shrink: 0;
}
.cq-submit-btn.loading .cq-spinner { display: block; }
.cq-submit-btn.loading .cq-btn-txt { display: none; }

/* ── LINK BUTTON ──────────────────────────── */
.cq-link-btn {
    background: none; border: none; padding: 0;
    font-family: var(--f-sans); font-size: 13px;
    font-weight: 600; color: var(--l-lime); cursor: pointer;
    transition: color .2s;
}
.cq-link-btn:hover { color: #8FFF1A; }
.cq-link-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ── DIVIDER ──────────────────────────────── */
.cq-login-divider {
    display: flex; align-items: center; gap: 12px;
    margin: 20px 0; font-size: 12px; color: var(--l-muted);
}
.cq-login-divider::before,
.cq-login-divider::after {
    content: ''; flex: 1; height: 1px;
    background: var(--l-border);
}

/* ── RIGHT — PROMO ────────────────────────── */
.cq-login-promo {
    flex: 1; min-width: 0;
    display: flex; flex-direction: column; gap: 28px;
}

@keyframes cqFadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: none; }
}
.cq-promo-anim   { animation: cqFadeUp .6s ease both; }
.cq-promo-anim.d1 { animation-delay: .1s; }
.cq-promo-anim.d2 { animation-delay: .2s; }
.cq-promo-anim.d3 { animation-delay: .3s; }
.cq-promo-anim.d4 { animation-delay: .4s; }

/* Promo heading */
.cq-promo-heading {
    font-family: var(--f-display);
    font-size: clamp(26px, 3vw, 40px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em;
}
.cq-promo-heading span { color: var(--l-lime); }

.cq-promo-sub {
    font-size: 14px; color: var(--l-muted);
    line-height: 1.7; margin-top: 10px; max-width: 440px;
}

/* Video card */
.cq-promo-vid-wrap {
    border-radius: 12px; overflow: hidden;
    border: 1px solid var(--l-border);
    box-shadow: 0 16px 56px rgba(0,0,0,.55),
                0 0 0 1px rgba(125,255,0,.04);
    aspect-ratio: 16/9;
    background: var(--l-panel);
    position: relative;
}
.cq-promo-vid-wrap::before {
    content: '';
    position: absolute; top: 0; left: 20px; right: 20px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--l-lime), transparent);
    opacity: .4; z-index: 1;
}
.cq-promo-vid-wrap iframe {
    width: 100%; height: 100%; border: none; display: block;
}
/* Corner brackets */
.cq-vid-corner { position: absolute; width: 16px; height: 16px; border-color: var(--l-lime); border-style: solid; z-index: 2; }
.cq-vid-corner.tl { top: -1px; left: -1px; border-width: 2px 0 0 2px; }
.cq-vid-corner.tr { top: -1px; right: -1px; border-width: 2px 2px 0 0; }
.cq-vid-corner.bl { bottom: -1px; left: -1px; border-width: 0 0 2px 2px; }
.cq-vid-corner.br { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; }

/* Feature pills */
.cq-promo-features { display: flex; flex-wrap: wrap; gap: 8px; }
.cq-feature-pill {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--l-surface);
    border: 1px solid var(--l-border2);
    border-radius: 100px;
    padding: 7px 16px;
    font-size: 13px; font-weight: 500; color: var(--l-text);
    transition: border-color .2s, color .2s;
}
.cq-feature-pill:hover { border-color: rgba(125,255,0,.25); color: #fff; }
.cq-feature-pill i { font-size: 11px; color: var(--l-lime); }

/* Brokers row */
.cq-brokers-label {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: var(--l-muted);
    margin-bottom: 12px;
}
.cq-brokers-row {
    display: flex; flex-wrap: nowrap; gap: 8px;
    overflow-x: auto; padding-bottom: 4px;
}
.cq-brokers-row::-webkit-scrollbar { height: 2px; }
.cq-brokers-row::-webkit-scrollbar-thumb { background: var(--l-border2); border-radius: 2px; }
.cq-broker-pill {
    display: inline-flex; align-items: center; gap: 7px;
    flex-shrink: 0; padding: 7px 14px;
    background: var(--l-surface);
    border: 1px solid var(--l-border);
    border-radius: 100px;
    font-size: 12px; font-weight: 600; color: var(--l-text);
    white-space: nowrap; transition: border-color .2s;
}
.cq-broker-pill:hover { border-color: var(--l-border2); }
.cq-broker-letter {
    width: 20px; height: 20px; border-radius: 5px;
    font-size: 10px; font-weight: 700; color: #fff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ── RESPONSIVE ───────────────────────────── */
@media (max-width: 920px) {
    .cq-login-inner {
        flex-direction: column-reverse;
        padding: 32px 20px 48px;
        min-height: auto; gap: 36px;
    }
    .cq-login-card { width: 100%; max-width: 460px; margin: 0 auto; }
    .cq-login-promo { width: 100%; }
    .cq-promo-heading { font-size: 28px; }
}
@media (max-width: 480px) {
    .cq-login-card { padding: 28px 20px 24px; }
}
</style>

<div class="cq-login-page">
    <div class="cq-login-grid"></div>
    <div class="cq-login-orb1"></div>
    <div class="cq-login-orb2"></div>

    <div class="cq-login-inner">

        {{-- ══════════════ LEFT — AUTH CARD ══════════════ --}}
        <div class="cq-login-card">

            {{-- Logo --}}
            <div class="cq-login-logo">
                <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}" alt="CityQuants">
            </div>

            {{-- Step progress bar --}}
            <div class="cq-step-row" id="cqStepRow">
                <div class="cq-step-seg active" id="seg0"></div>
                <div class="cq-step-seg"         id="seg1"></div>
            </div>

            {{-- Alert --}}
            <div class="cq-alert" id="cqAlert">
                <i class="fas fa-info-circle"></i>
                <span id="cqAlertMsg"></span>
            </div>

            {{-- ── STEP 1 : Email ── --}}
            <div id="step1">
                <h2 class="cq-login-h2">{{ $loginHeading ?? 'Welcome Back' }}</h2>
                <p class="cq-login-sub">
                    @if(!empty($loginSubheading))
                        {{ $loginSubheading }}
                    @else
                        Enter your email and we'll send you a one-time passcode to sign in instantly.
                    @endif
                </p>

                <div class="cq-form-group">
                    <label class="cq-form-label" for="loginEmail">Email Address</label>
                    <input type="email" id="loginEmail" class="cq-form-input"
                           placeholder="you@example.com" autocomplete="email">
                    <div class="cq-form-err" id="emailErr">Please enter a valid email address.</div>
                </div>

                <button class="cq-submit-btn" id="sendOtpBtn" onclick="sendOtp()">
                    <span class="cq-spinner"></span>
                    <span class="cq-btn-txt">
                        <i class="fas fa-paper-plane" style="margin-right:6px;font-size:13px;"></i>Send OTP
                    </span>
                </button>

                <div class="cq-login-divider">or</div>

                <p style="font-size:12px;color:var(--l-muted);text-align:center;line-height:1.65;">
                    A 4-digit OTP will be sent to your email.<br>
                    No account? One is created automatically.
                </p>
            </div>

            {{-- ── STEP 2 : OTP ── --}}
            <div id="step2" style="display:none;">
                <h2 class="cq-login-h2">Check Your Inbox</h2>
                <p class="cq-login-sub">
                    OTP sent to <strong id="emailDisplay"></strong>.<br>
                    Enter the 4-digit code below to continue.
                </p>

                <div class="cq-form-group">
                    <label class="cq-form-label" for="otpInput">One-Time Passcode</label>
                    <input type="text" id="otpInput" class="cq-otp-input"
                           maxlength="4" inputmode="numeric"
                           placeholder="· · · ·" autocomplete="one-time-code">
                    <div class="cq-form-err" id="otpErr">Invalid OTP. Please try again.</div>
                </div>

                <div class="cq-otp-timer" id="timerWrap">
                    <i class="far fa-clock" style="font-size:11px;"></i>
                    Expires in <span class="cq-otp-timer-val" id="timerVal">10:00</span>
                </div>

                <button class="cq-submit-btn" id="verifyBtn" onclick="verifyOtp()" style="margin-top:16px;">
                    <span class="cq-spinner"></span>
                    <span class="cq-btn-txt">
                        <i class="fas fa-unlock-alt" style="margin-right:6px;font-size:13px;"></i>Verify &amp; Sign In
                    </span>
                </button>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;">
                    <button class="cq-link-btn" onclick="backToEmail()">
                        <i class="fas fa-arrow-left" style="font-size:11px;margin-right:4px;"></i>Change Email
                    </button>
                    <button class="cq-link-btn" id="resendBtn" onclick="resendOtp()" style="display:none;">
                        <i class="fas fa-redo" style="font-size:11px;margin-right:4px;"></i>Resend OTP
                    </button>
                </div>
            </div>

        </div>{{-- /card --}}


        {{-- ══════════════ RIGHT — PROMO ══════════════ --}}
        <div class="cq-login-promo">

            <div class="cq-promo-anim">
                <h1 class="cq-promo-heading">
                    Options <br> <span>Decoded Instantly.</span>
                </h1>
            </div>

            @if(!empty($promoVideo))
            <div class="cq-promo-vid-wrap cq-promo-anim d1">
                <div class="cq-vid-corner tl"></div>
                <div class="cq-vid-corner tr"></div>
                <div class="cq-vid-corner bl"></div>
                <div class="cq-vid-corner br"></div>
                <iframe src="{{ $promoVideo }}"
                        allow="autoplay; encrypted-media" allowfullscreen>
                </iframe>
            </div>
            @endif

            @if(!empty($features))
            <div class="cq-promo-features cq-promo-anim d2">
                @foreach($features as $feature)
                <div class="cq-feature-pill">
                    <i class="fas fa-check-circle"></i>
                    {{ $feature }}
                </div>
                @endforeach
            </div>
            @endif

            {{-- @if(!empty($brokers))
            <div class="cq-promo-anim d3">
                <div class="cq-brokers-label">Integrated Brokers</div>
                <div class="cq-brokers-row">
                    @foreach($brokers as $broker)
                    <div class="cq-broker-pill">
                        <div class="cq-broker-letter" style="background:{{ $broker['bg'] ?? '#455a64' }}">
                            {{ $broker['letter'] ?? strtoupper(substr($broker['name'],0,1)) }}
                        </div>
                        {{ $broker['name'] }}
                    </div>
                    @endforeach
                </div>
            </div>
            @endif --}}

        </div>{{-- /promo --}}

    </div>{{-- /inner --}}
</div>{{-- /page --}}

<script>
var timerInterval = null;
var timerSeconds  = 600;

/* ── STEP SEGMENTS ─────────────────── */
function setStep(n) {
    document.getElementById('seg0').className = 'cq-step-seg ' + (n >= 1 ? 'done' : 'active');
    document.getElementById('seg1').className = 'cq-step-seg ' + (n >= 2 ? 'done' : n === 1 ? 'active' : '');
}

/* ── SEND OTP ──────────────────────── */
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
    setLoading('sendOtpBtn', true);
    fetch('{{ route("user.login.send-otp") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ email: email })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data.success) {
            document.getElementById('emailDisplay').textContent = email;
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = '';
            document.getElementById('otpInput').focus();
            setStep(1);
            startTimer();
            showAlert('OTP sent! Check your inbox.', 'success');
        } else {
            showAlert(data.message || 'Something went wrong. Please try again.', 'error');
        }
    })
    .catch(function(){ showAlert('Network error. Please try again.', 'error'); })
    .finally(function(){ setLoading('sendOtpBtn', false); });
}

/* ── VERIFY OTP ────────────────────── */
function verifyOtp() {
    clearAlert();
    var otp   = document.getElementById('otpInput').value.trim();
    var email = document.getElementById('loginEmail').value.trim();
    var errEl = document.getElementById('otpErr');
    errEl.classList.remove('show');
    if (!otp || otp.length !== 4) {
        errEl.textContent = 'Please enter the 4-digit OTP.';
        errEl.classList.add('show');
        return;
    }
    setLoading('verifyBtn', true);
    fetch('{{ route("user.login.verify-otp") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ email: email, otp: otp })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data.success) {
            clearInterval(timerInterval);
            setStep(2);
            showAlert('Verified! Redirecting…', 'success');
            setTimeout(function(){
                window.location.href = data.redirect || '{{ route("user.dashboard") }}';
            }, 500);
        } else {
            errEl.textContent = data.message || 'Invalid OTP. Please try again.';
            errEl.classList.add('show');
            document.getElementById('otpInput').value = '';
            document.getElementById('otpInput').focus();
        }
    })
    .catch(function(){ showAlert('Network error. Please try again.', 'error'); })
    .finally(function(){ setLoading('verifyBtn', false); });
}

/* ── RESEND ────────────────────────── */
function resendOtp() {
    document.getElementById('otpInput').value = '';
    document.getElementById('resendBtn').style.display = 'none';
    timerSeconds = 600;
    var email = document.getElementById('loginEmail').value.trim();
    fetch('{{ route("user.login.send-otp") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ email: email })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data.success) { showAlert('New OTP sent!', 'success'); startTimer(); }
        else { showAlert(data.message || 'Could not resend OTP.', 'error'); }
    })
    .catch(function(){ showAlert('Network error.', 'error'); });
}

/* ── BACK ──────────────────────────── */
function backToEmail() {
    clearInterval(timerInterval);
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = '';
    document.getElementById('otpInput').value = '';
    document.getElementById('otpErr').classList.remove('show');
    setStep(0);
    clearAlert();
}

/* ── TIMER ─────────────────────────── */
function startTimer() {
    clearInterval(timerInterval);
    timerSeconds = 600;
    var wrap = document.getElementById('timerWrap');
    var val  = document.getElementById('timerVal');
    wrap.style.display = '';
    document.getElementById('resendBtn').style.display = 'none';
    timerInterval = setInterval(function(){
        timerSeconds--;
        var m = Math.floor(timerSeconds / 60);
        var s = timerSeconds % 60;
        val.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        val.className = 'cq-otp-timer-val' + (timerSeconds <= 60 ? ' warn' : '');
        if (timerSeconds <= 0) {
            clearInterval(timerInterval);
            wrap.style.display = 'none';
            document.getElementById('resendBtn').style.display = 'inline';
        }
    }, 1000);
}

/* ── HELPERS ───────────────────────── */
function setLoading(id, on) {
    var btn = document.getElementById(id);
    btn.classList.toggle('loading', on);
    btn.disabled = on;
}
function showAlert(msg, type) {
    var el = document.getElementById('cqAlert');
    document.getElementById('cqAlertMsg').textContent = msg;
    el.className = 'cq-alert show ' + type;
}
function clearAlert() {
    document.getElementById('cqAlert').className = 'cq-alert';
}

/* ── AUTO SUBMIT on 4 digits ────────── */
document.getElementById('otpInput').addEventListener('input', function(){
    this.value = this.value.replace(/\D/g, '');
    if (this.value.length === 4) verifyOtp();
});

/* ── ENTER KEY ──────────────────────── */
document.getElementById('loginEmail').addEventListener('keydown', function(e){
    if (e.key === 'Enter') sendOtp();
});
</script>

@endsection