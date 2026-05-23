{{-- FILE: resources/views/admin/cms/home/platform.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-7">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.home.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Platform Banner</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.home.platform.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label required">Title</label>
                        <input type="text" name="title" class="form-control" required
                               value="{{ old('title', $platform->title ?? "India's Largest Options Trading Analytics Platform") }}"
                               placeholder="India's Largest Options Trading Analytics Platform">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subtitle</label>
                        <input type="text" name="subtitle" class="form-control"
                               value="{{ old('subtitle', $platform->subtitle ?? '') }}"
                               placeholder="Build an option strategy with our options trading analytical tools.">
                    </div>
                    <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
