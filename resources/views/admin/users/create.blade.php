@extends('admin.layouts.app')

@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card b-radius--10">
            <div class="card-header">
                <h5 class="card-title mb-0">@lang('Create New User')</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data">
                    @csrf

                    {{-- Basic Info --}}
                    <h6 class="text--primary mb-3">@lang('Basic Information')</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('First Name') <span class="text-danger">*</span></label>
                                <input type="text" name="firstname" class="form-control @error('firstname') is-invalid @enderror"
                                       value="{{ old('firstname') }}" required>
                                @error('firstname') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Last Name') <span class="text-danger">*</span></label>
                                <input type="text" name="lastname" class="form-control @error('lastname') is-invalid @enderror"
                                       value="{{ old('lastname') }}" required>
                                @error('lastname') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Email') <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email') }}" required>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Mobile') <span class="text-danger">*</span></label>
                                <input type="text" name="mobile" class="form-control @error('mobile') is-invalid @enderror"
                                       value="{{ old('mobile') }}" required>
                                @error('mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Profile Picture')</label>
                                <input type="file" name="profile_pic" class="form-control @error('profile_pic') is-invalid @enderror"
                                    accept="image/*">
                                @error('profile_pic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Password') <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Confirm Password') <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    {{-- Role Assignment --}}
                    <h6 class="text--primary mb-3 mt-3">@lang('Assign Roles') <span class="text-danger">*</span></h6>
                    <div class="row">
                        @foreach($roles as $role)
                        <div class="col-md-3 col-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input role-checkbox"
                                       type="checkbox"
                                       name="roles[]"
                                       value="{{ $role->id }}"
                                       id="role_{{ $role->id }}"
                                       data-role="{{ $role->name }}"
                                       {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}>
                                <label class="form-check-label text-capitalize" for="role_{{ $role->id }}">
                                    {{ $role->name }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @error('roles') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    {{-- Employee Profile (shown only when 'employee' role is checked) --}}
                    <div id="employee-fields" class="d-none">
                        <hr>
                        <h6 class="text--primary mb-3">@lang('Employee Details')</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Employee Code') <span class="text-danger">*</span></label>
                                    <input type="text" name="employee_code"
                                           class="form-control @error('employee_code') is-invalid @enderror"
                                           value="{{ old('employee_code') }}">
                                    @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Department')</label>
                                    <input type="text" name="department" class="form-control"
                                           value="{{ old('department') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Designation')</label>
                                    <input type="text" name="designation" class="form-control"
                                           value="{{ old('designation') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Date of Joining')</label>
                                    <input type="date" name="date_of_joining" class="form-control"
                                           value="{{ old('date_of_joining') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Salary')</label>
                                    <input type="number" step="0.01" name="salary" class="form-control"
                                           value="{{ old('salary') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Emergency Contact')</label>
                                    <input type="text" name="emergency_contact" class="form-control"
                                           value="{{ old('emergency_contact') }}">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Notes')</label>
                                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary w-100 mt-3">
                        <i class="las la-check-circle"></i> @lang('Create User')
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    "use strict";

    function toggleEmployeeFields() {
        const hasEmployee = [...document.querySelectorAll('.role-checkbox:checked')]
            .some(cb => cb.dataset.role === 'employee');
        document.getElementById('employee-fields').classList.toggle('d-none', !hasEmployee);
    }

    document.querySelectorAll('.role-checkbox').forEach(cb => {
        cb.addEventListener('change', toggleEmployeeFields);
    });

    // Run on page load (handles old() values on validation fail)
    toggleEmployeeFields();
})();
</script>
@endpush