<!-- header-section start  -->
@role(['admin', 'trader'])
    <header class="header">
        <div class="header__bottom">
            <div class="container">
                <nav class="navbar navbar-expand-xl p-0 align-items-center">
                    <a class="site-logo site-title" href="{{ route('home') }}">
                        <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}" alt="logo" style="height:200px;">
                    </a>
                    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                        aria-expanded="false" aria-label="Toggle navigation">
                        <span class="menu-toggle"></span>
                    </button>
                    <div class="collapse navbar-collapse mt-lg-0 mt-3" id="navbarSupportedContent">
                        <ul class="navbar-nav main-menu ms-auto">
                            <li><a href="{{ route('user.home') }}">@lang('Dashboard')</a></li>
                            <li><a href="{{ route('pivot-analysis.index') }}">@lang('Pivot Analysis')</a></li>
                            <li><a href="{{ route('open-hl.index') }}">@lang('OpenRange Signal')</a></li>
                            <li><a href="{{ route('momentum-breakout.index') }}">@lang('Momentum Scanner')</a></li>
                            <li><a href="{{ route('oi-flow-sentiment.index') }}">@lang('EOD OI Signal')</a></li>
                            <li><a href="{{ route('intraday-oi-snapshot.index') }}">@lang('Intraday OI Signal')</a></li>
                            <li><a href="{{ route('nifty-breakout-analyzer.index') }}">@lang('Nifty Analyzer')</a></li>
                            <li><a href="{{ route('strata-options-fv.index') }}">@lang('Fair Price')</a></li>
                            <li><a href="{{ route('quantedge-smc.index') }}">@lang('QuantEdge Analysis')</a></li>
                            <li><a href="{{ route('primeflow-scanner.index') }}">@lang('PrimeFlow Scanner')</a></li>
                            <li><a href="{{ route('straddle-strategy.index') }}">@lang('Straddle & Strangle')</a></li>
                            
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('1HR Pivot')</a>
                                <ul class="sub-menu">
                                    <li>
                                        <a href="{{ route('pivot-signal.config.index') }}">
                                            <i class="las la-robot"></i> Both Pivot Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('pivot-signal.index') }}">
                                            <i class="las la-robot"></i> 1HR Pivot Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('pivot-signal-15.index') }}">
                                            <i class="las la-robot"></i> 15MIN Pivot Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('symbols.analysis') }}">
                                            <i class="las la-robot"></i> FUT Trend Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('symbols.backtesting') }}">
                                            <i class="las la-robot"></i> FUT Back Testing
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('manual-orders.index') }}">
                                            <i class="las la-robot"></i> Manual Order
                                        </a>
                                    </li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Pivot Analysis')</a>
                                <ul class="sub-menu">
                                    <li>
                                        <a href="{{ route('pivot-order-config.index') }}">
                                            <i class="las la-robot"></i> Daily Pivot AMO Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('pivot-normal-order-config.index') }}">
                                            <i class="las la-robot"></i> Daily Pivot Normal Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('pivot.index') }}">
                                            <i class="las la-robot"></i> Daily Pivot Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('fut-ohl-auto.config') }}">
                                            <i class="las la-robot"></i> H/L FUT Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('fut-ohl.analysis') }}">
                                            <i class="las la-robot"></i> H/L FUT Analysis
                                        </a>
                                    </li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('OI & IV Analysis')</a>
                                <ul class="sub-menu">
                                    <li>
                                        <a href="{{ route('one-percent-auto.index') }}">
                                            <i class="las la-robot"></i> 1% Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('symbols.one-percent') }}">
                                            <i class="las la-robot"></i> 1% Change Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('oiiv-auto.config') }}">
                                            <i class="las la-robot"></i> Daily Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('portfolio.amo-config.index') }}">
                                            <i class="las la-robot"></i> AMO Sell Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('portfolio.broker-sell-config.index') }}">
                                            <i class="las la-robot"></i> Normal Sell Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('portfolio.broker-stop-loss-config.index') }}">
                                            <i class="las la-robot"></i> SL Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('exit-plan.index') }}">
                                            <i class="las la-robot"></i> Exit Plan Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('exit-plan.config.index') }}">
                                            <i class="las la-robot"></i> Exit Plan Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('oiiv-auto.pece-analysis') }}">
                                            <i class="las la-robot"></i> Daily Change Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('oi-strategy.index') }}">
                                            <i class="las la-robot"></i> Daily OI Strategy
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('9to12.config') }}">
                                            <i class="las la-robot"></i> 9to12 Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('9to12.pece-analysis') }}">
                                            <i class="las la-robot"></i> 9to12 Analysis
                                        </a>
                                    </li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Nifty Analysis')</a>
                                <ul class="sub-menu">
                                    <li>
                                        <a href="{{ route('nifty-driven-breakout.config') }}">
                                            <i class="las la-robot"></i> Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('nifty-driven-breakout.index') }}">
                                            <i class="las la-robot"></i> Analysis
                                        </a>
                                    </li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Other Analysis')</a>
                                <ul class="sub-menu">
                                    <li>
                                        <a href="{{ route('option-fair-value.index') }}">
                                            <i class="las la-robot"></i> Fair Price
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('smart-money.index') }}">
                                            <i class="las la-robot"></i> Daily Stock Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('expiry-oi.scan') }}">
                                            <i class="las la-robot"></i> Multi-Symbol Scanner 
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('straddle-strangle.index') }}">
                                            <i class="las la-robot"></i> 1HR Straddle & Strangle Scanner 
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('straddle-strangle-15.index') }}">
                                            <i class="las la-robot"></i> 15MIN Straddle & Strangle Scanner 
                                        </a>
                                    </li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Option Data')</a>
                                <ul class="sub-menu">
                                    <li>
                                        <a href="{{ route('user.zerodha-auto.index') }}">
                                            <i class="las la-robot"></i> FUT Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('futures.config') }}">
                                            <i class="las la-robot"></i> FUT Signal Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('futures.supertrend-analysis') }}">
                                            <i class="las la-robot"></i> FUT Trend Analysis
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('futures.backtesting') }}">
                                            <i class="las la-robot"></i> FUT Back Testing
                                        </a>
                                    </li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Expiry Data')</a>
                                <ul class="sub-menu">
                                    <li>
                                        <a href="{{ route('expiry.auto.index') }}">
                                            <i class="las la-robot"></i> Expiry Order Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('expiry.config.index') }}">
                                            <i class="las la-robot"></i> Expiry Signal Config
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('expiry.analysis') }}">
                                            <i class="las la-robot"></i> Expiry Analysis
                                        </a>
                                    </li>
                                    <li><a href="{{ route('user.historical.options') }}">@lang('Old Historical Data')</a></li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Portfolios')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.histofical-portfolio') }}">@lang('Old Histofical Portfolio')</a></li>
                                    <li><a href="{{ route('user.historical.new-portfolio') }}">@lang('New Histofical Portfolio')</a></li>
                                </ul>
                            </li>
                            <li class="menu_has_children">
                                <a href="#0">@lang('Historical Data')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.weekly.astro.analysis') }}">@lang('Weekly Astro')</a></li>
                                    <li><a href="{{ route('user.astro.trading') }}">@lang('Astro Trading')</a></li>
                                    <li><a href="{{ route('user.unified.analysis') }}">@lang('Unified Options Analysis')</a></li>
                                    <li><a href="{{ route('user.historical.options') }}">@lang('Old Historical Data')</a></li>
                                    <li><a href="{{ route('user.early.historical.options') }}">@lang('Old Early Historical Data')</a></li>
                                    <li><a href="{{ route('user.historical.analysis') }}">@lang('New Historical Data')</a></li>
                                    <li><a href="{{ route('user.volume.analytics') }}">@lang('New Volume Data')</a></li>
                                    <li><a href="{{ route('user.early-historical.analysis') }}">@lang('New Early Historical Data')</a></li>
                                </ul>
                            </li>
                            
                            <li class="menu_has_children">
                                <a href="#0">@lang('Instrument Historical')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.futures.signal') }}">@lang('Combined Signal')</a></li>
                                    <li><a href="{{ route('user.instrument.historical.data') }}">@lang('Trend Analysis')</a></li>
                                    <li><a href="{{ route('user.instrument.historical.analysis') }}">@lang('New Trend Analysis')</a></li>
                                    <li><a href="{{ route('user.instrument.volume.analytics') }}">@lang('Volume Analysis')</a></li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Our Portfolios')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.oi-buildup') }}">@lang('OI Buildup')</a></li> 
                                    <li><a href="{{ route('user.oi-buildup-fresh') }}">@lang('OI Buildup Fresh')</a></li> 
                                    <li><a href="{{ route('user.oi-transitions') }}">@lang('OI Buildup Transitions')</a></li> 
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('AutoTrader')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.portfolio.top.gainers') }}">@lang('Trade Desk Signal')</a></li>
                                    <li><a href="{{ route('user.trade-positions-master') }}">@lang('Trade Position')</a></li>
                                    <li><a href="{{ route('user.order-books') }}">@lang('Order Book')</a></li>
                                    <li><a href="{{ route('user.portfolio.broker-details') }}">@lang('Broker Details')</a></li>
                                    <li><a href="{{ route('user.oms-config-master') }}">@lang('OMS Config')</a></li>
                                    <li><a href="{{ route('user.order-historical-master') }}">@lang('New Order Master')</a></li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Portfolios')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.stock.portfolios') }}">@lang('Stock Portfolio')</a></li>
                                    <li><a href="{{ route('user.thematic.portfolios') }}">@lang('Thematic Portfolios')</a></li>
                                    <li><a href="{{ route('user.global.stock.portfolio') }}">@lang('Global Stock Portfolio')</a></li>
                                    <li><a href="{{ route('user.fo.portfolio.hedging') }}">@lang('F&O Portfolio-Hedging')</a></li>
                                    <li><a href="{{ route('user.metals.portfolio') }}">@lang('Metals Portfolio (Gold & Silver)')</a></li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Paper Trading')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.paperTrading') }}">@lang('Paper Trading')</a></li>
                                </ul>
                            </li> --}}
                        

                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Portfolio Insights')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.portfolio.top.gainers') }}">@lang('Portfolio Top Gainers')</a></li>
                                    <li><a href="{{ route('user.portfolio.top.losers') }}">@lang('Portfolio Top Losers')</a></li>
                                </ul>
                            </li> --}}
                            {{-- <li class="menu_has_children">
                                <a href="#0">@lang('Support')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('ticket.open') }}">@lang('New Ticket')</a></li>
                                    <li><a href="{{ route('ticket.index') }}">@lang('My Tickets')</a></li>
                                </ul>
                            </li> --}}
                            <li class="menu_has_children">
                                <a href="#0">@lang('Account')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.profile.setting') }}"><i class="las la-user-circle"></i> @lang('Profile')</a></li>
                                    <li><a href="{{ route('user.change.password') }}"><i class="las la-lock"></i> @lang('Change Password')</a></li>
                                    <li><a href="{{ route('user.logout') }}"><i class="las la-sign-out-alt"></i> @lang('Logout')</a></li>
                                </ul>
                            </li>
                        </ul>
                        <div class="nav-right justify-content-xl-end">
                            <a href="{{ route('user.logout') }}" class="btn btn-md btn--base d-flex align-items-center">
                                <i class="las la-sign-out-alt fs--18px me-2"></i>
                                @lang('Logout')
                                </i>
                            </a>
                            @include('partials.language')
                        </div>
                    </div>
                </nav>
            </div>
        </div><!-- header__bottom end -->
    </header>
