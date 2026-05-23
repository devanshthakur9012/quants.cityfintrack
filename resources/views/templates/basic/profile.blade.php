{{-- FILE: resources/views/themes/{activeTemplate}/profile.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
.up { font-family:'Exo 2',sans-serif; background:#f4f6fb; min-height:80vh; }
.up *,.up *::before,.up *::after { box-sizing:border-box; }
.up h1,.up h2,.up h3,.up h4 { font-family:'Rajdhani',sans-serif; letter-spacing:.02em; }
.up a { text-decoration:none; color:inherit; }
/* Header */
.up-header { background:linear-gradient(135deg,#0f1b2d,#1a3050); padding:32px 24px; }
.up-header-inner { max-width:960px; margin:0 auto; }
.up-header h1 { font-size:26px; font-weight:700; color:#fff; margin:0 0 4px; }
.up-header p  { font-size:13.5px; color:rgba(255,255,255,.5); margin:0; }
/* Layout */
.up-layout { max-width:960px; margin:0 auto; padding:28px 24px 72px; display:flex; gap:24px; align-items:flex-start; }
.up-sidebar { flex-shrink:0; width:240px; position:sticky; top:20px; }
.up-main    { flex:1; min-width:0; }
/* Sidebar profile box */
.up-profile-box { background:linear-gradient(135deg,#0f1b2d,#1a3050); border-radius:12px; padding:24px 18px 18px; text-align:center; margin-bottom:12px; border:1px solid rgba(245,166,35,.2); }
.up-avatar-wrap { width:68px; height:68px; border-radius:50%; margin:0 auto 12px; border:3px solid #f5a623; overflow:hidden; background:#f5a623; display:flex; align-items:center; justify-content:center; font-family:'Rajdhani',sans-serif; font-size:24px; font-weight:700; color:#0f1b2d; cursor:pointer; position:relative; }
.up-avatar-wrap img { width:100%; height:100%; object-fit:cover; }
.up-avatar-overlay { position:absolute; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity .2s; border-radius:50%; color:#fff; font-size:16px; }
.up-avatar-wrap:hover .up-avatar-overlay { opacity:1; }
.up-profile-name  { font-size:15px; font-weight:700; color:#fff; margin-bottom:2px; }
.up-profile-email { font-size:12px; color:rgba(255,255,255,.45); }
/* Sidebar nav */
.up-nav { background:#fff; border-radius:10px; border:1px solid #e5e9f2; overflow:hidden; }
.up-nav-item { display:flex; align-items:center; gap:10px; padding:12px 16px; font-size:13.5px; font-weight:500; color:#5a6678; border-bottom:1px solid #f0f2f7; transition:all .2s; }
.up-nav-item:last-child { border-bottom:none; }
.up-nav-item:hover,.up-nav-item.active { background:#f0f5ff; color:#1a56db; }
.up-nav-item.danger { color:#e53935; }
.up-nav-item.danger:hover { background:#fff5f5; color:#b71c1c; }
.up-nav-item i { width:18px; text-align:center; font-size:15px; flex-shrink:0; }
/* Cards */
.up-card { background:#fff; border-radius:12px; border:1px solid #e5e9f2; overflow:hidden; margin-bottom:20px; }
.up-card-header { padding:18px 22px; border-bottom:1px solid #f0f2f7; display:flex; align-items:center; gap:10px; }
.up-card-header h3 { font-size:17px; font-weight:700; color:#0f1b2d; margin:0; }
.up-card-header i  { color:#1a56db; font-size:17px; }
.up-card-body   { padding:22px; }
.up-card-footer { padding:14px 22px; border-top:1px solid #f0f2f7; background:#fafbff; display:flex; justify-content:flex-end; gap:10px; }
/* Form */
.up-label { font-size:12.5px; font-weight:600; color:#5a6678; margin-bottom:5px; display:block; }
.up-input { width:100%; border:1px solid #dde2ee; border-radius:7px; padding:10px 13px; font-size:13.5px; color:#2d3a4e; font-family:'Exo 2',sans-serif; outline:none; transition:border-color .2s; background:#fff; }
.up-input:focus { border-color:#1a56db; }
.up-input[readonly] { background:#f8f9fd; color:#8a94a6; cursor:not-allowed; }
.up-invalid-msg { font-size:12px; color:#e53935; margin-top:4px; }
.up-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.up-btn { display:inline-flex; align-items:center; gap:7px; padding:11px 24px; border-radius:8px; border:none; font-family:'Exo 2',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:all .2s; }
.up-btn.primary   { background:#f5a623; color:#0f1b2d; }
.up-btn.primary:hover   { background:#d4890e; transform:translateY(-1px); }
.up-btn.secondary { background:#f0f2f7; color:#5a6678; }
.up-btn.secondary:hover { background:#e5e9f2; }
/* Avatar upload area */
.up-avatar-upload { display:flex; align-items:center; gap:16px; margin-bottom:16px; }
.up-avatar-large  { width:72px; height:72px; border-radius:50%; border:3px solid #f5a623; overflow:hidden; background:#f5a623; display:flex; align-items:center; justify-content:center; font-family:'Rajdhani',sans-serif; font-size:26px; font-weight:700; color:#0f1b2d; flex-shrink:0; }
.up-avatar-large img { width:100%; height:100%; object-fit:cover; }
/* Verify badges */
.up-vbadge { font-size:11px; padding:2px 7px; border-radius:4px; font-weight:600; display:inline-flex; align-items:center; gap:3px; margin-left:5px; }
.up-vbadge.yes { background:#e8f5e9; color:#2e7d32; }
.up-vbadge.no  { background:#fce4ec; color:#c62828; }
/* Responsive */
@media(max-width:768px) { .up-layout{flex-direction:column;padding:16px 14px 56px;} .up-sidebar{width:100%;position:static;} .up-form-grid{grid-template-columns:1fr;} }
</style>

<div class="up">

<div class="up-header">
    <div class="up-header-inner">
        <h1><i class="fas fa-user-circle" style="color:#f5a623;margin-right:8px;font-size:22px;"></i>Profile Settings</h1>
        <p>Manage your personal information and contact details.</p>
    </div>
</div>

<div class="up-layout">

    {{-- ── SIDEBAR ── --}}
    <div class="up-sidebar">
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

        <div class="up-nav">
            <a href="{{ route('user.dashboard') }}" class="up-nav-item">
                <i class="fas fa-th-large"></i> Dashboard
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
        </div>
    </div>

    {{-- ── FORM ── --}}
    <div class="up-main">

        @if(session('notify'))
            @foreach(session('notify') as $n)
            <div style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:500;
                 background:{{ $n[0]==='success'?'#e8f5e9':'#fce4ec' }};color:{{ $n[0]==='success'?'#2e7d32':'#c62828' }};">
                <i class="fas fa-{{ $n[0]==='success'?'check-circle':'exclamation-circle' }}"></i>
                {{ $n[1] }}
            </div>
            @endforeach
        @endif

        <form action="{{ route('user.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Photo --}}
            <div class="up-card">
                <div class="up-card-header"><i class="fas fa-image"></i><h3>Profile Photo</h3></div>
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
                            <label for="profilePicInput" class="up-btn secondary" style="cursor:pointer;margin-bottom:6px;">
                                <i class="fas fa-upload"></i> Choose Photo
                            </label>
                            <input type="file" name="profile_pic" id="profilePicInput"
                                   accept="image/jpeg,image/png,image/webp" style="display:none;">
                            <p style="font-size:12px;color:#9aa3b5;margin:4px 0 0;">JPG, PNG, WEBP — max 2 MB</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Personal Info --}}
            <div class="up-card">
                <div class="up-card-header"><i class="fas fa-user"></i><h3>Personal Information</h3></div>
                <div class="up-card-body">

                    <div class="up-form-grid" style="margin-bottom:16px;">
                        <div>
                            <label class="up-label">First Name <span style="color:#e53935;">*</span></label>
                            <input type="text" name="firstname"
                                   class="up-input {{ $errors->has('firstname') ? 'is-invalid' : '' }}"
                                   value="{{ old('firstname', $user->firstname) }}" required>
                            @error('firstname')<div class="up-invalid-msg">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="up-label">Last Name <span style="color:#e53935;">*</span></label>
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
                            <small style="font-size:11.5px;color:#9aa3b5;">Contact support to change email.</small>
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
                        <div style="position:relative;">
                            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9aa3b5;">@</span>
                            <input type="text" name="telegram_username" class="up-input" style="padding-left:26px;"
                                   value="{{ old('telegram_username', $user->telegram_username) }}"
                                   placeholder="yourusername">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="up-card">
                <div class="up-card-header"><i class="fas fa-map-marker-alt"></i><h3>Address</h3></div>
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
                            <label class="up-label">Username <small style="color:#9aa3b5;">(read only)</small></label>
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
    </div>
</div>
</div>

<script>
document.getElementById('profilePicInput').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        // main preview
        var prev = document.getElementById('avatarPreview');
        var init = document.getElementById('avatarInit');
        if (prev) { prev.src = e.target.result; prev.style.display = 'block'; }
        if (init) init.style.display = 'none';
        // sidebar preview
        var sb   = document.getElementById('sbAvatar');
        var sbi  = document.getElementById('sbInit');
        if (sb)  { sb.src = e.target.result; sb.style.display = 'block'; }
        if (sbi) sbi.style.display = 'none';
    };
    reader.readAsDataURL(file);
});
</script>

@endsection