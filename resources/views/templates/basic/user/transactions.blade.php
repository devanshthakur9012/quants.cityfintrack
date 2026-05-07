@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <form action="#" class="transparent-form mb-3">
            <div class="row">
                <div class="col-lg-3 form-group">
                    <label>@lang('Transaction Number')</label>
                    <input type="text" name="search" value="{{ request()->search }}" class="form--control" placeholder="@lang('Trx Number')">
                </div>
                <div class="col-lg-3 form-group">
                    <label>@lang('Type')</label>
                    <select name="type" class="form--control">
                        <option value="">@lang('All')</option>
                        <option value="+" @selected(request()->type == '+')>@lang('Plus')</option>
                        <option value="-" @selected(request()->type == '-')>@lang('Minus')</option>
                    </select>
                </div>
                <div class="col-lg-3 form-group">
                    <label>@lang('Remark')</label>
                    <select class="form--control" name="remark">
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
        <div class="row">
            <div class="col-lg-12">
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md">
                            <table class="table custom--table">
                                <thead>
                                    <tr>
                                        <th>@lang('Stock Name')</th>
                                        <th>@lang('Txn Date')</th>
                                        <th>@lang('Trade Type')</th>
                                        <th>@lang('Qty')</th>
                                        <th>@lang('Trx')</th>
                                        <th>@lang('Amount')</th>
                                        <th>@lang('Pooling Broker Name')</th>
                                        {{-- <th>@lang('Post Balance')</th> --}}
                                        {{-- <th>@lang('Detail')</th> --}}
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $trx)
                                    <tr>
                                        <td>
                                            <strong>{{ $trx->stock_name }}</strong>
                                        </td>
                                        <td>
                                            {{ showDate($trx->trx_date) }}
                                        </td>
                                        <td class="budget">
                                            <span class="fw-bold @if($trx->trx_type == '+')text--success @else text--danger @endif">
                                                {{ $trx->trx_type }} {{showAmount($trx->amount)}} {{ $general->cur_text }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $trx->quantity }}</strong>
                                        </td>
                                        <td>
                                            <strong>{{ $trx->trx }}</strong>
                                        </td>
                                        <td>
                                            {{ showAmount($trx->amount) }}
                                        </td>
                                        <td> @isset($trx->poolingAccountPortfolio->broker_name)
                                            {{ $trx->poolingAccountPortfolio->broker_name }}
                                        @endisset </td>
                                        {{-- <td class="budget">
                                            {{ showAmount($trx->post_balance) }} {{ __($general->cur_text) }}
                                        </td> --}}
                                        {{-- <td>{{ __($trx->details) }}</td> --}}
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 justify-content-center d-flex">
            {{ paginateLinks($transactions) }}
        </div>
    </div>
</section>
@endsection


