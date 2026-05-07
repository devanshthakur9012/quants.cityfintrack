@extends($activeTemplate.'layouts.master')
@section('content')
@push('style')
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
@endpush
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="text-start">
            <form action="" method="get" id="filter_frm">
                <div class="row">
                    <div class="col-lg-3 form-group">
                        <label>@lang('Broker')</label>
                        <select name="broker_name" class="form--control" id="broker_name">
                            <option value="">All</option>
                            @foreach ($broker_data as $item)
                                <option value="{{$item->id}}" {{$item->id==$brokerId ? 'selected':''}}>{{$item->broker_name.' ('.$item->account_user_name.')'}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 form-group mt-auto">
                        <button class="btn btn--base w-100" id="sub_btn" type="submit"><i class="las la-filter"></i> @lang('Filter')</button>
                    </div>
                </div>
            </form>
        </div>
        @foreach ($trade_book_data as $key=>$trade_book_data)
            <div class="row mt-3">
                <div class="col-lg-12">
                    <div class="custom--card">
                        <div class="card-header d-flex justify-content-between">
                            <h5 class="card-title">{{$key}}</h5>
                            <div class="d-flex">
                                @if(isset($trade_book_data['realised']))
                                <span class="me-3 {{$trade_book_data['realised'] > 0 ? 'text-success':'text-danger'}}">Realised  ({{$trade_book_data['realised']}})</span>
                                <span class="{{$trade_book_data['un_realised'] > 0 ? 'text-success':'text-danger'}}">UnRealised  ({{$trade_book_data['un_realised']}})</span>
                                @endif
                            </div>
                           
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive--md table-responsive transparent-form">
                                <table class="table custom--table text-nowrap">
                                    <thead>
                                       
                                        <tr>
                                            <th>Product Type</th>
                                            <th>cfbuyqty</th>
                                            <th>cfsellqty</th>
                                            <th>buyavgprice</th>
                                            <th>sellavgprice</th>
                                            <th>avgnetprice</th>
                                            <th>netvalue</th>
                                            <th>netqty</th>
                                            <th>totalbuyvalue</th>                                        
                                            <th>totalsellvalue</th>                                        
                                            <th>cfbuyavgprice</th>                                        
                                            <th>cfsellavgprice</th>                                        
                                            <th>totalbuyavgprice</th>                                        
                                            <th>totalsellavgprice</th>                                        
                                            <th>netprice</th>                                        
                                            <th>buyqty</th>                                        
                                            <th>sellqty</th>                                        
                                            <th>buyamount</th>                                        
                                            <th>sellamount</th>                                        
                                            <th>pnl</th>                                        
                                            <th>realised</th>                                        
                                            <th>unrealised</th>                                        
                                            <th>ltp</th>                                        
                                            <th>close</th>                                        
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @forelse ($trade_book_data as $k=>$vl)
                                        @if(!in_array($k,['un_realised','realised']))
                                        <tr>
                                            <td>{{$vl->producttype}}</td>
                                            <td>{{$vl->cfbuyqty}}</td>
                                            <td>{{$vl->cfsellqty}}</td>
                                            <td>{{$vl->buyavgprice}}</td>
                                            <td>{{$vl->sellavgprice}}</td>
                                            <td>{{$vl->avgnetprice}}</td>
                                            <td>{{$vl->netvalue}}</td>
                                            <td>{{$vl->netqty}}</td>
                                            <td>{{$vl->totalbuyvalue}}</td>
                                            <td>{{$vl->totalsellvalue}}</td>
                                            <td>{{$vl->cfbuyavgprice}}</td>
                                            <td>{{$vl->cfsellavgprice}}</td>
                                            <td>{{$vl->totalbuyavgprice}}</td>
                                            <td>{{$vl->totalsellavgprice}}</td>
                                            <td>{{$vl->netprice}}</td>
                                            <td>{{$vl->buyqty}}</td>
                                            <td>{{$vl->sellqty}}</td>
                                            <td>{{$vl->buyamount}}</td>
                                            <td>{{$vl->sellamount}}</td>
                                            <td>{{$vl->pnl}}</td>
                                            <td>{{$vl->realised}}</td>
                                            <td>{{$vl->unrealised}}</td>
                                            <td>{{$vl->ltp}}</td>
                                            <td>{{$vl->close}}</td>
                                            
                                        </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="100%">NO DATA</td>
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
        

        <div class="mt-4 justify-content-center d-flex">
            {{-- {{ paginateLinks($trade_book_data) }} --}}
        </div>
    </div>
</section>

@endsection

@push('script')
    <script>
        $("#filter_frm").on('submit',function(e){
            e.preventDefault();
            $("#filter_frm")[0].submit();
        });
    </script>
@endpush


