@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   VARIABLES — mirrors about.blade.php
========================================= */
.qa-login-wrap {
    --gold:    #F5A623;
    --gold2:   #FFD06A;
    --golddim: rgba(245,166,35,.1);
    --d1: #06101A;
    --d2: #091828;
    --d3: #0C2040;
    --d4: #0F2848;
    --card:  #0E1E35;
    --card2: #142540;
    --txt:   #E4EBF5;
    --muted: #7A90B5;
    --bdr:   rgba(255,255,255,.07);
    font-family: 'Exo 2', sans-serif;
    color: #E4EBF5;
    display: block;
    background: #06101A;
    min-height: 100vh;
}
.qa-login-wrap * { box-sizing: border-box; }
.qa-login-wrap h1,.qa-login-wrap h2,.qa-login-wrap h3,.qa-login-wrap h4 {
    font-family: 'Rajdhani', sans-serif;
    letter-spacing: .03em;
}
.qa-login-wrap a { text-decoration: none; }

/* animations */
@keyframes qaFadeUp {
    from { opacity: 0; transform: translateY(32px); }
    to   { opacity: 1; transform: none; }
}
@keyframes gridScroll {
    from { background-position: 0 0; }
    to   { background-position: 64px 64px; }
}
@keyframes pulseBorder {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245,166,35,0); }
    50%       { box-shadow: 0 0 0 6px rgba(245,166,35,.12); }
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
@keyframes otpShake {
    0%,100% { transform: translateX(0); }
    20%      { transform: translateX(-8px); }
    40%      { transform: translateX(8px); }
    60%      { transform: translateX(-5px); }
    80%      { transform: translateX(5px); }
}
@keyframes successPop {
    0%   { transform: scale(.6); opacity: 0; }
    70%  { transform: scale(1.15); }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes countdownShrink {
    from { width: 100%; }
    to   { width: 0%; }
}

/* =========================================
   PAGE SHELL — full-page layout
========================================= */
.qa-login-page {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, #06101A 0%, #0F2848 60%, #06101A 100%);
    padding: 40px 20px;
    margin-top: 80px;
}

/* animated grid lines (same as hero) */
.qa-login-page::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(245,166,35,.055) 1px, transparent 1px),
        linear-gradient(90deg, rgba(245,166,35,.055) 1px, transparent 1px);
    background-size: 64px 64px;
    animation: gridScroll 18s linear infinite;
    pointer-events: none;
}
.qa-login-page::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 70% 65% at 50% 50%, rgba(245,166,35,.07) 0%, transparent 72%);
    pointer-events: none;
}

/* gold top bar */
.qa-login-topbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 50;
    height: 3px;
    background: linear-gradient(90deg, transparent, #F5A623 30%, #FFD06A 50%, #F5A623 70%, transparent);
}

