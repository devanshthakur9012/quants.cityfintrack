@extends($activeTemplate.'layouts.app')

@php
    $authImage = getContent('auth_image.content', true);
@endphp

@section('panel')
<!-- Account Section -->
<div class="account-section pt-60 pb-60">
    <img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->image, '1920x840') }}" alt="bg" class="accout-bg">
    <div class="account-wrapper">
        <a href="{{ route('home') }}" class="logo mb-4"><img src="{{getImage(getFilePath('logoIcon') .'/dark_logo.png')}}" alt="logo"></a>
        <form method="POST" action="{{ route('user.login')}}" class="verify-gcaptcha">
            @csrf
            <div class="form-group mb-3">
                <label class="form-label">@lang('Username or Email')</label>
                <input type="text" name="username" value="{{ old('username') }}" class="form--control form-control" required>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">@lang('Password')</label>
                <input id="password" type="password" class="form--control form-control" name="password" required>
            </div>

            <div class="col-lg-12 form-group">
                <x-captcha></x-captcha>
            </div>

            <div class="form-group custom--checkbox d-flex justify-content-between flex-wrap">
                <div>
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">@lang('Remember Me')</label>
                </div>
                <a href="{{ route('user.password.request') }}" class="text--base">@lang('Forgot Password')?</a>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn--base mt-4 w-100">@lang('Login')</button>
            </div>
            <!-- <div class="mt-3 mt-sm-4">
                <div class="text-center">
                    @lang("Don't have an account")? <a href="" class="text-decoration-underline text--base">@lang('Create Account')</a>
                </div>
            </div> -->
        </form>
    </div>
</div>
<!-- Account Section -->
@endsection
