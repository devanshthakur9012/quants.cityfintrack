@php
    $package = getContent('package.content', true);

    if( request()->routeIs('home') ){
        $packages = App\Models\Package::active()->take(3)->get();
    }else{
        $packages = App\Models\Package::active()->paginate(getPaginate());
    }

@endphp

<!-- Plan Section -->
<div class="plan-section pt-100 pb-100 position-relative overflow-hidden bg_img bg_fixed bg--light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-xxl-8">
                <div class="section-header text-center">
                    <h2 class="section-header__title">{{ __(@$package->data_values->heading) }}</h2>
                    <p class="section-header__text">{{ __(@$package->data_values->subheading) }}</p>
                </div>
            </div>
        </div>
        <div class="row gy-4 justify-content-center">
            @foreach($packages as $package)
                <div class="col-xl-3 col-md-6">
                    <div class="plan-item">
                        <div class="plan-item__header">
                            <h4 class="plan-name mb-3 d-flex align-items-center gap-3">
                                <span class="plan-icon"><i class="fas la-hand-point-right"></i></span>
                                {{ __($package->name) }}
                            </h4>
                            <h4 class="plan-price fw-semibold"><sub class="pre-sub">{{ $general->cur_sym }}</sub>{{ showAmount($package->price, 0) }} <sub>/ @lang('Products')</sub>
                            </h4>
                        </div>
                        <div class="plan-item__body">
                            <ul class="list list-style-check">
                                @foreach($package->features as $feature)
                                    <li class="active">{{ __($feature) }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="plan-item__footer">
                            <a href="javascript:void(0)" class="btn btn--base w-100 btn--sm text-center py-3 chooseBtn"
                            @auth
                                data-id="{{$package->id}}"
                                data-name="{{$package->name}}"
                                data-price="{{ showAmount($package->price, 2) }}"
                                data-validity="{{ $package->validity }}"
                            @endauth
                            >
                                @lang('Choose Plan')
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @if(!request()->routeIs('home'))
        <div class="pt-50 d-flex text-center justify-content-center">
            {{ $packages->links() }}
        </div>
    @endif
</div>
<!-- Plan Section -->

@auth
<div class="modal fade cmn--modal" id="chooseModal">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title method-name">@lang('Are you sure to buy') <span class="modal-title-text"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{route('user.purchase.package')}}" method="post">
                @csrf
                <div class="modal-body pt-0">
                    <div class="form-group">
                        <input type="hidden" name="id">
                    </div>
                    <ul class="list-group list-group-flush mt-3">
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
                    <button type="button" class="btn btn--dark btn--sm" data-bs-dismiss="modal">@lang('Close')</button>
                    <div class="prevent-double-click">
                        <button type="submit" class="btn btn--sm btn--success">@lang('Confirm')</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@else
<div class="modal fade cmn--modal" id="chooseModal">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0">
            <div class="modal-header bg--base">
                <h5 class="modal-title method-name">@lang('Please login before buy a Product')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">@lang('To purchase a Product, you have to login into your account')</p>
                <div class="form-group">
                    <a href="{{ route('user.login') }}" class="btn btn--sm btn--base w-100">@lang('Login')</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endauth

@push('script')
<script>

    (function ($) {
        "use strict";

        $('.chooseBtn').on('click', function () {
            var modal = $('#chooseModal');

            if(@json( auth()->user() )){
                modal.find('.modal-title-text').text($(this).data('name'));
                modal.find('.packageName').text($(this).data('name'));
                modal.find('.packagePrice').text($(this).data('price')+' '+@json( __($general->cur_text) ));
                modal.find('.packageValidity').text($(this).data('validity')+' Days');
                modal.find('input[name=id]').val($(this).data('id'));
            }

            modal.modal('show');
        });

    })(jQuery);

</script>
@endpush
