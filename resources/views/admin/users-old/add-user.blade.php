@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.store-user') }}">
                    @csrf

                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="firstname" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lastname" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" name="mobile" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn--primary">Create User</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role-select');
    const traderWrapper = document.getElementById('trader-select-wrapper');

    roleSelect.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const roleName = selectedOption.getAttribute('data-role-name');

        if (roleName === 'investor') {
            traderWrapper.classList.remove('d-none');
        } else {
            traderWrapper.classList.add('d-none');
        }
    });
});
</script>
@endpush