@endrole
@role('investor')
    <header class="header">
        <div class="header__bottom">
            <div class="container">
                <nav class="navbar navbar-expand-xl p-0 align-items-center">
                    <a class="site-logo site-title" href="{{ route('home') }}">
                        <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}" alt="logo">
                    </a>
                    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                        aria-expanded="false" aria-label="Toggle navigation">
                        <span class="menu-toggle"></span>
                    </button>
                    <div class="collapse navbar-collapse mt-lg-0 mt-3" id="navbarSupportedContent">
                        <ul class="navbar-nav main-menu ms-auto">
                            <li><a href="{{ route('user.home') }}">@lang('Dashboard')</a></li>
                            <li><a href="{{ route('user.order-books') }}">@lang('Orders')</a></li>
                            <li><a href="{{ route('user.trade-positions') }}">@lang('Positions')</a></li>
                            <li><a href="#">@lang('Funds')</a></li>
                            <li class="menu_has_children">
                                <a href="#0">@lang('Portfolios')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.stock.portfolios') }}">@lang('Stock Portfolio')</a></li>
                                    <li><a href="{{ route('user.thematic.portfolios') }}">@lang('Thematic Portfolios')</a></li>
                                    <li><a href="{{ route('user.global.stock.portfolio') }}">@lang('Global Stock Portfolio')</a></li>
                                    <li><a href="{{ route('user.fo.portfolio.hedging') }}">@lang('F&O Portfolio-Hedging')</a></li>
                                    <li><a href="{{ route('user.metals.portfolio') }}">@lang('Metals Portfolio (Gold & Silver)')</a></li>
                                </ul>
                            </li>
                            <li class="menu_has_children">
                                <a href="#0">@lang('Account')</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('user.profile.setting') }}">@lang('Profile')</a></li>
                                    <li><a href="{{ route('user.change.password') }}">@lang('Change Password')</a></li>
                                    <li><a href="{{ route('user.transactions') }}">@lang('Stock Transactions')</a></li>
                                    <li><a href="{{ route('user.trade-book') }}">@lang('Trade Book')</a></li>
                                    <li><a href="{{ route('user.pl-reports') }}">@lang('PL Reports')</a></li>
                                    <li><a href="{{ route('user.logout') }}">@lang('Logout')</a></li>
                                </ul>
                            </li>
                        </ul>
                        <div class="nav-right justify-content-xl-end">
                            <a href="{{ route('user.logout') }}" class="btn btn-md btn--base d-flex align-items-center">
                                <i class="las la-sign-out-alt fs--18px me-2"></i>
                                @lang('Logout')
                                </i>
                            </a>
                            @include('partials.language')
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>
@endrole
<!-- header-section end  -->