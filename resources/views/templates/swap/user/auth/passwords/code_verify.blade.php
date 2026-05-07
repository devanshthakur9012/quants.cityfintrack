@extends($activeTemplate.'layouts.app')

@php
    $authImage = getContent('auth_image.content', true);
@endphp

@section('panel')
<div class="account-section pt-60 pb-60">
    <img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->image, '1920x840') }}" alt="bg" class="accout-bg">
    <div class="account-wrapper removeBg">
        <a href="{{ route('home') }}" class="logo mb-4 justify-content-center d-flex">
            <img src="{{getImage(getFilePath('logoIcon') .'/dark_logo.png')}}" alt="logo">
        </a>
        <div class="container">
            <div class="row justify-content-center">
                <div class="d-flex justify-content-center">
                    <div class="verification-code-wrapper">
                        <div class="verification-area">
                            <h5 class="pb-3 text-center border-bottom text-dark mb-3">@lang('Verify Email Address')</h5>
                            <form action="{{ route('user.password.verify.code') }}" method="POST" class="submit-form">
                                @csrf
                                <p class="verification-text">@lang('A 6 digit verification code sent to your email address') :  {{ showEmailAddress($email) }}</p>
                                <input type="hidden" name="email" value="{{ $email }}">
    
                                @include($activeTemplate.'partials.verification_code')
    
                                <div class="form-group">
                                    <button type="submit" class="btn btn--base custom-submit-btn w-100">@lang('Submit')</button>
                                </div>
    
                                <div class="form-group verification-area__text mt-3">
                                    @lang('Please check including your Junk/Spam Folder. if not found, you can')
                                    <a href="{{ route('user.password.request') }}" class="text--base">@lang('Try to send again')</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
