@extends($activeTemplate.'layouts.frontend')

@php
    $authImage = getContent('auth_image.content', true);
    $policyPages = getContent('policy_pages.element', orderById:true);
@endphp

@section('content')

<!-- registration section start -->
<section class="registration-section pt-100 pb-100">
    <div class="el-1"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->left_image, '450x590') }}" alt="@lang('image')"></div>
    <div class="el-2"><img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->right_image, '450x335') }}" alt="@lang('image')"></div>
    <div class="container content-container">
        <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="registration-wrapper section--bg">
            <form class="transparent-form verify-gcaptcha" action="{{ route('user.register') }}" method="POST">
                @csrf
                <div class="row">

                @if(session()->has('reference'))
                    <div class="col-lg-12 form-group">
                        <label for="referenceBy">@lang('Reference By') <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                        <i class="las la-user"></i>
                        <input type="text" name="referBy" id="referenceBy" class="form--control" value="{{session()->get('reference')}}" readonly>
                        </div>
                    </div>
                @endif
                <div class="col-lg-6 form-group">
                    <label for="firstname">@lang('First Name') <sup class="text--danger">*</sup></label>
                    <div class="custom-icon-field">
                        <i class="las la-user"></i>
                        <input id="firstname" type="text" class="form--control" name="firstname" required placeholder="@lang('First Name')" value="{{ old('firstname') }}">
                        <small class="text-danger"></small>
                    </div>
                </div>
                <div class="col-lg-6 form-group">
                    <label for="lastname">@lang('Username') <sup class="text--danger">*</sup></label>
                    <div class="custom-icon-field">
                        <i class="las la-user"></i>
                        <input id="lastname" type="text" class="form--control" name="lastname" required placeholder="@lang('Last Name')" value="{{ old('lastname') }}">
                        <small class="text-danger"></small>
                    </div>
                </div>
                <div class="col-lg-6 form-group">
                    <label for="email">@lang('E-Mail Address') <sup class="text--danger">*</sup></label>
                    <div class="custom-icon-field">
                    <i class="las la-envelope"></i>
                    <input id="email" type="email" class="form--control checkUser" name="email" value="{{ old('email') }}" required placeholder="@lang('Email')">
                    </div>
                </div>
                <div class="col-lg-6 form-group">
                    <label>@lang('Country') <sup class="text--danger">*</sup></label>
                    <div class="custom-icon-field">
                    <i class="las la-flag"></i>
                    <select name="country" id="country" class="form--control">
                        @foreach($countries as $key => $country)
                            <option data-mobile_code="{{ $country->dial_code }}" value="{{ $country->country }}" data-code="{{ $key }}">{{ __($country->country) }}</option>
                        @endforeach
                    </select>
                    </div>
                </div>
                <div class="col-lg-6 form-group">
                    <label>@lang('Mobile') <sup class="text--danger">*</sup></label>
                    <div class="custom-icon-field">
                    <i class="las la-envelope"></i>
                    <div class="input-group">
                        <span class="input-group-text mobile-code bg--base border-0 text-white">
                        </span>
                        <input type="hidden" name="mobile_code">
                        <input type="hidden" name="country_code">
                        <input type="number" name="mobile" id="mobile" value="{{ old('mobile') }}" class="form--control checkUser form-phone" placeholder="@lang('Mobile')">
                    </div>
                    <small class="text-danger mobileExist"></small>
                    </div>
                </div>
                <div class="col-lg-6 form-group">
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
                <div class="col-lg-6 form-group">
                    <label for="password-confirm">@lang('Confirm Password') <sup class="text--danger">*</sup></label>
                    <div class="custom-icon-field">
                    <i class="las la-key"></i>
                    <input id="password-confirm" type="password" class="form--control" name="password_confirmation" required autocomplete="new-password" placeholder="@lang('Confirm password')">
                    </div>
                </div>

                {{-- <div class="col-lg-12 form-group">
                    <x-captcha></x-captcha>
                </div> --}}

                @if($general->agree)
                <div class="col-lg-12 form-group">
                    <div class="col-md-12">
                        <input type="checkbox" id="agree" name="agree">
                        <label for="agree">
                            @lang('I agree with ')
                            @foreach($policyPages as $policyPage)
                                <a href="{{route('policy.pages',['slug'=>slug($policyPage->data_values->title), 'id'=>$policyPage->id])}}" target="_blank" class="text--base">
                                    {{__($policyPage->data_values->title)}}
                                </a>
                                {{ $loop->last ? '' : ', ' }}
                            @endforeach
                        </label>
                    </div>
                </div>
                @endif

                <div class="col-lg-12">
                    <button type="submit" class="btn btn--base w-100">@lang('Register')</button>
                    <p class="text-center mt-3"> @lang('Already you have an account') ? <a href="{{ route('user.login') }}" class="text--base">@lang('Login Here')</a></p>
                </div>
                </div>
            </form>
            </div>
        </div>
        </div>
    </div>
</section>
<!-- registration section end -->

<div class="modal fade" id="existModalCenter" tabindex="-1" role="dialog" aria-labelledby="existModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="existModalLongTitle">@lang('You are with us')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-center">@lang('You already have an account please Login')</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
                <a href="{{ route('user.login') }}" class="btn btn--success btn-sm">@lang('Login')</a>
            </div>
        </div>
    </div>
</div>
@endsection

@if($general->secure_password)
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif

@push('script')
    <script>
      "use strict";
        (function ($) {
            @if($mobileCode)
            $(`option[data-code={{ $mobileCode }}]`).attr('selected','');
            @endif

            $('select[name=country]').change(function(){
                $('input[name=mobile_code]').val($('select[name=country] :selected').data('mobile_code'));
                $('input[name=country_code]').val($('select[name=country] :selected').data('code'));
                $('.mobile-code').text('+'+$('select[name=country] :selected').data('mobile_code'));
            });
            $('input[name=mobile_code]').val($('select[name=country] :selected').data('mobile_code'));
            $('input[name=country_code]').val($('select[name=country] :selected').data('code'));
            $('.mobile-code').text('+'+$('select[name=country] :selected').data('mobile_code'));

            $('.checkUser').on('focusout',function(e){
                var url = '{{ route('user.checkUser') }}';
                var value = $(this).val();
                var token = '{{ csrf_token() }}';
                if ($(this).attr('name') == 'mobile') {
                    var mobile = `${$('.mobile-code').text().substr(1)}${value}`;
                    var data = {mobile:mobile,_token:token}
                }
                if ($(this).attr('name') == 'email') {
                    var data = {email:value,_token:token}
                }
                if ($(this).attr('name') == 'username') {
                    var data = {username:value,_token:token}
                }
                $.post(url,data,function(response) {
                  if (response.data != false && response.type == 'email') {
                    $('#existModalCenter').modal('show');
                  }else if(response.data != false){
                    $(`.${response.type}Exist`).text(`${response.type} already exist`);
                  }else{
                    $(`.${response.type}Exist`).text('');
                  }
                });
            });
        })(jQuery);

    </script>
@endpush
