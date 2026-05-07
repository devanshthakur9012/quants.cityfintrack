@extends($activeTemplate . 'layouts.frontend')

@php
    $contact = getContent('contact_us.content', true);
    $contacts = getContent('contact_us.element', orderById: true);
@endphp

@section('content')
    <!-- contact section start -->
    <section class="contact-section overflow-hidden">
        <div class="map-area bg_img" style="background-image: url('{{ $activeTemplateTrue . '/images/bg/map.jpg' }}');">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-5 text-lg-start text-center">
                        <h2 class="section-title text-white">{{ __(@$contact->data_values->heading) }}</h2>
                        <p class="text-white-75">{{ __(@$contact->data_values->subheading) }}</p>
                    </div>
                    <div class="col-lg-5 text-lg-end text-center">
                        <div class="map-info-box">
                            <div class="map-info rounded-3">
                                <p class="text-white-75"><strong class="fw-bold">@lang('Location') : </strong>{{ __(@$contact->data_values->address) }}</p>
                                <p><a href="{{ __(@$contact->data_values->google_map) }}" target="_blank" class="text--base fs--14px">@lang('See on google map')</a></p>
                            </div>
                            <div class="map-icon">
                                <i class="las la-map-marker"></i>
                                <div class="map-pointer-shadow"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- map-area end -->
        <div class="contact-area pb-100">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="contact-wrapper section--bg d-flex flex-wrap">
                            <div class="contact-wrapper__left">
                                <form class="transparent-form verify-gcaptcha" method="post" action="#">
                                    @csrf
                                    <div class="row">
                                        <div class="col-xl-6 form-group">
                                            <label>@lang('Name') <sup class="text--danger">*</sup></label>
                                            <div class="custom-icon-field">
                                                <i class="las la-user"></i>
                                                <input name="name" type="text" class="form--control" placeholder="@lang('Enter name')" value="{{ auth()->user() ? auth()->user()->fullname : old('name') }}" {{ auth()->user() ? 'readonly' : null }} required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 form-group">
                                            <label>@lang('Email') <sup class="text--danger">*</sup></label>
                                            <div class="custom-icon-field">
                                                <i class="las la-envelope"></i>
                                                <input name="email" type="text" class="form--control" placeholder="@lang('Enter email')" value="{{ auth()->user() ? auth()->user()->email : old('email') }}" {{ auth()->user() ? 'readonly' : null }} required>
                                            </div>
                                        </div>
                                        <div class="col-12 form-group">
                                            <label>@lang('Subject') <sup class="text--danger">*</sup></label>
                                            <div class="custom-icon-field">
                                                <i class="las la-sticky-note"></i>
                                                <input name="subject" type="text" class="form--control" placeholder="@lang('Enter subject')" value="{{ old('subject') }}" required>
                                            </div>
                                        </div>
                                        <div class="col-12 form-group">
                                            <label>@lang('Message') <sup class="text--danger">*</sup></label>
                                            <div class="custom-icon-field">
                                                <i class="las la-envelope-square"></i>
                                                <textarea name="message" wrap="off" class="form--control" placeholder="@lang('Enter message')" required>{{ old('message') }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-12 form-group">
                                            <x-captcha></x-captcha>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="contact-wrapper__right">
                                <div class="mb-4">
                                    <h4 class="mb-4">@lang('Contact Info')</h4>
                                    <ul class="contact-info-list">
                                        @foreach ($contacts as $singleContact)
                                            <li class="contact-info-single">
                                                @php
                                                    echo $singleContact->data_values->icon;
                                                @endphp
                                                <p>{{ __($singleContact->data_values->address) }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- contact section end -->

    @if ($sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include($activeTemplate . 'sections.' . $sec)
        @endforeach
    @endif
@endsection
