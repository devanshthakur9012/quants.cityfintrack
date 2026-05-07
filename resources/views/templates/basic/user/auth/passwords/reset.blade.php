@extends($activeTemplate.'layouts.frontend')

@php
    $authImage = getContent('auth_image.content', true);
@endphp

@section('content')
<section class="registration-section pt-100 pb-100">
    <div class="el-1"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->left_image, '450x590') }}" alt="@lang('image')"></div>
    <div class="el-2"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->right_image, '450x335') }}" alt="@lang('image')"></div>
    <div class="container content-container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="registration-wrapper section--bg">
                    <form class="transparent-form" method="POST" action="{{ route('user.password.update') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="row">
                            <div class="mb-4">
                                <p>
                                    @lang('Your account is verified successfully. Now you can change your password. Please enter a strong password and don\'t share it with anyone').
                                </p>
                            </div>
                            <div class="col-lg-12 form-group">
                                <label for="password">@lang('Password') <sup class="text--danger">*</sup></label>
                                <div class="custom-icon-field">
                                <i class="las la-key"></i>
                                <input id="password" type="password" class="form--control" name="password" required placeholder="@lang('Password')">
                                @if($general->secure_password)
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
                            <div class="col-lg-12 form-group">
                                <label for="password-confirm">@lang('Confirm Password') <sup class="text--danger">*</sup></label>
                                <div class="custom-icon-field">
                                <i class="las la-key"></i>
                                <input id="password-confirm" type="password" class="form--control" name="password_confirmation" required placeholder="@lang('Enter confirm password')">
                                </div>
                            </div>
                            <div class="col-lg-12 mt-3">
                                <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                                <p class="text-center mt-3">
                                    @lang("Login into your") <a href="{{ route('user.login') }}" class="text--base">@lang('account')</a>
                                </p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

{{-- @if($general->secure_password)
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif --}}
