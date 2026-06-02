{{-- FILE: resources/views/themes/{activeTemplate}/change-password.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — CHANGE PASSWORD  v2.0
   Dark terminal · Matches dashboard design system
══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --c-bg:       #0B0E11;
    --c-surface:  #131722;
    --c-panel:    #1C2030;
    --c-border:   rgba(255,255,255,.06);
    --c-border2:  rgba(255,255,255,.11);
    --c-lime:     #7DFF00;
    --c-lime-dim: rgba(125,255,0,.1);
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;
    --c-teal:     #26A69A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cpw { font-family: var(--f-sans); background: var(--c-bg); color: var(--c-text); min-height: 80vh; }
.cpw * { box-sizing: border-box; }
.cpw a { text-decoration: none; color: inherit; }

@keyframes cpwFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.cpw-anim    { animation: cpwFadeUp .45s ease both; }
.cpw-anim.d1 { animation-delay: .06s; }
.cpw-anim.d2 { animation-delay: .12s; }

/* ── PAGE HEADER ───────────────────────────── */
.cpw-header {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 44px 24px 36px;
    border-bottom: 1px solid var(--c-border);
}
.cpw-header::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 25% 50%, black, transparent);
    pointer-events: none;
}
.cpw-header::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 38% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.cpw-header-inner { position: relative; z-index: 1; max-width: 1000px; margin: 0 auto; }
.cpw-header h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 800; color: #fff; margin-bottom: 5px;
    display: flex; align-items: center; gap: 10px;
}
.cpw-header h1 i { color: var(--c-lime); font-size: 22px; }
.cpw-header p { font-size: 13px; color: var(--c-muted); }

/* ── LAYOUT ────────────────────────────────── */
.cpw-layout {
    max-width: 1000px; margin: 0 auto;
    padding: 28px 24px 80px;
    display: flex; gap: 22px; align-items: flex-start;
}
.cpw-sidebar { flex-shrink: 0; width: 240px; position: sticky; top: 96px; }
.cpw-main    { flex: 1; min-width: 0; max-width: 560px; }

/* ── SIDEBAR PROFILE BOX ───────────────────── */
.cpw-profile-box {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 22px 16px 16px;
    text-align: center; margin-bottom: 12px;
    position: relative; overflow: hidden;
}
.cpw-profile-box::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .5;
}
.cpw-profile-box::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.cpw-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    margin: 0 auto 12px; position: relative; z-index: 1;
    border: 2px solid var(--c-lime);
    background: var(--c-lime);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 22px; font-weight: 800; color: #000;
    overflow: hidden; box-shadow: 0 0 16px rgba(125,255,0,.2);
}
.cpw-avatar img { width: 100%; height: 100%; object-fit: cover; }
.cpw-profile-name  { font-family: var(--f-display); font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 3px; position: relative; z-index: 1; }
.cpw-profile-email { font-size: 11px; color: var(--c-muted); word-break: break-all; position: relative; z-index: 1; }

/* ── SIDEBAR NAV ───────────────────────────── */
.cpw-sidebar-nav {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
}
.cpw-nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    font-size: 13px; font-weight: 500; color: var(--c-muted);
    border-bottom: 1px solid var(--c-border);
    border-left: 2px solid transparent;
    transition: all .2s;
}
.cpw-nav-item:last-child { border-bottom: none; }
.cpw-nav-item:hover { background: var(--c-faint); color: var(--c-text); border-left-color: var(--c-border2); }
.cpw-nav-item.active { background: var(--c-lime-dim); color: var(--c-lime); border-left-color: var(--c-lime); }
.cpw-nav-item i { width: 16px; text-align: center; font-size: 14px; flex-shrink: 0; }
.cpw-nav-item.danger { color: rgba(239,83,80,.7); }
.cpw-nav-item.danger:hover { background: rgba(239,83,80,.07); color: var(--c-red); border-left-color: var(--c-red); }

