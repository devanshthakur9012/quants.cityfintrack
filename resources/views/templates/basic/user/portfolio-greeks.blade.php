@extends($activeTemplate.'layouts.master')
@section('content')

@push('style')
{{-- <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" /> --}}
@endpush


<section class="pt-100 pb-100">
    <div class="container content-container">
              <div class="mb-1">
                <div class="custom--nav-tabs mb-3">
                    <ul class="nav ">
                        <li class="nav-item">
                            <a class="nav-link" href="{{url('user/portfolio-top-gainers')}}">Index Options</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{url('user/portfolio-top-gainers-stock')}}">Stock Options</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="{{url('user/portfolio-greeks')}}">Greeks Options</a>
                        </li>
                    </ul>
                </div>
            </div>
        <form action="" class="transparent-form mb-3">
            <div class="row">
                <div class="col-lg-3 form-group">
                    <label>@lang('Symbol Name')</label>
                    <select name="stock_name" class="form--control" id="">
                        <option value="">Select Symbol  Name</option>
                        @foreach ($symbolArr as $v)
                            @if(in_array($v,['CRUDEOIL','BANKNIFTY','FINNIFTY','SILVER','NIFTY','MIDCPNIFTY','NATURALGAS','SILVER','GOLD']))
                                <option value="{{$v}}" {{$v==$stockName ? 'selected':''}}>{{$v}}</option>
                             @endif
                        @endforeach
                    </select>
                    {{-- <input type="text" name="search" value="" class="form--control" placeholder="@lang('Stock Name')"> --}}
                </div>
                <div class="col-lg-3 form-group">
                    <label>@lang('TimeFrame')</label>
                    <select name="time_frame" class="form--control">
                       @foreach (allTradeTimeFrames() as $item)
                           <option value="{{$item}}" {{$item==$timeFrame ? 'selected':''}}>{{$item}}</option>
                       @endforeach
                    </select>
                </div>
                <div class="col-lg-3 form-group mt-auto">
                    <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i> @lang('Filter')</button>
                </div>
                <div class="col-lg-3 col-md-3 col-6 form-group mt-auto">
                    <a href="{{url('/user/portfolio-greeks')}}" class="btn btn--base w-100"><i class="las la-redo-alt"></i> @lang('Refresh')</a>
                </div>
            </div>
        </form>

            
        <div class="mb-1">
            <div class="custom--nav-tabs border-0 mb-3">
                <ul class="nav d-flex justify-content-end">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{url('user/portfolio-greeks')}}">Greeks Options</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{url('user/portfolio-greeks-graphs')}}">Greeks Graphs</a>
                    </li>
                </ul>
            </div>
        </div>
        <div id="pst_hre">
           
            @if ($stockName != "")
                @php
                   
                    $data = [];
                    $lastDate = \DB::connection('mysql_rm')->table($stockName)->select('date')->where(['timeframe'=>$timeFrame])->orderBy('date','DESC')->first();
                    if($lastDate){
                        $data = \DB::connection('mysql_rm')->table($stockName)->select('*')->where(['timeframe'=>$timeFrame,'date'=>$lastDate->date])->get();
                    }
            
                @endphp
                <div class="row mb-5">
                    <div class="col-lg-12">
                        <div class="custom--card card">
                            <div class="card-header">
                                <h6 class="card-title">{{$stockName}}</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive--md table-responsive">
                                    <table class="table custom--table text-nowrap">
                                        <thead>
                                            <tr>
                                                {{-- <th>#</th> --}}
                                                <th class="text-nowrap">DATE</th>
                                                <th>TIME</th>
                                                <th>CE Symbol Name</th>
                                                <th>PE Symbol Name</th>
                                                <th>CE IV</th>
                                                <th>PE IV</th>
                                                <th>CE Delta</th>
                                                <th>PE Delta</th>
                                                <th>CE Theta</th>
                                                <th>PE Theta</th>
                                                <th>CE Vega</th>
                                                <th>PE Vega</th>
                                                <th>CE Gamma</th>
                                                <th>PE Gamma</th>
                                                <th>CE IV Sentiment</th>
                                                <th>PE IV Sentiment</th>
                                                <th>CE Theta Sentiment</th>
                                                <th>PE Theta Sentiment</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $atmData = [];
                                                foreach($data as $vvl){
                                                    if(isset($vvl->atm) && $vvl->atm=="ATM"){
                                                        $atmData[] = $vvl;
                                                    }
                                                }
                                                
                                            @endphp
                                            @php  
                                                $totalItems = 0;
                                                $itemsPerPage = 100;
                                                $currentPage =  isset($_GET['page']) ? $_GET['page'] : 1;
                                            @endphp
                                            @forelse($atmData as $val)
                                                    @php
                                                        $arrData = json_decode($val->data,true);  
                                                        $totalItems = count($arrData['Date']);

                                                        $newArr = array_reverse($arrData['Date'],true);
                                                        $currentItems = array_slice($newArr, ($currentPage - 1) * $itemsPerPage, $itemsPerPage,true);
                                                      
                                                    @endphp
                                                    @foreach ($currentItems as $k=>$item)
                                                        @php
                                                        //    echo $k;die;
                                                        @endphp
                                                        <tr>
                                                            {{-- <td>{{$i++}}</td> --}}
                                                            <td>{{date("d-M-Y",($item/1000))}}</td>
                                                            <td>{{$arrData['time'][$k]}}</td>
                                                            <td>{{$arrData['CE'][$k]}}</td>
                                                            <td>{{$arrData['PE'][$k]}}</td>
                                                            <td>{{$arrData['CE_IV'][$k]}}</td>
                                                            <td>{{$arrData['PE_IV'][$k]}}</td>
                                                            <td>{{$arrData['CE_Delta'][$k]}}</td>
                                                            <td>{{$arrData['PE_Delta'][$k]}}</td>
                                                            <td>{{$arrData['CE_Theta'][$k]}}</td>
                                                            <td>{{$arrData['PE_Theta'][$k]}}</td>
                                                            <td>{{$arrData['CE_Vega'][$k]}}</td>
                                                            <td>{{$arrData['PE_Vega'][$k]}}</td>
                                                            <td>{{$arrData['CE_Gamma'][$k]}}</td>
                                                            <td>{{$arrData['PE_Gamma'][$k]}}</td>
                                                            <td>{{$arrData['CE_IV_Sentiment'][$k]}}</td>
                                                            <td>{{$arrData['PE_IV_Sentiment'][$k]}}</td>
                                                            <td>{{$arrData['CE_Theta_Sentiment'][$k]}}</td>
                                                            <td>{{$arrData['PE_Theta_Sentiment'][$k]}}</td>
                                                        </tr>
                                                        @php
                                                            // $k++;
                                                        @endphp
                                                    @endforeach
                                            @empty
                                                <tr>
                                                    <td colspan="100%"><h5 class="text-danger text-center">NO DATA</h5></td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div>
                            @php
                                $totalPages = ceil($totalItems / $itemsPerPage);
                                echo '<nav class="mt-3 justify-content-end d-flex">
                                        <ul class="pagination mb-0">';
                            @endphp
                            @for($i = 1; $i <= $totalPages; $i++)
                                @if($i == $currentPage)
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{$i}}</span></li>
                                @else
                                <li class="page-item"><a class="page-link" href="{{url('user/portfolio-greeks?stock_name='.$stockName.'&time_frame='.$timeFrame.'&page='.$i.'')}}">{{$i}}</a></li>
                                @endif
                            @endfor
                            @php     
                                echo '  </ul>
                                    </nav>';
                            @endphp
                        </div>
                    </div>
                </div>
            @else
                
                @foreach($symbolArr as $v)
                    @php 
                    if(!in_array($v,['CRUDEOIL','BANKNIFTY','FINNIFTY','SILVER','NIFTY','MIDCPNIFTY','NATURALGAS','SILVER','GOLD'])){
                        continue;
                    }
                    if($v == "LTP"){

                    }else{
                        $dataLast = \DB::connection('mysql_rm')->table($v)->select('date')->where(['timeframe'=>$timeFrame])->orderBy('id','DESC')->first();
                        if($dataLast){
                            $todayDate = $dataLast->date;
                        }
                        $data = \DB::connection('mysql_rm')->table($v)->select('*')->where(['date'=>$todayDate,'timeframe'=>$timeFrame])->get(); 

                        // dd($data);
                        // dd($data);
                        // if(count($data)==0){
                        //     $data = \DB::connection('mysql_rm')->table($stockName)->select('*')->where(['timeframe'=>$timeFrame])->get();
                        // }
                    }
                    @endphp
                </pre>
                    <div class="row mb-5">
                        <div class="col-lg-12">
                            <div class="custom--card card">
                                <div class="card-header">
                                    <h6 class="card-title">{{$v}}</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive--md table-responsive">
                                        <table class="table custom--table text-nowrap">
                                            <thead>
                                                <tr>
                                                    {{-- <th>#</th> --}}
                                                    <th class="text-nowrap">DATE</th>
                                                    <th>TIME</th>
                                                    <th>CE Symbol Name</th>
                                                    <th>PE Symbol Name</th>
                                                    <th>CE IV</th>
                                                    <th>PE IV</th>
                                                    <th>CE Delta</th>
                                                    <th>PE Delta</th>
                                                    <th>CE Theta</th>
                                                    <th>PE Theta</th>
                                                    <th>CE Vega</th>
                                                    <th>PE Vega</th>
                                                    <th>CE Gamma</th>
                                                    <th>PE Gamma</th>
                                                    <th>CE IV Sentiment</th>
                                                    <th>PE IV Sentiment</th>
                                                    <th>CE Theta Sentiment</th>
                                                    <th>PE Theta Sentiment</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $atmData = [];
                                                    foreach($data as $vvl){
                                                        if(isset($vvl->atm) && $vvl->atm=="ATM"){
                                                            $atmData[] = $vvl;
                                                        }
                                                    }
                                                @endphp
                                                @php $i=1; @endphp
                                                @forelse($atmData as $val)
                                                        @php
                                                            $arrData = json_decode($val->data,true); 
                                                            $CE = array_slice($arrData['CE'],-5);
                                                            $PE = array_slice($arrData['PE'],-5);
                                                            $Date = array_slice($arrData['Date'],-5);
                                                            $time = array_slice($arrData['time'],-5);
                                                            $CEIV = array_slice($arrData['CE_IV'],-5);
                                                            $PEIV = array_slice($arrData['PE_IV'],-5);
                                                            $CEDelta = array_slice($arrData['CE_Delta'],-5);
                                                            $PEDelta = array_slice($arrData['PE_Delta'],-5);
                                                            $CETheta = array_slice($arrData['CE_Theta'],-5);
                                                            $PETheta = array_slice($arrData['PE_Theta'],-5);
                                                            $CEVega = array_slice($arrData['CE_Vega'],-5);
                                                            $PEVega = array_slice($arrData['PE_Vega'],-5);
                                                            $CEGamma = array_slice($arrData['CE_Gamma'],-5);
                                                            $PEGamma = array_slice($arrData['PE_Gamma'],-5);
                                                            $CE_IV_Sentiment = array_slice($arrData['CE_IV_Sentiment'],-5);
                                                            $PE_IV_Sentiment = array_slice($arrData['PE_IV_Sentiment'],-5);
                                                            $CE_Theta_Sentiment = array_slice($arrData['CE_Theta_Sentiment'],-5);
                                                            $PE_Theta_Sentiment = array_slice($arrData['PE_Theta_Sentiment'],-5);
                                                            
                                                        @endphp
                                                        @foreach ($CE as $k=>$item)
                                                            <tr>
                                                                {{-- <td>{{$i++}}</td> --}}
                                                                <td>{{date("d-M-Y",($Date[$k]/1000))}}</td>
                                                                <td>{{$time[$k]}}</td>
                                                                <td>{{$item}}</td>
                                                                <td>{{$PE[$k]}}</td>
                                                                <td>{{$CEIV[$k]}}</td>
                                                                <td>{{$PEIV[$k]}}</td>
                                                                <td>{{$CEDelta[$k]}}</td>
                                                                <td>{{$PEDelta[$k]}}</td>
                                                                <td>{{$CETheta[$k]}}</td>
                                                                <td>{{$PETheta[$k]}}</td>
                                                                <td>{{$CEVega[$k]}}</td>
                                                                <td>{{$PEVega[$k]}}</td>
                                                                <td>{{$CEGamma[$k]}}</td>
                                                                <td>{{$PEGamma[$k]}}</td>
                                                                <td>{{$CE_IV_Sentiment[$k]}}</td>
                                                                <td>{{$PE_IV_Sentiment[$k]}}</td>
                                                                <td>{{$CE_Theta_Sentiment[$k]}}</td>
                                                                <td>{{$PE_Theta_Sentiment[$k]}}</td>
                                                            </tr>
                                                        @endforeach
                                                @empty
                                                    <tr>
                                                        <td colspan="100%"><h5 class="text-danger text-center">NO DATA</h5></td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>    
    </div>
</section>
@endsection


@push('script')
<script>
    // function reloadData(){
    //     $.get('{!!$fullUrl!!}',function(data){
    //         $("#pst_hre").html(data);
    //     });
    // }

    // setInterval(() => {
    //     reloadData();
    // }, 30000);//call every 1/2 minute
    
</script>
@endpush
