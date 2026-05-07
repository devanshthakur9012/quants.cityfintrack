@extends($activeTemplate.'layouts.frontend')

@php
    $contact = getContent('contact_us.content', true);
    $contacts = getContent('contact_us.element', orderById:true);
@endphp

@section('content')

<!-- Contact Section -->
<div class="contact-section pt-100 pb-100">
    <div class="container">
        <div class="row gy-4 justify-content-between">
            <div class="col-lg-5">
                <h3 class="mb-2 mb-sm-3">{{ __(@$contact->data_values->heading) }}</h3>
                <p class="mb-3 mb-sm-4">{{ __(@$contact->data_values->subheading) }}</p>
                <div class="row gy-lg-5 gy-4">

                    @foreach($contacts as $singleContact)
                    <div class="col-lg-12 col-sm-6">
                        <div class="contact-info">
                            <div class="contact-info__icon">
                                @php
                                    echo $singleContact->data_values->icon;
                                @endphp
                            </div>
                            <div class="contact-info__content">
                                <h6 class="title">{{ __($singleContact->data_values->title) }}</h6>
                                <p>{{ __($singleContact->data_values->address) }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-wrapper">
                    <form action="#" method="post" class="verify-gcaptcha">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="form-label fw-medium">@lang('Name')</label>
                            <input name="name" type="text" class="form-control form--control border-0 bg-white" value="{{ auth()->user() ? auth()->user()->fullname : old('name') }}" {{ auth()->user() ? 'readonly' : null }} required>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label fw-medium">@lang('Email')</label>
                            <input name="email" type="email" class="form-control form--control border-0 bg-white" value="{{ auth()->user() ? auth()->user()->email : old('email') }}" {{ auth()->user() ? 'readonly' : null }} required>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label fw-medium">@lang('Subject')</label>
                            <input name="subject" type="text" class="form-control form--control border-0 bg-white" value="{{old('subject')}}" required>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label fw-medium">@lang('Message')</label>
                            <textarea name="message" class="form-control form--control border-0 bg-white" required>{{old('message')}}</textarea>
                        </div>

                        <div class="col-12 form-group">
                            <x-captcha />
                        </div>

                        <button type="submit" class="btn btn--base w-100 mt-2">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Contact Section -->

@if($sections->secs != null)
    @foreach(json_decode($sections->secs) as $sec)
        @include($activeTemplate.'sections.'.$sec)
    @endforeach
@endif
@endsection
