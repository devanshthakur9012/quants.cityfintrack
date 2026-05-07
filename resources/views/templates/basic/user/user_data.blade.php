@extends($activeTemplate.'layouts.frontend')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="row justify-content-center mt-4">
            <div class="col-md-8">
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title text-center">{{ __($pageTitle) }}</h5>
                    </div>
                    <div class="card-body">
                        <form class="transparent-form register prevent-double-click" method="post" action="{{ route('user.data.submit') }}">
                            @csrf
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="InputFirstname" >@lang('First Name')</label>
                                    <input type="text" class="form--control" id="InputFirstname" name="firstname" placeholder="@lang('First Name')" value="{{$user->firstname}}" minlength="3">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="lastname" >@lang('Last Name')</label>
                                    <input type="text" class="form--control" id="lastname" name="lastname" placeholder="@lang('Last Name')" value="{{$user->lastname}}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="InputFirstname" >@lang('Date of Birth')</label>
                                    <input type="date" class="form--control" id="Inputdob" name="dateofbirth" placeholder="@lang('Date of Birth')" value="{{$user->dob}}" minlength="3">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="lastname" >@lang('Last Name')</label>
                                    <input type="text" class="form--control" id="lastname" name="lastname" placeholder="@lang('Last Name')" value="{{$user->lastname}}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="address" >@lang('Address')</label>
                                    <input type="text" class="form--control" id="address" name="address" placeholder="@lang('Address')" value="{{@$user->address->address}}" required="">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="state" >@lang('State')</label>
                                    <input type="text" class="form--control" id="state" name="state" placeholder="@lang('State')" value="{{@$user->address->state}}" required="">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="zip" >@lang('Zip Code')</label>
                                    <input type="text" class="form--control" id="zip" name="zip" placeholder="@lang('Zip Code')" value="{{@$user->address->zip}}" required="">
                                </div>

                                <div class="form-group col-sm-6">
                                    <label for="city" >@lang('City')</label>
                                    <input type="text" class="form--control" id="city" name="city" placeholder="@lang('City')" value="{{@$user->address->city}}" required="">
                                </div>
                            </div>
                            {{-- <div class="form-group col-sm-12">
                                <label class="d-flex justify-content-between flex-wrap">@lang('Telegram Username')
                                    @if($general->bot_name)
                                        <a href="http//t.me/{{ $general->bot_name }}" target="_blank" class="text--base">
                                            @lang('Get Telegram Notification')
                                        </a>
                                    @endif
                                </label>
                                <input class="form--control" value="{{ @$user->telegram_username }}" name="telegram_username" placeholder="@lang('Telegram Username')">
                            </div> --}}
                            <div class="form-group mb-0 mt-4">
                                <div class="col-sm-12 text-center mt-3">
                                    <button type="submit" class="btn btn-block btn--base w-100 text-center">@lang('Submit')</button>
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
