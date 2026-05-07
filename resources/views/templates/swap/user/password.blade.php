@extends($activeTemplate.'layouts.master')

@section('content')
<section class="pt-100 pb-100 bg-light">
    <div class="container">
        <div class="row justify-content-center mt-4">
            <div class="col-md-6">
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title text-center">{{ __($pageTitle) }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="#" method="post" class="register transparent-form">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">@lang('Current Password')</label>
                                <div class="custom-icon-field">
                                    <input id="password" type="password" class="form--control form-control" name="current_password" required autocomplete="off">
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">@lang('Password')</label>
                                <div class="custom-icon-field">
                                    <input id="password" type="password" class="form--control form-control" name="password" required autocomplete="off">
                                </div>
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
                            <div class="form-group mb-3">
                                <label for="confirm_password" class="form-label">@lang('Confirm Password')</label>
                                <div class="custom-icon-field">
                                    <input id="password_confirmation" type="password" class="form--control form-control" name="password_confirmation" required autocomplete="off">
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="submit" class="mt-2 btn btn--base w-100 text-center" value="@lang('Submit')">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@if($general->secure_password)
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif

