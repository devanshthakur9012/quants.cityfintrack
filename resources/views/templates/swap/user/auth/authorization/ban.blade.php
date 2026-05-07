@extends($activeTemplate .'layouts.frontend')
@section('content')
<section class="pt-100 pb-100 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="text-center mb-2">
                    <a href="{{ route('home') }}" class="fw-bold home-link"><i class="las la-sign-out-alt"></i> @lang('Logout')</a>
                </div>
                <div class="custom--card">
                    <div class="card-header">
                        <h5 class="card-title text-center">@lang('You are banned')</h5>
                    </div>
                    <div class="card-body">
                        <strong class="mb-1">@lang('Reason'):</strong>
                        <p>{{ $user->ban_reason }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
 