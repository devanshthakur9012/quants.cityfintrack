@extends($activeTemplate .'layouts.frontend')

@php
    $authImage = getContent('auth_image.content', true);
@endphp

@section('content')
<section class="registration-section pt-100 pb-100">
    <div class="el-1"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->left_image, '450x590') }}" alt="@lang('image')"></div>
    <div class="el-2"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->right_image, '450x335') }}" alt="@lang('image')"></div>
        <div class="container content-container">
          <div class="row justify-content-center">
            <div class="d-flex justify-content-center">
                <div class="verification-code-wrapper">
                    <div class="verification-area">
                        <h5 class="pb-3 text-center border-bottom text-dark mb-3">@lang('Verify Mobile Number')</h5>
                        <form action="{{route('user.verify.mobile')}}" method="POST" class="submit-form">
                            @csrf
                            <p class="verification-text text-dark">@lang('A 6 digit verification code sent to your mobile number') :  +{{ showMobileNumber(auth()->user()->mobile) }}</p>
                            @include($activeTemplate.'partials.verification_code')
                            <div class="mb-3">
                                <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                            </div>
                            <div class="form-group verification-area__text text-dark">
                                <p>
                                    @lang('If you don\'t get any code'), <a href="{{route('user.send.verify.code', 'phone')}}" class="forget-pass"> @lang('Try again')</a>
                                </p>
                                @if($errors->has('resend'))
                                    <small class="text-danger">{{ $errors->first('resend') }}</small>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

