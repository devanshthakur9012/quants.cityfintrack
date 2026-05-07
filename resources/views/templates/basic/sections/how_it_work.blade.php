@php
    $work = getContent('how_it_work.content', true);
    $works = getContent('how_it_work.element', orderById:true);
@endphp

<!-- how it work start -->
<section class="pt-100 pb-100 section--bg">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-6 wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
          <div class="section-header text-center">
            <div class="section-subtitle">
                {{ __(@$work->data_values->heading) }}
            </div>
            <h2 class="section-title">
                {{ __(@$work->data_values->subheading) }}
            </h2>
          </div>
        </div>
      </div><!-- row end -->
      <div class="row justify-content-center gy-4">
        @foreach($works as $singleWork)
            <div class="col-lg-4 col-sm-6 work-item wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
                <div class="work-card">
                    <div class="work-card__thumb">
                        <img src="{{ getImage('assets/images/frontend/how_it_work/' .@$singleWork->data_values->image, '200x180') }}" alt="image">
                    </div>
                    <div class="work-card__content">
                        <h5 class="work-card__title">{{ __($singleWork->data_values->title) }}</h5>
                    </div>
                </div><!-- work-card end -->
            </div>
        @endforeach
      </div>
    </div>
  </section>
  <!-- how it work end -->
