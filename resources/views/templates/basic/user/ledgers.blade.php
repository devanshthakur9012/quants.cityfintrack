@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <form action="#" class="transparent-form mb-3">
            <div class="row">
                <div class="col-lg-3 form-group">
                    <label>@lang('Stock Name')</label>
                    <input type="text" name="search" value="{{ request()->search }}" class="form--control" placeholder="@lang('Stock Name')">
                </div>
                {{-- <div class="col-lg-3 form-group">
                    <label>@lang('Type')</label>
                    <select name="type" class="form--control">
                        <option value="" disabled>@lang('Select an option')</option>
                        <option value="+" @selected(request()->type == '+')>@lang('Profit')</option>
                        <option value="-" @selected(request()->type == '-')>@lang('Minus')</option>
                    </select>
                </div> --}}
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
                                        <th>@lang('Baught Date')</th>
                                        <th>@lang('Buy Price')</th>
                                        <th>@lang('Qty')</th>
                                        <th>@lang('Sold Date')</th>
                                        <th>@lang('Sell Price')</th>
                                        <th>@lang('Profit/Loss')</th>
                                        <th>@lang('Pooling Broker Name')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($ledgers as $ledger)
                                    <tr>
                                        <td>
                                            <strong>{{ $ledger->stock_name }}</strong>
                                        </td>
                                        <td>{{ showDate($ledger->baught_date) }}</td>
                                        <td>{{ $ledger->buy_price }}</td>
                                        <td>{{ $ledger->quantity }}</td>
                                        <td>{{ showDate($ledger->sold_date) }}</td>
                                        <td>{{ $ledger->sell_price }}</td>
                                        <td> 
                                            <span class="{{$ledger->profit_loss > 0 ? "text-success" : "text-danger"}}"> {{ $ledger->profit_loss }}</span>
                                           </td>
                                        <td>{{ $ledger->poolingAccountPortfolio->broker_name }}</td>
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
            {{ paginateLinks($ledgers) }}
        </div>
    </div>
</section>
@endsection


