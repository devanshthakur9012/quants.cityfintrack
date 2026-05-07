@php
    $feature = getContent('feature.content', true);
    $features = getContent('feature.element', orderById:true);
@endphp

<!-- Feature Section -->
<div class="feature-section pt-100 pb-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-header text-center">
                    <h2 class="section-header__title">{{ __(@$feature->data_values->heading) }}</h2>
                    <p class="section-header__text">{{ __(@$feature->data_values->subheading) }}</p>
                </div>
            </div>
        </div>
        <div class="feature-wrapper">
            <div class="feature-thumb d-lg-block d-none">
                <img src="{{ getImage('assets/images/frontend/feature/' .@$feature->data_values->image, '840x1565') }}" alt="thumb" class="mw-100">
            </div>
            <div class="item-wrapper d-flex flex-wrap justify-content-between w-100">
                @foreach($features as $feature)
                    <div class="single-item">
                        <div class="feature-item">
                            <div class="feature-item__icon">
                                <img src="{{ getImage('assets/images/frontend/feature/' .@$feature->data_values->image, '128x128') }}" alt="icon" class="mw-100">
                            </div>
                            <div class="feature-item__content">
                                <h6 class="feature-item__content-title">{{ __(@$feature->data_values->text) }}</h6>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
<!-- Feature Section -->
