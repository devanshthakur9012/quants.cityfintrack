@extends($activeTemplate.'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="row justify-content-center mt-4">
            <div class="col-md-10">
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title text-center">{{ __($pageTitle) }}</h5>
                    </div>
                    <div class="card-body">
                        <form class="transparent-form register prevent-double-click" action="#" method="post">
                            @csrf
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label class="form-label">@lang('First Name')</label>
                                    <input type="text" class="form-control form--control" name="firstname" value="{{$user->firstname}}" required placeholder="@lang('First Name')">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label class="form-label">@lang('Last Name')</label>
                                    <input type="text" class="form-control form--control" name="lastname" value="{{$user->lastname}}" required placeholder="@lang('Last Name')">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="Inputdob" >@lang('Date of Birth')</label>
                                    <input type="date" class="form--control" id="Inputdob" name="dob" placeholder="@lang('Date of Birth')" value="{{$user->dob}}" minlength="3">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="InputPanNumber" >@lang('PAN Number')</label>
                                    <input type="text" class="form--control" id="InputPanNumber" name="pan_number" placeholder="@lang('PAN Number')" value="{{$user->pan_number}}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label class="form-label">@lang('E-mail Address')</label>
                                    <input class="form-control form--control" value="{{$user->email}}" readonly>
                                </div>
                                <div class="form-group col-sm-6">
                                    <label class="form-label">@lang('Mobile Number')</label>
                                    <input class="form-control form--control" value="{{$user->mobile}}" readonly>
                                </div>
                            </div>
                            {{-- <div class="row">
                                <div class="form-group col-sm-12">
                                    <label class="d-flex justify-content-between flex-wrap">@lang('Telegram Username')
                                        @if(@$general->telegram_config->bot_username)
                                            <a href="http://t.me/{{ @$general->telegram_config->bot_username }}" target="_blank" class="text--base">
                                                @lang('Get Telegram Notification')
                                            </a>
                                        @endif
                                    </label>
                                    <input class="form--control" value="{{ @$user->telegram_username }}" name="telegram_username" placeholder="@lang('Telegram username')">
                                </div>
                            </div> --}}
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="InputInvestmentAmount" >@lang('Inverstment Amount')</label>
                                    <input type="text" class="form--control" id="InputInvestmentAmount" name="investment_amount" placeholder="@lang('Investment Amount')" value="{{$user->investment_amount}}" minlength="3">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="InputSchemeName" >@lang('Scheme Name')</label>
                                    <input type="text" class="form--control" id="InputSchemeName" name="scheme_name" placeholder="@lang('Scheme Name')" value="{{$user->scheme_name}}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="InputBankName" >@lang('Bank Name')</label>
                                    <input type="text" class="form--control" id="InputBankName" name="bank_name" placeholder="@lang('Branch Name')" value="{{$user->bank_name}}" minlength="3">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="InputBankAccountNumber" >@lang('Bank Account No.')</label>
                                    <input type="text" class="form--control" id="InputBankAccountNumber" name="bank_account_no" placeholder="@lang('Bank Account No.')" value="{{$user->bank_account_no}}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label for="InputIFSC" >@lang('IFSC')</label>
                                    <input type="text" class="form--control" id="InputIFSC" name="ifsc_code" placeholder="@lang('IFSC')" value="{{$user->ifsc_code}}" minlength="3">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label for="InputBankAddress" >@lang('Bank Address')</label>
                                    <input type="text" class="form--control" id="InputBankAddress" name="bank_address" placeholder="@lang('Bank Address')" value="{{$user->bank_address}}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-6">
                                    <label class="form-label">@lang('Address')</label>
                                    <input type="text" class="form-control form--control" name="address" value="{{@$user->address->address}}" placeholder="@lang('Address')">
                                </div>
                                <div class="form-group col-sm-6">
                                    <label class="form-label">@lang('State')</label>
                                    <input type="text" class="form-control form--control" name="state" value="{{@$user->address->state}}" placeholder="@lang('State')">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-sm-4">
                                    <label class="form-label">@lang('Zip Code')</label>
                                    <input type="text" class="form-control form--control" name="zip" value="{{@$user->address->zip}}" placeholder="@lang('Zip')">
                                </div>
                                <div class="form-group col-sm-4">
                                    <label class="form-label">@lang('City')</label>
                                    <input type="text" class="form-control form--control" name="city" value="{{@$user->address->city}}" placeholder="@lang('City')">
                                </div>
                                <div class="form-group col-sm-4">
                                    <label class="form-label">@lang('Country')</label>
                                    <input class="form-control form--control" value="{{@$user->address->country}}" disabled>
                                </div>
                            </div>
                            <div class="form-group mb-0">
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

