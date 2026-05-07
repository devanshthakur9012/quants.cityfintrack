<div class="d-flex justify-content-between align-items-center">
    <h5 class="text-center">@lang('Greeks Market View')</h5>
    <form action="" class="transparent-form mb-3" method="GET">
        <div class="row">
            <div class="col-lg-3 form-group">
                <label for="stock_name">Symbol Name</label>
                <select name="stock_name" class="form--control" id="stock_name">
                    <option value="">Select Symbol Name</option>  
                    @foreach ($StrengthsymbolArr as $v)
                        @if(in_array($v,['CRUDEOIL','BANKNIFTY','FINNIFTY','SILVER','NIFTY','MIDCPNIFTY','NATURALGAS','SILVER','GOLD']))
                            <option value="{{$v}}" {{$v==$stock_name ? 'selected':''}}>{{$v}}</option>
                        @endif
                @endforeach
                </select>
            </div>
            <div class="col-lg-3 form-group">
                <label for="atm_type">Timeframe</label>
                <select name="timeframe" class="form--control" id="timeframe">
                    <option value="">Select Timeframe</option>  
                    <option value="5" {{$timeframe == "5" ? "selected" : ""}}>5</option>
                    <option value="10" {{$timeframe == "10" ? "selected" : ""}}>10</option>
                    <option value="15" {{$timeframe == "15" ? "selected" : ""}}>15</option>
                </select>
            </div>
            <div class="col-lg-3 form-group mt-auto">
                <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i> Filter</button>
            </div>
            <div class="col-lg-3 col-md-3 col-6 form-group mt-auto">
                <a href="{{url('/user/dashboard')}}" class="btn btn--base w-100"><i class="las la-redo-alt"></i> Refresh</a>
            </div>
        </div>
    </form>
</div>
@isset($greekSentiments)
<div class="custom--card">
    <div class="card-body p-0">
        <div class="table-responsive--md">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th class="text-uppercase">@lang('Date')</th>
                        <th class="text-uppercase">@lang('Time')</th>
                        <th class="text-uppercase">@lang('Stock Name')</th>
                        <th class="text-uppercase">@lang('ce iv avg')</th>
                        <th class="text-uppercase">@lang('ce iv std')</th>
                        <th class="text-uppercase">@lang('pe iv avg')</th>
                        <th class="text-uppercase">@lang('pe iv std')</th>
                        <th class="text-uppercase">@lang('iv sentiment')</th>
                        <th class="text-uppercase">@lang('ce theta avg')</th>
                        <th class="text-uppercase">@lang('ce theta std')</th>
                        <th class="text-uppercase">@lang('pe theta avg')</th>
                        <th class="text-uppercase">@lang('pe theta std')</th>
                        <th class="text-uppercase">@lang('theta sentiment')</th>
                        {{-- <th class="text-uppercase">@lang('market strength')</th> --}}
                    </tr>
                </thead>
                <tbody>
                    <tbody>
                        @forelse($greekSentiments as $data)
                            <tr>
                                <td>{{$data->date}}</td>
                                <td>{{$data->timestamp}}</td>
                                <td>{{$data->symbol}}</td>
                                <td>{{$data->ce_iv_avg}}</td>
                                <td>{{$data->ce_iv_std}}</td>
                                <td>{{$data->pe_iv_avg}}</td>
                                <td>{{$data->pe_iv_std}}</td>
                                <td>{{$data->iv_sentiment}}</td>
                                <td>{{$data->ce_theta_avg}}</td>
                                <td>{{$data->ce_theta_std}}</td>
                                <td>{{$data->pe_theta_avg}}</td>
                                <td>{{$data->pe_theta_std}}</td>
                                <td>{{$data->theta_sentiment}}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted text-center" colspan="100%">NO DATA FOUND</td>
                            </tr>
                        @endforelse
                    </tbody>
            </table>
        </div>
    </div>
</div>
<div class="mt-4 justify-content-center d-flex">
    {{ paginateLinks($greekSentiments)}}
</div>
@endisset