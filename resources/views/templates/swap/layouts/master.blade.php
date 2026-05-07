@extends($activeTemplate.'layouts.app')
@section('panel')

@include($activeTemplate.'partials.auth_header')

<div class="main-wrapper">

    @include($activeTemplate.'partials.bread_crumb')

    @yield('content') 

</div><!-- main-wrapper end -->

@include($activeTemplate.'partials.footer')

@endsection

@push('script-lib')
    <script src="{{ asset('assets/global/js/jquery.validate.js') }}"></script>
@endpush

@push('script')
<script>
    (function ($) {
        "use strict";
        
        $('form').on('submit', function () {
            if ($(this).valid() && !$(this).hasClass('disabled-false')) {
                $(':submit', this).attr('disabled', 'disabled');
            }
        });

    })(jQuery);
</script>
@endpush