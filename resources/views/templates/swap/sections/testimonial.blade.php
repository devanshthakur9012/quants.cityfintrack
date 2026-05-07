@php
    $testimonial = getContent('testimonial.content', true);
    $testimonials = getContent('testimonial.element', orderById:true);
@endphp

<!-- Testimonial Section -->
<div class="testimonial-section position-relative bg_img pt-100 pb-100" style="background: url('{{ getImage('assets/images/frontend/testimonial/' .@$testimonial->data_values->image, '1920x730') }}');">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <div class="section-header mb-0">
                    <h3 class="section-header__title style--two text-white">{{ __(@$testimonial->data_values->heading) }}</h3>
                    <p class="fs--18px text-white">{{ __(@$testimonial->data_values->subheading) }}</p>
                </div>
            </div>
            <div class="col-lg-8 ps-lg-4 ps-xl-5 pt-5 pt-lg-0">
                <div class="testimonial-slider">
                    @foreach($testimonials as $testimonial)
                        <div class="single-slide">
                            <div class="testimonial-item">
                                <span class="testimonial-item__quote"><i class="fas fa-quote-right"></i></span>
                                <div class="testimonial-item__header">
                                    <div class="testimonial-item__header-thumb">
                                        <img src="{{ getImage('assets/images/frontend/testimonial/' .@$testimonial->data_values->image, '1000x665') }}" alt="thumb" class="h-100 w-100">
                                    </div>
                                    <div class="testimonial-item__header-content">
                                        <h5 class="name">{{ __(@$testimonial->data_values->name) }}</h5>
                                        <p class="designation">{{ __(@$testimonial->data_values->designation) }}</p>
                                    </div>
                                </div>
                                <div class="testimonial-item__body">
                                    <p class="testimonial-item__body-text">{{ __(@$testimonial->data_values->quotation) }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Testimonial Section -->

@push('script-lib')
    <!-- slick  slider js -->
    <script src="{{asset('assets/global/js/lib/slick.min.js')}}"></script>
@endpush

@push('style-lib')
    <!-- slick  slider js -->
    <link rel="stylesheet" href="{{asset('assets/global/css/lib/slick.css')}}">
@endpush

@push('script')
<script>
    (function ($) {
        "use strict";
        $(".testimonial-slider").slick({
            speed: 3000,
            autoplay: true,
            autoplaySpeed: 2000,
            cssEase: "linear",
            slidesToShow: 2,
            slidesToScroll: 1,
            infinite: true,
            dots: false,
            arrows: false,
            // mobileFirst: true,
            responsive: [
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 1,
                    },
                },
            ],
        });
    })(jQuery);
</script>
@endpush
