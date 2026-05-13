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
                                <th>@lang('Employee')</th>
                                <th>@lang('Employee Code')</th>
                                <th>@lang('Department')</th>
                                <th>@lang('Designation')</th>
                                <th>@lang('Date of Joining')</th>
                                <th>@lang('Roles')</th>
                                <th>@lang('Status')</th>
                                <th>@lang('Action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ getImage(getFilePath('userProfile') . '/' . $user->profile_pic, getFileSize('userProfile')) }}"
                                            alt="avatar"
                                            class="rounded-circle"
                                            style="width:40px; height:40px; object-fit:cover;">
                                        <div>
                                            <span class="fw-bold">{{ $user->fullname }}</span><br>
                                            <small>
                                                <a href="{{ route('admin.users.detail', $user->id) }}">{{ $user->username }}</a>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code>{{ @$user->employeeProfile->employee_code ?? '—' }}</code>
                                </td>
                                <td>{{ @$user->employeeProfile->department ?? '—' }}</td>
                                <td>{{ @$user->employeeProfile->designation ?? '—' }}</td>
                                <td>
                                    @if(@$user->employeeProfile->date_of_joining)
                                        {{ $user->employeeProfile->date_of_joining->format('d M, Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @foreach($user->roles as $role)
                                        <span class="badge badge--primary text-capitalize">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($user->status == \App\Constants\Status::USER_ACTIVE)
                                        <span class="badge badge--success">@lang('Active')</span>
                                    @else
                                        <span class="badge badge--danger">@lang('Banned')</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.detail', $user->id) }}"
                                       class="btn btn-sm btn-outline--primary">
                                        <i class="las la-desktop"></i> @lang('Detail')
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    {{ __($emptyMessage ?? 'No employees found.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($employees->hasPages())
            <div class="card-footer py-4">
                {{ paginateLinks($employees) }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
    <x-search-form placeholder="Username / Email" />
    <a href="{{ route('admin.users.create') }}" class="btn btn--primary">
        <i class="las la-plus"></i> @lang('Add Employee')
    </a>
@endpush