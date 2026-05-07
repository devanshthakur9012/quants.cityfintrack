@extends('admin.layouts.app')

@section('panel')


    <div class="row">
        <div class="col-lg-12">
            <div class="card b-radius--10 ">
                <div class="card-body p-0">
                    <div class="table-responsive--lg">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>S.No</th>
                                    <th>Name</th>
                                    <th>legs</th>
                                    <th>Risk</th>
                                    <th>Prof</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @isset($data)
                                    @foreach ($data as $record)
                                        <tr>
                                            <td>{{ ++$loop->index }}</td>
                                            <td><span>{{ $record->strategy_name }}</span></td>
                                            <td>{{ $record->legs }}</td>
                                            <td>{{ $record->risk }}</td>
                                            <td>{{ $record->profit }}</td>
                                            <td>{{ $record->market_trend}}</td>
                                            <td><span class="@if ($record->strategy_status == 'Enable') 
                                                text--small badge font-weight-normal badge--success
                                                @else  
                                                text--small badge font-weight-normal badge--danger 
                                                @endif ">{{ $record->strategy_status }}</span></td>

                                            <td>
                                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                                    <a href="{{route('admin.portfolio-insights.strategy.edit',$record->id)}}" class="btn btn-sm btn-outline--primary">
                                                        <i class="la la-pencil"></i> Edit </a>
                                                    <a href="{{route('admin.portfolio-insights.strategy.delete',$record->id)}}" class="btn btn-sm btn-outline--danger confirmationBtn">
                                                        <i class="la la-trash"></i> Delete </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endisset


                            </tbody>
                        </table><!-- table end -->
                    </div>
                </div>

            </div>
        </div>
    </div>


@endsection

@if (request()->routeIs('admin.portfolio-insights.strategy.all'))

    @push('breadcrumb-plugins')
        <a href="{{ route('admin.portfolio-insights.strategy.add.page') }}" class="btn btn-sm btn-outline--primary"><i
                class="las la-plus"></i>@lang('Add New')</a>
    @endpush
@endif


@push('script')
