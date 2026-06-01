{{-- FILE: resources/views/admin/cms/pages/auth.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-12 mb-3">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.pages.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Login &amp; Register Pages CMS</h5>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-body">
                <form action="{{ route('admin.cms.pages.auth.update') }}" method="POST">
                    @csrf

                    {{-- ── PROMO VIDEO ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Promo Video (Left Panel)
                    </h6>
                    <div class="mb-4">
                        <label class="form-label">YouTube Embed URL</label>
                        <input type="text" name="promo_video_url" class="form-control"
                               value="{{ old('promo_video_url', $cms->promo_video_url ?? '') }}"
                               placeholder="https://www.youtube.com/embed/MxpeY6j-_XE?si=...&autoplay=1&mute=1">
                        <small class="text-muted">Include <code>&amp;autoplay=1&amp;mute=1&amp;rel=0</code> parameters for best experience.</small>
                    </div>

                    {{-- ── FEATURE BULLETS ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Feature Bullet Points
                        <small class="text-muted fw-normal ms-1">(shown on left panel — one per line)</small>
                    </h6>
                    <div class="mb-4">
                        <textarea name="features_text" class="form-control" rows="5"
                                  placeholder="25 Free Real Time Tools&#10;59 Premium Real Time Tools&#10;2 Option Algorithm">{{ old('features_text', implode("\n", $cms->features_list)) }}</textarea>
                    </div>

                    {{-- ── PAGE HEADINGS ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">Page Headings</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Login Page Heading</label>
                            <input type="text" name="login_heading" class="form-control"
                                   value="{{ old('login_heading', $cms->login_heading ?? 'Welcome Back') }}"
                                   placeholder="Welcome Back">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Login Page Subheading</label>
                            <input type="text" name="login_subheading" class="form-control"
                                   value="{{ old('login_subheading', $cms->login_subheading ?? '') }}"
                                   placeholder="Sign in to your CityQuants account">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Register Page Heading</label>
                            <input type="text" name="register_heading" class="form-control"
                                   value="{{ old('register_heading', $cms->register_heading ?? 'Create Account') }}"
                                   placeholder="Create Account">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Register Page Subheading</label>
                            <input type="text" name="register_subheading" class="form-control"
                                   value="{{ old('register_subheading', $cms->register_subheading ?? '') }}"
                                   placeholder="Join thousands of option traders">
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save Auth Pages</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── BROKERS ── --}}
    <div class="col-xl-4">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">Broker Logos / Pills</h5></div>
            <div class="card-body">
                <form action="{{ route('admin.cms.pages.auth.update') }}" method="POST">
                    @csrf
                    {{-- Hidden fields for non-broker data to avoid overwriting --}}
                    <input type="hidden" name="promo_video_url"     value="{{ $cms->promo_video_url ?? '' }}">
                    <input type="hidden" name="login_heading"       value="{{ $cms->login_heading ?? 'Welcome Back' }}">
                    <input type="hidden" name="login_subheading"    value="{{ $cms->login_subheading ?? '' }}">
                    <input type="hidden" name="register_heading"    value="{{ $cms->register_heading ?? 'Create Account' }}">
                    <input type="hidden" name="register_subheading" value="{{ $cms->register_subheading ?? '' }}">
                    <input type="hidden" name="features_text"       value="{{ implode("\n", $cms->features_list) }}">

                    <small class="text-muted d-block mb-3">Each row = one broker pill shown on login/register left panel.</small>

                    <div id="brokerRows">
                        @foreach($cms->brokers_list as $i => $broker)
                        <div class="broker-row row g-1 mb-2 align-items-center">
                            <div class="col-5">
                                <input type="text" name="broker_name[]" class="form-control form-control-sm"
                                       value="{{ $broker['name'] }}" placeholder="Broker name">
                            </div>
                            <div class="col-2">
                                <input type="text" name="broker_letter[]" class="form-control form-control-sm"
                                       value="{{ $broker['letter'] }}" placeholder="Z" maxlength="2">
                            </div>
                            <div class="col-4">
                                <input type="color" name="broker_bg[]" class="form-control form-control-color form-control-sm w-100"
                                       value="{{ $broker['bg'] }}">
                            </div>
                            <div class="col-1">
                                <button type="button" class="btn btn--danger btn--sm" style="padding:3px 7px;"
                                        onclick="this.closest('.broker-row').remove()"><i class="las la-trash"></i></button>
                            </div>
                        </div>
                        @endforeach
                        <div id="newBrokers"></div>
                    </div>

                    <button type="button" class="btn btn--secondary btn--sm mb-3" onclick="addBroker()">
                        <i class="las la-plus"></i> Add Broker
                    </button>

                    <button type="submit" class="btn btn--primary btn--sm w-100">
                        <i class="las la-save"></i> Save Brokers
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
function addBroker() {
    var html = '<div class="broker-row row g-1 mb-2 align-items-center">' +
        '<div class="col-5"><input type="text" name="broker_name[]" class="form-control form-control-sm" placeholder="Broker name"></div>' +
        '<div class="col-2"><input type="text" name="broker_letter[]" class="form-control form-control-sm" placeholder="Z" maxlength="2"></div>' +
        '<div class="col-4"><input type="color" name="broker_bg[]" class="form-control form-control-color form-control-sm w-100" value="#455a64"></div>' +
        '<div class="col-1"><button type="button" class="btn btn--danger btn--sm" style="padding:3px 7px;" onclick="this.closest(\'.broker-row\').remove()"><i class="las la-trash"></i></button></div>' +
        '</div>';
    document.getElementById('newBrokers').insertAdjacentHTML('beforeend', html);
}
</script>
@endpush
@endsection