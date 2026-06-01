{{-- ═══════════════════════════════════════════════════════════════════
     FILE: resources/views/admin/cms/pages/media.blade.php
     Admin CMS for the Media page hero & CTA strip
════════════════════════════════════════════════════════════════════ --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.pages.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Media Page — Hero &amp; CTA</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.pages.media.update') }}" method="POST">
                    @csrf

                    {{-- ── HERO ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Hero Section
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Eyebrow Label <small class="text-muted">(pill above title)</small></label>
                            <input type="text" name="hero_eyebrow" class="form-control"
                                   value="{{ old('hero_eyebrow', $cms->hero_eyebrow ?? 'Press, Media & Recognition') }}"
                                   placeholder="Press, Media & Recognition">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Page Title</label>
                            <input type="text" name="hero_title" class="form-control"
                                   value="{{ old('hero_title', $cms->hero_title ?? 'CityQuants In The Media') }}"
                                   placeholder="CityQuants In The Media">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Title Highlight <small class="text-muted">(portion shown in gold)</small></label>
                            <input type="text" name="hero_title_highlight" class="form-control"
                                   value="{{ old('hero_title_highlight', $cms->hero_title_highlight ?? 'In The Media') }}"
                                   placeholder="In The Media">
                            <small class="text-muted">Must be an exact substring of the full title above.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Hero Subtitle / Description</label>
                            <textarea name="hero_subtitle" class="form-control" rows="3"
                                      placeholder="TV interviews, podcast appearances...">{{ old('hero_subtitle', $cms->hero_subtitle ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- ── CTA STRIP ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Press Enquiry CTA Strip
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">CTA Heading</label>
                            <input type="text" name="cta_title" class="form-control"
                                   value="{{ old('cta_title', $cms->cta_title ?? 'Press & Media Enquiries') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="cta_email" class="form-control"
                                   value="{{ old('cta_email', $cms->cta_email ?? 'media@cityquants.com') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Button Label</label>
                            <input type="text" name="cta_btn_label" class="form-control"
                                   value="{{ old('cta_btn_label', $cms->cta_btn_label ?? 'Contact Media Team') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">CTA Description</label>
                            <textarea name="cta_description" class="form-control" rows="2"
                                      placeholder="For interviews, features...">{{ old('cta_description', $cms->cta_description ?? '') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save Media Page</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection