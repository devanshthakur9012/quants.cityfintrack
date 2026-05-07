@extends('admin.layouts.app')

@section('panel')
@push('style')
<link rel="stylesheet" href="{{asset('assets/admin/css/vendor/select2.min.css')}}">
    <style>
        .select2-container .select2-selection--single {
                width: 100% !important;
                height: 45px;
                line-height: 45px;
                background: transparent;
                border: 1px solid #D7DBDA;
                font-size: 14px;
                color: #A09E9E;
                border-radius: 10px;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 26px;
                position: absolute;
                top: 10px;
                right: 1px;
                width: 20px;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                color: #444;
                line-height: 45px;
            }
            .select2-container{
                width:100% !important;
            }
        </style>
    </style>
@endpush

<div class="row">
    <div class="col-lg-12">
        <form action="{{route('admin.portfolio-insights.top-gainers.remove-stock-portfolio')}}" name="record_frm" id="record_frm" method="post">
            @csrf
                <div class="card responsive-filter-card mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-3">
                            <div class="flex-grow-1">
                                <label>Stock Name</label>
                                <select class="form-control" id="stock_name">
                                    <option value="all">All</option>
                                    @if($stockName!='all')
                                        <option value="{{$stockName}}" selected>{{$stockName}}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="flex-grow-1 align-self-end">
                                <button class="btn btn--primary w-100 h-45" type="button" id="filter_btn"><i class="fas fa-filter"></i> Filter</button>
                            </div>
                            <div class="flex-grow-1 align-self-end">
                                <a class="btn btn--primary w-100 h-45" href="{{url('admin/portfolio-insights/top-gainers')}}"><i class="las la-sync-alt"></i> Refresh</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="w-100 mb-3">
                    <button type="submit" class="btn btn-danger" id="delete_records"><i class="las la-trash-alt"></i> Delete Records</button>
                </div>
                <div class="table-responsive--lg table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                        <tr>
                            <th>
                                <input type="checkbox"  id="checkAll">
                            </th>
                            <th>@lang('Stock Name')</th>
                            <th>@lang('Avg Price')</th>
                            <th>@lang('CMP')</th>
                            <th>@lang('Change%')</th>
                            <th>@lang('Action')</th>
                        </tr>
                        </thead>
                        @php
                        $date = \DB::connection('mysql_pr')->table('LTP')->WHEREIN('symbol',$symbolArray)->pluck('ltp','symbol')->toArray();  
                        @endphp
                        <tbody>
                            @forelse($portfolioTopGainers as $portfolioTopGainer)
                                @php  $key = isset($date[$portfolioTopGainer->stock_name.'.NS']) ? $date[$portfolioTopGainer->stock_name.'.NS'] : 0;
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" name="data[]" value="{{$portfolioTopGainer->id}}" class="checkAll">
                                    </td>
                                    <td>
                                        {{ $portfolioTopGainer->stock_name }}
                                    </td>
                                    <td>
                                        {{ showAmount($portfolioTopGainer->avg_buy_price) }}
                                    </td>
                                    <td>{{showAmount($key)}}</td>
                                    <td>{{ $portfolioTopGainer->change_percentage }}</td>

                                    <td>
                                        <div class="d-flex justify-content-end flex-wrap gap-2">
                                            <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-question="@lang('Are you sure to delete this record')?"
                                                data-action="{{ route('admin.portfolio-insights.top-gainers.delete') }}"
                                                data-hidden_id="{{ $portfolioTopGainer->id }}">
                                                <i class="la la-trash"></i> @lang('Delete')
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table><!-- table end -->
                </div>
            </div>
            @if ($portfolioTopGainers->hasPages())
                <div class="card-footer py-4">
                    {{ paginateLinks($portfolioTopGainers) }}
                </div>
            @endif
        </form>
    </div>
</div>

<x-confirmation-modal />

<!-- Model pop with the form to upload an xls file -->
<div class="modal fade" id="uploadXlsModal" tabindex="-1" role="dialog" aria-labelledby="uploadXlsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadXlsModalLabel">@lang('Upload XLS File')</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.portfolio-insights.top-gainers.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="xlsFile">@lang('Select XLS File')</label>
                        <input type="file" class="form-control" id="xlsFile" name="xlsFile" accept=".xlsx, .xls" required>
                    </div>
                    <button type="submit" class="btn btn-primary">@lang('Upload')</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@if(!request()->routeIs('admin.portfolio-insights.top-gainers'))
    @push('breadcrumb-plugins')
        <a href="{{ route('admin.portfolio-insights.top-gainers.add.page') }}" class="btn btn-sm btn-outline--primary"><i class="las la-plus"></i>@lang('Add New')</a>
        <a href="{{ route('admin.portfolio-insights.top-gainers.download.template') }}" class="btn btn-sm btn-outline--primary"><i class="las la-download"></i>@lang('Download Excel Template')</a>
        <button class="btn btn-sm btn-outline--primary" data-bs-toggle="modal" data-bs-target="#uploadXlsModal"><i class="las la-upload"></i>@lang('Upload via XLS')</button>
    @endpush
@endif


@push('script')
<script src="{{asset('assets/admin/js/vendor/select2.min.js')}}"></script>
<script>    
    $("#stock_name").select2({
        placeholder: "Stock Name",
        multiple: false,
        tokenSeparators: [',', ' '],
        minimumInputLength: 2,
        minimumResultsForSearch: 10,
        ajax: {
            url: "{{ route('admin.portfolio-insights.top-gainers.get-stock-name') }}",
            dataType: "json",
            type: "GET",
            data: function(params) {

                var queryParameters = {
                    term: params.term
                }
                return queryParameters;
            },
            processResults: function(data) {
                return {
                    results: $.map(data, function(item) {
                        return {
                            text: item.stock_name,
                            id: item.stock_name
                        }
                    })
                };
            }
        }
    });
</script>
<script>
    $("#checkAll").on('click',function(){
        if($(this).is(":checked")){
            $(".checkAll").attr('checked','checked').prop("checked",true);
        }else{
            $(".checkAll").removeAttr('checked').prop("checked",false);
        }
    })
</script>
<script>
    $("#filter_btn").on('click',function(){
        var stock_name = $("#stock_name option:selected").val()!='' ? $("#stock_name option:selected").val() : 'all';
        window.location.href = '{{url("admin/portfolio-insights/top-gainers")}}?stock_name='+stock_name;
    });
</script>

<script>
    $("#record_frm").on("submit",function(e){
        e.preventDefault();
        if($(".checkAll:checked").length>0){
            var r=confirm("Are you sure?")
            if (r==true)
            {
                $("#record_frm")[0].submit();
            }
        }else{
            alert("Select one or more records to delete");
        }
    })
</script>

@endpush
