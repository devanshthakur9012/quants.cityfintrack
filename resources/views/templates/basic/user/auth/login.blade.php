@extends($activeTemplate . 'layouts.frontend')

@php
$authImage = getContent('auth_image.content', true);
@endphp

@section('content')
    <!-- Login section start -->
    <section class="registration-section pt-100 pb-100">
        <div class="el-1">
            <img src="{{ getImage('assets/images/frontend/auth_image/' . @$authImage->data_values->left_image, '450x590') }}" alt="@lang('image')">
        </div>
        <div class="el-2">
            <img src="{{ getImage('assets/images/frontend/auth_image/' . @$authImage->data_values->right_image, '450x335') }}" alt="@lang('image')">
        </div>
        <div class="container content-container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="registration-wrapper section--bg">
                        <form class="transparent-form verify-gcaptcha" method="POST" action="{{ route('user.login') }}">
                            @csrf
                            <div class="row">
                                <div class="col-lg-12 form-group">
                                    <label>@lang('Username or Email') <sup class="text--danger">*</sup></label>
                                    <div class="custom-icon-field">
                                        <i class="las la-user"></i>
                                        <input type="text" name="username" value="{{ old('username') }}"
                                            class="form--control" placeholder="@lang('Enter username')" required>
                                    </div>
                                    <div class="col-lg-12 form-group mt-3">
                                        <label>@lang('Password') <sup class="text--danger">*</sup></label>
                                        <div class="custom-icon-field">
                                            <i class="las la-key"></i>
                                            <input id="password" type="password" class="form--control" name="password"
                                                placeholder="@lang('Enter password')" required>
                                        </div>
                                    </div>

                                    {{-- <div class="col-lg-12 form-group">
                                        <x-captcha></x-captcha>
                                    </div> --}}

                                    <div class="col-lg-12 form-group d-flex justify-content-between flex-wrap">
                                        <div>
                                            <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                                {{ old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="remember">@lang('Remember Me')</label>
                                        </div>
                                        <a href="{{ route('user.password.request') }}"
                                            class="text--base">@lang('Forgot Password')?</a>
                                    </div>
                                    <div class="col-lg-12">
                                        <button type="submit" class="btn btn--base w-100">@lang('Login')</button>
                                        <!-- <p class="text-center mt-3"> @lang("Don't have an account")?
                                            <a href="" class="text--base">
                                                @lang('Create Account')
                                            </a>
                                        </p> -->
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Login section end -->
@endsection
