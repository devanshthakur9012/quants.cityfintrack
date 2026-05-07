@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <form action="" class="transparent-form mb-3">
            <div class="row">
                <div class="col-lg-3 form-group">
                    <label>@lang('Stock Name')</label>
                    <select name="symbols" class="form--control" id="symbols">
                        <option value="">Select Symbols</option>
                        {{-- @if (\Cache::has('allSymbols'))
                        @php
                            $allData =\Cache::get('allSymbols');
                            sort($allData);
                        @endphp
                            @foreach ($allData as $item)
                                <option value="{{$item}}" {{$searchSymbol == $item ? "selected" : ""}}>{{$item}}</option>
                            @endforeach
                        @endif --}}
                        @if (count($allSymbols))
                            @foreach ($allSymbols as $item)
                                <option value="{{$item}}" {{$searchSymbol == $item ? "selected" : ""}}>{{$item}}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div class="col-lg-3 form-group mt-auto">
                    <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i> @lang('Filter')</button>
                </div>
                <div class="col-lg-3 col-md-3 col-6 form-group mt-auto">
                    <a href="{{url('/user/paper-trading')}}" class="btn btn--base w-100"><i class="las la-redo-alt"></i> Refresh</a>
                </div>
            </div>
        </form>
        <div class="row" >
            <div class="col-lg-12">
                <div class="custom--card" id="pst_hre">
                    <div class="card-body p-0">
                        <div class="table-responsive--md table-responsive">
                            <table class="table custom--table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>@lang('TXN Date')</th>
                                        <th>@lang('Symbol')</th>
                                        <th>@lang('Expiry')</th>
                                        <th>@lang('TXN Type')</th>
                                        <th>@lang('Lot Size')</th>
                                        <th>@lang('Atm Status')</th>
                                        <th>@lang('Ce')</th>
                                        <th>@lang('Pe')</th>
                                        <th>@lang('Ce Entry Price')</th>
                                        <th>@lang('pe Entry Price')</th>
                                        <th>@lang('Ce Ltp')</th>
                                        <th>@lang('Pe Ltp')</th>
                                        <th>@lang('Total Premium')</th>
                                        <th>@lang('Total Premium *')</th>
                                        <th>@lang('MTM')</th>
                                        <th>@lang('Target Status')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($paperTrade as $trade)
                                        <tr>
                                            <td>
                                                <strong>{{showDate($trade->date)}}</strong>
                                            </td>
                                            <td>{{ $trade->symbol }}</td>
                                            <td>{{ showDate($trade->expiry) }}</td>
                                            <td>{{ $trade->transaction_type }}</td>
                                            <td>{{ $trade->lot_size }}</td>
                                            <td>{{ $trade->atm_status }}</td>
                                            <td>{{ $trade->ce }}</td>
                                            <td>{{ $trade->pe }}</td>
                                            @php
                                                $ce_textColor = "text-success";
                                                if ($trade->ce_entry_price > $trade->ce_ltp ) {
                                                    $ce_textColor = "text-danger";
                                                }

                                                $pe_textColor = "text-success";
                                                if ($trade->pe_entry_price > $trade->pe_ltp ) {
                                                    $pe_textColor = "text-danger";
                                                }
                                            @endphp
                                            <td>{{ $trade->ce_entry_price }}</td>
                                            <td>{{ $trade->pe_entry_price }}</td>
                                            <td class="{{$ce_textColor}}">{{ $trade->ce_ltp }}</td>
                                            <td class="{{$pe_textColor}}">{{ $trade->pe_ltp }}</td>
                                            <td>{{ $trade->combined_premium_ce_pe }}</td>
                                            <td>{{ $trade->combined_premium_mul_lot_size }}</td>
                                            @php
                                                $mtm = (($trade->ce_ltp+$trade->pe_ltp)*$trade->lot_size)-($trade->combined_premium_mul_lot_size);
                                                $target = "";
                                            @endphp
                                            <td>{{ round($mtm,2) }}</td>
                                            <td>{{ $trade->target_status }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @isset($paperTrade)
            @if (count($paperTrade))
                <div class="mt-4 justify-content-center d-flex">
                    {{ paginateLinks($paperTrade)}}
                </div>
            @endif
        @endisset
    </div>
</section>
@endsection

@push('script')
<script>
    $(document).ready(function(){
        function reloadData(){
            
            $.get('{!!url("user/paper-trading-ajax?".(isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : ''))!!}',function(data){
                // console.log(data);
                $("#pst_hre").html(data);
            });
        }
        setInterval(() => {
            reloadData();
        }, 2000);//call every 1/2 minute
    });
</script>
@endpush


