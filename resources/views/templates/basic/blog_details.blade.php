@extends($activeTemplate . 'layouts.frontend')

@section('content')
    <!-- blog details section start -->
    <section class="pt-100 pb-100 content-section">
        <div class="container">
            <div class="row gy-5">
                <div class="col-lg-8">
                    <div class="blog-post__date fs--14px d-inline-flex align-items-center"><i
                            class="las la-calendar-alt fs--18px me-2"></i>{{ showDateTime($blog->updated_at, 'd M Y') }}</div>
                    <h2 class="blog-details-title mb-3">
                        {{ __($blog->data_values->title) }}
                    </h2>
                    <div class="blog-details-thumb">
                        <img src="{{ getImage('assets/images/frontend/blog/' . @$blog->data_values->image, '856x480') }}"
                            alt="image" class="rounded-3">
                    </div>
                    <div class="blog-details-content mt-4">
                        <p class="fs--18px">
                            @php
                                echo @$blog->data_values->description;
                            @endphp
                        </p>
                    </div>

                    <div class="fb-comments mt-3"
                        data-href="{{ route('blog.details', [slug($blog->data_values->title), $blog->id]) }}"
                        data-numposts="5">
                    </div>

                    <ul class="post-share d-flex flex-wrap align-items-center justify-content-center mt-5">
                        <li class="caption">@lang('Share') : </li>
                        <li data-bs-toggle="tooltip" data-bs-placement="top" title="Facebook">
                            <a href="https://www.facebook.com/sharer/sharer.php?=u{{ url()->current() }}" target="_blank">
                                <i class="lab la-facebook-f"></i>
                            </a>
                        </li>
                        <li data-bs-toggle="tooltip" data-bs-placement="top" title="Linkedin">
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ url()->current() }}"
                                target="_blank">
                                <i class="lab la-linkedin-in"></i>
                            </a>
                        </li>
                        <li data-bs-toggle="tooltip" data-bs-placement="top" title="Twitter">
                            <a href="https://twitter.com/home?status={{ url()->current() }}" target="_blank">
                                <i class="lab la-twitter"></i>
                            </a>
                        </li>
                        <li data-bs-toggle="tooltip" data-bs-placement="top" title="Instagram">
                            <a href="http://www.reddit.com/submit?url={{ url()->current() }}" target="_blank">
                                <i class="lab la-reddit"></i>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-4 ps-xl-5">
                    <div class="blog-sidebar rounded-3 section--bg">
                        <h4 class="title">@lang('Latest Posts')</h4>
                        <ul class="s-post-list">
                            @foreach ($latestBlogs as $singleBlog)
                                <li class="s-post d-flex flex-wrap">
                                    <div class="s-post__thumb">
                                        <img src="{{ getImage('assets/images/frontend/blog/thumb_' . @$singleBlog->data_values->image, '428x240') }}"
                                            alt="image">
                                    </div>
                                    <div class="s-post__content">
                                        <h6 class="s-post__title">
                                            <a
                                                href="{{ route('blog.details', ['slug' => slug($singleBlog->data_values->title), 'id' => $singleBlog->id]) }}">
                                                {{ __($singleBlog->data_values->title) }}
                                            </a>
                                        </h6>
                                        <p class="fs--12px mt-2"><i
                                                class="las la-calendar-alt fs--14px me-1"></i>{{ showDateTime($singleBlog->updated_at, 'd M Y') }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- blog details section end -->
@endsection

@push('fbComment')
    @php echo loadExtension('fb-comment') @endphp
@endpush

