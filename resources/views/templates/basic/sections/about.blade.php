@php
    $about = getContent('about.content', true);
    $abouts = getContent('about.element', orderById:true);
@endphp

<!-- about section start -->
<section class="pt-100 pb-100">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xxl-6 col-xl-8 col-lg-9">
          <div class="section-header text-center wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
            <h2 class="section-title">
                {{ __(@$about->data_values->heading) }}
            </h2>
          </div>
        </div>
      </div><!-- row end -->
      <div class="row gy-4 align-items-center justify-content-between">
        <div class="col-lg-5 text-lg-start text-center">
          <div class="about-thumb wow fadeInLeft" data-wow-duration="0.5" data-wow-delay="0.5s">
            <img src="{{ getImage('assets/images/frontend/about/' .@$about->data_values->image, '525x270') }}" alt="image">
          </div>
        </div>
        <div class="col-lg-6 wow fadeInRight text-lg-start text-center" data-wow-duration="0.5" data-wow-delay="0.5s">
          <h2 class="mb-2">
            {{ __(@$about->data_values->subheading) }}
          </h2>
          <p>
            {{ __(@$about->data_values->description) }}
          </p>
          <ul class="cmn-list about-list mt-4">
            @foreach($abouts as $singleData)
                <li>{{ __(@$singleData->data_values->feature) }}</li>
            @endforeach
          </ul>
          <a href="{{ @$about->data_values->button_url }}" class="btn btn--base style--two mt-5">{{ __(@$about->data_values->button_text) }}
            <i class="las la-long-arrow-alt-right me-0 ms-2"></i>
          </a>
        </div>
      </div>
    </div>
</section>
<!-- about section end -->
