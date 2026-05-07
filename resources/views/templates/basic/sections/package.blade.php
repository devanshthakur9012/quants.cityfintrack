@php
    $package = getContent('package.content', true);

    if (request()->routeIs('home')) {
        $packages = App\Models\Package::active()
            ->take(3)
            ->get();
    } else {
        $packages = App\Models\Package::active()->paginate(getPaginate());
    }

@endphp

<!-- packaage section start -->
<section class="pt-100 pb-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="section-header text-center wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
                    <div class="section-subtitle">{{ __(@$package->data_values->heading) }}</div>
                    <h2 class="section-title">{{ __(@$package->data_values->subheading) }}</h2>
                </div>
            </div>
        </div><!-- row end -->
        <div class="row gy-4 justify-content-center">
            <div class="col-lg-10">
                <div class="row gy-4 justify-content-center">
                    @foreach ($packages as $package)
                        <div class="col-xl-4 col-md-6 wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
                            <div class="package-card">
                                <h4 class="package-card__name">{{ __($package->name) }}</h4>
                                <p class="">Min. Investment : <span class="text--base">{{ $package->min_investment }}</span></p>
                                <ul class="package-card__feature-list mt-4">
                                    <li>Horizon : <span class="text--base">{{ $package->time_horizon }}</span></li>
                                    <li>Asset Type : <span class="text--base">{{ $package->asset_type }}</span></li>
                                    <li>Risk Appetite : <span class="text--base">{{ $package->risk_appetite }}</span></li>
                                </ul>
                                <div class="mt-4">
                                    <a href="{{url('/package-details/'.$package->id.'')}}" class="btn btn-outline--base ">
                                        @lang('View Product')
                                    </a>
                                    {{-- <a href="{{url('/package-details/'.$package->id.'')}}" class="btn btn-outline--base chooseBtn"
                                        @auth data-id="{{ $package->id }}"
                                        data-name="{{ $package->name }}"
                                        data-price="{{ showAmount($package->min_investment, 2) }}"
                                        data-validity="{{ $package->validity }}" @endauth>
                                        @lang('View Product')
                                    </a> --}}
                                </div>
                            </div><!-- package-card end -->
                        </div>
                    @endforeach
                </div><!-- row end -->
            </div>
            @if (!request()->routeIs('home'))
                <div class="pt-50 d-flex text-center justify-content-center">
                    {{ $packages->links() }}
                </div>
            @endif

        </div><!-- row end -->
    </div>
</section>
<!-- packaage section end -->

@auth
    <div class="modal fade cmn--modal" id="chooseModal">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title method-name">@lang('Are you sure to buy') <span class="modal-title-text"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('user.purchase.package') }}" method="post">
                    @csrf
                    <div class="modal-body pt-0">
                        <div class="form-group">
                            <input type="hidden" name="id">
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                @lang('Product') <span class="packageName"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                @lang('Price') <span class="packagePrice"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                @lang('Validity') <span class="packageValidity"></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                @lang('Your Balance')
                                <span>{{ showAmount(auth()->user()->balance, 2) }} {{ __($general->cur_text) }} </span>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
                        <div class="prevent-double-click">
                            <button type="submit" class="btn btn-sm btn--success">@lang('Confirm')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@else
    <div class="modal fade cmn--modal" id="chooseModal">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header"> 
                    <h5 class="modal-title method-name">@lang('Please login before buy a product')</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">@lang('To purchase a package, you have to login into your account')</p>
                    <div class="form-group">
                        <a href="{{ route('user.login') }}" class="btn btn-sm btn--success w-100">@lang('Login')</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endauth

@push('script')
    <script>
        (function($) {
            "use strict";

            $('.chooseBtn').on('click', function() {
                var modal = $('#chooseModal');

                if (@json(auth()->user())) {
                    modal.find('.modal-title-text').text($(this).data('name'));
                    modal.find('.packageName').text($(this).data('name'));
                    modal.find('.packagePrice').text($(this).data('price') + ' ' + @json(__($general->cur_text)));
                    modal.find('.packageValidity').text($(this).data('validity') + ' Days');
                    modal.find('input[name=id]').val($(this).data('id'));
                }

                modal.modal('show');
            });

        })(jQuery);
    </script>
@endpush
