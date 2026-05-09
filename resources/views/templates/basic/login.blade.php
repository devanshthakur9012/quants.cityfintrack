@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   PAGE WRAPPER — peach/cream bg
========================================= */
.ql-page {
    min-height: 100vh;
    background: #FDECC8;
    display: flex; align-items: center; justify-content: center;
    padding: 40px 20px;
    font-family: 'Exo 2', sans-serif;
}
.ql-page * { box-sizing: border-box; }

/* =========================================
   MAIN CARD
========================================= */
.ql-card {
    background: #F0EFED;
    border-radius: 24px;
    width: 100%; max-width: 1120px;
    display: grid; grid-template-columns: 420px 1fr;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(0,0,0,.14);
    min-height: 600px;
}
@media(max-width:860px){
    .ql-card { grid-template-columns:1fr; max-width:480px; }
}

/* =========================================
   LEFT — FORM PANEL
========================================= */
.ql-left {
    background: #F0EFED;
    padding: 52px 48px 40px;
    display: flex; flex-direction: column;
    border-right: 1px solid rgba(0,0,0,.08);
}
@media(max-width:860px){
    .ql-left { border-right:none; border-bottom:1px solid rgba(0,0,0,.08); padding:36px 28px; }
}

/* logo */
.ql-logo { display:flex; align-items:center; gap:10px; margin-bottom:32px; }
.ql-logo-icon {
    width:48px; height:48px; border-radius:10px; background:#F5A623;
    display:flex; align-items:center; justify-content:center;
    font-size:22px; font-weight:700; color:#fff;
    font-family:'Rajdhani',sans-serif; flex-shrink:0;
}
.ql-logo-name { font-family:'Rajdhani',sans-serif; font-size:19px; font-weight:700; color:#1a1a1a; }
.ql-logo-sub  { font-size:11px; color:#888; letter-spacing:.03em; }

/* heading */
.ql-heading {
    font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700;
    color:#1a1a1a; margin:0 0 26px; line-height:1;
}

/* social quick-login row */
.ql-social-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:22px; }
.ql-social-btn {
    display:flex; align-items:center; gap:10px; padding:13px 16px;
    border-radius:10px; border:1px solid #ddd; background:#fff;
    cursor:pointer; transition:all .2s; text-align:left;
}
.ql-social-btn:hover { border-color:#F5A623; box-shadow:0 4px 12px rgba(245,166,35,.15); }
.ql-social-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.ql-social-icon.wa  { background:#25D366; color:#fff; }
.ql-social-icon.mob { background:#1a1a2e; color:#fff; }
.ql-social-label { font-size:14px; font-weight:700; color:#1a1a1a; line-height:1.1; }
.ql-social-sub   { font-size:11px; color:#888; }

/* divider */
.ql-divider {
    display:flex; align-items:center; gap:14px;
    margin-bottom:22px; color:#aaa; font-size:13px;
}
.ql-divider::before,.ql-divider::after { content:''; flex:1; height:1px; background:#ddd; }

/* phone row */
.ql-phone-row { display:flex; gap:8px; margin-bottom:18px; }
.ql-country-btn {
    display:flex; align-items:center; gap:6px; padding:0 14px; height:52px;
    border-radius:10px; border:1.5px solid #ddd; background:#fff;
    font-size:14px; font-weight:600; color:#1a1a1a; cursor:pointer;
    white-space:nowrap; flex-shrink:0; transition:border-color .2s;
}
.ql-country-btn:hover { border-color:#F5A623; }
.ql-phone-input {
    flex:1; height:52px; padding:0 16px; border:1.5px solid #ddd;
    border-radius:10px; font-size:15px; color:#1a1a1a; background:#fff;
    font-family:'Exo 2',sans-serif; outline:none; transition:border-color .2s;
}
.ql-phone-input:focus { border-color:#F5A623; }
.ql-phone-input::placeholder { color:#bbb; }

/* CTA button */
.ql-cta-btn {
    width:100%; height:52px; border:none; border-radius:10px;
    background:#F5A623; color:#fff; font-family:'Rajdhani',sans-serif;
    font-size:18px; font-weight:700; letter-spacing:.06em;
    cursor:pointer; transition:background .2s, transform .15s; margin-bottom:12px;
}
.ql-cta-btn:hover  { background:#d4890e; }
.ql-cta-btn:active { transform:scale(.98); }

/* text links */
.ql-text-link { font-size:13px; color:#888; }
.ql-text-link a { color:#888; text-decoration:none; }
.ql-text-link a:hover { color:#F5A623; }

/* generic input */
.ql-input-group { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
.ql-input-label { font-size:11px; color:#999; font-weight:700; letter-spacing:.05em; }
.ql-input-field {
    height:52px; padding:0 16px; width:100%; border:1.5px solid #ddd;
    border-radius:10px; font-size:15px; color:#1a1a1a; background:#fff;
    font-family:'Exo 2',sans-serif; outline:none; transition:border-color .2s;
}
.ql-input-field:focus { border-color:#F5A623; }
.ql-input-field::placeholder { color:#bbb; }

/* OTP boxes */
.ql-otp-row { display:flex; gap:9px; margin-bottom:16px; }
.ql-otp-box {
    flex:1; height:56px; text-align:center; border:1.5px solid #ddd;
    border-radius:10px; font-size:22px; font-weight:700; color:#1a1a1a;
    background:#fff; outline:none; transition:border-color .2s;
    font-family:'Rajdhani',sans-serif;
}
.ql-otp-box:focus { border-color:#F5A623; }

/* back link */
.ql-back { display:inline-flex; align-items:center; gap:6px; font-size:13px; color:#888; cursor:pointer; margin-bottom:18px; transition:color .2s; }
.ql-back:hover { color:#F5A623; }

/* separator */
.ql-sep { height:1px; background:#ddd; margin:18px 0; }

/* T&C */
.ql-tnc { display:flex; align-items:flex-start; gap:10px; font-size:12px; color:#666; line-height:1.55; }
.ql-tnc-cb {
    width:16px; height:16px; border-radius:3px; background:#F5A623; border:none;
    flex-shrink:0; margin-top:2px; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
}
.ql-tnc-cb i { color:#fff; font-size:10px; }
.ql-tnc a { color:#F5A623; text-decoration:underline; }

/* step visibility */
.ql-step { display:none; }
.ql-step.active { display:block; }

/* =========================================
   RIGHT — PROMO PANEL
========================================= */
.ql-right {
    background:#F0EFED; padding:40px 40px 36px;
    display:flex; flex-direction:column; gap:0;width:35%;
}
@media(max-width:860px){ .ql-right { padding:28px 22px; } }

.ql-right-title {
    font-family:'Rajdhani',sans-serif;
    font-size:clamp(20px,2.3vw,28px); font-weight:700;
    color:#1a1a1a; margin:0 0 20px; text-align:center; line-height:1.2;
}
.ql-right-title span { color:#F5A623; }

/* video */
.ql-video-wrap {
    border-radius:14px; overflow:hidden; margin-bottom:18px;
    background:#000; position:relative; aspect-ratio:16/9;
    box-shadow:0 8px 32px rgba(0,0,0,.2);
}
.ql-video-wrap iframe { width:100%; height:100%; display:block; border:none; }
.ql-video-logo {
    position:absolute; top:12px; right:14px;
    width:32px; height:32px; border-radius:8px; background:#F5A623;
    display:flex; align-items:center; justify-content:center;
    font-size:14px; font-weight:700; color:#fff;
    font-family:'Rajdhani',sans-serif; pointer-events:none;
}

/* feature pills */
.ql-features { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-bottom:20px; }
.ql-feat-pill {
    background:rgba(0,0,0,.08); border-radius:30px;
    padding:8px 16px; font-size:13px; font-weight:600; color:#333; white-space:nowrap;
}

/* trade with */
.ql-trade-label {
    display:flex; align-items:center; gap:12px; margin-bottom:14px;
}
.ql-trade-line { flex:1; height:1.5px; background:#F5A623; border-radius:2px; }
.ql-trade-text { font-size:14px; font-weight:600; color:#333; white-space:nowrap; }

/* broker scroll */
.ql-brokers-wrap { overflow:hidden; }
.ql-brokers-track {
    display:flex; gap:18px;
    animation:brokerScroll 24s linear infinite;
    width:max-content;
}
.ql-brokers-wrap:hover .ql-brokers-track { animation-play-state:paused; }
@keyframes brokerScroll {
    from { transform:translateX(0); }
    to   { transform:translateX(-50%); }
}
.ql-broker { display:flex; flex-direction:column; align-items:center; gap:5px; flex-shrink:0; }
.ql-broker-logo {
    width:50px; height:50px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; font-weight:700; color:#fff;
    font-family:'Rajdhani',sans-serif;
    border:2px solid rgba(255,255,255,.3);
}
.ql-broker-name { font-size:11px; color:#555; font-weight:500; text-align:center; }
</style>

<div class="ql-page">
<div class="ql-card">

    {{-- ══════════════════════════════
         LEFT — FORM
    ══════════════════════════════ --}}
    <div class="ql-left">

        {{-- Logo --}}
        <div class="ql-logo">
            <div class="ql-logo-icon">Q</div>
            <div>
                <div class="ql-logo-name">CityQuants<sup style="font-size:9px">®</sup></div>
                <div class="ql-logo-sub">Optimize Opportunities</div>
            </div>
        </div>

        <h1 class="ql-heading">Log in</h1>

        {{-- Quick login buttons --}}
        <div class="ql-social-row">
            <button class="ql-social-btn">
                <div class="ql-social-icon wa"><i class="fab fa-whatsapp"></i></div>
                <div>
                    <div class="ql-social-label">Whatsapp</div>
                    <div class="ql-social-sub">All Countries</div>
                </div>
            </button>
            <button class="ql-social-btn">
                <div class="ql-social-icon mob"><i class="fas fa-qrcode"></i></div>
                <div>
                    <div class="ql-social-label">Mobile App</div>
                    <div class="ql-social-sub">&nbsp;</div>
                </div>
            </button>
        </div>

        <div class="ql-divider">or</div>

        {{-- ── STEP 1: Phone + Get OTP ── --}}
        <div class="ql-step active" id="stepPhone">
            <div class="ql-phone-row">
                <button class="ql-country-btn">
                    <span style="font-size:18px">🇮🇳</span>
                    <span>91</span>
                    <i class="fas fa-chevron-down" style="font-size:10px;color:#bbb;"></i>
                </button>
                <input class="ql-phone-input" type="tel" id="mobileInput"
                       placeholder="Enter Mobile" maxlength="10"
                       oninput="this.value=this.value.replace(/\D/g,'')">
            </div>

            <button class="ql-cta-btn" onclick="sendOtp()">Get OTP</button>
            <div class="ql-text-link" style="text-align:right;margin-bottom:18px;">
                <a href="#" onclick="showStep('stepPassword');return false;">Use Password</a>
            </div>
            <div class="ql-text-link" style="margin-bottom:22px;"><a href="#">Help?</a></div>

            <div class="ql-sep" style="margin-top:0;"></div>
            <div class="ql-tnc">
                <div class="ql-tnc-cb" id="tncCheck" onclick="toggleTnc(this)" data-on="1">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    By proceeding, you agree to <a href="#">T&amp;C</a><br>
                    I consent receiving messages via SMS, WhatsApp, RCS and others
                </div>
            </div>
        </div>

        {{-- ── STEP 2: OTP entry ── --}}
        <div class="ql-step" id="stepOtp">
            <div class="ql-back" onclick="showStep('stepPhone')">
                <i class="fas fa-arrow-left"></i> Back
            </div>
            <p style="font-size:14px;color:#555;margin-bottom:18px;">
                OTP sent to <strong id="sentTo">+91 ——</strong>
            </p>
            <div class="ql-otp-row">
                @for($i = 0; $i < 6; $i++)
                <input class="ql-otp-box" type="text" maxlength="1"
                       oninput="otpAdvance(this,{{ $i }})">
                @endfor
            </div>
            <div class="ql-text-link" style="margin-bottom:18px;">
                Didn't receive? <a href="#" onclick="resendOtp();return false;">Resend OTP</a>
            </div>
            <button class="ql-cta-btn" onclick="verifyOtp()">Verify &amp; Login</button>
            <div class="ql-sep"></div>
            <div class="ql-tnc">
                <div class="ql-tnc-cb" data-on="1"><i class="fas fa-check"></i></div>
                <div>By proceeding, you agree to <a href="#">T&amp;C</a></div>
            </div>
        </div>

        {{-- ── STEP 3: Password login ── --}}
        <div class="ql-step" id="stepPassword">
            <div class="ql-back" onclick="showStep('stepPhone')">
                <i class="fas fa-arrow-left"></i> Back
            </div>
            <div class="ql-phone-row" style="margin-bottom:14px;">
                <button class="ql-country-btn">
                    <span style="font-size:18px">🇮🇳</span><span>91</span>
                </button>
                <input class="ql-phone-input" type="tel" placeholder="Enter Mobile" maxlength="10"
                       oninput="this.value=this.value.replace(/\D/g,'')">
            </div>
            <div class="ql-input-group">
                <label class="ql-input-label">PASSWORD</label>
                <input class="ql-input-field" type="password" placeholder="Enter your password">
            </div>
            <button class="ql-cta-btn">Login</button>
            <div class="ql-text-link" style="text-align:right;margin-bottom:16px;">
                <a href="#" style="color:#F5A623;">Forgot Password?</a>
            </div>
            <div class="ql-sep" style="margin-top:0;"></div>
            <div class="ql-tnc">
                <div class="ql-tnc-cb" data-on="1"><i class="fas fa-check"></i></div>
                <div>By proceeding, you agree to <a href="#">T&amp;C</a></div>
            </div>
        </div>

    </div>{{-- /.ql-left --}}

    {{-- ══════════════════════════════
         RIGHT — PROMO
    ══════════════════════════════ --}}
    <div class="ql-right">

        <h2 class="ql-right-title">
            <span>Options Trading</span> Analytical Platform
        </h2>

        {{-- Promo video --}}
        <div class="ql-video-wrap">
            <iframe src="{{ $promoVideo }}"
                    allow="autoplay; encrypted-media" width="100%" height="400px" allowfullscreen>
            </iframe>
            <div class="ql-video-logo">Q</div>
        </div>

        {{-- Feature pills from controller --}}
        <div class="ql-features">
            @foreach($features as $feat)
                <div class="ql-feat-pill">{{ $feat }}</div>
            @endforeach
        </div>

        {{-- Trade With --}}
        <div class="ql-trade-label">
            <div class="ql-trade-line"></div>
            <div class="ql-trade-text">Trade With</div>
            <div class="ql-trade-line"></div>
        </div>

        {{-- Auto-scrolling broker strip (duplicated for infinite scroll) --}}
        <div class="ql-brokers-wrap">
            <div class="ql-brokers-track">
                @php $allBrokers = array_merge($brokers, $brokers); @endphp
                @foreach($allBrokers as $b)
                <div class="ql-broker">
                    <div class="ql-broker-logo" style="background:{{ $b['bg'] }};">
                        {{ $b['letter'] }}
                    </div>
                    <div class="ql-broker-name">{{ $b['name'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

    </div>{{-- /.ql-right --}}

</div>{{-- /.ql-card --}}
</div>{{-- /.ql-page --}}

<script>
/* ── Step switcher ── */
function showStep(id) {
    document.querySelectorAll('.ql-step').forEach(function(s) { s.classList.remove('active'); });
    document.getElementById(id).classList.add('active');
}

/* ── Send OTP ── */
function sendOtp() {
    var mob = document.getElementById('mobileInput').value.trim();
    if (mob.length < 10) { alert('Please enter a valid 10-digit mobile number.'); return; }
    document.getElementById('sentTo').textContent = '+91 ' + mob;
    showStep('stepOtp');
    document.querySelector('.ql-otp-box').focus();
}

/* ── OTP box auto-advance ── */
function otpAdvance(el, idx) {
    el.value = el.value.replace(/\D/g, '');
    if (el.value && idx < 5) {
        document.querySelectorAll('.ql-otp-box')[idx + 1].focus();
    }
}

/* ── Verify OTP ── */
function verifyOtp() {
    var boxes = document.querySelectorAll('.ql-otp-box');
    var otp   = Array.from(boxes).map(function(b) { return b.value; }).join('');
    if (otp.length < 6) { alert('Please enter the complete 6-digit OTP.'); return; }
    // Wire to your backend:
    document.querySelector('form#otpForm') && document.querySelector('form#otpForm').submit();
    // Or redirect:
    window.location.href = '{{ route("user.home") }}';
}

/* ── Resend OTP ── */
function resendOtp() {
    document.querySelectorAll('.ql-otp-box').forEach(function(b) { b.value = ''; });
    document.querySelector('.ql-otp-box').focus();
}

/* ── T&C toggle ── */
function toggleTnc(el) {
    if (el.dataset.on === '1') {
        el.dataset.on = '0';
        el.style.background = '#ccc';
        el.innerHTML = '';
    } else {
        el.dataset.on = '1';
        el.style.background = '#F5A623';
        el.innerHTML = '<i class="fas fa-check" style="color:#fff;font-size:10px;"></i>';
    }
}
</script>

@endsection