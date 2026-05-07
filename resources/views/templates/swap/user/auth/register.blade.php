@extends($activeTemplate . 'layouts.app')

@php
$authImage = getContent('auth_image.content', true);
$policyPages = getContent('policy_pages.element', orderById:true);
@endphp

@section('panel')
<!-- Account Section -->
<div class="account-section pt-60 pb-60">
    <img src="{{ getImage('assets/images/frontend/auth_image/' .@$authImage->data_values->image, '1920x840') }}" alt="bg" class="accout-bg">
    <div class="account-wrapper sign-up">
        <a href="{{ route('home') }}" class="logo mb-4"><img src="{{getImage(getFilePath('logoIcon') .'/dark_logo.png')}}" alt="logo"></a>
        <form class="transparent-form verify-gcaptcha" action="{{ route('user.register') }}" method="POST">
            @csrf

            @if(session()->has('reference'))
                <div class="col-sm-12">
                    <div class="form-group mb-3">
                        <label class="form-label">@lang('Reference By')</label>
                        <input type="text" name="referBy" id="referenceBy" class="form--control form-control" value="{{session()->get('reference')}}" readonly>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group mb-3">
                        <label class="form-label">@lang('Username')</label>
                        <input id="username" type="text" class="form--control form-control checkUser" name="username" required value="{{ old('username') }}">
                        <small class="text-danger usernameExist"></small>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group mb-3">
                        <label class="form-label">@lang('E-Mail Address')</label>
                        <input id="email" type="email" class="form--control form-control checkUser" name="email" value="{{ old('email') }}">
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group mb-3">
                        <label class="form-label">@lang('Country')</label>
                        <select name="country" id="country" class="form--control form-select">
                            @foreach($countries as $key => $country)
                                <option data-mobile_code="{{ $country->dial_code }}" value="{{ $country->country }}" data-code="{{ $key }}">{{ __($country->country) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group mb-3">
                        <label class="form-label">@lang('Mobile')</label>
                        <div class="input-group">
                            <span class="input-group-text mobile-code bg--base border-0 text-white"></span>
                            <input type="hidden" name="mobile_code">
                            <input type="hidden" name="country_code">
                            <input type="number" name="mobile" id="mobile" value="{{ old('mobile') }}" class="form--control form-control checkUser">
                        </div>
                        <small class="text-danger mobileExist"></small>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group mb-3">
                        <label class="form-label">@lang('Password')</label>
                        <div class="position-relative">
                            <input id="password" type="password" class="form--control form-control" name="password" required>
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
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="form-label">@lang('Confirm Password')</label>
                        <input id="password-confirm" type="password" class="form--control form-control" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <div class="col-lg-12 form-group">
                    <x-captcha></x-captcha>
                </div>

                @if($general->agree)
                    <div class="col-lg-12 form-group mt-2">
                        <div class="col-md-12">
                            <input type="checkbox" id="agree" name="agree">
                            <label for="agree" class="check-agree">
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

                <div class="col-sm-12">
                    <button type="submit" class="btn btn--base mt-4 w-100">@lang('Register')</button>
                </div>
            </div>
            <div class="mt-3 mt-sm-4">
                <div class="text-center">
                    @lang('Already you have an account') ? <a href="{{ route('user.login') }}" class="text--base">@lang('Login Here')</a>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Account Section -->

<div class="modal fade" id="existModalCenter" tabindex="-1" role="dialog" aria-labelledby="existModalCenterTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="existModalLongTitle">@lang('You are with us')</h5>
                <span type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="las la-times"></i>
                </span>
            </div>
            <div class="modal-body">
                <p class="text-center fw-bold">@lang('You already have an account please Login')</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark btn--sm"
                    data-bs-dismiss="modal">@lang('Close')</button>
                <a href="{{ route('user.login') }}" class="btn btn--success btn--sm">@lang('Login')</a>
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
        (function($) {
            @if ($mobileCode)
                $(`option[data-code={{ $mobileCode }}]`).attr('selected', '');
            @endif

            $('select[name=country]').change(function() {
                $('input[name=mobile_code]').val($('select[name=country] :selected').data('mobile_code'));
                $('input[name=country_code]').val($('select[name=country] :selected').data('code'));
                $('.mobile-code').text('+' + $('select[name=country] :selected').data('mobile_code'));
            });
            $('input[name=mobile_code]').val($('select[name=country] :selected').data('mobile_code'));
            $('input[name=country_code]').val($('select[name=country] :selected').data('code'));
            $('.mobile-code').text('+' + $('select[name=country] :selected').data('mobile_code'));

            $('.checkUser').on('focusout', function(e) {
                var url = '{{ route('user.checkUser') }}';
                var value = $(this).val();
                var token = '{{ csrf_token() }}';
                if ($(this).attr('name') == 'mobile') {
                    var mobile = `${$('.mobile-code').text().substr(1)}${value}`;
                    var data = {
                        mobile: mobile,
                        _token: token
                    }
                }
                if ($(this).attr('name') == 'email') {
                    var data = {
                        email: value,
                        _token: token
                    }
                }
                if ($(this).attr('name') == 'username') {
                    var data = {
                        username: value,
                        _token: token
                    }
                }
                $.post(url, data, function(response) {
                    if (response.data != false && response.type == 'email') {
                        $('#existModalCenter').modal('show');
                    } else if (response.data != false) {
                        $(`.${response.type}Exist`).text(`${response.type} already exist`);
                    } else {
                        $(`.${response.type}Exist`).text('');
                    }
                });
            });
        })(jQuery);
    </script>
@endpush
