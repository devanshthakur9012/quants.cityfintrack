@php
$breadCrumb = getContent('bread_crumb.content', true);
@endphp

<!-- inner hero section start -->
<section class="inner-banner bg_img position-relative"
    style="background: url('{{ getImage('assets/images/frontend/bread_crumb/' . @$breadCrumb->data_values->image, '1920x1280') }}') center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-xl-6 text-center">
                <h3 class="title text-white">{{ __($pageTitle) }}</h3>
            </div> 
        </div>
    </div>
</section>
<!-- inner hero section end -->
