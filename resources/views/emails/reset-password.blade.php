@extends('emails.layout')

@section('email-content')

<p class="email-greeting">Password Reset Request</p>

<p class="email-text">
    Hi <strong>{{ $user->firstname }}</strong>, we received a request to reset the password
    for your <strong>CityQuants</strong> account associated with this email.
    Click the button below to choose a new password.
</p>

<div class="email-cta-wrap">
    <a href="{{ $link }}" class="email-cta">
        🔑 &nbsp; Reset My Password
    </a>
</div>

<div class="email-info">
    ⏳ <strong>This link expires in 1 hour.</strong>
    If you don't reset your password within that time, you'll need to make a new request.
</div>

<p class="email-text">
    Or copy and paste this URL into your browser:
</p>
<p style="font-size:12px; color:#888; word-break:break-all; margin-bottom:20px;">
    {{ $link }}
</p>

<hr class="email-hr">

<p class="email-text" style="font-size:13px; color:#888; margin-bottom:0;">
    🔒 If you didn't request a password reset, please ignore this email.
    Your password will not change, and your account remains secure.
    If you're concerned, <a href="mailto:support@cityquants.com">contact support</a>.
</p>

@endsection