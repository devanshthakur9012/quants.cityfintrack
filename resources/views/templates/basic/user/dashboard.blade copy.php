@extends($activeTemplate.'layouts.master')
@section('content')
<!-- dashboard section start -->

<section class="pt-100 pb-100">
    <div class="container content-container">

        {{-- <div class="row notice"></div> --}}
        <div class="row justify-content-center g-3">
            {{-- <div class="col-md-12 mb-3">
                <form action="#" class="transparent-form">
                    <label>@lang('Referral Link')</label>
                    <div class="input-group">
                        <input type="text" name="text" class="form-control form--control referralURL"
                            value="{{ route('home', ['reference'=>$user->username]) }}" readonly
                        >
                        <button class="input-group-text bg--base text-white border-0" id="copyBoard" type="button">
                            <span class="copytext"><i class="fa fa-copy"></i></span>
                        </button>
                    </div>
                </form>
            </div> --}}

            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.transactions') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'capital_investment.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('Invested Amount')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($totalInvestedAmount, 2) }}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($totalCurrentAmount, 2) }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.stock.portfolios') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'portfolio.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('Stock Portfolio')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($stockPortFolio->buy_value,2) }}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($stockPortFolio->current_value,2) }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.thematic.portfolios') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'themeatic_portfolio.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('Thematic Portfolio')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    ----
                                    {{-- {{ $general->cur_sym }} {{ showAmount($user->balance, 2) }} --}}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    ----
                                    {{-- {{ $general->cur_sym }} {{ showAmount($user->balance, 2) }} --}}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.global.stock.portfolio') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'global_stocks.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('Global Stock Portfolio')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($globalStockPortFolio->buy_value,2) }}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($globalStockPortFolio->current_value,2) }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.fo.portfolio.hedging') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'profit.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('F&O Portfolio-Hedging')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($foglobalStockPortFolio->buy_value,2) }}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($foglobalStockPortFolio->current_value,2) }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>

            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.metals.portfolio') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'metals_portfolio.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('Metals Portfolio (Gold & Silver)')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($metalsPortFolio->buy_value,2) }}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($metalsPortFolio->current_value,2) }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>

            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.transactions') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'ledger.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('Invested in All Portfolios')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($user->balance, 2) }}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($user->balance, 2) }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.transactions') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'pf_current_value.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('All Portfolios Current Value')</h4>
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Invested Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($user->balance, 2) }}
                                </h6>
                            </div>
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <p class="d-widget__caption fs--12px">@lang('Current Value')</p>
                                <h6 class="d-widget__amount mt-1">
                                    {{ $general->cur_sym }} {{ showAmount($user->balance, 2) }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.transactions') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <img src="{{ getImage('assets/templates/basic/images/dashboard/' .'networth.png') }}" alt="logo">
                        {{-- <i class="las la-money-bill-wave text--base"></i> --}}
                    </div>
                    <div class="d-widget__content">
                        <h4 class="d-widget__caption text-center">@lang('Networth')</h4>
                        <h3 class="d-widget__amount mt-1">
                            {{ $general->cur_sym }} {{ showAmount($user->balance, 2) }}
                        </h3>
                    </div>
                </div><!-- d-widget end -->
            </div>
            {{-- <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="javascript:void(0)" class="item--link {{ $user->package_id ? 'renewBtn' : null }}"
                        @if($user->package_id)
                            data-package="{{ @$user->package }}"
                        @endif
                    >
                    </a>
                    <div class="d-widget__icon v">
                        <i class="las la-calendar text--base"></i>
                    </div>
                    <div class="d-widget__content">
                        <p class="d-widget__caption fs--14px">
                            @if($user->package_id != 0)
                                {{ __(@$user->package->name) }}
                            @else
                                @lang('Package')
                            @endif
                        </p>
                        <div class="d-flex align-items-center">
                            <h3 class="d-widget__amount mt-1">
                                @if($user->package_id != 0)
                                    {{ showDateTime($user->validity, 'd M Y') }}
                                @else
                                    @lang('N/A')
                                @endif
                            </h3>
                            <small class="d-widget__caption ms-2">(@lang('Validity'))</small>
                        </div>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.deposit.history') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <i class="las la-wallet text--base"></i>
                    </div>
                    <div class="d-widget__content">
                        <p class="d-widget__caption fs--14px">@lang('Total Deposit')</p>
                        <h3 class="d-widget__amount mt-1">
                            {{ $general->cur_sym }} {{ showAmount($totalDeposit, 2) }}
                        </h3>
                    </div>
                </div><!-- d-widget end -->
            </div>

            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.signals') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <i class="las la-signal text--base"></i>
                    </div>
                    <div class="d-widget__content">
                        <p class="d-widget__caption fs--14px">@lang('Total Signal')</p>
                        <h3 class="d-widget__amount mt-1">
                            {{ $totalSignal }}
                        </h3>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.transactions') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <i class="las la-exchange-alt text--base"></i>
                    </div>
                    <div class="d-widget__content">
                        <p class="d-widget__caption fs--14px">@lang('Total Transaction')</p>
                        <h3 class="d-widget__amount mt-1">
                            {{ $totalTrx }}
                        </h3>
                    </div>
                </div><!-- d-widget end -->
            </div>
            <div class="col-xl-4 col-lg-4 col-md-6">
                <div class="d-widget has--link">
                    <a href="{{ route('user.referrals') }}" class="item--link"></a>
                    <div class="d-widget__icon">
                        <i class="las la-users text--base"></i>
                    </div>
                    <div class="d-widget__content">
                        <p class="d-widget__caption fs--14px">@lang('Total Referral')</p>
                        <h3 class="d-widget__amount mt-1">
                            {{ $user->referrals->count() }}
                        </h3>
                    </div>
                </div><!-- d-widget end -->
            </div>--}}
        </div> 

        <div class="row mt-4">
            <div class="col-xl-6">
                <div class="card dash-card">
                    <div class="card-header">
                        <h5 class="card-title">@lang('Networth Graph')</h5>
                    </div>
                    <div class="card-body">
                       
                        <div id="apex-spline-chart" style="width: 100%;"> </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card dash-card">
                     <div class="card-header">
                         <h5 class="card-title">@lang('Sectory Wise Protfolio')</h5>
                    </div>
                    <div class="card-body">
                      
                        <div id="apex-polar-area-basic-chart" style="width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-6">
                <h5 class="m-4 text-center">@lang('Top Gainers')</h5>
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md">
                            <table class="table custom--table">
                                <thead>
                                    <tr>
                                        <th>@lang('Stock Name')</th>
                                        <th>@lang('Avg Price')</th>
                                        <th>@lang('CMP')</th>
                                        <th>@lang('Change%')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- @php
                                    $date = \DB::connection('mysql_pr')->table('LTP')->WHEREIN('symbol',$symbolArray)->pluck('ltp','symbol')->toArray();  
                                    @endphp -->
                                    @php
                                        $date = [];
                                        try {
                                            $date = \DB::connection('mysql_pr')
                                                ->table('LTP')
                                                ->whereIn('symbol', $symbolArray)
                                                ->pluck('ltp', 'symbol')
                                                ->toArray();
                                        } catch (\Exception $e) {
                                            \Log::error("Failed to fetch LTP data from mysql_pr: " . $e->getMessage());
                                        }
                                    @endphp

                                    @forelse($portfolioTopGainers as $portfolioTopGainer)
                                        @php  $key = isset($date[$portfolioTopGainer->stock_name.'.NS']) ? $date[$portfolioTopGainer->stock_name.'.NS'] : 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $portfolioTopGainer->stock_name }}
                                            </td>
                                            <td>
                                                {{ showAmount($portfolioTopGainer->avg_buy_price) }}
                                            </td>
                                            <td>{{showAmount($key)}}</td>
                                            @php
                                                $cmp = $key;
                                                $avgPrice = $portfolioTopGainer->avg_buy_price;
                                                $change = ($cmp -$avgPrice)/$avgPrice
                                            @endphp
                                            <td class="{{($change * 100) > 0 ? "text-success" : "text-danger"}}">{{ showAmount($change * 100)  }}</td>
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
            <div class="col-lg-6">
                <h5 class="m-4 text-center">@lang('Top Losers')</h5>
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md">
                            <table class="table custom--table">
                                <thead>
                                    <tr>
                                        <th>@lang('Stock Name')</th>
                                        <th>@lang('Avg Price')</th>
                                        <th>@lang('CMP')</th>
                                        <th>@lang('Change%')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- @php
                                    $date = \DB::connection('mysql_pr')->table('LTP')->WHEREIN('symbol',$symbolArray2)->pluck('ltp','symbol')->toArray();  
                                    @endphp -->
                                    @php
                                        $date = [];
                                        try {
                                            $date = \DB::connection('mysql_pr')
                                                ->table('LTP')
                                                ->whereIn('symbol', $symbolArray2)
                                                ->pluck('ltp', 'symbol')
                                                ->toArray();
                                        } catch (\Exception $e) {
                                            \Log::error("Failed to fetch LTP data from mysql_pr for symbolArray2: " . $e->getMessage());
                                        }
                                    @endphp

                                    <tbody>
                                        @forelse($portfolioTopLosers as $portfolioTopLoser)
                                            @php  $key = isset($date[$portfolioTopLoser->stock_name.'.NS']) ? $date[$portfolioTopLoser->stock_name.'.NS'] : 0;
                                            @endphp
                                            <tr>
                                                <td>
                                                    {{ $portfolioTopLoser->stock_name }}
                                                </td>
                                                <td>
                                                    {{ showAmount($portfolioTopLoser->avg_buy_price) }}
                                                </td>
                                                <td>{{showAmount($key)}}</td>
                                                @php
                                                    $cmp = $key;
                                                    $avgPrice = $portfolioTopLoser->avg_buy_price;
                                                    $change = ($cmp -$avgPrice)/$avgPrice
                                                @endphp
                                            <td class="{{($change * 100) > 0 ? "text-success" : "text-danger"}}">{{ showAmount($change * 100)  }}</td>
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

            {{-- FOR STRENGTH DATA --}}
            <div class="col-lg-12 mt-4" id="pst_hre">
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
                @if ($stock_name == "")
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
                @else 
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
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tbody>
                                                @php  
                                                    $totalItems = 0;
                                                    $itemsPerPage = 100;
                                                    $currentPage =  isset($_GET['pages']) ? $_GET['pages'] : 1;
                                                @endphp
                                                @forelse($greekSentiments as $sentiment)
                                                    @php
                                                        $content = json_decode($sentiment->data,true);
                                                        $date = $content['date'];
                                                        $totalItems = count($content['date']);
                                                        $newArr = array_reverse($content['date'],true);
                                                        $currentItems = array_slice($newArr, ($currentPage - 1) * $itemsPerPage, $itemsPerPage,true);

                                                        // ALL DATA
                                                        $timestamp = $content['timestamp'];
                                                        $symbol = $content['symbol'];
                                                        $ce_iv_avg = $content['ce_iv_avg'];
                                                        $ce_iv_std = $content['ce_iv_std'];
                                                        $pe_iv_avg = $content['pe_iv_avg'];
                                                        $pe_iv_std = $content['pe_iv_std'];
                                                        $iv_sentiment = $content['iv_sentiment'];
                                                        $ce_theta_avg = $content['ce_theta_avg'];
                                                        $ce_theta_std = $content['ce_theta_std'];
                                                        $pe_theta_avg = $content['pe_theta_avg'];
                                                        $pe_theta_std = $content['pe_theta_std'];
                                                        $theta_sentiment =$content['theta_sentiment'];
                                                    @endphp
                                                    @foreach ($currentItems as $k => $item)
                                                        <tr>
                                                            <td>{{date("d-m-Y",($item/1000))}}</td>
                                                            @php
                                                                $milliseconds = $timestamp[$k];
                                                                $seconds = $milliseconds / 1000;
                                                                $dateTime = date("H:i:s", $milliseconds);
                                                            @endphp
                                                            <td >{{$dateTime}}</td>
                                                            <td>{{$symbol[$k]}}</td>
                                                            <td>{{$ce_iv_avg[$k]}}</td>
                                                            <td>{{$ce_iv_std[$k]}}</td>
                                                            <td>{{$pe_iv_avg[$k]}}</td>
                                                            <td>{{$pe_iv_std[$k]}}</td>
                                                            <td>{{$iv_sentiment[$k]}}</td>
                                                            <td>{{$ce_theta_avg[$k]}}</td>
                                                            <td>{{$ce_theta_std[$k]}}</td>
                                                            <td>{{$pe_theta_avg[$k]}}</td>
                                                            <td>{{$pe_theta_std[$k]}}</td>
                                                            <td>{{$theta_sentiment[$k]}}</td>
                                                        </tr>
                                                    @endforeach
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
                            @php
                                $totalPages = ceil($totalItems / $itemsPerPage);
                                echo '<nav class="mt-3 justify-content-end d-flex">
                                        <ul class="pagination mb-0">';
                            @endphp
                            @for($i = 1; $i <= $totalPages; $i++)
                                @if($i == $currentPage)
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{$i}}</span></li>
                                @else
                                <li class="page-item"><a class="page-link" href="{{url('user/dashboard?stock_name='.$stock_name.'&timeframe='.$timeframe.'&pages='.$i.'')}}">{{$i}}</a></li>
                                @endif
                            @endfor
                            @php     
                                echo '  </ul>
                                    </nav>';
                            @endphp
                        </div>
                    @endisset
                @endif
               
            </div>
        </div>

    </div>
