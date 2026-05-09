
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

                    {{-- Pill CTA Buttons (like Quantsapp's DOWNLOAD APP / OPTION APP / WEALTH APP) --}}
                    <div class="qapp-cta-btns ms-auto me-3 d-none d-xl-flex align-items-center gap-2">
                        <a href="#" class="qapp-pill-btn">@lang('Download APP')</a>
                        <a href="#" class="qapp-pill-btn">@lang('OPTION APP')</a>
                        <a href="#" class="qapp-pill-btn">@lang('WEALTH APP')</a>
                    </div>

                    {{-- Uppercase Nav Links --}}
                    <ul class="navbar-nav qapp-nav-links">
                        <li><a href="{{ route('home') }}">@lang('HOME')</a></li>
                        
                        <li class="menu_has_children">
                            <a href="#0">@lang('LEARN')</a>
                            <ul class="sub-menu">
                                <li><a href="{{ route('webinars') }}">@lang('Webinars')</a></li>
                                <li><a href="{{ route('courses') }}">@lang('Courses')</a></li>
                                <li><a href="{{ route('user.login') }}">@lang('Book a Demo')</a></li>
                                <li><a href="{{ route('events') }}">@lang('Event')</a></li>
                                <li><a href="{{ route('optionsymposium') }}">@lang('Option Symposium 7.0')</a></li>
                            </ul>
                        </li>
                        <li><a href="{{ route('video.library') }}">@lang('VIDEO LIBRARY')</a></li>
                        <li><a href="{{ route('user.login') }}">@lang('MEDIA')</a></li>
                        <li><a href="{{ route('about') }}">@lang('ABOUT')</a></li>
                        {{-- @php
                            $pages = App\Models\Page::where('tempname', $activeTemplate)
                                ->where('is_default', 0)
                                ->get();
                        @endphp
                        @foreach ($pages as $k => $data)
                            <li>
                                <a href="{{ route('pages', [$data->slug]) }}">{{ __($data->name) }}</a>
                            </li>
                        @endforeach --}}
                    </ul>

                    {{-- Auth + Language --}}
                    <div class="nav-right d-flex align-items-center p-0">
                        @auth
                            <a href="{{ route('user.home') }}" class="qapp-user-icon" title="Dashboard">
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