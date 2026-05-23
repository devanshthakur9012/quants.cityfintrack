{{-- FILE: resources/views/admin/cms/about/who_we_are.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.about.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Who We Are</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.about.who_we_are.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Heading</label>
                        <input type="text" name="heading" class="form-control"
                               value="{{ old('heading', $data->heading ?? 'Who Are We?') }}">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Body Text</label>
                        <textarea name="body" class="form-control" rows="5">{{ old('body', $data->body ?? '') }}</textarea>
                    </div>

                    <h6 style="color:#F5A623;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">Pillars <small class="text-muted fw-normal">(shown as pills below text)</small></h6>
                    <div id="pillarsContainer">
                        @php $pillars = $data->pillars ?? []; @endphp
                        @foreach($pillars as $i => $p)
                        <div class="pillar-row row g-2 mb-2 align-items-center">
                            <div class="col-4">
                                <input type="text" name="pillar_icon[]" class="form-control form-control-sm"
                                       value="{{ $p['icon'] ?? '' }}" placeholder="fa-chart-line">
                            </div>
                            <div class="col-7">
                                <input type="text" name="pillar_label[]" class="form-control form-control-sm"
                                       value="{{ $p['label'] ?? '' }}" placeholder="Capital Risk Frameworks">
                            </div>
                            <div class="col-1">
                                <button type="button" class="btn btn--danger btn--sm" style="padding:4px 8px;"
                                        onclick="this.closest('.pillar-row').remove()"><i class="las la-trash"></i></button>
                            </div>
                        </div>
                        @endforeach
                        <div id="newPillars"></div>
                    </div>
                    <button type="button" class="btn btn--secondary btn--sm mb-3" onclick="addPillar()">
                        <i class="las la-plus"></i> Add Pillar
                    </button>

                    <div class="mt-2">
                        <button type="submit" class="btn btn--primary"><i class="las la-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@push('script')
<script>
function addPillar() {
    var html = '<div class="pillar-row row g-2 mb-2 align-items-center">' +
        '<div class="col-4"><input type="text" name="pillar_icon[]" class="form-control form-control-sm" placeholder="fa-chart-line"></div>' +
        '<div class="col-7"><input type="text" name="pillar_label[]" class="form-control form-control-sm" placeholder="Label"></div>' +
        '<div class="col-1"><button type="button" class="btn btn--danger btn--sm" style="padding:4px 8px;" onclick="this.closest(\'.pillar-row\').remove()"><i class="las la-trash"></i></button></div>' +
        '</div>';
    document.getElementById('newPillars').insertAdjacentHTML('beforeend', html);
}
</script>
@endpush
@endsection
