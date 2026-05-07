@php
    $overview = getContent('overview.content', true);
    $overviews = getContent('overview.element', orderById:true);
@endphp

<!-- overview section start -->
<section class="pt-100 pb-100 section--bg">
<div class="container">
    <div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="section-header text-center wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
        <div class="section-subtitle">{{ __(@$overview->data_values->heading) }}</div>
        <h2 class="section-title">{{ __(@$overview->data_values->subheading) }}</h2>
        </div>
    </div>
    </div><!-- row end -->
    <div class="row gy-5 justify-content-center">
        @foreach($overviews as $singleOverView)
            <div class="col-lg-3 col-sm-6 overview-item wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
                <div class="overview-card">
                <div class="overview-card__icon">
                    @php
                        echo $singleOverView->data_values->icon;
                    @endphp
                </div>
                <div class="overview-card__content">
                    <h4 class="overview-card__number text-white">
                        {{ __(@$singleOverView->data_values->title) }}
                    </h4>
                    <p class="overview-card__caption">
                        {{ __(@$singleOverView->data_values->text) }}
                    </p>
                </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
</section>
<!-- overview section end -->
