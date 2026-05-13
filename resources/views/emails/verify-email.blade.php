@extends('emails.layout')

@section('email-content')

<p class="email-greeting">Welcome to CityQuants, {{ $user->firstname }}! 🎉</p>

<p class="email-text">
    Thanks for signing up! You're one step away from unlocking
    <strong>25+ real-time trading tools</strong> and advanced options analytics.
    Click the button below to verify your email and set your password.
</p>

<div class="email-cta-wrap">
    <a href="{{ $link }}" class="email-cta">
        ✓ &nbsp; Verify Email &amp; Set Password
    </a>
</div>

<div class="email-info">
    ⏳ <strong>This link expires in 24 hours.</strong>
    After verifying, you'll be prompted to create your password and log in immediately.
</div>

<p class="email-text">
    Or copy and paste this URL into your browser:
</p>
<p style="font-size:12px; color:#888; word-break:break-all; margin-bottom:20px;">
    {{ $link }}
</p>

<hr class="email-hr">

<p class="email-text" style="font-size:13px; color:#888; margin-bottom:0;">
    If you didn't create a CityQuants account, you can safely ignore this email.
    No account will be created without verification.
</p>

@endsection