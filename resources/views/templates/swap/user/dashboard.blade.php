@extends($activeTemplate.'layouts.master')
@section('content')
<!-- Dashboard Section -->
<div class="dashboard-section pt-100 pb-100 bg-light">
    <div class="container">

        <div class="row notice"></div>

        <div class="row gy-4 pb-60">
            <div class="col-md-12 mb-3">
                <form action="#" class="transparent-form">
                    <label class="form-label">@lang('Referral Link')</label>
                    <div class="input-group">
                        <input type="text" name="text" class="form-control form--control referralURL"
                            value="{{ route('home', ['reference'=>$user->username]) }}" readonly
                        >
                        <button class="input-group-text bg--base text-white border-0" id="copyBoard" type="button">
                            <span class="copytext"><i class="fa fa-copy"></i></span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="dashboard-widget has--link">
                    <a href="{{ route('user.transactions') }}" class="item--link"></a>
                    <div class="dashboard-widget__icon">
                        <i class="las la-money-bill-wave text--base"></i>
                    </div>
                    <div class="dashboard-widget__content">
                        <p class="text-uppercase mb-1 fw-medium">@lang('Total Balance')</p>
                        <h4 class="title">{{ $general->cur_sym }} {{ showAmount($user->balance, 2) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-widget has--link">
                    <a href="javascript:void(0)" class="item--link {{ $user->package_id ? 'renewBtn' : null }}"
                        @if($user->package_id)
                            data-package="{{ @$user->package }}"
                        @endif
                    >
                    </a>
                    <div class="dashboard-widget__icon">
                        <i class="las la-calendar text--base"></i>
                    </div>
                    <div class="dashboard-widget__content">
                        <p class="text-uppercase mb-1 fw-medium">
                            @if($user->package_id != 0)
                                {{ __(@$user->package->name) }}
                            @else
                                @lang('Product')
                            @endif
                        </p>
                        <h4 class="title">
                            @if($user->package_id != 0)
                                {{ showDateTime($user->validity, 'd M Y') }}
                            @else
                                @lang('N/A')
                            @endif
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-widget has--link">
                    <a href="{{ route('user.deposit.history') }}" class="item--link"></a>
                    <div class="dashboard-widget__icon">
                        <i class="las la-wallet text--base"></i>
                    </div>
                    <div class="dashboard-widget__content">
                        <p class="text-uppercase mb-1 fw-medium">@lang('Total Deposit')</p>
                        <h4 class="title ">{{ $general->cur_sym }} {{ showAmount($totalDeposit, 2) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-widget has--link">
                    <a href="{{ route('user.signals') }}" class="item--link"></a>
                    <div class="dashboard-widget__icon">
                        <i class="las la-signal text--base"></i>
                    </div>
                    <div class="dashboard-widget__content">
                        <p class=" text-uppercase mb-1 fw-medium">@lang('Total Signal')</p>
                        <h4 class="title">{{ $totalSignal }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-widget has--link">
                    <a href="{{ route('user.transactions') }}" class="item--link"></a>
                    <div class="dashboard-widget__icon">
                        <i class="las la-exchange-alt text--base"></i>
                    </div>
                    <div class="dashboard-widget__content">
                        <p class=" text-uppercase mb-1 fw-medium">@lang('Total Transaction')</p>
                        <h4 class="title">{{ $totalTrx }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="dashboard-widget has--link">
                    <a href="{{ route('user.referrals') }}" class="item--link"></a>
                    <div class="dashboard-widget__icon">
                        <i class="las la-users text--base"></i>
                    </div>
                    <div class="dashboard-widget__content">
                        <p class=" text-uppercase mb-1 fw-medium">@lang('Total Referral')</p>
                        <h4 class="title">{{ $user->referrals->count() }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <h5 class="mb-4 text-center">@lang('Latest Transaction')</h5>
                <table class="table table--responsive--lg">
                    <thead class="bg--base">
                        <tr>
                            <th>@lang('Trx')</th>
                            <th>@lang('Transacted')</th>
                            <th>@lang('Amount')</th>
                            <th>@lang('Post Balance')</th>
                            <th>@lang('Detail')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestTrx as $trx)
                            <tr>
                                <td>
                                    <strong>{{ $trx->trx }}</strong>
                                </td>
                                <td>
                                    {{ showDateTime($trx->created_at) }}<br>{{ diffForHumans($trx->created_at) }}
                                </td>
                                <td class="budget">
                                    <span class="fw-bold @if($trx->trx_type == '+')text--success @else text--danger @endif">
                                        {{ $trx->trx_type }} {{showAmount($trx->amount)}} {{ $general->cur_text }}
                                    </span>
                                </td>
                                <td class="budget">
                                {{ showAmount($trx->post_balance) }} {{ __($general->cur_text) }}
                                </td>
                                <td>{{ __($trx->details) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100%" class="text-center justify-content-center">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
<!-- Dashboard Section -->

@if($user->package_id)
    <div class="modal fade cmn--modal" id="renewModal">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title method-name"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{route('user.renew.package')}}" method="post">
                    @csrf
                    <div class="modal-body pt-0">
                        <div class="form-group">
                            <input type="hidden" name="id" required>
                        </div>
                        <ul class="list-group list-group-flush mt-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center">@lang('Product') <span class="packageName"></span></li>
                            <li class="list-group-item d-flex justify-content-between align-item
                            s-center">@lang('Price') <span class="packagePrice"></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">@lang('Validity') <span class="packageValidity"></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">@lang('Your Balance')
                                <span>{{ showAmount($user->balance, 2) }} {{ __($general->cur_text) }} </span>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark btn--sm" data-bs-dismiss="modal">@lang('Close')</button>
                        <div class="prevent-double-click">
                            <button type="submit" class="btn btn--sm btn--success">@lang('Confirm')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@endsection

@push('script')
<script>
    (function ($) {
        "use strict";

        @if($user->package_id != 0)
            $('.renewBtn').on('click', function () {
                var modal = $('#renewModal');

                modal.find('.modal-title').text('Are you sure to renew '+$(this).data('package').name);
                modal.find('.packageName').text($(this).data('package').name);
                modal.find('.packagePrice').text($(this).data('package').price+' '+@json( __($general->cur_text) ));
                modal.find('.packageValidity').text($(this).data('package').validity+' Days');
                modal.find('input[name=id]').val($(this).data('package').id);

                modal.modal('show');
            });
        @endif

        $('#copyBoard').click(function(){
            var copyText = document.getElementsByClassName("referralURL");
            copyText = copyText[0];
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            /*For mobile devices*/
            document.execCommand("copy");
            notify('success', "Copied: " + copyText.value)
        });

    })(jQuery);
</script>
@endpush

