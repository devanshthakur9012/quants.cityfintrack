{{-- FILE: resources/views/admin/cms/about/mission.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card b-radius--10">
            <div class="card-header d-flex align-items-center gap-2">
                <a href="{{ route('admin.cms.about.index') }}" class="btn btn--secondary btn--sm"><i class="las la-arrow-left"></i></a>
                <h5 class="card-title mb-0">Mission & Vision</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cms.about.mission.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Heading</label>
                        <input type="text" name="heading" class="form-control"
                               value="{{ old('heading', $data->heading ?? 'Our Mission & Vision') }}">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Body Text</label>
                        <textarea name="body" class="form-control" rows="5">{{ old('body', $data->body ?? '') }}</textarea>
                    </div>

                    <h6 style="color:#F5A623;border-bottom:1px solid #f0f2f7;padding-bottom:8px;margin-bottom:16px;">Value Items <small class="text-muted fw-normal">(icon, label, description rows)</small></h6>
                    <div class="row g-1 mb-1" style="font-size:11px;color:#999;font-weight:700;text-transform:uppercase;">
                        <div class="col-2">Icon (fa-...)</div><div class="col-3">Label</div><div class="col-6">Description</div><div class="col-1"></div>
                    </div>
                    <div id="valuesContainer">
                        @php $values = $data->values ?? []; @endphp
                        @foreach($values as $v)
                        <div class="value-row row g-2 mb-2 align-items-center">
                            <div class="col-2"><input type="text" name="value_icon[]" class="form-control form-control-sm" value="{{ $v['icon'] ?? '' }}" placeholder="fa-eye"></div>
                            <div class="col-3"><input type="text" name="value_label[]" class="form-control form-control-sm" value="{{ $v['label'] ?? '' }}" placeholder="Transparency"></div>
                            <div class="col-6"><input type="text" name="value_desc[]" class="form-control form-control-sm" value="{{ $v['desc'] ?? '' }}" placeholder="Description..."></div>
                            <div class="col-1"><button type="button" class="btn btn--danger btn--sm" style="padding:4px 8px;" onclick="this.closest('.value-row').remove()"><i class="las la-trash"></i></button></div>
                        </div>
                        @endforeach
                        <div id="newValues"></div>
                    </div>
                    <button type="button" class="btn btn--secondary btn--sm mb-3" onclick="addValue()">
                        <i class="las la-plus"></i> Add Value
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
function addValue() {
    var html = '<div class="value-row row g-2 mb-2 align-items-center">' +
        '<div class="col-2"><input type="text" name="value_icon[]" class="form-control form-control-sm" placeholder="fa-eye"></div>' +
        '<div class="col-3"><input type="text" name="value_label[]" class="form-control form-control-sm" placeholder="Transparency"></div>' +
        '<div class="col-6"><input type="text" name="value_desc[]" class="form-control form-control-sm" placeholder="Description..."></div>' +
        '<div class="col-1"><button type="button" class="btn btn--danger btn--sm" style="padding:4px 8px;" onclick="this.closest(\'.value-row\').remove()"><i class="las la-trash"></i></button></div>' +
        '</div>';
    document.getElementById('newValues').insertAdjacentHTML('beforeend', html);
}
</script>
@endpush
@endsection