/* ── ALERT ─────────────────────────────────── */
.cpw-alert {
    display: flex; align-items: center; gap: 9px;
    padding: 12px 16px; border-radius: 8px;
    font-size: 13px; font-weight: 500; margin-bottom: 16px;
}
.cpw-alert.success { background: rgba(38,166,154,.1); border: 1px solid rgba(38,166,154,.25); color: #4DB6AC; }
.cpw-alert.error   { background: rgba(239,83,80,.1);  border: 1px solid rgba(239,83,80,.25);  color: #EF9A9A; }

/* ── FORM CARD ─────────────────────────────── */
.cpw-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
    margin-bottom: 16px; position: relative;
}
.cpw-card::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.cpw-card-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; gap: 10px;
}
.cpw-card-header h3 {
    font-family: var(--f-display); font-size: 15px;
    font-weight: 700; color: var(--c-text); margin: 0;
}
.cpw-card-header i { color: var(--c-lime); font-size: 16px; }
.cpw-card-body   { padding: 22px; }
.cpw-card-footer {
    padding: 14px 22px;
    border-top: 1px solid var(--c-border);
    background: rgba(0,0,0,.15);
    display: flex; justify-content: flex-end; gap: 10px;
}

/* ── FORM ELEMENTS ─────────────────────────── */
.cpw-field { margin-bottom: 20px; }
.cpw-field:last-child { margin-bottom: 0; }
.cpw-label {
    display: block; font-size: 11px; font-weight: 600;
    letter-spacing: .1em; text-transform: uppercase;
    color: var(--c-muted); margin-bottom: 7px;
}
.cpw-input-wrap { position: relative; }
.cpw-input {
    width: 100%; padding: 11px 44px 11px 14px;
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 8px;
    font-size: 13.5px; color: var(--c-text);
    font-family: var(--f-sans); outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.cpw-input::placeholder { color: var(--c-muted); }
.cpw-input:focus {
    border-color: rgba(125,255,0,.45);
    box-shadow: 0 0 0 3px rgba(125,255,0,.08);
}
.cpw-input.is-invalid { border-color: rgba(239,83,80,.5); }
.cpw-eye-btn {
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--c-muted); font-size: 14px; padding: 0;
    display: flex; align-items: center; transition: color .2s;
}
.cpw-eye-btn:hover { color: var(--c-lime); }
.cpw-invalid-msg { font-size: 12px; color: #EF9A9A; margin-top: 5px; }

/* ── STRENGTH BAR ──────────────────────────── */
.cpw-strength-bar {
    height: 3px; border-radius: 2px;
    background: var(--c-panel); margin-top: 10px; overflow: hidden;
}
.cpw-strength-fill {
    height: 100%; border-radius: 2px;
    transition: width .35s ease, background .35s ease;
    width: 0%;
}
.cpw-strength-text {
    font-family: var(--f-mono); font-size: 11px;
    margin-top: 5px; font-weight: 600; min-height: 16px;
}

/* ── PASSWORD RULES ────────────────────────── */
.cpw-rules {
    list-style: none; padding: 0; margin: 12px 0 0;
    display: flex; flex-wrap: wrap; gap: 6px;
}
.cpw-rule {
    font-size: 11px; display: inline-flex; align-items: center; gap: 4px;
    color: var(--c-muted); transition: color .2s;
    background: var(--c-panel); border: 1px solid var(--c-border);
    padding: 3px 10px; border-radius: 100px;
    font-family: var(--f-mono); letter-spacing: .03em;
}
.cpw-rule i { font-size: 10px; }
.cpw-rule.ok {
    color: var(--c-lime);
    background: var(--c-lime-dim);
    border-color: rgba(125,255,0,.25);
}

/* Match message */
.cpw-match-msg {
    font-size: 12px; margin-top: 6px;
    display: none; font-family: var(--f-mono);
    display: flex; align-items: center; gap: 5px;
    font-weight: 600;
}

/* ── TIPS CARD ─────────────────────────────── */
.cpw-tips {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 18px 20px;
    position: relative; overflow: hidden;
}
.cpw-tips::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-border2), transparent);
}
.cpw-tips h5 {
    font-family: var(--f-display); font-size: 14px;
    font-weight: 700; color: var(--c-text);
    margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
}
.cpw-tips h5 i { color: var(--c-lime); }
.cpw-tips ul { padding-left: 16px; }
.cpw-tips ul li { font-size: 12.5px; color: var(--c-muted); margin-bottom: 6px; line-height: 1.6; }