/* =========================================
   CARD
========================================= */
.qa-login-card {
    position: relative; z-index: 2;
    width: 100%; max-width: 460px;
    background: linear-gradient(160deg, #0E1E35 0%, #142540 100%);
    border: 1px solid rgba(245,166,35,.22);
    border-radius: 26px;
    overflow: hidden;
    box-shadow: 0 32px 80px rgba(0,0,0,.6), 0 0 0 1px rgba(245,166,35,.08), 0 0 60px rgba(245,166,35,.08);
    animation: qaFadeUp .7s ease both;
}

/* top shimmer line */
.qa-login-card::before {
    content: '';
    position: absolute; top: 0; left: 24px; right: 24px; height: 2px;
    background: linear-gradient(90deg, transparent, #F5A623, transparent);
    border-radius: 2px;
}

/* ── Card Header ── */
.qa-card-header {
    padding: 38px 40px 28px;
    border-bottom: 1px solid rgba(255,255,255,.07);
    text-align: center;
}
.qa-card-logo {
    display: inline-flex; align-items: center; gap: 12px;
    margin-bottom: 20px;
}
.qa-logo-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: #F5A623;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Rajdhani', sans-serif;
    font-size: 20px; font-weight: 700; color: #000;
    box-shadow: 0 4px 16px rgba(245,166,35,.4);
}
.qa-logo-text {
    font-family: 'Rajdhani', sans-serif;
    font-size: 22px; font-weight: 700; color: #fff;
    letter-spacing: .04em;
}
.qa-logo-text span { color: #F5A623; }
.qa-card-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: 28px; font-weight: 700; color: #fff;
    margin-bottom: 6px; line-height: 1.15;
}
.qa-card-sub {
    font-size: 13.5px; color: #7A90B5;
    line-height: 1.6;
}

/* ── Login Method Tabs ── */
.qa-method-tabs {
    display: flex; gap: 0;
    background: rgba(0,0,0,.2);
    border-bottom: 1px solid rgba(255,255,255,.07);
}
.qa-method-tab {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 16px; cursor: pointer;
    font-family: 'Rajdhani', sans-serif; font-size: 13px; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase;
    color: #7A90B5; border: none; background: none;
    border-bottom: 2px solid transparent;
    transition: all .25s;
}
.qa-method-tab i { font-size: 14px; }
.qa-method-tab.on {
    color: #F5A623;
    border-bottom-color: #F5A623;
    background: rgba(245,166,35,.05);
}
.qa-method-tab:hover:not(.on) { color: rgba(245,166,35,.6); }

/* ── Card Body ── */
.qa-card-body {
    padding: 36px 40px 32px;
}

/* ── OTP Step panels ── */
.qa-step { display: none; }
.qa-step.on { display: block; animation: qaFadeUp .45s ease both; }

/* ── Form elements ── */
.qa-form-label {
    display: block; font-size: 12px; font-weight: 700;
    color: #7A90B5; letter-spacing: .08em; text-transform: uppercase;
    margin-bottom: 8px;
}
.qa-input-wrap {
    position: relative; margin-bottom: 20px;
}
.qa-input-icon {
    position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
    color: #7A90B5; font-size: 14px; transition: color .25s;
    pointer-events: none;
}
.qa-input {
    width: 100%; height: 52px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 12px;
    padding: 0 16px 0 44px;
    font-family: 'Exo 2', sans-serif; font-size: 15px;
    color: #E4EBF5; outline: none;
    transition: border-color .25s, box-shadow .25s, background .25s;
}
.qa-input::placeholder { color: rgba(122,144,181,.5); }
.qa-input:focus {
    border-color: rgba(245,166,35,.5);
    background: rgba(245,166,35,.04);
    box-shadow: 0 0 0 4px rgba(245,166,35,.1);
}
.qa-input:focus + .qa-input-icon,
.qa-input-wrap:focus-within .qa-input-icon { color: #F5A623; }

/* password toggle */
.qa-pass-toggle {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    color: #7A90B5; font-size: 14px; cursor: pointer; background: none; border: none;
    padding: 4px; transition: color .2s;
}
.qa-pass-toggle:hover { color: #F5A623; }

/* OTP boxes */
.qa-otp-boxes {
    display: flex; gap: 10px; justify-content: center;
    margin-bottom: 24px;
}
.qa-otp-box {
    width: 52px; height: 58px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 12px;
    font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700;
    color: #F5A623; text-align: center; outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
    caret-color: #F5A623;
}
.qa-otp-box:focus {
    border-color: rgba(245,166,35,.55);
    background: rgba(245,166,35,.06);
    box-shadow: 0 0 0 4px rgba(245,166,35,.12);
}
.qa-otp-box.filled {
    border-color: rgba(245,166,35,.4);
    background: rgba(245,166,35,.08);
}
.qa-otp-box.error {
    border-color: rgba(255,80,80,.6);
    animation: otpShake .4s ease;
}

/* countdown bar */
.qa-countdown-wrap {
    margin-bottom: 18px;
}
.qa-countdown-track {
    height: 3px; background: rgba(255,255,255,.07);
    border-radius: 2px; overflow: hidden; margin-bottom: 8px;
}
.qa-countdown-bar {
    height: 100%; background: linear-gradient(90deg, #F5A623, #FFD06A);
    border-radius: 2px;
    transition: width 1s linear;
}
.qa-countdown-text {
    display: flex; justify-content: space-between;
    font-size: 12px; color: #7A90B5;
}
.qa-countdown-num { color: #F5A623; font-weight: 700; }

/* info box */
.qa-info-box {
    display: flex; align-items: flex-start; gap: 12px;
    background: rgba(245,166,35,.07); border: 1px solid rgba(245,166,35,.18);
    border-radius: 10px; padding: 14px 16px; margin-bottom: 24px;
}
.qa-info-box i { color: #F5A623; font-size: 14px; margin-top: 1px; flex-shrink: 0; }
.qa-info-box span { font-size: 13px; color: #B0C0D8; line-height: 1.6; }
.qa-info-box strong { color: #F5A623; }

/* error box */
.qa-error-box {
    display: none;
    align-items: flex-start; gap: 12px;
    background: rgba(255,60,60,.07); border: 1px solid rgba(255,60,60,.2);
    border-radius: 10px; padding: 12px 16px; margin-bottom: 18px;
}
.qa-error-box.on { display: flex; animation: qaFadeUp .3s ease; }
.qa-error-box i { color: #ff6b6b; font-size: 13px; margin-top: 2px; flex-shrink: 0; }
.qa-error-box span { font-size: 13px; color: #ffb0b0; }

/* ── Primary button ── */
.qa-btn-primary {
    width: 100%; height: 52px;
    background: #F5A623;
    border: none; border-radius: 12px; cursor: pointer;
    font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase; color: #000;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    box-shadow: 0 4px 20px rgba(245,166,35,.35);
    transition: background .25s, transform .2s, box-shadow .25s;
    position: relative; overflow: hidden;
}
.qa-btn-primary::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.15) 50%, transparent 100%);
    transform: translateX(-100%);
    transition: transform .5s;
}
.qa-btn-primary:hover::before { transform: translateX(100%); }
.qa-btn-primary:hover {
    background: #FFD06A;
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(245,166,35,.45);
}
.qa-btn-primary:active { transform: translateY(0); }
.qa-btn-primary:disabled {
    background: rgba(245,166,35,.35); color: rgba(0,0,0,.5);
    cursor: not-allowed; transform: none; box-shadow: none;
}
/* spinner inside button */
.qa-btn-spinner {
    width: 18px; height: 18px; border: 2px solid rgba(0,0,0,.3);
    border-top-color: #000; border-radius: 50%;
    animation: spin .7s linear infinite;
    display: none;
}
.qa-btn-primary.loading .qa-btn-spinner { display: block; }
.qa-btn-primary.loading .qa-btn-label   { display: none; }

/* ── Ghost button ── */
.qa-btn-ghost {
    background: none; border: none; cursor: pointer;
    font-family: 'Exo 2', sans-serif; font-size: 13px;
    color: #7A90B5; padding: 0;
    transition: color .2s;
    display: inline-flex; align-items: center; gap: 6px;
}
.qa-btn-ghost:hover { color: #F5A623; }
.qa-btn-ghost:disabled { opacity: .4; cursor: not-allowed; }

/* ── Divider ── */
.qa-divider {
    display: flex; align-items: center; gap: 14px;
    margin: 22px 0;
}
.qa-divider::before,.qa-divider::after {
    content: ''; flex: 1; height: 1px;
    background: rgba(255,255,255,.08);
}
.qa-divider-text { font-size: 12px; color: #7A90B5; letter-spacing: .06em; text-transform: uppercase; }

/* ── Password step extras ── */
.qa-remember-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 22px;
}
.qa-checkbox-label {
    display: flex; align-items: center; gap: 8px; cursor: pointer;
    font-size: 13px; color: #7A90B5;
}
.qa-checkbox {
    width: 16px; height: 16px; accent-color: #F5A623; cursor: pointer;
}
.qa-forgot { font-size: 13px; color: #7A90B5; }
.qa-forgot:hover { color: #F5A623; }

/* ── Success state ── */
.qa-success-wrap {
    text-align: center; padding: 10px 0;
}
.qa-success-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: rgba(245,166,35,.12); border: 2px solid rgba(245,166,35,.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; color: #F5A623;
    margin: 0 auto 20px;
    animation: successPop .5s ease both;
}
.qa-success-title {
    font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700;
    color: #fff; margin-bottom: 8px;
}
.qa-success-sub { font-size: 13.5px; color: #7A90B5; margin-bottom: 26px; }

/* ── Card Footer ── */
.qa-card-footer {
    padding: 18px 40px 28px;
    border-top: 1px solid rgba(255,255,255,.07);
    text-align: center;
}
.qa-footer-links {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    font-size: 13px; color: #7A90B5; flex-wrap: wrap;
}
.qa-footer-links a { color: #F5A623; }
.qa-footer-links a:hover { color: #FFD06A; text-decoration: underline; }
.qa-footer-sep { color: rgba(255,255,255,.2); }

/* trust badges */
.qa-trust-row {
    display: flex; align-items: center; justify-content: center; gap: 18px;
    margin-top: 16px; flex-wrap: wrap;
}
.qa-trust-badge {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; color: rgba(122,144,181,.55);
    letter-spacing: .05em;
}
.qa-trust-badge i { color: rgba(245,166,35,.4); font-size: 11px; }

/* ── Back row ── */
.qa-back-row {
    display: flex; align-items: center; margin-bottom: 24px;
}

/* ── Email display pill ── */
.qa-email-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(245,166,35,.08); border: 1px solid rgba(245,166,35,.2);
    border-radius: 30px; padding: 7px 16px;
    font-size: 13px; color: #E4EBF5; margin-bottom: 22px;
    max-width: 100%; overflow: hidden;
}
.qa-email-pill i { color: #F5A623; font-size: 12px; flex-shrink: 0; }
.qa-email-pill span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Resend row ── */
.qa-resend-row {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    margin-top: 16px; font-size: 13px; color: #7A90B5;
}

/* ── Responsive ── */
@media(max-width:520px){
    .qa-card-header { padding: 28px 26px 22px; }
    .qa-card-body   { padding: 28px 26px 24px; }
    .qa-card-footer { padding: 16px 26px 22px; }
    .qa-otp-box     { width: 44px; height: 52px; font-size: 20px; }
    .qa-otp-boxes   { gap: 8px; }
    .qa-method-tab  { font-size: 11px; padding: 13px 10px; }
}
</style>

<div class="qa-login-wrap">
<div class="qa-login-topbar"></div>

<div class="qa-login-page">

    <div class="qa-login-card">

        {{-- ── Header ── --}}
        <div class="qa-card-header">
            <div class="qa-card-logo">
                <div class="qa-logo-icon">Q</div>
                <div class="qa-logo-text">Quants<span>app</span><sup style="font-size:11px;color:#7A90B5;">®</sup></div>
            </div>
            <h2 class="qa-card-title" id="cardTitle">Welcome Back</h2>
            <p class="qa-card-sub" id="cardSub">Sign in to your account to continue trading smarter.</p>
        </div>

        {{-- ── Method Tabs ── --}}
        <div class="qa-method-tabs" id="methodTabs">
            <button class="qa-method-tab on" id="tabOtp" onclick="switchTab('otp')">
                <i class="fas fa-envelope"></i> Email OTP
            </button>
            <button class="qa-method-tab" id="tabPass" onclick="switchTab('password')">
                <i class="fas fa-lock"></i> Password
            </button>
        </div>

        {{-- ── Card Body ── --}}
        <div class="qa-card-body">

            {{-- ════ OTP FLOW ════ --}}

            {{-- Step 1: Enter Email --}}
            <div class="qa-step on" id="stepOtpEmail">
                <label class="qa-form-label">Email Address</label>
                <div class="qa-input-wrap">
                    <input type="email" class="qa-input" id="otpEmailInput"
                           placeholder="your@email.com" autocomplete="email"
                           onkeydown="if(event.key==='Enter') sendOtp()">
                    <i class="fas fa-envelope qa-input-icon"></i>
                </div>
                <div class="qa-error-box" id="otpEmailErr">
                    <i class="fas fa-circle-exclamation"></i>
                    <span id="otpEmailErrMsg">Please enter a valid email address.</span>
                </div>
                <button class="qa-btn-primary" onclick="sendOtp()">
                    <div class="qa-btn-spinner"></div>
                    <span class="qa-btn-label"><i class="fas fa-paper-plane"></i>&nbsp; Send OTP</span>
                </button>
            </div>

            {{-- Step 2: Enter OTP --}}
            <div class="qa-step" id="stepOtpVerify">
                <div class="qa-back-row">
                    <button class="qa-btn-ghost" onclick="goStep('stepOtpEmail')">
                        <i class="fas fa-arrow-left"></i> Change Email
                    </button>
                </div>
                <div style="text-align:center;margin-bottom:20px;">
                    <div class="qa-email-pill">
                        <i class="fas fa-envelope"></i>
                        <span id="otpEmailDisplay">—</span>
                    </div>
                    <p style="font-size:13.5px;color:#7A90B5;line-height:1.7;">
                        We've sent a <strong style="color:#E4EBF5;">6-digit OTP</strong> to your email.<br>
                        Enter it below to sign in.
                    </p>
                </div>

                <div class="qa-otp-boxes" id="otpBoxes">
                    <input class="qa-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="0">
                    <input class="qa-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="1">
                    <input class="qa-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="2">
                    <input class="qa-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="3">
                    <input class="qa-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="4">
                    <input class="qa-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="5">
                </div>

                <div class="qa-countdown-wrap">
                    <div class="qa-countdown-track">
                        <div class="qa-countdown-bar" id="countdownBar" style="width:100%;"></div>
                    </div>
                    <div class="qa-countdown-text">
                        <span>OTP expires in</span>
                        <span class="qa-countdown-num" id="countdownNum">5:00</span>
                    </div>
                </div>

                <div class="qa-error-box" id="otpVerifyErr">
                    <i class="fas fa-circle-exclamation"></i>
                    <span id="otpVerifyErrMsg">Invalid OTP. Please try again.</span>
                </div>

                <button class="qa-btn-primary" id="verifyOtpBtn" onclick="verifyOtp()">
                    <div class="qa-btn-spinner"></div>
                    <span class="qa-btn-label"><i class="fas fa-shield-check"></i>&nbsp; Verify & Sign In</span>
                </button>

                <div class="qa-resend-row">
                    <span>Didn't receive it?</span>
                    <button class="qa-btn-ghost" id="resendBtn" onclick="resendOtp()">
                        <i class="fas fa-rotate-right"></i> Resend OTP
                    </button>
                </div>
            </div>

            {{-- Step 3: Success --}}
            <div class="qa-step" id="stepSuccess">
                <div class="qa-success-wrap">
                    <div class="qa-success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="qa-success-title">Login Successful!</div>
                    <p class="qa-success-sub">Welcome back, trader. Redirecting you to your dashboard…</p>
                    <div style="width:100%;height:3px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden;">
                        <div id="redirectBar" style="height:100%;width:0%;background:linear-gradient(90deg,#F5A623,#FFD06A);border-radius:2px;transition:width 2.8s linear;"></div>
                    </div>
                </div>
            </div>

            {{-- ════ PASSWORD FLOW ════ --}}

            <div class="qa-step" id="stepPassword">
                <label class="qa-form-label">Email Address</label>
                <div class="qa-input-wrap" style="margin-bottom:16px;">
                    <input type="email" class="qa-input" id="passEmailInput"
                           placeholder="your@email.com" autocomplete="email">
                    <i class="fas fa-envelope qa-input-icon"></i>
                </div>
                <label class="qa-form-label">Password</label>
                <div class="qa-input-wrap" style="margin-bottom:16px;position:relative;">
                    <input type="password" class="qa-input" id="passInput"
                           placeholder="Enter your password" autocomplete="current-password"
                           style="padding-right:48px;"
                           onkeydown="if(event.key==='Enter') doPasswordLogin()">
                    <i class="fas fa-lock qa-input-icon"></i>
                    <button class="qa-pass-toggle" type="button" onclick="togglePass()" id="passToggleBtn">
                        <i class="fas fa-eye" id="passToggleIcon"></i>
                    </button>
                </div>
                <div class="qa-remember-row">
                    <label class="qa-checkbox-label">
                        <input type="checkbox" class="qa-checkbox" name="remember"> Remember me
                    </label>
                    <a href="" class="qa-forgot">Forgot Password?</a>
                </div>
                <div class="qa-error-box" id="passErr">
                    <i class="fas fa-circle-exclamation"></i>
                    <span id="passErrMsg">Invalid email or password.</span>
                </div>
                <button class="qa-btn-primary" onclick="doPasswordLogin()">
                    <div class="qa-btn-spinner"></div>
                    <span class="qa-btn-label"><i class="fas fa-right-to-bracket"></i>&nbsp; Sign In</span>
                </button>

                <div class="qa-divider"><span class="qa-divider-text">or sign in with OTP</span></div>
                <button class="qa-btn-ghost" style="width:100%;justify-content:center;border:1px solid rgba(255,255,255,.1);padding:12px;border-radius:10px;font-size:13px;" onclick="switchTab('otp')">
                    <i class="fas fa-envelope"></i> Use Email OTP Instead
                </button>
            </div>

        </div>{{-- /.qa-card-body --}}

        {{-- ── Footer ── --}}
        <div class="qa-card-footer" id="cardFooter">
            {{-- <div class="qa-footer-links">
                <span>Don't have an account?</span>
                <a href="">Create Account</a>
                <span class="qa-footer-sep">|</span>
                <a href="{{ route('user.home') }}">Back to Home</a>
            </div> --}}
            <div class="qa-trust-row">
                <span class="qa-trust-badge"><i class="fas fa-shield-halved"></i> SSL Secured</span>
                <span class="qa-trust-badge"><i class="fas fa-lock"></i> 256-bit Encryption</span>
                <span class="qa-trust-badge"><i class="fas fa-user-shield"></i> SEBI Regulated</span>
            </div>
        </div>

    </div>{{-- /.qa-login-card --}}

</div>{{-- /.qa-login-page --}}
</div>{{-- /.qa-login-wrap --}}

<script>
/* ========================================================
   CONFIG — replace these endpoints with your real routes
======================================================== */
const SEND_OTP_URL   = "";   // POST: {email}
const VERIFY_OTP_URL = ""; // POST: {email, otp}
const PASSWORD_URL   = "{{ route('user.login') }}";            // POST: {username/email, password}
const CSRF_TOKEN     = "{{ csrf_token() }}";
const REDIRECT_URL   = "{{ route('user.home') }}";

/* ========================================================
   STATE
======================================================== */
let currentEmail = '';
let countdownInterval = null;
let countdownSecs = 300;

/* ========================================================
   TAB SWITCH
======================================================== */
function switchTab(tab){
    document.getElementById('tabOtp').classList.toggle('on',  tab==='otp');
    document.getElementById('tabPass').classList.toggle('on', tab==='password');

    if(tab === 'otp'){
        goStep('stepOtpEmail');
        document.getElementById('cardTitle').textContent = 'Welcome Back';
        document.getElementById('cardSub').textContent   = 'Sign in with a one-time password sent to your email.';
    } else {
        goStep('stepPassword');
        document.getElementById('cardTitle').textContent = 'Sign In';
        document.getElementById('cardSub').textContent   = 'Enter your credentials to access your account.';
    }
}

/* ========================================================
   STEP NAVIGATION
======================================================== */
function goStep(id){
    document.querySelectorAll('.qa-step').forEach(function(s){ s.classList.remove('on'); });
    document.getElementById(id).classList.add('on');
}

/* ========================================================
   SEND OTP
======================================================== */
async function sendOtp(){
    const email = document.getElementById('otpEmailInput').value.trim();
    const errBox = document.getElementById('otpEmailErr');
    const errMsg = document.getElementById('otpEmailErrMsg');
    const btn    = document.querySelector('#stepOtpEmail .qa-btn-primary');

    // client-side validation
    if(!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
        errMsg.textContent = 'Please enter a valid email address.';
        errBox.classList.add('on');
        return;
    }
    errBox.classList.remove('on');
    btnLoading(btn, true);

    try {
        const res = await fetch(SEND_OTP_URL, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({ email })
        });
        const data = await res.json();

        if(res.ok && data.status === 'success'){
            currentEmail = email;
            document.getElementById('otpEmailDisplay').textContent = email;
            goStep('stepOtpVerify');
            document.getElementById('cardTitle').textContent = 'Enter OTP';
            document.getElementById('cardSub').textContent   = 'Check your inbox for the 6-digit code.';
            // hide tabs during OTP verify
            document.getElementById('methodTabs').style.display = 'none';
            initOtpBoxes();
            startCountdown();
        } else {
            errMsg.textContent = data.message || 'Failed to send OTP. Please try again.';
            errBox.classList.add('on');
        }
    } catch(e){
        errMsg.textContent = 'Network error. Please check your connection.';
        errBox.classList.add('on');
    }

    btnLoading(btn, false);
}

/* ========================================================
   OTP BOXES — keyboard nav + auto-advance
======================================================== */
function initOtpBoxes(){
    const boxes = document.querySelectorAll('.qa-otp-box');
    boxes.forEach(function(box){
        box.value = '';
        box.classList.remove('filled','error');
    });
    boxes[0].focus();

    boxes.forEach(function(box, i){
        box.oninput = function(e){
            const val = e.data || box.value;
            // accept only digits
            box.value = val.replace(/\D/g,'').slice(-1);
            box.classList.toggle('filled', box.value !== '');
            if(box.value && i < 5) boxes[i+1].focus();
            checkAllFilled();
        };
        box.onkeydown = function(e){
            if(e.key === 'Backspace'){
                if(!box.value && i > 0){ boxes[i-1].focus(); boxes[i-1].value=''; boxes[i-1].classList.remove('filled'); }
            }
            if(e.key === 'Enter') verifyOtp();
        };
        // handle paste
        box.onpaste = function(e){
            e.preventDefault();
            const pasted = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
            pasted.split('').forEach(function(ch,j){
                if(boxes[j]){ boxes[j].value=ch; boxes[j].classList.add('filled'); }
            });
            if(boxes[Math.min(pasted.length, 5)]) boxes[Math.min(pasted.length, 5)].focus();
            checkAllFilled();
        };
    });
}

function checkAllFilled(){
    const boxes = document.querySelectorAll('.qa-otp-box');
    const allFilled = [...boxes].every(function(b){ return b.value !== ''; });
    const btn = document.getElementById('verifyOtpBtn');
    // auto-submit when all filled
    if(allFilled) verifyOtp();
}

function getOtpValue(){
    return [...document.querySelectorAll('.qa-otp-box')].map(function(b){ return b.value; }).join('');
}

/* ========================================================
   COUNTDOWN TIMER
======================================================== */
function startCountdown(){
    clearInterval(countdownInterval);
    countdownSecs = 300; // 5 minutes
    updateCountdownUI();

    countdownInterval = setInterval(function(){
        countdownSecs--;
        updateCountdownUI();
        if(countdownSecs <= 0){
            clearInterval(countdownInterval);
            // disable verify button
            document.getElementById('verifyOtpBtn').disabled = true;
        }
    }, 1000);
}

function updateCountdownUI(){
    const mins = Math.floor(countdownSecs / 60);
    const secs = countdownSecs % 60;
    document.getElementById('countdownNum').textContent = mins + ':' + (secs<10?'0':'') + secs;
    document.getElementById('countdownBar').style.width = ((countdownSecs / 300) * 100) + '%';

    // turn bar red when < 60s
    const bar = document.getElementById('countdownBar');
    if(countdownSecs < 60){
        bar.style.background = 'linear-gradient(90deg, #ff4444, #ff8800)';
        document.getElementById('countdownNum').style.color = '#ff6b6b';
    }
}

/* ========================================================
   VERIFY OTP
======================================================== */
async function verifyOtp(){
    const otp    = getOtpValue();
    const errBox = document.getElementById('otpVerifyErr');
    const errMsg = document.getElementById('otpVerifyErrMsg');
    const btn    = document.getElementById('verifyOtpBtn');

    if(otp.length < 6){
        errMsg.textContent = 'Please enter all 6 digits of the OTP.';
        errBox.classList.add('on');
        return;
    }
    if(countdownSecs <= 0){
        errMsg.textContent = 'Your OTP has expired. Please request a new one.';
        errBox.classList.add('on');
        return;
    }

    errBox.classList.remove('on');
    btnLoading(btn, true);

    try {
        const res = await fetch(VERIFY_OTP_URL, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({ email: currentEmail, otp })
        });
        const data = await res.json();

        if(res.ok && data.status === 'success'){
            clearInterval(countdownInterval);
            // Show success
            document.getElementById('methodTabs').style.display = 'none';
            document.getElementById('cardTitle').textContent = '🎉 Verified!';
            document.getElementById('cardSub').textContent   = '';
            document.getElementById('cardFooter').style.display = 'none';
            goStep('stepSuccess');
            // animate redirect bar then redirect
            setTimeout(function(){
                document.getElementById('redirectBar').style.width = '100%';
            }, 100);
            setTimeout(function(){
                window.location.href = data.redirect || REDIRECT_URL;
            }, 3000);
        } else {
            // shake otp boxes
            document.querySelectorAll('.qa-otp-box').forEach(function(b){
                b.classList.add('error');
                setTimeout(function(){ b.classList.remove('error'); }, 600);
            });
            errMsg.textContent = data.message || 'Invalid OTP. Please try again.';
            errBox.classList.add('on');
        }
    } catch(e){
        errMsg.textContent = 'Network error. Please check your connection.';
        errBox.classList.add('on');
    }

    btnLoading(btn, false);
}

/* ========================================================
   RESEND OTP
======================================================== */
async function resendOtp(){
    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

    try {
        const res = await fetch(SEND_OTP_URL, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({ email: currentEmail })
        });
        const data = await res.json();

        if(res.ok && data.status === 'success'){
            // reset boxes & countdown
            initOtpBoxes();
            startCountdown();
            document.getElementById('verifyOtpBtn').disabled = false;
            document.getElementById('otpVerifyErr').classList.remove('on');
            // reset bar colour
            document.getElementById('countdownBar').style.background = 'linear-gradient(90deg,#F5A623,#FFD06A)';
            document.getElementById('countdownNum').style.color = '#F5A623';
        }
    } catch(e){}

    // re-enable resend after 30s cooldown
    setTimeout(function(){
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-rotate-right"></i> Resend OTP';
    }, 30000);
}

/* ========================================================
   PASSWORD LOGIN
======================================================== */
async function doPasswordLogin(){
    const email    = document.getElementById('passEmailInput').value.trim();
    const password = document.getElementById('passInput').value;
    const errBox   = document.getElementById('passErr');
    const errMsg   = document.getElementById('passErrMsg');
    const btn      = document.querySelector('#stepPassword .qa-btn-primary');

    if(!email || !password){
        errMsg.textContent = 'Please enter your email and password.';
        errBox.classList.add('on'); return;
    }
    errBox.classList.remove('on');
    btnLoading(btn, true);

    try {
        const res = await fetch(PASSWORD_URL, {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify({ username: email, password })
        });
        const data = await res.json();

        if(res.ok && data.status === 'success'){
            window.location.href = data.redirect || REDIRECT_URL;
        } else {
            errMsg.textContent = data.message || 'Invalid credentials. Please try again.';
            errBox.classList.add('on');
        }
    } catch(e){
        errMsg.textContent = 'Network error. Please check your connection.';
        errBox.classList.add('on');
    }

    btnLoading(btn, false);
}

/* ========================================================
   PASSWORD VISIBILITY TOGGLE
======================================================== */
function togglePass(){
    const inp = document.getElementById('passInput');
    const ico = document.getElementById('passToggleIcon');
    if(inp.type === 'password'){
        inp.type = 'text';
        ico.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'fas fa-eye';
    }
}

/* ========================================================
   BUTTON LOADING STATE
======================================================== */
function btnLoading(btn, state){
    btn.disabled = state;
    btn.classList.toggle('loading', state);
}
</script>

@endsection