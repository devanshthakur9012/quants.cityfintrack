@extends($activeTemplate.'layouts.frontend')

@section('content')
<section class="pt-100 pb-100 bg-light">
    <div class="container">
        <div class="row gy-4 justify-content-center">
            <div class="col-lg-8">
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title text-center">{{ __($pageTitle) }}</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('user.data.submit') }}">
                            @csrf 
                            <div class="row gy-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="InputFirstname" class="form-label">@lang('First Name')</label>
                                        <input type="text" class="form-control form--control" id="InputFirstname" name="firstname" placeholder="@lang('First Name')" value="{{$user->firstname}}" minlength="3">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lastname" class="form-label">@lang('Last Name')</label>
                                        <input type="text" class="form-control form--control" id="lastname" name="lastname" placeholder="@lang('Last Name')" value="{{$user->lastname}}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="address" class="form-label">@lang('Address')</label>
                                        <input type="text" class="form-control form--control" id="address" name="address" placeholder="@lang('Address')" value="{{@$user->address->address}}" required="">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="state" class="form-label">@lang('State')</label>
                                        <input type="text" class="form-control form--control" id="state" name="state" placeholder="@lang('State')" value="{{@$user->address->state}}" required="">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group" class="form-label">
                                        <label for="zip" class="form-label">@lang('Zip Code')</label>
                                        <input type="text" class="form-control form--control" id="zip" name="zip" placeholder="@lang('Zip Code')" value="{{@$user->address->zip}}" required="">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group" class="form-label">
                                        <label for="city" class="form-label">@lang('City')</label>
                                        <input type="text" class="form-control form--control" id="city" name="city" placeholder="@lang('City')" value="{{@$user->address->city}}" required="">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="d-flex justify-content-between flex-wrap form-label">@lang('Telegram Username') 
                                            @if($general->bot_name) 
                                                <a href="http//t.me/{{ $general->bot_name }}" target="_blank" class="text--base">
                                                    @lang('Get Telegram Notification')
                                                </a> 
                                            @endif 
                                        </label>
                                        <input class="form-control form--control" value="{{ @$user->telegram_username }}" name="telegram_username" placeholder="@lang('Telegram Username')">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button class="btn btn--base w-100 mt-2" type="submit">@lang('Submit')</button>
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
