@extends($activeTemplate.'layouts.master')

@section('content')
<div class="pt-100 pb-100">
    <div class="container content-container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="custom--card">
                    <div class="card-body p-4">

                        <div class="text-center mb-4">
                            <div class="user-avatar mx-auto mb-3">
                                {{ strtoupper(substr($user->firstname, 0, 1)) }}{{ strtoupper(substr($user->lastname, 0, 1)) }}
                            </div>
                            <h5 class="mb-1">{{ $user->firstname }} {{ $user->lastname }}</h5>
                            <p class="text-muted mb-0 small">@lang('Use the details below to log in to the system.')</p>
                        </div>

                        <hr class="mb-4">

                        <form action="#" class="transparent-form">
                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="form-label">@lang('User Code')</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form--control referralURL"
                                            value="{{ $user->username }}" readonly>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">@lang('User Email')</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form--control referralURL"
                                            value="{{ $user->email }}" readonly>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">@lang('First Name')</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form--control referralURL"
                                            value="{{ $user->firstname }}" readonly>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">@lang('Last Name')</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form--control referralURL"
                                            value="{{ $user->lastname }}" readonly>
                                    </div>
                                </div>

                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .user-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--base-color, #6366f1), #818cf8);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 600;
        letter-spacing: 1px;
    }
</style>
@endsection