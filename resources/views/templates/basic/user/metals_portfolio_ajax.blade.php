<div class="row">
    <div class="col-lg-12">
        <div class="custom--card">
            <div class="card-body p-0">
                <div class="table-responsive--md">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>@lang('Broker Name')</th>
                                <th>@lang('Stock Name')</th>
                                <th>@lang('Qty')</th>
                                <th>@lang('Buy Date')</th>
                                <th>@lang('Buy Price')</th>
                                <th>@lang('CMP')</th>
                                <th>@lang('Current Value')</th>
                                <th>@lang('Profit/Loss')</th>
                                <th>@lang('Sector')</th>
                                <th>@lang('Pooling Broker Name')</th>
                            </tr>
                        </thead>
                        {{-- @php
                        $date = \DB::connection('mysql_pr')->table('LTP')->WHEREIN('symbol',$symbolArray)->pluck('ltp','symbol')->toArray();  
                        @endphp --}}
                       <tbody>
                        @forelse($metalsPortfolios as $metalsPortfolio)
                        {{-- @php $key = isset($date[$metalsPortfolio->stock_name.'.NS']) ? $date[$metalsPortfolio->stock_name.'.NS'] : 0;
                        @endphp --}}
                        <tr>
                            <td>
                                {{ $metalsPortfolio->broker_name }}
                            </td>
                            <td>
                                {{ $metalsPortfolio->stock_name }}
                            </td>
                            <td>
                                {{ $metalsPortfolio->quantity }}
                            </td>
                            <td>
                                {{ showDate($metalsPortfolio->buy_date) }}
                            </td>
                            <td>
                                {{ showAmount($metalsPortfolio->buy_price) }}
                            </td>
                            <td>{{showAmount($metalsPortfolio->cmp)}}</td>
                            <td>
                                {{ showAmount($metalsPortfolio->quantity*$metalsPortfolio->cmp) }}
                            </td>
                            <td>
                                @php $vals = $metalsPortfolio->quantity*($metalsPortfolio->cmp - $metalsPortfolio->buy_price);  @endphp
                                <span class="{{ $vals > 0 ? "text-success" : "text-danger"}}"> {{showAmount($metalsPortfolio->quantity*($metalsPortfolio->cmp - $metalsPortfolio->buy_price))}}</span>
                                </td>
                            <td>{{ $metalsPortfolio->sector }}</td>
                            <td>{{ $metalsPortfolio->poolingAccountPortfolio->broker_name }}</td>
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
    {{ paginateLinks($metalsPortfolios) }}
</div>