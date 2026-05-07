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
            <form class="transparent-form " method="POST" action="{{ route('user.password.email') }}">
                @csrf
                <div class="row">
                    <div class="col-lg-12 form-group">
                        <div class="mb-4">
                            <p>@lang('Please provide your email username to find your account').</p>
                        </div>
                        <label>@lang('Email or Username')</label>
                        <input type="text" class="form--control @error('value') is-invalid @enderror" name="value" value="{{ old('value') }}" required autofocus="off" placeholder="@lang('Enter email or username')">
                        <div class="custom-icon-field">
                        @error('value')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    {{-- <div class="form-group mt-4">
                        <x-captcha />
                    </div> --}}
                    <div class="col-lg-12 form-group mt-4">
                        <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                    </div>
                </div>
            </form>
            </div>
        </div>
        </div>
    </div>
</section>
@endsection
