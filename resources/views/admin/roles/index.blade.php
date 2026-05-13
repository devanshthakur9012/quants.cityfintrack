@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>@lang('Role')</th>
                                <th>@lang('Users')</th>
                                <th>@lang('Permissions')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $role)
                            <tr>
                                <td><span class="fw-bold text-capitalize">{{ $role->name }}</span></td>
                                <td>{{ $role->users_count }}</td>
                                <td>{{ $role->permissions_count }}</td>
                                <td>
                                    <a href="{{ route('admin.roles.edit', $role->id) }}"
                                       class="btn btn-sm btn-outline--primary">
                                        <i class="las la-edit"></i> @lang('Edit')
                                    </a>

                                    @if(!in_array($role->name, ['admin', 'user', 'employee']))
                                    <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline--danger"
                                                onclick="return confirm('Delete this role?')">
                                            <i class="las la-trash"></i> @lang('Delete')
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">@lang('No roles found.')</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($roles->hasPages())
            <div class="card-footer py-4">{{ paginateLinks($roles) }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
    <a href="{{ route('admin.roles.permissions') }}" class="btn btn--secondary">
        <i class="las la-key"></i> @lang('Permissions')
    </a>
    <a href="{{ route('admin.roles.create') }}" class="btn btn--primary">
        <i class="las la-plus"></i> @lang('New Role')
    </a>
@endpush