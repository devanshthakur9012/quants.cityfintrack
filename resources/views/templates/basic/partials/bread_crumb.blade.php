@php $breadCrumb = getContent('bread_crumb.content', true); @endphp

<!-- inner hero section start -->
<section class="inner-hero bg_img" style="background-image: url('{{ getImage('assets/images/frontend/bread_crumb/' .@$breadCrumb->data_values->image, '1920x510') }}');">
    <div class="container">
        <div class="row justify-content-center">
            @if(@$userGlblNameData!=null)
                <div class="col-lg-12 col-12">
                    <h5 class="text-white text-start mb-0">Welcome <span class="text--base">{{$userGlblNameData->firstname.' '.$userGlblNameData->lastname}}</span> </h5>
                </div>
            @endif
            <div class="col-lg-6 text-center">
                <h2 class="title text-white">{{ __($pageTitle) }}</h2>
                <ul class="page-breadcrumb justify-content-center">
                    <li><a href="{{ route('home') }}">@lang('Home')</a></li>
                    <li>{{ __($pageTitle) }}</li>
                </ul>
            </div>
          
        </div>
    </div>
</section>
<!-- inner hero section end -->

<section class="nifty--section" id="nifty-section">
   
</section>

@push('script')
<script>
    function fetchMarketData(){
        $.get('{{route("get-market-data")}}',function(data){
            if(data.length){
                var str = `<div class="container-fluid">
                    <div class="row g-1">`;
                    for(var i in data){
                        var closePrice = data[i].close;
                        var latestPrice = data[i].ltp;
                        var change = latestPrice - closePrice;
                        if(change > 0){
                            str+=`<div class="col-lg-4 col-md-4 col-4"><p >${data[i].tradingSymbol} <span class="text-light">${latestPrice} <em class="text-success">+${change.toFixed(2)} (${data[i].percentChange}%)</em></span></p></div>`;
                        }else{
                            str+=`<div class="col-lg-4 col-md-4 col-4"><p >${data[i].tradingSymbol} <span class="text-light ">${latestPrice} <em class="text-danger">-${change.toFixed(2)} (${data[i].percentChange}%)</em></span></p></div>`;
                        }

                        // str+=`<div class="col-lg-4 col-md-4 col-4"><p >${data[i].tradingSymbol} <span class="text--base">${latestPrice}</span></p></div>`;
                    }
                    str+=`</div></div>`;
                    $("#nifty-section").html(str);
            }else{
                $("#nifty-section").html('');
            }
        });
    }
    $(document).ready(function(){
        fetchMarketData();
        setInterval(() => {
            fetchMarketData();
        }, 10 * 1000);
        
    });
</script>
@endpush
