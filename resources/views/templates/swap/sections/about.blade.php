@php
    $about = getContent('about.content', true);
    $abouts = getContent('about.element', orderById:true);
@endphp

<!-- About Section -->
<div class="about-section pt-100 pb-100">
  <div class="container">
      <div class="row gy-5">
          <div class="col-lg-6">
              <div class="section-header">
                  <h2 class="section-header__title">{{ __(@$about->data_values->heading) }}</h2>
                  <h4 class="mt-4">{{ __(@$about->data_values->subheading) }}</h4>
                  <p class="section-header__text">{{ __(@$about->data_values->description) }}</p>
              </div>
              <a href="{{ @$about->data_values->button_url }}" class="btn btn--base">
                {{ __(@$about->data_values->button_text) }}
                <i class="las la-angle-right ms-1"></i>
            </a>

          </div>
          <div class="col-lg-6 ps-lg-5">
              <div class="section-thumb">
                  <img src="{{ getImage('assets/images/frontend/about/' .@$about->data_values->image, '765x545') }}" alt="" class="mw-100">
              </div>
          </div>
      </div>

      <div class="row gy-4 gy-sm-5 pt-80 align-items-center justify-content-between flex-wrap-reverse">
          <div class="col-lg-4">
              <div class="section-thumb">
                  <img src="{{ getImage('assets/images/frontend/about/' .@$about->data_values->second_image, '450x345') }}" alt="img" class="mw-100">
              </div>
          </div>
          <div class="col-lg-8 col-xl-7">
              <h3 class="pb-50">{{ __(@$about->data_values->second_heading) }}</h3>
              <div class="row gy-sm-5 gy-4">
                @foreach($abouts as $about)
                    <div class="col-sm-6">
                        <div class="why-list">
                            <div class="icon">
                                @php echo @$about->data_values->icon; @endphp
                            </div>
                            <div class="content">
                                <h5>{{ __(@$about->data_values->title) }}</h5>
                                <p>{{ __(@$about->data_values->text) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
              </div>
          </div>
      </div>
  </div>
</div>
<!-- About Section -->
