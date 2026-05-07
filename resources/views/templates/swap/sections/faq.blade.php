@php
    $faq = getContent('faq.content', true);
    $faqs = getContent('faq.element', orderById:true);
@endphp

<!-- Faq Section -->
<div class="faq-section pt-100 pb-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-header text-center">
                    <h2 class="section-header__title">{{ __(@$faq->data_values->heading) }}</h2>
                    <p class="section-header__text">{{ __(@$faq->data_values->subheading) }}</p>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-6 pe-lg-4 pe-xl-5">
                @foreach($faqs as $faq)
                    @if($loop->odd)
                        <div class="faq-item">
                            <div class="faq-item__title">
                                <h5 class="title">{{ __(@$faq->data_values->question) }}</h5>
                            </div>
                            <div class="faq-item__content">
                                <p>{{ __(@$faq->data_values->answer) }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="col-lg-6 ps-lg-4 pe-xl-5">
                @foreach($faqs as $faq)
                    @if($loop->even)
                        <div class="faq-item">
                            <div class="faq-item__title">
                                <h5 class="title">{{ __(@$faq->data_values->question) }}</h5>
                            </div>
                            <div class="faq-item__content">
                                <p>I{{ __(@$faq->data_values->answer) }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
<!-- Faq Section -->
