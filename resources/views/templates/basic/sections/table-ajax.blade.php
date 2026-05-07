<div class="container">
    <div class="about-thumb -from-top-wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s" id="faqAccordion">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="d-flex align-items-center">
                    <h2>Top Gainers</h2>
                </div>
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md">
                            <table class="text-start table custom--table">
                                <thead>
                                    <tr>
                                        <th class="text-start">@lang('Symbol')</th>
                                        <th class="text-start">@lang('LTP')</th>
                                        <th class="text-start">@lang('Change')</th>
                                        <th class="text-start">@lang('%Change')</th>
                                    </tr>
                                </thead>
                                <tbody id="topGainer">
                                    @if (isset($topGainer))
                                        @if (count($topGainer))
                                            @foreach ($topGainer as $item)
                                                @if ($item->type == "gainer")
                                                    <tr>
                                                        <td class="text-start">{{$item->symbol}}</td>
                                                        <td class="text-start">{{$item->ltp}}</td>
                                                        <td class="text-start text-success">{{$item->net_change}}</td>
                                                        <td class="text-start text-success">{{$item->per_change}}</td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="100%">
                                                    <span>No Data Found</span>
                                                </td>
                                            </tr>
                                        @endif                                            
                                    @else
                                        <tr>
                                            <td colspan="100%">
                                                <span>No Data Found</span>
                                            </td>
                                        </tr>
                                    @endif                                        
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="d-flex align-items-center">
                    <h2>Top Losers</h2>
                </div>
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md">
                            <table class="text-start table custom--table">
                                <thead>
                                    <tr>
                                        <th class="text-start">@lang('Symbol')</th>
                                        <th class="text-start">@lang('LTP')</th>
                                        <th class="text-start">@lang('Change')</th>
                                        <th class="text-start">@lang('%Change')</th>
                                    </tr>
                                </thead>
                                <tbody id="topLoser">
                                    @if (isset($topLoser))
                                        @if (count($topLoser))
                                            @foreach ($topLoser as $item)
                                                @if ($item->type == "loser")
                                                    <tr>
                                                        <td class="text-start">{{$item->symbol}}</td>
                                                        <td class="text-start">{{$item->ltp}}</td>
                                                        <td class="text-start text-danger">{{$item->net_change}}</td>
                                                        <td class="text-start text-danger">{{$item->per_change}}</td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @else
                                            <tr>
                                                <td  colspan="100%">
                                                    <span>No Data Found</span>
                                                </td>
                                            </tr>
                                        @endif
                                    @else
                                        <tr>
                                            <td  colspan="100%">
                                                <span>No Data Found</span>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
