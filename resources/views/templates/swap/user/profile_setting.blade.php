@extends($activeTemplate.'layouts.master')

@section('content')
<section class="pt-100 pb-100 bg-light">
    <div class="container">
        <div class="row gy-4 justify-content-center">
            <div class="col-lg-10">
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title text-center">{{ __($pageTitle) }}</h5>
                    </div>
                    <div class="card-body">
                        <form class="transparent-form register prevent-double-click" action="#" method="post">
                            @csrf
                            <div class="row gy-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">@lang('First Name')</label>
                                        <input type="text" class="form-control form--control" name="firstname" value="{{$user->firstname}}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">@lang('Last Name')</label>
                                        <input type="text" class="form-control form--control" name="lastname" value="{{$user->lastname}}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">@lang('E-mail Address')</label>
                                        <input class="form-control form--control" value="{{$user->email}}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">@lang('Mobile Number')</label>
                                        <input class="form-control form--control" value="{{$user->mobile}}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">@lang('Address')</label>
                                        <input type="text" class="form-control form--control" name="address" value="{{@$user->address->address}}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">@lang('State')</label>
                                        <input type="text" class="form-control form--control" name="state" value="{{@$user->address->state}}">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="d-flex justify-content-between flex-wrap form-label">@lang('Telegram Username')
                                        @if(@$general->telegram_config->bot_username) 
                                            <a href="http://t.me/{{ @$general->telegram_config->bot_username }}" target="_blank" class="text--base">
                                                @lang('Get Telegram Notification')
                                            </a> 
                                        @endif 
                                    </label>
                                    <input class="form--control form-control" value="{{ @$user->telegram_username }}" name="telegram_username">
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">@lang('Zip Code')</label>
                                        <input type="text" class="form-control form--control" name="zip" value="{{@$user->address->zip}}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">@lang('City')</label>
                                        <input type="text" class="form-control form--control" name="city" value="{{@$user->address->city}}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">@lang('Country')</label>
                                        <input class="form-control form--control" value="{{@$user->address->country}}" disabled>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-4">
                                    <button class="btn btn--base w-100 mt-2">@lang('Submit')</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
