@extends($activeTemplate.'layouts.master')
@section('content')

<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="row mb-3 justify-content-end">
            <div class="col-lg-5">
                <form action="#" class="transparent-form">
                    <div class="input-group">
                        <input type="text" name="search" class="form--control" value="{{ request()->search }}" placeholder="@lang('Search by transactions')">
                        <button class="input-group-text btn--base border-0" type="submit">
                            <i class="las la-search me-1"></i> @lang('Search')
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md">
                            <table class="table custom--table">
                            <thead>
                                <tr>
                                    <th>@lang('Gateway | Transaction')</th>
                                    <th>@lang('Initiated')</th>
                                    <th>@lang('Amount')</th>
                                    <th>@lang('Conversion')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Detail')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($deposits as $deposit)
                                    <tr>
                                        <td>
                                        <span class="fw-bold"> <span class="text--base">{{ __($deposit->gateway?->name) }}</span> </span>
                                        <br>
                                        <small> {{ $deposit->trx }} </small>
                                    </td>

                                    <td class="text-center">
                                        {{ showDateTime($deposit->created_at) }}<br>{{ diffForHumans($deposit->created_at) }}
                                    </td>
                                    <td class="text-center">
                                        {{ __($general->cur_sym) }}{{ showAmount($deposit->amount ) }} + <span class="text--danger" title="@lang('charge')">{{ showAmount($deposit->charge)}} </span>
                                        <br>
                                        <strong title="@lang('Amount with charge')">
                                        {{ showAmount($deposit->amount+$deposit->charge) }} {{ __($general->cur_text) }}
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        1 {{ __($general->cur_text) }} =  {{ showAmount($deposit->rate) }} {{__($deposit->method_currency)}}
                                        <br>
                                        <strong>{{ showAmount($deposit->final_amo) }} {{__($deposit->method_currency)}}</strong>
                                    </td>
                                    <td class="text-center">
                                        @php echo $deposit->statusBadge @endphp
                                    </td>
                                        @php
                                            $details = ($deposit->detail != null) ? json_encode($deposit->detail) : null;
                                        @endphp
                                        <td>
                                            <button class="icon-btn bg--base
                                                @if($deposit->method_code >= 1000)
                                                    detailBtn
                                                @else
                                                    disabled
                                                @endif"
                                                @if($deposit->method_code >= 1000)
                                                    data-info="{{ $details }}"
                                                @endif
                                                @if ($deposit->status == Status::PAYMENT_REJECT)
                                                    data-admin_feedback="{{ $deposit->admin_feedback }}"
                                                @endif
                                                >
                                                <i class="fa fa-desktop"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100%" class="text-center">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="pt-50 justify-content-center d-flex">
            @php echo paginateLinks($deposits) @endphp
        </div>
    </div>
</section>

{{-- APPROVE MODAL --}}
<div id="approveModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Details')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <li>@lang('Amount') : <span class="withdraw-amount fw-bold"></span></li>
                    <li>@lang('Charge') : <span class="withdraw-charge fw-bold"></span></li>
                    <li>@lang('After Charge') : <span class="withdraw-after_charge fw-bold"></span></li>
                    <li>@lang('Conversion Rate') : <span class="withdraw-rate fw-bold"></span></li>
                    <li>@lang('Payable Amount') : <span class="withdraw-payable fw-bold"></span></li>
                </ul>
                <ul class="list-group withdraw-detail mt-1">
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
            </div>
        </div>
    </div>
</div>

{{-- Detail MODAL --}}
<div id="detailModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title ">@lang('Details')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush userData">
                </ul>
                <div class="feedback"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
            </div>
        </div>
    </div>
</div>
@endsection


@push('script')
    <script>
        (function ($) {
            "use strict";

            $('.detailBtn').on('click', function () {
                var modal = $('#detailModal');

                var userData = $(this).data('info');
                var html = '';
                if(userData){
                    userData.forEach(element => {
                        if(element.type != 'file'){
                            html += `
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>${element.name}</span>
                                <span">${element.value}</span>
                            </li>`;
                        }
                    });
                }

                modal.find('.userData').html(html);

                if($(this).data('admin_feedback') != undefined){
                    var adminFeedback = `
                        <div class="my-3 ms-2">
                            <strong>@lang('Admin Feedback')</strong>
                            <p>${$(this).data('admin_feedback')}</p>
                        </div>
                    `;
                }else{
                    var adminFeedback = '';
                }

                modal.find('.feedback').html(adminFeedback);

                modal.modal('show');
            });
        })(jQuery);

    </script>
@endpush
