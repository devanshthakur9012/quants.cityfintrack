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
                            @forelse($foPortFolioHedgings as $foPortFolioHedging)
                                {{-- @php  $key = isset($date[$foPortFolioHedging->stock_name.'.NS']) ? $date[$foPortFolioHedging->stock_name.'.NS'] : 0;
                                @endphp --}}
                                <tr>
                                    <td>
                                        {{ $foPortFolioHedging->broker_name }}
                                    </td>
                                    <td>
                                        {{ $foPortFolioHedging->stock_name }}
                                    </td>
                                    <td>
                                        {{ $foPortFolioHedging->quantity }}
                                    </td>
                                    <td>
                                        {{ showDate($foPortFolioHedging->buy_date) }}
                                    </td>
                                    <td>
                                        {{ showAmount($foPortFolioHedging->buy_price) }}
                                    </td>
                                    <td>{{showAmount($foPortFolioHedging->cmp)}}</td>
                                    <td>
                                        {{ showAmount($foPortFolioHedging->quantity*$foPortFolioHedging->cmp) }}
                                    </td>
                                    <td> 
                                        @php $vals = $foPortFolioHedging->quantity*($foPortFolioHedging->cmp - $foPortFolioHedging->buy_price);
                                        @endphp
                                        <span class="{{$vals > 0 ? "text-success" : "text-danger"}}">{{showAmount($foPortFolioHedging->quantity*($foPortFolioHedging->cmp - $foPortFolioHedging->buy_price))}}</span>
                                         </td>
                                    <td>{{ $foPortFolioHedging->sector }}</td>
                                    <td>{{ $foPortFolioHedging->poolingAccountPortfolio->broker_name }}</td>
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
    {{ paginateLinks($foPortFolioHedgings) }}
</div>