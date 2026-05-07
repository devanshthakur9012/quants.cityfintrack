@php
    $counter = getContent('counter.content', true);
    $counters = getContent('counter.element', orderById:true);
@endphp
<!-- Counter Section -->
<div class="counter-section pt-80 pb-80 bg_img position-relative bg_fixed" style="background: url('{{ getImage('assets/images/frontend/counter/' .@$counter->data_values->image, '1920x840') }}') ;">
    <div class="container">
        <div class="row gy-5">
            @foreach($counters as $counter)
                <div class="col-lg-3 col-sm-6">
                    <div class="counter-item">
                        <div class="counter-item__icon">
                            @php echo @$counter->data_values->icon; @endphp
                        </div>
                        <div class="counter-item__content">
                            <h4 class="counter text-white mb-2">{{ __(@$counter->data_values->digit) }}</h4>
                            <h6 class="text-white fw-normal">{{ __(@$counter->data_values->title) }}</h6>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
<!-- Counter Section -->
