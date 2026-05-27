{{-- FILE: resources/views/admin/cms/pages/course.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.pages.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Courses Page CMS</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.pages.course.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- ── HERO ── --}}
                    <h6 style="color:#F5A623;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">Hero Section</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label">Page Title</label>
                            <input type="text" name="hero_title" class="form-control"
                                   value="{{ old('hero_title', $cms->hero_title ?? 'Learn Option') }}"
                                   placeholder="Learn Option">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Hero Description</label>
                            <textarea name="hero_description" class="form-control" rows="3"
                                      placeholder="Enhance your options trading skills...">{{ old('hero_description', $cms->hero_description ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- ── BANNER IMAGES (4 slots) ── --}}
                    <h6 style="color:#F5A623;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Hero Banner Images <small class="text-muted fw-normal">(4 images shown in grid beside title)</small>
                    </h6>
                    @php $existingBanners = $cms->hero_banners ?? []; @endphp
                    <div class="row g-3 mb-4">
                        @for($i = 1; $i <= 4; $i++)
                        @php $existing = $existingBanners[$i-1] ?? null; @endphp
                        <div class="col-md-3">
                            <label class="form-label">Banner {{ $i }}</label>
                            @if($existing)
                            <img src="{{ str_starts_with($existing, 'http') ? $existing : asset('assets/images/cms/course_banners/'.$existing) }}"
                                 style="width:100%;height:70px;object-fit:cover;border-radius:6px;margin-bottom:6px;">
                            @endif
                            <input type="file" name="banner_{{ $i }}" class="form-control form-control-sm" accept="image/*">
                        </div>
                        @endfor
                    </div>

                    {{-- ── FILTER DROPDOWNS ── --}}
                    <h6 style="color:#F5A623;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">
                        Filter Dropdown Options
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Languages <small class="text-muted">(one per line)</small></label>
                            <textarea name="languages_text" class="form-control" rows="4"
                                      placeholder="Hindi&#10;English&#10;Gujarati">{{ old('languages_text', implode("\n", $cms->languages_list)) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Levels <small class="text-muted">(one per line)</small></label>
                            <textarea name="levels_text" class="form-control" rows="4"
                                      placeholder="Beginner&#10;Intermediate&#10;Advanced">{{ old('levels_text', implode("\n", $cms->levels_list)) }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modes <small class="text-muted">(one per line)</small></label>
                            <textarea name="modes_text" class="form-control" rows="4"
                                      placeholder="Online&#10;Offline&#10;Hybrid">{{ old('modes_text', implode("\n", $cms->modes_list)) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save Courses Page</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection