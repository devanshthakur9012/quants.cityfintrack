@extends($activeTemplate . 'layouts.frontend')

@section('content')
<section class="pt-100 pb-100">
    <div class="container">
        <div class="row gy-4 justify-content-center">
            <div class="col-lg-10">
                <div class="row gy-4 justify-content-center">
                    @if(session()->has('notify'))
                        @foreach(session('notify') as $msg)
                        @if ($msg[0] == "success")
                            <div class="col-lg-7 col-md-7 col-12">
                                <div class="custom--card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">@lang('Success 😀')</h5>
                                        <h2 class="text--base">We Will Call You Back</h2>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @endforeach
                    @endif
                    @isset($packageDetails)
                        <div class="col-xl-6 col-md-6 wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title method-name">@lang('Are you sure to buy') {{$packageDetails->name}} <span class="modal-title-text"></span></h5>
                                    </div>
                                    <form action="{{ route('user.purchase.package') }}" method="post">
                                        @csrf
                                        <div class="modal-body pt-0">
                                            <div class="form-group">
                                                <input type="hidden" name="id">
                                            </div>
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Product') <span class="packageName">{{$packageDetails->name}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Description') <span class="packageName">{{$packageDetails->description}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Asset Type') <span class="packageName">{{$packageDetails->asset_type}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Minimum Investment') <span class="packageName">{{$packageDetails->min_investment}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Time Horizon') <span class="packageName">{{$packageDetails->time_horizon}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Risk Appetite') <span class="packageName">{{$packageDetails->risk_appetite}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Expected Returns') <span class="packageName">{{$packageDetails->expected_returns}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Frequency') <span class="packageName">{{$packageDetails->frequency}}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Hedging Strategy') <span class="packageName">{{$packageDetails->hedging_strategy}}</span>
                                                </li>
                                                {{-- <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    @lang('Your Balance')
                                                    <span>{{ showAmount(auth()->user()->balance, 2) }} {{ __($general->cur_text) }} </span>
                                                </li> --}}
                                            </ul>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="{{url('/packages')}}" class="btn btn--danger btn-sm" >@lang('Cancel')</a>
                                            <div class="prevent-double-click">
                                                <button type="button" class="btn btn-sm btn--success chooseBtn">@lang('Request for Call Back')</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endisset
                </div>
            </div>
        </div>
    </div>
</section>
@auth
    <div class="modal fade cmn--modal" id="chooseModal">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title method-name">@lang('Fill this form to complete the process') <span class="modal-title-text"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form autocomplete="off" method="post">
                    @csrf
                    <div class="modal-body pt-0">
                        <div class="col-12 form-group">
                            <label for="name" class="required">Name <sup class="text--danger">*</sup></label>
                            <div class="custom-icon-field">
                                <input name="name" type="text" class="form--control" placeholder="Enter Name" value="" required="" id="name">
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label for="email" class="required">Email <sup class="text--danger">*</sup></label>
                            <div class="custom-icon-field">
                                <input name="email" type="email" class="form--control" placeholder="Enter Email" value="" required="" id="subject">
                            </div>
                        </div>
                        <div class="col-12 form-group">
                            <label for="number" class="required">Mobile Number <sup class="text--danger">*</sup></label>
                            <div class="custom-icon-field">
                                <input name="number" type="number" class="form--control" placeholder="Enter Mobile Number" value="" required="" id="subject">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
                        <div class="prevent-double-click">
                            <button type="submit" class="btn btn-sm btn--success">@lang('Confirm')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@else
    <div class="modal fade cmn--modal" id="chooseModal">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header"> 
                    <h5 class="modal-title method-name">@lang('Kindly login before proceeding with the request callback')</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">@lang('To enquire about product, please ensure you are logged into your account')</p>
                    <div class="form-group">
                        <a href="{{ route('user.login') }}" class="btn btn-sm btn--success w-100">@lang('Login')</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endauth
@endsection
@push('script')
    <script>
        (function($) {
            "use strict";
            $('.chooseBtn').on('click', function() {
                var modal = $('#chooseModal');
                modal.modal('show');
            });
        })(jQuery);
    </script>
@endpush


