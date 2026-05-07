@extends($activeTemplate .'layouts.app')

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
                            <form action="{{route('user.verify.email')}}" method="POST" class="submit-form">
                                @csrf
                                <p class="verification-text mb-2 text-dark">@lang('A 6 digit verification code sent to your email address'):  {{ showEmailAddress(auth()->user()->email) }}</p>
    
                                @include($activeTemplate.'partials.verification_code')
    
                                <div class="form-group">
                                    <button type="submit" class="btn btn--base custom-submit-btn w-100">@lang('Submit')</button>
                                </div>
    
                                <div class="form-group verification-area__text mt-3 text-dark">
                                    @lang('If you don\'t get any code'), <a href="{{route('user.send.verify.code', 'email')}}"> @lang('Try again')</a>
                                    @if($errors->has('resend'))
                                        <small class="text--danger d-block">{{ $errors->first('resend') }}</small>
                                    @endif
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