/* ── BUTTONS ───────────────────────────────── */
.cpw-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 22px; border-radius: 8px; border: none;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    letter-spacing: .04em; cursor: pointer; transition: all .2s;
}
.cpw-btn.primary {
    background: var(--c-lime); color: #000;
    box-shadow: 0 0 16px rgba(125,255,0,.2);
}
.cpw-btn.primary:hover { background: #8FFF1A; box-shadow: 0 0 26px rgba(125,255,0,.35); transform: translateY(-1px); }
.cpw-btn.primary:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
.cpw-btn.secondary {
    background: var(--c-panel); color: var(--c-muted);
    border: 1px solid var(--c-border2);
}
.cpw-btn.secondary:hover { color: var(--c-text); border-color: var(--c-border2); }

/* ── RESPONSIVE ────────────────────────────── */
@media (max-width: 768px) {
    .cpw-layout  { flex-direction: column; padding: 20px 16px 60px; }
    .cpw-sidebar { width: 100%; position: static; }
    .cpw-main    { max-width: 100%; }
    .cpw-header  { padding: 32px 16px 26px; }
}
</style>

<div class="cpw">

{{-- PAGE HEADER --}}
<div class="cpw-header">
    <div class="cpw-header-inner cpw-anim">
        <h1><i class="fas fa-lock"></i> Change Password</h1>
        <p>Keep your account secure with a strong, unique password.</p>
    </div>
</div>

<div class="cpw-layout">

    {{-- ── SIDEBAR ── --}}
    <div class="cpw-sidebar cpw-anim">
        <div class="cpw-profile-box">
            <div class="cpw-avatar">
                @if($user->profile_pic)
                    <img src="{{ asset(getFilePath('userProfile') . '/' . $user->profile_pic) }}" alt="">
                @else
                    {{ strtoupper(substr($user->firstname, 0, 1)) }}
                @endif
            </div>
            <div class="cpw-profile-name">{{ $user->firstname }} {{ $user->lastname }}</div>
            <div class="cpw-profile-email">{{ $user->email }}</div>
        </div>

        <nav class="cpw-sidebar-nav">
            <a href="{{ route('user.dashboard') }}" class="cpw-nav-item">
                <i class="fas fa-th-large"></i> My Dashboard
            </a>
            <a href="{{ route('cp.my-subscription') }}" class="cpw-nav-item">
                <i class="fas fa-crown"></i> My Subscription
            </a>
            <a href="{{ route('cp.analyses.index') }}" class="cpw-nav-item">
                <i class="fas fa-chart-line"></i> Option Analysis
            </a>
            <a href="{{ route('courses') }}" class="cpw-nav-item">
                <i class="fas fa-book-open"></i> Browse Courses
            </a>
            <a href="{{ route('user.profile') }}" class="cpw-nav-item">
                <i class="fas fa-user-circle"></i> Profile Settings
            </a>
            <a href="{{ route('user.change-password') }}" class="cpw-nav-item active">
                <i class="fas fa-lock"></i> Change Password
            </a>
            <a href="{{ route('user.logout') }}" class="cpw-nav-item danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    {{-- ── MAIN ── --}}
    <div class="cpw-main cpw-anim d1">

        {{-- Session notifications --}}
        @if(session('notify'))
            @foreach(session('notify') as $n)
            <div class="cpw-alert {{ $n[0] }}">
                <i class="fas fa-{{ $n[0]==='success' ? 'check-circle' : 'exclamation-circle' }}"></i>
                {{ $n[1] }}
            </div>
            @endforeach
        @endif

        <div class="cpw-card">
            <div class="cpw-card-header">
                <i class="fas fa-key"></i>
                <h3>Update Password</h3>
            </div>
            <form action="{{ route('user.change-password.update') }}" method="POST">
                @csrf
                <div class="cpw-card-body">

                    {{-- Current Password --}}
                    <div class="cpw-field">
                        <label class="cpw-label">Current Password <span style="color:var(--c-red);">*</span></label>
                        <div class="cpw-input-wrap">
                            <input type="password" name="current_password" id="currentPw"
                                   class="cpw-input {{ $errors->has('current_password') ? 'is-invalid' : '' }}"
                                   placeholder="Enter your current password" required>
                            <button type="button" class="cpw-eye-btn" onclick="togglePw('currentPw',this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('current_password')<div class="cpw-invalid-msg">{{ $message }}</div>@enderror
                    </div>

                    {{-- New Password --}}
                    <div class="cpw-field">
                        <label class="cpw-label">New Password <span style="color:var(--c-red);">*</span></label>
                        <div class="cpw-input-wrap">
                            <input type="password" name="password" id="newPw"
                                   class="cpw-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                   placeholder="Min. 8 characters" required minlength="8"
                                   oninput="checkStrength(this.value);checkRules(this.value);checkMatch();">
                            <button type="button" class="cpw-eye-btn" onclick="togglePw('newPw',this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')<div class="cpw-invalid-msg">{{ $message }}</div>@enderror
                        <div class="cpw-strength-bar">
                            <div class="cpw-strength-fill" id="sBar"></div>
                        </div>
                        <div class="cpw-strength-text" id="sText"></div>
                        <ul class="cpw-rules">
                            <li class="cpw-rule" id="rLen"><i class="fas fa-circle"></i> 8+ chars</li>
                            <li class="cpw-rule" id="rUpper"><i class="fas fa-circle"></i> Uppercase</li>
                            <li class="cpw-rule" id="rLower"><i class="fas fa-circle"></i> Lowercase</li>
                            <li class="cpw-rule" id="rNum"><i class="fas fa-circle"></i> Number</li>
                            <li class="cpw-rule" id="rSpec"><i class="fas fa-circle"></i> Symbol</li>
                        </ul>
                    </div>

                    {{-- Confirm Password --}}
                    <div class="cpw-field">
                        <label class="cpw-label">Confirm New Password <span style="color:var(--c-red);">*</span></label>
                        <div class="cpw-input-wrap">
                            <input type="password" name="password_confirmation" id="confirmPw"
                                   class="cpw-input" placeholder="Re-enter new password"
                                   required oninput="checkMatch()">
                            <button type="button" class="cpw-eye-btn" onclick="togglePw('confirmPw',this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="cpw-match-msg" id="matchMsg"></div>
                    </div>

                </div>
                <div class="cpw-card-footer">
                    <a href="{{ route('user.dashboard') }}" class="cpw-btn secondary">Cancel</a>
                    <button type="submit" class="cpw-btn primary" id="submitBtn">
                        <i class="fas fa-lock"></i> Update Password
                    </button>
                </div>
            </form>
        </div>

        {{-- Tips --}}
        <div class="cpw-tips cpw-anim d2">
            <h5><i class="fas fa-shield-alt"></i> Password Tips</h5>
            <ul>
                <li>Use a mix of uppercase, lowercase, numbers and special characters.</li>
                <li>Avoid using your name, email or common words like "password123".</li>
                <li>Don't reuse a password from another account.</li>
                <li>A password manager can help generate and store strong passwords.</li>
            </ul>
        </div>

    </div>{{-- /cpw-main --}}
</div>{{-- /cpw-layout --}}
</div>{{-- /cpw --}}

{{-- ── JS: ALL LOGIC IDENTICAL ── --}}
<script>
function togglePw(id, btn) {
    var i  = document.getElementById(id);
    var ic = btn.querySelector('i');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
}

function checkStrength(v) {
    var s = 0;
    if (v.length >= 8)           s++;
    if (/[A-Z]/.test(v))         s++;
    if (/[a-z]/.test(v))         s++;
    if (/[0-9]/.test(v))         s++;
    if (/[^A-Za-z0-9]/.test(v))  s++;
    var f = document.getElementById('sBar');
    var t = document.getElementById('sText');
    var labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    var colors = ['', '#EF5350', '#FFA726', '#FFC107', '#26A69A', '#7DFF00'];
    f.style.width     = (s / 5 * 100) + '%';
    f.style.background = colors[s] || 'var(--c-panel)';
    t.textContent  = v.length ? labels[s] : '';
    t.style.color  = colors[s] || 'var(--c-muted)';
}

function checkRules(v) {
    var rules = {
        rLen:   v.length >= 8,
        rUpper: /[A-Z]/.test(v),
        rLower: /[a-z]/.test(v),
        rNum:   /[0-9]/.test(v),
        rSpec:  /[^A-Za-z0-9]/.test(v),
    };
    Object.keys(rules).forEach(function (id) {
        var el = document.getElementById(id);
        var ic = el.querySelector('i');
        el.classList.toggle('ok', rules[id]);
        ic.className = rules[id] ? 'fas fa-check-circle' : 'fas fa-circle';
    });
}

function checkMatch() {
    var np  = document.getElementById('newPw').value;
    var cp  = document.getElementById('confirmPw').value;
    var msg = document.getElementById('matchMsg');
    var btn = document.getElementById('submitBtn');
    if (!cp) { msg.style.display = 'none'; return; }
    if (np === cp) {
        msg.style.display = 'flex';
        msg.style.color   = '#4DB6AC';
        msg.innerHTML     = '<i class="fas fa-check-circle"></i> Passwords match';
        btn.disabled      = false;
    } else {
        msg.style.display = 'flex';
        msg.style.color   = '#EF9A9A';
        msg.innerHTML     = '<i class="fas fa-times-circle"></i> Passwords do not match';
        btn.disabled      = true;
    }
}
</script>

@endsection