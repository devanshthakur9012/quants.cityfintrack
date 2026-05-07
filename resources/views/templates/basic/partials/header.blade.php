<!-- header-section start  -->
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
                        <li><a href="{{ route('home') }}">@lang('Home')</a></li>
                        @php
                            $pages = App\Models\Page::where('tempname', $activeTemplate)
                                ->where('is_default', 0)
                                ->get();
                        @endphp
                        @foreach ($pages as $k => $data)
                            <li>
                                <a href="{{ route('pages', [$data->slug]) }}">
                                    {{ __($data->name) }}
                                </a>
                            </li>
                        @endforeach
                        <li><a href="{{ route('packages') }}">@lang('Products')</a></li>
                        <li><a href="{{ route('blogs') }}">@lang('Blogs')</a></li>
                        <li><a href="{{ route('contact') }}">@lang('Contact')</a></li>
                    </ul>
                    <div class="nav-right justify-content-xl-end">
                        @auth
                            <a href="{{ route('user.home') }}" class="btn btn-md btn--base d-flex align-items-center">
                                <i class="las la-home fs--18px me-2"></i>
                                @lang('Dashboard')
                            </a>
                        @else
                            <a href="{{ route('user.login') }}" class="btn btn-md btn--base d-flex align-items-center">
                                <i class="las la-user fs--18px me-2"></i>
                                @lang('Login')
                            </a>
                        @endauth
                        @include('partials.language')
                    </div>
                </div>
            </nav>
        </div>
    </div><!-- header__bottom end -->
</header>
<!-- header-section end  -->
