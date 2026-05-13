@extends($activeTemplate.'layouts.frontend')
@section('content')

@include($activeTemplate.'partials.auth-shell')

<div class="ql-page">
<div class="ql-card">

    <div class="ql-left">

        <a href="{{ route('home') }}" class="ql-logo">
            <div class="ql-logo-icon">Q</div>
            <div>
                <div class="ql-logo-name">CityQuants<sup style="font-size:9px">®</sup></div>
                <div class="ql-logo-sub">Optimize Opportunities</div>
            </div>
        </a>

        <h1 class="ql-heading">Reset Password</h1>
        <p style="font-size:14px; color:#666; margin-bottom:24px;">
            Choose a new strong password for your account.
        </p>

        <form method="POST" action="{{ route('user.reset.password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="ql-input-group">
                <label class="ql-input-label">NEW PASSWORD</label>
                <div class="ql-pw-wrap">
                    <input class="ql-input-field {{ $errors->has('password') ? 'is-error' : '' }}"
                           type="password" name="password"
                           placeholder="Minimum 8 characters" id="newPw"
                           autocomplete="new-password">
                    <button type="button" class="ql-pw-toggle" onclick="togglePassword(this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @error('password') <div class="ql-input-error">{{ $message }}</div> @enderror
            </div>

            <div class="ql-input-group">
                <label class="ql-input-label">CONFIRM PASSWORD</label>
                <div class="ql-pw-wrap">
                    <input class="ql-input-field" type="password" name="password_confirmation"
                           placeholder="Repeat password" autocomplete="new-password">
                    <button type="button" class="ql-pw-toggle" onclick="togglePassword(this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            {{-- Strength bar --}}
            <div style="margin-bottom:18px;">
                <div style="height:4px; background:#eee; border-radius:4px; overflow:hidden;">
                    <div id="strengthBar" style="height:100%; width:0; transition:all .3s; border-radius:4px;"></div>
                </div>
                <div id="strengthLabel" style="font-size:12px; color:#bbb; margin-top:4px;"></div>
            </div>

            <button type="submit" class="ql-cta-btn">
                <i class="fas fa-lock"></i> Reset &amp; Login
            </button>

            @error('token')
            <div style="margin-top:12px; padding:12px; background:#fce4e4; border-radius:8px; font-size:13px; color:#c62828;">
                {{ $message }}
            </div>
            @enderror
        </form>

    </div>

    @include($activeTemplate.'partials.auth-right')

</div>
</div>

<script>
document.getElementById('newPw').addEventListener('input', function() {
    var val = this.value;
    var score = 0;
    if (val.length >= 8)       score++;
    if (/[A-Z]/.test(val))     score++;
    if (/[0-9]/.test(val))     score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var bar    = document.getElementById('strengthBar');
    var label  = document.getElementById('strengthLabel');
    var colors = ['#e53935','#f57c00','#fbc02d','#2e7d32'];
    var labels = ['Weak','Fair','Good','Strong'];
    var widths = ['25%','50%','75%','100%'];

    if (!val.length) { bar.style.width='0'; label.textContent=''; return; }
    var i = Math.min(score-1, 3);
    bar.style.width      = widths[i];
    bar.style.background = colors[i];
    label.textContent    = labels[i];
    label.style.color    = colors[i];
});
</script>

@endsection