{{-- FILE: resources/views/themes/{activeTemplate}/profile.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — PROFILE SETTINGS  v2.0
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
    --c-amber:    #FFA726;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.up { font-family: var(--f-sans); background: var(--c-bg); color: var(--c-text); min-height: 80vh; }
.up * { box-sizing: border-box; }
.up a { text-decoration: none; color: inherit; }

@keyframes upFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.up-anim    { animation: upFadeUp .45s ease both; }
.up-anim.d1 { animation-delay: .06s; }
.up-anim.d2 { animation-delay: .12s; }

/* ── PAGE HEADER ───────────────────────────── */
.up-header {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 44px 24px 36px;
    border-bottom: 1px solid var(--c-border);
}
.up-header::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 25% 50%, black, transparent);
    pointer-events: none;
}
.up-header::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 38% 70% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.up-header-inner {
    position: relative; z-index: 1;
    max-width: 1000px; margin: 0 auto;
}
.up-header h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 800; color: #fff; margin-bottom: 5px;
    display: flex; align-items: center; gap: 10px;
}
.up-header h1 i { color: var(--c-lime); font-size: 22px; }
.up-header p { font-size: 13px; color: var(--c-muted); }

/* ── LAYOUT ────────────────────────────────── */
.up-layout {
    max-width: 1000px; margin: 0 auto;
    padding: 28px 24px 80px;
    display: flex; gap: 22px; align-items: flex-start;
}
.up-sidebar { flex-shrink: 0; width: 240px; position: sticky; top: 96px; }
.up-main    { flex: 1; min-width: 0; }

/* ── SIDEBAR PROFILE BOX ───────────────────── */
.up-profile-box {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 22px 16px 16px;
    text-align: center; margin-bottom: 12px;
    position: relative; overflow: hidden;
}
.up-profile-box::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .5;
}
.up-profile-box::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.up-avatar-wrap {
    width: 64px; height: 64px; border-radius: 50%;
    margin: 0 auto 12px; position: relative; z-index: 1;
    border: 2px solid var(--c-lime);
    background: var(--c-lime);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 22px; font-weight: 800; color: #000;
    overflow: hidden; cursor: pointer;
    box-shadow: 0 0 16px rgba(125,255,0,.2);
    transition: box-shadow .2s;
}
.up-avatar-wrap:hover { box-shadow: 0 0 24px rgba(125,255,0,.35); }
.up-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.up-avatar-overlay {
    position: absolute; inset: 0; border-radius: 50%;
    background: rgba(0,0,0,.5);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .2s; color: #fff; font-size: 15px;
}
.up-avatar-wrap:hover .up-avatar-overlay { opacity: 1; }
.up-profile-name  { font-family: var(--f-display); font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 3px; position: relative; z-index: 1; }
.up-profile-email { font-size: 11px; color: var(--c-muted); word-break: break-all; position: relative; z-index: 1; }

/* ── SIDEBAR NAV ───────────────────────────── */
.up-sidebar-nav {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
}
.up-nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    font-size: 13px; font-weight: 500; color: var(--c-muted);
    border-bottom: 1px solid var(--c-border);
    border-left: 2px solid transparent;
    transition: all .2s;
}
.up-nav-item:last-child { border-bottom: none; }
.up-nav-item:hover { background: var(--c-faint); color: var(--c-text); border-left-color: var(--c-border2); }
.up-nav-item.active { background: var(--c-lime-dim); color: var(--c-lime); border-left-color: var(--c-lime); }
.up-nav-item i { width: 16px; text-align: center; font-size: 14px; flex-shrink: 0; }
.up-nav-item.danger { color: rgba(239,83,80,.7); }
.up-nav-item.danger:hover { background: rgba(239,83,80,.07); color: var(--c-red); border-left-color: var(--c-red); }

