{{-- FILE: resources/views/admin/cms/pages/event.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.pages.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Events Page CMS</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.pages.event.update') }}" method="POST">
                    @csrf

                    {{-- ── HERO ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">Hero Section</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label">Eyebrow Label</label>
                            <input type="text" name="hero_eyebrow" class="form-control"
                                   value="{{ old('hero_eyebrow', $cms->hero_eyebrow ?? 'Offline Events & Workshops') }}"
                                   placeholder="Offline Events & Workshops">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Page Title <small class="text-muted">(full text including highlight)</small></label>
                            <input type="text" name="hero_title" class="form-control"
                                   value="{{ old('hero_title', $cms->hero_title ?? 'Options Trading Events & Workshops') }}"
                                   placeholder="Options Trading Events & Workshops">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Title Highlight <small class="text-muted">(rendered in gold)</small></label>
                            <input type="text" name="hero_title_highlight" class="form-control"
                                   value="{{ old('hero_title_highlight', $cms->hero_title_highlight ?? 'Events') }}"
                                   placeholder="Events">
                            <small class="text-muted">Must be an exact substring of the title above.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Hero Subtitle</label>
                            <textarea name="hero_subtitle" class="form-control" rows="2"
                                      placeholder="Join India's top options traders...">{{ old('hero_subtitle', $cms->hero_subtitle ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- ── CITIES LIST ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        City Filter Dropdown
                        <small class="text-muted fw-normal ms-2">Format: <code>key|Display Label</code> per line (or just the city name)</small>
                    </h6>
                    <div class="mb-4">
                        <textarea name="cities_text" class="form-control" rows="8"
                                  placeholder="bangalore|Bangalore&#10;delhi|Delhi&#10;mumbai|Mumbai">{{ old('cities_text', collect($cms->cities_map)->map(fn($label,$key) => "$key|$label")->implode("\n")) }}</textarea>
                    </div>

                    {{-- ── BOTTOM CTA STRIP ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Bottom CTA Strip <small class="text-muted fw-normal">(leave blank to hide)</small>
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">CTA Heading</label>
                            <input type="text" name="cta_title" class="form-control"
                                   value="{{ old('cta_title', $cms->cta_title ?? '') }}"
                                   placeholder="Want to host an event with us?">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Button Label</label>
                            <input type="text" name="cta_btn_label" class="form-control"
                                   value="{{ old('cta_btn_label', $cms->cta_btn_label ?? '') }}"
                                   placeholder="Contact Us">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Button URL</label>
                            <input type="text" name="cta_btn_url" class="form-control"
                                   value="{{ old('cta_btn_url', $cms->cta_btn_url ?? '#') }}"
                                   placeholder="https://...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">CTA Description</label>
                            <textarea name="cta_description" class="form-control" rows="2"
                                      placeholder="Reach out to partner with us...">{{ old('cta_description', $cms->cta_description ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save Events Page</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection