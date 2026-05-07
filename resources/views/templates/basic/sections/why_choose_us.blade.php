@php
    $choose = getContent('why_choose_us.content', true);
    $chooses = getContent('why_choose_us.element', orderById: true);
@endphp

<!-- why choose section start -->
<section class="pt-100 pb-100 dark--overlay-two bg_img  bg-attachment-fixed" style="background-image: url('{{ getImage('assets/images/frontend/why_choose_us/' .@$choose->data_values->background_image, '1920x1080') }}');">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="section-header text-center wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
                    <div class="section-subtitle">{{ __(@$choose->data_values->heading) }}</div>
                    <h2 class="section-title text-white">
                        {{ __(@$choose->data_values->subheading) }}
                    </h2>
                </div>
            </div>
        </div><!-- row end -->
        <div class="row gy-4 justify-content-center">
            @foreach ($chooses as $singleChoose)
                <div class="col-lg-4 col-sm-6 wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
                    <div class="choose-card">
                        <div class="choose-card__icon text-white">
                            @php
                                echo @$singleChoose->data_values->icon;
                            @endphp
                        </div>
                        <h3 class="choose-card__title text-white">
                            {{ __(@$singleChoose->data_values->title) }}
                        </h3>
                        <div class="choose-card__content">
                            <p>
                                {{ __(@$singleChoose->data_values->description) }}
                            </p>
                        </div>
                    </div><!-- choose-card end -->
                </div>
            @endforeach
        </div>
    </div>
</section>
<!-- why choose section end -->
