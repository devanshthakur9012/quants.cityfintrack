<div class="header">
    <div class="container">
        <div class="header-bottom">
            <div class="header-bottom-area align-items-center">
                <div class="logo">
                  <a href="{{ route('home') }}">
                    <img src="{{getImage(getFilePath('logoIcon') .'/logo.png')}}" alt="logo">
                  </a>
                </div>
                <ul class="menu ms-auto">
                    <li><a href="{{ route('user.home') }}">@lang('Dashboard')</a></li>
                    <li>
                        <a href="javascript:void(0)">@lang('Deposit')</a>
                        <ul class="sub-menu">
                            <li><a href="{{ route('user.deposit.index') }}">@lang('Deposit Now')</a></li>
                            <li><a href="{{ route('user.deposit.history') }}">@lang('Deposit History')</a></li>
                        </ul>
                    </li>
                    <li><a href="{{ route('packages') }}">@lang('Products')</a></li>
                    <li><a href="{{ route('user.signals') }}">@lang('Signals')</a></li>
                    <li><a href="{{ route('user.referrals') }}">@lang('Referrals')</a></li>
                    <li>
                        <a href="javascript:void(0)">@lang('Support')</a>
                        <ul class="sub-menu">
                            <li><a href="{{ route('ticket.open') }}">@lang('New Ticket')</a></li>
                            <li><a href="{{ route('ticket.index') }}">@lang('My Tickets')</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="javascript:void(0)">@lang('Account')</a>
                        <ul class="sub-menu">
                            <li><a href="{{ route('user.profile.setting') }}">@lang('Profile')</a></li>
                            <li><a href="{{ route('user.twofactor') }}">@lang('Two Factor')</a></li>
                            <li><a href="{{ route('user.change.password') }}">@lang('Change Password')</a></li>
                            <li><a href="{{ route('user.transactions') }}">@lang('Transactions')</a></li>
                            <li><a href="{{ route('user.logout') }}">@lang('Logout')</a></li>
                        </ul>
                    </li>
                    <li class="ms-xl-4 ms-lg-2 d-flex align-items-center justify-content-between mt-lg-0 mt-2">
                        <a href="{{ route('user.logout') }}" class="btn btn--base btn--sm rounded-5 text-white">@lang('Logout')</a>
                        @include('partials.language')
                    </li>
                </ul>
                <div class="header-trigger-wrapper d-flex d-lg-none align-items-center">
                    <div class="header-trigger">
                        <span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
