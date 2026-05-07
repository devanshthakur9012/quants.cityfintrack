@php
    $featureAbout = getContent('feature_about.content', true);
    $featureAbouts = getContent('feature_about.element', orderById:true);
@endphp

<!-- Feature About Section -->
<div class="row gx-0">
    <div class="col-lg-6">
        <div class="section-thumb about-thumb h-100">
            <img src="{{ getImage('assets/images/frontend/feature_about/' .@$featureAbout->data_values->image, '955x765') }}" class="mw-100 h-100">
        </div>
    </div>
    <div class="col-lg-6">
        <div class="section-header mb-0 pt-80 pb-80 px-sm-5 px-3 about-content">
            <h2 class="section-header__title text-white pb-50 mb-0">{{ __(@$featureAbout->data_values->heading) }}</h2>
            <ul class="d-flex flex-wrap flex-column gap-4">

                @foreach($featureAbouts as $featureAbout)
                    <li class="d-flex flex-wrap about-list">
                        <div class="ico-thumb">@php echo @$featureAbout->data_values->icon; @endphp</div>
                        <div class="content">
                            <h4 class="title text-white fw-normal">{{ __(@$featureAbout->data_values->title) }}</h4>
                            <p>{{ __(@$featureAbout->data_values->text) }}</p>
                        </div>
                    </li>
                @endforeach

            </ul>
        </div>
    </div>
</div>
<!-- Feature About Section -->
