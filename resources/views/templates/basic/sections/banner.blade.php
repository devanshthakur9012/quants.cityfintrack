
@php $banner = getContent('banner.content', true); @endphp
<!-- hero section start -->
<section class="hero bg_img" style="background-image: url('{{ getImage('assets/images/frontend/banner/' .@$banner->data_values->image, '1920x1080') }}');">
    <div class="hero__radar"> 
        <div class="hero__radar-content">
            <div class="circle"><img src="{{asset($activeTemplateTrue.'images/elements/hero/circle.png')}}" alt="image"></div>
            <div class="radar"><img src="{{asset($activeTemplateTrue.'images/elements/hero/radar.png')}}" alt="image"></div>
            <span class="dot-1"></span>
            <span class="dot-2"></span>
            <span class="dot-3"></span>
            <span class="dot-4"></span>
            <span class="dot-5"></span>
            <span class="dot-6"></span>
        </div>
    </div>
    <div class="container">
        <div class="row">
            <div class="col-lg-6 text-lg-start text-center"> 
                <div class="hero__top-title wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
                    {{ __(@$banner->data_values->title) }}
                </div>
                <h2 class="hero__title text-white wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
                    {{ __(@$banner->data_values->heading) }}
                </h2>
                <p class="hero__description text-white wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.7s">
                    {{ __(@$banner->data_values->subheading) }}
                </p>
                <a href="{{ @$banner->data_values->button_url }}" class="btn btn--base mt-4 wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.7s">
                    {{ __(@$banner->data_values->button_text) }}
                </a>
            </div>
        </div>
    </div>
</section>
<!-- hero section end -->
