<!-- header-section start  -->
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
                    <li>
                        <a href="{{ route('home') }}">@lang('Home')</a>
                    </li>
                    @php
                        $pages = App\Models\Page::where('tempname',$activeTemplate)->where('is_default',0)->get();
                    @endphp
                        @foreach($pages as $k => $data)
                        <li>
                            <a href="{{route('pages',[$data->slug])}}">
                                {{__($data->name)}}
                            </a>
                        </li> 
                    @endforeach
                    <li>
                        <a href="{{ route('packages') }}">@lang('Products')</a>
                    </li>
                    <li>
                        <a href="{{ route('blogs') }}">@lang('Blogs')</a>
                    </li>
                    <li>
                        <a href="{{ route('contact') }}">@lang('Contact')</a>
                    </li>
                    <li class="ms-xl-4 ms-lg-2 d-flex align-items-center justify-content-between mt-lg-0 mt-2">
                        @auth
                            <a href="{{ route('user.home') }}" class="btn btn--base btn--sm rounded-5 text-white">@lang('Dashboard')</a>
                        @else
                            <a href="{{ route('user.login') }}" class="btn btn--base btn--sm rounded-5 text-white">@lang('Login')</a>
                        @endauth
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
<!-- header-section end  -->
