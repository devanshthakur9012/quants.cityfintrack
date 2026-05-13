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

        <h1 class="ql-heading">Create Account</h1>

        {{-- ── STEP 1: Fill details ── --}}
        <div class="ql-step active" id="stepForm">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 14px;">
                <div class="ql-input-group">
                    <label class="ql-input-label">FIRST NAME</label>
                    <input class="ql-input-field" type="text" id="reg_firstname"
                           placeholder="John" autocomplete="given-name">
                    <div class="ql-input-error" id="err_firstname" style="display:none;"></div>
                </div>
                <div class="ql-input-group">
                    <label class="ql-input-label">LAST NAME</label>
                    <input class="ql-input-field" type="text" id="reg_lastname"
                           placeholder="Doe" autocomplete="family-name">
                    <div class="ql-input-error" id="err_lastname" style="display:none;"></div>
                </div>
            </div>

            <div class="ql-input-group">
                <label class="ql-input-label">EMAIL ADDRESS</label>
                <input class="ql-input-field" type="email" id="reg_email"
                       placeholder="you@example.com" autocomplete="email">
                <div class="ql-input-error" id="err_email" style="display:none;"></div>
            </div>

            <div class="ql-input-group">
                <label class="ql-input-label">MOBILE <span style="font-weight:400;color:#bbb;">(optional)</span></label>
                <input class="ql-input-field" type="tel" id="reg_mobile"
                       placeholder="10-digit number" maxlength="10"
                       oninput="this.value=this.value.replace(/\D/g,'')">
            </div>

            <div class="ql-tnc" style="margin-bottom:18px;">
                <div class="ql-tnc-cb" id="tncCb" onclick="toggleTnc()" data-on="0" style="background:#ccc;">
                </div>
                <div>
                    I agree to the <a href="#">Terms &amp; Conditions</a> and
                    <a href="#">Privacy Policy</a> of CityQuants
                </div>
            </div>

            <button class="ql-cta-btn" id="regBtn" onclick="doRegister()">
                Create Account
            </button>

            <div class="ql-sep"></div>
            <div style="text-align:center; font-size:13px; color:#888;">
                Already have an account?
                <a href="{{ route('user.login') }}" style="color:#F5A623; font-weight:600;">
                    Log In
                </a>
            </div>
        </div>

        {{-- ── STEP 2: Email sent confirmation ── --}}
        <div class="ql-step" id="stepDone">
            <div style="text-align:center; padding:20px 0;">
                <div style="width:70px;height:70px;border-radius:50%;background:#F5A623;
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 20px; font-size:30px; color:#fff;">
                    <i class="fas fa-envelope"></i>
                </div>
                <h2 style="font-family:'Rajdhani',sans-serif; font-size:24px; font-weight:700;
                            color:#1a1a1a; margin-bottom:12px;">Check Your Email</h2>
                <p style="font-size:14px; color:#666; margin-bottom:6px;">
                    We've sent a verification link to
                </p>
                <p style="font-size:16px; font-weight:700; color:#1a1a1a; margin-bottom:20px;" id="regEmailConfirm">—</p>
                <p style="font-size:13px; color:#888; line-height:1.6;">
                    Click the link in the email to verify your address and set your password.
                    The link expires in <strong>24 hours</strong>.
                </p>
                <div class="ql-sep"></div>
                <div style="font-size:13px; color:#888;">
                    Didn't get it? Check your spam folder or
                    <a href="{{ route('user.register') }}" style="color:#F5A623;">try again</a>.
                </div>
            </div>
        </div>

    </div>{{-- /.ql-left --}}

    @include($activeTemplate.'partials.auth-right')

</div>
</div>

<script>
var tncChecked = false;

function toggleTnc() {
    var cb = document.getElementById('tncCb');
    tncChecked = !tncChecked;
    cb.dataset.on = tncChecked ? '1' : '0';
    cb.style.background = tncChecked ? '#F5A623' : '#ccc';
    cb.innerHTML = tncChecked ? '<i class="fas fa-check" style="color:#fff;font-size:10px;"></i>' : '';
}

function doRegister() {
    var firstname = document.getElementById('reg_firstname').value.trim();
    var lastname  = document.getElementById('reg_lastname').value.trim();
    var email     = document.getElementById('reg_email').value.trim();
    var mobile    = document.getElementById('reg_mobile').value.trim();
    var btn       = document.getElementById('regBtn');
    var valid     = true;

    // Clear errors
    ['firstname','lastname','email'].forEach(function(f){
        document.getElementById('err_'+f).style.display = 'none';
    });

    if (!firstname) {
        document.getElementById('err_firstname').textContent = 'Required.';
        document.getElementById('err_firstname').style.display = 'block';
        valid = false;
    }
    if (!lastname) {
        document.getElementById('err_lastname').textContent = 'Required.';
        document.getElementById('err_lastname').style.display = 'block';
        valid = false;
    }
    if (!email || !/\S+@\S+\.\S+/.test(email)) {
        document.getElementById('err_email').textContent = 'Enter a valid email.';
        document.getElementById('err_email').style.display = 'block';
        valid = false;
    }
    if (!tncChecked) {
        qlToast('Please accept the Terms & Conditions.', 'error');
        valid = false;
    }
    if (!valid) return;

    qlLoading(btn, true);

    fetch('{{ route("user.register.store") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ firstname, lastname, email, mobile })
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        qlLoading(btn, false);
        if (data.success) {
            document.getElementById('regEmailConfirm').textContent = email;
            showStep('stepDone');
        } else {
            if (data.errors) {
                Object.keys(data.errors).forEach(function(key) {
                    var el = document.getElementById('err_' + key);
                    if (el) { el.textContent = data.errors[key][0]; el.style.display = 'block'; }
                });
            }
            qlToast(data.message || 'Registration failed.', 'error');
        }
    })
    .catch(function() {
        qlLoading(btn, false);
        qlToast('Something went wrong.', 'error');
    });
}
</script>

@endsection