</section>

<!-- dashboard section end -->
@if($user->package_id)
    <div class="modal fade cmn--modal" id="renewModal">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title method-name"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{route('user.renew.package')}}" method="post">
                    @csrf
                    <div class="modal-body pt-0">
                        <div class="form-group">
                            <input type="hidden" name="id" required>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">@lang('Product') <span class="packageName"></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">@lang('Price') <span class="packagePrice"></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">@lang('Validity') <span class="packageValidity"></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">@lang('Your Balance')
                                <span>{{ showAmount($user->balance, 2) }} {{ __($general->cur_text) }} </span>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
                        <div class="prevent-double-click">
                            <button type="submit" class="btn btn-sm btn--success">@lang('Confirm')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@endsection
@push('script')
{{-- <script>
    $(document).ready(function(){
        function reloadData(){
            $.get('{!!url("user/dashboard-ajax")!!}',function(data){
                $("#pst_hre").html(data);
            });
        }
        setInterval(() => {
            reloadData();
        }, 1000);//call every 15 minute
    });
</script> --}}

<script>
    (function ($) {
        "use strict";

        @if($user->package_id != 0)
            $('.renewBtn').on('click', function () {
                var modal = $('#renewModal');

                modal.find('.modal-title').text('Are you sure to renew '+$(this).data('package').name);
                modal.find('.packageName').text($(this).data('package').name);
                modal.find('.packagePrice').text($(this).data('package').price+' '+@json( __($general->cur_text) ));
                modal.find('.packageValidity').text($(this).data('package').validity+' Days');
                modal.find('input[name=id]').val($(this).data('package').id);

                modal.modal('show');
            });
        @endif

        $('#copyBoard').click(function(){
            var copyText = document.getElementsByClassName("referralURL");
            copyText = copyText[0];
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            /*For mobile devices*/
            document.execCommand("copy");
            notify('success', "Copied: " + copyText.value)
        });

    })(jQuery);


