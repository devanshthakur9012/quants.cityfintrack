@php
    $cta = getContent('cta.content', true);
@endphp

<!-- cta section start -->
<section class="pt-50 pb-50 dark--overlay-two bg_img  bg-attachment-fixed" style="background-image: url('{{ getImage('assets/images/frontend/cta/' .@$cta->data_values->image, '1920x1080') }}');">
    <div class="container">
        <div class="row gy-4 justify-content-between align-items-center">
        <div class="col-lg-6 text-lg-start text-center wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
            <h2 class="section-title text-white">
                {{ __(@$cta->data_values->title) }}
            </h2>
        </div>
        <div class="col-lg-4 text-lg-start text-center text-lg-end wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
            <a href="{{ @$cta->data_values->button_url }}" class="btn btn--base">
                {{ __(@$cta->data_values->button_text) }}
            </a>
        </div>
        </div>
    </div>
</section>
<!-- cta section end -->
