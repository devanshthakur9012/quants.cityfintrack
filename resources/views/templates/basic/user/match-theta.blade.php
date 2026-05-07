@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-100 pb-100">
    <div class="container-fluid mb-1">
        <div class="custom--nav-tabs border-0 mb-3">
            <ul class="nav d-flex justify-content-start">
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/user/match-delta') }}">DELTA</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/user/match-iv') }}">IV</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/user/match-premium') }}">PREMIUM</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{ url('/user/match-theta') }}">THETA</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="container-fluid" id="pst_hre">
        <div class="row" >
            <div class="col-lg-12">
                <div class="custom--card" id="pst_hre">
                    <div class="card-body p-0">
                        <div class="table-responsive--md table-responsive">
                            <table class="table custom--table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>@lang('Date')</th>
                                        <th>@lang('Symbol')</th>
                                        <th>@lang('Entry Time')</th>
                                        <th>@lang('Exit Time')</th>
                                        <th>@lang('Trade Type')</th>
                                        <th>@lang('CE')</th>
                                        <th>@lang('PE')</th>
                                        <th>@lang('CE ATM')</th>
                                        <th>@lang('PE ATM')</th>
                                        {{-- <th>@lang('CE Delta')</th>
                                        <th>@lang('PE Delta')</th> --}}
                                        <th>@lang('CE Entry Price')</th>
                                        <th>@lang('PE Entry Price')</th>
                                        <th>@lang('Difference')</th>
                                        <th>@lang('CE LTP')</th>
                                        <th>@lang('PE LTP')</th>
                                        <th>@lang('TOTAL PREMIUM')</th>
                                        <th>@lang('MTM')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($data['paperTrade'] as $trade)

                                        @php
                                            $ce_textColor = "text-success";
                                            if ($trade->ce_entry_price > $trade->ce_ltp ) {
                                                $ce_textColor = "text-danger";
                                            }

                                            $pe_textColor = "text-success";
                                            if ($trade->pe_entry_price > $trade->pe_ltp ) {
                                                $pe_textColor = "text-danger";
                                            }

                                            $mtm = "";
                                            $target = "";

                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{showDate($trade->date)}}</strong>
                                            </td>
                                            <td>{{ $trade->symbol }}</td>
                                            <td>{{$trade->entry_time}}</td>
                                            <td>{{$trade->exit_time}}</td>
                                            <td>{{$trade->trade_type}}</td>
                                            <td>{{$trade->ce}}</td>
                                            <td>{{$trade->pe}}</td>
                                            <td>{{$trade->ce_atm}}</td>
                                            <td>{{$trade->pe_atm}}</td>
                                            {{-- <td>{{$trade->ce_delta}}</td>
                                            <td>{{$trade->pe_delta}}</td> --}}
                                            <td>{{$trade->ce_entry_price}}</td>
                                            <td>{{$trade->pe_entry_price}}</td>
                                            <td>{{$trade->difference}}</td>
                                            <td class="{{$ce_textColor}}">{{$trade->ce_ltp}}</td>
                                            <td class="{{$pe_textColor}}">{{$trade->pe_ltp}}</td>
                                            <td>{{$trade->combined_premium}}</td>
                                            @php $mtm = (($trade->ce_ltp+$trade->pe_ltp))-($trade->combined_premium); @endphp
                                            <td>{{ round($mtm,2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted text-center" colspan="100%">NO DATA</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- @isset($paperTrade)
            @if (count($paperTrade))
                <div class="mt-4 justify-content-center d-flex">
                    {{ paginateLinks($paperTrade)}}
                </div>
            @endif
        @endisset --}}
    </div>
</section>
@endsection

@push('script')
<script>
    $(document).ready(function(){
        function reloadData(){
            
            $.get('{!!url("user/match-theta-ajax?".(isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : ''))!!}',function(data){
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


