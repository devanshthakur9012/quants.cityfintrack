@extends($activeTemplate . 'layouts.master')

@section('content')
    <div class="pt-100 pb-100">
        <div class="container content-container">

            <form action="" class="transparent-form mb-3">
                <div class="row">
                    <div class="col-lg-2 form-group">
                        <label>@lang('Symbol Name')</label>
                        <select name="symbol" class="form--control" id="">
                            <option value="">All</option>
                            @foreach ($symbolArr as $v)
                                <option value="{{ $v }}" {{ $v == $selSymbol ? 'selected' : '' }}>{{ $v }}
                                </option>
                            @endforeach
                        </select>
                        {{-- <input type="text" name="search" value="" class="form--control" placeholder="@lang('Stock Name')"> --}}
                    </div>
                    <div class="col-lg-2 form-group">
                        <label>@lang('From Date')</label>
                        <input type="date" name="from_date" class="form--control" value="{{ $fromDate }}" required>
                    </div>
                    <div class="col-lg-2 form-group">
                        <label>@lang('To Date')</label>
                        <input type="date" name="to_date" class="form--control" value="{{ $toDate }}" required>
                    </div>
                    <div class="col-lg-3 form-group mt-auto">
                        <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i>
                            @lang('Filter')</button>
                    </div>
                    <div class="col-lg-3 col-md-3 col-6 form-group mt-auto">
                        <a href="{{ url('/user/predictions') }}" class="btn btn--base w-100"><i class="las la-redo-alt"></i>
                            @lang('Refresh')</a>
                    </div>
                </div>
            </form>
            <div class="row mt-3">
                {{-- @forelse ($data as $key=>$itemArr)
                        @foreach ($itemArr as $k => $value)
                                <div class="col-lg-12 mt-3">
                                    <div class="custom--card">
                                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                                            <h5 class="card-title mb-0" >{{$k}} <span class="text--base">({{date("d-M-Y",strtotime($key))}}) </span></h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive--md table-responsive">
                                                <table class="table custom--table text-nowrap">
                                                    <thead>
                                                        <tr>
                                                            
                                                            <th>AI/ML Model Name</th>
                                                            <th>Open</th>
                                                            <th>High </th>
                                                            <th>Low</th>
                                                            <th>Close</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($value as $item)
                                                            <tr>
                                                                <td>{{$item->model}}</td>
                                                                <td>{{$item->open}}</td>
                                                                <td>{{$item->high}}</td>
                                                                <td>{{$item->low}}</td>
                                                                <td>{{$item->close}}</td>
                                                            </tr>
                                                        @endforeach
                                                        
                                                                                                
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                </div>
                        @endforeach

                    @empty

                        <div class="col-lg-12 mt-3">
                            <div class="custom--card">
                                <div class="card-body p-0">
                                    <h4 class="my-5 text-center text-danger">NO DATA</h4>
                                </div>
                            </div>
                        </div> 

                    @endforelse --}}

                {{-- FOR NEW DATA --}}
                @forelse ($data as $key => $itemArr)
                    @foreach ($itemArr as $k => $value)
                        <div class="col-lg-12 mt-3">
                            <div class="custom--card">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                                    <h5 class="card-title mb-0">{{ $k }} <span class="text--base">({{ date('d-M-Y', strtotime($key)) }}) </span></h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive--md table-responsive">
                                        <table class="table custom--table text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th>AI/ML Model Name</th>
                                                    <th>IDX/STOCK</th>
                                                    <th>Open</th>
                                                    <th>High </th>
                                                    <th>Low</th>
                                                    <th>Close</th>
                                                    <th>CE</th>
                                                    <th>Open</th>
                                                    <th>High </th>
                                                    <th>Low</th>
                                                    <th>Close</th>
                                                    <th>PE</th>
                                                    <th>Open</th>
                                                    <th>High </th>
                                                    <th>Low</th>
                                                    <th>Close</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($value as $modal => $item)
                                                    <tr>
                                                        <td>{{ $modal }}</td>
                                                        @if (isset($item[0]) && in_array($item[0]->instrument_type,['IDX','STOCK']))
                                                            <td>{{$item[0]->symbol}}</td>
                                                            <td>{{$item[0]->open}}</td>
                                                            <td>{{$item[0]->high}}</td>
                                                            <td>{{$item[0]->low}}</td>
                                                            <td>{{$item[0]->close}}</td>
                                                        @elseif (isset($item[1]) && in_array($item[1]->instrument_type,['IDX','STOCK']))
                                                            <td>{{$item[1]->symbol}}</td>
                                                            <td>{{$item[1]->open}}</td>
                                                            <td>{{$item[1]->high}}</td>
                                                            <td>{{$item[1]->low}}</td>
                                                            <td>{{$item[1]->close}}</td>
                                                        @elseif(isset($item[2]) && in_array($item[2]->instrument_type,['IDX','STOCK']))
                                                            <td>{{$item[2]->symbol}}</td>
                                                            <td>{{$item[2]->open}}</td>
                                                            <td>{{$item[2]->high}}</td>
                                                            <td>{{$item[2]->low}}</td>
                                                            <td>{{$item[2]->close}}</td>
                                                        @else
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                        @endif
                                                        @if (isset($item[0]) && $item[0]->instrument_type == "CE")
                                                            <td>{{$item[0]->symbol}}</td>
                                                            <td>{{$item[0]->open}}</td>
                                                            <td>{{$item[0]->high}}</td>
                                                            <td>{{$item[0]->low}}</td>
                                                            <td>{{$item[0]->close}}</td>
                                                        @elseif(isset($item[1]) && $item[1]->instrument_type == "CE")
                                                            <td>{{$item[1]->symbol}}</td>
                                                            <td>{{$item[1]->open}}</td>
                                                            <td>{{$item[1]->high}}</td>
                                                            <td>{{$item[1]->low}}</td>
                                                            <td>{{$item[1]->close}}</td>
                                                        @elseif(isset($item[2]) && $item[2]->instrument_type == "CE")
                                                            <td>{{$item[2]->symbol}}</td>
                                                            <td>{{$item[2]->open}}</td>
                                                            <td>{{$item[2]->high}}</td>
                                                            <td>{{$item[2]->low}}</td>
                                                            <td>{{$item[2]->close}}</td>
                                                        @else
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                        @endif
                                                        @if (isset($item[0]) && $item[0]->instrument_type == "PE")
                                                            <td>{{$item[0]->symbol}}</td>
                                                            <td>{{$item[0]->open}}</td>
                                                            <td>{{$item[0]->high}}</td>
                                                            <td>{{$item[0]->low}}</td>
                                                            <td>{{$item[0]->close}}</td>
                                                        @elseif(isset($item[1]) && $item[1]->instrument_type == "PE")
                                                           <td>{{$item[1]->symbol}}</td>
                                                            <td>{{$item[1]->open}}</td>
                                                            <td>{{$item[1]->high}}</td>
                                                            <td>{{$item[1]->low}}</td>
                                                            <td>{{$item[1]->close}}</td>
                                                        @elseif(isset($item[2]) && $item[2]->instrument_type == "PE")
                                                            <td>{{$item[2]->symbol}}</td>
                                                             <td>{{$item[2]->open}}</td>
                                                             <td>{{$item[2]->high}}</td>
                                                             <td>{{$item[2]->low}}</td>
                                                             <td>{{$item[2]->close}}</td>
                                                        @else
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                        @endif
                                                        {{-- <td>{{ $item->open }}</td>
                                                        <td>{{ $item->high }}</td>
                                                        <td>{{ $item->low }}</td>
                                                        <td>{{ $item->close }}</td> --}}
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                    @endforeach
                @empty
                    <div class="col-lg-12 mt-3">
                        <div class="custom--card">
                            <div class="card-body p-0">
                                <h4 class="my-5 text-center text-danger">NO DATA</h4>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

{{-- TCS,SBILIFE,NTPC,DIVISLAB ,ADANIPORTS  --}}