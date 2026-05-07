@extends($activeTemplate.'layouts.master')
@section('content')

@push('style')
{{-- <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" /> --}}
@endpush


<section class="pt-100 pb-100">
    <div class="container content-container">
        <form action="" class="transparent-form mb-3">
            <div class="row">
                <div class="col-lg-3 form-group">
                    <label>@lang('Symbol Name')</label>
                    <select name="stock_name" class="form--control" id="">
                        <option value="">Select Symbol  Name</option>
                        @foreach ($symbolArr as $v)
                            <option value="{{$v}}" {{$v==$stockName ? 'selected':''}}>{{$v}}</option>
                        @endforeach
                    </select>
                    {{-- <input type="text" name="search" value="" class="form--control" placeholder="@lang('Stock Name')"> --}}
                </div>
                <div class="col-lg-3 form-group">
                    <label>@lang('TimeFrame')</label>
                    <select name="time_frame" class="form--control">
                       @foreach (allTradeTimeFrames() as $item)
                           <option value="{{$item}}" {{$timeFrame==$item ? 'selected':''}}>{{$item}}</option>
                       @endforeach
                    </select>
                </div>
                <div class="col-lg-3 form-group mt-auto">
                    <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i> @lang('Filter')</button>
                </div>
                <div class="col-lg-3 col-md-3 col-6 form-group mt-auto">
                    <a href="{{url('/user/trade-desk-signal')}}" class="btn btn--base w-100"><i class="las la-redo-alt"></i> @lang('Refresh')</a>
                </div>
            </div>
        </form>

        <div id="pst_hre">
            @if($filtered==0)
                @foreach ($finalData as $symbol => $item)
                    <div class="row mb-5">
                        <div class="col-lg-12">
                            <div class="custom--card card">
                                <div class="card-header">
                                    <h6 class="card-title">{{$symbol}}</h6>
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
                                                    <th>VWAP CE</th>
                                                    <th>VWAP PE</th>
                                                    <th>OI CE</th>
                                                    <th>OI PE</th>
                                                    <th>CE CLOSE PRICE</th>
                                                    <th>PE CLOSE PRICE</th>
                                                    <th>BUY ACTION</th>
                                                    <th>SELL ACTION</th>
                                                    <th>STRATEGY NAME</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($item as $val)
                                                    <tr>
                                                        <td>{{$val->date}}</td>
                                                        <td>{{$val->time}}</td>
                                                        <td>{{$val->ce_symbol_name}}</td>
                                                        <td>{{$val->pe_symbol_name}}</td>
                                                        <td>{{$val->ce_vmap}}</td>
                                                        <td>{{$val->pe_vmap}}</td>
                                                        <td>{{$val->ce_oi}}</td>
                                                        <td>{{$val->pe_oi}}</td>
                                                        <td>{{$val->ce_close_price}}</td>
                                                        <td>{{$val->pe_close_price}}</td>
                                                        <td>{{$val->buy_action}}</td>
                                                        <td>{{$val->sell_action}}</td>
                                                        <td>{{$val->strategy_name}}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                
                                </div>
                        
                            </div>
                        </div>
                    </div>
                    
                @endforeach
            @else
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
                                            <th class="text-nowrap">DATE</th>
                                            <th>TIME</th>
                                            <th>CE Symbol Name</th>
                                            <th>PE Symbol Name</th>
                                            <th>VWAP CE</th>
                                            <th>VWAP PE</th>
                                            <th>OI CE</th>
                                            <th>OI PE</th>
                                            <th>CE CLOSE PRICE</th>
                                            <th>PE CLOSE PRICE</th>
                                            <th>BUY ACTION</th>
                                            <th>SELL ACTION</th>
                                            <th>STRATEGY NAME</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($finalData as $val)
                                            <tr>
                                                <td>{{date("d-M-Y",strtotime($val->exchFeedTime_ce))}}</td>
                                                <td>{{date("H:i",strtotime($val->exchFeedTime_ce))}}</td>
                                                <td>{{$val->symbol_ce}}</td>
                                                <td>{{$val->symbol_pe}}</td>
                                                <td>{{$val->vmap_ce}}</td>
                                                <td>{{$val->vmap_pe}}</td>
                                                <td>{{$val->vmap_ce}}</td>
                                                <td>{{$val->vmap_pe}}</td>
                                                <td>{{$val->ltp_ce}}</td>
                                                <td>{{$val->ltp_pe}}</td>
                                                <td>{{"BUY CE"}}</td>
                                                <td>{{"SELL PE"}}</td>
                                                <td>{{"LONG CE, SHORT PE"}}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                        
                        </div>
                
                    </div>
                </div>
            </div>
                <div class="mt-4 justify-content-center d-flex">
                    {{ paginateLinks($finalData)}}
                </div>
            @endif
        </div>    
    </div>
</section>
@endsection

