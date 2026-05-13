@extends('admin.layouts.app')

@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card b-radius--10">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ $pageTitle }}</h5>
            </div>
            <div class="card-body">
                @php
                    $isEdit  = isset($role);
                    $action  = $isEdit
                        ? route('admin.roles.update', $role->id)
                        : route('admin.roles.store');
                    $assigned = $isEdit ? $role->permissions->pluck('id')->toArray() : old('permissions', []);
                @endphp

                <form method="POST" action="{{ $action }}">
                    @csrf

                    <div class="form-group">
                        <label>@lang('Role Name') <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', @$role->name) }}"
                               placeholder="e.g. hr, manager, support"
                               {{ in_array(@$role->name, ['admin', 'user']) ? 'readonly' : '' }}
                               required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <h6 class="text--primary mt-4 mb-3">@lang('Permissions')</h6>

                    {{-- "Select All" toggle --}}
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="select-all">
                        <label class="form-check-label fw-bold" for="select-all">@lang('Select All')</label>
                    </div>

                    @foreach($permissions as $group => $perms)
                    <div class="mb-3">
                        <p class="fw-bold text-capitalize mb-1 border-bottom pb-1">{{ $group }}</p>
                        <div class="row">
                            @foreach($perms as $perm)
                            <div class="col-md-4 col-6">
                                <div class="form-check mb-1">
                                    <input class="form-check-input perm-checkbox"
                                           type="checkbox"
                                           name="permissions[]"
                                           value="{{ $perm->id }}"
                                           id="perm_{{ $perm->id }}"
                                           {{ in_array($perm->id, $assigned) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="perm_{{ $perm->id }}">
                                        {{ last(explode('.', $perm->name)) }}
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn--primary flex-fill">
                            <i class="las la-save"></i> @lang('Save Role')
                        </button>
                        <a href="{{ route('admin.roles.index') }}" class="btn btn--dark flex-fill">
                            @lang('Cancel')
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
document.getElementById('select-all').addEventListener('change', function () {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush