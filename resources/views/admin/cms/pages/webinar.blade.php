{{-- FILE: resources/views/admin/cms/pages/webinar.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.pages.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Webinar Page CMS</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.pages.webinar.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- ── HERO ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">Hero Section</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Page Title</label>
                            <input type="text" name="hero_title" class="form-control"
                                   value="{{ old('hero_title', $cms->hero_title ?? 'Webinar') }}"
                                   placeholder="Webinar">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Hero Description</label>
                            <textarea name="hero_description" class="form-control" rows="3"
                                      placeholder="Our webinar series is designed to...">{{ old('hero_description', $cms->hero_description ?? '') }}</textarea>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Illustration URL <small class="text-muted">(or upload below)</small></label>
                            <input type="text" name="hero_illustration_url" class="form-control"
                                   value="{{ old('hero_illustration_url', $cms->hero_illustration_url ?? '') }}"
                                   placeholder="https://...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Upload Illustration</label>
                            <input type="file" name="hero_illustration_file" class="form-control" accept="image/*">
                            @if($cms->hero_illustration_url)
                            <img src="{{ $cms->hero_illustration_url }}" style="height:50px;margin-top:6px;border-radius:4px;">
                            @endif
                        </div>
                    </div>

                    {{-- ── FILTER DROPDOWNS ── --}}
                    <h6 style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Filter Dropdown Options
                    </h6>
                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label class="form-label">Languages <small class="text-muted">(one per line)</small></label>
                            <textarea name="languages_text" class="form-control" rows="4"
                                      placeholder="Hindi&#10;English&#10;Gujarati">{{ old('languages_text', implode("\n", $cms->languages_list)) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proficiency Levels <small class="text-muted">(one per line)</small></label>
                            <textarea name="proficiency_text" class="form-control" rows="4"
                                      placeholder="Beginner&#10;Intermediate&#10;Advanced">{{ old('proficiency_text', implode("\n", $cms->proficiency_list)) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save Webinar Page</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection