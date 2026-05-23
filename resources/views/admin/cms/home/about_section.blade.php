{{-- FILE: resources/views/admin/cms/home/about_section.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<form action="{{ route('admin.cms.home.about_section.update') }}" method="POST" enctype="multipart/form-data">
@csrf
<div class="row">
    <div class="col-xl-7">

        {{-- VIDEO --}}
        <div class="card b-radius--10 mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.home.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">About the App — Video & Heading</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Section Heading <small class="text-muted">(italic gold text)</small></label>
                    <input type="text" name="section_heading" class="form-control"
                           value="{{ old('section_heading', $about->section_heading ?? 'Be a " Data Driven " Option Trader!') }}"
                           placeholder='Be a " Data Driven " Option Trader!'>
                </div>
                <div class="mb-3">
                    <label class="form-label">Video Source</label>
                    <select name="video_type" class="form-select" id="videoTypeSelect" onchange="toggleVideoFields()">
                        <option value="youtube" @selected(($about->video_type ?? 'youtube') === 'youtube')>YouTube URL</option>
                        <option value="upload"  @selected(($about->video_type ?? '') === 'upload')>Upload Video</option>
                    </select>
                </div>
                <div id="ytField">
                    <label class="form-label">YouTube URL</label>
                    <input type="text" name="video_url" class="form-control"
                           value="{{ old('video_url', ($about->video_type ?? 'youtube') === 'youtube' ? ($about->video_url ?? '') : '') }}"
                           placeholder="https://www.youtube.com/embed/...">
                </div>
                <div id="uploadVidField" style="display:none;">
                    <label class="form-label">Upload Video</label>
                    <input type="file" name="video_file" class="form-control" accept="video/*">
                    @if(isset($about) && $about->video_type === 'upload' && $about->video_url)
                    <small class="text-muted">Current: {{ $about->video_url }}</small>
                    @endif
                </div>
            </div>
        </div>

        {{-- STATS --}}
        <div class="card b-radius--10 mb-3">
            <div class="card-header"><h5 class="card-title mb-0">Stat Boxes <small class="text-muted fw-normal">(4 boxes shown next to video)</small></h5></div>
            <div class="card-body">
                @php $statCount = max(4, $stats->count()); @endphp
                @for($i = 0; $i < $statCount; $i++)
                @php $s = $stats[$i] ?? null; @endphp
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-1 text-center" style="font-size:18px;font-weight:700;color:#F5A623;">{{ $i+1 }}</div>
                    <div class="col-3">
                        <input type="text" name="stat_value[]" class="form-control form-control-sm"
                               value="{{ old('stat_value.'.$i, $s?->value ?? '') }}" placeholder="6500+">
                    </div>
                    <div class="col-4">
                        <input type="text" name="stat_label[]" class="form-control form-control-sm"
                               value="{{ old('stat_label.'.$i, $s?->label ?? '') }}" placeholder="Happy Clients">
                    </div>
                    <div class="col-4">
                        <input type="text" name="stat_sub[]" class="form-control form-control-sm"
                               value="{{ old('stat_sub.'.$i, $s?->sub ?? '') }}" placeholder="Sub description">
                    </div>
                </div>
                @endfor
                <small class="text-muted"><i class="las la-info-circle"></i> Leave Value blank to hide a stat box.</small>
            </div>
        </div>

    </div>
    <div class="col-xl-4">
        <div class="card b-radius--10 mb-3">
            <div class="card-body">
                <button type="submit" class="btn btn--primary w-100"><i class="las la-save"></i> Save About Section</button>
            </div>
        </div>
    </div>
</div>
</form>

@push('script')
<script>
function toggleVideoFields() {
    var v = document.getElementById('videoTypeSelect').value;
    document.getElementById('ytField').style.display       = v === 'youtube' ? '' : 'none';
    document.getElementById('uploadVidField').style.display = v === 'upload'  ? '' : 'none';
}
toggleVideoFields();
</script>
@endpush

@endsection
