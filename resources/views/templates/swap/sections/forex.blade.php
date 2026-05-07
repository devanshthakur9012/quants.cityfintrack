@php
    $forex = getContent('forex.content', true);
@endphp

<!-- Forex Signals Section -->
<div class="forex-signals pt-100 pb-100">
    <div class="container">
        <div class="row gy-4 gy-sm-5 gx-lg-5 gx-xxl-4 justify-content-between flex-wrap-reverse">
            <div class="col-lg-5">
                <div class="section-thumb position-relative mb-sm-4">
                    <img src="{{ getImage('assets/images/frontend/forex/' .@$forex->data_values->image, '650x435') }}" alt="img" class="mw-100">
                    <div class="experience-card">
                        <h5 class="text-white mb-2">{{ __(@$forex->data_values->card_title) }}</h5>
                        <p class="text-white fs--14px mb-3">{{ __(@$forex->data_values->card_heading) }}</p>
                        <span class="text--base fw-bold">{{ __(@$forex->data_values->card_text) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 col-xl-6">
                <div class="section-header mb-0">
                    <h3 class="section-header__title style--two">{{ __(@$forex->data_values->heading) }}</h3>
                    <p class="fs--20px">{{ __(@$forex->data_values->subheading) }}</p>
                    <p>{{ __(@$forex->data_values->description) }}</p>
                </div>
            </div>
        </div>
        <div class="row gy-5 justify-content-between pt-60">
            <div class="col-lg-6">
                <div class="section-header mb-0">
                    <h3 class="section-header__title style--two">{{ __(@$forex->data_values->second_heading) }}</h3>
                    <p class="fs--20px">{{ __(@$forex->data_values->second_subheading) }}</p>
                    <p>{{ __(@$forex->data_values->second_description) }}</p>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="section-thumb position-relative">
                    <img src="{{ getImage('assets/images/frontend/forex/' .@$forex->data_values->second_image, '650x475') }}" alt="img" class="mw-100">
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Forex Signals Section -->