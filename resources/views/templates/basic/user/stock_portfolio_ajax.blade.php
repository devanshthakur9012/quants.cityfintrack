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
                            @forelse($stockPortfolios as $stockPortfolio)
                            {{-- @php  $key = isset($date[$stockPortfolio->stock_name.'.NS']) ? $date[$stockPortfolio->stock_name.'.NS'] : 0;
                            @endphp --}}
                            <tr>
                                <td>
                                    {{ $stockPortfolio->broker_name }}
                                </td>
                                <td>
                                    {{ $stockPortfolio->stock_name }}
                                </td>
                                <td>
                                    {{ $stockPortfolio->quantity }}
                                </td>
                                <td>
                                    {{ showDate($stockPortfolio->buy_date) }}
                                </td>
                                <td>
                                    {{ showAmount($stockPortfolio->buy_price) }}
                                </td>
                                <td>{{showAmount($stockPortfolio->cmp)}}</td>
                                <td>
                                    {{ showAmount($stockPortfolio->quantity*$stockPortfolio->cmp) }}
                                </td>
                                <td>
                                    @php $vals = $stockPortfolio->quantity*($stockPortfolio->cmp - $stockPortfolio->buy_price); @endphp
                                    <span class="{{$vals > 0 ? "text-success" : "text-danger"}}">{{showAmount($stockPortfolio->quantity*($stockPortfolio->cmp - $stockPortfolio->buy_price))}}</span>
                                     </td>
                                <td>{{ $stockPortfolio->sector }}</td>
                                <td>{{ $stockPortfolio->poolingAccountPortfolio->broker_name }}</td>
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
    {{ paginateLinks($stockPortfolios) }}
</div>