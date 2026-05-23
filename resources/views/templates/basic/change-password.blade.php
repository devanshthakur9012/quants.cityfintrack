{{-- FILE: resources/views/themes/{activeTemplate}/change-password.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
.cp{font-family:'Exo 2',sans-serif;background:#f4f6fb;min-height:80vh;}
.cp *,.cp *::before,.cp *::after{box-sizing:border-box;}
.cp h1,.cp h2,.cp h3,.cp h4{font-family:'Rajdhani',sans-serif;letter-spacing:.02em;}
.cp a{text-decoration:none;color:inherit;}
.cp-header{background:linear-gradient(135deg,#0f1b2d,#1a3050);padding:32px 24px;}
.cp-header-inner{max-width:960px;margin:0 auto;}
.cp-header h1{font-size:26px;font-weight:700;color:#fff;margin:0 0 4px;}
.cp-header p{font-size:13.5px;color:rgba(255,255,255,.5);margin:0;}
.cp-layout{max-width:960px;margin:0 auto;padding:28px 24px 72px;display:flex;gap:24px;align-items:flex-start;}
.cp-sidebar{flex-shrink:0;width:240px;position:sticky;top:20px;}
.cp-main{flex:1;min-width:0;max-width:580px;}
.cp-profile-box{background:linear-gradient(135deg,#0f1b2d,#1a3050);border-radius:12px;padding:24px 18px 18px;text-align:center;margin-bottom:12px;border:1px solid rgba(245,166,35,.2);}
.cp-avatar{width:68px;height:68px;border-radius:50%;margin:0 auto 12px;border:3px solid #f5a623;overflow:hidden;background:#f5a623;display:flex;align-items:center;justify-content:center;font-family:'Rajdhani',sans-serif;font-size:24px;font-weight:700;color:#0f1b2d;}
.cp-avatar img{width:100%;height:100%;object-fit:cover;}
.cp-profile-name{font-size:15px;font-weight:700;color:#fff;margin-bottom:2px;}
.cp-profile-email{font-size:12px;color:rgba(255,255,255,.45);}
.cp-nav{background:#fff;border-radius:10px;border:1px solid #e5e9f2;overflow:hidden;}
.cp-nav-item{display:flex;align-items:center;gap:10px;padding:12px 16px;font-size:13.5px;font-weight:500;color:#5a6678;border-bottom:1px solid #f0f2f7;transition:all .2s;}
.cp-nav-item:last-child{border-bottom:none;}
.cp-nav-item:hover,.cp-nav-item.active{background:#f0f5ff;color:#1a56db;}
.cp-nav-item.danger{color:#e53935;}
.cp-nav-item.danger:hover{background:#fff5f5;color:#b71c1c;}
.cp-nav-item i{width:18px;text-align:center;font-size:15px;flex-shrink:0;}
.cp-card{background:#fff;border-radius:12px;border:1px solid #e5e9f2;overflow:hidden;}
.cp-card-header{padding:18px 22px;border-bottom:1px solid #f0f2f7;display:flex;align-items:center;gap:10px;}
.cp-card-header h3{font-size:17px;font-weight:700;color:#0f1b2d;margin:0;}
.cp-card-header i{color:#1a56db;font-size:17px;}
.cp-card-body{padding:22px;}
.cp-card-footer{padding:14px 22px;border-top:1px solid #f0f2f7;background:#fafbff;display:flex;justify-content:flex-end;gap:10px;}
.cp-label{font-size:12.5px;font-weight:600;color:#5a6678;margin-bottom:5px;display:block;}
.cp-input-wrap{position:relative;}
.cp-input{width:100%;border:1px solid #dde2ee;border-radius:7px;padding:10px 42px 10px 13px;font-size:13.5px;color:#2d3a4e;font-family:'Exo 2',sans-serif;outline:none;transition:border-color .2s;background:#fff;}
.cp-input:focus{border-color:#1a56db;}
.cp-input.is-invalid{border-color:#e53935;}
.cp-eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9aa3b5;font-size:15px;padding:0;display:flex;align-items:center;transition:color .2s;}
.cp-eye-btn:hover{color:#1a56db;}
.cp-invalid-msg{font-size:12px;color:#e53935;margin-top:4px;}
.cp-field{margin-bottom:20px;}
.cp-strength-bar{height:5px;border-radius:3px;background:#e5e9f2;margin-top:8px;overflow:hidden;}
.cp-strength-fill{height:100%;border-radius:3px;transition:width .3s,background .3s;width:0%;}
.cp-strength-text{font-size:11.5px;margin-top:4px;font-weight:600;}
.cp-rules{list-style:none;padding:0;margin:12px 0 0;display:flex;flex-wrap:wrap;gap:6px;}
.cp-rule{font-size:12px;display:flex;align-items:center;gap:4px;color:#9aa3b5;transition:color .2s;}
.cp-rule i{font-size:11px;}
.cp-rule.ok{color:#2e7d32;}
.cp-btn{display:inline-flex;align-items:center;gap:7px;padding:11px 24px;border-radius:8px;border:none;font-family:'Exo 2',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;}
.cp-btn.primary{background:#f5a623;color:#0f1b2d;}
.cp-btn.primary:hover{background:#d4890e;transform:translateY(-1px);}
.cp-btn.secondary{background:#f0f2f7;color:#5a6678;}
.cp-btn.secondary:hover{background:#e5e9f2;}
.cp-tips{background:#f8f9fd;border:1px solid #e5e9f2;border-radius:10px;padding:16px 18px;margin-top:20px;}
.cp-tips h5{font-size:14px;font-weight:700;color:#0f1b2d;margin:0 0 10px;display:flex;align-items:center;gap:7px;}
.cp-tips h5 i{color:#f5a623;}
.cp-tips ul{padding-left:16px;margin:0;}
.cp-tips ul li{font-size:13px;color:#5a6678;margin-bottom:5px;line-height:1.55;}
@media(max-width:768px){.cp-layout{flex-direction:column;padding:16px 14px 56px;}.cp-sidebar{width:100%;position:static;}.cp-main{max-width:100%;}}
</style>

<div class="cp">
<div class="cp-header">
    <div class="cp-header-inner">
        <h1><i class="fas fa-lock" style="color:#f5a623;margin-right:8px;font-size:22px;"></i>Change Password</h1>
        <p>Keep your account secure with a strong, unique password.</p>
    </div>
</div>
<div class="cp-layout">
    <div class="cp-sidebar">
        <div class="cp-profile-box">
            <div class="cp-avatar">
                @if($user->profile_pic)
                    <img src="{{ asset(getFilePath('userProfile') . '/' . $user->profile_pic) }}" alt="">
                @else
                    {{ strtoupper(substr($user->firstname, 0, 1)) }}
                @endif
            </div>
            <div class="cp-profile-name">{{ $user->firstname }} {{ $user->lastname }}</div>
            <div class="cp-profile-email">{{ $user->email }}</div>
        </div>
        <div class="cp-nav">
            <a href="{{ route('user.dashboard') }}" class="cp-nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="{{ route('user.profile') }}" class="cp-nav-item"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="{{ route('user.change-password') }}" class="cp-nav-item active"><i class="fas fa-lock"></i> Change Password</a>
            <a href="{{ route('user.logout') }}" class="cp-nav-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="cp-main">

        @if(session('notify'))
            @foreach(session('notify') as $n)
            <div style="padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:500;background:{{ $n[0]==='success'?'#e8f5e9':'#fce4ec' }};color:{{ $n[0]==='success'?'#2e7d32':'#c62828' }};">
                <i class="fas fa-{{ $n[0]==='success'?'check-circle':'exclamation-circle' }}"></i> {{ $n[1] }}
            </div>
            @endforeach
        @endif

        <div class="cp-card">
            <div class="cp-card-header"><i class="fas fa-key"></i><h3>Update Password</h3></div>
            <form action="{{ route('user.change-password.update') }}" method="POST">
                @csrf
                <div class="cp-card-body">

                    <div class="cp-field">
                        <label class="cp-label">Current Password <span style="color:#e53935;">*</span></label>
                        <div class="cp-input-wrap">
                            <input type="password" name="current_password" id="currentPw"
                                   class="cp-input {{ $errors->has('current_password') ? 'is-invalid' : '' }}"
                                   placeholder="Enter your current password" required>
                            <button type="button" class="cp-eye-btn" onclick="togglePw('currentPw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        @error('current_password')<div class="cp-invalid-msg">{{ $message }}</div>@enderror
                    </div>

                    <div class="cp-field">
                        <label class="cp-label">New Password <span style="color:#e53935;">*</span></label>
                        <div class="cp-input-wrap">
                            <input type="password" name="password" id="newPw"
                                   class="cp-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                   placeholder="Min. 8 characters" required minlength="8"
                                   oninput="checkStrength(this.value);checkRules(this.value);checkMatch();">
                            <button type="button" class="cp-eye-btn" onclick="togglePw('newPw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        @error('password')<div class="cp-invalid-msg">{{ $message }}</div>@enderror
                        <div class="cp-strength-bar"><div class="cp-strength-fill" id="sBar"></div></div>
                        <div class="cp-strength-text" id="sText" style="color:#9aa3b5;"></div>
                        <ul class="cp-rules">
                            <li class="cp-rule" id="rLen"><i class="fas fa-circle"></i> 8+ characters</li>
                            <li class="cp-rule" id="rUpper"><i class="fas fa-circle"></i> Uppercase</li>
                            <li class="cp-rule" id="rLower"><i class="fas fa-circle"></i> Lowercase</li>
                            <li class="cp-rule" id="rNum"><i class="fas fa-circle"></i> Number</li>
                            <li class="cp-rule" id="rSpec"><i class="fas fa-circle"></i> Symbol</li>
                        </ul>
                    </div>

                    <div class="cp-field" style="margin-bottom:0;">
                        <label class="cp-label">Confirm New Password <span style="color:#e53935;">*</span></label>
                        <div class="cp-input-wrap">
                            <input type="password" name="password_confirmation" id="confirmPw"
                                   class="cp-input" placeholder="Re-enter new password" required
                                   oninput="checkMatch()">
                            <button type="button" class="cp-eye-btn" onclick="togglePw('confirmPw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div id="matchMsg" style="font-size:12px;margin-top:4px;display:none;"></div>
                    </div>

                </div>
                <div class="cp-card-footer">
                    <a href="{{ route('user.dashboard') }}" class="cp-btn secondary">Cancel</a>
                    <button type="submit" class="cp-btn primary" id="submitBtn">
                        <i class="fas fa-lock"></i> Update Password
                    </button>
                </div>
            </form>
        </div>

        <div class="cp-tips">
            <h5><i class="fas fa-shield-alt"></i> Password Tips</h5>
            <ul>
                <li>Use a mix of uppercase, lowercase, numbers and special characters.</li>
                <li>Avoid using your name, email or common words like "password123".</li>
                <li>Don't reuse a password from another account.</li>
                <li>A password manager can help generate and store strong passwords.</li>
            </ul>
        </div>
    </div>
</div>
</div>

<script>
function togglePw(id,btn){var i=document.getElementById(id),ic=btn.querySelector('i');i.type=i.type==='password'?'text':'password';ic.className=i.type==='text'?'fas fa-eye-slash':'fas fa-eye';}
function checkStrength(v){var s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[a-z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;var f=document.getElementById('sBar'),t=document.getElementById('sText'),l=['','Very Weak','Weak','Fair','Strong','Very Strong'],c=['','#e53935','#fb8c00','#f9a825','#43a047','#2e7d32'];f.style.width=(s/5*100)+'%';f.style.background=c[s]||'#e5e9f2';t.textContent=v.length?l[s]:'';t.style.color=c[s]||'#9aa3b5';}
function checkRules(v){var r={rLen:v.length>=8,rUpper:/[A-Z]/.test(v),rLower:/[a-z]/.test(v),rNum:/[0-9]/.test(v),rSpec:/[^A-Za-z0-9]/.test(v)};Object.keys(r).forEach(function(id){var el=document.getElementById(id),ic=el.querySelector('i');el.classList.toggle('ok',r[id]);ic.className=r[id]?'fas fa-check-circle':'fas fa-circle';});}
function checkMatch(){var np=document.getElementById('newPw').value,cp=document.getElementById('confirmPw').value,m=document.getElementById('matchMsg'),btn=document.getElementById('submitBtn');if(!cp){m.style.display='none';return;}if(np===cp){m.style.display='block';m.style.color='#2e7d32';m.innerHTML='<i class="fas fa-check-circle"></i> Passwords match';btn.disabled=false;}else{m.style.display='block';m.style.color='#e53935';m.innerHTML='<i class="fas fa-times-circle"></i> Passwords do not match';btn.disabled=true;}}
</script>
@endsection