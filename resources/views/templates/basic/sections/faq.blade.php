@php
    $faq = getContent('faq.content', true);
    $faqs = getContent('faq.element', orderById:true);
@endphp

<!-- faq section start -->
<section class="pt-100 pb-100">
    <div class="container">
        <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="section-header text-center wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
            <h2 class="section-title">{{ __(@$faq->data_values->heading) }}</h2>
            <p class="mt-3">{{ __(@$faq->data_values->subheading) }}</p>
            </div>
        </div>
        </div><!-- row end -->
        <div class="accordion custom--accordion wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s" id="faqAccordion">
        <div class="row g-3">
        @foreach($faqs as $singleFaq)
            <div class="col-lg-6">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="h-{{ $singleFaq->id }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-{{ $singleFaq->id }}" aria-expanded="false" aria-controls="c-{{ $singleFaq->id }}">
                        {{ __($singleFaq->data_values->question) }}
                    </button>
                    </h2>
                    <div id="c-{{ $singleFaq->id }}" class="accordion-collapse collapse" aria-labelledby="h-{{ $singleFaq->id }}" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        <p>{{ __($singleFaq->data_values->answer) }}</p>
                    </div>
                    </div>
                </div><!-- accordion-item-->
            </div>
        @endforeach
        </div>
        </div>
    </div>
</section>
<!-- faq section end -->
