{{-- FILE: resources/views/admin/cms/home/hero.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card b-radius--10 mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.home.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Hero Section</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.home.hero.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Heading Line 1 <small class="text-muted">("Complex")</small></label>
                            <input type="text" name="heading_line1" class="form-control"
                                   value="{{ old('heading_line1', $hero->heading_line1 ?? 'Complex') }}"
                                   placeholder="Complex">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Highlight Word <small class="text-muted text-warning">(gold color)</small></label>
                            <input type="text" name="heading_highlight" class="form-control"
                                   value="{{ old('heading_highlight', $hero->heading_highlight ?? 'Option') }}"
                                   placeholder="Option">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heading Line 2 <small class="text-muted">("Simplified")</small></label>
                            <input type="text" name="heading_line2" class="form-control"
                                   value="{{ old('heading_line2', $hero->heading_line2 ?? 'Simplified') }}"
                                   placeholder="Simplified">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Background Video <small class="text-muted">(MP4, max 50MB — saved to /assets/video/)</small></label>
                        @if($hero && $hero->video_file)
                        <div class="mb-2">
                            <span class="badge badge--success"><i class="las la-check"></i> Current: {{ $hero->video_file }}</span>
                        </div>
                        @endif
                        <input type="file" name="video_file" class="form-control" accept="video/mp4,video/*">
                        <small class="text-muted">Leave blank to keep existing video.</small>
                    </div>

                    <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save Hero</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
