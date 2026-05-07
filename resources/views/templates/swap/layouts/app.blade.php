<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> {{ $general->siteName(__(isset($customPageTitle) ? $customPageTitle : $pageTitle)) }}</title>

    @include('partials.seo')
    
    <!-- bootstrap 5  -->
    <link rel="stylesheet" href="{{asset($activeTemplateTrue.'css/bootstrap.min.css')}}">
    <!-- fontawesome 5  -->
    <link rel="stylesheet" href="{{asset('assets/global/css/all.min.css')}}">
    <!-- lineawesome font -->
    <link rel="stylesheet" href="{{asset('assets/global/css/line-awesome.min.css')}}">
    <!-- animate css -->
    <link rel="stylesheet" href="{{asset($activeTemplateTrue.'css/lib/animate.css')}}">
    <!-- main css -->
    <link rel="stylesheet" href="{{asset($activeTemplateTrue.'css/main.css')}}">

    <link rel="stylesheet" href="{{asset($activeTemplateTrue.'css/custom.css')}}">

    @stack('style-lib')
 
    @stack('style')

    <link rel="stylesheet" href="{{ asset($activeTemplateTrue. 'css/color.php?color='.$general->base_color.'&secondColor='.$general->secondary_color) }}">
</head>

<body class="swap">
    {{-- Preloader --}}
    <div class="preloader">
        <div class="loader-p"></div>
    </div>
    {{-- Preloader --}}
    @stack('fbComment')

    <!-- Overlay -->
    <div class="overlay"></div>
    <a href="javascript::void(0)" class="scrollToTop"><i class="las la-chevron-up"></i></a>

    @yield('panel')

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="{{asset('assets/global/js/jquery-3.6.0.min.js')}}"></script>
    <script src="{{asset('assets/global/js/bootstrap.bundle.min.js')}}"></script>

    @stack('script-lib')

    <script src="{{asset($activeTemplateTrue.'js/main.js')}}"></script>

    @include('partials.plugins')

    @include('partials.notify')

    @stack('script')

    <script>
        (function ($) {
            "use strict";

            $('.policy').on('click',function(){
                $.get('{{route('cookie.accept')}}', function(response){
                    $('.cookies-card').addClass('d-none');
                });
            });

            setTimeout(function(){
                $('.cookies-card').removeClass('hide')
            },2000);

            if($('#bannerVideo').length){ 
                var vid = document.getElementById("bannerVideo");
                vid.playbackRate = 0.6;
            }

            var inputElements = $('input, select, [type=text], [type=password], textarea');
            $.each(inputElements, function (index, element) {
                element = $(element);
                element.closest('.form-group').find('label').attr('for',element.attr('name'));
                element.attr('id',element.attr('name'))
            });

            $('.showFilterBtn').on('click',function(){
                $('.responsive-filter-card').slideToggle();
            });

            $.each($('input, select, textarea'), function (i, element) {
                if (element.hasAttribute('required')) {
                    $(element).closest('.form-group').find('label').addClass('required');
                }
            });

            var currentRoute = '{{ url()->current() }}'; 
            $('.menu li a[href="'+ currentRoute +'"]').addClass('active')
            .closest('.has-sub-menu').find('a:first').addClass('active');

        })(jQuery); 
    </script>

    @include('partials.signal_lab')

    <script>
        (function ($) {
            "use strict";
            $('.notice_notify').addClass('bg--dark text-white');
        })(jQuery); 
    </script>

</body>

</html>
