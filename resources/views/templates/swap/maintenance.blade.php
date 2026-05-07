@extends($activeTemplate.'layouts.app')
@section('panel')
<section class="maintenance-page flex-column justify-content-center">
    <div class="container">
        <div class="row justify-content-center align-items-center">
            <div class="col-lg-7 text-center">
                <div class="row justify-content-center">
                    <div class="col-xl-10">
                        <h4 class="text--danger">{{ __(@$maintenance->data_values->heading) }}</h4>
                    </div>
                    <div class="col-sm-6 col-8 col-lg-12">
                        <img src="{{ getImage('assets/global/images/maintenance.png') }}" alt="@lang('image')" class="img-fluid mx-auto mb-5">
                    </div>
                </div>
                <div class="mx-auto text-center text--color maintenance-page-color">@php echo $maintenance->data_values->description; @endphp</div>
            </div>
        </div>
    </div>
</section>
@endsection