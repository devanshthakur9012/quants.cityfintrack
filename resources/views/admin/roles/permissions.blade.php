@extends('admin.layouts.app')

@section('panel')

{{-- Inline Create Permission Form --}}
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card b-radius--10">
            <div class="card-header"><h5 class="card-title mb-0">@lang('Add Permission')</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.roles.permissions.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>@lang('Group') <small class="text-muted">(e.g. users, reports)</small></label>
                                <input type="text" name="group" class="form-control @error('group') is-invalid @enderror"
                                       value="{{ old('group') }}" placeholder="users" required>
                                @error('group') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>@lang('Action') <small class="text-muted">(e.g. edit, delete)</small></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="edit" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn--primary w-100 mb-3">
                                <i class="las la-plus"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">@lang('Will be saved as') <code>group.action</code></small>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>@lang('Permission')</th>
                                <th>@lang('Group')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($permissions as $perm)
                            <tr>
                                <td><code>{{ $perm->name }}</code></td>
                                <td>{{ explode('.', $perm->name)[0] }}</td>
                                <td>
                                    <form action="{{ route('admin.roles.permissions.destroy', $perm->id) }}"
                                          method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline--danger"
                                                onclick="return confirm('Delete this permission?')">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">@lang('No permissions found.')</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($permissions->hasPages())
            <div class="card-footer py-4">{{ paginateLinks($permissions) }}</div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('breadcrumb-plugins')
    <a href="{{ route('admin.roles.index') }}" class="btn btn--secondary">
        <i class="las la-arrow-left"></i> @lang('Back to Roles')
    </a>
@endpush