/* ── ALERT / NOTIFY ────────────────────────── */
.up-alert {
    display: flex; align-items: center; gap: 9px;
    padding: 12px 16px; border-radius: 8px;
    font-size: 13px; font-weight: 500; margin-bottom: 16px;
}
.up-alert.success { background: rgba(38,166,154,.1); border: 1px solid rgba(38,166,154,.25); color: #4DB6AC; }
.up-alert.error   { background: rgba(239,83,80,.1);  border: 1px solid rgba(239,83,80,.25);  color: #EF9A9A; }

/* ── FORM CARDS ────────────────────────────── */
.up-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
    margin-bottom: 16px;
    position: relative;
}
.up-card::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .3;
}
.up-card-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; gap: 10px;
}
.up-card-header h3 {
    font-family: var(--f-display); font-size: 15px;
    font-weight: 700; color: var(--c-text); margin: 0;
}
.up-card-header i { color: var(--c-lime); font-size: 16px; }
.up-card-body   { padding: 22px; }
.up-card-footer {
    padding: 14px 22px;
    border-top: 1px solid var(--c-border);
    background: rgba(0,0,0,.15);
    display: flex; justify-content: flex-end; gap: 10px;
}

/* ── FORM ELEMENTS ─────────────────────────── */
.up-label {
    display: block; font-size: 11px; font-weight: 600;
    letter-spacing: .1em; text-transform: uppercase;
    color: var(--c-muted); margin-bottom: 7px;
}
.up-input {
    width: 100%; padding: 11px 14px;
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 8px;
    font-size: 13.5px; color: var(--c-text);
    font-family: var(--f-sans); outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.up-input::placeholder { color: var(--c-muted); }
.up-input:focus {
    border-color: rgba(125,255,0,.45);
    box-shadow: 0 0 0 3px rgba(125,255,0,.08);
}
.up-input[readonly] {
    background: rgba(0,0,0,.2); color: var(--c-muted);
    cursor: not-allowed; border-color: var(--c-border);
}
.up-invalid-msg { font-size: 12px; color: #EF9A9A; margin-top: 5px; }

.up-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Telegram prefix */
.up-input-prefix {
    position: relative;
}
.up-input-prefix-sym {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    color: var(--c-muted); font-size: 13px; pointer-events: none;
}
.up-input-prefix .up-input { padding-left: 26px; }

/* ── AVATAR UPLOAD ─────────────────────────── */
.up-avatar-upload { display: flex; align-items: center; gap: 16px; }
.up-avatar-large {
    width: 68px; height: 68px; border-radius: 50%;
    border: 2px solid var(--c-lime);
    background: var(--c-lime);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 22px; font-weight: 800; color: #000;
    overflow: hidden; flex-shrink: 0;
    box-shadow: 0 0 14px rgba(125,255,0,.2);
}
.up-avatar-large img { width: 100%; height: 100%; object-fit: cover; }
.up-upload-btn {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-panel); color: var(--c-text);
    border: 1px solid var(--c-border2);
    font-family: var(--f-sans); font-size: 13px; font-weight: 600;
    padding: 9px 18px; border-radius: 7px; cursor: pointer;
    transition: all .2s;
}
.up-upload-btn:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.up-upload-hint { font-size: 11.5px; color: var(--c-muted); margin-top: 5px; }

/* ── VERIFY BADGES ─────────────────────────── */
.up-vbadge {
    font-size: 10px; font-weight: 700; letter-spacing: .05em;
    padding: 2px 8px; border-radius: 100px; margin-left: 6px;
    display: inline-flex; align-items: center; gap: 3px;
    font-family: var(--f-mono);
}
.up-vbadge.yes { background: rgba(38,166,154,.12); color: #4DB6AC; }
.up-vbadge.no  { background: rgba(239,83,80,.1);   color: #EF9A9A; }

/* ── BUTTONS ───────────────────────────────── */
.up-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 22px; border-radius: 8px; border: none;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    letter-spacing: .04em; cursor: pointer; transition: all .2s;
}
.up-btn.primary {
    background: var(--c-lime); color: #000;
    box-shadow: 0 0 16px rgba(125,255,0,.2);
}
.up-btn.primary:hover { background: #8FFF1A; box-shadow: 0 0 26px rgba(125,255,0,.35); transform: translateY(-1px); }
.up-btn.secondary {
    background: var(--c-panel); color: var(--c-muted);
    border: 1px solid var(--c-border2);
}
.up-btn.secondary:hover { color: var(--c-text); border-color: var(--c-border2); }

/* ── RESPONSIVE ────────────────────────────── */
@media (max-width: 768px) {
    .up-layout  { flex-direction: column; padding: 20px 16px 60px; }
    .up-sidebar { width: 100%; position: static; }
    .up-form-grid { grid-template-columns: 1fr; }
    .up-header  { padding: 32px 16px 26px; }
}
</style>

<div class="up">

{{-- PAGE HEADER --}}
<div class="up-header">
    <div class="up-header-inner up-anim">
        <h1><i class="fas fa-user-circle"></i> Profile Settings</h1>
        <p>Manage your personal information and contact details.</p>
    </div>
</div>

<div class="up-layout">

    {{-- ── SIDEBAR ── --}}
    <div class="up-sidebar up-anim">
        <div class="up-profile-box">
            <div class="up-avatar-wrap" onclick="document.getElementById('profilePicInput').click()">
                @if($user->profile_pic)
                    <img id="sbAvatar" src="{{ asset(getFilePath('userProfile') . '/' . $user->profile_pic) }}" alt="">
                @else
                    <span id="sbInit">{{ strtoupper(substr($user->firstname, 0, 1)) }}</span>
                    <img id="sbAvatar" style="display:none;" src="" alt="">
                @endif
                <div class="up-avatar-overlay"><i class="fas fa-camera"></i></div>
            </div>
            <div class="up-profile-name">{{ $user->firstname }} {{ $user->lastname }}</div>
            <div class="up-profile-email">{{ $user->email }}</div>
        </div>

        <nav class="up-sidebar-nav">
            <a href="{{ route('user.dashboard') }}" class="up-nav-item">
                <i class="fas fa-th-large"></i> My Dashboard
            </a>
            <a href="{{ route('cp.my-subscription') }}" class="up-nav-item">
                <i class="fas fa-crown"></i> My Subscription
            </a>
            <a href="{{ route('cp.analyses.index') }}" class="up-nav-item">
                <i class="fas fa-chart-line"></i> Option Analysis
            </a>
            <a href="{{ route('courses') }}" class="up-nav-item">
                <i class="fas fa-book-open"></i> Browse Courses
            </a>
            <a href="{{ route('user.profile') }}" class="up-nav-item active">
                <i class="fas fa-user-circle"></i> Profile Settings
            </a>
            <a href="{{ route('user.change-password') }}" class="up-nav-item">
                <i class="fas fa-lock"></i> Change Password
            </a>
            <a href="{{ route('user.logout') }}" class="up-nav-item danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    {{-- ── MAIN FORM ── --}}
    <div class="up-main up-anim d1">

        {{-- Session notifications --}}
        @if(session('notify'))
            @foreach(session('notify') as $n)
            <div class="up-alert {{ $n[0] }}">
                <i class="fas fa-{{ $n[0]==='success' ? 'check-circle' : 'exclamation-circle' }}"></i>
                {{ $n[1] }}
            </div>
            @endforeach
        @endif

        <form action="{{ route('user.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Profile Photo --}}
            <div class="up-card">
                <div class="up-card-header">
                    <i class="fas fa-image"></i>
                    <h3>Profile Photo</h3>
                </div>
                <div class="up-card-body">
                    <div class="up-avatar-upload">
                        <div class="up-avatar-large">
                            @if($user->profile_pic)
                                <img id="avatarPreview" src="{{ asset(getFilePath('userProfile') . '/' . $user->profile_pic) }}" alt="">
                            @else
                                <span id="avatarInit">{{ strtoupper(substr($user->firstname, 0, 1)) }}</span>
                                <img id="avatarPreview" style="display:none;" src="" alt="">
                            @endif
                        </div>
                        <div>
                            <label for="profilePicInput" class="up-upload-btn">
                                <i class="fas fa-upload"></i> Choose Photo
                            </label>
                            <input type="file" name="profile_pic" id="profilePicInput"
                                   accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <div class="up-upload-hint">JPG, PNG, WEBP — max 2 MB</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Personal Information --}}
            <div class="up-card">
                <div class="up-card-header">
                    <i class="fas fa-user"></i>
                    <h3>Personal Information</h3>
                </div>
                <div class="up-card-body">

                    <div class="up-form-grid" style="margin-bottom:16px;">
                        <div>
                            <label class="up-label">First Name <span style="color:var(--c-red);">*</span></label>
                            <input type="text" name="firstname"
                                   class="up-input {{ $errors->has('firstname') ? 'is-invalid' : '' }}"
                                   value="{{ old('firstname', $user->firstname) }}" required>
                            @error('firstname')<div class="up-invalid-msg">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="up-label">Last Name <span style="color:var(--c-red);">*</span></label>
                            <input type="text" name="lastname"
                                   class="up-input {{ $errors->has('lastname') ? 'is-invalid' : '' }}"
                                   value="{{ old('lastname', $user->lastname) }}" required>
                            @error('lastname')<div class="up-invalid-msg">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="up-form-grid" style="margin-bottom:16px;">
                        <div>
                            <label class="up-label">
                                Email Address
                                @if($user->ev)
                                    <span class="up-vbadge yes"><i class="fas fa-check-circle"></i> Verified</span>
                                @else
                                    <span class="up-vbadge no"><i class="fas fa-times-circle"></i> Unverified</span>
                                @endif
                            </label>
                            <input type="email" class="up-input" value="{{ $user->email }}" readonly>
                            <div style="font-size:11px;color:var(--c-muted);margin-top:4px;">Contact support to change.</div>
                        </div>
                        <div>
                            <label class="up-label">
                                Mobile
                                @if($user->sv)
                                    <span class="up-vbadge yes"><i class="fas fa-check-circle"></i> Verified</span>
                                @else
                                    <span class="up-vbadge no"><i class="fas fa-times-circle"></i> Unverified</span>
                                @endif
                            </label>
                            <input type="text" name="mobile"
                                   class="up-input {{ $errors->has('mobile') ? 'is-invalid' : '' }}"
                                   value="{{ old('mobile', $user->mobile) }}"
                                   placeholder="+91 9000000000">
                            @error('mobile')<div class="up-invalid-msg">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="up-label">Telegram Username</label>
                        <div class="up-input-prefix">
                            <span class="up-input-prefix-sym">@</span>
                            <input type="text" name="telegram_username" class="up-input"
                                   value="{{ old('telegram_username', $user->telegram_username) }}"
                                   placeholder="yourusername">
                        </div>
                    </div>

                </div>
            </div>

            {{-- Address --}}
            <div class="up-card">
                <div class="up-card-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Address</h3>
                </div>
                <div class="up-card-body">
                    <div style="margin-bottom:16px;">
                        <label class="up-label">Street Address</label>
                        <input type="text" name="address" class="up-input"
                               value="{{ old('address', optional($user->address)->address) }}"
                               placeholder="123 Main Street">
                    </div>
                    <div class="up-form-grid" style="margin-bottom:16px;">
                        <div>
                            <label class="up-label">City</label>
                            <input type="text" name="city" class="up-input"
                                   value="{{ old('city', optional($user->address)->city) }}" placeholder="Mumbai">
                        </div>
                        <div>
                            <label class="up-label">State</label>
                            <input type="text" name="state" class="up-input"
                                   value="{{ old('state', optional($user->address)->state) }}" placeholder="Maharashtra">
                        </div>
                    </div>
                    <div class="up-form-grid">
                        <div>
                            <label class="up-label">ZIP / PIN Code</label>
                            <input type="text" name="zip" class="up-input"
                                   value="{{ old('zip', optional($user->address)->zip) }}" placeholder="400001">
                        </div>
                        <div>
                            <label class="up-label">Username <small style="color:var(--c-muted);text-transform:none;letter-spacing:0;">(read only)</small></label>
                            <input type="text" class="up-input" value="{{ $user->username }}" readonly>
                        </div>
                    </div>
                </div>
                <div class="up-card-footer">
                    <a href="{{ route('user.dashboard') }}" class="up-btn secondary">Cancel</a>
                    <button type="submit" class="up-btn primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>

        </form>
    </div>{{-- /up-main --}}

</div>{{-- /up-layout --}}
</div>{{-- /up --}}

{{-- ── AVATAR PREVIEW SCRIPT — LOGIC IDENTICAL ── --}}
<script>
document.getElementById('profilePicInput').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        var prev = document.getElementById('avatarPreview');
        var init = document.getElementById('avatarInit');
        if (prev) { prev.src = e.target.result; prev.style.display = 'block'; }
        if (init) init.style.display = 'none';
        var sb  = document.getElementById('sbAvatar');
        var sbi = document.getElementById('sbInit');
        if (sb)  { sb.src = e.target.result; sb.style.display = 'block'; }
        if (sbi) sbi.style.display = 'none';
    };
    reader.readAsDataURL(file);
});
</script>

@endsection