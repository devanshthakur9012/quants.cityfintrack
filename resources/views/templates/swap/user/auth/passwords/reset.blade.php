@extends($activeTemplate . 'layouts.app')

@php
$authImage = getContent('auth_image.content', true);
@endphp

@section('panel')
    <!-- Account Section -->
    <div class="account-section pt-60 pb-60">
        <img src="{{ getImage('assets/images/frontend/auth_image/' . @$authImage->data_values->image, '1920x840') }}"
            alt="bg" class="accout-bg">
        <div class="account-wrapper">
            <a href="{{ route('home') }}" class="logo mb-4"><img src="{{ getImage(getFilePath('logoIcon') . '/dark_logo.png') }}"
                    alt="logo"></a>
            <form method="POST" action="{{ route('user.password.update') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-4">
                    <p>@lang('Your account is verified successfully. Now you can change your password. Please enter a strong password and don\'t share it with anyone').</p>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">@lang('Password')</label>
                    <div class="position-relative">
                        <input id="password" type="password" class="form--control form-control" name="password" required>
                        @if ($general->secure_password)
                            <div class="input-popup">
                                <p class="error lower">@lang('1 small letter minimum')</p>
                                <p class="error capital">@lang('1 capital letter minimum')</p>
                                <p class="error number">@lang('1 number minimum')</p>
                                <p class="error special">@lang('1 special character minimum')</p>
                                <p class="error minimum">@lang('6 character password')</p>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">@lang('Confirm Password')</label>
                    <input id="password-confirm" type="password" class="form--control form-control"
                        name="password_confirmation" required>
                </div>
                <div class="form-group mb-3">
                    <button type="submit" class="btn btn--base mt-4 w-100">@lang('Submit')</button>
                </div>
                <div class="mt-3 mt-sm-4">
                    <div class="text-center">
                        @lang('Login into your') <a href="{{ route('user.login') }}"
                            class="text-decoration-underline">@lang('account')</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Account Section -->
@endsection

@if($general->secure_password)
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif
