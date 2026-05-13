<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>CityQuants</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body {
            background:#FDECC8; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
            font-size:15px; color:#1a1a1a; -webkit-font-smoothing:antialiased;
        }
        a { color:#F5A623; text-decoration:none; }
        a:hover { text-decoration:underline; }

        .email-wrap   { width:100%; background:#FDECC8; padding:40px 16px; }
        .email-card   {
            max-width:580px; margin:0 auto;
            background:#F0EFED; border-radius:20px;
            overflow:hidden; box-shadow:0 12px 48px rgba(0,0,0,.12);
        }

        /* Header */
        .email-header {
            background:#1a1a1a; padding:28px 40px;
            display:flex; align-items:center; gap:14px;
        }
        .email-logo-icon {
            width:46px; height:46px; border-radius:10px; background:#F5A623;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:22px; font-weight:700; color:#fff;
            font-family:Georgia,serif; flex-shrink:0;
        }
        .email-logo-text { color:#fff; }
        .email-logo-name { font-size:20px; font-weight:700; letter-spacing:.01em; }
        .email-logo-sub  { font-size:11px; color:#aaa; letter-spacing:.05em; margin-top:2px; }

        /* Body */
        .email-body { padding:40px 40px 32px; }
        @media(max-width:480px){ .email-body { padding:28px 24px 24px; } }

        .email-greeting {
            font-size:22px; font-weight:700; color:#1a1a1a;
            margin-bottom:12px; line-height:1.2;
        }
        .email-text {
            font-size:14px; color:#555; line-height:1.7;
            margin-bottom:20px;
        }

        /* OTP box */
        .otp-wrap { text-align:center; margin:28px 0; }
        .otp-box  {
            display:inline-block; background:#1a1a1a; color:#F5A623;
            font-size:40px; font-weight:700; letter-spacing:18px;
            padding:18px 28px 18px 46px; border-radius:14px;
            font-family:Georgia,'Courier New',monospace;
        }
        .otp-label { font-size:12px; color:#888; margin-top:10px; }

        /* CTA button */
        .email-cta-wrap { text-align:center; margin:28px 0; }
        .email-cta {
            display:inline-block; background:#F5A623; color:#fff !important;
            font-size:16px; font-weight:700; letter-spacing:.06em;
            padding:16px 40px; border-radius:10px; text-decoration:none !important;
        }

        /* Info box */
        .email-info {
            background:#fff; border-left:4px solid #F5A623;
            border-radius:0 8px 8px 0; padding:14px 18px;
            font-size:13px; color:#555; margin-bottom:20px; line-height:1.6;
        }

        /* Divider */
        .email-hr { border:none; border-top:1px solid #ddd; margin:24px 0; }

        /* Footer */
        .email-footer {
            background:#1a1a1a; padding:24px 40px;
            text-align:center;
        }
        .email-footer p { font-size:12px; color:#777; line-height:1.7; }
        .email-footer a { color:#F5A623; font-size:12px; }
        .email-footer .footer-links { margin-bottom:10px; }
        .email-footer .footer-links a { margin:0 8px; }
    </style>
</head>
<body>
<div class="email-wrap">
<div class="email-card">

    {{-- Header --}}
    <div class="email-header">
        <div class="email-logo-icon">Q</div>
        <div class="email-logo-text">
            <div class="email-logo-name">CityQuants</div>
            <div class="email-logo-sub">OPTIMIZE OPPORTUNITIES</div>
        </div>
    </div>

    {{-- Body --}}
    <div class="email-body">
        @yield('email-content')
    </div>

    {{-- Footer --}}
    <div class="email-footer">
        <div class="footer-links">
            <a href="{{ config('app.url') }}">Home</a>
            <a href="{{ config('app.url') }}/privacy">Privacy</a>
            <a href="{{ config('app.url') }}/terms">Terms</a>
        </div>
        <p>
            &copy; {{ date('Y') }} CityQuants. All rights reserved.<br>
            This email was sent because an action was performed on your account.<br>
            If this wasn't you, please <a href="mailto:support@cityquants.com">contact support</a>.
        </p>
    </div>

</div>
</div>
</body>
</html>