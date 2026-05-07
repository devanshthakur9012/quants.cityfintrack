@extends($activeTemplate.'layouts.master')
@section('content')
<div class="pt-100 pb-100 bg-light">
    <div class="container">
        <form action="#" class="transparent-form mb-3">
            <div class="row g-2">
                <div class="col-lg-3 form-group">
                    <label class="form-label">@lang('Transaction Number')</label>
                    <input type="text" name="search" value="{{ request()->search }}" class="form--control form-control" placeholder="@lang('Trx Number')">
                </div>
                <div class="col-lg-3 form-group">
                    <label class="form-label">@lang('Type')</label>
                    <select name="trx_type" class="form--control form-control form-select">
                        <option value="">@lang('All')</option>
                        <option value="+" @selected(request()->trx_type == '+')>@lang('Plus')</option>
                        <option value="-" @selected(request()->trx_type == '-')>@lang('Minus')</option>
                    </select>
                </div>
                <div class="col-lg-3 form-group mb-lg-0 mb-3">
                    <label class="form-label">@lang('Remark')</label>
                    <select class="form--control form-control form-select" name="remark">
                        <option value="">@lang('Any')</option>
                        @foreach($remarks as $remark)
                            <option value="{{ $remark->remark }}" @selected(request()->remark == $remark->remark)>
                                {{ __(keyToTitle($remark->remark)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 form-group mt-auto">
                    <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i> @lang('Filter')</button>
                </div>
            </div>
        </form>
        <table class="table table--responsive--lg mt-4">
            <thead>
                <tr>
                    <th>@lang('Trx')</th>
                    <th>@lang('Transacted')</th>
                    <th>@lang('Amount')</th>
                    <th>@lang('Post Balance')</th>
                    <th>@lang('Detail')</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $trx)
                    <tr>
                        <td>
                            <strong>{{ $trx->trx }}</strong>
                        </td>
                        <td>
                            <div>
                                {{ showDateTime($trx->created_at) }}<br>{{ diffForHumans($trx->created_at) }}
                            </div>
                        </td>
                        <td class="budget">
                            <span class="fw-bold @if($trx->trx_type == '+')text--success @else text--danger @endif">
                                {{ $trx->trx_type }} {{showAmount($trx->amount)}} {{ $general->cur_text }}
                            </span>
                        </td>
                        <td class="budget">
                            <div>
                                {{ showAmount($trx->post_balance) }} {{ __($general->cur_text) }}
                            </div>
                        </td>
                        <td>
                            <div>{{ __($trx->details) }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="pt-50">
            {{ paginateLinks($transactions) }}
        </div>
    </div>
</div>
@endsection


