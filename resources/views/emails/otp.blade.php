@extends('emails.layout')

@section('email-content')

<p class="email-greeting">Hi {{ $user->firstname }} 👋</p>

<p class="email-text">
    You requested a one-time password (OTP) to log in to your <strong>CityQuants</strong> account.
    Use the code below:
</p>

<div class="otp-wrap">
    <div class="otp-box">{{ $otp }}</div>
    <p class="otp-label">
        <strong>⏱ Expires in 10 minutes.</strong> Do not share this code with anyone.
    </p>
</div>

<div class="email-info">
    🔒 <strong>Security tip:</strong> CityQuants will never ask for your OTP over phone or chat.
    If you didn't request this, please ignore this email — your account is safe.
</div>

<hr class="email-hr">

<p class="email-text" style="font-size:13px; color:#888; margin-bottom:0;">
    Didn't request this OTP? No action is needed. Your account remains secure.
</p>

@endsection