@extends('admin.layouts.app')


@section('panel')
<div class="row">
    <form action="{{route('uploadZerodhaInstruments')}}" method="POST">
        @csrf
        <div class="col-lg-6">
            <label for="uploadFile">Upload Excel File <span class="text-danger">*</span></label>
            <input type="file" name="uploadFile" id="uploadFile" required>
        </div>
        <div class="col-lg-6">
            <button class="btn btn-outline--primary showFilterBtn btn-sm">Upload</button>
        </div>
    </form>
    <div class="col-lg-12">
        <div class="show-filter mb-3 text-end">
            <button type="button" class="btn btn-outline--primary showFilterBtn btn-sm"><i class="las la-filter"></i>
                Filter</button>
        </div>
        <div class="card responsive-filter-card mb-4">
            <div class="card-body">
                {{-- <form action="#"> --}}
                    <div class="d-flex flex-wrap gap-3">
                       
                        <div class="flex-grow-1">
                            <label>Symbol Name</label>
                            <select class="form-control" id="symbol_type">
                               @foreach ($symbolArr as $item)
                                   <option value="{{$item}}" {{$item==$symbol ? 'selected':''}}>{{$item}}</option>
                               @endforeach
                            </select>
                        </div>
                        <div class="flex-grow-1">
                            <label>Time Frame</label>
                            <select class="form-control" id="time_frame">
                              @foreach (allTradeTimeFrames() as $item)
                                  <option value="{{$item}}" {{$item==$timeFrame ? 'selected':''}}>{{$item}}</option>
                              @endforeach
                            </select>
                        </div>
                       
                        <div class="flex-grow-1 align-self-end">
                            <button class="btn btn--primary w-100 h-45" type="button" id="filter_btn"><i class="fas fa-filter"></i> Filter</button>
                        </div>
                    </div>
                {{-- </form> --}}
            </div>
        </div>
        <div class="card b-radius--10 ">
            <div class="card-body p-0">
                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>CE</th>
                                <th>PE</th>
                                <th>VWAP CE Signal</th>
                                <th>VWAP PE Signal</th>
                                <th>CE_Con Signal</th>
                                <th>PE_Con Signal</th>
                                <th>BUY_Action</th>
                                <th>SELL_Action</th>
                                <th>Strategy Name</th>
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
                                $i=1;
                            @endphp
                            @forelse($atmData as $val)
                                    

                                    @php
                                        $arrData = json_decode($val->data,true);    
                                        // dd($arrData);
                                        $CE = array_slice($arrData['CE'],-5);
                                        $PE = array_slice($arrData['PE'],-5);
                                        $Date = array_slice($arrData['Date'],-5);
                                        $time = array_slice($arrData['time'],-5);
                                        $BUY_Action = array_slice($arrData['BUY_Action'],-5);
                                        $SELL_Action = array_slice($arrData['SELL_Action'],-5);
                                        $Strategy_name = array_slice($arrData['Strategy_name'],-5);
                                        $vwap_CE_signal = array_slice($arrData['vwap_CE_signal'],-5);
                                        $vwap_PE_signal = array_slice($arrData['vwap_PE_signal'],-5);
                                        $CE_consolidated = array_slice($arrData['CE_consolidated'],-5);
                                        $PE_consolidated = array_slice($arrData['PE_consolidated'],-5);
                                    @endphp

                                    @foreach ($CE as $k=>$item)
                                        <tr>
                                            <td>{{$i++}}</td>
                                            <td>{{date("d-M-Y",($Date[$k]/1000))}}</td>
                                            <td>{{$time[$k]}}</td>
                                            <td>{{$item}}</td>
                                            <td>{{$PE[$k]}}</td>
                                            <td>{{$vwap_CE_signal[$k]}}</td>
                                            <td>{{$vwap_PE_signal[$k]}}</td>
                                            <td>{{$CE_consolidated[$k]}}</td>
                                            <td>{{$PE_consolidated[$k]}}</td>
                                            <td>{{$BUY_Action[$k]}}</td>
                                            <td>{{$SELL_Action[$k]}}</td>
                                            <td>{{$Strategy_name[$k]}}</td>
                                        </tr>
                                    @endforeach


                            @empty
                                <tr>
                                    <td colspan="11"><h5 class="text-danger text-center">NO DATA</h5></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table><!-- table end -->
                </div>
            </div>
            <div class="card-footer py-4">
                

            </div>
        </div><!-- card end -->
    </div>
</div>
@endsection

@push('breadcrumb-plugins')

@endpush

@push('script')
    <script>
        $("#filter_btn").on('click',function(){
            $(this).text('Processing...').attr('disabled','disabled');
            var symbol_type = $("#symbol_type").val();
            var time_frame = $("#time_frame").val();
            window.location.href = '{{url("admin/trade/trade-desk-signal")}}?time_frame='+time_frame+'&symbol='+symbol_type
        });
    </script>
@endpush