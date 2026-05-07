@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-50 pb-50">
    <div class="container content-container">
        <!-- Form to filter by date -->
        <form action="{{ url()->current() }}" method="GET" class="transparent-form mb-3">
            <div class="row">
              <div class="col-lg-3 form-group">
                    <label for="factor_symbol">Symbol Name</label>
                    <select name="factor_symbol" class="form--control" id="stock_name">
                        <option value="">Select Symbol  Name</option>     
                        @foreach($symbolArr as $symbol)
                         <option value="{{$symbol}}" {{request('factor_symbol')==$symbol ? 'selected':''}}>{{$symbol}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 form-group">
                    <label for="factor_date">Choose Date</label>
                    <input type="date" name="factor_date" class="form--control" id="factor_date" 
                           value="{{ $today }}" max="{{ date('Y-m-d') }}" />
                </div>
                <div class="col-lg-2 form-group mt-auto">
                    <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i> Filter</button>
                </div>
                <div class="col-lg-2 col-md-3 col-6 form-group mt-auto">
                    <a href="{{ url('/user/paper-x-factor-combined') }}" class="btn btn--base w-100"><i class="las la-redo-alt"></i> Refresh</a>
                </div>
            </div>
        </form>

        <div id="pst_hre">
        @forelse($paperFactor as $value)
            @php  
                $revArr = json_decode($value->json_data,true);
                if(!empty(request('factor_symbol'))){
                    $revArr = array_filter($revArr, function ($val) {
                        return $val['config_symbol'] == request('factor_symbol');
                    });
                }
    
                $Investment = array_reduce($revArr, function ($carry, $val) {
                    return $carry + $val['investment'];
                }, 0);
    
                $profitLoss = array_reduce($revArr, function ($carry, $val) {
                    return $carry + $val['profit'];
                }, 0);
            
            @endphp
            <div class="row mb-3">
                <div class="col-lg-12">
                    
                    <div class="custom--card">
                        <div class="card-body p-0">
                            <div class="table-responsive--md table-responsive">
                                <table class="table custom--table text-nowrap">
                                    <thead>
                                         <tr style="background:#0d222b;">
                                             <th style="font-size:12px;">Last Updated: {{date("d M Y H:i:s",strtotime($value->updated_at))}}</th>
                                             <th style="font-size:16px;">No of Trades: {{count($revArr) }}</th>
                                            <th colspan="5" style="font-size:16px;" class="text-white text-end">Investment Amount</th>
                                            <th class="text-success fw-bolder" style="font-size:16px;">
                                                ₹ {{ $Investment }}
                                            </th>
                                            <th class="text-white" style="font-size:16px;">Profit&Loss</th>
                                            <th class="fw-bolder {{$value->total_profit > 0 ? 'text-success':'text-danger'}}" style="font-size:16px;">
                                                @php
                                                    $percent = ($profitLoss / $Investment) * 100
                                                @endphp
                                                ₹ {{ $profitLoss }} ( {{ round($percent, 2) }}%)
                                            </th>
                                        </tr>
                                        <tr>
                                            <th>@lang('Entry TIME')</th>
                                            <th>@lang('TXN Type')</th>
                                            <th>@lang('Symbol')</th>
                                            <th>@lang('LotSize')</th>
                                            <th>@lang('QTY')</th>
                                            <th>@lang('Entry Price')</th>
                                            <th>@lang('Close Price')</th>
                                            <th>@lang('Investment')</th>
                                            <th>@lang('Current Value')</th>
                                            <th>@lang('Profit')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            
                                            

                                            usort($revArr, function ($a, $b) {
                                                return strtotime($b['time']) - strtotime($a['time']);
                                            });
                                        
                                            $fData = $isFiltered == 1 ? array_slice($revArr,0,5) : $revArr;
                                            $totalItems = 0;
                                            $itemsPerPage = 100;
                                            $currentPage =  isset($_GET['page']) ? $_GET['page'] : 1;

                                            $arrData = $fData;  
                                            $totalItems = count($arrData);
                                            $currentItems = array_slice($arrData, ($currentPage - 1) * $itemsPerPage, $itemsPerPage);
                                        
                                        @endphp
                                        @foreach($currentItems as $val)
                                        <tr>
                                            <td>{{isset($val['time']) ? $val['time']: '-'}}</td>
                                            <td>{{$val['txn']}}</td>
                                            <td>{{$val['symbol']}}</td>
                                            <td>{{$val['lotsize']}}</td>
                                            <td>{{$val['quantity']}}</td>
                                            <td>{{$val['entry_price']}}</td>
                                             <td>{{$val['close_price']}}</td>
                                            <td>{{$val['investment']}}</td>                                            
                                            <td>{{$val['current_value']}}</td>
                                            <td class="{{ $val['profit'] > 0 ?  'text-success' : 'text-danger' }}">{{$val['profit']}}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div>
                            @php
                             if($isFiltered != 1):
                                $totalPages = ceil($totalItems / $itemsPerPage);
                                echo '<nav class="mt-3 justify-content-end d-flex">
                                        <ul class="pagination mb-0">';
                            @endphp
                            @if($totalPages > 1)                
                                @for($i = 1; $i <= $totalPages; $i++)
                                    @if($i == $currentPage)
                                        <li class="page-item active" aria-current="page"><span class="page-link">{{$i}}</span></li>
                                    @else
                                    <li class="page-item"><a class="page-link" href="{{url('user/paper-x-factor?factor_symbol='.request('factor_symbol').'&factor_date='.request('factor_date').'&page='.$i.'')}}">{{$i}}</a></li>
                                    @endif
                                @endfor
                            @endif    
                            @php     
                                echo '  </ul>
                                    </nav>';
                                endif;
                            @endphp
                        </div>
                </div>
            </div>
        @empty
            <div class="row mb-3">
                <div class="col-lg-12">
                    <div class="custom--card">
                       
                        <div class="card-body p-0">
                            <h3 class="text-center text-danger">NO DATA</h3>
                        </div>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
        
    </div>
</section>

@push('script')
<script>
    function reloadData(){
        $.get('{!!url("user/paper-x-factor-combined-ajax?".(isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : ''))!!}',function(data){
            $("#pst_hre").html(data);
        });
    }
    setInterval(() => {
        reloadData();
    }, 60000);//call every 1 minute
    
</script>
@endpush
                                
@endsection
