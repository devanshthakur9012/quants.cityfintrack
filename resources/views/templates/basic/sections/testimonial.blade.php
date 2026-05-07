@php
    $testimonial = getContent('testimonial.content', true);
    $testimonials = getContent('testimonial.element', orderById:true);
@endphp

<!-- testimonial section start -->
<section class="pt-100 pb-100 dark--overlay-two bg_img  bg-attachment-fixed overflow-hidden"
    style="background-image: url('{{ getImage('assets/images/frontend/testimonial/' .@$testimonial->data_values->background_image, '1920x1280') }}');">
    <div class="container">
        <div class="row justify-content-center gy-5">
            <div class="col-xl-4 col-lg-8 text-xl-start text-center wow fadeInUp" data-wow-duration="0.5"
                data-wow-delay="0.3s">
                <h2 class="section-title text-white">{{ __(@$testimonial->data_values->heading) }}</h2>
                <p class="mt-3 text-white-75">{{ __(@$testimonial->data_values->subheading) }}</p>
            </div>
            <div class="col-xl-8 ps-xl-5 wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
                <div class="testimonial-slider">
                
                    @foreach ($testimonials as $singleTestimonial)
                        <div class="single-slide">
                            <div class="testimonial-card">
                                <div class="testimonial-card__thumb">
                                    <img src="{{ getImage('assets/images/frontend/testimonial/' . @$singleTestimonial->data_values->image, '65x80') }}" alt="image">
                                </div>
                                <div class="testimonial-card__content">
                                    <p class="testimonial-card__description">
                                        {{ __(@$singleTestimonial->data_values->quotation) }}
                                    </p>
                                    <h6 class="mt-3 text-white">{{ __($singleTestimonial->data_values->name) }}</h6>
                                    <div class="ratings">
                                        @for ($i = 0; $i < @$singleTestimonial->data_values->rating; $i++)
                                            <i class="las la-star"></i>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div><!-- single-slide end -->
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
<!-- testimonial section end -->

@push('style-lib')
    <!-- slick  slider js -->
    <link rel="stylesheet" href="{{asset('assets/global/css/lib/slick.css')}}">
@endpush

@push('script-lib')
    <!-- slick slider css -->
    <script src="{{ asset('assets/global/js/lib/slick.min.js') }}"></script>
@endpush

@push('script')
    <script>
    (function ($) {
        "use strict";
        // testimonial-slider js
        $('.testimonial-slider').slick({
            infinite: true,
            slidesToShow: 2,
            slidesToScroll: 1,
            dots: false,
            arrows: false,
            autoplay: true,
            cssEase: 'cubic-bezier(0.645, 0.045, 0.355, 1.000)',
            speed: 1000,
            autoplaySpeed: 1000,
            responsive: [
            {
                breakpoint: 1200,
                settings: {
                slidesToShow: 2,
                }
            },
            {
                breakpoint: 992,
                settings: {
                slidesToShow: 1,
                }
            },
            {
                breakpoint: 576,
                settings: {
                slidesToShow: 1,
                }
            }
            ]
        });
    })(jQuery);
    </script>
@endpush
