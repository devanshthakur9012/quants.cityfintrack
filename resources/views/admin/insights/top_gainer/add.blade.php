@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-md-12">
            <div class="card-body ps-0 pe-0">
                <form action="{{ route('admin.portfolio-insights.top-gainers.add.submit')}}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-xl-12 mt-xl-0">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xxl-12">
                                            <div class="form-group">
                                                <label>@lang('Stock Name')</label>
                                                <input type="text" class="form-control" name="stock_name" required value="{{ old('stock_name') }}">
                                            </div>
                                        </div>
                                        <div class="col-xxl-12">
                                            <div class="form-group">
                                                <label>@lang('Avg Buy Price')</label>
                                                <input type="text" class="form-control" name="avg_buy_price" required value="{{ old('avg_buy_price') }}">
                                            </div>
                                        </div>
                                        <div class="col-xxl-12">
                                            <div class="form-group">
                                                <label>@lang('CMP')</label>
                                                <input type="text" class="form-control" name="cmp" required value="{{ old('cmp') }}">
                                            </div>
                                        </div>
                                        <div class="col-xxl-12">
                                            <div class="form-group">
                                                <label>@lang('Change %')</label>
                                                <input type="text" class="form-control" name="change_percentage" required value="{{ old('change_percentage') }}">
                                            </div>
                                        </div>
                                        <div class="col-xxl-12 mt-3 border-top pt-4">
                                            <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.portfolio-insights.top-gainers.all') }}" />
@endpush
