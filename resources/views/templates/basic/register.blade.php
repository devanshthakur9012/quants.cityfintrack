{{-- FILE: resources/views/themes/{active_theme}/register.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ─── BASE ─────────────────────────────────────────────────────────────── */
.qa-auth-page {
    font-family: 'Exo 2', sans-serif;
    min-height: 100vh;
    background: #f5e6c8;
    display: flex;
    align-items: stretch;
}
.qa-auth-page * { box-sizing: border-box; }
.qa-auth-page h1,.qa-auth-page h2,.qa-auth-page h3 { font-family: 'Rajdhani', sans-serif; }
.qa-auth-page a { text-decoration: none; }
@keyframes authFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.qa-auth-anim    { animation: authFadeUp .5s ease both; }
.qa-auth-anim.d1 { animation-delay: .1s; }
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

/* ─── LEFT — REGISTER CARD ───────────────────────────────────────────────── */
.qa-auth-card {
    width: 420px;
    flex-shrink: 0;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 48px rgba(0,0,0,.10);
    padding: 40px 36px 32px;
}

/* Logo */
.qa-card-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
.qa-card-logo-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: #7DFF00; color: #fff; font-size: 20px; font-weight: 700;
    font-family: 'Rajdhani', sans-serif;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.qa-card-logo-text { line-height: 1.2; }
.qa-card-logo-name { font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700; color: #1a1a2e; }
.qa-card-logo-sub  { font-size: 12px; color: #999; }

/* Heading */
.qa-auth-card h2 { font-size: 28px; font-weight: 700; color: #1a1a2e; margin: 0 0 6px; line-height: 1.15; }
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
.qa-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.qa-form-group { margin-bottom: 14px; }
.qa-form-label {
    display: block; font-size: 11px; font-weight: 700; color: #999;
    text-transform: uppercase; letter-spacing: .07em; margin-bottom: 7px;
}
.qa-form-label .req { color: #e53935; }
.qa-form-input {
    width: 100%; padding: 12px 14px; border-radius: 9px;
    border: 1.5px solid #e5e9f2; font-size: 14px; color: #1a1a2e;
    font-family: 'Exo 2', sans-serif; outline: none;
    transition: border-color .2s, box-shadow .2s; background: #fff;
}
.qa-form-input:focus { border-color: #7DFF00; box-shadow: 0 0 0 3px rgba(245,166,35,.12); }

/* Submit */
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

/* Terms */
.qa-terms { font-size: 11.5px; color: #bbb; line-height: 1.6; margin-top: 12px; text-align: center; }
.qa-terms a { color: #7DFF00; }

/* Bottom */
.qa-auth-bottom { margin-top: 18px; text-align: center; font-size: 13px; color: #aab; }
.qa-auth-bottom a { color: #7DFF00; font-weight: 600; }
.qa-auth-bottom a:hover { color: #d4890e; }

/* Success */
.qa-reg-success { display: none; text-align: center; padding: 16px 0; }
.qa-reg-success i { font-size: 52px; color: #43a047; display: block; margin-bottom: 14px; }
.qa-reg-success h3 { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
.qa-reg-success p  { font-size: 13px; color: #888; line-height: 1.7; }

/* ─── RIGHT — PROMO PANEL ────────────────────────────────────────────────── */
.qa-auth-promo { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 28px; }
.qa-promo-heading {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(26px, 3vw, 38px); font-weight: 700;
    color: #1a1a2e; line-height: 1.2;
}
.qa-promo-heading span { color: #7DFF00; }
.qa-promo-video {
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 12px 48px rgba(0,0,0,.15);
    aspect-ratio: 16/9; background: #000;
    border: 1px solid rgba(0,0,0,.08);
}
.qa-promo-video iframe { width: 100%; height: 100%; display: block; border: none; }
.qa-promo-features { display: flex; flex-wrap: wrap; gap: 10px; }
.qa-promo-feature-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.75); border: 1px solid rgba(0,0,0,.08);
    border-radius: 30px; padding: 8px 18px;
    font-size: 13px; font-weight: 600; color: #1a1a2e;
    backdrop-filter: blur(4px);
}
.qa-promo-feature-pill i { color: #7DFF00; font-size: 12px; }
.qa-promo-brokers-divider {
    display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
}
.qa-promo-brokers-divider::before,.qa-promo-brokers-divider::after {
    content: ''; flex: 1; height: 1px; background: rgba(0,0,0,.1);
}
.qa-promo-brokers-label {
    font-size: 12px; color: #999; font-weight: 700;
    text-transform: uppercase; letter-spacing: .08em; white-space: nowrap;
}
.qa-promo-brokers { display: flex; flex-wrap: nowrap; gap: 10px; overflow-x: auto; padding-bottom: 4px; }
.qa-promo-brokers::-webkit-scrollbar { height: 3px; }
.qa-promo-brokers::-webkit-scrollbar-thumb { background: #e5e9f2; border-radius: 2px; }
.qa-promo-broker-pill {
    display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
    padding: 7px 14px; border-radius: 30px;
    background: rgba(255,255,255,.8); border: 1px solid rgba(0,0,0,.08);
    font-size: 12.5px; font-weight: 600; color: #333; white-space: nowrap;
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
    .qa-form-row   { grid-template-columns: 1fr; }
}
</style>

<div class="qa-auth-page">
    <div class="qa-auth-inner">

        {{-- ══════════════════════════════════════════════
             LEFT — REGISTER CARD
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

            {{-- Heading — dynamic --}}
            <h2>{{ $registerHeading ?? 'Create Account' }}</h2>
            @if(!empty($registerSubheading))
                <p class="qa-auth-card-sub">{{ $registerSubheading }}</p>
            @else
                <p class="qa-auth-card-sub">Join thousands of option traders. It's free.</p>
            @endif

            {{-- Alert --}}
            <div class="qa-auth-alert" id="regAlert">
                <i class="fas fa-info-circle"></i>
                <span id="regAlertMsg"></span>
            </div>

            {{-- ── FORM ── --}}
            <div id="regForm">
                <div class="qa-form-row">
                    <div class="qa-form-group">
                        <label class="qa-form-label">
                            First Name <span class="req">*</span>
                        </label>
                        <input type="text" id="regFirstname" class="qa-form-input"
                               placeholder="Ravi" autocomplete="given-name">
                    </div>
                    <div class="qa-form-group">
                        <label class="qa-form-label">
                            Last Name <span class="req">*</span>
                        </label>
                        <input type="text" id="regLastname" class="qa-form-input"
                               placeholder="Sharma" autocomplete="family-name">
                    </div>
                </div>
                <div class="qa-form-group">
                    <label class="qa-form-label">
                        Email Address <span class="req">*</span>
                    </label>
                    <input type="email" id="regEmail" class="qa-form-input"
                           placeholder="you@example.com" autocomplete="email">
                </div>
                <div class="qa-form-group">
                    <label class="qa-form-label">
                        Mobile <span style="color:#bbb;font-weight:400;text-transform:none;">(optional)</span>
                    </label>
                    <input type="tel" id="regMobile" class="qa-form-input"
                           placeholder="+91 9876543210" autocomplete="tel">
                </div>
                <button class="qa-auth-submit" onclick="submitRegister()" id="regBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Create Free Account</span>
                </button>
                <p class="qa-terms">
                    By signing up you agree to our
                    <a href="#">Terms</a> &amp; <a href="#">Privacy Policy</a>.
                </p>
            </div>

            {{-- ── SUCCESS ── --}}
            <div class="qa-reg-success" id="regSuccess">
                <i class="fas fa-envelope-open-text"></i>
                <h3>Check Your Email!</h3>
                <p>
                    We sent a verification link to
                    <strong id="regSuccessEmail"></strong>.<br>
                    Click it to verify your account and set your password.
                </p>
            </div>

            {{-- Bottom --}}
            <div class="qa-auth-bottom">
                Already have an account?
                <a href="{{ route('user.login') }}">Sign in</a>
            </div>

        </div>

        {{-- ══════════════════════════════════════════════
             RIGHT — PROMO PANEL
        ══════════════════════════════════════════════ --}}
        <div class="qa-auth-promo qa-auth-anim d1">

            <div class="qa-promo-heading">
                Options Trading <span>Analytical</span> Platform
            </div>

            {{-- Video --}}
            @if(!empty($promoVideo))
            <div class="qa-promo-video">
                <iframe src="{{ $promoVideo }}"
                        allow="autoplay; encrypted-media"
                        allowfullscreen>
                </iframe>
            </div>
            @endif

            {{-- Features --}}
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

            {{-- Brokers --}}
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
function submitRegister() {
    var firstname = document.getElementById('regFirstname').value.trim();
    var lastname  = document.getElementById('regLastname').value.trim();
    var email     = document.getElementById('regEmail').value.trim();
    var mobile    = document.getElementById('regMobile').value.trim();

    clearRegAlert();

    if (!firstname) { showRegAlert('Please enter your first name.', 'error'); return; }
    if (!lastname)  { showRegAlert('Please enter your last name.',  'error'); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showRegAlert('Please enter a valid email address.', 'error'); return;
    }

    var btn = document.getElementById('regBtn');
    btn.classList.add('loading'); btn.disabled = true;

    fetch('{{ route("user.register.store") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ firstname: firstname, lastname: lastname, email: email, mobile: mobile || null })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('regForm').style.display    = 'none';
            document.getElementById('regSuccessEmail').textContent = email;
            document.getElementById('regSuccess').style.display = 'block';
        } else {
            showRegAlert(data.message || 'Something went wrong.', 'error');
        }
    })
    .catch(function() { showRegAlert('Network error. Please try again.', 'error'); })
    .finally(function() { btn.classList.remove('loading'); btn.disabled = false; });
}

function showRegAlert(msg, type) {
    var el = document.getElementById('regAlert');
    document.getElementById('regAlertMsg').textContent = msg;
    el.className = 'qa-auth-alert show ' + type;
}
function clearRegAlert() {
    document.getElementById('regAlert').className = 'qa-auth-alert';
}

document.querySelectorAll('.qa-form-input').forEach(function(input) {
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') submitRegister();
    });
});
</script>
@endsection