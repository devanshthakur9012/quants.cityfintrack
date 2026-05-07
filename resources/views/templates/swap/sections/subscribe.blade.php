@php
    $subscribe = getContent('subscribe.content', true);
@endphp

<!-- Subscription Section -->
<div class="subscription-section position-relative pt-80 pb-80 bg_img bg_fixed" style="background: url('{{ getImage('assets/images/frontend/subscribe/' .@$subscribe->data_values->image, '350x420') }}') center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7 col-md-10">
                <div class="subscription-wrapper text-center">
                    <h3 class="text-white mb-3">{{ __(@$subscribe->data_values->heading) }}</h3>
                    <p class="text-white mb-4 pb-3">{{ __(@$subscribe->data_values->subheading) }}</p>
                    <form action="#" class="subscription-form disabled-false">
                        @csrf
                        <div class="input-group">
                            <input type="email" class="form-control form--control" placeholder="@lang('Enter your mail')" name="email">
                            <button class="btn btn--base">@lang('Subscribe')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Subscription Section -->

@push('style')
    <style>
        #email-error {
            position: absolute;
            z-index: -1;
            opacity: 0;
            visibility: hidden;
        }
    </style>
@endpush

@push('script')

    <script>
        (function($){

            "use strict";

            var formEl = $(".subscription-form");

            formEl.on('submit', function(e){
                e.preventDefault();
                var data = formEl.serialize();

                if(!formEl.find('input[name=email]').val()){
                    return notify('info', 'Email field is required');
                }

                $.ajax({
                url:"{{ route('subscribe') }}",
                method:'post',
                data:data,

                success:function(response){
                    if(response.success){
                        formEl.find('input[name=email]').val('')
                        notify('success', response.message);
                    }else{
                        $.each(response.error, function( key, value ) {
                            notify('error', value);
                        });
                    }
                },
                error:function(error){
                    console.log(error)
                }

                });
            });

        })(jQuery);
    </script>

@endpush
