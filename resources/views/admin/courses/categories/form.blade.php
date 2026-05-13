{{-- FILE: resources/views/admin/courses/categories/form.blade.php --}}
@extends('admin.layouts.app')

@section('panel')
@php $editing = isset($category); @endphp

<div class="row justify-content-center">
    <div class="col-xl-7">
        <div class="card b-radius--10">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="las la-tag me-1"></i>
                    {{ $editing ? 'Edit Category: ' . $category->name : 'Add Course Category' }}
                </h5>
            </div>
            <form action="{{ $editing ? route('admin.courses.categories.update', $category) : route('admin.courses.categories.store') }}"
                  method="POST">
                @csrf
                @if($editing) @method('PUT') @endif

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Category Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $category->name ?? '') }}" required
                               placeholder="e.g. Options Strategies">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Short description of this category">{{ old('description', $category->description ?? '') }}</textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                FontAwesome Icon Class
                                <a href="https://fontawesome.com/icons?d=gallery&q=chart" target="_blank" class="small ms-1">Browse icons</a>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text" id="iconPreview"><i class="fas fa-tag"></i></span>
                                <input type="text" name="icon" class="form-control" id="iconInput"
                                       value="{{ old('icon', $category->icon ?? '') }}"
                                       placeholder="e.g. fa-chart-line">
                            </div>
                            <small class="text-muted">FontAwesome class without "fas" prefix. e.g. <code>fa-chart-bar</code></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Badge Color</label>
                            <input type="color" name="color" class="form-control form-control-color w-100"
                                   value="{{ old('color', $category->color ?? '#2196F3') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0"
                                   value="{{ old('sort_order', $category->sort_order ?? 0) }}">
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.courses.categories.index') }}" class="btn btn--secondary btn--sm">
                        <i class="las la-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="btn btn--primary btn--sm">
                        <i class="las la-save"></i> {{ $editing ? 'Update' : 'Create' }} Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
// Live icon preview
document.getElementById('iconInput').addEventListener('input', function() {
    var val = this.value.trim();
    var preview = document.getElementById('iconPreview');
    preview.innerHTML = val ? '<i class="fas ' + val + '"></i>' : '<i class="fas fa-tag"></i>';
});
// Init
(function() {
    var val = document.getElementById('iconInput').value.trim();
    if (val) document.getElementById('iconPreview').innerHTML = '<i class="fas ' + val + '"></i>';
})();
</script>
@endpush