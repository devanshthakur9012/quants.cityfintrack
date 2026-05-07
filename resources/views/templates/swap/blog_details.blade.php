@extends($activeTemplate . 'layouts.frontend')

@section('content')
    <!-- blog details section start -->
    <div class="blog-details pt-100 pb-100">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-8">
                    <div class="post-item">
                        <div class="post-item__thumb">
                            <img src="{{ getImage('assets/images/frontend/blog/' . @$blog->data_values->image, '855x480') }}"
                                alt="thumb" class="w-100">
                        </div>
                        <div class="post-item__content">
                            <div class="content">
                                <div class="d-flex gap-3 mb-3">
                                    <span class="date info"><i class="fas fa-calendar fs--14px"></i>
                                        {{ showDateTime($blog->created_at, 'd M Y') }}</span>
                                </div>
                                <h4 class="title mb-3 mb-sm-4">{{ __($blog->data_values->title) }}</h4>
                                <p>
                                    @php
                                        echo @$blog->data_values->description;
                                    @endphp 
                                </p>
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
                        </div>
                        <div class="fb-comments mt-3"
                            data-href="{{ route('blog.details', ['slug' => slug($blog->data_values->title), 'id' => $blog->id]) }}"
                            data-numposts="5">
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="sidebar">
                        <div class="sidebar-item">
                            <h6 class="title">@lang('Latest Posts')</h6>
                            <div class="content">
                                @foreach ($latestBlogs as $singleBlog)
                                    <div class="news-item d-flex gap-3">
                                        <div class="thumb">
                                            <img src="{{ getImage('assets/images/frontend/blog/thumb_' . @$singleBlog->data_values->image, '428x240') }}"
                                                alt="thumb">
                                        </div>
                                        <div class="content">
                                            <h6 class="title mb-1 mt-0 fs--16px">
                                                <a
                                                    href="{{ route('blog.details', [slug($singleBlog->data_values->title), $singleBlog->id]) }}">
                                                    {{ __($singleBlog->data_values->title) }}
                                                </a>
                                            </h6>
                                            <small
                                                class="fs--13px">{{ showDateTime($singleBlog->created_at, 'd M Y') }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- blog details section end -->
@endsection

@push('fbComment')
    @php echo loadExtension('fb-comment') @endphp
@endpush
