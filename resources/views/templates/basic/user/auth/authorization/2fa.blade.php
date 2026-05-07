@extends($activeTemplate .'layouts.frontend')
@php
    $authImage = getContent('auth_image.content', true);
@endphp
@section('content')
<section class="registration-section pt-100 pb-100">
    <div class="el-1"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->left_image, '450x590') }}" alt="@lang('image')"></div>
    <div class="el-2"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->right_image, '450x335') }}" alt="@lang('image')"></div>
        <div class="container content-container">
        <div class="d-flex justify-content-center">
            <div class="verification-code-wrapper">
                <div class="verification-area">
                    <h5 class="pb-3 text-center border-bottom text-dark mb-3">@lang('2FA Verification')</h5>
                    <form action="{{route('user.go2fa.verify')}}" method="POST" class="submit-form">
                        @csrf
                        @include($activeTemplate.'partials.verification_code')
                        <div class="form--group">
                            <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
