@php
    $subscribe = getContent('subscribe.content', true);
@endphp

<!-- subscribe section start -->
<section class="subscribe-section section--bg">
    <div class="container">
        <div class="row gy-4 align-items-center">
        <div class="col-lg-6 text-lg-start text-center wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.3s">
            <h2>{{ __(@$subscribe->data_values->heading) }}</h2>
        </div>
        <div class="col-lg-6 wow fadeInUp" data-wow-duration="0.5" data-wow-delay="0.5s">
            <form class="subscribe-form disabled-false">
                @csrf
                <div class="custom-icon-field">
                    <i class="las la-envelope"></i>
                    <input type="email" name="email" autocomplete="off" class="form--control" placeholder="@lang('Enter email address')">
                </div>
                <button type="submit" class="subscribe-btn">@lang('Subscribe') <i class="lab la-telegram-plane"></i></button>
            </form>
        </div>
        </div>
    </div>
</section>
<!-- subscribe section end -->

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

            var formEl = $(".subscribe-form");

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

