@php $banner = getContent('banner.content', true); @endphp
<!-- Banner Section -->
    <div class="banner-section position-relative overflow-hidden">
        <video class="banner-video" id="bannerVideo" src="{{ asset('assets/images/frontend/banner/'. @$banner->data_values->video_file) }}" autoplay loop>
        </video>
        <div class="container">
            <div class="d-flex flex-wrap">
                <div class="banner-content">
                    <h1 class="title text-white"><span>{{ __(@$banner->data_values->heading) }}</span></h1>
                    <p class="text-white banner-text mt-4">
                        {{ __(@$banner->data_values->subheading) }}
                    </p>
                    <div class="d-flex flex-wrap gap-3 pt-50 justify-content-center justify-content-lg-start">
                        <a href="{{ @$banner->data_values->button_url }}" class="btn btn--base">
                            {{ __(@$banner->data_values->button_text) }}
                        </a>
                        <a href="{{ @$banner->data_values->second_button_url }}" class="btn btn--base active">
                            {{ __(@$banner->data_values->second_button_text) }}
                        </a>
                    </div>
                </div>
                <div class="banner-thumb ps-lg-5 d-none d-lg-block">
                    <img src="{{ getImage('assets/images/frontend/banner/' .@$banner->data_values->banner_thumb, '550x565') }}" alt="thumb" class="mw-100">
                </div>
            </div>
        </div>
        <div class="banner-shape-wrapper d-none d-lg-block">
            <img src="{{ asset($activeTemplateTrue. 'images/thumb/banner2.png') }}" alt="thumb" class="banner-shape">
            <img src="{{ asset($activeTemplateTrue. 'images/thumb/banner2.png') }}" alt="thumb" class="banner-shape2">
        </div>
    </div>
<!-- Banner Section -->
