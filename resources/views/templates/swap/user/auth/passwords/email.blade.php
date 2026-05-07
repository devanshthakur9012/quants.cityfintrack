@extends($activeTemplate.'layouts.app')

@php
    $authImage = getContent('auth_image.content', true);
@endphp

@section('panel') 
<div class="account-section pt-60 pb-60">
    <img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->image, '1920x840') }}" alt="bg" class="accout-bg">
    <div class="account-wrapper">
        <a href="{{ route('home') }}" class="logo mb-4"><img src="{{getImage(getFilePath('logoIcon') .'/dark_logo.png')}}" alt="logo"></a>
        <form method="POST" action="{{ route('user.password.email') }}" class="verify-gcaptcha">
            @csrf
            <div class="mb-4"> 
                <p>@lang('Please provide your email username to find your account').</p> 
            </div>
            <div class="form-group">
                <label class="form-label">@lang('Username or Email')</label>
                <input type="text" class="form--control form-control @error('value') is-invalid @enderror" name="value" value="{{ old('value') }}" required autofocus="off">
                <div class="custom-icon-field">
                    @error('value')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>
            <div class="form-group mt-4">
                <x-captcha />
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn--base mt-4 w-100">@lang('Submit')</button>
            </div>
            <div class="mt-3 mt-sm-4">
                <div class="text-center">
                    @lang("Login into your") <a href="{{ route('user.login') }}" class="text-decoration-underline">@lang('account')</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
