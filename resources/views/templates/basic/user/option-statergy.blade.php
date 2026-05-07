@extends($activeTemplate . 'layouts.master')

@section('content')
    <div class="pt-100 pb-100 statergy-area">
        <div class="container content-container">
            <div class="row g-3">
                @foreach ($groupedRecords as $marketTrend => $records)
                    <div class="col-lg-3 col-md-4 col-12">
                        <div class="statergy-pannel">
                            <div class="statergy-pannel-header">
                                <h5>@lang($marketTrend)</h5>
                                <i class="las la-arrow-down"></i>
                            </div>
                            <div class="statergy-pannel-body">
                                <div class="row g-1">

                                    @foreach($records as $record)
                                        <div class="col-lg-6 col-md-6 col-12">
                                            <a href="{{route('user.stratergyDetails',$record->id)}}" class="startery-card">

                                                <img src="{{ asset('assets/images/strategy/'.$record['strategy_image'])}}"
                                                    alt="" class="img-fluid statergy-img">
                                                <h6 class="text--base">{{$record->strategy_name}}</h6>

                                                <ul class="list-group list-group-flush">
                                                    <li class="d-flex justify-content-between">
                                                        <span>@lang('legs')</span><span>@lang($record->legs)</span">
                                                    </li>
                                                    <li class="d-flex justify-content-between">
                                                        <span>@lang('Risk')</span><span>@lang($record->risk)</span">
                                                    </li>
                                                    <li class="d-flex justify-content-between">
                                                        <span>@lang('Prof.')</span><span>@lang($record->profit)</span">
                                                    </li>
                                                </ul>

                                            </a>
                                        </div>
                                    @endforeach

                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>


        </div>
    </div>
@endsection
