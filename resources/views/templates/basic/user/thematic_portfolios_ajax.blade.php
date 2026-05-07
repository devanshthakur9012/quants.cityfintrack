<div class="row">
    <div class="col-lg-12">
        <div class="custom--card">
            <div class="card-body p-0">
                <div class="table-responsive--md">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>@lang('Stock Name')</th>
                                <th>@lang('Reco Date')</th>
                                <th>@lang('Buy Price')</th>
                                <th>@lang('CMP')</th>
                                <th>@lang('PNL')</th>
                                <th>@lang('Sector')</th>
                            </tr>
                        </thead>
                        {{-- @php
                        $date = \DB::connection('mysql_pr')->table('LTP')->WHEREIN('symbol',$symbolArray)->pluck('ltp','symbol')->toArray();  
                        @endphp --}}
                        <tbody>
                            @forelse($thematicPortfolios as $thematicPortfolio)
                            {{-- @php  $key = isset($date[$thematicPortfolio->stock_name.'.NS']) ? $date[$thematicPortfolio->stock_name.'.NS'] : 0;
                            @endphp --}}
                            <tr>
                                <td>
                                    {{ $thematicPortfolio->stock_name }}
                                </td>
                                <td>
                                    {{ showDate($thematicPortfolio->reco_date) }}
                                </td>
                                <td>
                                   {{ showAmount($thematicPortfolio->buy_price) }}
                                </td>
                                <td>{{showAmount($thematicPortfolio->cmp)}}</td>
                                <td>
                                  <span class="{{$thematicPortfolio->cmp-$thematicPortfolio->buy_price > 0 ? "text-success" : "text-danger"}}">{{ showAmount($thematicPortfolio->cmp-$thematicPortfolio->buy_price) }}</span>  
                                </td>
                                <td>{{ $thematicPortfolio->sector }}</td>
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
    {{ paginateLinks($thematicPortfolios) }}
</div>