<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title> {{ $general->siteName(__(isset($customPageTitle) ? $customPageTitle : $pageTitle)) }}</title>

    @include('partials.seo')

    <!-- bootstrap 5  -->
    <link rel="stylesheet" href="{{asset('assets/global/css/bootstrap.min.css')}}">
    <!-- fontawesome 5  -->
    <link rel="stylesheet" href="{{asset('assets/global/css/all.min.css')}}">
    <!-- lineawesome font -->
    <link rel="stylesheet" href="{{asset('assets/global/css/line-awesome.min.css')}}">
    <!-- main css -->
    <link rel="stylesheet" href="{{asset($activeTemplateTrue.'css/main.css')}}">

    <link rel="stylesheet" href="{{asset($activeTemplateTrue.'css/custom.css')}}">

    @stack('style-lib')

    @stack('style')

    <link rel="stylesheet" href="{{ asset($activeTemplateTrue. 'css/color.php?color='.$general->base_color.'&secondColor='.$general->secondary_color) }}">
</head>
<style>
    /* ── Quantsapp-style Header ── */
.qapp-header {
    background-color: #000;
    border-bottom: 1px solid #1a1a1a;
    position: sticky;
    top: 0;
    z-index: 1000;
}

.qapp-header .header__bottom {
    padding: 12px 0;
}

/* Logo */
.qapp-header .site-logo img {
    height: 42px;
    width: auto;
}

/* Pill-shaped CTA buttons (Download App / Option App style) */
.qapp-pill-btn {
    display: inline-block;
    padding: 7px 18px;
    background: #fff;
    border: none;
    border-radius: 999px;
    color: #000;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.18s ease, color 0.18s ease;
}

.qapp-pill-btn:hover {
    background-color: #F5A623;
    color: #000;
}

/* Uppercase nav links */
.qapp-nav-links {
    display: flex;
    align-items: center;
    gap: 4px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.qapp-nav-links li a {
    display: block;
    padding: 6px 14px;
    color: #ccc;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-decoration: none;
    transition: color 0.15s ease;
}

.qapp-nav-links li a:hover,
.qapp-nav-links li a.active {
    color: #fff;
}

/* User icon button */
.qapp-user-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    color: #ccc;
    font-size: 22px;
    text-decoration: none;
    transition: color 0.15s ease;
}

.qapp-user-icon:hover {
    color: #fff;
}

/* Mobile toggler */
.qapp-toggler {
    border: 1.5px solid #444;
    padding: 6px 10px;
    border-radius: 6px;
}

.qapp-toggler .menu-toggle,
.qapp-toggler .menu-toggle::before,
.qapp-toggler .menu-toggle::after {
    background-color: #fff;
}

/* Mobile nav collapsed styles */
@media (max-width: 1199px) {
    .qapp-nav-links {
        flex-direction: column;
        align-items: flex-start;
        padding: 8px 0;
    }

    .qapp-nav-links li a {
        padding: 10px 4px;
        font-size: 12px;
    }

    .qapp-cta-btns {
        display: flex !important;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px 0 4px;
    }

    .nav-right {
        padding: 12px 0;
        border-top: 1px solid #1a1a1a;
        margin-top: 8px;
    }
}
</style>
<body>

    @stack('fbComment')
    <progress max="100" value="0" class="page-scroll-bar"></progress>

    <!-- scroll-to-top start -->
    <div class="scroll-to-top">
        <span class="scroll-icon">
            <i class="las la-arrow-up"></i>
        </span>
    </div>
      <!-- scroll-to-top end -->

      <!-- preloader start -->
    <div class="preloader">
        <div class="preloader__inner">
            <div class="preloader__box">
                <span class="line-1"></span>
                <span class="line-2"></span>
                <span class="line-3"></span>
            </div>
            <h4 class="preloader__sitename text--base">{{ $general->site_name }}</h4>
        </div>
    </div>
      <!-- preloader end -->

    @yield('panel')

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="{{asset('assets/global/js/jquery-3.6.0.min.js')}}"></script>
    <script src="{{asset('assets/global/js/bootstrap.bundle.min.js')}}"></script>

    @stack('script-lib')

    <!-- wow js  -->
    <script src="{{asset($activeTemplateTrue.'js/lib/wow.min.js')}}"></script>

    <script src="{{asset($activeTemplateTrue.'js/gsap.min.js')}}"></script>
    <script src="{{asset($activeTemplateTrue.'js/ScrollTrigger.js')}}"></script>

    <script src="{{asset($activeTemplateTrue.'js/app.js')}}"></script>

    @include('partials.plugins')

    @include('partials.notify')

    @stack('script')

    <script>
        (function ($) {
            "use strict";

            $('.showFilterBtn').on('click',function(){
                $('.responsive-filter-card').slideToggle();
            });

            var inputElements = $('input, select, [type=text], [type=password], textarea');
            $.each(inputElements, function (index, element) {
                element = $(element);
                element.closest('.form-group').find('label').attr('for',element.attr('name'));
                element.attr('id',element.attr('name'))
            });

            $('.policy').on('click',function(){
                $.get('{{route('cookie.accept')}}', function(response){
                    $('.cookies-card').addClass('d-none');
                });
            });

            setTimeout(function(){
                $('.cookies-card').removeClass('hide')
            },2000);

            $.each($('input, select, textarea'), function (i, element) {
                var elementType = $(element);
                if(elementType.attr('type') != 'checkbox'){
                    if (element.hasAttribute('required')) {
                        $(element).closest('.form-group').find('label').addClass('required');
                    }
                }
            });

            var darkMode = @json($general->dark_template);

            if (darkMode != 1) {
                document.body.classList.add('lightmode');
                localStorage.setItem('darkMode', 'enabled');
            }else{
                document.body.classList.remove('lightmode');
                localStorage.setItem('darkMode', null);
            }

            var currentRoute = '{{ url()->current() }}';

            $('.main-menu li a[href="'+ currentRoute +'"]')
            .closest('li').addClass('active')
            .closest('.menu_has_children').first().addClass('active');

        })(jQuery);
    </script>

    @include('partials.signal_lab')

</body>

</html>
