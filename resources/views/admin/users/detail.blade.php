@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">

        {{-- Action Buttons --}}
        <div class="d-flex flex-wrap gap-3 mb-4">
            <a href="{{ route('admin.report.login.history') }}?search={{ $user->username }}"
               class="btn btn--primary btn--shadow flex-fill">
                <i class="las la-list-alt"></i> @lang('Login History')
            </a>
            {{-- <a href="{{ route('admin.users.notification.log', $user->id) }}"
               class="btn btn--secondary btn--shadow flex-fill">
                <i class="las la-bell"></i> @lang('Notifications')
            </a> --}}
            <a href="{{ route('admin.users.login', $user->id) }}" target="_blank"
               class="btn btn--primary btn--gradi flex-fill">
                <i class="las la-sign-in-alt"></i> @lang('Login as User')
            </a>

            @if($user->status == \App\Constants\Status::USER_ACTIVE)
            <button class="btn btn--warning btn--gradi flex-fill"
                    data-bs-toggle="modal" data-bs-target="#statusModal">
                <i class="las la-ban"></i> @lang('Ban User')
            </button>
            @else
            <button class="btn btn--success btn--gradi flex-fill"
                    data-bs-toggle="modal" data-bs-target="#statusModal">
                <i class="las la-undo"></i> @lang('Unban User')
            </button>
            @endif
        </div>

        {{-- Edit Form --}}
        <div class="card b-radius--10">
            <div class="card-header">
                <h5 class="card-title mb-0">@lang('Edit') – {{ $user->fullname }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.users.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- Basic Info --}}
                    <h6 class="text--primary mb-3">@lang('Basic Information')</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>@lang('Profile Picture')</label>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ getImage(getFilePath('userProfile') . '/' . $user->profile_pic, getFileSize('userProfile')) }}"
                                        alt="profile"
                                        class="rounded-circle"
                                        style="width:70px; height:70px; object-fit:cover;">
                                    <input type="file" name="profile_pic" class="form-control" accept="image/*">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('First Name')</label>
                                <input type="text" name="firstname" class="form-control" value="{{ $user->firstname }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Last Name')</label>
                                <input type="text" name="lastname" class="form-control" value="{{ $user->lastname }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Email')</label>
                                <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Mobile')</label>
                                <div class="input-group">
                                    <span class="input-group-text mobile-code"></span>
                                    <input type="text" name="mobile" id="mobile" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        {{-- <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Telegram Username')</label>
                                <input type="text" name="telegram_username" class="form-control"
                                       value="{{ $user->telegram_username }}">
                            </div>
                        </div> --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>@lang('Country')</label>
                                <select name="country" class="form-control country-select">
                                    @foreach($countries as $key => $country)
                                    <option data-dial="{{ $country->dial_code }}" value="{{ $key }}"
                                        {{ $user->country_code == $key ? 'selected' : '' }}>
                                        {{ $country->country }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Address --}}
                    <h6 class="text--primary mb-3 mt-2">@lang('Address')</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>@lang('Address')</label>
                                <input type="text" name="address" class="form-control" value="{{ @$user->address->address }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>@lang('City')</label>
                                <input type="text" name="city" class="form-control" value="{{ @$user->address->city }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>@lang('State')</label>
                                <input type="text" name="state" class="form-control" value="{{ @$user->address->state }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>@lang('Zip')</label>
                                <input type="text" name="zip" class="form-control" value="{{ @$user->address->zip }}">
                            </div>
                        </div>
                    </div>

                    {{-- Role Assignment --}}
                    <h6 class="text--primary mb-3 mt-2">@lang('Roles')</h6>
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
                                       {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                                <label class="form-check-label text-capitalize" for="role_{{ $role->id }}">
                                    {{ $role->name }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Employee Profile --}}
                    <div id="employee-fields" class="{{ $user->hasRole('employee') ? '' : 'd-none' }}">
                        <hr>
                        <h6 class="text--primary mb-3">@lang('Employee Details')</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Employee Code')</label>
                                    <input type="text" name="employee_code" class="form-control"
                                           value="{{ @$user->employeeProfile->employee_code }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Department')</label>
                                    <input type="text" name="department" class="form-control"
                                           value="{{ @$user->employeeProfile->department }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Designation')</label>
                                    <input type="text" name="designation" class="form-control"
                                           value="{{ @$user->employeeProfile->designation }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Date of Joining')</label>
                                    <input type="date" name="date_of_joining" class="form-control"
                                           value="{{ @$user->employeeProfile->date_of_joining?->format('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Salary')</label>
                                    <input type="number" step="0.01" name="salary" class="form-control"
                                           value="{{ @$user->employeeProfile->salary }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Emergency Contact')</label>
                                    <input type="text" name="emergency_contact" class="form-control"
                                           value="{{ @$user->employeeProfile->emergency_contact }}">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Notes')</label>
                                    <textarea name="notes" class="form-control" rows="3">{{ @$user->employeeProfile->notes }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Verification Toggles --}}
                    <hr>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>@lang('Email Verification')</label>
                            <input type="checkbox" data-width="100%" data-onstyle="-success" data-offstyle="-danger"
                                   data-bs-toggle="toggle" data-on="@lang('Verified')" data-off="@lang('Unverified')"
                                   name="ev" {{ $user->ev ? 'checked' : '' }}>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>@lang('Mobile Verification')</label>
                            <input type="checkbox" data-width="100%" data-onstyle="-success" data-offstyle="-danger"
                                   data-bs-toggle="toggle" data-on="@lang('Verified')" data-off="@lang('Unverified')"
                                   name="sv" {{ $user->sv ? 'checked' : '' }}>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>@lang('2FA')</label>
                            <input type="checkbox" data-width="100%" data-onstyle="-success" data-offstyle="-danger"
                                   data-bs-toggle="toggle" data-on="@lang('Enabled')" data-off="@lang('Disabled')"
                                   name="ts" {{ $user->ts ? 'checked' : '' }}>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary w-100 mt-3">
                        @lang('Save Changes')
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Ban / Unban Modal --}}
<div id="statusModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ $user->status == \App\Constants\Status::USER_ACTIVE ? __('Ban User') : __('Unban User') }}
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <form action="{{ route('admin.users.status', $user->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    @if($user->status == \App\Constants\Status::USER_ACTIVE)
                        <p>@lang('Banning this user will prevent them from accessing their dashboard.')</p>
                        <div class="form-group">
                            <label>@lang('Reason') <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>
                    @else
                        <p>@lang('Ban reason:') <strong>{{ $user->ban_reason }}</strong></p>
                        <h5 class="text-center mt-3">@lang('Unban this user?')</h5>
                    @endif
                </div>
                <div class="modal-footer">
                    @if($user->status == \App\Constants\Status::USER_ACTIVE)
                        <button type="submit" class="btn btn--primary w-100">@lang('Confirm Ban')</button>
                    @else
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                        <button type="submit" class="btn btn--primary">@lang('Yes, Unban')</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function ($) {
    "use strict";

    // Toggle employee fields
    function toggleEmployeeFields() {
        const hasEmployee = [...document.querySelectorAll('.role-checkbox:checked')]
            .some(cb => cb.dataset.role === 'employee');
        document.getElementById('employee-fields').classList.toggle('d-none', !hasEmployee);
    }
    document.querySelectorAll('.role-checkbox').forEach(cb => cb.addEventListener('change', toggleEmployeeFields));

    // Country dial code
    const mobileEl = $('.mobile-code');

    $('select[name=country]').on('change', function () {
        mobileEl.text('+' + $('select[name=country] :selected').data('dial'));
    }).trigger('change');

    // Strip dial code from stored mobile number
    const dialCode    = $('select[name=country] :selected').data('dial');
    const storedMobile = '{{ $user->mobile }}';
    $('#mobile').val(storedMobile.replace(dialCode, ''));

})(jQuery);
</script>
@endpush