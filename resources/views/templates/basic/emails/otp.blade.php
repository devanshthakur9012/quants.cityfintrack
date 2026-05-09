<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your OTP – {{ $siteName }}</title>
<style>
  body { margin:0; padding:0; background:#06101A; font-family:'Segoe UI',Arial,sans-serif; }
  .wrap { max-width:520px; margin:0 auto; padding:40px 20px; }
  .card {
    background:linear-gradient(160deg,#0E1E35,#142540);
    border:1px solid rgba(245,166,35,.25);
    border-radius:20px; overflow:hidden;
  }
  .top-bar { height:3px; background:linear-gradient(90deg,transparent,#F5A623,#FFD06A,#F5A623,transparent); }
  .inner   { padding:40px 36px; }
  .logo    { font-size:24px; font-weight:700; color:#fff; margin-bottom:28px; letter-spacing:.04em; }
  .logo span { color:#F5A623; }
  h1       { font-size:22px; color:#fff; margin:0 0 8px; font-weight:600; }
  .sub     { font-size:14px; color:#7A90B5; margin:0 0 32px; line-height:1.6; }
  .otp-box {
    background:rgba(245,166,35,.08);
    border:1px solid rgba(245,166,35,.3);
    border-radius:14px; padding:22px;
    text-align:center; margin-bottom:28px;
  }
  .otp-label { font-size:11px; color:#7A90B5; letter-spacing:.12em; text-transform:uppercase; margin-bottom:10px; }
  .otp-code  {
    font-size:42px; font-weight:700; color:#F5A623;
    letter-spacing:12px; font-family:'Courier New',monospace;
  }
  .otp-exp   { font-size:12px; color:#7A90B5; margin-top:10px; }
  .info-box  {
    background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
    border-radius:10px; padding:16px 18px; margin-bottom:28px;
  }
  .info-box p { font-size:13px; color:#7A90B5; margin:4px 0; line-height:1.65; }
  .info-box strong { color:#E4EBF5; }
  .footer-note { font-size:12px; color:rgba(122,144,181,.5); text-align:center; line-height:1.7; }
  .footer-note a { color:rgba(245,166,35,.6); text-decoration:none; }
  .bottom  { padding:20px 36px 28px; border-top:1px solid rgba(255,255,255,.07); text-align:center; }
  .bottom p { font-size:11px; color:rgba(122,144,181,.4); margin:0; line-height:1.8; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="top-bar"></div>
    <div class="inner">

      <div class="logo">Quants<span>app</span><sup style="font-size:11px;color:rgba(122,144,181,.6);">®</sup></div>

      <h1>Your Login OTP</h1>
      <p class="sub">Hi {{ $user->fullname ?? $user->username }},<br>
        Use the code below to sign in to your {{ $siteName }} account.</p>

      <div class="otp-box">
        <div class="otp-label">One-Time Password</div>
        <div class="otp-code">{{ $otp }}</div>
        <div class="otp-exp">⏱ Valid for {{ $expMins }} minutes</div>
      </div>

      <div class="info-box">
        <p>🔒 <strong>Keep this code private.</strong> {{ $siteName }} will never ask for your OTP via phone or chat.</p>
        <p>📍 If you didn't request this, your account is safe — simply ignore this email.</p>
      </div>

      <p class="footer-note">
        Having trouble? <a href="mailto:support@quantsapp.com">Contact Support</a><br>
        © {{ date('Y') }} {{ $siteName }}. All rights reserved.
      </p>

    </div>
    <div class="bottom">
      <p>This is an automated message — please do not reply.<br>
         Quantsapp Private Ltd. · Lower Parel, Mumbai 400013</p>
    </div>
  </div>
</div>
</body>
</html>