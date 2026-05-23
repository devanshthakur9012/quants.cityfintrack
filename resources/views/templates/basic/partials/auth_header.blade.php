<!-- header-section start  -->
<header class="header qapp-header">
    <div class="header__bottom">
        <div class="container">
            <nav class="navbar navbar-expand-xl p-0 align-items-center">
                <a class="site-logo site-title" href="{{ route('home') }}">
                    <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}" alt="logo">
                </a>
                <button class="navbar-toggler ms-auto qapp-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="menu-toggle"></span>
                </button>

                <div class="collapse navbar-collapse mt-lg-0 mt-3" id="navbarSupportedContent">

                    <div class="qapp-cta-btns ms-auto me-3 d-none d-xl-flex align-items-center gap-2">
                    </div>

                    {{-- Uppercase Nav Links --}}
                    <ul class="navbar-nav qapp-nav-links">
                        <li><a href="{{ route('home') }}">@lang('Home')</a></li>
                        <li><a href="{{ route('webinars.index') }}">@lang('Webinars')</a></li>
                        <li><a href="{{ route('courses') }}">@lang('Courses')</a></li>
                        {{-- <li><a href="{{ route('book.demo') }}">@lang('Book a Demo')</a></li> --}}
                        <li><a href="{{ route('events.index') }}">@lang('Event')</a></li>
                        <li><a href="{{ route('media') }}">@lang('Media')</a></li>
                        <li><a href="{{ route('about') }}">@lang('About')</a></li>
                    </ul>

                    {{-- Auth + Language --}}
                    <div class="nav-right d-flex align-items-center p-0">
                        @auth
                            <a href="{{ route('user.dashboard') }}" class="qapp-user-icon" title="Dashboard">
                                <i class="las la-home"></i>
                            </a>
                        @else
                            <a href="{{ route('user.login') }}" class="qapp-user-icon" title="Login">
                                <i class="las la-user"></i>
                            </a>
                        @endauth
                        @include('partials.language')
                    </div>
                </div>
            </nav>
        </div>
    </div>
</header>
<!-- header-section end  -->