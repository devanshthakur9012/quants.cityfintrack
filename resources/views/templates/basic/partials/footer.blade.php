@php
    $policyPages = getContent('policy_pages.element', orderById:true);
    $socialIcons = getContent('social_icon.element', orderById:true);
@endphp

<!-- footer section start  -->
<footer class="footer">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-3">
                <div class="footer-logo text-lg-start text-center">
                    <a href="{{ route('home') }}" class="footer-logo"><img src="{{getImage(getFilePath('logoIcon') .'/logo.png')}}" alt="image"></a>
                </div>
            </div>
            <div class="col-lg-6">
                <ul class="inline-menu d-flex flex-wrap align-items-center justify-content-center">
                    @foreach($policyPages as $policyPage)
                        <li>
                            <a href="{{route('policy.pages',['slug'=>slug($policyPage->data_values->title), 'id'=>$policyPage->id])}}">
                                {{__($policyPage->data_values->title)}}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="col-lg-3">
                <ul class="social-link-list d-flex flex-wrap align-items-center justify-content-center justify-content-lg-end">
                    @foreach($socialIcons as $icon)
                        <li>
                            <a href="{{ $icon->data_values->url }}" target="_blank">
                                @php echo $icon->data_values->social_icon; @endphp
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</footer>
<!-- footer section section end  -->
