@php
    $blog = getContent('blog.content', true);
    $blogs = getContent('blog.element', false, 3, false);
@endphp

<!-- Blog Section -->
<div class="section-section pt-100 pb-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="section-header text-center">
                    <h2 class="section-header__title">{{ __(@$blog->data_values->heading) }}</h2>
                    <p class="section-header__text">{{ __(@$blog->data_values->subheading) }}</p>
                </div>
            </div>
        </div>
        <div class="row gy-4 justify-content-center">
            @foreach($blogs as $blog)                
                <div class="col-lg-4 col-md-6">
                    <div class="post-item">
                        <div class="post-item__thumb">
                            <img src="{{ getImage('assets/images/frontend/blog/thumb_' .@$blog->data_values->image, '428x240') }}" alt="thumb" class="w-100">
                        </div>
                        <div class="post-item__content">
                            <p class="date fs--14px fw-medium mb-2"><i class="las la-calendar"></i> {{ showDateTime($blog->created_at, 'd M Y') }}</p>
                            <h4 class="post-item__content-title">
                                <a href="{{ route('blog.details', ['slug'=>slug($blog->data_values->title), 'id'=>$blog->id]) }}">
                                    {{ __(@$blog->data_values->title) }}
                                </a>
                            </h4>
                            <a href="{{ route('blog.details', ['slug'=>slug($blog->data_values->title), 'id'=>$blog->id]) }}" class="text-decoration-underline fw-bold text--base">
                                @lang('Read More')
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
<!-- Blog Section -->
