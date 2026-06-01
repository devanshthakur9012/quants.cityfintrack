{{-- FILE: resources/views/admin/cms/about/hero.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.about.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">About Hero Banner</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.about.hero.update') }}" method="POST">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tagline</label>
                            <input type="text" name="tagline" class="form-control"
                                   value="{{ old('tagline', $hero->tagline ?? '') }}"
                                   placeholder="Experts in Providing Investment Consulting Services">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subtitle</label>
                            <input type="text" name="subtitle" class="form-control"
                                   value="{{ old('subtitle', $hero->subtitle ?? '') }}"
                                   placeholder="India's most trusted options analytics platform">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Founded Year</label>
                            <input type="text" name="founded" class="form-control form-control-sm"
                                   value="{{ old('founded', $hero->founded ?? '2017') }}" placeholder="2017">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">HQ Location</label>
                            <input type="text" name="hq" class="form-control form-control-sm"
                                   value="{{ old('hq', $hero->hq ?? '') }}" placeholder="Belgaum, India">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Users</label>
                            <input type="text" name="users" class="form-control form-control-sm"
                                   value="{{ old('users', $hero->users ?? '') }}" placeholder="17 Lakh+">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Experience</label>
                            <input type="text" name="experience" class="form-control form-control-sm"
                                   value="{{ old('experience', $hero->experience ?? '') }}" placeholder="23+ Years">
                        </div>
                    </div>

                    <h6 class="mb-3" style="color:#7DFF00;border-bottom:1px solid #f0f2f7;padding-bottom:8px;">Stat Boxes (shown in hero strip)</h6>
                    @foreach([1,2,3,4] as $n)
                    <div class="row g-2 mb-2 align-items-center">
                        <div class="col-1 text-center fw-bold" style="color:#7DFF00;">{{ $n }}</div>
                        <div class="col-4">
                            <input type="text" name="stat{{ $n }}_value" class="form-control form-control-sm"
                                   value="{{ old('stat'.$n.'_value', $hero->{'stat'.$n.'_value'} ?? '') }}"
                                   placeholder="17 Lakh+">
                        </div>
                        <div class="col-7">
                            <input type="text" name="stat{{ $n }}_label" class="form-control form-control-sm"
                                   value="{{ old('stat'.$n.'_label', $hero->{'stat'.$n.'_label'} ?? '') }}"
                                   placeholder="Active Traders">
                        </div>
                    </div>
                    @endforeach

                    <div class="mt-4">
                        <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
