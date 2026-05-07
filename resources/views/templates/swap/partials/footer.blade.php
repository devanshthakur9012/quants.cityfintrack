@php
    $policyPages = getContent('policy_pages.element', orderById:true);
    $socialIcons = getContent('social_icon.element', orderById:true);
    $footer = getContent('footer.content', true);
@endphp
<!-- Footer Section -->
<footer class="footer-section pt-50 pb-50 bg--accent">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between footer-top gap-3">
            <a href="{{ route('home') }}" class="logo">
                <img src="{{getImage(getFilePath('logoIcon') .'/logo.png')}}" alt="logo">
            </a>
            <ul class="social-links d-flex flex-wrap fs--22px">
                @foreach($socialIcons as $icon)
                    <li>
                        <a href="{{ $icon->data_values->url }}" target="_blank">
                            @php echo $icon->data_values->social_icon; @endphp
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="footer-bottom">
            <div class="row gy-4 gy-sm-5">
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="footer-widget">
                        <h4 class="footer-widget__title text-white mb-2 mb-lg-4">@lang('Important Links')</h4>
                        <ul class="links">
                            <li><a href="{{ route('packages') }}">@lang('Products')</a></li>
                            <li><a href="{{ route('user.login') }}">@lang('Login')</a></li>
                            <li><a href="{{ route('user.register') }}">@lang('Create Account')</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="footer-widget">
                        <h4 class="footer-widget__title text-white mb-2 mb-lg-4">@lang('Information')</h4>
                        <ul class="links">
                            <li><a href="{{ route('home') }}">@lang('Home')</a></li>
                            <li><a href="{{ route('blogs') }}">@lang('Blogs')</a></li>
                            <li><a href="{{ route('contact') }}">@lang('Contact')</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="footer-widget">
                        <h4 class="footer-widget__title text-white mb-2 mb-lg-4">@lang('Other Links')</h4>
                        <ul class="links">
                            @foreach($policyPages as $policyPage)
                                <li>
                                    <a href="{{route('policy.pages',['slug'=>slug($policyPage->data_values->title), 'id'=>$policyPage->id])}}">
                                        {{__($policyPage->data_values->title)}}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="footer-widget">
                        <h4 class="footer-widget__title text-white mb-2 mb-lg-4">@lang('Contact Us')</h4>
                        <ul class="links">
                            <li>
                                <a href="mailto:{{ @$footer->data_values->email }}"><i class="las la-envelope"></i>
                                    {{ @$footer->data_values->email }}
                                </a>
                            </li>
                            <li><a href="tel:{{ @$footer->data_values->contact_number }}">
                                <i class="las la-phone-volume"></i>
                                {{ @$footer->data_values->contact_number }}
                            </a>
                            </li>
                            <li>
                                <p class='fs--15px mt-1 mt-sm-0 text-white'>
                                    {{ __(@$footer->data_values->address) }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- Footer Section -->