</script>
<script src="{{ asset('assets/admin/js/vendor/apexcharts.min.js') }}"></script>
<script src="{{ asset('assets/admin/js/vendor/chart.js.2.8.0.js') }}"></script>
<script>
    "use strict";
        var options = {
        series: [
            {
                name: '',
                data: [{{implode(",",$buyArr)}}]
            }, {
                name: '',
                data: [{{implode(",",$currArr)}}]
            }
        ],
        chart: {
             width: '100%',
            height: 360,
            type: 'area',
            toolbar: {
        show: false,
       
    }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth'
        },
        
        xaxis: {
            type: 'date',
            categories: [
                {!!"'".implode("','", $datesArr)."'"!!}
            ],
            labels: {
                style: {
                    colors: '#fff'
                }
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#fff'
                }
            }
        },
        tooltip: {
            enabled: true,
            theme: 'dark',
            x: {
                format: 'MM yyyy',
                style: {
                    color: '#fff'
                }
            },
        },
    };

    var chart = new ApexCharts(document.querySelector("#apex-spline-chart"), options);
    chart.render();


    var options = {
        series: [{{implode(',',$chrtArr)}}],
        chart: {
            width: '100%',
            height: 360,
            type: 'polarArea',
            foreColor: '#e4e4e4',
        },
        stroke: {
          colors: ['#fff']
        },
        fill: {
          opacity: 0.8
        },
        labels: ['Stock Portfolio', 'Metals Portfolio', 'Global stock', 'F&O Portfolio'], // Add your labels here
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                },
                legend: {
                    position: 'bottom'
                }
            }
        }]
    };

    var chart = new ApexCharts(document.querySelector("#apex-polar-area-basic-chart"), options);
    chart.render();


</script>

@endpush

