@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-12">
            <div class="row gy-4">
                {{-- <div class="col-xxl-3 col-sm-6">
                    <div class="widget-two style--two box--shadow2 b-radius--5 bg--19">
                        <div class="widget-two__icon b-radius--5 bg--primary">
                            <i class="las la-money-bill-wave-alt"></i>
                        </div>
                        <div class="widget-two__content">
                            <h3 class="text-white">{{ $general->cur_sym }}{{ showAmount($user->balance) }}</h3>
                            <p class="text-white">@lang('Balance')</p>
                        </div>
                        <a href="{{ route('admin.report.transaction') }}?search={{ $user->username }}" class="widget-two__btn">@lang('View All')</a>
                    </div>
                </div> --}}
                <!-- dashboard-w1 end -->
                {{-- <div class="col-xxl-3 col-sm-6">
                    <div class="widget-two style--two box--shadow2 b-radius--5 bg--primary">
                        <div class="widget-two__icon b-radius--5 bg--primary">
                            <i class="las la-wallet"></i>
                        </div>
                        <div class="widget-two__content">
                            <h3 class="text-white">{{ $general->cur_sym }}{{ showAmount($totalDeposit) }}</h3>
                            <p class="text-white">@lang('Deposits')</p>
                        </div>
                        <a href="{{ route('admin.deposit.list') }}?search={{ $user->username }}" class="widget-two__btn">@lang('View All')</a>
                    </div>
                </div> --}}
                <!-- dashboard-w1 end -->
                {{-- <div class="col-xxl-3 col-sm-6">
                    <div class="widget-two style--two box--shadow2 b-radius--5 bg--13">
                        <div class="widget-two__icon b-radius--5 bg--primary">
                            <i class="las la-users"></i>
                        </div>
                        <div class="widget-two__content">
                            <h3 class="text-white">{{ $user->referrals->count() }}</h3>
                            <p class="text-white">@lang('Referrals')</p>
                        </div>
                        <a href="{{ route('admin.users.referrals', $user->id) }}" class="widget-two__btn">@lang('View All')</a>
                    </div>
                </div> --}}
                <!-- dashboard-w1 end -->
                {{-- <div class="col-xxl-3 col-sm-6">
                    <div class="widget-two style--two box--shadow2 b-radius--5 bg--11">
                        <div class="widget-two__icon b-radius--5 bg--primary">
                            <i class="fas fa-signal"></i>
                        </div>
                        <div class="widget-two__content">
                            <h3 class="text-white">{{ $totalSignal }}</h3>
                            <p class="text-white">@lang('Signals')</p>
                        </div>
                        <a href="{{ route('admin.users.signal.log', $user->id) }}" class="widget-two__btn">@lang('View All')</a>
                    </div>
                </div> --}}
                <!-- dashboard-w1 end -->
            </div>

            <div class="d-flex flex-wrap gap-3 mt-4">
                {{-- <div class="flex-fill">
                    <button data-bs-toggle="modal" data-bs-target="#addSubModal" class="btn btn--success btn--shadow w-100 btn-lg bal-btn" data-act="add">
                        <i class="las la-plus-circle"></i> @lang('Balance')
                    </button>
                </div>

                <div class="flex-fill">
                    <button data-bs-toggle="modal" data-bs-target="#addSubModal" class="btn btn--danger btn--shadow w-100 btn-lg bal-btn" data-act="sub">
                        <i class="las la-minus-circle"></i> @lang('Balance')
                    </button>
                </div> --}}

                <div class="flex-fill">
                    <a href="{{route('admin.report.login.history')}}?search={{ $user->username }}" class="btn btn--primary btn--shadow w-100 btn-lg">
                        <i class="las la-list-alt"></i>@lang('Logins')
                    </a>
                </div>

                <div class="flex-fill">
                    <a href="{{ route('admin.users.notification.log',$user->id) }}" class="btn btn--secondary btn--shadow w-100 btn-lg">
                        <i class="las la-bell"></i>@lang('Notifications')
                    </a>
                </div>

                <div class="flex-fill">
                    <a href="{{route('admin.users.login',$user->id)}}" target="_blank" class="btn btn--primary btn--gradi btn--shadow w-100 btn-lg">
                        <i class="las la-sign-in-alt"></i>@lang('Login as User')
                    </a>
                </div>

                <div class="flex-fill">
                    @if($user->status == 1)
                    <button type="button" class="btn btn--warning btn--gradi btn--shadow w-100 btn-lg userStatus" data-bs-toggle="modal" data-bs-target="#userStatusModal">
                        <i class="las la-ban"></i>@lang('Ban User')
                    </button>
                    @else
                    <button type="button" class="btn btn--success btn--gradi btn--shadow w-100 btn-lg userStatus" data-bs-toggle="modal" data-bs-target="#userStatusModal">
                        <i class="las la-undo"></i>@lang('Unban User')
                    </button>
                    @endif
                </div>
            </div>


            <div class="card mt-30">
                <div class="card-header">
                    <h5 class="card-title mb-0 justify-content-between d-flex flex-wrap">
                        <div>
                            @lang('Information of') {{$user->fullname}}
                            {{-- (@lang('Product') - <span class="text--primary">{{ $user->package_id ? __(@$user->package->name) : __('N/A') }}</span>) --}}
                        </div>
                        {{-- <div>
                            @lang('Validity') - <span class="text--primary">{{ $user->package_id ? showDateTime($user->validity) : __('N/A') }}</span>
                        </div> --}}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{route('admin.users.update',[$user->id])}}" method="POST"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group ">
                                    <label>@lang('First Name')</label>
                                    <input class="form-control" type="text" name="firstname" required value="{{$user->firstname}}">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-control-label">@lang('Last Name')</label>
                                    <input class="form-control" type="text" name="lastname" required value="{{$user->lastname}}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Email') </label>
                                    <input class="form-control" type="email" name="email" value="{{$user->email}}" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Mobile Number') </label>
                                    <div class="input-group ">
                                        <span class="input-group-text mobile-code"></span>
                                        <input type="number" name="mobile" value="{{ old('mobile') }}" id="mobile" class="form-control checkUser" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Telegram Username') </label>
                                    <input type="text" name="telegram_username" value="{{$user->telegram_username}}" class="form-control">
                                </div>
                            </div>
                        </div>


                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="form-group ">
                                    <label>@lang('Address')</label>
                                    <input class="form-control" type="text" name="address" value="{{@$user->address->address}}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group">
                                    <label>@lang('City')</label>
                                    <input class="form-control" type="text" name="city" value="{{@$user->address->city}}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('State')</label>
                                    <input class="form-control" type="text" name="state" value="{{@$user->address->state}}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('Zip/Postal')</label>
                                    <input class="form-control" type="text" name="zip" value="{{@$user->address->zip}}">
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="form-group ">
                                    <label>@lang('Country')</label>
                                    <select name="country" class="form-control">
                                        @foreach($countries as $key => $country)
                                            <option data-mobile_code="{{ $country->dial_code }}" value="{{ $key }}">{{ __($country->country) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="form-group  col-xl-4 col-md-4 col-12">
                                <label>@lang('Email Verification')</label>
                                <input type="checkbox" data-width="100%" data-onstyle="-success" data-offstyle="-danger"
                                       data-bs-toggle="toggle" data-on="@lang('Verified')" data-off="@lang('Unverified')" name="ev"
                                       @if($user->ev) checked @endif>

                            </div>

                            <div class="form-group  col-xl-4 col-md-4 col-12">
                                <label>@lang('Mobile Verification')</label>
                                <input type="checkbox" data-width="100%" data-onstyle="-success" data-offstyle="-danger"
                                       data-bs-toggle="toggle" data-on="@lang('Verified')" data-off="@lang('Unverified')" name="sv"
                                       @if($user->sv) checked @endif>

                            </div>
                            <div class="form-group col-xl-4 col-md-4 col-12">
                                <label>@lang('2FA Verification') </label>
                                <input type="checkbox" data-width="100%" data-height="50" data-onstyle="-success" data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Enable')" data-off="@lang('Disable')" name="ts" @if($user->ts) checked @endif>
                            </div>
                        </div>


                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')
                                    </button>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>



    {{-- Add Sub Balance MODAL --}}
    <div id="addSubModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span class="type"></span> <span>@lang('Balance')</span></h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{route('admin.users.add.sub.balance',$user->id)}}" method="POST">
                    @csrf
                    <input type="hidden" name="act">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Amount')</label>
                            <div class="input-group">
                                <input type="number" step="any" name="amount" class="form-control" placeholder="@lang('Please provide positive amount')" required>
                                <div class="input-group-text">{{ __($general->cur_text) }}</div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>@lang('Remark')</label>
                            <textarea class="form-control" placeholder="@lang('Remark')" name="remark" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="userStatusModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @if($user->status == Status::USER_ACTIVE)
                        <span>@lang('Ban User')</span>
                        @else
                        <span>@lang('Unban User')</span>
                        @endif
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{route('admin.users.status',$user->id)}}" method="POST">
                    @csrf
                    <div class="modal-body">
                        @if($user->status == Status::USER_ACTIVE)
                        <h6 class="mb-2">@lang('If you ban this user he/she won\'t able to access his/her dashboard.')</h6>
                        <div class="form-group">
                            <label>@lang('Reason')</label>
                            <textarea class="form-control" name="reason" rows="4" required></textarea>
                        </div>
                        @else
                        <p><span>@lang('Ban reason was'):</span></p>
                        <p>{{ $user->ban_reason }}</p>
                        <h4 class="text-center mt-3">@lang('Are you sure to unban this user?')</h4>
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if($user->status == Status::USER_ACTIVE)
                        <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>
                        @else
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('No')</button>
                        <button type="submit" class="btn btn--primary">@lang('Yes')</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

@if($user->package_id)
    <div id="validityModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="type"></span>
                        <span>@lang('Product Validity') 
                            <small class="text--primary d-block d-lg-inline d-md-inline d-sm-inline">({{ showDateTime($user->validity, 'Y-d-m') }})</small>
                        </span>
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{route('admin.users.update.validity')}}" method="POST">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $user->id }}" required>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Validity')</label>
                            <div class="input-group">
                                <input type="number" name="validity_day" class="form-control" placeholder="@lang('Enter day')" required min="1">
                                <span class="input-group-text">@lang('Days')</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="checkbox" data-width="100%" 
                                data-onstyle="-success" 
                                data-offstyle="-danger"data-bs-toggle="toggle" 
                                data-on="@lang('Add')" 
                                data-off="@lang('Subtract')" 
                                name="day_type"
                                checked
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn--primary h-45 w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@endsection

@if($user->package_id)
    @push('breadcrumb-plugins')
        <button class="btn btn-sm btn-outline--primary validity-btn"><i class="las la-calendar"></i>@lang('Validity')</button>
    @endpush
@endif

@push('script')
<script>
    (function($){
    "use strict"
        $('.validity-btn').click(function(){
            var modal = $('#validityModal');
            modal.modal('show');
        });
        $('.bal-btn').click(function(){
            var act = $(this).data('act');
            $('#addSubModal').find('input[name=act]').val(act);
            if (act == 'add') {
                $('.type').text('Add');
            }else{
                $('.type').text('Subtract');
            }
        });
        let mobileElement = $('.mobile-code');
        $('select[name=country]').change(function(){
            mobileElement.text(`+${$('select[name=country] :selected').data('mobile_code')}`);
        });

        $('select[name=country]').val('{{@$user->country_code}}');
        let dialCode        = $('select[name=country] :selected').data('mobile_code');
        let mobileNumber    = `{{ $user->mobile }}`;
        mobileNumber        = mobileNumber.replace(dialCode,'');
        $('input[name=mobile]').val(mobileNumber);
        mobileElement.text(`+${dialCode}`);

    })(jQuery);
</script>
@endpush
